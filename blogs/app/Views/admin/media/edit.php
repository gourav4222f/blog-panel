<?php
$this->layout('admin/layout');
$media = bf_app()->service(\BlogFlow\Services\MediaService::class);
$saved = $row['original_filesize'] > 0 ? max(0, (int) round((1 - $row['filesize'] / $row['original_filesize']) * 100)) : 0;
$fmt = strtoupper((string) ($row['variants']['base'] ?? pathinfo($row['path'], PATHINFO_EXTENSION)));
?>
<div class="mb-5">
  <a class="btn btn-ghost btn-sm -ml-2" href="<?= e(bf_admin_url('media')) ?>"><?= bf_icon('arrow-left', 'h-3.5 w-3.5') ?> Media library</a>
  <h1 class="page-title mt-1 break-all"><?= e($row['title'] ?: $row['filename']) ?></h1>
</div>
<div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
  <div class="space-y-5">
    <div class="card overflow-hidden">
      <div class="grid place-items-center bg-[repeating-conic-gradient(#f5f5f4_0%_25%,#fff_0%_50%)] bg-[length:20px_20px] p-4">
        <img src="<?= e($media->url($row, 'large', false)) ?>" alt="<?= e($row['alt_text'] ?? '') ?>" class="max-h-[60vh] w-auto rounded" width="<?= (int) $row['width'] ?>" height="<?= (int) $row['height'] ?>">
      </div>
    </div>
    <section class="card" aria-labelledby="opt-title">
      <div class="card-head"><h2 id="opt-title" class="card-title">Optimisation</h2>
        <?php if ($canModify && $row['mime'] !== 'image/gif'): ?>
          <form method="post" action="<?= e(bf_admin_url('media/' . $row['id'] . '/optimize')) ?>"><?= bf_csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit"><?= bf_icon('zap', 'h-3.5 w-3.5') ?> Re-optimise</button></form>
        <?php endif; ?>
      </div>
      <dl class="grid grid-cols-2 gap-px bg-stone-100 text-sm sm:grid-cols-3">
        <?php foreach ([
          ['Original size', bf_bytes((int) $row['original_filesize'])],
          ['Optimised size', bf_bytes((int) $row['filesize'])],
          ['Reduction', $saved . '%'],
          ['Original dimensions', (int) $row['original_width'] . ' × ' . (int) $row['original_height']],
          ['Output dimensions', (int) $row['width'] . ' × ' . (int) $row['height']],
          ['Output format', $fmt . (isset($row['variants']['full']['webp']) ? ' + WebP' : '') . (isset($row['variants']['full']['avif']) ? ' + AVIF' : '')],
        ] as [$label, $value]): ?>
          <div class="bg-white px-4 py-3"><dt class="text-xs text-stone-500"><?= e($label) ?></dt><dd class="font-semibold tabular-nums"><?= e($value) ?></dd></div>
        <?php endforeach; ?>
      </dl>
      <div class="card-pad text-xs text-stone-500">
        <?= $row['optimized_at'] ? 'Optimised ' . e(bf_date($row['optimized_at'], 'M j, Y H:i')) : ($row['mime'] === 'image/gif' ? 'Animated GIFs are stored unchanged.' : 'Not optimised yet.') ?>
        <?= (int) $row['optimization_version'] !== (int) $currentVersion && $row['mime'] !== 'image/gif' ? ' · <span class="text-amber-700">Image settings changed since — re-optimise to apply them.</span>' : '' ?>
        <?= $row['original_path'] ? ' · Private original kept for lossless re-processing.' : '' ?>
        · All files: <?= e(bf_bytes($totalBytes)) ?>
      </div>
      <?php if ($variants): ?>
        <details class="border-t border-stone-100">
          <summary class="cursor-pointer px-5 py-3 text-sm font-medium">Responsive versions (<?= count($variants) ?>)</summary>
          <ul class="divide-y divide-stone-100 text-xs">
            <?php foreach ($variants as $v): ?><li class="flex justify-between gap-3 px-5 py-2"><a class="text-brand-700 hover:underline" href="<?= e($v['url']) ?>" target="_blank" rel="noopener"><?= e($v['label']) ?></a><span class="text-stone-500"><?= (int) $v['w'] ?>×<?= (int) $v['h'] ?> · <?= e(bf_bytes((int) $v['bytes'])) ?></span></li><?php endforeach; ?>
          </ul>
        </details>
      <?php endif; ?>
    </section>
    <section class="card" aria-labelledby="usage-title">
      <div class="card-head"><h2 id="usage-title" class="card-title">Where it’s used</h2></div>
      <?php if ($usage): ?>
        <ul class="divide-y divide-stone-100 text-sm"><?php foreach ($usage as $u): ?><li class="flex items-center gap-2 px-5 py-2.5"><span class="badge badge-gray"><?= e($u['type']) ?></span><?php if ($u['url']): ?><a class="hover:underline" href="<?= e($u['url']) ?>"><?= e($u['label']) ?></a><?php else: ?><?= e($u['label']) ?><?php endif; ?></li><?php endforeach; ?></ul>
      <?php else: ?><p class="card-pad text-sm text-stone-500">Not used anywhere. It can be deleted safely.</p><?php endif; ?>
    </section>
  </div>

  <div class="space-y-5">
    <form method="post" action="<?= e(bf_admin_url('media/' . $row['id'])) ?>" class="card card-pad space-y-4">
      <?= bf_csrf_field() ?>
      <fieldset class="space-y-4"<?= $canModify ? '' : ' disabled' ?>>
        <div><label class="label" for="m-alt">ALT text</label><textarea class="textarea<?= isset($errors['alt_text']) ? ' is-invalid' : '' ?>" id="m-alt" name="alt_text" rows="3" maxlength="500"><?= e($old['alt_text'] ?? $row['alt_text'] ?? '') ?></textarea><p class="help">Describe the image for people who can’t see it. Used wherever no post-specific ALT is set.</p></div>
        <div><label class="label" for="m-title">Title</label><input class="input" id="m-title" name="title" value="<?= e($old['title'] ?? $row['title'] ?? '') ?>" maxlength="255"></div>
        <div><label class="label" for="m-caption">Caption</label><textarea class="textarea" id="m-caption" name="caption" rows="2"><?= e($old['caption'] ?? $row['caption'] ?? '') ?></textarea></div>
        <div><label class="label" for="m-credit">Credit / attribution</label><input class="input" id="m-credit" name="credit" value="<?= e($old['credit'] ?? $row['credit'] ?? '') ?>" maxlength="255" placeholder="Photo: Name / Source"></div>
        <div><label class="label" for="m-file">Filename</label><div class="flex items-center gap-1"><input class="input font-mono text-xs" id="m-file" name="filename" value="<?= e(pathinfo($row['filename'], PATHINFO_FILENAME)) ?>" maxlength="80"><span class="text-xs text-stone-500">.<?= e(pathinfo($row['filename'], PATHINFO_EXTENSION)) ?></span></div><p class="help">Renaming changes the file URLs; posts update automatically.</p></div>
        <button class="btn btn-primary w-full" type="submit">Save details</button>
      </fieldset>
    </form>
    <div class="card card-pad space-y-3 text-sm">
      <div><span class="label">File URL</span><div class="flex gap-1.5"><input class="input text-xs" value="<?= e($url) ?>" readonly aria-label="File URL"><button type="button" class="btn btn-secondary btn-sm" data-copy-text="<?= e($url) ?>">Copy</button></div></div>
      <p class="text-xs text-stone-500">Uploaded <?= e(bf_date($row['created_at'], 'M j, Y H:i')) ?><?= $uploader ? ' by ' . e($uploader) : '' ?> · original name <?= e($row['original_name'] ?? '—') ?></p>
    </div>
    <?php if ($canModify): ?>
      <form method="post" action="<?= e(bf_admin_url('media/' . $row['id'] . '/replace')) ?>" enctype="multipart/form-data" class="card card-pad space-y-3">
        <?= bf_csrf_field() ?>
        <label class="label" for="m-replace">Replace image</label>
        <input class="block w-full text-xs file:mr-3 file:rounded-md file:border-0 file:bg-stone-100 file:px-3 file:py-1.5 file:text-xs file:font-medium" id="m-replace" type="file" name="file" accept="image/jpeg,image/png,image/webp,image/avif,image/gif" required>
        <p class="help">Keeps the ALT text, caption and every place the image is used.</p>
        <button class="btn btn-secondary w-full" type="submit" data-confirm="Replace this image everywhere it is used?">Upload replacement</button>
      </form>
      <form method="post" action="<?= e(bf_admin_url('media/' . $row['id'] . '/delete')) ?>">
        <?= bf_csrf_field() ?>
        <?php if ($usage): ?><input type="hidden" name="force" value="1"><?php endif; ?>
        <button class="btn btn-danger w-full" type="submit" data-confirm="<?= $usage ? e('This image is used in ' . count($usage) . ' place(s). Deleting it will leave those places without an image. Delete anyway?') : 'Delete this image permanently?' ?>"><?= bf_icon('trash') ?> Delete image</button>
      </form>
    <?php endif; ?>
  </div>
</div>
