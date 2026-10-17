<?php
$this->layout('install/layout');
$s = $data['site'] ?? [];
$v = fn ($k) => $old[$k] ?? $s[$k] ?? $defaults[$k] ?? '';
?>
<h1 class="page-title">Your site</h1>
<p class="page-sub">All of this can be changed later in the admin.</p>
<form method="post" class="mt-6 grid gap-4 sm:grid-cols-2">
  <input type="hidden" name="_token" value="<?= e($token) ?>">
  <div class="sm:col-span-2"><?= $this->insert('install/field', ['name' => 'blog_url', 'label' => 'Blog URL', 'value' => $v('blog_url'), 'required' => true, 'type' => 'url', 'help' => 'The address of this blog folder. Used for canonical URLs, the sitemap and feeds.']) ?></div>
  <?= $this->insert('install/field', ['name' => 'blog_name', 'label' => 'Blog name', 'value' => $v('blog_name') ?: 'Blog', 'required' => true]) ?>
  <?= $this->insert('install/field', ['name' => 'main_site_name', 'label' => 'Business / main website name', 'value' => $v('main_site_name')]) ?>
  <div class="sm:col-span-2"><?= $this->insert('install/field', ['name' => 'main_site_url', 'label' => 'Main website URL', 'value' => $v('main_site_url'), 'type' => 'url', 'help' => 'Optional. Used for the "Home" menu link, breadcrumbs and organisation schema.']) ?></div>
  <div>
    <label class="label" for="f-timezone">Timezone</label>
    <select class="select" id="f-timezone" name="timezone">
      <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
        <option value="<?= e($tz) ?>"<?= $tz === $v('timezone') ? ' selected' : '' ?>><?= e($tz) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (!empty($errors['timezone'])): ?><p class="error-text"><?= e($errors['timezone']) ?></p><?php endif; ?>
  </div>
  <?= $this->insert('install/field', ['name' => 'language', 'label' => 'Language code', 'value' => $v('language') ?: 'en', 'help' => 'e.g. en, en-GB, hi-IN']) ?>
  <fieldset class="sm:col-span-2">
    <legend class="label">Theme preset</legend>
    <div class="grid gap-3 sm:grid-cols-3">
      <?php foreach (\BlogFlow\Services\ThemeService::PRESETS as $key => $p): $sel = ($v('preset') ?: 'minimal') === $key; ?>
        <label class="flex cursor-pointer flex-col gap-2 rounded-lg border p-3 text-sm has-[:checked]:border-ink has-[:checked]:ring-1 has-[:checked]:ring-ink <?= $sel ? 'border-ink' : 'border-stone-200' ?>">
          <span class="flex gap-1">
            <?php foreach (['color_bg', 'color_text', 'color_accent', 'color_border'] as $c): ?>
              <span class="h-5 w-5 rounded-full border border-stone-200" style="background: <?= e($p['theme'][$c]) ?>"></span>
            <?php endforeach; ?>
          </span>
          <span class="flex items-center gap-2 font-medium"><input type="radio" class="checkbox" name="preset" value="<?= e($key) ?>"<?= $sel ? ' checked' : '' ?>> <?= e($p['label']) ?></span>
          <span class="text-xs text-stone-500"><?= e($p['description']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>
  <div class="sm:col-span-2 flex items-center justify-between pt-2">
    <a class="btn btn-ghost" href="?step=database">Back</a>
    <button class="btn btn-primary" type="submit">Continue</button>
  </div>
</form>
