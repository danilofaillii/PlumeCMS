// Il sito di plumeCMS (1.2.3, 27 ottobre 2026): il pacchetto di sito-plumecms/costruisci.py caricato e applicato dal
// pannello Aggiornamenti come un utente, poi il sito visitato pagina per pagina e i file scaricati davvero.
// uso: node prova-sito-plumecms.js <base> <cartella CMS installato> <utente> <password> <pacchetto.zip[,parte-2.zip,...]> <versione>
// I pacchetti si caricano e si applicano uno dopo l'altro, in ordine, come li carica chi gestisce il sito. Dalla 2.1.0 di solito
// è uno solo: il pacchetto del sito (sito-plumecms-<versione>.zip) oppure il pacchetto unico di tools/consegna.py, che porta
// anche l'aggiornamento del CMS (allora si controlla anche che il CMS sia passato alla versione).
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello'); // UX 2026-11: conferme del pannello
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, ZIP, V] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 220) : '')); };

// Lo stato del pacchetto si legge dal suo file in aggiornamenti/pacchetti (scritto dal motore alla fine).
const statoPacchetto = (nome) => {
  const d = path.join(DIR, 'aggiornamenti', 'pacchetti');
  const s = fs.readdirSync(d).filter(f => f.endsWith('.json')).map(f => JSON.parse(fs.readFileSync(path.join(d, f), 'utf8')))
    .filter(j => (j.nome || (j.manifest && j.manifest.nome) || '') === nome).sort((a, b) => String(b.id).localeCompare(String(a.id)));
  return s[0] || null;
};

// Per occhiello, titolo e testo della Copertina: [pezzo, contrasto del bianco sul fondo]. Il fondo e la pagina
// fotografata con le parole nascoste; i pixel si leggono in un canvas, nella pagina stessa.
async function contrastoParole(pg) {
  const r = await pg.evaluate(() => [...document.querySelectorAll('.ed-hcop-parole .occhiello, .ed-hcop-parole h1, .ed-hcop-parole p.lead')]
    .map(e => { const b = e.getBoundingClientRect(); return [e.tagName.toLowerCase(), Math.round(b.x), Math.round(b.y + scrollY), Math.round(b.width), Math.round(b.height)]; }));
  const st = await pg.addStyleTag({ content: '.ed-hcop-parole{visibility:hidden}' });
  const foto = (await pg.screenshot({ fullPage: true })).toString('base64');
  await st.evaluate(e => e.remove());
  return pg.evaluate(async ([foto, r]) => {
    const im = new Image(); im.src = 'data:image/png;base64,' + foto; await im.decode();
    const cv = document.createElement('canvas'); cv.width = im.width; cv.height = im.height;
    const cx = cv.getContext('2d'); cx.drawImage(im, 0, 0);
    const lin = v => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    const k = im.width / document.documentElement.clientWidth;      // pixel della foto per pixel CSS
    return r.map(([t, x, y, w, h]) => {
      const d = cx.getImageData(Math.round(x * k), Math.round(y * k), Math.round(w * k), Math.round(h * k)).data, l = [];
      for (let i = 0; i < d.length; i += 4 * 3) l.push(0.2126 * lin(d[i]) + 0.7152 * lin(d[i + 1]) + 0.0722 * lin(d[i + 2]));
      l.sort((a, b) => a - b);
      return [t, Math.round(1.05 / (l[Math.floor(l.length * 0.99)] + 0.05) * 10) / 10];
    });
  }, [foto, r]);
}

(async () => {
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  pnConferme.on(p, d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok('login', !p.url().includes('login.php'), p.url());

  const zips = ZIP.split(',');
  let log = '';
  let cmsNelPacchetto = false, conHome = false;
  for (const [k, z] of zips.entries()) {
    // Il nome con cui il pannello lo elenca è quello del suo manifest.
    const man = JSON.parse(execFileSync('python3', ['-I', '-c', 'import sys,zipfile;print(zipfile.ZipFile(sys.argv[1]).read("manifest.json").decode())', z], { encoding: 'utf8' }));
    const nome = man.nome;
    if ((man.file || []).some(f => f.a === 'new/VERSIONE')) cmsNelPacchetto = true;
    if ((man.pagine || []).some(x => /(^|\/)pagine\/home\.json$/.test(x))) conHome = true; // 2.18.0: costruisci.py --senza-home la lascia fuori
    await p.goto(B + '/admin/aggiornamenti.php', { waitUntil: 'networkidle' });
    await p.setInputFiles('input[name=pacchetto]', z);
    await Promise.all([p.waitForNavigation(), p.click('form:has(input[name=pacchetto]) button[type=submit]')]);
    const testo = await p.locator('body').innerText();
    ok(`parte ${k + 1}: caricata (${Math.round(fs.statSync(z).size / 1024)} KB)`, testo.includes(nome) && !/supera il limite/.test(testo), testo.slice(0, 160));
    await Promise.all([p.waitForNavigation({ timeout: 180000 }), p.locator('tr', { hasText: nome }).locator('button:has-text("Applica ora")').first().click()]);
    let st = null;
    for (let i = 0; i < 120; i++) { st = statoPacchetto(nome); if (st && ['applicato', 'fallito'].includes(st.stato)) break; await p.waitForTimeout(1000); }
    ok(`parte ${k + 1}: applicata`, st && st.stato === 'applicato', st && (st.stato + ' ' + (st.log || []).slice(-2).join(' | ')));
    log += (st && st.log || []).join('\n') + '\n';
  }
  ok('resoconto: nome del sito, stile, header e footer, menu', /Impostazioni del sito/.test(log) && /Stile del sito aggiornato/.test(log) && /Header e footer: 2/.test(log) && /Menu di navigazione: 3/.test(log), log.slice(0, 300));
  ok('resoconto: ' + (conHome ? 'sette pagine' : 'sei pagine, la home resta quella del sito'), (log.match(/^Pagina \//gm) || []).length === (conHome ? 7 : 6) && (conHome || !/^Pagina \/home\//m.test(log)));
  if (cmsNelPacchetto) {
    // 2.1.0, il pacchetto unico: nello stesso giro anche il codice del CMS.
    ok(`pacchetto unico: il CMS è passato alla ${V} (VERSIONE e pannello Informazioni)`, fs.readFileSync(path.join(DIR, 'VERSIONE'), 'utf8').trim() === V
      && (await (async () => { await p.goto(B + '/admin/informazioni.php', { waitUntil: 'networkidle' }); return (await p.locator('body').innerText()).includes(V); })()));
    ok('pacchetto unico: il resoconto ha i file del CMS e le pagine del sito', /File scritto: new\/includes\/aggiornamenti\.php/.test(log) && /File scritto: uploads\/scaricabili\/manuale-uso\.pdf/.test(log));
  }
  ok('nessun errore JavaScript nel pannello', err.length === 0, err);

  // Il sito, come un visitatore (senza login).
  const v = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const errV = []; v.on('pageerror', e => errV.push(e.message.slice(0, 200)));
  v.on('console', m => { if (m.type() === 'error' && !/favicon/.test(m.text())) errV.push(m.text().slice(0, 200)); });
  // Con la hero tenuta (2.18.0, "l'apertura resta quella del sito") il titolo della home è quello del sito, non quello del pacchetto.
  const tenuta = /l'apertura resta quella del sito/.test(log);
  const pagine = [...(conHome ? [['/', tenuta ? /./ : /leggero come una piuma/i]] : []), ['/funzionalita/', /vuoto/], ['/temi/', /Cambia vestito/], ['/documentazione/', /da leggere anche offline/],
    ['/installazione/', /Installalo in 5 minuti/], ['/scarica/', new RegExp('Scarica plumeCMS ' + V.replace(/\./g, '\\.') + '$')], ['/domande-frequenti/', /Domande frequenti/]];
  for (const [u, re] of pagine) {
    const r = await v.goto(B + u, { waitUntil: 'networkidle' });
    const h1 = (await v.locator('h1').first().innerText({ timeout: 15000 }).catch(async () => 'senza h1: ' + (await v.title()) + ' | ' + (await v.locator('body').innerText()).slice(0, 300))).replace(/\s+/g, ' ');
    ok(`${u} risponde 200 con il suo titolo`, r.status() === 200 && re.test(h1), [r.status(), h1]);
  }
  await v.goto(B + '/', { waitUntil: 'networkidle' });
  ok('titolo della scheda: plumeCMS', /plumeCMS/.test(await v.title()), await v.title());
  const voci = (await v.locator('header nav a').allInnerTexts()).map(s => s.trim()).filter(Boolean);
  ok('menu: Home, Funzionalità, Temi, Documentazione, Installazione, Scarica', ['Home', 'Funzionalità', 'Temi', 'Documentazione', 'Installazione'].every(x => voci.some(y => y.toLowerCase() === x.toLowerCase())) && voci.some(y => /scarica/i.test(y)), voci);
  if (conHome) {
  ok("l'onda non c'è nella home", await v.locator('.chip-onda').count() === 0);
  // Dalla 1.2.6 la home apre con la Copertina; dalla notte del 27 ottobre con la piuma di copertina.js, a colori
  // (trattamento naturale) e con lo zoom lento. La foto deve essere caricata davvero e restare leggera.
  ok('la home apre con la Copertina: la piuma a tutto sfondo, naturale, con lo zoom', await v.locator('.ed-hero-copertina .finestra.ed-foto-naturale.ed-fx-zoom img[src*="copertina.webp"]').count() === 1);
  const cop = await v.evaluate(() => { const i = document.querySelector('.ed-hero-copertina img'); return i ? { w: i.naturalWidth, h: i.naturalHeight } : null; });
  const copPeso = (await (await v.request.get(B + '/uploads/plumecms/copertina.webp')).body()).length;
  ok('la piuma: caricata (1440 di larghezza) e sotto 150 KB', cop && cop.w === 1440 && copPeso < 150 * 1024, [cop, Math.round(copPeso / 1024) + ' KB']);
  // Al telefono la foto si ritaglia: il CSS del sito sposta il punto di vista a destra, dove sta la piuma.
  const tel = await (await br.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, reducedMotion: 'reduce' })).newPage();
  await tel.goto(B + '/', { waitUntil: 'networkidle' });
  const posTel = await tel.evaluate(() => getComputedStyle(document.querySelector('.ed-hero-copertina img')).objectPosition);
  ok('al telefono la Copertina guarda la piuma (object-position 88%)', /^88%/.test(posTel), posTel);
  // Il contrasto delle parole bianche sulla piuma, misurato sul sito vero: si nascondono le parole, si fotografa il
  // fondo e per occhiello, titolo e testo si prende la luminanza del fondo al 99% (i punti piu chiari dietro le lettere).
  const pc = await (await br.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' })).newPage();
  await pc.goto(B + '/', { waitUntil: 'networkidle' });
  for (const [nome, pg] of [['computer', pc], ['telefono', tel]]) {
    const c = await contrastoParole(pg);
    ok(`Copertina al ${nome}: parole bianche leggibili sulla piuma (contrasto 4,5 o più)`, c.every(x => x[1] >= 4.5), c);
  }
  await pc.close(); await tel.close();
  // Chi chiede meno movimento vede la piuma ferma (lo zoom lento si spegne, sito.css).
  const fermo = await (await br.newContext({ reducedMotion: 'reduce' })).newPage();
  await fermo.goto(B + '/', { waitUntil: 'networkidle' });
  ok('con meno movimento la piuma sta ferma', await fermo.evaluate(() => getComputedStyle(document.querySelector('.ed-hero-copertina img')).animationName) === 'none');
  await fermo.close();
  const imgRotte = await v.evaluate(() => [...document.images].filter(i => i.complete && i.naturalWidth === 0).map(i => i.src));
  ok('nessuna immagine rotta nella home', imgRotte.length === 0, imgRotte);
  } // fine dei controlli sulla home (2.18.0: solo quando il pacchetto la porta)
  await v.goto(B + '/funzionalita/', { waitUntil: 'networkidle' });
  const imgF = await v.evaluate(async () => { for (const i of document.images) { i.loading = 'eager'; } await new Promise(r => setTimeout(r, 1500)); return [...document.images].filter(i => i.naturalWidth === 0).map(i => i.src); });
  ok('nessuna immagine rotta in Funzionalità', imgF.length === 0, imgF);
  ok('Funzionalità (1.8.0): la frase in fondo al sito, con il link al sito ufficiale', await v.locator('#firma-sito a[href="https://plumecms.it"]').count() === 1);
  // Regola di Danilo (2 novembre 2026): Funzionalità mostra le 10 caratteristiche principali, non un blocco per versione.
  ok('Funzionalità: dieci caratteristiche, non una per versione', await v.locator('#editor, #partenza, #aspetto, #newsletter, #automazioni, #form, #seo, #social, #addon, #sicurezza').count() === 10 && await v.locator('#report, #test-ab, #firma, #download, #installa-ora, #kit, #versioni').count() === 0);
  ok('Funzionalità (2.0.0): più di 500 attacchi respinti, addon compresi', /più di 500 attacchi, addon compresi/.test(await v.locator('#sicurezza').innerText()));
  ok('favicon del sito', await v.locator('link[rel=icon][href*="favicon.png"]').count() === 1);

  // "Installalo in 5 minuti" (pagina Installazione): cosa serve prima e il carosello delle cinque schermate vere
  // dell'installatore (sito-plumecms/installatore.js), che scorre da solo e si ferma per chi chiede meno movimento.
  await v.goto(B + '/', { waitUntil: 'networkidle' });
  if (conHome) ok('la home porta ai cinque passi dell\'installatore', await v.locator('a[href$="/installazione/#passi"]').count() >= 1);
  await v.goto(B + '/installazione/', { waitUntil: 'networkidle' });
  const prima = await v.locator('#passi').innerText();
  ok('Installazione: prima di cominciare (hosting con PHP 8.2 e MySQL, i dati del database, lo zip)', /Prima di cominciare/.test(prima) && /PHP 8\.2/.test(prima) && /MySQL/.test(prima) && /dati del database/.test(prima) && /zip/i.test(prima));
  const car = v.locator('#passi [data-ew-car]');
  const passi = await car.locator('.ew-car-voce').evaluateAll(li => li.map(x => ({ src: x.querySelector('img').getAttribute('src'), did: (x.querySelector('figcaption') || {}).textContent || '' })));
  ok('Installazione: il carosello dei cinque passi, con la didascalia di ognuno', passi.length === 5 && passi.every((x, k) => x.src.includes(`installa-${k + 1}.webp`) && x.did.startsWith(`${k + 1}. `)), passi.map(x => x.did));
  const immInst = await v.evaluate(async () => { const im = [...document.querySelectorAll('#passi img')]; im.forEach(i => { i.loading = 'eager'; }); await Promise.all(im.map(i => i.decode().catch(() => null))); return im.map(i => i.naturalWidth); });
  ok('Installazione: le cinque schermate caricate', immInst.length === 5 && immInst.every(x => x >= 1000), immInst);
  const pesi = [];
  for (let k = 1; k <= 5; k++) pesi.push(Math.round((await (await v.request.get(B + `/uploads/plumecms/installa-${k}.webp`)).body()).length / 1024));
  ok('Installazione: ogni schermata sotto 150 KB', pesi.every(x => x < 150), pesi.map(x => x + ' KB'));
  ok('Installazione: il carosello scorre da solo e si ferma al passaggio del mouse', await car.getAttribute('data-auto') !== null && await car.getAttribute('data-pausa') === '1');
  const scorre = async (pg) => { await pg.mouse.move(0, 0); const t = pg.locator('#passi .ew-car-traccia'); const a = await t.evaluate(e => e.scrollLeft); await pg.waitForTimeout(6000); return [a, await t.evaluate(e => e.scrollLeft)]; };
  const sv = await scorre(v);
  ok('Installazione: dopo qualche secondo il carosello è andato avanti', sv[1] > sv[0], sv);
  const piano = await (await br.newContext({ viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce' })).newPage();
  await piano.goto(B + '/installazione/', { waitUntil: 'networkidle' });
  const sp = await scorre(piano);
  ok('Installazione: con meno movimento il carosello resta fermo (si sfoglia con frecce e pallini)', sp[0] === sp[1] && await piano.locator('#passi .ew-car-next').isVisible(), sp);
  await piano.close();
  const telI = await (await br.newContext({ viewport: { width: 390, height: 844 }, isMobile: true })).newPage();
  await telI.goto(B + '/installazione/', { waitUntil: 'networkidle' });
  ok('Installazione al telefono: niente scorrimento orizzontale, carosello largo come lo schermo', await telI.evaluate(() => document.documentElement.scrollWidth <= innerWidth && document.querySelector('#passi .ew-car-voce').getBoundingClientRect().width > 300));
  await telI.close();

  // I file da scaricare: i link delle pagine portano a file veri, con il tipo giusto.
  await v.goto(B + '/scarica/', { waitUntil: 'networkidle' });
  const link = await v.locator('a[href*="/scarica/"]').evaluateAll(as => [...new Set(as.map(a => a.getAttribute('href')).filter(h => /\/scarica\/[^/]+\/$/.test(h)))]);
  ok('la pagina Scarica, widget Download: cinque file (dalla 2.1.0 il manuale d\'uso è uno solo), tutti presenti', await v.locator('.ed-dl-voce').count() === 5 && await v.locator('.ed-dl-mancante').count() === 0, await v.locator('.ed-dl-titolo').allInnerTexts());
  // Dalla 1.2.5 i link sono quelli della serie, che porta sempre all'ultima versione caricata.
  ok('la pagina Scarica punta alle serie (sempre l\'ultima versione)', link.some(h => h.endsWith('/plumecms-ultima/')) && link.some(h => h.endsWith('/plumecms-ultima-aggiornamento/')), link);
  ok('nessun segnaposto {versione:...} rimasto nella pagina', !/\{versione:/.test(await v.content()));
  ok(`il pulsante dice "Scarica plumeCMS ${V}"`, await v.locator('a', { hasText: `Scarica plumeCMS ${V}` }).count() >= 1);
  const rSerie = await v.request.get(B + '/scarica/plumecms-ultima/');
  ok(`/scarica/plumecms-ultima/ manda plumecms-${V}.zip`, rSerie.status() === 200 && (rSerie.headers()['content-disposition'] || '').includes(`plumecms-${V}.zip`), rSerie.headers()['content-disposition']);
  for (const [u, tipo, min] of [[`/scarica/plumecms-${V}/`, 'application/zip', 500000], [`/scarica/plumecms-${V}-aggiornamento/`, 'application/zip', 500000],
    ['/scarica/manuale-uso/', 'application/pdf', 3000000], ['/scarica/manuale-temi/', 'application/pdf', 300000], ['/scarica/tema-lavagna/', 'application/zip', 1000]]) {
    const r = await v.request.get(B + u);
    const corpo = await r.body();
    const firma = tipo === 'application/pdf' ? corpo.slice(0, 4).toString() === '%PDF' : corpo[0] === 0x50 && corpo[1] === 0x4b;
    ok(`${u}: ${tipo}, ${Math.round(corpo.length / 1024)} KB, scaricabile`, r.status() === 200 && r.headers()['content-type'] === tipo && /attachment/.test(r.headers()['content-disposition'] || '') && corpo.length > min && firma,
      [r.status(), r.headers()['content-type'], r.headers()['content-disposition']]);
  }
  // 2.1.0: il manuale degli addon è dentro il manuale d'uso. Il file non c'è più (anche su un sito che lo aveva) e il suo
  // indirizzo porta al manuale intero.
  ok('2.1.0: uploads/scaricabili/manuale-uso-addon.pdf tolto', !fs.existsSync(path.join(DIR, 'uploads', 'scaricabili', 'manuale-uso-addon.pdf')));
  const rAdd = await v.request.get(B + '/scarica/manuale-uso-addon/', { maxRedirects: 0 });
  ok('2.1.0: /scarica/manuale-uso-addon/ porta a /scarica/manuale-uso/', [301, 302].includes(rAdd.status()) && /\/scarica\/manuale-uso\/$/.test(rAdd.headers()['location'] || ''), [rAdd.status(), rAdd.headers()['location']]);
  const pdfUso = await (await v.request.get(B + '/scarica/manuale-uso/')).body();
  ok('2.1.0: il manuale d\'uso scaricato ha anche la Parte seconda (gli addon)', /Parte seconda/.test(execFileSync('python3', ['-I', '-c', 'import sys,io,pypdf;r=pypdf.PdfReader(io.BytesIO(sys.stdin.buffer.read()));print(" ".join(p.extract_text() for p in r.pages[1:6]))'], { input: pdfUso, encoding: 'utf8', maxBuffer: 64 << 20 })));
  // 1.6.4: il manuale tecnico non c'è più, nemmeno su un sito che lo aveva (la parte 1 lo toglie).
  const rTec = await v.request.get(B + '/scarica/manuale-tecnico/');
  ok('/scarica/manuale-tecnico/: tolto (404)', rTec.status() === 404, rTec.status());
  const r404 = await v.request.get(B + '/scarica/non-esiste/');
  ok('/scarica/ di un file che non c\'è: 404', r404.status() === 404, r404.status());
  const rFuga = await v.request.get(B + '/scarica/..%2Fconfig/');
  ok('/scarica/ non esce dalla cartella', rFuga.status() === 404, rFuga.status());
  // 1.2.8: la licenza detta dove si scarica e nelle domande frequenti.
  await v.goto(B + '/scarica/', { waitUntil: 'networkidle' });
  ok('Scarica: la licenza GNU GPL, con il link', /GNU GPL versione 3/.test(await v.locator('body').innerText()) && await v.locator('a[href*="gnu.org/licenses/gpl-3.0"]').count() >= 1);
  await v.goto(B + '/domande-frequenti/', { waitUntil: 'networkidle' });
  ok('Domande frequenti: quanto costa e a quali condizioni', /A quali condizioni lo uso\?/.test(await v.locator('body').innerText()));
  ok('Domande frequenti (2.20.0): niente più import da un altro CMS', !/WordPress|Importa da/.test(await v.locator('body').innerText()));
  await v.goto(B + '/scarica/', { waitUntil: 'networkidle' });
  // Regola di Danilo (1 novembre 2026): nelle novità un elenco breve delle ultime versioni, senza nomi di aziende terze,
  // che non scende sotto il riquadro "Cosa serve" accanto (al computer).
  { const r = await v.evaluate(() => {
      const h = [...document.querySelectorAll('h2')].find(e => e.textContent.trim() === 'Novità');
      const g = [...document.querySelectorAll('a')].find(e => /Guida all.installazione/i.test(e.textContent));
      if (!h || !g) return null;
      let col = h; while (col.parentElement && !col.parentElement.contains(g)) col = col.parentElement;
      const voci = [...col.querySelectorAll('li')];
      return { voci: voci.map(e => e.textContent.trim()), fondo: Math.max(...voci.map(e => e.getBoundingClientRect().bottom)), sinistra: g.getBoundingClientRect().bottom };
    });
    ok('Scarica: novità, elenco breve con l\'ultima versione in cima', r && r.voci.length >= 3 && r.voci.length <= 8 && r.voci[0].startsWith(V + ': '), r && r.voci);
    const terzi = (r ? r.voci.join(' ') : '').match(/brevo|google|wordpress|linkedin|facebook|instagram|whatsapp|telegram|mailgun|amazon|dropbox|indexnow|search console|gnu/gi) || [];
    ok('Scarica: nelle novità nessun nome di aziende terze', r && terzi.length === 0, terzi);
    ok('Scarica: le novità finiscono non oltre il riquadro Cosa serve', r && r.fondo <= r.sinistra + 2, r && { fondo: r.fondo, sinistra: r.sinistra }); }
  // 2.6.0: il blocco del pannello più chiaro in Funzionalità, con la sua schermata caricata; il logo di header e footer dallo Stile.
  await v.goto(B + '/funzionalita/', { waitUntil: 'networkidle' });
  // Le immagini dei blocchi si caricano quando si arriva lì (loading=lazy): la pagina si scorre tutta prima di guardarle.
  await v.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 80)); } });
  await v.waitForLoadState('networkidle'); await v.waitForTimeout(500);
  const DIECI = [['editor', 'editor', 'L\'editor visuale'], ['partenza', 'installa-4', 'In cinque minuti hai un sito vero'], ['aspetto', 'stile', 'Temi e stile'],
    ['newsletter', 'campagne', 'Newsletter: contatti, campagne e report'], ['automazioni', 'automazioni', 'Automazioni: lavora anche quando dormi'], ['form', 'form-passi', 'Form e consenso'],
    ['seo', 'seo', 'SEO e AEO'], ['social', 'social', 'Social ogni mattina'], ['addon', 'addon', 'Gli addon'], ['sicurezza', 'sicurezza', 'Al sicuro']];
  for (const [id, img, t] of DIECI)
    ok(`Funzionalità: ${t}, con la schermata`, (await v.locator('#' + id).innerText()).includes(t)
      && await v.evaluate((img) => [...document.images].some(i => new RegExp(img + '\\.webp').test(i.currentSrc || i.src) && i.complete && i.naturalWidth > 0), img));
  ok('Funzionalità (2.20.0): nessun blocco e nessuna riga sull\'import da WordPress', await v.locator('#wordpress, #ponte').count() === 0 && !/WordPress|plugin Ponte/.test(await v.locator('main, body').first().innerText()));
  ok('Funzionalità (2.21.0): l\'allineamento delle parti sta nel blocco dell\'editor, non in un blocco nuovo', /Ogni parte si allinea per conto suo/.test(await v.locator('#editor').innerText()) && await v.locator('#allinea, #allineamento').count() === 0);
  ok('Funzionalità (2.23.0): i tag stanno nel blocco della newsletter, non in un blocco nuovo', /Ogni persona ha i suoi tag/.test(await v.locator('#newsletter').innerText()) && await v.locator('#tag').count() === 0);
  ok('Funzionalità (2.24.0): le pagine del test stanno nella riga del test, non in un blocco nuovo', /trascinandole, e un telefono accanto/.test(await v.locator('body').innerText()));
  ok('Funzionalità (2.25.0): Scorri di lato sta nella riga dei widget', /Scorri di lato, le pagine che scorrono/.test(await v.locator('body').innerText()));
  ok('Funzionalità (2.26.0): le barre a confronto stanno nella riga dei widget, 30 infografiche e 109 widget', /30 infografiche \(anche le barre a confronto/.test(await v.locator('#editor').innerText()) && /109 widget/.test(await v.locator('body').innerText()) && !/108 widget/.test(await v.locator('body').innerText()));
  ok('Funzionalità (2.25.1): il ripristino sicuro della copia sta nella riga della copia, nel blocco della sicurezza', /anche con un database diverso\. Se il ripristino non può riuscire, niente si cancella/.test(await v.locator('#sicurezza').innerText()));
  ok('Funzionalità: il blocco degli addon nomina i nove addon', /Prenotazioni e appuntamenti/.test(await v.locator('#addon').innerText()) && /Copia del sito altrove/.test(await v.locator('#addon').innerText()) && /Test e questionari/.test(await v.locator('#addon').innerText()) && /nove funzioni/.test(await v.locator('#addon').innerText()));
  ok('Header e footer (2.6.0): il logo è quello dello Stile del sito', await v.evaluate(() => [...document.querySelectorAll('.marchio img')].length >= 2 && [...document.querySelectorAll('.marchio img')].every(i => /marchio/.test(i.getAttribute('src')))));
  // 2.5.0: la pagina Temi con le stagioni e le tinte piene, ogni immagine caricata.
  await v.goto(B + '/temi/', { waitUntil: 'networkidle' });
  const tTemi = await v.locator('body').innerText();
  ok('Temi (2.5.0): le quattro stagioni e le tinte piene', /Le quattro stagioni/.test(tTemi) && /Le tinte piene/.test(tTemi) && ['Estate', 'Inverno', 'Autunno', 'Primavera', 'Giallo', 'Rosso', 'Verde', 'Blu'].every(n => tTemi.includes(n)));
  ok('Temi (2.5.0): le undici immagini dei temi si vedono', await v.evaluate(() => [...document.querySelectorAll('img[alt^="Il tema "]')].filter(i => i.complete && i.naturalWidth > 0).length) === 11);
  await v.goto(B + '/scarica/', { waitUntil: 'networkidle' });
  await v.goto(B + '/installazione/', { waitUntil: 'networkidle' });
  ok('Installazione (1.4.0): dopo l\'installazione, Primi passi e Controlla il server', /Primi passi/.test(await v.locator('body').innerText()) && /Controlla il server/.test(await v.locator('body').innerText()));
  // 1.4.2: il manuale d'uso nella Documentazione, nelle Domande frequenti e nel piede.
  await v.goto(B + '/documentazione/', { waitUntil: 'networkidle' });
  ok('Documentazione (1.6.4): due manuali, il primo è il manuale d\'uso', /Due manuali/.test(await v.locator('body').innerText()) && await v.locator('a[href$="/scarica/manuale-uso/"]').count() >= 1);
  ok('Documentazione (2.1.0): niente più il manuale degli addon a parte, gli addon sono nel manuale d\'uso', await v.locator('a[href*="manuale-uso-addon"]').count() === 0 && /nove addon/.test(await v.locator('body').innerText()));
  await v.goto(B + '/domande-frequenti/', { waitUntil: 'networkidle' });
  ok('Domande frequenti: lo stesso invito sotto tutti gli articoli; i quaranta capitoli e gli addon (2.0.0)', /stesso invito sotto tutti gli articoli\?/.test(await v.locator('body').innerText()) && /Quaranta capitoli/.test(await v.locator('body').textContent()) && /Cosa sono gli addon\?/.test(await v.locator('body').innerText()));
  ok('Domande frequenti (1.5.0): i social', /Mi aiuta anche con i social\?/.test(await v.locator('body').innerText()));
  ok('Domande frequenti (1.4.2): la guida per imparare a usarlo', /una guida per imparare a usarlo/.test(await v.locator('body').innerText()));
  ok('Domande frequenti (2.1.0): il manuale d\'uso è un file solo', /in un file solo/.test(await v.locator('body').textContent()) && !/in due file/.test(await v.locator('body').textContent()));
  ok('nessun errore JavaScript sul sito', errV.length === 0, errV);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
