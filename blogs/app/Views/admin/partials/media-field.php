<?php
/** Media picker bound to a hidden input. @var string $name @var mixed $value @var string $id */
$media = bf_app()->service(\BlogFlow\Services\MediaService::class);
$row = $value ? $media->find((int) $value) : null;
$id ??= 'media-' . preg_replace('/[^a-z0-9]/i', '-', $name);
?>
<div class="flex items-center gap-3" data-media-field>
  <input type="hidden" name="<?= e($name) ?>" value="<?= $row ? (int) $row['id'] : '' ?>" id="<?= e($id) ?>" data-media-input>
  <div class="grid h-16 w-24 shrink-0 place-items-center overflow-hidden rounded-lg border border-stone-200 bg-stone-50 text-stone-300" data-media-preview>
    <?php if ($row): ?>
      <img src="<?= e($media->url($row, 'thumb', false)) ?>" alt="" class="h-full w-full object-cover">
    <?php else: ?>
      <?= bf_icon('image', 'h-5 w-5') ?>
    <?php endif; ?>
  </div>
  <div class="min-w-0 space-y-1.5">
    <p class="truncate text-xs text-stone-500" data-media-name><?= $row ? e($row['filename'] . ' · ' . $row['width'] . '×' . $row['height']) : 'No image selected' ?></p>
    <div class="flex gap-1.5">
      <button type="button" class="btn btn-secondary btn-sm" data-media-choose>Choose image</button>
      <button type="button" class="btn btn-ghost btn-sm" data-media-clear<?= $row ? '' : ' hidden' ?>>Remove</button>
    </div>
  </div>
</div>
