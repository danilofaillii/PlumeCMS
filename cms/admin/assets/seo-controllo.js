/*
 * Controllo SEO nella scheda classica del contenuto (admin/contenuto.php): il riquadro #seo-controllo mostra il
 * punteggio calcolato dal server all'apertura; il pulsante "Ricontrolla" rimanda i campi del modulo a seo-api.php
 * (azione analisi) e ridisegna, senza salvare. Il testo arriva dalla scheda Testo (textarea name=contenuto) o,
 * se c'è, dalla scheda Visuale.
 */
(function () {
  var box = document.getElementById('seo-controllo');
  if (!box) return;
  var form = document.getElementById('sc-form');
  var btn = document.getElementById('seo-ricontrolla');
  var out = document.getElementById('seo-controllo-corpo');
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
    var c = testo();
    fd.append('contenuto', window.campiProtetti ? window.campiProtetti.codifica(c) : c);
    fetch('seo-api.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j.errore) { out.innerHTML = '<p class="hint">' + j.errore + '</p>'; } else { out.innerHTML = j.html; } })
      .catch(function (e) { out.innerHTML = '<p class="hint">Controllo non riuscito: ' + e + '</p>'; })
      .then(function () { btn.disabled = false; btn.textContent = 'Ricontrolla'; });
  }
  btn.addEventListener('click', ricontrolla);
})();
