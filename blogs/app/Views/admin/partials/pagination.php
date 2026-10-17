<?php
/** @var int $page @var int $pages */
if (($pages ?? 1) < 2) {
    return;
}
$query = bf_app()->request->query;
$link = function (int $p) use ($query) {
    $query['page'] = $p;
    return '?' . http_build_query($query);
};
?>
<nav class="flex items-center justify-between gap-3 px-4 py-3 text-sm" aria-label="Pagination">
  <p class="text-stone-500">Page <?= (int) $page ?> of <?= (int) $pages ?><?= isset($total) ? ' · ' . (int) $total . ' items' : '' ?></p>
  <div class="flex gap-1.5">
    <?php if ($page > 1): ?><a class="btn btn-secondary btn-sm" href="<?= e($link($page - 1)) ?>" rel="prev"><?= bf_icon('chevron-left', 'h-3.5 w-3.5') ?> Previous</a><?php endif; ?>
    <?php if ($page < $pages): ?><a class="btn btn-secondary btn-sm" href="<?= e($link($page + 1)) ?>" rel="next">Next <?= bf_icon('chevron-right', 'h-3.5 w-3.5') ?></a><?php endif; ?>
  </div>
</nav>
