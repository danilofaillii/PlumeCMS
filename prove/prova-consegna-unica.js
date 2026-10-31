// 2.1.0: la consegna a Danilo con il pacchetto unico (tools/consegna.py). Controlla lo zip di consegna (cosa c'è dentro, il
// LEGGIMI, il manifest del pacchetto unico: solo voci che il motore installato conosce, nessuna destinazione doppia), poi
// carica e applica il pacchetto unico dal pannello di un plumeCMS già installato con prove/prova-sito-plumecms.js (pagine,
// Download, manuali, versione del CMS) e alla fine guarda il pannello aggiornato: la finestra Scegli dai Media c'è.
// Da provare su un 2.0.3 nuovo e su un 2.0.3 con il sito di plumeCMS già caricato (le parti della 2.0.0, con il manuale
// degli addon nel Download).
// uso: node prove/prova-consegna-unica.js <base> <cartella CMS installato> <utente> <password> <plumecms-V-consegna.zip> <versione>
const { chromium } = require('playwright');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync, spawnSync } = require('child_process');
const [B, DIR, UTENTE, PASSWORD, CONSEGNA, V] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const py = (codice, ...arg) => execFileSync('python3', ['-I', '-c', codice, ...arg], { encoding: 'utf8', maxBuffer: 64 << 20 });

(async () => {
  // 1. Lo zip di consegna.
  const nomi = JSON.parse(py('import sys,json,zipfile;print(json.dumps(zipfile.ZipFile(sys.argv[1]).namelist()))', CONSEGNA)).filter(x => !x.endsWith('/'));
  const unico = `plumecms-${V}-pacchetto-unico.zip`;
  ok('consegna: il pacchetto unico, lo zip completo, i due manuali, il LEGGIMI (e nient\'altro)',
    JSON.stringify(nomi.slice().sort()) === JSON.stringify([unico, `cms/plumecms-${V}.zip`, 'manuali/manuale-uso.pdf', 'manuali/manuale-temi.pdf', 'LEGGIMI.txt'].sort()), nomi);
  const leggimi = py('import sys,zipfile;print(zipfile.ZipFile(sys.argv[1]).read("LEGGIMI.txt").decode())', CONSEGNA);
  ok('LEGGIMI: un pacchetto solo, dove si carica, che versione deve comparire', leggimi.includes(unico) && /Impostazioni > Aggiornamenti/.test(leggimi) && leggimi.includes(`versione ${V}`) && !/parte-\d/.test(leggimi));
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'consegna-unica-'));
  py('import sys,zipfile;z=zipfile.ZipFile(sys.argv[1]);open(sys.argv[3],"wb").write(z.read(sys.argv[2]))', CONSEGNA, unico, path.join(tmp, unico));
  const pdf = JSON.parse(py('import sys,json,zipfile;z=zipfile.ZipFile(sys.argv[1]);print(json.dumps([z.getinfo(n).file_size for n in ("manuali/manuale-uso.pdf","manuali/manuale-temi.pdf")]))', CONSEGNA));
  ok('il manuale d\'uso è un PDF solo, sotto 8 MB', pdf[0] > 3e6 && pdf[0] < 8 * 1048576, pdf.map(x => Math.round(x / 1024) + ' KB'));

  // 2. Il manifest del pacchetto unico.
  const m = JSON.parse(py('import sys,zipfile;print(zipfile.ZipFile(sys.argv[1]).read("manifest.json").decode())', path.join(tmp, unico)));
  const note = ['nome', 'descrizione', 'creato', 'applica_dal', 'sql', 'file', 'redirect', 'elimina', 'articoli', 'test', 'azioni', 'pagine', 'editor', 'editor_ambiti', 'impostazioni', 'note', 'firma'];
  ok('manifest: solo voci che il motore 2.0.3 conosce', Object.keys(m).every(k => note.includes(k)), Object.keys(m));
  ok('manifest: azioni conosciute (sito-pagine-e-stile)', (m.azioni || []).every(a => ['attiva-sito-nuovo', 'sito-pagine-e-stile'].includes(a)), m.azioni);
  const dest = m.file.map(f => f.a);
  ok('manifest: nessuna destinazione doppia', new Set(dest).size === dest.length);
  ok('manifest: il codice del CMS (new/, con il motore e VERSIONE)', dest.includes('new/includes/aggiornamenti.php') && dest.includes('new/VERSIONE') && dest.includes('new/admin/assets/pannello.js'));
  ok('manifest: in Download gli zip del CMS e i due manuali, non più quello degli addon', [`uploads/scaricabili/plumecms-${V}.zip`, `uploads/scaricabili/plumecms-${V}-aggiornamento.zip`, 'uploads/scaricabili/manuale-uso.pdf', 'uploads/scaricabili/manuale-temi.pdf'].every(x => dest.includes(x))
    && !dest.includes('uploads/scaricabili/manuale-uso-addon.pdf') && (m.elimina || []).includes('uploads/scaricabili/manuale-uso-addon.pdf'));
  ok('manifest: pagine, stile e menu del sito', (m.pagine || []).length === 7 && !!m.editor);

  // 3. Caricato e applicato dal pannello, poi il sito visitato (prova-sito-plumecms.js).
  const r = spawnSync('node', [path.join(__dirname, 'prova-sito-plumecms.js'), B, DIR, UTENTE, PASSWORD, path.join(tmp, unico), V], { encoding: 'utf8', timeout: 900000 });
  const righe = (r.stdout || '').split('\n');
  righe.filter(x => /^KO/.test(x)).forEach(x => console.log('    ' + x));
  const fine = righe.reverse().find(x => / su \d+ OK$/.test(x)) || '';
  const [buoni, tutti] = (fine.match(/(\d+) su (\d+)/) || [0, 0, 1]).slice(1).map(Number);
  ok(`prova-sito-plumecms.js con il pacchetto unico: ${fine}`, r.status === 0 && buoni === tutti && tutti > 50, (r.stderr || '').slice(0, 300));

  // 4. Il pannello aggiornato: la finestra Scegli dai Media della 2.1.0.
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 160)));
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/seo.php?scheda=globale', { waitUntil: 'networkidle' });
  ok('pannello aggiornato: il campo immagine della SEO ha Scegli dai Media e la miniatura', await p.locator('.pn-immagine:has(#seo-og-immagine) .pn-immagine-scegli').count() === 1);
  await p.click('[data-scegli-media="#seo-og-immagine"]');
  await p.waitForSelector('dialog.pn-media[open] .pn-media-voce', { timeout: 10000 }).catch(() => {});
  ok('pannello aggiornato: la finestra dei Media si apre con le immagini del sito', await p.locator('dialog.pn-media .pn-media-voce').count() > 0);
  ok('nessun errore JavaScript nel pannello', err.length === 0, err);
  await br.close();
  fs.rmSync(tmp, { recursive: true, force: true });
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
