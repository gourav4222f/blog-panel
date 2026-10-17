<?php $this->layout('admin/layout'); ?>
<div class="card card-pad mx-auto max-w-lg text-center">
  <p class="text-xs font-semibold uppercase tracking-wider text-stone-400">Error <?= (int) $status ?></p>
  <h1 class="page-title mt-1"><?= e($title) ?></h1>
  <p class="mt-2 text-sm text-stone-600"><?= e($message) ?></p>
  <?php if (!empty($exception)): ?><pre class="mt-4 overflow-x-auto rounded-lg bg-slate-900 p-3 text-left text-xs text-red-200"><?= e(get_class($exception) . ': ' . $exception->getMessage() . "\n" . $exception->getFile() . ':' . $exception->getLine()) ?></pre><?php endif; ?>
  <a class="btn btn-secondary mt-5" href="<?= e(bf_admin_url()) ?>">Back to dashboard</a>
</div>
