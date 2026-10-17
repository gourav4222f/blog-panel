<?php

/**
 * Dependency-free unit tests for BlogFlow CMS core logic.
 * Run: php tests/unit/run.php
 */

declare(strict_types=1);

define('BLOGFLOW', true);
define('BF_ROOT', dirname(__DIR__, 2) . '/blogs');
define('BF_VERSION', 'test');
require BF_ROOT . '/vendor/autoload.php';
require BF_ROOT . '/app/Helpers/functions.php';

use BlogFlow\Controllers\Admin\RevisionController;
use BlogFlow\Core\HttpException;
use BlogFlow\Core\Migrator;
use BlogFlow\Core\Router;
use BlogFlow\Core\Url;
use BlogFlow\Services\HtmlSanitizer;
use BlogFlow\Services\ImageService;
use BlogFlow\Services\RedirectService;
use BlogFlow\Services\SeoService;
use BlogFlow\Services\Settings;
use BlogFlow\Services\ThemeService;
use BlogFlow\Validation\Validator;

$passed = 0;
$failed = [];
function test(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        echo "  ✓ $name\n";
    } catch (Throwable $e) {
        $failed[] = $name;
        echo "  ✕ $name\n      " . $e->getMessage() . "\n";
    }
}
function eq(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($msg ? $msg . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
function ok(bool $cond, string $msg = 'assertion failed'): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

echo "Validation\n";
test('slugify transliterates and trims', function () {
    eq('cafe-and-creme-brulee', Validator::slugify('Café & Crème brûlée'));
    eq('best-time-to-visit-goa-a-local-drivers-guide', Validator::slugify("Best Time to Visit Goa: A Local Driver's Guide"));
    eq('hello-world', Validator::slugify('  --Hello__World--  '));
    eq('', Validator::slugify('   '));
    ok(strlen(Validator::slugify(str_repeat('word ', 80))) <= 120, 'slug length capped');
});
test('isSlug', function () {
    ok(Validator::isSlug('a-b-c1'));
    ok(!Validator::isSlug('A-b'));
    ok(!Validator::isSlug('a--b'));
    ok(!Validator::isSlug('-a'));
    ok(!Validator::isSlug('a/b'));
});
test('isHttpUrl rejects dangerous schemes', function () {
    ok(Validator::isHttpUrl('https://example.com/a?b=c'));
    ok(!Validator::isHttpUrl('javascript:alert(1)'));
    ok(!Validator::isHttpUrl('//evil.com'));
    ok(!Validator::isHttpUrl('https://exa mple.com'));
    ok(!Validator::isHttpUrl('ftp://example.com'));
});
test('parseDate respects timezone', function () {
    $d = Validator::parseDate('2026-01-15T10:30', 'Asia/Kolkata');
    eq('2026-01-15 05:00:00', $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
    eq(null, Validator::parseDate('2026-02-30T10:00'));
});
test('password rules', function () {
    ok(Validator::passwordProblems('short', 10) !== []);
    ok(Validator::passwordProblems('password123', 8) !== []);
    ok(Validator::passwordProblems('aaaaaaaaaaaa', 10) !== []);
    eq([], Validator::passwordProblems('Correct-Horse-42', 10));
});
test('rule validator', function () {
    $v = Validator::make(['t' => '', 'e' => 'nope', 'n' => '5'], ['t' => 'required', 'e' => 'email', 'n' => 'between:1,3']);
    eq(['t', 'e', 'n'], array_keys($v->errors()));
});

echo "HTML sanitising\n";
test('removes scripts, handlers and javascript: links', function () {
    $out = HtmlSanitizer::clean('<p onclick="x()">Hi<script>alert(1)</script> <a href="javascript:alert(1)">x</a> <img src="x" onerror="alert(1)"></p>');
    ok(!str_contains($out, 'script'), 'script removed');
    ok(!str_contains($out, 'onclick') && !str_contains($out, 'onerror'), 'handlers removed');
    ok(!str_contains($out, 'javascript:'), 'javascript url removed');
});
test('keeps editor structures', function () {
    $out = HtmlSanitizer::clean('<figure data-align="left" data-width="50"><img src="/u/a.jpg" alt="A" data-media-id="7"><figcaption>Cap</figcaption></figure><p style="text-align:center">c</p><mark>m</mark><table><tr><th scope="col">h</th></tr></table>');
    foreach (['<figure', 'data-align="left"', 'data-width="50"', 'data-media-id="7"', '<figcaption>Cap</figcaption>', 'text-align:center', '<mark>', 'scope="col"'] as $needle) {
        ok(str_contains($out, $needle), "kept $needle");
    }
});
test('only allowlisted iframes survive', function () {
    $out = HtmlSanitizer::clean('<div data-youtube-video=""><iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe></div><iframe src="https://evil.example/x"></iframe>');
    ok(str_contains($out, 'youtube-nocookie.com/embed/abc'));
    ok(!str_contains($out, 'evil.example'));
});
test('content H1 becomes H2 (single H1 per page)', function () {
    $out = HtmlSanitizer::clean('<h1>A</h1><h5>B</h5>');
    ok(str_contains($out, '<h2>A</h2>') && str_contains($out, '<h4>B</h4>') && !str_contains($out, '<h1'));
});
test('text, word count, reading time, summary', function () {
    $t = HtmlSanitizer::text('<h2>Title</h2><p>One two three.</p>');
    eq(4, HtmlSanitizer::wordCount($t));
    eq(1, HtmlSanitizer::readingTime(4));
    eq(0, HtmlSanitizer::readingTime(0));
    ok(mb_strlen(HtmlSanitizer::summarize(str_repeat('word ', 100), 50)) <= 51);
});

echo "Routing & URLs\n";
test('router matches params and reports 405', function () {
    $r = new Router();
    $r->get('/category/{slug:[a-z0-9\-]+}', 'h1');
    $r->post('/admin/posts', 'h2');
    $m = $r->match('GET', '/category/travel-tips');
    eq('travel-tips', $m['params']['slug']);
    eq(null, $r->match('GET', '/category/Bad'));
    try {
        $r->match('GET', '/admin/posts');
        throw new RuntimeException('expected 405');
    } catch (HttpException $e) {
        eq(405, $e->status);
    }
});
test('url builder applies trailing slash policy', function () {
    $u = new Url('https://example.com/blogs', '/blogs', false);
    eq('https://example.com/blogs/', $u->home());
    eq('https://example.com/blogs/page/2', $u->home(2));
    eq('https://example.com/blogs/my-post', $u->post('my-post'));
    eq('https://example.com/blogs/category/travel/page/3', $u->category('travel', 3));
    eq('https://example.com/blogs/sitemap.xml', $u->sitemap());
    $s = new Url('https://example.com/blogs/', '/blogs', true, 'topics');
    eq('https://example.com/blogs/my-post/', $s->post('my-post'));
    eq('https://example.com/blogs/topics/x/', $s->category('x'));
    eq('https://example.com/blogs/feed.xml', $s->feed());
});
test('url relativePath and isExternal', function () {
    $u = new Url('https://example.com/blogs', '/blogs');
    eq('/my-post', $u->relativePath('https://example.com/blogs/my-post?x=1'));
    eq('/my-post', $u->relativePath('/blogs/my-post'));
    eq(null, $u->relativePath('https://example.com/blogsfoo'));
    eq(null, $u->relativePath('https://other.com/blogs/x'));
    ok($u->isExternal('https://other.com/x'));
    ok(!$u->isExternal('https://www.example.com/about'));
    ok(!$u->isExternal('/about'));
});
test('redirect path normalisation', function () {
    eq('/old-post', RedirectService::normalize('old-post/'));
    eq('/a/b', RedirectService::normalize('//a//b/?q=1'));
    eq('/', RedirectService::normalize(''));
});

echo "SEO\n";
test('advanced robots directives are allowlisted', function () {
    eq(['nosnippet', 'max-snippet:50'], SeoService::parseAdvancedRobots('nosnippet, max-snippet:50, <script>, noindex'));
});
test('JSON-LD escapes HTML-significant characters', function () {
    $json = SeoService::jsonLd([['@type' => 'Thing', 'name' => '</script><script>alert(1)</script>']]);
    ok(!str_contains($json, '</script>'));
    ok(is_array(json_decode($json, true)));
});

echo "Theme\n";
test('presets meet WCAG AA contrast for key pairs', function () {
    foreach (ThemeService::PRESETS as $key => $p) {
        eq([], ThemeService::contrastWarnings($p['theme']), "preset $key");
    }
});
test('variables CSS ignores invalid values', function () {
    $css = ThemeService::variablesCss(['color_bg' => 'red;}</style><script>', 'radius' => 999], []);
    ok(!str_contains($css, '<') && !str_contains($css, 'script'));
    ok(str_contains($css, '--bf-bg:#FAFAF8'));
    ok(str_contains($css, '--bf-radius:28px'));
});
test('font face CSS only includes requested fonts', function () {
    $css = ThemeService::fontFaceCss(['inter'], '/blogs/assets/fonts');
    ok(str_contains($css, 'Inter Variable') && !str_contains($css, 'Lora'));
    eq([], ThemeService::usedFonts(['font_heading' => 'system-sans', 'font_body' => 'system-serif', 'font_ui' => 'system-sans']));
});

echo "Settings\n";
test('settings validation per type', function () {
    [$clean, $errors] = Settings::validateGroup('theme', ['color_bg' => '#abc', 'radius' => '50', 'card_style' => 'weird'], []);
    ok(isset($errors['color_bg'], $errors['radius'], $errors['card_style']));
    [$clean, $errors] = Settings::validateGroup('url', ['blog_url' => 'javascript:alert(1)', 'category_base' => 'Topics!'], []);
    ok(isset($errors['blog_url'], $errors['category_base']));
    [$clean, $errors] = Settings::validateGroup('integrations', ['ga4_id' => 'G-ABC123', 'head_snippet' => '<script>x</script>', 'smtp_pass' => ''], ['smtp_pass' => 'secret', 'head_snippet' => 'old'], false);
    eq([], $errors);
    eq('secret', $clean['smtp_pass'], 'empty password keeps stored secret');
    eq('old', $clean['head_snippet'], 'non-admins cannot change code snippets');
});

echo "Database helpers\n";
test('migration splitter', function () {
    $parts = Migrator::split("-- c\nCREATE TABLE a (x INT);\n\nCREATE TABLE b (\n y INT\n);\n");
    eq(2, count($parts));
});
test('revision diff', function () {
    $d = RevisionController::diff(['a', 'b', 'c'], ['a', 'c', 'd']);
    eq([['=', 'a'], ['-', 'b'], ['=', 'c'], ['+', 'd']], $d);
});

echo "Images\n";
test('rejects non-images disguised as images', function () {
    foreach (['shell.php.jpg', 'fake.jpg'] as $f) {
        try {
            ImageService::inspect(__DIR__ . '/../fixtures/out/' . $f, 40_000_000);
            throw new LogicException('accepted ' . $f);
        } catch (RuntimeException $e) {
            ok(true);
        }
    }
});
test('applies EXIF orientation, resizes without upscaling, encodes WebP', function () {
    $info = ImageService::inspect(__DIR__ . '/../fixtures/out/rotated-phone-photo.jpg', 40_000_000);
    $img = ImageService::load(__DIR__ . '/../fixtures/out/rotated-phone-photo.jpg', $info['ext']);
    eq([200, 300], [$img->width, $img->height], 'rotated to portrait');
    $same = ImageService::resize($img, 1000);
    eq(200, $same->width, 'no upscaling');
    $small = ImageService::resize($img, 100);
    eq([100, 150], [$small->width, $small->height], 'aspect ratio kept');
    $out = sys_get_temp_dir() . '/bf-test.webp';
    if (ImageService::canWrite('webp')) {
        ok(ImageService::save($small, $out, 'webp', ['jpg' => 80, 'webp' => 80, 'avif' => 50, 'png' => 8]));
        eq('image/webp', (new finfo(FILEINFO_MIME_TYPE))->file($out));
        @unlink($out);
    }
});

echo "\n" . $passed . ' passed, ' . count($failed) . " failed\n";
exit($failed ? 1 : 0);
