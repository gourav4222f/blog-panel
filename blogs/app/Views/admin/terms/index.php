<?php $this->layout('admin/layout'); $route = $meta['route']; $isAuthor = $route === 'authors'; ?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
  <div><h1 class="page-title"><?= e($meta['plural']) ?></h1><p class="page-sub"><?= count($rows) ?> total</p></div>
  <div class="flex gap-2">
    <form method="get" class="hidden sm:block"><label class="sr-only" for="term-q">Search</label><input id="term-q" class="input h-9 w-56" type="search" name="q" value="<?= e($search) ?>" placeholder="Search…"></form>
    <a class="btn btn-primary" href="<?= e(bf_admin_url($route . '/new')) ?>"><?= bf_icon('plus') ?> Add <?= e(strtolower($meta['singular'])) ?></a>
  </div>
</div>
<div class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>Name</th><th class="hidden sm:table-cell">Slug</th><th class="hidden md:table-cell">Indexing</th><th>Published</th><th class="hidden sm:table-cell">All posts</th><th class="w-24"><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): $name = $r[$meta['name_field']]; ?>
          <tr>
            <td>
              <span style="padding-left: <?= (int) ($r['depth'] ?? 0) * 1.25 ?>rem" class="inline-block"><?= ($r['depth'] ?? 0) ? '<span class="text-stone-300">└ </span>' : '' ?><a class="font-medium hover:underline" href="<?= e(bf_admin_url($route . '/' . $r['id'] . '/edit')) ?>"><?= e($name) ?></a></span>
              <?php if (!empty($r['description']) || !empty($r['bio'])): ?><p class="max-w-md truncate text-xs text-stone-500" style="padding-left: <?= (int) ($r['depth'] ?? 0) * 1.25 ?>rem"><?= e(mb_substr((string) ($r['description'] ?? $r['bio']), 0, 140)) ?></p><?php endif; ?>
            </td>
            <td class="hidden sm:table-cell font-mono text-xs text-stone-500"><?= e($r['slug']) ?></td>
            <td class="hidden md:table-cell"><span class="badge <?= $r['robots'] === 'noindex' ? 'badge-amber' : 'badge-gray' ?>"><?= e($r['robots'] === 'default' ? 'Default' : $r['robots']) ?></span></td>
            <td class="tabular-nums"><?= (int) $r['published_count'] ?></td>
            <td class="hidden sm:table-cell tabular-nums text-stone-500"><?= (int) $r['post_count'] ?></td>
            <td class="whitespace-nowrap text-right">
              <?php if ((int) $r['published_count'] > 0): ?><a class="icon-btn" href="<?= e($publicUrl($r['slug'])) ?>" target="_blank" rel="noopener" aria-label="View <?= e($name) ?>"><?= bf_icon('external') ?></a><?php endif; ?>
              <a class="icon-btn" href="<?= e(bf_admin_url($route . '/' . $r['id'] . '/edit')) ?>" aria-label="Edit <?= e($name) ?>"><?= bf_icon('edit') ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="py-12 text-center text-stone-500">Nothing here yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
