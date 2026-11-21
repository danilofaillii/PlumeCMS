// L'editor delle email a blocchi (2.12.0, admin/newsletter-design.php). La tela mostra l'email come la vedrà chi legge;
// l'HTML vero per la posta lo fa il server (includes/newsletter-design.php), che pulisce di nuovo ogni valore.
// Trascinamento con SortableJS; da tastiera: Invio sui blocchi della tavolozza, frecce Su e Giù con Alt per spostare,
// pulsanti di ogni blocco e sezione. Annulla e ripeti (Ctrl+Z, Ctrl+Y), salvataggio automatico.
(function () {
  'use strict';
  var D = JSON.parse(document.getElementById('nd-dati').textContent);
  var S = { d: D.design, sel: null, storia: [], futuro: [], gruppo: '', tempoGruppo: 0, timer: null, sporco: false, salvando: false, tel: false, sortable: [] };
  var tela = document.getElementById('nd-tela');
  var NOMI_SPECIALI = { '{{ unsubscribe }}': 'il link per uscire', '{{ update_profile }}': 'il link delle preferenze', '{{ mirror }}': 'il link della versione web', '{{ doubleoptin }}': 'il link di conferma' };

  function el(tag, cls, testo) { var e = document.createElement(tag); if (cls) e.className = cls; if (testo !== undefined) e.textContent = testo; return e; }
  function uid() { return Math.random().toString(36).slice(2, 12).replace(/[^a-z0-9]/g, 'x'); }
  function copia(x) { return JSON.parse(JSON.stringify(x)); }
  function predefiniti(spec) { var o = {}; Object.keys(spec).forEach(function (k) { o[k] = copia(spec[k][2]); }); return o; }
  function nuovoBlocco(t) { var b = predefiniti(D.blocchi[t][2]); b.id = uid(); b.t = t; return b; }
  function nuovaSezione(layout) {
    var s = predefiniti(D.sezione); s.id = uid(); s.layout = layout || '100'; s.salvata = 0;
    s.colonne = D.layout[s.layout][1].map(function () { return []; }); return s;
  }
  function rinnova(s) { s = copia(s); s.id = uid(); s.colonne.forEach(function (c) { c.forEach(function (b) { b.id = uid(); }); }); return s; }

  // ------------------------------------------------------------ dove sta un elemento
  function trova(id) {
    var d = S.d;
    for (var si = 0; si < d.sezioni.length; si++) {
      var s = d.sezioni[si];
      if (s.id === id) return { s: s, si: si };
      for (var ci = 0; ci < s.colonne.length; ci++) for (var bi = 0; bi < s.colonne[ci].length; bi++) if (s.colonne[ci][bi].id === id) return { s: s, si: si, ci: ci, bi: bi, b: s.colonne[ci][bi] };
    }
    return null;
  }

  // ------------------------------------------------------------ modifiche, annulla e ripeti, salvataggio
  function cambia(fn, gruppo) {
    var ora = Date.now();
    if (!gruppo || gruppo !== S.gruppo || ora - S.tempoGruppo > 1500) { S.storia.push(JSON.stringify(S.d)); if (S.storia.length > 80) S.storia.shift(); S.futuro = []; }
    S.gruppo = gruppo || ''; S.tempoGruppo = ora;
    fn();
    sporca();
  }
  function sporca() {
    S.sporco = true; disegna(); bottoniStoria();
    if (!D.modificabile) return;
    stato('Modifiche non salvate', 'bozza');
    clearTimeout(S.timer); S.timer = setTimeout(salva, 2500);
  }
  function torna(da, a) {
    if (!da.length) return;
    a.push(JSON.stringify(S.d)); S.d = JSON.parse(da.pop()); S.gruppo = '';
    if (S.sel && !trova(S.sel)) S.sel = null;
    sporca(); controlli();
  }
  function bottoniStoria() { document.getElementById('nd-annulla').disabled = !S.storia.length; document.getElementById('nd-ripeti').disabled = !S.futuro.length; }
  function stato(t, tipo) { var s = document.getElementById('nd-stato'); s.textContent = t; s.className = 'ed-stato' + (tipo ? ' ed-stato-' + tipo : ''); }

  function invia(dati) {
    var f = new FormData();
    f.append('_csrf', D.csrf);
    Object.keys(dati).forEach(function (k) { var v = typeof dati[k] === 'object' ? JSON.stringify(dati[k]) : String(dati[k]); f.append(k, window.campiProtetti ? window.campiProtetti.codifica(v) : v); });
    return fetch(location.href, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) {
      return r.json().catch(function () { throw new Error(window.campiProtetti ? window.campiProtetti.spiega(r.status) : 'Risposta non leggibile (' + r.status + ').'); });
    }).then(function (j) { if (j.sezioni) { D.sezioni = j.sezioni; salvati(); } if (j.errore) throw new Error(j.errore); return j; });
  }
  function salva() {
    clearTimeout(S.timer);
    if (!D.modificabile || S.salvando) return Promise.resolve();
    S.salvando = true; stato('Salvataggio…');
    var inviato = JSON.stringify(S.d);
    return invia({ azione: 'salva', design: S.d }).then(function (j) {
      S.salvando = false;
      if (JSON.stringify(S.d) === inviato) { S.sporco = false; stato('Salvato alle ' + j.ora); } else { S.timer = setTimeout(salva, 1500); }
    }).catch(function (e) { S.salvando = false; stato('Non salvato: ' + e.message, 'errore'); });
  }

  // ------------------------------------------------------------ la tela
  function stile() { return S.d.stile; }
  function font(k) { return (D.font[k] || D.font.arial)[1]; }
  function testoPulito(html) {
    // Lo stesso elenco chiuso del server: p, br, strong, b, em, i, u, s, a, ul, ol, li.
    var doc = new DOMParser().parseFromString('<div>' + (html || '') + '</div>', 'text/html'), ok = /^(P|BR|STRONG|B|EM|I|U|S|A|UL|OL|LI)$/;
    (function giro(n) {
      Array.prototype.slice.call(n.childNodes).forEach(function (f) {
        if (f.nodeType === 3) return;
        if (f.nodeType !== 1 || /^(SCRIPT|STYLE|IFRAME|OBJECT|EMBED|SVG|IMG)$/.test(f.tagName)) { f.remove(); return; }
        giro(f);
        if (!ok.test(f.tagName)) { while (f.firstChild) n.insertBefore(f.firstChild, f); f.remove(); return; }
        var href = f.getAttribute('href');
        Array.prototype.slice.call(f.attributes).forEach(function (a) { f.removeAttribute(a.name); });
        if (f.tagName === 'A' && href && /^(https?:|mailto:|tel:|\{\{|\/)/i.test(href.trim())) f.setAttribute('href', href.trim());
      });
    })(doc.body.firstChild);
    return doc.body.firstChild.innerHTML;
  }
  function immagineVuota(testo) { var v = el('div', 'nd-vuoto', testo); v.setAttribute('aria-hidden', 'true'); return v; }
  function youtube(u) { var m = /^https?:\/\/(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?(?:.*&)?v=|shorts\/|embed\/)|youtu\.be\/)([A-Za-z0-9_-]{6,20})/.exec(u || ''); return m ? 'https://img.youtube.com/vi/' + m[1] + '/hqdefault.jpg' : ''; }

  function bloccoVista(b) {
    var st = stile(), w = el('div', 'nd-b-dentro'), x;
    w.style.textAlign = b.allinea || 'left';
    if (b.spazio !== undefined) w.style.paddingBottom = b.spazio + 'px';
    switch (b.t) {
      case 'titolo':
        x = el(b.livello, 'nd-titolo', b.testo || 'Titolo vuoto');
        x.style.cssText = 'margin:0;font-weight:700;line-height:1.25;font-family:' + font(st.font_titoli) + ';font-size:' + { h1: 30, h2: 24, h3: 19 }[b.livello] + 'px;color:' + (b.colore || st.titoli);
        if (!b.testo) x.classList.add('nd-segnaposto');
        w.appendChild(x); break;
      case 'testo': case 'piede':
        x = el('div', 'nd-testo');
        x.innerHTML = testoPulito(b.html) || '<p>Testo vuoto</p>';
        x.style.cssText = 'font-family:' + font(st.font) + ';font-size:' + (b.t === 'piede' ? b.dim : (b.dim !== '' ? b.dim : st.dim)) + 'px;line-height:1.6;color:' + (b.colore || st.testo);
        x.querySelectorAll('a').forEach(function (a) { a.style.color = b.t === 'piede' ? (b.colore || st.testo) : st.link; a.setAttribute('tabindex', '-1'); if (NOMI_SPECIALI[a.getAttribute('href')]) a.title = NOMI_SPECIALI[a.getAttribute('href')]; });
        w.appendChild(x);
        if (b.t === 'piede' && b.indirizzo) { var ind = el('p', 'nd-indirizzo', D.indirizzo || 'Qui va l\'indirizzo dell\'azienda: scrivilo in Newsletter, Impostazioni.'); ind.style.cssText = x.style.cssText; w.appendChild(ind); }
        break;
      case 'immagine': case 'video':
        var src = b.src || (b.t === 'video' ? youtube(b.url) : '');
        if (src) { x = el('img'); x.src = src; x.alt = b.alt || ''; x.style.cssText = 'display:inline-block;max-width:100%;width:' + b.larghezza + '%;height:auto;border-radius:' + (b.raggio || 0) + 'px'; w.appendChild(x); }
        else w.appendChild(immagineVuota(b.t === 'video' ? 'Video: scrivi il link (la miniatura di YouTube arriva da sola) o scegli una miniatura' : 'Immagine: sceglila nel pannello, in Modifica'));
        if (b.t === 'video' && b.testo) { var c = el('p', 'nd-didascalia', '▶ ' + b.testo); c.style.cssText = 'margin:10px 0 0;font-weight:700;font-family:' + font(st.font) + ';color:' + st.link; w.appendChild(c); }
        break;
      case 'pulsante':
        x = el('span', 'nd-pulsante', b.testo || 'Pulsante');
        x.style.cssText = 'display:' + (b.pieno ? 'block' : 'inline-block') + ';padding:14px 28px;font-weight:700;font-family:' + font(st.font) + ';font-size:' + st.dim + 'px;background:' + (b.fondo || st.btn_fondo) + ';color:' + (b.colore || st.btn_testo) + ';border-radius:' + (b.raggio !== '' ? b.raggio : st.btn_raggio) + 'px';
        w.appendChild(x);
        if (!b.link) w.appendChild(el('p', 'nd-nota', 'Manca il link: senza, il pulsante non esce nell\'email.'));
        break;
      case 'divisore':
        x = el('div'); x.style.cssText = 'margin:0 auto;width:' + b.larghezza + '%;border-top:' + b.spessore + 'px ' + b.stile + ' ' + b.colore; w.appendChild(x); break;
      case 'spaziatore':
        x = el('div', 'nd-spaziatore'); x.style.height = b.altezza + 'px'; w.appendChild(x); break;
      case 'social':
        var conta = 0;
        b.reti.forEach(function (r) { if (!r.url) return; var i = el('img'); i.src = D.icone + r.r + '-' + b.stile + '.png'; i.alt = D.reti[r.r]; i.width = i.height = b.dim; i.style.margin = '0 5px'; w.appendChild(i); conta++; });
        if (!conta) w.appendChild(immagineVuota('Social: scrivi gli indirizzi delle pagine nel pannello, in Modifica'));
        break;
    }
    return w;
  }

  function strumenti(id, nome, sezione) {
    var t = el('div', 'nd-strumenti' + (sezione ? ' nd-strumenti-s' : ''));
    if (sezione) { var m = el('span', 'nd-maniglia', '☰'); m.title = 'Trascina per spostare la sezione'; m.setAttribute('aria-hidden', 'true'); t.appendChild(m); }
    t.appendChild(el('span', 'nd-strumenti-nome', nome));
    var az = [['su', '↑', 'Sposta su'], ['giu', '↓', 'Sposta giù'], ['duplica', '⧉', 'Duplica']];
    if (sezione) az.push(['salva', '★', 'Salva la sezione per riusarla']);
    az.push(['elimina', '✕', 'Elimina']);
    az.forEach(function (a) {
      var b = el('button', 'nd-azione', a[1]); b.type = 'button'; b.setAttribute('data-nd-azione', a[0]); b.setAttribute('data-nd-id', id);
      b.setAttribute('aria-label', a[2] + ' ' + (sezione ? 'la sezione' : 'il blocco ' + nome)); b.title = a[2];
      t.appendChild(b);
    });
    return t;
  }

  function disegna() {
    var st = stile(), fondo = document.getElementById('nd-fondo');
    S.sortable.forEach(function (x) { x.destroy(); }); S.sortable = [];
    fondo.style.background = st.fondo;
    tela.style.cssText = 'background:' + st.corpo + ';border-radius:' + st.raggio + 'px;width:' + (S.tel ? 390 : st.larghezza) + 'px;font-family:' + font(st.font) + ';color:' + st.testo;
    tela.classList.toggle('nd-tel', S.tel);
    tela.innerHTML = '';
    var elenco = el('div', 'nd-sezioni'); elenco.id = 'nd-sezioni';
    S.d.sezioni.forEach(function (s, si) {
      var se = el('div', 'nd-s' + (S.sel === s.id ? ' nd-sel' : '') + (s.nascosta ? ' nd-nascosta' : ''));
      se.setAttribute('data-id', s.id);
      se.style.cssText = 'padding:' + s.spazio + 'px ' + (S.tel ? Math.min(16, s.spazio_x) : s.spazio_x) + 'px;' + (s.fondo ? 'background:' + s.fondo : '');
      var nomeS = 'Sezione ' + (si + 1) + (s.salvata ? ' (salvata)' : '') + (s.nascosta ? ' (nascosta)' : '');
      se.appendChild(strumenti(s.id, nomeS, true));
      var righe = el('div', 'nd-righe' + (s.tel === 'affianca' ? ' nd-affianca' : ''));
      var quote = D.layout[s.layout][1];
      s.colonne.forEach(function (col, ci) {
        var ce = el('div', 'nd-c'); ce.style.flexBasis = quote[ci] + '%'; ce.setAttribute('data-sez', s.id); ce.setAttribute('data-col', ci);
        col.forEach(function (b) {
          var be = el('div', 'nd-b' + (S.sel === b.id ? ' nd-sel' : '')); be.setAttribute('data-id', b.id); be.tabIndex = 0; be.setAttribute('role', 'group');
          var nome = D.blocchi[b.t][0];
          be.setAttribute('aria-label', nome + (b.testo ? ': ' + b.testo : '') + '. Invio per modificarlo, Alt più frecce per spostarlo.');
          be.appendChild(strumenti(b.id, nome, false));
          be.appendChild(bloccoVista(b));
          ce.appendChild(be);
        });
        if (!col.length) ce.appendChild(el('p', 'nd-colonna-vuota', 'Trascina qui un blocco'));
        righe.appendChild(ce);
      });
      se.appendChild(righe);
      elenco.appendChild(se);
    });
    if (!S.d.sezioni.length) elenco.appendChild(el('p', 'nd-colonna-vuota nd-tutta-vuota', 'L\'email è vuota: trascina qui una sezione o un blocco dalla scheda Contenuti.'));
    tela.appendChild(elenco);
    if (!D.modificabile || typeof Sortable === 'undefined') return;
    // Il modello cambia dopo che SortableJS ha finito il suo giro (la tela si ridisegna e i Sortable rinascono).
    var dopo = function (fn) { return function (e) { var x = { item: e.item, from: e.from, to: e.to, oldIndex: e.oldIndex, newIndex: e.newIndex }; setTimeout(function () { fn(x); }, 0); }; };
    S.sortable.push(Sortable.create(elenco, { group: { name: 'sezioni', put: ['sezioni'] }, handle: '.nd-maniglia', draggable: '.nd-s', animation: 120, onAdd: dopo(aggiuntaSezione), onUpdate: dopo(spostataSezione) }));
    tela.querySelectorAll('.nd-c').forEach(function (c) {
      S.sortable.push(Sortable.create(c, { group: { name: 'blocchi', put: ['blocchi'] }, draggable: '.nd-b', filter: '.nd-azione', preventOnFilter: false, animation: 120, onAdd: dopo(aggiuntoBlocco), onUpdate: dopo(spostatoBlocco) }));
    });
  }

  // ------------------------------------------------------------ trascinamento
  function aggiuntaSezione(e) {
    var it = e.item, i = e.newIndex, nuova;
    if (it.hasAttribute('data-layout')) nuova = nuovaSezione(it.getAttribute('data-layout'));
    else if (it.hasAttribute('data-salvata')) nuova = daSalvata(+it.getAttribute('data-salvata'));
    it.remove();
    if (!nuova) { disegna(); return; }
    cambia(function () { S.d.sezioni.splice(Math.min(i, S.d.sezioni.length), 0, nuova); S.sel = nuova.id; });
    controlli();
  }
  function spostataSezione(e) { var o = e.oldIndex, n = e.newIndex; cambia(function () { var s = S.d.sezioni.splice(o, 1)[0]; S.d.sezioni.splice(n, 0, s); }); }
  function colonna(c) { var x = trova(c.getAttribute('data-sez')); return x ? x.s.colonne[+c.getAttribute('data-col')] : null; }
  function aggiuntoBlocco(e) {
    var dest = colonna(e.to), it = e.item, i = e.newIndex;
    if (it.hasAttribute('data-blocco')) {
      var b = nuovoBlocco(it.getAttribute('data-blocco')); it.remove();
      cambia(function () { dest.splice(i, 0, b); S.sel = b.id; }); controlli(); scheda('modifica'); return;
    }
    var da = colonna(e.from), o = e.oldIndex;
    cambia(function () { var b = da.splice(o, 1)[0]; dest.splice(i, 0, b); });
  }
  function spostatoBlocco(e) { var col = colonna(e.to), o = e.oldIndex, n = e.newIndex; cambia(function () { var b = col.splice(o, 1)[0]; col.splice(n, 0, b); }); }

  // ------------------------------------------------------------ azioni (pulsanti e tastiera)
  function inserisciBlocco(t) {
    var b = nuovoBlocco(t), x = S.sel ? trova(S.sel) : null;
    cambia(function () {
      if (x && x.b) x.s.colonne[x.ci].splice(x.bi + 1, 0, b);
      else if (x) x.s.colonne[0].push(b);
      else { var s = nuovaSezione('100'); s.colonne[0].push(b); S.d.sezioni.push(s); }
      S.sel = b.id;
    });
    controlli(); scheda('modifica'); vaiA(b.id);
  }
  function inserisciSezione(s) {
    var x = S.sel ? trova(S.sel) : null;
    cambia(function () { S.d.sezioni.splice(x ? x.si + 1 : S.d.sezioni.length, 0, s); S.sel = s.id; });
    controlli(); vaiA(s.id);
  }
  function vaiA(id) { var e = tela.querySelector('[data-id="' + id + '"]'); if (e) { e.scrollIntoView({ block: 'nearest' }); if (e.classList.contains('nd-b')) e.focus({ preventScroll: true }); } }
  // Un blocco sale o scende: dentro la sua colonna, poi nella colonna (o nella sezione) prima o dopo.
  function sposta(id, verso) {
    var x = trova(id); if (!x) return;
    cambia(function () {
      if (!x.b) { var j = x.si + verso; if (j < 0 || j >= S.d.sezioni.length) return; S.d.sezioni.splice(x.si, 1); S.d.sezioni.splice(j, 0, x.s); return; }
      var col = x.s.colonne[x.ci], j2 = x.bi + verso;
      if (j2 >= 0 && j2 < col.length) { col.splice(x.bi, 1); col.splice(j2, 0, x.b); return; }
      var cols = []; S.d.sezioni.forEach(function (s) { s.colonne.forEach(function (c) { cols.push(c); }); });
      var k = cols.indexOf(col) + verso; if (k < 0 || k >= cols.length) return;
      col.splice(x.bi, 1); if (verso < 0) cols[k].push(x.b); else cols[k].unshift(x.b);
    });
    vaiA(id);
  }
  function azione(nome, id) {
    var x = trova(id); if (!x) return;
    if (nome === 'su' || nome === 'giu') return sposta(id, nome === 'su' ? -1 : 1);
    if (nome === 'duplica') {
      if (x.b) { var b = copia(x.b); b.id = uid(); cambia(function () { x.s.colonne[x.ci].splice(x.bi + 1, 0, b); S.sel = b.id; }); }
      else { var s = rinnova(x.s); cambia(function () { S.d.sezioni.splice(x.si + 1, 0, s); S.sel = s.id; }); }
      return controlli();
    }
    if (nome === 'salva') return dialogoNome('sezione', x.s);
    if (nome === 'elimina') {
      cambia(function () { if (x.b) x.s.colonne[x.ci].splice(x.bi, 1); else S.d.sezioni.splice(x.si, 1); if (S.sel === id || (!x.b && S.sel && !trova(S.sel))) S.sel = null; });
      controlli(); stato((x.b ? 'Blocco eliminato' : 'Sezione eliminata') + ': Ctrl+Z per riaverlo', 'bozza');
    }
  }
  function seleziona(id) { S.sel = id; disegna(); controlli(); scheda('modifica'); }

  tela.addEventListener('click', function (e) {
    var a = e.target.closest('[data-nd-azione]');
    if (a) { e.preventDefault(); if (D.modificabile) azione(a.getAttribute('data-nd-azione'), a.getAttribute('data-nd-id')); return; }
    var x = e.target.closest('.nd-b') || e.target.closest('.nd-s');
    if (x) { e.preventDefault(); seleziona(x.getAttribute('data-id')); }
  });
  tela.addEventListener('keydown', function (e) {
    var b = e.target.closest('.nd-b'); if (!b || e.target !== b) return;
    var id = b.getAttribute('data-id');
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); seleziona(id); vaiA(id); }
    else if (e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown') && D.modificabile) { e.preventDefault(); sposta(id, e.key === 'ArrowUp' ? -1 : 1); }
    else if (e.key === 'Delete' && D.modificabile) { e.preventDefault(); azione('elimina', id); }
  });

  // ------------------------------------------------------------ il pannello: tavolozza
  function voce(testo, icona, attr, val, fai) {
    var v = el('div', 'ed-widget-voce'); v.setAttribute(attr, val); v.setAttribute('role', 'button'); v.tabIndex = 0;
    v.setAttribute('aria-label', 'Aggiungi: ' + testo);
    if (icona) { var i = el('i', '', icona); i.setAttribute('aria-hidden', 'true'); v.appendChild(i); } else v.appendChild(icona === '' ? el('i') : schema(val));
    v.appendChild(el('span', '', testo));
    v.addEventListener('click', fai);
    v.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fai(); } });
    return v;
  }
  function schema(layout) { var s = el('span', 'nd-schema'); s.setAttribute('aria-hidden', 'true'); D.layout[layout][1].forEach(function (q) { var i = el('b'); i.style.flexGrow = q; s.appendChild(i); }); return s; }
  function tavolozza() {
    var tb = document.getElementById('nd-tavolozza-blocchi'), ts = document.getElementById('nd-tavolozza-sezioni');
    Object.keys(D.blocchi).forEach(function (t) { tb.appendChild(voce(D.blocchi[t][0], D.blocchi[t][1], 'data-blocco', t, function () { if (D.modificabile) inserisciBlocco(t); })); });
    Object.keys(D.layout).forEach(function (l) { ts.appendChild(voce(D.layout[l][0], null, 'data-layout', l, function () { if (D.modificabile) inserisciSezione(nuovaSezione(l)); })); });
    if (!D.modificabile || typeof Sortable === 'undefined') return;
    Sortable.create(tb, { group: { name: 'blocchi', pull: 'clone', put: false }, sort: false });
    Sortable.create(ts, { group: { name: 'sezioni', pull: 'clone', put: false }, sort: false });
  }

  // ------------------------------------------------------------ il pannello: controlli
  var progressivo = 0;
  function campo(spec, valore, scrivi, gruppo) {
    var tipo = spec[0], etichetta = spec[1], box = el('div', 'ed-controllo'), id = 'nd-c' + (++progressivo), x;
    var lab = el('label', '', etichetta); lab.htmlFor = id;
    var su = function (v) { cambia(function () { scrivi(v); }, gruppo + id.replace(/\d+/g, '') + etichetta); };
    switch (tipo) {
      case 't': case 'l': case 'i': case 'm':
        x = el('input'); x.type = tipo === 'm' ? 'number' : 'text'; x.id = id; x.value = valore === null ? '' : valore;
        if (tipo === 't') x.maxLength = spec[3];
        if (tipo === 'm') { x.min = spec[3][0]; x.max = spec[3][1]; x.placeholder = 'come lo stile'; }
        if (tipo === 'l') x.placeholder = 'per esempio: https://esempio.it/pagina';
        x.addEventListener('input', function () { su(tipo === 'm' ? (x.value === '' ? '' : +x.value) : x.value); });
        box.appendChild(lab); box.appendChild(x);
        if (tipo === 'l') {
          if (window.pnLink) window.pnLink.campo(x, { modo: 'assoluto', classe: 'ed-b ed-b-piccolo' });
          var sp = el('div', 'nd-speciali'); sp.appendChild(el('span', 'ed-aiuto', 'Link speciali: '));
          Object.keys(NOMI_SPECIALI).forEach(function (k) { if (D.campagna && k === "{{ doubleoptin }}") return; var b = el('button', 'ed-b ed-b-piccolo', NOMI_SPECIALI[k].replace('il link ', '')); b.type = 'button'; b.title = 'Usa ' + NOMI_SPECIALI[k] + ' (' + k + ')'; b.addEventListener('click', function () { x.value = k; su(k); }); sp.appendChild(b); });
          box.appendChild(sp);
        }
        if (tipo === 'i') {
          // Con l'immagine si salvano le sue misure: l'email le scrive (larghezza e altezza) per non far saltare il testo.
          var metti = function (u) { misure(u, function (w, h) { cambia(function () { scrivi(u, w, h); }, gruppo + 'img'); }); };
          var bm = el('button', 'ed-b nd-media', 'Scegli dai Media'); bm.type = 'button';
          bm.addEventListener('click', function () {
            if (!window.pnMedia) return;
            window.pnMedia.apri({ titolo: 'Un\'immagine per l\'email', carica: 'newsletter-immagine.php', assoluto: true, accetta: 'image/png,image/jpeg,image/gif,image/webp', attuale: x.value,
              scegli: function (u) { x.value = u; metti(u); } });
          });
          box.appendChild(bm);
          x.addEventListener('change', function () { metti(x.value.trim()); });
        }
        break;
      case 'h':
        lab = el('span', 'ed-etichetta', etichetta); lab.id = id + '-e'; box.appendChild(lab);
        var q = el('div', 'ed-quill nd-quill'); box.appendChild(q);
        setTimeout(function () {
          if (typeof Quill === 'undefined') return;
          var qu = new Quill(q, { theme: 'snow', modules: { toolbar: [['bold', 'italic', 'underline'], ['link'], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']] } });
          qu.root.setAttribute('aria-labelledby', id + '-e'); qu.root.setAttribute('role', 'textbox'); qu.root.setAttribute('aria-multiline', 'true');
          qu.clipboard.dangerouslyPasteHTML(testoPulito(valore));
          if (window.pnLink) window.pnLink.quill(qu, { assoluto: true });
          qu.on('text-change', function () { su(qu.getLength() > 1 ? qu.root.innerHTML : ''); });
        }, 0);
        box.appendChild(el('p', 'ed-aiuto', 'Il nome di chi legge: {{ contact.NOME }}. Nei link puoi scrivere {{ unsubscribe }} (uscire), {{ update_profile }} (preferenze), {{ mirror }} (versione web).'));
        return box;
      case 'c':
        x = el('input'); x.type = 'text'; x.id = id; x.value = valore; x.placeholder = spec[2] ? spec[2] : 'come lo stile'; x.maxLength = 7;
        var riga = el('div', 'ed-riga-colore'), cc = el('input'); cc.type = 'color'; cc.value = valore || spec[2] || '#000000'; cc.setAttribute('aria-label', etichetta + ': scegli dalla tavolozza');
        cc.addEventListener('input', function () { x.value = cc.value; su(cc.value); });
        x.addEventListener('input', function () { if (/^#[0-9a-f]{6}$/i.test(x.value) || x.value === '') { if (x.value) cc.value = x.value; su(x.value.toLowerCase()); } });
        riga.appendChild(cc); riga.appendChild(x);
        if (spec[2] === '') { var vuoto = el('button', 'ed-b ed-b-piccolo', 'Come lo stile'); vuoto.type = 'button'; vuoto.addEventListener('click', function () { x.value = ''; su(''); }); riga.appendChild(vuoto); }
        box.appendChild(lab); box.appendChild(riga);
        if (D.colori.length) {
          var cam = el('div', 'nd-campioni'); cam.setAttribute('role', 'group'); cam.setAttribute('aria-label', 'Colori del sito');
          D.colori.forEach(function (c) { var b = el('button', 'nd-campione'); b.type = 'button'; b.style.background = c.valore; b.title = c.nome + ' ' + c.valore; b.setAttribute('aria-label', 'Colore del sito: ' + c.nome); b.addEventListener('click', function () { x.value = c.valore; cc.value = c.valore; su(c.valore); }); cam.appendChild(b); });
          box.appendChild(cam);
        }
        return box;
      case 's':
        x = el('select'); x.id = id;
        Object.keys(spec[3]).forEach(function (k) { var o = el('option', '', spec[3][k]); o.value = k; if (k === valore) o.selected = true; x.appendChild(o); });
        x.addEventListener('change', function () { su(x.value); });
        box.appendChild(lab); box.appendChild(x); return box;
      case 'n':
        var r = el('div', 'ed-numero-riga'), cur = el('input'); cur.type = 'range'; cur.min = spec[3][0]; cur.max = spec[3][1]; cur.value = valore; cur.setAttribute('aria-label', etichetta + ' (cursore)');
        x = el('input'); x.type = 'number'; x.id = id; x.min = spec[3][0]; x.max = spec[3][1]; x.value = valore;
        cur.addEventListener('input', function () { x.value = cur.value; su(+cur.value); });
        x.addEventListener('input', function () { if (x.value !== '') { cur.value = x.value; su(Math.max(spec[3][0], Math.min(spec[3][1], +x.value))); } });
        r.appendChild(cur); r.appendChild(x); box.appendChild(lab); box.appendChild(r); return box;
      case 'b':
        lab = el('label', 'ed-interruttore'); lab.appendChild(el('span', '', etichetta)); x = el('input'); x.type = 'checkbox'; x.checked = !!valore; x.id = id;
        x.addEventListener('change', function () { su(x.checked); }); lab.appendChild(x); box.appendChild(lab); return box;
      case 'r':
        box.appendChild(el('span', 'ed-etichetta', etichetta));
        var lista = el('div', 'ed-ripetitore');
        var ora = function () { var y = trova(gruppo); return y && y.b ? y.b.reti : []; };
        (valore || []).forEach(function (rete, i) {
          var v = el('div', 'nd-rete'), sel = el('select'); sel.setAttribute('aria-label', 'Rete ' + (i + 1));
          Object.keys(D.reti).forEach(function (k) { var o = el('option', '', D.reti[k]); o.value = k; if (k === rete.r) o.selected = true; sel.appendChild(o); });
          var u = el('input'); u.type = 'text'; u.value = rete.url; u.placeholder = rete.r === 'email' ? 'mailto:info@esempio.it' : 'https://…'; u.setAttribute('aria-label', 'Indirizzo della pagina ' + D.reti[rete.r]);
          var t = el('button', 'ed-b ed-b-piccolo', '✕'); t.type = 'button'; t.setAttribute('aria-label', 'Togli ' + D.reti[rete.r]);
          sel.addEventListener('change', function () { su(ora().map(function (z, j) { return j === i ? { r: sel.value, url: z.url } : z; })); controlli(); });
          u.addEventListener('input', function () { su(ora().map(function (z, j) { return j === i ? { r: z.r, url: u.value } : z; })); });
          t.addEventListener('click', function () { su(ora().filter(function (z, j) { return j !== i; })); controlli(); });
          v.appendChild(sel); v.appendChild(u); v.appendChild(t); lista.appendChild(v);
        });
        var piu = el('button', 'ed-b', '+ Aggiungi una rete'); piu.type = 'button';
        piu.addEventListener('click', function () { su(ora().concat([{ r: 'linkedin', url: '' }])); controlli(); });
        box.appendChild(lista); box.appendChild(piu); return box;
    }
    return box;
  }
  function misure(u, poi) { if (!u) return poi(0, 0); var i = new Image(); i.onload = function () { poi(i.naturalWidth, i.naturalHeight); }; i.onerror = function () { poi(0, 0); }; i.src = u; }

  function gruppo(titolo, aperto) { var d = el('details', 'ed-sez'); d.open = aperto !== false; d.appendChild(el('summary', '', titolo)); return d; }
  function controlli() {
    var box = document.getElementById('nd-controlli'), nome = document.getElementById('nd-modifica-nome');
    box.innerHTML = '';
    var x = S.sel ? trova(S.sel) : null;
    if (!x) { nome.textContent = 'Niente selezionato'; box.appendChild(el('p', 'ed-aiuto', 'Fai clic su un blocco o su una sezione della tela per cambiarli.')); return; }
    if (x.b) {
      var spec = D.blocchi[x.b.t][2];
      nome.textContent = 'Blocco: ' + D.blocchi[x.b.t][0];
      Object.keys(spec).forEach(function (k) {
        if (!spec[k][1]) return;
        box.appendChild(campo(spec[k], x.b[k], function (v, w, h) { var y = trova(x.b.id); if (!y) return; y.b[k] = v; if (w !== undefined) { y.b.w = w; y.b.h = h; } }, x.b.id));
      });
      if (x.b.t === 'piede') box.appendChild(el('p', 'ed-aiuto', 'Il link per uscire è obbligatorio: se nel piede manca, all\'invio lo rimettiamo noi in fondo, con l\'indirizzo dell\'azienda.'));
      var su = el('button', 'ed-b', 'Seleziona la sua sezione'); su.type = 'button'; su.addEventListener('click', function () { seleziona(x.s.id); }); box.appendChild(su);
    } else {
      nome.textContent = 'Sezione ' + (x.si + 1);
      if (x.s.salvata) {
        var nota = el('div', 'nd-salvata'), ss = D.sezioni.filter(function (z) { return z.id === x.s.salvata; })[0];
        if (ss && ss.sincronizzata) {
          nota.appendChild(el('p', '', 'Sezione salvata «' + ss.nome + '», sincronizzata. Quello che cambi qui resta in questa email finché non premi «Aggiorna in tutte le email».'));
          var ag = el('button', 'ed-b ed-b-primo', 'Aggiorna in tutte le email'); ag.type = 'button'; ag.addEventListener('click', function () { aggiornaOvunque(x.s); });
          nota.appendChild(ag);
        } else nota.appendChild(el('p', '', ss ? 'Copia della sezione salvata «' + ss.nome + '».' : 'La sezione salvata da cui viene non c\'è più.'));
        var sc = el('button', 'ed-b', 'Scollega da quella salvata'); sc.type = 'button'; sc.addEventListener('click', function () { cambia(function () { trova(x.s.id).s.salvata = 0; }); controlli(); });
        nota.appendChild(sc); box.appendChild(nota);
      }
      Object.keys(D.sezione).forEach(function (k) {
        box.appendChild(campo(D.sezione[k], x.s[k], function (v) {
          var y = trova(x.s.id); if (!y) return;
          if (k === 'layout') {
            var n = D.layout[v][1].length, cols = y.s.colonne;
            while (cols.length > n) { var via = cols.pop(); cols[cols.length - 1] = cols[cols.length - 1].concat(via); }
            while (cols.length < n) cols.push([]);
          }
          y.s[k] = v;
        }, x.s.id));
      });
      box.appendChild(el('p', 'ed-aiuto', 'Togliendo colonne, i blocchi non si perdono: finiscono nell\'ultima colonna rimasta.'));
    }
  }
  function controlliStile() {
    var box = document.getElementById('nd-controlli-stile');
    box.innerHTML = '';
    var gruppi = [['L\'email', ['fondo', 'corpo', 'larghezza', 'raggio']], ['Testi', ['font', 'font_titoli', 'dim', 'testo', 'titoli', 'link']], ['Pulsanti', ['btn_fondo', 'btn_testo', 'btn_raggio']]];
    gruppi.forEach(function (g) {
      var d = gruppo(g[0]);
      g[1].forEach(function (k) { d.appendChild(campo(D.stile[k], S.d.stile[k], function (v) { S.d.stile[k] = v; }, 'stile')); });
      box.appendChild(d);
    });
  }

  // ------------------------------------------------------------ sezioni salvate
  function daSalvata(id) {
    var ss = D.sezioni.filter(function (z) { return z.id === id; })[0]; if (!ss) return null;
    var s = rinnova(ss.sezione); s.salvata = ss.sincronizzata ? ss.id : 0; return s;
  }
  function salvati() {
    var box = document.getElementById('nd-elenco-salvati'); box.innerHTML = '';
    if (!D.sezioni.length) { box.appendChild(el('p', 'ed-aiuto', 'Ancora nessuna sezione salvata: nella tela passa sopra una sezione e premi la stella (Salva la sezione per riusarla).')); return; }
    var lista = el('div', 'nd-salvati');
    D.sezioni.forEach(function (ss) {
      var v = el('div', 'nd-salvato'); v.setAttribute('data-salvata', ss.id);
      var t = el('div', 'nd-salvato-testa'); t.appendChild(el('b', '', ss.nome)); t.appendChild(el('small', '', ss.sincronizzata ? 'Sincronizzata: cambia ovunque' : 'Copia: cambia solo dove la metti'));
      v.appendChild(t);
      var az = el('div', 'nd-salvato-azioni');
      var ins = el('button', 'ed-b ed-b-piccolo', 'Inserisci'); ins.type = 'button'; ins.setAttribute('aria-label', 'Inserisci la sezione ' + ss.nome);
      ins.addEventListener('click', function () { var s = daSalvata(ss.id); if (s && D.modificabile) inserisciSezione(s); });
      var del = el('button', 'ed-b ed-b-piccolo ed-b-pericolo', 'Elimina'); del.type = 'button'; del.setAttribute('aria-label', 'Elimina la sezione salvata ' + ss.nome);
      del.addEventListener('click', function () {
        (window.pnConferma || function (t, a, poi) { poi(confirm(t)); })('Eliminare la sezione salvata «' + ss.nome + '»? Le email che la usano tengono la loro copia.', del, function (si) {
          if (si) invia({ azione: 'sezione_elimina', id: ss.id }).then(function () { stato('Sezione salvata eliminata'); }).catch(function (e) { stato(e.message, 'errore'); });
        });
      });
      az.appendChild(ins); az.appendChild(del); v.appendChild(az); lista.appendChild(v);
    });
    box.appendChild(lista);
    if (D.modificabile && typeof Sortable !== 'undefined') Sortable.create(lista, { group: { name: 'sezioni', pull: 'clone', put: false }, sort: false, filter: 'button', preventOnFilter: false });
  }
  function aggiornaOvunque(s) {
    var id = s.salvata;
    stato('Aggiorno la sezione in tutte le email…');
    salva().then(function () { return invia({ azione: 'sezione_aggiorna', id: id, sezione: s }); })
      .then(function (j) { stato(j.messaggio); controlli(); }).catch(function (e) { stato(e.message, 'errore'); });
  }

  // ------------------------------------------------------------ finestre: nome (sezione salvata, salva come modello), anteprima
  var dlgNome = document.getElementById('nd-dlg-nome'), cosaNome = null;
  function dialogoNome(cosa, sez) {
    cosaNome = { cosa: cosa, sez: sez };
    var f = document.getElementById('nd-form-nome');
    document.getElementById('nd-nome-titolo').textContent = cosa === 'sezione' ? 'Salva la sezione per riusarla' : 'Salva come modello';
    document.getElementById('nd-nome-spunta-testo').textContent = cosa === 'sezione' ? 'Sincronizzata: modificandola, cambia in tutte le email in bozza che la usano' : 'Attiva subito il modello (si sceglie nella sezione Design delle campagne)';
    document.getElementById('nd-nome-aiuto').textContent = cosa === 'sezione' ? 'Senza la spunta è una copia: ogni email tiene la sua. Le email già partite non cambiano mai.' : 'Il modello porta solo il design: mittente, destinatari e oggetto restano della campagna.';
    document.getElementById('nd-nome-ok').textContent = cosa === 'sezione' ? 'Salva la sezione' : 'Salva il modello';
    document.getElementById('nd-nome-errore').hidden = true;
    f.reset(); dlgNome.showModal(); document.getElementById('nd-nome-campo').focus();
  }
  document.getElementById('nd-form-nome').addEventListener('submit', function (e) {
    e.preventDefault();
    var nome = document.getElementById('nd-nome-campo').value.trim(), spunta = document.getElementById('nd-nome-spunta').checked, err = document.getElementById('nd-nome-errore');
    if (!nome) { err.textContent = 'Scrivi un nome: ti serve per ritrovarlo.'; err.hidden = false; return; }
    var c = cosaNome, p = c.cosa === 'sezione' ? invia({ azione: 'sezione_salva', nome: nome, sezione: c.sez, sincronizzata: spunta ? 1 : 0 }) : invia({ azione: 'modello', nome: nome, design: S.d, attivo: spunta ? 1 : 0 });
    p.then(function (j) {
      dlgNome.close();
      if (c.cosa === 'sezione') {
        if (spunta) cambia(function () { var y = trova(c.sez.id); if (y) y.s.salvata = j.id; });
        stato('Sezione salvata: la trovi nella scheda Salvati'); controlli();
      } else stato(j.messaggio);
    }).catch(function (e2) { err.textContent = e2.message; err.hidden = false; });
  });
  function anteprima() {
    var dlg = document.getElementById('nd-dlg-anteprima'), fr = document.getElementById('nd-anteprima-iframe');
    fr.srcdoc = '<p style="font:14px sans-serif;padding:20px">Preparo l\'anteprima…</p>'; dlg.showModal();
    invia({ azione: 'anteprima', design: S.d }).then(function (j) { fr.srcdoc = j.html; document.getElementById('nd-anteprima-oggetto').textContent = j.oggetto ? 'Oggetto: ' + j.oggetto : ''; })
      .catch(function (e) { fr.srcdoc = '<p style="font:14px sans-serif;padding:20px">' + e.message.replace(/</g, '&lt;') + '</p>'; });
  }
  document.querySelectorAll('[data-nd-ant]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelector('.nd-ant-cornice').classList.toggle('nd-tel', b.getAttribute('data-nd-ant') === 'tel');
      document.querySelectorAll('[data-nd-ant]').forEach(function (z) { z.classList.toggle('attivo', z === b); z.setAttribute('aria-pressed', z === b ? 'true' : 'false'); });
    });
  });

  // ------------------------------------------------------------ schede del pannello (ARIA), barra, tastiera
  var schede = Array.prototype.slice.call(document.querySelectorAll('[data-nd-scheda]'));
  function scheda(nome) {
    schede.forEach(function (b) {
      var si = b.getAttribute('data-nd-scheda') === nome;
      b.classList.toggle('attivo', si); b.setAttribute('aria-selected', si ? 'true' : 'false'); b.tabIndex = si ? 0 : -1;
      document.getElementById(b.getAttribute('aria-controls')).hidden = !si;
    });
  }
  schede.forEach(function (b, i) {
    b.addEventListener('click', function () { scheda(b.getAttribute('data-nd-scheda')); });
    b.addEventListener('keydown', function (e) {
      var j = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: schede.length - 1 }[e.key];
      if (j === undefined) return; e.preventDefault(); j = (j + schede.length) % schede.length; scheda(schede[j].getAttribute('data-nd-scheda')); schede[j].focus();
    });
  });
  function misura(tel) {
    S.tel = tel; disegna();
    document.querySelectorAll('[data-nd-misura]').forEach(function (b) { var si = (b.getAttribute('data-nd-misura') === 'tel') === tel; b.classList.toggle('attivo', si); b.setAttribute('aria-pressed', si ? 'true' : 'false'); });
  }
  document.querySelectorAll('[data-nd-misura]').forEach(function (b) { b.addEventListener('click', function () { misura(b.getAttribute('data-nd-misura') === 'tel'); }); });
  document.getElementById('nd-annulla').addEventListener('click', function () { torna(S.storia, S.futuro); });
  document.getElementById('nd-ripeti').addEventListener('click', function () { torna(S.futuro, S.storia); });
  document.getElementById('nd-salva').addEventListener('click', function () { salva(); });
  document.getElementById('nd-anteprima').addEventListener('click', anteprima);
  var cm = document.getElementById('nd-come-modello'); if (cm) cm.addEventListener('click', function () { dialogoNome('modello'); });
  document.addEventListener('keydown', function (e) {
    if (!(e.ctrlKey || e.metaKey) || e.target.closest('input, textarea, select, .ql-editor')) return;
    var k = e.key.toLowerCase();
    if (k === 'z' && !e.shiftKey) { e.preventDefault(); torna(S.storia, S.futuro); }
    else if (k === 'y' || (k === 'z' && e.shiftKey)) { e.preventDefault(); torna(S.futuro, S.storia); }
    else if (k === 's') { e.preventDefault(); salva(); }
  });
  window.addEventListener('beforeunload', function (e) { if (S.sporco && D.modificabile) { salva(); e.preventDefault(); e.returnValue = ''; } });

  tavolozza(); controlliStile(); salvati();
  misura(window.matchMedia('(max-width: 760px)').matches);
  stato(D.modificabile ? 'Le modifiche si salvano da sole' : 'Solo da guardare');
  window.ndEditor = { stato: S, salva: salva, trova: trova };
})();
