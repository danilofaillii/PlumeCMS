// Foto di Apertura e Presentazione (1.2.2, 27 ottobre 2026): dall'editor, come un utente, si spegne l'onda, si cambia il
// trattamento della foto, si sceglie un effetto e si pubblica; poi il sito pubblico mostra quello che si è scelto.
// La pagina deve avere un'Apertura con foto ed etichetta dell'onda e una Presentazione con foto, valori di partenza.
// uso: node prova-foto-effetti.js <base> <utente> <password> <id della pagina> <percorso pubblico della pagina>
const { chromium } = require('playwright');
const [B, UTENTE, PASSWORD, ID, PERCORSO] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 220) : '')); };

(async () => {
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 1440, height: 950 } })).newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('console', m => { if (m.type() === 'error' && !/favicon/.test(m.text())) err.push(m.text().slice(0, 200)); });
  p.on('dialog', d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok('login', !p.url().includes('login.php'), p.url());

  await p.goto(`${B}/admin/editor.php?tipo=pagine&id=${ID}`, { waitUntil: 'networkidle' });
  const tela = p.frameLocator('iframe').first();
  const ctl = (testo) => p.locator('#ed-controlli .ed-controllo', { hasText: testo }).first();
  const scheda = async (s) => { await p.click(`#ed-schede-controlli button[data-scheda="${s}"]`); await p.waitForTimeout(200); };
  const apriFoto = async () => { const d = p.locator('#ed-controlli details.ed-sez', { has: p.locator('summary', { hasText: /^Foto$/ }) }); if (!(await d.getAttribute('open') !== null)) await d.locator('summary').click(); };
  const attendiTela = () => p.waitForTimeout(1200);
  const seleziona = async (tipo) => { await tela.locator(`[data-ed-tipo="${tipo}"]`).first().click(); await p.waitForTimeout(400); await p.click('.ed-schede-pannello button[data-pannello="modifica"]'); await p.waitForTimeout(200); };

  // Apertura: l'onda si spegne dalla scheda Contenuto.
  await seleziona('hero');
  ok("Apertura: c'è l'interruttore \"Mostra l'onda\", acceso", await ctl("Mostra l'onda").locator('input[type=checkbox]').isChecked());
  ok("Apertura: l'onda c'è nella tela", await tela.locator('.chip-onda').count() === 1);
  await ctl("Mostra l'onda").locator('label.ed-interruttore').click();
  await attendiTela();
  ok("Apertura: spento l'interruttore, l'onda sparisce dalla tela", await tela.locator('.chip-onda').count() === 0);
  ok("Apertura: con l'onda spenta sparisce anche il campo dell'etichetta", await ctl("Etichetta dell'onda").count() === 0);

  // Apertura: scheda Stile, sezione Foto.
  await scheda('stile'); await apriFoto();
  ok('Apertura: sezione Foto con trattamento, tre colori del velo, intensità, effetto',
    await ctl('Trattamento della foto').count() === 1 && await ctl('Colore del velo in alto a sinistra').count() === 1 && await ctl('Colore del velo al centro').count() === 1
    && await ctl('Colore del velo in basso a destra').count() === 1 && await ctl('Intensità del velo').count() === 1 && await ctl('Effetto animato').count() === 1);
  ok("Apertura: senza effetto i colori dell'effetto non si vedono", await ctl("Primo colore dell'effetto").count() === 0);
  await ctl('Effetto animato').locator('select').selectOption('cornice');
  await attendiTela(); await apriFoto();
  ok("Apertura: scelto Cornice luminosa, compaiono colori e velocità dell'effetto", await ctl("Primo colore dell'effetto").count() === 1 && await ctl("Velocità dell'effetto").count() === 1);
  ok('Apertura: la tela mostra la cornice', await tela.locator('.finestra.ed-fx-cornice .ed-fx-luce').count() === 1);
  await ctl('Trattamento della foto').locator('select').selectOption('naturale');
  await attendiTela(); await apriFoto();
  ok('Apertura: foto naturale nella tela', await tela.locator('.finestra.ed-foto-naturale').count() === 1);
  ok('Apertura: con la foto naturale i colori del velo non si vedono', await ctl('Colore del velo al centro').count() === 0);
  const filtro = await tela.locator('.finestra img').first().evaluate(e => getComputedStyle(e).filter);
  ok('Apertura: la foto naturale non è più in bianco e nero', filtro === 'none', filtro);

  // Presentazione: velo più leggero ed effetto Alone.
  await seleziona('presentazione');
  await scheda('stile'); await apriFoto();
  ok('Presentazione: sezione Foto con due colori del velo', await ctl('Colore del velo in alto').count() === 1 && await ctl('Colore del velo in basso').count() === 1);
  await ctl('Intensità del velo').locator('input[type=number]').fill('40');
  await ctl('Effetto animato').locator('select').selectOption('alone');
  await attendiTela();
  const opac = await tela.locator('.ritratto').first().evaluate(e => getComputedStyle(e, '::after').opacity);
  ok('Presentazione: velo al 40% nella tela', Math.abs(parseFloat(opac) - 0.4) < 0.01, opac);
  ok("Presentazione: l'alone nella tela", await tela.locator('.ritratto.ed-fx-alone').count() === 1);

  // Pubblica e guarda il sito.
  await p.click('#ed-pubblica');
  await p.waitForTimeout(2500);
  const s = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  await s.goto(B + PERCORSO, { waitUntil: 'networkidle' });
  ok('sito: niente onda', await s.locator('.chip-onda').count() === 0);
  ok('sito: Apertura con foto naturale e cornice', await s.locator('.finestra.ed-foto-naturale.ed-fx-cornice').count() === 1);
  ok("sito: Presentazione con l'alone", await s.locator('.ritratto.ed-fx-alone').count() === 1);
  const opacS = await s.locator('.ritratto').first().evaluate(e => getComputedStyle(e, '::after').opacity);
  ok('sito: velo della Presentazione al 40%', Math.abs(parseFloat(opacS) - 0.4) < 0.01, opacS);
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
