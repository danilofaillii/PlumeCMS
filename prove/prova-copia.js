// Copia del sito (1.3.0) come un utente: Impostazioni > Copia del sito, l'avviso dei dati sensibili, Prepara la copia,
// Scarica (lo zip vero: manifest firmato, database, uploads; niente config.local.php, niente file nascosti), una pagina
// nuova che dopo il ripristino non c'e piu, la copia "prima del ripristino", il ripristino da uno zip caricato, e i casi
// da respingere: senza conferma, senza token, senza login, zip che non e una copia, zip slip, file nascosti, copia di un
// altro sito con PHP e SQL estraneo (si ripristina ma il PHP e l'SQL si saltano), file oltre il limite di caricamento.
// uso: node prova-copia.js <base> <cartella CMS installato> <utente> <password> <cartella zip di zip-copie-prova.py> <cartella schermate>
// (il server di prova con il limite di PHP: upload_max_filesize 2M, post_max_size 8M)
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs'), path = require('path');
const [B, CMS, U, P, ZIP, OUT] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); };
const elencoZip = f => JSON.parse(execFileSync('python3', ['-I', '-c', 'import json,sys,zipfile; z=zipfile.ZipFile(sys.argv[1]); print(json.dumps({"nomi": z.namelist(), "manifest": json.loads(z.read("manifest.json"))}))', f]).toString());
(async () => {
  const br = await chromium.launch(); const ctx = await br.newContext({ viewport: { width: 1280, height: 900 }, acceptDownloads: true }); const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('dialog', d => d.accept());
  // Senza login
  const anon = await ctx.request.get(B + '/admin/copia-sito.php', { maxRedirects: 0 });
  ok('senza login: la pagina rimanda al login', [301, 302, 303].includes(anon.status()) && /login/.test(anon.headers()['location'] || ''), anon.status());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', U); await p.fill('#password', P);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/copia-sito.php', { waitUntil: 'networkidle' });
  ok('voce "Copia del sito" nel menu Impostazioni', await p.locator('.admin-sidebar a[href="copia-sito.php"]').count() === 1);
  ok('avviso: la copia contiene dati sensibili, la password del database no', /dati sensibili/.test(await p.locator('.cs-avviso').innerText()) && /config\.local\.php/.test(await p.locator('.cs-avviso').innerText()));
  // Prepara e scarica
  const copiePrima = await p.locator('.cs-table tr').count();
  await Promise.all([p.waitForNavigation({ timeout: 120000 }), p.click('button:has-text("Prepara la copia")')]);
  ok('Prepara la copia: messaggio e copia nuova evidenziata nell\'elenco', /Copia pronta/.test(await p.locator('body').innerText()) && await p.locator('.cs-table tr.cs-nuova').count() === 1);
  ok('la copia nuova risulta "questo sito" (firmata)', await p.locator('.cs-table tr.cs-nuova .cs-tag.si').count() === 1);
  await p.screenshot({ path: `${OUT}/copia-pannello.png`, fullPage: true });
  const [dl] = await Promise.all([p.waitForEvent('download'), p.click('.cs-table tr.cs-nuova button:has-text("Scarica")')]);
  const scaricata = path.join(OUT, 'copia-scaricata.zip'); await dl.saveAs(scaricata);
  const z = elencoZip(scaricata);
  ok('lo zip scaricato: manifest firmato, database, file di uploads/', z.manifest.formato === 'plumecms-copia' && /^[a-f0-9]{64}$/.test(z.manifest.firma) && z.nomi.includes('db.sql.gz') && z.nomi.some(x => x.startsWith('uploads/')), { file: z.nomi.length, tabelle: z.manifest.tabelle });
  ok('lo zip scaricato: niente config.local.php, niente file nascosti, niente fuori dalle cartelle', !z.nomi.some(x => /config\.local\.php|(^|\/)\.|\.\.|^\//.test(x)) && z.nomi.every(x => /^(manifest\.json|db\.sql\.gz|(uploads|temi|moduli)\/)/.test(x)));
  const sulDisco = fs.readdirSync(path.join(CMS, 'aggiornamenti/copie')).filter(f => f.endsWith('.zip'));
  const vietato = await ctx.request.get(B + '/aggiornamenti/copie/' + sulDisco[0]);
  ok('la copia sul server non si apre da internet', vietato.status() === 403 || vietato.status() === 404, vietato.status());
  // Un cambiamento dopo la copia: una pagina nuova
  await p.goto(B + '/admin/editor.php?nuova=1&tipo=pagine', { waitUntil: 'networkidle' });
  const idNuova = new URL(p.url()).searchParams.get('id');
  const esiste = async id => (await ctx.request.get(B + '/admin/editor.php?tipo=pagine&id=' + id)).status() === 200;
  ok('una pagina nuova dopo la copia', !!idNuova && await esiste(idNuova), idNuova);
  // Ripristino senza conferma (il required tolto a mano) e senza token: respinti
  await p.goto(B + '/admin/copia-sito.php', { waitUntil: 'networkidle' });
  const tok = await p.locator('input[name=csrf_token], input[name=_csrf]').first().getAttribute('value');
  const nomeCampo = await p.locator('input[name=csrf_token], input[name=_csrf]').first().getAttribute('name');
  const file = await p.locator('.cs-table tr.cs-nuova input[name=file], .cs-table tr:nth-child(2) input[name=file]').first().getAttribute('value');
  const senzaConferma = await ctx.request.post(B + '/admin/copia-sito.php', { form: { [nomeCampo]: tok, azione: 'ripristina', file } });
  ok('ripristino senza la spunta di conferma: respinto', /spunta la conferma/.test(await senzaConferma.text()) && await esiste(idNuova));
  const senzaToken = await ctx.request.post(B + '/admin/copia-sito.php', { form: { azione: 'ripristina', file, conferma: '1' }, maxRedirects: 0 });
  ok('ripristino senza token CSRF: respinto', senzaToken.status() !== 200 || !/Sito ripristinato/.test(await senzaToken.text()), senzaToken.status());
  ok('dopo i due tentativi la pagina nuova c\'e ancora', await esiste(idNuova));
  // Ripristino vero dall'elenco
  const riga = p.locator('.cs-table tr', { has: p.locator(`input[value="${file}"]`) });
  await riga.locator('summary').click();
  await riga.locator('input[name=conferma]').check();
  await Promise.all([p.waitForNavigation({ timeout: 180000 }), riga.locator('button:has-text("Ripristina adesso")').click()]);
  const testo = await p.locator('body').innerText();
  ok('ripristino dall\'elenco: esito senza errori', /Sito ripristinato dalla copia/.test(testo) && !/ERRORE/.test(testo), testo.slice(0, 300));
  ok('dopo il ripristino la pagina nuova non c\'e piu', !(await esiste(idNuova)));
  ok('prima del ripristino si e fatta da sola una copia del sito', await p.locator('.cs-table td', { hasText: 'prima-del-ripristino' }).count() >= 1);
  await p.screenshot({ path: `${OUT}/copia-ripristinata.png`, fullPage: true });
  const home = await ctx.request.get(B + '/');
  ok('il sito risponde dopo il ripristino', home.status() === 200, home.status());
  // Ripristino da uno zip caricato
  await p.setInputFiles('#cs-carica input[type=file]', scaricata);
  await p.check('#cs-carica input[name=conferma]');
  await Promise.all([p.waitForNavigation({ timeout: 180000 }), p.click('#cs-carica button[type=submit]')]);
  ok('ripristino da uno zip caricato', /Sito ripristinato dalla copia copia-\d{8}-\d{6}-caricata\.zip/.test(await p.locator('body').innerText()));
  // Gli zip da respingere
  const carica = async (nome) => {
    await p.goto(B + '/admin/copia-sito.php', { waitUntil: 'networkidle' });
    await p.setInputFiles('#cs-carica input[type=file]', path.join(ZIP, nome)); await p.check('#cs-carica input[name=conferma]');
    await Promise.all([p.waitForNavigation({ timeout: 120000 }), p.click('#cs-carica button[type=submit]')]);
    return p.locator('body').innerText();
  };
  ok('uno zip che non e una copia: respinto', /non e una copia del sito/.test(await carica('non-copia.zip')));
  ok('zip slip (../fuori.txt): respinto, niente scritto fuori', /Nome non ammesso/.test(await carica('slip.zip')) && !fs.existsSync(path.join(CMS, '..', 'fuori.txt')) && !fs.existsSync(path.join(CMS, 'fuori.txt')));
  ok('percorso assoluto (/tmp/assoluto.txt): respinto', /Nome non ammesso/.test(await carica('slip-assoluto.zip')) && !fs.existsSync('/tmp/assoluto.txt'));
  ok('file nascosto (uploads/.htaccess): respinto', /Nome non ammesso/.test(await carica('nascosto.zip')));
  const htPrima = fs.existsSync(path.join(CMS, 'uploads/.htaccess')) ? fs.readFileSync(path.join(CMS, 'uploads/.htaccess'), 'utf8') : null;
  const est = await carica('estranea.zip');
  ok('copia di un altro sito: si ripristina, segnata come non firmata', /Non firmata da questo sito/.test(est) && /Sito ripristinato/.test(est), est.slice(0, 200));
  ok('copia di un altro sito: il PHP non entra (temi, uploads, .php.jpg)', !fs.existsSync(path.join(CMS, 'temi/prova-x/funzioni.php')) && !fs.existsSync(path.join(CMS, 'uploads/shell.php')) && !fs.existsSync(path.join(CMS, 'uploads/foto.php.jpg')) && fs.existsSync(path.join(CMS, 'uploads/copia-estranea.txt')));
  ok('copia di un altro sito: SQL estraneo saltato (DROP DATABASE, GRANT, senza prefisso, due comandi)', /righe non ammesse saltate/.test(est) && /File saltati/.test(est), (est.match(/Database:[^\n]*/) || [''])[0]);
  const login2 = await ctx.request.get(B + '/admin/index.php', { maxRedirects: 0 });
  ok('dopo la copia estranea gli utenti ci sono ancora (users non cancellata)', login2.status() === 200 || [301, 302].includes(login2.status()));
  ok('uploads/.htaccess resta quello del CMS', (fs.existsSync(path.join(CMS, 'uploads/.htaccess')) ? fs.readFileSync(path.join(CMS, 'uploads/.htaccess'), 'utf8') : null) === htPrima);
  // Oltre il limite: il browser avvisa prima di mandare; senza il browser il server spiega
  await p.goto(B + '/admin/copia-sito.php', { waitUntil: 'networkidle' });
  await p.setInputFiles('#cs-carica input[type=file]', path.join(ZIP, 'grande.zip')); await p.check('#cs-carica input[name=conferma]');
  await p.click('#cs-carica button[type=submit]'); await p.waitForTimeout(500);
  ok('file oltre il limite: il pannello lo dice prima di mandarlo e spiega la cartella aggiornamenti/copie/', /il server ne accetta al massimo/.test(await p.locator('#cs-limite').innerText()) && /aggiornamenti\/copie/.test(await p.locator('#cs-limite').innerText()));
  await p.screenshot({ path: `${OUT}/copia-limite.png` });
  const tok2 = await p.locator(`input[name=${nomeCampo}]`).first().getAttribute('value');
  const grosso = await ctx.request.post(B + '/admin/copia-sito.php', { multipart: { [nomeCampo]: tok2, azione: 'carica', conferma: '1', copia: { name: 'grande.zip', mimeType: 'application/zip', buffer: fs.readFileSync(path.join(ZIP, 'grande.zip')) } } });
  ok('file oltre upload_max_filesize mandato lo stesso: messaggio chiaro', /supera il limite di caricamento/.test(await grosso.text()));
  const enorme = await ctx.request.post(B + '/admin/copia-sito.php', { multipart: { [nomeCampo]: tok2, azione: 'carica', conferma: '1', copia: { name: 'enorme.zip', mimeType: 'application/zip', buffer: Buffer.alloc(9 * 1024 * 1024) } } });
  ok('file oltre post_max_size: messaggio chiaro invece di "token scaduto"', /supera il limite di caricamento/.test(await enorme.text()));
  ok('nessun errore JavaScript', err.length === 0, err);
  console.log(`\n${n - ko} su ${n} OK`); await br.close(); process.exit(ko ? 1 : 0);
})().catch(e => { console.log('ECCEZIONE', e.message.slice(0, 600)); process.exit(1); });
