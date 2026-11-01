// Le quindici infografiche disegnate (2.9.0): imbuto, piramide, matrice, ciclo, ciambella, cerchi che si toccano, ragnatela,
// lancetta, iceberg, omini, viaggio, bilancia, mappa di calore, cerchi concentrici, barre al cento per cento.
// Come le vede un visitatore al computer (1280) e al telefono (390): i numeri calcolati giusti, i dati per chi non vede,
// niente scritte sovrapposte o fuori dal disegno, niente scorrimento di lato, scritte grandi abbastanza al telefono,
// valori estremi senza errori, contrasto del testo misurato sui pixel (senza tema, Aria, Cosmo, Autunno, corpo scuro).
// Poi dall'editor: le quindici si aggiungono dalla tavolozza, si cambiano le voci, l'editor segnala la ciambella che non
// fa 100, si pubblica e il sito le mostra. Alla fine la pagina della prova dell'editor si cancella.
// Prima: un sito installato (prova-kit.js attivita), poi
//   php prove/pagina-infografiche-15.php <cartella CMS> normali|estremi|notte   (la prova le rifà comunque)
// uso: node prove/prova-infografiche-15.js <base> <cartella CMS> <utente> <password> [cartella delle foto]
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const fs = require('fs');
const [B, DIR, UTENTE, PASSWORD, FOTO] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const pagina = modo => execFileSync('php', [path.join(__dirname, 'pagina-infografiche-15.php'), DIR, modo], { encoding: 'utf8' });
const TIPI = ['imbuto', 'piramide', 'matrice', 'ciclo', 'ciambella', 'venn', 'radar', 'lancetta', 'iceberg', 'omini', 'viaggio', 'bilancia', 'calore', 'cerchi', 'barre100'];

// I testi di ogni SVG visibile: niente sovrapposizioni fra scritte, niente scritte fuori dal disegno, la misura vera al telefono.
async function geometria(p) {
  return p.evaluate(() => {
    const out = { sovrapposte: [], fuori: [], minimo: 99, svg: 0 };
    for (const s of document.querySelectorAll('.igs svg')) {
      const rs = s.getBoundingClientRect();
      if (!rs.width || getComputedStyle(s).display === 'none') continue;
      out.svg++;
      const scala = rs.width / s.viewBox.baseVal.width;
      const w = s.closest('.ed-widget').className.match(/ed-w-ig_(\w+)/)[1];
      const t = [...s.querySelectorAll('text')].map(x => ({ r: x.getBoundingClientRect(), s: w + ': ' + x.textContent, f: parseFloat(x.getAttribute('font-size')) * scala })).filter(x => x.s.trim() !== '');
      for (const x of t) {
        out.minimo = Math.min(out.minimo, x.f);
        if (x.r.left < rs.left - 3 || x.r.right > rs.right + 3 || x.r.top < rs.top - 3 || x.r.bottom > rs.bottom + 3) out.fuori.push(x.s.slice(0, 50));
      }
      for (let i = 0; i < t.length; i++) for (let j = i + 1; j < t.length; j++) {
        // il riquadro di una scritta ha sopra e sotto lo spazio delle lettere alte e di quelle con la coda: si toglie
        // (15% della misura del carattere sopra, 20% sotto) e si conta solo una sovrapposizione vera
        const g = x => ({ left: x.r.left, right: x.r.right, top: x.r.top + .15 * x.f, bottom: x.r.bottom - .2 * x.f });
        const a = g(t[i]), b = g(t[j]);
        const w = Math.min(a.right, b.right) - Math.max(a.left, b.left), h = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
        if (w > 1 && h > 1) out.sovrapposte.push(t[i].s.slice(0, 40) + ' / ' + t[j].s.slice(0, 40));
      }
    }
    out.largo = document.documentElement.scrollWidth - innerWidth;
    out.minimo = Math.round(out.minimo * 10) / 10;
    return out;
  });
}

// Contrasto WCAG di ogni scritta delle infografiche, misurato sui pixel: si nascondono le scritte, si fotografa la pagina e
// si legge il colore dietro ogni scritta in sei punti (si tiene il peggiore). Le scritte con l'alone (il numero della lancetta)
// hanno dietro il colore dell'alone.
async function contrasti(p, cornice = false) {
  const testi = await p.evaluate((cornice) => {
    const out = [];
    const sx = scrollX, sy = scrollY;
    const punti = r => [[.2, .35], [.5, .35], [.8, .35], [.2, .65], [.5, .65], [.8, .65]].map(([a, b]) => [Math.round(r.left + sx + r.width * a), Math.round(r.top + sy + r.height * b)]);
    for (const s of document.querySelectorAll('.igs svg')) {
      if (!s.getBoundingClientRect().width || getComputedStyle(s).display === 'none') continue;
      for (const t of s.querySelectorAll('text')) {
        if (!t.textContent.trim()) continue;
        const cs = getComputedStyle(t);
        out.push({ s: t.textContent.slice(0, 40), c: cs.fill, alone: t.classList.contains('igs-alone') ? cs.stroke : '', p: punti(t.getBoundingClientRect()), w: s.closest('.ed-widget').className.match(/ed-w-ig_(\w+)/)[1] });
      }
    }
    if (cornice) for (const e of document.querySelectorAll('.igx .igx-occhiello, .igx .igx-titolo, .igx .igx-firma')) {
      const r = document.createRange(); r.selectNodeContents(e); const q = r.getBoundingClientRect();
      if (q.width) out.push({ s: e.textContent.slice(0, 40), c: getComputedStyle(e).color, alone: '', p: punti(q), w: 'cornice:' + e.className.split(' ')[0], grande: e.classList.contains('igx-titolo') });
    }
    return out;
  }, cornice);
  await p.addStyleTag({ content: '.igs text{fill:transparent!important}.igs text:not(.igs-alone){stroke:transparent!important}.igx .igx-occhiello,.igx .igx-titolo,.igx .igx-firma{color:transparent!important}' });
  await p.waitForTimeout(200);
  const png = (await p.screenshot({ fullPage: true })).toString('base64');
  const ris = await p.evaluate(async ({ png, testi }) => {
    const img = new Image(); img.src = 'data:image/png;base64,' + png; await img.decode();
    const c = document.createElement('canvas'); c.width = img.width; c.height = img.height;
    const g = c.getContext('2d'); g.drawImage(img, 0, 0);
    const lum = ([r, gg, b]) => [r, gg, b].map(v => { v /= 255; return v <= .04045 ? v / 12.92 : Math.pow((v + .055) / 1.055, 2.4); }).reduce((a, v, i) => a + v * [.2126, .7152, .0722][i], 0);
    // "color(srgb 1 0 0)" (colori calcolati dal foglio, da 0 a 1) oppure "rgb(255, 0, 0)"
    const rgb = s => { const v = s.match(/-?[\d.]+/g).slice(0, 3).map(Number); return /^color\(srgb/.test(s) ? v.map(x => Math.round(Math.max(0, Math.min(1, x)) * 255)) : v; };
    const cr = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + .05) / (Math.min(x, y) + .05); };
    return testi.map(t => {
      const col = rgb(t.c);
      let min = 99;
      for (const [x, y] of t.p) {
        const fondo = t.alone ? rgb(t.alone) : [...g.getImageData(Math.min(c.width - 1, Math.max(0, x)), Math.min(c.height - 1, Math.max(0, y)), 1, 1).data].slice(0, 3);
        min = Math.min(min, cr(col, fondo));
      }
      return { w: t.w, s: t.s, c: Math.round(min * 100) / 100, grande: !!t.grande };
    });
  }, { png, testi });
  return ris;
}

(async () => {
  const br = await chromium.launch();
  const err = [];
  const contesto = async (w, h = 900) => { const c = await br.newContext({ viewport: { width: w, height: h }, reducedMotion: 'reduce' }); await c.route(u => !u.href.startsWith(B), r => r.abort()); return c; };
  const nuova = async c => { const p = await c.newPage(); p.on('pageerror', e => err.push(e.message.slice(0, 200))); p.on('dialog', d => { if (/^Pubblicare questa versione/.test(d.message())) return d.accept(); err.push('finestra: ' + d.message()); d.dismiss(); }); p.on('console', m => { if (m.type() === 'error' && !/ERR_FAILED|net::|Failed to load resource/.test(m.text())) err.push(m.text().slice(0, 200)); }); return p; };
  for (const m of ['normali', 'estremi', 'notte']) pagina(m);

  // 1. Al computer, con i valori di partenza.
  const pc = await nuova(await contesto(1280));
  const r = await pc.goto(B + '/infografiche-15/', { waitUntil: 'networkidle' });
  ok('la pagina con le quindici risponde 200', r.status() === 200);
  const conta = await pc.evaluate(T => T.map(t => [t, document.querySelectorAll('.ed-w-ig_' + t + ' figure.igs svg[role=img]').length]), TIPI);
  ok('ci sono tutte e quindici, ognuna con le due versioni del disegno (larga e stretta)', conta.every(([, k]) => k === 2), conta.filter(([, k]) => k !== 2));
  ok('il foglio delle infografiche si carica una volta sola', await pc.locator('link[href*="infografiche-svg.css"]').count() === 1);
  const vis = await pc.evaluate(() => [...document.querySelectorAll('.igs svg')].filter(s => getComputedStyle(s).display !== 'none').map(s => s.classList.contains('igs-largo') ? 'L' : 'S').join(''));
  ok('al computer si vede la versione larga di tutte', vis === 'L'.repeat(15), vis);
  const aria = await pc.evaluate(T => Object.fromEntries(T.map(t => [t, (document.querySelector('.ed-w-ig_' + t + ' .igs-largo') || {}).getAttribute?.('aria-label') || ''])), TIPI);
  const attesi = {
    imbuto: ['Visite al sito 1.200', 'Contatti 340 (resa 28%)', 'Appuntamenti 85 (resa 25%)', 'Clienti 32 (resa 38%)'],
    piramide: ['Identità: chi sono', 'Ambiente: dove e con chi'], matrice: ['IMPORTANTE e URGENTE: Fai subito', 'Elimina (Le riunioni senza scopo)'],
    ciclo: ['Pianifica, poi Fai, poi Verifica, poi Correggi', 'Un giro al mese'], ciambella: ['Passaparola 45%', 'Altro 10%'],
    venn: ['Ciò che ami', 'Dove si incontrano: Il tuo lavoro'], radar: ['Energia: prima 4, Dopo 3 mesi 8,8', 'Lavoro: prima 3,5'],
    lancetta: ['8,6 su una scala da 0 a 10, nella zona verde'], iceberg: ['Il risultato che tutti vedono', 'Le rinunce'],
    omini: ['7 su 10: clienti arrivano dal passaparola'], viaggio: ['1. La chiamata', '5. Il ritorno (la meta)'],
    bilancia: ['pende a sinistra', 'PRO: Più clienti; Meno attesa; Prezzo chiaro'], calore: ['8-10: Lun 1, Mar 1', '18-20: Lun 3, Mar 4'],
    cerchi: ['Cosa: i servizi che vendi', 'Perché: la ragione per cui esisti'], barre100: ['Prima: Lavoro 55%, Urgenze 25%', 'Dopo 3 mesi: Lavoro 45%'],
  };
  const mancano = Object.entries(attesi).flatMap(([t, l]) => l.filter(x => !aria[t].includes(x)).map(x => t + ': ' + x));
  ok('chi non vede sente tutti i dati (aria-label con i numeri calcolati: resa dell\'imbuto, zona della lancetta, inclinazione della bilancia)', mancano.length === 0, mancano);
  const conti = await pc.evaluate(() => {
    const w = t => document.querySelector('.ed-w-ig_' + t + ' .igs-largo');
    const testi = t => [...w(t).querySelectorAll('text')].map(x => x.textContent);
    const bil = [...w('bilancia').querySelectorAll('line.igs-linea')][0];
    const barre = [...w('barre100').querySelectorAll('rect.igs-p')].slice(0, 4).map(x => +x.getAttribute('width'));
    return {
      imbuto: w('imbuto').querySelectorAll('polygon').length, resa: testi('imbuto').filter(x => /%$/.test(x)),
      piramide: w('piramide').querySelectorAll('polygon').length, matrice: w('matrice').querySelectorAll('rect.igs-tinta').length,
      ciclo: [w('ciclo').querySelectorAll('circle').length, w('ciclo').querySelectorAll('polygon.igs-pieno').length], centro: testi('ciambella').includes('45%'),
      venn: w('venn').querySelectorAll('circle.igs-tinta').length, radar: w('radar').querySelectorAll('circle.igs-p2').length,
      lancetta: testi('lancetta').includes('8,6'), iceberg: testi('iceberg').filter(x => ['Anni di pratica', 'Le rinunce'].includes(x)).length,
      omini: [w('omini').querySelectorAll('g .igs-p').length, w('omini').querySelectorAll('.igs-spento').length],
      viaggio: [w('viaggio').querySelectorAll('circle.igs-anello-carta').length, w('viaggio').querySelectorAll('polygon.igs-p').length],
      bilancia: +bil.getAttribute('y1') > +bil.getAttribute('y2'), calore: w('calore').querySelectorAll('rect title').length,
      cerchi: w('cerchi').querySelectorAll('g > circle').length, barre: barre.map(x => Math.round(x / barre.reduce((a, b) => a + b, 0) * 100)),
    };
  });
  ok('imbuto: 4 gradini e la resa calcolata 28%, 25%, 38%', conti.imbuto === 4 && conti.resa.join() === '28%,25%,38%', conti);
  ok('piramide 5 livelli, matrice 4 quadranti, ciclo 4 tappe con 4 frecce', conti.piramide === 5 && conti.matrice === 4 && conti.ciclo.join() === '4,4');
  ok('ciambella con il 45% al centro, venn con 3 cerchi, ragnatela con 6 punti del "dopo"', conti.centro && conti.venn === 3 && conti.radar === 6);
  ok('lancetta 8,6; iceberg con le voci; omini 7 colorati su 10', conti.lancetta && conti.iceberg === 2 && conti.omini.join() === '14,6', conti.omini);
  ok('viaggio: 4 tappe numerate e la bandiera sulla quinta; bilancia che pende dalla parte dei pro (3 contro 2)', conti.viaggio.join() === '4,1' && conti.bilancia, conti.viaggio);
  ok('mappa di calore 6 x 7 caselle, cerchi concentrici 3, barre al cento per cento larghe come le percentuali (55 25 5 15)', conti.calore === 42 && conti.cerchi === 3 && conti.barre.join() === '55,25,5,15', conti.barre);
  let g = await geometria(pc);
  ok('al computer: nessuna scritta sovrapposta né fuori dal disegno, niente scorrimento di lato', g.sovrapposte.length === 0 && g.fuori.length === 0 && g.largo <= 0 && g.svg === 15, g);
  if (FOTO) { fs.mkdirSync(FOTO, { recursive: true }); for (let i = 0; i < 15; i++) await pc.locator('.igx').nth(i).screenshot({ path: path.join(FOTO, `${String(i + 1).padStart(2, '0')}-${TIPI[i]}-1280.png`) }); }
  ok('le note per l\'editor non si vedono sul sito', await pc.evaluate(() => [...document.querySelectorAll('.igx .ed-vuoto')].every(e => getComputedStyle(e).display === 'none')));

  // 2. Al telefono.
  const tel = await nuova(await contesto(390, 844));
  await tel.goto(B + '/infografiche-15/', { waitUntil: 'networkidle' });
  const visT = await tel.evaluate(() => [...document.querySelectorAll('.igs svg')].filter(s => getComputedStyle(s).display !== 'none').map(s => s.classList.contains('igs-largo') ? 'L' : 'S').join(''));
  ok('al telefono si vede la versione stretta di tutte', visT === 'S'.repeat(15), visT);
  g = await geometria(tel);
  ok('al telefono: nessuna scritta sovrapposta né fuori, niente scorrimento di lato', g.sovrapposte.length === 0 && g.fuori.length === 0 && g.largo <= 0, g);
  ok('al telefono le scritte più piccole restano di almeno 10 px', g.minimo >= 10, g.minimo);
  if (FOTO) for (let i = 0; i < 15; i++) await tel.locator('.igx').nth(i).screenshot({ path: path.join(FOTO, `${String(i + 1).padStart(2, '0')}-${TIPI[i]}-390.png`) });
  const t768 = await nuova(await contesto(768));
  await t768.goto(B + '/infografiche-15/', { waitUntil: 'networkidle' });
  g = await geometria(t768);
  ok('a 768 px: nessuna scritta sovrapposta né fuori, niente scorrimento di lato', g.sovrapposte.length === 0 && g.fuori.length === 0 && g.largo <= 0, g);

  // 3. Valori estremi: minimi, massimi, testi lunghi, parole lunghissime, zero voci, totali sbagliati, colori liberi.
  for (const [w, c] of [[1280, pc], [390, tel]]) {
    await c.goto(B + '/infografiche-15-estremi/', { waitUntil: 'networkidle' });
    g = await geometria(c);
    ok(`valori estremi a ${w} px: nessuna scritta sovrapposta né fuori, niente scorrimento di lato`, g.sovrapposte.length === 0 && g.fuori.length === 0 && g.largo <= 0, g);
  }
  const est = await pc.evaluate(() => {
    const ws = [...document.querySelectorAll('.ed-widget')];
    const w = (t, i = 0) => ws.filter(x => x.classList.contains('ed-w-ig_' + t))[i];
    return {
      imbuto: w('imbuto').querySelectorAll('.igs-largo polygon').length, imbutoVuoto: !w('imbuto', 1).querySelector('svg'),
      ciambella: [...w('ciambella').querySelectorAll('.ed-vuoto')].map(e => e.textContent).join(' | '),
      omini: [w('omini', 1).querySelectorAll('.igs-largo g .igs-p').length, w('omini', 1).querySelectorAll('.igs-largo .igs-spento').length],
      calore: w('calore').querySelectorAll('.igs-largo rect title').length, radar: w('radar').querySelectorAll('.igs-largo circle.igs-p2').length,
      lancetta: [...w('lancetta').querySelectorAll('.ed-vuoto')].map(e => e.textContent).join(' | '),
      barre: [...w('barre100', 1).querySelectorAll('.igs-largo text')].map(t => [t.textContent, getComputedStyle(t).fill.replace(/color\(srgb ([\d.]+) ([\d.]+) ([\d.]+)\)/, (m, a, b, c) => 'rgb(' + [a, b, c].map(x => Math.round(Math.min(1, x) * 255)).join(', ') + ')')]),
    };
  });
  ok('estremi: imbuto al massimo 6 gradini, senza gradini niente disegno', est.imbuto === 6 && est.imbutoVuoto, est.imbuto);
  ok('estremi: la ciambella che non fa 100 lo scrive per l\'editor (nascosto sul sito), con il conto', /fanno 180%/.test(est.ciambella) && /Si vedono solo 6 voci su 7/.test(est.ciambella), est.ciambella);
  ok('estremi: omini con quanti più del totale (15 su 3) ne colora 3; mappa di calore al massimo 12 x 12; ragnatela 8 aspetti', est.omini.join() === '3,0' && est.calore === 144 && est.radar === 8, est);
  ok('estremi: lancetta con massimo uguale al minimo e valore fuori scala lo dice all\'editor', /massimo deve essere più grande/.test(est.lancetta) && /fuori dalla scala/.test(est.lancetta), est.lancetta);
  const barreCol = Object.fromEntries(est.barre.filter(([t]) => /%/.test(t)).map(([t, f]) => [t, f]));
  ok('estremi: sopra un colore libero chiaro il testo è nero, sopra uno scuro è bianco', est.barre.filter(([t]) => t === '50%').map(([, f]) => f).join() === 'rgb(0, 0, 0),rgb(255, 255, 255)', barreCol);

  // 4. Contrasto del testo: senza tema, Aria (chiaro), Cosmo e Autunno (scuri), e con il corpo scuro (tema Notte delle infografiche).
  const adm = await nuova(await contesto(1280));
  await adm.goto(B + '/admin/login.php'); await adm.fill('#username', UTENTE); await adm.fill('#password', PASSWORD);
  await Promise.all([adm.waitForNavigation(), adm.click('button[type=submit]')]);
  for (const [nome, url] of [['senza tema', '/infografiche-15/'], ['Aria', '/infografiche-15/?preview=1&tema=aria'], ['Cosmo', '/infografiche-15/?preview=1&tema=cosmo'], ['Autunno', '/infografiche-15/?preview=1&tema=autunno'], ['corpo scuro (Notte)', '/infografiche-15-notte/'], ['al telefono, senza tema', '/infografiche-15/']]) {
    if (/telefono/.test(nome)) await adm.setViewportSize({ width: 390, height: 844 });
    await adm.goto(B + url, { waitUntil: 'networkidle' });
    const c = await contrasti(adm, true);
    const disegno = c.filter(x => !x.w.startsWith('cornice')), bassi = disegno.filter(x => x.c < 4.5);
    const cornice = c.filter(x => x.w.startsWith('cornice')), cBassi = cornice.filter(x => x.c < (x.grande ? 3 : 4.5));
    ok(`contrasto, ${nome}: ${disegno.length} scritte dei disegni tutte almeno 4,5 (la più bassa ${Math.min(...disegno.map(x => x.c))})`, disegno.length >= 15 * 4 && bassi.length === 0, bassi.slice(0, 8));
    ok(`contrasto, ${nome}: occhiello, titolo e firma delle quindici almeno 4,5 (il titolo, grande, almeno 3)`, cBassi.length === 0, cBassi.slice(0, 8));
  }
  await adm.setViewportSize({ width: 1280, height: 900 });

  // 5. Dall'editor: le quindici dalla tavolozza, una voce cambiata, la ciambella che non fa 100, pubblica, il sito.
  const { id } = JSON.parse(pagina('vuota'));
  await adm.goto(B + '/admin/editor.php?tipo=pagine&id=' + id + '&parti=vuota', { waitUntil: 'networkidle' }); await adm.waitForTimeout(1200);
  const inTavolozza = await adm.evaluate(T => T.filter(t => document.querySelector('#ed-pannello-widget .ed-widget-voce[data-tipo=ig_' + t + ']')), TIPI);
  ok('le quindici sono nella tavolozza, nel gruppo Infografiche', inTavolozza.length === 15 && await adm.evaluate(() => [...document.querySelectorAll('.ed-gruppo-widget')].some(g => /Infografiche/.test(g.textContent) && g.querySelector('[data-tipo=ig_imbuto]') && g.querySelector('[data-tipo=ig_passi]'))), inTavolozza.length);
  for (const t of TIPI) { await adm.click('.ed-schede-pannello button[data-pannello=widget]'); await adm.locator('.ed-widget-voce[data-tipo=ig_' + t + ']').click(); await adm.waitForTimeout(350); }
  const tela = adm.frameLocator('.ed-tela iframe');
  await adm.waitForTimeout(1200);
  ok('nella tela dell\'editor ci sono le quindici, disegnate (con il loro foglio di stile)', await tela.locator('figure.igs').count() === 15 && await tela.locator('figure.igs').first().evaluate(e => getComputedStyle(e).containerType) === 'inline-size');
  // l'ultima aggiunta (barre al cento per cento) è aperta in Modifica: si cambia il nome della prima voce
  const campo = async (etichetta, valore, voce = 0, ripetitore = null) => {
    const rip = ripetitore ? adm.locator('#ed-pannello-modifica .ed-controllo', { hasText: ripetitore }).locator('.ed-ripetitore').first() : adm.locator('#ed-pannello-modifica .ed-ripetitore').first();
    if (await rip.locator('.ed-voce').nth(voce).locator('.ed-voce-corpo').isHidden()) await rip.locator('.ed-voce-testa b').nth(voce).click();
    const c = rip.locator('.ed-voce-corpo:visible .ed-controllo', { hasText: etichetta }).first();
    const i = c.locator('input[type=number], input[type=text], input:not([type]), textarea').first();
    await i.fill(String(valore)); await i.dispatchEvent('input'); await adm.waitForTimeout(700);
  };
  await campo('Nome', 'Clienti nuovi', 0, 'Voci, con il loro colore');
  ok('barre al cento per cento: il nome cambiato si vede subito nella tela', await tela.locator('.ed-w-ig_barre100 .igs-largo text', { hasText: 'Clienti nuovi' }).count() === 1);
  // la ciambella: si seleziona nella tela e si porta la prima voce a 50 (totale 105)
  await tela.locator('.ed-w-ig_ciambella').click(); await adm.waitForTimeout(500);
  await campo('Percentuale', 50, 0);
  ok('ciambella: l\'editor dice che le percentuali fanno 105%', /fanno 105%/.test(await tela.locator('.ed-w-ig_ciambella .igs-nota').innerText().catch(() => '')));
  await tela.locator('.ed-w-ig_imbuto').click(); await adm.waitForTimeout(500);
  await campo('Nome del gradino', 'Richieste di preventivo', 1);
  await campo('Quanti', 170, 1);
  ok('imbuto: voce cambiata e resa ricalcolata nella tela (170 su 1.200: 14%, 85 su 170: 50%)', await tela.locator('.ed-w-ig_imbuto .igs-largo').evaluate(s => { const t = [...s.querySelectorAll('text')].map(x => x.textContent); return t.includes('Richieste di preventivo') && t.includes('14%') && t.includes('50%'); }));
  await adm.click('#ed-pubblica');
  await adm.waitForFunction(() => /Pubblicat/.test(document.querySelector('#ed-stato').textContent), null, { timeout: 20000 }).catch(() => {});
  ok('pubblicata dall\'editor', /Pubblicata/.test(await adm.locator('#ed-stato').innerText()), await adm.locator('#ed-stato').innerText());
  const vis2 = await nuova(await contesto(1280));
  await vis2.goto(B + '/infografiche-15-editor/', { waitUntil: 'networkidle' });
  const sito = await vis2.evaluate(() => ({ n: document.querySelectorAll('figure.igs').length, imbuto: document.querySelector('.ed-w-ig_imbuto .igs-largo').getAttribute('aria-label'), barre: document.querySelector('.ed-w-ig_barre100 .igs-largo').getAttribute('aria-label'), nota: [...document.querySelectorAll('.igs-nota')].every(e => getComputedStyle(e).display === 'none') }));
  ok('sul sito le quindici aggiunte dall\'editor, con le voci cambiate; la nota della ciambella non si vede', sito.n === 15 && /Richieste di preventivo 170 \(resa 14%\)/.test(sito.imbuto) && /Clienti nuovi/.test(sito.barre) && sito.nota, sito);
  pagina('togli');

  ok('nessun errore JavaScript e nessuna finestra aperta da script', err.length === 0, err);
  console.log(`\n${n - ko} su ${n} OK`);
  await br.close();
  process.exit(ko ? 1 : 0);
})();
