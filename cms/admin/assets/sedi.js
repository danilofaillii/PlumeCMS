// 2.27.0: la pagina Sedi (admin/sedi.php). Tre cose, senza librerie e senza gestori in linea (la CSP a nonce li blocca):
//   1. la casella "Che attività è" compare solo con "Altro, lo scrivo io";
//   2. "Cerca sulla mappa" chiede le coordinate dell'indirizzo al server (seo-api.php, azione sedi_cerca: è il server a
//      parlare con il servizio delle mappe, mai il browser) e le scrive nei due campi;
//   3. la mini mappa: una griglia 3x3 di tessere di OpenStreetMap (https://tile.openstreetmap.org/{z}/{x}/{y}.png) intorno
//      alle coordinate, con il segnaposto; un clic sposta il segnaposto e ricalcola latitudine e longitudine con la
//      matematica delle tessere (Web Mercator); + e - cambiano lo zoom. Le tessere si chiedono solo quando la mappa è
//      aperta, cioè quando le coordinate ci sono: senza coordinate la mappa resta nascosta e nessuna richiesta parte.
(function () {
  'use strict';
  var $ = function (s) { return document.querySelector(s); };
  var tipo = $('#sd-tipo'), rigaTesto = $('#sd-tipo-testo-riga'), lat = $('#sd-lat'), lng = $('#sd-lng');
  var mappa = $('#sd-mappa'), tessere = $('#sd-tessere'), segnaposto = $('#sd-segnaposto'), esito = $('#sd-geo-esito'), cerca = $('#sd-cerca');
  if (!tipo || !lat || !lng || !mappa) return;

  // 1. Il tipo scritto a mano
  function tipoTesto() { var altro = tipo.value === 'altro'; rigaTesto.hidden = !altro; if (altro && document.activeElement === tipo) { var c = $('#sd-tipo-testo'); if (c) c.focus(); } }
  tipo.addEventListener('change', tipoTesto);
  tipoTesto();

  // 3. La mini mappa: matematica delle tessere
  var Z = 16, ZMIN = 3, ZMAX = 19;
  function n(z) { return Math.pow(2, z); }
  function lon2x(lon, z) { return (lon + 180) / 360 * n(z); }
  function lat2y(la, z) { var r = la * Math.PI / 180; return (1 - Math.log(Math.tan(r) + 1 / Math.cos(r)) / Math.PI) / 2 * n(z); }
  function x2lon(x, z) { return x / n(z) * 360 - 180; }
  function y2lat(y, z) { var t = Math.PI * (1 - 2 * y / n(z)); return Math.atan(Math.sinh(t)) * 180 / Math.PI; }
  function leggi() {
    var a = parseFloat(String(lat.value).replace(',', '.')), b = parseFloat(String(lng.value).replace(',', '.'));
    if (!isFinite(a) || !isFinite(b) || a < -85.05 || a > 85.05 || b < -180 || b > 180) return null;
    return { lat: a, lng: b };
  }
  function scrivi(p) { lat.value = p.lat.toFixed(6).replace(/0+$/, '').replace(/\.$/, ''); lng.value = p.lng.toFixed(6).replace(/0+$/, '').replace(/\.$/, ''); }
  var stato = { tx: 0, ty: 0 };
  function disegna() {
    var p = leggi();
    if (!p) { mappa.hidden = true; tessere.innerHTML = ''; return; }
    mappa.hidden = false;
    var fx = lon2x(p.lng, Z), fy = lat2y(p.lat, Z), tx = Math.floor(fx), ty = Math.floor(fy), N = n(Z);
    if (tx !== stato.tx || ty !== stato.ty || tessere.dataset.z !== String(Z) || !tessere.firstChild) {
      stato.tx = tx; stato.ty = ty; tessere.dataset.z = String(Z);
      var frag = document.createDocumentFragment();
      for (var dy = -1; dy <= 1; dy++) for (var dx = -1; dx <= 1; dx++) {
        var x = ((tx + dx) % N + N) % N, y = ty + dy, img = document.createElement('img');
        img.alt = '';
        if (y >= 0 && y < N) img.src = 'https://tile.openstreetmap.org/' + Z + '/' + x + '/' + y + '.png';
        frag.appendChild(img);
      }
      tessere.innerHTML = ''; tessere.appendChild(frag);
    }
    segnaposto.style.left = ((fx - (tx - 1)) / 3 * 100) + '%';
    segnaposto.style.top = ((fy - (ty - 1)) / 3 * 100) + '%';
    tessere.setAttribute('aria-label', 'Mappa intorno a latitudine ' + p.lat.toFixed(4) + ', longitudine ' + p.lng.toFixed(4) + ', zoom ' + Z + ': un clic sposta il segnaposto');
  }
  tessere.addEventListener('click', function (e) {
    if (!leggi()) return;
    var r = tessere.getBoundingClientRect();
    var px = (e.clientX - r.left) / r.width * 3 + (stato.tx - 1), py = (e.clientY - r.top) / r.height * 3 + (stato.ty - 1);
    scrivi({ lat: y2lat(py, Z), lng: x2lon(px, Z) });
    disegna();
    if (esito) { esito.textContent = 'Segnaposto spostato: latitudine ' + lat.value + ', longitudine ' + lng.value + '. Ricorda di salvare.'; esito.className = 'sd-ok'; esito.setAttribute('role', 'status'); }
  });
  document.querySelectorAll('[data-sd-zoom]').forEach(function (b) {
    b.addEventListener('click', function () { Z = Math.max(ZMIN, Math.min(ZMAX, Z + parseInt(b.getAttribute('data-sd-zoom'), 10))); disegna(); });
  });
  lat.addEventListener('change', disegna); lng.addEventListener('change', disegna);
  disegna();

  // 2. Cerca sulla mappa (dal server)
  function csrf() { var m = document.querySelector('meta[name="pn-csrf"]'); if (m) return m.getAttribute('content'); var i = document.querySelector('input[name="_csrf"]'); return i ? i.value : ''; }
  function valore(id) { var e = $(id); return e ? String(e.value).trim() : ''; }
  if (cerca) cerca.addEventListener('click', function () {
    var citta = [valore('#sd-cap'), valore('#sd-citta'), valore('#sd-provincia')].filter(Boolean).join(' ');
    var indirizzo = [valore('#sd-via'), citta, valore('#sd-paese')].filter(Boolean).join(', ');
    if (!valore('#sd-citta')) { esito.textContent = 'Scrivi almeno la città, poi riprova.'; esito.className = 'sd-no'; esito.setAttribute('role', 'alert'); $('#sd-citta').focus(); return; }
    esito.textContent = 'Cerco le coordinate di "' + indirizzo + '"...'; esito.className = ''; esito.setAttribute('role', 'status');
    cerca.disabled = true;
    var fd = new FormData(); fd.append('_csrf', csrf()); fd.append('azione', 'sedi_cerca'); fd.append('indirizzo', indirizzo);
    fetch('seo-api.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      cerca.disabled = false;
      if (!j || !j.ok) { esito.textContent = (j && j.errore) ? j.errore : 'Il servizio delle mappe non ha risposto. Scrivi le coordinate a mano.'; esito.className = 'sd-no'; esito.setAttribute('role', 'alert'); return; }
      lat.value = j.lat; lng.value = j.lng; Z = 16;
      disegna();
      esito.textContent = 'Trovato: ' + (j.nome || indirizzo) + '. Controlla il segnaposto sulla mappa e, se serve, spostalo con un clic. Poi salva.'; esito.className = 'sd-ok'; esito.setAttribute('role', 'status');
    }).catch(function () { cerca.disabled = false; esito.textContent = 'Il servizio delle mappe non ha risposto. Riprova fra poco o scrivi le coordinate a mano.'; esito.className = 'sd-no'; esito.setAttribute('role', 'alert'); });
  });
})();
