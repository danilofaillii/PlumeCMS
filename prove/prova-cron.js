// La pagina Log cron (1.4.0) come un utente, e il pannello senza i resti di WordPress.
// 1. la pagina spiega il cron di plumeCMS, niente "Test connessione WP", "Dry run", "Pubblica ora";
// 2. le due righe del cron portano il percorso vero dell'installazione;
// 3. "Esegui adesso": messaggio, giro nel registro "dal pannello", stato aggiornato, e non conta come cron vero;
// 4. lanciati da riga di comando come fa il cron dell'hosting, i due giri dicono "il cron gira";
// 5. sicurezza: senza CSRF e da redattore "Esegui adesso" non parte;
// 6. nessuna pagina del pannello parla di WordPress come se il sito lo fosse ancora.
// uso: node prova-cron.js <base> <cartella CMS> <utente> <password> <cartella schermate>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, OUT] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); };
const giri = () => { try { return (fs.readFileSync(path.join(DIR, 'logs', 'cron-daily.log'), 'utf8').match(/\| Cron avviato/g) || []).length; } catch (e) { return 0; } };

(async () => {
  const br = await chromium.launch(); const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  // 1-2.
  await p.goto(B + '/admin/cron-log.php', { waitUntil: 'networkidle' });
  const testo = await p.locator('main').innerText();
  ok('la pagina spiega il cron di plumeCMS', testo.includes('Cosa fa il cron') && testo.includes('Il giro del mattino') && testo.includes('Il giro della newsletter'));
  ok('niente istruzioni di WordPress', !/WordPress|\bWP\b|Dry run|Pubblica ora|Test connessione/i.test(testo));
  ok('nessun avviso di PHP nella pagina', !/Warning|Notice|Fatal error|Deprecated/.test(testo));
  const comandi = await p.locator('textarea.cr-riga').evaluateAll(t => t.map(x => x.value));
  const vero = fs.realpathSync(DIR);
  ok('due righe con il percorso vero', comandi.length === 2 && comandi[0].includes(vero + '/cron-daily.php') && comandi[1].includes(vero + '/cron-newsletter.php'), comandi);

  // 3.
  const prima = giri();
  await Promise.all([p.waitForNavigation(), p.click('#cron-esegui')]);
  await p.waitForLoadState('networkidle');
  const f = (await p.locator('.flash').allInnerTexts()).join(' ');
  ok('"Esegui adesso": messaggio con i due giri', f.includes('giro del mattino fatto') && f.includes('giro della newsletter fatto'), f);
  ok('nel registro un giro "dal pannello" in piu', giri() === prima + 1 && (await p.locator('.cr-registro').innerText()).split('\n')[0].includes('dal pannello'));
  const statoDopo = JSON.parse(fs.readFileSync(path.join(DIR, 'logs', 'cron-stato.json'), 'utf8'));
  ok('stato: ultimo giro dal pannello per tutti e due', statoDopo.giornaliero.da === 'pannello' && statoDopo.newsletter.da === 'pannello');
  await p.screenshot({ path: `${OUT}/cron-log.png`, fullPage: true });

  // 4.
  execFileSync('php', [path.join(DIR, 'cron-daily.php')]);
  execFileSync('php', [path.join(DIR, 'cron-newsletter.php')]);
  await p.reload({ waitUntil: 'networkidle' });
  ok('dopo il cron vero: "il cron gira" per tutti e due', (await p.locator('[data-cron=giornaliero]').innerText()).toLowerCase().includes('il cron gira') && (await p.locator('[data-cron=newsletter]').innerText()).toLowerCase().includes('il cron gira'));

  // 5.
  let n0 = giri();
  let r = await p.request.post(B + '/admin/cron-log.php', { form: { azione: 'esegui' }, maxRedirects: 0 });
  ok('senza CSRF: rifiutato, nessun giro', giri() === n0, r.status());
  execFileSync('php', [path.join(__dirname, 'tema-cli.php'), DIR, 'redattore', 'Redattore-Prova-Cron-2026']);
  const rctx = await br.newContext(); const rp = await rctx.newPage();
  await rp.goto(B + '/admin/login.php'); await rp.fill('#username', 'redattore-prova'); await rp.fill('#password', 'Redattore-Prova-Cron-2026');
  await Promise.all([rp.waitForNavigation(), rp.click('button[type=submit]')]);
  const rtok = await rp.locator('input[name=_csrf]').first().inputValue().catch(() => '');
  r = await rp.request.post(B + '/admin/cron-log.php', { form: { _csrf: rtok, azione: 'esegui' }, maxRedirects: 0 });
  ok('redattore: 403, nessun giro', r.status() === 403 && giri() === n0, r.status());
  await rp.goto(B + '/admin/cron-log.php', { waitUntil: 'networkidle' });
  ok('redattore: la pagina lo rimanda alla dashboard', !rp.url().includes('cron-log.php'), rp.url());
  await rctx.close();

  // 6. Tutte le pagine del pannello, senza WordPress
  const pagine = ['index.php', 'contenuti.php?tipo=articoli', 'contenuti.php?tipo=pagine', 'form.php', 'popup.php', 'categories.php', 'media.php', 'scaricabili.php',
    'utenti.php', 'newsletter.php', 'newsletter-campagna.php', 'newsletter-flussi.php', 'newsletter-statistiche.php', 'form-submissions.php', 'temi.php', 'design.php',
    'modelli.php', 'menu.php', 'editor-temi.php', 'editor-widget.php', 'versioni.php', 'editor-impostazioni.php', 'informazioni.php', 'seo.php', 'impostazioni.php',
    'sicurezza.php', 'aggiornamenti.php', 'copia-sito.php', 'cron-log.php', 'controlla-server.php', 'account.php'];
  const trovati = [];
  for (const pg of pagine) {
    await p.goto(B + '/admin/' + pg, { waitUntil: 'domcontentloaded' });
    const t = await p.evaluate(() => document.body.innerText + ' ' + Array.from(document.querySelectorAll('input[placeholder],textarea[placeholder]')).map(x => x.placeholder).join(' '));
    // Restano le cose che di WordPress parlano davvero: importare da WordPress, gli indirizzi /?p= e wp-sitemap.xml di un sito
    // di prima, i paragoni ("come in WordPress").
    const righe = t.split('\n').filter(x => /WordPress|\bWP\b|wp-content|wp-config/i.test(x) && !/import|\?p=|wp-sitemap|sito di prima|da WordPress|come (in|i temi di|quell[io] di) WordPress|Menu di WordPress|su WordPress|WordPress si porta qui/i.test(x));
    if (righe.length) trovati.push(pg + ': ' + righe.slice(0, 2).join(' / '));
  }
  ok('nessuna pagina del pannello parla di WordPress come se il sito lo fosse', trovati.length === 0, trovati);

  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
