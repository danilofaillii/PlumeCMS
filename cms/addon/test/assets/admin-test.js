// Addon Test e questionari, pannello: gli editor dei passi 2 (aree, profili, scala, domande), 3 (fasce) e 4 (regole e
// testi ricchi). Ogni editor tiene il suo modello in memoria e, all'invio del modulo, lo scrive in JSON in un campo
// nascosto (campi-protetti.js lo manda in base64). Il server controlla tutto di nuovo (test_normalizza): qui si aiuta
// soltanto chi scrive. Nessun gestore in linea: tutto con addEventListener. Ogni campo ha la sua etichetta.
(function () {
  'use strict';
  var el = document.getElementById('ts-iniziali');
  if (!el) return;
  var I = JSON.parse(el.textContent || '{}');
  var D = I.dati || {}, TIPO = I.tipo, MAX = I.max || {};
  var form = document.getElementById('ts-form');

  function h(s) { return String(s === undefined || s === null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function chiave(p) { var a = new Uint8Array(4); (window.crypto || {}).getRandomValues ? crypto.getRandomValues(a) : a.forEach(function (x, i) { a[i] = Math.random() * 256; }); return p + Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join(''); }
  function opz(lista, sc) { return lista.map(function (x) { return '<option value="' + h(x[0]) + '"' + (String(x[0]) === String(sc) ? ' selected' : '') + '>' + h(x[1]) + '</option>'; }).join(''); }
  function annuncia(box, testo) { var s = box.querySelector('.ts-annuncio'); if (s) s.textContent = testo; }
  function sposta(lista, i, d) { var j = i + d; if (j < 0 || j >= lista.length) return false; var x = lista.splice(i, 1)[0]; lista.splice(j, 0, x); return true; }
  function sortable(box, sel, lista, poi) {
    if (typeof Sortable === 'undefined' || !box) return;
    new Sortable(box, { handle: '.ts-maniglia', draggable: sel, animation: 120, onEnd: function (e) { if (e.oldIndex === e.newIndex) return; var x = lista.splice(e.oldIndex, 1)[0]; lista.splice(e.newIndex, 0, x); poi(); } });
  }
  function testoPiu(x) { return x.length > 60 ? x.slice(0, 57) + '...' : x; }

  // =====================================================================  PASSO 2
  var boxDom = document.getElementById('ts-domande-ed');
  if (boxDom) {
    D.domande = D.domande || []; D.aree = D.aree || []; D.profili = D.profili || []; D.scala = D.scala || [];
    var boxAree = document.getElementById('ts-aree-ed'), boxProf = document.getElementById('ts-profili-ed'), boxScala = document.getElementById('ts-scala-ed');

    // ---- elenco semplice di nomi (aree, profili)
    function elencoNomi(box, lista, cosa, pref, max) {
      if (!box) return;
      function disegna(fuoco) {
        box.innerHTML = '<p class="ts-annuncio sr-solo" aria-live="polite"></p><ol class="ts-nomi">' + lista.map(function (a, i) {
          return '<li class="ts-nome" data-i="' + i + '"><span class="ts-maniglia" aria-hidden="true" title="Trascina">&#8942;&#8942;</span>'
            + '<label class="sr-solo" for="tsn-' + pref + i + '">Nome ' + cosa + ' ' + (i + 1) + '</label><input type="text" id="tsn-' + pref + i + '" data-k="nome" maxlength="60" value="' + h(a.nome) + '">'
            + '<button type="button" class="btn btn-secondary btn-sm" data-su aria-label="Sposta su ' + h(a.nome) + '"' + (i === 0 ? ' disabled' : '') + '>&uarr;</button>'
            + '<button type="button" class="btn btn-secondary btn-sm" data-giu aria-label="Sposta giù ' + h(a.nome) + '"' + (i === lista.length - 1 ? ' disabled' : '') + '>&darr;</button>'
            + '<button type="button" class="btn btn-danger btn-sm" data-togli>Togli</button></li>';
        }).join('') + '</ol>' + (lista.length ? '' : '<p class="hint ts-vuoto">Ancora nessun' + (cosa === 'dell\'area' ? '\'area' : ' profilo') + ': aggiungine uno.</p>')
          + '<button type="button" class="btn btn-secondary btn-sm" data-piu' + (lista.length >= max ? ' disabled' : '') + '>+ Aggiungi ' + (cosa === 'dell\'area' ? 'un\'area' : 'un profilo') + '</button>';
        sortable(box.querySelector('.ts-nomi'), '.ts-nome', lista, function () { disegna(); ridisegnaDomande(); });
        if (fuoco) { var f = box.querySelector(fuoco); if (f) f.focus(); }
      }
      box.addEventListener('input', function (e) { var li = e.target.closest('[data-i]'); if (li && e.target.dataset.k === 'nome') { lista[+li.dataset.i].nome = e.target.value; ridisegnaDomande(true); } });
      box.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return;
        var li = b.closest('[data-i]'), i = li ? +li.dataset.i : -1;
        if (b.hasAttribute('data-piu')) { lista.push({ k: chiave(pref), nome: cosa === 'dell\'area' ? 'Area ' + (lista.length + 1) : 'Profilo ' + (lista.length + 1), fasce: undefined }); disegna('#tsn-' + pref + (lista.length - 1)); ridisegnaDomande(); return; }
        if (b.hasAttribute('data-togli')) { var nome = lista[i].nome; lista.splice(i, 1); disegna('[data-piu]'); ridisegnaDomande(); annuncia(box, nome + ' tolto.'); return; }
        if (b.hasAttribute('data-su') && sposta(lista, i, -1)) { disegna('#tsn-' + pref + (i - 1)); ridisegnaDomande(); annuncia(box, 'Spostato su.'); }
        if (b.hasAttribute('data-giu') && sposta(lista, i, 1)) { disegna('#tsn-' + pref + (i + 1)); ridisegnaDomande(); annuncia(box, 'Spostato giù.'); }
      });
      disegna();
    }
    if (TIPO === 'aree') elencoNomi(boxAree, D.aree, 'dell\'area', 'a', MAX.aree || 12);
    if (TIPO === 'profili') elencoNomi(boxProf, D.profili, 'del profilo', 'p', MAX.profili || 12);

    // ---- la scala
    if (boxScala) {
      var disegnaScala = function (fuoco) {
        boxScala.innerHTML = '<table class="ts-scala" data-no-schede><thead><tr><th scope="col">Risposta</th><th scope="col">Punti</th><th scope="col"><span class="sr-solo">Togli</span></th></tr></thead><tbody>' + D.scala.map(function (s, i) {
          return '<tr data-i="' + i + '"><td><label class="sr-solo" for="tss-t' + i + '">Risposta ' + (i + 1) + '</label><input type="text" id="tss-t' + i + '" data-k="t" maxlength="40" value="' + h(s.t) + '"></td>'
            + '<td><label class="sr-solo" for="tss-v' + i + '">Punti della risposta ' + (i + 1) + '</label><input type="number" id="tss-v' + i + '" data-k="v" min="-100" max="100" value="' + h(s.v) + '"></td>'
            + '<td><button type="button" class="btn btn-danger btn-sm" data-togli' + (D.scala.length <= 2 ? ' disabled' : '') + '>Togli</button></td></tr>';
        }).join('') + '</tbody></table><button type="button" class="btn btn-secondary btn-sm" data-piu' + (D.scala.length >= 7 ? ' disabled' : '') + '>+ Aggiungi una risposta alla scala</button>';
        if (fuoco) { var f = boxScala.querySelector(fuoco); if (f) f.focus(); }
      };
      boxScala.addEventListener('input', function (e) { var r = e.target.closest('[data-i]'); if (!r) return; var s = D.scala[+r.dataset.i]; if (e.target.dataset.k === 't') s.t = e.target.value; else s.v = parseInt(e.target.value, 10) || 0; });
      boxScala.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return;
        if (b.hasAttribute('data-piu')) { D.scala.push({ t: 'Risposta ' + (D.scala.length + 1), v: D.scala.length }); disegnaScala('#tss-t' + (D.scala.length - 1)); }
        if (b.hasAttribute('data-togli')) { D.scala.splice(+b.closest('[data-i]').dataset.i, 1); disegnaScala('[data-piu]'); }
      });
      disegnaScala();
    }

    // ---- le domande
    var tipiDom = Object.keys(I.tipiDomanda || {}).filter(function (k) { return TIPO !== 'profili' || k === 'scelta' || k === 'sino'; }).map(function (k) { return [k, I.tipiDomanda[k]]; });
    function opzioniDi(q) {
      if (q.tipo === 'scala') return D.scala.map(function (s, i) { return { k: 's' + i, t: s.t }; });
      if (q.tipo === 'cursore') return [];
      return q.opzioni || [];
    }
    function assicuraOpzioni(q) {
      if (q.tipo === 'sino') {
        var per = {}; (q.opzioni || []).forEach(function (o) { per[o.k] = o; });
        q.opzioni = [{ k: 'si', t: 'Sì', punti: per.si ? per.si.punti : 1, profili: per.si ? per.si.profili || {} : {} }, { k: 'no', t: 'No', punti: per.no ? per.no.punti : 0, profili: per.no ? per.no.profili || {} : {} }];
      } else if (q.tipo === 'scelta') {
        q.opzioni = (q.opzioni || []).filter(function (o) { return o.k !== 'si' && o.k !== 'no' || q.opzioni.length > 2; });
        while (q.opzioni.length < 2) q.opzioni.push({ k: chiave('o'), t: '', punti: q.opzioni.length, profili: {} });
      }
      q.opzioni = q.opzioni || [];
      q.opzioni.forEach(function (o) { o.profili = o.profili || {}; });
    }
    // Le condizioni si appoggiano solo a domande che vengono prima: dopo uno spostamento quelle rotte si tolgono e si dice.
    function controllaCondizioni() {
      var prima = {}, tolte = [];
      D.domande.forEach(function (q, i) {
        if (q.condizione && (!prima[q.condizione.domanda] || prima[q.condizione.domanda].tipo === 'cursore')) { q.condizione = null; tolte.push(i + 1); }
        prima[q.k] = q;
      });
      return tolte;
    }
    function cardDomanda(q, i) {
      var p = 'tsd' + i, s = '<li class="ts-dom" data-i="' + i + '"><div class="ts-dom-testa"><span class="ts-maniglia" aria-hidden="true" title="Trascina">&#8942;&#8942;</span><b>Domanda ' + (i + 1) + '</b>'
        + (q.condizione ? ' <span class="badge badge-scheduled">condizionale</span>' : '')
        + '<span class="ts-dom-bottoni"><button type="button" class="btn btn-secondary btn-sm" data-su aria-label="Sposta su la domanda ' + (i + 1) + '"' + (i === 0 ? ' disabled' : '') + '>&uarr;</button>'
        + '<button type="button" class="btn btn-secondary btn-sm" data-giu aria-label="Sposta giù la domanda ' + (i + 1) + '"' + (i === D.domande.length - 1 ? ' disabled' : '') + '>&darr;</button>'
        + '<button type="button" class="btn btn-danger btn-sm" data-togli aria-label="Togli la domanda ' + (i + 1) + '">Togli</button></span></div>'
        + '<div class="form-group"><label for="' + p + '-t">Testo della domanda</label><input type="text" id="' + p + '-t" data-k="testo" maxlength="400" value="' + h(q.testo) + '" placeholder="per esempio: Ti svegli stanco?"></div>'
        + '<div class="form-group"><label for="' + p + '-a">Aiuto sotto la domanda (facoltativo)</label><input type="text" id="' + p + '-a" data-k="aiuto" maxlength="300" value="' + h(q.aiuto || '') + '"></div>'
        + '<div class="ts-dom-riga"><div class="form-group"><label for="' + p + '-tipo">Tipo di risposta</label><select id="' + p + '-tipo" data-k="tipo">' + opz(tipiDom, q.tipo) + '</select></div>';
      if (TIPO === 'aree') s += '<div class="form-group"><label for="' + p + '-area">Area</label><select id="' + p + '-area" data-k="area">' + (D.aree.length ? opz(D.aree.map(function (a) { return [a.k, a.nome]; }), q.area) : '<option value="">Aggiungi prima un\'area</option>') + '</select></div>';
      s += '<div class="form-group"><label for="' + p + '-peso">Peso</label><input type="number" id="' + p + '-peso" data-k="peso" min="0" max="10" step="0.5" value="' + h(q.peso === undefined ? 1 : q.peso) + '"></div></div>'
        + '<div class="ts-dom-riga ts-caselle">' + (TIPO !== 'profili' ? '<label class="nl-casella"><input type="checkbox" data-k="invertita"' + (q.invertita ? ' checked' : '') + '> Invertita (punti al contrario)</label>' : '')
        + '<label class="nl-casella"><input type="checkbox" data-k="obbligatoria"' + (q.obbligatoria !== false ? ' checked' : '') + '> Obbligatoria</label></div>';
      if (q.tipo === 'scelta' || q.tipo === 'sino') {
        s += '<fieldset class="ts-opzioni"><legend>Risposte</legend><div class="ad-tabella"><table data-no-schede><thead><tr><th scope="col">Risposta</th>'
          + (TIPO === 'profili' ? D.profili.map(function (pr) { return '<th scope="col">Punti a ' + h(pr.nome) + '</th>'; }).join('') : '<th scope="col">Punti</th>')
          + '<th scope="col"><span class="sr-solo">Togli</span></th></tr></thead><tbody>' + q.opzioni.map(function (o, j) {
            var r = '<tr data-j="' + j + '"><td><label class="sr-solo" for="' + p + '-o' + j + '">Risposta ' + (j + 1) + '</label><input type="text" id="' + p + '-o' + j + '" data-o="t" maxlength="200" value="' + h(o.t) + '"' + (q.tipo === 'sino' ? ' readonly' : '') + '></td>';
            if (TIPO === 'profili') r += D.profili.map(function (pr) { return '<td><label class="sr-solo" for="' + p + '-o' + j + '-' + pr.k + '">Punti di «' + h(o.t) + '» al profilo ' + h(pr.nome) + '</label><input type="number" id="' + p + '-o' + j + '-' + pr.k + '" data-o="profilo" data-p="' + h(pr.k) + '" min="-20" max="20" value="' + h(o.profili[pr.k] || 0) + '"></td>'; }).join('');
            else r += '<td><label class="sr-solo" for="' + p + '-o' + j + '-p">Punti di «' + h(o.t) + '»</label><input type="number" id="' + p + '-o' + j + '-p" data-o="punti" min="-100" max="100" value="' + h(o.punti || 0) + '"></td>';
            return r + '<td>' + (q.tipo === 'scelta' ? '<button type="button" class="btn btn-danger btn-sm" data-togli-o="' + j + '"' + (q.opzioni.length <= 2 ? ' disabled' : '') + '>Togli</button>' : '') + '</td></tr>';
          }).join('') + '</tbody></table></div>' + (TIPO === 'profili' && !D.profili.length ? '<p class="hint">Aggiungi prima i profili qui sopra: poi dai i punti a ogni risposta.</p>' : '')
          + (q.tipo === 'scelta' ? '<button type="button" class="btn btn-secondary btn-sm" data-piu-o' + (q.opzioni.length >= (MAX.opzioni || 12) ? ' disabled' : '') + '>+ Aggiungi una risposta</button>' : '') + '</fieldset>';
      } else if (q.tipo === 'scala') s += '<p class="hint">Risposte della scala: ' + h(D.scala.map(function (x) { return x.t + ' ' + x.v; }).join(', ')) + (q.invertita ? ' (invertite)' : '') + '.</p>';
      else s += '<p class="hint">Un cursore da 0 a 10: i punti sono il numero scelto' + (q.invertita ? ' al contrario (10 meno il numero)' : '') + '.</p>';
      // Condizione
      var padri = D.domande.slice(0, i).filter(function (x) { return x.tipo !== 'cursore'; });
      s += '<div class="ts-cond"><div class="form-group"><label for="' + p + '-c">Mostra questa domanda</label><select id="' + p + '-c" data-k="cond">'
        + opz([['', 'Sempre']].concat(padri.map(function (x) { return [x.k, 'Solo se la domanda ' + (D.domande.indexOf(x) + 1) + ' (' + testoPiu(x.testo || 'senza testo') + ') ha risposta...']; })), q.condizione ? q.condizione.domanda : '') + '</select>'
        + (i === 0 ? '<div class="hint">La prima domanda compare sempre.</div>' : '') + '</div>';
      if (q.condizione) {
        var padre = D.domande.filter(function (x) { return x.k === q.condizione.domanda; })[0];
        s += '<fieldset class="ts-valori"><legend>Una di queste risposte</legend>' + opzioniDi(padre || {}).map(function (o, j) {
          return '<label class="nl-casella"><input type="checkbox" data-val="' + h(o.k) + '"' + (q.condizione.valori.indexOf(o.k) >= 0 ? ' checked' : '') + '> ' + h(o.t || ('Risposta ' + (j + 1))) + '</label>';
        }).join('') + (q.condizione.valori.length ? '' : '<p class="errore-campo" role="alert">Spunta almeno una risposta: senza, la condizione non vale e si toglie al salvataggio.</p>') + '</fieldset>';
      }
      return s + '</div></li>';
    }
    function ridisegnaDomande(soloTesti, fuoco) {
      if (soloTesti) { /* i nomi di aree e profili compaiono nelle tendine: si ridisegna lasciando il fuoco dov'è */ var att = document.activeElement && document.activeElement.id; disegnaDomande(att ? '#' + att : null, true); return; }
      disegnaDomande(fuoco);
    }
    function disegnaDomande(fuoco, senzaScorrere) {
      D.domande.forEach(assicuraOpzioni);
      boxDom.innerHTML = '<p class="ts-annuncio sr-solo" aria-live="polite"></p><ol class="ts-domande">' + D.domande.map(cardDomanda).join('') + '</ol>'
        + (D.domande.length ? '' : '<p class="hint ts-vuoto">Ancora nessuna domanda: aggiungi la prima con il pulsante qui sotto.</p>')
        + '<button type="button" class="btn btn-secondary" data-piu-d' + (D.domande.length >= (MAX.domande || 80) ? ' disabled' : '') + '>+ Aggiungi una domanda</button> <span class="hint">' + D.domande.length + ' domande</span>';
      sortable(boxDom.querySelector('.ts-domande'), '.ts-dom', D.domande, function () { var t = controllaCondizioni(); disegnaDomande(); if (t.length) annuncia(boxDom, 'Tolta la condizione della domanda ' + t.join(', ') + ': ora la domanda da cui dipendeva viene dopo.'); });
      if (fuoco) { var f = boxDom.querySelector(fuoco) || document.querySelector(fuoco); if (f && f !== document.activeElement) f.focus({ preventScroll: !!senzaScorrere }); }
    }
    boxDom.addEventListener('input', function (e) {
      var li = e.target.closest('[data-i]'); if (!li) return;
      var q = D.domande[+li.dataset.i], k = e.target.dataset.k, o = e.target.dataset.o;
      if (k === 'testo' || k === 'aiuto') q[k] = e.target.value;
      if (k === 'peso') q.peso = parseFloat(e.target.value) || 0;
      if (o) {
        var opt = q.opzioni[+e.target.closest('[data-j]').dataset.j];
        if (o === 't') opt.t = e.target.value;
        if (o === 'punti') opt.punti = parseInt(e.target.value, 10) || 0;
        if (o === 'profilo') opt.profili[e.target.dataset.p] = parseInt(e.target.value, 10) || 0;
      }
    });
    boxDom.addEventListener('change', function (e) {
      var li = e.target.closest('[data-i]'); if (!li) return;
      var i = +li.dataset.i, q = D.domande[i], k = e.target.dataset.k;
      if (k === 'tipo') { q.tipo = e.target.value; if (q.tipo === 'sino' || q.tipo === 'scelta') q.opzioni = q.tipo === 'scelta' && q.opzioni && q.opzioni.length > 2 ? q.opzioni : []; controllaCondizioni(); D.domande.forEach(function (x) { if (x.condizione && x.condizione.domanda === q.k) x.condizione.valori = []; }); disegnaDomande('#' + e.target.id); return; }
      if (k === 'area') q.area = e.target.value;
      if (k === 'invertita' || k === 'obbligatoria') q[k] = e.target.checked;
      if (k === 'cond') { q.condizione = e.target.value ? { domanda: e.target.value, valori: [] } : null; disegnaDomande('#' + e.target.id); return; }
      if (e.target.dataset.val !== undefined) {
        var v = e.target.dataset.val, l = q.condizione.valori, at = l.indexOf(v);
        if (e.target.checked && at < 0) l.push(v); if (!e.target.checked && at >= 0) l.splice(at, 1);
        var ide = e.target.closest('.ts-valori'); var er = ide && ide.querySelector('.errore-campo'); if (er && l.length) er.remove();
      }
    });
    boxDom.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      if (b.hasAttribute('data-piu-d')) { D.domande.push({ k: chiave('d'), testo: '', aiuto: '', tipo: TIPO === 'profili' ? 'scelta' : 'scala', peso: 1, invertita: false, obbligatoria: true, area: D.aree.length ? D.aree[0].k : '', opzioni: [], condizione: null }); disegnaDomande('#tsd' + (D.domande.length - 1) + '-t'); return; }
      var li = b.closest('[data-i]'); if (!li) return;
      var i = +li.dataset.i, q = D.domande[i];
      if (b.hasAttribute('data-togli')) { D.domande.splice(i, 1); var t = controllaCondizioni(); disegnaDomande('[data-piu-d]'); annuncia(boxDom, 'Domanda ' + (i + 1) + ' tolta.' + (t.length ? ' Tolta anche la condizione della domanda ' + t.join(', ') + '.' : '')); return; }
      if (b.hasAttribute('data-su') && sposta(D.domande, i, -1)) { var t1 = controllaCondizioni(); disegnaDomande('#tsd' + (i - 1) + '-t'); annuncia(boxDom, 'Domanda spostata al posto ' + i + '.' + (t1.length ? ' Tolta la condizione della domanda ' + t1.join(', ') + '.' : '')); return; }
      if (b.hasAttribute('data-giu') && sposta(D.domande, i, 1)) { var t2 = controllaCondizioni(); disegnaDomande('#tsd' + (i + 1) + '-t'); annuncia(boxDom, 'Domanda spostata al posto ' + (i + 2) + '.' + (t2.length ? ' Tolta la condizione della domanda ' + t2.join(', ') + '.' : '')); return; }
      if (b.hasAttribute('data-piu-o')) { q.opzioni.push({ k: chiave('o'), t: '', punti: q.opzioni.length, profili: {} }); disegnaDomande('#tsd' + i + '-o' + (q.opzioni.length - 1)); return; }
      if (b.hasAttribute('data-togli-o')) { q.opzioni.splice(+b.getAttribute('data-togli-o'), 1); disegnaDomande('#tsd' + i + '-t'); }
    });
    disegnaDomande();
    form.addEventListener('submit', function () {
      document.getElementById('ts-dati-json').value = JSON.stringify({ scala: D.scala, aree: D.aree, profili: D.profili, domande: D.domande });
    });
  }

  // =====================================================================  PASSO 3: le fasce
  var boxFasce = document.getElementById('ts-fasce-ed'), boxAreeF = document.querySelectorAll('.ts-fasce-area');
  if (boxFasce || boxAreeF.length) {
    var perArea = {}; (D.aree || []).forEach(function (a) { perArea[a.k] = a; });
    // Lo stesso controllo del server (test_fasce_controlla), per vederlo subito.
    function controlla(f) {
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
    }
    function editorFasce(box, lista) {
      var pref = 'tsf-' + (box.dataset.scope || 'x');
      function stato() {
        var e = controlla(lista), s = box.querySelector('.ts-fasce-stato');
        s.className = 'ts-fasce-stato ' + (e ? 'ts-avviso' : 'ts-bene');
        s.textContent = e || '✓ Le fasce coprono da 0 a 100 senza buchi.';
      }
      function disegna(fuoco) {
        box.innerHTML = '<div class="ad-tabella"><table class="ts-fasce" data-no-schede><thead><tr><th scope="col">Nome della fascia</th><th scope="col">Da</th><th scope="col">A</th><th scope="col"><span class="sr-solo">Togli</span></th></tr></thead><tbody>' + lista.map(function (f, i) {
          return '<tr data-i="' + i + '"><td><label class="sr-solo" for="' + pref + i + '-n">Nome della fascia ' + (i + 1) + '</label><input type="text" id="' + pref + i + '-n" data-k="nome" maxlength="60" value="' + h(f.nome) + '"></td>'
            + '<td><label class="sr-solo" for="' + pref + i + '-da">Da (fascia ' + (i + 1) + ')</label><input type="number" id="' + pref + i + '-da" data-k="da" min="0" max="100" value="' + h(f.da) + '"></td>'
            + '<td><label class="sr-solo" for="' + pref + i + '-a">A (fascia ' + (i + 1) + ')</label><input type="number" id="' + pref + i + '-a" data-k="a" min="0" max="100" value="' + h(f.a) + '"></td>'
            + '<td><button type="button" class="btn btn-danger btn-sm" data-togli>Togli</button></td></tr>';
        }).join('') + '</tbody></table></div><p class="ts-fasce-stato" role="status"></p>'
          + '<button type="button" class="btn btn-secondary btn-sm" data-piu' + (lista.length >= (MAX.fasce || 8) ? ' disabled' : '') + '>+ Aggiungi una fascia</button> '
          + '<button type="button" class="btn btn-secondary btn-sm" data-dividi' + (lista.length ? '' : ' disabled') + '>Dividi da 0 a 100 in parti uguali</button>';
        stato();
        if (fuoco) { var f = box.querySelector(fuoco); if (f) f.focus(); }
      }
      box.addEventListener('input', function (e) { var r = e.target.closest('[data-i]'); if (!r) return; var f = lista[+r.dataset.i], k = e.target.dataset.k; f[k] = k === 'nome' ? e.target.value : Math.max(0, Math.min(100, parseInt(e.target.value, 10) || 0)); stato(); });
      box.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return;
        if (b.hasAttribute('data-piu')) { var ult = lista[lista.length - 1]; lista.push({ k: chiave('f'), nome: 'Fascia ' + (lista.length + 1), da: ult ? Math.min(100, ult.a + 1) : 0, a: 100, testo: '', pulsante_testo: '', pulsante_link: '' }); disegna('#' + pref + (lista.length - 1) + '-n'); }
        if (b.hasAttribute('data-togli')) { lista.splice(+b.closest('[data-i]').dataset.i, 1); disegna('[data-piu]'); }
        if (b.hasAttribute('data-dividi')) { var n = lista.length; lista.forEach(function (f, i) { f.da = Math.round(i * 101 / n); f.a = Math.round((i + 1) * 101 / n) - 1; }); lista[n - 1].a = 100; disegna('[data-dividi]'); }
      });
      disegna();
    }
    D.fasce = D.fasce || [];
    if (boxFasce) editorFasce(boxFasce, D.fasce);
    boxAreeF.forEach(function (b) { var a = perArea[b.dataset.scope]; if (a) { a.fasce = a.fasce || []; editorFasce(b, a.fasce); } });
    form.addEventListener('submit', function () {
      var aree = {}; (D.aree || []).forEach(function (a) { aree[a.k] = a.fasce; });
      document.getElementById('ts-fasce-json').value = JSON.stringify({ fasce: D.fasce, aree: aree });
    });
  }

  // =====================================================================  PASSO 4: testi ricchi e regole
  function ricco(ta) {
    if (typeof Quill === 'undefined' || ta.dataset.ricco) return;
    ta.dataset.ricco = '1';
    var box = document.createElement('div'); box.className = 'ts-quill';
    ta.parentNode.insertBefore(box, ta.nextSibling);
    var q = new Quill(box, { theme: 'snow', modules: { toolbar: [['bold', 'italic', 'underline'], ['link'], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']] } });
    q.root.innerHTML = ta.value;
    var et = document.querySelector('label[for="' + ta.id + '"]');
    q.root.setAttribute('role', 'textbox'); q.root.setAttribute('aria-multiline', 'true'); if (et) q.root.setAttribute('aria-label', et.textContent);
    q.on('text-change', function () { ta.value = q.root.innerHTML === '<p><br></p>' ? '' : q.root.innerHTML; });
    if (et) et.addEventListener('click', function () { q.focus(); });
    ta.hidden = true;
    if (window.pnLink && window.pnLink.quill) window.pnLink.quill(q);
  }
  document.querySelectorAll('textarea.ts-ricco').forEach(ricco);

  var boxReg = document.getElementById('ts-regole-ed');
  if (boxReg) {
    D.regole = D.regole || [];
    var scopi = [['totale', 'Totale']].concat((D.aree || []).map(function (a) { return ['area:' + a.k, 'Area ' + a.nome]; }));
    function fasceDi(cosa) { if (cosa === 'totale') return D.fasce || []; var a = (D.aree || []).filter(function (x) { return 'area:' + x.k === cosa; })[0]; return a ? a.fasce : []; }
    function disegnaRegole(fuoco) {
      boxReg.innerHTML = '<p class="ts-annuncio sr-solo" aria-live="polite"></p><ol class="ts-regole">' + D.regole.map(function (r, i) {
        var p = 'tsr' + i;
        return '<li class="ts-regola" data-i="' + i + '"><div class="ts-dom-testa"><b>Regola ' + (i + 1) + '</b><span class="ts-dom-bottoni">'
          + '<button type="button" class="btn btn-secondary btn-sm" data-su aria-label="Sposta su la regola ' + (i + 1) + '"' + (i === 0 ? ' disabled' : '') + '>&uarr;</button>'
          + '<button type="button" class="btn btn-secondary btn-sm" data-giu aria-label="Sposta giù la regola ' + (i + 1) + '"' + (i === D.regole.length - 1 ? ' disabled' : '') + '>&darr;</button>'
          + '<button type="button" class="btn btn-danger btn-sm" data-togli aria-label="Togli la regola ' + (i + 1) + '">Togli</button></span></div>'
          + '<div class="ts-dom-riga"><div class="form-group"><label for="' + p + '-n">Nome della regola (è anche il titolo del risultato)</label><input type="text" id="' + p + '-n" data-k="nome" maxlength="80" value="' + h(r.nome) + '"></div>'
          + '<div class="form-group"><label for="' + p + '-m">Vale quando</label><select id="' + p + '-m" data-k="modo">' + opz([['e', 'tutte le condizioni sono vere (E)'], ['o', 'almeno una condizione è vera (O)']], r.modo) + '</select></div></div>'
          + '<fieldset class="ts-condizioni"><legend>Condizioni</legend>' + r.condizioni.map(function (c, j) {
            return '<div class="ts-dom-riga" data-j="' + j + '"><div class="form-group"><label for="' + p + '-c' + j + '">Che cosa</label><select id="' + p + '-c' + j + '" data-c="cosa">' + opz(scopi, c.cosa) + '</select></div>'
              + '<div class="form-group"><label for="' + p + '-f' + j + '">è in fascia</label><select id="' + p + '-f' + j + '" data-c="fascia">' + opz(fasceDi(c.cosa).map(function (f) { return [f.k, f.nome]; }), c.fascia) + '</select></div>'
              + '<button type="button" class="btn btn-danger btn-sm" data-togli-c="' + j + '" aria-label="Togli la condizione ' + (j + 1) + '"' + (r.condizioni.length <= 1 ? ' disabled' : '') + '>Togli</button></div>';
          }).join('') + '<button type="button" class="btn btn-secondary btn-sm" data-piu-c>+ Aggiungi una condizione</button></fieldset>'
          + '<div class="form-group"><label for="' + p + '-b">Risposta breve (a schermo)</label><textarea id="' + p + '-b" data-k="breve" rows="3" class="ts-ricco-r">' + h(r.breve || '') + '</textarea></div>'
          + '<div class="form-group"><label for="' + p + '-t">Risposta completa (per email; a schermo se la breve è vuota)</label><textarea id="' + p + '-t" data-k="testo" rows="4" class="ts-ricco-r">' + h(r.testo) + '</textarea></div>'
          + '<div class="sc-riga ts-riga-pulsante"><div class="form-group"><label for="' + p + '-bt">Testo del pulsante (facoltativo)</label><input type="text" id="' + p + '-bt" data-k="pulsante_testo" maxlength="80" value="' + h(r.pulsante_testo) + '"></div>'
          + '<div class="form-group"><label for="' + p + '-bl">Link del pulsante</label><input type="text" id="' + p + '-bl" data-k="pulsante_link" maxlength="500" value="' + h(r.pulsante_link) + '" placeholder="per esempio: /contatti/" data-link-sito></div></div></li>';
      }).join('') + '</ol>' + (D.regole.length ? '' : '<p class="hint ts-vuoto">Nessuna regola: vale la fascia del totale. Aggiungine una se una combinazione di aree merita un testo suo.</p>')
        + '<button type="button" class="btn btn-secondary btn-sm" data-piu-r' + (D.regole.length >= (MAX.regole || 20) || !(D.fasce || []).length ? ' disabled' : '') + '>+ Aggiungi una regola</button>';
      boxReg.querySelectorAll('textarea.ts-ricco-r').forEach(function (ta) {
        ricco(ta);
        var i = +ta.closest('[data-i]').dataset.i;
        ta.addEventListener('input', function () { D.regole[i][ta.dataset.k] = ta.value; });
      });
      if (fuoco) { var f = boxReg.querySelector(fuoco); if (f) f.focus(); }
    }
    // Quill scrive nel textarea senza evento: si rilegge tutto all'invio.
    function leggiTesti() { boxReg.querySelectorAll('textarea.ts-ricco-r').forEach(function (ta) { D.regole[+ta.closest('[data-i]').dataset.i][ta.dataset.k] = ta.value; }); }
    boxReg.addEventListener('input', function (e) { var li = e.target.closest('[data-i]'); var k = e.target.dataset.k; if (li && k && k !== 'modo') D.regole[+li.dataset.i][k] = e.target.value; });
    boxReg.addEventListener('change', function (e) {
      var li = e.target.closest('[data-i]'); if (!li) return;
      var r = D.regole[+li.dataset.i];
      if (e.target.dataset.k === 'modo') r.modo = e.target.value;
      if (e.target.dataset.k === 'pulsante_link') r.pulsante_link = e.target.value;
      var c = e.target.dataset.c;
      if (c) {
        var cond = r.condizioni[+e.target.closest('[data-j]').dataset.j];
        cond[c] = e.target.value;
        if (c === 'cosa') { var f = fasceDi(cond.cosa); cond.fascia = f.length ? f[0].k : ''; leggiTesti(); disegnaRegole('#' + e.target.id); }
      }
    });
    boxReg.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b || b.closest('.ql-toolbar')) return;
      if (b.hasAttribute('data-piu-r')) { leggiTesti(); D.regole.push({ k: chiave('r'), nome: 'Regola ' + (D.regole.length + 1), modo: 'e', condizioni: [{ cosa: 'totale', fascia: (D.fasce[0] || {}).k || '' }], breve: '', testo: '', pulsante_testo: '', pulsante_link: '' }); disegnaRegole('#tsr' + (D.regole.length - 1) + '-n'); return; }
      var li = b.closest('[data-i]'); if (!li) return;
      var i = +li.dataset.i, r = D.regole[i];
      leggiTesti();
      if (b.hasAttribute('data-togli')) { D.regole.splice(i, 1); disegnaRegole('[data-piu-r]'); annuncia(boxReg, 'Regola ' + (i + 1) + ' tolta.'); return; }
      if (b.hasAttribute('data-su') && sposta(D.regole, i, -1)) { disegnaRegole('#tsr' + (i - 1) + '-n'); annuncia(boxReg, 'Regola spostata su: ora si guarda prima.'); return; }
      if (b.hasAttribute('data-giu') && sposta(D.regole, i, 1)) { disegnaRegole('#tsr' + (i + 1) + '-n'); annuncia(boxReg, 'Regola spostata giù.'); return; }
      if (b.hasAttribute('data-piu-c')) { r.condizioni.push({ cosa: 'totale', fascia: (D.fasce[0] || {}).k || '' }); disegnaRegole('#tsr' + i + '-c' + (r.condizioni.length - 1)); return; }
      if (b.hasAttribute('data-togli-c')) { r.condizioni.splice(+b.getAttribute('data-togli-c'), 1); disegnaRegole('#tsr' + i + '-c0'); }
    });
    disegnaRegole();
    form.addEventListener('submit', function () {
      leggiTesti();
      boxReg.querySelectorAll('input[data-k="pulsante_link"]').forEach(function (x) { D.regole[+x.closest('[data-i]').dataset.i].pulsante_link = x.value; });
      document.getElementById('ts-regole-json').value = JSON.stringify({ regole: D.regole });
    });
  }
})();
