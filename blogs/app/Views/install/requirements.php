<?php $this->layout('install/layout'); ?>
<h1 class="page-title">Check the server environment</h1>
<p class="page-sub">BlogFlow runs inside this folder only and never changes your main website.</p>
<ul class="mt-6 divide-y divide-stone-100 rounded-lg border border-stone-200">
  <?php foreach ($checks as $c): ?>
    <li class="flex items-start gap-3 px-4 py-2.5 text-sm">
      <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full text-[11px] <?= $c['ok'] ? 'bg-emerald-100 text-emerald-700' : ($c['required'] ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800') ?>"><?= $c['ok'] ? '✓' : ($c['required'] ? '✕' : '!') ?></span>
      <div class="min-w-0">
        <p class="font-medium"><?= e($c['label']) ?><?= $c['required'] ? '' : ' <span class="text-stone-400 font-normal">(optional)</span>' ?></p>
        <?php if ($c['detail'] !== ''): ?><p class="text-xs text-stone-500 break-all"><?= e($c['detail']) ?></p><?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
  <li class="flex items-start gap-3 px-4 py-2.5 text-sm" id="rewrite-check" data-url="<?= e($basePath) ?>/__bf/rewrite-check">
    <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-stone-100 text-[11px] text-stone-500" data-icon>…</span>
    <div>
      <p class="font-medium">Clean URLs (Apache mod_rewrite / Nginx try_files)</p>
      <p class="text-xs text-stone-500" data-detail>Checking… (requires JavaScript)</p>
    </div>
  </li>
</ul>
<div class="mt-6 flex items-center justify-between gap-3">
  <a class="btn btn-ghost" href="?step=requirements">Re-check</a>
  <?php if ($requirementsOk): ?>
    <a class="btn btn-primary" href="?step=database">Continue</a>
  <?php else: ?>
    <span class="text-sm text-red-700">Fix the required items above to continue.</span>
  <?php endif; ?>
</div>
<script src="<?= e($assetBase) ?>/js/install.js" defer></script>
