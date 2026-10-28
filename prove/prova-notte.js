// Pubblicare di notte (1.3.1). Il CMS va alle 02 di notte cambiando il fuso orario (CMS_FUSO in config.local.php, poi rimesso
// com'era): PHP e MySQL seguono lo stesso fuso (includes/db.php), quindi per il sito sono davvero le due. Come un utente:
// "Pubblica" nell'editor e nella scheda, Modifica rapida e Pubblica in massa dall'elenco, e ogni volta la pagina si apre
// come visitatore (senza login). Le bozze "vecchie" sono quelle dei CMS fino alla 1.2.7: data di oggi e ora 06:00.
// Poi la programmazione, che deve restare: un'ora scelta più tardi oggi, una data futura, e l'editor su una pagina programmata.
// uso: node prova-notte.js <base> <cartella CMS installato> <utente> <password> [cartella schermate]
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs'), path = require('path');
const [B, CMS, U, P, OUT] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); };
const CFG = path.join(CMS, 'includes/config.local.php');
const originale = fs.readFileSync(CFG, 'utf8');
// Un fuso di una città in cui adesso sono le 02 (o l'una, o le tre): PHP accetta solo i nomi veri, non Etc/GMT+N.
const oraIn = z => +new Intl.DateTimeFormat('en-GB', { timeZone: z, hour: '2-digit', hourCycle: 'h23' }).format(new Date());
const FUSI = JSON.parse(execFileSync('php', ['-r', 'echo json_encode(timezone_identifiers_list());']).toString());
const FUSO = [2, 3, 1].map(h => FUSI.find(z => z.includes('/') && oraIn(z) === h)).find(Boolean);
const attesa = ms => new Promise(r => setTimeout(r, ms));
const php = (codice) => execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='127.0.0.1';$_SERVER['REQUEST_URI']='/';require '${CMS}/includes/config.php';require_once '${CMS}/includes/db.php';require_once '${CMS}/includes/contenuti.php';${codice}`]).toString();
const riga = (tipo, id) => JSON.parse(php(`$r = contenuti_leggi('${tipo}', ${+id}); echo json_encode(['quando' => contenuti_quando('${tipo}', $r), 'stato' => contenuti_stato('${tipo}', $r), 'percorso' => contenuti_percorso('${tipo}', $r), 'adesso' => date('Y-m-d H:i:s')]);`));
// Una bozza come la lasciava la 1.2.7 creata di notte: oggi alle 06:00. Le pagine con una riga di testo, come scritte nella
// scheda: una pagina con solo la bozza dell'editor non ha niente da mostrare finché l'editor non la pubblica.
const vecchia = (tipo, id) => php(tipo === 'pagine'
  ? `db()->prepare('UPDATE ' . tbl('pages') . " SET status = 'draft', publish_at = CONCAT(CURDATE(), ' 06:00:00'), publish_time = '06:00:00', html = '<p>Scritta di notte.</p>' WHERE id = ?")->execute([${+id}]);`
  : `db()->prepare('UPDATE ' . tbl('articles') . " SET status = 'draft', publish_date = CURDATE(), publish_time = '06:00:00' WHERE id = ?")->execute([${+id}]);`);
const pulisci = () => php(`foreach (['pages', 'articles'] as $t) db()->exec('DELETE FROM ' . tbl($t) . " WHERE slug LIKE 'notte-%' OR slug LIKE 'nuova-pagina%' OR slug LIKE 'nuovo-articolo%'");`);

(async () => {
  fs.writeFileSync(CFG, originale.replace(/define\('CMS_FUSO', '[^']*'\);/, `define('CMS_FUSO', '${FUSO}');`));
  await attesa(2200); // con l'opcache il server rilegge un file cambiato solo dopo 2 secondi
  const br = await chromium.launch();
  try {
    pulisci();
    const ora = php("echo date('H:i');");
    ok(`il sito crede che sia notte, fra l'una e le quattro (${FUSO})`, /^0[1-3]:/.test(ora), ora);

    const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await ctx.newPage();
    p.on('dialog', d => d.accept()); // "Pubblicare questa versione?"
    const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
    const ospite = await (await br.newContext()).newPage(); // il visitatore, senza login
    const visita = async (percorso) => (await ospite.goto(B + percorso, { waitUntil: 'domcontentloaded' })).status();
    await p.goto(B + '/admin/login.php'); await p.fill('#username', U); await p.fill('#password', P);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    ok('login nel pannello', !p.url().includes('login.php'), p.url());

    // Una pagina o un articolo nuovi dall'editor (+ Aggiungi), con lo slug "notte-...".
    const nuovo = async (tipo, slug, eVecchia) => {
      await p.goto(B + '/admin/editor.php?nuova=1&tipo=' + tipo, { waitUntil: 'networkidle' });
      const id = new URL(p.url()).searchParams.get('id');
      php(`db()->prepare('UPDATE ' . tbl('${tipo === 'pagine' ? 'pages' : 'articles'}') . ' SET slug = ? WHERE id = ?')->execute(['${slug}', ${+id}]);`);
      if (eVecchia) { vecchia(tipo, id); await p.reload({ waitUntil: 'networkidle' }); }
      return id;
    };
    const pubblicaEditor = async () => {
      await p.waitForTimeout(600);
      // I campi viaggiano codificati (campi-protetti.js): si riconosce la risposta di Pubblica dal suo JSON.
      const [r] = await Promise.all([p.waitForResponse(async r => r.url().includes('editor-api.php') && (await r.json().catch(() => ({}))).pubblicata === true), p.click('#ed-pubblica')]);
      return (await r.json()).ok === true;
    };

    // 1. Pagina nuova, Pubblica nell'editor: il caso del 28 ottobre alle 00:06.
    let id = await nuovo('pagine', 'notte-editor');
    ok('editor, pagina nuova: Pubblica risponde ok', await pubblicaEditor());
    let x = riga('pagine', id);
    ok('editor, pagina nuova: pubblicata, non programmata', x.stato === 'pubblicato' && x.quando <= x.adesso, x);
    ok('editor, pagina nuova: il visitatore la vede (200)', await visita(x.percorso) === 200, x.percorso);
    if (OUT) { await ospite.screenshot({ path: OUT + '/notte-pagina-editor.png' }); }

    // 2. Bozza vecchia (oggi 06:00), Pubblica nell'editor.
    id = await nuovo('pagine', 'notte-editor-vecchia', true);
    ok('editor, bozza delle 06:00: prima di pubblicare il visitatore non la vede', await visita('/notte-editor-vecchia/') === 404);
    ok('editor, bozza delle 06:00: Pubblica risponde ok', await pubblicaEditor());
    x = riga('pagine', id);
    ok('editor, bozza delle 06:00: esce adesso, non alle 06:00', x.stato === 'pubblicato' && x.quando <= x.adesso, x);
    ok('editor, bozza delle 06:00: il visitatore la vede (200)', await visita(x.percorso) === 200, x.percorso);

    // 3. Articolo, bozza vecchia, Pubblica nell'editor.
    id = await nuovo('articoli', 'notte-articolo', true);
    ok('editor, articolo delle 06:00: Pubblica risponde ok', await pubblicaEditor());
    x = riga('articoli', id);
    ok('editor, articolo delle 06:00: esce adesso', x.stato === 'pubblicato' && x.quando <= x.adesso, x);
    ok('editor, articolo delle 06:00: il visitatore lo vede (200)', await visita(x.percorso) === 200, x.percorso);

    // 3b. Bozza vecchia, Impostazioni dell'editor, Stato Pubblicata.
    id = await nuovo('pagine', 'notte-impostazioni', true);
    await p.waitForTimeout(600);
    await p.click('#ed-apri-impostazioni'); await p.selectOption('#ed-form-impostazioni select[name=stato]', 'published');
    await Promise.all([p.waitForResponse(async r => r.url().includes('editor-api.php') && 'percorso' in await r.json().catch(() => ({}))), p.click('#ed-form-impostazioni button[type=submit]')]);
    x = riga('pagine', id);
    ok('Impostazioni dell\'editor, bozza delle 06:00, Stato Pubblicata: esce adesso', x.stato === 'pubblicato' && x.quando <= x.adesso, x);
    ok('Impostazioni dell\'editor: il visitatore la vede (200)', await visita(x.percorso) === 200, x.percorso);

    // 4. Scheda classica, pagina nuova.
    await p.goto(B + '/admin/contenuto.php?tipo=pagine&scheda=1', { waitUntil: 'networkidle' });
    await p.fill('#sc-title', 'Notte dalla scheda'); await p.fill('#sc-slug', 'notte-scheda');
    await p.click('#te-visuale'); await p.keyboard.type('Scritta e pubblicata alle due di notte.'); // una pagina senza testo il sito non la mostra mai
    await Promise.all([p.waitForNavigation(), p.click('button[data-valore=pubblica]')]);
    id = new URL(p.url()).searchParams.get('id'); x = riga('pagine', id);
    ok('scheda, pagina nuova: pubblicata subito', x.stato === 'pubblicato', x);
    ok('scheda, pagina nuova: il visitatore la vede (200)', await visita(x.percorso) === 200, x.percorso);

    // 5. Scheda classica, bozza vecchia: Pubblica senza toccare data e ora.
    id = await nuovo('pagine', 'notte-scheda-vecchia', true);
    await p.goto(B + '/admin/contenuto.php?tipo=pagine&id=' + id, { waitUntil: 'networkidle' });
    ok('scheda, bozza delle 06:00: la scheda mostra le 06:00', await p.inputValue('input[name=ora]') === '06:00');
    await Promise.all([p.waitForNavigation(), p.click('button[data-valore=pubblica]')]);
    x = riga('pagine', id);
    ok('scheda, bozza delle 06:00: esce adesso', x.stato === 'pubblicato' && x.quando <= x.adesso, x);
    ok('scheda, bozza delle 06:00: il visitatore la vede (200)', await visita(x.percorso) === 200, x.percorso);
    if (OUT) { await p.screenshot({ path: OUT + '/notte-scheda.png' }); }

    // 6. Modifica rapida dall'elenco, bozza vecchia, Stato Pubblicato.
    id = await nuovo('pagine', 'notte-rapida', true);
    await p.goto(B + '/admin/contenuti.php?tipo=pagine', { waitUntil: 'networkidle' });
    await p.hover(`#ct-riga-${id}`); await p.click(`#ct-riga-${id} button:has-text("Modifica rapida")`);
    await p.selectOption('#ct-rapido select[name=stato]', 'pubblicato');
    await Promise.all([p.waitForNavigation(), p.click('#ct-rapido button[type=submit]')]);
    x = riga('pagine', id);
    ok('modifica rapida, bozza delle 06:00: esce adesso', x.stato === 'pubblicato' && x.quando <= x.adesso, x);
    ok('modifica rapida: il visitatore la vede (200)', await visita(x.percorso) === 200, x.percorso);

    // 7. Pagina senza data (publish_at vuoto) in Modifica rapida: niente 06:00 di ripiego.
    id = await nuovo('pagine', 'notte-senza-data');
    php(`db()->prepare('UPDATE ' . tbl('pages') . " SET status = 'draft', publish_at = NULL, html = '<p>Senza data.</p>' WHERE id = ?")->execute([${+id}]);`);
    await p.goto(B + '/admin/contenuti.php?tipo=pagine', { waitUntil: 'networkidle' });
    await p.hover(`#ct-riga-${id}`); await p.click(`#ct-riga-${id} button:has-text("Modifica rapida")`);
    ok('modifica rapida, pagina senza data: ora vuota, non 06:00', await p.inputValue('#ct-rapido input[name=ora]') === '');
    await p.selectOption('#ct-rapido select[name=stato]', 'pubblicato');
    await Promise.all([p.waitForNavigation(), p.click('#ct-rapido button[type=submit]')]);
    x = riga('pagine', id);
    ok('modifica rapida, pagina senza data: il visitatore la vede (200)', x.stato === 'pubblicato' && await visita(x.percorso) === 200, x);

    // 8. Pubblica in massa dall'elenco, una pagina e un articolo vecchi.
    const idP = await nuovo('pagine', 'notte-massa', true), idA = await nuovo('articoli', 'notte-massa-art', true);
    for (const [tipo, i] of [['pagine', idP], ['articoli', idA]]) {
      await p.goto(B + '/admin/contenuti.php?tipo=' + tipo, { waitUntil: 'networkidle' });
      await p.check(`#ct-riga-${i} .ct-sel`);
      await p.selectOption('#ct-azione', 'pubblica');
      await Promise.all([p.waitForNavigation(), p.click('button[data-pn-gruppo="#ct-azione"]')]);
      x = riga(tipo, i);
      ok(`Pubblica in massa (${tipo}), bozza delle 06:00: il visitatore la vede (200)`, x.stato === 'pubblicato' && await visita(x.percorso) === 200, x);
    }

    // 9. Programmare resta possibile. Un'ora scelta più tardi oggi, nella scheda.
    id = await nuovo('pagine', 'notte-programmata', true);
    const dopo = php("echo date('H:i', time() + 7200);");
    await p.goto(B + '/admin/contenuto.php?tipo=pagine&id=' + id, { waitUntil: 'networkidle' });
    await p.fill('input[name=ora]', dopo);
    await Promise.all([p.waitForNavigation(), p.click('button[data-valore=pubblica]')]);
    x = riga('pagine', id);
    ok(`scheda, ora scelta ${dopo}: programmata a quell'ora`, x.stato === 'programmato' && x.quando.slice(11, 16) === dopo, x);
    ok('scheda, ora scelta: il visitatore non la vede ancora (404)', await visita(x.percorso) === 404);
    // L'editor non la fa uscire prima: Pubblica su una pagina programmata aggiorna il contenuto, non la data.
    await p.goto(B + '/admin/editor.php?tipo=pagine&id=' + id, { waitUntil: 'networkidle' });
    ok('editor su una pagina programmata: Pubblica risponde ok', await pubblicaEditor());
    const y = riga('pagine', id);
    ok('editor su una pagina programmata: resta programmata alla stessa ora', y.stato === 'programmato' && y.quando === x.quando, y);
    // Pubblica in massa su una pagina programmata: la prende insieme alle bozze, ma non la fa uscire prima.
    await p.goto(B + '/admin/contenuti.php?tipo=pagine', { waitUntil: 'networkidle' });
    await p.check(`#ct-riga-${id} .ct-sel`); await p.selectOption('#ct-azione', 'pubblica');
    await Promise.all([p.waitForNavigation(), p.click('button[data-pn-gruppo="#ct-azione"]')]);
    const z = riga('pagine', id);
    ok('Pubblica in massa su una pagina programmata: resta programmata alla stessa ora', z.stato === 'programmato' && z.quando === x.quando, z);
    // Una data futura con le 06:00, nella scheda: domani alle 06:00.
    id = await nuovo('pagine', 'notte-domani', true);
    const domani = php("echo date('Y-m-d', strtotime('+1 day'));");
    await p.goto(B + '/admin/contenuto.php?tipo=pagine&id=' + id, { waitUntil: 'networkidle' });
    await p.fill('input[name=data]', domani);
    await Promise.all([p.waitForNavigation(), p.click('button[data-valore=pubblica]')]);
    x = riga('pagine', id);
    ok(`scheda, data scelta ${domani}: programmata per domani alle 06:00`, x.stato === 'programmato' && x.quando === domani + ' 06:00:00', x);
    ok('scheda, data futura: il visitatore non la vede (404)', await visita(x.percorso) === 404);
    // Un articolo con data futura, Pubblica nell'editor: resta per quel giorno.
    id = await nuovo('articoli', 'notte-art-domani');
    php(`db()->prepare('UPDATE ' . tbl('articles') . " SET status = 'draft', publish_date = ?, publish_time = '06:00:00' WHERE id = ?")->execute(['${domani}', ${+id}]);`);
    await p.reload({ waitUntil: 'networkidle' });
    ok('editor, articolo con data futura: Pubblica risponde ok', await pubblicaEditor());
    x = riga('articoli', id);
    ok('editor, articolo con data futura: programmato per quel giorno', x.stato === 'programmato' && x.quando === domani + ' 06:00:00', x);

    ok('nessun errore JavaScript nel pannello', err.length === 0, err);
  } finally {
    pulisci();
    fs.writeFileSync(CFG, originale);
    await br.close();
  }
  console.log(`\n${n - ko} su ${n}`);
  process.exit(ko ? 1 : 0);
})();
