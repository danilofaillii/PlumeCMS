// Titolo animato, Condividi, Avviso, Flip box, Icone social e Carosello di immagini (1.3.0, albero versione 2): i controlli
// che avevano lo stesso nome di uno delle Avanzate ("animazione", "padding") o di un altro dello stesso widget ("spazio")
// hanno un nome loro. Prima: php prove/pagina-nomi-doppi.php <cartella CMS> (stampa l'id della pagina).
// 1. sul sito, con l'albero salvato con la versione 1: tutto come prima (parole in dissolvenza, Zoom con l'ingresso in zoom,
//    lo spazio interno di 30 px sia sul widget sia dentro, le icone social a 10 px, caroselli a 30 e 12 px);
// 2. nell'editor, come un utente: Titolo animato con parole "Scrittura a macchina" e ingresso "Dal basso"; sul Titolo
//    animato salvato in Zoom l'ingresso torna "Nessuna"; Condividi con 20 px di spazio interno nei pulsanti (Stile); Icone
//    social con 16 px intorno all'icona (Stile); nel carosello un solo controllo "spazio"; Pubblica;
// 3. riaperto l'editor, i controlli hanno ancora quei valori;
// 4. sul sito: classi e spazi misurati nel browser.
// uso: node prova-nomi-doppi.js <base> <utente> <password> <id della pagina> <cartella schermate>
const { chromium } = require('playwright');
const [B, UTENTE, PASSWORD, PAGINA, OUT] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); };
// Gli id dei widget scritti da pagina-nomi-doppi.php
const W = { vuota: 'nd00003', zoom: 'nd00006', basso: 'nd00009', senza: 'nd00012', nuovo: 'nd00015', cond: 'nd00018', avviso: 'nd00021',
  flip: 'nd00024', social: 'nd00027', condNuovo: 'nd00030', socialNuovo: 'nd00033', car30: 'nd00036', car: 'nd00039' };

(async () => {
  const br = await chromium.launch();
  const v = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const misura = async () => {
    await v.goto(B + '/nomi-doppi/', { waitUntil: 'networkidle' });
    return v.evaluate(W => {
      const out = {};
      const pad = el => el ? getComputedStyle(el).paddingTop : null;
      for (const [k, id] of Object.entries(W)) {
        const w = document.querySelector('.ed-' + id);
        const dentro = w.querySelector('.ew-tanim-rot, .ew-cond-voce, .ew-avviso, .ew-flip-contenuto, .ew-social-voce, .ew-social a');
        out[k] = { classi: w.className, parole: (w.querySelector('.ew-tanim-rot') || { className: '' }).className, fuori: pad(w), dentro: pad(dentro),
          gap: getComputedStyle(w).getPropertyValue('--ew-gap').trim() };
      }
      return out;
    }, W);
  };

  // 1.
  let m = await misura();
  for (const k of ['vuota', 'senza', 'nuovo']) ok(`versione 1, ${k}: parole in dissolvenza, senza ingresso`, m[k].parole.includes('ew-anim-dissolvi') && !m[k].classi.includes('ed-anima'), m[k]);
  ok('versione 1: Zoom resta zoom per le parole e per l\'ingresso', m.zoom.parole.includes('ew-anim-zoom') && m.zoom.classi.includes('ed-anima-zoom'), m.zoom);
  ok('versione 1: Dal basso, parole in dissolvenza e ingresso dal basso', m.basso.parole.includes('ew-anim-dissolvi') && m.basso.classi.includes('ed-anima-dal_basso'), m.basso);
  for (const k of ['cond', 'avviso', 'flip']) ok(`versione 1: ${k}, 30 px sul widget e dentro, come prima`, m[k].fuori === '30px' && m[k].dentro === '30px', m[k]);
  ok('versione 1: social, 5 px sul widget (Avanzate) e 10 px intorno alle icone', m.social.fuori === '5px' && m.social.dentro === '10px', m.social);
  ok('versione 1: caroselli a 30 e 12 px', m.car30.gap === '30px' && m.car.gap === '12px', [m.car30.gap, m.car.gap]);

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
  const scegli = async (id, scheda) => {
    await p.frameLocator('iframe').first().locator(`[data-ed-id="${id}"]`).click(); await p.waitForTimeout(400);
    await p.click('.ed-schede-pannello button[data-pannello="modifica"]'); await p.waitForTimeout(200);
    await p.click(`[data-scheda="${scheda}"]`); await p.waitForTimeout(300);
  };
  const controlli = async re => {
    await p.evaluate(() => document.querySelectorAll('#ed-controlli details.ed-sez').forEach(d => { d.open = true; }));
    return p.locator('#ed-controlli .ed-controllo').filter({ has: p.locator('.ed-etichetta', { hasText: re }) });
  };
  const controllo = async re => (await controlli(re)).first();
  const tendina = async re => (await controllo(re)).locator('select').first();
  await apri();
  await scegli(W.nuovo, 'contenuto');
  ok('editor: il titolo nuovo mostra Dissolvenza (quello che il sito mostra)', await (await tendina(/^Animazione delle parole/)).inputValue() === 'dissolvi');
  await (await tendina(/^Animazione delle parole/)).selectOption('scrittura'); await p.waitForTimeout(500);
  await scegli(W.nuovo, 'avanzate');
  await (await tendina(/^Animazione all'ingresso/)).selectOption('dal_basso'); await p.waitForTimeout(500);
  await scegli(W.zoom, 'avanzate');
  await (await tendina(/^Animazione all'ingresso/)).selectOption(''); await p.waitForTimeout(500);
  await scegli(W.condNuovo, 'stile');
  const lati = (await controllo(/^Spazio interno(?! \()/)).locator('.ed-dimensioni input[type=number]');
  for (let i = 0; i < 4; i++) { await lati.nth(i).fill('20'); await p.waitForTimeout(150); }
  await scegli(W.socialNuovo, 'stile');
  await (await controllo(/^Spazio intorno all'icona/)).locator('input[type=number]').fill('16'); await p.waitForTimeout(500);
  await scegli(W.car, 'stile');
  ok('editor: il carosello ha un solo controllo di spazio', await (await controlli(/^Spazio fra/)).count() === 1, await (await controlli(/^Spazio fra/)).allInnerTexts());
  await p.click('#ed-pubblica'); await p.waitForTimeout(2500);
  ok('pubblicato', /pubblicat/i.test(await p.locator('#ed-stato').innerText()), await p.locator('#ed-stato').innerText());

  // 3.
  await apri();
  await scegli(W.nuovo, 'contenuto');
  ok('riaperto: "Animazione delle parole" e Scrittura a macchina', await (await tendina(/^Animazione delle parole/)).inputValue() === 'scrittura');
  await scegli(W.nuovo, 'avanzate');
  ok('riaperto: "Animazione all\'ingresso" e Dal basso', await (await tendina(/^Animazione all'ingresso/)).inputValue() === 'dal_basso');
  await scegli(W.zoom, 'contenuto');
  ok('riaperto: il titolo di prima ha ancora le parole in Zoom', await (await tendina(/^Animazione delle parole/)).inputValue() === 'zoom');
  await scegli(W.condNuovo, 'avanzate');
  ok('riaperto: Condividi, lo spazio interno delle Avanzate e ancora vuoto', await (await controllo(/^Spazio interno/)).locator('.ed-dimensioni input[type=number]').first().inputValue() === '');
  await scegli(W.condNuovo, 'stile');
  ok('riaperto: Condividi, lo spazio interno dei pulsanti e 20', await (await controllo(/^Spazio interno(?! \()/)).locator('.ed-dimensioni input[type=number]').first().inputValue() === '20');
  await scegli(W.socialNuovo, 'stile');
  ok('riaperto: Icone social, lo spazio intorno all\'icona e 16', await (await controllo(/^Spazio intorno all'icona/)).locator('input[type=number]').inputValue() === '16');

  // 4.
  m = await misura();
  ok('sito: parole a macchina e ingresso dal basso', m.nuovo.parole.includes('ew-anim-scrittura') && m.nuovo.classi.includes('ed-anima-dal_basso'), m.nuovo);
  ok('sito: Zoom senza ingresso, parole ancora in zoom (prima erano legate)', m.zoom.parole.includes('ew-anim-zoom') && !m.zoom.classi.includes('ed-anima'), m.zoom);
  ok('sito: Condividi, 20 px nei pulsanti e niente sul widget', m.condNuovo.dentro === '20px' && m.condNuovo.fuori === '0px', m.condNuovo);
  ok('sito: Icone social, 16 px intorno alle icone (prima restavano 10)', m.socialNuovo.dentro === '16px' && m.socialNuovo.fuori === '0px', m.socialNuovo);
  for (const k of ['cond', 'avviso', 'flip']) ok(`sito: ${k} salvato con la versione 1 ancora a 30 px, fuori e dentro`, m[k].fuori === '30px' && m[k].dentro === '30px', m[k]);
  ok('sito: caroselli ancora a 30 e 12 px', m.car30.gap === '30px' && m.car.gap === '12px', [m.car30.gap, m.car.gap]);
  await v.screenshot({ path: `${OUT}/nomi-doppi.png`, fullPage: true });
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
