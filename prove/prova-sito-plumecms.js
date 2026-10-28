// Il sito di plumeCMS (1.2.3, 27 ottobre 2026): il pacchetto di sito-plumecms/costruisci.py caricato e applicato dal
// pannello Aggiornamenti come un utente, poi il sito visitato pagina per pagina e i file scaricati davvero.
// uso: node prova-sito-plumecms.js <base> <cartella CMS installato> <utente> <password> <parte-1.zip,parte-2.zip,...> <versione>
// Le parti si caricano e si applicano una dopo l'altra, in ordine, come le carica chi gestisce il sito.
const { chromium } = require('playwright');
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
  p.on('dialog', d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok('login', !p.url().includes('login.php'), p.url());

  const zips = ZIP.split(',');
  let log = '';
  for (const [k, z] of zips.entries()) {
    const nome = `Sito di plumeCMS (${V}), parte ${k + 1} di ${zips.length}`;
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
  ok('resoconto: sette pagine', (log.match(/^Pagina \//gm) || []).length === 7);
  ok('nessun errore JavaScript nel pannello', err.length === 0, err);

  // Il sito, come un visitatore (senza login).
  const v = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const errV = []; v.on('pageerror', e => errV.push(e.message.slice(0, 200)));
  v.on('console', m => { if (m.type() === 'error' && !/favicon/.test(m.text())) errV.push(m.text().slice(0, 200)); });
  const pagine = [['/', /leggero come una piuma/i], ['/funzionalita/', /vuoto/], ['/temi/', /Cambia vestito/], ['/documentazione/', /da leggere anche offline/],
    ['/installazione/', /Installalo in 5 minuti/], ['/scarica/', new RegExp('Scarica plumeCMS ' + V.replace(/\./g, '\\.') + '$')], ['/domande-frequenti/', /Domande frequenti/]];
  for (const [u, re] of pagine) {
    const r = await v.goto(B + u, { waitUntil: 'networkidle' });
    const h1 = (await v.locator('h1').first().innerText()).replace(/\s+/g, ' ');
    ok(`${u} risponde 200 con il suo titolo`, r.status() === 200 && re.test(h1), [r.status(), h1]);
  }
  await v.goto(B + '/', { waitUntil: 'networkidle' });
  ok('titolo della scheda: plumeCMS', /plumeCMS/.test(await v.title()), await v.title());
  const voci = (await v.locator('header nav a').allInnerTexts()).map(s => s.trim()).filter(Boolean);
  ok('menu: Home, Funzionalità, Temi, Documentazione, Installazione, Scarica', ['Home', 'Funzionalità', 'Temi', 'Documentazione', 'Installazione'].every(x => voci.some(y => y.toLowerCase() === x.toLowerCase())) && voci.some(y => /scarica/i.test(y)), voci);
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
  await v.goto(B + '/funzionalita/', { waitUntil: 'networkidle' });
  const imgF = await v.evaluate(async () => { for (const i of document.images) { i.loading = 'eager'; } await new Promise(r => setTimeout(r, 1500)); return [...document.images].filter(i => i.naturalWidth === 0).map(i => i.src); });
  ok('nessuna immagine rotta in Funzionalità', imgF.length === 0, imgF);
  ok('Funzionalità: il blocco del download manager, con la schermata', await v.locator('#download img[src*="download.webp"]').count() === 1 && /Download manager/.test(await v.locator('#download').innerText()));
  ok('Funzionalità: il blocco delle undici aperture, con il foglio', await v.locator('#aperture img[src*="aperture.webp"]').count() === 1 && /Undici aperture/.test(await v.locator('#aperture').innerText()));
  for (const [id, img, re] of [['kit', 'installa-4.webp', /sito vero/], ['sezioni', 'sezioni.webp', /Sezioni pronte/], ['copia', 'copia.webp', /copia del tuo sito/]])
    ok(`Funzionalità (1.3.0): il blocco ${id}, con la schermata`, await v.locator(`#${id} img[src*="${img}"]`).count() === 1 && re.test(await v.locator(`#${id}`).innerText()));
  for (const [id, img, re] of [['wordpress', 'importa-wp.webp', /Vieni da WordPress/], ['tipo-sito', 'tipo-sito.webp', /Definitivo/], ['primi-passi', 'primi-passi.webp', /Primi passi/],
    ['aspetti', 'aspetti.webp', /Lavagna/], ['server', 'controlla-server.webp', /Controlla il server/]])
    ok(`Funzionalità (1.4.0): il blocco ${id}, con la schermata`, await v.locator(`#${id} img[src*="${img}"]`).count() === 1 && re.test(await v.locator(`#${id}`).innerText()));
  ok('Funzionalità (1.5.0): il blocco dei social, con l\'email del mattino', await v.locator('#social img[src*="social.webp"]').count() === 1 && /pronti ogni mattina/.test(await v.locator('#social').innerText()));
  ok('Funzionalità: 177 attacchi respinti, non più 163', /177 attacchi/.test(await v.locator('#sicurezza').innerText()));
  ok('favicon del sito', await v.locator('link[rel=icon][href*="favicon.png"]').count() === 1);

  // "Installalo in 5 minuti" (pagina Installazione): cosa serve prima e il carosello delle cinque schermate vere
  // dell'installatore (sito-plumecms/installatore.js), che scorre da solo e si ferma per chi chiede meno movimento.
  await v.goto(B + '/', { waitUntil: 'networkidle' });
  ok('la home porta ai cinque passi dell\'installatore', await v.locator('a[href$="/installazione/#passi"]').count() >= 1);
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
  ok('la pagina Scarica, widget Download: sei file (dalla 1.4.2 anche il manuale d\'uso), tutti presenti', await v.locator('.ed-dl-voce').count() === 6 && await v.locator('.ed-dl-mancante').count() === 0, await v.locator('.ed-dl-titolo').allInnerTexts());
  // Dalla 1.2.5 i link sono quelli della serie, che porta sempre all'ultima versione caricata.
  ok('la pagina Scarica punta alle serie (sempre l\'ultima versione)', link.some(h => h.endsWith('/plumecms-ultima/')) && link.some(h => h.endsWith('/plumecms-ultima-aggiornamento/')), link);
  ok('nessun segnaposto {versione:...} rimasto nella pagina', !/\{versione:/.test(await v.content()));
  ok(`il pulsante dice "Scarica plumeCMS ${V}"`, await v.locator('a', { hasText: `Scarica plumeCMS ${V}` }).count() >= 1);
  const rSerie = await v.request.get(B + '/scarica/plumecms-ultima/');
  ok(`/scarica/plumecms-ultima/ manda plumecms-${V}.zip`, rSerie.status() === 200 && (rSerie.headers()['content-disposition'] || '').includes(`plumecms-${V}.zip`), rSerie.headers()['content-disposition']);
  for (const [u, tipo, min] of [[`/scarica/plumecms-${V}/`, 'application/zip', 500000], [`/scarica/plumecms-${V}-aggiornamento/`, 'application/zip', 500000],
    ['/scarica/manuale-uso/', 'application/pdf', 1000000], ['/scarica/manuale-tecnico/', 'application/pdf', 300000], ['/scarica/manuale-temi/', 'application/pdf', 300000], ['/scarica/tema-lavagna/', 'application/zip', 1000]]) {
    const r = await v.request.get(B + u);
    const corpo = await r.body();
    const firma = tipo === 'application/pdf' ? corpo.slice(0, 4).toString() === '%PDF' : corpo[0] === 0x50 && corpo[1] === 0x4b;
    ok(`${u}: ${tipo}, ${Math.round(corpo.length / 1024)} KB, scaricabile`, r.status() === 200 && r.headers()['content-type'] === tipo && /attachment/.test(r.headers()['content-disposition'] || '') && corpo.length > min && firma,
      [r.status(), r.headers()['content-type'], r.headers()['content-disposition']]);
  }
  const r404 = await v.request.get(B + '/scarica/non-esiste/');
  ok('/scarica/ di un file che non c\'è: 404', r404.status() === 404, r404.status());
  const rFuga = await v.request.get(B + '/scarica/..%2Fconfig/');
  ok('/scarica/ non esce dalla cartella', rFuga.status() === 404, rFuga.status());
  // 1.2.8: la licenza detta dove si scarica e nelle domande frequenti.
  await v.goto(B + '/scarica/', { waitUntil: 'networkidle' });
  ok('Scarica: la licenza GNU GPL, con il link', /GNU GPL versione 3/.test(await v.locator('body').innerText()) && await v.locator('a[href*="gnu.org/licenses/gpl-3.0"]').count() >= 1);
  await v.goto(B + '/domande-frequenti/', { waitUntil: 'networkidle' });
  ok('Domande frequenti: quanto costa e a quali condizioni', /A quali condizioni lo uso\?/.test(await v.locator('body').innerText()));
  ok('Domande frequenti (1.4.0): chi viene da WordPress', /Vengo da WordPress\. Devo rifare tutto\?/.test(await v.locator('body').innerText()));
  await v.goto(B + '/scarica/', { waitUntil: 'networkidle' });
  ok('Scarica: le novità della 1.4.0', /1\.4\.0: Il sito si prova/.test(await v.locator('body').innerText()));
  await v.goto(B + '/installazione/', { waitUntil: 'networkidle' });
  ok('Installazione (1.4.0): dopo l\'installazione, Primi passi e Controlla il server', /Primi passi/.test(await v.locator('body').innerText()) && /Controlla il server/.test(await v.locator('body').innerText()));
  // 1.4.2: il manuale d'uso nella Documentazione, nelle Domande frequenti e nel piede.
  await v.goto(B + '/documentazione/', { waitUntil: 'networkidle' });
  ok('Documentazione (1.4.2): tre manuali, il primo è il manuale d\'uso', /Tre manuali/.test(await v.locator('body').innerText()) && await v.locator('a[href$="/scarica/manuale-uso/"]').count() >= 1);
  await v.goto(B + '/domande-frequenti/', { waitUntil: 'networkidle' });
  ok('Domande frequenti (1.5.0): i social', /Mi aiuta anche con i social\?/.test(await v.locator('body').innerText()));
  ok('Domande frequenti (1.4.2): la guida per imparare a usarlo', /una guida per imparare a usarlo/.test(await v.locator('body').innerText()));
  ok('nessun errore JavaScript sul sito', errV.length === 0, errV);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
