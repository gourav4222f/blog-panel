<?php $this->layout('admin/layout'); ?>
<div class="mb-5">
  <a class="btn btn-ghost btn-sm -ml-2" href="<?= e(bf_admin_url('posts/' . $post['id'] . '/edit')) ?>"><?= bf_icon('arrow-left', 'h-3.5 w-3.5') ?> Back to editor</a>
  <h1 class="page-title mt-2">Revision history</h1>
  <p class="page-sub"><?= e($post['title']) ?> · the newest <?= \BlogFlow\Services\PostService::MAX_REVISIONS ?> saved versions are kept.</p>
</div>
<div class="card overflow-hidden">
  <table class="table">
    <thead><tr><th>Saved</th><th>By</th><th>Title</th><th class="hidden sm:table-cell">Words</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($revisions as $i => $r): ?>
        <tr>
          <td class="whitespace-nowrap"><?= e(bf_date($r['created_at'], 'M j, Y H:i')) ?> <?php if ($r['kind'] === 'autosave'): ?><span class="badge badge-amber">Autosave</span><?php elseif ($i === 0): ?><span class="badge badge-green">Current</span><?php endif; ?><?php if ($r['note']): ?><p class="text-xs text-stone-500"><?= e($r['note']) ?></p><?php endif; ?></td>
          <td class="whitespace-nowrap text-stone-600"><?= e($r['user_name'] ?? '—') ?></td>
          <td class="max-w-xs truncate"><?= e($r['title']) ?></td>
          <td class="hidden sm:table-cell tabular-nums text-stone-600"><?= (int) $r['words'] ?></td>
          <td class="text-right"><a class="btn btn-secondary btn-sm" href="<?= e(bf_admin_url('posts/' . $post['id'] . '/revisions/' . $r['id'])) ?>">Compare</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$revisions): ?><tr><td colspan="5" class="py-10 text-center text-stone-500">No revisions yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
