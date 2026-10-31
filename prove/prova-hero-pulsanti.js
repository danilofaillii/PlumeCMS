// 2.0.2: i pulsanti dell'Apertura si cliccano davvero, in ogni disposizione e con ogni effetto della foto, al computer e al
// telefono. Per ogni pulsante: l'elemento sotto il centro del pulsante è il pulsante (niente velo, foto, onda o effetto sopra),
// e il clic porta alla pagina del suo link. Prima: php prove/pagina-hero-pulsanti.php <cartella CMS> <url foto> > elenco.
// Con TEMA=cosmo (o aria, bottega) e UTENTE e PASSWORD nell'ambiente: le pagine nell'anteprima del tema (?tema=), da amministratore.
// uso: node prove/prova-hero-pulsanti.js <base> <file con l'elenco delle pagine>
const { chromium } = require('playwright');
const fs = require('fs');
const [B, ELENCO] = process.argv.slice(2);
const pagine = fs.readFileSync(ELENCO, 'utf8').split('\n').filter(Boolean);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; if (!c || process.env.TUTTI) console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); };
(async () => {
  const br = await chromium.launch();
  for (const [L, H] of [[1280, 900], [390, 844]]) {
    const ctx = await br.newContext({ viewport: { width: L, height: H } });
    const p = await ctx.newPage();
    const TEMA = process.env.TEMA || '';
    if (TEMA) { await p.goto(B + '/admin/login.php'); await p.fill('#username', process.env.UTENTE); await p.fill('#password', process.env.PASSWORD); await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]); }
    for (const s of pagine) {
      await p.goto(B + '/' + s + '/' + (TEMA ? '?preview=1&tema=' + TEMA : ''), { waitUntil: 'networkidle' });
      const bott = p.locator('.ed-w-hero .azioni a.btn');
      const quanti = await bott.count();
      ok(`${s} a ${L} px: due pulsanti`, quanti === 2, quanti);
      for (let i = 0; i < quanti; i++) {
        const b = bott.nth(i);
        await b.scrollIntoViewIfNeeded();
        const sopra = await b.evaluate(a => {
          const r = a.getBoundingClientRect();
          const punti = [[r.left + r.width / 2, r.top + r.height / 2], [r.left + 6, r.top + r.height / 2], [r.right - 6, r.top + r.height / 2]];
          return punti.map(([x, y]) => { const e = document.elementFromPoint(x, y); return e === a || a.contains(e) ? '' : (e ? e.tagName.toLowerCase() + '.' + String(e.className).trim().replace(/\s+/g, '.') : 'niente'); }).filter(Boolean);
        });
        ok(`${s} a ${L} px: il pulsante ${i + 1} è sopra a tutto`, sopra.length === 0, sopra);
      }
      if (quanti) {
        const href = await bott.first().getAttribute('href');
        const senza = x => x.replace(/\/$/, '');
        ok(`${s} a ${L} px: il primo pulsante ha un link vero, non "#"`, !!href && href !== '#' && href.startsWith('/'), href);
        await Promise.all([p.waitForURL(u => senza(u.pathname) === senza(href), { timeout: 8000 }).catch(() => {}), bott.first().click({ timeout: 5000 }).catch(() => {})]);
        ok(`${s} a ${L} px: il clic sul primo pulsante porta a ${href}`, senza(new URL(p.url()).pathname) === senza(href) && !/404|non trovata/i.test(await p.title()), p.url());
      }
    }
    await ctx.close();
  }
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
