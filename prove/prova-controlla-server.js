// Impostazioni > Controlla il server (1.4.0), come un utente. Il server di prova deve servire piu richieste insieme
// (PHP_CLI_SERVER_WORKERS=4 per php -S): la pagina chiede al sito stesso i file vietati.
// 1. la voce di menu e la pagina; niente controllo finche non si preme il pulsante;
// 2. "Controlla adesso": i sette gruppi, ognuno con il suo esito; PHP verde; HTTPS rosso (il sito di prova e in http)
//    con cosa fare; cartelle verdi; protezioni del .htaccess verdi (403 vero dal server); aggiornamenti con un esito
//    leggibile; nessun avviso di PHP;
// 3. con un .htaccess che non protegge (il file VERSIONE servito, prova con CONTROLLO_APERTO) le protezioni diventano rosse;
// 4. "Manda un'email di prova" da qui: messaggio e ritorno alla pagina;
// 5. sicurezza: senza CSRF niente controllo; il redattore non vede la voce e non apre la pagina.
// uso: node prova-controlla-server.js <base> <cartella CMS> <utente> <password> <cartella schermate>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, OUT] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const esito = async (p, gruppo) => p.locator(`.cs-gruppo:has(h2:text-is("${gruppo}")) .cs-voce`).evaluateAll(v => v.map(x => [x.querySelector('b').textContent, x.dataset.esito, x.innerText.replace(/\s+/g, ' ')]));

(async () => {
  const br = await chromium.launch(); const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  // 1.
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('voce "Controlla il server" nel menu Impostazioni', await p.locator('nav a[href="controlla-server.php"]').count() === 1);
  await p.goto(B + '/admin/controlla-server.php', { waitUntil: 'networkidle' });
  ok('prima del clic: solo la spiegazione e il pulsante', await p.locator('#cs-controlla').count() === 1 && await p.locator('.cs-gruppo').count() === 0);

  // 2.
  const t0 = Date.now();
  await Promise.all([p.waitForNavigation({ timeout: 90000 }), p.click('#cs-controlla')]);
  await p.waitForLoadState('networkidle');
  ok('il controllo finisce in meno di 30 secondi', Date.now() - t0 < 30000, Date.now() - t0);
  const gruppi = await p.locator('.cs-gruppo h2').allInnerTexts();
  ok('sette gruppi', ['PHP e database', 'Indirizzo del sito', 'Cron', 'Email', 'Aggiornamenti', 'Cartelle', 'Protezioni (.htaccess)'].every(g => gruppi.map(x => x.toLowerCase()).includes(g.toLowerCase())), gruppi);
  const php = await esito(p, 'PHP e database');
  ok('PHP, database ed estensioni necessarie: verdi', php.filter(x => /^PHP|Database|pdo_mysql|mbstring|openssl|json/.test(x[0])).every(x => x[1] === 'ok'), php.filter(x => x[1] !== 'ok'));
  const https = await esito(p, 'Indirizzo del sito');
  ok('HTTPS rosso sul sito di prova in http, con cosa fare', https[0][1] === 'ko' && https[0][2].includes('Cosa fare') && https[0][2].includes('AutoSSL'), https);
  const cart = await esito(p, 'Cartelle');
  ok('quattro cartelle scrivibili, verdi', cart.length === 4 && cart.every(x => x[1] === 'ok'), cart);
  const prot = await esito(p, 'Protezioni (.htaccess)');
  ok('protezioni: config.local.php, registri e VERSIONE rispondono 403, verde', prot[0][1] === 'ok' && prot[0][2].includes('403'), prot);
  const agg = await esito(p, 'Aggiornamenti');
  ok('aggiornamenti: un esito leggibile (verde se risponde, giallo se no)', ['ok', 'avviso'].includes(agg[0][1]) && agg[0][2].length > 40, agg);
  const cron = await esito(p, 'Cron');
  ok('cron: due voci, con quando e il link alla pagina Log cron', cron.length === 2 && cron.every(x => /Ultimo giro/.test(x[2])), cron);
  ok('riassunto in alto con i numeri', await p.locator('.cs-somma span').count() >= 2);
  ok('nessun avviso di PHP nella pagina', !/Warning|Notice|Fatal error|Deprecated/.test(await p.locator('main').innerText()));
  await p.screenshot({ path: `${OUT}/controlla-server.png`, fullPage: true });

  // 3. Un server che non legge il .htaccess (il router di prova lo simula con il file controllo-aperto)
  execFileSync('php', ['-r', 'file_put_contents($argv[1], "1");', path.join(DIR, 'logs', 'controllo-aperto')]);
  await Promise.all([p.waitForNavigation({ timeout: 90000 }), p.click('#cs-controlla')]);
  const prot2 = await esito(p, 'Protezioni (.htaccess)');
  ok('con le protezioni spente: rosso, con i file che si aprono e cosa fare', prot2[0][1] === 'ko' && prot2[0][2].includes('/VERSIONE') && prot2[0][2].includes('.htaccess'), prot2);
  execFileSync('php', ['-r', 'unlink($argv[1]);', path.join(DIR, 'logs', 'controllo-aperto')]);

  // 4.
  await Promise.all([p.waitForNavigation(), p.click('#cs-email')]);
  await p.waitForLoadState('networkidle');
  const f = (await p.locator('.flash').allInnerTexts()).join(' ');
  ok('email di prova da qui: messaggio e ritorno alla pagina', p.url().includes('controlla-server.php') && /email di prova/i.test(f), f);

  // 5.
  const r = await p.request.post(B + '/admin/controlla-server.php', { form: {}, maxRedirects: 0 });
  ok('senza CSRF: nessun controllo', !(await r.text()).includes('cs-gruppo'), r.status());
  execFileSync('php', [path.join(__dirname, 'tema-cli.php'), DIR, 'redattore', 'Redattore-Prova-Server-2026']);
  const rctx = await br.newContext(); const rp = await rctx.newPage();
  await rp.goto(B + '/admin/login.php'); await rp.fill('#username', 'redattore-prova'); await rp.fill('#password', 'Redattore-Prova-Server-2026');
  await Promise.all([rp.waitForNavigation(), rp.click('button[type=submit]')]);
  ok('redattore: nessuna voce nel menu', await rp.locator('nav a[href="controlla-server.php"]').count() === 0);
  await rp.goto(B + '/admin/controlla-server.php', { waitUntil: 'networkidle' });
  ok('redattore: la pagina lo rimanda alla dashboard', !rp.url().includes('controlla-server.php'), rp.url());
  const rtok = await rp.locator('input[name=_csrf]').first().inputValue().catch(() => '');
  const rr = await rp.request.post(B + '/admin/controlla-server.php', { form: { _csrf: rtok }, maxRedirects: 0 });
  ok('redattore: POST 403', rr.status() === 403, rr.status());
  await rctx.close();

  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
