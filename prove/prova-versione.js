// "C'e una versione nuova" (1.3.0) con due installazioni locali: una fa da sito ufficiale (con il pacchetto finto
// plumecms-1.3.1-aggiornamento.zip nel suo download manager), l'altra e il sito che si aggiorna. Piu un sito ufficiale
// che si comporta male (prove/finto-ufficiale.php). Come un utente: l'avviso nella dashboard (che non aspetta la rete),
// la pagina Aggiornamenti, "Scarica e prepara", il pacchetto in coda, Applica ora, la versione nuova. Poi i casi da
// respingere: http senza la costante delle prove, rete assente, rimandi, sha256 sbagliato, zip su un altro host,
// dimensione bugiarda o troppo grande, JSON enorme, sito lento, senza token, controllo spento.
// uso: node prova-versione.js <base cliente> <cartella CMS cliente> <base ufficiale> <cartella CMS ufficiale> <base finto> <file FINTO_CONTA del finto> <utente> <password> <cartella schermate>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs'), path = require('path'), crypto = require('crypto');
const [B, CMS, UFF, CMSU, FINTO, LOGU, U, P, OUT] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); };
const CFG = path.join(CMS, 'includes/config.local.php');
const base = fs.readFileSync(CFG, 'utf8').replace(/\n\/\/ prova-versione[\s\S]*$/, '\n');
// Dopo ogni cambio del config si aspettano 2,2 secondi: con l'opcache il server rilegge un file cambiato solo dopo 2 secondi.
const attesa = ms => new Promise(r => setTimeout(r, ms));
const config = async (url, http = true) => { fs.writeFileSync(CFG, base + `\n// prova-versione\n${url ? `define('CMS_AGGIORNAMENTI_URL', '${url}');\n` : ''}${http ? "define('CMS_AGGIORNAMENTI_HTTP_PROVA', true);\n" : ''}`); await attesa(2200); };
const php = (codice) => execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='127.0.0.1';$_SERVER['REQUEST_URI']='/';require '${CMS}/includes/config.php';require_once '${CMS}/includes/db.php';${codice}`]).toString();
const svuota = () => php("save_setting('versione_controllo_cache', '');");
const richiesteUff = () => fs.existsSync(LOGU) ? fs.readFileSync(LOGU, 'utf8').length : 0; // FINTO_CONTA: un carattere per richiesta
(async () => {
  // Il pacchetto finto 1.3.1: scrive solo VERSIONE.
  const pacchetto = path.join(OUT, 'plumecms-1.3.1-aggiornamento.zip');
  execFileSync('python3', ['-I', '-c', `import json,zipfile,sys
z=zipfile.ZipFile(sys.argv[1],'w',zipfile.ZIP_DEFLATED)
z.writestr('manifest.json', json.dumps({'nome':'plumeCMS 1.3.1 (prova)','descrizione':'Pacchetto di prova: cambia solo VERSIONE','file':[{'da':'cms/VERSIONE','a':'new/VERSIONE'}]}))
z.writestr('cms/VERSIONE','1.3.1\\n'); z.close()`, pacchetto]);
  fs.mkdirSync(path.join(CMSU, 'uploads/scaricabili'), { recursive: true });
  fs.copyFileSync(pacchetto, path.join(CMSU, 'uploads/scaricabili/plumecms-1.3.1-aggiornamento.zip'));
  const shaVero = crypto.createHash('sha256').update(fs.readFileSync(pacchetto)).digest('hex');
  // L'endpoint pubblico del sito ufficiale
  const req = await (await chromium.launch()).newContext();
  const r1 = await req.request.get(UFF + '/api/versione.php?serie=plumecms-ultima-aggiornamento');
  const j1 = await r1.json().catch(() => ({}));
  ok('api/versione.php: versione, nome, sha256, dimensione e indirizzo dell\'ultimo file della serie', r1.status() === 200 && j1.versione === '1.3.1' && j1.nome === 'plumecms-1.3.1-aggiornamento.zip' && j1.sha256 === shaVero && j1.dimensione === fs.statSync(pacchetto).size && j1.url.endsWith('/scarica/plumecms-1.3.1-aggiornamento/'), j1);
  const r2 = await req.request.get(UFF + '/api/versione.php?serie=../../etc');
  const r3 = await req.request.get(UFF + '/api/versione.php?serie=nessuna-ultima');
  const r4 = await req.request.post(UFF + '/api/versione.php?serie=plumecms-ultima-aggiornamento');
  ok('api/versione.php: serie non valida 400, inesistente 404, POST 405', r2.status() === 400 && r3.status() === 404 && r4.status() === 405, [r2.status(), r3.status(), r4.status()]);
  const scar = await req.request.get(j1.url);
  ok('il file annunciato si scarica ed e quello (sha256)', scar.status() === 200 && crypto.createHash('sha256').update(await scar.body()).digest('hex') === shaVero);

  const br = await chromium.launch(); const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('dialog', d => d.accept());
  await config(FINTO + '/buono'); svuota();
  await p.goto(B + '/admin/login.php'); await p.fill('#username', U); await p.fill('#password', P);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  // Dashboard: niente cache, il browser chiede il controllo dopo il caricamento; l'avviso compare
  svuota(); const prima = richiesteUff();
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' }); await p.waitForTimeout(1500);
  ok('dashboard: "plumeCMS 1.3.1 e disponibile" con il link ad Aggiornamenti', await p.locator('#db-versione').isVisible() && /1\.3\.1/.test(await p.locator('#db-versione').innerText()) && await p.locator('#db-versione a[href^="aggiornamenti.php"]').count() === 1);
  await p.screenshot({ path: `${OUT}/versione-dashboard.png` });
  const dopo1 = richiesteUff();
  for (let i = 0; i < 3; i++) { await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' }); await p.goto(B + '/admin/aggiornamenti.php', { waitUntil: 'networkidle' }); }
  ok('una sola richiesta al sito ufficiale per tre giri fra dashboard e Aggiornamenti (cache di un giorno)', dopo1 - prima === 1 && richiesteUff() === dopo1, { prima, dopo1, ora: richiesteUff() });
  ok('dashboard: l\'avviso resta (dalla cache), senza un\'altra richiesta', await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' }) && await p.locator('#db-versione').isVisible());
  // Da qui il sito ufficiale vero (la seconda installazione)
  await config(UFF); php(`require_once '${CMS}/includes/versione-nuova.php'; versione_controlla(true);`);
  // Aggiornamenti: Scarica e prepara
  await p.goto(B + '/admin/aggiornamenti.php', { waitUntil: 'networkidle' });
  ok('Aggiornamenti: la scheda della versione nuova con "Scarica e prepara"', /1\.3\.1 è disponibile/.test(await p.locator('#agg-versione').innerText()) && await p.locator('#agg-versione button:has-text("Scarica e prepara")').count() === 1);
  await p.screenshot({ path: `${OUT}/versione-aggiornamenti.png` });
  // Senza token: respinto
  const inCoda = () => fs.readdirSync(path.join(CMS, 'aggiornamenti/pacchetti')).filter(f => f.endsWith('.zip')).length;
  const codaPrima = inCoda();
  const senza = await ctx.request.post(B + '/admin/aggiornamenti.php', { form: { action: 'versione_prepara' }, maxRedirects: 0 });
  ok('Scarica e prepara senza token CSRF: respinto, niente in coda', senza.status() === 403 && inCoda() === codaPrima, senza.status());
  await Promise.all([p.waitForNavigation({ timeout: 120000 }), p.click('#agg-versione button:has-text("Scarica e prepara")')]);
  const tAgg = await p.locator('body').innerText();
  ok('Scarica e prepara: scaricato e controllato, il pacchetto e nella lista in attesa', /scaricato e controllato/.test(tAgg) && await p.locator('.agg-table tr', { hasText: 'plumeCMS 1.3.1 (prova)' }).locator('.agg-stato.in_attesa').count() === 1);
  ok('il sito non e ancora cambiato (versione 1.3.0)', fs.readFileSync(path.join(CMS, 'VERSIONE'), 'utf8').trim() === '1.3.0');
  ok('un secondo "Scarica e prepara" non lo mette due volte', await p.locator('#agg-versione').innerText().then(t => /già scaricato/.test(t)) && await p.locator('#agg-versione button:has-text("Scarica e prepara")').count() === 0);
  await p.screenshot({ path: `${OUT}/versione-in-coda.png`, fullPage: true });
  const rigaP = p.locator('.agg-table tr', { hasText: 'plumeCMS 1.3.1 (prova)' });
  await Promise.all([p.waitForNavigation({ timeout: 120000 }), rigaP.locator('button:has-text("Applica ora")').click()]);
  ok('Applica ora: il sito e alla 1.3.1', /Aggiornamento applicato/.test(await p.locator('body').innerText()) && fs.readFileSync(path.join(CMS, 'VERSIONE'), 'utf8').trim() === '1.3.1');
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' }); await p.waitForTimeout(800);
  ok('dopo l\'aggiornamento la dashboard non mostra piu l\'avviso', !(await p.locator('#db-versione').isVisible()));
  fs.writeFileSync(path.join(CMS, 'VERSIONE'), '1.3.0\n');
  // I casi da respingere: Controlla adesso, poi Scarica e prepara
  const controlla = async () => { await p.goto(B + '/admin/aggiornamenti.php', { waitUntil: 'networkidle' }); await Promise.all([p.waitForNavigation({ timeout: 60000 }), p.click('#agg-versione button:has-text("Controlla adesso")')]); return p.locator('body').innerText(); };
  const prepara = async () => { if (!await p.locator('#agg-versione button:has-text("Scarica e prepara")').count()) return 'nessun pulsante'; await Promise.all([p.waitForNavigation({ timeout: 120000 }), p.click('#agg-versione button:has-text("Scarica e prepara")')]); return p.locator('body').innerText(); };
  const pacchetti = () => fs.readdirSync(path.join(CMS, 'aggiornamenti/pacchetti')).filter(f => f.endsWith('.zip')).length;
  await config(UFF, false);
  ok('http senza la costante delle prove: non si chiama (serve https)', /serve https/.test(await controlla()));
  await config('http://127.0.0.1:9');
  const t0 = Date.now(); const tRete = await controlla();
  ok('rete assente: messaggio chiaro, in pochi secondi, il pannello funziona', /non risponde/.test(tRete) && Date.now() - t0 < 12000 && await p.locator('.admin-sidebar').count() === 1, Date.now() - t0);
  const dash = await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('rete assente: la dashboard si apre lo stesso, senza avviso', dash.status() === 200 && !(await p.locator('#db-versione').isVisible()));
  const casi = [['redirect', 'controlla', /rimando/], ['enorme', 'controlla', /più grande del previsto|piu grande del previsto/], ['lento', 'controlla', /non risponde|troppo lento/],
    ['host', 'controlla', /altro sito/], ['grande', 'controlla', /Dimensione del file non ammessa/], ['http', 'controlla', /altro sito/],
    ['sha', 'prepara', /non corrisponde all'impronta/], ['corto', 'prepara', /più grande del previsto|piu grande del previsto/], ['zipredirect', 'prepara', /rimando/]];
  for (const [modo, cosa, atteso] of casi) {
    await config(FINTO + '/' + modo);
    const prima = pacchetti();
    let t = await controlla();
    if (cosa === 'prepara') t = await prepara();
    ok(`sito ufficiale "${modo}": respinto, niente in coda`, atteso.test(t) && pacchetti() === prima, (t.match(/(Controllo della versione[^\n]*|[^\n]*(non corrisponde|rimando|grande|altro sito)[^\n]*)/) || [''])[0].slice(0, 160));
  }
  // Il controllo si spegne
  await config(FINTO + '/buono'); svuota();
  await p.goto(B + '/admin/aggiornamenti.php', { waitUntil: 'networkidle' }); await p.waitForTimeout(1200);
  await Promise.all([p.waitForNavigation(), p.locator('#agg-versione input[name=attivo]').uncheck()]);
  ok('spento da Aggiornamenti: messaggio e casella vuota', /Controllo automatico della versione spento/.test(await p.locator('body').innerText()) && !(await p.locator('#agg-versione input[name=attivo]').isChecked()));
  svuota(); const pr = richiesteUff();
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' }); await p.waitForTimeout(1200);
  ok('spento: la dashboard non chiede niente e non mostra avvisi', richiesteUff() === pr && !(await p.locator('#db-versione').isVisible()));
  await p.goto(B + '/admin/aggiornamenti.php', { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.locator('#agg-versione input[name=attivo]').check()]);
  await config(null, false); svuota();
  ok('nessun errore JavaScript', err.length === 0, err);
  console.log(`\n${n - ko} su ${n} OK`); await br.close(); process.exit(ko ? 1 : 0);
})().catch(e => { console.log('ECCEZIONE', e.message.slice(0, 600)); process.exit(1); });
