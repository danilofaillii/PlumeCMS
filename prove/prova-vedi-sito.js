// Login nel pannello, clic su "Vedi sito", controllo che si apra il sito (anche in cartella).
// uso: node prova-vedi-sito.js <base del CMS, es. http://127.0.0.1:8118/test> <utente> <password>
const { chromium } = require('playwright');
(async () => {
  const B = process.argv[2], U = process.argv[3], P = process.argv[4];
  const br = await chromium.launch(); const ctx = await br.newContext(); const p = await ctx.newPage();
  await p.goto(B + '/admin/login.php'); await p.fill('input[name=username]', U); await p.fill('input[name=password]', P);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  const a = p.locator('a:has-text("Vedi sito")'); console.log('href', await a.getAttribute('href'));
  const [nuova] = await Promise.all([ctx.waitForEvent('page'), a.click()]);
  await nuova.waitForLoadState('networkidle'); console.log('apre', nuova.url(), '|', (await nuova.title()));
  for (const u of ['/', '']) { const r = await p.goto(B + u); console.log(B + u, '->', r.status(), p.url()); }
  await br.close();
})();
