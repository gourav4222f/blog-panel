// Resumable batch runners (bulk image optimisation, link checking).
import { $, fetchJson, toast } from './util.js';

export function initRunner(root) {
  const url = root.getAttribute('data-url');
  const startBtn = $('[data-run-start]', root);
  const stopBtn = $('[data-run-stop]', root);
  const bar = $('[data-run-bar]', root);
  const label = $('[data-run-label]', root);
  const log = $('[data-run-log]', root);
  let running = false;

  const update = (d) => {
    const pct = d.total > 0 ? Math.round((d.done / d.total) * 100) : 100;
    bar.style.width = pct + '%';
    bar.parentElement.setAttribute('aria-valuenow', String(pct));
    label.textContent = d.message || `${d.done} of ${d.total} processed (${pct}%)`;
    (d.log || []).forEach((line) => {
      const li = document.createElement('li');
      li.textContent = line;
      log.prepend(li);
    });
  };

  const step = async () => {
    if (!running) { return; }
    try {
      const d = await fetchJson(url, { method: 'POST', json: { reset: false } });
      update(d);
      if (d.remaining > 0 && running) {
        setTimeout(step, 150);
      } else {
        running = false;
        startBtn.hidden = false;
        stopBtn.hidden = true;
        startBtn.textContent = 'Run again';
        if (d.remaining === 0) { toast('Finished', 'success'); if (root.hasAttribute('data-reload')) { setTimeout(() => window.location.reload(), 600); } }
      }
    } catch (e) {
      running = false;
      startBtn.hidden = false;
      stopBtn.hidden = true;
      startBtn.textContent = 'Resume';
      toast(e.message, 'error');
    }
  };

  startBtn.addEventListener('click', () => {
    running = true;
    startBtn.hidden = true;
    stopBtn.hidden = false;
    label.textContent = 'Working…';
    step();
  });
  stopBtn.addEventListener('click', () => {
    running = false;
    stopBtn.hidden = true;
    startBtn.hidden = false;
    startBtn.textContent = 'Resume';
    label.textContent += ' — paused. Progress is saved; you can resume later.';
  });
}
