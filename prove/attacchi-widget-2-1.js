// Attacchi ai widget e ai campi della 2.1. Ogni OK è un attacco respinto.
//  - XSS in ogni campo dei widget nuovi e delle opzioni nuove (pagina /widget-2-1-cattivi/): niente script, gestori o link
//    javascript:/data: nell'HTML, nessuna finestra di alert;
//  - tabella: [x](javascript:...) e [x](data:...) restano testo;
//  - contenuto esterno: un dominio non ammesso, un sottodominio finto, un iframe incollato non diventano un frame, e la CSP
//    non si apre a loro; sicurezza_csp_pagina ammette solo frame-src e solo https://host;
//  - richiesta di prenotazione: configurazione firmata cambiata (destinatario dell'attaccante), firma inventata, campo esca,
//    date nel passato, troppe persone, a capo nell'email, pagina di ritorno verso un altro sito;
//  - form con campo File: .php, .pdf.php, .php rinominato .pdf, PDF con codice dentro, SVG e HTML, file troppo grande,
//    file buono senza captcha (nessun file resta sul disco, nessun invio salvato);
//  - scarico: senza login, con un invio che punta fuori dalla cartella (../ e percorso assoluto), cartella dei file dal sito.
// Prima: php prove/pagina-widget-2-1.php <cartella CMS> cattivi
// uso: node prove/attacchi-widget-2-1.js <base> <cartella CMS> <utente> <password> <cartella posta>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, POSTA] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, 'widget-2-1-cli.php'), DIR, ...a], { encoding: 'utf8' }));
const quantePoste = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).length;

(async () => {
  cli('svuota-limiti');
  const br = await chromium.launch();
  const ctx = await br.newContext();
  await ctx.route(u => !u.href.startsWith(B), r => r.abort());
  const p = await ctx.newPage();
  const dialoghi = []; p.on('dialog', d => { dialoghi.push(d.message()); d.dismiss(); });

  // ---- XSS nei widget
  const r = await p.goto(B + '/widget-2-1-cattivi/', { waitUntil: 'networkidle' });
  const html = await (await p.request.get(B + '/widget-2-1-cattivi/')).text();
  await p.waitForTimeout(500);
  ok('pagina cattiva: risponde 200', r.status() === 200);
  const corpo = html.replace(/<script type="application\/ld\+json">[\s\S]*?<\/script>/g, '');
  ok('XSS: nessun <img src=x> né <script>alert scritti come codice', !/<img src=x/i.test(corpo) && !/<script>alert/i.test(corpo));
  const attr = await p.evaluate(() => [...document.querySelectorAll('*')].flatMap(e => [...e.attributes].filter(a => /^on/i.test(a.name)).map(a => e.tagName + ' ' + a.name)));
  ok('XSS: nessun gestore on* nel documento', attr.length === 0, attr);
  const href = await p.evaluate(() => [...document.querySelectorAll('[href],[src],[data-src]')].map(e => e.getAttribute('href') || e.getAttribute('src') || e.getAttribute('data-src')).filter(u => /^\s*(javascript|data|vbscript):/i.test(u)));
  ok('XSS: nessun link o sorgente javascript: o data:', href.length === 0, href);
  ok('XSS: nessuna finestra di alert', dialoghi.length === 0, dialoghi);
  const ld = await p.locator('script[type="application/ld+json"]').evaluateAll(l => l.map(s => s.textContent));
  ok('XSS: i dati per Google non chiudono lo script (JSON_HEX_TAG)', ld.every(t => !/<\/script|<img|<script/i.test(t)) && ld.every(t => { try { JSON.parse(t); return true; } catch (e) { return false; } }));
  ok('tabella: [x](javascript:...) e [x](data:...) restano testo', await p.locator('.ed-w-tabella a').count() === 0 && /clic/.test(await p.locator('.ed-w-tabella').first().innerText()));
  ok('video: un indirizzo con "onload=" dentro dà solo l\'id del video', !/onload/i.test(corpo.match(/<div class="ed-video[^>]*>/g)?.join('') || '') && /youtube-nocookie\.com\/embed\/dQw4w9WgXcQ\?/.test(corpo));

  // ---- Contenuto esterno
  ok('contenuto esterno: nessun indirizzo non ammesso diventa un frame (dominio estraneo, sottodominio finto, javascript:, iframe incollato)', await p.locator('[data-ew-est]').count() === 0 && !/cattivo\.example/.test(corpo.replace(/<div class="ed-vuoto">[^<]*<\/div>/g, '')) && await p.locator('iframe:not([src^="https://www.youtube-nocookie.com/embed/"])').count() === 0);
  const csp = r.headers()['content-security-policy'] || '';
  ok('contenuto esterno: la CSP della pagina cattiva non si apre a domini estranei (né a Calendly, che non c\'è)', !/cattivo|calendly/.test(csp), csp.slice(-200));
  const cp = cli('csp-prova');
  ok('CSP: solo frame-src e solo https://host (niente script-src, http, percorsi, ";", *)', JSON.stringify(cp) === JSON.stringify({ 'frame-src': ['https://calendly.com'] }), cp);

  // ---- Richiesta di prenotazione
  const nuovi = async u => { await p.goto(B + u, { waitUntil: 'networkidle' }); };
  await nuovi('/widget-2-1/');
  const cfg = await p.locator('.ew-rp input[name=rp_cfg]').first().inputValue();
  const firma = await p.locator('.ew-rp input[name=rp_firma]').first().inputValue();
  const w = await p.locator('.ew-rp input[name=rp_w]').first().inputValue();
  const domani = new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10);
  const base = { modulo: 'prenota', pagina: '/widget-2-1/', rp_w: w, rp_cfg: cfg, rp_firma: firma, data: domani, ora: '20:00', persone: '2', nome: 'Mario', email: 'mario@persona.it', telefono: '+39 333 1234567', privacy: '1', captcha: '' };
  const manda = async (campi) => { const x = await p.request.post(B + '/api/modulo.php', { form: { ...base, ...campi }, maxRedirects: 0 }); return x.headers().location || ''; };
  const poste = quantePoste();
  const cambiata = Buffer.from(JSON.stringify({ ...JSON.parse(Buffer.from(cfg, 'base64').toString()), a: ['attaccante@cattivo.example'] })).toString('base64');
  ok('prenotazione: configurazione cambiata (il destinatario dell\'attaccante) con la firma vera: rifiutata', /prenota=robot/.test(await manda({ rp_cfg: cambiata })));
  ok('prenotazione: firma inventata: rifiutata', /prenota=robot/.test(await manda({ rp_firma: 'a'.repeat(64) })));
  ok('prenotazione: senza configurazione: rifiutata', /prenota=robot/.test(await manda({ rp_cfg: '', rp_firma: '' })));
  ok('prenotazione: campo esca compilato: rifiutata', /prenota=robot/.test(await manda({ sito_web: 'http://spam.example' })));
  ok('prenotazione: data nel passato: rifiutata', /prenota=data/.test(await manda({ data: '2020-01-01' })));
  ok('prenotazione: più persone del massimo: rifiutata', /prenota=persone/.test(await manda({ persone: '999' })));
  ok('prenotazione: a capo e Bcc nell\'email: rifiutata', /prenota=email/.test(await manda({ email: 'a@b.it\r\nBcc: spia@cattivo.example' })));
  ok('prenotazione: ora sbagliata: rifiutata', /prenota=ora/.test(await manda({ ora: '25:99' })));
  ok('prenotazione: senza captcha: rifiutata', /prenota=captcha/.test(await manda({})));
  const fuori = await manda({ pagina: '//cattivo.example/x', rp_cfg: '' });
  ok('prenotazione: la pagina di ritorno non porta su un altro sito', fuori.startsWith('/?') || fuori.startsWith('/#') || fuori.startsWith('/'), fuori);
  ok('prenotazione: il ritorno resta nel sito anche con "//"', !/^\/\//.test(fuori) && !/cattivo/.test(fuori), fuori);
  ok('prenotazione: nessuna email partita con le richieste rifiutate', quantePoste() === poste);

  // ---- Form con campo File
  const f = cli('form-prova').id;
  const prima = cli('cartella-file').file.length;
  const ultimo = (cli('invii', '1')[0] || {}).id;
  const carica = async (nome, tipo, dati, extra = {}) => {
    const x = await p.request.post(B + '/api/modulo.php', { multipart: { modulo: 'form:' + f, pagina: '/contatti/', c_email: 'a@persona.it', c_documento: { name: nome, mimeType: tipo, buffer: Buffer.from(dati) }, privacy: '1', captcha: '', ...extra }, maxRedirects: 0 });
    return decodeURIComponent(x.headers().location || '');
  };
  const php = '<?php system($_GET["c"]); ?>';
  ok('file: shell.php: rifiutato', /file_tipo:Documento/.test(await carica('shell.php', 'application/x-php', php)));
  ok('file: shell.pdf.php: rifiutato', /file_tipo:Documento/.test(await carica('shell.pdf.php', 'application/pdf', '%PDF-1.4 ' + php)));
  ok('file: .php rinominato .pdf: rifiutato', /file_tipo:Documento/.test(await carica('fattura.pdf', 'application/pdf', php)));
  ok('file: PDF vero con codice PHP dentro: rifiutato', /file_tipo:Documento/.test(await carica('doppio.pdf', 'application/pdf', '%PDF-1.4\n' + php + '\n%%EOF')));
  ok('file: PDF con <script> dentro: rifiutato', /file_tipo:Documento/.test(await carica('script.pdf', 'application/pdf', '%PDF-1.4\n<script>alert(1)</script>\n%%EOF')));
  ok('file: SVG e HTML (tipi non ammessi): rifiutati', /file_tipo/.test(await carica('a.svg', 'image/svg+xml', '<svg onload=alert(1)>')) && /file_tipo/.test(await carica('a.html', 'text/html', '<script>alert(1)</script>')));
  ok('file: JPG finto (testo con estensione .jpg): rifiutato', /file_tipo:Documento/.test(await carica('foto.jpg', 'image/jpeg', 'non sono una foto')));
  ok('file: troppo grande (oltre 1 MB): rifiutato', /file_peso:Documento/.test(await carica('grande.pdf', 'application/pdf', '%PDF-1.4\n' + 'x'.repeat(1100 * 1024))));
  ok('file: due file nello stesso campo (array): non passa', /campo:Documento/.test(decodeURIComponent((await p.request.post(B + '/api/modulo.php', { multipart: { modulo: 'form:' + f, pagina: '/', c_email: 'a@b.it', 'c_documento[]': { name: 'a.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4') }, privacy: '1' }, maxRedirects: 0 })).headers().location || '')));
  ok('file: un PDF buono senza captcha: rifiutato', /modulo=captcha/.test(await carica('buono.pdf', 'application/pdf', '%PDF-1.4\n1 0 obj << >> endobj\n%%EOF')));
  ok('file: nessuno di questi è rimasto sul disco né è stato salvato come invio', cli('cartella-file').file.length === prima && (cli('invii', '1')[0] || {}).id === ultimo);
  cli('form-prova', 'togli');

  // ---- Scarico
  const anonimo = await br.newContext();
  const dir = cli('cartella-file');
  ok('scarico: la cartella dei file dei form dal sito: 403', (await anonimo.request.get(B + '/' + dir.url + '/')).status() === 403 && (await anonimo.request.get(B + '/' + dir.url + '/index.html')).status() === 403);
  const cattivo = cli('invio-cattivo').id;
  const s0 = await anonimo.request.get(B + '/admin/form-submissions.php?file=' + cattivo + '&n=0', { maxRedirects: 0 });
  ok('scarico: senza login niente (rimanda al login)', [302, 303].includes(s0.status()) && !(await s0.text()).includes('DB_'), s0.status());
  await anonimo.close();
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  const s1 = await p.request.get(B + '/admin/form-submissions.php?file=' + cattivo + '&n=0');
  const s2 = await p.request.get(B + '/admin/form-submissions.php?file=' + cattivo + '&n=1');
  ok('scarico: un invio che punta fuori dalla cartella (../includes/config.local.php, /etc/passwd) dà 404 anche all\'amministratore', s1.status() === 404 && s2.status() === 404 && !/DB_PASS|root:/.test((await s1.text()) + (await s2.text())));
  const s3 = await p.request.get(B + '/admin/form-submissions.php?file=' + cattivo + '&n=../../x');
  ok('scarico: un numero di file che non è un numero: 404', s3.status() === 404);
  ok('pannello: l\'invio cattivo non mostra link di scarico', await (async () => { await p.goto(B + '/admin/form-submissions.php', { waitUntil: 'networkidle' }); return await p.locator(`a.fs-file[href*="file=${cattivo}&"]`).count() === 0; })());
  await p.goto(B + '/admin/form-submissions.php', { waitUntil: 'networkidle' });
  await p.locator('tr', { has: p.locator(`button.ct-rosso[data-uno="${cattivo}"]`) }).hover();
  p.removeAllListeners('dialog'); p.on('dialog', d => d.accept());
  await Promise.all([p.waitForNavigation(), p.locator(`button.ct-rosso[data-uno="${cattivo}"]`).click()]);
  ok('scarico: eliminando l\'invio cattivo non si cancella niente fuori dalla cartella', fs.existsSync(path.join(DIR, 'includes', 'config.local.php')));
  cli('svuota-limiti');
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
