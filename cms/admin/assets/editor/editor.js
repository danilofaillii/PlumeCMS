/* L'editor delle pagine: il pannello. L'albero vive qui; la tela (iframe) mostra quello che il server disegna. */
(function () {
  'use strict';
  var D = JSON.parse(document.getElementById('ed-dati').textContent);
  var DEF = D.definizioni;
  var albero = sistemaAlbero(D.albero);
  var misura = 'd';
  // Dal server un elemento senza impostazioni arriva con [] (array vuoto di PHP): in JavaScript deve essere un oggetto,
  // altrimenti i valori scritti dal pannello spariscono nel JSON.
  function sistemaAlbero(a) {
    (function giro(lista) { (lista || []).forEach(function (x) { if (!x.impostazioni || typeof x.impostazioni !== 'object' || Array.isArray(x.impostazioni)) x.impostazioni = {}; if (x.figli) giro(x.figli); }); })(a.elementi);
    return a;
  }
  var selezionato = null;
  var storia = [JSON.stringify(albero)], indiceStoria = 0;
  var sporco = false, salvando = false, ultimoSalvato = storia[0], pubblicata = D.pubblicata, bozzaInSospeso = D.bozza;
  var iframe = document.getElementById('ed-iframe');
  var telaPronta = false, disegnoInCorso = false, disegnoRichiesto = false, timerDisegno = null;
  var etichette = {};
  Object.keys(DEF).forEach(function (t) { etichette[t] = DEF[t].etichetta; });
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  // ------------------------------------------------------------ chiamate
  function api(azione, campi, file) {
    var fd = new FormData();
    fd.append('_csrf', D.csrf);
    fd.append('id', D.id);
    fd.append('tipo', D.tipo || 'pagine');
    fd.append('azione', azione);
    Object.keys(campi || {}).forEach(function (k) { fd.append(k, window.campiProtetti ? window.campiProtetti.codifica(campi[k]) : campi[k]); });
    if (file) fd.append('immagine', file);
    return fetch('editor-api.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
      return r.json().catch(function () { return { errore: window.campiProtetti ? window.campiProtetti.spiega(r.status) : 'Risposta del server non leggibile (' + r.status + ').' }; });
    }).then(function (j) { if (j.errore) throw new Error(j.errore); return j; });
  }

  function stato(testo, classe) {
    var s = $('#ed-stato');
    s.textContent = testo;
    s.className = 'ed-stato' + (classe ? ' ed-stato-' + classe : '');
  }
  function aggiornaStato() {
    if (sporco) stato('Modifiche non salvate', 'bozza');
    else if (bozzaInSospeso) stato('Bozza salvata, non ancora pubblicata', 'bozza');
    else if (pubblicata) stato('Tutto pubblicato');
    else stato('Mai pubblicata', 'bozza');
    $('#ed-annulla').disabled = indiceStoria === 0;
    $('#ed-ripeti').disabled = indiceStoria >= storia.length - 1;
  }

  // ------------------------------------------------------------ albero
  function nuovoId() {
    var s = '';
    var a = 'abcdefghijklmnopqrstuvwxyz0123456789';
    for (var i = 0; i < 7; i++) s += a[Math.floor(Math.random() * a.length)];
    return s;
  }
  function tutti() {
    var out = [];
    albero.elementi.forEach(function (s, i) {
      out.push({ el: s, lista: albero.elementi, indice: i, genitore: null });
      s.figli.forEach(function (c, j) {
        out.push({ el: c, lista: s.figli, indice: j, genitore: s });
        c.figli.forEach(function (w, k) { out.push({ el: w, lista: c.figli, indice: k, genitore: c }); });
      });
    });
    return out;
  }
  function trova(id) {
    var t = tutti();
    for (var i = 0; i < t.length; i++) if (t[i].el.id === id) return t[i];
    return null;
  }
  function clona(el) {
    var c = JSON.parse(JSON.stringify(el));
    (function rinomina(x) { x.id = nuovoId(); (x.figli || []).forEach(rinomina); })(c);
    return c;
  }
  function nuovoWidget(tipo) { return { id: nuovoId(), tipo: tipo, impostazioni: {} }; }
  function nuovaColonna(larghezza) { return { id: nuovoId(), tipo: 'colonna', impostazioni: larghezza ? { larghezza: { d: larghezza } } : {}, figli: [] }; }
  function nuovaSezione() { return { id: nuovoId(), tipo: 'sezione', impostazioni: {}, figli: [nuovaColonna()] }; }

  // Dopo ogni cambiamento: storia, tela, pannelli.
  function cambiato(selezionaId, senzaStoria) {
    if (!senzaStoria) {
      storia = storia.slice(0, indiceStoria + 1);
      storia.push(JSON.stringify(albero));
      if (storia.length > 60) storia.shift();
      indiceStoria = storia.length - 1;
    }
    sporco = storia[indiceStoria] !== ultimoSalvato;
    if (selezionaId !== undefined) selezionato = selezionaId;
    aggiornaStato();
    disegna();
    disegnaNavigatore();
  }
  function annulla() { if (indiceStoria > 0) { indiceStoria--; albero = JSON.parse(storia[indiceStoria]); if (selezionato && !trova(selezionato)) selezionato = null; cambiato(undefined, true); mostraControlli(); } }
  function ripeti() { if (indiceStoria < storia.length - 1) { indiceStoria++; albero = JSON.parse(storia[indiceStoria]); if (selezionato && !trova(selezionato)) selezionato = null; cambiato(undefined, true); mostraControlli(); } }

  // ------------------------------------------------------------ tela
  function mandaTela(m) { if (iframe.contentWindow) iframe.contentWindow.postMessage(Object.assign({ ed: 'pannello' }, m), location.origin); }
  function disegna() {
    clearTimeout(timerDisegno);
    timerDisegno = setTimeout(disegnaOra, 120);
  }
  function disegnaOra() {
    if (!telaPronta) return;
    if (disegnoInCorso) { disegnoRichiesto = true; return; }
    disegnoInCorso = true;
    api('disegna', { albero: JSON.stringify(albero) }).then(function (j) {
      mandaTela({ tipo: 'disegna', html: j.html, css: j.css, etichette: etichette, selezionato: selezionato });
      avvisoH1(j.h1);
    }).catch(function (e) { stato(e.message, 'errore'); }).then(function () {
      disegnoInCorso = false;
      if (disegnoRichiesto) { disegnoRichiesto = false; disegnaOra(); }
    });
  }
  function avvisoH1(ha) {
    var n = $('#ed-titolo-pagina');
    n.title = ha ? '' : 'La pagina non ha un titolo H1: aggiungi un widget Titolo con livello H1 (o un\'Apertura).';
    n.style.color = ha ? '' : 'var(--attenzione-testo)';
  }

  window.addEventListener('message', function (e) {
    if (e.origin !== location.origin || !e.data || e.data.ed !== 'tela') return;
    var m = e.data;
    switch (m.tipo) {
      case 'pronta': telaPronta = true; disegnaOra(); break;
      case 'seleziona': selezionato = m.id; mostraControlli(); disegnaNavigatore(); break;
      case 'modifica': selezionato = m.id; apriPannello('modifica'); mostraControlli(); disegnaNavigatore(); break;
      case 'azione': azione(m.azione, m.id); break;
      case 'aggiungi-sezione': aggiungiSezione(m.dopo); break;
      case 'aggiungi-widget': aggiungiWidget(m.widget, m.colonna, m.indice); break;
      case 'aggiungi-pronta': inserisciPronta(m.pronta, m.indice); break;
      case 'sposta': sposta(m.id, m.genitore, m.indice); break;
    }
  });

  // ------------------------------------------------------------ azioni sull'albero
  function azione(az, id) {
    var p = trova(id);
    if (!p) return;
    switch (az) {
      case 'su': if (p.indice > 0) { p.lista.splice(p.indice, 1); p.lista.splice(p.indice - 1, 0, p.el); cambiato(id); } break;
      case 'giu': if (p.indice < p.lista.length - 1) { p.lista.splice(p.indice, 1); p.lista.splice(p.indice + 1, 0, p.el); cambiato(id); } break;
      case 'duplica': var c = clona(p.el); p.lista.splice(p.indice + 1, 0, c); cambiato(c.id); mostraControlli(); break;
      case 'elimina':
        if (p.el.tipo === 'colonna' && p.lista.length === 1) { alert('Una sezione ha almeno una colonna: elimina la sezione.'); return; }
        if (p.el.tipo !== 'sezione' || confirm('Eliminare la sezione con tutto quello che contiene?')) {
          p.lista.splice(p.indice, 1);
          cambiato(null); mostraControlli();
        }
        break;
    }
  }
  function aggiungiSezione(dopoId) {
    var s = nuovaSezione();
    var p = dopoId ? trova(dopoId) : null;
    if (p && p.el.tipo === 'sezione') albero.elementi.splice(p.indice + 1, 0, s); else albero.elementi.push(s);
    cambiato(s.id); apriPannello('modifica'); mostraControlli();
  }
  function aggiungiWidget(tipo, colonnaId, indice) {
    if (!DEF[tipo]) return;
    var w = nuovoWidget(tipo);
    var col = colonnaId ? trova(colonnaId) : null;
    if (!col || col.el.tipo !== 'colonna') {
      // Niente colonna: sotto l'elemento selezionato, oppure in fondo alla pagina
      var p = selezionato ? trova(selezionato) : null;
      if (p && p.el.tipo === 'colonna') { col = p; indice = null; }
      else if (p && p.el.tipo === 'sezione') { col = { el: p.el.figli[0] }; indice = null; }
      else if (p && p.genitore) { col = { el: p.genitore }; indice = p.indice + 1; }
      else {
        if (!albero.elementi.length) albero.elementi.push(nuovaSezione());
        var ultima = albero.elementi[albero.elementi.length - 1];
        col = { el: ultima.figli[ultima.figli.length - 1] }; indice = null;
      }
    }
    if (indice === null || indice === undefined || indice > col.el.figli.length) indice = col.el.figli.length;
    col.el.figli.splice(indice, 0, w);
    cambiato(w.id); apriPannello('modifica'); mostraControlli();
  }
  function sposta(id, genitoreId, indice) {
    var p = trova(id);
    if (!p) return;
    var lista;
    if (p.el.tipo === 'sezione') lista = albero.elementi;
    else { var g = trova(genitoreId); if (!g) { disegna(); return; } lista = g.el.figli; }
    p.lista.splice(p.indice, 1);
    if (indice > lista.length) indice = lista.length;
    lista.splice(indice, 0, p.el);
    cambiato(id);
  }
  function impostaColonne(sezione, n) {
    var colonne = sezione.figli;
    while (colonne.length > n) { var via = colonne.pop(); colonne[colonne.length - 1].figli = colonne[colonne.length - 1].figli.concat(via.figli); }
    while (colonne.length < n) colonne.push(nuovaColonna());
    var l = Math.floor(100 / n);
    colonne.forEach(function (c) { c.impostazioni.larghezza = { d: l, m: 100 }; if (n > 2) c.impostazioni.larghezza.t = 50; });
    cambiato(sezione.id); mostraControlli();
  }

  // ------------------------------------------------------------ pannello widget
  function disegnaElencoWidget() {
    var gruppi = {};
    Object.keys(DEF).forEach(function (t) {
      var d = DEF[t];
      if (!d.gruppo) return;
      if ((D.widgetDisattivi || []).indexOf(t) >= 0) return; // menu: widget spento in Editor > Widget: via dalla tavolozza, resta nelle pagine
      (gruppi[d.gruppo] = gruppi[d.gruppo] || []).push(t);
    });
    var html = '';
    Object.keys(gruppi).forEach(function (g) {
      html += '<div class="ed-gruppo-widget"><h4>' + g + '</h4><div class="ed-griglia-widget">';
      gruppi[g].forEach(function (t) {
        html += '<div class="ed-widget-voce" draggable="true" data-tipo="' + t + '" data-nome="' + DEF[t].etichetta.toLowerCase() + '"><i>' + DEF[t].icona + '</i>' + DEF[t].etichetta + '</div>';
      });
      html += '</div></div>';
    });
    $('#ed-elenco-widget').innerHTML = html;
    $$('.ed-widget-voce').forEach(function (v) {
      v.addEventListener('dragstart', function (e) { e.dataTransfer.setData('text/ed-widget', v.dataset.tipo); e.dataTransfer.effectAllowed = 'copy'; });
      v.addEventListener('click', function () { aggiungiWidget(v.dataset.tipo); });
    });
    $('#ed-cerca-widget').addEventListener('input', function () {
      var q = this.value.trim().toLowerCase();
      $$('.ed-widget-voce').forEach(function (v) { v.classList.toggle('ed-nascosto', q !== '' && v.dataset.nome.indexOf(q) < 0 && v.dataset.tipo.indexOf(q) < 0); });
    });
  }

  // ------------------------------------------------------------ sezioni pronte (1.3.0)
  // Blocchi già impaginati (includes/sezioni-pronte-dati.php) che entrano nella pagina come sezioni normali: con un
  // clic sotto la sezione selezionata (o in fondo), oppure trascinati nella tela. L'anteprima è un disegno schematico
  // fatto dai widget della variante: leggero, niente immagini, niente richieste al server.
  var PRONTE = {};
  (D.sezioniPronte || []).forEach(function (v) { sistemaAlbero({ elementi: v.elementi }); PRONTE[v.id] = v; });
  function miniWidget(w) {
    var v = w.impostazioni || {}, t = w.tipo, l = function (c, n) { var o = ''; for (var i = 0; i < (n || 1); i++) o += '<i class="' + c + '"></i>'; return o; };
    var allinea = (v.allineamento && v.allineamento.d === 'center') ? ' ed-mini-centro' : '';
    switch (t) {
      case 'titolo': return '<div class="ed-mini-w' + allinea + '">' + (v.occhiello ? l('ed-mini-occhiello') : '') + l('ed-mini-titolo') + (v.sottotitolo ? l('ed-mini-riga ed-mini-corta') : '') + '</div>';
      case 'hero':
        var d = v.disposizione || 'classica', centro = ['centrata', 'minimale'].indexOf(d) >= 0;
        var parole = l('ed-mini-occhiello') + l('ed-mini-titolo ed-mini-grande') + l('ed-mini-titolo ed-mini-grande ed-mini-corta') + l('ed-mini-riga ed-mini-corta') + '<span class="ed-mini-pulsanti">' + l('ed-mini-pill', Math.max(1, (v.pulsanti || []).length)) + '</span>';
        if (d === 'minimale') parole += '<span class="ed-mini-numeri">' + l('ed-mini-cifra', (v.barre || []).length || 3) + '</span>';
        return '<div class="ed-mini-w ed-mini-hero ed-mini-hero-' + d + (centro ? ' ed-mini-centro' : '') + '">' + parole + '</div>';
      case 'testo': case 'frase': case 'citazione': return '<div class="ed-mini-w' + (t !== 'testo' ? ' ed-mini-centro' : '') + '">' + l('ed-mini-riga', t === 'testo' ? 3 : 2) + l('ed-mini-riga ed-mini-corta') + '</div>';
      case 'pulsanti': case 'pulsante': return '<div class="ed-mini-w' + allinea + '"><span class="ed-mini-pulsanti">' + l('ed-mini-pill', Math.max(1, (v.voci || [1]).length)) + '</span></div>';
      case 'schede': case 'passi': return '<div class="ed-mini-w ed-mini-griglia">' + l('ed-mini-scheda', Math.min(4, (v.voci || [1, 1, 1]).length)) + '</div>';
      case 'riquadro_icona': case 'contatore': return '<div class="ed-mini-w ed-mini-centro">' + l(t === 'contatore' ? 'ed-mini-cifra' : 'ed-mini-icona') + l('ed-mini-riga ed-mini-media') + l('ed-mini-riga ed-mini-corta') + '</div>';
      case 'numeri': return '<div class="ed-mini-w ed-mini-griglia' + ((v.colonne && v.colonne.d === 1) ? ' ed-mini-colonna' : '') + '">' + l('ed-mini-scheda ed-mini-cifre', Math.min(4, (v.voci || [1, 1, 1]).length)) + '</div>';
      case 'faq': case 'fisarmonica': return '<div class="ed-mini-w">' + l('ed-mini-riga-box', Math.min(4, (v.voci || [1, 1, 1]).length)) + '</div>';
      case 'tabella_prezzi': return '<div class="ed-mini-w"><span class="ed-mini-prezzo' + (v.evidenza ? ' ed-mini-evidenza' : '') + '">' + l('ed-mini-cifra') + l('ed-mini-riga', 3) + l('ed-mini-pill') + '</span></div>';
      case 'listino': case 'elenco_icone': case 'elenco': return '<div class="ed-mini-w">' + l('ed-mini-voce', Math.min(4, (v.voci || [1, 1, 1]).length)) + '</div>';
      case 'testimonianza': return '<div class="ed-mini-w ed-mini-centro">' + l('ed-mini-stelle') + l('ed-mini-riga', 2) + l('ed-mini-riga ed-mini-corta') + '</div>';
      case 'carosello_testimonianze': return '<div class="ed-mini-w ed-mini-griglia">' + l('ed-mini-scheda ed-mini-citazione', 3) + '</div>';
      case 'presentazione': return '<div class="ed-mini-w ed-mini-chi">' + l('ed-mini-ritratto') + '<span>' + l('ed-mini-titolo') + l('ed-mini-riga', 2) + '</span></div>';
      case 'modulo': return '<div class="ed-mini-w ed-mini-form">' + l('ed-mini-campo', 2) + l('ed-mini-campo ed-mini-alto') + l('ed-mini-pill') + '</div>';
      case 'barra_avanzamento': return '<div class="ed-mini-w">' + l('ed-mini-riga ed-mini-corta') + l('ed-mini-barra') + '</div>';
      case 'invito_libero': return '<div class="ed-mini-w ed-mini-centro ed-mini-cornice">' + l('ed-mini-icona') + l('ed-mini-titolo ed-mini-media') + l('ed-mini-riga') + l('ed-mini-pill') + '</div>';
      default: return '<div class="ed-mini-w">' + l('ed-mini-riga', 2) + '</div>';
    }
  }
  function miniatura(elementi) {
    return '<div class="ed-mini" aria-hidden="true">' + elementi.map(function (s) {
      var si = s.impostazioni || {};
      return '<div class="ed-mini-sez ed-mini-sfondo-' + (si.sfondo || 'nessuno') + (si.larghezza === 'stretta' ? ' ed-mini-stretta' : '') + '">' + s.figli.map(function (c) {
        var lg = c.impostazioni && c.impostazioni.larghezza && c.impostazioni.larghezza.d;
        return '<div class="ed-mini-col' + (c.impostazioni && c.impostazioni.riquadro ? ' ed-mini-cornice' : '') + '" style="flex:' + (lg || 100) + ' 1 0">' + c.figli.map(miniWidget).join('') + '</div>';
      }).join('') + '</div>';
    }).join('') + '</div>';
  }
  function disegnaElencoSezioni() {
    var box = $('#ed-elenco-sezioni');
    if (!box) return;
    var cat = D.categorieSezioni || {}, html = '';
    Object.keys(cat).forEach(function (c) {
      var voci = (D.sezioniPronte || []).filter(function (v) { return v.categoria === c; });
      if (!voci.length) return;
      html += '<div class="ed-gruppo-sezioni" data-categoria="' + c + '"><h4>' + escapeHtml(cat[c]) + '</h4>';
      voci.forEach(function (v) {
        html += '<button type="button" class="ed-sezione-voce" draggable="true" data-id="' + escapeHtml(v.id) + '" data-cerca="' + escapeHtml((cat[c] + ' ' + v.nome + ' ' + v.descrizione).toLowerCase()) + '" title="' + escapeHtml(v.descrizione) + '">'
          + miniatura(v.elementi) + '<b>' + escapeHtml(v.nome) + '</b><small>' + escapeHtml(v.descrizione) + '</small></button>';
      });
      html += '</div>';
    });
    box.innerHTML = html;
    $$('.ed-sezione-voce', box).forEach(function (b) {
      b.addEventListener('dragstart', function (e) { e.dataTransfer.setData('text/ed-sezione', b.dataset.id); e.dataTransfer.effectAllowed = 'copy'; });
      b.addEventListener('click', function () {
        var p = selezionato ? trova(selezionato) : null;
        while (p && p.el.tipo !== 'sezione') p = p.genitore ? trova(p.genitore.id) : null;
        inserisciPronta(b.dataset.id, p ? p.indice + 1 : albero.elementi.length);
      });
    });
    $('#ed-cerca-sezioni').addEventListener('input', function () {
      var q = this.value.trim().toLowerCase();
      $$('.ed-sezione-voce', box).forEach(function (v) { v.classList.toggle('ed-nascosto', q !== '' && v.dataset.cerca.indexOf(q) < 0); });
      $$('.ed-gruppo-sezioni', box).forEach(function (g) { g.hidden = !$$('.ed-sezione-voce:not(.ed-nascosto)', g).length; });
    });
  }
  // Le sezioni della variante entrano copiate, con id nuovi: si può mettere la stessa variante più volte.
  function inserisciPronta(id, indice) {
    var v = PRONTE[id];
    if (!v) return;
    var nuove = v.elementi.map(clona);
    if (indice === null || indice === undefined || indice > albero.elementi.length) indice = albero.elementi.length;
    Array.prototype.splice.apply(albero.elementi, [indice, 0].concat(nuove));
    cambiato(nuove[0].id); apriPannello('modifica'); mostraControlli();
    stato('Sezione "' + v.nome + '" aggiunta', 'ok');
  }

  function apriPannello(nome) {
    $$('.ed-schede-pannello button').forEach(function (b) { b.classList.toggle('attivo', b.dataset.pannello === nome); });
    $$('.ed-pannello-vista').forEach(function (v) { v.hidden = v.id !== 'ed-pannello-' + nome; });
  }
  $$('.ed-schede-pannello button').forEach(function (b) { b.addEventListener('click', function () { apriPannello(b.dataset.pannello); }); });

  // ------------------------------------------------------------ controlli
  var schedaAttiva = 'contenuto';
  var quillVivi = [];
  var sezioniAperte = {};   // "tipo/scheda/sezione" -> aperta?
  var statoAttivo = {};     // nome del controllo -> 'normale' | 'hover'
  var GRUPPI = ['carattere', 'tipografia', 'sfondo', 'bordo', 'ombra', 'ombra_testo', 'dimensioni', 'misura', 'cursore', 'allineamento', 'scelta_icone', 'icona', 'galleria', 'data_ora', 'trasformazione'];
  var ICONE_MISURA = { d: 'Desktop', t: 'Tablet', m: 'Telefono' };
  var SVG = {
    d: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
    t: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="2" width="18" height="20" rx="2"/><path d="M11 18h2"/></svg>',
    m: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/></svg>',
    left: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h12M3 18h15"/></svg>',
    center: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M6 12h12M5 18h14"/></svg>',
    right: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M9 12h12M6 18h15"/></svg>',
    justify: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>',
    'flex-start': '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 3v18"/><rect x="7" y="7" width="10" height="4"/><rect x="7" y="13" width="6" height="4"/></svg>',
    'flex-end': '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 3v18"/><rect x="7" y="7" width="10" height="4"/><rect x="11" y="13" width="6" height="4"/></svg>',
    stretch: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 3v18M20 3v18"/><rect x="7" y="7" width="10" height="4"/><rect x="7" y="13" width="10" height="4"/></svg>',
    lucchetto: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
    lucchettoAperto: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.5-2"/></svg>'
  };
  function eGruppo(ctl) { return GRUPPI.indexOf(ctl.tipo) >= 0; }
  function copia(v) { return v === undefined ? undefined : JSON.parse(JSON.stringify(v)); }
  function mk(tag, classe, html) { var e = document.createElement(tag); if (classe) e.className = classe; if (html !== undefined) e.innerHTML = html; return e; }

  function valoreResponsive(v, m) {
    var catena = { d: ['d'], t: ['t', 'd'], m: ['m', 't', 'd'] }[m];
    if (!v || typeof v !== 'object' || Array.isArray(v) || !('d' in v || 't' in v || 'm' in v)) return { valore: v, ereditato: false };
    for (var i = 0; i < catena.length; i++) if (v[catena[i]] !== undefined && v[catena[i]] !== '' && v[catena[i]] !== null) return { valore: v[catena[i]], ereditato: catena[i] !== m };
    return { valore: undefined, ereditato: true };
  }
  function predefinito(ctl, m) {
    var p = ctl.predefinito;
    if (ctl.responsive) { var r = valoreResponsive(p && typeof p === 'object' && !Array.isArray(p) && ('d' in p || 't' in p || 'm' in p) ? p : { d: p }, m); return r.valore; }
    return p;
  }
  function leggi(imp, ctl) {
    var v = imp[ctl.nome];
    if (ctl.responsive) {
      var r = valoreResponsive(v, misura);
      if (r.valore === undefined) return { valore: predefinito(ctl, misura), ereditato: true, proprio: false };
      return { valore: r.valore, ereditato: r.ereditato, proprio: !r.ereditato };
    }
    return { valore: v === undefined ? ctl.predefinito : v, ereditato: false, proprio: v !== undefined };
  }
  function scrivi(imp, ctl, valore) {
    if (ctl.responsive) {
      var r = imp[ctl.nome];
      if (!r || typeof r !== 'object' || Array.isArray(r) || !('d' in r || 't' in r || 'm' in r)) r = r === undefined ? {} : { d: r };
      if (valore === undefined || valore === '') delete r[misura]; else r[misura] = valore;
      imp[ctl.nome] = r;
    } else if (valore === undefined) delete imp[ctl.nome];
    else imp[ctl.nome] = valore;
  }
  // Il valore "di confronto" per le condizioni: desktop, stato normale.
  function valoreCondizione(imp, def, nome) {
    var ctl = null;
    def.controlli.forEach(function (c) { if (c.nome === nome) ctl = c; });
    if (!ctl) return imp[nome];
    var v = imp[nome];
    if (ctl.stati && v && typeof v === 'object' && ('normale' in v || 'hover' in v)) v = v.normale;
    if (ctl.responsive) { var r = valoreResponsive(v, 'd'); v = r.valore === undefined ? predefinito(ctl, 'd') : r.valore; }
    else if (v === undefined) v = ctl.predefinito;
    if (v && typeof v === 'object' && 'v' in v) v = v.v;
    return v === undefined || v === null ? '' : v;
  }
  function condizioneOk(imp, def, ctl) {
    var cond = ctl.condizione || ctl.se;
    if (!cond) return true;
    return Object.keys(cond).every(function (k) {
      var neg = k.charAt(0) === '!', nome = neg ? k.slice(1) : k;
      var attuale = String(valoreCondizione(imp, def, nome));
      var attesi = Array.isArray(cond[k]) ? cond[k] : [cond[k]];
      var ok = attesi.some(function (x) { return String(x) === attuale; });
      return neg ? !ok : ok;
    });
  }
  // I controlli da cui dipende qualche condizione: cambiandoli il pannello si ridisegna.
  function dipendenze(def) {
    var out = {};
    def.controlli.forEach(function (c) { var cond = c.condizione || c.se; if (cond) Object.keys(cond).forEach(function (k) { out[k.replace(/^!/, '')] = true; }); });
    return out;
  }

  function mostraControlli() {
    quillVivi = [];
    var p = selezionato ? trova(selezionato) : null;
    var cont = $('#ed-controlli'), schede = $('#ed-schede-controlli'), azioni = $('#ed-modifica-azioni');
    var vista = cont.closest('.ed-pannello-vista'), scroll = vista ? vista.scrollTop : 0;
    if (!p) {
      $('#ed-modifica-nome').textContent = 'Niente selezionato';
      schede.innerHTML = ''; azioni.innerHTML = '';
      cont.innerHTML = '<p class="ed-aiuto">Fai clic su un elemento della pagina per modificarlo.</p>';
      return;
    }
    var elx = p.el, def = DEF[elx.tipo];
    $('#ed-modifica-nome').textContent = def.etichetta;
    azioni.innerHTML = '<button type="button" data-az="su" title="Sposta su">↑</button><button type="button" data-az="giu" title="Sposta giù">↓</button><button type="button" data-az="duplica" title="Duplica">⧉</button><button type="button" data-az="elimina" title="Elimina">\uD83D\uDDD1</button>';
    $$('button', azioni).forEach(function (b) { b.addEventListener('click', function () { azione(b.dataset.az, elx.id); }); });
    var presenti = [];
    def.controlli.forEach(function (c) { if (presenti.indexOf(c.scheda) < 0) presenti.push(c.scheda); });
    var nomi = { contenuto: 'Contenuto', stile: 'Stile', avanzate: 'Avanzate' };
    if (presenti.indexOf(schedaAttiva) < 0) schedaAttiva = presenti[0];
    schede.innerHTML = presenti.map(function (s) { return '<button type="button" data-scheda="' + s + '" class="' + (s === schedaAttiva ? 'attivo' : '') + '">' + (nomi[s] || s) + '</button>'; }).join('');
    $$('button', schede).forEach(function (b) { b.addEventListener('click', function () { schedaAttiva = b.dataset.scheda; mostraControlli(); }); });
    cont.innerHTML = '';
    if (elx.tipo === 'sezione' && schedaAttiva === 'contenuto') cont.appendChild(controlloColonne(elx));
    var dip = dipendenze(def);
    var sezioneCorrente = null, contenitore = cont, primaSezione = true;
    def.controlli.forEach(function (ctl) {
      if (ctl.scheda !== schedaAttiva) return;
      if (!condizioneOk(elx.impostazioni, def, ctl)) return;
      var sez = ctl.sezione || '';
      if (sez !== sezioneCorrente) {
        sezioneCorrente = sez;
        if (sez === '') contenitore = cont;
        else {
          var chiave = elx.tipo + '/' + schedaAttiva + '/' + sez;
          if (sezioniAperte[chiave] === undefined) sezioniAperte[chiave] = primaSezione;
          primaSezione = false;
          var det = mk('details', 'ed-sez'); det.open = !!sezioniAperte[chiave];
          var sum = mk('summary'); sum.textContent = sez; det.appendChild(sum);
          det.addEventListener('toggle', function () { sezioniAperte[chiave] = det.open; });
          var corpo = mk('div', 'ed-sez-corpo'); det.appendChild(corpo);
          cont.appendChild(det); contenitore = corpo;
        }
      }
      var eTema = ctl.nome === 'tema' && elx.tipo.indexOf('ig_') === 0;
      contenitore.appendChild(controllo(elx.impostazioni, ctl, function () {
        if (eTema) applicaTema(elx, elx.impostazioni.tema);
        cambiato(elx.id);
        if (dip[ctl.nome] || eTema) mostraControlli();
      }, def));
      if (eTema) contenitore.appendChild(barraTemi(elx));
    });
    if (vista) vista.scrollTop = scroll;
  }

  // ------------------------------------------------------------ temi delle infografiche
  // Un tema è un insieme di valori per i campi di stile dei widget ig_* (D.temiCampi). Scegliendolo, quei campi
  // prendono i suoi valori e restano modificabili; "Salva come tema" tiene i valori correnti con un nome.
  function applicaTema(el, chiave) {
    var t = D.temi && D.temi[chiave];
    if (!t) return;
    var def = DEF[el.tipo];
    (D.temiCampi || []).forEach(function (k) {
      var ha = def.controlli.some(function (c) { return c.nome === k; });
      if (!ha) return;
      if (Object.prototype.hasOwnProperty.call(t.valori, k)) el.impostazioni[k] = t.valori[k]; else delete el.impostazioni[k];
    });
  }
  function valoriStile(el) {
    var out = {};
    DEF[el.tipo].controlli.forEach(function (c) { if ((D.temiCampi || []).indexOf(c.nome) >= 0) out[c.nome] = leggi(el.impostazioni, c).valore; });
    return out;
  }
  function aggiornaTemi(j) {
    D.temi = j.temi;
    Object.keys(DEF).forEach(function (t) {
      if (t.indexOf('ig_') !== 0) return;
      DEF[t].controlli.forEach(function (c) { if (c.nome === 'tema') c.opzioni = j.opzioni; });
    });
  }
  function barraTemi(el) {
    var chiave = el.impostazioni.tema || 'carta', t = D.temi && D.temi[chiave];
    var d = document.createElement('div'); d.className = 'ed-temi-barra';
    if (t && t.descrizione) { var p = document.createElement('p'); p.className = 'ed-aiuto ed-tema-descrizione'; p.textContent = t.descrizione; d.appendChild(p); }
    var riga = document.createElement('div'); riga.className = 'ed-temi-azioni'; d.appendChild(riga);
    function bottone(testo, classe, fn) { var b = document.createElement('button'); b.type = 'button'; b.className = 'ed-b ed-b-piccolo' + (classe ? ' ' + classe : ''); b.textContent = testo; b.addEventListener('click', fn); riga.appendChild(b); return b; }
    function dopo(j, messaggio) { aggiornaTemi(j); el.impostazioni.tema = j.scelto; applicaTema(el, j.scelto); cambiato(el.id); mostraControlli(); stato(messaggio, 'ok'); }
    function errore(e) { alert(e.message || 'Qualcosa non ha funzionato.'); }
    bottone('Salva come tema', 'ed-b-primo', function () {
      var nome = prompt('Nome del tema (i valori di stile di questo widget diventano un tema nuovo):', t && t.id ? t.nome : '');
      if (nome === null || !nome.trim()) return;
      api('temi', { cosa: 'salva', nome: nome.trim(), valori: JSON.stringify(valoriStile(el)) }).then(function (j) { dopo(j, 'Tema salvato'); }).catch(errore);
    });
    if (t && t.id) {
      bottone('Rinomina', '', function () {
        var nome = prompt('Nuovo nome del tema:', t.nome);
        if (nome === null || !nome.trim()) return;
        api('temi', { cosa: 'rinomina', tema: chiave, nome: nome.trim() }).then(function (j) { dopo(j, 'Tema rinominato'); }).catch(errore);
      });
      bottone('Elimina', 'ed-b-pericolo', function () {
        if (!confirm('Eliminare il tema "' + t.nome + '"? I widget che lo usano tengono i loro valori e tornano a dire "Carta".')) return;
        api('temi', { cosa: 'elimina', tema: chiave }).then(function (j) { aggiornaTemi(j); el.impostazioni.tema = 'carta'; cambiato(el.id); mostraControlli(); stato('Tema eliminato', 'ok'); }).catch(errore);
      });
    }
    return d;
  }

  function controlloColonne(sezione) {
    var d = document.createElement('div');
    d.className = 'ed-controllo';
    d.innerHTML = '<span class="ed-etichetta">Struttura: quante colonne</span><div class="ed-colonne-scelta"></div><p class="ed-aiuto">Le larghezze si regolano poi su ogni colonna, misura per misura.</p>';
    var sc = $('.ed-colonne-scelta', d);
    var colonneMax = Math.max(2, Math.min(6, parseInt(D.colonneMax, 10) || 4)), scelte = []; // menu: Impostazioni dell'editor, colonne massime
    for (var ci = 1; ci <= colonneMax; ci++) scelte.push(ci);
    scelte.forEach(function (n) {
      var b = document.createElement('button');
      b.type = 'button'; b.title = n + (n === 1 ? ' colonna' : ' colonne');
      for (var i = 0; i < n; i++) b.appendChild(document.createElement('i'));
      b.addEventListener('click', function () { impostaColonne(sezione, n); });
      sc.appendChild(b);
    });
    return d;
  }

  // Un controllo: etichetta (con il pulsante della misura per i responsive, le linguette Normale / Al passaggio per gli stati), campo, aiuto.
  function controllo(imp, ctl, dopo, def) {
    var d = mk('div', 'ed-controllo ed-tipo-' + ctl.tipo);
    if (ctl.tipo === 'intestazione') {
      d.className = 'ed-controllo ed-intestazione';
      d.appendChild(mk('h5', '', escapeHtml(ctl.etichetta)));
      if (ctl.aiuto) d.appendChild(mk('p', 'ed-aiuto', escapeHtml(ctl.aiuto)));
      return d;
    }
    if (ctl.stati) {
      // Il valore è {normale, hover}: due linguette, ognuna con lo stesso controllo senza "stati".
      var stato = statoAttivo[ctl.nome] || 'normale';
      var testa = mk('div', 'ed-etichetta ed-etichetta-stati');
      testa.appendChild(document.createTextNode(ctl.etichetta));
      var ling = mk('div', 'ed-stati');
      [['normale', 'Normale'], ['hover', 'Al passaggio']].forEach(function (s) {
        var b = mk('button', s[0] === stato ? 'attivo' : ''); b.type = 'button'; b.textContent = s[1];
        b.addEventListener('click', function () { statoAttivo[ctl.nome] = s[0]; var nuovo = controllo(imp, ctl, dopo, def); d.replaceWith(nuovo); });
        ling.appendChild(b);
      });
      testa.appendChild(ling);
      d.appendChild(testa);
      var v = imp[ctl.nome];
      if (!v || typeof v !== 'object' || Array.isArray(v) || !('normale' in v || 'hover' in v)) v = v === undefined ? {} : { normale: v };
      var obj = copia(v);
      var predStati = ctl.predefinito && typeof ctl.predefinito === 'object' && !Array.isArray(ctl.predefinito) && ('normale' in ctl.predefinito || 'hover' in ctl.predefinito);
      var sotto = Object.assign({}, ctl, { nome: stato, stati: false, etichetta: '', sezione: undefined, predefinito: predStati ? (ctl.predefinito[stato] !== undefined ? ctl.predefinito[stato] : (eGruppo(ctl) ? {} : '')) : (stato === 'normale' ? ctl.predefinito : (eGruppo(ctl) ? {} : '')) });
      var interno = controllo(obj, sotto, function () { imp[ctl.nome] = copia(obj); dopo(); }, def);
      interno.classList.add('ed-controllo-stato');
      d.appendChild(interno);
      if (ctl.aiuto) d.appendChild(mk('p', 'ed-aiuto', escapeHtml(ctl.aiuto)));
      return d;
    }
    var l = leggi(imp, ctl);
    var etichetta = document.createElement(ctl.tipo === 'interruttore' ? 'label' : 'div');
    etichetta.className = ctl.tipo === 'interruttore' ? 'ed-etichetta ed-interruttore' : 'ed-etichetta';
    if (ctl.etichetta !== '') etichetta.appendChild(document.createTextNode(ctl.etichetta));
    var mp = null;
    if (ctl.responsive) {
      mp = mk('button', 'ed-misura-pulsante' + (l.proprio ? '' : ' ed-ereditato'), SVG[misura]);
      mp.type = 'button';
      mp.title = (l.proprio ? 'Valore proprio di questa misura (' + ICONE_MISURA[misura] + '). Clic: torna a ereditare dalla misura più grande.' : 'Eredita dalla misura più grande. Modifica il valore per fissarlo su ' + ICONE_MISURA[misura] + '.');
      mp.addEventListener('click', function () { if (l.proprio) { scrivi(imp, ctl, undefined); dopo(); mostraControlli(); } else { prossimaMisura(); } });
      etichetta.appendChild(mp);
    }
    if (eGruppo(ctl) && ctl.tipo !== 'allineamento' && ctl.tipo !== 'scelta_icone' && imp[ctl.nome] !== undefined) {
      var rs = mk('button', 'ed-azzera', '×'); rs.type = 'button'; rs.title = 'Azzera: torna al valore di partenza';
      rs.addEventListener('click', function () { delete imp[ctl.nome]; dopo(); mostraControlli(); });
      etichetta.appendChild(rs);
    }
    var campo = campoControllo(imp, ctl, l, function (v) {
      scrivi(imp, ctl, v); dopo();
      if (ctl.responsive && mp) { var proprio = imp[ctl.nome] && typeof imp[ctl.nome] === 'object' && imp[ctl.nome][misura] !== undefined; mp.classList.toggle('ed-ereditato', !proprio); l.proprio = proprio; }
    });
    if (ctl.tipo === 'interruttore') { etichetta.appendChild(campo); d.appendChild(etichetta); }
    else { if (ctl.etichetta !== '' || ctl.responsive) d.appendChild(etichetta); d.appendChild(campo); }
    if (ctl.aiuto) d.appendChild(mk('p', 'ed-aiuto', escapeHtml(ctl.aiuto)));
    return d;
  }
  function prossimaMisura() {
    var ordine = ['d', 't', 'm'], n = ordine[(ordine.indexOf(misura) + 1) % 3];
    var b = $('.ed-misure button[data-misura="' + n + '"]'); if (b) b.click();
  }

  // ---- pezzi riusati dai gruppi
  function selectDa(opzioni, valore, salva) {
    var c = document.createElement('select');
    Object.keys(opzioni).forEach(function (k) { var o = document.createElement('option'); o.value = k; o.textContent = opzioni[k]; c.appendChild(o); });
    // Un valore che non sta nell'elenco (un file del widget Download eliminato, o non ancora arrivato) si vede, non si perde.
    if (valore !== undefined && valore !== null && String(valore) !== '' && !Object.prototype.hasOwnProperty.call(opzioni, String(valore))) {
      var x = document.createElement('option'); x.value = String(valore); x.textContent = String(valore) + ' (non trovato)'; c.appendChild(x);
    }
    c.value = valore === undefined || valore === null ? '' : String(valore);
    if (c.value !== String(valore === undefined || valore === null ? '' : valore)) c.value = '';
    c.addEventListener('change', function () { salva(c.value); });
    return c;
  }
  function intervallo(ctl, u) {
    var base = { px: [0, 500, 1], '%': [0, 100, 1], em: [0, 20, 0.1], rem: [0, 20, 0.1], vw: [0, 100, 1], vh: [0, 100, 1], '': [0, 5, 0.1], ms: [0, 5000, 10], deg: [-360, 360, 1] };
    if (ctl.tipo === 'dimensioni') base = { px: [-500, 500, 1], '%': [-100, 100, 1], em: [-20, 20, 0.1], rem: [-20, 20, 0.1], vw: [-100, 100, 1], vh: [-100, 100, 1] };
    var i = (ctl.intervalli && ctl.intervalli[u]) || base[u] || [0, 500, 1];
    if (!(ctl.intervalli && ctl.intervalli[u])) {
      i = i.slice();
      if (ctl.min !== undefined) i[0] = ctl.min;
      if (ctl.max !== undefined) i[1] = ctl.max;
      if (ctl.passo !== undefined) i[2] = ctl.passo;
    }
    return i;
  }
  function unitaDi(ctl, pred) {
    var u = ctl.unita || pred || ['px', '%', 'em', 'rem', 'vw', 'vh'];
    return typeof u === 'string' ? [u] : u;
  }
  function selectUnita(unita, u, salva) {
    var s = mk('select', 'ed-unita');
    unita.forEach(function (x) { var o = document.createElement('option'); o.value = x; o.textContent = x === '' ? 'n' : x; s.appendChild(o); });
    s.value = unita.indexOf(u) >= 0 ? u : unita[0];
    s.addEventListener('change', function () { salva(s.value); });
    if (unita.length === 1) s.disabled = true;
    return s;
  }
  // Cursore + casella + unità: il valore è {v, u}.
  function campoMisura(ctl, v, ereditato, salva, unitaPred) {
    var unita = unitaDi(ctl, unitaPred);
    v = v && typeof v === 'object' ? { v: v.v, u: v.u } : (typeof v === 'number' ? { v: v, u: unita[0] } : { v: '', u: unita[0] });
    if (unita.indexOf(v.u) < 0) v.u = unita[0];
    var c = mk('div', 'ed-misura');
    var r = document.createElement('input'); r.type = 'range';
    var n = document.createElement('input'); n.type = 'number';
    function aggiornaIntervallo() { var i = intervallo(ctl, v.u); r.min = i[0]; r.max = i[1]; r.step = i[2]; n.min = i[0]; n.max = i[1]; n.step = i[2]; r.value = v.v === '' || v.v === undefined ? (i[0] > 0 ? i[0] : 0) : v.v; }
    aggiornaIntervallo();
    n.value = v.v === undefined || ereditato ? '' : v.v;
    if (ereditato && v.v !== '' && v.v !== undefined) n.placeholder = v.v;
    var manda = function () { salva(v.v === '' ? undefined : { v: v.v, u: v.u }); };
    r.addEventListener('input', function () { v.v = Number(r.value); n.value = v.v; manda(); });
    n.addEventListener('input', function () { if (n.value === '') { v.v = ''; } else { v.v = Number(n.value); r.value = v.v; } manda(); });
    var su = selectUnita(unita, v.u, function (u) { v.u = u; aggiornaIntervallo(); if (v.v !== '' && v.v !== undefined) { var i = intervallo(ctl, u); v.v = Math.max(i[0], Math.min(i[1], v.v)); n.value = v.v; r.value = v.v; manda(); } });
    c.appendChild(r); c.appendChild(n); c.appendChild(su);
    return c;
  }
  // Quattro lati con lucchetto e unità: il valore è {sopra, destra, sotto, sinistra, u, legati}.
  function campoDimensioni(ctl, v, ereditato, salva, unitaPred) {
    var unita = unitaDi(ctl, unitaPred || ['px', '%', 'em', 'rem']);
    v = Object.assign({ sopra: '', destra: '', sotto: '', sinistra: '', u: unita[0], legati: false }, v && typeof v === 'object' ? v : {});
    if (unita.indexOf(v.u) < 0) v.u = unita[0];
    var c = mk('div', 'ed-dimensioni');
    var lati = [['sopra', 'Sopra'], ['destra', 'Destra'], ['sotto', 'Sotto'], ['sinistra', 'Sinistra']];
    var caselle = {};
    var manda = function () { var vuoto = lati.every(function (l) { return v[l[0]] === ''; }); salva(vuoto ? undefined : copia(v)); };
    lati.forEach(function (l) {
      var w = mk('label', 'ed-lato');
      var i = document.createElement('input'); i.type = 'number'; i.step = 'any';
      i.value = ereditato ? '' : v[l[0]]; if (ereditato && v[l[0]] !== '') i.placeholder = v[l[0]];
      i.addEventListener('input', function () {
        var x = i.value === '' ? '' : Number(i.value);
        if (v.legati) { lati.forEach(function (k) { v[k[0]] = x; if (k[0] !== l[0]) caselle[k[0]].value = i.value; }); } else v[l[0]] = x;
        manda();
      });
      caselle[l[0]] = i;
      w.appendChild(i); w.appendChild(mk('span', '', l[1]));
      c.appendChild(w);
    });
    var luc = mk('button', 'ed-lucchetto' + (v.legati ? ' attivo' : ''), v.legati ? SVG.lucchetto : SVG.lucchettoAperto); luc.type = 'button'; luc.title = 'Lega i quattro lati';
    luc.addEventListener('click', function () { v.legati = !v.legati; luc.className = 'ed-lucchetto' + (v.legati ? ' attivo' : ''); luc.innerHTML = v.legati ? SVG.lucchetto : SVG.lucchettoAperto; if (v.legati && v.sopra !== '') { lati.forEach(function (k) { v[k[0]] = v.sopra; caselle[k[0]].value = v.sopra; }); } manda(); });
    c.appendChild(luc);
    c.appendChild(selectUnita(unita, v.u, function (u) { v.u = u; manda(); }));
    return c;
  }
  // Pulsanti affiancati (allineamento, scelta_icone): il valore è una chiave.
  function campoScelta(opzioni, icone, v, salva) {
    var c = mk('div', 'ed-scelta-icone');
    Object.keys(opzioni).forEach(function (k) {
      var b = mk('button', (String(v === undefined || v === null ? '' : v) === k ? 'attivo' : '')); b.type = 'button'; b.title = opzioni[k];
      var ico = icone && icone[k] !== undefined ? icone[k] : (SVG[k] || null);
      if (ico) { b.innerHTML = ico; b.setAttribute('aria-label', opzioni[k]); } else b.textContent = opzioni[k];
      b.addEventListener('click', function () { $$('button', c).forEach(function (x) { x.classList.remove('attivo'); }); b.classList.add('attivo'); salva(k); });
      c.appendChild(b);
    });
    return c;
  }
  // Colore: cerchietti dei colori globali, poi colore libero con alfa (testo) e campionatore.
  function campoColore(v, salva, placeholder) {
    var c = mk('div', 'ed-colore');
    var glob = (D.globali && D.globali.colori) || [];
    if (glob.length) {
      var riga = mk('div', 'ed-globali');
      glob.forEach(function (g) {
        var b = mk('button', 'ed-globale' + (v === 'globale:' + g.id ? ' attivo' : '')); b.type = 'button'; b.title = g.nome + ' (globale)';
        b.style.background = g.valore;
        b.addEventListener('click', function () { v = 'globale:' + g.id; $$('.ed-globale', riga).forEach(function (x) { x.classList.remove('attivo'); }); b.classList.add('attivo'); t.value = v; salva(v); });
        riga.appendChild(b);
      });
      c.appendChild(riga);
    }
    var r2 = mk('div', 'ed-riga-colore');
    var col = document.createElement('input'); col.type = 'color'; col.value = /^#[0-9a-f]{6}$/i.test(v || '') ? v : '#ffffff';
    var t = document.createElement('input'); t.type = 'text'; t.value = v || ''; t.placeholder = placeholder || 'come il sito';
    var x = mk('button', 'ed-b ed-b-piccolo', '×'); x.type = 'button'; x.title = 'Togli il colore';
    col.addEventListener('input', function () { t.value = col.value; $$('.ed-globale', c).forEach(function (g) { g.classList.remove('attivo'); }); salva(col.value); });
    t.addEventListener('input', function () { var s = t.value.trim(); if (/^#[0-9a-f]{6}$/i.test(s)) col.value = s; $$('.ed-globale', c).forEach(function (g) { g.classList.toggle('attivo', 'globale:' + g.title.replace(/ \(globale\)$/, '') === s); }); salva(s); });
    x.addEventListener('click', function () { t.value = ''; $$('.ed-globale', c).forEach(function (g) { g.classList.remove('attivo'); }); salva(''); });
    r2.appendChild(col); r2.appendChild(t); r2.appendChild(x);
    c.appendChild(r2);
    return c;
  }
  // Famiglia di carattere: font globali, poi l'elenco delle famiglie.
  function campoFamiglia(v, salva) {
    var c = document.createElement('select');
    var o0 = document.createElement('option'); o0.value = ''; o0.textContent = 'Come il sito'; c.appendChild(o0);
    var g = (D.globali && D.globali.font) || [];
    if (g.length) { var og = document.createElement('optgroup'); og.label = 'Caratteri globali'; g.forEach(function (f) { var o = document.createElement('option'); o.value = 'globale:' + f.id; o.textContent = f.nome; og.appendChild(o); }); c.appendChild(og); }
    var fam = (D.globali && D.globali.famiglie) || {};
    var of = document.createElement('optgroup'); of.label = 'Famiglie';
    (Array.isArray(fam) ? fam : Object.keys(fam)).forEach(function (n) { var o = document.createElement('option'); o.value = n; o.textContent = n; o.style.fontFamily = Array.isArray(fam) ? n : fam[n]; of.appendChild(o); });
    c.appendChild(of);
    if (v && !$$('option', c).some(function (o) { return o.value === v; })) { var ox = document.createElement('option'); ox.value = v; ox.textContent = v; c.appendChild(ox); }
    c.value = v || '';
    c.addEventListener('change', function () { salva(c.value); });
    return c;
  }
  // Un sottocampo di un gruppo: scrive dentro obj[nome] e poi chiama manda().
  function sotto(obj, ctlSub, manda, def) {
    return controllo(obj, ctlSub, manda, def);
  }
  function campoOmbra(v, salva, conDiffusione) {
    v = Object.assign({ colore: '', x: 0, y: 0, sfocatura: 10, diffusione: 0, posizione: '' }, v || {});
    var c = mk('div', 'ed-gruppo');
    var manda = function () { salva(v.colore === '' ? undefined : copia(v)); };
    var riga = mk('label', 'ed-etichetta ed-interruttore'); riga.appendChild(document.createTextNode('Ombra attiva'));
    var sw = document.createElement('input'); sw.type = 'checkbox'; sw.checked = v.colore !== '';
    sw.addEventListener('change', function () { v.colore = sw.checked ? 'rgba(0,0,0,.5)' : ''; manda(); var nuovo = campoOmbra(copia(v), salva, conDiffusione); c.replaceWith(nuovo); });
    riga.appendChild(sw); c.appendChild(riga);
    if (v.colore === '') return c;
    var cc = mk('div', 'ed-controllo'); cc.appendChild(mk('div', 'ed-etichetta', 'Colore')); cc.appendChild(campoColore(v.colore, function (x) { v.colore = x || 'rgba(0,0,0,.5)'; manda(); })); c.appendChild(cc);
    [['x', 'Orizzontale', -100, 100], ['y', 'Verticale', -100, 100], ['sfocatura', 'Sfocatura', 0, 200]].concat(conDiffusione ? [['diffusione', 'Diffusione', -100, 100]] : []).forEach(function (f) {
      var d = mk('div', 'ed-controllo'); d.appendChild(mk('div', 'ed-etichetta', f[1]));
      var r = mk('div', 'ed-numero-riga'); var ra = document.createElement('input'); ra.type = 'range'; ra.min = f[2]; ra.max = f[3]; ra.value = v[f[0]];
      var n = document.createElement('input'); n.type = 'number'; n.min = f[2]; n.max = f[3]; n.value = v[f[0]];
      ra.addEventListener('input', function () { v[f[0]] = Number(ra.value); n.value = ra.value; manda(); });
      n.addEventListener('input', function () { v[f[0]] = Number(n.value || 0); ra.value = n.value; manda(); });
      r.appendChild(ra); r.appendChild(n); d.appendChild(r); c.appendChild(d);
    });
    if (conDiffusione) { var dp = mk('div', 'ed-controllo'); dp.appendChild(mk('div', 'ed-etichetta', 'Posizione')); dp.appendChild(selectDa({ '': 'Esterna', inset: 'Interna' }, v.posizione, function (x) { v.posizione = x; manda(); })); c.appendChild(dp); }
    return c;
  }
  function campoTipografia(ctl, v, salva) {
    v = Object.assign({ famiglia: '', dimensione: {}, peso: '', stile: '', trasformazione: '', decorazione: '', interlinea: {}, spaziatura_lettere: {}, spaziatura_parole: {} }, v || {});
    var c = mk('div', 'ed-gruppo');
    var manda = function () { salva(copia(v)); };
    var f = mk('div', 'ed-controllo'); f.appendChild(mk('div', 'ed-etichetta', 'Famiglia')); f.appendChild(campoFamiglia(v.famiglia, function (x) { v.famiglia = x; manda(); })); c.appendChild(f);
    c.appendChild(sotto(v, { nome: 'dimensione', tipo: 'misura', etichetta: 'Dimensione', responsive: true, unita: ['px', 'em', 'rem', 'vw'], intervalli: { px: [0, 300, 1], em: [0, 20, 0.01], rem: [0, 20, 0.01], vw: [0, 30, 0.1] }, predefinito: {} }, manda));
    var p = mk('div', 'ed-controllo'); p.appendChild(mk('div', 'ed-etichetta', 'Peso')); p.appendChild(selectDa({ '': 'Predefinito', '100': '100 (sottile)', '200': '200', '300': '300 (leggero)', '400': '400 (normale)', '500': '500', '600': '600 (semi grassetto)', '700': '700 (grassetto)', '800': '800', '900': '900 (nero)' }, v.peso, function (x) { v.peso = x; manda(); })); c.appendChild(p);
    var due = mk('div', 'ed-due');
    var t1 = mk('div', 'ed-controllo'); t1.appendChild(mk('div', 'ed-etichetta', 'Maiuscole')); t1.appendChild(selectDa({ '': 'Predefinito', none: 'Come scritto', uppercase: 'TUTTO MAIUSCOLO', lowercase: 'tutto minuscolo', capitalize: 'Iniziali Maiuscole' }, v.trasformazione, function (x) { v.trasformazione = x; manda(); })); due.appendChild(t1);
    var t2 = mk('div', 'ed-controllo'); t2.appendChild(mk('div', 'ed-etichetta', 'Stile')); t2.appendChild(selectDa({ '': 'Predefinito', normal: 'Normale', italic: 'Corsivo' }, v.stile, function (x) { v.stile = x; manda(); })); due.appendChild(t2);
    c.appendChild(due);
    var dec = mk('div', 'ed-controllo'); dec.appendChild(mk('div', 'ed-etichetta', 'Decorazione')); dec.appendChild(selectDa({ '': 'Predefinita', none: 'Nessuna', underline: 'Sottolineato', 'line-through': 'Barrato' }, v.decorazione, function (x) { v.decorazione = x; manda(); })); c.appendChild(dec);
    c.appendChild(sotto(v, { nome: 'interlinea', tipo: 'misura', etichetta: 'Interlinea', responsive: true, unita: ['', 'px', 'em'], intervalli: { '': [0.5, 4, 0.05], px: [8, 120, 1], em: [0.5, 4, 0.05] }, predefinito: {} }, manda));
    c.appendChild(sotto(v, { nome: 'spaziatura_lettere', tipo: 'misura', etichetta: 'Spaziatura delle lettere', responsive: true, unita: ['px', 'em'], intervalli: { px: [-5, 20, 0.1], em: [-0.2, 1, 0.01] }, predefinito: {} }, manda));
    c.appendChild(sotto(v, { nome: 'spaziatura_parole', tipo: 'misura', etichetta: 'Spaziatura delle parole', responsive: true, unita: ['px', 'em'], intervalli: { px: [-5, 50, 0.5], em: [-0.5, 3, 0.05] }, predefinito: {} }, manda));
    return c;
  }
  function campoSfondo(ctl, v, salva) {
    v = Object.assign({ tipo: '', colore: '', immagine: '', posizione: 'center center', ripetizione: 'no-repeat', dimensione: 'cover', allegato: 'scroll', sfumatura: {}, velo: '' }, v || {});
    v.sfumatura = Object.assign({ colore1: '', posizione1: 0, colore2: '', posizione2: 100, tipo: 'linear', angolo: 180 }, v.sfumatura || {});
    var c = mk('div', 'ed-gruppo');
    var manda = function () { salva(v.tipo === '' ? undefined : copia(v)); };
    var ridisegna = function () { var nuovo = campoSfondo(ctl, copia(v), salva); c.replaceWith(nuovo); };
    c.appendChild(campoScelta({ '': 'Nessuno', classico: 'Classico', sfumatura: 'Sfumatura' }, { '': '<span class="ed-ico-t">Ø</span>', classico: '<span class="ed-ico-t ed-ico-pieno"></span>', sfumatura: '<span class="ed-ico-t ed-ico-sfumato"></span>' }, v.tipo, function (x) { v.tipo = x; manda(); ridisegna(); }));
    if (v.tipo === 'classico') {
      var cc = mk('div', 'ed-controllo'); cc.appendChild(mk('div', 'ed-etichetta', 'Colore')); cc.appendChild(campoColore(v.colore, function (x) { v.colore = x; manda(); }, 'nessuno')); c.appendChild(cc);
      var ci = mk('div', 'ed-controllo'); ci.appendChild(mk('div', 'ed-etichetta', 'Immagine')); ci.appendChild(campoImmagine(v.immagine, function (x) { v.immagine = x; manda(); if ((x === '') !== (v.immagine === '')) ridisegna(); else if (x && c.querySelectorAll('.ed-sfondo-img').length === 0) ridisegna(); })); c.appendChild(ci);
      if (v.immagine) {
        var dett = mk('div', 'ed-sfondo-img');
        [['posizione', 'Posizione', { 'center center': 'Centro', 'center left': 'Centro a sinistra', 'center right': 'Centro a destra', 'top center': 'Alto', 'top left': 'Alto a sinistra', 'top right': 'Alto a destra', 'bottom center': 'Basso', 'bottom left': 'Basso a sinistra', 'bottom right': 'Basso a destra' }],
         ['ripetizione', 'Ripetizione', { 'no-repeat': 'Nessuna', repeat: 'Ripeti', 'repeat-x': 'In orizzontale', 'repeat-y': 'In verticale' }],
         ['dimensione', 'Dimensione', { cover: 'Copri (cover)', contain: 'Contieni', auto: 'Originale' }],
         ['allegato', 'Allegato', { scroll: 'Scorre con la pagina', fixed: 'Fisso (parallasse)' }]].forEach(function (f) {
          var d = mk('div', 'ed-controllo'); d.appendChild(mk('div', 'ed-etichetta', f[1])); d.appendChild(selectDa(f[2], v[f[0]], function (x) { v[f[0]] = x; manda(); })); dett.appendChild(d);
        });
        var cv = mk('div', 'ed-controllo'); cv.appendChild(mk('div', 'ed-etichetta', "Velo sull'immagine (colore, anche con trasparenza)")); cv.appendChild(campoColore(v.velo, function (x) { v.velo = x; manda(); }, 'nessuno')); dett.appendChild(cv);
        c.appendChild(dett);
      }
    } else if (v.tipo === 'sfumatura') {
      var s = v.sfumatura;
      var c1 = mk('div', 'ed-controllo'); c1.appendChild(mk('div', 'ed-etichetta', 'Primo colore')); c1.appendChild(campoColore(s.colore1, function (x) { s.colore1 = x; manda(); })); c.appendChild(c1);
      c.appendChild(cursoreSemplice('Posizione del primo colore (%)', 0, 100, s.posizione1, function (x) { s.posizione1 = x; manda(); }));
      var c2 = mk('div', 'ed-controllo'); c2.appendChild(mk('div', 'ed-etichetta', 'Secondo colore')); c2.appendChild(campoColore(s.colore2, function (x) { s.colore2 = x; manda(); })); c.appendChild(c2);
      c.appendChild(cursoreSemplice('Posizione del secondo colore (%)', 0, 100, s.posizione2, function (x) { s.posizione2 = x; manda(); }));
      var ct = mk('div', 'ed-controllo'); ct.appendChild(mk('div', 'ed-etichetta', 'Tipo')); ct.appendChild(selectDa({ linear: 'Lineare', radial: 'Radiale' }, s.tipo, function (x) { s.tipo = x; manda(); ridisegna(); })); c.appendChild(ct);
      if (s.tipo !== 'radial') c.appendChild(cursoreSemplice('Angolo (gradi)', 0, 360, s.angolo, function (x) { s.angolo = x; manda(); }));
    }
    return c;
  }
  function cursoreSemplice(etichetta, min, max, v, salva, passo) {
    var d = mk('div', 'ed-controllo'); d.appendChild(mk('div', 'ed-etichetta', escapeHtml(etichetta)));
    var r = mk('div', 'ed-numero-riga'); var ra = document.createElement('input'); ra.type = 'range'; ra.min = min; ra.max = max; ra.step = passo || 1; ra.value = v === '' || v === undefined ? min : v;
    var n = document.createElement('input'); n.type = 'number'; n.min = min; n.max = max; n.step = passo || 1; n.value = v === undefined ? '' : v;
    ra.addEventListener('input', function () { n.value = ra.value; salva(Number(ra.value)); });
    n.addEventListener('input', function () { if (n.value === '') salva(''); else { ra.value = n.value; salva(Number(n.value)); } });
    r.appendChild(ra); r.appendChild(n); d.appendChild(r);
    return d;
  }
  function campoBordo(ctl, v, salva) {
    v = Object.assign({ tipo: '', larghezza: {}, colore: '', raggio: {} }, v || {});
    var c = mk('div', 'ed-gruppo');
    var manda = function () { salva(copia(v)); };
    var ct = mk('div', 'ed-controllo'); ct.appendChild(mk('div', 'ed-etichetta', 'Tipo di bordo')); ct.appendChild(selectDa({ '': 'Predefinito', none: 'Nessuno', solid: 'Continuo', dashed: 'Tratteggiato', dotted: 'Puntinato', double: 'Doppio' }, v.tipo, function (x) { v.tipo = x; manda(); var nuovo = campoBordo(ctl, copia(v), salva); c.replaceWith(nuovo); })); c.appendChild(ct);
    if (v.tipo !== '' && v.tipo !== 'none') {
      c.appendChild(sotto(v, { nome: 'larghezza', tipo: 'dimensioni', etichetta: 'Spessore', responsive: true, unita: ['px'], intervalli: { px: [0, 50, 1] }, predefinito: {} }, manda));
      var cc = mk('div', 'ed-controllo'); cc.appendChild(mk('div', 'ed-etichetta', 'Colore del bordo')); cc.appendChild(campoColore(v.colore, function (x) { v.colore = x; manda(); })); c.appendChild(cc);
    } else if (v.tipo === '') {
      var cc2 = mk('div', 'ed-controllo'); cc2.appendChild(mk('div', 'ed-etichetta', 'Colore del bordo (se il bordo esiste già)')); cc2.appendChild(campoColore(v.colore, function (x) { v.colore = x; manda(); })); c.appendChild(cc2);
    }
    c.appendChild(sotto(v, { nome: 'raggio', tipo: 'dimensioni', etichetta: 'Raggio degli angoli', responsive: true, unita: ['px', '%'], intervalli: { px: [0, 400, 1], '%': [0, 100, 1] }, predefinito: {} }, manda));
    return c;
  }
  function campoTrasformazione(v, salva) {
    v = Object.assign({ rotazione: 0, scala: 1, x: '', y: '', u: 'px' }, v || {});
    var c = mk('div', 'ed-gruppo');
    var manda = function () { salva(copia(v)); };
    c.appendChild(cursoreSemplice('Rotazione (gradi)', -180, 180, v.rotazione, function (x) { v.rotazione = x === '' ? 0 : x; manda(); }));
    c.appendChild(cursoreSemplice('Scala (1 = normale)', 0.1, 3, v.scala, function (x) { v.scala = x === '' ? 1 : x; manda(); }, 0.05));
    c.appendChild(cursoreSemplice('Spostamento orizzontale', -300, 300, v.x, function (x) { v.x = x; manda(); }));
    c.appendChild(cursoreSemplice('Spostamento verticale', -300, 300, v.y, function (x) { v.y = x; manda(); }));
    var cu = mk('div', 'ed-controllo'); cu.appendChild(mk('div', 'ed-etichetta', 'Unità dello spostamento')); cu.appendChild(selectDa({ px: 'px', '%': '%' }, v.u, function (x) { v.u = x; manda(); })); c.appendChild(cu);
    return c;
  }
  function campoImmagine(v, salva) {
    var c = mk('div', 'ed-riga-immagine');
    var img = document.createElement('img'); img.src = v || ''; img.alt = ''; img.hidden = !v;
    var u = document.createElement('input'); u.type = 'url'; u.value = v || ''; u.placeholder = 'Indirizzo dell\'immagine';
    var b = mk('button', 'ed-b ed-b-piccolo', 'Scegli'); b.type = 'button';
    var x = mk('button', 'ed-b ed-b-piccolo', '×'); x.type = 'button'; x.title = 'Togli l\'immagine'; x.hidden = !v;
    u.addEventListener('input', function () { salva(u.value.trim()); img.src = u.value; img.hidden = !u.value; x.hidden = !u.value; });
    b.addEventListener('click', function () { apriMedia(function (url) { u.value = url; img.src = url; img.hidden = false; x.hidden = false; salva(url); }); });
    x.addEventListener('click', function () { u.value = ''; img.hidden = true; x.hidden = true; salva(''); });
    c.appendChild(img); c.appendChild(u); c.appendChild(b); c.appendChild(x);
    return c;
  }
  function campoIcona(v, salva) {
    var set = D.icone || {};
    var nomi = Object.keys(set);
    var c = mk('div', 'ed-icona');
    if (!nomi.length) {
      var t = document.createElement('input'); t.type = 'text'; t.value = v || ''; t.placeholder = 'Nome dell\'icona (il set non è installato)';
      t.addEventListener('input', function () { salva(t.value.trim()); });
      c.appendChild(t);
      return c;
    }
    var testa = mk('div', 'ed-icona-testa');
    var ant = mk('span', 'ed-icona-scelta', v && set[v] ? set[v] : ''); testa.appendChild(ant);
    var nome = mk('span', 'ed-icona-nome', escapeHtml(v || 'nessuna')); testa.appendChild(nome);
    var apri = mk('button', 'ed-b ed-b-piccolo', 'Scegli'); apri.type = 'button'; testa.appendChild(apri);
    var via = mk('button', 'ed-b ed-b-piccolo', '×'); via.type = 'button'; via.title = 'Nessuna icona'; testa.appendChild(via);
    c.appendChild(testa);
    var griglia = mk('div', 'ed-icona-griglia'); griglia.hidden = true;
    var cerca = document.createElement('input'); cerca.type = 'search'; cerca.placeholder = 'Cerca fra ' + nomi.length + ' icone';
    var lista = mk('div', 'ed-icona-lista');
    function disegna(q) {
      lista.innerHTML = '';
      nomi.filter(function (n) { return !q || n.indexOf(q) >= 0; }).slice(0, 400).forEach(function (n) {
        var b = mk('button', 'ed-icona-voce' + (n === v ? ' attivo' : ''), set[n]); b.type = 'button'; b.title = n;
        b.addEventListener('click', function () { v = n; ant.innerHTML = set[n]; nome.textContent = n; griglia.hidden = true; salva(n); });
        lista.appendChild(b);
      });
    }
    cerca.addEventListener('input', function () { disegna(cerca.value.trim().toLowerCase()); });
    griglia.appendChild(cerca); griglia.appendChild(lista); c.appendChild(griglia);
    apri.addEventListener('click', function () { griglia.hidden = !griglia.hidden; if (!griglia.hidden) { disegna(''); cerca.focus(); } });
    via.addEventListener('click', function () { v = ''; ant.innerHTML = ''; nome.textContent = 'nessuna'; salva(''); });
    return c;
  }
  function campoGalleria(v, salva) {
    var voci = Array.isArray(v) ? copia(v) : [];
    var c = mk('div', 'ed-galleria');
    function manda() { salva(copia(voci)); }
    function disegna() {
      c.innerHTML = '';
      var g = mk('div', 'ed-galleria-griglia');
      voci.forEach(function (x, i) {
        var d = mk('div', 'ed-galleria-voce');
        var img = document.createElement('img'); img.src = x.immagine; img.alt = x.alt || ''; img.title = x.didascalia || x.alt || '';
        var via = mk('button', 'ed-galleria-via', '×'); via.type = 'button'; via.title = 'Togli';
        via.addEventListener('click', function () { voci.splice(i, 1); manda(); disegna(); });
        var mod = mk('button', 'ed-galleria-mod', 'Testo'); mod.type = 'button'; mod.title = 'Testo alternativo e didascalia';
        mod.addEventListener('click', function () {
          var alt = prompt('Testo alternativo (per chi non vede e per Google):', x.alt || ''); if (alt === null) return;
          var did = prompt('Didascalia (facoltativa):', x.didascalia || ''); if (did === null) return;
          var link = prompt('Link (facoltativo):', x.link || ''); if (link === null) return;
          x.alt = alt; x.didascalia = did; x.link = link; manda(); disegna();
        });
        d.appendChild(img); d.appendChild(via); d.appendChild(mod); g.appendChild(d);
      });
      c.appendChild(g);
      var agg = mk('button', 'ed-b', '+ Aggiungi immagine'); agg.type = 'button';
      agg.addEventListener('click', function () { apriMedia(function (url) { voci.push({ immagine: url, alt: '', didascalia: '', link: '' }); manda(); disegna(); }); });
      c.appendChild(agg);
      if (typeof Sortable !== 'undefined' && voci.length > 1) new Sortable(g, { draggable: '.ed-galleria-voce', animation: 120, onEnd: function (ev) { var x = voci.splice(ev.oldIndex, 1)[0]; voci.splice(ev.newIndex, 0, x); manda(); disegna(); } });
    }
    disegna();
    return c;
  }

  function campoControllo(imp, ctl, l, salva) {
    var v = l.valore, c;
    switch (ctl.tipo) {
      case 'testo':
        c = document.createElement('input'); c.type = 'text'; c.value = v || ''; c.maxLength = 500;
        c.addEventListener('input', function () { salva(c.value); });
        return c;
      case 'testolungo':
        c = document.createElement('textarea'); c.value = v || '';
        c.addEventListener('input', function () { salva(c.value); });
        return c;
      case 'codice':
        c = document.createElement('textarea'); c.className = 'ed-codice'; c.value = v || ''; c.spellcheck = false;
        c.addEventListener('input', function () { salva(c.value); });
        return c;
      case 'numero':
        c = mk('div', 'ed-numero-riga');
        var r = document.createElement('input'); r.type = 'range'; r.min = ctl.min !== undefined ? ctl.min : 0; r.max = ctl.max !== undefined ? ctl.max : 100; r.step = ctl.passo || 1; r.value = v === undefined || v === '' ? r.min : v;
        var n = document.createElement('input'); n.type = 'number'; n.min = r.min; n.max = r.max; n.step = r.step; n.value = v === undefined ? '' : v;
        if (l.ereditato) { n.placeholder = v; n.value = ''; }
        r.addEventListener('input', function () { n.value = r.value; salva(Number(r.value)); });
        n.addEventListener('input', function () { if (n.value === '') salva(undefined); else { r.value = n.value; salva(Number(n.value)); } });
        c.appendChild(r); c.appendChild(n);
        return c;
      case 'interruttore':
        c = document.createElement('input'); c.type = 'checkbox'; c.checked = !!v;
        c.addEventListener('change', function () { salva(c.checked); });
        return c;
      case 'scelta':
        return selectDa(ctl.opzioni, v, salva);
      case 'scelta_icone':
        return campoScelta(ctl.opzioni, ctl.icone, v, salva);
      case 'allineamento':
        var opz = ctl.opzioni || (ctl.flex ? Object.assign({ '': 'Predefinito', 'flex-start': 'Inizio', center: 'Centro', 'flex-end': 'Fine' }, ctl.stretch ? { stretch: 'Allarga' } : {}) : Object.assign({ '': 'Predefinito', left: 'Sinistra', center: 'Centro', right: 'Destra' }, ctl.giustifica ? { justify: 'Giustificato' } : {}));
        return campoScelta(opz, Object.assign({ '': '<span class="ed-ico-t">Ø</span>' }, ctl.icone || {}), v, salva);
      case 'colore':
        return campoColore(v || '', salva);
      case 'carattere':
        return campoFamiglia(v || '', salva);
      case 'immagine':
        return campoImmagine(v, salva);
      case 'link':
        c = mk('div', 'ed-riga-link');
        v = v && typeof v === 'object' ? v : { url: '', nuova: false, nofollow: false };
        var lu = document.createElement('input'); lu.type = 'text'; lu.value = v.url || ''; lu.placeholder = '/pagina/ oppure https://…';
        var ln = mk('label', 'ed-spunta'); var lc = document.createElement('input'); lc.type = 'checkbox'; lc.checked = !!v.nuova; ln.appendChild(lc); ln.appendChild(document.createTextNode('Apri in una nuova scheda'));
        var lf = mk('label', 'ed-spunta'); var lcf = document.createElement('input'); lcf.type = 'checkbox'; lcf.checked = !!v.nofollow; lf.appendChild(lcf); lf.appendChild(document.createTextNode('Nofollow (Google non lo segue)'));
        var aggiorna = function () { salva({ url: lu.value.trim(), nuova: lc.checked, nofollow: lcf.checked }); };
        lu.addEventListener('input', aggiorna); lc.addEventListener('change', aggiorna); lcf.addEventListener('change', aggiorna);
        c.appendChild(lu); c.appendChild(ln); c.appendChild(lf);
        return c;
      case 'ricco':
        c = mk('div', 'ed-quill');
        var ed = document.createElement('div'); c.appendChild(ed);
        ed.innerHTML = v || '';
        setTimeout(function () {
          var q = new Quill(ed, { theme: 'snow', modules: { toolbar: [[{ header: [2, 3, 4, false] }], ['bold', 'italic', 'underline', 'link'], [{ list: 'ordered' }, { list: 'bullet' }], [{ align: [] }], ['blockquote', 'clean']] } });
          q.on('text-change', function (d, o, fonte) { if (fonte === 'user') salva(q.getSemanticHTML ? q.getSemanticHTML() : q.root.innerHTML); });
          quillVivi.push(q);
        }, 0);
        return c;
      case 'ripetitore':
        return ripetitore(imp, ctl, Array.isArray(v) ? v : [], salva);
      case 'misura': case 'cursore':
        return campoMisura(ctl, v, l.ereditato, salva);
      case 'dimensioni':
        return campoDimensioni(ctl, v, l.ereditato, salva);
      case 'tipografia':
        return campoTipografia(ctl, v, salva);
      case 'sfondo':
        return campoSfondo(ctl, v, salva);
      case 'bordo':
        return campoBordo(ctl, v, salva);
      case 'ombra':
        return campoOmbra(v, salva, true);
      case 'ombra_testo':
        return campoOmbra(v, salva, false);
      case 'trasformazione':
        return campoTrasformazione(v, salva);
      case 'icona':
        return campoIcona(v || '', salva);
      case 'galleria':
        return campoGalleria(v, salva);
      case 'data_ora':
        c = document.createElement('input'); c.type = 'datetime-local'; c.value = v ? String(v).replace(' ', 'T') : '';
        c.addEventListener('input', function () { salva(c.value ? c.value.replace('T', ' ') : ''); });
        return c;
    }
    c = document.createElement('input'); c.type = 'text'; c.value = v || '';
    c.addEventListener('input', function () { salva(c.value); });
    return c;
  }

  function ripetitore(imp, ctl, voci, salva) {
    var c = mk('div', 'ed-ripetitore');
    // Le voci si copiano: l'elenco salvato è sempre quello che si vede.
    voci = copia(voci);
    var aperta = null;
    function salvaTutto() { salva(copia(voci)); }
    function disegnaVoci() {
      c.innerHTML = '';
      voci.forEach(function (voce, i) {
        var d = mk('div', 'ed-voce');
        var titolo = voce[ctl.titolo_voce] || ctl.campi[0] && voce[ctl.campi[0].nome] || ('Voce ' + (i + 1));
        var testa = mk('div', 'ed-voce-testa', '<span class="ed-voce-maniglia">⋮⋮</span><b></b><button type="button" data-az="duplica" title="Duplica">⧉</button><button type="button" data-az="elimina" title="Elimina">×</button>');
        $('b', testa).textContent = String(titolo).replace(/\*/g, '').slice(0, 60);
        var corpo = mk('div', 'ed-voce-corpo'); corpo.hidden = aperta !== i;
        if (aperta === i) ctl.campi.forEach(function (campo) {
          if (campo.condizione || campo.se) { var okc = Object.keys(campo.condizione || campo.se).every(function (k) { var neg = k.charAt(0) === '!', nome = neg ? k.slice(1) : k; var att = voce[nome] === undefined ? '' : String(voce[nome]); var attesi = [].concat((campo.condizione || campo.se)[k]); var ok = attesi.some(function (x) { return String(x) === att; }); return neg ? !ok : ok; }); if (!okc) return; }
          corpo.appendChild(controllo(voce, Object.assign({}, campo, { responsive: false, sezione: undefined }), function () { salvaTutto(); $('b', testa).textContent = String(voce[ctl.titolo_voce] || titolo).replace(/\*/g, '').slice(0, 60); if (campo.condizione || campo.se || ctl.campi.some(function (x) { var cc = x.condizione || x.se; return cc && Object.keys(cc).some(function (k) { return k.replace(/^!/, '') === campo.nome; }); })) disegnaVoci(); }));
        });
        testa.addEventListener('click', function (e) {
          var b = e.target.closest('button');
          if (b && b.dataset.az === 'elimina') { voci.splice(i, 1); aperta = null; salvaTutto(); disegnaVoci(); return; }
          if (b && b.dataset.az === 'duplica') { voci.splice(i + 1, 0, copia(voce)); salvaTutto(); disegnaVoci(); return; }
          if (e.target.classList.contains('ed-voce-maniglia')) return;
          aperta = aperta === i ? null : i; disegnaVoci();
        });
        d.appendChild(testa); d.appendChild(corpo); c.appendChild(d);
      });
      var agg = mk('button', 'ed-b', '+ Aggiungi'); agg.type = 'button';
      agg.addEventListener('click', function () {
        var n = {};
        ctl.campi.forEach(function (campo) { n[campo.nome] = campo.predefinito; });
        voci.push(n); aperta = voci.length - 1; salvaTutto(); disegnaVoci();
      });
      c.appendChild(agg);
      if (typeof Sortable !== 'undefined') new Sortable(c, { handle: '.ed-voce-maniglia', draggable: '.ed-voce', animation: 120, onEnd: function (ev) {
        var v = voci.splice(ev.oldIndex, 1)[0]; voci.splice(ev.newIndex, 0, v); aperta = null; salvaTutto(); disegnaVoci();
      } });
    }
    disegnaVoci();
    return c;
  }

  // ------------------------------------------------------------ navigatore
  function disegnaNavigatore() {
    var n = $('#ed-navigatore');
    function riassunto(el) {
      var i = el.impostazioni || {};
      var t = i.testo || i.titolo || (i.voci && i.voci.length + ' voci') || i.ancora || '';
      if (el.tipo === 'colonna') { var l = valoreResponsive(i.larghezza || { d: 100 }, 'd').valore; t = (l || 100) + '%'; }
      if (el.tipo === 'sezione') t = i.ancora ? '#' + i.ancora : (el.figli.length + (el.figli.length === 1 ? ' colonna' : ' colonne'));
      return String(t).replace(/\*/g, '').slice(0, 40);
    }
    function voce(el) {
      var nascosto = el.impostazioni && (el.impostazioni.nascondi_d || el.impostazioni.nascondi_t || el.impostazioni.nascondi_m);
      var h = '<li><div class="ed-nav-voce' + (el.id === selezionato ? ' attivo' : '') + '" data-id="' + el.id + '"><i>' + (DEF[el.tipo].icona || '') + '</i>' + DEF[el.tipo].etichetta + ' <small>' + escapeHtml(riassunto(el)) + '</small>' + (nascosto ? '<span class="ed-nav-occhio" title="Nascosto su qualche misura">\uD83D\uDC41</span>' : '') + '</div>';
      if (el.figli) h += '<ul>' + el.figli.map(voce).join('') + '</ul>';
      return h + '</li>';
    }
    n.innerHTML = albero.elementi.length ? '<ul class="ed-nav-albero">' + albero.elementi.map(voce).join('') + '</ul>' : '<p class="ed-aiuto">La pagina è vuota.</p>';
    $$('.ed-nav-voce', n).forEach(function (v) {
      v.addEventListener('click', function () { selezionato = v.dataset.id; mandaTela({ tipo: 'seleziona', id: selezionato }); mostraControlli(); disegnaNavigatore(); });
      v.addEventListener('dblclick', function () { apriPannello('modifica'); });
    });
  }
  function escapeHtml(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  $('#ed-aggiungi-sezione').addEventListener('click', function () { aggiungiSezione(''); });

  // ------------------------------------------------------------ misure, storia, tasti
  $$('.ed-misure button').forEach(function (b) {
    b.addEventListener('click', function () {
      misura = b.dataset.misura;
      $$('.ed-misure button').forEach(function (x) { x.classList.toggle('attivo', x === b); });
      $('#ed-tela').dataset.misura = misura;
      mostraControlli();
    });
  });
  $('#ed-annulla').addEventListener('click', annulla);
  $('#ed-ripeti').addEventListener('click', ripeti);
  document.addEventListener('keydown', function (e) {
    var inCampo = /INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.target.isContentEditable;
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); salva(false); }
    else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !inCampo) { e.preventDefault(); e.shiftKey ? ripeti() : annulla(); }
    else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y' && !inCampo) { e.preventDefault(); ripeti(); }
    else if (e.key === 'Delete' && selezionato && !inCampo) { azione('elimina', selezionato); }
  });

  // ------------------------------------------------------------ salvataggio
  function salva(pubblica) {
    if (salvando) return Promise.resolve();
    if (pubblica && !confirm('Pubblicare questa versione? Chi visita il sito la vede subito.')) return Promise.resolve();
    salvando = true;
    var istantanea = JSON.stringify(albero);
    stato(pubblica ? 'Pubblico…' : 'Salvo…');
    return api('salva', { albero: istantanea, pubblica: pubblica ? 1 : 0, nota: '' }).then(function (j) {
      ultimoSalvato = istantanea;
      if (pRev && !pRev.hidden) caricaRevisioni(); // versioni
      sporco = JSON.stringify(albero) !== istantanea;
      if (pubblica) { pubblicata = true; bozzaInSospeso = false; D.stato = 'published'; D.pubblicato = true; } else bozzaInSospeso = pubblicata; // modelli: Pubblica aggiorna anche lo stato mostrato nelle Impostazioni
      aggiornaStato();
      if (pubblica) stato('Pubblicata alle ' + j.quando);
      else stato('Bozza salvata alle ' + j.quando, pubblicata ? 'bozza' : '');
    }).catch(function (e) { stato(e.message, 'errore'); alert(e.message); }).then(function () { salvando = false; });
  }
  $('#ed-salva').addEventListener('click', function () { salva(false); });
  $('#ed-pubblica').addEventListener('click', function () { salva(true); });
  setInterval(function () { if (sporco && !salvando) salva(false); }, 45000);
  window.addEventListener('beforeunload', function (e) { if (sporco) { e.preventDefault(); e.returnValue = ''; } });

  // ------------------------------------------------------------ impostazioni
  var dImp = $('#ed-dialogo-impostazioni'), fImp = $('#ed-form-impostazioni');
  $('#ed-apri-impostazioni').addEventListener('click', function () {
    fImp.titolo.value = D.titolo; fImp.slug.value = D.slug; fImp.seo_titolo.value = D.seo.titolo; fImp.seo_descrizione.value = D.seo.descrizione;
    fImp.robots.value = D.seo.robots; fImp.stato.value = D.stato;
    if (fImp.header_id) { fImp.header_id.value = String(D.headerId || 0); fImp.footer_id.value = String(D.footerId || 0); }
    // 2.1: un contenitore di un modulo (una lezione): nella finestra resta solo il titolo.
    if (D.contenitore) { ['slug', 'seo_titolo', 'seo_descrizione', 'robots', 'stato'].forEach(function (n) { var c = fImp[n] && fImp[n].closest('label'); if (c) { c.hidden = true; c.style.display = 'none'; } }); var h3 = fImp.querySelector('h2'); if (h3) h3.textContent = 'Impostazioni: ' + D.contenitore.uno; }
    if (D.modello) { ['slug', 'seo_titolo', 'seo_descrizione', 'robots'].forEach(function (n) { var c = fImp[n] && fImp[n].closest('label'); if (c) { c.hidden = true; c.style.display = 'none'; } }); var h2 = fImp.querySelector('h2'); if (h2) h2.textContent = 'Impostazioni del modello'; } // modelli: il CSS dà display:block alle label, hidden da solo non basta
    $('#ed-impostazioni-errore').hidden = true;
    dImp.showModal();
    controlloSeo();
  });
  // Il punteggio SEO del contenuto com'è nella tela (includes/seo.php), aggiornato mentre si scrivono titolo e descrizione.
  var seoTimer = null;
  function controlloSeo() {
    var box = $('#ed-seo');
    if (!box) return;
    clearTimeout(seoTimer);
    seoTimer = setTimeout(function () {
      box.innerHTML = '<p class="ed-aiuto">Controllo SEO in corso…</p>';
      api('seo', { albero: JSON.stringify(albero), titolo: fImp.titolo.value, seo_titolo: fImp.seo_titolo.value, seo_descrizione: fImp.seo_descrizione.value, slug: fImp.slug.value }).then(function (j) { box.innerHTML = j.html || ''; }).catch(function (e) { box.innerHTML = '<p class="ed-errore">' + escapeHtml(e.message) + '</p>'; });
    }, 300);
  }
  ['titolo', 'seo_titolo', 'seo_descrizione', 'slug'].forEach(function (n) { if (fImp[n]) fImp[n].addEventListener('input', controlloSeo); });
  fImp.addEventListener('submit', function (e) {
    e.preventDefault();
    var campi = { titolo: fImp.titolo.value, seo_titolo: fImp.seo_titolo.value, seo_descrizione: fImp.seo_descrizione.value, robots: fImp.robots.value, stato: fImp.stato.value };
    if (!fImp.slug.disabled) campi.slug = fImp.slug.value;
    if (fImp.header_id) { campi.header_id = fImp.header_id.value; campi.footer_id = fImp.footer_id.value; }
    api('impostazioni', campi).then(function (j) {
      // 1.4.2: il titolo di un articolo sta nella testata della tela, scritta dal server: con un titolo nuovo la tela si ricarica.
      if (campi.titolo !== D.titolo) { telaPronta = false; $('#ed-iframe').contentWindow.location.reload(); }
      D.titolo = campi.titolo; D.slug = j.slug; D.percorso = j.percorso; D.stato = campi.stato;
      if (fImp.header_id) { D.headerId = Number(campi.header_id); D.footerId = Number(campi.footer_id); disegna(); }
      D.seo = { titolo: campi.seo_titolo, descrizione: campi.seo_descrizione, robots: campi.robots };
      $('#ed-titolo-pagina').textContent = D.titolo; $('#ed-percorso').textContent = D.percorso; if (!D.contenitore) $('#ed-vedi').href = D.percorso + (D.pubblicato === false ? '?preview=1' : '');
      dImp.close();
    }).catch(function (er) { var p = $('#ed-impostazioni-errore'); p.textContent = er.message; p.hidden = false; });
  });

  // ------------------------------------------------------------ revisioni (versioni: pannello laterale, stessa tabella della pagina Versioni)
  var pRev = $('#ed-revisioni');
  function dataBreve(s) { var m = /^(\d{4})-(\d{2})-(\d{2}) (\d{2}:\d{2})/.exec(s || ''); return m ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] : (s || ''); }
  function caricaRevisioni() {
    var el = $('#ed-elenco-revisioni');
    el.innerHTML = '<p class="ed-aiuto">Carico…</p>';
    api('revisioni').then(function (j) {
      if (j.url) $('#ed-tutte-versioni').href = j.url;
      if (!j.revisioni.length) { el.innerHTML = '<p class="ed-aiuto">Nessuna versione ancora: la prima nasce al primo salvataggio.</p>'; return; }
      el.innerHTML = '';
      j.revisioni.forEach(function (r, i) {
        var d = document.createElement('div'); d.className = 'ed-revisione' + (r.pubblicata ? ' ed-revisione-pubblicata' : '');
        d.innerHTML = '<div class="ed-revisione-riga"><span><b>' + escapeHtml(dataBreve(r.creato)) + '</b>' + (r.pubblicata ? ' <i class="ed-revisione-tag">pubblicata</i>' : '') + (i === 0 ? ' <small>(l\'ultima)</small>' : '')
          + '<br><small>' + escapeHtml(r.autore || '') + (r.nota ? ' · ' + escapeHtml(r.nota) : '') + ' · ' + Math.max(1, Math.round(r.peso / 1024)) + ' KB</small></span></div>';
        var az = document.createElement('div'); az.className = 'ed-revisione-azioni';
        var bc = document.createElement('button'); bc.type = 'button'; bc.className = 'ed-b ed-b-piccolo'; bc.textContent = 'Cosa è cambiato';
        var box = document.createElement('div'); box.className = 'ed-revisione-diff'; box.hidden = true;
        bc.addEventListener('click', function () {
          if (!box.hidden) { box.hidden = true; return; }
          box.hidden = false; box.innerHTML = '<p class="ed-aiuto">Confronto…</p>';
          api('confronta', { versione: r.id, con: 'precedente' }).then(function (x) { box.innerHTML = x.html; }).catch(function (e) { box.innerHTML = '<p class="ed-errore">' + escapeHtml(e.message) + '</p>'; });
        });
        var bt = document.createElement('button'); bt.type = 'button'; bt.className = 'ed-b ed-b-piccolo'; bt.textContent = 'Rispetto alla tela';
        bt.addEventListener('click', function () {
          box.hidden = false; box.innerHTML = '<p class="ed-aiuto">Confronto…</p>';
          api('confronta', { versione: r.id, con: 'tela', albero: JSON.stringify(albero) }).then(function (x) { box.innerHTML = x.html; }).catch(function (e) { box.innerHTML = '<p class="ed-errore">' + escapeHtml(e.message) + '</p>'; });
        });
        var b = document.createElement('button'); b.type = 'button'; b.className = 'ed-b ed-b-piccolo ed-b-primo'; b.textContent = 'Carica nella tela';
        b.addEventListener('click', function () {
          api('revisione', { revisione: r.id }).then(function (x) { albero = sistemaAlbero(x.albero); selezionato = null; cambiato(null); mostraControlli(); stato('Versione ' + r.id + ' nella tela: pubblica per renderla visibile', 'bozza'); }).catch(function (e) { alert(e.message); });
        });
        var bn = document.createElement('button'); bn.type = 'button'; bn.className = 'ed-b ed-b-piccolo'; bn.textContent = r.nota_mano ? 'Cambia nota' : 'Aggiungi nota';
        bn.addEventListener('click', function () {
          var t = prompt('Una nota per ricordare questa versione (una versione con nota non viene mai cancellata):', r.nota_mano ? r.nota : '');
          if (t === null) return;
          api('nota', { versione: r.id, testo: t }).then(caricaRevisioni).catch(function (e) { alert(e.message); });
        });
        az.appendChild(bc); az.appendChild(bt); az.appendChild(b); az.appendChild(bn);
        d.appendChild(az); d.appendChild(box); el.appendChild(d);
      });
    }).catch(function (e) { el.innerHTML = '<p class="ed-errore">' + escapeHtml(e.message) + '</p>'; });
  }
  $('#ed-apri-revisioni').addEventListener('click', function () {
    if (!pRev.hidden) { pRev.hidden = true; return; }
    pRev.hidden = false;
    caricaRevisioni();
  });
  $('#ed-chiudi-revisioni').addEventListener('click', function () { pRev.hidden = true; });
  var togli = $('#ed-togli-editor');
  if (togli) togli.addEventListener('click', function () {
    if (!confirm('La pagina torna a mostrare il contenuto di prima (quello della scheda). La struttura fatta con l\'editor si perde; le versioni restano. Continuare?')) return;
    api('togli').then(function () { sporco = false; location.href = D.contenitore ? D.contenitore.scheda : 'contenuto.php?tipo=' + (D.tipo || 'pagine') + '&id=' + D.id; }).catch(function (e) { alert(e.message); });
  });

  // ------------------------------------------------------------ immagini
  var dMedia = $('#ed-dialogo-media'), mediaCallback = null, mediaCartelleFatte = false, mediaTimer = null;
  function apriMedia(cb) {
    mediaCallback = cb;
    $('#ed-media-errore').hidden = true;
    $('#ed-media-url').value = '';
    dMedia.showModal();
    caricaMedia(1);
  }
  function caricaMedia(pagina) {
    var g = $('#ed-media-griglia'), pg = $('#ed-media-pagine');
    g.innerHTML = '<p class="ed-aiuto">Carico…</p>'; pg.innerHTML = '';
    api('media', { cartella: $('#ed-media-cartella').value, q: $('#ed-media-cerca').value, pagina: pagina }).then(function (j) {
      if (!mediaCartelleFatte) {
        mediaCartelleFatte = true;
        j.cartelle.forEach(function (c) { if (!c.quante) return; var o = document.createElement('option'); o.value = c.chiave; o.textContent = c.nome + ' (' + c.quante + ')'; $('#ed-media-cartella').appendChild(o); });
      }
      $('#ed-media-conta').textContent = j.totale + ' immagini';
      g.innerHTML = j.immagini.length ? '' : '<p class="ed-aiuto">Nessuna immagine qui. Carica la prima, oppure incolla un indirizzo.</p>';
      j.immagini.forEach(function (m) {
        var b = document.createElement('button'); b.type = 'button'; b.title = m.url;
        var im = document.createElement('img'); im.src = m.url; im.loading = 'lazy'; b.appendChild(im);
        var sp = document.createElement('span'); sp.textContent = m.nome; b.appendChild(sp);
        b.addEventListener('click', function () { scegliMedia(m.url); });
        g.appendChild(b);
      });
      for (var i = 1; i <= j.pagine; i++) {
        if (j.pagine > 12 && Math.abs(i - j.pagina) > 3 && i !== 1 && i !== j.pagine) continue;
        var pb = document.createElement('button'); pb.type = 'button'; pb.className = 'ed-b ed-b-piccolo' + (i === j.pagina ? ' ed-b-primo' : ''); pb.textContent = i;
        (function (n) { pb.addEventListener('click', function () { caricaMedia(n); }); })(i);
        pg.appendChild(pb);
      }
    }).catch(function (e) { g.innerHTML = '<p class="ed-errore">' + escapeHtml(e.message) + '</p>'; });
  }
  $('#ed-media-cartella').addEventListener('change', function () { caricaMedia(1); });
  $('#ed-media-cerca').addEventListener('input', function () { clearTimeout(mediaTimer); mediaTimer = setTimeout(function () { caricaMedia(1); }, 300); });
  function scegliMedia(u) { dMedia.close(); if (mediaCallback) mediaCallback(u); }
  $('#ed-media-file').addEventListener('change', function () {
    var f = this.files[0]; if (!f) return;
    $('#ed-media-errore').hidden = true;
    api('carica', {}, f).then(function (j) { scegliMedia(j.url); }).catch(function (e) { var p = $('#ed-media-errore'); p.textContent = e.message; p.hidden = false; });
    this.value = '';
  });
  $('#ed-media-usa-url').addEventListener('click', function () { var u = $('#ed-media-url').value.trim(); if (u) scegliMedia(u); });
  $$('[data-chiudi]').forEach(function (b) { b.addEventListener('click', function () { b.closest('dialog').close(); }); });

  // ------------------------------------------------------------ avvio
  disegnaElencoWidget();
  disegnaElencoSezioni();
  disegnaNavigatore();
  mostraControlli();
  aggiornaStato();
})();
