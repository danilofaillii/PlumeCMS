// L'addon Test e questionari (2.22.0) provato nel browser come un amministratore e come chi fa il test.
// Crea dal pannello un test per tipo con la procedura in cinque passi (domande scritte nell'editor del passo 2, fasce,
// testi con l'editor ricco, regole, dopo il test), li pubblica e li fa come un visitatore: una domanda per schermata,
// Indietro, Invio da tastiera, ripresa dopo il ricaricamento, condizionali, invertite, pesi. Controlla il risultato calcolato
// a mano, il grafico, il link condivisibile (e che uno modificato sia rifiutato), il simulatore, le statistiche, il CSV,
// l'email con il consenso e la lista (doppia conferma), l'innesco delle automazioni con il filtro sul risultato, la versione
// senza JavaScript, il telefono a 390 px, il widget Riquadro in evidenza e "Scegli dal sito".
// Su un sito appena installato (va su un sito da buttare), con il server di posta finto acceso:
//   python3 -I prove/smtp-finto.py <porta> <cartella posta> &
// 2.23.0 (sezione D): domande sulla persona (facoltative e obbligatorie, "Preferisco non rispondere", alla fine), risposta breve e
// completa, il risultato completo per email (senza newsletter: niente contatto e niente tag; con la newsletter: dopo la conferma
// il contatto nella lista con i tag e l'automazione "Riceve un tag"), le statistiche per gruppi con la soglia di 5, il CSV, un
// test scritto con la 2.22.0 che esce uguale al byte (pagina e risultato, confrontati con il codice di c3b99e9).
// uso: node prove/prova-test.js <base> <cartella del CMS installato> <porta smtp> <cartella posta> [cartella foto]
const { chromium } = require('playwright');
const path = require('path');
const { execFileSync } = require('child_process');
const C = require('./addon-b-comune.js');
const { ok } = C;
const pag = async (br, err, o) => { const p = await C.pagina(br, err, o); p.setDefaultTimeout(15000); return p; };
const [B, CMS, SMTP, POSTA, FOTO] = process.argv.slice(2);
const PAG = (p, q = '') => B + '/admin/addon-pagina.php?a=test&p=' + p + q;
const cli = (...a) => { const o = execFileSync('php', [path.join(__dirname, 'test-cli.php'), CMS, ...a.map(String)], { encoding: 'utf8' }); return JSON.parse(o.trim().split('\n').pop()); };
const sql = q => cli('sql', q);
const foto = async (p, nome) => { if (FOTO) await p.screenshot({ path: path.join(FOTO, nome + '.png'), fullPage: true }); };
const vai = (p, u) => p.goto(u.startsWith('http') ? u : B + u, { waitUntil: 'networkidle' });
const flash = async p => (await p.locator('.flash').first().innerText().catch(() => '')).trim();
const salvaPasso = async (p, avanti = true) => { await Promise.all([p.waitForNavigation(), p.click(avanti ? 'button[name=vai][value=avanti]' : 'button[name=vai][value=resta]')]); return flash(p); };

/** Un test nuovo dal pannello: Nuovo test, nome, tipo. Ritorna l'id. */
async function nuovo(a, nome, tipo) {
  await vai(a, PAG('test', '&nuovo=1'));
  if (tipo === 'punteggio') await foto(a, 'nuovo');
  await a.fill('#ts-nome-nuovo', nome);
  await Promise.all([a.waitForNavigation(), a.click(`#ts-crea button[name=tipo][value=${tipo}]`)]);
  return +new URL(a.url()).searchParams.get('id');
}
// 2.24.0: ogni passo ha le carte che si aprono in una finestra (<dialog>) e il telefono dell'anteprima.
/** Apre la finestra di una carta (con il clic vero sulla carta o sul pulsante che la apre). */
async function apriCarta(a, fin) { await a.locator(`[data-apri="${fin}"]`).first().click(); await a.waitForSelector(`#${fin}[open]`); }
/** Chiude la finestra aperta con «Fatto». */
async function fatto(a) { await a.locator('dialog.ts-finestra[open] .ts-fin-piede [data-chiudi-fin]').click(); await a.waitForSelector('dialog.ts-finestra[open]', { state: 'detached' }).catch(() => {}); await a.waitForTimeout(80); }
/** Passo 2: una domanda nuova («+ Domanda» apre la sua scheda). q: testo, tipo, area (nome), peso, invertita, obbligatoria, opzioni [[testo, punti]] o [[testo, [punti per profilo]]], si/no [punti], cond [numero domanda, [testi risposta]]. */
async function domanda(a, q) {
  await a.click('[data-piu-d]');
  await a.waitForSelector('#ts-fin-domanda[open]');
  const i = await a.locator('#ts-lavagna .ts-carta-d').count() - 1;
  await a.fill('#tsq-testo', q.testo);
  if (q.tipo) await a.selectOption('#tsq-tipo', q.tipo);
  if (q.area) await a.selectOption('#tsq-area', { label: q.area });
  if (q.peso) await a.fill('#tsq-peso', String(q.peso));
  if (q.invertita) await a.check('#tsq-inv');
  if (q.obbligatoria === false) await a.uncheck('#tsq-obb');
  if (q.opzioni) {
    for (let j = 0; j < q.opzioni.length; j++) {
      if (j >= 2 && q.tipo === 'scelta') await a.click('#ts-fin-domanda [data-piu-o]');
      const [t, pt] = q.opzioni[j];
      if (q.tipo === 'scelta') await a.fill(`#tsq-o${j}`, t);
      if (Array.isArray(pt)) { const campi = a.locator(`#ts-domanda-corpo tr[data-j="${j}"] input[data-o=profilo]`); for (let k = 0; k < pt.length; k++) await campi.nth(k).fill(String(pt[k])); }
      else await a.fill(`#tsq-o${j}-p`, String(pt));
    }
  }
  if (q.cond) await condizione(a, q.cond);
  await fatto(a);
  return i;
}
/** Nella scheda aperta: «Mostra questa domanda: solo se la domanda N ha risposta...» e le risposte da spuntare. */
async function condizione(a, cond) {
  const val = await a.$eval('#tsq-cond', (s, n) => [...s.options].find(o => o.textContent.startsWith('Solo se la domanda ' + n + ' '))?.value || '', cond[0]);
  await a.selectOption('#tsq-cond', val);
  for (const t of cond[1]) await a.locator('#ts-domanda-corpo .ts-valori label', { hasText: t }).locator('input').check();
}
/** Apre la scheda della domanda N (la carta N della lavagna). */
async function apriDomanda(a, n) { await a.locator('#ts-lavagna .ts-carta-d').nth(n - 1).click(); await a.waitForSelector('#ts-fin-domanda[open]'); }
/** Passo 4: apre la carta della fascia (o del profilo) e scrive nell'editor ricco: la risposta completa, oppure (2.23.0) la breve. */
async function testoRicco(a, nome, testo, pulsante = null, quale = 'completa') {
  const carta = a.locator('button[data-testo]', { hasText: nome instanceof RegExp ? nome : new RegExp('^\\s*' + nome.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')) }).first();
  await carta.click();
  const fin = a.locator('dialog.ts-finestra[open]');
  await fin.locator('.ql-editor').nth(quale === 'breve' ? 0 : 1).click();
  await a.keyboard.type(testo);
  if (pulsante) { await fin.locator('input[name$="[pulsante_testo]"]').fill(pulsante[0]); await fin.locator('input[name$="[pulsante_link]"]').fill(pulsante[1]); }
  await fatto(a);
}
/** Trascina con il mouse vero (SortableJS del pannello): dal centro di «da» a 20 px sotto l'alto di «a». */
async function trascina(a, da, verso) {
  await verso.scrollIntoViewIfNeeded(); await da.scrollIntoViewIfNeeded();
  const s = await da.boundingBox(), d = await verso.boundingBox();
  const x0 = s.x + s.width / 2, y0 = s.y + s.height / 2, x1 = d.x + d.width / 2, y1 = d.y + Math.min(20, d.height / 2);
  await a.mouse.move(x0, y0); await a.mouse.down();
  for (let i = 1; i <= 15; i++) { await a.mouse.move(x0 + (x1 - x0) * i / 15, y0 + (y1 - y0) * i / 15); await a.waitForTimeout(20); }
  await a.waitForTimeout(150); await a.mouse.up(); await a.waitForTimeout(250);
}
const telTesto = async a => (await a.locator('#ts-tel').innerText()).replace(/\s+/g, ' ');
/** Pubblica dal passo 5. */
async function pubblica(a, id) {
  await vai(a, PAG('test', `&id=${id}&passo=5`));
  await Promise.all([a.waitForNavigation(), a.click('button[name=azione][value=pubblica]')]);
  return flash(a);
}
/** Il visitatore risponde alla domanda che si vede (un testo di risposta, oppure un numero per il cursore) e va avanti. */
async function rispondi(v, risposta, avanti = true) {
  const f = v.locator('.tq-domanda:not([hidden])');
  if (typeof risposta === 'number') await f.locator('input[type=range]').fill(String(risposta));
  else await f.locator('label.tq-opz', { hasText: new RegExp('^\\s*' + risposta.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*$') }).click();
  if (avanti) await v.click('[data-avanti]');
}
const visibile = async v => (await v.locator('.tq-domanda:not([hidden]) .tq-testo-domanda').innerText().catch(() => '')).trim();

(async () => {
  const br = await chromium.launch();
  const err = [];
  const a = await pag(br, err);
  await C.entra(a, B);
  cli('smtp', SMTP);
  const listaId = cli('lista', 'Lettori dei test');
  ok('spento: la pagina dell\'addon nel pannello dà 404', (await a.goto(PAG('test'))).status() === 404);
  const prima = (await (await a.request.get(B + '/fai-il-test/qualcosa/')).status());
  const f1 = await C.addon(a, B, 'test', true);
  ok('accendo da Impostazioni > Addon: tabelle a posto', /Test e questionari» è acceso/.test(f1) && /tabelle a posto/.test(f1), f1);
  ok('nel menu compare Test, con Test e Statistiche', await a.locator('nav.admin-nav a[href*="a=test&p=statistiche"]').count() >= 1 && await a.locator('nav.admin-nav a[href*="a=test&p=test"]').count() >= 1);
  await vai(a, PAG('test'));
  ok('elenco vuoto: dice cosa fare', /Ancora nessun test/.test(await a.locator('.empty-state').innerText()));
  ok('prima di accenderlo /fai-il-test/qualcosa/ era 404', prima === 404, prima);

  // =============================================================== A. Test a punteggio
  const A = await nuovo(a, 'Prova punteggio', 'punteggio');
  ok('A: creato in bozza, procedura al passo 1', A > 0 && /passo=1/.test(a.url()) && /bozza/i.test(await flash(a)));
  ok('A: cinque passi nella barra, il primo è quello attivo', await a.locator('.fo-passi li').count() === 5 && await a.locator('.fo-passi li.qui').innerText().then(t => /Presentazione/.test(t)));
  await apriCarta(a, 'ts-fin-pres');
  await a.fill('#ts-pres', 'Sei domande di prova.\n\nRispondi come ti viene.');
  ok('A passo 1: il telefono mostra la presentazione mentre scrivi, con «Inizia il test»', /Sei domande di prova/.test(await telTesto(a)) && /Inizia il test/.test(await telTesto(a)));
  await fatto(a);
  ok('A: passo 1 salvato', /Passo 1 salvato/.test(await salvaPasso(a)));
  await domanda(a, { testo: 'Uno: quanto spesso?' });
  await domanda(a, { testo: 'Due: quanto spesso? (invertita)', invertita: true });
  await domanda(a, { testo: 'Tre: quanto spesso? (peso 2)', peso: 2 });
  await domanda(a, { testo: 'Quattro: bevi caffè?', tipo: 'sino', opzioni: [['Sì', 3], ['No', 0]] });
  await domanda(a, { testo: 'Cinque: quante volte?', tipo: 'scelta', opzioni: [['Poche', 0], ['Tante', 4]], cond: [4, ['Sì']] });
  await domanda(a, { testo: 'Sei: da 0 a 10?', tipo: 'cursore' });
  ok('A: la domanda 5 è segnata condizionale (bollino della carta)', /Condizionale/.test(await a.locator('#ts-lavagna .ts-carta-d').nth(4).innerText()));
  ok('A: un test nuovo è «Una per pagina» (come prima della 2.24.0): sei colonne più quella sulla persona', await a.getAttribute('[data-modo=una]', 'aria-pressed') === 'true' && await a.locator('.ts-col[data-pag]').count() === 6 && await a.locator('.ts-col-persona').count() === 1 && await a.locator('[data-nuova]').count() === 0);
  // Spostare in alto la condizionale toglie la condizione (dipenderebbe da una domanda che viene dopo) e lo dice.
  await apriDomanda(a, 5);
  await a.click('#ts-fin-domanda [data-sposta-d="-1"]');
  ok('A: spostata sopra la sua domanda, la condizione si toglie e lo si annuncia', /Tolta la condizione/.test(await a.locator('#ts-annuncio').innerText()) && !/Condizionale/.test(await a.locator('#ts-lavagna .ts-carta-d').nth(3).innerText()));
  await a.click('#ts-fin-domanda [data-sposta-d="1"]');
  await condizione(a, [4, ['Sì']]);
  await fatto(a);
  await foto(a, 'a-passo2');
  ok('A: passo 2 salvato', /Passo 2 salvato/.test(await salvaPasso(a, false)));
  const dA = cli('sql', `SELECT dati FROM {p}test_test WHERE id = ${A}`)[0].dati;
  const DA = JSON.parse(dA);
  ok('A: sei domande salvate con tipo, invertita, peso, sì/no, condizione', DA.domande.length === 6 && DA.domande[1].invertita === true && DA.domande[2].peso === 2 && DA.domande[3].tipo === 'sino' && DA.domande[3].opzioni[0].punti === 3
    && DA.domande[4].condizione && DA.domande[4].condizione.domanda === DA.domande[3].k && DA.domande[4].condizione.valori.join() === 'si' && DA.domande[5].tipo === 'cursore', DA.domande.map(q => q.tipo));
  await vai(a, PAG('test', `&id=${A}&passo=2`));
  await apriDomanda(a, 3);
  ok('A: riaperto, il passo 2 ha le sei domande (carte), la terza con il peso 2', await a.locator('#ts-lavagna .ts-carta-d').count() === 6 && await a.inputValue('#tsq-peso') === '2');
  await a.keyboard.press('Escape');
  await salvaPasso(a);
  // Passo 3: le fasce, con il controllo dei buchi.
  ok('A: passo 3 con le tre fasce di partenza (carte in fila), coperte da 0 a 100', await a.locator('#ts-fasce-ed .ts-carta-f').count() === 3 && /senza buchi/.test(await a.locator('#ts-fasce-ed .ts-fasce-stato').innerText()) && await a.locator('#ts-fasce-ed .ts-copertura .ts-buco').count() === 0);
  await a.locator('#ts-fasce-ed .ts-carta-f[data-i="1"]').click();
  await a.fill('#tsf-da', '36');
  ok('A: un buco fra le fasce si vede subito, in parole e sulla barra da 0 a 100', /buco: da 34 a 35/.test(await a.locator('#ts-fasce-ed .ts-fasce-stato').innerText()) && await a.locator('#ts-fasce-ed .ts-copertura .ts-buco').count() === 1, await a.locator('#ts-fasce-ed .ts-fasce-stato').innerText());
  await fatto(a);
  const fl3 = await salvaPasso(a, false);
  ok('A: salvato con il buco, il messaggio lo ripete', /buco/.test(fl3), fl3);
  await a.locator('#ts-fasce-ed .ts-carta-f[data-i="1"]').click();
  await a.fill('#tsf-da', '34');
  await a.fill('#tsf-nome', 'Medio dal vivo');
  ok('A passo 3: il telefono mostra il risultato della fascia toccata, e cambia mentre scrivi', /Medio dal vivo/.test(await telTesto(a)) && /su 100/.test(await telTesto(a)));
  await a.fill('#tsf-nome', 'Medio');
  await fatto(a);
  await salvaPasso(a, false);
  ok('A: simulatore: minimo 0, massimo 100, ogni fascia raggiunta', await a.locator('.ts-sim tbody tr').first().innerText().then(t => /\b0\b/.test(t) && /Basso/.test(t)) && /100/.test(await a.locator('.ts-sim tbody tr').nth(2).innerText()) && /Ogni fascia viene raggiunta/.test(await a.locator('#simulatore').innerText()));
  await foto(a, 'a-passo3');
  await salvaPasso(a);
  // Passo 4: i testi, con un pulsante scelto da "Scegli dal sito".
  await testoRicco(a, 'Basso', 'Riposi bene.');
  await testoRicco(a, 'Medio', 'Riposo da curare.');
  await testoRicco(a, 'Alto', 'Riposo in rosso: parliamone.', ['Prenota', '']);
  await a.locator('button[data-testo]', { hasText: /^\s*Alto/ }).click();
  ok('A passo 4: il telefono mostra il risultato della carta (titolo, testo, pulsante)', /Alto/.test(await telTesto(a)) && /Riposo in rosso/.test(await telTesto(a)) && /Prenota/.test(await telTesto(a)));
  await a.locator('dialog.ts-finestra[open] .pn-link-scegli').click();
  await a.waitForSelector('dialog.pn-link[open] .pn-link-voce');
  ok('A: "Scegli dal sito" mostra anche i test (tipo Test)', await a.locator('dialog.pn-link[open] .pn-link-voce', { hasText: 'Prova punteggio' }).count() >= 1);
  await a.locator('dialog.pn-link[open] input[type=search]').fill('Contatti');
  await a.waitForTimeout(700);
  await a.locator('dialog.pn-link[open] .pn-link-voce', { hasText: 'Contatti' }).first().click();
  const linkScelto = await a.locator('dialog.ts-finestra[open] input[name$="[pulsante_link]"]').inputValue();
  ok('A: il link del pulsante scelto dal sito', /contatti/.test(linkScelto), linkScelto);
  await fatto(a);
  await a.click('[data-vista=email]');
  ok('A passo 4: «Email» mostra l\'email del risultato (oggetto, testo completo)', /Il tuo risultato: Prova punteggio/.test(await telTesto(a)) && /Riposo in rosso/.test(await telTesto(a)));
  await a.click('[data-vista=schermo]');
  await apriCarta(a, 'ts-fin-avvertenza');
  await a.fill('#ts-avvertenza', 'Avvertenza di prova: non è una diagnosi.');
  await fatto(a);
  await foto(a, 'a-passo4');
  ok('A: passo 4 salvato', /Passo 4 salvato/.test(await salvaPasso(a)));
  // Passo 5: l'email con il consenso e la lista.
  ok('A: un test nuovo nasce con «Il risultato completo solo per email» acceso (2.23.0)', await a.isChecked('input[name=solo_email]'));
  ok('A passo 5: il telefono mostra la fine del test con «Lasciami la tua email...»', /Lasciami la tua email/.test(await telTesto(a)));
  await apriCarta(a, 'ts-fin-email');
  await a.check('input[name=email]');
  await fatto(a);
  await apriCarta(a, 'ts-fin-newsletter');
  await a.selectOption('#ts-lista', String(listaId));
  ok('A passo 5: con la lista, nel telefono compare la casella della newsletter', /Voglio ricevere anche la newsletter/.test(await telTesto(a)));
  await fatto(a);
  ok('A: passo 5 salvato', /Passo 5 salvato/.test(await salvaPasso(a, false)));
  await foto(a, 'a-passo5');
  const pubA = await pubblica(a, A);
  ok('A: pubblicato, il messaggio dice l\'indirizzo', /fai-il-test\/prova-punteggio\//.test(pubA), pubA);
  ok('A: il testo ricco è stato ripulito e salvato', /Riposo in rosso/.test(JSON.stringify(JSON.parse(cli('sql', `SELECT dati FROM {p}test_test WHERE id = ${A}`)[0].dati).fasce[2].testo)));

  // Le automazioni: una per chi è Alto, una per chi è Basso (filtro sul risultato), dal CLI; la tela le mostra.
  const nodo = (id, tipo, dati = {}) => ({ id, tipo, x: 40, y: 40 + id * 140, dati });
  const em = (id, ogg) => nodo(id, 'email', { oggetto: ogg, anteprima: '', corpo: '<p>Ciao, «' + ogg + '».</p>', pulsante_testo: '', pulsante_link: '', ascolto: 0 });
  const flusso = (nome, esito) => cli('salva_flusso', JSON.stringify({ nome, stato: 'attiva', inneschi: [{ tipo: 'test', id: A, esito }], grafo: { nodi: [nodo(0, 'innesco'), em(1, nome)], archi: [{ da: 0, uscita: 'poi', a: 1 }] } }));
  const flAlto = flusso('Dopo il test: Alto', 'f:alto'), flBasso = flusso('Dopo il test: Basso', 'f:basso');
  ok('due automazioni con l\'innesco "Completa un test" salvate', typeof flAlto === 'number' && typeof flBasso === 'number', [flAlto, flBasso]);
  await vai(a, B + '/admin/newsletter-flussi.php?id=' + flAlto);
  ok('la tela mostra l\'innesco: quale test e con quale risultato', await a.inputValue('#fi-t0') === 'test' && await a.inputValue('#fi-v0') === String(A) && await a.inputValue('#fi-e0') === 'f:alto'
    && await a.locator('#fi-e0 option', { hasText: 'Totale: Alto' }).count() === 1);
  await vai(a, PAG('test', `&id=${A}&passo=5`));
  ok('A: il passo 5 elenca le automazioni che usano il test (nella finestra della carta Automazioni)', await a.locator('#ts-fin-automazioni a', { hasText: 'Dopo il test: Alto' }).count() === 1 && /2 automazioni usano/.test(await a.locator('[data-apri="ts-fin-automazioni"]').innerText()));

  // =============================================================== Il visitatore, con JavaScript
  const v = await pag(br, err);
  const rA = await vai(v, '/fai-il-test/prova-punteggio/');
  ok('A sul sito: 200, titolo, "5-6 domande, circa 2 minuti", header e footer del sito', rA.status() === 200 && /Prova punteggio/.test(await v.locator('h1').first().innerText()) && /5-6 domande, circa 2 minuti/.test(await v.locator('.tq-numeri').first().innerText()) && await v.locator('footer').count() >= 1);
  ok('A sul sito: la presentazione composta con l\'editor', /Sei domande di prova|Scrivi qui la presentazione/.test(await v.locator('.tq-presentazione').innerText()));
  ok('A sul sito: prima di cominciare le domande non si vedono', !(await v.locator('#tq-app').isVisible()));
  await v.click('#tq-inizia');
  ok('una domanda per schermata, la prima, con la barra di avanzamento (aria) a 0', await v.locator('.tq-domanda:not([hidden])').count() === 1 && /Uno/.test(await visibile(v))
    && await v.getAttribute('.tq-barra[role=progressbar]', 'aria-valuenow') === '0' && /Domanda 1 di 5/.test(await v.locator('.tq-passo').innerText()));
  ok('il fuoco va sulla domanda', await v.evaluate(() => document.activeElement && document.activeElement.classList.contains('tq-domanda')));
  await v.click('[data-avanti]');
  ok('Avanti senza risposta: l\'errore si annuncia (role=alert) e si resta lì', await v.locator('.tq-domanda:not([hidden]) .tq-manca[role=alert]').count() === 1 && /Uno/.test(await visibile(v)));
  await rispondi(v, 'Mai');
  ok('Avanti porta alla domanda 2, barra a 20', /Due/.test(await visibile(v)) && await v.getAttribute('.tq-barra[role=progressbar]', 'aria-valuenow') === '20');
  await v.click('[data-indietro]');
  ok('Indietro torna alla domanda 1 con la risposta data', /Uno/.test(await visibile(v)) && await v.locator('.tq-domanda:not([hidden]) input[value=s0]').isChecked());
  await rispondi(v, 'Spesso');
  await rispondi(v, 'Raramente');
  // Tastiera: frecce sulle risposte, Invio va avanti (non manda il modulo).
  await v.locator('.tq-domanda:not([hidden]) input[value=s0]').focus();
  await v.keyboard.press('ArrowRight'); await v.keyboard.press('ArrowRight');
  await v.keyboard.press('Enter');
  ok('tastiera: frecce scelgono "A volte", Invio va alla domanda 4', /Quattro/.test(await visibile(v)), await visibile(v));
  // Ricarico: le risposte sono rimaste nel browser.
  await v.reload({ waitUntil: 'networkidle' });
  ok('dopo il ricaricamento: "Riprendi da dove eri"', await v.locator('#tq-riprendi [data-riprendi]').isVisible());
  await v.click('#tq-riprendi [data-riprendi]');
  ok('ripreso alla domanda 4', /Quattro/.test(await visibile(v)), await visibile(v));
  await v.waitForTimeout(3000); // la pagina ricaricata ha un codice del modulo nuovo: un invio entro 3 secondi sarebbe da robot
  await rispondi(v, 'Sì', false);
  await foto(v, 'a-domanda');
  await v.click('[data-avanti]');
  ok('con "Sì" compare la domanda condizionale, e il totale diventa 6', /Cinque/.test(await visibile(v)) && /Domanda 5 di 6/.test(await v.locator('.tq-passo').innerText()));
  await rispondi(v, 'Tante');
  await rispondi(v, 7);
  ok('ultimo passo: "Vedi il risultato", barra al 100', await v.locator('#tq-fine button[type=submit]').isVisible() && await v.getAttribute('.tq-barra[role=progressbar]', 'aria-valuenow') === '100');
  await foto(v, 'a-ultimo');
  await Promise.all([v.waitForNavigation(), v.click('#tq-fine button[type=submit]')]);
  if (!/risultato/.test(v.url())) console.log('     pagina dopo l\'invio: ' + (await v.locator('.modulo-errore, .errore-campo').allInnerTexts()).join(' | '));
  // A mano: 3 (Spesso) + 3 (Raramente invertita: 4-1) + 2x2 (A volte, peso 2) + 3 (Sì) + 4 (Tante) + 7 = 24 su 4+4+8+3+4+10 = 33 -> 72,7 -> 73, Alto.
  ok('risultato: 73 su 100, fascia Alto (calcolato a mano: 24 punti su 33)', /\/risultato\/\?e=/.test(v.url()) && (await v.locator('h1').innerText()).trim() === 'Alto' && (await v.locator('.tq-punteggio b').innerText()).trim() === '73', [await v.locator('h1').innerText(), await v.locator('.tq-punteggio').innerText().catch(() => '')]);
  ok('risultato: il testo della fascia, il pulsante verso Contatti, l\'avvertenza', /Riposo in rosso/.test(await v.locator('.tq-testo').innerText()) && /contatti/.test(await v.locator('.tq-azione a').getAttribute('href')) && /Avvertenza di prova/.test(await v.locator('.tq-avvertenza').innerText()));
  ok('risultato: noindex e senza dati personali nel link', /noindex/.test(await v.getAttribute('meta[name=robots]', 'content').catch(() => 'noindex')) && !/email|@/.test(await v.inputValue('#tq-link')));
  await foto(v, 'a-risultato');
  const linkA = await v.inputValue('#tq-link');
  const calcA = cli('calcola', A, JSON.stringify({ [DA.domande[0].k]: 's3', [DA.domande[1].k]: 's1', [DA.domande[2].k]: 's2', [DA.domande[3].k]: 'si', [DA.domande[4].k]: DA.domande[4].opzioni[1].k, [DA.domande[5].k]: '7' }));
  ok('test_calcola dal CLI dà lo stesso: 24 su 33, 73, alto', calcA.punti === 24 && calcA.massimo === 33 && calcA.totale === 73 && calcA.fascia === 'alto', [calcA.punti, calcA.massimo, calcA.totale]);
  const tent = sql(`SELECT completato, ultima FROM {p}test_tentativi WHERE test_id = ${A}`);
  ok('il tentativo (codice casuale) è segnato finito; nessun IP salvato nelle tabelle dei test', tent.length === 1 && +tent[0].completato === 1 && sql(`SHOW COLUMNS FROM {p}test_risultati`).every(c => !/ip|email/i.test(c.Field)), tent);
  ok('un risultato registrato con solo il giorno, totale 73, fascia alto', JSON.stringify(sql(`SELECT totale, fascia FROM {p}test_risultati WHERE test_id = ${A}`)) === '[{"totale":73,"fascia":"alto"}]');

  // Il link condivisibile, e i link cambiati.
  const w = await pag(br, err);
  await vai(w, linkA);
  ok('il link condiviso apre lo stesso risultato, senza il riquadro dell\'email', (await w.locator('h1').innerText()).trim() === 'Alto' && await w.locator('#tq-email').count() === 0);
  const u = new URL(linkA);
  const e = u.searchParams.get('e'), f = u.searchParams.get('f');
  const altroE = Buffer.from(JSON.stringify(Object.assign(JSON.parse(Buffer.from(e.replace(/-/g, '+').replace(/_/g, '/'), 'base64').toString()), { o: 5, f: 'basso' }))).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  const r1 = await w.goto(B + '/fai-il-test/prova-punteggio/risultato/?e=' + altroE + '&f=' + f);
  ok('un esito cambiato con la firma vecchia: rifiutato (404, "Questo link non funziona")', r1.status() === 404 && /non funziona/.test(await w.content()));
  const r2 = await w.goto(B + '/fai-il-test/prova-punteggio/risultato/?e=' + e + '&f=' + f.slice(0, -1) + (f.endsWith('0') ? '1' : '0'));
  ok('una firma cambiata: rifiutata', r2.status() === 404);

  // L'email del risultato, con il consenso e la lista: doppia conferma, poi l'automazione per chi è Alto.
  const nEmail = C.posta(POSTA).length;
  const modulo = v.locator('#tq-email form');
  ok('il riquadro "Vuoi ricevere il risultato per email?" con il consenso e, separata, l\'iscrizione', await modulo.count() === 1 && await modulo.locator('input[name=consenso]').count() === 1 && await modulo.locator('input[name=iscrivi]').count() === 1);
  await modulo.locator('input[name=email]').fill('visitatore@tq-prova.it');
  await Promise.all([v.waitForNavigation({ timeout: 2500 }).catch(() => {}), modulo.locator('button[type=submit]').click()]);
  ok('senza il consenso non parte (il browser lo chiede)', await v.locator('#tq-email form').count() === 1 && C.posta(POSTA).length === nEmail);
  await modulo.locator('input[name=consenso]').check();
  await modulo.locator('input[name=iscrivi]').check();
  await C.captcha(modulo, v);
  await Promise.all([v.waitForNavigation(), modulo.locator('button[type=submit]').click()]);
  ok('mandato: lo dice (role=status) e chiede di confermare la newsletter', /ti abbiamo mandato il risultato/.test(await v.locator('.tq-ok[role=status]').innerText()) && /conferma/.test(await v.locator('.tq-ok').innerText()));
  const arrivate = await C.nuove(POSTA, nEmail, 2);
  const emRis = arrivate.find(m => /Il tuo risultato: Prova punteggio/.test(m.oggetto)), emConf = arrivate.find(m => !/Il tuo risultato/.test(m.oggetto));
  ok('arriva l\'email con il risultato: 73 su 100, Alto, il link per rivederlo', !!emRis && /73 su 100/.test(emRis.html) && /Alto/.test(emRis.html) && C.link(emRis).some(l => l.includes('/risultato/?e=')), arrivate.map(m => m.oggetto));
  const iscr = sql(`SELECT id, stato FROM {p}newsletter_iscritti WHERE email = 'visitatore@tq-prova.it'`);
  ok('il contatto c\'è, in attesa di conferma, con l\'esito del test nella sua storia', iscr.length === 1 && iscr[0].stato === 'in_attesa' && sql(`SELECT dettaglio FROM {p}newsletter_storia WHERE contatto_id = ${iscr[0].id} AND tipo = 'test'`).some(x => /Prova punteggio.*Alto.*73/.test(x.dettaglio)));
  ok('prima della conferma l\'automazione non è partita (aspetta in test_attese)', sql(`SELECT COUNT(*) n FROM {p}newsletter_corsi WHERE iscritto_id = ${iscr[0].id}`)[0].n == 0 && sql(`SELECT esiti FROM {p}test_attese WHERE iscritto_id = ${iscr[0].id}`)[0]?.esiti === 'f:alto');
  const linkConf = emConf ? C.link(emConf).find(l => /conferma/.test(l)) : '';
  await vai(w, linkConf);
  const corsi = sql(`SELECT flusso_id FROM {p}newsletter_corsi WHERE iscritto_id = ${iscr[0].id}`).map(x => +x.flusso_id);
  ok('confermata l\'iscrizione: parte solo l\'automazione "Alto" (filtro sul risultato)', corsi.includes(flAlto) && !corsi.includes(flBasso), corsi);
  const nPrimaGiro = C.posta(POSTA).length;
  cli('giro'); cli('giro');
  const dopoGiro = await C.nuove(POSTA, nPrimaGiro, 1);
  ok('il giro della newsletter manda l\'email dell\'automazione "Alto", non quella "Basso"', dopoGiro.some(m => /Dopo il test: Alto/.test(m.oggetto)) && !dopoGiro.some(m => /Basso/.test(m.oggetto)), dopoGiro.map(m => m.oggetto));
  ok('nella scheda del contatto (Cronologia) c\'è il test', /Prova punteggio/.test(await (await a.request.get(B + '/admin/newsletter-contatto.php?id=' + iscr[0].id)).text()) || sql(`SELECT COUNT(*) n FROM {p}newsletter_storia WHERE contatto_id = ${iscr[0].id} AND tipo='test'`)[0].n == 1);

  // =============================================================== Senza JavaScript
  const nj = await (await br.newContext({ javaScriptEnabled: false, viewport: { width: 1280, height: 900 } })).newPage();
  await nj.goto(B + '/fai-il-test/prova-punteggio/', { waitUntil: 'load' });
  ok('senza JavaScript: tutte e sei le domande in una pagina, la condizionale dice "Rispondi solo se..."', await nj.locator('.tq-domanda').count() === 6 && await nj.locator('#tq-app').isVisible()
    && /Rispondi solo se alla domanda 4 hai risposto «Sì»/.test(await nj.locator('.tq-domanda').nth(4).innerText()));
  for (const [i, val] of [[0, 's3'], [1, 's1'], [2, 's2'], [3, 'no']]) await nj.locator('.tq-domanda').nth(i).locator(`input[value="${val}"]`).check();
  await nj.locator('.tq-domanda').nth(4).locator('label.tq-opz', { hasText: 'Tante' }).locator('input').check(); // risponde anche se non dovrebbe: non conta
  await nj.locator('input[type=range]').fill('7');
  await nj.waitForTimeout(3200);
  await Promise.all([nj.waitForNavigation(), nj.click('#tq-fine button[type=submit]')]);
  // A mano: 3 + 3 + 4 + 0 (No) + 7 = 17 su 4+4+8+3+10 = 29 (la 5 non è mostrata) -> 58,6 -> 59, Medio.
  ok('senza JavaScript: risultato 59, Medio (la domanda non mostrata non conta: 17 su 29)', (await nj.locator('h1').innerText()).trim() === 'Medio' && (await nj.locator('.tq-punteggio b').innerText()).trim() === '59', await nj.locator('h1').innerText());
  // Un invio con una domanda obbligatoria vuota: la pagina torna con l'errore sotto la domanda e le risposte date.
  const tok = await (await nj.goto(B + '/fai-il-test/prova-punteggio/')).text().then(h => (h.match(/name="gettone" value="([^"]+)"/) || [])[1]);
  await nj.waitForTimeout(3200);
  const r422 = await nj.request.post(B + '/fai-il-test/prova-punteggio/invia/', { form: { gettone: tok, [`r[${DA.domande[1].k}]`]: 's2', [`r[${DA.domande[3].k}]`]: 'no', [`r[${DA.domande[5].k}]`]: '3' } });
  const h422 = await r422.text();
  ok('senza la risposta obbligatoria: 422, "domande da sistemare", errore sotto la domanda, risposte rimaste', r422.status() === 422 && /domande da sistemare/.test(h422) && /class="tq-domanda errato" id="td-/.test(h422) && new RegExp(`name="r\\[${DA.domande[1].k}\\]" value="s2" checked`).test(h422));

  // =============================================================== B. Test ad aree, con regole e grafico
  const Bid = await nuovo(a, 'Prova aree', 'aree');
  await salvaPasso(a);
  await apriCarta(a, 'ts-fin-aree');
  await a.click('#ts-aree-ed [data-piu]'); await a.fill('#tsn-a0', 'Corpo');
  await a.click('#ts-aree-ed [data-piu]'); await a.fill('#tsn-a1', 'Mente');
  await fatto(a);
  ok('B: il pulsante delle aree dice quante sono', /Aree: 2/.test(await a.locator('[data-conta=aree]').innerText()));
  await domanda(a, { testo: 'Corpo uno', area: 'Corpo' });
  await domanda(a, { testo: 'Corpo due (invertita)', area: 'Corpo', invertita: true });
  await domanda(a, { testo: 'Mente uno (peso 2)', area: 'Mente', peso: 2 });
  await domanda(a, { testo: 'Mente due', area: 'Mente', tipo: 'sino' });
  ok('B: passo 2 salvato con due aree e quattro domande', /Passo 2 salvato/.test(await salvaPasso(a)));
  ok('B: passo 3 con le fasce del totale e di ogni area', await a.locator('.ts-fasce-area').count() === 2);
  await salvaPasso(a);
  // Passo 4: la regola "Doppio alto" (Corpo Alto E Mente Alto) e una che non può mai valere.
  await a.click('#ts-regole-ed [data-piu-r]');
  await a.fill('#tsr-n', 'Doppio alto');
  await a.selectOption('#tsr-c0', { label: 'Area Corpo' }); await a.selectOption('#tsr-f0', { label: 'Alto' });
  await a.click('#ts-regola-corpo [data-piu-c]');
  await a.selectOption('#tsr-c1', { label: 'Area Mente' }); await a.selectOption('#tsr-f1', { label: 'Alto' });
  await a.locator('#ts-regola-corpo .ql-editor').nth(1).click(); await a.keyboard.type('Corpo e mente al massimo.');
  ok('B passo 4: il telefono mostra la regola mentre la scrivi', /Doppio alto/.test(await telTesto(a)) && /Corpo e mente al massimo/.test(await telTesto(a)));
  await fatto(a);
  await a.click('#ts-regole-ed [data-piu-r]');
  await a.fill('#tsr-n', 'Mai');
  await a.selectOption('#tsr-c0', { label: 'Area Corpo' }); await a.selectOption('#tsr-f0', { label: 'Basso' });
  await a.click('#ts-regola-corpo [data-piu-c]');
  await a.selectOption('#tsr-c1', { label: 'Area Corpo' }); await a.selectOption('#tsr-f1', { label: 'Alto' });
  await fatto(a);
  ok('B: due carte delle regole, con le condizioni in parole', await a.locator('#ts-regole-ed .ts-carta-r').count() === 2 && /Area Corpo in fascia Alto e Area Mente in fascia Alto/.test(await a.locator('#ts-regole-ed .ts-carta-r').first().innerText()));
  await testoRicco(a, 'Alto (da', 'Totale alto.');
  ok('B: passo 4 salvato con due regole', /Passo 4 salvato/.test(await salvaPasso(a, false)) && JSON.parse(sql(`SELECT dati FROM {p}test_test WHERE id = ${Bid}`)[0].dati).regole.length === 2);
  await vai(a, PAG('test', `&id=${Bid}&passo=3`));
  ok('B: il simulatore dice che la regola «Mai» non vale mai', /La regola «Mai» non vale mai/.test(await a.locator('#simulatore').innerText()));
  await foto(a, 'b-passo3');
  // 2.23.0: B senza il risultato solo per email (e senza l'email): a schermo il testo completo, niente riquadro.
  await vai(a, PAG('test', `&id=${Bid}&passo=5`));
  await apriCarta(a, 'ts-fin-email');
  await a.uncheck('input[name=solo_email]');
  ok('B passo 5: senza email il telefono dice che il test finisce con il risultato a schermo', /Nessun riquadro dell'email/.test(await telTesto(a)));
  await fatto(a);
  await salvaPasso(a, false);
  ok('B: pubblicato', /pubblicato/i.test(await pubblica(a, Bid)));
  const DB_ = JSON.parse(sql(`SELECT dati FROM {p}test_test WHERE id = ${Bid}`)[0].dati);
  await vai(v, '/fai-il-test/prova-aree/');
  await v.click('#tq-inizia');
  await rispondi(v, 'Sempre'); await rispondi(v, 'Mai'); await rispondi(v, 'Spesso'); await rispondi(v, 'Sì');
  await v.waitForTimeout(3000);
  await Promise.all([v.waitForNavigation(), v.click('#tq-fine button[type=submit]')]);
  // A mano: Corpo 4 + 4 (Mai invertita) = 8 su 8 -> 100 Alto; Mente 3x2 + 1 = 7 su 9 -> 78 Alto; totale 15 su 17 -> 88. Vale la regola.
  ok('B: risultato "Doppio alto" (la regola vince sulla fascia), totale 88', (await v.locator('h1').innerText()).trim() === 'Doppio alto' && (await v.locator('.tq-punteggio b').innerText()).trim() === '88' && /Corpo e mente al massimo/.test(await v.locator('.tq-testo').innerText()));
  const desc = await v.locator('svg.tq-grafico desc').textContent();
  ok('B: il grafico SVG (role img) con Corpo 100 e Mente 78, fasce Alto', await v.locator('svg.tq-grafico[role=img]').count() === 1 && /Corpo: 100 su 100 \(Alto\)/.test(desc) && /Mente: 78 su 100 \(Alto\)/.test(desc) && await v.locator('svg.tq-grafico rect.tq-g-barra').count() === 2, desc);
  await foto(v, 'b-risultato');
  ok('B: niente riquadro dell\'email (non chiesto nel passo 5)', await v.locator('#tq-email').count() === 0);

  // =============================================================== C. Che tipo sei (profili), al telefono
  const Cid = await nuovo(a, 'Prova profili', 'profili');
  await salvaPasso(a);
  await apriCarta(a, 'ts-fin-profili');
  await a.click('#ts-profili-ed [data-piu]'); await a.fill('#tsn-p0', 'Gatto');
  await a.click('#ts-profili-ed [data-piu]'); await a.fill('#tsn-p1', 'Cane');
  await fatto(a);
  await domanda(a, { testo: 'Sabato sera?', tipo: 'scelta', opzioni: [['Divano', [2, 0]], ['Parco', [0, 2]]] });
  await domanda(a, { testo: 'Ti piace correre?', tipo: 'sino', opzioni: [['Sì', [0, 2]], ['No', [1, 0]]] });
  await domanda(a, { testo: 'Colazione?', tipo: 'scelta', peso: 2, opzioni: [['Tranquilla', [1, 0]], ['Di corsa', [0, 1]]] });
  ok('C: passo 2 salvato', /Passo 2 salvato/.test(await salvaPasso(a, false)));
  const DC = JSON.parse(sql(`SELECT dati FROM {p}test_test WHERE id = ${Cid}`)[0].dati);
  const pari = JSON.stringify({ [DC.domande[0].k]: DC.domande[0].opzioni[0].k, [DC.domande[1].k]: 'si' }); // Gatto 2, Cane 2
  ok('C: in parità vince il primo profilo dell\'elenco (Gatto)', DC.profili[0].nome === 'Gatto' && cli('calcola', Cid, pari).profilo === DC.profili[0].k);
  await apriCarta(a, 'ts-fin-profili');
  await a.locator('#ts-profili-ed li[data-i="1"] [data-su]').click();
  await fatto(a);
  await salvaPasso(a, false);
  const DC2 = JSON.parse(sql(`SELECT dati FROM {p}test_test WHERE id = ${Cid}`)[0].dati);
  ok('C: spostato Cane in cima, in parità vince Cane', DC2.profili[0].nome === 'Cane' && cli('calcola', Cid, pari).profilo === DC2.profili[0].k);
  await apriCarta(a, 'ts-fin-profili');
  await a.locator('#ts-profili-ed li[data-i="1"] [data-su]').click();
  await fatto(a);
  await salvaPasso(a);
  ok('C: passo 3 senza fasce, con il simulatore dei profili', /vince il profilo con più punti/.test(await a.locator('.sc-corpo').first().innerText()) && await a.locator('#simulatore .ts-sim').count() === 1);
  await a.locator('#ts-profili-carte [data-prof="1"]').click();
  ok('C passo 3: toccato un profilo, il telefono mostra il suo risultato', /Cane/.test(await a.locator('#ts-tel .tsv-h1').innerText()));
  await salvaPasso(a);
  await testoRicco(a, 'Gatto', 'Sei un gatto: calma e divano.', ['Scopri i gatti', '/blog/']);
  await salvaPasso(a);
  ok('C: pubblicato', /pubblicato/i.test(await pubblica(a, Cid)));
  const tel = await pag(br, err, { telefono: true });
  await vai(tel, '/fai-il-test/prova-profili/');
  ok('telefono 390: la pagina del test non scorre di lato', await C.largo(tel) <= 0, await C.largo(tel));
  await tel.click('#tq-inizia');
  ok('telefono: la domanda non scorre di lato, risposte alte almeno 44 px', await C.largo(tel) <= 0 && (await tel.locator('.tq-domanda:not([hidden]) label.tq-opz').first().boundingBox()).height >= 44);
  await foto(tel, 'c-telefono-domanda');
  await rispondi(tel, 'Parco'); await rispondi(tel, 'No'); await rispondi(tel, 'Tranquilla');
  await tel.waitForTimeout(3000);
  await Promise.all([tel.waitForNavigation(), tel.click('#tq-fine button[type=submit]')]);
  // A mano: Parco Cane 2; No Gatto 1; Tranquilla Gatto 1x2 = 2. Gatto 3, Cane 2 -> Gatto (60 e 40).
  ok('C: risultato Gatto (3 punti contro 2), con il testo e il pulsante', (await tel.locator('h1').innerText()).trim() === 'Gatto' && /calma e divano/.test(await tel.locator('.tq-testo').innerText()) && /\/blog\//.test(await tel.locator('.tq-azione a').getAttribute('href')));
  ok('C: il grafico dei profili (Gatto 60, Cane 40)', /Gatto: 60 su 100/.test(await tel.locator('svg.tq-grafico desc').textContent()) && /Cane: 40 su 100/.test(await tel.locator('svg.tq-grafico desc').textContent()));
  ok('telefono: il risultato non scorre di lato', await C.largo(tel) <= 0, await C.largo(tel));
  await foto(tel, 'c-telefono-risultato');

  // =============================================================== D. 2.23.0: persona, due testi, risultato completo per email, tag, gruppi
  const corto = u0 => Object.fromEntries(new URL(u0).searchParams);
  // L'email delle aree di B: il grafico fatto di tabelle (niente SVG), la risposta completa della regola, della fascia e di ogni area.
  const emB = cli('email_corpo', Bid, JSON.stringify({ t: Bid, g: '20261103', o: 88, f: 'alto', a: { [DB_.aree[0].k]: [100, 'alto'], [DB_.aree[1].k]: [78, 'alto'] }, r: DB_.regole[0].k }));
  ok('B: l\'email del risultato completo ha le barre fatte di tabelle (100% e 78%), niente SVG, la regola e la fascia del totale', /width="100%" style="background:#7c3aed/.test(emB[1]) && /width="78%" style="background:#7c3aed/.test(emB[1]) && !/<svg/.test(emB[1])
    && /Corpo e mente al massimo/.test(emB[1]) && /Il totale: Alto/.test(emB[1]) && /Totale alto/.test(emB[1]), emB[1].slice(0, 300));
  const listaP = cli('lista', 'Lettori persona');
  const tagEta = cli('tag_crea', 'Età: 35-44');
  const flTag = cli('salva_flusso', JSON.stringify({ nome: 'Benvenuto 35-44', stato: 'attiva', inneschi: [{ tipo: 'tag', id: 0, tag: [tagEta] }], grafo: { nodi: [nodo(0, 'innesco'), em(1, 'Benvenuto 35-44')], archi: [{ da: 0, uscita: 'poi', a: 1 }] } }));
  ok('D: un\'automazione con l\'innesco «Riceve un tag» (Età: 35-44)', typeof flTag === 'number' && typeof tagEta === 'number', [flTag, tagEta]);
  const Did = await nuovo(a, 'Prova persona', 'punteggio');
  await salvaPasso(a);
  await domanda(a, { testo: 'Persona uno: quanto spesso?' });
  await domanda(a, { testo: 'Persona due: quanto spesso?' });
  ok('D: passo 2 con la finestra «Domande sulla persona» (catalogo pronto, facoltative di partenza)', await a.locator('#ts-persona details.ts-persona-voce').count() >= 20 && await a.isChecked('input[name=persona_obbligatorie][value="0"]'));
  await apriCarta(a, 'ts-fin-persona');
  for (const [k, n] of [['eta', 1], ['occupazione', 2], ['settore', 3]]) {
    await a.locator(`#tsp-${k}-o`).evaluate(el => { el.closest('details').open = true; });
    await a.check(`input[name="persona[${k}][usa]"]`);
    await a.fill(`#tsp-${k}-o`, String(n));
  }
  await a.fill('#tsp-eta-t', 'Quanti anni hai, più o meno?');
  await a.fill('#tsp-occupazione-r', (await a.inputValue('#tsp-occupazione-r')) + '\nInfluencer');
  await fatto(a);
  ok('D: la colonna fissa in fondo («su di te») ha le tre domande sulla persona, e il telefono le mostra nell\'ultima pagina', await a.locator('.ts-col-persona .ts-carta-p').count() === 3
    && (await a.locator('.ts-col-persona').click({ position: { x: 20, y: 15 } }).then(() => telTesto(a))).includes('Quanti anni hai, più o meno?'));
  ok('D: passo 2 salvato', /Passo 2 salvato/.test(await salvaPasso(a, false)));
  const DD = JSON.parse(sql(`SELECT dati FROM {p}test_test WHERE id = ${Did}`)[0].dati);
  const occ = DD.persona.domande[1];
  ok('D: tre domande sulla persona in ordine (Età, Occupazione, Settore), testo cambiato, una risposta in più con la sua chiave', DD.persona.obbligatorie === false && DD.persona.domande.map(q => q.k).join() === 'eta,occupazione,settore'
    && DD.persona.domande[0].testo === 'Quanti anni hai, più o meno?' && occ.opzioni.length === 18 && occ.opzioni[17].t === 'Influencer' && occ.opzioni[17].k === 'o18' && occ.opzioni[6].t === 'Manager' && occ.opzioni[6].k === 'o7'
    && DD.persona.domande[2].opzioni.length >= 60, DD.persona.domande.map(q => [q.k, q.opzioni.length]));
  await vai(a, PAG('test', `&id=${Did}&passo=4`));
  ok('D: passo 4 con due testi per fascia (risposta breve e risposta completa)', await a.locator('fieldset.ts-testo-ris').first().locator('.ql-editor').count() === 2 && /Risposta breve/.test(await a.locator('fieldset.ts-testo-ris').first().textContent()));
  for (const [f, b, c] of [['Basso', 'Breve basso.', 'Completa basso.'], ['Medio', 'Breve medio.', 'Completa medio.'], ['Alto', 'Breve alto.', 'Completa alto: ecco tutto quello che c\'è da sapere.']]) {
    await testoRicco(a, f, b, null, 'breve'); await testoRicco(a, f, c);
  }
  ok('D: le carte dicono che breve e completa ci sono (bollini senza «manca»)', !/manca/.test(await a.locator('button[data-testo]', { hasText: /^\s*Alto/ }).innerText()));
  ok('D: passo 4 salvato', /Passo 4 salvato/.test(await salvaPasso(a, false)));
  const DD4 = JSON.parse(sql(`SELECT dati FROM {p}test_test WHERE id = ${Did}`)[0].dati);
  ok('D: breve e completa salvate per fascia', /Breve alto/.test(DD4.fasce[2].breve) && /Completa alto/.test(DD4.fasce[2].testo), DD4.fasce[2]);
  await vai(a, PAG('test', `&id=${Did}&passo=5`));
  await apriCarta(a, 'ts-fin-newsletter');
  await a.selectOption('#ts-lista', String(listaP));
  await fatto(a);
  await salvaPasso(a, false);
  ok('D: pubblicato', /pubblicato/i.test(await pubblica(a, Did)));
  const vd = await pag(br, err);
  await vai(vd, '/fai-il-test/prova-persona/');
  await vd.click('#tq-inizia');
  await rispondi(vd, 'Sempre'); await rispondi(vd, 'Sempre');
  ok('D: dopo le domande del test arrivano quelle sulla persona: «Domanda 3 di 5», «Su di te», facoltativa, con «Preferisco non rispondere»',
    /Quanti anni hai, più o meno/.test(await visibile(vd)) && /Domanda 3 di 5/.test(await vd.locator('.tq-passo').innerText()) && /Su di te/.test(await vd.locator('.tq-domanda:not([hidden])').innerText())
    && /facoltativa/.test(await vd.locator('.tq-domanda:not([hidden]) legend').innerText()) && await vd.locator('.tq-domanda:not([hidden]) label.tq-opz', { hasText: 'Preferisco non rispondere' }).count() === 1, await visibile(vd));
  await foto(vd, 'd-persona');
  await rispondi(vd, '35-44');
  await rispondi(vd, 'Manager');
  ok('D: il settore ha l\'elenco lungo (su due colonne dove c\'è posto)', await vd.locator('.tq-domanda:not([hidden]) .tq-opzioni-tante').count() === 1);
  await rispondi(vd, 'Preferisco non rispondere');
  await vd.waitForTimeout(3000);
  await Promise.all([vd.waitForNavigation(), vd.click('#tq-fine button[type=submit]')]);
  const urlRis = vd.url();
  ok('D: risultato Alto 100, a schermo la risposta breve e non la completa', (await vd.locator('h1').innerText()).trim() === 'Alto' && (await vd.locator('.tq-punteggio b').innerText()).trim() === '100'
    && /Breve alto/.test(await vd.locator('.tq-breve').innerText()) && !/Completa alto/.test(await vd.content()));
  ok('D: sotto il risultato «Lasciami la tua email e ti mando il risultato completo», con consenso e newsletter', /Lasciami la tua email e ti mando il risultato completo/.test(await vd.locator('#tq-email h2').innerText())
    && await vd.locator('#tq-email input[name=consenso]').count() === 1 && await vd.locator('#tq-email input[name=iscrivi]').count() === 1);
  ok('D: le risposte sulla persona viaggiano firmate con il permesso dell\'email, non nel link da condividere', corto(urlRis).pz === 'eta=o3.occupazione=o7.settore=nr' && !/pz=/.test(await vd.inputValue('#tq-link')), corto(urlRis).pz);
  ok('D: il risultato registrato ha le risposte sulla persona (anonime, con l\'esito)', sql(`SELECT persona FROM {p}test_risultati WHERE test_id = ${Did}`).map(x => x.persona).join() === '{"eta":"o3","occupazione":"o7","settore":"nr"}', sql(`SELECT persona FROM {p}test_risultati WHERE test_id = ${Did}`));
  await foto(vd, 'd-risultato');
  // Senza la newsletter: arriva il risultato completo, nessun contatto, nessun tag.
  let nP = C.posta(POSTA).length;
  let mD = vd.locator('#tq-email form');
  await mD.locator('input[name=email]').fill('solo-risultato@tq-prova.it');
  await mD.locator('input[name=consenso]').check();
  await C.captcha(mD, vd);
  await Promise.all([vd.waitForNavigation(), mD.locator('button[type=submit]').click()]);
  const soloRis = await C.nuove(POSTA, nP, 1);
  const emD = soloRis.find(m => /Il tuo risultato: Prova persona/.test(m.oggetto));
  ok('D senza newsletter: arriva l\'email con la risposta completa (e il punteggio)', !!emD && /Completa alto/.test(emD.html) && /100 su 100/.test(emD.html) && !soloRis.some(m => /conferma/i.test(m.oggetto)), soloRis.map(m => m.oggetto));
  ok('D senza newsletter: nessun contatto creato, nessun tag', sql(`SELECT COUNT(*) n FROM {p}newsletter_iscritti WHERE email = 'solo-risultato@tq-prova.it'`)[0].n == 0 && sql(`SELECT COUNT(*) n FROM {p}newsletter_tag_attese`)[0].n == 0);
  // Con la newsletter: dalla stessa pagina del risultato (il permesso vale due ore).
  await vai(vd, urlRis);
  mD = vd.locator('#tq-email form');
  await mD.locator('input[name=email]').fill('persona@tq-prova.it');
  await mD.locator('input[name=consenso]').check();
  await mD.locator('input[name=iscrivi]').check();
  await C.captcha(mD, vd);
  nP = C.posta(POSTA).length;
  await Promise.all([vd.waitForNavigation(), mD.locator('button[type=submit]').click()]);
  const conNl = await C.nuove(POSTA, nP, 2);
  const confD = conNl.find(m => !/Il tuo risultato/.test(m.oggetto));
  const idP = sql(`SELECT id, stato FROM {p}newsletter_iscritti WHERE email = 'persona@tq-prova.it'`)[0];
  ok('D con newsletter: risultato completo e email di conferma; il contatto aspetta la conferma, ancora senza tag', conNl.some(m => /Completa alto/.test(m.html)) && !!confD && idP && idP.stato === 'in_attesa' && cli('tag_di', 'persona@tq-prova.it').length === 0
    && sql(`SELECT COUNT(*) n FROM {p}newsletter_tag_attese WHERE iscritto_id = ${idP.id}`)[0].n == 3, conNl.map(m => m.oggetto));
  await vai(w, C.link(confD).find(l => /conferma/.test(l)));
  const tagP = cli('tag_di', 'persona@tq-prova.it');
  ok('D confermato: i tag «Età: 35-44», «Occupazione: Manager» e l\'esito «Test Prova persona: Alto»; «Preferisco non rispondere» non diventa un tag', tagP.join('|') === 'Età: 35-44|Occupazione: Manager|Test Prova persona: Alto', tagP);
  ok('D confermato: il contatto è nella lista scelta nel test, la cronologia dice i tag aggiunti', sql(`SELECT COUNT(*) n FROM {p}newsletter_iscritti_liste WHERE iscritto_id = ${idP.id} AND lista_id = ${listaP}`)[0].n == 1
    && sql(`SELECT COUNT(*) n FROM {p}newsletter_storia WHERE contatto_id = ${idP.id} AND tipo = 'tag_aggiunto'`)[0].n == 3);
  const corsiP = sql(`SELECT flusso_id FROM {p}newsletter_corsi WHERE iscritto_id = ${idP.id}`).map(x => +x.flusso_id);
  ok('D confermato: parte l\'automazione «Riceve un tag» (Età: 35-44)', corsiP.includes(flTag), corsiP);
  nP = C.posta(POSTA).length;
  cli('giro'); cli('giro');
  ok('D: il giro manda l\'email dell\'automazione «Benvenuto 35-44»', (await C.nuove(POSTA, nP, 1)).some(m => /Benvenuto 35-44/.test(m.oggetto)));
  // Senza «solo per email»: a schermo la breve e sotto la completa (il link condiviso).
  await vai(a, PAG('test', `&id=${Did}&passo=5`));
  await apriCarta(a, 'ts-fin-email');
  await a.uncheck('input[name=solo_email]'); await a.uncheck('input[name=email]');
  await fatto(a);
  await salvaPasso(a, false);
  const linkD = (() => { const x = new URL(urlRis); return B + '/fai-il-test/prova-persona/risultato/?e=' + x.searchParams.get('e') + '&f=' + x.searchParams.get('f'); })();
  await vai(w, linkD);
  ok('D senza «solo per email»: a schermo la breve e sotto la completa', /Breve alto/.test(await w.locator('.tq-breve').innerText()) && /Completa alto/.test(await w.locator('.tq-testo').nth(1).innerText()) && await w.locator('#tq-email').count() === 0);
  // Obbligatorie (senza JavaScript): niente "Preferisco non rispondere", la risposta manca o è inventata: 422 con l'errore sotto.
  await vai(a, PAG('test', `&id=${Did}&passo=2`));
  await apriCarta(a, 'ts-fin-persona');
  await a.check('input[name=persona_obbligatorie][value="1"]');
  await fatto(a);
  await salvaPasso(a, false);
  const njD = await (await br.newContext({ javaScriptEnabled: false })).newPage();
  await njD.goto(B + '/fai-il-test/prova-persona/');
  ok('D obbligatorie, senza JavaScript: le tre domande sulla persona in fondo, senza «Preferisco non rispondere», la prima risposta required',
    await njD.locator('.tq-persona').count() === 3 && !/Preferisco non rispondere/.test(await njD.content()) && await njD.locator('.tq-persona').first().locator('input[required]').count() === 1
    && await njD.locator('.tq-domanda').last().getAttribute('data-k') === 'p-settore');
  const dk = DD.domande.map(q => q.k);
  const invia = async (persona) => { const tk = await (await njD.goto(B + '/fai-il-test/prova-persona/')).text().then(h => (h.match(/name="gettone" value="([^"]+)"/) || [])[1]); await njD.waitForTimeout(3200);
    return njD.request.post(B + '/fai-il-test/prova-persona/invia/', { form: Object.assign({ gettone: tk, [`r[${dk[0]}]`]: 's2', [`r[${dk[1]}]`]: 's2' }, persona), maxRedirects: 0 }); };
  let rq = await invia({ 'p[eta]': 'o2', 'p[occupazione]': 'o5' });
  let hq = await rq.text();
  ok('D obbligatorie: manca il settore: 422, errore sotto la domanda sulla persona, le risposte date restano', rq.status() === 422 && /class="tq-domanda tq-persona errato" id="tp-settore"/.test(hq) && /Manca la risposta/.test(hq) && /name="p\[eta\]" value="o2" checked/.test(hq), rq.status());
  rq = await invia({ 'p[eta]': 'zz9', 'p[occupazione]': 'o5', 'p[settore]': 'o1' });
  ok('D: una risposta sulla persona inventata è rifiutata (422, «non va bene»)', rq.status() === 422 && /Questa risposta non va bene/.test(await rq.text()));
  rq = await invia({ 'p[eta]': 'nr', 'p[occupazione]': 'o5', 'p[settore]': 'o1' });
  ok('D obbligatorie: «Preferisco non rispondere» mandato a mano è rifiutato', rq.status() === 422);
  await vai(a, PAG('test', `&id=${Did}&passo=2`));
  await apriCarta(a, 'ts-fin-persona');
  await a.check('input[name=persona_obbligatorie][value="0"]');
  await fatto(a);
  await salvaPasso(a, false);
  rq = await invia({});
  ok('D facoltative: senza nessuna risposta sulla persona il test va al risultato (303)', rq.status() === 303 && /\/risultato\//.test(rq.headers()['location'] || ''), rq.status());
  // Statistiche per gruppi: 14 risultati (2 veri, 12 finti). 25-34: 6; 35-44: 4 + 1 vero = 5; 45-54: 2.
  cli('risultati_finti', Did, JSON.stringify([...Array(6)].map(() => ({ totale: 80, fascia: 'alto', persona: { eta: 'o2', occupazione: 'o5', settore: 'o3' } }))
    .concat([...Array(4)].map((x, i) => ({ totale: i < 2 ? 20 : 50, fascia: i < 2 ? 'basso' : 'medio', persona: { eta: 'o3', occupazione: 'o5', settore: 'o3' } })))
    .concat([...Array(2)].map(() => ({ totale: 50, fascia: 'medio', persona: { eta: 'o4', occupazione: 'o1', settore: 'o3' } })))));
  const gr = cli('gruppi', Did);
  const riga = (q, t) => gr.domande.find(x => x.k === q).righe.find(x => x.t === t);
  ok('gruppi: 14 risultati; 25-34 mostrato (6, Alto 100%, media 80)', gr.totale === 14 && riga('eta', '25-34').n === 6 && riga('eta', '25-34').dist.alto === 100 && riga('eta', '25-34').media === 80, riga('eta', '25-34'));
  ok('gruppi: 45-54 (2 persone) «meno di 5»; 35-44 (5 persone) nascosto anche lui, se no per differenza dal totale resterebbero 3 persone', riga('eta', '45-54').nascosto && riga('eta', '45-54').perche === 'pochi' && riga('eta', '45-54').n === null
    && riga('eta', '35-44').nascosto && riga('eta', '35-44').perche === 'resto' && riga('eta', '35-44').n === null && riga('eta', '18-24').n === 0, gr.domande[0].righe.map(x => [x.t, x.n, x.perche]));
  ok('gruppi: «Preferisco non rispondere» è un gruppo come gli altri (1 persona: meno di 5)', riga('settore', 'Preferisco non rispondere').nascosto && riga('settore', 'Preferisco non rispondere').perche === 'pochi');
  await vai(a, PAG('statistiche', `&id=${Did}`));
  const tg = await a.locator('#ts-gruppi').innerText();
  ok('statistiche: la tabella «Per gruppi» per ogni domanda sulla persona, con «meno di 5» e «non mostrato»', await a.locator('#ts-gruppi table').count() === 3 && /meno di 5/.test(tg) && /non mostrato/.test(tg)
    && /25-34\s+6\s+0%\s+0%\s+100%\s+80/.test(tg), tg.slice(0, 400));
  await vai(a, PAG('statistiche', `&id=${Did}&giorni=7`));
  ok('statistiche: i gruppi non cambiano con il periodo (sempre su tutti: due periodi non isolano nessuno)', (await a.locator('#ts-gruppi').innerText()) === tg);
  await foto(a, 'd-gruppi');
  const csvD = await (await a.request.get(PAG('statistiche', `&id=${Did}&csv=1`))).text();
  ok('CSV: i gruppi con la stessa soglia (6 persone, meno di 5, non mostrato), nessuna email', /"?Per gruppi: Età"?;"?25-34 - persone"?;6/.test(csvD) && /"?Per gruppi: Età"?;"?45-54 - persone"?;"?meno di 5"?/.test(csvD) && /"?Per gruppi: Età"?;"?35-44 - persone"?;"?non mostrato"?/.test(csvD)
    && !/35-44 - fascia/.test(csvD) && /25-34 - fascia Alto \(%\)"?;100/.test(csvD) && !/@/.test(csvD), csvD.split('\n').filter(x => /Per gruppi/.test(x)).slice(0, 6));
  // Un test scritto con la 2.22.0 (un solo testo, niente persona, niente «solo per email»): pagina e risultato uguali al byte.
  const vecchio = cli('crea_vecchio', JSON.stringify({ slug: 'prova-vecchio', nome: 'Prova vecchio', tipo: 'punteggio', dati: {
    scala: [{ t: 'Mai', v: 0 }, { t: 'Raramente', v: 1 }, { t: 'A volte', v: 2 }, { t: 'Spesso', v: 3 }, { t: 'Sempre', v: 4 }], aree: [], profili: [],
    domande: [{ k: 'dvecchia', testo: 'Vecchia domanda?', aiuto: '', tipo: 'scala', invertita: false, peso: 1, obbligatoria: true, area: '', opzioni: [], condizione: null }],
    fasce: [{ k: 'basso', nome: 'Basso', da: 0, a: 50, testo: '<p>Testo vecchio basso.</p>', pulsante_testo: '', pulsante_link: '' }, { k: 'alto', nome: 'Alto', da: 51, a: 100, testo: '<p>Testo vecchio alto.</p>', pulsante_testo: 'Scrivimi', pulsante_link: '/contatti/' }],
    regole: [], avvertenza: 'Avvertenza vecchia.', casuale: false, presentazione: '', dopo: { email: true, consenso: 'Consenso vecchio.', lista: listaP, iscrizione: 'Iscriviti.' } } }));
  const vecchiaCartella = path.join(CMS, 'addon', 'test-vecchio');
  let oldOk = false;
  try { execFileSync('bash', ['-c', `rm -rf "${vecchiaCartella}" && mkdir -p "${vecchiaCartella}" && git -C "${path.join(__dirname, '..')}" archive c3b99e9 cms/addon/test | tar -x -C "${vecchiaCartella}" --strip-components=3`]); oldOk = true; } catch (x) { console.log('     (manca il commit c3b99e9: il confronto con la 2.22.0 si salta)'); }
  if (oldOk) {
    const esV = { t: vecchio, g: '20261103', o: 75, f: 'alto' };
    const nuovaV = cli('vista', vecchio, JSON.stringify(esV), '9999999999.' + 'a'.repeat(32));
    const vecchiaV = JSON.parse(execFileSync('php', [path.join(__dirname, 'test-cli.php'), CMS, 'vista', String(vecchio), JSON.stringify(esV), '9999999999.' + 'a'.repeat(32)], { encoding: 'utf8', env: Object.assign({}, process.env, { TEST_ADDON: vecchiaCartella }) }).trim().split('\n').pop());
    ok('test della 2.22.0: la pagina con le domande è uguale al byte a quella della 2.22.0', nuovaV.modulo === vecchiaV.modulo && nuovaV.modulo.length > 500, [nuovaV.modulo.length, vecchiaV.modulo.length]);
    ok('test della 2.22.0: la pagina del risultato (testo unico a schermo, riquadro dell\'email) è uguale al byte', nuovaV.risultato === vecchiaV.risultato && /Testo vecchio alto/.test(nuovaV.risultato) && /Vuoi ricevere il risultato per email/.test(nuovaV.risultato), [nuovaV.risultato.length, vecchiaV.risultato.length]);
    ok('test della 2.22.0: l\'email ha lo stesso testo (ora con il formato del risultato completo)', /Testo vecchio alto/.test(nuovaV.email[1]) && /75 su 100/.test(nuovaV.email[1]) && nuovaV.email[0] === vecchiaV.email[0]);
    execFileSync('rm', ['-rf', vecchiaCartella]);
  }
  await vai(w, '/fai-il-test/prova-vecchio/');
  ok('test della 2.22.0 sul sito: nessuna domanda sulla persona', w.url().includes('prova-vecchio') && await w.locator('.tq-persona').count() === 0 && await w.locator('.tq-domanda').count() === 1);

  // =============================================================== E. 2.24.0: come si mostrano le domande (una per pagina, a pagine, tutte), la lavagna
  const Eid = await nuovo(a, 'Prova pagine', 'punteggio');
  await salvaPasso(a);
  await domanda(a, { testo: 'E uno: dormi bene?' });
  await domanda(a, { testo: 'E due: pranzi seduto?' });
  await domanda(a, { testo: 'E tre: bevi caffè la sera?', tipo: 'sino', opzioni: [['Sì', 2], ['No', 0]] });
  await domanda(a, { testo: 'E quattro: quante tazze?', tipo: 'scelta', opzioni: [['Una', 1], ['Tante', 3]], cond: [3, ['Sì']] });
  await domanda(a, { testo: 'E cinque: da 0 a 10?', tipo: 'cursore' });
  await apriCarta(a, 'ts-fin-persona');
  await a.locator('#tsp-eta-o').evaluate(el => { el.closest('details').open = true; });
  await a.check('input[name="persona[eta][usa]"]');
  await fatto(a);
  ok('E: «Una per pagina» di partenza: cinque colonne, una carta ciascuna, più la colonna fissa «su di te»', await a.getAttribute('[data-modo=una]', 'aria-pressed') === 'true' && await a.locator('.ts-col[data-pag]').count() === 5 && await a.locator('.ts-col[data-pag="2"] .ts-carta-d').count() === 1);
  await a.click('[data-modo=tutte]');
  ok('E: «Tutte in una pagina»: una colonna con le cinque carte; il telefono «Pagina 1 di 2» con le cinque domande', await a.locator('.ts-col[data-pag]').count() === 1 && await a.locator('.ts-col[data-pag="0"] .ts-carta-d').count() === 5
    && /Pagina 1 di 2/.test(await telTesto(a)) && await a.locator('#ts-tel .tsv-dom').count() === 5);
  await a.click('[data-modo=pagine]');
  ok('E: «A pagine, le scegli tu»: la colonna, il pulsante «Titolo» e il riquadro tratteggiato «+ Pagina nuova»', await a.locator('.ts-col[data-pag]').count() === 1 && await a.locator('[data-titolo-pag="0"]').count() === 1 && /Pagina nuova/.test(await a.locator('[data-nuova]').innerText()));
  const carta = t => a.locator('#ts-lavagna .ts-carta-d', { hasText: t });
  await trascina(a, carta('E quattro'), a.locator('[data-nuova]'));
  ok('E: trascinata con il mouse nel riquadro tratteggiato, la carta fa una pagina nuova', await a.locator('.ts-col[data-pag]').count() === 2 && await a.locator('.ts-col[data-pag="1"] .ts-carta-d', { hasText: 'E quattro' }).count() === 1
    && /pagina 2 \(nuova\)/.test(await a.locator('#ts-annuncio').innerText()), await a.locator('#ts-annuncio').innerText());
  await a.click('[data-piu-pag]');
  ok('E: «+ Pagina nuova» aggiunge una colonna vuota che lo dice', await a.locator('.ts-col[data-pag]').count() === 3 && /Pagina vuota/.test(await a.locator('.ts-col[data-pag="2"]').innerText()));
  await trascina(a, carta('E cinque'), a.locator('.ts-col[data-pag="2"] .ts-col-lista'));
  ok('E: trascinata con il mouse da una colonna all\'altra (pagina 1 -> pagina 3)', await a.locator('.ts-col[data-pag="2"] .ts-carta-d', { hasText: 'E cinque' }).count() === 1 && await a.locator('.ts-col[data-pag="0"] .ts-carta-d').count() === 3);
  // Senza trascinare: la tendina «Pagina» nella scheda (con la tastiera: Invio sulla carta apre la scheda, Esc la chiude).
  await carta('E due').focus();
  await a.keyboard.press('Enter');
  ok('E: Invio sulla carta apre la scheda della domanda (finestra del pannello), con la tendina «Pagina»', await a.locator('#ts-fin-domanda[open]').count() === 1 && await a.locator('#tsq-pag option').count() === 4);
  await a.selectOption('#tsq-pag', '2');
  ok('E: con la tendina «Pagina» la domanda passa alla pagina 3, la condizione della quattro resta', await a.locator('.ts-col[data-pag="2"] .ts-carta-d', { hasText: 'E due' }).count() === 1 && /pagina 3/.test(await a.locator('#ts-annuncio').innerText()) && !/Tolta/.test(await a.locator('#ts-annuncio').innerText()));
  // L'anteprima che si aggiorna mentre scrivi.
  await a.fill('#tsq-testo', 'E due: pranzi seduto davvero?');
  ok('E: mentre scrivi, la carta e il telefono si aggiornano', /pranzi seduto davvero/.test(await carta('E due').innerText()) && /pranzi seduto davvero/.test(await telTesto(a)));
  await a.keyboard.press('Escape');
  await a.waitForTimeout(150); // l'evento close del dialog arriva dopo
  ok('E: Esc chiude la scheda e il fuoco torna alla carta', await a.locator('#ts-fin-domanda[open]').count() === 0 && await a.evaluate(() => !!document.activeElement.closest('.ts-carta-d') && /E due/.test(document.activeElement.textContent)));
  // Il titolo delle pagine.
  await a.click('[data-titolo-pag="0"]');
  await a.fill('#ts-pag-titolo', 'Come inizi la giornata');
  ok('E: il titolo della pagina compare nella colonna e nel telefono mentre lo scrivi', /Come inizi la giornata/.test(await a.locator('.ts-col[data-pag="0"] .ts-col-titolo').innerText()) && /Come inizi la giornata/.test(await a.locator('#ts-tel .tsv-titolo').innerText()));
  ok('E: una pagina con domande non si toglie (il pulsante è spento)', await a.locator('[data-togli-pag-fin]').isDisabled());
  await fatto(a);
  await a.click('[data-titolo-pag="1"]'); await a.fill('#ts-pag-titolo', 'La sera'); await fatto(a);
  await a.click('[data-vai="1"]');
  ok('E: toccata la colonna 2, il telefono va alla pagina 2 (la condizionale dice quando compare)', /Pagina 2 di 4/.test(await telTesto(a)) && /La sera/.test(await telTesto(a)) && /Compare solo se alla domanda 2 rispondi «Sì»/.test(await telTesto(a)), await telTesto(a));
  await a.locator('#ts-tel .tsv-dom').first().click();
  ok('E: toccata la domanda nel telefono, si apre la sua scheda', await a.locator('#ts-fin-domanda[open]').count() === 1 && await a.inputValue('#tsq-testo') === 'E quattro: quante tazze?');
  await a.keyboard.press('Escape');
  ok('E: passo 2 salvato', /Passo 2 salvato/.test(await salvaPasso(a, false)));
  const DE = JSON.parse(sql(`SELECT dati FROM {p}test_test WHERE id = ${Eid}`)[0].dati);
  const testi = ks => ks.map(k => DE.domande.find(q => q.k === k).testo.split(':')[0]).join(',');
  ok('E: salvati il modo «pagine», tre pagine con i titoli, le domande nell\'ordine delle pagine, la condizione al suo posto', DE.modo === 'pagine' && DE.pagine.length === 3
    && DE.pagine[0].titolo === 'Come inizi la giornata' && DE.pagine[1].titolo === 'La sera' && DE.pagine[2].titolo === ''
    && testi(DE.pagine[0].domande) === 'E uno,E tre' && testi(DE.pagine[1].domande) === 'E quattro' && testi(DE.pagine[2].domande) === 'E cinque,E due'
    && DE.domande.map(q => q.testo.split(':')[0]).join(',') === 'E uno,E tre,E quattro,E cinque,E due' && DE.domande[2].condizione && DE.domande[2].condizione.domanda === DE.domande[1].k, JSON.stringify(DE.pagine));
  await vai(a, PAG('test', `&id=${Eid}&passo=2`));
  ok('E: riaperto, la lavagna ha le tre pagine con i titoli', await a.getAttribute('[data-modo=pagine]', 'aria-pressed') === 'true' && await a.locator('.ts-col[data-pag]').count() === 3 && /La sera/.test(await a.locator('.ts-col[data-pag="1"]').innerText()));
  await foto(a, 'e-lavagna');
  await apriDomanda(a, 3);
  await foto(a, 'e-scheda');
  await a.keyboard.press('Escape');
  // Tutti i passi per tutti e tre i tipi hanno carte, finestre e telefono; l'anteprima del passo 1 cambia mentre scrivi.
  for (const [id, tipo] of [[A, 'punteggio'], [Bid, 'aree'], [Cid, 'profili']]) {
    let tutti = true; const dove = [];
    for (const n of [1, 2, 3, 4, 5]) {
      await vai(a, PAG('test', `&id=${id}&passo=${n}`));
      const carte = await a.locator('.ts-banco [data-apri], .ts-banco .ts-carta-d').count();
      const corpo = (await a.locator('#ts-tel .tsv-corpo').innerText().catch(() => '')).trim();
      const primo = a.locator('.ts-banco [data-apri], .ts-banco .ts-carta-d').first();
      await primo.click();
      const senzaFinestra = n === 3 && tipo === 'profili'; // i profili nel passo 3 si toccano solo per vederli nel telefono
      const aperta = senzaFinestra ? 1 : await a.locator('dialog.ts-finestra[open]').count();
      const ruolo = senzaFinestra ? true : aperta ? await a.locator('dialog.ts-finestra[open]').evaluate(d => d.tagName === 'DIALOG' && !!d.querySelector('.ts-fin-testa h2')) : false;
      await a.keyboard.press('Escape');
      const chiusa = await a.locator('dialog.ts-finestra[open]').count() === 0;
      if (!(carte >= 1 && corpo.length > 10 && aperta === 1 && ruolo && chiusa)) { tutti = false; dove.push(n + ':' + [carte, corpo.length, aperta, ruolo, chiusa].join('/')); }
    }
    ok(`tutti e cinque i passi del test ${tipo}: carte, una finestra che si apre (titolo in fascia) e si chiude con Esc, il telefono con l'anteprima`, tutti, dove);
  }
  await vai(a, PAG('test', `&id=${Cid}&passo=1`));
  await apriCarta(a, 'ts-fin-nome');
  await a.fill('#ts-nome', 'Prova profili dal vivo');
  ok('passo 1: il nome cambia nel telefono e nella carta mentre scrivi', /Prova profili dal vivo/.test(await a.locator('#ts-tel .tsv-h1').innerText()) && /Prova profili dal vivo/.test(await a.locator('[data-apri="ts-fin-nome"]').innerText()));
  await a.keyboard.press('Escape');
  // Il pulsante «Anteprima» al telefono (390 px): il telefono a schermo intero, Esc lo chiude; la pagina non scorre di lato.
  const at2 = await pag(br, err, { telefono: true });
  await C.entra(at2, B);
  await vai(at2, PAG('test', `&id=${Eid}&passo=2`));
  ok('390 px: il telefono dell\'anteprima è nascosto, c\'è il pulsante «Anteprima» (44 px), la pagina non scorre di lato', !(await at2.locator('#ts-lato').isVisible()) && await at2.locator('[data-ts-anteprima]').isVisible()
    && (await at2.locator('[data-ts-anteprima]').boundingBox()).height >= 44 && await C.largo(at2) <= 0, await C.largo(at2));
  await at2.click('[data-ts-anteprima]');
  const bx = await at2.locator('#ts-lato').boundingBox();
  ok('390 px: «Anteprima» apre il telefono a schermo intero (finestra), con «Chiudi l\'anteprima» a fuoco', await at2.locator('#ts-lato').isVisible() && bx.width >= 380 && await at2.getAttribute('#ts-lato', 'role') === 'dialog'
    && await at2.evaluate(() => document.activeElement.hasAttribute('data-ts-anteprima-chiudi')) && /Pagina 1 di 4/.test(await telTesto(at2)), bx);
  await foto(at2, 'e-anteprima-telefono');
  await at2.keyboard.press('Escape');
  ok('390 px: Esc chiude l\'anteprima', !(await at2.locator('#ts-lato').isVisible()));
  await at2.locator('#ts-lavagna .ts-carta-d').first().click();
  const bd = await at2.locator('#ts-fin-domanda').boundingBox();
  ok('390 px: la scheda della domanda sta nello schermo, con i campi in una colonna', bd.width <= 390 && bd.x >= 0 && await C.largo(at2) <= 0);
  await at2.keyboard.press('Escape');
  for (const n of [1, 3, 4, 5]) { await vai(at2, PAG('test', `&id=${Eid}&passo=${n}`)); ok(`390 px, passo ${n}: niente scorrimento di lato`, await C.largo(at2) <= 0, await C.largo(at2)); }
  await vai(a, PAG('test', `&id=${Eid}&passo=5`));
  await pubblica(a, Eid);
  // Il sito a pagine, con JavaScript.
  const vp = await pag(br, err);
  await vai(vp, '/fai-il-test/prova-pagine/');
  await vp.click('#tq-inizia');
  const vis = async () => (await vp.locator('.tq-domanda:not([hidden]) .tq-testo-domanda').allInnerTexts()).map(x => x.split(':')[0]).join(',');
  ok('sito a pagine: «Pagina 1 di 3» (la pagina 2 ha solo la condizionale: si salta), il titolo della pagina, le due domande', /Pagina 1 di 3/.test(await vp.locator('.tq-passo').innerText()) && await vis() === 'E uno,E tre'
    && (await vp.locator('.tq-pagina-titolo:not([hidden])').allInnerTexts()).join() === 'Come inizi la giornata' && await vp.getAttribute('.tq-barra[role=progressbar]', 'aria-valuetext') === 'Pagina 1 di 3');
  await vp.click('[data-avanti]');
  ok('sito a pagine: Avanti senza risposte: un errore sotto ognuna delle due domande obbligatorie', await vp.locator('.tq-domanda:not([hidden]) .tq-manca[role=alert]').count() === 2);
  await rispondi(vp, 'Spesso', false);
  await vp.locator('.tq-domanda:not([hidden])').nth(1).locator('label.tq-opz', { hasText: 'Sì' }).click();
  ok('sito a pagine: con «Sì» la condizionale entra, la sua pagina conta: «Pagina 1 di 4»', /Pagina 1 di 4/.test(await vp.locator('.tq-passo').innerText()));
  await vp.click('[data-avanti]');
  ok('sito a pagine: pagina 2 «La sera» con la condizionale', /Pagina 2 di 4/.test(await vp.locator('.tq-passo').innerText()) && await vis() === 'E quattro' && /La sera/.test(await vp.locator('.tq-pagina-titolo:not([hidden])').innerText()));
  await vp.click('[data-indietro]');
  ok('sito a pagine: Indietro torna alla pagina 1 con le risposte tenute', await vis() === 'E uno,E tre' && await vp.locator('.tq-domanda:not([hidden]) input[value=s3]').isChecked());
  await vp.locator('.tq-domanda:not([hidden])').nth(1).locator('label.tq-opz', { hasText: 'No' }).click();
  await vp.click('[data-avanti]');
  ok('sito a pagine: con «No» la pagina della condizionale si salta: «Pagina 2 di 3», senza titolo', /Pagina 2 di 3/.test(await vp.locator('.tq-passo').innerText()) && await vis() === 'E cinque,E due' && await vp.locator('.tq-pagina-titolo:not([hidden])').count() === 0);
  await vp.locator('.tq-domanda:not([hidden]) input[type=range]').fill('6');
  await vp.locator('.tq-domanda:not([hidden])').nth(1).locator('label.tq-opz', { hasText: 'Sempre' }).click();
  await foto(vp, 'e-sito-pagina');
  await vp.click('[data-avanti]');
  ok('sito a pagine: l\'ultima è la pagina sulla persona, con «Vedi il risultato» e senza Avanti', /Pagina 3 di 3/.test(await vp.locator('.tq-passo').innerText()) && await vp.locator('.tq-domanda.tq-persona:not([hidden])').count() === 1
    && await vp.locator('#tq-fine button[type=submit]').isVisible() && !(await vp.locator('[data-avanti]').isVisible()));
  await vp.waitForTimeout(3000);
  await Promise.all([vp.waitForNavigation(), vp.click('#tq-fine button[type=submit]')]);
  // A mano: Spesso 3 + No 0 + 6 + Sempre 4 = 13 su 4 + 2 + 10 + 4 = 20 (la condizionale non mostrata non conta) -> 65, Medio.
  ok('sito a pagine: risultato 65, Medio (13 punti su 20, la condizionale nascosta non conta)', /\/risultato\//.test(vp.url()) && (await vp.locator('.tq-punteggio b').innerText()).trim() === '65', vp.url());
  // Senza JavaScript: tutto in una pagina, con i titoli delle pagine come intestazioni.
  const njE = await (await br.newContext({ javaScriptEnabled: false })).newPage();
  await njE.goto(B + '/fai-il-test/prova-pagine/');
  ok('senza JavaScript: tutte le domande in una pagina, i titoli delle pagine come intestazioni (h2) prima delle loro domande', await njE.locator('.tq-domanda').count() === 6 && await njE.locator('#tq-app').isVisible()
    && (await njE.locator('h2.tq-pagina-titolo').allInnerTexts()).join('|') === 'Come inizi la giornata|La sera'
    && await njE.locator('h2.tq-pagina-titolo + fieldset.tq-domanda .tq-testo-domanda').first().innerText().then(t => /E uno/.test(t)));
  await foto(njE, 'e-senza-js');
  // «Tutte in una pagina» sul sito: una pagina con le domande (la condizionale entra ed esce lì), poi quella sulla persona.
  await vai(a, PAG('test', `&id=${Eid}&passo=2`));
  await a.click('[data-modo=tutte]');
  await salvaPasso(a, false);
  await vai(vp, '/fai-il-test/prova-pagine/');
  if (await vp.locator('#tq-riprendi').count()) await vp.click('#tq-riprendi [data-ricomincia]'); else await vp.click('#tq-inizia');
  ok('sito «Tutte in una pagina»: «Pagina 1 di 2», quattro domande (la condizionale nascosta), nessun titolo', /Pagina 1 di 2/.test(await vp.locator('.tq-passo').innerText()) && await vp.locator('.tq-domanda:not([hidden])').count() === 4 && await vp.locator('.tq-pagina-titolo').count() === 0);
  await vp.locator('.tq-domanda:not([hidden])').nth(1).locator('label.tq-opz', { hasText: 'Sì' }).click();
  ok('sito «Tutte in una pagina»: con «Sì» la condizionale compare nella stessa pagina, dopo la sua', await vp.locator('.tq-domanda:not([hidden])').count() === 5 && /E quattro/.test(await vp.locator('.tq-domanda:not([hidden]) .tq-testo-domanda').nth(2).innerText()));
  // Un test scritto prima della 2.24.0 (niente modo): una domanda per schermata, come sempre.
  await vai(vp, '/fai-il-test/prova-vecchio/');
  await vp.click('#tq-inizia');
  ok('test senza modo (prima della 2.24.0): una domanda per schermata, «Domanda 1 di 1», niente data-modo', /Domanda 1 di 1/.test(await vp.locator('.tq-passo').innerText()) && await vp.locator('#tq-modulo[data-modo]').count() === 0);

  // =============================================================== Statistiche e CSV
  const abb = await pag(br, err);
  await vai(abb, '/fai-il-test/prova-punteggio/');
  await abb.click('#tq-inizia'); await rispondi(abb, 'Mai');
  await abb.waitForTimeout(800);
  await vai(a, PAG('statistiche', `&id=${A}`));
  const numeri = await a.locator('.ts-numeri').innerText();
  ok('statistiche A: 3 iniziati, 2 completati', /3\s*iniziati/.test(numeri) && /2\s*completati/.test(numeri), numeri);
  ok('statistiche A: uno si è fermato alla domanda 2', /^\s*1\s*$/.test(await a.locator('.ts-tabella-dom tbody tr').nth(1).locator('td').nth(1).innerText()));
  ok('statistiche A: risultati Alto 1 e Medio 1', /Alto\s*1/.test(await a.locator('.ts-barre').first().innerText()) && /Medio\s*1/.test(await a.locator('.ts-barre').first().innerText()));
  await foto(a, 'a-statistiche');
  const csv = await (await a.request.get(PAG('statistiche', `&id=${A}&csv=1`))).text();
  ok('CSV aggregato: completati, fasce, risposte, nessuna email', /Riepilogo;Completati;2/.test(csv) && /Risultati;"?Totale: Alto"?;1/.test(csv) && /Risposte;/.test(csv) && !/@/.test(csv), csv.slice(0, 200));
  await vai(a, PAG('statistiche', `&id=${Bid}`));
  ok('statistiche B: media per area', /Corpo/.test(await a.locator('.sc-scatola', { hasText: 'Media per area' }).innerText()) && /100/.test(await a.locator('.sc-scatola', { hasText: 'Media per area' }).innerText()));

  // =============================================================== Il widget Riquadro in evidenza
  const pr = cli('pagina_riquadro', 'riquadro-test', A, '/contatti/');
  const pr2 = cli('pagina_riquadro', 'riquadro-link', 0, '/contatti/');
  ok('Riquadro in evidenza: con l\'addon acceso c\'è la scelta del test (salvata)', pr.test === String(A), pr);
  await vai(w, '/riquadro-test/');
  ok('Riquadro in evidenza con il test scelto: il pulsante porta al test', /\/fai-il-test\/prova-punteggio\/$/.test(await w.locator('a.ed-test-pulsante').getAttribute('href')));
  await vai(w, '/riquadro-link/');
  ok('Riquadro in evidenza senza test: resta il link di prima', /\/contatti\/$/.test(await w.locator('a.ed-test-pulsante').getAttribute('href')));
  await vai(a, B + '/admin/editor.php?tipo=pagine&id=' + pr.id);
  ok('nell\'editor il Riquadro ha la tendina "Oppure porta a un test"', /Oppure porta a un test/.test(await a.content()));

  // =============================================================== Duplica, Elimina, bozza
  await vai(a, PAG('test'));
  ok('elenco: sei test con tipo, stato e completati (con quelli della 2.23.0 e 2.24.0)', await a.locator('.ts-tabella tbody tr').count() === 6);
  await a.locator('tr', { hasText: 'Prova profili' }).hover(); // le azioni della riga compaiono al passaggio (come in Contenuti)
  await Promise.all([a.waitForNavigation(), a.locator('tr', { hasText: 'Prova profili' }).locator('button[value=duplica]').click()]);
  ok('Duplica: la copia nasce in bozza', /copia/.test(await flash(a)) && /Prova profili \(copia\)/.test(await a.locator('.fo-sopra').innerText()));
  const copia = +new URL(a.url()).searchParams.get('id');
  ok('la copia in bozza non si apre sul sito (404) ma sì nell\'anteprima dell\'amministratore', (await w.goto(B + '/fai-il-test/prova-profili-copia/')).status() === 404 && (await a.goto(B + '/fai-il-test/prova-profili-copia/?preview=1')).status() === 200);
  await vai(a, PAG('test'));
  await a.locator('tr', { hasText: 'Prova profili (copia)' }).hover();
  await Promise.all([a.waitForNavigation(), a.locator('tr', { hasText: 'Prova profili (copia)' }).locator('button.ts-elimina').click()]);
  ok('Elimina con la conferma del pannello', /eliminato/.test(await flash(a)) && sql(`SELECT COUNT(*) n FROM {p}test_test WHERE id = ${copia}`)[0].n == 0);
  const at = await pag(br, err, { telefono: true });
  await C.entra(at, B);
  for (const [u, n] of [[PAG('test'), 'elenco'], [PAG('test', `&id=${A}&passo=2`), 'passo 2'], [PAG('test', `&id=${A}&passo=3`), 'passo 3'], [PAG('test', `&id=${A}&passo=4`), 'passo 4'], [PAG('statistiche', `&id=${A}`), 'statistiche']]) {
    await vai(at, u);
    ok('pannello a 390 px, ' + n + ': niente scorrimento di lato', await C.largo(at) <= 0, await C.largo(at));
  }
  await foto(at, 'pannello-telefono-statistiche');

  // Spento l'addon: le pagine dei test non rispondono più, il pannello dà 404, i dati restano.
  ok('spengo l\'addon', /spento/.test(await C.addon(a, B, 'test', false)));
  ok('spento: /fai-il-test/prova-punteggio/ dà 404 e il pannello dell\'addon 404', (await w.goto(B + '/fai-il-test/prova-punteggio/')).status() === 404 && (await a.goto(PAG('test'))).status() === 404);
  await vai(w, '/riquadro-test/');
  ok('spento: il Riquadro torna al link scritto a mano', /\/contatti\/$/.test(await w.locator('a.ed-test-pulsante').getAttribute('href')));
  ok('spento: i dati restano', sql(`SELECT COUNT(*) n FROM {p}test_test`)[0].n == 6);
  ok('riacceso: tutto torna', /acceso/.test(await C.addon(a, B, 'test', true)) && (await w.goto(B + '/fai-il-test/prova-punteggio/')).status() === 200);
  C.fine(err);
  await br.close();
})().catch(e => { console.log('ERRORE ' + e.stack); process.exit(1); }); // exit: il browser aperto terrebbe vivo il processo
