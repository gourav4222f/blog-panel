<?php
/** @var string $url */
/** @var string $title */
/** @var ?string $image */
$networks = (array) bf_setting('social.share_networks', []);
if (!$networks) {
    return;
}
$u = rawurlencode($url);
$t = rawurlencode($title);
$links = [
    'x' => ['X', 'https://twitter.com/intent/tweet?url=' . $u . '&text=' . $t],
    'facebook' => ['Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . $u],
    'linkedin' => ['LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url=' . $u],
    'whatsapp' => ['WhatsApp', 'https://wa.me/?text=' . rawurlencode($title . ' ' . $url)],
    'pinterest' => ['Pinterest', 'https://pinterest.com/pin/create/button/?url=' . $u . '&description=' . $t . (!empty($image) ? '&media=' . rawurlencode($image) : '')],
    'email' => ['Email', 'mailto:?subject=' . $t . '&body=' . rawurlencode($title . "\n\n" . $url)],
];
?>
<div class="bf-share">
  <span class="bf-share__label">Share</span>
  <ul>
    <?php foreach ($networks as $n): ?>
      <?php if ($n === 'copy'): ?>
        <li><button type="button" class="bf-chip" data-copy="<?= e($url) ?>" hidden><?= bf_icon('copy', 'bf-icon') ?> <span>Copy link</span></button></li>
      <?php elseif (isset($links[$n])): ?>
        <li><a class="bf-chip" href="<?= e($links[$n][1]) ?>" <?= $n === 'email' ? '' : 'target="_blank" rel="noopener noreferrer"' ?>><?= e($links[$n][0]) ?><span class="bf-sr-only"> (opens <?= $n === 'email' ? 'your email app' : 'in a new tab' ?>)</span></a></li>
      <?php endif; ?>
    <?php endforeach; ?>
  </ul>
</div>
