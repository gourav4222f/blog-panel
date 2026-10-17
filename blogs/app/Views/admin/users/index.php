<?php $this->layout('admin/layout'); $roleBadge = ['admin' => 'badge-violet', 'editor' => 'badge-blue', 'author' => 'badge-gray']; ?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
  <div><h1 class="page-title">Users &amp; roles</h1><p class="page-sub">Administrators manage everything · Editors manage all content, media and SEO · Authors write their own posts<?= bf_setting('security.authors_can_publish') ? ' and can publish them' : ' and submit drafts for review' ?>.</p></div>
  <a class="btn btn-primary" href="<?= e(bf_admin_url('users/new')) ?>"><?= bf_icon('plus') ?> Add user</a>
</div>
<div class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>User</th><th>Role</th><th class="hidden md:table-cell">Author profile</th><th class="hidden sm:table-cell">Posts</th><th class="hidden lg:table-cell">Last sign-in</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><p class="font-medium"><?= e($u['name']) ?><?= (int) $u['id'] === (int) $user['id'] ? ' <span class="text-xs text-stone-400">(you)</span>' : '' ?></p><p class="text-xs text-stone-500"><?= e($u['email']) ?></p></td>
            <td><span class="badge <?= $roleBadge[$u['role']] ?? 'badge-gray' ?>"><?= e(\BlogFlow\Core\Auth::ROLES[$u['role']] ?? $u['role']) ?></span><?php if ($u['status'] !== 'active'): ?> <span class="badge badge-red">Disabled</span><?php endif; ?></td>
            <td class="hidden md:table-cell text-stone-600"><?= e($u['author_name'] ?? '—') ?></td>
            <td class="hidden sm:table-cell tabular-nums"><?= (int) $u['post_count'] ?></td>
            <td class="hidden lg:table-cell text-xs text-stone-500"><?= $u['last_login_at'] ? e(bf_ago($u['last_login_at'])) : 'Never' ?></td>
            <td class="text-right"><a class="icon-btn" href="<?= e(bf_admin_url('users/' . $u['id'] . '/edit')) ?>" aria-label="Edit <?= e($u['name']) ?>"><?= bf_icon('edit') ?></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
