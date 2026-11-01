// 2.8.0: Contatti e Attributi dei contatti (admin/newsletter-contatti.php, newsletter-attributi.php). Niente gestori in
// linea (la CSP a nonce li vieterebbe): attributi data-*.
//   data-apri-dialogo="#id"   pulsante: apre quel <dialog> come finestra del pannello
//   data-nlx-azione           tendina delle azioni di gruppo: mostra solo i campi dell'azione scelta (data-nlx-per)
//                             e cambia la domanda di conferma (rossa per lista nera ed elimina)
//   data-nlx-ordina           elenco delle colonne: si riordina trascinando (SortableJS) o con le frecce data-nlx-su/giu
//   data-nla-tipo             tipo del nuovo attributo: le opzioni compaiono per Categoria e Scelta multipla
// 2.9.0, importazione ed esportazione (admin/newsletter-importa.php, la finestra Esporta di newsletter-contatti.php):
//   data-nli-modo             Carica un file o Copia e incolla: mostra il pannello giusto (data-nli-pannello)
//   data-nli-col              tendina dell'abbinamento: con "Crea un nuovo attributo" compaiono nome e tipo (data-nli-nuovo)
//   data-nli-aggiorna         "Aggiorna gli attributi": senza, "Celle vuote" si spegne (data-nli-vuote), come Brevo
//   data-nlx-origine          Quali contatti esportare: mostra la lista o la campagna (data-nlx-origine-per)
//   data-nl-giro="pagina"     lavoro a giri: chiede un giro dopo l'altro (POST azione=giro, o data-azione) e aggiorna la
//                             barra (data-nl-barra) e il testo (data-nl-testo); alla fine ricarica la pagina
(function () {
  'use strict';
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-apri-dialogo], [data-nlx-su], [data-nlx-giu]');
    if (!b) return;
    if (b.hasAttribute('data-apri-dialogo')) {
      var d = document.querySelector(b.getAttribute('data-apri-dialogo'));
      if (d && d.showModal && !d.open) { d.showModal(); var f = d.querySelector('input:not([type=hidden]), select, textarea'); if (f) f.focus(); }
      return;
    }
    var li = b.closest('li'); if (!li) return;
    if (b.hasAttribute('data-nlx-su') && li.previousElementSibling) li.parentNode.insertBefore(li, li.previousElementSibling);
    if (b.hasAttribute('data-nlx-giu') && li.nextElementSibling) li.parentNode.insertBefore(li.nextElementSibling, li);
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
    form.querySelectorAll('[data-nlx-per]').forEach(function (c) { c.hidden = (' ' + c.getAttribute('data-nlx-per') + ' ').indexOf(' ' + v + ' ') < 0; });
    var b = form.querySelector('#nlx-applica'); if (!b) return;
    var q = DOMANDE[v] || ["Applicare l'azione ai contatti scelti?", false];
    var tutti = form.querySelector('#nlx-tutti');
    b.setAttribute('data-conferma', q[0] + (tutti && tutti.checked ? ' (Tutti i contatti del filtro.)' : ''));
    if (q[1]) b.setAttribute('data-conferma-pericolo', ''); else b.removeAttribute('data-conferma-pericolo');
    b.setAttribute('data-conferma-ok', v === 'elimina' ? 'Elimina definitivamente' : v === 'blocca' ? 'Metti in lista nera' : 'Applica');
  }
  document.addEventListener('change', function (ev) {
    var el = ev.target;
    if (el.hasAttribute && el.hasAttribute('data-nlx-azione')) azione(el);
    if (el.id === 'nlx-tutti') { var s = document.querySelector('[data-nlx-azione]'); if (s) azione(s); }
    if (el.hasAttribute && el.hasAttribute('data-nlx-origine')) origine();
    if (el.hasAttribute && el.hasAttribute('data-nli-modo')) modo();
    if (el.hasAttribute && el.hasAttribute('data-nli-col')) colonna(el);
    if (el.hasAttribute && el.hasAttribute('data-nli-aggiorna')) aggiorna(el);
    if (el.hasAttribute && el.hasAttribute('data-nla-tipo')) {
      var o = el.form.querySelector('[data-nla-opzioni]');
      if (o) { o.hidden = ['category', 'multiple-choice'].indexOf(el.value) < 0; var t = o.querySelector('textarea'); if (t) t.required = !o.hidden; }
    }
  });
  // Senza un'azione o senza contatti scelti il pulsante non chiede conferma e non invia: dice cosa manca, sotto.
  // Sulla finestra e in cattura: parte prima della conferma di pannello.js (che ascolta sul documento).
  window.addEventListener('click', function (ev) {
    var e = ev.target.closest && ev.target.closest('#nlx-esporta-avvia');
    if (e) {
      // Esporta i contatti scelti: serve almeno una riga spuntata, o "Tutti i contatti".
      var o = e.form.querySelector('[name=esp_origine]:checked'), err = document.getElementById('nlx-esporta-err');
      var vuoto = o && o.value === 'scelti' && !document.querySelectorAll('.nlx-sel:checked').length && !(document.getElementById('nlx-tutti') || {}).checked;
      if (err) { err.hidden = !vuoto; err.textContent = vuoto ? 'Spunta almeno un contatto nella tabella, oppure la casella «Tutti i contatti», oppure scegli una lista o una campagna.' : ''; }
      if (vuoto) { ev.preventDefault(); ev.stopPropagation(); }
      else { var d0 = document.getElementById('nlx-esporta'); setTimeout(function () { if (d0 && d0.open) d0.close(); }, 50); }
      return;
    }
    var b = ev.target.closest && ev.target.closest('#nlx-applica');
    if (!b || b.__pnSi) return;
    var f = b.form, cosa = f.querySelector('[name=cosa]');
    if (cosa.value === 'esporta') {
      // Esporta apre la finestra di Brevo: attributi, separatore, quali contatti.
      ev.preventDefault(); ev.stopPropagation();
      var d = document.getElementById('nlx-esporta');
      var sc = d && d.querySelector('[name=esp_origine][value=scelti]'); if (sc) { sc.checked = true; origine(); }
      if (d && d.showModal && !d.open) d.showModal();
      return;
    }
    var spuntate = document.querySelectorAll('.nlx-sel:checked').length, tutti = f.querySelector('#nlx-tutti');
    var msg = !cosa.value ? "Scegli un'azione nella tendina Azione." : (!spuntate && !(tutti && tutti.checked) ? 'Spunta almeno un contatto nella tabella, oppure la casella "Tutti i contatti".' : '');
    var p = f.querySelector('.nlx-errore');
    if (msg) {
      ev.preventDefault(); ev.stopPropagation();
      if (!p) { p = document.createElement('p'); p.className = 'errore-campo nlx-errore'; p.id = 'nlx-errore'; p.setAttribute('role', 'alert'); b.insertAdjacentElement('afterend', p); }
      p.textContent = msg;
    } else if (p) p.remove();
  }, true);

  function origine() {
    var o = document.querySelector('[name=esp_origine]:checked');
    document.querySelectorAll('[data-nlx-origine-per]').forEach(function (c) { c.hidden = !o || o.value !== c.getAttribute('data-nlx-origine-per'); });
  }
  function modo() {
    var m = document.querySelector('[data-nli-modo]:checked');
    document.querySelectorAll('[data-nli-pannello]').forEach(function (c) { c.hidden = !m || m.value !== c.getAttribute('data-nli-pannello'); });
  }
  function colonna(sel) {
    var n = sel.parentNode.querySelector('[data-nli-nuovo]'); if (!n) return;
    n.hidden = sel.value !== '_NUOVO';
    var i = n.querySelector('input'); if (i) i.required = !n.hidden;
  }
  function aggiorna(c) {
    var v = document.querySelector('[data-nli-vuote]'); if (!v) return;
    v.disabled = !c.checked; if (!c.checked) v.checked = false;
  }
  // Il lavoro a giri: un giro dopo l'altro finché il server dice finito, poi la pagina si ricarica (resoconto o file pronto).
  function giri(box) {
    var barra = box.querySelector('[data-nl-barra]'), testo = box.querySelector('[data-nl-testo]'), err = box.querySelector('[data-nl-errore]'), rip = box.querySelector('[data-nl-riprendi]');
    function uno() {
      var d = new FormData(); d.append('azione', box.getAttribute('data-azione') || 'giro'); d.append('_csrf', box.getAttribute('data-csrf'));
      if (box.getAttribute('data-id')) d.append('id', box.getAttribute('data-id'));
      fetch(box.getAttribute('data-nl-giro'), { method: 'POST', body: d, headers: { 'X-CSRF': box.getAttribute('data-csrf'), 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.errore) throw new Error(j.errore);
          var p = Math.round((j.fatte || 0) * 100 / Math.max(1, j.totale || 0));
          if (barra) { barra.setAttribute('aria-valuenow', p); barra.firstElementChild.style.width = p + '%'; }
          if (testo && j.testo) testo.textContent = j.testo + ' (' + p + '%)';
          if (j.finito) { location.replace(location.pathname + location.search.replace(/[?&]avvia=1/, '')); return; }
          setTimeout(uno, 300);
        })
        .catch(function (e) {
          if (err) { err.hidden = false; err.textContent = 'Il giro non è andato: ' + (e && e.message ? e.message : 'la rete non risponde') + '. Premi Riprendi: il lavoro continua da dove era.'; }
          if (rip) rip.hidden = false;
        });
    }
    if (rip) rip.addEventListener('click', function () { rip.hidden = true; if (err) err.hidden = true; uno(); });
    uno();
  }

  function avvia() {
    origine(); modo();
    document.querySelectorAll('[data-nli-col]').forEach(colonna);
    var ag = document.querySelector('[data-nli-aggiorna]'); if (ag) aggiorna(ag);
    document.querySelectorAll('[data-nl-giro]').forEach(giri);
    var s = document.querySelector('[data-nlx-azione]'); if (s) azione(s);
    var t = document.querySelector('[data-nla-tipo]'); if (t) t.dispatchEvent(new Event('change', { bubbles: true }));
    document.querySelectorAll('[data-nlx-ordina]').forEach(function (ul) {
      if (window.Sortable) window.Sortable.create(ul, { handle: '.nlx-maniglia', animation: 120 });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', avvia); else avvia();
})();
