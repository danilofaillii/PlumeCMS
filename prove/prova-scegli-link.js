// 2.2: "Scegli dal sito". In ogni punto del pannello dove si scrive un link, la finestra che cerca per titolo fra pagine,
// articoli, categorie, tag e contenuti degli addon e scrive il link da sola. Per ogni punto: apre la finestra come una
// persona, cerca, sceglie, salva, e poi sul sito pubblico fa clic sul pulsante o sul link e guarda dove arriva (200).
// Poi la tastiera (frecce, Invio, Esc, il fuoco che torna), la bozza segnata, un titolo con <script> che resta testo,
// il redattore (vede le bozze delle pagine, non quelle degli addon), il telefono a 390 px, il sito in una cartella
// (il link porta dentro la cartella) e il cambio d'indirizzo di una pagina già linkata (il redirect 301 del CMS).
// Prepara da solo (prove/scegli-link-cli.php): pagine e articoli di prova, gli otto addon accesi, un evento, un prodotto,
// un corso, un redattore. Alla fine rimette com'erano form, impostazioni dei social e degli addon, e toglie i dati di prova.
// Serve un sito con il kit Attività (prove/prova-kit.js).
// uso: CMS_DIR=<cartella del CMS installato> node prove/prova-scegli-link.js <base> <utente> <password>
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello'); // UX 2026-11: conferme del pannello
const path = require('path');
const { execFileSync } = require('child_process');
const [B, UTENTE, PASSWORD] = process.argv.slice(2);
const DIR = process.env.CMS_DIR;
if (!B || !DIR) { console.error('uso: CMS_DIR=<cartella> node prove/prova-scegli-link.js <base> <utente> <password>'); process.exit(2); }
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const dorme = (ms) => new Promise(r => setTimeout(r, ms));
const cli = (...a) => { const o = execFileSync('php', [path.join(__dirname, 'scegli-link-cli.php'), DIR, ...a], { encoding: 'utf8' }).trim(); try { return JSON.parse(o); } catch (e) { return o; } };
const ADDON = ['prenotazioni', 'eventi-iscrizione', 'recensioni', 'vetrina', 'statistiche', 'membri', 'corsi', 'copia-altrove'];
const ORIGINE = new URL(B).origin, CARTELLA = new URL(B).pathname.replace(/\/$/, ''); // '' nella radice, '/test' in una cartella
const D = 'dialog.pn-link';

(async () => {
  cli('pulisci');
  const dati = cli('prepara');
  ok('dati di prova pronti', dati.pagina > 0 && dati.bozza > 0 && dati.prenota > 0, dati);
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  const err = [];
  const guarda = (pg) => {
    pg.on('pageerror', e => err.push(pg.url() + ' ' + e.message.slice(0, 160)));
    pg.on('console', m => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) err.push(pg.url() + ' ' + m.text().slice(0, 160)); });
    pnConferme.on(pg, d => d.accept());
  };
  guarda(p);
  const vai = async (pg, u) => { await pg.goto(B + '/admin/' + u, { waitUntil: 'networkidle' }); };
  const entra = async (pg, u, pw) => {
    await vai(pg, 'login.php'); await pg.fill('#username', u); await pg.fill('#password', pw);
    await Promise.all([pg.waitForNavigation(), pg.click('button[type=submit]')]);
  };
  await entra(p, UTENTE, PASSWORD);

  // Il pulsante "Scegli dal sito" accanto a un campo, la finestra, la scelta di una voce per titolo.
  const pulsante = (pg, campo) => pg.locator(`.pn-link-campo:has(${campo}) > .pn-link-scegli`);
  const aperta = (pg) => pg.waitForSelector(D + '[open]', { timeout: 10000 });
  const pronta = (pg) => pg.waitForFunction((d) => { const l = document.querySelector(d + ' .pn-link-lista'); return l && !l.hasAttribute('aria-busy') && document.querySelector(d + ' .pn-link-stato').textContent !== ''; }, D, { timeout: 10000 });
  async function cerca(pg, testo) {
    await pg.fill(D + ' input[type=search]', testo);
    await dorme(350); await pronta(pg);
  }
  const voce = (pg, titolo) => pg.locator(D + ' .pn-link-voce').filter({ has: pg.locator('.pn-link-nome', { hasText: titolo }) }).first();
  async function scegli(pg, bottone, testo, titolo) {
    await bottone.click(); await aperta(pg); await pronta(pg);
    await cerca(pg, testo);
    const v = voce(pg, titolo);
    await v.waitFor({ timeout: 5000 }).catch(() => {});
    if (!(await v.count())) { const nomi = await pg.locator(D + ' .pn-link-nome').allInnerTexts(); await pg.keyboard.press('Escape'); return { trovata: false, nomi }; }
    const info = await v.innerText();
    await v.click();
    await pg.waitForFunction((d) => !document.querySelector(d).open, D);
    return { trovata: true, info };
  }
  // Sul sito: apre la pagina, fa clic sul link, risponde con l'indirizzo finale e il codice.
  async function clic(pg, pagina, sel) {
    await pg.goto(B + pagina, { waitUntil: 'networkidle' });
    const l = pg.locator(sel).first();
    if (!(await l.count())) return { manca: sel };
    const href = await l.getAttribute('href');
    // I link dei testi (Quill) si aprono in una scheda nuova, come prima della 2.2: si segue la scheda nuova.
    if ((await l.getAttribute('target')) === '_blank') {
      const [nuova] = await Promise.all([pg.context().waitForEvent('page'), l.click()]);
      await nuova.waitForLoadState('load');
      const out = { href, url: nuova.url(), stato: (await pg.request.get(nuova.url())).status(), scheda: 'nuova' };
      await nuova.close();
      return out;
    }
    const [r] = await Promise.all([pg.waitForNavigation({ waitUntil: 'load' }), l.click()]);
    return { href, url: pg.url(), stato: r ? r.status() : 0 };
  }
  const arriva = (r, percorso) => r && r.url === B + percorso && r.stato === 200;
  const api = (pg, q, cosa = '') => pg.evaluate(async ([q, cosa]) => {
    const fd = new FormData(); fd.append('_csrf', document.querySelector('meta[name=pn-csrf]').content); fd.append('q', q); fd.append('cosa', cosa);
    const r = await fetch('link-api.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    return { stato: r.status, j: await r.json() };
  }, [q, cosa]);
  const sito = await ctx.newPage(); guarda(sito);

  // ---------------------------------------------------------------- addon accesi, contenuti degli addon
  for (const a of ADDON) {
    await vai(p, 'addon.php');
    const b = p.locator(`[data-addon="${a}"] button[value=accendi]`);
    if (await b.count()) await Promise.all([p.waitForNavigation(), b.click()]);
  }
  await vai(p, 'addon.php');
  ok('gli otto addon accesi', await p.locator('.ad-card.acceso').count() === 8);
  cli('addon');

  // ---------------------------------------------------------------- il motore (link-api.php)
  await vai(p, 'index.php');
  {
    const c = await api(p, 'Contatti');
    const v = (c.j.voci || []).find(x => x.titolo === 'Contatti');
    ok('motore: "Contatti" trova la pagina, percorso senza cartella, indirizzo con la cartella, assoluto per le email',
      c.stato === 200 && v && v.tipo === 'pagina' && v.percorso === '/contatti/' && v.indirizzo === CARTELLA + '/contatti/' && v.assoluto === B + '/contatti/' && v.stato === 'pubblicato', v || c);
    const a = await api(p, 'offerta di prim');
    ok('motore: un articolo per una parte del titolo, senza maiuscole', (a.j.voci || []).some(x => x.tipo === 'articolo' && x.percorso === '/sl-offerta-di-primavera/'), a.j.voci);
    const cat = await api(p, 'novità del mese', 'categoria'), tag = await api(p, 'SL Offerte', 'tag');
    ok('motore: la categoria (/category/...) e il tag (/tag/...), filtrati per tipo', (cat.j.voci || []).length === 1 && cat.j.voci[0].percorso === '/category/' + dati.categoria + '/'
      && (tag.j.voci || []).length === 1 && tag.j.voci[0].percorso === '/tag/' + dati.tag + '/', { cat: cat.j.voci, tag: tag.j.voci });
    const bz = await api(p, 'Listino in bozza');
    ok('motore: la pagina in bozza c\'è, con lo stato "bozza"', (bz.j.voci || []).some(x => x.percorso === '/sl-listino-bozza/' && x.stato === 'bozza'), bz.j.voci);
    const h = await api(p, '', '');
    ok('motore: la home è "/"', (h.j.voci || []).some(x => x.tipo === 'pagina' && x.titolo === 'Home' && x.percorso === '/'));
    const tipi = h.j.tipi || {};
    ok('motore: i tipi degli addon accesi nella tendina (evento, prodotto, negozio, corso, area riservata)', ['evento', 'prodotto', 'negozio', 'corso', 'membri'].every(k => tipi[k]), tipi);
    const ev = await api(p, 'SL Serata'), evb = await api(p, 'SL Evento in bozza'), pr = await api(p, 'SL Candela'), co = await api(p, 'SL Corso'), me = await api(p, '', 'membri');
    ok('motore: l\'evento pubblicato (/evento/...)', (ev.j.voci || []).some(x => x.tipo === 'evento' && x.percorso === '/evento/sl-serata-di-prova/' && x.stato === 'pubblicato'), ev.j.voci);
    ok('motore: all\'amministratore anche l\'evento in bozza, segnato', (evb.j.voci || []).some(x => x.tipo === 'evento' && x.stato === 'bozza'), evb.j.voci);
    ok('motore: il prodotto della vetrina (/negozio/...)', (pr.j.voci || []).some(x => x.tipo === 'prodotto' && x.percorso === '/negozio/sl-candela-profumata/'), pr.j.voci);
    ok('motore: il corso (/corsi/...)', (co.j.voci || []).some(x => x.tipo === 'corso' && x.percorso === '/corsi/sl-corso-di-prova/'), co.j.voci);
    ok('motore: le pagine dell\'area riservata', (me.j.voci || []).some(x => x.percorso === '/accedi/') && (me.j.voci || []).every(x => x.tipo === 'membri'), me.j.voci);
    for (const [cosa, perc] of [['evento', '/evento/sl-serata-di-prova/'], ['prodotto', '/negozio/sl-candela-profumata/'], ['corso', '/corsi/sl-corso-di-prova/']]) {
      const r = await p.request.get(B + perc);
      ok(`motore: la pagina pubblica del ${cosa} risponde 200`, r.status() === 200, r.status());
    }
  }

  // ---------------------------------------------------------------- editor visuale: Apertura, Immagine, Testo
  await vai(p, `editor.php?tipo=pagine&id=${dati.pagina}`);
  await p.waitForFunction(() => { const f = document.querySelector('#ed-iframe'); return f && f.contentDocument && f.contentDocument.querySelector('.ed-sezione'); }, null, { timeout: 30000 });
  if (await p.locator('#ed-chiudi-revisioni').isVisible()) await p.click('#ed-chiudi-revisioni');
  const tela = p.frameLocator('#ed-iframe');
  const seleziona = async (tipo) => { await tela.locator(`[data-ed-tipo="${tipo}"]`).first().dispatchEvent('click'); await dorme(400); await p.click('.ed-schede-pannello button[data-pannello="modifica"]'); await dorme(250); };
  {
    await seleziona('hero');
    await p.locator('#ed-controlli .ed-voce-testa').first().click(); await dorme(250);
    const campo = p.locator('#ed-controlli .ed-voce-corpo .pn-link-campo input').first();
    const b = p.locator('#ed-controlli .ed-voce-corpo .pn-link-scegli').first();
    ok('editor, Apertura: accanto al Link del pulsante c\'è "Scegli dal sito"', await b.count() === 1 && (await b.innerText()).trim() === 'Scegli dal sito');
    // Con la tastiera: si apre con il fuoco sulla ricerca, freccia giù, Invio.
    await b.click(); await aperta(p); await pronta(p);
    ok('tastiera: la finestra si apre con il fuoco sulla ricerca', await p.evaluate((d) => document.activeElement === document.querySelector(d + ' input[type=search]'), D));
    await cerca(p, 'contatti');
    await p.keyboard.press('ArrowDown');
    ok('tastiera: freccia giù porta sulla prima voce', await p.evaluate(() => document.activeElement.classList.contains('pn-link-voce') && document.activeElement.textContent.includes('Contatti')));
    await p.keyboard.press('ArrowUp');
    ok('tastiera: freccia su dalla prima voce torna alla ricerca', await p.evaluate(() => document.activeElement.type === 'search'));
    await p.keyboard.press('ArrowDown'); await p.keyboard.press('Enter');
    await p.waitForFunction((d) => !document.querySelector(d).open, D);
    ok('tastiera: Invio sceglie, la finestra si chiude, il link è nella casella', await campo.inputValue() === '/contatti/', await campo.inputValue());
    ok('tastiera: il fuoco torna nella casella del link', await p.evaluate(() => document.activeElement && document.activeElement.closest('.pn-link-campo') !== null));
    // Esc chiude senza cambiare niente e il fuoco torna al pulsante.
    await b.focus(); await b.press('Enter'); await aperta(p); await pronta(p);
    ok('la finestra segna la scelta di adesso', await p.locator(D + ' .pn-link-voce.pn-link-scelta').count() === 1 && (await p.locator(D + ' .pn-link-voce.pn-link-scelta').innerText()).includes('Contatti'));
    await p.keyboard.press('Escape'); await dorme(200);
    ok('tastiera: Esc chiude, il link resta quello di prima, il fuoco torna al pulsante', !(await p.locator(D + '[open]').count()) && await campo.inputValue() === '/contatti/'
      && await p.evaluate(() => document.activeElement.classList.contains('pn-link-scegli')));
    // Canc dentro la finestra non toglie il widget dalla tela.
    await b.click(); await aperta(p); await pronta(p); await p.keyboard.press('ArrowDown'); await p.keyboard.press('Delete'); await p.keyboard.press('Escape'); await dorme(300);
    ok('Canc dentro la finestra non cancella l\'Apertura dalla tela', await tela.locator('[data-ed-tipo="hero"]').count() === 1);
  }
  {
    await seleziona('immagine');
    const b = p.locator('#ed-controlli .ed-tipo-link .pn-link-scegli').first();
    const r = await scegli(p, b, 'offerta', 'SL Offerta di primavera');
    ok('editor, Immagine: il controllo Link sceglie un articolo', r.trovata && await p.locator('#ed-controlli .ed-tipo-link .pn-link-campo input').first().inputValue() === '/sl-offerta-di-primavera/', r);
  }
  {
    await seleziona('testo');
    const ed = p.locator('#ed-controlli .ed-quill .ql-editor').first();
    await ed.click(); await p.keyboard.press('Control+End'); await p.keyboard.press('ArrowLeft');
    for (let i = 0; i < 3; i++) await p.keyboard.press('Shift+ArrowLeft');
    const r = await scegli(p, p.locator('#ed-controlli .ql-toolbar .ql-link').first(), 'prenota', 'SL Prenota ora');
    ok('editor, Testo: il pulsante link di Quill apre "Scegli dal sito" e fa diventare link la parola scelta', r.trovata && await ed.locator('a[href="/sl-prenota-ora/"]').innerText().catch(() => '') === 'qui', await ed.innerHTML());
    await ed.click(); await p.keyboard.press('Control+End'); await p.keyboard.type(' Scrivici: ');
    const r2 = await scegli(p, p.locator('#ed-controlli .ql-toolbar .ql-link').first(), 'contatti', 'Contatti');
    ok('editor, Testo: senza niente selezionato scrive il titolo della pagina come link', r2.trovata && await ed.locator('a[href="/contatti/"]').innerText().catch(() => '') === 'Contatti', await ed.innerHTML());
  }
  await p.click('#ed-pubblica');
  await p.waitForFunction(() => /Pubblicat/.test(document.querySelector('#ed-stato').textContent), null, { timeout: 20000 });
  {
    const r1 = await clic(sito, '/sl-pulsanti/', '.ed-w-hero a.btn:has-text("Prenota")');
    ok('sito: il pulsante Prenota dell\'Apertura porta ai Contatti (clic vero, 200)', arriva(r1, '/contatti/'), r1);
    const r2 = await clic(sito, '/sl-pulsanti/', '.ed-w-immagine a');
    ok('sito: l\'immagine porta all\'articolo', arriva(r2, '/sl-offerta-di-primavera/'), r2);
    const r3 = await clic(sito, '/sl-pulsanti/', '.ed-w-testo a:has-text("qui")');
    ok('sito: la parola "qui" del testo porta alla pagina Prenota ora', arriva(r3, '/sl-prenota-ora/'), r3);
    const r4 = await clic(sito, '/sl-pulsanti/', '.ed-w-testo a:has-text("Contatti")');
    ok('sito: il link scritto dal titolo porta ai Contatti', arriva(r4, '/contatti/'), r4);
    if (CARTELLA) ok('sito in cartella: i link della pagina vanno dentro la cartella', [r1, r2, r3, r4].every(r => r.href && r.href.startsWith(CARTELLA + '/')), [r1.href, r2.href, r3.href, r4.href]);
  }

  // ---------------------------------------------------------------- popup: pulsante e "indirizzi elencati"
  await vai(p, 'popup.php?nuovo=1');
  await p.fill('input[name=nome]', 'SL Popup'); await p.check('input[name=attivo]');
  await p.fill('input[name=titolo]', 'Prenota adesso'); await p.fill('input[name=pulsante_testo]', 'Vai alla prenotazione');
  {
    const r = await scegli(p, pulsante(p, '#pp-link'), 'prenota', 'SL Prenota ora');
    ok('Popup: "Link del pulsante" scelto dal sito', r.trovata && await p.inputValue('#pp-link') === '/sl-prenota-ora/', await p.inputValue('#pp-link'));
    await p.selectOption('select[name=dove]', 'percorsi');
    await scegli(p, pulsante(p, '#pp-percorsi'), 'SL Pulsanti', 'SL Pulsanti');
    await scegli(p, pulsante(p, '#pp-percorsi'), 'contatti', 'Contatti');
    ok('Popup: gli "indirizzi elencati" si aggiungono una riga per volta', await p.inputValue('#pp-percorsi') === '/sl-pulsanti/\n/contatti/', await p.inputValue('#pp-percorsi'));
    await p.selectOption('select[name=innesco]', 'secondi'); await p.fill('input[name=valore]', '0'); await p.fill('input[name=frequenza_giorni]', '0');
    await Promise.all([p.waitForNavigation(), p.click('form button.btn-primary:has-text("Salva")')]);
    ok('Popup: salvato', /popup\.php\?id=\d+/.test(p.url()) && await p.inputValue('#pp-link') === '/sl-prenota-ora/', p.url());
    await sito.goto(B + '/sl-pulsanti/', { waitUntil: 'networkidle' });
    await sito.waitForSelector('.popup:not([hidden]) a.btn', { timeout: 8000 }).catch(() => {});
    const b = sito.locator('.popup:not([hidden]) a.btn').first();
    const href = await b.getAttribute('href').catch(() => '');
    let rp = { manca: 'popup' };
    if (href) { const [x] = await Promise.all([sito.waitForNavigation(), b.click()]); rp = { href, url: sito.url(), stato: x ? x.status() : 0 }; }
    ok('sito: il popup compare sulla pagina elencata e il suo pulsante porta a Prenota ora', arriva(rp, '/sl-prenota-ora/'), rp);
  }

  // ---------------------------------------------------------------- form: "Continua" della pagina di ringraziamento
  await vai(p, 'form.php?id=1');
  const grazieDiPrima = await p.inputValue('#fm-grazie-link');
  {
    const r = await scegli(p, pulsante(p, '#fm-grazie-link'), 'prenota', 'SL Prenota ora');
    await Promise.all([p.waitForNavigation(), p.click('form button.btn-primary:has-text("Salva")')]);
    await vai(p, 'form.php?id=1');
    ok('Form: il link del pulsante "Continua" scelto dal sito e salvato', r.trovata && await p.inputValue('#fm-grazie-link') === '/sl-prenota-ora/', await p.inputValue('#fm-grazie-link'));
    const s = await clic(sito, '/grazie/?f=1', 'a.btn:has-text("Continua")');
    ok('sito: "Continua" della pagina di ringraziamento porta a Prenota ora', arriva(s, '/sl-prenota-ora/'), s);
    await p.fill('#fm-grazie-link', grazieDiPrima);
    await Promise.all([p.waitForNavigation(), p.click('form button.btn-primary:has-text("Salva")')]);
  }

  // ---------------------------------------------------------------- newsletter: pulsante e link del testo, indirizzi interi
  await vai(p, 'newsletter.php');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=nuova]) button')]);
  const campagna = p.url().split('admin/')[1];
  {
    await p.fill('#oggetto', 'SL Campagna di prova');
    const r = await scegli(p, pulsante(p, '#nl-pulsante-link'), 'contatti', 'Contatti');
    ok('Newsletter: il link del pulsante è l\'indirizzo intero (serve alle email)', r.trovata && await p.inputValue('#nl-pulsante-link') === B + '/contatti/', await p.inputValue('#nl-pulsante-link'));
    const ed = p.locator('#editor .ql-editor');
    await ed.click(); await p.keyboard.type('Scopri il listino'); await p.keyboard.press('Control+A');
    const r2 = await scegli(p, p.locator('.ql-toolbar .ql-link').first(), 'prenota', 'SL Prenota ora');
    ok('Newsletter: il link nel testo dell\'email, scelto dal sito, è l\'indirizzo intero', r2.trovata && await ed.locator(`a[href="${B}/sl-prenota-ora/"]`).count() === 1, await ed.innerHTML());
    await Promise.all([p.waitForNavigation(), p.click('button[name=azione][value=salva]')]);
    await vai(p, campagna);
    ok('Newsletter: salvati, dopo aver ricaricato ci sono ancora', await p.inputValue('#nl-pulsante-link') === B + '/contatti/' && await p.locator(`#editor .ql-editor a[href="${B}/sl-prenota-ora/"]`).count() === 1);
    const st = (await p.request.get(B + '/sl-prenota-ora/')).status();
    ok('Newsletter: l\'indirizzo intero si apre (200)', st === 200, st);
  }

  // ---------------------------------------------------------------- social: link in più di WhatsApp
  await vai(p, 'social.php?scheda=impostazioni');
  const waDiPrima = await p.inputValue('#so-wa-l');
  {
    const r = await scegli(p, pulsante(p, '#so-wa-l'), 'contatti', 'Contatti');
    await Promise.all([p.waitForNavigation(), p.click('form:has(#so-wa-l) button.btn-primary')]);
    await vai(p, 'social.php?scheda=impostazioni');
    ok('Social: il link in più di WhatsApp scelto dal sito e salvato', r.trovata && await p.inputValue('#so-wa-l') === '/contatti/', await p.inputValue('#so-wa-l'));
    await p.fill('#so-wa-l', waDiPrima);
    await Promise.all([p.waitForNavigation(), p.click('form:has(#so-wa-l) button.btn-primary')]);
  }

  // ---------------------------------------------------------------- SEO: redirect, dati per Google, motori
  await vai(p, 'seo.php?scheda=redirect');
  {
    await p.fill('#seo-redirect-da', '/sl-vecchia-pagina/');
    ok('SEO, redirect: "Scegli dal sito" anche accanto al vecchio indirizzo', await pulsante(p, '#seo-redirect-da').count() === 1);
    const r = await scegli(p, pulsante(p, '#seo-redirect-a'), 'prenota', 'SL Prenota ora');
    ok('SEO, redirect: il nuovo indirizzo scelto dal sito', r.trovata && await p.inputValue('#seo-redirect-a') === '/sl-prenota-ora/');
    await Promise.all([p.waitForNavigation(), p.click('#seo-redirect-form button[type=submit]')]);
    await sito.goto(B + '/sl-vecchia-pagina/', { waitUntil: 'load' });
    ok('sito: il vecchio indirizzo porta a Prenota ora', sito.url() === B + '/sl-prenota-ora/', sito.url());
  }
  await vai(p, 'seo.php?scheda=schema');
  {
    const r = await scegli(p, pulsante(p, '#seo-jsonld-url'), 'contatti', 'Contatti');
    await p.click('#seo-jsonld-vai');
    await p.waitForFunction(() => !document.querySelector('#seo-jsonld-pre').hidden, null, { timeout: 10000 }).catch(() => {});
    ok('SEO, dati per Google: l\'indirizzo da mostrare scelto dal sito, e il calcolo parte', r.trovata && await p.inputValue('#seo-jsonld-url') === '/contatti/' && !(await p.locator('#seo-jsonld-pre').isHidden()));
  }
  await vai(p, 'seo.php?scheda=motori');
  for (const id of ['#seo-indexnow-url', '#seo-google-url']) {
    if (!(await p.locator(id).count())) { ok(`SEO, motori: ${id} non c'è in questa configurazione (salto)`, true); continue; }
    const r = await scegli(p, pulsante(p, id), 'offerta', 'SL Offerta di primavera');
    ok(`SEO, motori: ${id} scelto dal sito`, r.trovata && await p.inputValue(id) === '/sl-offerta-di-primavera/');
  }

  // ---------------------------------------------------------------- menu: link personalizzato e indirizzo di una voce
  await vai(p, 'menu.php?nuovo=1');
  {
    await p.fill('input[name=nome]', 'SL Menu');
    await p.click('summary:has-text("Link personalizzato")');
    const r = await scegli(p, pulsante(p, '#mn-link-url'), 'offerta', 'SL Offerta di primavera');
    ok('Menu: il link personalizzato scelto dal sito, con il testo della voce dal titolo', r.trovata && await p.inputValue('#mn-link-url') === '/sl-offerta-di-primavera/' && await p.inputValue('#mn-link-testo') === 'SL Offerta di primavera');
    await p.click('[data-al-clic="mnAggiungiLink()"]');
    const v = p.locator('#mn-albero .mn-voce-ed').last();
    ok('Menu: la voce aggiunta è un Articolo (segue l\'indirizzo se cambia)', (await v.locator('.mn-tipo').innerText()) === 'Articolo');
    await v.locator('.mn-apri').click();
    const r2 = await scegli(p, v.locator('.pn-link-scegli'), 'contatti', 'Contatti');
    ok('Menu: l\'indirizzo di una voce cambiato dal sito: diventa la pagina Contatti', r2.trovata && await v.locator('[data-c=url]').inputValue() === '/contatti/' && (await v.locator('.mn-tipo').innerText()) === 'Pagina');
    await Promise.all([p.waitForNavigation(), p.click('#mn-form button.btn-primary[type=submit]')]);
    const id = +(p.url().match(/id=(\d+)/) || [0, 0])[1];
    const voci = JSON.parse(cli('leggi', 'menu', 'voci', String(id)).valore || '[]');
    ok('Menu: salvato con tipo pagina e lo slug', voci.length === 1 && voci[0].tipo === 'pagina' && voci[0].rif === 'contatti' && voci[0].etichetta === 'SL Offerta di primavera', voci);
    if (id) { await vai(p, 'menu.php?id=' + id); await Promise.all([p.waitForNavigation(), p.click('button[name=azione][value=elimina]')]); }
  }

  // ---------------------------------------------------------------- addon: pagina dell'informativa privacy
  for (const [nome, u, campo, pagina] of [['Eventi', 'addon-pagina.php?a=eventi-iscrizione&p=impostazioni', '#evi-privacy', '/evento/sl-serata-di-prova/'], ['Prenotazioni', 'addon-pagina.php?a=prenotazioni&p=impostazioni', '#pren-privacy', '']]) {
    await vai(p, u);
    if (!(await p.locator(campo).count())) { ok(`${nome}: il campo della privacy c'è`, false); continue; }
    const prima = await p.inputValue(campo);
    const r = await scegli(p, pulsante(p, campo), 'chi siamo', 'Chi siamo');
    await Promise.all([p.waitForNavigation(), p.click(`form:has(${campo}) button.btn-primary`)]);
    await vai(p, u);
    ok(`${nome}: la pagina dell'informativa privacy scelta dal sito e salvata`, r.trovata && await p.inputValue(campo) === '/chi-siamo/', await p.inputValue(campo));
    if (pagina) {
      await sito.goto(B + pagina, { waitUntil: 'networkidle' });
      const href = await sito.locator('a:has-text("informativa privacy")').first().getAttribute('href').catch(() => '');
      const st = href ? (await sito.request.get(new URL(href, ORIGINE).href)).status() : 0;
      ok(`sito, ${nome}: il link "informativa privacy" porta a Chi siamo (200)`, href === CARTELLA + '/chi-siamo/' && st === 200, { href, st });
    }
    await p.fill(campo, prima);
    await Promise.all([p.waitForNavigation(), p.click(`form:has(${campo}) button.btn-primary`)]);
  }

  // ---------------------------------------------------------------- bozza segnata, titolo cattivo
  await vai(p, 'popup.php?nuovo=1');
  {
    await pulsante(p, '#pp-link').click(); await aperta(p); await pronta(p);
    await cerca(p, 'listino');
    const b = voce(p, 'SL Listino in bozza');
    ok('finestra: la pagina in bozza ha il segno "bozza"', await b.count() === 1 && (await b.locator('.pn-link-bozza').innerText()).trim() === 'bozza');
    await b.click(); await dorme(200);
    ok('finestra: anche una bozza si può scegliere', await p.inputValue('#pp-link') === '/sl-listino-bozza/');
    await pulsante(p, '#pp-link').click(); await aperta(p); await pronta(p);
    await cerca(p, 'Cattivo');
    const c = voce(p, 'SL Cattivo');
    const testo = await c.locator('.pn-link-nome').innerText().catch(() => '');
    ok('finestra: un titolo con <script> e <img onerror> si legge come testo e non parte', testo.includes('<script>') && testo.includes('<img') && await p.locator(D + ' .pn-link-lista img, ' + D + ' .pn-link-lista script').count() === 0
      && await p.evaluate(() => window.__slXss === undefined), testo);
    await cerca(p, 'nessuna pagina ha questo titolo xyz');
    ok('finestra: nessun risultato, con la spiegazione', await p.locator(D + ' .pn-link-vuoto').count() === 1);
    // Oppure scrivi un indirizzo a mano.
    await p.click(D + ' .pn-link-mano summary');
    await p.fill(D + ' .pn-link-mano input', 'https://www.esempio.it/pagina');
    await p.click(D + ' .pn-link-mano button');
    ok('finestra: "Oppure scrivi un indirizzo" lo mette nella casella', await p.inputValue('#pp-link') === 'https://www.esempio.it/pagina');
    await p.fill('#pp-link', '/scritto-a-mano/');
    ok('la casella resta scrivibile a mano', await p.inputValue('#pp-link') === '/scritto-a-mano/');
  }

  // ---------------------------------------------------------------- telefono a 390 px
  {
    const tel = await br.newContext({ viewport: { width: 390, height: 844 }, storageState: await ctx.storageState() });
    const t = await tel.newPage(); guarda(t);
    for (const [u, campo] of [['popup.php?nuovo=1', '#pp-link'], ['form.php?id=1', '#fm-grazie-link'], ['seo.php?scheda=redirect', '#seo-redirect-a'], ['social.php?scheda=impostazioni', '#so-wa-l'], [campagna, '#nl-pulsante-link'], ['menu.php', ''], ['addon-pagina.php?a=eventi-iscrizione&p=impostazioni', '#evi-privacy']]) {
      await vai(t, u);
      const largo = await t.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      const vis = campo ? await pulsante(t, campo).isVisible() : true;
      ok(`telefono 390: ${u.split('?')[0]} senza scorrimento di lato, il pulsante si vede`, largo <= 1 && vis, { largo, vis });
    }
    await vai(t, 'popup.php?nuovo=1');
    await pulsante(t, '#pp-link').click(); await aperta(t); await pronta(t);
    const box = await t.locator(D).boundingBox();
    const dentro = await t.evaluate((d) => { const x = document.querySelector(d); return x.scrollWidth - x.clientWidth; }, D);
    ok('telefono 390: la finestra prende tutto lo schermo, niente di lato', box && box.width >= 389 && box.x <= 1 && dentro <= 1, { box, dentro });
    await cerca(t, 'contatti');
    await voce(t, 'Contatti').click(); await dorme(200);
    ok('telefono 390: si sceglie con un tocco', await t.inputValue('#pp-link') === '/contatti/');
    // L'editor al telefono: il pulsante accanto al link dell'Apertura.
    await vai(t, `editor.php?tipo=pagine&id=${dati.pagina}`);
    await t.waitForFunction(() => { const f = document.querySelector('#ed-iframe'); return f && f.contentDocument && f.contentDocument.querySelector('.ed-sezione'); }, null, { timeout: 30000 });
    await tel.close();
  }

  // ---------------------------------------------------------------- il redattore
  {
    const rc = await br.newContext({ viewport: { width: 1280, height: 900 } });
    const r = await rc.newPage(); guarda(r);
    await entra(r, dati.redattore, dati.password);
    await vai(r, 'index.php');
    const a = await api(r, 'SL');
    const titoli = (a.j.voci || []).map(x => x.titolo);
    ok('redattore: il motore gli risponde', a.stato === 200, a.stato);
    ok('redattore: vede pagine e articoli, anche la pagina in bozza (come nel pannello)', titoli.includes('SL Listino in bozza') && titoli.includes('SL Offerta di primavera'), titoli);
    ok('redattore: degli addon solo quello che è già pubblico (niente evento in bozza)', titoli.includes('SL Serata di prova') && !titoli.some(x => x.includes('Evento in bozza')), titoli);
    await vai(r, `editor.php?tipo=pagine&id=${dati.pagina}`);
    await r.waitForFunction(() => { const f = document.querySelector('#ed-iframe'); return f && f.contentDocument && f.contentDocument.querySelector('.ed-sezione'); }, null, { timeout: 30000 });
    if (await r.locator('#ed-chiudi-revisioni').isVisible()) await r.click('#ed-chiudi-revisioni');
    await r.frameLocator('#ed-iframe').locator('[data-ed-tipo="immagine"]').first().dispatchEvent('click'); await dorme(400);
    await r.click('.ed-schede-pannello button[data-pannello="modifica"]'); await dorme(250);
    const s = await scegli(r, r.locator('#ed-controlli .ed-tipo-link .pn-link-scegli').first(), 'chi siamo', 'Chi siamo');
    ok('redattore: nell\'editor sceglie una pagina dal sito', s.trovata && await r.locator('#ed-controlli .ed-tipo-link .pn-link-campo input').first().inputValue() === '/chi-siamo/', s);
    await vai(r, 'popup.php?nuovo=1');
    ok('redattore: le pagine dell\'amministratore restano chiuse (il popup no)', !r.url().includes('popup.php'), r.url());
    await rc.close();
  }

  // ---------------------------------------------------------------- l'indirizzo di una pagina linkata cambia
  {
    const x = cli('slug', String(dati.prenota), 'sl-prenota-adesso');
    ok('cambio d\'indirizzo: il CMS scrive da solo il redirect 301 dal vecchio al nuovo', (x.redirect || []).some(r => r.da === '/sl-prenota-ora/' && r.a === '/sl-prenota-adesso/' && +r.codice === 301), x);
    // Il popup (acceso su questa pagina) ha il pulsante scelto prima: un clic vero.
    await sito.goto(B + '/sl-pulsanti/', { waitUntil: 'networkidle' });
    await sito.waitForSelector('.popup:not([hidden]) a.btn', { timeout: 8000 }).catch(() => {});
    const b = sito.locator('.popup:not([hidden]) a.btn').first();
    let r = { manca: 'popup' };
    if (await b.count()) { const href = await b.getAttribute('href'); const [x] = await Promise.all([sito.waitForNavigation(), b.click()]); r = { href, url: sito.url(), stato: x ? x.status() : 0 }; }
    ok('cambio d\'indirizzo: il pulsante scelto prima porta ancora alla pagina (passando dal redirect)', r.href === CARTELLA + '/sl-prenota-ora/' && arriva(r, '/sl-prenota-adesso/'), r);
    const a = await api(p, 'SL Prenota');
    ok('cambio d\'indirizzo: la finestra propone già l\'indirizzo nuovo', (a.j.voci || []).some(v => v.percorso === '/sl-prenota-adesso/'), a.j.voci);
  }

  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  cli('pulisci');
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
