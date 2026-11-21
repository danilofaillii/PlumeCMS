// Popup costruiti con l'editor visuale (2.39.0), come li usa una persona, su un plumeCMS installato (meglio con il kit
// Attività: serve un form acceso da mettere dentro il popup).
//  1. scheda: "Costruito con l'editor visuale", "Apri nell'editor" crea il disegno con titolo, testo e pulsante e apre l'editor;
//     la tela mostra la finestra larga come nella scheda, con la X fuori dal disegno; "Torna al popup" e la freccia tornano alla scheda;
//  2. acceso ma con il disegno non pubblicato: non esce sul sito e la scheda lo dice;
//  3. disegno con Titolo, Testo, Immagine, Pulsanti (#chiudi) e un Form, pubblicato dall'editor: compare dopo i secondi scelti,
//     come finestra di dialogo con il nome dal titolo, il fuoco dentro; si chiude con la X, con Esc e con #chiudi, e non ricompare
//     per i giorni scelti; il form con un errore riapre il popup sulla risposta;
//  4. larghezze e le quattro posizioni (misurate), il fondo della finestra tolto, il telefono a 390 px senza scorrimento di lato;
//  5. anteprima nella scheda, versioni, Header e footer senza i disegni dei popup, elenco, eliminazione.
//  Con PRIMA=<file> e CAMPI=<id>: l'HTML del popup a campi CAMPI sulla home è identico al byte a quello salvato in PRIMA
//  (preso con lo stesso comando, `node prova-popup-editor.js impronta <base> <id>`, prima dell'aggiornamento).
// uso: node prova-popup-editor.js <base> <cartella CMS> <utente> <password> [cartella schermate]
//      node prova-popup-editor.js impronta <base> <id popup>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const pnConferme = require('./conferme-pannello');
const path = require('path');
const fs = require('fs');

const impronta = async (base, id) => {
  const r = await fetch(base + '/', { headers: { 'User-Agent': 'Mozilla/5.0 prova-popup' } });
  const h = await r.text();
  const i = h.indexOf('<div class="popup" id="popup-' + id + '"');
  if (i < 0) return '';
  const j = h.indexOf('</div></div>', i);
  return h.slice(i, j + 12);
};

if (process.argv[2] === 'impronta') {
  impronta(process.argv[3], process.argv[4]).then(t => { process.stdout.write(t); process.exit(t ? 0 : 1); });
} else (async () => {
  const [B, DIR, UTENTE, PASSWORD, FOTO_] = process.argv.slice(2);
  const FOTO = FOTO_ || '';
  let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
  const foto = async (pg, nome, o = {}) => { if (FOTO) await pg.screenshot({ path: path.join(FOTO, nome + '.png'), ...o }); };
  const cli = (...a) => execFileSync('php', [path.join(__dirname, 'popup-cli.php'), DIR, ...a], { encoding: 'utf8' }).trim();
  const uno = q => cli('uno', q);

  // 0. Il popup a campi di prima, identico al byte
  if (process.env.PRIMA && process.env.CAMPI) {
    // Il numero di versione dei file (?v=<data del file>) cambia con ogni aggiornamento: è l'unica cosa che si toglie.
    const senzaV = t => t.replace(/\?v=\d+/g, '?v=');
    const prima = fs.readFileSync(process.env.PRIMA, 'utf8'), dopo = await impronta(B, process.env.CAMPI);
    ok('popup a campi: HTML identico al byte a prima dell\'aggiornamento (tolto solo il ?v= dei file)', prima !== '' && senzaV(prima) === senzaV(dopo), { prima: prima.length, dopo: dopo.length });
  }

  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1360, height: 900 } });
  const p = await ctx.newPage();
  const errPannello = []; p.on('pageerror', e => errPannello.push(e.message.slice(0, 200)));
  pnConferme.on(p, d => d.accept());
  const flash = async () => (await p.locator('.flash').allInnerTexts()).join(' ');
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  const errSito = [];
  // Una persona nuova (memoria del browser vuota) che apre una pagina del sito.
  const visitatore = async (w = 1280, h = 900, u = '/') => {
    const c = await br.newContext({ viewport: { width: w, height: h }, isMobile: w < 500, hasTouch: w < 500 });
    const s = await c.newPage(); s.on('pageerror', e => errSito.push(e.message.slice(0, 200)));
    await s.goto(B + u, { waitUntil: 'networkidle' });
    return s;
  };
  const visibile = (s, id, t = 8000) => s.waitForSelector(`#popup-${id}:not([hidden])`, { timeout: t }).then(() => true).catch(() => false);
  const nascosto = async (s, id) => s.locator(`#popup-${id}`).evaluate(e => e.hidden).catch(() => null);

  // 1. Scheda nuova, "Costruito con l'editor visuale", Apri nell'editor
  await p.goto(B + '/admin/popup.php?nuovo=1', { waitUntil: 'networkidle' });
  ok('scheda: le due carte di "Cosa mostra"', await p.locator('#pp-c-campi').count() === 1 && await p.locator('#pp-c-editor').count() === 1);
  ok('scheda nuova: di partenza "Campi semplici", larghezza media, al centro, con il fondo',
    await p.locator('#pp-c-campi').isChecked() && await p.locator('#pp-larghezza').inputValue() === 'media' && await p.locator('#pp-posizione').inputValue() === 'centro' && await p.locator('input[type=checkbox][name=sfondo_finestra]').isChecked());
  await p.fill('#pp-nome', 'Benvenuto (editor)');
  await p.fill('#pp-titolo', 'Ciao, benvenuto');
  await p.check('#pp-c-editor');
  ok('scelta l\'editor: i campi semplici si nascondono, compare "Apri nell\'editor"', !(await p.locator('#pp-titolo').isVisible()) && await p.locator('#pp-apri-editor').isVisible());
  await p.check('input[name=attivo]');
  await p.selectOption('#pp-innesco', 'secondi'); await p.fill('#pp-valore', '2'); await p.fill('#pp-giorni', '7');
  await foto(p, 'popup-scheda-nuova', { fullPage: true });
  await Promise.all([p.waitForNavigation(), p.click('#pp-apri-editor')]);
  ok('"Apri nell\'editor" salva e apre l\'editor del disegno', /editor\.php\?tipo=modelli&id=\d+/.test(p.url()), p.url());
  const M = +(p.url().match(/id=(\d+)/) || [0, 0])[1];
  const P = +uno(`SELECT id FROM {p}popup WHERE modello_id = ${M}`);
  ok('il popup è salvato, collegato al disegno, di tipo "popup"', P > 0 && uno(`SELECT tipo FROM {p}modelli WHERE id = ${M}`) === 'popup' && uno(`SELECT contenuto FROM {p}popup WHERE id = ${P}`) === 'editor');
  await p.waitForFunction(() => { const f = document.querySelector('#ed-iframe'); return f && f.contentDocument && f.contentDocument.querySelector('.ed-sezione'); }, null, { timeout: 30000 });
  const tela = p.frameLocator('#ed-iframe');
  ok('tela: la finestra del popup con la tela dentro, sulla pagina velata', await tela.locator('.ed-popup-tela .popup-finestra #ed-pagina').count() === 1);
  const lt = await tela.locator('.ed-popup-finestra').evaluate(e => Math.round(e.getBoundingClientRect().width));
  ok('tela: la finestra è larga come la larghezza scelta (media, 560 px)', Math.abs(lt - 560) <= 2, lt);
  ok('tela: la X sta fuori dal disegno', await tela.locator('.ed-popup-finestra > .popup-chiudi').count() === 1 && await tela.locator('#ed-pagina .popup-chiudi').count() === 0);
  ok('tela: niente header e footer del sito', await tela.locator('#testata, #piede, header.top, footer.piede').count() === 0);
  const partenza = await tela.locator('#ed-pagina').innerText();
  ok('tela: il disegno di partenza ha il titolo dei campi semplici, un testo e il pulsante "Ho capito"', partenza.includes('Ciao, benvenuto') && /ho capito/i.test(partenza), partenza);
  ok('editor: "Torna al popup" e la freccia portano alla scheda del popup', (await p.locator('#ed-torna-popup').getAttribute('href')) === 'popup.php?id=' + P && (await p.locator('a.ed-indietro').getAttribute('href')) === 'popup.php?id=' + P);
  ok('editor: sotto il nome dice che cosa è', /Popup/.test(await p.locator('#ed-percorso').innerText()));
  await foto(p, 'popup-editor', { clip: { x: 0, y: 0, width: 1360, height: 900 } });

  // 2. Acceso ma non pubblicato
  let s = await visitatore();
  ok('acceso con il disegno in bozza: sul sito non esce', await s.locator('#popup-' + P).count() === 0);
  await s.context().close();
  await p.goto(B + '/admin/popup.php?id=' + P, { waitUntil: 'networkidle' });
  ok('scheda: dice che il disegno è in bozza e che il popup non esce', /bozza/i.test(await p.locator('#pp-stato-disegno').innerText()) && /non esce sul sito/.test(await p.locator('#pp-avviso-pubblica').innerText()));
  await p.click('button[type=submit]:has-text("Salva")'); await p.waitForLoadState('networkidle');
  ok('Salva con il disegno non pubblicato: il messaggio lo dice', /non esce ancora/.test(await flash()), await flash());

  // 3. Il disegno: Titolo, Testo, Immagine, Pulsanti con #chiudi, Form; poi Pubblica dall'editor
  const F = +uno('SELECT id FROM {p}moduli WHERE attivo = 1 ORDER BY id LIMIT 1');
  ok('(preparazione) un form acceso da mettere nel popup', F > 0, F);
  // Un'immagine della libreria (cartella uploads del sito), altrimenti l'icona del sito.
  const cercaImg = (d, rel = '') => { for (const f of fs.existsSync(d) ? fs.readdirSync(d, { withFileTypes: true }) : []) { if (f.isDirectory()) { const x = cercaImg(path.join(d, f.name), rel + f.name + '/'); if (x) return x; } else if (/\.(jpe?g|png|webp|svg)$/i.test(f.name)) return '/uploads/' + rel + f.name; } return ''; };
  const img = cercaImg(path.join(DIR, 'uploads')) || '/favicon.ico';
  await p.goto(B + '/admin/editor.php?tipo=modelli&id=' + M, { waitUntil: 'networkidle' });
  const st = await p.evaluate(async ({ M, F, img }) => {
    const D = JSON.parse(document.querySelector('#ed-dati').textContent);
    const w = (tipo, imp) => ({ id: 'pp' + Math.random().toString(36).slice(2, 9), tipo, impostazioni: imp });
    const a = { versione: 1, elementi: [{ id: 'ppsez01', tipo: 'sezione', impostazioni: { larghezza: 'contenuta', spazio_sopra: { d: 32 }, spazio_sotto: { d: 32 } }, figli: [{ id: 'ppcol01', tipo: 'colonna', impostazioni: {}, figli: [
      w('titolo', { occhiello: 'Solo per oggi', testo: 'Offerta di benvenuto', livello: 'h2' }),
      w('testo', { contenuto: '<p>Il dieci per cento sul primo ordine, per chi si iscrive adesso.</p>' }),
      w('immagine', { immagine: img, alt: 'Foto della bottega' }),
      w('pulsanti', { voci: [{ testo: 'No grazie', link: '#chiudi', stile: 'filo', nuova: false }] }),
      w('modulo', { tipo: 'form:' + F }),
    ] }] }] };
    const fd = new FormData();
    for (const [k, v] of Object.entries({ _csrf: D.csrf, id: M, tipo: 'modelli', azione: 'salva', albero: JSON.stringify(a), pubblica: 0, nota: 'prova' })) fd.append(k, v);
    return (await fetch('editor-api.php', { method: 'POST', body: fd, credentials: 'same-origin' })).status;
  }, { M, F, img });
  ok('(preparazione) disegno con titolo, testo, immagine, pulsante #chiudi e form salvato in bozza', st === 200, st);
  await p.reload({ waitUntil: 'networkidle' });
  await p.waitForFunction(() => { const f = document.querySelector('#ed-iframe'); return f && f.contentDocument && f.contentDocument.querySelector('.ed-w-modulo'); }, null, { timeout: 30000 });
  await foto(p, 'popup-editor-disegno', { clip: { x: 0, y: 0, width: 1360, height: 900 } });
  await p.click('#ed-pubblica');
  await p.waitForFunction(() => /Pubblicat/.test(document.querySelector('#ed-stato').textContent), null, { timeout: 20000 });
  ok('Pubblica dall\'editor: il disegno è pubblicato', uno(`SELECT status FROM {p}modelli WHERE id = ${M}`) === 'published' && uno(`SELECT struttura IS NOT NULL FROM {p}modelli WHERE id = ${M}`) === '1');
  await p.goto(B + '/admin/popup.php?id=' + P, { waitUntil: 'networkidle' });
  ok('scheda: disegno "Pubblicato", nessun avviso', await p.locator('#pp-stato-disegno .badge-published').count() === 1 && await p.locator('#pp-avviso-pubblica').count() === 0);
  await p.locator('#pp-anteprima').scrollIntoViewIfNeeded();
  const ant = p.frameLocator('#pp-anteprima');
  ok('scheda: l\'anteprima mostra il popup aperto', await ant.locator(`#popup-${P}:not([hidden]) .popup-disegno`).count().catch(() => 0) === 1 || await ant.locator(`#popup-${P}:not([hidden])`).waitFor({ timeout: 10000 }).then(() => true).catch(() => false));
  await foto(p, 'popup-scheda', { fullPage: true });

  // Sul sito
  s = await visitatore();
  ok('sito: subito dopo l\'apertura il popup non si vede ancora', await nascosto(s, P) === true);
  ok('sito: dopo i secondi scelti compare', await visibile(s, P, 6000));
  const fin = s.locator(`#popup-${P} .popup-finestra`);
  const ar = await fin.evaluate(e => ({ role: e.getAttribute('role'), modale: e.getAttribute('aria-modal'), nome: (document.getElementById(e.getAttribute('aria-labelledby') || '') || {}).textContent || '', dentro: e.contains(document.activeElement) }));
  ok('sito: finestra di dialogo modale, con il nome dal primo titolo del disegno, il fuoco dentro', ar.role === 'dialog' && ar.modale === 'true' && ar.nome.includes('Offerta di benvenuto') && ar.dentro, ar);
  const dis = s.locator(`#popup-${P} .popup-disegno`);
  ok('sito: dentro ci sono titolo, testo, immagine, pulsante e form', (await dis.innerText()).includes('Il dieci per cento') && await dis.locator('img').count() >= 1 && await dis.locator('a[href="#chiudi"]').count() === 1 && await dis.locator(`form input[name=popup][value="${P}"]`).count() === 1);
  ok('sito: la X è fuori dal disegno', await s.locator(`#popup-${P} .popup-finestra > .popup-chiudi`).count() === 1 && await dis.locator('.popup-chiudi').count() === 0);
  ok('sito: nessun id ripetuto nella pagina', await s.evaluate(() => { const v = {}; for (const e of document.querySelectorAll('[id]')) { if (v[e.id]) return false; v[e.id] = 1; } return true; }));
  ok('sito: il CSS del disegno c\'è', await s.locator('#ed-css-popup-' + P).count() === 1);
  const mc = await fin.evaluate(e => { const r = e.getBoundingClientRect(); return { w: Math.round(r.width), cx: Math.round(r.left + r.width / 2 - innerWidth / 2), cy: Math.round(r.top + r.height / 2 - innerHeight / 2) }; });
  ok('sito: larghezza media (560 px), al centro', Math.abs(mc.w - 560) <= 2 && Math.abs(mc.cx) <= 2 && Math.abs(mc.cy) <= 2, mc);
  await foto(s, 'popup-sito-centro');
  await s.click(`#popup-${P} .popup-chiudi`);
  ok('X: il popup si chiude', await nascosto(s, P) === true);
  ok('X: il fuoco torna fuori dalla finestra', await s.evaluate(id => !document.getElementById(id).contains(document.activeElement), 'popup-' + P));
  await s.reload({ waitUntil: 'networkidle' }); await s.waitForTimeout(3500);
  ok('X: ricaricando la pagina non ricompare (giorni scelti: 7)', await nascosto(s, P) === true);
  await s.context().close();

  s = await visitatore();
  await visibile(s, P, 6000);
  await s.keyboard.press('Escape');
  ok('Esc: il popup si chiude', await nascosto(s, P) === true);
  await s.reload({ waitUntil: 'networkidle' }); await s.waitForTimeout(3500);
  ok('Esc: non ricompare per i giorni scelti', await nascosto(s, P) === true);
  await s.context().close();

  s = await visitatore();
  await visibile(s, P, 6000);
  // Tab dentro la finestra: il fuoco non esce (finestra modale)
  for (let i = 0; i < 25; i++) await s.keyboard.press('Tab');
  ok('Tab: il fuoco resta dentro la finestra', await s.evaluate(id => document.getElementById(id).contains(document.activeElement), 'popup-' + P));
  await s.click(`#popup-${P} a[href="#chiudi"]`);
  ok('#chiudi: il pulsante chiude il popup, l\'indirizzo non cambia', await nascosto(s, P) === true && !s.url().includes('#chiudi'), s.url());
  await s.reload({ waitUntil: 'networkidle' }); await s.waitForTimeout(3500);
  ok('#chiudi: conta come chiuso, non ricompare', await nascosto(s, P) === true);
  await s.context().close();

  // Il form del popup con un errore: la pagina riapre il popup sulla risposta
  s = await visitatore();
  await visibile(s, P, 6000);
  await Promise.all([s.waitForURL(/[?&]modulo=/, { timeout: 15000 }).catch(() => {}), s.evaluate(id => { const f = document.querySelector('#' + id + ' form'); f.noValidate = true; f.querySelectorAll('input[type=email]').forEach(i => { i.value = 'non-una-email'; }); f.submit(); }, 'popup-' + P)]);
  await s.waitForLoadState('networkidle');
  ok('form con un errore: si torna alla pagina con il popup indicato', /[?&]popup=\d+/.test(s.url()) && s.url().includes('popup=' + P), s.url());
  ok('form con un errore: il popup si riapre subito', await visibile(s, P, 3000));
  ok('form con un errore: il messaggio d\'errore sta nel form del popup', await s.locator(`#popup-${P} [role=alert], #popup-${P} .modulo-errore, #popup-${P} [aria-invalid=true]`).count() > 0);
  await foto(s, 'popup-sito-errore');
  await s.context().close();

  // 4. Larghezze e posizioni (dalla scheda)
  const imposta = async (larghezza, posizione, sfondo = true) => {
    await p.goto(B + '/admin/popup.php?id=' + P, { waitUntil: 'networkidle' });
    await p.selectOption('#pp-larghezza', larghezza); await p.selectOption('#pp-posizione', posizione);
    if (sfondo) await p.check('input[type=checkbox][name=sfondo_finestra]'); else await p.uncheck('input[type=checkbox][name=sfondo_finestra]');
    await p.fill('#pp-valore', '0');
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]:has-text("Salva")')]);
  };
  const misura = async (s) => s.locator(`#popup-${P} .popup-finestra`).evaluate(e => {
    const r = e.getBoundingClientRect(), c = getComputedStyle(e), pp = e.parentElement;
    // Un punto della pagina fuori dalla finestra (sopra una barra in basso, sotto una barra in alto, a sinistra altrimenti).
    const angolo = document.elementFromPoint(5, r.top > 20 ? 10 : innerHeight - 10);
    return { w: Math.round(r.width), sx: Math.round(r.left), dx: Math.round(innerWidth - r.right), su: Math.round(r.top), giu: Math.round(innerHeight - r.bottom), vw: innerWidth,
      modale: e.getAttribute('aria-modal'), velo: getComputedStyle(pp).backgroundColor, paginaLibera: !pp.contains(angolo), scorre: document.documentElement.scrollWidth > innerWidth,
      fondo: c.backgroundColor, padding: c.paddingTop, blocco: document.body.style.overflow };
  });
  for (const [l, atteso] of [['stretta', 420], ['larga', 900], ['piena', 1280 - 32]]) {
    await imposta(l, 'centro');
    s = await visitatore(); await visibile(s, P, 4000);
    const m = await misura(s);
    ok(`larghezza ${l}: ${atteso} px, al centro`, Math.abs(m.w - atteso) <= 2 && Math.abs(m.sx - m.dx) <= 2, m);
    await s.context().close();
  }
  await imposta('stretta', 'basso-destra');
  s = await visitatore(); await visibile(s, P, 4000);
  let m = await misura(s);
  ok('in basso a destra: attaccato all\'angolo (16 px), la pagina resta usabile, non modale', Math.abs(m.dx - 16) <= 2 && Math.abs(m.giu - 16) <= 2 && m.paginaLibera && m.modale === 'false' && m.blocco === '', m);
  await foto(s, 'popup-sito-basso-destra');
  await s.keyboard.press('Escape');
  ok('in basso a destra: Esc chiude', await nascosto(s, P) === true);
  await s.context().close();
  await imposta('media', 'barra-basso');
  s = await visitatore(); await visibile(s, P, 4000);
  m = await misura(s);
  ok('barra in basso: larga quanto lo schermo, attaccata al fondo, pagina usabile', m.w === m.vw && m.giu === 0 && m.paginaLibera && m.modale === 'false', m);
  await foto(s, 'popup-sito-barra-basso');
  await s.context().close();
  await imposta('media', 'barra-alto');
  s = await visitatore(); await visibile(s, P, 4000);
  m = await misura(s);
  ok('barra in alto: larga quanto lo schermo, attaccata in cima, pagina usabile', m.w === m.vw && m.su === 0 && m.paginaLibera && m.modale === 'false', m);
  await s.context().close();
  // Al telefono, ogni posizione: dentro lo schermo, niente scorrimento di lato
  for (const pos of ['centro', 'basso-destra', 'barra-basso', 'barra-alto']) {
    await imposta(pos === 'centro' ? 'larga' : 'media', pos);
    s = await visitatore(390, 844); await visibile(s, P, 4000);
    m = await misura(s);
    ok(`telefono 390 px, ${pos}: la finestra sta nello schermo, niente scorrimento di lato`, m.sx >= 0 && m.dx >= 0 && !m.scorre, m);
    if (pos === 'centro') await foto(s, 'popup-telefono');
    await s.context().close();
  }
  // Disegno più alto dello schermo: si scorre dentro la finestra
  s = await visitatore(390, 500); await visibile(s, P, 4000);
  const sc = await s.locator(`#popup-${P} .popup-finestra`).evaluate(e => ({ alto: e.scrollHeight > e.clientHeight, dentro: e.getBoundingClientRect().bottom <= innerHeight + 1, ov: getComputedStyle(e).overflowY }));
  ok('schermo basso: la finestra non esce dallo schermo e si scorre dentro', sc.dentro && (sc.ov === 'auto' || sc.ov === 'scroll'), sc);
  await s.context().close();
  // Senza il fondo della finestra
  await imposta('media', 'centro', false);
  s = await visitatore(); await visibile(s, P, 4000);
  m = await misura(s);
  ok('senza fondo: finestra trasparente e senza margine, la X resta', m.fondo === 'rgba(0, 0, 0, 0)' && m.padding === '0px' && await s.locator(`#popup-${P} .popup-chiudi`).isVisible(), m);
  await s.context().close();
  await imposta('media', 'centro', true);

  // Meno movimento: il popup si apre lo stesso
  {
    const c = await br.newContext({ viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce' });
    const r = await c.newPage(); await r.goto(B + '/', { waitUntil: 'networkidle' });
    ok('meno movimento: il popup compare lo stesso', await visibile(r, P, 4000));
    await c.close();
  }

  // Un secondo popup con l'editor, acceso ma mai pubblicato: non esce
  await p.goto(B + '/admin/popup.php?nuovo=1', { waitUntil: 'networkidle' });
  await p.fill('#pp-nome', 'Mai pubblicato'); await p.check('#pp-c-editor'); await p.check('input[name=attivo]'); await p.fill('#pp-valore', '0');
  await Promise.all([p.waitForNavigation(), p.click('#pp-apri-editor')]);
  const M2 = +(p.url().match(/id=(\d+)/) || [0, 0])[1];
  const P2 = +uno(`SELECT id FROM {p}popup WHERE modello_id = ${M2}`);
  s = await visitatore(); await s.waitForTimeout(1500);
  ok('popup acceso con il disegno mai pubblicato: non esce sul sito', P2 > 0 && await s.locator('#popup-' + P2).count() === 0);
  await s.context().close();
  await p.goto(B + '/admin/popup.php', { waitUntil: 'networkidle' });
  const el = await p.locator('table').innerText();
  ok('elenco: dice "Costruito con l\'editor" e che il secondo non esce', /Costruito con l'editor/.test(el) && /non esce/.test(el), el.slice(0, 300));
  await foto(p, 'popup-elenco', { fullPage: true });

  // 5. Versioni, Header e footer, esportazione
  await p.goto(B + '/admin/versioni.php?ambito=modello&oggetto=' + M, { waitUntil: 'networkidle' });
  const av = p.locator('a:has-text("Anteprima")').first();
  if (await av.count()) {
    const v = await ctx.newPage();
    await v.goto(new URL(await av.getAttribute('href'), p.url()).href, { waitUntil: 'networkidle' });
    ok('versioni: l\'anteprima di una versione mostra il popup aperto', await visibile(v, P, 4000));
    await v.close();
  } else ok('versioni: il disegno ha le sue versioni', false);
  for (const t of ['header', 'footer']) {
    await p.goto(B + '/admin/modelli.php?tipo=' + t, { waitUntil: 'networkidle' });
    ok(`Header e footer (${t}): i disegni dei popup non ci sono`, !(await p.locator('table').innerText()).includes('Popup:'));
  }
  const r = await p.evaluate(async (M) => {
    const fd = new FormData(); fd.append('_csrf', document.querySelector('input[name=_csrf]') ? document.querySelector('input[name=_csrf]').value : ''); fd.append('azione', 'pubblica'); fd.append('id', M); fd.append('tipo', 'header');
    const x = await fetch('modelli.php', { method: 'POST', body: fd, credentials: 'same-origin' }); return x.url;
  }, M2);
  ok('Header e footer: un disegno di popup non si pubblica da lì', uno(`SELECT status FROM {p}modelli WHERE id = ${M2}`) === 'draft', r);

  // Eliminazione: anche il disegno
  await p.goto(B + '/admin/popup.php?id=' + P2, { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.click('button[name=azione][value=elimina]')]);
  ok('Elimina: via il popup e il suo disegno', uno(`SELECT COUNT(*) FROM {p}popup WHERE id = ${P2}`) === '0' && uno(`SELECT COUNT(*) FROM {p}modelli WHERE id = ${M2}`) === '0');

  ok('nessun errore JavaScript nel pannello', errPannello.length === 0, errPannello);
  ok('nessun errore JavaScript sul sito', errSito.length === 0, errSito);
  console.log(`${n - ko} su ${n} OK`);
  await br.close();
  process.exit(ko ? 1 : 0);
})();
