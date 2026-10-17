<?php
/** @var array $site */
/** @var array $meta */
$media = bf_app()->service(\BlogFlow\Services\MediaService::class);
$private = !empty($preview);
?>
<!doctype html>
<html lang="<?= e($site['lang']) ?>" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>document.documentElement.className=document.documentElement.className.replace('no-js','js');</script>
<?= $this->insert('components/seo-head', ['meta' => $meta, 'site' => $site]) ?>
<?php if ($site['preload_font']): ?>
<link rel="preload" href="<?= e($site['preload_font']) ?>" as="font" type="font/woff2" crossorigin>
<?php endif; ?>
<link rel="stylesheet" href="<?= e(bf_asset('css/front.css')) ?>">
<style><?= $site['css'] /* generated from validated theme settings */ ?></style>
<?php if ($site['favicon']): ?>
<link rel="icon" href="<?= e($media->url($site['favicon'], 'thumb')) ?>" type="<?= e($site['favicon']['mime']) ?>">
<link rel="apple-touch-icon" href="<?= e($media->url($site['favicon'], 'thumb')) ?>">
<?php endif; ?>
<link rel="alternate" type="application/rss+xml" title="<?= e($site['name']) ?>" href="<?= e(bf_url()->feed()) ?>">
<?php if (($site['theme']['dark_mode'] ?? 'off') === 'auto'): ?>
<meta name="color-scheme" content="light dark">
<?php endif; ?>
<?= $this->insert('components/integrations-head', ['i' => $site['integrations'], 'private' => $private]) ?>
</head>
<body class="bf-body <?= e($site['body_class']) ?>">
<?php if (!$private && !empty($site['integrations']['gtm_id'])): ?>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= e($site['integrations']['gtm_id']) ?>" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>
<?php endif; ?>
<a class="bf-skip" href="#main">Skip to content</a>
<?php if ($private): ?>
<div class="bf-preview-bar" role="status">Preview — this page is private and not indexed. <a href="<?= e(bf_admin_url('posts/' . (int) ($post['id'] ?? 0) . '/edit')) ?>">Back to editor</a></div>
<?php endif; ?>
<?= $this->insert('frontend/partials/header', ['site' => $site]) ?>
<main id="main" tabindex="-1">
<?= $this->section('content') ?>
</main>
<?= $this->insert('frontend/partials/footer', ['site' => $site]) ?>
<script src="<?= e(bf_asset('js/front.js')) ?>" defer></script>
<?php if (!$private && !empty($site['integrations']['body_snippet'])): ?>
<?= $site['integrations']['body_snippet'] /* administrator-only raw HTML */ ?>

<?php endif; ?>
</body>
</html>
