// Il widget "Scorri di lato" (2.25.0), come lo vede un visitatore e come lo usa chi fa il sito.
// Prima: php prove/pagina-scorri.php <cartella CMS> <foto> <foto> (le pagine /scorri-<disposizione>/, /scorri-due/, /scorri-editor/).
// uso: node prove/prova-scorri.js <base> <utente> <password> [cartella foto]
// Per ogni disposizione (pellicola, carte, fermo, tempo, profondita), a 1280 e a 390 px, senza tema e con Cosmo, Aria e
// Bottega nell'anteprima (?preview=1&tema=): la scena si ferma in alto, scorre di lato fino all'ultima pagina, alla fine la
// pagina riparte in verticale; la pagina non scorre mai di lato; con Tab si arriva a ogni pulsante delle pagine, che si
// vede e non è coperto; titoli e testi leggibili. Poi: due widget nella stessa pagina, meno movimento e senza JavaScript
// (pagine una sotto l'altra), la tela dell'editor, e il widget creato dal pannello come un utente (pagine aggiunte, riordinate
// e tolte, pubblicato). Con SCHERMATE=<cartella> scrive anche le schermate JPEG del manuale d'uso.
// TEMI=,cosmo (per esempio) prova solo quei temi; DISP=pellicola,fermo solo quelle disposizioni.
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const fs = require('fs');
const [B, UTENTE, PASSWORD, FOTO] = process.argv.slice(2);
const DISP = (process.env.DISP || 'pellicola,carte,fermo,tempo,profondita').split(',');
const TEMI = (process.env.TEMI !== undefined ? process.env.TEMI : ',cosmo,aria,bottega').split(',');
const SCH = process.env.SCHERMATE || '';
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); };
if (FOTO) fs.mkdirSync(FOTO, { recursive: true });
if (SCH) fs.mkdirSync(SCH, { recursive: true });

// Lo stato del widget numero w della pagina: dove sta la scena, quanto è spostato il binario, quali pagine si vedono.
const stato = (p, w = 0) => p.evaluate((w) => {
  const r = document.querySelectorAll('.sdl')[w]; if (!r) return null;
  const sc = r.querySelector('.sdl-scena'), bin = r.querySelector('.sdl-binario');
  const rr = r.getBoundingClientRect(), rs = sc.getBoundingClientRect();
  const alto = parseFloat(getComputedStyle(r).getPropertyValue('--sdl-alto')) || 0;
  const m = new DOMMatrixReadOnly(getComputedStyle(bin).transform === 'none' ? undefined : getComputedStyle(bin).transform);
  const x = r.dataset.sdl === 'fermo' ? parseFloat(getComputedStyle(r).getPropertyValue('--sdl-x')) || 0 : m.m41;
  const pagine = [...r.querySelectorAll('.sdl-pagina')];
  const vis = (el) => { const q = el.getBoundingClientRect(); return q.width > 0 && q.left >= rs.left - 2 && q.right <= rs.right + 2; };
  const ultima = r.dataset.sdl === 'fermo' ? pagine[pagine.length - 1].querySelector('.sdl-finestra') : pagine[pagine.length - 1];
  return { vivo: r.classList.contains('sdl-vivo'), pos: getComputedStyle(sc).position, alto, top: Math.round(rs.top), x: Math.round(x), rTop: rr.top + scrollY,
    rH: r.offsetHeight, sH: sc.offsetHeight, ultimaVista: vis(ultima), primaVista: vis(r.dataset.sdl === 'fermo' ? pagine[0].querySelector('.sdl-finestra') : pagine[0]),
    on: pagine.findIndex(pg => pg.classList.contains('sdl-on')), larghezza: Math.round(rr.width), sinistra: Math.round(rr.left), cw: document.documentElement.clientWidth,
    contatore: (r.querySelector('.sdl-contatore b') || {}).textContent || '' };
}, w);
const largo = (p) => p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
const vaiA = async (p, y) => { await p.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), y); await p.waitForTimeout(220); };
const contrasto = (p, el) => p.evaluate((el) => {
  if (!el) return null;
  const rgb = (s) => { const m = (s.match(/[\d.]+/g) || []).map(Number); return { r: m[0], g: m[1], b: m[2], a: m.length > 3 ? m[3] : 1 }; };
  const lum = (c) => { const f = (x) => { x /= 255; return x <= .03928 ? x / 12.92 : Math.pow((x + .055) / 1.055, 2.4); }; return .2126 * f(c.r) + .7152 * f(c.g) + .0722 * f(c.b); };
  let e = el, bg = null;
  while (e) { const s = getComputedStyle(e); const c = rgb(s.backgroundColor); if (c.a > .9) { bg = c; break; } e = e.parentElement; }
  if (!bg) bg = rgb(getComputedStyle(document.body).backgroundColor);
  const fg = rgb(getComputedStyle(el).color);
  const [a, b] = [lum(fg), lum(bg)].sort((x, y) => y - x);
  return Math.round((a + .05) / (b + .05) * 100) / 100;
}, el);

(async () => {
  const br = await chromium.launch();
  const c0 = await br.newContext(); const q = await c0.newPage();
  await q.goto(B + '/admin/login.php'); await q.fill('#username', UTENTE); await q.fill('#password', PASSWORD);
  await Promise.all([q.waitForNavigation(), q.click('button[type=submit]')]);
  const sessione = await c0.storageState(); await c0.close();
  const errori = (p) => { const e = []; p.on('pageerror', x => e.push(x.message.slice(0, 200))); p.on('console', m => { if (m.type() === 'error' && !/favicon/.test(m.text())) e.push(m.text().slice(0, 200)); }); return e; };

  // 1. Le cinque disposizioni, al computer e al telefono, nei temi.
  for (const tema of TEMI) for (const d of DISP) for (const [nome, W, H] of [['pc', 1280, 820], ['tel', 390, 844]]) {
    const ctx = await br.newContext({ viewport: { width: W, height: H }, storageState: tema ? sessione : undefined, hasTouch: nome === 'tel', isMobile: nome === 'tel' });
    const p = await ctx.newPage(); const err = errori(p);
    const pre = `${tema || 'base'} ${d} ${nome}:`;
    const r = await p.goto(`${B}/scorri-${d}/` + (tema ? `?preview=1&tema=${tema}` : ''), { waitUntil: 'networkidle' });
    await p.waitForTimeout(300);
    let s = await stato(p);
    ok(`${pre} 200, la scena è viva e larga quanto la finestra, dal bordo sinistro`, r.status() === 200 && s && s.vivo && s.pos === 'sticky' && Math.abs(s.larghezza - s.cw) <= 1 && Math.abs(s.sinistra) <= 1, s && { vivo: s.vivo, pos: s.pos, l: s.larghezza, sx: s.sinistra, cw: s.cw });
    const tot = s.rH - s.sH, y0 = Math.ceil(s.rTop - s.alto);
    if (process.env.DEBUG) console.log("DBG", JSON.stringify(s), tot, y0);
    ok(`${pre} la radice è più alta della scena (c'è strada da fare di lato)`, tot > 100, { tot });
    await vaiA(p, y0 - 200); s = await stato(p);
    ok(`${pre} prima del widget: niente spostamento, prima pagina in vista`, s.x === 0 && s.primaVista, { x: s.x });
    await vaiA(p, y0 + Math.round(tot / 2)); s = await stato(p);
    ok(`${pre} a metà: la scena è ferma in alto e le pagine sono andate di lato`, Math.abs(s.top - s.alto) <= 2 && s.x < -20 && s.on >= 1, { top: s.top, alto: s.alto, x: s.x, on: s.on });
    ok(`${pre} a metà: la pagina non scorre di lato`, await largo(p) <= 1, await largo(p));
    if (FOTO) await p.screenshot({ path: `${FOTO}/${tema || 'base'}-${d}-${nome}-meta.png` });
    await vaiA(p, y0 + tot); s = await stato(p);
    ok(`${pre} alla fine: scena ancora ferma, l'ultima pagina in vista`, Math.abs(s.top - s.alto) <= 2 && s.ultimaVista && s.on === 3 && (d !== 'fermo' || s.contatore === '4'), { top: s.top, x: s.x, ultima: s.ultimaVista, on: s.on, cont: s.contatore });
    if (FOTO) await p.screenshot({ path: `${FOTO}/${tema || 'base'}-${d}-${nome}-fine.png` });
    if (d === 'carte' || d === 'profondita') {
      const cop = await p.evaluate(() => { const pg = [...document.querySelector('.sdl').querySelectorAll('.sdl-pagina')].pop(), a = pg.getBoundingClientRect(), b = pg.querySelector('.sdl-finestra').getBoundingClientRect(); return b.left <= a.left + 1 && b.right >= a.right - 1 && b.top <= a.top + 1 && b.bottom >= a.bottom - 1; });
      ok(`${pre} alla fine: l'immagine (o il disegno) fa da sfondo a tutta l'ultima pagina`, cop);
    }
    await vaiA(p, y0 + tot + 300); s = await stato(p);
    const dopo = await p.evaluate(() => [...document.querySelectorAll('h2')].find(h => /Dopo il widget/.test(h.textContent)).getBoundingClientRect().top);
    ok(`${pre} dopo l'ultima pagina la pagina riparte in verticale (la scena sale, arriva il testo dopo)`, s.top < s.alto - 250 && dopo < 820, { top: s.top, dopo });
    ok(`${pre} dopo: la pagina non scorre di lato`, await largo(p) <= 1, await largo(p));
    // Titolo e testo della seconda pagina (senza foto) leggibili.
    await vaiA(p, y0); await p.evaluate(() => { const r = document.querySelector('.sdl'); r.querySelectorAll('.sdl-pagina')[1].querySelector('a,h2,h3').scrollIntoView; });
    {
      const hT = await p.$('.sdl .sdl-p2 .sdl-titolo'), hL = await p.$('.sdl .sdl-p2 .sdl-lead p');
      const ct = await contrasto(p, hT), cl = await contrasto(p, hL);
      ok(`${pre} titolo (${ct}) e testo (${cl}) della pagina senza foto leggibili`, ct >= 3 && cl >= 4.5, { ct, cl });
    }
    // Tastiera: dal link prima del widget, Tab fino all'ultimo pulsante delle pagine; ognuno si vede e non è coperto.
    await vaiA(p, 0);
    await p.locator('.ed-sezione a', { hasText: 'Scrivici' }).first().focus();
    const visti = [];
    for (let i = 0; i < 8; i++) {
      await p.keyboard.press('Tab'); await p.waitForTimeout(650);
      const f = await p.evaluate(() => {
        const a = document.activeElement; if (!a || !a.closest('.sdl')) return null;
        const q = a.getBoundingClientRect(), sc = a.closest('.sdl').querySelector('.sdl-scena').getBoundingClientRect();
        const x = q.left + q.width / 2, y = q.top + q.height / 2, sopra = document.elementFromPoint(x, y);
        let op = 1; for (let e = a; e; e = e.parentElement) op *= parseFloat(getComputedStyle(e).opacity);
        const coperti = [...document.querySelectorAll('.sdl-lineette, .sdl-contatore')].some(c => { const k = c.getBoundingClientRect(); return k.width && !(k.right < q.left || k.left > q.right || k.bottom < q.top || k.top > q.bottom); });
        return { testo: a.textContent.trim(), dentro: q.left >= Math.max(0, sc.left) - 1 && q.right <= Math.min(innerWidth, sc.right) + 1 && q.top >= 0 && q.bottom <= innerHeight, sopra: !!sopra && (sopra === a || a.contains(sopra)), op: Math.round(op * 100) / 100, coperti };
      });
      if (!f) break;
      visti.push(f);
    }
    const attesi = ['Scarica plumeCMS', 'Guarda l\'editor', 'Leggi la storia', 'Comincia adesso', 'Guarda cosa sa fare'];
    ok(`${pre} con Tab si arriva ai cinque pulsanti delle pagine, in ordine`, JSON.stringify(visti.map(v => v.testo)) === JSON.stringify(attesi), visti.map(v => v.testo));
    const male = visti.filter(v => !(v.dentro && v.sopra && v.op > 0.95 && !v.coperti));
    ok(`${pre} ogni pulsante col fuoco è in vista, sopra a tutto e non coperto`, visti.length === 5 && male.length === 0, male);
    ok(`${pre} il link scritto a mano "storia" diventa /storia`, await p.locator('.sdl a', { hasText: 'Leggi la storia' }).getAttribute('href').then(h => /\/storia$/.test(h)));
    ok(`${pre} nessun errore JavaScript`, err.length === 0, err);
    if (SCH && !tema && nome === 'pc') { await vaiA(p, y0 + Math.round(tot / 3)); await p.screenshot({ path: `${SCH}/08-scorri-${d}.jpg`, type: 'jpeg', quality: 55 }); }
    await ctx.close();
  }

  // 2. Due widget nella stessa pagina: ognuno si ferma e scorre per conto suo.
  {
    const p = await (await br.newContext({ viewport: { width: 1280, height: 820 } })).newPage(); const err = errori(p);
    await p.goto(B + '/scorri-due/', { waitUntil: 'networkidle' }); await p.waitForTimeout(300);
    let a = await stato(p, 0), b = await stato(p, 1);
    ok('due widget: tutti e due vivi', a.vivo && b.vivo);
    await vaiA(p, Math.ceil(a.rTop - a.alto + (a.rH - a.sH) / 2)); a = await stato(p, 0); b = await stato(p, 1);
    ok('due widget: a metà del primo il primo scorre, il secondo è fermo all\'inizio', a.x < -100 && Math.abs(a.top - a.alto) <= 2 && b.x === 0, { a: a.x, b: b.x });
    await vaiA(p, Math.ceil(b.rTop - b.alto + (b.rH - b.sH))); a = await stato(p, 0); b = await stato(p, 1);
    ok('due widget: alla fine del secondo il secondo è all\'ultima carta, il primo è finito e sopra lo schermo', b.ultimaVista && Math.abs(b.top - b.alto) <= 2 && a.top < -400 && a.ultimaVista, { a: a.top, b: b.top });
    ok('due widget: la pagina non scorre di lato', await largo(p) <= 1);
    ok('due widget: nessun errore JavaScript', err.length === 0, err);
    await p.context().close();
  }

  // 3. Meno movimento e senza JavaScript: le pagine una sotto l'altra, niente scena ferma.
  for (const [nome, opz] of [['meno movimento', { reducedMotion: 'reduce' }], ['senza JavaScript', { javaScriptEnabled: false }]]) for (const d of DISP) for (const W of [1280, 390]) {
    const p = await (await br.newContext({ viewport: { width: W, height: 820 }, ...opz })).newPage();
    await p.goto(`${B}/scorri-${d}/`, { waitUntil: 'networkidle' }); await p.waitForTimeout(200);
    const e = await p.evaluate(() => {
      const r = document.querySelector('.sdl'), sc = r.querySelector('.sdl-scena');
      const pg = [...r.querySelectorAll('.sdl-pagina')].map(x => x.getBoundingClientRect());
      const titoli = [...r.querySelectorAll('.sdl-titolo')].map(t => { const q = t.getBoundingClientRect(); let op = 1; for (let x = t; x; x = x.parentElement) op *= parseFloat(getComputedStyle(x).opacity); return q.width > 0 && q.height > 0 && op > 0.95; });
      const sotto = pg.every((q, i) => i === 0 || q.top >= pg[i - 1].bottom - 1 || (q.top >= pg[i - 1].top && q.left > pg[i - 1].left)); // carte a griglia: accanto o sotto
      return { vivo: r.classList.contains('sdl-vivo'), pos: getComputedStyle(sc).position, sotto, titoli, n: pg.length };
    });
    ok(`${nome} ${d} ${W}: niente scena ferma, quattro pagine una dopo l'altra, tutti i titoli si vedono`, !e.vivo && e.pos !== 'sticky' && e.sotto && e.n === 4 && e.titoli.every(Boolean), e);
    ok(`${nome} ${d} ${W}: la pagina non scorre di lato`, await largo(p) <= 1, await largo(p));
    await p.context().close();
  }

  // 4. L'editor: la tela mostra le pagine in fila (niente scena ferma); il widget si crea dal pannello come un utente.
  {
    const p = await (await br.newContext({ viewport: { width: 1440, height: 950 }, storageState: sessione })).newPage(); const err = errori(p);
    pnConferme.on(p, d => d.accept());
    const idDi = async (slug) => { await p.goto(B + '/admin/contenuti.php?tipo=pagine', { waitUntil: 'networkidle' }); return p.evaluate((slug) => { const a = [...document.querySelectorAll('a[href*="editor.php"]')].find(x => /id=\d+/.test(x.href) && x.closest('tr') && x.closest('tr').innerText.includes('/' + slug + '/')); return a ? new URL(a.href).searchParams.get('id') : null; }, slug); };
    const apri = async (id) => { await p.goto(`${B}/admin/editor.php?tipo=pagine&id=${id}`, { waitUntil: 'networkidle' }); await p.waitForTimeout(1300); if (await p.locator('#ed-chiudi-revisioni').isVisible()) await p.click('#ed-chiudi-revisioni'); };
    const tela = p.frameLocator('#ed-iframe');
    const idT = await idDi('scorri-tempo');
    await apri(idT);
    const t = await tela.locator('.sdl').first().evaluate(r => ({ tela: r.classList.contains('sdl-tela'), vivo: r.classList.contains('sdl-vivo'), pos: getComputedStyle(r.querySelector('.sdl-scena')).position,
      ox: getComputedStyle(r.querySelector('.sdl-binario')).overflowX, pagine: [...r.querySelectorAll('.sdl-pagina')].map(x => x.getBoundingClientRect().width > 100), nota: !!document.querySelector('.sdl-nota-tela') }));
    ok('editor: nella tela le pagine in una fila che si scorre a mano, niente scena ferma, la nota che lo dice', t.tela && !t.vivo && t.pos !== 'sticky' && t.ox === 'auto' && t.pagine.length === 4 && t.pagine.every(Boolean) && t.nota, t);
    // Creato dal pannello: la pagina /scorri-editor/ ha solo un testo.
    const idE = await idDi('scorri-editor');
    await apri(idE);
    await p.click('.ed-schede-pannello button[data-pannello=widget]');
    await p.fill('#ed-cerca-widget', 'scorri'); await p.waitForTimeout(200);
    const voce = p.locator('.ed-widget-voce[data-tipo="scorri_lato"]');
    ok('editor: "Scorri di lato" è nella tavolozza, nel gruppo Contenuto', await voce.isVisible() && /Contenuto/.test(await voce.evaluate(v => v.closest('.ed-gruppo-widget').querySelector('h4').textContent)));
    await voce.click(); await p.waitForTimeout(900);
    ok('editor: con un clic il widget entra, si apre Modifica', /Scorri di lato/.test(await p.locator('#ed-modifica-nome').innerText()));
    const tendina = p.locator('#ed-controlli .ed-controllo', { hasText: 'Disposizione' }).first().locator('select');
    const voci = await tendina.locator('option').evaluateAll(os => os.map(o => o.value));
    ok('editor: la tendina Disposizione ha le cinque voci, Pellicola scelta', voci.join() === 'pellicola,carte,fermo,tempo,profondita' && await tendina.inputValue() === 'pellicola', voci);
    const titoloSez = () => p.locator('#ed-controlli .ed-controllo', { hasText: 'Titolo della sezione' }).count();
    ok('editor: con la Pellicola il titolo fermo della sezione non c\'è', await titoloSez() === 0);
    const pagineCtl = p.locator('#ed-controlli .ed-ripetitore').first();
    const nomiVoci = () => pagineCtl.locator('.ed-voce-testa b').allInnerTexts();
    ok('editor: quattro pagine d\'esempio, col testo del sito di plumeCMS', JSON.stringify(await nomiVoci()) === JSON.stringify(['Il tuo sito, leggero come una piuma.', 'Sezioni, colonne, widget.', 'Undici temi, un clic.', 'Premi Pubblica. Fatto.']), await nomiVoci());
    // Aggiungi una pagina e riempila.
    await pagineCtl.locator(':scope > .ed-b', { hasText: 'Aggiungi' }).click(); await p.waitForTimeout(300);
    const aperta = pagineCtl.locator('.ed-voce').nth(4).locator('.ed-voce-corpo');
    await aperta.locator('.ed-controllo', { hasText: 'Titolo' }).first().locator('input').fill('Una pagina *nuova*.');
    await aperta.locator('.ed-controllo', { hasText: 'Primo pulsante' }).first().locator('input').fill('Prova adesso');
    await aperta.locator('.ed-controllo', { hasText: 'Link del primo pulsante' }).first().locator('input').fill('/prova/');
    ok('editor: nella pagina aperta la data non c\'è (serve solo alla Linea del tempo)', await aperta.locator('.ed-controllo', { hasText: 'Data della tappa' }).count() === 0);
    ok('editor: il link del pulsante ha "Scegli dal sito"', await aperta.locator('.ed-controllo', { hasText: 'Link del primo pulsante' }).first().locator('button', { hasText: /Scegli dal sito/ }).count() === 1);
    ok('editor: l\'immagine della pagina ha "Scegli"', await aperta.locator('.ed-controllo', { hasText: 'Immagine' }).first().locator('button').count() >= 1);
    await tela.locator('.sdl').last().locator('.sdl-p5 .sdl-titolo').waitFor({ timeout: 8000 }).catch(() => {}); await p.waitForTimeout(300);
    ok('editor: la tela mostra la pagina nuova (cinque pagine)', await tela.locator('.sdl').last().locator('.sdl-pagina').count() === 5 && /nuova/i.test(await tela.locator('.sdl').last().locator('.sdl-p5 .sdl-titolo').innerText()));
    // Riordina: la nuova sale di due posti con Sposta su.
    for (let i = 0; i < 2; i++) { const k = (await nomiVoci()).indexOf('Una pagina nuova.'); await pagineCtl.locator('.ed-voce').nth(k).locator('[data-az=su]').click(); await p.waitForTimeout(250); }
    ok('editor: con Sposta su la pagina nuova è la terza', (await nomiVoci())[2] === 'Una pagina nuova.', await nomiVoci());
    ok('editor: Sposta su della prima e Sposta giù dell\'ultima sono spenti', await pagineCtl.locator('.ed-voce').first().locator('[data-az=su]').isDisabled() && await pagineCtl.locator('.ed-voce').last().locator('[data-az=giu]').isDisabled());
    // Trascinamento con la maniglia: l'ultima in cima.
    const man = pagineCtl.locator('.ed-voce-maniglia');
    {
      // La pagina aperta si chiude (con le caselle aperte l'elenco è più alto dello schermo), poi si trascina.
      const ap = pagineCtl.locator('.ed-voce-corpo:not([hidden])');
      if (await ap.count()) { await ap.first().locator('xpath=..').locator('.ed-voce-testa b').click(); await p.waitForTimeout(300); }
      await pagineCtl.scrollIntoViewIfNeeded();
      const p0 = await man.last().boundingBox(), p1 = await pagineCtl.locator('.ed-voce').first().boundingBox();
      await p.mouse.move(p0.x + p0.width / 2, p0.y + p0.height / 2); await p.mouse.down();
      await p.mouse.move(p0.x + p0.width / 2, p0.y + p0.height / 2 - 6, { steps: 3 });
      await p.mouse.move(p1.x + p1.width / 2, p1.y + 4, { steps: 20 }); await p.waitForTimeout(200);
      await p.mouse.move(p1.x + p1.width / 2, p1.y + 2, { steps: 2 }); await p.mouse.up(); await p.waitForTimeout(500);
    }
    ok('editor: trascinando la maniglia l\'ultima pagina va in cima', (await nomiVoci())[0] === 'Premi Pubblica. Fatto.', await nomiVoci());
    // Togli: la seconda.
    await pagineCtl.locator('.ed-voce').nth(1).locator('[data-az=elimina]').click(); await p.waitForTimeout(300);
    ok('editor: Elimina toglie una pagina (restano quattro)', (await nomiVoci()).length === 4, await nomiVoci());
    // La Linea del tempo: compare il titolo della sezione e, nelle pagine, la data.
    await tendina.selectOption('tempo'); await p.waitForTimeout(900);
    ok('editor: con la Linea del tempo compaiono occhiello e titolo della sezione', await titoloSez() === 1);
    const p2 = p.locator('#ed-controlli .ed-ripetitore').first();
    await p2.locator('.ed-voce-testa b').first().click(); await p.waitForTimeout(300);
    await p2.locator('.ed-voce').first().locator('.ed-controllo', { hasText: 'Data della tappa' }).locator('input').fill('Marzo 2026');
    ok('editor: nella pagina aperta ora c\'è la data della tappa', await p2.locator('.ed-voce').first().locator('.ed-controllo', { hasText: 'Data della tappa' }).count() === 1);
    // Il massimo: fino a 12, poi + Aggiungi si spegne.
    for (let i = 0; i < 8; i++) { await p2.locator(':scope > .ed-b', { hasText: 'Aggiungi' }).click(); await p.waitForTimeout(120); }
    ok('editor: con dodici pagine + Aggiungi si spegne e dice perché', (await p2.locator('.ed-voce').count()) === 12 && await p2.locator(':scope > .ed-b', { hasText: 'Aggiungi' }).isDisabled() && /massimo 12/.test(await p2.locator(':scope > .ed-b', { hasText: 'Aggiungi' }).getAttribute('title')));
    for (let i = 0; i < 8; i++) { await p2.locator('.ed-voce').last().locator('[data-az=elimina]').click(); await p.waitForTimeout(100); }
    await p.waitForTimeout(800);
    if (SCH) { await p.screenshot({ path: `${SCH}/08-scorri-editor.jpg`, type: 'jpeg', quality: 55 }); }
    await p.click('#ed-pubblica'); await p.waitForTimeout(1800);
    ok('editor: Pubblica', /Pubblicata/.test(await p.locator('#ed-stato').innerText()), await p.locator('#ed-stato').innerText());
    const s = await p.context().newPage();
    await s.setViewportSize({ width: 1280, height: 820 });
    await s.goto(B + '/scorri-editor/', { waitUntil: 'networkidle' }); await s.waitForTimeout(300);
    const sul = await s.evaluate(() => { const r = document.querySelector('.sdl'); return r && { d: r.dataset.sdl, vivo: r.classList.contains('sdl-vivo'), titoli: [...r.querySelectorAll('.sdl-titolo')].map(t => t.textContent), data: (r.querySelector('.sdl-data') || {}).textContent, link: [...r.querySelectorAll('.sdl-btn')].map(a => a.getAttribute('href')) }; });
    ok('sito: la pagina pubblicata ha il widget con le pagine nell\'ordine scelto, la data e il link', !!sul && sul.d === 'tempo' && sul.vivo && sul.titoli.length === 4 && JSON.stringify(sul.titoli) === JSON.stringify(['Premi Pubblica. Fatto.', 'Sezioni, colonne, widget.', 'Una pagina nuova.', 'Undici temi, un clic.']) && sul.data === 'Marzo 2026' && sul.link.some(l => /\/prova\/$/.test(l)), sul);
    ok('editor e sito: nessun errore JavaScript', err.length === 0, err);
    await p.context().close();
  }
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
