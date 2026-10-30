// Addon Vetrina (2.0.0): tutto il percorso, come lo farebbero l'attività e un cliente, su un sito nuovo (kit Attività).
// L'attività accende l'addon, imposta il negozio (bonifico, link PayPal.me, spedizione), crea categorie e prodotti con
// foto e varianti; il cliente sceglie, mette nel carrello, manda la richiesta d'ordine e riceve l'email; l'attività cambia
// lo stato (email a ogni passo), annulla (le scorte tornano). Poi i widget, il telefono 390x844, la sitemap, e con l'addon
// spento il sito uguale a prima. Con il server di posta finto acceso:
//   python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> &
// uso: node prove/prova-addon-vetrina.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella del CMS installato>
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const [B, UTENTE, PASSWORD, SMTP, POSTA, CMS] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const posta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => fs.readFileSync(path.join(POSTA, f), 'utf8'));
const dorme = ms => new Promise(r => setTimeout(r, ms));
// Il corpo di un'email (quoted-printable o base64) in testo leggibile.
const corpo = eml => {
  let t = eml;
  const b64 = [...eml.matchAll(/Content-Transfer-Encoding: base64\r?\n(?:[^\r\n]+\r?\n)*\r?\n([A-Za-z0-9+/=\r\n]+)/g)].map(m => Buffer.from(m[1].replace(/\s+/g, ''), 'base64').toString('utf8')).join('\n');
  t = t.replace(/=\r?\n/g, '').replace(/=([0-9A-F]{2})/g, (m, x) => String.fromCharCode(parseInt(x, 16)));
  try { t = Buffer.from(t, 'latin1').toString('utf8'); } catch (e) {}
  return t + '\n' + b64;
};
const senzaNonce = h => h.replace(/nonce-[A-Za-z0-9+/_=-]+/g, 'nonce-X').replace(/nonce="[^"]*"/g, 'nonce="X"').replace(/\?v=\d+/g, '');
const UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36 ProvaVetrina';

(async () => {
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 }, userAgent: UA });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200))); p.on('console', m => { if (m.type() === 'error' && !/favicon|404|422|403/.test(m.text())) err.push(m.text().slice(0, 200)); });
  p.on('dialog', d => d.accept());
  const vai = u => p.goto(B + u, { waitUntil: 'networkidle' });
  const flash = async () => (await p.locator('.flash').first().innerText().catch(() => '')).trim();
  const invia = async (sel) => { await Promise.all([p.waitForNavigation(), p.click(sel)]); };

  // Le foto dei prodotti: tre PNG colorate fatte qui.
  const fotoDir = path.join(POSTA, '..', 'vetrina-foto'); fs.mkdirSync(fotoDir, { recursive: true });
  const fp = await br.newPage({ viewport: { width: 600, height: 600 } });
  const foto = [];
  for (const [i, c] of [['#b45309', 'tazza'], ['#2563eb', 'retro'], ['#16a34a', 'grembiule']].entries()) {
    await fp.setContent(`<body style="margin:0;background:${c[0]}"><h1 style="font:bold 80px sans-serif;color:#fff;padding:200px 40px">${c[1]}</h1></body>`);
    const f = path.join(fotoDir, `${c[1]}.png`); await fp.screenshot({ path: f }); foto.push(f);
  }
  await fp.close();

  // Il sito prima di accendere l'addon: per il confronto finale (con l'addon spento deve tornare uguale).
  const pagineSito = ['/', '/servizi/', '/contatti/'];
  const prima = {};
  for (const u of pagineSito) prima[u] = senzaNonce(await (await p.request.get(B + u, { headers: { 'User-Agent': UA } })).text());
  const sitemapPrima = await (await p.request.get(B + '/sitemap.xml')).text();
  ok('addon spento: /negozio/ non esiste (404)', (await p.request.get(B + '/negozio/')).status() === 404);

  await vai('/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await invia('button[type=submit]');
  ok('addon spento: le pagine del pannello della vetrina danno 404', (await p.request.get(B + '/admin/addon-pagina.php?a=vetrina&p=ordini')).status() === 404);

  // Posta: il server finto.
  await vai('/admin/impostazioni.php');
  await p.fill('#smtp_host', '127.0.0.1'); await p.fill('#smtp_porta', SMTP); await p.selectOption('#smtp_sicurezza', 'nessuna');
  await p.fill('[name=smtp_mittente]', 'sito@esempio.it').catch(() => {});
  await p.fill('[name=notification_email]', 'avvisi-sito@esempio.it');
  await invia('form:has(input[value=save_posta]) button[type=submit]');

  // Accendere l'addon da Impostazioni > Addon.
  await vai('/admin/addon.php');
  ok('Impostazioni > Addon: la carta della Vetrina, spenta', /Vetrina prodotti/.test(await p.locator('[data-addon=vetrina] h2').textContent()) && /Spento/.test(await p.locator('[data-addon=vetrina] .badge').textContent()));
  await invia('[data-addon=vetrina] button');
  ok('accesa: il messaggio e la voce Vetrina nel menu', /acceso/.test(await flash()) && await p.locator('.admin-nav a', { hasText: 'Vetrina' }).count() >= 1, await flash());

  // Impostazioni del negozio.
  await vai('/admin/addon-pagina.php?a=vetrina&p=impostazioni');
  await p.fill('#vt-nome_negozio', 'Bottega');
  await p.fill('#vt-intro', 'Ceramiche fatte a mano a Bologna.');
  await p.fill('#vt-spedizione_costo', '7,00'); await p.fill('#vt-gratis_oltre', '60,00');
  await p.fill('#vt-iban', 'IT60 X054 2811 1010 0000 0123 456'); await p.fill('#vt-intestatario', 'Bottega Rossi di Anna Rossi');
  await p.check('input[name=link]'); await p.fill('#vt-link_url', 'javascript:alert(1)');
  await p.fill('#vt-attivita_nome', 'Bottega Rossi'); await p.fill('#vt-attivita_indirizzo', 'Via Rizzoli 1, 40125 Bologna'); await p.fill('#vt-attivita_piva', '01234567890');
  await p.fill('#vt-email_avvisi', 'negozio@esempio.it');
  await invia('#vt-salva');
  ok('impostazioni: un link di pagamento javascript: si rifiuta', /deve cominciare con https/.test(await p.locator('#vt-errori').innerText().catch(() => '')));
  await p.fill('#vt-link_url', 'https://paypal.me/bottegarossi');
  await invia('#vt-salva');
  ok('impostazioni salvate con il link PayPal.me', /salvate/.test(await flash()), await flash());
  ok('l\'IBAN si salva senza spazi', await p.inputValue('#vt-iban') === 'IT60X0542811101000000123456');

  // Categorie.
  for (const c of ['Tazze', 'Grembiuli']) {
    await vai('/admin/addon-pagina.php?a=vetrina&p=categorie');
    await p.fill('#vt-cat-nome', c); await invia('#vt-cat-nuova button[type=submit]');
  }
  ok('due categorie create', await p.locator('#vt-categorie tbody tr').count() === 2);

  // Prodotti: una tazza scontata con due foto e 3 pezzi, un grembiule con tre taglie, un piatto esaurito.
  const nuovo = async (dati) => {
    await vai('/admin/addon-pagina.php?a=vetrina&p=prodotto');
    await p.fill('#vt-nome', dati.nome); await p.fill('#vt-prezzo', dati.prezzo);
    if (dati.breve) await p.fill('#vt-breve', dati.breve);
    if (dati.descrizione) await p.fill('#vt-descrizione', dati.descrizione);
    if (dati.scontato) await p.fill('#vt-scontato', dati.scontato);
    if (dati.scorta !== undefined) await p.fill('#vt-scorta', dati.scorta);
    if (dati.foto) await p.setInputFiles('#vt-foto-nuove', dati.foto);
    if (dati.evidenza) await p.check('input[name=evidenza]');
    if (dati.esaurito) await p.check('input[name=esaurito]');
    if (dati.categoria) await p.check(`label.vt-a-spunta:has-text("${dati.categoria}") input`);
    if (dati.opzione1) await p.fill('#vt-opz1', dati.opzione1);
    for (const [i, v] of (dati.varianti || []).entries()) {
      await p.fill(`input[name="var[${i}][valore1]"]`, v[0]); await p.fill(`input[name="var[${i}][prezzo]"]`, v[1]); await p.fill(`input[name="var[${i}][scorta]"]`, v[2]);
    }
    await invia('#vt-salva');
    return { msg: await flash(), url: p.url() };
  };
  // Un prezzo scontato più alto del pieno si rifiuta, e quello che si è scritto resta.
  const sbagliato = await nuovo({ nome: 'Tazza smaltata', prezzo: '18,00', scontato: '20,00' });
  ok('prezzo scontato più alto del pieno: rifiutato, i campi restano', /più basso/.test(await p.locator('#vt-errore').innerText().catch(() => '')) && await p.inputValue('#vt-nome') === 'Tazza smaltata');
  const t = await nuovo({ nome: 'Tazza smaltata', prezzo: '18,00', scontato: '15,00', scorta: '3', foto: [foto[0], foto[1]], evidenza: true, categoria: 'Tazze', breve: 'Fatta a mano, 300 ml', descrizione: 'Ceramica smaltata a mano.\n\nSi lava in lavastoviglie.' });
  ok('tazza salvata con due foto', /salvato/.test(t.msg) && await p.locator('#vt-foto figure').count() === 2, t.msg);
  const g = await nuovo({ nome: 'Grembiule da bottega', prezzo: '25,00', foto: [foto[2]], categoria: 'Grembiuli', opzione1: 'Taglia', varianti: [['S', '', '1'], ['M', '', '0'], ['L', '30,00', '']], evidenza: true });
  ok('grembiule salvato con tre varianti', /salvato/.test(g.msg) && await p.locator('#vt-varianti input[name$="[valore1]"]').evaluateAll(es => es.filter(e => e.value).length) === 3, g.msg);
  await nuovo({ nome: 'Piatto fondo', prezzo: '22,00', esaurito: true, categoria: 'Tazze' });
  await vai('/admin/addon-pagina.php?a=vetrina&p=prodotti');
  ok('Vetrina > Prodotti: tre prodotti', await p.locator('#vt-prodotti tbody tr').count() === 3);

  // Il negozio, come un cliente (contesto nuovo, senza sessione del pannello).
  const cli = await br.newContext({ viewport: { width: 1280, height: 900 }, userAgent: UA });
  const c = await cli.newPage();
  c.on('pageerror', e => err.push('cliente: ' + e.message.slice(0, 200)));
  const cvai = u => c.goto(B + u, { waitUntil: 'networkidle' });
  await cvai('/negozio/');
  ok('/negozio/: titolo, frase e tre prodotti', await c.locator('h1').innerText() === 'Bottega' && /Ceramiche/.test(await c.locator('.vt-intro').innerText()) && await c.locator('.vt-scheda').count() === 3);
  ok('il piatto esaurito ha il bollino Esaurito', await c.locator('.vt-scheda.vt-esaurito', { hasText: 'Piatto fondo' }).count() === 1);
  ok('la tazza mostra il prezzo pieno barrato e lo scontato', /18,00/.test(await c.locator('.vt-scheda', { hasText: 'Tazza' }).locator('s').innerText()) && /15,00/.test(await c.locator('.vt-scheda', { hasText: 'Tazza' }).locator('b').innerText()));
  await c.click('.vt-filtri a:has-text("Grembiuli")'); await c.waitForLoadState('networkidle');
  ok('filtro per categoria: solo il grembiule', await c.locator('.vt-scheda').count() === 1 && /Grembiule/.test(await c.locator('.vt-scheda').innerText()));
  await cvai('/negozio/?ordina=prezzo');
  ok('ordina per prezzo: prima la tazza (15), poi piatto (22), poi grembiule (25)', JSON.stringify(await c.locator('.vt-nome').allInnerTexts()) === JSON.stringify(['Tazza smaltata', 'Piatto fondo', 'Grembiule da bottega']), await c.locator('.vt-nome').allInnerTexts());
  ok('categoria che non esiste: 404', (await c.request.get(B + '/negozio/?categoria=non-esiste')).status() === 404);
  ok('prima del carrello il sito non ha messo cookie', (await cli.cookies()).length === 0, await cli.cookies());

  // Scheda della tazza: foto, dati per Google.
  await cvai('/negozio/tazza-smaltata/');
  const ld = JSON.parse(await c.locator('script[type="application/ld+json"]').last().textContent());
  ok('scheda: dati per Google Product e Offer (15.00 EUR, disponibile)', ld['@type'] === 'Product' && ld.offers['@type'] === 'Offer' && ld.offers.price === '15.00' && ld.offers.priceCurrency === 'EUR' && /InStock/.test(ld.offers.availability) && ld.image.length === 2, ld);
  await c.click('.vt-miniature a:nth-child(2)');
  ok('la seconda miniatura diventa la foto grande', (await c.getAttribute('[data-vt-principale]', 'src')) === (await c.getAttribute('.vt-miniature a:nth-child(2)', 'data-vt-foto')));
  await c.fill('input[name=qta]', '2');
  await Promise.all([c.waitForNavigation(), c.click('.vt-compra button')]);
  ok('aggiunta al carrello: si va al carrello con il messaggio', /carrello/.test(c.url()) && /Aggiunto/.test(await c.locator('.vt-avviso').innerText()));
  const ck = (await cli.cookies()).find(x => x.name === 'vetrina_carrello');
  ok('il carrello è un cookie firmato, HttpOnly, SameSite Lax, senza prezzi', ck && ck.httpOnly && ck.sameSite === 'Lax' && /^\[\[\d+,0,2\]\]$/.test(Buffer.from(ck.value.split('.')[0].replace(/-/g, '+').replace(/_/g, '/'), 'base64').toString()), ck && ck.value.slice(0, 40));

  // Grembiule: varianti, M esaurita non si sceglie, L ha il suo prezzo.
  await cvai('/negozio/grembiule-da-bottega/');
  const ld2 = JSON.parse(await c.locator('script[type="application/ld+json"]').last().textContent());
  ok('grembiule: AggregateOffer da 25.00 a 30.00', ld2.offers['@type'] === 'AggregateOffer' && ld2.offers.lowPrice === '25.00' && ld2.offers.highPrice === '30.00', ld2.offers);
  ok('la taglia M (scorte 0) non si può scegliere', await c.locator('select[name=variante] option').evaluateAll(os => os.some(o => /^M\b/.test(o.textContent) && o.disabled && /esaurito/.test(o.textContent))));
  await c.selectOption('select[name=variante]', { label: 'L · € 30,00' });
  ok('scelta la L, il prezzo mostrato diventa 30,00', /30,00/.test(await c.locator('[data-vt-prezzo]').innerText()));
  await c.selectOption('select[name=variante]', { label: 'S · € 25,00' });
  await Promise.all([c.waitForNavigation(), c.click('.vt-compra button')]);
  ok('carrello: due righe, totale con spedizione 30 + 25 + 7 = 62,00', await c.locator('.vt-riga').count() === 2 && /62,00/.test(await c.locator('.vt-totali .vt-totale').last().innerText()), await c.locator('.vt-totali').innerText());
  // Cambia la quantità della tazza a 3 e torna a 2.
  await c.fill('.vt-riga:has-text("Tazza") input[type=number]', '3'); await Promise.all([c.waitForNavigation(), c.click('button:has-text("Aggiorna il carrello")')]);
  ok('quantità aggiornata: 3 tazze (45) + 25 = 70, spedizione gratis oltre 60', /70,00/.test(await c.locator('.vt-totali .vt-totale').last().innerText()) && /gratis/.test(await c.locator('.vt-totali').innerText()));
  await c.fill('.vt-riga:has-text("Tazza") input[type=number]', '2'); await Promise.all([c.waitForNavigation(), c.click('button:has-text("Aggiorna il carrello")')]);

  // Al telefono: elenco, scheda, carrello senza scorrimento orizzontale.
  const tel = await br.newContext({ viewport: { width: 390, height: 844 }, userAgent: UA + ' Mobile', isMobile: true, hasTouch: true });
  await tel.addCookies((await cli.cookies()).map(x => ({ ...x })));
  const tp = await tel.newPage(); tp.on('pageerror', e => err.push('telefono: ' + e.message.slice(0, 200)));
  const larghe = [];
  for (const u of ['/negozio/', '/negozio/tazza-smaltata/', '/negozio/grembiule-da-bottega/', '/negozio/carrello/', '/negozio/ordine/', '/negozio/condizioni/']) {
    await tp.goto(B + u, { waitUntil: 'networkidle' });
    const [sw, iw] = await tp.evaluate(() => [document.documentElement.scrollWidth, window.innerWidth]);
    if (sw > iw) larghe.push([u, sw]);
  }
  ok('al telefono (390x844) nessuna pagina del negozio scorre di lato', larghe.length === 0, larghe);
  await tp.goto(B + '/negozio/carrello/', { waitUntil: 'networkidle' });
  await tp.screenshot({ path: path.join(fotoDir, 'carrello-telefono.png'), fullPage: true });
  await tel.close();

  // La richiesta d'ordine: errori segnati, poi l'invio vero.
  await cvai('/negozio/ordine/');
  ok('richiesta d\'ordine: riepilogo con il totale di 62,00', /62,00/.test(await c.locator('.vt-riepilogo [data-vt-totali="spedizione"] .vt-totale').last().innerText()));
  await c.click('label.vt-scelta:has-text("Ritiro") input');
  ok('scelto il ritiro: l\'indirizzo sparisce e il totale diventa 55,00', await c.locator('[data-vt-indirizzo]').isHidden() && /55,00/.test(await c.locator('.vt-riepilogo [data-vt-totali="ritiro"] .vt-totale').last().innerText()));
  await c.click('label.vt-scelta:has-text("Spedizione") input');
  // Senza captcha il browser non manda niente; con il captcha e i campi vuoti il server segna gli errori.
  await c.locator('.vt-ordine .captcha input[type=checkbox]').check(); await c.waitForSelector('form.verificato', { timeout: 30000 });
  await Promise.all([c.waitForNavigation(), c.click('.vt-ordine button[type=submit]')]);
  ok('inviata vuota: errori su nome, email, telefono, indirizzo e consenso', await c.locator('.vt-errore').count() >= 5 && await c.locator('.modulo-errore').count() === 1, await c.locator('.vt-errore').allInnerTexts());
  const compila = async (dati) => {
    for (const [k, v] of Object.entries(dati)) await c.fill(`.vt-ordine [name=${k}]`, v);
    await c.check('.vt-ordine input[name=consenso]');
    await c.locator('.vt-ordine .captcha input[type=checkbox]').check();
    await c.waitForSelector('form.verificato', { timeout: 30000 });
  };
  const quante = posta().length;
  await compila({ nome: 'Marta Bianchi', email: 'marta@cliente.it', telefono: '+39 333 1234567', indirizzo: 'Via Indipendenza 10', cap: '40121', citta: 'Bologna', note: 'Citofono Bianchi, grazie.' });
  await c.click('label.vt-scelta:has-text("Bonifico") input');
  await Promise.all([c.waitForNavigation(), c.click('.vt-ordine button[type=submit]')]);
  const anno = new Date().getFullYear();
  const num1 = `${anno}-0001`;
  ok('ordine inviato: la pagina "Grazie" con il numero, IBAN e causale', /ordine\/fatto/.test(c.url()) && (await c.locator('.vt-fatto .occhiello').innerText()).includes(num1) && /IT60X0542811101000000123456/.test(await c.locator('.vt-paga').innerText()) && /Ordine \d{4}-0001/.test(await c.locator('.vt-paga').innerText()), c.url());
  ok('dopo l\'ordine il carrello è vuoto', !(await cli.cookies()).some(x => x.name === 'vetrina_carrello'));
  const fattoUrl = c.url();
  ok('la pagina dell\'ordine non si apre senza la firma giusta', (await c.request.get(fattoUrl.replace(/k=[0-9a-f]+/, 'k=' + '0'.repeat(32)))).status() === 404);
  for (let i = 0; i < 30 && posta().length < quante + 2; i++) await dorme(300);
  const m1 = posta().slice(quante);
  const aCliente = m1.find(x => /X-Destinatari: marta@cliente\.it/.test(x)), aNegozio = m1.find(x => /X-Destinatari: negozio@esempio\.it/.test(x));
  ok('email al cliente: riepilogo, totale 62,00 e IBAN', aCliente && /62,00/.test(corpo(aCliente)) && /IT60X0542811101000000123456/.test(corpo(aCliente)) && corpo(aCliente).includes(num1), m1.map(x => x.slice(0, 80)));
  ok('email al negozio: avviso con nome, telefono e note, Reply-To del cliente', aNegozio && /Marta Bianchi/.test(corpo(aNegozio)) && /333 1234567/.test(corpo(aNegozio)) && /Citofono/.test(corpo(aNegozio)) && /Reply-To: marta@cliente\.it/i.test(aNegozio));

  // Le scorte sono scese: tazza 3 -> 1, taglia S 1 -> 0.
  await vai('/admin/addon-pagina.php?a=vetrina&p=prodotti');
  const scorteTazza = await p.locator('#vt-prodotti tr[data-slug="tazza-smaltata"] td:nth-child(4)').innerText();
  const scorteGremb = await p.locator('#vt-prodotti tr[data-slug="grembiule-da-bottega"] td:nth-child(4)').innerText();
  ok('scorte scese con l\'ordine: tazza 1, taglia S 0', scorteTazza.trim() === '1' && /S: 0/.test(scorteGremb), [scorteTazza, scorteGremb]);

  // Il pannello: l'ordine e i cambi di stato.
  await vai('/admin/addon-pagina.php?a=vetrina&p=ordini');
  ok('Vetrina > Ordini: l\'ordine nuovo con il totale', await p.locator(`#vt-ordini tr[data-numero="${num1}"]`).count() === 1 && /62,00/.test(await p.locator(`#vt-ordini tr[data-numero="${num1}"]`).innerText()) && /Nuovo/i.test(await p.locator(`#vt-ordini tr[data-numero="${num1}"] .badge`).textContent()));
  await p.click(`#vt-ordini tr[data-numero="${num1}"] a`); await p.waitForLoadState('networkidle');
  ok('scheda dell\'ordine: cliente, indirizzo, note', /Marta Bianchi/.test(await p.locator('.vt-a-cliente').innerText()) && /Via Indipendenza 10/.test(await p.locator('#vt-ordine').innerText()) && /Citofono/.test(await p.locator('.vt-a-note').innerText()));
  const cambia = async (stato, messaggio = '', avvisa = true) => {
    const q = posta().length;
    await p.selectOption('#vt-stato-scelta', stato);
    if (messaggio) await p.fill('#vt-messaggio', messaggio);
    if (!avvisa) await p.uncheck('#vt-stato input[name=avvisa]');
    await invia('#vt-stato button[type=submit]');
    for (let i = 0; i < 20 && posta().length === q && avvisa; i++) await dorme(250);
    await dorme(300);
    return posta().slice(q);
  };
  const e1 = await cambia('pagato');
  ok('stato Pagato: email al cliente "pagamento ricevuto"', e1.length === 1 && /marta@cliente\.it/.test(e1[0]) && /ricevuto il pagamento/.test(corpo(e1[0])), e1.length);
  const e2 = await cambia('spedito', 'Spedito con il corriere, numero BRT 123456.');
  ok('stato Spedito: email con il messaggio del numero di spedizione', e2.length === 1 && /BRT 123456/.test(corpo(e2[0])) && /partito/.test(corpo(e2[0])));
  const e3 = await cambia('consegnato', '', false);
  ok('stato Consegnato senza avviso: nessuna email', e3.length === 0 && /Consegnato/i.test(await p.locator('#vt-ordine .badge').first().textContent()));
  const e4 = await cambia('annullato');
  ok('stato Annullato: email al cliente', e4.length === 1 && /annullato/.test(corpo(e4[0])));
  ok('la storia dell\'ordine ha cinque passi', await p.locator('.vt-a-storia li').count() === 5);
  await vai('/admin/addon-pagina.php?a=vetrina&p=prodotti');
  ok('annullato: le scorte tornano (tazza 3, taglia S 1)', (await p.locator('#vt-prodotti tr[data-slug="tazza-smaltata"] td:nth-child(4)').innerText()).trim() === '3' && /S: 1/.test(await p.locator('#vt-prodotti tr[data-slug="grembiule-da-bottega"] td:nth-child(4)').innerText()));

  // Secondo ordine: ritiro in negozio e link PayPal.me con l'importo.
  await cvai('/negozio/tazza-smaltata/'); await Promise.all([c.waitForNavigation(), c.click('.vt-compra button')]);
  await cvai('/negozio/ordine/');
  await compila({ nome: 'Luca Verdi', email: 'luca@cliente.it', telefono: '051 654321' });
  await c.click('label.vt-scelta:has-text("Ritiro") input');
  await c.click('label.vt-scelta:has-text("online") input');
  const q2 = posta().length;
  await Promise.all([c.waitForNavigation(), c.click('.vt-ordine button[type=submit]')]);
  const link = await c.locator('.vt-paga a').getAttribute('href').catch(() => '');
  ok('ritiro e link: il pulsante porta a PayPal.me con l\'importo (15.00EUR)', link === 'https://paypal.me/bottegarossi/15.00EUR' && (await c.locator('.vt-fatto .occhiello').innerText()).includes(`${anno}-0002`), link);
  for (let i = 0; i < 30 && posta().length < q2 + 2; i++) await dorme(300);
  const m2 = posta().slice(q2).find(x => /luca@cliente\.it/.test(x.split('\n')[0]));
  ok('email del secondo ordine con il link di pagamento e il ritiro', m2 && /paypal\.me\/bottegarossi\/15\.00EUR/.test(corpo(m2)) && /Ritiro in negozio/.test(corpo(m2)));

  // Widget dell'editor: Carrello e Prodotti in evidenza in una pagina.
  execFileSync('php', [path.join(__dirname, 'pagina-vetrina.php'), CMS]);
  await cvai('/negozio/grembiule-da-bottega/');
  await c.selectOption('select[name=variante]', { label: 'L · € 30,00' }); await c.fill('input[name=qta]', '2');
  await Promise.all([c.waitForNavigation(), c.click('.vt-compra button')]);
  await cvai('/vetrina-widget/');
  ok('widget Carrello: icona con 2 articoli e il link al carrello', (await c.locator('.vt-carrello-w .vt-conta').innerText()) === '2' && /\/negozio\/carrello\/$/.test(await c.locator('.vt-carrello-w').getAttribute('href')));
  ok('widget Prodotti: i due prodotti in evidenza, con il foglio della vetrina', await c.locator('.ed-w-vetrina_prodotti .vt-scheda').count() === 2 && await c.locator('link[href*="addon/vetrina/assets/vetrina.css"]').count() === 1);
  await vai('/admin/editor-widget.php');
  execFileSync('php', [path.join(__dirname, 'pagina-vetrina.php'), CMS, 'togli']); // la pagina di prova via: il confronto finale è con il sito di prima
  ok('i due widget nell\'elenco dei widget dell\'editor', /Prodotti/.test(await p.locator('body').innerText()) && /Carrello/.test(await p.locator('body').innerText()));

  // Sitemap e pagina delle condizioni.
  const sm = await (await p.request.get(B + '/sitemap.xml')).text();
  ok('sitemap: il negozio e le schede dei prodotti', /\/negozio\/<\/loc>/.test(sm) && /\/negozio\/tazza-smaltata\/<\/loc>/.test(sm));
  await cvai('/negozio/condizioni/');
  ok('condizioni di vendita con i dati dell\'attività', /recedere entro 14 giorni/.test(await c.locator('.vt-condizioni').innerText()) && /01234567890/.test(await c.locator('.vt-condizioni').innerText()));
  ok('dashboard: il numero degli ordini nuovi', await (async () => { await vai('/admin/'); return /ordini nuovi/.test(await p.locator('body').innerText()); })());

  // Spegnere l'addon: il sito torna uguale a prima, i dati restano.
  await vai('/admin/addon.php');
  await invia('[data-addon=vetrina] button');
  ok('spenta: messaggio "i dati restano"', /restano/.test(await flash()));
  const diversi = [];
  for (const u of pagineSito) if (senzaNonce(await (await p.request.get(B + u, { headers: { 'User-Agent': UA } })).text()) !== prima[u]) diversi.push(u);
  ok('addon spento: home, Servizi e Contatti identiche a prima di accenderlo', diversi.length === 0, diversi);
  ok('addon spento: sitemap identica a prima', (await (await p.request.get(B + '/sitemap.xml')).text()) === sitemapPrima);
  ok('addon spento: /negozio/ torna 404', (await p.request.get(B + '/negozio/')).status() === 404);
  await vai('/admin/addon.php'); await invia('[data-addon=vetrina] button');
  await vai('/admin/addon-pagina.php?a=vetrina&p=ordini');
  ok('riaccesa: gli ordini ci sono ancora', await p.locator('#vt-ordini tbody tr').count() === 2);

  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
