<?php
/** @var string $kind */
/** @var array $meta */
$this->layout('frontend/layout');
$media = bf_app()->service(\BlogFlow\Services\MediaService::class);
?>
<div class="bf-container">
  <header class="bf-archive-head<?= $kind === 'author' ? ' bf-archive-head--author' : '' ?>">
    <?= $this->insert('frontend/partials/breadcrumbs', ['crumbs' => $meta['breadcrumbs'] ?? []]) ?>
    <div class="bf-archive-head__row">
      <?php if ($kind === 'author' && !empty($photo)): ?>
        <div class="bf-avatar bf-avatar--lg"><?= $media->picture($photo, ['alt' => $heading, 'sizes' => '96px', 'fallback' => 'thumb', 'priority' => true]) ?></div>
      <?php endif; ?>
      <div>
        <p class="bf-eyebrow"><?= e($eyebrow) ?></p>
        <h1 class="bf-archive-head__title"><?= e($heading) ?><?php if ($page > 1): ?> <span class="bf-intro__page">— Page <?= (int) $page ?></span><?php endif; ?></h1>
        <?php if ($description !== '' && $page === 1): ?>
          <div class="bf-archive-head__text"><?= nl2br(e($description)) ?></div>
        <?php endif; ?>
        <?php if ($kind === 'author'): $social = array_filter(\BlogFlow\Models\Authors::social($author)); ?>
          <?php if ($social || $author['website_url']): ?>
            <ul class="bf-inline-links">
              <?php if ($author['website_url']): ?><li><a href="<?= e($author['website_url']) ?>" rel="me noopener" target="_blank">Website</a></li><?php endif; ?>
              <?php foreach ($social as $k => $href): ?><li><a href="<?= e($href) ?>" rel="me noopener noreferrer" target="_blank"><?= e(\BlogFlow\Models\Authors::SOCIAL_FIELDS[$k] ?? ucfirst($k)) ?></a></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php if (!empty($children)): ?>
      <nav class="bf-catnav" aria-label="Subcategories"><ul>
        <?php foreach ($children as $c): ?><li><a class="bf-chip" href="<?= e(bf_url()->category($c['slug'])) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?>
      </ul></nav>
    <?php endif; ?>
  </header>
  <?php if ($posts): ?>
    <h2 class="bf-sr-only">Articles</h2>
    <?= $this->insert('frontend/partials/grid', ['posts' => $posts, 'headingTag' => 'h3', 'priorityFirst' => $page === 1]) ?>
    <?= $this->insert('frontend/partials/pagination', ['page' => $page, 'pages' => $pages, 'pageUrl' => $pageUrl]) ?>
  <?php else: ?>
    <div class="bf-empty"><p>There are no published articles here yet.</p><p><a class="bf-link" href="<?= e(bf_url()->home()) ?>">Browse all articles</a></p></div>
  <?php endif; ?>
</div>
