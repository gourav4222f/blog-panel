<?php
/** @var array $post card row from Posts::publicList */
/** @var string $headingTag */
/** @var bool $large */
$url = bf_url();
$media = bf_app()->service(\BlogFlow\Services\MediaService::class);
$general = bf_app()->settings->group('general');
$tag = $headingTag ?? 'h2';
$large = $large ?? false;
$cols = max(2, (int) bf_setting('theme.grid_columns', 3));
$href = $url->post($post['slug']);
$excerpt = trim((string) $post['excerpt']) ?: \BlogFlow\Services\HtmlSanitizer::summarize((string) ($post['text_preview'] ?? ''), 150);
?>
<article class="bf-card<?= $large ? ' bf-card--featured' : '' ?>">
  <a class="bf-card__media" href="<?= e($href) ?>" tabindex="-1" aria-hidden="true">
    <?php if ($post['image']): ?>
      <?= $media->picture($post['image'], [
          'alt' => '',
          'sizes' => $large ? '(max-width: 900px) 100vw, 60vw' : '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, ' . (int) round(100 / $cols) . 'vw',
          'priority' => !empty($priority),
          'fallback' => $large ? 'medium' : 'card',
      ]) ?>
    <?php else: ?>
      <span class="bf-card__placeholder"><?= e(mb_substr($post['title'], 0, 1)) ?></span>
    <?php endif; ?>
  </a>
  <div class="bf-card__body">
    <?php if ($post['category_slug']): ?>
      <a class="bf-eyebrow" href="<?= e($url->category($post['category_slug'])) ?>"><?= e($post['category_name']) ?></a>
    <?php endif; ?>
    <<?= $tag ?> class="bf-card__title"><a href="<?= e($href) ?>"><?= e($post['title']) ?></a></<?= $tag ?>>
    <?php if ($excerpt !== ''): ?><p class="bf-card__excerpt"><?= e($excerpt) ?></p><?php endif; ?>
    <p class="bf-meta">
      <time datetime="<?= e(bf_iso($post['published_at'])) ?>"><?= e(bf_date($post['published_at'])) ?></time>
      <?php if ($general['show_author_on_cards'] && $post['author_name']): ?><span aria-hidden="true">·</span> <span><?= e($post['author_name']) ?></span><?php endif; ?>
      <?php if ($general['show_reading_time'] && (int) $post['reading_time'] > 0): ?><span aria-hidden="true">·</span> <span><?= (int) $post['reading_time'] ?> min read</span><?php endif; ?>
    </p>
  </div>
</article>
