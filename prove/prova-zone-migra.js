// 2.36.0: il registro delle pagine per zona passa dal JSON della configurazione SEO (fino alla 2.35.0) alla tabella <prefisso>zone,
// una volta sola, senza perdere niente. Due fasi, prima e dopo l'aggiornamento dal pannello (prova-aggiornamento.js):
//   prima  su un sito 2.35.0 col kit Attività: una sede, sei pagine per zona di due servizi (una zona con l'accento, una con
//          un'emoji), cinque pubblicate e una in bozza; salva HTML e JSON-LD delle pagine per zona, della home e di Servizi, la
//          sitemap e llms.txt nel file di stato. Con ROMPI=1 crea anche una tabella <prefisso>zone sbagliata (senza colonne):
//          la migrazione dopo l'aggiornamento deve fallire senza perdere niente.
//   dopo   sul sito aggiornato alla 2.36.0: se la tabella sbagliata c'è, la prima visita non migra (il JSON resta intatto, le
//          pagine si aprono, il pannello Zone dice cosa succede), poi si toglie la tabella sbagliata; la migrazione si fa alla
//          prima visita, una volta sola (migrato = 1, elenco del JSON vuoto, sei righe); HTML e JSON-LD identici al byte a parte
//          il numero di versione dei fogli.
//   pannello  (dopo le foto del sito) il pannello Zone mostra le sei pagine; il registro si scrive (Togli dal registro).
// uso: node prove/prova-zone-migra.js <prima|dopo|pannello> <base> <cartella CMS> <utente> <password> <file di stato>
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const [FASE, B, DIR, UTENTE, PASSWORD, STATO] = process.argv.slice(2);
if (!['prima', 'dopo', 'pannello'].includes(FASE) || !B || !DIR || !UTENTE || !PASSWORD || !STATO) { console.error('uso: node prove/prova-zone-migra.js <prima|dopo> <base> <cartella CMS> <utente> <password> <file di stato>'); process.exit(2); }
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? '  ' + JSON.stringify(extra).slice(0, 300) : '')); };
const cliDi = (file) => (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, file), DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }).trim().split('\n').pop());
const cli = cliDi('zone-cli.php'), sedi = cliDi('sedi-cli.php');
const PREF = () => cli('sql', "SELECT SUBSTRING_INDEX(TABLE_NAME, 'settings', 1) AS p FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE '%settings' LIMIT 1")[0].p;
const ZONE = [['Idraulico', 'Bologna'], ['Idraulico', 'Casalecchio di Reno'], ['Idraulico', 'Forlì'], ['Idraulico', 'Provincia di Bologna'], ['Elettricista', 'Imola'], ['Elettricista', 'Lugo &#127829;']];
// Il numero di versione nei fogli e negli script (?v=...) cambia con l'aggiornamento: si toglie prima del confronto.
const norm = (h) => h.replace(/([?&]v=)[^"'&\s)]+/g, '$1X').replace(/2\.3[56]\.0/g, 'V');
const leggi = async (ctx, u) => { const r = await ctx.request.get(B + u); return { s: r.status(), t: await r.text() }; };
const ldDi = (h) => { const m = h.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/); return m ? m[1] : ''; };

(async () => {
  const br = await chromium.launch();
  const vis = await br.newContext();
  if (FASE === 'prima') {
    cli('azzera'); sedi('azzera');
    const sd = sedi('salva', JSON.stringify({ nome: 'Sede di Bologna', tipo: 'idraulico', citta: 'Bologna', provincia: 'BO', via: 'Via Rizzoli 1', cap: '40125', telefono: '+39 051 123456', email: 'bologna@esempio.it', lat: '44.4949', lng: '11.3426', zona: 'Bologna\nCasalecchio di Reno', orari: { lun: '9-13' } }));
    ok('2.35.0: una sede', sd && sd.sede && sd.sede.id === 'sede-di-bologna', sd && sd.errori);
    const servizi = {};
    for (const [s, z] of ZONE) (servizi[s] = servizi[s] || []).push(z.replace('&#127829;', '\u{1F355}'));
    for (const [s, zz] of Object.entries(servizi)) { const r = cli('crea', JSON.stringify({ servizio: s, zone: zz.join('\n'), sede: 'sede-di-bologna' })); ok('2.35.0: bozze di ' + s, r.create && r.create.length === zz.length, r.errori); }
    const reg = cli('elenco');
    ok('2.35.0: sei pagine per zona nel registro del JSON (nessuna tabella zone)', reg.length === 6 && cli('sql', "SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" + PREF() + "zone'")[0].n == 0, reg.length);
    for (const z of reg) if (z.zona !== 'Provincia di Bologna') { cli('scrivi', z.pagina); cli('pubblica', z.pagina); }
    const stato = { pagine: {}, slug: reg.map(z => z.slug), elenco: reg.map(z => [z.pagina, z.servizio, z.zona, z.sede, z.servizio_pagina, z.creata]) };
    for (const u of ['/', '/servizi/', '/sitemap.xml', '/llms.txt', ...reg.map(z => '/' + z.slug + '/')]) stato.pagine[u] = await leggi(vis, u);
    const pubb = reg.filter(z => z.zona !== 'Provincia di Bologna');
    ok('2.35.0: cinque pagine per zona pubblicate (200) con il Service nel JSON-LD, la bozza 404', pubb.every(z => stato.pagine['/' + z.slug + '/'].s === 200 && /"Service"/.test(stato.pagine['/' + z.slug + '/'].t)) && stato.pagine['/' + reg.find(z => z.zona === 'Provincia di Bologna').slug + '/'].s === 404);
    ok('2.35.0: il widget Altre zone della pagina di Bologna elenca Casalecchio di Reno e Forlì', /ew-zone-link[^>]*>Casalecchio di Reno</.test(stato.pagine['/idraulico-a-bologna/'].t) && /ew-zone-link[^>]*>Forlì</.test(stato.pagine['/idraulico-a-bologna/'].t));
    if (process.env.ROMPI === '1') { cli('sql', 'CREATE TABLE {p}zone (x INT) ENGINE=InnoDB'); stato.rotta = 1; ok('ROMPI: una tabella zone sbagliata (una colonna sola) pronta a far fallire la migrazione', true); }
    fs.writeFileSync(STATO, JSON.stringify(stato));
  } else if (FASE === 'dopo') {
    const stato = JSON.parse(fs.readFileSync(STATO, 'utf8'));
    const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
    const p = await ctx.newPage(); pnConferme.on(p, d => d.accept());
    await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    ok('login', !p.url().includes('login.php'));
    if (stato.rotta) {
      const r = await leggi(vis, '/idraulico-a-bologna/');
      const c = cli('cfg');
      ok('migrazione fallita (tabella sbagliata): la pagina per zona si apre lo stesso (200), il JSON resta intatto con le sei voci, migrato 0', r.s === 200 && c.migrato === 0 && c.elenco === 6, c);
      await p.goto(B + '/admin/zone.php', { waitUntil: 'networkidle' });
      const fe = await p.locator('.flash-error').innerText().catch(() => '');
      ok('migrazione fallita: il pannello Zone dice che il registro di prima non è passato e che niente è perso', /non è riuscito a passare nella tabella nuova/.test(fe) && /niente è perso/.test(fe) && cli('cfg').elenco === 6, fe.slice(0, 160));
      cli('sql', 'DROP TABLE {p}zone');
    }
    ok('prima della prima visita dopo l\'aggiornamento: il registro è ancora nel JSON (sei voci), migrato 0', (() => { const c = cli('cfg'); return c.migrato === 0 && c.elenco === 6; })(), cli('cfg'));
    const dopo = {};
    for (const u of Object.keys(stato.pagine)) dopo[u] = await leggi(vis, u);
    const c1 = cli('cfg');
    ok('alla prima visita il registro passa nella tabella: migrato 1, elenco del JSON vuoto, sei righe, voci 6', c1.migrato === 1 && c1.elenco === 0 && c1.tabella === 6 && c1.voci === 6, c1);
    const righe = cli('sql', 'SELECT pagina, servizio, zona, sede, servizio_pagina, creata FROM {p}zone ORDER BY id');
    ok('le righe della tabella sono le voci di prima: pagina, servizio, zona (anche Forlì e l\'emoji), sede, pagina del servizio, data', righe.length === 6 && stato.elenco.every(e => righe.some(r => +r.pagina === e[0] && r.servizio === e[1] && r.zona.replace('&#x1F355;', '\u{1F355}') === e[2] && r.sede === e[3] && r.servizio_pagina === e[4] && String(r.creata) === e[5])), righe);
    const diversi = [];
    for (const [u, x] of Object.entries(stato.pagine)) if (x.s !== dopo[u].s || norm(x.t) !== norm(dopo[u].t)) diversi.push(u);
    ok('pagine per zona, bozza, home, Servizi, sitemap e llms.txt identici al byte prima e dopo (a parte il numero di versione dei fogli)', diversi.length === 0, diversi);
    const ldDiversi = Object.keys(stato.pagine).filter(u => ldDi(stato.pagine[u].t) !== ldDi(dopo[u].t));
    ok('il JSON-LD di ogni pagina identico al byte', ldDiversi.length === 0, ldDiversi);
    const tab = cli('cfg'); await leggi(vis, '/idraulico-a-forli/');
    ok('la migrazione non si ripete: dopo altre visite sempre sei righe', cli('cfg').tabella === 6 && tab.tabella === 6);
  } else if (FASE === 'pannello') {
    const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
    const p = await ctx.newPage(); pnConferme.on(p, d => d.accept());
    await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    await p.goto(B + '/admin/zone.php', { waitUntil: 'networkidle' });
    ok('il pannello Zone mostra le sei pagine per zona, senza avvisi di errore', await p.locator('#zn-tabella tbody tr').count() === 6 && await p.locator('.flash-error').count() === 0 && /6 pagine per zona in tutto/.test((await p.locator('#zn-numeri').innerText()).replace(/\s+/g, ' ')));
    await Promise.all([p.waitForNavigation(), p.locator('#zn-tabella tr', { hasText: 'Imola' }).locator('form[data-conferma] button').click()]);
    ok('il registro migrato si scrive: Togli dal registro (Imola) lascia cinque righe e voci 5', cli('cfg').tabella === 5 && cli('cfg').voci === 5);
    const lugo = (await leggi(vis, '/elettricista-a-lugo/')).t;
    ok('dopo Togli dal registro la pagina di Lugo non elenca più Imola', !/ew-zone-link[^>]*>Imola</.test(lugo));
  }
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error('ERRORE della prova:', e); process.exit(2); });
