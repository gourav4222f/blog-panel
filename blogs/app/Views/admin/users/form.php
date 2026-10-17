<?php
$this->layout('admin/layout');
$val = fn ($k, $d = '') => $old[$k] ?? $row[$k] ?? $d;
$err = fn ($k) => isset($errors[$k]) ? '<p class="error-text">' . e($errors[$k]) . '</p>' : '';
?>
<div class="mb-5">
  <a class="btn btn-ghost btn-sm -ml-2" href="<?= e(bf_admin_url('users')) ?>"><?= bf_icon('arrow-left', 'h-3.5 w-3.5') ?> Users</a>
  <h1 class="page-title mt-1"><?= $row ? e($row['name']) : 'Add user' ?></h1>
</div>
<form method="post" action="<?= e(bf_admin_url($row ? 'users/' . $row['id'] : 'users')) ?>" class="card card-pad grid max-w-2xl gap-4 sm:grid-cols-2" autocomplete="off">
  <?= bf_csrf_field() ?>
  <div><label class="label" for="u-name">Name</label><input class="input" id="u-name" name="name" value="<?= e($val('name')) ?>" required maxlength="120"><?= $err('name') ?></div>
  <div><label class="label" for="u-email">Email</label><input class="input" id="u-email" type="email" name="email" value="<?= e($val('email')) ?>" required maxlength="190"><?= $err('email') ?></div>
  <div><label class="label" for="u-role">Role</label>
    <select class="select" id="u-role" name="role"><?php foreach (\BlogFlow\Core\Auth::ROLES as $k => $l): ?><option value="<?= e($k) ?>"<?= $val('role', 'author') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('role') ?></div>
  <div><label class="label" for="u-status">Status</label>
    <select class="select" id="u-status" name="status"><option value="active"<?= $val('status', 'active') === 'active' ? ' selected' : '' ?>>Active</option><option value="disabled"<?= $val('status') === 'disabled' ? ' selected' : '' ?>>Disabled (cannot sign in)</option></select><?= $err('status') ?></div>
  <div class="sm:col-span-2"><label class="label" for="u-pass"><?= $row ? 'New password' : 'Password' ?></label><input class="input" id="u-pass" type="password" name="password" autocomplete="new-password"<?= $row ? '' : ' required' ?>>
    <?= $err('password') ?: '<p class="help">' . ($row ? 'Leave empty to keep the current password. Changing it signs the user out everywhere. ' : '') . 'At least ' . (int) bf_setting('security.min_password_length') . ' characters.</p>' ?></div>
  <?php if (!$row || !$row['author_profile_id']): ?>
    <label class="sm:col-span-2 flex items-center gap-2 text-sm"><input class="checkbox" type="checkbox" name="create_author" value="1"<?= $row ? '' : ' checked' ?>> Create a public author profile for this user</label>
  <?php else: ?>
    <p class="sm:col-span-2 text-sm text-stone-600">Author profile: <a class="text-brand-700 hover:underline" href="<?= e(bf_admin_url('authors/' . $row['author_profile_id'] . '/edit')) ?>"><?= e($row['author_name']) ?></a></p>
  <?php endif; ?>
  <div class="sm:col-span-2 flex items-center justify-between gap-2 pt-1">
    <button class="btn btn-primary" type="submit"><?= $row ? 'Save user' : 'Create user' ?></button>
    <?php if ($row && (int) $row['id'] !== (int) $user['id']): ?><button class="btn btn-danger" type="submit" form="user-delete" data-confirm="Delete this user? Their posts are kept.">Delete user</button><?php endif; ?>
  </div>
</form>
<?php if ($row): ?><form id="user-delete" method="post" action="<?= e(bf_admin_url('users/' . $row['id'] . '/delete')) ?>" hidden><?= bf_csrf_field() ?></form><?php endif; ?>
