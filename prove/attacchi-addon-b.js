// Gli attacchi agli addon Prenotazioni, Eventi con iscrizione e Raccolta recensioni (2.0.0). Ogni OK è un attacco respinto.
// Su un sito nuovo (kit Attività) con il server di posta finto acceso: la prova accende i tre addon dal pannello e prepara
// i suoi dati dalla riga di comando (prove/addon-b-cli.php). Mai contro un sito vero.
// Gruppi: A prenotazioni (doppia prenotazione in parallelo, posti che non esistono, captcha, campo esca, origine, XSS,
// SQL injection, header injection, troppe richieste, link manomessi e scaduti, annullare quella di un altro, CSRF),
// B eventi (posti oltre il massimo, iscrizioni in parallelo, bozze e chiuse, link, CSRF), C recensioni (due volte in
// parallelo, link manomessi, scaduti, non partiti, stelle fuori scala, XSS, CSRF), D pannello (senza accesso, redattore,
// file degli addon dal web).
// uso: node prove/attacchi-addon-b.js <base> <cartella del CMS installato> <porta smtp> <cartella posta>
const { chromium } = require('playwright');
const crypto = require('crypto');
const C = require('./addon-b-comune.js');
const { ok } = C;
const [B, CMS, SMTP, POSTA] = process.argv.slice(2);
const sql = q => C.cli(CMS, 'sql', q);
const hmac = (s, k) => crypto.createHmac('sha256', k).update(s).digest('hex').slice(0, 40);
const giorno = n => { const d = new Date(Date.now() + n * 86400000); const z = x => String(x).padStart(2, '0'); return `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`; };

async function captcha() {
  const s = await (await fetch(B + '/api/captcha.php')).json();
  for (let n = 0; n <= s.max; n++) if (crypto.createHash('sha256').update(s.sale + n).digest('hex') === s.sfida) return Buffer.from(JSON.stringify({ ...s, n })).toString('base64');
  return '';
}
const invia = (url, campi, intest = {}) => fetch(B + url, { method: 'POST', body: new URLSearchParams(campi), redirect: 'manual', headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...intest } });
const codice = r => decodeURIComponent(((r.headers.get('location') || '').match(/[?&]modulo=([^&#]+)/) || [])[1] || '');

(async () => {
  const br = await chromium.launch();
  const err = [];
  const a = await C.pagina(br, err);
  await C.entra(a, B);
  await C.postaFinta(a, B, SMTP, 'attivita@esempio.it');
  for (const x of ['prenotazioni', 'eventi-iscrizione', 'recensioni']) await C.addon(a, B, x, true);
  const segreto = sql("SELECT setting_value v FROM {p}settings WHERE setting_key = 'moduli_segreto'")[0]?.v || (await captcha(), sql("SELECT setting_value v FROM {p}settings WHERE setting_key = 'moduli_segreto'")[0].v);
  const pulisciIp = () => { sql("UPDATE {p}pren_prenotazioni SET ip = ''"); sql("UPDATE {p}evi_iscrizioni SET ip = ''"); };

  // ================= A. Prenotazioni
  sql("INSERT INTO {p}pren_risorse (nome, orari, ferie, creato) VALUES ('Anna', '{\"lun\":\"8:00-20:00\",\"mar\":\"8:00-20:00\",\"mer\":\"8:00-20:00\",\"gio\":\"8:00-20:00\",\"ven\":\"8:00-20:00\",\"sab\":\"8:00-20:00\",\"dom\":\"8:00-20:00\"}', '[]', NOW())");
  sql("INSERT INTO {p}pren_servizi (nome, durata, creato) VALUES ('Taglio', 30, NOW())");
  sql("INSERT INTO {p}pren_servizi (nome, durata, attivo, creato) VALUES ('Spento', 30, 0, NOW())");
  const ids = sql("SELECT (SELECT MAX(id) FROM {p}pren_risorse) r, (SELECT id FROM {p}pren_servizi WHERE nome = 'Taglio') s, (SELECT id FROM {p}pren_servizi WHERE nome = 'Spento') spento")[0];
  const posti = await (await fetch(`${B}/prenotazione/posti/?s=${ids.s}&r=${ids.r}&da=${giorno(3)}`)).json();
  const [g, o1, o2, o3] = [posti.giorno, posti.posti[0].ora, posti.posti[1].ora, posti.posti[2].ora];
  const base = (extra = {}) => ({ servizio: String(ids.s), risorsa: String(ids.r), giorno: g, ora: o1, nome: 'Mario Rossi', email: 'mario@cliente.it', telefono: '3331234567', privacy: '1', pagina: '/prenota/', ancora: 'pren', ...extra });
  // A1. Lo stesso posto chiesto otto volte insieme.
  const otto = await Promise.all(Array.from({ length: 8 }, async (_, k) => invia('/prenotazione/invia/', base({ email: `doppia${k}@cliente.it`, captcha: await captcha() }))));
  const fatte = sql(`SELECT COUNT(*) n FROM {p}pren_prenotazioni WHERE inizio = '${g} ${o1.padStart(5, '0')}:00' AND stato IN ('confermata','da_confermare')`)[0].n;
  ok('A1 otto richieste insieme per lo stesso posto: una sola prenotazione, le altre "posto preso"', +fatte === 1 && otto.filter(r => codice(r) === 'pren-posto').length === 7, [fatte, otto.map(codice)]);
  pulisciIp();
  // Anche "chi è libero" (risorsa 0), con un'altra ora.
  const otto2 = await Promise.all(Array.from({ length: 6 }, async (_, k) => invia('/prenotazione/invia/', base({ risorsa: '0', ora: o2, email: `libero${k}@cliente.it`, captcha: await captcha() }))));
  ok('A1b sei richieste insieme con "chi è libero": una sola', +sql(`SELECT COUNT(*) n FROM {p}pren_prenotazioni WHERE inizio = '${g} ${o2.padStart(5, '0')}:00'`)[0].n === 1 && otto2.filter(r => codice(r) === 'pren-posto').length === 5, otto2.map(codice));
  pulisciIp();
  const prima = +sql('SELECT COUNT(*) n FROM {p}pren_prenotazioni')[0].n;
  const casi = [
    ['A2 un\'ora fuori dalla griglia (3:17)', base({ ora: '3:17' }), 'pren-posto'],
    ['A2 un giorno passato', base({ giorno: giorno(-2) }), 'pren-posto'],
    ['A2 oltre i 60 giorni', base({ giorno: giorno(90) }), 'pren-posto'],
    ['A2 una data che non esiste', base({ giorno: '2027-02-30' }), 'pren-posto'],
    ['A3 un servizio spento', base({ servizio: String(ids.spento) }), 'pren-servizio'],
    ['A3 un servizio che non c\'è', base({ servizio: '99999' }), 'pren-servizio'],
    ['A9 SQL injection nel servizio', base({ servizio: "1 OR 1=1" }), 'pren-servizio'],
    ['A9 SQL injection nel giorno', base({ giorno: "2026-01-01' OR '1'='1" }), 'pren-ora'],
    ['A4 senza captcha', base({ captcha: '' }), 'pren-captcha'],
    ['A4 captcha inventato', base({ captcha: Buffer.from('{"sale":"aaaaaaaaaaaaaaaaaaaaaaaa","sfida":"x","scade":9999999999,"max":1,"firma":"x","n":1}').toString('base64') }), 'pren-captcha'],
    ['A5 il campo esca riempito', base({ sito_web: 'http://spam.example' }), 'pren-robot'],
    ['A7 senza consenso privacy', base({ privacy: '' }), 'pren-privacy'],
    ['A10 header injection nell\'email', base({ email: 'a@b.it\r\nBcc: spia@cattivo.it' }), 'pren-email'],
    ['A10 email senza dominio vero', base({ email: 'mario@localhost' }), 'pren-email'],
  ];
  for (const [t, campi, atteso] of casi) {
    if (!('captcha' in campi)) campi.captcha = await captcha();
    const r = await invia('/prenotazione/invia/', campi);
    ok(t + ': respinto (' + atteso + ')', codice(r) === atteso, codice(r));
  }
  const cc = await captcha();
  await invia('/prenotazione/invia/', base({ ora: o3, email: 'riuso1@cliente.it', captcha: cc }));
  const riuso = await invia('/prenotazione/invia/', base({ ora: posti.posti[4].ora, email: 'riuso2@cliente.it', captcha: cc }));
  ok('A4 lo stesso captcha due volte: la seconda no', codice(riuso) === 'pren-captcha', codice(riuso));
  const orig = await invia('/prenotazione/invia/', { ...base({ ora: posti.posti[5].ora, email: 'origine@cliente.it' }), captcha: await captcha() }, { Origin: 'https://cattivo.example' });
  ok('A6 una richiesta da un altro sito (Origin): respinta', codice(orig) === 'pren-robot', codice(orig));
  ok('nessuna prenotazione in più dai casi respinti', +sql('SELECT COUNT(*) n FROM {p}pren_prenotazioni')[0].n === prima + 1);
  // A8 XSS, A9 nome con apici, A10 a capo nel nome.
  pulisciIp();
  let quante = C.posta(POSTA).length;
  const xss = await invia('/prenotazione/invia/', base({ ora: posti.posti[6].ora, nome: '<script>alert(1)</script>"><img src=x onerror=alert(2)>\' OR \'1\'=\'1\r\nBcc: spia@cattivo.it', email: 'xss@cliente.it', note: '<svg onload=alert(3)>', captcha: await captcha() }));
  const pagina = xss.headers.get('location') || '';
  ok('A8 prenotazione con codice nel nome: accettata come testo', /\/prenotazione\/gestisci\/\?p=\d+/.test(pagina), pagina);
  const hp = await (await fetch(pagina)).text();
  ok('A8 la pagina personale lo mostra come testo', !/<script>alert\(1\)/.test(hp) && !/<img src=x/.test(hp) && /&lt;script&gt;alert\(1\)/.test(hp));
  const pidX = +new URL(pagina).searchParams.get('p');
  const hpa = await (await a.request.get(B + '/admin/addon-pagina.php?a=prenotazioni&p=elenco&id=' + pidX)).text();
  ok('A8 la scheda nel pannello lo mostra come testo (anche le note)', !/<script>alert\(1\)/.test(hpa) && !/<svg onload/.test(hpa) && /&lt;svg onload/.test(hpa));
  ok('A9 il nome con gli apici è salvato così com\'è (query preparate)', /OR '1'='1/.test(sql(`SELECT nome FROM {p}pren_prenotazioni WHERE id = ${pidX}`)[0].nome));
  const emX = await C.nuove(POSTA, quante, 2);
  ok('A10 un a capo nel nome non diventa un\'intestazione: nessuna email a spia@cattivo.it, nessun Bcc', emX.length >= 1 && !C.posta(POSTA).some(e => /spia@cattivo/.test(e.a)) && !emX.some(e => /^Bcc:/mi.test(e.grezza)), emX.map(e => e.a));
  ok('A8 l\'avviso all\'attività ha il nome come testo', emX.some(e => e.a === 'attivita@esempio.it' && /&lt;script&gt;/.test(e.html) && !/<script>alert/.test(e.html)));
  // A11 troppe richieste dallo stesso indirizzo IP (6 in un'ora).
  pulisciIp();
  let ultimo = '';
  for (let k = 0; k < 7; k++) {
    const r = await invia('/prenotazione/invia/', base({ ora: posti.posti[7 + k].ora, email: `ip${k}@cliente.it`, captcha: await captcha() }));
    ultimo = codice(r) || 'fatta';
  }
  ok('A11 la settima prenotazione dallo stesso IP in un\'ora: "troppe"', ultimo === 'pren-troppi', ultimo);
  pulisciIp();
  // A12-A15 link personali.
  const due = sql("SELECT id, codice FROM {p}pren_prenotazioni WHERE stato = 'confermata' ORDER BY id DESC LIMIT 2");
  const [pa, pb] = due;
  const tA = hmac(`prenotazione|${pa.id}|${pa.codice}`, segreto), tB = hmac(`prenotazione|${pb.id}|${pb.codice}`, segreto);
  ok('(controllo) la firma calcolata dalla prova apre la pagina giusta', (await fetch(`${B}/prenotazione/gestisci/?p=${pa.id}&t=${tA}`)).status === 200);
  ok('A12 la firma di una prenotazione su un\'altra: 404', (await fetch(`${B}/prenotazione/gestisci/?p=${pb.id}&t=${tA}`)).status === 404);
  ok('A12 una firma cambiata di una lettera: 404', (await fetch(`${B}/prenotazione/gestisci/?p=${pa.id}&t=${tA.slice(0, -1)}${tA.endsWith('a') ? 'b' : 'a'}`)).status === 404);
  ok('A12 senza firma, firma corta, id non numerico: 404', (await fetch(`${B}/prenotazione/gestisci/?p=${pa.id}`)).status === 404 && (await fetch(`${B}/prenotazione/gestisci/?p=${pa.id}&t=abc`)).status === 404 && (await fetch(`${B}/prenotazione/gestisci/?p=${pa.id}x&t=${tA}`)).status === 404);
  ok('A12 il file .ics con la firma sbagliata: 404', (await fetch(`${B}/prenotazione/calendario/?p=${pb.id}&t=${tA}`)).status === 404);
  await invia('/prenotazione/gestisci/', { p: String(pb.id), t: tA, azione: 'annulla' });
  ok('A13 annullare la prenotazione di un altro con la propria firma: resta confermata', sql(`SELECT stato FROM {p}pren_prenotazioni WHERE id = ${pb.id}`)[0].stato === 'confermata');
  await invia('/prenotazione/gestisci/', { p: String(pa.id), t: tA, azione: 'annulla' }, { Origin: 'https://cattivo.example' });
  ok('A13b annullare con la firma giusta ma da un altro sito (Origin): resta confermata', sql(`SELECT stato FROM {p}pren_prenotazioni WHERE id = ${pa.id}`)[0].stato === 'confermata');
  const occupato = sql(`SELECT inizio_ts FROM {p}pren_prenotazioni WHERE id = ${pb.id}`)[0].inizio_ts;
  const oraB = new Date(occupato * 1000); const oB = `${oraB.getHours()}:${String(oraB.getMinutes()).padStart(2, '0')}`;
  const sp = await invia('/prenotazione/gestisci/', { p: String(pa.id), t: tA, azione: 'sposta', giorno: g, ora: oB });
  ok('A17 spostarsi sul posto di un altro: "posto preso", nessun cambio', /e=posto/.test(sp.headers.get('location') || '') && sql(`SELECT risorsa_id, inizio_ts FROM {p}pren_prenotazioni WHERE id = ${pa.id}`)[0].inizio_ts !== occupato);
  const ora = Math.floor(Date.now() / 1000);
  sql(`UPDATE {p}pren_prenotazioni SET inizio_ts = ${ora + 3 * 3600}, fine_ts = ${ora + 3 * 3600 + 1800}, blocco_ts = ${ora + 3 * 3600 + 1800} WHERE id = ${pb.id}`);
  await invia('/prenotazione/gestisci/', { p: String(pb.id), t: tB, azione: 'annulla' });
  ok('A14 annullare a 3 ore dall\'appuntamento (limite 24): non si può', sql(`SELECT stato FROM {p}pren_prenotazioni WHERE id = ${pb.id}`)[0].stato === 'confermata');
  sql(`UPDATE {p}pren_prenotazioni SET inizio_ts = ${ora - 40 * 86400}, fine_ts = ${ora - 40 * 86400 + 1800}, blocco_ts = ${ora - 40 * 86400 + 1800} WHERE id = ${pb.id}`);
  ok('A15 il link di un appuntamento di 40 giorni fa: scaduto (404)', (await fetch(`${B}/prenotazione/gestisci/?p=${pb.id}&t=${tB}`)).status === 404);
  ok('A9 i posti con un servizio "1 UNION SELECT": 404, niente dati', (await fetch(`${B}/prenotazione/posti/?s=${encodeURIComponent('1 UNION SELECT 1')}`)).status === 404);
  const csrf = await a.request.post(B + '/admin/addon-pagina.php?a=prenotazioni&p=elenco', { form: { id: String(pa.id), azione: 'annulla' }, maxRedirects: 0 });
  ok('A16 CSRF: annullare dal pannello senza il token: 403, resta confermata', csrf.status() === 403 && sql(`SELECT stato FROM {p}pren_prenotazioni WHERE id = ${pa.id}`)[0].stato === 'confermata', csrf.status());
  const cerca = await a.request.get(B + "/admin/addon-pagina.php?a=prenotazioni&p=elenco&filtro=tutte&q=" + encodeURIComponent("' OR 1=1 -- "));
  ok('A9 SQL injection nella ricerca del pannello: nessuna riga', cerca.status() === 200 && /Nessuna prenotazione qui/.test(await cerca.text()));

  // ================= B. Eventi
  const ev = (slug, posti, max, attesa, stato, chiusura = 0) => {
    sql(`INSERT INTO {p}evi_eventi (slug, titolo, inizio, inizio_ts, descrizione, posti, max_persona, attesa, stato, chiusura_ts, creato, aggiornato) VALUES ('${slug}', 'Evento ${slug}', FROM_UNIXTIME(${ora + 7 * 86400}), ${ora + 7 * 86400}, '', ${posti}, ${max}, ${attesa}, '${stato}', ${chiusura}, NOW(), NOW())`);
    return sql(`SELECT id FROM {p}evi_eventi WHERE slug = '${slug}'`)[0].id;
  };
  const e1 = ev('due-posti', 2, 1, 0, 'pubblicato'), e2 = ev('bozza', 10, 2, 1, 'bozza'), e3 = ev('chiuso', 10, 2, 1, 'pubblicato', ora - 60), e4 = ev('aperto', 20, 2, 1, 'pubblicato');
  const isc = (ev, extra = {}) => ({ evento: String(ev), nome: 'Anna Bianchi', email: 'anna@cliente.it', posti: '1', privacy: '1', pagina: '/evento/x/', ancora: 'iscriviti', ...extra });
  pulisciIp();
  const sei = await Promise.all(Array.from({ length: 6 }, async (_, k) => invia('/evento-iscrizione/invia/', { ...isc(e1, { email: `folla${k}@cliente.it` }), captcha: await captcha() })));
  const conf = +sql(`SELECT COALESCE(SUM(posti),0) n FROM {p}evi_iscrizioni WHERE evento_id = ${e1} AND stato = 'confermata'`)[0].n;
  ok('B2 sei iscrizioni insieme a un evento con 2 posti (senza attesa): due dentro, quattro "pieno"', conf === 2 && sei.filter(r => codice(r) === 'evi-pieno').length === 4, [conf, sei.map(codice)]);
  pulisciIp();
  const stessa = await Promise.all(Array.from({ length: 4 }, async () => invia('/evento-iscrizione/invia/', { ...isc(e4, { email: 'gemello@cliente.it' }), captcha: await captcha() })));
  ok('B3 la stessa email quattro volte insieme: una sola iscrizione', +sql(`SELECT COUNT(*) n FROM {p}evi_iscrizioni WHERE email = 'gemello@cliente.it'`)[0].n === 1 && stessa.filter(r => codice(r) === 'evi-gia').length === 3, stessa.map(codice));
  pulisciIp();
  const casiE = [
    ['B1 più posti del massimo per persona (5 su 2)', isc(e4, { posti: '5', email: 'b1@cliente.it' }), 'evi-posti'],
    ['B1 posti negativi', isc(e4, { posti: '-1', email: 'b1b@cliente.it' }), 'evi-posti'],
    ['B1 posti non numerici', isc(e4, { posti: '1 OR 1=1', email: 'b1c@cliente.it' }), 'evi-posti'],
    ['B4 un evento in bozza', isc(e2, { email: 'b4@cliente.it' }), 'evi-chiuse'],
    ['B5 iscrizioni chiuse', isc(e3, { email: 'b5@cliente.it' }), 'evi-chiuse'],
    ['B4 un evento che non c\'è', isc(999999, { email: 'b4b@cliente.it' }), 'evi-robot'],
    ['B campo esca', isc(e4, { sito_web: 'x', email: 'b6@cliente.it' }), 'evi-robot'],
    ['B header injection nell\'email', isc(e4, { email: 'x@y.it\nBcc: spia@cattivo.it' }), 'evi-email'],
  ];
  for (const [t, campi, atteso] of casiE) {
    const r = await invia('/evento-iscrizione/invia/', { ...campi, captcha: await captcha() });
    ok(t + ': respinto (' + atteso + ')', codice(r) === atteso, codice(r));
  }
  ok('B4 la pagina di un evento in bozza: 404', (await fetch(B + '/evento/bozza/')).status === 404);
  pulisciIp();
  const ex = await invia('/evento-iscrizione/invia/', { ...isc(e4, { nome: '<script>alert(1)</script>', email: 'xss-ev@cliente.it' }), captcha: await captcha() });
  const pagEv = ex.headers.get('location') || '';
  ok('B7 il nome con codice esce come testo nella pagina personale e nel pannello', !/<script>alert/.test(await (await fetch(pagEv)).text()) && !/<script>alert\(1\)/.test(await (await a.request.get(B + '/admin/addon-pagina.php?a=eventi-iscrizione&p=iscritti&e=' + e4)).text()));
  const i1 = sql(`SELECT id, codice FROM {p}evi_iscrizioni WHERE email = 'gemello@cliente.it'`)[0], i2 = sql(`SELECT id, codice FROM {p}evi_iscrizioni WHERE email = 'xss-ev@cliente.it'`)[0];
  const t1 = hmac(`evento-iscrizione|${i1.id}|${i1.codice}`, segreto);
  ok('B6 la firma di un\'iscrizione su un\'altra: 404', (await fetch(`${B}/evento-iscrizione/gestisci/?i=${i2.id}&t=${t1}`)).status === 404);
  await invia('/evento-iscrizione/gestisci/', { i: String(i2.id), t: t1, azione: 'cancella' });
  ok('B6 cancellare l\'iscrizione di un altro: resta', sql(`SELECT stato FROM {p}evi_iscrizioni WHERE id = ${i2.id}`)[0].stato === 'confermata');
  const csrfE = await a.request.post(B + '/admin/addon-pagina.php?a=eventi-iscrizione&p=iscritti', { form: { e: String(e4), iscrizione: String(i2.id), azione: 'cancella' }, maxRedirects: 0 });
  ok('B8 CSRF: cancellare un iscritto dal pannello senza il token: 403', csrfE.status() === 403 && sql(`SELECT stato FROM {p}evi_iscrizioni WHERE id = ${i2.id}`)[0].stato === 'confermata');
  const scrivi = await a.request.post(B + '/admin/addon-pagina.php?a=eventi-iscrizione&p=iscritti', { form: { e: String(e4), azione: 'scrivi', oggetto: 'x', messaggio: 'y' }, maxRedirects: 0 });
  ok('B8 CSRF: "Scrivi a tutti" senza il token: 403', scrivi.status() === 403);

  // ================= C. Recensioni
  const inv = (email, inviato, scade) => {
    sql(`INSERT INTO {p}rec_inviti (codice, email, nome, origine, invia_dopo, inviato_at, scade_at, creato) VALUES (MD5(RAND()), '${email}', 'Cliente', 'manuale', NOW(), ${inviato}, ${scade}, NOW())`);
    const r = sql(`SELECT id, codice FROM {p}rec_inviti WHERE email = '${email}'`)[0];
    return { ...r, t: hmac(`recensione|${r.id}|${r.codice}`, segreto) };
  };
  const r1 = inv('r1@cliente.it', 'NOW()', 'DATE_ADD(NOW(), INTERVAL 10 DAY)'), r2 = inv('r2@cliente.it', 'NOW()', 'DATE_ADD(NOW(), INTERVAL 10 DAY)');
  const r3 = inv('r3@cliente.it', 'DATE_SUB(NOW(), INTERVAL 40 DAY)', 'DATE_SUB(NOW(), INTERVAL 10 DAY)'), r4 = inv('r4@cliente.it', 'NULL', 'NULL');
  const rec = (i, extra = {}) => ({ i: String(i.id), t: i.t, stelle: '5', testo: 'Tutto molto bene, grazie.', nome: 'Cliente C.', consenso: '1', ...extra });
  ok('(controllo) il link giusto apre il modulo', (await fetch(`${B}/recensione/?i=${r1.id}&t=${r1.t}`)).status === 200);
  await Promise.all(Array.from({ length: 5 }, () => invia('/recensione/', rec(r1))));
  ok('C1 la stessa recensione mandata cinque volte insieme: una sola', +sql(`SELECT COUNT(*) n FROM {p}rec_recensioni WHERE invito_id = ${r1.id}`)[0].n === 1);
  await invia('/recensione/', rec(r1, { stelle: '1', testo: 'Seconda' }));
  ok('C1 ritornare con lo stesso link: niente seconda recensione', +sql(`SELECT COUNT(*) n FROM {p}rec_recensioni WHERE invito_id = ${r1.id}`)[0].n === 1);
  ok('C2 la firma di un invito su un altro: 404', (await fetch(`${B}/recensione/?i=${r2.id}&t=${r1.t}`)).status === 404 && (await invia('/recensione/', { ...rec(r2), t: r1.t })).status === 404);
  ok('C2 la firma per "non scrivermi più" non vale per lasciare la recensione', (await fetch(`${B}/recensione/?i=${r2.id}&t=${hmac(`esci|${r2.id}|${r2.codice}`, segreto)}`)).status === 404);
  ok('C2 e quella della recensione non vale per "non scrivermi più"', (await fetch(`${B}/recensione/esci/?i=${r2.id}&t=${r2.t}`)).status === 404);
  ok('C3 un link scaduto: 410, e il POST non salva niente', (await fetch(`${B}/recensione/?i=${r3.id}&t=${r3.t}`)).status === 410 && (await invia('/recensione/', rec(r3))).status === 410 && +sql(`SELECT COUNT(*) n FROM {p}rec_recensioni WHERE invito_id = ${r3.id}`)[0].n === 0);
  ok('C6 un invito non ancora partito (firma giusta): 404', (await fetch(`${B}/recensione/?i=${r4.id}&t=${r4.t}`)).status === 404);
  for (const s of ['0', '6', 'abc', '-1', '5.5']) {
    const r = await invia('/recensione/', rec(r2, { stelle: s }));
    ok(`C4 stelle "${s}": respinte`, r.status === 422 && +sql(`SELECT COUNT(*) n FROM {p}rec_recensioni WHERE invito_id = ${r2.id}`)[0].n === 0, r.status);
  }
  ok('C senza consenso alla pubblicazione: respinta', (await invia('/recensione/', rec(r2, { consenso: '' }))).status === 422);
  ok('C da un altro sito (Origin): niente recensione', (await invia('/recensione/', rec(r2), { Origin: 'https://cattivo.example' })).status === 404 && +sql(`SELECT COUNT(*) n FROM {p}rec_recensioni WHERE invito_id = ${r2.id}`)[0].n === 0);
  await invia('/recensione/', rec(r2, { testo: '<script>alert(1)</script><img src=x onerror=alert(2)>', nome: '<b onmouseover=alert(3)>Cliente</b>' }));
  const rid = sql(`SELECT id FROM {p}rec_recensioni WHERE invito_id = ${r2.id}`)[0].id;
  const csrfR = await a.request.post(B + '/admin/addon-pagina.php?a=recensioni&p=recensioni', { form: { id: String(rid), azione: 'approva' }, maxRedirects: 0 });
  ok('C8 CSRF: approvare senza il token: 403, resta da approvare', csrfR.status() === 403 && sql(`SELECT stato FROM {p}rec_recensioni WHERE id = ${rid}`)[0].stato === 'da_approvare');
  sql(`UPDATE {p}rec_recensioni SET stato = 'approvata' WHERE id = ${rid}`);
  C.cli(CMS, 'pagina', 'dicono-di-noi', 'Dicono di noi', 'recensioni_sito');
  const wh = await (await fetch(B + '/dicono-di-noi/')).text();
  ok('C5 testo e nome con codice: nel widget escono come testo', /&lt;script&gt;alert\(1\)/.test(wh) && !/<script>alert\(1\)/.test(wh) && !/<img src=x/.test(wh) && !/<b onmouseover/.test(wh));
  const ph = await (await a.request.get(B + '/admin/addon-pagina.php?a=recensioni&p=recensioni&filtro=tutte')).text();
  ok('C5 e nel pannello', !/<script>alert\(1\)/.test(ph) && !/<b onmouseover/.test(ph));

  // ================= D. Pannello e file
  const pagine = [['prenotazioni', ['agenda', 'elenco', 'servizi', 'persone', 'impostazioni']], ['eventi-iscrizione', ['eventi', 'iscritti', 'impostazioni']], ['recensioni', ['recensioni', 'inviti', 'impostazioni']]];
  let senza = 0, tot = 0;
  for (const [ad, ps] of pagine) for (const p of ps) { tot++; const r = await fetch(`${B}/admin/addon-pagina.php?a=${ad}&p=${p}`, { redirect: 'manual' }); if (r.status >= 300 && r.status < 400 && /login\.php/.test(r.headers.get('location') || '')) senza++; }
  ok(`D1 le ${tot} pagine del pannello degli addon senza accesso: tutte al login`, senza === tot, [senza, tot]);
  // Un redattore.
  await a.goto(B + '/admin/utenti.php', { waitUntil: 'networkidle' });
  await a.fill('form.sc-corpo [name=username]', 'red_addon'); await a.fill('form.sc-corpo [name=email]', 'red_addon@esempio.it');
  await a.selectOption('form.sc-corpo [name=role]', 'editor'); await a.fill('form.sc-corpo [name=password]', 'Redattore-Prova-2026'); await a.fill('form.sc-corpo [name=password2]', 'Redattore-Prova-2026');
  await Promise.all([a.waitForNavigation(), a.click('form.sc-corpo button.btn-primary')]);
  const red = await C.pagina(br, err);
  await C.entra(red, B, 'red_addon', 'Redattore-Prova-2026');
  let bloccate = 0;
  for (const [ad, ps] of pagine) for (const p of ps) { const r = await red.goto(`${B}/admin/addon-pagina.php?a=${ad}&p=${p}`); if (!/addon-pagina\.php/.test(red.url()) || r.status() >= 400) bloccate++; }
  const postRed = await red.request.post(B + '/admin/addon-pagina.php?a=prenotazioni&p=impostazioni', { form: { anticipo_ore: '0' }, maxRedirects: 0 });
  ok(`D2 il redattore non apre nessuna delle ${tot} pagine degli addon`, bloccate === tot, [bloccate, tot]);
  ok('D2 e non vede le voci nel menu', await red.locator('nav.admin-nav a', { hasText: 'Persone e orari' }).count() === 0 && await red.locator('nav.admin-nav a', { hasText: /^\s*Inviti\s*$/ }).count() === 0);
  ok('D2 un POST del redattore alle impostazioni non passa', postRed.status() !== 200 || !/salvate/.test(await postRed.text()));
  const file = ['/addon/prenotazioni/modulo.php', '/addon/prenotazioni/includes/prenotazioni.php', '/addon/eventi-iscrizione/includes/sito.php', '/addon/recensioni/includes/admin-inviti.php', '/moduli/addon-attivi.json'];
  const stati = [];
  for (const f of file) stati.push((await fetch(B + f)).status);
  ok('D3 i file PHP degli addon e l\'elenco degli accesi non si aprono dal web (403)', stati.every(s => s === 403), stati);
  ok('D3 gli assets degli addon invece si servono', (await fetch(B + '/addon/prenotazioni/assets/prenota.js')).status === 200);

  C.fine(err);
  await br.close();
})().catch(e => { console.error(e); process.exitCode = 1; });
