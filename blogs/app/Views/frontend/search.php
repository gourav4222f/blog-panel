<?php
/** @var string $q */
$this->layout('frontend/layout');
?>
<div class="bf-container">
  <header class="bf-archive-head">
    <p class="bf-eyebrow">Search</p>
    <h1 class="bf-archive-head__title"><?= $q === '' ? 'Search articles' : 'Results for “' . e($q) . '”' ?></h1>
    <form class="bf-search" action="<?= e(bf_url()->search()) ?>" method="get" role="search">
      <label class="bf-sr-only" for="bf-search-q">Search articles</label>
      <input class="bf-input" id="bf-search-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search articles…" maxlength="100" autocomplete="off">
      <button class="bf-btn" type="submit">Search</button>
    </form>
    <?php if ($q !== ''): ?>
      <p class="bf-meta" role="status"><?= (int) $total ?> <?= $total === 1 ? 'article' : 'articles' ?> found</p>
    <?php endif; ?>
  </header>
  <?php if ($posts): ?>
    <?= $this->insert('frontend/partials/grid', ['posts' => $posts, 'headingTag' => 'h2']) ?>
    <?= $this->insert('frontend/partials/pagination', ['page' => $page, 'pages' => $pages, 'pageUrl' => $pageUrl]) ?>
  <?php elseif ($q !== ''): ?>
    <div class="bf-empty"><p>No articles matched your search. Try different or fewer words.</p></div>
  <?php endif; ?>
</div>
