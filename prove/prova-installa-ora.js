// 2.3.0: la versione in alto a sinistra del pannello e, nel banner della dashboard, "Nuova versione disponibile v. x.x.x" con
// "Installa ora", che scarica, controlla e applica in un clic. Un sito che ha la versione di prima, un sito ufficiale finto
// (prove/finto-ufficiale.php, modo "buono") che annuncia il pacchetto passato.
// Prima: il sito installato (kit Attività) con la VERSIONE di prima scritta nella sua cartella, e il finto acceso:
//   FINTO_ZIP=<.../plumecms-X-aggiornamento.zip, col suo nome: il finto legge la versione dal nome> php -S 127.0.0.1:<porta> prove/finto-ufficiale.php
// uso: node prove/prova-installa-ora.js <base> <cartella CMS> <utente> <password> <base del finto> <versione nuova>
const { chromium } = require('playwright');
const fs = require('fs'); const path = require('path');
const { execFileSync } = require('child_process');
const [B, DIR, U, P, FINTO, NUOVA] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, x = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (x !== '' ? ' ' + JSON.stringify(x).slice(0, 300) : '')); };
const CFG = path.join(DIR, 'includes', 'config.local.php');
const base = fs.readFileSync(CFG, 'utf8').replace(/\n\/\/ prova-installa-ora[\s\S]*$/, '');
fs.writeFileSync(CFG, base + `\n// prova-installa-ora\ndefine('CMS_AGGIORNAMENTI_URL', '${FINTO}/buono');\ndefine('CMS_AGGIORNAMENTI_HTTP_PROVA', true);\n`);
const php = c => execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='127.0.0.1'; require '${DIR}/includes/config.php'; ${c}`], { encoding: 'utf8' });
(async () => {
  const prima = fs.readFileSync(path.join(DIR, 'VERSIONE'), 'utf8').trim();
  php("save_setting('versione_controllo_cache', '');");
  const br = await chromium.launch(); const err = [];
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await ctx.newPage();
  p.on('pageerror', e => err.push(e.message)); p.on('dialog', d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', U); await p.fill('#password', P);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/', { waitUntil: 'networkidle' });
  ok('la versione installata in alto a sinistra: "v. ' + prima + '"', (await p.locator('.admin-logo #admin-versione').innerText()).trim() === 'v. ' + prima && await p.locator('#admin-versione').isVisible());
  await p.goto(B + '/admin/seo.php', { waitUntil: 'networkidle' });
  ok('anche nelle altre pagine del pannello', await p.locator('#admin-versione').isVisible());
  // Il controllo del giorno parte dalla dashboard e scrive la versione nuova nel banner.
  await p.goto(B + '/admin/', { waitUntil: 'networkidle' }); await p.waitForTimeout(1500);
  await p.goto(B + '/admin/', { waitUntil: 'networkidle' });
  const banner = (await p.locator('#db-ufficiale').innerText()).replace(/\s+/g, ' ');
  ok('banner: "Visita il sito ufficiale plumeCMS" e subito dopo "Nuova versione disponibile v. ' + NUOVA + '"', /Visita il sito ufficiale plumeCMS/.test(banner) && banner.includes('Nuova versione disponibile v. ' + NUOVA) && await p.locator('#db-nuova').isVisible(), banner);
  ok('accanto, il pulsante "Installa ora"', await p.locator('#db-installa button').isVisible() && (await p.locator('#db-installa button').innerText()).trim() === 'Installa ora');
  // Al telefono la versione sta accanto al pulsante del menu.
  const t = await (await br.newContext({ viewport: { width: 390, height: 844 }, storageState: await ctx.storageState() })).newPage();
  await t.goto(B + '/admin/', { waitUntil: 'networkidle' });
  ok('al telefono la versione si vede in alto a sinistra, accanto al menu', await t.locator('.admin-versione-tel').isVisible() && (await t.locator('.admin-versione-tel').innerText()).trim() === 'v. ' + prima);
  ok('al telefono il banner va a capo senza far scorrere la pagina di lato', await t.evaluate(() => document.documentElement.scrollWidth <= 391));
  // Installa ora.
  await Promise.all([p.waitForNavigation({ timeout: 180000 }), p.click('#db-installa button')]);
  const testo = (await p.locator('main').innerText()).replace(/\s+/g, ' ');
  ok('Installa ora: l\'aggiornamento è applicato (Aggiornamenti dice "applicato")', /applicato/i.test(testo) && !/fallito/i.test(testo), testo.slice(0, 300));
  const dopo = fs.readFileSync(path.join(DIR, 'VERSIONE'), 'utf8').trim();
  ok('la VERSIONE del sito ora è ' + NUOVA, dopo === NUOVA, dopo);
  await p.goto(B + '/admin/', { waitUntil: 'networkidle' });
  ok('in alto a sinistra ora c\'è "v. ' + NUOVA + '" e il banner non dice più "Nuova versione"', (await p.locator('#admin-versione').innerText()).trim() === 'v. ' + NUOVA && !(await p.locator('#db-nuova').isVisible()) && !(await p.locator('#db-installa').isVisible()));
  ok('il sito pubblico risponde', (await p.request.get(B + '/')).status() === 200);
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  fs.writeFileSync(CFG, base);
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); try { fs.writeFileSync(CFG, base); } catch (x) {} process.exit(2); });
