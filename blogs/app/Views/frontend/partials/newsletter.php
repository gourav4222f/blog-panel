<?php
/** @var array $n integration settings */
$hidden = [];
foreach (preg_split('/\r?\n/', (string) $n['newsletter_hidden']) ?: [] as $line) {
    if (str_contains($line, '=')) {
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if (preg_match('/^[A-Za-z0-9_\-\[\]]{1,60}$/', $k)) {
            $hidden[$k] = $v;
        }
    }
}
?>
<section class="bf-newsletter" aria-labelledby="bf-newsletter-title">
  <div class="bf-container bf-newsletter__inner">
    <div>
      <h2 id="bf-newsletter-title" class="bf-newsletter__title"><?= e($n['newsletter_title']) ?></h2>
      <?php if ($n['newsletter_text']): ?><p class="bf-newsletter__text"><?= e($n['newsletter_text']) ?></p><?php endif; ?>
    </div>
    <form class="bf-newsletter__form" action="<?= e($n['newsletter_action']) ?>" method="post" target="_blank" rel="noopener">
      <?php foreach ($hidden as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
      <label class="bf-sr-only" for="bf-newsletter-email">Email address</label>
      <input class="bf-input" id="bf-newsletter-email" type="email" name="<?= e($n['newsletter_field'] ?: 'email') ?>" placeholder="you@example.com" autocomplete="email" required>
      <button class="bf-btn" type="submit"><?= e($n['newsletter_button'] ?: 'Subscribe') ?></button>
    </form>
  </div>
</section>
