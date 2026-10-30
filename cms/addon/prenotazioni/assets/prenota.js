// Addon Prenotazioni: il calendario dei posti liberi del widget "Prenota" e della pagina per spostare un appuntamento.
// Chiede i giorni e gli orari a /prenotazione/posti/ (li calcola il server, nel fuso del sito) e scrive la scelta nei
// campi nascosti giorno e ora. Il modulo si invia normalmente; il captcha lo fa sito/assets/moduli.js.
(function () {
  "use strict";
  if (window.prenJsPronto) return;
  window.prenJsPronto = true;

  function el(tag, cls, testo) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (testo !== undefined) e.textContent = testo;
    return e;
  }

  function avvia(box) {
    var form = box.querySelector("form");
    var url = box.getAttribute("data-pren");
    var servizio = form.querySelector("[name=servizio]");
    var risorsa = form.querySelector("[name=risorsa]");
    var campoGiorno = form.querySelector("[name=giorno]");
    var campoOra = form.querySelector("[name=ora]");
    var giorni = box.querySelector(".pren-giorni");
    var orari = box.querySelector(".pren-orari");
    var mese = box.querySelector(".pren-mese");
    var prima = box.querySelector(".pren-prima");
    var dopo = box.querySelector(".pren-dopo");
    var riepilogo = box.querySelector(".pren-riepilogo");
    var stato = { da: "", dati: null, giornoTesto: "" };
    var richiesta = 0;

    function nota(testo) {
      orari.textContent = "";
      orari.appendChild(el("p", "pren-nota", testo));
    }
    function scegliOra(ora, bottone) {
      campoOra.value = ora;
      orari.querySelectorAll("button").forEach(function (b) { b.setAttribute("aria-pressed", b === bottone ? "true" : "false"); });
      var nomeServ = stato.dati ? stato.dati.servizio.nome : "";
      riepilogo.textContent = "Hai scelto: " + nomeServ + ", " + stato.giornoTesto + " alle " + ora + (bottone && bottone.dataset.con ? " con " + bottone.dataset.con : "") + ".";
      riepilogo.hidden = false;
    }
    function pulisciScelta() {
      campoOra.value = "";
      riepilogo.hidden = true;
    }

    function carica(giorno) {
      if (!servizio.value) { giorni.textContent = ""; mese.textContent = ""; nota("Scegli prima il servizio."); pulisciScelta(); return; }
      var q = "s=" + encodeURIComponent(servizio.value) + "&r=" + encodeURIComponent(risorsa ? risorsa.value || "0" : "0");
      if (stato.da) q += "&da=" + stato.da;
      if (giorno) q += "&d=" + giorno;
      var p = form.querySelector("[name=p]"), t = form.querySelector("[name=t]");
      if (p && t) q += "&p=" + encodeURIComponent(p.value) + "&t=" + encodeURIComponent(t.value);
      var mia = ++richiesta;
      nota("Cerco gli orari liberi…");
      fetch(url + "?" + q, { cache: "no-store", credentials: "same-origin" })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (mia === richiesta) disegna(d); })
        .catch(function () { if (mia === richiesta) nota("Non riesco a leggere gli orari. Ricarica la pagina o telefonaci."); });
    }

    function aggiornaPersone(persone) {
      if (!risorsa || risorsa.tagName !== "SELECT") return;
      var scelta = risorsa.value;
      var ids = persone.map(function (x) { return String(x.id); });
      risorsa.querySelectorAll("option").forEach(function (o) { if (o.value !== "0") o.hidden = ids.indexOf(o.value) < 0; });
      if (scelta !== "0" && ids.indexOf(scelta) < 0) risorsa.value = "0";
      risorsa.closest("label").hidden = persone.length < 2;
    }

    function disegna(d) {
      if (d.errore) { nota("Questo servizio non si può prenotare dal sito."); return; }
      stato.dati = d;
      stato.da = d.da;
      aggiornaPersone(d.persone || []);
      giorni.textContent = "";
      var mesi = [];
      d.giorni.forEach(function (g) {
        if (mesi.indexOf(g.mese) < 0) mesi.push(g.mese);
        var b = el("button", "pren-giorno");
        b.type = "button";
        b.dataset.d = g.d;
        b.disabled = g.n === 0;
        b.setAttribute("aria-pressed", g.d === d.giorno ? "true" : "false");
        b.setAttribute("aria-label", g.g + " " + g.num + " " + g.mese + (g.n ? ", " + g.n + (g.n === 1 ? " orario libero" : " orari liberi") : ", pieno o chiuso"));
        b.appendChild(el("span", "pren-g", g.g));
        b.appendChild(el("b", "", String(g.num)));
        b.addEventListener("click", function () { carica(g.d); });
        giorni.appendChild(b);
      });
      mese.textContent = mesi.join(" - ");
      prima.disabled = d.da <= d.oggi;
      dopo.disabled = d.giorni.length && d.giorni[d.giorni.length - 1].d >= d.ultimo;
      campoGiorno.value = d.giorno || "";
      stato.giornoTesto = d.giorno_testo || "";
      pulisciScelta();
      orari.textContent = "";
      if (!d.giorno) {
        nota(d.prossimo ? "In questi giorni non ci sono orari liberi. Vai avanti con la freccia: il primo libero è il " + d.prossimo.split("-").reverse().join("/") + "." : "In questi giorni non ci sono orari liberi.");
        return;
      }
      if (!d.posti.length) { nota("Questo giorno è pieno. Scegline un altro."); return; }
      orari.appendChild(el("p", "pren-nota pren-titolo-giorno", d.giorno_testo.charAt(0).toUpperCase() + d.giorno_testo.slice(1)));
      var griglia = el("div", "pren-griglia-ore");
      d.posti.forEach(function (x) {
        var b = el("button", "pren-ora", x.ora);
        b.type = "button";
        b.setAttribute("aria-pressed", "false");
        if (risorsa && risorsa.value !== "0" && x.con.length) b.dataset.con = x.con[0];
        b.addEventListener("click", function () { scegliOra(x.ora, b); });
        griglia.appendChild(b);
      });
      orari.appendChild(griglia);
    }

    function sposta(passo) {
      if (!stato.dati) return;
      var g = new Date(stato.da + "T12:00:00");
      g.setDate(g.getDate() + passo);
      var due = function (n) { return (n < 10 ? "0" : "") + n; };
      stato.da = g.getFullYear() + "-" + due(g.getMonth() + 1) + "-" + due(g.getDate());
      carica("");
    }
    prima.addEventListener("click", function () { sposta(-14); });
    dopo.addEventListener("click", function () { sposta(14); });
    servizio.addEventListener("change", function () { stato.da = ""; carica(""); });
    if (risorsa && risorsa.tagName === "SELECT") risorsa.addEventListener("change", function () { carica(campoGiorno.value); });

    // Senza un orario scelto il modulo non parte (prima del captcha: fase di cattura).
    form.addEventListener("submit", function (e) {
      if (campoOra.value && campoGiorno.value) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      riepilogo.hidden = false;
      riepilogo.textContent = "Scegli il giorno e l'ora prima di prenotare.";
      orari.scrollIntoView({ block: "center" });
    }, true);

    carica("");
  }

  function tutti() { document.querySelectorAll("[data-pren]").forEach(avvia); }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", tutti); else tutti();
})();
