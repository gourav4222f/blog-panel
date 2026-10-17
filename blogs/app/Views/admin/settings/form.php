<?php
/** Generic schema-driven settings form. Optional: $preview (bool), $presets (array), $warnings (array). */
$this->layout('admin/layout');
$sections = [];
$current = 'Settings';
foreach ($schema['fields'] as $k => $f) {
    $current = $f['section'] ?? $current;
    if ($f['type'] === 'code' && empty($isAdmin)) {
        continue;
    }
    $sections[$current][$k] = $f;
}
$preview = $preview ?? false;
?>
<?php if ($preview): $this->start('head'); ?>
<script type="application/json" id="font-stacks"><?= json_encode($fontData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?></script>
<?php $this->stop(); endif; ?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
  <div><h1 class="page-title"><?= e($title) ?></h1><p class="page-sub"><?= e($intro) ?></p></div>
  <?php if ($group === 'integrations'): ?>
    <form method="post" action="<?= e(bf_admin_url('settings/test-email')) ?>"><?= bf_csrf_field() ?><button class="btn btn-secondary" type="submit"><?= bf_icon('mail') ?> Send test email</button></form>
  <?php endif; ?>
</div>

<?php foreach ($warnings ?? [] as $w): ?>
  <div class="alert alert-warning mb-3"><?= bf_icon('alert', 'h-4 w-4 mt-0.5 shrink-0') ?><div><?= e($w) ?></div></div>
<?php endforeach; ?>

<?php if (!empty($presets)): ?>
  <section class="card mb-5" aria-labelledby="presets-title">
    <div class="card-head"><h2 id="presets-title" class="card-title">Theme presets</h2><span class="text-xs text-stone-500">Applying a preset changes colours, fonts and shapes only.</span></div>
    <div class="grid gap-3 card-pad sm:grid-cols-3">
      <?php foreach ($presets as $key => $p): ?>
        <form method="post" action="<?= e(bf_admin_url('appearance/preset')) ?>" class="rounded-xl border p-3 <?= ($values['preset'] ?? '') === $key ? 'border-ink ring-1 ring-ink' : 'border-stone-200' ?>" style="background: <?= e($p['theme']['color_bg']) ?>">
          <?= bf_csrf_field() ?><input type="hidden" name="preset" value="<?= e($key) ?>">
          <div class="rounded-lg p-3" style="background: <?= e($p['theme']['color_surface']) ?>; border: 1px solid <?= e($p['theme']['color_border']) ?>; border-radius: <?= (int) $p['theme']['radius'] ?>px">
            <p class="text-[10px] font-semibold uppercase tracking-wider" style="color: <?= e($p['theme']['color_accent']) ?>">Category</p>
            <p class="mt-1 text-base font-semibold leading-snug" style="color: <?= e($p['theme']['color_text']) ?>; font-family: <?= e(\BlogFlow\Services\ThemeService::stack($p['typography']['font_heading'])) ?>"><?= e($p['label']) ?></p>
            <p class="mt-1 text-xs" style="color: <?= e($p['theme']['color_muted']) ?>"><?= e($p['description']) ?></p>
          </div>
          <button class="btn btn-secondary btn-sm mt-3 w-full" type="submit" data-preset-apply><?= ($values['preset'] ?? '') === $key ? 'Re-apply' : 'Apply' ?> <?= e($p['label']) ?></button>
        </form>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<div class="<?= $preview ? 'grid gap-5 2xl:grid-cols-[minmax(0,34rem)_minmax(0,1fr)]' : '' ?>">
  <form method="post" action="<?= e($action) ?>" class="<?= $preview ? '' : 'max-w-3xl' ?> space-y-5" data-settings-form novalidate>
    <?= bf_csrf_field() ?>
    <?php foreach ($sections as $sectionTitle => $fields): ?>
      <section class="card">
        <div class="card-head"><h2 class="card-title"><?= e($sectionTitle) ?></h2></div>
        <div class="card-pad grid gap-4 sm:grid-cols-2">
          <?php foreach ($fields as $k => $f): ?>
            <?php if ($f['type'] === 'select' && $k === 'preset') { echo '<input type="hidden" name="values[preset]" value="' . e($values['preset'] ?? 'minimal') . '">'; continue; } ?>
            <?= $this->insert('admin/partials/field', ['name' => 'values[' . $k . ']', 'field' => $f, 'value' => $values[$k] ?? ($f['default'] ?? ''), 'error' => $errors[$k] ?? null]) ?>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
    <?php if ($group === 'images'): ?>
      <div class="alert alert-info"><?= bf_icon('info', 'h-4 w-4 mt-0.5 shrink-0') ?><div>Image engine: <strong><?= e(strtoupper($caps['driver'])) ?></strong>. WebP output: <strong><?= in_array('webp', $caps['write'], true) ? 'available' : 'not available' ?></strong>. AVIF output: <strong><?= in_array('avif', $caps['write'], true) ? 'available' : 'not available on this server' ?></strong>. Changing sizes or quality marks existing images for <a class="underline" href="<?= e(bf_admin_url('media/optimize')) ?>">bulk optimisation</a>.</div></div>
    <?php endif; ?>
    <?php if ($group === 'integrations'): ?>
      <div class="alert alert-info"><?= bf_icon('info', 'h-4 w-4 mt-0.5 shrink-0') ?><div>Tracking scripts are never loaded on admin pages or private previews. If you use analytics, make sure your privacy policy and any required cookie consent cover them. Integration settings and the SMTP password are excluded from configuration exports.</div></div>
    <?php endif; ?>
    <div class="sticky bottom-0 -mx-1 flex items-center gap-3 border-t border-stone-200 bg-[#F6F6F4]/95 px-1 py-3 backdrop-blur">
      <button class="btn btn-primary" type="submit">Save changes</button>
      <?php if ($preview): ?><span class="text-xs text-stone-500">The preview updates as you edit; visitors see changes after saving.</span><?php endif; ?>
    </div>
  </form>
  <?php if ($preview): ?>
    <aside class="card sticky top-20 hidden self-start overflow-hidden xl:block" data-theme-preview-wrap>
      <div class="flex items-center gap-2 border-b border-stone-200 px-3 py-2">
        <span class="text-xs font-medium text-stone-500">Live preview</span>
        <div class="ml-auto flex gap-1">
          <button type="button" class="btn btn-ghost btn-sm is-active" data-preview-width="100%">Desktop</button>
          <button type="button" class="btn btn-ghost btn-sm" data-preview-width="390px">Mobile</button>
        </div>
      </div>
      <div class="flex justify-center bg-stone-100 p-2">
        <iframe src="<?= e($previewUrl) ?>" title="Blog preview" class="h-[78vh] w-full rounded border border-stone-200 bg-white transition-all" data-theme-preview></iframe>
      </div>
    </aside>
  <?php endif; ?>
</div>
