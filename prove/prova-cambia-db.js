// Cambia database (2.37.0): Impostazioni > Cambia database provato come una persona, nel browser.
// uso: node prova-cambia-db.js <base> <cartella del CMS installato> <utente> <password>
// Il database nuovo dall'ambiente: T_HOST (127.0.0.1), T_PORT, T_DB, T_USER, T_PASS, T_PREF (di partenza il prefisso del sito).
// SCENARI (separati da virgole, di partenza tutti): errori, gia, guasto, interrotta, copia, torna, manuale.
//   guasto     CMS_CAMBIADB_GUASTO: il controllo dopo il config nuovo fallisce; tutto torna com'era, anche nel database nuovo
//   errori     credenziali sbagliate, database che non c'è, porta chiusa, campi cattivi, lo stesso database, troppi tentativi
//   gia        T_DB_GIA: un database con tabelle già col prefisso: la prova si ferma, poi la casella "Sostituisci"
//   interrotta pagina chiusa a metà: si riapre e riprende; poi chiusa più della scadenza: il sito torna normale, si annulla
//   copia      la copia a giri con il sito in «Torno subito», conteggi e impronte uguali, passaggio, pagine identiche al byte,
//              login, una scrittura che va nel database nuovo e non nel vecchio
//   torna      "Torna al database di prima": il config di prima, la scrittura fatta dopo non c'è
//   manuale    config non scrivibile: le righe da incollare, il file cambiato dalla prova, "controlla"
// Il config del sito di prova deve avere CMS_CAMBIADB_PAUSA (una copia lenta) e CMS_CAMBIADB_SCADENZA (pochi secondi): li
// aggiunge la prova stessa se mancano. Senza password del database nei log: la prova lo controlla.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const pnConferme = require('./conferme-pannello');
const [B, CMS, UTENTE, PASSWORD] = process.argv.slice(2);
const T = { host: process.env.T_HOST || '127.0.0.1', port: process.env.T_PORT || '3308', db: process.env.T_DB, user: process.env.T_USER, pass: process.env.T_PASS, pref: process.env.T_PREF || '' };
const SCENARI = (process.env.SCENARI || 'errori,gia,guasto,interrotta,copia,torna,manuale').split(',');
const CLI = path.join(__dirname, 'cambia-db-cli.php');
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const dorme = ms => new Promise(r => setTimeout(r, ms));
const cli = (...a) => JSON.parse(execFileSync('php', [CLI, ...a], { encoding: 'utf8' }).trim().split('\n').pop());
const CONFIG = path.join(CMS, 'includes', 'config.local.php');
const cfg = () => cli('config', CMS);
const DEST = () => [T.host, T.port, T.db, T.user, T.pass];

async function entra(p) {
  await p.goto(B + '/admin/login.php', { waitUntil: 'domcontentloaded' });
  if (!p.url().includes('login.php')) return;
  await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
}
async function apri(p) { await p.goto(B + '/admin/cambia-db.php', { waitUntil: 'domcontentloaded' }); }
async function compila(p, d) {
  for (const [k, v] of Object.entries(d)) await p.fill('#cdb-' + k, String(v));
  await Promise.all([p.waitForNavigation(), p.click('#cdb-modulo button[type=submit]')]);
}
const datiBuoni = (pref) => ({ host: T.host, porta: T.port, nome: T.db, utente: T.user, password: T.pass, prefisso: pref || T.pref || cfg().CMS_PREFISSO });
async function testoErrore(p) { return (await p.locator('.flash-error, .errore-campo').allTextContents()).join(' | '); }
// Aspetta che la pagina del cambio finisca da sola (i giri) e torni senza la barra.
async function aspettaFine(p, max = 240000) {
  const t0 = Date.now();
  while (Date.now() - t0 < max) {
    if (await p.locator('#cdb-lavoro').count() === 0) return true;
    const e = p.locator('[data-nl-errore]:visible');
    if (await e.count()) { const r = p.locator('[data-nl-riprendi]:visible'); if (await r.count()) await r.click(); }
    await dorme(500);
  }
  return false;
}
async function avvia(p, conSostituisci = false) {
  if (conSostituisci) await p.check('input[name=sostituisci]');
  await p.fill('#cdb-password-avvia', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('#pronto button[type=submit]:has-text("Copia e passa")')]);
}
function pagineSito() { return ['/', '/servizi/', '/chi-siamo/', '/blog/', '/contatti/', '/sitemap.xml', '/robots.txt', '/feed/']; }
async function scaricaPagine(req) { const o = {}; for (const u of pagineSito()) { const r = await req.get(B + u); o[u] = r.status() + '\n' + (await r.text()); } return o; }
function configAggiungi(riga) { const t = fs.readFileSync(CONFIG, 'utf8'); if (!t.includes(riga.split(',')[0])) fs.writeFileSync(CONFIG, t + '\n' + riga + '\n'); }
function logPulito() {
  const f = path.join(CMS, 'logs', 'php-errori.log');
  const t = fs.existsSync(f) ? fs.readFileSync(f, 'utf8') : '';
  return { senzaPassword: !t.includes(T.pass), fatali: (t.match(/Fatal|Eccezione non gestita/g) || []).length };
}

(async () => {
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  pnConferme.on(p, d => d.accept());
  const errJs = []; p.on('pageerror', e => errJs.push(e.message));
  configAggiungi("define('CMS_CAMBIADB_PAUSA', 150000);");
  configAggiungi("define('CMS_CAMBIADB_SCADENZA', 12);");
  configAggiungi("define('CMS_CAMBIADB_GIRO', 2);");
  await dorme(2500);
  const prima = cfg();
  await entra(p);
  await apri(p);
  ok('la pagina Cambia database si apre dal menu Impostazioni', await p.locator('#cdb-modulo h3').count() === 1 && await p.locator('nav a[href="cambia-db.php"]').count() >= 1);

  if (SCENARI.includes('errori')) {
    await compila(p, { ...datiBuoni(), password: 'sbagliata-di-sicuro' });
    let t = await testoErrore(p);
    ok('password sbagliata: "Utente o password del database nuovo sbagliati", senza dettagli del server', /Utente o password del database nuovo sbagliati/.test(t) && !/SQLSTATE|Access denied|1045/.test(t), t);
    ok('la password scritta non torna nella pagina', !(await p.content()).includes('sbagliata-di-sicuro') && await p.inputValue('#cdb-password') === '');
    await compila(p, { ...datiBuoni(), nome: 'cdb_non_esiste_mai' });
    t = await testoErrore(p); ok('database inesistente: detto in parole semplici', /non esiste su quel server/.test(t) && !/SQLSTATE|1049/.test(t), t);
    await compila(p, { ...datiBuoni(), porta: '9339' });
    t = await testoErrore(p); ok('porta chiusa: "non risponde sulla porta"', /non risponde sulla porta 9339/.test(t), t);
    await compila(p, { ...datiBuoni(), host: 'http://127.0.0.1/x', porta: 'abc', nome: 'x;DROP', prefisso: "a'b" });
    const campi = await p.locator('[aria-invalid=true]').count();
    t = await testoErrore(p); ok('host con schema, porta non numerica, nome e prefisso cattivi: quattro campi segnati in rosso con il riepilogo', campi === 4 && /4 campi da sistemare/.test(t), { campi, t });
    const c = cfg(); const [h, pt] = c.DB_HOST.split(';port=');
    await compila(p, { host: h, porta: pt || '3306', nome: c.DB_NAME, utente: c.DB_USER, password: process.env.SRC_PASS || '', prefisso: c.CMS_PREFISSO });
    t = await testoErrore(p); ok('lo stesso database con lo stesso prefisso: rifiutato', /database che il sito usa adesso/.test(t), t);
    if (process.env.ALT_HOST) {
      // Lo stesso server MySQL raggiunto da un altro indirizzo (l'IP del contenitore, porta interna): lo riconosce dalla risposta del server.
      await compila(p, { host: process.env.ALT_HOST, porta: process.env.ALT_PORT || '3306', nome: c.DB_NAME, utente: c.DB_USER, password: process.env.SRC_PASS || '', prefisso: c.CMS_PREFISSO });
      t = await testoErrore(p); ok('lo stesso database raggiunto da un altro indirizzo (' + process.env.ALT_HOST + '): riconosciuto e rifiutato', /database che il sito usa adesso/.test(t) && await p.locator('#pronto').count() === 0, t);
    }
    if (process.env.T_POCO_USER) {
      await compila(p, { ...datiBuoni(), utente: process.env.T_POCO_USER, password: process.env.T_POCO_PASS, nome: process.env.T_POCO_DB });
      t = await testoErrore(p); ok('utente senza tutti i permessi: detto quale manca', /non ha il permesso di/.test(t), t);
    }
    // troppi tentativi: la sessione ne ha già usati alcuni; si arriva oltre dieci
    let bloccato = false;
    for (let i = 0; i < 12 && !bloccato; i++) { await compila(p, { ...datiBuoni(), porta: '9339' }); bloccato = /troppe volte/.test(await testoErrore(p)); }
    ok('dopo dieci prove in dieci minuti: "troppe volte", niente più connessioni', bloccato);
    // Una sessione nuova per il resto della prova (il limite è per sessione).
    await ctx.clearCookies(); await entra(p); await apri(p);
  }

  if (SCENARI.includes('gia') && process.env.T_DB_GIA) {
    const tdb = T.db; T.db = process.env.T_DB_GIA;
    await compila(p, datiBuoni());
    ok('database con tabelle già col prefisso: la prova le conta e chiede la casella', await p.locator('#pronto').count() === 1 && /ci sono già \d+ tabelle/.test(await p.locator('#pronto').innerText()) && await p.locator('input[name=sostituisci][required]').count() === 1);
    // Senza la casella (tolto il "required", come un attacco): il server si ferma.
    await p.evaluate(() => document.querySelector('input[name=sostituisci]').removeAttribute('required'));
    await p.fill('#cdb-password-avvia', PASSWORD);
    await Promise.all([p.waitForNavigation(), p.click('#pronto button[type=submit]:has-text("Copia e passa")')]);
    const t = await testoErrore(p);
    ok('senza "Sostituisci" la copia non parte e il sito resta sul suo database', /Sostituisci le tabelle/.test(t) && cfg().DB_NAME === prima.DB_NAME, t);
    // Password dell'amministratore sbagliata
    await compila(p, datiBuoni());
    await p.check('input[name=sostituisci]'); await p.fill('#cdb-password-avvia', 'non-e-la-mia');
    await Promise.all([p.waitForNavigation(), p.click('#pronto button[type=submit]:has-text("Copia e passa")')]);
    ok('con la password dell\'amministratore sbagliata non parte', /password non è giusta/.test(await testoErrore(p)) && !fs.existsSync(path.join(CMS, 'aggiornamenti/cambia-db/lavoro.json')));
    T.db = tdb;
    await p.click('button:has-text("Cambia i dati del database")').catch(() => {}); await p.waitForLoadState('domcontentloaded');
  }

  if (SCENARI.includes('guasto') && process.env.T_DB_GIA) {
    // Il controllo dopo il config nuovo non va (CMS_CAMBIADB_GUASTO): config di prima rimesso, e nel database nuovo le tabelle
    // che c'erano (sostituite con "Sostituisci") tornano al loro nome con le loro righe; niente tabelle di lavoro.
    const tdb = T.db; T.db = process.env.T_DB_GIA;
    const primaDest = cli('sql-db', ...DEST(), "SHOW TABLES");
    const conf0 = fs.readFileSync(CONFIG, 'utf8');
    fs.writeFileSync(CONFIG, conf0 + "\ndefine('CMS_CAMBIADB_GUASTO', 'controllo');\n"); await dorme(2500);
    await apri(p); await compila(p, datiBuoni());
    await avvia(p, true);
    await aspettaFine(p);
    const confDopo = fs.readFileSync(CONFIG, 'utf8');
    ok('guasto nel controllo dopo il passaggio: il pannello lo dice e il config è quello di prima', /controllo non è andato/.test(await p.locator('#cdb-errore').innerText().catch(() => '')) && confDopo.includes("'DB_NAME', '" + prima.DB_NAME + "'"), await p.locator('body').innerText().then(t => t.slice(0, 200)));
    const dopoDest = cli('sql-db', ...DEST(), "SHOW TABLES");
    ok('guasto: nel database nuovo le tabelle che c\'erano sono tornate al loro nome, nessuna tabella di lavoro né copiata', JSON.stringify(dopoDest) === JSON.stringify(primaDest), { prima: primaDest, dopo: dopoDest });
    const r = await (await br.newContext()).request.get(B + '/');
    ok('guasto: il sito risponde sul database di prima', r.status() === 200 && cfg().DB_NAME === prima.DB_NAME, r.status());
    fs.writeFileSync(CONFIG, fs.readFileSync(CONFIG, 'utf8').replace("\ndefine('CMS_CAMBIADB_GUASTO', 'controllo');\n", '')); await dorme(2500);
    T.db = tdb;
  }

  if (SCENARI.includes('interrotta')) {
    await compila(p, datiBuoni());
    ok('prova riuscita: versione del server e riepilogo', await p.locator('#pronto').count() === 1 && /(MySQL|MariaDB) \d/.test(await p.locator('#pronto').innerText()));
    await avvia(p, await p.locator('input[name=sostituisci]').count() > 0);
    ok('copia partita: barra di avanzamento', await p.locator('#cdb-lavoro [data-nl-barra]').count() === 1);
    await dorme(3000);
    const anon = await br.newContext(); const r = await anon.request.get(B + '/');
    ok('durante la copia il sito risponde 503 «Torno subito» con Retry-After', r.status() === 503 && /Torno subito/.test(await r.text()) && !!r.headers()['retry-after'], r.status());
    const api = await anon.request.get(B + '/api/versione.php', { headers: { Accept: 'application/json' } });
    ok('durante la copia anche le API rispondono 503', api.status() === 503, api.status());
    await anon.close();
    // il pannello: le altre pagine rimandano qui
    const p2 = await ctx.newPage(); await p2.goto(B + '/admin/articles.php', { waitUntil: 'domcontentloaded' });
    ok('durante la copia le altre pagine del pannello portano a Cambia database', p2.url().includes('cambia-db.php'), p2.url());
    await p.close(); // la pagina che faceva i giri si chiude a metà
    await dorme(1500);
    await p2.goto(B + '/admin/cambia-db.php', { waitUntil: 'domcontentloaded' });
    const pct1 = Number(await p2.locator('[data-nl-barra]').getAttribute('aria-valuenow'));
    await dorme(4000);
    const pct2 = Number(await p2.locator('[data-nl-barra]').getAttribute('aria-valuenow').catch(() => 100));
    ok('riaperta, la copia riprende da dove era (la barra va avanti)', pct2 > pct1 || await p2.locator('#cdb-lavoro').count() === 0, { pct1, pct2 });
    // chiusa di nuovo, più della scadenza: il sito torna normale
    await p2.goto('about:blank'); await dorme(16000);
    const anon2 = await br.newContext(); const r2 = await anon2.request.get(B + '/');
    ok('pagina chiusa oltre la scadenza: il sito torna normale (200) sul database di prima', r2.status() === 200 && cfg().DB_NAME === prima.DB_NAME, r2.status()); await anon2.close();
    await p2.goto(B + '/admin/categories.php', { waitUntil: 'domcontentloaded' });
    ok('e il pannello torna libero', p2.url().includes('categories.php'), p2.url());
    await p2.goto(B + '/admin/cambia-db.php', { waitUntil: 'domcontentloaded' });
    ok('la pagina dice che la copia si è fermata e offre Ricomincia e Annulla', await p2.locator('#cdb-fermo').count() === 1);
    pnConferme.on(p2, d => d.accept());
    await Promise.all([p2.waitForNavigation(), p2.click('#cdb-fermo button:has-text("Annulla il cambio")')]);
    const rest = cli('sql-db', ...DEST(), "SHOW TABLES LIKE 'rip%'");
    ok('annullato: nel database nuovo non restano tabelle di lavoro, il sito è sul suo database', rest.length === 0 && cfg().DB_NAME === prima.DB_NAME && /annullato/i.test(await p2.locator('.flash-success').innerText()), rest);
    globalThis.P = p2;
  }
  const q = globalThis.P || p;

  if (SCENARI.includes('copia')) {
    const req = (await br.newContext()).request;
    const pagPrima = await scaricaPagine(req);
    const contePrima = cli('conte', CMS); const imprPrima = cli('impronte', CMS);
    await apri(q); await compila(q, datiBuoni());
    await avvia(q, await q.locator('input[name=sostituisci]').count() > 0);
    await dorme(2500);
    const r = await req.get(B + '/contatti/');
    ok('copia: il sito è in manutenzione mentre copia', r.status() === 503, r.status());
    const fine = await aspettaFine(q);
    ok('copia finita da sola, a giri, con la pagina aperta', fine);
    ok('dopo il passaggio la pagina dice "Il sito usa il database nuovo" e che il vecchio resta intatto', await q.locator('#cdb-fatto').count() === 1 && /resta intatto/.test(await q.locator('#cdb-fatto').innerText()), await q.locator('body').innerText().catch(() => '').then(s => s.slice(0, 300)));
    const c = cfg();
    ok('il config ora indica il database nuovo (host, nome, utente, prefisso)', c.DB_NAME === T.db && c.DB_USER === T.user && c.DB_HOST.startsWith(T.host) && c.CMS_PREFISSO === (T.pref || prima.CMS_PREFISSO), c);
    const testoConf = fs.readFileSync(CONFIG, 'utf8');
    ok('il resto del config è rimasto uguale (segreto, fuso, righe delle prove)', /CMS_SEGRETO/.test(testoConf) && /CMS_FUSO/.test(testoConf) && /CMS_CAMBIADB_PAUSA/.test(testoConf));
    const copie = fs.readdirSync(path.join(CMS, 'includes')).filter(f => /^config\.local\.prima-/.test(f));
    ok('c\'è la copia del config di prima in includes/, che comincia con un\'uscita immediata', copie.length >= 1 && fs.readFileSync(path.join(CMS, 'includes', copie[copie.length - 1]), 'utf8').startsWith('<?php http_response_code(404); exit;'), copie);
    const rc = await req.get(B + '/includes/' + copie[copie.length - 1]);
    ok('la copia del config non si apre dal web', [403, 404].includes(rc.status()) && !(await rc.text()).includes('DB_PASS'), rc.status());
    // Il confronto: il database di prima com'è adesso (dopo il passaggio nessuno ci scrive più) e quello nuovo com'era alla fine
    // della copia (impronte prese dal giro stesso non si possono: si confrontano adesso, escludendo il registro di sicurezza e le
    // sessioni, dove il passaggio e il nuovo accesso aggiungono righe solo nel database nuovo).
    const SRC = [prima.DB_HOST.split(';port=')[0], prima.DB_HOST.split(';port=')[1] || '3306', prima.DB_NAME, prima.DB_USER, process.env.SRC_PASS || ''];
    const VIVE = ['sicurezza_log', 'sicurezza_sessioni'];
    const conteSrc = cli('conte-db', ...SRC, prima.CMS_PREFISSO);
    const conteDopo = cli('conte-db', ...DEST(), c.CMS_PREFISSO);
    const conteDiv = Object.keys(conteSrc).filter(k => VIVE.includes(k) ? conteDopo[k] < conteSrc[k] : conteSrc[k] !== conteDopo[k]);
    ok('conteggi uguali tabella per tabella (' + Object.keys(conteSrc).length + ' tabelle, ' + Object.values(conteSrc).reduce((a, b) => a + b, 0) + ' righe)', conteDiv.length === 0 && Object.keys(conteSrc).length === Object.keys(conteDopo).length, conteDiv);
    const imprSrc = cli('impronte-db', ...SRC, prima.CMS_PREFISSO);
    const imprDopo = cli('impronte-db', ...DEST(), c.CMS_PREFISSO);
    const diverse = Object.keys(imprSrc).filter(k => !VIVE.includes(k) && imprSrc[k] !== imprDopo[k]);
    // Nelle impostazioni l'unica differenza ammessa: le impronte degli schemi che contengono il nome del database
    // (sicurezza_schema_impronta = md5 di tabelle, colonne e DB_NAME), ricalcolate dal primo accesso al database nuovo.
    let dettaglio = diverse.filter(k => k !== 'settings');
    if (diverse.includes('settings')) {
      const a = Object.fromEntries(cli('sql-db', ...SRC, 'SELECT setting_key, setting_value FROM `' + prima.CMS_PREFISSO + 'settings`'));
      const b2 = Object.fromEntries(cli('sql-db', ...DEST(), 'SELECT setting_key, setting_value FROM `' + c.CMS_PREFISSO + 'settings`'));
      dettaglio = dettaglio.concat([...new Set([...Object.keys(a), ...Object.keys(b2)])].filter(k => a[k] !== b2[k] && !/_impronta$/.test(k)));
    }
    ok('contenuto identico riga per riga (anche colonne generate, date, testi con apici e byte; solo le impronte degli schemi col nome del database cambiano)', dettaglio.length === 0, dettaglio);
    ok('il database di prima ha ancora tutte le sue tabelle e righe (non è stato toccato)', JSON.stringify(Object.keys(conteSrc)) === JSON.stringify(Object.keys(contePrima)) && Object.keys(contePrima).every(k => VIVE.includes(k) || conteSrc[k] === contePrima[k]), Object.keys(contePrima).filter(k => conteSrc[k] !== contePrima[k]));
    await dorme(2500);
    const pagDopo = await scaricaPagine(req);
    const cambiate = Object.keys(pagPrima).filter(u => pagPrima[u] !== pagDopo[u]);
    ok('le pagine del sito sono identiche al byte (' + pagineSito().length + ' indirizzi)', cambiate.length === 0, cambiate);
    // login da capo sul database nuovo
    const ctx2 = await br.newContext(); const p3 = await ctx2.newPage(); await entra(p3);
    ok('si entra nel pannello con gli utenti del database nuovo', p3.url().includes('/admin/') && !p3.url().includes('login.php'), p3.url());
    // una scrittura: una categoria nuova
    await p3.goto(B + '/admin/categories.php', { waitUntil: 'domcontentloaded' });
    const nomeCat = 'Dopo il cambio ' + Date.now();
    await p3.fill('input[name=name]', nomeCat);
    await Promise.all([p3.waitForNavigation(), p3.click('button.btn-primary:has-text("Aggiungi")')]);
    const nuovi = cli('sql-db', ...DEST(), "SELECT COUNT(*) FROM `" + c.CMS_PREFISSO + "categories` WHERE name LIKE 'Dopo il cambio %'")[0][0];
    const vecchi = cli('sql-db', prima.DB_HOST.split(';port=')[0], prima.DB_HOST.split(';port=')[1] || '3306', prima.DB_NAME, prima.DB_USER, process.env.SRC_PASS || '', "SELECT COUNT(*) FROM `" + prima.CMS_PREFISSO + "categories` WHERE name LIKE 'Dopo il cambio %'")[0][0];
    ok('una scrittura nuova finisce nel database nuovo e non nel vecchio', Number(nuovi) >= 1 && Number(vecchi) === 0, { nuovi, vecchi });
    globalThis.CAT = nomeCat;
    const reg = cli('sql-db', ...DEST(), "SELECT dettagli FROM `" + c.CMS_PREFISSO + "sicurezza_log` WHERE evento = 'cambia_db' ORDER BY id");
    ok('il registro di sicurezza ha la prova, l\'avvio e il passaggio, senza password', reg.some(x => /passaggio fatto/.test(x[0])) && reg.some(x => /copia avviata/.test(x[0])) && !JSON.stringify(reg).includes(T.pass), reg.length);
    await ctx2.close();
  }

  if (SCENARI.includes('torna')) {
    await apri(q);
    await q.locator('#torna summary').click();
    await q.check('#torna input[name=conferma]'); await q.fill('#cdb-password-torna', PASSWORD);
    await Promise.all([q.waitForNavigation(), q.click('#torna button:has-text("Torna al database di prima")')]);
    await dorme(2500);
    const c = cfg();
    ok('"Torna al database di prima": il config indica di nuovo il database di prima', c.DB_NAME === prima.DB_NAME && c.CMS_PREFISSO === prima.CMS_PREFISSO, c);
    ok('la pagina lo dice', /di nuovo il database di prima/.test(await q.locator('body').innerText()));
    const req = (await br.newContext()).request; const r = await req.get(B + '/');
    ok('il sito risponde sul database di prima', r.status() === 200);
    if (globalThis.CAT) {
      const n0 = cli('sql-db', prima.DB_HOST.split(';port=')[0], prima.DB_HOST.split(';port=')[1] || '3306', prima.DB_NAME, prima.DB_USER, process.env.SRC_PASS || '', "SELECT COUNT(*) FROM `" + prima.CMS_PREFISSO + "categories` WHERE name LIKE 'Dopo il cambio %'")[0][0];
      ok('la scrittura fatta dopo il passaggio non c\'è nel database di prima (come avvisa la pagina)', Number(n0) === 0);
    }
  }

  if (SCENARI.includes('manuale')) {
    // Il database nuovo va svuotato delle tabelle del cambio di prima: si usa "Sostituisci".
    fs.chmodSync(CONFIG, 0o444);
    await apri(q); await compila(q, datiBuoni());
    await avvia(q, await q.locator('input[name=sostituisci]').count() > 0);
    await aspettaFine(q);
    ok('config non scrivibile: la pagina mostra le righe da incollare con i passi', await q.locator('#cdb-manuale').count() === 1 && /Gestione file/.test(await q.locator('#cdb-manuale').innerText()));
    const righe = await q.locator('#cdb-righe').innerText();
    ok('le righe sono cinque define con il database nuovo', (righe.match(/define\(/g) || []).length === 5 && righe.includes("'DB_NAME', '" + T.db + "'"), righe.replace(T.pass, '***'));
    const anon = await br.newContext(); const r = await anon.request.get(B + '/');
    ok('finché non si controlla, il sito resta in manutenzione', r.status() === 503, r.status());
    // "Ho cambiato il file" senza averlo cambiato
    await Promise.all([q.waitForNavigation(), q.click('button:has-text("Ho cambiato il file, controlla")')]);
    ok('controllo senza aver cambiato il file: lo dice e non cambia niente', /indica ancora il database di prima/.test(await testoErrore(q)));
    // la persona cambia il file come dicono i passi
    fs.chmodSync(CONFIG, 0o644);
    let t = fs.readFileSync(CONFIG, 'utf8');
    for (const riga of righe.split('\n')) {
      const nome = riga.match(/define\('([A-Z_]+)'/)[1];
      const re = new RegExp("^define\\('" + nome + "'.*$", 'm');
      t = re.test(t) ? t.replace(re, () => riga) : t.replace(/^define\('DB_PASS'.*$/m, m => m + '\n' + riga);
    }
    fs.writeFileSync(CONFIG, t); await dorme(2500);
    await Promise.all([q.waitForNavigation(), q.click('button:has-text("Ho cambiato il file, controlla")')]);
    ok('dopo il file cambiato a mano: "Il sito usa il database nuovo"', await q.locator('#cdb-fatto').count() === 1, await testoErrore(q));
    const r2 = await anon.request.get(B + '/'); ok('il sito risponde dal database nuovo', r2.status() === 200 && cfg().DB_NAME === T.db);
    await anon.close();
  }

  const lp = logPulito();
  ok('la password del database nuovo non compare nel log degli errori', lp.senzaPassword);
  ok('nessun errore fatale nel log', lp.fatali === 0, lp.fatali);
  ok('nessun errore JavaScript', errJs.length === 0, errJs);
  console.log(`\n${n - ko} su ${n} OK`);
  await br.close();
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
