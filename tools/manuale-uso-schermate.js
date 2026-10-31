// Le schermate del manuale d'uso (1.4.2): esegue gli esempi del manuale come una persona e fotografa ogni passo.
// Se un passo del manuale non si può fare, lo script si ferma: il manuale e il pannello restano d'accordo.
// Serve un plumeCMS appena installato con il kit Attività (prove/prova-kit.js ... attivita ...), utente prova.
// uso: [CMS_DIR_MANUALE=<cartella del sito delle schermate>] [SU_MISURA_BASE=<un sito su misura, stesso utente>] node tools/manuale-uso-schermate.js <base> <utente> <password> [solo un capitolo, es. 04]
// Senza SU_MISURA_BASE mancano le due foto del sito su misura (02-su-misura-home, 07-sostituisci).
// Scrive cms/docs/immagini/manuale-uso/<capitolo>-<nome>.jpg (JPEG nitidi: dal 2.1.0 il manuale è un PDF solo, sotto 8 MB).
// 2.0.0: i capitoli 31n (le novità della 2.0.0 nei capitoli di prima: file riservati, form che dà un file, Scegli dai Media) e 32-40 (la Parte seconda, gli addon) girano dopo tutti gli altri e vogliono il server di posta finto:
//   python3 -I prove/smtp-finto.py <porta> <cartella> &   e   POSTA_MANUALE=<porta>:<cartella>
// Per rifare solo alcuni capitoli, esattamente quelli: SOLO_CAPITOLI=31n,32,33 (le foto degli altri restano come sono).
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const [B, UTENTE, PASSWORD, SOLO] = process.argv.slice(2);
const SOLI = (process.env.SOLO_CAPITOLI || '').split(',').filter(Boolean);
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

// Da PNG a JPEG, una volta sola: al massimo LARGO px, qualità QUALITA. 2.1.0: il limite di 1900 KB non c'è più (Danilo ha alzato il
// limite di caricamento del suo hosting): le foto restano larghe come sono fatte (1200 px, circa 180 punti per pollice sui 17 cm
// del PDF) e a qualità 65, così si leggono anche le scritte piccole; il manuale intero (Parte prima e seconda) resta sotto 8 MB.
const LARGO = 1200, QUALITA = 65;
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
  // 1.7.0: il sito su misura. Si apre la pagina delle domande e si compila come nell'esempio, senza premere il pulsante
  // (il sito delle schermate resta Attività).
  await Promise.all([p.waitForNavigation(), p.click('.db-kit-carta a[data-kit="su-misura"]')]);
  await p.check('.sm-carta input[value=negozio]');
  await p.fill('input[name=citta]', 'Ravenna');
  for (const [i, s] of ['Biciclette da città', 'Riparazioni', 'Noleggio'].entries()) await p.locator('input[name="servizi[]"]').nth(i).fill(s);
  await p.check('input[name="pagine[]"][value=faq]');
  await foto('02-su-misura', { clip: { x: 240, y: 56, width: 960, height: 1700 }, segni: [{ sel: '.sm-carta:has(input[value=negozio])', n: 1 }, { sel: 'input[name=citta]', n: 2 }, { sel: '.sm-servizi', n: 3 }, { sel: '.sm-pagine', n: 4 }, { sel: '#sm-componi', n: 5 }] });
  if (!process.env.SU_MISURA_BASE || (SOLO && !'02'.startsWith(SOLO.slice(0, 2)))) return;
  // Il sito che ne esce e Media > Da sostituire: da un sito su misura a parte (SU_MISURA_BASE, per esempio quello di
  // prove/prova-su-misura.js), perché il sito delle schermate è Attività.
  const altro = await p.context().browser().newContext({ viewport: { width: 1200, height: 800 }, locale: 'it-IT', deviceScaleFactor: 1 });
  const q = await altro.newPage();
  const SM = process.env.SU_MISURA_BASE;
  await q.goto(SM + '/', { waitUntil: 'networkidle' }); await dorme(400);
  await q.screenshot({ path: path.join(GREZZE, '02-su-misura-home.png'), clip: { x: 0, y: 0, width: 1200, height: 760 } }); console.log('02-su-misura-home');
  await q.goto(SM + '/admin/login.php'); await q.fill('#username', UTENTE); await q.fill('#password', PASSWORD);
  await Promise.all([q.waitForNavigation(), q.click('button[type=submit]')]);
  await q.goto(SM + '/admin/media.php?cartella=segnaposto', { waitUntil: 'networkidle' }); await dorme(400);
  await segna(q, [{ sel: '#md-da-sostituire', n: 1 }, { sel: '.md-sostituisci label', n: 2 }]);
  await q.screenshot({ path: path.join(GREZZE, '07-sostituisci.png'), clip: { x: 240, y: 56, width: 960, height: 640 } }); console.log('07-sostituisci');
  await altro.close();
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
  await p.setInputFiles('dialog.pn-media input[type=file]', path.join(FOTO_PROVA, 'agosto.jpg')); // 2.1.0: la finestra Scegli dai Media di tutto il pannello
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
  // 1.8.0: la frase in fondo al sito, in Impostazioni (solo la foto: non si salva niente).
  await vai('impostazioni.php');
  await foto('15-frase', { sel: 'form#firma-sito', segni: [{ sel: '#firma_mostra', n: 1 }, { sel: '#firma_testo', n: 2 }, { sel: '#firma_url', n: 3 }, { sel: '#firma-salva', n: 4, lato: 'destra' }] });
};

// 15f. Un riquadro in fondo a ogni articolo (1.6.0): Articoli > Aggiungi sempre alla fine, sotto "Chiusi per ferie" del capitolo 4.
capitoli['15f'] = async () => {
  await vai('contenuti.php?tipo=articoli');
  await foto('15f-linguetta', { clip: { x: 240, y: 56, width: 960, height: 230 }, segni: [{ sel: '#ct-fine-articolo', n: 1 }] });
  await Promise.all([p.waitForNavigation(), p.click('#ct-fine-articolo')]);
  await p.waitForLoadState('networkidle');
  await foto('15f-crea', { sel: '#fa-stato', segni: [{ sel: '#fa-stato .badge', n: '' }, { sel: '#fa-crea', n: 2 }] });
  await Promise.all([p.waitForNavigation(), p.click('#fa-crea')]);
  await apriEditor(p.url().split('/admin/')[1]);
  // Il titolo e il testo di partenza, riscritti
  // Doppio clic a sinistra: al centro della sezione, senza spazi sopra e sotto, sta il "+" che aggiunge una sezione.
  await tela().locator('.ed-w-titolo').first().dblclick({ position: { x: 16, y: 10 } }); await dorme(500);
  await scriviCtl('Titolo', 'Ti è stato utile? Vieni a trovarci');
  await tela().locator('.ed-w-testo').first().dblclick({ position: { x: 16, y: 10 } }); await dorme(500);
  await p.locator('#ed-controlli .ql-editor').click(); await p.keyboard.press('Control+A');
  await p.keyboard.type('Siamo in negozio dal martedì al sabato, dalle 9 alle 19. Se vuoi un consiglio con calma, prenota una visita: ti dedichiamo mezz\'ora solo per te.');
  await dorme(600);
  // Un pulsante sotto il testo (il testo è selezionato: il widget nuovo va subito dopo)
  await widget('pulsanti');
  await voce(0, { Testo: 'Prenota una visita', Link: '/contatti/' });
  await dorme(800);
  await foto('15f-editor', { clip: { x: 0, y: 0, width: 1200, height: 700 }, segni: [{ sel: '#ed-controlli .ed-voce-corpo', n: 5 }, { sel: '#ed-pubblica', n: 6 }] });
  await p.click('#ed-pubblica');
  await p.waitForFunction(() => /Pubblicata alle/.test(document.querySelector('#ed-stato').textContent));
  // Indietro: la pagina del riquadro dice Acceso
  await Promise.all([p.waitForNavigation(), p.click('a.ed-indietro')]);
  await p.waitForLoadState('networkidle');
  if (!(await p.locator('#fa-stato .badge-published').count())) throw new Error('15f: il riquadro pubblicato non risulta Acceso');
  await foto('15f-acceso', { sel: '#fa-stato', segni: [{ sel: '#fa-stato .badge-published', n: 7 }, { sel: '#fa-spegni', n: 8 }] });
  // Sul sito, sotto l'articolo
  await p.goto(B + '/chiusi-per-ferie/', { waitUntil: 'networkidle' });
  if (!(await p.locator('article #fine-articolo a', { hasText: 'Prenota una visita' }).count())) throw new Error('15f: sul sito il riquadro non c\'è sotto l\'articolo');
  const r = await p.locator('#fine-articolo').boundingBox();
  await foto('15f-sul-sito', { clip: { x: Math.max(0, r.x - 40), y: Math.max(0, r.y - 170), width: Math.min(1200, r.width + 80), height: r.height + 210 } });
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
  // 1.9.0: a chi arriva l'avviso di ogni compilazione.
  await p.fill('#fm-avviso-a', 'lavoro@studioprova.it, marta@studioprova.it');
  await foto('17-avviso', { sel: '.sc-scatola:has(#fm-avviso-a)', segni: [{ sel: 'input[name=avviso]', n: 1 }, { sel: '#fm-avviso-a', n: 2 }] });
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

// 19s. I social e l'email del mattino (1.5.0), sull'articolo "Chiusi per ferie" del capitolo 4.
capitoli['19s'] = async () => {
  // L'indirizzo degli avvisi: senza, le email del mattino non partono (la pagina Social lo direbbe in rosso).
  await vai('impostazioni.php');
  await p.fill('#notification_email', 'negozio@studioprova.it');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=save_posta]) button[type=submit]')]);
  await vai('social.php?scheda=impostazioni');
  await p.fill('#so-h-li', '#studioprova');
  await p.fill('#so-h-fb', '#studioprova');
  await p.fill('#so-h-ig', '#studioprova #negozio #bologna');
  await p.fill('#so-h-cat', 'Avvisi: #avvisi #orari');
  await p.fill('#so-wa-l', '/contatti/');
  await p.fill('#so-wa-e', 'Per scriverci:');
  await foto('19s-impostazioni', { clip: { x: 240, y: 56, width: 960, height: 760 }, segni: [{ sel: 'input[name=email_mattino]', n: 1 }, { sel: 'input[name="canale[linkedin]"]', n: 2 }, { sel: '#so-h-li', n: 3 }, { sel: '#so-h-cat', n: 4 }] });
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=impostazioni]) button.btn-primary')]);
  // Il riquadro Social della scheda
  await vai('contenuti.php?tipo=articoli');
  await Promise.all([p.waitForNavigation(), p.locator('a', { hasText: 'Chiusi per ferie' }).first().click()]);
  await p.waitForLoadState('networkidle');
  await p.fill('[name=social_testo]', 'Dal 10 al 20 agosto il negozio chiude: andiamo a prendere il sole \u{1F30A} Ci vediamo lunedì 21, con gli orari di sempre.');
  await p.locator('#social details summary').click();
  await p.fill('[name=social_whatsapp]', 'Ciao! Ti avviso prima che tu passi: dal 10 al 20 agosto siamo chiusi.');
  await Promise.all([p.waitForNavigation(), p.click('button[data-valore=pubblica]')]);
  await p.waitForLoadState('networkidle');
  await p.locator('#social').scrollIntoViewIfNeeded();
  await foto('19s-scheda', { sel: '#social', segni: [{ sel: '[name=social_testo]', n: 1 }, { sel: '#social details summary', n: 2 }, { sel: '.sc-social-img', n: 3 }] });
  // I testi pronti da copiare
  await vai('social.php?scheda=testi');
  await foto('19s-testi', { sel: '.so-carta', segni: [{ sel: '.so-carta .so-canale textarea', n: 1 }, { sel: '.so-carta .so-condividi', n: 2 }] });
  // L'email del mattino
  await vai('social.php?scheda=mattino');
  await foto('19s-mattino', { clip: { x: 240, y: 56, width: 960, height: 760 }, segni: [{ sel: 'input[name=giorno]', n: 1 }, { sel: 'form:has(input[value=manda_mattino]) button', n: 2 }] });
  // Il calendario
  await vai('social.php?scheda=calendario&nuovo=1');
  const domani = new Date(Date.now() + 86400000 - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  await p.fill('#so-data', domani);
  await p.selectOption('#so-cont', { label: 'Articolo: Chiusi per ferie dal 10 al 20 agosto' });
  await p.fill('#so-tit', 'Promemoria: ultimi giorni prima delle ferie');
  await p.fill('#so-li', 'Ultima settimana prima della pausa estiva: se ti serve qualcosa, passa entro sabato.');
  await p.fill('#so-fb', 'Ultimi giorni prima delle ferie! Passa entro sabato \u{2600}\u{FE0F}');
  await foto('19s-calendario-nuovo', { clip: { x: 240, y: 56, width: 960, height: 760 }, segni: [{ sel: '#so-data', n: 1 }, { sel: '#so-cont', n: 2 }, { sel: '#so-tit', n: 3 }, { sel: '#so-li', n: 4 }] });
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=programmato_salva]) button.btn-primary')]);
  await foto('19s-calendario', { sel: '.so-tabella', margine: 10 });
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

// 1.10.0: i widget per le attività, sul sito (foto del capitolo 8). La pagina /widget-nuovi/ la crea prove/pagina-widget-nuovi.php
// nella cartella del sito delle schermate (CMS_DIR_MANUALE; senza, la foto si salta). Si fa dopo tutti i capitoli: creata prima,
// prenderebbe il numero di pagina che il capitolo 17 si aspetta per "Lavora con noi".
async function fotoWidgetAttivita() {
  if (process.env.CMS_DIR_MANUALE && (!SOLO || '08'.startsWith(SOLO.slice(0, 2)))) {
    require('child_process').execFileSync('php', [path.join(__dirname, '..', 'prove', 'pagina-widget-nuovi.php'), process.env.CMS_DIR_MANUALE]);
    await p.goto(B + '/widget-nuovi/', { waitUntil: 'networkidle' }); await dorme(600);
    await p.addStyleTag({ content: 'header{visibility:hidden}' }); // la testata fissa coprirebbe la parte alta della foto
    const sez = p.locator('.ed-sezione:has(.ed-w-orari)').first();
    await sez.scrollIntoViewIfNeeded();
    await sez.screenshot({ path: path.join(GREZZE, '08-attivita.png') }); console.log('08-attivita');
  }
}

// ------------------------------------------------------------------------------------------------------------------------
// 2.0.0. Da qui in poi i capitoli girano dopo tutti gli altri (creano pagine: la trappola dei numeri di pagina) e vogliono
// il server di posta finto (POSTA_MANUALE=<porta>:<cartella>): le email di conferma si aprono come farebbe una persona.
const C2 = require(path.join(__dirname, '..', 'prove', 'addon-b-comune.js'));
const [POSTA_PORTA, POSTA_DIR] = (process.env.POSTA_MANUALE || ':').split(':');
const postaN = () => C2.posta(POSTA_DIR).length;
async function postaNuove(prima, quante = 1) {
  if (!POSTA_DIR) throw new Error('serve POSTA_MANUALE=<porta>:<cartella> (prove/smtp-finto.py)');
  return C2.nuove(POSTA_DIR, prima, quante, 15000);
}
const addonPag = (a, pag) => vai('addon-pagina.php?a=' + a + '&p=' + pag);
// Nelle email il sito ha il suo indirizzo vero (sito_url, https://www.studioprova.it): lo si apre dal server di prova.
const qui = (u) => u.replace(/^https?:\/\/[^/]+/, B);
const flash = async () => (await p.locator('.flash').first().innerText().catch(() => '')).trim();
// La posta del sito sul server finto (una volta per giro), perché le email di conferma arrivino davvero.
let postaPronta = false;
async function postaFinta() {
  if (postaPronta) return;
  if (!POSTA_DIR) throw new Error('serve POSTA_MANUALE=<porta>:<cartella> (prove/smtp-finto.py)');
  await vai('impostazioni.php');
  await p.fill('#smtp_host', '127.0.0.1'); await p.fill('#smtp_porta', POSTA_PORTA); await p.selectOption('#smtp_sicurezza', 'nessuna');
  await p.fill('[name=smtp_mittente]', 'negozio@studioprova.it').catch(() => {});
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=save_posta]) button[type=submit]')]);
  postaPronta = true;
}
// Un visitatore, in una finestra sua (senza l'accesso al pannello).
async function visitatore(telefono = false) {
  const c = await p.context().browser().newContext(telefono ? { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 1, locale: 'it-IT', timezoneId: 'Europe/Rome' }
    : { viewport: { width: 1200, height: 800 }, locale: 'it-IT', timezoneId: 'Europe/Rome', deviceScaleFactor: 1 });
  // Il sito risponde con il suo indirizzo vero (sito_url, https://www.studioprova.it) nei rimandi: per il visitatore quell'indirizzo
  // si serve dal server di prova, e il visitatore resta sempre lì (un solo dominio, gli stessi cookie).
  await c.route(/^https:\/\/www\.studioprova\.it\//, async (r) => {
    const ck = (await c.cookies('https://www.studioprova.it/')).map(x => x.name + '=' + x.value).join('; ');
    const h = { ...r.request().headers() }; delete h.cookie; if (ck) h.cookie = ck;
    if (h.origin) h.origin = B; if (h.referer && h.referer.startsWith('https://www.studioprova.it/')) h.referer = qui(h.referer); // il controllo dell'origine dei moduli guarda il server vero
    const risposta = await r.fetch({ url: qui(r.request().url()), headers: h, maxRedirects: 0 });
    const dove = risposta.headers().location; if (process.env.DBG && r.request().method() === 'POST') console.log('POST', r.request().url(), risposta.status(), dove, JSON.stringify(risposta.headersArray().filter(x => /cookie/i.test(x.name))), (r.request().postData() || '').slice(0, 200));
    // I cookie (il carrello, l'accesso dei membri) si mettono a mano: una risposta finta non li porta con sé.
    const cookie = risposta.headersArray().filter(x => x.name.toLowerCase() === 'set-cookie').map(x => x.value.split(';'));
    for (const [nv, ...attr] of cookie) {
      const [n, ...v] = nv.split('='); const scade = attr.find(a => /^\s*expires=/i.test(a));
      const ts = scade ? Date.parse(scade.split('=')[1]) / 1000 : -1;
      if (ts > 0 && ts < Date.now() / 1000) await c.clearCookies({ name: n.trim() });
      else await c.addCookies([{ name: n.trim(), value: v.join('='), url: 'https://www.studioprova.it/' }]);
    }
    // Chromium non intercetta il rimando di una risposta finta: il rimando diventa una pagina che va avanti da sola.
    if (risposta.status() >= 300 && risposta.status() < 400 && dove) {
      await r.fulfill({ status: 200, headers: { 'content-type': 'text/html; charset=utf-8' }, body: '<script>location.replace(' + JSON.stringify(new URL(dove, r.request().url()).href) + ')</script>' });
    } else await r.fulfill({ response: risposta });
  });
  const v = await c.newPage(); v.on('dialog', d => d.accept());
  return v;
}
const SITO = 'https://www.studioprova.it';
async function fotoDi(q, nome, opz = {}) {
  if (SOLO && !nome.startsWith(SOLO)) return;
  if (opz.segni) await segna(q, opz.segni);
  if (opz.sel) { const el = q.locator(opz.sel).first(); await el.scrollIntoViewIfNeeded(); await el.screenshot({ path: path.join(GREZZE, nome + '.png') }); }
  else await q.screenshot({ path: path.join(GREZZE, nome + '.png'), clip: opz.clip, fullPage: !!opz.clip && !opz.vista });
  if (opz.segni) await togliSegni(q);
  console.log(nome);
}
// Una pagina nuova dall'editor, con un titolo e un widget sotto il titolo; ritorna l'id della pagina.
async function paginaConWidget(titolo, slug, tipo, imposta, testo) {
  await vai('index.php');
  await Promise.all([p.waitForNavigation(), p.click('.db-azioni a:has-text("+ Pagina")')]);
  await apriEditor(p.url().split('/admin/')[1]);
  const id = new URL(p.url()).searchParams.get('id');
  await p.click('#ed-apri-impostazioni'); await dorme(300);
  await p.fill('#ed-form-impostazioni [name=titolo]', titolo);
  await p.fill('#ed-form-impostazioni [name=slug]', slug);
  await p.click('#ed-form-impostazioni button[type=submit]'); await dorme(1200);
  if (await p.locator('#ed-dialogo-impostazioni[open]').count()) throw new Error('la pagina ' + slug + ' non si salva: ' + (await p.locator('#ed-dialogo-impostazioni').innerText()).slice(0, 300));
  await tela().locator('.ed-w-titolo').first().dblclick(); await dorme(500);
  await scriviCtl('Titolo', titolo);
  if (testo) { // il testo di partenza della pagina, riscritto
    await tela().locator('.ed-w-testo').first().dblclick(); await dorme(500);
    await p.locator('#ed-controlli .ql-editor').click(); await p.keyboard.press('Control+A');
    await p.keyboard.type(testo); await dorme(500);
  }
  if (tipo) await widget(tipo);
  if (imposta) await imposta();
  return id;
}
async function pubblica() {
  await p.click('#ed-pubblica');
  await p.waitForFunction(() => /Pubblicata alle/.test(document.querySelector('#ed-stato').textContent));
}
// Compila un form del sito (tutti i campi visibili), con la privacy e "Sono una persona".
async function compilaForm(v, f, valori) {
  for (const c of await f.locator('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea').all()) {
    const nome = await c.getAttribute('name'); const tipo = await c.getAttribute('type');
    if (nome === 'sito_web' || !(await c.isVisible())) continue;
    if (valori[nome] !== undefined) await c.fill(valori[nome]);
    else await c.fill(tipo === 'email' ? valori.email : (tipo === 'tel' ? '+39 333 1234567' : (tipo === 'password' ? valori.password : valori.nome)));
  }
  const pr = f.locator('input[name=privacy]'); if (await pr.count()) await pr.check();
  if (await f.locator('.captcha input[type=checkbox]').count()) await C2.captcha(f, v);
}

// 31n. Le novità della 2.0.0 nei capitoli di prima: file riservati e form che li dà (capitoli 18 e 22), Scegli dai Media (23).
// La newsletter (capitolo 20) è cambiata solo a parole: niente foto, il PDF del manuale d'uso deve restare sotto 1900 KB. Esempio: Studio Prova regala la "Guida alla cura delle piante" a chi si iscrive.
capitoli['31n'] = async () => {
  await postaFinta();
  // Download: un PDF riservato.
  const pdf = path.join(require('os').tmpdir(), 'guida-piante.pdf');
  const t = await ctx.newPage(); await t.setContent('<h1>Guida alla cura delle piante</h1><p>Acqua, luce, rinvasi: tutto quello che serve.</p>'); await t.pdf({ path: pdf }); await t.close();
  await vai('scaricabili.php');
  await p.setInputFiles('#dl-carica input[type=file]', pdf);
  await p.fill('#dl-carica input[name=titolo]', 'Guida alla cura delle piante');
  await p.fill('#dl-carica [name=descrizione]', 'In regalo a chi si iscrive alla newsletter.');
  await p.check('#dl-riservato');
  await foto('31n-riservato', { sel: '#dl-carica', segni: [{ sel: '#dl-carica input[type=file]', n: 1 }, { sel: '#dl-carica input[name=titolo]', n: 2 }, { sel: '#dl-riservato', n: 3 }, { sel: '#dl-carica button[type=submit]', n: 4 }] });
  await Promise.all([p.waitForNavigation(), p.click('#dl-carica button[type=submit]')]);
  if (!/riservato/.test(await flash())) throw new Error('31n: il file non risulta riservato: ' + await flash());
  // Form > Dopo l'invio: lista, conferma e il file.
  await vai('form.php?nuovo=1');
  await p.fill('[name=nome]', 'Guida in regalo');
  await p.fill('[name=titolo]', 'Ricevi la guida alla cura delle piante');
  await p.fill('[name=intro]', 'Lasciaci nome ed email: dopo la conferma ti mandiamo la guida in PDF e, una volta al mese, la nostra newsletter.');
  await p.fill('[name=pulsante]', 'Mandami la guida');
  await p.selectOption('select[name=lista_id]', { label: 'Newsletter' });
  await p.check('input[name=lista_conferma]');
  await p.selectOption('#fm-omaggio', { label: 'Guida alla cura delle piante (guida-piante.pdf)' });
  await foto('31n-form-file', { sel: '.sc-scatola:has(#fm-omaggio)', altezza: 300, segni: [{ sel: 'select[name=lista_id]', n: 1 }, { sel: 'input[name=lista_conferma]', n: 2 }, { sel: '#fm-omaggio', n: 3 }] });
  await Promise.all([p.waitForNavigation(), p.click('button.btn-primary:has-text("Salva")')]);
  // La pagina "Guida in regalo" con il form.
  await paginaConWidget('Guida in regalo', 'guida-in-regalo', 'modulo', async () => {
    const q = await ctl('Quale modulo');
    await q.locator('select').selectOption(await q.locator('option', { hasText: 'Guida in regalo' }).getAttribute('value')); await dorme(1200);
  });
  await pubblica();
  // Una persona compila, conferma dall'email e trova il file.
  const v = await visitatore();
  await v.goto(SITO + '/guida-in-regalo/', { waitUntil: 'networkidle' });
  const f = v.locator('form[data-captcha]').first();
  await compilaForm(v, f, { nome: 'Lucia Bassi', email: 'lucia.bassi@esempio.it', messaggio: '' });
  let n0 = postaN();
  await f.locator('button[type=submit]').click(); await v.waitForURL(/\/grazie\//); await v.waitForLoadState('networkidle');
  const conf = (await postaNuove(n0)).find(e => /lucia\.bassi/.test(e.a));
  const lk = conf && C2.link(conf).find(l => /\?t=[a-f0-9]{64}/.test(l));
  if (!lk) throw new Error('31n: non arriva l\'email di conferma');
  n0 = postaN();
  await v.goto(lk, { waitUntil: 'networkidle' });
  // La pagina "Ecco il tuo file" non si fotografa: il PDF del manuale d'uso deve restare sotto 1900 KB.
  if (!(await v.locator('a.btn[href*="/scarica/"]').count())) throw new Error('31n: dopo la conferma manca il pulsante Scarica');
  await postaNuove(n0);
  await v.request.get(qui(await v.locator('a.btn:has-text("Scarica")').getAttribute('href')));
  await v.context().close();
  // Download: chi l'ha ricevuto.
  await vai('scaricabili.php');
  await p.locator('tr#dl-guida-piante details.dl-consegne summary').click(); await dorme(300);
  // Solo la colonna del file (il PDF del manuale d'uso deve restare sotto 1900 KB: foto strette).
  await p.evaluate(() => scrollTo(0, 0));
  const riga = await p.locator('tr#dl-guida-piante td').first().boundingBox();
  await foto('31n-chi', { clip: { x: riga.x - 12, y: riga.y - 12, width: riga.width + 24, height: riga.height + 24 }, segni: [{ sel: 'tr#dl-guida-piante .dl-riservato', n: 1 }, { sel: 'tr#dl-guida-piante details.dl-consegne summary', n: 2 }] });
  // SEO e AEO > Globale SEO: l'immagine social scelta dai Media (prima si carica in Media, 1200x630).
  const img = path.join(require('os').tmpdir(), 'condivisione-studio-prova.jpg');
  const g = await ctx.newPage(); await g.setViewportSize({ width: 1200, height: 630 });
  await g.setContent('<body style="margin:0;width:1200px;height:630px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1f6f5c,#9fd3a8);font:700 72px sans-serif;color:#fff;text-align:center">Studio Prova<br><span style="font-size:36px;font-weight:400">Vasi e piante a Bologna</span></body>');
  await g.screenshot({ path: img, type: 'jpeg', quality: 80 }); await g.close();
  await vai('media.php');
  await p.setInputFiles('input[type=file][name=file]', img);
  await Promise.all([p.waitForNavigation(), p.click('form[enctype] button[type=submit]')]);
  await vai('seo.php?scheda=globale');
  await p.click('[data-scegli-media="#seo-og-immagine"]');
  await p.waitForSelector('dialog.pn-media .pn-media-voce'); await dorme(800);
  await p.fill('dialog.pn-media input[type=search]', 'condivisione'); await dorme(500);
  // Il dialogo dei Media sta sopra a tutto (top layer): niente foto, la casella dice abbastanza.
  await p.locator('dialog.pn-media .pn-media-voce').first().click(); await dorme(300);
  await foto('31n-immagine-social', { sel: '.form-group:has(#seo-og-immagine)', margine: 22, segni: [{ sel: '[data-scegli-media="#seo-og-immagine"]', n: 1 }, { sel: '#seo-og-immagine', n: 2 }] });
  await Promise.all([p.waitForNavigation(), p.click('form:has(#seo-og-immagine) button.btn-primary')]);
};

// Accende un addon da Impostazioni > Addon, come una persona; ritorna il messaggio verde.
async function accendi(nome) {
  await postaFinta();
  await vai('addon.php');
  const b = p.locator(`[data-addon="${nome}"] button[value=accendi]`);
  if (await b.count()) await Promise.all([p.waitForNavigation(), b.click()]);
  if (!(await p.locator(`[data-addon="${nome}"].acceso`).count())) throw new Error('non si accende ' + nome);
  return flash();
}

// 32. Gli addon: accendere e spegnere.
capitoli['32'] = async () => {
  await vai('addon.php');
  await foto('32-addon', { clip: { x: 240, y: 56, width: 960, height: 700 }, segni: [{ sel: '[data-addon="prenotazioni"] button[value=accendi]', n: 1 }] });
  await Promise.all([p.waitForNavigation(), p.click('[data-addon="prenotazioni"] button[value=accendi]')]);
  if (!/è acceso/.test(await flash())) throw new Error('32: Prenotazioni non si accende');
  await p.setViewportSize({ width: 1200, height: 1000 });
  await foto('32-acceso', { clip: { x: 0, y: 0, width: 1200, height: 1000 }, segni: [{ sel: '.flash', n: 2 }, { sel: '[data-addon="prenotazioni"] .badge', n: 3, lato: 'destra' }, { sel: 'nav.admin-nav a[href*="a=prenotazioni"]', n: 4 }] });
  await p.setViewportSize({ width: 1200, height: 800 });
};

// 33. Prenotazioni e appuntamenti: il salone "Forbici d'Oro" a Bologna, Anna e Marco.
capitoli['33'] = async () => {
  await accendi('prenotazioni');
  await addonPag('prenotazioni', 'persone&nuova=1');
  await p.fill('#pa-persona [name=nome]', 'Anna');
  await p.fill('[name="ferie[0][dal]"]', '2026-12-28'); await p.fill('[name="ferie[0][al]"]', '2026-12-31'); await p.fill('[name="ferie[0][motivo]"]', 'Ferie');
  await foto('33-persona', { clip: { x: 240, y: 56, width: 960, height: 1080 }, segni: [{ sel: '#pa-persona [name=nome]', n: 1 }, { sel: '#pa-o-lun', n: 2 }, { sel: '[name="ferie[0][dal]"]', n: 3 }, { sel: '#pa-persona button.btn-primary', n: 4 }] });
  await Promise.all([p.waitForNavigation(), p.click('#pa-persona button.btn-primary')]);
  await addonPag('prenotazioni', 'persone&nuova=1');
  await p.fill('#pa-persona [name=nome]', 'Marco');
  for (const [g, v] of Object.entries({ lun: '10:00-18:00', mar: '10:00-18:00', mer: '10:00-18:00', gio: '10:00-18:00', ven: '10:00-18:00', sab: 'Chiuso', dom: 'Chiuso' })) await p.fill(`#pa-o-${g}`, v);
  await Promise.all([p.waitForNavigation(), p.click('#pa-persona button.btn-primary')]);
  await addonPag('prenotazioni', 'servizi&nuovo=1');
  await p.fill('#pa-servizio [name=nome]', 'Taglio donna'); await p.fill('#pa-servizio [name=durata]', '45'); await p.fill('#pa-servizio [name=pausa]', '15'); await p.fill('#pa-servizio [name=prezzo]', '35 €');
  await foto('33-servizio', { sel: '#pa-servizio', segni: [{ sel: '#pa-servizio [name=nome]', n: 1 }, { sel: '#pa-servizio [name=durata]', n: 2 }, { sel: '#pa-servizio [name=pausa]', n: 3 }, { sel: '#pa-servizio [name=prezzo]', n: 4 }, { sel: '#pa-servizio label.pa-spunta', n: 5 }, { sel: '#pa-servizio button.btn-primary', n: 6 }] });
  await Promise.all([p.waitForNavigation(), p.click('#pa-servizio button.btn-primary')]);
  await addonPag('prenotazioni', 'servizi&nuovo=1');
  await p.fill('#pa-servizio [name=nome]', 'Colore'); await p.fill('#pa-servizio [name=durata]', '90'); await p.fill('#pa-servizio [name=prezzo]', '60 €');
  await p.locator('#pa-servizio label.pa-spunta', { hasText: 'Marco' }).locator('input').uncheck();
  await Promise.all([p.waitForNavigation(), p.click('#pa-servizio button.btn-primary')]);
  await addonPag('prenotazioni', 'servizi');
  await foto('33-servizi', { clip: { x: 240, y: 56, width: 960, height: 380 } });
  await addonPag('prenotazioni', 'impostazioni');
  await p.fill('#pa-impostazioni [name=avviso_a]', 'salone@forbicidoro.it');
  await p.fill('#pa-impostazioni [name=indirizzo]', 'Via dell\'Indipendenza 10, Bologna');
  await foto('33-impostazioni', { clip: { x: 240, y: 56, width: 960, height: 1000 }, segni: [{ sel: '#pa-impostazioni [name=anticipo_ore]', n: 1 }, { sel: '#pa-impostazioni [name=limite_ore]', n: 2 }, { sel: '#pa-impostazioni [name=da_confermare]', n: 3 }, { sel: '#pa-impostazioni [name=avviso_a]', n: 4 }, { sel: '#pa-impostazioni [name=indirizzo]', n: 5 }] });
  await Promise.all([p.waitForNavigation(), p.click('#pa-impostazioni button.btn-primary')]);
  // La pagina "Prenota" con il widget.
  await paginaConWidget('Prenota', 'prenota', 'prenota');
  await p.click('.ed-schede-pannello button[data-pannello="modifica"]'); await dorme(800);
  await foto('33-widget', { clip: { x: 0, y: 0, width: 1200, height: 760 }, segni: [{ sel: '#ed-controlli', n: 6 }, { sel: '#ed-pubblica', n: 7 }] });
  await pubblica();
  // Una cliente prenota dal sito.
  const v = await visitatore();
  await v.goto(SITO + '/prenota/', { waitUntil: 'networkidle' });
  const box = v.locator('[data-pren]').first();
  await box.locator('select[name=servizio]').selectOption(await box.locator('select[name=servizio] option', { hasText: 'Taglio donna' }).getAttribute('value'));
  await v.waitForSelector('.pren-giorno[aria-pressed=true]');
  await box.locator('select[name=risorsa]').selectOption(await box.locator('select[name=risorsa] option', { hasText: 'Anna' }).getAttribute('value'));
  await v.waitForSelector('.pren-ora'); await dorme(500);
  const fraTre = new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10);
  const d = await box.locator('.pren-giorno:not([disabled])').evaluateAll((bs, m) => (bs.find(b => b.dataset.d >= m) || {}).dataset?.d, fraTre);
  await box.locator(`.pren-giorno[data-d="${d}"]`).click();
  await v.waitForFunction(x => document.querySelector('[name=giorno]').value === x && document.querySelector('.pren-ora'), d); await dorme(400);
  await box.locator('.pren-ora', { hasText: /^10:00$/ }).first().click().catch(() => box.locator('.pren-ora').nth(2).click());
  const f = box.locator('form');
  await f.locator('[name=nome]').fill('Giulia Rossi'); await f.locator('[name=email]').fill('giulia.rossi@esempio.it'); await f.locator('[name=telefono]').fill('+39 333 1234567');
  await f.locator('[name=privacy]').check();
  await C2.captcha(f, v);
  await v.addStyleTag({ content: '#testata{visibility:hidden}' });
  await fotoDi(v, '33-sul-sito', { sel: '[data-pren]', segni: [{ sel: '[data-pren] select[name=servizio]', n: 1 }, { sel: '[data-pren] select[name=risorsa]', n: 2 }, { sel: '[data-pren] .pren-giorno[aria-pressed=true]', n: 3 }, { sel: '[data-pren] .pren-ora[aria-pressed=true], [data-pren] .pren-ora.scelta, [data-pren] .pren-riepilogo', n: 4 }, { sel: '[data-pren] form button[type=submit]', n: 5 }] });
  const n0 = postaN();
  await f.locator('button[type=submit]').click();
  await v.waitForURL(/\/prenotazione\/gestisci\//, { timeout: 20000 }).catch(async (e) => { await v.screenshot({ path: '/tmp/dbg-33.png', fullPage: true }); console.log(v.url(), (await v.locator('body').innerText()).slice(0, 1500)); throw e; });
  await v.waitForLoadState('networkidle');
  await fotoDi(v, '33-fatta', { sel: 'main' });
  const em = await postaNuove(n0, 2);
  if (!em.some(e => /Prenotazione confermata/.test(e.oggetto))) throw new Error('33: non arriva la conferma al cliente');
  await v.context().close();
  // Nel pannello: agenda e scheda.
  await addonPag('prenotazioni', 'agenda');
  if (!(await p.locator('.pa-voce', { hasText: 'Giulia Rossi' }).count())) { await Promise.all([p.waitForNavigation(), p.locator('.pa-barra a', { hasText: 'Dopo' }).click()]); }
  await foto('33-agenda', { clip: { x: 240, y: 56, width: 960, height: 560 }, segni: [{ sel: '.pa-voce', n: 1 }, { sel: '.pa-barra', n: 2 }] });
  await Promise.all([p.waitForNavigation(), p.locator('.pa-voce', { hasText: 'Giulia Rossi' }).first().click()]);
  await foto('33-scheda', { clip: { x: 240, y: 56, width: 960, height: 900 }, segni: [{ sel: '#pa-sposta', n: 3 }, { sel: '#pa-annulla textarea', n: 4 }] });
};

const dataLocale = d => { const z = n => String(n).padStart(2, '0'); return `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}T${z(d.getHours())}:${z(d.getMinutes())}`; };

// 34. Eventi con iscrizione: l'associazione "Colli Bolognesi" e la serata di degustazione.
capitoli['34'] = async () => {
  await accendi('eventi-iscrizione');
  await addonPag('eventi-iscrizione', 'impostazioni');
  await p.fill('#evi-impostazioni [name=avviso_a]', 'info@collibolognesi.it');
  await Promise.all([p.waitForNavigation(), p.click('#evi-impostazioni button.btn-primary')]);
  const inizio = new Date(Date.now() + 7 * 86400000); inizio.setHours(21, 0, 0, 0);
  const chiude = new Date(inizio.getTime() - 86400000); chiude.setHours(20, 0, 0, 0);
  await addonPag('eventi-iscrizione', 'eventi&nuovo=1');
  await p.fill('#evi-evento [name=titolo]', 'Serata di degustazione');
  await p.fill('#evi-evento [name=inizio]', dataLocale(inizio));
  await p.fill('#evi-evento [name=luogo]', 'Sede dell\'associazione'); await p.fill('#evi-evento [name=indirizzo]', 'Via Rizzoli 1, Bologna');
  await p.fill('#evi-evento [name=prezzo]', '15 € a persona');
  await p.fill('#evi-evento [name=descrizione]', 'Assaggiamo insieme cinque vini dei colli bolognesi, raccontati da chi li fa.\n\nIn abbinamento, pane e salumi del territorio.');
  await p.fill('#evi-evento [name=posti]', '30'); await p.fill('#evi-evento [name=max_persona]', '2');
  await p.fill('#evi-evento [name=chiusura]', dataLocale(chiude));
  await p.selectOption('#evi-evento [name=stato]', 'pubblicato');
  await foto('34-evento', { sel: '#evi-evento', segni: [{ sel: '#evi-evento [name=titolo]', n: 1 }, { sel: '#evi-evento [name=inizio]', n: 2 }, { sel: '#evi-evento [name=luogo]', n: 3 }, { sel: '#evi-evento [name=posti]', n: 4 }, { sel: '#evi-evento [name=max_persona]', n: 5 }, { sel: '#evi-evento [name=chiusura]', n: 6 }, { sel: '#evi-evento [name=stato]', n: 7 }, { sel: '#evi-evento button.btn-primary', n: 8 }] });
  await Promise.all([p.waitForNavigation(), p.click('#evi-evento button.btn-primary')]);
  if (!/\/evento\/serata-di-degustazione\//.test(await flash())) throw new Error('34: evento non salvato: ' + await flash());
  const eid = new URL(p.url()).searchParams.get('id');
  await foto('34-salvato', { clip: { x: 240, y: 56, width: 960, height: 200 }, segni: [{ sel: '.flash', n: 9 }] });
  // La pagina "Eventi" con il widget dell'elenco.
  await paginaConWidget('Eventi', 'eventi', 'eventi_iscrizione');
  await pubblica();
  // Una socia si iscrive con due posti.
  const v = await visitatore();
  await v.goto(SITO + '/eventi/', { waitUntil: 'networkidle' });
  await v.addStyleTag({ content: '#testata{visibility:hidden}' });
  await fotoDi(v, '34-elenco', { sel: '.ew-eventi' });
  await v.goto(SITO + '/evento/serata-di-degustazione/', { waitUntil: 'networkidle' });
  const f = v.locator('.evi form[data-captcha]').first();
  await f.locator('[name=nome]').fill('Marta Lodi'); await f.locator('[name=email]').fill('marta.lodi@esempio.it');
  await f.locator('[name=posti]').selectOption('2');
  await f.locator('[name=privacy]').check(); await C2.captcha(f, v);
  await v.addStyleTag({ content: '#testata{visibility:hidden}' });
  await fotoDi(v, '34-pagina', { sel: 'main', segni: [{ sel: '.evi-scheda', n: 1 }, { sel: '.evi form [name=posti]', n: 2 }, { sel: '.evi form button[type=submit]', n: 3 }] });
  const n0 = postaN();
  await f.locator('button[type=submit]').click(); await v.waitForLoadState('networkidle'); await dorme(800);
  await postaNuove(n0, 1);
  await fotoDi(v, '34-iscritta', { clip: { x: 0, y: 0, width: 1200, height: 700 } });
  await v.context().close();
  // Nel pannello: gli iscritti.
  await addonPag('eventi-iscrizione', 'iscritti&e=' + eid);
  await foto('34-iscritti', { clip: { x: 240, y: 56, width: 960, height: 900 }, segni: [{ sel: 'button[value=presente]', n: 1 }, { sel: 'a[href*="csv=1"]', n: 2 }, { sel: '#evi-scrivi', n: 3 }] });
};

// 35. Raccolta recensioni: il centro estetico "Rosa" a Bologna chiede com'è andata a due clienti affezionate.
capitoli['35'] = async () => {
  await accendi('recensioni');
  await addonPag('recensioni', 'impostazioni');
  await p.fill('#rec-impostazioni [name=google_link]', 'https://g.page/r/centro-estetico-rosa/review');
  await p.selectOption('#rec-impostazioni [name=google_da]', '4');
  await p.fill('#rec-impostazioni [name=avviso_a]', 'info@centrorosa.it');
  await foto('35-impostazioni', { sel: '#rec-impostazioni', segni: [{ sel: '#rec-impostazioni [name=giorni_dopo]', n: 1 }, { sel: '#rec-impostazioni [name=google_link]', n: 2 }, { sel: '#rec-impostazioni [name=google_da]', n: 3 }, { sel: '#rec-impostazioni button.btn-primary', n: 4 }] });
  await Promise.all([p.waitForNavigation(), p.click('#rec-impostazioni button.btn-primary')]);
  await addonPag('recensioni', 'inviti');
  await p.fill('#rec-aggiungi [name=elenco]', 'Giulia Rossi <giulia.rossi@esempio.it>\nPaola Neri <paola.neri@esempio.it>');
  await p.fill('#rec-aggiungi [name=servizio]', 'Trattamento viso');
  await p.selectOption('#rec-aggiungi [name=quando]', 'subito');
  await p.check('#rec-aggiungi [name=consenso]');
  await foto('35-inviti', { sel: '#rec-aggiungi', segni: [{ sel: '#rec-aggiungi [name=elenco]', n: 1 }, { sel: '#rec-aggiungi [name=servizio]', n: 2 }, { sel: '#rec-aggiungi [name=quando]', n: 3 }, { sel: '#rec-aggiungi [name=consenso]', n: 4 }, { sel: '#rec-aggiungi button', n: 5 }] });
  await Promise.all([p.waitForNavigation(), p.click('#rec-aggiungi button')]);
  const n0 = postaN();
  await Promise.all([p.waitForNavigation(), p.click('#rec-manda')]);
  await foto('35-coda', { clip: { x: 240, y: 56, width: 960, height: 1000 }, segni: [{ sel: '.flash', n: 6 }, { sel: '#rec-manda', n: 7 }] });
  const em = await postaNuove(n0, 2);
  // Le due clienti cliccano le stelle nell'email e scrivono due righe.
  for (const [chi, stelle, testo] of [['giulia.rossi', 5, 'Mani d\'oro e un\'accoglienza che mette subito a proprio agio. Tornerò di sicuro.'], ['paola.neri', 4, 'Trattamento rilassante e pelle luminosa per giorni. Un po\' di attesa all\'arrivo.']]) {
    const e = em.find(x => x.a.startsWith(chi));
    const lk = e && C2.link(e).find(l => new RegExp('s=' + stelle + '$').test(l));
    if (!lk) throw new Error('35: manca l\'email per ' + chi);
    const v = await visitatore();
    await v.goto(lk, { waitUntil: 'networkidle' });
    await v.fill('[name=testo]', testo); await v.check('[name=consenso]');
    if (stelle === 5) { await v.addStyleTag({ content: '#testata{visibility:hidden}' }); await fotoDi(v, '35-modulo', { sel: 'main', segni: [{ sel: '#rec-s5', n: 1 }, { sel: '[name=testo]', n: 2 }, { sel: '[name=nome]', n: 3 }, { sel: '.rec-modulo button[type=submit]', n: 4 }] }); }
    await v.click('.rec-modulo button[type=submit]'); await v.waitForLoadState('networkidle'); await dorme(600);
    if (stelle === 5) { await v.addStyleTag({ content: '#testata{visibility:hidden}' }); await fotoDi(v, '35-grazie', { sel: 'main' }); }
    await v.context().close();
  }
  // Nel pannello: approvare e rispondere.
  await addonPag('recensioni', 'recensioni');
  await foto('35-approva', { clip: { x: 240, y: 56, width: 960, height: 700 }, segni: [{ sel: 'button[value=approva]', n: 1 }, { sel: 'button[value=rifiuta]', n: 2 }] });
  for (let i = 0; i < 2; i++) await Promise.all([p.waitForNavigation(), p.locator('button[value=approva]').first().click()]);
  await addonPag('recensioni', 'recensioni&filtro=approvata');
  const art = p.locator('article', { hasText: 'Giulia R.' });
  await art.locator('textarea[name=risposta]').fill('Grazie Giulia, è stato un piacere. Ti aspettiamo!');
  await Promise.all([p.waitForNavigation(), art.locator('button[value=rispondi]').click()]);
  // La pagina "Dicono di noi" con il widget.
  await paginaConWidget('Dicono di noi', 'dicono-di-noi', 'recensioni_sito');
  await pubblica();
  const v = await visitatore();
  await v.goto(SITO + '/dicono-di-noi/', { waitUntil: 'networkidle' });
  await v.addStyleTag({ content: '#testata{visibility:hidden}' });
  await fotoDi(v, '35-sul-sito', { sel: 'main .ed-sezione:has(.ed-w-recensioni_sito), main .ed-w-recensioni_sito' });
  await v.context().close();
};

// Una foto finta di un prodotto (disegnata nel browser): una tazza o un grembiule, del colore scelto.
async function fotoProdotto(nome, forma, colore) {
  const file = path.join(require('os').tmpdir(), nome + '.jpg');
  const t = await ctx.newPage(); await t.setViewportSize({ width: 800, height: 800 });
  const disegno = forma === 'tazza'
    ? `<div style="position:relative;width:300px;height:280px"><div style="position:absolute;left:0;top:0;width:230px;height:280px;background:${colore};border-radius:18px 18px 60px 60px;box-shadow:inset -30px 0 0 rgba(0,0,0,.12)"></div><div style="position:absolute;left:200px;top:60px;width:110px;height:130px;border:28px solid ${colore};border-radius:50%;box-sizing:border-box"></div></div>`
    : `<div style="width:320px;height:420px;background:${colore};clip-path:polygon(30% 0,70% 0,70% 20%,100% 32%,92% 100%,8% 100%,0 32%,30% 20%);box-shadow:inset 0 -40px 0 rgba(0,0,0,.12)"></div>`;
  await t.setContent(`<body style="margin:0;width:800px;height:800px;display:flex;align-items:center;justify-content:center;background:radial-gradient(circle at 50% 40%,#f7f1e8,#e3d6c3)">${disegno}</body>`);
  await t.screenshot({ path: file, type: 'jpeg', quality: 82 }); await t.close();
  return file;
}

// 36. Vetrina prodotti: la "Bottega Rossi", ceramiche fatte a mano a Bologna.
capitoli['36'] = async () => {
  await accendi('vetrina');
  await addonPag('vetrina', 'impostazioni');
  await p.fill('#vt-nome_negozio', 'Bottega');
  await p.fill('#vt-intro', 'Ceramiche fatte a mano a Bologna.');
  await p.fill('#vt-spedizione_costo', '7,00'); await p.fill('#vt-gratis_oltre', '60,00');
  await p.fill('#vt-iban', 'IT60 X054 2811 1010 0000 0123 456'); await p.fill('#vt-intestatario', 'Bottega Rossi di Anna Rossi');
  await p.check('input[name=link]'); await p.fill('#vt-link_url', 'https://paypal.me/bottegarossi');
  await p.fill('#vt-attivita_nome', 'Bottega Rossi'); await p.fill('#vt-attivita_indirizzo', 'Via Rizzoli 1, 40125 Bologna'); await p.fill('#vt-attivita_piva', '01234567890');
  await p.fill('#vt-email_avvisi', 'ordini@bottegarossi.it');
  await foto('36-impostazioni', { clip: { x: 240, y: 56, width: 960, height: 1250 }, segni: [{ sel: '#vt-nome_negozio', n: 1 }, { sel: '#vt-spedizione_costo', n: 2 }, { sel: '#vt-iban', n: 3 }, { sel: '#vt-link_url', n: 4 }] });
  await Promise.all([p.waitForNavigation(), p.click('#vt-salva')]);
  if (!/salvate/.test(await flash())) throw new Error('36: impostazioni non salvate: ' + await flash());
  for (const c of ['Tazze', 'Grembiuli']) { await addonPag('vetrina', 'categorie'); await p.fill('#vt-cat-nome', c); await Promise.all([p.waitForNavigation(), p.click('#vt-cat-nuova button[type=submit]')]); }
  // La tazza: scontata, due foto, 3 pezzi, in evidenza.
  await addonPag('vetrina', 'prodotto');
  await p.fill('#vt-nome', 'Tazza smaltata'); await p.fill('#vt-breve', 'Fatta a mano, 300 ml');
  await p.fill('#vt-descrizione', 'Ceramica smaltata a mano nel nostro laboratorio di via Rizzoli.\n\nSi lava in lavastoviglie.');
  await p.fill('#vt-prezzo', '18,00'); await p.fill('#vt-scontato', '15,00'); await p.fill('#vt-scorta', '3');
  await p.setInputFiles('#vt-foto-nuove', [await fotoProdotto('tazza-blu', 'tazza', '#2f6f9f'), await fotoProdotto('tazza-verde', 'tazza', '#4f8a5b')]);
  await p.check('#vt-prodotto input[name=evidenza]');
  await p.check('label.vt-a-spunta:has-text("Tazze") input');
  await foto('36-prodotto', { sel: '#vt-prodotto', segni: [{ sel: '#vt-nome', n: 1 }, { sel: '#vt-prezzo', n: 2 }, { sel: '#vt-scontato', n: 3 }, { sel: '#vt-foto-nuove', n: 4 }, { sel: '#vt-scorta', n: 5 }, { sel: '#vt-prodotto input[name=evidenza]', n: 6 }, { sel: '#vt-salva', n: 7 }] });
  await Promise.all([p.waitForNavigation(), p.click('#vt-salva')]);
  // Il grembiule con le taglie.
  await addonPag('vetrina', 'prodotto');
  await p.fill('#vt-nome', 'Grembiule da bottega'); await p.fill('#vt-breve', 'Cotone pesante, tasca davanti'); await p.fill('#vt-prezzo', '25,00');
  await p.setInputFiles('#vt-foto-nuove', [await fotoProdotto('grembiule', 'grembiule', '#b5532f')]);
  await p.check('#vt-prodotto input[name=evidenza]');
  await p.check('label.vt-a-spunta:has-text("Grembiuli") input');
  await p.fill('#vt-opz1', 'Taglia');
  for (const [i, v] of [['S', '', '1'], ['M', '', '0'], ['L', '30,00', '']].entries()) { await p.fill(`input[name="var[${i}][valore1]"]`, v[0]); await p.fill(`input[name="var[${i}][prezzo]"]`, v[1]); await p.fill(`input[name="var[${i}][scorta]"]`, v[2]); }
  await foto('36-varianti', { sel: '#vt-varianti', segni: [{ sel: '#vt-opz1', n: 1 }, { sel: 'input[name="var[2][prezzo]"]', n: 2 }, { sel: 'input[name="var[1][scorta]"]', n: 3 }] });
  await Promise.all([p.waitForNavigation(), p.click('#vt-salva')]);
  await addonPag('vetrina', 'prodotti');
  await foto('36-prodotti', { clip: { x: 240, y: 56, width: 960, height: 420 } });
  // Un cliente compra dal negozio.
  const v = await visitatore();
  await v.goto(SITO + '/negozio/', { waitUntil: 'networkidle' });
  await fotoDi(v, '36-negozio', { clip: { x: 0, y: 0, width: 1200, height: 900 } });
  await v.goto(SITO + '/negozio/tazza-smaltata/', { waitUntil: 'networkidle' });
  await v.fill('input[name=qta]', '2');
  await fotoDi(v, '36-scheda', { clip: { x: 0, y: 0, width: 1200, height: 800 }, segni: [{ sel: 'input[name=qta]', n: 1 }, { sel: '.vt-compra button', n: 2 }] });
  await v.click('.vt-compra button'); await v.waitForLoadState('networkidle');
  await v.goto(SITO + '/negozio/grembiule-da-bottega/', { waitUntil: 'networkidle' });
  await v.selectOption('select[name=variante]', { label: 'L · € 30,00' });
  await v.click('.vt-compra button'); await v.waitForLoadState('networkidle'); await dorme(500);
  await v.goto(SITO + '/negozio/carrello/', { waitUntil: 'networkidle' }); if (process.env.DBG) console.log(await v.context().cookies(), (await v.locator('main').innerText()).slice(0, 600));
  await v.addStyleTag({ content: '#testata{visibility:hidden}' });
  await fotoDi(v, '36-carrello', { sel: 'main', segni: [{ sel: '.vt-riga input[type=number]', n: 3 }, { sel: '.vt-avanti a', n: 4 }] });
  await v.goto(SITO + '/negozio/ordine/', { waitUntil: 'networkidle' });
  for (const [k, val] of Object.entries({ nome: 'Marta Bianchi', email: 'marta.bianchi@esempio.it', telefono: '+39 333 1234567', indirizzo: 'Via Indipendenza 10', cap: '40121', citta: 'Bologna', note: 'Citofono Bianchi, grazie.' })) await v.fill(`.vt-ordine [name=${k}]`, val);
  await v.click('label.vt-scelta:has-text("Bonifico") input');
  await v.check('.vt-ordine input[name=consenso]');
  await v.locator('.vt-ordine .captcha input[type=checkbox]').check(); await v.waitForSelector('form.verificato', { timeout: 30000 });
  await v.addStyleTag({ content: '#testata{visibility:hidden}' });
  await fotoDi(v, '36-ordine', { sel: 'main', segni: [{ sel: '.vt-ordine [name=nome]', n: 5 }, { sel: 'label.vt-scelta:has(input[name=metodo]:checked)', n: 6 }, { sel: '.vt-ordine input[name=consenso]', n: 7 }, { sel: '.vt-ordine button[type=submit]', n: 8 }] });
  const n0 = postaN();
  await v.click('.vt-ordine button[type=submit]'); await v.waitForURL(/ordine\/fatto/); await v.waitForLoadState('networkidle');
  await v.addStyleTag({ content: '#testata{visibility:hidden}' });
  await fotoDi(v, '36-grazie', { sel: 'main' });
  await postaNuove(n0, 2);
  await v.context().close();
  // Nel pannello: l'ordine e il suo stato.
  await addonPag('vetrina', 'ordini');
  await foto('36-ordini', { clip: { x: 240, y: 56, width: 960, height: 400 } });
  await Promise.all([p.waitForNavigation(), p.click('#vt-ordini tbody tr a')]);
  await p.selectOption('#vt-stato-scelta', 'pagato');
  await p.fill('#vt-messaggio', 'Grazie, il bonifico è arrivato: prepariamo il pacco.');
  await foto('36-stato', { clip: { x: 240, y: 56, width: 960, height: 900 }, segni: [{ sel: '#vt-stato-scelta', n: 1 }, { sel: '#vt-stato input[name=avvisa]', n: 2 }, { sel: '#vt-messaggio', n: 3 }, { sel: '#vt-stato button[type=submit]', n: 4 }] });
  await Promise.all([p.waitForNavigation(), p.click('#vt-stato button[type=submit]')]);
};

// 37. Statistiche delle visite: la "Bottega Rossi" guarda da dove arrivano le persone dopo la newsletter d'autunno.
capitoli['37'] = async () => {
  await accendi('statistiche');
  // Qualche visita vera, da browser diversi (un browser senza finestra conterebbe come robot: si dice che browser è).
  const UA = {
    pc: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    mac: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
    iphone: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
    android: 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
    ipad: 'Mozilla/5.0 (iPad; CPU OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
  };
  const giri = [
    [UA.pc, [['/', 'https://www.google.com/search?q=ceramiche+bologna'], ['/servizi/'], ['/contatti/']]],
    [UA.iphone, [['/servizi/', 'https://m.facebook.com/'], ['/chi-siamo/']]],
    [UA.mac, [['/?utm_source=newsletter&utm_medium=email&utm_campaign=autunno'], ['/chi-siamo/'], ['/servizi/']]],
    [UA.android, [['/?utm_source=newsletter&utm_medium=email&utm_campaign=autunno'], ['/lavora-con-noi/']]],
    [UA.ipad, [['/'], ['/blog/']]],
  ];
  for (const [ua, pagine] of giri) {
    const c = await p.context().browser().newContext({ userAgent: ua, locale: 'it-IT', extraHTTPHeaders: { 'Accept-Language': 'it-IT,it;q=0.9' } });
    const v = await c.newPage();
    for (const [u, da] of pagine) await v.goto(B + u, { referer: da, waitUntil: 'networkidle' });
    await c.close();
  }
  await vai('addon-pagina.php?a=statistiche&p=visite');
  await p.locator('a, button', { hasText: 'Ultimi 7 giorni' }).first().click(); await p.waitForLoadState('networkidle'); await dorme(500);
  await foto('37-visite', { clip: { x: 240, y: 56, width: 960, height: 1250 }, segni: [{ sel: '#st-csv', n: 1, lato: 'destra' }] });
  await addonPag('statistiche', 'impostazioni');
  await foto('37-impostazioni', { clip: { x: 240, y: 56, width: 960, height: 760 }, segni: [{ sel: '#st-impostazioni select', n: 1 }, { sel: '#st-impostazioni input[name=dnt]', n: 2 }] });
};

// 38. Area riservata (membri): lo studio di yoga "Yoga Ponte" a Bologna riserva ai soci le sequenze da fare a casa e il calendario.
capitoli['38'] = async () => {
  await accendi('membri');
  await addonPag('membri', 'impostazioni');
  await p.fill('#mi-avviso', 'Questa pagina è per i soci di Yoga Ponte: entra con la tua email, oppure iscriviti, è gratis.');
  await foto('38-impostazioni', { sel: '#mi-form', segni: [{ sel: '#mi-form [name=modo][value=aperta]', n: 1 }, { sel: '#mi-avviso', n: 2 }, { sel: '#mi-form button[type=submit]', n: 3 }] });
  await Promise.all([p.waitForNavigation(), p.click('#mi-form button[type=submit]')]);
  // Un file riservato in Download, e la pagina "Sequenze da fare a casa".
  const pdf = path.join(require('os').tmpdir(), 'calendario-lezioni-2027.pdf');
  const t = await ctx.newPage(); await t.setContent('<h1>Calendario delle lezioni 2027</h1>'); await t.pdf({ path: pdf }); await t.close();
  await vai('scaricabili.php');
  await p.setInputFiles('#dl-carica input[type=file]', pdf);
  await p.fill('#dl-carica input[name=titolo]', 'Calendario delle lezioni 2027');
  await p.check('#dl-riservato');
  await Promise.all([p.waitForNavigation(), p.click('#dl-carica button[type=submit]')]);
  await paginaConWidget('Sequenze da fare a casa', 'sequenze-a-casa', null, null, 'Tre sequenze di quindici minuti per i giorni in cui non riesci a venire in sala: una per il mattino, una per la schiena, una per la sera.');
  await pubblica();
  await paginaConWidget('Area soci', 'area-soci', 'membri_accesso');
  await p.click('.ed-schede-pannello button[data-pannello="modifica"]'); await dorme(800);
  await foto('38-widget', { clip: { x: 0, y: 0, width: 1200, height: 640 }, segni: [{ sel: '#ed-controlli', n: 7 }] });
  await pubblica();
  await addonPag('membri', 'riservati');
  await p.locator('label.mr-voce', { hasText: '/sequenze-a-casa/' }).locator('input').check();
  await foto('38-riservati', { clip: { x: 240, y: 56, width: 960, height: 760 }, segni: [{ sel: 'label.mr-voce:has(input:checked)', n: 1 }, { sel: '#mr-contenuti button[type=submit]', n: 2 }] });
  await Promise.all([p.waitForNavigation(), p.click('#mr-contenuti button[type=submit]')]);
  await p.locator('#mr-file label.mr-voce', { hasText: 'calendario-lezioni-2027.pdf' }).locator('input').check();
  await foto('38-file', { sel: '#mr-file', segni: [{ sel: '#mr-file label.mr-voce', n: 3 }, { sel: '#mr-file button[type=submit]', n: 4 }] });
  await Promise.all([p.waitForNavigation(), p.click('#mr-file button[type=submit]')]);
  // Una persona trova la pagina chiusa, si iscrive e conferma.
  const v = await visitatore();
  await v.goto(SITO + '/sequenze-a-casa/', { waitUntil: 'networkidle' });
  await v.addStyleTag({ content: '#testata{visibility:hidden}' });
  await fotoDi(v, '38-chiusa', { clip: { x: 0, y: 0, width: 1200, height: 560 } });
  await v.goto(SITO + '/iscriviti/', { waitUntil: 'networkidle' });
  const PW = 'Respiro-lungo-2026';
  await v.fill('#membri-iscriviti [name=nome]', 'Chiara Ferri'); await v.fill('#membri-iscriviti [name=email]', 'chiara.ferri@esempio.it');
  await v.fill('#membri-iscriviti [name=password]', PW); await v.fill('#membri-iscriviti [name=password2]', PW);
  await v.check('#membri-iscriviti [name=privacy]');
  await v.check('#membri-iscriviti .captcha input');
  await v.waitForFunction(() => /Verificato/.test(document.querySelector('.captcha-stato').textContent), null, { timeout: 30000 });
  await v.addStyleTag({ content: '#testata{visibility:hidden}' });
  await fotoDi(v, '38-iscriviti', { sel: 'main', segni: [{ sel: '#membri-iscriviti [name=email]', n: 1 }, { sel: '#membri-iscriviti [name=password]', n: 2 }, { sel: '#membri-iscriviti button[type=submit]', n: 3 }] });
  let n0 = postaN();
  await v.click('#membri-iscriviti button[type=submit]'); await v.waitForLoadState('networkidle'); await dorme(500);
  const m = (await postaNuove(n0)).find(e => /chiara\.ferri/.test(e.a));
  const lk = m && C2.link(m).find(l => /\/conferma-iscrizione\/\?t=/.test(l));
  if (!lk) throw new Error('38: non arriva la conferma dell\'iscrizione');
  await v.goto(lk, { waitUntil: 'networkidle' });
  await v.click('main form button[type=submit]'); await v.waitForURL(/area-riservata/); await v.waitForLoadState('networkidle');
  await v.addStyleTag({ content: '#testata{visibility:hidden}' });
  await fotoDi(v, '38-area', { sel: 'main' });
  await v.context().close();
  await addonPag('membri', 'elenco');
  await foto('38-elenco', { clip: { x: 240, y: 56, width: 960, height: 560 } });
};

// 39. Corsi online: Luca Neri, fotografo e formatore, mette online "Fotografia per cominciare".
capitoli['39'] = async () => {
  const msg = await accendi('corsi');
  await foto('39-acceso', { clip: { x: 240, y: 56, width: 960, height: 130 }, segni: [{ sel: '.flash', n: '' }] });
  // Il materiale: un PDF riservato in Download.
  const pdf = path.join(require('os').tmpdir(), 'scheda-inquadratura.pdf');
  const t = await ctx.newPage(); await t.setContent('<h1>La regola dei terzi</h1><p>Una scheda da tenere nel telefono.</p>'); await t.pdf({ path: pdf }); await t.close();
  await vai('scaricabili.php');
  await p.setInputFiles('#dl-carica input[type=file]', pdf); await p.fill('#dl-carica [name=titolo]', 'Scheda dell\'inquadratura'); await p.check('#dl-riservato');
  await Promise.all([p.waitForNavigation(), p.click('#dl-carica button[type=submit]')]);
  // Il corso.
  await addonPag('corsi', 'corsi');
  await p.fill('#co-titolo', 'Fotografia per cominciare');
  await foto('39-nuovo', { clip: { x: 240, y: 56, width: 960, height: 420 }, segni: [{ sel: '#co-titolo', n: 1 }, { sel: '#co-nuovo button', n: 2 }] });
  await Promise.all([p.waitForNavigation(), p.click('#co-nuovo button')]);
  const urlCorso = p.url().split('/admin/')[1];
  await p.fill('#co-descrizione', 'Le basi per fare belle foto con il telefono: la luce, l\'inquadratura, il primo scatto. Tre lezioni brevi, da seguire quando vuoi.');
  await p.selectOption('#co-stato', 'pubblicato');
  await Promise.all([p.waitForNavigation(), p.click('#co-dati button[type=submit]')]);
  await p.locator('.co-modulo').first().locator('input[name=titolo]').first().fill('Le basi');
  await Promise.all([p.waitForNavigation(), p.locator('.co-modulo').first().locator('.co-modulo-testa button:has-text("Salva")').click()]);
  const lezione = async (i, titolo) => {
    await vai(urlCorso);
    const f = p.locator('.co-modulo').nth(i).locator('form.co-nuova-lezione');
    await f.locator('input[name=titolo]').fill(titolo);
    await Promise.all([p.waitForNavigation(), f.locator('button').click()]);
    return p.url().split('/admin/')[1];
  };
  const uLuce = await lezione(0, 'La luce');
  await p.fill('#le-testo', '<p>La luce più bella arriva <b>presto la mattina</b> e poco prima del tramonto.</p><p>Mettiti con la luce di lato: le forme si vedono meglio.</p>');
  await p.fill('#le-video', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'); await p.fill('#le-durata', '8 minuti');
  await Promise.all([p.waitForNavigation(), p.click('#le-form aside button.btn-primary')]);
  const uInq = await lezione(0, 'L\'inquadratura');
  await p.fill('#le-testo', '<h2>La regola dei terzi</h2><p>Dividi lo schermo in nove riquadri: metti il soggetto dove le linee si incrociano, non al centro.</p><p>La scheda qui sotto la riassume: tienila nel telefono.</p>');
  await p.fill('#le-durata', '10 minuti');
  await p.locator('label.le-mat', { hasText: 'scheda-inquadratura.pdf' }).locator('input').check();
  await foto('39-lezione', { sel: '#le-form', segni: [{ sel: '#le-testo', n: 1 }, { sel: '#le-video', n: 2 }, { sel: 'label.le-mat', n: 3 }, { sel: '#le-durata', n: 4 }, { sel: '#le-form aside button.btn-primary', n: 5 }] });
  await Promise.all([p.waitForNavigation(), p.click('#le-form aside button.btn-primary')]);
  await vai(urlCorso);
  await p.fill('#co-nuovo-modulo input[name=titolo]', 'Pratica');
  await Promise.all([p.waitForNavigation(), p.click('#co-nuovo-modulo button')]);
  await lezione(1, 'Il primo scatto');
  await p.fill('#le-testo', '<p>Esci, scegli una cosa sola da fotografare e fanne dieci scatti diversi. Poi tieni il migliore.</p>'); await p.fill('#le-durata', '5 minuti');
  await Promise.all([p.waitForNavigation(), p.click('#le-form aside button.btn-primary')]);
  await vai(urlCorso);
  await foto('39-corso', { clip: { x: 240, y: 56, width: 960, height: 1000 }, segni: [{ sel: '#co-stato', n: 1 }, { sel: '.co-modulo .co-modulo-testa input[name=titolo]', n: 2 }, { sel: 'form.co-nuova-lezione', n: 3 }, { sel: '#co-nuovo-modulo', n: 4 }] });
  // Un'allieva, invitata dal pannello, segue il corso.
  await addonPag('membri', 'elenco');
  await p.fill('#mb-nome', 'Sara Galli'); await p.fill('#mb-email', 'sara.galli@esempio.it');
  let n0 = postaN();
  await Promise.all([p.waitForNavigation(), p.click('#mb-invita button')]);
  const m = (await postaNuove(n0)).find(e => /sara\.galli/.test(e.a));
  const lk = m && C2.link(m).find(l => /\/nuova-password\/\?t=/.test(l));
  if (!lk) throw new Error('39: non arriva l\'invito');
  const v = await visitatore();
  await v.goto(lk, { waitUntil: 'networkidle' });
  await v.fill('#membri-nuova-password [name=password]', 'Scatti-al-mattino-26'); await v.fill('#membri-nuova-password [name=password2]', 'Scatti-al-mattino-26');
  await v.click('#membri-nuova-password button'); await v.waitForLoadState('networkidle'); await dorme(500);
  await v.goto(SITO + '/corsi/fotografia-per-cominciare/', { waitUntil: 'networkidle' });
  await v.addStyleTag({ content: '#testata{visibility:hidden}' });
  await fotoDi(v, '39-programma', { sel: 'main', segni: [{ sel: 'a.corsi-riprendi', n: 1 }] });
  await v.click('a.corsi-riprendi'); await v.waitForLoadState('networkidle');
  await v.click('form.corsi-completa button'); await v.waitForLoadState('networkidle'); await dorme(400);
  await v.addStyleTag({ content: '#testata{visibility:hidden}' });
  await fotoDi(v, '39-lezione-sito', { sel: 'main', segni: [{ sel: 'a.corsi-materiale', n: 2 }, { sel: 'form.corsi-completa button', n: 3 }] });
  for (let i = 0; i < 2; i++) { await v.click('form.corsi-completa button'); await v.waitForLoadState('networkidle'); await dorme(400); }
  await v.click('a:has-text("Il tuo certificato")'); await v.waitForLoadState('networkidle');
  await fotoDi(v, '39-certificato', { clip: { x: 0, y: 0, width: 1200, height: 800 } });
  await v.context().close();
  await vai(urlCorso);
  await foto('39-chi-segue', { sel: '.sc-scatola:has(#co-iscritti)', margine: 10 });
};

// 40. Copia del sito altrove: le copie di ogni notte su un Nextcloud (WebDAV). La destinazione vera della prova è un WebDAV
// finto in locale (prove/server-copia-finti.py webdav 8297 <cartella> copie Nuvola-Copie-2026, e nel config.local.php del sito
// define('CMS_COPIA_ALTROVE_LOCALE', '127.0.0.1:8297')): la foto del modulo si fa con l'indirizzo d'esempio, prima di salvare.
capitoli['40'] = async () => {
  await accendi('copia-altrove');
  await addonPag('copia-altrove', 'destinazione');
  await p.selectOption('#ca-tipo', 'webdav'); await dorme(300);
  await p.fill('#ca-url', 'https://cloud.esempio.it/remote.php/dav/files/mario/');
  await p.fill('#ca-utente', 'mario'); await p.fill('#ca-password', 'password-per-le-app');
  await p.fill('#ca-cartella', 'copie-sito'); await p.fill('#ca-tenere', '14'); await p.check('#ca-attivo');
  await foto('40-destinazione', { sel: '#ca-form', segni: [{ sel: '#ca-tipo', n: 1 }, { sel: '#ca-url', n: 2 }, { sel: '#ca-utente', n: 3 }, { sel: '#ca-cartella', n: 4 }, { sel: '#ca-tenere', n: 5 }, { sel: '#ca-attivo', n: 6 }, { sel: '#ca-form button[type=submit]', n: 7 }] });
  await p.fill('#ca-url', 'http://127.0.0.1:8297/dav/'); await p.fill('#ca-utente', 'copie'); await p.fill('#ca-password', 'Nuvola-Copie-2026');
  await Promise.all([p.waitForNavigation(), p.click('#ca-form button[type=submit]')]);
  if (!/Destinazione salvata/.test(await flash())) throw new Error('40: destinazione non salvata: ' + await flash());
  await Promise.all([p.waitForNavigation(), p.click('#ca-prova')]);
  if (!/Collegamento riuscito/.test(await flash())) throw new Error('40: la prova non riesce: ' + await flash());
  await foto('40-prova', { clip: { x: 240, y: 56, width: 960, height: 120 }, segni: [{ sel: '.flash', n: 8 }] });
  await Promise.all([p.waitForNavigation({ timeout: 180000 }), p.click('#ca-manda')]);
  if (!/Copia mandata/.test(await flash())) throw new Error('40: la copia non parte: ' + await flash());
  await foto('40-mandata', { clip: { x: 240, y: 56, width: 960, height: 120 }, segni: [{ sel: '.flash', n: 9 }] });
  await foto('40-registro', { sel: '#ca-registro', margine: 10 });
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
  if (!SOLO && !SOLI.length) for (const d of [USCITA, GREZZE]) for (const f of fs.readdirSync(d)) if (/\.(jpg|png)$/.test(f)) fs.unlinkSync(path.join(d, f));
  for (const [n, fn] of Object.entries(capitoli).sort((a, b) => a[0].localeCompare(b[0]))) { // '10' e oltre sono chiavi numeriche: JavaScript le mette prima di '01'
    if (SOLO && !SOLO.startsWith(n) && !n.startsWith(SOLO)) continue;
    if (SOLI.length && !SOLI.includes(n)) continue;
    await fn();
  }
  if (!SOLI.length) await fotoWidgetAttivita();
  if (err.length) console.log('errori JavaScript:', err);
  stringi();
  const tot = fs.readdirSync(USCITA).filter(f => f.endsWith('.jpg')).reduce((t, f) => t + fs.statSync(path.join(USCITA, f)).size, 0);
  console.log('immagini del manuale: ' + Math.round(tot / 1024) + ' KB');
  await br.close();
})().catch(e => { console.error('FERMO:', e.message); process.exit(1); });
