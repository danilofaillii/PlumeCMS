// Addon Copia del sito altrove (copia-altrove, 2.0.0): tutto il percorso dell'amministratore nel browser.
// Su un sito nuovo installato con il kit Attività (prove/prova-kit.js), con il server di posta finto acceso e i tre
// server finti di prove/server-copia-finti.py: FTP sulla porta 8205, FTPS sulla 8206, WebDAV sulla 8207 (cartella /dav),
// utente "copie", password "Prova-Copie-2026", che scrivono in <cartelle>/dest-ftp, dest-ftps e dest-dav.
// Accende l'addon, salva una destinazione FTP (la password non si rivede), prova la connessione (prima rifiutata perché
// locale, poi ammessa con CMS_COPIA_ALTROVE_LOCALE nel config), manda le copie (restano le ultime 2, gli altri file
// non si toccano), il giro della notte da Log cron > Esegui adesso, l'email se non riesce, FTPS, WebDAV, SFTP senza ssh2.
// Telefono 390x844 senza scorrimento di lato. Alla fine spegne l'addon: il sito torna identico a prima.
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
const php = codice => execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='127.0.0.1'; require '${DIR}/includes/config.php'; ${codice}`], { encoding: 'utf8' });

let br;
(async () => {
  br = await chromium.launch();
  const err = [];
  const a = await pagina(br, err);
  const vai = u => a.goto(B + u, { waitUntil: 'networkidle' });
  const flash = async () => (await a.locator('.flash').first().innerText().catch(() => '')).trim();
  // il config senza l'eccezione delle prove (una prova interrotta prima può averla lasciata)
  const conf0 = fs.readFileSync(CONFIG, 'utf8').replace(/\ndefine\('CMS_COPIA_ALTROVE_LOCALE'[^\n]*\n/g, '\n');
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

  // 2. Una destinazione FTP. La password non si rivede.
  const salva = async (campi) => {
    await vai(P);
    await a.selectOption('#ca-tipo', campi.tipo);
    for (const [k, v] of Object.entries(campi)) if (k !== 'tipo' && k !== 'attivo') await a.fill('#ca-' + k, String(v));
    if ('attivo' in campi) { if (campi.attivo) await a.check('#ca-attivo'); else await a.uncheck('#ca-attivo'); }
    await Promise.all([a.waitForNavigation(), a.click('#ca-form button[type=submit]')]);
    return flash();
  };
  let f = await salva({ tipo: 'ftp', host: '127.0.0.1', porta: 8205, utente: 'copie', password: PWD, cartella: 'copie-del-sito', tenere: 2 });
  ok('salva FTP: "Destinazione salvata"', /Destinazione salvata/.test(f), f);
  ok('la password non si rivede: il campo è vuoto, "Salvata"', await a.inputValue('#ca-password') === '' && /Salvata/.test(await a.getAttribute('#ca-password', 'placeholder')) && !(await a.content()).includes(PWD));
  const salvata = php("echo get_setting('copia_altrove_cfg');");
  ok('nel database la password è cifrata, mai in chiaro', !salvata.includes(PWD) && /"password":"v1:/.test(salvata));
  f = await salva({ tipo: 'ftp', host: 'ftp://pippo', porta: 21 });
  ok('un server scritto male si rifiuta, e si dice come scriverlo', /senza ftp:\/\//.test(f), f);
  f = await salva({ tipo: 'ftp', host: '127.0.0.1', porta: 8205, cartella: '../fuori' });
  ok('una cartella con ".." si rifiuta', /niente "\.\."/.test(f), f);
  await salva({ tipo: 'ftp', host: '127.0.0.1', porta: 8205, cartella: 'copie-del-sito' });

  // 3. Prova la connessione: un indirizzo locale si rifiuta (SSRF), poi l'eccezione delle prove nel config.
  const prova = async () => { await vai(P); await Promise.all([a.waitForNavigation(), a.click('#ca-prova')]); return flash(); };
  f = await prova();
  ok('Prova la connessione verso 127.0.0.1: rifiutata, "rete interna"', /rete interna \(127\.0\.0\.1\)/.test(f), f);
  ok('il registro lo scrive: "Prova della connessione", "Non riuscito"', /Prova della connessione/.test(await a.locator('#ca-registro tbody tr').first().innerText()) && /Non riuscito/.test(await a.locator('#ca-registro tbody tr').first().innerText()));
  fs.writeFileSync(CONFIG, conf0.replace(/\s*$/, '') + "\ndefine('CMS_COPIA_ALTROVE_LOCALE', '127.0.0.1:8205,127.0.0.1:8206,127.0.0.1:8207');\n");
  await dorme(2500); // opcache rilegge i file cambiati al massimo ogni due secondi
  f = await prova();
  ok('con CMS_COPIA_ALTROVE_LOCALE: "Collegamento riuscito"', /Collegamento riuscito/.test(f), f);
  ok('la cartella è nata sulla destinazione e il file di prova non è rimasto', fs.existsSync(path.join(DEST, 'dest-ftp', 'copie-del-sito')) && fs.readdirSync(path.join(DEST, 'dest-ftp', 'copie-del-sito')).length === 0);
  await salva({ tipo: 'ftp', host: '127.0.0.1', porta: 8205, password: 'sbagliata-davvero' });
  f = await prova();
  ok('password sbagliata: "Accesso rifiutato"', /Accesso rifiutato/.test(f), f);
  await salva({ tipo: 'ftp', host: '127.0.0.1', porta: 8205, password: PWD });

  // 4. Manda una copia adesso, tre volte: restano le ultime due; gli altri file non si toccano.
  const cart = path.join(DEST, 'dest-ftp', 'copie-del-sito');
  fs.writeFileSync(path.join(cart, 'mio-file.txt'), 'non toccare');
  fs.writeFileSync(path.join(cart, 'copia-20200101-000000-manuale.zip'), 'non toccare');
  const manda = async () => { await vai(P); await Promise.all([a.waitForNavigation({ timeout: 120000 }), a.click('#ca-manda')]); return flash(); };
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
  await salva({ tipo: 'ftp', host: '127.0.0.1', porta: 8205, attivo: true });
  await dorme(1100);
  await vai('/admin/cron-log.php');
  await Promise.all([a.waitForNavigation({ timeout: 120000 }), a.click('#cron-esegui')]);
  ok('Log cron > Esegui adesso: il passo "Copia del sito altrove" ha mandato la copia', /Copia del sito altrove: Copia mandata/.test(await a.locator('body').innerText()));
  await vai(P);
  ok('nel registro c\'è "Copia della notte", riuscita', /Copia della notte[\s\S]*Riuscito/.test(await a.locator('#ca-registro tbody tr').first().innerText()));
  await salva({ tipo: 'ftp', host: '127.0.0.1', porta: 8205, password: 'sbagliata-davvero' });
  const da = posta(POSTA).length;
  await vai('/admin/cron-log.php');
  await Promise.all([a.waitForNavigation({ timeout: 120000 }), a.click('#cron-esegui')]);
  ok('la notte non riesce: il log del cron lo dice', /ERRORE Copia del sito altrove/.test(await a.locator('body').innerText()));
  const m = await aspettaEmail(POSTA, 'avvisi-sito@esempio.it', da, x => /non è partita/.test(x.oggetto));
  ok('e arriva l\'email "La copia del sito non è partita" con il motivo', !!m && /Accesso rifiutato/.test(m.html));
  await salva({ tipo: 'ftp', host: '127.0.0.1', porta: 8205, password: PWD, attivo: false });

  // 6. FTPS e WebDAV.
  await salva({ tipo: 'ftps', host: '127.0.0.1', porta: 8206, utente: 'copie', password: PWD, cartella: 'cifrate' });
  f = await prova();
  ok('FTPS: collegamento riuscito', /Collegamento riuscito/.test(f), f);
  f = await manda();
  ok('FTPS: copia mandata', /Copia mandata/.test(f) && copie(path.join(DEST, 'dest-ftps', 'cifrate')).length === 1, f);
  await salva({ tipo: 'webdav', url: 'http://127.0.0.1:8207/dav/', utente: 'copie', password: PWD, cartella: 'sito/notti' });
  f = await prova();
  ok('WebDAV: collegamento riuscito', /Collegamento riuscito/.test(f), f);
  f = await manda();
  ok('WebDAV: copia mandata, nelle cartelle create (sito/notti)', /Copia mandata/.test(f) && copie(path.join(DEST, 'dest-dav', 'sito', 'notti')).length === 1, f);
  await vai(P);
  ok('con WebDAV si vedono solo i campi suoi (server e porta nascosti)', await a.locator('#ca-url').isVisible() && !(await a.locator('#ca-host').isVisible()));
  await salva({ tipo: 'webdav', url: 'http://dav.esempio.it/dav/', password: PWD });
  f = await prova();
  ok('WebDAV su http (non locale): rifiutato, "serve un indirizzo https"', /serve un indirizzo https/.test(f), f);
  await salva({ tipo: 'webdav', url: 'http://127.0.0.1:8207/dav/', password: 'sbagliata-davvero' });
  f = await prova();
  ok('WebDAV con la password sbagliata: "Accesso rifiutato"', /Accesso rifiutato/.test(f), f);
  await salva({ tipo: 'sftp', host: '127.0.0.1', porta: 22 });
  f = await prova();
  ok('SFTP senza ssh2: "manca l\'estensione ssh2", detto chiaro', /manca l'estensione ssh2/.test(f), f);

  // 7. Al telefono.
  const tel = await pagina(br, err, 390, 844);
  await entraPannello(tel, B, UTENTE, PASSWORD);
  await tel.goto(B + P, { waitUntil: 'networkidle' });
  ok('al telefono la pagina del pannello non scorre di lato', await largo(tel) <= 0, await largo(tel));

  // 8. Spegnere: il sito torna come prima, il cron non ha più il passo.
  await salva({ tipo: 'ftp', host: '127.0.0.1', porta: 8205, password: PWD, attivo: true });
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
