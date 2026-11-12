/*
 * Campi protetti. Il firewall di molti hosting cPanel (ModSecurity) legge ogni POST e, se trova HTML, JSON o SQL
 * in un campo, risponde 403 senza spiegare niente: il salvataggio di un articolo, di una campagna, di un flusso o
 * di un form "non funziona". Qui, un attimo prima dell'invio, i campi lunghi (textarea e campi nascosti) con dentro
 * HTML, JSON o virgolette diventano base64 con il prefisso ~b64~; admin/includes/auth.php li rimette in chiaro.
 * I campi di testo semplici (titolo, nome) non si toccano. Senza JavaScript tutto funziona come prima.
 */
(function () {
  var MARCA = '~b64~';
  function serve(v) { return typeof v === 'string' && v !== '' && /[<>{}\[\]"'`;\\]/.test(v); }
  function base64(s) {
    if (window.TextEncoder) {
      var b = new TextEncoder().encode(s), t = '';
      for (var i = 0; i < b.length; i += 0x8000) t += String.fromCharCode.apply(null, b.subarray(i, i + 0x8000));
      return btoa(t);
    }
    return btoa(unescape(encodeURIComponent(s)));
  }
  function codifica(v) { return serve(v) ? MARCA + base64(v) : v; }
  function spiega(status) {
    if (status === 403 || status === 406 || status === 501) return 'Il server ha rifiutato il salvataggio (codice ' + status + '): di solito è il firewall dell\'hosting (ModSecurity) che scambia il testo per un attacco. Niente è andato perso: riprova, e se succede ancora manda a chi gestisce il server l\'ora e il codice.';
    if (status === 413) return 'Il contenuto è troppo grande per il limite del server (codice 413, post_max_size). Alleggerisci le immagini o chiedi di alzare il limite.';
    if (status >= 500) return 'Il server ha risposto con un errore (codice ' + status + '). Il dettaglio sta nel log degli errori del server o in new/logs/php-errori.log.';
    return 'Risposta del server non leggibile (codice ' + status + ').';
  }
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (e.defaultPrevented || !f || f.tagName !== 'FORM' || String(f.method).toLowerCase() !== 'post' || f.hasAttribute('data-senza-protezione')) return;
    var ripristina = [];
    Array.prototype.forEach.call(f.elements, function (el) {
      if (!el.name || el.disabled || el.name === '_csrf') return;
      if (el.tagName !== 'TEXTAREA' && !(el.tagName === 'INPUT' && el.type === 'hidden')) return;
      var c = codifica(el.value);
      if (c !== el.value) { ripristina.push([el, el.value]); el.value = c; }
    });
    // I dati del modulo sono già stati raccolti: la pagina torna com'era, nel caso resti aperta (per esempio tornando indietro).
    if (ripristina.length) setTimeout(function () { ripristina.forEach(function (r) { r[0].value = r[1]; }); }, 0);
  });
  window.campiProtetti = { codifica: codifica, spiega: spiega, MARCA: MARCA };
})();
