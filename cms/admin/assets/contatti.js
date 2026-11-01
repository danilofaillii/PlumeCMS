// 2.8.0: Contatti e Attributi dei contatti (admin/newsletter-contatti.php, newsletter-attributi.php). Niente gestori in
// linea (la CSP a nonce li vieterebbe): attributi data-*.
//   data-apri-dialogo="#id"   pulsante: apre quel <dialog> come finestra del pannello
//   data-nlc-azione           tendina delle azioni di gruppo: mostra solo i campi dell'azione scelta (data-nlc-per)
//                             e cambia la domanda di conferma (rossa per lista nera ed elimina)
//   data-nlc-ordina           elenco delle colonne: si riordina trascinando (SortableJS) o con le frecce data-nlc-su/giu
//   data-nla-tipo             tipo del nuovo attributo: le opzioni compaiono per Categoria e Scelta multipla
(function () {
  'use strict';
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-apri-dialogo], [data-nlc-su], [data-nlc-giu]');
    if (!b) return;
    if (b.hasAttribute('data-apri-dialogo')) {
      var d = document.querySelector(b.getAttribute('data-apri-dialogo'));
      if (d && d.showModal && !d.open) { d.showModal(); var f = d.querySelector('input:not([type=hidden]), select, textarea'); if (f) f.focus(); }
      return;
    }
    var li = b.closest('li'); if (!li) return;
    if (b.hasAttribute('data-nlc-su') && li.previousElementSibling) li.parentNode.insertBefore(li, li.previousElementSibling);
    if (b.hasAttribute('data-nlc-giu') && li.nextElementSibling) li.parentNode.insertBefore(li.nextElementSibling, li);
    b.focus();
  });

  var DOMANDE = {
    aggiungi: ['Aggiungere i contatti scelti alla lista?', false],
    togli: ['Togliere i contatti scelti dalle liste? I contatti restano.', false],
    sposta: ['Spostare i contatti scelti nella lista di arrivo? Escono dalla lista caricata.', false],
    blocca: ['Mettere in lista nera i contatti scelti? Non riceveranno più campagne né automazioni. Toglierli dalla lista nera si può solo uno alla volta, dalla loro scheda.', true],
    modifica: ["Cambiare l'attributo ai contatti scelti?", false],
    esporta: ['Scaricare il file CSV dei contatti scelti?', false],
    elimina: ['Eliminare definitivamente i contatti scelti? Si perdono attributi, cronologia, statistiche e lista nera. Non si torna indietro.', true]
  };
  function azione(sel) {
    var v = sel.value, form = sel.form;
    form.querySelectorAll('[data-nlc-per]').forEach(function (c) { c.hidden = (' ' + c.getAttribute('data-nlc-per') + ' ').indexOf(' ' + v + ' ') < 0; });
    var b = form.querySelector('#nlc-applica'); if (!b) return;
    var q = DOMANDE[v] || ["Applicare l'azione ai contatti scelti?", false];
    var tutti = form.querySelector('#nlc-tutti');
    b.setAttribute('data-conferma', q[0] + (tutti && tutti.checked ? ' (Tutti i contatti del filtro.)' : ''));
    if (q[1]) b.setAttribute('data-conferma-pericolo', ''); else b.removeAttribute('data-conferma-pericolo');
    b.setAttribute('data-conferma-ok', v === 'elimina' ? 'Elimina definitivamente' : v === 'blocca' ? 'Metti in lista nera' : 'Applica');
  }
  document.addEventListener('change', function (ev) {
    var el = ev.target;
    if (el.hasAttribute && el.hasAttribute('data-nlc-azione')) azione(el);
    if (el.id === 'nlc-tutti') { var s = document.querySelector('[data-nlc-azione]'); if (s) azione(s); }
    if (el.hasAttribute && el.hasAttribute('data-nla-tipo')) {
      var o = el.form.querySelector('[data-nla-opzioni]');
      if (o) { o.hidden = ['category', 'multiple-choice'].indexOf(el.value) < 0; var t = o.querySelector('textarea'); if (t) t.required = !o.hidden; }
    }
  });
  // Senza un'azione o senza contatti scelti il pulsante non chiede conferma e non invia: dice cosa manca, sotto.
  // Sulla finestra e in cattura: parte prima della conferma di pannello.js (che ascolta sul documento).
  window.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('#nlc-applica');
    if (!b || b.__pnSi) return;
    var f = b.form, cosa = f.querySelector('[name=cosa]');
    var spuntate = document.querySelectorAll('.nlc-sel:checked').length, tutti = f.querySelector('#nlc-tutti');
    var msg = !cosa.value ? "Scegli un'azione nella tendina Azione." : (!spuntate && !(tutti && tutti.checked) ? 'Spunta almeno un contatto nella tabella, oppure la casella "Tutti i contatti".' : '');
    var p = f.querySelector('.nlc-errore');
    if (msg) {
      ev.preventDefault(); ev.stopPropagation();
      if (!p) { p = document.createElement('p'); p.className = 'errore-campo nlc-errore'; p.id = 'nlc-errore'; p.setAttribute('role', 'alert'); b.insertAdjacentElement('afterend', p); }
      p.textContent = msg;
    } else if (p) p.remove();
  }, true);

  function avvia() {
    var s = document.querySelector('[data-nlc-azione]'); if (s) azione(s);
    var t = document.querySelector('[data-nla-tipo]'); if (t) t.dispatchEvent(new Event('change', { bubbles: true }));
    document.querySelectorAll('[data-nlc-ordina]').forEach(function (ul) {
      if (window.Sortable) window.Sortable.create(ul, { handle: '.nlc-maniglia', animation: 120 });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', avvia); else avvia();
})();
