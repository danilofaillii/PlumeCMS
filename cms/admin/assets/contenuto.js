/* La scheda di articoli e pagine: editor Visuale/Testo, tag, immagine in evidenza, finestra delle immagini. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var visuale = $('#te-visuale'), testo = $('#te-testo'), scheda = null, markdown = SC.formato === 'markdown';

  function api(azione, campi, file) {
    var fd = new FormData();
    fd.append('_csrf', SC.csrf); fd.append('id', 0); fd.append('tipo', 'pagine'); fd.append('azione', azione);
    Object.keys(campi || {}).forEach(function (k) { fd.append(k, window.campiProtetti ? window.campiProtetti.codifica(campi[k]) : campi[k]); });
    if (file) fd.append('immagine', file);
    return fetch('editor-api.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { errore: window.campiProtetti ? window.campiProtetti.spiega(r.status) : 'Risposta del server non leggibile (' + r.status + ').' }; }); })
      .then(function (j) { if (j.errore) throw new Error(j.errore); return j; });
  }

  // ---------------- editor: Visuale e Testo
  function daTestoAVisuale() {
    if (markdown) {
      visuale.innerHTML = '<p style="color:var(--text-muted)">Converto il Markdown…</p>';
      api('markdown', { testo: testo.value }).then(function (j) { visuale.innerHTML = j.html; }).catch(function (e) { visuale.textContent = e.message; });
    } else visuale.innerHTML = testo.value;
  }
  function daVisualeATesto() {
    if (scheda !== 'visuale' || visuale.hidden) return;
    if (markdown) {
      // Modificato da Visuale: da qui in poi il testo è HTML.
      if (visuale.dataset.toccato) { testo.value = visuale.innerHTML; $('[name=content_format]').value = 'html'; markdown = false; var md = $('.te-md'); if (md) md.remove(); }
      return;
    }
    testo.value = visuale.innerHTML;
  }
  function mostra(s) {
    daVisualeATesto();
    scheda = s;
    $$('.te-schede [data-scheda]').forEach(function (b) { b.classList.toggle('attivo', b.dataset.scheda === s); });
    $('#te-barra').style.display = s === 'visuale' ? '' : 'none';
    visuale.hidden = s !== 'visuale'; testo.hidden = s !== 'testo';
    if (s === 'visuale') daTestoAVisuale();
    conta();
  }
  $$('.te-schede [data-scheda]').forEach(function (b) { b.addEventListener('click', function () { mostra(b.dataset.scheda); }); });
  visuale.addEventListener('input', function () { visuale.dataset.toccato = '1'; conta(); });
  testo.addEventListener('input', conta);
  function conta() {
    var t = scheda === 'visuale' ? visuale.innerText : testo.value.replace(/<[^>]+>/g, ' ');
    var n = (t.match(/[\p{L}\p{N}][\p{L}\p{N}'’-]*/gu) || []).length;
    $('#te-parole').textContent = n + ' parole';
  }
  $$('#te-barra [data-cmd]').forEach(function (b) {
    if (b.tagName === 'SELECT') { b.addEventListener('change', function () { visuale.focus(); document.execCommand('formatBlock', false, b.value); visuale.dataset.toccato = '1'; }); return; }
    b.addEventListener('mousedown', function (e) { e.preventDefault(); });
    b.addEventListener('click', function () { visuale.focus(); document.execCommand(b.dataset.cmd, false, null); visuale.dataset.toccato = '1'; });
  });
  $('#te-barra [data-az=link]').addEventListener('click', function () {
    visuale.focus();
    var sel = window.getSelection(), a = sel.anchorNode && sel.anchorNode.parentElement && sel.anchorNode.parentElement.closest('a');
    var u = prompt('Indirizzo del link', a ? a.getAttribute('href') : 'https://');
    if (u === null) return;
    if (a && sel.isCollapsed) { a.setAttribute('href', u); } else document.execCommand('createLink', false, u);
    visuale.dataset.toccato = '1';
  });
  visuale.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); $('#te-barra [data-az=link]').click(); }
  });
  // Incolla solo testo e tag semplici: niente stili di Word o di Google Docs.
  visuale.addEventListener('paste', function (e) {
    var html = e.clipboardData && e.clipboardData.getData('text/html');
    if (!html) return;
    e.preventDefault();
    var d = document.createElement('div'); d.innerHTML = html;
    $$('script,style,meta,link', d).forEach(function (x) { x.remove(); });
    $$('*', d).forEach(function (x) { Array.prototype.slice.call(x.attributes).forEach(function (at) { if (['href', 'src', 'alt'].indexOf(at.name) < 0) x.removeAttribute(at.name); }); });
    document.execCommand('insertHTML', false, d.innerHTML);
    visuale.dataset.toccato = '1';
  });
  function inserisciHtml(html) {
    if (scheda === 'visuale') { visuale.focus(); document.execCommand('insertHTML', false, html); visuale.dataset.toccato = '1'; }
    else { var p = testo.selectionStart || testo.value.length; testo.value = testo.value.slice(0, p) + '\n' + html + '\n' + testo.value.slice(p); }
    conta();
  }
  window.teInserisciImmagine = function (u) { inserisciHtml('<figure><img src="' + u + '" alt="" loading="lazy"></figure>'); };
  window.teInfografica = function () {
    openIgModal(function (shortcode, html) { inserisciHtml(scheda === 'visuale' ? html : shortcode); }, SC.formato);
  };
  $('#sc-form').addEventListener('submit', function () { daVisualeATesto(); });
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); $('#sc-invio').value = ''; $('#sc-form').requestSubmit(); }
  });
  mostra('visuale');

  // ---------------- titolo, slug, meta
  var slug = $('#sc-slug');
  if (slug && slug.dataset.auto) {
    $('#sc-title').addEventListener('input', function () {
      if (slug.dataset.auto) slug.value = this.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    });
    slug.addEventListener('input', function () { delete slug.dataset.auto; });
  }
  var meta = $('#sc-meta');
  if (meta) meta.addEventListener('input', function () { $('#sc-meta-conta').textContent = meta.value.length; });
  $$('.sc-scatola > h3').forEach(function (h) { h.addEventListener('click', function () { h.parentElement.classList.toggle('chiusa'); }); });
  window.scAnteprima = function () { /* il link apre la versione salvata: Ctrl+S prima, se hai cambiato qualcosa */ };

  // ---------------- tag
  var tags = $('#sc-tags');
  if (tags) {
    var chips = $('#sc-tag-chips'), nuovo = $('#sc-tag-nuovo');
    var lista = function () { return tags.value.split(',').map(function (x) { return x.trim(); }).filter(Boolean); };
    var disegna = function () {
      chips.innerHTML = '';
      lista().forEach(function (t) {
        var c = document.createElement('span'); c.className = 'sc-tag-chip'; c.textContent = t;
        var x = document.createElement('button'); x.type = 'button'; x.textContent = '×'; x.title = 'Togli';
        x.addEventListener('click', function () { tags.value = lista().filter(function (y) { return y !== t; }).join(', '); disegna(); });
        c.appendChild(x); chips.appendChild(c);
      });
    };
    var aggiungi = function () {
      nuovo.value.split(',').forEach(function (t) { t = t.trim(); if (t && lista().indexOf(t) < 0) tags.value = lista().concat([t]).join(', '); });
      nuovo.value = ''; disegna();
    };
    nuovo.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); aggiungi(); } });
    nuovo.addEventListener('change', aggiungi);
    disegna();
  }

  // ---------------- immagine in evidenza
  window.scImmagine = function (u) {
    $('#sc-immagine').value = u;
    var img = $('#sc-immagine-anteprima'); img.src = u; img.hidden = !u;
    $('#sc-immagine-togli').hidden = !u;
  };

  // ---------------- finestra delle immagini (la libreria Media)
  var dMedia = $('#sc-media'), cb = null, cartelleFatte = false, timer = null;
  // Apre la libreria e passa l'indirizzo scelto alla funzione globale con quel nome (usata da data-al-clic, senza onclick in linea).
window.scMediaPer = function (nome) { window.scMedia(function (u) { if (typeof window[nome] === 'function') window[nome](u); }); };
window.scMedia = function (f) { cb = f; $('#sc-media-errore').hidden = true; dMedia.showModal(); carica(1); };
  window.scMediaUrl = function () { var u = $('#sc-media-url').value.trim(); if (u) scegli(u); };
  function scegli(u) { dMedia.close(); if (cb) cb(u); }
  function carica(pagina) {
    var g = $('#sc-media-griglia'), pg = $('#sc-media-pagine');
    g.innerHTML = '<p class="hint">Carico…</p>'; pg.innerHTML = '';
    api('media', { cartella: $('#sc-media-cartella').value, q: $('#sc-media-cerca').value, pagina: pagina }).then(function (j) {
      if (!cartelleFatte) { cartelleFatte = true; j.cartelle.forEach(function (c) { if (!c.quante) return; var o = document.createElement('option'); o.value = c.chiave; o.textContent = c.nome + ' (' + c.quante + ')'; $('#sc-media-cartella').appendChild(o); }); }
      g.innerHTML = j.immagini.length ? '' : '<p class="hint">Nessuna immagine qui.</p>';
      j.immagini.forEach(function (m) {
        var b = document.createElement('button'); b.type = 'button'; b.title = m.url;
        var im = document.createElement('img'); im.src = m.url; im.loading = 'lazy'; b.appendChild(im);
        var sp = document.createElement('span'); sp.textContent = m.nome; b.appendChild(sp);
        b.addEventListener('click', function () { scegli(m.url); });
        g.appendChild(b);
      });
      for (var i = 1; i <= j.pagine; i++) {
        if (j.pagine > 12 && Math.abs(i - j.pagina) > 3 && i !== 1 && i !== j.pagine) continue;
        var pb = document.createElement('button'); pb.type = 'button'; pb.className = 'btn btn-sm ' + (i === j.pagina ? 'btn-primary' : 'btn-secondary'); pb.textContent = i;
        (function (n) { pb.addEventListener('click', function () { carica(n); }); })(i);
        pg.appendChild(pb);
      }
    }).catch(function (e) { g.innerHTML = '<p class="hint" style="color:var(--errore-testo)">' + e.message + '</p>'; });
  }
  $('#sc-media-cartella').addEventListener('change', function () { carica(1); });
  $('#sc-media-cerca').addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { carica(1); }, 300); });
  $('#sc-media-file').addEventListener('change', function () {
    var f = this.files[0]; if (!f) return;
    api('carica', {}, f).then(function (j) { scegli(j.url); }).catch(function (e) { var p = $('#sc-media-errore'); p.textContent = e.message; p.hidden = false; });
    this.value = '';
  });
})();
