/* La tela dell'editor (dentro l'iframe). Parla con il pannello (editor.js) via postMessage. */
(function () {
  'use strict';
  var pagina = document.getElementById('ed-pagina');
  var cssTag = document.getElementById('ed-css');
  var selezionato = null;
  var sortables = [];
  var etichette = {};

  function manda(m) { parent.postMessage(Object.assign({ ed: 'tela' }, m), location.origin); }

  // Niente navigazione: i link e i moduli della pagina restano fermi.
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a');
    if (a && !a.closest('.ed-strumenti')) e.preventDefault();
    if (e.target.closest('form') && e.target.matches('button,[type=submit]')) e.preventDefault();
  }, true);
  document.addEventListener('submit', function (e) { e.preventDefault(); }, true);

  // ---- selezione e strumenti
  var strumenti = document.createElement('div');
  strumenti.className = 'ed-strumenti';
  strumenti.innerHTML = '<span class="ed-strumenti-nome"></span>'
    + '<button type="button" data-az="su" title="Sposta su">↑</button><button type="button" data-az="giu" title="Sposta giù">↓</button>'
    + '<button type="button" data-az="duplica" title="Duplica">⧉</button><button type="button" data-az="elimina" title="Elimina">&#128465;</button>';
  document.body.appendChild(strumenti);
  strumenti.addEventListener('click', function (e) {
    var b = e.target.closest('button');
    if (b && selezionato) manda({ tipo: 'azione', azione: b.dataset.az, id: selezionato });
  });

  function elemento(id) { return pagina.querySelector('[data-ed-id="' + id + '"]'); }

  function seleziona(id, dalPannello) {
    pagina.querySelectorAll('.ed-selezionato').forEach(function (x) { x.classList.remove('ed-selezionato'); });
    selezionato = id;
    var el = id ? elemento(id) : null;
    if (!el) { strumenti.hidden = true; return; }
    el.classList.add('ed-selezionato');
    strumenti.hidden = false;
    strumenti.querySelector('.ed-strumenti-nome').textContent = etichette[el.dataset.edTipo] || el.dataset.edTipo;
    strumenti.className = 'ed-strumenti ed-strumenti-' + (el.dataset.edTipo === 'sezione' ? 'sezione' : el.dataset.edTipo === 'colonna' ? 'colonna' : 'widget');
    posizionaStrumenti(el);
    if (dalPannello) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    else manda({ tipo: 'seleziona', id: id });
  }

  function posizionaStrumenti(el) {
    var r = el.getBoundingClientRect();
    var top = r.top + window.scrollY - 30;
    if (top < window.scrollY + 76) top = r.top + window.scrollY + 4; // l'intestazione del sito è fissa in alto
    strumenti.style.top = top + 'px';
    strumenti.style.left = Math.max(4, r.left + window.scrollX) + 'px';
  }
  window.addEventListener('scroll', function () { if (selezionato && elemento(selezionato)) posizionaStrumenti(elemento(selezionato)); }, { passive: true });
  window.addEventListener('resize', function () { if (selezionato && elemento(selezionato)) posizionaStrumenti(elemento(selezionato)); });

  pagina.addEventListener('click', function (e) {
    if (e.target.closest('.ed-aggiungi')) {
      var agg = e.target.closest('.ed-aggiungi');
      manda({ tipo: 'aggiungi-sezione', dopo: agg.dataset.dopo || '' });
      return;
    }
    var el = e.target.closest('[data-ed-id]');
    if (!el) return;
    e.stopPropagation();
    seleziona(el.dataset.edId, false);
  });
  pagina.addEventListener('dblclick', function (e) {
    var el = e.target.closest('[data-ed-id]');
    if (el) manda({ tipo: 'modifica', id: el.dataset.edId });
  });

  // Passaggio del mouse: contorno leggero con l'etichetta.
  var sopra = null;
  pagina.addEventListener('mouseover', function (e) {
    var el = e.target.closest('[data-ed-id]');
    if (el === sopra) return;
    if (sopra) sopra.classList.remove('ed-sopra');
    sopra = el;
    if (el) el.classList.add('ed-sopra');
  });
  pagina.addEventListener('mouseleave', function () { if (sopra) { sopra.classList.remove('ed-sopra'); sopra = null; } });

  // ---- trascinamento dal pannello (HTML5, attraverso l'iframe)
  var bersaglio = null;
  function puliBersaglio() { if (bersaglio) { bersaglio.classList.remove('ed-bersaglio'); bersaglio = null; } }
  // Una sezione pronta (1.3.0) si posa fra due sezioni: prima o dopo quella sotto il puntatore, con una linea che lo mostra.
  var lineaSezione = document.createElement('div');
  lineaSezione.className = 'ed-linea-sezione'; lineaSezione.hidden = true;
  document.body.appendChild(lineaSezione);
  function puntoSezione(e) {
    var sezioni = Array.prototype.slice.call(pagina.querySelectorAll(':scope > .ed-sezione'));
    var s = e.target.closest && e.target.closest('.ed-sezione');
    if (!s || sezioni.indexOf(s) < 0) return { indice: sezioni.length, y: sezioni.length ? sezioni[sezioni.length - 1].getBoundingClientRect().bottom : 0 };
    var r = s.getBoundingClientRect(), dopo = e.clientY > r.top + r.height / 2;
    return { indice: sezioni.indexOf(s) + (dopo ? 1 : 0), y: dopo ? r.bottom : r.top };
  }
  function haTipo(e, t) { var tipi = e.dataTransfer && e.dataTransfer.types; return !!tipi && Array.prototype.indexOf.call(tipi, t) >= 0; }
  pagina.addEventListener('dragover', function (e) {
    if (!haTipo(e, 'text/ed-sezione')) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'copy';
    var p = puntoSezione(e);
    lineaSezione.style.top = (p.y + window.scrollY - 2) + 'px';
    lineaSezione.hidden = false;
  });
  pagina.addEventListener('drop', function (e) {
    var id = e.dataTransfer && e.dataTransfer.getData('text/ed-sezione');
    if (!id) return;
    e.preventDefault();
    lineaSezione.hidden = true;
    manda({ tipo: 'aggiungi-pronta', pronta: id, indice: puntoSezione(e).indice });
  });
  document.addEventListener('dragleave', function (e) { if (!e.relatedTarget) lineaSezione.hidden = true; });
  document.addEventListener('dragend', function () { lineaSezione.hidden = true; });
  pagina.addEventListener('dragover', function (e) {
    var tipi = e.dataTransfer && e.dataTransfer.types;
    if (!tipi || Array.prototype.indexOf.call(tipi, 'text/ed-widget') < 0) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'copy';
    var col = e.target.closest('.ed-colonna');
    if (col !== bersaglio) { puliBersaglio(); bersaglio = col; if (col) col.classList.add('ed-bersaglio'); }
  });
  pagina.addEventListener('dragleave', function (e) { if (!pagina.contains(e.relatedTarget)) puliBersaglio(); });
  pagina.addEventListener('drop', function (e) {
    var tipo = e.dataTransfer && e.dataTransfer.getData('text/ed-widget');
    if (!tipo) return;
    e.preventDefault();
    var col = e.target.closest('.ed-colonna');
    var indice = null;
    if (col) {
      // Prima o dopo il widget sotto il puntatore
      var w = e.target.closest('.ed-widget');
      var figli = Array.prototype.slice.call(col.children).filter(function (x) { return x.classList.contains('ed-widget'); });
      if (w && figli.indexOf(w) >= 0) {
        var r = w.getBoundingClientRect();
        indice = figli.indexOf(w) + (e.clientY > r.top + r.height / 2 ? 1 : 0);
      } else indice = figli.length;
    }
    puliBersaglio();
    manda({ tipo: 'aggiungi-widget', widget: tipo, colonna: col ? col.dataset.edId : '', indice: indice });
  });

  // ---- trascinamento dentro la pagina (Sortable): sezioni nella pagina, colonne nella riga, widget tra colonne
  function attivaSortable() {
    sortables.forEach(function (s) { s.destroy(); });
    sortables = [];
    if (typeof Sortable === 'undefined') return;
    var comuni = { animation: 150, ghostClass: 'ed-fantasma', forceFallback: true, fallbackOnBody: true, fallbackTolerance: 6, swapThreshold: 0.6, emptyInsertThreshold: 24 };
    sortables.push(new Sortable(pagina, Object.assign({}, comuni, { draggable: '.ed-sezione', handle: '.ed-maniglia-sezione', onEnd: fine })));
    pagina.querySelectorAll('.ed-riga').forEach(function (r) {
      sortables.push(new Sortable(r, Object.assign({}, comuni, { draggable: '.ed-colonna', handle: '.ed-maniglia-colonna', onEnd: fine })));
    });
    pagina.querySelectorAll('.ed-colonna').forEach(function (c) {
      sortables.push(new Sortable(c, Object.assign({}, comuni, { group: 'widget', draggable: '.ed-widget', filter: '.ed-colonna-vuota', onEnd: fine })));
    });
  }
  function fine(ev) {
    var el = ev.item;
    var genitore = el.parentElement.closest('[data-ed-id]');
    var fratelli = Array.prototype.slice.call(el.parentElement.children).filter(function (x) { return x.hasAttribute('data-ed-id'); });
    manda({ tipo: 'sposta', id: el.dataset.edId, genitore: genitore ? genitore.dataset.edId : '', indice: fratelli.indexOf(el) });
  }

  // Maniglie per le colonne e i pulsanti "aggiungi sezione" tra le sezioni.
  function arreda() {
    pagina.querySelectorAll('.ed-colonna').forEach(function (c) {
      var m = document.createElement('span');
      m.className = 'ed-maniglia-colonna';
      m.title = 'Trascina la colonna';
      m.textContent = '⋮⋮';
      c.insertBefore(m, c.firstChild);
    });
    var sezioni = pagina.querySelectorAll('.ed-sezione');
    sezioni.forEach(function (s) {
      var m = document.createElement('span');
      m.className = 'ed-maniglia-sezione';
      m.title = 'Trascina la sezione';
      m.textContent = '⋮⋮ Sezione';
      s.insertBefore(m, s.firstChild);
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'ed-aggiungi';
      b.dataset.dopo = s.dataset.edId;
      b.title = 'Aggiungi una sezione qui';
      b.textContent = '+';
      s.appendChild(b);
    });
    if (!sezioni.length) {
      pagina.innerHTML = '<div class="ed-pagina-vuota"><p>La pagina è vuota.</p><button type="button" class="ed-aggiungi ed-aggiungi-grande" data-dopo="">+ Aggiungi la prima sezione</button></div>';
    }
  }

  window.addEventListener('message', function (e) {
    if (e.origin !== location.origin || !e.data || e.data.ed !== 'pannello') return;
    var m = e.data;
    if (m.tipo === 'disegna') {
      var y = window.scrollY;
      pagina.innerHTML = m.html;
      cssTag.textContent = m.css;
      etichette = m.etichette || etichette;
      arreda();
      attivaSortable();
      window.scrollTo(0, y);
      seleziona(m.selezionato || selezionato, true);
      // Gli script dei widget (schede, fisarmonica, contatori) si riagganciano a ogni disegno.
      document.dispatchEvent(new CustomEvent('ed:disegnato', { detail: { radice: pagina } }));
      if (window.edWidget && typeof window.edWidget.avvia === 'function') window.edWidget.avvia(pagina);
    } else if (m.tipo === 'seleziona') {
      seleziona(m.id, true);
    } else if (m.tipo === 'etichette') {
      etichette = m.etichette;
    }
  });
  manda({ tipo: 'pronta' });
})();
