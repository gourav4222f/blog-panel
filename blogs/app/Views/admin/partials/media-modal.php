<template id="tpl-media-modal">
  <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="media-modal-title">
    <div class="modal sm:max-w-5xl h-[88vh]">
      <div class="flex items-center gap-3 border-b border-stone-200 px-4 py-3">
        <h2 id="media-modal-title" class="text-base font-semibold">Choose an image</h2>
        <label class="relative ml-auto hidden sm:block">
          <span class="sr-only">Search media</span>
          <input type="search" class="input h-8 w-56" placeholder="Search images…" data-media-search>
        </label>
        <button type="button" class="icon-btn" data-close aria-label="Close"><?= bf_icon('x') ?></button>
      </div>
      <div class="grid min-h-0 flex-1 md:grid-cols-[1fr_17rem]">
        <div class="min-h-0 overflow-y-auto p-4">
          <?php if (bf_can('media.upload')): ?>
            <label class="dropzone mb-4 cursor-pointer" data-dropzone>
              <?= bf_icon('upload', 'h-5 w-5') ?>
              <span><strong class="text-ink">Upload images</strong> or drag and drop here</span>
              <span class="text-xs">JPEG, PNG, WebP<?= \BlogFlow\Services\ImageService::canRead('avif') ? ', AVIF' : '' ?> or GIF · optimised automatically</span>
              <input type="file" class="sr-only" accept="image/jpeg,image/png,image/webp,image/avif,image/gif" multiple data-upload-input>
            </label>
          <?php endif; ?>
          <div class="grid grid-cols-3 gap-2.5 sm:grid-cols-4 lg:grid-cols-5" data-media-grid></div>
          <p class="py-8 text-center text-sm text-stone-500" data-media-empty hidden>No images found.</p>
          <div class="pt-4 text-center"><button type="button" class="btn btn-secondary btn-sm" data-media-more hidden>Load more</button></div>
        </div>
        <aside class="hidden min-h-0 overflow-y-auto border-l border-stone-200 bg-stone-50/60 p-4 md:block" data-media-detail>
          <p class="text-sm text-stone-500">Select an image to see its details.</p>
        </aside>
      </div>
      <div class="flex items-center justify-between gap-3 border-t border-stone-200 bg-stone-50 px-4 py-3">
        <p class="truncate text-xs text-stone-500" data-media-status></p>
        <div class="flex gap-2">
          <button type="button" class="btn btn-secondary" data-close>Cancel</button>
          <button type="button" class="btn btn-primary" data-media-select disabled>Use image</button>
        </div>
      </div>
    </div>
  </div>
</template>
