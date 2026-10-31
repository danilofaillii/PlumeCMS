// 2.0.0: tutti gli otto addon accesi insieme, come li vede l'amministratore e chi visita. Su un CMS installato con il kit Attività.
// Accende ogni addon da Impostazioni > Addon, apre ogni pagina del pannello degli addon (dal menu) al computer e al telefono
// (nessun errore JavaScript o PHP, niente scorrimento di lato), la dashboard, la pagina Widget e l'editor; poi le pagine
// pubbliche degli addon; poi li spegne tutti e controlla che home, Servizi, Contatti e sitemap tornino uguali a prima.
// uso: node prove/prova-addon-tutti.js <base> <utente> <password> <cartella del CMS installato>
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const [B, UTENTE, PASSWORD, DIR] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); };
const ADDON = ['copia-altrove', 'corsi', 'eventi-iscrizione', 'membri', 'prenotazioni', 'recensioni', 'statistiche', 'vetrina'];
// Il nonce della CSP e i token cambiano a ogni richiesta: si tolgono prima di confrontare.
const pulisci = h => h.replace(/nonce="[^"]*"/g, '').replace(/name="_csrf" value="[^"]*"/g, '').replace(/data-ora="[^"]*"/g, '').replace(/\d+ (giorni|ore|minuti|secondi)/g, '');
const log = () => { const f = path.join(DIR, 'logs', 'php-errori.log'); return fs.existsSync(f) ? fs.readFileSync(f, 'utf8') : ''; };

(async () => {
  const br = await chromium.launch();
  const err = [];
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  p.on('pageerror', e => err.push(p.url() + ' ' + e.message.slice(0, 160)));
  p.on('console', m => { if (m.type() === 'error' && !/favicon|404/.test(m.text())) err.push(p.url() + ' ' + m.text().slice(0, 160)); });
  p.on('dialog', d => d.accept());
  const pubbliche = ['/', '/servizi/', '/contatti/', '/sitemap.xml'];
  const prima = {};
  for (const u of pubbliche) prima[u] = pulisci(await (await p.request.get(B + u)).text());
  const logPrima = log().length;

  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  // 1. Accesi tutti, uno alla volta, dalla pagina Addon.
  await p.goto(B + '/admin/addon.php', { waitUntil: 'networkidle' });
  ok('Impostazioni > Addon: otto carte', await p.locator('[data-addon]').count() === 8, await p.locator('[data-addon]').count());
  for (const a of ADDON) {
    await p.goto(B + '/admin/addon.php', { waitUntil: 'networkidle' });
    const b = p.locator(`[data-addon="${a}"] button[value=accendi]`);
    if (await b.count()) await Promise.all([p.waitForNavigation(), b.click()]);
  }
  await p.goto(B + '/admin/addon.php', { waitUntil: 'networkidle' });
  const accesi = await p.locator('.ad-card.acceso').count();
  ok('tutti e otto accesi', accesi === 8, accesi);
  const attivi = JSON.parse(fs.readFileSync(path.join(DIR, 'moduli', 'addon-attivi.json'), 'utf8'));
  ok('moduli/addon-attivi.json li elenca tutti', ADDON.every(a => attivi.includes(a)), attivi);

  // 1b. Dati veri negli addon, come li mette chi li usa: una persona nelle Prenotazioni. Con la 2.0.0 prima della correzione,
  // una persona salvata (il widget Prenota legge le persone quando si carica) lasciava vuote tutte le pagine del sito.
  const homePrima = (await (await p.request.get(B + '/')).text()).length;
  await p.goto(B + '/admin/addon-pagina.php?a=prenotazioni&p=persone&nuova=1', { waitUntil: 'networkidle' });
  await p.fill('#pa-persona input[name=nome]', 'Anna');
  await Promise.all([p.waitForNavigation(), p.click('#pa-persona button.btn-primary')]);
  ok('Prenotazioni: una persona salvata dal pannello', /Anna/.test(await (await p.request.get(B + '/admin/addon-pagina.php?a=prenotazioni&p=persone')).text()));
  const homeDopo = await (await p.request.get(B + '/')).text();
  ok('con una persona nelle Prenotazioni la home esce intera (non vuota)', homeDopo.length > homePrima * 0.8 && /<footer|class="piede|firma-sito/.test(homeDopo), { prima: homePrima, dopo: homeDopo.length });
  const edApi = await p.goto(B + '/admin/', { waitUntil: 'networkidle' });

  // 2. Le pagine del pannello degli addon, prese dal menu.
  await p.goto(B + '/admin/', { waitUntil: 'networkidle' });
  const link = [...new Set(await p.locator('a[href*="addon-pagina.php"]').evaluateAll(l => l.map(a => a.getAttribute('href'))))];
  ok('il menu ha le pagine degli addon (almeno una per addon con pannello)', ADDON.filter(a => a !== 'statistiche' || true).every(a => link.some(h => h.includes('a=' + a))), link);
  const tel = await (await br.newContext({ viewport: { width: 390, height: 844 }, storageState: await ctx.storageState() })).newPage();
  tel.on('pageerror', e => err.push('tel ' + tel.url() + ' ' + e.message.slice(0, 160)));
  for (const h of link) {
    const r = await p.goto(B + '/admin/' + h, { waitUntil: 'networkidle' });
    const t = await p.locator('body').innerText();
    ok('pannello ' + h + ': si apre, senza errori PHP', r.status() === 200 && !/Fatal error|Warning:|Notice:|Deprecated:|Uncaught/.test(t) && await p.locator('.main-content, main').count() > 0, r.status());
    await tel.goto(B + '/admin/' + h, { waitUntil: 'networkidle' });
    const larga = await tel.evaluate(() => document.documentElement.scrollWidth);
    ok('pannello ' + h + ' al telefono: niente scorrimento di lato', larga <= 392, larga);
  }
  // 3. Dashboard, Widget, editor con tutti i widget degli addon.
  const d = await p.goto(B + '/admin/', { waitUntil: 'networkidle' });
  ok('dashboard con gli addon accesi: si apre, con i riquadri degli addon', d.status() === 200 && /visite oggi|Membri attivi|ordini nuovi/i.test(await p.locator('body').innerText()));
  await p.goto(B + '/admin/editor-widget.php', { waitUntil: 'networkidle' });
  const w = await p.locator('body').innerText();
  ok('pagina Widget: i widget degli addon dicono "addon", non "nucleo"', /addon Prenotazioni|addon Vetrina|addon Area riservata/.test(w), (w.match(/addon [A-Z][a-z]+/g) || []).slice(0, 6));
  const casa = await p.goto(B + '/admin/', { waitUntil: 'networkidle' }).then(() => p.locator('.db-azioni a', { hasText: "Home nell'editor" }).getAttribute('href'));
  await p.goto(B + '/admin/' + casa, { waitUntil: 'networkidle' }); await p.waitForTimeout(1200);
  const ed = await p.evaluate(() => document.body.innerText);
  ok('editor: i widget degli addon sono nell\'elenco', ['Prenota', 'Prodotti', 'Carrello', 'Accesso membri', 'Elenco corsi', 'Recensioni dal sito'].every(x => ed.includes(x)), ['Prenota', 'Prodotti', 'Carrello', 'Accesso membri', 'Elenco corsi', 'Recensioni dal sito'].filter(x => !ed.includes(x)));

  // 4. Il sito con tutto acceso: le pagine di sempre e quelle degli addon.
  const v = await (await br.newContext({ viewport: { width: 390, height: 844 } })).newPage();
  v.on('pageerror', e => err.push('sito ' + v.url() + ' ' + e.message.slice(0, 160)));
  for (const u of ['/', '/servizi/', '/contatti/', '/negozio/', '/accedi/', '/iscriviti/']) {
    const r = await v.goto(B + u, { waitUntil: 'networkidle' });
    const larga = await v.evaluate(() => document.documentElement.scrollWidth);
    ok('sito ' + u + ' con tutti gli addon accesi: si apre, al telefono senza scorrimento di lato', r.status() === 200 && larga <= 392, { s: r.status(), larga });
  }
  const cookie = (await v.context().cookies()).map(c => c.name);
  ok('chi visita le pagine normali non riceve cookie (Statistiche senza cookie)', !cookie.some(c => !/^(carrello|membri)/i.test(c)) || cookie.length === 0, cookie);

  // 5. Tutti spenti: il sito torna com'era.
  // Due giri: un addon che un altro usa (l'Area riservata per i Corsi) si spegne al secondo.
  for (const a of [...ADDON, ...ADDON]) {
    await p.goto(B + '/admin/addon.php', { waitUntil: 'networkidle' });
    const b = p.locator(`[data-addon="${a}"] button[value=spegni]`);
    if (await b.count()) await Promise.all([p.waitForNavigation(), b.click()]);
  }
  await p.goto(B + '/admin/addon.php', { waitUntil: 'networkidle' });
  ok('tutti spenti (Corsi prima dell\'Area riservata, che gli serve)', await p.locator('.ad-card.acceso').count() === 0, await p.locator('.ad-card.acceso').count());
  for (const u of pubbliche) {
    const ora = pulisci(await (await p.request.get(B + u)).text());
    ok('spenti: ' + u + ' è uguale a prima di accenderli', ora === prima[u], ora.length + ' / ' + prima[u].length);
  }
  ok('spenti: /negozio/ dà 404', (await p.request.get(B + '/negozio/')).status() === 404);
  const nuovo = log().slice(logPrima);
  ok('nessun errore PHP nel log durante la prova', !/Fatal|Warning|Uncaught|Deprecated/.test(nuovo), nuovo.slice(0, 300));
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
