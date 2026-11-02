// 2.15.0, fase 8 del piano Brevo (docs-sviluppo/brevo-confronto.md, capitolo 1.17): le automazioni. Provato come un utente
// nel pannello (tela, inneschi, azioni, regole, stato, impostazioni, modelli, attività e registro, editor a blocchi, 390 px)
// e con il motore spinto dal CLI e dal tempo spostato (automazioni-cli.php avanza): ogni innesco, azione e regola, più
// inneschi, divisione a più rami e in percentuale (proporzioni esatte su 200 contatti), attendi un evento con il massimo,
// rientro con l'attesa, uscita in mezzo a un'attesa, In pausa che lascia proseguire, Inattiva che toglie, webhook verso un
// server finto locale (solo con CMS_WEBHOOK_LOCALE, che la prova scrive nel config.local.php e poi toglie), statistiche per
// passo contate a mano.
// uso: node prove/prova-automazioni.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella CMS> [cartella foto]
// Porte del webhook finto: AU_WEBHOOK (9254) e quella dopo (il server "interno" che non deve ricevere niente).
// Il server di posta finto: python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> &
// Pulisce prima e dopo i suoi dati (AU ...): contatti @au-prova.it, liste, automazioni e segmenti "AU ".
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync, spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const [B, UTENTE, PASSWORD, SMTP, POSTA, DIR, FOTO] = process.argv.slice(2);
const CLI = path.join(__dirname, 'automazioni-cli.php');
const WH = +(process.env.AU_WEBHOOK || 9254);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (!c && extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 700) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [CLI, DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 256 << 20 }).trim().split('\n').pop());
const q = (sql) => cli('sql', sql);
const uno = (sql) => { const r = q(sql); return r && r[0] ? Object.values(r[0])[0] : null; };
const num = (sql) => { const v = uno(sql); return v === null ? NaN : +v; };
const dorme = ms => new Promise(r => setTimeout(r, ms));
const CONFIG = path.join(DIR, 'includes', 'config.local.php');
const configPrima = fs.readFileSync(CONFIG, 'utf8');

function leggi(eml) {
  const i = eml.search(/\r?\n\r?\n/);
  const testa = eml.slice(0, i).replace(/\r?\n[ \t]+/g, ' '); const corpo = eml.slice(i).replace(/^\r?\n\r?\n/, '');
  const h = (t, nome) => ((t.match(new RegExp('^' + nome + ':[ \\t]*(.*)$', 'mi')) || [])[1] || '').trim();
  const dec = s => s.replace(/=\?UTF-8\?B\?([^?]+)\?=/gi, (_, b) => Buffer.from(b, 'base64').toString('utf8'));
  const parte = (t, c) => /base64/i.test(h(t, 'Content-Transfer-Encoding')) ? Buffer.from(c.replace(/\s+/g, ''), 'base64').toString('utf8') : c;
  let html = '';
  const b = (h(testa, 'Content-Type').match(/boundary="([^"]+)"/) || [])[1];
  if (b) for (const p of corpo.split('--' + b).slice(1)) {
    const j = p.search(/\r?\n\r?\n/); if (j < 0) continue;
    if (/text\/html/i.test(h(p.slice(0, j).trim(), 'Content-Type'))) html = parte(p.slice(0, j).trim(), p.slice(j).trim());
  } else html = parte(testa, corpo);
  return { dest: h(testa, 'X-Destinatari'), oggetto: dec(h(testa, 'Subject')), html };
}
const posta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => leggi(fs.readFileSync(path.join(POSTA, f), 'utf8')));
const ricevute = (chi) => posta().filter(m => m.dest.startsWith(chi + '@')).map(m => m.oggetto);
const giro = (volte = 1) => { let l = []; for (let i = 0; i < volte; i++) l = l.concat(cli('giro')); return l; };
const lista = (nome) => cli('lista', nome);
const inLista = (l, id) => num(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE lista_id = ${l} AND iscritto_id = ${id}`) === 1;
const corsi = (f, id) => q(`SELECT * FROM {p}newsletter_corsi WHERE flusso_id = ${f}${id ? ' AND iscritto_id = ' + id : ''} ORDER BY id`);
// Un'automazione dal CLI: inneschi, nodi [id, tipo, dati], archi [da, uscita, a], altro (stato, rientro...).
const nodo = (id, tipo, dati = {}) => ({ id, tipo, x: 40 + (id % 4) * 260, y: 40 + id * 120, dati });
const em = (id, ogg, extra = {}) => nodo(id, 'email', Object.assign({ oggetto: ogg, anteprima: '', corpo: '<p>Ciao {nome}, «' + ogg + '».</p><p><a href="https://esempio.it/pagina">Leggi</a></p>', pulsante_testo: '', pulsante_link: '', ascolto: 0 }, extra));
const automazione = (nome, inneschi, nodi, archi, altro = {}) => {
  const r = cli('salva', JSON.stringify(Object.assign({ nome, stato: 'attiva', inneschi, grafo: { nodi: [nodo(0, 'innesco')].concat(nodi), archi: archi.map(([da, uscita, a]) => ({ da, uscita, a })) } }, altro)));
  if (typeof r !== 'number') console.log('     automazione ' + nome + ' non salvata: ' + JSON.stringify(r));
  return r;
};

// Il webhook finto, in un processo suo (le chiamate al CLI fermano questo): /ok risponde 200 con un corpo lungo; /rimanda
// risponde 302 verso il secondo server ("interno"), che non deve ricevere niente. Ogni arrivo è una riga JSON nel file.
const ARRIVI = path.join(POSTA, 'webhook-arrivi.jsonl');
const SERVER = `const http = require('http'), fs = require('fs'); const [porta, file] = process.argv.slice(1);
  const scrivi = (o) => fs.appendFileSync(file, JSON.stringify(o) + '\\n');
  http.createServer((req, res) => { let c = ''; req.on('data', d => { c += d; }); req.on('end', () => {
    scrivi({ porta: +porta, url: req.url, metodo: req.method, tipo: req.headers['content-type'], firma: req.headers['x-plume-firma'], corpo: c });
    if (req.url === '/rimanda') { res.writeHead(302, { Location: 'http://127.0.0.1:' + (+porta + 1) + '/segreto' }); return res.end(); }
    res.writeHead(200, { 'Content-Type': 'text/plain' }); res.end('<b>ricevuto</b> ' + 'x'.repeat(3000)); }); }).listen(+porta, '127.0.0.1');`;
const arrivi = () => (fs.existsSync(ARRIVI) ? fs.readFileSync(ARRIVI, 'utf8').trim().split('\n').filter(Boolean).map(JSON.parse) : []);
const serverWh = [spawn(process.execPath, ['-e', SERVER, String(WH), ARRIVI], { stdio: 'ignore' }), spawn(process.execPath, ['-e', SERVER, String(WH + 1), ARRIVI], { stdio: 'ignore' })];

(async () => {
  try { fs.unlinkSync(ARRIVI); } catch (e) {}
  await dorme(500);
  cli('pulisci');
  cli('smtp', SMTP);
  cli('attr_testo', 'AU_NOTA');
  cli('attributo', 0, 'AU_DATA', ''); // crea l'attributo data (contatto 0: nessun valore)
  const L = {};
  for (const k of ['base', 'ui', 'inn', 'tolto', 'dv', 'roma', 'milano', 'pc', 'ri', 'us', 'esce', 'pa', 'ina', 'ev', 'st', 'wh', 'or', 'mi', 'de', 'd1']) L[k] = lista('AU ' + k);
  const vista = (k) => lista('AU Visti ' + k);

  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1440, height: 950 } });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  pnConferme.on(p, d => d.accept());
  const vai = u => p.goto(B + '/admin/' + u, { waitUntil: 'networkidle' });
  const flash = async () => (await p.locator('.flash, [role=alert], [role=status]').allInnerTexts()).join(' | ');
  const invia = async (sel) => { await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle' }), p.click(sel)]); };
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  // ===== 1. L'elenco, il nome di partenza, la tela disegnata come un utente
  await vai('newsletter-flussi.php');
  ok('menu: la voce si chiama Automazioni ed è accesa', await p.locator('.admin-sidebar a[aria-current], .admin-sidebar a.active').filter({ hasText: 'Automazioni' }).count() > 0 || await p.locator('.admin-sidebar a:has-text("Automazioni")').count() > 0);
  ok('elenco: Crea un\'automazione con Da zero e i tre modelli (benvenuto, anniversario, attività di marketing)', await p.locator('#fl-crea .fl-modello').count() === 4 && await p.locator('[data-modello=benvenuto]').count() === 1 && await p.locator('[data-modello=anniversario]').count() === 1 && await p.locator('[data-modello=riattivazione]').count() === 1);
  await p.click('a:has-text("Crea da zero")'); await p.waitForLoadState('networkidle');
  const nomeZero = await p.inputValue('#fl-nome');
  ok('da zero: il nome di partenza è "Automazione n. X"', /^Automazione n\. \d+$/.test(nomeZero), nomeZero);
  ok('da zero: stato di partenza In pausa, con le tre scelte', await p.inputValue('#fl-stato') === 'in_pausa' && (await p.locator('#fl-stato option').allInnerTexts()).join('|') === 'Attiva|In pausa|Inattiva');
  ok('pannello con le schede Inneschi, Azioni, Regole', (await p.locator('.fl-schede [role=tab]').allInnerTexts()).join('|') === 'Inneschi|Azioni|Regole');
  await p.fill('#fl-nome', 'AU Interfaccia'); await p.fill('#fl-tag', 'prova, benvenuto');
  await p.click('#fl-innesco-piu');
  await p.selectOption('#fi-v1', String(L.ui));
  ok('due inneschi nella scheda Inneschi (iscrizione e una lista)', await p.locator('.fl-innesco').count() === 2 && await p.inputValue('#fi-t1') === 'lista');
  const nodoClic = async (id) => { await p.locator(`.fl-nodo[data-id="${id}"] .fl-nodo-corpo`).click(); };
  const pezzo = async (scheda, tipo) => { await p.click('#fl-scheda-' + scheda); await p.click(`.fl-pezzo[data-tipo="${tipo}"]`); };
  await nodoClic(1); await p.fill('#fp-oggetto', 'AU UI benvenuto');
  await pezzo('regole', 'attesa'); await p.fill('#fp-n', '1'); await p.selectOption('#fp-u', '1');           // #2
  await pezzo('regole', 'evento');                                                                          // #3
  await pezzo('regole', 'dividi');                                                                          // #4 (da evento Sì)
  await p.fill('#fp-rn0', 'Iscritti'); await p.click('#fp-rami [data-aggiungi="0"]');
  await nodoClic(3); await pezzo('regole', 'percento');                                                     // #5 (da evento No)
  await pezzo('azioni', 'lista_aggiungi'); await p.selectOption('#fp-lista', String(L.ui));                 // #6 (da p0)
  await nodoClic(5); await pezzo('azioni', 'lista_togli'); await p.selectOption('#fp-lista', String(L.ui)); // #7 (da p1)
  await nodoClic(4); await pezzo('azioni', 'attributo'); await p.selectOption('#fp-attr', 'AU_NOTA'); await p.fill('#fp-av', 'vista'); // #8 (da r0)
  await nodoClic(4); await pezzo('azioni', 'webhook'); await p.fill('#fp-url', 'https://127.0.0.1/ricevi');  // #9 (da altro)
  await pezzo('regole', 'bivio'); await p.selectOption('#fp-cond', 'in_lista');                             // #10
  await pezzo('regole', 'fine');                                                                            // #11 (da Sì)
  ok('tela: undici blocchi più l\'innesco, aggiunti dalla tavolozza dopo il blocco scelto', await p.locator('.fl-nodo').count() === 12);
  // Il webhook verso 127.0.0.1 (senza la costante delle prove): il Salva lo rifiuta e la tela resta com'era.
  await invia('#fl-form button[value=salva]');
  ok('webhook verso 127.0.0.1: il Salva dice che porta a una rete interna, e il disegno resta', /rete interna/.test(await flash()) && await p.locator('.fl-nodo').count() === 12, await flash());
  await nodoClic(9); await p.fill('#fp-url', 'http://hooks.esempio.it/ricevi');
  await invia('#fl-form button[value=salva]');
  ok('webhook in http: il Salva chiede https', /https/.test(await flash()), await flash());
  await nodoClic(9); await p.fill('#fp-url', 'https://localhost/ricevi');
  await invia('#fl-form button[value=salva]');
  ok('webhook verso localhost: rifiutato', /questo server|rete interna/.test(await flash()), await flash());
  await nodoClic(9); await p.fill('#fp-url', 'https://hooks.esempio.it/ricevi');
  await nodoClic(5); await p.fill('#fp-p0', '40');
  await invia('#fl-form button[value=salva]');
  ok('divisione in percentuale a 90%: il Salva dice che le parti devono fare 100', /fa 90%/.test(await flash()), await flash());
  await nodoClic(5); await p.fill('#fp-p0', '50');
  await invia('#fl-form button[value=salva]');
  const fUi = num("SELECT id FROM {p}newsletter_flussi WHERE nome = 'AU Interfaccia'");
  ok('salvata: il messaggio dice In pausa', fUi > 0 && /In pausa|in pausa/.test(await flash()), await flash());
  const rUi = q(`SELECT * FROM {p}newsletter_flussi WHERE id = ${fUi}`)[0] || {};
  const gUi = JSON.parse(rUi.passi || '{"nodi":[],"archi":[]}');
  const tipiUi = gUi.nodi.map(x => x.tipo).sort().join(',');
  ok('nel database: tutti i tipi di blocco', tipiUi === 'attesa,attributo,bivio,dividi,email,evento,fine,innesco,lista_aggiungi,lista_togli,percento,webhook', tipiUi);
  const archi = gUi.archi.map(a => `${a.da}${a.uscita}${a.a}`).sort().join(' ');
  ok('nel database: i collegamenti fatti dal "+" sotto il blocco scelto', archi === ['0poi1', '1poi2', '2poi3', '3si4', '3no5', '5p06', '5p17', '4r08', '4altro9', '9poi10', '10si11'].sort().join(' '), archi);
  const nG = (id) => gUi.nodi.find(x => x.id === id) || { dati: {} };
  ok('nel database: attesa 1 minuto, attendi un evento con il massimo, ramo con la condizione, attributo, webhook', nG(2).dati.minuti === 1 && nG(3).dati.evento === 'apertura' && nG(3).dati.massimo === 4320 && nG(4).dati.rami[0].nome === 'Iscritti' && nG(4).dati.rami[0].regole.gruppi[0].condizioni[0].campo === 'stato' && nG(8).dati.nome === 'AU_NOTA' && nG(8).dati.valore === 'vista' && nG(9).dati.url === 'https://hooks.esempio.it/ricevi', gUi.nodi.map(x => x.dati));
  ok('nel database: stato, tag, due inneschi, attivo scritto insieme allo stato', rUi.stato === 'in_pausa' && +rUi.attivo === 0 && rUi.tag === 'prova, benvenuto' && JSON.parse(rUi.inneschi).length === 2 && JSON.parse(rUi.inneschi)[1].id === L.ui, rUi);
  ok('un giro chiuso fatto a mano nel JSON: rifiutato', /giro chiuso/.test(JSON.stringify(cli('salva', JSON.stringify({ nome: 'AU Giro', stato: 'attiva', inneschi: [{ tipo: 'lista', id: L.ui }], grafo: { nodi: [nodo(0, 'innesco'), em(1, 'a'), nodo(2, 'attesa', { minuti: 5 })], archi: [{ da: 0, uscita: 'poi', a: 1 }, { da: 1, uscita: 'poi', a: 2 }, { da: 2, uscita: 'poi', a: 1 }] } })))));
  // Impostazioni dalla tela: rientro e condizioni di uscita.
  await vai('newsletter-flussi.php?id=' + fUi);
  await p.click('#fl-impostazioni > summary');
  await p.check('#fl-rientro'); await p.fill('#fl-rientro-ore', '24'); await p.check('[name=uscita_disiscritto]');
  await p.selectOption('#fl-stato', 'attiva');
  await invia('#fl-form button[value=salva]');
  const rUi2 = q(`SELECT stato, attivo, rientro, rientro_attesa, uscita FROM {p}newsletter_flussi WHERE id = ${fUi}`)[0];
  ok('impostazioni: rientro dopo 24 ore e uscita alla disiscrizione salvati; Attiva con attivo = 1', rUi2.stato === 'attiva' && +rUi2.attivo === 1 && +rUi2.rientro === 1 && +rUi2.rientro_attesa === 24 && JSON.parse(rUi2.uscita).disiscritto === true, rUi2);
  const tUi = await p.locator('#fl-tela').innerText();
  ok('la tela si riapre con i blocchi e i rami con il loro nome', /Iscritti/.test(tUi) && /Altrimenti/.test(tUi) && /50%/.test(tUi), tUi.slice(0, 300));
  // L'automazione dell'interfaccia non serve più: Inattiva (nessuno dentro).
  await p.selectOption('#fl-stato', 'inattiva'); await invia('#fl-form button[value=salva]');

  // ===== 2. Ogni innesco (e più inneschi)
  const solo = (k, inneschi) => automazione('AU Inn ' + k, inneschi, [nodo(1, 'lista_aggiungi', { lista: vista(k) })], [[0, 'poi', 1]]);
  const C = {};
  const persona = (k, l = 0) => (C[k] = cli('contatto', k + '@au-prova.it', 'Persona ' + k, l));
  const fInn = {
    iscrizione: solo('iscrizione', [{ tipo: 'iscrizione', id: 0 }]), lista: solo('lista', [{ tipo: 'lista', id: L.inn }]), lista_tolto: solo('lista_tolto', [{ tipo: 'lista_tolto', id: L.tolto }]),
    form: solo('form', [{ tipo: 'form', id: 77 }]), apertura: solo('apertura', [{ tipo: 'apertura', id: 55 }]), clic: solo('clic', [{ tipo: 'clic', id: 0 }]),
    inattivo: solo('inattivo', [{ tipo: 'inattivo', id: 30 }]), disiscritto: solo('disiscritto', [{ tipo: 'disiscritto', id: 0 }]),
    anniversario: solo('anniversario', [{ tipo: 'anniversario', id: 0, attr: 'AU_DATA' }]), anniv2: solo('anniv2', [{ tipo: 'anniversario', id: 2, attr: 'AU_DATA' }]),
    doppio: solo('doppio', [{ tipo: 'lista', id: L.d1 }, { tipo: 'form', id: 88 }]),
  };
  ok('le automazioni degli inneschi si salvano', Object.values(fInn).every(x => typeof x === 'number'), fInn);
  persona('ci1'); cli('innesco', 'iscrizione', 0, C.ci1);
  persona('cl1', L.inn);
  persona('ct1', L.tolto); cli('togli_lista', C.ct1, L.tolto);
  persona('cf1'); cli('innesco', 'form', 77, C.cf1); persona('cf2'); cli('innesco', 'form', 78, C.cf2);
  persona('ca1'); cli('innesco', 'apertura', 55, C.ca1); persona('ca2'); cli('innesco', 'apertura', 56, C.ca2);
  persona('cc1'); cli('innesco', 'clic', 99, C.cc1);
  persona('cin'); q(`UPDATE {p}newsletter_iscritti SET consenso_at = NOW() - INTERVAL 40 DAY, creato = NOW() - INTERVAL 40 DAY WHERE id = ${C.cin}`);
  persona('cd1'); cli('esci', C.cd1);
  const oggi = new Date(), d2 = new Date(Date.now() + 2 * 86400000), f2 = (d) => `${d.getFullYear() - 5}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  persona('can'); cli('attributo', C.can, 'AU_DATA', f2(oggi));
  persona('ca3'); cli('attributo', C.ca3, 'AU_DATA', f2(d2));
  persona('cdp', L.d1); cli('innesco', 'form', 88, C.cdp);
  persona('cdf'); cli('innesco', 'form', 88, C.cdf);
  giro();
  const visto = (k, chi) => inLista(vista(k), C[chi]);
  ok('innesco "si iscrive": entra', visto('iscrizione', 'ci1'));
  ok('innesco "entra in una lista": entra', visto('lista', 'cl1'));
  ok('innesco "viene tolto da una lista" (nuovo): entra', visto('lista_tolto', 'ct1'));
  ok('innesco "compila un form": entra solo per quel form', visto('form', 'cf1') && !visto('form', 'cf2'));
  ok('innesco "apre una campagna": entra solo per quella campagna; "fa clic", qualsiasi campagna', visto('apertura', 'ca1') && !visto('apertura', 'ca2') && visto('clic', 'cc1'));
  ok('innesco "non apre e non clicca da 30 giorni": entra chi è iscritto da 40 giorni senza aperture', visto('inattivo', 'cin') && !visto('inattivo', 'ci1'));
  ok('innesco "si disiscrive" (nuovo): entra chi esce, e le azioni lo raggiungono', visto('disiscritto', 'cd1') && num(`SELECT COUNT(*) FROM {p}newsletter_flussi_registro WHERE flusso_id = ${fInn.disiscritto} AND evento = 'entrato'`) === 1);
  ok('innesco "anniversario" (nuovo): entra il giorno della data; 2 giorni prima entra chi la compie fra 2 giorni', visto('anniversario', 'can') && !visto('anniversario', 'ca3') && visto('anniv2', 'ca3') && !visto('anniv2', 'can'));
  giro();
  ok('anniversario: una volta sola, anche al giro dopo', corsi(fInn.anniversario, C.can).length === 1);
  ok('più inneschi: entra dalla lista o dal form, una volta sola per giro', visto('doppio', 'cdp') && visto('doppio', 'cdf') && corsi(fInn.doppio, C.cdp).length === 1);
  ok('registro: chi è entrato e da quale innesco', /Viene tolto da una lista/.test(uno(`SELECT dettaglio FROM {p}newsletter_flussi_registro WHERE flusso_id = ${fInn.lista_tolto} AND evento = 'entrato'`) || ''));

  // ===== 3. Attendi un evento con il massimo
  const fEv = automazione('AU Evento', [{ tipo: 'lista', id: L.ev }], [em(1, 'AU ev email'), nodo(2, 'evento', { evento: 'apertura', email: 1, massimo: 60 }), nodo(3, 'lista_aggiungi', { lista: vista('ev si') }), nodo(4, 'lista_aggiungi', { lista: vista('ev no') })],
    [[0, 'poi', 1], [1, 'poi', 2], [2, 'si', 3], [2, 'no', 4]]);
  persona('e1', L.ev); persona('e2', L.ev); persona('e3', L.ev);
  giro();
  ok('attendi un evento: le email partono e tutti aspettano (ascolto evento, massimo 60 minuti)', ['e1', 'e2', 'e3'].every(k => ricevute(k).includes('AU ev email')) && corsi(fEv).every(c => c.ascolto === 'evento'));
  cli('evento', 'apertura', fEv, 1, C.e1); cli('evento', 'clic', fEv, 1, C.e3);
  giro();
  ok('attendi un evento: chi apre (e chi clicca) esce subito da "Se succede"', inLista(vista('ev si'), C.e1) && inLista(vista('ev si'), C.e3) && !inLista(vista('ev si'), C.e2));
  cli('avanza', 30, fEv); giro();
  ok('attendi un evento: a metà del massimo chi non ha aperto aspetta ancora', corsi(fEv, C.e2)[0].stato === 'attivo' && !inLista(vista('ev no'), C.e2));
  cli('avanza', 31, fEv); giro();
  ok('attendi un evento: passato il massimo esce da "Se non succede"', inLista(vista('ev no'), C.e2) && corsi(fEv, C.e2)[0].stato === 'finito');
  // Già successo prima di arrivare: esce subito dal Sì.
  const fEv2 = automazione('AU Evento prima', [{ tipo: 'lista', id: L.base }], [em(1, 'AU ev2 email', { ascolto: 0 }), nodo(2, 'attesa', { minuti: 5 }), nodo(3, 'evento', { evento: 'apertura', email: 1, massimo: 600 }), nodo(4, 'lista_aggiungi', { lista: vista('ev2 si') })],
    [[0, 'poi', 1], [1, 'poi', 2], [2, 'poi', 3], [3, 'si', 4]]);
  persona('e4', L.base); giro(); cli('evento', 'apertura', fEv2, 1, C.e4); cli('avanza', 6, fEv2); giro();
  ok('attendi un evento: se era già successo da quando è entrato, esce subito da "Se succede"', inLista(vista('ev2 si'), C.e4));

  // ===== 4. Divisione condizionale a più rami (motore dei segmenti) e in percentuale
  const regLista = (l) => ({ op: 'e', gruppi: [{ op: 'e', condizioni: [{ campo: 'lista', op: 'in', valore: [l] }] }] });
  const fDv = automazione('AU Dividi', [{ tipo: 'lista', id: L.dv }], [nodo(1, 'dividi', { rami: [{ nome: 'Roma', regole: regLista(L.roma) }, { nome: 'Milano', regole: regLista(L.milano) }] }),
    nodo(2, 'lista_aggiungi', { lista: vista('dv roma') }), nodo(3, 'lista_aggiungi', { lista: vista('dv milano') }), nodo(4, 'lista_aggiungi', { lista: vista('dv altro') })],
  [[0, 'poi', 1], [1, 'r0', 2], [1, 'r1', 3], [1, 'altro', 4]]);
  const D = cli('molti', 'dv', 30);
  q(`INSERT IGNORE INTO {p}newsletter_iscritti_liste (iscritto_id, lista_id, aggiunto) SELECT id, ${L.roma}, NOW() FROM {p}newsletter_iscritti WHERE id IN (${D.slice(0, 10).join(',')})`);
  q(`INSERT IGNORE INTO {p}newsletter_iscritti_liste (iscritto_id, lista_id, aggiunto) SELECT id, ${L.milano}, NOW() FROM {p}newsletter_iscritti WHERE id IN (${D.slice(8, 16).join(',')})`);
  cli('in_lista', L.dv, D.join(','));
  giro();
  const conta = (l) => num(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE lista_id = ${l}`);
  ok('divisione a più rami: 30 contatti, Roma 10 (dv01-10, anche i due che stanno in tutte e due le liste: vale il primo ramo), Milano 6, altrimenti 14', conta(vista('dv roma')) === 10 && conta(vista('dv milano')) === 6 && conta(vista('dv altro')) === 14, [conta(vista('dv roma')), conta(vista('dv milano')), conta(vista('dv altro'))]);
  ok('divisione a più rami: proprio quelli giusti', D.slice(0, 10).every(id => inLista(vista('dv roma'), id)) && D.slice(10, 16).every(id => inLista(vista('dv milano'), id)) && D.slice(16).every(id => inLista(vista('dv altro'), id)));
  const fPc = automazione('AU Percento', [{ tipo: 'lista', id: L.pc }], [nodo(1, 'percento', { rami: [50, 30, 20] })], [[0, 'poi', 1]]);
  const P = cli('molti', 'pc', 200);
  cli('in_lista', L.pc, P.join(','));
  giro(); giro();
  const npc = cli('numeri', fPc).nodi;
  ok('divisione in percentuale 50/30/20 su 200 contatti: esattamente 100, 60, 40', npc[1] && npc[1].p0 === 100 && npc[1].p1 === 60 && npc[1].p2 === 40, npc[1]);
  ok('divisione in percentuale: un ramo libero chiude il cammino (200 finiti)', num(`SELECT COUNT(*) FROM {p}newsletter_corsi WHERE flusso_id = ${fPc} AND stato = 'finito'`) === 200);

  // ===== 5. Azioni (lista, togli, attributo) e rientro con attesa
  const fAz = automazione('AU Azioni', [{ tipo: 'lista', id: L.base }], [nodo(1, 'lista_aggiungi', { lista: vista('az') }), nodo(2, 'lista_togli', { lista: L.base }), nodo(3, 'attributo', { nome: 'AU_NOTA', valore: 'cliente fedele' })], [[0, 'poi', 1], [1, 'poi', 2], [2, 'poi', 3]]);
  persona('az1', L.base); giro();
  ok('azioni: aggiunto a una lista, tolto da un\'altra, attributo aggiornato', inLista(vista('az'), C.az1) && !inLista(L.base, C.az1) && uno(`SELECT v.v_testo FROM {p}newsletter_valori v JOIN {p}newsletter_attributi a ON a.id = v.attributo_id WHERE a.nome = 'AU_NOTA' AND v.contatto_id = ${C.az1}`) === 'cliente fedele');
  ok('azioni: il registro dice cosa ha fatto ogni azione', /aggiunto alla lista AU Visti az.*tolto dalla lista AU base.*AU_NOTA = cliente fedele/.test(q(`SELECT dettaglio FROM {p}newsletter_flussi_registro WHERE flusso_id = ${fAz} AND evento = 'azione' ORDER BY id`).map(r => r.dettaglio).join(' / ')));
  const fRi = automazione('AU Rientro', [{ tipo: 'lista', id: L.ri }], [nodo(1, 'lista_aggiungi', { lista: vista('ri') })], [[0, 'poi', 1]], { rientro: 1, rientro_attesa: 1 });
  const fNo = automazione('AU Senza rientro', [{ tipo: 'lista', id: L.ri }], [nodo(1, 'lista_aggiungi', { lista: vista('nori') })], [[0, 'poi', 1]]);
  persona('ri1', L.ri); giro();
  const rientra = () => { cli('togli_lista', C.ri1, L.ri); cli('in_lista', L.ri, C.ri1); };
  rientra(); giro();
  ok('rientro: chi ha appena finito non rientra prima dell\'ora di attesa', corsi(fRi, C.ri1).length === 1);
  cli('avanza', 61, fRi); cli('avanza', 61, fNo); rientra(); giro();
  const cr = corsi(fRi, C.ri1);
  ok('rientro: passata l\'ora rientra, con il giro 2', cr.length === 2 && +cr[1].giro === 2 && cr[1].stato === 'finito', cr);
  ok('senza rientro: una volta sola', corsi(fNo, C.ri1).length === 1);

  // ===== 6. Uscita in mezzo a un'attesa (segmento e disiscrizione)
  const sEsce = cli('segmento', 'AU Esce', L.esce);
  const fUs = automazione('AU Uscita', [{ tipo: 'lista', id: L.us }], [nodo(1, 'attesa', { minuti: 1440 }), em(2, 'AU dopo attesa')], [[0, 'poi', 1], [1, 'poi', 2]], { uscita_segmento: sEsce, uscita_disiscritto: 1 });
  persona('u1', L.us); persona('u2', L.us); persona('u3', L.us); giro();
  ok('uscita: tutti e tre in attesa (un giorno)', corsi(fUs).every(c => c.stato === 'attivo' && c.ascolto === 'attesa'));
  cli('in_lista', L.esce, C.u1); cli('esci', C.u2); giro();
  ok('uscita: chi entra nel segmento esce durante l\'attesa', corsi(fUs, C.u1)[0].stato === 'tolto' && /segmento AU Esce/.test(uno(`SELECT dettaglio FROM {p}newsletter_flussi_registro WHERE flusso_id = ${fUs} AND iscritto_id = ${C.u1} AND evento = 'uscito'`) || ''));
  ok('uscita: chi si disiscrive esce durante l\'attesa', corsi(fUs, C.u2)[0].stato === 'tolto' && num(`SELECT COUNT(*) FROM {p}newsletter_flussi_registro WHERE flusso_id = ${fUs} AND iscritto_id = ${C.u2} AND evento = 'uscito'`) === 1);
  cli('avanza', 1441, fUs); giro();
  ok('uscita: alla fine dell\'attesa l\'email arriva solo a chi è rimasto', ricevute('u3').includes('AU dopo attesa') && !ricevute('u1').includes('AU dopo attesa') && !ricevute('u2').includes('AU dopo attesa'));

  // ===== 7. In pausa lascia proseguire, Inattiva toglie (dal pannello)
  const fPa = automazione('AU Pausa', [{ tipo: 'lista', id: L.pa }], [nodo(1, 'attesa', { minuti: 60 }), em(2, 'AU pausa email')], [[0, 'poi', 1], [1, 'poi', 2]]);
  const fIn = automazione('AU Inattiva', [{ tipo: 'lista', id: L.ina }], [nodo(1, 'attesa', { minuti: 60 }), em(2, 'AU inattiva email')], [[0, 'poi', 1], [1, 'poi', 2]]);
  persona('pa1', L.pa); persona('in1', L.ina); giro();
  await vai('newsletter-flussi.php?id=' + fPa); await p.selectOption('#fl-stato', 'in_pausa'); await invia('#fl-form button[value=salva]');
  ok('In pausa dal pannello: il messaggio lo spiega', /non entra nessuno di nuovo, chi è dentro prosegue/.test(await flash()), await flash());
  await vai('newsletter-flussi.php?id=' + fIn); await p.selectOption('#fl-stato', 'inattiva'); await invia('#fl-form button[value=salva]');
  ok('Inattiva dal pannello: chi era dentro è tolto subito', corsi(fIn, C.in1)[0].stato === 'tolto' && /tolto/.test(await flash()));
  persona('pa2', L.pa); cli('avanza', 61, fPa); cli('avanza', 61, fIn); giro();
  ok('In pausa: non entra nessuno di nuovo', corsi(fPa, C.pa2).length === 0);
  ok('In pausa: chi era dentro prosegue e riceve l\'email', ricevute('pa1').includes('AU pausa email'));
  ok('Inattiva: chi era dentro non riceve niente', !ricevute('in1').includes('AU inattiva email'));

  // ===== 8. Webhook verso un server finto locale (solo con la costante delle prove)
  fs.writeFileSync(CONFIG, configPrima.replace(/\?>\s*$/, '') + `\ndefine('CMS_WEBHOOK_LOCALE', '127.0.0.1:${WH}');\n`);
  const fWh = automazione('AU Webhook', [{ tipo: 'lista', id: L.wh }], [nodo(1, 'webhook', { url: `http://127.0.0.1:${WH}/ok` }), nodo(2, 'webhook', { url: `http://127.0.0.1:${WH}/rimanda` }), nodo(3, 'lista_aggiungi', { lista: vista('wh') })], [[0, 'poi', 1], [1, 'poi', 2], [2, 'poi', 3]]);
  persona('w1', L.wh); giro();
  const a1 = arrivi().find(x => x.url === '/ok');
  const interni = arrivi().filter(x => x.porta === WH + 1);
  let json = {}; try { json = JSON.parse(a1 ? a1.corpo : '{}'); } catch (e) {}
  ok('webhook (modalità prova): POST JSON con l\'automazione e il contatto, firmato', !!a1 && a1.metodo === 'POST' && /application\/json/.test(a1.tipo) && json.contatto && json.contatto.email === 'w1@au-prova.it' && json.automazione.nome === 'AU Webhook' && /^sha256=[0-9a-f]{64}$/.test(a1.firma || ''), a1);
  const regWh = q(`SELECT nodo, dettaglio FROM {p}newsletter_flussi_registro WHERE flusso_id = ${fWh} AND evento = 'azione' ORDER BY id`);
  ok('webhook: nel registro la risposta 200 e solo l\'inizio del corpo (senza tag, al massimo 120 caratteri)', regWh[0] && /risposta 200 \(ricevuto\): ricevuto x+$/.test(regWh[0].dettaglio) && regWh[0].dettaglio.length < 180 && !/<b>/.test(regWh[0].dettaglio), regWh);
  ok('webhook: un rimando non si segue (il server "interno" non riceve niente), e il cammino prosegue', regWh[1] && /non lo seguo/.test(regWh[1].dettaglio) && interni.length === 0 && inLista(vista('wh'), C.w1), [regWh, interni]);
  fs.writeFileSync(CONFIG, configPrima);
  persona('w2', L.wh); const prima = arrivi().length; giro();
  ok('webhook: senza la costante delle prove 127.0.0.1 non si chiama più, il registro lo dice', arrivi().length === prima && /non chiamato/.test(uno(`SELECT dettaglio FROM {p}newsletter_flussi_registro WHERE flusso_id = ${fWh} AND iscritto_id = ${C.w2} AND evento = 'azione' ORDER BY id LIMIT 1`) || ''));

  // ===== 9. Statistiche per passo (contate a mano), attività, registro, togli, aggiungi a mano
  const fSt = automazione('AU Statistiche', [{ tipo: 'lista', id: L.st }], [em(1, 'AU stat email'), nodo(2, 'attesa', { minuti: 1440 })], [[0, 'poi', 1], [1, 'poi', 2]]);
  for (const k of ['s1', 's2', 's3', 's4', 's5']) persona(k, L.st);
  giro();
  cli('evento', 'apertura', fSt, 1, C.s1); cli('evento', 'apertura', fSt, 1, C.s1); cli('evento', 'apertura', fSt, 1, C.s2); cli('evento', 'clic', fSt, 1, C.s3);
  // A mano: 5 inviate; aperture uniche 3 (s1, s2, s3 con il clic), totali 4 (s1 due volte); clic 1. Tassi sulle consegnate: 60% e 20%.
  const nst = cli('numeri', fSt);
  const e1 = nst.nodi[1].email;
  ok('statistiche per passo: 5 inviate, 3 aperture (4 in tutto), 1 clic, 60% e 20%', e1.inviate === 5 && e1.apertura === 3 && e1.apertura_tot === 4 && e1.clic === 1 && e1.t_apertura === 60 && e1.t_clic === 20, e1);
  ok('statistiche per passo: all\'attesa 5 passati e 5 lì adesso; in tutto 5 entrati', nst.nodi[2].passati === 5 && nst.nodi[2].qui === 5 && nst.tot.entrati === 5 && nst.tot.attivo === 5, nst);
  await vai('newsletter-flussi.php?id=' + fSt);
  const testoTela = await p.locator('#fl-tela').innerText();
  ok('sulla tela: "5 inviate", "3 aperture (60%)", "1 clic (20%)", "5 qui adesso"', /5 inviate/.test(testoTela) && /3 aperture \(60%\)/.test(testoTela) && /1 clic \(20%\)/.test(testoTela) && /5 qui adesso/.test(testoTela), testoTela);
  ok('sopra la tela: Entrati 5, In corso 5', /Entrati 5 · In corso 5/.test(await p.locator('#fl-numeri').innerText()));
  if (FOTO) await p.screenshot({ path: path.join(FOTO, 'automazione-tela.png') });
  await vai(`newsletter-flussi.php?id=${fSt}&scheda=attivita`);
  ok('attività: chi è dentro adesso, 5 righe', await p.locator('#fl-dentro tbody tr[data-corso]').count() === 5);
  await p.selectOption('#fl-passo', '1'); await p.waitForLoadState('networkidle');
  ok('attività: filtro per passo (all\'email non c\'è nessuno) con la frase', await p.locator('#fl-dentro tbody tr[data-corso]').count() === 0 && /Nessuno a questo passo/.test(await p.locator('#fl-dentro').innerText()));
  await vai(`newsletter-flussi.php?id=${fSt}&scheda=attivita&passo=2`);
  const corsoS5 = num(`SELECT id FROM {p}newsletter_corsi WHERE flusso_id = ${fSt} AND iscritto_id = ${C.s5}`);
  await invia(`tr[data-corso="${corsoS5}"] button:has-text("Togli")`);
  ok('attività: "Togli" con la conferma toglie il contatto', corsi(fSt, C.s5)[0].stato === 'tolto' && /tolto/.test(await flash()) && await p.locator('#fl-dentro tbody tr[data-corso]').count() === 4);
  ok('registro: la riga "Tolto" con "tolto a mano"', await p.locator('#fl-registro tr[data-evento=tolto]').filter({ hasText: 'tolto a mano' }).count() === 1);
  await p.fill('#fl-cerca', 's1@au-prova.it'); await invia('#fl-registro button[type=submit]');
  const regS1 = await p.locator('#fl-registro tbody').innerText();
  ok('registro: cercato un contatto, la sua storia (entrato, email, attesa)', /È entrato/.test(regS1) && /AU stat email/.test(regS1) && /Attesa/.test(regS1) && !/s2@/.test(regS1), regS1);
  ok('aggiungi a mano: senza l\'innesco la pagina dice come fare', /Aggiunto a mano dal pannello/.test(await p.locator('#fl-aggiungi').innerText()));
  const fMa = automazione('AU Mano', [{ tipo: 'mano', id: 0 }], [nodo(1, 'lista_aggiungi', { lista: vista('mano') })], [[0, 'poi', 1]]);
  persona('m1');
  await vai(`newsletter-flussi.php?id=${fMa}&scheda=attivita`);
  await p.fill('#fl-email-mano', 'm1@au-prova.it'); await invia('#fl-aggiungi button[type=submit]');
  ok('aggiungi a mano: il contatto entra', /Contatto aggiunto/.test(await flash()) && corsi(fMa, C.m1).length === 1);
  await p.fill('#fl-email-mano', 'nessuno@au-prova.it'); await invia('#fl-aggiungi button[type=submit]');
  ok('aggiungi a mano: un\'email che non c\'è lo dice', /Nessun contatto con questa email/.test(await flash()));
  giro();
  ok('aggiungi a mano: al giro la sua azione', inLista(vista('mano'), C.m1));

  // ===== 10. Passo Email: orario, orario migliore, editor a blocchi, modello
  const ora = +uno('SELECT HOUR(NOW())'), fra2 = (ora + 2) % 24;
  const fOr = automazione('AU Orario', [{ tipo: 'lista', id: L.or }], [em(1, 'AU alle ore', { orario: 'ora', ora: fra2 })], [[0, 'poi', 1]]);
  persona('o1', L.or); giro();
  const co = corsi(fOr, C.o1)[0];
  ok('email a un\'ora precisa: aspetta l\'ora (oggi o domani alle ' + fra2 + ')', !ricevute('o1').includes('AU alle ore') && co.ascolto === 'orario' && new Date(co.prossimo.replace(' ', 'T')).getHours() === fra2, co);
  cli('avanza', 180, fOr); giro();
  ok('email a un\'ora precisa: arrivata l\'ora parte', ricevute('o1').includes('AU alle ore'));
  const hM = (ora + 5) % 24;
  persona('o2'); persona('o3');
  q(`INSERT INTO {p}newsletter_eventi (campagna_id, flusso_id, passo, iscritto_id, tipo, url, creato) VALUES (9991, 0, 0, ${C.o2}, 'apertura', '', DATE_FORMAT(NOW() - INTERVAL 3 DAY, '%Y-%m-%d ${String(hM).padStart(2, '0')}:20:00')), (9992, 0, 0, ${C.o2}, 'apertura', '', DATE_FORMAT(NOW() - INTERVAL 5 DAY, '%Y-%m-%d ${String(hM).padStart(2, '0')}:40:00'))`);
  const fMi = automazione('AU Migliore', [{ tipo: 'lista', id: L.mi }], [em(1, 'AU migliore', { orario: 'migliore', ora: (ora + 7) % 24 })], [[0, 'poi', 1]]);
  cli('in_lista', L.mi, `${C.o2},${C.o3}`); giro();
  const h = (k) => new Date(corsi(fMi, C[k])[0].prossimo.replace(' ', 'T')).getHours();
  ok('email all\'orario migliore: chi apre alle ' + hM + ' riceve alle ' + hM + ', chi non ha dati all\'ora scelta', h('o2') === hM && h('o3') === (ora + 7) % 24, [h('o2'), h('o3')]);
  const fDe = automazione('AU Design', [{ tipo: 'lista', id: L.de }], [em(1, 'AU blocchi')], [[0, 'poi', 1]]);
  await vai('newsletter-flussi.php?id=' + fDe);
  await nodoClic(1);
  ok('passo Email: mittente, oggetto, anteprima, contenuto (semplice o a blocchi), orario', await p.locator('#fp-mitt option').count() >= 1 && await p.locator('#fp-anteprima').count() === 1 && await p.locator('[name=fp-editor]').count() === 2 && (await p.locator('#fp-orario option').count()) === 3);
  await p.check('[name=fp-editor][value=blocchi]');
  await Promise.all([p.waitForURL(/newsletter-design\.php\?flusso=\d+&nodo=1/, { timeout: 20000 }), p.click('#fp-apri-design')]);
  await p.waitForLoadState('networkidle');
  ok('"Salva e apri l\'editor a blocchi": si apre l\'editor delle email del passo', /Automazione «AU Design»: email #1/.test(await p.locator('.ed-nome').innerText()));
  await p.click('#nd-salva'); await p.waitForFunction(() => /Salvat|salvat/.test(document.getElementById('nd-stato').textContent), null, { timeout: 15000 });
  const dDe = JSON.parse(uno(`SELECT passi FROM {p}newsletter_flussi WHERE id = ${fDe}`)).nodi.find(x => x.id === 1).dati;
  ok('editor a blocchi: il design si salva nel passo', dDe.editor === 'blocchi' && dDe.design.length > 100 && /sezioni/.test(dDe.design), dDe.editor);
  await Promise.all([p.waitForNavigation(), p.click('.ed-indietro')]);
  ok('editor a blocchi: "Torna" riporta alla tela dell\'automazione', /newsletter-flussi\.php\?id=/.test(p.url()));
  await invia('#fl-form button[value=salva]');
  const dDe2 = JSON.parse(uno(`SELECT passi FROM {p}newsletter_flussi WHERE id = ${fDe}`)).nodi.find(x => x.id === 1).dati;
  ok('salvare la tela non cancella il design a blocchi', dDe2.design === dDe.design);
  persona('de1', L.de); giro();
  const mDe = posta().find(m => m.dest.startsWith('de1@'));
  ok('l\'email a blocchi parte, con il link per uscire e il pixel', !!mDe && mDe.oggetto === 'AU blocchi' && /newsletter-esci/.test(mDe.html) && /api\/nl\.php/.test(mDe.html), mDe && mDe.oggetto);
  const mod = cli('modello');
  await vai('newsletter-flussi.php?id=' + fDe); await nodoClic(1);
  await p.selectOption('#fp-modello', String(mod)); await invia('#fl-form button[value=salva]');
  ok('"Parti da un modello": il design del modello entra nel passo', JSON.parse(uno(`SELECT passi FROM {p}newsletter_flussi WHERE id = ${fDe}`)).nodi.find(x => x.id === 1).dati.design === uno(`SELECT design FROM {p}newsletter_modelli WHERE id = ${mod}`));

  // ===== 11. Modelli di automazione
  for (const [k, nome, nNodi] of [['benvenuto', 'Benvenuto', 6], ['anniversario', 'Anniversario', 2], ['riattivazione', 'Attività di marketing: chi non apre più', 6]]) {
    await vai('newsletter-flussi.php');
    await p.click(`[data-modello=${k}] a`); await p.waitForLoadState('networkidle');
    const nn = await p.locator('.fl-nodo').count();
    await p.fill('#fl-nome', 'AU Modello ' + k);
    await invia('#fl-form button[value=salva]');
    const rM = q(`SELECT * FROM {p}newsletter_flussi WHERE nome = 'AU Modello ${k}'`)[0];
    ok(`modello ${nome}: la tela si apre con ${nNodi} blocchi e si salva (in pausa)`, nn === nNodi && !!rM && rM.stato === 'in_pausa' && JSON.parse(rM.passi).nodi.length === nNodi, [nn, await flash()]);
  }
  ok('modello Anniversario: l\'innesco usa l\'attributo data', JSON.parse(uno("SELECT inneschi FROM {p}newsletter_flussi WHERE nome = 'AU Modello anniversario'"))[0].attr === 'AU_DATA');

  // ===== 12. Elenco e 390 px
  await vai('newsletter-flussi.php');
  const riga = await p.locator(`tr[data-flusso="${fSt}"]`).innerText();
  ok('elenco: la riga con inneschi, entrati, in corso e stato in parole', /Entra in una lista/.test(riga) && /\t5\t4\t/.test(riga.replace(/\n/g, '\t')) && /attiva/i.test(riga), riga);
  const tel = await br.newContext({ viewport: { width: 390, height: 800 } });
  const pt = await tel.newPage();
  pt.on('pageerror', e => err.push('390: ' + e.message.slice(0, 200)));
  await pt.goto(B + '/admin/login.php'); await pt.fill('#username', UTENTE); await pt.fill('#password', PASSWORD);
  await Promise.all([pt.waitForNavigation(), pt.click('button[type=submit]')]);
  const larghi = [];
  for (const u of ['newsletter-flussi.php', `newsletter-flussi.php?id=${fUi}`, `newsletter-flussi.php?id=${fSt}&scheda=attivita`, 'newsletter-flussi.php?nuovo=1&modello=benvenuto']) {
    await pt.goto(B + '/admin/' + u, { waitUntil: 'networkidle' });
    const w = await pt.evaluate(() => document.documentElement.scrollWidth);
    if (w > 390) larghi.push([u, w]);
  }
  ok('390 px: elenco, tela, attività e modello senza scorrimento di lato', larghi.length === 0, larghi);
  await pt.goto(B + '/admin/newsletter-flussi.php?id=' + fUi, { waitUntil: 'networkidle' });
  await pt.click('#fl-scheda-regole');
  ok('390 px: la scheda Regole si apre e mostra le regole', await pt.locator('#fl-p-regole .fl-pezzo').first().isVisible());
  if (FOTO) await pt.screenshot({ path: path.join(FOTO, 'automazione-390.png'), fullPage: true });
  ok('nessun errore JavaScript nelle pagine', err.length === 0, err);
  await br.close();
  cli('pulisci');
  serverWh.forEach(s => s.kill());
  console.log(`${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); try { fs.writeFileSync(CONFIG, configPrima); } catch (x) {} serverWh.forEach(s => s.kill()); console.log(`${n - ko} su ${n} OK (interrotta)`); process.exit(1); });
