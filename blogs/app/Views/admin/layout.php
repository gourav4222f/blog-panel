<?php
/** @var array $user */
/** @var array $flash */
$app = bf_app();
$nav = \BlogFlow\Helpers\AdminNav::items($app);
$blogName = $app->settings->str('general.blog_name') ?: 'Blog';
$initials = mb_strtoupper(mb_substr($user['name'] ?? '?', 0, 1));
$pageTitle = $pageTitle ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(bf_csrf_token()) ?>">
<meta name="bf-admin-base" content="<?= e(bf_admin_url()) ?>">
<title><?= e($pageTitle) ?> · <?= e($blogName) ?> admin</title>
<link rel="preload" href="<?= e($app->url->basePath()) ?>/assets/fonts/inter-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(bf_asset('css/admin.css')) ?>">
<?= $this->section('head') ?>
</head>
<body class="min-h-screen text-[14px]">
<a href="#admin-main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[60] btn btn-primary">Skip to content</a>

<div class="bf-admin-sidebar fixed inset-y-0 left-0 z-40 w-[15.5rem] -translate-x-full border-r border-stone-200 bg-[#FBFBFA] transition-transform lg:translate-x-0" id="admin-sidebar" data-sidebar>
  <div class="flex h-14 items-center gap-2.5 border-b border-stone-200/70 px-4">
    <div class="grid h-7 w-7 shrink-0 place-items-center rounded-md bg-ink text-[13px] font-semibold text-white"><?= e(mb_strtoupper(mb_substr($blogName, 0, 1))) ?></div>
    <div class="min-w-0">
      <p class="truncate text-[13px] font-semibold leading-tight"><?= e($blogName) ?></p>
      <p class="text-[11px] text-stone-400 leading-tight">BlogFlow CMS</p>
    </div>
    <button type="button" class="icon-btn ml-auto lg:hidden" data-sidebar-close aria-label="Close menu"><?= bf_icon('x') ?></button>
  </div>
  <nav class="h-[calc(100vh-3.5rem)] overflow-y-auto px-2.5 pb-8 pt-2" aria-label="Admin">
    <?php foreach ($nav as $group): ?>
      <?php if ($group['label']): ?><p class="nav-group"><?= e($group['label']) ?></p><?php endif; ?>
      <ul class="space-y-px">
        <?php foreach ($group['items'] as $item): ?>
          <li>
            <a class="nav-link<?= $item['active'] ? ' is-active' : '' ?>" href="<?= e($item['href']) ?>"<?= $item['active'] && !$item['children'] ? ' aria-current="page"' : '' ?>>
              <?= bf_icon($item['icon'], 'h-[17px] w-[17px] shrink-0 ' . ($item['active'] ? 'text-ink' : 'text-stone-400')) ?>
              <span><?= e($item['label']) ?></span>
            </a>
            <?php if ($item['children'] && $item['active']): ?>
              <ul class="nav-sub">
                <?php foreach ($item['children'] as $child): ?>
                  <li><a class="nav-sublink<?= $child['active'] ? ' is-active' : '' ?>" href="<?= e($child['href']) ?>"<?= $child['active'] ? ' aria-current="page"' : '' ?>><?= e($child['label']) ?></a></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </nav>
</div>
<div class="fixed inset-0 z-30 bg-slate-900/30 lg:hidden" data-sidebar-backdrop hidden></div>

<div class="bf-admin-main lg:pl-[15.5rem]">
  <header class="bf-admin-topbar sticky top-0 z-20 flex h-14 items-center gap-2 border-b border-stone-200/70 bg-[#F6F6F4]/90 px-3 backdrop-blur sm:px-5">
    <button type="button" class="icon-btn lg:hidden" data-sidebar-open aria-label="Open menu" aria-controls="admin-sidebar"><?= bf_icon('menu') ?></button>
    <form class="relative hidden max-w-sm flex-1 sm:block" action="<?= e(bf_admin_url('posts')) ?>" method="get" role="search">
      <label class="sr-only" for="admin-search">Search posts</label>
      <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-stone-400"><?= bf_icon('search') ?></span>
      <input id="admin-search" class="input h-9 bg-white/70 pl-8" type="search" name="q" placeholder="Search posts…" value="<?= e((string) ($app->request->query['q'] ?? '')) ?>" data-hotkey="/">
      <span class="pointer-events-none absolute right-2.5 top-1/2 hidden -translate-y-1/2 md:block"><span class="kbd">/</span></span>
    </form>
    <div class="ml-auto flex items-center gap-1.5">
      <a class="btn btn-ghost hidden sm:inline-flex" href="<?= e($app->url->home()) ?>" target="_blank" rel="noopener"><?= bf_icon('external') ?> View blog</a>
      <?php if (bf_can('posts.create')): ?>
        <a class="btn btn-primary" href="<?= e(bf_admin_url('posts/new')) ?>"><?= bf_icon('plus') ?><span class="hidden sm:inline"> New post</span></a>
      <?php endif; ?>
      <details class="relative" data-dropdown>
        <summary class="flex h-9 cursor-pointer list-none items-center gap-2 rounded-full pl-1 pr-2 hover:bg-stone-200/60" aria-label="Account menu">
          <span class="grid h-7 w-7 place-items-center rounded-full bg-brand-100 text-[12px] font-semibold text-brand-700"><?= e($initials) ?></span>
          <?= bf_icon('chevron-down', 'h-3.5 w-3.5 text-stone-400') ?>
        </summary>
        <div class="absolute right-0 mt-1.5 w-56 rounded-xl border border-stone-200 bg-white p-1.5 shadow-lg">
          <div class="px-2.5 py-2">
            <p class="truncate text-[13px] font-semibold"><?= e($user['name']) ?></p>
            <p class="truncate text-xs text-stone-500"><?= e($user['email']) ?> · <?= e(\BlogFlow\Core\Auth::ROLES[$user['role']] ?? $user['role']) ?></p>
          </div>
          <a class="nav-link" href="<?= e(bf_admin_url('profile')) ?>"><?= bf_icon('user') ?> Profile &amp; password</a>
          <form method="post" action="<?= e(bf_admin_url('logout')) ?>">
            <?= bf_csrf_field() ?>
            <button class="nav-link w-full text-left" type="submit"><?= bf_icon('logout') ?> Sign out</button>
          </form>
        </div>
      </details>
    </div>
  </header>

  <main id="admin-main" class="mx-auto max-w-[84rem] px-3 py-5 sm:px-6 sm:py-7" tabindex="-1">
    <?php if ($flash): ?>
      <div class="mb-5 space-y-2" data-flash>
        <?php foreach ($flash as $f): $type = in_array($f['type'], ['success', 'error', 'info', 'warning'], true) ? $f['type'] : 'info'; ?>
          <div class="alert alert-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
            <?= bf_icon($type === 'success' ? 'check' : ($type === 'error' ? 'alert' : 'info'), 'h-4 w-4 mt-0.5 shrink-0') ?>
            <div class="flex-1"><?= e($f['message']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?= $this->section('content') ?>
  </main>
</div>
<div class="pointer-events-none fixed bottom-4 right-4 z-[70] flex w-[min(92vw,22rem)] flex-col gap-2" id="toasts" aria-live="polite"></div>
<template id="tpl-confirm">
  <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
    <div class="modal max-w-md">
      <div class="p-5">
        <h2 id="confirm-title" class="text-base font-semibold" data-title>Are you sure?</h2>
        <p class="mt-1.5 text-sm text-stone-600" data-message></p>
      </div>
      <div class="flex justify-end gap-2 border-t border-stone-100 bg-stone-50 px-5 py-3">
        <button type="button" class="btn btn-secondary" data-cancel>Cancel</button>
        <button type="button" class="btn btn-danger-solid" data-ok>Confirm</button>
      </div>
    </div>
  </div>
</template>
<?= $this->insert('admin/partials/media-modal') ?>
<script src="<?= e(bf_asset('js/admin.js')) ?>" defer></script>
<?= $this->section('scripts') ?>
</body>
</html>
