<?php $this->layout('admin/layout'); $yes = '<span class="badge badge-green">index</span>'; $no = '<span class="badge badge-amber">noindex</span>'; ?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
  <div><h1 class="page-title">SEO overview</h1><p class="page-sub">Indexing, sitemap and search engine setup for this blog.</p></div>
  <form method="post" action="<?= e(bf_admin_url('seo/audit')) ?>"><?= bf_csrf_field() ?><button class="btn btn-primary" type="submit"><?= bf_icon('refresh') ?> Run SEO audit</button></form>
</div>
<div class="grid gap-5 lg:grid-cols-3">
  <section class="card lg:col-span-2">
    <div class="card-head"><h2 class="card-title">Content health</h2><a class="text-xs text-stone-500 hover:text-ink" href="<?= e(bf_admin_url('seo/audit')) ?>">Audit details</a></div>
    <dl class="grid grid-cols-2 gap-px bg-stone-100 sm:grid-cols-3">
      <?php foreach ([['Published posts', $published, null], ['Without description', $noDescription, $noDescription ? 'text-amber-700' : ''], ['Without featured image', $noImage, $noImage ? 'text-amber-700' : ''], ['Set to noindex', $noindex, ''], ['Canonical elsewhere', $foreignCanonical, ''], ['URLs in sitemap', array_sum($sitemapCounts), '']] as [$l, $v, $cls]): ?>
        <div class="bg-white px-5 py-4"><dt class="text-xs text-stone-500"><?= e($l) ?></dt><dd class="text-xl font-semibold tabular-nums <?= e((string) $cls) ?>"><?= (int) $v ?></dd></div>
      <?php endforeach; ?>
    </dl>
    <div class="card-pad text-sm">
      <?php if ($lastAudit): ?>
        <p>Last audit <?= e(bf_ago((string) $lastAudit)) ?>: <strong class="text-red-700"><?= (int) ($counts['critical'] ?? 0) ?> critical</strong>, <strong class="text-amber-700"><?= (int) ($counts['warning'] ?? 0) ?> warnings</strong>, <?= (int) ($counts['suggestion'] ?? 0) ?> suggestions, <?= (int) ($counts['passed'] ?? 0) ?> passed.</p>
      <?php else: ?><p class="text-stone-600">The audit has not been run yet.</p><?php endif; ?>
    </div>
  </section>
  <section class="card">
    <div class="card-head"><h2 class="card-title">Indexing defaults</h2><?php if (bf_can('settings.manage')): ?><a class="text-xs text-stone-500 hover:text-ink" href="<?= e(bf_admin_url('seo/metadata')) ?>">Change</a><?php endif; ?></div>
    <dl class="divide-y divide-stone-100 text-sm"><?php foreach ($indexing as $label => $on): ?><div class="flex justify-between px-5 py-2"><dt><?= e($label) ?></dt><dd><?= $on ? $yes : $no ?></dd></div><?php endforeach; ?>
      <div class="flex justify-between px-5 py-2"><dt>Admin &amp; previews</dt><dd><?= $no ?></dd></div></dl>
  </section>
  <section class="card lg:col-span-2">
    <div class="card-head"><h2 class="card-title">Sitemap &amp; robots.txt</h2></div>
    <div class="card-pad space-y-4 text-sm">
      <div><span class="label">XML sitemap</span><div class="flex gap-1.5"><input class="input text-xs" readonly value="<?= e($sitemapUrl) ?>" aria-label="Sitemap URL"><a class="btn btn-secondary btn-sm" href="<?= e($sitemapUrl) ?>" target="_blank" rel="noopener">Open</a></div>
        <p class="help">Contains <?php $parts = []; foreach ($sitemapCounts as $k => $n) { $parts[] = $n . ' ' . $k; } echo e(implode(', ', $parts)); ?>. Only published, indexable, self-canonical URLs are listed; it updates automatically.</p></div>
      <div><span class="label">RSS feed</span><input class="input text-xs" readonly value="<?= e($feedUrl) ?>" aria-label="Feed URL"></div>
      <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
        <p class="font-medium text-amber-900">Add this line to the main website’s robots.txt</p>
        <p class="mt-1 text-xs text-amber-900/80">robots.txt only works at the domain root (<?= e(($mainUrl ? rtrim($mainUrl, '/') : '') . '/robots.txt') ?>), so a /blogs/robots.txt file would be ignored. BlogFlow never edits the root file for you.</p>
        <div class="mt-2 flex gap-1.5"><code class="block flex-1 overflow-x-auto rounded bg-white px-3 py-2 text-xs"><?= e($robotsLine) ?></code><button type="button" class="btn btn-secondary btn-sm" data-copy-text="<?= e($robotsLine) ?>">Copy</button></div>
      </div>
      <div>
        <p class="font-medium">Submit to Google Search Console</p>
        <ol class="mt-1 list-decimal space-y-1 pl-5 text-stone-600">
          <li>Open <a class="text-brand-700 underline" href="https://search.google.com/search-console" target="_blank" rel="noopener noreferrer">Search Console</a> and select (or add) the property for your domain.</li>
          <li>If you need to verify ownership with a meta tag, paste the code in <a class="text-brand-700 underline" href="<?= e(bf_admin_url('settings/integrations')) ?>">Integrations</a>.</li>
          <li>Go to <em>Indexing → Sitemaps</em>, enter the sitemap URL above and press <em>Submit</em>.</li>
          <li>Repeat in Bing Webmaster Tools if you use it.</li>
        </ol>
      </div>
    </div>
  </section>
  <section class="card">
    <div class="card-head"><h2 class="card-title">Verification</h2></div>
    <dl class="divide-y divide-stone-100 text-sm"><?php foreach ($verification as $label => $on): ?><div class="flex justify-between px-5 py-2.5"><dt><?= e($label) ?></dt><dd><?= $on ? '<span class="badge badge-green">Meta tag set</span>' : '<span class="badge badge-gray">Not set</span>' ?></dd></div><?php endforeach; ?></dl>
    <p class="card-pad text-xs text-stone-500">Ranking and traffic data live in those tools; BlogFlow never displays estimated rankings.</p>
  </section>
</div>
