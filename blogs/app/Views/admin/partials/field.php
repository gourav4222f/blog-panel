<?php
/**
 * Renders one form field from a schema definition.
 * @var string $name  input name
 * @var array  $field definition (type, label, help, options, min, max)
 * @var mixed  $value
 * @var ?string $error
 */
$type = $field['type'];
$id = 'fld-' . preg_replace('/[^a-z0-9_\-]/i', '-', $name);
$error = $error ?? null;
$invalid = $error ? ' is-invalid' : '';
$describedBy = $error ? $id . '-error' : (!empty($field['help']) ? $id . '-help' : null);
$aria = ($describedBy ? ' aria-describedby="' . e($describedBy) . '"' : '') . ($error ? ' aria-invalid="true"' : '');
$required = !empty($field['required']) ? ' required' : '';
?>
<div class="<?= in_array($type, ['textarea', 'code', 'multicheck', 'media'], true) ? 'sm:col-span-2' : '' ?>">
  <?php if ($type === 'bool'): ?>
    <label class="flex items-start gap-3 cursor-pointer">
      <input type="hidden" name="<?= e($name) ?>" value="0">
      <input class="switch mt-0.5" type="checkbox" role="switch" id="<?= e($id) ?>" name="<?= e($name) ?>" value="1"<?= $value ? ' checked' : '' ?><?= $aria ?>>
      <span>
        <span class="block text-[13px] font-medium text-ink"><?= e($field['label']) ?></span>
        <?php if (!empty($field['help'])): ?><span class="help mt-0.5 block" id="<?= e($id) ?>-help"><?= e($field['help']) ?></span><?php endif; ?>
      </span>
    </label>
  <?php else: ?>
    <label class="label" for="<?= e($id) ?>"><?= e($field['label']) ?><?= $required ? ' <span class="text-red-600" aria-hidden="true">*</span>' : '' ?></label>
    <?php switch ($type):
      case 'textarea': ?>
        <textarea class="textarea<?= $invalid ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" rows="3"<?= isset($field['max']) ? ' maxlength="' . (int) $field['max'] . '"' : '' ?><?= $aria . $required ?>><?= e($value) ?></textarea>
      <?php break; case 'code': ?>
        <textarea class="textarea font-mono text-xs<?= $invalid ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" rows="5" spellcheck="false"<?= $aria ?>><?= e($value) ?></textarea>
      <?php break; case 'select': ?>
        <select class="select<?= $invalid ?>" id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $aria ?>>
          <?php foreach ($field['options'] as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= (string) $k === (string) $value ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      <?php break; case 'timezone': ?>
        <select class="select<?= $invalid ?>" id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $aria ?>>
          <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
            <option value="<?= e($tz) ?>"<?= $tz === $value ? ' selected' : '' ?>><?= e($tz) ?></option>
          <?php endforeach; ?>
        </select>
      <?php break; case 'multicheck': ?>
        <div class="flex flex-wrap gap-x-5 gap-y-2 pt-1">
          <input type="hidden" name="<?= e($name) ?>[]" value="">
          <?php foreach ($field['options'] as $k => $label): ?>
            <label class="flex items-center gap-2 text-sm"><input class="checkbox" type="checkbox" name="<?= e($name) ?>[]" value="<?= e($k) ?>"<?= in_array($k, (array) $value, true) ? ' checked' : '' ?>> <?= e($label) ?></label>
          <?php endforeach; ?>
        </div>
      <?php break; case 'color': ?>
        <div class="flex items-center gap-2" data-color-field>
          <input class="h-9 w-11 shrink-0 cursor-pointer rounded-md border border-stone-300 bg-white p-0.5" type="color" value="<?= e($value) ?>" aria-label="<?= e($field['label']) ?> picker" data-color-picker>
          <input class="input font-mono uppercase<?= $invalid ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" pattern="#[0-9A-Fa-f]{6}" maxlength="7" data-color-text<?= $aria ?>>
        </div>
      <?php break; case 'media': ?>
        <?= $this->insert('admin/partials/media-field', ['name' => $name, 'value' => $value, 'id' => $id]) ?>
      <?php break; case 'password': ?>
        <input class="input<?= $invalid ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" type="password" value="" autocomplete="new-password" placeholder="<?= $value ? '•••••••• (unchanged)' : '' ?>"<?= $aria ?>>
      <?php break; case 'int': ?>
        <input class="input<?= $invalid ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" type="number" value="<?= e($value) ?>"<?= isset($field['min']) ? ' min="' . (int) $field['min'] . '"' : '' ?><?= isset($field['max']) ? ' max="' . (int) $field['max'] . '"' : '' ?><?= $aria . $required ?>>
      <?php break; default: ?>
        <input class="input<?= $invalid ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" type="<?= $type === 'url' ? 'url' : ($type === 'email' ? 'email' : 'text') ?>" value="<?= e($value) ?>"<?= isset($field['max']) ? ' maxlength="' . (int) $field['max'] . '"' : '' ?><?= $aria . $required ?>>
    <?php endswitch; ?>
    <?php if ($error): ?>
      <p class="error-text" id="<?= e($id) ?>-error"><?= e($error) ?></p>
    <?php elseif (!empty($field['help'])): ?>
      <p class="help" id="<?= e($id) ?>-help"><?= e($field['help']) ?></p>
    <?php endif; ?>
  <?php endif; ?>
</div>
