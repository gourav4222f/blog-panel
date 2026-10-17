# BlogFlow CMS — Troubleshooting

Error details are written to `blogs/storage/logs/error-YYYY-MM.log` (never shown to visitors).
For temporary detailed error pages set `'debug' => true` in `config/config.php` — turn it off again.

## Installation

| Symptom | Fix |
|---|---|
| “vendor/ directory is missing” | Upload the complete release; `blogs/vendor/` is required. |
| Installer says a folder is not writable | Set `config/`, `storage/` (and subfolders) and `uploads/` to 755 or 775 so PHP can write. |
| “Clean URLs … Not detected” | Apache: enable `mod_rewrite` and `AllowOverride All` for the folder (ask your host). Nginx: add the rules from DEPLOYMENT.md. |
| “Access denied” at the database step | Check user/password and that the user is assigned to the database with ALL PRIVILEGES. On cPanel the names are prefixed, e.g. `account_blog`. |
| “The database does not exist” | Create it first in the hosting panel. |
| “BlogFlow tables with this prefix already exist” | Use an empty database or another table prefix. |
| Installer shows “already installed” but you need to reinstall | Delete `storage/installed.lock` and `config/config.php` via FTP and use an empty database. |

## Public site

| Symptom | Fix |
|---|---|
| Every blog URL except the homepage is 404 (Apache’s own page) | `mod_rewrite`/`AllowOverride` is off, or a root rule captures `/blogs/`. See DEPLOYMENT.md §6. |
| Links point to the wrong domain or `http://` | Fix **Settings → URL settings → Blog URL**, or run `php bin/blogflow set-url https://domain/blogs`. |
| Redirect loop after enabling “Redirect other hosts / HTTP” | The Blog URL does not match how the site is reached (e.g. https behind a proxy that doesn’t send `X-Forwarded-Proto`). Run `php bin/blogflow disable-host-redirect`, or set `'url' => 'https://…/blogs'` in config. |
| Changes don’t appear | Pages are cached and cleared automatically on save. Use **Backup & restore → Maintenance → Clear caches** if you edited files by hand. Browser/CDN caches may also need purging. |
| Scheduled post not live yet | It publishes on the first visit after its time. On quiet sites add the optional cron from DEPLOYMENT.md §11. |
| Fonts look different from the preview | Only fonts selected in **Appearance → Typography** are loaded; system fonts load nothing. |

## Admin

| Symptom | Fix |
|---|---|
| “Your session expired” on save | You were inactive longer than the idle timeout (**Settings → Security**). Content is kept in the browser and the editor offers to restore it. |
| “Too many failed sign-in attempts” | Wait for the lockout window or, with SSH, run `php bin/blogflow reset-password you@example.com` (also clears lockouts). |
| Password-reset email never arrives | Configure SMTP in **Settings → Integrations** and use *Send test email*. An administrator can also set a new password under **Users & roles**. |
| Locked out completely | SSH: `php bin/blogflow reset-password email`. No SSH: restore a database backup or ask your host to run the command. |
| Uploads fail for large images | Raise `upload_max_filesize` and `post_max_size` in PHP (hosting panel), and **Settings → Image settings → Maximum upload size**. Very large photos may also exceed `memory_limit`; the megapixel limit protects against this. |
| “… images are not supported by this server’s image library” | The server’s GD/Imagick lacks that format (commonly AVIF). Upload JPEG/PNG/WebP instead. |
| Editor toolbar missing | JavaScript must be enabled; check the browser console for blocked scripts (the admin uses a strict Content-Security-Policy; browser extensions can interfere). |

## Restore & migrations

- A restore always creates a `blogflow-safety-*.sql.gz` backup first; restore that file to undo.
- Only backups created by BlogFlow are accepted. Each statement is validated and may only touch this
  installation’s prefixed tables.
- After uploading a new version, migrations apply automatically; check **Backup & restore → Maintenance**
  for the schema status.
