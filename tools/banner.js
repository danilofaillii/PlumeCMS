// I banner pubblicitari di plumeCMS (29 ottobre 2026, richiesta di Danilo): due 970x250 (billboard) e due 160x600
// (skyscraper), disegnati qui in HTML e SVG con i colori del sito di plumeCMS e la piuma della favicon, fotografati da
// Chromium. Caratteri di casa (cms/sito/assets/font), niente servizi esterni.
// uso: node tools/banner.js [cartella]   scrive <cartella>/plumecms-<misura>-<n>.png alla misura esatta e @2x per gli
// schermi densi. Predefinita: releases/banner. Il link da mettere sotto ogni banner: https://plumecms.danilofailli.it
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const OUT = process.argv[2] || path.join(__dirname, '..', 'releases', 'banner');
const FONT = path.join(__dirname, '..', 'cms', 'sito', 'assets', 'font');
const font = (f) => 'data:font/woff2;base64,' + fs.readFileSync(path.join(FONT, f)).toString('base64');

// La piuma della favicon (sito-plumecms/costruisci.py, favicon()), su una griglia 64x64.
const piuma = (lato) => `<svg width="${lato}" height="${lato}" viewBox="0 0 64 64" aria-hidden="true">
  <rect width="64" height="64" rx="14" fill="#5b45e0"/>
  <g fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round">
    <path d="M48 12 L22 34 L15 52" stroke-width="4"/>
    <path d="M44 18 L28 24 M44 22 L30 32 M40 30 L26 38" stroke-width="3"/>
  </g></svg>`;

// Una finestra del browser finta con una pagina composta: dice "editor visuale" senza parole.
const finestra = (l, a) => `<svg width="${l}" height="${a}" viewBox="0 0 300 190" aria-hidden="true">
  <defs><linearGradient id="h" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#5b45e0"/><stop offset="1" stop-color="#0b7d72"/></linearGradient></defs>
  <rect x="0.5" y="0.5" width="299" height="189" rx="12" fill="#17143a" stroke="rgba(185,173,255,.35)"/>
  <circle cx="16" cy="14" r="4" fill="#e8553e"/><circle cx="30" cy="14" r="4" fill="#e8a830"/><circle cx="44" cy="14" r="4" fill="#5fd6c6"/>
  <rect x="12" y="28" width="276" height="78" rx="8" fill="url(#h)"/>
  <rect x="24" y="48" width="130" height="10" rx="5" fill="#fff"/><rect x="24" y="66" width="90" height="6" rx="3" fill="rgba(255,255,255,.7)"/>
  <rect x="24" y="82" width="56" height="14" rx="7" fill="#fff"/>
  <rect x="12" y="116" width="86" height="62" rx="8" fill="#231f52"/><rect x="107" y="116" width="86" height="62" rx="8" fill="#231f52"/><rect x="202" y="116" width="86" height="62" rx="8" fill="#231f52"/>
  <rect x="22" y="128" width="44" height="7" rx="3" fill="#b9adff"/><rect x="117" y="128" width="44" height="7" rx="3" fill="#5fd6c6"/><rect x="212" y="128" width="44" height="7" rx="3" fill="#b9adff"/>
  <rect x="22" y="142" width="64" height="5" rx="2.5" fill="rgba(255,255,255,.35)"/><rect x="117" y="142" width="64" height="5" rx="2.5" fill="rgba(255,255,255,.35)"/><rect x="212" y="142" width="64" height="5" rx="2.5" fill="rgba(255,255,255,.35)"/>
  <rect x="22" y="152" width="50" height="5" rx="2.5" fill="rgba(255,255,255,.35)"/><rect x="117" y="152" width="50" height="5" rx="2.5" fill="rgba(255,255,255,.35)"/><rect x="212" y="152" width="50" height="5" rx="2.5" fill="rgba(255,255,255,.35)"/>
  <rect x="104" y="113" width="92" height="68" rx="10" fill="none" stroke="#e8a830" stroke-width="2.5" stroke-dasharray="6 4"/>
  <path d="M188 168 l0 18 l5 -5 l4 9 l4 -2 l-4 -9 l7 0 z" fill="#fff" stroke="#0d0b26" stroke-width="1.2"/>
</svg>`;

// Quattro domande, quattro schede: il sito su misura.
const domande = (l, a) => `<svg width="${l}" height="${a}" viewBox="0 0 300 190" aria-hidden="true">
  ${['Che attività è?', 'Dove si trova?', 'Cosa offri?', 'Quali pagine?'].map((t, i) => `
  <g transform="translate(${8 + i * 10} ${8 + i * 44})">
    <rect width="250" height="36" rx="10" fill="${i === 3 ? '#5b45e0' : '#231f52'}" stroke="rgba(185,173,255,.35)"/>
    <circle cx="20" cy="18" r="10" fill="${i === 3 ? '#fff' : '#5fd6c6'}"/>
    <text x="20" y="22.5" text-anchor="middle" font-family="Grotesk" font-weight="700" font-size="12" fill="${i === 3 ? '#5b45e0' : '#0d0b26'}">${i + 1}</text>
    <text x="40" y="23" font-family="Inter" font-size="13" font-weight="600" fill="#fff">${t}</text>
    ${i === 3 ? '<path d="M222 18 l6 6 l12 -12" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>' : ''}
  </g>`).join('')}
</svg>`;

const BANNER = [
  { nome: 'plumecms-970x250-1', l: 970, a: 250, forma: 'largo', disegno: domande,
    occhiello: 'Nuovo: il sito su misura', titolo: 'Quattro domande.<br><em>Il tuo sito è pronto.</em>',
    testo: 'Dici che attività fai: plumeCMS compone pagine, menu e testi. Tu metti le foto.', invito: 'Provalo gratis' },
  { nome: 'plumecms-970x250-2', l: 970, a: 250, forma: 'largo', disegno: finestra,
    occhiello: 'Il CMS italiano con l’editor visuale', titolo: 'Il sito lo fai tu.<br><em>Come lo vuoi tu.</em>',
    testo: 'Trascini, scrivi, pubblichi. Temi, SEO e newsletter già dentro. Si installa in 5 minuti.', invito: 'Scarica plumeCMS' },
  { nome: 'plumecms-160x600-1', l: 160, a: 600, forma: 'alto', disegno: domande,
    occhiello: 'Il sito su misura', titolo: 'Quattro domande.<br><em>Il tuo sito è pronto.</em>', punti: ['Gratis, per sempre', 'In italiano', 'Foto finte da sostituire'],
    testo: 'Dici che attività fai: pagine, menu e testi si compongono da soli.', invito: 'Provalo gratis' },
  { nome: 'plumecms-160x600-2', l: 160, a: 600, forma: 'alto', disegno: finestra,
    occhiello: 'Editor visuale', titolo: 'Il sito<br>lo fai tu.<br><em>Come lo vuoi tu.</em>', punti: ['Gratis, per sempre', 'In italiano', 'Senza scrivere codice'],
    testo: 'Temi, SEO e newsletter già dentro. Pronto in 5 minuti.', invito: 'Scarica gratis' },
];

const CSS = `
@font-face{font-family:Inter;src:url(${font('inter-latin.woff2')}) format('woff2');font-weight:100 900}
@font-face{font-family:Grotesk;src:url(${font('space-grotesk-latin.woff2')}) format('woff2');font-weight:300 700}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:#0d0b26}
.b{position:relative;overflow:hidden;color:#fff;font-family:Inter,sans-serif;
  background:radial-gradient(120% 140% at 100% 0%,rgba(91,69,224,.55),transparent 55%),radial-gradient(90% 120% at 0% 100%,rgba(11,125,114,.45),transparent 60%),#0d0b26}
.b::after{content:'';position:absolute;inset:0;border:1px solid rgba(185,173,255,.25);pointer-events:none}
.marchio{display:flex;align-items:center;gap:8px;font:700 17px/1 Grotesk,sans-serif;letter-spacing:-.01em}
.marchio b{font-weight:700}.marchio span{color:#b9adff}
.occhiello{font:700 11px/1.2 Inter,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#5fd6c6}
h1{font:700 34px/1.05 Grotesk,sans-serif;letter-spacing:-.02em}
h1 em{font-style:normal;background:linear-gradient(90deg,#b9adff,#5fd6c6);-webkit-background-clip:text;background-clip:text;color:transparent}
p{color:#d6d2f5;font-size:14px;line-height:1.4}
.invito{display:inline-block;background:#fff;color:#2a1d8f;font:700 14px/1 Inter,sans-serif;padding:12px 18px;border-radius:999px;white-space:nowrap;box-shadow:0 6px 18px rgba(91,69,224,.5)}
.url{font-size:11px;color:#b9adff;letter-spacing:.02em}
/* 970x250 */
.largo{width:970px;height:250px;display:grid;grid-template-columns:1fr 330px;align-items:center;padding:0 34px 0 40px;gap:24px}
.largo .testo{display:flex;flex-direction:column;gap:10px}
.largo .riga{display:flex;align-items:center;gap:18px;margin-top:4px}
.largo .testa{display:flex;align-items:center;gap:14px}
.largo .testa .occhiello{padding-left:14px;border-left:1px solid rgba(185,173,255,.35)}
.largo .disegno{display:flex;justify-content:center}
/* 160x600 */
.alto{width:160px;height:600px;display:flex;flex-direction:column;align-items:flex-start;padding:22px 14px 20px;gap:12px}
.alto .marchio{font-size:16px}
.alto .occhiello{font-size:9.5px;margin-top:6px}
.alto h1{font-size:25px}
.alto p{font-size:12.5px}
.alto .disegno{margin:4px -4px 0;width:136px}
.alto ul{list-style:none;display:flex;flex-direction:column;gap:7px;margin-top:6px}
.alto li{font-size:12px;color:#fff;display:flex;gap:7px;align-items:center}
.alto li::before{content:'';flex:none;width:14px;height:14px;border-radius:50%;background:#5fd6c6 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 14 14'%3E%3Cpath d='M4 7.2l2 2 4-4.4' fill='none' stroke='%230d0b26' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center/14px no-repeat}
.alto .basso{margin-top:auto;display:flex;flex-direction:column;align-items:center;gap:8px;width:100%}
.alto .invito{font-size:13px;padding:11px 14px;width:100%;text-align:center}
.alto .url{font-size:9.5px;text-align:center}
`;

const pagina = (b) => {
  const marchio = `<div class="marchio">${piuma(b.forma === 'largo' ? 26 : 28)}<span><b>plume</b>CMS</span></div>`;
  const corpo = b.forma === 'largo'
    ? `<div class="b largo"><div class="testo"><div class="testa">${marchio}<div class="occhiello">${b.occhiello}</div></div><h1>${b.titolo}</h1><p>${b.testo}</p>
         <div class="riga"><span class="invito">${b.invito} &rarr;</span><span class="url">plumecms.danilofailli.it</span></div></div>
         <div class="disegno">${b.disegno(300, 190)}</div></div>`
    : `<div class="b alto">${marchio}<div class="occhiello">${b.occhiello}</div><h1>${b.titolo}</h1><p>${b.testo}</p>
         <div class="disegno">${b.disegno(136, 86)}</div><ul>${b.punti.map(x => `<li>${x}</li>`).join('')}</ul>
         <div class="basso"><span class="invito">${b.invito}</span><span class="url">plumecms.danilofailli.it</span></div></div>`;
  return `<!doctype html><html lang="it"><head><meta charset="utf-8"><style>${CSS}</style></head><body>${corpo}</body></html>`;
};

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const br = await chromium.launch();
  for (const b of BANNER) {
    for (const scala of [1, 2]) {
      const p = await (await br.newContext({ viewport: { width: b.l, height: b.a }, deviceScaleFactor: scala })).newPage();
      await p.setContent(pagina(b), { waitUntil: 'load' });
      await p.evaluate(() => document.fonts.ready);
      // Niente deve uscire dal banner: un testo troppo lungo ferma lo script invece di finire tagliato.
      const fuori = await p.evaluate(() => [...document.querySelectorAll('.b *')].filter(e => { const r = e.getBoundingClientRect(); return r.right > innerWidth + .5 || r.bottom > innerHeight + .5; }).map(e => e.className || e.tagName));
      if (fuori.length) throw new Error(b.nome + ': esce dal banner: ' + fuori.join(', '));
      const file = path.join(OUT, b.nome + (scala === 2 ? '@2x' : '') + '.png');
      await p.locator('.b').screenshot({ path: file });
      console.log(file, Math.round(fs.statSync(file).size / 1024) + ' KB');
      await p.context().close();
    }
  }
  await br.close();
})().catch(e => { console.error('FERMO:', e.message); process.exit(1); });
