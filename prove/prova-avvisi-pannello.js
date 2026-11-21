// 2.31.0: il pulsante "Apri nel pannello" negli avvisi con il Reply-To del visitatore (segnalazione di Danilo, 6 novembre 2026,
// sito di Cucina Creativa: rispondendo a una richiesta di prenotazione il cliente riceveva citati il pulsante e l'indirizzo del pannello).
// Controlla: la casella in Impostazioni > Configurazione email (spenta di partenza), l'anteprima e la prova con e senza pulsante, e i
// cinque avvisi veri (form, richiesta di prenotazione, Prenotazioni, Vetrina, Iscrizioni agli eventi) mandati da prove/avvisi-cli.php:
// casella spenta, nessun indirizzo del pannello nel corpo (HTML e testo); accesa, il pulsante come prima. Anche a 390 px.
// Lascia dati di prova: va su un sito da buttare.
// uso: node prove/prova-avvisi-pannello.js <base> <utente> <password> <cartella del CMS> <porta smtp libera> <cartella posta> [cartella foto]
const { chromium } = require('playwright');
const { execFileSync, spawn } = require('child_process');
const fs = require('fs'); const path = require('path');
const pnConferme = require('./conferme-pannello');
const [B, UTENTE, PASSWORD, CMS, PORTA, POSTA, FOTO = ''] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const dorme = ms => new Promise(r => setTimeout(r, ms));

function leggi(eml) {
  const i = eml.search(/\r?\n\r?\n/);
  const testa = eml.slice(0, i).replace(/\r?\n[ \t]+/g, ' '); const corpo = eml.slice(i).replace(/^\r?\n\r?\n/, '');
  const h = (t, nome) => ((t.match(new RegExp('^' + nome + ':[ \\t]*(.*)$', 'mi')) || [])[1] || '').trim();
  const dec = s => s.replace(/=\?UTF-8\?B\?([^?]+)\?=/gi, (_, b) => Buffer.from(b, 'base64').toString('utf8'));
  const parte = (t, c) => /base64/i.test(h(t, 'Content-Transfer-Encoding')) ? Buffer.from(c.replace(/\s+/g, ''), 'base64').toString('utf8')
    : (/quoted-printable/i.test(h(t, 'Content-Transfer-Encoding')) ? Buffer.from(c.replace(/=\r?\n/g, '').replace(/=([0-9A-F]{2})/gi, (_, x) => String.fromCharCode(parseInt(x, 16))), 'latin1').toString('utf8') : c);
  const ct = h(testa, 'Content-Type'); let html = '', testo = '';
  const b = (ct.match(/boundary="([^"]+)"/) || [])[1];
  if (b) {
    for (const p of corpo.split('--' + b).slice(1)) {
      const j = p.search(/\r?\n\r?\n/); if (j < 0) continue;
      const pt = p.slice(0, j).trim(); const pc = p.slice(j).trim();
      if (/text\/plain/i.test(h(pt, 'Content-Type'))) testo = parte(pt, pc); else if (/text\/html/i.test(h(pt, 'Content-Type'))) html = parte(pt, pc);
    }
  } else if (/text\/plain/i.test(ct)) testo = parte(testa, corpo); else html = parte(testa, corpo);
  return { a: h(testa, 'To').replace(/[<>]/g, ''), reply: h(testa, 'Reply-To'), oggetto: dec(h(testa, 'Subject')), html, testo };
}
const posta = dir => (fs.existsSync(dir) ? fs.readdirSync(dir) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => leggi(fs.readFileSync(path.join(dir, f), 'utf8')));
const PANNELLO = /\/admin\/|form-submissions\.php|addon-pagina\.php/;
const PULSANTE = /Apri nel pannello|Apri l(&#0?39;|&apos;|')ordine nel pannello|Apri gli iscritti/;

(async () => {
  fs.rmSync(POSTA, { recursive: true, force: true }); fs.mkdirSync(POSTA, { recursive: true });
  const smtp = spawn('python3', ['-I', path.join(__dirname, 'smtp-finto.py'), PORTA, POSTA], { stdio: 'ignore' });
  await dorme(800);
  const cli = (...a) => execFileSync('php', [path.join(__dirname, 'avvisi-cli.php'), CMS, ...a], { encoding: 'utf8' }).trim();
  const br = await chromium.launch();
  try {
    const c = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await c.newPage();
    pnConferme.on(p, d => d.accept());
    const err = []; p.on('pageerror', e => err.push(e.message)); p.on('console', m => { if (m.type() === 'error') err.push(m.text()); });
    await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    ok('accesso al pannello', !p.url().includes('login.php'));

    // Il server di posta finto, per la prova dal pannello (e l'indirizzo degli avvisi), lo scrive avvisi-cli.php.
    cli('manda', PORTA, '10.9.' + Math.floor(Math.random() * 250) + '.1'); // anche: casella mai salvata = spenta
    let prima = posta(POSTA);
    const avvisi = prima.filter(m => m.a === 'negozio@esempio.it');
    ok('di partenza: cinque avvisi con il Reply-To del visitatore', avvisi.length === 5 && avvisi.every(m => /cliente\./.test(m.reply)), avvisi.map(m => [m.oggetto, m.reply]));
    for (const m of avvisi) {
      ok('spenta: niente pulsante, niente indirizzo del pannello: ' + m.oggetto, !PULSANTE.test(m.html) && !PANNELLO.test(m.html) && !PANNELLO.test(m.testo) && !/copia questo indirizzo/.test(m.html), { html: (m.html.match(PANNELLO) || [])[0] });
      ok('spenta: i dati della richiesta restano: ' + m.oggetto, /cliente\.|Maria|Luca|Anna|Paolo|Sara/.test(m.html) && m.html.length > 600);
    }

    // Il riquadro in Configurazione email
    await p.goto(B + '/admin/impostazioni.php', { waitUntil: 'networkidle' });
    const riq = p.locator('#avvisi');
    ok('riquadro "Gli avvisi che ricevi" con la fascia', (await riq.locator('h3').first().innerText()) === 'Gli avvisi che ricevi');
    const casella = p.locator('#avvisi_pulsante_pannello');
    ok('casella spenta di partenza, con l\'etichetta vera', !(await casella.isChecked()) && /mostra il pulsante "Apri nel pannello"/.test(await p.locator('label[for=avvisi_pulsante_pannello]').innerText()));
    ok('aiuto collegato alla casella', (await casella.getAttribute('aria-describedby')) === 'avvisi-aiuto' && await p.locator('#avvisi-aiuto').isVisible());
    const srcdoc = async () => (await p.locator('#avvisi iframe.av-cornice').getAttribute('srcdoc')) || '';
    let s = await srcdoc();
    ok('anteprima spenta: senza pulsante e senza indirizzo del pannello', s.includes('Maria Rossi') && !PULSANTE.test(s) && !PANNELLO.test(s));
    ok('anteprima in un iframe sandbox', (await p.locator('#avvisi iframe.av-cornice').getAttribute('sandbox')) === '');
    const corniceVede = await p.frameLocator('#avvisi iframe.av-cornice').locator('h1').innerText().catch(() => '');
    ok('l\'anteprima si vede (titolo dell\'avviso)', /Contatti/.test(corniceVede), corniceVede);
    if (FOTO) await riq.screenshot({ path: path.join(FOTO, 'avvisi-spenta.png') });

    // Prova dal pannello, spenta
    let quante = posta(POSTA).length;
    await Promise.all([p.waitForNavigation(), riq.locator('button', { hasText: 'Manda un avviso di prova' }).click()]);
    for (let i = 0; i < 20 && posta(POSTA).length === quante; i++) await dorme(250);
    let pr = posta(POSTA).slice(quante);
    ok('prova spenta: arriva, senza pulsante né indirizzo del pannello', pr.length === 1 && /^\[PROVA\]/.test(pr[0].oggetto) && !PULSANTE.test(pr[0].html) && !PANNELLO.test(pr[0].html) && !PANNELLO.test(pr[0].testo), pr.map(m => m.oggetto));
    ok('prova: messaggio verde annunciato', /Avviso di prova spedito a negozio@esempio\.it, senza il pulsante/.test(await p.locator('[role=status]').first().innerText().catch(() => '')));
    ok('dopo la prova si torna al riquadro', p.url().endsWith('#avvisi'));

    // Accesa
    await casella.check();
    await Promise.all([p.waitForNavigation(), riq.locator('button', { hasText: 'Salva gli avvisi' }).click()]);
    ok('salvata accesa: messaggio che avvisa del cliente', /un cliente/.test(await p.locator('[role=status]').first().innerText().catch(() => '')) && await p.locator('#avvisi_pulsante_pannello').isChecked());
    s = await srcdoc();
    ok('anteprima accesa: con il pulsante e l\'indirizzo del pannello', PULSANTE.test(s) && PANNELLO.test(s));
    if (FOTO) await riq.screenshot({ path: path.join(FOTO, 'avvisi-accesa.png') });
    quante = posta(POSTA).length;
    await Promise.all([p.waitForNavigation(), riq.locator('button', { hasText: 'Manda un avviso di prova' }).click()]);
    for (let i = 0; i < 20 && posta(POSTA).length === quante; i++) await dorme(250);
    pr = posta(POSTA).slice(quante);
    ok('prova accesa: con il pulsante', pr.length === 1 && PULSANTE.test(pr[0].html) && PANNELLO.test(pr[0].html));

    quante = posta(POSTA).length;
    cli('manda', PORTA, '10.8.' + Math.floor(Math.random() * 250) + '.1');
    const accesi = posta(POSTA).slice(quante).filter(m => m.a === 'negozio@esempio.it');
    ok('accesa: cinque avvisi', accesi.length === 5);
    for (const m of accesi) ok('accesa: pulsante e indirizzo del pannello come prima: ' + m.oggetto, PULSANTE.test(m.html) && PANNELLO.test(m.html));
    const cliente = posta(POSTA).slice(quante).filter(m => m.a !== 'negozio@esempio.it');
    ok('l\'email al cliente della Vetrina non ha mai l\'indirizzo del pannello', cliente.length === 1 && !PANNELLO.test(cliente[0].html));

    // Di nuovo spenta, dal pannello
    await p.locator('#avvisi_pulsante_pannello').uncheck();
    await Promise.all([p.waitForNavigation(), riq.locator('button', { hasText: 'Salva gli avvisi' }).click()]);
    ok('salvata spenta di nuovo', !(await p.locator('#avvisi_pulsante_pannello').isChecked()) && !PULSANTE.test(await srcdoc()));
    quante = posta(POSTA).length;
    cli('manda', PORTA, '10.7.' + Math.floor(Math.random() * 250) + '.1');
    const dinuovo = posta(POSTA).slice(quante).filter(m => m.a === 'negozio@esempio.it');
    ok('spenta di nuovo: cinque avvisi senza indirizzo del pannello', dinuovo.length === 5 && dinuovo.every(m => !PANNELLO.test(m.html) && !PANNELLO.test(m.testo)));
    ok('nessun errore JavaScript', err.length === 0, err);

    // 390 px
    const t = await br.newContext({ viewport: { width: 390, height: 844 }, storageState: await c.storageState() }); const tp = await t.newPage();
    await tp.goto(B + '/admin/impostazioni.php#avvisi', { waitUntil: 'networkidle' });
    const larga = await tp.evaluate(() => document.documentElement.scrollWidth);
    ok('390 px: niente scorrimento di lato', larga <= 391, larga);
    const bt = await tp.locator('#avvisi button.btn-primary').boundingBox();
    ok('390 px: pulsante Salva almeno 44 px di altezza', bt && bt.height >= 44, bt);
    if (FOTO) await tp.locator('#avvisi').screenshot({ path: path.join(FOTO, 'avvisi-390.png') });
  } finally {
    await br.close(); smtp.kill();
  }
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
