// Pagine fatte con l'editor: video che si caricano al clic, animazioni all'ingresso, elementi sticky.
(function () {
  'use strict';
  // Video: il lettore di YouTube o Vimeo si carica solo al clic.
  document.querySelectorAll('.ed-video[data-src]').forEach(function (v) {
    var b = v.querySelector('button');
    if (!b) return;
    b.addEventListener('click', function () {
      var f = document.createElement('iframe');
      f.src = v.dataset.src;
      f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
      f.allowFullscreen = true;
      f.title = this.getAttribute('aria-label') || 'Video';
      v.innerHTML = '';
      v.appendChild(f);
    });
  });
  // Animazioni all'ingresso (scheda Avanzate, Effetti di movimento): l'elemento parte invisibile e si anima quando entra in vista.
  var anima = document.querySelectorAll('.ed-anima');
  if (anima.length && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    if ('IntersectionObserver' in window) {
      var oss = new IntersectionObserver(function (voci) {
        voci.forEach(function (x) {
          if (!x.isIntersecting) return;
          x.target.classList.remove('ed-anima-attesa');
          x.target.classList.add('ed-anima-via');
          oss.unobserve(x.target);
        });
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
      anima.forEach(function (e) {
        var r = e.getBoundingClientRect();
        if (r.top < window.innerHeight * 0.92 && r.bottom > 0) e.classList.add('ed-anima-via'); // già in vista: parte subito
        else { e.classList.add('ed-anima-attesa'); oss.observe(e); }
      });
    } else anima.forEach(function (e) { e.classList.add('ed-anima-via'); });
  }
  // Sticky: la classe ed-sticky-attivo quando l'elemento è incollato (e, se chiesto, dopo N px di scorrimento).
  var sticky = document.querySelectorAll('.ed-sticky');
  if (sticky.length) {
    var aggiorna = function () {
      sticky.forEach(function (e) {
        var dopo = parseInt(e.dataset.edStickyDopo || '0', 10);
        var r = e.getBoundingClientRect();
        var offset = parseFloat(getComputedStyle(e).top) || 0;
        var incollato = e.classList.contains('ed-sticky-alto') ? r.top <= offset + 1 && window.scrollY > 0 : r.bottom >= window.innerHeight - (parseFloat(getComputedStyle(e).bottom) || 0) - 1;
        e.classList.toggle('ed-sticky-incollato', incollato);
        e.classList.toggle('ed-sticky-attivo', dopo > 0 ? window.scrollY >= dopo : incollato);
      });
    };
    window.addEventListener('scroll', aggiorna, { passive: true });
    window.addEventListener('resize', aggiorna);
    aggiorna();
  }
})();
