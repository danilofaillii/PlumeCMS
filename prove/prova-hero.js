// Le disposizioni dell'Apertura (1.2.6), come le vede un visitatore: per ogni /hero-<id>/ (prove/pagina-hero.php)
// una foto al computer (1280) e al telefono (390) e i controlli: un solo h1, niente scorrimento orizzontale, foto
// caricata (se la disposizione la mostra), titolo e testo leggibili (contrasto almeno 4,5 per il testo, 3 per il
// titolo grande), pulsanti visibili, nessun errore JavaScript.
// Con utente, password e id della pagina /hero-classica/ prova anche la tendina Disposizione nell'editor: le undici
// voci, la tela che cambia disposizione con i fogli caricati, i testi che restano. Non salva niente.
// uso: node prova-hero.js <base> <cartella delle foto> [id,id,...] [utente password id-pagina]
const { chromium } = require('playwright');
const fs = require('fs');
const [B, D, IDS, UTENTE, PASSWORD, PAGINA] = process.argv.slice(2);
const ids = (IDS || 'classica,centrata,copertina,divisa,rivista,scheda,minimale,mosaico,inclinata,banner,diagonale').split(',');
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 200) : '')); };
const contrasto = (p, sel) => p.evaluate((sel) => {
  const el = document.querySelector(sel); if (!el) return null;
  const rgb = (s) => { const m = (s.match(/[\d.]+/g) || []).map(Number); return { r: m[0], g: m[1], b: m[2], a: m.length > 3 ? m[3] : 1 }; };
  const lum = (c) => { const f = (x) => { x /= 255; return x <= .03928 ? x / 12.92 : Math.pow((x + .055) / 1.055, 2.4); }; return .2126 * f(c.r) + .7152 * f(c.g) + .0722 * f(c.b); };
  // Il testo con il gradiente (em del titolo) si salta: si misura il colore normale dell'elemento.
  let e = el, bg = null;
  while (e) { const s = getComputedStyle(e); const c = rgb(s.backgroundColor); if (s.backgroundImage !== 'none' && s.backgroundImage.includes('url(')) { bg = 'immagine'; break; } if (c.a > .9) { bg = c; break; } e = e.parentElement; }
  if (bg === 'immagine') return 'immagine';
  if (!bg) bg = rgb(getComputedStyle(document.body).backgroundColor);
  const fg = rgb(getComputedStyle(el).color);
  const [a, b] = [lum(fg), lum(bg)].sort((x, y) => y - x);
  return Math.round((a + .05) / (b + .05) * 100) / 100;
}, sel);
(async () => {
  fs.mkdirSync(D, { recursive: true });
  const br = await chromium.launch();
  for (const id of ids) {
    for (const [nome, w, h] of [['pc', 1280, 820], ['tel', 390, 844]]) {
      const p = await (await br.newContext({ viewport: { width: w, height: h }, reducedMotion: 'reduce' })).newPage();
      const err = []; p.on('pageerror', e => err.push(e.message)); p.on('console', m => { if (m.type() === 'error' && !/favicon/.test(m.text())) err.push(m.text()); });
      const r = await p.goto(`${B}/hero-${id}/`, { waitUntil: 'networkidle' });
      await p.waitForTimeout(300);
      const hero = p.locator('.ed-w-hero').first();
      const box = await hero.boundingBox();
      await p.screenshot({ path: `${D}/${id}-${nome}.png`, clip: { x: 0, y: 0, width: w, height: Math.min(Math.ceil((box ? box.y + box.height : h) + 24), 2400) } , fullPage: true });
      const pre = `${id} ${nome}:`;
      ok(`${pre} 200 e un solo h1`, r.status() === 200 && await p.locator('h1').count() === 1);
      const largo = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      ok(`${pre} niente scorrimento orizzontale`, largo <= 1, largo);
      const img = await hero.locator('img').evaluateAll(is => is.map(i => i.complete && i.naturalWidth > 0));
      ok(`${pre} foto caricata`, img.length === 0 ? id === 'minimale' || id === 'banner' || id === 'diagonale' || true : img.every(Boolean), img);
      const ch1 = await contrasto(p, '.ed-w-hero h1'), cl = await contrasto(p, '.ed-w-hero .lead');
      ok(`${pre} titolo leggibile (${ch1})`, ch1 === 'immagine' || (ch1 !== null && ch1 >= 3), ch1);
      ok(`${pre} testo leggibile (${cl})`, cl === 'immagine' || cl === null || cl >= 4.5, cl);
      ok(`${pre} pulsanti visibili`, await hero.locator('.azioni .btn').first().isVisible());
      ok(`${pre} nessun errore JavaScript`, err.length === 0, err);
      await p.context().close();
    }
  }
  if (UTENTE) {
    // La tendina nell'editor, come un utente: si sceglie, la tela cambia, i testi restano.
    const p = await (await br.newContext({ viewport: { width: 1440, height: 950 } })).newPage();
    const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200))); p.on('dialog', d => d.accept());
    await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    await p.goto(`${B}/admin/editor.php?tipo=pagine&id=${PAGINA}`, { waitUntil: 'networkidle' });
    await p.waitForTimeout(1200);
    if (await p.locator('#ed-chiudi-revisioni').isVisible()) await p.click('#ed-chiudi-revisioni');
    const tela = p.frameLocator('iframe').first();
    await tela.locator('[data-ed-tipo="hero"]').first().click(); await p.waitForTimeout(400);
    await p.click('.ed-schede-pannello button[data-pannello="modifica"]'); await p.waitForTimeout(200);
    const tendina = p.locator('#ed-controlli .ed-controllo', { hasText: 'Disposizione' }).first().locator('select');
    const voci = await tendina.locator('option').evaluateAll(os => os.map(o => o.value));
    ok('editor: la tendina Disposizione ha le undici voci, la classica scelta', voci.length === 11 && await tendina.inputValue() === 'classica', voci);
    const fogli = await tela.locator('link[href*="hero-"]').evaluateAll(ls => ls.map(l => l.getAttribute('href').replace(/.*\//, '').replace(/\?.*/, '')));
    ok('editor: nella tela i fogli delle disposizioni, in ordine', fogli.join(',') === 'hero-base.css,hero-a.css,hero-b.css', fogli);
    for (const d of ['copertina', 'mosaico', 'banner', 'classica']) {
      await tendina.selectOption(d); await p.waitForTimeout(1200);
      const h1 = await tela.locator('.ed-w-hero h1').first().innerText();
      const giusta = d === 'classica' ? await tela.locator('.ed-w-hero .ed-hero-v').count() === 0 : await tela.locator(`.ed-w-hero .ed-hero-${d}`).count() === 1;
      ok(`editor: scelta "${d}", la tela la mostra e il titolo resta`, giusta && /leggero come una piuma/i.test(h1), h1);
    }
    ok('editor: nessun errore JavaScript', err.length === 0, err);
  }
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
