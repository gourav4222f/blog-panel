<?php
$this->layout('admin/layout');
$types = \BlogFlow\Services\MenuService::TYPES;
$row = function (?array $i) use ($types, $categories, $tags, $posts) {
    $i ??= ['label' => '', 'link_type' => 'custom', 'url' => '', 'target_id' => null, 'new_tab' => 0, 'is_visible' => 1, 'parent_id' => null, 'id' => ''];
    $child = !empty($i['parent_id']);
    ob_start(); ?>
    <li class="rounded-lg border border-stone-200 bg-white<?= $child ? ' is-child ml-8' : '' ?>" data-menu-item<?= $i['id'] ? ' data-key="m' . (int) $i['id'] . '"' : '' ?>>
      <div class="flex items-center gap-2 px-2 py-2">
        <button type="button" class="icon-btn cursor-grab" data-handle aria-label="Drag to reorder"><?= bf_icon('drag') ?></button>
        <button type="button" class="min-w-0 flex-1 truncate text-left text-sm font-medium" data-toggle-row><span data-summary><?= e($i['label']) ?></span> <span class="ml-1 text-xs font-normal text-stone-400" data-type-label></span></button>
        <button type="button" class="btn btn-ghost btn-sm" data-indent title="Make a sub-item of the item above (or back to top level)">Indent</button>
        <button type="button" class="icon-btn text-red-700" data-remove aria-label="Remove item"><?= bf_icon('trash') ?></button>
      </div>
      <div class="grid gap-3 border-t border-stone-100 p-3 sm:grid-cols-2" data-row-body hidden>
        <label class="block"><span class="label">Label</span><input class="input" data-field="label" value="<?= e($i['label']) ?>" maxlength="120"></label>
        <label class="block"><span class="label">Link to</span><select class="select" data-field="link_type"><?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>"<?= $i['link_type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label class="block sm:col-span-2" data-show-for="custom"><span class="label">URL</span><input class="input font-mono text-xs" data-field="url" value="<?= e($i['url'] ?? '') ?>" placeholder="https://example.com/services or /blogs/… or mailto:"></label>
        <label class="block sm:col-span-2" data-show-for="category"><span class="label">Category</span><select class="select" data-field="target_category"><?php foreach ($categories as $id => $n): ?><option value="<?= (int) $id ?>"<?= $i['link_type'] === 'category' && (int) $i['target_id'] === $id ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
        <label class="block sm:col-span-2" data-show-for="tag"><span class="label">Tag</span><select class="select" data-field="target_tag"><?php foreach ($tags as $t): ?><option value="<?= (int) $t['id'] ?>"<?= $i['link_type'] === 'tag' && (int) $i['target_id'] === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></label>
        <label class="block sm:col-span-2" data-show-for="post"><span class="label">Post</span><select class="select" data-field="target_post"><?php foreach ($posts as $p): ?><option value="<?= (int) $p['id'] ?>"<?= $i['link_type'] === 'post' && (int) $i['target_id'] === (int) $p['id'] ? ' selected' : '' ?>><?= e($p['title']) ?></option><?php endforeach; ?></select></label>
        <label class="flex items-center gap-2 text-sm"><input class="checkbox" type="checkbox" data-field="new_tab"<?= $i['new_tab'] ? ' checked' : '' ?>> Open in a new tab</label>
        <label class="flex items-center gap-2 text-sm"><input class="checkbox" type="checkbox" data-field="is_visible"<?= $i['is_visible'] ? ' checked' : '' ?>> Visible</label>
      </div>
    </li>
    <?php return (string) ob_get_clean();
};
?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
  <div><h1 class="page-title">Menu builder</h1><p class="page-sub">Links to blog pages, your main website or anywhere else. Drag to reorder; use “Indent” for dropdown items.</p></div>
</div>
<nav class="tabs mb-5" aria-label="Menu location">
  <?php foreach (\BlogFlow\Services\MenuService::LOCATIONS as $k => $l): ?>
    <a class="tab<?= $location === $k ? ' is-active' : '' ?>" href="<?= e(bf_admin_url('appearance/menus', ['location' => $k])) ?>"<?= $location === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a>
  <?php endforeach; ?>
</nav>
<div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]" data-menu-builder data-save-url="<?= e(bf_admin_url('appearance/menus/' . $location)) ?>">
  <section class="card card-pad">
    <ul class="space-y-2" data-menu-list>
      <?php foreach ($items as $i): ?><?= $row($i) ?><?php endforeach; ?>
    </ul>
    <?php if (!$items): ?><p class="py-4 text-center text-sm text-stone-500">This menu is empty.</p><?php endif; ?>
    <div class="mt-4 flex gap-2">
      <button type="button" class="btn btn-secondary" data-add-item><?= bf_icon('plus') ?> Add link</button>
      <button type="button" class="btn btn-primary ml-auto" data-save-menu>Save menu</button>
    </div>
  </section>
  <aside class="card card-pad space-y-2 self-start text-sm text-stone-600">
    <h2 class="card-title text-ink">Tips</h2>
    <p><strong>Main website</strong> links to <?= $mainUrl ? '<span class="break-all">' . e($mainUrl) . '</span>' : 'the URL set in General settings (not set yet)' ?>.</p>
    <p>Blog category, tag and post links keep working if their slugs change.</p>
    <p>External links opened in a new tab automatically get <code>rel="noopener noreferrer"</code>.</p>
    <p>The header shows one level of dropdowns; the footer lists sub-items under their parent.</p>
  </aside>
</div>
<template id="tpl-menu-item"><?= $row(null) ?></template>
