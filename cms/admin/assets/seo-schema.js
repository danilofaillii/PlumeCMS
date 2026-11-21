/*
 * 2.29.0: i dati per Google in più nel riquadro SEO della scheda (admin/contenuto.php, includes/seo-schema.php): la tendina
 * mostra i campi del tipo scelto (Prodotto, How-to, Video); i passi dell'How-to si aggiungono e si tolgono, fino a venti.
 * Niente gestori in linea (la CSP del pannello li blocca).
 */
(function () {
  var box = document.getElementById('sc-schema');
  if (!box) return;
  var sel = box.querySelector('[data-schema-tipo]');
  var passi = document.getElementById('sc-schema-passi');
  var MAX = 20;
  function mostra() {
    Array.prototype.forEach.call(box.querySelectorAll('[data-schema-campi]'), function (d) { d.hidden = d.getAttribute('data-schema-campi') !== sel.value; });
  }
  function rinumera() {
    Array.prototype.forEach.call(passi.querySelectorAll('.sc-schema-passo'), function (r, i) {
      r.querySelector('.sc-schema-n').textContent = i + 1;
      var inp = r.querySelector('input'), ta = r.querySelector('textarea'), b = r.querySelector('[data-schema-togli-passo]');
      inp.name = 'schema[howto][passi][' + i + '][nome]'; inp.setAttribute('aria-label', 'Nome del passo ' + (i + 1));
      ta.name = 'schema[howto][passi][' + i + '][testo]'; ta.setAttribute('aria-label', 'Testo del passo ' + (i + 1));
      if (b) b.setAttribute('aria-label', 'Togli il passo ' + (i + 1));
    });
    var agg = box.querySelector('[data-schema-aggiungi-passo]');
    if (agg) agg.disabled = passi.children.length >= MAX;
  }
  sel.addEventListener('change', mostra);
  mostra();
  if (passi) rinumera();
  box.addEventListener('click', function (e) {
    var b = e.target.closest('[data-schema-aggiungi-passo], [data-schema-togli-passo]');
    if (!b || !passi) return;
    e.preventDefault();
    if (b.hasAttribute('data-schema-aggiungi-passo')) {
      if (passi.children.length >= MAX) return;
      var r = passi.lastElementChild.cloneNode(true);
      r.querySelector('input').value = ''; r.querySelector('textarea').value = '';
      passi.appendChild(r); rinumera(); r.querySelector('input').focus();
    } else {
      var riga = b.closest('.sc-schema-passo');
      if (passi.children.length > 1) riga.remove(); else { riga.querySelector('input').value = ''; riga.querySelector('textarea').value = ''; }
      rinumera();
    }
  });
})();
