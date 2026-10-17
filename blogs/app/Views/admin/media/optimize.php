<?php $this->layout('admin/layout'); $saved = max(0, (int) $stats['original'] - (int) $stats['current']); ?>
<div class="mb-5">
  <a class="btn btn-ghost btn-sm -ml-2" href="<?= e(bf_admin_url('media')) ?>"><?= bf_icon('arrow-left', 'h-3.5 w-3.5') ?> Media library</a>
  <h1 class="page-title mt-1">Bulk image optimisation</h1>
  <p class="page-sub">Re-processes images with the current <a class="underline" href="<?= e(bf_admin_url('settings/images')) ?>">image settings</a>, in small batches that are safe for shared hosting.</p>
</div>
<div class="grid gap-5 lg:grid-cols-3">
  <section class="card card-pad lg:col-span-2 space-y-4" data-runner data-url="<?= e(bf_admin_url('media/optimize/batch')) ?>">
    <div class="flex flex-wrap items-center gap-3">
      <p class="text-sm"><strong class="tabular-nums"><?= (int) $pending ?></strong> of <?= (int) $total ?> image(s) need processing.</p>
      <div class="ml-auto flex gap-2">
        <button type="button" class="btn btn-primary" data-run-start<?= $pending ? '' : ' disabled' ?>><?= $pending ? 'Start' : 'Nothing to do' ?></button>
        <button type="button" class="btn btn-secondary" data-run-stop hidden>Pause</button>
      </div>
    </div>
    <div class="h-2 overflow-hidden rounded-full bg-stone-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $total ? (int) round(($total - $pending) / $total * 100) : 100 ?>" aria-label="Progress">
      <div class="h-full bg-brand-600 transition-all" style="width: <?= $total ? (int) round(($total - $pending) / $total * 100) : 100 ?>%" data-run-bar></div>
    </div>
    <p class="text-xs text-stone-500" data-run-label aria-live="polite">Progress is saved after each batch — you can leave and resume later.</p>
    <ul class="max-h-72 overflow-y-auto rounded-lg bg-stone-50 p-3 font-mono text-[11.5px] leading-relaxed text-stone-600" data-run-log></ul>
  </section>
  <aside class="card card-pad space-y-3 text-sm">
    <h2 class="card-title">How it works</h2>
    <ul class="list-disc space-y-1.5 pl-4 text-stone-600">
      <li>Images with a private original (<?= (int) $withOriginal ?>) are rebuilt from it — no quality loss accumulates.</li>
      <li>Other images keep their stored file; only missing sizes and formats are generated.</li>
      <li>Existing files are replaced only after new versions are written successfully.</li>
      <li>Engine: <?= e(strtoupper($caps['driver'])) ?> · WebP <?= in_array('webp', $caps['write'], true) ? 'available' : 'not available' ?> · AVIF <?= in_array('avif', $caps['write'], true) ? 'available' : 'not available on this server' ?>.</li>
    </ul>
    <p class="border-t border-stone-100 pt-3 text-stone-600">Total saved so far: <strong><?= e(bf_bytes($saved)) ?></strong></p>
  </aside>
</div>
