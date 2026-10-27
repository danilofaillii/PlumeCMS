// Comportamenti comuni del pannello senza gestori in linea (onclick="..."): la Content-Security-Policy a nonce
// del pannello li vieterebbe. Ogni pagina li dichiara con attributi data-*, questo file li aggancia una volta sola.
//
//   data-conferma="testo"        su un form (all'invio) o su un pulsante/link (al clic): chiede conferma
//   data-uno="12"                pulsante: prima dell'invio scrive 12 nel campo "uno" del suo form
//   data-campo="nome" data-valore="x"      pulsante: scrive x nel campo "nome" del suo form
//   data-campo-id="id" data-valore="x"     pulsante: scrive x nel campo con quell'id
//   data-pn-gruppo="#select" [data-pn-gruppo-righe=".sel"] [data-pn-gruppo-elimina="testo"]   azioni di gruppo
//   data-seleziona-tutti=".sel"  casella: spunta o toglie tutte le caselle con quel selettore
//   data-vai-al-valore           select: va all'indirizzo scelto
//   data-invia-al-cambio         select o casella: invia il suo form
//   data-chiudi-dialogo="#id"    pulsante: chiude quel <dialog>
//   data-rimuovi-genitore        pulsante: toglie l'elemento che lo contiene
//   data-copia-se-stesso         campo: al clic seleziona il testo e lo copia
//   data-nascondi="#id"          pulsante: nasconde quell'elemento
//   data-menu-mobile             apre e chiude il menu laterale sul telefono
//   data-al-clic="funzione(arg, ...)"   data-al-input="..."   data-al-cambio="..."
//        chiama una funzione globale della pagina. Gli argomenti si leggono senza eval: stringhe tra apici,
//        numeri, true/false/null, oggetti e liste JSON, e le parole this (l'elemento) ed event.
//        Se la funzione ritorna false, o il testo finisce con ";return false", l'azione predefinita non parte.
(function () {
  'use strict';

  // ------------------------------------------------------------ lettura sicura di "nome(arg, arg)"
  function leggiChiamata(testo) {
    testo = String(testo || '').trim();
    var stop = false;
    var r = testo.match(/;\s*return\s+false\s*;?\s*$/);
    if (r) { stop = true; testo = testo.slice(0, r.index).trim(); }
    var m = testo.match(/^([A-Za-z_$][\w$]*(?:\.[A-Za-z_$][\w$]*)*)\s*\(([\s\S]*)\)\s*;?$/);
    if (!m) return null;
    return { nome: m[1], argomenti: m[2], stop: stop };
  }

  // Divide "a, 'b,c', {..}, [..]" in pezzi, rispettando apici e parentesi.
  function dividi(s) {
    var out = [], prof = 0, ap = null, cur = '', i, c;
    for (i = 0; i < s.length; i++) {
      c = s[i];
      if (ap) {
        cur += c;
        if (c === '\\' && i + 1 < s.length) { cur += s[++i]; continue; }
        if (c === ap) ap = null;
        continue;
      }
      if (c === '"' || c === "'") { ap = c; cur += c; continue; }
      if (c === '{' || c === '[' || c === '(') prof++;
      if (c === '}' || c === ']' || c === ')') prof--;
      if (c === ',' && prof === 0) { out.push(cur.trim()); cur = ''; continue; }
      cur += c;
    }
    if (cur.trim() !== '') out.push(cur.trim());
    return out;
  }

  function valore(t, el, ev) {
    if (t === 'this') return el;
    if (t === 'event') return ev;
    if (t === 'true') return true;
    if (t === 'false') return false;
    if (t === 'null') return null;
    if (/^-?\d+(\.\d+)?$/.test(t)) return Number(t);
    if (t[0] === "'" && t[t.length - 1] === "'") return t.slice(1, -1).replace(/\\(.)/g, '$1');
    if (t[0] === '"' || t[0] === '{' || t[0] === '[') { try { return JSON.parse(t); } catch (e) { return undefined; } }
    // this.value, this.checked: le sole proprieta dell'elemento ammesse
    var p = t.match(/^this\.(value|checked)$/);
    if (p) return el[p[1]];
    throw new Error('argomento non ammesso: ' + t);
  }

  function chiama(el, ev, attr) {
    var c = leggiChiamata(el.getAttribute(attr));
    if (!c) { console.error('pannello.js: chiamata non leggibile', el.getAttribute(attr)); return; }
    var fn = c.nome.split('.').reduce(function (o, k) { return o ? o[k] : undefined; }, window);
    if (typeof fn !== 'function') { console.error('pannello.js: funzione assente', c.nome); return; }
    var args = c.argomenti.trim() === '' ? [] : dividi(c.argomenti).map(function (t) { return valore(t, el, ev); });
    var esito = fn.apply(el, args);
    if (esito === false || c.stop) { ev.preventDefault(); ev.stopPropagation(); }
  }

  // ------------------------------------------------------------ clic
  document.addEventListener('click', function (ev) {
    var el = ev.target.closest('[data-conferma], [data-uno], [data-campo], [data-campo-id], [data-pn-gruppo], [data-seleziona-tutti], [data-chiudi-dialogo], [data-rimuovi-genitore], [data-copia-se-stesso], [data-nascondi], [data-menu-mobile], [data-al-clic]');
    if (!el) return;
    // data-conferma sui form vale all'invio (sotto), non al clic dentro il form
    if (el.tagName === 'FORM') return;
    if (el.hasAttribute('data-pn-gruppo')) {
      var sel = document.querySelector(el.getAttribute('data-pn-gruppo'));
      if (!sel || !sel.value) { ev.preventDefault(); if (el.hasAttribute('data-pn-gruppo-righe')) alert("Scegli un'azione di gruppo."); return; }
      var righe = el.getAttribute('data-pn-gruppo-righe');
      if (righe && !document.querySelector(righe + ':checked')) { ev.preventDefault(); alert('Spunta almeno una riga.'); return; }
      var msg = el.getAttribute('data-pn-gruppo-elimina');
      if (msg && sel.value === 'elimina' && !confirm(msg)) { ev.preventDefault(); return; }
    }
    if (el.hasAttribute('data-conferma') && !confirm(el.getAttribute('data-conferma'))) { ev.preventDefault(); ev.stopPropagation(); return; }
    var form = el.form || el.closest('form');
    if (el.hasAttribute('data-uno') && form && form.uno) form.uno.value = el.getAttribute('data-uno');
    if (el.hasAttribute('data-campo') && form) { var f = form.querySelector('[name="' + el.getAttribute('data-campo') + '"]'); if (f) f.value = el.getAttribute('data-valore'); }
    if (el.hasAttribute('data-campo-id')) { var g = document.getElementById(el.getAttribute('data-campo-id')); if (g) g.value = el.getAttribute('data-valore'); }
    if (el.hasAttribute('data-seleziona-tutti')) document.querySelectorAll(el.getAttribute('data-seleziona-tutti')).forEach(function (c) { c.checked = el.checked; });
    if (el.hasAttribute('data-chiudi-dialogo')) { var d = document.querySelector(el.getAttribute('data-chiudi-dialogo')); if (d && d.close) d.close(); }
    if (el.hasAttribute('data-rimuovi-genitore') && el.parentNode) el.parentNode.remove();
    if (el.hasAttribute('data-copia-se-stesso')) { el.select(); if (navigator.clipboard) navigator.clipboard.writeText(el.value); }
    if (el.hasAttribute('data-nascondi')) { var n = document.querySelector(el.getAttribute('data-nascondi')); if (n) n.style.display = 'none'; }
    if (el.hasAttribute('data-menu-mobile')) {
      var s = document.querySelector('.admin-sidebar'), o = document.querySelector('.admin-sidebar-overlay');
      if (el.classList.contains('admin-sidebar-overlay')) { el.classList.remove('open'); if (s) s.classList.remove('open'); }
      else { if (s) s.classList.toggle('open'); if (o) o.classList.toggle('open'); }
    }
    if (el.hasAttribute('data-al-clic')) chiama(el, ev, 'data-al-clic');
  });

  // ------------------------------------------------------------ invio dei form
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (f.hasAttribute && f.hasAttribute('data-conferma') && !confirm(f.getAttribute('data-conferma'))) ev.preventDefault();
  }, true);

  // ------------------------------------------------------------ cambio e scrittura
  document.addEventListener('change', function (ev) {
    var el = ev.target;
    if (el.hasAttribute('data-vai-al-valore') && el.value) location.href = el.value;
    if (el.hasAttribute('data-invia-al-cambio') && el.form) el.form.submit();
    if (el.hasAttribute('data-al-cambio')) chiama(el, ev, 'data-al-cambio');
  });
  document.addEventListener('input', function (ev) {
    var el = ev.target;
    if (el.hasAttribute && el.hasAttribute('data-al-input')) chiama(el, ev, 'data-al-input');
  });
})();
