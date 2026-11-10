// Gli strumenti SEO (2.29.0), come li userebbe una persona, su un sito col kit Attività.
//  1. La Tabella SEO (SEO e AEO > Tabella SEO): i semafori giusti su una pagina senza descrizione, su un titolo doppio, su un
//     contenuto a posto; la modifica nella riga (titolo per Google e descrizione) salvata senza ricaricare e visibile nel
//     sito (<title> e meta description); i filtri "solo problemi", tipo e ricerca.
//  2. I link interni suggeriti nell'editor: un articolo con la keyword, il riquadro Collega con le pagine suggerite, Copia il
//     link (gli appunti), Inserisci nel Testo selezionato, la riga dei link interni verde dopo il collegamento, il link sul sito.
//  3. I dati per Google in più: Prodotto nella scheda di un prodotto della Vetrina (addon acceso dal pannello), How-to e Video
//     scelti dal riquadro SEO della scheda di una pagina, i nodi nel JSON-LD del sito, un video javascript: rifiutato,
//     un contenuto senza niente scelto identico a prima.
//  4. Le immagini senza alt: una in una pagina e una in libreria (Media > Senza alt), la correzione in posto, la colonna della
//     Tabella SEO che torna a 0 e l'alt che esce nel sito.
//  Poi il redattore con i suoi limiti, 1280 e 390 px senza scorrimento di lato, la pulizia finale.
// uso: node prove/prova-seo-strumenti.js <base> <cartella CMS> <utente> <password> [cartella foto]
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, FOTO] = process.argv.slice(2);
if (!B || !DIR || !UTENTE || !PASSWORD) { console.error('uso: node prove/prova-seo-strumenti.js <base> <cartella CMS> <utente> <password> [cartella foto]'); process.exit(2); }
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? '  ' + JSON.stringify(extra).slice(0, 300) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, 'seo-strumenti-cli.php'), DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }).trim().split('\n').pop());
const foto = async (p, nome) => { if (FOTO) await p.screenshot({ path: path.join(FOTO, nome + '.png'), fullPage: true }); };
const RED = 'seo_prova_red', REDPW = 'Red-Seo-Prova-2026';
const PERCORSO = new URL(B).pathname.replace(/\/$/, '');
const grafo = async (p, u) => { const r = await p.request.get(B + u); const h = await r.text(); const m = h.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/); try { return m ? JSON.parse(m[1])['@graph'] : null; } catch (e) { return null; } };
const html = async (p, u) => (await (await p.request.get(B + u)).text()).replace(/\?v=\d+/g, '').replace(/ nonce="[^"]*"/g, '');
const entra = async (br, utente, password, vp = { width: 1280, height: 900 }) => {
  const c = await br.newContext({ viewport: vp, permissions: ['clipboard-read', 'clipboard-write'] }); const p = await c.newPage();
  pnConferme.on(p, d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', utente); await p.fill('#password', password);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  return { c, p, dentro: !p.url().includes('login.php') };
};

(async () => {
  const br = await chromium.launch();
  // Pulizia: niente avanzi di un giro prima.
  const pulisci = () => {
    for (const slug of ['prova-seo-vasi', 'prova-seo-doppio', 'prova-seo-terracotta', 'prova-seo-video']) cli('sql', `DELETE FROM {p}pages WHERE slug = '${slug}'`);
    cli('sql', "DELETE FROM {p}articles WHERE slug = 'prova-seo-vaso'");
    cli('sql', "UPDATE {p}pages SET seo_title = '', seo_description = '', meta_description = '' WHERE slug IN ('chi-siamo', 'blog')");
    cli('alt_azzera');
    cli('redattore', 'elimina');
  };
  pulisci();
  // Due foto nella libreria (PNG minimi in uploads/pagine): una va nel widget Immagine e nel prodotto, l'altra resta solo in libreria.
  const IMG1 = cli('foto', 'prova-seo-1').url, IMG2 = cli('foto', 'prova-seo-2').url;
  const idChi = +cli('sql', "SELECT id FROM {p}pages WHERE slug = 'chi-siamo'")[0].id;
  // I contenuti di prova: una pagina sui vasi con un'immagine senza alt, una pagina con il titolo doppio di "Chi siamo", una
  // sulla terracotta, una per il video, un articolo fatto con l'editor con la keyword "vaso in terracotta".
  const vasi = cli('pagina', 'prova-seo-vasi', 'Vasi in terracotta per il giardino', JSON.stringify([
    { tipo: 'titolo', impostazioni: { testo: 'Vasi in terracotta per il giardino', livello: 'h1' } },
    { tipo: 'testo', impostazioni: { contenuto: '<p>I vasi in terracotta fatti a mano, per il giardino e il terrazzo: misure, colori e prezzi.</p>' } },
    { tipo: 'immagine', impostazioni: { immagine: IMG1, alt: '' } },
  ]));
  const doppio = cli('pagina', 'prova-seo-doppio', 'Chi siamo', JSON.stringify([{ tipo: 'testo', impostazioni: { contenuto: '<p>Una pagina con lo stesso titolo di un\'altra.</p>' } }]));
  const terra = cli('pagina', 'prova-seo-terracotta', 'La nostra terracotta: come nasce un vaso', JSON.stringify([{ tipo: 'testo', impostazioni: { contenuto: '<p>Dalla creta al forno: come nasce la terracotta dei nostri vasi.</p>' } }]));
  const video = cli('pagina', 'prova-seo-video', 'Il laboratorio in video', JSON.stringify([{ tipo: 'testo', impostazioni: { contenuto: '<p>Un giro nel laboratorio.</p>' } }]));
  const art = cli('articolo', 'prova-seo-vaso', 'Come scegliere un vaso in terracotta', 'vaso in terracotta', 'Come scegliere un vaso in terracotta per le piante del terrazzo: misura, spessore, foro di scolo, e dove comprarlo bene.', JSON.stringify([
    { tipo: 'titolo', impostazioni: { testo: 'Come scegliere un vaso in terracotta', livello: 'h1' } },
    { tipo: 'testo', impostazioni: { contenuto: '<p>Un vaso in terracotta respira: la pianta sta meglio. Ecco come scegliere la misura e lo spessore giusti per il terrazzo.</p>' } },
  ]));
  ok('pagine e articolo di prova creati dalla riga di comando', vasi.id > 0 && doppio.id > 0 && terra.id > 0 && video.id > 0 && art.id > 0, [vasi, doppio, terra, video, art]);
  const anon = await br.newContext();
  const serviziPrima = await html(anon, '/servizi/').catch(() => '');
  const serviziLdPrima = (serviziPrima.match(/<script type="application\/ld\+json">[\s\S]*?<\/script>/) || [''])[0];
  await anon.close();

  const admin = await entra(br, UTENTE, PASSWORD);
  ok('login amministratore', admin.dentro, admin.p.url());
  const p = admin.p;
  const err = []; p.on('pageerror', e => err.push(p.url() + ' ' + e.message.slice(0, 160)));

  // === 1. La Tabella SEO
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('il menu SEO e AEO ha la voce Tabella SEO', await p.locator('.admin-sidebar a[href="seo-tabella.php"]').count() === 1);
  await p.goto(B + '/admin/seo.php?scheda=panoramica', { waitUntil: 'networkidle' });
  ok('la fila delle schede di SEO e AEO ha Tabella SEO', await p.locator('.seo-schede a[href="seo-tabella.php"]').count() === 1);
  const r1 = await p.goto(B + '/admin/seo-tabella.php', { waitUntil: 'networkidle' });
  ok('seo-tabella.php si apre (200) con una riga per contenuto, bozze comprese, e la fila delle schede', r1.status() === 200 && await p.locator('#st-tabella tbody tr').count() >= 10 && await p.locator('.imp-schede a.attiva[href="seo-tabella.php"]').count() === 1, await p.locator('#st-tabella tbody tr').count());
  const riga = (tipo, id) => p.locator(`#st-tabella tr[data-tipo="${tipo}"][data-id="${id}"]`);
  const sem = async (tipo, id) => { const r = riga(tipo, id); return [await r.locator('td.st-campo').nth(0).locator('.st-sem').getAttribute('data-sem'), await r.locator('td.st-campo').nth(1).locator('.st-sem').getAttribute('data-sem'), await r.locator('td.st-campo').nth(0).locator('.st-sem').innerText(), await r.locator('td.st-campo').nth(1).locator('.st-sem').innerText()]; };
  const sBlog = await sem('pagine', +cli('sql', "SELECT id FROM {p}pages WHERE slug = 'blog'")[0].id);
  ok('Blog: titolo giallo (corto) e descrizione rossa (manca), detto anche in parole', sBlog[0] === 'giallo' && sBlog[1] === 'rosso' && /corto/.test(sBlog[2]) && /manca/.test(sBlog[3]), sBlog);
  const sChi = await sem('pagine', idChi), sDop = await sem('pagine', doppio.id);
  ok('Chi siamo e la pagina con lo stesso titolo: titolo rosso "uguale a quello di" in tutte e due', sChi[0] === 'rosso' && sDop[0] === 'rosso' && /uguale a quello di/.test(sChi[2]) && /uguale a quello di/.test(sDop[2]), [sChi[2], sDop[2]]);
  const sArt = await sem('articoli', art.id);
  ok('l\'articolo con titolo e descrizione giusti: tutto verde, con i caratteri contati', sArt[0] === 'verde' && sArt[1] === 'verde' && /^\u2713\s+Titolo a posto:\s+\d+ caratteri$/.test(sArt[2].replace(/\n/g, ' ')) && /\d+ caratteri/.test(sArt[3]), sArt);
  const sVasi = await sem('pagine', vasi.id);
  ok('la pagina dei vasi: titolo verde, descrizione rossa, 1 immagine senza alt nella colonna', sVasi[0] === 'verde' && sVasi[1] === 'rosso' && await riga('pagine', vasi.id).locator('td.st-alt').innerText() === '1', sVasi);
  ok('le righe con problemi stanno prima di quelle a posto', +await p.locator('#st-tabella tbody tr').first().getAttribute('data-problemi') > 0 && +await p.locator('#st-tabella tbody tr').last().getAttribute('data-problemi') === 0);
  await foto(p, 'seo-tabella');
  // Il filtro "solo problemi": l'articolo a posto sparisce.
  await p.check('#st-solo-problemi'); await p.waitForTimeout(150);
  ok('"Solo i contenuti con problemi": l\'articolo a posto sparisce, le altre restano', await riga('articoli', art.id).isHidden() && await riga('pagine', idChi).isVisible() && await riga('pagine', vasi.id).isVisible());
  await p.uncheck('#st-solo-problemi');
  await p.fill('#st-cerca', 'vasi'); await p.waitForTimeout(150);
  ok('la ricerca "vasi" lascia solo la pagina dei vasi', await riga('pagine', vasi.id).isVisible() && await riga('pagine', idChi).isHidden() && await p.locator('#st-tabella tbody tr:not([hidden])').count() === 1);
  await p.fill('#st-cerca', 'zzzz'); await p.waitForTimeout(150);
  ok('una ricerca senza risultati lo dice e suggerisce cosa fare', await p.locator('#st-nessuno').isVisible() && /parola più corta/.test(await p.locator('#st-nessuno').innerText()));
  await p.fill('#st-cerca', ''); await p.selectOption('#st-tipo', 'articoli'); await p.waitForTimeout(150);
  ok('il filtro per tipo Articoli lascia solo gli articoli', (await p.locator('#st-tabella tbody tr:not([hidden])').evaluateAll(l => l.map(r => r.getAttribute('data-tipo')))).every(t => t === 'articoli'));
  await p.selectOption('#st-tipo', '');
  // La modifica nella riga: Chi siamo riceve un titolo per Google e una descrizione, salvati senza ricaricare.
  const TIT = 'Chi siamo: lo studio, le persone e il metodo di lavoro';
  const DESC = 'Lo Studio Prova in due righe: chi siamo, da quanto lavoriamo, come seguiamo ogni cliente dal primo incontro alla consegna.';
  const tChi = riga('pagine', idChi).locator('input[data-campo=seo_title]');
  await tChi.fill(TIT); await tChi.press('Enter');
  await p.waitForFunction((id) => { const e = document.querySelector(`#st-tabella tr[data-tipo="pagine"][data-id="${id}"] td.st-campo:nth-child(2) .st-esito`); return e && /Salvato|Non salvato/.test(e.textContent); }, idChi, { timeout: 15000 });
  const esitoT = await riga('pagine', idChi).locator('td.st-campo').nth(0).locator('.st-esito').innerText();
  const sChi2 = await sem('pagine', idChi), sDop2 = await sem('pagine', doppio.id);
  ok('titolo per Google scritto nella riga e salvato con Invio: "Salvato", semaforo verde, e l\'altra pagina non è più "uguale" (giallo: corto)', esitoT === 'Salvato' && sChi2[0] === 'verde' && sDop2[0] === 'giallo', [esitoT, sChi2[2], sDop2[2]]);
  const dChi = riga('pagine', idChi).locator('textarea[data-campo=descrizione]');
  await dChi.fill(DESC); await dChi.evaluate(e => e.dispatchEvent(new Event('change', { bubbles: true })));
  await p.waitForFunction((id) => { const e = document.querySelector(`#st-tabella tr[data-tipo="pagine"][data-id="${id}"] td.st-campo:nth-child(3) .st-esito`); return e && /Salvato|Non salvato/.test(e.textContent); }, idChi, { timeout: 15000 });
  const sChi3 = await sem('pagine', idChi);
  ok('descrizione scritta nella riga e salvata uscendo dalla casella: semaforo verde con i caratteri', sChi3[1] === 'verde' && /caratteri/.test(sChi3[3]) && /Salvato/.test(await riga('pagine', idChi).locator('td.st-campo').nth(1).locator('.st-esito').innerText()), sChi3);
  ok('il contatore "con qualcosa da sistemare" è sceso', +await p.locator('#st-n-problemi').innerText() < +await p.locator('#st-n-tutti').innerText());
  const chiHtml = await html(p, '/chi-siamo/');
  ok('sul sito la pagina Chi siamo ha il nuovo <title> e la meta description scritti dalla tabella', chiHtml.includes('<title>' + TIT + '</title>') && chiHtml.includes('name="description" content="' + DESC + '"'), (chiHtml.match(/<title>[^<]*<\/title>/) || [''])[0]);
  const rigaDb = cli('sql', `SELECT seo_title, seo_description, meta_description, LENGTH(struttura) s FROM {p}pages WHERE id = ${idChi}`)[0];
  ok('nel database: titolo per Google in seo_title, la descrizione nella colonna che legge l\'editor (seo_description), l\'albero intatto', rigaDb.seo_title === TIT && rigaDb.seo_description === DESC && +rigaDb.s > 100, rigaDb);
  ok('una revisione della scheda è stata lasciata (la stessa strada della scheda)', +cli('sql', `SELECT COUNT(*) c FROM {p}revisioni WHERE tipo = 'pagine' AND contenuto_id = ${idChi}`)[0].c >= 1);
  await p.reload({ waitUntil: 'networkidle' });
  ok('dopo la ricarica la riga mostra i valori salvati', await riga('pagine', idChi).locator('input[data-campo=seo_title]').inputValue() === TIT && await riga('pagine', idChi).locator('textarea[data-campo=descrizione]').inputValue() === DESC);

  // === 2. I link interni suggeriti nell'editor
  await p.goto(B + '/admin/editor.php?tipo=articoli&id=' + art.id, { waitUntil: 'networkidle' });
  await p.waitForFunction(() => document.querySelector('#ed-iframe') && document.querySelector('#ed-iframe').contentDocument && document.querySelector('#ed-iframe').contentDocument.querySelector('.ed-sezione'));
  await p.waitForTimeout(600);
  if (await p.locator('#ed-chiudi-revisioni').isVisible()) await p.click('#ed-chiudi-revisioni');
  await p.click('#ed-apri-impostazioni');
  await p.waitForSelector('#ed-seo .seo-collega', { timeout: 15000 });
  const collega = await p.locator('#ed-seo .seo-collega').innerText();
  const vociSug = await p.locator('#ed-seo .seo-collega li').allInnerTexts();
  ok('Impostazioni dell\'editor: il riquadro Collega dice che le pagine parlano di «vaso in terracotta» e propone i vasi e la terracotta (non l\'articolo stesso)', /parlano di «vaso in terracotta»/.test(collega) && vociSug.some(v => /Vasi in terracotta per il giardino/.test(v)) && vociSug.some(v => /La nostra terracotta/.test(v)) && !vociSug.some(v => /Come scegliere un vaso/.test(v)), vociSug);
  const rigaLink = await p.locator('#ed-seo li', { hasText: 'Collegamenti interni' }).innerText();
  ok('la riga dell\'analisi dice "Collegamenti interni: 0 nel testo; 2 pagine parlano di ..., collegale"', /Collegamenti interni: 0 nel testo; 2 pagine parlano di «vaso in terracotta», collegale/.test(rigaLink), rigaLink);
  await foto(p, 'editor-collega');
  const btnCopia = p.locator('#ed-seo [data-copia-link]').first();
  const urlCopia = await btnCopia.getAttribute('data-copia-link');
  await btnCopia.click(); await p.waitForTimeout(300);
  const appunti = await p.evaluate(() => navigator.clipboard.readText().catch(() => ''));
  ok('Copia il link: il pulsante dice "Copiato" e negli appunti c\'è l\'indirizzo intero della pagina', await btnCopia.innerText() === 'Copiato' && appunti === urlCopia && /^https?:\/\/.+\/prova-seo-(vasi|terracotta)\/$/.test(appunti), [appunti, urlCopia]);
  // Inserisci senza un Testo selezionato: lo dice, non inserisce niente.
  const btnIns = p.locator('#ed-seo [data-inserisci-link]').first();
  const titoloIns = await btnIns.getAttribute('data-inserisci-titolo'), percorsoIns = await btnIns.getAttribute('data-inserisci-link');
  await btnIns.click(); await p.waitForTimeout(200);
  ok('Inserisci senza un widget Testo selezionato: il riquadro spiega cosa fare e non cambia niente', /Seleziona prima un widget Testo/.test(await p.locator('#ed-seo .seo-collega-esito').innerText()) && await btnIns.isEnabled());
  await p.click('#ed-dialogo-impostazioni [data-chiudi]');
  const fr = p.frameLocator('#ed-iframe');
  await fr.locator('.ed-widget.ed-w-testo').first().click(); await p.waitForTimeout(400);
  ok('il widget Testo si seleziona nella tela', /Testo/.test(await p.locator('#ed-modifica-nome').innerText()), await p.locator('#ed-modifica-nome').innerText());
  await p.click('#ed-apri-impostazioni');
  await p.waitForSelector('#ed-seo [data-inserisci-link]', { timeout: 15000 });
  await p.locator('#ed-seo [data-inserisci-link]').first().click();
  await p.waitForTimeout(800);
  await p.waitForFunction(() => { const l = [...document.querySelectorAll('#ed-seo li')].find(x => /Collegamenti interni/.test(x.textContent)); return l && /già collegat/.test(l.textContent); }, null, { timeout: 15000 });
  const rigaLink2 = await p.locator('#ed-seo li', { hasText: 'Collegamenti interni' }).innerText();
  const colore2 = await p.locator('#ed-seo li', { hasText: 'Collegamenti interni' }).locator('span').first().evaluate(e => e.style.color);
  ok('Inserisci nel Testo selezionato: la riga diventa "1 nel testo; ... 1 già collegata" e verde', /Collegamenti interni: 1 nel testo/.test(rigaLink2) && /1 già collegata/.test(rigaLink2) && /successo/.test(colore2), [rigaLink2, colore2]);
  ok('la pagina inserita non è più fra quelle da collegare', !(await p.locator('#ed-seo .seo-collega li').allInnerTexts()).some(v => v.includes(titoloIns)));
  const testoTela = await fr.locator('.ed-widget.ed-w-testo').first().innerHTML();
  ok('nella tela il widget Testo ha il link in fondo (href al percorso, testo il titolo)', testoTela.includes('href="' + percorsoIns + '"') && testoTela.includes(titoloIns), testoTela.slice(-200));
  await p.click('#ed-dialogo-impostazioni [data-chiudi]');
  await p.click('#ed-pubblica'); await p.waitForTimeout(1500);
  const artHtml = await html(p, '/prova-seo-vaso/');
  ok('pubblicato: sul sito l\'articolo ha il link alla pagina suggerita', artHtml.includes('href="' + PERCORSO + percorsoIns + '"') && artHtml.includes('>' + titoloIns + '</a>'), (artHtml.match(/href="[^"]*prova-seo-[a-z]+\/"/g) || []).slice(0, 3));
  const sugCli = cli('suggeriti', 'articoli', art.id);
  ok('dalla riga di comando: 2 suggeriti, 1 collegato, 1 da collegare', sugCli.voci.length === 2 && sugCli.collegati === 1 && sugCli.da_collegare === 1, sugCli);
  // Il riquadro Collega anche nella scheda classica, con Copia il link.
  await p.goto(B + '/admin/contenuto.php?tipo=articoli&id=' + art.id, { waitUntil: 'networkidle' });
  ok('la scheda classica mostra il riquadro Collega con Copia il link e senza Inserisci (che è dell\'editor)', await p.locator('#seo-controllo .seo-collega [data-copia-link]').count() >= 1 && await p.locator('#seo-controllo [data-inserisci-link]').count() === 0);
  await p.click('#seo-ricontrolla'); await p.waitForTimeout(1500);
  ok('Ricontrolla nella scheda: il riquadro Collega resta (tipo e id del contenuto passano all\'analisi, l\'articolo stesso non si suggerisce)', await p.locator('#seo-controllo .seo-collega [data-copia-link]').count() >= 1 && !(await p.locator('#seo-controllo .seo-collega').innerText()).includes('Come scegliere un vaso'));

  // === 3. I dati per Google in più
  // Prodotto nella scheda della pagina dei vasi.
  await p.goto(B + '/admin/contenuto.php?tipo=pagine&id=' + vasi.id, { waitUntil: 'networkidle' });
  // 2.35.0: c'è anche Programma (SoftwareApplication, prova-seo-plume.js).
  ok('la scheda ha la tendina "Dati per Google in più" con Nessuno, Prodotto, How-to, Video, Programma, e i campi nascosti', await p.locator('#sc-schema-tipo option').count() === 5 && await p.locator('[data-schema-campi=prodotto]').isHidden() && await p.locator('[data-schema-campi=programma]').isHidden());
  await p.selectOption('#sc-schema-tipo', 'prodotto');
  ok('scelto Prodotto compaiono i suoi campi', await p.locator('[data-schema-campi=prodotto]').isVisible() && await p.locator('[data-schema-campi=howto]').isHidden());
  await p.fill('#sc-schema-prodotto-nome', 'Vaso in terracotta 30 cm'); await p.fill('#sc-schema-prodotto-prezzo', '24,90'); await p.selectOption('#sc-schema-prodotto-disponibilita', 'InStock');
  await p.fill('#sc-schema-prodotto-codice', 'VT-30'); await p.fill('#sc-schema-prodotto-marca', 'Studio Prova'); await p.fill('#sc-schema-prodotto-foto', IMG1);
  await foto(p, 'scheda-prodotto');
  await Promise.all([p.waitForNavigation(), p.click('button[data-valore=pubblica]')]);
  const fl3 = (await p.locator('.flash').allInnerTexts()).join(' | ');
  ok('salvata la scheda: messaggio verde, nessun errore sui dati in più', /aggiornata/.test(fl3) && !/non sono stati salvati/.test(fl3), fl3);
  ok('la tendina ricorda Prodotto con i campi', await p.locator('#sc-schema-tipo').inputValue() === 'prodotto' && await p.locator('#sc-schema-prodotto-prezzo').inputValue() === '24.90');
  const gVasi = await grafo(p, '/prova-seo-vasi/');
  const prod = gVasi && gVasi.find(x => x['@type'] === 'Product');
  const wpVasi = gVasi && gVasi.find(x => x['@type'] === 'WebPage');
  ok('JSON-LD della pagina: nodo Product con name, image, description, sku, brand, offers (price 24.90, EUR, InStock, url) e la WebPage lo indica in mainEntity', !!prod && prod.name === 'Vaso in terracotta 30 cm' && /prova-seo-1\.png$/.test(prod.image || '') && prod.sku === 'VT-30' && prod.brand && prod.brand.name === 'Studio Prova' && prod.offers && prod.offers.price === '24.90' && prod.offers.priceCurrency === 'EUR' && prod.offers.availability === 'https://schema.org/InStock' && /prova-seo-vasi\/$/.test(prod.offers.url) && wpVasi && wpVasi.mainEntity && wpVasi.mainEntity['@id'] === prod['@id'], prod);
  // How-to nella pagina della terracotta, con tre passi e un quarto aggiunto dal pulsante.
  await p.goto(B + '/admin/contenuto.php?tipo=pagine&id=' + terra.id, { waitUntil: 'networkidle' });
  await p.selectOption('#sc-schema-tipo', 'howto');
  await p.fill('#sc-schema-howto-nome', 'Come nasce un vaso in terracotta'); await p.fill('#sc-schema-howto-tempo', '90');
  await p.fill('#sc-schema-passi .sc-schema-passo:nth-child(1) input', 'Impastare la creta'); await p.fill('#sc-schema-passi .sc-schema-passo:nth-child(1) textarea', 'Si lavora la creta finché è liscia e senza bolle.');
  await p.click('[data-schema-aggiungi-passo]'); await p.fill('#sc-schema-passi .sc-schema-passo:nth-child(2) input', 'Modellare al tornio'); await p.fill('#sc-schema-passi .sc-schema-passo:nth-child(2) textarea', 'Il vaso prende forma sul tornio.');
  await p.click('[data-schema-aggiungi-passo]'); await p.fill('#sc-schema-passi .sc-schema-passo:nth-child(3) input', 'Cuocere nel forno'); await p.fill('#sc-schema-passi .sc-schema-passo:nth-child(3) textarea', 'Ventiquattro ore a 950 gradi.');
  ok('Aggiungi un passo: tre passi numerati 1, 2, 3', (await p.locator('#sc-schema-passi .sc-schema-n').allInnerTexts()).join('') === '123');
  await foto(p, 'scheda-howto');
  await Promise.all([p.waitForNavigation(), p.click('button[data-valore=pubblica]')]);
  const gTerra = await grafo(p, '/prova-seo-terracotta/');
  const howto = gTerra && gTerra.find(x => x['@type'] === 'HowTo');
  ok('JSON-LD: nodo HowTo con name, totalTime PT1H30M e tre HowToStep in ordine, mainEntity della WebPage', !!howto && howto.name === 'Come nasce un vaso in terracotta' && howto.totalTime === 'PT1H30M' && howto.step.length === 3 && howto.step[0]['@type'] === 'HowToStep' && howto.step[0].name === 'Impastare la creta' && howto.step[2].position === 3 && gTerra.find(x => x['@type'] === 'WebPage').mainEntity['@id'] === howto['@id'], howto && howto.step);
  // Video: prima con un indirizzo javascript: (rifiutato), poi giusto.
  await p.goto(B + '/admin/contenuto.php?tipo=pagine&id=' + video.id, { waitUntil: 'networkidle' });
  await p.selectOption('#sc-schema-tipo', 'video');
  await p.fill('#sc-schema-video-nome', 'Il laboratorio'); await p.fill('#sc-schema-video-descrizione', 'Un giro di due minuti nel laboratorio, dal tornio al forno.');
  await p.fill('#sc-schema-video-miniatura', IMG2); await p.fill('#sc-schema-video-data', '2026-11-05'); await p.fill('#sc-schema-video-url', 'javascript:alert(1)'); await p.fill('#sc-schema-video-durata', '2:05');
  await Promise.all([p.waitForNavigation(), p.click('button[data-valore=pubblica]')]);
  const flV = (await p.locator('.flash').allInnerTexts()).join(' | ');
  const gV1 = await grafo(p, '/prova-seo-video/');
  ok('un indirizzo javascript: nel video: messaggio in rosso, dati in più non salvati, nessun VideoObject sul sito', /non sono stati salvati/.test(flV) && /https/.test(flV) && !(gV1 || []).some(x => x['@type'] === 'VideoObject'), flV);
  await p.selectOption('#sc-schema-tipo', 'video');
  await p.fill('#sc-schema-video-nome', 'Il laboratorio'); await p.fill('#sc-schema-video-descrizione', 'Un giro di due minuti nel laboratorio, dal tornio al forno.');
  await p.fill('#sc-schema-video-miniatura', IMG2); await p.fill('#sc-schema-video-data', '2026-11-05'); await p.fill('#sc-schema-video-url', 'https://video.esempio.it/laboratorio.mp4'); await p.fill('#sc-schema-video-incorporo', 'https://player.esempio.it/embed/abc'); await p.fill('#sc-schema-video-durata', '2:05');
  await Promise.all([p.waitForNavigation(), p.click('button[data-valore=pubblica]')]);
  const gV2 = await grafo(p, '/prova-seo-video/');
  const vo = gV2 && gV2.find(x => x['@type'] === 'VideoObject');
  ok('JSON-LD: nodo VideoObject con name, description, thumbnailUrl, uploadDate, contentUrl, embedUrl, duration PT2M5S', !!vo && vo.name === 'Il laboratorio' && /prova-seo-2\.png$/.test(vo.thumbnailUrl) && vo.uploadDate === '2026-11-05' && vo.contentUrl === 'https://video.esempio.it/laboratorio.mp4' && vo.embedUrl === 'https://player.esempio.it/embed/abc' && vo.duration === 'PT2M5S', vo);
  ok('la durata torna nella casella come 2:05', (await p.locator('#sc-schema-video-durata').inputValue()) === '2:05');
  // Nessuno: il nodo sparisce.
  await p.selectOption('#sc-schema-tipo', '');
  await Promise.all([p.waitForNavigation(), p.click('button[data-valore=pubblica]')]);
  ok('"Nessuno" toglie il VideoObject e la colonna torna vuota', !((await grafo(p, '/prova-seo-video/')) || []).some(x => x['@type'] === 'VideoObject') && cli('sql', `SELECT COALESCE(seo_schema, '') s FROM {p}pages WHERE id = ${video.id}`)[0].s === '');
  const serviziDopo = (await html(p, '/servizi/')).match(/<script type="application\/ld\+json">[\s\S]*?<\/script>/)[0];
  ok('un contenuto senza niente scelto (Servizi) ha il JSON-LD identico al byte a prima', serviziLdPrima !== '' && serviziDopo === serviziLdPrima, [serviziLdPrima.length, serviziDopo.length]);
  // La Vetrina: il nodo Product nella scheda di un prodotto.
  await p.goto(B + '/admin/addon.php', { waitUntil: 'networkidle' });
  const accendi = p.locator('[data-addon="vetrina"] button[value=accendi]');
  const eraSpenta = await accendi.count() === 1;
  if (eraSpenta) { await Promise.all([p.waitForNavigation(), accendi.click()]); }
  const prodotto = cli('prodotto', JSON.stringify({ nome: 'Vaso prova SEO', prezzo: '19,90', codice: 'VPS-1', breve: 'Un vaso di prova per il negozio.', foto: [IMG1] }));
  const gProd = await (async () => { const r = await p.request.get(B + '/negozio/' + prodotto.slug + '/'); const h = await r.text(); const m = h.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/); return { status: r.status(), ld: m ? JSON.parse(m[1]) : null }; })();
  ok('Vetrina accesa: la scheda del prodotto ha il nodo Product con name, image, description, sku e offers (price 19.90, EUR, InStock, url)', gProd.status === 200 && gProd.ld && gProd.ld['@type'] === 'Product' && gProd.ld.name === 'Vaso prova SEO' && gProd.ld.sku === 'VPS-1' && gProd.ld.offers.price === '19.90' && gProd.ld.offers.priceCurrency === 'EUR' && gProd.ld.offers.availability === 'https://schema.org/InStock' && Array.isArray(gProd.ld.image) && gProd.ld.description === 'Un vaso di prova per il negozio.', gProd.ld);

  // === 4. Le immagini senza alt
  const r4 = await p.goto(B + '/admin/media.php?scheda=alt', { waitUntil: 'networkidle' });
  // 2.38.0: "Senza alt" è diventata Dati delle immagini; media.php?scheda=alt apre il filtro Senza alt.
  ok('Media ha la scheda Dati delle immagini (era Senza alt), che si apre (200) sul filtro Senza alt', r4.status() === 200 && await p.locator('.imp-schede a.attiva[href="media.php?scheda=dati"]').count() === 1 && await p.locator('.md-filtri a[data-filtro=alt][aria-current=page]').count() === 1);
  const voceVasi = p.locator('#ma-elenco-pagine .ma-voce', { hasText: 'Vasi in terracotta' });
  ok('"Nelle pagine" elenca l\'immagine della pagina dei vasi (widget Immagine), con il link all\'editor e la casella', await voceVasi.count() === 1 && /Immagine/.test(await voceVasi.innerText()) && await voceVasi.locator('a[href*="editor.php"]').count() === 1 && await voceVasi.locator('input[data-alt-url]').count() === 1, await voceVasi.innerText().catch(() => ''));
  ok('"Nella libreria" elenca le immagini senza alt, fra cui prova-seo-2.png', await p.locator('#ma-elenco-libreria .ma-voce', { hasText: 'prova-seo-2.png' }).count() === 1);
  await foto(p, 'media-senza-alt');
  const ALT1 = 'Un vaso in terracotta con una pianta grassa, sul terrazzo';
  await voceVasi.locator('input[data-alt-url]').fill(ALT1); await voceVasi.locator('input[data-alt-url]').press('Enter');
  await p.waitForFunction(() => [...document.querySelectorAll('#ma-elenco-pagine .ma-esito')].some(e => /Salvato|Non salvato/.test(e.textContent)), null, { timeout: 15000 });
  ok('alt scritto nella riga "Nelle pagine" e salvato senza ricaricare', /^Salvato/.test(await voceVasi.locator('.ma-esito').innerText()), await voceVasi.locator('.ma-esito').innerText());
  const vociLib = p.locator('#ma-elenco-libreria .ma-voce', { hasText: 'prova-seo-1.png' });
  ok('la stessa immagine nella libreria mostra l\'alt appena scritto', await vociLib.count() === 1 && await vociLib.locator('input').inputValue() === ALT1);
  const ALT2 = 'Il laboratorio, con il tornio e gli scaffali dei vasi';
  const voce2 = p.locator('#ma-elenco-libreria .ma-voce', { hasText: 'prova-seo-2.png' });
  await voce2.locator('input').fill(ALT2); await voce2.locator('input').dispatchEvent('change');
  await p.waitForFunction(() => { const v = [...document.querySelectorAll('#ma-elenco-libreria .ma-voce')].find(x => /prova-seo-2\.png/.test(x.textContent)); return v && /Salvato|Non salvato/.test(v.querySelector('.ma-esito').textContent); }, null, { timeout: 15000 });
  ok('alt scritto nella libreria per prova-seo-2.png: "Salvato"', /^Salvato/.test(await voce2.locator('.ma-esito').innerText()));
  await p.reload({ waitUntil: 'networkidle' });
  ok('dopo la ricarica: niente più immagini nelle pagine, le due foto fuori dall\'elenco senza alt, 2 con l\'alt', await p.locator('#ma-elenco-pagine').count() === 0 && await p.locator('#ma-elenco-libreria .ma-voce', { hasText: 'prova-seo-1.png' }).count() === 0 && await p.locator('#ma-elenco-libreria .ma-voce', { hasText: 'prova-seo-2.png' }).count() === 0 && (await p.locator('#ma-n-con').innerText()) === '2');
  const vasiHtml = await html(p, '/prova-seo-vasi/');
  ok('sul sito l\'immagine della pagina dei vasi esce con l\'alt della libreria', vasiHtml.includes('alt="' + ALT1 + '"'), (vasiHtml.match(/<img[^>]*prova-seo-1[^>]*>/) || [''])[0]);
  const altDb = cli('alt_libreria');
  ok('la tabella media_alt ha le due righe e media_alt_n vale 2', +cli('sql', "SELECT COUNT(*) c FROM {p}media_alt")[0].c === 2 && cli('sql', "SELECT setting_value v FROM {p}settings WHERE setting_key = 'media_alt_n'")[0].v === '2' && altDb.length >= 1);
  await p.goto(B + '/admin/seo-tabella.php', { waitUntil: 'networkidle' });
  ok('nella Tabella SEO la colonna "immagini senza alt" della pagina dei vasi è tornata a 0', await riga('pagine', vasi.id).locator('td.st-alt').innerText() === '0' && await p.locator('#st-n-alt').innerText() === '0');

  // === 5. Il redattore: la Tabella SEO sì, la configurazione no.
  cli('redattore', 'crea');
  const red = await entra(br, RED, REDPW);
  ok('il redattore di prova entra', red.dentro);
  if (red.dentro) {
    const rp = red.p;
    await rp.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
    const vociSeo = await rp.locator('.admin-sidebar a[href^="seo"]').evaluateAll(l => l.map(a => a.getAttribute('href')));
    ok('nel menu del redattore il gruppo SEO e AEO ha solo Tabella SEO', vociSeo.length >= 1 && vociSeo.every(h => h === 'seo-tabella.php'), vociSeo);
    const rr = await rp.goto(B + '/admin/seo-tabella.php', { waitUntil: 'networkidle' });
    ok('il redattore apre la Tabella SEO (200) senza la fila delle schede della configurazione', rr.status() === 200 && await rp.locator('#st-tabella').count() === 1 && await rp.locator('.imp-schede').count() === 0);
    const rSeo = await red.c.request.get(B + '/admin/seo.php', { maxRedirects: 0 });
    const rSedi = await red.c.request.get(B + '/admin/sedi.php', { maxRedirects: 0 });
    ok('seo.php e sedi.php restano chiuse al redattore (rimando alla dashboard)', [302, 303].includes(rSeo.status()) && [302, 303].includes(rSedi.status()), [rSeo.status(), rSedi.status()]);
    const campoRed = rp.locator(`#st-tabella tr[data-tipo="pagine"][data-id="${terra.id}"] input[data-campo=seo_title]`);
    await campoRed.fill('La terracotta fatta a mano: come nasce ogni vaso'); await campoRed.press('Enter');
    await rp.waitForFunction((id) => { const e = document.querySelector(`#st-tabella tr[data-tipo="pagine"][data-id="${id}"] td.st-campo:nth-child(2) .st-esito`); return e && /Salvato|Non salvato/.test(e.textContent); }, terra.id, { timeout: 15000 });
    ok('il redattore salva il titolo per Google di una pagina dalla riga', cli('sql', `SELECT seo_title t FROM {p}pages WHERE id = ${terra.id}`)[0].t === 'La terracotta fatta a mano: come nasce ogni vaso');
    const ra = await rp.goto(B + '/admin/media.php?scheda=alt', { waitUntil: 'networkidle' });
    ok('il redattore apre Media > Dati delle immagini (Senza alt)', ra.status() === 200 && await rp.locator('#ma-libreria').count() === 1);
    await red.c.close();
  }

  // === 6. Al telefono e al computer senza scorrimento di lato.
  for (const [w, h] of [[1280, 900], [390, 844]]) {
    const ctx = await br.newContext({ viewport: { width: w, height: h }, storageState: await admin.c.storageState() }); const t = await ctx.newPage();
    for (const u of ['seo-tabella.php', 'media.php?scheda=alt', 'contenuto.php?tipo=pagine&id=' + vasi.id]) {
      await t.goto(B + '/admin/' + u, { waitUntil: 'networkidle' });
      const W = await t.evaluate(() => document.documentElement.scrollWidth);
      ok(`${u} a ${w} px: niente scorrimento di lato`, W <= w + 1, W);
      if (w === 390 && u === 'seo-tabella.php') { ok('a 390 px la tabella è a schede (data-schede) e le caselle si vedono', await t.locator('#st-tabella[data-schede]').count() === 1 && await t.locator('#st-tabella input[data-campo=seo_title]').first().isVisible()); await foto(t, 'seo-tabella-390'); }
    }
    await ctx.close();
  }

  ok('nessun errore JavaScript nel pannello', err.length === 0, err.slice(0, 5));

  // Pulizia: le pagine e l'articolo di prova, il prodotto, la Vetrina com'era, gli alt, il redattore, Chi siamo e Blog com'erano.
  cli('sql', `DELETE FROM {p}vetrina_prodotti WHERE id = ${+prodotto.id}`);
  if (eraSpenta) cli('addon', 'vetrina', '0');
  pulisci();
  for (const id of [vasi.id, doppio.id, terra.id, video.id]) cli('sql', `DELETE FROM {p}pagine_revisioni WHERE tipo = 'pagine' AND pagina_id = ${id}`);
  cli('sql', `DELETE FROM {p}pagine_revisioni WHERE tipo = 'articoli' AND pagina_id = ${art.id}`);
  cli('sql', `DELETE FROM {p}revisioni WHERE (tipo = 'pagine' AND contenuto_id IN (${vasi.id}, ${doppio.id}, ${terra.id}, ${video.id}, ${idChi})) OR (tipo = 'articoli' AND contenuto_id = ${art.id})`);
  cli('foto_togli', 'prova-seo-1'); cli('foto_togli', 'prova-seo-2');
  ok('pulizia: pagine, articolo, prodotto, foto, alt, redattore tolti; Chi siamo e Blog come prima', +cli('sql', "SELECT COUNT(*) c FROM {p}pages WHERE slug LIKE 'prova-seo-%'")[0].c === 0 && +cli('sql', "SELECT COUNT(*) c FROM {p}articles WHERE slug = 'prova-seo-vaso'")[0].c === 0 && +cli('sql', "SELECT COUNT(*) c FROM {p}media_alt")[0].c === 0 && cli('sql', `SELECT seo_title t FROM {p}pages WHERE id = ${idChi}`)[0].t === '' && !require('fs').existsSync(cli('foto', 'prova-seo-x').file.replace('prova-seo-x', 'prova-seo-1')));
  cli('foto_togli', 'prova-seo-x');

  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error('ECCEZIONE', e); process.exit(2); });
