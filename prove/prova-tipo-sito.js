// Il tipo di sito in prova dalla dashboard (1.4.0), come un utente. Serve un'installazione nuova della 1.4.0 fatta con il
// kit Portfolio (prove/prova-kit.js ... portfolio ...), con il CMS in radice o in cartella.
// 1. la dashboard mostra "Stai provando il sito Portfolio", le sei carte (cinque fino alla 1.6.4), Portfolio segnato, i Primi passi;
// 2. la persona cambia la pagina Chi sono e scrive un articolo (dal pannello, scheda classica);
// 3. la conferma rifiutata non cambia niente; accettata, il sito diventa Blog: la copia "prima del cambio" c'e, la pagina
//    cambiata e l'articolo scritto restano (e il riquadro lo dice), Lavori sparisce, il menu e gli articoli sono del Blog;
// 4. di nuovo, il sito diventa Vuoto: niente doppioni, i disegni del kit tolti perche nessuno li usa piu;
// 5. sicurezza: senza CSRF, da redattore, con un kit inventato, e dopo il Definitivo il cambio e rifiutato;
// 6. Definitivo con la conferma: il riquadro sparisce, la voce dei Primi passi si spunta.
// uso: node prova-tipo-sito.js <base> <cartella CMS> <utente> <password> <cartella schermate>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, OUT] = process.argv.slice(2);
const cli = (...a) => execFileSync('php', [path.join(__dirname, 'tipo-sito-cli.php'), DIR, ...a]).toString().trim();
const tema = (...a) => execFileSync('php', [path.join(__dirname, 'tema-cli.php'), DIR, ...a]).toString().trim();
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); };
const sito = new URL(B).pathname.replace(/\/$/, '');

(async () => {
  const br = await chromium.launch(); const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok('login', !p.url().includes('login.php'), p.url());

  // 1. La dashboard
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('riquadro "Stai provando il sito Portfolio"', (await p.locator('#tipo-sito-titolo').innerText().catch(() => '')) === 'Stai provando il sito Portfolio');
  // Dalla 1.7.0 le carte sono sei: la sesta, Su misura, è un link alla pagina delle domande.
  ok('sei carte (con Su misura), Portfolio segnato e non cliccabile', await p.locator('.db-kit-carta').count() === 6 && await p.locator('.db-kit-carta a[data-kit=su-misura][href*="sito-su-misura.php"]').count() === 1 && await p.locator('.db-kit-carta.attuale button[data-kit=portfolio]').isDisabled());
  ok('Primi passi: sei voci, "definitivo" da fare', await p.locator('#primi-passi li').count() === 6 && !(await p.locator('#primi-passi li[data-passo=definitivo]').getAttribute('class')).includes('fatto'));
  ok('Primi passi: "prima pagina o articolo" da fare su un sito appena installato', !(await p.locator('#primi-passi li[data-passo=contenuto]').getAttribute('class')).includes('fatto'));
  await p.screenshot({ path: `${OUT}/tipo-sito-portfolio.png`, fullPage: true });
  await p.setViewportSize({ width: 390, height: 844 });
  ok('al telefono niente scorrimento orizzontale', await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth) <= 1);
  await p.screenshot({ path: `${OUT}/tipo-sito-tel.png`, fullPage: true });
  await p.setViewportSize({ width: 1280, height: 900 });

  // 2. La persona cambia Chi sono e scrive un articolo
  const chi = cli('pagina', 'chi-sono').split(' | ')[0];
  await p.goto(`${B}/admin/contenuto.php?tipo=pagine&id=${chi}`, { waitUntil: 'networkidle' });
  await p.fill('#sc-title', 'Chi sono davvero');
  await Promise.all([p.waitForNavigation(), p.click('button[data-valore=pubblica]')]);
  ok('pagina Chi sono cambiata dal pannello', cli('pagina', 'chi-sono').includes('Chi sono davvero'));
  await p.goto(`${B}/admin/contenuto.php?tipo=articoli&scheda=classica`, { waitUntil: 'networkidle' });
  await p.fill('#sc-title', 'Il mio primo articolo');
  await Promise.all([p.waitForNavigation(), p.click('button[data-valore=pubblica]')]);
  ok('articolo scritto dal pannello', cli('articolo', 'il-mio-primo-articolo') !== 'nessuna', cli('articolo', 'il-mio-primo-articolo'));
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('Primi passi: "prima pagina o articolo" spuntata da sola', (await p.locator('#primi-passi li[data-passo=contenuto]').getAttribute('class')).includes('fatto'));

  // 3. Il cambio: prima la conferma rifiutata, poi accettata
  let testo = '';
  p.once('dialog', d => { testo = d.message(); d.dismiss(); });
  await p.click('.db-kit-carta button[data-kit=blog]');
  await p.waitForTimeout(800);
  ok('la conferma dice cosa succede', testo.includes('Il sito diventa Blog. Le pagine che hai scritto o cambiato restano.'), testo);
  ok('conferma rifiutata: niente cambia', JSON.parse(cli('stato')).kit === 'portfolio');
  p.once('dialog', d => d.accept());
  await Promise.all([p.waitForNavigation({ timeout: 120000 }), p.click('.db-kit-carta button[data-kit=blog]')]);
  await p.waitForLoadState('networkidle');
  const flash = await p.locator('.flash').allInnerTexts();
  ok('messaggio: il sito ora e Blog, con la copia', flash.some(f => f.includes('Il sito ora è Blog') && f.includes('copia')), flash);
  ok('titolo del riquadro: Blog', (await p.locator('#tipo-sito-titolo').innerText()) === 'Stai provando il sito Blog');
  const tenuti = await p.locator('#tipo-sito-tenuti').innerText().catch(() => '');
  ok('il riquadro dice cosa e rimasto: Chi sono, una volta sola', tenuti.includes('Cosa è rimasto') && tenuti.includes('l\'hai cambiata') && tenuti.split('Chi sono').length === 2, tenuti);
  ok('una copia "prima del cambio" in Copia del sito', cli('copie') === '1', cli('copie'));
  ok('Chi sono cambiata e rimasta', cli('pagina', 'chi-sono').includes('Chi sono davvero'));
  ok('l\'articolo scritto e rimasto', cli('articolo', 'il-mio-primo-articolo') !== 'nessuna');
  ok('Lavori (del Portfolio, mai toccata) tolta', cli('pagina', 'lavori') === 'nessuna');
  await p.screenshot({ path: `${OUT}/tipo-sito-blog.png`, fullPage: true });
  // Il sito, da visitatore
  const v = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  await v.goto(B + '/', { waitUntil: 'networkidle' });
  const menu = (await v.locator('header nav a').allInnerTexts()).map(t => t.trim().toLowerCase());
  ok('sito: il menu e quello del Blog', ['home', 'blog', 'chi sono', 'contatti'].every(x => menu.includes(x)) && !menu.includes('lavori'), menu);
  let r = await v.goto(B + '/lavori/'); ok('sito: /lavori/ non c\'e piu (404)', r.status() === 404, r.status());
  r = await v.goto(B + '/chi-sono/', { waitUntil: 'networkidle' }); ok('sito: /chi-sono/ e quella della persona', r.status() === 200 && (await v.title()).includes('Chi sono davvero'), await v.title());
  r = await v.goto(B + '/blog/', { waitUntil: 'networkidle' });
  const art = await v.locator('main a, .ed-pagina a').allInnerTexts();
  ok('sito: nel blog l\'articolo della persona e quelli d\'esempio del Blog', art.some(t => t.includes('Il mio primo articolo')) && art.filter(t => /\S/.test(t)).length >= 4, art.slice(0, 8));
  r = await v.goto(B + '/contatti/', { waitUntil: 'networkidle' }); ok('sito: Contatti con il form', r.status() === 200 && await v.locator('form input[type=email]').count() >= 1);
  ok('sito: i link restano nella cartella del sito', (await v.$$eval('a[href^="/"]', as => as.map(a => a.getAttribute('href')))).every(h => sito === '' || h.startsWith(sito + '/')));

  // 4. Di nuovo: Vuoto
  p.once('dialog', d => d.accept());
  await Promise.all([p.waitForNavigation({ timeout: 120000 }), p.click('.db-kit-carta button[data-kit=vuoto]')]);
  await p.waitForLoadState('networkidle');
  ok('il sito ora e Vuoto', (await p.locator('#tipo-sito-titolo').innerText()) === 'Stai provando il sito Vuoto' && JSON.parse(cli('stato')).kit === 'vuoto');
  ok('due copie "prima del cambio"', cli('copie') === '2');
  ok('Chi sono resta quella della persona (nessun doppione)', cli('pagina', 'chi-sono').includes('Chi sono davvero'));
  ok('i disegni del kit che nessuna pagina usa piu sono tolti (resta solo quello della pagina tenuta)', cli('disegni').endsWith(' 0') && +cli('disegni').split(' ')[0] < 8, cli('disegni'));
  r = await v.goto(B + '/', { waitUntil: 'networkidle' });
  ok('sito: la home di Vuoto', r.status() === 200 && /benvenuto/i.test(await v.locator('h1').innerText()), await v.locator('h1').innerText().catch(() => ''));

  // 5. Sicurezza
  const token = await p.locator('input[name=_csrf]').first().inputValue();
  let rr = await p.request.post(B + '/admin/tipo-sito.php', { form: { azione: 'cambia', kit: 'attivita' }, maxRedirects: 0 });
  ok('senza CSRF: rifiutato', JSON.parse(cli('stato')).kit === 'vuoto', rr.status());
  rr = await p.request.post(B + '/admin/tipo-sito.php', { form: { _csrf: token, azione: 'cambia', kit: '../config' }, maxRedirects: 0 });
  ok('kit inventato: rifiutato', JSON.parse(cli('stato')).kit === 'vuoto');
  tema('redattore', 'Redattore-Prova-Tipo-2026');
  const rctx = await br.newContext(); const rp = await rctx.newPage();
  await rp.goto(B + '/admin/login.php'); await rp.fill('#username', 'redattore-prova'); await rp.fill('#password', 'Redattore-Prova-Tipo-2026');
  await Promise.all([rp.waitForNavigation(), rp.click('button[type=submit]')]);
  await rp.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('redattore: niente riquadro e niente primi passi', await rp.locator('#tipo-sito').count() === 0 && await rp.locator('#primi-passi').count() === 0);
  const rtoken = await rp.locator('input[name=_csrf]').first().inputValue().catch(() => '');
  rr = await rp.request.post(B + '/admin/tipo-sito.php', { form: { _csrf: rtoken, azione: 'cambia', kit: 'attivita' }, maxRedirects: 0 });
  ok('redattore: il cambio risponde 403', rr.status() === 403 && JSON.parse(cli('stato')).kit === 'vuoto', rr.status());
  rr = await rp.request.post(B + '/admin/tipo-sito.php', { form: { _csrf: rtoken, azione: 'definitivo' }, maxRedirects: 0 });
  ok('redattore: il Definitivo risponde 403', rr.status() === 403 && JSON.parse(cli('stato')).stato === 'prova', rr.status());
  await rctx.close();

  // 6. Definitivo
  testo = '';
  p.once('dialog', d => { testo = d.message(); d.accept(); });
  await Promise.all([p.waitForNavigation(), p.click('#kit-definitivo')]);
  await p.waitForLoadState('networkidle');
  ok('la conferma del Definitivo spiega che non si torna indietro', testo.includes('non si potrà più cambiare'), testo);
  ok('Definitivo: il riquadro sparisce', await p.locator('#tipo-sito').count() === 0 && JSON.parse(cli('stato')).stato === 'definitivo');
  ok('Primi passi: "definitivo" spuntata', (await p.locator('#primi-passi li[data-passo=definitivo]').getAttribute('class')).includes('fatto'));
  rr = await p.request.post(B + '/admin/tipo-sito.php', { form: { _csrf: token, azione: 'cambia', kit: 'attivita' } });
  ok('dopo il Definitivo il cambio e rifiutato dal server, anche con il token giusto', JSON.parse(cli('stato')).kit === 'vuoto' && cli('copie') === '2' && (await rr.text()).includes('già definitivo'));
  await p.screenshot({ path: `${OUT}/tipo-sito-definitivo.png`, fullPage: true });

  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
