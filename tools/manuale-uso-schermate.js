// Le schermate del manuale d'uso (1.4.2): esegue gli esempi del manuale come una persona e fotografa ogni passo.
// Se un passo del manuale non si può fare, lo script si ferma: il manuale e il pannello restano d'accordo.
// Serve un plumeCMS appena installato con il kit Attività (prove/prova-kit.js ... attivita ...), utente prova.
// uso: node tools/manuale-uso-schermate.js <base> <utente> <password> [solo un capitolo, es. 04]
// Scrive cms/docs/immagini/manuale-uso/<capitolo>-<nome>.jpg (JPEG: il PDF del manuale deve restare sotto 1900 KB).
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const [B, UTENTE, PASSWORD, SOLO] = process.argv.slice(2);
const USCITA = path.join(__dirname, '..', 'cms', 'docs', 'immagini', 'manuale-uso');
const FOTO_PROVA = path.join(__dirname, '..', 'esempi', 'manuale-uso');
// Le foto nascono PNG qui (senza perdita) e diventano JPEG una volta sola, alla fine (stringi()).
const GREZZE = path.join(require('os').tmpdir(), 'manuale-uso-png');

let p, ctx;
const dorme = (ms) => new Promise(r => setTimeout(r, ms));

// I segni sopra la schermata: un riquadro intorno a un elemento e un pallino con il numero del passo.
async function segna(dove, voci) {
  await dove.evaluate((voci) => {
    for (const v of voci) {
      const e = typeof v.sel === 'string' ? document.querySelector(v.sel) : null;
      if (!e) throw new Error('segno: non trovo ' + v.sel);
      const r = e.getBoundingClientRect();
      const box = document.createElement('div');
      box.className = 'mu-segno';
      Object.assign(box.style, { position: 'absolute', left: (r.left + scrollX - 4) + 'px', top: (r.top + scrollY - 4) + 'px', width: (r.width + 8) + 'px',
        height: (r.height + 8) + 'px', border: '3px solid #e8553e', borderRadius: '8px', zIndex: 99998, pointerEvents: 'none', boxSizing: 'border-box' });
      document.body.appendChild(box);
      if (v.n) {
        const b = document.createElement('div');
        b.className = 'mu-segno';
        b.textContent = v.n;
        const sx = Math.max(2, v.lato === 'destra' ? r.right + scrollX - 6 : r.left + scrollX - 16);
        Object.assign(b.style, { position: 'absolute', left: sx + 'px', top: Math.max(2, r.top + scrollY - 16) + 'px', width: '26px', height: '26px', borderRadius: '50%',
          background: '#e8553e', color: '#fff', font: '700 15px/26px Inter, sans-serif', textAlign: 'center', zIndex: 99999, pointerEvents: 'none',
          boxShadow: '0 1px 4px rgba(0,0,0,.35)' });
        document.body.appendChild(b);
      }
    }
  }, voci.map(v => typeof v === 'string' ? { sel: v } : v));
}
const togliSegni = (dove) => dove.evaluate(() => document.querySelectorAll('.mu-segno').forEach(e => e.remove()));

// Una foto: tutta la finestra, un elemento (con un po' di margine) o un rettangolo. Sempre JPEG.
async function foto(nome, { sel, clip, margine = 12, segni = [], dove = null, altezza } = {}) {
  if (SOLO && !nome.startsWith(SOLO)) return;
  const fr = dove || p;
  await p.evaluate(() => scrollTo(0, 0)); await dorme(100);
  if (segni.length) await segna(fr, segni);
  let area = clip;
  if (sel) {
    const r = await p.locator(sel).first().boundingBox();
    if (!r) throw new Error('foto ' + nome + ': non trovo ' + sel);
    area = { x: Math.max(0, r.x - margine), y: Math.max(0, r.y - margine), width: r.width + 2 * margine, height: (altezza || r.height) + 2 * margine };
  }
  await p.screenshot({ path: path.join(GREZZE, nome + '.png'), clip: area, fullPage: !!area });
  if (segni.length) await togliSegni(fr);
  console.log(nome);
}

const vai = async (u) => { await p.goto(B + '/admin/' + u, { waitUntil: 'networkidle' }); await dorme(300); };
const tela = () => p.frameLocator('#ed-iframe');
const telaFrame = () => p.frames().find(f => f.url().includes('editor-anteprima.php'));
async function apriEditor(u) {
  await vai(u);
  await p.waitForFunction(() => document.querySelector('#ed-iframe') && document.querySelector('#ed-iframe').contentDocument
    && document.querySelector('#ed-iframe').contentDocument.querySelector('.ed-sezione, .ed-pagina-vuota'));
  await dorme(700);
  if (await p.locator('#ed-chiudi-revisioni').isVisible()) await p.click('#ed-chiudi-revisioni');
}

// Da PNG a JPEG, una volta sola: al massimo LARGO px (nel PDF sono 17 cm), qualità QUALITA. Il PDF deve stare sotto 1900 KB.
const LARGO = 800, QUALITA = 38;
function stringi() {
  require('child_process').execFileSync('python3', ['-c', `
import os, sys
from PIL import Image
da, a, largo, q = sys.argv[1], sys.argv[2], int(sys.argv[3]), int(sys.argv[4])
for f in sorted(os.listdir(da)):
    if not f.endswith('.png'): continue
    im = Image.open(os.path.join(da, f)).convert('RGB')
    if im.width > largo: im = im.resize((largo, round(im.height * largo / im.width)), Image.LANCZOS)
    im.save(os.path.join(a, f[:-4] + '.jpg'), 'JPEG', quality=q, optimize=True, progressive=True)
`, GREZZE, USCITA, String(LARGO), String(QUALITA)], { stdio: 'inherit' });
}
if (process.argv[2] === '--stringi') { stringi(); process.exit(0); }

const capitoli = {};

// 01. Il pannello in cinque minuti
capitoli['01'] = async () => {
  await p.context().clearCookies();
  await vai('login.php');
  await foto('01-accesso', { sel: 'form', margine: 40, segni: [{ sel: '#username', n: 1 }, { sel: '#password', n: 2 }, { sel: 'button[type=submit]', n: 3 }] });
  await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await vai('index.php');
  await foto('01-dashboard', { clip: { x: 0, y: 0, width: 1200, height: 760 }, segni: [{ sel: '.admin-nav', n: 1 }, { sel: '.db-azioni', n: 2 }] });
  // La colonna di sinistra è fissa: per fotografarne il fondo la finestra deve contenerla tutta.
  await p.setViewportSize({ width: 1200, height: 1000 });
  await foto('01-menu-basso', { sel: '.admin-sidebar-footer', margine: 8, segni: [{ sel: '.admin-sidebar-footer a[href="account.php"]', n: 1 }] });
  await p.setViewportSize({ width: 1200, height: 800 });
  await vai('account.php');
  await foto('01-aspetto', { sel: '#aspetto', segni: [{ sel: '.asp-voce:nth-child(3)', n: 1 }, { sel: '#aspetto button', n: 2 }] });
};

// 02. Il tipo di sito in prova
capitoli['02'] = async () => {
  await vai('index.php');
  await foto('02-tipo-sito', { sel: '#tipo-sito', segni: [{ sel: '.db-kit-carta:not(.attuale) button[data-kit="blog"]', n: 1 }, { sel: '#tipo-sito .db-kit-piede button', n: 2, lato: 'destra' }] });
};

// 03. I primi passi
capitoli['03'] = async () => {
  await vai('index.php');
  await foto('03-primi-passi', { sel: '.db-passi', altezza: 330 });
};

// 04. Il primo articolo: "Chiusi per ferie", scritto nell'editor e rifinito nella scheda.
async function nuovoArticolo(titolo, slug, paragrafi) {
  await vai('index.php');
  await Promise.all([p.waitForNavigation(), p.click('.db-azioni a:has-text("+ Articolo")')]);
  await apriEditor(p.url().split('/admin/')[1]);
  await p.click('#ed-apri-impostazioni'); await dorme(300);
  await p.fill('#ed-form-impostazioni [name=titolo]', titolo);
  await p.fill('#ed-form-impostazioni [name=slug]', slug);
  return async (fotoTitolo, fotoTesto) => {
    if (fotoTitolo) await foto(fotoTitolo, { sel: '#ed-dialogo-impostazioni', altezza: 240, segni: [{ sel: '#ed-form-impostazioni [name=titolo]', n: 1 }, { sel: '#ed-form-impostazioni .ed-slug', n: 2 }] });
    await p.click('#ed-form-impostazioni button[type=submit]'); await dorme(800);
    await tela().locator('.ed-w-testo').first().dblclick(); await dorme(500);
    await p.locator('#ed-controlli .ql-editor').click();
    await p.keyboard.press('Control+A');
    for (let k = 0; k < paragrafi.length; k++) { if (k) await p.keyboard.press('Enter'); await p.keyboard.type(paragrafi[k]); }
    await dorme(900);
    if (fotoTesto) await foto(fotoTesto, { clip: { x: 0, y: 0, width: 1200, height: 560 }, segni: [{ sel: '#ed-controlli .ql-editor', n: 3 }, { sel: '#ed-salva', n: 4 }] });
    await p.click('#ed-salva');
    await p.waitForFunction(() => /Bozza salvata/.test(document.querySelector('#ed-stato').textContent));
  };
}
capitoli['04'] = async () => {
  await vai('index.php');
  await foto('04-nuovo', { sel: '.db-azioni', margine: 14, segni: [{ sel: '.db-azioni a:first-child', n: 1 }] });
  const scrivi = await nuovoArticolo('Chiusi per ferie dal 10 al 20 agosto', 'chiusi-per-ferie', [
    'Dal 10 al 20 agosto il negozio resta chiuso: ci prendiamo qualche giorno di mare.',
    'Ci rivediamo lunedì 21 agosto alle 9, con gli orari di sempre. Per le urgenze rispondiamo alle email una volta al giorno.']);
  await scrivi('04-titolo', '04-testo');
  await foto('04-indietro', { clip: { x: 0, y: 0, width: 1200, height: 54 }, segni: [{ sel: 'a.ed-indietro', n: 5 }, { sel: '#ed-stato', n: '' }] });
  await Promise.all([p.waitForNavigation(), p.click('a.ed-indietro')]);
  await p.waitForLoadState('networkidle');
  await p.fill('[name=excerpt]', 'Dal 10 al 20 agosto siamo chiusi. Si riapre lunedì 21 con gli orari di sempre.');
  await p.fill('[name=categoria_nuova]', 'Avvisi');
  await p.fill('#sc-tag-nuovo', 'orari'); await p.keyboard.press('Enter');
  await p.click('button:has-text("Scegli l\'immagine")'); await dorme(400);
  await p.setInputFiles('#sc-media-file', path.join(FOTO_PROVA, 'agosto.jpg'));
  await p.waitForFunction(() => !document.querySelector('#sc-immagine-anteprima').hidden);
  await p.fill('[name=immagine_alt]', 'Il mare d\'agosto, con un ombrellone rosso');
  await dorme(500);
  await foto('04-scheda-destra', { sel: '.sc-scatola:has(h3:text-is("Categorie"))', altezza: 600, segni: [
    { sel: '[name=categoria_nuova]', n: 6 }, { sel: '#sc-tag-nuovo', n: 7 }, { sel: '#sc-immagine-anteprima', n: 8 }] });
  await foto('04-estratto', { sel: '.sc-scatola:has(h3:text-is("Estratto"))', segni: [{ sel: '[name=excerpt]', n: 9 }] });
  await p.locator('[data-valore=pubblica]').scrollIntoViewIfNeeded();
  await foto('04-pubblica', { sel: '.sc-scatola:has(h3:text-is("Pubblica"))', segni: [{ sel: '#sc-stato', n: 10 }, { sel: 'button[data-valore=pubblica]', n: 11 }] });
  await p.selectOption('#sc-stato', 'pubblicato');
  await Promise.all([p.waitForNavigation(), p.click('button[data-valore=pubblica]')]);
  await p.goto(B + '/chiusi-per-ferie/', { waitUntil: 'networkidle' });
  await foto('04-sul-sito', { clip: { x: 0, y: 0, width: 1200, height: 760 } });
};

// 05. Programmare un articolo
capitoli['05'] = async () => {
  const scrivi = await nuovoArticolo('Da settembre apriamo anche il sabato', 'aperti-il-sabato', [
    'Da settembre il negozio è aperto anche il sabato mattina, dalle 9 alle 13.']);
  await scrivi(null, null);
  await Promise.all([p.waitForNavigation(), p.click('a.ed-indietro')]);
  await p.waitForLoadState('networkidle');
  const d = new Date(Date.now() + 5 * 86400000);
  await p.fill('[name=data]', d.toISOString().slice(0, 10));
  await p.fill('[name=ora]', '08:00');
  await p.selectOption('#sc-stato', 'pubblicato');
  await foto('05-data', { sel: '.sc-scatola:has(h3:text-is("Pubblica"))', segni: [{ sel: '#sc-stato', n: 1 }, { sel: 'input[name=data]', n: 2 }, { sel: 'button[data-valore=pubblica]', n: 3 }] });
  await Promise.all([p.waitForNavigation(), p.click('button[data-valore=pubblica]')]);
  await vai('contenuti.php?tipo=articoli');
  await foto('05-elenco', { sel: 'table', margine: 10, altezza: 190 });
  await vai('index.php');
  await foto('05-prossime', { sel: '.db-scatola:has(h3:has-text("Prossime uscite"))', margine: 8 });
};

// I controlli del pannello Modifica, per etichetta (i gruppi chiusi si aprono da soli).
async function ctl(etichetta) {
  const c = p.locator('#ed-controlli .ed-controllo').filter({ has: p.locator('.ed-etichetta, label, b', { hasText: etichetta }) }).first();
  if (!(await c.isVisible())) {
    const sez = p.locator('#ed-controlli details.ed-sez').filter({ has: c });
    if (await sez.count()) await sez.locator('summary').first().click();
  }
  return c;
}
async function scriviCtl(etichetta, testo) { const c = await ctl(etichetta); await c.locator('input[type=text], textarea').first().fill(testo); await dorme(250); }
async function widget(tipo) { await p.click('.ed-schede-pannello button[data-pannello="widget"]'); await p.click(`#ed-elenco-widget .ed-widget-voce[data-tipo="${tipo}"]`); await dorme(600); }
async function voce(i, campi) {
  const v = p.locator('#ed-controlli .ed-voce').nth(i);
  if (!(await v.count())) { await p.locator('#ed-controlli button:has-text("+ Aggiungi")').first().click(); await dorme(300); }
  else if (await v.locator('.ed-voce-corpo').isHidden()) { await v.locator('.ed-voce-testa b').click(); await dorme(300); }
  for (const [et, val] of Object.entries(campi)) {
    await p.locator('#ed-controlli .ed-voce').nth(i).locator('.ed-voce-corpo .ed-controllo').filter({ has: p.locator('.ed-etichetta, label', { hasText: et }) }).first().locator('input[type=text], textarea').first().fill(val);
  }
  await dorme(300);
}
async function scegliFoto(file) {
  await (await ctl('Immagine')).locator('button:has-text("Scegli")').click(); await dorme(400);
  await p.setInputFiles('#ed-media-file', path.join(FOTO_PROVA, file));
  await p.waitForFunction(() => !document.querySelector('#ed-dialogo-media').open); await dorme(800);
}

// 08. L'editor: com'è fatto
capitoli['08'] = async () => {
  await apriEditor('editor.php?tipo=pagine&id=1');
  await foto('08-editor', { clip: { x: 0, y: 0, width: 1200, height: 800 }, segni: [
    { sel: '.ed-schede-pannello', n: 1 }, { sel: '#ed-tela', n: 2 }, { sel: '.ed-misure', n: 3 }, { sel: '.ed-storia', n: 4 },
    { sel: '#ed-apri-impostazioni', n: 5 }, { sel: '#ed-apri-revisioni', n: 6 }, { sel: '#ed-vedi', n: 7 }, { sel: '#ed-salva', n: 8 }, { sel: '#ed-pubblica', n: 9 }] });
  await tela().locator('.ed-w-hero').first().dblclick(); await dorme(600);
  await foto('08-modifica', { clip: { x: 0, y: 0, width: 1200, height: 520 }, segni: [{ sel: '#ed-schede-controlli', n: 1 }, { sel: '#ed-modifica-azioni', n: 2 }] });
};

// 09. Esempio: la pagina "Lavora con noi"
capitoli['09'] = async () => {
  await vai('index.php');
  await Promise.all([p.waitForNavigation(), p.click('.db-azioni a:has-text("+ Pagina")')]);
  await apriEditor(p.url().split('/admin/')[1]);
  await foto('09-nuova', { clip: { x: 0, y: 0, width: 1200, height: 520 } });
  await p.click('#ed-apri-impostazioni'); await dorme(300);
  await p.fill('#ed-form-impostazioni [name=titolo]', 'Lavora con noi');
  await p.fill('#ed-form-impostazioni [name=slug]', 'lavora-con-noi');
  await p.click('#ed-form-impostazioni button[type=submit]'); await dorme(1200);
  // Il titolo grande e il testo
  await tela().locator('.ed-w-titolo').first().dblclick(); await dorme(500);
  await scriviCtl('Titolo', 'Cerchiamo una persona *curiosa*');
  await foto('09-titolo', { clip: { x: 0, y: 0, width: 1200, height: 560 }, segni: [{ sel: '#ed-controlli .ed-controllo:nth-child(2) input', n: 1 }] });
  await tela().locator('.ed-w-testo').first().dblclick(); await dorme(500);
  await p.locator('#ed-controlli .ql-editor').click(); await p.keyboard.press('Control+A');
  await p.keyboard.type('In negozio arrivano ogni giorno persone con una domanda. Cerchiamo qualcuno che abbia voglia di trovare la risposta insieme a loro.');
  await dorme(600);
  // Una sezione nuova, a due colonne
  await tela().locator('#ed-pagina .ed-sezione .ed-aggiungi').last().click(); await dorme(800);
  await p.locator('.ed-colonne-scelta button[title="2 colonne"]').click(); await dorme(800);
  await foto('09-colonne', { clip: { x: 0, y: 0, width: 1200, height: 640 }, segni: [{ sel: '.ed-colonne-scelta', n: 2 }] });
  // Colonna di sinistra: la foto
  await tela().locator('#ed-pagina .ed-sezione').last().locator('.ed-colonna').first().click({ position: { x: 30, y: 30 } }); await dorme(400);
  await widget('immagine');
  await foto('09-widget', { clip: { x: 0, y: 0, width: 1200, height: 640 }, segni: [{ sel: '.ed-schede-pannello button[data-pannello="widget"]', n: 3 }, { sel: '#ed-elenco-widget .ed-widget-voce[data-tipo="immagine"]', n: 4 }] });
  await p.click('.ed-schede-pannello button[data-pannello="modifica"]'); await dorme(300);
  await scegliFoto('bottega.jpg');
  await scriviCtl('Testo alternativo', 'Gli scaffali del negozio, con i vasi colorati');
  await foto('09-immagine', { clip: { x: 0, y: 0, width: 1200, height: 640 }, segni: [{ sel: '#ed-controlli .ed-riga-immagine', n: 5 }] });
  // Colonna di destra: titolo, elenco, pulsante
  await tela().locator('#ed-pagina .ed-sezione').last().locator('.ed-colonna').nth(1).click({ position: { x: 30, y: 30 } }); await dorme(400);
  await widget('titolo');
  await scriviCtl('Titolo', 'Cosa cerchiamo');
  await widget('elenco');
  await voce(0, { Testo: 'Ti piace parlare con le persone' });
  await voce(1, { Testo: 'Sai usare il computer per le cose di tutti i giorni' });
  await voce(2, { Testo: 'Puoi lavorare il sabato mattina' });
  await foto('09-elenco', { clip: { x: 0, y: 0, width: 1200, height: 700 }, segni: [{ sel: '#ed-controlli .ed-voce:nth-child(1) .ed-voce-testa', n: 6 }, { sel: '#ed-controlli .ed-voce + button', n: 7 }] });
  await widget('pulsanti');
  await voce(0, { Testo: 'Scrivici', Link: '/contatti/' });
  await foto('09-pulsante', { clip: { x: 0, y: 0, width: 1200, height: 700 }, segni: [{ sel: '#ed-controlli .ed-voce-corpo', n: 8 }] });
  // La scheda Stile
  await p.click('#ed-schede-controlli button[data-scheda="stile"]'); await dorme(400);
  await foto('09-stile', { clip: { x: 0, y: 0, width: 360, height: 640 }, segni: [{ sel: '#ed-schede-controlli button[data-scheda="stile"]', n: 9 }] });
  // Al telefono
  await p.click('.ed-misure button[data-misura="m"]'); await dorme(1200);
  await foto('09-telefono', { clip: { x: 0, y: 0, width: 1200, height: 800 }, segni: [{ sel: '.ed-misure button[data-misura="m"]', n: 10 }] });
  await p.click('.ed-misure button[data-misura="d"]'); await dorme(600);
  // Pubblica
  await p.click('#ed-pubblica');
  await p.waitForFunction(() => /Pubblicata alle/.test(document.querySelector('#ed-stato').textContent));
  await foto('09-pubblicata', { clip: { x: 500, y: 0, width: 700, height: 54 }, segni: [{ sel: '#ed-stato', n: 11 }] });
  await p.goto(B + '/lavora-con-noi/', { waitUntil: 'networkidle' });
  await foto('09-sul-sito', { clip: { x: 0, y: 0, width: 1200, height: 1100 } });
};

// 06. Categorie e tag
capitoli['06'] = async () => {
  await vai('categories.php');
  await p.fill('form.sc-corpo [name=name]', 'Novità');
  await foto('06-categorie', { clip: { x: 240, y: 56, width: 960, height: 520 }, segni: [{ sel: 'form.sc-corpo [name=name]', n: 1 }, { sel: 'form.sc-corpo button.btn-primary', n: 2 }] });
  await Promise.all([p.waitForNavigation(), p.click('form.sc-corpo button.btn-primary')]);
};

// 07. Le immagini: Media
capitoli['07'] = async () => {
  await vai('media.php');
  await p.setInputFiles('input[type=file][name=file]', path.join(FOTO_PROVA, 'squadra.jpg'));
  await foto('07-carica', { clip: { x: 240, y: 56, width: 960, height: 300 }, segni: [{ sel: 'input[type=file][name=file]', n: 1 }, { sel: 'form[enctype] button[type=submit]', n: 2 }] });
  await Promise.all([p.waitForNavigation(), p.click('form[enctype] button[type=submit]')]);
  await p.waitForLoadState('networkidle');
  await foto('07-libreria', { clip: { x: 240, y: 56, width: 960, height: 700 } });
};

// 10. Le sezioni pronte
capitoli['10'] = async () => {
  await apriEditor('editor.php?tipo=pagine&id=6');
  await tela().locator('#ed-pagina .ed-sezione').last().click({ position: { x: 20, y: 20 } }); await dorme(300);
  await p.click('.ed-schede-pannello button[data-pannello="sezioni"]'); await dorme(400);
  await p.fill('#ed-cerca-sezioni', 'domande'); await dorme(400);
  await foto('10-sezioni', { clip: { x: 0, y: 0, width: 1200, height: 640 }, segni: [{ sel: '.ed-schede-pannello button[data-pannello="sezioni"]', n: 1 }, { sel: '#ed-cerca-sezioni', n: 2 }, { sel: '.ed-sezione-voce:not(.ed-nascosto)', n: 3 }] });
  await p.locator('.ed-sezione-voce:visible').first().click(); await dorme(1200);
  await tela().locator('#ed-pagina .ed-sezione').last().scrollIntoViewIfNeeded(); await dorme(500);
  await foto('10-inserita', { clip: { x: 0, y: 0, width: 1200, height: 800 } });
  await p.click('#ed-pubblica');
  await p.waitForFunction(() => /Pubblicata alle/.test(document.querySelector('#ed-stato').textContent));
};

// 11. L'apertura della pagina
capitoli['11'] = async () => {
  await apriEditor('editor.php?tipo=pagine&id=1');
  await tela().locator('.ed-w-hero').first().dblclick(); await dorme(600);
  const disp = await ctl('Disposizione');
  await disp.scrollIntoViewIfNeeded();
  await disp.locator('select').selectOption('centrata'); await dorme(1500);
  await foto('11-disposizione', { clip: { x: 0, y: 0, width: 1200, height: 700 }, segni: [{ sel: '#ed-controlli .ed-controllo select', n: 1 }] });
  await p.click('#ed-schede-controlli button[data-scheda="stile"]'); await dorme(400);
  const sezFoto = p.locator('#ed-controlli details.ed-sez').filter({ has: p.locator('summary', { hasText: /^Foto$/ }) });
  if (await sezFoto.count()) { if ((await sezFoto.getAttribute('open')) === null) await sezFoto.locator('summary').click(); await dorme(400);
    await foto('11-foto', { clip: { x: 0, y: 0, width: 360, height: 800 } }); }
};

// 12. Tornare indietro: revisioni e versioni
capitoli['12'] = async () => {
  await apriEditor('editor.php?tipo=pagine&id=6');
  await p.click('#ed-apri-revisioni'); await dorme(1200);
  await foto('12-revisioni', { clip: { x: 0, y: 0, width: 1200, height: 700 }, segni: [{ sel: '#ed-elenco-revisioni', n: 1 }, { sel: '#ed-tutte-versioni', n: 2 }] });
  await vai('versioni.php');
  await foto('12-versioni', { clip: { x: 240, y: 56, width: 960, height: 600 } });
};

// 13. Stile del sito
capitoli['13'] = async () => {
  await vai('design.php');
  await foto('13-colori', { clip: { x: 240, y: 56, width: 960, height: 700 }, segni: [{ sel: '.dg button[data-scheda="colori"], .dg button[data-scheda]', n: 1 }, { sel: '.dg-sezione:not([hidden]) details', n: 2 }] });
  await p.click('.dg button[data-scheda="sito"]'); await dorme(500);
  await foto('13-identita', { clip: { x: 240, y: 56, width: 960, height: 740 }, segni: [{ sel: '.dg button[data-scheda="sito"]', n: 3 }] });
};

// 14. Temi
capitoli['14'] = async () => {
  await vai('temi.php');
  await foto('14-temi', { clip: { x: 240, y: 56, width: 960, height: 740 } });
};

// 15. Header e footer
capitoli['15'] = async () => {
  await vai('modelli.php');
  await foto('15-modelli', { clip: { x: 240, y: 56, width: 960, height: 440 }, segni: [{ sel: '.md-stella.on', n: 1 }] });
};

// 16. Menu: "Lavora con noi" nel menu principale
capitoli['16'] = async () => {
  await vai('menu.php');
  const d = p.locator('details', { has: p.locator('summary', { hasText: /^Pagine/ }) }).first();
  if ((await d.getAttribute('open')) === null) await d.locator('summary').click();
  await d.locator('label', { hasText: 'Lavora con noi' }).locator('input').check();
  await foto('16-aggiungi', { clip: { x: 240, y: 56, width: 960, height: 600 }, segni: [{ sel: 'input[data-nome="Lavora con noi"]', n: 1 }, { sel: '.mn-scelte-azioni button', n: 2 }] });
  await d.locator('button:has-text("Aggiungi al menu")').click(); await dorme(500);
  await foto('16-struttura', { clip: { x: 240, y: 56, width: 960, height: 720 }, segni: [{ sel: 'button.btn-primary[type=submit]', n: 3 }] });
  await Promise.all([p.waitForNavigation(), p.click('button.btn-primary[type=submit]:has-text("Salva il menu")')]);
  await p.goto(B + '/', { waitUntil: 'networkidle' });
  await foto('16-sul-sito', { clip: { x: 0, y: 0, width: 1200, height: 90 } });
};

// 17. Form: le candidature di "Lavora con noi"
capitoli['17'] = async () => {
  await vai('form.php?nuovo=1');
  await p.fill('[name=nome]', 'Candidature');
  await p.fill('[name=titolo]', 'Mandaci la tua candidatura');
  await p.fill('[name=intro]', 'Due righe su di te bastano. Ti rispondiamo entro una settimana.');
  await p.fill('[name=pulsante]', 'Invia la candidatura');
  await foto('17-form', { clip: { x: 240, y: 56, width: 960, height: 760 }, segni: [{ sel: '[name=nome]', n: 1 }, { sel: '[name=titolo]', n: 2 }, { sel: '#fm-campi', n: 3 }, { sel: 'button.btn-primary', n: 4, lato: 'destra' }] });
  await Promise.all([p.waitForNavigation(), p.click('button.btn-primary:has-text("Salva")')]);
  // Nella pagina, sotto il pulsante "Scrivici"
  await apriEditor('editor.php?tipo=pagine&id=6');
  await tela().locator('#ed-pagina .ed-w-pulsanti').first().click(); await dorme(400);
  await widget('modulo');
  const q = await ctl('Quale modulo');
  const val = await q.locator('option', { hasText: 'Candidature' }).getAttribute('value');
  await q.locator('select').selectOption(val); await dorme(1500);
  await foto('17-widget', { clip: { x: 0, y: 0, width: 1200, height: 700 }, segni: [{ sel: '#ed-controlli .ed-controllo select', n: 5 }] });
  await p.click('#ed-pubblica');
  await p.waitForFunction(() => /Pubblicata alle/.test(document.querySelector('#ed-stato').textContent));
  // Una persona compila il form
  const v = await ctx.newPage();
  await v.goto(B + '/lavora-con-noi/', { waitUntil: 'networkidle' });
  const m = v.locator('form.modulo, .modulo form').first();
  await m.scrollIntoViewIfNeeded();
  await m.locator('input[type=text]').first().fill('Giulia Rossi');
  await m.locator('input[type=email]').first().fill('giulia@esempio.it');
  await m.locator('textarea').first().fill('Lavoro da tre anni in una libreria e mi piacerebbe cambiare.');
  const spunta = m.locator('input[type=checkbox]'); for (let i = 0; i < await spunta.count(); i++) await spunta.nth(i).check();
  const r = await m.boundingBox(); const sy = await v.evaluate(() => scrollY);
  await v.screenshot({ path: path.join(GREZZE, '17-sul-sito.png'), clip: { x: 0, y: r.y + sy - 30, width: 1200, height: r.height + 60 }, fullPage: true });
  console.log('17-sul-sito');
  await m.locator('button[type=submit]').click(); await v.waitForLoadState('networkidle'); await dorme(800);
  await v.close();
  await vai('form-submissions.php');
  await foto('17-invii', { clip: { x: 240, y: 56, width: 960, height: 420 } });
};

// 18. Popup
capitoli['18'] = async () => {
  await vai('popup.php?nuovo=1');
  await p.fill('[name=nome]', 'Saldi di gennaio');
  await p.fill('[name=occhiello]', 'Solo questa settimana');
  await p.fill('[name=titolo]', 'Saldi: meno 30% su tutto');
  await p.fill('[name=testo]', 'Vieni in negozio entro domenica.');
  await p.fill('[name=pulsante_testo]', 'Come arrivare');
  await p.fill('[name=pulsante_link]', '/contatti/');
  await p.selectOption('[name=dove]', 'tutte');
  await p.selectOption('[name=innesco]', 'secondi');
  await p.fill('[name=valore]', '3');
  await p.check('[name=attivo]');
  await foto('18-popup', { clip: { x: 240, y: 56, width: 960, height: 760 }, segni: [{ sel: '[name=titolo]', n: 1 }, { sel: '[name=dove]', n: 2 }, { sel: '[name=innesco]', n: 3 }, { sel: '[name=attivo]', n: 4 }] });
  await Promise.all([p.waitForNavigation(), p.click('button.btn-primary:has-text("Salva")')]);
  const v = await ctx.newPage();
  await v.goto(B + '/', { waitUntil: 'networkidle' }); await dorme(4500);
  await v.screenshot({ path: path.join(GREZZE, '18-sul-sito.png'), clip: { x: 0, y: 0, width: 1200, height: 760 } });
  console.log('18-sul-sito');
  await v.close();
  // spento, per non coprire le schermate dopo
  await vai('popup.php');
  await Promise.all([p.waitForNavigation(), p.locator('a', { hasText: 'Saldi di gennaio' }).first().click()]);
  await p.uncheck('[name=attivo]');
  await Promise.all([p.waitForNavigation(), p.click('button.btn-primary:has-text("Salva")')]);
};

// 19. Newsletter
capitoli['19'] = async () => {
  await vai('newsletter.php?scheda=iscritti');
  await foto('19-iscritti', { clip: { x: 240, y: 56, width: 960, height: 520 } });
  await vai('newsletter.php');
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("+ Nuova campagna")')]);
  await p.waitForLoadState('networkidle');
  await p.fill('[name=oggetto]', 'Da settembre siamo aperti anche il sabato');
  await p.locator('#editor .ql-editor').click();
  await p.keyboard.type('Ciao! Da settembre ci trovi in negozio anche il sabato mattina, dalle 9 alle 13. Ti aspettiamo.');
  await p.fill('[name=pulsante_testo]', 'Leggi la notizia');
  await p.fill('[name=pulsante_link]', '/aperti-il-sabato/');
  await foto('19-campagna', { clip: { x: 240, y: 56, width: 960, height: 760 }, segni: [{ sel: '[name=oggetto]', n: 1 }, { sel: '#editor', n: 2 }] });
  await vai('newsletter-flussi.php?nuovo=1');
  await foto('19-flussi', { clip: { x: 240, y: 56, width: 960, height: 600 } });
};

// 20. Download
capitoli['20'] = async () => {
  const pdf = path.join(require('os').tmpdir(), 'listino-2026.pdf');
  const t = await ctx.newPage(); await t.setContent('<h1>Listino 2026</h1><p>Prezzi validi dal 1 gennaio.</p>'); await t.pdf({ path: pdf }); await t.close();
  await vai('scaricabili.php');
  await p.setInputFiles('input[type=file][name=file]', pdf);
  await p.fill('[name=titolo]', 'Listino 2026');
  await foto('20-carica', { clip: { x: 240, y: 56, width: 960, height: 520 }, segni: [{ sel: 'input[type=file][name=file]', n: 1 }, { sel: '[name=titolo]', n: 2 }] });
  await Promise.all([p.waitForNavigation(), p.locator('form:has(input[type=file]) button[type=submit], form:has(input[type=file]) button.btn-primary').first().click()]);
  await foto('20-elenco', { clip: { x: 240, y: 56, width: 960, height: 520 } });
};

// 21. SEO e AEO
capitoli['21'] = async () => {
  await vai('seo.php?scheda=panoramica');
  await foto('21-panoramica', { clip: { x: 240, y: 56, width: 960, height: 700 } });
  await vai('seo.php?scheda=redirect');
  await foto('21-redirect', { clip: { x: 240, y: 56, width: 960, height: 560 } });
};

// 22. Utenti e ruoli: un redattore
capitoli['22'] = async () => {
  await vai('utenti.php');
  await p.fill('[name=username]', 'marta');
  await p.fill('[name=display_name]', 'Marta Bianchi');
  await p.fill('[name=email]', 'marta@esempio.it');
  await p.fill('[name=password]', 'Una-password-lunga-2026');
  const ruolo = p.locator('[name=role]').first();
  if (await ruolo.count()) await ruolo.selectOption('editor').catch(() => ruolo.selectOption({ index: 1 }));
  await foto('22-utenti', { clip: { x: 240, y: 56, width: 960, height: 620 }, segni: [{ sel: '[name=username]', n: 1 }, { sel: '[name=role]', n: 2 }] });
};

// 23. Le email
capitoli['23'] = async () => {
  await vai('impostazioni.php');
  await foto('23-email', { clip: { x: 240, y: 56, width: 960, height: 760 }, segni: [{ sel: '[name=smtp_host]', n: 1 }, { sel: '[name=prova_a]', n: 2 }] });
};

// 24. Il cron
capitoli['24'] = async () => {
  await vai('cron-log.php');
  const h = p.locator('h2', { hasText: 'Le due righe' });
  const r = await h.boundingBox();
  await foto('24-cron', { clip: { x: 240, y: r.y - 24, width: 960, height: 330 } });
  await foto('24-come-va', { clip: { x: 240, y: 56, width: 960, height: 560 } });
};

// 25. Sicurezza
capitoli['25'] = async () => {
  await vai('sicurezza.php');
  await foto('25-sicurezza', { clip: { x: 240, y: 56, width: 960, height: 700 } });
  await vai('account.php');
  const d = p.locator('.form-card', { hasText: 'Secondo fattore' }).first();
  const r = await d.boundingBox();
  await foto('25-due-fattori', { clip: { x: r.x - 10, y: r.y - 10, width: r.width + 20, height: r.height + 20 } });
};

// 26. Aggiornare plumeCMS
capitoli['26'] = async () => {
  await vai('aggiornamenti.php');
  await foto('26-aggiornamenti', { clip: { x: 240, y: 56, width: 960, height: 700 } });
};

// 27. Copia del sito
capitoli['27'] = async () => {
  await vai('copia-sito.php');
  await foto('27-copia', { clip: { x: 240, y: 56, width: 960, height: 620 } });
};

// 28. Controlla il server
capitoli['28'] = async () => {
  await vai('controlla-server.php');
  await foto('28-server', { clip: { x: 240, y: 56, width: 960, height: 640 } });
};

// 29. Importare da WordPress (il collegamento; il report viene dalla prova con un WordPress vero)
capitoli['29'] = async () => {
  await vai('importa-wordpress.php');
  await foto('29-collegati', { clip: { x: 240, y: 56, width: 960, height: 760 } });
  // Il report: schermata della prova contro un WordPress 6.6.2 vero (prove/prova-importa-wp.js), tagliata e messa in esempi/.
  if (!SOLO || '29'.startsWith(SOLO)) fs.copyFileSync(path.join(FOTO_PROVA, 'importa-report.png'), path.join(GREZZE, '29-report.png'));
};

module.exports = { capitoli };

(async () => {
  fs.mkdirSync(USCITA, { recursive: true }); fs.mkdirSync(GREZZE, { recursive: true });
  const br = await chromium.launch({ args: ['--lang=it-IT'], env: { ...process.env, LANG: 'it_IT.UTF-8', LANGUAGE: 'it' } });
  ctx = await br.newContext({ viewport: { width: 1200, height: 800 }, locale: 'it-IT', timezoneId: 'Europe/Rome', deviceScaleFactor: 1 });
  p = await ctx.newPage();
  p.on('dialog', d => d.accept());
  const err = []; p.on('pageerror', e => err.push(e.message));
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  // Un giro completo riparte da una cartella vuota: nel manuale restano solo le foto di questo giro.
  if (!SOLO) for (const d of [USCITA, GREZZE]) for (const f of fs.readdirSync(d)) if (/\.(jpg|png)$/.test(f)) fs.unlinkSync(path.join(d, f));
  for (const [n, fn] of Object.entries(capitoli).sort((a, b) => a[0].localeCompare(b[0]))) { // '10' e oltre sono chiavi numeriche: JavaScript le mette prima di '01'
    if (SOLO && !SOLO.startsWith(n) && !n.startsWith(SOLO)) continue;
    await fn();
  }
  if (err.length) console.log('errori JavaScript:', err);
  stringi();
  const tot = fs.readdirSync(USCITA).filter(f => f.endsWith('.jpg')).reduce((t, f) => t + fs.statSync(path.join(USCITA, f)).size, 0);
  console.log('immagini del manuale: ' + Math.round(tot / 1024) + ' KB');
  await br.close();
})().catch(e => { console.error('FERMO:', e.message); process.exit(1); });
