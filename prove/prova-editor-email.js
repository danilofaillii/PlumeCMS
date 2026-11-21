// 2.12.0, fase 5 del piano di docs-sviluppo/brevo-confronto.md: l'editor delle email a blocchi, i modelli e le sezioni
// salvate, provati come un utente.
// Le cinque strade del design; ogni blocco e i suoi controlli; trascinamento e tastiera; colonne; annulla e ripeti;
// salvataggio automatico; tela al telefono; stile globale; anteprima; salva come modello e riusa; galleria; modelli
// (attivo, rinomina, duplica, elimina); sezione salvata sincronizzata cambiata nelle bozze e non nelle inviate; piede
// rimesso; invio vero al server di posta finto con l'HTML controllato da un validatore per la posta; foto delle email a
// 600 e 390 px; modello di doppio opt-in (e i form con la loro email di conferma che la tengono); 390 px senza
// scorrimento di lato.
// uso: node prove/prova-editor-email.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella CMS> [cartella foto]
// Il server di posta finto: python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> &
// Lascia i dati di prova (EE ..., CR ...): va su un sito da buttare.
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const [B, UTENTE, PASSWORD, SMTP, POSTA, DIR, FOTO] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (!c && extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 500) : '')); };
const php = (file, ...a) => JSON.parse(execFileSync('php', [path.join(__dirname, file), DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 << 20 }).trim().split('\n').pop());
const cr = (...a) => php('campagne-cli.php', ...a);
const ee = (...a) => php('editor-email-cli.php', ...a);
const q = (sql) => cr('sql', sql);
const uno = (sql) => { const r = q(sql); return r && r[0] ? Object.values(r[0])[0] : null; };
const num = (sql) => +uno(sql);
if (FOTO) fs.mkdirSync(FOTO, { recursive: true });

// ------------------------------------------------------------ il validatore per la posta
// Solo tabelle per l'impaginazione, stili in linea, larghezza del corpo, immagini con alt e misure, niente script né gestori.
function validaPosta(html, larghezza = 600) {
  const male = [];
  const tag = (re) => html.match(re) || [];
  if (/<script\b/i.test(html)) male.push('script');
  if (/\son[a-z]+\s*=/i.test(html)) male.push('gestore on...=');
  if (/javascript:|vbscript:|data:text/i.test(html)) male.push('link pericoloso');
  if (/<link\b/i.test(html)) male.push('foglio esterno');
  for (const t of ['section', 'article', 'header', 'footer', 'nav', 'main', 'aside', 'figure', 'iframe', 'form', 'input', 'button', 'video', 'svg']) if (new RegExp('<' + t + '\\b', 'i').test(html)) male.push('<' + t + '>');
  // Le div: solo il testo di anteprima nascosto.
  tag(/<div\b[^>]*>/gi).forEach(d => { if (!/display:\s*none/i.test(d)) male.push('div di impaginazione: ' + d.slice(0, 80)); });
  // Fogli di stile: uno solo, nella testa, solo con la media query del telefono.
  const stili = tag(/<style\b[^>]*>([\s\S]*?)<\/style>/gi);
  if (stili.length > 1) male.push('più di un <style>');
  stili.forEach(s => { const dentro = s.replace(/<\/?style[^>]*>/gi, '').trim(); if (!/^@media[^{]+\{[\s\S]*\}$/.test(dentro)) male.push('<style> non solo media query'); });
  if (stili.length && html.indexOf('<style') > html.indexOf('</head>')) male.push('<style> fuori dalla testa');
  if (/display:\s*(flex|grid)|position:\s*(absolute|fixed)|float:/i.test(html)) male.push('impaginazione non da posta (flex, grid, position, float)');
  // Stili in linea su ogni testo.
  tag(/<(p|h1|h2|h3|ul|ol|a)\b[^>]*>/gi).forEach(e => { if (!/\sstyle="/.test(e)) male.push('senza stile in linea: ' + e.slice(0, 60)); });
  // Tabelle di impaginazione.
  tag(/<table\b[^>]*>/gi).forEach(t => { if (!/role="presentation"/.test(t)) male.push('tabella senza role=presentation'); });
  const corpo = (html.match(/<table[^>]*class="nd-corpo"[^>]*>/) || [''])[0];
  if (!new RegExp('width="' + larghezza + '"').test(corpo) || !new RegExp('max-width:' + larghezza + 'px').test(corpo)) male.push('corpo non largo ' + larghezza + ': ' + corpo);
  // Immagini: alt sempre, larghezza sempre (il pixel di apertura ha 1x1 e alt vuoto).
  tag(/<img\b[^>]*>/gi).forEach(i => { if (!/\salt="/.test(i)) male.push('immagine senza alt: ' + i.slice(0, 80)); if (!/\swidth="\d+"/.test(i)) male.push('immagine senza larghezza: ' + i.slice(0, 80)); });
  return male;
}
function leggi(eml) {
  const i = eml.search(/\r?\n\r?\n/);
  const testa = eml.slice(0, i).replace(/\r?\n[ \t]+/g, ' '); const corpo = eml.slice(i).replace(/^\r?\n\r?\n/, '');
  const h = (t, nome) => ((t.match(new RegExp('^' + nome + ':[ \\t]*(.*)$', 'mi')) || [])[1] || '').trim();
  const dec = s => s.replace(/=\?UTF-8\?B\?([^?]+)\?=/gi, (_, b) => Buffer.from(b, 'base64').toString('utf8'));
  const parte = (t, c) => /base64/i.test(h(t, 'Content-Transfer-Encoding')) ? Buffer.from(c.replace(/\s+/g, ''), 'base64').toString('utf8') : c;
  const ct = h(testa, 'Content-Type'); let html = '', testo = '';
  const b = (ct.match(/boundary="([^"]+)"/) || [])[1];
  if (b) for (const p of corpo.split('--' + b).slice(1)) { const j = p.search(/\r?\n\r?\n/); if (j < 0) continue; const pt = p.slice(0, j).trim(), pc = p.slice(j).trim(); if (/text\/plain/i.test(h(pt, 'Content-Type'))) testo = parte(pt, pc); else if (/text\/html/i.test(h(pt, 'Content-Type'))) html = parte(pt, pc); }
  else html = parte(testa, corpo);
  return { dest: h(testa, 'X-Destinatari'), oggetto: dec(h(testa, 'Subject')), html, testo };
}
const posta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => leggi(fs.readFileSync(path.join(POSTA, f), 'utf8')));

(async () => {
  cr('pulisci'); ee('pulisci');
  const S = cr('semina'); const L = S.liste, C = S.contatti;
  cr('smtp', SMTP);
  q("DELETE FROM {p}settings WHERE setting_key = 'newsletter_indirizzo'");
  q("INSERT INTO {p}settings (setting_key, setting_value) VALUES ('newsletter_indirizzo', 'EE Studio Prova srl\nVia Roma 1, Milano')");
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(p.url().replace(B, '') + ' ' + e.message.slice(0, 200)));
  pnConferme.on(p, d => d.accept());
  const vai = async (u) => p.goto(B + (u.startsWith('/') ? u : '/admin/' + u), { waitUntil: 'networkidle' });
  const invia = async (sel) => { await Promise.all([p.waitForNavigation(), p.click(sel)]); };
  const flash = async () => (await p.locator('.flash, [role=alert]').allInnerTexts()).join(' | ');
  const foto = async (nome, opz = {}) => { if (FOTO) await p.screenshot({ path: path.join(FOTO, nome + '.png'), ...opz }); };
  const D = () => p.evaluate(() => window.ndEditor.stato.d);
  const salvato = async () => { await p.click('#nd-salva'); await p.waitForFunction(() => !window.ndEditor.stato.sporco && !window.ndEditor.stato.salvando, null, { timeout: 8000 }).catch(() => {}); };
  const blocchi = (d) => d.sezioni.flatMap(s => s.colonne.flat());
  // I pulsanti di un blocco o di una sezione compaiono passandoci sopra, come per una persona.
  const az = async (tipo, id, a) => { const e = p.locator(`.nd-${tipo}[data-id="${id}"]`); await e.scrollIntoViewIfNeeded(); await e.hover(tipo === "s" ? { position: { x: 6, y: 6 } } : {}); await e.locator(`:scope > .nd-strumenti [data-nd-azione=${a}]`).click(); };
  const scegli = async (id) => { await p.locator(`.nd-b[data-id="${id}"]`).click({ position: { x: 5, y: 5 } }); await p.waitForSelector('#nd-p-modifica:not([hidden])'); };
  const controllo = (etichetta) => p.locator('#nd-controlli .ed-controllo').filter({ has: p.locator(`label:text-is("${etichetta}"), .ed-etichetta:text-is("${etichetta}"), .ed-interruttore > span:text-is("${etichetta}")`) });
  await vai('/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  // ===== Le cinque strade, l'editor a blocchi
  await vai('newsletter.php?crea=1'); await p.fill('#nl-crea-nome', 'EE Blocchi'); await invia('#nl-crea button[type=submit]');
  const c1 = +new URL(p.url()).searchParams.get('id');
  q(`UPDATE {p}newsletter_campagne SET liste = '${L['CR Alfa']}', oggetto = 'EE Ciao {{ contact.NOME | default:"amico" }}' WHERE id = ${c1}`);
  await vai(`newsletter-campagna.php?id=${c1}&apri=design`);
  const strade = await p.locator('#sez-design .nd-strada b').allInnerTexts();
  ok('Design: cinque strade (editor a blocchi, un tuo modello, galleria, editor semplice, codice HTML)', JSON.stringify(strade) === JSON.stringify(['Editor a blocchi', 'Un tuo modello', 'La galleria', 'Editor semplice', 'Codice HTML']), strade);
  ok('una campagna nuova parte con l\'editor semplice (in uso)', /In uso/.test(await p.locator('#sez-design .nd-strada.nd-in-uso').innerText()) && /Editor semplice/.test(await p.locator('#sez-design .nd-strada.nd-in-uso').innerText()));
  await foto('01-design-strade', { fullPage: true });
  await invia('#sez-design form.nd-strada:has(input[name=editor][value=blocchi]) button');
  ok('«Usa l\'editor a blocchi» apre l\'editor', p.url().includes('newsletter-design.php?campagna=' + c1), p.url());
  let r = ee('design', c1);
  ok('la campagna passa all\'editor a blocchi con un design di partenza (titolo, testo, pulsante, piede)', r.editor === 'blocchi' && JSON.stringify(blocchi(r.design).map(b => b.t)) === '["titolo","testo","pulsante","piede"]', r && r.editor);
  await p.waitForSelector('.nd-b');
  await foto('02-editor');

  // ===== Schede del pannello, tavolozza
  const schede = await p.locator('[role=tab]').allInnerTexts();
  ok('pannello: schede Contenuti, Modifica, Stile, Salvati (role=tab)', JSON.stringify(schede) === '["Contenuti","Modifica","Stile","Salvati"]', schede);
  await p.focus('#nd-scheda-contenuti'); await p.keyboard.press('ArrowRight');
  ok('le frecce passano da una scheda all\'altra (aria-selected)', await p.getAttribute('#nd-scheda-modifica', 'aria-selected') === 'true' && !(await p.locator('#nd-p-modifica').isHidden()));
  await p.click('#nd-scheda-contenuti');
  const tav = await p.locator('#nd-tavolozza-blocchi .ed-widget-voce span').allInnerTexts();
  ok('nove blocchi: titolo, testo, immagine, pulsante, divisore, spaziatore, social, video, piede', JSON.stringify(tav) === '["Titolo","Testo","Immagine","Pulsante","Divisore","Spaziatore","Social","Video","Piede"]', tav);
  ok('sette sezioni a colonne (1, 2, 3 colonne e proporzioni)', await p.locator('#nd-tavolozza-sezioni .ed-widget-voce').count() === 7);
  ok('le voci della tavolozza si usano da tastiera (role=button, tabindex=0)', await p.locator('#nd-tavolozza-blocchi [role=button][tabindex="0"]').count() === 9);

  // ===== Tastiera: seleziona il titolo con Invio, aggiunge un titolo dalla tavolozza con Invio
  let d = await D();
  const tit0 = blocchi(d)[0].id;
  await p.focus(`.nd-b[data-id="${tit0}"]`); await p.keyboard.press('Enter');
  ok('Invio su un blocco della tela lo apre in Modifica', /Blocco: Titolo/.test(await p.locator('#nd-modifica-nome').innerText()));
  await p.click('#nd-scheda-contenuti');
  await p.focus('#nd-tavolozza-blocchi [data-blocco=titolo]'); await p.keyboard.press('Enter');
  d = await D();
  ok('Invio sulla voce Titolo aggiunge un titolo subito dopo quello selezionato', blocchi(d).length === 5 && blocchi(d)[1].t === 'titolo');
  const tit1 = blocchi(d)[1].id;
  // Alt+freccia su: il titolo nuovo sale sopra il primo.
  await p.focus(`.nd-b[data-id="${tit1}"]`); await p.keyboard.press('Alt+ArrowUp');
  d = await D();
  ok('Alt+Freccia su sposta il blocco da tastiera', blocchi(d)[0].id === tit1);
  await az("b", tit1, "giu");
  ok('il pulsante ↓ del blocco lo riporta giù', blocchi(await D())[1].id === tit1);

  // ===== Ogni blocco e ogni controllo
  await scegli(tit1);
  await controllo('Testo').locator('input').fill('EE Titolo grande');
  await controllo('Grandezza').locator('select').selectOption('h2');
  await controllo('Allineamento').locator('select').selectOption('center');
  await controllo('Colore del testo (vuoto: quello dello stile)').locator('input[type=text]').fill('#123456');
  await controllo('Spazio sotto (px)').locator('input[type=number]').fill('20');
  let st = await p.locator(`.nd-b[data-id="${tit1}"] h2`).evaluate(e => ({ t: e.textContent, c: getComputedStyle(e).color, a: getComputedStyle(e).textAlign }));
  ok('titolo: testo, grandezza H2, centro e colore si vedono subito nella tela', st.t === 'EE Titolo grande' && st.c === 'rgb(18, 52, 86)' && st.a === 'center', st);
  const aggiungi = async (t) => { await p.click('#nd-scheda-contenuti'); await p.click(`#nd-tavolozza-blocchi [data-blocco=${t}]`); const sel = await p.evaluate(() => window.ndEditor.stato.sel); return blocchi(await D()).find(b => b.id === sel); };
  // testo
  let b = await aggiungi('testo');
  ok('clic sulla voce Testo: il blocco entra e si apre in Modifica', b && b.t === 'testo' && /Blocco: Testo/.test(await p.locator('#nd-modifica-nome').innerText()));
  await p.locator('#nd-controlli .ql-editor').click(); await p.keyboard.press('Control+A');
  await p.keyboard.type('EE Ciao {{ contact.NOME }}, ');
  await p.keyboard.press('Control+B'); await p.keyboard.type('in grassetto'); await p.keyboard.press('Control+B');
  await controllo('Allineamento').locator('select').selectOption('right');
  await controllo('Dimensione (px, vuoto: quella dello stile)').locator('input').fill('18');
  const idTesto = b.id;
  st = await p.locator(`.nd-b[data-id="${idTesto}"] .nd-testo`).evaluate(e => ({ h: e.innerHTML, f: getComputedStyle(e).fontSize }));
  ok('testo: formattazione (grassetto), segnaposto e dimensione nella tela', /<strong>in grassetto<\/strong>/.test(st.h) && /\{\{ contact\.NOME \}\}/.test(st.h) && st.f === '18px', st);
  // immagine: dai Media, caricando un file
  b = await aggiungi('immagine'); const idImg = b.id;
  ok('immagine vuota: la tela dice di sceglierla', /sceglila nel pannello/.test(await p.locator(`.nd-b[data-id="${idImg}"]`).innerText()));
  const png = path.join(require('os').tmpdir(), 'ee-prova-' + process.pid + '.png'); ee('png', png, 800, 400);
  await controllo('Immagine').locator('button.nd-media').click();
  await p.waitForSelector('dialog.pn-media[open]');
  await p.locator('dialog.pn-media .pn-media-file').setInputFiles(png);
  await p.waitForFunction(id => { const x = window.ndEditor.trova(id); return x && x.b.src && x.b.w > 0; }, idImg, { timeout: 15000 }).catch(() => {});
  b = blocchi(await D()).find(x => x.id === idImg);
  ok('immagine: Scegli dai Media, caricata e scelta; indirizzo intero e misure (800x400) salvate', /^https?:\/\/.+\/newsletter\/\d{4}\/\d{2}\/.+\.png$/.test(b.src) && b.w === 800 && b.h === 400, b);
  await controllo('Testo alternativo (lo legge chi non vede le immagini)').locator('input').fill('EE foto di prova');
  await controllo('Larghezza (% della colonna)').locator('input[type=number]').fill('80');
  await controllo('Link (facoltativo)').locator('input[type=text]').first().fill('https://esempio.it/foto');
  await controllo('Angoli (px)').locator('input[type=number]').fill('6');
  st = await p.locator(`.nd-b[data-id="${idImg}"] img`).evaluate(e => ({ alt: e.alt, w: e.style.width, r: e.style.borderRadius }));
  ok('immagine: alt, larghezza 80% e angoli nella tela', st.alt === 'EE foto di prova' && st.w === '80%' && st.r === '6px', st);
  // pulsante
  b = await aggiungi('pulsante'); const idBtn = b.id;
  await controllo('Testo del pulsante').locator('input').fill('EE Prenota');
  await controllo('Link').locator('input[type=text]').first().fill('https://esempio.it/prenota');
  await controllo('Sfondo (vuoto: quello dello stile)').locator('input[type=text]').fill('#0a7d4f');
  await controllo('Testo (vuoto: quello dello stile)').locator('input[type=text]').fill('#ffffff');
  await controllo('Angoli (px, vuoto: quelli dello stile)').locator('input').fill('20');
  await controllo('Largo quanto la colonna').locator('input').check();
  st = await p.locator(`.nd-b[data-id="${idBtn}"] .nd-pulsante`).evaluate(e => ({ t: e.textContent, bg: getComputedStyle(e).backgroundColor, r: e.style.borderRadius, d: e.style.display }));
  ok('pulsante: testo, sfondo, angoli e larghezza piena nella tela', st.t === 'EE Prenota' && st.bg === 'rgb(10, 125, 79)' && st.r === '20px' && st.d === 'block', st);
  // i link speciali
  await controllo('Link').locator('.nd-speciali button', { hasText: 'per uscire' }).click();
  ok('link speciali: «per uscire» scrive {{ unsubscribe }} nel link del pulsante', (blocchi(await D()).find(x => x.id === idBtn) || {}).link === '{{ unsubscribe }}');
  await controllo('Link').locator('input[type=text]').first().fill('https://esempio.it/prenota');
  // divisore, spaziatore
  b = await aggiungi('divisore'); const idDiv = b.id;
  await controllo('Colore').locator('input[type=text]').fill('#ff0000');
  await controllo('Spessore (px)').locator('input[type=number]').fill('3');
  await controllo('Linea').locator('select').selectOption('dashed');
  await controllo('Larghezza (%)').locator('input[type=number]').fill('50');
  st = await p.locator(`.nd-b[data-id="${idDiv}"] .nd-b-dentro > div`).evaluate(e => e.style.borderTop + ' ' + e.style.width);
  ok('divisore: colore, spessore, linea a trattini e larghezza', /3px dashed/.test(st) && /255, 0, 0|#ff0000/.test(st) && /50%/.test(st), st);
  b = await aggiungi('spaziatore'); const idSpa = b.id;
  await controllo('Altezza (px)').locator('input[type=number]').fill('40');
  ok('spaziatore: altezza 40 px', await p.locator(`.nd-b[data-id="${idSpa}"] .nd-spaziatore`).evaluate(e => e.offsetHeight) === 40);
  // social
  b = await aggiungi('social'); const idSoc = b.id;
  const reti = p.locator('#nd-controlli .nd-rete');
  await reti.nth(0).locator('input').fill('https://www.facebook.com/eeprova');
  await reti.nth(1).locator('input').fill('https://www.instagram.com/eeprova');
  await p.click('#nd-controlli button:has-text("Aggiungi una rete")');
  await p.locator('#nd-controlli .nd-rete').nth(2).locator('input').fill('https://www.linkedin.com/company/eeprova');
  await controllo('Icone').locator('select').selectOption('scuro');
  await controllo('Grandezza delle icone (px)').locator('input[type=number]').fill('40');
  st = await p.locator(`.nd-b[data-id="${idSoc}"] img`).evaluateAll(l => l.map(i => i.alt + ':' + i.getAttribute('src').split('/').pop() + ':' + i.width));
  ok('social: tre reti con le icone scure da 40 px', JSON.stringify(st) === '["Facebook:facebook-scuro.png:40","Instagram:instagram-scuro.png:40","LinkedIn:linkedin-scuro.png:40"]', st);
  // video
  b = await aggiungi('video'); const idVid = b.id;
  await controllo('Link del video (YouTube, Vimeo o il tuo sito)').locator('input[type=text]').first().fill('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
  await controllo('Testo del link sotto').locator('input').fill('EE Guarda il video');
  st = await p.locator(`.nd-b[data-id="${idVid}"]`).evaluate(e => ({ src: (e.querySelector('img') || {}).src || '', t: e.innerText }));
  ok('video: la miniatura di YouTube arriva da sola, con il link sotto', /img\.youtube\.com\/vi\/dQw4w9WgXcQ/.test(st.src) && /EE Guarda il video/.test(st.t), st);
  // piede in più (poi tolto): i controlli ci sono
  b = await aggiungi('piede');
  ok('piede: testo, indirizzo dell\'azienda, colore, dimensione', await controllo('Mostra l\'indirizzo dell\'azienda (da Impostazioni della newsletter)').count() === 1 && await controllo('Dimensione (px)').count() === 1 && /EE Studio Prova srl/.test(await p.locator(`.nd-b[data-id="${b.id}"]`).innerText()));
  await az("b", b.id, "elimina");
  ok('✕ elimina il blocco', !(blocchi(await D()).some(x => x.id === b.id)));
  await foto('03-blocchi');

  // ===== Salvataggio automatico
  await p.waitForFunction(() => /Salvato alle/.test(document.getElementById('nd-stato').textContent), null, { timeout: 10000 }).catch(() => {});
  ok('salvataggio automatico: «Salvato alle ...» senza premere niente', /Salvato alle/.test(await p.locator('#nd-stato').innerText()), await p.locator('#nd-stato').innerText());
  r = ee('design', c1);
  const bb = Object.fromEntries(blocchi(r.design).map(x => [x.id, x]));
  ok('nel database: titolo, testo, immagine, pulsante, divisore, spaziatore, social, video con i valori scelti',
    bb[tit1].testo === 'EE Titolo grande' && bb[tit1].livello === 'h2' && bb[tit1].colore === '#123456' && bb[tit1].spazio === 20
    && /<strong>in grassetto<\/strong>/.test(bb[idTesto].html) && bb[idTesto].allinea === 'right' && bb[idTesto].dim === 18
    && bb[idImg].alt === 'EE foto di prova' && bb[idImg].larghezza === 80 && bb[idImg].link === 'https://esempio.it/foto' && bb[idImg].raggio === 6
    && bb[idBtn].fondo === '#0a7d4f' && bb[idBtn].raggio === 20 && bb[idBtn].pieno === true && bb[idBtn].link === 'https://esempio.it/prenota'
    && bb[idDiv].stile === 'dashed' && bb[idDiv].spessore === 3 && bb[idDiv].larghezza === 50 && bb[idSpa].altezza === 40
    && bb[idSoc].reti.length === 3 && bb[idSoc].stile === 'scuro' && bb[idSoc].dim === 40 && bb[idVid].url.includes('dQw4w9WgXcQ'), Object.values(bb).map(x => x.t));

  // ===== Annulla e ripeti
  const prima = blocchi(await D()).length;
  await p.click('#nd-annulla');
  ok('Annulla rimette il blocco eliminato', blocchi(await D()).length === prima + 1);
  await p.click('#nd-ripeti');
  ok('Ripeti lo toglie di nuovo', blocchi(await D()).length === prima);
  await p.locator('#nd-tela').click({ position: { x: 3, y: 3 } }).catch(() => {});
  await p.keyboard.press('Control+z');
  ok('Ctrl+Z fa lo stesso di Annulla', blocchi(await D()).length === prima + 1);
  await p.keyboard.press('Control+y');

  // ===== Sezioni a colonne, trascinamento
  await p.click('#nd-scheda-contenuti');
  await p.click('#nd-tavolozza-sezioni [data-layout="33-33-33"]');
  d = await D();
  const s3 = d.sezioni.find(s => s.layout === '33-33-33');
  ok('sezione a tre colonne aggiunta dalla tavolozza', s3 && s3.colonne.length === 3);
  // La sezione sale in cima con il suo pulsante ↑ (così la tavolozza e la sezione si vedono insieme per trascinare).
  for (let i = 0; i < 6 && (await D()).sezioni[0].id !== s3.id; i++) await az("s", s3.id, "su");
  ok('il pulsante ↑ della sezione la porta in cima', (await D()).sezioni[0].id === s3.id);
  const trascina = async (da, a2) => {
    const p0 = await da.boundingBox(), p1 = await a2.boundingBox();
    await p.mouse.move(p0.x + p0.width / 2, p0.y + p0.height / 2); await p.mouse.down();
    await p.mouse.move(p0.x + p0.width / 2 + 8, p0.y + p0.height / 2 + 8, { steps: 4 });
    await p.mouse.move(p1.x + p1.width / 2, p1.y + p1.height / 2, { steps: 25 }); await p.waitForTimeout(250);
    await p.mouse.move(p1.x + p1.width / 2 + 2, p1.y + p1.height / 2 + 2, { steps: 2 }); await p.mouse.up(); await p.waitForTimeout(300);
  };
  await p.click('#nd-scheda-contenuti');
  await trascina(p.locator('#nd-tavolozza-blocchi [data-blocco=pulsante]'), p.locator(`.nd-s[data-id="${s3.id}"] .nd-c[data-col="1"]`));
  d = await D();
  let s3b = d.sezioni.find(s => s.id === s3.id);
  ok('trascinamento: il Pulsante dalla tavolozza nella seconda colonna', s3b.colonne[1].length === 1 && s3b.colonne[1][0].t === 'pulsante', s3b.colonne.map(c => c.length));
  await trascina(p.locator(`.nd-b[data-id="${tit0}"]`), p.locator(`.nd-s[data-id="${s3.id}"] .nd-c[data-col="2"]`));
  d = await D(); s3b = d.sezioni.find(s => s.id === s3.id);
  ok('trascinamento: un blocco della tela nella terza colonna', s3b.colonne[2].length === 1 && s3b.colonne[2][0].id === tit0, s3b.colonne.map(c => c.map(x => x.t)));
  // al telefono le colonne vanno una sotto l'altra
  await p.click('[data-nd-misura=tel]'); await p.waitForTimeout(450);
  const telw = await p.locator('#nd-tela').evaluate(e => e.offsetWidth);
  const imp = await p.locator(`.nd-s[data-id="${s3.id}"] .nd-c`).evaluateAll(l => l.map(c => Math.round(c.getBoundingClientRect().top)));
  ok('vista telefono: tela larga 390 px e colonne una sotto l\'altra', telw === 390 && imp[1] > imp[0] && imp[2] > imp[1], [telw, imp]);
  await foto('04-telefono');
  await p.click('[data-nd-misura=pc]');
  // layout da tre a due colonne: niente si perde
  await scegli(s3b.colonne[1][0].id); await p.click('#nd-controlli button:has-text("Seleziona la sua sezione")');
  await controllo('Colonne').locator('select').selectOption('50-50');
  d = await D(); s3b = d.sezioni.find(s => s.id === s3.id);
  ok('da tre a due colonne: i blocchi della terza finiscono nella seconda', s3b.colonne.length === 2 && s3b.colonne[1].length === 2, s3b.colonne.map(c => c.map(x => x.t)));
  await controllo('Sfondo della sezione').locator('input[type=text]').fill('#fff7e6');
  await controllo('Spazio sopra e sotto (px)').locator('input[type=number]').fill('30');
  ok('sezione: sfondo e spazio nella tela', await p.locator(`.nd-s[data-id="${s3.id}"]`).evaluate(e => getComputedStyle(e).backgroundColor + ' ' + e.style.paddingTop) === 'rgb(255, 247, 230) 30px');
  // duplica, nascondi, elimina la sezione
  const nSez = (await D()).sezioni.length;
  await az("s", s3.id, "duplica");
  d = await D();
  ok('⧉ duplica la sezione (con blocchi nuovi)', d.sezioni.length === nSez + 1 && d.sezioni[d.sezioni.findIndex(s => s.id === s3.id) + 1].colonne[1].length === 2);
  const copiaS = d.sezioni[d.sezioni.findIndex(s => s.id === s3.id) + 1].id;
  await p.locator(`.nd-s[data-id="${copiaS}"]`).click({ position: { x: 3, y: 3 } });
  await controllo('Nascondi la sezione (non esce nell\'email)').locator('input').check();
  ok('la sezione nascosta resta nella tela, più chiara', await p.locator(`.nd-s[data-id="${copiaS}"].nd-nascosta`).count() === 1);
  await salvato();
  let html = ee('html', c1, C.a1).html;
  ok('la sezione nascosta non esce nell\'email (una sola sezione con quello sfondo)', (html.match(/background:#fff7e6/g) || []).length === 1, (html.match(/background:#fff7e6/g) || []).length);

  // ===== Stile globale
  await p.click('#nd-scheda-stile');
  const stile = async (et, v) => { const c = p.locator('#nd-controlli-stile .ed-controllo').filter({ has: p.locator(`label:text-is("${et}")`) }); const sel = c.locator('select'); if (await sel.count()) await sel.selectOption(v); else await c.locator('input[type=text], input[type=number]').first().fill(v); };
  await stile('Sfondo intorno all\'email', '#e8eef5'); await stile('Sfondo del corpo', '#fffdf8'); await stile('Larghezza del corpo (px)', '560');
  await stile('Carattere del testo', 'georgia'); await stile('Colore dei link', '#9a3412'); await stile('Sfondo dei pulsanti', '#1d4ed8'); await stile('Angoli dei pulsanti (px)', '0'); await p.waitForTimeout(450);
  st = await p.evaluate(() => ({ fondo: getComputedStyle(document.getElementById('nd-fondo')).backgroundColor, corpo: getComputedStyle(document.getElementById('nd-tela')).backgroundColor, w: document.getElementById('nd-tela').offsetWidth, f: getComputedStyle(document.getElementById('nd-tela')).fontFamily }));
  ok('stile: sfondi, larghezza 560 e carattere Georgia nella tela', st.fondo === 'rgb(232, 238, 245)' && st.corpo === 'rgb(255, 253, 248)' && st.w === 560 && /Georgia/.test(st.f), st);
  ok('stile: i colori del sito come scorciatoia (campioni)', await p.locator('#nd-controlli-stile .nd-campione').count() > 0);
  await foto('05-stile');
  await salvato();
  html = ee('html', c1, C.a1).html;
  let v = validaPosta(html, 560);
  ok('HTML per la posta (larghezza 560): il validatore non trova niente', v.length === 0, v);
  ok('stile nell\'HTML: fondo, corpo, Georgia, pulsante con lo sfondo dello stile', /background:#e8eef5/.test(html) && /bgcolor="#fffdf8"/.test(html) && /font-family:Georgia/.test(html));
  await stile('Larghezza del corpo (px)', '600');
  await salvato();

  // ===== Anteprima
  await p.click('#nd-anteprima');
  const fr = p.frameLocator('#nd-anteprima-iframe');
  await fr.locator('text=EE Titolo grande').waitFor({ timeout: 8000 }).catch(() => {});
  ok('Anteprima: l\'email del server, con «Giulia» al posto del nome', await fr.locator('text=EE Titolo grande').count() === 1 && /Giulia/.test(await fr.locator('body').innerText()));
  await p.click('[data-nd-ant=tel]'); await p.waitForTimeout(450);
  ok('Anteprima: Telefono stringe l\'email a 390 px', await p.locator('#nd-anteprima-iframe').evaluate(e => e.offsetWidth) === 390);
  await foto('06-anteprima');
  await p.click('#nd-dlg-anteprima [data-chiudi-dialogo]');

  // ===== HTML per la posta, piede rimesso
  html = ee('html', c1, C.a1).html;
  v = validaPosta(html);
  ok('HTML per la posta (600): solo tabelle, stili in linea, immagini con alt e misure, niente script', v.length === 0, v);
  ok('segnaposto {{ contact.NOME }} diventato il nome (Anna)', /EE Ciao Anna, <strong/.test(html), (html.match(/EE Ciao[^<]{0,30}/) || [''])[0]);
  ok('immagine con alt, larghezza 80% della colonna (432 px) e altezza dalle proporzioni (216)', /<img class="nd-img" src="https?:[^"]+\.png" alt="EE foto di prova" width="432" height="216"/.test(html), (html.match(/<img class="nd-img"[^>]*>/) || [''])[0]);
  ok('pulsante a prova di Outlook: tabella con bgcolor e link', /<td align="center" bgcolor="#0a7d4f"[^>]*><a href="https:\/\/esempio\.it\/prenota"/.test(html));
  ok('video: miniatura di YouTube con il link al video', /<a href="https:\/\/www\.youtube\.com\/watch\?v=dQw4w9WgXcQ"[^>]*><img class="nd-img" src="https:\/\/img\.youtube\.com\/vi\/dQw4w9WgXcQ\/hqdefault\.jpg" alt="Guarda il video" width="\d+" height="\d+"/.test(html));
  ok('social: icone PNG con alt e misure', /<img src="[^"]+\/sito\/assets\/email\/linkedin-scuro\.png" alt="LinkedIn" width="40" height="40"/.test(html));
  ok('media query per il telefono: le colonne una sotto l\'altra', /@media only screen and \(max-width:620px\)\{[^<]*\.nd-impila \.nd-col\{display:block!important/.test(html));
  ok('il piede del design ha il link per uscire firmato e l\'indirizzo dell\'azienda', /href="[^"]*\/newsletter-esci\/\?i=\d+[^"]*"/.test(html) && (html.match(/EE Studio Prova srl/g) || []).length === 1);
  // via tutti i piedi: il link e l'indirizzo si rimettono da soli
  d = await D();
  for (const x of blocchi(d).filter(x => x.t === 'piede')) await az("b", x.id, "elimina");
  await salvato();
  html = ee('html', c1, C.a1).html;
  ok('senza piede: il link per uscire e l\'indirizzo dell\'azienda si rimettono da soli in fondo', /Esci dalla newsletter<\/a>, basta un clic\.<br>EE Studio Prova srl<br>Via Roma 1, Milano<\/td><\/tr><\/table>/.test(html) && /newsletter-esci\/\?i=/.test(html), html.slice(-500));
  ok('e l\'HTML resta valido per la posta', validaPosta(html).length === 0, validaPosta(html));
  await p.click('#nd-annulla');
  d = await D();
  ok('Annulla rimette i piedi tolti', blocchi(d).some(x => x.t === 'piede'));
  await salvato();

  // ===== Riepilogo: Design a posto, invio vero
  await vai(`newsletter-campagna.php?id=${c1}`);
  ok('riepilogo: Design «Editor a blocchi» a posto', /A posto/.test(await p.locator('#sez-design > summary').innerText()) && /Editor a blocchi/.test(await p.locator('#sez-design .nlr-riassunto').innerText()));
  for (const f of fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []) if (f.endsWith('.eml')) fs.unlinkSync(path.join(POSTA, f));
  await invia('#nlr-invio button.btn-primary');
  for (let i = 0; i < 4 && uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c1}`) !== 'inviata'; i++) cr('giro');
  const arr = posta();
  ok('invio vero: la campagna parte ai contatti della lista (a1, a2, a3, a5)', arr.length === 4 && uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c1}`) === 'inviata', arr.map(m => m.dest));
  const anna = arr.find(m => /a1@/.test(m.dest)) || { html: '', oggetto: '', testo: '' };
  v = validaPosta(anna.html);
  ok('email arrivata: il validatore per la posta non trova niente', anna.html.length > 1000 && v.length === 0, v);
  ok('email arrivata: oggetto e testo con il nome, link tracciati, pixel di apertura', anna.oggetto === 'EE Ciao Anna' && /EE Ciao Anna/.test(anna.html) && /api\/nl\.php\?/.test(anna.html) && /width="1" height="1" alt=""/.test(anna.html), anna.oggetto);
  ok('email arrivata: il testo semplice c\'è, con il link per uscire', /EE Titolo grande/.test(anna.testo) && /newsletter-esci/.test(anna.testo));
  if (FOTO) {
    // Le foto delle email a 600 e 390 px, da guardare a occhio.
    const fp = await br.newPage({ viewport: { width: 600, height: 900 } });
    for (const [w, nome] of [[600, 'email-600'], [390, 'email-390']]) {
      await fp.setViewportSize({ width: w, height: 900 }); await fp.setContent(anna.html, { waitUntil: 'load' }).catch(() => {});
      await fp.waitForTimeout(300); await fp.screenshot({ path: path.join(FOTO, nome + '.png'), fullPage: true });
      ok(`email a ${w} px: niente scorrimento di lato`, await fp.evaluate(() => document.documentElement.scrollWidth) <= w, await fp.evaluate(() => document.documentElement.scrollWidth));
    }
    await fp.close();
  }

  // ===== Salva come modello e riusa
  await vai('newsletter.php?crea=1'); await p.fill('#nl-crea-nome', 'EE Modello sorgente'); await invia('#nl-crea button[type=submit]');
  const c2 = +new URL(p.url()).searchParams.get('id');
  await vai(`newsletter-campagna.php?id=${c2}&apri=design`);
  await invia('#sez-design form.nd-strada:has(input[name=editor][value=blocchi]) button');
  await p.waitForSelector('.nd-b');
  const t2 = blocchi(await D())[0].id;
  await scegli(t2); await controllo('Testo').locator('input').fill('EE Titolo del modello');
  await p.click('#nd-come-modello');
  await p.fill('#nd-nome-campo', 'EE Mio modello');
  ok('Salva come modello: «Attiva subito» spuntato di partenza', await p.isChecked('#nd-nome-spunta'));
  await p.click('#nd-nome-ok');
  await p.waitForFunction(() => /Modello salvato/.test(document.getElementById('nd-stato').textContent), null, { timeout: 8000 }).catch(() => {});
  const m1 = num("SELECT id FROM {p}newsletter_modelli WHERE nome = 'EE Mio modello'");
  ok('il modello nasce attivo, con il design della campagna', m1 > 0 && num(`SELECT attivo FROM {p}newsletter_modelli WHERE id = ${m1}`) === 1 && blocchi(ee('design', 'm:' + m1).design)[0].testo === 'EE Titolo del modello');
  await vai('newsletter.php?crea=1'); await p.fill('#nl-crea-nome', 'EE Riuso'); await invia('#nl-crea button[type=submit]');
  const c3 = +new URL(p.url()).searchParams.get('id');
  await vai(`newsletter-campagna.php?id=${c3}&apri=design`);
  await p.selectOption('#nd-modello', String(m1));
  await invia('#sez-design form.nd-strada:has(input[value=usa_modello]) button');
  ok('Un tuo modello > Usa questo modello: la campagna prende il design e si apre l\'editor', p.url().includes('newsletter-design.php?campagna=' + c3) && blocchi(ee('design', c3).design)[0].testo === 'EE Titolo del modello' && num(`SELECT modello_id FROM {p}newsletter_campagne WHERE id = ${c3}`) === m1);
  await vai(`newsletter-campagna.php?id=${c3}&apri=design`);
  ok('il riepilogo dice da quale modello viene', /dal modello «EE Mio modello»/.test(await p.locator('#sez-design .nlr-riassunto').innerText()));

  // ===== Galleria
  await vai(`newsletter-modelli.php?scheda=galleria&campagna=${c3}`);
  const gal = await p.locator('.ndm-carta b').allInnerTexts();
  ok('galleria: sei modelli (benvenuto, newsletter del mese, offerta, evento, nuovo articolo, conferma dell\'iscrizione)', JSON.stringify(gal) === JSON.stringify(['Benvenuto', 'Newsletter del mese', 'Offerta', 'Evento', 'Nuovo articolo', 'Conferma dell\'iscrizione']), gal);
  ok('galleria: ogni modello ha l\'anteprima', await p.locator('.ndm-carta iframe').count() === 6);
  await foto('07-galleria', { fullPage: true });
  await invia('.ndm-carta:has(b:text-is("Offerta")) button:has-text("Usa in questa campagna")');
  ok('galleria > Usa in questa campagna: si apre l\'editor con il modello Offerta', p.url().includes('newsletter-design.php?campagna=' + c3) && /SCONTO20/.test(JSON.stringify(ee('design', c3).design)));
  await vai('newsletter-modelli.php?scheda=galleria');
  await invia('.ndm-carta:has(b:text-is("Evento")) button:has-text("Aggiungi ai miei modelli")');
  const mEv = num("SELECT id FROM {p}newsletter_modelli WHERE origine = '4-evento'");
  ok('galleria > Aggiungi ai miei modelli: il modello Evento è fra i tuoi, attivo', mEv > 0 && num(`SELECT attivo FROM {p}newsletter_modelli WHERE id = ${mEv}`) === 1);

  // ===== Modelli: elenco, attivo, rinomina, duplica, elimina
  await vai('newsletter-modelli.php');
  const riga = (nome) => p.locator('.ndm-tabella tbody tr').filter({ has: p.locator(`b:text-is("${nome}")`) });
  ok('I tuoi modelli: i due modelli con stato «Attivo» scritto (non solo a colori)', await riga('EE Mio modello').locator('.ndm-stato').innerText().then(t => /Attivo/.test(t)) && await riga('Evento').count() === 1);
  await invia(`${'.ndm-tabella tbody tr'}:has(b:text-is("EE Mio modello")) button:has-text("Rendi inattivo")`);
  ok('Rendi inattivo: il modello non è più attivo', num(`SELECT attivo FROM {p}newsletter_modelli WHERE id = ${m1}`) === 0 && /Inattivo/.test(await riga('EE Mio modello').locator('.ndm-stato').innerText()));
  await vai(`newsletter-campagna.php?id=${c3}&apri=design`);
  ok('un modello inattivo non si sceglie nelle campagne', await p.locator(`#nd-modello option[value="${m1}"]`).count() === 0);
  await vai('newsletter-modelli.php');
  await riga('EE Mio modello').locator('summary:text-is("Rinomina")').click();
  await riga('EE Mio modello').locator('input[name=nome]').fill('EE Modello rinominato');
  await invia('.ndm-tabella tbody tr:has(b:text-is("EE Mio modello")) button:has-text("Salva il nome")');
  ok('Rinomina', uno(`SELECT nome FROM {p}newsletter_modelli WHERE id = ${m1}`) === 'EE Modello rinominato');
  await invia('.ndm-tabella tbody tr:has(b:text-is("EE Modello rinominato")) button:has-text("Duplica")');
  const mCopia = num("SELECT id FROM {p}newsletter_modelli WHERE nome = 'Copia di EE Modello rinominato'");
  ok('Duplica: la copia nasce inattiva', mCopia > 0 && num(`SELECT attivo FROM {p}newsletter_modelli WHERE id = ${mCopia}`) === 0);
  await invia('.ndm-tabella tbody tr:has(b:text-is("Copia di EE Modello rinominato")) button:has-text("Elimina")');
  ok('Elimina (con la conferma del pannello)', num(`SELECT COUNT(*) FROM {p}newsletter_modelli WHERE id = ${mCopia}`) === 0);
  await p.click('a.btn:has-text("Modifica") >> nth=0').catch(() => {});
  await p.waitForSelector('.nd-b', { timeout: 8000 }).catch(() => {});
  ok('Modifica apre il modello nell\'editor a blocchi', /newsletter-design\.php\?modello=\d+/.test(p.url()) && !(await p.locator('#nd-come-modello').count()));

  // ===== Sezioni salvate sincronizzate: bozze sì, inviate no
  const nuovaBlocchi = async (nome) => { await vai('newsletter.php?crea=1'); await p.fill('#nl-crea-nome', nome); await invia('#nl-crea button[type=submit]'); const id = +new URL(p.url()).searchParams.get('id'); await vai(`newsletter-campagna.php?id=${id}&apri=design`); await invia('#sez-design form.nd-strada:has(input[name=editor][value=blocchi]) button'); await p.waitForSelector('.nd-b'); return id; };
  const ca = await nuovaBlocchi('EE Sync A');
  d = await D(); const sA = d.sezioni[0].id;
  await scegli(blocchi(d)[0].id); await controllo('Testo').locator('input').fill('EE Testata comune');
  await salvato();
  await p.locator(`.nd-s[data-id="${sA}"]`).hover();
  await p.locator(`.nd-s[data-id="${sA}"] > .nd-strumenti [data-nd-azione=salva]`).click();
  await p.fill('#nd-nome-campo', 'EE Sezione sync');
  ok('Salva la sezione: «Sincronizzata» spuntato di partenza', await p.isChecked('#nd-nome-spunta'));
  await p.click('#nd-nome-ok');
  await p.waitForFunction(() => /Sezione salvata/.test(document.getElementById('nd-stato').textContent), null, { timeout: 8000 }).catch(() => {});
  const ss = num("SELECT id FROM {p}newsletter_sezioni_salvate WHERE nome = 'EE Sezione sync'");
  ok('sezione salvata (sincronizzata), e la sezione della campagna resta collegata', ss > 0 && num(`SELECT sincronizzata FROM {p}newsletter_sezioni_salvate WHERE id = ${ss}`) === 1 && (await D()).sezioni[0].salvata === ss);
  await p.click('#nd-scheda-salvati');
  ok('la scheda Salvati la mostra, con «Sincronizzata» scritto', /EE Sezione sync/.test(await p.locator('#nd-elenco-salvati').innerText()) && /Sincronizzata/.test(await p.locator('#nd-elenco-salvati').innerText()));
  await salvato();
  const inserisciSalvata = async (id) => { const n0 = (await D()).sezioni.length; await p.click('#nd-scheda-salvati'); await p.click(`.nd-salvato[data-salvata="${id}"] button:has-text("Inserisci")`); await salvato(); return (await D()).sezioni.length === n0 + 1; };
  const cb = await nuovaBlocchi('EE Sync B');
  ok('in un\'altra campagna in bozza: Inserisci la sezione salvata', await inserisciSalvata(ss) && (await D()).sezioni.some(s => s.salvata === ss));
  const cc = await nuovaBlocchi('EE Sync C inviata');
  await inserisciSalvata(ss);
  q(`UPDATE {p}newsletter_campagne SET stato = 'inviata' WHERE id = ${cc}`);
  // una copia non sincronizzata
  await vai(`newsletter-design.php?campagna=${cb}`); await p.waitForSelector('.nd-b');
  d = await D();
  await p.locator(`.nd-s[data-id="${d.sezioni[1].id}"]`).hover();
  await p.locator(`.nd-s[data-id="${d.sezioni[1].id}"] > .nd-strumenti [data-nd-azione=salva]`).click();
  await p.fill('#nd-nome-campo', 'EE Sezione copia'); await p.uncheck('#nd-nome-spunta'); await p.click('#nd-nome-ok');
  await p.waitForFunction(() => /Sezione salvata/.test(document.getElementById('nd-stato').textContent), null, { timeout: 8000 }).catch(() => {});
  const sc = num("SELECT id FROM {p}newsletter_sezioni_salvate WHERE nome = 'EE Sezione copia'");
  ok('sezione salvata come copia: la sezione non resta collegata', sc > 0 && (await D()).sezioni[1].salvata === 0);
  // in A la sezione cambia, poi «Aggiorna in tutte le email»
  await vai(`newsletter-design.php?campagna=${ca}`); await p.waitForSelector('.nd-b');
  d = await D();
  await scegli(d.sezioni[0].colonne[0][0].id); await controllo('Testo').locator('input').fill('EE Testata cambiata ovunque');
  await p.click('#nd-controlli button:has-text("Seleziona la sua sezione")');
  ok('sezione collegata: il pannello lo dice e offre «Aggiorna in tutte le email»', /sincronizzata/.test(await p.locator('#nd-controlli .nd-salvata').innerText()));
  await p.click('#nd-controlli button:has-text("Aggiorna in tutte le email")');
  await p.waitForFunction(() => /Sezione aggiornata ovunque/.test(document.getElementById('nd-stato').textContent), null, { timeout: 10000 }).catch(() => {});
  const msg = await p.locator('#nd-stato').innerText();
  ok('«Aggiorna in tutte le email»: dice quante campagne in bozza ha cambiato', /Sezione aggiornata ovunque: 2 campagne in bozza/.test(msg), msg);
  const testoDi = (c) => JSON.stringify(ee('design', c).design);
  ok('la campagna in bozza B ha la sezione cambiata', /EE Testata cambiata ovunque/.test(testoDi(cb)));
  ok('la campagna inviata C resta com\'era', /EE Testata comune/.test(testoDi(cc)) && !/EE Testata cambiata ovunque/.test(testoDi(cc)));
  ok('la sezione salvata ha il contenuto nuovo', /EE Testata cambiata ovunque/.test(uno(`SELECT json FROM {p}newsletter_sezioni_salvate WHERE id = ${ss}`) || ''));
  await foto('08-salvati');
  await p.click('#nd-scheda-salvati');
  await p.click(`.nd-salvato[data-salvata="${sc}"] button:has-text("Elimina")`);
  await p.waitForFunction(id => !document.querySelector(`.nd-salvato[data-salvata="${id}"]`), sc, { timeout: 8000 }).catch(() => {});
  ok('Elimina una sezione salvata (con la conferma del pannello)', num(`SELECT COUNT(*) FROM {p}newsletter_sezioni_salvate WHERE id = ${sc}`) === 0);

  // ===== L'editor di una campagna partita si guarda e basta
  await vai(`newsletter-design.php?campagna=${cc}`);
  ok('campagna inviata: l\'editor si apre solo da guardare (Salva spento, avviso)', await p.locator('#nd-salva').isDisabled() && /già partita/.test(await p.locator('.nd-avviso').innerText()));

  // ===== Email di conferma dell'iscrizione: modello di doppio opt-in
  await vai('newsletter-modelli.php?scheda=galleria');
  await invia('.ndm-carta:has(b:text-is("Conferma dell\'iscrizione")) button:has-text("Aggiungi ai miei modelli")');
  const mDoi = num("SELECT id FROM {p}newsletter_modelli WHERE origine = '6-conferma-iscrizione'");
  ok('il modello della galleria «Conferma dell\'iscrizione» ha il tag optin', mDoi > 0 && uno(`SELECT tag FROM {p}newsletter_modelli WHERE id = ${mDoi}`) === 'optin');
  await vai('newsletter-modelli.php#ndm-conferma');
  ok('Email di conferma: la predefinita è quella scelta di partenza', await p.inputValue('#ndm-doi') === '0');
  await p.selectOption('#ndm-doi', String(mDoi)); await invia('#ndm-conferma button[type=submit]');
  ok('si sceglie il modello per l\'email di conferma', uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_doi_modello'") === String(mDoi));
  const nPrima = posta().length;
  ee('doi', 'nuovo1@ee-prova.it', 'Ugo');
  let conf = posta().slice(nPrima).pop() || { html: '', oggetto: '' };
  const linkConf = ((conf.html.match(/href="([^"]*newsletter-conferma\/\?t=[a-f0-9]{64})"/) || [])[1] || '').replace(/&amp;/g, '&');
  ok('l\'email di conferma è quella del modello: «Manca un clic», «Ciao Ugo», il pulsante con il link di conferma', /Manca un clic/.test(conf.html) && /Ciao Ugo/.test(conf.html) && linkConf !== '' && conf.oggetto === 'Conferma l\'iscrizione', conf.oggetto);
  ok('e regge il validatore per la posta', validaPosta(conf.html).length === 0, validaPosta(conf.html));
  await p.goto(linkConf, { waitUntil: 'networkidle' });
  ok('il link di conferma funziona: il contatto è attivo', uno("SELECT stato FROM {p}newsletter_iscritti WHERE email = 'nuovo1@ee-prova.it'") === 'attivo');
  ee('doi', 'nuovo2@ee-prova.it', 'Pia', 'EE Titolo del form');
  conf = posta().slice(nPrima).pop() || { html: '' };
  ok('un form con la sua email di conferma tiene la sua (non il modello)', /EE Titolo del form/.test(conf.html) && !/Manca un clic/.test(conf.html));
  await vai('newsletter-modelli.php#ndm-conferma');
  await p.selectOption('#ndm-doi', '0'); await invia('#ndm-conferma button[type=submit]');
  ee('doi', 'nuovo3@ee-prova.it', 'Eva');
  conf = posta().slice(nPrima).pop() || { html: '' };
  ok('tornando alla predefinita: l\'email di sempre («Conferma l\'iscrizione», «Confermo l\'iscrizione»)', /Conferma l(&apos;|&#039;|')iscrizione/.test(conf.html) && /Confermo l/.test(conf.html) && !/Manca un clic/.test(conf.html));

  // ===== 390 px senza scorrimento di lato
  const tel = await br.newContext({ viewport: { width: 390, height: 844 } });
  await tel.addCookies(await ctx.cookies());
  const pt = await tel.newPage(); pt.on('pageerror', e => err.push('390 ' + e.message.slice(0, 200)));
  for (const u of [`newsletter-design.php?campagna=${cb}`, 'newsletter-modelli.php', 'newsletter-modelli.php?scheda=galleria', `newsletter-campagna.php?id=${cb}&apri=design`]) {
    await pt.goto(B + '/admin/' + u, { waitUntil: 'networkidle' });
    const w = await pt.evaluate(() => document.documentElement.scrollWidth);
    ok(`390 px: ${u.replace(/\?.*/, '')}${u.includes('galleria') ? ' (galleria)' : ''} senza scorrimento di lato`, w <= 390, w);
    if (FOTO) await pt.screenshot({ path: path.join(FOTO, '390-' + u.replace(/[^a-z]+/g, '-').slice(0, 40) + '.png'), fullPage: true });
  }
  await pt.goto(B + `/admin/newsletter-design.php?campagna=${cb}`, { waitUntil: 'networkidle' });
  ok('390 px: l\'editor parte in vista telefono e i pulsanti in alto sono alti almeno 44 px', await pt.locator('#nd-tela.nd-tel').count() === 1 && (await pt.locator('#nd-salva').boundingBox()).height >= 44);

  ok('nessun errore JavaScript nelle pagine', err.length === 0, err);
  try { fs.unlinkSync(png); } catch (e) {}
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); console.log(`\n${n - ko} su ${n} OK (interrotta)`); process.exit(1); });
