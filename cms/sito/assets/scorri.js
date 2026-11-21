/* 2.25.0: il widget "Scorri di lato" (includes/editor-widget-scorri.php, sito/assets/scorri.css).
   Quando chi scorre arriva sul widget la scena si ferma (position:sticky, alta uno schermo) e le pagine scorrono di lato,
   legate allo scorrimento verticale; dopo l'ultima pagina la pagina riparte in verticale. Lo script mette .sdl-vivo e l'altezza
   della radice; senza script, per chi chiede meno movimento e nella tela dell'editor (.sdl-tela) le pagine restano una sotto
   l'altra. Più widget nella stessa pagina vanno ognuno per conto suo. Niente librerie, niente gestori in linea. */
(function () {
  'use strict';
  var ridotto = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
  var tutti = [];
  var limita = function (v, a, b) { return Math.min(b, Math.max(a, v)); };

  /** L'altezza della testata ferma del sito (.top o un header sticky/fisso in cima): la scena si ferma sotto. */
  function altoTestata() {
    var h = 0;
    document.querySelectorAll('body > header, .top, .ed-testata, header.sito-testata').forEach(function (e) {
      var s = getComputedStyle(e);
      if ((s.position === 'sticky' || s.position === 'fixed') && parseFloat(s.top || '0') <= 1) {
        var r = e.getBoundingClientRect();
        if (r.top <= 1 && r.height < window.innerHeight / 3) h = Math.max(h, r.height);
      }
    });
    return Math.round(h);
  }

  function prepara(r) {
    if (r._sdl || r.classList.contains('sdl-tela')) return;
    var d = {
      disp: r.getAttribute('data-sdl') || 'pellicola',
      vel: parseFloat(r.getAttribute('data-sdl-vel')) || 1,
      pieno: r.hasAttribute('data-sdl-pieno'),
      scena: r.querySelector('.sdl-scena'),
      bin: r.querySelector('.sdl-binario'),
      pagine: [].slice.call(r.querySelectorAll('.sdl-pagina')),
      lineette: [].slice.call(r.querySelectorAll('.sdl-lineette b')),
      contatore: r.querySelector('.sdl-contatore b'),
      linea: r.querySelector('.sdl-linea'),
      corsa: 0, alto: 0, k: -1
    };
    d.finestre = d.pagine.map(function (p) { return p.querySelector('.sdl-finestra'); });
    d.testi = d.pagine.map(function (p) { return p.querySelector('.sdl-testo'); });
    r._sdl = d;
    tutti.push(r);
    // Tastiera: chi arriva con Tab su un pulsante di una pagina la vede, la scena si sposta lì.
    r.addEventListener('focusin', function (e) {
      if (!r.classList.contains('sdl-vivo')) return;
      var pg = e.target.closest ? e.target.closest('.sdl-pagina') : null;
      if (!pg) return;
      var i = d.pagine.indexOf(pg);
      if (i < 0) return;
      vaiAPagina(r, i);
      requestAnimationFrame(function () { vaiAPagina(r, i); });
    });
    // Il focus su un elemento fuori dalla scena la fa scorrere da sola (overflow:hidden si scorre col codice): si rimette a zero.
    [d.scena, d.bin].forEach(function (el) { if (el) el.addEventListener('scroll', function () { if (el.scrollLeft || el.scrollTop) { el.scrollLeft = 0; el.scrollTop = 0; } }); });
  }

  /** Quanto deve scorrere di lato il binario (px) per arrivare all'ultima pagina. */
  function corsa(r) {
    var d = r._sdl, n = d.pagine.length;
    if (n < 2) return 0;
    if (d.disp === 'fermo') return Math.max(0, d.finestre[n - 1].offsetLeft - d.finestre[0].offsetLeft);
    var ultima = d.pagine[n - 1];
    var pad = parseFloat(getComputedStyle(d.bin).paddingLeft) || 0;
    return Math.max(0, Math.round(ultima.offsetLeft + ultima.offsetWidth + pad - d.scena.clientWidth));
  }

  /** Dove sta la pagina i nel binario (lo spostamento che la porta in vista). */
  function posPagina(r, i) {
    var d = r._sdl;
    if (d.disp === 'fermo') return d.finestre[i].offsetLeft - d.finestre[0].offsetLeft;
    var pad = parseFloat(getComputedStyle(d.bin).paddingLeft) || 0;
    return d.pagine[i].offsetLeft - pad;
  }

  function vaiAPagina(r, i) {
    var d = r._sdl;
    if (d.scena) { d.scena.scrollLeft = 0; d.scena.scrollTop = 0; }
    if (d.bin) { d.bin.scrollLeft = 0; }
    var tot = r.offsetHeight - d.scena.offsetHeight;
    var p = d.corsa > 0 ? limita(posPagina(r, i) / d.corsa, 0, 1) : 0;
    var su = r.getBoundingClientRect().top + window.pageYOffset - d.alto;
    var dove = Math.round(su + p * tot);
    if (Math.abs(window.pageYOffset - dove) > 1) {
      try { window.scrollTo({ top: dove, behavior: 'instant' }); } catch (e) { window.scrollTo(0, dove); }
    }
    giro();
  }

  function misura() {
    var vivo = !ridotto.matches;
    var alto = vivo ? altoTestata() : 0;
    tutti.forEach(function (r) {
      var d = r._sdl;
      r.classList.toggle('sdl-vivo', vivo);
      if (!vivo) {
        r.style.width = ''; r.style.marginLeft = ''; r.style.height = '';
        d.bin.style.transform = '';
        r.style.removeProperty('--sdl-x'); r.style.removeProperty('--sdl-alto');
        d.pagine.forEach(function (p, i) { p.style.transform = ''; if (d.finestre[i]) d.finestre[i].style.transform = ''; if (d.testi[i]) { d.testi[i].style.transform = ''; d.testi[i].style.opacity = ''; } });
        return;
      }
      d.alto = alto;
      r.style.setProperty('--sdl-alto', alto + 'px');
      // L'altezza non si azzera mai prima di misurare: la pagina si accorcerebbe per un attimo e chi sta leggendo più sotto
      // salterebbe in fondo (il browser riporta lo scorrimento dentro la pagina più corta).
      if (d.pieno) {
        // A tutta larghezza: la radice esce dalla colonna fino ai bordi della finestra (senza la barra di scorrimento).
        // Il bordo sinistro si misura dal contenitore, che non dipende dal margine della radice.
        var g = r.parentElement, gs = getComputedStyle(g), gb = g.getBoundingClientRect();
        var sinistra = gb.left + (parseFloat(gs.borderLeftWidth) || 0) + (parseFloat(gs.paddingLeft) || 0) + window.pageXOffset;
        r.style.width = document.documentElement.clientWidth + 'px';
        r.style.marginLeft = (-Math.round(sinistra)) + 'px';
      } else { r.style.width = ''; r.style.marginLeft = ''; }
      d.corsa = corsa(r);
      var hScena = d.scena.offsetHeight;
      r.style.height = Math.round(hScena + d.corsa * d.vel) + 'px';
      if (d.linea) d.linea.style.setProperty('--sdl-lw', (d.bin.scrollWidth) + 'px');
    });
    giro();
  }

  function giro() {
    if (ridotto.matches) return;
    tutti.forEach(function (r) {
      var d = r._sdl;
      if (!r.classList.contains('sdl-vivo')) return;
      var n = d.pagine.length;
      var tot = r.offsetHeight - d.scena.offsetHeight;
      var p = tot > 0 ? limita((d.alto - r.getBoundingClientRect().top) / tot, 0, 1) : 0;
      var x = -p * d.corsa;
      if (d.disp === 'fermo') r.style.setProperty('--sdl-x', x.toFixed(1) + 'px');
      else d.bin.style.transform = 'translate3d(' + x.toFixed(1) + 'px,0,0)';
      var k = n > 1 ? Math.round(p * (n - 1)) : 0;
      if (k !== d.k) {
        d.k = k;
        d.pagine.forEach(function (pg, i) { pg.classList.toggle('sdl-on', i === k); });
        if (d.contatore) d.contatore.textContent = String(k + 1);
      }
      d.lineette.forEach(function (b, i) { b.style.transform = 'scaleX(' + limita(p * (n - 1) - i + 1, 0, 1).toFixed(3) + ')'; });
      var w = d.scena.clientWidth;
      if (d.disp === 'carte') {
        var cx = d.scena.getBoundingClientRect().left + w / 2;
        d.pagine.forEach(function (c) { var q = c.getBoundingClientRect(); var dd = limita(Math.abs(q.left + q.width / 2 - cx) / (w / 2), 0, 1); c.style.transform = 'translateY(' + (dd * 18).toFixed(1) + 'px) scale(' + (1 - dd * 0.06).toFixed(3) + ')'; });
      } else if (d.disp === 'tempo') {
        var lb = d.linea && d.linea.querySelector('b');
        if (lb) lb.style.transform = 'scaleX(' + p.toFixed(3) + ')';
        var lim = d.scena.getBoundingClientRect().left + w * 0.55;
        d.pagine.forEach(function (t) { t.classList.toggle('sdl-raggiunta', t.getBoundingClientRect().left < lim); });
      } else if (d.disp === 'profondita') {
        d.pagine.forEach(function (pg, i) {
          var off = pg.offsetLeft + x;
          var f = d.finestre[i], t = d.testi[i];
          if (f) f.style.transform = 'translate3d(' + (-off * 0.45).toFixed(1) + 'px,0,0) scale(' + (1.05 + Math.abs(off) / w * 0.08).toFixed(3) + ')';
          if (t) { t.style.transform = 'translate3d(' + (off * 0.35).toFixed(1) + 'px,0,0)'; t.style.opacity = limita(1 - Math.abs(off) / w * 1.4, 0, 1).toFixed(3); }
        });
      }
    });
  }

  var inAttesa = false;
  function suScroll() { if (!inAttesa) { inAttesa = true; requestAnimationFrame(function () { inAttesa = false; giro(); }); } }
  var tMis = null;
  function presto() { clearTimeout(tMis); tMis = setTimeout(misura, 60); }

  function avvia() {
    document.querySelectorAll('.sdl[data-sdl]').forEach(prepara);
    if (!tutti.length) return;
    window.addEventListener('scroll', suScroll, { passive: true });
    window.addEventListener('resize', presto);
    if (ridotto.addEventListener) ridotto.addEventListener('change', misura); else if (ridotto.addListener) ridotto.addListener(misura);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(misura);
    window.addEventListener('load', misura);
    tutti.forEach(function (r) { r.querySelectorAll('img').forEach(function (i) { if (!i.complete) i.addEventListener('load', presto); }); });
    misura();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', avvia); else avvia();
})();
