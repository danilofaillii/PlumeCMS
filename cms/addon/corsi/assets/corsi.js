// Corsi online (addon corsi): il pulsante "Stampa o salva in PDF" del certificato. Niente gestori in linea (CSP a nonce).
(function () {
  document.querySelectorAll("[data-corsi-stampa]").forEach(function (b) {
    b.addEventListener("click", function () { window.print(); });
  });
})();
