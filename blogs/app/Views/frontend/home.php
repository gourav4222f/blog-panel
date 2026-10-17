<?php
/** @var array $site */
/** @var ?array $featured */
/** @var array $posts */
$this->layout('frontend/layout');
$g = $site['general'];
?>
<div class="bf-container">
  <section class="bf-intro<?= $page > 1 ? ' bf-intro--compact' : '' ?>" aria-labelledby="bf-page-title">
    <?php if ($site['main_url'] && $site['main_name']): ?>
      <p class="bf-eyebrow bf-eyebrow--muted"><?= e($site['main_name']) ?></p>
    <?php endif; ?>
    <h1 id="bf-page-title" class="bf-intro__title"><?= e($g['intro_title'] ?: $site['name']) ?><?php if ($page > 1): ?> <span class="bf-intro__page">— Page <?= (int) $page ?></span><?php endif; ?></h1>
    <?php if ($page === 1 && $g['intro_text']): ?><p class="bf-intro__text"><?= e($g['intro_text']) ?></p><?php endif; ?>
    <?php if ($site['categories']): ?>
      <nav class="bf-catnav" aria-label="Categories">
        <ul>
          <li><a href="<?= e(bf_url()->home()) ?>" class="bf-chip is-active" aria-current="page">All</a></li>
          <?php foreach ($site['categories'] as $c): ?>
            <li><a class="bf-chip" href="<?= e(bf_url()->category($c['slug'])) ?>"><?= e($c['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </section>

  <?php if ($featured): ?>
    <section class="bf-featured" aria-label="Featured article">
      <?= $this->insert('frontend/partials/card', ['post' => $featured, 'headingTag' => 'h2', 'large' => true, 'priority' => true]) ?>
    </section>
  <?php endif; ?>

  <?php if ($posts): ?>
    <section aria-labelledby="bf-latest-title" class="bf-section">
      <h2 id="bf-latest-title" class="bf-section__title"><?= $page > 1 ? 'More articles' : 'Latest articles' ?></h2>
      <?= $this->insert('frontend/partials/grid', ['posts' => $posts, 'headingTag' => 'h3', 'priorityFirst' => !$featured && $page === 1]) ?>
      <?= $this->insert('frontend/partials/pagination', ['page' => $page, 'pages' => $pages, 'pageUrl' => $pageUrl]) ?>
    </section>
  <?php elseif (!$featured): ?>
    <div class="bf-empty">
      <p>No articles have been published yet. Please check back soon.</p>
    </div>
  <?php endif; ?>
</div>
