/*
 * 2.38.0: la scheda dell'immagine (admin/includes/media-scheda.php) e Media > Dati delle immagini. Il conteggio dei caratteri
 * dell'alt con il consiglio (da 5 a 125, niente "immagine di"). Niente gestori in linea.
 */
(function () {
  function aggiorna(c, out) {
    var lim = (c.getAttribute('data-ms-conta') || '5,125').split(',');
    var min = +lim[0], max = +lim[1];
    var v = c.value.trim(), n = v.length;
    var msg = n + (n === 1 ? ' carattere' : ' caratteri');
    var fuori = false;
    if (n === 0) msg += ': scrivi cosa si vede nella foto.';
    else if (n < min) { msg += ': un po\' corto, aggiungi un dettaglio.'; fuori = true; }
    else if (n > max) { msg += ': lungo, oltre ' + max + ' i lettori di schermo lo leggono tutto; il resto va nella descrizione.'; fuori = true; }
    else msg += ': lunghezza giusta.';
    if (/^\s*(immagine|foto|fotografia|image|picture)\s+(di|del|della|dei|delle|of)\b/i.test(v)) { msg += ' Togli "' + v.split(/\s+/).slice(0, 2).join(' ') + '": si capisce già che è un\'immagine.'; fuori = true; }
    out.textContent = msg;
    out.classList.toggle('ms-fuori', fuori);
  }
  Array.prototype.forEach.call(document.querySelectorAll('[data-ms-conta]'), function (c) {
    var out = document.querySelector('[data-ms-conta-per="' + c.id + '"]');
    if (!out) { out = document.createElement('div'); out.className = 'ms-conta'; out.setAttribute('aria-live', 'polite'); c.parentNode.insertBefore(out, c.nextSibling); }
    aggiorna(c, out);
    c.addEventListener('input', function () { aggiorna(c, out); });
  });
})();
