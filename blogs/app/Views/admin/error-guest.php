<?php $this->layout('admin/guest', ['pageTitle' => $title, 'flash' => []]); ?>
<h1 class="text-lg font-semibold"><?= e($title) ?></h1>
<p class="mt-2 text-sm text-stone-600"><?= e($message) ?></p>
<a class="btn btn-primary mt-5 w-full" href="<?= e(bf_admin_url('login')) ?>">Go to sign in</a>
