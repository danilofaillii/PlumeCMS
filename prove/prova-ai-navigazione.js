// La seconda fase dell'addon AI collegate (2.33.0): navigazione e schede degli articoli, come le userebbero un'AI (con una chiave
// personale, via MCP) e l'amministratore (nel browser). Su un sito col kit Attività: il menu Principale rovinato apposta
// (voce verso una bozza, voce doppia, voce verso una pagina che non c'è, etichetta lunga a maiuscole miste, otto voci) e una
// pagina orfana; analyze_navigation li trova tutti; propose_menu cattive (javascript:, data:, //evil, http, bozza, id
// inesistente, quattro livelli, 201 voci, etichetta solo HTML, menu inesistente) rifiutate senza righe; una proposta valida
// che sostituisce la vecchia; i due alberi affiancati nel pannello, Approva il menu e il menu cambiato sul sito pubblico; il
// conflitto quando il menu cambia dopo la proposta; review_article e list_articles_to_review su un articolo pieno di problemi;
// propose_article_changes (o tutte o nessuna, al massimo 20) raggruppate sotto la scheda con Approva tutte e Scarta tutte;
// propose_navigation_fix su un pubblicato (proposta, approvata, Leggi anche sul sito) e su una bozza (subito nella bozza);
// la pagina Proposte a 390 px senza scorrimento di lato; i tempi con 300 pagine e 5 menu.
//
// Serve un sito installato con il kit Attività (prove/prova-kit.js ... attivita ...), radice o cartella.
// uso: node prove/prova-ai-navigazione.js <base> <cartella CMS> <utente> <password> [cartella foto]
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, FOTO] = process.argv.slice(2);
if (!B || !DIR || !UTENTE || !PASSWORD) { console.error('uso: node prove/prova-ai-navigazione.js <base> <cartella CMS> <utente> <password> [cartella foto]'); process.exit(2); }
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (!c && extra !== '' ? '  ' + JSON.stringify(extra).slice(0, 500) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, 'ai-cli.php'), DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }).trim().split('\n').pop());
const sql = (q) => cli('sql', q);
const MCP = B + '/api/mcp';
let idRpc = 1;
async function rpc(token, method, params) {
  const r = await fetch(MCP, { method: 'POST', headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json', Accept: 'application/json, text/event-stream', 'MCP-Protocol-Version': '2025-06-18' }, body: JSON.stringify({ jsonrpc: '2.0', id: idRpc++, method, params }) });
  return { stato: r.status, json: await r.json().catch(() => null) };
}
async function strumento(token, nome, args) {
  const r = await rpc(token, 'tools/call', { name: nome, arguments: args || {} });
  const res = r.json && r.json.result; let dati = null; try { dati = JSON.parse(res.content[0].text); } catch (e) {}
  return { http: r.stato, errore: !res || !!res.isError, dati };
}
const nProposte = () => +sql('SELECT COUNT(*) n FROM {p}ai_proposte')[0].n;
const pagina = async (url) => (await fetch(url)).text();

(async () => {
  cli('accendi'); cli('pulisci'); cli('tentativi'); cli('nav-ripristina');
  const prep = cli('nav-prepara');
  const MID = prep.menu;
  const art = cli('articolo-prova', 'ai-prova-art-1');
  const K = cli('chiave', 'leggi,bozze,proposte', 'Chiave navigazione').chiave;
  const KL = cli('chiave', 'leggi', 'Chiave sola lettura').chiave;

  // === 1. Gli strumenti nuovi: elenco MCP, permessi, OpenAPI
  const lista = (await rpc(K, 'tools/list')).json.result.tools;
  const nomi = lista.map(t => t.name);
  const nuovi = ['analyze_navigation', 'propose_menu', 'propose_navigation_fix', 'review_article', 'list_articles_to_review', 'propose_article_changes'];
  ok('tools/list: i sei strumenti nuovi con descrizione e schema', nuovi.every(x => nomi.includes(x)) && nuovi.every(x => { const t = lista.find(y => y.name === x); return t.description.length > 80 && t.inputSchema.type === 'object'; }), nomi);
  ok('sola lettura segnata giusta (analyze_navigation, review_article, list_articles_to_review sì; le proposte no)', ['analyze_navigation', 'review_article', 'list_articles_to_review'].every(x => lista.find(y => y.name === x).annotations.readOnlyHint) && !lista.find(y => y.name === 'propose_menu').annotations.readOnlyHint);
  const oa = await (await fetch(MCP + '/openapi')).json();
  ok('OpenAPI: i sei strumenti come endpoint REST', nuovi.every(x => oa.paths['/rest/' + x] && oa.paths['/rest/' + x].post.operationId === x));
  const pmNo = await strumento(KL, 'propose_menu', { menu_id: MID, voci: [{ etichetta: 'Home', url: '/' }], motivo: 'senza permesso' });
  ok('propose_menu con una chiave di sola lettura: manca il permesso "proposte"', pmNo.errore && /"proposte"/.test(pmNo.dati.errore), pmNo.dati);
  const rest = await fetch(MCP + '/rest/analyze_navigation', { method: 'POST', headers: { Authorization: 'Bearer ' + KL, 'Content-Type': 'application/json' }, body: '{}' });
  ok('REST con la chiave: analyze_navigation risponde con il punteggio', rest.status === 200 && typeof (await rest.json()).punteggio === 'number');

  // === 2. analyze_navigation sul menu rovinato
  const t0 = Date.now();
  const an = await strumento(K, 'analyze_navigation', {});
  const tAn = Date.now() - t0;
  const A = an.dati || {};
  const rotte = (A.problemi || {}).voci_rotte || [];
  ok('analisi: risponde in meno di 3 secondi', !an.errore && tAn < 3000, [tAn, an.dati]);
  ok('analisi: l\'albero del menu Principale, nell\'header, con le otto voci e dove porta ognuna', A.menu.some(m => m.id === MID && m.dove.includes('header') && m.voci_primo_livello === 8 && m.albero.find(v => v.etichetta === 'Chi siamo').destinazione.tipo === 'pagine'), A.menu);
  ok('analisi: voce verso una bozza e voce verso una pagina che non c\'è', rotte.some(v => v.etichetta === 'Offerte' && v.esito === 'bozza') && rotte.some(v => v.esito === 'inesistente'), rotte);
  ok('analisi: voce doppia (Servizi)', A.problemi.doppioni.some(v => v.etichetta === 'Servizi'), A.problemi.doppioni);
  ok('analisi: etichetta oltre 25 caratteri e maiuscole miste', A.problemi.etichette_lunghe.some(v => v.caratteri > 25) && A.problemi.maiuscole_miste.length === 1, [A.problemi.etichette_lunghe, A.problemi.maiuscole_miste]);
  ok('analisi: troppe voci al primo livello (8 su 7)', A.problemi.troppe_voci_primo_livello.some(v => v.voci === 8));
  ok('analisi: la pagina orfana "Lavora con noi"', A.pagine_orfane.some(p => p.id === prep.orfana) && !A.pagine_orfane.some(p => p.percorso === '/servizi/'), A.pagine_orfane);
  ok('analisi: briciole assenti (senza impostazione), Contatti presente, voce del blog presente', A.briciole_di_pane.presenti === false && A.briciole_di_pane.impostazione === false && A.contatti.presente && A.blog.voce_presente);
  ok('analisi: per le pagine dei menu titolo, slug, invito finale e link verso le altre', A.pagine_dei_menu.length >= 4 && A.pagine_dei_menu.every(p => 'invito_finale' in p && 'link_verso_pagine_dei_menu' in p && p.slug !== undefined));
  ok('analisi: punteggio sotto 70 e suggerimenti in italiano', A.punteggio < 70 && A.suggerimenti.length >= 5 && A.suggerimenti.some(s => /briciole/i.test(s)), [A.punteggio, A.suggerimenti]);

  // === 3. propose_menu cattive: nessuna riga
  const prima = nProposte();
  const cattive = [
    ['javascript:', [{ etichetta: 'X', url: 'javascript:alert(1)' }]],
    ['data:', [{ etichetta: 'X', url: 'data:text/html,<script>alert(1)</script>' }]],
    ['//evil', [{ etichetta: 'X', url: '//evil.example/' }]],
    ['http esterno', [{ etichetta: 'X', url: 'http://evil.example/' }]],
    ['verso una bozza', [{ etichetta: 'X', tipo: 'pagina', id: prep.bozza }]],
    ['id inesistente', [{ etichetta: 'X', tipo: 'pagina', id: 999999 }]],
    ['link a una pagina inesistente', [{ etichetta: 'X', url: '/non-esiste-davvero/' }]],
    ['quattro livelli', [{ etichetta: 'A', url: '/', figli: [{ etichetta: 'B', url: '/', figli: [{ etichetta: 'C', url: '/', figli: [{ etichetta: 'D', url: '/' }] }] }] }]],
    ['201 voci', Array.from({ length: 201 }, (_, i) => ({ etichetta: 'V' + i, url: '/' }))],
    ['etichetta solo HTML', [{ etichetta: '<img src=x onerror=alert(1)>', url: '/' }]],
  ];
  const passate = [];
  for (const [nome, voci] of cattive) { const r = await strumento(K, 'propose_menu', { menu_id: MID, voci, motivo: 'Prova cattiva della navigazione.' }); if (!r.errore) passate.push(nome); }
  const altroMenu = await strumento(K, 'propose_menu', { menu_id: 987654, voci: [{ etichetta: 'Home', url: '/' }], motivo: 'Menu che non esiste.' });
  ok('propose_menu cattive (javascript:, data:, //evil, http, bozza, id inesistente, 404, 4 livelli, 201 voci, etichetta solo HTML, menu inesistente): tutte rifiutate, nessuna proposta', passate.length === 0 && altroMenu.errore && nProposte() === prima, passate);

  // === 4. propose_menu valida, poi una seconda che la sostituisce
  const vociBuone = [{ etichetta: 'Home', url: '/' }, { etichetta: 'Chi siamo', tipo: 'pagina', rif: 'chi-siamo', figli: [{ etichetta: 'Lavora con noi', tipo: 'pagina', id: prep.orfana }] },
    { etichetta: 'I servizi', tipo: 'pagina', rif: 'servizi' }, { etichetta: 'Blog', url: '/blog/' }, { etichetta: 'Contatti', url: '/contatti/' }];
  const pm1 = await strumento(K, 'propose_menu', { menu_id: MID, voci: [{ etichetta: 'Home', url: '/' }, { etichetta: 'Contatti', url: '/contatti/' }], motivo: 'Prima proposta, poi la cambio.' });
  const pm2 = await strumento(K, 'propose_menu', { menu_id: MID, voci: vociBuone, motivo: 'Tolgo le voci rotte e il doppione, metto Lavora con noi sotto Chi siamo.' });
  const st1 = sql('SELECT stato, nota FROM {p}ai_proposte WHERE id = ' + (+pm1.dati.proposta))[0];
  ok('propose_menu valida: proposta in attesa con il riepilogo (1 nuova, 3 tolte, 1 rinominata)', !pm2.errore && pm2.dati.stato === 'attesa' && pm2.dati.riepilogo.nuove === 1 && pm2.dati.riepilogo.tolte === 3 && pm2.dati.riepilogo.rinominate === 1, pm2.dati);
  ok('una proposta per menu: la vecchia ancora aperta va in Scartata con la nota', st1.stato === 'scartata' && /più recente/.test(st1.nota) && pm2.dati.sostituite === 1, st1);
  ok('il menu non è cambiato finché nessuno approva', cli('menu', MID).some(v => v.etichetta === 'Offerte'));

  // === 5. Il pannello: i due alberi, Approva il menu, il sito pubblico
  const br = await chromium.launch();
  const c = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await c.newPage();
  pnConferme.on(p, d => d.accept());
  const errJs = []; p.on('pageerror', e => errJs.push(e.message.slice(0, 160)));
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  const URLP = B + '/admin/addon-pagina.php?a=ai-collegate&p=proposte';
  await p.goto(URLP, { waitUntil: 'networkidle' });
  const box = '#proposta-' + pm2.dati.proposta;
  const testoPrima = await p.locator(box + ' .aic-prima').innerText(), testoDopo = await p.locator(box + ' .aic-dopo').innerText();
  ok('Proposte: il menu adesso e quello proposto affiancati, con le parole dei segni', await p.locator(box + '.aic-proposta-menu').count() === 1 && /Offerte[\s\S]*tolta/.test(testoPrima) && /Lavora con noi[\s\S]*nuova/.test(testoDopo) && /nome nuovo/.test(testoDopo) && /era «Servizi»/.test(testoDopo), [testoPrima, testoDopo]);
  const lati = await p.evaluate((s) => { const a = document.querySelector(s + ' .aic-prima').getBoundingClientRect(), b = document.querySelector(s + ' .aic-dopo').getBoundingClientRect(); return [Math.round(a.top), Math.round(b.top), Math.round(a.left), Math.round(b.left)]; }, box);
  ok('al computer i due alberi stanno uno accanto all\'altro', lati[0] === lati[1] && lati[3] > lati[2] + 200, lati);
  ok('una proposta di menu non ha "Modifica e approva" (lo dice la nota)', await p.locator(box + ' details.aic-modifica').count() === 0 && /Menu di navigazione/.test(await p.locator(box).innerText()));
  if (FOTO) await p.locator(box).screenshot({ path: path.join(FOTO, 'ai-menu-proposto.png') });
  await p.setViewportSize({ width: 390, height: 800 });
  await p.goto(URLP, { waitUntil: 'networkidle' });
  const t390 = await p.evaluate((s) => { const a = document.querySelector(s + ' .aic-prima').getBoundingClientRect(), b = document.querySelector(s + ' .aic-dopo').getBoundingClientRect(); return [document.documentElement.scrollWidth, innerWidth, b.top > a.bottom - 1]; }, box);
  ok('a 390 px gli alberi vanno uno sotto l\'altro e la pagina non scorre di lato', t390[0] <= t390[1] + 1 && t390[2], t390);
  await p.setViewportSize({ width: 1280, height: 900 });
  await p.goto(URLP, { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.click(box + ' button[value=approva]')]); await p.waitForLoadState('networkidle');
  const flash1 = await p.locator('.flash-success').first().innerText().catch(() => '');
  const menuDopo = cli('menu', MID);
  ok('Approva il menu: messaggio verde e menu salvato come proposto', /approvata/.test(flash1) && menuDopo.length === 5 && !menuDopo.some(v => v.etichetta === 'Offerte') && menuDopo[1].figli[0].etichetta === 'Lavora con noi', [flash1, menuDopo.map(v => v.etichetta)]);
  ok('la versione di prima del menu resta nelle versioni (Editor > Versioni)', +sql("SELECT COUNT(*) n FROM {p}editor_versioni WHERE ambito = 'menu' AND oggetto_id = " + MID)[0].n >= 2);
  const home = await pagina(B + '/');
  ok('sul sito pubblico il menu nuovo: "Lavora con noi" c\'è, "Offerte" no', /Lavora con noi/.test(home) && !/>Offerte</.test(home) && /I servizi/.test(home));
  const an2 = await strumento(K, 'analyze_navigation', {});
  ok('dopo l\'approvazione l\'analisi migliora: niente voci rotte, niente orfane', an2.dati.problemi.voci_rotte.length === 0 && an2.dati.pagine_orfane.length === 0 && an2.dati.punteggio > A.punteggio, [an2.dati.punteggio, an2.dati.problemi.voci_rotte]);

  // === 6. Conflitto: il menu cambia dopo la proposta
  const pm3 = await strumento(K, 'propose_menu', { menu_id: MID, voci: vociBuone.slice(0, 4), motivo: 'Tolgo Contatti (prova del conflitto).' });
  cli('menu-cambia', MID);
  await p.goto(URLP, { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.click('#proposta-' + pm3.dati.proposta + ' button[value=approva]')]); await p.waitForLoadState('networkidle');
  const flash2 = await p.locator('.flash-error').first().innerText().catch(() => '');
  const menuC = cli('menu', MID);
  ok('menu cambiato dopo la proposta: l\'approvazione si ferma con l\'avviso, il menu resta, la proposta resta da decidere', /è cambiato/.test(flash2) && menuC.some(v => v.etichetta === 'Aggiunta a mano') && menuC.some(v => v.etichetta === 'Contatti') && sql('SELECT stato FROM {p}ai_proposte WHERE id = ' + pm3.dati.proposta)[0].stato === 'attesa', [flash2, menuC.map(v => v.etichetta)]);
  await Promise.all([p.waitForNavigation(), p.click('#proposta-' + pm3.dati.proposta + ' button[value=scarta]')]); await p.waitForLoadState('networkidle');

  // === 7. review_article e list_articles_to_review
  const rv = await strumento(K, 'review_article', { tipo: 'articoli', id: art.id });
  const R = rv.dati || {};
  ok('review_article: l\'attacco non risponde alla parola chiave', !rv.errore && R.attacco.risponde === false && R.parola_chiave === 'potare le rose', R.attacco);
  ok('review_article: salto H2 > H4 e paragrafo lungo', R.sottotitoli.salti.length === 1 && /H4/.test(R.sottotitoli.salti[0]) && R.paragrafi_lunghi.length === 1);
  ok('review_article: frasi datate con il punto preciso (quest\'anno, recentemente, l\'anno passato) e il widget_id', ['quest\'anno', 'recentemente', 'anno passato'].every(g => R.frasi_datate.some(f => f.genere === g && f.widget_id === art.testo_id)), R.frasi_datate);
  ok('review_article: immagine senza alt, domande frequenti mancanti, ultima modifica, punteggio e consigli', R.immagini_senza_alt.length === 1 && R.domande_frequenti.quante === 0 && R.ultima_modifica && typeof R.punteggio_revisione === 'number' && R.consigli.length >= 5 && R.widget_di_testo.some(w => w.widget_id === art.testo_id));
  ok('review_article: titolo e descrizione per Google con il semaforo (dalla Tabella SEO)', R.titolo_per_google.valore === 'Rose' && R.descrizione_per_google.semaforo !== 'verde');
  const la = await strumento(K, 'list_articles_to_review', { per_pagina: 5 });
  ok('list_articles_to_review: l\'articolo con più problemi è il primo, con i problemi principali', !la.errore && la.dati.contenuti[0].id === art.id && la.dati.contenuti[0].problemi_principali.length > 0 && la.dati.totale >= 2, la.dati);
  const laP = await strumento(K, 'list_articles_to_review', { tipo: 'pagine', per_pagina: 2, pagina: 2 });
  ok('list_articles_to_review: le pagine, a pagine di 2', !laP.errore && laP.dati.contenuti.length <= 2 && laP.dati.pagina === 2);

  // === 8. propose_article_changes: o tutte o nessuna, al massimo 20, raggruppate nel pannello
  const p0 = nProposte();
  const pc1 = await strumento(K, 'propose_article_changes', { tipo: 'articoli', id: art.id, motivo: 'Scheda con un errore.', modifiche: [{ campo: 'titolo_seo', valore: 'Titolo che non deve restare' }, { campo: 'testo', widget_id: 'zzzzzz', valore: '<p>x</p>' }] });
  const pc2 = await strumento(K, 'propose_article_changes', { tipo: 'articoli', id: art.id, motivo: 'Troppe modifiche.', modifiche: Array.from({ length: 21 }, (_, i) => ({ campo: 'titolo_seo', valore: 'T' + i })) });
  const pc3 = await strumento(K, 'propose_article_changes', { tipo: 'articoli', id: art.id, motivo: 'Campo doppio.', modifiche: [{ campo: 'titolo_seo', valore: 'A uno' }, { campo: 'titolo_seo', valore: 'A due' }] });
  ok('propose_article_changes con una modifica sbagliata, con 21 modifiche o con un campo doppio: nessuna proposta salvata', pc1.errore && /Nessuna proposta salvata: modifica 2/.test(pc1.dati.errore) && pc2.errore && /massimo 20/.test(pc2.dati.errore) && pc3.errore && nProposte() === p0, [pc1.dati, pc2.dati, pc3.dati]);
  const mods = [{ campo: 'titolo_seo', valore: 'Potare le rose in inverno: la guida in cinque passi' },
    { campo: 'descrizione_seo', valore: 'Quando e come potare le rose in inverno: attrezzi, tagli giusti ed errori da evitare, spiegati passo per passo.' },
    { campo: 'testo', widget_id: art.testo_id, valore: '<p>Potare le rose in inverno le fa fiorire di più: si taglia sopra una gemma, con forbici pulite.</p><h2>Gli attrezzi</h2><p>Forbici affilate e guanti.</p><h3>Le forbici</h3><p>Meglio a lama doppia.</p>' },
    { campo: 'domande_frequenti', valore: [{ domanda: 'Quando si potano le rose?', risposta: 'A fine inverno, prima che riprendano a crescere.' }] },
    { campo: 'alt_immagine', valore: 'Rose potate in giardino', motivo: 'Alt per chi non vede la foto.' }];
  const pc = await strumento(K, 'propose_article_changes', { tipo: 'articoli', id: art.id, motivo: 'Scheda dell\'articolo rivista: titolo, descrizione, attacco, sottotitoli e domande.', modifiche: mods });
  const G = pc.dati && pc.dati.gruppo;
  ok('propose_article_changes: cinque proposte legate da un codice di gruppo', !pc.errore && /^articoli-\d+-[a-f0-9]{12}$/.test(G || '') && pc.dati.proposte.length === 5 && +sql(`SELECT COUNT(*) n FROM {p}ai_proposte WHERE gruppo = '${G}' AND stato = 'attesa'`)[0].n === 5, pc.dati);
  const lp = await strumento(K, 'list_proposals', { stato: 'attesa' });
  ok('list_proposals mostra il gruppo', lp.dati.proposte.filter(x => x.gruppo === G).length === 5);
  await p.goto(URLP, { waitUntil: 'networkidle' });
  const sc = `#scheda-${G}`;
  const titoloScheda = await p.locator(sc + ' > h3').innerText();
  ok('Proposte: le cinque sotto "Scheda dell\'articolo Potare le rose in inverno", con Approva tutte e Scarta tutte', /Scheda dell'articolo: Potare le rose in inverno/.test(titoloScheda) && await p.locator(sc + ' article.aic-nella-scheda').count() === 5 && await p.locator(sc + ' button[value=scheda_approva]').count() === 1 && await p.locator(sc + ' button[value=scheda_scarta]').count() === 1, titoloScheda);
  if (FOTO) await p.locator(sc).screenshot({ path: path.join(FOTO, 'ai-scheda-articolo.png') });
  await Promise.all([p.waitForNavigation(), p.click(sc + ' button[value=scheda_approva]')]); await p.waitForLoadState('networkidle');
  const rA = sql('SELECT seo_title, seo_description, faq, featured_image_alt FROM {p}articles WHERE id = ' + art.id)[0];
  const sitoA = await pagina(B + '/ai-prova-art-1/');
  ok('Approva tutte: le cinque approvate, titolo, descrizione, domande e alt salvati', +sql(`SELECT COUNT(*) n FROM {p}ai_proposte WHERE gruppo = '${G}' AND stato = 'approvata'`)[0].n === 5 && rA.seo_title === mods[0].valore && /Quando si potano/.test(rA.faq) && rA.featured_image_alt === 'Rose potate in giardino', rA);
  ok('sul sito l\'articolo ha il testo nuovo e il titolo per Google nuovo', /fiorire di più/.test(sitoA) && sitoA.includes('<title>Potare le rose in inverno: la guida in cinque passi'));
  const rv2 = await strumento(K, 'review_article', { tipo: 'articoli', id: art.id });
  ok('dopo l\'approvazione la scheda migliora: attacco giusto, niente salti, niente frasi datate nel testo', rv2.dati.attacco.risponde && rv2.dati.sottotitoli.salti.length === 0 && !rv2.dati.frasi_datate.some(f => f.campo === 'testo') && rv2.dati.punteggio_revisione > R.punteggio_revisione, [rv2.dati.attacco, rv2.dati.punteggio_revisione]);
  const pcS = await strumento(K, 'propose_article_changes', { tipo: 'articoli', id: art.id, motivo: 'Seconda scheda da scartare.', modifiche: [{ campo: 'in_breve', valore: 'Si potano a fine inverno.' }, { campo: 'estratto', valore: 'Come potare le rose.' }] });
  await p.goto(URLP, { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.click(`#scheda-${pcS.dati.gruppo} button[value=scheda_scarta]`)]); await p.waitForLoadState('networkidle');
  ok('Scarta tutte: le proposte della scheda scartate, l\'articolo non cambia', +sql(`SELECT COUNT(*) n FROM {p}ai_proposte WHERE gruppo = '${pcS.dati.gruppo}' AND stato = 'scartata'`)[0].n === 2 && !sql('SELECT tldr FROM {p}articles WHERE id = ' + art.id)[0].tldr);

  // === 9. propose_navigation_fix
  const chi = +sql("SELECT id FROM {p}pages WHERE slug = 'chi-siamo'")[0].id, serv = +sql("SELECT id FROM {p}pages WHERE slug = 'servizi'")[0].id;
  const nf = await strumento(K, 'propose_navigation_fix', { tipo: 'pagine', id: chi, motivo: 'Chi siamo non rimanda alle altre pagine.', leggi_anche: { titolo: 'Altre pagine', voci: [{ tipo: 'pagine', id: serv }, { tipo: 'pagine', id: prep.orfana }] }, briciole: true });
  ok('propose_navigation_fix su un pubblicato: proposta "Blocchi in fondo alla pagina" e la nota sulle briciole', !nf.errore && nf.dati.campo === 'navigazione' && nf.dati.avvisi.some(a => /Briciole/.test(a)), nf.dati);
  const nfB = await strumento(K, 'propose_navigation_fix', { tipo: 'pagine', id: prep.bozza, motivo: 'Bozza: invito verso Contatti.', invito: { titolo: 'Ti interessa?', testo: 'Scrivici.', pulsante: 'Scrivici' } });
  const bz = sql('SELECT struttura, struttura_bozza, status FROM {p}pages WHERE id = ' + prep.bozza)[0];
  ok('propose_navigation_fix su una bozza: l\'invito va subito nella bozza, resta bozza', !nfB.errore && nfB.dati.applicato_alla_bozza && /Ti interessa/.test(bz.struttura_bozza || '') && bz.status === 'draft', nfB.dati);
  const nfX = await strumento(K, 'propose_navigation_fix', { tipo: 'pagine', id: chi, motivo: 'Leggi anche verso una bozza.', leggi_anche: { voci: [{ tipo: 'pagine', id: prep.bozza }] } });
  const nfY = await strumento(K, 'propose_navigation_fix', { tipo: 'pagine', id: prep.orfana, motivo: 'Invito con link cattivo.', invito: { link: 'javascript:alert(1)' } });
  ok('propose_navigation_fix verso una bozza o con un link javascript: rifiutate', nfX.errore && nfY.errore, [nfX.dati, nfY.dati]);
  await p.goto(URLP, { waitUntil: 'networkidle' });
  ok('Proposte: prima e dopo leggibili dei blocchi in fondo', /Leggi anche|Altre pagine/.test(await p.locator('#proposta-' + nf.dati.proposta + ' .aic-dopo').innerText()) && /ultima sezione/.test(await p.locator('#proposta-' + nf.dati.proposta + ' .aic-prima').innerText()));
  await Promise.all([p.waitForNavigation(), p.click('#proposta-' + nf.dati.proposta + ' button[value=approva]')]); await p.waitForLoadState('networkidle');
  const sitoChi = await pagina(B + '/chi-siamo/');
  ok('approvata: sul sito Chi siamo finisce con "Altre pagine" e i link', /Altre pagine/.test(sitoChi) && /ai-prova-nav-orfana\//.test(sitoChi));

  // === 10. Telefono e tempi
  await p.setViewportSize({ width: 390, height: 800 });
  await p.goto(URLP + '&stato=tutte', { waitUntil: 'networkidle' });
  const l390 = await p.evaluate(() => [document.documentElement.scrollWidth, innerWidth]);
  ok('a 390 px la pagina Proposte (tutte, con menu e schede) non scorre di lato', l390[0] <= l390[1] + 1, l390);
  ok('nessun errore JavaScript nel pannello', errJs.length === 0, errJs);
  await br.close();
  if (!process.env.SENZA_MOLTE) {
    cli('nav-molte', 300);
    const t1 = Date.now(); const big = await strumento(K, 'analyze_navigation', {}); const tBig = Date.now() - t1;
    console.log('    tempi: analisi ' + tBig + ' ms');
    ok('con 300 pagine in più e 5 menu l\'analisi risponde in meno di 5 secondi', !big.errore && big.dati.menu.length >= 5 && tBig < 5000, [tBig, big.errore && big.dati]);
    const t2 = Date.now(); const bigL = await strumento(K, 'list_articles_to_review', { tipo: 'pagine' }); const tL = Date.now() - t2;
    console.log('    tempi: elenco ' + tL + ' ms');
    ok('con 300 pagine list_articles_to_review (pagine) risponde in meno di 15 secondi', !bigL.errore && bigL.dati.totale >= 300 && tL < 15000, [tL]);
  }
  cli('nav-ripristina'); cli('pulisci'); cli('tentativi');
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
