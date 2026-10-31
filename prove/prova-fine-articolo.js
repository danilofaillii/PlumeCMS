// Articoli > Aggiungi sempre alla fine (1.6.0), su un plumeCMS appena installato (due articoli pubblicati).
//  1. la linguetta nella lista degli articoli porta alla pagina; il menu Articoli resta acceso;
//  2. "Crea il riquadro" apre l'editor: la tela mostra un articolo finto e il riquadro in fondo, con il testo di partenza;
//     il ritorno dall'editor porta alla pagina del riquadro;
//  3. in bozza il sito non lo mostra, l'anteprima della pagina sì;
//  4. Pubblica: esce in fondo a ogni articolo, dentro la colonna di lettura, dopo il testo, ombreggiato; non nelle pagine;
//     al telefono sta nella larghezza dello schermo;
//  5. Spegni e Accendi; versioni con l'anteprima di una versione; Header e footer non cambia.
//  Con [articolo largo] (un articolo fatto con sezioni a tutta larghezza e una Griglia di articoli in fondo, da
//  `php prove/articolo-largo.php <cartella CMS>`, che stampa /articolo-largo/): il riquadro esce dopo le sezioni, largo
//  quanto la lettura, mai dentro una scheda della griglia.
// uso: node prova-fine-articolo.js <base> <utente> <password> <articolo 1> <articolo 2> [cartella schermate] [articolo largo]
const { chromium } = require('playwright');
const path = require('path');
const [B, UTENTE, PASSWORD, ART1, ART2, FOTO_, LARGO] = process.argv.slice(2);
const FOTO = FOTO_ || '';
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const foto = async (pg, nome, o = {}) => { if (FOTO) await pg.screenshot({ path: path.join(FOTO, nome + '.png'), ...o }); };

(async () => {
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1360, height: 900 } });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('dialog', d => d.accept());
  const flash = async () => (await p.locator('.flash').allInnerTexts()).join(' ');
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  // Il sito visto da chi non è entrato nel pannello.
  const sito = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const riquadro = async (u) => { await sito.goto(B + u, { waitUntil: 'networkidle' }); return sito.locator('aside.fine-articolo'); };

  // 1. Linguetta e pagina
  await p.goto(B + '/admin/contenuti.php?tipo=articoli', { waitUntil: 'networkidle' });
  ok('lista degli articoli: c\'è la linguetta "Aggiungi sempre alla fine"', (await p.locator('#ct-fine-articolo').innerText()).trim() === 'Aggiungi sempre alla fine');
  await p.goto(B + '/admin/contenuti.php?tipo=pagine', { waitUntil: 'networkidle' });
  ok('lista delle pagine: la linguetta non c\'è', await p.locator('#ct-fine-articolo').count() === 0);
  await p.goto(B + '/admin/contenuti.php?tipo=articoli', { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.click('#ct-fine-articolo')]);
  ok('la linguetta apre fine-articolo.php', p.url().endsWith('/admin/fine-articolo.php'), p.url());
  ok('la linguetta è quella accesa', await p.locator('#fa-scheda.attivo').count() === 1);
  ok('il menu Articoli resta acceso', /Articoli/.test(await p.locator('nav a.active, nav .active, aside a.active, [aria-current="page"]').first().innerText().catch(() => '')) || await p.locator('a.active:has-text("Articoli"), .active >> text=Articoli').count() > 0);
  ok('stato: non ancora creato', /non ancora creato/i.test(await p.locator('#fa-stato').innerText()));
  ok('prima di crearlo: niente riquadro sul sito', await (await riquadro(ART1)).count() === 0);
  await foto(p, 'fine-vuota', { fullPage: true });

  // 2. Crea: si apre l'editor
  await Promise.all([p.waitForNavigation(), p.click('#fa-crea')]);
  ok('"Crea il riquadro" apre l\'editor di un modello', /editor\.php\?tipo=modelli&id=\d+/.test(p.url()), p.url());
  const id = +(p.url().match(/id=(\d+)/) || [0, 0])[1];
  await p.waitForFunction(() => { const f = document.querySelector('#ed-iframe'); return f && f.contentDocument && f.contentDocument.querySelector('.ed-sezione'); }, null, { timeout: 30000 });
  const tela = p.frameLocator('#ed-iframe');
  ok('tela: il riquadro sta in fondo a un articolo finto', await tela.locator('article.corpo .ed-segnaposto + aside.fine-articolo #ed-pagina').count() === 1);
  const partenza = await tela.locator('aside.fine-articolo').innerText();
  ok('tela: il testo di partenza (titolo "Prima di andare" e la spiegazione, non il testo vuoto del widget)', partenza.includes('Prima di andare') && partenza.includes('in fondo a ogni articolo') && !partenza.includes('Scrivi qui il testo'), partenza);
  ok('editor: la freccia torna alla pagina del riquadro', (await p.locator('a.ed-indietro').getAttribute('href')) === 'fine-articolo.php');
  ok('editor: "Vedi sul sito" porta sotto un articolo con il riquadro in anteprima', new RegExp('/(' + ART1.replace(/\//g, '') + '|' + ART2.replace(/\//g, '') + ')/\\?preview=1&fine=' + id + '$').test(await p.locator('#ed-vedi').getAttribute('href')), await p.locator('#ed-vedi').getAttribute('href'));
  await foto(p, 'fine-editor', { clip: { x: 0, y: 0, width: 1360, height: 900 } });

  // 3. Bozza: il sito no, l'anteprima della pagina sì
  ok('in bozza: niente riquadro sul sito', await (await riquadro(ART1)).count() === 0);
  await p.goto(B + '/admin/fine-articolo.php', { waitUntil: 'networkidle' });
  ok('stato: spento, con la spiegazione per pubblicarlo', /spento/i.test(await p.locator('#fa-stato').innerText()) && /Pubblica/.test(await p.locator('.fa-nota').innerText()));
  const ant = p.frameLocator('#fa-anteprima');
  await p.locator('#fa-anteprima').scrollIntoViewIfNeeded();
  ok('anteprima nella pagina: l\'articolo con il riquadro in bozza', (await ant.locator('aside.fine-articolo').innerText({ timeout: 15000 })).includes('Prima di andare'));
  await p.click('#fa-accendi'); await p.waitForLoadState('networkidle');
  ok('Accendi prima di pubblicare: rifiutato con la spiegazione', /non è ancora stato pubblicato/.test(await flash()), await flash());

  // 4. Pubblica dall'editor
  await p.goto(B + '/admin/editor.php?tipo=modelli&id=' + id, { waitUntil: 'networkidle' });
  await p.waitForFunction(() => { const f = document.querySelector('#ed-iframe'); return f && f.contentDocument && f.contentDocument.querySelector('.ed-sezione'); }, null, { timeout: 30000 });
  await p.click('#ed-pubblica');
  await p.waitForFunction(() => /Pubblicat/.test(document.querySelector('#ed-stato').textContent), null, { timeout: 20000 });
  for (const u of [ART1, ART2]) {
    const r = await riquadro(u);
    ok(`sito ${u}: il riquadro c'è, una volta`, await r.count() === 1);
    ok(`sito ${u}: dentro la colonna di lettura, dopo il testo`, await sito.locator('article.corpo > aside.fine-articolo:last-child').count() === 1);
    ok(`sito ${u}: con il testo pubblicato`, (await r.innerText()).includes('Prima di andare') && (await r.innerText()).includes('in fondo a ogni articolo'));
  }
  const spazi = await sito.locator('aside.fine-articolo').evaluate(e => { const a = e.getBoundingClientRect(), t = e.querySelector('h1,h2,h3,h4,p').getBoundingClientRect(); return { sopra: Math.round(t.top - a.top), sx: Math.round(t.left - a.left) }; });
  ok('sito: il titolo del riquadro non ha spazio in più sopra (solo quello del riquadro)', Math.abs(spazi.sopra - spazi.sx) <= 4, spazi);
  const stile = await sito.locator('aside.fine-articolo').evaluate(e => { const s = getComputedStyle(e); return { ombra: s.boxShadow, raggio: s.borderTopLeftRadius, padding: s.paddingTop, sfondo: s.backgroundColor }; });
  ok('sito: riquadro ombreggiato, con angoli tondi e spazio dentro', stile.ombra !== 'none' && parseFloat(stile.raggio) > 0 && parseFloat(stile.padding) >= 20, stile);
  await sito.locator('aside.fine-articolo').scrollIntoViewIfNeeded();
  await foto(sito, 'fine-sito', { clip: { x: 0, y: 0, width: 1280, height: 900 } });
  if (FOTO) await sito.locator('aside.fine-articolo').screenshot({ path: path.join(FOTO, 'fine-riquadro.png') });
  ok('sito: la home non ha il riquadro', await (await riquadro('/')).count() === 0);
  if (LARGO) {
    const r = await riquadro(LARGO);
    ok('articolo a tutta larghezza: il riquadro c\'è, una volta, dopo le sezioni', await r.count() === 1 && await sito.locator('.ed-pagina + .fine-articolo-fuori > .corpo > aside.fine-articolo').count() === 1);
    ok('articolo a tutta larghezza: mai dentro una scheda della griglia', await sito.locator('article aside.fine-articolo').count() === 0 && await sito.locator('article.ew-post').count() > 0);
    const l = await r.evaluate(e => { const b = e.getBoundingClientRect(), h = e.querySelector('h1,h2,h3,h4'); return { w: Math.round(b.width), centro: Math.round(b.left + b.width / 2 - innerWidth / 2), h: h ? getComputedStyle(h).fontSize : '' }; });
    ok('articolo a tutta larghezza: largo quanto la lettura, al centro, titolo come nell\'articolo', l.w <= 700 && Math.abs(l.centro) <= 2 && l.h === '24px', l);
    await r.scrollIntoViewIfNeeded();
    await foto(sito, 'fine-largo', { clip: { x: 0, y: 0, width: 1280, height: 900 } });
  }
  // Un pulsante aggiunto sotto il testo (dall'API dell'editor, come fa Pubblica): non attaccato al testo, con i colori che
  // ha in una pagina (non quelli dei link dell'articolo), e niente spazio in più in fondo al riquadro.
  {
    await p.goto(B + '/admin/editor.php?tipo=modelli&id=' + id, { waitUntil: 'networkidle' });
    const r = await p.evaluate(async (id) => {
      const D = JSON.parse(document.querySelector('#ed-dati').textContent);
      const a = JSON.parse(JSON.stringify(D.albero));
      a.elementi[0].figli[0].figli.push({ id: 'pfa0001', tipo: 'pulsanti', impostazioni: {} });
      const fd = new FormData();
      for (const [k, v] of Object.entries({ _csrf: D.csrf, id, tipo: 'modelli', azione: 'salva', albero: JSON.stringify(a), pubblica: 1, nota: '' })) fd.append(k, v);
      const x = await fetch('editor-api.php', { method: 'POST', body: fd, credentials: 'same-origin' });
      return x.status;
    }, id);
    ok('(preparazione) un pulsante aggiunto al riquadro e pubblicato', r === 200, r);
    await riquadro(ART1);
    const m = await sito.evaluate(() => {
      const aside = document.querySelector('aside.fine-articolo'), a = aside && aside.querySelector('.ed-w-pulsanti a');
      if (!a) return null;
      const k = a.closest('.ed-w-pulsanti').cloneNode(true); document.body.append(k);
      const fuori = getComputedStyle(k.querySelector('a')).color; k.remove();
      const t = aside.querySelector('.ed-w-testo').getBoundingClientRect(), b = a.getBoundingClientRect(), s = aside.getBoundingClientRect();
      return { dentro: getComputedStyle(a).color, fuori, sotto: Math.round(b.top - t.bottom), fondo: Math.round(s.bottom - b.bottom), pad: parseFloat(getComputedStyle(aside).paddingBottom) + 1 };
    });
    ok('pulsante nel riquadro: stesso colore del testo che ha in una pagina', m && m.dentro === m.fuori, m);
    ok('pulsante nel riquadro: staccato dal testo, niente spazio in più in fondo', m && m.sotto >= 12 && Math.abs(m.fondo - m.pad) <= 2, m);
    if (FOTO) await sito.locator('aside.fine-articolo').screenshot({ path: path.join(FOTO, 'fine-pulsante.png') });
  }
  // Al telefono
  const tel = await (await br.newContext({ viewport: { width: 390, height: 844 }, isMobile: true })).newPage();
  await tel.goto(B + ART1, { waitUntil: 'networkidle' });
  const mis = await tel.evaluate(() => { const a = document.querySelector('aside.fine-articolo').getBoundingClientRect(); return { sx: a.left, dx: a.right, w: innerWidth, scorre: document.documentElement.scrollWidth > innerWidth }; });
  ok('telefono: il riquadro sta nello schermo, niente scorrimento di lato', mis.sx >= 0 && mis.dx <= mis.w && !mis.scorre, mis);
  await tel.locator('aside.fine-articolo').scrollIntoViewIfNeeded();
  await foto(tel, 'fine-telefono');

  // 5. Spegni, Accendi, versioni, Header e footer
  await p.goto(B + '/admin/fine-articolo.php', { waitUntil: 'networkidle' });
  ok('stato: acceso', /acceso/i.test(await p.locator('#fa-stato').innerText()));
  await foto(p, 'fine-pagina', { fullPage: true });
  await p.click('#fa-spegni'); await p.waitForLoadState('networkidle');
  ok('Spegni: messaggio e stato spento', /spento/i.test(await p.locator('#fa-stato').innerText()) && /non esce più/.test(await flash()));
  ok('spento: niente riquadro sul sito', await (await riquadro(ART1)).count() === 0);
  await p.click('#fa-accendi'); await p.waitForLoadState('networkidle');
  ok('Accendi: di nuovo acceso', /acceso/i.test(await p.locator('#fa-stato').innerText()));
  ok('acceso: il riquadro torna sul sito', await (await riquadro(ART1)).count() === 1);
  await p.goto(B + '/admin/versioni.php?ambito=modello&oggetto=' + id, { waitUntil: 'networkidle' });
  const av = p.locator('a:has-text("Anteprima")').first();
  ok('versioni: il riquadro ha le sue versioni', await av.count() === 1);
  if (await av.count()) {
    const v = await (await ctx.newPage());
    await v.goto(new URL(await av.getAttribute('href'), p.url()).href, { waitUntil: 'networkidle' });
    ok('anteprima di una versione: il riquadro sotto un articolo d\'esempio', await v.locator('article.corpo aside.fine-articolo').count() === 1);
    await v.close();
  }
  await p.goto(B + '/admin/modelli.php', { waitUntil: 'networkidle' });
  const md = await p.content();
  ok('Header e footer: si apre, nessun avviso PHP, il riquadro non è fra gli header', !/Warning|Notice|Fatal error/.test(md) && !/Fine articolo/.test(await p.locator('table').innerText()));
  ok('nessun errore JavaScript nel pannello', err.length === 0, err);
  console.log(`${n - ko} su ${n} OK`);
  await br.close();
  process.exit(ko ? 1 : 0);
})();
