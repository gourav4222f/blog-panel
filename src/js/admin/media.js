// Media picker modal, uploads and media fields.
import { $, $$, fetchJson, toast, escapeHtml, formatBytes, adminUrl, csrf } from './util.js';

export async function uploadFiles(files, onProgress) {
  const results = [];
  let i = 0;
  for (const file of files) {
    i++;
    if (onProgress) { onProgress(`Uploading ${i} of ${files.length}: ${file.name}`); }
    const fd = new FormData();
    fd.append('file', file);
    try {
      const data = await fetchJson(adminUrl('media/upload'), { method: 'POST', body: fd });
      results.push(data.item);
      toast(`${file.name} uploaded — saved ${data.item.saved_percent}%`, 'success');
    } catch (e) {
      toast(`${file.name}: ${e.message}`, 'error');
    }
  }
  if (onProgress) { onProgress(''); }
  return results;
}

export function bindDropzone(zone, onFiles) {
  const input = zone.querySelector('input[type=file]');
  ['dragenter', 'dragover'].forEach((ev) => zone.addEventListener(ev, (e) => { e.preventDefault(); zone.classList.add('is-over'); }));
  ['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, (e) => { e.preventDefault(); zone.classList.remove('is-over'); }));
  zone.addEventListener('drop', (e) => {
    const files = Array.from(e.dataTransfer.files || []).filter((f) => /^image\//.test(f.type));
    if (files.length) { onFiles(files); }
  });
  if (input) {
    input.addEventListener('change', () => {
      const files = Array.from(input.files || []);
      input.value = '';
      if (files.length) { onFiles(files); }
    });
  }
}

/**
 * Opens the media picker. Resolves with the selected media item or null.
 */
export function pickMedia() {
  return new Promise((resolve) => {
    const tpl = document.getElementById('tpl-media-modal');
    if (!tpl) { resolve(null); return; }
    const modal = tpl.content.firstElementChild.cloneNode(true);
    const grid = $('[data-media-grid]', modal);
    const detail = $('[data-media-detail]', modal);
    const selectBtn = $('[data-media-select]', modal);
    const status = $('[data-media-status]', modal);
    const more = $('[data-media-more]', modal);
    const empty = $('[data-media-empty]', modal);
    const search = $('[data-media-search]', modal);
    let page = 1;
    let selected = null;
    let query = '';
    const previous = document.activeElement;

    const close = (value) => {
      modal.remove();
      document.removeEventListener('keydown', onKey);
      if (previous && previous.focus) { previous.focus(); }
      resolve(value);
    };
    const onKey = (e) => { if (e.key === 'Escape') { close(null); } };

    const renderDetail = (item) => {
      detail.innerHTML = `
        <img src="${escapeHtml(item.medium)}" alt="" class="w-full rounded-lg border border-stone-200 bg-white">
        <dl class="mt-3 space-y-1.5 text-xs">
          <div><dt class="text-stone-500">File</dt><dd class="break-all font-medium">${escapeHtml(item.filename)}</dd></div>
          <div><dt class="text-stone-500">Dimensions</dt><dd>${item.width} × ${item.height} px · ${formatBytes(item.filesize)}</dd></div>
          <div><dt class="text-stone-500">ALT text</dt><dd>${item.alt ? escapeHtml(item.alt) : '<span class="text-amber-700">Missing — add it after inserting or in the media library</span>'}</dd></div>
          ${item.caption ? `<div><dt class="text-stone-500">Caption</dt><dd>${escapeHtml(item.caption)}</dd></div>` : ''}
        </dl>
        <a class="btn btn-secondary btn-sm mt-3" href="${escapeHtml(adminUrl('media/' + item.id))}" target="_blank" rel="noopener">Edit details</a>`;
    };

    const tile = (item) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'group relative aspect-square overflow-hidden rounded-lg border border-stone-200 bg-stone-100 focus:outline-none';
      btn.setAttribute('aria-label', item.title || item.filename);
      btn.innerHTML = `<img src="${escapeHtml(item.thumb)}" alt="" loading="lazy" class="h-full w-full object-cover">${item.alt ? '' : '<span class="absolute left-1 top-1 rounded bg-amber-500 px-1 text-[10px] font-semibold text-white">ALT</span>'}`;
      btn.addEventListener('click', () => {
        $$('[aria-pressed="true"]', grid).forEach((b) => { b.setAttribute('aria-pressed', 'false'); b.classList.remove('ring-2', 'ring-brand-500'); });
        btn.setAttribute('aria-pressed', 'true');
        btn.classList.add('ring-2', 'ring-brand-500');
        selected = item;
        selectBtn.disabled = false;
        status.textContent = item.filename;
        renderDetail(item);
      });
      btn.addEventListener('dblclick', () => close(item));
      return btn;
    };

    const load = async (reset) => {
      if (reset) { page = 1; grid.innerHTML = ''; }
      status.textContent = 'Loading…';
      try {
        const data = await fetchJson(adminUrl('api/media') + '?page=' + page + '&q=' + encodeURIComponent(query));
        data.items.forEach((it) => grid.appendChild(tile(it)));
        empty.hidden = grid.children.length > 0;
        more.hidden = data.page >= data.pages;
        status.textContent = '';
      } catch (e) {
        status.textContent = e.message;
      }
    };

    $$('[data-close]', modal).forEach((b) => b.addEventListener('click', () => close(null)));
    modal.addEventListener('click', (e) => { if (e.target === modal) { close(null); } });
    selectBtn.addEventListener('click', () => close(selected));
    more.addEventListener('click', () => { page++; load(false); });
    let t;
    if (search) {
      search.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => { query = search.value.trim(); load(true); }, 250); });
    }
    const zone = $('[data-dropzone]', modal);
    if (zone) {
      bindDropzone(zone, async (files) => {
        const items = await uploadFiles(files, (msg) => { status.textContent = msg; });
        if (items.length) {
          await load(true);
          const first = grid.querySelector('button');
          if (first) { first.click(); }
        }
      });
    }
    document.addEventListener('keydown', onKey);
    document.body.appendChild(modal);
    (search || selectBtn).focus();
    load(true);
  });
}

export function initMediaFields() {
  $$('[data-media-field]').forEach((field) => {
    const input = $('[data-media-input]', field);
    const preview = $('[data-media-preview]', field);
    const name = $('[data-media-name]', field);
    const clear = $('[data-media-clear]', field);
    $('[data-media-choose]', field).addEventListener('click', async () => {
      const item = await pickMedia();
      if (!item) { return; }
      input.value = item.id;
      preview.innerHTML = `<img src="${escapeHtml(item.thumb)}" alt="" class="h-full w-full object-cover">`;
      name.textContent = `${item.filename} · ${item.width}×${item.height}`;
      clear.hidden = false;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    clear.addEventListener('click', () => {
      input.value = '';
      preview.innerHTML = '';
      name.textContent = 'No image selected';
      clear.hidden = true;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });

  // Media library page upload zone.
  const libZone = document.querySelector('[data-library-dropzone]');
  if (libZone) {
    const status = document.querySelector('[data-library-status]');
    bindDropzone(libZone, async (files) => {
      const items = await uploadFiles(files, (msg) => { if (status) { status.textContent = msg; } });
      if (items.length) { window.location.reload(); }
    });
  }
  void csrf;
}
