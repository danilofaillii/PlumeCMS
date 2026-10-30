// Le due illustrazioni del Prima e dopo nella pagina Funzionalità, sotto "Vieni da WordPress?" (30 ottobre 2026, richiesta
// di Danilo): prima una persona disperata davanti a un pannello WordPress pieno di avvisi, dopo la stessa persona felice
// con il sito fatto con plumeCMS. Disegnate qui in SVG, stessa scena e stessa inquadratura (il cursore le confronta),
// fotografate da Chromium. Niente loghi di altri: "WordPress" è scritto come testo, come in "Vieni da WordPress?".
// uso: node sito-plumecms/prima-dopo.js <cartella>   scrive wp-prima.png e wp-dopo.png (1500x1000);
// poi python3 sito-plumecms/costruisci.py --schermate <cartella> (le fa webp).
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const D = process.argv[2];
const L = 1500, A = 1000;
const FONT = path.join(__dirname, '..', 'cms', 'sito', 'assets', 'font');
const f64 = (f) => 'data:font/woff2;base64,' + fs.readFileSync(path.join(FONT, f)).toString('base64');

// La persona: stessa posizione nelle due scene, cambiano braccia, viso e colori.
function persona(felice) {
  const pelle = '#f1c6a3', pelleOmbra = '#d9a582', maglia = felice ? '#5b45e0' : '#56607a', capelli = '#3a2a22';
  const spalle = { sx: [372, 548], dx: [508, 548] };
  // braccia: [spalla, gomito, mano]
  const braccia = felice
    ? [[[372, 552], [318, 460], [300, 352]], [[508, 552], [566, 460], [584, 352]]]  // in alto, a V
    : [[[372, 552], [322, 470], [392, 418]], [[508, 552], [560, 470], [490, 418]]]; // mani sulla testa
  const braccio = (b) => `<path d="M${b[0]} L${b[1]} L${b[2]}" fill="none" stroke="${maglia}" stroke-width="44" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="${b[2][0]}" cy="${b[2][1]}" r="24" fill="${pelle}"/>`;
  const viso = felice
    ? `<path d="M410 448 q12 -14 24 0" stroke="#2b1d18" stroke-width="6" fill="none" stroke-linecap="round"/>
       <path d="M450 448 q12 -14 24 0" stroke="#2b1d18" stroke-width="6" fill="none" stroke-linecap="round"/>
       <path d="M412 480 q30 34 60 0 z" fill="#7a2e2e"/><path d="M420 482 q22 12 44 0" fill="#fff"/>
       <circle cx="402" cy="474" r="11" fill="#f39a9a" opacity=".6"/><circle cx="482" cy="474" r="11" fill="#f39a9a" opacity=".6"/>`
    : `<path d="M408 432 l22 8" stroke="#2b1d18" stroke-width="6" stroke-linecap="round"/><path d="M476 432 l-22 8" stroke="#2b1d18" stroke-width="6" stroke-linecap="round"/>
       <circle cx="422" cy="452" r="6" fill="#2b1d18"/><circle cx="462" cy="452" r="6" fill="#2b1d18"/>
       <path d="M418 496 q24 -22 48 0" stroke="#2b1d18" stroke-width="6" fill="none" stroke-linecap="round"/>
       <path d="M496 420 q10 16 0 26 q-10 -10 0 -26 z" fill="#8fd3ff"/>`;
  return `<g>
    <path d="M330 720 q0 -160 110 -174 q110 14 110 174 z" fill="${maglia}"/>
    <rect x="420" y="520" width="40" height="36" rx="10" fill="${pelleOmbra}"/>
    <circle cx="442" cy="458" r="72" fill="${pelle}"/>
    <path d="M370 452 q-4 -86 72 -88 q78 2 74 86 q-14 -46 -74 -48 q-58 2 -72 50 z" fill="${capelli}"/>
    <ellipse cx="370" cy="462" rx="10" ry="16" fill="${pelleOmbra}"/><ellipse cx="514" cy="462" rx="10" ry="16" fill="${pelleOmbra}"/>
    ${viso}
    ${braccia.map(braccio).join('')}
  </g>`;
}

// Lo schermo di prima: un pannello WordPress (scritto come testo) pieno di avvisi.
function schermoPrima(x, y, l, a) {
  const av = (yy, col, testo) => `<rect x="${x + 150}" y="${yy}" width="${l - 170}" height="40" rx="4" fill="#fff"/><rect x="${x + 150}" y="${yy}" width="6" height="40" fill="${col}"/>
    <text x="${x + 170}" y="${yy + 26}" font-family="Inter" font-size="16" font-weight="600" fill="#1d2327">${testo}</text>`;
  return `<rect x="${x}" y="${y}" width="${l}" height="${a}" fill="#f0f0f1"/>
    <rect x="${x}" y="${y}" width="${l}" height="34" fill="#1d2327"/>
    <text x="${x + 14}" y="${y + 23}" font-family="Inter" font-size="15" font-weight="600" fill="#c3c4c7">WordPress  ·  Bacheca  ·  3 aggiornamenti  ·  12 commenti in attesa</text>
    <rect x="${x}" y="${y + 34}" width="130" height="${a - 34}" fill="#2c3338"/>
    ${['Bacheca', 'Articoli', 'Media', 'Pagine', 'Plugin (7)', 'Aspetto', 'Strumenti', 'Impostazioni'].map((t, i) => `<text x="${x + 16}" y="${y + 70 + i * 34}" font-family="Inter" font-size="15" fill="${t.startsWith('Plugin') ? '#f86368' : '#c3c4c7'}">${t}</text>`).join('')}
    ${av(y + 54, '#d63638', 'Si è verificato un errore critico nel tuo sito.')}
    ${av(y + 104, '#dba617', '7 plugin hanno bisogno di un aggiornamento.')}
    ${av(y + 154, '#d63638', 'Aggiornamento non riuscito: il tema non è compatibile.')}
    ${av(y + 204, '#dba617', 'La versione di PHP del tuo server è obsoleta.')}
    ${av(y + 254, '#d63638', 'Il backup di stanotte non è stato completato.')}
    <rect x="${x + 150}" y="${y + 310}" width="${(l - 190) / 2}" height="${a - 330}" rx="4" fill="#fff"/>
    <rect x="${x + 170 + (l - 190) / 2}" y="${y + 310}" width="${(l - 190) / 2}" height="${a - 330}" rx="4" fill="#fff"/>
    <circle cx="${x + 150 + (l - 190) / 4}" cy="${y + 310 + (a - 330) / 2}" r="18" fill="none" stroke="#c3c4c7" stroke-width="5" stroke-dasharray="70 40"/>
    <circle cx="${x + 170 + 3 * (l - 190) / 4}" cy="${y + 310 + (a - 330) / 2}" r="18" fill="none" stroke="#c3c4c7" stroke-width="5" stroke-dasharray="70 40"/>`;
}

// Lo schermo di dopo: un sito fatto con plumeCMS, pulito, con la piuma.
function schermoDopo(x, y, l, a) {
  return `<defs><linearGradient id="hero" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#5b45e0"/><stop offset="1" stop-color="#0b7d72"/></linearGradient>
      <linearGradient id="tit" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#b9adff"/><stop offset="1" stop-color="#5fd6c6"/></linearGradient></defs>
    <rect x="${x}" y="${y}" width="${l}" height="${a}" fill="#0d0b26"/>
    <rect x="${x + 22}" y="${y + 20}" width="28" height="28" rx="7" fill="#5b45e0"/>
    <path d="M${x + 44} ${y + 26} L${x + 32} ${y + 37} L${x + 28} ${y + 44}" stroke="#fff" stroke-width="2.6" fill="none" stroke-linecap="round"/>
    <text x="${x + 60}" y="${y + 41}" font-family="Grotesk" font-size="19" font-weight="700" fill="#fff">plume<tspan fill="#b9adff">CMS</tspan></text>
    ${['Home', 'Chi siamo', 'Servizi', 'Contatti'].map((t, i) => `<text x="${x + l - 330 + i * 82}" y="${y + 40}" font-family="Inter" font-size="14" fill="#d6d2f5">${t}</text>`).join('')}
    <rect x="${x + 22}" y="${y + 66}" width="${l - 44}" height="${a * 0.46}" rx="14" fill="url(#hero)"/>
    <text x="${x + 52}" y="${y + 120}" font-family="Inter" font-size="13" font-weight="700" letter-spacing="2.5" fill="#cfe9ff">IL TUO SITO</text>
    <text x="${x + 52}" y="${y + 164}" font-family="Grotesk" font-size="38" font-weight="700" fill="#fff">Leggero come</text>
    <text x="${x + 52}" y="${y + 206}" font-family="Grotesk" font-size="38" font-weight="700" fill="#fff">una piuma.</text>
    <rect x="${x + 52}" y="${y + 228}" width="150" height="38" rx="19" fill="#fff"/><text x="${x + 77}" y="${y + 253}" font-family="Inter" font-size="15" font-weight="700" fill="#2a1d8f">Scrivici</text>
    <path d="M${x + l - 90} ${y + 92} q-90 40 -150 170 q60 -60 150 -170 z M${x + l - 92} ${y + 96} L${x + l - 230} ${y + 250}" fill="rgba(255,255,255,.85)" stroke="rgba(255,255,255,.9)" stroke-width="3"/>
    ${[0, 1, 2].map(i => `<rect x="${x + 22 + i * ((l - 44 + 16) / 3)}" y="${y + 92 + a * 0.46}" width="${(l - 44 - 32) / 3}" height="${a - 112 - a * 0.46}" rx="12" fill="#1b1840" stroke="rgba(185,173,255,.25)"/>
      <rect x="${x + 40 + i * ((l - 44 + 16) / 3)}" y="${y + 114 + a * 0.46}" width="80" height="10" rx="5" fill="${i === 1 ? '#5fd6c6' : '#b9adff'}"/>
      <rect x="${x + 40 + i * ((l - 44 + 16) / 3)}" y="${y + 136 + a * 0.46}" width="120" height="7" rx="3.5" fill="rgba(255,255,255,.3)"/>
      <rect x="${x + 40 + i * ((l - 44 + 16) / 3)}" y="${y + 152 + a * 0.46}" width="96" height="7" rx="3.5" fill="rgba(255,255,255,.3)"/>`).join('')}
    <g transform="translate(${x + l - 210} ${y + a - 58})"><rect width="190" height="40" rx="20" fill="#0f5132"/><circle cx="22" cy="20" r="11" fill="#4ade80"/>
      <path d="M16 20 l4 4 l8 -9" stroke="#0f5132" stroke-width="3" fill="none" stroke-linecap="round"/><text x="42" y="26" font-family="Inter" font-size="15" font-weight="600" fill="#bbf7d0">Tutto pubblicato</text></g>`;
}

function scena(felice) {
  const sx = 690, sy = 236, sl = 640, sa = 400; // lo schermo, uguale nelle due scene
  const muro = felice ? ['#fff1dc', '#ffd9b0'] : ['#3a4256', '#262c3b'];
  const tavolo = felice ? '#b9835a' : '#5b5048';
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${L}" height="${A}" viewBox="0 0 ${L} ${A}">
  <defs><linearGradient id="muro" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${muro[0]}"/><stop offset="1" stop-color="${muro[1]}"/></linearGradient>
    <radialGradient id="luce" cx="0.5" cy="0.5" r="0.5"><stop offset="0" stop-color="${felice ? '#fff7b0' : '#9fb4ff'}" stop-opacity="${felice ? '.8' : '.25'}"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient></defs>
  <rect width="${L}" height="${A}" fill="url(#muro)"/>
  ${felice
    ? `<circle cx="200" cy="170" r="210" fill="url(#luce)"/><circle cx="200" cy="170" r="62" fill="#ffd34d"/>
       <rect x="80" y="70" width="250" height="210" rx="12" fill="none" stroke="#fff" stroke-width="10" opacity=".7"/>
       <g transform="translate(1432 560)"><rect x="-38" y="80" width="76" height="80" rx="10" fill="#c56b3c"/>
         ${[[-60, -10], [-20, -60], [25, -40], [55, 10], [0, 20]].map(([a, b]) => `<ellipse cx="${a}" cy="${b + 40}" rx="22" ry="48" transform="rotate(${a / 2} ${a} ${b + 40})" fill="#3f9a62"/>`).join('')}</g>
       ${[[620, 130], [1380, 150], [240, 520], [1160, 120]].map(([a, b], i) => `<path d="M${a} ${b - 16} L${a + 5} ${b - 5} L${a + 16} ${b} L${a + 5} ${b + 5} L${a} ${b + 16} L${a - 5} ${b + 5} L${a - 16} ${b} L${a - 5} ${b - 5} Z" fill="${i % 2 ? '#5fd6c6' : '#b9adff'}"/>`).join('')}
       <path d="M210 640 q40 -120 110 -150 q-30 70 -110 150 z M208 642 L300 520" fill="#ffffff" stroke="#ffffff" stroke-width="3" opacity=".9" transform="rotate(-18 250 580)"/>`
    : `<circle cx="1180" cy="430" r="380" fill="url(#luce)"/>
       <g transform="translate(442 250)" fill="#8b94ab"><ellipse cx="0" cy="0" rx="90" ry="44"/><ellipse cx="-56" cy="10" rx="54" ry="36"/><ellipse cx="60" cy="12" rx="56" ry="34"/>
         ${[-50, -18, 14, 46].map(a => `<path d="M${a} 52 l-8 26" stroke="#8fd3ff" stroke-width="5" stroke-linecap="round"/>`).join('')}</g>
       ${[[180, 790, -20], [250, 760, 15], [1240, 760, -8]].map(([a, b, r]) => `<rect x="${a}" y="${b}" width="90" height="62" rx="4" fill="#e7e2d6" transform="rotate(${r} ${a} ${b})"/>`).join('')}
       <g transform="translate(620 650)"><rect x="0" y="0" width="44" height="52" rx="8" fill="#e9e4dc"/><rect x="0" y="0" width="44" height="10" rx="4" fill="#6b4f3a"/></g>
       <g transform="translate(560 668)"><rect x="0" y="0" width="40" height="34" rx="8" fill="#e9e4dc"/></g>`}
  <rect x="120" y="704" width="1300" height="30" rx="8" fill="${tavolo}"/>
  <rect x="160" y="734" width="26" height="270" fill="${tavolo}"/><rect x="1354" y="734" width="26" height="270" fill="${tavolo}"/>
  <rect x="${sx + sl / 2 - 30}" y="${sy + sa + 20}" width="60" height="58" fill="#2a2d36"/><rect x="${sx + sl / 2 - 110}" y="${sy + sa + 72}" width="220" height="18" rx="9" fill="#2a2d36"/>
  <rect x="${sx - 20}" y="${sy - 20}" width="${sl + 40}" height="${sa + 44}" rx="18" fill="#1a1c23"/>
  <svg x="${sx}" y="${sy}" width="${sl}" height="${sa}" viewBox="${sx} ${sy} ${sl} ${sa}">${felice ? schermoDopo(sx, sy, sl, sa) : schermoPrima(sx, sy, sl, sa)}</svg>
  <rect x="${sx}" y="${sy}" width="${sl}" height="${sa}" fill="${felice ? 'none' : 'rgba(20,24,40,.12)'}"/>
  <rect x="${sx + 60}" y="${sy + sa + 94}" width="${sl - 160}" height="18" rx="6" fill="#d7d9df" opacity=".9"/>
  ${persona(felice)}
  <rect x="120" y="704" width="380" height="30" rx="8" fill="${tavolo}"/>
</svg>`;
}

(async () => {
  if (!D) { console.log('uso: node sito-plumecms/prima-dopo.js <cartella>'); process.exit(1); }
  fs.mkdirSync(D, { recursive: true });
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: L, height: A }, deviceScaleFactor: 1 })).newPage();
  for (const [nome, felice] of [['wp-prima', false], ['wp-dopo', true]]) {
    const svg = scena(felice);
    fs.writeFileSync(path.join(D, nome + '.svg'), svg);
    await p.setContent(`<!doctype html><html><head><style>@font-face{font-family:Inter;src:url(${f64('inter-latin.woff2')})}@font-face{font-family:Grotesk;src:url(${f64('space-grotesk-latin.woff2')})}html,body{margin:0}</style></head><body>${svg}</body></html>`);
    await p.evaluate(() => document.fonts.ready);
    await p.screenshot({ path: path.join(D, nome + '.png'), clip: { x: 0, y: 0, width: L, height: A } });
    console.log(nome);
  }
  await br.close();
})().catch(e => { console.error(e); process.exit(1); });
