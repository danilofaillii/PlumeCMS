// Il plugin Ponte (2.19.0) come un utente, contro un WordPress vero che toglie le credenziali come certi hosting (Aruba):
// router-wp.php con WP_TOGLI_AUTH=1. La password per le applicazioni non passa e il messaggio porta al ponte; il plugin si
// scarica dal pannello, si carica e si attiva da WordPress, il ponte si apre, il codice si copia, analisi e import vanno
// fino al riepilogo; poi le difese del ponte con richieste fatte a mano: firma, ora, ripetute, rotte vietate, solo lettura,
// ponte chiuso, blocco dopo 20 firme sbagliate (per ultimo: tiene fuori 127.0.0.1 per 15 minuti).
// uso: node prova-ponte.js <base CMS> <cartella CMS> <utente> <password> <base WordPress senza credenziali> <cartella WordPress> <cartella segreti> <cartella schermate>
// WordPress: prove/wp-installa.php e prove/wp-riempi.php, poi
//   WP_TOGLI_AUTH=1 DBPASS=... PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8091 -t <cartella WP> prove/router-wp.php
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const fs = require('fs'), path = require('path'), crypto = require('crypto'), os = require('os');
const [B, CMS, U, P, WP, WPDIR, SEG, OUT] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, 'importa-wp-cli.php'), CMS, ...a]).toString());
const atteso = JSON.parse(fs.readFileSync(path.join(SEG, 'atteso.json'), 'utf8'));
const app = fs.readFileSync(path.join(SEG, 'app.txt'), 'utf8').trim();
const login = fs.readFileSync(path.join(SEG, 'login.txt'), 'utf8').trim();
const dorme = ms => new Promise(r => setTimeout(r, ms));
fs.mkdirSync(OUT, { recursive: true });

(async () => {
  const conf = path.join(CMS, 'includes/config.local.php');
  let c = fs.readFileSync(conf, 'utf8');
  c = c.split('\n').filter(r => !/^define\('CMS_IMPORTA_WP_(LOCALE|GIRO)'/.test(r)).join('\n');
  fs.writeFileSync(conf, c.trimEnd() + `\ndefine('CMS_IMPORTA_WP_LOCALE', '${new URL(WP).host}'); // solo prove in locale\ndefine('CMS_IMPORTA_WP_GIRO', 0.3); // solo prove\n`);
  await dorme(2000);
  // Un ponte lasciato da un giro di prima si chiude, e il plugin si toglie: lo si carica da capo.
  fs.rmSync(path.join(WPDIR, 'wp-content/plugins/plumecms-ponte'), { recursive: true, force: true });
  execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='${new URL(WP).host}';require '${WPDIR}/wp-load.php';update_option('active_plugins',array_values(array_filter((array)get_option('active_plugins'),function($x){return strpos($x,'plumecms-ponte')===false;})));delete_option('plume_ponte');delete_option('plume_prova_guasto');global $wpdb;$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%plume_ponte_%'");`]);

  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 }, acceptDownloads: true }); const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  pnConferme.on(p, d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', U); await p.fill('#password', P);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/importa-wordpress.php', { waitUntil: 'networkidle' });
  if (await p.locator('button:has-text("Annulla l\'import")').count()) await Promise.all([p.waitForNavigation(), p.locator('button:has-text("Annulla l\'import")').first().click()]);
  if (await p.locator('button:has-text("Lascia perdere"), button:has-text("Ricomincia")').count()) await Promise.all([p.waitForNavigation(), p.locator('button:has-text("Lascia perdere"), button:has-text("Ricomincia")').first().click()]);

  ok('due modi di collegarsi, la password per le applicazioni scelta di partenza', await p.isChecked('input[name=modo][value=password]') && await p.locator('#iw-codice').isHidden() && await p.locator('#iw-password').isVisible());
  const messaggio = async () => (await p.locator('.flash-error').allInnerTexts()).join(' ');
  // 1. La password per le applicazioni giusta, ma il server la toglie.
  await p.fill('#iw-indirizzo', WP); await p.fill('#iw-utente', 'admin'); await p.fill('#iw-password', app);
  await Promise.all([p.waitForNavigation({ timeout: 60000 }), p.click('button:has-text("Collegati e analizza il sito")')]);
  let m = await messaggio();
  ok('credenziali tolte dal server: dice le due cause possibili e offre il ponte', /Controlla il nome utente e la password/.test(m) && /toglie le credenziali/.test(m) && /plugin Ponte/.test(m), m);
  ok('dopo l\'errore indirizzo e utente restano, la password no', await p.inputValue('#iw-indirizzo') === WP && await p.inputValue('#iw-utente') === 'admin' && await p.inputValue('#iw-password') === '');
  await p.click('input[name=modo][value=ponte]');
  ok('un clic su "Con il plugin Ponte" mostra il codice e nasconde la password', await p.locator('#iw-codice').isVisible() && await p.locator('#iw-password').isHidden());
  ok('i passi del ponte si vedono, quelli della password no', await p.locator('.iw-passi-ponte li').count() === 4 && await p.locator('.iw-passi').isHidden());
  await p.screenshot({ path: `${OUT}/ponte-scelta.png`, fullPage: true });
  await p.click('input[name=modo][value=password]');
  ok('si torna alla password con un clic', await p.locator('#iw-password').isVisible() && await p.locator('#iw-codice').isHidden() && await p.getAttribute('#iw-codice', 'required') === null);
  await p.click('input[name=modo][value=ponte]');

  // 2. Lo zip del plugin.
  const [dl] = await Promise.all([p.waitForEvent('download'), p.click('#iw-scarica-ponte')]);
  const zip = path.join(OUT, 'plumecms-ponte.zip'); await dl.saveAs(zip);
  const elenco = execFileSync('unzip', ['-l', zip]).toString();
  ok('il plugin si scarica: plumecms-ponte.zip con il file del plugin', dl.suggestedFilename() === 'plumecms-ponte.zip' && /plumecms-ponte\/plumecms-ponte\.php/.test(elenco) && /readme\.txt/.test(elenco), elenco);
  const anon = await br.newContext();
  const r0 = await anon.request.get(B + '/admin/importa-wordpress.php?scarica=ponte', { maxRedirects: 0 });
  ok('senza login lo zip non si scarica', r0.status() >= 300 && r0.status() < 400, r0.status());

  // 3. Su WordPress: Carica plugin, Attiva, Apri il ponte.
  const w = await ctx.newPage();
  await w.goto(WP + '/wp-login.php'); await w.fill('#user_login', 'admin'); await w.fill('#user_pass', login);
  await Promise.all([w.waitForNavigation(), w.click('#wp-submit')]);
  await w.goto(WP + '/wp-admin/plugin-install.php?tab=upload');
  await w.setInputFiles('#pluginzip', zip);
  await Promise.all([w.waitForNavigation({ timeout: 60000 }), w.click('#install-plugin-submit')]);
  await Promise.all([w.waitForNavigation(), w.click('a[href*="action=activate"]')]);
  ok('WordPress: il plugin si carica e si attiva', await w.locator('tr[data-slug="plumecms-ponte"].active').count() === 1);
  await w.goto(WP + '/wp-admin/tools.php?page=plumecms-ponte');
  ok('WordPress: Strumenti > plumeCMS Ponte, ponte chiuso di partenza', /Il ponte è chiuso/.test(await w.locator('#plume-ponte').innerText()));
  await Promise.all([w.waitForNavigation(), w.click('button:has-text("Apri il ponte")')]);
  const codice = await w.inputValue('#plume-ponte-codice');
  ok('WordPress: il ponte si apre e mostra il codice con la scadenza', /^PLUME1-[A-Za-z0-9_-]{43}$/.test(codice) && /vale fino a/.test(await w.locator('#plume-ponte').innerText()), codice.slice(0, 7));
  await w.screenshot({ path: `${OUT}/ponte-wordpress.png`, fullPage: true });
  const segreto = path.join(os.tmpdir(), 'ponte-codice-' + process.pid + '.txt'); fs.writeFileSync(segreto, codice);

  // 4. Codici sbagliati, poi quello giusto.
  const collega = async cod => {
    await p.fill('#iw-indirizzo', WP); await p.fill('#iw-codice', cod);
    await Promise.all([p.waitForNavigation({ timeout: 60000 }), p.click('button:has-text("Collegati e analizza il sito")')]);
    return messaggio();
  };
  m = await collega('codice a caso');
  ok('codice senza la forma giusta: lo dice', /non ha la forma giusta/.test(m), m);
  m = await collega('PLUME1-' + 'A'.repeat(43));
  ok('codice con la forma giusta ma sbagliato: lo dice', /non è quello giusto/.test(m), m);
  ok('il codice sbagliato non resta nel campo', await p.inputValue('#iw-codice') === '');
  m = await collega(' ' + codice + '\n');
  ok('codice giusto (anche con spazi intorno): parte l\'analisi', m === '' && /Leggo il sito|Il report/.test(await p.locator('.iw').innerText()), m);
  await p.waitForSelector('h2:has-text("Il report di")', { timeout: 180000 });
  const voci = {};
  for (const el of await p.locator('.iw-voce').all()) voci[await el.locator('[data-iw-voce]').getAttribute('data-iw-voce')] = await el.innerText();
  const pub = atteso.articoli.filter(a => a.stato === 'publish').length, fut = atteso.articoli.filter(a => a.stato === 'future').length, boz = atteso.articoli.length - pub - fut;
  ok('report dal ponte: articoli con bozze e programmati', new RegExp(`${pub} pubblicati, ${boz} bozze, ${fut} programmat[oi]`).test(voci.articoli), voci.articoli);
  ok('report dal ponte: pagine, categorie, tag, media, menu', new RegExp(`${atteso.pagine.length}`).test(voci.pagine) && voci.categorie.includes(String(atteso.categorie.length)) && voci.tag.includes(String(atteso.tag)) && /15 immagini e 1 altro file/.test(voci.media) && /8 voci in tutto/.test(voci.menu), voci);
  ok('report dal ponte: SEO di Yoast e redirect', /Yoast/.test(voci.seo) && /%year%\/%monthnum%\/%postname%/.test(voci.redirect), [voci.seo, voci.redirect]);
  ok('report dal ponte: autori', /Marco Bianchi/.test(await p.locator('.iw-card').first().innerText()));
  ok('il codice è cifrato durante il lavoro, mai in chiaro', cli('stato').segreto === true && cli('cerca', segreto).trovati.length === 0 && !(await p.content()).includes(codice));
  await p.screenshot({ path: `${OUT}/ponte-report.png`, fullPage: true });

  // 5. Import di tutto, con il codice dimenticato a metà (come dopo un giorno fermo).
  await p.click('[data-iw-tutto="1"]');
  let giri = 0;
  await p.route('**/admin/importa-wordpress.php', async route => {
    if (route.request().method() === 'POST' && /giro/.test(route.request().postData() || '')) { giri++; if (giri === 3) { await route.abort(); return; } }
    return route.continue();
  });
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Importa le voci scelte")')]);
  while (giri < 3) await dorme(100);
  await p.unroute('**/admin/importa-wordpress.php');
  await dorme(1500);
  cli('dimentica');
  await p.goto(B + '/admin/importa-wordpress.php', { waitUntil: 'networkidle' });
  ok('codice dimenticato: la pagina chiede il codice del ponte', /riscrivi il codice del ponte/.test(await p.locator('#iw-lavoro').innerText()), await p.locator('#iw-lavoro').innerText());
  await p.fill('#iw-lavoro input[name=password]', codice);
  await Promise.all([p.waitForNavigation(), p.click('#iw-lavoro button:has-text("Continua")')]);
  await p.waitForSelector('h2:has-text("Riepilogo")', { timeout: 300000 });
  const stat = async e => +(await p.locator(`.stat-card:has(.stat-card-label:text-is("${e}")) .stat-card-value`).innerText());
  ok('riepilogo: tutti gli articoli, le pagine senza Contatti', await stat('Articoli') === atteso.articoli.length && await stat('Pagine') === atteso.pagine.length - 1, { articoli: await stat('Articoli'), pagine: await stat('Pagine') });
  ok('riepilogo: file, menu e redirect', await stat('File nei media') > 0 && await stat('Menu') === 1 && await stat('Redirect') > 20);
  const cont = cli('contenuti').filter(x => x.genere === 'articolo').map(x => x.titolo);
  ok('nessun doppione dopo la ripresa', new Set(cont).size === cont.length);
  ok('dopo l\'import il codice non c\'è più, nemmeno cifrato', cli('stato').segreto === false && cli('cerca', segreto).trovati.length === 0);
  await p.screenshot({ path: `${OUT}/ponte-riepilogo.png`, fullPage: true });
  await Promise.all([p.waitForNavigation(), p.locator('button:has-text("Annulla l\'import")').first().click()]);

  // 6. Le difese del ponte, con richieste fatte a mano.
  const ponte = async (campi, metodo = 'POST') => {
    const r = await anon.request.fetch(WP + '/?plume_ponte=1', { method: metodo, form: metodo === 'POST' ? campi : undefined, maxRedirects: 0 });
    let j = null; try { j = await r.json(); } catch (e) {}
    return { s: r.status(), j };
  };
  const firma = (cod, rotta, q, t = Math.floor(Date.now() / 1000), nn = crypto.randomBytes(16).toString('hex')) => {
    const qj = JSON.stringify(q);
    return { rotta, q: qj, t: String(t), n: nn, firma: crypto.createHmac('sha256', cod).update(`plume-ponte|1|${t}|${nn}|${rotta}|${qj}`).digest('hex') };
  };
  let r = await ponte(firma(codice, '/wp/v2/posts', { per_page: 1, status: 'draft', context: 'edit' }));
  ok('richiesta firmata: risponde come la REST, bozze comprese', r.s === 200 && r.j?.codice === 200 && Array.isArray(r.j.corpo) && r.j.corpo[0]?.status === 'draft' && +r.j.header['x-wp-total'] > 0, r);
  const una = firma(codice, '/', {});
  r = await ponte(una); r = await ponte(una);
  ok('la stessa richiesta due volte: la seconda si rifiuta', r.s === 403 && r.j?.errore === 'ripetuta', r);
  r = await ponte(firma(codice, '/', {}, Math.floor(Date.now() / 1000) - 600));
  ok('richiesta di 10 minuti fa: si rifiuta', r.s === 403 && r.j?.errore === 'ora', r);
  r = await ponte({ ...firma(codice, '/wp/v2/posts', {}), rotta: '/wp/v2/users' });
  ok('rotta cambiata dopo la firma: si rifiuta', r.s === 403 && r.j?.errore === 'firma', r);
  for (const rotta of ['/wp/v2/users/1/application-passwords', '/wp/v2/plugins', '/wp/v2/themes', '/wp/v2/users/1/application-passwords/introspect', '/wp-site-health/v1/tests/background-updates', '/elementor/v1/globals', '/wp/v2/../../x']) {
    r = await ponte(firma(codice, rotta, {}));
    ok('rotta vietata: ' + rotta, r.s === 403 && r.j?.errore === 'rotta' && r.j.corpo === undefined, r);
  }
  const prima = execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='${new URL(WP).host}';require '${WPDIR}/wp-load.php';echo wp_count_posts()->publish;`]).toString();
  const unPost = (await ponte(firma(codice, '/wp/v2/posts', { per_page: 1 }))).j.corpo[0].id;
  r = await ponte(firma(codice, '/wp/v2/posts/' + unPost, { _method: 'DELETE', force: true }));
  const dopo = execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='${new URL(WP).host}';require '${WPDIR}/wp-load.php';echo wp_count_posts()->publish;`]).toString();
  ok('solo lettura: _method=DELETE non cancella niente', prima === dopo && r.j?.codice === 200 && r.j.corpo?.id === unPost, { prima, dopo, r });
  r = await ponte({}, 'GET');
  ok('una GET al ponte si rifiuta', r.s === 405, r);
  r = await ponte({ ...firma(codice, '/', {}), firma: 'zz' });
  ok('firma senza la forma giusta: si rifiuta', r.s === 403 && r.j?.errore === 'richiesta', r);
  ok('nessuna risposta del ponte contiene il codice', !JSON.stringify(r).includes(codice));

  // 2.19.1, ponte 1.0.1: WordPress o un altro plugin va in errore mentre il ponte prepara la risposta (il caso di un sito vero:
  // 500 alla lettura delle categorie). Il ponte risponde con il motivo, lo scrive nella sua pagina, e un errore fatale nei filtri
  // li fa saltare dalla richiesta dopo; plumeCMS lo dice in parole semplici e Riprova continua.
  const wpphp = code => execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='${new URL(WP).host}';require '${WPDIR}/wp-load.php';` + code]).toString();
  // Aspetta che l'analisi si fermi con un errore o arrivi al report (la pagina si ricarica da sola alla fine).
  const giroErrore = async () => {
    for (let i = 0; i < 240; i++) {
      await dorme(500);
      try {
        const st = await p.evaluate(() => { const e = document.getElementById('iw-errore'); return { errore: e && !e.hidden ? e.innerText : '', report: /Il report di/.test(document.body.innerText) }; });
        if (st.errore) return st.errore;
        if (st.report) return '';
      } catch (e) { /* la pagina si sta ricaricando */ }
    }
    return 'tempo scaduto';
  };
  wpphp(`update_option('plume_prova_guasto','eccezione');`);
  r = await ponte(firma(codice, '/wp/v2/categories', { per_page: 100, page: 1, _fields: 'id,name' }));
  ok('guasto che si intercetta: il ponte risponde con il motivo, non con un 500 vuoto', r.s === 500 && r.j?.ponte === 1 && r.j.errore === 'interno' && /guasto di prova nelle categorie/.test(r.j.dettaglio) && !/\/tmp\/|\/home\//.test(r.j.dettaglio), r);
  await w.goto(WP + '/wp-admin/tools.php?page=plumecms-ponte');
  ok('la pagina del ponte mostra l\'ultimo errore, con la rotta', /Ultimo errore del ponte/.test(await w.locator('#plume-ponte-errore').innerText()) && /\/wp\/v2\/categories/.test(await w.locator('#plume-ponte-errore').innerText()) && /guasto di prova/.test(await w.locator('#plume-ponte-errore').innerText()));
  await w.locator('#plume-ponte-errore').screenshot({ path: `${OUT}/ponte-errore.png` });
  await p.goto(B + '/admin/importa-wordpress.php', { waitUntil: 'networkidle' });
  if (await p.locator('button:has-text("Lascia perdere"), button:has-text("Ricomincia")').count()) await Promise.all([p.waitForNavigation(), p.locator('button:has-text("Lascia perdere"), button:has-text("Ricomincia")').first().click()]);
  await p.click('input[name=modo][value=ponte]');
  m = await collega(codice);
  let eg = await giroErrore();
  ok('plumeCMS: l\'analisi si ferma alle categorie e dice che si è fermato WordPress, con il motivo', /WordPress si è fermato con un errore/.test(eg) && /guasto di prova nelle categorie/.test(eg) && /\/wp\/v2\/categories/.test(eg), eg);
  await p.screenshot({ path: `${OUT}/ponte-errore-plume.png`, fullPage: false });
  wpphp(`delete_option('plume_prova_guasto');`);
  await p.click('#iw-riprendi');
  eg = await giroErrore();
  ok('tolto il guasto, Riprova continua fino al report', eg === '' && /Il report di/.test(await p.locator('body').innerText()), eg);
  // 2.19.1: un'immagine con le misure salvate male (caso vero) fa andare in errore la REST con media_details: l'analisi rilegge
  // la pagina senza e arriva al report, con tutti i file e il peso che manca detto.
  wpphp(`update_option('plume_prova_guasto','media');`);
  await p.goto(B + '/admin/importa-wordpress.php', { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.locator('button:has-text("Lascia perdere"), button:has-text("Ricomincia")').first().click()]);
  await p.click('input[name=modo][value=ponte]');
  m = await collega(codice);
  eg = await giroErrore();
  const vmedia = eg === '' ? await p.locator('.iw-voce:has([data-iw-voce=media])').innerText() : '';
  ok('immagine con le misure guaste: l\'analisi arriva al report con tutti i file e dice del peso', eg === '' && /15 immagini e 1 altro file/.test(vmedia) && /senza peso dichiarato/.test(vmedia), [eg, vmedia]);
  wpphp(`delete_option('plume_prova_guasto');`);
  // Errore fatale nei filtri dopo la risposta: la prima volta il motivo, poi il ponte li salta e i dati arrivano (con _fields).
  wpphp(`update_option('plume_prova_guasto','fatale');delete_option('plume_ponte_errore');delete_option('plume_ponte_senza_filtri');`);
  r = await ponte(firma(codice, '/wp/v2/categories', { per_page: 100, _fields: 'id,name' }));
  ok('errore fatale nei filtri: risponde il ponte, con la fase', r.s === 500 && r.j?.errore === 'interno' && r.j.fase === 'filtri' && /guasto fatale di prova/.test(r.j.dettaglio), r);
  r = await ponte(firma(codice, '/wp/v2/categories', { per_page: 100, _fields: 'id,name' }));
  ok('la richiesta dopo salta i filtri: arrivano le categorie, solo id e nome', r.s === 200 && r.j?.codice === 200 && Array.isArray(r.j.corpo) && r.j.corpo.length > 0 && r.j.corpo.every(c => Object.keys(c).sort().join() === 'id,name') && +r.j.header['x-wp-total'] > 0, r);
  await w.goto(WP + '/wp-admin/tools.php?page=plumecms-ponte');
  ok('la pagina del ponte dice che ora salta i filtri', /salta i filtri/.test(await w.locator('#plume-ponte-errore').innerText()));
  wpphp(`delete_option('plume_prova_guasto');delete_option('plume_ponte_errore');delete_option('plume_ponte_senza_filtri');`);
  await p.goto(B + '/admin/importa-wordpress.php', { waitUntil: 'networkidle' });
  if (await p.locator('button:has-text("Lascia perdere"), button:has-text("Ricomincia")').count()) await Promise.all([p.waitForNavigation(), p.locator('button:has-text("Lascia perdere"), button:has-text("Ricomincia")').first().click()]);

  // Chiudi il ponte: il codice non vale più, e neanche da plumeCMS.
  await w.goto(WP + '/wp-admin/tools.php?page=plumecms-ponte');
  await Promise.all([w.waitForNavigation(), w.click('button:has-text("Chiudi il ponte")')]);
  r = await ponte(firma(codice, '/', {}));
  ok('ponte chiuso: il codice vecchio non vale più', r.s === 403 && r.j?.errore === 'chiuso', r);
  await p.goto(B + '/admin/importa-wordpress.php', { waitUntil: 'networkidle' });
  await p.click('input[name=modo][value=ponte]');
  m = await collega(codice);
  ok('ponte chiuso: plumeCMS dice di riaprirlo', /chiuso o scaduto/.test(m), m);
  // Ponte scaduto: si apre e si porta la scadenza nel passato.
  await Promise.all([w.waitForNavigation(), w.click('button:has-text("Apri il ponte")')]);
  const codice2 = await w.inputValue('#plume-ponte-codice');
  ok('riaperto: il codice è nuovo', codice2 !== codice && /^PLUME1-/.test(codice2));
  execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='${new URL(WP).host}';require '${WPDIR}/wp-load.php';$s=get_option('plume_ponte');$s['scade']=time()-1;update_option('plume_ponte',$s,false);`]);
  r = await ponte(firma(codice2, '/', {}));
  ok('ponte scaduto (48 ore): si rifiuta e si cancella', r.s === 403 && r.j?.errore === 'chiuso' && execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='${new URL(WP).host}';require '${WPDIR}/wp-load.php';var_export(get_option('plume_ponte'));`]).toString() === 'false', r);
  // Disattivare il plugin cancella il codice.
  await w.goto(WP + '/wp-admin/tools.php?page=plumecms-ponte');
  await Promise.all([w.waitForNavigation(), w.click('button:has-text("Apri il ponte")')]);
  await w.goto(WP + '/wp-admin/plugins.php');
  await Promise.all([w.waitForNavigation(), w.click('tr[data-slug="plumecms-ponte"] .deactivate a')]);
  ok('disattivato il plugin: il codice si cancella', execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='${new URL(WP).host}';require '${WPDIR}/wp-load.php';var_export(get_option('plume_ponte'));`]).toString() === 'false');
  m = await collega(codice2);
  ok('plugin spento: plumeCMS dice che non trova il ponte', /non trovo il plugin Ponte/.test(m), m);
  // Per ultimo: 20 firme sbagliate e l'indirizzo resta fuori (127.0.0.1 per 15 minuti: dopo si svuota a mano).
  await Promise.all([w.waitForNavigation(), w.click('tr[data-slug="plumecms-ponte"] .activate a')]);
  await w.goto(WP + '/wp-admin/tools.php?page=plumecms-ponte');
  await Promise.all([w.waitForNavigation(), w.click('button:has-text("Apri il ponte")')]);
  const codice3 = await w.inputValue('#plume-ponte-codice');
  for (let i = 0; i < 20; i++) await ponte(firma('PLUME1-' + 'B'.repeat(43), '/', {}));
  r = await ponte(firma(codice3, '/', {}));
  ok('dopo 20 firme sbagliate anche quella giusta resta fuori (429)', r.s === 429 && r.j?.errore === 'bloccato', r);
  m = await collega(codice3);
  ok('bloccato: plumeCMS dice di aspettare 15 minuti', /15 minuti/.test(m), m);
  execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='${new URL(WP).host}';require '${WPDIR}/wp-load.php';global $wpdb;$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%plume_ponte_e_%'");delete_option('plume_ponte');`]);

  ok('nessun errore di JavaScript nel pannello', err.length === 0, err);
  wpphp(`delete_option('plume_prova_guasto');delete_option('plume_ponte_errore');delete_option('plume_ponte_senza_filtri');`);
  fs.rmSync(segreto, { force: true });
  await br.close();
  console.log(`${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
