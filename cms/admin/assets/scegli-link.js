// 2.2: "Scegli dal sito". Una finestra del pannello che cerca per titolo fra pagine, articoli, categorie, tag e i contenuti
// degli addon con una pagina pubblica (eventi, prodotti, corsi...) e scrive il link nel campo, senza doverlo scrivere a mano.
// La casella resta scrivibile come prima: il pulsante aggiunge, non toglie. Le voci arrivano da link-api.php (includes/collegamenti.php).
// Nessun gestore in linea (la CSP del pannello li vieta): tutto con addEventListener.
//
//   <input type="text" data-link-sito ...>         accanto alla casella compare "Scegli dal sito"; scrive il percorso ("/contatti/")
//       data-link-sito="assoluto"                  scrive l'indirizzo intero ("https://esempio.it/contatti/"), per le email
//       data-link-sito="riga"                      (textarea) aggiunge il percorso su una riga nuova, per gli elenchi di indirizzi
//       data-link-sito-titolo="#id"                se quel campo è vuoto, ci scrive il titolo della pagina scelta (voce di menu)
//   window.pnLink.apri({ scegli: function (url, voce) {}, attuale: '...', assoluto: false, titolo: '...', manuale: true })
//       voce: { titolo, tipo, tipo_nome, stato, percorso, indirizzo, assoluto }, oppure null per un indirizzo scritto a mano
//   window.pnLink.campo(input, opzioni)            prepara una casella creata da uno script (l'editor visuale); ritorna il contenitore
//   window.pnLink.quill(quill, { assoluto })       il pulsante link di un editor Quill apre la finestra (con il testo scritto a mano)
//
// Tastiera: si apre con il fuoco sulla ricerca; freccia giù porta all'elenco, le frecce si muovono fra le voci, Invio sceglie,
// Esc chiude e il fuoco torna al pulsante che l'ha aperta. Sul telefono la finestra prende tutto lo schermo (scegli-link.css).
(function () {
  'use strict';
  if (window.pnLink) return;
  var L = { dlg: null, opz: null, giro: 0, cerca: null, timer: null, origine: null, tipiFatti: false };

  function token() {
    var m = document.querySelector('meta[name=pn-csrf]'), i = document.querySelector('input[name=_csrf]');
    return m ? m.content : (i ? i.value : '');
  }
  function q(sel) { return L.dlg.querySelector(sel); }
  function el(tag, cls, testo) { var e = document.createElement(tag); if (cls) e.className = cls; if (testo !== undefined) e.textContent = testo; return e; }
  function stato(testo, errore) { var s = q('.pn-link-stato'); s.textContent = testo || ''; s.classList.toggle('pn-link-errore', !!errore); }

  function crea() {
    var d = document.createElement('dialog');
    d.className = 'pn-link'; d.setAttribute('aria-labelledby', 'pn-link-titolo');
    var dentro = el('div', 'pn-link-dentro');
    var testa = el('div', 'pn-link-testa');
    var h = el('h3', '', 'Scegli dal sito'); h.id = 'pn-link-titolo';
    var x = el('button', 'pn-link-chiudi', '×'); x.type = 'button'; x.setAttribute('aria-label', 'Chiudi');
    testa.appendChild(h); testa.appendChild(x);
    var filtri = el('div', 'pn-link-filtri');
    var lc = el('label', 'pn-link-cerca'); lc.appendChild(el('span', 'pn-link-sr', 'Cerca nel titolo'));
    var cerca = document.createElement('input'); cerca.type = 'search'; cerca.placeholder = 'Cerca nel titolo…'; cerca.autocomplete = 'off'; cerca.maxLength = 100;
    lc.appendChild(cerca);
    var lt = el('label', 'pn-link-tipo-scelta'); lt.appendChild(el('span', 'pn-link-sr', 'Che cosa'));
    var sel = document.createElement('select'); var o = el('option', '', 'Tutto il sito'); o.value = ''; sel.appendChild(o);
    lt.appendChild(sel);
    filtri.appendChild(lc); filtri.appendChild(lt);
    var st = el('p', 'pn-link-stato'); st.setAttribute('role', 'status'); st.setAttribute('aria-live', 'polite');
    var corpo = el('div', 'pn-link-corpo');
    var lista = el('ul', 'pn-link-lista'); lista.setAttribute('aria-label', 'Pagine e contenuti del sito');
    corpo.appendChild(lista);
    var azioni = el('div', 'pn-link-azioni');
    var det = el('details', 'pn-link-mano'); det.appendChild(el('summary', '', 'Oppure scrivi un indirizzo'));
    var riga = el('div'); var lm = el('label'); lm.appendChild(el('span', 'pn-link-sr', 'Indirizzo'));
    var mano = document.createElement('input'); mano.type = 'text'; mano.placeholder = '/pagina/ oppure https://…'; mano.autocomplete = 'off'; mano.maxLength = 500;
    lm.appendChild(mano); riga.appendChild(lm);
    var usa = el('button', 'pn-link-b', 'Usa'); usa.type = 'button'; riga.appendChild(usa);
    det.appendChild(riga);
    var esci = el('button', 'pn-link-b pn-link-esci', 'Chiudi'); esci.type = 'button';
    azioni.appendChild(det); azioni.appendChild(esci);
    [testa, filtri, st, corpo, azioni].forEach(function (n) { dentro.appendChild(n); });
    d.appendChild(dentro);
    document.body.appendChild(d);
    L.dlg = d;

    x.addEventListener('click', function () { d.close(); });
    esci.addEventListener('click', function () { d.close(); });
    d.addEventListener('click', function (e) { if (e.target === d) d.close(); }); // clic sullo sfondo
    d.addEventListener('close', function () {
      if (L.cerca) L.cerca.abort();
      if (L.origine && document.contains(L.origine)) { try { L.origine.focus(); } catch (e) {} }
    });
    cerca.addEventListener('input', function () { clearTimeout(L.timer); L.timer = setTimeout(carica, 200); });
    cerca.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { var b = lista.querySelector('.pn-link-voce'); if (b) { e.preventDefault(); fuoco(b); } }
      if (e.key === 'Enter') {
        e.preventDefault(); clearTimeout(L.timer);
        // Invio nella ricerca: se c'è una voce sola (o la prima è uguale a quello che si è scritto), si sceglie quella.
        var voci = lista.querySelectorAll('.pn-link-voce');
        if (voci.length === 1) voci[0].click(); else carica();
      }
    });
    sel.addEventListener('change', carica);
    var usaMano = function () { var u = mano.value.trim(); if (u) scegli(u, null); };
    usa.addEventListener('click', usaMano);
    mano.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); usaMano(); } });
    lista.addEventListener('keydown', function (e) {
      var voci = Array.prototype.slice.call(lista.querySelectorAll('.pn-link-voce')), i = voci.indexOf(document.activeElement);
      if (i < 0) return;
      var n = i;
      if (e.key === 'ArrowDown') n = i + 1; else if (e.key === 'ArrowUp') n = i - 1;
      else if (e.key === 'Home') n = 0; else if (e.key === 'End') n = voci.length - 1;
      else if (e.key === 'PageDown') n = i + 8; else if (e.key === 'PageUp') n = i - 8;
      else return;
      e.preventDefault();
      if (n < 0) { cerca.focus(); return; }
      fuoco(voci[Math.min(voci.length - 1, n)]);
    });
  }
  // Una sola fermata di Tab per l'elenco (tabindex mobile), come la finestra dei Media.
  function fuoco(b) {
    L.dlg.querySelectorAll('.pn-link-voce[tabindex="0"]').forEach(function (x) { if (x !== b) x.tabIndex = -1; });
    b.tabIndex = 0; b.focus();
    if (b.scrollIntoView) b.scrollIntoView({ block: 'nearest' });
  }

  function uguale(v, attuale) {
    if (!attuale) return false;
    return attuale === v.percorso || attuale === v.indirizzo || attuale === v.assoluto;
  }
  function voce(v) {
    var li = el('li');
    var b = el('button', 'pn-link-voce'); b.type = 'button'; b.tabIndex = -1;
    var nome = el('span', 'pn-link-nome', v.titolo);
    var info = el('span', 'pn-link-info');
    info.appendChild(el('span', 'pn-link-tipo', v.tipo_nome));
    if (v.stato !== 'pubblicato') {
      var bz = el('span', 'pn-link-bozza', v.stato === 'programmato' ? 'programmato' : 'bozza');
      bz.title = v.stato === 'programmato' ? 'Non è ancora uscito: il link funziona dal giorno in cui esce.' : 'È una bozza: chi visita il sito non la vede finché non la pubblichi.';
      info.appendChild(bz);
    }
    info.appendChild(el('span', 'pn-link-percorso', L.opz.assoluto ? v.assoluto : v.indirizzo));
    b.appendChild(nome); b.appendChild(info);
    b.setAttribute('aria-label', v.titolo + ', ' + v.tipo_nome.toLowerCase() + (v.stato !== 'pubblicato' ? ', ' + (v.stato === 'programmato' ? 'programmato' : 'bozza') : '') + ', ' + v.indirizzo);
    b.dataset.percorso = v.percorso;
    if (uguale(v, L.opz.attuale)) { b.classList.add('pn-link-scelta'); b.setAttribute('aria-current', 'true'); }
    b.addEventListener('click', function () { scegli(L.opz.assoluto ? v.assoluto : v.percorso, v); });
    li.appendChild(b);
    return li;
  }

  function carica() {
    var lista = q('.pn-link-lista'), giro = ++L.giro;
    if (L.cerca) L.cerca.abort();
    lista.setAttribute('aria-busy', 'true');
    stato('Cerco…');
    var fd = new FormData();
    fd.set('_csrf', token()); fd.set('q', q('input[type=search]').value.trim()); fd.set('cosa', q('select').value);
    var ctrl = window.AbortController ? new AbortController() : null;
    L.cerca = ctrl;
    fetch('link-api.php', { method: 'POST', body: fd, credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok || j.errore) throw new Error(j.errore || 'Errore ' + r.status); return j; }); })
      .then(function (j) {
        if (giro !== L.giro) return;
        L.cerca = null;
        lista.removeAttribute('aria-busy');
        if (!L.tipiFatti && j.tipi) {
          L.tipiFatti = true;
          var sel = q('select');
          Object.keys(j.tipi).forEach(function (k) { var o = el('option', '', j.tipi[k]); o.value = k; sel.appendChild(o); });
        }
        lista.textContent = '';
        var frag = document.createDocumentFragment();
        (j.voci || []).forEach(function (v) { frag.appendChild(voce(v)); });
        lista.appendChild(frag);
        var prima = lista.querySelector('.pn-link-scelta') || lista.querySelector('.pn-link-voce');
        if (prima) prima.tabIndex = 0;
        var n = (j.voci || []).length, cerco = q('input[type=search]').value.trim();
        if (!n) {
          var p = el('li', 'pn-link-vuoto', cerco ? 'Niente con questo titolo. Prova un\'altra parola, oppure scrivi l\'indirizzo qui sotto.' : 'Il sito non ha ancora pagine da scegliere.');
          lista.appendChild(p);
          stato(cerco ? 'Nessun risultato.' : 'Nessuna pagina.');
        } else {
          stato((n === 1 ? '1 risultato' : n + ' risultati') + (j.altre ? ': ce ne sono altri, scrivi qualche lettera del titolo' : '') + '.');
        }
      })
      .catch(function (e) {
        if (e && e.name === 'AbortError') return;
        if (giro !== L.giro) return;
        lista.removeAttribute('aria-busy');
        stato('Elenco non raggiungibile (' + (e && e.message ? e.message : 'rete') + '). Puoi scrivere l\'indirizzo qui sotto.', true);
      });
  }

  function scegli(u, v) {
    var o = L.opz;
    L.dlg.close();
    if (o.scegli) o.scegli(u, v);
  }

  function apri(opz) {
    if (!L.dlg) crea();
    L.opz = opz || {};
    L.origine = document.activeElement;
    q('h3').textContent = L.opz.titolo || 'Scegli dal sito';
    var det = q('.pn-link-mano'); det.hidden = L.opz.manuale === false; det.open = false;
    q('.pn-link-mano input').value = '';
    q('input[type=search]').value = '';
    var sel = q('select'); while (sel.options.length > 1) sel.remove(1); sel.value = ''; L.tipiFatti = false;
    q('.pn-link-lista').textContent = '';
    stato('');
    if (!L.dlg.open) L.dlg.showModal();
    q('input[type=search]').focus();
    q('.pn-link-corpo').scrollTop = 0;
    carica();
  }

  // ------------------------------------------------------------ i campi con data-link-sito
  function scrivi(c, u) {
    c.value = u;
    c.dispatchEvent(new Event('input', { bubbles: true }));
    c.dispatchEvent(new Event('change', { bubbles: true }));
  }
  function campo(c, opz) {
    opz = opz || {};
    if (c.closest('.pn-link-campo') || c.hasAttribute('data-link-pronto')) return c.closest('.pn-link-campo');
    c.setAttribute('data-link-pronto', '');
    var modo = opz.modo !== undefined ? opz.modo : (c.getAttribute('data-link-sito') || '');
    var box = el('div', 'pn-link-campo' + (c.tagName === 'TEXTAREA' ? ' pn-link-campo-area' : ''));
    c.parentNode.insertBefore(box, c);
    box.appendChild(c);
    var b = el('button', (opz.classe || 'btn btn-secondary btn-sm') + ' pn-link-scegli', 'Scegli dal sito'); b.type = 'button';
    b.title = 'Scegli una pagina, un articolo o un altro contenuto del sito';
    if (c.readOnly || c.disabled) b.disabled = true;
    box.appendChild(b);
    b.addEventListener('click', function (e) {
      e.preventDefault();
      apri({
        titolo: opz.titolo || '',
        assoluto: modo === 'assoluto',
        attuale: modo === 'riga' ? '' : c.value.trim(),
        scegli: function (u, v) {
          if (modo === 'riga') { var t = c.value.replace(/\s+$/, ''); u = (t ? t + '\n' : '') + u; }
          if (opz.scegli) opz.scegli(u, v); else scrivi(c, u);
          var ts = c.getAttribute('data-link-sito-titolo'), tc = ts ? document.querySelector(ts) : null;
          if (tc && v && !tc.value.trim()) scrivi(tc, v.titolo);
          c.focus();
        }
      });
    });
    return box;
  }
  function prepara(dove) { (dove || document).querySelectorAll('input[data-link-sito], textarea[data-link-sito]').forEach(function (c) { campo(c); }); }

  // Quill: il pulsante link apre la finestra. Con del testo selezionato lo fa diventare un link; senza, scrive il titolo
  // della pagina scelta come link. Sopra un link c'è già, il pulsante lo toglie (come prima). Il fumetto di Quill per
  // modificare o togliere un link esistente resta quello di sempre.
  function quill(qu, opz) {
    opz = opz || {};
    var tb = qu.getModule && qu.getModule('toolbar');
    if (!tb) return;
    tb.addHandler('link', function (valore) {
      var r = qu.getSelection(true);
      if (!valore) { qu.format('link', false, 'user'); return; }
      apri({
        assoluto: !!opz.assoluto,
        titolo: 'Inserisci un link',
        scegli: function (u, v) {
          qu.focus();
          if (r && r.length > 0) qu.formatText(r.index, r.length, 'link', u, 'user');
          else {
            var i = r ? r.index : qu.getLength() - 1, t = v ? v.titolo : u;
            qu.insertText(i, t, 'link', u, 'user');
            qu.setSelection(i + t.length, 0, 'user');
          }
        }
      });
    });
  }

  window.pnLink = { apri: apri, campo: campo, prepara: prepara, quill: quill };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { prepara(); }); else prepara();
  // I campi che arrivano dopo (le voci del menu, i blocchi aggiunti da uno script) si preparano da soli.
  if ('MutationObserver' in window) {
    new MutationObserver(function (m) {
      for (var i = 0; i < m.length; i++) for (var j = 0; j < m[i].addedNodes.length; j++) {
        var n = m[i].addedNodes[j];
        if (n.nodeType !== 1) continue;
        if (n.matches && n.matches('input[data-link-sito], textarea[data-link-sito]')) campo(n);
        else if (n.querySelector && n.querySelector('[data-link-sito]')) prepara(n);
      }
    }).observe(document.documentElement, { childList: true, subtree: true });
  }
})();
