<?php
$this->layout('admin/layout');
$statusBadge = ['published' => 'badge-green', 'draft' => 'badge-gray', 'scheduled' => 'badge-blue'];
$stat = function (string $label, $value, string $href, string $icon, string $sub = '') {
    return '<a href="' . e($href) . '" class="card group flex items-center gap-3.5 p-4 transition hover:border-stone-300">'
        . '<span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-stone-100 text-stone-500 group-hover:bg-brand-50 group-hover:text-brand-700">' . bf_icon($icon, 'h-[18px] w-[18px]') . '</span>'
        . '<span class="min-w-0"><span class="block text-xl font-semibold leading-tight tabular-nums">' . e((string) $value) . '</span>'
        . '<span class="block truncate text-xs text-stone-500">' . e($label) . ($sub !== '' ? ' · ' . e($sub) : '') . '</span></span></a>';
};
$seoTotal = ($seo['critical'] ?? 0) + ($seo['warning'] ?? 0);
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="page-title">Welcome back, <?= e(explode(' ', $user['name'])[0]) ?></h1>
    <p class="page-sub">Here’s what’s happening on <?= e(bf_setting('general.blog_name')) ?>.</p>
  </div>
  <?php if (bf_can('posts.create')): ?><a class="btn btn-primary" href="<?= e(bf_admin_url('posts/new')) ?>"><?= bf_icon('plus') ?> Write a post</a><?php endif; ?>
</div>

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
  <?= $stat('Published posts', $counts['published'], bf_admin_url('posts', ['status' => 'published']), 'file') ?>
  <?= $stat('Drafts', $counts['draft'], bf_admin_url('posts', ['status' => 'draft']), 'edit') ?>
  <?= $stat('Scheduled', $counts['scheduled'], bf_admin_url('posts', ['status' => 'scheduled']), 'calendar') ?>
  <?= $stat('Categories', $categories, bf_admin_url('categories'), 'folder', $tags . ' tags') ?>
  <?= $stat('Media files', $mediaCount, bf_admin_url('media'), 'image', bf_bytes($mediaBytes)) ?>
  <?= $stat('Saved by optimisation', bf_bytes($mediaSaved), bf_admin_url('media/optimize'), 'zap') ?>
  <?= $stat('SEO issues', $lastAudit ? $seoTotal : '—', bf_admin_url('seo/audit'), 'seo', $lastAudit ? 'critical + warnings' : 'audit not run yet') ?>
  <?= $stat('Active redirects', $redirects, bf_admin_url('seo/redirects'), 'redirect') ?>
</div>

<div class="mt-6 grid gap-5 lg:grid-cols-3">
  <section class="card lg:col-span-2" aria-labelledby="recent-title">
    <div class="card-head"><h2 id="recent-title" class="card-title">Recently updated posts</h2><a class="text-xs font-medium text-stone-500 hover:text-ink" href="<?= e(bf_admin_url('posts')) ?>">View all</a></div>
    <?php if ($recent): ?>
      <ul class="divide-y divide-stone-100">
        <?php foreach ($recent as $p): ?>
          <li class="flex items-center gap-3 px-4 py-3 sm:px-5">
            <div class="min-w-0 flex-1">
              <a class="block truncate font-medium hover:underline" href="<?= e(bf_admin_url('posts/' . $p['id'] . '/edit')) ?>"><?= e($p['title']) ?></a>
              <p class="text-xs text-stone-500">Edited <?= e(bf_ago($p['updated_at'])) ?><?= $p['editor'] ? ' by ' . e($p['editor']) : '' ?></p>
            </div>
            <span class="badge <?= $statusBadge[$p['status']] ?? 'badge-gray' ?>"><?= e(ucfirst($p['status'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="p-8 text-center text-sm text-stone-500">
        <p>No posts yet.</p>
        <?php if (bf_can('posts.create')): ?><a class="btn btn-primary mt-3" href="<?= e(bf_admin_url('posts/new')) ?>">Write your first post</a><?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card" aria-labelledby="seo-title">
    <div class="card-head"><h2 id="seo-title" class="card-title">SEO health</h2><a class="text-xs font-medium text-stone-500 hover:text-ink" href="<?= e(bf_admin_url('seo/audit')) ?>">Open audit</a></div>
    <div class="card-pad">
      <?php if ($lastAudit): ?>
        <dl class="grid grid-cols-2 gap-2 text-sm">
          <?php foreach (['critical' => ['Critical', 'bg-red-500'], 'warning' => ['Warnings', 'bg-amber-500'], 'suggestion' => ['Suggestions', 'bg-sky-500'], 'passed' => ['Passed', 'bg-emerald-500']] as $k => [$label, $dot]): ?>
            <div class="rounded-lg border border-stone-100 bg-stone-50/60 px-3 py-2">
              <dt class="flex items-center gap-1.5 text-xs text-stone-500"><span class="h-2 w-2 rounded-full <?= $dot ?>"></span><?= e($label) ?></dt>
              <dd class="text-lg font-semibold tabular-nums"><?= (int) ($seo[$k] ?? 0) ?></dd>
            </div>
          <?php endforeach; ?>
        </dl>
        <p class="mt-3 text-xs text-stone-500">Last audit <?= e(bf_ago((string) $lastAudit)) ?>. Checks are advisory and do not predict rankings.</p>
      <?php else: ?>
        <p class="text-sm text-stone-600">Run the SEO audit to check titles, descriptions, headings, ALT text, links, redirects and indexing.</p>
        <form method="post" action="<?= e(bf_admin_url('seo/audit')) ?>" class="mt-3"><?= bf_csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit"><?= bf_icon('refresh', 'h-3.5 w-3.5') ?> Run audit now</button></form>
      <?php endif; ?>
    </div>
  </section>

  <section class="card" aria-labelledby="sched-title">
    <div class="card-head"><h2 id="sched-title" class="card-title">Upcoming scheduled posts</h2></div>
    <?php if ($scheduled): ?>
      <ul class="divide-y divide-stone-100">
        <?php foreach ($scheduled as $p): ?>
          <li class="px-4 py-2.5 sm:px-5"><a class="block truncate text-sm font-medium hover:underline" href="<?= e(bf_admin_url('posts/' . $p['id'] . '/edit')) ?>"><?= e($p['title']) ?></a><p class="text-xs text-stone-500"><?= e(bf_date($p['published_at'], 'D, M j · H:i')) ?></p></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?><p class="card-pad text-sm text-stone-500">Nothing scheduled.</p><?php endif; ?>
  </section>

  <section class="card" aria-labelledby="analytics-title">
    <div class="card-head"><h2 id="analytics-title" class="card-title">Analytics</h2></div>
    <div class="card-pad text-sm">
      <?php if ($ga4 || $gtm): ?>
        <p class="flex items-center gap-2"><span class="badge badge-green">Connected</span> <?= e($ga4 ?: $gtm) ?></p>
        <p class="mt-2 text-stone-600">Tracking code is active on public pages. Traffic reports are available in your analytics account — BlogFlow does not display or estimate visitor numbers.</p>
        <a class="btn btn-secondary btn-sm mt-3" href="https://analytics.google.com/" target="_blank" rel="noopener noreferrer"><?= bf_icon('external', 'h-3.5 w-3.5') ?> Open Google Analytics</a>
      <?php else: ?>
        <p class="flex items-center gap-2"><span class="badge badge-gray">Not configured</span></p>
        <p class="mt-2 text-stone-600">Connect Google Analytics 4 or Tag Manager to measure traffic. No analytics data is shown until an integration is configured.</p>
        <?php if (bf_can('settings.manage')): ?><a class="btn btn-secondary btn-sm mt-3" href="<?= e(bf_admin_url('settings/integrations')) ?>">Configure integrations</a><?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <section class="card" aria-labelledby="system-title">
    <div class="card-head"><h2 id="system-title" class="card-title">System</h2></div>
    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 card-pad text-[13px]">
      <dt class="text-stone-500">Version</dt><dd>BlogFlow <?= e(BF_VERSION) ?> · PHP <?= e(PHP_VERSION) ?></dd>
      <dt class="text-stone-500">Images</dt><dd><?= e(strtoupper($caps['driver'])) ?> · WebP <?= in_array('webp', $caps['write'], true) ? '✓' : '✕' ?> · AVIF <?= in_array('avif', $caps['write'], true) ? '✓' : 'not available' ?></dd>
      <dt class="text-stone-500">Page cache</dt><dd><?= bf_setting('general.page_cache') ? 'Enabled' : 'Disabled' ?></dd>
      <dt class="text-stone-500">Last backup</dt><dd><?= $lastBackup ? e(bf_ago(gmdate('Y-m-d H:i:s', $lastBackup))) : '<span class="text-amber-700">Never</span>' ?></dd>
      <dt class="text-stone-500">Sitemap</dt><dd><a class="text-brand-700 hover:underline break-all" href="<?= e(bf_url()->sitemap()) ?>" target="_blank" rel="noopener">sitemap.xml</a></dd>
    </dl>
  </section>

  <?php if ($activity): ?>
  <section class="card lg:col-span-3" aria-labelledby="activity-title">
    <div class="card-head"><h2 id="activity-title" class="card-title">Recent activity</h2><a class="text-xs font-medium text-stone-500 hover:text-ink" href="<?= e(bf_admin_url('activity')) ?>">Full log</a></div>
    <ul class="divide-y divide-stone-100">
      <?php foreach ($activity as $a): ?>
        <li class="flex items-start gap-3 px-4 py-2.5 text-[13px] sm:px-5">
          <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-stone-300"></span>
          <p class="min-w-0 flex-1"><span class="font-medium"><?= e($a['user_name'] ?? 'System') ?></span> <span class="text-stone-600"><?= e($a['summary'] ?? $a['action']) ?></span></p>
          <time class="shrink-0 text-xs text-stone-400" datetime="<?= e(bf_iso($a['created_at'])) ?>"><?= e(bf_ago($a['created_at'])) ?></time>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
</div>
