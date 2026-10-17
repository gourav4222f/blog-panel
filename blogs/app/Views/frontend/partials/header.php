<?php
/** @var array $site */
$media = bf_app()->service(\BlogFlow\Services\MediaService::class);
$layout = $site['layout'];
$logo = $site['logo'];
$logoH = (int) ($site['theme']['logo_height'] ?? 32);
$renderItems = function (array $items, bool $sub = false) use (&$renderItems) {
    foreach ($items as $item) {
        $attrs = $item['new_tab'] ? ' target="_blank" rel="noopener' . ($item['external'] ? ' noreferrer' : '') . '"' : '';
        $current = $item['active'] ? ' aria-current="page"' : '';
        if ($item['children']) {
            echo '<li class="bf-nav__item bf-nav__item--parent">';
            echo '<a class="bf-nav__link" href="' . e($item['url']) . '"' . $attrs . $current . '>' . e($item['label']) . '</a>';
            echo '<button type="button" class="bf-nav__toggle" aria-expanded="false" aria-label="Show ' . e($item['label']) . ' submenu">' . bf_icon('chevron-down', 'bf-icon') . '</button>';
            echo '<ul class="bf-nav__sub">';
            $renderItems($item['children'], true);
            echo '</ul></li>';
        } else {
            echo '<li class="bf-nav__item"><a class="bf-nav__link" href="' . e($item['url']) . '"' . $attrs . $current . '>' . e($item['label']) . '</a></li>';
        }
    }
};
?>
<header class="bf-header" role="banner">
  <div class="bf-container bf-header__inner">
    <a class="bf-brand" href="<?= e(bf_url()->home()) ?>"<?= $logo ? '' : ' aria-label="' . e($site['name']) . ' home"' ?>>
      <?php if ($logo): ?>
        <img class="bf-brand__logo" src="<?= e($media->url($logo, $logo['width'] > 640 ? 'card' : null)) ?>" alt="<?= e($site['name']) ?>" height="<?= $logoH ?>" width="<?= (int) round($logoH * max(1, (int) $logo['width']) / max(1, (int) $logo['height'])) ?>">
      <?php endif; ?>
      <?php if (!$logo || !empty($layout['header_show_name'])): ?>
        <span class="bf-brand__name"><?= e($site['name']) ?></span>
      <?php endif; ?>
    </a>
    <div class="bf-header__actions">
      <?php if ($site['search_enabled']): ?>
        <a class="bf-icon-btn" href="<?= e(bf_url()->search()) ?>" aria-label="Search articles"><?= bf_icon('search', 'bf-icon') ?></a>
      <?php endif; ?>
      <?php if (!empty($layout['header_cta_label']) && !empty($layout['header_cta_url'])): ?>
        <a class="bf-btn bf-btn--small bf-header__cta" href="<?= e($layout['header_cta_url']) ?>"><?= e($layout['header_cta_label']) ?></a>
      <?php endif; ?>
      <?php if ($site['header_menu']): ?>
        <button type="button" class="bf-icon-btn bf-menu-btn" aria-expanded="false" aria-controls="bf-primary-nav" aria-label="Open menu"><?= bf_icon('menu', 'bf-icon') ?></button>
      <?php endif; ?>
    </div>
    <?php if ($site['header_menu']): ?>
      <nav class="bf-nav" id="bf-primary-nav" aria-label="Main">
        <ul class="bf-nav__list"><?php $renderItems($site['header_menu']); ?></ul>
      </nav>
    <?php endif; ?>
  </div>
</header>
