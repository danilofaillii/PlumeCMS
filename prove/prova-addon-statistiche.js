// Addon Statistiche (2.0.0) su un sito nuovo (kit Attività): l'attività accende l'addon dal pannello, quattro
// "persone" con browser diversi (computer, telefono, tablet) visitano il sito da Google, Facebook, una newsletter con
// gli utm e direttamente; robot, pagine non trovate e chi è nel pannello non contano. Poi: nessun cookie dal sito,
// nessun indirizzo IP nel database, i numeri del pannello uguali a quelli attesi (anche nel CSV e nella dashboard),
// Do Not Track, il testo per la privacy, la pagina al telefono, il tempo di risposta prima e dopo, e con l'addon spento
// il sito uguale a prima.
// uso: node prove/prova-addon-statistiche.js <base> <utente> <password> <cartella del CMS installato>
const { chromium } = require('playwright');
const path = require('path');
const { execFileSync } = require('child_process');
const [B, UTENTE, PASSWORD, CMS] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const cli = (...a) => execFileSync('php', [path.join(__dirname, 'statistiche-cli.php'), CMS, ...a]).toString();
const dump = () => JSON.parse(cli('dump'));
const dorme = ms => new Promise(r => setTimeout(r, ms));
const senzaNonce = h => h.replace(/nonce-[A-Za-z0-9+/_=-]+/g, 'nonce-X').replace(/nonce="[^"]*"/g, 'nonce="X"').replace(/\?v=\d+/g, '');
const UA = {
  pc: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
  pc2: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
  iphone: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
  ipad: 'Mozilla/5.0 (iPad; CPU OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
  misura: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36 Misura',
};
const BROWSER = { 'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'Accept-Language': 'it-IT,it;q=0.9' };

// Il tempo medio di risposta di tre pagine, come le chiede un browser (40 giri dopo 3 di riscaldamento).
async function misura(ua) {
  const pagine = ['/', '/servizi/', '/contatti/'];
  for (let i = 0; i < 3; i++) await fetch(B + pagine[i % 3] , { headers: { ...BROWSER, 'User-Agent': ua } }).then(r => r.text());
  const t0 = performance.now(); let k = 0;
  for (let i = 0; i < 40; i++) for (const u of pagine) { await fetch(B + u, { headers: { ...BROWSER, 'User-Agent': ua } }).then(r => r.text()); k++; }
  return (performance.now() - t0) / k;
}

(async () => {
  const br = await chromium.launch();
  const err = [];
  const contesto = async (ua, extra = {}) => {
    const c = await br.newContext({ userAgent: ua, locale: 'it-IT', viewport: (ua || '').includes('iPhone') ? { width: 390, height: 844 } : { width: 1280, height: 900 }, ...extra });
    const pg = await c.newPage(); pg.on('pageerror', e => err.push(e.message.slice(0, 200)));
    return [c, pg];
  };
  const [ac, p] = await contesto(UA.pc);
  p.on('dialog', d => d.accept());
  const vai = u => p.goto(B + u, { waitUntil: 'networkidle' });
  const flash = async () => (await p.locator('.flash').first().innerText().catch(() => '')).trim();

  // Prima di accendere: il sito per il confronto finale, e il tempo di risposta.
  const pagineSito = ['/', '/servizi/', '/contatti/'];
  const prima = {};
  for (const u of pagineSito) prima[u] = senzaNonce(await (await fetch(B + u, { headers: { ...BROWSER, 'User-Agent': UA.pc } })).text());
  const tPrima = await misura(UA.misura);

  await vai('/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await vai('/admin/addon.php');
  ok('Impostazioni > Addon: la carta delle Statistiche', /Statistiche delle visite/.test(await p.locator('[data-addon=statistiche] h2').textContent()));
  // Un giro interrotto può averla lasciata accesa: si spegne prima, così si prova l'accensione vera.
  if (/Acceso/.test(await p.locator('[data-addon=statistiche] .badge').textContent())) await Promise.all([p.waitForNavigation(), p.click('[data-addon=statistiche] button')]);
  await Promise.all([p.waitForNavigation(), p.click('[data-addon=statistiche] button')]);
  ok('accese: messaggio e voce Statistiche nel menu', /acceso/.test(await flash()) && await p.locator('.admin-nav a', { hasText: 'Statistiche' }).count() >= 1, await flash());
  // Una pagina contata non manda cookie (questa visita si toglie subito dopo, con l'azzeramento).
  cli('azzera');
  const r = await fetch(B + '/', { headers: { ...BROWSER, 'User-Agent': UA.pc } });
  await r.text(); await dorme(200);
  ok('nessuna intestazione Set-Cookie nella risposta di una pagina contata', !r.headers.get('set-cookie') && JSON.parse(cli('dump')).stat_visite.length === 1);
  cli('azzera');

  // Le visite. A: computer, arriva da Google, poi Servizi, Contatti, e torna alla home.
  const [cA, pA] = await contesto(UA.pc2);
  await pA.goto(B + '/', { referer: 'https://www.google.com/search?q=studio+prova', waitUntil: 'networkidle' });
  await pA.goto(B + '/servizi/', { waitUntil: 'networkidle' });
  await pA.goto(B + '/contatti/', { waitUntil: 'networkidle' });
  await pA.goto(B + '/', { waitUntil: 'networkidle' });
  ok('una pagina che non esiste dà 404 (e non si conta)', (await pA.goto(B + '/non-esiste-davvero/')).status() === 404);
  // B: telefono, dalla newsletter con gli utm, poi Servizi.
  const [cB, pB] = await contesto(UA.iphone, { isMobile: true, hasTouch: true });
  await pB.goto(B + '/?utm_source=newsletter&utm_medium=email&utm_campaign=autunno', { waitUntil: 'networkidle' });
  await pB.goto(B + '/servizi/', { waitUntil: 'networkidle' });
  // C: tablet, da Facebook.
  const [cC, pC] = await contesto(UA.ipad);
  await pC.goto(B + '/servizi/', { referer: 'https://m.facebook.com/', waitUntil: 'networkidle' });
  // Chi non conta: robot noti, programmi senza i segni di un browser, il browser senza testa con il suo nome, l'amministratore.
  const nonContano = [
    ['Googlebot', { 'User-Agent': 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', ...BROWSER }],
    ['anteprima di WhatsApp', { 'User-Agent': 'WhatsApp/2.23.20.0 A', ...BROWSER }],
    ['curl', { 'User-Agent': 'curl/8.5.0', 'Accept': '*/*' }],
    ['user agent da browser ma senza Accept-Language (fetch di Node manda "*")', { 'User-Agent': UA.pc, 'Accept': 'text/html' }],
    ['senza user agent', { 'Accept': 'text/html', 'Accept-Language': 'it' }],
    ['pagina chiesta in anticipo (prefetch)', { 'User-Agent': UA.pc, ...BROWSER, 'Sec-Purpose': 'prefetch' }],
    ['risorsa non documento (Sec-Fetch-Dest: image)', { 'User-Agent': UA.pc, ...BROWSER, 'Sec-Fetch-Dest': 'image' }],
    ['richiesta HEAD', { 'User-Agent': UA.pc, ...BROWSER }, 'HEAD'],
  ];
  for (const [, h, metodo] of nonContano) await fetch(B + '/', { method: metodo || 'GET', headers: h }).then(r => r.text());
  const [cH, pH] = await contesto(undefined); // il nome predefinito di Playwright: HeadlessChrome
  await pH.goto(B + '/', { waitUntil: 'networkidle' });
  await vai('/'); await vai('/servizi/'); // l'amministratore, con il cookie del pannello
  await dorme(300);

  // Nessun cookie dal sito pubblico, a nessuno dei visitatori.
  const biscotti = [...await cA.cookies(), ...await cB.cookies(), ...await cC.cookies(), ...await cH.cookies()];
  ok('il sito pubblico non ha messo nessun cookie ai visitatori', biscotti.length === 0, biscotti);
  // La pagina dell'amministratore: niente cookie nuovi e niente Cache-Control della sessione.
  const ra = await p.request.get(B + '/servizi/', { headers: { ...BROWSER, 'User-Agent': UA.pc } });
  ok('pagina vista dall\'amministratore: nessun Set-Cookie, nessun Cache-Control aggiunto', !ra.headers()['set-cookie'] && !/no-store|no-cache/.test(ra.headers()['cache-control'] || ''), ra.headers()['cache-control'] || '');
  await dorme(300);

  // Il database: righe aggregate, nessun IP, nessuno user agent.
  const d = dump();
  const tutto = JSON.stringify(d);
  ok('nel database nessun indirizzo IP (127.0.0.1) e nessuno user agent', !tutto.includes('127.0.0.1') && !/Mozilla|iPhone|Googlebot/.test(tutto));
  ok('le colonne sono solo quelle aggregate (niente ip, niente user agent)', JSON.stringify(d.colonne.stat_visite) === JSON.stringify(['giorno', 'chiave', 'pagina', 'fonte', 'utm_source', 'utm_medium', 'utm_campaign', 'dispositivo', 'visite', 'visitatori']) && JSON.stringify(d.colonne.stat_impronte) === JSON.stringify(['giorno', 'impronta', 'viste']), d.colonne);
  ok('tre impronte anonime di oggi (A, B, C), 16 byte ciascuna', d.stat_impronte.length === 3 && d.stat_impronte.every(x => /^[0-9a-f]{32}$/.test(x.impronta)), d.stat_impronte);
  ok('il sale del giorno è di oggi', d.sale && d.sale.giorno === new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10) && /^[0-9a-f]{64}$/.test(d.sale.sale), d.sale && d.sale.giorno);
  const somma = (f) => d.stat_visite.filter(f).reduce((a, x) => [a[0] + +x.visite, a[1] + +x.visitatori], [0, 0]);
  ok('in tutto 7 visite e 3 visitatori (robot, 404 e amministratore esclusi)', JSON.stringify(somma(() => true)) === '[7,3]', somma(() => true));

  // Il pannello.
  await vai('/admin/addon-pagina.php?a=statistiche&p=visite&periodo=7');
  const num = async k => (await p.locator(`[data-st="${k}"]`).innerText()).trim();
  ok('pannello: 7 visite, 3 visitatori, 2,3 pagine per visitatore', await num('visite') === '7' && await num('visitatori') === '3' && await num('per-visitatore') === '2,3', [await num('visite'), await num('visitatori'), await num('per-visitatore')]);
  const tabella = async id => p.locator(`#${id} tbody tr`).evaluateAll(trs => trs.map(tr => [...tr.cells].map(c => c.innerText.trim())));
  ok('pagine più viste: / 3 visite (2 entrati da qui), /servizi/ 3 (1), /contatti/ 1 (0)', JSON.stringify(await tabella('st-pagine')) === JSON.stringify([['/', '3', '2'], ['/servizi/', '3', '1'], ['/contatti/', '1', '0']]), await tabella('st-pagine'));
  const fonti = Object.fromEntries((await tabella('st-fonti')).map(x => [x[0], x[1]]));
  ok('provenienze: google.com 1, m.facebook.com 1, diretto 1', fonti['google.com'] === '1' && fonti['m.facebook.com'] === '1' && fonti['Diretto o sconosciuto'] === '1', fonti);
  ok('campagne: newsletter / email / autunno, 1 visitatore e 1 visita', JSON.stringify(await tabella('st-campagne')) === JSON.stringify([['newsletter', 'email', 'autunno', '1', '1']]), await tabella('st-campagne'));
  const disp = Object.fromEntries((await tabella('st-dispositivi')).map(x => [x[0], x.slice(1).join('/')]));
  ok('dispositivi: computer 1/4, telefono 1/2, tablet 1/1 (visitatori/visite)', disp.Computer === '1/4' && disp.Telefono === '1/2' && disp.Tablet === '1/1', disp);
  ok('il grafico SVG con una barra per giorno e la linea dei visitatori', await p.locator('svg.st-grafico .st-giorno').count() === 7 && await p.locator('svg.st-grafico polyline.st-linea').count() === 1 && await p.locator('svg.st-grafico rect.st-barra').count() === 1);
  ok('confronto con il periodo prima (vuoto): «prima non c\'erano»', /prima non c.erano/.test(await p.locator('#st-numeri').innerText()));
  // Periodo personalizzato: solo ieri, niente visite.
  const ieri = new Date(Date.now() - 86400000 - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  await vai(`/admin/addon-pagina.php?a=statistiche&p=visite&periodo=personalizzato&dal=${ieri}&al=${ieri}`);
  ok('periodo personalizzato (solo ieri): 0 visite', await num('visite') === '0' && await p.locator('svg.st-grafico .st-giorno').count() === 1);
  await vai('/admin/addon-pagina.php?a=statistiche&p=visite&periodo=personalizzato&dal=2026-02-30&al=xx');
  ok('date sbagliate: torna ai 30 giorni senza errori', await p.locator('svg.st-grafico .st-giorno').count() === 30);
  // Il CSV.
  await vai('/admin/addon-pagina.php?a=statistiche&p=visite&periodo=7');
  const [dl] = await Promise.all([p.waitForEvent('download'), p.click('#st-csv')]);
  const csv = require('fs').readFileSync(await dl.path(), 'utf8').replace(/^﻿/, '').trim().split(/\r?\n/).map(l => l.split(';').map(c => c.replace(/^"|"$/g, '')));
  ok('CSV: intestazione e righe che sommano 7 visite e 3 visitatori', csv[0].join(',') === 'giorno,pagina,provenienza,utm_source,utm_medium,utm_campaign,dispositivo,visite,visitatori' && csv.slice(1).reduce((a, x) => a + +x[7], 0) === 7 && csv.slice(1).reduce((a, x) => a + +x[8], 0) === 3, csv.length);
  // La dashboard.
  await vai('/admin/');
  const db = await p.locator('.db-numeri', { hasText: 'visite oggi' }).innerText();
  ok('dashboard: «visite oggi» 7 e «visite, 7 giorni» 7', /7\s*visite oggi/i.test(db) && /7\s*visite, 7 giorni/i.test(db), db);
  // Al telefono la pagina delle statistiche non scorre di lato.
  await p.setViewportSize({ width: 390, height: 844 });
  await vai('/admin/addon-pagina.php?a=statistiche&p=visite&periodo=30');
  const [sw, iw] = await p.evaluate(() => [document.documentElement.scrollWidth, window.innerWidth]);
  ok('pannello delle statistiche al telefono (390x844): nessuno scorrimento orizzontale', sw <= iw, [sw, iw]);
  await p.setViewportSize({ width: 1280, height: 900 });

  // Do Not Track e Global Privacy Control, quando l'attività lo sceglie.
  await vai('/admin/addon-pagina.php?a=statistiche&p=impostazioni');
  ok('il testo per l\'informativa privacy è pronto', /Non usiamo cookie/.test(await p.inputValue('#st-privacy')) && /Non salviamo il tuo indirizzo IP/.test(await p.inputValue('#st-privacy')));
  // Un giro interrotto può averlo lasciato acceso: si parte da spento (il predefinito).
  if (await p.isChecked('#st-impostazioni input[name=dnt]')) { await p.uncheck('#st-impostazioni input[name=dnt]'); await Promise.all([p.waitForNavigation(), p.click('#st-impostazioni button[type=submit]')]); }
  const visiteOra = () => dump().stat_visite.reduce((a, x) => a + +x.visite, 0);
  const base = visiteOra();
  for (const [nome, h] of [['DNT: 1', { DNT: '1' }], ['Sec-GPC: 1', { 'Sec-GPC': '1' }]]) await fetch(B + '/contatti/', { headers: { ...BROWSER, 'User-Agent': UA.pc + ' ' + nome, ...h } }).then(r => r.text());
  await dorme(200);
  ok('Do Not Track spento (predefinito): chi lo manda si conta lo stesso', visiteOra() === base + 2);
  await p.check('#st-impostazioni input[name=dnt]');
  await Promise.all([p.waitForNavigation(), p.click('#st-impostazioni button[type=submit]')]);
  ok('impostazioni salvate e testo privacy aggiornato con Do Not Track', /salvate/.test(await flash()) && /Do Not Track/.test(await p.inputValue('#st-privacy')));
  for (const [nome, h] of [['DNT: 1', { DNT: '1' }], ['Sec-GPC: 1', { 'Sec-GPC': '1' }]]) await fetch(B + '/contatti/', { headers: { ...BROWSER, 'User-Agent': UA.pc + ' altro ' + nome, ...h } }).then(r => r.text());
  await dorme(200);
  ok('Do Not Track acceso: chi manda DNT o GPC non si conta più', visiteOra() === base + 2);
  await p.uncheck('#st-impostazioni input[name=dnt]'); await Promise.all([p.waitForNavigation(), p.click('#st-impostazioni button[type=submit]')]); // com'era

  // Il tempo di risposta con le statistiche accese.
  const tDopo = await misura(UA.misura + ' accese');
  // Spegnere: il sito torna uguale a prima.
  await vai('/admin/addon.php');
  await Promise.all([p.waitForNavigation(), p.click('[data-addon=statistiche] button')]);
  ok('spente: messaggio "i dati restano"', /restano/.test(await flash()));
  const diversi = [];
  for (const u of pagineSito) if (senzaNonce(await (await fetch(B + u, { headers: { ...BROWSER, 'User-Agent': UA.pc } })).text()) !== prima[u]) diversi.push(u);
  ok('addon spento: home, Servizi e Contatti identiche a prima', diversi.length === 0, diversi);
  const quante = dump().stat_visite.reduce((a, x) => a + +x.visite, 0);
  await fetch(B + '/', { headers: { ...BROWSER, 'User-Agent': UA.pc } }).then(r => r.text());
  ok('addon spento: le visite non si contano più, i dati restano', dump().stat_visite.reduce((a, x) => a + +x.visite, 0) === quante && quante > 0);
  const tSpento = await misura(UA.misura + ' spente');
  console.log(`TEMPI medi di risposta (3 pagine x 40, ms): addon mai acceso ${tPrima.toFixed(2)}, acceso ${tDopo.toFixed(2)}, spento di nuovo ${tSpento.toFixed(2)}; differenza ${(tDopo - (tPrima + tSpento) / 2).toFixed(2)} ms`);
  ok('il sito non rallenta in modo visibile (meno di 15 ms in più a pagina)', tDopo - (tPrima + tSpento) / 2 < 15);
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
