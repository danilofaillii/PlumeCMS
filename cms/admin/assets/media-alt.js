/*
 * 2.29.0: Media > Senza alt (admin/includes/media-alt-pagina.php). Ogni casella [data-alt-url] salva l'alt dell'immagine quando
 * si esce dalla casella o con Invio: POST a seo-api.php (azione media_alt_salva, token CSRF). Una foto che compare in più righe
 * (nelle pagine e nella libreria) si aggiorna in tutte. Niente gestori in linea.
 */
(function () {
  var campi = Array.prototype.slice.call(document.querySelectorAll('input[data-alt-url]'));
  if (!campi.length) return;
  function salva(c) {
    var esito = c.parentNode.querySelector('.ma-esito');
    var valore = c.value.trim();
    if (valore === (c.getAttribute('data-salvato') || '')) return;
    var fd = new FormData();
    fd.append('_csrf', c.getAttribute('data-alt-csrf')); fd.append('azione', 'media_alt_salva'); fd.append('url', c.getAttribute('data-alt-url')); fd.append('alt', valore);
    if (esito) { esito.className = 'ma-esito'; esito.textContent = 'Salvo...'; }
    c.disabled = true;
    fetch('seo-api.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { errore: 'Risposta del server non leggibile (' + r.status + ').' }; }); })
      .then(function (j) {
        if (j.errore) throw new Error(j.errore);
        var voce = c.closest('.ma-voce');
        campi.forEach(function (x) {
          var v = x.closest('.ma-voce');
          if (x !== c && v && voce && v.getAttribute('data-chiave') && v.getAttribute('data-chiave') === j.percorso) { x.value = j.alt; x.setAttribute('data-salvato', j.alt); v.classList.toggle('ma-fatta', j.alt !== ''); }
        });
        c.value = j.alt; c.setAttribute('data-salvato', j.alt);
        if (voce) voce.classList.toggle('ma-fatta', j.alt !== '');
        if (esito) { esito.className = 'ma-esito ma-ok'; esito.setAttribute('role', 'status'); esito.textContent = j.alt !== '' ? 'Salvato: vale in tutte le pagine che usano questa foto senza un alt loro.' : 'Alt tolto.'; }
        var ns = document.getElementById('ma-n-senza'), nc = document.getElementById('ma-n-con');
        if (ns && nc && voce && voce.closest('#ma-elenco-libreria')) { var fatte = document.querySelectorAll('#ma-elenco-libreria .ma-voce.ma-fatta').length; ns.textContent = Math.max(0, +ns.getAttribute('data-base') || (ns.setAttribute('data-base', ns.textContent), +ns.textContent)) - fatte; nc.textContent = (+nc.getAttribute('data-base') || (nc.setAttribute('data-base', nc.textContent), +nc.textContent)) + fatte; }
      })
      .catch(function (e) { if (esito) { esito.className = 'ma-esito ma-no'; esito.setAttribute('role', 'alert'); esito.textContent = 'Non salvato: ' + e.message; } })
      .then(function () { c.disabled = false; });
  }
  campi.forEach(function (c) {
    c.setAttribute('data-salvato', c.value.trim());
    c.addEventListener('change', function () { salva(c); });
    c.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); salva(c); } });
  });
})();
