// 2.10.0, fase 3 del piano Brevo (docs-sviluppo/brevo-confronto.md): i segmenti provati come un utente.
// Sito con 5000 contatti finti ed eventi (prove/segmenti-cli.php semina): ogni condizione del motore contro una query
// scritta a mano, gruppi E/O e negazioni, tempi (sotto un secondo), bivi dei flussi con lo stesso esito della 2.9.0;
// nel browser: Crea un segmento da un modello e da zero (conteggio che si aggiorna), categorie, modifica, segmento nella
// pagina Contatti, Aggiungi filtro e Salva come segmento, viste salvate (filtri e colonne), lista nuova da un segmento,
// esportazione di un segmento, bivio "fa parte di un segmento" nei flussi, errori in parole, segmento usato che non si
// elimina, redattore che non vede e non salva, 390 e 768 px senza scorrimento di lato.
// uso: node prove/prova-segmenti.js <base> <utente> <password> <cartella CMS installato> [cartella schermate]
// SEMINA=0 non rifà i 5000 contatti se ci sono già. Lascia i dati di prova (SG ...): va su un sito da buttare.
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const [B, UTENTE, PASSWORD, DIR, FOTO] = process.argv.slice(2);
const CLI = path.join(__dirname, 'segmenti-cli.php');
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [CLI, DIR, ...a], { encoding: 'utf8', maxBuffer: 64 << 20 }).trim().split('\n').pop());
const q = (sql) => cli('sql', sql);
const uno = (sql) => { const r = q(sql); return r && r[0] ? Object.values(r[0])[0] : null; };
const conta = (regole) => cli('conta', JSON.stringify(regole)).n;
const g1 = (...c) => ({ op: 'e', gruppi: [{ op: 'e', condizioni: c }] });
const numeroIt = (x) => String(x).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

(async () => {
  // ===== Dati: 5000 contatti con eventi
  const gia = +uno("SELECT COUNT(*) FROM {p}newsletter_iscritti WHERE email LIKE '%@seg-prova.it'");
  if (process.env.SEMINA !== '0' || gia < 5000) { cli('pulisci'); const s = cli('semina', '5000'); ok('semina: 5000 contatti, eventi, form, liste', s.contatti === 5000 && s.eventi > 3000, s); }
  const L = {}; for (const r of q("SELECT id, nome FROM {p}newsletter_liste WHERE nome LIKE 'SG %'")) L[r.nome] = +r.id;
  q("DELETE FROM {p}newsletter_segmenti"); q("DELETE FROM {p}newsletter_segmenti_cartelle"); q("DELETE FROM {p}newsletter_viste");
  q("DELETE FROM {p}newsletter_esportazioni"); q("DELETE FROM {p}newsletter_liste WHERE nome LIKE 'SG Da segmento%'");

  // ===== Motore: ogni condizione contro la query scritta a mano, e i tempi
  const conf = cli('confronta');
  for (const c of conf) ok(`motore = query a mano: ${c.nome} (${c.motore} contatti, ${c.conta_ms} ms)`, !c.errore && c.uguali && c.motore === c.mano, c);
  const lenti = conf.filter(c => (c.conta_ms || 0) >= 1000 || (c.ms || 0) >= 1000);
  ok(`tempi: ogni conteggio sotto un secondo con 5000 contatti (il più lento ${Math.max(...conf.map(c => c.conta_ms || 0))} ms)`, lenti.length === 0, lenti);
  // ===== Bivi dei flussi: lo stesso esito della 2.9.0
  for (const f of cli('flussi')) ok(`bivio ${f.condizione}: stesso esito del codice di prima su ${f.prove} contatti (sì per ${f.si})`, f.diverse === 0, f);

  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 }, acceptDownloads: true });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  pnConferme.on(p, d => d.accept());
  const vai = async (u) => { await p.goto(B + '/admin/' + u, { waitUntil: 'networkidle' }); };
  const testo = async (sel = '.admin-content') => (await p.locator(sel).first().innerText()).replace(/\s+/g, ' ');
  const flash = async () => (await p.locator('.flash, [role=alert]').allInnerTexts()).join(' | ');
  const invia = async (sel) => { await Promise.all([p.waitForNavigation(), p.click(sel)]); };
  const foto = async (nome) => { if (FOTO) await p.screenshot({ path: path.join(FOTO, nome + '.png'), fullPage: true }); };
  // Il numero che il costruttore mostra (aspetta che il conteggio arrivi).
  let atteso;
  // Aspetta il numero atteso (il conteggio parte mezzo secondo dopo l'ultimo cambio), al massimo 10 secondi.
  let attesoN = null, attesoRe = null;
  const numero = async () => {
    const loc = p.locator('[data-nls-numero]').first();
    let t = '';
    for (let i = 0; i < 70; i++) {
      t = (await loc.innerText()).trim();
      if (t && !/^Conto/.test(t) && (attesoN === null || t.startsWith(numeroIt(attesoN) + ' contatt')) && (attesoRe === null || attesoRe.test(t))) break;
      await p.waitForTimeout(150);
    }
    attesoN = null; attesoRe = null;
    return t;
  };
  const totale = async () => +((await p.locator('label.nlx-tutti b').innerText()).trim());
  const ultima = (sel) => p.locator(sel).last();

  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  // ===== Pagina Segmenti vuota
  await vai('newsletter-segmenti.php');
  let t = await testo();
  ok('Segmenti: scheda accanto a Contatti e Liste, attiva', (await p.locator('nav.imp-schede a.attiva').innerText()).trim() === 'Segmenti' && (await p.locator('nav.imp-schede a').count()) === 3);
  ok('Segmenti: elenco vuoto che dice cosa fare', /Ancora nessun segmento/.test(t) && /Crea un segmento/.test(t), t.slice(0, 200));
  const r302 = await ctx.request.get(B + '/admin/newsletter-contatti.php?scheda=segmenti', { maxRedirects: 0 });
  ok('newsletter-contatti.php?scheda=segmenti porta alla pagina Segmenti (302, no-store)', r302.status() === 302 && /newsletter-segmenti\.php/.test(r302.headers()['location'] || '') && /no-store/.test(r302.headers()['cache-control'] || ''));

  // ===== Crea da un modello
  await vai('newsletter-segmenti.php'); await invia('a:has-text("Crea un segmento")');
  const modelli = await p.locator('.nls-modello b').allInnerTexts();
  ok('Crea un segmento: da zero o da un modello (10 modelli)', modelli[0] === 'Crea da zero' && modelli.length === 11 && modelli.includes('Chi non apre da 90 giorni') && modelli.includes('Nuovi iscritti di questo mese') && modelli.includes("Chi ha cliccato l'ultima campagna"), modelli);
  await foto('segmenti-modelli');
  await invia('a.nls-modello:has-text("Chi non apre da 90 giorni")');
  const atteso90 = conta(g1({ campo: 'stato', op: 'iscritto' }, { campo: 'email', op: 'non_aperta', campagna: 0, n: 90, unita: 'giorni' }));
  attesoN = atteso90; let num = await numero();
  ok('modello: le condizioni compilate e il conteggio giusto', num.includes(numeroIt(atteso90) + ' contatti') && (await p.locator('.nls-cond').count()) === 2, [num, atteso90]);
  ok('modello: il nome proposto', await p.inputValue('#nls-nome') === 'Chi non apre da 90 giorni');
  await p.fill('#nls-nome', 'SG Non aprono'); await p.fill('#nls-cart-nuova', 'SG Categoria');
  await foto('segmenti-modello');
  await invia('button:has-text("Crea il segmento")');
  const s90 = +uno("SELECT id FROM {p}newsletter_segmenti WHERE nome = 'SG Non aprono'");
  ok('segmento creato con la categoria nuova e il numero dei contatti', s90 > 0 && /creato/.test(await flash()) && +uno(`SELECT conteggio FROM {p}newsletter_segmenti WHERE id = ${s90}`) === atteso90 && uno(`SELECT c.nome FROM {p}newsletter_segmenti s JOIN {p}newsletter_segmenti_cartelle c ON c.id = s.cartella_id WHERE s.id = ${s90}`) === 'SG Categoria', await flash());
  t = await testo();
  ok("l'elenco mostra il segmento nella sua categoria, con il numero e le condizioni in parole", /SG CATEGORIA|SG Categoria/i.test(t) && t.includes(numeroIt(atteso90) + ' contatti') && /Iscrizione all'email è iscritto/.test(t), t.slice(0, 400));

  // ===== Crea da zero: costruttore, E, O, gruppi
  await vai('newsletter-segmenti.php?nuovo=1&modello=zero');
  ok('da zero: un gruppo vuoto che dice cosa fare', /Nessuna condizione/.test(await testo('.nls-editor')));
  await p.click('button[data-aggiungi="0"]');
  await ultima('[data-k=campo]').selectOption('attr:CITTA');
  await ultima('[data-k=op]').selectOption('uguale');
  await ultima('[data-k=valore]').fill('Roma');
  atteso = conta(g1({ campo: 'attr:CITTA', op: 'uguale', valore: 'Roma' }));
  attesoN = atteso; num = await numero();
  ok('da zero: Città è Roma, il conteggio si aggiorna mentre si scrive', num.startsWith(numeroIt(atteso) + ' contatti'), [num, atteso]);
  await p.click('button[data-aggiungi="0"]');
  await ultima('[data-k=campo]').selectOption('lista');
  await ultima(`.nls-caselle input[value="${L['SG Clienti']}"]`).check();
  atteso = conta(g1({ campo: 'attr:CITTA', op: 'uguale', valore: 'Roma' }, { campo: 'lista', op: 'in', valore: [L['SG Clienti']] }));
  attesoN = atteso; num = await numero();
  ok('+ E: Città è Roma E nella lista Clienti', num.startsWith(numeroIt(atteso) + ' contatti'), [num, atteso]);
  await p.selectOption('[data-gop="0"]', 'o');
  atteso = conta({ op: 'e', gruppi: [{ op: 'o', condizioni: [{ campo: 'attr:CITTA', op: 'uguale', valore: 'Roma' }, { campo: 'lista', op: 'in', valore: [L['SG Clienti']] }] }] });
  attesoN = atteso; num = await numero();
  ok('dentro il gruppo O: Roma O nella lista Clienti', num.startsWith(numeroIt(atteso) + ' contatti'), [num, atteso]);
  await p.click('button[data-nuovo-gruppo]');
  await ultima('[data-k=campo]').selectOption('lista_nera');
  await ultima('[data-k=op]').selectOption('no');
  atteso = conta({ op: 'e', gruppi: [{ op: 'o', condizioni: [{ campo: 'attr:CITTA', op: 'uguale', valore: 'Roma' }, { campo: 'lista', op: 'in', valore: [L['SG Clienti']] }] }, { op: 'e', condizioni: [{ campo: 'lista_nera', op: 'no' }] }] });
  attesoN = atteso; num = await numero();
  ok('due gruppi in E: (Roma O Clienti) E non in lista nera', num.startsWith(numeroIt(atteso) + ' contatti'), [num, atteso]);
  await p.selectOption('[data-g="op"]', 'o');
  atteso = conta({ op: 'o', gruppi: [{ op: 'o', condizioni: [{ campo: 'attr:CITTA', op: 'uguale', valore: 'Roma' }, { campo: 'lista', op: 'in', valore: [L['SG Clienti']] }] }, { op: 'e', condizioni: [{ campo: 'lista_nera', op: 'no' }] }] });
  attesoN = atteso; num = await numero();
  ok('i gruppi in O', num.startsWith(numeroIt(atteso) + ' contatti'), [num, atteso]);
  await p.selectOption('[data-g="op"]', 'e');
  await num; attesoN = atteso; num = await numero();
  // Una data relativa e un periodo: il costruttore mette i valori di partenza.
  await p.click('button[data-aggiungi="1"]');
  await ultima('[data-k=campo]').selectOption('attr:ACQUISTO');
  await ultima('[data-k=op]').selectOption('ultimi');
  await ultima('[data-k=n]').fill('60');
  atteso = conta({ op: 'e', gruppi: [{ op: 'o', condizioni: [{ campo: 'attr:CITTA', op: 'uguale', valore: 'Roma' }, { campo: 'lista', op: 'in', valore: [L['SG Clienti']] }] }, { op: 'e', condizioni: [{ campo: 'lista_nera', op: 'no' }, { campo: 'attr:ACQUISTO', op: 'ultimi', n: 60, unita: 'giorni' }] }] });
  attesoN = atteso; num = await numero();
  ok('data negli ultimi 60 giorni nel secondo gruppo', num.startsWith(numeroIt(atteso) + ' contatti'), [num, atteso]);
  ok('tastiera: il fuoco resta sul campo appena cambiato', await p.evaluate(() => document.activeElement && document.activeElement.getAttribute('data-k')) !== null);
  await foto('segmenti-da-zero');
  await p.fill('#nls-nome', 'SG Romani attivi');
  await invia('button:has-text("Crea il segmento")');
  const sR = +uno("SELECT id FROM {p}newsletter_segmenti WHERE nome = 'SG Romani attivi'");
  ok('segmento da zero salvato, senza categoria, con il numero giusto', sR > 0 && +uno(`SELECT cartella_id FROM {p}newsletter_segmenti WHERE id = ${sR}`) === 0 && +uno(`SELECT conteggio FROM {p}newsletter_segmenti WHERE id = ${sR}`) === atteso, await flash());

  // ===== Modifica
  await vai('newsletter-segmenti.php?id=' + sR);
  ok('modifica: le condizioni salvate tornano nel costruttore', (await p.locator('.nls-cond').count()) === 4 && await p.locator('.nls-cond [data-k=valore]').first().inputValue() === 'Roma');
  await p.locator('.nls-cond [data-k=valore]').first().fill('Milano');
  atteso = conta({ op: 'e', gruppi: [{ op: 'o', condizioni: [{ campo: 'attr:CITTA', op: 'uguale', valore: 'Milano' }, { campo: 'lista', op: 'in', valore: [L['SG Clienti']] }] }, { op: 'e', condizioni: [{ campo: 'lista_nera', op: 'no' }, { campo: 'attr:ACQUISTO', op: 'ultimi', n: 60, unita: 'giorni' }] }] });
  attesoN = atteso; await numero();
  await p.fill('#nls-nome', 'SG Milanesi attivi');
  await invia('button:has-text("Salva il segmento")');
  ok('modifica salvata: nome, condizioni e numero', uno(`SELECT nome FROM {p}newsletter_segmenti WHERE id = ${sR}`) === 'SG Milanesi attivi' && +uno(`SELECT conteggio FROM {p}newsletter_segmenti WHERE id = ${sR}`) === atteso && /Milano/.test(uno(`SELECT condizioni FROM {p}newsletter_segmenti WHERE id = ${sR}`)), await flash());

  // ===== Errori in parole
  await vai('newsletter-segmenti.php?nuovo=1&modello=zero');
  await p.fill('#nls-nome', 'SG Vuoto'); await invia('button:has-text("Crea il segmento")');
  ok('senza condizioni: errore in parole, il segmento non nasce', /almeno una condizione/.test(await flash()) && !+uno("SELECT COUNT(*) FROM {p}newsletter_segmenti WHERE nome = 'SG Vuoto'"), await flash());
  ok("dopo l'errore il nome scritto resta", await p.inputValue('#nls-nome') === 'SG Vuoto');
  await p.click('button[data-aggiungi="0"]'); await ultima('[data-k=campo]').selectOption('attr:CITTA'); await ultima('[data-k=op]').selectOption('uguale');
  attesoRe = /scrivi/; num = await numero();
  ok('condizione senza valore: il conteggio dice cosa manca', /scrivi il valore/.test(num), num);
  await p.fill('#nls-nome', 'SG Non aprono'); await ultima('[data-k=valore]').fill('Bari'); attesoRe = /rispett/; await numero();
  await invia('button:has-text("Crea il segmento")');
  ok('nome già usato: errore in parole', /Esiste già un segmento/.test(await flash()), await flash());
  // Un segmento che contiene sé stesso (dal JSON, come farebbe un attacco): rifiutato.
  const tk = await p.evaluate(() => document.querySelector('input[name=_csrf]').value);
  const b64 = (o) => Buffer.from(JSON.stringify(o)).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  await p.request.post(B + '/admin/newsletter-segmenti.php', { form: { _csrf: tk, azione: 'salva', id: String(s90), nome: 'SG Non aprono', regole: b64(g1({ campo: 'segmento', op: 'in', valore: s90 })) }, maxRedirects: 0 });
  ok('un segmento non può contenere se stesso', !/"segmento"/.test(uno(`SELECT condizioni FROM {p}newsletter_segmenti WHERE id = ${s90}`)));

  // ===== Segmento dentro un segmento, e il segmento usato non si elimina
  await vai('newsletter-segmenti.php?nuovo=1&modello=zero');
  await p.click('button[data-aggiungi="0"]'); await ultima('[data-k=campo]').selectOption('segmento'); await ultima('[data-k=valore]').selectOption(String(s90));
  await p.click('button[data-aggiungi="0"]'); await ultima('[data-k=campo]').selectOption('attr:VIP'); await ultima('[data-k=op]').selectOption('si');
  atteso = conta(g1({ campo: 'segmento', op: 'in', valore: s90 }, { campo: 'attr:VIP', op: 'si' }));
  attesoN = atteso; num = await numero();
  ok('condizione "fa parte del segmento" (con E)', num.startsWith(numeroIt(atteso) + ' contatti'), [num, atteso]);
  await p.fill('#nls-nome', 'SG VIP che non aprono'); await invia('button:has-text("Crea il segmento")');
  const sV = +uno("SELECT id FROM {p}newsletter_segmenti WHERE nome = 'SG VIP che non aprono'");
  await vai('newsletter-segmenti.php?id=' + s90);
  ok('un segmento usato da un altro: niente Elimina, e dice chi lo usa', (await p.locator('button:has-text("Elimina il segmento")').count()) === 0 && /SG VIP che non aprono/.test(await testo()));
  await p.request.post(B + '/admin/newsletter-segmenti.php', { form: { _csrf: tk, azione: 'elimina', id: String(s90) }, maxRedirects: 0 });
  ok('...e anche il POST diretto non lo elimina', +uno(`SELECT COUNT(*) FROM {p}newsletter_segmenti WHERE id = ${s90}`) === 1);

  // ===== Ricerca e categorie nell'elenco
  await vai('newsletter-segmenti.php?cerca=Milanesi');
  t = await testo('.nls-tabella');
  ok('ricerca dei segmenti', /SG Milanesi attivi/.test(t) && !/SG Non aprono/.test(t), t.slice(0, 200));
  await vai('newsletter-segmenti.php?cerca=zzzz');
  ok('ricerca senza risultati: lo dice e suggerisce cosa fare', /Nessun segmento per «zzzz»/.test(await testo()) && /togli i filtri/.test(await testo()));

  // ===== Nella pagina Contatti: segmento caricato
  await vai('newsletter-contatti.php?segmento=' + sR);
  let tot = await totale();
  ok('Carica un segmento: la pagina Contatti mostra i suoi contatti', tot === atteso || tot === +uno(`SELECT conteggio FROM {p}newsletter_segmenti WHERE id = ${sR}`), [tot]);
  const totSR = tot;
  ok('la tendina "Carica un segmento" ha il segmento scelto', await p.inputValue('#nlx-segmento') === String(sR));
  await p.fill('#nlx-cerca', 'c00'); await invia('.nlx-filtri button[type=submit]');
  tot = await totale();
  const attesoCerca = +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti WHERE email LIKE '%c00%'`) >= tot;
  ok('segmento e ricerca insieme (restano tutti e due)', p.url().includes('segmento=' + sR) && tot <= totSR && attesoCerca, [p.url(), tot]);

  // ===== Aggiungi filtro e Salva come segmento
  await vai('newsletter-contatti.php');
  await p.click('#nlx-filtro > summary');
  await p.click('#nlx-filtro button[data-aggiungi="0"]');
  await ultima('#nlx-filtro [data-k=campo]').selectOption('attr:VIP');
  await ultima('#nlx-filtro [data-k=op]').selectOption('si');
  await p.click('#nlx-filtro button[data-aggiungi="0"]');
  await ultima('#nlx-filtro [data-k=campo]').selectOption('email');
  await ultima('#nlx-filtro [data-k=op]').selectOption('cliccata');
  await ultima('#nlx-filtro [data-k=campagna]').selectOption('-1');
  const regF = g1({ campo: 'attr:VIP', op: 'si' }, { campo: 'email', op: 'cliccata', campagna: -1 });
  atteso = conta(regF);
  attesoN = atteso; num = await numero();
  ok('Aggiungi filtro: stesse condizioni dei segmenti, con il conteggio', num.startsWith(numeroIt(atteso) + ' contatt'), [num, atteso]);
  await foto('contatti-filtro');
  await invia('#nlx-filtro button:has-text("Applica il filtro")');
  tot = await totale();
  const tF = await testo();
  ok('Applica il filtro: la tabella ha quei contatti, e il filtro si legge in parole', tot === atteso && /Filtro: \(?Cliente VIP è sì E Email aperte, cliccate, ricevute ha cliccato \(l'ultima campagna\)/.test(tF), [tot, atteso, (tF.match(/Filtro:[^.]*/) || [''])[0]]);
  const idsTabella = await p.locator('input.nlx-sel').evaluateAll(x => x.map(e => +e.value));
  const giusti = new Set(q(`SELECT v.contatto_id AS id FROM {p}newsletter_valori v JOIN {p}newsletter_attributi a ON a.id = v.attributo_id WHERE a.nome = 'VIP' AND v.v_num = 1 AND v.contatto_id IN (SELECT iscritto_id FROM {p}newsletter_eventi WHERE tipo = 'clic' AND campagna_id = (SELECT MAX(id) FROM {p}newsletter_campagne WHERE oggetto LIKE 'SG Campagna%'))`).map(r => +r.id));
  ok('le righe della tabella sono proprio quelle della query a mano', idsTabella.length > 0 && idsTabella.every(i => giusti.has(i)), idsTabella.slice(0, 5));
  // Azione di gruppo "tutti i contatti del filtro": vale sul filtro
  await p.check('#nlx-tutti'); await p.selectOption('#nlx-cosa', 'aggiungi'); await p.selectOption('#nlx-lista-dest', String(L['SG Vuota']));
  await invia('#nlx-applica');
  ok('"Tutti i contatti del filtro" con il filtro delle condizioni: le azioni di gruppo vanno su quelli', +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE lista_id = ${L['SG Vuota']}`) === atteso && /Filtro:/.test(await testo()), await flash());
  await p.click('#nlx-filtro button:has-text("Salva come segmento")');
  await p.fill('#nls-salva-nome', 'SG VIP che cliccano');
  await invia('#nls-salva button[type=submit]');
  const sF = +uno("SELECT id FROM {p}newsletter_segmenti WHERE nome = 'SG VIP che cliccano'");
  ok('Salva come segmento: nasce il segmento e la pagina lo carica', sF > 0 && p.url().includes('segmento=' + sF) && (await totale()) === atteso, [p.url(), await flash()]);

  // ===== Viste: filtri e colonne con un nome
  await vai('newsletter-contatti.php?segmento=' + sF);
  await p.click('button[data-apri-dialogo="#nlx-colonne"]');
  await p.check('#nlx-colonne input[value="CITTA"]'); await invia('#nlx-colonne button[type=submit]');
  await p.click('button[data-apri-dialogo="#nlv-salva"]');
  await p.fill('#nlv-nome', 'SG Vista VIP');
  await invia('#nlv-salva button:has-text("Salva la vista")');
  const vId = +uno("SELECT id FROM {p}newsletter_viste WHERE nome = 'SG Vista VIP'");
  ok('Salva come vista: la scheda nuova sopra la tabella, attiva', vId > 0 && (await p.locator('.nlv-viste a.attiva').innerText()).trim() === 'SG Vista VIP', await flash());
  // Colonne dell'utente cambiate: la vista tiene le sue.
  await vai('newsletter-contatti.php');
  await p.click('button[data-apri-dialogo="#nlx-colonne"]'); await p.uncheck('#nlx-colonne input[value="CITTA"]'); await invia('#nlx-colonne button[type=submit]');
  ok('"Tutti i contatti" non ha più la colonna Città', !(await p.locator('table.nlx-tabella thead').innerText()).includes('Città'));
  await invia('.nlv-viste a:has-text("SG Vista VIP")');
  const thV = await p.locator('table.nlx-tabella thead').innerText();
  ok('la vista riporta il suo segmento e la sua colonna', (await totale()) === atteso && /città/i.test(thV) && /Vista «SG Vista VIP»/.test(await testo()), [p.url(), await totale(), thV]);
  await p.click('button[data-apri-dialogo="#nlv-salva"]');
  await invia('#nlv-salva button:has-text("Elimina questa vista")');
  ok('Elimina la vista', !+uno("SELECT COUNT(*) FROM {p}newsletter_viste WHERE nome = 'SG Vista VIP'") && /Vista eliminata/.test(await flash()), await flash());

  // ===== Lista nuova da un segmento
  await vai('newsletter-segmenti.php');
  const cS = +uno(`SELECT conteggio FROM {p}newsletter_segmenti WHERE id = ${sR}`);
  await p.click(`button[data-apri-dialogo="#nls-lista-${sR}"]`);
  await p.fill(`#nls-ln${sR}`, 'SG Da segmento');
  await invia(`#nls-lista-${sR} button[type=submit]`);
  const lN = +uno("SELECT id FROM {p}newsletter_liste WHERE nome = 'SG Da segmento'");
  ok('Crea una lista da un segmento: la lista ha i contatti del segmento', lN > 0 && +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE lista_id = ${lN}`) === cS && /Lista «SG Da segmento» creata/.test(await flash()), [cS, await flash()]);
  ok('...e nella cronologia di ogni contatto', +uno(`SELECT COUNT(*) FROM {p}newsletter_storia WHERE tipo = 'lista_aggiunto' AND rif_id = ${lN}`) === cS);

  // ===== Esportazione di un segmento
  await vai('newsletter-segmenti.php');
  await invia(`a[href="newsletter-contatti.php?segmento=${sF}&esporta=1"]`);
  ok('Esporta dal segmento: si apre la finestra Esporta con il segmento scelto', await p.locator('#nlx-esporta').evaluate(d => d.open) && await p.isChecked('input[name=esp_origine][value=segmento]') && await p.inputValue('#nlx-esp-seg') === String(sF));
  const [dl] = await Promise.all([p.waitForEvent('download'), p.click('#nlx-esporta-avvia')]);
  const csv = fs.readFileSync(await dl.path(), 'utf8').replace(/^﻿/, '').trim().split(/\r?\n/);
  const emailCsv = csv.slice(1).map(r => r.split(';')[0].replace(/"/g, '')).sort();
  const emailSeg = q(`SELECT email FROM {p}newsletter_iscritti WHERE id IN (${[...giusti].join(',') || 0})`).map(r => r.email).sort();
  ok(`Esporta il segmento: il CSV ha le sue ${atteso} righe, proprio quelle`, emailCsv.length === atteso && JSON.stringify(emailCsv) === JSON.stringify(emailSeg), [emailCsv.length, atteso]);
  ok("l'esportazione si chiama con il segmento", /Segmento «SG VIP che cliccano»/.test(uno('SELECT descrizione FROM {p}newsletter_esportazioni ORDER BY id DESC LIMIT 1')));

  // ===== Flussi: il bivio "fa parte di un segmento"
  const fl = +uno("SELECT id FROM {p}newsletter_flussi WHERE nome = 'SG Flusso'");
  await vai('newsletter-flussi.php?id=' + fl);
  const dati = await p.evaluate(() => ({ c: !!FL_DATI.condizioni.in_segmento, s: Object.values(FL_DATI.segmenti || {}) }));
  ok('flussi: il bivio offre "Fa parte di un segmento" con i segmenti salvati', dati.c && dati.s.includes('SG VIP che cliccano'), dati);
  await p.click('.fl-pezzo[data-tipo="bivio"]');
  await p.selectOption('#fp-cond', 'in_segmento');
  ok('flussi: scelto il segmento nel pannello del bivio', (await p.locator('#fp-val option').count()) >= 3);
  await invia('#fl-form button[type=submit]:has-text("Salva")');
  ok('flussi: il flusso col bivio sul segmento si salva', /in_segmento/.test(uno(`SELECT passi FROM {p}newsletter_flussi WHERE id = ${fl}`)), await flash());
  const usaId = +JSON.parse(uno(`SELECT passi FROM {p}newsletter_flussi WHERE id = ${fl}`)).nodi.filter(x => x.tipo === 'bivio').pop().dati.valore;
  await vai('newsletter-segmenti.php?id=' + usaId);
  ok('un segmento usato da un flusso non si elimina e lo dice', (await p.locator('button:has-text("Elimina il segmento")').count()) === 0 && /il flusso «SG Flusso»/.test(await testo()));

  // ===== Elimina un segmento libero
  await vai('newsletter-segmenti.php?id=' + sV);
  await invia('button:has-text("Elimina il segmento")');
  ok('Elimina un segmento: i contatti restano', !+uno(`SELECT COUNT(*) FROM {p}newsletter_segmenti WHERE id = ${sV}`) && +uno("SELECT COUNT(*) FROM {p}newsletter_iscritti WHERE email LIKE '%@seg-prova.it'") === 5000, await flash());
  // Categoria eliminata: i segmenti restano senza categoria.
  await vai('newsletter-segmenti.php');
  await p.click('#nls-categorie > summary');
  await invia('#nls-categorie button:has-text("Elimina la categoria")');
  ok('Elimina una categoria: i suoi segmenti restano, senza categoria', +uno(`SELECT cartella_id FROM {p}newsletter_segmenti WHERE id = ${s90}`) === 0 && !+uno('SELECT COUNT(*) FROM {p}newsletter_segmenti_cartelle'), await flash());
  await foto('segmenti-elenco');

  // ===== Telefono e tablet
  for (const w of [390, 768]) {
    await p.setViewportSize({ width: w, height: 800 });
    for (const u of ['newsletter-segmenti.php', 'newsletter-segmenti.php?nuovo=1', 'newsletter-segmenti.php?id=' + sR, 'newsletter-contatti.php?filtro=1&segmento=' + sF, 'newsletter-contatti.php?f=' + b64(regF)]) {
      await vai(u);
      if (u.includes('filtro=1')) { await p.click('#nlx-filtro button[data-aggiungi="0"]'); await p.waitForTimeout(200); }
      const lato = await p.evaluate(() => document.documentElement.scrollWidth - innerWidth);
      ok(`${w} px: ${u.slice(0, 60)} non scorre di lato`, lato <= 1, lato);
    }
    if (w === 390) { await vai('newsletter-segmenti.php?id=' + sR); await foto('segmenti-390'); }
  }
  await p.setViewportSize({ width: 1280, height: 900 });

  // ===== Redattore: non vede e non salva
  execFileSync('php', [path.join(__dirname, 'tema-cli.php'), DIR, 'redattore', 'Redattore-Prova-2026!']);
  const ctxR = await br.newContext(); const pr = await ctxR.newPage();
  await pr.goto(B + '/admin/login.php'); await pr.fill('#username', 'redattore-prova'); await pr.fill('#password', 'Redattore-Prova-2026!');
  await Promise.all([pr.waitForNavigation(), pr.click('button[type=submit]')]);
  for (const u of ['newsletter-segmenti.php', 'newsletter-segmenti.php?id=' + sR, 'newsletter-contatti.php?segmento=' + sR]) {
    await pr.goto(B + '/admin/' + u, { waitUntil: 'networkidle' });
    ok('redattore: ' + u + ' lo rimanda alla dashboard', /index\.php/.test(pr.url()) && !/seg-prova\.it|SG Milanesi/.test(await pr.content()), pr.url());
  }
  const tkR = await pr.evaluate(() => (document.querySelector('input[name=_csrf]') || {}).value || '');
  const prima = +uno('SELECT COUNT(*) FROM {p}newsletter_segmenti');
  const rc = await pr.request.post(B + '/admin/newsletter-segmenti.php', { form: { _csrf: tkR, azione: 'salva', nome: 'SG Dal redattore', regole: b64(g1({ campo: 'stato', op: 'iscritto' })) }, maxRedirects: 0 });
  await pr.request.post(B + '/admin/newsletter-segmenti.php', { form: { _csrf: tkR, azione: 'elimina', id: String(sR) }, maxRedirects: 0 });
  const rcj = await pr.request.post(B + '/admin/newsletter-segmenti.php', { form: { _csrf: tkR, azione: 'conta', regole: b64(g1({ campo: 'stato', op: 'iscritto' })) }, maxRedirects: 0 });
  ok('redattore: i POST non salvano, non eliminano e non contano', +uno('SELECT COUNT(*) FROM {p}newsletter_segmenti') === prima && !/"n":/.test(await rcj.text()), [rc.status(), rcj.status()]);
  await ctxR.close();

  ok('nessun errore JavaScript', err.length === 0, err);
  console.log(`\n${n - ko} su ${n} OK`);
  await br.close();
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
