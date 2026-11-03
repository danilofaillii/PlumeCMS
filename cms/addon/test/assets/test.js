// Addon Test e questionari: il test una domanda per schermata, con la barra di avanzamento, Indietro e Avanti, la
// tastiera, le domande condizionali che compaiono solo se servono, le risposte salvate nel browser per riprendere.
// Senza questo script il modulo funziona lo stesso: tutte le domande in una pagina sola (le condizionali dicono
// "Rispondi solo se..."). Il calcolo del risultato lo fa sempre il server: qui non si conta niente.
// Nella pagina del risultato: toglie le risposte salvate e accende "Copia il link".
(function () {
  'use strict';
  function leggi(k) { try { return JSON.parse(window.localStorage.getItem(k) || 'null'); } catch (e) { return null; } }
  function salva(k, v) { try { window.localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
  function togli(k) { try { window.localStorage.removeItem(k); } catch (e) {} }

  // ---------- Pagina del risultato ----------
  var fatto = document.querySelector('[data-tq-fatto]');
  if (fatto) {
    togli('plume-test-' + fatto.getAttribute('data-tq-fatto'));
    var campo = document.querySelector('[data-copia]'), bott = document.querySelector('[data-copia-link]');
    if (campo && bott) {
      bott.hidden = false;
      campo.addEventListener('focus', function () { campo.select(); });
      bott.addEventListener('click', function () {
        var ok = function () { bott.textContent = 'Link copiato'; setTimeout(function () { bott.textContent = 'Copia il link'; }, 2500); };
        if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(campo.value).then(ok, function () { campo.select(); });
        else { campo.select(); try { document.execCommand('copy'); ok(); } catch (e) {} }
      });
    }
    return;
  }

  var form = document.getElementById('tq-modulo');
  if (!form) return;
  var app = document.getElementById('tq-app'), inizia = document.getElementById('tq-inizia');
  var id = form.getAttribute('data-test'), CHIAVE = 'plume-test-' + id, api = form.getAttribute('data-api');
  var tutte = Array.prototype.slice.call(form.querySelectorAll('.tq-domanda'));
  var fine = document.getElementById('tq-fine');
  var perK = {};
  tutte.forEach(function (f) { perK[f.getAttribute('data-k')] = f; });
  // Il cursore mostra il suo numero.
  form.querySelectorAll('[data-cursore]').forEach(function (r) {
    var o = r.parentNode.querySelector('output');
    r.addEventListener('input', function () { if (o) o.textContent = r.value; });
  });
  // Dopo un invio con errori (il server ha rimandato la pagina) resta la pagina intera, con gli errori in vista.
  if (app.getAttribute('data-errori')) { var err = document.getElementById('tq-errore'); if (err) err.scrollIntoView(); return; }

  var stato = leggi(CHIAVE) || {};
  if (typeof stato !== 'object' || !stato) stato = {};
  stato.r = stato.r && typeof stato.r === 'object' ? stato.r : {};

  function codice() {
    var a = new Uint8Array(16), c = 'abcdefghijklmnopqrstuvwxyz0123456789', s = '';
    if (window.crypto && crypto.getRandomValues) crypto.getRandomValues(a); else for (var i = 0; i < 16; i++) a[i] = Math.floor(Math.random() * 256);
    for (var j = 0; j < 16; j++) s += c[a[j] % 36];
    return s;
  }
  function cond(f) { try { return JSON.parse(f.getAttribute('data-cond') || 'null'); } catch (e) { return null; } }
  function valore(f) {
    var r = f.querySelector('input[type=radio]:checked');
    if (r) return r.value;
    var c = f.querySelector('input[type=range]');
    return c ? c.value : '';
  }
  // L'ordine: quello del test, oppure (ordine casuale) le domande fisse mescolate e ogni condizionale subito dopo quella da cui dipende.
  function ordine() {
    var chiavi = tutte.map(function (f) { return f.getAttribute('data-k'); });
    if (!form.getAttribute('data-casuale')) return chiavi;
    if (Array.isArray(stato.o) && stato.o.length === chiavi.length && stato.o.every(function (k) { return perK[k]; })) return stato.o;
    // 2.23.0: le domande sulla persona restano in fondo, nel loro ordine.
    var persona = chiavi.filter(function (k) { return perK[k].getAttribute('data-persona'); });
    chiavi = chiavi.filter(function (k) { return !perK[k].getAttribute('data-persona'); });
    var fisse = chiavi.filter(function (k) { return !cond(perK[k]); });
    for (var i = fisse.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)), x = fisse[i]; fisse[i] = fisse[j]; fisse[j] = x; }
    var out = [];
    function metti(k) { out.push(k); chiavi.forEach(function (c) { var x = cond(perK[c]); if (x && x.domanda === k) metti(c); }); }
    fisse.forEach(metti);
    chiavi.forEach(function (k) { if (out.indexOf(k) < 0) out.push(k); });
    out = out.concat(persona);
    stato.o = out;
    return out;
  }
  var lista = [];
  function visibili() {
    var si = {};
    return lista.filter(function (k) {
      var c = cond(perK[k]);
      if (c && (!si[c.domanda] || c.valori.indexOf(valore(perK[c.domanda])) < 0)) return false;
      si[k] = true;
      return true;
    });
  }
  function manda(k, n) {
    if (!api) return;
    try {
      var d = new FormData();
      d.append('t', id); d.append('c', stato.c); d.append('d', k || ''); d.append('n', String(n || 0));
      if (navigator.sendBeacon) navigator.sendBeacon(api, d); else fetch(api, { method: 'POST', body: d, keepalive: true, credentials: 'same-origin' });
    } catch (e) {}
  }

  // I pezzi della modalità "una per schermata".
  form.setAttribute('novalidate', '');
  form.querySelectorAll('[required]').forEach(function (x) { if (!x.closest('#tq-fine')) x.removeAttribute('required'); });
  var testa = document.createElement('div');
  testa.className = 'tq-progresso';
  testa.innerHTML = '<div class="tq-barra" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="Avanzamento del test"><span></span></div><p class="tq-passo" aria-live="polite"></p>';
  form.insertBefore(testa, form.firstChild);
  var nav = document.createElement('div');
  nav.className = 'tq-nav';
  nav.innerHTML = '<button type="button" class="btn btn-filo" data-indietro>Indietro</button><button type="button" class="btn btn-viola" data-avanti>Avanti</button>';
  form.insertBefore(nav, fine);
  var bIndietro = nav.querySelector('[data-indietro]'), bAvanti = nav.querySelector('[data-avanti]');
  var barra = testa.querySelector('.tq-barra'), passo = testa.querySelector('.tq-passo');
  var qui = 0, viste = {};

  function mostra(i, fuoco) {
    var vis = visibili(), tot = vis.length;
    qui = Math.max(0, Math.min(i, tot));
    tutte.forEach(function (f) { f.hidden = true; });
    fine.hidden = qui < tot;
    var f = qui < tot ? perK[vis[qui]] : null;
    if (f) {
      f.hidden = false;
      var num = f.querySelector('.tq-num');
      if (num) num.firstChild.nodeValue = 'Domanda ' + (qui + 1) + ' di ' + tot;
    }
    var pct = tot ? Math.round(qui / tot * 100) : 100;
    barra.setAttribute('aria-valuenow', String(pct));
    barra.setAttribute('aria-valuetext', qui < tot ? 'Domanda ' + (qui + 1) + ' di ' + tot : 'Ultimo passo: vedi il risultato');
    barra.firstChild.style.width = pct + '%';
    passo.textContent = qui < tot ? 'Domanda ' + (qui + 1) + ' di ' + tot : 'Hai risposto a tutto: un ultimo passo';
    bIndietro.hidden = qui === 0;
    bAvanti.hidden = qui >= tot;
    stato.i = qui;
    salva(CHIAVE, stato);
    if (f && !viste[vis[qui]]) { viste[vis[qui]] = true; manda(vis[qui], qui + 1); }
    if (fuoco !== false) {
      var dove = f || fine;
      dove.setAttribute('tabindex', '-1');
      dove.focus({ preventScroll: true });
      var top = form.getBoundingClientRect().top + window.pageYOffset - 90;
      if (window.pageYOffset > top || form.getBoundingClientRect().top > window.innerHeight * 0.5) window.scrollTo(0, Math.max(0, top));
    }
  }
  function errore(f, si) {
    var p = f.querySelector('.tq-manca');
    if (!si) { if (p) p.remove(); f.classList.remove('errato'); f.removeAttribute('aria-invalid'); return; }
    if (!p) {
      p = document.createElement('p'); p.className = 'errore-campo tq-manca'; p.setAttribute('role', 'alert'); p.id = 'tm-' + f.getAttribute('data-k');
      p.textContent = 'Scegli una risposta per andare avanti.';
      f.appendChild(p);
      f.setAttribute('aria-describedby', ((f.getAttribute('aria-describedby') || '') + ' ' + p.id).trim());
    }
    f.classList.add('errato');
  }
  function avanti() {
    var vis = visibili(), f = perK[vis[qui]];
    if (f && f.getAttribute('data-obbl') === '1' && valore(f) === '') { errore(f, true); var r = f.querySelector('input'); if (r) r.focus(); return; }
    if (f) errore(f, false);
    mostra(qui + 1);
  }
  bAvanti.addEventListener('click', avanti);
  bIndietro.addEventListener('click', function () { mostra(qui - 1); });
  // Invio su una risposta va avanti invece di mandare il modulo (che va mandato solo dall'ultimo passo).
  form.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' || fine.contains(e.target) || e.target.tagName === 'BUTTON') return;
    e.preventDefault();
    avanti();
  });
  form.addEventListener('change', function (e) {
    var f = e.target.closest('.tq-domanda');
    if (!f) return;
    stato.r[f.getAttribute('data-k')] = valore(f);
    if (valore(f) !== '') errore(f, false);
    salva(CHIAVE, stato);
  });
  form.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;
    // Le risposte delle domande che non si vedono più (condizionali) non partono.
    var vis = visibili();
    tutte.forEach(function (f) {
      if (vis.indexOf(f.getAttribute('data-k')) >= 0) return;
      f.querySelectorAll('input').forEach(function (x) { if (x.type === 'radio') x.checked = false; else x.disabled = true; });
    });
    form.querySelector('input[name=tentativo]').value = stato.c || '';
  });

  function rimetti() {
    Object.keys(stato.r).forEach(function (k) {
      var f = perK[k];
      if (!f) return;
      var v = String(stato.r[k]);
      f.querySelectorAll('input[type=radio]').forEach(function (x) { x.checked = x.value === v; });
      var c = f.querySelector('input[type=range]');
      if (c && /^\d+$/.test(v)) { c.value = v; var o = c.parentNode.querySelector('output'); if (o) o.textContent = v; }
    });
  }
  function avvia(riprendi) {
    if (!riprendi || !stato.c) { stato = { r: {}, c: codice() }; form.reset(); form.querySelectorAll('[data-cursore]').forEach(function (r) { var o = r.parentNode.querySelector('output'); if (o) o.textContent = r.value; }); }
    lista = ordine();
    if (riprendi) rimetti();
    app.hidden = false;
    inizia.parentNode.hidden = true;
    var box = document.getElementById('tq-riprendi');
    if (box) box.remove();
    if (!riprendi) manda('', 0);
    mostra(riprendi ? (stato.i || 0) : 0);
  }

  app.hidden = true;
  inizia.addEventListener('click', function (e) { e.preventDefault(); avvia(false); });
  // Un test cominciato e non finito: si riprende da dove si era, oppure si ricomincia.
  if (stato.c && Object.keys(stato.r).length) {
    var box = document.createElement('div');
    box.id = 'tq-riprendi';
    box.className = 'tq-riprendi';
    box.setAttribute('role', 'status');
    box.innerHTML = '<p>Hai già cominciato questo test: le tue risposte sono rimaste in questo browser.</p><p class="tq-azioni"><button type="button" class="btn btn-viola" data-riprendi>Riprendi da dove eri</button> <button type="button" class="btn btn-filo" data-ricomincia>Ricomincia da capo</button></p>';
    inizia.parentNode.parentNode.insertBefore(box, inizia.parentNode);
    inizia.parentNode.hidden = true;
    box.querySelector('[data-riprendi]').addEventListener('click', function () { avvia(true); });
    box.querySelector('[data-ricomincia]').addEventListener('click', function () { togli(CHIAVE); avvia(false); });
  }
})();
