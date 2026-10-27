// Il sito di plumeCMS (1.2.3, 27 ottobre 2026): il pacchetto di sito-plumecms/costruisci.py caricato e applicato dal
// pannello Aggiornamenti come un utente, poi il sito visitato pagina per pagina e i file scaricati davvero.
// uso: node prova-sito-plumecms.js <base> <cartella CMS installato> <utente> <password> <sito-plumecms-X.zip> <versione>
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, ZIP, V] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 220) : '')); };

// Lo stato del pacchetto si legge dal suo file in aggiornamenti/pacchetti (scritto dal motore alla fine).
const statoPacchetto = () => {
  const d = path.join(DIR, 'aggiornamenti', 'pacchetti');
  const s = fs.readdirSync(d).filter(f => f.endsWith('.json')).map(f => JSON.parse(fs.readFileSync(path.join(d, f), 'utf8')))
    .filter(j => /Sito di plumeCMS/.test(j.nome || (j.manifest && j.manifest.nome) || '')).sort((a, b) => String(b.id).localeCompare(String(a.id)));
  return s[0] || null;
};

(async () => {
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('dialog', d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok('login', !p.url().includes('login.php'), p.url());

  await p.goto(B + '/admin/aggiornamenti.php', { waitUntil: 'networkidle' });
  await p.setInputFiles('input[name=pacchetto]', ZIP);
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[name=pacchetto]) button[type=submit]')]);
  ok('pacchetto del sito caricato', /Sito di plumeCMS/.test(await p.locator('body').innerText()));
  await Promise.all([p.waitForNavigation({ timeout: 180000 }), p.locator('tr', { hasText: 'Sito di plumeCMS' }).locator('button:has-text("Applica ora")').first().click()]);
  let st = null;
  for (let i = 0; i < 120; i++) { st = statoPacchetto(); if (st && ['applicato', 'fallito'].includes(st.stato)) break; await p.waitForTimeout(1000); }
  ok('pacchetto applicato', st && st.stato === 'applicato', st && (st.stato + ' ' + (st.log || []).slice(-3).join(' | ')));
  const log = (st && st.log || []).join('\n');
  ok('resoconto: nome del sito, stile, header e footer, menu', /Impostazioni del sito/.test(log) && /Stile del sito aggiornato/.test(log) && /Header e footer: 2/.test(log) && /Menu di navigazione: 3/.test(log), log.slice(0, 300));
  ok('resoconto: sette pagine', (log.match(/^Pagina \//gm) || []).length === 7);
  ok('nessun errore JavaScript nel pannello', err.length === 0, err);

  // Il sito, come un visitatore (senza login).
  const v = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const errV = []; v.on('pageerror', e => errV.push(e.message.slice(0, 200)));
  v.on('console', m => { if (m.type() === 'error' && !/favicon/.test(m.text())) errV.push(m.text().slice(0, 200)); });
  const pagine = [['/', /leggero come una piuma/i], ['/funzionalita/', /vuoto/], ['/temi/', /Cambia vestito/], ['/documentazione/', /da leggere anche offline/],
    ['/installazione/', /Installare plumeCMS/], ['/scarica/', new RegExp('Scarica plumeCMS')], ['/domande-frequenti/', /Domande frequenti/]];
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
  ok('la foto della home è naturale, con la cornice', await v.locator('.finestra.ed-foto-naturale.ed-fx-cornice').count() === 1);
  const imgRotte = await v.evaluate(() => [...document.images].filter(i => i.complete && i.naturalWidth === 0).map(i => i.src));
  ok('nessuna immagine rotta nella home', imgRotte.length === 0, imgRotte);
  await v.goto(B + '/funzionalita/', { waitUntil: 'networkidle' });
  const imgF = await v.evaluate(async () => { for (const i of document.images) { i.loading = 'eager'; } await new Promise(r => setTimeout(r, 1500)); return [...document.images].filter(i => i.naturalWidth === 0).map(i => i.src); });
  ok('nessuna immagine rotta in Funzionalità', imgF.length === 0, imgF);
  ok('favicon del sito', await v.locator('link[rel=icon][href*="favicon.png"]').count() === 1);

  // I file da scaricare: i link delle pagine portano a file veri, con il tipo giusto.
  await v.goto(B + '/scarica/', { waitUntil: 'networkidle' });
  const link = await v.locator('a[href*="/scarica/"]').evaluateAll(as => [...new Set(as.map(a => a.getAttribute('href')).filter(h => /\/scarica\/[^/]+\/$/.test(h)))]);
  ok('la pagina Scarica ha i due zip', link.some(h => h.includes(`plumecms-${V}/`)) && link.some(h => h.includes(`plumecms-${V}-aggiornamento/`)), link);
  for (const [u, tipo, min] of [[`/scarica/plumecms-${V}/`, 'application/zip', 500000], [`/scarica/plumecms-${V}-aggiornamento/`, 'application/zip', 500000],
    ['/scarica/manuale-tecnico/', 'application/pdf', 300000], ['/scarica/manuale-temi/', 'application/pdf', 300000], ['/scarica/tema-lavagna/', 'application/zip', 1000]]) {
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
  ok('nessun errore JavaScript sul sito', errV.length === 0, errV);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
