/*
 * La tela delle automazioni. Blocchi (nodi) trascinabili, uscite con un pallino, archi disegnati in SVG.
 * Il grafo vive in FL.g = {nodi:[{id,tipo,x,y,dati}], archi:[{da,uscita,a}]}; al salvataggio finisce in #fl-grafo, gli
 * inneschi in #fl-inneschi-campo. Pannello a sinistra con le schede Inneschi, Azioni, Regole (schede ARIA, frecce).
 * La divisione condizionale usa il costruttore dei segmenti (window.nlsMonta, assets/segmenti.js).
 * Senza librerie: pointer events e un po' di geometria. Niente gestori in linea: la CSP a nonce li vieta.
 */
var FL = (function () {
  var D = FL_DATI, g = D.grafo, tela = document.getElementById('fl-tela'), box = document.getElementById('fl-tela-box'), svg = document.getElementById('fl-archi');
  var pannello = document.getElementById('fl-pannello'), scelto = null, arcoScelto = null, quill = null, ns = 'http://www.w3.org/2000/svg';
  var ICONE = {innesco: '\uD83D\uDE80', email: '✉️', attesa: '⏳', bivio: '\uD83D\uDD00', fine: '⏹', evento: '\uD83D\uDC41', dividi: '\uD83D\uDD00', percento: '⚖', lista_aggiungi: '➕', lista_togli: '➖', attributo: '✎', webhook: '↗'};
  var NOMI = {innesco: 'Inneschi', email: 'Email', attesa: 'Attesa', bivio: 'Sì o No', fine: 'Fine', evento: 'Attendi un evento', dividi: 'Divisione condizionale', percento: 'Divisione in percentuale', lista_aggiungi: 'Aggiungi a una lista', lista_togli: 'Togli da una lista', attributo: 'Aggiorna un attributo', webhook: 'Webhook'};
  var inneschi = (D.inneschi || []).slice();

  function h(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]; }); }
  function nodo(id) { return g.nodi.find(function (n) { return n.id === id; }); }
  function nuovoId() { return g.nodi.reduce(function (m, n) { return Math.max(m, n.id); }, 0) + 1; }
  function arrivo(da, u) { var a = g.archi.find(function (x) { return x.da === da && x.uscita === u; }); return a ? a.a : null; }
  // 2.18.0: il segnaposto di oggetto e anteprima di un passo che richiama un modello: quello del modello, o l'esempio.
  function segnaposto(mm, k, esempio) { return mm ? (mm[k] ? 'dal modello: ' + mm[k] : (k === 'o' ? 'il modello non ha oggetto: scrivilo qui' : 'il modello non ha anteprima')) : esempio; }
  function opz(obj, sc) { return Object.keys(obj).map(function (k) { return '<option value="' + h(k) + '"' + (String(k) === String(sc) ? ' selected' : '') + '>' + h(obj[k]) + '</option>'; }).join(''); }
  function tempo(min) {
    if (!min) return 'subito';
    if (min % 1440 === 0) return (min / 1440) + (min === 1440 ? ' giorno' : ' giorni');
    if (min % 60 === 0) return (min / 60) + (min === 60 ? ' ora' : ' ore');
    return min + ' min';
  }
  function unita(min) { if (min % 1440 === 0) return {n: min / 1440, u: 1440}; if (min % 60 === 0) return {n: min / 60, u: 60}; return {n: min, u: 1}; }
  function opzUnita(u) { return [[1, 'minuti'], [60, 'ore'], [1440, 'giorni']].map(function (x) { return '<option value="' + x[0] + '"' + (x[0] === u ? ' selected' : '') + '>' + x[1] + '</option>'; }).join(''); }
  function b64(o) { return btoa(unescape(encodeURIComponent(JSON.stringify(o)))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
  function deB64(s) { try { s = s.replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) s += '='; return JSON.parse(decodeURIComponent(escape(atob(s)))); } catch (e) { return null; } }
  // Il nome di un'email nei menu e nei riassunti: il suo oggetto; 2.18.0: senza, l'oggetto (o il nome) del modello che richiama.
  function nomeEmail(x) { var mm = x.dati.editor === 'modello' ? D.modelli[x.dati.modello] : null; return x.dati.oggetto || (mm ? mm.o || mm.n : '') || 'Email ' + x.id; }
  function emailDelFlusso() { var o = {}; g.nodi.forEach(function (x) { if (x.tipo === 'email') o[x.id] = '#' + x.id + ' ' + nomeEmail(x); }); return o; }
  function dati() { return {grafo: g, inneschi: inneschi}; }

  // Le uscite di un blocco: fisse per tipo; le divisioni hanno quelle dei loro rami.
  function uscite(n) {
    var u = {};
    if (n.tipo === 'dividi') { n.dati.rami.forEach(function (r, i) { u['r' + i] = r.nome || 'Ramo ' + (i + 1); }); u.altro = 'Altrimenti'; return u; }
    if (n.tipo === 'percento') { n.dati.rami.forEach(function (p, i) { u['p' + i] = p + '%'; }); return u; }
    return D.uscite[n.tipo] || {};
  }

  // ---------- Riassunto di ogni blocco sulla tela ----------
  function riassunto(n) {
    var d = n.dati || {};
    switch (n.tipo) {
      case 'innesco': return inneschi.length ? inneschi.map(function (x) { return h(testoInnesco(x)); }).join('<br>') : '<i>Nessun innesco: aggiungine uno a sinistra</i>';
      case 'email':
        // 2.18.0: un passo "Da un modello" richiama il modello: la tela dice quale, e l'oggetto del modello se il passo non ne ha uno.
        if (d.editor === 'modello') {
          var mo = d.modello ? D.modelli[d.modello] : null;
          return (d.oggetto ? h(d.oggetto) : mo && mo.o ? h(mo.o) + ' <small class="fl-dal-modello">(oggetto del modello)</small>' : '<i class="fl-manca">Senza oggetto</i>') + '<small>' + (mo ? 'Modello: ' + h(mo.n) : d.modello ? '<i class="fl-manca">Modello #' + h(d.modello) + ' non trovato</i>' : '<i class="fl-manca">Modello da scegliere</i>') +
            (d.orario === 'ora' ? ', alle ' + d.ora + ':00' : d.orario === 'migliore' ? ', all\'orario migliore' : '') + (d.ascolto > 0 ? '; aspetta una reazione per ' + tempo(d.ascolto) : '') + '</small>';
        }
        return (d.oggetto ? h(d.oggetto) : '<i class="fl-manca">Senza oggetto</i>') + '<small>' + (d.editor === 'blocchi' ? 'editor a blocchi' : d.editor === 'html' ? 'codice HTML del modello' : 'testo semplice') + (d.orario === 'ora' ? ', alle ' + d.ora + ':00' : d.orario === 'migliore' ? ', all\'orario migliore' : '') + (d.ascolto > 0 ? '; aspetta una reazione per ' + tempo(d.ascolto) : '') + '</small>';
      case 'attesa': return 'Aspetta ' + h(tempo(d.minuti || 1440));
      case 'evento': return (d.evento === 'clic' ? 'Clicca in ' : 'Apre ') + h(d.email ? (emailDelFlusso()[d.email] || 'un\'email che non c\'è più') : 'qualsiasi email di questa automazione') + '<small>al massimo ' + h(tempo(d.massimo)) + '</small>';
      case 'bivio': var c = D.condizioni[d.condizione] || ['?', '']; return h(c[0]) + '<small>' + h(valoreBivio(d)) + '</small>';
      case 'dividi': return d.rami.length + (d.rami.length === 1 ? ' ramo' : ' rami') + ' più "altrimenti"';
      case 'percento': return d.rami.map(function (p) { return p + '%'; }).join(' / ') + (d.rami.reduce(function (s, p) { return s + (+p); }, 0) !== 100 ? '<small class="fl-manca">le parti devono fare 100</small>' : '');
      case 'lista_aggiungi': case 'lista_togli': return D.liste[d.lista] ? h(D.liste[d.lista]) : '<i class="fl-manca">Scegli la lista</i>';
      case 'attributo': return D.attributi[d.nome] ? h(D.attributi[d.nome].e) + ' = ' + h(d.valore === '' ? '(vuoto)' : d.valore) : '<i class="fl-manca">Scegli l\'attributo</i>';
      case 'webhook': return d.url ? h(d.url.replace(/^https?:\/\//, '').slice(0, 40)) : '<i class="fl-manca">Scrivi l\'indirizzo</i>';
      case 'fine': return 'Il cammino finisce qui.';
    }
    return '';
  }
  function valoreBivio(d) {
    var c = D.condizioni[d.condizione] || ['', ''];
    if (c[1] === 'lista') return D.liste[d.valore] ? 'lista: ' + D.liste[d.valore] : 'scegli la lista';
    if (c[1] === 'email') { var e = nodo(d.valore); return e && e.tipo === 'email' ? 'email: ' + nomeEmail(e) : 'scegli l\'email'; }
    if (c[1] === 'giorni') return (d.valore || 0) + ' giorni';
    if (c[1] === 'segmento') return (D.segmenti || {})[d.valore] ? 'segmento: ' + D.segmenti[d.valore] : 'scegli il segmento';
    return '';
  }
  // I numeri di un passo: quanti sono qui, quanti ci sono passati, e per le email gli invii con aperture e clic.
  function numeri(n) {
    var x = D.numeri[n.id], s = [];
    if (!x) return '';
    if (x.qui) s.push('<span>' + x.qui + ' qui adesso</span>');
    if (n.tipo === 'email' && x.email) {
      var e = x.email;
      s.push('<span>' + e.inviate + ' inviate</span>', '<span>' + e.apertura + ' aperture' + (e.inviate ? ' (' + String(e.t_apertura).replace('.', ',') + '%)' : '') + '</span>', '<span>' + e.clic + ' clic' + (e.inviate ? ' (' + String(e.t_clic).replace('.', ',') + '%)' : '') + '</span>');
    } else if (x.passati && n.id !== 0) s.push('<span>' + x.passati + ' passati</span>');
    if (n.id === 0 && x.passati) s.push('<span>' + x.passati + ' entrati</span>');
    return s.length ? '<div class="fl-nodo-numeri">' + s.join('') + '</div>' : '';
  }

  // ---------- Disegno ----------
  function disegna() {
    tela.querySelectorAll('.fl-nodo').forEach(function (el) { if (!nodo(+el.dataset.id)) el.remove(); });
    g.nodi.forEach(function (n) {
      var el = tela.querySelector('.fl-nodo[data-id="' + n.id + '"]');
      if (!el) { el = document.createElement('div'); el.className = 'fl-nodo'; el.dataset.id = n.id; el.dataset.tipo = n.tipo; el.tabIndex = 0; el.setAttribute('role', 'button'); tela.appendChild(el); }
      el.style.left = n.x + 'px'; el.style.top = n.y + 'px';
      el.classList.toggle('fl-scelto', scelto === n.id);
      el.setAttribute('aria-label', NOMI[n.tipo] + (n.id ? ' #' + n.id : '') + ': apri le impostazioni del blocco');
      var u = uscite(n), x = (D.numeri[n.id] || {}), html = '<div class="fl-nodo-testa"><span aria-hidden="true">' + ICONE[n.tipo] + '</span> ' + NOMI[n.tipo] + (n.tipo !== 'innesco' && n.tipo !== 'fine' ? '<span class="fl-num">#' + n.id + '</span>' : '') + '</div>';
      html += '<div class="fl-nodo-corpo">' + riassunto(n) + '</div>' + numeri(n);
      var keys = Object.keys(u);
      if (keys.length) {
        html += '<div class="fl-uscite">';
        keys.forEach(function (k) { html += '<span class="fl-uscita"><span class="fl-porta' + (arrivo(n.id, k) !== null ? ' fl-collegata' : '') + '" data-nodo="' + n.id + '" data-uscita="' + k + '" title="Tira da qui fino a un blocco"></span>' + h(u[k]) + (x[k] ? ' <b>' + x[k] + '</b>' : '') + '</span>'; });
        html += '</div>';
      }
      el.innerHTML = html;
    });
    disegnaArchi();
  }
  function posPorta(id, u) {
    var p = tela.querySelector('.fl-porta[data-nodo="' + id + '"][data-uscita="' + u + '"]'); if (!p) return null;
    var r = p.getBoundingClientRect(), t = tela.getBoundingClientRect();
    return {x: r.left - t.left + r.width / 2, y: r.top - t.top + r.height / 2};
  }
  function posIngresso(id) {
    var el = tela.querySelector('.fl-nodo[data-id="' + id + '"]'); if (!el) return null;
    var r = el.getBoundingClientRect(), t = tela.getBoundingClientRect();
    return {x: r.left - t.left + r.width / 2, y: r.top - t.top};
  }
  function curva(a, b) {
    var dy = Math.max(40, Math.abs(b.y - a.y) / 2);
    return 'M' + a.x + ',' + a.y + ' C' + a.x + ',' + (a.y + dy) + ' ' + b.x + ',' + (b.y - dy) + ' ' + b.x + ',' + b.y;
  }
  function disegnaArchi() {
    svg.innerHTML = '';
    var bottone = tela.querySelector('.fl-cestina-arco'); if (bottone) bottone.remove();
    g.archi.forEach(function (ar, i) {
      var a = posPorta(ar.da, ar.uscita), b = posIngresso(ar.a); if (!a || !b) return;
      var d = curva(a, b);
      var ombra = document.createElementNS(ns, 'path'); ombra.setAttribute('d', d); ombra.setAttribute('class', 'fl-ombra'); ombra.dataset.i = i; svg.appendChild(ombra);
      var p = document.createElementNS(ns, 'path'); p.setAttribute('d', d); if (arcoScelto === i) p.setAttribute('class', 'fl-scelto'); p.dataset.i = i; svg.appendChild(p);
      if (arcoScelto === i) {
        var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'fl-cestina-arco'; btn.textContent = '✕'; btn.title = 'Togli il collegamento'; btn.setAttribute('aria-label', 'Togli il collegamento');
        btn.style.left = (a.x + b.x) / 2 + 'px'; btn.style.top = (a.y + b.y) / 2 + 'px';
        btn.addEventListener('click', function (e) { e.stopPropagation(); g.archi.splice(i, 1); arcoScelto = null; disegna(); });
        tela.appendChild(btn);
      }
    });
  }
  svg.addEventListener('pointerdown', function (e) { var t = e.target.closest('path'); if (!t) return; arcoScelto = +t.dataset.i; scelto = null; pannelloVuoto(); disegnaArchi(); tela.querySelectorAll('.fl-nodo').forEach(function (el) { el.classList.remove('fl-scelto'); }); e.stopPropagation(); });
  document.addEventListener('keydown', function (e) { if ((e.key === 'Delete' || e.key === 'Backspace') && arcoScelto !== null && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && !document.activeElement.closest('.ql-editor')) { g.archi.splice(arcoScelto, 1); arcoScelto = null; disegna(); e.preventDefault(); } });
  tela.addEventListener('keydown', function (e) { var el = e.target.closest('.fl-nodo'); if (el && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); scegli(+el.dataset.id); var p = pannello.querySelector('input,select,button'); if (p) p.focus(); } });

  // ---------- Trascinamenti sulla tela ----------
  var drag = null;
  tela.addEventListener('pointerdown', function (e) {
    var porta = e.target.closest('.fl-porta');
    if (porta) {
      e.preventDefault(); tela.setPointerCapture(e.pointerId);
      drag = {tipo: 'arco', da: +porta.dataset.nodo, uscita: porta.dataset.uscita, path: document.createElementNS(ns, 'path')};
      drag.path.setAttribute('class', 'fl-temp'); svg.appendChild(drag.path);
      return;
    }
    var el = e.target.closest('.fl-nodo');
    if (el && !e.target.closest('button')) {
      e.preventDefault(); tela.setPointerCapture(e.pointerId);
      var n = nodo(+el.dataset.id), t = tela.getBoundingClientRect();
      drag = {tipo: 'nodo', n: n, el: el, dx: e.clientX - t.left - n.x, dy: e.clientY - t.top - n.y, mosso: false, x0: e.clientX, y0: e.clientY};
      return;
    }
    if (e.target === tela || e.target === svg) { arcoScelto = null; scegli(null); }
  });
  tela.addEventListener('pointermove', function (e) {
    if (!drag) return;
    var t = tela.getBoundingClientRect(), x = e.clientX - t.left, y = e.clientY - t.top;
    if (drag.tipo === 'nodo') {
      if (Math.abs(e.clientX - drag.x0) + Math.abs(e.clientY - drag.y0) > 3) drag.mosso = true;
      drag.n.x = Math.max(0, Math.round(x - drag.dx)); drag.n.y = Math.max(0, Math.round(y - drag.dy));
      drag.el.style.left = drag.n.x + 'px'; drag.el.style.top = drag.n.y + 'px';
      disegnaArchi();
    } else if (drag.tipo === 'arco') {
      var a = posPorta(drag.da, drag.uscita); drag.path.setAttribute('d', curva(a, {x: x, y: y}));
      tela.querySelectorAll('.fl-nodo').forEach(function (el) { el.classList.remove('fl-bersaglio'); });
      var sotto = document.elementFromPoint(e.clientX, e.clientY), b = sotto && sotto.closest('.fl-nodo');
      if (b && +b.dataset.id !== drag.da && +b.dataset.id !== 0) b.classList.add('fl-bersaglio');
    }
  });
  tela.addEventListener('pointerup', function (e) {
    if (!drag) return;
    if (drag.tipo === 'nodo') { if (!drag.mosso) scegli(drag.n.id); else disegnaArchi(); }
    else if (drag.tipo === 'arco') {
      drag.path.remove();
      var sotto = document.elementFromPoint(e.clientX, e.clientY), b = sotto && sotto.closest('.fl-nodo');
      if (b && +b.dataset.id !== drag.da && +b.dataset.id !== 0) collega(drag.da, drag.uscita, +b.dataset.id);
      tela.querySelectorAll('.fl-nodo').forEach(function (el) { el.classList.remove('fl-bersaglio'); });
      disegna();
    }
    drag = null;
  });
  tela.addEventListener('pointercancel', function () { if (drag && drag.path) drag.path.remove(); drag = null; });
  function collega(da, u, a) {
    g.archi = g.archi.filter(function (x) { return !(x.da === da && x.uscita === u); });
    g.archi.push({da: da, uscita: u, a: a});
  }

  // ---------- Pannello: schede Inneschi, Azioni, Regole ----------
  var schede = [].slice.call(document.querySelectorAll('.fl-schede [role=tab]'));
  function apriScheda(t, fuoco) {
    schede.forEach(function (s) { var on = s === t; s.setAttribute('aria-selected', on ? 'true' : 'false'); s.tabIndex = on ? 0 : -1; document.getElementById(s.getAttribute('aria-controls')).hidden = !on; });
    if (fuoco) t.focus();
  }
  schede.forEach(function (s, i) {
    s.addEventListener('click', function () { apriScheda(s); });
    s.addEventListener('keydown', function (e) {
      var j = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : e.key === 'Home' ? 0 : e.key === 'End' ? schede.length - 1 : null;
      if (j === null) return; e.preventDefault(); apriScheda(schede[(j + schede.length) % schede.length], true);
    });
  });

  // ---------- Tavolozza: trascina sulla tela, clicca o Invio ----------
  var pezzo = null;
  document.querySelectorAll('.fl-pezzo').forEach(function (p) {
    p.addEventListener('pointerdown', function (e) {
      e.preventDefault(); p.setPointerCapture(e.pointerId);
      var fantasma = p.cloneNode(true); fantasma.style.cssText = 'position:fixed;pointer-events:none;opacity:.8;width:' + p.offsetWidth + 'px;z-index:50;left:' + e.clientX + 'px;top:' + e.clientY + 'px;transform:translate(-20px,-20px)';
      document.body.appendChild(fantasma);
      pezzo = {tipo: p.dataset.tipo, el: fantasma, x0: e.clientX, y0: e.clientY, mosso: false};
    });
    p.addEventListener('pointermove', function (e) {
      if (!pezzo) return;
      if (Math.abs(e.clientX - pezzo.x0) + Math.abs(e.clientY - pezzo.y0) > 4) pezzo.mosso = true;
      pezzo.el.style.left = e.clientX + 'px'; pezzo.el.style.top = e.clientY + 'px';
    });
    p.addEventListener('pointerup', function (e) {
      if (!pezzo) return;
      pezzo.el.remove();
      var t = tela.getBoundingClientRect(), r = box.getBoundingClientRect();
      if (pezzo.mosso) {
        if (e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom) aggiungi(pezzo.tipo, Math.max(0, e.clientX - t.left - 20), Math.max(0, e.clientY - t.top - 20));
      } else aggiungi(pezzo.tipo);
      pezzo = null;
    });
    p.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); aggiungi(p.dataset.tipo); } });
  });
  function datiNuovi(tipo) {
    var primaEmail = Object.keys(emailDelFlusso())[0];
    return {
      email: {oggetto: '', anteprima: '', corpo: '', pulsante_testo: '', pulsante_link: '', ascolto: 0, editor: 'semplice', design: '', modello: 0, mittente_id: 0, orario: 'subito', ora: 10},
      attesa: {minuti: 1440}, bivio: {condizione: 'in_lista', valore: 0}, fine: {},
      evento: {evento: 'apertura', email: primaEmail ? +primaEmail : 0, massimo: 4320},
      dividi: {rami: [{nome: '', regole: {op: 'e', gruppi: []}}]}, percento: {rami: [50, 50]},
      lista_aggiungi: {lista: +(Object.keys(D.liste)[0] || 0)}, lista_togli: {lista: +(Object.keys(D.liste)[0] || 0)},
      attributo: {nome: Object.keys(D.attributi)[0] || '', valore: ''}, webhook: {url: ''}
    }[tipo];
  }
  // Senza una posizione: sotto il blocco scelto, collegato alla sua prima uscita libera (il "+" sotto un passo).
  function aggiungi(tipo, x, y) {
    if (g.nodi.length >= D.max.nodi) { avviso('Al massimo ' + D.max.nodi + ' blocchi in un\'automazione.'); return; }
    var n = {id: nuovoId(), tipo: tipo, x: 0, y: 0, dati: datiNuovi(tipo)}, da = scelto !== null ? nodo(scelto) : null, libera = null, sotto = x === undefined;
    if (da) Object.keys(uscite(da)).some(function (u) { if (arrivo(da.id, u) === null) { libera = u; return true; } return false; });
    if (sotto) {
      if (da && libera !== null) { var k = Object.keys(uscite(da)).indexOf(libera); x = da.x + k * 250; y = da.y + 230; }
      else { x = box.scrollLeft + box.clientWidth / 2 - 115 + (g.nodi.length % 5) * 15; y = box.scrollTop + box.clientHeight / 2 - 60 + (g.nodi.length % 5) * 15; }
    }
    n.x = Math.max(0, Math.round(x)); n.y = Math.max(0, Math.round(y));
    g.nodi.push(n);
    if (sotto && da && libera !== null) collega(da.id, libera, n.id);
    disegna(); scegli(n.id);
    var el = tela.querySelector('.fl-nodo[data-id="' + n.id + '"]'); if (el && el.scrollIntoView) el.scrollIntoView({block: 'nearest', inline: 'nearest'});
  }
  function elimina(id) {
    if (id === 0) return;
    g.nodi = g.nodi.filter(function (n) { return n.id !== id; });
    g.archi = g.archi.filter(function (a) { return a.da !== id && a.a !== id; });
    scegli(null); disegna();
  }
  function avviso(t) { var a = document.getElementById('fl-avviso-js'); if (!a) { a = document.createElement('p'); a.id = 'fl-avviso-js'; a.className = 'fl-avviso'; a.setAttribute('role', 'alert'); document.getElementById('fl-form').insertBefore(a, document.querySelector('.fl-corpo')); } a.textContent = t; }

  // ---------- Pannello del blocco scelto ----------
  function pannelloVuoto() { quill = null; pannello.innerHTML = '<div class="fl-pannello-vuoto">Clicca un blocco per modificarlo.<br><br>Per cancellare un collegamento, clicca la linea e premi Canc.</div>'; }
  function campo(id, et, dentro, aiuto) { return '<label for="' + id + '">' + et + '</label>' + dentro + (aiuto ? '<p class="hint">' + aiuto + '</p>' : ''); }
  function scegli(id) {
    scelto = id; arcoScelto = null;
    tela.querySelectorAll('.fl-nodo').forEach(function (el) { el.classList.toggle('fl-scelto', +el.dataset.id === id); });
    disegnaArchi();
    if (id === null) { pannelloVuoto(); return; }
    var n = nodo(id), d = n.dati, html = '<h3><span><span aria-hidden="true">' + ICONE[n.tipo] + '</span> ' + NOMI[n.tipo] + (id ? ' #' + id : '') + '</span>' + (id ? ' <button type="button" class="fl-elimina" data-elimina="' + id + '" data-conferma="Togliere questo blocco dalla tela, con i suoi collegamenti?" data-conferma-ok="Togli il blocco" data-conferma-pericolo>Togli il blocco</button>' : '') + '</h3>';
    var tempoCampo = function (et, min, minimo, aiuto) { var u = unita(min || minimo); return '<label for="fp-n">' + et + '</label><div class="fl-tempo"><input type="number" min="' + minimo + '" id="fp-n" value="' + (min ? u.n : 0) + '" aria-label="' + et + ': quanti"><select id="fp-u" aria-label="' + et + ': unità">' + opzUnita(min ? u.u : 1440) + '</select></div>' + (aiuto ? '<p class="hint">' + aiuto + '</p>' : ''); };
    switch (n.tipo) {
      case 'innesco': html += '<p class="hint">Il punto di partenza. Gli inneschi si scelgono a sinistra, nella scheda Inneschi. Tira dal pallino "Poi" al primo blocco.</p>'; break;
      case 'fine': html += '<p class="hint">Chi arriva qui ha finito. Serve a chiudere un ramo in modo esplicito; un\'uscita lasciata libera fa lo stesso.</p>'; break;
      case 'attesa': html += tempoCampo('Aspetta', d.minuti, 1, 'Almeno 1 minuto. Il cammino riparte passata l\'attesa, al primo giro del cron (ogni cinque minuti).'); break;
      case 'evento':
        html += campo('fp-ev', 'Che cosa aspetta', '<select id="fp-ev">' + opz({apertura: 'Apre l\'email', clic: 'Clicca un link dell\'email'}, d.evento) + '</select>');
        html += campo('fp-em', 'Quale email', '<select id="fp-em">' + opz(Object.assign({'0': 'Qualsiasi email di questa automazione'}, emailDelFlusso()), d.email) + '</select>');
        html += tempoCampo('Al massimo per', d.massimo, 1, 'Appena succede esce da "Se succede". Passato il tempo, da "Se non succede in tempo". Se era già successo da quando è entrato, esce subito da "Se succede".');
        break;
      case 'bivio': html += campo('fp-cond', 'Condizione', '<select id="fp-cond">' + Object.keys(D.condizioni).map(function (k) { return '<option value="' + k + '"' + (d.condizione === k ? ' selected' : '') + '>' + h(D.condizioni[k][0]) + '</option>'; }).join('') + '</select>') + '<div id="fp-valore"></div><p class="hint">Sì e No sono le due uscite del blocco: collegale a quello che deve succedere in ciascun caso.</p>'; break;
      case 'dividi':
        html += '<p class="hint">I rami si controllano in ordine: la persona prende il primo che vale per lei; se non ne vale nessuno, "Altrimenti". Fino a ' + D.max.rami + ' rami.</p><div id="fp-rami"></div><button type="button" class="btn btn-secondary btn-sm" id="fp-ramo-piu"' + (d.rami.length >= D.max.rami ? ' disabled' : '') + '>+ Aggiungi un ramo</button>';
        break;
      case 'percento':
        html += '<p class="hint">Le parti devono fare 100. Le persone si dividono nell\'ordine in cui arrivano, così le proporzioni restano esatte. Da 2 a 5 parti.</p><div id="fp-parti">' + d.rami.map(function (p, i) { return '<div class="fl-parte"><label for="fp-p' + i + '">Parte ' + (i + 1) + ' (%)</label><input type="number" min="1" max="99" id="fp-p' + i + '" data-parte="' + i + '" value="' + p + '">' + (d.rami.length > 2 ? '<button type="button" class="btn btn-danger btn-sm" data-togli-parte="' + i + '">Togli la parte ' + (i + 1) + '</button>' : '') + '</div>'; }).join('') + '</div><p class="fl-somma" id="fp-somma" role="status"></p>' + (d.rami.length < 5 ? '<button type="button" class="btn btn-secondary btn-sm" id="fp-parte-piu">+ Aggiungi una parte</button>' : '');
        break;
      case 'lista_aggiungi': case 'lista_togli':
        html += Object.keys(D.liste).length ? campo('fp-lista', 'Quale lista', '<select id="fp-lista">' + opz(D.liste, d.lista) + '</select>', n.tipo === 'lista_aggiungi' ? 'Se la lista ha delle automazioni con l\'innesco "Entra in una lista", partono anche quelle.' : 'Parte anche l\'innesco "Viene tolto da una lista" delle altre automazioni.') : '<p class="hint">Ancora nessuna lista: creala in Contatti, scheda Liste.</p>';
        break;
      case 'attributo':
        html += Object.keys(D.attributi).length ? campo('fp-attr', 'Quale attributo', '<select id="fp-attr">' + Object.keys(D.attributi).map(function (k) { return '<option value="' + h(k) + '"' + (k === d.nome ? ' selected' : '') + '>' + h(D.attributi[k].e) + '</option>'; }).join('') + '</select>') + '<div id="fp-attr-valore"></div>' : '<p class="hint">Ancora nessun attributo da cambiare: crealo in Contatti, Attributi.</p>';
        break;
      case 'webhook':
        html += campo('fp-url', 'Indirizzo che riceve i dati', '<input type="url" id="fp-url" value="' + h(d.url) + '" maxlength="500" placeholder="per esempio: https://servizio.esempio.it/ricevi" inputmode="url">', 'Solo https. Ogni volta che una persona passa di qui, il sito manda a questo indirizzo il nome dell\'automazione e i dati del contatto (email, nome, cognome). Gli indirizzi di questo server o della sua rete sono rifiutati; i rimandi a un altro indirizzo non si seguono. L\'esito si legge nel registro.');
        break;
      case 'email':
        var moS = d.editor === 'modello' && d.modello ? D.modelli[d.modello] : null;
        html += campo('fp-oggetto', 'Oggetto', '<input type="text" id="fp-oggetto" value="' + h(d.oggetto) + '" maxlength="255" placeholder="' + h(segnaposto(moS, 'o', 'per esempio: Benvenuto, {nome}')) + '">', '{nome} diventa il nome della persona.' + (d.editor === 'modello' ? ' Vuoto: vale quello del modello quando l\'email parte.' : ''));
        html += campo('fp-anteprima', 'Testo di anteprima', '<input type="text" id="fp-anteprima" value="' + h(d.anteprima) + '" maxlength="255" placeholder="' + h(segnaposto(moS, 'a', 'per esempio: Tre cose da sapere')) + '">', d.editor === 'modello' ? 'Vuoto: vale quello del modello.' : '');
        html += campo('fp-mitt', 'Mittente', '<select id="fp-mitt">' + opz(D.mittenti, d.mittente_id) + '</select>');
        // 2.17.1: tre scelte; "Da un modello" elenca tutti i modelli (a blocchi e in codice HTML) e li copia al salvataggio.
        var nMod = Object.keys(D.modelli).length, ed = d.editor === 'html' ? 'html' : d.editor;
        html += '<fieldset class="fl-scelta"><legend>Contenuto</legend><label class="fl-spunta"><input type="radio" name="fp-editor" value="semplice"' + (ed === 'semplice' || !ed ? ' checked' : '') + '> Testo semplice</label><label class="fl-spunta"><input type="radio" name="fp-editor" value="blocchi"' + (ed === 'blocchi' ? ' checked' : '') + '> Editor a blocchi</label><label class="fl-spunta"><input type="radio" name="fp-editor" value="modello"' + (ed === 'modello' ? ' checked' : '') + '> Da un modello</label>' + (ed === 'html' ? '<label class="fl-spunta"><input type="radio" name="fp-editor" value="html" checked> Codice HTML del modello</label>' : '') + '</fieldset>';
        // 2.18.0: il passo richiama il modello e lo legge ogni volta che l'email parte (la 2.17.1 lo copiava al salvataggio).
        var manca = ed === 'modello' && d.modello && !D.modelli[d.modello];
        html += '<div id="fp-da-modello"' + (ed === 'modello' ? '' : ' hidden') + '>' + (nMod || manca ? campo('fp-modello', 'Quale modello', '<select id="fp-modello"><option value="0">Scegli un modello</option>' + (manca ? '<option value="' + h(d.modello) + '" selected>Modello #' + h(d.modello) + ' (non trovato)</option>' : '') + Object.keys(D.modelli).map(function (k) { var m = D.modelli[k]; return '<option value="' + h(k) + '"' + (String(k) === String(d.modello) ? ' selected' : '') + '>' + h(m.n) + (m.t === 'html' ? ' (codice HTML)' : '') + '</option>'; }).join('') + '</select>', 'L\'email legge il modello ogni volta che parte: se correggi il modello in Modelli, da quel momento esce corretta. Oggetto e anteprima vuoti qui sopra: valgono quelli del modello.') +
          (manca ? '<p class="fl-manca" role="alert">Il modello #' + h(d.modello) + ' non c\'è più: questa email non parte e chi arriva qui si ferma. Scegline un altro e salva.</p>' : '') +
          '<p class="fl-modello-azioni"><a class="btn btn-secondary btn-sm" id="fp-apri-modello" href="#"' + (d.modello && D.modelli[d.modello] ? '' : ' hidden') + '>Apri il modello</a> <button type="button" class="btn btn-secondary btn-sm" id="fp-stacca"' + (d.modello && D.modelli[d.modello] ? '' : ' hidden') + '>Stacca dal modello</button></p><p class="hint" id="fp-stacca-aiuto">"Stacca dal modello" salva l\'automazione e copia in questa email il contenuto di adesso del modello: da lì in poi cambiare il modello non la tocca più.</p>' : '<p class="hint">Ancora nessun modello. Creali in <a href="newsletter-modelli.php">Newsletter e form, Modelli</a>.</p>') + '</div>';
        if (ed === 'html') html += '<p class="hint" id="fp-html-nota">Questa email usa il codice HTML di un modello. Per cambiarla scegli un altro modello, oppure il testo semplice.</p>';
        html += '<div id="fp-semplice"' + (ed === 'semplice' || !ed ? '' : ' hidden') + '><label for="fp-corpo-q">Testo</label><div id="fp-corpo" class="fl-editor"></div>' + campo('fp-pt', 'Pulsante (facoltativo)', '<input type="text" id="fp-pt" value="' + h(d.pulsante_testo) + '" placeholder="per esempio: Scopri di più" maxlength="100"><input type="text" id="fp-pl" value="' + h(d.pulsante_link) + '" placeholder="per esempio: https://esempio.it/pagina" inputmode="url" aria-label="Indirizzo del pulsante">') + '</div>';
        html += '<div id="fp-blocchi"' + (ed === 'blocchi' ? '' : ' hidden') + '>' +
          '<button type="button" class="btn btn-secondary btn-sm" id="fp-apri-design">Salva e apri l\'editor a blocchi</button><p class="hint">L\'automazione si salva, poi si apre l\'editor delle email; con "Torna" ritorni qui.</p></div>';
        html += campo('fp-orario', 'Quando parte', '<select id="fp-orario">' + opz({subito: 'Appena la persona arriva qui', ora: 'A un\'ora precisa del giorno', migliore: 'All\'orario migliore di ogni persona'}, d.orario) + '</select>') + '<div id="fp-ora-box"' + (d.orario === 'subito' ? ' hidden' : '') + '>' + campo('fp-ora', d.orario === 'migliore' ? 'Ora per chi non ha ancora un orario' : 'Alle ore', '<select id="fp-ora">' + Array.from({length: 24}, function (x, i) { return '<option value="' + i + '"' + (i === +d.ora ? ' selected' : '') + '>' + ('0' + i).slice(-2) + ':00</option>'; }).join('') + '</select>', 'L\'orario migliore è l\'ora in cui la persona apre di più le email (almeno due aperture nell\'ultimo anno).') + '</div>';
        html += tempoCampo('Aspetta una reazione per', d.ascolto, 0, 'Con 0 l\'email prosegue subito dall\'uscita "Subito dopo". Con un tempo, resta in ascolto: appena la persona apre o clicca parte da "Se apre" o "Se clicca"; passato il tempo senza niente, da "Se non fa niente" (o da "Subito dopo" se quella è libera).');
        break;
    }
    pannello.innerHTML = html;
    var b = pannello.querySelector('[data-elimina]'); if (b) b.addEventListener('click', function () { elimina(+b.dataset.elimina); });
    var leggi = function (sel, ev, f) { var el = pannello.querySelector(sel); if (el) el.addEventListener(ev, function () { f(el); disegna(); }); };
    if (pannello.querySelector('#fp-n')) {
      var leggiTempo = function () { var v = Math.max(0, parseInt(pannello.querySelector('#fp-n').value || '0', 10)) * parseInt(pannello.querySelector('#fp-u').value, 10); if (n.tipo === 'attesa') d.minuti = Math.max(1, v); else if (n.tipo === 'evento') d.massimo = Math.max(1, v); else d.ascolto = v; disegna(); };
      pannello.querySelector('#fp-n').addEventListener('input', leggiTempo); pannello.querySelector('#fp-u').addEventListener('change', leggiTempo);
    }
    if (n.tipo === 'evento') { leggi('#fp-ev', 'change', function (el) { d.evento = el.value; }); leggi('#fp-em', 'change', function (el) { d.email = +el.value; }); }
    if (n.tipo === 'bivio') pannelloBivio(n);
    if (n.tipo === 'dividi') pannelloDividi(n);
    if (n.tipo === 'percento') pannelloPercento(n);
    if (n.tipo === 'lista_aggiungi' || n.tipo === 'lista_togli') { leggi('#fp-lista', 'change', function (el) { d.lista = +el.value; }); var ls = pannello.querySelector('#fp-lista'); if (ls) d.lista = +ls.value; }
    if (n.tipo === 'attributo') pannelloAttributo(n);
    if (n.tipo === 'webhook') leggi('#fp-url', 'input', function (el) { d.url = el.value.trim(); });
    if (n.tipo === 'email') pannelloEmail(n);
  }
  function pannelloBivio(n) {
    var d = n.dati, cond = pannello.querySelector('#fp-cond');
    var valore = function () {
      var tipo = D.condizioni[d.condizione][1], vb = pannello.querySelector('#fp-valore'), s = '';
      if (tipo === 'lista') s = '<label for="fp-val">Quale lista</label><select id="fp-val">' + opz(D.liste, d.valore) + '</select>';
      if (tipo === 'email') s = '<label for="fp-val">Quale email di questa automazione</label><select id="fp-val">' + opz(emailDelFlusso(), d.valore) + '</select>';
      if (tipo === 'giorni') s = '<label for="fp-val">Giorni</label><input type="number" id="fp-val" min="1" max="3650" value="' + (d.valore || 30) + '">';
      if (tipo === 'segmento') { var sg = D.segmenti || {}; s = Object.keys(sg).length ? '<label for="fp-val">Quale segmento</label><select id="fp-val">' + opz(sg, d.valore) + '</select><p class="hint">Il segmento si controlla quando la persona arriva qui.</p>' : '<p class="hint">Ancora nessun segmento: crealo in Contatti, scheda Segmenti.</p>'; }
      vb.innerHTML = s;
      var v = vb.querySelector('#fp-val');
      if (v) { if (!d.valore || (tipo !== 'giorni' && ![].slice.call(v.options).some(function (o) { return +o.value === +d.valore; }))) d.valore = +v.value || 0; ['input', 'change'].forEach(function (ev) { v.addEventListener(ev, function () { d.valore = +v.value || 0; disegna(); }); }); }
      disegna();
    };
    cond.addEventListener('change', function () { d.condizione = cond.value; d.valore = 0; valore(); });
    valore();
  }
  function pannelloDividi(n) {
    var d = n.dati, rb = pannello.querySelector('#fp-rami');
    if (window.nlsDef) window.nlsDef.flussoEmail = emailDelFlusso();
    var giro = function () {
      rb.innerHTML = '';
      d.rami.forEach(function (r, i) {
        var f = document.createElement('fieldset'); f.className = 'fl-ramo';
        f.innerHTML = '<legend>Ramo ' + (i + 1) + '</legend><label for="fp-rn' + i + '">Nome del ramo</label><input type="text" id="fp-rn' + i + '" maxlength="60" value="' + h(r.nome) + '" placeholder="per esempio: Clienti di Roma"><input type="hidden" id="fp-rr' + i + '" value="' + (r.regole && r.regole.gruppi && r.regole.gruppi.length ? b64(r.regole) : '') + '"><div class="fl-regole" data-nls-editor="#fp-rr' + i + '"></div>' + (d.rami.length > 1 ? '<button type="button" class="btn btn-danger btn-sm" data-togli-ramo="' + i + '">Togli il ramo ' + (i + 1) + '</button>' : '');
        rb.appendChild(f);
        f.querySelector('#fp-rn' + i).addEventListener('input', function (e) { r.nome = e.target.value; disegna(); });
        var ed = f.querySelector('.fl-regole');
        ed.nlsCambio = function (v) { r.regole = v ? (deB64(v) || {op: 'e', gruppi: []}) : {op: 'e', gruppi: []}; bozzaPiuTardi(); };
        if (window.nlsMonta) window.nlsMonta(ed); else ed.innerHTML = '<p class="hint">Il costruttore delle condizioni non si è caricato: ricarica la pagina.</p>';
        var t = f.querySelector('[data-togli-ramo]'); if (t) t.addEventListener('click', function () { d.rami.splice(i, 1); g.archi = g.archi.filter(function (a) { return a.da !== n.id || !/^r\d+$/.test(a.uscita); }); giro(); disegna(); });
      });
    };
    giro();
    pannello.querySelector('#fp-ramo-piu').addEventListener('click', function () { if (d.rami.length >= D.max.rami) return; d.rami.push({nome: '', regole: {op: 'e', gruppi: []}}); giro(); disegna(); this.disabled = d.rami.length >= D.max.rami; });
  }
  function pannelloPercento(n) {
    var d = n.dati, somma = function () { var s = d.rami.reduce(function (t, p) { return t + (+p || 0); }, 0); var el = pannello.querySelector('#fp-somma'); el.textContent = 'In tutto: ' + s + '%' + (s === 100 ? '' : ' (devono fare 100)'); el.classList.toggle('fl-manca', s !== 100); };
    pannello.querySelectorAll('[data-parte]').forEach(function (el) { el.addEventListener('input', function () { d.rami[+el.dataset.parte] = Math.max(1, Math.min(99, parseInt(el.value || '0', 10) || 1)); somma(); disegna(); }); });
    pannello.querySelectorAll('[data-togli-parte]').forEach(function (b) { b.addEventListener('click', function () { var i = +b.dataset.togliParte; d.rami.splice(i, 1); g.archi = g.archi.filter(function (a) { return a.da !== n.id; }); scegli(n.id); disegna(); }); });
    var piu = pannello.querySelector('#fp-parte-piu'); if (piu) piu.addEventListener('click', function () { d.rami.push(10); scegli(n.id); disegna(); });
    somma();
  }
  function pannelloAttributo(n) {
    var d = n.dati, sel = pannello.querySelector('#fp-attr'); if (!sel) return;
    var valore = function () {
      var a = D.attributi[d.nome] || {t: 'text', o: {}}, vb = pannello.querySelector('#fp-attr-valore'), s;
      if (a.t === 'boolean') s = '<select id="fp-av">' + opz({'': '(vuoto)', '1': 'Sì', '0': 'No'}, d.valore) + '</select>';
      else if (a.t === 'category') s = '<select id="fp-av">' + opz(Object.assign({'': '(vuoto)'}, a.o), d.valore) + '</select>';
      else s = '<input type="' + (a.t === 'date' ? 'date' : a.t === 'float' ? 'number' : 'text') + '" step="any" id="fp-av" maxlength="500" value="' + h(d.valore) + '" placeholder="' + (a.t === 'date' ? '' : 'per esempio: cliente') + '">';
      vb.innerHTML = campo('fp-av', 'Nuovo valore', s, 'Vuoto toglie il valore. Un valore del tipo sbagliato non si salva: lo dice il Salva.');
      var el = vb.querySelector('#fp-av'); ['input', 'change'].forEach(function (ev) { el.addEventListener(ev, function () { d.valore = el.value; disegna(); }); });
    };
    if (!D.attributi[d.nome]) d.nome = sel.value;
    sel.addEventListener('change', function () { d.nome = sel.value; d.valore = ''; valore(); disegna(); });
    valore();
  }
  function pannelloEmail(n) {
    var d = n.dati;
    ['oggetto', 'anteprima'].forEach(function (k) { pannello.querySelector('#fp-' + k).addEventListener('input', function (e) { d[k] = e.target.value; disegna(); }); });
    pannello.querySelector('#fp-mitt').addEventListener('change', function (e) { d.mittente_id = +e.target.value; });
    pannello.querySelector('#fp-pt').addEventListener('input', function (e) { d.pulsante_testo = e.target.value; });
    pannello.querySelector('#fp-pl').addEventListener('input', function (e) { d.pulsante_link = e.target.value; });
    pannello.querySelectorAll('[name=fp-editor]').forEach(function (r) { r.addEventListener('change', function () {
      d.editor = r.value; if (r.value !== 'modello') d.modello = 0; else if (m) { d.modello = +m.value; mostraModello(); }
      pannello.querySelector('#fp-semplice').hidden = r.value !== 'semplice'; pannello.querySelector('#fp-blocchi').hidden = r.value !== 'blocchi';
      pannello.querySelector('#fp-da-modello').hidden = r.value !== 'modello'; var nota = pannello.querySelector('#fp-html-nota'); if (nota) nota.hidden = r.value !== 'html';
      disegna(); }); });
    // 2.18.0: scegliere un modello non copia più niente: aggiorna i segnaposti di oggetto e anteprima e il link "Apri il modello".
    var m = pannello.querySelector('#fp-modello'), apri = pannello.querySelector('#fp-apri-modello'), stacca = pannello.querySelector('#fp-stacca');
    var mostraModello = function () {
      var mm = D.modelli[d.modello] || null;
      pannello.querySelector('#fp-oggetto').placeholder = segnaposto(mm, 'o', 'per esempio: Benvenuto, {nome}');
      pannello.querySelector('#fp-anteprima').placeholder = segnaposto(mm, 'a', 'per esempio: Tre cose da sapere');
      if (apri) { apri.hidden = !mm; apri.href = mm ? mm.u : '#'; apri.setAttribute('aria-label', mm ? 'Apri il modello ' + mm.n : 'Apri il modello'); }
      if (stacca) stacca.hidden = !mm;
    };
    if (m) { m.addEventListener('change', function () { d.modello = +m.value; mostraModello(); disegna(); }); if (d.editor === 'modello') mostraModello(); }
    if (stacca) stacca.addEventListener('click', function () { document.getElementById('fl-stacca').value = n.id; var s = document.createElement('button'); s.type = 'submit'; s.name = 'azione'; s.value = 'stacca'; s.hidden = true; form.appendChild(s); s.click(); });
    pannello.querySelector('#fp-apri-design').addEventListener('click', function () { document.getElementById('fl-nodo-design').value = n.id; var s = document.createElement('button'); s.type = 'submit'; s.name = 'azione'; s.value = 'design'; s.hidden = true; form.appendChild(s); s.click(); });
    var or = pannello.querySelector('#fp-orario');
    or.addEventListener('change', function () { d.orario = or.value; scegli(n.id); disegna(); });
    pannello.querySelector('#fp-ora').addEventListener('change', function (e) { d.ora = +e.target.value; disegna(); });
    if (typeof Quill === 'undefined') return;
    quill = new Quill('#fp-corpo', {theme: 'snow', modules: {toolbar: [[{header: [2, 3, false]}], ['bold', 'italic', 'underline'], ['link', 'blockquote'], [{list: 'ordered'}, {list: 'bullet'}], ['clean']]}});
    quill.root.id = 'fp-corpo-q'; quill.root.setAttribute('aria-label', 'Testo dell\'email');
    quill.clipboard.dangerouslyPasteHTML(d.corpo || '');
    if (window.pnLink) window.pnLink.quill(quill, { assoluto: true }); // 2.2: il link di un'email sceglie una pagina del sito (indirizzo intero)
    quill.on('text-change', function () { d.corpo = quill.root.innerHTML === '<p><br></p>' ? '' : quill.root.innerHTML; bozzaPiuTardi(); });
  }

  // ---------- Inneschi ----------
  var boxInn = document.getElementById('fl-inneschi');
  function testoInnesco(x) {
    var c = D.cosa[x.tipo] || {}, t = D.nomiInneschi[x.tipo] || x.tipo;
    if (x.tipo === 'inattivo') return t + ' (' + (x.id || '?') + ' giorni)';
    if (x.tipo === 'anniversario') return t + ': ' + (c[x.attr] || 'scegli la data') + (x.id ? ', ' + x.id + ' giorni prima' : '');
    // 2.22.0: un innesco di un addon con il filtro sul risultato (Completa un test: quale test e con quale risultato).
    var es = D.esiti && D.esiti[x.tipo] && x.id ? (D.esiti[x.tipo][x.id] || {}) : null;
    if (x.tipo in D.cosa && x.tipo !== 'anniversario') return t + ': ' + (x.id ? (c[x.id] || 'non c\'è più') : (x.tipo === 'apertura' || x.tipo === 'clic' ? 'qualsiasi campagna' : 'qualunque')) + (es && x.esito ? ', ' + (es[x.esito] || 'risultato che non c\'è più') : '');
    return t;
  }
  function disegnaInneschi(fuoco) {
    boxInn.innerHTML = inneschi.map(function (x, i) {
      var c = D.cosa[x.tipo] || {}, s = '<fieldset class="fl-innesco" data-i="' + i + '"><legend>Innesco ' + (i + 1) + '</legend><label for="fi-t' + i + '">Quando</label><select id="fi-t' + i + '" data-k="tipo">' + opz(D.nomiInneschi, x.tipo) + '</select>';
      if (x.tipo === 'inattivo') s += '<label for="fi-v' + i + '">Da quanti giorni</label><input type="number" id="fi-v' + i + '" data-k="id" min="1" max="3650" value="' + (x.id || 30) + '">';
      else if (x.tipo === 'anniversario') s += (Object.keys(c).length ? '<label for="fi-a' + i + '">Quale data</label><select id="fi-a' + i + '" data-k="attr">' + opz(c, x.attr) + '</select>' : '<p class="hint">Serve un attributo di tipo Data: crealo in Contatti, Attributi.</p>') + '<label for="fi-v' + i + '">Quanti giorni prima (0 = il giorno stesso)</label><input type="number" id="fi-v' + i + '" data-k="id" min="0" max="365" value="' + (x.id || 0) + '">';
      else if (x.tipo in D.cosa) s += '<label for="fi-v' + i + '">Quale</label><select id="fi-v' + i + '" data-k="id">' + opz(Object.assign({'0': x.tipo === 'apertura' || x.tipo === 'clic' ? 'Qualsiasi campagna' : 'Qualunque'}, c), x.id) + '</select>';
      if (D.esiti && D.esiti[x.tipo]) s += x.id ? '<label for="fi-e' + i + '">Con quale risultato</label><select id="fi-e' + i + '" data-k="esito">' + opz(Object.assign({'': 'Qualunque risultato'}, D.esiti[x.tipo][x.id] || {}), x.esito || '') + '</select>' : '<p class="hint">Scegli il test per filtrare anche sul risultato.</p>';
      if (x.tipo === 'mano') s += '<p class="hint">Si aggiunge dalla scheda Attività e registro.</p>';
      if (x.tipo === 'iscrizione') s += '<p class="hint">Con la doppia conferma conta quando la persona conferma.</p>';
      return s + '<button type="button" class="btn btn-danger btn-sm" data-togli-innesco="' + i + '">Togli l\'innesco ' + (i + 1) + '</button></fieldset>';
    }).join('') || '<p class="hint fl-manca">Nessun innesco: nessuno può entrare. Aggiungine uno.</p>';
    boxInn.querySelectorAll('[data-k=attr]').forEach(function (el) { var x = inneschi[+el.closest('[data-i]').dataset.i]; if (!x.attr) x.attr = el.value; });
    document.getElementById('fl-innesco-piu').disabled = inneschi.length >= D.max.inneschi;
    if (fuoco) { var el = boxInn.querySelector(fuoco); if (el) el.focus(); }
    disegna();
  }
  boxInn.addEventListener('change', function (e) {
    var el = e.target, i = el.closest('[data-i]'); if (!i) return;
    var x = inneschi[+i.dataset.i], k = el.dataset.k;
    if (k === 'tipo') { inneschi[+i.dataset.i] = {tipo: el.value, id: el.value === 'inattivo' ? 30 : 0}; disegnaInneschi('#' + el.id); return; }
    x[k] = k === 'attr' || k === 'esito' ? el.value : +el.value || 0;
    if (k === 'id' && D.esiti && D.esiti[x.tipo]) { x.esito = ''; disegnaInneschi('#' + el.id); return; }
    disegna();
  });
  boxInn.addEventListener('input', function (e) { var el = e.target, i = el.closest('[data-i]'); if (i && el.type === 'number') { inneschi[+i.dataset.i].id = +el.value || 0; disegna(); } });
  boxInn.addEventListener('click', function (e) { var b = e.target.closest('[data-togli-innesco]'); if (!b) return; inneschi.splice(+b.dataset.togliInnesco, 1); disegnaInneschi('#fl-innesco-piu'); });
  document.getElementById('fl-innesco-piu').addEventListener('click', function () { if (inneschi.length >= D.max.inneschi) return; inneschi.push({tipo: 'lista', id: 0}); disegnaInneschi('#fi-t' + (inneschi.length - 1)); });

  // ---------- Riordino automatico: colonne per profondità dall'innesco ----------
  function ordina() {
    var prof = {0: 0}, coda = [0], ordine = [0];
    while (coda.length) { var id = coda.shift(); g.archi.filter(function (a) { return a.da === id; }).forEach(function (a) { if (prof[a.a] === undefined) { prof[a.a] = prof[id] + 1; coda.push(a.a); ordine.push(a.a); } }); }
    var max = Math.max.apply(null, Object.values(prof).concat([0]));
    g.nodi.forEach(function (n) { if (prof[n.id] === undefined) { prof[n.id] = ++max; ordine.push(n.id); } });
    var righe = {};
    ordine.forEach(function (id) { var n = nodo(id), c = prof[id]; righe[c] = (righe[c] || 0); n.y = 40 + c * 230; n.x = 40 + righe[c] * 270; righe[c]++; });
    disegna();
  }

  // ---------- Salvataggio ----------
  var form = document.getElementById('fl-form'), nome = form.querySelector('.fl-nome');
  // Il grafo va nel campo nascosto in JSON; a proteggerlo dal firewall del server pensa campi-protetti.js (base64 con ~b64~).
  form.addEventListener('submit', function (e) {
    if (e.submitter && e.submitter.value === 'elimina') { bozzaTogli(); return; }
    document.getElementById('fl-grafo').value = JSON.stringify(g);
    document.getElementById('fl-inneschi-campo').value = JSON.stringify(inneschi);
    bozzaScrivi(true);
  });
  // Il nome è obbligatorio: il messaggio del browser dice perché e porta il campo in vista, anche se si era scesi sulla tela.
  nome.addEventListener('invalid', function () { nome.setCustomValidity('Dai un nome all\'automazione prima di salvarla.'); nome.scrollIntoView({block: 'center'}); });
  nome.addEventListener('input', function () { nome.setCustomValidity(''); bozzaPiuTardi(); });
  ['#fl-stato', '#fl-tag'].forEach(function (s) { form.querySelector(s).addEventListener('change', function () { bozzaPiuTardi(); }); });

  // ---------- Sessione viva e copia di sicurezza ----------
  // Ogni cinque minuti un battito al server: la sessione del pannello non scade mentre si disegna.
  setInterval(function () { try { fetch('newsletter-flussi.php?ping=1', {credentials: 'same-origin', cache: 'no-store'}); } catch (e) {} }, 5 * 60 * 1000);
  // Una copia del lavoro nel browser, aggiornata a ogni modifica: se il salvataggio va male (sessione scaduta,
  // errore del server, rete), alla riapertura si può riprendere da dove si era.
  var pronto = false, chiaveBozza = 'fl-bozza-' + (form.querySelector('[name=id]').value !== '0' ? form.querySelector('[name=id]').value : 'nuovo'), timerBozza = null;
  function statoAttuale() { return {nome: nome.value, stato: form.querySelector('#fl-stato').value, tag: form.querySelector('#fl-tag').value, inneschi: inneschi, grafo: g}; }
  function bozzaScrivi(inviata) { try { var b = statoAttuale(); b.quando = Date.now(); b.inviata = !!inviata; localStorage.setItem(chiaveBozza, JSON.stringify(b)); } catch (e) {} }
  function bozzaTogli() { try { localStorage.removeItem(chiaveBozza); if (chiaveBozza !== 'fl-bozza-nuovo' && D.appenaSalvato) localStorage.removeItem('fl-bozza-nuovo'); } catch (e) {} }
  function bozzaPiuTardi() { if (!pronto) return; clearTimeout(timerBozza); timerBozza = setTimeout(function () { bozzaScrivi(false); }, 800); }
  function bozzaLeggi() { try { return JSON.parse(localStorage.getItem(chiaveBozza) || 'null'); } catch (e) { return null; } }
  function stessoStato(b) { return JSON.stringify([b.nome, b.stato, b.tag, b.inneschi, b.grafo]) === JSON.stringify([nome.value, form.querySelector('#fl-stato').value, form.querySelector('#fl-tag').value, inneschi, g]); }
  function bozzaApplica(b) {
    g.nodi = b.grafo.nodi; g.archi = b.grafo.archi; inneschi = b.inneschi || inneschi;
    nome.value = b.nome; if (b.stato) form.querySelector('#fl-stato').value = b.stato; form.querySelector('#fl-tag').value = b.tag || '';
    scegli(null); disegnaInneschi();
  }
  function bozzaControlla() {
    var b = bozzaLeggi();
    if (!b || !b.grafo || !b.grafo.nodi) return;
    // Salvataggio riuscito (la pagina mostra proprio quello che era stato mandato): la copia non serve più.
    if (stessoStato(b) || (D.appenaSalvato && b.inviata)) { bozzaTogli(); return; }
    var q = new Date(b.quando || Date.now()), ora = ('0' + q.getHours()).slice(-2) + ':' + ('0' + q.getMinutes()).slice(-2);
    var av = document.createElement('div'); av.className = 'fl-avviso'; av.setAttribute('role', 'status');
    av.innerHTML = '<span>C\'è una versione di questa automazione non salvata (lavoro delle ' + ora + (D.errore ? ', quello appena mandato' : '') + '). Vuoi riprenderla?</span><button type="button" class="btn btn-primary btn-sm" data-si>Riprendi la versione non salvata</button><button type="button" class="btn btn-secondary btn-sm" data-no>Scarta</button>';
    form.insertBefore(av, form.querySelector('.fl-corpo'));
    av.querySelector('[data-si]').addEventListener('click', function () { bozzaApplica(b); av.remove(); bozzaScrivi(false); });
    av.querySelector('[data-no]').addEventListener('click', function () { bozzaTogli(); av.remove(); });
  }
  var disegnaOrig = disegna; disegna = function () { disegnaOrig(); bozzaPiuTardi(); };
  // Dopo un salvataggio riuscito la copia della "nuova" automazione non serve più.
  if (D.appenaSalvato) { try { localStorage.removeItem('fl-bozza-nuovo'); } catch (e) {} }

  disegnaInneschi();
  disegnaOrig();
  bozzaControlla();
  pronto = true;
  return {ordina: ordina, dati: dati, aggiungi: aggiungi, scegli: scegli, disegna: function () { disegna(); }};
})();
