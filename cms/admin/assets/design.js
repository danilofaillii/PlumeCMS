/*
 * Stile del sito (admin/design.php): schede, controlli colore con i globali, riassunti della tipografia,
 * elenchi di colori e caratteri personalizzati, anteprima dal vivo (il CSS ricalcolato dal server a ogni modifica
 * e iniettato nell'iframe del sito), libreria delle immagini.
 */
(function () {
  var form = document.getElementById('dg-form');
  if (!form) return;
  var dati = JSON.parse(document.getElementById('dg-dati').textContent);
  var iframe = document.getElementById('dg-iframe');
  var stato = document.getElementById('dg-anteprima-stato');
  var modificato = false, ultimoCss = null, ultimoLink = '', timer = null, richiesta = 0;

  function q(sel, radice) { return (radice || document).querySelector(sel); }
  function qa(sel, radice) { return Array.prototype.slice.call((radice || document).querySelectorAll(sel)); }

  // ---------- schede ----------
  qa('.dg-schede button').forEach(function (b) {
    b.addEventListener('click', function () {
      qa('.dg-schede button').forEach(function (x) { x.classList.toggle('attiva', x === b); });
      qa('.dg-sezione').forEach(function (s) { s.hidden = s.dataset.scheda !== b.dataset.scheda; });
      q('#dg-scheda').value = b.dataset.scheda;
      try { history.replaceState(null, '', 'design.php?scheda=' + b.dataset.scheda); } catch (e) {}
    });
  });

  // ---------- colori ----------
  /** Il valore vero di un colore scritto nel modulo (anche globale:xxx), leggendo i campi del modulo stesso. */
  function colori() {
    var m = {};
    qa('input[name^="v[colori][sistema]"]').forEach(function (i) { m[i.name.replace(/.*\[(\w+)\]$/, '$1')] = i.value.trim(); });
    qa('#dg-colori-personalizzati .dg-voce').forEach(function (r) {
      var id = q('input[type=hidden]', r).value || slug(q('.dg-voce-nome', r).value);
      var val = q('.dg-colore-testo', r).value.trim();
      if (id && val) m[id] = val;
    });
    return m;
  }
  function slug(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);
  }
  function risolvi(v, mappa) {
    v = (v || '').trim();
    if (/^globale:/.test(v)) return (mappa || colori())[v.slice(8)] || '';
    return v;
  }
  function hex6(v) { return /^#[0-9a-f]{6}$/i.test(v) ? v : (/^#[0-9a-f]{3}$/i.test(v) ? '#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3] : null); }

  function aggiornaColore(c, mappa) {
    var t = q('.dg-colore-testo', c), p = q('.dg-picker', c), s = q('.dg-swatch', c), g = q('.dg-globali', c);
    var v = t.value.trim(), vero = risolvi(v, mappa);
    s.style.setProperty('--c', vero || 'transparent');
    s.title = vero ? v + (vero !== v ? ' = ' + vero : '') : 'come il sito';
    var h = hex6(vero); if (h) p.value = h;
    if (g) g.value = /^globale:/.test(v) && qa('option', g).some(function (o) { return o.value === v; }) ? v : '';
  }
  function aggiornaTuttiColori() {
    var mappa = colori();
    qa('.dg-colore').forEach(function (c) { aggiornaColore(c, mappa); });
    qa('.dg-swatch-grande[data-globale]').forEach(function (s) { s.style.setProperty('--c', mappa[s.dataset.globale] || 'transparent'); });
  }
  function legaColore(c) {
    var t = q('.dg-colore-testo', c), p = q('.dg-picker', c), g = q('.dg-globali', c), x = q('.dg-x', c);
    p.addEventListener('input', function () { t.value = p.value; cambiato(t); });
    t.addEventListener('input', function () { cambiato(t); });
    if (g) g.addEventListener('change', function () { if (g.value) { t.value = g.value; cambiato(t); } });
    if (x) x.addEventListener('click', function () { t.value = ''; cambiato(t); });
  }

  // ---------- tipografia: il riassunto ----------
  function nomeFamiglia(v) {
    if (!v) return '';
    if (/^globale:font-/.test(v)) { var id = v.slice(13), f = dati.globali.font.filter(function (x) { return x.id === id; })[0]; return f ? f.nome + ' (' + f.famiglia + ')' : id; }
    return v;
  }
  function riassuntoTip(d) {
    var el = function (fine) { var i = q('[name$="' + fine + '"]', d); return i ? i.value.trim() : ''; };
    var parti = [];
    var fam = nomeFamiglia(el('[famiglia]')); if (fam) parti.push(fam);
    var dim = el('[dimensione][d]'); if (dim) parti.push(dim);
    var peso = el('[peso]'); if (peso) parti.push(peso);
    var stile = el('[stile]'); if (stile === 'italic') parti.push('corsivo');
    var tr = el('[trasformazione]'); if (tr && tr !== 'none') parti.push(tr === 'uppercase' ? 'MAIUSCOLO' : tr);
    var il = el('[interlinea][d]'); if (il) parti.push('riga ' + il);
    q('.dg-tip-riassunto', d).textContent = parti.length ? parti.join(' · ') : 'come il sito';
  }
  function aggiornaRiassunti() { qa('.dg-tip').forEach(riassuntoTip); }

  // ---------- elenchi dei personalizzati ----------
  var contatore = 1000;
  function aggiungiVoce(tpl, contenitore) {
    var html = q(tpl).innerHTML.replace(/__I__/g, 'n' + (contatore++));
    var div = document.createElement('div'); div.innerHTML = html;
    var voce = div.firstElementChild;
    q(contenitore).appendChild(voce);
    qa('.dg-colore', voce).forEach(legaColore);
    aggiornaTuttiColori(); aggiornaRiassunti();
    var nome = q('.dg-voce-nome', voce); if (nome) nome.focus();
    segnaModificato(); programmaAnteprima();
  }
  q('#dg-aggiungi-colore').addEventListener('click', function () { aggiungiVoce('#dg-tpl-colore', '#dg-colori-personalizzati'); });
  q('#dg-aggiungi-font').addEventListener('click', function () { aggiungiVoce('#dg-tpl-font', '#dg-font-personalizzati'); });
  form.addEventListener('click', function (e) {
    var b = e.target.closest('.dg-elimina');
    if (!b) return;
    var voce = b.closest('.dg-voce');
    var nome = q('.dg-voce-nome', voce).value || 'questa voce';
    if (!confirm('Elimino "' + nome + '"? Dove era usato, torna il valore scritto nel foglio di stile del sito.')) return;
    voce.remove();
    aggiornaTuttiColori(); segnaModificato(); programmaAnteprima();
  });
  // Il nome di una voce nuova dà la variabile; una voce già salvata tiene il suo id.
  form.addEventListener('input', function (e) {
    if (!e.target.classList.contains('dg-voce-nome')) return;
    var voce = e.target.closest('.dg-voce'), id = q('input[type=hidden]', voce);
    if (id.value) return;
    var s = slug(e.target.value), v = q('.dg-voce-var', voce);
    v.textContent = s ? (voce.classList.contains('dg-voce-font') ? '--g-font-' : '--g-colore-') + s : 'variabile dal nome';
  });

  // ---------- modifiche e anteprima ----------
  function segnaModificato() { modificato = true; q('#dg-modificato').hidden = false; }
  function cambiato(el) {
    var c = el.closest('.dg-colore'); if (c) aggiornaColore(c);
    if (el.name && /^v\[colori\]/.test(el.name)) aggiornaTuttiColori();
    var d = el.closest('.dg-tip'); if (d) riassuntoTip(d);
    if (el.classList.contains('dg-famiglia')) aggiornaEtichetteGlobali();
    segnaModificato(); programmaAnteprima();
  }
  // Il selettore nativo, la casella del colore e la tendina dei globali hanno i loro ascoltatori (legaColore):
  // qui non si toccano, se no l'evento input della tendina azzera la scelta prima che arrivi il change.
  var proprio = function (el) { return el.classList.contains('dg-picker') || el.classList.contains('dg-colore-testo') || el.classList.contains('dg-globali'); };
  form.addEventListener('input', function (e) { if (e.target.matches('input,textarea,select') && !proprio(e.target)) cambiato(e.target); });
  form.addEventListener('change', function (e) { if (e.target.matches('select,input[type=checkbox],input[type=number]') && !proprio(e.target)) cambiato(e.target); });
  qa('.dg-colore').forEach(legaColore);

  /** Se cambia la famiglia di un font globale, le tendine "Caratteri globali" mostrano il nome nuovo. */
  function aggiornaEtichetteGlobali() {
    dati.globali.font.forEach(function (f) {
      var sel = q('select[name="v[font][sistema][' + f.id + '][famiglia]"]') || q('select[name="v[font][personalizzati]"]');
      if (!sel) return;
      f.famiglia = sel.value;
      qa('option[value="globale:font-' + f.id + '"]').forEach(function (o) { o.textContent = f.nome + ' (' + sel.value + ')'; });
    });
  }

  function programmaAnteprima() { clearTimeout(timer); timer = setTimeout(anteprima, 350); }
  function anteprima() {
    var fd = new FormData(form); fd.set('azione', 'css');
    var n = ++richiesta;
    stato.textContent = 'Aggiorno…';
    fetch('design.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (n !== richiesta) return;
        if (j.errore) { stato.textContent = 'Errore: ' + j.errore; return; }
        ultimoCss = j.css; ultimoLink = j.link || '';
        applica();
        stato.textContent = 'Anteprima aggiornata (non ancora salvata)';
      })
      .catch(function () { stato.textContent = 'Anteprima non raggiungibile'; });
  }
  /** Mette il CSS calcolato nell'iframe del sito: stesso tag che il sito usa (#g-css), stesso <link> dei font. */
  function applica() {
    if (ultimoCss === null) return;
    var doc;
    try { doc = iframe.contentDocument; } catch (e) { return; }
    if (!doc || !doc.head) return;
    var st = doc.getElementById('g-css');
    if (!st) { st = doc.createElement('style'); st.id = 'g-css'; doc.head.appendChild(st); }
    st.textContent = ultimoCss;
    var link = doc.getElementById('g-fonts');
    if (ultimoLink) {
      if (!link) { link = doc.createElement('link'); link.id = 'g-fonts'; link.rel = 'stylesheet'; doc.head.appendChild(link); }
      if (link.getAttribute('href') !== ultimoLink) link.href = ultimoLink;
    } else if (link) link.remove();
  }
  // Nell'anteprima i popup del sito non servono: coprirebbero quello che si sta guardando.
  iframe.addEventListener('load', function () { applica(); try { iframe.contentDocument.querySelectorAll('.popup').forEach(function (e) { e.remove(); }); } catch (e) {} });
  q('#dg-anteprima-pagina').addEventListener('change', function () { iframe.src = (iframe.getAttribute('data-base') || '/') + this.value.replace(/^\//, ''); });
  qa('.dg-misure-anteprima button').forEach(function (b) {
    b.addEventListener('click', function () {
      qa('.dg-misure-anteprima button').forEach(function (x) { x.classList.toggle('attiva', x === b); });
      q('.dg-cornice').dataset.misura = b.dataset.misura;
    });
  });

  form.addEventListener('submit', function () { modificato = false; });
  window.addEventListener('beforeunload', function (e) { if (modificato) { e.preventDefault(); e.returnValue = ''; } });

  // ---------- immagini: la libreria del pannello (stessa chiamata dell'editor) ----------
  var dialogo = document.getElementById('dg-media'), destinazione = null, immagini = [];
  form.addEventListener('click', function (e) {
    var b = e.target.closest('.dg-scegli');
    if (b) { destinazione = b.closest('.dg-immagine'); apriMedia(); return; }
    var x = e.target.closest('.dg-immagine .dg-x');
    if (x) { var c = x.closest('.dg-immagine'); q('input[type=text]', c).value = ''; q('.dg-miniatura', c).hidden = true; cambiato(q('input[type=text]', c)); }
  });
  form.addEventListener('input', function (e) {
    var c = e.target.closest('.dg-immagine'); if (!c || e.target.type !== 'text') return;
    var img = q('.dg-miniatura', c); img.src = e.target.value; img.hidden = !e.target.value;
  });
  function apriMedia() {
    dialogo.showModal();
    var fd = new FormData(); fd.set('azione', 'media'); fd.set('_csrf', q('input[name=_csrf]', form).value); fd.set('pagina', '1');
    q('#dg-media-griglia').innerHTML = '<p class="dg-aiuto">Carico…</p>';
    fetch('editor-api.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      immagini = j.immagini || []; disegnaMedia();
      q('#dg-media-conta').textContent = (j.totale || immagini.length) + ' immagini' + (j.pagine > 1 ? ' (le prime ' + immagini.length + ')' : '');
    }).catch(function () { q('#dg-media-griglia').innerHTML = '<p class="dg-aiuto">Libreria non raggiungibile. Incolla l\'indirizzo dell\'immagine nella casella.</p>'; });
  }
  function disegnaMedia() {
    var cerca = q('#dg-media-cerca').value.toLowerCase(), g = q('#dg-media-griglia'); g.innerHTML = '';
    immagini.filter(function (m) { return !cerca || (m.nome || '').toLowerCase().indexOf(cerca) >= 0; }).forEach(function (m) {
      var b = document.createElement('button'); b.type = 'button';
      b.innerHTML = '<img loading="lazy" alt=""><small></small>';
      q('img', b).src = m.url; q('small', b).textContent = m.nome || m.url;
      b.addEventListener('click', function () {
        if (destinazione) { var i = q('input[type=text]', destinazione); i.value = m.url; var img = q('.dg-miniatura', destinazione); img.src = m.url; img.hidden = false; cambiato(i); }
        dialogo.close();
      });
      g.appendChild(b);
    });
    if (!g.children.length) g.innerHTML = '<p class="dg-aiuto">Nessuna immagine.</p>';
  }
  q('#dg-media-cerca').addEventListener('input', disegnaMedia);
  qa('[data-chiudi]', dialogo).forEach(function (b) { b.addEventListener('click', function () { dialogo.close(); }); });

  aggiornaTuttiColori(); aggiornaRiassunti();
})();
