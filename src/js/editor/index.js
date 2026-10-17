// BlogFlow CMS post editor built on Tiptap (MIT, self-hosted). Bundled by esbuild.
import { Editor, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Highlight from '@tiptap/extension-highlight';
import TextAlign from '@tiptap/extension-text-align';
import { TableKit } from '@tiptap/extension-table';
import Youtube from '@tiptap/extension-youtube';
import { Placeholder, CharacterCount } from '@tiptap/extensions';
import { icons } from './icons.js';
import { openLinkDialog, openAltDialog, openPromptDialog } from './dialogs.js';
import { initPostForm } from './form.js';

/** Image with caption, stored as <figure data-align data-width><img data-media-id><figcaption>. */
const Figure = Node.create({
  name: 'figure',
  group: 'block',
  content: 'inline*',
  draggable: true,
  isolating: true,
  addAttributes() {
    const img = (el) => (el.tagName === 'IMG' ? el : el.querySelector('img'));
    return {
      src: { default: null, parseHTML: (el) => img(el)?.getAttribute('src') },
      alt: { default: '', parseHTML: (el) => img(el)?.getAttribute('alt') || '' },
      mediaId: { default: null, parseHTML: (el) => img(el)?.getAttribute('data-media-id') || null },
      width: { default: '100', parseHTML: (el) => el.getAttribute('data-width') || '100' },
      align: { default: 'center', parseHTML: (el) => el.getAttribute('data-align') || 'center' },
    };
  },
  parseHTML() {
    return [
      { tag: 'figure', contentElement: 'figcaption' },
      { tag: 'img[src]', getContent: (_node, schema) => schema.nodes.figure.createAndFill().content },
    ];
  },
  renderHTML({ node }) {
    const a = node.attrs;
    return ['figure', { 'data-width': a.width, 'data-align': a.align },
      ['img', mergeAttributes({ src: a.src, alt: a.alt || '' }, a.mediaId ? { 'data-media-id': a.mediaId } : {}, a.alt ? {} : { class: 'no-alt' })],
      ['figcaption', 0]];
  },
  addCommands() {
    return {
      insertFigure: (attrs) => ({ commands }) => commands.insertContent({
        type: this.name,
        attrs,
        content: attrs.caption ? [{ type: 'text', text: attrs.caption }] : [],
      }),
    };
  },
});

function el(tag, attrs = {}, html = '') {
  const e = document.createElement(tag);
  Object.entries(attrs).forEach(([k, v]) => e.setAttribute(k, v));
  if (html) { e.innerHTML = html; }
  return e;
}

function boot() {
  const root = document.getElementById('bf-editor');
  const dataEl = document.getElementById('editor-data');
  if (!root || !dataEl) { return; }
  const data = JSON.parse(dataEl.textContent);
  const BF = window.BlogFlow;
  const mount = root.querySelector('[data-editor]');
  const toolbar = root.querySelector('[data-toolbar]');
  const htmlView = root.querySelector('[data-html-view]');

  const uploadAndInsert = async (files, pos) => {
    if (!data.canUpload) { BF.toast('You do not have permission to upload images.', 'error'); return; }
    const items = await BF.uploadFiles(files, () => {});
    items.forEach((item) => {
      const attrs = { src: item.path, alt: item.alt || '', mediaId: String(item.id), width: '100', align: 'center', caption: item.caption || '' };
      if (typeof pos === 'number') {
        editor.chain().focus().insertContentAt(pos, { type: 'figure', attrs, content: attrs.caption ? [{ type: 'text', text: attrs.caption }] : [] }).run();
      } else {
        editor.chain().focus().insertFigure(attrs).run();
      }
    });
  };

  const editor = new Editor({
    element: mount,
    extensions: [
      StarterKit.configure({
        heading: { levels: [2, 3, 4] },
        link: { openOnClick: false, autolink: true, defaultProtocol: 'https', protocols: ['mailto', 'tel'], HTMLAttributes: { rel: null, target: null } },
      }),
      Highlight,
      TextAlign.configure({ types: ['heading', 'paragraph'], alignments: ['left', 'center', 'right'] }),
      TableKit.configure({ table: { resizable: false, HTMLAttributes: {} } }),
      Youtube.configure({ nocookie: true, controls: true, modestBranding: true, width: 640, height: 360 }),
      Figure,
      Placeholder.configure({
        includeChildren: true,
        placeholder: ({ node }) => {
          if (node.type.name === 'heading') { return 'Heading'; }
          if (node.type.name === 'figure') { return 'Add a caption…'; }
          return 'Start writing… Type your article here. Use the toolbar or Markdown shortcuts like ## for headings.';
        },
      }),
      CharacterCount,
    ],
    content: data.html || '',
    editorProps: {
      attributes: { 'aria-label': 'Article content', 'aria-multiline': 'true', role: 'textbox', spellcheck: 'true' },
      handleDrop(view, event, _slice, moved) {
        const files = Array.from(event.dataTransfer?.files || []).filter((f) => /^image\//.test(f.type));
        if (moved || !files.length) { return false; }
        event.preventDefault();
        const coords = view.posAtCoords({ left: event.clientX, top: event.clientY });
        uploadAndInsert(files, coords ? coords.pos : undefined);
        return true;
      },
      handlePaste(_view, event) {
        const files = Array.from(event.clipboardData?.files || []).filter((f) => /^image\//.test(f.type));
        if (!files.length) { return false; }
        event.preventDefault();
        uploadAndInsert(files);
        return true;
      },
    },
  });

  // ----- Toolbar -----
  const buttons = [];
  const sep = () => toolbar.appendChild(el('span', { class: 'sep', 'aria-hidden': 'true' }));
  const btn = (name, title, run, isActive, isEnabled) => {
    const b = el('button', { type: 'button', title, 'aria-label': title }, icons[name] || name);
    b.addEventListener('mousedown', (e) => e.preventDefault());
    b.addEventListener('click', () => run());
    toolbar.appendChild(b);
    buttons.push({ b, isActive, isEnabled });
    return b;
  };

  const blockSelect = el('select', { 'aria-label': 'Text style', title: 'Text style' });
  [['p', 'Paragraph'], ['2', 'Heading 2'], ['3', 'Heading 3'], ['4', 'Heading 4']].forEach(([v, l]) => blockSelect.appendChild(el('option', { value: v }, l)));
  blockSelect.addEventListener('change', () => {
    const v = blockSelect.value;
    if (v === 'p') { editor.chain().focus().setParagraph().run(); } else { editor.chain().focus().toggleHeading({ level: parseInt(v, 10) }).run(); }
  });
  toolbar.appendChild(blockSelect);
  sep();
  const c = () => editor.chain().focus();
  btn('bold', 'Bold (Ctrl+B)', () => c().toggleBold().run(), () => editor.isActive('bold'));
  btn('italic', 'Italic (Ctrl+I)', () => c().toggleItalic().run(), () => editor.isActive('italic'));
  btn('underline', 'Underline (Ctrl+U)', () => c().toggleUnderline().run(), () => editor.isActive('underline'));
  btn('strike', 'Strikethrough', () => c().toggleStrike().run(), () => editor.isActive('strike'));
  btn('highlight', 'Highlight', () => c().toggleHighlight().run(), () => editor.isActive('highlight'));
  btn('code', 'Inline code', () => c().toggleCode().run(), () => editor.isActive('code'));
  sep();
  btn('link', 'Insert or edit link (Ctrl+K)', () => editLink(), () => editor.isActive('link'));
  btn('unlink', 'Remove link', () => c().extendMarkRange('link').unsetLink().run(), null, () => editor.isActive('link'));
  sep();
  btn('ul', 'Bulleted list', () => c().toggleBulletList().run(), () => editor.isActive('bulletList'));
  btn('ol', 'Numbered list', () => c().toggleOrderedList().run(), () => editor.isActive('orderedList'));
  btn('indent', 'Indent list item (Tab)', () => c().sinkListItem('listItem').run(), null, () => editor.can().sinkListItem('listItem'));
  btn('outdent', 'Outdent list item (Shift+Tab)', () => c().liftListItem('listItem').run(), null, () => editor.can().liftListItem('listItem'));
  btn('quote', 'Quote', () => c().toggleBlockquote().run(), () => editor.isActive('blockquote'));
  btn('codeblock', 'Code block', () => c().toggleCodeBlock().run(), () => editor.isActive('codeBlock'));
  btn('hr', 'Horizontal rule', () => c().setHorizontalRule().run());
  sep();
  btn('alignLeft', 'Align left', () => c().setTextAlign('left').run(), () => editor.isActive({ textAlign: 'left' }));
  btn('alignCenter', 'Align center', () => c().setTextAlign('center').run(), () => editor.isActive({ textAlign: 'center' }));
  btn('alignRight', 'Align right', () => c().setTextAlign('right').run(), () => editor.isActive({ textAlign: 'right' }));
  sep();
  btn('image', 'Insert image', async () => {
    const item = await BF.pickMedia();
    if (item) { c().insertFigure({ src: item.path, alt: item.alt || '', mediaId: String(item.id), width: '100', align: 'center', caption: item.caption || '' }).run(); }
  });
  btn('table', 'Insert table', () => c().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run());
  btn('video', 'Embed YouTube video', async () => {
    const url = await openPromptDialog('Embed a YouTube video', 'YouTube URL', 'https://www.youtube.com/watch?v=…');
    if (!url) { return; }
    if (!editor.commands.setYoutubeVideo({ src: url })) { BF.toast('That is not a valid YouTube URL.', 'error'); }
  });
  sep();
  btn('undo', 'Undo (Ctrl+Z)', () => c().undo().run(), null, () => editor.can().undo());
  btn('redo', 'Redo (Ctrl+Shift+Z)', () => c().redo().run(), null, () => editor.can().redo());
  const htmlBtn = btn('html', 'HTML source', () => toggleHtml(), () => !htmlView.hidden);

  // Contextual image & table controls.
  const ctx = el('div', { class: 'flex basis-full flex-wrap items-center gap-0.5 border-t border-stone-100 pt-1 mt-1', hidden: '' });
  toolbar.appendChild(ctx);
  const ctxBtn = (label, title, run, active) => {
    const b = el('button', { type: 'button', title, 'aria-label': title }, label);
    b.addEventListener('mousedown', (e) => e.preventDefault());
    b.addEventListener('click', run);
    ctx.appendChild(b);
    buttons.push({ b, isActive: active || null });
  };
  const figureCtl = el('span', { class: 'contents' });
  const tableCtl = el('span', { class: 'contents' });
  const setFig = (attrs) => editor.chain().focus().updateAttributes('figure', attrs).run();
  const figBox = el('span', { class: 'mr-1 text-xs font-medium text-stone-500' }, 'Image:');
  ctx.appendChild(figBox);
  ['25', '50', '75', '100'].forEach((w) => ctxBtn(w + '%', 'Width ' + w + '%', () => setFig({ width: w }), () => editor.isActive('figure', { width: w })));
  ctxBtn('Left', 'Float left', () => setFig({ align: 'left' }), () => editor.isActive('figure', { align: 'left' }));
  ctxBtn('Center', 'Centre', () => setFig({ align: 'center' }), () => editor.isActive('figure', { align: 'center' }));
  ctxBtn('Right', 'Float right', () => setFig({ align: 'right' }), () => editor.isActive('figure', { align: 'right' }));
  ctxBtn('Wide', 'Wide (breaks out of the text column on large screens)', () => setFig({ width: '100', align: 'wide' }), () => editor.isActive('figure', { align: 'wide' }));
  ctxBtn('ALT text', 'Edit ALT text', async () => {
    const alt = await openAltDialog(editor.getAttributes('figure').alt || '');
    if (alt !== null) { setFig({ alt }); }
  });
  ctxBtn('Replace', 'Replace image', async () => {
    const item = await BF.pickMedia();
    if (item) { setFig({ src: item.path, mediaId: String(item.id), alt: editor.getAttributes('figure').alt || item.alt || '' }); }
  });
  ctxBtn('Remove', 'Remove image', () => editor.chain().focus().deleteSelection().run());
  const figControls = Array.from(ctx.children);
  const tableLabel = el('span', { class: 'mr-1 text-xs font-medium text-stone-500' }, 'Table:');
  ctx.appendChild(tableLabel);
  ctxBtn('+ Row', 'Add row below', () => c().addRowAfter().run());
  ctxBtn('+ Col', 'Add column right', () => c().addColumnAfter().run());
  ctxBtn('− Row', 'Delete row', () => c().deleteRow().run());
  ctxBtn('− Col', 'Delete column', () => c().deleteColumn().run());
  ctxBtn('Header row', 'Toggle header row', () => c().toggleHeaderRow().run());
  ctxBtn('Merge', 'Merge or split cells', () => c().mergeOrSplit().run());
  ctxBtn('Delete table', 'Delete table', () => c().deleteTable().run());
  const tableControls = Array.from(ctx.children).filter((x) => !figControls.includes(x));
  void figureCtl; void tableCtl;

  const refresh = () => {
    buttons.forEach(({ b, isActive, isEnabled }) => {
      if (isActive) { b.classList.toggle('is-active', !!isActive()); b.setAttribute('aria-pressed', isActive() ? 'true' : 'false'); }
      if (isEnabled) { b.disabled = !isEnabled(); }
    });
    const level = [2, 3, 4].find((l) => editor.isActive('heading', { level: l }));
    blockSelect.value = level ? String(level) : 'p';
    const fig = editor.isActive('figure');
    const tbl = editor.isActive('table');
    figControls.forEach((x) => { x.hidden = !fig; });
    tableControls.forEach((x) => { x.hidden = !tbl; });
    ctx.hidden = !fig && !tbl;
  };
  editor.on('selectionUpdate', refresh);
  editor.on('transaction', refresh);
  refresh();

  async function editLink() {
    const attrs = editor.getAttributes('link');
    const { from, to } = editor.state.selection;
    const result = await openLinkDialog({
      href: attrs.href || '',
      newTab: attrs.target === '_blank',
      rel: attrs.rel || '',
      hasSelection: from !== to || editor.isActive('link'),
      postsApi: data.postsApi,
    });
    if (!result) { editor.commands.focus(); return; }
    if (result.remove) { c().extendMarkRange('link').unsetLink().run(); return; }
    const rel = [];
    if (result.newTab) { rel.push('noopener'); }
    if (result.nofollow) { rel.push('nofollow'); }
    if (result.sponsored) { rel.push('sponsored'); }
    const linkAttrs = { href: result.href, target: result.newTab ? '_blank' : null, rel: rel.length ? rel.join(' ') : null };
    if (from === to && !editor.isActive('link')) {
      c().insertContent({ type: 'text', text: result.text || result.href, marks: [{ type: 'link', attrs: linkAttrs }] }).run();
    } else {
      c().extendMarkRange('link').setLink(linkAttrs).run();
    }
  }

  function toggleHtml() {
    if (htmlView.hidden) {
      htmlView.value = formatHtml(editor.getHTML());
      htmlView.hidden = false;
      mount.hidden = true;
      toolbar.querySelectorAll('button, select').forEach((b) => { if (b !== htmlBtn) { b.disabled = true; } });
      htmlView.focus();
    } else {
      editor.commands.setContent(htmlView.value, { emitUpdate: true });
      htmlView.hidden = true;
      mount.hidden = false;
      toolbar.querySelectorAll('button, select').forEach((b) => { b.disabled = false; });
      refresh();
      editor.commands.focus();
    }
    htmlBtn.classList.toggle('is-active', !htmlView.hidden);
  }

  function formatHtml(html) {
    return html.replace(/<\/(p|h2|h3|h4|ul|ol|li|blockquote|figure|table|tr|pre|div)>/g, '</$1>\n').replace(/<(ul|ol|table|tbody|thead)>/g, '<$1>\n').trim();
  }

  // Keyboard shortcuts beyond Tiptap defaults.
  mount.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); editLink(); }
  });

  initPostForm({ editor, data, htmlView, isHtmlMode: () => !htmlView.hidden, syncFromHtml: () => { if (!htmlView.hidden) { editor.commands.setContent(htmlView.value, { emitUpdate: false }); } } });
}

document.addEventListener('DOMContentLoaded', boot);
