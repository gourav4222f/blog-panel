<?php
/** @var array $meta */
/** @var array $site */
use BlogFlow\Services\SeoService;

$seo = bf_app()->service(SeoService::class);
$og = $meta['og'] ?? [];
$image = $og['image'] ?? null;
$integrations = $site['integrations'] ?? [];
?>
<title><?= e($meta['title']) ?></title>
<?php if (($meta['description'] ?? '') !== ''): ?>
<meta name="description" content="<?= e($meta['description']) ?>">
<?php endif; ?>
<meta name="robots" content="<?= e($meta['robots']) ?>">
<?php if (!empty($meta['canonical'])): ?>
<link rel="canonical" href="<?= e($meta['canonical']) ?>">
<?php endif; ?>
<?php if (!empty($meta['prev'])): ?><link rel="prev" href="<?= e($meta['prev']) ?>">
<?php endif; ?>
<?php if (!empty($meta['next'])): ?><link rel="next" href="<?= e($meta['next']) ?>">
<?php endif; ?>
<?php if (!empty($integrations['google_verification'])): ?>
<meta name="google-site-verification" content="<?= e($integrations['google_verification']) ?>">
<?php endif; ?>
<?php if (!empty($integrations['bing_verification'])): ?>
<meta name="msvalidate.01" content="<?= e($integrations['bing_verification']) ?>">
<?php endif; ?>
<?php if (!empty($meta['canonical'])): ?>
<meta property="og:site_name" content="<?= e($seo->siteName()) ?>">
<meta property="og:type" content="<?= e($og['type'] ?? 'website') ?>">
<meta property="og:title" content="<?= e($og['title'] ?? $meta['title']) ?>">
<?php if (($og['description'] ?? '') !== ''): ?>
<meta property="og:description" content="<?= e($og['description']) ?>">
<?php endif; ?>
<meta property="og:url" content="<?= e($og['url'] ?? $meta['canonical']) ?>">
<?php if ($locale = $seo->ogLocale()): ?>
<meta property="og:locale" content="<?= e($locale) ?>">
<?php endif; ?>
<?php if ($image): ?>
<meta property="og:image" content="<?= e($image['url']) ?>">
<meta property="og:image:width" content="<?= (int) $image['w'] ?>">
<meta property="og:image:height" content="<?= (int) $image['h'] ?>">
<meta property="og:image:type" content="<?= e($image['mime']) ?>">
<?php if (($image['alt'] ?? '') !== ''): ?>
<meta property="og:image:alt" content="<?= e($image['alt']) ?>">
<?php endif; ?>
<?php endif; ?>
<?php if (!empty($meta['article'])): $a = $meta['article']; ?>
<meta property="article:published_time" content="<?= e($a['published_time']) ?>">
<meta property="article:modified_time" content="<?= e($a['modified_time']) ?>">
<?php if ($a['section'] !== ''): ?><meta property="article:section" content="<?= e($a['section']) ?>">
<?php endif; ?>
<?php foreach ($a['tags'] as $tag): ?><meta property="article:tag" content="<?= e($tag) ?>">
<?php endforeach; ?>
<?php if ($a['author_url'] !== ''): ?><meta property="article:author" content="<?= e($a['author_url']) ?>">
<?php endif; ?>
<?php endif; ?>
<meta name="twitter:card" content="<?= e($image ? $meta['twitter_card'] : 'summary') ?>">
<?php if ($handle = bf_app()->settings->str('seo.twitter_site')): ?>
<meta name="twitter:site" content="@<?= e(ltrim($handle, '@')) ?>">
<?php endif; ?>
<meta name="twitter:title" content="<?= e($og['title'] ?? $meta['title']) ?>">
<?php if (($og['description'] ?? '') !== ''): ?>
<meta name="twitter:description" content="<?= e($og['description']) ?>">
<?php endif; ?>
<?php if ($image): ?>
<meta name="twitter:image" content="<?= e($image['url']) ?>">
<?php if (($image['alt'] ?? '') !== ''): ?><meta name="twitter:image:alt" content="<?= e($image['alt']) ?>">
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php if (!empty($meta['schema'])): ?>
<script type="application/ld+json"><?= SeoService::jsonLd($meta['schema']) ?></script>
<?php endif; ?>
