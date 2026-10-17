<?php
$this->layout('admin/layout');
$tabs = ['all' => ['All', $counts['total']], 'published' => ['Published', $counts['published']], 'draft' => ['Drafts', $counts['draft']], 'scheduled' => ['Scheduled', $counts['scheduled']], 'featured' => ['Featured', $counts['featured']], 'trash' => ['Trash', $counts['trash']]];
$badge = ['published' => 'badge-green', 'draft' => 'badge-gray', 'scheduled' => 'badge-blue'];
$query = bf_app()->request->query;
$sortLink = function (string $col, string $label) use ($query, $filters) {
    $dir = $filters['sort'] === $col && $filters['dir'] === 'asc' ? 'desc' : 'asc';
    $q = array_merge($query, ['sort' => $col, 'dir' => $dir, 'page' => 1]);
    $arrow = $filters['sort'] === $col ? ($filters['dir'] === 'asc' ? ' ↑' : ' ↓') : '';
    $aria = $filters['sort'] === $col ? ' aria-sort="' . ($filters['dir'] === 'asc' ? 'ascending' : 'descending') . '"' : '';
    return '<a class="hover:text-ink" href="?' . e(http_build_query($q)) . '"' . $aria . '>' . e($label) . $arrow . '</a>';
};
$isTrash = $filters['status'] === 'trash';
?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
  <div><h1 class="page-title">Posts</h1><p class="page-sub"><?= (int) $total ?> <?= $total === 1 ? 'post' : 'posts' ?> in this view</p></div>
  <div class="flex gap-2">
    <?php if (bf_can('posts.edit_all')): ?>
      <details class="relative" data-dropdown>
        <summary class="btn btn-secondary list-none cursor-pointer"><?= bf_icon('download') ?> Export</summary>
        <div class="absolute right-0 z-10 mt-1 w-44 rounded-lg border border-stone-200 bg-white p-1 shadow-lg">
          <a class="nav-link" href="<?= e(bf_admin_url('posts/export', ['format' => 'json'])) ?>">JSON (full content)</a>
          <a class="nav-link" href="<?= e(bf_admin_url('posts/export', ['format' => 'csv'])) ?>">CSV (summary)</a>
        </div>
      </details>
    <?php endif; ?>
    <?php if (bf_can('posts.create')): ?><a class="btn btn-primary" href="<?= e(bf_admin_url('posts/new')) ?>"><?= bf_icon('plus') ?> Add new</a><?php endif; ?>
  </div>
</div>

<div class="card overflow-hidden">
  <div class="px-4 pt-2 sm:px-5">
    <nav class="tabs" aria-label="Post status">
      <?php foreach ($tabs as $key => [$label, $n]): ?>
        <a class="tab<?= $filters['status'] === $key ? ' is-active' : '' ?>" href="<?= e(bf_admin_url('posts', $key === 'all' ? [] : ['status' => $key])) ?>"<?= $filters['status'] === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?> <span class="ml-1 text-xs text-stone-400"><?= (int) $n ?></span></a>
      <?php endforeach; ?>
    </nav>
  </div>
  <form method="get" class="flex flex-wrap items-center gap-2 border-b border-stone-100 px-4 py-3 sm:px-5">
    <?php if ($filters['status'] !== 'all'): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif; ?>
    <label class="sr-only" for="pf-q">Search</label>
    <input id="pf-q" class="input h-9 w-full sm:w-64" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search title or slug…">
    <label class="sr-only" for="pf-cat">Category</label>
    <select id="pf-cat" class="select h-9 w-auto" name="category_id">
      <option value="">All categories</option>
      <?php foreach ($categories as $id => $name): ?><option value="<?= (int) $id ?>"<?= $filters['category_id'] === $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
    </select>
    <?php if (bf_can('posts.edit_all')): ?>
      <label class="sr-only" for="pf-author">Author</label>
      <select id="pf-author" class="select h-9 w-auto" name="author_id">
        <option value="">All authors</option>
        <?php foreach ($authors as $id => $name): ?><option value="<?= (int) $id ?>"<?= $filters['author_id'] === $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
      </select>
    <?php endif; ?>
    <button class="btn btn-secondary h-9" type="submit">Filter</button>
    <?php if ($filters['q'] !== '' || $filters['category_id'] || $filters['author_id']): ?><a class="btn btn-ghost h-9" href="<?= e(bf_admin_url('posts', $filters['status'] !== 'all' ? ['status' => $filters['status']] : [])) ?>">Reset</a><?php endif; ?>
  </form>

  <form method="post" action="<?= e(bf_admin_url('posts/bulk')) ?>" data-bulk-form>
    <?= bf_csrf_field() ?>
    <div class="flex flex-wrap items-center gap-2 border-b border-stone-100 bg-stone-50/50 px-4 py-2 sm:px-5" data-bulk-bar>
      <label class="sr-only" for="bulk-action">Bulk action</label>
      <select class="select h-8 w-auto text-xs" id="bulk-action" name="bulk_action">
        <option value="">Bulk actions</option>
        <?php if ($isTrash): ?>
          <option value="restore">Restore</option>
          <?php if (bf_can('posts.delete_all')): ?><option value="destroy">Delete permanently</option><?php endif; ?>
        <?php else: ?>
          <option value="publish">Publish</option>
          <option value="draft">Revert to draft</option>
          <?php if (bf_can('posts.edit_all')): ?><option value="feature">Mark featured</option><option value="unfeature">Remove featured</option><?php endif; ?>
          <option value="trash">Move to trash</option>
        <?php endif; ?>
      </select>
      <button class="btn btn-secondary btn-sm" type="submit" data-confirm="Apply this action to the selected posts?">Apply</button>
      <span class="text-xs text-stone-500" data-bulk-count></span>
    </div>
    <div class="overflow-x-auto">
      <table class="table">
        <thead>
          <tr>
            <th class="w-8"><input class="checkbox" type="checkbox" data-check-all aria-label="Select all posts"></th>
            <th><?= $sortLink('title', 'Title') ?></th>
            <th class="hidden md:table-cell">Category</th>
            <th class="hidden lg:table-cell">Author</th>
            <th><?= $sortLink('status', 'Status') ?></th>
            <th class="hidden sm:table-cell"><?= $sortLink('date', 'Date') ?></th>
            <th class="hidden xl:table-cell"><?= $sortLink('updated', 'Last edited') ?></th>
            <th class="w-10"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $p): $editUrl = bf_admin_url('posts/' . $p['id'] . '/edit'); ?>
            <tr>
              <td><input class="checkbox" type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" data-check aria-label="Select <?= e($p['title']) ?>"></td>
              <td class="min-w-[16rem]">
                <div class="flex items-center gap-3">
                  <div class="hidden h-10 w-14 shrink-0 overflow-hidden rounded-md bg-stone-100 sm:block">
                    <?php if ($p['image']): ?><img src="<?= e(bf_app()->service(\BlogFlow\Services\MediaService::class)->url($p['image'], 'thumb', false)) ?>" alt="" class="h-full w-full object-cover" loading="lazy"><?php endif; ?>
                  </div>
                  <div class="min-w-0">
                    <a class="font-medium hover:underline" href="<?= e($editUrl) ?>"><?= e($p['title']) ?></a>
                    <?php if ($p['is_featured']): ?><span class="badge badge-amber ml-1"><?= bf_icon('star', 'h-3 w-3') ?> Featured</span><?php endif; ?>
                    <p class="truncate text-xs text-stone-400">/<?= e($p['slug']) ?></p>
                  </div>
                </div>
              </td>
              <td class="hidden md:table-cell text-stone-600"><?= e($p['category_name'] ?? '—') ?></td>
              <td class="hidden lg:table-cell text-stone-600"><?= e($p['author_name'] ?? '—') ?></td>
              <td><?php if ($p['deleted_at']): ?><span class="badge badge-red">Trashed</span><?php else: ?><span class="badge <?= $badge[$p['status']] ?? 'badge-gray' ?>"><?= e(ucfirst($p['status'])) ?></span><?php endif; ?></td>
              <td class="hidden sm:table-cell whitespace-nowrap text-stone-600"><?= $p['published_at'] ? e(bf_date($p['published_at'], 'M j, Y')) : '<span class="text-stone-400">—</span>' ?></td>
              <td class="hidden xl:table-cell whitespace-nowrap text-xs text-stone-500"><?= e(bf_ago($p['updated_at'])) ?><?= $p['editor_name'] ? '<br>by ' . e($p['editor_name']) : '' ?></td>
              <td class="text-right">
                <details class="relative" data-dropdown>
                  <summary class="icon-btn list-none cursor-pointer" aria-label="Actions for <?= e($p['title']) ?>"><?= bf_icon('more') ?></summary>
                  <div class="absolute right-0 z-10 mt-1 w-44 rounded-lg border border-stone-200 bg-white p-1 text-left shadow-lg">
                    <?php if (!$p['deleted_at']): ?>
                      <a class="nav-link" href="<?= e($editUrl) ?>"><?= bf_icon('edit') ?> Edit</a>
                      <?php if ($p['status'] === 'published'): ?><a class="nav-link" href="<?= e(bf_url()->post($p['slug'])) ?>" target="_blank" rel="noopener"><?= bf_icon('external') ?> View</a><?php else: ?><a class="nav-link" href="<?= e(bf_url()->to('/preview/' . $p['id'])) ?>" target="_blank" rel="noopener"><?= bf_icon('eye') ?> Preview</a><?php endif; ?>
                      <button class="nav-link w-full" type="submit" form="act-dup-<?= (int) $p['id'] ?>"><?= bf_icon('copy') ?> Duplicate</button>
                      <button class="nav-link w-full text-red-700" type="submit" form="act-trash-<?= (int) $p['id'] ?>"><?= bf_icon('trash') ?> Trash</button>
                    <?php else: ?>
                      <button class="nav-link w-full" type="submit" form="act-restore-<?= (int) $p['id'] ?>"><?= bf_icon('restore') ?> Restore</button>
                      <?php if (bf_can('posts.delete_all')): ?><button class="nav-link w-full text-red-700" type="submit" form="act-destroy-<?= (int) $p['id'] ?>" data-confirm="Permanently delete “<?= e($p['title']) ?>”? This cannot be undone."><?= bf_icon('trash') ?> Delete permanently</button><?php endif; ?>
                    <?php endif; ?>
                  </div>
                </details>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$items): ?>
            <tr><td colspan="8" class="py-12 text-center text-stone-500"><?= $isTrash ? 'Trash is empty.' : 'No posts found.' ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </form>
  <?= $this->insert('admin/partials/pagination', ['page' => $page, 'pages' => $pages, 'total' => $total]) ?>
</div>
<?php foreach ($items as $p): $pid = (int) $p['id']; ?>
  <?php foreach (['dup' => 'duplicate', 'trash' => 'trash', 'restore' => 'restore', 'destroy' => 'destroy'] as $k => $route): ?>
    <form id="act-<?= $k ?>-<?= $pid ?>" method="post" action="<?= e(bf_admin_url('posts/' . $pid . '/' . $route)) ?>" hidden><?= bf_csrf_field() ?></form>
  <?php endforeach; ?>
<?php endforeach; ?>
