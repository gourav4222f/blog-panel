<?php
$this->layout('admin/layout');
$val = fn ($k) => $old[$k] ?? $row[$k] ?? '';
$err = fn ($k) => isset($errors[$k]) ? '<p class="error-text">' . e($errors[$k]) . '</p>' : '';
?>
<div class="mb-5"><h1 class="page-title">Your profile</h1><p class="page-sub"><?= e(\BlogFlow\Core\Auth::ROLES[$row['role']]) ?><?php if ($row['author_profile_id']): ?> · public author profile: <a class="text-brand-700 hover:underline" href="<?= e(bf_can('authors.manage') ? bf_admin_url('authors/' . $row['author_profile_id'] . '/edit') : bf_url()->home()) ?>"><?= e($row['author_name']) ?></a><?php endif; ?></p></div>
<form method="post" action="<?= e(bf_admin_url('profile')) ?>" class="max-w-2xl space-y-5">
  <?= bf_csrf_field() ?>
  <section class="card card-pad grid gap-4 sm:grid-cols-2">
    <div><label class="label" for="p-name">Name</label><input class="input" id="p-name" name="name" value="<?= e($val('name')) ?>" required maxlength="120" autocomplete="name"><?= $err('name') ?></div>
    <div><label class="label" for="p-email">Email</label><input class="input" id="p-email" type="email" name="email" value="<?= e($val('email')) ?>" required autocomplete="email"><?= $err('email') ?></div>
  </section>
  <section class="card card-pad grid gap-4 sm:grid-cols-2">
    <h2 class="card-title sm:col-span-2">Change password</h2>
    <div><label class="label" for="p-new">New password</label><input class="input" id="p-new" type="password" name="password" autocomplete="new-password"><?= $err('password') ?></div>
    <div><label class="label" for="p-confirm">Confirm new password</label><input class="input" id="p-confirm" type="password" name="password_confirm" autocomplete="new-password"></div>
    <div class="sm:col-span-2"><label class="label" for="p-current">Current password</label><input class="input" id="p-current" type="password" name="current_password" autocomplete="current-password"><?= $err('current_password') ?: '<p class="help">Required when changing your email or password.</p>' ?></div>
  </section>
  <button class="btn btn-primary" type="submit">Save profile</button>
</form>
