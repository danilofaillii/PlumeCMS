// La foto della Copertina della home del sito di plumeCMS (27 ottobre 2026): una piuma disegnata qui, in SVG, nei
// colori del sito, renderizzata da Chromium in PNG. Niente immagini, caratteri o servizi esterni.
// uso: node sito-plumecms/copertina.js <cartella>   scrive <cartella>/copertina.png (e copertina.svg, il sorgente)
// poi: python3 sito-plumecms/costruisci.py --schermate <cartella> (fa immagini/copertina.webp).
//
// La composizione segue la Copertina (cms/sito/assets/hero-a.css, .ed-hero-copertina): le parole bianche stanno in
// basso a sinistra e il velo scuro scende dall'alto in basso, quindi la piuma sta in alto a destra e il basso a
// sinistra resta scuro e calmo. Al telefono la foto si ritaglia: il CSS del sito (costruisci.py) sposta il punto di
// vista a destra, dove la piuma resta intera. I numeri casuali vengono da un generatore con seme: stessa immagine a
// ogni giro.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const L = 1600, A = 1000;        // il disegno; il PNG esce a 1600x1000 e il costruttore lo porta a 1440
const VIOLA = '#5b45e0', VIOLA_CHIARO = '#b9adff', VIOLA_SCURO = '#2a1d8f', NOTTE = '#0d0b26';
const VERDE = '#0b7d72', VERDE_CHIARO = '#5fd6c6';

let seme = 7;
const caso = () => { seme = (seme * 16807) % 2147483647; return (seme - 1) / 2147483646; };
const f = (n) => n.toFixed(1);

// Un punto della curva di Bezier quadratica p0-c-p1 e la sua direzione, per t fra 0 e 1.
function bez(p0, c, p1, t) {
  const u = 1 - t;
  const x = u * u * p0[0] + 2 * u * t * c[0] + t * t * p1[0], y = u * u * p0[1] + 2 * u * t * c[1] + t * t * p1[1];
  const dx = 2 * u * (c[0] - p0[0]) + 2 * t * (p1[0] - c[0]), dy = 2 * u * (c[1] - p0[1]) + 2 * t * (p1[1] - c[1]);
  const m = Math.hypot(dx, dy);
  return { x, y, tx: dx / m, ty: dy / m };
}

// Il profilo del vessillo lungo il rachide (u da 0, la base delle barbe, a 1, la punta): largo verso un terzo,
// poi si assottiglia fino alla punta.
const profilo = (u) => Math.pow(u, 0.3) * Math.pow(1 - u, 0.9) / (Math.pow(0.25, 0.3) * Math.pow(0.75, 0.9));
const INIZIO = 0.15;   // dove cominciano le barbe; sotto c'e il piumino

/**
 * Una piuma: il calamo (base) in p0, la punta in p1, la curva del rachide con il controllo c. Le barbe sono linee
 * curve che partono dal rachide e piegano verso la punta, piu larghe a sinistra. Qua e la un gruppo di barbe si
 * separa dalle vicine (le "spaccature" di una piuma vera). In basso, il piumino.
 */
function piuma(p0, c, p1, { largS = 150, largD = 112, barbe = 150, spessore = 4, opacita = 0.95, id = 'p' } = {}) {
  const lato = (segno, larg) => {
    let s = '', fuori = 0, giro = 0;
    for (let k = 0; k < barbe; k++) {
      const u = k / barbe, t = INIZIO + (1 - INIZIO) * u, b = bez(p0, c, p1, t);
      const nx = -b.ty * segno, ny = b.tx * segno;                     // la normale, da una parte o dall'altra
      if (caso() < 0.06 && u > 0.12 && u < 0.85) { fuori = 22 + caso() * 22; giro = (caso() - 0.2) * 0.5; } // una spaccatura
      fuori *= 0.84; giro *= 0.84;
      const lung = larg * profilo(u) * (0.9 + caso() * 0.14);
      const pieg = 0.5 + u * 0.22 + giro + caso() * 0.06;             // verso la punta le barbe piegano di piu
      const x1 = b.x + nx * lung + b.tx * lung * pieg + nx * fuori, y1 = b.y + ny * lung + b.ty * lung * pieg + ny * fuori;
      // Il controllo spinge la barba in fuori: esce quasi dritta dal rachide e poi si piega, come una vera.
      const cx = b.x + nx * lung * 0.7 + b.tx * lung * 0.05, cy = b.y + ny * lung * 0.7 + b.ty * lung * 0.05;
      s += `<path d="M${f(b.x)} ${f(b.y)}Q${f(cx)} ${f(cy)} ${f(x1)} ${f(y1)}" stroke-width="${f(1 + caso() * 1.3)}" opacity="${(0.4 + caso() * 0.55).toFixed(2)}"/>`;
    }
    return s;
  };
  // Il piumino alla base: barbe corte, morbide, arricciate, piu chiare.
  let piumino = '';
  for (let k = 0; k < 54; k++) {
    const t = 0.03 + (INIZIO - 0.01) * (k / 54), b = bez(p0, c, p1, t), segno = k % 2 ? 1 : -1;
    const nx = -b.ty * segno, ny = b.tx * segno, lung = 34 + caso() * 60;
    const x1 = b.x + nx * lung + b.tx * lung * 0.25 + (caso() - 0.5) * 34, y1 = b.y + ny * lung + b.ty * lung * 0.25 + (caso() - 0.5) * 34;
    const cx = b.x + nx * lung * 0.35 - b.tx * 34, cy = b.y + ny * lung * 0.35 - b.ty * 34;
    piumino += `<path d="M${f(b.x)} ${f(b.y)}Q${f(cx)} ${f(cy)} ${f(x1)} ${f(y1)}" stroke-width="${f(0.8 + caso())}" opacity="${(0.25 + caso() * 0.35).toFixed(2)}"/>`;
  }
  const sx = lato(1, largS), dx = lato(-1, largD);
  // La sagoma piena, sfocata e leggera, sotto le barbe: da corpo al vessillo senza coprirne le linee.
  const sag = (segno, larg) => {
    const pts = [];
    for (let k = 0; k <= 48; k++) {
      const u = k / 48, b = bez(p0, c, p1, INIZIO + (1 - INIZIO) * u), lung = larg * profilo(u) * 0.92;
      const nx = -b.ty * segno, ny = b.tx * segno, pieg = 0.5 + u * 0.22;
      pts.push([b.x + nx * lung + b.tx * lung * pieg, b.y + ny * lung + b.ty * lung * pieg]);
    }
    return pts;
  };
  const a = sag(1, largS), d = sag(-1, largD).reverse(), inizio = bez(p0, c, p1, INIZIO);
  const sagoma = `M${f(inizio.x)} ${f(inizio.y)}` + a.map(p => `L${f(p[0])} ${f(p[1])}`).join('') + d.map(p => `L${f(p[0])} ${f(p[1])}`).join('') + 'Z';
  return `<g>
  <path d="${sagoma}" fill="url(#${id}-corpo)" opacity="${(opacita * 0.6).toFixed(2)}" filter="url(#morbido)"/>
  <g fill="none" stroke="url(#${id}-barbe)" stroke-linecap="round" opacity="${opacita}">${sx}${dx}</g>
  <g fill="none" stroke="${VERDE_CHIARO}" stroke-linecap="round" opacity="${(opacita * 0.9).toFixed(2)}">${piumino}</g>
  <path d="M${f(p0[0])} ${f(p0[1])}Q${f(c[0])} ${f(c[1])} ${f(p1[0])} ${f(p1[1])}" fill="none" stroke="url(#${id}-rachide)" stroke-width="${spessore}" stroke-linecap="round"/>
</g>`;
}

// I gradienti di una piuma, dalla base (p0) alla punta (p1): verde acqua in basso, viola, lilla chiaro in cima.
const sfumature = (id, p0, p1) => `
  <linearGradient id="${id}-barbe" gradientUnits="userSpaceOnUse" x1="${p0[0]}" y1="${p0[1]}" x2="${p1[0]}" y2="${p1[1]}">
    <stop offset="0" stop-color="#9ff0e4"/><stop offset=".3" stop-color="#b3a6ff"/><stop offset=".7" stop-color="#dcd5ff"/><stop offset="1" stop-color="#ffffff"/></linearGradient>
  <linearGradient id="${id}-corpo" gradientUnits="userSpaceOnUse" x1="${p0[0]}" y1="${p0[1]}" x2="${p1[0]}" y2="${p1[1]}">
    <stop offset="0" stop-color="${VERDE}"/><stop offset=".5" stop-color="${VIOLA}"/><stop offset="1" stop-color="${VIOLA_CHIARO}"/></linearGradient>
  <linearGradient id="${id}-rachide" gradientUnits="userSpaceOnUse" x1="${p0[0]}" y1="${p0[1]}" x2="${p1[0]}" y2="${p1[1]}">
    <stop offset="0" stop-color="#d8fff8" stop-opacity=".7"/><stop offset=".4" stop-color="#ffffff"/><stop offset="1" stop-color="#ffffff" stop-opacity=".6"/></linearGradient>`;

function svg() {
  // La piuma grande: base in basso a destra del centro, punta in alto a destra; il rachide piega appena.
  const P = [[1205, 905], [1250, 430], [1445, 70]];
  // Una piuma piccola e lontana, sfocata, che scende nell'aria piu in alto a sinistra.
  const Q = [[1010, 300], [1000, 190], [1080, 95]];
  // Le correnti d'aria: quattro curve lunghe e sottili che salgono da sinistra e girano attorno alla piuma, e un
  // nastro largo e sfocato, la forma morbida su cui la piuma galleggia.
  let aria = '';
  for (let k = 0; k < 4; k++) {
    const y = 640 - k * 120 + caso() * 40, x0 = 300 + k * 90 + caso() * 80;
    aria += `<path d="M${f(x0)} ${f(y + 160)}C${f(x0 + 420)} ${f(y + 60)} ${f(980 + k * 40)} ${f(y - 260)} ${f(1640)} ${f(y - 330 - k * 30)}" opacity="${(0.1 + caso() * 0.08).toFixed(2)}" stroke-width="${f(1.2 + caso() * 1.2)}"/>`;
  }
  // Pulviscolo: puntini di luce, piu fitti vicino alla piuma.
  let luce = '';
  for (let k = 0; k < 70; k++) {
    const x = 760 + caso() * 840, y = caso() * 700, r = 0.8 + caso() * 2.4;
    luce += `<circle cx="${f(x)}" cy="${f(y)}" r="${f(r)}" opacity="${(0.15 + caso() * 0.55).toFixed(2)}"/>`;
  }
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${L}" height="${A}" viewBox="0 0 ${L} ${A}">
<defs>
  <linearGradient id="cielo" x1="0" y1="1" x2="1" y2="0">
    <stop offset="0" stop-color="${NOTTE}"/><stop offset=".45" stop-color="#1a1452"/><stop offset=".8" stop-color="${VIOLA_SCURO}"/><stop offset="1" stop-color="#3d2fb8"/></linearGradient>
  <radialGradient id="alone-viola" cx="1270" cy="330" r="620" gradientUnits="userSpaceOnUse">
    <stop offset="0" stop-color="${VIOLA}" stop-opacity=".75"/><stop offset=".55" stop-color="${VIOLA}" stop-opacity=".18"/><stop offset="1" stop-color="${VIOLA}" stop-opacity="0"/></radialGradient>
  <radialGradient id="alone-verde" cx="1180" cy="780" r="520" gradientUnits="userSpaceOnUse">
    <stop offset="0" stop-color="${VERDE}" stop-opacity=".55"/><stop offset="1" stop-color="${VERDE}" stop-opacity="0"/></radialGradient>
  <radialGradient id="buio" cx="120" cy="1000" r="900" gradientUnits="userSpaceOnUse">
    <stop offset="0" stop-color="#05040f" stop-opacity=".85"/><stop offset="1" stop-color="#05040f" stop-opacity="0"/></radialGradient>
  ${sfumature('p', P[0], P[2])}${sfumature('q', Q[0], Q[2])}
  <linearGradient id="nastro" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="${VERDE}" stop-opacity="0"/><stop offset=".5" stop-color="${VERDE_CHIARO}" stop-opacity=".5"/><stop offset="1" stop-color="${VIOLA_CHIARO}" stop-opacity=".6"/></linearGradient>
  <filter id="morbido" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="10"/></filter>
  <filter id="nebbia" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="60"/></filter>
  <filter id="lontano" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="2.2"/></filter>
</defs>
<rect width="${L}" height="${A}" fill="url(#cielo)"/>
<rect width="${L}" height="${A}" fill="url(#alone-viola)"/>
<rect width="${L}" height="${A}" fill="url(#alone-verde)"/>
<g filter="url(#nebbia)">
  <ellipse cx="1420" cy="160" rx="260" ry="150" fill="${VIOLA_CHIARO}" opacity=".22"/>
  <ellipse cx="980" cy="520" rx="320" ry="120" fill="${VERDE_CHIARO}" opacity=".08"/>
  <ellipse cx="560" cy="180" rx="380" ry="140" fill="${VIOLA}" opacity=".14"/>
</g>
<path d="M200 980C620 900 900 640 1120 520S1500 260 1700 120" fill="none" stroke="url(#nastro)" stroke-width="170" stroke-linecap="round" opacity=".5" filter="url(#nebbia)"/>
<g fill="none" stroke="#ffffff" stroke-linecap="round">${aria}</g>
<g filter="url(#lontano)" opacity=".8">${piuma(Q[0], Q[1], Q[2], { largS: 52, largD: 40, barbe: 60, spessore: 2, opacita: 0.7, id: 'q' })}</g>
<ellipse cx="1330" cy="420" rx="230" ry="380" transform="rotate(18 1330 420)" fill="${VIOLA_CHIARO}" opacity=".28" filter="url(#nebbia)"/>
${piuma(P[0], P[1], P[2])}
<g fill="#ffffff">${luce}</g>
<rect width="${L}" height="${A}" fill="url(#buio)"/>
</svg>`;
}

(async () => {
  const dest = process.argv[2];
  if (!dest) { console.log('uso: node sito-plumecms/copertina.js <cartella>'); process.exit(1); }
  fs.mkdirSync(dest, { recursive: true });
  const s = svg();
  fs.writeFileSync(path.join(dest, 'copertina.svg'), s);
  const br = await chromium.launch();
  const p = await br.newPage({ viewport: { width: L, height: A }, deviceScaleFactor: 1 });
  await p.setContent(`<!doctype html><html><body style="margin:0;background:#000">${s}</body></html>`);
  await p.screenshot({ path: path.join(dest, 'copertina.png'), clip: { x: 0, y: 0, width: L, height: A } });
  await br.close();
  console.log('copertina.png', L + 'x' + A);
})();
