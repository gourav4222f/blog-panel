# BlogFlow CMS — Deployment guide

BlogFlow installs into a `/blogs/` folder next to an existing website. It never reads, changes or
depends on the main website's files. Every copy is independent: its own database (or table prefix),
settings, theme, users and content.

## 1. Requirements

| | Minimum | Recommended |
|---|---|---|
| PHP | 8.2 | 8.3+ |
| Database | MySQL 5.7.7 / MariaDB 10.3 | MySQL 8 / MariaDB 10.11 |
| PHP extensions | pdo_mysql, mbstring, dom, libxml, fileinfo, json, gd **or** imagick | + exif, intl, zip, curl, opcache |
| Web server | Apache 2.4 with `mod_rewrite` and `AllowOverride All` | or Nginx (rules below) |

WebP output needs GD/Imagick built with WebP (almost all hosts). AVIF output is used only when the
server's image library supports it; the admin shows what is available and never pretends otherwise.

Node.js and Composer are **not** needed on the server. The release contains compiled CSS/JS, fonts and
the `vendor/` folder.

## 2. Upload

1. Download/extract the release (`blogflow-cms-<version>.zip`). It contains one folder: `blogs/`.
2. Upload `blogs/` into your web root (e.g. `public_html/blogs/`) with FTP or the host's File Manager.
   Your existing `index.php`, `about.php`, `assets/`, `.htaccess` and other files are untouched.
3. Make these folders writable by PHP (usually `755`; use `775` if your host requires it — never `777`
   on shared servers unless your host tells you to):
   - `blogs/config/`
   - `blogs/storage/` and its subfolders (`cache`, `logs`, `backups`, `originals`, `sessions`, `tmp`)
   - `blogs/uploads/`

The folder name `blogs` is only a convention — any folder name works, and the URL path is detected
automatically.

## 3. Run the installer

1. Create an empty database and a database user in your hosting panel (cPanel → MySQL Databases).
2. Visit `https://your-domain.com/blogs/` — you are redirected to the installer.
3. Step through **Environment → Database → Site → Administrator → Install**.
   - The environment step checks PHP, extensions, writable folders and whether clean URLs work.
   - Use a table prefix (default `bf_`) if the database is shared with other software.
   - There are no default accounts; you choose the administrator email and a strong password.
4. After installation you land on `/blogs/admin/login`. The installer is locked by
   `storage/installed.lock` and returns HTTP 403 from then on.

Run the installer straight after uploading. Until it has finished, anyone who can reach the URL could
complete it.

## 4. First configuration (5 minutes)

1. **Settings → General**: blog name, main website name/URL, timezone, language.
2. **Appearance → Theme settings**: choose a preset, upload the logo and favicon, set brand colours
   (live preview included). Contrast problems are flagged.
3. **Appearance → Menu builder**: add the main website links (Home, About, Services, Contact…), the
   Blog link and any external links. Drag to reorder; indent for dropdowns.
4. **Appearance → Header & footer**: business description, address, phone, email, legal links.
5. **SEO → Global metadata / Schema settings**: homepage title/description, title templates,
   organisation details and default social image.
6. **Settings → Integrations** (optional): GA4/GTM, Search Console & Bing verification, newsletter
   form, SMTP for password-reset email.
7. Write and publish your first post.

## 5. robots.txt and Search Console

`robots.txt` only works at the domain root, so `/blogs/robots.txt` would be ignored. BlogFlow never
edits your root files. Add this line to the main site's `robots.txt` (the exact line, with your
domain, is shown in **SEO → SEO overview**):

```
Sitemap: https://your-domain.com/blogs/sitemap.xml
```

Then in Google Search Console open your property → *Indexing → Sitemaps* → submit
`https://your-domain.com/blogs/sitemap.xml`. If you verify with a meta tag, paste only the `content`
value in **Settings → Integrations**.

Make sure the root `robots.txt` does **not** contain `Disallow: /blogs/`.

## 6. Apache notes

`blogs/.htaccess` contains everything needed: front-controller rewriting, denial of `app/`, `config/`,
`database/`, `storage/`, `vendor/` and `bin/`, compression and long-lived caching for versioned assets.
Each private folder also has its own deny-all `.htaccess`, and `uploads/.htaccess` disables PHP and only
serves image files.

Because `blogs/.htaccess` enables its own `RewriteEngine`, the main site's rewrite rules do not apply
inside `/blogs/`. If your root `.htaccess` rewrites *everything* to the main site's `index.php`, add
this line near the top of the **root** `.htaccess` (optional — only if `/blogs/` URLs are being
captured by the main site):

```apache
RewriteRule ^blogs(/|$) - [L]
```

If the blog is served through an alias and URLs 404, uncomment and set `RewriteBase /blogs/` in
`blogs/.htaccess`.

**HTTPS**: install an SSL certificate, enter the `https://` URL in **Settings → URL settings**, then
enable *Redirect other hosts / HTTP to the canonical URL* to keep www/non-www and http/https
consistent. Adding `Strict-Transport-Security` is best done in the main server configuration.

## 7. Nginx

Nginx ignores `.htaccess`, so the deny rules must be added to the server block. Use this tested snippet (also available as `docs/nginx-blogs.conf` in the source repository):

```nginx
location ~ ^/blogs/(app|config|database|storage|vendor|bin|tests)(/|$) { deny all; return 404; }
location ~ ^/blogs/.*\.(sql|log|md|lock|ini|sh|dist|sample|bak)$ { deny all; return 404; }
location ~ ^/blogs/composer\.(json|lock)$ { deny all; return 404; }
location ~ /\.(?!well-known) { deny all; return 404; }

location ^~ /blogs/uploads/ {
    location ~* \.(jpe?g|png|webp|avif|gif)$ {
        add_header Cache-Control "public, max-age=31536000, immutable";
        add_header X-Content-Type-Options nosniff;
        try_files $uri =404;
    }
    return 404;
}
location ^~ /blogs/assets/ {
    location ~* \.php$ { return 404; }
    add_header Cache-Control "public, max-age=31536000, immutable";
    try_files $uri =404;
}
location = /blogs/install/ { try_files $uri /blogs/install/index.php$is_args$args; }
location /blogs/ { try_files $uri $uri/ /blogs/index.php$is_args$args; }
location ~ ^/blogs/(index|install/index|admin/index)\.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param SCRIPT_NAME $fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
location ~ ^/blogs/.*\.php$ { return 404; }
```

Place these blocks above the main site's generic `location /` and `location ~ \.php$` rules, adjust
the PHP-FPM socket, then `nginx -t && systemctl reload nginx`.

## 8. Protecting the configuration file

The installer writes `blogs/config/config.php` (permissions 640). It is a PHP file that outputs nothing
if requested directly, and the folder is denied by `.htaccess` (Apache) or the Nginx rules above.

For extra safety you can move it outside the web root:

1. Move `blogs/config/config.php` to e.g. `/home/account/blogflow-config.php`.
2. Tell BlogFlow where it is, either in `blogs/.htaccess`:
   `SetEnv BLOGFLOW_CONFIG /home/account/blogflow-config.php`
   or in the PHP-FPM pool / Nginx `fastcgi_param BLOGFLOW_CONFIG /home/account/blogflow-config.php;`.

Never commit or share the config file; it contains database credentials and the application key.

## 9. Updating BlogFlow

1. Create a database backup (**Settings → Backup & restore**) and download it.
2. Upload the new release over the existing `blogs/` folder **except** `config/config.php`,
   `storage/` and `uploads/` (FTP clients can skip existing folders; never delete them).
3. Database migrations run automatically on the next request (or via **Backup & restore →
   Maintenance → Run migrations**, or `php bin/blogflow migrate` over SSH).

## 10. Moving to another domain or server

- Same content on a new domain: copy the `blogs/` folder, import the database, update
  `config/config.php` if the database details changed, then set the new address in
  **Settings → URL settings** (or `php bin/blogflow set-url https://new-domain.com/blogs`).
- New client site: upload a fresh release and run the installer with a new database. Use
  **Backup & restore → Site configuration** to copy your theme, menus and SEO templates from another
  BlogFlow site.

## 11. Optional cron

BlogFlow publishes scheduled posts without cron (the first request after the publish time does it).
On very low-traffic sites you can add a cron job to publish exactly on time:

```
*/5 * * * * php /home/account/public_html/blogs/bin/blogflow publish-scheduled >/dev/null 2>&1
```
