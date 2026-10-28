// L'icona di partenza del sito (1.4.0), radice o cartella.
// 1. senza icona nello Stile: <link rel="icon"> all'icona di partenza (ico e svg), /favicon.ico 200 con un ICO vero, svg 200;
// 2. con un'icona scelta nello Stile del sito (dal pannello): il link porta a quella e /favicon.ico rimanda li (302, no-store);
// 3. tolta l'icona dallo Stile: si torna a quella di partenza.
// uso: node prova-favicon.js <base> <utente> <password> <url di un'immagine del sito, dalla radice (es. /test/uploads/kit/disegno-1.svg)> <cartella schermate>
const { chromium } = require('playwright');
const [B, UTENTE, PASSWORD, IMG, OUT] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); };
const cartella = new URL(B).pathname.replace(/\/$/, '');

(async () => {
  const br = await chromium.launch(); const ctx = await br.newContext(); const p = await ctx.newPage();
  const v = await (await br.newContext()).newPage();
  const errV = []; v.on('console', m => { if (m.type() === 'error') errV.push(m.text().slice(0, 200)); });
  const icone = async () => { await v.goto(B + '/', { waitUntil: 'networkidle' }); return v.$$eval('link[rel=icon]', l => l.map(x => x.getAttribute('href'))); };
  const stile = async valore => {
    await p.goto(B + '/admin/design.php', { waitUntil: 'networkidle' });
    await p.evaluate(x => { document.querySelector('input[name="v[sito][favicon]"]').value = x; }, valore);
    await Promise.all([p.waitForNavigation(), p.click('#dg-salva')]);
  };
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await stile('');

  // 1.
  let l = await icone();
  ok('due link all\'icona di partenza, nella cartella del sito', l.length === 2 && l[0] === cartella + '/sito/assets/favicon.ico' && l[1] === cartella + '/sito/assets/favicon.svg', l);
  let r = await v.request.get(B + '/favicon.ico', { maxRedirects: 0 }); const b = await r.body();
  ok('/favicon.ico: 200, image/x-icon, un ICO con tre misure', r.status() === 200 && (r.headers()['content-type'] || '').startsWith('image/x-icon') && b.readUInt16LE(2) === 1 && b.readUInt16LE(4) === 3, { stato: r.status(), tipo: r.headers()['content-type'] });
  r = await v.request.get(new URL(l[1], B).href);
  ok('svg 200, leggero', r.status() === 200 && (await r.body()).length < 2048);
  ok('nessun errore in console (niente 404 dell\'icona)', errV.length === 0, errV);

  // 2.
  await stile(IMG);
  l = await icone();
  ok('con l\'icona dello Stile: il link porta a quella', l.length === 1 && l[0] === IMG, l);
  r = await v.request.get(B + '/favicon.ico', { maxRedirects: 0 });
  ok('/favicon.ico rimanda all\'icona dello Stile (302, no-store)', r.status() === 302 && (r.headers()['location'] || '').endsWith(IMG) && /no-store/.test(r.headers()['cache-control'] || ''), r.headers());

  // 3.
  await stile('');
  l = await icone();
  ok('tolta dallo Stile: di nuovo l\'icona di partenza', l.length === 2 && l[0].endsWith('/sito/assets/favicon.ico'), l);
  await v.screenshot({ path: `${OUT}/favicon-home.png` });
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
