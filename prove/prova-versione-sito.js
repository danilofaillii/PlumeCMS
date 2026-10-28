// L'indirizzo del sito ufficiale come parametro (1.3.1): in Aggiornamenti si scrive, si controlla, si torna al predefinito.
// Serve un sito senza CMS_AGGIORNAMENTI_URL nel config.local.php (con la costante il campo non c'è: lo dice la pagina).
// uso: node prova-versione-sito.js <base> <utente> <password>
const { chromium } = require('playwright');
const [B, UTENTE, PASSWORD] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 200) : '')); };

(async () => {
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  const pagina = () => p.goto(B + '/admin/aggiornamenti.php', { waitUntil: 'networkidle' });
  const nota = async () => (await p.locator('.agg-note', { hasText: 'qual è l\'ultima versione' }).first().innerText());
  const salva = async (u) => { await p.fill('input[name=sito]', u); await Promise.all([p.waitForNavigation(), p.click('button:has-text("Salva l\'indirizzo")')]); return p.locator('body').innerText(); };

  await pagina();
  const predefinito = await p.locator('input[name=sito]').getAttribute('placeholder');
  ok('il campo dell\'indirizzo c\'è, con il predefinito come esempio', /^https:\/\//.test(predefinito || ''), predefinito);
  let t = await salva('http://esempio.it');
  ok('un indirizzo senza https viene rifiutato', /comincia con https:\/\//.test(t));
  t = await salva('https://esempio.it/x?a=1');
  ok('un indirizzo con ? viene rifiutato', /senza \? né #/.test(t));
  t = await salva('https://www.esempio.it/plume/');
  ok('un indirizzo giusto si salva, senza la barra finale', /Da adesso il pannello chiede le versioni nuove a https:\/\/www\.esempio\.it\/plume\./.test(t));
  ok('la pagina dice a chi chiede', (await nota()).includes('https://www.esempio.it/plume'), await nota());
  t = await salva('');
  ok('vuoto torna al predefinito', (await nota()).includes(predefinito), await nota());
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
