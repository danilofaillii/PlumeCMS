// 2.15.0: i flussi di prima, in corso, dopo l'aggiornamento alla 2.15.0. Il sito è stato preparato sulla 2.14.0 con
// `automazioni-cli.php vecchi_semina` (tre flussi "AU Vecchio" e sei contatti a metà) e `impronte` (sha256 di oggetto e HTML
// di ogni email per ogni contatto, con il motore di prima). Qui, sulla 2.15.0:
//   - le impronte sono uguali al byte (le email di prima escono identiche);
//   - A (email, attesa 1 giorno, bivio "ha cliccato A1"): au01 e au02 avevano cliccato prima dell'aggiornamento -> "AU A2 cliccato",
//     gli altri -> "AU A2 no" (lo stesso esito del bivio di prima);
//   - B (email in ascolto 2 giorni: se apre / se non fa niente): au01 e au03 avevano aperto prima (cammino già spostato),
//     au04 apre adesso -> "AU B apre"; au02, au05, au06 -> "AU B niente" passati i due giorni;
//   - C, spento prima dell'aggiornamento con sei cammini in attesa: diventa Inattiva, nessuno riceve "AU C dopo" (prima un
//     flusso spento fermava chi era dentro: stessa sorte).
// Tutti i cammini di A e B arrivano in fondo (finito), una email per passo, nessuna doppia.
// uso: node prove/prova-automazioni-vecchie.js <base> <utente> <password> <cartella posta> <cartella CMS> <impronte di prima (json)>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const [B, UTENTE, PASSWORD, POSTA, DIR, IMPRONTE] = process.argv.slice(2);
const CLI = path.join(__dirname, 'automazioni-cli.php');
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (!c && extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 700) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [CLI, DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 << 20 }).trim().split('\n').pop());
const q = (sql) => cli('sql', sql);
const uno = (sql) => { const r = q(sql); return r && r[0] ? Object.values(r[0])[0] : null; };
const soggetto = (eml) => { const m = eml.match(/^Subject:[ \t]*(.*)$/mi); return m ? m[1].replace(/=\?UTF-8\?B\?([^?]+)\?=/gi, (_, b) => Buffer.from(b, 'base64').toString('utf8')).trim() : ''; };
const posta = () => fs.readdirSync(POSTA).filter(f => f.endsWith('.eml')).map(f => fs.readFileSync(path.join(POSTA, f), 'utf8')).map(e => ({ a: (e.match(/^X-Destinatari: (.*)$/m) || [])[1], o: soggetto(e) }));

(async () => {
  const prima = JSON.parse(fs.readFileSync(IMPRONTE, 'utf8'));
  const dopo = cli('impronte');
  const diverse = Object.keys(prima).filter(k => prima[k] !== dopo[k]);
  ok(`le email dei flussi di prima sono uguali al byte (${Object.keys(prima).length} impronte)`, Object.keys(prima).length === 42 && diverse.length === 0 && Object.keys(dopo).length === 42, diverse.slice(0, 5));
  const F = Object.fromEntries(q("SELECT id, nome, stato, attivo, inneschi FROM {p}newsletter_flussi WHERE nome LIKE 'AU Vecchio%'").map(r => [r.nome.slice(-1), r]));
  ok('i flussi di prima sono automazioni: A e B Attiva, C (spento) Inattiva; inneschi dalla colonna di prima', F.A.stato === 'attiva' && F.B.stato === 'attiva' && F.C.stato === 'inattiva' && +F.C.attivo === 0 && (F.A.inneschi === null || JSON.parse(F.A.inneschi || '[]').length <= 1), F);
  ok('i cammini di prima sono il giro 1, nessuno perso (18)', +uno("SELECT COUNT(*) FROM {p}newsletter_corsi c JOIN {p}newsletter_flussi f ON f.id = c.flusso_id WHERE f.nome LIKE 'AU Vecchio%' AND c.giro = 1") === 18);
  const C = Object.fromEntries(q("SELECT id, email FROM {p}newsletter_iscritti WHERE email LIKE 'au0_@au-prova.it'").map(r => [r.email.slice(0, 4), +r.id]));
  const pr = posta().length;
  cli('evento', 'apertura', F.B.id, 1, C.au04);
  cli('giro');
  ok('subito: au01 e au03 (avevano aperto prima) e au04 (apre adesso) ricevono "AU B apre"', ['au01', 'au03', 'au04'].every(k => posta().some(m => m.a === k + '@au-prova.it' && m.o === 'AU B apre')));
  ok('C (Inattiva): i sei cammini in attesa sono tolti', +uno(`SELECT COUNT(*) FROM {p}newsletter_corsi WHERE flusso_id = ${F.C.id} AND stato = 'tolto'`) === 6);
  cli('avanza', 2881); cli('giro'); cli('giro'); cli('giro');
  const att = { au01: ['AU A2 cliccato', 'AU B apre'], au02: ['AU A2 cliccato', 'AU B niente'], au03: ['AU A2 no', 'AU B apre'], au04: ['AU A2 no', 'AU B apre'], au05: ['AU A2 no', 'AU B niente'], au06: ['AU A2 no', 'AU B niente'] };
  const nuove = posta().slice(0);
  for (const [k, v] of Object.entries(att)) {
    const sue = nuove.filter(m => m.a === k + '@au-prova.it').map(m => m.o).sort();
    ok(`${k}: ha ricevuto A1, B1 (prima) e ${v.join(', ')} (dopo), una volta ciascuna, e niente di C`, JSON.stringify(sue) === JSON.stringify(['AU A1', 'AU B1'].concat(v).sort()), sue);
  }
  ok('A e B: tutti i cammini arrivati in fondo (finito)', +uno(`SELECT COUNT(*) FROM {p}newsletter_corsi WHERE flusso_id IN (${F.A.id}, ${F.B.id}) AND stato = 'finito'`) === 12);
  ok('nessuno ha ricevuto "AU C dopo"', !posta().some(m => m.o === 'AU C dopo'));
  ok('il registro dei passi di dopo c\'è (email e fine), i contatori anche', +uno(`SELECT COUNT(*) FROM {p}newsletter_flussi_registro WHERE flusso_id = ${F.A.id} AND evento = 'email'`) === 6 && +uno(`SELECT COUNT(*) FROM {p}newsletter_flussi_registro WHERE flusso_id = ${F.A.id} AND evento = 'finito'`) === 6);
  // Nel pannello: l'elenco con gli stati, la tela di A senza errori, l'attività di C.
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 1440, height: 950 } })).newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/newsletter-flussi.php', { waitUntil: 'networkidle' });
  ok('elenco: A Attiva, C Inattiva, con i numeri', /attiva/i.test(await p.locator(`tr[data-flusso="${F.A.id}"]`).innerText()) && /inattiva/i.test(await p.locator(`tr[data-flusso="${F.C.id}"]`).innerText()));
  await p.goto(B + '/admin/newsletter-flussi.php?id=' + F.A.id, { waitUntil: 'networkidle' });
  ok('la tela del flusso di prima si apre con i suoi blocchi (innesco, 3 email, attesa, Sì o No)', await p.locator('.fl-nodo').count() === 6 && await p.locator('.fl-nodo[data-tipo=bivio]').count() === 1);
  await p.goto(B + `/admin/newsletter-flussi.php?id=${F.C.id}&scheda=attivita`, { waitUntil: 'networkidle' });
  ok('attività di C: i tolti nel registro con "automazione inattiva"', await p.locator('#fl-registro tr[data-evento=tolto]').filter({ hasText: 'automazione inattiva' }).count() === 6);
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); console.log(`${n - ko} su ${n} OK (interrotta)`); process.exit(1); });
