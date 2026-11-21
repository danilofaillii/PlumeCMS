// Attacchi alla Copia del sito, ripristino pulito e due installazioni nello stesso database (2.1). Ogni OK è un attacco respinto.
// Due siti nello stesso database, A (cms_) e B (cms_b_), come per prove/prova-copia-pulita.js.
//  - le pagine e le azioni nuove (Elimina dei file messi da parte, la casella "tieni") senza login, senza token, da redattore;
//  - nomi dello scarto con "..", percorsi assoluti, byte nulli: non si cancella niente fuori dallo scarto;
//  - lo scarto non si apre dal web;
//  - un collegamento in uploads/ verso includes/ (il config con la password del database) o verso fuori dal sito: il
//    ripristino non sposta né il collegamento né i file a cui porta;
//  - nomi di file con HTML nell'elenco dei file spostati;
//  - una copia caricata (non firmata) con un elenco di tabelle inventato ("b_settings", nomi con apici e "..") e righe per
//    le tabelle dell'altra installazione: le tabelle di B non cambiano;
//  - una copia che dice di avere il prefisso "cms_b" (senza il trattino basso finale) per arrivare alle tabelle di B.
// uso: node prove/attacchi-copia.js <base A> <cartella CMS A> <base B> <cartella CMS B> <utente> <password>
const { chromium, request } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { conta, pagina, entraPannello } = require('./addon-a-comune');
const [BA, DA, BB, DB, UTENTE, PASSWORD] = process.argv.slice(2);
const { ok, fine } = conta();
const php = (dir, codice) => execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='127.0.0.1'; require '${dir}/includes/config.php'; ${codice}`], { encoding: 'utf8' });
const impronteB = () => php(DB, "$o=[]; foreach (db()->query(\"SHOW TABLES LIKE 'cms\\\\_b\\\\_%'\")->fetchAll(PDO::FETCH_COLUMN) as $t) $o[$t]=db()->query('CHECKSUM TABLE `'.$t.'`')->fetch()['Checksum']; ksort($o); echo json_encode($o);");
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'attacchi-copia-'));
const SCARTO = path.join(DA, 'aggiornamenti', 'scarto');
const COPIE = path.join(DA, 'aggiornamenti', 'copie');
const scarti = () => (fs.existsSync(SCARTO) ? fs.readdirSync(SCARTO) : []).filter(x => /^ripristino-/.test(x)).sort();
const RED = 'redattore-copia', REDPW = 'Ceramica-Forno-Copia-2026';

/** Una copia "di un altro sito" (non firmata) scritta a mano: manifest e righe di database a scelta. */
function copiaFinta(nome, manifest, righe, file = {}) {
  const dir = fs.mkdtempSync(path.join(tmp, 'z-'));
  fs.writeFileSync(path.join(dir, 'm.json'), JSON.stringify(manifest));
  fs.writeFileSync(path.join(dir, 'db.sql'), righe.join('\n') + '\n');
  fs.writeFileSync(path.join(dir, 'f.json'), JSON.stringify(file));
  execFileSync('python3', ['-I', '-c', `import sys,zipfile,gzip,json,os
d=sys.argv[1]; z=zipfile.ZipFile(sys.argv[2],'w')
z.writestr('manifest.json', open(os.path.join(d,'m.json')).read())
z.writestr('db.sql.gz', gzip.compress(open(os.path.join(d,'db.sql'),'rb').read()))
for k,v in json.load(open(os.path.join(d,'f.json'))).items(): z.writestr(k, v)
z.close()`, dir, path.join(COPIE, nome)]);
  return nome;
}

let br;
(async () => {
  br = await chromium.launch();
  const err = [];
  const a = await pagina(br, err);
  const dialoghi = [];
  a.on('dialog', d => dialoghi.push(d.message()));
  const vai = u => a.goto(BA + u, { waitUntil: 'networkidle' });
  await entraPannello(a, BA, UTENTE, PASSWORD);
  const flash = async () => (await a.locator('.flash').first().innerText().catch(() => '')).trim();

  // Preparazione: una copia vera di A, poi un file in più e un ripristino, così lo scarto ha una cartella.
  await vai('/admin/copia-sito.php');
  await Promise.all([a.waitForNavigation({ timeout: 120000 }), a.click('button:has-text("Prepara la copia")')]);
  const nomeCopia = await a.locator('.cs-table tr.cs-nuova input[name=file]').first().getAttribute('value');
  const U = path.join(DA, 'uploads');
  fs.writeFileSync(path.join(U, 'in-piu.txt'), 'in più');
  fs.writeFileSync(path.join(U, '"><svg onload=alert(9)>.txt'), 'nome cattivo');
  fs.symlinkSync(path.join(DA, 'includes'), path.join(U, 'verso-includes'));
  fs.symlinkSync(path.join(DA, 'includes', 'config.local.php'), path.join(U, 'config-collegato.php.txt'));
  const fuori = path.join(tmp, 'fuori.txt'); fs.writeFileSync(fuori, 'non toccare');
  fs.symlinkSync(fuori, path.join(U, 'verso-fuori.txt'));
  const ripristina = async (file, tieni = false) => {
    await vai('/admin/copia-sito.php');
    const r = a.locator('.cs-table tr', { has: a.locator(`input[value="${file}"]`) });
    await r.locator('summary').click();
    await r.locator('input[name=conferma]').check();
    if (tieni) await r.locator('input[name=tieni]').check();
    await Promise.all([a.waitForNavigation({ timeout: 180000 }), r.locator('button:has-text("Ripristina adesso")').click()]);
    return a.locator('body').innerText();
  };
  let t = await ripristina(nomeCopia);
  ok('preparazione: ripristino di A riuscito', /Sito ripristinato/.test(t), t.slice(0, 200));

  // 1. Collegamenti: il config con la password del database non si sposta, né il collegamento né il file vero.
  ok('collegamento verso includes/: config.local.php resta al suo posto, il collegamento non si sposta', fs.existsSync(path.join(DA, 'includes', 'config.local.php')) && fs.lstatSync(path.join(U, 'verso-includes')).isSymbolicLink() && fs.lstatSync(path.join(U, 'config-collegato.php.txt')).isSymbolicLink());
  ok('nessun file di includes/ è finito nello scarto', !scarti().some(s => fs.existsSync(path.join(SCARTO, s, 'uploads', 'verso-includes')) || fs.existsSync(path.join(SCARTO, s, 'uploads', 'config-collegato.php.txt'))));
  ok('collegamento verso fuori dal sito: il file fuori non si tocca', fs.readFileSync(fuori, 'utf8') === 'non toccare' && fs.lstatSync(path.join(U, 'verso-fuori.txt')).isSymbolicLink());
  for (const l of ['verso-includes', 'config-collegato.php.txt', 'verso-fuori.txt']) fs.unlinkSync(path.join(U, l));
  // 2. Nomi con HTML.
  ok('un nome di file con HTML nell\'elenco dei file spostati: testo, nessuno script', !dialoghi.includes('9') && await a.locator('#cs-scartati svg').count() === 0 && (await a.locator('#cs-scartati').innerText()).includes('"><svg onload=alert(9)>.txt'), dialoghi);
  const cartella = scarti()[scarti().length - 1];
  ok('preparazione: lo scarto ha una cartella', !!cartella && fs.existsSync(path.join(SCARTO, cartella, 'uploads', 'in-piu.txt')), cartella);

  // 3. Lo scarto dal web.
  for (const u of [`/aggiornamenti/scarto/${cartella}/uploads/in-piu.txt`, `/aggiornamenti/scarto/${cartella}/LEGGIMI.txt`, '/aggiornamenti/scarto/']) {
    const r = await a.request.get(BA + u, { maxRedirects: 0 });
    ok('lo scarto non si apre dal web: ' + u, [403, 404].includes(r.status()), r.status());
  }

  // 4. Elimina senza login, senza token, con GET, da redattore.
  await vai('/admin/copia-sito.php');
  const campo = await a.locator('input[name=csrf_token], input[name=_csrf]').first().getAttribute('name');
  const tok = await a.locator(`input[name=${campo}]`).first().getAttribute('value');
  const fuoriCtx = await request.newContext();
  let r = await fuoriCtx.post(BA + '/admin/copia-sito.php', { form: { azione: 'elimina_scarto', scarto: cartella, [campo]: tok }, maxRedirects: 0 });
  ok('senza login: Elimina dello scarto rimanda al login, niente cancellato', [302, 303].includes(r.status()) && /login/.test(r.headers().location || '') && fs.existsSync(path.join(SCARTO, cartella)), r.status());
  r = await a.request.post(BA + '/admin/copia-sito.php', { form: { azione: 'elimina_scarto', scarto: cartella }, maxRedirects: 0 });
  ok('senza token CSRF: niente cancellato', fs.existsSync(path.join(SCARTO, cartella)), r.status());
  r = await a.request.get(BA + '/admin/copia-sito.php?azione=elimina_scarto&scarto=' + cartella);
  ok('con un GET: niente cancellato', fs.existsSync(path.join(SCARTO, cartella)));
  // Il redattore (creato da Utenti, come un amministratore).
  await vai('/admin/utenti.php');
  if (!(await a.locator('text=' + RED).count())) {
    await a.fill('input[name=username]', RED); await a.fill('input[name=email]', RED + '@esempio.it');
    await a.selectOption('select[name=role]', 'editor'); await a.fill('input[name=password]', REDPW); await a.fill('input[name=password2]', REDPW);
    await Promise.all([a.waitForNavigation(), a.click('form:has(input[name=username]) button.btn-primary')]);
  }
  const red = await pagina(br, err);
  await entraPannello(red, BA, RED, REDPW);
  r = await red.request.get(BA + '/admin/copia-sito.php', { maxRedirects: 0 });
  ok('il redattore non apre Copia del sito', r.status() !== 200 || !/Ripristina da una copia/.test(await r.text()), r.status());
  await red.goto(BA + '/admin/index.php', { waitUntil: 'networkidle' });
  const tokR = await red.locator(`input[name=${campo}]`).first().getAttribute('value').catch(() => '');
  const meta = await red.locator('meta[name=csrf-token]').getAttribute('content').catch(() => '');
  r = await red.request.post(BA + '/admin/copia-sito.php', { form: { azione: 'elimina_scarto', scarto: cartella, [campo]: tokR || meta || '' }, maxRedirects: 0 });
  ok('il redattore non elimina lo scarto', fs.existsSync(path.join(SCARTO, cartella)), r.status());
  r = await red.request.post(BA + '/admin/copia-sito.php', { form: { azione: 'ripristina', file: nomeCopia, conferma: '1', [campo]: tokR || meta || '' }, maxRedirects: 0 });
  ok('il redattore non ripristina', r.status() !== 200 || !/Sito ripristinato/.test(await r.text()), r.status());
  r = await red.request.get(BA + '/admin/oauth-ritorno.php?state=copia-altrove.gdrive.x.1.y&code=z', { maxRedirects: 0 });
  ok('il redattore non apre la pagina di ritorno OAuth', r.status() !== 200, r.status());

  // 5. Nomi dello scarto cattivi: non si cancella niente.
  const copiePrima = fs.readdirSync(COPIE).length;
  for (const n of ['../copie', `${cartella}/../../copie`, '/etc', '..', '.', '', `${cartella}\0`, 'ripristino-20200101-000000', '../scarto/' + cartella, `..\\copie`]) {
    r = await a.request.post(BA + '/admin/copia-sito.php', { form: { azione: 'elimina_scarto', scarto: n, [campo]: tok } });
    ok('nome dello scarto cattivo ' + JSON.stringify(n) + ': respinto, niente cancellato fuori', fs.readdirSync(COPIE).length === copiePrima && fs.existsSync(path.join(DA, 'includes', 'config.local.php'))
      && fs.existsSync(path.join(SCARTO, cartella, 'uploads', 'in-piu.txt')));
  }
  ok('dopo i nomi cattivi la cartella dello scarto vera c\'è ancora: si cancella solo con il suo nome esatto', scarti().includes(cartella));
  r = await a.request.post(BA + '/admin/copia-sito.php', { form: { azione: 'elimina_scarto', scarto: cartella, [campo]: tok } });
  ok('con il nome esatto e il token l\'amministratore la elimina (e solo quella)', !fs.existsSync(path.join(SCARTO, cartella)) && fs.readdirSync(COPIE).length === copiePrima);

  // 6. Una copia non firmata con un elenco di tabelle inventato e righe per le tabelle di B.
  php(DB, "save_setting('attacco_b', 'intatta');");
  const impB = impronteB();
  const nB = JSON.parse(php(DB, "echo json_encode(db()->query('SELECT COUNT(*) FROM ' . tbl('users'))->fetchColumn());"));
  const manifest = { formato: 'plumecms-copia', versione: 1, cms_versione: '2.1.0', creato: '2026-10-31 10:00:00', sito: 'http://cattivo.example', prefisso: 'cms_', tabelle: 2, righe: 2, file: 0,
    elenco_tabelle: ['b_settings', 'b_users', '../x', "users`; DROP TABLE cms_b_users; --", 'b_articles'] };
  const righe = [
    "DROP TABLE IF EXISTS `cms_b_users`;",
    "CREATE TABLE `cms_b_settings_finta` (`k` varchar(10)) ENGINE=InnoDB;",
    "INSERT INTO `cms_b_settings` VALUES (999,'attacco_b','presa','2026-10-31 10:00:00');",
    "INSERT INTO `cms_b_users` VALUES (999,'cattivo','c@c.it','x','C','admin',NULL,NOW(),NULL,0,NULL,NULL,0,NULL,NULL);",
    "DROP TABLE IF EXISTS `cms_b_articles`;",
  ];
  const finta = copiaFinta('copia-20261031-100000-finta.zip', manifest, righe);
  t = await ripristina(finta, true);
  ok('copia con elenco inventato: si ripristina come "altro sito" ma non arriva a B', /Sito ripristinato|Ripristino con errori/.test(t) && /Non firmata/.test(t), (t.match(/Database:[^\n]*/) || [''])[0]);
  ok('le tabelle di B sono intatte (users, settings, articles: stessa impronta)', impronteB() === impB && php(DB, "echo get_setting('attacco_b');") === 'intatta');
  ok('nessun amministratore "cattivo" in B, e B ha ancora i suoi utenti', JSON.parse(php(DB, "echo json_encode(db()->query('SELECT COUNT(*) FROM ' . tbl('users'))->fetchColumn());")) === nB
    && !/cattivo/.test(php(DB, "echo json_encode(db()->query('SELECT username FROM ' . tbl('users'))->fetchAll(PDO::FETCH_COLUMN));")));
  ok('B risponde e il suo pannello apre il login', (await a.request.get(BB + '/')).status() === 200 && (await a.request.get(BB + '/admin/login.php')).status() === 200);

  // 7. Una copia con il prefisso "cms_b" (senza il trattino basso): le sue righe "cms_b_..." diventerebbero "cms__...".
  const finta2 = copiaFinta('copia-20261031-100100-finta.zip', { ...manifest, prefisso: 'cms_b', elenco_tabelle: undefined }, ["INSERT INTO `cms_b_settings` VALUES (998,'attacco_b','presa2','2026-10-31 10:00:00');"]);
  await ripristina(finta2, true);
  ok('copia con prefisso "cms_b": le tabelle di B restano intatte', impronteB() === impB && php(DB, "echo get_setting('attacco_b');") === 'intatta');
  // Il prefisso della copia uguale a quello di B ("cms_b_"): le righe vanno nelle tabelle di A (cms_), mai in quelle di B.
  const finta3 = copiaFinta('copia-20261031-100200-finta.zip', { ...manifest, prefisso: 'cms_b_', elenco_tabelle: ['settings'] }, ["INSERT INTO `cms_b_settings` VALUES (997,'attacco_b_in_a','in A','2026-10-31 10:00:00');"]);
  await ripristina(finta3, true);
  ok('copia "di B" ripristinata in A: scrive in A, B intatto', impronteB() === impB);

  // Il sito A torna come prima (la copia vera).
  t = await ripristina(nomeCopia, true);
  ok('A torna come prima con la sua copia', /Sito ripristinato/.test(t));
  for (const f of ['copia-20261031-100000-finta.zip', 'copia-20261031-100100-finta.zip', 'copia-20261031-100200-finta.zip']) try { fs.unlinkSync(path.join(COPIE, f)); } catch (e) {}
  fine(err);
  await br.close();
})().catch(async e => { console.error(e); process.exitCode = 2; if (br) await br.close(); });
