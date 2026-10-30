// Copia del sito altrove (addon copia-altrove): nella pagina del pannello si vedono solo i campi del tipo scelto
// (server e porta per FTP, FTPS e SFTP; l'indirizzo per WebDAV). Senza JavaScript si vedono tutti, e funziona lo stesso.
(function () {
  var tipo = document.querySelector("[data-ca-tipo]");
  if (!tipo) return;
  function mostra() {
    document.querySelectorAll("[data-ca-solo]").forEach(function (el) {
      el.hidden = el.getAttribute("data-ca-solo").split(" ").indexOf(tipo.value) < 0;
    });
  }
  tipo.addEventListener("change", mostra);
  mostra();
})();
