// Form > passo 2, i campi del form (admin/form.php). Senza gestori in linea (CSP): pannello.js chiama queste funzioni dagli
// attributi data-al-*. I dati arrivano dalla pagina: fmTipi, fmCampi, fmFile, fmPesoServer e, dalla 2.16.0, fmAttr (gli
// attributi dei contatti), fmCompat (i tipi di campo che vanno con ogni tipo di attributo), fmListe, fmGruppi.
/* global fmTipi, fmCampi, fmFile, fmPesoServer, fmAttr, fmCompat, fmListe, fmGruppi, fmGruppiAttivi, fmGenerico, Sortable */
// Le opzioni di una scelta possono arrivare come elenco (dal database) o come testo (appena scritto): sempre testo, una per riga.
function fmOpzioni(c) {
  if (Array.isArray(c.opzioni)) return c.opzioni.join('\n');
  return String(c.opzioni == null ? '' : c.opzioni);
}
function fmScelta(tipo) { return tipo === 'scelta' || tipo === 'multiscelta'; }
function fmE(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
// Gli attributi che vanno con il tipo del campo (EMAIL solo con il campo Email, e il campo Email solo con EMAIL).
function fmAttrPer(tipo) {
  return Object.keys(fmAttr).filter(function (n) {
    var a = fmAttr[n];
    if (n === 'EMAIL') return tipo === 'email';
    if (tipo === 'email') return false;
    return (fmCompat[a.tipo] || []).indexOf(tipo) >= 0;
  });
}
function fmSelectAttr(campo, i) {
  if (['liste', 'gruppi', 'tracciamento', 'file', 'ora'].indexOf(campo.tipo) >= 0) return '';
  var ok = fmAttrPer(campo.tipo);
  if (campo.attributo && ok.indexOf(campo.attributo) < 0) campo.attributo = '';
  var id = 'fm-attr-' + i;
  return '<label class="fm-attr" for="' + id + '">Attributo <select id="' + id + '" class="fm-attributo" data-al-cambio="fmImposta(' + i + ', \'attributo\', this.value, true)">'
    + '<option value="">' + (fmGenerico ? '(nessuno: solo nelle compilazioni)' : '(nessuno)') + '</option>'
    + ok.map(function (n) { return '<option value="' + fmE(n) + '"' + (campo.attributo === n ? ' selected' : '') + '>' + fmE(fmAttr[n].etichetta) + ' (' + fmE(n) + ')</option>'; }).join('')
    + '</select></label>';
}
function fmScegliId(campo, i, chiave, elenco, legenda) {
  var scelti = Array.isArray(campo[chiave]) ? campo[chiave].map(Number) : [];
  if (!elenco.length) return '<span class="fm-nota">' + (chiave === 'liste' ? 'Ancora nessuna lista: creale in Contatti, Liste.' : 'Ancora nessun gruppo di consenso: crealo in Pagine e consenso.') + '</span>';
  return '<fieldset class="fm-scelta-liste"><legend>' + legenda + '</legend>' + elenco.map(function (x) {
    return '<label><input type="checkbox" value="' + x.id + '"' + (scelti.indexOf(x.id) >= 0 ? ' checked' : '') + ' data-al-cambio="fmScegli(' + i + ', \'' + chiave + '\', ' + x.id + ', this.checked)"> ' + fmE(x.nome) + '</label>';
  }).join('') + '</fieldset>';
}
function fmScegli(i, chiave, id, on) {
  var c = fmCampi[i]; if (!c) return;
  var l = (Array.isArray(c[chiave]) ? c[chiave].map(Number) : []).filter(function (x) { return x !== id; });
  if (on) l.push(id);
  c[chiave] = l;
}
function fmRiga2(campo, i) {
  var a = campo.attributo && fmAttr[campo.attributo];
  if (campo.tipo === 'liste') return fmScegliId(campo, i, 'liste', fmListe, 'Le liste fra cui scegliere');
  if (campo.tipo === 'gruppi') return fmScegliId(campo, i, 'gruppi', fmGruppi, 'I gruppi fra cui scegliere (nessuno spuntato: tutti)') + (fmGruppiAttivi ? '' : '<span class="fm-nota">I gruppi di consenso sono spenti: il blocco compare nel form quando li accendi in Pagine e consenso.</span>');
  if (campo.tipo === 'tracciamento') return '<span class="fm-nota">La casella parte vuota. Chi non la spunta riceve le email senza che si contino aperture e clic.</span>';
  if (fmScelta(campo.tipo) && a && a.opzioni && a.opzioni.length) return '<span class="fm-nota">Le opzioni sono quelle dell\'attributo: ' + fmE(a.opzioni.join(', ')) + '.</span>';
  if (fmScelta(campo.tipo)) return '<textarea class="fm-opzioni" rows="3" placeholder="Opzioni tra cui scegliere: una per riga" aria-label="Opzioni tra cui scegliere, una per riga" data-al-input="fmImposta(' + i + ', \'opzioni\', this.value)">' + fmE(fmOpzioni(campo)) + '</textarea>';
  if (campo.tipo === 'file') return fmRegoleFile(campo, i);
  if (campo.tipo === 'spunta' || campo.tipo === 'data' || campo.tipo === 'ora') return '';
  return '<input type="text" class="fm-segnaposto" placeholder="Suggerimento dentro il campo (facoltativo)" aria-label="Suggerimento dentro il campo" value="' + fmE(campo.segnaposto) + '" data-al-input="fmImposta(' + i + ', \'segnaposto\', this.value)">';
}
function fmDisegna() {
  var c = document.getElementById('fm-campi'); c.innerHTML = '';
  fmCampi.forEach(function (campo, i) {
    if (!campo || typeof campo !== 'object') return;
    var d = document.createElement('div'); d.className = 'fm-campo';
    d.innerHTML = '<div class="fm-riga"><span class="fm-maniglia" title="Trascina" aria-hidden="true">⋮⋮</span>'
      + '<input type="text" class="fm-etichetta" placeholder="Etichetta" aria-label="Etichetta del campo" value="' + fmE(campo.etichetta) + '" data-al-input="fmImposta(' + i + ', \'etichetta\', this.value)">'
      + '<select class="fm-tipo" aria-label="Tipo del campo" data-al-cambio="fmImposta(' + i + ', \'tipo\', this.value, true)">' + Object.keys(fmTipi).map(function (k) { return '<option value="' + k + '"' + (campo.tipo === k ? ' selected' : '') + '>' + fmTipi[k] + '</option>'; }).join('') + '</select>'
      + (campo.tipo === 'tracciamento' ? '' : '<label class="fm-obb"><input type="checkbox"' + (campo.obbligatorio ? ' checked' : '') + ' data-al-cambio="fmImposta(' + i + ', \'obbligatorio\', this.checked)"> obbligatorio</label>')
      + '<button type="button" class="fm-via" title="Togli" aria-label="Togli il campo ' + fmE(campo.etichetta) + '" data-al-clic="fmTogli(' + i + ')">×</button></div>'
      + '<div class="fm-riga fm-riga2">' + fmRiga2(campo, i) + fmSelectAttr(campo, i)
      + (fmGenerico && ['testo', 'email', 'telefono'].indexOf(campo.tipo) >= 0 ? '<select class="fm-meta" aria-label="A cosa serve questo campo" data-al-cambio="fmImposta(' + i + ', \'meta\', this.value)" title="A cosa serve questo campo"><option value="">(dato generico)</option><option value="nome"' + (campo.meta === 'nome' ? ' selected' : '') + '>è il nome</option><option value="cognome"' + (campo.meta === 'cognome' ? ' selected' : '') + '>è il cognome</option><option value="email"' + (campo.meta === 'email' ? ' selected' : '') + '>è l\'email</option><option value="cellulare"' + (campo.meta === 'cellulare' ? ' selected' : '') + '>è il cellulare</option></select>' : '')
      + '</div>';
    c.appendChild(d);
  });
}
// 2.1: un campo File: quali file accetta (caselle) e quanto pesa al massimo.
function fmRegoleFile(campo, i) {
  if (!Array.isArray(campo.estensioni) || !campo.estensioni.length) campo.estensioni = ['pdf', 'jpg', 'png'];
  if (!campo.peso) campo.peso = 5;
  var pesi = [1, 2, 3, 5, 8, 10].filter(function (p) { return p <= Math.max(1, Math.floor(fmPesoServer)); });
  if (pesi.indexOf(+campo.peso) < 0) pesi.push(+campo.peso);
  return '<div class="fm-file"><fieldset class="fm-file-tipi"><legend>File che accetta</legend>' + Object.keys(fmFile).map(function (e) {
      return '<label><input type="checkbox" value="' + e + '"' + (campo.estensioni.indexOf(e) >= 0 ? ' checked' : '') + ' data-al-cambio="fmEstensione(' + i + ', \'' + e + '\', this.checked)"> ' + fmE(fmFile[e]) + '</label>';
    }).join('') + '</fieldset>'
    + '<label class="fm-file-peso">Peso massimo <select data-al-cambio="fmImposta(' + i + ', \'peso\', this.value)">' + pesi.map(function (p) { return '<option value="' + p + '"' + (+campo.peso === p ? ' selected' : '') + '>' + p + ' MB</option>'; }).join('') + '</select></label>'
    + '<small class="fm-file-nota">Il file va in una cartella riservata: lo scarichi da Invii dei form, e arriva allegato all\'avviso se è piccolo. Il server accetta al massimo ' + String(fmPesoServer).replace('.', ',') + ' MB.</small></div>';
}
function fmEstensione(i, e, on) {
  var c = fmCampi[i]; if (!c) return;
  var l = (c.estensioni || []).filter(function (x) { return x !== e; });
  if (on) l.push(e);
  c.estensioni = l.length ? l : [e];
  if (!l.length) fmDisegna(); // almeno un tipo resta sempre scelto
}
// Le etichette di partenza dei blocchi della newsletter, quando si sceglie il tipo e l'etichetta è ancora quella generica.
var fmEtichette = { liste: 'Cosa vuoi ricevere', gruppi: 'I tuoi consensi', tracciamento: 'Acconsento a far sapere se apro le email e clicco i link' };
function fmImposta(i, k, v, ridisegna) {
  var c = fmCampi[i]; if (!c) return;
  c[k] = v;
  if (k === 'tipo' && fmEtichette[v] && (c.etichetta === 'Nuovo campo' || c.etichetta === '')) c.etichetta = fmEtichette[v];
  if (k === 'tipo' && v === 'tracciamento') c.obbligatorio = false;
  if (ridisegna) fmDisegna();
}
function fmTogli(i) { fmCampi.splice(i, 1); fmDisegna(); }
function fmAggiungi() { fmCampi.push({ nome: '', etichetta: 'Nuovo campo', tipo: 'testo', obbligatorio: false, segnaposto: '', opzioni: [], meta: '', attributo: '' }); fmDisegna(); var u = document.querySelectorAll('.fm-etichetta'); if (u.length) { u[u.length - 1].focus(); u[u.length - 1].select(); } }
fmDisegna();
new Sortable(document.getElementById('fm-campi'), { handle: '.fm-maniglia', animation: 120, onEnd: function (e) { if (e.oldIndex === e.newIndex) return; var v = fmCampi.splice(e.oldIndex, 1)[0]; fmCampi.splice(e.newIndex, 0, v); fmDisegna(); } });
document.getElementById('fm-form').addEventListener('submit', function () {
  document.getElementById('fm-campi-json').value = JSON.stringify(fmCampi.map(function (c) { return Object.assign({}, c, { opzioni: fmOpzioni(c) }); }));
});
