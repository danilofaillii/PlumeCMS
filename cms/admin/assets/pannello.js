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
//   data-immagine                campo di testo: miniatura, "Scegli dai Media" e X per toglierla (2.1.0, vedi in fondo)
//   data-scegli-media="#id"      pulsante: apre la libreria dei Media e scrive l'indirizzo scelto nel campo con quell'id (2.0.0)
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

  // ------------------------------------------------------------ UX 2026-11: la finestra di conferma del pannello
  // Al posto di confirm() del browser (che non segue l'aspetto e non distingue l'azione pericolosa): un <dialog> modale,
  // con il messaggio, "Annulla" e il pulsante che conferma. Il pulsante che conferma ripete il nome dell'azione premuta
  // ("Rimuovi", "Elimina il form"), rosso se l'azione distrugge qualcosa. Il fuoco va dentro (su Annulla se l'azione e
  // pericolosa), Esc chiude, alla chiusura il fuoco torna dove era. Senza <dialog> (browser vecchi) resta confirm().
  // Evento "pn-conferma" sul documento all'apertura (detail: testo, pericolo, rispondi(si)): lo usano le prove.
  var PERICOLO = /elimin|cancell|cestin|rimuov|togli|svuot|ripristin|azzer|sospend|spegn|esci da|disconnett|revoc|sostituisc|annulla (la|il|l')|rigener|chiudi tutt|definitivo/i;
  var C = { dlg: null, poi: null, prima: null };
  function confermaCrea() {
    var d = document.createElement('dialog');
    d.className = 'pn-conferma'; d.setAttribute('role', 'alertdialog'); d.setAttribute('aria-labelledby', 'pn-conferma-titolo'); d.setAttribute('aria-describedby', 'pn-conferma-testo');
    d.innerHTML = '<h2 id="pn-conferma-titolo">Confermi?</h2><p id="pn-conferma-testo"></p>'
      + '<div class="pn-conferma-azioni"><button type="button" class="btn btn-secondary pn-conferma-no">Annulla</button>'
      + '<button type="button" class="btn btn-primary pn-conferma-si">Conferma</button></div>';
    document.body.appendChild(d);
    d.querySelector('.pn-conferma-no').addEventListener('click', function () { confermaChiudi(false); });
    d.querySelector('.pn-conferma-si').addEventListener('click', function () { confermaChiudi(true); });
    d.addEventListener('cancel', function (e) { e.preventDefault(); confermaChiudi(false); });
    d.addEventListener('click', function (e) { if (e.target === d) confermaChiudi(false); }); // clic sul velo = Annulla
    C.dlg = d;
  }
  function confermaChiudi(si) {
    var poi = C.poi, prima = C.prima; C.poi = null;
    if (C.dlg.open) C.dlg.close();
    if (prima && prima.focus && document.contains(prima)) { try { prima.focus({ preventScroll: true }); } catch (e) { prima.focus(); } }
    if (poi) poi(si);
  }
  // testo: la domanda; azione: l'elemento premuto (per il nome del pulsante e il colore); poi(si): cosa fare dopo
  function conferma(testo, azione, poi) {
    if (typeof HTMLDialogElement === 'undefined' || !document.body) { poi(confirm(testo)); return; }
    if (!C.dlg) confermaCrea();
    if (C.dlg.open) return;
    var nome = azione ? ((azione.getAttribute && azione.getAttribute('data-conferma-ok')) || (azione.tagName === 'INPUT' ? azione.value : azione.textContent) || '').replace(/\s+/g, ' ').trim() : '';
    if (nome.length > 32 || nome === '') nome = 'Conferma';
    var pericolo = (azione && azione.hasAttribute && azione.hasAttribute('data-conferma-pericolo')) || PERICOLO.test(nome) || PERICOLO.test(testo.split(/[.?!]/)[0]);
    C.dlg.querySelector('#pn-conferma-testo').textContent = testo;
    var si = C.dlg.querySelector('.pn-conferma-si'), no = C.dlg.querySelector('.pn-conferma-no');
    si.textContent = nome;
    si.className = 'btn ' + (pericolo ? 'btn-pericolo' : 'btn-primary') + ' pn-conferma-si';
    C.dlg.classList.toggle('pn-conferma-pericolo', !!pericolo);
    C.poi = poi; C.prima = document.activeElement;
    C.dlg.showModal();
    (pericolo ? no : si).focus();
    document.dispatchEvent(new CustomEvent('pn-conferma', { detail: { testo: testo, pericolo: !!pericolo, rispondi: function (r) { (r ? si : no).click(); } } }));
  }
  window.pnConferma = conferma;
  // Rifa l'azione dopo il si: l'elemento riceve di nuovo il clic (cosi partono anche data-uno, data-campo e i gestori
  // della pagina; un pulsante invia il suo form con nome e valore, un link apre l'indirizzo). Il segno __pnSi fa passare
  // la seconda volta senza chiedere, anche al form se ha una sua conferma.
  function rifai(el) {
    el.__pnSi = true;
    var form = el.form || (el.closest && el.closest('form'));
    if (form) form.__pnSi = true;
    el.click();
    setTimeout(function () { el.__pnSi = false; if (form) form.__pnSi = false; }, 0);
  }

  // ------------------------------------------------------------ clic
  // La conferma si chiede nella fase di cattura: i gestori della pagina sul pulsante partono solo dopo il si.
  document.addEventListener('click', function (ev) {
    var el = ev.target.closest && ev.target.closest('[data-conferma]');
    if (!el || el.tagName === 'FORM' || el.__pnSi) return;
    ev.preventDefault(); ev.stopPropagation();
    conferma(el.getAttribute('data-conferma'), el, function (si) { if (si) rifai(el); });
  }, true);
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
      if (msg && sel.value === 'elimina' && !el.__pnSi) { ev.preventDefault(); ev.stopPropagation(); conferma(msg, null, function (si) { if (si) rifai(el); }); return; }
    }
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
      // UX 2026-11: chi usa un lettore di schermo sa se il menu e aperto.
      var b = document.querySelector('.admin-menu-toggle'); if (b && s) b.setAttribute('aria-expanded', s.classList.contains('open') ? 'true' : 'false');
    }
    if (el.hasAttribute('data-al-clic')) chiama(el, ev, 'data-al-clic');
  });

  // ------------------------------------------------------------ invio dei form
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!f.hasAttribute || !f.hasAttribute('data-conferma') || f.__pnSi) return;
    ev.preventDefault(); ev.stopPropagation(); // i gestori della pagina sul form (invii con barra di avanzamento...) aspettano il si
    var chi = ev.submitter || null;
    conferma(f.getAttribute('data-conferma'), chi, function (si) {
      if (!si) return;
      f.__pnSi = true;
      if (f.requestSubmit) f.requestSubmit(chi && chi.form === f ? chi : undefined); else f.submit();
      setTimeout(function () { f.__pnSi = false; }, 0);
    });
  }, true);

  // ------------------------------------------------------------ UX 2026-11: un indirizzo con #id apre il riquadro richiudibile
  // (<details>) che ha quell'id o che lo contiene: dopo un cambio la pagina torna con il riquadro aperto (Dashboard, #tipo-sito).
  function apriDaIndirizzo() {
    var id = location.hash ? decodeURIComponent(location.hash.slice(1)) : '';
    var t = id && document.getElementById(id); if (!t) return;
    // Un riquadro spostato in un'altra scheda lascia qui il suo id con data-spostato: si va dove sta adesso.
    if (t.hasAttribute('data-spostato')) { location.replace(t.getAttribute('data-spostato')); return; }
    for (var d = t; d; d = d.parentElement) if (d.tagName === 'DETAILS') d.open = true;
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apriDaIndirizzo); else apriDaIndirizzo();
  window.addEventListener('hashchange', apriDaIndirizzo);

  // ------------------------------------------------------------ UX 2026-11: le tabelle diventano schede al telefono
  // Ogni tabella semplice del pannello (testata con almeno tre colonne, righe senza celle unite) riceve data-schede e, su
  // ogni cella, data-etichetta con il nome della sua colonna: sotto 640 px admin.css mostra ogni riga come una scheda
  // ("Ruolo: Amministratore") invece di una tabella da far scorrere di lato. data-no-schede su una tabella la lascia com'e.
  function tabelleASchede() {
    document.querySelectorAll('.admin-content table:not([data-no-schede]):not([data-schede])').forEach(function (t) {
      var th = t.tHead && t.tHead.rows.length === 1 ? Array.prototype.slice.call(t.tHead.rows[0].cells) : [];
      if (th.length < 3 || th.some(function (c) { return c.colSpan > 1; })) return;
      var nomi = th.map(function (c) { return (c.textContent || '').replace(/\s+/g, ' ').replace(/[▴▾▲▼]/g, '').trim(); });
      var righe = Array.prototype.slice.call(t.tBodies.length ? t.tBodies[0].rows : []);
      if (righe.some(function (r) { return r.cells.length !== nomi.length && !(r.cells.length === 1 && r.cells[0].colSpan > 1); })) return;
      righe.forEach(function (r) { if (r.cells.length === nomi.length) Array.prototype.forEach.call(r.cells, function (c, i) { if (nomi[i]) c.setAttribute('data-etichetta', nomi[i]); }); });
      t.setAttribute('data-schede', '');
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', tabelleASchede); else tabelleASchede();

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

  // ------------------------------------------------------------ 2.1.0: scegli un'immagine dai Media
  // Un solo <dialog> per la pagina, creato al primo uso. Le immagini arrivano da editor-api.php (azione media) a pagine
  // di 60, cercate e filtrate dal server: con mille immagini si scaricano solo quelle che si vedono. Dalla stessa finestra
  // si carica un'immagine nuova (azione carica, oppure l'indirizzo dato con data-scegli-media-carica) e la si sceglie subito.
  //
  //   <input type="text" data-immagine ...>     il campo diventa: miniatura, casella, "Scegli dai Media", X per toglierla
  //       data-immagine-carica="pagina.php"     (facoltativo) dove carica i file nuovi; data-immagine-assoluto: indirizzo completo
  //   <button data-scegli-media="#id">           un pulsante libero che scrive nel campo con quell'id
  //   <button data-scegli-media-lista="#el" data-scegli-media-modello="#template">
  //                                              aggiunge in #el una copia del <template>, con l'indirizzo in [data-url-src] e [data-url-valore]
  //   window.pnMedia.apri({ scegli: function (url) {}, attuale: '...', carica: '...', assoluto: false, accetta: '...' })
  //
  // Tastiera: si apre con il fuoco sulla ricerca; Tab porta alla griglia, le frecce si muovono fra le immagini, Invio sceglie,
  // Esc chiude e il fuoco torna al pulsante che l'ha aperta. Senza libreria (errore di rete) il campo resta da scrivere a mano.
  var PER = 60, ACCETTA = 'image/jpeg,image/png,image/gif,image/webp,image/avif,image/svg+xml';
  var M = { dlg: null, opz: null, pagina: 0, pagine: 1, totale: 0, giro: 0, carica: null, cerca: null, cartelleFatte: false, timer: null, origine: null };
  function token() {
    var m = document.querySelector('meta[name=pn-csrf]'), i = document.querySelector('input[name=_csrf]');
    return m ? m.content : (i ? i.value : '');
  }
  function q(sel) { return M.dlg.querySelector(sel); }
  function stato(testo, errore) { var s = q('.pn-media-stato'); s.textContent = testo || ''; s.classList.toggle('pn-media-errore', !!errore); }
  function peso(b) { b = +b || 0; return b > 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }

  function mediaCrea() {
    var d = document.createElement('dialog');
    d.className = 'pn-media'; d.setAttribute('aria-labelledby', 'pn-media-titolo');
    d.innerHTML = '<div class="pn-media-dentro">'
      + '<div class="pn-media-testa"><h3 id="pn-media-titolo">Scegli un\'immagine</h3>'
      + '<button type="button" class="pn-media-chiudi" aria-label="Chiudi">×</button></div>'
      + '<div class="pn-media-filtri">'
      + '<label class="pn-media-cerca"><span class="sr-solo">Cerca nel nome</span><input type="search" placeholder="Cerca nel nome…" autocomplete="off"></label>'
      + '<label class="pn-media-cartella"><span class="sr-solo">Cartella</span><select><option value="">Tutte le cartelle</option></select></label>'
      + '<button type="button" class="btn btn-primary btn-sm pn-media-carica">Carica un\'immagine</button>'
      + '<input type="file" class="pn-media-file" hidden tabindex="-1">'
      + '</div>'
      + '<div class="pn-media-barra" hidden><span></span></div>'
      + '<p class="pn-media-stato" role="status" aria-live="polite"></p>'
      + '<div class="pn-media-corpo"><div class="pn-media-griglia" role="list" aria-label="Immagini della libreria"></div>'
      + '<div class="pn-media-fondo"><button type="button" class="btn btn-secondary btn-sm pn-media-altre" hidden>Mostra altre immagini</button></div></div>'
      + '<p class="pn-media-cala" aria-hidden="true">Lascia qui il file per caricarlo</p>'
      + '<div class="pn-media-azioni">'
      + '<details class="pn-media-indirizzo"><summary>Oppure usa un indirizzo</summary><div><label><span class="sr-solo">Indirizzo dell\'immagine</span><input type="url" placeholder="https://…" autocomplete="off"></label><button type="button" class="btn btn-secondary btn-sm">Usa</button></div></details>'
      + '<button type="button" class="btn btn-secondary pn-media-esci">Chiudi</button></div></div>';
    document.body.appendChild(d);
    M.dlg = d;
    q('.pn-media-chiudi').addEventListener('click', function () { d.close(); });
    q('.pn-media-esci').addEventListener('click', function () { d.close(); });
    d.addEventListener('click', function (e) { if (e.target === d) d.close(); }); // clic sullo sfondo
    d.addEventListener('close', function () {
      if (M.carica) M.carica.abort();
      if (M.origine && document.contains(M.origine)) M.origine.focus();
    });
    q('input[type=search]').addEventListener('input', function () { clearTimeout(M.timer); M.timer = setTimeout(function () { mediaPagina(1); }, 250); });
    q('input[type=search]').addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { var b = q('.pn-media-voce'); if (b) { e.preventDefault(); fuoco(b); } }
      if (e.key === 'Enter') { e.preventDefault(); clearTimeout(M.timer); mediaPagina(1); }
    });
    q('select').addEventListener('change', function () { mediaPagina(1); });
    q('.pn-media-altre').addEventListener('click', function () { mediaPagina(M.pagina + 1); });
    q('.pn-media-carica').addEventListener('click', function () { q('.pn-media-file').click(); });
    q('.pn-media-file').addEventListener('change', function () { if (this.files[0]) mediaCarica(this.files[0]); this.value = ''; });
    var usa = function () { var u = q('.pn-media-indirizzo input').value.trim(); if (u) mediaScegli(u); };
    q('.pn-media-indirizzo button').addEventListener('click', usa);
    q('.pn-media-indirizzo input').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); usa(); } });
    // Un file trascinato sopra la finestra si carica.
    d.addEventListener('dragover', function (e) { if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') >= 0) { e.preventDefault(); d.classList.add('pn-media-sopra'); } });
    d.addEventListener('dragleave', function (e) { if (e.target === d || !d.contains(e.relatedTarget)) d.classList.remove('pn-media-sopra'); });
    d.addEventListener('drop', function (e) { d.classList.remove('pn-media-sopra'); if (e.dataTransfer && e.dataTransfer.files[0]) { e.preventDefault(); mediaCarica(e.dataTransfer.files[0]); } });
    // Le frecce fra le immagini: una sola fermata di Tab per la griglia (tabindex mobile).
    var g = q('.pn-media-griglia');
    g.addEventListener('keydown', function (e) {
      var voci = Array.prototype.slice.call(g.querySelectorAll('.pn-media-voce')), i = voci.indexOf(document.activeElement);
      if (i < 0) return;
      var col = Math.max(1, Math.round(g.clientWidth / voci[0].getBoundingClientRect().width)), n = i;
      if (e.key === 'ArrowRight') n = i + 1; else if (e.key === 'ArrowLeft') n = i - 1;
      else if (e.key === 'ArrowDown') n = i + col; else if (e.key === 'ArrowUp') n = i - col;
      else if (e.key === 'Home') n = 0; else if (e.key === 'End') n = voci.length - 1;
      else if (e.key === 'PageDown') n = i + col * 3; else if (e.key === 'PageUp') n = i - col * 3;
      else return;
      e.preventDefault();
      if (e.key === 'ArrowUp' && n < 0) { q('input[type=search]').focus(); return; }
      if (n >= voci.length && M.pagina < M.pagine) mediaPagina(M.pagina + 1);
      fuoco(voci[Math.max(0, Math.min(voci.length - 1, n))]);
    });
    // Le pagine dopo arrivano da sole quando si scorre fino in fondo.
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (v) {
        if (v[0].isIntersecting && M.dlg.open && M.pagina < M.pagine && !M.cerca) mediaPagina(M.pagina + 1);
      }, { root: q('.pn-media-corpo'), rootMargin: '0px 0px 300px 0px' }).observe(q('.pn-media-fondo'));
    }
  }
  function fuoco(b) {
    M.dlg.querySelectorAll('.pn-media-voce[tabindex="0"]').forEach(function (x) { if (x !== b) x.tabIndex = -1; });
    b.tabIndex = 0; b.focus();
  }

  function mediaVoce(m) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'pn-media-voce'; b.tabIndex = -1; b.setAttribute('role', 'listitem');
    b.title = m.url;
    var f = document.createElement('span'); f.className = 'pn-media-foto';
    var i = document.createElement('img'); i.loading = 'lazy'; i.decoding = 'async'; i.alt = ''; i.src = m.url;
    i.addEventListener('error', function () { f.classList.add('pn-media-rotta'); });
    f.appendChild(i);
    var t = document.createElement('span'); t.className = 'pn-media-nome'; t.textContent = m.nome || m.url;
    var s = document.createElement('span'); s.className = 'pn-media-info'; s.textContent = (m.peso ? peso(m.peso) + ' · ' : '') + (m.cartella || '');
    b.appendChild(f); b.appendChild(t); b.appendChild(s);
    b.setAttribute('aria-label', (m.nome || m.url) + (m.cartella ? ', ' + m.cartella : ''));
    if (M.opz && M.opz.attuale && (m.url === M.opz.attuale || assoluto(m.url) === M.opz.attuale)) { b.classList.add('pn-media-scelta'); b.setAttribute('aria-current', 'true'); }
    b.addEventListener('click', function () { mediaScegli(m.url); });
    return b;
  }

  function mediaPagina(n) {
    var g = q('.pn-media-griglia'), giro = ++M.giro;
    if (M.cerca) M.cerca.abort();
    if (n === 1) { g.textContent = ''; g.setAttribute('aria-busy', 'true'); }
    stato(n === 1 ? 'Carico le immagini…' : 'Carico altre immagini…');
    q('.pn-media-altre').hidden = true;
    var fd = new FormData();
    fd.set('azione', 'media'); fd.set('pagina', String(n)); fd.set('_csrf', token());
    fd.set('q', q('input[type=search]').value.trim()); fd.set('cartella', q('select').value);
    var ctrl = window.AbortController ? new AbortController() : null;
    M.cerca = ctrl;
    fetch('editor-api.php', { method: 'POST', body: fd, credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok || j.errore) throw new Error(j.errore || 'Errore ' + r.status); return j; }); })
      .then(function (j) {
        if (giro !== M.giro) return;
        M.cerca = null; M.pagina = j.pagina || n; M.pagine = j.pagine || 1; M.totale = j.totale || 0;
        g.removeAttribute('aria-busy');
        if (!M.cartelleFatte && j.cartelle) {
          M.cartelleFatte = true;
          j.cartelle.forEach(function (c) { if (!c.quante) return; var o = document.createElement('option'); o.value = c.chiave; o.textContent = c.nome + ' (' + c.quante + ')'; q('select').appendChild(o); });
        }
        var frag = document.createDocumentFragment();
        (j.immagini || []).forEach(function (m) { frag.appendChild(mediaVoce(m)); });
        g.appendChild(frag);
        var prima = g.querySelector('.pn-media-scelta') || g.querySelector('.pn-media-voce');
        if (prima && !g.querySelector('.pn-media-voce[tabindex="0"]')) prima.tabIndex = 0;
        var viste = g.querySelectorAll('.pn-media-voce').length, cerco = q('input[type=search]').value.trim() || q('select').value;
        if (!M.totale) {
          var p = document.createElement('p'); p.className = 'pn-media-vuoto';
          p.textContent = cerco ? 'Nessuna immagine con questo nome. Prova un\'altra parola, o carica l\'immagine.' : 'Nessuna immagine nella libreria: carica la prima con il pulsante qui sopra.';
          g.appendChild(p);
          stato(cerco ? 'Nessuna immagine trovata.' : 'La libreria è vuota.');
        } else {
          stato(M.totale === 1 ? '1 immagine' : (viste < M.totale ? viste + ' di ' + M.totale + ' immagini' : M.totale + ' immagini') + (cerco ? ' trovate' : ''));
        }
        q('.pn-media-altre').hidden = M.pagina >= M.pagine;
      })
      .catch(function (e) {
        if (e && e.name === 'AbortError') return;
        if (giro !== M.giro) return;
        g.removeAttribute('aria-busy');
        stato('Libreria non raggiungibile (' + (e && e.message ? e.message : 'rete') + '). Puoi caricare un\'immagine o usare un indirizzo.', true);
      });
  }

  function mediaCarica(file) {
    var acc = (M.opz.accetta || ACCETTA).split(',');
    if (file.type && acc.indexOf(file.type) < 0) { stato('Questo file non è un\'immagine che si può usare qui (' + (file.type || 'tipo sconosciuto') + ').', true); return; }
    var fd = new FormData(), x = new XMLHttpRequest(), barra = q('.pn-media-barra'), btn = q('.pn-media-carica');
    fd.set('azione', 'carica'); fd.set('_csrf', token()); fd.set('immagine', file);
    M.carica = x; btn.disabled = true; barra.hidden = false; barra.firstChild.style.width = '0';
    stato('Carico ' + file.name + '…');
    x.open('POST', M.opz.carica || 'editor-api.php');
    x.upload.addEventListener('progress', function (e) { if (e.lengthComputable) barra.firstChild.style.width = Math.round(e.loaded / e.total * 100) + '%'; });
    var fine = function () { M.carica = null; btn.disabled = false; barra.hidden = true; };
    x.addEventListener('load', function () {
      fine();
      var j = null; try { j = JSON.parse(x.responseText); } catch (e) {}
      if (j && j.url) { mediaScegli(j.url); return; }
      stato((j && j.errore) || (x.status === 413 ? 'Il file supera il limite di caricamento del server.' : 'Caricamento non riuscito (errore ' + x.status + ').'), true);
    });
    x.addEventListener('error', function () { fine(); stato('Caricamento non riuscito: controlla la connessione e riprova.', true); });
    x.addEventListener('abort', fine);
    x.send(fd);
  }

  function assoluto(u) { try { return new URL(u, location.href).href; } catch (e) { return u; } }
  function mediaScegli(u) {
    var o = M.opz;
    if (o.assoluto) u = assoluto(u);
    M.dlg.close();
    if (o.scegli) o.scegli(u);
  }

  function mediaApri(opz) {
    if (!M.dlg) mediaCrea();
    M.opz = opz || {};
    M.origine = document.activeElement;
    q('.pn-media-file').accept = M.opz.accetta || ACCETTA;
    q('.pn-media-indirizzo').open = false; q('.pn-media-indirizzo input').value = '';
    q('h3').textContent = M.opz.titolo || 'Scegli un\'immagine';
    // Ogni apertura riparte da capo: tutte le cartelle, nessuna ricerca, i conti delle cartelle rifatti.
    q('input[type=search]').value = '';
    var sel = q('select'); while (sel.options.length > 1) sel.remove(1); sel.value = ''; M.cartelleFatte = false;
    stato('');
    if (!M.dlg.open) M.dlg.showModal();
    q('input[type=search]').focus();
    q('.pn-media-corpo').scrollTop = 0;
    mediaPagina(1);
  }
  window.pnMedia = { apri: mediaApri };

  // Il campo che riceve l'indirizzo: scrive, avvisa la pagina (input e change) e aggiorna la miniatura.
  function scriviCampo(c, u) {
    c.value = u;
    c.dispatchEvent(new Event('input', { bubbles: true }));
    c.dispatchEvent(new Event('change', { bubbles: true }));
  }
  var progressivo = 0;
  function miniatura(box, u) {
    var img = box.querySelector('.pn-immagine-mini img'), mini = box.querySelector('.pn-immagine-mini');
    mini.classList.remove('pn-immagine-rotta');
    box.classList.toggle('pn-immagine-vuota', !u);
    var x = box.querySelector('.pn-immagine-x'); if (x) x.hidden = !u;
    if (u) img.src = u; else img.removeAttribute('src');
  }
  function preparaCampo(c) {
    if (c.closest('.pn-immagine')) return;
    if (!c.id) c.id = 'pn-immagine-' + (++progressivo);
    var box = document.createElement('div'); box.className = 'pn-immagine';
    c.parentNode.insertBefore(box, c);
    var mini = document.createElement('span'); mini.className = 'pn-immagine-mini'; mini.setAttribute('aria-hidden', 'true');
    var img = document.createElement('img'); img.alt = '';
    img.addEventListener('error', function () { if (img.getAttribute('src')) mini.classList.add('pn-immagine-rotta'); });
    img.addEventListener('load', function () { mini.classList.remove('pn-immagine-rotta'); });
    mini.appendChild(img);
    var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-secondary btn-sm pn-immagine-scegli';
    b.setAttribute('data-scegli-media', '#' + c.id); b.textContent = 'Scegli dai Media';
    var x = document.createElement('button'); x.type = 'button'; x.className = 'pn-immagine-x';
    x.setAttribute('aria-label', 'Togli l\'immagine'); x.title = 'Togli l\'immagine'; x.textContent = '×';
    x.addEventListener('click', function () { scriviCampo(c, ''); c.focus(); });
    // I due pulsanti vanno a capo insieme: la X non resta mai da sola su una riga.
    var az = document.createElement('span'); az.className = 'pn-immagine-azioni'; az.appendChild(b); az.appendChild(x);
    box.appendChild(mini); box.appendChild(c); box.appendChild(az);
    if (c.readOnly || c.disabled) { b.disabled = true; x.hidden = true; }
    var t = null;
    c.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { miniatura(box, c.value.trim()); }, 300); });
    c.addEventListener('change', function () { clearTimeout(t); miniatura(box, c.value.trim()); });
    miniatura(box, c.value.trim());
    if (c.readOnly || c.disabled) x.hidden = true;
  }
  function preparaTutti(dove) { (dove || document).querySelectorAll('input[data-immagine]').forEach(preparaCampo); }
  window.pnMedia.prepara = preparaTutti;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { preparaTutti(); }); else preparaTutti();

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-scegli-media], [data-scegli-media-lista]');
    if (!b) return;
    ev.preventDefault();
    if (b.hasAttribute('data-scegli-media-lista')) {
      var dove = document.querySelector(b.getAttribute('data-scegli-media-lista')), tpl = document.querySelector(b.getAttribute('data-scegli-media-modello'));
      if (!dove || !tpl) return;
      mediaApri({ titolo: b.getAttribute('data-scegli-media-titolo') || '', carica: b.getAttribute('data-scegli-media-carica') || '', scegli: function (u) {
        var copia = tpl.content.cloneNode(true);
        copia.querySelectorAll('[data-url-src]').forEach(function (e) { e.src = u; });
        copia.querySelectorAll('[data-url-valore]').forEach(function (e) { e.value = u; });
        dove.hidden = false; dove.appendChild(copia);
        dove.dispatchEvent(new Event('change', { bubbles: true }));
      } });
      return;
    }
    var c = document.querySelector(b.getAttribute('data-scegli-media'));
    if (!c) return;
    mediaApri({
      attuale: c.value.trim(),
      carica: c.getAttribute('data-immagine-carica') || b.getAttribute('data-scegli-media-carica') || '',
      assoluto: c.hasAttribute('data-immagine-assoluto'),
      scegli: function (u) { scriviCampo(c, u); }
    });
  });
})();
