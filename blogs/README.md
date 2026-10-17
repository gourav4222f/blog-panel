# BlogFlow CMS

A standalone, reusable blog CMS for existing PHP websites. Upload the `blogs/` folder next to a site,
run the installer, and the client gets a fast, SEO-complete blog at `example.com/blogs/` with its own
admin at `example.com/blogs/admin/` — without touching the main website.

- **Install:** see [DEPLOYMENT.md](DEPLOYMENT.md) (Apache and Nginx).
- **Problems:** see [TROUBLESHOOTING.md](TROUBLESHOOTING.md).
- **Runtime:** PHP 8.2+, MySQL 8 / MariaDB 10.3+, GD or Imagick. No Node.js, Composer or CDN needed on
  the server; compiled assets, fonts and PHP dependencies are included.

## What's inside

**Public blog** — server-rendered, readable editorial design: homepage with featured article, category
navigation and paginated grid; articles with breadcrumbs, table of contents, responsive images, FAQ,
tags, sharing links, author bio, related posts, previous/next and an optional call-to-action;
category, tag, author and search pages; custom 404; RSS feed; XML sitemap (auto-split into a sitemap
index for large sites). Core content never needs JavaScript; the public JS is 1.6 KB.

**Admin** — dashboard with real counts only; posts (draft/scheduled/published, featured, bulk actions,
filters, sorting, soft delete & restore, duplicate, revisions with diff & restore, autosave with server
and browser recovery, private preview links); Tiptap-based editor (headings, lists, quotes, code, tables,
alignment, highlight, links with internal post picker, images with caption/ALT/width/alignment, YouTube
embeds, HTML view, word count, focus mode, keyboard shortcuts, drag-and-drop/paste image upload);
categories (nested), tags, authors; media library; SEO overview, audit, global metadata, schema,
sitemap, redirects, link audit; theme presets, colours, typography, header/footer, menu builder;
settings; users & roles; security; activity log; backup & restore.

**SEO** — title/description templates, canonical URLs, robots meta + X-Robots-Tag, per-post overrides,
Open Graph & X cards, JSON-LD (Organization, WebSite, WebPage/CollectionPage/ProfilePage, BlogPosting
/Article/NewsArticle, Person, BreadcrumbList, FAQPage when visible), live advisory content analysis,
site-wide audit (critical / warning / suggestion / passed), automatic 301s on slug changes, redirect
manager with loop and chain protection, broken-link checks.

**Images** — MIME + content validation, EXIF orientation fix, metadata stripping, resizing without
upscaling, configurable quality, WebP (and AVIF when the server supports it), responsive sizes with
`<picture>`/`srcset`/`sizes`, width/height to prevent layout shift, lazy loading, bulk re-optimisation
in resumable batches, usage tracking, safe replacement, cleanup of unused files.

**Security** — password hashing, hardened sessions (regeneration, idle/absolute timeouts, HttpOnly,
SameSite, Secure on HTTPS), CSRF on every form, login throttling, role checks on every route, HTML
Purifier allowlist sanitising, strict admin CSP, PDO prepared statements, upload folder that cannot run
scripts, protected config/storage, audit log, no default accounts, locked installer.

## Roles

| | Administrator | Editor | Author |
|---|---|---|---|
| Posts | all | all (publish, delete) | own (publish if allowed in Security settings) |
| Categories, tags, authors | ✓ | ✓ | — |
| Media | ✓ | ✓ | upload (configurable) |
| SEO audit, redirects, link audit | ✓ | ✓ | view analysis |
| Appearance, settings, integrations, users, backups | ✓ | — | — |

## Reusing on another website

Each copy is independent. For a new client: upload a fresh release, install with a new database (or a
new table prefix), then import your favourite look and SEO templates from another BlogFlow site via
**Settings → Backup & restore → Site configuration**.

### Configuration export / import

- Choose sections: *Theme, typography, header & footer*, *SEO templates, schema & sitemap*, *Social
  media*, *General display options* (incl. image settings), *Integrations*, *Menus*.
- The JSON export never contains passwords, database credentials, the application key, the SMTP
  password, custom code snippets, media IDs (logo, favicon, images), the blog URL or the main website
  name/URL — these are specific to each site.
- Menus are exported with link types; links to categories/tags/posts are matched by slug on import and
  skipped if the target does not exist.
- Imports are validated field by field with the same rules as the settings forms; invalid sections are
  reported and not applied.

### Content export / import

Posts can be exported as JSON (full content and SEO fields) or CSV (summary) and imported from
BlogFlow JSON (as drafts by default). Images are not embedded in post exports — copy `uploads/` or use
the media ZIP.

### Sample content (seed data)

`database/seed.sql` adds fictional demo content to a fresh install so you can preview the theme:
3 categories (one nested), 10 tags, 2 author profiles and 8 travel/taxi posts (6 published, 1 draft,
1 scheduled a week ahead) with tags, FAQ and related posts. It adds no users, passwords, settings or
images, and leaves existing rows alone, so running it twice is harmless.

1. Install BlogFlow first (the installer creates the tables).
2. If your table prefix is not `bf_`, replace it (or find-and-replace `` `bf_ `` in a text editor):
   ```
   sed 's/`bf_/`yourprefix_/g' seed.sql > seed-mine.sql
   ```
3. Import it: phpMyAdmin → your database → *Import*, or `mysql -u USER -p DATABASE < seed.sql`.
4. In the admin: **Settings → Backup & restore → Maintenance → Clear caches** so the pages show the new
   posts immediately.

Delete the sample posts, categories, tags and authors from the admin when you no longer need them.
Regenerate the file after editing the content with `php scripts/make-seed.php` (repository only).

## Command line (optional)

```
php bin/blogflow migrate | reset-password <email> | set-url <url> | disable-host-redirect | clear-cache | publish-scheduled | audit
```

## Directory layout

```
blogs/
├── index.php            front controller (public + admin)
├── .htaccess            rewrites + protections (Apache)
├── admin/ install/      entry points
├── app/                 Core, Controllers, Models, Services, Validation, Views, Helpers, Install
├── assets/              compiled CSS/JS, self-hosted fonts
├── config/              private configuration (created by the installer)
├── database/migrations/ SQL migrations (applied automatically)
├── storage/             cache, logs, backups, private originals, sessions (never public)
├── uploads/             optimised images (scripts disabled)
├── vendor/              HTML Purifier, PHPMailer
└── bin/blogflow         CLI tools
```

Licences: HTML Purifier (LGPL-2.1), PHPMailer (LGPL-2.1), Tiptap/ProseMirror (MIT), SortableJS (MIT),
fonts via Fontsource (SIL OFL 1.1, see `assets/fonts/LICENSES.txt`), icons adapted from Lucide (ISC).
