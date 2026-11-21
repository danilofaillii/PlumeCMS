/*
 * Controllo SEO nella scheda classica del contenuto (admin/contenuto.php): il riquadro #seo-controllo mostra il
 * punteggio calcolato dal server all'apertura; il pulsante "Ricontrolla" rimanda i campi del modulo a seo-api.php
 * (azione analisi) e ridisegna, senza salvare. Il testo arriva dalla scheda Testo (textarea name=contenuto) o,
 * se c'è, dalla scheda Visuale.
 * 2.29.0: manda anche tipo e id del contenuto (per i link interni suggeriti, che escludono il contenuto stesso) e muove
 * i pulsanti "Copia il link" del riquadro Collega (data-copia-link).
 */
(function () {
  var box = document.getElementById('seo-controllo');
  if (!box) return;
  var form = document.getElementById('sc-form');
  var btn = document.getElementById('seo-ricontrolla');
  var out = document.getElementById('seo-controllo-corpo');
  var qs = new URLSearchParams(location.search);
  function valore(nome) { var el = form.elements[nome]; return el ? (el.value || '') : ''; }
  function testo() {
    var vis = document.getElementById('te-visuale');
    var ta = form.elements['contenuto'];
    if (vis && vis.offsetParent !== null && vis.innerHTML.trim() !== '') return vis.innerHTML;
    return ta ? ta.value : '';
  }
  function ricontrolla() {
    btn.disabled = true; btn.textContent = 'Controllo...';
    var fd = new FormData();
    fd.append('_csrf', form.elements['_csrf'].value);
    fd.append('azione', 'analisi');
    ['title', 'slug', 'seo_title', 'meta_description', 'seo_description', 'keyword_target', 'keywords_secondarie', 'immagine', 'immagine_alt', 'tldr', 'faq_testo', 'type', 'content_format'].forEach(function (k) { fd.append(k, valore(k)); });
    fd.append('tipo_contenuto', qs.get('tipo') || ''); fd.append('id', qs.get('id') || '0');
    var c = testo();
    fd.append('contenuto', window.campiProtetti ? window.campiProtetti.codifica(c) : c);
    fetch('seo-api.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j.errore) { out.innerHTML = '<p class="hint">' + j.errore + '</p>'; } else { out.innerHTML = j.html; } })
      .catch(function (e) { out.innerHTML = '<p class="hint">Controllo non riuscito: ' + e + '</p>'; })
      .then(function () { btn.disabled = false; btn.textContent = 'Ricontrolla'; });
  }
  btn.addEventListener('click', ricontrolla);
  // 2.29.0: Copia il link (riquadro Collega). Con gli appunti del browser; se non si può, selezionando il testo in una casella.
  function copia(testo) {
    if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(testo).catch(function () { return copiaVecchia(testo); });
    return Promise.resolve(copiaVecchia(testo));
  }
  function copiaVecchia(testo) {
    var ta = document.createElement('textarea'); ta.value = testo; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.left = '-9999px';
    document.body.appendChild(ta); ta.select();
    var ok = false; try { ok = document.execCommand('copy'); } catch (e) {}
    ta.remove(); if (!ok) throw new Error('non copiato');
  }
  box.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copia-link]');
    if (!b) return;
    e.preventDefault();
    var esito = b.closest('.seo-collega') ? b.closest('.seo-collega').querySelector('.seo-collega-esito') : null;
    copia(b.getAttribute('data-copia-link')).then(function () {
      var t = b.textContent; b.textContent = 'Copiato'; if (esito) esito.textContent = 'Indirizzo copiato: incollalo con il pulsante link del testo.';
      setTimeout(function () { b.textContent = t; }, 1800);
    }).catch(function () { if (esito) esito.textContent = 'Non riesco a copiare: seleziona l\'indirizzo e copialo a mano: ' + b.getAttribute('data-copia-link'); });
  });
})();
