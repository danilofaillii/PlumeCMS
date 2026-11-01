// 2.8.0, fase 1 del piano Brevo (docs-sviluppo/brevo-confronto.md): la base dei contatti provata come un utente.
// Contatti (colonne fisse, ricerca, Carica una lista, filtri, pagine, Personalizza le colonne, azioni di gruppo), Crea un
// contatto (doppione che porta all'esistente), scheda del contatto (Panoramica, Cronologia, Liste, Preferenze), attributi
// dei sei tipi più calcolati e globali (regole di Brevo), cartelle e liste, lista nera con il motivo (mettere, togliere uno
// alla volta, riempire a mano), 5 soft bounce di fila, cambio di email che toglie la lista nera, Elimina definitivamente con
// l'impronta, segnaposti {{ contact.X }} in una campagna, redattore che non vede e non salva, 390 px senza scorrimento.
// Con MIGRATO=1 controlla anche la migrazione di un sito 2.7.0 preparato con `contatti-cli.php semina` prima di aggiornare.
// Prima: `php prove/contatti-cli.php <cartella CMS> semina` (contatti nei cinque stati di prima, liste, campagne, flusso).
// uso: node prove/prova-contatti-brevo.js <base> <utente> <password> <cartella CMS installato> [cartella schermate]
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const [B, UTENTE, PASSWORD, DIR, FOTO] = process.argv.slice(2);
const CLI = path.join(__dirname, 'contatti-cli.php');
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [CLI, DIR, ...a], { encoding: 'utf8' }).trim().split('\n').pop());
const q = (sql) => cli('sql', sql);
const uno = (sql) => { const r = q(sql); return r && r[0] ? Object.values(r[0])[0] : null; };
const idDi = (e) => +uno(`SELECT id FROM {p}newsletter_iscritti WHERE email = '${e}'`);

(async () => {
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 }, acceptDownloads: true });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  pnConferme.on(p, d => d.accept());
  const vai = async (u) => { await p.goto(B + '/admin/' + u, { waitUntil: 'networkidle' }); };
  const testo = async (sel = '.admin-content') => (await p.locator(sel).first().innerText()).replace(/\s+/g, ' ');
  const flash = async () => (await p.locator('.flash, [role=alert], [role=status]').allInnerTexts()).join(' | ');
  const invia = async (sel) => { await Promise.all([p.waitForNavigation(), p.click(sel)]); };
  const foto = async (nome) => { if (FOTO) await p.screenshot({ path: path.join(FOTO, nome + '.png'), fullPage: true }); };

  // Pulizia di quello che una prova interrotta può aver lasciato (attributi e cartelle "PC").
  q("DELETE v FROM {p}newsletter_valori v JOIN {p}newsletter_attributi a ON a.id = v.attributo_id WHERE a.sistema = 0");
  q("DELETE o FROM {p}newsletter_attributi_opzioni o JOIN {p}newsletter_attributi a ON a.id = o.attributo_id WHERE a.sistema = 0");
  q('DELETE FROM {p}newsletter_attributi WHERE sistema = 0');
  q("DELETE FROM {p}newsletter_iscritti WHERE email LIKE '%@nuovi-contatti.it'");
  q("DELETE FROM {p}newsletter_soppressi");

  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  // ===== Migrazione (solo su un sito 2.7.0 aggiornato)
  if (process.env.MIGRATO) {
    const m = q("SELECT email, stato, blocco_motivo, blocco_at IS NOT NULL AS data FROM {p}newsletter_iscritti WHERE email LIKE '%@prova-contatti.it' ORDER BY id");
    const di = (e) => m.find(r => r.email === e + '@prova-contatti.it') || {};
    ok('migrazione: uscito -> lista nera con motivo unsubscribedViaEmail e la data', di('uscito1').stato === 'uscito' && di('uscito1').blocco_motivo === 'unsubscribedViaEmail' && +di('uscito1').data === 1, di('uscito1'));
    ok('migrazione: rimbalzato -> hardBounce', di('rimbalzato1').blocco_motivo === 'hardBounce' && +di('rimbalzato1').data === 1, di('rimbalzato1'));
    ok('migrazione: spam -> contactFlaggedAsSpam', di('spam1').blocco_motivo === 'contactFlaggedAsSpam', di('spam1'));
    ok('migrazione: iscritti e in attesa senza motivo', ['attivo1', 'attivo2', 'attesa1'].every(e => di(e).blocco_motivo === '' && ['attivo', 'in_attesa'].includes(di(e).stato)), m.map(r => r.stato));
    ok('migrazione: le liste di prima nella cartella "Le tue liste"', +uno("SELECT COUNT(*) FROM {p}newsletter_liste l JOIN {p}newsletter_cartelle c ON c.id = l.cartella_id WHERE c.nome = 'Le tue liste'") === +uno('SELECT COUNT(*) FROM {p}newsletter_liste'));
    ok('migrazione: registro del consenso dai dati di prima', +uno("SELECT COUNT(*) FROM {p}newsletter_consensi c JOIN {p}newsletter_iscritti i ON i.id = c.contatto_id WHERE i.email LIKE '%@prova-contatti.it'") === 7);
    ok('migrazione: DOUBLE_OPT-IN per chi aveva confermato dal modulo', +uno("SELECT COUNT(*) FROM {p}newsletter_iscritti WHERE email = 'attivo1@prova-contatti.it' AND doi_at IS NOT NULL") === 1);
    await vai('newsletter-contatti.php?stato=lista_nera');
    const t = await testo();
    ok('migrazione: la pagina Contatti mostra i tre in lista nera con il motivo', /uscito1@prova-contatti\.it/.test(t) && /Si è disiscritto dal link/.test(t) && /Hard bounce/.test(t) && /segnalato un'email come spam/.test(t), t.slice(0, 300));
  }

  // ===== Menu e indirizzi vecchi
  await vai('index.php');
  const menu = await p.locator('.admin-sidebar').textContent();
  ok('menu: Newsletter e form ha Contatti, Campagne email e Attributi dei contatti, non più "Iscritti e liste"', /Contatti/.test(menu) && /Campagne email/.test(menu) && /Attributi dei contatti/.test(menu) && !/Iscritti e liste/.test(menu));
  const r302 = await ctx.request.get(B + '/admin/newsletter.php?scheda=iscritti&stato=spam', { maxRedirects: 0 });
  ok('newsletter.php?scheda=iscritti rimanda con 302 e no-store alla pagina Contatti con i filtri', r302.status() === 302 && /newsletter-contatti\.php\?stato=spam/.test(r302.headers()['location'] || '') && /no-store/.test(r302.headers()['cache-control'] || ''), [r302.status(), r302.headers()['location']]);
  await vai('newsletter.php?scheda=liste');
  ok('newsletter.php?scheda=liste porta alla scheda Liste', p.url().includes('newsletter-contatti.php?scheda=liste'), p.url());

  // ===== Pagina Contatti
  await vai('newsletter-contatti.php');
  const th = (await p.locator('table.nlc-tabella thead th').allInnerTexts()).map(x => x.trim().toLowerCase());
  ok('le cinque colonne fisse di Brevo', ['contatto', 'iscritto', 'in lista nera', 'data di creazione', 'ultima modifica'].every(c => th.includes(c)), th);
  const righeTot = await p.locator('table.nlc-tabella tbody tr').count();
  ok('elenco con i contatti seminati', righeTot >= 8, righeTot);
  await foto('contatti');
  await p.selectOption('#nlc-stato', 'motivo:hardBounce'); await invia('.nlc-filtri button[type=submit]');
  let t = await testo('table.nlc-tabella');
  ok('filtro "In lista nera: hard bounce" mostra solo il rimbalzato', /rimbalzato1@/.test(t) && !/attivo1@/.test(t), t.slice(0, 200));
  await vai('newsletter-contatti.php?cerca=Rossi');
  t = await testo('table.nlc-tabella');
  ok('ricerca per cognome', /attivo1@/.test(t) && !/attivo2@/.test(t), t.slice(0, 200));
  await vai('newsletter-contatti.php?cerca=' + encodeURIComponent('zzz-niente'));
  ok('ricerca senza risultati: lo dice e suggerisce cosa fare', /Nessun contatto con questi filtri per «zzz-niente»/.test(await testo()));
  const clienti = +uno("SELECT id FROM {p}newsletter_liste WHERE nome = 'PC Clienti'");
  const eventi = +uno("SELECT id FROM {p}newsletter_liste WHERE nome = 'PC Eventi'");
  const vuota = +uno("SELECT id FROM {p}newsletter_liste WHERE nome = 'PC Vuota'");
  await vai('newsletter-contatti.php'); await p.selectOption('#nlc-lista', String(clienti)); await invia('.nlc-filtri button[type=submit]');
  ok('Carica una lista: solo i contatti della lista', await p.locator('table.nlc-tabella tbody tr').count() === 5);

  // ===== Attributi: i sei tipi, calcolati e globali
  const creaAttr = async (scheda, campi) => {
    await vai('newsletter-attributi.php' + (scheda ? '?scheda=' + scheda : ''));
    await p.click('button[data-apri-dialogo="#nla-crea"]');
    if (campi.tipo) await p.selectOption('#nla-tipo', campi.tipo);
    await p.fill('#nla-nome', campi.nome);
    if (campi.etichetta) await p.fill('#nla-etichetta', campi.etichetta);
    if (campi.opzioni) await p.fill('#nla-opzioni', campi.opzioni);
    if (campi.formula) await p.fill('#nla-formula', campi.formula);
    await invia('#nla-crea button[type=submit]');
    return flash();
  };
  for (const a of [['text', 'Città', 'citta'], ['float', 'Punti', 'punti'], ['date', 'Data di nascita', 'data nascita'], ['boolean', 'Cliente', 'cliente']]) {
    const f = await creaAttr('', { tipo: a[0], nome: a[2], etichetta: a[1] });
    ok('crea un attributo ' + a[0] + ' (nome scritto in minuscolo e con lo spazio: diventa maiuscolo)', /creato/.test(f), f);
  }
  await vai('newsletter-attributi.php'); await p.click('button[data-apri-dialogo="#nla-crea"]');
  ok('le opzioni compaiono solo per Categoria e Scelta multipla', await p.locator('[data-nla-opzioni]').isHidden() && (await p.selectOption('#nla-tipo', 'category'), await p.locator('[data-nla-opzioni]').isVisible()));
  ok('crea un attributo Categoria con le opzioni', /creato/.test(await creaAttr('', { tipo: 'category', nome: 'TIPO', etichetta: 'Tipo di cliente', opzioni: 'Privato\nAzienda' })));
  ok('crea un attributo Scelta multipla con un\'emoji nelle opzioni', /creato/.test(await creaAttr('', { tipo: 'multiple-choice', nome: 'INTERESSI', opzioni: 'Yoga\nCorsa \u{1F3C3}' })));
  let f = await creaAttr('', { tipo: 'text', nome: 'CITTA' });
  ok('nome doppio rifiutato, con un messaggio chiaro', /Esiste già un attributo che si chiama CITTA/.test(f), f);
  f = await creaAttr('', { tipo: 'text', nome: 'FIRSTNAME' });
  ok('FIRSTNAME rifiutato (è NOME, come in Brevo)', /Esiste già/.test(f), f);
  f = await creaAttr('', { tipo: 'category', nome: 'LUNGA', opzioni: 'x'.repeat(201) });
  ok('opzione oltre 200 caratteri rifiutata', /200 caratteri/.test(f), f);
  ok('attributi salvati con i tipi di Brevo', JSON.stringify(q("SELECT nome, tipo, categoria FROM {p}newsletter_attributi WHERE sistema = 0 ORDER BY id").map(r => r.nome + ':' + r.tipo + ':' + r.categoria)) === JSON.stringify(['CITTA:text:normal', 'PUNTI:float:normal', 'DATA_NASCITA:date:normal', 'CLIENTE:boolean:normal', 'TIPO:category:category', 'INTERESSI:multiple-choice:normal']));
  ok("l'emoji dell'opzione salvata come entità (tabella a 3 byte)", +uno("SELECT COUNT(*) FROM {p}newsletter_attributi_opzioni WHERE etichetta LIKE '%&#x1F3C3;%'") === 1);
  f = await creaAttr('calcolati', { nome: 'APERTURE_15', formula: 'COUNT[READS,READS,>,NOW(-15)]' });
  ok('attributo calcolato con la formula di Brevo', /creato/.test(f), f);
  f = await creaAttr('calcolati', { nome: 'MALE', formula: 'DROP TABLE x' });
  ok('formula sbagliata rifiutata', /formula non si legge/.test(f), f);
  f = await creaAttr('globali', { nome: 'ISCRITTI_TOTALI', formula: 'COUNT[CONTACTS]' });
  ok('valore globale COUNT[CONTACTS]', /creato/.test(f), f);
  f = await creaAttr('globali', { nome: 'PUNTI_TOTALI', formula: 'SUM[PUNTI]' });
  ok('valore globale SUM[PUNTI]', /creato/.test(f), f);
  f = await creaAttr('globali', { nome: 'SOMMA_TESTO', formula: 'SUM[CITTA]' });
  ok('SUM su un attributo di testo rifiutato (Brevo)', /tipo Numero/.test(f), f);
  await vai('newsletter-attributi.php?scheda=globali');
  t = await testo('table');
  ok('valori globali calcolati nella tabella', /ISCRITTI_TOTALI/.test(t) && /COUNT\[CONTACTS\]/.test(t), t.slice(0, 200));
  await foto('attributi');

  // ===== Crea un contatto
  await vai('newsletter-contatti.php'); await invia('a:has-text("Crea un contatto")');
  ok('Crea un contatto: un campo per attributo', await p.locator('#nlk-email').count() === 1 && await p.locator('#nlk-citta').count() === 1 && await p.locator('#nlk-tipo').count() === 1 && await p.locator('#nlk-data-nascita').count() === 1);
  await p.fill('#nlk-email', 'Nuova@Nuovi-Contatti.it'); await p.fill('#nlk-nome', 'Nora'); await p.fill('#nlk-cognome', 'Neri');
  await p.fill('#nlk-citta', '=CMD("calc")'); await p.fill('#nlk-punti', 'abc'); await p.fill('#nlk-data-nascita', '1990-05-17');
  await p.selectOption('#nlk-cliente', '1'); await p.selectOption('#nlk-tipo', { label: 'Azienda' });
  await p.check('input[name="a[INTERESSI][]"] >> nth=1');
  await p.check(`input[name="liste[]"][value="${eventi}"]`);
  await p.fill('#nlk-lista-nuova', 'PC Lista dal contatto');
  await invia('button:has-text("Crea")');
  const nuova = idDi('nuova@nuovi-contatti.it');
  const v = cli('valori', String(nuova));
  ok('contatto creato e aperto nella sua scheda', nuova > 0 && p.url().includes('newsletter-contatto.php?id=' + nuova), p.url());
  ok('valori salvati per tipo; il numero sbagliato ("abc") ignorato senza errore', v.CITTA === '=CMD("calc")' && v.DATA_NASCITA === '1990-05-17' && v.CLIENTE === 1 && v.TIPO === 2 && JSON.stringify(v.INTERESSI) === '[2]' && v.PUNTI === undefined, v);
  ok('liste scelte e lista nuova creata dal modulo', +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste il JOIN {p}newsletter_liste l ON l.id = il.lista_id WHERE il.iscritto_id = ${nuova} AND l.nome IN ('PC Eventi', 'PC Lista dal contatto')`) === 2);
  await vai('newsletter-contatto.php?nuovo=1'); await p.fill('#nlk-email', 'nuova@nuovi-contatti.it'); await invia('button:has-text("Crea")');
  ok('doppione: porta al contatto che c\'è già, senza crearne un altro', p.url().includes('id=' + nuova) && /c'è già/.test(await flash()) && +uno("SELECT COUNT(*) FROM {p}newsletter_iscritti WHERE email = 'nuova@nuovi-contatti.it'") === 1);
  await vai('newsletter-contatto.php?nuovo=1'); await p.fill('#nlk-email', 'non-una-email'); await p.evaluate(() => document.querySelector('#nlk-email').type = 'text'); await invia('button:has-text("Crea")');
  ok('email non valida: errore in parole semplici', /non è valida/.test(await flash()), await flash());

  // ===== Scheda: Panoramica modificabile, statistiche, cronologia
  await vai('newsletter-contatto.php?id=' + nuova);
  ok('quattro schede: Panoramica, Cronologia, Liste, Preferenze', (await p.locator('nav.imp-schede a').allInnerTexts()).join(',') === 'Panoramica,Cronologia,Liste,Preferenze');
  await p.fill('#nlk-punti', '12,5'); await p.fill('#nlk-citta', 'Roma'); await p.fill('#nlk-data-nascita', '');
  await invia('button:has-text("Salva le informazioni")');
  const v2 = cli('valori', String(nuova));
  ok('Panoramica: valori cambiati (numero con la virgola, data tolta)', v2.PUNTI === 12.5 && v2.CITTA === 'Roma' && v2.DATA_NASCITA === undefined, v2);
  await foto('scheda');
  const a1 = idDi('attivo1@prova-contatti.it');
  await vai('newsletter-contatto.php?id=' + a1);
  t = await testo();
  ok('statistiche dei 90 giorni dai dati che c\'erano (coda ed eventi)', /Inviate\s*1/.test(t) && /Aperte\s*1/.test(t) && /Cliccate\s*1/.test(t) && /esempio\.it\/offerta/.test(t), t.slice(t.indexOf('Statistiche'), t.indexOf('Statistiche') + 300));
  await vai('newsletter-contatto.php?id=' + a1 + '&scheda=cronologia');
  t = await testo('table');
  ok('Cronologia: campagna ricevuta, apertura, clic, liste', /Ha ricevuto la campagna «PC Campagna inviata»/.test(t) && /Ha aperto/.test(t) && /Ha cliccato/.test(t) && /Aggiunto alla lista «PC Clienti»/.test(t), t.slice(0, 300));
  await p.selectOption('#nlk-tipo', 'clic'); await invia('button:has-text("Filtra")');
  t = await testo('table');
  ok('Cronologia filtrata per tipo', /Ha cliccato/.test(t) && !/Ha ricevuto/.test(t), t.slice(0, 200));

  // ===== Scheda Liste
  await vai('newsletter-contatto.php?id=' + nuova + '&scheda=liste');
  await p.check(`input[name="liste[]"][value="${vuota}"]`); await p.uncheck(`input[name="liste[]"][value="${eventi}"]`);
  await invia('button:has-text("Salva le liste")');
  ok('scheda Liste: aggiunto a una, tolto da un\'altra', +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE iscritto_id = ${nuova} AND lista_id = ${vuota}`) === 1 && +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE iscritto_id = ${nuova} AND lista_id = ${eventi}`) === 0);

  // ===== Preferenze: lista nera uno alla volta
  await vai('newsletter-contatto.php?id=' + nuova + '&scheda=preferenze');
  await invia('button:has-text("Metti in lista nera")');
  let r = q(`SELECT stato, blocco_motivo FROM {p}newsletter_iscritti WHERE id = ${nuova}`)[0];
  ok('Preferenze: in lista nera a mano (adminBlocked)', r.stato === 'bloccato' && r.blocco_motivo === 'adminBlocked', r);
  t = await testo();
  ok("Preferenze: l'avviso legale prima di togliere dalla lista nera", /vietato dalla legge/.test(t));
  await p.evaluate(() => { const c = document.querySelector('input[name=consenso]'); c.required = false; });
  await invia('button:has-text("Togli dalla lista nera")');
  r = q(`SELECT stato FROM {p}newsletter_iscritti WHERE id = ${nuova}`)[0];
  ok('senza la spunta del consenso non si toglie', r.stato === 'bloccato' && /spunta la casella/.test(await flash()), r);
  await p.check('input[name=consenso]'); await invia('button:has-text("Togli dalla lista nera")');
  r = q(`SELECT stato, blocco_motivo FROM {p}newsletter_iscritti WHERE id = ${nuova}`)[0];
  ok('con la spunta torna iscritto, motivo tolto', r.stato === 'attivo' && r.blocco_motivo === '', r);
  // Cambio di email di un contatto in lista nera: torna iscritto (Brevo).
  const u1 = idDi('uscito1@prova-contatti.it');
  await vai('newsletter-contatto.php?id=' + u1); await p.fill('#nlk-email', 'uscito1-nuova@prova-contatti.it'); await invia('button:has-text("Salva le informazioni")');
  r = q(`SELECT email, stato FROM {p}newsletter_iscritti WHERE id = ${u1}`)[0];
  ok("cambio dell'email di un contatto in lista nera: la lista nera si toglie (Brevo)", r.email === 'uscito1-nuova@prova-contatti.it' && r.stato === 'attivo', r);

  // ===== Personalizza le colonne
  await vai('newsletter-contatti.php');
  await p.click('button[data-apri-dialogo="#nlc-colonne"]');
  await p.check('#nlc-colonne input[value=PUNTI]'); await p.check('#nlc-colonne input[value=CITTA]');
  const li = p.locator('#nlc-colonne li:has(input[value=CITTA])');
  await li.locator('[data-nlc-su]').click(); await li.locator('[data-nlc-su]').click();
  await invia('#nlc-colonne button[type=submit]');
  const th2 = (await p.locator('table.nlc-tabella thead th').allInnerTexts()).map(x => x.trim());
  ok('Personalizza le colonne: due attributi in più, nell\'ordine scelto con le frecce', th2.slice(-2).join(',') === 'Città,Punti' || th2.slice(-2).join(',').toLowerCase() === 'città,punti', th2);
  t = await testo('table.nlc-tabella');
  ok('le colonne in più mostrano i valori', /Roma/.test(t) && /12,5/.test(t), t.slice(0, 200));

  // ===== Azioni di gruppo
  const a2 = idDi('attivo2@prova-contatti.it'), a3 = idDi('attivo3@prova-contatti.it');
  const gruppo = async (ids, cosa, extra = {}, tutti = false, url = 'newsletter-contatti.php') => {
    await vai(url);
    for (const i of ids) await p.check(`input.nlc-sel[value="${i}"]`);
    if (tutti) await p.check('#nlc-tutti');
    await p.selectOption('#nlc-cosa', cosa);
    for (const [k, val] of Object.entries(extra)) await p.selectOption(k, val).catch(async () => p.fill(k, val));
    await invia('#nlc-applica');
    return flash();
  };
  await vai('newsletter-contatti.php'); await p.click('#nlc-applica');
  ok("Applica senza azione: lo dice sotto il pulsante, senza inviare", /Scegli un'azione/.test(await p.locator('#nlc-errore').innerText().catch(() => '')));
  f = await gruppo([a2, a3], 'aggiungi', { '#nlc-lista-dest': String(vuota) });
  ok('Aggiungi a una lista', /2 contatti aggiunti/.test(f) && +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE lista_id = ${vuota} AND iscritto_id IN (${a2},${a3})`) === 2, f);
  f = await gruppo([a2], 'sposta', { '#nlc-lista-dest': String(eventi) }, false, 'newsletter-contatti.php?lista=' + vuota);
  ok('Sposta: entra nella lista di arrivo ed esce da quella caricata', /spostato/.test(f) && +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE iscritto_id = ${a2} AND lista_id = ${eventi}`) === 1 && +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE iscritto_id = ${a2} AND lista_id = ${vuota}`) === 0, f);
  f = await gruppo([a3], 'togli', { '#nlc-lista-da': String(vuota) });
  ok('Togli dalle liste (una lista)', /1 contatto tolto dalla lista/.test(f) && +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE iscritto_id = ${a3} AND lista_id = ${vuota}`) === 0, f);
  await vai('newsletter-contatti.php');
  const opzioniModifica = await p.locator('#nlc-attr option').allInnerTexts();
  ok('Modifica in massa: solo attributi Data, Sì/No e Categoria (Brevo)', opzioniModifica.length === 3 && !opzioniModifica.some(o => /Città|Punti|Interessi/.test(o)), opzioniModifica);
  f = await gruppo([a2, a3], 'modifica', { '#nlc-attr': 'CLIENTE', '#nlc-valore': 'sì' });
  ok('Modifica in massa del Sì/No', /a 2 contatti/.test(f) && cli('valori', String(a2)).CLIENTE === 1 && cli('valori', String(a3)).CLIENTE === 1, f);
  f = await gruppo([a2], 'modifica', { '#nlc-attr': 'DATA_NASCITA', '#nlc-valore': 'non una data' });
  ok('Modifica in massa con un valore del tipo sbagliato: rifiutata', /non va bene/.test(f), f);
  // Esporta: CSV con le formule neutralizzate.
  await vai('newsletter-contatti.php'); await p.check(`input.nlc-sel[value="${nuova}"]`); await p.selectOption('#nlc-cosa', 'esporta');
  const [dl] = await Promise.all([p.waitForEvent('download'), p.click('#nlc-applica')]);
  const csv = fs.readFileSync(await dl.path(), 'utf8');
  ok('Esporta: CSV con EMAIL, gli attributi, lo stato e le liste', /^﻿?EMAIL;NOME;COGNOME/.test(csv) && /nuova@nuovi-contatti\.it/.test(csv) && /CITTA/.test(csv) && /LISTE/.test(csv), csv.slice(0, 200));
  await vai('newsletter-contatto.php?id=' + nuova); await p.fill('#nlk-citta', '=CMD("calc")'); await invia('button:has-text("Salva le informazioni")');
  await vai('newsletter-contatti.php'); await p.check(`input.nlc-sel[value="${nuova}"]`); await p.selectOption('#nlc-cosa', 'esporta');
  const [dl2] = await Promise.all([p.waitForEvent('download'), p.click('#nlc-applica')]);
  const csv2 = fs.readFileSync(await dl2.path(), 'utf8');
  ok("Esporta: una cella che comincia con = esce con l'apostrofo davanti (niente formule nel foglio di calcolo)", /'=CMD\(""calc""\)/.test(csv2) && !/;=CMD/.test(csv2), csv2.split('\n')[1]);
  f = await gruppo([a3], 'blocca');
  r = q(`SELECT stato, blocco_motivo FROM {p}newsletter_iscritti WHERE id = ${a3}`)[0];
  ok('Metti in lista nera dalla pagina', /1 contatto messo in lista nera/.test(f) && r.stato === 'bloccato' && r.blocco_motivo === 'adminBlocked', [f, r]);
  ok('chi è in lista nera non si toglie in massa (nessuna azione per farlo)', !(await p.locator('#nlc-cosa option').allInnerTexts()).some(o => /Togli dalla lista nera/i.test(o)));
  // Tutti i contatti del filtro
  await vai('newsletter-contatti.php?cerca=' + encodeURIComponent('@prova-contatti.it') + '&stato=iscritti');
  const quanti = await p.locator('table.nlc-tabella tbody tr').count();
  await p.check('#nlc-tutti'); await p.selectOption('#nlc-cosa', 'aggiungi'); await p.selectOption('#nlc-lista-dest', String(vuota));
  await invia('#nlc-applica');
  ok('"Tutti i contatti del filtro" vale per tutto il filtro, senza spuntare le righe', new RegExp(quanti + ' contatti aggiunti').test(await flash()), [quanti, await flash()]);

  // ===== Lista nera riempita a mano
  await vai('newsletter-contatti.php#nlc-nera');
  await p.fill('#nlc-indirizzi', 'nero1@nuovi-contatti.it\nnon-valido\nattivo2@prova-contatti.it');
  await invia('#nlc-nera button[type=submit]');
  r = q("SELECT email, stato, blocco_motivo FROM {p}newsletter_iscritti WHERE email IN ('nero1@nuovi-contatti.it', 'attivo2@prova-contatti.it') ORDER BY email");
  ok('Metti in lista nera degli indirizzi: nuovi e già contatti, motivo a mano; le righe non valide saltate', r.length === 2 && r.every(x => x.stato === 'bloccato' && x.blocco_motivo === 'adminBlocked') && /1 non erano email valide/.test(await flash()), [r, await flash()]);

  // ===== Soft bounce: al quinto di fila, lista nera (Brevo)
  const e1 = idDi('errori1@prova-contatti.it');
  q(`UPDATE {p}newsletter_iscritti SET errori_di_fila = 3, stato = 'attivo' WHERE id = ${e1}`);
  cli('soft', 'errori1@prova-contatti.it');
  r = q(`SELECT stato, errori_di_fila FROM {p}newsletter_iscritti WHERE id = ${e1}`)[0];
  ok('quarto soft bounce: resta iscritto', r.stato === 'attivo' && +r.errori_di_fila === 4, r);
  cli('soft', 'errori1@prova-contatti.it');
  r = q(`SELECT stato, blocco_motivo FROM {p}newsletter_iscritti WHERE id = ${e1}`)[0];
  ok('quinto soft bounce di fila: lista nera con motivo softBounce', r.stato === 'bloccato' && r.blocco_motivo === 'softBounce', r);

  // ===== Cartelle e liste
  await vai('newsletter-contatti.php?scheda=liste');
  await p.fill('#nlc-cart-nome', 'PC Cartella'); await invia('button:has-text("Crea la cartella")');
  const cart = +uno("SELECT id FROM {p}newsletter_cartelle WHERE nome = 'PC Cartella'");
  await p.fill('#nll-nome', 'PC Lista in cartella'); await p.selectOption('#nll-cart', String(cart)); await invia('button:has-text("Crea la lista")');
  const lc = +uno("SELECT id FROM {p}newsletter_liste WHERE nome = 'PC Lista in cartella'");
  ok('crea una cartella e una lista dentro', cart > 0 && +uno(`SELECT cartella_id FROM {p}newsletter_liste WHERE id = ${lc}`) === cart);
  q(`INSERT INTO {p}newsletter_iscritti_liste (iscritto_id, lista_id, aggiunto) VALUES (${a1}, ${lc}, NOW())`);
  await foto('liste');
  await vai('newsletter-contatti.php?scheda=liste');
  await invia(`form:has(input[name=azione][value=cartella_elimina]):has(input[name=id][value="${cart}"]) button`);
  ok('elimina la cartella: via le sue liste, i contatti restano', +uno(`SELECT COUNT(*) FROM {p}newsletter_cartelle WHERE id = ${cart}`) === 0 && +uno(`SELECT COUNT(*) FROM {p}newsletter_liste WHERE id = ${lc}`) === 0 && +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti WHERE id = ${a1}`) === 1);
  const princ = +uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_lista_principale'");
  const cartPrinc = +uno(`SELECT cartella_id FROM {p}newsletter_liste WHERE id = ${princ}`);
  await vai('newsletter-contatti.php?scheda=liste');
  await invia(`form:has(input[name=azione][value=cartella_elimina]):has(input[name=id][value="${cartPrinc}"]) button`);
  ok('la cartella con la lista del sito non si elimina, e lo dice', +uno(`SELECT COUNT(*) FROM {p}newsletter_liste WHERE id = ${princ}`) === 1 && /spostala/.test(await flash()), await flash());

  // ===== Segnaposti degli attributi in una campagna di prova
  q(`INSERT INTO {p}newsletter_campagne (oggetto, anteprima, corpo, stato, liste, creato, aggiornato) VALUES ('PC Segnaposti {{ contact.FIRSTNAME | upper }}', '', '<p>Ciao {{ contact.NOME }} da {{ contact.CITTA | default:"tutta Italia" }}, sei di tipo {{ contact.TIPO }}.</p><p>Iscritti: {{ contact.ISCRITTI_TOTALI }}. Vuoto: [{{ contact.DATA_NASCITA | default : &quot;mai&quot; }}] {nome}</p>', 'bozza', '${vuota}', NOW(), NOW())`);
  const camp = +uno("SELECT MAX(id) FROM {p}newsletter_campagne WHERE oggetto LIKE 'PC Segnaposti%'");
  const [ogg, html] = cli('email', String(camp), String(nuova));
  ok('segnaposti nell\'oggetto con il filtro upper (FIRSTNAME = NOME)', ogg === 'PC Segnaposti NORA', ogg);
  ok('segnaposti nel testo: attributo, categoria in parole, valore globale, valore predefinito', /Ciao Nora da =CMD\(&quot;calc&quot;\)/.test(html) && /sei di tipo Azienda/.test(html) && /Iscritti: \d+\./.test(html) && /Vuoto: \[mai\] Nora/.test(html), html.slice(html.indexOf('Ciao'), html.indexOf('Ciao') + 200));
  const [ogg2, html2] = cli('email', String(camp), String(a1));
  ok('valore predefinito quando l\'attributo è vuoto', /da tutta Italia/.test(html2) && ogg2 === 'PC Segnaposti ANNA', [ogg2, html2.slice(html2.indexOf('Ciao'), html2.indexOf('Ciao') + 80)]);
  q(`DELETE FROM {p}newsletter_campagne WHERE id = ${camp}`);

  // ===== Elimina definitivamente
  await vai('newsletter-contatto.php?id=' + nuova);
  await invia('button:has-text("Elimina definitivamente")');
  ok('Elimina definitivamente: via contatto, valori e storia; l\'impronta resta (scelta di plumeCMS)', +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti WHERE id = ${nuova}`) === 0 && +uno(`SELECT COUNT(*) FROM {p}newsletter_valori WHERE contatto_id = ${nuova}`) === 0
    && +uno(`SELECT COUNT(*) FROM {p}newsletter_storia WHERE contatto_id = ${nuova}`) === 0 && +uno(`SELECT COUNT(*) FROM {p}newsletter_soppressi WHERE impronta = SHA2('nuova@nuovi-contatti.it', 256)`) === 1);

  // ===== Attributo eliminato: valori via per tutti
  const idTipo = +uno("SELECT id FROM {p}newsletter_attributi WHERE nome = 'CLIENTE'");
  await vai('newsletter-attributi.php');
  await p.locator(`details:has(input[name=id][value="${idTipo}"]) summary`).click();
  await invia(`form:has(input[name=azione][value=elimina]):has(input[name=id][value="${idTipo}"]) button`);
  ok('elimina un attributo: i suoi valori spariscono per tutti', +uno(`SELECT COUNT(*) FROM {p}newsletter_attributi WHERE id = ${idTipo}`) === 0 && +uno(`SELECT COUNT(*) FROM {p}newsletter_valori WHERE attributo_id = ${idTipo}`) === 0);
  ok('gli attributi di sistema non hanno Elimina', await p.locator('tr:has(code:text-is("EMAIL")) button:has-text("Elimina")').count() === 0);

  // ===== Telefono: 390 px senza scorrimento di lato
  for (const w of [390, 768]) {
    await p.setViewportSize({ width: w, height: 800 });
    for (const u of ['newsletter-contatti.php', 'newsletter-contatti.php?scheda=liste', 'newsletter-contatto.php?id=' + a1, 'newsletter-contatto.php?id=' + a1 + '&scheda=cronologia', 'newsletter-contatto.php?id=' + a1 + '&scheda=preferenze', 'newsletter-contatto.php?nuovo=1', 'newsletter-attributi.php', 'newsletter-attributi.php?scheda=globali']) {
      await vai(u);
      const lato = await p.evaluate(() => document.documentElement.scrollWidth - innerWidth);
      ok(`${w} px: ${u} non scorre di lato`, lato <= 1, lato);
    }
    if (w === 390) await foto('contatti-390');
  }
  await p.setViewportSize({ width: 1280, height: 900 });

  // ===== Redattore: non vede e non salva
  execFileSync('php', [path.join(__dirname, 'tema-cli.php'), DIR, 'redattore', 'Redattore-Prova-2026!']);
  const ctxR = await br.newContext(); const pr = await ctxR.newPage();
  await pr.goto(B + '/admin/login.php'); await pr.fill('#username', 'redattore-prova'); await pr.fill('#password', 'Redattore-Prova-2026!');
  await Promise.all([pr.waitForNavigation(), pr.click('button[type=submit]')]);
  const menuR = await pr.locator('.admin-sidebar').textContent();
  ok('redattore: nel menu niente Contatti né Attributi', !/Contatti|Attributi dei contatti/.test(menuR));
  for (const u of ['newsletter-contatti.php', 'newsletter-contatto.php?id=' + a1, 'newsletter-attributi.php', 'newsletter-contatti.php?scarica=tutti']) {
    const rr = await pr.goto(B + '/admin/' + u, { waitUntil: 'networkidle' });
    ok('redattore: ' + u + ' lo rimanda alla dashboard', /index\.php/.test(pr.url()) && !/attivo1@/.test(await pr.content()), pr.url());
  }
  const tkR = await pr.evaluate(() => (document.querySelector('input[name=_csrf]') || {}).value || '');
  const prima = +uno('SELECT COUNT(*) FROM {p}newsletter_attributi');
  await pr.request.post(B + '/admin/newsletter-attributi.php', { form: { _csrf: tkR, azione: 'crea', tipo: 'text', nome: 'DAL_REDATTORE' }, maxRedirects: 0 });
  await pr.request.post(B + '/admin/newsletter-contatto.php', { form: { _csrf: tkR, azione: 'blocca', id: String(a1) }, maxRedirects: 0 });
  ok('redattore: i POST non salvano niente', +uno('SELECT COUNT(*) FROM {p}newsletter_attributi') === prima && uno(`SELECT stato FROM {p}newsletter_iscritti WHERE id = ${a1}`) === 'attivo');
  await ctxR.close();

  ok('nessun errore JavaScript', err.length === 0, err);
  console.log(`\n${n - ko} su ${n} OK`);
  await br.close();
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
