// I dati delle immagini (2.38.0), come li userebbero una persona e una AI: la scheda dell'immagine in Media (salva, rileggi,
// lunghezze, HTML scartato, indirizzi sbagliati segnati sotto il campo), dove è usata e Rinomina il file, l'alt della libreria sul
// sito vinto dall'alt del widget, la didascalia della libreria solo con l'interruttore, il JSON-LD ImageObject con i campi giusti e
// senza campi vuoti (e niente ImageObject per una foto senza dati), il nome leggibile al caricamento, Media > Dati delle immagini
// con i filtri e la correzione in posto, la foto importata dall'AI con i crediti già scritti, list_media e propose_media_data via
// MCP con una chiave e l'approvazione nel pannello (Approva tutte), il redattore, il telefono a 390 px senza scorrimento di lato.
//
// Serve: un sito installato (meglio col kit Attività), radice o cartella, e il server finto delle foto
//   php -S 127.0.0.1:<porta> prove/foto-finte.php
// uso: node prove/prova-immagini.js <base> <cartella CMS> <utente> <password> <porta foto finte> [cartella foto]
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const path = require('path');
const fs = require('fs');
const os = require('os');
const [B, DIR, UTENTE, PASSWORD, PORTA_FOTO, FOTO] = process.argv.slice(2);
if (!B || !DIR || !UTENTE || !PASSWORD || !PORTA_FOTO) { console.error('uso: node prove/prova-immagini.js <base> <cartella CMS> <utente> <password> <porta foto finte> [cartella foto]'); process.exit(2); }
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (!c && extra !== '' ? '  ' + JSON.stringify(extra).slice(0, 500) : '')); };
const cliDi = (file) => (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, file), DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }).trim().split('\n').pop());
const cli = cliDi('immagini-cli.php'), ai = cliDi('ai-cli.php');
const CART = new URL(B).pathname.replace(/\/$/, ''); // '' oppure '/test'
const MCP = B + '/api/mcp';
let idRpc = 1;
async function strumento(token, nome, args) {
  const r = await fetch(MCP, { method: 'POST', headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json', Accept: 'application/json, text/event-stream', 'MCP-Protocol-Version': '2025-06-18' },
    body: JSON.stringify({ jsonrpc: '2.0', id: idRpc++, method: 'tools/call', params: { name: nome, arguments: args || {} } }) });
  const j = await r.json().catch(() => null); const res = j && j.result;
  let dati = null; try { dati = JSON.parse(res.content[0].text); } catch (e) {}
  return { http: r.status, errore: !!(res && res.isError), dati, grezzo: j };
}
const ldjson = (html) => { const m = html.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/); return m ? JSON.parse(m[1]) : null; };
const vuoti = (o) => { const out = []; const giro = (x, p) => { if (x === '' || x === null) out.push(p); else if (x && typeof x === 'object') for (const k of Object.keys(x)) giro(x[k], p + '.' + k); }; giro(o, ''); return out; };
const largo = (p) => p.evaluate(() => document.documentElement.scrollWidth);
const scheda = (u) => B + '/admin/media.php?immagine=' + encodeURIComponent(u);

(async () => {
  cli('pulisci'); ai('tentativi');
  const A = cli('foto', 'imgprova-a.jpg', 640, 420).url, Bf = cli('foto', 'imgprova-b.jpg', 300, 200).url, C = cli('foto', 'imgprova-c.jpg', 300, 200).url;
  const P = cli('foto', 'imgprova-p.png', 520, 520, 'pesante').url, L = cli('foto', 'imgprova-libera.jpg', 300, 200).url;
  const pag = cli('pagina', 'img-prova', A, Bf, C);
  const sempl = cli('pagina-semplice', 'img-prova-apertura', C);
  const art = cli('articolo', 'img-prova-articolo', Bf);
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await ctx.newPage();
  pnConferme.on(p, d => d.accept());
  const errJs = []; p.on('pageerror', e => errJs.push(e.message));
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  // === 0. Senza dati: niente ImageObject, alt e didascalie come prima
  const h0 = await (await p.request.get(B + pag.percorso)).text();
  const g0 = ldjson(h0);
  ok('senza dati: la WebPage della pagina non ha primaryImageOfPage', g0 && !g0['@graph'].some(x => x['@type'] === 'WebPage' && x.primaryImageOfPage));
  ok('senza dati: la prima Immagine esce con alt vuoto e senza didascalia', /<img src="[^"]*imgprova-a\.jpg" alt="" loading="lazy"><\/figure>/.test(h0), (h0.match(/<figure class="ed-immagine[^]*?<\/figure>/) || [''])[0]);

  // === 1. Libreria: clic sulla foto apre la scheda
  await p.goto(B + '/admin/media.php?q=imgprova-a', { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.click('a[aria-label="Apri la scheda di imgprova-a.jpg"]')]);
  ok('un clic sulla foto in Libreria apre la sua scheda', /media\.php\?immagine=/.test(p.url()) && await p.locator('#ms-modulo').count() === 1);
  const info = await p.locator('.ms-dati').innerText();
  ok('la scheda mostra dimensioni, peso, formato e data', /640 × 420 pixel/.test(info) && /KB/.test(info) && /JPG/.test(info) && /\d\d\/\d\d\/\d{4}/.test(info), info);
  ok('Dove è usata: la pagina di prova, senza "forse"', /Immagini di prova/.test(await p.locator('#ms-usata').innerText()) && await p.locator('#ms-usata li', { hasText: 'Immagini di prova' }).locator('.ms-forse').count() === 0, await p.locator('#ms-usata').innerText());
  // il conteggio dell'alt
  await p.fill('#ms-alt', 'Immagine di una vetrina');
  ok('il conteggio dell\'alt avvisa su "immagine di"', /Togli "Immagine di"/.test(await p.locator('[data-ms-conta-per="ms-alt"]').innerText()));
  await p.fill('#ms-alt', 'x'.repeat(130));
  ok('il conteggio dell\'alt avvisa oltre 125 caratteri', /130 caratteri: lungo/.test(await p.locator('[data-ms-conta-per="ms-alt"]').innerText()));
  // indirizzo sbagliato: errore sotto il campo, niente salvato
  await p.fill('#ms-alt', 'La vetrina del negozio di sera');
  await p.fill('#ms-fonte_url', 'javascript:alert(1)');
  await Promise.all([p.waitForNavigation(), p.click('#ms-modulo button[type=submit]')]);
  ok('fonte javascript: rifiutata, errore sotto il campo con aria-invalid e riassunto in cima', await p.locator('#ms-fonte_url-errore').count() === 1 && await p.getAttribute('#ms-fonte_url', 'aria-invalid') === 'true' && /campo da sistemare/.test(await p.locator('.flash-error').first().innerText()));
  ok('dopo l\'errore i valori scritti restano nei campi e niente è salvato', await p.inputValue('#ms-alt') === 'La vetrina del negozio di sera' && cli('dati', A).alt === '');
  // salva tutto
  await p.fill('#ms-alt', 'La vetrina del negozio di sera');
  await p.fill('#ms-titolo', '<b>Vetrina</b> di sera<script>alert(1)</script>');
  await p.fill('#ms-didascalia', 'La vetrina dopo il rinnovo');
  await p.evaluate(() => { document.querySelector('#ms-descrizione').removeAttribute('maxlength'); document.querySelector('#ms-descrizione').value = 'D'.repeat(2500); });
  await p.fill('#ms-autore', 'Giulia Rossi');
  await p.selectOption('#ms-licenza', 'cc-by');
  await p.fill('#ms-fonte_url', 'https://archivio.example/foto/a');
  await Promise.all([p.waitForNavigation(), p.click('#ms-modulo button[type=submit]')]);
  ok('salvataggio: messaggio di conferma', /Dati della foto salvati/.test(await p.locator('.flash-success').first().innerText().catch(() => '')));
  await p.reload();
  const d1 = cli('dati', A);
  ok('rileggendo: alt, didascalia, autore, licenza e fonte salvati', await p.inputValue('#ms-alt') === 'La vetrina del negozio di sera' && d1.didascalia === 'La vetrina dopo il rinnovo' && d1.autore === 'Giulia Rossi' && d1.licenza === 'cc-by' && d1.fonte_url === 'https://archivio.example/foto/a', d1);
  ok('HTML scartato: il titolo è "Vetrina di sera", senza tag né script', d1.titolo === 'Vetrina di sera' && await p.inputValue('#ms-titolo') === 'Vetrina di sera', d1.titolo);
  ok('descrizione tagliata a 2000 caratteri', d1.descrizione.length === 2000, d1.descrizione.length);
  await p.fill('#ms-descrizione', 'La vetrina con le novità di stagione, fotografata dalla strada di sera.');
  await Promise.all([p.waitForNavigation(), p.click('#ms-modulo button[type=submit]')]);
  if (FOTO) await p.screenshot({ path: path.join(FOTO, 'immagini-scheda.png'), fullPage: true });

  // === 2. Sul sito
  const h1 = await (await p.request.get(B + pag.percorso)).text();
  ok('sito: l\'Immagine senza alt nel widget prende l\'alt della libreria', /<img src="[^"]*imgprova-a\.jpg" alt="La vetrina del negozio di sera"/.test(h1));
  ok('sito: l\'alt scritto nel widget vince su quello della libreria', /<img src="[^"]*imgprova-a\.jpg" alt="Alt scritto nel widget"/.test(h1));
  ok('sito: la didascalia della libreria esce solo nell\'Immagine con l\'interruttore acceso', (h1.match(/<figcaption>La vetrina dopo il rinnovo<\/figcaption>/g) || []).length === 1);
  ok('sito: niente attributo title sulle immagini', !/<img[^>]*imgprova[^>]*\btitle=/.test(h1));
  const g1 = ldjson(h1);
  const io = (g1['@graph'].find(x => x['@type'] === 'WebPage') || {}).primaryImageOfPage || {};
  ok('JSON-LD: primaryImageOfPage è un ImageObject con contentUrl assoluto e le misure', io['@type'] === 'ImageObject' && /^https?:\/\/[^/]+.*\/uploads\/pagine\/2026\/11\/imgprova-a\.jpg$/.test(io.contentUrl || '') && io.width === 640 && io.height === 420, io);
  ok('JSON-LD: name, caption, description, creditText, creator Person, copyrightNotice, license CC BY, acquireLicensePage', io.name === 'Vetrina di sera' && io.caption === 'La vetrina dopo il rinnovo' && /novità di stagione/.test(io.description || '') && io.creditText === 'Giulia Rossi'
    && io.creator && io.creator['@type'] === 'Person' && io.creator.name === 'Giulia Rossi' && io.copyrightNotice === 'Giulia Rossi' && io.license === 'https://creativecommons.org/licenses/by/4.0/' && io.acquireLicensePage === 'https://archivio.example/foto/a', io);
  ok('JSON-LD: nessun campo vuoto nell\'ImageObject', vuoti(io).length === 0, vuoti(io));
  const g2 = ldjson(await (await p.request.get(B + sempl.percorso)).text());
  ok('JSON-LD: una pagina con una foto senza dati non ha ImageObject', g2 && !g2['@graph'].some(x => x['@type'] === 'WebPage' && x.primaryImageOfPage));
  // la foto dell'apertura con dati: titolo a C
  await p.goto(scheda(C)); await p.fill('#ms-titolo', 'Foto della apertura'); await Promise.all([p.waitForNavigation(), p.click('#ms-modulo button[type=submit]')]);
  const g3 = ldjson(await (await p.request.get(B + sempl.percorso)).text());
  const io3 = (g3['@graph'].find(x => x['@type'] === 'WebPage') || {}).primaryImageOfPage || {};
  ok('JSON-LD: la foto della prima Apertura vale come immagine principale, solo con i campi scritti', io3['@type'] === 'ImageObject' && io3.name === 'Foto della apertura' && !('creator' in io3) && !('license' in io3) && !('caption' in io3), io3);
  // articolo: B ha solo alt e didascalia nella libreria
  await p.goto(scheda(Bf)); await p.fill('#ms-alt', 'Alt di B nella libreria'); await p.fill('#ms-didascalia', 'Didascalia di B nella libreria');
  await p.fill('#ms-autore', 'Studio Fotografico Prova'); await p.selectOption('#ms-autore_tipo', 'organizzazione'); await p.selectOption('#ms-licenza', 'riservati');
  await Promise.all([p.waitForNavigation(), p.click('#ms-modulo button[type=submit]')]);
  const hA = await (await p.request.get(B + art.percorso)).text();
  const ga = ldjson(hA); const ioA = (ga['@graph'].find(x => x['@type'] === 'WebPage') || {}).primaryImageOfPage || {};
  ok('articolo: ImageObject con creator Organization, copyrightNotice e senza license (diritti riservati senza pagina)', ioA.creator && ioA.creator['@type'] === 'Organization' && ioA.copyrightNotice === 'Studio Fotografico Prova' && !('license' in ioA) && ioA.caption === 'Didascalia di B nella libreria', ioA);
  const h2 = await (await p.request.get(B + pag.percorso)).text();
  ok('galleria: B senza alt nel widget prende l\'alt della libreria e, con l\'interruttore, la didascalia', /imgprova-b\.jpg" alt="Alt di B nella libreria"/.test(h2) && /<figcaption>Didascalia di B nella libreria<\/figcaption>/.test(h2));
  ok('galleria: C con alt e didascalia nel widget li tiene', /imgprova-c\.jpg" alt="Alt di C nel widget"/.test(h2) && /<figcaption>Didascalia di C nel widget<\/figcaption>/.test(h2));

  // === 3. Rinomina il file (solo una foto non usata)
  await p.goto(scheda(A));
  ok('Rinomina: una foto usata non si rinomina (spiegato)', /Non si può/.test(await p.locator('#ms-rinomina').textContent()) && await p.locator('#ms-rinomina button').count() === 0);
  await p.goto(scheda(L));
  ok('Dove è usata: una foto mai usata lo dice', /Non la trovo in nessuna pagina/.test(await p.locator('#ms-usata').innerText()));
  ok('Rinomina: spento finché la foto non ha un titolo', await p.locator('#ms-rinomina button').isDisabled());
  await p.fill('#ms-titolo', 'Foto non usata'); await p.fill('#ms-alt', 'Una foto mai usata');
  await Promise.all([p.waitForNavigation(), p.click('#ms-modulo button[type=submit]')]);
  await p.click('#ms-rinomina summary');
  await Promise.all([p.waitForNavigation(), p.click('#ms-rinomina button')]);
  const nuovoUrl = decodeURIComponent((p.url().match(/immagine=([^&]+)/) || [])[1] || '');
  ok('Rinomina: il file prende il nome dal titolo e i dati lo seguono', /\/foto-non-usata-[a-f0-9]{6}\.jpg$/.test(nuovoUrl) && cli('dati', nuovoUrl).alt === 'Una foto mai usata' && (await p.request.get(new URL(B).origin + nuovoUrl)).status() === 200, nuovoUrl);

  // === 4. Nome leggibile al caricamento
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'imgprova-'));
  const jpg = fs.readFileSync(path.join(DIR, 'uploads/pagine/2026/11/imgprova-b.jpg'));
  fs.writeFileSync(path.join(tmp, 'IMG_20261108_123456.jpg'), jpg); fs.writeFileSync(path.join(tmp, 'negozio-prova.jpg'), jpg);
  const carica = async (file, titolo) => {
    await p.goto(B + '/admin/media.php');
    await p.setInputFiles('input[type=file][name=file]', path.join(tmp, file));
    await p.fill('#md-titolo-nuova', titolo);
    await Promise.all([p.waitForNavigation(), p.click('button:has-text("Carica in uploads/pagine")')]);
    return decodeURIComponent((p.url().match(/immagine=([^&]+)/) || [])[1] || '');
  };
  const u1 = await carica('IMG_20261108_123456.jpg', 'Vetrina di sera');
  ok('caricamento: IMG_20261108_123456.jpg con un titolo diventa vetrina-di-sera-xxxxxx.jpg e apre la scheda', /\/uploads\/pagine\/\d{4}\/\d\d\/vetrina-di-sera-[a-f0-9]{6}\.jpg$/.test(u1) && await p.inputValue('#ms-titolo') === 'Vetrina di sera', u1);
  const u2 = await carica('negozio-prova.jpg', 'Un altro titolo');
  ok('caricamento: un nome già leggibile resta com\'è (negozio-prova-...)', /\/negozio-prova-[a-f0-9]{6}\.jpg$/.test(u2), u2);
  fs.rmSync(tmp, { recursive: true, force: true });

  // === 5. Media > Dati delle immagini
  await p.goto(B + '/admin/media.php?scheda=dati', { waitUntil: 'networkidle' });
  ok('Dati delle immagini: la scheda attiva e i cinque filtri con i numeri', await p.locator('.imp-schede a.attiva[href="media.php?scheda=dati"]').count() === 1 && await p.locator('.md-filtri a').count() === 5 && /Senza alt \(\d+\)/.test(await p.locator('.md-filtri').innerText()));
  ok('filtro Senza alt: la foto pesante senza alt è in elenco, A (con alt) no', await p.locator('#ma-elenco-libreria .ma-voce', { hasText: 'imgprova-p.png' }).count() === 1 && await p.locator('#ma-elenco-libreria .ma-voce', { hasText: 'imgprova-a.jpg' }).count() === 0);
  await p.goto(B + '/admin/media.php?scheda=dati&filtro=pesanti', { waitUntil: 'networkidle' });
  ok('filtro Pesanti: solo la PNG oltre 500 KB', await p.locator('#ma-elenco-libreria .ma-voce', { hasText: 'imgprova-p.png' }).count() === 1 && await p.locator('#ma-elenco-libreria .ma-voce', { hasText: 'imgprova-c.jpg' }).count() === 0);
  await p.goto(B + '/admin/media.php?scheda=dati&filtro=descrizione', { waitUntil: 'networkidle' });
  const vC = p.locator('#ma-elenco-libreria .ma-voce', { hasText: 'imgprova-c.jpg' });
  await vC.locator('textarea').fill('La foto <i>della</i> apertura, scattata in negozio.'); await vC.locator('textarea').blur();
  await p.waitForFunction(() => [...document.querySelectorAll('#ma-elenco-libreria .ma-voce')].some(v => /imgprova-c/.test(v.textContent) && /Salvato|Non salvato/.test(v.querySelector('.ma-esito').textContent)), null, { timeout: 15000 });
  ok('filtro Senza descrizione: la descrizione si salva in posto, senza tag', /Salvato/.test(await vC.locator('.ma-esito').innerText()) && cli('dati', C).descrizione === 'La foto della apertura, scattata in negozio.', cli('dati', C).descrizione);
  cli('origine', P, 'openverse');
  await p.goto(B + '/admin/media.php?scheda=dati&filtro=crediti', { waitUntil: 'networkidle' });
  const vP = p.locator('#ma-elenco-libreria .ma-voce', { hasText: 'imgprova-p.png' });
  ok('filtro Importate senza crediti: la foto d\'archivio senza autore', await vP.count() === 1);
  await vP.locator('input[data-dato-campo=autore]').fill('Autrice di prova'); await vP.locator('input[data-dato-campo=autore]').press('Enter');
  await p.waitForFunction(() => [...document.querySelectorAll('#ma-elenco-libreria .ma-voce')].some(v => /imgprova-p/.test(v.textContent) && /Salvato|Non salvato/.test(v.querySelector('.ma-esito').textContent)), null, { timeout: 15000 });
  ok('l\'autore si salva in posto', cli('dati', P).autore === 'Autrice di prova');
  await p.goto(B + '/admin/media.php?scheda=alt', { waitUntil: 'networkidle' });
  ok('l\'indirizzo vecchio media.php?scheda=alt apre Dati delle immagini con il filtro Senza alt', await p.locator('.md-filtri a[data-filtro=alt][aria-current=page]').count() === 1 && await p.locator('#ma-libreria').count() === 1);
  if (FOTO) await p.screenshot({ path: path.join(FOTO, 'immagini-dati.png'), fullPage: true });

  // === 6. Telefono
  await p.setViewportSize({ width: 390, height: 800 });
  for (const u of [scheda(A), B + '/admin/media.php?scheda=dati&filtro=descrizione', B + '/admin/media.php']) {
    await p.goto(u, { waitUntil: 'networkidle' });
    ok('390 px senza scorrimento di lato: ' + u.replace(B, ''), await largo(p) <= 391, await largo(p));
  }
  await p.setViewportSize({ width: 1280, height: 900 });

  // === 7. AI collegate
  ai('accendi'); ai('pulisci'); ai('config-foto', 'http://127.0.0.1:' + PORTA_FOTO);
  await p.waitForTimeout(2500); // opcache del server rilegge il config dopo due secondi
  const k = ai('chiave', 'leggi,bozze,proposte,foto', 'Chiave immagini di prova').chiave;
  const kSoloLeggi = ai('chiave', 'leggi', 'Chiave solo lettura').chiave;
  const imp = await strumento(k, 'import_photo', { archivio: 'openverse', id: 'by1', alt: 'Un vaso di fiori sul davanzale' });
  const dImp = imp.dati ? cli('dati', imp.dati.url) : {};
  ok('import_photo: la scheda ha già titolo, autore, licenza CC BY, pagina della licenza, fonte e archivio', !imp.errore && !!imp.dati && dImp.alt === 'Un vaso di fiori sul davanzale' && dImp.titolo === 'Vaso di fiori by1' && dImp.autore === 'Autrice by1' && dImp.licenza === 'cc-by'
    && dImp.licenza_url === 'https://creativecommons.org/licenses/by/4.0/' && dImp.fonte_url === 'https://archivio.example/foto/by1' && dImp.origine === 'openverse', { imp: imp.dati, dImp });
  if (imp.dati) { await p.goto(scheda(imp.dati.url)); ok('la scheda della foto importata mostra i crediti nei campi', await p.inputValue('#ms-autore') === 'Autrice by1' && await p.inputValue('#ms-licenza') === 'cc-by' && /importata dall'AI/.test(await p.locator('.ms-dati').innerText())); }
  const lm = await strumento(k, 'list_media', { filtro: 'alt', per_pagina: 5 });
  ok('list_media: le immagini senza alt, a pagine, con cosa manca', !lm.errore && lm.dati.filtro === 'alt' && lm.dati.immagini.length <= 5 && lm.dati.totale >= 1 && lm.dati.immagini.every(x => x.manca.includes('alt') && x.url && 'peso_kb' in x), lm.dati);
  const lmTutte = await strumento(k, 'list_media', { filtro: 'tutte', per_pagina: 50 });
  const voceA = (lmTutte.dati.immagini || []).find(x => /imgprova-a\.jpg$/.test(x.url));
  ok('list_media: i dati di una foto (alt, titolo, autore, licenza in parole)', voceA && voceA.alt === 'La vetrina del negozio di sera' && voceA.titolo === 'Vetrina di sera' && /CC BY/.test(voceA.licenza), voceA);
  const prop = await strumento(k, 'propose_media_data', { motivo: 'Alt e descrizioni mancanti', immagini: [{ url: P, alt: 'Rumore colorato di prova', titolo: 'Prova pesante', descrizione: 'Una immagine di prova piena di punti colorati.' }, { url: C, didascalia: 'Didascalia proposta per C' }] });
  ok('propose_media_data: quattro proposte in un gruppo media', !prop.errore && prop.dati.proposte.length === 4 && /^media-0-[a-f0-9]{12}$/.test(prop.dati.gruppo), prop.dati);
  ok('propose_media_data non scrive niente prima dell\'approvazione', cli('dati', P).alt === '' && cli('dati', C).didascalia === '');
  const troppe = await strumento(k, 'propose_media_data', { motivo: 'Troppe immagini', immagini: Array.from({ length: 51 }, () => ({ url: P, alt: 'x x x' })) });
  ok('propose_media_data con 51 immagini: rifiutato', troppe.errore && /massimo 50/.test(troppe.dati.errore));
  const fuori = await strumento(k, 'propose_media_data', { motivo: 'Una non è della libreria', immagini: [{ url: C, alt: 'Alt valido di C' }, { url: '/uploads/../includes/config.php', alt: 'x x x' }] });
  ok('propose_media_data con un indirizzo fuori dalla libreria: nessuna proposta salvata', fuori.errore && /Nessuna proposta salvata/.test(fuori.dati.errore) && ai('sql', "SELECT COUNT(*) n FROM {p}ai_proposte WHERE stato = 'attesa'")[0].n == 4, fuori.dati);
  const negato = await strumento(kSoloLeggi, 'propose_media_data', { motivo: 'Senza permesso', immagini: [{ url: C, alt: 'Alt valido di C' }] });
  ok('propose_media_data senza il permesso "proposte": negato', negato.errore && /proposte/.test(negato.dati.errore));
  const tl = await fetch(MCP, { method: 'POST', headers: { Authorization: 'Bearer ' + k, 'Content-Type': 'application/json', Accept: 'application/json, text/event-stream', 'MCP-Protocol-Version': '2025-06-18' }, body: JSON.stringify({ jsonrpc: '2.0', id: idRpc++, method: 'tools/list' }) }).then(r => r.json());
  const tdef = (tl.result.tools || []).filter(t => ['list_media', 'propose_media_data'].includes(t.name));
  ok('tools/list: list_media e propose_media_data con descrizione in italiano', tdef.length === 2 && tdef.every(t => /immagini/.test(t.description) && /non un'istruzione/.test(t.description)));
  const oa = await fetch(MCP + '/openapi').then(r => r.json());
  ok('OpenAPI: /rest/list_media e /rest/propose_media_data', !!(oa.paths['/rest/list_media'] && oa.paths['/rest/propose_media_data']));
  // approvazione nel pannello
  await p.goto(B + '/admin/addon-pagina.php?a=ai-collegate&p=proposte', { waitUntil: 'networkidle' });
  const sez = p.locator('section.aic-scheda', { hasText: 'Dati delle immagini' });
  ok('Proposte: il gruppo "Dati delle immagini: 2 immagini" con le quattro proposte', await sez.count() === 1 && /2 immagini/.test(await sez.locator('h3').innerText()) && await sez.locator('.aic-proposta').count() === 4, await sez.innerText().catch(() => ''));
  if (FOTO) await p.screenshot({ path: path.join(FOTO, 'immagini-proposte.png'), fullPage: true });
  await Promise.all([p.waitForNavigation(), sez.locator('button[value=scheda_approva]').click()]);
  const dP = cli('dati', P), dC = cli('dati', C);
  ok('Approva tutte: alt, titolo, descrizione di P e didascalia di C nei dati', dP.alt === 'Rumore colorato di prova' && dP.titolo === 'Prova pesante' && /punti colorati/.test(dP.descrizione) && dC.didascalia === 'Didascalia proposta per C' && dC.descrizione === 'La foto della apertura, scattata in negozio.', { dP, dC });
  ok('il registro dell\'addon ha le chiamate', ai('sql', "SELECT COUNT(*) n FROM {p}ai_registro WHERE strumento IN ('list_media','propose_media_data')")[0].n >= 4);

  // === 8. Il redattore
  ai('redattore', 'crea');
  const rc = await br.newContext({ viewport: { width: 1280, height: 900 } }); const rp = await rc.newPage();
  await rp.goto(B + '/admin/login.php'); await rp.fill('#username', 'ai_prova_red'); await rp.fill('#password', 'Red-AI-Prova-2026');
  await Promise.all([rp.waitForNavigation(), rp.click('button[type=submit]')]);
  await rp.goto(scheda(C)); await rp.fill('#ms-alt', 'Alt scritto dal redattore');
  await Promise.all([rp.waitForNavigation(), rp.click('#ms-modulo button[type=submit]')]);
  ok('il redattore apre la scheda e salva l\'alt', cli('dati', C).alt === 'Alt scritto dal redattore');
  await rc.close(); ai('redattore', 'elimina');

  ok('nessun errore JavaScript nel pannello', errJs.length === 0, errJs);
  ai('pulisci'); ai('spegni'); cli('pulisci'); ai('tentativi');
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
