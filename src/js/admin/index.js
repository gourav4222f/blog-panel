// BlogFlow CMS admin entry point.
import { $, $$, toast, confirmDialog, fetchJson, copyText } from './util.js';
import { pickMedia, initMediaFields, uploadFiles } from './media.js';
import { initRunner } from './runners.js';
import { initMenuBuilder } from './menus.js';
import { initThemePreview } from './theme.js';

window.BlogFlow = { toast, confirmDialog, fetchJson, pickMedia, uploadFiles, copyText };

function initSidebar() {
  const sidebar = $('[data-sidebar]');
  const backdrop = $('[data-sidebar-backdrop]');
  if (!sidebar) { return; }
  const open = () => { sidebar.classList.remove('-translate-x-full'); backdrop.hidden = false; };
  const close = () => { sidebar.classList.add('-translate-x-full'); backdrop.hidden = true; };
  $$('[data-sidebar-open]').forEach((b) => b.addEventListener('click', open));
  $$('[data-sidebar-close]').forEach((b) => b.addEventListener('click', close));
  if (backdrop) { backdrop.addEventListener('click', close); }
}

function initDropdowns() {
  document.addEventListener('click', (e) => {
    $$('details[data-dropdown][open]').forEach((d) => { if (!d.contains(e.target)) { d.removeAttribute('open'); } });
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { $$('details[data-dropdown][open]').forEach((d) => d.removeAttribute('open')); }
  });
}

function initConfirm() {
  document.addEventListener('click', async (e) => {
    const el = e.target.closest('[data-confirm]');
    if (!el || el.dataset.confirmed === '1') { return; }
    e.preventDefault();
    const ok = await confirmDialog(el.getAttribute('data-confirm'));
    if (!ok) { return; }
    el.dataset.confirmed = '1';
    const formId = el.getAttribute('form');
    const form = formId ? document.getElementById(formId) : el.closest('form');
    if (el.tagName === 'A') { window.location.href = el.href; return; }
    if (form) {
      if (form.requestSubmit) { form.requestSubmit(el.type === 'submit' && !formId ? el : undefined); } else { form.submit(); }
    }
    setTimeout(() => { delete el.dataset.confirmed; }, 500);
  });
}

function initBulk() {
  const form = $('[data-bulk-form]');
  if (!form) { return; }
  const all = $('[data-check-all]', form);
  const count = $('[data-bulk-count]', form);
  const boxes = () => $$('[data-check]', form);
  const update = () => {
    const n = boxes().filter((b) => b.checked).length;
    count.textContent = n ? n + ' selected' : '';
    if (all) { all.checked = n > 0 && n === boxes().length; all.indeterminate = n > 0 && n < boxes().length; }
  };
  if (all) { all.addEventListener('change', () => { boxes().forEach((b) => { b.checked = all.checked; }); update(); }); }
  form.addEventListener('change', (e) => { if (e.target.matches('[data-check]')) { update(); } });
}

function initColors() {
  $$('[data-color-field]').forEach((f) => {
    const picker = $('[data-color-picker]', f);
    const text = $('[data-color-text]', f);
    picker.addEventListener('input', () => { text.value = picker.value.toUpperCase(); text.dispatchEvent(new Event('input', { bubbles: true })); });
    text.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(text.value)) { picker.value = text.value; } });
  });
}

function initTabs() {
  $$('[data-tab]').forEach((tab) => {
    tab.addEventListener('click', () => {
      const scope = tab.closest('section, .card, form') || document;
      $$('[data-tab]', scope).forEach((t) => { const on = t === tab; t.classList.toggle('is-active', on); t.setAttribute('aria-selected', on ? 'true' : 'false'); });
      $$('[data-tab-panel]', scope).forEach((p) => { p.hidden = p.getAttribute('data-tab-panel') !== tab.getAttribute('data-tab'); });
    });
  });
}

export function initCounters(root = document) {
  $$('[data-count]', root).forEach((el) => {
    const target = parseInt(el.getAttribute('data-count'), 10);
    const counter = el.parentElement.querySelector('[data-counter]');
    if (!counter) { return; }
    const update = () => {
      const n = el.value.length;
      counter.textContent = n ? `${n} characters (about ${target} display without truncation)` : '';
      counter.className = n > target * 1.1 ? 'text-amber-700' : '';
    };
    el.addEventListener('input', update);
    update();
  });
}

function initAutogrow() {
  $$('[data-autogrow]').forEach((el) => {
    const fit = () => { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; };
    el.addEventListener('input', fit);
    fit();
  });
}

function initCopy() {
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-copy-text]');
    if (b) { e.preventDefault(); copyText(b.getAttribute('data-copy-text')); }
  });
}

function initHotkeys() {
  document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName) && !document.activeElement.isContentEditable) {
      const s = $('[data-hotkey="/"]');
      if (s) { e.preventDefault(); s.focus(); }
    }
  });
}

function initPresetButtons() {
  $$('[data-preset-apply]').forEach((b) => b.addEventListener('click', async (e) => {
    e.preventDefault();
    const ok = await confirmDialog('Apply this preset? Colours, fonts and shapes will be replaced. Your logo, content and menus are not changed.', { ok: 'Apply preset', danger: false });
    if (ok) { b.closest('form').submit(); }
  }));
}

document.addEventListener('DOMContentLoaded', () => {
  initSidebar();
  initDropdowns();
  initConfirm();
  initBulk();
  initColors();
  initTabs();
  initCounters();
  initAutogrow();
  initCopy();
  initHotkeys();
  initMediaFields();
  initPresetButtons();
  $$('[data-runner]').forEach(initRunner);
  const menu = $('[data-menu-builder]');
  if (menu) { initMenuBuilder(menu); }
  const preview = $('[data-theme-preview-wrap]');
  if (preview) { initThemePreview(preview); }
});
