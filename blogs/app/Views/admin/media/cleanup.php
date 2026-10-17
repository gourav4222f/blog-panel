<?php $this->layout('admin/layout'); $media = bf_app()->service(\BlogFlow\Services\MediaService::class); ?>
<div class="mb-5">
  <a class="btn btn-ghost btn-sm -ml-2" href="<?= e(bf_admin_url('media')) ?>"><?= bf_icon('arrow-left', 'h-3.5 w-3.5') ?> Media library</a>
  <h1 class="page-title mt-1">Media cleanup</h1>
  <p class="page-sub">Remove images that no post, category, author or setting uses, and stray files left on disk. Make a backup first.</p>
</div>
<form method="post" action="<?= e(bf_admin_url('media/cleanup')) ?>" class="space-y-5" data-bulk-form>
  <?= bf_csrf_field() ?>
  <section class="card">
    <div class="card-head"><h2 class="card-title">Unused media (<?= (int) $unusedCount ?>)</h2><label class="flex items-center gap-2 text-xs"><input class="checkbox" type="checkbox" data-check-all> Select all</label></div>
    <?php if ($unused): ?>
      <ul class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-4 lg:grid-cols-6">
        <?php foreach ($unused as $m): ?>
          <li><label class="block cursor-pointer overflow-hidden rounded-lg border border-stone-200 has-[:checked]:ring-2 has-[:checked]:ring-red-500">
            <img src="<?= e($media->url($m, 'thumb', false)) ?>" alt="" class="aspect-[4/3] w-full object-cover" loading="lazy">
            <span class="flex items-center gap-2 px-2 py-1.5 text-[11px]"><input class="checkbox" type="checkbox" name="media_ids[]" value="<?= (int) $m['id'] ?>" data-check><span class="truncate"><?= e($m['filename']) ?></span></span>
          </label></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?><p class="card-pad text-sm text-stone-500">Every image is in use.</p><?php endif; ?>
  </section>
  <section class="card">
    <div class="card-head"><h2 class="card-title">Orphan files on disk (<?= count($orphans) ?>)</h2></div>
    <?php if ($orphans): ?>
      <ul class="max-h-72 divide-y divide-stone-100 overflow-y-auto font-mono text-xs">
        <?php foreach ($orphans as $f): ?><li class="px-5 py-1.5"><label class="flex items-center gap-2"><input class="checkbox" type="checkbox" name="files[]" value="<?= e($f) ?>" data-check> <?= e($f) ?></label></li><?php endforeach; ?>
      </ul>
    <?php else: ?><p class="card-pad text-sm text-stone-500">No stray files found in uploads/.</p><?php endif; ?>
  </section>
  <div class="flex items-center gap-3"><button class="btn btn-danger-solid" type="submit" data-confirm="Permanently delete the selected media and files? This cannot be undone.">Delete selected</button><span class="text-xs text-stone-500" data-bulk-count></span></div>
</form>
