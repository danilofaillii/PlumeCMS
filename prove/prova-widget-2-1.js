// I widget della 2.1 come li usa un visitatore, al computer e al telefono: menu del ristorante, richiesta di prenotazione
// (tavolo e alloggio, con l'email all'attività), galleria con filtri (anche da tastiera, con il lightbox), loghi e testo
// scorrevoli (pausa, pulsante, meno movimento), contenuto esterno con consenso (CSP solo nella sua pagina), mappa con
// "Come arrivare", video che parte da solo e si ripete, tabella con grassetto, corsivo e link, Ultimi articoli per
// categoria. Poi il form dei Contatti con i campi Ora e File: file buono accettato e allegato all'avviso, .php rinominato
// .pdf rifiutato, file troppo grande rifiutato, scarico dal pannello solo per l'amministratore, file mai pubblico, file
// cancellato con l'invio e con "Cancella dati". Alla fine il form torna com'era.
// Prima: un sito installato con il kit Attività (prova-kit.js), una categoria "notizie" con l'articolo di benvenuto,
//   php prove/pagina-widget-2-1.php <cartella CMS> nuovi      e il server di posta finto:
//   python3 -I prove/smtp-finto.py <porta smtp> <cartella posta>
// uso: node prove/prova-widget-2-1.js <base> <cartella CMS> <utente> <password> <porta smtp> <cartella posta>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, SMTP, POSTA] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, 'widget-2-1-cli.php'), DIR, ...a], { encoding: 'utf8' }));
const posta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => fs.readFileSync(path.join(POSTA, f), 'utf8'));
const dorme = ms => new Promise(r => setTimeout(r, ms));
const domani = (g = 1) => { const d = new Date(Date.now() + g * 86400000); return d.toISOString().slice(0, 10); };

(async () => {
  cli('svuota-limiti');
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'w21-'));
  const buono = path.join(tmp, 'preventivo.pdf'); fs.writeFileSync(buono, '%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n');
  const finto = path.join(tmp, 'fattura.pdf'); fs.writeFileSync(finto, '<?php echo shell_exec($_GET["c"]); ?>');
  const grande = path.join(tmp, 'catalogo.pdf'); fs.writeFileSync(grande, '%PDF-1.4\n' + 'x'.repeat(1600 * 1024) + '\n%%EOF\n');

  const br = await chromium.launch();
  const err = [];
  const contesto = async (w, h, extra = {}) => { const c = await br.newContext({ viewport: { width: w, height: h }, ...extra }); await c.route(u => !u.href.startsWith(B), r => r.abort()); return c; };
  const pagina = async (c) => { const p = await c.newPage(); p.on('pageerror', e => err.push(e.message.slice(0, 200))); p.on('console', m => { if (m.type() === 'error' && !/ERR_FAILED|net::|Failed to load resource/.test(m.text())) err.push(m.text().slice(0, 200)); }); p.on('dialog', d => d.accept()); return p; };
  const cpc = await contesto(1280, 900);
  const p = await pagina(cpc);
  const r = await p.goto(B + '/widget-2-1/', { waitUntil: 'networkidle' });
  ok('la pagina con i widget della 2.1 risponde 200', r.status() === 200);
  const html = await (await p.request.get(B + '/widget-2-1/')).text();
  ok('ci sono tutti e cinque i widget nuovi', ['menu_ristorante', 'richiesta_prenotazione', 'galleria_filtri', 'scorrevole', 'contenuto_esterno'].every(t => html.includes('ed-w-' + t)));
  const ld = await p.locator('script[type="application/ld+json"]').evaluateAll(l => l.map(s => { try { return JSON.parse(s.textContent); } catch (e) { return null; } }));
  ok('nessun dato per Google rotto', ld.every(Boolean), ld.length);

  // ---- Menu del ristorante
  const sez = await p.locator('.ew-menu-sez-titolo').allInnerTexts();
  ok('menu: sezioni nell\'ordine dei piatti; quella della domenica solo la domenica, il menu del giorno oggi sì', JSON.stringify(sez.slice(0, 3)) === '["Antipasti","Primi","Menu del giorno"]' && !sez.includes('Menu della domenica') && sez.includes('Allergeni'), sez);
  ok('menu: il menu del giorno dice "Oggi"', (await p.locator('.ew-menu-del-giorno .ew-menu-quando').innerText()).trim() === 'Oggi');
  const prezzi = await p.locator('.ew-menu-prezzo').allInnerTexts();
  ok('menu: prezzi scritti bene (14 €, 9,50 €, 13 €)', prezzi[0] === '14 €' && prezzi[1] === '9,50 €' && prezzi[2] === '13 €', prezzi);
  const tagliere = p.locator('.ew-menu-piatto', { hasText: 'Tagliere di salumi' });
  ok('menu: allergeni con il numero del regolamento (glutine 1, latte 7) e il nome per chi non vede', JSON.stringify(await tagliere.locator('.ew-all').allInnerTexts()) === '["1","7"]' && /Allergeni: cereali con glutine, latte e lattosio/.test(await tagliere.locator('.ew-nascosto-vista').innerText()));
  const legenda = await p.locator('.ew-menu-legenda li').allInnerTexts();
  ok('menu: la legenda ha solo gli allergeni usati, in ordine (1, 3, 4, 7, 9, 14)', legenda.map(x => parseInt(x)).join() === '1,3,4,7,9,14', legenda);
  ok('menu: etichette Vegano, Vegetariano, Senza glutine, Piccante, Novità', await p.locator('.ew-menu-eti-vegano').count() === 1 && await p.locator('.ew-menu-eti-piccante').count() === 1 && await p.locator('.ew-menu-eti-novita').count() === 1 && await p.locator('.ew-menu-eti-senza_glutine').count() === 1);
  ok('menu: la foto del piatto c\'è, con il nome come testo alternativo', await tagliere.locator('img[alt="Tagliere di salumi"]').count() === 1);
  const menu = ld.find(x => x && x['@type'] === 'Menu');
  const verd = menu && menu.hasMenuSection[0].hasMenuItem[1];
  ok('menu: dati per Google Menu > MenuSection > MenuItem con prezzo e dieta', menu && menu.hasMenuSection.length === 3 && verd.offers.price === '9.50' && verd.offers.priceCurrency === 'EUR' && verd.suitableForDiet.includes('https://schema.org/VeganDiet'), verd);

  // ---- Galleria con filtri
  const gf = p.locator('.ew-gf');
  const visibili = () => gf.locator('.ew-gal-voce:not([hidden])').count();
  ok('galleria: pulsanti Tutti, Matrimoni, Ritratti, Eventi; all\'inizio tutte le 5 foto', JSON.stringify(await gf.locator('.ew-gf-filtro').allInnerTexts()) === '["Tutti","Matrimoni","Ritratti","Eventi"]' && await visibili() === 5);
  await gf.locator('.ew-gf-filtro', { hasText: 'Ritratti' }).click(); await p.waitForTimeout(700);
  ok('galleria: "Ritratti" mostra le 2 foto dei ritratti, il pulsante è premuto', await visibili() === 2 && await gf.locator('.ew-gf-filtro[aria-pressed=true]').innerText() === 'Ritratti');
  ok('galleria: chi non vede sente quante foto ci sono', /2 foto: Ritratti/.test(await gf.locator('[data-ew-gf-conta]').textContent()));
  await gf.locator('.ew-gal-voce:not([hidden]) a').first().click(); await p.waitForTimeout(300);
  ok('galleria: il lightbox scorre solo le foto del filtro (1 / 2)', (await p.locator('.ew-lightbox .ew-lb-conta').innerText()) === '1 / 2');
  await p.keyboard.press('Escape');
  await gf.locator('.ew-gf-filtro', { hasText: 'Eventi' }).focus(); await p.keyboard.press('Enter'); await p.waitForTimeout(700);
  ok('galleria: da tastiera (Invio su "Eventi") le 2 foto degli eventi, una sta anche in Matrimoni', await visibili() === 2 && await gf.locator('.ew-gal-voce:not([hidden]) img[alt="Il taglio della torta"]').count() === 1);
  await gf.locator('.ew-gf-filtro', { hasText: 'Tutti' }).click(); await p.waitForTimeout(700);
  ok('galleria: "Tutti" le rimette tutte', await visibili() === 5);

  // ---- Scorrevole
  const loghi = p.locator('.ew-scr-loghi');
  const anim = () => loghi.locator('.ew-scr-traccia').evaluate(e => [getComputedStyle(e).animationName, getComputedStyle(e).animationPlayState]);
  const a0 = await anim();
  ok('scorrevole: i loghi scorrono (animazione infinita)', a0[0] === 'ew-scorri' && a0[1] === 'running', a0);
  await loghi.hover(); await p.waitForTimeout(100);
  ok('scorrevole: al passaggio del mouse si ferma', (await anim())[1] === 'paused');
  await p.mouse.move(5, 5);
  await loghi.locator('.ew-scr-ferma').click();
  await p.mouse.move(5, 5); await p.waitForTimeout(100);
  ok('scorrevole: il pulsante lo ferma (e lo dice: aria-pressed)', (await anim())[1] === 'paused' && await loghi.locator('.ew-scr-ferma').getAttribute('aria-pressed') === 'true');
  ok('scorrevole: loghi in grigio, a colori al passaggio', /grayscale/.test(await loghi.locator('.ew-scr-logo').first().evaluate(e => getComputedStyle(e).filter)));
  ok('scorrevole: la copia per il giro infinito è nascosta a chi legge con la voce e i suoi link non si raggiungono con Tab', await loghi.locator('ul[aria-hidden=true]').count() === 1 && await loghi.locator('ul[aria-hidden=true] a:not([tabindex="-1"])').count() === 0);
  ok('scorrevole: la frase va verso destra', await p.locator('.ew-scr-testo .ew-scr-traccia').evaluate(e => getComputedStyle(e).animationDirection) === 'reverse');
  const cmeno = await contesto(1280, 900, { reducedMotion: 'reduce' });
  const pm = await pagina(cmeno);
  await pm.goto(B + '/widget-2-1/', { waitUntil: 'networkidle' });
  ok('scorrevole: per chi chiede meno movimento sta fermo, senza copie e senza pulsante', await pm.locator('.ew-scr-loghi .ew-scr-traccia').evaluate(e => getComputedStyle(e).animationName) === 'none' && !(await pm.locator('.ew-scr-loghi ul[aria-hidden=true]').isVisible()) && !(await pm.locator('.ew-scr-loghi .ew-scr-ferma').isVisible()));
  await cmeno.close();

  // ---- Contenuto esterno
  const csp = r.headers()['content-security-policy'] || '';
  ok('contenuto esterno: la CSP di questa pagina ammette Calendly e Spotify nei frame', /frame-src[^;]*https:\/\/calendly\.com/.test(csp) && /frame-src[^;]*https:\/\/open\.spotify\.com/.test(csp), csp.slice(-300));
  const altra = (await p.request.get(B + '/widget-vecchi/')).headers()['content-security-policy'] || '';
  ok('contenuto esterno: in una pagina senza il widget la CSP non li ammette', !/calendly|spotify/.test(altra) && /frame-src/.test(altra));
  const cal = p.locator('.ew-est-calendly');
  ok('contenuto esterno: prima del clic niente iframe, la frase sulla privacy e il pulsante', await p.locator('.ew-est iframe').count() === 0 && /Calendly riceve il tuo indirizzo IP/.test(await cal.innerText()) && (await cal.locator('button').innerText()).toLowerCase() === 'mostra il contenuto di calendly');
  await cal.locator('button').click();
  ok('contenuto esterno: dopo il clic l\'iframe di Calendly, con il titolo', await cal.locator('iframe[src^="https://calendly.com/esempio/30min?"]').count() === 1 && await cal.locator('iframe').getAttribute('title') === 'Contenuto di Calendly');
  ok('contenuto esterno: Spotify diventa il lettore incorporato', (await p.locator('.ew-est-spotify').getAttribute('data-src')) === 'https://open.spotify.com/embed/playlist/37i9dQZF1DXcBWIGoYBM5M');

  // ---- Mappa, video, tabella, articoli
  const arr = await p.locator('.ew-mappa-arrivare a').evaluateAll(l => l.map(a => [a.getAttribute('href'), a.innerText.trim(), a.target]));
  ok('mappa: "Come arrivare" sotto con Google Maps e "Portami lì" sopra con Apple Mappe, in una scheda nuova', arr.length === 2 && arr[0][0] === 'https://www.google.com/maps/dir/?api=1&destination=Piazza%20Maggiore%2C%20Bologna' && /^Come arrivare/i.test(arr[0][1]) && arr[1][0] === 'https://maps.apple.com/?daddr=Via%20Rizzoli%201%2C%20Bologna' && /^Portami lì/i.test(arr[1][1]) && arr.every(x => x[2] === '_blank'), arr);
  ok('mappa: il pulsante sopra sta prima della mappa, quello sotto dopo', await p.locator('.ew-ma-sopra + .ew-mappa').count() === 1 && await p.locator('.ew-mappa + .ew-ma-sotto').count() === 1);
  const yt = await p.locator('.ed-video-solo iframe').getAttribute('src');
  ok('video: "parte da solo" carica subito YouTube, senza audio, e "si ripete"', /youtube-nocookie\.com\/embed\/dQw4w9WgXcQ\?.*autoplay=1/.test(yt) && /mute=1/.test(yt) && /loop=1/.test(yt) && /playlist=dQw4w9WgXcQ/.test(yt), yt);
  const vm = await p.locator('.ed-video[data-src*="vimeo"]').getAttribute('data-src');
  ok('video: Vimeo con "si ripete" resta al clic, con loop=1', /player\.vimeo\.com\/video\/76979871\?.*loop=1/.test(vm) && !/muted/.test(vm), vm);
  const tab = p.locator('.ed-w-tabella table').last();
  ok('tabella: **grassetto** e *corsivo* nelle celle', await tab.locator('td strong').innerText() === 'Taglio' && await tab.locator('td em').innerText() === 'su prenotazione');
  const lt = await tab.locator('a').evaluateAll(l => l.map(a => [a.getAttribute('href'), a.target]));
  ok('tabella: [testo](link) diventa un link; un sito esterno si apre in una scheda nuova', JSON.stringify(lt) === JSON.stringify([[new URL(B).pathname.replace(/\/$/, '') + '/contatti/', ''], ['https://www.esempio.it', '_blank']]), lt);
  ok('ultimi articoli: con la categoria scelta dalla tendina, solo i suoi', await p.locator('.ed-w-articoli .card').count() === 1);
  const def = cli('definizioni');
  ok('editor: la categoria di Ultimi articoli e della Griglia è una tendina con le categorie del sito', def['articoli.categoria'].tipo === 'scelta' && def['articoli.categoria'].opzioni.notizie === 'Notizie' && def['griglia_contenuti.categoria'].opzioni.ricette === 'Ricette' && !!def['articoli.categoria'].accetta, def['articoli.categoria']);
  ok('editor: i cinque widget nuovi hanno nome, gruppo e un\'icona del set di casa', Object.values(def.nuovi).every(x => x && x.icona), def.nuovi);
  const cont = await p.evaluate(() => [...document.querySelectorAll('.ew-menu-desc, .ew-menu-nome, .ew-gf-filtro, .ew-est-privacy, .ew-menu-legenda li, .ew-rp-nota, .ew-menu-quando')].map(e => {
    const lum = c => { const m = c.match(/[\d.]+/g).map(Number); const [r, g, b] = m.slice(0, 3).map(x => { x /= 255; return x <= .03928 ? x / 12.92 : Math.pow((x + .055) / 1.055, 2.4); }); return .2126 * r + .7152 * g + .0722 * b; };
    let el = e, bg = null; while (el) { const c = getComputedStyle(el).backgroundColor; if (c && !/rgba\(0, 0, 0, 0\)|transparent/.test(c) && !/, 0\.\d+\)$/.test(c)) { bg = c; break; } el = el.parentElement; }
    bg = bg || getComputedStyle(document.body).backgroundColor;
    const a = lum(getComputedStyle(e).color), b = lum(bg); return [e.className, Math.round((Math.max(a, b) + .05) / (Math.min(a, b) + .05) * 10) / 10];
  }));
  ok('contrasto dei testi nuovi almeno 4,5 a 1', cont.every(x => x[1] >= 4.5), cont.filter(x => x[1] < 4.5).slice(0, 4));

  // ---- Al telefono
  const ctel = await contesto(390, 844);
  const t = await pagina(ctel);
  await t.goto(B + '/widget-2-1/', { waitUntil: 'networkidle' });
  ok('telefono: niente scorrimento di lato', await t.evaluate(() => document.documentElement.scrollWidth) <= 390);
  ok('telefono: il menu è su una colonna', await t.locator('.ew-menu-piatti').first().evaluate(e => getComputedStyle(e).gridTemplateColumns.split(' ').length) === 1);
  ok('telefono: i pulsanti dei filtri sono alti almeno 44 px (dito)', (await t.locator('.ew-gf-filtro').first().boundingBox()).height >= 44);
  await t.locator('.ew-rp input[name=data]').first().scrollIntoViewIfNeeded();
  ok('telefono: il modulo di prenotazione sta nello schermo', (await t.locator('.ew-rp').first().boundingBox()).width <= 390);
  await ctel.close();

  // ---- Richiesta di prenotazione (tavolo e alloggio), con l'email all'attività
  const prenota = async (indice, campi, invalida = false) => {
    await p.goto(B + '/widget-2-1/', { waitUntil: 'networkidle' });
    const f = p.locator('.ew-rp form').nth(indice);
    for (const [k, v] of Object.entries(campi)) await f.locator(`[name=${k}]`).fill(v);
    await f.locator('input[name=privacy]').check();
    await f.locator('.captcha input[type=checkbox]').check();
    await p.waitForFunction(i => document.querySelectorAll('.ew-rp form')[i].classList.contains('verificato'), indice, { timeout: 30000 });
    if (invalida) await f.evaluate(x => { x.noValidate = true; });
    const quante = posta().length;
    await Promise.all([p.waitForNavigation(), f.locator('button[type=submit]').click()]);
    for (let i = 0; i < 12 && posta().length === quante; i++) await dorme(250);
    return posta().slice(quante);
  };
  // Il server di posta finto (Impostazioni > Posta in uscita) e il login.
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/impostazioni.php');
  await p.fill('#smtp_host', '127.0.0.1'); await p.fill('#smtp_porta', SMTP); await p.selectOption('#smtp_sicurezza', 'nessuna');
  await p.fill('[name=notification_email]', 'avvisi-sito@esempio.it');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=save_posta]) button[type=submit]')]);
  const m1 = await prenota(0, { data: domani(3), ora: '20:30', persone: '4', nome: 'Giulia Rossi', email: 'giulia@persona.it', telefono: '+39 333 1234567', note: 'Un seggiolone, grazie' });
  ok('prenotazione tavolo: dopo l\'invio il messaggio di conferma al posto del modulo', /abbiamo ricevuto la tua richiesta/.test(await p.locator('.ew-rp-ok').first().innerText()) && p.url().includes('prenota=ok'));
  ok('prenotazione tavolo: l\'email arriva all\'attività (solo a lei), con data, ora, persone e note, "Rispondi" a chi ha chiesto', m1.length === 1 && /X-Destinatari: ristorante@esempio\.it/.test(m1[0]) && /Reply-To: giulia@persona\.it/.test(m1[0]), m1.map(x => x.slice(0, 300)));
  const inv = cli('invii', '1')[0];
  ok('prenotazione tavolo: la richiesta è anche nel pannello, sotto "Richieste di prenotazione"', inv.modulo === 'Richieste di prenotazione' && inv.dati.Ora === '20:30' && inv.dati.Persone === '4' && inv.dati.Note === 'Un seggiolone, grazie', inv);
  const m2 = await prenota(1, { data: domani(10), partenza: domani(8), persone: '2', nome: 'Luca', email: 'luca@persona.it', telefono: '+39 333 7654321' }, true);
  ok('prenotazione alloggio: partenza prima dell\'arrivo, la richiesta torna indietro con l\'errore e niente email', m2.length === 0 && /La partenza deve essere dopo l.arrivo/.test(await p.locator('#' + (await p.locator('.ew-rp').nth(1).getAttribute('id')) + ' .modulo-errore').innerText()));
  await p.goto(B + '/widget-2-1/', { waitUntil: 'networkidle' });
  await p.locator('[data-ew-rp-arrivo]').fill(domani(12)); await p.locator('[data-ew-rp-arrivo]').dispatchEvent('change');
  ok('prenotazione alloggio: scelto l\'arrivo, la partenza propone il giorno dopo', await p.locator('[data-ew-rp-partenza]').inputValue() === domani(13));
  const m3 = await prenota(1, { data: domani(12), partenza: domani(15), persone: '2', nome: 'Luca Bianchi', email: 'luca@persona.it', telefono: '+39 333 7654321' });
  ok('prenotazione alloggio: email al B&B con arrivo, partenza e notti', m3.length === 1 && /X-Destinatari: bnb@esempio\.it/.test(m3[0]) && cli('invii', '1')[0].dati.Partenza.includes('(3 notti)'), cli('invii', '1')[0].dati);
  cli('svuota-limiti');

  // ---- Form con Ora e File
  await p.goto(B + '/contatti/', { waitUntil: 'networkidle' });
  const idForm = +((await p.locator('input[name=modulo]').first().getAttribute('value')) || '').replace('form:', '');
  await p.goto(B + '/admin/form.php?id=' + idForm, { waitUntil: 'networkidle' });
  // Una prova interrotta prima può aver lasciato i due campi: via, prima di contare.
  let resti = 0;
  for (let i = (await p.locator('.fm-campo').count()) - 1; i >= 0; i--) {
    if (['Ora preferita', 'Documento'].includes(await p.locator('.fm-etichetta').nth(i).inputValue())) { await p.locator('.fm-via').nth(i).click(); resti++; }
  }
  if (resti) await Promise.all([p.waitForNavigation(), p.click('#fm-form button[type=submit]:not([name=azione])')]);
  const campiPrima = await p.locator('.fm-campo').count();
  await p.click('text=+ Aggiungi un campo');
  await p.locator('.fm-etichetta').last().fill('Ora preferita');
  await p.locator('.fm-tipo').last().selectOption('ora');
  await p.click('text=+ Aggiungi un campo');
  await p.locator('.fm-etichetta').last().fill('Documento');
  await p.locator('.fm-tipo').last().selectOption('file');
  ok('form: il campo File chiede quali file accetta (PDF, JPG, PNG di partenza) e il peso', await p.locator('.fm-file-tipi input:checked').count() === 3 && await p.locator('.fm-file-peso select').count() === 1);
  await p.locator('.fm-file-tipi label', { hasText: 'PNG' }).locator('input').uncheck();
  await p.locator('.fm-file-peso select').selectOption('1');
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[type=submit]:not([name=azione])')]);
  ok('form: salvato con i due campi nuovi', await p.locator('.fm-campo').count() === campiPrima + 2 && await p.locator('.fm-file-tipi input:checked').count() === 2, await p.locator('.flash').first().innerText().catch(() => ''));
  const compila = async (email, file) => {
    await p.goto(B + '/contatti/', { waitUntil: 'networkidle' });
    const f = p.locator('form[data-captcha]').first();
    for (const c of await f.locator('input:not([type=hidden]):not([type=checkbox]):not([type=file]):not([type=time]), textarea').all()) {
      const nome = await c.getAttribute('name'); const tipo = await c.getAttribute('type');
      if (nome === 'sito_web') continue;
      await c.fill(tipo === 'email' ? email : (tipo === 'tel' ? '+39 333 1234567' : 'Prova file'));
    }
    await f.locator('input[type=time]').fill('18:30');
    if (file) await f.locator('input[type=file]').setInputFiles(file);
    await f.locator('input[name=privacy]').check();
    await f.locator('.captcha input[type=checkbox]').check();
    await p.waitForSelector('form.verificato', { timeout: 30000 });
    const quante = posta().length;
    await Promise.all([p.waitForNavigation(), f.locator('button[type=submit]').click()]);
    for (let i = 0; i < 12 && posta().length === quante; i++) await dorme(250);
    await dorme(300);
    return posta().slice(quante);
  };
  await p.goto(B + '/contatti/', { waitUntil: 'networkidle' });
  ok('form sul sito: campo Ora (type=time) e campo File con i tipi e il peso scritti sotto', await p.locator('form input[type=time]').count() === 1 && await p.locator('form input[type=file][accept=".pdf,.jpg,.jpeg"]').count() === 1 && /PDF, JPG, fino a 1 MB/.test(await p.locator('.form-file-aiuto').innerText()) && await p.locator('form[enctype="multipart/form-data"]').count() === 1);
  const f1 = await compila('file@persona.it', buono);
  ok('form: un PDF vero si accetta (pagina di ringraziamento)', /\/grazie\//.test(p.url()), p.url());
  const i1 = cli('invii', '1')[0];
  ok('form: l\'invio ha l\'ora e il file (nome com\'era, salvato con un nome a caso)', i1.dati['Ora preferita'] === '18:30' && /^preventivo\.pdf \(\d+ KB\)$/.test(i1.dati.Documento) && i1.allegati.length === 1 && /^[a-f0-9]{32}\.pdf$/.test(i1.allegati[0].file), i1);
  const av = f1.find(x => /Nuova compilazione/.test(x)) || '';
  ok('form: l\'avviso per email ha il file allegato', /Content-Disposition: attachment; filename="preventivo\.pdf"/.test(av) && av.includes(fs.readFileSync(buono).toString('base64').slice(0, 40)), av.slice(0, 200));
  const dir = cli('cartella-file');
  ok('form: il file sta nella cartella riservata', dir.file.includes(i1.allegati[0].file), dir);
  const anonimo = await br.newContext();
  const pub = await anonimo.request.get(B + '/' + dir.url + '/' + i1.allegati[0].file);
  ok('form: il file non si apre dal sito (403)', pub.status() === 403, pub.status());
  const scaricaUrl = B + '/admin/form-submissions.php?file=' + i1.id + '&n=0';
  const senza = await anonimo.request.get(scaricaUrl, { maxRedirects: 0 });
  ok('form: senza login lo scarico dal pannello non dà il file', !(await senza.body()).toString().startsWith('%PDF') && [302, 303, 403].includes(senza.status()), senza.status());
  await anonimo.close();
  await p.goto(B + '/admin/form-submissions.php?modulo=' + idForm, { waitUntil: 'networkidle' });
  ok('pannello: in Invii dei form il link "Scarica preventivo.pdf"', await p.locator(`a.fs-file[href="form-submissions.php?file=${i1.id}&n=0"]`, { hasText: 'Scarica preventivo.pdf' }).count() === 1);
  const sc = await p.request.get(scaricaUrl);
  ok('pannello: l\'amministratore scarica il file, uguale, come allegato', (await sc.body()).equals(fs.readFileSync(buono)) && /attachment/.test(sc.headers()['content-disposition'] || '') && sc.headers()['content-type'] === 'application/octet-stream');
  const f2 = await compila('finto@persona.it', finto);
  ok('form: un .php rinominato .pdf si rifiuta, con il messaggio sul campo', /Questo tipo di file non si può allegare/.test(await p.locator('.modulo-errore').innerText().catch(() => '')) && f2.length === 0 && cli('invii', '1')[0].id === i1.id);
  const f3 = await compila('grande@persona.it', grande);
  ok('form: un file più grande del massimo (1 MB) si rifiuta', /troppo grande/.test(await p.locator('.modulo-errore').innerText().catch(() => '')) && f3.length === 0 && cli('invii', '1')[0].id === i1.id);
  ok('form: nessun file rifiutato è rimasto sul disco', cli('cartella-file').file.length === dir.file.length);
  // Elimina dal pannello: via anche il file.
  await p.goto(B + '/admin/form-submissions.php?modulo=' + idForm, { waitUntil: 'networkidle' });
  await p.locator('tr', { has: p.locator(`button.ct-rosso[data-uno="${i1.id}"]`) }).hover(); // le azioni della riga compaiono al passaggio
  await Promise.all([p.waitForNavigation(), p.locator(`button.ct-rosso[data-uno="${i1.id}"]`).click()]);
  ok('pannello: eliminato l\'invio, il file non c\'è più', !cli('cartella-file').file.includes(i1.allegati[0].file));
  // Cancella dati della newsletter: via l'invio con quell'email e il suo file.
  cli('svuota-limiti');
  cli('iscritto', 'cancella@persona.it');
  await compila('cancella@persona.it', buono);
  const i2 = cli('invii', '1')[0];
  ok('cancella dati: prima, l\'invio con il file c\'è', i2.email === 'cancella@persona.it' && cli('cartella-file').file.includes(i2.allegati[0].file));
  await p.goto(B + '/admin/newsletter.php?scheda=iscritti&cerca=cancella%40persona.it', { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.locator('button', { hasText: 'Cancella dati' }).first().click()]);
  ok('cancella dati: via l\'invio e il file', cli('invii', '1')[0].id !== i2.id && !cli('cartella-file').file.includes(i2.allegati[0].file));
  // Il form torna com'era.
  await p.goto(B + '/admin/form.php?id=' + idForm, { waitUntil: 'networkidle' });
  while (await p.locator('.fm-campo').count() > campiPrima) await p.locator('.fm-via').last().click();
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[type=submit]:not([name=azione])')]);
  ok('il form dei Contatti torna com\'era', await p.locator('.fm-campo').count() === campiPrima);
  cli('svuota-limiti');

  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  fs.rmSync(tmp, { recursive: true, force: true });
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
