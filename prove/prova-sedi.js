// Le sedi e la SEO geografica (2.27.0), come le userebbe una persona: nel pannello (SEO e AEO, Sedi) crea due sedi, una dal
// tipo in elenco e una con "Altro, lo scrivo io" ("centro di meditazione" -> "Centro di meditazione"); cerca le coordinate con il
// servizio finto (prove/nominatim-finto.php, dal server), sposta il segnaposto sulla mini mappa (le tessere di OpenStreetMap sono
// finte: la prova risponde lei alle richieste e ne controlla gli indirizzi z/x/y); salva con "Crea la pagina della sede".
// Poi il sito: la pagina della sede (200, mappa, orari, contatti, Come arrivare), il JSON-LD di home, di una pagina e della
// pagina della sede (due LocalBusiness con i campi, mainEntity, areaServed dei servizi), il titolo con %citta%, la sitemap, i
// widget Sedi e Dati dell'attività sul sito e nell'editor (prove/pagina-sedi.php), Mappa, Orari e Barra "dalla sede",
// l'eliminazione di una sede con la pagina che resta, il redattore senza accesso, 1280 e 390 px senza scorrimento di lato.
//
// Serve un sito installato (anche col kit Attività), il servizio finto acceso e nel config.local.php del sito:
//   define('CMS_NOMINATIM_URL', 'http://127.0.0.1:<porta del finto>/search'); define('CMS_NOMINATIM_PROVA', true);
// uso: node prove/prova-sedi.js <base> <cartella CMS> <utente> <password> [cartella foto]
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, FOTO] = process.argv.slice(2);
if (!B || !DIR || !UTENTE || !PASSWORD) { console.error('uso: node prove/prova-sedi.js <base> <cartella CMS> <utente> <password> [cartella foto]'); process.exit(2); }
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? '  ' + JSON.stringify(extra).slice(0, 300) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, 'sedi-cli.php'), DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }).trim().split('\n').pop());
const PNG1 = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', 'base64');
const foto = async (p, nome) => { if (FOTO) await p.screenshot({ path: path.join(FOTO, nome + '.png'), fullPage: true }); };
const ld = async (p) => (await p.locator('script[type="application/ld+json"]').evaluateAll(l => l.map(s => { try { return JSON.parse(s.textContent); } catch (e) { return null; } }))).filter(Boolean);
const grafo = async (p, u) => { await p.goto(B + u, { waitUntil: 'networkidle' }); const g = (await ld(p)).find(x => x && x['@graph']); return g ? g['@graph'] : []; };
const orgDi = (g) => g.find(x => x['@id'] && /#organizzazione$/.test(x['@id']));
const entra = async (br, utente, password) => {
  const c = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await c.newPage();
  pnConferme.on(p, d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', utente); await p.fill('#password', password);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  return { c, p, dentro: !p.url().includes('login.php') };
};

(async () => {
  const br = await chromium.launch();
  // Pulizia: nessuna sede, niente pagine di prova delle sedi.
  cli('azzera');
  for (const slug of ['prova-sedi']) cli('sql', `DELETE FROM {p}pages WHERE slug = '${slug}'`);
  cli('redattore', 'elimina');
  const admin = await entra(br, UTENTE, PASSWORD);
  ok('login amministratore', admin.dentro, admin.p.url());
  const p = admin.p;
  const err = []; p.on('pageerror', e => err.push(p.url() + ' ' + e.message.slice(0, 160)));
  // Le tessere di OpenStreetMap: risposte finte, indirizzi registrati.
  const tessere = [];
  await p.route('https://tile.openstreetmap.org/**', r => { tessere.push(r.request().url()); r.fulfill({ status: 200, contentType: 'image/png', body: PNG1 }); });

  // === 1. La pagina Sedi vuota, dal menu
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  const vociMenu = await p.locator('.admin-sidebar a[href="sedi.php"]').count();
  ok('il menu SEO e AEO ha la voce Sedi', vociMenu >= 1);
  await p.goto(B + '/admin/seo.php?scheda=schema', { waitUntil: 'networkidle' });
  ok('la fila delle schede di SEO e AEO ha Sedi', await p.locator('.seo-schede a[href="sedi.php"]').count() === 1);
  const r1 = await p.goto(B + '/admin/sedi.php', { waitUntil: 'networkidle' });
  ok('sedi.php si apre (200) senza sedi, con lo stato vuoto che dice cosa fare', r1.status() === 200 && /Ancora nessuna sede/.test(await p.locator('.empty-state').innerText()));
  ok('nessuna tessera chiesta finché le coordinate non ci sono (mini mappa nascosta)', tessere.length === 0 && await p.locator('#sd-mappa').isHidden());
  ok('la casella "Che attività è" è nascosta finché il tipo non è Altro', await p.locator('#sd-tipo-testo-riga').isHidden());

  // === 2. La prima sede: dal tipo in elenco, coordinate dal servizio finto, segnaposto spostato, pagina creata
  await p.fill('#sd-nome', 'Sede di Bologna');
  await p.selectOption('#sd-tipo', 'ristorante');
  await p.fill('#sd-descrizione', 'La sede storica, in centro.');
  await p.fill('#sd-via', 'Via Rizzoli 1'); await p.fill('#sd-cap', '40125'); await p.fill('#sd-citta', 'Bologna'); await p.fill('#sd-provincia', 'BO');
  await p.click('#sd-cerca');
  await p.waitForFunction(() => /Trovato|non|errore/i.test(document.getElementById('sd-geo-esito').textContent), null, { timeout: 15000 });
  const esito = await p.locator('#sd-geo-esito').innerText();
  const lat1 = await p.inputValue('#sd-lat'), lng1 = await p.inputValue('#sd-lng');
  ok('Cerca sulla mappa: il server chiede le coordinate al servizio finto e le scrive nei campi', /^Trovato/.test(esito) && lat1 === '44.495' && lng1 === '11.3427', [esito.slice(0, 80), lat1, lng1]);
  const log = fs.existsSync('/tmp/nominatim-finto.log') ? fs.readFileSync('/tmp/nominatim-finto.log', 'utf8').trim().split('\n').pop() : '';
  const versione = fs.readFileSync(path.join(DIR, 'VERSIONE'), 'utf8').trim();
  ok('la richiesta al servizio porta lo User-Agent plumeCMS/<versione> (<sito>) e l\'indirizzo scritto', log.includes('plumeCMS/' + versione + ' (') && /Via Rizzoli 1, 40125 Bologna BO, Italia/.test(log), log.slice(0, 160));
  await p.waitForTimeout(400);
  ok('la mini mappa compare con nove tessere di OpenStreetMap allo zoom 16 intorno al punto', await p.locator('#sd-mappa').isVisible() && await p.locator('#sd-tessere img').count() === 9 && tessere.length === 9 && tessere.every(u => /\/16\/\d+\/\d+\.png$/.test(u)), tessere.slice(0, 3));
  // Un clic in alto a destra sulla mappa: il segnaposto va lì, latitudine e longitudine crescono.
  const box = await p.locator('#sd-tessere').boundingBox();
  const nPrima = tessere.length;
  await p.mouse.click(box.x + box.width * 0.8, box.y + box.height * 0.2);
  await p.waitForTimeout(300);
  const lat2 = parseFloat(await p.inputValue('#sd-lat')), lng2 = parseFloat(await p.inputValue('#sd-lng'));
  ok('un clic sulla mini mappa sposta il segnaposto e ricalcola latitudine e longitudine', lat2 > parseFloat(lat1) && lng2 > parseFloat(lng1) && Math.abs(lat2 - parseFloat(lat1)) < 0.02 && Math.abs(lng2 - parseFloat(lng1)) < 0.02, [lat1, lng1, lat2, lng2]);
  const seg = await p.evaluate(() => { const s = document.getElementById('sd-segnaposto').style; return { left: parseFloat(s.left), top: parseFloat(s.top) }; });
  ok('il segnaposto segue il clic (se il punto cade in un\'altra tessera la mappa si ricentra lì)', (tessere.length > nPrima && seg.left > 20 && seg.left < 80 && seg.top > 20 && seg.top < 80) || (seg.left > 70 && seg.top < 30), [seg, tessere.length - nPrima]);
  const prima = tessere.length;
  await p.click('[data-sd-zoom="1"]'); await p.waitForTimeout(300);
  ok('con + lo zoom passa a 17 e le tessere si richiedono', tessere.length === prima + 9 && tessere.slice(-9).every(u => /\/17\/\d+\/\d+\.png$/.test(u)));
  await p.click('[data-sd-zoom="-1"]'); await p.waitForTimeout(200);
  await p.fill('#sd-lat', '44.4949'); await p.fill('#sd-lng', '11.3426'); await p.locator('#sd-lng').dispatchEvent('change');
  await p.fill('#sd-telefono', '+39 051 123456'); await p.fill('#sd-email', 'bologna@esempio.it');
  await p.selectOption('#sd-prezzo', '€€');
  await p.fill('#sd-zona', 'Bologna\nCasalecchio di Reno\nProvincia di Bologna');
  for (const g of ['lun', 'mar', 'mer', 'gio', 'ven']) await p.fill('#sd-orario-' + g, '9:00-13:00, 15:00-19:00');
  await p.fill('#sd-orario-sab', '9-13'); await p.fill('#sd-orario-dom', 'Chiuso');
  await p.fill('#sd-chiusure', '2026-12-24 - 2026-12-26: Natale');
  await p.fill('#sd-google-scheda', 'https://maps.app.goo.gl/abc123'); await p.fill('#sd-google-recensione', 'https://g.page/r/abc123/review');
  ok('"Crea la pagina della sede" è accesa di partenza', await p.isChecked('input[name=crea_pagina]'));
  await foto(p, 'sedi-modulo-compilato');
  await Promise.all([p.waitForNavigation(), p.click('#sd-modulo button[type=submit]')]);
  const fl1 = await p.locator('.flash-success').innerText().catch(() => '');
  ok('salvata, con la pagina /sede-di-bologna/ creata e pubblicata', /Sede «Sede di Bologna» salvata/.test(fl1) && /\/sede-di-bologna\//.test(fl1), fl1.slice(0, 160));
  const elenco1 = cli('elenco');
  ok('la sede salvata ha i campi puliti (coordinate, zona 3 righe, orari, chiusura, link Google, pagina)', elenco1.length === 1 && elenco1[0].id === 'sede-di-bologna' && elenco1[0].lat === '44.4949' && elenco1[0].zona.length === 3 && elenco1[0].orari.lun === '9:00-13:00, 15:00-19:00' && elenco1[0].chiusure.length === 1 && elenco1[0].google_scheda === 'https://maps.app.goo.gl/abc123' && elenco1[0].pagina > 0 && elenco1[0].principale === true, elenco1[0]);
  ok('l\'elenco mostra la sede con Principale, il tipo, le coordinate e il link alla pagina', /Sede di Bologna/.test(await p.locator('#sd-tabella').innerText()) && await p.locator('#sd-tabella .sd-principale').count() === 1 && /Ristorante/.test(await p.locator('#sd-tabella').innerText()) && await p.locator('#sd-tabella a[href*="/sede-di-bologna/"]').count() === 1);

  // === 3. La seconda sede con "Altro, lo scrivo io"; prima gli errori sotto i campi
  await p.goto(B + '/admin/sedi.php', { waitUntil: 'networkidle' });
  await p.fill('#sd-nome', 'Sede di Imola');
  await p.selectOption('#sd-tipo', 'altro');
  ok('con Altro compare la casella "Che attività è"', await p.locator('#sd-tipo-testo-riga').isVisible());
  await p.fill('#sd-citta', 'Imola'); await p.fill('#sd-via', 'Via Emilia 10');
  await p.fill('#sd-email', 'non-una-email'); await p.fill('#sd-google-scheda', 'https://evil.example/scheda'); await p.fill('#sd-lat', '1e99');
  await p.uncheck('input[name=crea_pagina]');
  await Promise.all([p.waitForNavigation(), p.click('#sd-modulo button[type=submit]')]);
  const errori = await p.locator('.sd-errore[role=alert]').allInnerTexts();
  ok('quattro errori sotto i campi (tipo scritto vuoto, email, coordinate, link Google) e il riassunto in cima', errori.length === 4 && /4 campi da sistemare/.test(await p.locator('.flash-error').innerText()) && await p.locator('#sd-tipo-testo[aria-invalid=true]').count() === 1, errori);
  ok('i valori scritti restano nel modulo', await p.inputValue('#sd-nome') === 'Sede di Imola' && await p.inputValue('#sd-via') === 'Via Emilia 10' && await p.inputValue('#sd-tipo') === 'altro' && cli('elenco').length === 1);
  await p.fill('#sd-tipo-testo', 'centro di meditazione');
  await p.fill('#sd-email', 'imola@esempio.it'); await p.fill('#sd-google-scheda', ''); await p.fill('#sd-lat', ''); await p.fill('#sd-lng', '');
  await p.fill('#sd-zona', 'Imola\nCastel San Pietro Terme');
  await p.fill('#sd-orario-lun', '10-18');
  await Promise.all([p.waitForNavigation(), p.click('#sd-modulo button[type=submit]')]);
  const elenco2 = cli('elenco');
  const imola = elenco2.find(s => s.id === 'sede-di-imola');
  ok('la seconda sede è salvata con il tipo scritto a mano formattato: "Centro di meditazione"', !!imola && imola.tipo === 'altro' && imola.tipo_testo === 'Centro di meditazione' && imola.pagina === 0 && imola.principale === false, imola);
  ok('nell\'elenco esce "Centro di meditazione" e il pulsante Crea la pagina', /Centro di meditazione/.test(await p.locator('#sd-tabella').innerText()) && await p.locator('#sd-tabella button:has-text("Crea la pagina")').count() === 1);
  await foto(p, 'sedi-elenco');

  // === 4. Il sito: la pagina della sede, i dati per Google, il titolo con %citta%, la sitemap
  const v = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const errSito = []; v.on('pageerror', e => errSito.push(v.url() + ' ' + e.message.slice(0, 160)));
  const rs = await v.goto(B + '/sede-di-bologna/', { waitUntil: 'networkidle' });
  const html = await v.content();
  ok('la pagina della sede si apre (200) con apertura, dati dell\'attività, mappa, orari, testo e barra dei contatti', rs.status() === 200 && /Sede di Bologna/i.test(await v.locator('h1').first().innerText()) && await v.locator('.ew-dati-attivita').count() === 1 && await v.locator('.ew-mappa').count() >= 1 && await v.locator('.ew-orari-giorni').count() === 1 && await v.locator('.ew-barra-contatti').count() === 1 && /La sede storica/.test(html));
  ok('la pagina della sede ha "Come arrivare" verso le coordinate e gli orari del lunedì', (await v.locator('a[href*="maps/dir/"]').first().getAttribute('href')).includes('44.4949%2C11.3426') && /9:00 - 13:00, 15:00 - 19:00/.test(await v.locator('.ew-orari-giorni').innerText()));
  ok('nella pagina della sede il widget Orari non ripete i dati per Google (uno script JSON-LD solo)', (html.match(/application\/ld\+json/g) || []).length === 1);
  const gSede = (await ld(v)).find(x => x['@graph'])['@graph'];
  const web = gSede.find(x => x['@type'] === 'WebPage'), org = orgDi(gSede);
  ok('JSON-LD della pagina della sede: la WebPage ha mainEntity = la LocalBusiness della sede', web && web.mainEntity && web.mainEntity['@id'] === org.location[0]['@id'], web && web.mainEntity);
  const loc = org ? org.location : [];
  ok('JSON-LD: l\'organizzazione ha location con due sedi, Restaurant e LocalBusiness, nell\'ordine salvato', loc.length === 2 && loc[0]['@type'] === 'Restaurant' && loc[1]['@type'] === 'LocalBusiness' && loc[0].name === 'Sede di Bologna' && loc[1].name === 'Sede di Imola', loc.map(x => x['@type']));
  const b = loc[0] || {};
  ok('la prima sede ha address a campi, geo, telephone, email, openingHoursSpecification, priceRange, areaServed, hasMap, sameAs, url, parentOrganization',
    b.address && b.address['@type'] === 'PostalAddress' && b.address.streetAddress === 'Via Rizzoli 1' && b.address.postalCode === '40125' && b.address.addressLocality === 'Bologna' && b.address.addressRegion === 'BO' && b.address.addressCountry === 'IT'
    && b.geo && b.geo.latitude === 44.4949 && b.geo.longitude === 11.3426 && b.telephone === '+39 051 123456' && b.email === 'bologna@esempio.it'
    && Array.isArray(b.openingHoursSpecification) && b.openingHoursSpecification.length === 11 && b.openingHoursSpecification[0].dayOfWeek === 'https://schema.org/Monday' && b.openingHoursSpecification[0].opens === '09:00'
    && b.priceRange === '€€' && b.areaServed.length === 3 && b.areaServed[2]['@type'] === 'AdministrativeArea' && /maps\/search/.test(b.hasMap) && b.sameAs.includes('https://maps.app.goo.gl/abc123') && b.url === B + '/sede-di-bologna/' && b.parentOrganization['@id'] === org['@id'], b);
  const i = loc[1] || {};
  ok('la seconda sede (Altro) esce come LocalBusiness con il testo scritto nella description, senza geo e senza url', i['@type'] === 'LocalBusiness' && i.description === 'Centro di meditazione' && !i.geo && !i.url && i.address.addressLocality === 'Imola', i);
  const gHome = await grafo(v, '/');
  ok('JSON-LD della home: le stesse due sedi nella location, nessuna mainEntity', orgDi(gHome).location.length === 2 && !gHome.find(x => x['@type'] === 'WebPage').mainEntity);
  const gChi = await grafo(v, '/chi-siamo/');
  ok('JSON-LD di un\'altra pagina: le due sedi, nessuna mainEntity', orgDi(gChi).location.length === 2 && !gChi.find(x => x['@type'] === 'WebPage').mainEntity);
  // I servizi: areaServed dall'unione delle zone delle sedi.
  cli('sql', `UPDATE {p}pages SET servizio = '{"tipo":"servizio"}' WHERE slug = 'servizi'`);
  const gServ = await grafo(v, '/servizi/');
  const serv = gServ.find(x => x['@type'] === 'Service');
  ok('JSON-LD dei servizi: areaServed è l\'unione delle zone delle sedi (5 voci: 4 City e 1 AdministrativeArea)', serv && Array.isArray(serv.areaServed) && serv.areaServed.length === 5 && serv.areaServed.filter(x => x['@type'] === 'City').length === 4, serv && serv.areaServed);
  cli('sql', `UPDATE {p}pages SET servizio = NULL WHERE slug = 'servizi'`);
  // Il titolo con %citta%: dal pannello, Globale SEO.
  await p.goto(B + '/admin/seo.php?scheda=globale', { waitUntil: 'networkidle' });
  const formatoPrima = await p.inputValue('input[name="titolo[pagina]"]');
  await p.fill('input[name="titolo[pagina]"]', '%titolo% %sep% %citta% %sep% %sito%');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[name="titolo[pagina]"]) button[type=submit]')]);
  await v.goto(B + '/chi-siamo/', { waitUntil: 'networkidle' });
  ok('titolo con %citta%: la città della sede principale entra nel titolo', (await v.title()) === 'Chi siamo - Bologna - Studio Prova', await v.title());
  ok('nella sitemap c\'è la pagina della sede', /sede-di-bologna\//.test(await (await v.context().request.get(B + '/sitemap.xml')).text()));

  // === 5. I widget sul sito (prove/pagina-sedi.php) e nell'editor
  const seme = execFileSync('php', [path.join(__dirname, 'pagina-sedi.php'), DIR, 'sede-di-imola'], { encoding: 'utf8' });
  const idProva = +(seme.match(/pagina (\d+)/) || [])[1];
  const rw = await v.goto(B + '/prova-sedi/', { waitUntil: 'networkidle' });
  ok('la pagina con i widget delle sedi si apre (200) e carica sedi.css', rw.status() === 200 && await v.locator('link[href*="sedi.css"]').count() === 1);
  const griglia = v.locator('.ew-sedi-griglia');
  ok('widget Sedi in griglia: due schede, con nome, tipo, indirizzo, telefono (tel:), email (mailto:), stato aperto/chiuso, Come arrivare, recensione solo dove c\'è il link, La sede solo dove c\'è la pagina',
    await griglia.locator('.ew-sede').count() === 2 && await griglia.locator('.ew-sede-nome').first().innerText() === 'Sede di Bologna' && /Ristorante/i.test(await griglia.locator('.ew-sede-tipo').first().innerText())
    && (await griglia.locator('.ew-sede-tel').first().getAttribute('href')) === 'tel:+39051123456' && (await griglia.locator('.ew-sede-email').first().getAttribute('href')) === 'mailto:bologna@esempio.it'
    && await griglia.locator('.ew-orari-frase').count() === 2 && await griglia.locator('.ew-sede-arrivare').count() === 2 && await griglia.locator('.ew-sede-recensione').count() === 1 && await griglia.locator('.ew-sede-pagina').count() === 1
    && (await griglia.locator('.ew-sede-pagina').getAttribute('href')) === B + '/sede-di-bologna/');
  ok('widget Sedi: la mappa in ogni scheda si carica al clic (OpenStreetMap con le coordinate, dall\'indirizzo senza)', await griglia.locator('.ew-sede-mappa [data-ew-mappa]').count() === 2 && /openstreetmap\.org\/export\/embed/.test(await griglia.locator('.ew-sede-mappa').first().getAttribute('data-src')) && /google\.com\/maps/.test(await griglia.locator('.ew-sede-mappa').nth(1).getAttribute('data-src')));
  await griglia.locator('.ew-sede-mappa [data-ew-mappa]').first().click();
  ok('al clic la mappa della scheda diventa un iframe con il segnaposto della sede', await griglia.locator('.ew-sede-mappa iframe').count() === 1 && /marker=44\.4949,11\.3426/.test(await griglia.locator('.ew-sede-mappa iframe').getAttribute('src')));
  const elenco = v.locator('.ew-sedi-elenco');
  ok('widget Sedi in elenco con una sede scelta: solo Imola, mappa subito (iframe)', await elenco.locator('.ew-sede').count() === 1 && /Sede di Imola/.test(await elenco.innerText()) && await elenco.locator('iframe').count() === 1);
  const dati = v.locator('.ew-dati-attivita');
  ok('widget Dati dell\'attività (sede principale, in riga): titolo, nome, indirizzo, orari, pulsanti', await dati.count() === 1 && await dati.locator('.ew-dati-titolo').innerText() === 'Dove siamo' && /Sede di Bologna/.test(await dati.innerText()) && await dati.locator('.ew-orari-frase').count() === 1 && await dati.locator('.ew-sede-arrivare').count() === 1 && await dati.evaluate(e => e.classList.contains('ew-dati-orizzontale')));
  const mappaSede = v.locator('.ed-w-mappa .ew-mappa iframe');
  ok('widget Mappa "dalla sede principale": OpenStreetMap con le coordinate della sede e Come arrivare verso di esse', /openstreetmap\.org\/export\/embed\.html\?bbox=.*marker=44\.4949,11\.3426/.test(await mappaSede.getAttribute('src')) && (await v.locator('.ed-w-mappa .ew-mappa-arrivare a').first().getAttribute('href')).includes('44.4949%2C11.3426'));
  const orariW = v.locator('.ed-w-orari');
  ok('widget Orari "dalla sede": i giorni della sede (sabato 9:00 - 13:00, domenica Chiuso) e la chiusura di Natale', /Sabato\s+9:00 - 13:00/.test(await orariW.innerText()) && /Domenica\s+Chiuso/.test(await orariW.innerText()) && /Natale: dal 24\/12 al 26\/12/.test(await orariW.innerText()));
  const t = await (await br.newContext({ viewport: { width: 390, height: 844 } })).newPage();
  await t.goto(B + '/prova-sedi/', { waitUntil: 'networkidle' });
  const vociBarra = await t.locator('.ew-barra-contatti a').evaluateAll(l => l.map(a => a.getAttribute('href')));
  ok('widget Barra contatti "dalla sede" al telefono: Chiama, WhatsApp (del widget), Indicazioni verso le coordinate, Scrivici con l\'email della sede', vociBarra.length === 4 && vociBarra[0] === 'tel:+39051123456' && /wa\.me\/39333123456/.test(vociBarra[1]) && vociBarra[2].includes('destination=44.4949%2C11.3426') && vociBarra[3] === 'mailto:bologna@esempio.it', vociBarra);
  ok('la pagina dei widget al telefono non scorre di lato e la griglia ha una colonna', await t.evaluate(() => document.documentElement.scrollWidth <= 391) && await t.evaluate(() => getComputedStyle(document.querySelector('.ew-sedi-griglia')).gridTemplateColumns.split(' ').length === 1));
  await t.goto(B + '/sede-di-bologna/', { waitUntil: 'networkidle' });
  ok('la pagina della sede al telefono non scorre di lato', await t.evaluate(() => document.documentElement.scrollWidth <= 391));
  // Nell'editor: la tela mostra i widget, il pannello della Mappa ha la tendina "Dati dalla sede" con le sedi.
  await p.goto(B + '/admin/editor.php?tipo=pagine&id=' + idProva, { waitUntil: 'networkidle' });
  await p.waitForFunction(() => { const f = document.querySelector('#ed-iframe'); return f && f.contentDocument && f.contentDocument.querySelector('.ew-sedi'); }, null, { timeout: 20000 });
  const tela = p.frameLocator('#ed-iframe');
  ok('editor: la tela disegna i widget Sedi e Dati dell\'attività con sedi.css', await tela.locator('.ew-sedi .ew-sede').count() === 3 && await tela.locator('.ew-dati-attivita').count() === 1 && await tela.locator('link[href*="sedi.css"]').count() === 1);
  // Il widget Orari (la Mappa è un iframe: il clic non arriva all'editor); il clic forzato scavalca la maniglia della colonna.
  await tela.locator('.ed-w-orari .ew-orari-giorni li').nth(3).click({ force: true });
  await p.waitForTimeout(700);
  const opzioni = await p.locator('#ed-controlli .ed-controllo', { hasText: 'Dati dalla sede' }).first().locator('select option').evaluateAll(os => os.map(o => o.value + ':' + o.text)).catch(() => []);
  ok('editor: il pannello degli Orari ha "Dati dalla sede" con Nessuna, La sede principale e le due sedi', opzioni.length === 4 && opzioni[0].startsWith(':') && opzioni[1] === 'principale:La sede principale' && opzioni.some(x => x === 'sede-di-bologna:Sede di Bologna') && opzioni.some(x => x === 'sede-di-imola:Sede di Imola'), opzioni);
  ok('editor: la tavolozza ha Sedi e Dati dell\'attività', (await p.locator('body').innerText()).includes('Dati dell\'attività') && await p.locator('.ed-widget-voce[data-tipo="sedi"]').count() === 1 && await p.locator('.ed-widget-voce[data-tipo="dati_attivita"]').count() === 1);
  await foto(p, 'sedi-editor');

  // === 6. Elimina una sede: la pagina resta; il redattore non entra
  await p.goto(B + '/admin/sedi.php', { waitUntil: 'networkidle' });
  const riga = p.locator('#sd-tabella tr', { hasText: 'Sede di Bologna' });
  await Promise.all([p.waitForNavigation(), riga.locator('form[data-conferma] button').click()]);
  const fl3 = await p.locator('.flash-success').innerText().catch(() => '');
  ok('eliminata la sede: il messaggio dice che la pagina resta', /eliminata/.test(fl3) && /pagina resta/.test(fl3), fl3);
  const rp = await v.goto(B + '/sede-di-bologna/', { waitUntil: 'networkidle' });
  ok('la pagina della sede eliminata risponde ancora (200) e non ha più mainEntity; l\'altra sede è diventata principale', rp.status() === 200 && !(await ld(v)).find(x => x['@graph'])['@graph'].find(x => x['@type'] === 'WebPage').mainEntity && cli('elenco')[0].principale === true && cli('elenco')[0].id === 'sede-di-imola');
  await v.goto(B + '/chi-siamo/', { waitUntil: 'networkidle' });
  ok('titolo con %citta%: ora la città è Imola', (await v.title()) === 'Chi siamo - Imola - Studio Prova', await v.title());
  cli('azzera');
  await v.goto(B + '/chi-siamo/', { waitUntil: 'networkidle' });
  ok('senza sedi %citta% sparisce con il suo separatore (niente " - -")', (await v.title()) === 'Chi siamo - Studio Prova', await v.title());
  await v.goto(B + '/prova-sedi/', { waitUntil: 'networkidle' });
  ok('senza sedi i widget Sedi e Dati dell\'attività non escono sul sito, e Mappa, Orari e Barra "dalla sede" tornano ai loro campi', await v.locator('.ew-sede').count() === 0 && await v.locator('.ed-w-mappa iframe').count() === 1 && /Firenze/.test(await v.locator('.ed-w-mappa iframe').getAttribute('src')) && await v.locator('.ed-w-orari .ew-orari-giorni').count() === 1 && (await ld(v)).some(x => x['@type'] === 'LocalBusiness'));
  await p.goto(B + '/admin/seo.php?scheda=globale', { waitUntil: 'networkidle' });
  await p.fill('input[name="titolo[pagina]"]', formatoPrima);
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[name="titolo[pagina]"]) button[type=submit]')]);
  cli('redattore', 'crea');
  const red = await entra(br, 'sedi_prova_red', 'Red-Sedi-Prova-2026');
  ok('il redattore entra nel pannello', red.dentro);
  await red.p.goto(B + '/admin/sedi.php', { waitUntil: 'networkidle' });
  ok('il redattore non apre Sedi (torna alla dashboard con l\'avviso)', !red.p.url().includes('sedi.php') && /riservata/.test(await red.p.content()));
  const tRed = await red.p.evaluate(() => (document.querySelector('meta[name="pn-csrf"]') || {}).content || '');
  const rr = await red.p.evaluate(async ({ t }) => { const fd = new FormData(); fd.append('_csrf', t); fd.append('azione', 'sedi_cerca'); fd.append('indirizzo', 'Via Rizzoli 1, Bologna'); const r = await fetch('seo-api.php', { method: 'POST', body: fd, credentials: 'include' }); return { s: r.status, t: await r.text() }; }, { t: tRed });
  ok('il redattore non cerca le coordinate (403) e non salva una sede (torna alla dashboard, niente sedi)', rr.s === 403 && (await (async () => { await red.p.goto(B + '/admin/index.php'); const r = await red.c.request.post(B + '/admin/sedi.php', { form: { _csrf: tRed, azione: 'salva', 'sede[nome]': 'Sede del redattore', 'sede[citta]': 'X', 'sede[tipo]': 'negozio' }, maxRedirects: 0 }); return [302, 303, 403].includes(r.status()); })()) && cli('elenco').length === 0, rr.s);
  await red.c.close();
  cli('redattore', 'elimina');

  // === 7. Il pannello a 1280 e 390 px
  await p.goto(B + '/admin/sedi.php', { waitUntil: 'networkidle' });
  ok('sedi.php a 1280 px non scorre di lato', await p.evaluate(() => document.documentElement.scrollWidth <= 1281));
  const tp = await (await br.newContext({ viewport: { width: 390, height: 844 }, storageState: await admin.c.storageState() })).newPage();
  await tp.goto(B + '/admin/sedi.php', { waitUntil: 'networkidle' });
  ok('sedi.php a 390 px non scorre di lato', await tp.evaluate(() => document.documentElement.scrollWidth <= 391), await tp.evaluate(() => document.documentElement.scrollWidth));
  await foto(tp, 'sedi-telefono');

  // Pulizia finale
  cli('sql', `DELETE FROM {p}pages WHERE slug IN ('prova-sedi', 'sede-di-bologna')`);
  ok('nessun errore JavaScript nel pannello e sul sito', err.length === 0 && errSito.length === 0, err.concat(errSito));
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error('ERRORE della prova:', e); process.exit(2); });
