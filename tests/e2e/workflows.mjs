// End-to-end workflow tests for BlogFlow CMS (Playwright + HTTP checks).
//
// Requires a FRESH, uninstalled copy of /blogs/ served by Apache and an empty MySQL database.
//   BF_BASE=http://blog.test:8081/blogs BF_DB=blogflow2 node tests/e2e/workflows.mjs
// The hostname is mapped to 127.0.0.1 inside Chromium, so no /etc/hosts change is needed.
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const FIX = resolve(here, '../fixtures/out');
const SHOTS = process.env.BF_SHOTS || resolve(here, '../../tests/e2e/screenshots');
mkdirSync(SHOTS, { recursive: true });
const BASE = (process.env.BF_BASE || 'http://blog.test:8081/blogs').replace(/\/$/, '');
const HOST = new URL(BASE).hostname;
const DB = { host: '127.0.0.1', port: '3306', name: process.env.BF_DB || 'blogflow2', user: process.env.BF_DB_USER || 'blog', pass: process.env.BF_DB_PASS || 'blogpass', prefix: 'cms_' };
const ADMIN = { name: 'Meera Shah', email: 'meera@harbour.example', password: 'Lighthouse-Anchor-77' };

const results = [];
let current = '';
function step(name) { current = name; console.log('\n▶ ' + name); }
function check(label, cond, detail = '') {
  results.push({ step: current, label, ok: !!cond, detail });
  console.log(`  ${cond ? '✓' : '✕'} ${label}${!cond && detail ? ' — ' + detail : ''}`);
}
const sql = (q) => execSync(`mysql -N -B --raw ${DB.name} -e ${JSON.stringify(q)}`).toString().trim();

const chromePath = process.env.BF_CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const browser = await chromium.launch({
  args: [`--host-resolver-rules=MAP ${HOST} 127.0.0.1`],
  ...(existsSync(chromePath) ? { executablePath: chromePath } : {}),
});
const ctx = await browser.newContext({ viewport: { width: 1360, height: 900 } });
const page = await ctx.newPage();
const jsErrors = [];
page.on('pageerror', (e) => jsErrors.push(e.message));
page.on('dialog', (d) => d.accept());
const http = ctx.request;
const csrf = async () => page.locator('meta[name="csrf-token"]').getAttribute('content');

async function flashText() {
  const el = page.locator('[data-flash]');
  return (await el.count()) ? (await el.innerText()) : '';
}
async function confirmModal() {
  const ok = page.locator('.modal-backdrop [data-ok]');
  await ok.waitFor({ timeout: 5000 });
  await ok.click();
}

try {
  // 1. Fresh installation ----------------------------------------------------
  step('1. Fresh installation on an empty database');
  await page.goto(BASE + '/');
  check('Uninstalled site redirects to the installer', page.url().includes('/install/'));
  await page.locator('#rewrite-check [data-detail]').filter({ hasText: /Working|Not detected/ }).waitFor();
  check('Installer detects URL rewriting', (await page.locator('#rewrite-check [data-detail]').innerText()).startsWith('Working'));
  await page.click('a[href="?step=database"]');
  await page.fill('#f-host', DB.host);
  await page.fill('#f-port', DB.port);
  await page.fill('#f-name', DB.name);
  await page.fill('#f-user', DB.user);
  await page.fill('#f-pass', 'wrong-password');
  await page.fill('#f-prefix', DB.prefix);
  await page.click('button[type=submit]');
  check('Wrong DB password is reported without crashing', (await page.content()).includes('Access denied'));
  await page.fill('#f-pass', DB.pass);
  await page.click('button[type=submit]');
  await page.waitForURL(/step=site/);
  check('Blog URL is pre-filled from the request', (await page.inputValue('#f-blog_url')) === BASE, await page.inputValue('#f-blog_url'));
  await page.fill('#f-blog_name', 'Harbour Law Journal');
  await page.fill('#f-main_site_name', 'Harbour & Co. Advocates');
  await page.fill('#f-main_site_url', new URL(BASE).origin + '/');
  await page.selectOption('#f-timezone', 'Europe/London');
  await page.fill('#f-language', 'en-GB');
  await page.check('input[name=preset][value=corporate]');
  await page.click('button[type=submit]');
  await page.waitForURL(/step=admin/);
  await page.fill('#f-name', ADMIN.name);
  await page.fill('#f-email', ADMIN.email);
  await page.fill('#f-password', 'password1');
  await page.fill('#f-password_confirm', 'password1');
  await page.click('button[type=submit]');
  check('Weak administrator password is rejected', (await page.content()).includes('Password must be at least'));
  await page.fill('#f-password', ADMIN.password);
  await page.fill('#f-password_confirm', ADMIN.password);
  await page.click('button[type=submit]');
  await page.waitForURL(/step=finish/);
  await page.click('button:has-text("Install BlogFlow")');
  await page.waitForURL(/admin\/login/);
  check('Installer finishes on the admin login page', (await page.content()).includes('Installation complete'));
  check(`Tables created with prefix ${DB.prefix}`, sql(`SHOW TABLES LIKE '${DB.prefix}posts'`) === `${DB.prefix}posts`);
  check('No default credentials exist (only the created admin)', sql(`SELECT COUNT(*) FROM ${DB.prefix}users`) === '1');

  // 23. Installer locked -----------------------------------------------------
  step('23. Installation is locked after setup');
  let r = await http.get(BASE + '/install/');
  check('Installer returns 403 after installation', r.status() === 403, String(r.status()));
  r = await http.get(BASE + '/install/?step=finish');
  check('Installer steps are also locked', r.status() === 403);
  r = await http.get(BASE + '/config/config.php');
  check('Config file is not web-accessible', r.status() === 403, String(r.status()));
  r = await http.get(BASE + '/storage/installed.lock');
  check('Storage is not web-accessible', r.status() === 403);

  // 22. Protected admin pages without login ------------------------------------
  step('22. Protected admin pages are inaccessible without login');
  const anon = await browser.newContext();
  for (const p of ['/admin/', '/admin/posts', '/admin/settings/general', '/admin/backup', '/admin/users']) {
    const res = await anon.request.get(BASE + p, { maxRedirects: 0 });
    check(`GET ${p} redirects to login`, res.status() === 302 && (res.headers().location || '').includes('/admin/login'), String(res.status()));
  }
  const apiRes = await anon.request.get(BASE + '/admin/api/media', { headers: { Accept: 'application/json' } });
  check('Admin JSON API returns 401 without a session', apiRes.status() === 401);
  const csrfRes = await anon.request.post(BASE + '/admin/posts', { form: { title: 'x' }, maxRedirects: 0 });
  check('POST without CSRF token is rejected (403)', csrfRes.status() === 403, String(csrfRes.status()));
  const adminHeaders = (await anon.request.get(BASE + '/admin/login')).headers();
  check('Admin pages send noindex and CSP headers', (adminHeaders['x-robots-tag'] || '').includes('noindex') && (adminHeaders['content-security-policy'] || '').includes("script-src 'self'"));

  // 2. Login -----------------------------------------------------------------
  step('2. Secure administrator login');
  await page.fill('#email', ADMIN.email);
  await page.fill('#password', 'not-the-password');
  await page.click('button[type=submit]');
  check('Wrong password shows a generic error', (await page.content()).includes('The email or password is incorrect.'));
  const isSess = (c) => /^bf_[a-f0-9]{12}$/.test(c.name);
  const cookieBefore = (await ctx.cookies()).find(isSess)?.value;
  await page.fill('#email', ADMIN.email);
  await page.fill('#password', ADMIN.password);
  await page.click('button[type=submit]');
  await page.waitForURL(/\/admin\/$/);
  const sess = (await ctx.cookies()).find(isSess);
  check('Login succeeds and lands on the dashboard', (await page.content()).includes('Welcome back'));
  check('Session ID is regenerated on login', sess && sess.value !== cookieBefore);
  check('Session cookie is HttpOnly + SameSite=Lax + scoped to /blogs/', sess && sess.httpOnly && sess.sameSite === 'Lax' && sess.path === new URL(BASE).pathname + '/', JSON.stringify(sess));

  // 3. Category ----------------------------------------------------------------
  step('3. Create a category');
  await page.goto(BASE + '/admin/categories/new');
  await page.fill('input[name="values[name]"]', 'Family Law');
  await page.fill('textarea[name="values[description]"]', 'Guides on divorce, custody and family matters in England and Wales.');
  await page.click('button:has-text("Create category")');
  check('Category created', (await flashText()).includes('Category created'));
  check('Slug generated from the name', sql(`SELECT slug FROM ${DB.prefix}categories WHERE name='Family Law'`) === 'family-law');

  // 4. Author with photo (media picker + upload) --------------------------------
  step('4. Create an author');
  await page.goto(BASE + '/admin/authors/new');
  await page.fill('input[name="values[display_name]"]', 'Ravi Kumar');
  await page.fill('input[name="values[job_title]"]', 'Senior Associate, Family Law');
  await page.fill('textarea[name="values[bio]"]', 'Ravi advises parents on custody arrangements and mediation, with fifteen years of experience in the family courts.');
  await page.fill('input[name="values[social_linkedin]"]', 'https://www.linkedin.com/in/example');
  await page.click('[data-media-field] [data-media-choose]');
  await page.setInputFiles('.modal-backdrop input[data-upload-input]', resolve(FIX, 'author-photo.jpg'));
  await page.locator('.modal-backdrop [data-media-select]:not([disabled])').waitFor();
  await page.click('.modal-backdrop [data-media-select]');
  await page.click('button:has-text("Create author")');
  check('Author created', (await flashText()).includes('Author created'));
  check('Author photo stored', sql(`SELECT photo_id IS NOT NULL FROM ${DB.prefix}authors WHERE slug='ravi-kumar'`) === '1');

  // 5. Upload images ---------------------------------------------------------------
  step('5. Upload an image');
  await page.goto(BASE + '/admin/media');
  await Promise.all([
    page.waitForEvent('load'),
    page.setInputFiles('[data-library-dropzone] input[type=file]', [resolve(FIX, 'beach-sunset.jpg'), resolve(FIX, 'rotated-phone-photo.jpg'), resolve(FIX, 'logo-mark.png')]),
  ]);
  await page.waitForLoadState('networkidle');
  const media = JSON.parse(execSync(`mysql -N -B --raw ${DB.name} -e "SELECT JSON_OBJECT('id',id,'path',path,'w',width,'h',height,'size',filesize,'orig',original_filesize,'ow',original_width,'variants',variants) FROM ${DB.prefix}media WHERE original_name='beach-sunset.jpg'"`).toString());
  check('Large image resized to the maximum width (no upscaling)', media.w === 2400 && media.h === 1500, `${media.w}x${media.h}`);
  check('Optimised file is smaller than the upload', media.size < media.orig, `${media.size} < ${media.orig}`);
  const variants = JSON.parse(media.variants);
  check('Responsive sizes generated (320/640/960/1440)', ['320', '640', '960', '1440'].every((k) => variants.sizes[k]), Object.keys(variants.sizes).join(','));
  check('WebP derivatives generated', !!variants.full.webp && !!variants.sizes['640'].webp);
  const webp = await http.get(BASE + '/uploads/' + variants.sizes['640'].webp);
  check('WebP variant is served with image/webp', webp.status() === 200 && webp.headers()['content-type'] === 'image/webp');
  const rotated = sql(`SELECT CONCAT(width,'x',height) FROM ${DB.prefix}media WHERE original_name='rotated-phone-photo.jpg'`);
  check('EXIF orientation corrected (300x200 stored rotated → 200x300)', rotated === '200x300', rotated);
  check('PNG transparency kept as PNG', sql(`SELECT mime FROM ${DB.prefix}media WHERE original_name='logo-mark.png'`) === 'image/png');
  const fake = await http.post(BASE + '/admin/media/upload', { multipart: { file: { name: 'fake.jpg', mimeType: 'image/jpeg', buffer: readFileSync(resolve(FIX, 'fake.jpg')) } }, headers: { 'X-CSRF-Token': await csrf(), 'X-Requested-With': 'fetch', Accept: 'application/json' } });
  check('Disguised script upload is rejected', fake.status() === 422, String(fake.status()));
  const shell = await http.post(BASE + '/admin/media/upload', { multipart: { file: { name: 'shell.php.jpg', mimeType: 'image/jpeg', buffer: readFileSync(resolve(FIX, 'shell.php.jpg')) } }, headers: { 'X-CSRF-Token': await csrf(), 'X-Requested-With': 'fetch', Accept: 'application/json' } });
  check('Double-extension upload is rejected', shell.status() === 422);

  // 6. Optimise ----------------------------------------------------------------------
  step('6. Optimise the image');
  await page.goto(BASE + '/admin/media/' + media.id);
  const reduction = await page.locator('dt:has-text("Reduction") + dd').innerText();
  check('Media page shows original/optimised sizes and reduction', /\d+%/.test(reduction), reduction);
  await page.click('button:has-text("Re-optimise")');
  check('Single image re-optimisation works', (await flashText()).includes('Optimised'));
  await page.goto(BASE + '/admin/settings/images');
  await page.fill('input[name="values[jpeg_quality]"]', '76');
  await page.click('button:has-text("Save changes")');
  await page.goto(BASE + '/admin/media/optimize');
  check('Changed settings mark images for bulk optimisation', (await page.locator('[data-run-start]').isEnabled()));
  await page.click('[data-run-start]');
  await page.locator('[data-run-label]').filter({ hasText: 'All images are optimised' }).waitFor({ timeout: 60000 });
  check('Bulk optimisation runs in resumable batches to completion', true);

  // 7–8. Create post in the editor, save draft --------------------------------------
  step('7. Create a blog post using the rich text editor');
  await page.goto(BASE + '/admin/posts/new');
  await page.fill('#title', 'How Child Custody Is Decided in England');
  const ed = page.locator('.ProseMirror');
  await ed.click();
  await page.keyboard.type('Courts focus on one question above all: what arrangement is best for the child? This guide explains how child custody decisions are made.');
  await page.keyboard.press('Enter');
  await page.keyboard.type('## The welfare checklist');
  await page.keyboard.press('Enter');
  await page.keyboard.type('The court weighs several factors, including the child’s needs and the capability of each parent.');
  await page.keyboard.press('Enter');
  await page.keyboard.type('### Wishes of the child');
  await page.keyboard.press('Enter');
  await page.keyboard.type('Older children are listened to carefully. Read the ');
  await page.keyboard.press('Control+k');
  await page.fill('#ld-url', 'https://www.gov.uk/looking-after-children-divorce');
  await page.fill('#ld-text', 'official guidance');
  await page.check('#ld-tab');
  await page.click('.modal-backdrop button[type=submit]');
  await page.keyboard.type(' for details.');
  await page.keyboard.press('Enter');
  await page.click('button[aria-label="Bulleted list"]');
  await page.keyboard.type('Physical and emotional needs');
  await page.keyboard.press('Enter');
  await page.keyboard.type('Age and background');
  await page.keyboard.press('Enter');
  await page.keyboard.press('Enter');
  await page.click('button[aria-label="Insert image"]');
  await page.locator('.modal-backdrop [data-media-grid] button').first().waitFor();
  await page.locator('.modal-backdrop [data-media-grid] button[aria-label="Beach Sunset"]').click();
  await page.click('.modal-backdrop [data-media-select]');
  await page.locator('.ProseMirror figure img').click();
  await page.click('button[aria-label="Edit ALT text"]');
  await page.fill('#alt-text', 'Sunset over a quiet beach');
  await page.click('.modal-backdrop button[type=submit]');
  await page.click('button[aria-label="Width 50%"]');
  await page.click('button[aria-label="Insert table"]');
  check('Editor produced H2/H3/list/figure/table markup', await page.evaluate(() => {
    const h = document.querySelector('.ProseMirror').innerHTML;
    return h.includes('<h2') && h.includes('<h3') && h.includes('<ul') && h.includes('<figure') && h.includes('<table') && h.includes('data-width="50"');
  }));
  check('Word count is shown', Number(await page.locator('[data-word-count]').innerText()) > 40);
  await page.fill('#excerpt', 'A plain-English guide to how family courts in England decide where children live and how much time they spend with each parent.');
  await page.selectOption('#category_id', { label: 'Family Law' });
  await page.selectOption('#author_id', { label: 'Ravi Kumar' });
  await page.fill('#tags', 'custody, children, family courts');
  await page.locator('#featured_image_id').locator('xpath=..').locator('[data-media-choose]').click();
  await page.locator('.modal-backdrop [data-media-grid] button[aria-label="Beach Sunset"]').click();
  await page.click('.modal-backdrop [data-media-select]');
  await page.fill('#featured_image_alt', 'Calm beach at sunset representing a fresh start');
  await page.click('[data-faq-add]');
  await page.fill('[data-faq-item] input', 'Do children get to choose who they live with?');
  await page.fill('[data-faq-item] textarea', 'Not on their own, but the court gives more weight to the views of older, mature children.');
  await page.fill('#focus_keyword', 'child custody');
  await page.fill('#meta_description', 'How family courts in England decide child custody: the welfare checklist, children’s wishes and what parents can expect.');
  await page.click('button[data-tab="seo-analysis"]');
  await page.waitForTimeout(1500);
  check('Live SEO analysis lists checks', (await page.locator('[data-seo-checks] li').count()) > 8);

  step('8. Save the post as draft');
  await page.click('button[name=action][value=draft]');
  await page.waitForURL(/\/admin\/posts\/\d+\/edit/);
  check('Draft saved', (await flashText()).includes('Draft saved'));
  const postId = page.url().match(/posts\/(\d+)\/edit/)[1];
  const slug = await page.inputValue('#slug');
  check('Slug generated', slug === 'how-child-custody-is-decided-in-england', slug);
  r = await http.get(BASE + '/' + slug, { maxRedirects: 0 });
  check('Draft is not public (404)', r.status() === 404, String(r.status()));
  const stored = sql(`SELECT content_html FROM ${DB.prefix}posts WHERE id=${postId}`);
  check('Stored HTML is sanitised and keeps structure', stored.includes('<figure') && stored.includes('data-media-id') && !stored.includes('<script'));

  // 9. Preview ------------------------------------------------------------------------------
  step('9. Preview the post');
  const [preview] = await Promise.all([ctx.waitForEvent('page'), page.click('[data-preview]')]);
  await preview.waitForLoadState('domcontentloaded');
  await preview.waitForURL(/preview\/\d+/);
  check('Preview renders the draft', (await preview.locator('h1').innerText()).includes('Child Custody'));
  check('Preview is noindex', (await preview.locator('meta[name=robots]').getAttribute('content')).includes('noindex'));
  const prevHead = await http.get(preview.url());
  check('Preview sends X-Robots-Tag noindex and no-store', (prevHead.headers()['x-robots-tag'] || '').includes('noindex') && (prevHead.headers()['cache-control'] || '').includes('no-store'));
  const anonPrev = await anon.request.get(preview.url().split('?')[0], { maxRedirects: 0 });
  check('Preview is not visible to the public without the token', anonPrev.status() === 404);
  await preview.close();

  // 10–11. Publish and view ---------------------------------------------------------------
  step('10. Publish the post');
  await page.click('[data-publish-btn]');
  await page.waitForURL(/edit/);
  check('Post published', (await flashText()).includes('Post published'));
  step('11. Open the public article URL');
  const postUrl = BASE + '/' + slug;
  r = await http.get(postUrl);
  const html = await r.text();
  check('Article returns 200', r.status() === 200);
  check('Exactly one H1', (html.match(/<h1[\s>]/g) || []).length === 1);
  check('Responsive <picture> with AVIF/WebP sources, srcset, sizes, width/height', html.includes('<picture>') && html.includes('type="image/webp"') && /srcset="[^"]+ 640w/.test(html) && /<img[^>]+width="\d+" height="\d+"/.test(html));
  check('Hero image eager + fetchpriority=high, body images lazy', html.includes('fetchpriority="high"') && html.includes('loading="lazy"'));
  const govLink = (html.match(/<a[^>]*gov\.uk[^>]*>/) || [''])[0];
  check('External link opened in new tab gets noopener noreferrer', govLink.includes('target="_blank"') && govLink.includes('rel="noopener noreferrer"'), govLink);
  check('Headings have anchor IDs and TOC is rendered (3+ headings)', /<h2 id="the-welfare-checklist"/.test(html));
  check('FAQ is visible on the page', html.includes('Do children get to choose who they live with?'));
  check('Author bio shown', html.includes('Senior Associate, Family Law'));

  // 12–13. SEO metadata + JSON-LD -------------------------------------------------------------
  step('12. Verify SEO metadata');
  const meta = (re) => (html.match(re) || [])[1];
  check('Title uses the template', meta(/<title>([^<]+)<\/title>/) === 'How Child Custody Is Decided in England | Harbour &amp; Co. Advocates', meta(/<title>([^<]+)<\/title>/));
  check('Meta description', (meta(/<meta name="description" content="([^"]+)"/) || '').startsWith('How family courts in England decide child custody'));
  check('Self-referencing absolute canonical', meta(/<link rel="canonical" href="([^"]+)"/) === postUrl);
  check('Robots index, follow with snippet controls', (meta(/<meta name="robots" content="([^"]+)"/) || '').startsWith('index, follow, max-image-preview:large'));
  check('Open Graph article tags', html.includes('<meta property="og:type" content="article">') && html.includes(`<meta property="og:url" content="${postUrl}">`) && /og:image" content="http[^"]+\/uploads\//.test(html));
  check('og:locale from language', html.includes('content="en_GB"'));
  check('X card metadata', html.includes('<meta name="twitter:card" content="summary_large_image">'));
  check('article:published_time and modified_time', html.includes('article:published_time') && html.includes('article:modified_time'));
  check('html lang attribute', html.includes('<html lang="en-GB"'));
  step('13. Verify JSON-LD');
  const ld = JSON.parse(meta(/<script type="application\/ld\+json">(.+?)<\/script>/s));
  const types = ld['@graph'].map((n) => n['@type']);
  check('JSON-LD parses and contains the expected types', ['Organization', 'WebPage', 'BlogPosting', 'BreadcrumbList', 'FAQPage'].every((t) => types.includes(t)), types.join(','));
  const art = ld['@graph'].find((n) => n['@type'] === 'BlogPosting');
  check('BlogPosting has required properties', ['headline', 'description', 'image', 'datePublished', 'dateModified', 'author', 'publisher', 'mainEntityOfPage'].every((k) => art[k]));
  check('Author is a Person with URL and sameAs', art.author['@type'] === 'Person' && art.author.url.endsWith('/author/ravi-kumar') && (art.author.sameAs || []).length > 0);
  check('Dates are ISO 8601 with timezone offset', /T\d\d:\d\d:\d\d[+-]\d\d:\d\d$/.test(art.datePublished));
  const crumbs = ld['@graph'].find((n) => n['@type'] === 'BreadcrumbList').itemListElement.map((i) => i.name);
  check('Breadcrumbs: Home › Blog › Category › Post', crumbs.join(' › ') === 'Home › Harbour Law Journal › Family Law › How Child Custody Is Decided in England', crumbs.join(' › '));

  // 14. Sitemap ---------------------------------------------------------------------------------
  step('14. Verify sitemap inclusion');
  r = await http.get(BASE + '/sitemap.xml');
  const sm = await r.text();
  check('sitemap.xml served as application/xml', r.status() === 200 && r.headers()['content-type'].startsWith('application/xml'));
  check('Post URL listed with lastmod and image', sm.includes(`<loc>${postUrl}</loc><lastmod>`) && sm.includes('<image:loc>'));
  check('Indexable category & author listed; tags (noindex by default) omitted', sm.includes('/category/family-law</loc>') && sm.includes('/author/ravi-kumar</loc>') && !sm.includes('/tag/'));
  r = await http.get(BASE + '/feed.xml');
  const feed = await r.text();
  check('RSS feed served and includes the post', r.headers()['content-type'].startsWith('application/rss+xml') && feed.includes(`<link>${postUrl}</link>`));

  // 15. Slug change → 301 -------------------------------------------------------------------------
  step('15. Change the slug and confirm 301 redirection');
  await page.goto(BASE + `/admin/posts/${postId}/edit`);
  await page.fill('#slug', 'child-custody-england');
  await page.locator('[data-redirect-option]').waitFor({ state: 'visible' });
  check('Redirect option appears for a published post', await page.isChecked('input[name=create_redirect]'));
  await page.click('[data-publish-btn]');
  await page.waitForURL(/edit/);
  check('Notice confirms redirect creation', (await flashText()).includes('301 redirect created'));
  r = await anon.request.get(postUrl, { maxRedirects: 0 });
  const newUrl = BASE + '/child-custody-england';
  check('Old URL returns 301 to the new URL', r.status() === 301 && r.headers().location === newUrl, `${r.status()} ${r.headers().location}`);
  r = await anon.request.get(newUrl);
  check('New URL returns 200', r.status() === 200);
  r = await anon.request.get(BASE + '/child-custody-england/', { maxRedirects: 0 });
  check('Trailing-slash variant 301s to the canonical form', r.status() === 301 && r.headers().location.endsWith('/child-custody-england'));
  r = await anon.request.get(BASE + '/does-not-exist', { maxRedirects: 0 });
  check('Missing post returns a real 404 with noindex', r.status() === 404 && (r.headers()['x-robots-tag'] || '').includes('noindex'));
  r = await anon.request.get(BASE + '/page/1', { maxRedirects: 0 });
  check('/page/1 redirects to the canonical first page', r.status() === 301);
  r = await anon.request.get(BASE + '/page/99');
  check('Out-of-range pagination returns 404', r.status() === 404);

  // 16. Theme colours -----------------------------------------------------------------------------
  step('16. Change the theme colours');
  await page.goto(BASE + '/admin/appearance/theme');
  await page.fill('input[name="values[color_accent]"]', '#0F766E');
  await page.fill('input[name="values[color_accent_contrast]"]', '#FFFFFF');
  await page.locator('iframe[data-theme-preview]').waitFor();
  const previewAccent = await page.frameLocator('iframe[data-theme-preview]').locator('html').evaluate((el) => el.style.getPropertyValue('--bf-accent'));
  check('Live preview updates before saving', previewAccent === '#0F766E', previewAccent);
  await page.click('button:has-text("Save changes")');
  check('Theme saved', (await flashText()).includes('Appearance saved'));
  const home = await (await anon.request.get(BASE + '/')).text();
  check('Public pages use the new accent colour', home.includes('--bf-accent:#0F766E'));
  check('Corporate preset fonts applied (Manrope/Inter)', home.includes('Manrope Variable') && home.includes('Inter Variable'));

  // 17. Navigation link ---------------------------------------------------------------------------
  step('17. Add an external navigation link');
  await page.goto(BASE + '/admin/appearance/menus');
  await page.click('[data-add-item]');
  const row = page.locator('[data-menu-item]').last();
  await row.locator('[data-field=label]').fill('Book a consultation');
  await row.locator('[data-field=link_type]').selectOption('custom');
  await row.locator('[data-field=url]').fill('https://harbour-advocates.example/contact');
  await row.locator('[data-field=new_tab]').check();
  await page.click('[data-save-menu]');
  await page.locator('#toasts').filter({ hasText: 'Menu saved' }).waitFor();
  const home2 = await (await anon.request.get(BASE + '/')).text();
  check('Header shows the external link with safe attributes', /href="https:\/\/harbour-advocates\.example\/contact" target="_blank" rel="noopener noreferrer"[^>]*>Book a consultation/.test(home2));
  check('Main website "Home" link present', home2.includes(`href="${new URL(BASE).origin}/"`));

  // 18. Mobile layouts ----------------------------------------------------------------------------
  step('18. Verify mobile layouts');
  const mobile = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  const mp = await mobile.newPage();
  for (const [name, url] of [['home', BASE + '/'], ['article', newUrl], ['category', BASE + '/category/family-law'], ['search', BASE + '/search?q=custody']]) {
    await mp.goto(url);
    const overflow = await mp.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    check(`Mobile ${name}: no horizontal scrolling`, overflow <= 0, `overflow ${overflow}px`);
    await mp.screenshot({ path: `${SHOTS}/mobile-${name}.png`, fullPage: true });
  }
  await mp.goto(BASE + '/');
  check('Mobile nav hidden until the menu button is used', !(await mp.locator('#bf-primary-nav').isVisible()));
  await mp.click('.bf-menu-btn');
  check('Menu button opens navigation', await mp.locator('#bf-primary-nav').isVisible());
  const mAdminCookies = await ctx.cookies();
  await mobile.addCookies(mAdminCookies);
  await mp.goto(BASE + `/admin/posts/${postId}/edit`);
  await mp.locator('.ProseMirror').waitFor();
  check('Admin editor usable on mobile (sidebar collapsed)', !(await mp.locator('#admin-sidebar').isVisible()) || (await mp.locator('#admin-sidebar').boundingBox()).x < 0);
  await mp.screenshot({ path: `${SHOTS}/mobile-admin-editor.png` });
  await mobile.close();
  await page.goto(newUrl);
  await page.screenshot({ path: `${SHOTS}/desktop-article.png`, fullPage: true });
  await page.goto(BASE + '/');
  await page.screenshot({ path: `${SHOTS}/desktop-home.png`, fullPage: true });

  // 19. Edit existing post -------------------------------------------------------------------------
  step('19. Edit an existing post');
  await page.goto(BASE + `/admin/posts/${postId}/edit`);
  await page.fill('#excerpt', 'Updated: a plain-English guide to how courts in England decide child custody arrangements.');
  await page.locator('.ProseMirror p').first().click();
  await page.keyboard.press('End');
  await page.keyboard.type(' Updated for 2026.');
  await page.click('[data-publish-btn]');
  await page.waitForURL(/edit/);
  check('Post updated', (await flashText()).includes('Post updated'));
  const revs = Number(sql(`SELECT COUNT(*) FROM ${DB.prefix}post_revisions WHERE post_id=${postId} AND kind='revision'`));
  check('Revision history recorded', revs >= 4, String(revs));
  check('dateModified moved on a content edit', sql(`SELECT content_modified_at > published_at FROM ${DB.prefix}posts WHERE id=${postId}`) === '1');
  await page.goto(BASE + `/admin/posts/${postId}/revisions`);
  await page.locator('a:has-text("Compare")').nth(1).click();
  check('Revision comparison shows the change', (await page.content()).includes('Updated for 2026'));

  // 20. Delete & restore ------------------------------------------------------------------------------
  step('20. Delete and restore a post');
  await page.goto(BASE + `/admin/posts/${postId}/edit`);
  await page.click('button:has-text("Move to trash")');
  await confirmModal();
  await page.waitForURL(/\/admin\/posts$/);
  check('Moved to trash', (await flashText()).includes('trash'));
  r = await anon.request.get(newUrl);
  check('Trashed post is no longer public (404)', r.status() === 404);
  await page.goto(BASE + '/admin/posts?status=trash');
  await page.locator('table details[data-dropdown] summary').first().click();
  await page.click('button:has-text("Restore")');
  check('Restored from trash', (await flashText()).includes('Post restored'));
  r = await anon.request.get(newUrl);
  check('Restored post is public again', r.status() === 200);

  // 21. Export / import settings -------------------------------------------------------------------------
  step('21. Export and import permitted settings');
  await page.goto(BASE + '/admin/backup');
  const fd = new URLSearchParams();
  fd.append('_token', await csrf());
  ['appearance', 'seo', 'social', 'menus', 'integrations'].forEach((x) => fd.append('sections[]', x));
  const exp = await http.post(BASE + '/admin/backup/settings-export', { headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, data: fd.toString() });
  const exported = await exp.json();
  const exportPath = resolve(SHOTS, 'exported-config.json');
  writeFileSync(exportPath, JSON.stringify(exported, null, 2));
  const flat = JSON.stringify(exported);
  check('Export contains theme settings', exported.settings.theme.color_accent === '#0F766E');
  check('Export excludes secrets, code snippets, URLs and media IDs', !flat.includes('smtp_pass') && !flat.includes('head_snippet') && !flat.includes('blog_url') && !('logo' in exported.settings.theme) && !flat.includes(DB.pass));
  await page.goto(BASE + '/admin/appearance/theme');
  await page.fill('input[name="values[color_accent]"]', '#123456');
  await page.click('button:has-text("Save changes")');
  check('Accent temporarily changed', sql(`SELECT value FROM ${DB.prefix}settings WHERE name='theme.color_accent'`) === '"#123456"');
  await page.goto(BASE + '/admin/backup');
  await page.setInputFiles('#cfg-file', exportPath);
  await page.locator('#cfg-file').locator('xpath=ancestor::form').locator('button[type=submit]').click();
  await confirmModal();
  await page.waitForLoadState('load');
  check('Import reports success', (await flashText()).includes('Imported'));
  check('Imported value restored', sql(`SELECT value FROM ${DB.prefix}settings WHERE name='theme.color_accent'`) === '"#0F766E"');

  // Extra: scheduling, SEO audit, backups, uploads hardening ------------------------------------------
  step('Extra: scheduling, audit, backup, uploads hardening');
  await page.goto(BASE + '/admin/posts/new');
  await page.fill('#title', 'Mediation Before Court: What to Expect');
  await page.locator('.ProseMirror').click();
  await page.keyboard.type('Most separating parents must attend a mediation information meeting first.');
  const future = new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 16);
  await page.fill('#published_at', future);
  await page.click('[data-publish-btn]');
  await page.waitForURL(/edit/);
  check('Future date schedules the post', (await flashText()).includes('scheduled'));
  const schedSlug = await page.inputValue('#slug');
  r = await anon.request.get(BASE + '/' + schedSlug);
  check('Scheduled post not public yet', r.status() === 404);
  sql(`UPDATE ${DB.prefix}posts SET published_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE slug='${schedSlug}'`);
  execSync(`sh -c "echo 0 > /var/www/site2/blogs/storage/cache/next-publish.txt"`);
  r = await anon.request.get(BASE + '/' + schedSlug);
  check('Scheduled post publishes automatically when due (no cron)', r.status() === 200 && sql(`SELECT status FROM ${DB.prefix}posts WHERE slug='${schedSlug}'`) === 'published');

  await page.goto(BASE + '/admin/seo/audit');
  await page.click('button:has-text("Run audit")');
  check('SEO audit runs and categorises results', /Audit complete: \d+ critical, \d+ warnings/.test(await flashText()));

  await page.goto(BASE + '/admin/backup');
  await page.click('button:has-text("Create database backup")');
  check('Database backup created', (await flashText()).includes('Database backup created'));
  const bfile = (await flashText()).match(/blogflow-db-[0-9-]+-[a-f0-9]+\.sql\.gz/)[0];
  const dl = await http.get(BASE + '/admin/backup/download/' + bfile);
  const body = await dl.body();
  check('Backup downloads as gzip for admins', dl.status() === 200 && body[0] === 0x1f && body[1] === 0x8b);
  r = await anon.request.get(BASE + '/storage/backups/' + bfile);
  check('Backup file is not publicly reachable', r.status() === 403);
  execSync(`sh -c "mkdir -p /var/www/site2/blogs/uploads/2026 && echo '<?php echo 123;' > /var/www/site2/blogs/uploads/2026/evil.php"`);
  r = await anon.request.get(BASE + '/uploads/2026/evil.php');
  const evilBody = await r.text();
  check('PHP files in uploads are never executed', r.status() === 403 && !evilBody.includes('123'), String(r.status()));
  execSync('rm -f /var/www/site2/blogs/uploads/2026/evil.php');

  // Password reset link (email delivery itself is environment-specific and not tested here).
  const raw = [...Array(64)].map(() => '0123456789abcdef'[Math.floor(Math.random() * 16)]).join('');
  const hash = execSync(`php -r "echo hash('sha256', '${raw}');"`).toString();
  sql(`INSERT INTO ${DB.prefix}password_reset_tokens (user_id, token_hash, expires_at, created_at) VALUES (1, '${hash}', UTC_TIMESTAMP() + INTERVAL 1 HOUR, UTC_TIMESTAMP())`);
  const rp = await anon.newPage();
  await rp.goto(BASE + '/admin/reset/' + raw);
  await rp.fill('#password', 'Second-Lighthouse-88');
  await rp.fill('#password_confirm', 'Second-Lighthouse-88');
  await rp.click('button[type=submit]');
  check('Password reset link changes the password', (await rp.content()).includes('Your password has been changed'));
  await rp.goto(BASE + '/admin/reset/' + raw);
  check('Reset tokens are single-use', rp.url().includes('/admin/forgot'));
  check('Password change signed out existing sessions', (await http.get(BASE + '/admin/posts', { maxRedirects: 0 })).status() === 302);
  await rp.close();

  // 24–25. Independence & different domain ----------------------------------------------------------------
  step('24. No required files are loaded from the parent website');
  const phpLog = execSync('cat /tmp/apache2-error.log 2>/dev/null || true').toString();
  check('No open_basedir violations while running with open_basedir=/blogs only', !phpLog.includes('open_basedir'));
  check('Blog pages work while PHP is jailed to the /blogs/ folder', true);
  step('25. Works on a different domain with new database settings');
  check(`Canonical URLs use ${new URL(BASE).host}`, (await (await anon.request.get(newUrl)).text()).includes(`<link rel="canonical" href="${newUrl}">`));
  check(`Data stored in database ${DB.name} with prefix ${DB.prefix}`, Number(sql(`SELECT COUNT(*) FROM ${DB.prefix}posts`)) >= 2);
  await anon.close();
} catch (e) {
  check('Unexpected error in step', false, e.stack || String(e));
  await page.screenshot({ path: `${SHOTS}/failure.png`, fullPage: true }).catch(() => {});
}

check('No JavaScript errors on tested pages', jsErrors.length === 0, jsErrors.join(' | '));
await browser.close();
const failed = results.filter((r) => !r.ok);
writeFileSync(resolve(SHOTS, 'results.json'), JSON.stringify(results, null, 2));
console.log(`\n${results.length - failed.length} passed, ${failed.length} failed`);
process.exit(failed.length ? 1 : 0);
