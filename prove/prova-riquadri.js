// Riquadro con icona, Riquadro con immagine e Pulsante fisso (1.4.0): la posizione scelta resta dopo il salvataggio e
// l'allineamento vale anche per il testo. Prima: php prove/pagina-riquadri.php <cartella CMS> (stampa l'id della pagina).
// 1. sul sito, ai valori di partenza: icona e immagine sopra, testo centrato, pulsante a destra (com'era);
//    il riquadro salvato dalla 1.3.1 con "posizione" = "sinistra" esce a sinistra (il nome di prima vale ancora);
// 2. nell'editor, come un utente: icona "A sinistra" con allineamento Destra, immagine sopra con allineamento
//    Sinistra, pulsante fisso "In basso a sinistra"; Pubblica;
// 3. riaperto l'editor, le tendine hanno ancora quei valori (prima tornavano "Sopra" e "In basso a destra");
// 4. sul sito: classi e allineamento del testo misurati nel browser.
// uso: node prova-riquadri.js <base> <utente> <password> <id della pagina> <cartella schermate>
const { chromium } = require('playwright');
const [B, UTENTE, PASSWORD, PAGINA, OUT] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); };

(async () => {
  const br = await chromium.launch();
  const v = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const misura = async () => {
    await v.goto(B + '/riquadri/', { waitUntil: 'networkidle' });
    return v.evaluate(() => Array.from(document.querySelectorAll('.ew-ribox')).map(r => ({ classi: r.className, testo: getComputedStyle(r.querySelector('.ew-ribox-corpo')).textAlign }))
      .concat(Array.from(document.querySelectorAll('.ew-fisso')).map(f => ({ classi: f.className, sinistra: f.getBoundingClientRect().left < innerWidth / 2 }))));
  };

  // 1.
  let m = await misura();
  ok('partenza: icona sopra, testo centrato', m[0].classi.includes('ew-pos-sopra') && m[0].testo === 'center', m[0]);
  ok('partenza: immagine sopra, testo centrato', m[1].classi.includes('ew-pos-sopra') && m[1].testo === 'center', m[1]);
  ok('partenza: riquadro della 1.3.1 con "posizione" = sinistra esce a sinistra, testo a sinistra', m[2].classi.includes('ew-pos-sinistra') && m[2].testo === 'left', m[2]);
  ok('partenza: pulsante fisso in basso a destra', m[3].classi.includes('ew-fisso-destra') && !m[3].sinistra, m[3]);

  // 2.
  const p = await (await br.newContext({ viewport: { width: 1440, height: 950 } })).newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200))); p.on('dialog', d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  const apri = async () => {
    await p.goto(`${B}/admin/editor.php?tipo=pagine&id=${PAGINA}`, { waitUntil: 'networkidle' });
    await p.waitForTimeout(1200);
    if (await p.locator('#ed-chiudi-revisioni').isVisible()) await p.click('#ed-chiudi-revisioni');
  };
  const scegli = async (tipo, n) => {
    await p.frameLocator('iframe').first().locator(`[data-ed-tipo="${tipo}"]`).nth(n).click(); await p.waitForTimeout(400);
    await p.click('.ed-schede-pannello button[data-pannello="modifica"]'); await p.waitForTimeout(200);
  };
  // Le sezioni del pannello (details) si aprono tutte, poi il controllo si trova dalla sua etichetta.
  const controllo = async re => {
    await p.evaluate(() => document.querySelectorAll('#ed-controlli details.ed-sez').forEach(d => { d.open = true; }));
    return p.locator('#ed-controlli .ed-controllo').filter({ has: p.locator('.ed-etichetta', { hasText: re }) }).first();
  };
  const tendina = async re => (await controllo(re)).locator('select');
  await apri();
  await scegli('riquadro_icona', 0);
  await (await tendina(/^Posizione dell'icona/)).selectOption('sinistra'); await p.waitForTimeout(500);
  await (await controllo(/^Allineamento(?! verticale)/)).locator('button[title="Destra"]').first().click(); await p.waitForTimeout(500);
  await scegli('riquadro_immagine', 0);
  await (await controllo(/^Allineamento(?! verticale)/)).locator('button[title="Sinistra"]').first().click(); await p.waitForTimeout(500);
  await scegli('pulsante_fisso', 0);
  await (await tendina(/^Angolo/)).selectOption('sinistra'); await p.waitForTimeout(500);
  await p.click('#ed-pubblica'); await p.waitForTimeout(2500);
  ok('pubblicato', /pubblicat/i.test(await p.locator('#ed-stato').innerText()), await p.locator('#ed-stato').innerText());

  // 3.
  await apri();
  await scegli('riquadro_icona', 0);
  ok('riaperto: "Posizione dell\'icona" e ancora A sinistra', await (await tendina(/^Posizione dell'icona/)).inputValue() === 'sinistra');
  await scegli('pulsante_fisso', 0);
  ok('riaperto: "Angolo" e ancora In basso a sinistra', await (await tendina(/^Angolo/)).inputValue() === 'sinistra');

  // 4.
  m = await misura();
  ok('sito: icona a sinistra, testo allineato a destra come scelto', m[0].classi.includes('ew-pos-sinistra') && m[0].testo === 'right', m[0]);
  ok('sito: immagine sopra, testo a sinistra come scelto (prima restava centrato)', m[1].classi.includes('ew-pos-sopra') && m[1].testo === 'left', m[1]);
  ok('sito: pulsante fisso in basso a sinistra', m[3].classi.includes('ew-fisso-sinistra') && m[3].sinistra, m[3]);
  await v.screenshot({ path: `${OUT}/riquadri.png`, fullPage: true });
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
