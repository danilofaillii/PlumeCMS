// Fotografa una per una le infografiche (.igx) della pagina /tutti-i-widget/ (prove/pagina-widget.php), per il confronto a occhio
// fra il sito senza tema e con un tema. Attenzione: l header fisso del sito si sovrappone alla parte alta di alcune foto.
// uso: node foto-infografiche.js <base> <prefisso dei file, es. cartella/base>
const { chromium } = require('playwright');
(async () => {
  const br = await chromium.launch(); const p = await (await br.newContext({ viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce' })).newPage();
  await p.goto(process.argv[2] + '/tutti-i-widget/', { waitUntil: 'networkidle' });
  await p.addStyleTag({ content: 'html{scroll-behavior:auto!important}' });
  const n = await p.locator('.igx').count();
  for (let i = 0; i < n; i++) await p.locator('.igx').nth(i).screenshot({ path: `${process.argv[3]}-${String(i).padStart(2, '0')}.png` });
  console.log(n, 'infografiche'); await br.close();
})();
