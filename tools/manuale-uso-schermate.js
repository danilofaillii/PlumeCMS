// Le schermate del manuale d'uso (1.4.2): esegue gli esempi del manuale come una persona e fotografa ogni passo.
// Se un passo del manuale non si può fare, lo script si ferma: il manuale e il pannello restano d'accordo.
// Serve un plumeCMS appena installato con il kit Attività (prove/prova-kit.js ... attivita ...), utente prova.
// uso: [CMS_DIR_MANUALE=<cartella del sito delle schermate>] [SU_MISURA_BASE=<un sito su misura, stesso utente>] node tools/manuale-uso-schermate.js <base> <utente> <password> [solo un capitolo, es. 04]
// Senza SU_MISURA_BASE mancano le due foto del sito su misura (02-su-misura-home, 07-sostituisci).
// Scrive cms/docs/immagini/manuale-uso/<capitolo>-<nome>.jpg (JPEG nitidi: dal 2.1.0 il manuale è un PDF solo, sotto 8 MB).
// 2.0.0: i capitoli 31n (le novità della 2.0.0 nei capitoli di prima: file riservati, form che dà un file, Scegli dai Media) e 32-40 (la Parte seconda, gli addon) girano dopo tutti gli altri e vogliono il server di posta finto:
//   python3 -I prove/smtp-finto.py <porta> <cartella> &   e   POSTA_MANUALE=<porta>:<cartella>
// Per rifare solo alcuni capitoli, esattamente quelli: SOLO_CAPITOLI=31n,32,33 (le foto degli altri restano come sono).
// 2.1.0: il capitolo 31q (Consegna delle email) vuole il server di posta finto con "rimbalzi" (prove/smtp-finto.py <porta> <cartella> rimbalzi)
//   e, nel config.local.php del sito, CMS_DNS_PROVA con i record finti di studiorossi.it (senza, la foto del Dominio dice che il DNS non
//   risponde); il capitolo 40 il WebDAV finto (COPIA_PORTA, 8297 se manca). I widget della 2.1 (08-menu-ristorante, 08-galleria-filtri)
//   vogliono CMS_DIR_MANUALE. Il giro completo cancella prima tutte le foto: ognuna deve nascere da qui.
// Infografiche disegnate: le sedici infografiche disegnate (08-infografiche; quindici fino alla 2.25.1), una foto d'insieme dalla pagina di prove/pagina-infografiche-15.php:
//   vuole CMS_DIR_MANUALE; da sola con SOLO_CAPITOLI=infografiche.
// 2.2.0: Scegli dal sito (08-scegli-dal-sito, 11-prenota-1 e 2 con l'esempio eseguito davvero, 17-menu-scegli, 19-popup-scegli; la
//   finestra 08-scegli-finestra dopo tutti i capitoli, con la pagina in bozza "Condizioni di vendita") e 28-server con il gruppo
//   "Google e IndexNow" (due foto della stessa pagina unite).
// 2.9.0: il capitolo 19i (Importare ed esportare i contatti, la lista dei test): SOLO_CAPITOLI=19i, con TMPDIR vuota (in
//   /tmp/manuale-uso-png possono esserci PNG vecchi che il giro ricopierebbe).
// 2.10.0: il capitolo 19j (Segmenti, Aggiungi filtro, viste) dopo il 19i, di cui usa i contatti: SOLO_CAPITOLI=19i,19j.
// 2.11.0: il capitolo 19k (la campagna a riepilogo, l'elenco, la pagina per uscire, il centro preferenze) dopo il 19, il 19i e il
//   19j: SOLO_CAPITOLI=19,19i,19j,19k, con CMS_DIR_MANUALE e POSTA_MANUALE (la campagna parte davvero).
// 2.13.0: il capitolo 19n (il test A/B) gira da solo: SOLO_CAPITOLI=19n, con CMS_DIR_MANUALE e POSTA_MANUALE.
// 2.14.0: il capitolo 19o (report, statistiche, orario migliore; rifà anche 19-programma e 23-consegna-statistiche) gira da
//   solo: SOLO_CAPITOLI=19o, con CMS_DIR_MANUALE e POSTA_MANUALE e TMPDIR vuota.
// 2.15.0: il capitolo 19p (le automazioni, al posto della foto 19-flussi) gira da solo: SOLO_CAPITOLI=19p, con CMS_DIR_MANUALE e
//   POSTA_MANUALE e TMPDIR vuota.
// 2.18.0: il 19p fa anche 19p-modello (il passo Email che richiama il modello "Ebook 05") e rifà 19m-modelli con "Usato in".
// 2.16.0: i form a cinque passi: capitoli 17 (rifatto, con il campo File) e 17n (form di iscrizione, pagina del form, Pagine e
//   consenso), poi 31n e 31r con il costruttore nuovo: SOLO_CAPITOLI=17,17n,31n,31r su un sito col kit appena installato, TMPDIR vuota.
const { chromium } = require('playwright');
const pnConferme = require('../prove/conferme-pannello'); // UX 2026-11: conferme del pannello
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
      const r0 = e.getBoundingClientRect();
      // 2.1.0: un elemento dentro un dialogo modale (Scegli dai Media) sta sopra a tutto (top layer): i segni vanno dentro il
      // dialogo, con le coordinate del dialogo.
      // 2.2.0: un elemento fuori dal dialogo aperto (il pulsante Scegli dal sito, sotto il velo della finestra): { sopra: true }
      // mette il segno in uno strato popover, che sta sopra al dialogo; coordinate della finestra.
      if (v.sopra) {
        let st = document.querySelector('.mu-segno-strato');
        if (!st) {
          st = document.createElement('div'); st.className = 'mu-segno mu-segno-strato'; st.setAttribute('popover', 'manual');
          Object.assign(st.style, { position: 'fixed', inset: '0', width: '100vw', height: '100vh', margin: '0', padding: '0', border: '0', background: 'transparent', overflow: 'visible', pointerEvents: 'none' });
          document.body.appendChild(st); st.showPopover();
        }
        const box = document.createElement('div');
        Object.assign(box.style, { position: 'absolute', left: (r0.left - 4) + 'px', top: (r0.top - 4) + 'px', width: (r0.width + 8) + 'px', height: (r0.height + 8) + 'px',
          border: '3px solid #e8553e', borderRadius: '8px', boxSizing: 'border-box' });
        st.appendChild(box);
        if (v.n) {
          const b = document.createElement('div'); b.textContent = v.n;
          Object.assign(b.style, { position: 'absolute', left: Math.max(2, v.lato === 'destra' ? r0.right - 6 : r0.left - 16) + 'px', top: Math.max(2, r0.top - 16) + 'px', width: '26px', height: '26px',
            borderRadius: '50%', background: '#e8553e', color: '#fff', font: '700 15px/26px Inter, sans-serif', textAlign: 'center', boxShadow: '0 1px 4px rgba(0,0,0,.35)' });
          st.appendChild(b);
        }
        continue;
      }
      const dlg = e.closest('dialog[open]'), dr = dlg ? dlg.getBoundingClientRect() : null;
      const dove = dlg || document.body;
      const r = dlg ? { left: r0.left - dr.left - dlg.clientLeft + dlg.scrollLeft - scrollX, top: r0.top - dr.top - dlg.clientTop + dlg.scrollTop - scrollY, right: r0.right - dr.left - dlg.clientLeft + dlg.scrollLeft - scrollX, width: r0.width, height: r0.height } : r0;
      const box = document.createElement('div');
      box.className = 'mu-segno';
      Object.assign(box.style, { position: 'absolute', left: (r.left + scrollX - 4) + 'px', top: (r.top + scrollY - 4) + 'px', width: (r.width + 8) + 'px',
        height: (r.height + 8) + 'px', border: '3px solid #e8553e', borderRadius: '8px', zIndex: 99998, pointerEvents: 'none', boxSizing: 'border-box' });
      dove.appendChild(box);
      if (v.n) {
        const b = document.createElement('div');
        b.className = 'mu-segno';
        b.textContent = v.n;
        const sx = Math.max(2, v.lato === 'destra' ? r.right + scrollX - 6 : r.left + scrollX - 16);
        Object.assign(b.style, { position: 'absolute', left: sx + 'px', top: Math.max(2, r.top + scrollY - 16) + 'px', width: '26px', height: '26px', borderRadius: '50%',
          background: '#e8553e', color: '#fff', font: '700 15px/26px Inter, sans-serif', textAlign: 'center', zIndex: 99999, pointerEvents: 'none',
          boxShadow: '0 1px 4px rgba(0,0,0,.35)' });
        dove.appendChild(b);
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
// del PDF). 2.2.0: qualità 55 (era 65, il PDF era a 8089 KB su un tetto di 8192): le scritte si leggono ancora bene anche
// ingrandite, il PDF scende di circa 1 MB. Provato il PNG a 256 colori (più leggero per molte schermate): Chromium lo rimette nel
// PDF senza tavolozza e il PDF sale a 9,6 MB. Nel PDF va il JPEG, che Chromium copia così com'è.
const LARGO = 1200, QUALITA = 55;
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
  // 2.6.0: la finestra di conferma del pannello (si apre e si chiude con Annulla: nessun tema si cancella).
  await vai('temi.php');
  pnConferme.ferma(p, true);
  await p.locator('.tt-card button', { hasText: 'Elimina' }).first().click();
  await p.waitForSelector('dialog.pn-conferma[open]'); await dorme(300);
  await foto('01-conferma', { sel: 'dialog.pn-conferma', margine: 0, segni: [{ sel: '#pn-conferma-testo', n: 1 }, { sel: 'dialog.pn-conferma .pn-conferma-no', n: 2 }, { sel: 'dialog.pn-conferma .pn-conferma-si', n: 3, lato: 'destra' }] });
  await p.click('dialog.pn-conferma .pn-conferma-no'); await dorme(200);
  pnConferme.ferma(p, false);
};

// 02. Il tipo di sito in prova
capitoli['02'] = async () => {
  await vai('index.php#tipo-sito'); // UX 2026-11: il riquadro e chiuso dopo la prima visita, l'indirizzo con #tipo-sito lo apre
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

// 2.2.0: Scegli dal sito (assets/scegli-link.js). marca() dà a un elemento un nome per i segni ([data-mu=...]); apriScelta() apre la
// finestra dal pulsante accanto a una casella, scrive nella ricerca e aspetta l'elenco; rigaSito() è la riga con quel titolo.
const marca = (loc, nome) => loc.first().evaluate((e, n) => e.setAttribute('data-mu', n), nome);
const SCELTA = 'dialog.pn-link[open]';
async function apriScelta(bottone, cerca) {
  await bottone.click();
  await p.waitForSelector(SCELTA + ' .pn-link-voce'); await dorme(300);
  if (cerca) await p.fill(SCELTA + ' input[type=search]', cerca);
  await dorme(400);
  await p.waitForFunction((s) => { const l = document.querySelector(s + ' .pn-link-lista'); return l && !l.hasAttribute('aria-busy'); }, SCELTA); await dorme(300);
}
const rigaSito = (titolo) => p.locator(SCELTA + ' .pn-link-voce').filter({ has: p.locator('.pn-link-nome', { hasText: new RegExp('^' + titolo + '$') }) }).first();
// Il primo pulsante dell'Apertura della home, aperto nel pannello Modifica (capitoli 8 e 11).
async function primoPulsanteApertura() {
  await apriEditor('editor.php?tipo=pagine&id=1');
  await tela().locator('.ed-w-hero').first().dblclick(); await dorme(600);
  const v = p.locator('#ed-controlli .ed-voce').first();
  await v.scrollIntoViewIfNeeded();
  if (await v.locator('.ed-voce-corpo').isHidden()) { await v.locator('.ed-voce-testa b').click(); await dorme(300); }
  const campo = (et) => v.locator('.ed-voce-corpo .ed-controllo').filter({ has: p.locator('.ed-etichetta, label', { hasText: new RegExp('^' + et + '$') }) }).first();
  await campo('Link').scrollIntoViewIfNeeded(); await dorme(200);
  return { voce: v, testo: campo('Testo').locator('input[type=text]').first(), link: campo('Link').locator('input[type=text]').first(), scegli: campo('Link').locator('.pn-link-scegli').first() };
}

// 08. L'editor: com'è fatto
capitoli['08'] = async () => {
  await apriEditor('editor.php?tipo=pagine&id=1');
  await foto('08-editor', { clip: { x: 0, y: 0, width: 1200, height: 800 }, segni: [
    { sel: '.ed-schede-pannello', n: 1 }, { sel: '#ed-tela', n: 2 }, { sel: '.ed-misure', n: 3 }, { sel: '.ed-storia', n: 4 },
    { sel: '#ed-apri-impostazioni', n: 5 }, { sel: '#ed-apri-revisioni', n: 6 }, { sel: '#ed-vedi', n: 7 }, { sel: '#ed-salva', n: 8 }, { sel: '#ed-pubblica', n: 9 }] });
  await tela().locator('.ed-w-hero').first().dblclick(); await dorme(600);
  await foto('08-modifica', { clip: { x: 0, y: 0, width: 1200, height: 520 }, segni: [{ sel: '#ed-schede-controlli', n: 1 }, { sel: '#ed-modifica-azioni', n: 2 }] });
  // 2.2.0: il link del primo pulsante dell'Apertura, con Scegli dal sito accanto (la finestra la fotografa fotoSceltaSito(), dopo
  // tutti i capitoli: vuole una pagina in bozza).
  const pu = await primoPulsanteApertura();
  await marca(pu.link, 'link'); await marca(pu.scegli, 'scegli');
  await pu.voce.evaluate(e => { const c = e.closest('.ed-controllo'); c.setAttribute('data-mu', 'pulsanti'); c.scrollIntoView({ block: 'center' }); }); await dorme(300);
  await foto('08-scegli-dal-sito', { sel: '[data-mu=pulsanti]', margine: 12, segni: [{ sel: '[data-mu=link]', n: 1 }, { sel: '[data-mu=scegli]', n: 2, lato: 'destra' }] });
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
  // 2.1.0: Scegli dai Media, sul campo dell'immagine social della SEO (solo le foto: non si salva niente, la casella la riempie
  // il capitolo 31n).
  await vai('seo.php?scheda=globale');
  await p.click('[data-scegli-media="#seo-og-immagine"]');
  await p.waitForSelector('dialog.pn-media .pn-media-voce'); await dorme(900);
  await foto('07-scegli-finestra', { sel: 'dialog.pn-media', margine: 0, segni: [{ sel: 'dialog.pn-media input[type=search]', n: 1 }, { sel: 'dialog.pn-media .pn-media-cartella select', n: 2 },
    { sel: 'dialog.pn-media .pn-media-carica', n: 3, lato: 'destra' }, { sel: 'dialog.pn-media .pn-media-voce', n: 4 }, { sel: 'dialog.pn-media .pn-media-indirizzo summary', n: 5 }] });
  await p.locator('dialog.pn-media .pn-media-voce', { hasText: 'squadra' }).first().click(); await dorme(800);
  await foto('07-scegli-campo', { sel: '.form-group:has(#seo-og-immagine)', margine: 22, segni: [{ sel: '.form-group:has(#seo-og-immagine) .pn-immagine-mini', n: 1 },
    { sel: '[data-scegli-media="#seo-og-immagine"]', n: 2 }, { sel: '.form-group:has(#seo-og-immagine) .pn-immagine-x', n: 3, lato: 'destra' }] });
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

// 11v. (2.17.0) L'Apertura con il video: il video caricato in Media (riquadro Video), poi nell'editor la disposizione Video e
// Scegli in modo video. Non pubblica (la home resta com'è). Da solo: SOLO_CAPITOLI=11v, con TMPDIR vuota.
capitoli['11v'] = async () => {
  await vai('media.php');
  await p.setInputFiles('#md-video-file', path.join(FOTO_PROVA, 'presentazione.mp4'));
  await Promise.all([p.waitForNavigation(), p.click('.md-video-carica button[type=submit]')]);
  if (!/Video caricato/.test(await p.locator('.flash').first().innerText())) throw new Error('11v: il video non si carica: ' + await p.locator('.flash').first().innerText());
  await apriEditor('editor.php?tipo=pagine&id=1');
  await tela().locator('.ed-w-hero').first().dblclick(); await dorme(600);
  const disp = await ctl('Disposizione');
  await disp.locator('select').selectOption('video'); await dorme(1500);
  const campo = p.locator('#ed-controlli .ed-riga-video');
  await campo.scrollIntoViewIfNeeded();
  await campo.locator('button', { hasText: 'Scegli' }).click(); await dorme(900);
  await p.locator('#ed-dialogo-media .ed-media-griglia button').first().click(); await dorme(1500);
  if (!(await tela().locator('.ed-hvi-play').count())) throw new Error('11v: nella tela non c\'è il play');
  await campo.evaluate(e => e.closest('.ed-controllo').setAttribute('data-mu', 'video'));
  await disp.evaluate(e => e.setAttribute('data-mu', 'disp'));
  await foto('11-video', { clip: { x: 0, y: 0, width: 1200, height: 760 }, segni: [{ sel: '[data-mu=video]', n: 3 }] });
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
  // 2.2.0: l'esempio "Prenota", eseguito davvero (la disposizione Centrata di sopra non si salva: l'editor si riapre da capo).
  const pu = await primoPulsanteApertura();
  await pu.testo.fill('Prenota'); await dorme(400);
  await marca(pu.testo, 'testo');
  await foto('11-prenota-1', { clip: { x: 0, y: 0, width: 1200, height: 800 }, segni: [{ sel: '#ed-titolo-pagina', n: 1, lato: 'destra' }, { sel: '#ed-schede-controlli', n: 2 }, { sel: '[data-mu=testo]', n: 3 }] });
  await marca(pu.scegli, 'scegli');
  await apriScelta(pu.scegli, 'cont');
  const riga = rigaSito('Contatti');
  await riga.hover(); await marca(riga, 'contatti'); await dorme(200);
  await foto('11-prenota-2', { clip: { x: 0, y: 0, width: 1200, height: 800 }, segni: [{ sel: '[data-mu=scegli]', n: 4, sopra: true },
    { sel: SCELTA + ' input[type=search]', n: 5 }, { sel: '[data-mu=contatti]', n: 6 }] });
  await riga.click(); await dorme(500);
  if (await pu.link.inputValue() !== '/contatti/') throw new Error('11: nella casella Link non c\'è /contatti/: ' + await pu.link.inputValue());
  await pubblica();
  const v = await ctx.newPage();
  await v.goto(B + '/', { waitUntil: 'networkidle' });
  await Promise.all([v.waitForNavigation(), v.locator('.ed-w-hero a', { hasText: 'Prenota' }).first().click()]);
  if (!/\/contatti\/$/.test(v.url())) throw new Error('11: Prenota porta a ' + v.url());
  await v.close();
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
  await vai('impostazioni.php?scheda=sito'); // UX 2026-11: la frase sta nella scheda Sito
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
  // 2.2.0: Link personalizzato con Scegli dal sito (solo la foto: la voce non si aggiunge).
  await vai('menu.php');
  const lp = p.locator('details', { has: p.locator('summary', { hasText: /^Link personalizzato/ }) }).first();
  if ((await lp.getAttribute('open')) === null) await lp.locator('summary').click();
  const bs = lp.locator('.pn-link-scegli').first();
  await apriScelta(bs, 'cont');
  await rigaSito('Contatti').click(); await dorme(500);
  if (await p.inputValue('#mn-link-url') !== '/contatti/' || await p.inputValue('#mn-link-testo') !== 'Contatti') throw new Error('16: Scegli dal sito non riempie la voce');
  await marca(bs, 'scegli'); await marca(lp, 'lp');
  await foto('17-menu-scegli', { sel: '[data-mu=lp]', margine: 16, segni: [{ sel: '[data-mu=scegli]', lato: 'destra' }] });
};

// 17. Form: le candidature di "Lavora con noi". 2.16.0: il costruttore a cinque passi (Impostazione, Aspetto e campi,
// Messaggi, Conferma, Condividi), con il campo File del 2.1 nel passo 2.
capitoli['17'] = async () => {
  await vai('form.php?nuovo=1');
  await foto('17-tipi', { sel: '.fo-tipi-scatola', margine: 6, segni: [{ sel: '.fo-tipi li:nth-child(1) a', n: 1 }, { sel: '.fo-tipi li:nth-child(5) a', n: 2 }] });
  await Promise.all([p.waitForNavigation(), p.click('.fo-tipi li:nth-child(5) a')]);
  await p.fill('#fm-nome', 'Candidature');
  await foto('17-form', { clip: { x: 240, y: 56, width: 960, height: 520 }, segni: [{ sel: '.fo-passi', n: 1 }, { sel: '#fm-nome', n: 2 }, { sel: '#fm-form .fo-azioni .btn-primary', n: 3, lato: 'destra' }] });
  await Promise.all([p.waitForNavigation(), p.click('#fm-form .fo-azioni .btn-primary')]);
  if (!/passo=2/.test(p.url())) throw new Error('17: il form non è stato creato: ' + await flash());
  await p.fill('#fm-titolo', 'Mandaci la tua candidatura');
  await p.fill('#fm-intro', 'Due righe su di te bastano. Ti rispondiamo entro una settimana.');
  await p.fill('#fm-pulsante', 'Invia la candidatura');
  // Il campo File (2.1): il curriculum, solo PDF e Word, fino a 5 MB.
  await p.click('button:has-text("+ Aggiungi un campo")'); await dorme(300);
  await p.locator('.fm-campo:last-child .fm-etichetta').fill('Curriculum');
  await p.locator('.fm-campo:last-child .fm-tipo').selectOption('file'); await dorme(300);
  const tipo = (e) => p.locator(`.fm-campo:last-child .fm-file-tipi input[value=${e}]`);
  await tipo('jpg').uncheck(); await tipo('png').uncheck(); await tipo('docx').check();
  await p.locator('.fm-campo:last-child .fm-file-peso select').selectOption('5');
  await foto('17-campi', { clip: { x: 240, y: 56, width: 960, height: 760 }, segni: [{ sel: '#fm-titolo', n: 1 }, { sel: '.fm-campo:first-child .fm-tipo', n: 2 }, { sel: '.fm-campo:first-child .fm-attributo', n: 3 }, { sel: 'button[data-al-clic="fmAggiungi()"]', n: 4 }] });
  await foto('17-campo-file', { sel: '.fm-campo:last-child', margine: 30, segni: [{ sel: '.fm-campo:last-child .fm-tipo', n: 1 }, { sel: '.fm-campo:last-child .fm-file-tipi', n: 2 },
    { sel: '.fm-campo:last-child .fm-file-peso select', n: 3 }] });
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=avanti]')]);
  if (!/passo=3/.test(p.url())) throw new Error('17: passo 2 non salvato: ' + await flash());
  // Passo 3: dopo l'invio e l'avviso (1.9.0: a chi arriva).
  await p.fill('#fm-grazie-titolo', 'Grazie, abbiamo la tua candidatura.');
  await foto('17-messaggi', { sel: '.sc-scatola:has(input[name=dopo_invio])', altezza: 560, segni: [{ sel: 'input[name=dopo_invio][value=pagina]', n: 1 }, { sel: '#fm-riuscita', n: 2 }, { sel: '#fm-grazie-titolo', n: 3 }] });
  await p.fill('#fm-avviso-a', 'lavoro@studioprova.it, marta@studioprova.it');
  await foto('17-avviso', { sel: '.sc-scatola:has(#fm-avviso-a)', segni: [{ sel: 'input[name=avviso]', n: 1 }, { sel: '#fm-avviso-a', n: 2 }] });
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=avanti]')]);
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=avanti]')]);
  if (!/passo=5/.test(p.url())) throw new Error('17: passo 4 non salvato: ' + await flash());
  await foto('17-condividi', { clip: { x: 240, y: 56, width: 960, height: 760 }, segni: [{ sel: '#fo-link', n: 1 }, { sel: '#fo-iframe', n: 2 }, { sel: '#fo-html', n: 3 }, { sel: '#fo-semplice', n: 4 }, { sel: '.fo-qr svg', n: 5 }] });
  // Nella pagina, sotto il pulsante "Scrivici"
  await apriEditor('editor.php?tipo=pagine&id=6');
  await tela().locator('#ed-pagina .ed-w-pulsanti').first().click(); await dorme(400);
  await widget('modulo');
  const q = await ctl('Quale modulo');
  const val = await q.locator('option', { hasText: 'Candidature' }).getAttribute('value');
  await q.locator('select').selectOption(val); await dorme(1500);
  await foto('17-widget', { clip: { x: 0, y: 0, width: 1200, height: 700 }, segni: [{ sel: '#ed-controlli .ed-controllo select', n: 6 }] });
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

// 17n. (2.16.0) Un form di iscrizione alla newsletter: campi legati agli attributi, Iscrizione a più liste, GDPR, la conferma,
// la pagina del form; poi Pagine e consenso (gruppi di consenso). Dopo il 17. Da solo: SOLO_CAPITOLI=17n.
capitoli['17n'] = async () => {
  await vai('newsletter-contatti.php?scheda=liste');
  // Due liste da scegliere nel form (si creano qui se mancano).
  for (const nome of ['Offerte del mese', 'Consigli per le piante']) {
    if ((await p.content()).includes(nome)) continue;
    const f = p.locator('form:has(input[value=lista_crea])').first();
    if (!(await f.count())) break;
    await f.locator('input[name=nome]').fill(nome);
    await Promise.all([p.waitForNavigation(), f.locator('button[type=submit]').click()]);
  }
  await vai('form.php?nuovo=1&tipo=iscrizione');
  await p.fill('#fm-nome', 'Iscrizione alle novità');
  await p.check('input[name=gdpr]');
  await Promise.all([p.waitForNavigation(), p.click('#fm-form .fo-azioni .btn-primary')]);
  if (!/passo=2/.test(p.url())) throw new Error('17n: il form non è stato creato: ' + await flash());
  await p.fill('#fm-titolo', 'Le novità dello studio, una volta al mese');
  await p.click('button:has-text("+ Aggiungi un campo")'); await dorme(300);
  await p.locator('.fm-campo:last-child .fm-etichetta').fill('Scegli cosa ricevere');
  await p.locator('.fm-campo:last-child .fm-tipo').selectOption('liste'); await dorme(300);
  const scelte = p.locator('.fm-campo:last-child .fm-scelta-liste label');
  for (const t of ['Offerte del mese', 'Consigli per le piante']) await scelte.filter({ hasText: t }).locator('input').check();
  await p.click('button:has-text("+ Aggiungi un campo")'); await dorme(300);
  await p.locator('.fm-campo:last-child .fm-etichetta').fill('Nuovo campo');
  await p.locator('.fm-campo:last-child .fm-tipo').selectOption('tracciamento'); await dorme(300);
  await p.fill('#fm-gdpr-testo', 'Usiamo il tuo indirizzo solo per mandarti le email che scegli qui. Puoi uscire quando vuoi con il link in fondo a ogni email.');
  await foto('17n-campi', { sel: '#fm-form .sc-griglia', margine: 6, segni: [{ sel: '.fm-campo:nth-child(1) .fm-attributo', n: 1 }, { sel: '.fm-campo:nth-last-child(2) .fm-scelta-liste', n: 2 }, { sel: '.fm-campo:last-child .fm-tipo', n: 3 }, { sel: '#fm-gdpr-testo', n: 4 }] });
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=avanti]')]);
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=avanti]')]);
  if (!/passo=4/.test(p.url())) throw new Error('17n: passo 3 non salvato: ' + await flash());
  await p.selectOption('#fm-lista', { label: 'Newsletter' });
  await foto('17n-conferma', { sel: '.sc-scatola:has(input[name=conferma])', altezza: 520, segni: [{ sel: 'input[name=conferma][value=doppia]', n: 1 }, { sel: '#fm-lista', n: 2 }, { sel: 'input[name=aggiorna_esistenti]', n: 3 }] });
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=avanti]')]);
  if (!/passo=5/.test(p.url())) throw new Error('17n: passo 4 non salvato: ' + await flash());
  const url = await p.inputValue('#fo-link');
  const v = await ctx.newPage();
  await v.setViewportSize({ width: 900, height: 900 });
  await v.goto(url.replace(/^https?:\/\/[^/]+/, B.replace(/^(https?:\/\/[^/]+).*$/, '$1')), { waitUntil: 'networkidle' });
  await v.screenshot({ path: path.join(GREZZE, '17n-ospitata.png'), fullPage: true }); console.log('17n-ospitata');
  await v.close();
  // Pagine e consenso: i gruppi di consenso (spenti di partenza: li accende chi li vuole).
  await vai('newsletter-consenso.php');
  await p.fill('#nc-g-nome', 'Offerte');
  await p.fill('#nc-g-descr', 'Sconti e promozioni, una volta al mese');
  await foto('17n-consenso', { clip: { x: 240, y: 56, width: 960, height: 700 }, segni: [{ sel: '#nc-attivi input[name=attivi]', n: 1 }, { sel: '#nc-g-nome', n: 2 }, { sel: '#nc-gruppo input[name=ingresso][value=automatic]', n: 3 }, { sel: '#nc-gruppo button[type=submit]', n: 4, lato: 'destra' }] });
};

// 18. Popup
capitoli['18'] = async () => {
  await vai('popup.php?nuovo=1');
  await p.fill('[name=nome]', 'Saldi di gennaio');
  await p.fill('[name=occhiello]', 'Solo questa settimana');
  await p.fill('[name=titolo]', 'Saldi: meno 30% su tutto');
  await p.fill('[name=testo]', 'Vieni in negozio entro domenica.');
  await p.fill('[name=pulsante_testo]', 'Come arrivare');
  // 2.2.0: il link del pulsante con Scegli dal sito.
  await apriScelta(p.locator('.pn-link-campo:has(#pp-link) .pn-link-scegli'), 'cont');
  await rigaSito('Contatti').click(); await dorme(400);
  if (await p.inputValue('#pp-link') !== '/contatti/') throw new Error('18: Scegli dal sito non riempie il link del pulsante');
  // La casella Indirizzi: due pagine scelte dal sito, una per riga (solo per la foto: il popup resta su tutte le pagine).
  const bi = p.locator('.pn-link-campo:has(#pp-percorsi) .pn-link-scegli');
  await apriScelta(bi, 'cont'); await rigaSito('Contatti').click(); await dorme(300);
  await apriScelta(bi, 'serv'); await rigaSito('Servizi').click(); await dorme(300);
  if (await p.inputValue('#pp-percorsi') !== '/contatti/\n/servizi/') throw new Error('18: indirizzi: ' + JSON.stringify(await p.inputValue('#pp-percorsi')));
  {
    await p.evaluate(() => scrollTo(0, 0)); await dorme(100);
    const a = await p.locator('.sc-riga:has(#pp-link)').boundingBox(), b = await p.locator('.pn-link-campo:has(#pp-percorsi)').boundingBox();
    const y0 = Math.min(a.y, b.y) - 50, y1 = Math.max(a.y + a.height, b.y + b.height) + 24;
    await foto('19-popup-scegli', { clip: { x: 240, y: y0, width: 960, height: y1 - y0 }, segni: [{ sel: '.pn-link-campo:has(#pp-link) .pn-link-scegli', n: 1, lato: 'destra' },
      { sel: '.pn-link-campo:has(#pp-percorsi)', n: 2 }] });
  }
  await p.fill('#pp-percorsi', '');
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
  // 2.8.0: i contatti come Brevo. Prima un attributo (Data di nascita), poi tre contatti creati a mano, uno in lista nera.
  await vai('newsletter-attributi.php');
  await p.click('button[data-apri-dialogo="#nla-crea"]');
  await p.selectOption('#nla-tipo', 'date'); await p.fill('#nla-nome', 'DATA_NASCITA'); await p.fill('#nla-etichetta', 'Data di nascita');
  await foto('19-attributo', { sel: '#nla-crea', margine: 8, segni: [{ sel: '#nla-tipo', n: 1 }, { sel: '#nla-nome', n: 2 }, { sel: '#nla-etichetta', n: 3 }, { sel: '#nla-crea button[type=submit]', n: 4 }], dove: p });
  await Promise.all([p.waitForNavigation(), p.click('#nla-crea button[type=submit]')]);
  for (const [e, n, c, d] of [['bruno.verdi@esempio.it', 'Bruno', 'Verdi', ''], ['carla.bianchi@esempio.it', 'Carla', 'Bianchi', ''], ['anna.rossi@esempio.it', 'Anna', 'Rossi', '1985-04-12']]) {
    await vai('newsletter-contatto.php?nuovo=1');
    await p.fill('#nlk-email', e); await p.fill('#nlk-nome', n); await p.fill('#nlk-cognome', c);
    if (d) await p.fill('#nlk-data-nascita', d);
    await p.locator('input[name="liste[]"]').first().check();
    if (n === 'Anna') await foto('19-crea', { clip: { x: 240, y: 56, width: 960, height: 700 }, segni: [{ sel: '#nlk-email', n: 1 }, { sel: '#nlk-nome', n: 2 }, { sel: 'input[name="liste[]"]', n: 3 }, { sel: 'form.nlk-largo button.btn-primary', n: 4 }] });
    await Promise.all([p.waitForNavigation(), p.click('button:has-text("Crea")')]);
  }
  await foto('19-contatto', { clip: { x: 240, y: 56, width: 960, height: 640 }, segni: [{ sel: 'nav.imp-schede', n: 1 }, { sel: 'form.form-card button.btn-primary', n: 2 }] });
  // Bruno in lista nera dalla sua scheda, Preferenze.
  await vai('newsletter-contatti.php?cerca=bruno');
  await Promise.all([p.waitForNavigation(), p.locator('a', { hasText: 'bruno.verdi@esempio.it' }).click()]);
  await Promise.all([p.waitForNavigation(), p.locator('nav.imp-schede a', { hasText: 'Preferenze' }).click()]);
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Metti in lista nera")')]);
  await foto('19-preferenze', { clip: { x: 240, y: 56, width: 960, height: 560 }, segni: [{ sel: '.nlk-pref-stato', n: 1 }, { sel: 'input[name=consenso]', n: 2 }] });
  await vai('newsletter-contatti.php');
  await foto('19-contatti', { clip: { x: 240, y: 56, width: 960, height: 760 }, segni: [{ sel: 'a[href="newsletter-contatto.php?nuovo=1"]', n: 1, lato: 'destra' }, { sel: '#nlx-lista', n: 2 }, { sel: '#nlx-cosa', n: 3 }, { sel: 'table.nlx-tabella thead', n: 4 }] });
  await vai('newsletter-contatti.php?scheda=liste');
  await foto('19-liste', { clip: { x: 240, y: 56, width: 960, height: 520 }, segni: [{ sel: '#nll-nome', n: 1 }, { sel: '#nlx-cart-nome', n: 2 }] });
  // 2.11.0: Crea una campagna (tipo e nome), poi il riepilogo a sezioni. Oggetto e testo qui; destinatari e il resto nel 19k.
  await vai('newsletter.php?crea=1');
  await p.fill('#nl-crea-nome', 'Aperti il sabato');
  await foto('19-crea-campagna', { sel: '#nl-crea', margine: 8, segni: [{ sel: '#nl-crea input[name=tipo]', n: 1 }, { sel: '#nl-crea-nome', n: 2 }, { sel: '#nl-crea button[type=submit]', n: 3 }] });
  await Promise.all([p.waitForNavigation(), p.click('#nl-crea button[type=submit]')]);
  await p.waitForLoadState('networkidle');
  await foto('19-riepilogo', { clip: { x: 240, y: 56, width: 960, height: 760 }, segni: [{ sel: '#nlr-manca', n: 1 }, { sel: '#sez-mittente > summary', n: 2 }, { sel: '#sez-destinatari .nlr-stato', n: 3, lato: 'destra' }] });
  const campagnaManuale = p.url().split('admin/')[1];
  await vai(campagnaManuale + '&apri=oggetto');
  await p.fill('#oggetto', 'Da settembre siamo aperti anche il sabato, {{ contact.NOME | default:"cliente" }}');
  await foto('19-oggetto', { sel: '#sez-oggetto', margine: 6, segni: [{ sel: '#oggetto', n: 1 }, { sel: '#anteprima', n: 2 }, { sel: '#sez-oggetto details.pn-aiuto > summary', n: 3 }, { sel: '#sez-oggetto button[type=submit]', n: 4 }] });
  await Promise.all([p.waitForNavigation(), p.click('#sez-oggetto button[type=submit]')]);
  await vai(campagnaManuale + '&apri=design');
  await p.locator('#editor .ql-editor').click();
  await p.keyboard.type('Ciao! Da settembre ci trovi in negozio anche il sabato mattina, dalle 9 alle 13. Ti aspettiamo.');
  await p.fill('[name=pulsante_testo]', 'Leggi la notizia');
  await p.fill('[name=pulsante_link]', B + '/aperti-il-sabato/');
  // 2.12.0: le cinque strade del design sono riquadri con il loro pulsante; l'editor semplice è quello in uso.
  await foto('19-design', { sel: '#sez-design', margine: 6, altezza: 1180, segni: [{ sel: '#sez-design .nd-strade', n: 1 }, { sel: '#editor', n: 2 }, { sel: '#pulsante_testo', n: 3 }] });
  await Promise.all([p.waitForNavigation(), p.click('#nl-form button[type=submit]')]);
  // 2.15.0: le foto delle automazioni sono nel capitolo 19p.
};

// 19i. Importare ed esportare i contatti, la lista dei test (2.9.0, come Brevo). Un file CSV vero, i quattro passi, il
// resoconto con una riga scartata, la finestra Esporta, la lista dei test e la prova di una campagna.
capitoli['19i'] = async () => {
  const f = path.join(require('os').tmpdir(), 'manuale-clienti-negozio.csv');
  fs.writeFileSync(f, ['EMAIL;NOME;COGNOME;Data di nascita;Città', 'marta.gialli@esempio.it;Marta;Gialli;12/05/1990;Bologna', 'luca.neri@esempio.it;Luca;Neri;03/11/1984;Modena',
    'sara.blu@esempio.it;Sara;Blu;;Bologna', 'luca.neri@esempio.it;Luca;Neri;03/11/1984;Modena', 'paolo.rosa@esempio;Paolo;Rosa;;Parma'].join('\n') + '\n');
  await vai('newsletter-importa.php');
  await p.setInputFiles('#nli-file', f);
  await foto('19-importa-1', { clip: { x: 240, y: 56, width: 960, height: 520 }, segni: [{ sel: '.nli-modo', n: 1 }, { sel: '#nli-file', n: 2 }, { sel: '#nli-carica button[type=submit]', n: 3 }] });
  await Promise.all([p.waitForNavigation(), p.click('#nli-carica button[type=submit]')]);
  const riga = (c) => p.locator(`table.nli-abbina tr:has(td:first-child b:text-is("${c}"))`);
  await riga('Data di nascita').locator('select[data-nli-col]').selectOption(await p.locator('select[data-nli-col] option[value="DATA_NASCITA"]').count() ? 'DATA_NASCITA' : '_NUOVO');
  await riga('Città').locator('select[data-nli-col]').selectOption('_NUOVO');
  await riga('Città').locator('input[name$="[nome]"]').fill('CITTA');
  await foto('19-importa-2', { sel: '#nli-abbina', margine: 8, segni: [{ sel: 'table.nli-abbina tbody tr:first-child select', n: 1 }, { sel: 'table.nli-abbina tbody tr:last-child select[data-nli-col]', n: 2 }, { sel: '#nli-abbina button[type=submit]', n: 3 }] });
  await Promise.all([p.waitForNavigation(), p.click('#nli-abbina button[type=submit]')]);
  if (!(await p.locator('#nli-liste').count())) throw new Error('19i: il passo 2 non va avanti: ' + await flash());
  await p.fill('#nli-nuova', 'Clienti del negozio');
  await foto('19-importa-3', { sel: '#nli-liste', margine: 8, segni: [{ sel: '#nli-liste fieldset.nlk-scelte', n: 1 }, { sel: '#nli-nuova', n: 2 }, { sel: '#nli-liste button[type=submit]', n: 3 }] });
  await Promise.all([p.waitForNavigation(), p.click('#nli-liste button[type=submit]')]);
  await p.check('input[name=consenso]');
  await foto('19-importa-4', { sel: '#nli-conferma', margine: 8, segni: [{ sel: '.nli-opzioni', n: 1 }, { sel: '.nli-consenso', n: 2 }, { sel: '#nli-conferma button[type=submit]', n: 3 }] });
  await Promise.all([p.waitForNavigation(), p.click('#nli-conferma button[type=submit]')]);
  await p.waitForSelector('#nli-resoconto', { timeout: 120000 });
  await foto('19-resoconto', { sel: '#nli-resoconto', margine: 8, segni: [{ sel: '#nli-resoconto dl', n: 1 }, { sel: 'a[href*="scarica=scarti"]', n: 2 }] });
  // Esporta: la lista appena importata, con il punto e virgola
  await p.setViewportSize({ width: 1200, height: 1400 }); // la finestra Esporta intera, senza scorrere
  await vai('newsletter-contatti.php');
  await p.click('button:has-text("Esporta i contatti")');
  await p.check('#nlx-esporta input[name=esp_origine][value=lista]');
  await p.selectOption('#nlx-esp-lista', { label: 'Clienti del negozio' });
  await dorme(200);
  await foto('19-esporta', { sel: '#nlx-esporta', margine: 0, segni: [{ sel: '#nlx-esporta fieldset:nth-of-type(1) legend', n: 1 }, { sel: '#nlx-esporta .nlx-esp-colonne legend', n: 2 }, { sel: '#nlx-esporta fieldset:nth-of-type(3) legend', n: 3 }, { sel: '#nlx-esporta-avvia', n: 4, lato: 'destra' }], dove: p });
  await p.click('#nlx-esporta button[data-chiudi-dialogo]');
  await p.setViewportSize({ width: 1200, height: 800 });
  // La lista dei test e la prova di una campagna
  await vai('newsletter.php?scheda=impostazioni');
  await p.fill('#nl-test-indirizzi', 'marta.gialli@esempio.it\nluca.neri@esempio.it');
  await foto('19-lista-test', { sel: '#nl-test', margine: 8, segni: [{ sel: '#nl-test-indirizzi', n: 1 }, { sel: '#nl-test button[type=submit]', n: 2 }] });
  await Promise.all([p.waitForNavigation(), p.click('#nl-test button[type=submit]')]);
  if (!/Lista dei test salvata/.test(await flash())) throw new Error('19i: lista dei test non salvata: ' + await flash());
  await vai('newsletter.php');
  const idCamp = await p.locator('a[href^="newsletter-campagna.php?id="]').first().getAttribute('href').catch(() => '');
  if (!idCamp) throw new Error('19i: manca la campagna del capitolo 19');
  await vai(idCamp + '&apri=prova'); // 2.11.0: la prova sta nella sezione Anteprima e prova del riepilogo
  await foto('19-prova', { sel: '#nlc-prova', margine: 8, segni: [{ sel: '#nlc-prova label:first-of-type', n: 1 }, { sel: '#nlc-prova-a', n: 2 }, { sel: '#nlc-prova button[value=prova]', n: 3 }] });
};

// 19k. La campagna a riepilogo, il programma, l'elenco, la pagina per uscire e il centro preferenze (2.11.0). Dopo il 19,
// il 19i e il 19j (la campagna "Aperti il sabato", i clienti importati, il segmento). SOLO_CAPITOLI=19,19i,19j,19k con
// TMPDIR vuota. Vuole CMS_DIR_MANUALE (i link firmati per uscire e per le preferenze si leggono da prove/campagne-cli.php).
capitoli['19k'] = async () => {
  await vai('newsletter.php');
  const idCamp = await p.locator('a[href^="newsletter-campagna.php?id="]').first().getAttribute('href');
  await vai(idCamp + '&apri=destinatari');
  await p.locator('#sez-destinatari label', { hasText: 'Clienti del negozio' }).locator('input[name="liste[]"]').check();
  await p.click('#sez-destinatari details.pn-aiuto > summary');
  await foto('19-destinatari', { sel: '#sez-destinatari', margine: 6, segni: [{ sel: '#sez-destinatari fieldset.nlk-scelte', n: 1 }, { sel: '#sez-destinatari .nlr-colonne fieldset:nth-of-type(2)', n: 2 }, { sel: '#sez-destinatari details.pn-aiuto > summary', n: 3 }, { sel: '#sez-destinatari input[name=non_coinvolti]', n: 4 }, { sel: '#sez-destinatari button[type=submit]', n: 5 }] });
  await Promise.all([p.waitForNavigation(), p.click('#sez-destinatari button[type=submit]')]);
  await vai(idCamp + '&apri=impostazioni');
  await p.fill('#nlr-campo-a', '{{ contact.NOME }} {{ contact.COGNOME }}');
  await foto('19-impostazioni', { sel: '#sez-impostazioni', margine: 6, segni: [{ sel: '#nlr-rispondi', n: 1 }, { sel: '#nlr-campo-a', n: 2 }, { sel: '#sez-impostazioni input[name=link_web]', n: 3 }, { sel: '#sez-impostazioni input[name=utm]', n: 4 }, { sel: '#sez-impostazioni input[name=lotti_attivi]', n: 5 }] });
  await Promise.all([p.waitForNavigation(), p.click('#sez-impostazioni button[type=submit]')]);
  await vai(idCamp + '&contatto=marta.gialli@esempio.it');
  await foto('19-anteprima', { sel: '#sez-prova', margine: 6, altezza: 560, segni: [{ sel: '#nlr-contatto', n: 1 }, { sel: '#sez-prova .nlr-schede', n: 2 }, { sel: '#nlr-anteprima iframe', n: 3 }] });
  await vai(idCamp);
  await foto('19-programma', { sel: '#nlr-invio', margine: 6, segni: [{ sel: '#nlr-invio input[value=subito]', n: 1 }, { sel: '#nlr-invio input[value=programma]', n: 2 }, { sel: '#nlr-quando', n: 3 }, { sel: '#nlr-invio input[value=migliore]', n: 4 }, { sel: '#nlr-invio button.btn-primary', n: 5 }] });
  // L'indirizzo dell'azienda e le pagine pubbliche
  await vai('newsletter.php?scheda=impostazioni#nl-indirizzo');
  await p.fill('#nl-indirizzo-testo', 'Studio Prova, via Emilia 10, 41121 Modena');
  await foto('19-indirizzo', { sel: '#nl-indirizzo', margine: 6, segni: [{ sel: '#nl-indirizzo-testo', n: 1 }, { sel: '#nl-indirizzo button[type=submit]', n: 2 }] });
  await Promise.all([p.waitForNavigation(), p.click('#nl-indirizzo button[type=submit]')]);
  await vai('newsletter.php?scheda=impostazioni#nl-pagine');
  await p.locator('#nl-pagine label', { hasText: 'Clienti del negozio' }).locator('input[name="pubbliche[]"]').check();
  await foto('19-pagine', { sel: '#nl-pagine', margine: 6, segni: [{ sel: '#pg-ut', n: 1 }, { sel: '#nl-pagine input[name="pg[questionario]"]', n: 2 }, { sel: '#nl-pagine fieldset:nth-of-type(2) legend', n: 3 }, { sel: '#nl-pagine fieldset:nth-of-type(3) legend', n: 4 }, { sel: '#nl-pagine button[type=submit]', n: 5 }] });
  await Promise.all([p.waitForNavigation(), p.click('#nl-pagine button[type=submit]')]);
  // Manda subito (verso il server di posta finto, POSTA_MANUALE) e l'elenco
  await postaFinta();
  await vai(idCamp);
  await Promise.all([p.waitForNavigation(), p.click('#nlr-invio button.btn-primary')]);
  await vai('newsletter.php');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=giro]) button')]);
  await vai('newsletter.php');
  await p.locator('details.nl-azioni > summary').first().click();
  await foto('19-elenco', { clip: { x: 240, y: 56, width: 960, height: 700 }, segni: [{ sel: 'details#nl-crea > summary', n: 1 }, { sel: 'nav.nl-viste', n: 2 }, { sel: 'details.nl-azioni[open] > summary', n: 3, lato: 'destra' }] });
  // La pagina per uscire e il centro preferenze, come li vede chi riceve
  const DIRM = process.env.CMS_DIR_MANUALE;
  if (!DIRM) throw new Error('19k: serve CMS_DIR_MANUALE');
  const cli = (...a) => JSON.parse(require('child_process').execFileSync('php', [path.join(__dirname, '..', 'prove', 'campagne-cli.php'), DIRM, ...a.map(String)], { encoding: 'utf8' }).trim().split('\n').pop());
  const idDi = (e) => +Object.values(cli('sql', `SELECT id FROM {p}newsletter_iscritti WHERE email = '${e}'`)[0])[0];
  const u = await visitatore();
  await u.goto(qui(cli('preferenze', idDi('luca.neri@esempio.it'))), { waitUntil: 'networkidle' });
  await u.screenshot({ path: path.join(GREZZE, '19-centro-preferenze.png'), clip: { x: 0, y: 70, width: 1200, height: 900 }, fullPage: true }); console.log('19-centro-preferenze');
  await u.goto(qui(cli('uscita', idDi('sara.blu@esempio.it'))), { waitUntil: 'networkidle' });
  await Promise.all([u.waitForNavigation(), u.click('button:has-text("toglimi")')]);
  await u.screenshot({ path: path.join(GREZZE, '19-uscita.png'), clip: { x: 0, y: 70, width: 1200, height: 860 }, fullPage: true }); console.log('19-uscita');
  await u.context().close();
};

// 19m. L'editor delle email a blocchi, la galleria, le sezioni salvate e i modelli (2.12.0). Una campagna sua, "Offerta
// d'autunno", dalla galleria; gira da sola (SOLO_CAPITOLI=19m) o dopo il 19k.
capitoli['19m'] = async () => {
  await vai('newsletter.php?crea=1');
  await p.fill('#nl-crea-nome', 'Offerta d\'autunno');
  await Promise.all([p.waitForNavigation(), p.click('#nl-crea button[type=submit]')]);
  const idC = new URL(p.url()).searchParams.get('id');
  await vai(`newsletter-campagna.php?id=${idC}&apri=design`);
  await foto('19m-strade', { sel: '#sez-design .nd-strade', margine: 10, segni: [{ sel: '#sez-design .nd-strada:nth-child(1) .btn', n: 1 }, { sel: '#sez-design .nd-strada:nth-child(2) b', n: 2 }, { sel: '#sez-design .nd-strada:nth-child(3) .btn', n: 3 }, { sel: '#sez-design .nd-strada:nth-child(4) b', n: 4 }, { sel: '#sez-design .nd-strada:nth-child(5) b', n: 5 }] });
  await vai(`newsletter-modelli.php?scheda=galleria&campagna=${idC}`);
  await foto('19m-galleria', { clip: { x: 240, y: 56, width: 960, height: 760 }, segni: [{ sel: 'nav.imp-schede a.attiva', n: 1 }, { sel: '.ndm-carta:nth-child(3) .btn-primary', n: 2 }, { sel: '.ndm-carta:nth-child(3) .btn-secondary', n: 3 }] });
  await Promise.all([p.waitForNavigation(), p.locator('.ndm-carta:has(b:text-is("Offerta")) button:has-text("Usa in questa campagna")').click()]);
  await p.waitForSelector('.nd-b'); await dorme(500);
  await foto('19m-editor', { clip: { x: 0, y: 0, width: 1200, height: 800 }, segni: [{ sel: '.ed-schede-pannello', n: 1 }, { sel: '#nd-tavolozza-blocchi', n: 2 }, { sel: '#nd-tavolozza-sezioni', n: 3 }, { sel: '#nd-tela', n: 4 }, { sel: '.nd-misure', n: 5 }, { sel: '.ed-storia', n: 6 }, { sel: '#nd-anteprima', n: 7 }, { sel: '#nd-salva', n: 8, lato: 'destra' }] });
  // Il titolo grande dell'offerta: si sceglie nella tela e si cambia nel pannello.
  const tit = p.locator('.nd-b:has(h1)').first();
  await tit.click({ position: { x: 8, y: 8 } }); await dorme(300);
  const marca = (et, nome) => p.evaluate(([et, nome]) => { const c = [...document.querySelectorAll("#nd-controlli .ed-controllo")].find(x => ((x.querySelector("label, .ed-etichetta") || {}).textContent || "") === et); if (c) c.setAttribute("data-mu", nome); }, [et, nome]);
  await marca("Testo", "testo");
  await p.locator('#nd-controlli .ed-controllo:has(label:text-is("Testo")) input').fill('-30% su tutti i cappotti');
  await dorme(300);
  await foto('19m-modifica', { clip: { x: 0, y: 0, width: 1200, height: 640 }, segni: [{ sel: '.nd-b.nd-sel > .nd-strumenti', n: 1 }, { sel: '#nd-modifica-nome', n: 2 }, { sel: '[data-mu=testo] input', n: 3 }, { sel: '#nd-controlli .nd-campioni', n: 4 }] });
  const btn = p.locator('.nd-b:has(.nd-pulsante)').first();
  await btn.click({ position: { x: 8, y: 8 } }); await dorme(300); await marca('Link', 'link');
  await p.locator('#nd-controlli .ed-controllo:has(label:text-is("Link")) input[type=text]').first().fill(B + '/negozio/');
  await foto('19m-pulsante', { clip: { x: 0, y: 50, width: 360, height: 560 }, segni: [{ sel: '[data-mu=link] input[type=text]', n: 1 }, { sel: '#nd-controlli .pn-link-scegli', n: 2 }, { sel: '#nd-controlli .nd-speciali', n: 3 }] });
  // Una sezione nuova con due colonne, trascinata dalla tavolozza... qui con un clic (entra dopo la sezione selezionata).
  await p.click('#nd-scheda-contenuti');
  await p.click('#nd-tavolozza-sezioni [data-layout="50-50"]'); await dorme(300);
  await p.click('#nd-scheda-stile'); await dorme(200);
  await foto('19m-stile', { clip: { x: 0, y: 50, width: 360, height: 700 }, segni: [{ sel: '#nd-controlli-stile details:nth-of-type(1) > summary', n: 1 }, { sel: '#nd-controlli-stile details:nth-of-type(2) > summary', n: 2 }, { sel: '#nd-controlli-stile details:nth-of-type(3) > summary', n: 3 }] });
  await p.click('[data-nd-misura=tel]'); await dorme(500);
  await foto('19m-telefono', { clip: { x: 340, y: 50, width: 860, height: 750 }, segni: [{ sel: '[data-nd-misura=tel]', n: 1 }] });
  await p.click('[data-nd-misura=pc]'); await dorme(400);
  // La testata salvata e sincronizzata: la prima sezione.
  const s1 = p.locator('.nd-s').first();
  await s1.hover({ position: { x: 6, y: 6 } });
  await s1.locator(':scope > .nd-strumenti [data-nd-azione=salva]').click();
  await p.fill('#nd-nome-campo', 'Testata delle offerte');
  await foto('19m-salva-sezione', { sel: '#nd-dlg-nome', margine: 0, segni: [{ sel: '#nd-nome-campo', n: 1 }, { sel: '#nd-nome-spunta', n: 2 }, { sel: '#nd-nome-ok', n: 3 }] });
  await p.click('#nd-nome-ok');
  await p.waitForFunction(() => /Sezione salvata/.test(document.getElementById('nd-stato').textContent));
  await s1.click({ position: { x: 6, y: 30 } }); await dorme(300);
  await p.click('#nd-scheda-salvati'); await dorme(200);
  await foto('19m-salvati', { clip: { x: 0, y: 50, width: 1200, height: 520 }, segni: [{ sel: '#nd-elenco-salvati .nd-salvato', n: 1 }, { sel: '.nd-s.nd-sel > .nd-strumenti', n: 2 }] });
  await p.click('#nd-scheda-modifica'); await dorme(200);
  await foto('19m-sincronizzata', { clip: { x: 0, y: 50, width: 360, height: 330 }, segni: [{ sel: '#nd-controlli .nd-salvata .ed-b-primo', n: 1 }, { sel: '#nd-controlli .nd-salvata .ed-b:not(.ed-b-primo)', n: 2 }] });
  await p.click('#nd-come-modello');
  await p.fill('#nd-nome-campo', 'Offerta stagionale');
  await foto('19m-modello', { sel: '#nd-dlg-nome', margine: 0, segni: [{ sel: '#nd-nome-campo', n: 1 }, { sel: '#nd-nome-spunta', n: 2 }, { sel: '#nd-nome-ok', n: 3 }] });
  await p.click('#nd-nome-ok');
  await p.waitForFunction(() => /Modello salvato/.test(document.getElementById('nd-stato').textContent));
  await p.click('#nd-anteprima'); await dorme(1500);
  await foto('19m-anteprima', { sel: '#nd-dlg-anteprima', margine: 0, segni: [{ sel: '.nd-misure-anteprima', n: 1 }] });
  await p.click('#nd-dlg-anteprima [data-chiudi-dialogo]');
  await vai('newsletter-modelli.php');
  await foto('19m-modelli', { clip: { x: 240, y: 56, width: 960, height: 760 }, segni: [{ sel: '#ndm-nuovo', n: 1 }, { sel: '.ndm-tabella tbody tr:first-child .ndm-stato', n: 2 }, { sel: '.ndm-tabella tbody tr:first-child .ndm-azioni', n: 3 }, { sel: '.ndm-tabella tbody tr:first-child .ndm-usato', n: 4 }, { sel: '#ndm-doi', n: 5 }] });
};

// 19n. Il test A/B (2.13.0): la campagna "Novità di novembre" con due oggetti, mandata a 30 lettori finti (lista "Lettori
// del blog"); le aperture si segnano dal database e il tempo si sposta con prove/campagne-cli.php (ab_indietro), poi il
// giro del cron sceglie la vincente. Da solo: SOLO_CAPITOLI=19n, con CMS_DIR_MANUALE e POSTA_MANUALE.
capitoli['19n'] = async () => {
  const DIRM = process.env.CMS_DIR_MANUALE;
  if (!DIRM) throw new Error('19n: serve CMS_DIR_MANUALE');
  const cli = (...a) => JSON.parse(require('child_process').execFileSync('php', [path.join(__dirname, '..', 'prove', 'campagne-cli.php'), DIRM, ...a.map(String)], { encoding: 'utf8' }).trim().split('\n').pop());
  cli('sql', "INSERT IGNORE INTO {p}newsletter_liste (nome, descrizione, creato) VALUES ('Lettori del blog', '', NOW())");
  const lista = +Object.values(cli('sql', "SELECT id FROM {p}newsletter_liste WHERE nome = 'Lettori del blog'")[0])[0];
  const nomi = ['Anna', 'Bruno', 'Carla', 'Davide', 'Elisa', 'Franco', 'Giada', 'Ivo', 'Laura', 'Marco'];
  for (let k = 1; k <= 30; k++) {
    const e = `lettore${k}@esempio.it`;
    cli('sql', `INSERT IGNORE INTO {p}newsletter_iscritti (email, nome, stato, fonte, creato, aggiornato) VALUES ('${e}', '${nomi[k % 10]}', 'attivo', 'manuale', NOW(), NOW())`);
    cli('sql', `INSERT IGNORE INTO {p}newsletter_iscritti_liste (iscritto_id, lista_id, aggiunto) SELECT id, ${lista}, NOW() FROM {p}newsletter_iscritti WHERE email = '${e}'`);
  }
  await postaFinta();
  await vai('newsletter.php?crea=1');
  await p.check('#nl-crea input[name=tipo][value=ab]');
  await p.fill('#nl-crea-nome', 'Novità di novembre');
  await foto('19n-crea', { sel: '#nl-crea', margine: 6, segni: [{ sel: '#nl-crea input[name=tipo][value=ab]', n: 1 }, { sel: '#nl-crea-nome', n: 2 }, { sel: '#nl-crea button[type=submit]', n: 3 }] });
  await Promise.all([p.waitForNavigation(), p.click('#nl-crea button[type=submit]')]);
  const idC = new URL(p.url()).searchParams.get('id');
  const qui2 = (x) => `newsletter-campagna.php?id=${idC}${x}`;
  await vai(qui2('&apri=destinatari'));
  await p.locator('#sez-destinatari label', { hasText: 'Lettori del blog' }).locator('input[name="liste[]"]').check();
  await Promise.all([p.waitForNavigation(), p.click('#sez-destinatari button[type=submit]')]);
  await vai(qui2('&apri=oggetto'));
  await p.fill('#oggetto', 'Le novità di novembre');
  await Promise.all([p.waitForNavigation(), p.click('#sez-oggetto button[type=submit]')]);
  await vai(qui2('&apri=design'));
  await p.locator('#editor .ql-editor').click(); await p.keyboard.type('Ciao {{ contact.NOME }}, ecco cosa c\'è di nuovo in negozio questo mese.');
  await Promise.all([p.waitForNavigation(), p.click('#nl-form button[type=submit]')]);
  await vai(qui2('&apri=ab'));
  await p.fill('#nlr-oggetto-b', 'Novembre: tre idee per te, {{ contact.NOME }}');
  await p.evaluate(() => { const r = document.getElementById('nlr-ab-perc'); r.value = 20; r.dispatchEvent(new Event('input', { bubbles: true })); });
  await foto('19n-sezione', { sel: '#sez-ab', margine: 6, segni: [{ sel: '#sez-ab fieldset.nlk-scelte', n: 1 }, { sel: '#nlr-oggetto-b', n: 2 }, { sel: '#nlr-ab-perc', n: 3 }, { sel: '#sez-ab fieldset.nlk-scelte:nth-of-type(2)', n: 4 }, { sel: '#nlr-ab-durata', n: 5 }, { sel: '#sez-ab button[type=submit]', n: 6 }] });
  await Promise.all([p.waitForNavigation(), p.click('#sez-ab button[type=submit]')]);
  await vai(qui2(''));
  await Promise.all([p.waitForNavigation(), p.click('#nlr-invio button.btn-primary')]);
  await vai('newsletter.php');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=giro]) button')]);
  // Aperture: 2 della A, 4 della B; poi le quattro ore passano
  const apri = (v, quanti) => cli('sql', `INSERT INTO {p}newsletter_eventi (campagna_id, iscritto_id, tipo, url, creato) SELECT ${idC}, iscritto_id, 'apertura', '', NOW() FROM {p}newsletter_coda WHERE campagna_id = ${idC} AND versione = '${v}' AND stato = 'inviata' ORDER BY id LIMIT ${quanti}`);
  apri('a', 2); apri('b', 4);
  await vai(qui2(''));
  await foto('19n-in-corso', { sel: '.nlr-stato-box', margine: 6, segni: [] });
  cli('ab_indietro', idC, 4 * 3600 + 60);
  await vai('newsletter.php');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=giro]) button')]);
  await vai(qui2(''));
  await foto('19n-risultati', { sel: '#nlr-ab-risultati', margine: 6, segni: [{ sel: '#nlr-ab-risultati .nlr-ab-esito', n: 1 }, { sel: '#nlr-ab-risultati tr[data-versione=b] .nlr-stato', n: 2 }, { sel: '#nlr-ab-risultati tr[data-versione=r]', n: 3 }] });
  // Il test sul contenuto: le schede delle due versioni nella sezione Design
  await vai('newsletter.php?crea=1');
  await p.check('#nl-crea input[name=tipo][value=ab]');
  await p.fill('#nl-crea-nome', 'Due vetrine a confronto');
  await Promise.all([p.waitForNavigation(), p.click('#nl-crea button[type=submit]')]);
  const idD = new URL(p.url()).searchParams.get('id');
  await vai(`newsletter-campagna.php?id=${idD}&apri=ab`);
  await p.check('#sez-ab input[name=cosa][value=design]');
  await Promise.all([p.waitForNavigation(), p.click('#sez-ab button[type=submit]')]);
  await vai(`newsletter-campagna.php?id=${idD}&versione=b&apri=design`);
  await foto('19n-versioni', { sel: '#sez-design', margine: 6, altezza: 420, segni: [{ sel: '#sez-design .nlr-versioni', n: 1 }, { sel: '#sez-design .nd-strade', n: 2 }] });
};

// 19p. Le automazioni (2.15.0). Gira da solo: SOLO_CAPITOLI=19p, con CMS_DIR_MANUALE e POSTA_MANUALE. Il modello Benvenuto
// usato come lo userebbe una persona; dodici iscritti entrano, le email partono davvero, alcune si aprono (come dal pixel).
capitoli['19p'] = async () => {
  const DIRM = process.env.CMS_DIR_MANUALE;
  if (!DIRM) throw new Error('19p: serve CMS_DIR_MANUALE');
  const cli = (...a) => JSON.parse(require('child_process').execFileSync('php', [path.join(__dirname, '..', 'prove', 'automazioni-cli.php'), DIRM, ...a.map(String)], { encoding: 'utf8' }).trim().split('\n').pop());
  await postaFinta();
  cli('smtp', POSTA_PORTA);
  await vai('newsletter-flussi.php');
  await foto('19p-crea', { sel: '#fl-crea', margine: 6, segni: [{ sel: '#fl-crea .fl-modello:first-child a', n: 1 }, { sel: '[data-modello=benvenuto] a', n: 2 }] });
  await Promise.all([p.waitForNavigation(), p.click('[data-modello=benvenuto] a')]);
  await p.fill('#fl-nome', 'Benvenuto ai nuovi clienti');
  await p.fill('#fl-tag', 'benvenuto');
  await p.selectOption('#fl-stato', 'attiva');
  await Promise.all([p.waitForNavigation(), p.click('#fl-form button[value=salva]')]);
  const idF = new URL(p.url()).searchParams.get('id');
  const nomi = ['Anna', 'Bruno', 'Carla', 'Davide', 'Elisa', 'Franco', 'Giada', 'Ivo', 'Laura', 'Marco', 'Nora', 'Paolo'];
  const ids = nomi.map((n, k) => cli('contatto', `nuovo-cliente${k + 1}@esempio.it`, n, 0));
  for (const i of ids) cli('innesco', 'iscrizione', 0, i);
  cli('giro');
  for (const i of ids.slice(0, 7)) cli('evento', 'apertura', idF, 1, i);
  for (const i of ids.slice(0, 3)) cli('evento', 'clic', idF, 1, i);
  await vai('newsletter-flussi.php?id=' + idF);
  await p.locator('.fl-nodo[data-id="1"] .fl-nodo-corpo').click();
  await foto('19p-tela', { clip: { x: 240, y: 56, width: 1200, height: 760 }, segni: [{ sel: '#fl-nome', n: 1 }, { sel: '#fl-stato', n: 2 }, { sel: '.fl-schede', n: 3 }, { sel: '.fl-nodo[data-id="1"] .fl-nodo-numeri', n: 4 }, { sel: '.fl-nodo[data-id="2"] .fl-nodo-numeri', n: 5 }, { sel: '#fl-form button[value=salva]', n: 6 }] });
  await vai(`newsletter-flussi.php?id=${idF}&scheda=attivita`);
  await foto('19p-attivita', { sel: '#fl-dentro', margine: 6, altezza: 420, segni: [{ sel: '#fl-passo', n: 1 }, { sel: '#fl-dentro tbody tr:first-child button', n: 2 }] });
  // 2.18.0: l'email del passo richiama un modello ("Ebook 05"): Da un modello, Quale modello, oggetto vuoto (vale quello del
  // modello), Apri il modello, Stacca dal modello, e sulla tela "Modello: Ebook 05". Poi Modelli con "Usato in" (rifà 19m-modelli).
  const idM = cli('modello_crea', 'Ebook 05', 'blocchi', 'Il quinto capitolo: come scegliere il colore giusto', 'Ebook, capitolo 5: il colore giusto', 'Cinque minuti di lettura');
  await vai('newsletter-flussi.php?id=' + idF);
  await p.locator('.fl-nodo[data-id="1"] .fl-nodo-corpo').click();
  await p.check('[name=fp-editor][value=modello]');
  await p.selectOption('#fp-modello', String(idM));
  await p.fill('#fp-oggetto', '');
  // Il pannello del passo scorre dentro di sé: per la foto si apre tutto, così si vedono anche Apri il modello e Stacca.
  await p.evaluate(() => { const pn = document.getElementById('fl-pannello'); pn.style.maxHeight = 'none'; pn.style.overflow = 'visible'; pn.style.height = 'auto'; });
  await dorme(300);
  const fondoP = await p.evaluate(() => { const r = document.getElementById('fp-stacca-aiuto').getBoundingClientRect(); return Math.ceil(r.bottom + scrollY + 16); });
  await foto('19p-modello', { clip: { x: 520, y: 290, width: 920, height: Math.max(500, fondoP - 290) }, segni: [{ sel: '#fp-oggetto', n: 1 }, { sel: '[name=fp-editor][value=modello]', n: 2 }, { sel: '#fp-modello', n: 3 }, { sel: '#fp-apri-modello', n: 4 }, { sel: '#fp-stacca', n: 5 }, { sel: '.fl-nodo[data-id="1"] .fl-nodo-corpo', n: 6 }] });
  await Promise.all([p.waitForNavigation(), p.click('#fl-form button[value=salva]')]);
  await vai('newsletter-modelli.php');
  await foto('19m-modelli', { clip: { x: 240, y: 56, width: 960, height: 760 }, segni: [{ sel: '#ndm-nuovo', n: 1 }, { sel: '.ndm-tabella tbody tr:first-child .ndm-stato', n: 2 }, { sel: '.ndm-tabella tbody tr:first-child .ndm-azioni', n: 3 }, { sel: '.ndm-tabella tbody tr:first-child .ndm-usato', n: 4 }, { sel: '#ndm-doi', n: 5 }] });
};

// 19o. Il report della campagna, le statistiche e l'orario migliore (2.14.0). Gira da solo: SOLO_CAPITOLI=19o, con
// CMS_DIR_MANUALE e POSTA_MANUALE. La campagna "Le offerte di dicembre" parte davvero a 40 lettori; aperture, clic e
// un'uscita si scrivono come li scriverebbero i link delle email (a ore diverse, per l'orario migliore).
capitoli['19o'] = async () => {
  const DIRM = process.env.CMS_DIR_MANUALE;
  if (!DIRM) throw new Error('19o: serve CMS_DIR_MANUALE');
  const cli = (...a) => JSON.parse(require('child_process').execFileSync('php', [path.join(__dirname, '..', 'prove', 'campagne-cli.php'), DIRM, ...a.map(String)], { encoding: 'utf8' }).trim().split('\n').pop());
  const sql = (s) => cli('sql', s);
  sql("INSERT IGNORE INTO {p}newsletter_liste (nome, descrizione, creato) VALUES ('Clienti di dicembre', '', NOW())");
  const lista = +Object.values(sql("SELECT id FROM {p}newsletter_liste WHERE nome = 'Clienti di dicembre'")[0])[0];
  const nomi = ['Anna', 'Bruno', 'Carla', 'Davide', 'Elisa', 'Franco', 'Giada', 'Ivo', 'Laura', 'Marco'];
  for (let k = 1; k <= 40; k++) {
    const e = `cliente-dic${k}@esempio.it`;
    sql(`INSERT IGNORE INTO {p}newsletter_iscritti (email, nome, stato, fonte, creato, aggiornato) VALUES ('${e}', '${nomi[k % 10]}', 'attivo', 'manuale', DATE_SUB(NOW(), INTERVAL ${k % 20} DAY), NOW())`);
    sql(`INSERT IGNORE INTO {p}newsletter_iscritti_liste (iscritto_id, lista_id, aggiunto) SELECT id, ${lista}, DATE_SUB(NOW(), INTERVAL ${k % 20} DAY) FROM {p}newsletter_iscritti WHERE email = '${e}'`);
  }
  await postaFinta();
  await vai('newsletter.php?crea=1');
  await p.fill('#nl-crea-nome', 'Le offerte di dicembre');
  await Promise.all([p.waitForNavigation(), p.click('#nl-crea button[type=submit]')]);
  const idC = new URL(p.url()).searchParams.get('id');
  const qui3 = (x) => `newsletter-campagna.php?id=${idC}${x}`;
  await vai(qui3('&apri=destinatari'));
  await p.locator('#sez-destinatari label', { hasText: 'Clienti di dicembre' }).locator('input[name="liste[]"]').check();
  await Promise.all([p.waitForNavigation(), p.click('#sez-destinatari button[type=submit]')]);
  await vai(qui3('&apri=oggetto'));
  await p.fill('#oggetto', 'Le offerte di dicembre, solo per te');
  await Promise.all([p.waitForNavigation(), p.click('#sez-oggetto button[type=submit]')]);
  await vai(qui3('&apri=design'));
  await p.locator('#editor .ql-editor').click(); await p.keyboard.type('Ciao {{ contact.NOME }}, a dicembre in negozio trovi le offerte per i regali.');
  await p.fill('#pulsante_testo', 'Guarda le offerte'); await p.fill('#nl-pulsante-link', 'https://www.studioprova.it/offerte/');
  await Promise.all([p.waitForNavigation(), p.click('#nl-form button[type=submit]')]);
  await vai(qui3(''));
  await Promise.all([p.waitForNavigation(), p.click('#nlr-invio button.btn-primary')]);
  await vai('newsletter.php');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=giro]) button')]);
  // 24 aperture (alcune ripetute), 9 clic, un'uscita con il motivo: a ore diverse negli ultimi giorni.
  sql(`INSERT INTO {p}newsletter_eventi (campagna_id, iscritto_id, tipo, url, creato, volte) SELECT ${idC}, q.iscritto_id, 'apertura', '', DATE_SUB(NOW(), INTERVAL (q.iscritto_id % 5) HOUR), 1 + q.iscritto_id % 3 FROM {p}newsletter_coda q WHERE q.campagna_id = ${idC} AND q.stato = 'inviata' ORDER BY q.id LIMIT 24`);
  sql(`INSERT INTO {p}newsletter_eventi (campagna_id, iscritto_id, tipo, url, creato, volte) SELECT ${idC}, q.iscritto_id, 'clic', 'https://www.studioprova.it/offerte/', NOW(), 1 + q.iscritto_id % 2 FROM {p}newsletter_coda q WHERE q.campagna_id = ${idC} AND q.stato = 'inviata' ORDER BY q.id LIMIT 9`);
  const usc = +Object.values(sql(`SELECT iscritto_id FROM {p}newsletter_coda WHERE campagna_id = ${idC} AND stato = 'inviata' ORDER BY id DESC LIMIT 1`)[0])[0];
  sql(`INSERT INTO {p}newsletter_eventi (campagna_id, iscritto_id, tipo, url, creato) VALUES (${idC}, ${usc}, 'uscita', '', NOW())`);
  sql(`INSERT INTO {p}newsletter_uscite_motivi (contatto_id, campagna_id, motivo, creato) VALUES (${usc}, ${idC}, 'troppe', NOW())`);
  sql(`UPDATE {p}newsletter_iscritti SET stato = 'uscito', blocco_motivo = 'unsubscribedViaEmail', blocco_at = NOW(), uscito_at = NOW() WHERE id = ${usc}`);
  // Il report
  await vai(`newsletter-report.php?id=${idC}`);
  await fotoFino('19o-report', '#nlr-panoramica', { max: 1000, segni: [{ sel: 'nav.imp-schede', n: 1 }, { sel: '#nlr-panoramica [data-numero=consegnate]', n: 2 }, { sel: '#nlr-panoramica [data-numero=apertura]', n: 3 }, { sel: '#nlr-panoramica [data-numero=clic_su_aperture]', n: 4 }, { sel: '#nlr-soglie', n: 5 }] }); // dalla testata alle tessere
  await vai(`newsletter-report.php?id=${idC}&scheda=clic`);
  await foto('19o-mappa', { sel: '#nlr-mappa', margine: 6, altezza: 330, segni: [{ sel: '#nlr-mappa iframe', n: 1 }] });
  await vai(`newsletter-report.php?id=${idC}&scheda=aperture`);
  await p.fill('#nlr-salva-nome', 'Hanno aperto a dicembre');
  await foto('19o-elenco', { sel: '#nlr-elenco', margine: 6, altezza: 360, segni: [{ sel: '#nlr-elenco .nlr-elenchi', n: 1 }, { sel: '.nlr-elenco-tabella tbody tr:first-child a', n: 2 }] });
  // Le statistiche
  await vai('newsletter-statistiche.php');
  await fotoFino('19o-statistiche', '#st-andamento', { max: 1150, segni: [{ sel: '#st-periodo', n: 1 }, { sel: '#st-totali .nlr-numeri', n: 2 }, { sel: '#st-andamento svg', n: 3 }] });
  await foto('23-consegna-statistiche', { sel: '#st-tabella', margine: 6, segni: [{ sel: '#st-tabella td.st-rimbalzi', n: '' }] });
  await vai(`newsletter-statistiche.php?scheda=liste&periodo=30&lista=${lista}`);
  await foto('19o-liste', { sel: '#st-lista', margine: 6, altezza: 420, segni: [{ sel: '#st-lista .nlr-numeri', n: 1 }, { sel: '#st-lista svg', n: 2 }] });
  // L'orario migliore: aperture passate di dodici clienti, alle 8 o alle 18; poi una campagna nuova, programmata così.
  sql(`INSERT INTO {p}newsletter_eventi (campagna_id, flusso_id, passo, iscritto_id, tipo, url, creato) SELECT 0, 999999, 1, i.id, 'apertura', '', CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), IF(i.id % 2, ' 08:10:00', ' 18:20:00')) FROM {p}newsletter_iscritti i WHERE i.email LIKE 'cliente-dic%' ORDER BY i.id LIMIT 12`);
  sql(`INSERT INTO {p}newsletter_eventi (campagna_id, flusso_id, passo, iscritto_id, tipo, url, creato) SELECT 0, 999999, 2, i.id, 'apertura', '', CONCAT(DATE_SUB(CURDATE(), INTERVAL 9 DAY), IF(i.id % 2, ' 08:40:00', ' 18:05:00')) FROM {p}newsletter_iscritti i WHERE i.email LIKE 'cliente-dic%' ORDER BY i.id LIMIT 12`);
  await vai('newsletter.php?crea=1');
  await p.fill('#nl-crea-nome', 'Il catalogo di gennaio');
  await Promise.all([p.waitForNavigation(), p.click('#nl-crea button[type=submit]')]);
  const idG = new URL(p.url()).searchParams.get('id');
  await vai(`newsletter-campagna.php?id=${idG}&apri=destinatari`);
  await p.locator('#sez-destinatari label', { hasText: 'Clienti di dicembre' }).locator('input[name="liste[]"]').check();
  await Promise.all([p.waitForNavigation(), p.click('#sez-destinatari button[type=submit]')]);
  await vai(`newsletter-campagna.php?id=${idG}&apri=oggetto`);
  await p.fill('#oggetto', 'Il catalogo di gennaio');
  await Promise.all([p.waitForNavigation(), p.click('#sez-oggetto button[type=submit]')]);
  await vai(`newsletter-campagna.php?id=${idG}&apri=design`);
  await p.locator('#editor .ql-editor').click(); await p.keyboard.type('Ciao {{ contact.NOME }}, ecco il catalogo di gennaio.');
  await Promise.all([p.waitForNavigation(), p.click('#nl-form button[type=submit]')]);
  await vai(`newsletter-campagna.php?id=${idG}`);
  await foto('19-programma', { sel: '#nlr-invio', margine: 6, segni: [{ sel: '#nlr-invio input[value=subito]', n: 1 }, { sel: '#nlr-invio input[value=programma]', n: 2 }, { sel: '#nlr-quando', n: 3 }, { sel: '#nlr-invio input[value=migliore]', n: 4 }, { sel: '#nlr-invio button.btn-primary', n: 5 }] });
  await p.check('#nlr-invio input[value=migliore]');
  await p.selectOption('#nlr-om-ora', '10');
  await Promise.all([p.waitForNavigation(), p.click('#nlr-invio button.btn-primary')]);
  await p.click('#nlr-invio details.pn-aiuto > summary');
  await foto('19o-orario', { sel: '#nlr-invio', margine: 6, segni: [{ sel: '#nlr-om-giorno', n: 1 }, { sel: '#nlr-om-ora', n: 2 }, { sel: '#nlr-om-conti', n: 3 }, { sel: '#nlr-invio details.pn-aiuto > summary', n: 4 }] });
};

// 19j. I segmenti, Aggiungi filtro e le viste (2.10.0). Dopo il 19i: usa i contatti importati ("Clienti del negozio",
// attributo CITTA). SOLO_CAPITOLI=19i,19j, con TMPDIR vuota.
capitoli['19j'] = async () => {
  const numero = async () => { for (let i = 0; i < 60; i++) { const t = await p.locator('[data-nls-numero]').first().innerText(); if (/rispett/.test(t)) return t; await dorme(150); } throw new Error('19j: il conteggio non arriva'); };
  await vai('newsletter-segmenti.php?nuovo=1');
  await foto('19-segmenti-modelli', { clip: { x: 240, y: 56, width: 960, height: 560 }, segni: [{ sel: 'a.nls-zero', n: 1 }, { sel: 'a.nls-modello[href*="non_aprono_90"]', n: 2 }] });
  await Promise.all([p.waitForNavigation(), p.click('a.nls-zero')]);
  await p.click('button[data-aggiungi="0"]');
  await p.locator('[data-k=campo]').last().selectOption('attr:CITTA');
  await p.locator('[data-k=op]').last().selectOption('uguale');
  await p.locator('[data-k=valore]').last().fill('Bologna');
  await p.click('button[data-aggiungi="0"]');
  await p.locator('[data-k=campo]').last().selectOption('lista');
  await p.locator('.nls-caselle label', { hasText: 'Clienti del negozio' }).locator('input').last().check();
  await numero();
  await p.fill('#nls-nome', 'Clienti di Bologna'); await p.fill('#nls-cart-nuova', 'Negozio');
  await p.setViewportSize({ width: 1200, height: 1300 });
  await foto('19-segmento', { sel: '#nls-form', margine: 0, segni: [{ sel: '.nls-cond [data-k=campo]', n: 1 }, { sel: 'button[data-aggiungi="0"]', n: 2 }, { sel: '[data-nls-numero]', n: 3 }, { sel: '#nls-nome', n: 4 }, { sel: '#nls-form button[type=submit]', n: 5 }] });
  await p.setViewportSize({ width: 1200, height: 800 });
  await Promise.all([p.waitForNavigation(), p.click('#nls-form button[type=submit]')]);
  if (!/creato/.test(await flash())) throw new Error('19j: segmento non creato: ' + await flash());
  const sid = await p.evaluate(() => { const a = [...document.querySelectorAll('a[href^="newsletter-segmenti.php?id="]')].find(x => x.textContent.trim() === 'Clienti di Bologna'); return a ? a.href.match(/id=(\d+)/)[1] : ''; });
  await foto('19-segmenti', { clip: { x: 240, y: 56, width: 960, height: 700 }, segni: [{ sel: `a[href="newsletter-segmenti.php?id=${sid}"]`, n: 1 }, { sel: `a[aria-label="Vedi i contatti del segmento Clienti di Bologna"]`, n: 2 }, { sel: `button[data-apri-dialogo="#nls-lista-${sid}"]`, n: 3, lato: 'destra' }] });
  // Aggiungi filtro nella pagina Contatti, poi Salva come vista
  await vai('newsletter-contatti.php');
  await p.click('#nlx-filtro > summary');
  await p.click('#nlx-filtro button[data-aggiungi="0"]');
  await p.locator('#nlx-filtro [data-k=campo]').last().selectOption('attr:CITTA');
  await p.locator('#nlx-filtro [data-k=op]').last().selectOption('uguale');
  await p.locator('#nlx-filtro [data-k=valore]').last().fill('Modena');
  await numero();
  await foto('19-filtro', { sel: '#nlx-filtro', margine: 8, segni: [{ sel: '#nlx-filtro > summary', n: 1 }, { sel: '#nlx-filtro .nls-cond', n: 2 }, { sel: '#nlx-filtro button[type=submit]', n: 3 }, { sel: '#nlx-filtro button[data-apri-dialogo="#nls-salva"]', n: 4, lato: 'destra' }] });
  await Promise.all([p.waitForNavigation(), p.click('#nlx-filtro button[type=submit]')]);
  await p.click('button[data-apri-dialogo="#nlv-salva"]');
  await p.fill('#nlv-nome', 'Clienti di Modena');
  await Promise.all([p.waitForNavigation(), p.click('#nlv-salva button:has-text("Salva la vista")')]);
  if (!/Vista salvata/.test(await flash())) throw new Error('19j: vista non salvata: ' + await flash());
  await foto('19-viste', { clip: { x: 240, y: 56, width: 960, height: 330 }, segni: [{ sel: '.nlv-viste a.attiva', n: 1 }, { sel: '.nlv-viste a:first-child', n: 2 }, { sel: 'button[data-apri-dialogo="#nlv-salva"]', n: 3, lato: 'destra' }] });
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
  // 2.6.0: i giorni dei link riservati, in un riquadro loro.
  await foto('22-giorni', { sel: '#dl-riservati', segni: [{ sel: '#dl-giorni-n', n: 1 }, { sel: '#dl-giorni button', n: 2, lato: 'destra' }] });
};

// 21. SEO e AEO
capitoli['21'] = async () => {
  await vai('seo.php?scheda=panoramica');
  await foto('21-panoramica', { clip: { x: 240, y: 56, width: 960, height: 700 } });
  await vai('seo.php?scheda=redirect');
  await foto('21-redirect', { clip: { x: 240, y: 56, width: 960, height: 560 } });
  // 2.6.0: il service account, con i passi in un riquadro che si apre (aperto finché il collegamento non c'è).
  await vai('seo.php?scheda=motori');
  await foto('23-service-account', { sel: '.seo-card:has(.seo-sa-passi)', segni: [{ sel: '.seo-sa-passi > summary', n: 1 }, { sel: 'textarea[name=sa_json]', n: 2 }] });
};

// 23s. Le sedi e la SEO geografica (2.27.0): il modulo di una sede compilato come nel manuale, le coordinate chieste al servizio
// (il sito delle schermate ha nel config CMS_NOMINATIM_URL verso prove/nominatim-finto.php e CMS_NOMINATIM_PROVA), la mini mappa,
// l'elenco con due sedi. Le tessere di OpenStreetMap arrivano da Internet: dove la rete è chiusa (TESSERE_FINTE=1) la prova
// risponde lei con una tessera disegnata (sfondo chiaro e righe), così la foto mostra la mini mappa e non nove immagini rotte.
// Da solo: SOLO_CAPITOLI=23s, con TMPDIR vuota. Lascia le due sedi e la pagina /sede-di-bologna/ sul sito delle schermate.
capitoli['23s'] = async () => {
  if (process.env.TESSERE_FINTE) {
    const tessera = path.join(require('os').tmpdir(), 'tessera-finta.png');
    require('child_process').execFileSync('python3', ['-c', `
import sys
from PIL import Image, ImageDraw
im = Image.new('RGB', (256, 256), (242, 239, 233)); d = ImageDraw.Draw(im)
for i in range(0, 256, 32): d.line([(i, 0), (i, 256)], fill=(255, 255, 255), width=5); d.line([(0, i), (256, i)], fill=(255, 255, 255), width=5)
d.rectangle([40, 60, 120, 110], fill=(226, 222, 212)); d.rectangle([150, 140, 230, 200], fill=(226, 222, 212)); d.rectangle([60, 160, 110, 230], fill=(200, 222, 190))
d.line([(0, 128), (256, 100)], fill=(252, 214, 164), width=9)
im.save(sys.argv[1])`, tessera]);
    await p.route('https://tile.openstreetmap.org/**', r => r.fulfill({ status: 200, contentType: 'image/png', body: fs.readFileSync(tessera) }));
  }
  await vai('sedi.php');
  await p.fill('#sd-nome', 'Sede di Bologna');
  await p.selectOption('#sd-tipo', 'ristorante');
  await p.fill('#sd-descrizione', 'La sede storica, in centro, a due passi dalla stazione.');
  await p.fill('#sd-via', 'Via Rizzoli 1'); await p.fill('#sd-cap', '40125'); await p.fill('#sd-citta', 'Bologna'); await p.fill('#sd-provincia', 'BO');
  await foto('23-sedi-modulo', { sel: '#sd-modulo', altezza: 820, segni: [{ sel: '#sd-nome', n: 1 }, { sel: '#sd-tipo', n: 2 }, { sel: '#sd-citta', n: 3 }, { sel: '#sd-cerca', n: 4, lato: 'destra' }] });
  await p.click('#sd-cerca');
  await p.waitForFunction(() => /Trovato|non|errore/i.test(document.getElementById('sd-geo-esito').textContent), null, { timeout: 15000 });
  await dorme(600);
  await foto('23-sedi-mappa', { sel: '.sd-largo:has(#sd-lat)', margine: 10, segni: [{ sel: '#sd-tessere', n: 5 }] });
  await p.fill('#sd-telefono', '+39 051 123456'); await p.fill('#sd-email', 'bologna@studioprova.it');
  await p.selectOption('#sd-prezzo', '€€');
  await p.fill('#sd-zona', 'Bologna\nCasalecchio di Reno\nProvincia di Bologna');
  for (const g of ['lun', 'mar', 'mer', 'gio', 'ven']) await p.fill('#sd-orario-' + g, '9:00-13:00, 15:00-19:00');
  await p.fill('#sd-orario-sab', '9-13'); await p.fill('#sd-orario-dom', 'Chiuso');
  await p.fill('#sd-chiusure', '2026-12-24 - 2026-12-26: Natale');
  await p.fill('#sd-google-scheda', 'https://maps.app.goo.gl/esempio'); await p.fill('#sd-google-recensione', 'https://g.page/r/esempio/review');
  await Promise.all([p.waitForNavigation(), p.click('#sd-modulo button[type=submit]')]);
  await p.fill('#sd-nome', 'Sede di Imola');
  await p.selectOption('#sd-tipo', 'altro'); await p.fill('#sd-tipo-testo', 'centro di meditazione');
  await p.fill('#sd-via', 'Via Emilia 10'); await p.fill('#sd-citta', 'Imola'); await p.fill('#sd-provincia', 'BO');
  await p.uncheck('input[name=crea_pagina]');
  await Promise.all([p.waitForNavigation(), p.click('#sd-modulo button[type=submit]')]);
  await foto('23-sedi-elenco', { sel: '#sd-elenco', segni: [{ sel: '#sd-tabella tr:nth-child(2) form:has(input[value=principale]) button', n: 10, lato: 'destra' }] });
  if (process.env.TESSERE_FINTE) await p.unroute('https://tile.openstreetmap.org/**');
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
  await foto('23-email', { clip: { x: 240, y: 56, width: 960, height: 1120 }, segni: [{ sel: '[name=smtp_host]', n: 1 }, { sel: '[name=prova_a]', n: 2 }] });
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
  // 2.1.0: una copia fatta adesso, una foto caricata dopo, il ripristino pulito: la foto in più si sposta da parte.
  await Promise.all([p.waitForNavigation({ timeout: 300000 }), p.click('form:has(input[value=crea]) button[type=submit]')]);
  await vai('copia-sito.php');
  await foto('27-copia', { clip: { x: 240, y: 56, width: 960, height: 620 } });
  await vai('media.php');
  await p.setInputFiles('input[type=file][name=file]', path.join(FOTO_PROVA, 'agosto.jpg'));
  await Promise.all([p.waitForNavigation(), p.click('form[enctype] button[type=submit]')]);
  await vai('copia-sito.php');
  const riga = p.locator('.cs-table tr:has(details)').first();
  await riga.locator('details summary').click();
  await riga.locator('input[name=conferma]').check();
  await Promise.all([p.waitForNavigation({ timeout: 600000 }), riga.locator('button.danger').click()]);
  await p.waitForLoadState('networkidle');
  if (!(await p.locator('#cs-scartati').count())) throw new Error('27: dopo il ripristino manca il riquadro dei file spostati da parte');
  const fine = (await p.locator('#cs-scarto-elenco').count()) ? '#cs-scarto-elenco' : '#cs-scartati';
  await fotoFino('27-scartati', fine, { max: 1500, segni: [{ sel: '#cs-scartati', n: 2 }, { sel: '#cs-scartati code', n: 3, lato: 'destra' }, ...(fine === '#cs-scarto-elenco' ? [{ sel: '#cs-scarto-elenco', n: 4 }] : [])] });
};

// 28. Controlla il server
capitoli['28'] = async () => {
  await vai('controlla-server.php');
  await Promise.all([p.waitForNavigation({ timeout: 120000 }), p.locator('.cs-azioni button.btn-primary').first().click()]);
  await p.waitForLoadState('networkidle'); await dorme(300);
  // 2.2.0: la cima della pagina (quante voci a posto, da sapere, da sistemare) e il gruppo nuovo "Google e IndexNow", che sta più
  // in basso: due foto unite in una, con una riga tratteggiata dove la pagina è tagliata.
  if (SOLO && !'28'.startsWith(SOLO.slice(0, 2))) return;
  await p.evaluate(() => scrollTo(0, 0)); await dorme(100);
  const somma = await p.locator('.cs-somma').boundingBox();
  await foto('28-server-a', { clip: { x: 240, y: 56, width: 960, height: somma.y + somma.height + 14 - 56 } });
  await marca(p.locator('.cs-gruppo').filter({ has: p.locator('h2', { hasText: 'Google e IndexNow' }) }), 'google');
  const g = await p.locator('[data-mu=google]').boundingBox();
  await foto('28-server-b', { clip: { x: 240, y: g.y - 14, width: 960, height: g.height + 28 }, segni: [{ sel: '[data-mu=google]' }] });
  require('child_process').execFileSync('python3', ['-c', `
import sys
from PIL import Image, ImageDraw
a, b, o = (sys.argv[1] + n for n in ('28-server-a.png', '28-server-b.png', '28-server.png'))
A, B = Image.open(a).convert('RGB'), Image.open(b).convert('RGB')
fondo = A.getpixel((4, A.height - 4))
im = Image.new('RGB', (A.width, A.height + 30 + B.height), fondo)
im.paste(A, (0, 0)); im.paste(B, (0, A.height + 30))
d = ImageDraw.Draw(im)
for x in range(0, im.width, 14): d.line([(x, A.height + 15), (x + 7, A.height + 15)], fill=(150, 150, 160), width=2)
im.save(o)
`, GREZZE + path.sep]);
  for (const n of ['28-server-a', '28-server-b']) fs.unlinkSync(path.join(GREZZE, n + '.png'));
  console.log('28-server');
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

// 2.1.0: i widget nuovi sul sito (capitolo 8) e il campo File del form (capitolo 18). Come sopra, dopo tutti i capitoli: la pagina
// /widget-2-1/ la crea prove/pagina-widget-2-1.php nella cartella del sito delle schermate (CMS_DIR_MANUALE; senza, le due foto
// dei widget si saltano).
async function fotoWidget21() {
  if (SOLO && !['08', '17'].some(c => c.startsWith(SOLO.slice(0, 2)))) return;
  if (process.env.CMS_DIR_MANUALE) {
    require('child_process').execFileSync('php', [path.join(__dirname, '..', 'prove', 'pagina-widget-2-1.php'), process.env.CMS_DIR_MANUALE, 'nuovi']);
    await p.goto(B + '/widget-2-1/', { waitUntil: 'networkidle' }); await dorme(600);
    await p.addStyleTag({ content: 'header{visibility:hidden}' });
    const menu = p.locator('.ew-menu').first();
    await menu.scrollIntoViewIfNeeded(); await dorme(300);
    await menu.screenshot({ path: path.join(GREZZE, '08-menu-ristorante.png') }); console.log('08-menu-ristorante');
    const gal = p.locator('.ed-sezione:has(.ew-gf-filtri)').first();
    await gal.scrollIntoViewIfNeeded();
    await gal.locator('.ew-gf-filtro', { hasText: 'Ritratti' }).click(); await dorme(700);
    await gal.screenshot({ path: path.join(GREZZE, '08-galleria-filtri.png') }); console.log('08-galleria-filtri');
  }
  // Il campo File nel form Candidature: dalla 2.16.0 la foto 17-campo-file si fa nel capitolo 17 (passo 2 del costruttore).
}

// 2.2.0: la finestra Scegli dal sito (capitolo 8), con "con" nella ricerca e una pagina in bozza ("Condizioni di vendita"). Dopo
// tutti i capitoli: la pagina in bozza prenderebbe un numero di pagina che un capitolo dopo si aspetta.
// Infografiche disegnate: le sedici infografiche disegnate (2.26.0), in una foto d'insieme (capitolo 8). La pagina /infografiche-15/ la crea
// prove/pagina-infografiche-15.php nella cartella del sito delle schermate (CMS_DIR_MANUALE; senza, la foto si salta). Ogni
// infografica si fotografa da sola a 1200 px, poi le sedici si mettono in una griglia di tre colonne. Dopo tutti i capitoli,
// come le altre pagine di prova. Da sola: SOLO_CAPITOLI=infografiche.
async function fotoInfografiche() {
  if (!process.env.CMS_DIR_MANUALE || (SOLO && !'08'.startsWith(SOLO.slice(0, 2)))) return;
  require('child_process').execFileSync('php', [path.join(__dirname, '..', 'prove', 'pagina-infografiche-15.php'), process.env.CMS_DIR_MANUALE, 'normali']);
  await p.goto(B + '/infografiche-15/', { waitUntil: 'networkidle' }); await dorme(600);
  await p.addStyleTag({ content: 'header{visibility:hidden}.igx .igx-firma{display:none}' });
  const pezzi = [];
  for (let i = 0; i < 16; i++) {
    const f = path.join(GREZZE, `08-infografica-${String(i).padStart(2, '0')}.png`);
    await p.locator('.igx').nth(i).screenshot({ path: f }); pezzi.push(f);
  }
  require('child_process').execFileSync('python3', ['-c', `
import sys
from PIL import Image
uscita, pezzi = sys.argv[1], sys.argv[2:]
col, larg, spazio = 3, 400, 16
im = [Image.open(f).convert('RGB') for f in pezzi]
im = [x.resize((larg, round(x.height * larg / x.width)), Image.LANCZOS) for x in im]
righe = [im[i:i + col] for i in range(0, len(im), col)]
H = sum(max(x.height for x in r) for r in righe) + spazio * (len(righe) + 1)
tela = Image.new('RGB', (col * larg + spazio * (col + 1), H), (238, 240, 238))
y = spazio
for r in righe:
    for j, x in enumerate(r): tela.paste(x, (spazio + j * (larg + spazio), y))
    y += max(x.height for x in r) + spazio
tela.save(uscita)
`, path.join(GREZZE, '08-infografiche.png'), ...pezzi]);
  for (const f of pezzi) fs.unlinkSync(f);
  console.log('08-infografiche');
}

async function fotoSceltaSito() {
  if (SOLO && !'08'.startsWith(SOLO.slice(0, 2))) return;
  await vai('index.php');
  await Promise.all([p.waitForNavigation(), p.click('.db-azioni a:has-text("+ Pagina")')]);
  await apriEditor(p.url().split('/admin/')[1]);
  await p.click('#ed-apri-impostazioni'); await dorme(300);
  await p.fill('#ed-form-impostazioni [name=titolo]', 'Condizioni di vendita');
  await p.fill('#ed-form-impostazioni [name=slug]', 'condizioni-di-vendita');
  await p.click('#ed-form-impostazioni button[type=submit]'); await dorme(1000);
  await p.click('#ed-salva');
  await p.waitForFunction(() => /Bozza salvata/.test(document.querySelector('#ed-stato').textContent));
  const pu = await primoPulsanteApertura();
  await apriScelta(pu.scegli, 'con');
  if (!(await p.locator(SCELTA + ' .pn-link-bozza').count())) throw new Error('08: nella finestra non c\'è la pagina in bozza');
  const tre = (await p.locator(SCELTA + ' .pn-link-voce.pn-link-scelta').count()) ? SCELTA + ' .pn-link-voce.pn-link-scelta' : SCELTA + ' .pn-link-voce';
  await foto('08-scegli-finestra', { sel: SCELTA, margine: 0, segni: [{ sel: SCELTA + ' input[type=search]', n: 1 }, { sel: SCELTA + ' .pn-link-tipo-scelta select', n: 2 },
    { sel: tre, n: 3 }, { sel: SCELTA + ' .pn-link-bozza', n: 4, lato: 'destra' }, { sel: SCELTA + ' .pn-link-mano summary', n: 5 }] });
  await p.keyboard.press('Escape'); await dorme(300);
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
  const v = await c.newPage(); pnConferme.on(v, d => d.accept());
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
  // 2.16.0: il costruttore a passi (form di iscrizione): passo 1 crea, passo 2 i testi, passo 4 lista, conferma e file.
  await vai('form.php?nuovo=1&tipo=iscrizione');
  await p.fill('#fm-nome', 'Guida in regalo');
  await Promise.all([p.waitForNavigation(), p.click('#fm-form .fo-azioni .btn-primary')]);
  await p.fill('#fm-titolo', 'Ricevi la guida alla cura delle piante');
  await p.fill('#fm-intro', 'Lasciaci nome ed email: dopo la conferma ti mandiamo la guida in PDF e, una volta al mese, la nostra newsletter.');
  await p.fill('#fm-pulsante', 'Mandami la guida');
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=resta]')]);
  await p.goto(p.url().replace(/passo=\d/, 'passo=4'), { waitUntil: 'networkidle' });
  await p.selectOption('select[name=lista_id]', { label: 'Newsletter' });
  await p.check('input[name=conferma][value=doppia]');
  await p.selectOption('#fm-omaggio', { label: 'Guida alla cura delle piante (guida-piante.pdf)' });
  await foto('31n-form-file', { clip: { x: 240, y: 56, width: 960, height: 520 }, segni: [{ sel: 'input[name=conferma][value=doppia]', n: 1 }, { sel: 'select[name=lista_id]', n: 2 }, { sel: '#fm-omaggio', n: 3 }] });
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=resta]')]);
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
  await f.locator('button[type=submit]').click(); await v.waitForURL(/\/grazie\/|modulo=ok/); await v.waitForLoadState('networkidle'); // 2.16.0: un form di iscrizione nuovo mostra il messaggio al posto del form
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

// Una foto del pannello (colonna di destra) dall'alto fino in fondo a un elemento.
async function fotoFino(nome, selFine, opz = {}) {
  await p.evaluate(() => scrollTo(0, 0)); await dorme(100);
  const r = await p.locator(selFine).last().boundingBox();
  if (!r) throw new Error('foto ' + nome + ': non trovo ' + selFine);
  await foto(nome, { clip: { x: 240, y: 56, width: 960, height: Math.min(opz.max || 1400, Math.round(r.y + r.height + 24 - 56)) }, segni: opz.segni || [] });
}

// 31q. Consegna delle email (2.1.0, capitolo 25): lo Studio Rossi e il mittente notizie@studiorossi.it. Il DNS del dominio è
// finto (CMS_DNS_PROVA nel config.local.php del sito, con i record di studiorossi.it: SPF e MX sì, DKIM e DMARC no); senza,
// la linguetta Dominio dice che il DNS non risponde. Le statistiche vogliono il server di posta finto con "rimbalzi"
// (prove/smtp-finto.py <porta> <cartella> rimbalzi): due indirizzi su venticinque rimbalzano.
capitoli['31q'] = async () => {
  await postaFinta();
  await vai('consegna.php');
  await fotoFino('23-consegna-inizio', '.ce-passi');
  // 1. Il mittente, confermato dall'email.
  await vai('consegna.php?scheda=mittenti');
  await p.fill('#ce-m-nome', 'Studio Rossi Notizie'); await p.fill('#ce-m-email', 'notizie@studiorossi.it');
  let n0 = postaN();
  await Promise.all([p.waitForNavigation(), p.click('#ce-nuovo-mittente button[type=submit]')]);
  const mc = (await postaNuove(n0)).find(e => /notizie@studiorossi\.it/.test(e.a));
  const lc = mc && C2.link(mc).find(l => /api\/mittente\.php\?t=/.test(l));
  if (!lc) throw new Error('31q: non arriva la conferma del mittente');
  const v = await visitatore();
  await v.goto(qui(lc), { waitUntil: 'networkidle' });
  await v.click('form button[type=submit]'); await v.waitForLoadState('networkidle');
  if (!/Mittente confermato/.test(await v.locator('body').innerText())) throw new Error('31q: il mittente non si conferma');
  await v.context().close();
  await vai('consegna.php?scheda=mittenti');
  await p.fill('#ce-m-nome', 'Anna dello Studio'); await p.fill('#ce-m-email', 'anna@studiorossi.it');
  await fotoFino('23-consegna-mittenti', '#ce-nuovo-mittente', { segni: [{ sel: '.ce-schede a[href="?scheda=mittenti"]', n: 1 }, { sel: '#ce-m-nome', n: 2 },
    { sel: '#ce-nuovo-mittente button[type=submit]', n: 3 }, { sel: 'tr[data-mittente="notizie@studiorossi.it"]', n: 6 }] });
  // 2. Il dominio.
  await vai('consegna.php?scheda=dominio');
  await p.fill('#ce-dominio', 'studiorossi.it');
  await Promise.all([p.waitForNavigation(), p.click('form:has(#ce-dominio) button[type=submit]')]);
  await fotoFino('23-consegna-dominio', (await p.locator('#ce-messaggio').count()) ? '#ce-messaggio' : '.ce-voce', { max: 1500, segni: [{ sel: '#ce-dominio', n: 1 }, { sel: '#ce-dkim', n: 2 },
    ...((await p.locator('#ce-messaggio').count()) ? [{ sel: '#ce-messaggio', n: 4 }] : [])] });
  // 3. Come partono: la tendina con Brevo (solo la foto, non si salva).
  await vai('consegna.php?scheda=servizio');
  await p.selectOption('#ce-servizio', 'brevo'); await dorme(400);
  await fotoFino('23-consegna-servizio', '#ce-prova', { segni: [{ sel: '#ce-servizio', n: 1 }, { sel: '#ce-brevo', n: 2 }, { sel: '#ce-prova', n: 3 }] });
  // 7. Le statistiche: venticinque clienti importati, una campagna, due rimbalzi e un'uscita.
  const csv = path.join(require('os').tmpdir(), 'clienti-studio-rossi.csv');
  fs.writeFileSync(csv, 'EMAIL,NOME\n' + Array.from({ length: 25 }, (_, i) => (i === 7 || i === 18 ? `rimbalza${i}@esempio.it` : `cliente${String(i + 1).padStart(2, '0')}@esempio.it`) + ',Cliente ' + (i + 1)).join('\n') + '\n');
  await vai('newsletter.php?scheda=iscritti');
  await p.setInputFiles('form:has(input[value=importa]) input[name=csv]', csv);
  await p.fill('form:has(input[value=importa]) input[name=lista_nuova]', 'Clienti');
  await p.check('form:has(input[value=importa]) input[name=consenso_brevo]');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=importa]) button[type=submit]')]);
  // 2.11.0: Crea una campagna, poi le sezioni del riepilogo e Manda la campagna.
  await vai('newsletter.php?crea=1');
  await p.fill('#nl-crea-nome', 'Le novità di novembre');
  await Promise.all([p.waitForNavigation(), p.click('#nl-crea button[type=submit]')]);
  const camp31 = p.url().split('admin/')[1];
  await vai(camp31 + '&apri=oggetto'); await p.fill('#oggetto', 'Le novità di novembre');
  await Promise.all([p.waitForNavigation(), p.click('#sez-oggetto button[type=submit]')]);
  await vai(camp31 + '&apri=design');
  await p.locator('#editor .ql-editor').click(); await p.keyboard.type('Ciao! A novembre lo studio è aperto anche il sabato mattina.');
  await Promise.all([p.waitForNavigation(), p.click('#nl-form button[type=submit]')]);
  await vai(camp31 + '&apri=destinatari');
  await p.locator('#sez-destinatari label', { hasText: 'Clienti' }).first().locator('input[name="liste[]"]').check();
  await Promise.all([p.waitForNavigation(), p.click('#sez-destinatari button[type=submit]')]);
  await Promise.all([p.waitForNavigation(), p.click('#nlr-invio button.btn-primary')]);
  n0 = postaN();
  await vai('newsletter.php');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=giro]) button')]);
  const uscita = (await postaNuove(n0, 23)).find(e => /cliente03@/.test(e.a));
  const lu = uscita && C2.link(uscita).find(l => /[?&]i=\d+&t=/.test(l) && /[?&]c=\d+/.test(l));
  if (!lu) throw new Error('31q: la campagna non arriva (o senza il link per uscire)');
  const u = await visitatore();
  await u.goto(qui(lu), { waitUntil: 'networkidle' });
  await u.click('form[action*="i="] button[type=submit]'); await u.waitForLoadState('networkidle');
  await u.context().close();
  await vai('newsletter-statistiche.php');
  await foto('23-consegna-statistiche', { sel: '#st-tabella', margine: 6, segni: [{ sel: '#st-tabella td.st-rimbalzi', n: '' }] }); // 2.14.0: la tabella del periodo
  // 5. Il riscaldamento.
  await vai('consegna.php?scheda=riscaldamento');
  await p.check('#ce-acceso');
  await Promise.all([p.waitForNavigation(), p.click('form:has(#ce-acceso) button[type=submit]')]);
  await fotoFino('23-consegna-riscaldamento', '#ce-giorni', { segni: [{ sel: '#ce-acceso', n: 1 }, { sel: '#ce-partenza', n: 2 }, { sel: '#ce-caldo-stato', n: 3 }] });
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

// 31r. (2.7.0) L'email di conferma modificabile: capitolo 18 ("Cambiare l'email di conferma", sul form "Guida in regalo" del
// capitolo 31n; se manca, lo si crea qui) e capitolo 20 ("L'email di conferma dell'iscrizione", Newsletter > Impostazioni, senza
// salvare). Si può rifare da solo: SOLO_CAPITOLI=31r.
capitoli['31r'] = async () => {
  await vai('form.php');
  let guida = p.locator('a.ct-nome', { hasText: 'Guida in regalo' }).first();
  if (!(await guida.count())) {
    await vai('form.php?nuovo=1&tipo=iscrizione');
    await p.fill('#fm-nome', 'Guida in regalo');
    await Promise.all([p.waitForNavigation(), p.click('#fm-form .fo-azioni .btn-primary')]);
    await p.fill('#fm-titolo', 'Ricevi la guida alla cura delle piante');
    await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=resta]')]);
    await p.goto(p.url().replace(/passo=\d/, 'passo=4'), { waitUntil: 'networkidle' });
    await p.selectOption('select[name=lista_id]', { label: 'Newsletter' });
    await p.check('input[name=conferma][value=doppia]');
    await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=resta]')]);
  } else await vai((await guida.getAttribute('href')) + '&passo=4'); // 2.16.0: l'email di conferma sta nel passo 4, Conferma
  await p.waitForLoadState('networkidle');
  if ((await p.locator('#fm-conferma-campi').getAttribute('open')) === null) await p.click('#fm-conferma-campi > summary');
  await p.fill('#ec-oggetto', 'Ultimo passo per la tua guida, {nome}');
  await p.fill('#ec-pulsante', 'Sì, mandami la guida');
  await p.fill('#ec-colore', '#0a7d32');
  await p.click('#fm-conferma [data-ec-anteprima]');
  await p.waitForFunction(() => /mandami la guida/.test((document.querySelector('#fm-conferma .ec-cornice') || {}).srcdoc || ''));
  await dorme(800);
  // Due foto: i campi cambiati (passi 1-2) e la barra con l'anteprima (passi 3-5), unite in una sola come 28-server.
  await p.evaluate(() => scrollTo(0, 0)); await dorme(100); // le foto con fullPage vogliono le coordinate della pagina
  const r1 = await p.locator('#fm-conferma').boundingBox();
  const fine1 = await p.locator('.ec-campo:has(#ec-colore)').boundingBox();
  await segna(p, [{ sel: '#fm-conferma-campi > summary', n: 1 }, { sel: '#ec-oggetto', n: 2 }, { sel: '#ec-pulsante', n: 2 }, { sel: '#ec-colore', n: 2 }]);
  await p.screenshot({ path: path.join(GREZZE, '17-email-conferma-a.png'), clip: { x: r1.x - 12, y: r1.y - 12, width: r1.width + 24, height: fine1.y + fine1.height - r1.y + 24 }, fullPage: true });
  await togliSegni(p);
  // La cornice dell'anteprima non esce nelle foto della pagina intera: la foto si fa con la pagina scorsa fin lì.
  await p.evaluate(() => scrollBy(0, document.querySelector('#fm-conferma .ec-anteprima-barra').getBoundingClientRect().top - 120)); await dorme(300); // sotto la barra in alto del pannello
  await segna(p, [{ sel: '#fm-conferma [data-ec-riempi]', n: 3 }, { sel: '#fm-conferma [data-ec-anteprima]', n: 4 }, { sel: '#fm-conferma [data-ec-prova]', n: 5 }]);
  const b2 = await p.locator('#fm-conferma .ec-anteprima-barra').boundingBox();
  const c2 = await p.locator('#fm-conferma .ec-cornice').boundingBox();
  await p.screenshot({ path: path.join(GREZZE, '17-email-conferma-b.png'), clip: { x: c2.x - 12, y: Math.max(0, b2.y - 12), width: c2.width + 24, height: Math.min(800 - Math.max(0, b2.y - 12), c2.y + Math.min(c2.height, 440) - b2.y + 24) } });
  await togliSegni(p);
  if (!SOLO || '17'.startsWith(SOLO.slice(0, 2))) {
    require('child_process').execFileSync('python3', ['-c', `
import sys
from PIL import Image
a, b, o = sys.argv[1], sys.argv[2], sys.argv[3]
A, B = Image.open(a).convert('RGB'), Image.open(b).convert('RGB')
w = max(A.width, B.width)
U = Image.new('RGB', (w, A.height + B.height), (255, 255, 255))
U.paste(A, (0, 0)); U.paste(B, (0, A.height)); U.save(o)
`, path.join(GREZZE, '17-email-conferma-a.png'), path.join(GREZZE, '17-email-conferma-b.png'), path.join(GREZZE, '17-email-conferma.png')]);
    for (const f of ['17-email-conferma-a.png', '17-email-conferma-b.png']) fs.unlinkSync(path.join(GREZZE, f));
    console.log('17-email-conferma');
  }
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=resta]')]);
  if (!/salvato/.test(await flash())) throw new Error('31r: il form non si salva: ' + await flash());
  // Newsletter > Impostazioni: il riquadro, senza salvare.
  await vai('newsletter.php?scheda=impostazioni#nl-conferma');
  await p.fill('#nl-conferma #ec-firma', 'Studio Prova, vasi e piante a Bologna');
  await p.evaluate(() => scrollTo(0, 0)); await dorme(100);
  const nl = await p.locator('#nl-conferma').boundingBox();
  const nlFine = await p.locator('#nl-conferma .ec-campo:has(#ec-titolo)').boundingBox();
  await segna(p, [{ sel: '#nl-conferma #ec-oggetto', n: 1 }, { sel: '#nl-conferma .ec-segnaposti summary', n: 2 }]);
  if (!SOLO || '19'.startsWith(SOLO.slice(0, 2))) {
    await p.screenshot({ path: path.join(GREZZE, '19-email-conferma.png'), clip: { x: nl.x - 12, y: nl.y - 12, width: nl.width + 24, height: nlFine.y + nlFine.height - nl.y + 24 }, fullPage: true });
    console.log('19-email-conferma');
  }
  await togliSegni(p);
};

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
  // 2.1.0: Paolo Verdi chiama: un taglio da Anna il giovedì dopo quello di Giulia, alle 10, senza email.
  const gio = new Date(d + 'T12:00:00'); do gio.setDate(gio.getDate() + 1); while (gio.getDay() !== 4);
  const giovedi = `${gio.getFullYear()}-${String(gio.getMonth() + 1).padStart(2, '0')}-${String(gio.getDate()).padStart(2, '0')}`;
  await addonPag('prenotazioni', 'nuova');
  const opz = async (sel, testo) => p.locator(sel + ' option', { hasText: testo }).first().getAttribute('value');
  await Promise.all([p.waitForNavigation(), p.selectOption('#pa-nuova-quando select[name=s]', await opz('#pa-nuova-quando select[name=s]', 'Taglio donna'))]);
  await Promise.all([p.waitForNavigation(), p.selectOption('#pa-nuova-quando select[name=r]', await opz('#pa-nuova-quando select[name=r]', 'Anna'))]);
  await Promise.all([p.waitForNavigation(), p.fill('#pa-nuova-quando input[name=d]', giovedi)]);
  await p.locator('label.pa-ora-scelta', { hasText: /^10:00/ }).first().click();
  await p.fill('#pa-nuova [name=nome]', 'Paolo Verdi'); await p.fill('#pa-nuova [name=telefono]', '051 123456');
  await p.fill('#pa-nuova [name=note]', 'Preferisce Anna, capelli lunghi');
  await fotoFino('33-nuova', '#pa-nuova button[type=submit]', { max: 1500, segni: [{ sel: '#pa-nuova-quando', n: 2 }, { sel: 'label.pa-ora-scelta:has(input:checked)', n: 3 },
    { sel: '#pa-nuova [name=nome]', n: 4 }, { sel: '#pa-nuova button[type=submit]', n: 5 }] });
  await Promise.all([p.waitForNavigation(), p.click('#pa-nuova button[type=submit]')]);
  if (!/Prenotazione salvata/.test(await flash())) throw new Error('33: la prenotazione dal pannello non si salva: ' + await flash());
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
  // 2.1.0: il riquadro Area riservata nella scheda dell'articolo del capitolo 4 (solo la foto: la spunta non si salva).
  await vai('contenuti.php?tipo=articoli');
  await vai(await p.locator('tr:has-text("Chiusi per ferie") .ct-azioni a', { hasText: 'Modifica' }).first().getAttribute('href'));
  await p.check('#membri-riservato');
  const rq = await p.locator('#membri-scheda').boundingBox();
  await p.evaluate(() => scrollTo(0, 0));
  const top = await p.locator('.sc-scatola:has(h3:text-is("Pubblica"))').boundingBox();
  const r2 = await p.locator('#membri-scheda').boundingBox();
  await foto('38-scheda', { clip: { x: top.x - 12, y: top.y - 12, width: top.width + 24, height: Math.min(1300, r2.y + r2.height - top.y + 24) },
    segni: [{ sel: '#membri-scheda h3', n: 2 }, { sel: '#membri-riservato', n: 3 }, { sel: 'button[data-valore=pubblica]', n: '' }] });
  if (!rq) throw new Error('38: manca il riquadro Area riservata nella scheda');
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
  // 2.1.0: il testo della lezione si scrive con l'editor visuale (il pulsante salva anche la pagina della lezione). Nella tela
  // c'è già un widget Testo: doppio clic, si scrive, Pubblica, e la freccia riporta alla lezione.
  const testoLezione = async (paragrafi, fotoEditor) => {
    await Promise.all([p.waitForNavigation(), p.click('#le-apri-editor')]);
    await apriEditor(p.url().split('/admin/')[1]);
    await tela().locator('.ed-w-testo').first().dblclick(); await dorme(500);
    await p.locator('#ed-controlli .ql-editor').click(); await p.keyboard.press('Control+A');
    for (let k = 0; k < paragrafi.length; k++) { if (k) await p.keyboard.press('Enter'); await p.keyboard.type(paragrafi[k]); }
    await dorme(900);
    if (fotoEditor) await foto(fotoEditor, { clip: { x: 0, y: 0, width: 1200, height: 700 }, segni: [{ sel: '#ed-iframe', n: 4 }, { sel: '#ed-controlli .ql-editor', n: 5 },
      { sel: '.ed-schede-pannello button[data-pannello="widget"]', n: 6 }, { sel: '#ed-pubblica', n: 7, lato: 'destra' }, { sel: 'a.ed-indietro', n: 8, lato: 'destra' }] });
    await pubblica();
    await Promise.all([p.waitForNavigation(), p.click('a.ed-indietro')]);
    await p.waitForLoadState('networkidle');
  };
  const uLuce = await lezione(0, 'La luce');
  await p.fill('#le-video', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'); await p.fill('#le-durata', '8 minuti');
  await testoLezione(['La luce migliore arriva presto, appena dopo l\'alba, e poco prima del tramonto.', 'Mettiti con la luce di lato: le forme si vedono meglio.'], '39-lezione-editor');
  const uInq = await lezione(0, 'L\'inquadratura');
  await p.fill('#le-durata', '10 minuti');
  await p.locator('label.le-mat', { hasText: 'scheda-inquadratura.pdf' }).locator('input').check();
  await foto('39-lezione', { sel: '#le-form', segni: [{ sel: '#le-apri-editor', n: 1 }, { sel: '#le-video', n: 2 }, { sel: 'label.le-mat', n: 3 }, { sel: '#le-durata', n: 4 }, { sel: '#le-form aside button.btn-primary', n: 5 }] });
  await testoLezione(['La regola dei terzi: dividi lo schermo in nove riquadri e metti il soggetto dove le linee si incrociano, non al centro.', 'La scheda qui sotto la riassume: tienila nel telefono.']);
  await vai(urlCorso);
  await p.fill('#co-nuovo-modulo input[name=titolo]', 'Pratica');
  await Promise.all([p.waitForNavigation(), p.click('#co-nuovo-modulo button')]);
  await lezione(1, 'Il primo scatto');
  await p.fill('#le-durata', '5 minuti');
  await testoLezione(['Esci, scegli una cosa sola da fotografare e fanne dieci scatti diversi. Poi tieni il migliore.']);
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
// Un'altra porta: COPIA_PORTA=<porta> (la stessa nel config.local.php).
capitoli['40'] = async () => {
  await accendi('copia-altrove');
  await addonPag('copia-altrove', 'destinazione');
  // 2.1.0: il riquadro Google Drive, non ancora collegato (solo la foto).
  await p.selectOption('#ca-tipo', 'gdrive'); await dorme(400);
  await p.fill('#ca-cartella', 'copie-del-sito'); await p.fill('#ca-tenere', '7'); await p.check('#ca-attivo');
  await foto('40-gdrive', { sel: '#ca-form', segni: [{ sel: '#ca-tipo', n: 2 }, { sel: '#ca-gdrive-redirect', n: 3 }, { sel: '#ca-gdrive-client_id', n: 10 }, { sel: '#ca-cartella', n: 11 },
    { sel: '#ca-gdrive-collega', n: 12, lato: 'destra' }] });
  await p.selectOption('#ca-tipo', 'webdav'); await dorme(300);
  await p.fill('#ca-url', 'https://cloud.esempio.it/remote.php/dav/files/mario/');
  await p.fill('#ca-utente', 'mario'); await p.fill('#ca-password', 'password-per-le-app');
  await p.fill('#ca-cartella', 'copie-sito'); await p.fill('#ca-tenere', '14'); await p.check('#ca-attivo');
  await foto('40-destinazione', { sel: '#ca-form', segni: [{ sel: '#ca-tipo', n: 1 }, { sel: '#ca-url', n: 2 }, { sel: '#ca-utente', n: 3 }, { sel: '#ca-cartella', n: 4 }, { sel: '#ca-tenere', n: 5 }, { sel: '#ca-attivo', n: 6 }, { sel: '#ca-salva', n: 7 }] });
  await p.fill('#ca-url', 'http://127.0.0.1:' + (process.env.COPIA_PORTA || '8297') + '/dav/'); await p.fill('#ca-utente', 'copie'); await p.fill('#ca-password', 'Nuvola-Copie-2026');
  await Promise.all([p.waitForNavigation(), p.click('#ca-salva')]);
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
  pnConferme.on(p, d => d.accept());
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
  if (!SOLI.length || SOLI.includes('fine')) { await fotoWidgetAttivita(); await fotoWidget21(); await fotoSceltaSito(); } // SOLO_CAPITOLI=...,fine: anche le foto dei widget sul sito
  if (!SOLI.length || SOLI.includes('fine') || SOLI.includes('infografiche')) await fotoInfografiche(); // infografiche disegnate: SOLO_CAPITOLI=infografiche
  if (err.length) console.log('errori JavaScript:', err);
  stringi();
  const tot = fs.readdirSync(USCITA).filter(f => f.endsWith('.jpg')).reduce((t, f) => t + fs.statSync(path.join(USCITA, f)).size, 0);
  console.log('immagini del manuale: ' + Math.round(tot / 1024) + ' KB');
  await br.close();
})().catch(e => { console.error('FERMO:', e.message); process.exit(1); });
