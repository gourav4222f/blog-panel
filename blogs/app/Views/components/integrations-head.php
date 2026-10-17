<?php
/** @var array $i integrations settings */
/** @var bool $private true on previews: never load trackers */
if (!empty($private)) {
    return;
}
?>
<?php if (!empty($i['gtm_id']) && preg_match('/^GTM-[A-Z0-9]{4,12}$/', $i['gtm_id'])): ?>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?= e($i['gtm_id']) ?>');</script>
<?php endif; ?>
<?php if (!empty($i['ga4_id']) && preg_match('/^G-[A-Z0-9]{4,20}$/', $i['ga4_id'])): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($i['ga4_id']) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($i['ga4_id']) ?>');</script>
<?php endif; ?>
<?php if (!empty($i['meta_pixel_id']) && preg_match('/^[0-9]{6,20}$/', $i['meta_pixel_id'])): ?>
<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','<?= e($i['meta_pixel_id']) ?>');fbq('track','PageView');</script>
<?php endif; ?>
<?php if (!empty($i['head_snippet'])): ?>
<?= $i['head_snippet'] /* administrator-only raw HTML, see Settings → Integrations */ ?>

<?php endif; ?>
