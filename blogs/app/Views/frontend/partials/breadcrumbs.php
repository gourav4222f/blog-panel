<?php
/** @var array $crumbs list of [name, url] */
if (count($crumbs ?? []) < 2) {
    return;
}
$last = count($crumbs) - 1;
?>
<nav class="bf-breadcrumbs" aria-label="Breadcrumb">
  <ol>
    <?php foreach ($crumbs as $i => $c): ?>
      <li>
        <?php if ($i === $last): ?>
          <span aria-current="page"><?= e($c['name']) ?></span>
        <?php else: ?>
          <a href="<?= e($c['url']) ?>"><?= e($c['name']) ?></a>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
</nav>
