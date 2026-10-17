// Small accessible dialogs used by the editor (link, ALT text, prompt).
function shell(title, bodyHtml, okLabel = 'Apply') {
  const wrap = document.createElement('div');
  wrap.className = 'modal-backdrop';
  wrap.setAttribute('role', 'dialog');
  wrap.setAttribute('aria-modal', 'true');
  wrap.innerHTML = `<div class="modal max-w-lg"><form class="flex min-h-0 flex-col" novalidate>
    <div class="border-b border-stone-200 px-5 py-3"><h2 class="text-base font-semibold"></h2></div>
    <div class="min-h-0 overflow-y-auto space-y-3 p-5">${bodyHtml}</div>
    <div class="flex items-center gap-2 border-t border-stone-100 bg-stone-50 px-5 py-3">
      <span class="flex-1" data-extra></span>
      <button type="button" class="btn btn-secondary" data-cancel>Cancel</button>
      <button type="submit" class="btn btn-primary">${okLabel}</button>
    </div></form></div>`;
  wrap.querySelector('h2').textContent = title;
  return wrap;
}

function run(wrap, collect, focusSel) {
  return new Promise((resolve) => {
    const prev = document.activeElement;
    const close = (v) => { wrap.remove(); document.removeEventListener('keydown', onKey, true); if (prev && prev.focus) { prev.focus(); } resolve(v); };
    const onKey = (e) => { if (e.key === 'Escape') { e.stopPropagation(); close(null); } };
    wrap.querySelector('[data-cancel]').addEventListener('click', () => close(null));
    wrap.addEventListener('click', (e) => { if (e.target === wrap) { close(null); } });
    wrap.querySelector('form').addEventListener('submit', (e) => { e.preventDefault(); const v = collect(); if (v !== undefined) { close(v); } });
    wrap.closeWith = close;
    document.addEventListener('keydown', onKey, true);
    document.body.appendChild(wrap);
    const f = wrap.querySelector(focusSel);
    if (f) { f.focus(); f.select && f.select(); }
  });
}

const esc = (s) => String(s || '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

export function openLinkDialog({ href, newTab, rel, hasSelection, postsApi }) {
  const wrap = shell('Link', `
    <div><label class="label" for="ld-url">URL</label>
      <input class="input" id="ld-url" type="text" value="${esc(href)}" placeholder="https://… or search your posts below" autocomplete="off">
      <p class="error-text" data-err hidden></p></div>
    ${hasSelection ? '' : '<div><label class="label" for="ld-text">Link text</label><input class="input" id="ld-text" type="text"></div>'}
    <div><label class="label" for="ld-search">Link to a blog post</label>
      <input class="input" id="ld-search" type="search" placeholder="Search posts by title…" autocomplete="off">
      <ul class="mt-1 max-h-44 overflow-y-auto rounded-md border border-stone-200 text-sm" data-results hidden></ul></div>
    <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
      <label class="flex items-center gap-2"><input class="checkbox" type="checkbox" id="ld-tab" ${newTab ? 'checked' : ''}> Open in new tab</label>
      <label class="flex items-center gap-2"><input class="checkbox" type="checkbox" id="ld-nofollow" ${/nofollow/.test(rel) ? 'checked' : ''}> nofollow</label>
      <label class="flex items-center gap-2"><input class="checkbox" type="checkbox" id="ld-sponsored" ${/sponsored/.test(rel) ? 'checked' : ''}> sponsored</label>
    </div>`);
  if (href) {
    const rm = document.createElement('button');
    rm.type = 'button';
    rm.className = 'btn btn-ghost text-red-700';
    rm.textContent = 'Remove link';
    rm.addEventListener('click', () => wrap.closeWith({ remove: true }));
    wrap.querySelector('[data-extra]').appendChild(rm);
  }
  const url = wrap.querySelector('#ld-url');
  const search = wrap.querySelector('#ld-search');
  const results = wrap.querySelector('[data-results]');
  let t;
  search.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(async () => {
      const q = search.value.trim();
      if (!q) { results.hidden = true; return; }
      try {
        const data = await window.BlogFlow.fetchJson(postsApi + '?categories=1&q=' + encodeURIComponent(q));
        results.innerHTML = '';
        data.items.forEach((it) => {
          const li = document.createElement('li');
          const b = document.createElement('button');
          b.type = 'button';
          b.className = 'flex w-full items-center justify-between gap-2 px-3 py-2 text-left hover:bg-stone-50';
          b.innerHTML = `<span class="truncate">${esc(it.title)}</span><span class="badge ${it.status === 'published' ? 'badge-green' : 'badge-gray'}">${esc(it.status)}</span>`;
          b.addEventListener('click', () => {
            url.value = it.url;
            const text = wrap.querySelector('#ld-text');
            if (text && !text.value) { text.value = it.title.replace(/^Category: /, ''); }
            results.hidden = true;
            url.focus();
          });
          li.appendChild(b);
          results.appendChild(li);
        });
        results.hidden = data.items.length === 0;
      } catch (e) { results.hidden = true; }
    }, 200);
  });
  return run(wrap, () => {
    let v = url.value.trim();
    const err = wrap.querySelector('[data-err]');
    if (!v) { err.textContent = 'Enter a URL.'; err.hidden = false; return undefined; }
    if (/^(javascript|data|vbscript):/i.test(v)) { err.textContent = 'This kind of link is not allowed.'; err.hidden = false; return undefined; }
    if (!/^(https?:\/\/|mailto:|tel:|\/|#)/i.test(v)) { v = 'https://' + v; }
    const text = wrap.querySelector('#ld-text');
    return {
      href: v,
      text: text ? text.value.trim() : '',
      newTab: wrap.querySelector('#ld-tab').checked,
      nofollow: wrap.querySelector('#ld-nofollow').checked,
      sponsored: wrap.querySelector('#ld-sponsored').checked,
    };
  }, '#ld-url');
}

export function openAltDialog(current) {
  const wrap = shell('Image ALT text', `
    <div><label class="label" for="alt-text">ALT text</label>
      <textarea class="textarea" id="alt-text" rows="3" maxlength="500">${esc(current)}</textarea>
      <p class="help">Describe what the image shows and why it matters in this article. Leave empty only for purely decorative images.</p></div>`, 'Save');
  return run(wrap, () => wrap.querySelector('#alt-text').value.trim(), '#alt-text');
}

export function openPromptDialog(title, label, placeholder) {
  const wrap = shell(title, `<div><label class="label" for="pd-val">${esc(label)}</label><input class="input" id="pd-val" type="url" placeholder="${esc(placeholder)}"></div>`, 'Insert');
  return run(wrap, () => wrap.querySelector('#pd-val').value.trim() || null, '#pd-val');
}
