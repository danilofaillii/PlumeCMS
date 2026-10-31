// Le schermate per il sito di plumeCMS (1.2.3): pannello, editor sulla home, i tre temi in anteprima sulla home.
// Si fanno su un plumeCMS a cui e gia stato applicato il pacchetto del sito (cosi l'editor e i temi mostrano plumeCMS).
// uso: node sito-plumecms/schermate.js <base> <cartella> <utente> <password>
// poi: python3 sito-plumecms/costruisci.py --schermate <cartella>. La home ha id 1, come in ogni installazione nuova.
// 1.4.0: importa-wp, tipo-sito, primi-passi, aspetti e controlla-server vengono dalle prove (servono stati che qui non ci sono:
// un WordPress collegato, un sito ancora in prova, i primi passi fatti): prova-importa-wp.js, prova-tipo-sito.js,
// prova-primi-passi.js, prova-aspetti.js, prova-controlla-server.js, tagliate in alto e messe nella cartella delle schermate.
// 1.5.0: social viene dall'email del mattino del sito delle schermate del manuale d'uso. 1.6.0: fine-articolo è il riquadro
// in fondo a "Chiusi per ferie" sullo stesso sito (kit Attività, dopo tools/manuale-uso-schermate.js), fotografato a 2x.
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
    ['seo', '/admin/seo.php'], ['flussi', '/admin/newsletter-flussi.php'], ['sicurezza', '/admin/sicurezza.php'], ['aggiornamenti', '/admin/aggiornamenti.php'], ['download', '/admin/scaricabili.php'], ['copia', '/admin/copia-sito.php'],
    ['temi', '/admin/temi.php'], ['menu', '/admin/menu.php'], ['form', '/admin/form.php'], ['addon', '/admin/addon.php']]) await foto(n, u); // 2.0.0: addon
  // 1.3.0: l'editor della home con la linguetta Sezioni aperta.
  await foto('sezioni', '/admin/editor.php?tipo=pagine&id=1', async () => {
    await p.waitForTimeout(1200);
    if (await p.locator('#ed-chiudi-revisioni').isVisible()) await p.click('#ed-chiudi-revisioni');
    await p.click('.ed-schede-pannello button[data-pannello="sezioni"]'); await p.waitForTimeout(600);
  });
  // 2.1.0: Scegli dai Media, sulla scheda Schema della SEO, con il logo scelto e la finestra aperta.
  await foto('scegli-media', '/admin/seo.php?scheda=schema', async () => {
    await p.click('[data-scegli-media="#seo-org-logo"]');
    await p.waitForSelector('dialog.pn-media[open] .pn-media-voce'); await p.waitForTimeout(900);
  });
  // 2.1.0: Impostazioni > Consegna delle email, Da dove cominciare; i widget della 2.1 (menu del ristorante e galleria con
  // filtri) sulla pagina /widget-2-1/ (prove/pagina-widget-2-1.php <cartella del sito> nuovi), solo la parte dei due widget.
  await foto('consegna', '/admin/consegna.php');
  await p.goto(B + '/widget-2-1/', { waitUntil: 'networkidle' }); await p.waitForTimeout(900);
  {
    const a = await p.locator('.ed-w-menu_ristorante').first().boundingBox();
    const g = await p.locator('.ed-w-galleria_filtri').first().boundingBox();
    const y0 = Math.max(0, a.y - 30), y1 = Math.min(a.y + 900, Math.max(a.y + a.height, g ? g.y + g.height : 0) + 30);
    await p.screenshot({ path: `${D}/widget-2-1.png`, fullPage: true, clip: { x: 0, y: y0, width: 1440, height: Math.min(1400, y1 - y0) } });
    console.log('widget-2-1');
  }
  await p.setViewportSize({ width: 1280, height: 800 });
  for (const t of ['cosmo', 'aria', 'bottega']) await foto('tema-' + t, `/?preview=1&tema=${t}`);
  await br.close();
})();
