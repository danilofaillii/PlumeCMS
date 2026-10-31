// I manuali in PDF (1.2.3, 27 ottobre 2026; dalla 1.6.4 solo manuale d'uso e manuale dei temi): i markdown in docs/ diventano
// releases/manuali/<nome>.pdf, con copertina, indice cliccabile e numeri di pagina.
// Peso: niente PDF "tagged" (i tag dell'accessibilita raddoppiavano il file, 2,1 MB il manuale tecnico) e il carattere
// Carlito (Inter installato qui e variabile, e Chromium lo mette nel PDF lettera per lettera come disegni). Cosi il
// manuale tecnico pesa sotto 1 MB e passa anche dai server che accettano caricamenti piccoli.
// Il Markdown lo converte pandoc (gfm), il PDF lo stampa Chromium (Playwright): nessuna libreria nel CMS.
// 1.4.2: il manuale d'uso ha le schermate (docs/immagini/manuale-uso/, JPEG piccoli fatti da tools/manuale-uso-schermate.js):
// la pagina si apre da un file accanto al Markdown, così gli indirizzi delle immagini valgono; ogni immagine da sola in
// un paragrafo diventa una figura con la didascalia (il testo alternativo).
// uso: node tools/manuali-pdf.js [cartella di uscita, predefinita releases/manuali]
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const RADICE = path.join(__dirname, '..');
const USCITA = path.resolve(process.argv[2] || path.join(RADICE, 'releases', 'manuali'));
const VERSIONE = fs.readFileSync(path.join(RADICE, 'cms', 'VERSIONE'), 'utf8').trim();
// 2.1.0: il manuale d'uso torna un PDF solo, Parte prima e Parte seconda (gli addon) insieme, con le schermate nitide
// (tools/manuale-uso-schermate.js, JPEG a 1200 px e qualità 65). Il limite di 1900 KB della 2.0.0 non serve più (Danilo ha
// alzato il limite di caricamento del suo hosting, 31 ottobre 2026): il manuale-uso-addon.pdf non c'è più. Tetto: 8 MB.
const MAX_KB = { 'manuale-uso': 8 * 1024 };
const MANUALI = [
  { file: 'manuale-uso', titolo: 'Manuale d\'uso', sotto: 'Come si fa ogni cosa con plumeCMS: articoli, pagine, menu, form, newsletter, sicurezza e gli otto addon. Con esempi veri, schermate e checklist.' },
  { file: 'manuale-temi', titolo: 'Manuale dei temi', sotto: 'Come si costruisce un tema da zero, file per file, con un esempio completo.' },
];

const CSS = `
@page { size: A4; margin: 20mm 17mm 22mm 17mm; }
html { font-family: Carlito, 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1d2433; line-height: 1.5; }
body { margin: 0; }
h1, h2, h3, h4 { font-family: Carlito, sans-serif; color: #141a33; line-height: 1.2; break-after: avoid; }
h2 { font-size: 17pt; margin: 0 0 10pt; padding-top: 4pt; border-bottom: 2px solid #e4e1f7; padding-bottom: 6pt; break-before: page; }
h3 { font-size: 12.5pt; margin: 16pt 0 6pt; color: #3d2fb8; }
h4 { font-size: 11pt; margin: 12pt 0 4pt; }
p, li { orphans: 3; widows: 3; }
a { color: #3d2fb8; text-decoration: none; }
code { font-family: 'DejaVu Sans Mono', monospace; font-size: 8.4pt; background: #f2f0fb; border-radius: 3px; padding: 0 2px; word-break: break-word; }
pre { background: #151a2e; color: #e8e6f5; border-radius: 6px; padding: 9pt 11pt; font-size: 8pt; line-height: 1.45; white-space: pre-wrap; word-break: break-word; break-inside: avoid; }
pre code { background: none; color: inherit; padding: 0; font-size: 8pt; }
table { border-collapse: collapse; width: 100%; margin: 8pt 0 12pt; font-size: 8.6pt; break-inside: auto; }
thead { display: table-header-group; }
tr { break-inside: avoid; }
th { background: #ece9fb; color: #141a33; text-align: left; font-weight: 600; }
th, td { border: 1px solid #d9d6ea; padding: 4pt 6pt; vertical-align: top; }
td code, th code { font-size: 7.8pt; }
hr { display: none; }
blockquote { margin: 8pt 0; padding: 6pt 10pt; border-left: 3px solid #7b6cf0; background: #f6f5fd; }
.copertina { box-sizing: border-box; height: 252mm; display: flex; flex-direction: column; justify-content: space-between; break-after: page;
  background: linear-gradient(160deg, #141a33 0%, #2a1f6e 60%, #4a35c9 100%); color: #fff; margin: 0; padding: 26mm 18mm 20mm; border-radius: 10pt; }
.copertina .marchio { font: 700 13pt Carlito, sans-serif; letter-spacing: .08em; opacity: .9; }
.copertina .marchio b { color: #b8acff; }
.copertina h1 { color: #fff; font-size: 38pt; margin: 0 0 12pt; letter-spacing: -.01em; }
.copertina .sotto { font-size: 13pt; max-width: 135mm; opacity: .88; line-height: 1.45; }
.copertina .piede { font-size: 10pt; opacity: .75; }
.copertina .piuma { width: 46mm; height: 46mm; opacity: .9; }
nav.indice { break-after: page; }
nav.indice h2 { break-before: auto; }
nav.indice ul { list-style: none; padding-left: 0; margin: 0; }
nav.indice > ul > li { margin: 0 0 5pt; font-weight: 600; }
nav.indice ul ul { padding-left: 14pt; font-weight: 400; margin-top: 3pt; }
nav.indice ul ul li { margin: 1.5pt 0; font-size: 9pt; }
nav.indice a { color: #1d2433; }
figure { margin: 6pt 0 12pt; break-inside: avoid; text-align: center; }
figure img { max-width: 100%; max-height: 180mm; border: 1px solid #d9d6ea; border-radius: 6px; }
figcaption { font-size: 8.4pt; color: #5d5a78; margin-top: 3pt; font-style: italic; }
ul.task-list { list-style: none; padding-left: 2pt; }
ul.task-list li { margin: 2pt 0; }
ul.task-list input { width: 9pt; height: 9pt; margin: 0 6pt 0 0; vertical-align: -1pt; }
`;

// Una piuma disegnata in SVG (niente immagini esterne).
const PIUMA = `<svg class="piuma" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="#b8acff" stroke-width="2.4" stroke-linecap="round">
<path d="M78 12C52 16 30 38 24 70l-6 18"/><path d="M78 12c-4 20-16 40-36 52"/><path d="M60 22c-8 2-16 6-22 12"/><path d="M68 30c-10 4-20 10-27 18"/>
<path d="M66 44c-10 4-18 8-26 16"/><path d="M58 56c-8 3-14 6-20 11"/></svg>`;

function html(m) {
  let md = fs.readFileSync(path.join(RADICE, 'cms', 'docs', m.file + '.md'), 'utf8');
  md = md.replace(/^# .*\n/, '').replace(/^Versione del manuale:.*\n/m, '');
  md = md.replace(/^## Indice\n[\s\S]*?(?=^---$)/m, ''); // l'indice lo fa pandoc, con i collegamenti
  const tpl = path.join(USCITA, '.modello.html');
  fs.writeFileSync(tpl, '<nav class="indice"><h2>Indice</h2>\n$toc$\n</nav>\n$body$\n');
  const corpo = execFileSync('pandoc', ['-f', 'gfm', '-t', 'html5', '--toc', '--toc-depth=3', '--template', tpl, '--metadata', 'title=' + m.titolo],
    { input: md, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
  fs.unlinkSync(tpl);
  const conFigure = corpo.replace(/<p><img src="([^"]+)" alt="([^"]*)" \/><\/p>/g, '<figure><img src="$1" alt="$2"><figcaption>$2</figcaption></figure>');
  const oggi = new Date().toLocaleDateString('it-IT', { day: 'numeric', month: 'long', year: 'numeric' });
  return `<!doctype html><html lang="it"><head><meta charset="utf-8"><title>plumeCMS - ${m.titolo}</title><style>${CSS}</style></head><body>
<section class="copertina"><div class="marchio">plume<b>CMS</b></div>
<div>${PIUMA}<h1>${m.titolo}</h1><p class="sotto">${m.sotto}</p></div>
<div class="piede">plumeCMS ${VERSIONE} &middot; ${oggi}</div></section>
${conFigure}</body></html>`;
}

(async () => {
  fs.mkdirSync(USCITA, { recursive: true });
  try { fs.unlinkSync(path.join(USCITA, 'manuale-uso-addon.pdf')); } catch (e) {} // 2.1.0: non esiste più
  const br = await chromium.launch();
  const p = await br.newPage();
  for (const m of MANUALI) {
    // Da un file accanto al Markdown: le immagini (immagini/...) si trovano.
    const nome = m.file;
    const pagina = path.join(RADICE, 'cms', 'docs', '.stampa-' + nome + '.html');
    fs.writeFileSync(pagina, html(m));
    await p.goto('file://' + pagina, { waitUntil: 'load' });
    fs.unlinkSync(pagina);
    await p.evaluate(() => document.fonts.ready);
    const piede = `<div style="width:100%;font:8pt Carlito,sans-serif;color:#8a86a3;padding:0 17mm;display:flex;justify-content:space-between">
<span>plumeCMS &middot; ${m.titolo}</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>`;
    const file = path.join(USCITA, nome + '.pdf');
    await p.pdf({ path: file, format: 'A4', printBackground: true, displayHeaderFooter: true, headerTemplate: '<span></span>', footerTemplate: piede,
      margin: { top: '20mm', bottom: '22mm', left: '17mm', right: '17mm' }, outline: true, tagged: false });
    const kb = Math.round(fs.statSync(file).size / 1024);
    console.log(`${file}: ${kb} KB`);
    if (MAX_KB[nome] && kb > MAX_KB[nome]) { console.error(`ERRORE: ${nome}.pdf pesa ${kb} KB, oltre ${MAX_KB[nome]} KB: abbassa QUALITA in tools/manuale-uso-schermate.js`); process.exitCode = 1; }
  }
  await br.close();
})();
