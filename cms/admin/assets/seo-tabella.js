/*
 * 2.29.0: la Tabella SEO (admin/seo-tabella.php). Ogni casella (titolo per Google, descrizione) si salva quando si esce dalla
 * casella o con Invio nel titolo: POST a seo-api.php (azione tabella_salva, token CSRF) e i semafori di tutte le righe si
 * aggiornano con la risposta (un doppione risolto cambia anche l'altra riga). Filtri: ricerca, tipo, solo problemi; ordine per
 * semaforo, titolo o tipo. Niente gestori in linea (la CSP del pannello li blocca).
 */
(function () {
  var tab = document.getElementById('st-tabella');
  if (!tab) return;
  var csrf = tab.getAttribute('data-csrf');
  var tb = tab.tBodies[0];
  var righe = Array.prototype.slice.call(tb.rows);
  var cerca = document.getElementById('st-cerca'), tipo = document.getElementById('st-tipo'), ordina = document.getElementById('st-ordina'), solo = document.getElementById('st-solo-problemi');
  var nessuno = document.getElementById('st-nessuno');
  var SEGNO = { verde: '✓', giallo: '!', rosso: '✗' }, PAROLA = { verde: 'a posto', giallo: 'da migliorare', rosso: 'da sistemare' };

  function semaforo(td, s, cosa) {
    var sp = td.querySelector('.st-sem');
    if (!sp) return;
    sp.className = 'st-sem st-' + s.colore; sp.setAttribute('data-sem', s.colore);
    sp.innerHTML = '';
    var seg = document.createElement('span'); seg.className = 'st-segno'; seg.setAttribute('aria-hidden', 'true'); seg.textContent = SEGNO[s.colore] || '';
    var sr = document.createElement('span'); sr.className = 'sr-solo'; sr.textContent = cosa + ' ' + PAROLA[s.colore] + ': ';
    sp.appendChild(seg); sp.appendChild(sr); sp.appendChild(document.createTextNode(s.testo));
  }
  function aggiornaRiga(tr, x) {
    var celle = tr.querySelectorAll('td.st-campo');
    semaforo(celle[0], x.sem_titolo, 'Titolo'); semaforo(celle[1], x.sem_descrizione, 'Descrizione');
    tr.setAttribute('data-problemi', x.problemi);
    tr.setAttribute('data-peso', (x.sem_titolo.colore === 'rosso' ? 0 : x.sem_titolo.colore === 'giallo' ? 1 : 2) + (x.sem_descrizione.colore === 'rosso' ? 0 : x.sem_descrizione.colore === 'giallo' ? 1 : 2));
  }
  function trova(tipoR, id) { return righe.filter(function (r) { return r.getAttribute('data-tipo') === tipoR && r.getAttribute('data-id') === String(id); })[0]; }

  function salva(campo) {
    var tr = campo.closest('tr'), esito = campo.parentNode.querySelector('.st-esito');
    if (campo.value === campo.getAttribute('data-salvato')) return;
    var fd = new FormData();
    fd.append('_csrf', csrf); fd.append('azione', 'tabella_salva'); fd.append('tipo', tr.getAttribute('data-tipo')); fd.append('id', tr.getAttribute('data-id'));
    fd.append(campo.getAttribute('data-campo'), campo.value);
    esito.className = 'st-esito'; esito.textContent = 'Salvo...';
    campo.disabled = true;
    fetch('seo-api.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { errore: 'Risposta del server non leggibile (' + r.status + ').' }; }); })
      .then(function (j) {
        if (j.errore) throw new Error(j.errore);
        campo.setAttribute('data-salvato', campo.value);
        if (j.riga) {
          if (campo.getAttribute('data-campo') === 'seo_title') { campo.value = j.riga.seo_title; campo.placeholder = j.riga.titolo_auto; campo.setAttribute('data-salvato', j.riga.seo_title); }
          else { campo.value = j.riga.descrizione; campo.setAttribute('data-salvato', j.riga.descrizione); }
        }
        (j.righe || []).forEach(function (x) { var r = trova(x.tipo, x.id); if (r) aggiornaRiga(r, x); });
        var np = document.getElementById('st-n-problemi'); if (np && typeof j.con_problemi === 'number') np.textContent = j.con_problemi;
        esito.className = 'st-esito st-ok'; esito.setAttribute('role', 'status'); esito.textContent = 'Salvato';
        setTimeout(function () { if (esito.textContent === 'Salvato') esito.textContent = ''; }, 2500);
        filtra();
      })
      .catch(function (e) { esito.className = 'st-esito st-no'; esito.setAttribute('role', 'alert'); esito.textContent = 'Non salvato: ' + e.message; })
      .then(function () { campo.disabled = false; });
  }
  righe.forEach(function (tr) {
    Array.prototype.forEach.call(tr.querySelectorAll('[data-campo]'), function (c) {
      c.setAttribute('data-salvato', c.value);
      c.addEventListener('change', function () { salva(c); });
      if (c.tagName === 'INPUT') c.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); salva(c); } });
    });
  });

  function filtra() {
    var q = (cerca.value || '').trim().toLowerCase(), t = tipo.value, sp = solo.checked, visibili = 0;
    righe.forEach(function (r) {
      var ok = (!q || r.getAttribute('data-cerca').indexOf(q) >= 0) && (!t || r.getAttribute('data-tipo') === t) && (!sp || +r.getAttribute('data-problemi') > 0);
      r.hidden = !ok; if (ok) visibili++;
    });
    if (nessuno) nessuno.hidden = visibili > 0;
    var ord = ordina.value;
    var copia = righe.slice().sort(function (a, b) {
      if (ord === 'titolo') return a.getAttribute('data-titolo') < b.getAttribute('data-titolo') ? -1 : 1;
      if (ord === 'tipo') { var x = a.getAttribute('data-tipo-nome'), y = b.getAttribute('data-tipo-nome'); if (x !== y) return x < y ? -1 : 1; return +a.getAttribute('data-ordine') - +b.getAttribute('data-ordine'); }
      var d = +a.getAttribute('data-peso') - +b.getAttribute('data-peso');
      return d !== 0 ? d : (a.getAttribute('data-titolo') < b.getAttribute('data-titolo') ? -1 : 1);
    });
    copia.forEach(function (r) { tb.appendChild(r); });
  }
  [cerca, tipo, ordina, solo].forEach(function (el) { if (el) el.addEventListener(el.tagName === 'INPUT' && el.type === 'search' ? 'input' : 'change', filtra); });
  var form = document.getElementById('st-filtri'); if (form) form.addEventListener('submit', function (e) { e.preventDefault(); filtra(); });
})();
