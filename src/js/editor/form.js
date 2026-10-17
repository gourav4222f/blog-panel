// Post form behaviour: content sync, autosave (server + local), live SEO analysis,
// slug handling, FAQ repeater, related posts picker, preview and focus mode.
const AUTOSAVE_MS = 30000;

const dotClass = { critical: 'bg-red-500', warning: 'bg-amber-500', suggestion: 'bg-sky-500', passed: 'bg-emerald-500' };

function slugify(s) {
  return s.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/&/g, ' and ')
    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120).replace(/-+$/, '');
}

export function initPostForm({ editor, htmlView, isHtmlMode, syncFromHtml }) {
  const BF = window.BlogFlow;
  const form = document.getElementById('post-form');
  if (!form) { return; }
  const q = (s) => form.querySelector(s);
  const htmlInput = q('#content_html');
  const jsonInput = q('#content_json');
  const idInput = q('input[name="id"]');
  const title = q('#title');
  const slug = q('[data-slug-input]');
  const saveState = document.querySelector('[data-save-state]');
  const autoState = document.querySelector('[data-autosave-state]');
  let postId = parseInt(form.dataset.postId || '0', 10);
  let dirty = false;
  let saving = false;
  let submitting = false;
  const status = form.dataset.status;
  const localKey = () => 'bf-post-' + (postId || 'new');

  const sync = () => {
    if (isHtmlMode()) { syncFromHtml(); }
    htmlInput.value = editor.isEmpty ? '' : editor.getHTML();
    jsonInput.value = JSON.stringify(editor.getJSON());
  };

  const counts = () => {
    const words = editor.storage.characterCount ? editor.storage.characterCount.words() : 0;
    document.querySelector('[data-word-count]').textContent = words;
    document.querySelector('[data-reading-time]').textContent = words ? Math.max(1, Math.ceil(words / 225)) : 0;
  };

  const collect = () => {
    sync();
    const fd = new FormData(form);
    const obj = {};
    fd.forEach((v, k) => {
      if (k === '_token') { return; }
      if (k.endsWith('[]')) { (obj[k.slice(0, -2)] = obj[k.slice(0, -2)] || []).push(v); return; }
      const m = k.match(/^faq\[(\d+)\]\[(q|a)\]$/);
      if (m) { obj.faq = obj.faq || []; obj.faq[m[1]] = obj.faq[m[1]] || {}; obj.faq[m[1]][m[2]] = v; return; }
      obj[k] = v;
    });
    if (obj.faq) { obj.faq = obj.faq.filter(Boolean); }
    obj.id = postId;
    return obj;
  };

  const markDirty = () => {
    dirty = true;
    if (autoState) { autoState.textContent = 'Unsaved changes'; }
    try {
      const data = collect();
      localStorage.setItem(localKey(), JSON.stringify({ at: Date.now(), title: data.title, content_html: data.content_html, excerpt: data.excerpt }));
    } catch (e) { /* storage full or disabled */ }
    scheduleAnalysis();
  };

  editor.on('update', () => { counts(); markDirty(); });
  form.addEventListener('input', (e) => { if (e.target !== htmlView) { markDirty(); } });
  form.addEventListener('change', markDirty);
  htmlView.addEventListener('input', markDirty);
  counts();
  sync();

  // ----- Autosave -----
  const autosave = async () => {
    if (!dirty || saving || submitting) { return null; }
    const data = collect();
    if (!postId && !data.title.trim() && editor.isEmpty) { return null; }
    saving = true;
    if (autoState) { autoState.textContent = 'Saving…'; }
    try {
      const res = await BF.fetchJson(form.dataset.autosaveUrl, { method: 'POST', json: data });
      dirty = false;
      if (res.created) {
        try { localStorage.removeItem(localKey()); } catch (e) { /* ignore */ }
        postId = res.id;
        form.dataset.postId = String(res.id);
        idInput.value = String(res.id);
        form.action = res.update_url;
        history.replaceState(null, '', res.edit_url);
        if (saveState) { saveState.textContent = 'Draft created'; }
      }
      if (autoState) { autoState.textContent = (res.created ? 'Draft saved' : 'Autosaved') + ' at ' + res.saved_at; }
      return res;
    } catch (e) {
      if (autoState) { autoState.textContent = 'Autosave failed — changes are kept in this browser'; }
      if (e.status === 401) { BF.toast(e.message, 'error'); }
      return null;
    } finally {
      saving = false;
    }
  };
  setInterval(autosave, AUTOSAVE_MS);

  // Local recovery banner.
  try {
    const raw = localStorage.getItem(localKey());
    const banner = document.querySelector('[data-local-recovery]');
    if (raw && banner) {
      const saved = JSON.parse(raw);
      const current = editor.isEmpty ? '' : editor.getHTML();
      if (saved.content_html !== current || (saved.title || '') !== title.value) {
        banner.hidden = false;
        banner.querySelector('[data-local-time]').textContent = new Date(saved.at).toLocaleString();
        banner.querySelector('[data-local-restore]').addEventListener('click', () => {
          title.value = saved.title || title.value;
          editor.commands.setContent(saved.content_html || '', { emitUpdate: true });
          if (typeof saved.excerpt === 'string') { q('#excerpt').value = saved.excerpt; }
          banner.hidden = true;
          markDirty();
        });
        banner.querySelector('[data-local-discard]').addEventListener('click', () => { localStorage.removeItem(localKey()); banner.hidden = true; });
      } else {
        localStorage.removeItem(localKey());
      }
    }
  } catch (e) { /* ignore */ }

  window.addEventListener('beforeunload', (e) => {
    if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; }
  });

  form.addEventListener('submit', (e) => {
    sync();
    if (!title.value.trim()) {
      e.preventDefault();
      BF.toast('Add a title before saving.', 'error');
      title.focus();
      return;
    }
    submitting = true;
    try { localStorage.removeItem(localKey()); } catch (err) { /* ignore */ }
  });

  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      const btn = form.querySelector('button[name="action"][value="draft"]') || form.querySelector('[data-publish-btn]');
      if (btn) { form.requestSubmit(btn); }
    }
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'f') { e.preventDefault(); toggleFocus(); }
  });

  // ----- Preview -----
  const previewBtn = form.querySelector('[data-preview]');
  if (previewBtn) {
    previewBtn.addEventListener('click', async () => {
      const win = window.open('about:blank', '_blank');
      dirty = true;
      await autosave();
      if (!postId) {
        if (win) { win.close(); }
        BF.toast('Add a title or some content first.', 'error');
        return;
      }
      const url = form.dataset.previewBase || (document.querySelector('meta[name="bf-admin-base"]').content.replace(/admin\/$/, '') + 'preview/' + postId);
      if (win) { win.location.href = url + '?autosave=1'; } else { window.location.href = url + '?autosave=1'; }
    });
  }

  // ----- Focus mode -----
  const toggleFocus = () => {
    document.body.classList.toggle('bf-focus-mode');
    editor.commands.focus();
  };
  const focusBtn = document.querySelector('[data-focus-toggle]');
  if (focusBtn) { focusBtn.addEventListener('click', toggleFocus); }

  // ----- Slug -----
  let slugTouched = slug.value !== '' && status !== 'draft';
  if (slug.value !== '') { slugTouched = true; }
  const slugStatus = form.querySelector('[data-slug-status]');
  const redirectOpt = form.querySelector('[data-redirect-option]');
  const checkSlug = async () => {
    const value = slug.value.trim() || slugify(title.value);
    if (redirectOpt) { redirectOpt.hidden = !form.dataset.originalSlug || value === form.dataset.originalSlug; }
    if (!value) { slugStatus.textContent = ''; return; }
    try {
      const res = await BF.fetchJson(form.dataset.slugUrl + '?id=' + postId + '&slug=' + encodeURIComponent(value));
      slugStatus.textContent = res.message || '';
      slugStatus.className = 'text-xs ' + (res.status === 'available' ? 'text-emerald-700' : res.status === 'empty' ? '' : 'text-red-700');
    } catch (e) { slugStatus.textContent = ''; }
  };
  let slugTimer;
  slug.addEventListener('input', () => { slugTouched = slug.value !== ''; clearTimeout(slugTimer); slugTimer = setTimeout(checkSlug, 300); });
  slug.addEventListener('blur', () => { if (slug.value) { slug.value = slugify(slug.value); checkSlug(); } });
  title.addEventListener('input', () => {
    if (!slugTouched && status === 'draft' && !form.dataset.originalSlug) {
      slug.placeholder = slugify(title.value) || 'generated-from-title';
    }
    clearTimeout(slugTimer);
    slugTimer = setTimeout(checkSlug, 400);
  });

  // ----- Live SEO analysis -----
  let analysisTimer;
  const scoreEl = document.querySelector('[data-seo-score]');
  function scheduleAnalysis() { clearTimeout(analysisTimer); analysisTimer = setTimeout(analyze, 900); }
  async function analyze() {
    const data = collect();
    if (!data.slug) { data.slug = slugify(data.title || ''); }
    try {
      const res = await BF.fetchJson(form.dataset.analyzeUrl, { method: 'POST', json: data });
      const list = document.querySelector('[data-seo-checks]');
      list.innerHTML = '';
      res.checks.forEach((c) => {
        const li = document.createElement('li');
        li.className = 'seo-check';
        li.innerHTML = `<span class="dot ${dotClass[c.severity]}" title="${c.severity}"></span><span><span class="sr-only">${c.severity}: </span></span>`;
        li.lastElementChild.appendChild(document.createTextNode(c.message));
        list.appendChild(li);
      });
      Object.entries(res.stats).forEach(([k, v]) => { const el = document.querySelector(`[data-stat="${k}"]`); if (el) { el.textContent = v; } });
      if (scoreEl) {
        scoreEl.textContent = res.score;
        scoreEl.className = 'badge tabular-nums ' + (res.score >= 80 ? 'badge-green' : res.score >= 55 ? 'badge-amber' : 'badge-red');
      }
      document.querySelector('[data-serp-title]').textContent = res.serp.title || 'Post title';
      document.querySelector('[data-serp-desc]').textContent = res.serp.description;
      document.querySelector('[data-serp-url]').textContent = res.serp.url;
    } catch (e) { /* analysis is best-effort */ }
  }
  if (scoreEl) {
    const n = parseInt(scoreEl.textContent, 10);
    scoreEl.className = 'badge tabular-nums ' + (n >= 80 ? 'badge-green' : n >= 55 ? 'badge-amber' : 'badge-red');
  }

  // ----- FAQ repeater -----
  const faqList = form.querySelector('[data-faq-list]');
  const faqTpl = document.getElementById('tpl-faq');
  const faqEmpty = form.querySelector('[data-faq-empty]');
  const renumber = () => {
    const items = faqList.querySelectorAll('[data-faq-item]');
    items.forEach((item, i) => {
      item.querySelectorAll('input, textarea').forEach((f) => {
        const part = f.dataset.name || (f.name.match(/\[(q|a)\]$/) || [])[1];
        f.dataset.name = part;
        f.name = `faq[${i}][${part}]`;
      });
    });
    faqEmpty.hidden = items.length > 0;
  };
  form.querySelector('[data-faq-add]').addEventListener('click', () => {
    const node = faqTpl.content.firstElementChild.cloneNode(true);
    faqList.insertBefore(node, faqEmpty);
    renumber();
    node.querySelector('input').focus();
    markDirty();
  });
  faqList.addEventListener('click', (e) => {
    const rm = e.target.closest('[data-faq-remove]');
    if (rm) { rm.closest('[data-faq-item]').remove(); renumber(); markDirty(); }
  });
  renumber();

  // ----- Related posts -----
  const rel = form.querySelector('[data-related]');
  if (rel) {
    const search = rel.querySelector('[data-related-search]');
    const results = rel.querySelector('[data-related-results]');
    const list = rel.querySelector('[data-related-list]');
    let t;
    const existing = () => Array.from(list.querySelectorAll('input')).map((i) => i.value);
    search.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(async () => {
        const term = search.value.trim();
        if (!term) { results.hidden = true; return; }
        try {
          const api = JSON.parse(document.getElementById('editor-data').textContent).postsApi;
          const data = await BF.fetchJson(api + '?exclude=' + postId + '&q=' + encodeURIComponent(term));
          results.innerHTML = '';
          data.items.filter((it) => !existing().includes(String(it.id))).forEach((it) => {
            const li = document.createElement('li');
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'block w-full truncate px-2.5 py-1.5 text-left hover:bg-stone-50';
            b.textContent = it.title + (it.status !== 'published' ? ' (' + it.status + ')' : '');
            b.addEventListener('click', () => {
              if (list.children.length >= 6) { BF.toast('Up to 6 related posts.', 'error'); return; }
              const item = document.createElement('li');
              item.className = 'flex items-center gap-2 rounded-md bg-stone-50 px-2 py-1.5 text-xs';
              item.setAttribute('data-related-item', '');
              item.innerHTML = `<input type="hidden" name="related_ids[]" value="${it.id}"><span class="min-w-0 flex-1 truncate"></span><button type="button" class="icon-btn h-6 w-6" data-related-remove aria-label="Remove">×</button>`;
              item.querySelector('span').textContent = it.title;
              list.appendChild(item);
              results.hidden = true;
              search.value = '';
              markDirty();
            });
            li.appendChild(b);
            results.appendChild(li);
          });
          results.hidden = results.children.length === 0;
        } catch (e) { results.hidden = true; }
      }, 250);
    });
    list.addEventListener('click', (e) => {
      const rm = e.target.closest('[data-related-remove]');
      if (rm) { rm.closest('[data-related-item]').remove(); markDirty(); }
    });
  }

  // Changes to the featured image are relevant to analysis.
  form.querySelectorAll('[data-media-input]').forEach((i) => i.addEventListener('change', markDirty));
  dirty = false;
  if (autoState) { autoState.textContent = 'Autosave on'; }
}
