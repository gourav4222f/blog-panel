<?php
/** @var array $post */
/** @var ?array $image */
/** @var array $tags */
/** @var string $content sanitised + rendered HTML */
/** @var array $toc */
/** @var array $faq */
/** @var array $related */
/** @var array $adjacent */
/** @var array $meta */
/** @var array $site */
$this->layout('frontend/layout');
$url = bf_url();
$media = bf_app()->service(\BlogFlow\Services\MediaService::class);
$g = $site['general'];
$modified = $post['content_modified_at'] ?? null;
$showUpdated = $modified && $post['published_at'] && strtotime($modified) - strtotime($post['published_at']) > 86400;
$heroAlt = trim((string) ($post['featured_image_alt'] ?? '')) ?: (string) ($image['alt_text'] ?? '');
$caption = trim((string) ($post['featured_image_caption'] ?? '')) ?: (string) ($image['caption'] ?? '');
$credit = (string) ($image['credit'] ?? '');
$article = (int) ($site['theme']['article_width'] ?? 740);
$canonicalSelf = $url->post($post['slug']);
?>
<article class="bf-article" aria-labelledby="bf-article-title">
  <header class="bf-article__header bf-measure">
    <?= $this->insert('frontend/partials/breadcrumbs', ['crumbs' => $meta['breadcrumbs'] ?? []]) ?>
    <?php if (!empty($post['category_slug'])): ?>
      <a class="bf-eyebrow" href="<?= e($url->category($post['category_slug'])) ?>"><?= e($post['category_name']) ?></a>
    <?php endif; ?>
    <h1 id="bf-article-title" class="bf-article__title"><?= e($post['title']) ?></h1>
    <?php if (trim((string) $post['excerpt']) !== ''): ?>
      <p class="bf-article__lede"><?= e($post['excerpt']) ?></p>
    <?php endif; ?>
    <div class="bf-byline">
      <?php if (!empty($post['author_slug'])): ?>
        <?php if ($authorPhoto): ?>
          <span class="bf-avatar"><?= $media->picture($authorPhoto, ['alt' => '', 'sizes' => '40px', 'fallback' => 'thumb', 'priority' => true]) ?></span>
        <?php endif; ?>
      <?php endif; ?>
      <div class="bf-byline__text">
        <?php if (!empty($post['author_slug'])): ?>
          <a class="bf-byline__author" href="<?= e($url->author($post['author_slug'])) ?>" rel="author"><?= e($post['author_name']) ?></a>
        <?php endif; ?>
        <p class="bf-meta">
          <time datetime="<?= e(bf_iso($post['published_at'])) ?>"><?= e(bf_date($post['published_at'])) ?></time>
          <?php if ($showUpdated): ?><span aria-hidden="true">·</span> <span>Updated <time datetime="<?= e(bf_iso($modified)) ?>"><?= e(bf_date($modified)) ?></time></span><?php endif; ?>
          <?php if ($g['show_reading_time'] && (int) $post['reading_time'] > 0): ?><span aria-hidden="true">·</span> <span><?= (int) $post['reading_time'] ?> min read</span><?php endif; ?>
        </p>
      </div>
    </div>
  </header>

  <?php if ($image): ?>
    <figure class="bf-hero">
      <div class="bf-hero__frame">
        <?= $media->picture($image, [
            'alt' => $heroAlt,
            'sizes' => '(max-width: 1200px) 100vw, 1200px',
            'priority' => true,
            'fallback' => 'large',
        ]) ?>
      </div>
      <?php if ($caption !== '' || $credit !== ''): ?>
        <figcaption class="bf-measure"><?= e($caption) ?><?php if ($credit !== ''): ?> <span class="bf-credit"><?= e($credit) ?></span><?php endif; ?></figcaption>
      <?php endif; ?>
    </figure>
  <?php endif; ?>

  <div class="bf-measure bf-article__body">
    <?php if ($toc): ?>
      <nav class="bf-toc" aria-labelledby="bf-toc-title">
        <details open>
          <summary id="bf-toc-title">In this article</summary>
          <ol>
            <?php foreach ($toc as $item): ?>
              <li class="bf-toc__l<?= (int) $item['level'] ?>"><a href="#<?= e($item['id']) ?>"><?= e($item['text']) ?></a></li>
            <?php endforeach; ?>
          </ol>
        </details>
      </nav>
    <?php endif; ?>

    <div class="bf-prose">
      <?= $content /* sanitised server-side with HTML Purifier, then rendered */ ?>
    </div>

    <?php if ($faq): ?>
      <section class="bf-faq" aria-labelledby="bf-faq-title">
        <h2 id="bf-faq-title">Frequently asked questions</h2>
        <?php foreach ($faq as $item): ?>
          <details class="bf-faq__item">
            <summary><?= e($item['q']) ?></summary>
            <div class="bf-faq__answer"><?= nl2br(e($item['a'])) ?></div>
          </details>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if ($tags): ?>
      <div class="bf-tags">
        <span class="bf-sr-only">Tags:</span>
        <ul>
          <?php foreach ($tags as $t): ?><li><a class="bf-chip" href="<?= e($url->tag($t['slug'])) ?>">#<?= e($t['name']) ?></a></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($g['show_share'] && !$preview): ?>
      <?= $this->insert('frontend/partials/share', ['url' => $canonicalSelf, 'title' => $post['title'], 'image' => $meta['og']['image']['url'] ?? null]) ?>
    <?php endif; ?>

    <?php if ($g['show_author_bio'] && !empty($post['author_slug']) && trim((string) $post['author_bio']) !== ''): ?>
      <aside class="bf-author" aria-label="About the author">
        <?php if ($authorPhoto): ?><span class="bf-avatar bf-avatar--md"><?= $media->picture($authorPhoto, ['alt' => '', 'sizes' => '64px', 'fallback' => 'thumb']) ?></span><?php endif; ?>
        <div>
          <p class="bf-author__label">Written by</p>
          <p class="bf-author__name"><a href="<?= e($url->author($post['author_slug'])) ?>"><?= e($post['author_name']) ?></a><?php if (!empty($post['author_job_title'])): ?> <span class="bf-author__role"><?= e($post['author_job_title']) ?></span><?php endif; ?></p>
          <p class="bf-author__bio"><?= e(\BlogFlow\Services\HtmlSanitizer::summarize((string) $post['author_bio'], 320)) ?></p>
        </div>
      </aside>
    <?php endif; ?>

    <?php if ($g['cta_enabled'] && ($g['cta_title'] || $g['cta_text'])): ?>
      <aside class="bf-cta" aria-label="<?= e($g['cta_title'] ?: 'Get in touch') ?>">
        <?php if ($g['cta_title']): ?><p class="bf-cta__title"><?= e($g['cta_title']) ?></p><?php endif; ?>
        <?php if ($g['cta_text']): ?><p class="bf-cta__text"><?= e($g['cta_text']) ?></p><?php endif; ?>
        <?php if ($g['cta_label'] && $g['cta_url']): ?><a class="bf-btn" href="<?= e($g['cta_url']) ?>"><?= e($g['cta_label']) ?></a><?php endif; ?>
      </aside>
    <?php elseif ($site['main_url'] && $site['layout']['footer_main_link_label']): ?>
      <p class="bf-backlink"><a class="bf-link" href="<?= e($site['main_url']) ?>"><?= e($site['layout']['footer_main_link_label']) ?> <?= bf_icon('arrow-right', 'bf-icon') ?></a></p>
    <?php endif; ?>
  </div>
</article>

<?php if ($adjacent['prev'] || $adjacent['next']): ?>
  <nav class="bf-container bf-adjacent" aria-label="More articles">
    <?php if ($adjacent['prev']): ?>
      <a class="bf-adjacent__link" href="<?= e($url->post($adjacent['prev']['slug'])) ?>" rel="prev"><span class="bf-meta"><?= bf_icon('arrow-left', 'bf-icon') ?> Previous article</span><span class="bf-adjacent__title"><?= e($adjacent['prev']['title']) ?></span></a>
    <?php else: ?><span></span><?php endif; ?>
    <?php if ($adjacent['next']): ?>
      <a class="bf-adjacent__link bf-adjacent__link--next" href="<?= e($url->post($adjacent['next']['slug'])) ?>" rel="next"><span class="bf-meta">Next article <?= bf_icon('arrow-right', 'bf-icon') ?></span><span class="bf-adjacent__title"><?= e($adjacent['next']['title']) ?></span></a>
    <?php endif; ?>
  </nav>
<?php endif; ?>

<?php if ($related): ?>
  <section class="bf-container bf-section bf-related" aria-labelledby="bf-related-title">
    <h2 id="bf-related-title" class="bf-section__title">Related articles</h2>
    <?= $this->insert('frontend/partials/grid', ['posts' => $related, 'headingTag' => 'h3']) ?>
  </section>
<?php endif; ?>
