// La terza fase dell'addon AI collegate (2.34.0): lo stile del sito e gli articoli nuovi dagli argomenti, come li userebbero
// un'AI (con una chiave personale, via MCP) e l'amministratore (nel browser). Su un sito col kit Attività più dieci articoli
// scritti con uno stile riconoscibile (prove/ai-cli.php stile-semina: frasi brevi in seconda persona, una domanda nell'attacco,
// due H2, un elenco, un link interno; il buco voluto "concime naturale"): analyze_style restituisce misure coerenti;
// save_style_profile e get_style_profile, con permessi e testi cattivi; la pagina Stile del sito con le misure in parole,
// Ripristina e la correzione a mano; suggest_topics trova il buco, la categoria povera e il tag con un solo articolo, e non
// scambia le formule dello stile per argomenti; propose_article_ideas valida e cattiva (tutte o nessuna); Approva, Scarta,
// Modifica con la data prevista e Sposta prima nella pagina Idee per articoli; list_article_ideas con l'ordine del piano;
// create_draft con idea_id (stati sbagliati rifiutati senza bozze, la bozza legata all'idea, il controllo di coerenza con uno
// scostamento voluto); update_draft che rientra nello stile; le pagine a 390 px senza scorrimento di lato; i tempi con 300 articoli.
//
// Serve un sito installato con il kit Attività (prove/prova-kit.js ... attivita ...), radice o cartella.
// uso: node prove/prova-ai-stile.js <base> <cartella CMS> <utente> <password> [cartella foto]   (SENZA_MOLTE=1 salta i 300 articoli)
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, FOTO] = process.argv.slice(2);
if (!B || !DIR || !UTENTE || !PASSWORD) { console.error('uso: node prove/prova-ai-stile.js <base> <cartella CMS> <utente> <password> [cartella foto]'); process.exit(2); }
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (!c && extra !== '' ? '  ' + JSON.stringify(extra).slice(0, 600) : '')); };
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
const nIdee = (dove = '') => +sql('SELECT COUNT(*) n FROM {p}ai_idee' + dove)[0].n;
const nArticoli = () => +sql('SELECT COUNT(*) n FROM {p}articles')[0].n;
const largo = async (p) => p.evaluate(() => [document.documentElement.scrollWidth, innerWidth]);

(async () => {
  cli('accendi'); cli('pulisci'); cli('stile-pulisci'); cli('tentativi');
  const sem = cli('stile-semina');
  const K = cli('chiave', 'leggi,bozze,proposte', 'Chiave stile').chiave;
  const KL = cli('chiave', 'leggi', 'Chiave sola lettura').chiave;

  // === 1. Gli strumenti nuovi: elenco MCP, sola lettura, OpenAPI, idea_id
  const lista = (await rpc(K, 'tools/list')).json.result.tools;
  const nuovi = ['analyze_style', 'save_style_profile', 'get_style_profile', 'suggest_topics', 'propose_article_ideas', 'list_article_ideas'];
  ok('tools/list: i sei strumenti nuovi con descrizione in italiano e schema', nuovi.every(x => { const t = lista.find(y => y.name === x); return t && t.description.length > 80 && t.inputSchema.type === 'object'; }), lista.map(t => t.name));
  ok('sola lettura segnata giusta (analyze_style, get_style_profile, suggest_topics, list_article_ideas sì; save_style_profile e propose_article_ideas no)', ['analyze_style', 'get_style_profile', 'suggest_topics', 'list_article_ideas'].every(x => lista.find(y => y.name === x).annotations.readOnlyHint) && !lista.find(y => y.name === 'save_style_profile').annotations.readOnlyHint && !lista.find(y => y.name === 'propose_article_ideas').annotations.readOnlyHint);
  ok('create_draft e update_draft accettano idea_id', ['create_draft', 'update_draft'].every(x => lista.find(y => y.name === x).inputSchema.properties.idea_id));
  const oa = await (await fetch(MCP + '/openapi')).json();
  ok('OpenAPI: i sei strumenti come endpoint REST', nuovi.every(x => oa.paths['/rest/' + x] && oa.paths['/rest/' + x].post.operationId === x));

  // === 2. analyze_style sugli articoli seminati
  const t0 = Date.now();
  const an = await strumento(K, 'analyze_style', {});
  const tAn = Date.now() - t0;
  const A = an.dati || {};
  ok('analyze_style: risponde in meno di 3 secondi e misura almeno gli 11 articoli pubblicati', !an.errore && tAn < 3000 && A.articoli_misurati >= 11 && A.articoli_pubblicati >= 11, [tAn, an.dati && an.dati.errore]);
  ok('persona: la seconda persona (tu) è la dominante, dieci articoli su dieci seminati', A.persona.dominante === 'tu' && A.persona.articoli.tu >= 10 && A.persona.ogni_mille_parole.tu > 40, A.persona);
  ok('frasi brevi: meno di 8 parole per frase in media; paragrafi corti', A.frasi.parole_per_frase_media > 3 && A.frasi.parole_per_frase_media < 8 && A.paragrafi.parole_per_paragrafo_media < 30, [A.frasi, A.paragrafi]);
  ok('domande: l\'attacco con una domanda in almeno l\'80% degli articoli, almeno una domanda per articolo', A.domande_ai_lettori.attacchi_con_domanda_percento >= 80 && A.domande_ai_lettori.per_articolo >= 1, A.domande_ai_lettori);
  ok('struttura: circa due H2 per articolo, elenchi nell\'80%, un link interno per articolo', A.sottotitoli.h2_per_articolo >= 1.7 && A.sottotitoli.h2_per_articolo <= 2 && A.elenchi.articoli_con_elenchi_percento >= 80 && A.link_interni.per_articolo >= 0.8, [A.sottotitoli, A.elenchi, A.link_interni]);
  ok('lunghezza: media e mediana in parole, minimo e massimo', A.lunghezza.parole_mediana > 50 && A.lunghezza.parole_mediana < 200 && A.lunghezza.parole_minimo <= A.lunghezza.parole_mediana && A.lunghezza.parole_massimo >= A.lunghezza.parole_mediana, A.lunghezza);
  ok('parole ricorrenti senza parole vuote (vanga, semi), espressioni dentro le frasi', A.ricorrenti.parole.some(x => x.testo === 'vanga') && !A.ricorrenti.parole.some(x => ['che', 'il', 'di', 'com'].includes(x.testo)) && !A.ricorrenti.espressioni_di_2.some(x => x.testo === 'freschi acqua'), A.ricorrenti.espressioni_di_2.slice(0, 6));
  ok('attacchi e chiusure tipici (5) e da 3 a 5 estratti diversi, ognuno al massimo di 600 caratteri, con il criterio', A.attacchi_tipici.length === 5 && A.chiusure_tipiche.length === 5 && A.estratti.length >= 3 && A.estratti.length <= 5 && A.estratti.every(e => e.testo.length <= 600) && new Set(A.estratti.map(e => e.testo)).size === A.estratti.length && /paragrafo/.test(A.criterio_estratti), [A.estratti.length, A.attacchi_tipici[0]]);
  ok('categorie e tag più usati, titoli e descrizioni per Google, struttura tipica dei widget', A.categorie.some(c => c.categoria === 'Orto prova' && c.articoli === 9) && A.tag.some(t => t.tag === 'Lumache prova' && t.articoli === 1) && A.titoli.descrizione_google_media > 50 && A.widget_editor.articoli_con_editor === 5 && A.widget_editor.sequenze_piu_comuni[0].sequenza === 'testo > immagine', [A.categorie, A.widget_editor]);
  ok('come_leggerlo spiega le misure (persona, frasi, ricorrenti)', Array.isArray(A.come_leggerlo) && A.come_leggerlo.length >= 8 && A.come_leggerlo.some(x => /tu\/ti\/te/.test(x)));
  const an3 = await strumento(K, 'analyze_style', { quanti: 3, salta: 2 });
  ok('analyze_style con quanti 3 e salta 2: tre articoli misurati', !an3.errore && an3.dati.articoli_misurati === 3 && an3.dati.salta === 2, an3.dati);

  // === 3. Il profilo: save_style_profile e get_style_profile
  const g0 = await strumento(K, 'get_style_profile', {});
  ok('get_style_profile senza profilo: lo dice e suggerisce analyze_style', !g0.errore && g0.dati.profilo === null && /analyze_style/.test(g0.dati.nota), g0.dati);
  const PROF1 = 'Tono: amichevole e pratico.\nPersona: diamo del tu a chi legge.\nLessico da usare: vanga, semi, orto.\nStruttura: domanda nell\'attacco, due H2, un elenco, un link interno.\nRegole: frasi brevi, sotto le otto parole.';
  const sL = await strumento(KL, 'save_style_profile', { profilo: PROF1 });
  const sC = await strumento(K, 'save_style_profile', { profilo: '<b>corto</b>' });
  ok('save_style_profile con una chiave di sola lettura (manca "bozze") o con un testo troppo corto: rifiutato', sL.errore && /"bozze"/.test(sL.dati.errore) && sC.errore && /troppo corto/.test(sC.dati.errore), [sL.dati, sC.dati]);
  const s1 = await strumento(K, 'save_style_profile', { profilo: PROF1 + '\n<script>alert(1)</script>Esempi: <b>Prendi la vanga.</b>' });
  const s1r = sql('SELECT testo, autore, origine, misure FROM {p}ai_stile ORDER BY id DESC LIMIT 1')[0] || {};
  ok('save_style_profile: salvato senza tag, con le misure calcolate dal sito e "AI: <nome>" come autore', !s1.errore && s1.dati.versione > 0 && !/<script|<b>/.test(s1r.testo) && /Prendi la vanga/.test(s1r.testo) && s1r.autore === 'AI: Chiave stile' && s1r.origine === 'ai' && JSON.parse(s1r.misure).persona.dominante === 'tu', [s1.dati, s1r.testo]);
  const PROF2 = PROF1.replace('amichevole e pratico', 'diretto e caldo');
  const s2 = await strumento(K, 'save_style_profile', { profilo: PROF2 });
  const g1 = await strumento(K, 'get_style_profile', {});
  ok('get_style_profile: il testo ultimo, la data, chi l\'ha salvato, le misure e una versione precedente', !g1.errore && /diretto e caldo/.test(g1.dati.profilo) && g1.dati.salvato && g1.dati.salvato_da === 'AI: Chiave stile' && g1.dati.misure.lunghezza.parole_mediana > 0 && g1.dati.versioni_precedenti === 1, g1.dati);

  // === 4. Il pannello: Stile del sito
  const br = await chromium.launch();
  const c = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await c.newPage();
  pnConferme.on(p, d => d.accept());
  const errJs = []; p.on('pageerror', e => errJs.push(e.message.slice(0, 160)));
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  const URLS = B + '/admin/addon-pagina.php?a=ai-collegate&p=stile';
  await p.goto(URLS, { waitUntil: 'networkidle' });
  const testoArea = await p.inputValue('#aic-profilo-testo');
  const misure = await p.locator('#misure dl.aic-misure').innerText();
  ok('Stile del sito: il profilo in uso nel campo, chi e quando, le misure in parole (non JSON)', /diretto e caldo/.test(testoArea) && /AI: Chiave stile/.test(await p.locator('#profilo').innerText()) && /Dà del tu/.test(misure) && /parole per frase/.test(misure) && !/[{}]/.test(misure), [misure.slice(0, 300)]);
  ok('Stile del sito: nelle schede del pannello c\'è "Stile del sito" e "Idee per articoli"', await p.locator('nav.imp-schede a', { hasText: 'Stile del sito' }).count() === 1 && await p.locator('nav.imp-schede a', { hasText: 'Idee per articoli' }).count() === 1);
  ok('Stile del sito: una versione precedente con Ripristina', await p.locator('#aic-versioni tbody tr').count() === 1);
  if (FOTO) await p.screenshot({ path: path.join(FOTO, 'ai-stile.png'), fullPage: true });
  await Promise.all([p.waitForNavigation(), p.click('#aic-versioni button:has-text("Ripristina")')]); await p.waitForLoadState('networkidle');
  ok('Ripristina: torna in uso la versione di prima, quella di adesso resta fra le precedenti', /amichevole e pratico/.test(await p.inputValue('#aic-profilo-testo')) && await p.locator('#aic-versioni tbody tr').count() === 2 && (sql('SELECT origine FROM {p}ai_stile ORDER BY id DESC LIMIT 1')[0] || {}).origine === 'ripristino');
  await p.fill('#aic-profilo-testo', PROF2 + '\nDa evitare: il voi e le frasi lunghe.');
  await Promise.all([p.waitForNavigation(), p.click('.aic-profilo-form button[type=submit]')]); await p.waitForLoadState('networkidle');
  const ultimo = sql('SELECT testo, autore, origine FROM {p}ai_stile ORDER BY id DESC LIMIT 1')[0] || {};
  ok('Salva il profilo (a mano): versione nuova con l\'utente come autore, messaggio verde', ultimo.origine === 'pannello' && ultimo.autore === UTENTE && /Da evitare: il voi/.test(ultimo.testo) && /salvato/.test(await p.locator('.flash-success').first().innerText().catch(() => '')), ultimo);
  const g2 = await strumento(K, 'get_style_profile', {});
  ok('get_style_profile legge la correzione fatta a mano', /Da evitare: il voi/.test(g2.dati.profilo) && g2.dati.origine === 'pannello');

  // === 5. suggest_topics
  const t1 = Date.now();
  const st = await strumento(K, 'suggest_topics', {});
  const tSt = Date.now() - t1;
  const S = st.dati || {}; const sp = S.spunti || [];
  const concime = sp.find(x => x.tema === 'concime naturale');
  ok('suggest_topics: risponde in meno di 3 secondi con i temi coperti (categorie, tag, parole chiave, titoli)', !st.errore && tSt < 3000 && S.temi_coperti.categorie.length >= 2 && S.temi_coperti.parole_chiave.length >= 10 && S.temi_coperti.titoli.length >= 10, [tSt, st.dati && st.dati.errore]);
  ok('suggest_topics trova il buco seminato: "concime naturale" citato in quattro articoli senza un articolo suo, primo per forza', concime && concime.tipo === 'parola_chiave_senza_articolo' && concime.nasce_da.length === 4 && concime.link_interni_consigliati.length === 4 && sp[0].tema === 'concime naturale', sp.slice(0, 3));
  ok('suggest_topics: la categoria povera (Balcone prova) e il tag con un solo articolo (Lumache prova)', sp.some(x => x.tipo === 'categoria_povera' && x.tema === 'Balcone prova') && sp.some(x => x.tipo === 'tag_con_un_articolo' && x.tema === 'Lumache prova'));
  ok('suggest_topics: le domande frequenti come spunti, e le formule dello stile ("ora tocca", "bastano pochi") non sono argomenti', sp.some(x => x.tipo === 'domanda_che_diventa_articolo') && !sp.some(x => /tocca|bastano pochi|strada facendo/.test(x.tema)), sp.map(x => x.tema));
  ok('suggest_topics non inventa titoli: dà tipo, tema, perché e come_leggerlo', sp.every(x => x.tipo && x.tema && x.perche && Array.isArray(x.nasce_da)) && S.come_leggerlo.length >= 4 && S.statistiche.disponibile === false);

  // === 6. propose_article_ideas: valide e cattive
  const idA = sem.ids[0], idB = sem.ids[1];
  const ideaBuona = (t, extra = {}) => ({ titolo: t, parola_chiave: 'concime naturale', a_chi_serve: 'Chi ha un orto piccolo', scaletta: ['Cos\'è', 'Come si fa', 'Errori da evitare'], collegati: [{ id: idA }, { id: idB }], motivo: 'Citato in quattro articoli senza un articolo suo.', ...extra });
  const i0 = nIdee();
  const iL = await strumento(KL, 'propose_article_ideas', { idee: [ideaBuona('Senza permesso')] });
  const i21 = await strumento(K, 'propose_article_ideas', { idee: Array.from({ length: 21 }, (_, i) => ideaBuona('Idea numero ' + i)) });
  const iMez = await strumento(K, 'propose_article_ideas', { idee: [ideaBuona('Prima idea valida'), { titolo: 'Seconda senza motivo' }] });
  const iDop = await strumento(K, 'propose_article_ideas', { idee: [ideaBuona('Titolo doppio'), ideaBuona('Titolo  doppio')] });
  ok('propose_article_ideas senza permesso "proposte", con 21 idee, con una idea senza motivo o con due titoli uguali: nessuna idea salvata', iL.errore && /"proposte"/.test(iL.dati.errore) && i21.errore && /massimo 20/.test(i21.dati.errore) && iMez.errore && /Idea 2/.test(iMez.dati.errore) && iDop.errore && nIdee() === i0, [iL.dati, i21.dati, iMez.dati, iDop.dati]);
  const pi = await strumento(K, 'propose_article_ideas', { idee: [
    ideaBuona('Il concime naturale per l\'orto di casa'),
    ideaBuona('<script>alert(1)</script>Pacciamatura: come e quando', { parola_chiave: 'pacciamatura', collegati: [{ id: idA }, { id: 99999999 }, { tipo: 'pagine', id: 1 }] }),
    ideaBuona('Una idea da scartare', { scaletta: '- primo\n- secondo' }),
  ] });
  const ideeIds = pi.dati && pi.dati.idee ? pi.dati.idee.map(x => x.idea_id) : [];
  const [I1, I2, I3] = ideeIds;
  const r2 = sql('SELECT titolo, collegati, stato FROM {p}ai_idee WHERE id = ' + (+I2))[0] || {};
  ok('propose_article_ideas: tre idee in attesa; il titolo senza tag; il collegato che non c\'è tolto con un avviso', !pi.errore && ideeIds.length === 3 && nIdee(" WHERE stato = 'attesa'") === 3 && r2.titolo === 'Pacciamatura: come e quando' && !/99999999/.test(r2.collegati) && pi.dati.avvisi.some(a => /99999999/.test(a)), [pi.dati, r2]);
  const lA = await strumento(K, 'list_article_ideas', { stato: 'attesa' });
  ok('list_article_ideas (attesa): le tre idee con scaletta, collegati e stato', !lA.errore && lA.dati.totale === 3 && lA.dati.idee[0].scaletta.length === 3 && lA.dati.idee[0].collegati.length === 2 && lA.dati.idee[2].scaletta.join('|') === 'primo|secondo', lA.dati);
  const cAtt = await strumento(K, 'create_draft', { tipo: 'articoli', titolo: 'Bozza da idea non approvata', idea_id: I1, testo: '<p>x</p>' });
  ok('create_draft con un\'idea ancora da decidere: rifiutata, nessuna bozza', cAtt.errore && /approvate/.test(cAtt.dati.errore) && +sql("SELECT COUNT(*) n FROM {p}articles WHERE title = 'Bozza da idea non approvata'")[0].n === 0, cAtt.dati);

  // === 7. Il pannello: Idee per articoli
  const URLI = B + '/admin/addon-pagina.php?a=ai-collegate&p=idee';
  await p.goto(URLI, { waitUntil: 'networkidle' });
  ok('Idee per articoli: le tre idee da decidere con Approva e Scarta; il menu dice quante', await p.locator('article.aic-idea[data-stato=attesa]').count() === 3 && await p.locator('#idea-' + I1 + ' button[value=approva]').count() === 1 && /3 da decidere/.test(await p.locator('nav.imp-schede').innerText()));
  ok('Idee per articoli: il titolo con lo script esce come testo (niente tag)', await p.locator('#idea-' + I2 + ' script').count() === 0 && /Pacciamatura/.test(await p.locator('#idea-' + I2 + ' h3').innerText()));
  if (FOTO) await p.screenshot({ path: path.join(FOTO, 'ai-idee.png'), fullPage: true });
  await Promise.all([p.waitForNavigation(), p.click('#idea-' + I1 + ' button[value=approva]')]); await p.waitForLoadState('networkidle');
  await p.goto(URLI + '&vista=attesa', { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.click('#idea-' + I3 + ' button[value=scarta]')]); await p.waitForLoadState('networkidle');
  await p.goto(URLI + '&vista=attesa', { waitUntil: 'networkidle' });
  const oggi = new Date(); const prossimo = new Date(oggi.getFullYear(), oggi.getMonth() + 1, 15);
  const dataP = prossimo.getFullYear() + '-' + String(prossimo.getMonth() + 1).padStart(2, '0') + '-15';
  await p.click('#modifica-' + I2 + ' summary');
  await p.fill('#k-' + I2, 'pacciamatura naturale');
  await p.fill('#d-' + I2, dataP);
  await Promise.all([p.waitForNavigation(), p.click('#modifica-' + I2 + ' button[type=submit]')]); await p.waitForLoadState('networkidle');
  const stati = sql('SELECT id, stato, data_prevista, parola_chiave, deciso_da FROM {p}ai_idee ORDER BY id');
  const st1 = stati.find(x => +x.id === I1), st2 = stati.find(x => +x.id === I2), st3 = stati.find(x => +x.id === I3);
  ok('Approva, Scarta e Modifica (con la data prevista e "e approva"): da scrivere, scartata, da scrivere con la data', st1.stato === 'da_scrivere' && st1.deciso_da === UTENTE && st3.stato === 'scartata' && st2.stato === 'da_scrivere' && st2.data_prevista === dataP && st2.parola_chiave === 'pacciamatura naturale', stati);
  await p.goto(URLI + '&vista=piano', { waitUntil: 'networkidle' });
  const mesi = await p.locator('.aic-mese-titolo').allInnerTexts();
  ok('Piano editoriale: diviso per mese (il mese della data prevista, poi "Senza data prevista")', mesi.length === 2 && /Senza data prevista/.test(mesi[1]) && new RegExp(String(prossimo.getFullYear())).test(mesi[0]), mesi);
  // La stessa data per tutte e due, poi Sposta prima sulla seconda del mese.
  await p.click('#modifica-' + I1 + ' summary'); await p.fill('#d-' + I1, dataP);
  await Promise.all([p.waitForNavigation(), p.click('#modifica-' + I1 + ' button[type=submit]')]); await p.waitForLoadState('networkidle');
  await p.goto(URLI + '&vista=piano', { waitUntil: 'networkidle' });
  const ordine1 = await p.locator('article.aic-idea').evaluateAll(xs => xs.map(x => +x.dataset.idea));
  const secondo = ordine1[1];
  await Promise.all([p.waitForNavigation(), p.click('#idea-' + secondo + ' button[value=su]')]); await p.waitForLoadState('networkidle');
  const ordine2 = await p.locator('article.aic-idea').evaluateAll(xs => xs.map(x => +x.dataset.idea));
  const lP = await strumento(K, 'list_article_ideas', {});
  ok('Sposta prima: l\'ordine nel mese cambia, e list_article_ideas (da scrivere) lo segue con la data prevista', ordine1.length === 2 && ordine2[0] === secondo && ordine2[1] === ordine1[0] && lP.dati.idee.map(x => x.idea_id).join() === ordine2.join() && lP.dati.idee.every(x => x.data_prevista === dataP) && lP.dati.totale === 2, [ordine1, ordine2, lP.dati && lP.dati.idee.map(x => x.idea_id)]);

  // === 8. create_draft con idea_id: stati sbagliati, coerenza, legame
  const a0 = nArticoli();
  const cScart = await strumento(K, 'create_draft', { tipo: 'articoli', titolo: 'Da idea scartata', idea_id: I3, testo: '<p>x</p>' });
  const cNo = await strumento(K, 'create_draft', { tipo: 'articoli', titolo: 'Da idea inesistente', idea_id: 99999999, testo: '<p>x</p>' });
  const cPag = await strumento(K, 'create_draft', { tipo: 'pagine', titolo: 'Pagina da idea', idea_id: I1, testo: '<p>x</p>' });
  const cTxt = await strumento(K, 'create_draft', { tipo: 'articoli', titolo: 'Idea come testo', idea_id: '1 OR 1=1', testo: '<p>x</p>' });
  ok('create_draft con un\'idea scartata, inesistente, su una pagina o con idea_id scritto come SQL: rifiutata, nessuna bozza', cScart.errore && cNo.errore && /non trovata/.test(cNo.dati.errore) && cPag.errore && cTxt.errore && nArticoli() === a0, [cScart.dati, cNo.dati, cPag.dati, cTxt.dati]);
  // Uno scostamento voluto: testo con il "noi", frasi lunghe, niente sottotitoli, niente link, lungo il triplo.
  const lunga = 'Noi della nostra azienda agricola abbiamo sempre pensato che la cura del terreno, quando viene fatta con attenzione e con materiali scelti con criterio, rappresenti il modo migliore per ottenere raccolti abbondanti e duraturi nel corso delle stagioni. ';
  const cd = await strumento(K, 'create_draft', { tipo: 'articoli', titolo: 'Il concime naturale per l\'orto di casa', idea_id: I1, testo: '<p>' + lunga.repeat(8) + '</p>', seo: { parola_chiave: 'concime naturale' } });
  const D = cd.dati || {};
  const scost = (D.coerenza && D.coerenza.scostamenti || []).map(x => x.misura);
  ok('create_draft con l\'idea approvata: bozza creata, mai pubblicata', !cd.errore && D.stato === 'bozza' && D.idea && D.idea.stato === 'scritta' && sql('SELECT status FROM {p}articles WHERE id = ' + (+D.id))[0].status === 'draft', cd.dati);
  ok('coerenza con il profilo: avvisi su persona, frase media, lunghezza, sottotitoli e link interni (la bozza è salvata lo stesso)', ['persona', 'frase media', 'lunghezza', 'sottotitoli', 'link interni'].every(m => scost.includes(m)) && D.coerenza.bozza.persona === 'noi' && /non errori/.test(D.coerenza.nota), D.coerenza);
  const legame = sql('SELECT stato, articolo_id FROM {p}ai_idee WHERE id = ' + (+I1))[0];
  ok('l\'idea passa a "scritta" con il link alla bozza', legame.stato === 'scritta' && +legame.articolo_id === D.id, legame);
  const cDue = await strumento(K, 'create_draft', { tipo: 'articoli', titolo: 'Seconda bozza della stessa idea', idea_id: I1, testo: '<p>x</p>' });
  ok('create_draft di nuovo con la stessa idea: rifiutata (usa update_draft), nessuna bozza in più', cDue.errore && /già scritta/.test(cDue.dati.errore) && nArticoli() === a0 + 1, cDue.dati);
  const buona = '<p>Vuoi un orto più forte? Usa il concime naturale. Lo fai in casa. Costa poco.</p><h2>Cosa ti serve</h2><p>Prendi gli scarti di cucina. Mettili in un angolo. Girali ogni settimana. Leggi anche <a href="/ai-prova-stile-5/">il compost</a>.</p><ul><li>Bucce</li><li>Fondi di caffè</li></ul><h2>Come lo usi</h2><p>Spargilo a fine inverno. Non esagerare. Le piante ti ringraziano. Hai dubbi? Scrivimi.</p><p>Ora tocca a te. Prova e raccontami.</p>';
  const ud = await strumento(K, 'update_draft', { tipo: 'articoli', id: D.id, idea_id: I1, testo: buona, seo: { domande_frequenti: [{ domanda: 'Quando si usa il concime naturale?', risposta: 'A fine inverno.' }] } });
  const scost2 = (ud.dati && ud.dati.coerenza && ud.dati.coerenza.scostamenti || []).map(x => x.misura);
  ok('update_draft nello stile del sito: niente avvisi su persona, frase media, sottotitoli e link interni', !ud.errore && ud.dati.coerenza && ud.dati.coerenza.bozza.persona === 'tu' && !['persona', 'frase media', 'sottotitoli', 'link interni'].some(m => scost2.includes(m)), ud.dati);
  await p.goto(URLI + '&vista=piano', { waitUntil: 'networkidle' });
  ok('Piano editoriale: l\'idea scritta mostra "Scritta (in bozza)" e il link all\'editor della bozza', /Scritta \(in bozza\)/.test(await p.locator('#idea-' + I1 + ' h3').innerText()) && await p.locator('#idea-' + I1 + ' a[href*="editor.php?tipo=articoli&id=' + D.id + '"]').count() === 1);
  const lS = await strumento(K, 'list_article_ideas', { stato: 'scritta' });
  ok('list_article_ideas (scritta): l\'idea con la bozza, ancora in bozza', lS.dati.totale === 1 && lS.dati.idee[0].bozza.id === D.id && lS.dati.idee[0].bozza.stato === 'bozza', lS.dati);
  const sitoArt = await fetch(B + '/' + (sql('SELECT slug FROM {p}articles WHERE id = ' + (+D.id))[0] || {}).slug + '/');
  ok('la bozza non è sul sito pubblico (404)', sitoArt.status === 404, sitoArt.status);

  // === 9. Telefono
  await p.setViewportSize({ width: 390, height: 800 });
  await p.goto(URLI + '&vista=tutte', { waitUntil: 'networkidle' });
  const w1 = await largo(p);
  await p.goto(URLI + '&vista=piano', { waitUntil: 'networkidle' });
  const w2 = await largo(p);
  if (FOTO) await p.screenshot({ path: path.join(FOTO, 'ai-idee-390.png'), fullPage: true });
  await p.goto(URLS, { waitUntil: 'networkidle' });
  const w3 = await largo(p);
  ok('a 390 px le pagine Idee per articoli (tutte e piano) e Stile del sito non scorrono di lato', [w1, w2, w3].every(w => w[0] <= w[1] + 1), [w1, w2, w3]);
  const piccoli = await p.locator('#versioni button, #profilo button').evaluateAll(xs => xs.filter(x => x.getBoundingClientRect().height < 44).length);
  ok('a 390 px i pulsanti di Stile del sito sono alti almeno 44 px', piccoli === 0, piccoli);
  ok('nessun errore JavaScript nel pannello', errJs.length === 0, errJs);
  await br.close();

  // === 10. Tempi con 300 articoli in più
  if (!process.env.SENZA_MOLTE) {
    cli('stile-molti', 300);
    const t2 = Date.now(); const big = await strumento(K, 'analyze_style', {}); const tBig = Date.now() - t2;
    console.log('    tempi: analyze_style ' + tBig + ' ms');
    ok('con 300 articoli in più analyze_style risponde in meno di 3 secondi (gli ultimi 50)', !big.errore && big.dati.articoli_misurati === 50 && big.dati.articoli_pubblicati >= 311 && tBig < 3000, [tBig, big.dati && big.dati.articoli_misurati]);
    const t3 = Date.now(); const bigT = await strumento(K, 'suggest_topics', {}); const tT = Date.now() - t3;
    console.log('    tempi: suggest_topics ' + tT + ' ms');
    ok('con 300 articoli in più suggest_topics li legge tutti in meno di 5 secondi', !bigT.errore && bigT.dati.articoli_letti >= 311 && tT < 5000, [tT, bigT.dati && bigT.dati.articoli_letti]);
  }
  cli('stile-pulisci'); cli('pulisci'); cli('tentativi');
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
