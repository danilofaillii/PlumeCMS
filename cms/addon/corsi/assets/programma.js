// Corsi online (addon corsi, 2.1): il programma di un corso si riordina trascinando, con SortableJS del pannello
// (admin/assets/editor/Sortable.min.js). Moduli dalla loro maniglia; lezioni dalla loro, anche da un modulo all'altro.
// Trascinamento con il mouse simulato da Sortable (forceFallback): uguale con il mouse, il dito e le prove.
// Dopo ogni trascinamento l'ordine intero va al server (POST azione=ordine, con il token CSRF della pagina), che lo
// controlla e lo salva tutto o niente. Le frecce restano per la tastiera. Niente gestori in linea (CSP a nonce).
(function () {
  var box = document.getElementById('co-moduli');
  if (!box || typeof Sortable === 'undefined') return;
  var stato = document.getElementById('co-ordine-stato');
  var token = (document.querySelector('input[name="_csrf"]') || {}).value || '';
  var figli = function (el, sel) { return Array.prototype.filter.call(el.children, function (x) { return x.matches(sel); }); };
  function ordine() {
    return figli(box, '.co-modulo').map(function (m) {
      var ol = m.querySelector('.co-lez');
      return { modulo: Number(m.getAttribute('data-modulo')), lezioni: (ol ? figli(ol, 'li[data-id]') : []).map(function (l) { return Number(l.getAttribute('data-id')); }) };
    });
  }
  // Le frecce della prima e dell'ultima voce si spengono, come quando la pagina arriva dal server.
  function frecce() {
    var ms = figli(box, '.co-modulo');
    ms.forEach(function (m, i) {
      var su = m.querySelector('.co-modulo-su'), giu = m.querySelector('.co-modulo-giu');
      if (su) su.disabled = i === 0;
      if (giu) giu.disabled = i === ms.length - 1;
      var ol = m.querySelector('.co-lez');
      var ls = ol ? figli(ol, 'li[data-id]') : [];
      ls.forEach(function (l, j) {
        var a = l.querySelector('.co-su'), b = l.querySelector('.co-giu');
        if (a) a.disabled = j === 0;
        if (b) b.disabled = j === ls.length - 1;
      });
    });
  }
  function scrivi(t) { if (stato) stato.textContent = t; }
  function salva() {
    var fd = new FormData();
    fd.append('_csrf', token);
    fd.append('azione', 'ordine');
    fd.append('ordine', JSON.stringify(ordine()));
    scrivi('Salvo il nuovo ordine…');
    box.setAttribute('aria-busy', 'true');
    fetch(location.pathname + location.search, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (j) {
          if (!r.ok || !j.ok) throw new Error(j.errore || 'L\'ordine non è stato salvato.');
        });
      })
      .then(function () { frecce(); scrivi('Ordine salvato.'); box.setAttribute('data-salvato', String(Date.now())); })
      .catch(function (e) { scrivi(e.message + ' Ricarico la pagina.'); setTimeout(function () { location.reload(); }, 1600); })
      .then(function () { box.removeAttribute('aria-busy'); });
  }
  var base = { animation: 150, ghostClass: 'co-fantasma', forceFallback: true, fallbackOnBody: true, fallbackTolerance: 3, onEnd: function (ev) { if (ev.from !== ev.to || ev.oldIndex !== ev.newIndex) salva(); } };
  Sortable.create(box, Object.assign({ draggable: '.co-modulo', handle: '.co-maniglia-modulo' }, base));
  Array.prototype.forEach.call(box.querySelectorAll('.co-lez'), function (ol) {
    Sortable.create(ol, Object.assign({ group: 'lezioni', draggable: 'li[data-id]', handle: '.co-maniglia-lezione' }, base));
  });
})();
