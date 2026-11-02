// 2.10.0: il costruttore delle condizioni dei segmenti (admin/newsletter-segmenti.php, "Aggiungi filtro" in
// admin/newsletter-contatti.php; dalla 2.15.0 le divisioni delle automazioni, window.nlsMonta). Niente gestori in linea (la CSP a nonce li vieterebbe): attributi data-*.
//   data-nls-editor="#campo"   il costruttore: legge e scrive le regole (JSON in base64) nel campo nascosto indicato.
//                              Gruppi di condizioni uniti da E/O, dentro ogni gruppo E/O; al massimo 100 condizioni.
//   data-nls-conta="pagina"    conta i contatti a ogni cambio (POST azione=conta) e scrive in [data-nls-numero]
//   data-nls-copia="#campo"    campo nascosto di un altro form (Salva come segmento): prende il valore al momento dell'invio
// La definizione (campi, operatori, liste, segmenti, campagne, form, motivi) arriva in <script type="application/json" id="nls-def">.
(function () {
  'use strict';
  var defEl = document.getElementById('nls-def');
  if (!defEl) return;
  var DEF = JSON.parse(defEl.textContent || '{}');
  var uid = 0;
  function h(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function b64(o) { var s = btoa(unescape(encodeURIComponent(JSON.stringify(o)))); return s.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
  function deB64(s) {
    if (!s) return null;
    try { s = s.replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) s += '='; return JSON.parse(decodeURIComponent(escape(atob(s)))); } catch (e) { return null; }
  }
  function tipoDi(campo) { return (DEF.campi[campo] || {}).t || ''; }
  function opzioni(obj, scelto) { return Object.keys(obj).map(function (k) { return '<option value="' + h(k) + '"' + (String(scelto) === String(k) ? ' selected' : '') + '>' + h(obj[k]) + '</option>'; }).join(''); }
  function caselle(nome, obj, scelti, etichetta) {
    var ks = Object.keys(obj);
    if (!ks.length) return '<p class="hint">' + h(etichetta === 'liste' ? 'Ancora nessuna lista: creala nella scheda Liste.' : 'Nessuna opzione.') + '</p>';
    return '<fieldset class="nls-caselle"><legend class="sr-solo">' + h(etichetta) + '</legend>' + ks.map(function (k) {
      var on = (scelti || []).map(String).indexOf(String(k)) >= 0;
      return '<label><input type="checkbox" data-k="' + nome + '" value="' + h(k) + '"' + (on ? ' checked' : '') + '> ' + h(obj[k]) + '</label>';
    }).join('') + '</fieldset>';
  }
  function nuovaCondizione() { return { campo: 'stato', op: 'iscritto' }; }

  function Editor(box) {
    var campo = document.querySelector(box.getAttribute('data-nls-editor'));
    var regole = deB64(campo.value) || { op: 'e', gruppi: [] };
    if (!regole.gruppi || !regole.gruppi.length) regole.gruppi = [{ op: 'e', condizioni: [] }];
    var numero = document.querySelector('[data-nls-numero]');
    var timer = null, giro = 0;

    function quante() { return regole.gruppi.reduce(function (t, g) { return t + g.condizioni.length; }, 0); }
    function salva() {
      var pulite = { op: regole.op === 'o' ? 'o' : 'e', gruppi: regole.gruppi.filter(function (g) { return g.condizioni.length; }) };
      campo.value = pulite.gruppi.length ? b64(pulite) : '';
      if (box.nlsCambio) box.nlsCambio(campo.value); // 2.15.0: la divisione delle automazioni legge le regole a ogni cambio
      conta();
    }
    function conta() {
      if (!numero || !box.getAttribute('data-nls-conta')) return;
      clearTimeout(timer);
      timer = setTimeout(function () {
        var mio = ++giro, d = new FormData();
        d.append('azione', 'conta'); d.append('_csrf', box.getAttribute('data-csrf')); d.append('regole', campo.value); d.append('id', box.getAttribute('data-nls-id') || '0');
        numero.textContent = 'Conto i contatti...';
        fetch(box.getAttribute('data-nls-conta'), { method: 'POST', body: d, headers: { 'X-CSRF': box.getAttribute('data-csrf'), 'Accept': 'application/json' }, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            if (mio !== giro) return;
            numero.classList.toggle('nls-errore', !!j.errore);
            numero.textContent = j.errore ? j.errore : j.testo;
          })
          .catch(function () { if (mio === giro) numero.textContent = 'Il conteggio non è arrivato: controlla la connessione e cambia di nuovo una condizione.'; });
      }, 450);
    }

    function valori(c, i) {
      var t = tipoDi(c.campo), op = c.op, s = '', id = 'nls-v' + i;
      var periodo = function () {
        return '<div class="form-group"><label for="' + id + 'p">Quando</label><select id="' + id + 'p" data-k="periodo"><option value="0"' + (!c.n ? ' selected' : '') + '>Da sempre</option><option value="1"' + (c.n ? ' selected' : '') + '>Negli ultimi...</option></select></div>' +
          (c.n ? '<div class="form-group nls-n"><label for="' + id + 'n">Quanti</label><input type="number" id="' + id + 'n" data-k="n" min="1" max="36500" value="' + h(c.n) + '"></div><div class="form-group"><label for="' + id + 'u">Unità</label><select id="' + id + 'u" data-k="unita">' + opzioni(DEF.unita, c.unita || 'giorni') + '</select></div>' : '');
      };
      var camp = function () {
        var o = { '0': 'Qualsiasi campagna', '-2': 'Qualsiasi email (anche automazioni)', '-1': "L'ultima campagna partita" };
        Object.keys(DEF.campagne).forEach(function (k) { o[k] = DEF.campagne[k]; });
        return '<div class="form-group"><label for="' + id + 'c">Quale email</label><select id="' + id + 'c" data-k="campagna">' + opzioni(o, c.campagna || 0) + '</select></div>';
      };
      if (t === 'text' && ['vuoto', 'pieno'].indexOf(op) < 0) s = '<div class="form-group"><label for="' + id + '">Valore</label><input type="text" id="' + id + '" data-k="valore" maxlength="500" value="' + h(c.valore) + '"></div>';
      if (t === 'float' && ['vuoto', 'pieno'].indexOf(op) < 0) {
        s = '<div class="form-group"><label for="' + id + '">' + (op === 'fra' ? 'Da' : 'Numero') + '</label><input type="number" step="any" id="' + id + '" data-k="valore" value="' + h(c.valore) + '"></div>';
        if (op === 'fra') s += '<div class="form-group"><label for="' + id + 'b">A</label><input type="number" step="any" id="' + id + 'b" data-k="valore2" value="' + h(c.valore2) + '"></div>';
      }
      if (t === 'date') {
        if (['uguale', 'prima', 'dopo', 'fra'].indexOf(op) >= 0) s = '<div class="form-group"><label for="' + id + '">' + (op === 'fra' ? 'Dal' : 'Data') + '</label><input type="date" id="' + id + '" data-k="valore" value="' + h(c.valore) + '"></div>';
        if (op === 'fra') s += '<div class="form-group"><label for="' + id + 'b">Al</label><input type="date" id="' + id + 'b" data-k="valore2" value="' + h(c.valore2) + '"></div>';
        if (['ultimi', 'piu_di', 'prossimi'].indexOf(op) >= 0) s = '<div class="form-group nls-n"><label for="' + id + 'n">Quanti</label><input type="number" id="' + id + 'n" data-k="n" min="1" max="36500" value="' + h(c.n || 30) + '"></div><div class="form-group"><label for="' + id + 'u">Unità</label><select id="' + id + 'u" data-k="unita">' + opzioni(DEF.unita, c.unita || 'giorni') + '</select></div>';
      }
      if ((t === 'category' || t === 'multiple-choice') && ['vuoto', 'pieno'].indexOf(op) < 0) s = caselle('valore', DEF.campi[c.campo].o || {}, c.valore, 'Opzioni');
      if (t === 'lista') s = caselle('valore', DEF.liste, c.valore, 'liste');
      if (t === 'segmento') s = Object.keys(DEF.segmenti).length ? '<div class="form-group"><label for="' + id + '">Quale segmento</label><select id="' + id + '" data-k="valore"><option value="0">Scegli...</option>' + opzioni(DEF.segmenti, c.valore || 0) + '</select></div>' : '<p class="hint">Ancora nessun altro segmento salvato.</p>';
      if (t === 'email') s = camp() + periodo();
      if (t === 'link') s = '<div class="form-group nls-largo-campo"><label for="' + id + '">Indirizzo del link</label><input type="url" id="' + id + '" data-k="valore" list="' + id + 'l" maxlength="500" value="' + h(c.valore) + '" placeholder="per esempio: https://esempio.it/offerta"><datalist id="' + id + 'l">' + (DEF.link || []).map(function (u) { return '<option value="' + h(u) + '">'; }).join('') + '</datalist></div>' + camp();
      if (t === 'form') { var f = { '0': 'Qualsiasi form' }; Object.keys(DEF.form).forEach(function (k) { f[k] = DEF.form[k]; }); s = '<div class="form-group"><label for="' + id + '">Quale form</label><select id="' + id + '" data-k="valore">' + opzioni(f, c.valore || 0) + '</select></div>' + periodo(); }
      if (t === 'lista_nera' && op === 'motivo') s = caselle('valore', DEF.motivi, c.valore, 'Motivi');
      if (t === 'flusso_email') s = '<div class="form-group"><label for="' + id + '">Quale email</label><select id="' + id + '" data-k="valore"><option value="0">Scegli...</option>' + opzioni(DEF.flussoEmail || {}, c.valore || 0) + '</select></div>';
      if (t === 'iscritto_da' || t === 'attivita') s = '<div class="form-group nls-n"><label for="' + id + 'n">' + (t === 'attivita' ? 'Negli ultimi (giorni)' : 'Giorni') + '</label><input type="number" id="' + id + 'n" data-k="n" min="' + (t === 'attivita' ? 1 : 0) + '" max="36500" value="' + h(c.n || 30) + '"></div>';
      return s;
    }

    function disegna(fuoco) {
      var html = '';
      if (regole.gruppi.length > 1) html += '<div class="form-group nls-unione"><label for="nls-op">I gruppi si uniscono così</label><select id="nls-op" data-g="op"><option value="e"' + (regole.op !== 'o' ? ' selected' : '') + '>E: il contatto deve rispettare tutti i gruppi</option><option value="o"' + (regole.op === 'o' ? ' selected' : '') + '>O: basta un gruppo</option></select></div>';
      regole.gruppi.forEach(function (g, gi) {
        if (gi > 0) html += '<p class="nls-tra" aria-hidden="true">' + (regole.op === 'o' ? 'O' : 'E') + '</p>';
        html += '<fieldset class="nls-gruppo" data-gi="' + gi + '"><legend>Gruppo ' + (gi + 1) + '</legend>';
        if (g.condizioni.length > 1) html += '<div class="form-group nls-unione"><label for="nls-gop' + gi + '">Dentro il gruppo</label><select id="nls-gop' + gi + '" data-gop="' + gi + '"><option value="e"' + (g.op !== 'o' ? ' selected' : '') + '>E: tutte le condizioni</option><option value="o"' + (g.op === 'o' ? ' selected' : '') + '>O: almeno una condizione</option></select></div>';
        if (!g.condizioni.length) html += '<p class="hint">Nessuna condizione: aggiungine una qui sotto. Senza condizioni valgono tutti i contatti.</p>';
        g.condizioni.forEach(function (c, ci) {
          var i = ++uid, t = tipoDi(c.campo), ops = DEF.operatori[t] || {};
          if (ci > 0) html += '<p class="nls-tra" aria-hidden="true">' + (g.op === 'o' ? 'o' : 'e') + '</p>';
          var gruppiCampi = {};
          Object.keys(DEF.campi).forEach(function (k) { var d = DEF.campi[k]; (gruppiCampi[d.g] = gruppiCampi[d.g] || []).push('<option value="' + h(k) + '"' + (k === c.campo ? ' selected' : '') + '>' + h(d.e) + '</option>'); });
          html += '<div class="nls-cond" data-gi="' + gi + '" data-ci="' + ci + '"><div class="nls-cond-campi">' +
            '<div class="form-group"><label for="nls-c' + i + '">Condizione ' + (ci + 1) + ': su cosa</label><select id="nls-c' + i + '" data-k="campo">' + Object.keys(gruppiCampi).map(function (gn) { return '<optgroup label="' + h(gn) + '">' + gruppiCampi[gn].join('') + '</optgroup>'; }).join('') + '</select></div>' +
            '<div class="form-group"><label for="nls-o' + i + '">Come</label><select id="nls-o' + i + '" data-k="op">' + opzioni(ops, c.op) + '</select></div>' +
            valori(c, i) + '</div>' +
            '<button type="button" class="btn btn-danger btn-sm nls-togli" data-togli aria-label="Togli la condizione ' + (ci + 1) + ' del gruppo ' + (gi + 1) + '">Togli</button></div>';
        });
        html += '<div class="nl-riga nls-azioni-gruppo"><button type="button" class="btn btn-secondary btn-sm" data-aggiungi="' + gi + '">+ ' + (g.op === 'o' ? 'O' : 'E') + ': aggiungi una condizione</button>' +
          (regole.gruppi.length > 1 ? '<button type="button" class="btn btn-danger btn-sm" data-togli-gruppo="' + gi + '">Togli il gruppo ' + (gi + 1) + '</button>' : '') + '</div></fieldset>';
      });
      html += '<div class="nl-riga"><button type="button" class="btn btn-secondary btn-sm" data-nuovo-gruppo>+ Aggiungi un gruppo di condizioni</button><span class="hint">' + quante() + (quante() === 1 ? ' condizione' : ' condizioni') + ' su ' + DEF.massimo + '</span></div>';
      box.innerHTML = html;
      if (fuoco) { var el = box.querySelector(fuoco); if (el) el.focus(); }
    }

    function riga(el) { var r = el.closest('.nls-cond'); return r ? regole.gruppi[+r.getAttribute('data-gi')].condizioni[+r.getAttribute('data-ci')] : null; }
    function idDi(el) { return el.id ? '#' + el.id : null; }
    box.addEventListener('change', function (ev) {
      var el = ev.target;
      if (el.hasAttribute('data-g')) { regole.op = el.value; disegna(idDi(el)); salva(); return; }
      if (el.hasAttribute('data-gop')) { regole.gruppi[+el.getAttribute('data-gop')].op = el.value; disegna(idDi(el)); salva(); return; }
      var c = riga(el); if (!c) return;
      var k = el.getAttribute('data-k');
      if (k === 'campo') {
        var t = tipoDi(el.value), primo = Object.keys(DEF.operatori[t] || {})[0];
        var r = el.closest('.nls-cond'), gi = +r.getAttribute('data-gi'), ci = +r.getAttribute('data-ci');
        regole.gruppi[gi].condizioni[ci] = { campo: el.value, op: primo };
        if (t === 'email' || t === 'link') regole.gruppi[gi].condizioni[ci].campagna = 0;
        if (t === 'iscritto_da' || t === 'attivita') regole.gruppi[gi].condizioni[ci].n = 30;
        disegna(idDi(el)); salva(); return;
      }
      if (k === 'op') {
        c.op = el.value;
        if (['date', 'float', 'text'].indexOf(tipoDi(c.campo)) >= 0) { delete c.valore2; delete c.n; delete c.unita; }
        if (['ultimi', 'piu_di', 'prossimi'].indexOf(c.op) >= 0) { c.n = 30; c.unita = 'giorni'; }
        disegna(idDi(el)); salva(); return;
      }
      if (k === 'periodo') { if (el.value === '1') { c.n = 30; c.unita = 'giorni'; } else { delete c.n; delete c.unita; } disegna(idDi(el)); salva(); return; }
      leggi(el, c); salva();
    });
    box.addEventListener('input', function (ev) { var el = ev.target, c = riga(el); if (!c || el.tagName === 'SELECT' || el.type === 'checkbox') return; leggi(el, c); salva(); });
    function leggi(el, c) {
      var k = el.getAttribute('data-k'); if (!k) return;
      if (el.type === 'checkbox') { c[k] = [].slice.call(el.closest('.nls-caselle').querySelectorAll('input:checked')).map(function (x) { return x.value; }); return; }
      c[k] = el.value;
    }
    box.addEventListener('click', function (ev) {
      var b = ev.target.closest('button'); if (!b) return;
      if (b.hasAttribute('data-aggiungi')) {
        if (quante() >= DEF.massimo) { if (numero) numero.textContent = 'Al massimo ' + DEF.massimo + ' condizioni.'; return; }
        var g = regole.gruppi[+b.getAttribute('data-aggiungi')]; g.condizioni.push(nuovaCondizione());
        disegna(); var sel = box.querySelectorAll('.nls-gruppo')[+b.getAttribute('data-aggiungi')].querySelectorAll('[data-k=campo]'); if (sel.length) sel[sel.length - 1].focus(); salva(); return;
      }
      if (b.hasAttribute('data-nuovo-gruppo')) {
        if (quante() >= DEF.massimo) return;
        regole.gruppi.push({ op: 'e', condizioni: [nuovaCondizione()] }); disegna(); var gs = box.querySelectorAll('.nls-gruppo [data-k=campo]'); if (gs.length) gs[gs.length - 1].focus(); salva(); return;
      }
      if (b.hasAttribute('data-togli-gruppo')) { regole.gruppi.splice(+b.getAttribute('data-togli-gruppo'), 1); disegna('[data-nuovo-gruppo]'); salva(); return; }
      if (b.hasAttribute('data-togli')) {
        var r = b.closest('.nls-cond'), gi = +r.getAttribute('data-gi');
        regole.gruppi[gi].condizioni.splice(+r.getAttribute('data-ci'), 1);
        disegna('[data-aggiungi="' + gi + '"]'); salva();
      }
    });
    disegna();
    conta();
  }

  document.querySelectorAll('[data-nls-editor]').forEach(function (b) { new Editor(b); });
  // 2.15.0: la tela delle automazioni monta il costruttore nel pannello di una divisione (e cambia DEF.flussoEmail).
  window.nlsDef = DEF;
  window.nlsMonta = function (b) { return new Editor(b); };
  // Salva come segmento: il form della finestra prende le regole del filtro al momento dell'invio.
  document.addEventListener('submit', function (ev) {
    ev.target.querySelectorAll('[data-nls-copia]').forEach(function (i) { var d = document.querySelector(i.getAttribute('data-nls-copia')); if (d) i.value = d.value; });
  }, true);
})();
