// Menu builder with drag-and-drop ordering (SortableJS) and one level of nesting.
import Sortable from 'sortablejs';
import { $, $$, fetchJson, toast, escapeHtml } from './util.js';

export function initMenuBuilder(root) {
  const list = $('[data-menu-list]', root);
  const tpl = document.getElementById('tpl-menu-item');
  const saveUrl = root.getAttribute('data-save-url');
  let counter = 0;

  const syncRow = (row) => {
    const type = $('[data-field=link_type]', row).value;
    $$('[data-show-for]', row).forEach((el) => { el.hidden = !el.getAttribute('data-show-for').split(' ').includes(type); });
    const label = $('[data-field=label]', row).value || 'Untitled link';
    $('[data-summary]', row).textContent = label;
    $('[data-type-label]', row).textContent = $('[data-field=link_type]', row).selectedOptions[0].textContent;
  };

  const bindRow = (row) => {
    row.dataset.key = row.dataset.key || 'n' + (++counter);
    $$('select, input', row).forEach((el) => el.addEventListener('input', () => syncRow(row)));
    $('[data-remove]', row).addEventListener('click', () => { row.remove(); });
    $('[data-indent]', row).addEventListener('click', () => {
      row.classList.toggle('is-child');
      row.classList.toggle('ml-8');
    });
    $('[data-toggle-row]', row).addEventListener('click', () => {
      const body = $('[data-row-body]', row);
      body.hidden = !body.hidden;
    });
    syncRow(row);
  };

  $$('[data-menu-item]', list).forEach(bindRow);
  Sortable.create(list, { handle: '[data-handle]', animation: 150, ghostClass: 'opacity-40' });

  $('[data-add-item]', root).addEventListener('click', () => {
    const row = tpl.content.firstElementChild.cloneNode(true);
    list.appendChild(row);
    bindRow(row);
    $('[data-row-body]', row).hidden = false;
    $('[data-field=label]', row).focus();
  });

  $('[data-save-menu]', root).addEventListener('click', async () => {
    const rows = $$('[data-menu-item]', list);
    let lastTopKey = '';
    const items = rows.map((row, i) => {
      const isChild = row.classList.contains('is-child') && i > 0 && lastTopKey !== '';
      if (!isChild) { lastTopKey = row.dataset.key; }
      const get = (f) => { const el = $(`[data-field=${f}]`, row); return el ? (el.type === 'checkbox' ? el.checked : el.value) : ''; };
      const type = get('link_type');
      const targetField = { category: 'target_category', post: 'target_post', tag: 'target_tag' }[type];
      return {
        key: row.dataset.key,
        parent_key: isChild ? lastTopKey : '',
        label: get('label'),
        link_type: type,
        url: get('url'),
        target_id: targetField ? get(targetField) : '',
        new_tab: get('new_tab'),
        is_visible: get('is_visible'),
      };
    });
    try {
      await fetchJson(saveUrl, { method: 'POST', json: { items } });
      toast('Menu saved', 'success');
    } catch (e) {
      const errs = e.data && e.data.errors ? Object.values(e.data.errors).join(' ') : e.message;
      toast(errs, 'error');
    }
  });
  void escapeHtml;
}
