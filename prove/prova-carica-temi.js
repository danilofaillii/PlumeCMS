// Caricare, scaricare ed eliminare i temi dalla pagina Editor > Temi (fase 6, 27 ottobre 2026), come un utente, più gli
// zip malevoli che il pannello deve rifiutare. Servono gli zip di prove/zip-temi-prova.py e i temi di partenza in temi/.
// uso: node prova-carica-temi.js <base> <cartella CMS installato> <utente> <password> <cartella degli zip di prova>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, ZIP] = process.argv.slice(2);
const cli = (...a) => execFileSync('php', [path.join(__dirname, 'tema-cli.php'), DIR, ...a], { encoding: 'utf8' });
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 240) : '')); };
const zipDentro = (f) => execFileSync('python3', ['-I', '-c', 'import sys,zipfile;print("\\n".join(zipfile.ZipFile(sys.argv[1]).namelist()))', f], { encoding: 'utf8' }).trim().split('\n');
const leggiZip = (f, nome) => execFileSync('python3', ['-I', '-c', 'import sys,zipfile;sys.stdout.write(zipfile.ZipFile(sys.argv[1]).read(sys.argv[2]).decode())', f, nome], { encoding: 'utf8' });

(async () => {
  cli('disattiva'); cli('ripristina-stile'); cli('finestra', 'chiudi');
  for (const s of ['nuvola', 'tuono', 'senza-cartella', 'il-mio-aspetto']) fs.rmSync(path.join(DIR, 'temi', s), { recursive: true, force: true });
  const br = await chromium.launch();
  const ctx = await br.newContext({ acceptDownloads: true, viewport: { width: 1360, height: 900 } }); const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('dialog', d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/temi.php', { waitUntil: 'networkidle' });

  // 1. La protezione della cartella
  ok('aprendo la pagina si scrive temi/.htaccess', cli('file').includes('temi/.htaccess'));
  const ht = fs.readFileSync(path.join(DIR, 'temi', '.htaccess'), 'utf8');
  ok('temi/.htaccess vieta PHP e JSON, maiuscole comprese', /\(\?i\)/.test(ht) && /php/.test(ht) && /json/.test(ht) && /Require all denied/.test(ht));

  const carica = async (file, sostituisci = false) => {
    await p.goto(B + '/admin/temi.php', { waitUntil: 'networkidle' });
    await p.setInputFiles('#carica input[type=file]', path.join(ZIP, file));
    if (sostituisci) await p.check('#carica input[name=sostituisci]');
    await Promise.all([p.waitForNavigation(), p.click('#carica button:has-text("Carica")')]);
    return (await p.locator('.flash').first().innerText().catch(() => '')).trim();
  };

  // 2. Scaricare un tema
  const [dl] = await Promise.all([p.waitForEvent('download'), p.click('#tema-aria a:has-text("Scarica")')]);
  const ariaZip = path.join(ZIP, 'aria-scaricato.zip'); await dl.saveAs(ariaZip);
  const voci = zipDentro(ariaZip);
  ok('Scarica: zip del tema con la sua cartella', voci.includes('aria/tema.json') && voci.includes('aria/anteprima.png') && voci.every(v => v.startsWith('aria/')), voci);
  ok('Scarica: niente file nascosti nello zip', voci.every(v => !/(^|\/)\./.test(v)));

  // 3. Caricare: lo stesso tema senza e con "Sostituisci"
  let m = await carica('aria-scaricato.zip');
  ok('stesso nome senza Sostituisci: rifiutato con la spiegazione', /Esiste già un tema nella cartella temi\/aria/.test(m), m);
  m = await carica('aria-scaricato.zip', true);
  ok('stesso nome con Sostituisci: caricato, copia della versione di prima', /sostituito in temi\/aria/.test(m) && /temi-precedenti\/aria-/.test(m), m);
  ok('la copia di prima esiste davvero', cli('file').split('\n').some(r => /aggiornamenti\/temi-precedenti\/aria-\d{8}-\d{6}$/.test(r)));

  // 4. Un tema nuovo, senza PHP; uno con tema.json nella radice dello zip
  m = await carica('buono.zip');
  ok('tema nuovo senza PHP: caricato e non attivo', /caricato in temi\/nuvola/.test(m) && await p.locator('#tema-nuvola').count() === 1 && await p.locator('#tema-nuvola .badge', { hasText: 'attivo' }).count() === 0, m);
  m = await carica('radice.zip');
  ok('tema.json nella radice dello zip: cartella da "slug"', /temi\/senza-cartella/.test(m), m);

  // 5. PHP: senza firma no, con la finestra sì, firmato sì, firmato e poi manomesso no
  m = await carica('con-php.zip');
  ok('tema con PHP non firmato, finestra chiusa: rifiutato', /contiene codice PHP/.test(m) && /firmato/.test(m) && /finestra/.test(m) && await p.locator('#tema-tuono').count() === 0, m);
  cli('finestra', 'apri');
  m = await carica('con-php.zip');
  ok('tema con PHP con la finestra aperta: caricato', /caricato in temi\/tuono.*con PHP/.test(m), m);
  cli('finestra', 'chiudi');
  fs.rmSync(path.join(DIR, 'temi', 'tuono'), { recursive: true, force: true });
  const chiave = cli('chiave');
  execFileSync('python3', [path.join(__dirname, '..', 'tools', 'pacchetto.py'), '--rifirma', path.join(ZIP, 'con-php.zip')], { env: { ...process.env, PACCHETTO_CHIAVE: chiave }, encoding: 'utf8' });
  m = await carica('con-php-firmato.zip');
  ok('tema con PHP firmato con la chiave del sito (tools/pacchetto.py --rifirma): caricato', /caricato in temi\/tuono.*con PHP, firmato/.test(m), m);
  ok('manifest.json della firma non installato', !cli('file').includes('temi/tuono/manifest.json'));
  // Manomesso: si cambia funzioni.php dopo la firma.
  execFileSync('python3', ['-I', '-c', `
import sys, zipfile
a, b = sys.argv[1], sys.argv[2]
with zipfile.ZipFile(a) as zi, zipfile.ZipFile(b, 'w') as zo:
    for n in zi.namelist():
        d = zi.read(n)
        if n.endswith('funzioni.php'): d = b"<?php system('id'); return [];"
        zo.writestr(n, d)`, path.join(ZIP, 'con-php-firmato.zip'), path.join(ZIP, 'manomesso.zip')]);
  m = await carica('manomesso.zip', true);
  ok('tema firmato e poi manomesso: rifiutato', /firma del tema non corrisponde/.test(m), m);
  ok('dopo il rifiuto il tema di prima resta com\'era', !fs.readFileSync(path.join(DIR, 'temi', 'tuono', 'funzioni.php'), 'utf8').includes('system'));

  // 6. Gli zip che non devono passare
  for (const [f, attesa, cosa] of [
    ['esce.zip', /Nome non ammesso nello zip/, 'un file con ../ che esce dalla cartella'],
    ['nascosto.zip', /Nome di file non ammesso/, 'un .htaccess nascosto'],
    ['user-ini.zip', /Nome di file non ammesso/, 'un .user.ini nascosto'],
    ['svg.zip', /Tipo di file non ammesso.*logo\.svg/, 'un SVG (può contenere script)'],
    ['phtml.zip', /Tipo di file non ammesso.*shell\.phtml/, 'un .phtml'],
    ['senza-tema-json.zip', /non trovo tema\.json/, 'uno zip senza tema.json'],
    ['due-cartelle.zip', /non trovo tema\.json/, 'due temi nello stesso zip'],
    ['bomba.zip', /compresso in modo sospetto/, 'uno zip bomba (9 MB di spazi in pochi byte)'],
    ['json-rotto.zip', /tema\.json non è un JSON valido/, 'un tema.json rotto'],
  ]) {
    const prima = cli('file');
    m = await carica(f);
    ok(`rifiutato: ${cosa}`, attesa.test(m), m);
    ok(`dopo il rifiuto di ${f} nessun file nuovo`, cli('file') === prima);
  }
  ok('nessun file finito fuori da temi/ (../includes/fuga.php)', !fs.existsSync(path.join(DIR, 'includes', 'fuga.php')));
  ok('nessuna cartella provvisoria rimasta', !fs.existsSync(path.join(DIR, 'aggiornamenti', 'temi-nuovi')) || fs.readdirSync(path.join(DIR, 'aggiornamenti', 'temi-nuovi')).length === 0);

  // 7. Elimina: un tema non in uso sì (con copia), il tema in uso no
  await p.goto(B + '/admin/temi.php', { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.click('#tema-nuvola button:has-text("Elimina")')]);
  m = (await p.locator('.flash').first().innerText().catch(() => '')).trim();
  ok('Elimina: tema tolto, copia tenuta', /temi-precedenti\/nuvola-/.test(m) && await p.locator('#tema-nuvola').count() === 0, m);
  cli('attiva', 'aria');
  await p.goto(B + '/admin/temi.php', { waitUntil: 'networkidle' });
  ok('il tema in uso non ha il pulsante Elimina', await p.locator('#tema-aria button:has-text("Elimina")').count() === 0);
  const token = await p.locator('input[name=_csrf]').first().inputValue();
  await p.request.post(B + '/admin/temi.php', { form: { _csrf: token, azione: 'elimina', tema: 'aria' } });
  ok('Elimina del tema in uso chiesto a mano: rifiutato', fs.existsSync(path.join(DIR, 'temi', 'aria', 'tema.json')));

  // 8. L'aspetto di adesso come tema, e ritorno
  const [dl2] = await Promise.all([p.waitForEvent('download'), p.click('a:has-text("Scarica l\'aspetto di adesso come tema")')]);
  const mioZip = path.join(ZIP, 'il-mio-aspetto.zip'); await dl2.saveAs(mioZip);
  const mio = zipDentro(mioZip);
  ok('aspetto di adesso: tema.json, stile.json, modelli.json, tema.css', ['il-mio-aspetto/tema.json', 'il-mio-aspetto/stile.json', 'il-mio-aspetto/modelli.json', 'il-mio-aspetto/tema.css'].every(v => mio.includes(v)), mio);
  const mod = leggiZip(mioZip, 'il-mio-aspetto/modelli.json');
  ok('aspetto di adesso: il menu scritto per posizione, non per numero', mod.includes('posizione:principale'));
  const tj = JSON.parse(leggiZip(mioZip, 'il-mio-aspetto/tema.json'));
  ok('aspetto di adesso: colori delle infografiche del tema attivo', tj.infografiche && tj.infografiche.voci && tj.infografiche.voci.rosso === '#e11d48', tj.infografiche);
  const stile = JSON.parse(leggiZip(mioZip, 'il-mio-aspetto/stile.json'));
  ok('aspetto di adesso: lo stile di Aria (primario blu)', stile.colori.sistema.primario === '#2563eb', stile.colori.sistema.primario);
  cli('disattiva'); cli('ripristina-stile');
  fs.copyFileSync(mioZip, path.join(ZIP, 'mio.zip'));
  m = await carica('mio.zip');
  ok('l\'aspetto scaricato si ricarica come tema', /temi\/il-mio-aspetto/.test(m), m);
  cli('attiva', 'il-mio-aspetto');
  const sito = await ctx.newPage(); await sito.goto(B + '/', { waitUntil: 'networkidle' });
  ok('attivato: il sito torna con l\'aspetto salvato (sfondo bianco, menu presente)', await sito.evaluate(() => getComputedStyle(document.body).backgroundColor) === 'rgb(255, 255, 255)' && await sito.locator('header nav a').count() >= 3);

  ok('nessun errore JavaScript', err.length === 0, err);
  cli('disattiva'); cli('ripristina-stile'); cli('finestra', 'chiudi');
  for (const s of ['tuono', 'senza-cartella', 'il-mio-aspetto']) fs.rmSync(path.join(DIR, 'temi', s), { recursive: true, force: true });
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
