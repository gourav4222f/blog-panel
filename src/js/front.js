/* BlogFlow CMS — progressive enhancements for the public blog (no dependencies). */
(function () {
  'use strict';
  var nav = document.getElementById('bf-primary-nav');
  var menuBtn = document.querySelector('.bf-menu-btn');
  if (nav && menuBtn) {
    menuBtn.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      menuBtn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('is-open')) {
        nav.classList.remove('is-open');
        menuBtn.setAttribute('aria-expanded', 'false');
        menuBtn.focus();
      }
    });
  }
  document.querySelectorAll('.bf-nav__toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var item = btn.closest('.bf-nav__item');
      var open = item.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  // Copy-link share button (hidden without JavaScript or clipboard support).
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    if (!navigator.clipboard) { return; }
    btn.hidden = false;
    btn.addEventListener('click', function () {
      navigator.clipboard.writeText(btn.getAttribute('data-copy')).then(function () {
        var label = btn.querySelector('span');
        var old = label.textContent;
        label.textContent = 'Copied!';
        setTimeout(function () { label.textContent = old; }, 1800);
      });
    });
  });

  // Highlight the current section in the table of contents.
  var toc = document.querySelector('.bf-toc');
  if (toc && 'IntersectionObserver' in window) {
    var links = {};
    toc.querySelectorAll('a[href^="#"]').forEach(function (a) { links[decodeURIComponent(a.getAttribute('href').slice(1))] = a; });
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting && links[entry.target.id]) {
          Object.keys(links).forEach(function (k) { links[k].classList.remove('is-active'); });
          links[entry.target.id].classList.add('is-active');
        }
      });
    }, { rootMargin: '0px 0px -70% 0px' });
    Object.keys(links).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) { observer.observe(el); }
    });
  }
})();
