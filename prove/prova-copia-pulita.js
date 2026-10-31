// Copia del sito, ripristino pulito e due installazioni nello stesso database (2.1), come un utente nel browser.
// Due siti nello stesso database: A con il prefisso cms_ e B con cms_b_ (prove/prova-kit.js con PREFISSO=cms_b_).
//  1. La copia di A non prende le tabelle di B (manifest elenco_tabelle e db.sql.gz).
//  2. Dopo la copia, in A nascono file in più: uploads/ (anche in una cartella nuova e con un nome con HTML), un tema
//     non del prodotto, un modulo, un file in più in un tema del prodotto (Aria), un file nascosto, un collegamento verso fuori.
//  3. Ripristino senza "tieni": i file in più vanno in aggiornamenti/scarto/ripristino-<data>/, il pannello li elenca,
//     le cartelle rimaste vuote si tolgono; restano il tema del prodotto, il file nascosto, il collegamento (e ciò a cui punta).
//     Le tabelle di B non cambiano. Lo scarto non si apre dal web.
//  4. Ripristino con "tieni i file in più": il file in più resta dov'è.
//  5. "File messi da parte dai ripristini": l'elenco e Elimina.
//  6. Una copia vecchia (senza elenco_tabelle) fatta con agg_dump_db, che si porta dietro le tabelle di B: il ripristino
//     in A le lascia stare (B non torna indietro).
//  7. Al telefono (390 px) la pagina non scorre di lato. Nessun errore JavaScript.
// uso: node prove/prova-copia-pulita.js <base A> <cartella CMS A> <base B> <cartella CMS B> <utente> <password>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { conta, pagina, entraPannello, largo } = require('./addon-a-comune');
const [BA, DA, BB, DB, UTENTE, PASSWORD] = process.argv.slice(2);
const { ok, fine } = conta();
const php = (dir, codice) => execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='127.0.0.1'; require '${dir}/includes/config.php'; ${codice}`], { encoding: 'utf8' });
const elencoZip = f => JSON.parse(execFileSync('python3', ['-I', '-c', 'import json,sys,zipfile,gzip; z=zipfile.ZipFile(sys.argv[1]); d=gzip.decompress(z.read("db.sql.gz")).decode("utf8","replace"); print(json.dumps({"nomi": z.namelist(), "manifest": json.loads(z.read("manifest.json")), "b": d.count("`cms_b_")}))', f]).toString());
// L'impronta delle tabelle di B: CHECKSUM TABLE di ognuna (dalla parte di B).
const impronteB = () => php(DB, "$o=[]; foreach (db()->query(\"SHOW TABLES LIKE 'cms\\\\_b\\\\_%'\")->fetchAll(PDO::FETCH_COLUMN) as $t) $o[$t]=db()->query('CHECKSUM TABLE `'.$t.'`')->fetch()['Checksum']; ksort($o); echo json_encode($o);");
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'copia-pulita-'));
const XSS = '<img src=x onerror=alert(7)>.txt';

let br;
(async () => {
  br = await chromium.launch();
  const err = [];
  const a = await pagina(br, err);
  const dialoghi = [];
  a.on('dialog', d => dialoghi.push(d.message()));
  const vai = u => a.goto(BA + u, { waitUntil: 'networkidle' });
  await entraPannello(a, BA, UTENTE, PASSWORD);
  // Le tabelle dei due siti nello stesso database.
  const tabelleB = JSON.parse(php(DB, "echo json_encode(db()->query(\"SHOW TABLES LIKE 'cms\\\\_b\\\\_%'\")->fetchAll(PDO::FETCH_COLUMN));"));
  ok('due installazioni nello stesso database: B ha le sue tabelle cms_b_ (settings e users comprese)', tabelleB.includes('cms_b_settings') && tabelleB.includes('cms_b_users'), tabelleB.length);

  // 1. La copia di A.
  await vai('/admin/copia-sito.php');
  await Promise.all([a.waitForNavigation({ timeout: 120000 }), a.click('button:has-text("Prepara la copia")')]);
  const [dl] = await Promise.all([a.waitForEvent('download'), a.click('.cs-table tr.cs-nuova button:has-text("Scarica")')]);
  const zipA = path.join(tmp, 'a.zip'); await dl.saveAs(zipA);
  const z = elencoZip(zipA);
  const nomeCopia = await a.locator('.cs-table tr.cs-nuova input[name=file]').first().getAttribute('value');
  ok('la copia di A non ha le tabelle di B: né nell\'elenco del manifest né nel database', Array.isArray(z.manifest.elenco_tabelle) && z.manifest.elenco_tabelle.includes('settings') && !z.manifest.elenco_tabelle.some(t => t.startsWith('b_')) && z.b === 0, { tabelle: z.manifest.tabelle, b: z.b });

  // 2. I file in più.
  const U = path.join(DA, 'uploads');
  const extra = {
    'uploads/cartella-nuova/foto-nuova.jpg': 'jpg finto', ['uploads/' + XSS]: 'nome con HTML', 'temi/mio-tema/tema.css': 'body{}',
    'moduli/mio-modulo/nota.txt': 'nota', 'uploads/sotto/piu/giu/fondo.txt': 'profondo',
  };
  for (const [f, c] of Object.entries(extra)) { fs.mkdirSync(path.dirname(path.join(DA, f)), { recursive: true }); fs.writeFileSync(path.join(DA, f), c); }
  fs.writeFileSync(path.join(DA, 'temi', 'aria', 'aggiunto-dal-prodotto.css'), '/* arriva con un aggiornamento */');
  fs.writeFileSync(path.join(U, '.nascosto-mio'), 'nascosto');
  const fuori = path.join(tmp, 'fuori.txt'); fs.writeFileSync(fuori, 'fuori dal sito');
  const fuoriDir = path.join(tmp, 'fuori-dir'); fs.mkdirSync(fuoriDir); fs.writeFileSync(path.join(fuoriDir, 'a.txt'), 'fuori');
  fs.symlinkSync(fuori, path.join(U, 'collegamento-fuori.txt'));
  fs.symlinkSync(fuoriDir, path.join(U, 'cartella-fuori'));
  php(DB, "save_setting('prova_due_prefissi', 'B prima');");
  const impB0 = impronteB();

  // 3. Ripristino dall'elenco, senza "tieni".
  await vai('/admin/copia-sito.php');
  const riga = a.locator('.cs-table tr', { has: a.locator(`input[value="${nomeCopia}"]`) });
  await riga.locator('summary').click();
  await riga.locator('input[name=conferma]').check();
  ok('nel ripristino dall\'elenco c\'è la casella "Tieni i file in più", non spuntata', await riga.locator('input[name=tieni]').count() === 1 && !(await riga.locator('input[name=tieni]').isChecked()));
  await Promise.all([a.waitForNavigation({ timeout: 180000 }), riga.locator('button:has-text("Ripristina adesso")').click()]);
  let testo = await a.locator('body').innerText();
  ok('ripristino: esito senza errori', /Sito ripristinato dalla copia/.test(testo) && !/ERRORE/.test(testo), testo.slice(0, 300));
  const scartati = await a.locator('#cs-scartati li').allInnerTexts();
  ok('il pannello dice quanti file in più ha spostato da parte e li elenca', /5 file c'erano nel sito ma non nella copia/.test(await a.locator('#cs-scartati').innerText()) && Object.keys(extra).every(f => scartati.includes(f)), scartati);
  ok('il nome con HTML si vede come testo (nessun alert, nessun <img> nell\'elenco)', scartati.includes('uploads/' + XSS) && await a.locator('#cs-scartati img').count() === 0 && !dialoghi.includes('7'), dialoghi);
  const scarto = fs.readdirSync(path.join(DA, 'aggiornamenti', 'scarto')).filter(x => /^ripristino-\d{8}-\d{6}$/.test(x));
  const S0 = path.join(DA, 'aggiornamenti', 'scarto', scarto[0] || 'x');
  ok('i file in più sono nello scarto, con il loro percorso, e non più nel sito', scarto.length === 1 && Object.keys(extra).every(f => fs.existsSync(path.join(S0, f)) && !fs.existsSync(path.join(DA, f))) && fs.existsSync(path.join(S0, 'LEGGIMI.txt')));
  ok('le cartelle rimaste vuote si tolgono (uploads/cartella-nuova, uploads/sotto, temi/mio-tema, moduli/mio-modulo)', !fs.existsSync(path.join(U, 'cartella-nuova')) && !fs.existsSync(path.join(U, 'sotto')) && !fs.existsSync(path.join(DA, 'temi', 'mio-tema')) && !fs.existsSync(path.join(DA, 'moduli', 'mio-modulo')) && fs.existsSync(U));
  ok('restano: il file in più del tema del prodotto (Aria), il file nascosto, i .htaccess', fs.existsSync(path.join(DA, 'temi', 'aria', 'aggiunto-dal-prodotto.css')) && fs.existsSync(path.join(U, '.nascosto-mio')) && fs.existsSync(path.join(DA, 'temi', '.htaccess')));
  ok('i collegamenti verso fuori restano dove sono, e i file fuori dal sito non si toccano', fs.lstatSync(path.join(U, 'collegamento-fuori.txt')).isSymbolicLink() && fs.lstatSync(path.join(U, 'cartella-fuori')).isSymbolicLink() && fs.readFileSync(fuori, 'utf8') === 'fuori dal sito' && fs.existsSync(path.join(fuoriDir, 'a.txt')));
  ok('i file della copia ci sono tutti (uploads/ del kit)', z.nomi.filter(n => n.startsWith('uploads/') && !n.endsWith('/')).every(n => fs.existsSync(path.join(DA, n))));
  ok('le tabelle di B non sono cambiate (stessa impronta di prima)', impronteB() === impB0);
  ok('il sito B risponde ancora e ha i suoi dati', (await a.request.get(BB + '/')).status() === 200 && php(DB, "echo get_setting('prova_due_prefissi');") === 'B prima');
  const web = await a.request.get(BA + '/aggiornamenti/scarto/' + scarto[0] + '/LEGGIMI.txt');
  const web2 = await a.request.get(BA + '/aggiornamenti/scarto/' + scarto[0] + '/moduli/mio-modulo/nota.txt');
  ok('lo scarto non si apre da internet', [403, 404].includes(web.status()) && [403, 404].includes(web2.status()), [web.status(), web2.status()]);
  ok('la sezione "File messi da parte dai ripristini" elenca lo scarto con quanti file', /5 file/.test(await a.locator('#cs-scarto-elenco').innerText()));
  await a.screenshot({ path: path.join(tmp, 'ripristino-pulito.png'), fullPage: true });

  // 7. Al telefono, con l'esito sullo schermo.
  const tel = await pagina(br, err, 390, 844);
  await entraPannello(tel, BA, UTENTE, PASSWORD);
  await tel.goto(BA + '/admin/copia-sito.php', { waitUntil: 'networkidle' });
  await tel.evaluate(() => document.querySelectorAll('details').forEach(d => { d.open = true; }));
  ok('al telefono Copia del sito non scorre di lato (con le copie, la casella "tieni" e lo scarto)', await largo(tel) <= 0, await largo(tel));

  // 4. Ripristino con "tieni i file in più" (dallo zip caricato).
  fs.writeFileSync(path.join(U, 'tienimi.txt'), 'resta');
  await vai('/admin/copia-sito.php');
  await a.setInputFiles('#cs-carica input[type=file]', zipA);
  await a.check('#cs-carica input[name=conferma]');
  await a.check('#cs-carica input[name=tieni]');
  await Promise.all([a.waitForNavigation({ timeout: 180000 }), a.click('#cs-carica button[type=submit]')]);
  testo = await a.locator('body').innerText();
  ok('con "Tieni i file in più": il file in più resta e il registro lo dice', /Sito ripristinato/.test(testo) && fs.existsSync(path.join(U, 'tienimi.txt')) && /lasciati dove sono/.test(testo) && await a.locator('#cs-scartati').count() === 0);
  // Di nuovo senza spunta: tienimi.txt va nello scarto.
  await vai('/admin/copia-sito.php');
  await a.setInputFiles('#cs-carica input[type=file]', zipA);
  await a.check('#cs-carica input[name=conferma]');
  await Promise.all([a.waitForNavigation({ timeout: 180000 }), a.click('#cs-carica button[type=submit]')]);
  ok('senza la spunta (zip caricato): il file in più va da parte', !fs.existsSync(path.join(U, 'tienimi.txt')) && (await a.locator('#cs-scartati li').allInnerTexts()).includes('uploads/tienimi.txt'));

  // 5. Elimina dallo scarto.
  await vai('/admin/copia-sito.php');
  const prime = await a.locator('#cs-scarto-elenco tr').count();
  await Promise.all([a.waitForNavigation(), a.locator('#cs-scarto-elenco form button').first().click()]);
  ok('"Elimina" toglie una cartella dello scarto, dal disco e dall\'elenco', /eliminati/.test(await a.locator('.flash').first().innerText()) && (await a.locator('#cs-scarto-elenco tr').count()) === prime - 1
    && fs.readdirSync(path.join(DA, 'aggiornamenti', 'scarto')).filter(x => /^ripristino-/.test(x)).length === prime - 2);

  // 6. Una copia vecchia (2.0, senza elenco_tabelle) che si è portata dietro le tabelle di B.
  const vecchia = php(DA, `require_once cms_radice() . '/includes/copia-sito.php';
    $n = 'copia-' . date('Ymd-His', time() + 5) . '-vecchia.zip'; $p = copia_dir() . '/' . $n; $g = copia_dir() . '/.v.sql.gz';
    $d = agg_dump_db($g, DB_PREFIX); $z = new ZipArchive(); $z->open($p, ZipArchive::CREATE);
    $z->addFile($g, 'db.sql.gz'); $z->addFromString('manifest.json', json_encode(['formato' => 'plumecms-copia', 'versione' => 1, 'cms_versione' => '2.0.3', 'creato' => date('Y-m-d H:i:s'), 'sito' => 'http://vecchio', 'prefisso' => DB_PREFIX, 'tabelle' => $d['tabelle'], 'righe' => $d['righe'], 'file' => 0]));
    $z->close(); @unlink($g); echo $n;`).trim();
  const zv = elencoZip(path.join(DA, 'aggiornamenti', 'copie', vecchia));
  ok('la copia vecchia (agg_dump_db con il prefisso cms_) contiene davvero le tabelle di B', zv.b > 0 && !zv.manifest.elenco_tabelle, zv.b);
  php(DB, "save_setting('prova_due_prefissi', 'B dopo');");
  const impB1 = impronteB();
  await vai('/admin/copia-sito.php');
  const rv = a.locator('.cs-table tr', { has: a.locator(`input[value="${vecchia}"]`) });
  await rv.locator('summary').click();
  await rv.locator('input[name=conferma]').check();
  await rv.locator('input[name=tieni]').check();
  await Promise.all([a.waitForNavigation({ timeout: 180000 }), rv.locator('button:has-text("Ripristina adesso")').click()]);
  testo = await a.locator('body').innerText();
  ok('ripristino della copia vecchia: le tabelle dell\'altra installazione si lasciano stare, e il pannello lo dice', /Sito ripristinato/.test(testo) && /altra installazione nello stesso database/.test(testo), (testo.match(/Tabelle di un[^\n]*/) || [''])[0]);
  ok('B non torna indietro: stesse tabelle, stessi dati di prima del ripristino di A', impronteB() === impB1 && php(DB, "echo get_setting('prova_due_prefissi');") === 'B dopo');
  ok('A dopo il ripristino della copia vecchia risponde', (await a.request.get(BA + '/')).status() === 200);

  for (const l of ['collegamento-fuori.txt', 'cartella-fuori', '.nascosto-mio']) try { fs.unlinkSync(path.join(U, l)); } catch (e) {}
  try { fs.unlinkSync(path.join(DA, 'temi', 'aria', 'aggiunto-dal-prodotto.css')); } catch (e) {}
  fine(err);
  await br.close();
})().catch(async e => { console.error(e); process.exitCode = 2; if (br) await br.close(); });
