<?php $this->layout('admin/layout'); $broken = array_filter($internal, fn ($l) => $l['status'] === 'missing'); ?>
<div class="mb-5"><h1 class="page-title">Link audit</h1><p class="page-sub">Links found in published posts. Internal links are verified against the database; external links are checked over HTTP on request.</p></div>
<div class="grid gap-5">
  <section class="card overflow-hidden">
    <div class="card-head"><h2 class="card-title">Internal links (<?= count($internal) ?>)</h2><span class="text-xs <?= $broken ? 'text-red-700' : 'text-emerald-700' ?>"><?= count($broken) ?> broken</span></div>
    <div class="overflow-x-auto"><table class="table"><thead><tr><th>Status</th><th>Link</th><th>In post</th></tr></thead><tbody>
      <?php foreach ($internal as $l): ?>
        <tr><td><?= match ($l['status']) { 'ok' => '<span class="badge badge-green">OK</span>', 'redirect' => '<span class="badge badge-amber">Redirects</span>', default => '<span class="badge badge-red">Broken</span>' } ?></td>
          <td class="max-w-md break-all font-mono text-xs"><?= e($l['href']) ?></td>
          <td><a class="hover:underline" href="<?= e(bf_admin_url('posts/' . $l['post_id'] . '/edit')) ?>"><?= e($l['title']) ?></a></td></tr>
      <?php endforeach; ?>
      <?php if (!$internal): ?><tr><td colspan="3" class="py-8 text-center text-stone-500">No internal links found. Linking related articles helps readers and search engines.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
  <section class="card overflow-hidden" data-runner data-reload data-url="<?= e(bf_admin_url('seo/links/check')) ?>">
    <div class="card-head flex-wrap"><h2 class="card-title">External links (<?= count($external) ?>)</h2>
      <?php if ($curl): ?><div class="flex items-center gap-2"><span class="text-xs text-stone-500" data-run-label aria-live="polite">Links are re-checked when older than 7 days.</span><button type="button" class="btn btn-secondary btn-sm" data-run-start>Check external links</button><button type="button" class="btn btn-ghost btn-sm" data-run-stop hidden>Pause</button></div><?php else: ?><span class="text-xs text-amber-700">cURL is not available, so external links cannot be verified on this server.</span><?php endif; ?>
    </div>
    <div class="h-1 bg-stone-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="Progress"><div class="h-full bg-brand-600" style="width:0" data-run-bar></div></div>
    <ul class="hidden" data-run-log></ul>
    <div class="overflow-x-auto"><table class="table"><thead><tr><th>Status</th><th>Link</th><th>In post</th><th class="hidden md:table-cell">Checked</th></tr></thead><tbody>
      <?php foreach ($external as $l): $c = $l['check']; $code = $c ? (int) $c['status_code'] : null; ?>
        <tr><td><?php if (!$c): ?><span class="badge badge-gray">Unchecked</span><?php elseif ($code >= 200 && $code < 300): ?><span class="badge badge-green"><?= $code ?></span><?php elseif ($code >= 300 && $code < 400): ?><span class="badge badge-amber"><?= $code ?> redirect</span><?php else: ?><span class="badge badge-red" title="<?= e($c['error'] ?? '') ?>"><?= $code ?: 'Error' ?></span><?php endif; ?></td>
          <td class="max-w-md break-all font-mono text-xs"><a class="hover:underline" href="<?= e($l['href']) ?>" target="_blank" rel="noopener noreferrer"><?= e($l['href']) ?></a><?php if ($c && $c['error']): ?><span class="block text-[11px] text-stone-500"><?= e($c['error']) ?></span><?php endif; ?></td>
          <td><a class="hover:underline" href="<?= e(bf_admin_url('posts/' . $l['post_id'] . '/edit')) ?>"><?= e($l['title']) ?></a></td>
          <td class="hidden md:table-cell text-xs text-stone-500"><?= $c ? e(bf_ago($c['checked_at'])) : '—' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$external): ?><tr><td colspan="4" class="py-8 text-center text-stone-500">No external links found.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
</div>
