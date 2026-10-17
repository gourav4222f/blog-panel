// Live preview of theme/typography settings inside an iframe of the public blog.
import { $, $$ } from './util.js';

const COLOR_VARS = {
  color_bg: '--bf-bg', color_surface: '--bf-surface', color_text: '--bf-text', color_muted: '--bf-muted',
  color_accent: '--bf-accent', color_accent_contrast: '--bf-accent-contrast', color_link: '--bf-link', color_border: '--bf-border',
  color_footer_bg: '--bf-footer-bg', color_footer_text: '--bf-footer-text',
};

export function initThemePreview(root) {
  const iframe = $('iframe[data-theme-preview]', root);
  const form = root.closest('form') || document.querySelector('[data-settings-form]');
  if (!iframe || !form) { return; }
  const fontData = JSON.parse(document.getElementById('font-stacks')?.textContent || '{}');
  const fonts = fontData.stacks || {};

  const apply = () => {
    const doc = iframe.contentDocument;
    if (!doc || !doc.documentElement) { return; }
    const rs = doc.documentElement.style;
    const val = (name) => { const el = form.querySelector(`[name="values[${name}]"]`); return el ? el.value : null; };
    Object.entries(COLOR_VARS).forEach(([k, v]) => { const x = val(k); if (x && /^#[0-9a-f]{6}$/i.test(x)) { rs.setProperty(v, x); } });
    const px = (k, v) => { const x = val(k); if (x !== null && x !== '') { rs.setProperty(v, parseInt(x, 10) + 'px'); } };
    px('radius', '--bf-radius'); px('container_width', '--bf-container'); px('article_width', '--bf-article'); px('logo_height', '--bf-logo-h');
    px('base_size', '--bf-base-size'); px('article_size', '--bf-article-size');
    const ratio = (k, v) => { const x = val(k); if (x) { rs.setProperty(v, x === 'original' ? 'auto' : x.replace(':', ' / ')); } };
    ratio('image_ratio', '--bf-card-ratio'); ratio('hero_ratio', '--bf-hero-ratio');
    const plain = { grid_columns: '--bf-cols', line_height: '--bf-line-height', heading_weight: '--bf-heading-weight', heading_scale: '--bf-scale', letter_spacing: '--bf-tracking' };
    Object.entries(plain).forEach(([k, v]) => { const x = val(k); if (x) { rs.setProperty(v, x); } });
    ['font_heading', 'font_body', 'font_ui'].forEach((k) => {
      const x = val(k);
      if (x && fonts[x]) { rs.setProperty({ font_heading: '--bf-font-heading', font_body: '--bf-font-body', font_ui: '--bf-font-ui' }[k], fonts[x]); }
    });
    const body = doc.body;
    if (body) {
      const cls = (prefix, value) => {
        body.className = body.className.split(' ').filter((c) => !c.startsWith(prefix)).join(' ') + ' ' + prefix + value;
      };
      const card = val('card_style'); if (card) { cls('bf-cards-', card); }
      const btn = val('button_style'); if (btn) { cls('bf-btn-', btn); }
      const header = val('header_layout'); if (header) { cls('bf-header-', header); }
    }
    if (fontData.css && doc.head && !doc.getElementById('bf-preview-fonts')) {
      const style = doc.createElement('style');
      style.id = 'bf-preview-fonts';
      style.textContent = fontData.css;
      doc.head.appendChild(style);
    }
  };

  iframe.addEventListener('load', apply);
  form.addEventListener('input', apply);
  form.addEventListener('change', apply);
  $$('[data-preview-width]', root).forEach((b) => b.addEventListener('click', () => {
    iframe.style.width = b.getAttribute('data-preview-width');
    $$('[data-preview-width]', root).forEach((x) => x.classList.toggle('is-active', x === b));
  }));
}
