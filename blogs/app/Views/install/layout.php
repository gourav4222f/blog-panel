<?php
/** @var string $step */
/** @var array $steps */
$keys = array_keys($steps);
$current = array_search($step, $keys, true);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Install BlogFlow CMS</title>
<link rel="stylesheet" href="<?= e($assetBase) ?>/css/admin.css">
</head>
<body class="min-h-screen">
<div class="mx-auto max-w-4xl px-4 py-8 sm:py-14">
  <div class="mb-8 flex items-center gap-3">
    <div class="grid h-9 w-9 place-items-center rounded-lg bg-ink text-white font-semibold">B</div>
    <div>
      <p class="text-base font-semibold leading-tight">BlogFlow CMS</p>
      <p class="text-xs text-stone-500">Installation wizard</p>
    </div>
  </div>
  <div class="grid gap-6 md:grid-cols-[13rem_1fr]">
    <ol class="flex md:flex-col gap-1 overflow-x-auto text-sm" aria-label="Installation steps">
      <?php foreach ($steps as $key => $label): $i = array_search($key, $keys, true); ?>
        <li class="flex items-center gap-2.5 rounded-md px-2.5 py-1.5 whitespace-nowrap <?= $key === $step ? 'bg-white shadow-card font-medium text-ink' : ($i < $current ? 'text-stone-600' : 'text-stone-400') ?>"<?= $key === $step ? ' aria-current="step"' : '' ?>>
          <span class="grid h-5 w-5 place-items-center rounded-full text-[11px] <?= $i < $current ? 'bg-emerald-600 text-white' : ($key === $step ? 'bg-ink text-white' : 'bg-stone-200 text-stone-500') ?>"><?= $i < $current ? '✓' : $i + 1 ?></span>
          <?= e($label) ?>
        </li>
      <?php endforeach; ?>
    </ol>
    <main class="card card-pad sm:p-7">
      <?php if (!empty($errors['_form'])): ?>
        <div class="alert alert-error mb-5" role="alert"><?= e($errors['_form']) ?></div>
      <?php endif; ?>
      <?= $this->section('content') ?>
    </main>
  </div>
  <p class="mt-8 text-center text-xs text-stone-400">BlogFlow CMS <?= e(BF_VERSION) ?> · The installer locks itself after setup.</p>
</div>
</body>
</html>
