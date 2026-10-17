/* Installer: verifies that clean URLs reach the front controller. */
(function () {
  var row = document.getElementById('rewrite-check');
  if (!row) { return; }
  var icon = row.querySelector('[data-icon]');
  var detail = row.querySelector('[data-detail]');
  function set(ok, text) {
    icon.textContent = ok ? '✓' : '!';
    icon.className = 'mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full text-[11px] ' + (ok ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800');
    detail.textContent = text;
  }
  fetch(row.getAttribute('data-url'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
    .then(function (j) { set(!!(j && j.rewrite), 'Working — pretty URLs like /blogs/my-first-post will resolve.'); })
    .catch(function () { set(false, 'Not detected. On Apache enable mod_rewrite and AllowOverride; on Nginx add the try_files rule from DEPLOYMENT.md. You can still continue.'); });
})();
