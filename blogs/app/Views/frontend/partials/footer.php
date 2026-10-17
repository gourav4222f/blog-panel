<?php
/** @var array $site */
$l = $site['layout'];
$media = bf_app()->service(\BlogFlow\Services\MediaService::class);
$copyright = strtr((string) $l['footer_copyright'], ['{year}' => gmdate('Y'), '{site_name}' => $site['main_name'] ?: $site['name']]);
$socialLabels = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'pinterest' => 'Pinterest', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp'];
$social = array_filter(array_intersect_key($site['social'], $socialLabels));
$hasContact = $l['footer_address'] || $l['footer_phone'] || $l['footer_email'];
$newsletter = $site['integrations'];
?>
<?php if (!empty($newsletter['newsletter_enabled']) && !empty($newsletter['newsletter_action'])): ?>
  <?= $this->insert('frontend/partials/newsletter', ['n' => $newsletter]) ?>
<?php endif; ?>
<footer class="bf-footer bf-footer--<?= e($l['footer_layout']) ?>" role="contentinfo">
  <div class="bf-container">
    <?php if ($l['footer_layout'] !== 'simple'): ?>
    <div class="bf-footer__grid">
      <div class="bf-footer__brand">
        <a class="bf-brand" href="<?= e($site['main_url'] ?: bf_url()->home()) ?>">
          <?php if ($site['footer_logo']): ?>
            <img class="bf-brand__logo" src="<?= e($media->url($site['footer_logo'], $site['footer_logo']['width'] > 640 ? 'card' : null)) ?>" alt="<?= e($site['main_name'] ?: $site['name']) ?>" height="<?= (int) $site['theme']['logo_height'] ?>" width="<?= (int) round((int) $site['theme']['logo_height'] * max(1, (int) $site['footer_logo']['width']) / max(1, (int) $site['footer_logo']['height'])) ?>" loading="lazy">
          <?php else: ?>
            <span class="bf-brand__name"><?= e($site['main_name'] ?: $site['name']) ?></span>
          <?php endif; ?>
        </a>
        <?php if ($l['footer_description']): ?><p class="bf-footer__text"><?= nl2br(e($l['footer_description'])) ?></p><?php endif; ?>
      </div>
      <?php if ($site['footer_menu']): ?>
      <nav class="bf-footer__col" aria-label="Footer">
        <h2 class="bf-footer__heading">Explore</h2>
        <ul class="bf-footer__links">
          <?php foreach ($site['footer_menu'] as $item): ?>
            <li><a href="<?= e($item['url']) ?>"<?= $item['new_tab'] ? ' target="_blank" rel="noopener' . ($item['external'] ? ' noreferrer' : '') . '"' : '' ?>><?= e($item['label']) ?></a></li>
            <?php foreach ($item['children'] as $child): ?>
              <li><a href="<?= e($child['url']) ?>"<?= $child['new_tab'] ? ' target="_blank" rel="noopener' . ($child['external'] ? ' noreferrer' : '') . '"' : '' ?>><?= e($child['label']) ?></a></li>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </ul>
      </nav>
      <?php endif; ?>
      <?php if ($hasContact): ?>
      <div class="bf-footer__col">
        <h2 class="bf-footer__heading">Contact</h2>
        <address class="bf-footer__contact">
          <?php if ($l['footer_address']): ?><p><?= nl2br(e($l['footer_address'])) ?></p><?php endif; ?>
          <?php if ($l['footer_phone']): ?><p><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $l['footer_phone'])) ?>"><?= e($l['footer_phone']) ?></a></p><?php endif; ?>
          <?php if ($l['footer_email']): ?><p><a href="mailto:<?= e($l['footer_email']) ?>"><?= e($l['footer_email']) ?></a></p><?php endif; ?>
        </address>
      </div>
      <?php endif; ?>
      <?php if ($social && $l['footer_show_social']): ?>
      <div class="bf-footer__col">
        <h2 class="bf-footer__heading">Follow</h2>
        <ul class="bf-footer__links">
          <?php foreach ($social as $key => $href): ?>
            <li><a href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer me"><?= e($socialLabels[$key]) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="bf-footer__bottom">
      <p><?= e($copyright) ?></p>
      <ul class="bf-footer__legal">
        <?php if ($l['footer_layout'] === 'simple'): ?>
          <?php foreach ($site['footer_menu'] as $item): ?>
            <li><a href="<?= e($item['url']) ?>"<?= $item['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= e($item['label']) ?></a></li>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($l['footer_privacy_url']): ?><li><a href="<?= e($l['footer_privacy_url']) ?>">Privacy policy</a></li><?php endif; ?>
        <?php if ($l['footer_terms_url']): ?><li><a href="<?= e($l['footer_terms_url']) ?>">Terms</a></li><?php endif; ?>
        <li><a href="<?= e(bf_url()->feed()) ?>">RSS</a></li>
        <?php if ($site['main_url'] && $l['footer_main_link_label']): ?><li><a href="<?= e($site['main_url']) ?>"><?= e($l['footer_main_link_label']) ?></a></li><?php endif; ?>
      </ul>
    </div>
  </div>
</footer>
