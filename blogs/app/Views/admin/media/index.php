<?php
$this->layout('admin/layout');
$media = bf_app()->service(\BlogFlow\Services\MediaService::class);
$query = bf_app()->request->query;
$link = fn (array $over) => '?' . http_build_query(array_merge($query, $over, ['page' => 1]));
$filters = ['all' => 'All', 'missing-alt' => 'Missing ALT', 'unoptimized' => 'Needs optimising', 'large' => 'Oversized', 'unused' => 'Unused'];
$saved = max(0, (int) $stats['original'] - (int) $stats['bytes']);
?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
  <div><h1 class="page-title">Media library</h1><p class="page-sub"><?= (int) $stats['n'] ?> images · <?= e(bf_bytes((int) $stats['bytes'])) ?> stored · <?= e(bf_bytes($saved)) ?> saved by optimisation</p></div>
  <div class="flex flex-wrap gap-2">
    <?php if (bf_can('media.manage')): ?>
      <a class="btn btn-secondary" href="<?= e(bf_admin_url('media/optimize')) ?>"><?= bf_icon('zap') ?> Bulk optimise</a>
      <a class="btn btn-secondary" href="<?= e(bf_admin_url('media/cleanup')) ?>"><?= bf_icon('trash') ?> Cleanup</a>
    <?php endif; ?>
  </div>
</div>

<?php if (bf_can('media.upload')): ?>
  <label class="dropzone mb-5 cursor-pointer" data-library-dropzone>
    <?= bf_icon('upload', 'h-6 w-6') ?>
    <span><strong class="text-ink">Click to upload</strong> or drag and drop images here</span>
    <span class="text-xs">JPEG, PNG, WebP<?= in_array('avif', $caps['read'], true) ? ', AVIF' : '' ?>, GIF · up to <?= e(bf_bytes($maxUpload)) ?> each · resized, compressed<?= in_array('webp', $caps['write'], true) ? ', WebP' : '' ?><?= in_array('avif', $caps['write'], true) ? '/AVIF' : '' ?> versions generated automatically</span>
    <span class="text-xs font-medium text-brand-700" data-library-status aria-live="polite"></span>
    <input type="file" class="sr-only" accept="image/jpeg,image/png,image/webp,image/avif,image/gif" multiple>
  </label>
<?php endif; ?>

<div class="card overflow-hidden">
  <div class="flex flex-wrap items-center gap-2 border-b border-stone-100 px-4 py-3">
    <nav class="flex flex-wrap gap-1" aria-label="Filter">
      <?php foreach ($filters as $k => $label): ?>
        <a class="btn btn-sm <?= $filter === $k ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e($link(['filter' => $k === 'all' ? null : $k])) ?>"<?= $filter === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <form method="get" class="ml-auto flex gap-2">
      <?php foreach (['filter', 'view'] as $keep): if (!empty($query[$keep])): ?><input type="hidden" name="<?= $keep ?>" value="<?= e($query[$keep]) ?>"><?php endif; endforeach; ?>
      <label class="sr-only" for="media-q">Search media</label>
      <input id="media-q" class="input h-8 w-48" type="search" name="q" value="<?= e($q) ?>" placeholder="Search…">
    </form>
    <div class="flex gap-1" role="group" aria-label="View">
      <a class="icon-btn<?= $mode === 'grid' ? ' bg-stone-100 text-ink' : '' ?>" href="<?= e($link(['view' => 'grid'])) ?>" aria-label="Grid view"<?= $mode === 'grid' ? ' aria-current="true"' : '' ?>><?= bf_icon('grid') ?></a>
      <a class="icon-btn<?= $mode === 'list' ? ' bg-stone-100 text-ink' : '' ?>" href="<?= e($link(['view' => 'list'])) ?>" aria-label="List view"<?= $mode === 'list' ? ' aria-current="true"' : '' ?>><?= bf_icon('list') ?></a>
    </div>
  </div>

  <?php if (!$items): ?>
    <p class="py-14 text-center text-sm text-stone-500">No images found.</p>
  <?php elseif ($mode === 'grid'): ?>
    <ul class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
      <?php foreach ($items as $m): ?>
        <li>
          <a href="<?= e(bf_admin_url('media/' . $m['id'])) ?>" class="group block overflow-hidden rounded-lg border border-stone-200 bg-white hover:border-stone-400">
            <span class="relative block aspect-[4/3] bg-stone-100">
              <img src="<?= e($media->url($m, 'thumb', false)) ?>" alt="<?= e($m['alt_text'] ?? '') ?>" class="h-full w-full object-cover" loading="lazy" width="<?= (int) $m['width'] ?>" height="<?= (int) $m['height'] ?>">
              <?php if (!$m['alt_text']): ?><span class="absolute left-1.5 top-1.5 rounded bg-amber-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">No ALT</span><?php endif; ?>
            </span>
            <span class="block px-2.5 py-2">
              <span class="block truncate text-xs font-medium"><?= e($m['title'] ?: $m['filename']) ?></span>
              <span class="block text-[11px] text-stone-500"><?= (int) $m['width'] ?>×<?= (int) $m['height'] ?> · <?= e(bf_bytes((int) $m['filesize'])) ?></span>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table class="table">
        <thead><tr><th>Image</th><th class="hidden md:table-cell">Dimensions</th><th>Size</th><th class="hidden lg:table-cell">Saved</th><th class="hidden sm:table-cell">ALT</th><th class="hidden lg:table-cell">Uploaded</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($items as $m): $pct = $m['original_filesize'] > 0 ? max(0, (int) round((1 - $m['filesize'] / $m['original_filesize']) * 100)) : 0; ?>
            <tr>
              <td><div class="flex items-center gap-3"><img src="<?= e($media->url($m, 'thumb', false)) ?>" alt="" class="h-10 w-14 shrink-0 rounded object-cover" loading="lazy"><div class="min-w-0"><a class="block truncate font-medium hover:underline" href="<?= e(bf_admin_url('media/' . $m['id'])) ?>"><?= e($m['title'] ?: $m['filename']) ?></a><span class="block truncate text-xs text-stone-500"><?= e($m['filename']) ?></span></div></div></td>
              <td class="hidden md:table-cell text-stone-600"><?= (int) $m['width'] ?>×<?= (int) $m['height'] ?></td>
              <td class="whitespace-nowrap"><?= e(bf_bytes((int) $m['filesize'])) ?></td>
              <td class="hidden lg:table-cell"><?= $m['optimized_at'] ? '<span class="badge badge-green">' . $pct . '%</span>' : '<span class="badge badge-gray">—</span>' ?></td>
              <td class="hidden sm:table-cell"><?= $m['alt_text'] ? '<span class="badge badge-green">Yes</span>' : '<span class="badge badge-amber">Missing</span>' ?></td>
              <td class="hidden lg:table-cell whitespace-nowrap text-xs text-stone-500"><?= e(bf_date($m['created_at'], 'M j, Y')) ?><?= $m['uploader'] ? '<br>' . e($m['uploader']) : '' ?></td>
              <td class="text-right"><button type="button" class="icon-btn" data-copy-text="<?= e($media->url($m)) ?>" aria-label="Copy URL"><?= bf_icon('copy') ?></button></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?= $this->insert('admin/partials/pagination', ['page' => $page, 'pages' => $pages, 'total' => $total]) ?>
</div>
