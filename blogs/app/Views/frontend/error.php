<?php
/** @var int $status */
$this->layout('frontend/layout');
?>
<div class="bf-container">
  <section class="bf-error">
    <p class="bf-eyebrow">Error <?= (int) $status ?></p>
    <h1 class="bf-archive-head__title"><?= e($title) ?></h1>
    <p class="bf-archive-head__text"><?= e($message) ?></p>
    <?php if (!empty($exception)): ?>
      <pre class="bf-debug"><?= e(get_class($exception) . ': ' . $exception->getMessage() . "\n" . $exception->getFile() . ':' . $exception->getLine()) ?></pre>
    <?php endif; ?>
    <div class="bf-error__actions">
      <a class="bf-btn" href="<?= e(bf_url()->home()) ?>">Go to the blog</a>
      <?php if ($site['search_enabled']): ?><a class="bf-btn bf-btn--ghost" href="<?= e(bf_url()->search()) ?>">Search articles</a><?php endif; ?>
    </div>
  </section>
  <?php if (!empty($latest)): ?>
    <section class="bf-section" aria-labelledby="bf-latest">
      <h2 id="bf-latest" class="bf-section__title">Latest articles</h2>
      <?= $this->insert('frontend/partials/grid', ['posts' => $latest, 'headingTag' => 'h3']) ?>
    </section>
  <?php endif; ?>
</div>
