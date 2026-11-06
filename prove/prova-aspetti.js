// Gli aspetti del pannello (1.4.0) provati come un utente: per ognuno dei quattro (Aria, Bottega, Cosmo, Lavagna) lo sceglie
// in "Il mio accesso", poi apre tutte le pagine del pannello (le voci del menu, le loro schede, la scheda di un articolo,
// l'editor, Aggiungi sempre alla fine della 1.6.0) e per ognuna controlla: attributo data-pannello scritto dal server (nessun JavaScript), nessun errore PHP o
// JavaScript, contrasto di ogni testo visibile (WCAG: 4,5 a 1, 3 a 1 per il testo grande), e fa una foto.
// Poi: le variabili di sempre definite in tutti e quattro, il login e le pagine senza utente in Aria, i colori rimasti
// scritti a mano nei fogli e nelle pagine del pannello (cms/admin), e alla fine rimette Aria.
// uso: node prove/prova-aspetti.js <base> <utente> <password> <cartella foto> [cartella cms da scandire, predefinita ../cms]
const { chromium } = require('playwright');
const fs = require('fs'), path = require('path');
const [B, U, P, D, CMS = path.join(__dirname, '..', 'cms')] = process.argv.slice(2);
const ASPETTI = ['aria', 'bottega', 'cosmo', 'lavagna'];
const VECCHI = ['--bg', '--surface', '--text', '--text-muted', '--border', '--coral', '--coral-light', '--coral-hover', '--sage', '--sage-light', '--mustard', '--mustard-light', '--sidebar', '--sidebar-hover', '--radius', '--font'];
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 20000) : '')); };

// Il contrasto di ogni testo visibile della pagina. Lo sfondo e quello pieno piu vicino risalendo i genitori, con le
// trasparenze composte; un testo sopra un'immagine o un gradiente non si misura (non si sa cosa c'e dietro), come i
// controlli spenti e le zone che mostrano il sito (anteprime, tela dell'editor, miniature della scelta).
const misuraContrasto = (p) => p.evaluate(() => {
  const ESCLUSI = '.asp-mini, iframe, .tm-anteprima, .tt-pulsanti, .tt-ig, #ig-preview, .dg-cornice, .dg-swatch, .ed-tela-cornice, .ed-mini, .kit-disegno, .sr-solo, .ql-picker-options, option, select, [aria-hidden=true]';
  const rgba = (s) => { const m = s.match(/rgba?\(([^)]+)\)/); if (!m) return null; const v = m[1].split(/[ ,/]+/).filter(Boolean).map(Number); return { r: v[0], g: v[1], b: v[2], a: v.length > 3 ? v[3] : 1 }; };
  const sopra = (c, f) => ({ r: c.r * c.a + f.r * (1 - c.a), g: c.g * c.a + f.g * (1 - c.a), b: c.b * c.a + f.b * (1 - c.a), a: 1 });
  const lum = (c) => { const f = (x) => { x /= 255; return x <= .03928 ? x / 12.92 : Math.pow((x + .055) / 1.055, 2.4); }; return .2126 * f(c.r) + .7152 * f(c.g) + .0722 * f(c.b); };
  const sfondo = (el) => {
    const strati = [];
    for (let e = el; e; e = e.parentElement) {
      const cs = getComputedStyle(e);
      if (cs.backgroundImage && cs.backgroundImage !== 'none') return null;
      const c = rgba(cs.backgroundColor);
      if (c && c.a > 0) { strati.push(c); if (c.a >= 1) break; }
    }
    let base = { r: 255, g: 255, b: 255, a: 1 };
    for (let i = strati.length - 1; i >= 0; i--) base = sopra(strati[i], base);
    return base;
  };
  const male = [], visti = new Set();
  const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  let misurati = 0;
  while (w.nextNode()) {
    const t = w.currentNode; if (!t.textContent.trim()) continue;
    const el = t.parentElement; if (!el || el.closest(ESCLUSI)) continue;
    const r = el.getBoundingClientRect(); if (!r.width || !r.height) continue;
    const cs = getComputedStyle(el); if (cs.visibility !== 'visible' || cs.display === 'none') continue;
    let spento = el.closest(':disabled, [disabled]') !== null;
    for (let e = el; e && !spento; e = e.parentElement) if (parseFloat(getComputedStyle(e).opacity) < 1) spento = true;
    if (spento) continue;
    let fg = rgba(cs.color); if (!fg || fg.a === 0) continue;
    const bg = sfondo(el); if (!bg) continue;
    if (fg.a < 1) fg = sopra(fg, bg);
    const [a, b] = [lum(fg), lum(bg)].sort((x, y) => y - x);
    const c = (a + .05) / (b + .05);
    const px = parseFloat(cs.fontSize), peso = parseInt(cs.fontWeight, 10) || 400;
    const soglia = px >= 24 || (px >= 18.66 && peso >= 700) ? 3 : 4.5;
    misurati++;
    if (c < soglia - 0.005) {
      const chiave = el.tagName + '.' + (el.className && el.className.baseVal === undefined ? el.className : '') + ':' + t.textContent.trim().slice(0, 24);
      if (visti.has(chiave)) continue; visti.add(chiave);
      male.push(`${c.toFixed(2)}<${soglia} "${t.textContent.trim().slice(0, 40)}" <${el.tagName.toLowerCase()} class="${typeof el.className === 'string' ? el.className : ''}"> ${cs.color} su rgb(${Math.round(bg.r)},${Math.round(bg.g)},${Math.round(bg.b)})`);
    }
  }
  return { misurati, male };
});

// I colori scritti a mano rimasti nel pannello. Ammessi: aspetti.css (dove stanno i colori), i fogli di altri (Quill,
// la tela dell'editor che mostra il sito), le righe segnate "fisso" (etichette sopra le foto, colori di categoria, il QR...),
// e i colori che sono dati e non stile (valori predefiniti dello Stile del sito, colori delle infografiche del sito).
function coloriAMano() {
  const trovati = [];
  const ammessiFile = /aspetti\.css$|quill|anteprima\.css$|design\.js$|infografica-modal\.php$/;
  // dati, non stile: i valori di partenza di un selettore di colore e le tavolozze del sito mostrate come anteprima
  const ammessiRiga = /fisso|v\.colore|\$bordo = |value = \/\^#|'#888888'|'#ffffff'\]|\$testate =|\$corpi =|'ciano_viola'|\?\? '#|'#fff'\) \?>|'#e6ebf5' :|preg_match\('\/\^#|<code>#|placeholder=|es\. |#\$\{|#\$idx|&#|#[0-9a-f]{3,6}\b(?=[^;{}]*\])/i;
  const giro = (d) => {
    for (const f of fs.readdirSync(d, { withFileTypes: true })) {
      const p = path.join(d, f.name);
      if (f.isDirectory()) { giro(p); continue; }
      if (!/\.(css|php|js)$/.test(f.name) || ammessiFile.test(p)) continue;
      fs.readFileSync(p, 'utf8').split('\n').forEach((riga, i) => {
        if (ammessiRiga.test(riga)) return;
        const m = riga.match(/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b(?![0-9a-zA-Z_-])|(?<![\w-])(?:white|black)(?=\s*[;}"'!])|rgba?\(\s*\d/);
        if (m && !/^\s*(\/\/|\*|\/\*)/.test(riga)) trovati.push(`${path.relative(CMS, p)}:${i + 1} ${m[0]}`);
      });
    }
  };
  giro(path.join(CMS, 'admin'));
  return trovati;
}

(async () => {
  fs.mkdirSync(D, { recursive: true });
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1360, height: 900 } });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 160))); p.on('console', m => { if (m.type() === 'error' && !/favicon/.test(m.text())) err.push(m.text().slice(0, 160)); });

  // Senza utente: login e password dimenticata sono Aria, e il contrasto regge
  await p.goto(B + '/admin/login.php', { waitUntil: 'networkidle' });
  ok('login: aspetto Aria scritto nella pagina', await p.getAttribute('html', 'data-pannello') === 'aria');
  let cm = await misuraContrasto(p); ok(`login: contrasto (${cm.misurati} testi)`, cm.male.length === 0, cm.male);
  await p.screenshot({ path: `${D}/login.png` });
  await p.goto(B + '/admin/password-dimenticata.php', { waitUntil: 'networkidle' });
  ok('password dimenticata: Aria', await p.getAttribute('html', 'data-pannello') === 'aria');
  cm = await misuraContrasto(p); ok('password dimenticata: contrasto', cm.male.length === 0, cm.male);

  await p.goto(B + '/admin/login.php'); await p.fill('#username', U); await p.fill('#password', P);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok('login riuscito', p.url().includes('/admin/') && !p.url().includes('login.php'), p.url());

  // Le pagine: le voci del menu (con le sottovoci) e quelle che si aprono da dentro.
  const menu = await p.$$eval('.admin-nav a[href], .admin-sidebar-footer a[href]', as => as.map(a => a.getAttribute('href')).filter(h => h && !/^https?:|logout\.php/.test(h) && !h.endsWith('/')));
  await p.goto(B + '/admin/contenuti.php?tipo=articoli');
  const articolo = await p.$eval('a.ct-nome', a => a.getAttribute('href')).catch(() => '');
  const pagine = [...new Set([...menu, articolo, 'newsletter-campagna.php', 'newsletter.php?scheda=liste', 'newsletter.php?scheda=impostazioni', 'popup.php?id=0&nuovo=1', 'seo.php?scheda=prestazioni', 'utenti.php', 'password.php', 'account.php', 'informazioni.php', 'fine-articolo.php'].filter(Boolean))];
  const editor = menu.find(h => /^editor\.php\?id=\d+/.test(h)) || '';

  for (const asp of ASPETTI) {
    // La scelta, come un utente: Il mio accesso, clic sulla miniatura, "Usa questo aspetto"
    await p.goto(B + '/admin/account.php', { waitUntil: 'networkidle' });
    await p.click(`label.asp-voce:has(input[value=${asp}])`);
    await Promise.all([p.waitForNavigation(), p.click('button:has-text("Usa questo aspetto")')]);
    ok(`${asp}: scelto in Il mio accesso, messaggio di conferma`, (await p.locator('.flash-success').innerText().catch(() => '')).toLowerCase().includes(asp));
    ok(`${asp}: la radice ha data-pannello="${asp}"`, await p.getAttribute('html', 'data-pannello') === asp);
    const crudo = await (await p.request.get(B + '/admin/index.php')).text();
    ok(`${asp}: l'attributo arriva dal server, nell'HTML (nessun JavaScript)`, crudo.includes(`<html lang="it" data-pannello="${asp}">`));
    const vars = await p.evaluate((v) => v.map(x => [x, getComputedStyle(document.documentElement).getPropertyValue(x).trim()]), VECCHI);
    ok(`${asp}: le ${VECCHI.length} variabili di sempre sono definite`, vars.every(([, x]) => x !== ''), vars.filter(([, x]) => x === ''));
    await p.screenshot({ path: `${D}/${asp}-account.png`, fullPage: true });

    let pagOk = 0, pagMale = [], contrMale = [];
    for (const u of pagine) {
      err.length = 0;
      const r = await p.goto(B + '/admin/' + u, { waitUntil: 'networkidle' }).catch(e => null);
      await p.waitForTimeout(250);
      const t = await p.locator('body').innerText().catch(() => '');
      const att = await p.getAttribute('html', 'data-pannello').catch(() => '');
      const rotta = !r || r.status() >= 400 || /Fatal error|Warning:|Notice:|Parse error|Uncaught/.test(t) || err.length || att !== asp;
      if (rotta) pagMale.push(`${u} ${r ? r.status() : 'x'} ${att} ${err.join(' | ')}`); else pagOk++;
      const c = await misuraContrasto(p);
      if (c.male.length) contrMale.push(`${u}: ${c.male.join(' ; ')}`);
      await p.screenshot({ path: `${D}/${asp}-${u.replace(/[^a-z0-9]+/gi, '_').replace(/_+$/, '')}.png` });
    }
    ok(`${asp}: ${pagOk} pagine su ${pagine.length} aperte con l'aspetto giusto e senza errori`, pagMale.length === 0, pagMale);
    ok(`${asp}: contrasto di ogni testo visibile in tutte le pagine`, contrMale.length === 0, contrMale);

    // L'editor: l'interfaccia segue l'aspetto, la tela no
    if (editor) {
      err.length = 0;
      await p.goto(B + '/admin/' + editor, { waitUntil: 'networkidle' }); await p.waitForTimeout(1200);
      ok(`${asp}: editor con data-pannello="${asp}"`, await p.getAttribute('html', 'data-pannello') === asp);
      const col = await p.evaluate(() => [getComputedStyle(document.querySelector('.ed-pannello')).backgroundColor, getComputedStyle(document.documentElement).getPropertyValue('--surface').trim()]);
      ok(`${asp}: il pannello dell'editor ha il fondo dell'aspetto`, col[0] !== '' && col[1] !== '', col);
      const c = await misuraContrasto(p);
      ok(`${asp}: editor, contrasto (${c.misurati} testi)`, c.male.length === 0, c.male);
      ok(`${asp}: editor senza errori JavaScript`, err.length === 0, err);
      await p.screenshot({ path: `${D}/${asp}-editor.png` });
      // un widget selezionato, per vedere i controlli
      const f = p.frameLocator('.ed-tela iframe');
      await f.locator('[data-ed-tipo="hero"], [data-ed-tipo="titolo"]').first().click().catch(() => {});
      await p.waitForTimeout(400);
      await p.click('.ed-schede-pannello button[data-pannello="modifica"]').catch(() => {});
      await p.waitForTimeout(300);
      ok(`${asp}: editor, un widget scelto apre i suoi controlli`, await p.locator('.ed-controllo:visible').count() > 0);
      const c2 = await misuraContrasto(p);
      ok(`${asp}: editor con un widget aperto, contrasto`, c2.male.length === 0, c2.male);
      await p.screenshot({ path: `${D}/${asp}-editor-widget.png` });
    }
  }

  // Il colore dei pulsanti diverso nei quattro (gli aspetti non sono lo stesso con un altro nome)
  const fondi = await p.evaluate((a) => a.map(x => { const d = document.createElement('div'); d.dataset.pannello = x; document.body.append(d); const v = getComputedStyle(d).getPropertyValue('--bg').trim() + getComputedStyle(d).getPropertyValue('--coral').trim(); d.remove(); return v; }), ASPETTI);
  ok('i quattro aspetti hanno fondi e accenti diversi', new Set(fondi).size === 4, fondi);

  // Torna ad Aria
  await p.goto(B + '/admin/account.php'); await p.click('label.asp-voce:has(input[value=aria])');
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Usa questo aspetto")')]);
  ok('di nuovo Aria alla fine', await p.getAttribute('html', 'data-pannello') === 'aria');

  const mano = coloriAMano();
  ok(`nessun colore scritto a mano rimasto nel pannello (${CMS}/admin)`, mano.length === 0, mano);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})();
