<?php $this->layout('admin/layout'); $changes = count(array_filter($diff, fn ($d) => $d[0] !== '=')); ?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
  <div>
    <a class="btn btn-ghost btn-sm -ml-2" href="<?= e(bf_admin_url('posts/' . $post['id'] . '/revisions')) ?>"><?= bf_icon('arrow-left', 'h-3.5 w-3.5') ?> All revisions</a>
    <h1 class="page-title mt-2"><?= $rev['kind'] === 'autosave' ? 'Autosave' : 'Revision' ?> from <?= e(bf_date($rev['created_at'], 'M j, Y H:i')) ?></h1>
    <p class="page-sub">By <?= e($rev['user_name'] ?? 'unknown') ?> · <?= $changes ?> changed line(s) compared with the current version</p>
  </div>
  <form method="post" action="<?= e(bf_admin_url('posts/' . $post['id'] . '/revisions/' . $rev['id'] . '/restore')) ?>">
    <?= bf_csrf_field() ?>
    <button class="btn btn-primary" type="submit" data-confirm="Restore this version? The current content will be kept as a revision."><?= bf_icon('restore') ?> Restore this version</button>
  </form>
</div>
<div class="card card-pad mb-4 text-sm">
  <p><span class="text-stone-500">Title in this version:</span> <strong><?= e($rev['title']) ?></strong><?= $rev['title'] !== $post['title'] ? ' <span class="badge badge-amber">changed</span>' : '' ?></p>
</div>
<div class="card overflow-hidden">
  <div class="card-head"><h2 class="card-title">Content changes</h2><span class="flex gap-3 text-xs"><span class="text-red-700">− current</span><span class="text-emerald-700">+ this version</span></span></div>
  <div class="max-h-[70vh] overflow-y-auto font-mono text-[12.5px] leading-relaxed">
    <?php foreach ($diff as [$op, $line]): ?>
      <div class="flex gap-2 px-4 py-0.5 <?= $op === '-' ? 'bg-red-50 text-red-900' : ($op === '+' ? 'bg-emerald-50 text-emerald-900' : 'text-stone-500') ?>"><span class="w-3 shrink-0 select-none"><?= $op === '=' ? ' ' : e($op) ?></span><span class="whitespace-pre-wrap break-words"><?= e($line) ?></span></div>
    <?php endforeach; ?>
    <?php if (!$diff): ?><p class="p-6 text-center text-stone-500">Both versions are empty.</p><?php endif; ?>
  </div>
</div>
