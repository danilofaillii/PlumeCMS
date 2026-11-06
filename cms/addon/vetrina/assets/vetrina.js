// Vetrina (addon, 2.0.0): piccoli aiuti nel browser. Senza questo script il negozio funziona lo stesso:
// i prezzi e i totali li decide sempre il server, qui si cambia solo quello che si vede.
(function () {
  if (window.vetrinaJsPronta) return;
  window.vetrinaJsPronta = true;

  // Ordina: la tendina manda il modulo da sola.
  document.querySelectorAll("select[data-vt-invia]").forEach(function (s) {
    s.addEventListener("change", function () { if (s.form) s.form.submit(); });
  });

  // Galleria: una miniatura cambia la foto grande.
  var grande = document.querySelector("[data-vt-principale]");
  document.querySelectorAll("[data-vt-foto]").forEach(function (a) {
    a.addEventListener("click", function (e) {
      if (!grande) return;
      e.preventDefault();
      grande.src = a.getAttribute("data-vt-foto");
      document.querySelectorAll("[data-vt-foto]").forEach(function (x) { x.removeAttribute("aria-current"); });
      a.setAttribute("aria-current", "true");
    });
  });

  // Variante: il prezzo mostrato segue la variante scelta.
  var prezzo = document.querySelector("[data-vt-prezzo]");
  document.querySelectorAll("select[data-vt-variante]").forEach(function (s) {
    var prima = prezzo ? prezzo.innerHTML : "";
    s.addEventListener("change", function () {
      var o = s.options[s.selectedIndex];
      if (!prezzo) return;
      if (o && o.getAttribute("data-prezzo")) {
        prezzo.textContent = "";
        var b = document.createElement("b");
        b.textContent = o.getAttribute("data-prezzo");
        var sp = document.createElement("span");
        sp.className = "vt-prezzo";
        sp.appendChild(b);
        prezzo.appendChild(sp);
      } else prezzo.innerHTML = prima;
    });
  });

  // Consegna: con il ritiro in negozio l'indirizzo non serve, e i totali cambiano.
  var radio = document.querySelectorAll("input[data-vt-consegna]");
  function consegna() {
    var scelta = "";
    radio.forEach(function (r) { if (r.checked) scelta = r.value; });
    var ind = document.querySelector("[data-vt-indirizzo]");
    if (ind) ind.hidden = scelta === "ritiro";
    document.querySelectorAll("[data-vt-totali]").forEach(function (t) { t.hidden = t.getAttribute("data-vt-totali") !== scelta; });
  }
  radio.forEach(function (r) { r.addEventListener("change", consegna); });
  if (radio.length) consegna();
})();
