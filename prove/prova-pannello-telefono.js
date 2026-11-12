// 2.0.1: il pannello al telefono. Apre ogni pagina del menu (e le sottovoci) a 390x844, come un amministratore, e controlla
// che la pagina non scorra di lato; per ogni pagina larga dice l'elemento più interno che esce dallo schermo.
// Con LARGHEZZA=<px> nell'ambiente prova un'altra misura (per esempio 768, il tablet).
// uso: node prove/prova-pannello-telefono.js <base> <utente> <password> [pagine in più separate da virgola]
const { chromium } = require('playwright');
const [B, UTENTE, PASSWORD, PIU = ''] = process.argv.slice(2);
const L = +(process.env.LARGHEZZA || 390);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
(async () => {
  const br = await chromium.launch();
  const c = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await c.newPage();
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/', { waitUntil: 'networkidle' });
  let pagine = [...new Set(await p.locator('.admin-sidebar a[href], nav a[href]').evaluateAll(l => l.map(a => a.getAttribute('href')).filter(h => h && !/^(https?:|#|mailto:|\.\.|\/)/.test(h) && !/logout|editor\.php|anteprima/.test(h))))];
  pagine = pagine.concat(PIU.split(',').filter(Boolean));
  const t = await (await br.newContext({ viewport: { width: L, height: 844 }, storageState: await c.storageState() })).newPage();
  const err = []; t.on('pageerror', e => err.push(t.url() + ' ' + e.message.slice(0, 120)));
  for (const u of [...new Set(pagine)]) {
    const r = await t.goto(B + '/admin/' + u, { waitUntil: 'networkidle' }).catch(() => null);
    if (!r || r.status() !== 200) { ok(u + ': si apre', false, r && r.status()); continue; }
    const m = await t.evaluate((L) => {
      const W = document.documentElement.scrollWidth;
      if (W <= L + 1) return { W };
      const colpe = [...document.querySelectorAll('body *')].filter(e => { const b = e.getBoundingClientRect(); return b.right > L + 1 && b.width > 0 && getComputedStyle(e).position !== 'fixed'; })
        .filter(e => ![...e.children].some(k => k.getBoundingClientRect().right > L + 1))
        .slice(0, 3).map(e => e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (e.className && typeof e.className === 'string' ? '.' + e.className.trim().split(/\s+/).join('.') : '') + ' ' + Math.round(e.getBoundingClientRect().right));
      return { W, colpe };
    }, L);
    ok(u + ': niente scorrimento di lato a ' + L + ' px', m.W <= L + 1, m.colpe ? m : '');
  }
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
