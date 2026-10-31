// Fotografa le pagine del sito (computer e telefono) a pagina intera, orologio fermo e scorrimento immediato. uso: node foto-sito.js <base> <cartella>
const { chromium } = require('playwright');
const [B, OUT] = process.argv.slice(2);
const PAGINE = { home: '/', blog: '/blog/', articolo: '/benvenuto/', articolo2: '/secondo-articolo-di-prova/', chi: '/chi-sono/',
  contatti: '/contatti/', prova: '/pagina-di-prova/', widget: '/tutti-i-widget/', cerca: '/?s=prova', e404: '/non-esiste/' };
const MISURE = { pc: { width: 1280, height: 900 }, tel: { width: 390, height: 844 } };
(async () => {
  const br = await chromium.launch();
  for (const [m, vp] of Object.entries(MISURE)) {
    const ctx = await br.newContext({ viewport: vp, reducedMotion: 'reduce', deviceScaleFactor: 1 });
    const p = await ctx.newPage();
    await p.clock.setFixedTime(new Date('2026-10-27T10:00:00'));
    for (const [nome, u] of Object.entries(PAGINE)) {
      await p.goto(B + u, { waitUntil: 'networkidle' });
      await p.addStyleTag({ content: 'html{scroll-behavior:auto!important}' });
      await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 400) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 30)); } window.scrollTo(0, 0); await document.fonts.ready; });
      await p.waitForTimeout(300);
      await p.screenshot({ path: `${OUT}/${nome}-${m}.png`, fullPage: true, animations: 'disabled' });
    }
    await ctx.close();
  }
  await br.close();
})();
