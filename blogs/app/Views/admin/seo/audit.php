<?php
$this->layout('admin/layout');
$sev = ['critical' => ['Critical', 'bg-red-500', 'badge-red'], 'warning' => ['Warning', 'bg-amber-500', 'badge-amber'], 'suggestion' => ['Suggestion', 'bg-sky-500', 'badge-blue'], 'passed' => ['Passed', 'bg-emerald-500', 'badge-green']];
$query = bf_app()->request->query;
$link = fn (array $o) => '?' . http_build_query(array_filter(array_merge($query, $o), fn ($v) => $v !== '' && $v !== null));
$entityLink = function (array $r) {
    return match ($r['entity_type']) {
        'post' => bf_admin_url('posts/' . $r['entity_id'] . '/edit'),
        'category' => bf_admin_url('categories/' . $r['entity_id'] . '/edit'),
        'tag' => bf_admin_url('tags/' . $r['entity_id'] . '/edit'),
        'author' => bf_admin_url('authors/' . $r['entity_id'] . '/edit'),
        'media' => $r['entity_id'] ? bf_admin_url('media/' . $r['entity_id']) : bf_admin_url('media', ['filter' => 'missing-alt']),
        'redirect' => bf_admin_url('seo/redirects'),
        default => bf_can('settings.manage') ? bf_admin_url('seo/metadata') : null,
    };
};
?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
  <div><h1 class="page-title">SEO audit</h1><p class="page-sub"><?= $lastAudit ? 'Last run ' . e(bf_ago((string) $lastAudit)) : 'Not run yet' ?> · rule-based checks of published and scheduled content. Advisory only — no ranking is implied.</p></div>
  <form method="post" action="<?= e(bf_admin_url('seo/audit')) ?>"><?= bf_csrf_field() ?><button class="btn btn-primary" type="submit"><?= bf_icon('refresh') ?> Run audit</button></form>
</div>
<div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
  <?php foreach ($sev as $k => [$label, $dot]): ?>
    <a href="<?= e($link(['severity' => $k])) ?>" class="card flex items-center gap-3 p-4 hover:border-stone-300<?= $severity === $k ? ' ring-2 ring-ink' : '' ?>">
      <span class="h-2.5 w-2.5 rounded-full <?= $dot ?>"></span><span class="text-sm text-stone-600"><?= e($label) ?></span><span class="ml-auto text-xl font-semibold tabular-nums"><?= (int) ($counts[$k] ?? 0) ?></span>
    </a>
  <?php endforeach; ?>
</div>
<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
  <section class="card overflow-hidden">
    <div class="flex flex-wrap items-center gap-1 border-b border-stone-100 px-4 py-3">
      <?php foreach (['' => 'All types', 'site' => 'Site', 'post' => 'Posts', 'category' => 'Categories', 'tag' => 'Tags', 'author' => 'Authors', 'redirect' => 'Redirects', 'media' => 'Media'] as $k => $l): ?>
        <a class="btn btn-sm <?= $type === $k ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e($link(['type' => $k])) ?>"><?= e($l) ?></a>
      <?php endforeach; ?>
      <?php if ($severity !== ''): ?><a class="btn btn-sm btn-ghost ml-auto" href="<?= e($link(['severity' => ''])) ?>">Show all issues</a><?php endif; ?>
    </div>
    <ul class="divide-y divide-stone-100">
      <?php foreach ($rows as $r): $href = $entityLink($r); ?>
        <li class="flex items-start gap-3 px-4 py-3 text-sm">
          <span class="badge <?= $sev[$r['severity']][2] ?> mt-0.5 shrink-0"><?= e($sev[$r['severity']][0]) ?></span>
          <div class="min-w-0 flex-1">
            <p><?= e($r['message']) ?></p>
            <p class="mt-0.5 text-xs text-stone-500"><?= e(ucfirst($r['entity_type'])) ?><?= $r['post_title'] ? ': ' . e($r['post_title']) : '' ?></p>
          </div>
          <?php if ($href): ?><a class="btn btn-secondary btn-sm shrink-0" href="<?= e($href) ?>">Fix</a><?php endif; ?>
        </li>
      <?php endforeach; ?>
      <?php if (!$rows): ?><li class="py-12 text-center text-sm text-stone-500"><?= $lastAudit ? 'No issues in this view.' : 'Run the audit to see results.' ?></li><?php endif; ?>
    </ul>
  </section>
  <aside class="card self-start">
    <div class="card-head"><h2 class="card-title">Posts needing attention</h2></div>
    <ul class="divide-y divide-stone-100 text-sm">
      <?php foreach ($postScores as $p): if (!$p['critical'] && !$p['warning']) { continue; } ?>
        <li class="px-4 py-2.5"><a class="block truncate font-medium hover:underline" href="<?= e(bf_admin_url('posts/' . $p['id'] . '/edit')) ?>"><?= e($p['title']) ?></a>
          <p class="text-xs text-stone-500"><?= (int) $p['critical'] ?> critical · <?= (int) $p['warning'] ?> warnings · <?= (int) $p['suggestion'] ?> suggestions</p></li>
      <?php endforeach; ?>
    </ul>
  </aside>
</div>
