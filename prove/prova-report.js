// 2.14.0, fase 7 del piano Brevo (docs-sviluppo/brevo-confronto.md, capitoli 1.6, 1.15, 1.16): report delle campagne,
// statistiche di un periodo e delle liste, soglie, orario migliore. Provato come un utente, con eventi veri (pixel, clic e
// uscita dalle email ricevute dal server di posta finto) e altri dal CLI, contati a mano qui sotto.
//
// La campagna "RP Report" va a 12 contatti: r01-r10, rimbalza1 (rifiutato al RCPT: rimbalzo definitivo), rinvia1 (451 tre
// volte: rimbalzo temporaneo). Partite 10, non partite 2, provate 12. Poi:
//   r01 apre 3 volte, clicca L1 due volte e L2 una;  r02 apre 1, clicca L1 una;  r03 apre 2 (nome con una formula, per il CSV);
//   r04 clicca L2 una volta senza pixel (il clic vale come apertura);  r05 apre 1 ed esce ("Ricevo troppe email");
//   r06 apre 1 e segnala spam;  r07-r09 niente;  r10 rimbalza dopo la partenza (avviso del servizio).
// A mano: consegnate 9; aperture uniche 6, totali 9 (3+1+2+1+1+1); clic unici 3, totali 5; clic sulle aperture 3/6 = 50%;
// tasso di apertura 6/9 = 66,7%, di clic 3/9 = 33,3%; disiscrizioni 1/9 = 11,1%; spam 1/9 = 11,11%; rimbalzi definitivi 2
// (rimbalza1, r10) su 12 = 16,7%; temporanei 1 = 8,3%. Link: L1 2 persone 3 clic (60%), L2 2 persone 2 clic (40%).
// Elenchi: aperto 6, non aperto 3 (r07-r09), cliccato 3 (L1: 2), uscito 1, spam 1, rimbalzo 2, temporaneo 1.
// uso: node prove/prova-report.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella CMS> [cartella foto]
// Il server di posta finto con i rimbalzi: python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> rimbalzi &
// Lascia i dati di prova (RP ...): va su un sito da buttare.
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const [B, UTENTE, PASSWORD, SMTP, POSTA, DIR, FOTO] = process.argv.slice(2);
const CLI = path.join(__dirname, 'report-cli.php');
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (!c && extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 600) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [CLI, DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 256 << 20 }).trim().split('\n').pop());
const q = (sql) => cli('sql', sql);
const uno = (sql) => { const r = q(sql); return r && r[0] ? Object.values(r[0])[0] : null; };
const num = (sql) => { const v = uno(sql); return v === null ? NaN : +v; };

function leggi(eml) {
  const i = eml.search(/\r?\n\r?\n/);
  const testa = eml.slice(0, i).replace(/\r?\n[ \t]+/g, ' '); const corpo = eml.slice(i).replace(/^\r?\n\r?\n/, '');
  const h = (t, nome) => ((t.match(new RegExp('^' + nome + ':[ \\t]*(.*)$', 'mi')) || [])[1] || '').trim();
  const dec = s => s.replace(/=\?UTF-8\?B\?([^?]+)\?=/gi, (_, b) => Buffer.from(b, 'base64').toString('utf8'));
  const parte = (t, c) => /base64/i.test(h(t, 'Content-Transfer-Encoding')) ? Buffer.from(c.replace(/\s+/g, ''), 'base64').toString('utf8') : c;
  let html = '';
  const b = (h(testa, 'Content-Type').match(/boundary="([^"]+)"/) || [])[1];
  if (b) for (const p of corpo.split('--' + b).slice(1)) {
    const j = p.search(/\r?\n\r?\n/); if (j < 0) continue;
    if (/text\/html/i.test(h(p.slice(0, j).trim(), 'Content-Type'))) html = parte(p.slice(0, j).trim(), p.slice(j).trim());
  } else html = parte(testa, corpo);
  return { dest: h(testa, 'X-Destinatari'), oggetto: dec(h(testa, 'Subject')), html };
}
const posta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => leggi(fs.readFileSync(path.join(POSTA, f), 'utf8')));
const am = s => s.replace(/&amp;/g, '&');
const pixel = (html) => am((html.match(/<img src="([^"]*api\/nl\.php[^"]*)"/) || [])[1] || '');
const linkTracciati = (html) => [...html.matchAll(/href="([^"]*api\/nl\.php[^"]*)"/g)].map(m => am(m[1]));
const href = (html, re) => { const m = html.match(new RegExp('href="([^"]*' + re + '[^"]*)"')); return m ? am(m[1]) : ''; };
const oggi = () => uno("SELECT DATE_FORMAT(NOW(), '%Y-%m-%d')");
const domani = () => uno("SELECT DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 DAY), '%Y-%m-%d')");

(async () => {
  cli('pulisci');
  cli('smtp', SMTP);
  const S = cli('semina');
  const { c1, c2 } = S.campagne; const C = S.contatti;
  cli('giro'); cli('giro'); cli('giro');
  ok('semina e invio: 10 partite, 2 non partite (rimbalza1 subito, rinvia1 dopo tre tentativi)', num(`SELECT inviate FROM {p}newsletter_campagne WHERE id = ${c1}`) === 10 && num(`SELECT errori FROM {p}newsletter_campagne WHERE id = ${c1}`) === 2);
  ok('rimbalzo temporaneo (terzo tentativo con 451): un evento «soft» per la campagna', num(`SELECT COUNT(*) FROM {p}newsletter_eventi WHERE campagna_id = ${c1} AND tipo = 'soft' AND iscritto_id = ${C.rinvia1}`) === 1);

  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const fuori = await br.newContext();
  const req = fuori.request;
  const mail = Object.fromEntries(posta().filter(m => m.oggetto === 'RP Report oggetto').map(m => [m.dest.split('@')[0], m]));
  ok('le email della campagna sono arrivate a r01-r10', ['r01', 'r02', 'r03', 'r04', 'r05', 'r06', 'r07', 'r08', 'r09', 'r10'].every(k => mail[k]), Object.keys(mail));
  const L = (k) => { const ls = linkTracciati(mail[k].html); return { L1: ls.find(u => /contatti/.test(decodeURIComponent(u))), L2: ls.find(u => /esempio\.it/.test(decodeURIComponent(u))) }; };
  const apre = async (k, volte) => { for (let i = 0; i < volte; i++) await req.get(pixel(mail[k].html)); };
  const clicca = async (k, quale, volte) => { for (let i = 0; i < volte; i++) await req.get(L(k)[quale], { maxRedirects: 0 }); };
  await apre('r01', 3); await clicca('r01', 'L1', 2); await clicca('r01', 'L2', 1);
  await apre('r02', 1); await clicca('r02', 'L1', 1);
  await apre('r03', 2);
  await clicca('r04', 'L2', 1);
  await apre('r05', 1);
  const esci = href(mail.r05.html, '/newsletter-esci/');
  await req.post(esci, { form: {} }); await req.post(esci, { form: { motivo: 'troppe' } });
  await apre('r06', 1);
  cli('interno', 'spam', c1, C.r06);
  cli('interno', 'rimbalzo', c1, C.r10);
  ok('clic ripetuti salvati: r01 su L1 ha volte = 2, la sua apertura volte = 3 (i clic non la aumentano)', num(`SELECT volte FROM {p}newsletter_eventi WHERE campagna_id = ${c1} AND iscritto_id = ${C.r01} AND tipo = 'clic' AND url LIKE '%contatti%'`) === 2 && num(`SELECT volte FROM {p}newsletter_eventi WHERE campagna_id = ${c1} AND iscritto_id = ${C.r01} AND tipo = 'apertura'`) === 3);
  ok('una riga per persona e link, come prima: conteggi unici invariati (r01 ha 2 righe di clic)', num(`SELECT COUNT(*) FROM {p}newsletter_eventi WHERE campagna_id = ${c1} AND iscritto_id = ${C.r01} AND tipo = 'clic'`) === 2);
  const N = cli('numeri', c1);
  const atteso = { destinatari: 12, inviate: 10, errori: 2, provate: 12, consegnate: 9, apertura: 6, apertura_tot: 9, clic: 3, clic_tot: 5, uscita: 1, spam: 1, rimbalzo: 2, soft: 1, t_apertura: 66.7, t_clic: 33.3, t_ctor: 50, t_uscita: 11.1, t_spam: 11.11, t_rimbalzo: 16.7, t_soft: 8.3 };
  ok('motore: ogni numero e tasso uguale al conto a mano', Object.entries(atteso).every(([k, v]) => N[k] === v), Object.fromEntries(Object.keys(atteso).map(k => [k, N[k]])));
  ok('motore: le tre soglie accese (rimbalzi, uscite, spam)', JSON.stringify(Object.keys(N.soglie).sort()) === '["rimbalzi","spam","uscite"]', N.soglie);

  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  pnConferme.on(p, d => d.accept());
  const vai = async (u) => p.goto(B + (u.startsWith('/') ? u : '/admin/' + u), { waitUntil: 'networkidle' });
  const flash = async () => (await p.locator('.flash, [role=alert]').allInnerTexts()).join(' | ');
  const invia = async (sel) => { await Promise.all([p.waitForNavigation(), p.click(sel)]); };
  const foto = async (nome, sel) => { if (!FOTO) return; if (sel) await p.locator(sel).first().screenshot({ path: path.join(FOTO, nome + '.png') }); else await p.screenshot({ path: path.join(FOTO, nome + '.png'), fullPage: true }); };
  const tessera = async (k, dove = '') => (await p.locator(`${dove} [data-numero="${k}"] b`).first().innerText()).trim();
  const celle = async (sel) => p.locator(sel).first().evaluate(r => [...r.children].map(c => c.textContent.trim()).join('|')).catch(() => '');
  const tasso = async (k, dove = '') => (await p.locator(`${dove} [data-numero="${k}"] .nlr-tasso`).first().innerText()).trim();
  await vai('/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  // ===== Report, Panoramica
  await vai(`newsletter-report.php?id=${c1}`);
  const sch = await p.locator('nav.imp-schede a').allInnerTexts();
  ok('report a schede: Panoramica, Aperture, Clic, Disiscrizioni, Rimbalzi', JSON.stringify(sch.map(s => s.trim())) === '["Panoramica","Aperture","Clic","Disiscrizioni","Rimbalzi"]', sch);
  const tess = {}; for (const k of ['destinatari', 'inviate', 'consegnate', 'apertura', 'apertura_tot', 'clic', 'clic_tot', 'uscita', 'rimbalzo', 'soft', 'spam']) tess[k] = await tessera(k, '#nlr-panoramica');
  ok('Panoramica: i numeri come a mano (12, 10, 9, 6, 9, 3, 5, 1, 2, 1, 1)', JSON.stringify(tess) === JSON.stringify({ destinatari: '12', inviate: '10', consegnate: '9', apertura: '6', apertura_tot: '9', clic: '3', clic_tot: '5', uscita: '1', rimbalzo: '2', soft: '1', spam: '1' }), tess);
  const tas = {}; for (const k of ['apertura', 'clic', 'clic_su_aperture', 'uscita', 'rimbalzo', 'soft', 'spam']) tas[k] = await tasso(k, '#nlr-panoramica');
  ok('Panoramica: i tassi come a mano (66,7% e 33,3% delle consegnate, 50% di chi ha aperto, 11,1%, 16,7% e 8,3% delle provate, 11,11%)',
    /^66,7% delle consegnate$/.test(tas.apertura) && /^33,3% delle consegnate$/.test(tas.clic) && /^50% di chi ha aperto$/.test(tas.clic_su_aperture) && /11,1% delle consegnate/.test(tas.uscita) && /16,7% delle provate/.test(tas.rimbalzo) && /^8,3% delle provate$/.test(tas.soft) && /11,11% delle consegnate/.test(tas.spam), tas);
  const soglie = await p.locator('#nlr-soglie li').evaluateAll(l => l.map(x => x.dataset.soglia));
  ok('soglie: il riquadro «Da guardare» con rimbalzi, disiscrizioni e spam, e il segno «!» sulle tre tessere', JSON.stringify(soglie) === '["rimbalzi","uscite","spam"]' && await p.locator('#nlr-panoramica .nlr-oltre').count() === 3 && /!/.test(await p.locator('#nlr-panoramica [data-numero=spam] .nlr-tasso').innerText()), soglie);
  await foto('report-panoramica', '#nlr-panoramica');

  // ===== Aperture
  await vai(`newsletter-report.php?id=${c1}&scheda=aperture`);
  ok('Aperture: 6 uniche, 9 totali, 3 non hanno aperto', await tessera('apertura') === '6' && await tessera('apertura_tot') === '9' && await tessera('non_aperto') === '3');
  const elN = async () => +(await p.locator('[data-elenco-n]').getAttribute('data-elenco-n'));
  const elEmail = async () => (await p.locator('.nlr-elenco-tabella tbody td:first-child').allInnerTexts()).map(s => s.split('@')[0]).sort();
  ok('elenco «Hanno aperto»: 6 contatti, con il link alla scheda e le volte (r01: 3)', await elN() === 6 && JSON.stringify(await elEmail()) === '["r01","r02","r03","r04","r05","r06"]'
    && await p.locator(`.nlr-elenco-tabella a[href="newsletter-contatto.php?id=${C.r01}"]`).count() === 1 && /\t3$|\s3$/.test(await p.locator(`.nlr-elenco-tabella tr:has(a[href="newsletter-contatto.php?id=${C.r01}"])`).innerText()));
  await vai(`newsletter-report.php?id=${c1}&scheda=aperture&elenco=non_aperto`);
  ok('elenco «Non hanno aperto»: r07, r08, r09 (r10 è rimbalzato)', await elN() === 3 && JSON.stringify(await elEmail()) === '["r07","r08","r09"]', await elEmail());

  // ===== Clic e mappa
  await vai(`newsletter-report.php?id=${c1}&scheda=clic`);
  ok('Clic: 3 unici, 5 totali, clic sulle aperture 50%', await tessera('clic') === '3' && await tessera('clic_tot') === '5' && await tessera('clic_su_aperture') === '50%');
  const righeLink = await p.locator('#nlr-link-tabella tbody tr').evaluateAll(r => r.map(x => [...x.querySelectorAll('td')].slice(0, 4).map(td => td.innerText.trim())));
  ok('link: L1 2 persone 3 clic 60%, L2 2 persone 2 clic 40%', righeLink.length === 2 && /contatti/.test(righeLink[0][0]) && righeLink[0].slice(1).join('|') === '2|3|60%' && /esempio\.it\/fuori/.test(righeLink[1][0]) && righeLink[1].slice(1).join('|') === '2|2|40%', righeLink);
  const mappa = await p.locator('#nlr-mappa iframe').getAttribute('srcdoc');
  ok('mappa dei clic: sull\'anteprima ogni link ha il suo numero e la percentuale', /3 clic, 2 persone, 60%/.test(mappa) && /2 clic, 2 persone, 40%/.test(mappa) && !/Esci dalla newsletter<\/a><\/span><span data-mappa/.test(mappa));
  const fm = p.frameLocator('#nlr-mappa iframe');
  ok('mappa: i numeri si vedono nell\'anteprima', await fm.locator('[data-mappa]').count() === 2);
  await foto('report-mappa', '#nlr-mappa');
  const L1 = righeLink[0][0];
  await vai(`newsletter-report.php?id=${c1}&scheda=clic&elenco=cliccato`);
  ok('elenco «Hanno cliccato»: 3 contatti (r01 con 3 clic su 2 link)', await elN() === 3 && /3\s+2 link/.test(await p.locator(`.nlr-elenco-tabella tr:has(a[href="newsletter-contatto.php?id=${C.r01}"])`).innerText()));
  await p.selectOption('#nlr-url', L1); await invia('#nlr-elenco form[method=get] button');
  ok('elenco per link: chi ha cliccato L1 sono r01 e r02', await elN() === 2 && JSON.stringify(await elEmail()) === '["r01","r02"]');

  // ===== CSV
  const csv = async (u) => { const r = await ctx.request.get(B + '/admin/' + u); return { stato: r.status(), tipo: r.headers()['content-type'] || '', testo: (await r.text()).replace(/^﻿/, '') }; };
  const x1 = await csv(`newsletter-report.php?id=${c1}&csv=aperto`);
  const righe1 = x1.testo.trim().split(/\r?\n/);
  ok('CSV «Hanno aperto»: un file CSV con la riga dei titoli e 6 contatti', x1.stato === 200 && /text\/csv/.test(x1.tipo) && righe1.length === 7 && righe1[0] === 'EMAIL;NOME;COGNOME;QUANDO;VOLTE;DETTAGLIO', righe1.slice(0, 2));
  ok('CSV: la cella con una formula esce neutralizzata con l\'apostrofo', righe1.some(r => r.startsWith('r03@rp-prova.it;') && r.includes(";\"'=HYPERLINK(\"\"http://x.it\"\")\";")), righe1.find(r => r.startsWith('r03')));
  const x2 = await csv(`newsletter-report.php?id=${c1}&csv=report`);
  ok('CSV del report: i numeri e i tassi, e i due link', /"Aperture uniche";6;66,7%/.test(x2.testo) && /"Clic totali";5;/.test(x2.testo) && /Consegnate;9;/.test(x2.testo) && /esempio\.it\/fuori\?a=1;2;2;40%/.test(x2.testo), x2.testo.slice(0, 400));
  const x3 = await csv(`newsletter-report.php?id=${c1}&csv=cliccato&url=${encodeURIComponent(L1)}`);
  ok('CSV per link: chi ha cliccato L1 (2 righe)', x3.testo.trim().split(/\r?\n/).length === 3);
  const tutti = { aperto: 6, non_aperto: 3, cliccato: 3, uscito: 1, spam: 1, rimbalzo: 2, soft: 1 };
  const conti = {}; for (const k of Object.keys(tutti)) conti[k] = (await csv(`newsletter-report.php?id=${c1}&csv=${k}`)).testo.trim().split(/\r?\n/).length - 1;
  ok('CSV di ogni elenco: lo stesso numero di righe del conto a mano', JSON.stringify(conti) === JSON.stringify(tutti), conti);

  // ===== Disiscrizioni e rimbalzi
  await vai(`newsletter-report.php?id=${c1}&scheda=uscite`);
  ok('Disiscrizioni: 1 (11,1%) e 1 spam; il motivo «Ricevo troppe email: 1»', await tessera('uscita') === '1' && await tessera('spam') === '1' && /Ricevo troppe email\s+1/.test(await p.locator('#nlr-motivi tr[data-motivo=troppe]').innerText()));
  ok('elenco «Si sono disiscritti»: r05 con il motivo', await elN() === 1 && /r05@rp-prova\.it[\s\S]*Ricevo troppe email/.test(await p.locator('.nlr-elenco-tabella tbody').innerText()));
  await vai(`newsletter-report.php?id=${c1}&scheda=uscite&elenco=spam`);
  ok('elenco «Hanno segnalato spam»: r06', await elN() === 1 && JSON.stringify(await elEmail()) === '["r06"]');
  await vai(`newsletter-report.php?id=${c1}&scheda=rimbalzi`);
  ok('Rimbalzi: 2 definitivi (16,7%), 1 temporaneo, 12 provate', await tessera('rimbalzo') === '2' && await tessera('soft') === '1' && await tessera('provate') === '12');
  ok('elenco dei definitivi: rimbalza1 (con l\'errore del server) e r10', await elN() === 2 && JSON.stringify(await elEmail()) === '["r10","rimbalza1"]' && /550/.test(await p.locator('.nlr-elenco-tabella tbody').innerText()));
  await vai(`newsletter-report.php?id=${c1}&scheda=rimbalzi&elenco=soft`);
  ok('elenco dei temporanei: rinvia1, con il 451', await elN() === 1 && /rinvia1[\s\S]*451/.test(await p.locator('.nlr-elenco-tabella tbody').innerText()));

  // ===== Salva come lista e come segmento
  await vai(`newsletter-report.php?id=${c1}&scheda=aperture&elenco=aperto`);
  await p.fill('#nlr-salva-nome', 'RP Aperti'); await invia('#nlr-elenco button[value=lista]');
  const lAp = num("SELECT id FROM {p}newsletter_liste WHERE nome = 'RP Aperti'");
  ok('Salva come lista: «RP Aperti» con i 6 che hanno aperto', lAp > 0 && num(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE lista_id = ${lAp}`) === 6 && /creata con 6 contatti/.test(await flash()), await flash());
  await p.fill('#nlr-salva-nome', 'RP Aperti'); await invia('#nlr-elenco button[value=lista]');
  ok('stesso nome: errore detto, niente lista doppia', /Esiste già una lista/.test(await flash()) && num("SELECT COUNT(*) FROM {p}newsletter_liste WHERE nome = 'RP Aperti'") === 1);
  await vai(`newsletter-report.php?id=${c1}&scheda=clic&elenco=cliccato&url=${encodeURIComponent(L1)}`);
  await p.fill('#nlr-salva-nome', 'RP Clic L1'); await invia('#nlr-elenco button[value=segmento]');
  const seg = q("SELECT conteggio, condizioni FROM {p}newsletter_segmenti WHERE nome = 'RP Clic L1'");
  ok('Salva come segmento (link L1): segmento con la condizione sul link e 2 contatti', seg.length === 1 && +seg[0].conteggio === 2 && /"campo":"link"/.test(seg[0].condizioni), seg);
  await vai(`newsletter-report.php?id=${c1}&scheda=aperture&elenco=non_aperto`);
  ok('«Non hanno aperto»: solo «Salva come lista» (niente segmento)', await p.locator('#nlr-elenco button[value=segmento]').count() === 0 && await p.locator('#nlr-elenco button[value=lista]').count() === 1);

  // ===== Riepilogo della campagna ed elenco delle campagne
  await vai(`newsletter-campagna.php?id=${c1}`);
  ok('riepilogo: il riquadro Report ha le consegnate (9), i tassi sulle consegnate, le soglie e il link al report completo', await tessera('consegnate', '#nlr-report') === '9' && /66,7%/.test(await tasso('apertura', '#nlr-report')) && await p.locator('#nlr-report #nlr-soglie').count() === 1 && await p.locator(`#nlr-report a[href="newsletter-report.php?id=${c1}"]`).count() === 1);
  await vai('newsletter.php');
  ok('elenco delle campagne: «Report» porta alla pagina del report', await p.locator(`a[href="newsletter-report.php?id=${c1}"]`).count() >= 1);
  await vai(`newsletter-statistiche.php?campagna=${c1}`);
  ok('gli indirizzi di prima (Statistiche?campagna=) portano al report', p.url().includes(`newsletter-report.php?id=${c1}`));

  // ===== A/B
  const cab = cli('semina_ab');
  await vai(`newsletter-report.php?id=${cab}`);
  const abT = await p.locator('#nlr-ab-risultati').innerText().catch(() => '');
  ok('report di un test A/B: il riquadro con le due versioni, ha vinto la A (1 su 2, 50%), B 0 su 2', /Ha vinto la versione A/.test(abT) && /1 \(50%\)/.test(await p.locator('#nlr-ab-risultati tr[data-versione=a]').innerText()) && /0 \(0%\)/.test(await p.locator('#nlr-ab-risultati tr[data-versione=b]').innerText()), abT.slice(0, 300));
  ok('report del test A/B: le tessere dei numeri ci sono (4 inviate, 1 apertura)', await tessera('inviate', '#nlr-panoramica') === '4' && await tessera('apertura', '#nlr-panoramica') === '1');

  // ===== Statistiche di un periodo
  q(`UPDATE {p}newsletter_campagne SET invio_at = '2025-03-10 10:00:00' WHERE id = ${c2}`);
  const m2 = posta().find(m => m.oggetto === 'RP Due oggetto' && /^r01@/.test(m.dest));
  await req.get(pixel(m2.html));
  await vai('newsletter-statistiche.php?periodo=scelto&da=2025-03-01&a=2025-03-31');
  const rT = await p.locator('#st-tabella tbody tr').evaluateAll(r => r.map(x => x.dataset.campagna));
  ok('periodo scelto (marzo 2025): solo la campagna di quel giorno', JSON.stringify(rT) === JSON.stringify([String(c2)]), rT);
  ok('periodo: totali di marzo (3 consegnate, 1 apertura, 33,3%)', await tessera('consegnate', '#st-totali') === '3' && await tessera('apertura', '#st-totali') === '1' && /^33,3%/.test(await tasso('apertura', '#st-totali')));
  ok('andamento per giorno: il grafico SVG e la riga del 10 marzo (3, 1, 33,3%)', await p.locator('#st-andamento svg.st-grafico polyline').count() === 2
    && (await celle('#st-andamento-tabella tr[data-passo="2025-03-10"]')) === '10/03|3|1|33,3%|0|0%', await celle('#st-andamento-tabella tr[data-passo="2025-03-10"]'));
  await vai('newsletter-statistiche.php');
  const r30 = await p.locator('#st-tabella tbody tr').evaluateAll(r => r.map(x => x.dataset.campagna));
  ok('ultimi 30 giorni (il predefinito): c\'è RP Report, non RP Due', r30.includes(String(c1)) && !r30.includes(String(c2)), r30);
  ok('tabella del periodo: lo spam di RP Report oltre la soglia, con il segno e la parola', await p.locator(`#st-tabella tr[data-campagna="${c1}"] .st-spam .kw-bad`).count() === 1 && /!/.test(await p.locator(`#st-tabella tr[data-campagna="${c1}"] .st-spam`).innerText()) && /0,2%/.test(await p.locator('#st-campagne').innerText()));
  await foto('statistiche', '#st-totali');
  await vai(`newsletter-statistiche.php?periodo=scelto&da=2025-03-01&a=${oggi()}`);
  await p.check(`#st-tabella tr[data-campagna="${c1}"] input[name="confronta[]"]`); await p.check(`#st-tabella tr[data-campagna="${c2}"] input[name="confronta[]"]`);
  await invia('#st-campagne button[type=submit]');
  const conf = await p.locator('#st-confronto tr[data-voce=apertura]').innerText().catch(() => '');
  ok('confronto fra due campagne: aperture 6 (66,7%) e 1 (33,3%) affiancate', /6 · 66,7%/.test(conf) && /1 · 33,3%/.test(conf), conf);
  ok('periodo lungo: andamento per mese', /per mese/.test(await p.locator('#st-andamento h3').innerText()));
  // Parametri manipolati
  for (const [u, re] of [['periodo=abc', /non esiste/], ['periodo=scelto&da=2025-13-45&a=2025-03-01', /non vanno bene/], ['periodo=scelto&da=2025-03-31&a=2025-03-01', /non vanno bene/], ['periodo[]=1', /non esiste/], ['scheda=liste&lista=999999', /non esiste/]]) {
    await vai('newsletter-statistiche.php?' + u);
    ok(`parametri storti (${u}): la pagina lo dice e mostra gli ultimi 30 giorni`, re.test(await p.locator('#st-periodo-form').innerText()) && err.length === 0 && !/Fatal|Warning/.test(await p.content()));
  }

  // ===== Statistiche della lista
  const lr = S.liste.report;
  q(`UPDATE {p}newsletter_iscritti_liste SET aggiunto = DATE_SUB(NOW(), INTERVAL 10 DAY) WHERE lista_id = ${lr} AND iscritto_id IN (${C.r01}, ${C.r02})`);
  await vai(`newsletter-statistiche.php?scheda=liste&periodo=7&lista=${lr}`);
  const lst = { iscritti: await tessera('iscritti'), nuovi: await tessera('nuovi'), usciti: await tessera('usciti'), netto: await tessera('netto') };
  ok('lista RP Report, ultimi 7 giorni: 10 entrati (r01 e r02 erano entrati 10 giorni fa), 4 usciti, 8 iscritti, +6', JSON.stringify(lst) === '{"iscritti":"8","nuovi":"10","usciti":"4","netto":"+6"}', lst);
  await vai(`newsletter-statistiche.php?scheda=liste&periodo=30&lista=${lr}`);
  ok('lista, ultimi 30 giorni: 12 entrati, +8; il grafico e la tabella per giorno', await tessera('nuovi') === '12' && await tessera('netto') === '+8' && await p.locator('#st-lista svg.st-grafico').count() === 1 && await p.locator('#st-lista-tabella tbody tr').count() === 30);
  ok('lista: le campagne mandate a lei nel periodo, con i numeri', /RP Report/.test(await p.locator('#st-lista-campagne').innerText()));
  await vai('newsletter-statistiche.php?scheda=liste&periodo=7');
  ok('tutti i contatti: i numeri ci sono, senza errori', await p.locator('#st-lista [data-numero=iscritti]').count() === 1 && err.length === 0);

  // ===== Orario migliore
  const O = cli('semina_orario');
  const ore = cli('orario_ore', Object.values(O.contatti).join(','));
  ok('orario migliore calcolato per contatto: o1 alle 18 (2 su 3), o2 alle 7 (pari: la più recente), o3 nessuno (1 apertura), o4 nessuno (troppo vecchie), o5 nessuno', ore[O.contatti.o1] === 18 && ore[O.contatti.o2] === 7 && !(O.contatti.o3 in ore) && !(O.contatti.o4 in ore) && !(O.contatti.o5 in ore), ore);
  q(`INSERT INTO {p}newsletter_campagne (nome, oggetto, corpo, liste, stato, creato, aggiornato) VALUES ('RP Orario', 'RP Orario oggetto', '<p>Ciao</p>', '${O.lista}', 'bozza', NOW(), NOW())`);
  const c3 = num("SELECT id FROM {p}newsletter_campagne WHERE nome = 'RP Orario'");
  await vai(`newsletter-campagna.php?id=${c3}#nlr-invio`);
  ok('Programma: c\'è «All\'orario migliore» con il giorno, l\'ora per chi non ha dati e la spiegazione', await p.locator('#nlr-invio input[name=quando][value=migliore]').count() === 1 && await p.locator('#nlr-om-giorno').count() === 1 && await p.locator('#nlr-om-ora option').count() === 24 && /Come si calcola l'orario migliore/.test(await p.locator('#nlr-invio').innerText()));
  await p.check('#nlr-invio input[name=quando][value=migliore]');
  await p.evaluate(() => { const g = document.getElementById('nlr-om-giorno'); g.removeAttribute('min'); g.value = '2020-01-01'; });
  await invia('#nlr-invio button.btn-primary');
  ok('giorno passato: rifiutato con la frase, la campagna resta bozza', /già passato/.test(await flash()) && uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c3}`) === 'bozza', await flash());
  ok('dopo l\'errore la sezione dice quanti hanno un orario loro: 2 dei 5, alle 07:00 e alle 18:00', /2 dei 5 destinatari hanno un orario loro: 07:00 \(1\), 18:00 \(1\)/.test(await p.locator('#nlr-om-conti').innerText().catch(() => '')), await p.locator('#nlr-om-conti').innerText().catch(() => ''));
  await p.check('#nlr-invio input[name=quando][value=migliore]');
  await p.fill('#nlr-om-giorno', domani()); await p.selectOption('#nlr-om-ora', '11');
  await invia('#nlr-invio button.btn-primary');
  const r3 = q(`SELECT stato, invio_at, orario_migliore, orario_ora FROM {p}newsletter_campagne WHERE id = ${c3}`)[0];
  ok('programmata all\'orario migliore per domani: parte a mezzanotte, chi non ha dati alle 11', r3.stato === 'programmata' && r3.invio_at === domani() + ' 00:00:00' && +r3.orario_migliore === 1 && +r3.orario_ora === 11 && /all'orario migliore/.test(await flash()), [r3, await flash()]);
  ok('il riquadro di stato lo dice', /all'orario migliore di ogni contatto \(chi non ha un orario suo alle 11:00/.test(await p.locator('.nlr-stato-box').first().innerText()));
  const log3 = cli('avvia', c3);
  const coda3 = Object.fromEntries(q(`SELECT iscritto_id, dopo FROM {p}newsletter_coda WHERE campagna_id = ${c3}`).map(r => [r.iscritto_id, r.dopo]));
  const D = domani();
  ok('all\'avvio la coda ha le ore giuste: o1 alle 18, o2 alle 7, gli altri alle 11 del giorno scelto', coda3[O.contatti.o1] === `${D} 18:00:00` && coda3[O.contatti.o2] === `${D} 07:00:00` && ['o3', 'o4', 'o5'].every(k => coda3[O.contatti[k]] === `${D} 11:00:00`) && /2 con il loro orario, gli altri alle 11:00/.test(log3), [coda3, log3]);
  const prima3 = posta().filter(m => m.oggetto === 'RP Orario oggetto').length;
  cli('giro');
  ok('il cron non manda niente prima dell\'ora di ognuno', posta().filter(m => m.oggetto === 'RP Orario oggetto').length === prima3 && uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c3}`) === 'in_invio');
  q(`UPDATE {p}newsletter_coda SET dopo = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE campagna_id = ${c3} AND iscritto_id = ${O.contatti.o1}`); // le 18 di domani arrivano
  cli('giro');
  const mand3 = posta().filter(m => m.oggetto === 'RP Orario oggetto').map(m => m.dest);
  ok('arrivata la sua ora, parte solo l\'email di o1; la campagna resta in invio per gli altri', JSON.stringify(mand3) === '["o1@rp-prova.it"]' && uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c3}`) === 'in_invio', mand3);
  // Oggi: chi ha l'ora già passata parte subito
  q(`INSERT INTO {p}newsletter_campagne (nome, oggetto, corpo, liste, stato, creato, aggiornato) VALUES ('RP Orario oggi', 'RP Orario oggi', '<p>Ciao</p>', '${O.lista}', 'bozza', NOW(), NOW())`);
  const c5 = num("SELECT id FROM {p}newsletter_campagne WHERE nome = 'RP Orario oggi'");
  await vai(`newsletter-campagna.php?id=${c5}#nlr-invio`);
  await p.check('#nlr-invio input[name=quando][value=migliore]'); await p.fill('#nlr-om-giorno', oggi()); await p.selectOption('#nlr-om-ora', '0');
  await invia('#nlr-invio button.btn-primary');
  cli('giro');
  const r5 = q(`SELECT q.dopo, q.stato, (q.dopo IS NULL OR q.dopo > NOW()) AS giusto FROM {p}newsletter_coda q WHERE q.campagna_id = ${c5}`);
  ok('oggi: la campagna parte subito, chi ha l\'ora passata (le 00:00 di chi non ha dati) la riceve subito, gli altri alla loro ora', r5.length === 5 && r5.every(x => +x.giusto === 1) && r5.filter(x => x.stato === 'inviata').length >= 3, r5);

  // ===== 20000 destinatari
  const G = cli('semina_grande', 20000);
  const tempo = async (u) => { const t0 = Date.now(); const r = await ctx.request.get(B + '/admin/' + u); await r.body(); return [Date.now() - t0, r.status()]; };
  const tempi = {};
  for (const [k, u] of Object.entries({ panoramica: `newsletter-report.php?id=${G.campagna}`, aperture: `newsletter-report.php?id=${G.campagna}&scheda=aperture&elenco=non_aperto`, clic: `newsletter-report.php?id=${G.campagna}&scheda=clic`, uscite: `newsletter-report.php?id=${G.campagna}&scheda=uscite`, rimbalzi: `newsletter-report.php?id=${G.campagna}&scheda=rimbalzi`, statistiche: 'newsletter-statistiche.php?periodo=30', liste: `newsletter-statistiche.php?scheda=liste&periodo=30&lista=${G.lista}`, riepilogo: `newsletter-campagna.php?id=${G.campagna}` })) {
    await tempo(u); tempi[k] = await tempo(u);
  }
  ok('20000 destinatari: ogni pagina del report e delle statistiche risponde in meno di un secondo', Object.values(tempi).every(([ms, st]) => st === 200 && ms < 1000), tempi);
  console.log('     tempi (ms): ' + JSON.stringify(Object.fromEntries(Object.entries(tempi).map(([k, v]) => [k, v[0]]))));
  const NG = cli('numeri', G.campagna);
  const conta = (tipo) => num(`SELECT COUNT(DISTINCT iscritto_id) FROM {p}newsletter_eventi WHERE campagna_id = ${G.campagna} AND tipo = '${tipo}'`);
  const gA = conta('apertura'), gR = conta('rimbalzo');
  ok('20000: i numeri tornano con il seme (circa 8000 aperture, 3000 clic, 200 uscite, 100 rimbalzi; consegnate = 20000 meno i rimbalzati)', NG.inviate === 20000 && NG.apertura === gA && gA > 7900 && NG.clic === conta('clic') && NG.uscita === conta('uscita') && NG.rimbalzo === gR && NG.consegnate === 20000 - gR, NG);
  const tc = Date.now(); const xg = await csv(`newsletter-report.php?id=${G.campagna}&csv=aperto`);
  ok('20000: il CSV di chi ha aperto ha una riga per ognuno', xg.testo.trim().split(/\r?\n/).length === gA + 1);
  console.log('     CSV di ' + gA + ' righe in ' + (Date.now() - tc) + ' ms');
  const to = cli('tempo_orario', G.campagna);
  ok('20000: il calcolo dell\'orario migliore per tutti i destinatari sta sotto un secondo', to.n === 20000 && to.ms < 1000, to);
  cli('pulisci_grande'); // le prove che vengono dopo (Contatti a pagine) non devono vedere i 20000 contatti

  // ===== 390 px
  const tel = await br.newContext({ viewport: { width: 390, height: 800 } });
  const pt = await tel.newPage();
  await pt.goto(B + '/admin/login.php'); await pt.fill('#username', UTENTE); await pt.fill('#password', PASSWORD);
  await Promise.all([pt.waitForNavigation(), pt.click('button[type=submit]')]);
  const larghi = [];
  for (const u of [`newsletter-report.php?id=${c1}`, `newsletter-report.php?id=${c1}&scheda=clic`, `newsletter-report.php?id=${c1}&scheda=aperture`, `newsletter-report.php?id=${c1}&scheda=uscite`, `newsletter-report.php?id=${c1}&scheda=rimbalzi`, `newsletter-report.php?id=${cab}`, 'newsletter-statistiche.php', `newsletter-statistiche.php?periodo=scelto&da=2025-03-01&a=${oggi()}&confronta[]=${c1}&confronta[]=${c2}`, `newsletter-statistiche.php?scheda=liste&lista=${lr}`, `newsletter-campagna.php?id=${c5}#nlr-invio`]) {
    await pt.goto(B + '/admin/' + u, { waitUntil: 'networkidle' });
    const w = await pt.evaluate(() => document.documentElement.scrollWidth);
    if (w > 390) larghi.push([u, w]);
  }
  ok('390 px: nessuna pagina del report, delle statistiche e del Programma scorre di lato', larghi.length === 0, larghi);
  if (FOTO) { await pt.goto(B + `/admin/newsletter-report.php?id=${c1}`, { waitUntil: 'networkidle' }); await pt.screenshot({ path: path.join(FOTO, 'report-390.png'), fullPage: true }); }
  ok('nessun errore JavaScript nelle pagine', err.length === 0, err);
  await br.close();
  console.log(`${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); console.log(`${n - ko} su ${n} OK (interrotta)`); process.exit(1); });
