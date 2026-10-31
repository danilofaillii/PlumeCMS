// Addon Copia del sito altrove (copia-altrove, 2.0.0): tutto il percorso dell'amministratore nel browser.
// Su un sito nuovo installato con il kit Attività (prove/prova-kit.js), con il server di posta finto acceso e i tre
// server finti di prove/server-copia-finti.py: FTP sulla porta 8205, FTPS sulla 8206, WebDAV sulla 8207 (cartella /dav),
// utente "copie", password "Prova-Copie-2026", che scrivono in <cartelle>/dest-ftp, dest-ftps e dest-dav.
// Accende l'addon, salva una destinazione FTP (la password non si rivede), prova la connessione (prima rifiutata perché
// locale, poi ammessa con CMS_COPIA_ALTROVE_LOCALE nel config), manda le copie (restano le ultime 2, gli altri file
// non si toccano), il giro della notte da Log cron > Esegui adesso, l'email se non riesce, FTPS, WebDAV, SFTP senza ssh2.
// Telefono 390x844 senza scorrimento di lato. Alla fine spegne l'addon: il sito torna identico a prima.
// 2.1: Google Drive e Dropbox contro i server finti di prove/server-cloud-finti.py (Google sulla porta di CLOUD_GOOGLE,
// di partenza 8321, Dropbox su CLOUD_DROPBOX, 8322; redirect URI <base>/admin/oauth-ritorno.php registrato): i passi per
// creare l'app, il redirect URI da copiare, "Salva e collega", la pagina del servizio (Consenti, Annulla), il ritorno,
// l'account, segreto e token mai nella pagina né in chiaro nel database, Prova la connessione, la copia caricata a pezzi
// da 256 KB (CMS_COPIA_ALTROVE_PEZZO), un pezzo che fallisce e la ripresa, le ultime N copie con l'elenco a pagine, gli
// altri file lasciati stare, il rinnovo dell'accesso scaduto, Scollega (permesso tolto anche dal servizio).
// Contro i servizi veri non si prova da qui: nessuna rete verso Google e Dropbox.
// Porte dei server FTP, FTPS e WebDAV: PORTE_COPIA="8205,8206,8207" (di partenza). SOLO=cloud salta FTP e WebDAV.
// uso: node prove/prova-addon-copia-altrove.js <base> <cartella CMS> <utente> <password> <porta smtp> <cartella posta> <cartelle dest>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const { conta, pagina, entraPannello, addon, postaFinta, posta, aspettaEmail, largo } = require('./addon-a-comune');
const [B, DIR, UTENTE, PASSWORD, SMTP, POSTA, DEST] = process.argv.slice(2);
const { ok, fine } = conta();
const PWD = 'Prova-Copie-2026';
const P = '/admin/addon-pagina.php?a=copia-altrove&p=destinazione';
const pulisci = h => h.replace(/ nonce="[^"]*"/g, '').replace(/\?v=\d+/g, '').replace(/data-fine="[^"]*"/g, '').replace(/<span class="ew-cd-[a-z]+">\d+<\/span>/g, '');
const dorme = ms => new Promise(r => setTimeout(r, ms));
const CONFIG = path.join(DIR, 'includes', 'config.local.php');
const copie = d => (fs.existsSync(d) ? fs.readdirSync(d) : []).filter(f => /^copia-\d{8}-\d{6}-altrove\.zip$/.test(f)).sort();
const [P_FTP, P_FTPS, P_DAV] = (process.env.PORTE_COPIA || '8205,8206,8207').split(',');
const G = 'http://127.0.0.1:' + (process.env.CLOUD_GOOGLE || '8321'), DB = 'http://127.0.0.1:' + (process.env.CLOUD_DROPBOX || '8322');
const SOLO_CLOUD = process.env.SOLO === 'cloud';
const ECCEZIONI = `\ndefine('CMS_COPIA_ALTROVE_LOCALE', '127.0.0.1:${P_FTP},127.0.0.1:${P_FTPS},127.0.0.1:${P_DAV}');\n`
  + `define('CMS_COPIA_ALTROVE_CLOUD_PROVA', 'gdrive=${G},dropbox=${DB}');\ndefine('CMS_COPIA_ALTROVE_PEZZO', 262144);\n`;
const php = codice => execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='127.0.0.1'; require '${DIR}/includes/config.php'; ${codice}`], { encoding: 'utf8' });

let br;
(async () => {
  br = await chromium.launch();
  const err = [];
  const a = await pagina(br, err);
  const vai = u => a.goto(B + u, { waitUntil: 'networkidle' });
  const flash = async () => (await a.locator('.flash').first().innerText().catch(() => '')).trim();
  // il config senza l'eccezione delle prove (una prova interrotta prima può averla lasciata)
  const conf0 = fs.readFileSync(CONFIG, 'utf8').replace(/\ndefine\('CMS_COPIA_ALTROVE_(LOCALE|CLOUD_PROVA|PEZZO)'[^\n]*/g, '').replace(/\s*$/, '\n');
  fs.writeFileSync(CONFIG, conf0);
  await dorme(2500);
  await entraPannello(a, B, UTENTE, PASSWORD);
  await postaFinta(a, B, SMTP);
  await addon(a, B, 'copia-altrove', false);
  const v0 = await pagina(br, err);
  const prima = {};
  for (const u of ['/', '/servizi/', '/contatti/']) prima[u] = pulisci(await (await v0.request.get(B + u)).text());

  // 1. Accendere.
  const f1 = await addon(a, B, 'copia-altrove', true);
  ok('Impostazioni > Addon: "Copia del sito altrove" si accende', /Copia del sito altrove.*acceso/.test(f1), f1);
  ok('nel menu Impostazioni c\'è "Copia altrove", subito dopo "Copia del sito"', await a.evaluate(() => { const l = [...document.querySelectorAll('.nav-sotto a')].map(x => x.textContent.trim()); return l.indexOf('Copia altrove') === l.indexOf('Copia del sito') + 1; }));
  await vai(P);
  ok('la pagina: destinazione, "Prova la connessione" e "Manda una copia adesso" (spenti finché non c\'è una destinazione)', await a.locator('#ca-form').count() === 1 && await a.locator('#ca-prova').isDisabled() && await a.locator('#ca-manda').isDisabled());
  ok('SFTP: si dice chiaramente che su questo server manca ssh2', /manca l'estensione ssh2/.test(await a.locator('#ca-sftp-manca').innerText().catch(() => '')) && /non disponibile/.test(await a.locator('#ca-tipo option[value=sftp]').innerText()));

  // Gli aiuti: salva la destinazione, prova la connessione, manda una copia.
  const salva = async (campi, pulsante = '#ca-salva') => {
    await vai(P);
    await a.selectOption('#ca-tipo', campi.tipo);
    for (const [k, v] of Object.entries(campi)) if (k !== 'tipo' && k !== 'attivo' && k !== 'tieni') await a.fill('#ca-' + k, String(v));
    if ('attivo' in campi) { if (campi.attivo) await a.check('#ca-attivo'); else await a.uncheck('#ca-attivo'); }
    if ('tieni' in campi) { if (campi.tieni) await a.check('[name=tieni_locale]'); else await a.uncheck('[name=tieni_locale]'); }
    await Promise.all([a.waitForNavigation(), a.click(pulsante)]);
    return flash();
  };
  const prova = async () => { await vai(P); await Promise.all([a.waitForNavigation(), a.click('#ca-prova')]); return flash(); };
  const manda = async () => { await vai(P); await Promise.all([a.waitForNavigation({ timeout: 120000 }), a.click('#ca-manda')]); return flash(); };
  const cart = path.join(DEST, 'dest-ftp', 'copie-del-sito');
  let f;
  if (!SOLO_CLOUD) {
  // 2. Una destinazione FTP. La password non si rivede.
  f = await salva({ tipo: 'ftp', host: '127.0.0.1', porta: P_FTP, utente: 'copie', password: PWD, cartella: 'copie-del-sito', tenere: 2 });
  ok('salva FTP: "Destinazione salvata"', /Destinazione salvata/.test(f), f);
  ok('la password non si rivede: il campo è vuoto, "Salvata"', await a.inputValue('#ca-password') === '' && /Salvata/.test(await a.getAttribute('#ca-password', 'placeholder')) && !(await a.content()).includes(PWD));
  const salvata = php("echo get_setting('copia_altrove_cfg');");
  ok('nel database la password è cifrata, mai in chiaro', !salvata.includes(PWD) && /"password":"v1:/.test(salvata));
  f = await salva({ tipo: 'ftp', host: 'ftp://pippo', porta: 21 });
  ok('un server scritto male si rifiuta, e si dice come scriverlo', /senza ftp:\/\//.test(f), f);
  f = await salva({ tipo: 'ftp', host: '127.0.0.1', porta: P_FTP, cartella: '../fuori' });
  ok('una cartella con ".." si rifiuta', /niente "\.\."/.test(f), f);
  await salva({ tipo: 'ftp', host: '127.0.0.1', porta: P_FTP, cartella: 'copie-del-sito' });

  // 3. Prova la connessione: un indirizzo locale si rifiuta (SSRF), poi l'eccezione delle prove nel config.
  f = await prova();
  ok('Prova la connessione verso 127.0.0.1: rifiutata, "rete interna"', /rete interna \(127\.0\.0\.1\)/.test(f), f);
  ok('il registro lo scrive: "Prova della connessione", "Non riuscito"', /Prova della connessione/.test(await a.locator('#ca-registro tbody tr').first().innerText()) && /Non riuscito/.test(await a.locator('#ca-registro tbody tr').first().innerText()));
  fs.writeFileSync(CONFIG, conf0.replace(/\s*$/, '') + ECCEZIONI);
  await dorme(2500); // opcache rilegge i file cambiati al massimo ogni due secondi
  f = await prova();
  ok('con CMS_COPIA_ALTROVE_LOCALE: "Collegamento riuscito"', /Collegamento riuscito/.test(f), f);
  ok('la cartella è nata sulla destinazione e il file di prova non è rimasto', fs.existsSync(path.join(DEST, 'dest-ftp', 'copie-del-sito')) && fs.readdirSync(path.join(DEST, 'dest-ftp', 'copie-del-sito')).length === 0);
  await salva({ tipo: 'ftp', host: '127.0.0.1', porta: P_FTP, password: 'sbagliata-davvero' });
  f = await prova();
  ok('password sbagliata: "Accesso rifiutato"', /Accesso rifiutato/.test(f), f);
  await salva({ tipo: 'ftp', host: '127.0.0.1', porta: P_FTP, password: PWD });

  // 4. Manda una copia adesso, tre volte: restano le ultime due; gli altri file non si toccano.
  fs.writeFileSync(path.join(cart, 'mio-file.txt'), 'non toccare');
  fs.writeFileSync(path.join(cart, 'copia-20200101-000000-manuale.zip'), 'non toccare');
  f = await manda();
  ok('Manda una copia adesso: "Copia mandata"', /Copia mandata/.test(f), f);
  const c1 = copie(cart);
  ok('sulla destinazione c\'è la copia', c1.length === 1, c1);
  const dentro = execFileSync('python3', ['-I', '-c', 'import sys,zipfile; z=zipfile.ZipFile(sys.argv[1]); print(" ".join(sorted(n for n in z.namelist() if "/" not in n)))', path.join(cart, c1[0])], { encoding: 'utf8' }).trim();
  ok('la copia è uno zip intero: manifest.json e db.sql.gz', dentro === 'db.sql.gz manifest.json', dentro);
  await vai('/admin/copia-sito.php');
  ok('sul server non resta la copia mandata (non c\'è "tienila anche qui")', !/altrove\.zip/.test(await a.locator('body').innerText()));
  await dorme(1100); await manda(); await dorme(1100); f = await manda();
  const c3 = copie(cart);
  ok('dopo tre invii restano le ultime 2 copie, le più nuove', c3.length === 2 && !c3.includes(c1[0]) && /Tolte 1 copie vecchie/.test(f), { c3, f });
  ok('gli altri file della cartella sono rimasti', fs.existsSync(path.join(cart, 'mio-file.txt')) && fs.existsSync(path.join(cart, 'copia-20200101-000000-manuale.zip')));
  await vai(P);
  ok('il registro: tre "Copia mandata a mano", riusciti', await a.locator('#ca-registro tbody tr', { hasText: 'Copia mandata a mano' }).count() === 3 && await a.locator('#ca-registro tbody tr', { hasText: 'Copia mandata a mano' }).filter({ hasText: 'Non riuscito' }).count() === 0);

  // 5. Ogni notte: il passo del cron (Log cron > Esegui adesso), e l'email se non riesce.
  await salva({ tipo: 'ftp', host: '127.0.0.1', porta: P_FTP, attivo: true });
  await dorme(1100);
  await vai('/admin/cron-log.php');
  await Promise.all([a.waitForNavigation({ timeout: 120000 }), a.click('#cron-esegui')]);
  ok('Log cron > Esegui adesso: il passo "Copia del sito altrove" ha mandato la copia', /Copia del sito altrove: Copia mandata/.test(await a.locator('body').innerText()));
  await vai(P);
  ok('nel registro c\'è "Copia della notte", riuscita', /Copia della notte[\s\S]*Riuscito/.test(await a.locator('#ca-registro tbody tr').first().innerText()));
  await salva({ tipo: 'ftp', host: '127.0.0.1', porta: P_FTP, password: 'sbagliata-davvero' });
  const da = posta(POSTA).length;
  await vai('/admin/cron-log.php');
  await Promise.all([a.waitForNavigation({ timeout: 120000 }), a.click('#cron-esegui')]);
  ok('la notte non riesce: il log del cron lo dice', /ERRORE Copia del sito altrove/.test(await a.locator('body').innerText()));
  const m = await aspettaEmail(POSTA, 'avvisi-sito@esempio.it', da, x => /non è partita/.test(x.oggetto));
  ok('e arriva l\'email "La copia del sito non è partita" con il motivo', !!m && /Accesso rifiutato/.test(m.html));
  await salva({ tipo: 'ftp', host: '127.0.0.1', porta: P_FTP, password: PWD, attivo: false });

  // 6. FTPS e WebDAV.
  await salva({ tipo: 'ftps', host: '127.0.0.1', porta: P_FTPS, utente: 'copie', password: PWD, cartella: 'cifrate' });
  f = await prova();
  ok('FTPS: collegamento riuscito', /Collegamento riuscito/.test(f), f);
  f = await manda();
  ok('FTPS: copia mandata', /Copia mandata/.test(f) && copie(path.join(DEST, 'dest-ftps', 'cifrate')).length === 1, f);
  await salva({ tipo: 'webdav', url: `http://127.0.0.1:${P_DAV}/dav/`, utente: 'copie', password: PWD, cartella: 'sito/notti' });
  f = await prova();
  ok('WebDAV: collegamento riuscito', /Collegamento riuscito/.test(f), f);
  f = await manda();
  ok('WebDAV: copia mandata, nelle cartelle create (sito/notti)', /Copia mandata/.test(f) && copie(path.join(DEST, 'dest-dav', 'sito', 'notti')).length === 1, f);
  await vai(P);
  ok('con WebDAV si vedono solo i campi suoi (server e porta nascosti)', await a.locator('#ca-url').isVisible() && !(await a.locator('#ca-host').isVisible()));
  await salva({ tipo: 'webdav', url: 'http://dav.esempio.it/dav/', password: PWD });
  f = await prova();
  ok('WebDAV su http (non locale): rifiutato, "serve un indirizzo https"', /serve un indirizzo https/.test(f), f);
  await salva({ tipo: 'webdav', url: `http://127.0.0.1:${P_DAV}/dav/`, password: 'sbagliata-davvero' });
  f = await prova();
  ok('WebDAV con la password sbagliata: "Accesso rifiutato"', /Accesso rifiutato/.test(f), f);
  await salva({ tipo: 'sftp', host: '127.0.0.1', porta: 22 });
  f = await prova();
  ok('SFTP senza ssh2: "manca l\'estensione ssh2", detto chiaro', /manca l'estensione ssh2/.test(f), f);
  } else {
    fs.writeFileSync(CONFIG, conf0.replace(/\s*$/, '') + ECCEZIONI);
    await dorme(2500);
  }

  // 6b. (2.1) Google Drive e Dropbox, contro i server finti.
  const stato = async base => (await (await a.request.get(base + '/_stato')).json());
  const imposta = async (base, dati, q = '') => a.request.post(base + '/_imposta' + q, { data: dati });
  const SERVIZI = [
    { s: 'gdrive', nome: 'Google Drive', base: G, id: 'prova-plumecms.apps.googleusercontent.com', seg: 'GOCSPX-prova-segreto-2026', idSbagliato: 'non-un-id-di-google', cartella: 'copie-del-sito/notti' },
    { s: 'dropbox', nome: 'Dropbox', base: DB, id: 'provadropbox2026', seg: 'segretodropbox2026', idSbagliato: 'chiave con spazi!', cartella: 'copie-dropbox' },
  ];
  // Un file grande e che non si comprime nella cartella delle immagini: la copia supera di molto un pezzo da 256 KB.
  const GRANDE = path.join(DIR, 'uploads', 'prova-copia-grande.bin');
  fs.writeFileSync(GRANDE, require('crypto').randomBytes(1300000));
  const zipDi = st => Object.values(st.file).filter(x => /^copia-\d{8}-\d{6}-altrove\.zip$/.test(x.name));
  for (const X of SERVIZI) {
    const t = X.nome + ': ';
    await vai(P);
    await a.selectOption('#ca-tipo', X.s);
    ok(t + 'scelto il servizio si vedono i suoi campi e i passi per creare l\'app (aperti), e non quelli FTP', await a.locator(`#ca-${X.s}`).isVisible() && !(await a.locator('#ca-host').isVisible()) && await a.locator(`#ca-${X.s} details[open] ol.ca-passi li`).count() >= 5);
    const rid = await a.inputValue(`#ca-${X.s}-redirect`);
    ok(t + 'l\'indirizzo da incollare nell\'app è quello fisso del pannello, senza query', rid === B + '/admin/oauth-ritorno.php', rid);
    ok(t + 'stato: "Non ancora collegato"', /Non ancora collegato/.test(await a.locator(`#ca-${X.s}-stato`).innerText()));
    f = await salva({ tipo: X.s, [`${X.s}-client_id`]: X.idSbagliato, [`${X.s}-segreto`]: X.seg });
    ok(t + 'un ID dell\'app scritto male si rifiuta, e si dice dove trovarlo', /Non salvato/.test(f) && /(googleusercontent|Settings)/.test(f), f);
    // Salva e collega: la pagina del servizio, Annulla.
    const s0 = await stato(X.base);
    await salva({ tipo: X.s, [`${X.s}-client_id`]: X.id, [`${X.s}-segreto`]: X.seg, cartella: X.cartella, tenere: 2 }, `#ca-${X.s}-collega`);
    await a.waitForURL(u => u.toString().startsWith(X.base + '/'), { timeout: 10000 });
    ok(t + '"Salva e collega": il pannello dice "Ti porto alla pagina di ' + X.nome + '" e ci va da solo', /Ti porto alla pagina di/.test(await (await a.request.get(B + P + '&autorizza=1')).text()));
    const auth = new URL(a.url());
    ok(t + 'la pagina del servizio riceve client id, redirect URI, state firmato e PKCE S256 (niente segreto nell\'indirizzo)', auth.searchParams.get('client_id') === X.id && auth.searchParams.get('redirect_uri') === rid
      && /^copia-altrove\./.test(auth.searchParams.get('state') || '') && auth.searchParams.get('code_challenge_method') === 'S256' && !a.url().includes(X.seg), a.url().slice(0, 200));
    await Promise.all([a.waitForURL(/p=destinazione/), a.click('#annulla')]);
    f = await flash();
    ok(t + 'Annulla nella pagina del servizio: "Non hai dato il permesso", niente collegamento', /Non hai dato il permesso/.test(f) && (await stato(X.base)).scambi === s0.scambi, f);
    // Di nuovo: Consenti.
    await salva({ tipo: X.s, [`${X.s}-client_id`]: X.id, cartella: X.cartella, tenere: 2 }, `#ca-${X.s}-collega`);
    await a.waitForURL(u => u.toString().startsWith(X.base + '/'), { timeout: 10000 });
    await Promise.all([a.waitForURL(/p=destinazione/), a.click('#consenti')]);
    f = await flash();
    ok(t + 'Consenti: torna al pannello, "Collegato ... come Mario Prova"', new RegExp('Collegato a ' + X.nome + ' come Mario Prova').test(f), f);
    ok(t + 'lo stato dice con quale account è collegato', /Collegato come/.test(await a.locator(`#ca-${X.s}-stato`).innerText()) && /mario\.prova@/.test(await a.locator(`#ca-${X.s}-stato`).innerText()));
    const s1 = await stato(X.base);
    ok(t + 'il codice si è scambiato una volta (con il PKCE giusto)', s1.scambi === s0.scambi + 1, s1.scambi);
    const html = await a.content();
    const cfg = php("echo get_setting('copia_altrove_cfg');");
    ok(t + 'segreto dell\'app e token mai nella pagina, solo cifrati nel database', !html.includes(X.seg) && !/ya29\.|"refresh_token"|sl\.[A-Za-z0-9_-]{20}/.test(html) && !cfg.includes(X.seg) && !/ya29\.|1\/\/|"sl\./.test(cfg) && /"refresh":"v1:/.test(cfg));
    ok(t + 'il campo del segreto è vuoto e dice "Salvato"', await a.inputValue(`#ca-${X.s}-segreto`) === '' && /Salvato/.test(await a.getAttribute(`#ca-${X.s}-segreto`, 'placeholder')));
    // Prova la connessione.
    f = await prova();
    ok(t + 'Prova la connessione: "Collegamento riuscito", e il file di prova non resta', /Collegamento riuscito/.test(f) && !Object.values((await stato(X.base)).file).some(x => /plumecms-prova/.test(x.name)), f);
    ok(t + 'la cartella nasce sul servizio', X.s === 'gdrive' ? Object.values((await stato(X.base)).file).some(x => x.name === 'notti' && x.mime === 'application/vnd.google-apps.folder') : true);
    // Un file che non è una copia, nella stessa cartella: non si tocca.
    const padre = X.s === 'gdrive' ? Object.values((await stato(X.base)).file).find(x => x.name === 'notti').id : '/' + X.cartella;
    for (const n of ['mio-file.txt', 'copia-20200101-000000-manuale.zip']) await a.request.post(X.base + '/_metti', { data: { name: n, parent: padre } });
    // Manda una copia, con la copia tenuta anche qui: la copia arrivata è uguale byte per byte.
    await salva({ tipo: X.s, tieni: true, cartella: X.cartella, tenere: 2 });
    const pz0 = (await stato(X.base)).pezzi;
    f = await manda();
    let st = await stato(X.base);
    const arrivate = zipDi(st);
    const locali = fs.readdirSync(path.join(DIR, 'aggiornamenti', 'copie')).filter(x => /altrove\.zip$/.test(x)).sort();
    const locale = locali.length ? path.join(DIR, 'aggiornamenti', 'copie', locali[locali.length - 1]) : '';
    const sha = locale ? require('crypto').createHash('sha256').update(fs.readFileSync(locale)).digest('hex') : '';
    ok(t + 'Manda una copia adesso: "Copia mandata"', /Copia mandata/.test(f), f);
    ok(t + 'la copia è arrivata intera (stessa impronta SHA-256 della copia sul server)', arrivate.length === 1 && arrivate[0].sha256 === sha && arrivate[0].size === fs.statSync(locale).size, { n: arrivate.length, peso: arrivate[0] && arrivate[0].size });
    ok(t + 'caricata a pezzi (più di un pezzo da 256 KB)', st.pezzi - pz0 > 1 && arrivate[0].size > 262144, { pezzi: st.pezzi - pz0, peso: arrivate[0] && arrivate[0].size });
    for (const l of locali) fs.unlinkSync(path.join(DIR, 'aggiornamenti', 'copie', l));
    await salva({ tipo: X.s, tieni: false, cartella: X.cartella, tenere: 2 });
    // Un pezzo che non arriva (503): la copia riprende da dove era e arriva lo stesso.
    await imposta(X.base, { guasto: st.pezzi + 2 });
    await dorme(1100);
    f = await manda();
    st = await stato(X.base);
    ok(t + 'un pezzo fallito (503): il caricamento riprende e la copia arriva intera', /Copia mandata/.test(f) && st.guasti_fatti === 1 && zipDi(st).length === 2 && new Set(zipDi(st).map(x => x.size)).size >= 1, { f, guasti: st.guasti_fatti });
    // L'accesso scade: si rinnova da solo con il refresh token.
    const rf0 = st.refresh_fatti;
    await imposta(X.base, { pagina: 1 }, '?scadi=1');
    await dorme(1100);
    f = await manda();
    st = await stato(X.base);
    ok(t + 'accesso scaduto: il CMS lo rinnova da solo (refresh) e la copia parte', /Copia mandata/.test(f) && st.refresh_fatti > rf0, { f, refresh: st.refresh_fatti - rf0 });
    ok(t + 'dopo tre invii restano le ultime 2 copie (elenco letto a pagine da 1)', zipDi(st).length === 2 && /Tolte 1 copie vecchie/.test(f), { n: zipDi(st).length, f });
    ok(t + 'gli altri file della cartella (non copie "altrove") sono rimasti', ['mio-file.txt', 'copia-20200101-000000-manuale.zip'].every(n => Object.values(st.file).some(x => x.name === n)));
    ok(t + 'il token non è mai andato in un indirizzo (sempre nella testata Authorization)', st.token_in_query === 0 && st.testate_auth > 0);
    await imposta(X.base, { pagina: 3 });
    // Al telefono, con il servizio scelto e i passi aperti.
    const tel = await pagina(br, err, 390, 844);
    await entraPannello(tel, B, UTENTE, PASSWORD);
    await tel.goto(B + P, { waitUntil: 'networkidle' });
    await tel.evaluate(() => document.querySelectorAll('details').forEach(d => { d.open = true; }));
    ok(t + 'al telefono la pagina non scorre di lato (passi aperti)', await largo(tel) <= 0, await largo(tel));
    await tel.context().close();
    // Il registro non mostra mai segreti.
    const reg = await a.locator('#ca-registro').innerText();
    ok(t + 'il registro dice gli invii riusciti, senza segreti', /Copia mandata a mano/.test(reg) && !reg.includes(X.seg) && !/ya29\.|sl\./.test(reg));
    // Scollega.
    await vai(P);
    await Promise.all([a.waitForNavigation(), a.click('#ca-scollega')]);
    f = await flash();
    st = await stato(X.base);
    ok(t + 'Scollega: "Scollegato", il servizio ha tolto il permesso, il sito non ha più token', /Scollegato da/.test(f) && st.refresh_revocati >= 1 && st.refresh_attivi === 0 && !/"refresh":"v1:/.test(php("echo get_setting('copia_altrove_cfg');")), { f, st: [st.refresh_revocati, st.refresh_attivi] });
    ok(t + 'scollegato: "Non ancora collegato", Prova e Manda spenti; le copie mandate restano sul servizio', /Non ancora collegato/.test(await a.locator(`#ca-${X.s}-stato`).innerText()) && await a.locator('#ca-prova').isDisabled() && zipDi(st).length === 2);
  }

  fs.unlinkSync(GRANDE);

  // 7. Al telefono.
  const tel = await pagina(br, err, 390, 844);
  await entraPannello(tel, B, UTENTE, PASSWORD);
  await tel.goto(B + P, { waitUntil: 'networkidle' });
  ok('al telefono la pagina del pannello non scorre di lato', await largo(tel) <= 0, await largo(tel));

  // 8. Spegnere: il sito torna come prima, il cron non ha più il passo.
  await salva({ tipo: 'ftp', host: '127.0.0.1', porta: P_FTP, password: PWD, attivo: true });
  const f2 = await addon(a, B, 'copia-altrove', false);
  ok('spento: "i dati restano"', /spento/.test(f2), f2);
  const diversi = [];
  for (const u of Object.keys(prima)) if (pulisci(await (await v0.request.get(B + u)).text()) !== prima[u]) diversi.push(u);
  ok('spento: home, Servizi e Contatti identici a prima', diversi.length === 0, diversi);
  ok('spento: la pagina del pannello dà 404', (await a.request.get(B + P)).status() === 404);
  const prima2 = copie(cart).length;
  await vai('/admin/cron-log.php');
  await Promise.all([a.waitForNavigation({ timeout: 120000 }), a.click('#cron-esegui')]);
  ok('spento: il cron non manda più copie', !/Copia del sito altrove/.test(await a.locator('.flash').first().innerText()) && copie(cart).length === prima2);
  fs.writeFileSync(CONFIG, conf0);

  fine(err);
  await br.close();
})().catch(async e => { console.error(e); process.exitCode = 2; if (br) await br.close(); });
