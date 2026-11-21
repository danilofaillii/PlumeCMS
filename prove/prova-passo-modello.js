// 2.18.0: il passo Email di un'automazione richiama un modello (richiesta di Danilo: le email di una sequenza si scrivono e si
// correggono una volta sola in Modelli, e l'automazione manda sempre l'ultima versione salvata). Provato come un utente nel
// pannello (passo Email, tela, Apri il modello, Stacca dal modello, Modelli con "Usato in", conferme, pagina del codice HTML,
// dashboard) e con il motore spinto dal CLI e dal tempo spostato (automazioni-cli.php avanza).
// uso: node prove/prova-passo-modello.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella CMS>
// Il server di posta finto: python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> &
// Pulisce prima e dopo i suoi dati (AU ...): contatti @au-prova.it, liste, automazioni e modelli "AU ".
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const [B, UTENTE, PASSWORD, SMTP, POSTA, DIR] = process.argv.slice(2);
const CLI = path.join(__dirname, 'automazioni-cli.php');
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (!c && extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 700) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [CLI, DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 256 << 20 }).trim().split('\n').pop());
const q = (sql) => cli('sql', sql);
const uno = (sql) => { const r = q(sql); return r && r[0] ? Object.values(r[0])[0] : null; };
const num = (sql) => { const v = uno(sql); return v === null ? NaN : +v; };

function leggi(eml) {
  const i = eml.search(/\r?\n\r?\n/);
  const testa = eml.slice(0, i).replace(/\r?\n[ \t]+/g, ' '); const corpo = eml.slice(i).replace(/^\r?\n\r?\n/, '');
  const h = (t, nome) => ((t.match(new RegExp('^' + nome + ':[ \\t]*(.*)$', 'mi')) || [])[1] || '').trim();
  const dec = s => s.replace(/=\?UTF-8\?B\?([^?]+)\?=/gi, (_, b) => Buffer.from(b, 'base64').toString('utf8'));
  const parte = (t, c) => /base64/i.test(h(t, 'Content-Transfer-Encoding')) ? Buffer.from(c.replace(/\s+/g, ''), 'base64').toString('utf8') : c.replace(/=\r?\n/g, '').replace(/=([0-9A-F]{2})/g, (_, x) => String.fromCharCode(parseInt(x, 16)));
  let html = '';
  const b = (h(testa, 'Content-Type').match(/boundary="([^"]+)"/) || [])[1];
  if (b) for (const p of corpo.split('--' + b).slice(1)) {
    const j = p.search(/\r?\n\r?\n/); if (j < 0) continue;
    if (/text\/html/i.test(h(p.slice(0, j).trim(), 'Content-Type'))) html = parte(p.slice(0, j).trim(), p.slice(j).trim());
  } else html = parte(testa, corpo);
  return { dest: h(testa, 'X-Destinatari'), oggetto: dec(h(testa, 'Subject')), html };
}
const posta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => leggi(fs.readFileSync(path.join(POSTA, f), 'utf8')));
const di = (chi) => posta().filter(m => m.dest.startsWith(chi + '@'));
const giro = () => cli('giro');
const nodoDb = (f, id) => JSON.parse(uno(`SELECT passi FROM {p}newsletter_flussi WHERE id = ${f}`)).nodi.find(x => x.id === id).dati;
const C = {};

(async () => {
  for (const f of fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []) if (f.endsWith('.eml')) fs.unlinkSync(path.join(POSTA, f));
  cli('pulisci');
  cli('smtp', SMTP);
  const L = cli('lista', 'AU PM');
  const persona = (k) => (C[k] = cli('contatto', k + '@au-prova.it', 'Persona ' + k, L));
  const M1 = cli('modello_crea', 'AU PM Uno', 'blocchi', 'AU PM testo A', 'AU PM oggetto uno {nome}', 'AU PM anteprima uno');
  const MH = cli('modello_crea', 'AU PM Codice', 'html', 'AU PM codice H', 'AU PM oggetto codice {nome}');
  const MV = cli('modello_crea', 'AU PM Senza oggetto', 'blocchi', 'AU PM testo V', '');
  q(`INSERT INTO {p}newsletter_modelli (nome, editor, design, oggetto, attivo, tag, origine, creato, aggiornato) SELECT 'AU PM Conferma', 'blocchi', design, 'Conferma', 1, 'optin', '', NOW(), NOW() FROM {p}newsletter_modelli WHERE id = ${M1}`);
  const MO = num("SELECT id FROM {p}newsletter_modelli WHERE nome = 'AU PM Conferma'");
  ok('preparazione: tre modelli e quello della conferma', M1 > 0 && MH > 0 && MV > 0 && MO > 0, [M1, MH, MV, MO]);
  // Un'automazione dal CLI con tre email semplici e due attese; i passi da modello si scelgono nel pannello.
  const nodo = (id, tipo, dati = {}) => ({ id, tipo, x: 40 + (id % 2) * 300, y: 40 + id * 140, dati });
  const em = (id) => nodo(id, 'email', { oggetto: 'AU PM semplice ' + id, anteprima: '', corpo: '<p>Ciao {nome}</p>', pulsante_testo: '', pulsante_link: '', ascolto: 0 });
  const F = cli('salva', JSON.stringify({ nome: 'AU PM Automazione', stato: 'attiva', inneschi: [{ tipo: 'lista', id: L }], grafo: { nodi: [nodo(0, 'innesco'), em(1), nodo(2, 'attesa', { minuti: 1440 }), em(3), nodo(4, 'attesa', { minuti: 1440 }), em(5)],
    archi: [[0, 'poi', 1], [1, 'poi', 2], [2, 'poi', 3], [3, 'poi', 4], [4, 'poi', 5]].map(([da, uscita, a]) => ({ da, uscita, a })) } }));
  ok('preparazione: automazione salvata', typeof F === 'number', F);

  // Un passo che richiama un modello senza oggetto, con l'oggetto vuoto anche nel passo: il salvataggio lo dice.
  const eV = cli('salva', JSON.stringify({ nome: 'AU PM Errore', stato: 'in_pausa', inneschi: [{ tipo: 'lista', id: L }], grafo: { nodi: [nodo(0, 'innesco'), nodo(1, 'email', { oggetto: '', anteprima: '', corpo: '', editor: 'modello', modello: MV })], archi: [{ da: 0, uscita: 'poi', a: 1 }] } }));
  ok('oggetto vuoto nel passo e nel modello: non si salva e dice cosa fare', eV && /scrivi l'oggetto, oppure dallo al modello «AU PM Senza oggetto»/.test(eV.errore || ''), eV);
  const eO = cli('salva', JSON.stringify({ nome: 'AU PM Errore', stato: 'in_pausa', inneschi: [{ tipo: 'lista', id: L }], grafo: { nodi: [nodo(0, 'innesco'), nodo(1, 'email', { oggetto: 'X', anteprima: '', corpo: '', editor: 'modello', modello: MO })], archi: [{ da: 0, uscita: 'poi', a: 1 }] } }));
  ok('il modello dell\'email di conferma non si può richiamare', eO && /non c'è più/.test(eO.errore || ''), eO);

  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1440, height: 950 } });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  const messaggi = [];
  pnConferme.on(p, d => { messaggi.push(d.message()); d.accept(); });
  const vai = u => p.goto(B + '/admin/' + u, { waitUntil: 'networkidle' });
  const flash = async () => (await p.locator('.flash, [role=alert], [role=status]').allInnerTexts()).join(' | ');
  const invia = async (sel) => { await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle' }), p.click(sel)]); };
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  const nodoClic = async (id) => { await p.locator(`.fl-nodo[data-id="${id}"] .fl-nodo-corpo`).click(); };
  const tela = async (id) => p.locator(`.fl-nodo[data-id="${id}"] .fl-nodo-corpo`).innerText();

  // ===== 1. Il passo Email nel pannello: "Da un modello" richiama il modello
  await vai('newsletter-flussi.php?id=' + F);
  await nodoClic(1);
  await p.check('[name=fp-editor][value=modello]');
  ok('"Da un modello": la tendina non elenca il modello della conferma', await p.locator(`#fp-modello option[value="${MO}"]`).count() === 0 && await p.locator(`#fp-modello option[value="${M1}"]`).count() === 1);
  await p.selectOption('#fp-modello', String(M1));
  await p.fill('#fp-oggetto', '');
  ok('oggetto e anteprima vuoti: il segnaposto dice quelli del modello', await p.getAttribute('#fp-oggetto', 'placeholder') === 'dal modello: AU PM oggetto uno {nome}' && await p.getAttribute('#fp-anteprima', 'placeholder') === 'dal modello: AU PM anteprima uno');
  ok('scegliere il modello non copia più l\'oggetto nel passo (2.17.1 lo copiava)', await p.inputValue('#fp-oggetto') === '');
  ok('"Apri il modello" porta all\'editor del modello, con il ritorno all\'automazione', (await p.getAttribute('#fp-apri-modello', 'href')) === `newsletter-design.php?modello=${M1}&flusso=${F}` && await p.locator('#fp-apri-modello').isVisible() && await p.locator('#fp-stacca').isVisible());
  const t1 = await tela(1);
  ok('sulla tela: "Modello: AU PM Uno" e l\'oggetto del modello', /Modello: AU PM Uno/.test(t1) && /AU PM oggetto uno \{nome\}/.test(t1) && /oggetto del modello/.test(t1), t1);
  await nodoClic(3);
  await p.check('[name=fp-editor][value=modello]'); await p.selectOption('#fp-modello', String(M1)); await p.fill('#fp-oggetto', 'AU PM oggetto del passo');
  await nodoClic(5);
  await p.check('[name=fp-editor][value=modello]'); await p.selectOption('#fp-modello', String(MH)); await p.fill('#fp-oggetto', '');
  ok('modello in codice HTML: "Apri il modello" porta alla sua pagina del codice', (await p.getAttribute('#fp-apri-modello', 'href')) === `newsletter-modelli.php?codice=${MH}&flusso=${F}`);
  await invia('#fl-form button[value=salva]');
  ok('salvata', /salvata/i.test(await flash()), await flash());
  const d1 = nodoDb(F, 1), d3 = nodoDb(F, 3), d5 = nodoDb(F, 5);
  ok('nel database il passo tiene il riferimento: editor "modello", id del modello, niente copia', d1.editor === 'modello' && d1.modello === M1 && d1.design === '' && d1.html === '' && d1.oggetto === '', d1);
  ok('nel database: il passo #3 con il suo oggetto, il #5 con il modello in codice HTML', d3.editor === 'modello' && d3.modello === M1 && d3.oggetto === 'AU PM oggetto del passo' && d5.editor === 'modello' && d5.modello === MH && d5.html === '', [d3, d5]);
  await nodoClic(1);
  ok('riaperto, il passo dice "Da un modello" con il modello scelto', await p.locator('[name=fp-editor][value=modello]').isChecked() && await p.inputValue('#fp-modello') === String(M1));

  // ===== 2. Gli invii leggono il modello com'è in quel momento
  persona('p1'); giro();
  let m = di('p1')[0];
  ok('primo invio: dal modello (testo, oggetto e anteprima del modello, nome della persona)', !!m && m.oggetto === 'AU PM oggetto uno Persona p1' && /AU PM testo A, Persona p1/.test(m.html) && /AU PM anteprima uno/.test(m.html), m && m.oggetto);
  ok('primo invio: link per uscire e pixel come le altre email', !!m && /newsletter-esci/.test(m.html) && /api\/nl\.php/.test(m.html));
  cli('modello_testo', M1, 'AU PM testo B', 'AU PM oggetto nuovo {nome}');
  cli('avanza', 1440, F); giro();
  m = di('p1')[1];
  ok('modello cambiato fra un invio e l\'altro: il secondo esce con il testo nuovo', !!m && /AU PM testo B, Persona p1/.test(m.html) && !/AU PM testo A/.test(m.html), m && m.oggetto);
  ok('oggetto scritto nel passo: vince su quello del modello', !!m && m.oggetto === 'AU PM oggetto del passo', m && m.oggetto);
  persona('p2'); giro();
  m = di('p2')[0];
  ok('oggetto vuoto nel passo: vale quello del modello al momento dell\'invio', !!m && m.oggetto === 'AU PM oggetto nuovo Persona p2' && /AU PM testo B/.test(m.html), m && m.oggetto);
  cli('avanza', 1440, F); giro();
  m = di('p1')[2];
  ok('modello in codice HTML richiamato: parte con il codice, il nome e il link per uscire', !!m && m.oggetto === 'AU PM oggetto codice Persona p1' && /AU PM codice H, Persona p1/.test(m.html) && /newsletter-esci/.test(m.html), m && m.oggetto);
  const reg1 = q(`SELECT dettaglio FROM {p}newsletter_flussi_registro WHERE flusso_id = ${F} AND iscritto_id = ${C.p1} AND evento = 'email' ORDER BY id`).map(x => x.dettaglio);
  ok('registro: le tre email con l\'oggetto mandato', reg1.join('|') === 'AU PM oggetto uno Persona p1|AU PM oggetto del passo|AU PM oggetto codice Persona p1', reg1);

  // ===== 3. Modelli: "Usato in", anteprima, conferme
  await vai('newsletter-modelli.php');
  const riga = (nome) => p.locator('tr', { hasText: nome }).first();
  const u1 = await riga('AU PM Uno').locator('td[data-etichetta="Usato in"]').innerText();
  ok('Modelli: "Usato in" dice l\'automazione e le email', /AU PM Automazione, email #1, #3/.test(u1), u1);
  ok('Modelli: il link porta all\'automazione', await riga('AU PM Uno').locator(`td[data-etichetta="Usato in"] a[href="newsletter-flussi.php?id=${F}"]`).count() === 1);
  ok('Modelli: un modello non usato lo dice', /Nessuna automazione/.test(await riga('AU PM Senza oggetto').locator('td[data-etichetta="Usato in"]').innerText()));
  await riga('AU PM Uno').locator('summary:has-text("Oggetto e uso")').click();
  await riga('AU PM Uno').locator('input[name=anteprima]').fill('AU PM anteprima due');
  await riga('AU PM Uno').locator('input[name=optin]').check();
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle' }), riga('AU PM Uno').locator('form:has(input[value=oggetto]) button[type=submit]').click()]);
  ok('un modello usato non diventa l\'email di conferma (lo dice e non cambia)', /non può diventare l'email di conferma/.test(await flash()) && uno(`SELECT tag FROM {p}newsletter_modelli WHERE id = ${M1}`) === '', await flash());
  await riga('AU PM Uno').locator('summary:has-text("Oggetto e uso")').click();
  await riga('AU PM Uno').locator('input[name=anteprima]').fill('AU PM anteprima due');
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle' }), riga('AU PM Uno').locator('form:has(input[value=oggetto]) button[type=submit]').click()]);
  ok('"Oggetto e uso": il testo di anteprima del modello si salva', uno(`SELECT anteprima FROM {p}newsletter_modelli WHERE id = ${M1}`) === 'AU PM anteprima due');
  messaggi.length = 0;
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle' }), riga('AU PM Uno').locator('button:has-text("Rendi inattivo")').click()]);
  ok('Rendi inattivo un modello usato: la conferma elenca le automazioni e dice che le email continuano', messaggi.length === 1 && /AU PM Automazione/.test(messaggi[0]) && /email #1, #3/.test(messaggi[0]) && /continuano a partire/.test(messaggi[0]), messaggi);
  ok('il modello è inattivo', num(`SELECT attivo FROM {p}newsletter_modelli WHERE id = ${M1}`) === 0);
  persona('p3'); giro();
  m = di('p3')[0];
  ok('modello inattivo usato da un passo: l\'email parte lo stesso, con l\'anteprima nuova', !!m && /AU PM testo B, Persona p3/.test(m.html) && /AU PM anteprima due/.test(m.html), m && m.oggetto);
  messaggi.length = 0;
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle' }), riga('AU PM Codice').locator('button:has-text("Elimina")').click()]);
  ok('Elimina un modello usato: la conferma elenca le automazioni e dice che si fermano', messaggi.length === 1 && /AU PM Automazione/.test(messaggi[0]) && /email #5/.test(messaggi[0]) && /si fermeranno/.test(messaggi[0]), messaggi);
  ok('il modello è eliminato, e il messaggio lo ricorda', num(`SELECT COUNT(*) FROM {p}newsletter_modelli WHERE id = ${MH}`) === 0 && /lo richiamava/.test(await flash()), await flash());

  // ===== 4. Modello sparito: niente email vuota, la persona si ferma, registro e dashboard
  const prima = posta().length;
  cli('avanza', 1440, F); giro(); // p2 (già all'attesa dopo la #3) arriva alla #5, che richiama il modello eliminato; p3 riceve la #3
  cli('avanza', 1440, F); giro(); // anche p3 arriva alla #5
  const nuove = posta().slice(prima).map(x => x.dest + ' ' + x.oggetto);
  ok('modello eliminato: al passo #5 nessuna email parte (parte solo la #3 di chi era prima)', nuove.length === 1 && /^p3@.* AU PM oggetto del passo$/.test(nuove[0]) && di('p2').length === 2, nuove);
  const cp2 = q(`SELECT * FROM {p}newsletter_corsi WHERE flusso_id = ${F} AND iscritto_id = ${C.p2}`)[0];
  ok('la persona resta ferma sul passo #5, in attesa del modello', cp2.stato === 'attivo' && +cp2.passo === 5 && cp2.ascolto === 'modello', cp2);
  const reg = () => q(`SELECT dettaglio FROM {p}newsletter_flussi_registro WHERE flusso_id = ${F} AND iscritto_id = ${C.p2} AND nodo = 5`).map(x => x.dettaglio);
  ok('registro: "modello #N non trovato: email non mandata"', reg().join('|') === `modello #${MH} non trovato: email non mandata`, reg());
  cli('avanza', 120, F); giro(); giro();
  ok('ai giri dopo: ancora nessuna email e il registro non si ripete', di('p2').length === 2 && di('p3').length === 2 && reg().length === 1, reg());
  await vai('index.php');
  const avv = await p.locator('.db-avvisi').innerText().catch(() => '');
  ok('la dashboard lo segnala, con il link all\'automazione', /Automazione «AU PM Automazione»: l'email #5 richiama il modello #\d+, che non c'è più/.test(avv) && /2 persone sono ferme/.test(avv) && await p.locator(`.db-avvisi a[href="newsletter-flussi.php?id=${F}"]`).count() === 1, avv);
  await vai('newsletter-flussi.php?id=' + F);
  ok('sulla tela il passo #5 dice "Modello non trovato"', /Modello #\d+ non trovato/.test(await tela(5)), await tela(5));
  await nodoClic(5);
  ok('nel pannello del passo: l\'avviso del modello che non c\'è più', /non c'è più: questa email non parte/.test(await p.locator('#fl-pannello').innerText()));
  await vai(`newsletter-flussi.php?id=${F}&scheda=attivita`);
  ok('Attività: chi è fermo lo dice', /fermo: il modello dell'email non si può mandare/.test(await p.locator('#fl-dentro').innerText()));
  // Si sceglie un altro modello: chi era fermo riparte al giro dopo.
  await vai('newsletter-flussi.php?id=' + F);
  await nodoClic(5); await p.selectOption('#fp-modello', String(M1));
  await invia('#fl-form button[value=salva]');
  cli('avanza', 120, F); giro();
  m = di('p2')[2];
  ok('scelto un altro modello, chi era fermo riceve l\'email al giro dopo', !!m && /AU PM testo B, Persona p2/.test(m.html) && di('p3').length === 3, m && m.oggetto);
  await vai('index.php');
  ok('la dashboard non segnala più niente', !/AU PM Automazione/.test(await p.locator('.db-avvisi').innerText().catch(() => '')));

  // ===== 5. Apri il modello e torna; Stacca dal modello
  await vai('newsletter-flussi.php?id=' + F);
  await nodoClic(3);
  await Promise.all([p.waitForURL(/newsletter-design\.php\?modello=\d+&flusso=\d+/), p.click('#fp-apri-modello')]);
  await p.waitForLoadState('networkidle');
  ok('"Apri il modello": si apre l\'editor del modello', /AU PM Uno/.test(await p.locator('.ed-nome').innerText()));
  await Promise.all([p.waitForNavigation(), p.click('.ed-indietro')]);
  ok('dall\'editor del modello, "Torna" riporta all\'automazione', new RegExp('newsletter-flussi\\.php\\?id=' + F + '$').test(p.url()), p.url());
  const designM1 = uno(`SELECT design FROM {p}newsletter_modelli WHERE id = ${M1}`);
  await nodoClic(1);
  await invia('#fp-stacca');
  ok('"Stacca dal modello": il messaggio dice che l\'email ora è indipendente', /copia sua del modello/.test(await flash()), await flash());
  const s1 = nodoDb(F, 1);
  ok('staccato: il passo ha il design del modello, editor a blocchi, nessun riferimento, l\'oggetto del modello', s1.editor === 'blocchi' && s1.modello === 0 && s1.design === designM1 && s1.oggetto === 'AU PM oggetto nuovo {nome}' && s1.anteprima === 'AU PM anteprima due', s1);
  ok('sulla tela il passo #1 dice "editor a blocchi"', /editor a blocchi/.test(await tela(1)));
  cli('modello_testo', M1, 'AU PM testo C');
  persona('p4'); giro();
  m = di('p4')[0];
  ok('cambiare il modello non tocca più il passo staccato', !!m && /AU PM testo B, Persona p4/.test(m.html) && !/AU PM testo C/.test(m.html), m && m.oggetto);
  await vai('newsletter-modelli.php');
  ok('Modelli: dopo lo stacco "Usato in" dice solo l\'email #3 e la #5', /AU PM Automazione, email #3, #5/.test(await riga('AU PM Uno').locator('td[data-etichetta="Usato in"]').innerText()));

  // ===== 6. Un modello nuovo in codice HTML, dal pannello
  await p.fill('#ndm-nuovo', 'AU PM Nuovo HTML'); await p.selectOption('#ndm-tipo', 'html');
  await invia('form:has(#ndm-nuovo) button[type=submit]');
  ok('Crea un modello "Incollo il codice HTML": si apre la pagina del codice, con il link per uscire già dentro', /newsletter-modelli\.php\?codice=\d+/.test(p.url()) && /\{\{ unsubscribe \}\}/.test(await p.inputValue('#ndm-c-html')), p.url());
  await p.fill('#ndm-c-ogg', 'AU PM oggetto HTML nuovo'); await p.fill('#ndm-c-ant', 'AU PM anteprima HTML');
  await p.fill('#ndm-c-html', '<html><body><p>AU PM incollato, {nome}</p><p><a href="{{ unsubscribe }}">Esci</a></p></body></html>');
  await invia('#ndm-codice button[type=submit]');
  const mN = q("SELECT * FROM {p}newsletter_modelli WHERE nome = 'AU PM Nuovo HTML'")[0] || {};
  ok('il codice, l\'oggetto e l\'anteprima si salvano', mN.editor === 'html' && /AU PM incollato/.test(mN.html) && mN.oggetto === 'AU PM oggetto HTML nuovo' && mN.anteprima === 'AU PM anteprima HTML', mN);
  await vai('newsletter-modelli.php');
  ok('Modelli: il modello in codice HTML ha "Modifica il codice"', await riga('AU PM Nuovo HTML').locator(`a[href="newsletter-modelli.php?codice=${mN.id}"]`).count() === 1);

  // ===== 7. Un'automazione scritta a mano nel database (come da un pacchetto): un passo "alle ore" che richiama un modello
  // che non c'è. La persona resta ferma (non "aspetta l'orario"); quando il passo richiama un modello vero, aspetta l'ora e parte.
  const ora = +uno('SELECT HOUR(NOW())'), fra2 = (ora + 2) % 24;
  const LO = cli('lista', 'AU PM Ore');
  const gO = { nodi: [{ id: 0, tipo: 'innesco', x: 40, y: 40, dati: [] }, { id: 1, tipo: 'attesa', x: 40, y: 190, dati: { minuti: 1440 } },
    { id: 2, tipo: 'email', x: 40, y: 340, dati: { oggetto: '', anteprima: '', corpo: '', pulsante_testo: '', pulsante_link: '', ascolto: 0, editor: 'modello', design: '', html: '', modello: 999999, mittente_id: 0, orario: 'ora', ora: fra2 } }],
    archi: [{ da: 0, uscita: 'poi', a: 1 }, { da: 1, uscita: 'poi', a: 2 }] };
  q(`INSERT INTO {p}newsletter_flussi (nome, attivo, innesco, innesco_id, passi, inviate, mittente_id, creato, aggiornato, stato, inneschi, tag, rientro, rientro_attesa, uscita) VALUES ('AU PM Scritta a mano', 1, 'lista', ${LO}, '${JSON.stringify(gO).replace(/'/g, "''")}', 0, 0, NOW(), NOW(), 'attiva', '[{"tipo":"lista","id":${LO}}]', '', 0, 0, '{"disiscritto":false,"segmento":0}')`);
  const FO = num("SELECT id FROM {p}newsletter_flussi WHERE nome = 'AU PM Scritta a mano'");
  C.o1 = cli('contatto', 'o1@au-prova.it', 'Persona o1', LO); giro();
  cli('avanza', 1440, FO); giro();
  let co = q(`SELECT * FROM {p}newsletter_corsi WHERE flusso_id = ${FO}`)[0];
  ok('scritta a mano, passo "alle ore" con un modello che non c\'è: la persona è ferma sul modello, nessuna email', co.ascolto === 'modello' && +co.passo === 2 && di('o1').length === 0, co);
  gO.nodi[2].dati.modello = M1;
  q(`UPDATE {p}newsletter_flussi SET passi = '${JSON.stringify(gO).replace(/'/g, "''")}' WHERE id = ${FO}`);
  cli('avanza', 120, FO); giro();
  co = q(`SELECT * FROM {p}newsletter_corsi WHERE flusso_id = ${FO}`)[0];
  ok('il passo richiama un modello vero: aspetta l\'ora scelta (alle ' + fra2 + ')', co.ascolto === 'orario' && new Date(co.prossimo.replace(' ', 'T')).getHours() === fra2 && di('o1').length === 0, co);
  cli('avanza', 180, FO); giro();
  m = di('o1')[0];
  ok('arrivata l\'ora parte, dal modello (oggetto del modello)', !!m && /AU PM testo C, Persona o1/.test(m.html) && m.oggetto === 'AU PM oggetto nuovo Persona o1', m && m.oggetto);

  // ===== 8. 390 e 768 px
  for (const larg of [390, 768]) {
    const tel = await br.newContext({ viewport: { width: larg, height: 800 } });
    const pt = await tel.newPage();
    pt.on('pageerror', e => err.push(larg + ': ' + e.message.slice(0, 200)));
    await pt.goto(B + '/admin/login.php'); await pt.fill('#username', UTENTE); await pt.fill('#password', PASSWORD);
    await Promise.all([pt.waitForNavigation(), pt.click('button[type=submit]')]);
    const larghi = [];
    for (const u of ['newsletter-modelli.php', 'newsletter-modelli.php?codice=' + mN.id, 'newsletter-flussi.php?id=' + F, 'index.php']) {
      await pt.goto(B + '/admin/' + u, { waitUntil: 'networkidle' });
      const w = await pt.evaluate(() => document.documentElement.scrollWidth);
      if (w > larg) larghi.push([u, w]);
    }
    ok(larg + ' px: Modelli, pagina del codice, tela e dashboard senza scorrimento di lato', larghi.length === 0, larghi);
    await tel.close();
  }
  ok('nessun errore JavaScript nelle pagine', err.length === 0, err);
  await br.close();
  cli('pulisci');
  console.log(`${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); console.log(`${n - ko} su ${n} OK (interrotta)`); process.exit(1); });
