// Addon Test e questionari, pannello: i cinque passi della procedura (2.24.0). Ogni passo ha la stessa forma: a sinistra
// il lavoro fatto di carte compatte con i bollini, che si aprono in una finestra del pannello (<dialog>: Esc chiude, il
// fuoco torna alla carta); a destra il telefono con l'anteprima dal vivo di quello che il passo cambia per chi fa il test.
// Sotto 900 px il telefono si apre a schermo intero con il pulsante «Anteprima».
//   passo 1  la pagina di presentazione (nome, durata, testo breve, Inizia il test)
//   passo 2  la lavagna delle pagine: una colonna per pagina, una carta per domanda, trascinamento fra colonne (SortableJS del
//            pannello), il riquadro «+ Pagina nuova», la colonna fissa delle domande sulla persona; aree, profili e scala
//   passo 3  le fasce come carte in fila, con la barra da 0 a 100 che mostra copertura e buchi
//   passo 4  una carta per fascia o profilo (risposta breve e completa con Quill, pulsante), le regole; «A schermo / Email»
//   passo 5  email, newsletter, automazioni, privacy: la fine del test
// I dati viaggiano come prima: campi del modulo (anche dentro le finestre chiuse) e, per i passi 2, 3 e 4, un JSON in un
// campo nascosto (campi-protetti.js lo manda in base64). Il server controlla tutto di nuovo (test_normalizza). Nessun
// gestore in linea: tutto con addEventListener.
(function () {
  'use strict';
  var el = document.getElementById('ts-iniziali');
  if (!el) return;
  var I = JSON.parse(el.textContent || '{}');
  var D = I.dati || {}, TIPO = I.tipo, MAX = I.max || {}, PASSO = +I.passo || 0;
  var form = document.getElementById('ts-form');
  if (!form) return;

  function h(s) { return String(s === undefined || s === null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function chiave(p) { var a = new Uint8Array(4); if (window.crypto && crypto.getRandomValues) crypto.getRandomValues(a); else for (var i = 0; i < 4; i++) a[i] = Math.floor(Math.random() * 256); return p + Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join(''); }
  function opz(lista, sc) { return lista.map(function (x) { return '<option value="' + h(x[0]) + '"' + (String(x[0]) === String(sc) ? ' selected' : '') + '>' + h(x[1]) + '</option>'; }).join(''); }
  function sposta(lista, i, d) { var j = i + d; if (j < 0 || j >= lista.length) return false; var x = lista.splice(i, 1)[0]; lista.splice(j, 0, x); return true; }
  function testoPiu(x, n) { n = n || 60; x = String(x || ''); return x.length > n ? x.slice(0, n - 3) + '...' : x; }
  function $(s, d) { return (d || document).querySelector(s); }
  function $$(s, d) { return Array.prototype.slice.call((d || document).querySelectorAll(s)); }
  function annuncia(testo, box) { var s = box ? box.querySelector('.ts-annuncio') : $('#ts-annuncio'); if (s) s.textContent = testo; }
  function vuotoRicco(x) { return String(x || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim() === ''; }
  function larghezze(box) { $$('[data-larga]', box).forEach(function (x) { x.style.width = x.getAttribute('data-larga') + '%'; }); $$('[data-sinistra]', box).forEach(function (x) { x.style.left = x.getAttribute('data-sinistra') + '%'; }); }
  // Un testo ricco salvato (già ripulito dal server) o scritto con Quill: nel telefono senza script e senza gestori.
  function ricco(x) { return String(x || '').replace(/<\/?(script|style|iframe|object|embed)[^>]*>/gi, '').replace(/\son\w+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, ''); }

  // =====================================================================  Le finestre (le carte si aprono qui)
  var daDove = null;
  function apri(id, primo) {
    var d = document.getElementById(id);
    if (!d || !d.showModal) return;
    daDove = document.activeElement;
    if (!d.open) d.showModal();
    var f = primo ? d.querySelector(primo) : d.querySelector('.ts-fin-corpo input:not([type=hidden]):not([disabled]), .ts-fin-corpo select, .ts-fin-corpo textarea:not([hidden]), .ts-fin-corpo .ql-editor, .ts-fin-corpo summary');
    if (f) f.focus();
  }
  function chiudiTutte() { $$('dialog.ts-finestra[open]').forEach(function (d) { d.close(); }); }
  $$('dialog.ts-finestra').forEach(function (d) {
    d.addEventListener('close', function () {
      d.dispatchEvent(new CustomEvent('ts-chiusa'));
      var torna = daDove && document.body.contains(daDove) ? daDove : document.querySelector('[data-apri="' + d.id + '"]');
      // Il fuoco torna alla carta subito dopo: durante l'evento close la pagina può essere ancora inerte (finestra modale).
      if (torna && torna.focus) { torna.focus(); setTimeout(function () { if (document.body.contains(torna) && document.activeElement !== torna) torna.focus(); }, 0); }
      daDove = null;
    });
    // Un clic sul velo, fuori dalla finestra, la chiude.
    d.addEventListener('mousedown', function (e) {
      if (e.target !== d) return;
      var r = d.getBoundingClientRect();
      if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) d.close();
    });
  });
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-apri]');
    if (a) { e.preventDefault(); apri(a.getAttribute('data-apri')); return; }
    var c = e.target.closest('[data-chiudi-fin]');
    if (c) { var d = c.closest('dialog'); if (d) d.close(); }
  });
  // Invio in un campo di una finestra non manda il modulo (salverebbe il passo a metà): chiude la finestra.
  form.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    var t = e.target;
    if (t.closest('dialog.ts-finestra') && (t.id === 'tsq-testo' || t.tagName === 'INPUT' && !/^(checkbox|radio|button|submit)$/.test(t.type))) { e.preventDefault(); if (!t.hasAttribute('data-link-sito')) { var d = t.closest('dialog'); if (d) d.close(); } }
  });
  // Le carte fatte di div (lavagna): Invio e Spazio come il clic.
  document.addEventListener('keydown', function (e) {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('[role=button][tabindex]:not(button)')) { e.preventDefault(); e.target.click(); }
  });

  // =====================================================================  L'anteprima a schermo intero (sotto 900 px)
  var lato = document.getElementById('ts-lato');
  var bAnt = $('[data-ts-anteprima]');
  function anteprima(si) {
    if (!lato) return;
    lato.classList.toggle('ts-lato-aperta', si);
    document.documentElement.classList.toggle('ts-bloccata', si);
    if (si) { lato.setAttribute('role', 'dialog'); lato.setAttribute('aria-modal', 'true'); var c = $('[data-ts-anteprima-chiudi]', lato); if (c) c.focus(); }
    else { lato.removeAttribute('role'); lato.removeAttribute('aria-modal'); if (bAnt) bAnt.focus(); }
    if (bAnt) bAnt.setAttribute('aria-expanded', si ? 'true' : 'false');
  }
  if (bAnt) bAnt.addEventListener('click', function () { anteprima(true); });
  document.addEventListener('click', function (e) { if (e.target.closest('[data-ts-anteprima-chiudi]')) anteprima(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && lato && lato.classList.contains('ts-lato-aperta') && !$('dialog.ts-finestra[open]')) anteprima(false); });

  // =====================================================================  Il telefono
  var tel = document.getElementById('ts-tel'), puntiniBox = document.getElementById('ts-puntini');
  // o: {su: 'Pagina 1 di 3', pct: 0..100 (o null), corpo: html, piede: html}
  function telefono(o) {
    if (!tel) return;
    tel.innerHTML = (o.su || o.pct !== undefined ? '<div class="tsv-top">' + (o.su ? '<span class="tsv-su">' + h(o.su) + '</span>' : '')
      + (typeof o.pct === 'number' ? '<span class="tsv-barra"><span data-larga="' + Math.max(3, o.pct) + '"></span></span>' : '') + '</div>' : '')
      + '<div class="tsv-corpo">' + (o.corpo || '') + '</div>' + (o.piede ? '<div class="tsv-piede">' + o.piede + '</div>' : '');
    larghezze(tel);
  }
  function puntini(n, att, nome) {
    if (!puntiniBox) return;
    var s = '';
    for (var i = 0; i < n; i++) s += '<button type="button" data-punto="' + i + '"' + (i === att ? ' aria-current="true"' : '') + ' aria-label="Mostra ' + h(nome(i, true)) + ' nel telefono">' + h(nome(i)) + '</button>';
    puntiniBox.innerHTML = s;
  }
  function occhiello(t) { return '<span class="tsv-occhiello">' + h(t) + '</span>'; }
  function pulsanteTel(t, cls) { return t ? '<span class="tsv-btn ' + (cls || 'tsv-pieno') + '">' + h(t) + '</span>' : ''; }
  function avvertenzaTel(t) { return String(t || '').trim() ? '<p class="tsv-avvertenza">' + h(t) + '</p>' : ''; }

  // =====================================================================  PASSO 1: la pagina di presentazione
  if (PASSO === 1) {
    var tipiNomi = {};
    $$('#ts-tipo option').forEach(function (o) { tipiNomi[o.value] = o.textContent; });
    var quanto = function () {
      var c = I.conti || [0, 0], fisse = c[0], tutte = c[1];
      var durata = parseInt(($('#ts-durata') || {}).value, 10) || 0;
      var min = durata > 0 ? durata : Math.max(1, Math.ceil(tutte * (I.secondiDomanda || 12) / 60));
      var n = fisse === tutte ? (tutte === 1 ? '1 domanda' : tutte + ' domande') : (fisse + '-' + tutte + ' domande');
      return n + ', circa ' + (min === 1 ? '1 minuto' : min + ' minuti');
    };
    var disegna1 = function () {
      var nome = ($('#ts-nome').value || '').trim() || I.nome, pres = ($('#ts-pres').value || '').trim();
      var slug = ($('#ts-slug').value || '').trim();
      var vivo = function (k, v) { $$('[data-vivo="' + k + '"]').forEach(function (x) { x.textContent = v; }); };
      vivo('nome', nome); vivo('indirizzo', '/fai-il-test/' + (slug || '...') + '/'); vivo('quanto', quanto()); vivo('tipo', tipiNomi[$('#ts-tipo').value] || ''); vivo('pres', testoPiu(pres, 140) || 'Vuoto');
      var paragrafi = pres ? pres.split(/\n\s*\n/).map(function (p) { return '<p>' + h(p.trim()).replace(/\n/g, '<br>') + '</p>'; }).join('') : '<p class="tsv-nota">Qui esce la presentazione composta con l\'editor visuale.</p>';
      telefono({ corpo: occhiello('Test') + '<h1 class="tsv-h1">' + h(nome) + '</h1><p class="tsv-numeri">' + h(quanto()) + '</p>' + paragrafi + '<p>' + pulsanteTel('Inizia il test') + '</p>' + avvertenzaTel(D.avvertenza) });
    };
    form.addEventListener('input', disegna1);
    form.addEventListener('change', disegna1);
    disegna1();
  }

  // =====================================================================  PASSO 2: la lavagna delle pagine
  var lav = document.getElementById('ts-lavagna');
  if (lav) {
    D.domande = D.domande || []; D.aree = D.aree || []; D.profili = D.profili || []; D.scala = D.scala || [];
    var MODO = I.modi && I.modi[D.modo] ? D.modo : 'una';
    var PAG = (D.pagine || []).map(function (p) { return { titolo: p.titolo || '', domande: (p.domande || []).slice() }; });
    var pagAtt = 0, selK = null, selPag = 0;
    var perK = function (k) { for (var i = 0; i < D.domande.length; i++) if (D.domande[i].k === k) return D.domande[i]; return null; };
    var numero = function (k) { for (var i = 0; i < D.domande.length; i++) if (D.domande[i].k === k) return i + 1; return 0; };
    // Ogni domanda in una pagina sola; quelle senza pagina in fondo all'ultima; almeno una pagina (come test_pagine_normalizza).
    var sistemaPagine = function () {
      var ks = D.domande.map(function (q) { return q.k; }), visti = {};
      PAG.forEach(function (p) { p.domande = p.domande.filter(function (k) { if (ks.indexOf(k) < 0 || visti[k]) return false; visti[k] = true; return true; }); });
      if (!PAG.length) PAG.push({ titolo: '', domande: [] });
      ks.forEach(function (k) { if (!visti[k]) PAG[PAG.length - 1].domande.push(k); });
    };
    // Le condizioni si appoggiano solo a domande che vengono prima: dopo uno spostamento quelle rotte si tolgono e si dice.
    var controllaCondizioni = function () {
      var prima = {}, tolte = [];
      D.domande.forEach(function (q, i) {
        if (q.condizione && (!prima[q.condizione.domanda] || prima[q.condizione.domanda].tipo === 'cursore')) { q.condizione = null; tolte.push(i + 1); }
        prima[q.k] = q;
      });
      return tolte;
    };
    // L'ordine delle domande è quello delle pagine.
    var allinea = function () {
      sistemaPagine();
      var nuovo = [];
      PAG.forEach(function (p) { p.domande.forEach(function (k) { nuovo.push(perK(k)); }); });
      D.domande = nuovo;
      return controllaCondizioni();
    };
    var pagineEff = function () {
      if (MODO === 'pagine') return PAG;
      var ks = D.domande.map(function (q) { return q.k; });
      if (MODO === 'tutte') return [{ titolo: '', domande: ks }];
      return ks.map(function (k) { return { titolo: '', domande: [k] }; });
    };
    var paginaDi = function (k) { for (var i = 0; i < PAG.length; i++) if (PAG[i].domande.indexOf(k) >= 0) return i; return -1; };
    var paginaEffDi = function (k) { var P = pagineEff(); for (var i = 0; i < P.length; i++) if (P[i].domande.indexOf(k) >= 0) return i; return 0; };
    // Le domande sulla persona in uso, dai campi della loro finestra, nell'ordine scelto.
    var persona = function () {
      return $$('#ts-persona [data-persona-k]').map(function (d, pos) {
        var k = d.getAttribute('data-persona-k'), v = function (c) { var x = d.querySelector('[name="persona[' + k + '][' + c + ']"]'); return x ? x.value : ''; };
        var usa = d.querySelector('[name="persona[' + k + '][usa]"]');
        return { k: k, usa: usa && usa.checked, ordine: parseInt(v('ordine'), 10) || 0, pos: pos, nome: v('nome'), testo: v('testo'), risposte: v('risposte').split(/\r?\n/).map(function (x) { return x.trim(); }).filter(Boolean) };
      }).filter(function (x) { return x.usa; }).sort(function (a, b) { return a.ordine - b.ordine || a.pos - b.pos; });
    };
    var obbligatoriePersona = function () { var r = $('input[name=persona_obbligatorie]:checked'); return r && r.value === '1'; };
    var tipiDom = Object.keys(I.tipiDomanda || {}).filter(function (k) { return TIPO !== 'profili' || k === 'scelta' || k === 'sino'; }).map(function (k) { return [k, I.tipiDomanda[k]]; });
    var tipiCorti = { scala: 'Scala', scelta: 'Scelta', sino: 'Sì o no', cursore: 'Cursore' };
    var opzioniDi = function (q) {
      if (q.tipo === 'scala') return D.scala.map(function (s, i) { return { k: 's' + i, t: s.t }; });
      if (q.tipo === 'cursore') return [];
      return q.opzioni || [];
    };
    var assicuraOpzioni = function (q) {
      if (q.tipo === 'sino') {
        var per = {}; (q.opzioni || []).forEach(function (o) { per[o.k] = o; });
        q.opzioni = [{ k: 'si', t: 'Sì', punti: per.si ? per.si.punti : 1, profili: per.si ? per.si.profili || {} : {} }, { k: 'no', t: 'No', punti: per.no ? per.no.punti : 0, profili: per.no ? per.no.profili || {} : {} }];
      } else if (q.tipo === 'scelta') {
        q.opzioni = (q.opzioni || []).filter(function (o) { return o.k !== 'si' && o.k !== 'no' || q.opzioni.length > 2; });
        while (q.opzioni.length < 2) q.opzioni.push({ k: chiave('o'), t: '', punti: q.opzioni.length, profili: {} });
      }
      q.opzioni = q.opzioni || [];
      q.opzioni.forEach(function (o) { o.profili = o.profili || {}; });
    };
    var areaIndice = function (k) { for (var i = 0; i < D.aree.length; i++) if (D.aree[i].k === k) return i; return -1; };
    var bollini = function (q) {
      var s = '';
      if (TIPO === 'aree') { var ai = areaIndice(q.area); s += '<span class="ts-bollino ts-bollino-area" data-colore="' + (ai % 5) + '">' + h(ai >= 0 ? D.aree[ai].nome : 'Senza area') + '</span>'; }
      s += '<span class="ts-bollino">' + h(tipiCorti[q.tipo] || q.tipo) + '</span>';
      if (q.invertita) s += '<span class="ts-bollino">Invertita</span>';
      if (q.peso !== undefined && +q.peso !== 1) s += '<span class="ts-bollino">Peso ' + h(q.peso) + '</span>';
      if (q.condizione) s += '<span class="ts-bollino ts-bollino-cond">Condizionale</span>';
      if (q.obbligatoria === false) s += '<span class="ts-bollino">Facoltativa</span>';
      return s;
    };
    var cartaD = function (q) {
      var n = numero(q.k);
      return '<div class="ts-carta-d' + (q.k === selK ? ' ts-carta-sel' : '') + '" role="button" tabindex="0" data-k="' + h(q.k) + '" aria-haspopup="dialog">'
        + '<span class="ts-carta-testo"><span class="ts-num"><span class="sr-solo">Domanda </span>' + n + '</span> ' + h(q.testo || 'Domanda senza testo') + '</span><span class="ts-bollini">' + bollini(q) + '</span></div>';
    };
    var riassunto = function () {
      var P = pagineEff().filter(function (p) { return p.domande.length; }), np = persona().length, nd = D.domande.length;
      var t = (P.length === 1 ? '1 pagina' : P.length + ' pagine') + ' di domande, ' + (nd === 1 ? '1 domanda' : nd + ' domande') + (np ? ', più la pagina sulla persona (' + np + ')' : '') + '.';
      if (MODO !== 'pagine') t += ' Per spostare le domande fra le pagine e dare un titolo alle pagine scegli «A pagine, le scegli tu».';
      $('#ts-riassunto').textContent = t;
      $$('[data-conta]').forEach(function (b) { var c = b.getAttribute('data-conta'); b.textContent = (c === 'aree' ? 'Aree: ' : 'Profili: ') + D[c].length; });
    };
    var disegnaLavagna = function () {
      var P = pagineEff(), pers = persona(), np = P.length;
      if (pagAtt > np) pagAtt = np;
      var s = '<div class="ts-colonne">';
      P.forEach(function (p, i) {
        var n = p.domande.length;
        s += '<section class="ts-col' + (i === pagAtt ? ' ts-col-qui' : '') + '" data-pag="' + i + '" aria-labelledby="ts-col-t' + i + '"><div class="ts-col-testa">'
          + '<button type="button" class="ts-col-nome" id="ts-col-t' + i + '" data-vai="' + i + '" aria-pressed="' + (i === pagAtt) + '"><b>Pagina ' + (i + 1) + '</b>'
          + (MODO === 'pagine' && p.titolo ? '<span class="ts-col-titolo">' + h(p.titolo) + '</span>' : '') + '<span class="ts-col-n">' + n + (n === 1 ? ' domanda' : ' domande') + '</span></button>'
          + (MODO === 'pagine' ? '<button type="button" class="btn btn-secondary btn-sm ts-col-mod" data-titolo-pag="' + i + '" aria-label="Titolo della pagina ' + (i + 1) + '">Titolo</button>' : '')
          + '</div><div class="ts-col-lista" data-lista="' + i + '">' + p.domande.map(function (k) { return cartaD(perK(k)); }).join('')
          + (n ? '' : '<p class="ts-col-vuota">Pagina vuota: trascina qui una domanda (chi fa il test la salta).</p>') + '</div></section>';
      });
      s += '<section class="ts-col ts-col-persona' + (pagAtt === np ? ' ts-col-qui' : '') + '" aria-labelledby="ts-col-tp"><div class="ts-col-testa"><button type="button" class="ts-col-nome" id="ts-col-tp" data-vai="' + np + '" aria-pressed="' + (pagAtt === np) + '"><b>Pagina ' + (np + 1) + ': su di te</b><span class="ts-col-n">sempre in fondo</span></button></div>'
        + '<div class="ts-col-fissa">' + pers.map(function (x) { return '<button type="button" class="ts-carta ts-carta-p" data-apri="ts-fin-persona"><span class="ts-carta-testo">' + h(x.testo) + '</span><span class="ts-bollini"><span class="ts-bollino">' + h(x.nome) + '</span></span></button>'; }).join('')
        + (pers.length ? '' : '<p class="ts-col-vuota">Nessuna domanda sulla persona.</p>') + '<button type="button" class="btn btn-secondary btn-sm" data-apri="ts-fin-persona">Scegli le domande sulla persona</button></div></section>';
      if (MODO === 'pagine') s += '<div class="ts-col-nuova" data-nuova><button type="button" class="ts-col-nuova-btn" data-piu-pag' + (PAG.length >= (MAX.pagine || 80) ? ' disabled' : '') + '>+ Pagina nuova<small>o trascina qui una carta</small></button></div>';
      s += '</div>';
      var scorri = lav.scrollLeft;
      lav.innerHTML = s;
      lav.scrollLeft = scorri;
      if (MODO === 'pagine' && typeof Sortable !== 'undefined') {
        $$('[data-lista]', lav).forEach(function (l) { new Sortable(l, { group: 'ts-pagine', draggable: '.ts-carta-d', animation: 120, forceFallback: true, fallbackTolerance: 5, ghostClass: 'ts-fantasma', onEnd: dopoTrascina }); });
        var nv = $('[data-nuova]', lav);
        if (nv) new Sortable(nv, { group: { name: 'ts-pagine', pull: false, put: true }, draggable: '.ts-carta-d', forceFallback: true, onAdd: function () { nv.classList.remove('ts-su'); } });
      }
    };
    var dopoTrascina = function (e) {
      var k = e.item.getAttribute('data-k');
      var nuove = $$('.ts-col[data-pag] [data-lista]', lav).map(function (l, i) { return { titolo: (PAG[i] || {}).titolo || '', domande: $$('.ts-carta-d', l).map(function (c) { return c.getAttribute('data-k'); }) }; });
      var nuova = e.to && e.to.hasAttribute('data-nuova');
      if (nuova) nuove.push({ titolo: '', domande: [k] });
      if (JSON.stringify(nuove) === JSON.stringify(PAG)) { disegnaTutto(); return; }
      PAG = nuove;
      var tolte = allinea();
      selK = k; pagAtt = paginaDi(k);
      disegnaTutto();
      annuncia('Domanda spostata nella pagina ' + (pagAtt + 1) + (nuova ? ' (nuova)' : '') + '.' + (tolte.length ? ' Tolta la condizione della domanda ' + tolte.join(', ') + ': ora la domanda da cui dipendeva viene dopo.' : ''));
    };

    // ---- il telefono del passo 2: la pagina scelta, come la vede chi fa il test
    var rispostaTel = function (q) {
      if (q.tipo === 'cursore') return '<span class="tsv-cursore"><span>0</span><span class="tsv-cursore-linea"></span><span>10</span></span>';
      var o = opzioniDi(q);
      return '<span class="tsv-opzioni' + (q.tipo === 'scala' ? ' tsv-scala' : '') + '">' + o.map(function (x, j) { return '<span class="tsv-opz">' + h(x.t || 'Risposta ' + (j + 1)) + '</span>'; }).join('') + '</span>';
    };
    var condTel = function (q) {
      if (!q.condizione) return '';
      var p = perK(q.condizione.domanda);
      if (!p) return '';
      var nomi = {}; opzioniDi(p).forEach(function (o) { nomi[o.k] = o.t; });
      return '<span class="tsv-se">Compare solo se alla domanda ' + numero(p.k) + ' rispondi ' + q.condizione.valori.map(function (v) { return '«' + h(nomi[v] || v) + '»'; }).join(' o ') + '.</span>';
    };
    var disegnaTel2 = function () {
      var P = pagineEff(), pers = persona(), np = P.length, tot = np + (pers.length ? 1 : 0), totDom = D.domande.length + pers.length;
      var i = Math.min(pagAtt, np), corpo = '';
      if (i >= np) {
        corpo = pers.length ? pers.map(function (x, j) {
          var r = x.risposte.slice(0, 6).concat(x.risposte.length > 6 ? ['... altre ' + (x.risposte.length - 6)] : []).concat(obbligatoriePersona() ? [] : [I.nr]);
          return '<button type="button" class="tsv-dom" data-apri="ts-fin-persona"><span class="tsv-num">Domanda ' + (D.domande.length + j + 1) + ' di ' + totDom + (obbligatoriePersona() ? '' : ' (facoltativa)') + '</span><span class="tsv-testo">' + h(x.testo) + '</span><span class="tsv-aiuto">Su di te: non cambia il risultato e resta anonima.</span><span class="tsv-opzioni">' + r.map(function (t) { return '<span class="tsv-opz">' + h(t) + '</span>'; }).join('') + '</span></button>';
        }).join('') : '<p class="tsv-nota">Nessuna domanda sulla persona: dopo l\'ultima pagina chi fa il test va dritto al risultato.</p>';
      } else {
        var p = P[i];
        corpo = (MODO === 'pagine' && p.titolo ? '<h3 class="tsv-titolo">' + h(p.titolo) + '</h3>' : '') + (p.domande.length ? p.domande.map(function (k) {
          var q = perK(k);
          return '<button type="button" class="tsv-dom' + (k === selK ? ' tsv-sel' : '') + '" data-k="' + h(k) + '"><span class="tsv-num">Domanda ' + numero(k) + ' di ' + totDom + (q.obbligatoria === false || q.condizione ? ' (facoltativa)' : '') + '</span><span class="tsv-testo">' + h(q.testo || 'Domanda senza testo') + '</span>'
            + (q.aiuto ? '<span class="tsv-aiuto">' + h(q.aiuto) + '</span>' : '') + condTel(q) + rispostaTel(q) + '</button>';
        }).join('') : '<p class="tsv-nota">Questa pagina è vuota: chi fa il test la salta.</p>');
      }
      var vis = Math.min(i, Math.max(0, tot - 1));
      telefono({ su: tot ? 'Pagina ' + (vis + 1) + ' di ' + tot : 'Nessuna domanda', pct: tot ? Math.round(vis / tot * 100) : 0, corpo: corpo,
        piede: '<button type="button" class="tsv-btn tsv-filo" data-tel="-1"' + (i === 0 ? ' disabled' : '') + '>Indietro</button><button type="button" class="tsv-btn tsv-pieno" data-tel="1"' + (i >= np ? ' disabled' : '') + '>' + (i >= tot - 1 ? 'Vedi il risultato' : 'Avanti') + '</button>' });
      puntini(np + 1, i, function (j, lungo) { return j === np ? (lungo ? 'la pagina sulla persona' : 'su di te') : (lungo ? 'la pagina ' + (j + 1) : String(j + 1)); });
    };
    var disegnaTutto = function () { disegnaLavagna(); disegnaTel2(); riassunto(); };

    // ---- la scheda della domanda (finestra)
    var finD = document.getElementById('ts-fin-domanda'), corpoD = document.getElementById('ts-domanda-corpo');
    var schedaDomanda = function (fuoco) {
      var q = perK(selK);
      if (!q) { if (finD.open) finD.close(); return; }
      assicuraOpzioni(q);
      var i = D.domande.indexOf(q), p = 'tsq';
      $('#ts-fin-domanda-t').textContent = 'Domanda ' + (i + 1);
      var s = '<div class="form-group"><label for="tsq-testo">Testo della domanda</label><textarea id="tsq-testo" data-k="testo" rows="2" maxlength="400" placeholder="per esempio: Ti svegli stanco?">' + h(q.testo) + '</textarea></div>'
        + '<div class="form-group"><label for="tsq-aiuto">Aiuto sotto la domanda (facoltativo)</label><input type="text" id="tsq-aiuto" data-k="aiuto" maxlength="300" value="' + h(q.aiuto || '') + '" placeholder="per esempio: pensa alle ultime due settimane"></div>'
        + '<div class="ts-griglia-3"><div class="form-group"><label for="tsq-tipo">Tipo di risposta</label><select id="tsq-tipo" data-k="tipo">' + opz(tipiDom, q.tipo) + '</select></div>';
      if (TIPO === 'aree') s += '<div class="form-group"><label for="tsq-area">Area</label><select id="tsq-area" data-k="area">' + (D.aree.length ? opz(D.aree.map(function (a) { return [a.k, a.nome]; }), q.area) : '<option value="">Aggiungi prima un\'area</option>') + '</select></div>';
      s += '<div class="form-group"><label for="tsq-peso">Peso</label><input type="number" id="tsq-peso" data-k="peso" min="0" max="10" step="0.5" value="' + h(q.peso === undefined ? 1 : q.peso) + '"></div></div>'
        + '<div class="ts-caselle">' + (TIPO !== 'profili' ? '<label class="nl-casella"><input type="checkbox" id="tsq-inv" data-k="invertita"' + (q.invertita ? ' checked' : '') + '> Invertita (punti al contrario)</label>' : '')
        + '<label class="nl-casella"><input type="checkbox" id="tsq-obb" data-k="obbligatoria"' + (q.obbligatoria !== false ? ' checked' : '') + '> Obbligatoria</label></div>';
      if (q.tipo === 'scelta' || q.tipo === 'sino') {
        s += '<fieldset class="ts-opzioni"><legend>Risposte</legend><div class="ad-tabella"><table data-no-schede><thead><tr><th scope="col">Risposta</th>'
          + (TIPO === 'profili' ? D.profili.map(function (pr) { return '<th scope="col">Punti a ' + h(pr.nome) + '</th>'; }).join('') : '<th scope="col">Punti</th>')
          + '<th scope="col"><span class="sr-solo">Togli</span></th></tr></thead><tbody>' + q.opzioni.map(function (o, j) {
            var r = '<tr data-j="' + j + '"><td><label class="sr-solo" for="' + p + '-o' + j + '">Risposta ' + (j + 1) + '</label><input type="text" id="' + p + '-o' + j + '" data-o="t" maxlength="200" value="' + h(o.t) + '"' + (q.tipo === 'sino' ? ' readonly' : '') + '></td>';
            if (TIPO === 'profili') r += D.profili.map(function (pr) { return '<td><label class="sr-solo" for="' + p + '-o' + j + '-' + pr.k + '">Punti di «' + h(o.t) + '» al profilo ' + h(pr.nome) + '</label><input type="number" id="' + p + '-o' + j + '-' + pr.k + '" data-o="profilo" data-p="' + h(pr.k) + '" min="-20" max="20" value="' + h(o.profili[pr.k] || 0) + '"></td>'; }).join('');
            else r += '<td><label class="sr-solo" for="' + p + '-o' + j + '-p">Punti di «' + h(o.t) + '»</label><input type="number" id="' + p + '-o' + j + '-p" data-o="punti" min="-100" max="100" value="' + h(o.punti || 0) + '"></td>';
            return r + '<td>' + (q.tipo === 'scelta' ? '<button type="button" class="btn btn-danger btn-sm" data-togli-o="' + j + '"' + (q.opzioni.length <= 2 ? ' disabled' : '') + '>Togli</button>' : '') + '</td></tr>';
          }).join('') + '</tbody></table></div>' + (TIPO === 'profili' && !D.profili.length ? '<p class="hint">Aggiungi prima i profili (pulsante «Profili» sopra la lavagna): poi dai i punti a ogni risposta.</p>' : '')
          + (q.tipo === 'scelta' ? '<button type="button" class="btn btn-secondary btn-sm" data-piu-o' + (q.opzioni.length >= (MAX.opzioni || 12) ? ' disabled' : '') + '>+ Aggiungi una risposta</button>' : '') + '</fieldset>';
      } else if (q.tipo === 'scala') s += '<p class="hint">Risposte della scala: ' + h(D.scala.map(function (x) { return x.t + ' ' + x.v; }).join(', ')) + (q.invertita ? ' (invertite)' : '') + '. Le cambi con «Le parole della scala».</p>';
      else s += '<p class="hint">Un cursore da 0 a 10: i punti sono il numero scelto' + (q.invertita ? ' al contrario (10 meno il numero)' : '') + '.</p>';
      var padri = D.domande.slice(0, i).filter(function (x) { return x.tipo !== 'cursore'; });
      s += '<div class="ts-griglia-2">';
      if (MODO === 'pagine') s += '<div class="form-group"><label for="tsq-pag">Pagina</label><select id="tsq-pag" data-k="pagina">' + PAG.map(function (pg, j) { return '<option value="' + j + '"' + (paginaDi(q.k) === j ? ' selected' : '') + '>Pagina ' + (j + 1) + (pg.titolo ? ': ' + h(testoPiu(pg.titolo, 40)) : '') + '</option>'; }).join('') + (PAG.length < (MAX.pagine || 80) ? '<option value="nuova">Una pagina nuova</option>' : '') + '</select></div>';
      else s += '<div class="form-group"><span class="ts-etichetta">Pagina</span><p class="hint">' + (MODO === 'una' ? 'Una domanda per pagina: è la pagina ' + (i + 1) + '.' : 'Tutte le domande stanno in una pagina.') + ' Per scegliere le pagine: «A pagine, le scegli tu».</p></div>';
      s += '<div class="form-group"><label for="tsq-cond">Mostra questa domanda</label><select id="tsq-cond" data-k="cond">'
        + opz([['', 'Sempre']].concat(padri.map(function (x) { return [x.k, 'Solo se la domanda ' + (D.domande.indexOf(x) + 1) + ' (' + testoPiu(x.testo || 'senza testo', 40) + ') ha risposta...']; })), q.condizione ? q.condizione.domanda : '') + '</select>'
        + (i === 0 ? '<div class="hint">La prima domanda compare sempre.</div>' : '') + '</div></div>';
      if (q.condizione) {
        var padre = perK(q.condizione.domanda);
        s += '<fieldset class="ts-valori"><legend>Una di queste risposte</legend>' + opzioniDi(padre || {}).map(function (o, j) {
          return '<label class="nl-casella"><input type="checkbox" data-val="' + h(o.k) + '"' + (q.condizione.valori.indexOf(o.k) >= 0 ? ' checked' : '') + '> ' + h(o.t || ('Risposta ' + (j + 1))) + '</label>';
        }).join('') + (q.condizione.valori.length ? '' : '<p class="errore-campo" role="alert">Spunta almeno una risposta: senza, la condizione non vale e si toglie al salvataggio.</p>') + '</fieldset>';
      }
      corpoD.innerHTML = s;
      $$('[data-sposta-d]', finD).forEach(function (b) { var d = +b.getAttribute('data-sposta-d'); b.disabled = d < 0 ? i === 0 : i === D.domande.length - 1; });
      if (fuoco) { var f = corpoD.querySelector(fuoco); if (f) f.focus(); }
    };
    var apriDomanda = function (k, fuoco) {
      selK = k; pagAtt = MODO === 'pagine' ? paginaDi(k) : paginaEffDi(k);
      disegnaTutto();
      schedaDomanda();
      apri('ts-fin-domanda', fuoco || '#tsq-testo');
    };
    finD.addEventListener('ts-chiusa', function () { disegnaTutto(); var c = $('.ts-carta-d[data-k="' + selK + '"]', lav); if (c) daDove = c; });
    corpoD.addEventListener('input', function (e) {
      var q = perK(selK); if (!q) return;
      var k = e.target.getAttribute('data-k'), o = e.target.getAttribute('data-o');
      if (k === 'testo' || k === 'aiuto') q[k] = e.target.value;
      if (k === 'peso') q.peso = parseFloat(e.target.value) || 0;
      if (o) {
        var opt = q.opzioni[+e.target.closest('[data-j]').getAttribute('data-j')];
        if (o === 't') opt.t = e.target.value;
        if (o === 'punti') opt.punti = parseInt(e.target.value, 10) || 0;
        if (o === 'profilo') opt.profili[e.target.getAttribute('data-p')] = parseInt(e.target.value, 10) || 0;
      }
      disegnaLavagna(); disegnaTel2();
    });
    corpoD.addEventListener('change', function (e) {
      var q = perK(selK); if (!q) return;
      var k = e.target.getAttribute('data-k');
      // I campi di testo e i numeri si aggiornano con «input»; il loro «change» arriva quando si esce dal campo (anche
      // chiudendo la finestra) e ridisegnare lì toglierebbe la carta su cui torna il fuoco.
      if (k === 'testo' || k === 'aiuto' || k === 'peso' || e.target.hasAttribute('data-o')) return;
      if (k === 'tipo') { q.tipo = e.target.value; if (q.tipo === 'sino' || q.tipo === 'scelta') q.opzioni = q.tipo === 'scelta' && q.opzioni && q.opzioni.length > 2 ? q.opzioni : []; controllaCondizioni(); D.domande.forEach(function (x) { if (x.condizione && x.condizione.domanda === q.k) x.condizione.valori = []; }); schedaDomanda('#tsq-tipo'); disegnaTutto(); return; }
      if (k === 'area') q.area = e.target.value;
      if (k === 'invertita' || k === 'obbligatoria') q[k] = e.target.checked;
      if (k === 'cond') { q.condizione = e.target.value ? { domanda: e.target.value, valori: [] } : null; schedaDomanda('#tsq-cond'); disegnaTutto(); return; }
      if (k === 'pagina') {
        var v = e.target.value, da = paginaDi(q.k);
        if (da >= 0) PAG[da].domande.splice(PAG[da].domande.indexOf(q.k), 1);
        if (v === 'nuova') PAG.push({ titolo: '', domande: [q.k] }); else PAG[+v].domande.push(q.k);
        var tolte = allinea();
        pagAtt = paginaDi(q.k);
        disegnaTutto(); schedaDomanda('#tsq-pag');
        annuncia('Domanda spostata nella pagina ' + (pagAtt + 1) + '.' + (tolte.length ? ' Tolta la condizione della domanda ' + tolte.join(', ') + ': ora la domanda da cui dipendeva viene dopo.' : ''));
        return;
      }
      if (e.target.hasAttribute('data-val')) {
        var val = e.target.getAttribute('data-val'), l = q.condizione.valori, at = l.indexOf(val);
        if (e.target.checked && at < 0) l.push(val); if (!e.target.checked && at >= 0) l.splice(at, 1);
        var er = e.target.closest('.ts-valori').querySelector('.errore-campo'); if (er && l.length) er.remove();
      }
      disegnaLavagna(); disegnaTel2();
    });
    corpoD.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      var q = perK(selK); if (!q) return;
      if (b.hasAttribute('data-piu-o')) { q.opzioni.push({ k: chiave('o'), t: '', punti: q.opzioni.length, profili: {} }); schedaDomanda('#tsq-o' + (q.opzioni.length - 1)); disegnaTel2(); return; }
      if (b.hasAttribute('data-togli-o')) { q.opzioni.splice(+b.getAttribute('data-togli-o'), 1); schedaDomanda('#tsq-testo'); disegnaTel2(); }
    });
    finD.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      if (b.hasAttribute('data-togli-d')) {
        var n = numero(selK), da = paginaDi(selK);
        D.domande = D.domande.filter(function (x) { return x.k !== selK; });
        if (da >= 0) PAG[da].domande = PAG[da].domande.filter(function (x) { return x !== selK; });
        var t = allinea(); selK = null;
        finD.close();
        annuncia('Domanda ' + n + ' tolta.' + (t.length ? ' Tolta anche la condizione della domanda ' + t.join(', ') + '.' : ''));
        disegnaTutto();
        var piu = $('[data-piu-d]'); if (piu) piu.focus();
        return;
      }
      if (b.hasAttribute('data-sposta-d')) {
        var d = +b.getAttribute('data-sposta-d'), k = selK, p = paginaDi(k), pg = PAG[p].domande, j = pg.indexOf(k);
        if (d < 0 && j > 0) sposta(pg, j, -1);
        else if (d > 0 && j < pg.length - 1) sposta(pg, j, 1);
        else {
          // In cima (o in fondo) alla sua pagina: passa alla pagina vicina con domande, scambiandosi con la sua ultima (o prima).
          var q2 = p + d; while (q2 >= 0 && q2 < PAG.length && !PAG[q2].domande.length) q2 += d;
          if (q2 < 0 || q2 >= PAG.length) return;
          pg.splice(j, 1);
          if (d < 0) PAG[q2].domande.splice(PAG[q2].domande.length - 1, 0, k); else PAG[q2].domande.splice(1, 0, k);
        }
        var tolte = allinea();
        pagAtt = MODO === 'pagine' ? paginaDi(k) : paginaEffDi(k);
        disegnaTutto(); schedaDomanda();
        b.focus();
        annuncia('Domanda spostata al posto ' + numero(k) + '.' + (tolte.length ? ' Tolta la condizione della domanda ' + tolte.join(', ') + ': ora la domanda da cui dipendeva viene dopo.' : ''));
      }
    });

    // ---- la finestra della pagina (titolo, togli)
    var finP = document.getElementById('ts-fin-pagina'), corpoP = document.getElementById('ts-pagina-corpo');
    var schedaPagina = function () {
      var p = PAG[selPag]; if (!p) return;
      $('#ts-fin-pagina-t').textContent = 'Pagina ' + (selPag + 1);
      corpoP.innerHTML = '<div class="form-group"><label for="ts-pag-titolo">Titolo della pagina (facoltativo)</label><input type="text" id="ts-pag-titolo" maxlength="' + (MAX.titolo || 120) + '" value="' + h(p.titolo) + '" placeholder="per esempio: Come inizi la giornata" aria-describedby="ts-pag-titolo-aiuto">'
        + '<div class="hint" id="ts-pag-titolo-aiuto">Chi fa il test lo vede sopra le domande di questa pagina. Senza JavaScript diventa un titolo nella pagina unica.</div></div>'
        + '<p class="hint">' + (p.domande.length ? 'In questa pagina: ' + p.domande.map(function (k) { return 'domanda ' + numero(k); }).join(', ') + '.' : 'La pagina è vuota.') + '</p>';
      var tb = $('[data-togli-pag-fin]', finP);
      tb.disabled = p.domande.length > 0 || PAG.length <= 1;
      tb.title = p.domande.length ? 'Si toglie solo una pagina vuota: sposta prima le sue domande.' : '';
    };
    corpoP.addEventListener('input', function (e) { if (e.target.id === 'ts-pag-titolo' && PAG[selPag]) { PAG[selPag].titolo = e.target.value; disegnaLavagna(); disegnaTel2(); } });
    finP.addEventListener('click', function (e) {
      if (!e.target.closest('[data-togli-pag-fin]') || !PAG[selPag] || PAG[selPag].domande.length || PAG.length <= 1) return;
      PAG.splice(selPag, 1); pagAtt = Math.max(0, selPag - 1); finP.close(); disegnaTutto(); annuncia('Pagina tolta.');
    });

    // ---- clic sulla lavagna, sul telefono, sui pulsanti
    lav.addEventListener('click', function (e) {
      var v = e.target.closest('[data-vai]');
      if (v) { pagAtt = +v.getAttribute('data-vai'); disegnaLavagna(); disegnaTel2(); var b = $('[data-vai="' + pagAtt + '"]', lav); if (b) b.focus(); return; }
      var tp = e.target.closest('[data-titolo-pag]');
      if (tp) { selPag = +tp.getAttribute('data-titolo-pag'); pagAtt = selPag; schedaPagina(); disegnaLavagna(); disegnaTel2(); apri('ts-fin-pagina', '#ts-pag-titolo'); return; }
      if (e.target.closest('[data-piu-pag]')) { PAG.push({ titolo: '', domande: [] }); pagAtt = PAG.length - 1; disegnaTutto(); annuncia('Pagina ' + PAG.length + ' aggiunta: trascinaci una domanda, o sceglila dalla tendina «Pagina» nella scheda della domanda.'); var nb = $('[data-vai="' + pagAtt + '"]', lav); if (nb) nb.focus(); return; }
      var c = e.target.closest('.ts-carta-d');
      if (c) apriDomanda(c.getAttribute('data-k'));
    });
    if (tel) tel.addEventListener('click', function (e) {
      var n = e.target.closest('[data-tel]');
      if (n && !n.disabled) { pagAtt = Math.max(0, Math.min(pagineEff().length, pagAtt + (+n.getAttribute('data-tel')))); disegnaLavagna(); disegnaTel2(); return; }
      var d = e.target.closest('.tsv-dom[data-k]');
      if (d) { anteprima(false); apriDomanda(d.getAttribute('data-k')); }
    });
    if (puntiniBox) puntiniBox.addEventListener('click', function (e) { var b = e.target.closest('[data-punto]'); if (b) { pagAtt = +b.getAttribute('data-punto'); disegnaLavagna(); disegnaTel2(); var x = $('[data-punto="' + pagAtt + '"]', puntiniBox); if (x) x.focus(); } });
    $$('[data-modo]').forEach(function (b) {
      b.addEventListener('click', function () {
        MODO = b.getAttribute('data-modo');
        $$('[data-modo]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        pagAtt = 0; disegnaTutto();
        annuncia('Le domande si mostrano: ' + b.textContent + '.');
      });
    });
    var piuD = $('[data-piu-d]');
    if (piuD) piuD.addEventListener('click', function () {
      if (D.domande.length >= (MAX.domande || 80)) { annuncia('Un test ha al massimo ' + (MAX.domande || 80) + ' domande.'); return; }
      var q = { k: chiave('d'), testo: '', aiuto: '', tipo: TIPO === 'profili' ? 'scelta' : 'scala', peso: 1, invertita: false, obbligatoria: true, area: D.aree.length ? D.aree[0].k : '', opzioni: [], condizione: null };
      D.domande.push(q);
      sistemaPagine();
      var dove = MODO === 'pagine' && pagAtt < PAG.length ? pagAtt : PAG.length - 1;
      PAG.forEach(function (p) { p.domande = p.domande.filter(function (x) { return x !== q.k; }); });
      PAG[dove].domande.push(q.k);
      allinea();
      apriDomanda(q.k);
    });
    // Le domande sulla persona: la colonna e il telefono seguono la finestra.
    var boxPers = document.getElementById('ts-persona');
    if (boxPers) {
      var aggiornaPersona = function (e) {
        var d = e && e.target.closest('[data-persona-k]');
        if (d) { var u = d.querySelector('input[name$="[usa]"]'), b = d.querySelector('[data-persona-stato]'); if (u && b) { b.textContent = u.checked ? 'In uso' : 'Non usata'; b.className = 'badge ' + (u.checked ? 'badge-published' : 'badge-draft'); } }
        disegnaTutto();
      };
      boxPers.addEventListener('input', aggiornaPersona);
      boxPers.addEventListener('change', function (e) { if (e.target.type === 'checkbox' || e.target.type === 'radio') aggiornaPersona(e); });
    }

    // ---- aree e profili (nelle loro finestre): elenco di nomi
    var elencoNomi = function (box, lista, cosa, pref, max) {
      if (!box) return;
      var disegna = function (fuoco) {
        box.innerHTML = '<p class="ts-annuncio sr-solo" aria-live="polite"></p><ol class="ts-nomi">' + lista.map(function (a, i) {
          return '<li class="ts-nome" data-i="' + i + '"><span class="ts-maniglia" aria-hidden="true" title="Trascina">&#8942;&#8942;</span>'
            + '<label class="sr-solo" for="tsn-' + pref + i + '">Nome ' + cosa + ' ' + (i + 1) + '</label><input type="text" id="tsn-' + pref + i + '" data-k="nome" maxlength="60" value="' + h(a.nome) + '">'
            + '<button type="button" class="btn btn-secondary btn-sm" data-su aria-label="Sposta su ' + h(a.nome) + '"' + (i === 0 ? ' disabled' : '') + '>&uarr;</button>'
            + '<button type="button" class="btn btn-secondary btn-sm" data-giu aria-label="Sposta giù ' + h(a.nome) + '"' + (i === lista.length - 1 ? ' disabled' : '') + '>&darr;</button>'
            + '<button type="button" class="btn btn-danger btn-sm" data-togli aria-label="Togli ' + h(a.nome) + '">Togli</button></li>';
        }).join('') + '</ol>' + (lista.length ? '' : '<p class="hint ts-vuoto">Ancora nessun' + (pref === 'a' ? '\'area' : ' profilo') + ': aggiungine uno.</p>')
          + '<button type="button" class="btn btn-secondary btn-sm" data-piu' + (lista.length >= max ? ' disabled' : '') + '>+ Aggiungi ' + (pref === 'a' ? 'un\'area' : 'un profilo') + '</button>';
        if (typeof Sortable !== 'undefined') new Sortable(box.querySelector('.ts-nomi'), { handle: '.ts-maniglia', draggable: '.ts-nome', animation: 120, onEnd: function (e) { if (e.oldIndex === e.newIndex) return; var x = lista.splice(e.oldIndex, 1)[0]; lista.splice(e.newIndex, 0, x); disegna(); disegnaTutto(); } });
        if (fuoco) { var f = box.querySelector(fuoco); if (f) f.focus(); }
      };
      box.addEventListener('input', function (e) { var li = e.target.closest('[data-i]'); if (li && e.target.getAttribute('data-k') === 'nome') { lista[+li.getAttribute('data-i')].nome = e.target.value; disegnaLavagna(); disegnaTel2(); } });
      box.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return;
        var li = b.closest('[data-i]'), i = li ? +li.getAttribute('data-i') : -1;
        if (b.hasAttribute('data-piu')) { lista.push({ k: chiave(pref), nome: pref === 'a' ? 'Area ' + (lista.length + 1) : 'Profilo ' + (lista.length + 1) }); disegna('#tsn-' + pref + (lista.length - 1)); disegnaTutto(); return; }
        if (b.hasAttribute('data-togli')) { var nome = lista[i].nome; lista.splice(i, 1); disegna('[data-piu]'); disegnaTutto(); annuncia(nome + ' tolto.', box); return; }
        if (b.hasAttribute('data-su') && sposta(lista, i, -1)) { disegna('#tsn-' + pref + (i - 1)); disegnaTutto(); annuncia('Spostato su.', box); }
        if (b.hasAttribute('data-giu') && sposta(lista, i, 1)) { disegna('#tsn-' + pref + (i + 1)); disegnaTutto(); annuncia('Spostato giù.', box); }
      });
      disegna();
    };
    if (TIPO === 'aree') elencoNomi(document.getElementById('ts-aree-ed'), D.aree, 'dell\'area', 'a', MAX.aree || 12);
    if (TIPO === 'profili') elencoNomi(document.getElementById('ts-profili-ed'), D.profili, 'del profilo', 'p', MAX.profili || 12);

    // ---- la scala (nella sua finestra)
    var boxScala = document.getElementById('ts-scala-ed');
    if (boxScala) {
      var disegnaScala = function (fuoco) {
        boxScala.innerHTML = '<table class="ts-scala" data-no-schede><thead><tr><th scope="col">Risposta</th><th scope="col">Punti</th><th scope="col"><span class="sr-solo">Togli</span></th></tr></thead><tbody>' + D.scala.map(function (s, i) {
          return '<tr data-i="' + i + '"><td><label class="sr-solo" for="tss-t' + i + '">Risposta ' + (i + 1) + '</label><input type="text" id="tss-t' + i + '" data-k="t" maxlength="40" value="' + h(s.t) + '"></td>'
            + '<td><label class="sr-solo" for="tss-v' + i + '">Punti della risposta ' + (i + 1) + '</label><input type="number" id="tss-v' + i + '" data-k="v" min="-100" max="100" value="' + h(s.v) + '"></td>'
            + '<td><button type="button" class="btn btn-danger btn-sm" data-togli' + (D.scala.length <= 2 ? ' disabled' : '') + '>Togli</button></td></tr>';
        }).join('') + '</tbody></table><button type="button" class="btn btn-secondary btn-sm" data-piu' + (D.scala.length >= 7 ? ' disabled' : '') + '>+ Aggiungi una risposta alla scala</button>';
        if (fuoco) { var f = boxScala.querySelector(fuoco); if (f) f.focus(); }
      };
      boxScala.addEventListener('input', function (e) { var r = e.target.closest('[data-i]'); if (!r) return; var s = D.scala[+r.getAttribute('data-i')]; if (e.target.getAttribute('data-k') === 't') s.t = e.target.value; else s.v = parseInt(e.target.value, 10) || 0; disegnaTel2(); });
      boxScala.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return;
        if (b.hasAttribute('data-piu')) { D.scala.push({ t: 'Risposta ' + (D.scala.length + 1), v: D.scala.length }); disegnaScala('#tss-t' + (D.scala.length - 1)); disegnaTel2(); }
        if (b.hasAttribute('data-togli')) { D.scala.splice(+b.closest('[data-i]').getAttribute('data-i'), 1); disegnaScala('[data-piu]'); disegnaTel2(); }
      });
      disegnaScala();
    }

    sistemaPagine();
    var tolteAvvio = allinea();
    disegnaTutto();
    if (tolteAvvio.length) annuncia('Tolta la condizione della domanda ' + tolteAvvio.join(', ') + '.');
    form.addEventListener('submit', function () {
      document.getElementById('ts-dati-json').value = JSON.stringify({ scala: D.scala, aree: D.aree, profili: D.profili, domande: D.domande, modo: MODO, pagine: PAG });
    });
  }

  // =====================================================================  PASSO 3: le fasce in fila, la barra da 0 a 100
  var boxesF = $$('.ts-fasce-ed');
  var boxProfC = document.getElementById('ts-profili-carte');
  if (PASSO === 3) {
    var perArea = {}; (D.aree || []).forEach(function (a) { perArea[a.k] = a; a.fasce = a.fasce || []; });
    D.fasce = D.fasce || [];
    var listaDi = function (scope) { return scope === 'totale' ? D.fasce : (perArea[scope] ? perArea[scope].fasce : []); };
    var selF = { scope: 'totale', i: 0 };
    // Lo stesso controllo del server (test_fasce_controlla), per vederlo subito.
    var controlla = function (f) {
      if (!f.length) return 'Mancano le fasce: aggiungine almeno una, da 0 a 100.';
      var s = f.slice().sort(function (a, b) { return a.da - b.da || a.a - b.a; }), p = null;
      for (var i = 0; i < s.length; i++) {
        var x = s[i];
        if (x.da > x.a) return 'Nella fascia «' + x.nome + '» «da» (' + x.da + ') è più grande di «a» (' + x.a + ').';
        if (!p && x.da !== 0) return 'La prima fascia deve cominciare da 0 (ora comincia da ' + x.da + ').';
        if (p && x.da <= p.a) return '«' + p.nome + '» e «' + x.nome + '» si sovrappongono: «' + x.nome + '» deve cominciare da ' + (p.a + 1) + '.';
        if (p && x.da > p.a + 1) return 'Fra «' + p.nome + '» e «' + x.nome + '» c\'è un buco: da ' + (p.a + 1) + ' a ' + (x.da - 1) + ' non c\'è nessuna fascia.';
        p = x;
      }
      return p.a !== 100 ? 'L\'ultima fascia deve arrivare a 100 (ora arriva a ' + p.a + ').' : '';
    };
    // La barra: ogni fascia un tratto; i buchi a righe. Decorativa: lo stato lo dice in parole (role=status).
    var copertura = function (lista) {
      var coperti = []; for (var n = 0; n <= 100; n++) coperti[n] = 0;
      lista.forEach(function (f) { for (var n = Math.max(0, f.da); n <= Math.min(100, f.a); n++) coperti[n]++; });
      var s = '<div class="ts-copertura" aria-hidden="true">';
      for (var a = 0; a <= 100;) { if (coperti[a]) { a++; continue; } var b = a; while (b <= 100 && !coperti[b]) b++; s += '<span class="ts-buco" data-sinistra="' + a / 101 * 100 + '" data-larga="' + (b - a) / 101 * 100 + '"></span>'; a = b; }
      lista.forEach(function (f, i) { if (f.a < f.da) return; s += '<span class="ts-tratto' + (i % 2 ? ' ts-tratto-b' : '') + '" data-sinistra="' + f.da / 101 * 100 + '" data-larga="' + (f.a - f.da + 1) / 101 * 100 + '"></span>'; });
      return s + '</div><div class="ts-copertura-numeri" aria-hidden="true"><span>0</span><span>50</span><span>100</span></div>';
    };
    var disegnaFasce = function (box, fuoco) {
      var scope = box.getAttribute('data-scope'), lista = listaDi(scope), e = controlla(lista);
      box.innerHTML = '<div class="ts-carte ts-carte-fila">' + lista.map(function (f, i) {
        return '<div class="ts-carta-d ts-carta-f' + (selF.scope === scope && selF.i === i ? ' ts-carta-sel' : '') + '" role="button" tabindex="0" aria-haspopup="dialog" data-scope="' + h(scope) + '" data-i="' + i + '"><span class="ts-carta-testo"><b>' + h(f.nome) + '</b></span><span class="ts-bollini"><span class="ts-bollino">da ' + h(f.da) + ' a ' + h(f.a) + '</span></span></div>';
      }).join('') + (lista.length ? '' : '<p class="hint">Ancora nessuna fascia: aggiungi la prima.</p>') + '</div>' + copertura(lista)
        + '<p class="ts-fasce-stato ' + (e ? 'ts-avviso' : 'ts-bene') + '" role="status">' + h(e || '✓ Le fasce coprono da 0 a 100 senza buchi.') + '</p>'
        + '<p class="ts-azioni-fasce"><button type="button" class="btn btn-secondary btn-sm" data-piu-f' + (lista.length >= (MAX.fasce || 8) ? ' disabled' : '') + '>+ Aggiungi una fascia</button> '
        + '<button type="button" class="btn btn-secondary btn-sm" data-dividi' + (lista.length ? '' : ' disabled') + '>Dividi da 0 a 100 in parti uguali</button></p>';
      larghezze(box);
      if (fuoco) { var f = box.querySelector(fuoco); if (f) f.focus(); }
    };
    var finF = document.getElementById('ts-fin-fascia'), corpoF = document.getElementById('ts-fascia-corpo');
    var boxDi = function (scope) { return boxesF.filter(function (b) { return b.getAttribute('data-scope') === scope; })[0]; };
    var schedaFascia = function () {
      var f = listaDi(selF.scope)[selF.i]; if (!f) return;
      $('#ts-fin-fascia-t').textContent = (selF.scope === 'totale' ? 'Fascia del totale' : 'Fascia dell\'area «' + perArea[selF.scope].nome + '»');
      corpoF.innerHTML = '<div class="ts-griglia-3"><div class="form-group"><label for="tsf-nome">Nome della fascia</label><input type="text" id="tsf-nome" data-k="nome" maxlength="60" value="' + h(f.nome) + '"></div>'
        + '<div class="form-group"><label for="tsf-da">Da</label><input type="number" id="tsf-da" data-k="da" min="0" max="100" value="' + h(f.da) + '"></div>'
        + '<div class="form-group"><label for="tsf-a">A</label><input type="number" id="tsf-a" data-k="a" min="0" max="100" value="' + h(f.a) + '"></div></div>'
        + '<p class="ts-fasce-stato" role="status" id="tsf-stato"></p>';
      statoF();
    };
    var statoF = function () { var s = document.getElementById('tsf-stato'); if (!s) return; var e = controlla(listaDi(selF.scope)); s.className = 'ts-fasce-stato ' + (e ? 'ts-avviso' : 'ts-bene'); s.textContent = e || '✓ Le fasce coprono da 0 a 100 senza buchi.'; };
    var disegnaTel3 = function () {
      var vista = { totale: function () {
        var f = D.fasce[selF.i]; if (!f) return '<p class="tsv-nota">Tocca una fascia per vedere il suo risultato.</p>';
        var pct = Math.round((f.da + f.a) / 2);
        return occhiello('Il tuo risultato · ' + I.nome) + '<h1 class="tsv-h1">' + h(f.nome) + '</h1><p class="tsv-punti"><b>' + pct + '</b> su 100</p><span class="tsv-barra tsv-barra-ris"><span data-larga="' + pct + '"></span></span>'
          + (vuotoRicco(f.breve) && vuotoRicco(f.testo) ? '<p class="tsv-nota">Il testo di questa fascia si scrive nel passo 4.</p>' : '<div class="tsv-ricco">' + ricco(vuotoRicco(f.breve) ? f.testo : f.breve) + '</div>') + pulsanteTel(f.pulsante_testo);
      } };
      var corpo;
      if (TIPO === 'profili') {
        var p = (D.profili || [])[selF.i];
        corpo = p ? occhiello('Il tuo risultato · ' + I.nome) + '<h1 class="tsv-h1">' + h(p.nome) + '</h1>' + (vuotoRicco(p.breve) && vuotoRicco(p.testo) ? '<p class="tsv-nota">Il testo di questo profilo si scrive nel passo 4.</p>' : '<div class="tsv-ricco">' + ricco(vuotoRicco(p.breve) ? p.testo : p.breve) + '</div>')
          + '<p class="tsv-sotto">Quanto somigli a ogni profilo</p>' + D.profili.map(function (x, i) { var v = i === selF.i ? 60 : Math.round(40 / Math.max(1, D.profili.length - 1)); return '<span class="tsv-area"><span>' + h(x.nome) + '</span><b>' + v + '</b></span><span class="tsv-barra tsv-barra-ris"><span data-larga="' + v + '"></span></span>'; }).join('') : '<p class="tsv-nota">Ancora nessun profilo: aggiungili nel passo 2.</p>';
      } else if (selF.scope === 'totale') corpo = vista.totale();
      else {
        var a = perArea[selF.scope], fa = a && a.fasce[selF.i];
        if (!fa) corpo = '<p class="tsv-nota">Tocca una fascia.</p>';
        else { var pc = Math.round((fa.da + fa.a) / 2); corpo = occhiello('Il tuo risultato · ' + I.nome) + '<p class="tsv-sotto">Le aree</p><span class="tsv-area"><span>' + h(a.nome) + ' · ' + h(fa.nome) + '</span><b>' + pc + '</b></span><span class="tsv-barra tsv-barra-ris"><span data-larga="' + pc + '"></span></span>'
          + (vuotoRicco(fa.breve) && vuotoRicco(fa.testo) ? '<p class="tsv-nota">Il testo di questa fascia dell\'area si scrive nel passo 4.</p>' : '<div class="tsv-ricco">' + ricco(vuotoRicco(fa.breve) ? fa.testo : fa.breve) + '</div>'); }
      }
      telefono({ corpo: corpo });
    };
    if (boxProfC) {
      var disegnaProf = function () { boxProfC.innerHTML = (D.profili || []).map(function (p, i) { return '<div class="ts-carta-d' + (selF.i === i ? ' ts-carta-sel' : '') + '" role="button" tabindex="0" data-prof="' + i + '"><span class="ts-carta-testo"><span class="ts-num">' + (i + 1) + '</span> <b>' + h(p.nome) + '</b></span><span class="ts-bollini"><span class="ts-bollino">' + (i === 0 ? 'Vince in parità' : 'Priorità ' + (i + 1)) + '</span></span></div>'; }).join('') || '<p class="hint">Ancora nessun profilo: aggiungili nel passo 2.</p>'; };
      boxProfC.addEventListener('click', function (e) { var c = e.target.closest('[data-prof]'); if (c) { selF = { scope: 'profili', i: +c.getAttribute('data-prof') }; disegnaProf(); disegnaTel3(); var x = $('[data-prof="' + selF.i + '"]', boxProfC); if (x) x.focus(); } });
      disegnaProf();
    }
    boxesF.forEach(function (box) {
      box.addEventListener('click', function (e) {
        var b = e.target.closest('button, [data-i]'); if (!b) return;
        var scope = box.getAttribute('data-scope'), lista = listaDi(scope);
        if (b.hasAttribute('data-piu-f')) { var ult = lista[lista.length - 1]; lista.push({ k: chiave('f'), nome: 'Fascia ' + (lista.length + 1), da: ult ? Math.min(100, ult.a + 1) : 0, a: 100, breve: '', testo: '', pulsante_testo: '', pulsante_link: '' }); selF = { scope: scope, i: lista.length - 1 }; disegnaFasce(box); disegnaTel3(); schedaFascia(); apri('ts-fin-fascia', '#tsf-nome'); return; }
        if (b.hasAttribute('data-dividi')) { var n = lista.length; lista.forEach(function (f, i) { f.da = Math.round(i * 101 / n); f.a = Math.round((i + 1) * 101 / n) - 1; }); lista[n - 1].a = 100; disegnaFasce(box, '[data-dividi]'); disegnaTel3(); annuncia('Fasce divise in parti uguali.'); return; }
        if (b.hasAttribute('data-i')) { selF = { scope: scope, i: +b.getAttribute('data-i') }; boxesF.forEach(function (x) { disegnaFasce(x); }); disegnaTel3(); schedaFascia(); daDove = null; apri('ts-fin-fascia', '#tsf-nome'); }
      });
      disegnaFasce(box);
    });
    if (finF) {
      corpoF.addEventListener('input', function (e) {
        var f = listaDi(selF.scope)[selF.i], k = e.target.getAttribute('data-k'); if (!f || !k) return;
        f[k] = k === 'nome' ? e.target.value : Math.max(0, Math.min(100, parseInt(e.target.value, 10) || 0));
        var b = boxDi(selF.scope); if (b) disegnaFasce(b);
        statoF(); disegnaTel3();
      });
      finF.addEventListener('click', function (e) {
        if (!e.target.closest('[data-togli-f]')) return;
        var lista = listaDi(selF.scope), nome = (lista[selF.i] || {}).nome;
        lista.splice(selF.i, 1); selF.i = 0;
        finF.close();
        var b = boxDi(selF.scope); if (b) disegnaFasce(b, '[data-piu-f]');
        disegnaTel3(); annuncia('Fascia «' + nome + '» tolta.');
      });
      finF.addEventListener('ts-chiusa', function () { var b = boxDi(selF.scope); if (b) { disegnaFasce(b); var c = b.querySelector('[data-i="' + selF.i + '"]'); if (c) daDove = c; } });
    }
    disegnaTel3();
    form.addEventListener('submit', function () {
      var aree = {}; (D.aree || []).forEach(function (a) { aree[a.k] = a.fasce; });
      var c = document.getElementById('ts-fasce-json'); if (c) c.value = JSON.stringify({ fasce: D.fasce, aree: aree });
    });
  }

  // =====================================================================  PASSO 4: risultati, regole, «A schermo / Email»
  function quill(ta) {
    if (typeof Quill === 'undefined' || ta.getAttribute('data-ricco')) return;
    ta.setAttribute('data-ricco', '1');
    var box = document.createElement('div'); box.className = 'ts-quill';
    ta.parentNode.insertBefore(box, ta.nextSibling);
    var q = new Quill(box, { theme: 'snow', modules: { toolbar: [['bold', 'italic', 'underline'], ['link'], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']] } });
    q.root.innerHTML = ta.value;
    var et = document.querySelector('label[for="' + ta.id + '"]');
    q.root.setAttribute('role', 'textbox'); q.root.setAttribute('aria-multiline', 'true'); if (et) q.root.setAttribute('aria-label', et.textContent);
    q.on('text-change', function () { ta.value = q.root.innerHTML === '<p><br></p>' ? '' : q.root.innerHTML; ta.dispatchEvent(new Event('input', { bubbles: true })); });
    if (et) et.addEventListener('click', function () { q.focus(); });
    ta.hidden = true;
    if (window.pnLink && window.pnLink.quill) window.pnLink.quill(q);
  }
  if (PASSO === 4) {
    var vista = 'schermo', sel = null; // sel: {tipo: 'carta', el} oppure {tipo: 'regola', i}
    var soloEmail = !!(D.dopo && D.dopo.solo_email), emailAttiva = soloEmail || !!(D.dopo && D.dopo.email);
    $$('textarea.ts-ricco').forEach(quill);
    var valore = function (id) { var x = document.getElementById(id); return x ? x.value : ''; };
    var testiCarta = function (c) { var id = c.getAttribute('data-testo'); return { breve: valore(id + '-br'), testo: valore(id), pt: valore(id + '-bt') }; };
    var bolliniCarta = function (c) {
      var t = testiCarta(c);
      var b = function (k, si, no, manca) { var x = c.querySelector('[data-boll="' + k + '"]'); if (!x) return; x.textContent = manca ? no : si; x.classList.toggle('ts-bollino-manca', manca); };
      b('breve', 'Breve', 'Breve: manca', vuotoRicco(t.breve)); b('testo', 'Completa', 'Completa: manca', vuotoRicco(t.testo));
      var p = c.querySelector('[data-boll="pulsante"]'); if (p) p.hidden = !t.pt.trim();
    };
    var risultatoTel = function (o) {
      // o: {titolo, pct|null, breve, testo, pt, area: {nome, fascia, pct}|null}
      if (vista === 'email') {
        return '<div class="tsv-email"><span class="tsv-email-ogg">Il tuo risultato: ' + h(I.nome) + '</span><h1 class="tsv-h1">' + h(I.nome + ': ' + o.titolo) + '</h1>' + (o.pct !== null ? '<p class="tsv-punti"><b>' + o.pct + '</b> su 100</p>' : '')
          + (o.area ? '<span class="tsv-area"><span>' + h(o.area.nome) + ' · ' + h(o.area.fascia) + '</span><b>' + o.area.pct + ' su 100</b></span><span class="tsv-barra tsv-barra-ris"><span data-larga="' + o.area.pct + '"></span></span>' : '')
          + (vuotoRicco(o.testo) && vuotoRicco(o.breve) ? '<p class="tsv-nota">Manca la risposta completa: nell\'email andrà la breve, se c\'è.</p>' : '<div class="tsv-ricco">' + ricco(vuotoRicco(o.testo) ? o.breve : o.testo) + '</div>')
          + pulsanteTel(o.pt) + avvertenzaTel(valore('ts-avvertenza')) + '<p>' + pulsanteTel('Rivedi il risultato', 'tsv-filo') + '</p></div>';
      }
      var s = occhiello('Il tuo risultato · ' + I.nome) + '<h1 class="tsv-h1">' + h(o.titolo) + '</h1>' + (o.pct !== null ? '<p class="tsv-punti"><b>' + o.pct + '</b> su 100</p><span class="tsv-barra tsv-barra-ris"><span data-larga="' + o.pct + '"></span></span>' : '');
      if (o.area) s += '<p class="tsv-sotto">Le aree</p><span class="tsv-area"><span>' + h(o.area.nome) + ' · ' + h(o.area.fascia) + '</span><b>' + o.area.pct + '</b></span><span class="tsv-barra tsv-barra-ris"><span data-larga="' + o.area.pct + '"></span></span>';
      var br = !vuotoRicco(o.breve), co = !vuotoRicco(o.testo);
      if (br) s += '<div class="tsv-ricco tsv-breve">' + ricco(o.breve) + '</div>';
      if (co && (!br || !soloEmail)) s += '<div class="tsv-ricco">' + ricco(o.testo) + '</div>';
      if (!br && !co) s += '<p class="tsv-nota">Ancora nessun testo: scrivilo nella finestra di questa carta.</p>';
      s += pulsanteTel(o.pt) + avvertenzaTel(valore('ts-avvertenza'));
      if (emailAttiva) s += '<div class="tsv-riquadro"><b>' + h(soloEmail ? I.titoloEmail : 'Vuoi ricevere il risultato per email?') + '</b><span class="tsv-campo">Email</span></div>';
      return s;
    };
    var disegnaTel4 = function () {
      var corpo = '<p class="tsv-nota">Tocca una carta per vedere il suo risultato.</p>';
      if (sel && sel.tipo === 'carta') {
        var c = sel.el, t = testiCarta(c), da = +c.getAttribute('data-da'), a = +c.getAttribute('data-a'), pct = isNaN(da) ? null : Math.round((da + a) / 2), tipo = c.getAttribute('data-tipo');
        corpo = risultatoTel({ titolo: tipo === 'area' ? 'Il tuo risultato' : c.getAttribute('data-nome'), pct: tipo === 'totale' ? pct : null, breve: t.breve, testo: t.testo, pt: t.pt, area: tipo === 'area' ? { nome: c.getAttribute('data-area'), fascia: c.getAttribute('data-nome'), pct: pct } : null });
      } else if (sel && sel.tipo === 'regola' && D.regole[sel.i]) {
        var r = D.regole[sel.i];
        corpo = risultatoTel({ titolo: r.nome, pct: null, breve: r.breve, testo: r.testo, pt: r.pulsante_testo, area: null });
      }
      telefono({ su: vista === 'email' ? 'Email del risultato' : 'Il risultato a schermo', corpo: corpo });
    };
    $$('[data-testo]').forEach(function (c) {
      c.addEventListener('click', function () { sel = { tipo: 'carta', el: c }; $$('.ts-carta-sel').forEach(function (x) { x.classList.remove('ts-carta-sel'); }); c.classList.add('ts-carta-sel'); disegnaTel4(); });
      var d = document.getElementById(c.getAttribute('data-testo') + '-fin');
      if (d) d.addEventListener('input', function () { bolliniCarta(c); disegnaTel4(); });
    });
    var avv = document.getElementById('ts-avvertenza');
    if (avv) avv.addEventListener('input', function () { $$('[data-vivo="avvertenza"]').forEach(function (x) { x.textContent = avv.value.trim() || 'Nessuna avvertenza.'; }); disegnaTel4(); });
    $$('[data-vista]').forEach(function (b) { b.addEventListener('click', function () { vista = b.getAttribute('data-vista'); $$('[data-vista]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); disegnaTel4(); }); });

    // ---- le regole: carte e la loro finestra
    var boxReg = document.getElementById('ts-regole-ed'), finR = document.getElementById('ts-fin-regola'), corpoR = document.getElementById('ts-regola-corpo');
    if (boxReg && finR) {
      D.regole = D.regole || [];
      var selR = 0;
      var scopi = [['totale', 'Totale']].concat((D.aree || []).map(function (a) { return ['area:' + a.k, 'Area ' + a.nome]; }));
      var fasceDi = function (cosa) { if (cosa === 'totale') return D.fasce || []; var a = (D.aree || []).filter(function (x) { return 'area:' + x.k === cosa; })[0]; return a ? a.fasce : []; };
      var nomeScopo = function (c) { var s = scopi.filter(function (x) { return x[0] === c.cosa; })[0], f = fasceDi(c.cosa).filter(function (x) { return x.k === c.fascia; })[0]; return (s ? s[1] : '?') + ' in fascia ' + (f ? f.nome : '?'); };
      var disegnaRegole = function (fuoco) {
        boxReg.innerHTML = '<p class="ts-annuncio sr-solo" aria-live="polite"></p><div class="ts-carte ts-carte-fila">' + D.regole.map(function (r, i) {
          return '<div class="ts-carta-d ts-carta-r' + (sel && sel.tipo === 'regola' && sel.i === i ? ' ts-carta-sel' : '') + '" role="button" tabindex="0" aria-haspopup="dialog" data-r="' + i + '"><span class="ts-carta-testo"><span class="ts-num">' + (i + 1) + '</span> <b>' + h(r.nome) + '</b></span>'
            + '<span class="ts-carta-sotto">' + h(r.condizioni.map(nomeScopo).join(r.modo === 'o' ? ' oppure ' : ' e ')) + '</span><span class="ts-bollini"><span class="ts-bollino' + (vuotoRicco(r.breve) ? ' ts-bollino-manca' : '') + '">' + (vuotoRicco(r.breve) ? 'Breve: manca' : 'Breve') + '</span><span class="ts-bollino' + (vuotoRicco(r.testo) ? ' ts-bollino-manca' : '') + '">' + (vuotoRicco(r.testo) ? 'Completa: manca' : 'Completa') + '</span></span></div>';
        }).join('') + '</div>' + (D.regole.length ? '' : '<p class="hint ts-vuoto">Nessuna regola: vale la fascia del totale. Aggiungine una se una combinazione di aree merita un testo suo.</p>')
          + '<button type="button" class="btn btn-secondary btn-sm" data-piu-r' + (D.regole.length >= (MAX.regole || 20) || !(D.fasce || []).length ? ' disabled' : '') + '>+ Aggiungi una regola</button>';
        if (fuoco) { var f = boxReg.querySelector(fuoco); if (f) f.focus(); }
      };
      var schedaRegola = function (fuoco) {
        var r = D.regole[selR]; if (!r) return;
        var p = 'tsr';
        $('#ts-fin-regola-t').textContent = 'Regola ' + (selR + 1);
        corpoR.innerHTML = '<div class="ts-griglia-2"><div class="form-group"><label for="' + p + '-n">Nome della regola (è il titolo del risultato)</label><input type="text" id="' + p + '-n" data-k="nome" maxlength="80" value="' + h(r.nome) + '"></div>'
          + '<div class="form-group"><label for="' + p + '-m">Vale quando</label><select id="' + p + '-m" data-k="modo">' + opz([['e', 'tutte le condizioni sono vere (E)'], ['o', 'almeno una condizione è vera (O)']], r.modo) + '</select></div></div>'
          + '<fieldset class="ts-condizioni"><legend>Condizioni</legend>' + r.condizioni.map(function (c, j) {
            return '<div class="ts-griglia-cond" data-j="' + j + '"><div class="form-group"><label for="' + p + '-c' + j + '">Che cosa</label><select id="' + p + '-c' + j + '" data-c="cosa">' + opz(scopi, c.cosa) + '</select></div>'
              + '<div class="form-group"><label for="' + p + '-f' + j + '">è in fascia</label><select id="' + p + '-f' + j + '" data-c="fascia">' + opz(fasceDi(c.cosa).map(function (f) { return [f.k, f.nome]; }), c.fascia) + '</select></div>'
              + '<button type="button" class="btn btn-danger btn-sm" data-togli-c="' + j + '" aria-label="Togli la condizione ' + (j + 1) + '"' + (r.condizioni.length <= 1 ? ' disabled' : '') + '>Togli</button></div>';
          }).join('') + '<button type="button" class="btn btn-secondary btn-sm" data-piu-c>+ Aggiungi una condizione</button></fieldset>'
          + '<div class="form-group"><label for="' + p + '-b">Risposta breve (a schermo)</label><textarea id="' + p + '-b" data-k="breve" rows="3" class="ts-ricco-r">' + h(r.breve || '') + '</textarea></div>'
          + '<div class="form-group"><label for="' + p + '-t">Risposta completa (per email; a schermo se la breve è vuota)</label><textarea id="' + p + '-t" data-k="testo" rows="4" class="ts-ricco-r">' + h(r.testo) + '</textarea></div>'
          + '<div class="ts-griglia-2 ts-riga-pulsante"><div class="form-group"><label for="' + p + '-bt">Testo del pulsante (facoltativo)</label><input type="text" id="' + p + '-bt" data-k="pulsante_testo" maxlength="80" value="' + h(r.pulsante_testo) + '"></div>'
          + '<div class="form-group"><label for="' + p + '-bl">Link del pulsante</label><input type="text" id="' + p + '-bl" data-k="pulsante_link" maxlength="500" value="' + h(r.pulsante_link) + '" placeholder="per esempio: /contatti/" data-link-sito></div></div>';
        $$('textarea.ts-ricco-r', corpoR).forEach(quill);
        $$('[data-sposta-r]', finR).forEach(function (b) { var d = +b.getAttribute('data-sposta-r'); b.disabled = d < 0 ? selR === 0 : selR === D.regole.length - 1; });
        if (fuoco) { var f = corpoR.querySelector(fuoco); if (f) f.focus(); }
      };
      var apriRegola = function (i, fuoco) { selR = i; sel = { tipo: 'regola', i: i }; $$('.ts-carta-sel').forEach(function (x) { x.classList.remove('ts-carta-sel'); }); disegnaRegole(); schedaRegola(); disegnaTel4(); apri('ts-fin-regola', fuoco || '#tsr-n'); };
      corpoR.addEventListener('input', function (e) {
        var r = D.regole[selR], k = e.target.getAttribute('data-k'); if (!r || !k || k === 'modo') return;
        r[k] = e.target.value; disegnaTel4();
      });
      corpoR.addEventListener('change', function (e) {
        var r = D.regole[selR]; if (!r) return;
        var k = e.target.getAttribute('data-k'), c = e.target.getAttribute('data-c');
        if (k === 'modo') r.modo = e.target.value;
        if (k === 'pulsante_link') r.pulsante_link = e.target.value;
        if (c) {
          var cond = r.condizioni[+e.target.closest('[data-j]').getAttribute('data-j')];
          cond[c] = e.target.value;
          if (c === 'cosa') { var f = fasceDi(cond.cosa); cond.fascia = f.length ? f[0].k : ''; schedaRegola('#' + e.target.id); }
        }
      });
      corpoR.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b || b.closest('.ql-toolbar')) return;
        var r = D.regole[selR]; if (!r) return;
        if (b.hasAttribute('data-piu-c')) { r.condizioni.push({ cosa: 'totale', fascia: (D.fasce[0] || {}).k || '' }); schedaRegola('#tsr-c' + (r.condizioni.length - 1)); return; }
        if (b.hasAttribute('data-togli-c')) { r.condizioni.splice(+b.getAttribute('data-togli-c'), 1); schedaRegola('#tsr-c0'); }
      });
      finR.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return;
        if (b.hasAttribute('data-togli-r')) { var n = selR + 1; D.regole.splice(selR, 1); sel = null; finR.close(); disegnaRegole('[data-piu-r]'); disegnaTel4(); annuncia('Regola ' + n + ' tolta.', boxReg); return; }
        if (b.hasAttribute('data-sposta-r') && sposta(D.regole, selR, +b.getAttribute('data-sposta-r'))) { selR += +b.getAttribute('data-sposta-r'); sel = { tipo: 'regola', i: selR }; schedaRegola(); disegnaRegole(); b.focus(); annuncia('Regola spostata: ora è la ' + (selR + 1) + '.', boxReg); }
      });
      finR.addEventListener('ts-chiusa', function () { disegnaRegole(); var c = $('[data-r="' + selR + '"]', boxReg); if (c) daDove = c; });
      boxReg.addEventListener('click', function (e) {
        if (e.target.closest('[data-piu-r]')) { D.regole.push({ k: chiave('r'), nome: 'Regola ' + (D.regole.length + 1), modo: 'e', condizioni: [{ cosa: 'totale', fascia: (D.fasce[0] || {}).k || '' }], breve: '', testo: '', pulsante_testo: '', pulsante_link: '' }); apriRegola(D.regole.length - 1); return; }
        var c = e.target.closest('[data-r]'); if (c) apriRegola(+c.getAttribute('data-r'));
      });
      disegnaRegole();
      form.addEventListener('submit', function () {
        if (finR.open) { var bl = document.getElementById('tsr-bl'); if (bl && D.regole[selR]) D.regole[selR].pulsante_link = bl.value; }
        document.getElementById('ts-regole-json').value = JSON.stringify({ regole: D.regole });
      });
    }
    var prima = $('[data-testo]');
    if (prima) { sel = { tipo: 'carta', el: prima }; prima.classList.add('ts-carta-sel'); }
    disegnaTel4();
  }

  // Un indirizzo con &apri=<finestra> la apre subito (per esempio un link che porta alle domande sulla persona).
  var apriSubito = (new URLSearchParams(location.search)).get('apri') || '';
  if (/^ts-fin-[a-z]+$/.test(apriSubito)) {
    if (apriSubito === 'ts-fin-domanda') { var c0 = document.querySelector('#ts-lavagna .ts-carta-d'); if (c0) c0.click(); }
    else if (apriSubito === 'ts-fin-fascia') { var f0 = document.querySelector('.ts-carta-f'); if (f0) f0.click(); }
    else if (apriSubito === 'ts-fin-regola') { var r0 = document.querySelector('[data-r]'); if (r0) r0.click(); }
    else apri(apriSubito);
  }

  // =====================================================================  PASSO 5: la fine del test
  if (PASSO === 5) {
    var disegna5 = function () {
      var c = function (n) { var x = form.querySelector('[name="' + n + '"]'); return x ? x : {}; };
      var solo = !!c('solo_email').checked, em = !!c('email').checked, lista = c('lista'), lid = +(lista.value || 0);
      var listaNome = lid && lista.options ? lista.options[lista.selectedIndex].textContent : '';
      var vivo = function (k, v) { $$('[data-vivo="' + k + '"]').forEach(function (x) { x.textContent = v; }); };
      vivo('email', solo ? 'Il risultato completo solo per email.' : (em ? 'Il risultato si può ricevere per email.' : 'Niente email: il risultato solo a schermo.'));
      vivo('email-boll', solo || em ? 'Acceso' : 'Spento');
      vivo('lista', lid ? 'Lista: ' + listaNome : 'Nessuna lista: niente casella per la newsletter.');
      var esempio = (D.fasce && D.fasce[0]) || (D.profili && D.profili[0]) || null;
      var s = occhiello('Il tuo risultato · ' + I.nome) + '<h1 class="tsv-h1">' + h(esempio ? esempio.nome : 'Il tuo risultato') + '</h1>'
        + (esempio && !vuotoRicco(esempio.breve) ? '<div class="tsv-ricco tsv-breve">' + ricco(esempio.breve) + '</div>' : '<p class="tsv-nota">Qui la risposta breve del risultato (passo 4).</p>')
        + (!solo && esempio && !vuotoRicco(esempio.testo) ? '<div class="tsv-ricco">' + ricco(esempio.testo) + '</div>' : '');
      if (solo || em) {
        s += '<div class="tsv-riquadro"><b>' + h(solo ? I.titoloEmail : 'Vuoi ricevere il risultato per email?') + '</b>' + (solo ? '<span class="tsv-nota">Per email il risultato completo.</span>' : '')
          + '<span class="tsv-campo">Email</span><span class="tsv-spunta"><span class="tsv-casella" aria-hidden="true"></span>' + h((c('consenso').value || '').trim()) + '</span>'
          + (lid ? '<span class="tsv-spunta"><span class="tsv-casella" aria-hidden="true"></span>' + h((c('iscrizione').value || '').trim()) + '</span>' : '')
          + '<span class="tsv-spunta"><span class="tsv-casella" aria-hidden="true"></span>Sono una persona</span>' + pulsanteTel('Mandami il risultato') + '</div>';
      } else s += '<p class="tsv-nota">Nessun riquadro dell\'email: il test finisce con il risultato a schermo.</p>';
      telefono({ su: 'La fine del test', corpo: s });
    };
    form.addEventListener('input', disegna5);
    form.addEventListener('change', disegna5);
    disegna5();
  }
})();
