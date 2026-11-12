/* Apertura (2.17.0): il video che parte al clic e la maniglia del prima e dopo. Si carica solo dove un'Apertura ha
   l'attributo data-ed-hero-js; nell'anteprima dell'editor un osservatore riprende le Aperture ridisegnate. Nessuna libreria. */
(function () {
  'use strict';

  // Video: al clic sul play, un <video> del sito prende il posto della foto e parte.
  function video(s) {
    var b = s.querySelector('.ed-hvi-play'), u = s.getAttribute('data-video');
    if (!b || !u) return;
    b.addEventListener('click', function () {
      var v = document.createElement('video');
      v.className = 'ed-hvi-video';
      v.src = u; v.controls = true; v.autoplay = true; v.playsInline = true; v.setAttribute('playsinline', '');
      v.setAttribute('aria-label', b.getAttribute('aria-label') || 'Video');
      var f = s.querySelector('.finestra');
      if (f) f.parentNode.replaceChild(v, f); else s.insertBefore(v, s.firstChild);
      b.parentNode.removeChild(b);
      var p = v.play(); if (p && p.catch) p.catch(function () {});
      v.focus();
    });
  }

  // Prima e dopo: l'input range (frecce, mouse) e, per il dito su tutti i browser, il trascinamento sulla finestra.
  function primadopo(s) {
    var r = s.querySelector('.ed-hpd-cursore');
    if (!r) return;
    function metti(n) {
      n = Math.max(0, Math.min(100, Math.round(n)));
      r.value = n;
      s.style.setProperty('--pos', n + '%');
      r.setAttribute('aria-valuetext', n + '% prima, ' + (100 - n) + '% dopo');
    }
    r.addEventListener('input', function () { metti(Number(r.value)); });
    var tira = false;
    function da(e) { var b = s.getBoundingClientRect(); if (b.width) metti((e.clientX - b.left) / b.width * 100); }
    s.addEventListener('pointerdown', function (e) {
      if (e.button !== 0) return;
      tira = true; da(e);
      try { s.setPointerCapture(e.pointerId); } catch (x) { /* niente */ }
    });
    s.addEventListener('pointermove', function (e) { if (tira) da(e); });
    s.addEventListener('pointerup', function () { tira = false; });
    s.addEventListener('pointercancel', function () { tira = false; });
    metti(Number(r.value) || 50);
  }

  function avvia(radice) {
    Array.prototype.forEach.call((radice || document).querySelectorAll('[data-ed-hero-js]'), function (s) {
      if (s.getAttribute('data-ed-hero-ok')) return;
      s.setAttribute('data-ed-hero-ok', '1');
      var t = s.getAttribute('data-ed-hero-js');
      if (t === 'video') video(s);
      else if (t === 'primadopo') primadopo(s);
    });
  }

  avvia();
  var tela = document.getElementById('ed-pagina');
  if (tela && 'MutationObserver' in window) {
    var t;
    new MutationObserver(function () { clearTimeout(t); t = setTimeout(function () { avvia(tela); }, 60); }).observe(tela, { childList: true, subtree: true });
  }
})();
