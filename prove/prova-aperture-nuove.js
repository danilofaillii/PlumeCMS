// 2.17.0: i campi delle quindici aperture nuove, come li vede e li usa una persona. Sul sito: le righe del terminale
// (scritte una alla volta, ferme con meno movimento), la parola traforata con il titolo intero nell'h1, il video che parte
// al clic (e niente play senza video), le etichette sulla foto, la lente sul punto scelto, la costellazione, la maniglia
// del prima e dopo con la tastiera e con il mouse, niente scorrimento di lato. Nell'editor: i campi nuovi compaiono solo con
// la loro disposizione, la tela cambia (fogli e script delle disposizioni caricati anche lì), la finestra Scegli in modo
// video. Non salva niente.
// Prima: bin/pagine (prove/pagina-hero.php con VIDEO=<url del video> e SUFFISSO=-nf).
// uso: node prove/prova-aperture-nuove.js <base> <utente> <password> <id della pagina /hero-terminale/>
const { chromium } = require('playwright');
const [B, UTENTE, PASSWORD, PAGINA] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 220) : '')); };

(async () => {
  const br = await chromium.launch();
  const err = [];
  const nuova = async (w = 1280, h = 860, movimento = 'no-preference') => {
    const p = await (await br.newContext({ viewport: { width: w, height: h }, reducedMotion: movimento })).newPage();
    p.on('pageerror', e => err.push(e.message.slice(0, 160)));
    return p;
  };

  // Terminale
  let p = await nuova();
  await p.goto(B + '/hero-terminale/', { waitUntil: 'networkidle' });
  const righe = await p.locator('.ed-w-hero .ed-hte-riga').allInnerTexts();
  ok('terminale: quattro righe, il testo è quello scritto (comando con $, fatto con la spunta)', righe.length === 4 && /carico plumecms\.zip/.test(righe[0]) && righe[0].startsWith('$') && /requisiti a posto/.test(righe[1]), righe);
  const anim = await p.locator('.ed-hte-riga').evaluateAll(rr => rr.map(r => [getComputedStyle(r).animationName, getComputedStyle(r).animationDelay]));
  ok('terminale: le righe si scrivono una dopo l\'altra (ritardi crescenti)', anim.every(a => a[0] === 'ed-hte-batti') && anim.map(a => parseFloat(a[1])).every((d, i, t) => i === 0 || d > t[i - 1]), anim);
  await p.context().close();
  p = await nuova(1280, 860, 'reduce');
  await p.goto(B + '/hero-terminale/', { waitUntil: 'networkidle' });
  ok('terminale: con meno movimento le righe sono già tutte scritte (nessuna animazione)', (await p.locator('.ed-hte-riga').evaluateAll(rr => rr.map(r => getComputedStyle(r).animationName))).every(x => x === 'none'));
  const fermi = await p.evaluate(() => ['.ed-hna-fila', '.ed-hor-giro', '.ed-hoz-suolo', '.ed-hco-alone', '.ed-hvi-play'].map(s => document.querySelector(s)).filter(Boolean).map(e => getComputedStyle(e).animationName));
  ok('meno movimento: nella pagina del terminale nulla si muove', fermi.every(x => x === 'none'), fermi);
  for (const s of ['nastro', 'orbita', 'orizzonte', 'costellazione', 'note', 'faro']) {
    await p.goto(B + '/hero-' + s + '/', { waitUntil: 'networkidle' });
    const a = await p.evaluate(() => [...document.querySelectorAll('.ed-w-hero *')].filter(e => getComputedStyle(e).animationName !== 'none').map(e => e.className.baseVal ?? e.className));
    ok(`meno movimento: ${s} ferma`, a.length === 0, a.slice(0, 4));
  }
  await p.context().close();

  // Titolo traforato
  p = await nuova();
  await p.goto(B + '/hero-traforata/', { waitUntil: 'networkidle' });
  const tr = await p.evaluate(() => {
    const h = document.querySelectorAll('h1'); const g = document.querySelector('.ed-htr-parola');
    return { h1: h.length, testo: h[0] && h[0].innerText, nascosta: g && g.closest('[aria-hidden="true"]') !== null, parola: g && g.textContent, foto: g && getComputedStyle(g).backgroundImage.includes('editor.webp'), clip: g && /text/.test(getComputedStyle(g).getPropertyValue('background-clip') + getComputedStyle(g).getPropertyValue('-webkit-background-clip')) };
  });
  ok('traforata: un solo h1, con dentro il titolo intero (per Google e per chi ascolta)', tr.h1 === 1 && /leggero come una piuma/i.test(tr.testo), tr);
  ok('traforata: la parola grande è "Piuma", con la foto dentro le lettere, nascosta ai lettori di schermo', tr.parola === 'Piuma' && tr.foto && tr.clip && tr.nascosta, tr);
  await p.goto(B + '/hero-traforata-nf/', { waitUntil: 'networkidle' });
  ok('traforata senza foto: la parola si riempie con il gradiente del sito', await p.evaluate(() => getComputedStyle(document.querySelector('.ed-htr-parola')).backgroundImage.includes('gradient')));

  // Video
  await p.goto(B + '/hero-video/', { waitUntil: 'networkidle' });
  ok('video: c\'è il pulsante play con il nome "Guarda il video", lo script è caricato', await p.getByRole('button', { name: 'Guarda il video' }).count() === 1 && await p.locator('script[src*="hero-c.js"]').count() === 1);
  ok('video: prima del clic nessun video si carica', await p.locator('video').count() === 0);
  await p.getByRole('button', { name: 'Guarda il video' }).click();
  await p.waitForTimeout(1200);
  const vd = await p.evaluate(() => { const v = document.querySelector('.ed-w-hero video'); return v ? { src: v.getAttribute('src'), controlli: v.controls, pronto: v.readyState, tempo: v.currentTime, play: !v.paused || v.currentTime > 0, err: v.error && v.error.code } : null; });
  ok('video: al clic il video del sito prende il posto della foto e parte', !!vd && /\/uploads\/video\/.+\.mp4$/.test(vd.src) && vd.controlli && vd.pronto >= 2 && !vd.err, vd);
  ok('video: il pulsante play sparisce, il fuoco va sul video', await p.locator('.ed-hvi-play').count() === 0 && await p.evaluate(() => document.activeElement && document.activeElement.tagName === 'VIDEO'));
  await p.goto(B + '/hero-video-nf/', { waitUntil: 'networkidle' });
  ok('video senza foto: resta la finestra con il play (il video c\'è)', await p.locator('.ed-hvi-play').count() === 1);
  await p.goto(B + '/hero-lente/', { waitUntil: 'networkidle' });
  ok('una pagina senza video né prima e dopo non carica lo script delle aperture', await p.locator('script[src*="hero-c.js"]').count() === 0);

  // Note
  await p.goto(B + '/hero-note/', { waitUntil: 'networkidle' });
  const note = await p.evaluate(() => {
    const f = document.querySelector('.ed-hno-foto').getBoundingClientRect();
    return [...document.querySelectorAll('.ed-hno-note li')].map(li => { const r = li.getBoundingClientRect(); return { t: li.innerText.trim(), dentro: r.left >= f.left - 1 && r.right <= f.right + 1 && r.top >= f.top - 1 && r.bottom <= f.bottom + 1 }; });
  });
  ok('note: tre etichette con il loro testo, tutte dentro la foto', note.length === 3 && /trascini qui/i.test(note[0].t) && note.every(x => x.dentro), note);
  // Lente
  await p.goto(B + '/hero-lente/', { waitUntil: 'networkidle' });
  const le = await p.evaluate(() => {
    const s = document.querySelector('.ed-hle-scena'), f = document.querySelector('.ed-hle-foto').getBoundingClientRect(), a = document.querySelector('.ed-hle-anello').getBoundingClientRect(), z = document.querySelector('.ed-hle-zoom img');
    return { x: s.style.getPropertyValue('--x'), y: s.style.getPropertyValue('--y'), cx: Math.round(((a.left + a.width / 2) - f.left) / f.width * 100), cy: Math.round(((a.top + a.height / 2) - f.top) / f.height * 100), filtro: getComputedStyle(z).filter };
  });
  ok('lente: l\'anello sta sul punto scelto (52% e 56%) e il pezzo ingrandito è a colori', le.x === '52%' && le.y === '56%' && Math.abs(le.cx - 52) <= 1 && Math.abs(le.cy - 56) <= 1 && le.filtro === 'none', le);
  // Costellazione
  await p.goto(B + '/hero-costellazione/', { waitUntil: 'networkidle' });
  const co = await p.evaluate(() => ({ svg: document.querySelectorAll('.ed-hco-cielo').length, piuma: !!document.querySelector('.ed-hco-piuma'), img: document.querySelectorAll('.ed-w-hero img').length, linee: document.querySelectorAll('.ed-hco-linee path').length, accento: !!document.querySelector('.ed-hco-accento') }));
  ok('costellazione: niente foto, la piuma disegnata con le sue linee e il punto viola', co.svg === 1 && co.piuma && co.img === 0 && co.linee > 10 && co.accento, co);
  // Prima e dopo
  await p.goto(B + '/hero-primadopo/', { waitUntil: 'networkidle' });
  const cur = p.getByRole('slider', { name: /prima e dopo/i });
  ok('prima e dopo: la maniglia è un cursore con il nome, a metà', await cur.count() === 1 && await cur.inputValue() === '50');
  await cur.focus();
  for (let i = 0; i < 10; i++) await p.keyboard.press('ArrowRight');
  const pos = async () => p.evaluate(() => ({ pos: document.querySelector('.ed-hpd-scena').style.getPropertyValue('--pos'), testo: document.querySelector('.ed-hpd-cursore').getAttribute('aria-valuetext') }));
  let ps = await pos();
  ok('prima e dopo: con le frecce la divisione si sposta (60%) e il lettore di schermo lo sente', ps.pos === '60%' && /60% prima/.test(ps.testo), ps);
  await p.keyboard.press('Home');
  ps = await pos();
  ok('prima e dopo: Inizio porta la divisione tutta a sinistra', ps.pos === '0%', ps);
  const box = await p.locator('.ed-hpd-scena').boundingBox();
  await p.mouse.move(box.x + box.width * .3, box.y + box.height / 2); await p.mouse.down(); await p.mouse.move(box.x + box.width * .75, box.y + box.height / 2, { steps: 6 }); await p.mouse.up();
  ps = await pos();
  ok('prima e dopo: trascinando con il mouse la divisione segue (circa 75%)', Math.abs(parseInt(ps.pos, 10) - 75) <= 2, ps);
  const focusVis = await p.evaluate(() => { const m = document.querySelector('.ed-hpd-maniglia'); return getComputedStyle(m).outlineStyle; });
  ok('prima e dopo: con il fuoco dalla tastiera la maniglia ha il contorno', focusVis !== 'none', focusVis);
  await p.context().close();

  // Telefono: niente scorrimento di lato, i pulsanti si toccano.
  p = await nuova(390, 844);
  const larghe = [];
  for (const s of ['prospettiva', 'dispositivi', 'pila', 'orbita', 'terminale', 'nastro', 'traforata', 'faro', 'orizzonte', 'video', 'note', 'lente', 'costellazione', 'primadopo', 'trittico']) {
    await p.goto(B + '/hero-' + s + '/', { waitUntil: 'networkidle' });
    const l = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    if (l > 1) larghe.push([s, l]);
  }
  ok('telefono (390 px): nessuna delle quindici scorre di lato', larghe.length === 0, larghe);
  await p.context().close();

  // Editor
  if (UTENTE && PAGINA) {
    p = await nuova(1440, 950);
    await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    await p.goto(`${B}/admin/editor.php?tipo=pagine&id=${PAGINA}`, { waitUntil: 'networkidle' });
    await p.waitForTimeout(1200);
    if (await p.locator('#ed-chiudi-revisioni').isVisible()) await p.click('#ed-chiudi-revisioni');
    const tela = p.frameLocator('iframe').first();
    await tela.locator('[data-ed-tipo="hero"]').first().click(); await p.waitForTimeout(400);
    await p.click('.ed-schede-pannello button[data-pannello="modifica"]'); await p.waitForTimeout(300);
    const ctl = (t) => p.locator('#ed-controlli .ed-controllo', { hasText: t });
    const tendina = ctl('Disposizione').first().locator('select');
    ok('editor: la pagina del terminale apre con la disposizione Terminale e il campo delle righe', await tendina.inputValue() === 'terminale' && await ctl('Righe del terminale').count() === 1);
    ok('editor: gli altri campi nuovi non si vedono con il Terminale', await ctl('Parola grande').count() === 0 && await ctl('Etichette sulla foto').count() === 0 && await ctl('Forma della costellazione').count() === 0 && await ctl('Foto "prima"').count() === 0);
    ok('editor: nella tela lo script delle aperture è caricato', await tela.locator('script[src*="hero-c.js"]').count() === 1);
    await ctl('Righe del terminale').first().locator('textarea').fill('$ prima riga\n+ seconda riga');
    await p.waitForTimeout(1300);
    ok('editor: cambiando le righe la tela le mostra', (await tela.locator('.ed-hte-riga').allInnerTexts()).join('|').includes('seconda riga'));
    await tendina.selectOption('traforata'); await p.waitForTimeout(1300);
    ok('editor: con Titolo traforato compare "Parola grande" e sparisce "Righe del terminale"', await ctl('Parola grande').count() === 1 && await ctl('Righe del terminale').count() === 0);
    await ctl('Parola grande').first().locator('input').fill('Leggero'); await p.waitForTimeout(1300);
    ok('editor: la parola scritta va nella tela, con lo stile del foglio caricato', (await tela.locator('.ed-htr-parola').innerText()).toUpperCase() === 'LEGGERO' && await tela.locator('.ed-htr-parola').evaluate(e => parseFloat(getComputedStyle(e).fontSize) > 60));
    await tendina.selectOption('costellazione'); await p.waitForTimeout(1300);
    await ctl('Forma della costellazione').first().locator('select').selectOption('stella'); await p.waitForTimeout(1300);
    ok('editor: Costellazione, forma Stella: la tela disegna la stella', await tela.locator('.ed-hco-stella').count() === 1);
    await tendina.selectOption('primadopo'); await p.waitForTimeout(1300);
    ok('editor: Prima e dopo compare con il campo della foto "prima", e la maniglia si muove anche nella tela', await ctl('Foto "prima"').count() === 1 && await tela.locator('.ed-hpd-cursore').count() === 1);
    await tela.locator('.ed-hpd-cursore').focus(); await p.keyboard.press('ArrowLeft');
    ok('editor: nella tela la maniglia risponde alle frecce (script ripreso dopo il ridisegno)', await tela.locator('.ed-hpd-scena').evaluate(e => e.style.getPropertyValue('--pos')) === '49%');
    await tendina.selectOption('note'); await p.waitForTimeout(1300);
    ok('editor: Note sulla foto ha il ripetitore delle etichette, la tela le mostra', await ctl('Etichette sulla foto').count() === 1 && await tela.locator('.ed-hno-note li').count() === 3);
    await tendina.selectOption('lente'); await p.waitForTimeout(1300);
    ok('editor: Lente ha i due campi del punto', await ctl('Punto da ingrandire').count() === 2);
    await tendina.selectOption('video'); await p.waitForTimeout(1300);
    ok('editor: Video ha il campo Video con il video scelto', await ctl('Video').filter({ has: p.locator('.ed-riga-video') }).count() === 1 && /prova\.mp4$/.test(await p.locator('.ed-riga-video input').inputValue()));
    await p.locator('.ed-riga-video button', { hasText: 'Scegli' }).click(); await p.waitForTimeout(800);
    const dlg = p.locator('#ed-dialogo-media');
    ok('editor: Scegli apre la finestra in modo video, con il video caricato', await dlg.isVisible() && await p.locator('#ed-media-titolo').innerText() === 'Video' && await dlg.locator('.ed-media-griglia video').count() >= 1 && await p.locator('#ed-media-cartella').isHidden());
    await dlg.locator('.ed-media-griglia button').first().click(); await p.waitForTimeout(1300);
    ok('editor: scelto il video, la tela mostra il play', !(await dlg.isVisible()) && await tela.locator('.ed-hvi-play').count() === 1);
    await p.locator('.ed-riga-video button[aria-label="Togli il video"]').click(); await p.waitForTimeout(1300);
    ok('editor: tolto il video, nella tela resta la foto senza play', await tela.locator('.ed-hvi-play').count() === 0 && await tela.locator('.ed-hvi-foto').count() === 1);
    await p.locator('.ed-riga-video button', { hasText: 'Scegli' }).click(); await p.waitForTimeout(500);
    ok('editor: il caricamento nella finestra accetta solo video', await p.locator('#ed-media-file').getAttribute('accept') === 'video/mp4,video/webm');
    await p.locator('#ed-dialogo-media [data-chiudi]').last().click();
    // Un campo immagine riapre la finestra delle immagini come prima.
    await p.click('#ed-controlli .ed-riga-immagine button:has-text("Scegli")'); await p.waitForTimeout(800);
    ok('editor: dopo i video, Scegli di una foto riapre la finestra delle immagini', await p.locator('#ed-media-titolo').innerText() === 'Immagini' && await p.locator('#ed-media-cartella').isVisible() && await p.locator('#ed-media-file').getAttribute('accept') !== 'video/mp4,video/webm');
    await p.locator('#ed-dialogo-media [data-chiudi]').last().click();
    await p.context().close();
  }
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
