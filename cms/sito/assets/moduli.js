// Captcha dei moduli: alla spunta di "Sono una persona" il browser chiede una sfida al server
// e cerca il numero che la risolve (sha256). Mezzo secondo per una persona, troppo costoso per mille invii.
(function () {
  // Lo script può stare due volte nella pagina (un modulo in pagina e uno nel popup): lavora una volta sola.
  if (window.moduliJsPronto) return;
  window.moduliJsPronto = true;
  var hex = function (buf) {
    return Array.prototype.map.call(new Uint8Array(buf), function (b) { return ("0" + b.toString(16)).slice(-2); }).join("");
  };
  var enc = new TextEncoder();

  function risolvi(s) {
    var n = 0;
    function giro() {
      // A blocchi, così la pagina resta reattiva.
      var lotto = [];
      for (var i = 0; i < 400 && n + i <= s.max; i++) lotto.push(n + i);
      if (!lotto.length) return Promise.reject(new Error("nessuna soluzione"));
      return Promise.all(lotto.map(function (k) {
        return crypto.subtle.digest("SHA-256", enc.encode(s.sale + k)).then(function (d) { return hex(d) === s.sfida ? k : -1; });
      })).then(function (r) {
        var trovato = r.filter(function (k) { return k >= 0; })[0];
        if (trovato !== undefined) return trovato;
        n += lotto.length;
        return giro();
      });
    }
    return giro();
  }

  // Quello che la persona ha scritto resta nel browser fino alla risposta: se il server rimanda indietro il
  // modulo con un errore, i campi si ritrovano compilati e lei corregge solo quello segnato.
  // Una chiave per modulo e per posto: lo stesso form può stare in pagina e dentro un popup.
  var chiave = function (form) { var m = form.querySelector("input[name=modulo]"), pp = form.querySelector("input[name=popup]"); return "modulo-" + (m ? m.value : "") + "-" + (pp ? "popup" + pp.value : "pagina") + "-" + location.pathname; };
  function conserva(form) {
    var v = {};
    form.querySelectorAll("input[name], select[name], textarea[name]").forEach(function (c) {
      if (["modulo", "pagina", "captcha", "sito_web", "privacy", "popup"].indexOf(c.name) >= 0 || c.type === "hidden" || c.type === "file") return; // un file non si rimette (2.1)
      if (c.type === "checkbox") { if (c.checked) v[c.name] = (v[c.name] || []).concat([c.value]); }
      else v[c.name] = c.value;
    });
    try { sessionStorage.setItem(chiave(form), JSON.stringify(v)); } catch (e) {}
  }
  function ripristina(form) {
    var v = null;
    try { v = JSON.parse(sessionStorage.getItem(chiave(form)) || "null"); } catch (e) {}
    if (!v) return;
    form.querySelectorAll("input[name], select[name], textarea[name]").forEach(function (c) {
      if (!(c.name in v) || c.type === "hidden" || c.type === "file") return;
      if (c.type === "checkbox") c.checked = v[c.name].indexOf(c.value) >= 0;
      else c.value = v[c.name];
    });
    // Il fuoco sul campo segnato, dopo che il browser ha finito di andare all'ancora.
    var errato = form.querySelector(".errato input, .errato select, .errato textarea");
    if (errato) setTimeout(function () { errato.focus(); }, 100);
  }
  var conErrore = /[?&]modulo=/.test(location.search);

  document.querySelectorAll("form[data-captcha]").forEach(function (form) {
    if (conErrore) ripristina(form);
    try { sessionStorage.removeItem(chiave(form)); } catch (e) {}
    var casella = form.querySelector(".captcha input[type=checkbox]");
    var stato = form.querySelector(".captcha-stato");
    var campo = form.querySelector("input[name=captcha]");
    var invia = form.querySelector("button[type=submit]");
    var pronto = false;

    if (!window.crypto || !crypto.subtle) {
      stato.textContent = "Il browser è troppo vecchio per la verifica: aggiornalo e riprova.";
      return;
    }

    casella.addEventListener("change", function () {
      if (!casella.checked) { pronto = false; campo.value = ""; stato.textContent = ""; form.classList.remove("verificato"); return; }
      casella.disabled = true;
      stato.textContent = "Verifica in corso…";
      fetch(((document.querySelector("meta[name=cms-base]") || {}).content || "") + "/api/captcha.php", { cache: "no-store" })
        .then(function (r) { return r.json(); })
        .then(function (s) {
          return risolvi(s).then(function (n) {
            s.n = n;
            campo.value = btoa(JSON.stringify(s));
            pronto = true;
            form.classList.add("verificato");
            stato.textContent = "Verificato";
          });
        })
        .catch(function () {
          casella.checked = false;
          stato.textContent = "La verifica non è riuscita. Riprova.";
        })
        .then(function () { casella.disabled = false; });
    });

    form.addEventListener("submit", function (e) {
      if (!pronto) {
        e.preventDefault();
        stato.textContent = 'Spunta "Sono una persona" e aspetta il segno verde.';
        casella.focus();
        return;
      }
      conserva(form);
      invia.disabled = true;
      invia.textContent = "Invio…";
    });
  });
})();
