// I tre temi di partenza (cosmo, aria, bottega) attivati uno per uno (fase 5, 27 ottobre 2026): pagine che rispondono,
// menu Principale nell'header (il tema lo indica come "posizione:principale"), contrasto leggibile del testo, nessun errore.
// Il contrasto si misura come le norme WCAG: almeno 4,5 a 1 fra testo e sfondo dietro.
// uso: node prova-temi-partenza.js <base> <cartella CMS installato> [temi separati da virgola, predefinito cosmo,aria,bottega]
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const [B, DIR, TEMI = 'cosmo,aria,bottega'] = process.argv.slice(2);
const cli = (...a) => execFileSync('php', [path.join(__dirname, 'tema-cli.php'), DIR, ...a], { encoding: 'utf8' });
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 220) : '')); };

// Il contrasto di un elemento: colore del testo contro il primo sfondo pieno risalendo i genitori (fino a body).
const contrasto = (p, sel) => p.evaluate((sel) => {
  const el = document.querySelector(sel); if (!el) return null;
  const rgb = (s) => { const m = s.match(/[\d.]+/g).map(Number); return { r: m[0], g: m[1], b: m[2], a: m.length > 3 ? m[3] : 1 }; };
  const lum = (c) => { const f = (x) => { x /= 255; return x <= .03928 ? x / 12.92 : Math.pow((x + .055) / 1.055, 2.4); }; return .2126 * f(c.r) + .7152 * f(c.g) + .0722 * f(c.b); };
  let e = el, bg = null;
  while (e) { const c = rgb(getComputedStyle(e).backgroundColor); if (c.a > .9) { bg = c; break; } e = e.parentElement; }
  if (!bg) bg = { r: 255, g: 255, b: 255 };
  const fg = rgb(getComputedStyle(el).color);
  const [a, b] = [lum(fg), lum(bg)].sort((x, y) => y - x);
  return Math.round((a + .05) / (b + .05) * 100) / 100;
}, sel);

(async () => {
  const br = await chromium.launch();
  for (const tema of TEMI.split(',')) {
    const att = cli('attiva', tema);
    ok(`${tema}: attivato`, att.includes('Tema attivo'), att.split('\n').pop());
    const p = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 160)));
    p.on('console', m => { if (m.type() === 'error' && !/favicon/.test(m.text())) err.push(m.text().slice(0, 160)); });
    for (const [nome, u, sel] of [['home', '/', '.lead, main p'], ['articolo', '/benvenuto/', '.corpo p'], ['blog', '/blog/', 'main p, .card-titolo'], ['widget', '/tutti-i-widget/', '.ed-testo p']]) {
      const r = await p.goto(B + u, { waitUntil: 'networkidle' });
      ok(`${tema}: ${nome} risponde 200`, r.status() === 200, r.status());
      const c = await contrasto(p, sel);
      ok(`${tema}: ${nome} testo leggibile (contrasto ${c} su almeno 4,5)`, c !== null && c >= 4.5, c);
    }
    await p.goto(B + '/', { waitUntil: 'networkidle' });
    const voci = await p.locator('header nav a').allInnerTexts();
    ok(`${tema}: menu Principale nell'header`, voci.some(v => /blog/i.test(v)) && voci.length >= 3, voci);
    const titolo = await contrasto(p, 'h1');
    ok(`${tema}: titolo della home leggibile (${titolo})`, titolo !== null && titolo >= 3, titolo);
    ok(`${tema}: nessun errore JavaScript`, err.length === 0, err);
    await p.context().close();
  }
  cli('disattiva'); cli('ripristina-stile');
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
