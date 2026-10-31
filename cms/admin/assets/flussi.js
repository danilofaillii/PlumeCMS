/*
 * La tela dei flussi automatici. Blocchi (nodi) trascinabili, uscite con un pallino, archi disegnati in SVG.
 * Il grafo vive in FL.g = {nodi:[{id,tipo,x,y,dati}], archi:[{da,uscita,a}]}; al salvataggio finisce in #fl-grafo.
 * Senza librerie: pointer events e un po' di geometria.
 */
var FL = (function () {
  var g = FL_DATI.grafo, tela = document.getElementById('fl-tela'), box = document.getElementById('fl-tela-box'), svg = document.getElementById('fl-archi');
  var pannello = document.getElementById('fl-pannello'), scelto = null, arcoScelto = null, quill = null, ns = 'http://www.w3.org/2000/svg';
  var ICONE = {innesco: '\uD83D\uDE80', email: '✉️', attesa: '⏳', bivio: '\uD83D\uDD00', fine: '⏹'};
  var NOMI = {innesco: 'Parte quando', email: 'Email', attesa: 'Attesa', bivio: 'Bivio', fine: 'Fine'};
  var INNESCHI = {}; document.querySelectorAll('#fl-innesco option').forEach(function (o) { INNESCHI[o.value] = o.textContent; });

  function h(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]; }); }
  function nodo(id) { return g.nodi.find(function (n) { return n.id === id; }); }
  function nuovoId() { return g.nodi.reduce(function (m, n) { return Math.max(m, n.id); }, 0) + 1; }
  function arrivo(da, u) { var a = g.archi.find(function (x) { return x.da === da && x.uscita === u; }); return a ? a.a : null; }
  function tempo(min) {
    if (!min) return 'subito';
    if (min % 1440 === 0) return (min / 1440) + (min === 1440 ? ' giorno' : ' giorni');
    if (min % 60 === 0) return (min / 60) + (min === 60 ? ' ora' : ' ore');
    return min + ' min';
  }
  function dati() { return {grafo: g}; }

  // ---------- Riassunto di ogni blocco sulla tela ----------
  function riassunto(n) {
    var d = n.dati || {};
    switch (n.tipo) {
      case 'innesco': return h(INNESCHI[document.getElementById('fl-innesco').value] || '') + '<small>' + h(innescoCosa()) + '</small>';
      case 'email': return (d.oggetto ? h(d.oggetto) : '<i style="color:var(--errore-testo)">Senza oggetto</i>') + '<small>' + (d.ascolto > 0 ? 'aspetta una reazione per ' + tempo(d.ascolto) : 'prosegue subito dopo l\'invio') + '</small>';
      case 'attesa': return 'Aspetta ' + h(tempo(d.minuti || 1440));
      case 'bivio': var c = FL_DATI.condizioni[d.condizione] || ['?', '']; return h(c[0]) + '<small>' + h(valoreBivio(d)) + '</small>';
      case 'fine': return 'Il cammino finisce qui.';
    }
    return '';
  }
  function valoreBivio(d) {
    var c = FL_DATI.condizioni[d.condizione] || ['', ''];
    if (c[1] === 'lista') return FL_DATI.liste[d.valore] ? 'lista: ' + FL_DATI.liste[d.valore] : 'scegli la lista';
    if (c[1] === 'email') { var e = nodo(d.valore); return e && e.tipo === 'email' ? 'email: ' + (e.dati.oggetto || 'Email ' + e.id) : 'scegli l\'email'; }
    if (c[1] === 'giorni') return (d.valore || 0) + ' giorni';
    return '';
  }
  function innescoCosa() {
    var k = document.getElementById('fl-innesco').value;
    if (k === 'inattivo') return (document.getElementById('fl-cosa-n').value || '?') + ' giorni senza aperture né clic';
    var sel = document.getElementById('fl-cosa'); return sel.style.display === 'none' ? '' : (sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].textContent : '');
  }

  // ---------- Disegno ----------
  function disegna() {
    tela.querySelectorAll('.fl-nodo').forEach(function (el) { if (!nodo(+el.dataset.id)) el.remove(); });
    g.nodi.forEach(function (n) {
      var el = tela.querySelector('.fl-nodo[data-id="' + n.id + '"]');
      if (!el) { el = document.createElement('div'); el.className = 'fl-nodo'; el.dataset.id = n.id; el.dataset.tipo = n.tipo; tela.appendChild(el); }
      el.style.left = n.x + 'px'; el.style.top = n.y + 'px';
      el.classList.toggle('fl-scelto', scelto === n.id);
      var uscite = FL_DATI.uscite[n.tipo] || {}, html = '<div class="fl-nodo-testa"><span>' + ICONE[n.tipo] + '</span> ' + NOMI[n.tipo] + (n.tipo !== 'innesco' && n.tipo !== 'fine' ? '<span style="margin-left:auto;opacity:.7;font-weight:400">#' + n.id + '</span>' : '') + '</div>';
      html += '<div class="fl-nodo-corpo">' + riassunto(n) + '</div>';
      if (FL_DATI.inCorso[n.id] || FL_DATI.aperture[n.id]) html += '<div class="fl-nodo-numeri">' + (FL_DATI.inCorso[n.id] ? '<span>\uD83D\uDC65 ' + FL_DATI.inCorso[n.id] + ' qui adesso</span>' : '') + (FL_DATI.aperture[n.id] ? '<span>\uD83D\uDC41 ' + FL_DATI.aperture[n.id] + ' aperture</span>' : '') + '</div>';
      var keys = Object.keys(uscite);
      if (keys.length) {
        html += '<div class="fl-uscite">';
        keys.forEach(function (u) { html += '<span class="fl-uscita"><span class="fl-porta' + (arrivo(n.id, u) !== null ? ' fl-collegata' : '') + '" data-nodo="' + n.id + '" data-uscita="' + u + '" title="Tira da qui fino a un blocco"></span>' + h(uscite[u]) + '</span>'; });
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
        var m = {x: (a.x + b.x) / 2, y: (a.y + b.y) / 2};
        var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'fl-cestina-arco'; btn.textContent = '✕'; btn.title = 'Togli il collegamento';
        btn.style.left = m.x + 'px'; btn.style.top = m.y + 'px'; btn.onclick = function (e) { e.stopPropagation(); g.archi.splice(i, 1); arcoScelto = null; disegna(); };
        tela.appendChild(btn);
      }
    });
  }
  svg.addEventListener('pointerdown', function (e) { var t = e.target.closest('path'); if (!t) return; arcoScelto = +t.dataset.i; scelto = null; pannelloVuoto(); disegnaArchi(); tela.querySelectorAll('.fl-nodo').forEach(function (el) { el.classList.remove('fl-scelto'); }); e.stopPropagation(); });
  document.addEventListener('keydown', function (e) { if ((e.key === 'Delete' || e.key === 'Backspace') && arcoScelto !== null && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && !document.activeElement.closest('.ql-editor')) { g.archi.splice(arcoScelto, 1); arcoScelto = null; disegna(); e.preventDefault(); } });

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
      if (b && +b.dataset.id !== drag.da && +b.dataset.id !== 0) {
        g.archi = g.archi.filter(function (x) { return !(x.da === drag.da && x.uscita === drag.uscita); });
        g.archi.push({da: drag.da, uscita: drag.uscita, a: +b.dataset.id});
      }
      tela.querySelectorAll('.fl-nodo').forEach(function (el) { el.classList.remove('fl-bersaglio'); });
      disegna();
    }
    drag = null;
  });
  tela.addEventListener('pointercancel', function () { if (drag && drag.path) drag.path.remove(); drag = null; });

  // ---------- Tavolozza: trascina sulla tela o clicca ----------
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
      } else aggiungi(pezzo.tipo, box.scrollLeft + box.clientWidth / 2 - 115 + (g.nodi.length % 5) * 15, box.scrollTop + box.clientHeight / 2 - 60 + (g.nodi.length % 5) * 15);
      pezzo = null;
    });
  });
  function aggiungi(tipo, x, y) {
    var d = {email: {oggetto: '', anteprima: '', corpo: '', pulsante_testo: '', pulsante_link: '', ascolto: 0}, attesa: {minuti: 1440}, bivio: {condizione: 'in_lista', valore: 0}, fine: {}}[tipo];
    var n = {id: nuovoId(), tipo: tipo, x: Math.round(x), y: Math.round(y), dati: d};
    g.nodi.push(n); disegna(); scegli(n.id);
  }
  function elimina(id) {
    if (id === 0) return;
    if (!confirm('Togliere questo blocco dalla tela, con i suoi collegamenti?')) return;
    g.nodi = g.nodi.filter(function (n) { return n.id !== id; });
    g.archi = g.archi.filter(function (a) { return a.da !== id && a.a !== id; });
    scegli(null); disegna();
  }

  // ---------- Pannello del blocco scelto ----------
  function pannelloVuoto() { if (quill) { quill = null; } pannello.innerHTML = '<div class="fl-pannello-vuoto">Clicca un blocco per modificarlo.<br><br>Per cancellare un collegamento, clicca la linea e premi Canc.</div>'; }
  function scegli(id) {
    scelto = id; arcoScelto = null;
    tela.querySelectorAll('.fl-nodo').forEach(function (el) { el.classList.toggle('fl-scelto', +el.dataset.id === id); });
    disegnaArchi();
    if (id === null) { pannelloVuoto(); return; }
    var n = nodo(id), d = n.dati, html = '<h3>' + ICONE[n.tipo] + ' ' + NOMI[n.tipo] + (id ? ' <button type="button" class="fl-elimina" data-elimina="' + id + '">Togli il blocco</button>' : '') + '</h3>';
    if (n.tipo === 'innesco') html += '<p class="hint">Il punto di partenza. Si sceglie nella colonna a sinistra, sotto "Parte quando". Tira dal pallino "Poi" al primo blocco.</p>';
    if (n.tipo === 'fine') html += '<p class="hint">Chi arriva qui ha finito il flusso. Serve a chiudere un ramo in modo esplicito; un\'uscita lasciata libera fa lo stesso.</p>';
    if (n.tipo === 'attesa') html += '<label>Aspetta</label><div class="fl-tempo"><input type="number" min="1" id="fp-n" value="' + unita(d.minuti).n + '"><select id="fp-u">' + opzUnita(unita(d.minuti).u) + '</select></div><p class="hint">Il cammino riparte passata l\'attesa, al primo giro del cron (ogni cinque minuti).</p>';
    if (n.tipo === 'bivio') {
      html += '<label>Condizione</label><select id="fp-cond">' + Object.keys(FL_DATI.condizioni).map(function (k) { return '<option value="' + k + '"' + (d.condizione === k ? ' selected' : '') + '>' + h(FL_DATI.condizioni[k][0]) + '</option>'; }).join('') + '</select><div id="fp-valore"></div><p class="hint">Sì e No sono le due uscite del blocco: collegale a quello che deve succedere in ciascun caso.</p>';
    }
    if (n.tipo === 'email') {
      html += '<label>Oggetto</label><input type="text" id="fp-oggetto" value="' + h(d.oggetto) + '" maxlength="255" placeholder="{nome} diventa il nome della persona">';
      html += '<label>Testo di anteprima</label><input type="text" id="fp-anteprima" value="' + h(d.anteprima) + '" maxlength="255">';
      html += '<label>Testo</label><div id="fp-corpo" class="fl-editor"></div>';
      html += '<label>Pulsante (facoltativo)</label><input type="text" id="fp-pt" value="' + h(d.pulsante_testo) + '" placeholder="Testo del pulsante" maxlength="100"><input type="text" id="fp-pl" value="' + h(d.pulsante_link) + '" placeholder="https://..." inputmode="url">';
      html += '<label>Aspetta una reazione per</label><div class="fl-tempo"><input type="number" min="0" id="fp-n" value="' + (d.ascolto ? unita(d.ascolto).n : 0) + '"><select id="fp-u">' + opzUnita(d.ascolto ? unita(d.ascolto).u : 1440) + '</select></div>';
      html += '<p class="hint">Con 0 l\'email prosegue subito dall\'uscita "Subito dopo". Con un tempo, resta in ascolto: appena la persona apre o clicca parte da "Se apre" o "Se clicca"; passato il tempo senza niente, da "Se non fa niente" (o da "Subito dopo" se quella è libera).</p>';
    }
    pannello.innerHTML = html;
    var b = pannello.querySelector('[data-elimina]'); if (b) b.onclick = function () { elimina(+b.dataset.elimina); };
    if (n.tipo === 'attesa' || n.tipo === 'email') {
      var leggiTempo = function () { var v = Math.max(0, parseInt(pannello.querySelector('#fp-n').value || '0', 10)) * parseInt(pannello.querySelector('#fp-u').value, 10); if (n.tipo === 'attesa') d.minuti = Math.max(1, v); else d.ascolto = v; disegna(); };
      pannello.querySelector('#fp-n').addEventListener('input', leggiTempo); pannello.querySelector('#fp-u').addEventListener('change', leggiTempo);
    }
    if (n.tipo === 'bivio') {
      var cond = pannello.querySelector('#fp-cond');
      var valore = function () {
        var tipo = FL_DATI.condizioni[d.condizione][1], box = pannello.querySelector('#fp-valore'), s = '';
        if (tipo === 'lista') s = '<label>Quale lista</label><select id="fp-val">' + Object.keys(FL_DATI.liste).map(function (k) { return '<option value="' + k + '"' + (+k === +d.valore ? ' selected' : '') + '>' + h(FL_DATI.liste[k]) + '</option>'; }).join('') + '</select>';
        if (tipo === 'email') s = '<label>Quale email di questo flusso</label><select id="fp-val">' + g.nodi.filter(function (x) { return x.tipo === 'email'; }).map(function (x) { return '<option value="' + x.id + '"' + (x.id === +d.valore ? ' selected' : '') + '>#' + x.id + ' ' + h(x.dati.oggetto || 'Email ' + x.id) + '</option>'; }).join('') + '</select>';
        if (tipo === 'giorni') s = '<label>Giorni</label><input type="number" id="fp-val" min="1" max="3650" value="' + (d.valore || 30) + '">';
        box.innerHTML = s;
        var v = box.querySelector('#fp-val');
        if (v) { if (!d.valore || (tipo !== 'giorni' && ![].slice.call(v.options).some(function (o) { return +o.value === +d.valore; }))) d.valore = +v.value || 0; v.addEventListener('input', function () { d.valore = +v.value || 0; disegna(); }); v.addEventListener('change', function () { d.valore = +v.value || 0; disegna(); }); }
        disegna();
      };
      cond.addEventListener('change', function () { d.condizione = cond.value; d.valore = 0; valore(); });
      valore();
    }
    if (n.tipo === 'email') {
      ['oggetto', 'anteprima'].forEach(function (k) { pannello.querySelector('#fp-' + k).addEventListener('input', function (e) { d[k] = e.target.value; disegna(); }); });
      pannello.querySelector('#fp-pt').addEventListener('input', function (e) { d.pulsante_testo = e.target.value; });
      pannello.querySelector('#fp-pl').addEventListener('input', function (e) { d.pulsante_link = e.target.value; });
      quill = new Quill('#fp-corpo', {theme: 'snow', modules: {toolbar: [[{header: [2, 3, false]}], ['bold', 'italic', 'underline'], ['link', 'blockquote'], [{list: 'ordered'}, {list: 'bullet'}], ['clean']]}});
      quill.clipboard.dangerouslyPasteHTML(d.corpo || '');
      quill.on('text-change', function () { d.corpo = quill.root.innerHTML === '<p><br></p>' ? '' : quill.root.innerHTML; bozzaPiuTardi(); });
    }
  }
  function unita(min) { if (min % 1440 === 0) return {n: min / 1440, u: 1440}; if (min % 60 === 0) return {n: min / 60, u: 60}; return {n: min, u: 1}; }
  function opzUnita(u) { return [[1, 'minuti'], [60, 'ore'], [1440, 'giorni']].map(function (x) { return '<option value="' + x[0] + '"' + (x[0] === u ? ' selected' : '') + '>' + x[1] + '</option>'; }).join(''); }

  // ---------- Innesco ----------
  var selInnesco = document.getElementById('fl-innesco'), selCosa = document.getElementById('fl-cosa'), nCosa = document.getElementById('fl-cosa-n'), lblCosa = document.getElementById('fl-cosa-label');
  function innesco() {
    var k = selInnesco.value, cosa = FL_DATI.cosa[k] || {}, keys = Object.keys(cosa);
    nCosa.style.display = k === 'inattivo' ? '' : 'none'; nCosa.disabled = k !== 'inattivo';
    selCosa.style.display = (k === 'inattivo' || !keys.length) ? 'none' : ''; selCosa.disabled = k === 'inattivo' || !keys.length;
    lblCosa.style.display = (k === 'inattivo' || keys.length) ? '' : 'none';
    lblCosa.textContent = k === 'inattivo' ? 'Dopo quanti giorni' : 'Quale';
    if (k !== 'inattivo') { selCosa.innerHTML = (k === 'lista' || k === 'form' ? '<option value="0">Qualunque</option>' : '') + keys.map(function (i) { return '<option value="' + i + '"' + (+i === FL_DATI.innescoId ? ' selected' : '') + '>' + h(cosa[i]) + '</option>'; }).join(''); }
    else if (!nCosa.value) nCosa.value = FL_DATI.innescoId || 30;
    disegna();
  }
  selInnesco.addEventListener('change', function () { FL_DATI.innescoId = 0; innesco(); });
  selCosa.addEventListener('change', disegna); nCosa.addEventListener('input', disegna);
  innesco();

  // ---------- Riordino automatico: colonne per profondità dall'innesco ----------
  function ordina() {
    var prof = {0: 0}, coda = [0], ordine = [0];
    while (coda.length) { var id = coda.shift(); g.archi.filter(function (a) { return a.da === id; }).forEach(function (a) { if (prof[a.a] === undefined) { prof[a.a] = prof[id] + 1; coda.push(a.a); ordine.push(a.a); } }); }
    var max = Math.max.apply(null, Object.values(prof).concat([0]));
    g.nodi.forEach(function (n) { if (prof[n.id] === undefined) { prof[n.id] = ++max; ordine.push(n.id); } });
    var righe = {};
    ordine.forEach(function (id) { var n = nodo(id), c = prof[id]; righe[c] = (righe[c] || 0); n.x = 40 + c * 300; n.y = 40 + righe[c] * 220; righe[c]++; });
    disegna();
  }

  // ---------- Salvataggio ----------
  var form = document.getElementById('fl-form'), nome = form.querySelector('.fl-nome');
  // Il grafo va nel campo nascosto in JSON; a proteggerlo dal firewall del server pensa campi-protetti.js (base64 con ~b64~).
  function grafoPerIlForm() { return JSON.stringify(g); }
  form.addEventListener('submit', function (e) {
    if (e.submitter && e.submitter.value === 'elimina') { bozzaTogli(); return; }
    if (selInnesco.value === 'inattivo') { selCosa.disabled = true; nCosa.name = 'innesco_id'; } else { nCosa.removeAttribute('name'); }
    document.getElementById('fl-grafo').value = grafoPerIlForm();
    bozzaScrivi(true);
  });
  // Il nome è obbligatorio: il messaggio del browser dice perché e porta il campo in vista, anche se si era scesi sulla tela.
  nome.addEventListener('invalid', function () { nome.setCustomValidity('Dai un nome al flusso prima di salvarlo.'); nome.scrollIntoView({block: 'center'}); });
  nome.addEventListener('input', function () { nome.setCustomValidity(''); bozzaPiuTardi(); });
  document.querySelector('[name=attivo]').addEventListener('change', bozzaPiuTardi);

  // ---------- Sessione viva e copia di sicurezza ----------
  // Ogni cinque minuti un battito al server: la sessione del pannello non scade mentre si disegna.
  setInterval(function () { try { fetch('newsletter-flussi.php?ping=1', {credentials: 'same-origin', cache: 'no-store'}); } catch (e) {} }, 5 * 60 * 1000);
  // Una copia del lavoro nel browser, aggiornata a ogni modifica: se il salvataggio va male (sessione scaduta,
  // errore del server, rete), alla riapertura si può riprendere da dove si era.
  var chiaveBozza = 'fl-bozza-' + (FL_DATI.grafo && form.querySelector('[name=id]').value !== '0' ? form.querySelector('[name=id]').value : 'nuovo'), timerBozza = null;
  function statoAttuale() { return {nome: nome.value, attivo: form.querySelector('[name=attivo]').checked ? 1 : 0, innesco: selInnesco.value, innesco_id: selInnesco.value === 'inattivo' ? (nCosa.value || '0') : (selCosa.disabled ? '0' : selCosa.value), grafo: g}; }
  function bozzaScrivi(inviata) { try { var b = statoAttuale(); b.quando = Date.now(); b.inviata = !!inviata; localStorage.setItem(chiaveBozza, JSON.stringify(b)); } catch (e) {} }
  function bozzaTogli() { try { localStorage.removeItem(chiaveBozza); if (chiaveBozza !== 'fl-bozza-nuovo' && FL_DATI.appenaSalvato) localStorage.removeItem('fl-bozza-nuovo'); } catch (e) {} }
  function bozzaPiuTardi() { clearTimeout(timerBozza); timerBozza = setTimeout(function () { bozzaScrivi(false); }, 800); }
  function bozzaLeggi() { try { return JSON.parse(localStorage.getItem(chiaveBozza) || 'null'); } catch (e) { return null; } }
  function stessoStato(b) { var a = statoAttuale(); return b.nome === a.nome && +b.attivo === +a.attivo && b.innesco === a.innesco && String(b.innesco_id) === String(a.innesco_id) && JSON.stringify(b.grafo) === JSON.stringify(a.grafo); }
  function bozzaApplica(b) {
    g.nodi = b.grafo.nodi; g.archi = b.grafo.archi;
    nome.value = b.nome; form.querySelector('[name=attivo]').checked = !!(+b.attivo);
    selInnesco.value = b.innesco; FL_DATI.innescoId = +b.innesco_id || 0; innesco();
    if (b.innesco === 'inattivo') nCosa.value = b.innesco_id;
    scegli(null); disegna();
  }
  function bozzaControlla() {
    var b = bozzaLeggi();
    if (!b || !b.grafo || !b.grafo.nodi) return;
    // Salvataggio riuscito (la pagina mostra proprio quello che era stato mandato): la copia non serve più.
    if (FL_DATI.appenaSalvato && (stessoStato(b) || b.inviata)) { bozzaTogli(); return; }
    if (stessoStato(b)) { bozzaTogli(); return; }
    var q = new Date(b.quando || Date.now()), ora = ('0' + q.getHours()).slice(-2) + ':' + ('0' + q.getMinutes()).slice(-2);
    var avviso = document.createElement('div'); avviso.className = 'fl-avviso';
    avviso.innerHTML = '<span>C\'è una versione di questo flusso non salvata (lavoro delle ' + ora + (FL_DATI.errore ? ', quello appena mandato' : '') + '). Vuoi riprenderla?</span><button type="button" class="btn btn-primary btn-sm" data-si>Riprendi</button><button type="button" class="btn btn-secondary btn-sm" data-no>Scarta</button>';
    form.insertBefore(avviso, form.firstChild.nextSibling);
    avviso.querySelector('[data-si]').onclick = function () { bozzaApplica(b); avviso.remove(); bozzaScrivi(false); };
    avviso.querySelector('[data-no]').onclick = function () { bozzaTogli(); avviso.remove(); };
  }
  var disegnaOrig = disegna; disegna = function () { disegnaOrig(); bozzaPiuTardi(); };
  // Dopo un salvataggio riuscito la copia del "nuovo" flusso non serve più.
  if (FL_DATI.appenaSalvato) { try { localStorage.removeItem('fl-bozza-nuovo'); } catch (e) {} }

  disegnaOrig();
  bozzaControlla();
  return {ordina: ordina, dati: dati, aggiungi: aggiungi, scegli: scegli, disegna: function () { disegna(); }};
})();
