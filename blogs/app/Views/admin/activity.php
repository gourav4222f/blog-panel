<?php $this->layout('admin/layout'); ?>
<div class="mb-5"><h1 class="page-title">Activity log</h1><p class="page-sub">Important administrative actions, newest first.</p></div>
<div class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($items as $a): ?>
          <tr>
            <td class="whitespace-nowrap text-stone-500"><time datetime="<?= e(bf_iso($a['created_at'])) ?>"><?= e(bf_date($a['created_at'], 'M j, Y H:i')) ?></time></td>
            <td class="whitespace-nowrap"><?= e($a['user_name'] ?? 'System') ?></td>
            <td><span class="badge badge-gray"><?= e($a['action']) ?></span></td>
            <td class="max-w-md"><?= e($a['summary'] ?? '') ?></td>
            <td class="whitespace-nowrap font-mono text-xs text-stone-500"><?= e($a['ip'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="5" class="py-10 text-center text-stone-500">No activity recorded yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= $this->insert('admin/partials/pagination', ['page' => $page, 'pages' => $pages, 'total' => $total]) ?>
</div>
