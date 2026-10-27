// Le schermate per il sito di plumeCMS (1.2.3): pannello, editor sulla home, i tre temi in anteprima sulla home.
// Si fanno su un plumeCMS a cui e gia stato applicato il pacchetto del sito (cosi l'editor e i temi mostrano plumeCMS).
// uso: node sito-plumecms/schermate.js <base> <cartella> <utente> <password>
// poi: python3 sito-plumecms/costruisci.py --schermate <cartella>. La home ha id 1, come in ogni installazione nuova.
const { chromium } = require('playwright');
const [B, D, UTENTE, PASSWORD] = process.argv.slice(2);
(async () => {
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
  const p = await ctx.newPage();
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  const foto = async (nome, url, prima) => { await p.goto(B + url, { waitUntil: 'networkidle' }); if (prima) await prima(); await p.waitForTimeout(700); await p.screenshot({ path: `${D}/${nome}.png` }); console.log(nome); };
  const editor = async (id, tipo, scheda, foto) => {
    await p.waitForTimeout(1200);
    if (await p.locator('#ed-chiudi-revisioni').isVisible()) { await p.click('#ed-chiudi-revisioni'); await p.waitForTimeout(500); }
    const t = p.frameLocator('iframe').first();
    await t.locator(`[data-ed-tipo="${tipo}"]`).first().click(); await p.waitForTimeout(400);
    await p.click('.ed-schede-pannello button[data-pannello="modifica"]');
    if (scheda) await p.click(`#ed-schede-controlli button[data-scheda="${scheda}"]`);
    if (foto) { const d = p.locator('#ed-controlli details.ed-sez', { has: p.locator('summary', { hasText: /^Foto$/ }) }); if (await d.getAttribute('open') === null) await d.locator('summary').click(); await p.locator('#ed-controlli .ed-controllo', { hasText: 'Effetto animato' }).locator('select').selectOption('cornice'); await p.waitForTimeout(1200); if (await d.getAttribute('open') === null) await d.locator('summary').click(); }
  };
  await foto('editor', '/admin/editor.php?tipo=pagine&id=1', () => editor(1, 'hero', 'stile'));
  await p.setViewportSize({ width: 1120, height: 1400 });
  await foto('editor-ritratto', '/admin/editor.php?tipo=pagine&id=1', () => editor(1, 'hero', 'stile', true));
  await p.setViewportSize({ width: 1440, height: 900 });
  for (const [n, u] of [['dashboard', '/admin/index.php'], ['stile', '/admin/design.php'], ['modelli', '/admin/modelli.php'], ['versioni', '/admin/versioni.php'],
    ['seo', '/admin/seo.php'], ['flussi', '/admin/newsletter-flussi.php'], ['sicurezza', '/admin/sicurezza.php'], ['aggiornamenti', '/admin/aggiornamenti.php'], ['download', '/admin/scaricabili.php'],
    ['temi', '/admin/temi.php'], ['menu', '/admin/menu.php'], ['form', '/admin/form.php']]) await foto(n, u);
  await p.setViewportSize({ width: 1280, height: 800 });
  for (const t of ['cosmo', 'aria', 'bottega']) await foto('tema-' + t, `/?preview=1&tema=${t}`);
  await br.close();
})();
