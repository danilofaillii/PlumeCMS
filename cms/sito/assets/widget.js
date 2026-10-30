/* Widget extra dell'editor: caroselli, lightbox, schede, fisarmonica, contatore, barra, conto alla rovescia,
   sommario, titolo animato, condividi, avviso, mappa, griglia con pagine, pulsante fisso. Nessuna libreria.
   Ogni elemento si inizializza una volta (data-ew-ok); nell'anteprima dell'editor un osservatore riprende i pezzi nuovi. */
(function () {
  'use strict';
  var anteprima = !!document.querySelector('#ed-pagina [data-ed-id]') || !!document.getElementById('ed-pagina');
  var ridotto = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var q = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  function nuovo(sel, root, fn) { q(sel, root).forEach(function (el) { if (el.dataset.ewOk) return; el.dataset.ewOk = '1'; try { fn(el); } catch (e) { if (window.console) console.error('widget', sel, e); } }); }
  var ioSupport = 'IntersectionObserver' in window;
  function quandoVisibile(el, fn) {
    if (!ioSupport || anteprima) { fn(); return; }
    var io = new IntersectionObserver(function (e) { if (e[0].isIntersecting) { io.disconnect(); fn(); } }, { threshold: 0.2 });
    io.observe(el);
  }

  // ---- contatore
  function contatore(el) {
    var da = parseFloat(el.dataset.da) || 0, a = parseFloat(el.dataset.a) || 0, dur = parseInt(el.dataset.durata, 10) || 2000, sep = el.dataset.sep || '';
    var fmt = function (n) { var s = Math.round(n).toString(); return sep ? s.replace(/\B(?=(\d{3})+(?!\d))/g, sep) : s; };
    quandoVisibile(el, function () {
      if (ridotto) { el.textContent = fmt(a); return; }
      var t0 = null;
      function passo(t) { if (!t0) t0 = t; var p = Math.min(1, (t - t0) / dur); p = 1 - Math.pow(1 - p, 3); el.textContent = fmt(da + (a - da) * p); if (p < 1) requestAnimationFrame(passo); }
      el.textContent = fmt(da); requestAnimationFrame(passo);
    });
  }

  // ---- barra
  function barra(el) {
    if (ridotto) return;
    el.classList.add('ew-attesa');
    quandoVisibile(el, function () { requestAnimationFrame(function () { el.classList.remove('ew-attesa'); }); });
  }

  // ---- fisarmonica: una sola aperta
  function fisa(el) {
    if (el.dataset.una !== '1') return;
    el.addEventListener('toggle', function (e) {
      if (!e.target.open) return;
      q(':scope > details', el).forEach(function (d) { if (d !== e.target) d.open = false; });
    }, true);
  }

  // ---- schede
  function tab(el) {
    var btn = q('[role=tab]', el), pan = q('[role=tabpanel]', el);
    function attiva(i, focus) {
      btn.forEach(function (b, j) { var on = i === j; b.classList.toggle('attivo', on); b.setAttribute('aria-selected', on); b.tabIndex = on ? 0 : -1; });
      pan.forEach(function (p, j) { var on = i === j; p.classList.toggle('attivo', on); p.hidden = !on; var m = p.querySelector('.ew-tab-mobile'); if (m) m.setAttribute('aria-expanded', on); });
      if (focus) btn[i].focus();
    }
    btn.forEach(function (b, i) {
      b.addEventListener('click', function () { attiva(i); });
      b.addEventListener('keydown', function (e) {
        var k = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
        if (k) { e.preventDefault(); attiva((i + k + btn.length) % btn.length, true); }
        if (e.key === 'Home') { e.preventDefault(); attiva(0, true); } if (e.key === 'End') { e.preventDefault(); attiva(btn.length - 1, true); }
      });
    });
    // Su telefono le schede possono diventare una fisarmonica: i pulsanti nei pannelli.
    pan.forEach(function (p, i) {
      var m = p.querySelector('.ew-tab-mobile');
      if (!m) return;
      m.addEventListener('click', function () {
        var on = m.getAttribute('aria-expanded') === 'true';
        if (on) { m.setAttribute('aria-expanded', 'false'); } else { attiva(i); }
      });
    });
  }

  // ---- carosello
  function carosello(el) {
    var tr = el.querySelector('.ew-car-traccia'), voci = q('.ew-car-voce', el), prev = el.querySelector('.ew-car-prev'), next = el.querySelector('.ew-car-next'), pall = el.querySelector('.ew-car-pallini');
    if (!tr || !voci.length) return;
    var ciclo = el.dataset.ciclo === '1', auto = parseInt(el.dataset.auto, 10) || 0, timer = null, fermo = false;
    function perVista() { var w = voci[0].getBoundingClientRect().width; return Math.max(1, Math.round((tr.clientWidth + gap()) / (w + gap()))); }
    function gap() { var g = parseFloat(getComputedStyle(tr).columnGap || getComputedStyle(tr).gap) || 0; return g; }
    function passo() { return el.dataset.scorri === 'vista' ? perVista() : 1; }
    function pagine() { return Math.max(1, Math.ceil((voci.length - perVista()) / passo()) + 1); }
    function indice() { var w = voci[0].getBoundingClientRect().width + gap(); return Math.round(tr.scrollLeft / (w * passo())); }
    function vai(i) {
      var n = pagine();
      if (i < 0) i = ciclo ? n - 1 : 0; if (i > n - 1) i = ciclo ? 0 : n - 1;
      var w = voci[0].getBoundingClientRect().width + gap();
      tr.scrollTo({ left: Math.min(i * passo() * w, tr.scrollWidth - tr.clientWidth), behavior: ridotto ? 'auto' : 'smooth' });
    }
    function aggiorna() {
      var i = indice(), n = pagine();
      if (prev) prev.disabled = !ciclo && i <= 0; if (next) next.disabled = !ciclo && i >= n - 1;
      if (pall) {
        if (pall.children.length !== n) { pall.innerHTML = ''; for (var k = 0; k < n; k++) { var b = document.createElement('button'); b.type = 'button'; b.setAttribute('role', 'tab'); b.setAttribute('aria-label', 'Vai a ' + (k + 1)); b.dataset.i = k; pall.appendChild(b); } }
        q('button', pall).forEach(function (b, k) { b.setAttribute('aria-selected', k === i); });
      }
    }
    if (prev) prev.addEventListener('click', function () { vai(indice() - 1); });
    if (next) next.addEventListener('click', function () { vai(indice() + 1); });
    if (pall) pall.addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) vai(parseInt(b.dataset.i, 10)); });
    var st; tr.addEventListener('scroll', function () { clearTimeout(st); st = setTimeout(aggiorna, 80); }, { passive: true });
    window.addEventListener('resize', aggiorna);
    el.addEventListener('keydown', function (e) { if (e.key === 'ArrowLeft') { vai(indice() - 1); e.preventDefault(); } if (e.key === 'ArrowRight') { vai(indice() + 1); e.preventDefault(); } });
    if (auto && !ridotto) {
      var avvia = function () { clearInterval(timer); timer = setInterval(function () { if (!fermo && !document.hidden) vai(indice() + 1 > pagine() - 1 && !ciclo ? 0 : indice() + 1); }, auto); };
      if (el.dataset.pausa === '1') { el.addEventListener('mouseenter', function () { fermo = true; }); el.addEventListener('mouseleave', function () { fermo = false; }); el.addEventListener('focusin', function () { fermo = true; }); el.addEventListener('focusout', function () { fermo = false; }); }
      avvia();
    }
    aggiorna();
  }

  // ---- lightbox (gallerie, caroselli, singole immagini con data-ew-lb)
  var lb = null;
  function apriLightbox(lista, i) {
    chiudiLightbox();
    var box = document.createElement('div'); box.className = 'ew-lightbox'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.setAttribute('aria-label', 'Immagine ingrandita');
    var ic = function (n) { var t = document.querySelector('.ico-' + n); return t ? t.outerHTML : (n === 'croce' ? 'X' : (n === 'chevron-sinistra' ? '<' : '>')); };
    box.innerHTML = '<button type="button" class="ew-lb-chiudi" aria-label="Chiudi">' + ic('croce') + '</button>' + (lista.length > 1 ? '<button type="button" class="ew-lb-prev" aria-label="Precedente">' + ic('chevron-sinistra') + '</button><button type="button" class="ew-lb-next" aria-label="Successiva">' + ic('chevron-destra') + '</button>' : '') + '<figure><img alt=""><figcaption></figcaption></figure><span class="ew-lb-conta"></span>';
    var img = box.querySelector('img'), did = box.querySelector('figcaption'), conta = box.querySelector('.ew-lb-conta'), prima = document.activeElement;
    function mostra(k) { i = (k + lista.length) % lista.length; img.src = lista[i].href; img.alt = lista[i].dataset.did || (lista[i].querySelector('img') || {}).alt || ''; did.textContent = lista[i].dataset.did || ''; did.hidden = !did.textContent; conta.textContent = lista.length > 1 ? (i + 1) + ' / ' + lista.length : ''; }
    box.addEventListener('click', function (e) { var b = e.target.closest('button'); if (b && b.classList.contains('ew-lb-prev')) mostra(i - 1); else if (b && b.classList.contains('ew-lb-next')) mostra(i + 1); else if (b || e.target === box) chiudiLightbox(); });
    function tasti(e) { if (e.key === 'Escape') chiudiLightbox(); if (e.key === 'ArrowLeft') mostra(i - 1); if (e.key === 'ArrowRight') mostra(i + 1); if (e.key === 'Tab') { var f = q('button', box); var a = f.indexOf(document.activeElement); e.preventDefault(); f[(a + (e.shiftKey ? -1 : 1) + f.length) % f.length].focus(); } }
    document.addEventListener('keydown', tasti);
    var x0 = null; box.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true }); box.addEventListener('touchend', function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) mostra(dx < 0 ? i + 1 : i - 1); x0 = null; });
    lb = { box: box, tasti: tasti, prima: prima, overflow: document.body.style.overflow };
    document.body.style.overflow = 'hidden';
    document.body.appendChild(box); mostra(i); box.querySelector('.ew-lb-chiudi').focus();
  }
  function chiudiLightbox() { if (!lb) return; document.removeEventListener('keydown', lb.tasti); lb.box.remove(); document.body.style.overflow = lb.overflow; if (lb.prima && lb.prima.focus) lb.prima.focus(); lb = null; }
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-ew-lb]');
    if (!a || anteprima) return;
    e.preventDefault();
    var gruppo = a.closest('[data-ew-galleria], .ew-car-lb, .ed-widget') || document;
    var lista = q('a[data-ew-lb]', gruppo); if (!lista.length) lista = [a];
    apriLightbox(lista, Math.max(0, lista.indexOf(a)));
  });

  // ---- conto alla rovescia
  function conto(el) {
    var fine = new Date(el.dataset.fine).getTime(); if (isNaN(fine)) return;
    var num = {}; q('.ew-conto-num', el).forEach(function (n) { num[n.dataset.u] = n; });
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    function tick() {
      var r = Math.max(0, Math.floor((fine - Date.now()) / 1000));
      var g = Math.floor(r / 86400), o = Math.floor(r % 86400 / 3600), m = Math.floor(r % 3600 / 60), s = r % 60;
      if (!num.g) { o += g * 24; }
      if (num.g) num.g.textContent = g; if (num.o) num.o.textContent = pad(o); if (num.m) num.m.textContent = pad(m); if (num.s) num.s.textContent = pad(s);
      if (r <= 0) {
        clearInterval(t);
        var az = el.dataset.azione;
        if (az === 'nascondi') el.classList.add('ew-nascosto');
        if (az === 'messaggio') { el.classList.add('ew-scaduto'); var msg = el.querySelector('.ew-conto-msg'); if (msg) msg.hidden = false; }
        if (az === 'vai' && el.dataset.vai && !anteprima && !el.dataset.giaScaduto) location.href = el.dataset.vai;
      }
    }
    if (fine <= Date.now()) el.dataset.giaScaduto = '1';
    tick(); var t = setInterval(tick, 1000);
  }

  // ---- sommario
  function slug(s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9\s-]/g, '').trim().replace(/\s+/g, '-').replace(/-+/g, '-'); }
  function sommario(el) {
    var liv = (el.dataset.livelli || 'h2,h3').split(','), cont = el.dataset.cont ? document.querySelector(el.dataset.cont) : null, esc = el.dataset.escludi;
    var radice = cont || document.getElementById('contenuto') || document.body;
    function testo(h) { if (!h.querySelector('.ew-tanim-rot')) return h.textContent.trim(); var c = h.cloneNode(true); q('.ew-tanim-misura, .ew-tanim-parola:not(.attiva)', c).forEach(function (x) { x.remove(); }); return c.textContent.replace(/\s+/g, ' ').trim(); }
    var lista = el.querySelector('.ew-toc-lista'), vuoto = el.querySelector('.ew-toc-vuoto'), corpo = el.querySelector('.ew-toc-corpo'), tog = el.querySelector('.ew-toc-toggle');
    function costruisci() {
      var titoli = q(liv.join(','), radice).filter(function (h) {
        if (el.contains(h) || h.closest('.ew-toc, .ed-strumenti, header.top, footer.piede, .ew-fisa-titolo, .ew-tab-lista, .ew-tab-mobile, .ew-posts, .griglia')) return false;
        if (esc) { try { if (h.closest(esc)) return false; } catch (e) { /* selettore non valido */ } }
        return h.textContent.trim() !== '';
      });
      lista.innerHTML = '';
      if (!titoli.length) { if (vuoto) vuoto.hidden = false; return []; }
      if (vuoto) vuoto.hidden = true;
      var usati = {}, pila = [{ livello: 0, ol: lista }], voci = [];
      titoli.forEach(function (h) {
        var n = parseInt(h.tagName.charAt(1), 10);
        if (!h.id) { var base = slug(testo(h)) || 'titolo', id = base, k = 2; while (document.getElementById(id) || usati[id]) id = base + '-' + k++; h.id = id; }
        usati[h.id] = true;
        while (pila.length > 1 && pila[pila.length - 1].livello >= n) pila.pop();
        var padre = pila[pila.length - 1];
        if (padre.livello && n > padre.livello && padre.ultimo) { var sub = padre.ultimo.querySelector(':scope > ol'); if (!sub) { sub = document.createElement('ol'); padre.ultimo.appendChild(sub); } pila.push({ livello: n, ol: sub }); padre = pila[pila.length - 1]; }
        var li = document.createElement('li'), a = document.createElement('a'); a.href = '#' + h.id; a.textContent = testo(h); li.appendChild(a); padre.ol.appendChild(li); padre.ultimo = li;
        if (!padre.livello) padre.livello = n;
        voci.push({ h: h, a: a });
      });
      return voci;
    }
    var voci = costruisci();
    if (tog && corpo) {
      var chiuso = el.dataset.chiuso === 'sempre' || (el.dataset.chiuso === 'telefono' && window.innerWidth <= 767);
      var imposta = function (aperto) { tog.setAttribute('aria-expanded', aperto); corpo.hidden = !aperto; };
      imposta(!chiuso);
      tog.addEventListener('click', function () { imposta(tog.getAttribute('aria-expanded') !== 'true'); });
    }
    if (el.dataset.evidenzia === '1' && ioSupport && voci.length) {
      var visibili = {};
      var io = new IntersectionObserver(function (es) {
        es.forEach(function (e) { visibili[e.target.id] = e.isIntersecting; });
        var primo = voci.filter(function (v) { return visibili[v.h.id]; })[0];
        if (primo) voci.forEach(function (v) { v.a.classList.toggle('attivo', v === primo); });
      }, { rootMargin: '-80px 0px -60% 0px' });
      voci.forEach(function (v) { io.observe(v.h); });
    }
    if (anteprima) el.ewRicostruisci = costruisci;
  }

  // ---- titolo animato: parole rotanti e disegno ripetuto
  function rotante(el) {
    var parole = q('.ew-tanim-parola', el), dur = parseInt(el.dataset.durata, 10) || 2500, ciclo = el.dataset.ciclo === '1', i = 0;
    if (parole.length < 2 || ridotto) return;
    var scrittura = el.classList.contains('ew-anim-scrittura'), testi = parole.map(function (p) { return p.textContent; });
    function mostra(k) {
      parole.forEach(function (p, j) { p.classList.remove('uscita'); if (j === i) p.classList.add('uscita'); p.classList.toggle('attiva', j === k); p.setAttribute('aria-hidden', j !== k); });
      i = k;
      if (scrittura) { var p = parole[k], t = testi[k], n = 0; p.textContent = ''; var it = setInterval(function () { n++; p.textContent = t.slice(0, n); if (n >= t.length) clearInterval(it); }, Math.max(30, Math.min(120, dur / 3 / t.length))); }
    }
    var timer = setInterval(function () { if (document.hidden) return; var k = i + 1; if (k >= parole.length) { if (!ciclo) { clearInterval(timer); return; } k = 0; } mostra(k); }, dur);
  }
  function marcatore(el) {
    if (el.dataset.ciclo === '1' && !ridotto) { el.style.setProperty('--ew-durata', (parseInt(el.dataset.durata, 10) || 2500) + 'ms'); el.classList.add('ew-ripeti'); }
  }

  // ---- condividi: copia e stampa; avviso: chiudi; mappa: carica al clic
  function copia(testo, b) {
    var fatto = function () { b.classList.add('ew-copiato'); var s = b.querySelector('span'); var vecchio = s ? s.textContent : ''; if (s) s.textContent = 'Copiato'; setTimeout(function () { b.classList.remove('ew-copiato'); if (s) s.textContent = vecchio; }, 1800); };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(testo).then(fatto, fatto);
    else { var t = document.createElement('textarea'); t.value = testo; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); } catch (e) { /* niente */ } t.remove(); fatto(); }
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-ew-copia]'); if (b) { var w = b.closest('[data-ew-condividi]'); copia((w && w.dataset.url) || location.href, b); return; }
    b = e.target.closest('[data-ew-copia-testo]'); if (b) { copia(b.dataset.ewCopiaTesto, b); return; }
    b = e.target.closest('[data-ew-stampa]'); if (b) { if (!anteprima) window.print(); return; }
    b = e.target.closest('[data-ew-chiudi]'); if (b) { var av = b.closest('.ew-avviso'); if (av && !anteprima) av.classList.add('ew-via'); return; }
    b = e.target.closest('[data-ew-mappa]'); if (b) { var m = b.closest('.ew-mappa'); if (m && m.dataset.src) { var f = document.createElement('iframe'); f.src = m.dataset.src; f.title = m.dataset.titolo || 'Mappa'; f.loading = 'lazy'; f.referrerPolicy = 'no-referrer-when-downgrade'; f.allowFullscreen = true; m.innerHTML = ''; m.appendChild(f); } return; }
    b = e.target.closest('[data-ew-fisso="su"]'); if (b) { e.preventDefault(); if (!anteprima) window.scrollTo({ top: 0, behavior: ridotto ? 'auto' : 'smooth' }); }
  });

  // ---- pulsante fisso: compare dopo uno scorrimento
  function fisso(el) {
    if (anteprima) return;
    var dopo = parseInt(el.dataset.dopo, 10) || 0;
    if (!dopo) return;
    var agg = function () { el.classList.toggle('ew-nascosto', window.scrollY < dopo); };
    window.addEventListener('scroll', agg, { passive: true }); agg();
  }

  // ---- griglia di articoli: pagine lato client
  function pagine(el) {
    var per = parseInt(el.dataset.ewPagina, 10) || 6, modo = el.dataset.modo || 'numeri', voci = q('.ew-post', el), nav = el.querySelector('.ew-posts-pag');
    if (!nav || voci.length <= per) return;
    var n = Math.ceil(voci.length / per), p = 0;
    function mostra(k) {
      p = k;
      voci.forEach(function (v, i) { v.classList.toggle('ew-pag-nascosto', modo === 'altri' ? i >= (k + 1) * per : (i < k * per || i >= (k + 1) * per)); });
      nav.innerHTML = '';
      if (modo === 'altri') { if (k < n - 1) { var b = document.createElement('button'); b.type = 'button'; b.textContent = 'Carica altri'; b.addEventListener('click', function () { mostra(k + 1); }); nav.appendChild(b); } return; }
      var mk = function (t, i, dis, cur) { var b = document.createElement('button'); b.type = 'button'; b.innerHTML = t; b.disabled = !!dis; if (cur) b.setAttribute('aria-current', 'page'); b.addEventListener('click', function () { mostra(i); el.scrollIntoView({ block: 'start', behavior: ridotto ? 'auto' : 'smooth' }); }); nav.appendChild(b); };
      mk('&lsaquo;', k - 1, k === 0); for (var i = 0; i < n; i++) mk(String(i + 1), i, false, i === k); mk('&rsaquo;', k + 1, k === n - 1);
    }
    mostra(0);
  }

  // ---- orari di apertura (1.10.0): "Aperto ora" si ricalcola ogni minuto, nel fuso del sito, con gli stessi dati del server
  function orari(el) {
    var d; try { d = JSON.parse(el.dataset.ewOrari); } catch (e) { return; }
    var stato = el.querySelector('.ew-orari-stato'), frase = el.querySelector('.ew-orari-frase');
    if (!stato || !frase || !window.Intl) return;
    var nomi = ['lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'];
    function adesso(spost) {
      var p = {}; new Intl.DateTimeFormat('en-GB', { timeZone: d.fuso, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', weekday: 'short', hour12: false })
        .formatToParts(new Date(Date.now() + (spost || 0) * 86400000)).forEach(function (x) { p[x.type] = x.value; });
      return { data: p.year + '-' + p.month + '-' + p.day, g: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].indexOf(p.weekday), min: (parseInt(p.hour, 10) % 24) * 60 + parseInt(p.minute, 10) };
    }
    function hm(m) { m = m % 1440; return Math.floor(m / 60) + ':' + ('0' + (m % 60)).slice(-2); }
    function inChiusura(data) { for (var i = 0; i < d.chiusure.length; i++) if (data >= d.chiusure[i][0] && data <= d.chiusure[i][1]) return d.chiusure[i]; return null; }
    function calcola() {
      var o = adesso(0), c = inChiusura(o.data), i, k, x;
      if (c) return [false, d.chiuso + (c[2] ? ': ' + c[2] : '')];
      var ieri = d.giorni[(o.g + 6) % 7] || [];
      for (i = 0; i < ieri.length; i++) if (ieri[i][1] > 1440 && o.min < ieri[i][1] - 1440) return [true, d.aperto + ', chiude alle ' + hm(ieri[i][1])];
      var oggi = d.giorni[o.g] || [];
      for (i = 0; i < oggi.length; i++) { if (oggi[i][0] === 0 && oggi[i][1] === 1440) return [true, d.aperto + ', 24 ore su 24']; if (o.min >= oggi[i][0] && o.min < oggi[i][1]) return [true, d.aperto + ', chiude alle ' + hm(oggi[i][1])]; }
      for (k = 0; k < 8; k++) {
        x = adesso(k); if (inChiusura(x.data)) continue;
        var g = d.giorni[x.g] || [];
        for (i = 0; i < g.length; i++) { if (k === 0 && g[i][0] <= o.min) continue; return [false, d.chiuso + ', apre ' + (k === 0 ? 'alle ' : k === 1 ? 'domani alle ' : nomi[x.g] + ' alle ') + hm(g[i][0])]; }
      }
      return [false, d.chiuso];
    }
    function aggiorna() { var r = calcola(); frase.textContent = r[1]; stato.classList.toggle('ew-aperto', r[0]); stato.classList.toggle('ew-chiuso-ora', !r[0]); }
    aggiorna(); var t = setInterval(function () { if (!el.isConnected) { clearInterval(t); return; } aggiorna(); }, 60000);
  }

  // ---- prima e dopo (1.10.0): il cursore (un input range invisibile sopra le foto) sposta la linea
  function primaDopo(el) {
    var r = el.querySelector('.ew-pd-cursore');
    if (!r) return;
    r.addEventListener('input', function () { el.style.setProperty('--ew-pos', r.value + '%'); });
  }

  function avvia(root) {
    nuovo('[data-ew-orari]', root, orari);
    nuovo('[data-ew-pd]', root, primaDopo);
    nuovo('[data-ew-num]', root, contatore);
    nuovo('[data-ew-barra]', root, barra);
    nuovo('[data-ew-fisa]', root, fisa);
    nuovo('[data-ew-tab]', root, tab);
    nuovo('[data-ew-car]', root, carosello);
    nuovo('[data-ew-conto]', root, conto);
    nuovo('[data-ew-toc]', root, sommario);
    nuovo('[data-ew-rot]', root, rotante);
    nuovo('[data-ew-mark]', root, marcatore);
    nuovo('[data-ew-fisso]', root, fisso);
    nuovo('[data-ew-pagina]', root, pagine);
  }
  function pronto() {
    avvia(document);
    // Anteprima dell'editor: la tela si ridisegna a ogni modifica, i pezzi nuovi si inizializzano da soli.
    var tela = document.getElementById('ed-pagina');
    if (tela && 'MutationObserver' in window) {
      var t;
      new MutationObserver(function () { clearTimeout(t); t = setTimeout(function () { avvia(tela); q('[data-ew-toc]', tela).forEach(function (s) { if (s.ewRicostruisci) s.ewRicostruisci(); }); }, 60); }).observe(tela, { childList: true, subtree: true });
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', pronto); else pronto();
  window.ewAvvia = avvia;
})();
