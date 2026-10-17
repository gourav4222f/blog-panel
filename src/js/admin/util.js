// Shared admin helpers: CSRF-aware fetch, toasts, confirm dialog.
export const $ = (sel, root = document) => root.querySelector(sel);
export const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

export function csrf() {
  const m = document.querySelector('meta[name="csrf-token"]');
  return m ? m.getAttribute('content') : '';
}

export function adminUrl(path = '') {
  const m = document.querySelector('meta[name="bf-admin-base"]');
  return (m ? m.getAttribute('content') : '/admin/') + path.replace(/^\//, '');
}

export async function fetchJson(url, { method = 'GET', body = null, json = null, signal } = {}) {
  const headers = { Accept: 'application/json', 'X-Requested-With': 'fetch', 'X-CSRF-Token': csrf() };
  let payload = body;
  if (json !== null) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(json);
  }
  const res = await fetch(url, { method, headers, body: payload, credentials: 'same-origin', signal });
  let data = null;
  try { data = await res.json(); } catch (e) { data = null; }
  if (!res.ok || !data || data.ok === false) {
    const err = new Error((data && data.error) || (res.status === 413 ? 'The file is too large for the server.' : 'Request failed (' + res.status + ').'));
    err.status = res.status;
    err.data = data;
    throw err;
  }
  return data;
}

export function toast(message, type = 'info') {
  const box = document.getElementById('toasts');
  if (!box) { return; }
  const el = document.createElement('div');
  el.className = 'toast' + (type === 'error' ? ' !bg-red-700' : type === 'success' ? ' !bg-emerald-700' : '');
  el.setAttribute('role', type === 'error' ? 'alert' : 'status');
  el.textContent = message;
  box.appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; }, type === 'error' ? 6000 : 3200);
  setTimeout(() => el.remove(), type === 'error' ? 6500 : 3600);
}

export function confirmDialog(message, { title = 'Are you sure?', ok = 'Confirm', danger = true } = {}) {
  return new Promise((resolve) => {
    const tpl = document.getElementById('tpl-confirm');
    if (!tpl) { resolve(window.confirm(message)); return; }
    const node = tpl.content.firstElementChild.cloneNode(true);
    node.querySelector('[data-title]').textContent = title;
    node.querySelector('[data-message]').textContent = message;
    const okBtn = node.querySelector('[data-ok]');
    okBtn.textContent = ok;
    if (!danger) { okBtn.className = 'btn btn-primary'; }
    const previous = document.activeElement;
    const close = (val) => { node.remove(); document.removeEventListener('keydown', onKey); if (previous && previous.focus) { previous.focus(); } resolve(val); };
    const onKey = (e) => { if (e.key === 'Escape') { close(false); } };
    node.querySelector('[data-cancel]').addEventListener('click', () => close(false));
    okBtn.addEventListener('click', () => close(true));
    node.addEventListener('click', (e) => { if (e.target === node) { close(false); } });
    document.addEventListener('keydown', onKey);
    document.body.appendChild(node);
    okBtn.focus();
  });
}

export function escapeHtml(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

export function formatBytes(n) {
  if (!n) { return '0 B'; }
  const u = ['B', 'KB', 'MB', 'GB'];
  let i = 0;
  while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
  return (i === 0 ? n : n.toFixed(n < 10 ? 1 : 0)) + ' ' + u[i];
}

export function debounce(fn, ms) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

export async function copyText(text) {
  try {
    await navigator.clipboard.writeText(text);
    toast('Copied to clipboard', 'success');
  } catch (e) {
    window.prompt('Copy this:', text);
  }
}
