// Prove di attacco dell'addon Test e questionari (2.22.0). Va lanciata dopo prova-test.js, sullo stesso sito da buttare
// (l'addon acceso, i test "Prova punteggio", "Prova aree", "Prova profili" pubblicati, il server di posta finto acceso).
//   A pannello: anonimo, redattore, CSRF, Origin di un altro sito
//   B invii del test: domande che non esistono, valori fuori scala, tipi sbagliati, test in bozza, codice del modulo
//     falso, di un altro test, troppo presto, campo esca, Origin, invii a raffica, richieste leggere sbagliate
//   C XSS in ogni campo di testo (dal pannello, POST veri con il token): sul sito e nel pannello escono come testo
//   D link del risultato falsificato o di un altro test; email del risultato senza permesso, a raffica
//   E SQL injection nei parametri; F CSV con le formule
// uso: node prove/attacchi-test.js <base> <cartella del CMS installato> <cartella posta>
const { chromium } = require('playwright');
const path = require('path');
const { execFileSync } = require('child_process');
const C = require('./addon-b-comune.js');
const { ok } = C;
const pag = async (br, err, o) => { const p = await C.pagina(br, err, o); p.setDefaultTimeout(15000); return p; };
const [B, CMS, POSTA] = process.argv.slice(2);
const PAG = (p, q = '') => B + '/admin/addon-pagina.php?a=test&p=' + p + q;
const cli = (...a) => { const o = execFileSync('php', [path.join(__dirname, 'test-cli.php'), CMS, ...a.map(String)], { encoding: 'utf8' }); return JSON.parse(o.trim().split('\n').pop()); };
const sql = q => cli('sql', q);
const n = q => +sql(q)[0].n;
const dorme = ms => new Promise(r => setTimeout(r, ms));
const b64 = o => Buffer.from(JSON.stringify(o)).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
const XSS = '<img src=x onerror=alert(1)><script>alert(2)</script>"\'>';

(async () => {
  const br = await chromium.launch();
  const err = [];
  const a = await pag(br, err);
  let alert = 0;
  a.on('dialog', d => { alert++; d.dismiss(); });
  await C.entra(a, B);
  cli('pulisci_limiti');
  const T = Object.fromEntries(sql('SELECT id, slug, dati FROM {p}test_test').map(r => [r.slug, { id: +r.id, d: JSON.parse(r.dati) }]));
  const A = T['prova-punteggio'];
  ok('ci sono i test della prova (prova-test.js prima)', !!A && !!T['prova-aree'], Object.keys(T));
  const qA = A.d.domande.map(q => q.k);
  const buone = { [qA[0]]: 's3', [qA[1]]: 's1', [qA[2]]: 's2', [qA[3]]: 'no', [qA[5]]: '5' };
  const form = (obj, extra = {}) => { const f = { ...extra }; for (const [k, v] of Object.entries(obj)) f[`r[${k}]`] = v; return f; };
  const gettone = async (slug) => (await (await fetch(B + '/fai-il-test/' + slug + '/')).text()).match(/name="gettone" value="([^"]+)"/)?.[1];
  const invia = async (slug, campi, opz = {}) => fetch(B + '/fai-il-test/' + slug + '/invia/', { method: 'POST', redirect: 'manual', headers: { 'content-type': 'application/x-www-form-urlencoded', ...(opz.origin ? { origin: opz.origin } : {}) }, body: new URLSearchParams(campi).toString() });
  const risultati = () => n(`SELECT COUNT(*) n FROM {p}test_risultati WHERE test_id = ${A.id}`);

  // ================= A. Pannello
  let alLogin = 0;
  const pagine = ['test', 'statistiche', 'test&id=' + A.id + '&passo=2', 'statistiche&id=' + A.id + '&csv=1'];
  for (const p of pagine) { const r = await fetch(PAG(p), { redirect: 'manual' }); if (r.status >= 300 && r.status < 400 && /login\.php/.test(r.headers.get('location') || '')) alLogin++; }
  ok(`A1 anonimo: le ${pagine.length} pagine del pannello (CSV compreso) portano al login`, alLogin === pagine.length, alLogin);
  const csvAnon = await (await fetch(PAG('statistiche', `&id=${A.id}&csv=1`))).text();
  ok('A1 anonimo: il CSV non esce', !/Riepilogo/.test(csvAnon));
  await a.goto(B + '/admin/utenti.php', { waitUntil: 'networkidle' });
  await a.fill('form.sc-corpo [name=username]', 'red_test'); await a.fill('form.sc-corpo [name=email]', 'red_test@esempio.it');
  await a.selectOption('form.sc-corpo [name=role]', 'editor'); await a.fill('form.sc-corpo [name=password]', 'Redattore-Prova-2026'); await a.fill('form.sc-corpo [name=password2]', 'Redattore-Prova-2026');
  await Promise.all([a.waitForNavigation(), a.click('form.sc-corpo button.btn-primary')]);
  const red = await pag(br, err);
  await C.entra(red, B, 'red_test', 'Redattore-Prova-2026');
  let bloccate = 0;
  for (const p of pagine) { const r = await red.goto(PAG(p)); if (!/addon-pagina\.php/.test(red.url()) || r.status() >= 400) bloccate++; }
  ok('A2 redattore: non apre nessuna pagina dei test', bloccate === pagine.length, bloccate);
  ok('A2 redattore: nel menu non c\'è Test', await red.locator('nav.admin-nav a', { hasText: /^\s*Test\s*$/ }).count() === 0);
  const tokRed = await red.getAttribute('meta[name=pn-csrf]', 'content').catch(() => '');
  const nTest = n('SELECT COUNT(*) n FROM {p}test_test');
  await red.request.post(PAG('test'), { form: { _csrf: tokRed, azione: 'crea', tipo: 'punteggio', nome: 'Dal redattore' }, maxRedirects: 0 });
  await red.request.post(PAG('test'), { form: { _csrf: tokRed, azione: 'elimina', id: String(A.id) }, maxRedirects: 0 });
  ok('A2 redattore: un POST per creare o eliminare un test non passa', n('SELECT COUNT(*) n FROM {p}test_test') === nTest && n(`SELECT COUNT(*) n FROM {p}test_test WHERE id = ${A.id}`) === 1);
  const edRed = await red.goto(B + '/admin/editor.php?tipo=test_presentazione&id=' + A.id);
  ok('A2 redattore: l\'editor della presentazione gli è chiuso (403)', edRed.status() === 403 || !/editor\.php/.test(red.url()), edRed.status());
  // CSRF: l'amministratore, ma senza token o da un altro sito.
  await a.request.post(PAG('test'), { form: { azione: 'elimina', id: String(A.id) }, maxRedirects: 0 });
  await a.request.post(PAG('test'), { form: { azione: 'crea', tipo: 'punteggio', nome: 'Senza token' }, maxRedirects: 0 });
  ok('A3 CSRF: senza il token niente si crea e niente si elimina', n('SELECT COUNT(*) n FROM {p}test_test') === nTest && n(`SELECT COUNT(*) n FROM {p}test_test WHERE id = ${A.id}`) === 1);
  await a.goto(PAG('test'), { waitUntil: 'networkidle' });
  const tok = await a.getAttribute('meta[name=pn-csrf]', 'content');
  await a.request.post(PAG('test'), { form: { _csrf: tok, azione: 'pubblica', id: String(A.id) }, headers: { origin: 'https://cattivo.example' }, maxRedirects: 0 });
  await a.request.post(PAG('test'), { form: { _csrf: tok, azione: 'bozza', id: String(A.id) }, headers: { origin: 'https://cattivo.example' }, maxRedirects: 0 });
  ok('A4 con il token ma da un altro sito (Origin): il test resta pubblicato', sql(`SELECT stato FROM {p}test_test WHERE id = ${A.id}`)[0].stato === 'pubblicato');

  // ================= B. Invii del test
  let g = await gettone('prova-punteggio'); await dorme(3200);
  const prima = risultati();
  let r = await invia('prova-punteggio', form({ ...buone, zzz999: 's1', 'd<script>': 'x' }, { gettone: g }));
  ok('B1 domande che non esistono: si scartano, il risultato si calcola sulle vere (303)', r.status === 303 && risultati() === prima + 1, r.status);
  for (const [nome, val] of [['scala fuori scala', ['0', 's9']], ['scala negativa', ['0', 's-1']], ['cursore 11', ['5', '11']], ['cursore -3', ['5', '-3']], ['sì/no inventato', ['3', 'forse']], ['cursore decimale', ['5', '7.5']]]) {
    const q = { ...buone }; q[qA[+val[0]]] = val[1];
    g = await gettone('prova-punteggio'); await dorme(3100);
    r = await invia('prova-punteggio', form(q, { gettone: g }));
    ok(`B1 valore fuori scala (${nome}): 422, nessun risultato registrato`, r.status === 422 && /non va bene/.test(await r.text()) && risultati() === prima + 1, r.status);
  }
  g = await gettone('prova-punteggio'); await dorme(3100);
  const corpoArr = new URLSearchParams(form(buone, { gettone: g })); corpoArr.delete(`r[${qA[0]}]`); corpoArr.append(`r[${qA[0]}][]`, 's1');
  r = await fetch(B + '/fai-il-test/prova-punteggio/invia/', { method: 'POST', redirect: 'manual', headers: { 'content-type': 'application/x-www-form-urlencoded' }, body: corpoArr.toString() });
  ok('B1 una risposta come elenco al posto del testo: 422 (manca la risposta)', r.status === 422 && risultati() === prima + 1, r.status);
  r = await invia('prova-punteggio', form(buone, { gettone: '9999999999.' + 'a'.repeat(32) }));
  ok('B2 codice del modulo inventato: 400', r.status === 400 && risultati() === prima + 1);
  const gB = await gettone('prova-aree'); await dorme(3100);
  r = await invia('prova-punteggio', form(buone, { gettone: gB }));
  ok('B2 codice del modulo di un altro test: 400', r.status === 400 && risultati() === prima + 1);
  r = await invia('prova-punteggio', form(buone, { gettone: await gettone('prova-punteggio') }));
  ok('B2 mandato subito (meno di 3 secondi, come un robot): 429 e nessun risultato', r.status === 429 && /in fretta/.test(await r.text()) && risultati() === prima + 1, r.status);
  r = await invia('prova-punteggio', form(buone, {}));
  ok('B2 senza il codice del modulo: 400', r.status === 400);
  g = await gettone('prova-punteggio'); await dorme(3100);
  r = await invia('prova-punteggio', form(buone, { gettone: g, sito_web: 'http://spam.example' }));
  ok('B3 campo esca compilato: 400', r.status === 400 && risultati() === prima + 1);
  r = await invia('prova-punteggio', form(buone, { gettone: g }), { origin: 'https://cattivo.example' });
  ok('B3 da un altro sito (Origin): 400', r.status === 400 && risultati() === prima + 1);
  // Un test in bozza: la pagina e l'invio non esistono (404), anche con un codice del modulo vero.
  await a.request.post(PAG('test'), { form: { _csrf: tok, azione: 'duplica', id: String(A.id) }, maxRedirects: 0 });
  const bozza = sql(`SELECT id, slug FROM {p}test_test WHERE stato = 'bozza' ORDER BY id DESC LIMIT 1`)[0];
  ok('B4 la pagina di un test in bozza: 404', (await fetch(B + '/fai-il-test/' + bozza.slug + '/')).status === 404);
  r = await invia(bozza.slug, form(buone, { gettone: g }));
  ok('B4 un invio a un test in bozza: 404, niente registrato', r.status === 404 && n(`SELECT COUNT(*) n FROM {p}test_risultati WHERE test_id = ${bozza.id}`) === 0, r.status);
  // Invii a raffica: al ventesimo nell'ora la connessione si ferma.
  cli('pulisci_limiti');
  let fermato = 0, passati = 0;
  g = await gettone('prova-punteggio'); await dorme(3100);
  for (let i = 0; i < 24; i++) { r = await invia('prova-punteggio', form(buone, { gettone: g })); if (r.status === 429) fermato++; else if (r.status === 303) passati++; }
  ok('B5 invii a raffica: 20 passano, gli altri 429 "troppi"', passati === 20 && fermato === 4, [passati, fermato]);
  cli('pulisci_limiti');
  // Le richieste leggere: niente righe per test in bozza, codici sbagliati, domande inventate.
  const nT = n('SELECT COUNT(*) n FROM {p}test_tentativi');
  const passo = (c) => fetch(B + '/test-api/passo/', { method: 'POST', body: new URLSearchParams(c) });
  for (const c of [{ t: String(bozza.id), c: 'abcdefghijklmnop' }, { t: String(A.id), c: 'CORTO' }, { t: String(A.id), c: "abc' OR 1=1 --xx" }, { t: '1 OR 1=1', c: 'abcdefghijklmnoq' }]) await passo(c);
  const rp = await passo({ t: String(A.id), c: 'qwertyuiopasdfgh', d: '<script>', n: '99999' });
  ok('B6 richieste leggere sbagliate: nessuna riga (204), una buona con una domanda inventata conta senza la domanda', rp.status === 204 && n('SELECT COUNT(*) n FROM {p}test_tentativi') === nT + 1 && sql("SELECT ultima FROM {p}test_tentativi WHERE codice = 'qwertyuiopasdfgh'")[0].ultima === '');
  ok('B6 la richiesta leggera in GET non fa niente (va al sito: 404)', (await fetch(B + '/test-api/passo/')).status === 404);

  // ================= C. XSS in ogni campo, scritto dal pannello con POST veri
  await a.request.post(PAG('test'), { form: { _csrf: tok, azione: 'crea', tipo: 'aree', nome: 'XSS ' + XSS }, maxRedirects: 0 });
  const X = sql(`SELECT id, slug FROM {p}test_test ORDER BY id DESC LIMIT 1`)[0];
  const id = String(X.id);
  const post = (passo, campi) => a.request.post(PAG('test', `&id=${id}&passo=${passo}`), { form: { _csrf: tok, azione: 'salva', id, passo: String(passo), vai: 'resta', ...campi }, maxRedirects: 0 });
  await post(1, { nome: 'XSS ' + XSS, slug: '"><script>alert(3)</script>', durata: '2', presentazione: XSS, tipo: 'aree' });
  await post(2, { dati_json: JSON.stringify({ scala: [{ t: 'Mai' + XSS, v: 0 }, { t: 'Sempre', v: 4 }], aree: [{ k: 'ax', nome: '=HYPERLINK("http://cattivo.example","clic")' + XSS }],
    domande: [{ k: 'dx', testo: 'Domanda ' + XSS, aiuto: 'Aiuto ' + XSS, tipo: 'scelta', area: 'ax', peso: 1, opzioni: [{ k: 'o1', t: 'Uno ' + XSS, punti: 0 }, { k: 'o2', t: 'Due', punti: 4 }] },
      { k: 'dy', testo: '@SOMMA(1;2) seconda', tipo: 'scala', area: 'ax', condizione: { domanda: 'dx', valori: ['o2', '"><script>'] } }] }) });
  await post(3, { fasce_json: JSON.stringify({ fasce: [{ k: 'f1', nome: '=1+1' + XSS, da: 0, a: 100 }], aree: { ax: [{ k: 'g1', nome: '-2+3' + XSS, da: 0, a: 100 }] } }) });
  await post(4, { 'testi[totale|f1][testo]': '<p>Testo <b>buono</b></p>' + XSS + '<a href="javascript:alert(4)">link</a><iframe src="//cattivo.example"></iframe>', 'testi[totale|f1][pulsante_testo]': 'Clic ' + XSS, 'testi[totale|f1][pulsante_link]': 'javascript:alert(5)',
    'testi[area|ax|g1][testo]': XSS, regole_json: JSON.stringify({ regole: [{ k: 'r1', nome: 'Regola ' + XSS, modo: 'e', condizioni: [{ cosa: 'area:ax', fascia: 'g1' }], testo: XSS, pulsante_testo: 'Vai', pulsante_link: 'javascript:alert(6)' }] }), avvertenza: 'Avvertenza ' + XSS });
  await post(5, { email: '1', consenso: 'Consenso ' + XSS, iscrizione: 'Iscriviti ' + XSS, lista: '0' });
  await a.request.post(PAG('test'), { form: { _csrf: tok, azione: 'pubblica', id }, maxRedirects: 0 });
  const XR = sql(`SELECT slug, stato, dati FROM {p}test_test WHERE id = ${id}`)[0];
  ok('C0 il test con l\'XSS è salvato e pubblicato, con un indirizzo pulito', XR.stato === 'pubblicato' && /^[a-z0-9-]+$/.test(XR.slug), XR.slug);
  const dx = JSON.parse(XR.dati);
  ok('C0 nei dati salvati: niente <script>, niente onerror, niente javascript: nei testi ricchi e nei link', !/<script|onerror|javascript:/i.test(JSON.stringify([dx.fasce[0].testo, dx.regole[0].testo, dx.aree[0].fasce[0].testo, dx.fasce[0].pulsante_link, dx.regole[0].pulsante_link])) && /<b>buono<\/b>/.test(dx.fasce[0].testo));
  ok('C0 la condizione con un valore inventato tiene solo quello vero', JSON.stringify(dx.domande[1].condizione) === '{"domanda":"dx","valori":["o2"]}');
  const v = await pag(br, err);
  v.on('dialog', d => { alert++; d.dismiss(); });
  const controlla = async (p, dove) => {
    // I dati in <script type="application/json"> non si eseguono: contano solo se dentro c'è un < vero (che chiuderebbe il blocco).
    const s = await p.evaluate(() => ({ script: [...document.scripts].filter(x => x.type !== 'application/json' && /alert\(/.test(x.textContent)).length + [...document.scripts].filter(x => x.type === 'application/json' && /</.test(x.textContent)).length, img: document.querySelectorAll('img[onerror],[onerror]').length, js: document.querySelectorAll('a[href^="javascript"]').length, iframe: document.querySelectorAll('iframe[src*="cattivo"]').length }));
    ok(`C ${dove}: nessuno script iniettato, nessun onerror, nessun link javascript:, nessun iframe`, s.script === 0 && s.img === 0 && s.js === 0 && s.iframe === 0, s);
  };
  await v.goto(B + '/fai-il-test/' + XR.slug + '/', { waitUntil: 'networkidle' });
  ok('C sito: il nome con l\'HTML esce come testo nel titolo', (await v.locator('h1').first().innerText()).includes('<img src=x onerror=alert(1)>'));
  await controlla(v, 'pagina del test');
  await v.click('#tq-inizia');
  await v.locator('label.tq-opz', { hasText: 'Due' }).click(); await v.click('[data-avanti]');
  await v.locator('.tq-domanda:not([hidden]) label.tq-opz').last().click(); await v.click('[data-avanti]');
  await dorme(3200);
  await Promise.all([v.waitForNavigation(), v.click('#tq-fine button[type=submit]')]);
  ok('C sito: risultato con la regola, titolo come testo', (await v.locator('h1').innerText()).includes('Regola <img'));
  await controlla(v, 'pagina del risultato (testo, regola, aree, grafico, pulsante, consenso)');
  for (const [p, nome] of [[PAG('test'), 'elenco del pannello'], [PAG('test', `&id=${id}&passo=1`), 'passo 1'], [PAG('test', `&id=${id}&passo=2`), 'passo 2'], [PAG('test', `&id=${id}&passo=3`), 'passo 3'], [PAG('test', `&id=${id}&passo=4`), 'passo 4'], [PAG('test', `&id=${id}&passo=5`), 'passo 5'], [PAG('statistiche', `&id=${id}`), 'statistiche']]) {
    await a.goto(p, { waitUntil: 'networkidle' });
    await controlla(a, 'pannello, ' + nome);
  }
  ok('C nessun alert() partito, in nessuna pagina', alert === 0, alert);

  // ================= D. Link del risultato e email
  const eA = b64({ t: A.id, g: '20261103', o: 99, f: 'alto' });
  ok('D1 un esito scritto a mano senza firma: 404', (await fetch(B + '/fai-il-test/prova-punteggio/risultato/?e=' + eA + '&f=' + '0'.repeat(32))).status === 404);
  const firmato = cli('esito_firmato', A.id, JSON.stringify({ t: A.id, g: '20261103', o: 99, f: 'alto' }));
  ok('D1 un esito firmato bene si apre (controllo della prova)', (await fetch(B + '/fai-il-test/prova-punteggio/risultato/?e=' + firmato.e + '&f=' + firmato.f)).status === 200);
  ok('D1 lo stesso link su un altro test: 404', (await fetch(B + '/fai-il-test/prova-aree/risultato/?e=' + firmato.e + '&f=' + firmato.f)).status === 404);
  ok('D1 esito che non è JSON, troppo lungo, con caratteri strani: 404', (await fetch(B + '/fai-il-test/prova-punteggio/risultato/?e=bm9u&f=' + 'a'.repeat(32))).status === 404 && (await fetch(B + '/fai-il-test/prova-punteggio/risultato/?e=' + 'A'.repeat(4000) + '&f=x')).status === 404 && (await fetch(B + '/fai-il-test/prova-punteggio/risultato/?e[]=1&f[]=2')).status === 404);
  const nPosta = C.posta(POSTA).length;
  const email = (campi) => fetch(B + '/fai-il-test/prova-punteggio/email/', { method: 'POST', redirect: 'manual', headers: { 'content-type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ e: firmato.e, f: firmato.f, email: 'vittima@tq-prova.it', consenso: '1', ...campi }).toString() });
  r = await email({});
  ok('D2 email del risultato senza il permesso (k) del dopo-test, cioè dal link condiviso: rifiutata', r.status === 303 && /errore=robot/.test(r.headers.get('location') || ''), r.headers.get('location'));
  r = await email({ k: '9999999999.' + 'b'.repeat(32) });
  ok('D2 permesso inventato: rifiutato', /errore=robot/.test(r.headers.get('location') || ''));
  r = await email({ k: '1000000000.' + 'b'.repeat(32) });
  ok('D2 permesso scaduto: rifiutato', /errore=robot/.test(r.headers.get('location') || ''));
  await dorme(1500);
  ok('D2 nessuna email partita', C.posta(POSTA).length === nPosta);

  // ================= E. SQL injection nei parametri
  const tab = n("SELECT COUNT(*) n FROM information_schema.tables WHERE table_name LIKE '%test\\_%'");
  const iniez = [B + "/fai-il-test/prova-punteggio'%20OR%201=1--/", B + '/fai-il-test/x%27%3BDROP%20TABLE%20cms_test_test--/', PAG('statistiche', "&id=1%20OR%201=1&giorni=1;DROP"), PAG('test', "&id=1'%20UNION%20SELECT%201--&passo=9"),
    B + "/fai-il-test/prova-punteggio/risultato/?e=' OR 1=1--&f=1"];
  let ko500 = 0;
  for (const u of iniez) { const x = await a.request.get(u); if (x.status() >= 500) ko500++; }
  g = await gettone('prova-punteggio'); await dorme(3100);
  r = await invia('prova-punteggio', form({ ...buone, [qA[0]]: "s1' OR '1'='1", ["x' OR 1=1"]: '1' }, { gettone: g, tentativo: "'; DROP TABLE x;--" }));
  ok('E SQL injection nei parametri: nessun 500, tabelle intatte, valore rifiutato (422)', ko500 === 0 && r.status === 422 && n("SELECT COUNT(*) n FROM information_schema.tables WHERE table_name LIKE '%test\\_%'") === tab && n('SELECT COUNT(*) n FROM {p}test_test') >= 4, [ko500, r.status]);

  // ================= F. CSV con le formule
  const csv = await (await a.request.get(PAG('statistiche', `&id=${id}&csv=1`))).text();
  const celle = csv.replace(/^﻿/, '').trim().split(/\r?\n/).flatMap(riga => (riga.match(/("([^"]|"")*"|[^;]*)(;|$)/g) || []).map(c => c.replace(/;$/, '').replace(/^"|"$/g, '').replace(/""/g, '"')));
  const formule = celle.filter(c => /^[=+\-@]/.test(c) && !/^-?\d+([.,]\d+)?$/.test(c));
  ok('F CSV: nessuna cella comincia con = + - @ (i nomi con le formule hanno l\'apice davanti)', formule.length === 0 && celle.some(c => c.startsWith("'=HYPERLINK")), formule.slice(0, 3));

  cli('pulisci_limiti');
  C.fine(err);
  await br.close();
})().catch(e => { console.log('ERRORE ' + e.stack); process.exit(1); }); // exit: il browser aperto terrebbe vivo il processo
