// Area riservata (addon membri): il captcha del modulo Iscriviti, lo stesso dei moduli del sito (api/captcha.php e
// includes/moduli.php). Alla spunta di "Sono una persona" il browser chiede una sfida e cerca il numero che la risolve.
// Qui niente sessionStorage: il modulo ha le password, e non devono restare nel browser.
(function () {
  var enc = new TextEncoder();
  var hex = function (buf) { return Array.prototype.map.call(new Uint8Array(buf), function (b) { return ("0" + b.toString(16)).slice(-2); }).join(""); };
  function risolvi(s) {
    var n = 0;
    function giro() {
      var lotto = [];
      for (var i = 0; i < 400 && n + i <= s.max; i++) lotto.push(n + i);
      if (!lotto.length) return Promise.reject(new Error("nessuna soluzione"));
      return Promise.all(lotto.map(function (k) {
        return crypto.subtle.digest("SHA-256", enc.encode(s.sale + k)).then(function (d) { return hex(d) === s.sfida ? k : -1; });
      })).then(function (r) {
        var t = r.filter(function (k) { return k >= 0; })[0];
        if (t !== undefined) return t;
        n += lotto.length;
        return giro();
      });
    }
    return giro();
  }
  document.querySelectorAll("form[data-membri-captcha]").forEach(function (form) {
    var casella = form.querySelector(".captcha input[type=checkbox]");
    var stato = form.querySelector(".captcha-stato");
    var campo = form.querySelector("input[name=captcha]");
    var pronto = false;
    if (!casella || !campo) return;
    if (!window.crypto || !crypto.subtle) { stato.textContent = "Il browser è troppo vecchio per la verifica: aggiornalo e riprova."; return; }
    casella.addEventListener("change", function () {
      if (!casella.checked) { pronto = false; campo.value = ""; stato.textContent = ""; return; }
      casella.disabled = true;
      stato.textContent = "Verifica in corso…";
      var base = (document.querySelector("meta[name=cms-base]") || {}).content || "";
      fetch(base + "/api/captcha.php", { cache: "no-store" })
        .then(function (r) { return r.json(); })
        .then(function (s) { return risolvi(s).then(function (n) { s.n = n; campo.value = btoa(JSON.stringify(s)); pronto = true; stato.textContent = "Verificato"; }); })
        .catch(function () { casella.checked = false; stato.textContent = "La verifica non è riuscita. Riprova."; })
        .then(function () { casella.disabled = false; });
    });
    form.addEventListener("submit", function (e) {
      if (!pronto) { e.preventDefault(); stato.textContent = "Spunta \"Sono una persona\" e aspetta il segno verde."; casella.focus(); }
    });
  });
})();
