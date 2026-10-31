// Installazione vera del CMS da browser, poi un giro da utente: login, Stile, pagina nuova, articolo, sitemap, SEO.
// uso: node prova-install.js <base, es. http://127.0.0.1:8099> <cartella screenshot> <prefisso shot> <db> [<base CMS, es. /sub>]
const { chromium } = require('playwright');
const [B, OUT, PRE, DB, CMSB = ''] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 200) : '')); };
(async () => {
  const br = await chromium.launch(); const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200))); p.on('console', m => { if (m.type() === 'error' && !(m.location().url || '').includes('/pagina-che-non-esiste/')) err.push(m.text().slice(0, 200) + ' ' + (m.location().url || '')); }); // il 404 cercato apposta non e un errore
  const shot = (nome) => p.screenshot({ path: `${OUT}/${PRE}-${nome}.png`, fullPage: false });
  // 1. la home rimanda all'installatore
  const r = await p.goto(B + '/', { waitUntil: 'networkidle' });
  ok('senza config.local.php la home apre l\'installatore', p.url().includes('/install/'), p.url());
  await shot('install-1-requisiti');
  ok('requisiti: nessuna voce "Manca"', await p.locator('.requisiti li.no').count() === 0);
  await p.click('a.btn:has-text("Avanti")'); await p.waitForLoadState('networkidle');
  // 2. database
  await p.fill('#host', '127.0.0.1:3308'); await p.fill('#nome', DB); await p.fill('#utente', 'danilo'); await p.fill('#password', process.env.DBPASS || '');
  await p.fill('#prefisso', 'cms_'); await p.check('input[name=crea]'); await shot('install-2-database');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok('database accettato, passo 3', p.url().includes('passo=3'), p.url());
  // 3. sito e amministratore
  const urlRilevato = await p.inputValue('#url');
  ok('indirizzo del sito rilevato', urlRilevato === B, urlRilevato);
  await p.fill('#nome', 'Bottega di prova'); await p.fill('#descrizione', 'Un sito di prova installato dall\'installatore.'); await p.fill('#email', 'sito@esempio.it');
  // 1.1.3: la password dell'amministratore passa dalle stesse regole del pannello (prima bastavano 10 caratteri)
  for (const [debole, attesa] of [['password1234', /più usate/], ['prova-di-casa-2026', /nome utente/], ['qwertyuiop12', /tastiera/]]) {
    await p.fill('#admin_nome', 'prova'); await p.fill('#admin_email', 'prova@esempio.it'); await p.fill('#admin_password', debole); await p.fill('#admin_password2', debole);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    const e = await p.locator('.errore').innerText().catch(() => '');
    ok('installatore: password debole "' + debole + '" rifiutata', p.url().includes('passo=3') && attesa.test(e), e);
  }
  await p.fill('#admin_nome', 'prova'); await p.fill('#admin_email', 'prova@esempio.it'); await p.fill('#admin_password', 'Collaudo-Officina-2026'); await p.fill('#admin_password2', 'Collaudo-Officina-2026');
  await shot('install-3-sito');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok('passo 4, riepilogo', p.url().includes('passo=4') && await p.locator('button:has-text("Installa adesso")').count() === 1);
  await shot('install-4-riepilogo');
  await Promise.all([p.waitForNavigation({ timeout: 120000 }), p.click('button:has-text("Installa adesso")')]);
  ok('installazione completata (passo 5)', p.url().includes('passo=5'), p.url());
  await shot('install-5-fine');
  const voci = await p.locator('.elenco.piccolo li').allInnerTexts();
  ok('log: amministratore, home, articolo, menu', voci.some(v => /amministratore/.test(v)) && voci.some(v => /Home/.test(v)) && voci.some(v => /articolo/.test(v)) && voci.some(v => /menu/.test(v)), voci.slice(0, 40));
  // 4. l'installatore ora rimanda al pannello
  await p.goto(B + CMSB + '/install/'); await p.waitForLoadState('networkidle');
  ok('install/ dopo l\'installazione rimanda al pannello', p.url().includes('/admin/'), p.url());
  // 5. il sito pubblico
  const home = await p.goto(B + '/', { waitUntil: 'networkidle' });
  ok('home 200', home.status() === 200);
  const h1 = await p.locator('h1').first().innerText();
  ok('home con il nome del sito nel titolo', /Bottega di prova/i.test(h1), h1);
  ok('header con il menu Principale', await p.locator('header a:has-text("Chi sono")').count() >= 1 && await p.locator('header a:has-text("Contatti")').count() >= 1);
  ok('footer presente', await p.locator('footer').count() >= 1);
  // 1.8.0: il link al sito ufficiale (plumecms.danilofailli.it, la frase in fondo al sito) è voluto; nient'altro di danilofailli.
  ok('nessuna traccia di danilofailli nella home (tranne il sito ufficiale)', !/danilofailli|FuoriDalCoso|Deep Office/i.test((await p.content()).split('https://plumecms.danilofailli.it').join('')));
  await shot('sito-home');
  const blog = await p.goto(B + '/blog/', { waitUntil: 'networkidle' }); ok('blog 200 con l\'articolo di benvenuto', blog.status() === 200 && (await p.content()).includes('Benvenuto'));
  const art = await p.goto(B + '/benvenuto/', { waitUntil: 'networkidle' }); ok('articolo di benvenuto 200', art.status() === 200 && /Benvenuto/.test(await p.locator('h1').first().innerText()));
  await shot('sito-articolo');
  const cont = await p.goto(B + '/contatti/', { waitUntil: 'networkidle' }); ok('contatti 200 con il form', cont.status() === 200 && await p.locator('form').count() >= 1);
  const sm = await p.goto(B + '/sitemap.xml'); const smT = await sm.text(); ok('sitemap.xml con home e articolo', sm.status() === 200 && smT.includes('<urlset') && smT.includes('/benvenuto/'));
  const rb = await p.goto(B + '/robots.txt'); const rbT = await rb.text(); ok('robots.txt con la sitemap', rb.status() === 200 && /Sitemap:/.test(rbT), rbT.split('\n').slice(0, 4));
  const no = await p.goto(B + '/pagina-che-non-esiste/'); ok('404 su una pagina inesistente', no.status() === 404);
  // 1.4.0: l'icona di partenza, a /favicon.ico e nel <link rel="icon">, anche con il CMS in una cartella.
  await p.goto(B + '/', { waitUntil: 'networkidle' });
  const icone = await p.$$eval('link[rel=icon]', l => l.map(x => x.getAttribute('href')));
  const fi = await p.request.get(B + '/favicon.ico'); const fiB = await fi.body();
  const fl = await p.request.get(new URL(icone[0] || '/', B).href);
  ok('favicon di partenza: /favicon.ico 200 (ICO) e link rel=icon nella cartella del sito', fi.status() === 200 && fiB.readUInt16LE(2) === 1 && fl.status() === 200 && icone.length >= 1 && icone.every(h => h.startsWith(new URL(B).pathname.replace(/\/$/, '') + '/')), { stato: fi.status(), icone });
  // 6. pannello
  await p.goto(B + CMSB + '/admin/login.php'); await p.fill('#username', 'prova'); await p.fill('#password', 'Collaudo-Officina-2026');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok('login nel pannello', !p.url().includes('login.php'), p.url());
  ok('dashboard senza errori fatali', !(await p.content()).includes('Fatal error') && await p.locator('.admin-sidebar').count() === 1);
  await shot('pannello-dashboard');
  const menu = await p.locator('.admin-sidebar a, .admin-sidebar summary').allInnerTexts();
  ok('menu senza le voci del modulo danilofailli', !menu.some(t => /Aforismi|Test|Social copy|Diagnosi|Importa SQL/.test(t)), menu.filter(t => /Aforismi|Test|Social|Diagnosi|Importa/.test(t)));
  // Stile
  await p.goto(B + CMSB + '/admin/design.php', { waitUntil: 'networkidle' }); ok('Stile del sito si apre', await p.locator('h1, h2').first().count() === 1 && !(await p.content()).includes('Fatal')); await shot('pannello-stile');
  // Pagina nuova nell'editor: titolo, testo, icona, galleria
  await p.goto(B + CMSB + '/admin/editor.php?nuova=1&tipo=pagine', { waitUntil: 'networkidle' }); await p.waitForTimeout(1200);
  ok('editor aperto su una pagina nuova', p.url().includes('editor.php') && await p.locator('.ed-widget-voce').count() > 20, p.url());
  const idPag = new URL(p.url()).searchParams.get('id');
  const fr = () => p.frames().find(f => f.url().includes('anteprima'));
  for (const t of ['icona', 'galleria']) {
    // La voce può stare in un gruppo chiuso del pannello dei widget: lo si apre e si clicca con il DOM, come farebbe l'utente dopo aver aperto il gruppo.
    await p.evaluate((tipo) => { const el = document.querySelector('.ed-widget-voce[data-tipo=' + tipo + ']'); let d = el && el.closest('details'); while (d) { d.open = true; d = d.parentElement && d.parentElement.closest('details'); } if (el) el.scrollIntoView(); }, t);
    await p.waitForTimeout(200);
    const v = p.locator(`.ed-widget-voce[data-tipo=${t}]`).first();
    if (await v.isVisible()) await v.click(); else await v.evaluate(e => e.click());
    await p.waitForTimeout(900);
  }
  ok('widget icona e galleria aggiunti', await fr().locator('.ed-w-icona').count() >= 1 && await fr().locator('.ed-w-galleria').count() >= 1);
  await shot('editor-pagina');
  await p.click('#ed-apri-impostazioni'); await p.waitForTimeout(400); await p.fill('#ed-form-impostazioni input[name=titolo]', 'Pagina di prova dell\'installazione');
  const slugIn = p.locator('#ed-form-impostazioni input[name=slug]'); if (await slugIn.count()) await slugIn.fill('pagina-di-prova');
  await p.click('#ed-form-impostazioni button[type=submit]'); await p.waitForTimeout(800);
  p.once('dialog', d => d.accept()); await p.click('#ed-pubblica'); await p.waitForTimeout(1500);
  const pg = await p.goto(B + '/pagina-di-prova/', { waitUntil: 'networkidle' });
  ok('pagina pubblicata visibile sul sito, con header e footer', pg.status() === 200 && await p.locator('header').count() >= 1 && await p.locator('footer').count() >= 1 && await p.locator('.ed-w-icona').count() >= 1, pg.status());
  await shot('sito-pagina-nuova');
  // Articolo nuovo dalla scheda classica
  await p.goto(B + CMSB + '/admin/contenuto.php?tipo=articoli&scheda=1', { waitUntil: 'networkidle' });
  await p.fill('input[name=title]', 'Secondo articolo di prova');
  // dalla 1.4.0 la casella del codice si vede solo con la scheda Testo aperta (prima l'attributo hidden non nascondeva niente)
  if (await p.locator('.te-schede button[data-scheda=testo]').count() && !(await p.locator('#te-testo').isVisible())) await p.click('.te-schede button[data-scheda=testo]');
  const ta = p.locator('textarea[name=contenuto], textarea[name=content]').first(); if (await ta.count()) await ta.fill('<p>Testo del secondo articolo.</p>');
  const pubblica = p.locator('.sc-pubblica-azioni .btn-primary, button[name=azione][value=pubblica]').first();
  if (await pubblica.count()) { await Promise.all([p.waitForNavigation(), pubblica.click()]); }
  const a2 = await p.goto(B + '/blog/', { waitUntil: 'networkidle' });
  ok('articolo nuovo nel blog', a2.status() === 200 && (await p.content()).includes('Secondo articolo di prova'));
  // Media (blindatura, attacco 3): un'immagine vera caricata dalla libreria risponde 200 al suo indirizzo,
  // e la pagina Sicurezza controlla la stessa cartella.
  await p.goto(B + CMSB + '/admin/media.php', { waitUntil: 'networkidle' });
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');
  await p.setInputFiles('input[type=file][name=file]', { name: 'prova-installazione.png', mimeType: 'image/png', buffer: png });
  await Promise.all([p.waitForNavigation(), p.click('form[enctype] button[type=submit]')]);
  const caricata = ((await p.content()).match(/Caricata: ([^<\s]+)/) || [])[1] || '';
  const img = caricata ? await p.request.get((caricata.startsWith('http') ? '' : new URL(B).origin) + caricata) : null;
  ok('media: immagine caricata e servita (200) al suo indirizzo', !!caricata && img.status() === 200 && /image\/png/.test(img.headers()['content-type'] || ''), { url: caricata, status: img && img.status() });
  ok('media: indirizzo dentro la cartella del CMS (uploads/)', /\/uploads\/pagine\//.test(caricata), caricata);
  await p.goto(B + CMSB + '/admin/sicurezza.php', { waitUntil: 'networkidle' });
  const voceUp = await p.locator('.sic-voce', { hasText: 'PHP spento nella cartella delle immagini' }).first();
  ok('Sicurezza: la voce della cartella immagini e verde (c\'e uploads/.htaccess)', await voceUp.locator('.sic-ok').count() === 1, await voceUp.innerText().catch(() => ''));
  await shot('pannello-sicurezza');
  // SEO
  await p.goto(B + CMSB + '/admin/seo.php', { waitUntil: 'networkidle' }); ok('pannello SEO si apre', !(await p.content()).includes('Fatal') && await p.locator('h1, h2').count() >= 1); await shot('pannello-seo');
  // Versioni / Informazioni
  const info = await p.goto(B + CMSB + '/admin/informazioni.php', { waitUntil: 'networkidle' });
  ok('pagina Informazioni (versione)', info.status() === 200 && /\d+\.\d+\.\d+/.test(await p.content()), info.status());
  await shot('pannello-informazioni');
  ok('nessun errore JS', err.length === 0, err.slice(0, 5));
  console.log(`\n${n - ko} su ${n} OK`); await br.close(); process.exit(ko ? 1 : 0);
})().catch(e => { console.log('ECCEZIONE', e.message.slice(0, 500)); process.exit(1); });
