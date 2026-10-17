<?php
$this->layout('admin/layout');
$route = $meta['route'];
$sections = [];
$current = 'Details';
foreach ($fields as $k => $f) {
    $current = $f['section'] ?? $current;
    $sections[$current][$k] = $f;
}
?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
  <div>
    <a class="btn btn-ghost btn-sm -ml-2" href="<?= e(bf_admin_url($route)) ?>"><?= bf_icon('arrow-left', 'h-3.5 w-3.5') ?> <?= e($meta['plural']) ?></a>
    <h1 class="page-title mt-1"><?= $row ? e($row[$meta['name_field']]) : 'New ' . e(strtolower($meta['singular'])) ?></h1>
    <?php if ($row): ?><p class="page-sub"><?= (int) $count ?> published post(s)<?php if ($count > 0): ?> · <a class="text-brand-700 hover:underline" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">View archive</a><?php endif; ?></p><?php endif; ?>
  </div>
</div>
<form method="post" action="<?= e(bf_admin_url($route . ($row ? '/' . $row['id'] : ''))) ?>" class="max-w-3xl space-y-5">
  <?= bf_csrf_field() ?>
  <?php foreach ($sections as $title => $group): ?>
    <section class="card">
      <div class="card-head"><h2 class="card-title"><?= e($title) ?></h2></div>
      <div class="card-pad grid gap-4 sm:grid-cols-2">
        <?php foreach ($group as $k => $f): ?>
          <?= $this->insert('admin/partials/field', ['name' => 'values[' . $k . ']', 'field' => $f, 'value' => $values[$k] ?? '', 'error' => $errors[$k] ?? null]) ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  <div class="flex items-center justify-between gap-3">
    <button class="btn btn-primary" type="submit"><?= $row ? 'Save changes' : 'Create ' . e(strtolower($meta['singular'])) ?></button>
    <?php if ($row): ?>
      <button class="btn btn-danger" type="submit" form="term-delete" data-confirm="Delete “<?= e($row[$meta['name_field']]) ?>”? Posts are kept but lose this <?= e(strtolower($meta['singular'])) ?>."><?= bf_icon('trash') ?> Delete</button>
    <?php endif; ?>
  </div>
</form>
<?php if ($row): ?><form id="term-delete" method="post" action="<?= e(bf_admin_url($route . '/' . $row['id'] . '/delete')) ?>" hidden><?= bf_csrf_field() ?></form><?php endif; ?>
