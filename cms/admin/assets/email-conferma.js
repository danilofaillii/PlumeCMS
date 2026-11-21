/*
 * 2.7.0: l'email di conferma dell'iscrizione (admin/includes/email-conferma-campi.php). Niente gestori in linea (CSP a nonce):
 * il contenitore ha data-email-conferma (l'indirizzo di admin/email-conferma-api.php), data-contesto (form o comuni) e
 * data-csrf; dentro, i pulsanti con data-ec-anteprima, data-ec-prova, data-ec-riempi e il selettore del colore con
 * data-ec-colore. L'anteprima esce in un iframe sandbox (srcdoc, niente script), i campi viaggiano come quelli dei form del
 * pannello (campi-protetti.js: base64 se contengono HTML, per ModSecurity).
 */
(function () {
  function scatola(el) { return el.closest('[data-email-conferma]'); }
  function codifica(v) { return window.campiProtetti ? window.campiProtetti.codifica(v) : v; }
  function dati(s, azione) {
    var fd = new FormData();
    fd.append('_csrf', s.getAttribute('data-csrf') || '');
    fd.append('azione', azione);
    fd.append('contesto', s.getAttribute('data-contesto') || 'comuni');
    s.querySelectorAll('[name^="ce["]').forEach(function (el) { fd.append(el.name, codifica(el.value)); });
    // Nella scheda di un form: per cosa ci si iscrive, da quello che c'è scritto adesso.
    var f = s.closest('form');
    if (f) ['titolo', 'nome', 'lista_id'].forEach(function (n) { var x = f.querySelector('[name="' + n + '"]'); if (x) fd.append(n, x.value); });
    return fd;
  }
  function stato(s, testo, tipo) {
    var p = s.querySelector('.ec-stato'); if (!p) return;
    p.textContent = testo; p.className = 'ec-stato' + (tipo ? ' ' + tipo : '');
    p.setAttribute('role', tipo === 'errore' ? 'alert' : 'status');
  }
  function chiedi(s, azione, bottone, zitto) {
    if (bottone) bottone.disabled = true;
    if (!zitto) stato(s, azione === 'prova' ? 'Mando la prova...' : 'Preparo l\'anteprima...', '');
    return fetch(s.getAttribute('data-email-conferma'), { method: 'POST', body: dati(s, azione), credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { errore: window.campiProtetti ? window.campiProtetti.spiega(r.status) : 'Risposta del server non leggibile (codice ' + r.status + ').' }; }); })
      .then(function (j) {
        if (j.errore) { stato(s, j.errore, 'errore'); return; }
        var c = s.querySelector('.ec-cornice'); if (c) c.srcdoc = j.html;
        var o = s.querySelector('.ec-oggetto-testo'); if (o) o.textContent = j.oggetto;
        if (!zitto || j.messaggio) stato(s, j.messaggio || 'Anteprima aggiornata.', j.messaggio ? 'fatto' : '');
      })
      .catch(function () { stato(s, 'Il server non risponde: controlla la connessione e riprova.', 'errore'); })
      .then(function () { if (bottone) bottone.disabled = false; });
  }
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-ec-anteprima],[data-ec-prova],[data-ec-riempi]');
    var s = b && scatola(b); if (!s) return;
    ev.preventDefault();
    if (b.hasAttribute('data-ec-riempi')) {
      var n = 0;
      s.querySelectorAll('[data-ec-eredita]').forEach(function (el) { if (el.value.trim() === '' && el.getAttribute('data-ec-eredita') !== '') { el.value = el.getAttribute('data-ec-eredita'); n++; } });
      stato(s, n ? 'Ho riempito ' + n + (n === 1 ? ' campo' : ' campi') + ' con i testi in uso: cambiali e salva. Quelli che lasci uguali continuano a seguire i testi in uso.' : 'Non c\'era nessun campo vuoto da riempire.', 'fatto');
      return;
    }
    chiedi(s, b.hasAttribute('data-ec-prova') ? 'prova' : 'anteprima', b);
  });
  // Il selettore del colore scrive nella casella accanto, e la casella (se è un colore) sposta il selettore.
  document.addEventListener('input', function (ev) {
    var el = ev.target;
    if (el.hasAttribute && el.hasAttribute('data-ec-colore')) { var t = document.getElementById(el.getAttribute('data-ec-colore')); if (t) t.value = el.value; }
    else if (el.id === 'ec-colore' && /^#[0-9a-f]{6}$/i.test(el.value)) { var c = document.querySelector('[data-ec-colore="ec-colore"]'); if (c) c.value = el.value.toLowerCase(); }
  });
  function primaVolta() { document.querySelectorAll('[data-email-conferma]').forEach(function (s) { if (s.querySelector('.ec-cornice')) chiedi(s, 'anteprima', null, true); }); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', primaVolta); else primaVolta();
})();
