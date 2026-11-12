// Fogli e script del pannello con la data nell'indirizzo (1.4.1). Il browser tiene CSS e JS un mese (.htaccess): dopo
// l'aggiornamento alla 1.4.0 il pannello restava con i colori della 1.3 e i quattro aspetti erano tutti uguali.
// La prova fa il browser con la memoria vecchia: ogni foglio o script del pannello chiesto SENZA "?v=" riceve il contenuto
// di prima (il marrone e il corallo della 1.3). Con la 1.4.1 nessuna richiesta deve arrivare senza "?v=" e gli aspetti cambiano.
// uso: node prova-file-pannello.js <base> <utente> <password>
const { chromium } = require('playwright');
const [B, UTENTE, PASSWORD] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const VECCHIO = ':root{--bg:#f4f1ec;--surface:#fff;--sidebar:#2d1b0e;--sidebar-hover:#4a2c18;--coral:#e8553e;--text:#2d1b0e;--text-muted:#9c7b65;--border:#e8ddd0}'
  + '.admin-sidebar{background:var(--sidebar)}';

(async () => {
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1360, height: 900 } });
  const senzaData = [];
  await ctx.route(/\/admin\/assets\/.*\.(css|js)(\?.*)?$/, (r) => {
    const u = r.request().url();
    if (u.includes('?v=')) return r.continue();
    senzaData.push(u.replace(B, ''));
    return r.fulfill({ status: 200, contentType: u.endsWith('.css') ? 'text/css' : 'application/javascript', body: u.endsWith('.css') ? VECCHIO : '' });
  });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  const lato = () => p.evaluate(() => getComputedStyle(document.querySelector('.admin-sidebar')).backgroundColor);
  const scegli = async (a) => {
    await p.goto(B + '/admin/account.php', { waitUntil: 'networkidle' });
    await p.check(`input[name=aspetto][value=${a}]`, { force: true });
    await Promise.all([p.waitForNavigation(), p.click('button:has-text("Usa questo aspetto")')]);
    await p.waitForLoadState('networkidle');
    return lato();
  };
  const colori = {};
  for (const a of ['cosmo', 'lavagna', 'bottega', 'aria']) colori[a] = await scegli(a);
  ok('i quattro aspetti danno quattro barre laterali diverse', new Set(Object.values(colori)).size === 4, colori);
  ok('Aria ha la barra chiara, non il marrone della 1.3', colori.aria !== 'rgb(45, 27, 14)', colori.aria);
  const mini = await p.evaluate(() => [...document.querySelectorAll('.asp-mini-lato')].map(e => getComputedStyle(e).backgroundColor));
  ok('le quattro miniature della scelta sono diverse', new Set(mini).size === 4, mini);
  for (const u of ['/admin/index.php', '/admin/design.php', '/admin/contenuti.php?tipo=articoli', '/admin/newsletter-flussi.php', '/admin/editor.php?tipo=pagine&id=1'])
    await p.goto(B + u, { waitUntil: 'networkidle' });
  await p.waitForTimeout(800);
  ok('nessun foglio o script del pannello chiesto senza la data', senzaData.length === 0, senzaData);
  const lg = await (await br.newContext()).newPage();
  await lg.goto(B + '/admin/login.php', { waitUntil: 'networkidle' });
  ok('la pagina di accesso chiede il foglio degli aspetti con la data', await lg.locator('link[href*="aspetti.css?v="]').count() === 1);
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
