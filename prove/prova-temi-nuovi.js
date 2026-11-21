// Gli otto temi della 2.5.0 (Estate, Inverno, Autunno, Primavera e le tinte piene Giallo, Rosso, Verde, Blu), come un
// visitatore, prima in anteprima da amministratore (?preview=1&tema=<nome>) e poi attivati dal pannello (Editor > Temi):
// home, una pagina, un articolo, il blog, la pagina con tutti i widget e quella con le undici aperture senza foto (se ci
// sono: prove/pagina-widget.php, prove/pagina-hero.php <cartella> "") e la 404, a 1280 e a 390 px.
// Controlla: nessun errore JavaScript, niente scorrimento di lato, lo sfondo esatto delle tinte piene e il contrasto
// (WCAG, almeno 4,5 a 1) di testo, titoli, link, pulsanti e campi dei moduli, misurato sui colori calcolati dal browser
// contro quello che sta davvero dietro ogni scritta (document.elementsFromPoint; un testo sopra una foto non si misura).
// Alla fine rimette il tema di partenza dal pannello (Cosmo, poi "Usa l'aspetto di base").
// uso: node prova-temi-nuovi.js <base> <utente> <password> [temi separati da virgola] [cartella schermate]
//   nell'ambiente, per lavorare su un tema: PAGINE=home,widget (solo quelle), MISURE=1280, SOLO_ANTEPRIMA=1, TUTTI=1 (ogni testo sotto 4,5).
//   con la cartella, salva <cartella>/<tema>.png (la home a 1280 px, applicata dal pannello) e <tema>-intera.png.
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello'); // UX 2026-11: conferme del pannello
const [B, UTENTE, PASSWORD, TEMI = 'estate,inverno,autunno,primavera,giallo,rosso,verde,blu', OUT = ''] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 400) : '')); };

const TINTE = { giallo: 'rgb(255, 255, 0)', rosso: 'rgb(255, 0, 0)', verde: 'rgb(0, 255, 0)', blu: 'rgb(0, 0, 255)' };
const PAGINE = [['home', '/'], ['pagina', '/servizi/'], ['articolo', '/benvenuto/'], ['blog', '/blog/'], ['widget', '/tutti-i-widget/'], ['aperture', '/tutte-le-hero/'], ['404', '/pagina-che-non-esiste-tn/']];
const MISURE = process.env.MISURE ? process.env.MISURE.split(',').map(Number) : [1280, 390];
const MINIMO = 4.5;

// Nel browser: ogni elemento con del testo suo, la categoria, il colore e quello che c'è dietro. Ritorna i peggiori per categoria.
function misura(MINIMO) {
  // Un colore calcolato dal browser: rgb(), rgba() oppure color(srgb r g b / a) (così esce un color-mix()).
  const rgba = (s) => {
    let m = (s || '').match(/color\(srgb([^)]+)\)/);
    if (m) { const v = m[1].split(/[ /]+/).filter(Boolean).map(Number); return { r: v[0] * 255, g: v[1] * 255, b: v[2] * 255, a: v.length > 3 ? v[3] : 1 }; }
    m = (s || '').match(/rgba?\(([^)]+)\)/); if (!m) return null;
    const v = m[1].split(/[ ,/]+/).filter(Boolean).map(Number); return { r: v[0], g: v[1], b: v[2], a: v.length > 3 ? v[3] : 1 };
  };
  const sopra = (c, sotto) => ({ r: c.r * c.a + sotto.r * (1 - c.a), g: c.g * c.a + sotto.g * (1 - c.a), b: c.b * c.a + sotto.b * (1 - c.a), a: 1 });
  const lum = (c) => { const f = (x) => { x /= 255; return x <= .03928 ? x / 12.92 : Math.pow((x + .055) / 1.055, 2.4); }; return .2126 * f(c.r) + .7152 * f(c.g) + .0722 * f(c.b); };
  const rap = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + .05) / (y + .05); };
  // I colori di una sfumatura (con un color-mix() dentro, Chrome li scrive come color(srgb ...)).
  const coloriDi = (img) => (img.match(/rgba?\([^)]+\)|color\(srgb[^)]+\)/g) || []).map(rgba);
  // Gli strati dietro a un punto: lo sfondo dell'elemento e dei suoi antenati, poi quello che sta sotto nel disegno.
  // Ritorna un elenco di sfondi possibili (più d'uno con una sfumatura) oppure 'immagine'.
  const dietro = (el, x, y, senzaSe = false) => {
    const pila = document.elementsFromPoint(x, y);
    if (!pila.length || !(pila.includes(el) || pila.some(e => el.contains(e)))) return 'coperto';
    const strati = [];
    for (const e of [...(senzaSe ? [] : [el]), ...pila.filter(e => e !== el && !el.contains(e))]) {
      if (['IMG', 'VIDEO', 'CANVAS', 'IFRAME', 'PICTURE'].includes(e.tagName) || (e.tagName === 'svg' && e.getBoundingClientRect().width > 60)) return 'immagine';
      const s = getComputedStyle(e);
      if (s.backgroundImage && s.backgroundImage !== 'none') {
        if (/url\(/.test(s.backgroundImage)) return 'immagine';
        const cc = coloriDi(s.backgroundImage);
        if (cc.length) { strati.push(cc); if (cc.every(c => c.a >= .99)) break; }
      }
      const c = rgba(s.backgroundColor);
      if (c && c.a > 0) { strati.push([c]); if (c.a >= .99) break; }
    }
    // dal fondo in su: ogni combinazione delle sfumature (al più poche)
    let basi = [{ r: 255, g: 255, b: 255, a: 1 }];
    for (const st of strati.reverse()) basi = st.flatMap(c => basi.map(b => sopra(c, b))).slice(0, 24);
    return basi;
  };
  const visibile = (el) => { const s = getComputedStyle(el); if (s.visibility === 'hidden' || s.display === 'none' || +s.opacity === 0) return false; const r = el.getBoundingClientRect(); return r.width > 2 && r.height > 2; };
  const categoria = (el) => {
    if (el.matches('input,textarea,select')) return 'campi';
    if (el.closest('.btn,button,[role=button],.ed-pulsante,.ew-btn')) return 'pulsanti';
    if (el.closest('a')) return 'link';
    if (el.closest('h1,h2,h3,h4,h5,h6,.sez-titolo,.card-titolo')) return 'titoli';
    return 'corpo';
  };
  const zona = (el) => el.closest('header,.ed-testata') ? 'header' : el.closest('footer,.ed-piede,.firma-sito') ? 'footer' : 'contenuto';
  const conTesto = (el) => [...el.childNodes].some(t => t.nodeType === 3 && t.textContent.trim().length > 1);
  const campi = [...document.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=submit]):not([type=button]):not([type=range]):not([type=color]):not([type=file]),textarea,select')];
  // .ew-attesa: un'animazione all'entrata non ancora partita (la barra è vuota e la percentuale sta fuori dal riempimento)
  const testi = [...document.body.querySelectorAll('*')].filter(e => !['SCRIPT', 'STYLE', 'NOSCRIPT', 'OPTION', 'svg'].includes(e.tagName) && !e.closest('[aria-hidden=true],.salta,.popup,dialog,.mn-h-pannello,.mn-sotto,details:not([open])>*:not(summary),.ew-attesa') && conTesto(e));
  const peggiori = {}, bassi = []; let misurati = 0, saltati = 0;
  for (const el of [...testi, ...campi]) {
    if (!visibile(el)) continue;
    const st = getComputedStyle(el);
    let fgs = [rgba(st.color)];
    // un titolo a sfumatura (testo trasparente con background-clip:text): contano i colori della sfumatura
    const aSfumatura = fgs[0] && fgs[0].a < .05 && /text/.test(st.backgroundClip || st.webkitBackgroundClip || '');
    if (aSfumatura) fgs = coloriDi(st.backgroundImage).filter(c => c.a > .05);
    if (!fgs.length || !fgs[0] || fgs[0].a < .05) continue;
    el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
    const r = el.getClientRects()[0] || el.getBoundingClientRect();
    const x = Math.min(Math.max(r.left + Math.min(r.width, 40) / 2, 1), innerWidth - 2), y = Math.min(Math.max(r.top + r.height / 2, 1), innerHeight - 2);
    const d = dietro(el, x, y, aSfumatura);
    if (!Array.isArray(d)) { saltati++; continue; }
    misurati++;
    const c = Math.min(...fgs.flatMap(fg => d.map(b => rap(sopra(fg, b), b))));
    const k = zona(el) + ' ' + categoria(el);
    const desc = (el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '') + ' "' + (el.value || el.textContent || '').trim().slice(0, 30) + '"');
    if (c < MINIMO && bassi.length < 60) bassi.push(`${k} ${Math.round(c * 100) / 100} ${desc} fg=${getComputedStyle(el).color} bg=${d.map(b => `rgb(${Math.round(b.r)},${Math.round(b.g)},${Math.round(b.b)})`).slice(0, 2).join(' / ')}`);
    if (!peggiori[k] || c < peggiori[k].c) peggiori[k] = { c: Math.round(c * 100) / 100, el: desc, fg: getComputedStyle(el).color, bg: d.map(b => `rgb(${Math.round(b.r)},${Math.round(b.g)},${Math.round(b.b)})`).slice(0, 2).join(' / ') };
  }
  scrollTo({ top: 0, behavior: 'instant' });
  return { peggiori, misurati, saltati, bassi };
}

(async () => {
  const br = await chromium.launch();
  const admin = await br.newContext({ viewport: { width: 1360, height: 900 } }); const pa = await admin.newPage();
  pnConferme.on(pa, d => d.accept());
  await pa.goto(B + '/admin/login.php'); await pa.fill('#username', UTENTE); await pa.fill('#password', PASSWORD);
  await Promise.all([pa.waitForNavigation(), pa.click('button[type=submit]')]);
  ok('login', !pa.url().includes('login.php'), pa.url());
  // La pagina con tutti i widget c'è? (prove/pagina-widget.php)
  // e le undici aperture senza foto? (prove/pagina-hero.php <cartella> "")
  const conWidget = (await pa.request.get(B + '/tutti-i-widget/')).status() === 200;
  const conAperture = (await pa.request.get(B + '/tutte-le-hero/')).status() === 200;
  const pagine = PAGINE.filter(([nome]) => (nome !== 'widget' || conWidget) && (nome !== 'aperture' || conAperture) && (!process.env.PAGINE || process.env.PAGINE.split(',').includes(nome)));
  if (!conWidget) console.log('--  la pagina /tutti-i-widget/ non c\'è: si salta (prove/pagina-widget.php la crea)');
  if (!conAperture) console.log('--  la pagina /tutte-le-hero/ non c\'è: si salta (prove/pagina-hero.php <cartella> "" la crea, senza foto)');

  await pa.goto(B + '/admin/temi.php', { waitUntil: 'networkidle' });
  // un tema rimasto attivo da una prova interrotta ha "attivo" al posto del pulsante Attiva
  for (const t of TEMI.split(',')) ok(`${t}: c'è in Editor > Temi, con l'anteprima e il pulsante Usa questo tema`, await pa.locator(`#tema-${t} img.tt-immagine`).count() === 1 && await pa.locator(`#tema-${t} button:has-text("Usa questo tema"), #tema-${t} .badge:has-text("attivo")`).count() >= 1);

  const giro = async (tema, modo, ctx) => {
    const minimi = {};
    for (const w of MISURE) {
      const p = await ctx.newPage(); await p.setViewportSize({ width: w, height: 900 });
      const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 160)));
      p.on('console', m => { if (m.type() === 'error' && !/favicon|404 \(Not Found\)/.test(m.text())) err.push(m.text().slice(0, 160)); });
      for (const [nome, u] of pagine) {
        const url = B + u + (modo === 'anteprima' ? '?preview=1&tema=' + tema : '');
        const r = await p.goto(url, { waitUntil: 'networkidle' });
        const atteso = nome === '404' ? 404 : 200;
        const dove = `${tema} ${modo} ${nome} ${w}`;
        const stato = r.status();
        const usato = await p.evaluate(() => { const l = document.getElementById('tema-css'); return l ? l.getAttribute('href') : ''; });
        const largo = await p.evaluate(() => document.documentElement.scrollWidth - innerWidth);
        const sfondo = await p.evaluate(() => getComputedStyle(document.body).backgroundColor);
        await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 300) { scrollTo({ top: y, behavior: 'instant' }); await new Promise(r => setTimeout(r, 90)); } scrollTo({ top: 0, behavior: 'instant' }); });
        await p.waitForTimeout(1500);
        const m = await p.evaluate(misura, MINIMO);
        const bassi = Object.entries(m.peggiori).filter(([, v]) => v.c < MINIMO);
        ok(`${dove}: risposta ${atteso}, CSS del tema, niente scorrimento di lato`, stato === atteso && usato.includes('/temi/' + tema + '/') && largo <= 1, { stato, usato, largo });
        if (TINTE[tema]) ok(`${dove}: sfondo della pagina ${TINTE[tema]}`, sfondo === TINTE[tema], sfondo);
        ok(`${dove}: contrasto almeno ${MINIMO} (${m.misurati} testi misurati, ${m.saltati} su foto o coperti)`, bassi.length === 0 && m.misurati > 5, bassi.length ? Object.fromEntries(bassi) : '');
        if (process.env.TUTTI && m.bassi.length) console.log('    ' + m.bassi.join('\n    '));
        for (const [k, v] of Object.entries(m.peggiori)) if (!minimi[k] || v.c < minimi[k].c) minimi[k] = { ...v, dove: `${nome} ${w}` };
        if (OUT && modo === 'applicato' && nome === 'home' && w === 1280) {
          await p.evaluate(() => scrollTo({ top: 0, behavior: 'instant' })); await p.waitForTimeout(300);
          await p.screenshot({ path: `${OUT}/${tema}.png` });
          await p.screenshot({ path: `${OUT}/${tema}-intera.png`, fullPage: true });
        }
      }
      ok(`${tema} ${modo} ${w}: nessun errore JavaScript`, err.length === 0, err);
      await p.close();
    }
    return minimi;
  };

  const riassunto = {};
  for (const t of TEMI.split(',')) {
    // 1. Anteprima da amministratore: niente si attiva
    await giro(t, 'anteprima', admin);
    if (process.env.SOLO_ANTEPRIMA) continue;
    // 2. Attivato dal pannello, guardato da un visitatore
    await pa.goto(B + '/admin/temi.php', { waitUntil: 'networkidle' });
    await Promise.all([pa.waitForNavigation(), pa.click(`#tema-${t} button:has-text("Usa questo tema")`)]);
    const msg = await pa.locator('.flash').first().innerText().catch(() => '');
    ok(`${t}: attivato dal pannello`, /attivo/i.test(msg) && await pa.locator(`#tema-${t} .badge`, { hasText: 'attivo' }).count() === 1, msg.slice(0, 120));
    const vis = await br.newContext();
    riassunto[t] = await giro(t, 'applicato', vis);
    await vis.close();
  }

  // 3. Il tema di partenza: Cosmo (i valori di partenza) e poi l'aspetto di base, come dopo l'installazione
  await pa.goto(B + '/admin/temi.php', { waitUntil: 'networkidle' });
  await Promise.all([pa.waitForNavigation(), pa.click('#tema-cosmo button:has-text("Usa questo tema")')]);
  await Promise.all([pa.waitForNavigation(), pa.click('#tema-base button:has-text("Usa l\'aspetto di base")')]);
  ok('alla fine: di nuovo l\'aspetto di base', await pa.locator('#tema-base .badge', { hasText: 'attivo' }).count() === 1);
  const vis = await (await br.newContext()).newPage(); await vis.goto(B + '/', { waitUntil: 'networkidle' });
  ok('alla fine: il sito ha lo sfondo di partenza', await vis.evaluate(() => getComputedStyle(document.body).backgroundColor) === 'rgb(6, 10, 23)');

  console.log('\nContrasto più basso per tema (applicato dal pannello):');
  for (const [t, m] of Object.entries(riassunto)) {
    const v = Object.entries(m).sort((a, b) => a[1].c - b[1].c);
    console.log(`  ${t}: ` + v.slice(0, 4).map(([k, x]) => `${k} ${x.c} (${x.dove}, ${x.el})`).join('; '));
  }
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})();
