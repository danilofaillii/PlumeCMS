// Le icone dei social per le email (blocco Social dell'editor delle email, 2.12.0): PNG a 48 px, perché molti programmi di
// posta non mostrano gli SVG. Si disegnano dalle icone di casa (includes/icone.php) in tre stili: a colori, scure, chiare.
// uso: node tools/icone-email.js   (scrive cms/sito/assets/email/<rete>-<stile>.png, poi le riduce a tavolozza con Python)
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const R = path.resolve(__dirname, '..');
const RETI = { facebook: ['facebook', '#1877f2'], instagram: ['instagram', '#c13584'], linkedin: ['linkedin', '#0a66c2'], x: ['x-social', '#000000'],
  youtube: ['youtube', '#e00000'], tiktok: ['tiktok', '#000000'], whatsapp: ['whatsapp', '#128c4b'], telegram: ['telegram', '#1c8adb'],
  pinterest: ['pinterest', '#e60023'], sito: ['globo', '#4b5563'], email: ['email', '#4b5563'] };
const STILI = { colori: (c) => [c, '#ffffff'], scuro: () => ['#1f2330', '#ffffff'], chiaro: () => ['#ffffff', '#1f2330'] };
(async () => {
  const svg = JSON.parse(execFileSync('php', ['-r', 'require $argv[1]; $o=[]; foreach (array_slice($argv,2) as $i) $o[$i]=icona_svg($i); echo json_encode($o);',
    path.join(R, 'cms/includes/icone.php'), ...Object.values(RETI).map(r => r[0])]).toString());
  const br = await chromium.launch(); const p = await br.newPage({ viewport: { width: 48, height: 48 } });
  const fuori = path.join(R, 'cms/sito/assets/email'); require('fs').mkdirSync(fuori, { recursive: true });
  for (const [rete, [ico, colore]] of Object.entries(RETI)) for (const [stile, f] of Object.entries(STILI)) {
    const [fondo, segno] = f(colore);
    await p.setContent(`<html><body style="margin:0;background:transparent"><div style="width:48px;height:48px;border-radius:50%;background:${fondo};color:${segno};display:flex;align-items:center;justify-content:center">${svg[ico].replace('width="24" height="24"', 'width="26" height="26"')}</div></body></html>`);
    await p.screenshot({ path: path.join(fuori, `${rete}-${stile}.png`), omitBackground: true, clip: { x: 0, y: 0, width: 48, height: 48 } });
  }
  await br.close();
  execFileSync('python3', ['-I', '-c', 'import sys,glob\nfrom PIL import Image\nfor f in glob.glob(sys.argv[1]+"/*.png"):\n  Image.open(f).convert("RGBA").quantize(32, method=Image.Quantize.FASTOCTREE).save(f, optimize=True)', fuori]);
  console.log('icone scritte in', fuori);
})();
