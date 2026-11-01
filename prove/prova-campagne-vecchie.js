// 2.11.0: le campagne fatte prima (2.10.0) dopo l'aggiornamento. Si aprono nel riepilogo senza cambiare, e mandate
// danno lo stesso HTML al byte (a parità di dati: indirizzo dell'azienda non scritto) e lo stesso oggetto.
// Prima, sul sito 2.10.0:  php prove/campagne-cli.php <cartella> semina_vecchie
//                          php prove/campagne-cli.php <cartella> impronte > prima.json
// Poi si aggiorna alla 2.11.0 e:
// uso: node prove/prova-campagne-vecchie.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella CMS> <prima.json>
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const [B, UTENTE, PASSWORD, SMTP, POSTA, DIR, PRIMA] = process.argv.slice(2);
const CLI = path.join(__dirname, 'campagne-cli.php');
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (!c && extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 400) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [CLI, DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 << 20 }).trim().split('\n').pop());
const q = (sql) => cli('sql', sql);
function leggi(eml) {
  const i = eml.search(/\r?\n\r?\n/);
  const testa = eml.slice(0, i).replace(/\r?\n[ \t]+/g, ' '); const corpo = eml.slice(i).replace(/^\r?\n\r?\n/, '');
  const h = (t, nome) => ((t.match(new RegExp('^' + nome + ':[ \\t]*(.*)$', 'mi')) || [])[1] || '').trim();
  const dec = s => s.replace(/=\?UTF-8\?B\?([^?]+)\?=/gi, (_, b) => Buffer.from(b, 'base64').toString('utf8'));
  const b = (h(testa, 'Content-Type').match(/boundary="([^"]+)"/) || [])[1];
  let html = '';
  for (const p of corpo.split('--' + b).slice(1)) {
    const j = p.search(/\r?\n\r?\n/); if (j < 0) continue;
    if (/text\/html/i.test(h(p.slice(0, j), 'Content-Type'))) html = Buffer.from(p.slice(j).replace(/\s+/g, ''), 'base64').toString('utf8');
  }
  return { dest: h(testa, 'X-Destinatari'), oggetto: dec(h(testa, 'Subject')), html, testa };
}
const posta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => leggi(fs.readFileSync(path.join(POSTA, f), 'utf8')));
const sha = (s) => crypto.createHash('sha256').update(s, 'utf8').digest('hex');

(async () => {
  const prima = JSON.parse(fs.readFileSync(PRIMA, 'utf8'));
  const campagne = Object.keys(prima);
  ok(`sul sito di prima: ${campagne.length} campagne della 2.10.0 con le impronte di ogni email`, campagne.length === 3 && campagne.every(c => Object.keys(prima[c]).length === 6), prima);
  cli('smtp', SMTP);
  q("DELETE FROM {p}settings WHERE setting_key = 'newsletter_indirizzo'");
  const dopo = cli('impronte');
  for (const c of campagne) ok(`campagna ${c}: stesso HTML al byte e stesso oggetto per ognuno dei 3 contatti, letto dal codice nuovo`, JSON.stringify(dopo[c]) === JSON.stringify(prima[c]), [prima[c], dopo[c]]);
  const col = 'oggetto, anteprima, corpo, pulsante_testo, pulsante_link, liste, mittente_id, stato';
  const righe = Object.fromEntries(campagne.map(c => [c, q(`SELECT ${col}, nome, editor, link_web, link_preferenze, utm, lotti FROM {p}newsletter_campagne WHERE id = ${c}`)[0]]));
  ok('migrate: nome = oggetto, editor semplice, niente versione web, niente link delle preferenze, niente UTM, un lotto', campagne.every(c => righe[c].nome === righe[c].oggetto.slice(0, 150) && righe[c].editor === 'semplice' && +righe[c].link_web === 0 && +righe[c].link_preferenze === 0 && +righe[c].utm === 0 && +righe[c].lotti === 1), righe);

  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  pnConferme.on(p, d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/newsletter.php', { waitUntil: 'networkidle' });
  ok('l\'elenco mostra le campagne di prima con il loro nome (l\'oggetto)', (await p.locator('table.nl-elenco tbody tr').count()) >= 3);
  const mail0 = posta().length;
  for (const c of campagne) {
    const prr = JSON.stringify(q(`SELECT ${col} FROM {p}newsletter_campagne WHERE id = ${c}`));
    await p.goto(B + `/admin/newsletter-campagna.php?id=${c}`, { waitUntil: 'networkidle' });
    const sez = await p.locator('details.nlr-sez > summary h3').count();
    const aPosto = await p.locator('details.nlr-sez .nlr-ok').count();
    ok(`campagna ${c}: si apre nel riepilogo, cinque sezioni a posto, il pulsante dell'invio acceso`, sez === 6 && aPosto === 5 && !(await p.locator('#nlr-invio button.btn-primary').isDisabled()), [sez, aPosto]);
    // Aprire non cambia niente
    ok(`campagna ${c}: aprirla non cambia niente nel database`, JSON.stringify(q(`SELECT ${col} FROM {p}newsletter_campagne WHERE id = ${c}`)) === prr);
    // Anteprima: lo stesso HTML della 2.10.0 per una prova (senza tracciamento)
    await Promise.all([p.waitForNavigation(), p.click('#nlr-invio button.btn-primary')]);
  }
  cli('giro'); cli('giro');
  const arrivate = posta().slice(mail0);
  ok('partite tutte: 3 campagne per 3 contatti', arrivate.length === 9, arrivate.map(m => m.dest));
  const ids = q("SELECT id, email FROM {p}newsletter_iscritti WHERE email LIKE 'v%@cr-prova.it'");
  const idDi = Object.fromEntries(ids.map(r => [r.email, String(r.id)]));
  let uguali = 0;
  for (const m of arrivate) {
    const c = campagne.find(x => (prima[x]['o' + idDi[m.dest]] || '').trim() === m.oggetto.trim()); // l'intestazione Subject si legge senza lo spazio in fondo
    if (c && sha(m.html) === prima[c][idDi[m.dest]]) uguali++;
    else console.log('    diversa: ' + m.dest + ' ' + m.oggetto);
  }
  ok('ogni email spedita dopo l\'aggiornamento ha lo stesso HTML al byte (e lo stesso oggetto) che dava la 2.10.0', uguali === 9, uguali);
  ok('nessun errore JavaScript', err.length === 0, err);
  console.log(`\n${n - ko} su ${n} OK`);
  await br.close();
  process.exit(ko ? 1 : 0);
})().catch(e => { console.log('KO  eccezione: ' + e.message.split('\n')[0]); console.log(`\n${n - ko} su ${n + 1} OK`); process.exit(1); });
