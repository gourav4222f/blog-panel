<?php
/** @var int $page */
/** @var int $pages */
/** @var callable $pageUrl */
if ($pages < 2) {
    return;
}
$nums = array_unique(array_filter([1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages], fn ($n) => $n >= 1 && $n <= $pages));
sort($nums);
$window = [];
$prev = 0;
foreach ($nums as $n) {
    if ($n - $prev > 1) {
        $window[] = null;
    }
    $window[] = $n;
    $prev = $n;
}
?>
<nav class="bf-pagination" aria-label="Pagination">
  <?php if ($page > 1): ?>
    <a class="bf-pagination__step" href="<?= e($pageUrl($page - 1)) ?>" rel="prev"><?= bf_icon('arrow-left', 'bf-icon') ?> <span>Newer</span></a>
  <?php else: ?><span></span><?php endif; ?>
  <ol class="bf-pagination__pages">
    <?php foreach ($window as $n): ?>
      <?php if ($n === null): ?>
        <li aria-hidden="true">…</li>
      <?php elseif ($n === $page): ?>
        <li><span aria-current="page"><?= $n ?></span></li>
      <?php else: ?>
        <li><a href="<?= e($pageUrl($n)) ?>" aria-label="Page <?= $n ?>"><?= $n ?></a></li>
      <?php endif; ?>
    <?php endforeach; ?>
  </ol>
  <?php if ($page < $pages): ?>
    <a class="bf-pagination__step" href="<?= e($pageUrl($page + 1)) ?>" rel="next"><span>Older</span> <?= bf_icon('arrow-right', 'bf-icon') ?></a>
  <?php else: ?><span></span><?php endif; ?>
</nav>
