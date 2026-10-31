// Sezioni pronte (1.3.0) come un utente. Nell'editor: la linguetta Sezioni, le categorie con almeno tre varianti e
// l'anteprima, la ricerca, il clic che mette la sezione sotto quella selezionata, il trascinamento sulla tela (prima
// della prima sezione), la sezione inserita che si modifica come le altre, Pubblica e la pagina sul sito.
// Sul sito: /sezioni-pronte/ (prove/pagina-sezioni.php) al computer e al telefono, senza scorrimento orizzontale,
// senza errori, con il contrasto del testo sopra 4,5 a 1 (come prova-temi-partenza.js). Lascia schermate in <cartella>.
// uso: node prova-sezioni.js <base> <utente> <password> <cartella schermate>
const { chromium } = require('playwright');
const [B, U, P, OUT] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 220) : '')); };
const CATEGORIE = ['apertura', 'servizi', 'chi', 'testimonianze', 'prezzi', 'faq', 'contatti', 'invito'];
(async () => {
  const br = await chromium.launch(); const p = await br.newPage({ viewport: { width: 1440, height: 1000 } });
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200))); p.on('console', m => { if (m.type() === 'error' && !/favicon/.test(m.location().url || '')) err.push(m.text().slice(0, 200)); });
  await p.goto(B + '/admin/login.php'); await p.fill('#username', U); await p.fill('#password', P);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/editor.php?nuova=1&tipo=pagine', { waitUntil: 'networkidle' }); await p.waitForTimeout(1200);
  const idPag = new URL(p.url()).searchParams.get('id');
  const fr = () => p.frames().find(f => f.url().includes('anteprima'));
  const sezioni = async () => fr().$$eval('#ed-pagina > .ed-sezione', s => s.map(x => x.dataset.edId));
  ok('editor: la linguetta Sezioni c\'è', await p.locator('button[data-pannello=sezioni]').count() === 1);
  await p.click('button[data-pannello=sezioni]'); await p.waitForTimeout(200);
  const perCat = await p.$$eval('.ed-gruppo-sezioni', g => g.map(x => [x.dataset.categoria, x.querySelectorAll('.ed-sezione-voce').length]));
  const mappa = Object.fromEntries(perCat);
  ok('sezioni: le sette categorie richieste e l\'apertura, ognuna con almeno tre varianti', CATEGORIE.every(c => (mappa[c] || 0) >= 3), perCat);
  const voci = await p.locator('.ed-sezione-voce').count();
  ok('sezioni: ogni variante ha l\'anteprima, il nome e la descrizione', await p.locator('.ed-sezione-voce .ed-mini .ed-mini-sez').count() >= voci && await p.locator('.ed-sezione-voce b').count() === voci && await p.locator('.ed-sezione-voce small').count() === voci, voci);
  await p.screenshot({ path: `${OUT}/sezioni-pannello.png` });
  await p.fill('#ed-cerca-sezioni', 'prezz');
  const visibili = await p.locator('.ed-sezione-voce:visible').count();
  ok('ricerca: "prezz" lascia solo le varianti dei prezzi', visibili >= 3 && visibili < voci && await p.locator('.ed-gruppo-sezioni:visible').count() === 1, visibili);
  await p.fill('#ed-cerca-sezioni', '');
  // Clic: la pagina nuova ha una sezione (titolo e testo); selezionata, la variante entra subito sotto.
  const prima = await sezioni();
  await fr().click('#ed-pagina > .ed-sezione:first-child', { position: { x: 5, y: 5 } }); await p.waitForTimeout(300);
  await p.click('button[data-pannello=sezioni]');
  await p.click('.ed-sezione-voce[data-id=prezzi-tre-piani]'); await p.waitForTimeout(1500);
  const dopo = await sezioni();
  ok('clic su "Tre piani": due sezioni nuove, subito sotto la prima', dopo.length === prima.length + 2 && dopo[0] === prima[0] && await fr().locator('.ed-w-tabella_prezzi').count() === 3, { prima: prima.length, dopo: dopo.length });
  ok('dopo il clic si apre la scheda Modifica della sezione nuova', await p.locator('#ed-pannello-modifica').isVisible() && /Sezione/.test(await p.locator('#ed-modifica-nome').innerText()));
  // Trascinamento: dragstart sul pannello porta l'id; il drop sulla tela, nella metà alta della prima sezione, la mette in cima.
  await p.click('button[data-pannello=sezioni]');
  const dato = await p.evaluate(() => { const dt = new DataTransfer(); document.querySelector('.ed-sezione-voce[data-id=invito-fascia]').dispatchEvent(new DragEvent('dragstart', { dataTransfer: dt, bubbles: true })); return dt.getData('text/ed-sezione'); });
  ok('trascinamento: la voce porta il suo id', dato === 'invito-fascia', dato);
  await fr().evaluate(() => {
    const s = document.querySelector('#ed-pagina > .ed-sezione'); const r = s.getBoundingClientRect();
    const dt = new DataTransfer(); dt.setData('text/ed-sezione', 'invito-fascia');
    const o = { dataTransfer: dt, bubbles: true, cancelable: true, clientX: r.left + 40, clientY: r.top + 8 };
    s.dispatchEvent(new DragEvent('dragover', o)); s.dispatchEvent(new DragEvent('drop', o));
  });
  await p.waitForTimeout(1500);
  const dopoDrop = await sezioni();
  ok('trascinamento: "Fascia di colore" posata in cima alla pagina', dopoDrop.length === dopo.length + 1 && dopoDrop[1] === prima[0] && await fr().locator('#ed-pagina > .ed-sezione:first-child .ed-sfondo-gradiente, #ed-pagina > .ed-sezione.ed-sfondo-gradiente:first-child').count() === 1, dopoDrop.length);
  // La sezione inserita si modifica come le altre: il titolo della fascia diventa un altro.
  await fr().click('#ed-pagina > .ed-sezione:first-child .ed-w-titolo'); await p.waitForTimeout(300);
  await p.click('button[data-pannello=modifica]');
  const caselle = p.locator('#ed-controlli input');
  let fatto = false;
  for (let i = 0; i < await caselle.count(); i++) { if (await caselle.nth(i).inputValue() === 'Pronto a cominciare?') { await caselle.nth(i).fill('Una fascia tutta mia'); fatto = true; break; } }
  await p.waitForTimeout(1500);
  ok('la sezione inserita si modifica: il titolo cambia nella tela', fatto && (await fr().locator('#ed-pagina').innerText()).includes('Una fascia tutta mia'));
  await p.screenshot({ path: `${OUT}/sezioni-editor.png` });
  p.once('dialog', d => d.accept()); await p.click('#ed-pubblica'); await p.waitForTimeout(2000);
  const slug = (await p.locator('#ed-percorso').innerText()).trim();
  // ?preview=1: di notte (fra le 00 e le 06) una pagina pubblicata adesso esce alle 06:00 di oggi e fino ad allora
  // il visitatore vede 404; l'anteprima dell'amministratore la mostra subito (difetto noto, non delle sezioni pronte).
  const notte = +new Date().toLocaleString('en-GB', { timeZone: 'Europe/Rome', hour: '2-digit', hour12: false }) < 6;
  const pg = await p.goto(B + slug + (notte ? '?preview=1' : ''), { waitUntil: 'networkidle' });
  const testo = await p.locator('main, body').first().innerText();
  ok('pubblicata: la pagina sul sito ha la fascia cambiata e i tre piani', pg.status() === 200 && testo.includes('Una fascia tutta mia') && await p.locator('.ed-w-tabella_prezzi').count() === 3, pg.status());
  // Sul sito: tutte le varianti, al computer e al telefono.
  for (const [m, w, h] of [['pc', 1280, 900], ['tel', 390, 844]]) {
    await p.setViewportSize({ width: w, height: h });
    const r = await p.goto(B + '/sezioni-pronte/', { waitUntil: 'networkidle' });
    await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 30)); } window.scrollTo(0, 0); });
    await p.waitForTimeout(800);
    const d = await p.evaluate(() => {
      const lum = c => { const m = c.match(/[\d.]+/g); if (!m) return null; const [r, g, b] = m.slice(0, 3).map(x => { x = +x / 255; return x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4); }); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
      const sfondo = el => { for (let e = el; e; e = e.parentElement) { const s = getComputedStyle(e); if (s.backgroundImage !== 'none') return null; const m = s.backgroundColor.match(/[\d.]+/g); if (m && (m.length < 4 || +m[3] > 0.9)) return s.backgroundColor; } return getComputedStyle(document.body).backgroundColor; };
      const bassi = [];
      document.querySelectorAll('#contenuto p, #contenuto h2, #contenuto h3, #contenuto li, .ed-sezione p, .ed-sezione h2, .ed-sezione h3, .ed-sezione li').forEach(el => {
        if (!el.offsetParent || !el.textContent.trim() || el.closest('header, footer, .ed-modello')) return; const s = getComputedStyle(el); const bg = sfondo(el); if (!bg || s.backgroundClip === 'text' || s.webkitTextFillColor === 'rgba(0, 0, 0, 0)') return;
        const a = lum(s.color), b = lum(bg); if (a === null || b === null) return; const c = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
        if (c < 4.5) bassi.push(el.textContent.trim().slice(0, 30) + ' ' + c.toFixed(2));
      });
      return { largo: document.documentElement.scrollWidth - window.innerWidth, sezioni: document.querySelectorAll('.ed-sezione').length, bassi };
    });
    ok(`/sezioni-pronte/ ${m}: 200, tutte le sezioni, niente scorrimento orizzontale`, r.status() === 200 && d.sezioni >= 30 && d.largo <= 1, { stato: r.status(), sezioni: d.sezioni, largo: d.largo });
    ok(`/sezioni-pronte/ ${m}: contrasto del testo almeno 4,5 a 1`, d.bassi.length === 0, d.bassi.slice(0, 6));
    await p.screenshot({ path: `${OUT}/sezioni-sito-${m}.png`, fullPage: true });
  }
  ok('nessun errore JavaScript', err.length === 0, err.slice(0, 5));
  console.log('pagina di prova creata: id ' + idPag + ' (' + slug + ')');
  console.log(`\n${n - ko} su ${n} OK`); await br.close(); process.exit(ko ? 1 : 0);
})().catch(e => { console.log('ECCEZIONE', e.message.slice(0, 500)); process.exit(1); });
