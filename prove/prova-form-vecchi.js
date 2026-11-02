// 2.16.0: i form di prima restano uguali dopo l'aggiornamento alla 2.16.0 (decisione del piano: "i form e le iscrizioni di
// prima devono continuare a funzionare uguali"). In due tempi, sullo stesso sito, con il server di posta finto acceso:
//   1. sulla 2.15.0:  node prove/prova-form-vecchi.js prima <base> <cartella CMS> <porta smtp> <cartella posta> <file.json>
//      semina cinque form di ogni tipo (contatti con l'avviso a due indirizzi, iscrizione con doppia conferma, file in regalo
//      ed email di conferma scritta a mano, iscrizione senza conferma con il file, tutti i tipi di campo, nome e cognome),
//      ne salva l'HTML, li compila, apre i link di conferma, e salva email, pagine e righe del database;
//   2. aggiorna il sito alla 2.16.0 (prova-aggiornamento.js);
//   3. sulla 2.16.0:  node prove/prova-form-vecchi.js dopo <base> <cartella CMS> <porta smtp> <cartella posta> <file.json> <utente> <password>
//      rifà le stesse compilazioni con altri indirizzi e confronta: HTML dei form al byte, email (oggetto e HTML, con
//      indirizzi, token, numeri dei contatti e ore resi uguali), pagine dopo la conferma, iscrizioni e dati salvati. Poi
//      salva ogni passo di un form di prima dal costruttore nuovo, senza cambiare niente, e controlla che esca uguale.
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const [MODO, B, DIR, SMTP, POSTA, FILE, UTENTE, PASSWORD] = process.argv.slice(2);
const CLI = path.join(__dirname, 'form-vecchi-cli.php');
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' && !c ? ' ' + JSON.stringify(extra).slice(0, 400) : '')); };
const dorme = ms => new Promise(r => setTimeout(r, ms));
const cliTesto = (...a) => execFileSync('php', [CLI, DIR, ...a.map(String)], { encoding: 'utf8' });
const cli = (...a) => JSON.parse(cliTesto(...a).trim().split('\n').pop());
const q = s => cli('sql', s);
function leggi(eml) {
  const i = eml.search(/\r?\n\r?\n/);
  const testa = eml.slice(0, i).replace(/\r?\n[ \t]+/g, ' '); const corpo = eml.slice(i).replace(/^\r?\n\r?\n/, '');
  const h = (t, nome) => ((t.match(new RegExp('^' + nome + ':[ \\t]*(.*)$', 'mi')) || [])[1] || '').trim();
  const dec = s => s.replace(/=\?UTF-8\?B\?([^?]+)\?=\s*/gi, (_, b) => Buffer.from(b, 'base64').toString('utf8'));
  const parte = (t, c) => /base64/i.test(h(t, 'Content-Transfer-Encoding')) ? Buffer.from(c.replace(/\s+/g, ''), 'base64').toString('utf8') : c;
  const ct = h(testa, 'Content-Type');
  let html = '', testo = '';
  const b = (ct.match(/boundary="([^"]+)"/) || [])[1];
  if (b) {
    for (const p of corpo.split('--' + b).slice(1)) {
      const j = p.search(/\r?\n\r?\n/); if (j < 0) continue;
      const pt = p.slice(0, j).trim(); const pc = p.slice(j).trim();
      if (/text\/html/i.test(h(pt, 'Content-Type'))) html = parte(pt, pc); else if (/text\/plain/i.test(h(pt, 'Content-Type'))) testo = parte(pt, pc);
    }
  } else html = parte(testa, corpo);
  return { dest: h(testa, 'X-Destinatari'), oggetto: dec(h(testa, 'Subject')), html, testo, allegati: (eml.match(/filename="[^"]+"/g) || []).length };
}
const posta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => leggi(fs.readFileSync(path.join(POSTA, f), 'utf8')));
// Quello che cambia per forza fra le due volte: l'indirizzo (prima-/dopo-), token e firme, numeri dei contatti, date e ore.
const uguale = (s, chi) => String(s || '').split(chi + '-').join('X-')
  .replace(/[a-f0-9]{64}/g, 'T64').replace(/[a-f0-9]{32}/g, 'T32').replace(/k=\d+\.\d+\.T32/g, 'k=K').replace(/([?&](i|c)=)\d+/g, '$1N')
  .replace(/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/g, 'DATA').replace(/boundary[^\s"]*/g, 'B');
const conferma = m => ((m && m.html || '').replace(/&amp;/g, '&').match(/https?:\/\/[^"<\s]+\/newsletter-conferma\/\?t=[a-f0-9]{64}/) || [])[0] || '';
const scarica = m => ((m && m.html || '').replace(/&amp;/g, '&').match(/https?:\/\/[^"<\s]+\/scarica\/[a-z0-9-]+\/\?k=[0-9.a-f]+/) || [])[0] || '';

// Le compilazioni, le stesse le due volte; per ognuna: email arrivate, pagina dopo il clic di conferma, righe del database.
const COMPILA = {
  contatti: chi => ({ c_nome: 'Anna Rossi', c_email: chi + '-contatti@fv-prova.it', c_messaggio: 'Ciao \u{1F600}, una domanda.' }),
  doppia: chi => ({ c_nome: 'Bruno', c_email: chi + '-doppia@fv-prova.it' }),
  subito: chi => ({ c_email: chi + '-subito@fv-prova.it' }),
  tutti: chi => ({ c_nome: 'Carla', c_cognome: 'Verdi', c_email: chi + '-tutti@fv-prova.it', c_telefono: '+39 333 1234567', c_servizio: 'Colore', c_giorni: ['Lunedì', 'Martedì'], c_ho_letto_le_condizioni: '1', c_data: '2026-12-01', c_ora: '10:30', c_persone: '2', c_note: 'Prima volta.' }),
  clienti: chi => ({ c_nome: 'Dario', c_cognome: 'Neri', c_email: chi + '-clienti@fv-prova.it' }),
};
async function giro(chi, ids) {
  const r = { html: {}, esiti: {}, email: {}, pagine: {}, db: {} };
  for (const k of Object.keys(COMPILA)) r.html[k] = cliTesto('html', ids[k]);
  for (const [k, f] of Object.entries(COMPILA)) {
    cli('sblocca');
    const prima = posta().length;
    const es = cli('ricevi', ids[k], JSON.stringify(f(chi)));
    r.esiti[k] = { esito: es.esito, file: es.omaggio ? es.omaggio.stato : '' };
    await dorme(1500);
    let mail = posta().slice(prima);
    const link = conferma(mail.find(m => conferma(m)));
    if (link) {
      const risp = await fetch(link.replace(/^https?:\/\/[^/]+(\/test)?/, B));
      const t = await risp.text();
      r.pagine[k] = uguale((t.match(/<main[\s\S]*<\/main>/) || [''])[0], chi);
      await dorme(1500);
      mail = posta().slice(prima);
    }
    r.email[k] = mail.map(m => ({ dest: uguale(m.dest, chi), oggetto: uguale(m.oggetto, chi), html: uguale(m.html, chi), testo: uguale(m.testo, chi), allegati: m.allegati }));
    const em = f(chi).c_email;
    const c = q(`SELECT stato, nome, cognome, fonte, consenso_versione, opt_in, IF(doi_at IS NULL, 0, 1) AS doi, ip FROM {p}newsletter_iscritti WHERE email = '${em}'`)[0] || null;
    const l = c ? q(`SELECT x.nome FROM {p}newsletter_iscritti_liste il JOIN {p}newsletter_liste x ON x.id = il.lista_id JOIN {p}newsletter_iscritti i ON i.id = il.iscritto_id WHERE i.email = '${em}' ORDER BY x.nome`).map(x => x.nome) : [];
    const inv = JSON.parse(uguale(JSON.stringify(q(`SELECT email, nome, dati, pagina, allegati FROM {p}moduli_invii WHERE email = '${em}'`)), chi));
    const cons = q(`SELECT COUNT(*) AS n FROM {p}scaricabili_consegne c JOIN {p}newsletter_iscritti i ON i.id = c.iscritto_id WHERE i.email = '${em}'`)[0];
    r.db[k] = { contatto: c, liste: l, invii: inv, consegne: cons ? +cons.n : 0 };
    const s = scarica(mail.find(m => scarica(m)));
    if (s) r.db[k].scarica = (await fetch(s.replace(/^https?:\/\/[^/]+(\/test)?/, B))).status;
  }
  return r;
}

(async () => {
  if (MODO === 'prima') {
    const ids = cli('semina', SMTP);
    const r = await giro('prima', ids);
    fs.writeFileSync(FILE, JSON.stringify({ ids, r }, null, 1));
    ok('prima: cinque form seminati e compilati', Object.values(r.esiti).every(x => x.esito === ''), r.esiti);
    ok('prima: le email arrivate (avvisi, conferme, file)', Object.values(r.email).reduce((a, x) => a + x.length, 0) >= 7, Object.fromEntries(Object.entries(r.email).map(([k, v]) => [k, v.length])));
  } else {
    const { ids, r: p } = JSON.parse(fs.readFileSync(FILE, 'utf8'));
    const tipi = Object.fromEntries(q('SELECT id, tipo, conferma FROM {p}moduli').map(x => [+x.id, x]));
    ok('dopo: i form di prima migrati: con una lista sono di iscrizione, la spunta di conferma è la doppia, senza è "nessuna"',
      tipi[ids.contatti].tipo === 'generico' && tipi[ids.tutti].tipo === 'generico' && tipi[ids.doppia].tipo === 'iscrizione' && tipi[ids.doppia].conferma === 'doppia' && tipi[ids.subito].conferma === 'nessuna' && tipi[ids.clienti].conferma === 'doppia', tipi);
    cli('togli', 'dopo'); // un giro di prima, se la prova si rifà
    const d = await giro('dopo', ids);
    for (const k of Object.keys(COMPILA)) {
      ok(`${k}: l'HTML del form uguale al byte`, d.html[k] === p.html[k], [d.html[k].length, p.html[k].length]);
      ok(`${k}: la compilazione riesce come prima (e il file va dove andava)`, JSON.stringify(d.esiti[k]) === JSON.stringify(p.esiti[k]), [d.esiti[k], p.esiti[k]]);
      ok(`${k}: le email uguali (${p.email[k].length}: destinatari, oggetto, HTML, testo, allegati)`, JSON.stringify(d.email[k]) === JSON.stringify(p.email[k]), d.email[k].map((m, i) => [m.oggetto, p.email[k][i] && p.email[k][i].oggetto, m.html === (p.email[k][i] || {}).html]));
      if (p.pagine[k] !== undefined) ok(`${k}: la pagina dopo il clic di conferma uguale`, d.pagine[k] === p.pagine[k], [d.pagine[k].slice(0, 200), p.pagine[k].slice(0, 200)]);
      ok(`${k}: iscrizione, liste, dati salvati e file consegnati come prima`, JSON.stringify(d.db[k]) === JSON.stringify(p.db[k]), [d.db[k], p.db[k]]);
    }
    // Il costruttore nuovo: salvare ogni passo di un form di prima senza cambiare niente lo lascia uguale.
    const { chromium } = require('playwright');
    const br = await chromium.launch();
    const pg = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    await pg.goto(B + '/admin/login.php'); await pg.fill('#username', UTENTE); await pg.fill('#password', PASSWORD);
    await Promise.all([pg.waitForNavigation(), pg.click('button[type=submit]')]);
    for (const k of ['doppia', 'contatti']) {
      const prima = cliTesto('html', ids[k]);
      const riga = q(`SELECT * FROM {p}moduli WHERE id = ${ids[k]}`)[0];
      for (const passo of [1, 2, 3, 4]) {
        await pg.goto(B + '/admin/form.php?id=' + ids[k] + '&passo=' + passo, { waitUntil: 'networkidle' });
        await Promise.all([pg.waitForNavigation(), pg.click('#fm-form button[name=vai][value=resta]')]);
      }
      const dopo = q(`SELECT * FROM {p}moduli WHERE id = ${ids[k]}`)[0];
      // Il browser manda gli a capo dei campi di testo come \r\n (come il pannello di prima): non contano.
      const diversi = Object.keys(riga).filter(c => !['aggiornato', 'campi'].includes(c) && String(riga[c]).replace(/\r/g, '') !== String(dopo[c]).replace(/\r/g, ''));
      ok(`${k}: salvato passo per passo dal costruttore, senza cambiare niente: HTML uguale e colonne uguali`, cliTesto('html', ids[k]) === prima && diversi.length === 0, diversi.map(c => [c, riga[c], dopo[c]]));
    }
    await br.close();
  }
  console.log(`${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); console.log(`${n - ko} su ${n} OK (interrotta)`); process.exit(1); });
