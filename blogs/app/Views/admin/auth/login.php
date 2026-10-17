<?php $this->layout('admin/guest', ['pageTitle' => 'Sign in']); ?>
<?php if ($installed): ?><div class="alert alert-success mb-4">Installation complete. Sign in with the administrator account you created.</div><?php endif; ?>
<h1 class="text-lg font-semibold">Sign in</h1>
<form method="post" action="<?= e(bf_admin_url('login')) ?>" class="mt-5 space-y-4">
  <?= bf_csrf_field() ?>
  <div>
    <label class="label" for="email">Email</label>
    <input class="input" id="email" name="email" type="email" autocomplete="username" required value="<?= e($old['email'] ?? '') ?>" autofocus>
  </div>
  <div>
    <div class="flex items-center justify-between"><label class="label" for="password">Password</label><a class="mb-1.5 text-xs text-stone-500 hover:text-ink" href="<?= e(bf_admin_url('forgot')) ?>">Forgot password?</a></div>
    <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
  </div>
  <button class="btn btn-primary w-full" type="submit">Sign in</button>
</form>
