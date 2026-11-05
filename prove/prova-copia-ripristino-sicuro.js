// Il ripristino sicuro della Copia del sito (2.25.1), come un utente nel browser. Dopo il guasto del 4 novembre 2026 su un sito
// vero (DROP TABLE prima di una CREATE che il server rifiutava: sito senza users, settings e pages, pannello morto).
// Siti: A (kit Attività, prefisso cms_, MySQL 8), C (kit Personale, prefisso plume_c_, database suo), B2 (prefisso cms_b_ nello
// STESSO database di A, come prova-copia-pulita.js), D (kit Vuoto, per la pagina di soccorso). Tutti con lo stesso utente.
//  a. copia di A ripristinata su C (prefisso diverso): riesce, contenuti uguali, file rimessi, nessuna tabella di appoggio
//     rimasta, viste valide, sito_url di C conservato, registro su file, dashboard senza errori;
//  b. copia con COLLATE utf8mb4_uca1400_ai_ci (e utf8mb3_uca1400_ai_ci) in ogni CREATE: riesce, il registro dice della
//     collazione sostituita, il sito funziona;
//  c. copia con una CREATE che non può funzionare (ENGINE=NonEsiste su pages): il ripristino si ferma, NIENTE cambia (stesse
//     tabelle e impronte, uploads intatti, nessuno scarto nuovo), il pannello spiega in parole semplici, niente appoggio;
//  d. tabelle di appoggio e marcatore lasciati da un ripristino interrotto (fatti a mano): aprendo Copia del sito si tolgono (e
//     la pagina lo dice), solo quelle nominate dal marcatore; una che non si cancella (un vincolo la tiene) resta nel marcatore
//     anche dopo il ripristino seguente, che lo dice;
//  e. due installazioni nello stesso database: B2 resta intatta; e il rientro dopo un ripristino con un altro amministratore
//     (login con l'avviso, ritorno in Copia del sito con il registro dal file);
//  f. pagina di soccorso su D: senza users (e senza settings) /admin/, il login e le API rispondono 503 con la pagina pulita,
//     senza percorsi del server né tracce; a chi non è entrato mai il nome del database, l'utente, il prefisso né la risposta
//     tecnica del server: escono (con le copie e l'ultimo registro) solo con aggiornamenti/soccorso.txt; la home 503 "Torno
//     subito"; il database si rimette importando db.sql.gz della copia e il pannello torna; credenziali sbagliate: "non
//     raggiunge il database" senza la password; a 390 px niente scorrimento di lato; la schermata per il manuale (FOTO_MANUALE=1);
//  g. fra server (SITO_MARIADB="<base>|<cartella>" e SITO_MYSQL57="<base>|<cartella>" nell'ambiente): la copia di A (MySQL 8)
//     ripristinata su MariaDB e su MySQL 5.7, e una copia di MariaDB su MySQL 8: riescono, collazioni sostituite nel registro;
//  h. ripristino interrotto (CMS_RIPRISTINO_PAUSA nel config di C, server php ucciso a metà, poi riacceso): il sito è intatto,
//     il ripristino dopo toglie le tabelle di appoggio;
//  i. copia non firmata con una INSERT che contiene una sottoquery verso l'altra installazione: rifiutata, database intatto;
//  j. copia non firmata con una CREATE che lega pages all'altra installazione (REFERENCES cms_b_users) o la monta sopra
//     (ENGINE=MERGE UNION=): rifiutata prima dello scambio, nessun vincolo nel database; le stesse parole in un COMMENT passano;
//  s. snapshot degli Aggiornamenti (stesso motore): con una CREATE rotta "Ripristino NON fatto" e nemmeno il file dello
//     snapshot si rimette; con il database buono database e file tornano; B2 intatta;
//  più: un secondo ripristino mentre uno è in corso viene rifiutato ("un altro ripristino è già in corso"), e anche un
//  aggiornamento (agg_applica) in quel momento non parte.
// uso: DBPASS=... node prove/prova-copia-ripristino-sicuro.js <base A> <cartella A> <base C> <cartella C> <base B2> <cartella B2>
//      <base D> <cartella D> <utente> <password> <cartella uscita>
// Lascia A come la sua prima copia, C con i contenuti di A. Le prove fra server e il php -S riacceso usano
// prove/router-php-s.php; il server di C deve essere un "php -S 127.0.0.1:<porta> -t <cartella C>" senza cartella.
const { chromium, request } = require('playwright');
const { execFileSync, spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const { conta, pagina, entraPannello, largo } = require('./addon-a-comune');
const [BA, DA, BC, DC, BB, DB2, BD, DD, UTENTE, PASSWORD, OUT] = process.argv.slice(2);
const { ok, fine } = conta();
fs.mkdirSync(OUT, { recursive: true });
const dorme = ms => new Promise(r => setTimeout(r, ms));
const php = (dir, codice) => execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='127.0.0.1'; require '${dir}/includes/config.php'; require_once '${dir}/includes/db.php'; ${codice}`], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
const likeEsc = s => s.replace(/[\\%_]/g, '\\$&');
/** Le tabelle del database che cominciano con un prefisso (dalla parte del sito dato). */
const tabelle = (dir, pref) => JSON.parse(php(dir, `echo json_encode(db()->query("SHOW TABLES LIKE '${likeEsc(pref)}%'")->fetchAll(PDO::FETCH_COLUMN));`));
/** Le impronte (CHECKSUM TABLE) delle tabelle con un prefisso, senza le viste e senza il registro e le sessioni della sicurezza (che ogni accesso e ogni copia scrivono). */
const impronte = (dir, pref) => php(dir, `$o=[]; foreach (db()->query("SHOW FULL TABLES LIKE '${likeEsc(pref)}%'")->fetchAll(PDO::FETCH_NUM) as $r) { if (strtoupper($r[1]) === 'VIEW' || preg_match('/(settings|sicurezza_[a-z]+)$/', $r[0])) continue; $o[$r[0]] = db()->query('CHECKSUM TABLE \`' . $r[0] . '\`')->fetch(PDO::FETCH_ASSOC)['Checksum']; } ksort($o); echo json_encode($o);`);
/** Le impostazioni (settings) per contenuto, senza le impronte degli schemi (che ogni pagina può riscrivere): la tabella settings si confronta così. */
const impostazioni = (dir, pref) => php(dir, `echo json_encode(db()->query("SELECT setting_key, setting_value FROM \`${pref}settings\` WHERE setting_key NOT LIKE '%impronta' AND setting_key NOT LIKE '%\\\\_schema' AND setting_key <> 'versione_controllo_cache' ORDER BY setting_key")->fetchAll(PDO::FETCH_KEY_PAIR));`);
/** Le tabelle che differiscono fra due impronte (per dire quali, in un KO). */
const diverse = (x, y) => { const a = JSON.parse(x), b = JSON.parse(y); return [...new Set([...Object.keys(a), ...Object.keys(b)])].filter(k => a[k] !== b[k]); };
const uno = (dir, sql) => php(dir, `echo (string)db()->query(${JSON.stringify(sql)})->fetchColumn();`);
const esegui = (dir, sql) => php(dir, `db()->exec(${JSON.stringify(sql)}); echo 'ok';`);
const impostazione = (dir, k, v) => php(dir, `save_setting(${JSON.stringify(k)}, ${JSON.stringify(v)}); echo 'ok';`);
/** L'elenco dei file di una cartella (ricorsivo, nomi relativi, senza i nascosti), per dire "intatti". */
function elenco(dir) {
  const out = [];
  const giro = (d, rel) => { for (const f of fs.existsSync(d) ? fs.readdirSync(d) : []) { if (f[0] === '.') continue; const p = path.join(d, f); const r = rel ? rel + '/' + f : f; if (fs.statSync(p).isDirectory()) giro(p, r); else out.push(r + ':' + fs.statSync(p).size); } };
  giro(dir, ''); return out.sort().join('\n');
}
const scarti = dir => (fs.existsSync(path.join(dir, 'aggiornamenti/scarto')) ? fs.readdirSync(path.join(dir, 'aggiornamenti/scarto')) : []).filter(x => /^ripristino-/.test(x)).sort();
const copie = dir => (fs.existsSync(path.join(dir, 'aggiornamenti/copie')) ? fs.readdirSync(path.join(dir, 'aggiornamenti/copie')) : []).filter(x => /\.zip$/.test(x)).sort();
/** Mette una copia (zip) fra quelle sul server di un sito. */
function metti(dir, zip, nome = null) { fs.mkdirSync(path.join(dir, 'aggiornamenti/copie'), { recursive: true }); const n = nome || path.basename(zip); fs.copyFileSync(zip, path.join(dir, 'aggiornamenti/copie', n)); return n; }
/**
 * Una copia modificata: le regole [regex, sostituzione, solo] si applicano riga per riga a db.sql.gz; solo = 'create',
 * 'create:<tabella>' o 'insert' limita le righe; aggiunte = righe da mettere prima dell'ultimo SET. Il manifest resta uguale.
 */
function modificaZip(src, dest, regole, aggiunte = []) {
  execFileSync('python3', ['-I', '-c', `
import sys, zipfile, gzip, json, re
src, dest, regole, aggiunte = sys.argv[1], sys.argv[2], json.loads(sys.argv[3]), json.loads(sys.argv[4])
zi = zipfile.ZipFile(src); zo = zipfile.ZipFile(dest, 'w', zipfile.ZIP_DEFLATED)
for n in zi.namelist():
    d = zi.read(n)
    if n == 'db.sql.gz':
        righe = gzip.decompress(d).decode('utf8').split('\\n')
        out = []
        for r in righe:
            for rx, rp, solo in regole:
                if solo == 'create' and not r.startswith('CREATE TABLE'): continue
                if solo.startswith('create:') and not r.startswith('CREATE TABLE \`' + solo[7:] + '\`'): continue
                if solo == 'insert' and not r.startswith('INSERT INTO'): continue
                r = re.sub(rx, rp, r)
            if r == 'SET FOREIGN_KEY_CHECKS=1;' and aggiunte:
                out.extend(aggiunte)
            out.append(r)
        d = gzip.compress('\\n'.join(out).encode('utf8'))
    zo.writestr(n, d)
zo.close()`, src, dest, JSON.stringify(regole), JSON.stringify(aggiunte)]);
  return dest;
}
/** Le righe CREATE di db.sql.gz di una copia (per contare le collazioni). */
const createDi = zip => execFileSync('python3', ['-I', '-c', 'import sys,zipfile,gzip; print("\\n".join(r for r in gzip.decompress(zipfile.ZipFile(sys.argv[1]).read("db.sql.gz")).decode("utf8").split("\\n") if r.startswith("CREATE TABLE")))', zip]).toString();
/** Importa db.sql.gz di una copia nel database di un sito riga per riga, come farebbe phpMyAdmin (senza il pannello). */
function importaDump(dir, zip) {
  const gz = path.join(OUT, 'importa-' + Date.now() + '.sql.gz');
  execFileSync('python3', ['-I', '-c', 'import sys,zipfile; open(sys.argv[2],"wb").write(zipfile.ZipFile(sys.argv[1]).read("db.sql.gz"))', zip, gz]);
  const r = php(dir, `$gz = gzopen(${JSON.stringify(gz)}, 'rb'); $n = 0; $e = 0; while (($l = gzgets($gz)) !== false) { $l = rtrim($l, "\\r\\n;"); if ($l === '') continue; try { db()->exec($l); $n++; } catch (Throwable $x) { $e++; fwrite(STDERR, $x->getMessage() . "\\n"); } } echo "$n eseguite, $e errori";`);
  fs.unlinkSync(gz);
  return r;
}
async function preparaCopia(p, B) {
  await p.goto(B + '/admin/copia-sito.php', { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation({ timeout: 180000 }), p.click('button:has-text("Prepara la copia")')]);
  return p.locator('.cs-table tr.cs-nuova input[name=file]').first().getAttribute('value');
}
async function ripristina(p, B, file, tieni = false) {
  await p.goto(B + '/admin/copia-sito.php', { waitUntil: 'networkidle' });
  const r = p.locator('.cs-table tr', { has: p.locator(`input[value="${file}"]`) });
  await r.locator('summary').click();
  await r.locator('input[name=conferma]').check();
  if (tieni) await r.locator('input[name=tieni]').check();
  await Promise.all([p.waitForNavigation({ timeout: 600000 }), r.locator('button:has-text("Ripristina adesso")').click()]);
  const testo = await p.locator('body').innerText();
  return { testo, ok: await p.locator('.cs-esito.ok').count() === 1, ko: await p.locator('.cs-esito.ko').count() === 1, titolo: await p.locator('.cs-esito strong').first().innerText().catch(() => ''), log: await p.locator('.cs-esito .cs-log').first().innerText().catch(() => ''), url: p.url() };
}
const nomeSito = async (p, B) => ((await (await p.goto(B + '/', { waitUntil: 'networkidle' })).text()).match(/<title>([^<]*)<\/title>/) || ['', ''])[1];
const homeTesto = async (ctx, B) => { const r = await ctx.request.get(B + '/'); return { status: r.status(), testo: await r.text() }; };
const pidsServer = porta => execFileSync('ps', ['-eo', 'pid=,comm=,args=']).toString().split('\n').map(l => l.match(/^\s*(\d+)\s+(\S+)\s+(.*)$/)).filter(m => m && m[2] === 'php' && m[3].includes(`-S 127.0.0.1:${porta} `)).map(m => +m[1]);
async function aspettaServer(B, giu = false) {
  const ctx = await request.newContext();
  for (let i = 0; i < 100; i++) {
    let su = false;
    try { su = (await ctx.get(B + '/admin/login.php', { timeout: 3000 })).status() > 0; } catch (e) { su = false; }
    if (su !== giu) { await ctx.dispose(); return true; }
    await dorme(300);
  }
  await ctx.dispose(); return false;
}

let br;
(async () => {
  br = await chromium.launch();
  const err = [];
  const a = await pagina(br, err); await entraPannello(a, BA, UTENTE, PASSWORD);
  const c = await pagina(br, err); await entraPannello(c, BC, UTENTE, PASSWORD);
  const vai = (p, B, u) => p.goto(B + u, { waitUntil: 'networkidle' });
  for (const d of [DA, DC, DB2, DD]) fs.mkdirSync(path.join(d, 'aggiornamenti/copie'), { recursive: true });

  // ---- Preparazione: A con un nome suo, un file in uploads e uno con codice dentro; la copia di A.
  impostazione(DA, 'sito_nome', 'Sito A rf');
  fs.mkdirSync(path.join(DA, 'uploads/pagine/2026/11'), { recursive: true });
  fs.writeFileSync(path.join(DA, 'uploads/pagine/2026/11/foto-di-a-rf.txt'), 'foto di A');
  fs.writeFileSync(path.join(DA, 'uploads/pagine/2026/11/cattivo-rf.svg'), '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
  const copiaA = await preparaCopia(a, BA);
  // Lo zip resta nella cartella di uscita: fra le copie sul server se ne tengono 6, e i ripristini ne fanno una ciascuno.
  const zipA = path.join(OUT, copiaA);
  fs.copyFileSync(path.join(DA, 'aggiornamenti/copie', copiaA), zipA);
  ok('preparazione: la copia di A è pronta, con server_versione nel manifest', !!copiaA && fs.existsSync(zipA) && /"server_versione"/.test(execFileSync('python3', ['-I', '-c', 'import sys,zipfile; print(zipfile.ZipFile(sys.argv[1]).read("manifest.json").decode())', zipA]).toString()), copiaA);
  const createA = createDi(zipA);
  ok('la copia di A (MySQL 8) ha CREATE con COLLATE=utf8mb4_0900_ai_ci (il caso di arketypos)', (createA.match(/COLLATE=utf8mb4_0900_ai_ci/g) || []).length >= 10, (createA.match(/COLLATE=utf8mb4_0900_ai_ci/g) || []).length);
  ok('la copia scrive gli INSERT a blocchi sotto 1 MB', !execFileSync('python3', ['-I', '-c', 'import sys,zipfile,gzip; print(max(len(r) for r in gzip.decompress(zipfile.ZipFile(sys.argv[1]).read("db.sql.gz")).decode("utf8").split("\\n")))', zipA]).toString().match(/^(1[1-9]\d{5,}|[2-9]\d{6,})/));

  // ---- a. Copia di A su C (prefisso diverso).
  const urlC = uno(DC, "SELECT setting_value FROM `plume_c_settings` WHERE setting_key='sito_url'");
  fs.writeFileSync(path.join(DC, 'uploads/solo-c-rf.txt'), 'solo in C');
  const tabC0 = tabelle(DC, 'plume_c_');
  metti(DC, zipA);
  let r = await ripristina(c, BC, copiaA);
  ok('a. copia di A (cms_) ripristinata su C (plume_c_): esito riuscito, nessun ERRORE', r.ok && /Sito ripristinato dalla copia/.test(r.titolo) && !/ERRORE/.test(r.log), r.titolo);
  ok('a. il registro dice le tabelle rimesse e i file', /58 tabelle rimesse dalla copia/.test(r.log) && /File rimessi: 6\d/.test(r.log), (r.log.match(/Database:[^\n]*/) || [''])[0]);
  const tabC1 = tabelle(DC, 'plume_c_');
  ok('a. C ha ora le tabelle della copia con il suo prefisso (users, settings, pages comprese) e le due viste', ['plume_c_users', 'plume_c_settings', 'plume_c_pages', 'plume_c_v_published_articles', 'plume_c_v_scheduled_articles'].every(t => tabC1.includes(t)) && tabC1.length >= tabC0.length, tabC1.length);
  ok('a. nessuna tabella di appoggio o di parcheggio rimasta, niente marcatore', tabelle(DC, 'rip').length === 0 && tabelle(DC, 'vec').length === 0 && !fs.existsSync(path.join(DC, 'aggiornamenti/copie/.ripristino-in-corso.json')));
  ok('a. le viste degli articoli sono valide (SELECT dalla vista)', /^\d+$/.test(uno(DC, 'SELECT COUNT(*) FROM `plume_c_v_published_articles`')));
  ok('a. sito_url di C conservato (scritto nella settings di appoggio prima dello scambio)', uno(DC, "SELECT setting_value FROM `plume_c_settings` WHERE setting_key='sito_url'") === urlC && urlC === BC, urlC);
  const tC = await nomeSito(c, BC);
  ok('a. la home di C ha il nome e il menu di A (Servizi del kit Attività)', /Sito A rf/.test(tC) && (await c.locator('header nav a', { hasText: 'Servizi' }).count()) >= 1, tC);
  ok('a. il file di uploads di A è in C; quello con codice dentro no (copia non firmata), e il registro lo dice', fs.existsSync(path.join(DC, 'uploads/pagine/2026/11/foto-di-a-rf.txt')) && !fs.existsSync(path.join(DC, 'uploads/pagine/2026/11/cattivo-rf.svg')) && /con codice dentro[^\n]*cattivo-rf\.svg/.test(r.log));
  ok('a. il file che c\'era solo in C è andato nello scarto', !fs.existsSync(path.join(DC, 'uploads/solo-c-rf.txt')) && /solo-c-rf\.txt/.test(r.testo));
  ok('a. il registro è anche su file (aggiornamenti/copie/<copia>.ripristino.log, esito=ok)', /esito=ok/.test(fs.readFileSync(path.join(DC, 'aggiornamenti/copie', copiaA + '.ripristino.log'), 'utf8')));
  await vai(c, BC, '/admin/index.php');
  ok('a. la dashboard di C si apre dopo lo scambio (le impronte degli schemi azzerate si rifanno da sole)', /Dashboard/.test(await c.locator('body').innerText()) && !(await c.content()).includes('Fatal error'));
  await vai(c, BC, '/admin/copia-sito.php');
  ok('a. Copia del sito mostra l\'ultimo registro letto dal file', await c.locator('#cs-ultimo').count() === 1 && /riuscito/.test(await c.locator('#cs-ultimo').innerText()));
  r = await ripristina(c, BC, copiaA, true);
  ok('a2. la stessa copia ripristinata di nuovo su C (tutte le tabelle già esistono: tutte parcheggiate e tolte): riesce', r.ok && tabelle(DC, 'vec').length === 0, r.titolo);

  // ---- b. Collazioni sconosciute in ogni CREATE.
  const zipB = modificaZip(zipA, path.join(OUT, 'copia-20261104-000001-collazione.zip'), [['COLLATE=utf8mb4_0900_ai_ci', 'COLLATE=utf8mb4_uca1400_ai_ci', 'create'], ['DEFAULT CHARSET=utf8mb3;$', 'DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;', 'create']]);
  const createB = createDi(zipB);
  ok('b. la copia modificata ha una collazione sconosciuta in ogni CREATE', createB.split('\n').filter(Boolean).every(l => /uca1400/.test(l)), createB.split('\n').filter(l => l && !/uca1400/.test(l)).length);
  const impC0 = impronte(DC, 'plume_c_'); const setC0 = impostazioni(DC, 'plume_c_');
  metti(DC, zipB);
  r = await ripristina(c, BC, path.basename(zipB));
  ok('b. copia con COLLATE utf8mb4_uca1400_ai_ci (sconosciuta a MySQL 8): il ripristino riesce', r.ok, r.titolo);
  ok('b. il registro dice della collazione sostituita, con i due server', /Copia fatta su MySQL 8\.0[^\n]*qui MySQL 8\.0[^\n]*collazione utf8mb4_uca1400_ai_ci -> utf8mb4_0900_ai_ci[^\n]*collazione utf8mb3_uca1400_ai_ci -> utf8mb3_unicode_ci/.test(r.log), (r.log.match(/Copia fatta su[^\n]*/) || [''])[0]);
  ok('b. il sito funziona e ha gli stessi dati di prima (stesse impronte delle tabelle, stesse impostazioni)', (await homeTesto(c, BC)).status === 200 && impronte(DC, 'plume_c_') === impC0 && impostazioni(DC, 'plume_c_') === setC0, { diverse: diverse(impronte(DC, 'plume_c_'), impC0), impostazioni: diverse(impostazioni(DC, 'plume_c_'), setC0) });

  // ---- c. Una CREATE che non può funzionare: niente cambia.
  const zipC = modificaZip(zipA, path.join(OUT, 'copia-20261104-000002-rotta.zip'), [['ENGINE=InnoDB', 'ENGINE=NonEsiste', 'create:cms_pages']]);
  ok('c. la copia rotta ha ENGINE=NonEsiste nella CREATE di pages', /CREATE TABLE `cms_pages`[^\n]*ENGINE=NonEsiste/.test(createDi(zipC)));
  const primaC = { imp: impronte(DC, 'plume_c_'), set: impostazioni(DC, 'plume_c_'), tab: tabelle(DC, 'plume_c_').length, up: elenco(path.join(DC, 'uploads')), scarti: scarti(DC), ultima: copie(DC).filter(x => /prima-del-ripristino/.test(x)).pop() || '' };
  metti(DC, zipC);
  r = await ripristina(c, BC, path.basename(zipC));
  ok('c. il ripristino si ferma e il pannello lo dice come errore', r.ko && /Ripristino con errori[^\n]*non si è fatto, il sito è come prima/.test(r.titolo), r.titolo);
  ok('c. in parole semplici: cosa è successo (motore di tabelle) e il testo tecnico fra parentesi', /Il ripristino non si è fatto: il sito è come prima/.test(r.log) && /Motivo: [^\n]*motore di tabelle[^\n]*\(SQLSTATE[^\n]*NonEsiste/.test(r.log), (r.log.match(/Motivo:[^\n]*/) || [''])[0].slice(0, 200));
  ok('c. NIENTE è cambiato: stesse tabelle, stesse impronte e impostazioni, uploads intatti, nessuno scarto nuovo', impronte(DC, 'plume_c_') === primaC.imp && impostazioni(DC, 'plume_c_') === primaC.set && tabelle(DC, 'plume_c_').length === primaC.tab && elenco(path.join(DC, 'uploads')) === primaC.up && scarti(DC).join() === primaC.scarti.join(), { diverse: diverse(impronte(DC, 'plume_c_'), primaC.imp), set: diverse(impostazioni(DC, 'plume_c_'), primaC.set), tab: tabelle(DC, 'plume_c_').length === primaC.tab, up: elenco(path.join(DC, 'uploads')) === primaC.up, scarti: scarti(DC).join() === primaC.scarti.join() });
  ok('c. nessuna tabella di appoggio rimasta, niente marcatore, ma la copia prima-del-ripristino è stata fatta', tabelle(DC, 'rip').length === 0 && tabelle(DC, 'vec').length === 0 && !fs.existsSync(path.join(DC, 'aggiornamenti/copie/.ripristino-in-corso.json')) && (copie(DC).filter(x => /prima-del-ripristino/.test(x)).pop() || '') > primaC.ultima);
  ok('c. il registro su file dice esito=errore', /esito=errore/.test(fs.readFileSync(path.join(DC, 'aggiornamenti/copie', path.basename(zipC) + '.ripristino.log'), 'utf8')));
  ok('c. il sito C risponde come prima', (await homeTesto(c, BC)).status === 200 && /Sito A rf/.test(await nomeSito(c, BC)));

  // ---- d. Tabelle di appoggio lasciate da un ripristino interrotto (fatte a mano).
  const marcatoreC = path.join(DC, 'aggiornamenti/copie/.ripristino-in-corso.json');
  for (const t of ['rip0000aa_plume_c_resto', 'vec0000aa_plume_c_resto', 'rip0000bb_plume_c_altra']) esegui(DC, 'CREATE TABLE `' + t + '` (`id` int NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB');
  fs.writeFileSync(marcatoreC, JSON.stringify({ appoggio: ['rip0000aa_plume_c_resto'], parcheggio: ['vec0000aa_plume_c_resto'], prefisso: 'plume_c_', quando: '2026-11-04 10:00:00' }));
  await vai(c, BC, '/admin/copia-sito.php');
  let flashC = (await c.locator('.flash').allInnerTexts()).join('\n');
  ok('d. aprendo Copia del sito le tabelle nominate dal marcatore si tolgono subito, e la pagina lo dice', /Tabelle di lavoro rimaste da un ripristino interrotto, tolte: 2/.test(flashC) && tabelle(DC, 'rip0000aa').length === 0 && tabelle(DC, 'vec0000aa').length === 0, flashC.slice(0, 200));
  ok('d. una tabella con il marchio di appoggio ma NON nel marcatore non si tocca', tabelle(DC, 'rip0000bb').length === 1);
  esegui(DC, 'DROP TABLE `rip0000bb_plume_c_altra`');
  ok('d. il marcatore è stato tolto', !fs.existsSync(marcatoreC));
  // Una tabella di lavoro che non si cancella (un vincolo di un'altra tabella la tiene): resta nel marcatore, il ripristino
  // dopo la dice e la tiene nel suo marcatore, e si toglie quando il vincolo non c'è più.
  esegui(DC, 'CREATE TABLE `rip0000cc_plume_c_padre` (`id` int NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB');
  esegui(DC, 'CREATE TABLE `rf_figlio` (`id` int NOT NULL, `p` int, PRIMARY KEY (`id`), CONSTRAINT `fk_rf_figlio` FOREIGN KEY (`p`) REFERENCES `rip0000cc_plume_c_padre` (`id`)) ENGINE=InnoDB');
  fs.writeFileSync(marcatoreC, JSON.stringify({ appoggio: ['rip0000cc_plume_c_padre'], parcheggio: [], prefisso: 'plume_c_', quando: '2026-11-04 10:00:00' }));
  await vai(c, BC, '/admin/copia-sito.php');
  ok('d. una tabella di lavoro che non si cancella resta, e resta nel marcatore', tabelle(DC, 'rip0000cc').length === 1 && fs.existsSync(marcatoreC) && fs.readFileSync(marcatoreC, 'utf8').includes('rip0000cc_plume_c_padre'));
  metti(DC, zipA);
  r = await ripristina(c, BC, copiaA, true);
  ok('d. il ripristino dopo riesce, lo dice, e la tabella resta nel marcatore (non si perde)', r.ok && /ATTENZIONE: 1 tabelle di lavoro di un ripristino interrotto non si sono cancellate/.test(r.log) && tabelle(DC, 'rip0000cc').length === 1 && fs.existsSync(marcatoreC) && fs.readFileSync(marcatoreC, 'utf8').includes('rip0000cc_plume_c_padre') && tabelle(DC, 'vec').length === 0, (r.log.match(/ATTENZIONE[^\n]*/) || [''])[0]);
  esegui(DC, 'DROP TABLE `rf_figlio`');
  await vai(c, BC, '/admin/copia-sito.php');
  flashC = (await c.locator('.flash').allInnerTexts()).join('\n');
  ok('d. senza il vincolo, aprendo la pagina la tabella si toglie e il marcatore sparisce', /tolte: 1/.test(flashC) && tabelle(DC, 'rip0000cc').length === 0 && !fs.existsSync(marcatoreC), flashC.slice(0, 160));

  // ---- e. Due installazioni nello stesso database (A cms_ e B2 cms_b_): B2 intatta. E il rientro con un altro amministratore.
  const impB0 = impronte(DB2, 'cms_b_');
  const nUtentiB = uno(DB2, 'SELECT COUNT(*) FROM `cms_b_users`');
  ok('e. B2 ha le sue tabelle cms_b_ nello stesso database di A', JSON.parse(impB0)['cms_b_users'] !== undefined && tabelle(DA, 'cms_b_').length > 10);
  esegui(DC, "UPDATE `plume_c_users` SET username = 'altro-rf' WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM `plume_c_users`) x)");
  const copiaC = await preparaCopia(c, BC);
  metti(DA, path.join(DC, 'aggiornamenti/copie', copiaC));
  r = await ripristina(a, BA, copiaC);
  ok('e. copia di C (plume_c_, amministratore diverso) ripristinata su A: la sessione cade e si torna al login con l\'avviso', /login\.php\?motivo=ripristino&dopo=copia-sito/.test(r.url) && /ripristinato da una copia che ha altri utenti/.test(r.testo), r.url);
  ok('e. B2 è intatta: stesse impronte, stessi utenti, il suo sito risponde', impronte(DB2, 'cms_b_') === impB0 && uno(DB2, 'SELECT COUNT(*) FROM `cms_b_users`') === nUtentiB && (await homeTesto(a, BB)).status === 200);
  await a.fill('#username', 'altro-rf'); await a.fill('#password', PASSWORD);
  await Promise.all([a.waitForNavigation(), a.click('button[type=submit]')]);
  ok('e. dopo l\'accesso con l\'utente della copia si torna in Copia del sito, con il registro dal file e l\'avviso', /copia-sito\.php\?ultimo=1/.test(a.url()) && await a.locator('#cs-ultimo').count() === 1 && /Sei rientrato con gli utenti della copia/.test(await a.locator('.flash').first().innerText()) && /58 tabelle rimesse/.test(await a.locator('#cs-ultimo .cs-log').innerText()), a.url());
  ok('e. A ha i contenuti della copia e il suo sito_url', /Sito A rf/.test(await nomeSito(a, BA)) && uno(DA, "SELECT setting_value FROM `cms_settings` WHERE setting_key='sito_url'") === BA);
  // A torna la sua copia di partenza (l'amministratore torna "prova": si rientra).
  metti(DA, zipA);
  r = await ripristina(a, BA, copiaA);
  ok('e. A torna come la sua prima copia (di nuovo il login, con gli utenti di prima)', /login\.php\?motivo=ripristino/.test(r.url));
  await a.fill('#username', UTENTE); await a.fill('#password', PASSWORD);
  await Promise.all([a.waitForNavigation(), a.click('button[type=submit]')]);
  ok('e. rientrato come prima, B2 ancora intatta', /copia-sito\.php/.test(a.url()) && impronte(DB2, 'cms_b_') === impB0);
  await vai(a, BA, '/admin/copia-sito.php');
  await a.evaluate(() => document.querySelectorAll('details').forEach(d => { d.open = true; }));
  const tel = await pagina(br, err, 390, 844);
  await entraPannello(tel, BA, UTENTE, PASSWORD);
  await tel.goto(BA + '/admin/copia-sito.php', { waitUntil: 'networkidle' });
  ok('e. al telefono Copia del sito con il registro dell\'ultimo ripristino non scorre di lato', await largo(tel) <= 0, await largo(tel));
  await tel.context().close();

  // ---- i. Una copia non firmata con una INSERT che contiene una sottoquery verso l'altra installazione.
  const zipI = modificaZip(zipA, path.join(OUT, 'copia-20261104-000003-sottoquery.zip'), [], ["INSERT INTO `cms_settings` VALUES ((SELECT password_hash FROM `cms_b_users` LIMIT 1),'rf_rubata',NULL);"]);
  const impA0 = impronte(DA, 'cms_'); const setA0 = impostazioni(DA, 'cms_');
  metti(DA, zipI);
  r = await ripristina(a, BA, path.basename(zipI));
  ok('i. INSERT con una sottoquery: la copia è rifiutata prima di toccare il database, e il pannello lo dice', r.ko && /non scrive mai nelle copie/.test(r.log) && /il sito è come prima/.test(r.log), (r.log.match(/Motivo:[^\n]*/) || [''])[0].slice(0, 160));
  ok('i. database intatto (A e B2), nessun dato rubato in settings', impronte(DA, 'cms_') === impA0 && impostazioni(DA, 'cms_') === setA0 && impronte(DB2, 'cms_b_') === impB0 && uno(DA, "SELECT COUNT(*) FROM `cms_settings` WHERE setting_value = 'rf_rubata'") === '0' && tabelle(DA, 'rip').length === 0);
  const zipF = modificaZip(zipA, path.join(OUT, 'copia-20261104-000004-funzione.zip'), [], ["INSERT INTO `cms_settings` VALUES (NOW(),'rf_fun',NULL);"]);
  metti(DA, zipF);
  r = await ripristina(a, BA, path.basename(zipF));
  ok('i. INSERT con una funzione (NOW()): rifiutata, database intatto', r.ko && /non scrive mai nelle copie/.test(r.log) && impronte(DA, 'cms_') === impA0 && impostazioni(DA, 'cms_') === setA0);

  // ---- j. Una copia non firmata con una CREATE che lega una tabella di questo sito a una dell'altra installazione (REFERENCES),
  // o la monta sopra (ENGINE=MERGE UNION=): rifiutata prima dello scambio. Le stesse parole dentro un COMMENT (fra apici) passano.
  const zipJ = modificaZip(zipA, path.join(OUT, 'copia-20261104-000005-vincolo.zip'), [['\\) ENGINE=', ', `b_user` int DEFAULT 1, KEY `b_user` (`b_user`), CONSTRAINT `fk_rf` FOREIGN KEY (`b_user`) REFERENCES `cms_b_users` (`id`) ON DELETE CASCADE) ENGINE=', 'create:cms_pages']]);
  ok('j. la copia modificata ha un vincolo REFERENCES verso cms_b_users nella CREATE di pages', /CREATE TABLE `cms_pages`[^\n]*REFERENCES `cms_b_users`/.test(createDi(zipJ)));
  const vincoli = () => uno(DA, "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'cms_b_users'");
  metti(DA, zipJ);
  r = await ripristina(a, BA, path.basename(zipJ));
  ok('j. CREATE con REFERENCES: la copia è rifiutata prima dello scambio, e il pannello dice la parola e la tabella', r.ko && /non scrive mai nelle copie/.test(r.log) && /REFERENCES/.test(r.log) && /tabella pages/.test(r.log) && /il sito è come prima/.test(r.log), (r.log.match(/Motivo:[^\n]*/) || [''])[0].slice(0, 220));
  ok('j. nessun vincolo verso cms_b_users nel database, A e B2 intatti, niente appoggio', vincoli() === '0' && impronte(DA, 'cms_') === impA0 && impronte(DB2, 'cms_b_') === impB0 && tabelle(DA, 'rip').length === 0, vincoli());
  const zipU = modificaZip(zipA, path.join(OUT, 'copia-20261104-000006-union.zip'), [['ENGINE=InnoDB', 'ENGINE=MERGE UNION=(`cms_b_users`) INSERT_METHOD=LAST', 'create:cms_pages']]);
  metti(DA, zipU);
  r = await ripristina(a, BA, path.basename(zipU));
  ok('j. CREATE con UNION= (una tabella MERGE sopra cms_b_users): rifiutata, database intatto', r.ko && /non scrive mai nelle copie/.test(r.log) && /UNION=/.test(r.log) && impronte(DA, 'cms_') === impA0 && tabelle(DA, 'rip').length === 0, (r.log.match(/Motivo:[^\n]*/) || [''])[0].slice(0, 160));
  const zipK = modificaZip(zipA, path.join(OUT, 'copia-20261104-000007-commento.zip'), [['(`id` int(?:\\(\\d+\\))? NOT NULL AUTO_INCREMENT),', "\\1 COMMENT 'con references, select e union= dentro',", 'create:cms_pages']]);
  ok('j. la copia con le stesse parole in un COMMENT ha la CREATE di pages con il commento', /CREATE TABLE `cms_pages`[^\n]*COMMENT 'con references, select e union= dentro'/.test(createDi(zipK)));
  metti(DA, zipK);
  r = await ripristina(a, BA, path.basename(zipK));
  ok('j. le stesse parole fra apici (un COMMENT di colonna) non fermano il ripristino: riesce', r.ok && !/ERRORE/.test(r.log), r.titolo);

  // ---- s. Lo snapshot degli Aggiornamenti passa dallo stesso motore: con il database rifiutato niente cambia, nemmeno i file.
  await vai(a, BA, '/admin/aggiornamenti.php');
  await Promise.all([a.waitForNavigation(), a.click('button:has-text("Crea uno snapshot adesso")')]);
  const snapId = ((await a.locator('.flash').first().innerText()).match(/Snapshot creato: (\S+) \(/) || [])[1] || '';
  const snapDir = path.join(DA, 'aggiornamenti/snapshot', snapId || 'nessuno');
  ok('s. lo snapshot manuale è stato creato', snapId !== '' && fs.existsSync(path.join(snapDir, 'db.sql.gz')), snapId);
  // Un file nello snapshot (come quelli che un pacchetto sta per sovrascrivere), e la CREATE di pages che non può funzionare.
  fs.mkdirSync(path.join(snapDir, 'files/uploads'), { recursive: true });
  fs.writeFileSync(path.join(snapDir, 'files/uploads/rf-snap.txt'), 'vecchio');
  const meta = JSON.parse(fs.readFileSync(path.join(snapDir, 'meta.json'), 'utf8')); meta.files = [{ dest: 'uploads/rf-snap.txt', esisteva: true }];
  fs.writeFileSync(path.join(snapDir, 'meta.json'), JSON.stringify(meta));
  fs.writeFileSync(path.join(DA, 'uploads/rf-snap.txt'), 'nuovo');
  fs.copyFileSync(path.join(snapDir, 'db.sql.gz'), path.join(OUT, 'snapshot-buono.sql.gz'));
  execFileSync('python3', ['-I', '-c', 'import sys,gzip\nd = gzip.decompress(open(sys.argv[1], "rb").read()).decode("utf8")\nd = "\\n".join(r.replace("ENGINE=InnoDB", "ENGINE=NonEsiste") if r.startswith("CREATE TABLE `cms_pages`") else r for r in d.split("\\n"))\nopen(sys.argv[1], "wb").write(gzip.compress(d.encode("utf8")))', path.join(snapDir, 'db.sql.gz')]);
  const ripristinaSnapshot = async () => {
    await vai(a, BA, '/admin/aggiornamenti.php');
    const f = a.locator('form', { has: a.locator(`input[name=id][value="${snapId}"]`) }).filter({ has: a.locator('input[name=action][value=ripristina]') });
    await Promise.all([a.waitForNavigation({ timeout: 600000 }), f.locator('button:has-text("Ripristina")').click()]);
    return { titolo: await a.locator('.agg-esito strong').first().innerText().catch(() => ''), log: await a.locator('.agg-esito .agg-log').first().innerText().catch(() => ''), flash: (await a.locator('.flash').allInnerTexts()).join('\n') };
  };
  const impS0 = impronte(DA, 'cms_');
  let rs2 = await ripristinaSnapshot();
  ok('s. snapshot con una CREATE che non può funzionare: "Ripristino NON fatto", il registro dice il motivo', /Ripristino NON fatto dallo snapshot/.test(rs2.titolo) && /Database NON ripristinato/.test(rs2.log) && /motore di tabelle/.test(rs2.log), rs2.titolo);
  ok('s. il file dello snapshot NON è stato rimesso (database e file restano com\'erano), e il messaggio lo dice', fs.readFileSync(path.join(DA, 'uploads/rf-snap.txt'), 'utf8') === 'nuovo' && /File dello snapshot non toccati \(1\)/.test(rs2.log) && /database e file sono rimasti com'erano/.test(rs2.flash) && impronte(DA, 'cms_') === impS0 && tabelle(DA, 'rip').length === 0, rs2.flash.slice(0, 160));
  fs.copyFileSync(path.join(OUT, 'snapshot-buono.sql.gz'), path.join(snapDir, 'db.sql.gz'));
  rs2 = await ripristinaSnapshot();
  ok('s. lo stesso snapshot con il database buono: ripristinato, il file rimesso, nessuna tabella di lavoro', /^Ripristino dallo snapshot/.test(rs2.titolo) && /Database ripristinato/.test(rs2.log) && /File rimesso: uploads\/rf-snap\.txt/.test(rs2.log) && fs.readFileSync(path.join(DA, 'uploads/rf-snap.txt'), 'utf8') === 'vecchio' && tabelle(DA, 'rip').length === 0 && tabelle(DA, 'vec').length === 0, rs2.titolo);
  fs.unlinkSync(path.join(DA, 'uploads/rf-snap.txt'));
  ok('s. B2 intatta anche dopo lo snapshot (che ha tutto il database): passano solo le tabelle del prefisso', impronte(DB2, 'cms_b_') === impB0);

  // ---- f. La pagina di soccorso su D.
  const d = await pagina(br, err); await entraPannello(d, BD, UTENTE, PASSWORD);
  const copiaD = await preparaCopia(d, BD);
  const zipD = path.join(DD, 'aggiornamenti/copie', copiaD);
  const anon = await request.newContext();
  const senzaPercorsi = t => !t.includes(DD) && !/\/home\/|\/tmp\/|\/var\//.test(t) && !/Fatal error|Stack trace|PDOException/.test(t);
  const confD = fs.readFileSync(path.join(DD, 'includes/config.local.php'), 'utf8');
  const nomeDb = (confD.match(/DB_NAME', '([^']+)'/) || [])[1] || '';
  const utenteDb = (confD.match(/DB_USER', '([^']+)'/) || [])[1] || '';
  /** I dati che a chi non è entrato non devono uscire: nome del database, utente, prefisso e nomi delle tabelle. */
  const senzaDati = t => nomeDb !== '' && utenteDb !== '' && !t.includes(nomeDb) && !t.includes(utenteDb) && !/cms_users|cms_settings|prefisso <b>/.test(t);
  const soccorsoTxt = path.join(DD, 'aggiornamenti/soccorso.txt');
  const nD0 = tabelle(DD, 'cms_').length;
  esegui(DD, 'DROP TABLE `cms_users`');
  let rs = await anon.get(BD + '/admin/');
  let ts = await rs.text();
  ok('f. senza la tabella users /admin/ risponde 503 con la pagina di soccorso, in parole semplici', rs.status() === 503 && /Il database del sito è vuoto o incompleto/.test(ts) && /manca la tabella degli utenti/.test(ts) && ts.includes('data-caso="tabelle"') && /Rimettere il sito da una copia/.test(ts) && /phpMyAdmin/.test(ts), rs.status());
  ok('f. a chi non è entrato la pagina non dice il nome del database, l\'utente né il prefisso; mai la password, niente percorsi del server né tracce', senzaDati(ts) && !ts.includes(process.env.DBPASS || '§§') && senzaPercorsi(ts) && /Cache-Control: no-store/i.test(Object.entries(rs.headers()).map(x => x.join(': ')).join('\n')), { db: ts.includes(nomeDb), utente: ts.includes(utenteDb) });
  ok('f. senza aggiornamenti/soccorso.txt niente elenco delle copie, e la pagina spiega come averlo (con i dati del database)', !ts.includes('soccorso-copie') && /soccorso\.txt/.test(ts) && !ts.includes(copiaD) && /nome del database, l'utente e il prefisso/.test(ts));
  rs = await anon.get(BD + '/admin/login.php'); ts = await rs.text();
  ok('f. anche il login risponde con la pagina di soccorso (503), non con "Errore temporaneo"', rs.status() === 503 && /Il database del sito è vuoto/.test(ts) && !/Errore temporaneo/.test(ts));
  rs = await anon.get(BD + '/admin/editor-api.php?azione=x', { headers: { Accept: 'application/json' } }); ts = await rs.text();
  ok('f. una chiamata API del pannello risponde 503 in JSON, senza HTML', rs.status() === 503 && /^\{"errore":/.test(ts) && !/<html/.test(ts), ts.slice(0, 100));
  rs = await anon.get(BD + '/'); ts = await rs.text();
  ok('f. la home del sito (che gli utenti non li legge) risponde senza tracce né percorsi del server', [200, 503].includes(rs.status()) && senzaPercorsi(ts), rs.status());
  rs = await d.request.get(BD + '/admin/'); ts = await rs.text();
  ok('f. con la sessione dell\'amministratore ancora aperta: la stessa pagina di soccorso (nessuna tabella creata di nascosto)', rs.status() === 503 && /pagina|soccorso|vuoto o incompleto/.test(ts) && !tabelle(DD, 'cms_users').length);
  ok('f. il controllo non ha creato né toccato tabelle (prima della pagina non parte nessuno schema)', tabelle(DD, 'cms_').length === nD0 - 1, { prima: nD0, dopo: tabelle(DD, 'cms_').length });
  // Senza users e senza settings (il caso vero del 4 novembre 2026): il sito pubblico risponde "Torno subito".
  esegui(DD, 'DROP TABLE `cms_settings`');
  rs = await anon.get(BD + '/'); ts = await rs.text();
  ok('f. senza users e settings la home del sito risponde 503 "Torno subito", senza percorsi del server', rs.status() === 503 && /Torno subito/.test(ts) && senzaPercorsi(ts), rs.status());
  rs = await anon.get(BD + '/admin/'); ts = await rs.text();
  ok('f. senza users e settings la pagina di soccorso lo dice (manca la tabella degli utenti e quella delle impostazioni)', rs.status() === 503 && ts.includes('data-caso="tabelle"') && /e quella delle impostazioni/.test(ts) && senzaPercorsi(ts));
  ok('f. nemmeno adesso è nata una tabella', tabelle(DD, 'cms_').length === nD0 - 2, tabelle(DD, 'cms_').length);
  fs.writeFileSync(soccorsoTxt, '');
  await d.goto(BD + '/admin/', { waitUntil: 'networkidle' });
  ts = await d.locator('body').innerText();
  ok('f. con aggiornamenti/soccorso.txt la pagina elenca le copie sul server: nome, data, etichetta, peso, sito d\'origine', await d.locator('#soccorso-copie').count() === 1 && ts.includes(copiaD) && /manuale/.test(ts) && /KB|MB/.test(ts) && ts.includes(BD), ts.slice(0, 80));
  ok('f. con il file escono anche il nome del database, il prefisso e le tabelle che mancano, mai la password', ts.includes(nomeDb) && /prefisso cms_/.test(ts) && /cms_users/.test(ts) && /cms_settings/.test(ts) && !ts.includes(process.env.DBPASS || '§§'));
  ok('f. anche con l\'elenco: nessun pulsante, nessun link alle copie, nessun percorso', await d.locator('#soccorso a, #soccorso button, #soccorso form').count() === 0 && senzaPercorsi(await d.content()));
  await d.setViewportSize({ width: 390, height: 844 });
  await d.goto(BD + '/admin/', { waitUntil: 'networkidle' });
  ok('f. a 390 px la pagina di soccorso non scorre di lato', await largo(d) <= 0, await largo(d));
  await d.setViewportSize({ width: 1200, height: 900 });
  await d.goto(BD + '/admin/', { waitUntil: 'networkidle' });
  if (process.env.FOTO_MANUALE) {
    // La schermata per il manuale d'uso (capitolo 29): i passi segnati come in tools/manuale-uso-schermate.js.
    await d.evaluate(() => {
      const segna = (sel, n) => { const e = document.querySelector(sel); if (!e) return; const r = e.getBoundingClientRect();
        const box = document.createElement('div'); Object.assign(box.style, { position: 'absolute', left: (r.left + scrollX - 4) + 'px', top: (r.top + scrollY - 4) + 'px', width: (r.width + 8) + 'px', height: (r.height + 8) + 'px', border: '3px solid #e8553e', borderRadius: '8px', zIndex: 99998, pointerEvents: 'none', boxSizing: 'border-box' }); document.body.appendChild(box);
        const b = document.createElement('div'); b.textContent = n; Object.assign(b.style, { position: 'absolute', left: Math.max(2, r.left + scrollX - 16) + 'px', top: Math.max(2, r.top + scrollY - 16) + 'px', width: '26px', height: '26px', borderRadius: '50%', background: '#e8553e', color: '#fff', font: '700 15px/26px Inter, sans-serif', textAlign: 'center', zIndex: 99999, boxShadow: '0 1px 4px rgba(0,0,0,.35)' }); document.body.appendChild(b); };
      segna('#soccorso .avviso', 1); segna('#soccorso-passi', 2); segna('#soccorso-copie', 3);
    });
    const box = await d.locator('#soccorso').boundingBox();
    await d.screenshot({ path: path.join(OUT, '27-soccorso.png'), clip: { x: Math.max(0, box.x - 12), y: 0, width: box.width + 24, height: Math.min(box.y + box.height + 12, 1500) }, fullPage: true });
    execFileSync('python3', ['-c', 'import sys\nfrom PIL import Image\nim = Image.open(sys.argv[1]).convert("RGB")\nim.save(sys.argv[2], "JPEG", quality=55, optimize=True, progressive=True)', path.join(OUT, '27-soccorso.png'), path.join(OUT, '27-soccorso.jpg')]);
    console.log('schermata per il manuale: ' + path.join(OUT, '27-soccorso.jpg'));
  }
  // Il rimedio: db.sql.gz della copia importato nel database (come con phpMyAdmin), poi il pannello torna.
  const imp = importaDump(DD, zipD);
  ok('f. db.sql.gz della copia si importa riga per riga senza errori (come con phpMyAdmin)', /, 0 errori/.test(imp), imp);
  rs = await anon.get(BD + '/admin/', { maxRedirects: 0 });
  ok('f. dopo l\'importazione /admin/ torna a rimandare al login', [301, 302, 303].includes(rs.status()) && /login/.test(rs.headers()['location'] || ''), rs.status());
  const d2 = await pagina(br, err);
  await entraPannello(d2, BD, UTENTE, PASSWORD);
  ok('f. il login funziona e la dashboard si apre; soccorso.txt si è cancellato da solo', /index\.php/.test(d2.url()) && !(await d2.content()).includes('Fatal') && !fs.existsSync(soccorsoTxt), d2.url());
  await d.goto(BD + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('f. anche la sessione di prima torna a funzionare (gli utenti sono quelli della copia)', /index\.php/.test(d.url()) && /Dashboard/.test(await d.locator('body').innerText()), d.url());
  await d2.context().close();
  // Senza settings (users c'è).
  esegui(DD, 'DROP TABLE `cms_settings`');
  rs = await anon.get(BD + '/admin/'); ts = await rs.text();
  ok('f. senza la sola tabella settings: la pagina di soccorso dice che mancano le impostazioni (senza i dati del database)', rs.status() === 503 && ts.includes('data-caso="settings"') && /manca la tabella delle impostazioni/.test(ts) && senzaPercorsi(ts) && senzaDati(ts));
  importaDump(DD, zipD);
  rs = await anon.get(BD + '/admin/login.php');
  ok('f. dopo l\'importazione il login torna', rs.status() === 200 && /Accesso al pannello/.test(await rs.text()));
  // Credenziali sbagliate nel config.
  const conf = path.join(DD, 'includes/config.local.php'); const conf0 = fs.readFileSync(conf, 'utf8');
  fs.writeFileSync(conf, conf0.replace(/define\('DB_PASS', '[^']*'\)/, "define('DB_PASS', 'sbagliata-rf')"));
  for (let i = 0; i < 40; i++) { rs = await anon.get(BD + '/admin/'); if (rs.status() === 503) break; await dorme(500); } // opcache di php -S rilegge il file dopo 2 secondi
  ts = await rs.text();
  ok('f. con la password del database sbagliata: "non raggiunge il database" e il rimando al config, mai la password; a chi non è entrato nemmeno nome del database, utente e risposta del server', rs.status() === 503 && ts.includes('data-caso="connessione"') && /non raggiunge il database/.test(ts) && /config\.local\.php/.test(ts) && !ts.includes('sbagliata-rf') && senzaDati(ts) && !/Access denied|codice 1045/.test(ts) && senzaPercorsi(ts), rs.status());
  fs.writeFileSync(soccorsoTxt, '');
  rs = await anon.get(BD + '/admin/'); ts = await rs.text();
  ok('f. con il file soccorso.txt la stessa pagina dice il nome del database, l\'utente e la risposta del server (codice 1045), mai la password', rs.status() === 503 && ts.includes(nomeDb) && ts.includes(utenteDb) && /codice 1045/.test(ts) && !ts.includes('sbagliata-rf') && !ts.includes(process.env.DBPASS || '§§'), rs.status());
  fs.writeFileSync(conf, conf0);
  for (let i = 0; i < 40; i++) { rs = await anon.get(BD + '/admin/login.php'); if (rs.status() === 200) break; await dorme(500); } // opcache rilegge il config dopo 2 secondi
  ok('f. con il config rimesso il login torna, e soccorso.txt si è cancellato da solo', rs.status() === 200 && /Accesso al pannello/.test(await rs.text()) && !fs.existsSync(soccorsoTxt), rs.status());
  await anon.dispose();

  // ---- g. Fra server: la copia di A (MySQL 8) su MariaDB e su MySQL 5.7; una copia di MariaDB su MySQL 8 (C).
  const altri = [['MariaDB', process.env.SITO_MARIADB], ['MySQL 5.7', process.env.SITO_MYSQL57]].filter(x => x[1]);
  for (const [nome, spec] of altri) {
    const [BX, DX] = spec.split('|');
    const x = await pagina(br, err); await entraPannello(x, BX, UTENTE, PASSWORD);
    fs.mkdirSync(path.join(DX, 'aggiornamenti/copie'), { recursive: true });
    metti(DX, zipA);
    r = await ripristina(x, BX, copiaA);
    ok(`g. copia di A (MySQL 8) ripristinata su ${nome}: riesce`, r.ok && !/ERRORE/.test(r.log), r.titolo);
    ok(`g. ${nome}: il registro dice le collazioni sostituite (utf8mb4_0900_ai_ci -> utf8mb4_unicode_ci) e i due server`, /collazione utf8mb4_0900_ai_ci -> utf8mb4_unicode_ci/.test(r.log) && /Copia fatta su MySQL 8\.0[^\n]*, qui (MariaDB|MySQL) /.test(r.log), (r.log.match(/Copia fatta su[^\n]*/) || [''])[0].slice(0, 200));
    ok(`g. ${nome}: il sito funziona con i contenuti di A, users e settings ci sono, niente appoggio`, /Sito A rf/.test(await nomeSito(x, BX)) && tabelle(DX, 'cms_users').length === 1 && tabelle(DX, 'cms_settings').length === 1 && tabelle(DX, 'rip').length === 0, await nomeSito(x, BX));
    await x.goto(BX + '/admin/index.php', { waitUntil: 'networkidle' });
    ok(`g. ${nome}: la dashboard si apre dopo il ripristino`, /Dashboard/.test(await x.locator('body').innerText()) && !(await x.content()).includes('Fatal error'));
    if (nome === 'MariaDB') {
      impostazione(DX, 'sito_nome', 'Sito M rf');
      const copiaM = await preparaCopia(x, BX);
      metti(DC, path.join(DX, 'aggiornamenti/copie', copiaM));
      r = await ripristina(c, BC, copiaM);
      ok('g. copia fatta su MariaDB ripristinata su MySQL 8 (C): riesce e il sito ha i contenuti di M', r.ok && /Sito M rf/.test(await nomeSito(c, BC)), r.titolo);
    }
    await x.context().close();
  }
  if (!altri.length) console.log('(g. saltata: SITO_MARIADB e SITO_MYSQL57 non dati)');

  // ---- Un secondo ripristino mentre uno è in corso, e h. il ripristino interrotto (su C).
  const portaC = +new URL(BC).port;
  const confC = path.join(DC, 'includes/config.local.php'); const confC0 = fs.readFileSync(confC, 'utf8');
  fs.writeFileSync(confC, confC0.replace(/\s*$/, "\ndefine('CMS_RIPRISTINO_PAUSA', 250000);\n")); // un quarto di secondo per istruzione: circa 18 secondi
  await dorme(2500);
  try {
    metti(DC, zipA);
    await c.goto(BC + '/admin/copia-sito.php', { waitUntil: 'networkidle' });
    const tok = await c.locator('input[name=_csrf]').first().getAttribute('value');
    const primaH = { imp: impronte(DC, 'plume_c_'), tab: tabelle(DC, 'plume_c_').length };
    const lento = c.request.post(BC + '/admin/copia-sito.php', { form: { _csrf: tok, azione: 'ripristina', file: copiaA, conferma: '1' }, timeout: 600000 }).catch(e => ({ errore: e.message }));
    await dorme(6000);
    const secondo = await c.request.post(BC + '/admin/copia-sito.php', { form: { _csrf: tok, azione: 'ripristina', file: copiaA, conferma: '1' } });
    ok('un secondo ripristino mentre uno è in corso: rifiutato ("un altro ripristino è già in corso"), senza toccare niente', /un altro ripristino è già in corso/.test(await secondo.text()));
    // E nemmeno un aggiornamento parte (agg_applica prende lo stesso blocco): il pacchetto resta in attesa, niente snapshot.
    fs.mkdirSync(path.join(DC, 'aggiornamenti/pacchetti'), { recursive: true });
    fs.writeFileSync(path.join(DC, 'aggiornamenti/pacchetti/rf-finto.json'), JSON.stringify({ id: 'rf-finto', nome: 'finto rf', stato: 'attesa', applica_dal: '', snapshot: null }));
    const agg = php(DC, `require_once '${DC}/includes/aggiornamenti.php'; $r = agg_applica('rf-finto'); echo json_encode([$r['ok'], $r['riassunto'], agg_leggi_stato('rf-finto')['stato']], JSON_UNESCAPED_UNICODE);`);
    fs.unlinkSync(path.join(DC, 'aggiornamenti/pacchetti/rf-finto.json'));
    ok('un aggiornamento mentre il ripristino è in corso: non parte ("un ripristino da Copia del sito è in corso"), il pacchetto resta in attesa', /^\[false,"un ripristino da Copia del sito è in corso[^"]*","attesa"\]/.test(agg), agg.slice(0, 200));
    ok('h. a metà ripristino ci sono tabelle di appoggio e il marcatore', tabelle(DC, 'rip').length > 0 && fs.existsSync(marcatoreC), tabelle(DC, 'rip').length);
    const pids = pidsServer(portaC);
    for (const pid of pids) { try { process.kill(pid, 'SIGKILL'); } catch (e) {} }
    await lento;
    ok('h. il server php di C ucciso a metà ripristino', pids.length > 0 && await aspettaServer(BC, true), pids);
    ok('h. il sito è intatto: stesse tabelle e stesse impronte di prima; appoggio e marcatore rimasti', impronte(DC, 'plume_c_') === primaH.imp && tabelle(DC, 'plume_c_').length === primaH.tab && tabelle(DC, 'rip').length > 0 && fs.existsSync(marcatoreC));
    fs.writeFileSync(confC, confC0);
    const figlio = spawn('php', ['-S', '127.0.0.1:' + portaC, '-t', DC, path.join(__dirname, 'router-php-s.php')], { cwd: DC, env: { ...process.env, PHP_CLI_SERVER_WORKERS: '4', CMS_CARTELLA: '' }, detached: true, stdio: 'ignore' });
    figlio.unref();
    ok('h. server riacceso: il sito risponde', await aspettaServer(BC) && (await homeTesto(c, BC)).status === 200);
    await c.goto(BC + '/admin/copia-sito.php', { waitUntil: 'networkidle' });
    if (c.url().includes('login.php')) { await entraPannello(c, BC, UTENTE, PASSWORD); await c.goto(BC + '/admin/copia-sito.php', { waitUntil: 'networkidle' }); } // la sessione di prima di solito vale ancora
    const flashH = (await c.locator('.flash').allInnerTexts()).join('\n');
    ok('h. aprendo Copia del sito le tabelle di appoggio rimaste si tolgono, la pagina lo dice, il marcatore sparisce', /Tabelle di lavoro rimaste da un ripristino interrotto, tolte: \d+/.test(flashH) && tabelle(DC, 'rip').length === 0 && !fs.existsSync(marcatoreC), flashH.slice(0, 160));
    metti(DC, zipA);
    r = await ripristina(c, BC, copiaA, true);
    ok('h. il ripristino dopo riesce, senza tabelle di lavoro rimaste', r.ok && tabelle(DC, 'rip').length === 0 && tabelle(DC, 'vec').length === 0 && !fs.existsSync(marcatoreC), r.titolo);
  } finally {
    fs.writeFileSync(confC, confC0);
  }

  fine(err);
  await br.close();
})().catch(async e => { console.error(e); process.exitCode = 2; if (br) await br.close(); });
