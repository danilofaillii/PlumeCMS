// Import da WordPress (1.4.0) come un utente, contro un WordPress vero (prove/wp-installa.php e prove/wp-riempi.php):
// credenziali sbagliate con i messaggi giusti, analisi con il report e i numeri giusti, scelta parziale, import con la
// barra e la ripresa dopo un'interruzione, anteprima protetta (il visitatore non vede niente), Annulla che rimette tutto
// com'era, un secondo import completo, Conferma che pubblica, confronto contenuto per contenuto con WordPress (titoli, slug,
// date, categorie, immagini che si caricano, menu, redirect 301), e la password che non compare da nessuna parte.
// uso: node prova-importa-wp.js <base CMS> <cartella CMS> <utente> <password> <base WordPress> <cartella segreti> <cartella schermate> [base WordPress con la REST chiusa]
// Il config.local.php del CMS deve avere CMS_IMPORTA_WP_LOCALE con l'host:porta del WordPress (e di quello chiuso):
// la prova lo aggiunge da sola, con CMS_IMPORTA_WP_GIRO a 0.3 secondi (giri corti per provare la ripresa).
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs'), path = require('path');
const [B, CMS, U, P, WP, SEG, OUT, WPCHIUSO] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, 'importa-wp-cli.php'), CMS, ...a]).toString());
const atteso = JSON.parse(fs.readFileSync(path.join(SEG, 'atteso.json'), 'utf8'));
const app = fs.readFileSync(path.join(SEG, 'app.txt'), 'utf8').trim();
const login = fs.readFileSync(path.join(SEG, 'login.txt'), 'utf8').trim();
const dorme = ms => new Promise(r => setTimeout(r, ms));
const hostPorta = u => new URL(u).host;

(async () => {
  // Il config del CMS: eccezione per il WordPress locale e giri corti.
  const conf = path.join(CMS, 'includes/config.local.php');
  let c = fs.readFileSync(conf, 'utf8');
  const locali = [hostPorta(WP), WPCHIUSO ? hostPorta(WPCHIUSO) : '', '127.0.0.1:8073'].filter(Boolean).join(',');
  c = c.split('\n').filter(r => !/^define\('CMS_IMPORTA_WP_(LOCALE|GIRO)'/.test(r)).join('\n');
  fs.writeFileSync(conf, c.trimEnd() + `\ndefine('CMS_IMPORTA_WP_LOCALE', '${locali}'); // solo prove in locale\ndefine('CMS_IMPORTA_WP_GIRO', 0.3); // solo prove\n`);
  await dorme(2000);

  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('dialog', d => d.accept());
  const anon = await br.newContext();
  const vis = (u, o = {}) => anon.request.get(B + u, { maxRedirects: 0, ...o });

  // Senza login
  const r0 = await vis('/admin/importa-wordpress.php');
  ok('senza login: la pagina rimanda al login', [301, 302, 303].includes(r0.status()) && /login/.test(r0.headers()['location'] || ''), r0.status());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', U); await p.fill('#password', P);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/impostazioni.php');
  ok('voce "Importa da WordPress" nel menu Impostazioni', await p.locator('.admin-sidebar a[href="importa-wordpress.php"]').count() === 1);
  await p.click('.admin-sidebar a[href="importa-wordpress.php"]');
  await p.waitForLoadState('networkidle');
  // Pulizia: un import lasciato a meta da una prova di prima si annulla.
  if (await p.locator('button:has-text("Annulla l\'import")').count()) { await Promise.all([p.waitForNavigation(), p.locator('button:has-text("Annulla l\'import")').first().click()]); }
  if (await p.locator('button:has-text("Lascia perdere"), button:has-text("Ricomincia")').count()) { await Promise.all([p.waitForNavigation(), p.locator('button:has-text("Lascia perdere"), button:has-text("Ricomincia")').first().click()]); }
  const testoForm = await p.locator('.iw').innerText();
  ok('la pagina spiega la password per le applicazioni in tre passi', /Utenti > Profilo/.test(testoForm) && /Password per le applicazioni/.test(testoForm) && await p.locator('.iw-passi li').count() === 3);
  await p.screenshot({ path: `${OUT}/importa-collegamento.png`, fullPage: true });

  const collega = async (url, utente, pwd) => {
    await p.fill('#iw-indirizzo', url); await p.fill('#iw-utente', utente); await p.fill('#iw-password', pwd);
    await Promise.all([p.waitForNavigation({ timeout: 60000 }), p.click('button:has-text("Collegati e analizza il sito")')]);
    return (await p.locator('.flash-error').allInnerTexts()).join(' ');
  };
  // Credenziali sbagliate
  let m = await collega(WP, 'admin', login);
  ok('password normale al posto di quella per le applicazioni: lo dice', /password con cui entri in WordPress/.test(m) && /Password per le applicazioni/.test(m), m);
  ok('dopo l\'errore: indirizzo e utente restano, la password no', await p.inputValue('#iw-indirizzo') === WP && await p.inputValue('#iw-utente') === 'admin' && await p.inputValue('#iw-password') === '');
  ok('la password scritta non compare nella pagina', !(await p.content()).includes(login));
  await p.screenshot({ path: `${OUT}/importa-errore-password.png`, fullPage: false });
  m = await collega(WP, 'utente-che-non-ce', app);
  ok('utente sbagliato: lo dice', /non conosce l'utente|non ha accettato utente e password/.test(m), m);
  ok('la password per le applicazioni non compare nella pagina', !(await p.content()).includes(app) && !(await p.content()).includes(app.replace(/\s+/g, '')));
  m = await collega('https://sito-che-non-esiste-davvero.invalid', 'admin', app);
  ok('sito inesistente: lo dice', /non esiste o non si trova|non si trova/.test(m), m);
  if (WPCHIUSO) {
    m = await collega(WPCHIUSO, 'admin', app);
    ok('REST chiusa da un plugin: lo dice', /REST di WordPress è chiusa/.test(m) && /plugin/.test(m), m);
  }
  for (const interno of ['http://127.0.0.1/', 'http://169.254.169.254/', 'http://10.0.0.1/', 'http://localhost/']) {
    m = await collega(interno, 'admin', app);
    ok('un indirizzo della rete interna (' + interno + '): rifiutato', /rete interna/.test(m), m);
  }
  m = await collega(B, 'admin', app);
  ok('il CMS stesso su una porta non del web: rifiutato', /porte normali/.test(m), m);
  m = await collega('https://example.org:3306', 'admin', app);
  ok('una porta che non e del web: rifiutata', /porte normali del web/.test(m), m);

  const foto0 = cli('foto');
  // Collegamento giusto: analisi e report
  m = await collega(WP, 'admin', app);
  ok('collegamento riuscito: parte l\'analisi', m === '' && /Leggo il sito|Il report/.test(await p.locator('.iw').innerText()), m);
  await p.waitForSelector('h2:has-text("Il report di")', { timeout: 120000 });
  const voci = {};
  for (const el of await p.locator('.iw-voce').all()) { const t = await el.innerText(); const k = await el.locator('[data-iw-voce]').getAttribute('data-iw-voce'); voci[k] = t; }
  const num = k => +((voci[k] || '').match(/\n?\s*(\d+)\s*\n/) || [0, -1])[1];
  const pub = atteso.articoli.filter(a => a.stato === 'publish').length, fut = atteso.articoli.filter(a => a.stato === 'future').length, boz = atteso.articoli.length - pub - fut;
  ok('report: articoli con i numeri giusti (pubblicati, bozze, programmati)', new RegExp(`${pub} pubblicati, ${boz} bozze, ${fut} programmat[oi]`).test(voci.articoli) && voci.articoli.includes(String(atteso.articoli.length)), voci.articoli);
  ok('report: pagine, una dentro un\'altra', new RegExp(`${atteso.pagine.length}`).test(voci.pagine) && /1 dentro un'altra pagina/.test(voci.pagine), voci.pagine);
  ok('report: categorie annidate e tag', voci.categorie.includes(String(atteso.categorie.length)) && /3 dentro un'altra categoria/.test(voci.categorie) && voci.tag.includes(String(atteso.tag)), [voci.categorie, voci.tag]);
  ok('report: media con il peso totale', /15 immagini e 1 altro file, [\d.,]+ (KB|MB) in tutto/.test(voci.media), voci.media);
  ok('report: menu con le voci e la posizione', /8 voci in tutto/.test(voci.menu) && /posizione/.test(voci.menu), voci.menu);
  ok('report: SEO di Yoast', /Yoast/.test(voci.seo) && /5 contenuti con titolo SEO/.test(voci.seo), voci.seo);
  ok('report: redirect con la struttura dei permalink', /%year%\/%monthnum%\/%postname%/.test(voci.redirect), voci.redirect);
  const rep = await p.locator('#iw-scelta').innerText();
  ok('report: commenti non importabili, con il perché', /5 commenti: plumeCMS non ha i commenti/.test(rep));
  ok('report: Elementor e shortcode spiegati', /fatt[oi] con Elementor \(Promozioni\)/.test(rep) && /\[contact-form-7\]/.test(rep));
  ok('report: la pagina Contatti c\'e gia in plumeCMS e si salta', /Contatti/.test(await p.locator('.iw-tab').first().innerText()) && /già una pagina/.test(rep));
  ok('report: autori', /Marco Bianchi/.test(await p.locator('.iw-card').first().innerText()) && /Giulia Rossi/.test(await p.locator('.iw-card').first().innerText()));
  await p.screenshot({ path: `${OUT}/importa-report.png`, fullPage: true });
  ok('stato: la password e cifrata (non in chiaro) durante il lavoro', cli('stato').segreto === true && cli('cerca', path.join(SEG, 'app.txt')).trovati.length === 0, cli('cerca', path.join(SEG, 'app.txt')));

  // Scelta parziale: niente bozze, niente libreria intera dei media (solo le immagini usate).
  await p.click('[data-iw-solo="pagine,seo,redirect"]');
  ok('scorciatoia "Solo le pagine": restano spuntate pagine, SEO e redirect', await p.isChecked('[data-iw-voce=pagine]') && !(await p.isChecked('[data-iw-voce=articoli]')) && !(await p.isChecked('[data-iw-voce=media]')));
  await p.click('[data-iw-tutto="1"]');
  await p.uncheck('input[name="stati[]"][value=draft]'); await p.uncheck('[data-iw-voce=media]');
  // Import con la barra, interrotto dopo due giri (come un browser chiuso) e ripreso.
  let giri = 0, fermati = false, fotoBarra = false;
  await p.route('**/admin/importa-wordpress.php', async route => {
    if (route.request().method() !== 'POST' || !/azione=giro|name="azione"\r\n\r\ngiro/.test(route.request().postData() || '')) return route.continue();
    if (fermati) return route.abort();
    try { const res = await route.fetch(); await dorme(400); giri++; await route.fulfill({ response: res }); } catch (e) { /* pagina chiusa a meta: come un browser chiuso */ }
  });
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Importa le voci scelte")')]);
  while (giri < 2) await dorme(100);
  await dorme(250);
  const barra = await p.locator('#iw-barra').getAttribute('aria-valuenow');
  await p.screenshot({ path: `${OUT}/importa-avanzamento.png`, fullPage: false });
  ok('avanzamento visibile: barra e testo', +barra > 0 && +barra < 100 && (await p.locator('#iw-testo').innerText()).length > 3, { barra, testo: await p.locator('#iw-testo').innerText() });
  fermati = true;
  await p.goto(B + '/admin/index.php');
  await p.unroute('**/admin/importa-wordpress.php');
  await dorme(2500);
  await p.goto(B + '/admin/importa-wordpress.php', { waitUntil: 'networkidle' });
  const fermo = await p.locator('#iw-testo').innerText();
  ok('dopo la chiusura: la pagina dice dove si e fermato e offre Riprendi', /si è fermato/.test(fermo) && await p.locator('#iw-riprendi').isVisible(), fermo);
  await p.screenshot({ path: `${OUT}/importa-fermo.png`, fullPage: false });
  // Fermo da piu di un giorno: la password si e dimenticata, la pagina la chiede di nuovo e poi continua.
  cli('dimentica');
  await p.goto(B + '/admin/importa-wordpress.php', { waitUntil: 'networkidle' });
  ok('password dimenticata (lavoro fermo da un giorno): la pagina la chiede di nuovo', /riscrivi la password per le applicazioni/.test(await p.locator('#iw-lavoro').innerText()) && await p.locator('#iw-lavoro input[name=password]').count() === 1);
  await p.fill('#iw-lavoro input[name=password]', app);
  await Promise.all([p.waitForNavigation(), p.click('#iw-lavoro button:has-text("Continua")')]);
  await p.waitForSelector('h2:has-text("Riepilogo")', { timeout: 180000 });
  const riep = await p.locator('.iw').innerText();
  const stat = async etichetta => +(await p.locator(`.stat-card:has(.stat-card-label:text-is("${etichetta}")) .stat-card-value`).innerText());
  ok('riepilogo: articoli senza le bozze, pagine senza Contatti', await stat('Articoli') === pub + fut && await stat('Pagine') === atteso.pagine.length - 1, { articoli: await stat('Articoli'), pagine: await stat('Pagine') });
  ok('riepilogo: Contatti fra quello che non e entrato, con il perché', /Contatti\s+In plumeCMS c'è già una pagina/.test(riep));
  ok('riepilogo: file, menu e redirect', await stat('File nei media') > 0 && await stat('Menu') === 1 && await stat('Redirect') > 20, { media: await stat('File nei media'), redirect: await stat('Redirect') });
  ok('riepilogo: "Da guardare" dice dello shortcode tolto e di Elementor', /tolti gli shortcode \[contact-form-7\]/.test(riep) && /fatto con Elementor/.test(riep));
  const cont1 = cli('contenuti');
  const titoliImp = cont1.filter(x => x.genere === 'articolo').map(x => x.titolo);
  ok('ripresa senza doppioni: ogni articolo una volta sola', new Set(titoliImp).size === titoliImp.length && titoliImp.length === foto0.articles + pub + fut, { articoli: titoliImp.length, attesi: foto0.articles + pub + fut });
  ok('dopo l\'import la password non c\'e piu (nemmeno cifrata)', cli('stato').segreto === false);
  await p.screenshot({ path: `${OUT}/importa-riepilogo.png`, fullPage: true });

  // Ambiente protetto: il visitatore non vede niente.
  const primo = atteso.articoli[0];
  const linkVecchio = new URL(primo.link).pathname;
  let r = await vis(`/${primo.slug}/`);
  ok('in prova: il visitatore non vede l\'articolo (404)', r.status() === 404, r.status());
  r = await vis(`/${primo.slug}/?preview=1`);
  ok('in prova: ?preview=1 senza la sessione dell\'amministratore non mostra niente', r.status() === 404, r.status());
  r = await vis('/category/dolci/');
  ok('in prova: la categoria nuova non c\'e per il visitatore', r.status() === 404, r.status());
  r = await vis(linkVecchio);
  ok('in prova: il redirect dal vecchio indirizzo non e ancora attivo', r.status() !== 301, r.status());
  r = await vis('/sitemap.xml');
  ok('in prova: la sitemap non ha i contenuti importati', r.status() === 200 && !(await r.text()).includes(primo.slug));
  r = await vis('/blog/');
  ok('in prova: il blog non mostra gli articoli importati', !(await r.text()).includes(primo.titolo));
  // L'amministratore li vede nell'anteprima.
  const pv = await ctx.newPage();
  r = await pv.goto(`${B}/${primo.slug}/?preview=1`, { waitUntil: 'networkidle' });
  ok('anteprima dell\'amministratore: l\'articolo c\'e', r.status() === 200 && (await pv.locator('h1').first().innerText()).includes(primo.titolo), r.status());
  await pv.goto(`${B}/il-lievito-madre-spiegato-bene/?preview=1`, { waitUntil: 'networkidle' });
  const imm = await pv.$$eval('main img, article img', l => l.map(i => [i.currentSrc || i.src, i.complete && i.naturalWidth > 0]));
  ok('anteprima: galleria con le immagini scaricate che si caricano', imm.length >= 3 && imm.every(x => x[1]) && imm.every(x => !x[0].includes(hostPortaWP())), imm);
  ok('anteprima: il video di YouTube resta un video', await pv.locator('iframe[src^="https://www.youtube-nocookie.com/embed/"]').count() === 1);
  await pv.screenshot({ path: `${OUT}/importa-anteprima.png`, fullPage: true });
  r = await pv.goto(`${B}/category/dolci/?preview=1`, { waitUntil: 'networkidle' });
  const dolci = atteso.articoli.filter(a => a.categorie.includes('dolci') && a.stato === 'publish').map(a => a.titolo);
  const bodyCat = await pv.locator('body').innerText();
  ok('anteprima: la categoria con i suoi articoli', r.status() === 200 && dolci.some(t => bodyCat.includes(t)), r.status());
  await pv.close();

  // Annulla: tutto com'era.
  await p.bringToFront();
  await Promise.all([p.waitForNavigation(), p.locator('form:has(input[value=annulla]) button').click()]);
  ok('Annulla: il messaggio dice quante cose ha tolto', /Import annullato: tolte \d+ cose/.test(await p.locator('.flash-success').innerText()));
  const foto1 = cli('foto');
  const uguale = JSON.stringify({ ...foto0, nascosti: '' }) === JSON.stringify({ ...foto1, nascosti: '' }) && foto1.nascosti === '';
  ok('Annulla: contenuti, categorie, tag, menu, redirect, revisioni e file come prima', uguale, uguale ? '' : { prima: { ...foto0, file: foto0.file.length }, dopo: { ...foto1, file: foto1.file.length } });

  // Secondo import, completo, e Conferma.
  m = await collega(WP, 'admin', app);
  await p.waitForSelector('h2:has-text("Il report di")', { timeout: 120000 });
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Importa le voci scelte")')]);
  await p.waitForSelector('h2:has-text("Riepilogo")', { timeout: 180000 });
  ok('secondo import: tutti gli articoli, anche le bozze', await stat('Articoli') === atteso.articoli.length, await stat('Articoli'));
  ok('alla conferma: il menu di WordPress (posizione Primary) proposto come menu principale', await p.isChecked('input[name=menu_principale]'));
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Conferma l\'import")')]);
  ok('Conferma: il messaggio', /Import confermato/.test(await p.locator('.flash-success').innerText()));
  await p.screenshot({ path: `${OUT}/importa-confermato.png`, fullPage: false });

  // Dopo la conferma, da visitatore.
  r = await vis(`/${primo.slug}/`);
  ok('dopo la conferma: l\'articolo pubblicato si vede', r.status() === 200 && (await r.text()).includes(primo.titolo), r.status());
  const progr = atteso.articoli.find(a => a.stato === 'future'), bozza = atteso.articoli.find(a => a.stato === 'draft');
  ok('dopo la conferma: il programmato resta programmato (non si vede prima della data)', (await vis(`/${progr.slug}/`)).status() === 404);
  ok('dopo la conferma: la bozza resta bozza', (await vis(`/${bozza.slug}/`)).status() === 404);
  r = await vis(linkVecchio);
  ok('redirect 301 dal permalink con la data', r.status() === 301 && /\/il-pane-di-casa\/$/.test(r.headers()['location'] || ''), [linkVecchio, r.status(), r.headers()['location']]);
  r = await vis(`/?p=${primo.id}`);
  ok('redirect 301 da /?p=ID', r.status() === 301 && /\/il-pane-di-casa\/$/.test(r.headers()['location'] || ''), [r.status(), r.headers()['location']]);
  r = await vis('/chi-siamo/il-team/');
  ok('redirect 301 dalla pagina figlia', r.status() === 301 && /\/il-team\/$/.test(r.headers()['location'] || ''), [r.status(), r.headers()['location']]);
  r = await vis('/category/ricette/dolci/');
  ok('redirect 301 dalla categoria annidata', r.status() === 301 && /\/category\/dolci\/$/.test(r.headers()['location'] || ''), [r.status(), r.headers()['location']]);
  r = await vis('/category/dolci/');
  const htmlCat = await r.text();
  ok('la categoria si vede, con i suoi articoli', r.status() === 200 && dolci.filter(t => htmlCat.includes(t.replace(/'/g, '&#039;')) || htmlCat.includes(t)).length >= 5, r.status());
  const pg = await anon.newPage();
  await pg.goto(B + '/', { waitUntil: 'networkidle' });
  const testata = await pg.locator('header').first().innerText().catch(() => '');
  ok('le voci del menu importato sono nel menu principale dell\'header', /Chi siamo/i.test(testata) && /Ricette/i.test(testata) && !/Chi sono/i.test(testata), testata.slice(0, 200));
  // Confronto contenuto per contenuto.
  const cont = cli('contenuti');
  const perSlug = Object.fromEntries(cont.map(x => [x.genere + ':' + x.slug, x]));
  const diff = [];
  for (const a of atteso.articoli) {
    const x = perSlug['articolo:' + a.slug];
    if (!x) { diff.push(['manca', a.slug]); continue; }
    const st = ['publish', 'future'].includes(a.stato) ? 'published' : 'draft';
    if (x.titolo !== a.titolo) diff.push(['titolo', a.slug, x.titolo, a.titolo]);
    if (x.data !== a.data.slice(0, 16)) diff.push(['data', a.slug, x.data, a.data]);
    if (x.status !== st) diff.push(['stato', a.slug, x.status, a.stato]);
    if (x.categorie.split(',').filter(Boolean).sort().join() !== [...a.categorie].sort().join()) diff.push(['categorie', a.slug, x.categorie, a.categorie]);
    if (a.evidenza !== (x.immagine !== '')) diff.push(['evidenza', a.slug]);
    if (/127\.0\.0\.1:\d+|\[contact-form-7|<script/i.test(x.testo)) diff.push(['testo', a.slug]);
  }
  for (const pa of atteso.pagine) if (pa.slug !== 'contatti' && !perSlug['pagina:' + pa.slug]) diff.push(['pagina manca', pa.slug]);
  ok(`confronto con WordPress: ${atteso.articoli.length} articoli (titolo, slug, data e ora, stato, categorie, immagine in evidenza, testo pulito) e ${atteso.pagine.length - 1} pagine`, diff.length === 0, diff);
  const pane = perSlug['articolo:il-pane-di-casa'];
  ok('SEO: titolo e descrizione di Yoast nei campi del contenuto, senza il nome del sito', pane.seo_title === 'SEO: Il pane di casa' && /La descrizione SEO dell'articolo 1/.test(pane.seo_description), [pane.seo_title, pane.seo_description]);
  r = await vis('/il-pane-di-casa/');
  const html = await r.text();
  ok('SEO: sul sito il titolo della scheda e la meta descrizione', /<title>SEO: Il pane di casa/.test(html) && /<meta name="description" content="La descrizione SEO/.test(html));
  ok('link interni riscritti (/chi-siamo/), niente link al vecchio sito', /href="(\/test)?\/chi-siamo\/"/.test(html) && !html.includes(hostPortaWP()));
  const emoji = cont.find(x => x.slug === 'ricetta-col-sorriso');
  ok('emoji: titolo con l\'emoji, slug senza, testo con l\'entita', emoji && /\u{1F600}/u.test(emoji.titolo) && emoji.testo.includes('&#x1F600;'), emoji && [emoji.titolo, emoji.slug]);
  ok('pagina fatta con Elementor: il testo c\'e', /Promo di ottobre/.test((perSlug['pagina:promozioni'] || {}).testo || ''));
  const pdf = (perSlug['articolo:ciambellone-morbido'] || {}).testo || '';
  const pdfUrl = (pdf.match(/href="([^"]+\.pdf)"/) || [])[1];
  r = pdfUrl ? await anon.request.get(new URL(pdfUrl, B + '/').href) : null;
  ok('il PDF linkato nel testo e nella libreria e si scarica', !!r && r.status() === 200 && /pdf/.test(r.headers()['content-type'] || ''), pdfUrl);
  // Ogni articolo pubblicato: le immagini si caricano.
  const rotte = [];
  for (const a of atteso.articoli.filter(a => a.stato === 'publish')) {
    await pg.goto(`${B}/${a.slug}/`, { waitUntil: 'networkidle' });
    const im = await pg.$$eval('img', l => l.map(i => [i.currentSrc || i.src, i.complete && i.naturalWidth > 0]));
    for (const x of im) if (!x[1]) rotte.push([a.slug, x[0]]);
    if (!(await pg.locator('h1').first().innerText()).includes(a.titolo.replace(/\s*\u{1F600}/u, ''))) rotte.push([a.slug, 'h1']);
  }
  ok(`${pub} articoli pubblicati: titolo giusto e tutte le immagini caricate`, rotte.length === 0, rotte);
  const menu = cli('menu').find(x => x.posizione === 'principale');
  const conta = v => v.reduce((t, x) => t + 1 + conta(x.figli || []), 0);
  ok('menu principale: 8 voci, Chi siamo > Il team, Ricette > Dolci e Il nostro pane, link esterno in una scheda nuova', menu && conta(menu.voci) === 8
    && menu.voci.find(v => v.etichetta === 'Chi siamo').figli[0].rif === 'il-team' && menu.voci.find(v => v.etichetta === 'Ricette').figli.length === 2
    && menu.voci.find(v => v.etichetta === 'Il mulino amico').nuova === true, menu && menu.voci.map(v => v.etichetta));
  ok('la voce Contatti (pagina gia presente) punta alla pagina di plumeCMS', menu && menu.voci.find(v => v.etichetta === 'Contatti').url === '/contatti/');
  ok('la password per le applicazioni non e da nessuna parte (impostazioni, registro, log)', cli('cerca', path.join(SEG, 'app.txt')).trovati.length === 0 && cli('stato').segreto === false);
  ok('nessun file PHP in uploads', cli('foto').php_in_uploads === 0);
  ok('nessun errore JavaScript nel pannello', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });

function hostPortaWP() { return new URL(WP).host; }
