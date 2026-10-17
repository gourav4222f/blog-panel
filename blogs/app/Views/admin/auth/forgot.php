<?php $this->layout('admin/guest', ['pageTitle' => 'Reset password']); ?>
<h1 class="text-lg font-semibold">Reset your password</h1>
<p class="mt-1 text-sm text-stone-500">Enter your account email and we’ll send a reset link.</p>
<form method="post" action="<?= e(bf_admin_url('forgot')) ?>" class="mt-5 space-y-4">
  <?= bf_csrf_field() ?>
  <div>
    <label class="label" for="email">Email</label>
    <input class="input" id="email" name="email" type="email" autocomplete="email" required>
  </div>
  <button class="btn btn-primary w-full" type="submit">Send reset link</button>
  <p class="text-center text-xs"><a class="text-stone-500 hover:text-ink" href="<?= e(bf_admin_url('login')) ?>">Back to sign in</a></p>
</form>
