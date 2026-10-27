// Un test costruito dalla sua definizione, scritta nella pagina in <script type="application/json" id="test-def">.
// Invia a /new/api/test.php. Le risposte restano sul server solo con il consenso alla ricerca; l'email del report non si salva.
(function () {
  var box = document.getElementById("test");
  var fonte = document.getElementById("test-def");
  if (!box || !fonte) return;
  var def = JSON.parse(fonte.textContent);
  var PER_PASSO = 7;
  var NOMI = { eta: "Età", genere: "Genere", settore: "Settore", ruolo: "Ruolo", esperienza: "Anni di lavoro", dimensione_azienda: "Persone in azienda", regione: "Regione" };
  var passi = [];
  for (var i = 0; i < def.domande.length; i += PER_PASSO) passi.push(def.domande.slice(i, i + PER_PASSO));
  var valori = [];
  for (var v = def.scala.min; v <= def.scala.max; v++) valori.push(v);

  var stato = { passo: -1, risposte: {}, profilo: {}, ricerca: false, risultato: null, consensoRisultato: false, errore: "", invio: false };

  function el(tag, attr, figli) {
    var n = document.createElement(tag);
    for (var k in attr || {}) {
      if (k === "testo") n.textContent = attr[k];
      else if (k.slice(0, 2) === "on") n.addEventListener(k.slice(2), attr[k]);
      else if (attr[k] !== false && attr[k] != null) n.setAttribute(k, attr[k] === true ? "" : attr[k]);
    }
    (figli || []).forEach(function (f) { if (f) n.append(f); });
    return n;
  }

  function vai(n) {
    stato.passo = n;
    disegna();
    box.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function date() { return Object.keys(stato.risposte).length; }

  function avanzamento() {
    var barra = el("div");
    barra.style.width = (date() / def.domande.length) * 100 + "%";
    return el("div", { class: "test-avanzamento", "aria-hidden": "true" }, [barra, el("span", { testo: date() + " di " + def.domande.length })]);
  }

  function introduzione() {
    return el("div", { class: "test-carta" }, [
      el("span", { class: "occhiello", testo: def.domande.length + " domande · 3 minuti" }),
      el("h2", { testo: "Come stai, davvero, al lavoro?" }),
      el("p", { testo: "Rispondi pensando alle ultime settimane. Conta la tua situazione di oggi, così com'è." }),
      el("button", { class: "btn btn-viola", type: "button", testo: "Inizia il test", onclick: function () { vai(0); } }),
      el("p", { class: "test-nota", testo: def.strumento + " Descrive come stai: la diagnosi resta al medico." }),
    ]);
  }

  function domande() {
    var gruppo = passi[stato.passo];
    var completo = gruppo.every(function (q) { return stato.risposte[q.codice] !== undefined; });
    var carta = el("div", { class: "test-carta" });
    var avanti;
    // Una risposta aggiorna solo quello che cambia: la pagina non si ridisegna e il fuoco resta sulla domanda.
    function scelta(q, v, fs) {
      stato.risposte[q.codice] = v;
      fs.className = "test-domanda fatta";
      var vecchia = box.querySelector(".test-avanzamento");
      if (vecchia) vecchia.replaceWith(avanzamento());
      avanti.disabled = !gruppo.every(function (x) { return stato.risposte[x.codice] !== undefined; });
    }
    gruppo.forEach(function (q, i) {
      var fs;
      var scala = el("div", { class: "test-scala" }, valori.map(function (v) {
        return el("label", {}, [
          el("input", { type: "radio", name: q.codice, value: v, checked: stato.risposte[q.codice] === v,
            onchange: function () { scelta(q, v, fs); } }),
          el("span", { testo: String(v) }),
        ]);
      }));
      carta.append(fs = el("fieldset", { class: "test-domanda" + (stato.risposte[q.codice] !== undefined ? " fatta" : "") }, [
        el("legend", {}, [el("span", { testo: String(stato.passo * PER_PASSO + i + 1) }), document.createTextNode(q.testo)]),
        scala,
        el("div", { class: "test-estremi" }, [el("span", { testo: q.etichetta_min }), el("span", { testo: q.etichetta_max })]),
      ]));
    });
    carta.append(el("div", { class: "test-azioni" }, [
      stato.passo > 0 && el("button", { class: "btn btn-filo", type: "button", testo: "Indietro", onclick: function () { vai(stato.passo - 1); } }),
      avanti = el("button", { class: "btn btn-viola", type: "button", disabled: !completo, testo: stato.passo === passi.length - 1 ? "Ultimo passo" : "Avanti",
        onclick: function () { vai(stato.passo + 1); } }),
    ]));
    return carta;
  }

  function profilo() {
    var campi = el("div", { class: "test-profilo" });
    Object.keys(def.demografia).forEach(function (campo) {
      var sel = el("select", { onchange: function (e) { stato.profilo[campo] = e.target.value; } }, [el("option", { value: "", testo: "Preferisco non dirlo" })]
        .concat(def.demografia[campo].map(function (v) { return el("option", { value: v, selected: stato.profilo[campo] === v, testo: v }); })));
      campi.append(el("label", {}, [el("span", { testo: NOMI[campo] || campo }), sel]));
    });
    var vedi = el("button", { class: "btn btn-viola", type: "button", disabled: !stato.consensoRisultato || stato.invio, testo: stato.invio ? "Calcolo…" : "Vedi il risultato", onclick: invia });
    function consenso(chiave, testo, nota) {
      return el("label", { class: "test-consenso" }, [
        el("input", { type: "checkbox", checked: stato[chiave], onchange: function (e) { stato[chiave] = e.target.checked; vedi.disabled = !stato.consensoRisultato || stato.invio; } }),
        el("span", {}, [document.createTextNode(testo + " "), el("em", { testo: nota })]),
      ]);
    }
    var nota = el("p", { class: "test-nota" });
    nota.append("Dettagli nell'", el("a", { href: "/privacy-policy/", testo: "informativa privacy" }),
      ". Senza il secondo consenso il risultato si calcola, si mostra e le risposte si cancellano subito.");
    return el("div", { class: "test-carta" }, [
      el("h2", { testo: "Ultimo passo" }),
      el("p", { testo: "Il profilo è facoltativo. Se lo compili, il tuo risultato entra nelle statistiche per età, settore e territorio." }),
      campi,
      consenso("consensoRisultato", def.consenso.risultato, "(necessario)"),
      consenso("ricerca", def.consenso.ricerca, "(facoltativo)"),
      nota,
      stato.errore && el("p", { class: "test-errore", role: "alert", testo: stato.errore }),
      el("div", { class: "test-azioni" }, [el("button", { class: "btn btn-filo", type: "button", testo: "Indietro", onclick: function () { vai(stato.passo - 1); } }), vedi]),
    ]);
  }

  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (c) { var r = (Math.random() * 16) | 0; return (c === "x" ? r : (r & 3) | 8).toString(16); });
  }

  function chiama(corpo) {
    return fetch(((document.querySelector("meta[name=cms-base]") || {}).content || "/new") + "/api/test.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(corpo) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, j: j }; }); });
  }

  function invia() {
    stato.invio = true; stato.errore = ""; disegna();
    chiama({
      azione: "invia", slug: def.slug, versione: def.versione, uuid: uuid(), risposte: stato.risposte, profilo: stato.profilo,
      consenso_risultato: stato.consensoRisultato, consenso_ricerca: stato.ricerca, consenso_versione: def.consenso.versione,
      canale: new URLSearchParams(location.search).get("utm_source") || "",
    }).then(function (x) {
      stato.invio = false;
      if (!x.ok) { stato.errore = x.j.errore || "Qualcosa non ha funzionato."; disegna(); return; }
      stato.risultato = x.j;
      vai(passi.length + 1);
    }).catch(function () { stato.invio = false; stato.errore = "La connessione si è fermata. Riprova tra un momento."; disegna(); });
  }

  function risultato() {
    var r = stato.risultato;
    var carta = el("div", { class: "test-carta" }, [
      el("span", { class: "occhiello", testo: "Il tuo risultato" }),
      el("div", { class: "test-totale" }, [document.createTextNode(String(r.totale)), el("small", { testo: "/" + r.massimo })]),
    ]);
    var livello = el("span", { class: "test-livello", testo: r.livello.etichetta });
    livello.style.background = r.livello.colore;
    carta.append(livello, el("p", { testo: r.livello.testo }));
    r.dimensioni.forEach(function (d) {
      var piena = el("div");
      piena.style.width = (d.punteggio / d.massimo) * 100 + "%";
      piena.style.background = r.livello.colore;
      carta.append(el("div", { class: "test-dimensione" }, [
        el("div", {}, [el("b", { testo: d.nome }), el("span", { testo: d.punteggio + "/" + d.massimo })]),
        el("div", { class: "test-barra" }, [piena]),
        el("p", { testo: d.testo }),
      ]));
    });
    carta.append(el("a", { class: "btn btn-viola", href: "/laser-coaching/", testo: "Parliamone in 15 minuti" }));
    var esito = el("p");
    var email = el("input", { id: "email-report", type: "email", required: true, placeholder: "nome@esempio.it" });
    var manda = el("button", { class: "btn btn-filo", type: "submit", testo: "Mandamelo" });
    carta.append(el("form", { class: "test-report", onsubmit: function (e) {
      e.preventDefault();
      manda.disabled = true;
      chiama({ azione: "report", slug: def.slug, versione: def.versione, risposte: stato.risposte, email: email.value }).then(function (x) {
        esito.className = x.ok ? "" : "test-errore";
        esito.textContent = x.ok ? "Inviato. Se non lo trovi, guarda nello spam." : "L'invio si è fermato. Riprova tra qualche minuto.";
        manda.disabled = x.ok;
      });
    } }, [
      el("label", { for: "email-report", testo: "Vuoi il risultato via email? L'indirizzo serve solo per spedirlo e non viene salvato." }),
      el("div", {}, [email, manda]),
      esito,
    ]));
    carta.append(el("p", { class: "test-nota", testo: "Questo test descrive come stai oggi, non è una diagnosi. Se i segnali sono forti, parlane con il tuo medico." }));
    return carta;
  }

  function disegna() {
    box.textContent = "";
    var p = stato.passo;
    if (p >= 0 && p <= passi.length) box.append(avanzamento());
    if (p === -1) box.append(introduzione());
    else if (p < passi.length) box.append(domande());
    else if (p === passi.length) box.append(profilo());
    else if (stato.risultato) box.append(risultato());
  }

  disegna();
})();
