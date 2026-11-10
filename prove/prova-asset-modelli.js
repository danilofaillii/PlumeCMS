// 2.38.1: i widget dei modelli (riquadro "Aggiungi sempre alla fine", footer) escono con il loro stile.
// Prima la pagina caricava i fogli dei widget solo per il suo contenuto: un Riquadro con immagine con la foto a sinistra,
// messo nel riquadro di fine articolo, usciva con la foto sopra e larga quanto tutto (segnalazione di Danilo, 10 novembre 2026).
// Prepara il sito con `php prove/asset-modelli-cli.php <cartella CMS>` (stampa l'articolo). Da visitatore, senza entrare.
// uso: node prove/prova-asset-modelli.js <base> <cartella CMS>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const [B, DIR] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, 'asset-modelli-cli.php'), DIR, ...a]).toString().trim().split('\n').pop());

(async () => {
  const dati = cli();
  ok('il sito ha un articolo pubblicato e un footer predefinito', dati.articolo !== '' && dati.footer > 0, dati);
  const br = await chromium.launch();
  const misura = async (pg, sel) => pg.evaluate(s => {
    const r = document.querySelector(s); if (!r) return null;
    const f = r.querySelector('.ew-ribox-immagine').getBoundingClientRect(), c = r.querySelector('.ew-ribox-corpo').getBoundingClientRect(), b = r.getBoundingClientRect();
    return { dir: getComputedStyle(r).flexDirection, fotoL: Math.round(f.left), fotoR: Math.round(f.right), fotoW: Math.round(f.width), corpoL: Math.round(c.left), corpoR: Math.round(c.right), larga: Math.round(b.width), alto: Math.round(f.top - c.top) };
  }, sel);
  for (const [w, nome] of [[1360, 'computer'], [390, 'telefono']]) {
    const pg = await (await br.newContext({ viewport: { width: w, height: 900 } })).newPage();
    const err = []; pg.on('pageerror', e => err.push(e.message.slice(0, 200)));
    await pg.goto(B + dati.articolo, { waitUntil: 'networkidle' });
    const fogli = await pg.$$eval('link[rel=stylesheet]', l => l.map(x => x.getAttribute('href')));
    const widgetCss = fogli.filter(h => /\/widget\.css/.test(h));
    ok(nome + ': widget.css caricato una sola volta', widgetCss.length === 1, widgetCss);
    const fine = await misura(pg, 'aside.fine-articolo .ew-ribox');
    const piede = await misura(pg, 'footer.ed-piede .ew-ribox');
    if (w > 767) {
      ok(nome + ': fine articolo, foto a sinistra del testo', fine && fine.dir === 'row' && fine.fotoR <= fine.corpoL + 1, fine);
      ok(nome + ': fine articolo, foto larga metà del riquadro (non tutto)', fine && fine.fotoW < fine.larga * 0.6 && fine.fotoW > fine.larga * 0.4, fine);
      ok(nome + ': footer, foto a destra del testo', piede && piede.dir === 'row-reverse' && piede.fotoL >= piede.corpoR - 1, piede);
    } else {
      ok(nome + ': fine articolo, sotto 768 px foto sopra e testo sotto, come nell\'editor', fine && fine.dir === 'column', fine);
      const lato = await pg.evaluate(() => document.documentElement.scrollWidth - innerWidth);
      ok(nome + ': niente scorrimento di lato', lato <= 0, lato);
    }
    ok(nome + ': nessun errore JavaScript', err.length === 0, err);
  }
  // Senza widget nei modelli la pagina non riceve fogli in più: lo stesso articolo con il riquadro spento.
  cli('togli');
  const pg = await (await br.newContext()).newPage();
  await pg.goto(B + '/', { waitUntil: 'networkidle' });
  const doppi = await pg.$$eval('link[rel=stylesheet],script[src]', l => { const v = l.map(x => x.getAttribute('href') || x.getAttribute('src')); return v.filter((x, i) => v.indexOf(x) !== i); });
  ok('home: nessun foglio o script ripetuto', doppi.length === 0, doppi);
  await br.close();
  console.log(`${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
