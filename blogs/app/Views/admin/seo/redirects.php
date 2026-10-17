<?php
$this->layout('admin/layout');
$f = $edit ?? [];
$val = fn ($k, $d = '') => $old[$k] ?? $f[$k] ?? $d;
$err = fn ($k) => isset($errors[$k]) ? '<p class="error-text">' . e($errors[$k]) . '</p>' : '';
?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
  <div><h1 class="page-title">Redirects</h1><p class="page-sub">Paths are relative to <?= e(bf_url()->base()) ?>. Slug changes on published posts create 301 redirects automatically.</p></div>
  <?php if ($chains): ?><form method="post" action="<?= e(bf_admin_url('seo/redirects/flatten')) ?>"><?= bf_csrf_field() ?><button class="btn btn-secondary" type="submit"><?= bf_icon('zap') ?> Fix <?= count($chains) ?> chain(s)</button></form><?php endif; ?>
</div>
<div class="grid gap-5 xl:grid-cols-[22rem_minmax(0,1fr)]">
  <form method="post" action="<?= e(bf_admin_url($edit ? 'seo/redirects/' . $edit['id'] : 'seo/redirects')) ?>" class="card card-pad space-y-3 self-start">
    <?= bf_csrf_field() ?>
    <h2 class="card-title"><?= $edit ? 'Edit redirect' : 'Add redirect' ?></h2>
    <div><label class="label" for="r-src">Old path</label><input class="input font-mono text-xs" id="r-src" name="source_path" value="<?= e($val('source_path')) ?>" placeholder="/old-article" required><?= $err('source_path') ?></div>
    <div><label class="label" for="r-dest">Redirect to</label><input class="input font-mono text-xs" id="r-dest" name="destination" value="<?= e($val('destination')) ?>" placeholder="/new-article or https://…" required><?= $err('destination') ?: '<p class="help">A blog path or a full URL (e.g. a page on the main website).</p>' ?></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="label" for="r-code">Type</label><select class="select" id="r-code" name="status_code"><?php foreach ([301 => '301 Permanent', 302 => '302 Temporary', 307 => '307 Temporary', 308 => '308 Permanent'] as $c => $l): ?><option value="<?= $c ?>"<?= (int) $val('status_code', 301) === $c ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <label class="mt-6 flex items-center gap-2 text-sm"><input type="hidden" name="is_enabled" value="0"><input class="switch" type="checkbox" role="switch" name="is_enabled" value="1"<?= $val('is_enabled', 1) ? ' checked' : '' ?>> Enabled</label>
    </div>
    <div><label class="label" for="r-note">Note</label><input class="input" id="r-note" name="note" value="<?= e($val('note')) ?>" maxlength="255"></div>
    <div class="flex gap-2"><button class="btn btn-primary" type="submit"><?= $edit ? 'Save' : 'Add redirect' ?></button><?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(bf_admin_url('seo/redirects')) ?>">Cancel</a><?php endif; ?></div>
  </form>
  <section class="card overflow-hidden">
    <form method="get" class="border-b border-stone-100 px-4 py-3"><label class="sr-only" for="r-q">Search</label><input id="r-q" class="input h-8 max-w-xs" type="search" name="q" value="<?= e($q) ?>" placeholder="Search redirects…"></form>
    <div class="overflow-x-auto"><table class="table">
      <thead><tr><th>From</th><th>To</th><th>Type</th><th class="hidden md:table-cell">Hits</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="<?= $r['is_enabled'] ? '' : 'opacity-60' ?>">
            <td class="max-w-[14rem] break-all font-mono text-xs"><?= e($r['source_path']) ?><?php if ($r['is_auto']): ?> <span class="badge badge-gray">auto</span><?php endif; ?><?php if (in_array($r['id'], $chains)): ?> <span class="badge badge-amber">chain</span><?php endif; ?><?php if ($r['note']): ?><span class="block font-sans text-[11px] text-stone-500"><?= e($r['note']) ?></span><?php endif; ?></td>
            <td class="max-w-[16rem] break-all font-mono text-xs"><?= e($r['destination']) ?></td>
            <td><span class="badge <?= $r['is_enabled'] ? 'badge-blue' : 'badge-gray' ?>"><?= (int) $r['status_code'] ?><?= $r['is_enabled'] ? '' : ' off' ?></span></td>
            <td class="hidden md:table-cell tabular-nums text-xs text-stone-500"><?= (int) $r['hits'] ?><?= $r['last_hit_at'] ? '<br>' . e(bf_ago($r['last_hit_at'])) : '' ?></td>
            <td class="whitespace-nowrap text-right">
              <a class="icon-btn" href="<?= e(bf_admin_url('seo/redirects', ['edit' => $r['id']])) ?>" aria-label="Edit"><?= bf_icon('edit') ?></a>
              <button class="icon-btn" type="submit" form="rt-<?= (int) $r['id'] ?>" aria-label="<?= $r['is_enabled'] ? 'Disable' : 'Enable' ?>"><?= bf_icon($r['is_enabled'] ? 'eye' : 'refresh') ?></button>
              <button class="icon-btn text-red-700" type="submit" form="rd-<?= (int) $r['id'] ?>" data-confirm="Delete the redirect from <?= e($r['source_path']) ?>?" aria-label="Delete"><?= bf_icon('trash') ?></button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="py-10 text-center text-stone-500">No redirects yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>
</div>
<?php foreach ($rows as $r): ?>
  <form id="rt-<?= (int) $r['id'] ?>" method="post" action="<?= e(bf_admin_url('seo/redirects/' . $r['id'] . '/toggle')) ?>" hidden><?= bf_csrf_field() ?></form>
  <form id="rd-<?= (int) $r['id'] ?>" method="post" action="<?= e(bf_admin_url('seo/redirects/' . $r['id'] . '/delete')) ?>" hidden><?= bf_csrf_field() ?></form>
<?php endforeach; ?>
