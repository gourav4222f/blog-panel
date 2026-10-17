<?php $this->layout('admin/guest', ['pageTitle' => 'Choose a new password']); ?>
<h1 class="text-lg font-semibold">Choose a new password</h1>
<form method="post" action="<?= e(bf_admin_url('reset/' . $token)) ?>" class="mt-5 space-y-4">
  <?= bf_csrf_field() ?>
  <div>
    <label class="label" for="password">New password</label>
    <input class="input<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password" type="password" autocomplete="new-password" required minlength="8">
    <?php if (isset($errors['password'])): ?><p class="error-text"><?= e($errors['password']) ?></p><?php else: ?><p class="help">At least <?= (int) bf_setting('security.min_password_length') ?> characters.</p><?php endif; ?>
  </div>
  <div>
    <label class="label" for="password_confirm">Confirm password</label>
    <input class="input" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required>
  </div>
  <button class="btn btn-primary w-full" type="submit">Update password</button>
</form>
