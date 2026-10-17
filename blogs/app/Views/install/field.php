<?php
/** Simple labelled input for installer forms. */
$id = 'f-' . $name;
$value = $old[$name] ?? $value ?? '';
$err = $errors[$name] ?? null;
?>
<div>
  <label class="label" for="<?= e($id) ?>"><?= e($label) ?></label>
  <input class="input<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" type="<?= e($type ?? 'text') ?>" value="<?= ($type ?? '') === 'password' ? '' : e($value) ?>"<?= !empty($required) ? ' required' : '' ?><?= !empty($autocomplete) ? ' autocomplete="' . e($autocomplete) . '"' : '' ?><?= $err ? ' aria-invalid="true" aria-describedby="' . e($id) . '-err"' : '' ?>>
  <?php if ($err): ?><p class="error-text" id="<?= e($id) ?>-err"><?= e($err) ?></p><?php elseif (!empty($help)): ?><p class="help"><?= e($help) ?></p><?php endif; ?>
</div>
