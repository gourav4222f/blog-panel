<?php $this->layout('admin/layout'); ?>
<div class="mb-5"><h1 class="page-title">Backup &amp; restore</h1><p class="page-sub">Backups are stored in storage/backups, which is not reachable from the web. Download copies and keep them somewhere safe.</p></div>
<div class="grid gap-5 lg:grid-cols-2">
  <section class="card">
    <div class="card-head"><h2 class="card-title">Database backup</h2><span class="text-xs text-stone-500">Last: <?= $lastBackup ? e(bf_ago((string) $lastBackup)) : '<span class="text-amber-700">never</span>' ?></span></div>
    <div class="card-pad space-y-3 text-sm">
      <p class="text-stone-600">Exports every BlogFlow table (prefix <code><?= e(bf_app()->db->prefix()) ?></code>) as compressed SQL: posts, settings, users (password hashes), redirects and media records. Treat backup files as sensitive.</p>
      <form method="post" action="<?= e(bf_admin_url('backup/database')) ?>"><?= bf_csrf_field() ?><button class="btn btn-primary" type="submit"><?= bf_icon('database') ?> Create database backup</button></form>
    </div>
  </section>
  <section class="card">
    <div class="card-head"><h2 class="card-title">Media backup</h2><span class="text-xs text-stone-500"><?= (int) $uploadsCount ?> files · <?= e(bf_bytes($uploadsBytes)) ?></span></div>
    <div class="card-pad space-y-3 text-sm">
      <p class="text-stone-600">Images live in the <code>uploads/</code> folder (originals in <code>storage/originals/</code>). For large libraries, download those folders with FTP or your host’s File Manager — it is faster and not subject to PHP time limits.</p>
      <?php if ($zip): ?><form method="post" action="<?= e(bf_admin_url('backup/media')) ?>"><?= bf_csrf_field() ?><button class="btn btn-secondary" type="submit" data-confirm="Create a ZIP of all uploaded images? This can take a while for large libraries."><?= bf_icon('image') ?> Create media ZIP</button></form><?php else: ?><p class="text-amber-700">The zip extension is unavailable; use FTP.</p><?php endif; ?>
    </div>
  </section>

  <section class="card lg:col-span-2">
    <div class="card-head"><h2 class="card-title">Stored backups</h2></div>
    <div class="overflow-x-auto"><table class="table"><thead><tr><th>File</th><th>Type</th><th>Size</th><th>Created</th><th></th></tr></thead><tbody>
      <?php foreach ($backups as $b): ?>
        <tr><td class="break-all font-mono text-xs"><?= e($b['file']) ?></td><td><?= e($b['type']) ?></td><td class="whitespace-nowrap"><?= e(bf_bytes($b['size'])) ?></td><td class="whitespace-nowrap text-stone-600"><?= e(bf_date(gmdate('Y-m-d H:i:s', $b['time']), 'M j, Y H:i')) ?></td>
          <td class="whitespace-nowrap text-right"><a class="btn btn-secondary btn-sm" href="<?= e(bf_admin_url('backup/download/' . $b['file'])) ?>"><?= bf_icon('download', 'h-3.5 w-3.5') ?> Download</a>
            <button class="btn btn-ghost btn-sm text-red-700" type="submit" form="bk-del-<?= e(md5($b['file'])) ?>" data-confirm="Delete <?= e($b['file']) ?>?">Delete</button></td></tr>
      <?php endforeach; ?>
      <?php if (!$backups): ?><tr><td colspan="5" class="py-8 text-center text-stone-500">No backups yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>

  <section class="card border-red-200">
    <div class="card-head"><h2 class="card-title text-red-800">Restore database</h2></div>
    <form method="post" action="<?= e(bf_admin_url('backup/restore')) ?>" enctype="multipart/form-data" class="card-pad space-y-3 text-sm">
      <?= bf_csrf_field() ?>
      <p class="text-stone-600">Replaces all BlogFlow data with the contents of a BlogFlow backup. A safety backup of the current data is created automatically first. Only files created by BlogFlow are accepted.</p>
      <div><label class="label" for="rs-file">Backup file (.sql.gz)</label><input class="block w-full text-xs file:mr-3 file:rounded-md file:border-0 file:bg-stone-100 file:px-3 file:py-1.5" id="rs-file" type="file" name="file" accept=".gz,application/gzip" required></div>
      <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="label" for="rs-confirm">Type RESTORE</label><input class="input" id="rs-confirm" name="confirm" autocomplete="off" required pattern="RESTORE"></div>
        <div><label class="label" for="rs-pass">Your password</label><input class="input" id="rs-pass" type="password" name="password" autocomplete="current-password" required></div>
      </div>
      <button class="btn btn-danger-solid" type="submit" data-confirm="Restore this backup? All current BlogFlow data will be replaced (a safety backup is made first).">Restore backup</button>
    </form>
  </section>

  <section class="card">
    <div class="card-head"><h2 class="card-title">Site configuration</h2></div>
    <div class="card-pad space-y-4 text-sm">
      <p class="text-stone-600">Move appearance, SEO templates, social profiles and menus between BlogFlow sites. Exports never include passwords, database credentials, the SMTP password, custom code, media files or site-specific URLs.</p>
      <form method="post" action="<?= e(bf_admin_url('backup/settings-export')) ?>" class="space-y-2">
        <?= bf_csrf_field() ?>
        <fieldset class="grid gap-1.5 sm:grid-cols-2"><legend class="label">Sections</legend>
          <?php foreach ($sections as $k => [$label]): ?><label class="flex items-center gap-2"><input class="checkbox" type="checkbox" name="sections[]" value="<?= e($k) ?>"<?= $k !== 'integrations' ? ' checked' : '' ?>> <?= e($label) ?></label><?php endforeach; ?>
        </fieldset>
        <button class="btn btn-secondary" type="submit"><?= bf_icon('download') ?> Export configuration</button>
      </form>
      <form method="post" action="<?= e(bf_admin_url('backup/settings-import')) ?>" enctype="multipart/form-data" class="space-y-2 border-t border-stone-100 pt-4">
        <?= bf_csrf_field() ?>
        <label class="label" for="cfg-file">Import configuration (.json)</label>
        <input class="block w-full text-xs file:mr-3 file:rounded-md file:border-0 file:bg-stone-100 file:px-3 file:py-1.5" id="cfg-file" type="file" name="file" accept=".json,application/json" required>
        <fieldset class="grid gap-1.5 sm:grid-cols-2"><legend class="sr-only">Sections to import</legend>
          <?php foreach ($sections as $k => [$label]): ?><label class="flex items-center gap-2"><input class="checkbox" type="checkbox" name="sections[]" value="<?= e($k) ?>"<?= $k !== 'integrations' ? ' checked' : '' ?>> <?= e($label) ?></label><?php endforeach; ?>
        </fieldset>
        <button class="btn btn-secondary" type="submit" data-confirm="Import the selected sections? Current values in those sections will be replaced."><?= bf_icon('upload') ?> Import</button>
      </form>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><h2 class="card-title">Posts import / export</h2></div>
    <div class="card-pad space-y-3 text-sm">
      <p class="text-stone-600">JSON exports include full content and SEO fields; CSV is a summary for spreadsheets.</p>
      <div class="flex gap-2"><a class="btn btn-secondary" href="<?= e(bf_admin_url('posts/export', ['format' => 'json'])) ?>">Export JSON</a><a class="btn btn-secondary" href="<?= e(bf_admin_url('posts/export', ['format' => 'csv'])) ?>">Export CSV</a></div>
      <form method="post" action="<?= e(bf_admin_url('posts/import')) ?>" enctype="multipart/form-data" class="space-y-2 border-t border-stone-100 pt-3">
        <?= bf_csrf_field() ?>
        <label class="label" for="pi-file">Import posts (BlogFlow JSON)</label>
        <input class="block w-full text-xs file:mr-3 file:rounded-md file:border-0 file:bg-stone-100 file:px-3 file:py-1.5" id="pi-file" type="file" name="file" accept=".json,application/json" required>
        <label class="flex items-center gap-2"><input class="checkbox" type="checkbox" name="as_drafts" value="1" checked> Import everything as drafts</label>
        <button class="btn btn-secondary" type="submit">Import posts</button>
      </form>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><h2 class="card-title">Maintenance</h2></div>
    <div class="card-pad space-y-3 text-sm">
      <p class="text-stone-600">Database schema: <?= count($applied) ?> migration(s) applied<?= $pending ? ', <strong class="text-amber-700">' . count($pending) . ' pending</strong>' : ', up to date' ?> · <?= e($dbVersion) ?></p>
      <div class="flex flex-wrap gap-2">
        <?php foreach (['clear-cache' => 'Clear caches', 'migrate' => 'Run migrations', 'publish-scheduled' => 'Publish due scheduled posts', 'rebuild-stats' => 'Rebuild word counts & media usage', 'prune-logs' => 'Prune old logs'] as $k => $l): ?>
          <form method="post" action="<?= e(bf_admin_url('maintenance/' . $k)) ?>"><?= bf_csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit"><?= e($l) ?></button></form>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</div>
<?php foreach ($backups as $b): ?><form id="bk-del-<?= e(md5($b['file'])) ?>" method="post" action="<?= e(bf_admin_url('backup/delete/' . $b['file'])) ?>" hidden><?= bf_csrf_field() ?></form><?php endforeach; ?>
