<?php $blogName = bf_app()->settings->str('general.blog_name') ?: 'Blog'; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? 'Sign in') ?> · <?= e($blogName) ?></title>
<link rel="stylesheet" href="<?= e(bf_asset('css/admin.css')) ?>">
</head>
<body class="grid min-h-screen place-items-center px-4 py-10">
  <main class="w-full max-w-sm">
    <div class="mb-6 text-center">
      <div class="mx-auto grid h-10 w-10 place-items-center rounded-xl bg-ink text-base font-semibold text-white"><?= e(mb_strtoupper(mb_substr($blogName, 0, 1))) ?></div>
      <p class="mt-3 text-sm font-semibold"><?= e($blogName) ?></p>
    </div>
    <?php foreach ($flash ?? [] as $f): $type = in_array($f['type'], ['success', 'error', 'info', 'warning'], true) ? $f['type'] : 'info'; ?>
      <div class="alert alert-<?= e($type) ?> mb-4" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <div class="card p-6"><?= $this->section('content') ?></div>
    <p class="mt-6 text-center text-xs text-stone-400"><a class="hover:text-ink" href="<?= e(bf_app()->url->home()) ?>">← Back to the blog</a></p>
  </main>
</body>
</html>
