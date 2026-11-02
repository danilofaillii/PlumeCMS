// 2.16.0, fase 9 del piano Brevo: i form a cinque passi e le pagine pubbliche (includes/form-brevo.php,
// includes/newsletter-consenso.php, admin/form.php, admin/newsletter-consenso.php).
// Su un CMS installato (radice, cartella o aggiornato), con il server di posta finto acceso:
//   python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> &
// La prova fa partire un secondo server PHP (un altro sito, su <porta2>) per l'iframe e i codici HTML incollati altrove.
// Percorsi: il costruttore passo per passo (Avanti), la casella solo con un attributo Sì/No, GDPR con la casella vuota,
// pagina ospitata con frame-ancestors * (e il resto del sito no), Iscrizione a più liste con la principale (e una lista non
// del form rifiutata), conferma doppia (con il modello di doppio opt-in e la pagina dopo il clic), semplice, nessuna,
// "Aggiorna i contatti esistenti", iframe su un'altra origine, codice HTML con il captcha da un'altra origine, HTML
// semplice senza JavaScript (domanda di controllo), QR letto da un lettore scritto a parte, disiscrizione da una sola lista
// e pagina di disiscrizione scelta nella campagna, centro preferenze con gruppi di consenso e tracciamento, link di un altro
// contatto, form di profilo come centro preferenze, form di disiscrizione, form pop-up, consenso al tracciamento (niente
// pixel, niente eventi, segmenti), registro del consenso. Alla fine pulisce.
// uso: node prove/prova-form-brevo.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella del CMS> <porta2> [cartella foto]
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const fs = require('fs');
const path = require('path');
const { execFileSync, spawn } = require('child_process');
const [B, UTENTE, PASSWORD, SMTP, POSTA, DIR, PORTA2, FOTO] = process.argv.slice(2);
const CLI = path.join(__dirname, 'form-brevo-cli.php');
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' && !c ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const dorme = ms => new Promise(r => setTimeout(r, ms));
const cli = (...a) => { try { return JSON.parse(execFileSync('php', [CLI, DIR, ...a.map(String)], { stdio: ['ignore', 'pipe', 'pipe'] }).toString().trim().split('\n').pop()); } catch (e) { return { errore: String(e.stderr || e.message).slice(0, 300) }; } };
const q = s => cli('sql', s);
const uno = s => { const r = q(s); return Array.isArray(r) && r[0] ? Object.values(r[0])[0] : null; };
const ORIGINE = B.replace(/^(https?:\/\/[^/]+).*$/, '$1');
const ALTRO = 'http://127.0.0.1:' + PORTA2;

function leggi(eml) {
  const i = eml.search(/\r?\n\r?\n/);
  const testa = eml.slice(0, i).replace(/\r?\n[ \t]+/g, ' '); const corpo = eml.slice(i).replace(/^\r?\n\r?\n/, '');
  const h = (t, nome) => ((t.match(new RegExp('^' + nome + ':[ \\t]*(.*)$', 'mi')) || [])[1] || '').trim();
  const dec = s => s.replace(/=\?UTF-8\?B\?([^?]+)\?=\s*/gi, (_, b) => Buffer.from(b, 'base64').toString('utf8'));
  const parte = (t, c) => /base64/i.test(h(t, 'Content-Transfer-Encoding')) ? Buffer.from(c.replace(/\s+/g, ''), 'base64').toString('utf8') : c;
  const ct = h(testa, 'Content-Type');
  let html = '';
  const b = (ct.match(/boundary="([^"]+)"/) || [])[1];
  if (b) {
    for (const p of corpo.split('--' + b).slice(1)) {
      const j = p.search(/\r?\n\r?\n/); if (j < 0) continue;
      const pt = p.slice(0, j).trim(); const pc = p.slice(j).trim();
      if (/text\/html/i.test(h(pt, 'Content-Type'))) html = parte(pt, pc);
    }
  } else html = parte(testa, corpo);
  return { dest: h(testa, 'X-Destinatari'), oggetto: dec(h(testa, 'Subject')), html: html.replace(/&amp;/g, '&') };
}
const posta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => leggi(fs.readFileSync(path.join(POSTA, f), 'utf8')));
const aspetta = async (prima, chi, quanto = 10000) => { for (let i = 0; i < quanto / 250; i++) { const m = posta().slice(prima).find(x => x.dest.includes(chi)); if (m) return m; await dorme(250); } return null; };
const linkConferma = m => ((m && m.html || '').match(/https?:\/\/[^"<\s]+\/newsletter-conferma\/\?t=[a-f0-9]{64}/) || [])[0] || '';
const contatto = email => (q(`SELECT * FROM {p}newsletter_iscritti WHERE email = '${email}'`) || [])[0] || null;
const liste = id => (q(`SELECT l.lista_id FROM {p}newsletter_iscritti_liste l WHERE l.iscritto_id = ${+id} AND NOT EXISTS (SELECT 1 FROM {p}newsletter_liste_uscite u WHERE u.contatto_id = l.iscritto_id AND u.lista_id = l.lista_id) ORDER BY l.lista_id`) || []).map(r => +r.lista_id);
const valore = (id, attr) => uno(`SELECT CONCAT(v.v_testo, COALESCE(v.v_num, '')) FROM {p}newsletter_valori v JOIN {p}newsletter_attributi a ON a.id = v.attributo_id WHERE v.contatto_id = ${+id} AND a.nome = '${attr}'`);

(async () => {
  // Il secondo sito: una cartella con le pagine che incollano i codici del form.
  const altroDir = path.join(POSTA, '..', 'fb-altro-sito');
  fs.mkdirSync(altroDir, { recursive: true });
  const altro = spawn('php', ['-S', '127.0.0.1:' + PORTA2, '-t', altroDir], { detached: true, stdio: 'ignore' });
  altro.unref();
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  pnConferme.on(p, d => d.accept());
  const vai = u => p.goto(u.startsWith('http') ? u : B + u, { waitUntil: 'networkidle' });
  const flash = async () => (await p.locator('.flash').first().innerText().catch(() => '')).trim();
  const foto = async nome => { if (FOTO) await p.screenshot({ path: path.join(FOTO, nome + '.png'), fullPage: true }).catch(() => {}); };
  await vai('/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  cli('pulisci');
  cli('smtp', SMTP);
  const L = cli('prepara');
  ok('preparazione: liste e attributi della prova', L.principale > 0 && L.novita > 0 && L.offerte > 0, L);

  // Un visitatore: compila un form in una pagina con il captcha vero (moduli.js).
  const compila = async (pg, campi, { captcha = true, invia = true } = {}) => {
    for (const [sel, v] of Object.entries(campi)) {
      if (v === true) await pg.check(sel); else if (v === false) await pg.uncheck(sel); else await pg.fill(sel, v);
    }
    // Il limite di 5 invii all'ora per indirizzo IP (moduli_troppi, newsletter_troppe_richieste): qui sono tutti 127.0.0.1.
    q("UPDATE {p}moduli_invii SET aggiornato = aggiornato - INTERVAL 2 HOUR WHERE aggiornato > NOW() - INTERVAL 2 HOUR");
    q("UPDATE {p}newsletter_iscritti SET aggiornato = aggiornato - INTERVAL 2 HOUR WHERE email LIKE '%@fb-prova.it' AND aggiornato > NOW() - INTERVAL 2 HOUR");
    if (captcha) { await pg.check('.captcha input[type=checkbox]'); await pg.waitForSelector('form.verificato', { timeout: 30000 }); }
    if (invia) await Promise.all([pg.waitForNavigation({ timeout: 20000 }).catch(() => {}), pg.click('form button[type=submit]')]);
  };
  const visitatore = async (opz = {}) => { const c = await br.newContext({ viewport: { width: 1100, height: 900 }, ...opz }); const v = await c.newPage(); v.on('pageerror', e => err.push(e.message.slice(0, 200))); return [c, v]; };

  // ===== 1. Il costruttore a cinque passi.
  await vai('/admin/form.php?nuovo=1');
  const tipi = await p.locator('.fo-tipi a.fo-tipo').allInnerTexts();
  ok('1. nuovo form: cinque tipi (Iscrizione, Pop-up, Aggiornamento del profilo, Disiscrizione, Altri form)', tipi.length === 5 && /Iscrizione/.test(tipi[0]) && /Pop-up/.test(tipi[1]) && /Aggiornamento del profilo/.test(tipi[2]) && /Disiscrizione/.test(tipi[3]) && /Altri form/.test(tipi[4]), tipi);
  await vai('/admin/form.php?nuovo=1&tipo=iscrizione');
  ok('1. passo 1, Impostazione: nome, "Attiva i campi GDPR", Crea il form; i passi dopo non si aprono prima di crearlo', await p.locator('#fm-nome').count() === 1 && await p.locator('input[name=gdpr]').count() === 1 && await p.locator('.fo-passi a').count() === 0);
  await foto('form-1-impostazione');
  await p.fill('#fm-nome', 'FB Iscrizione');
  await p.check('input[name=gdpr]');
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[type=submit]')]);
  const F1 = +(new URL(p.url()).searchParams.get('id') || 0);
  const r1 = (q(`SELECT * FROM {p}moduli WHERE id = ${F1}`) || [])[0] || {};
  ok('1. Crea il form: si va al passo 2, tipo Iscrizione, GDPR acceso, lista principale e doppia conferma di partenza', /passo=2/.test(p.url()) && r1.tipo === 'iscrizione' && +r1.gdpr === 1 && +r1.lista_id > 0 && r1.conferma === 'doppia', [p.url(), r1.tipo, r1.gdpr, r1.lista_id]);
  ok('1. i passi in cima, con quello di adesso segnato', await p.locator('.fo-passi li').count() === 5 && /Aspetto e campi/.test(await p.locator('.fo-passi [aria-current=step]').innerText()));
  // Passo 2: i campi legati agli attributi.
  const aggiungi = async (etichetta, tipo) => {
    await p.click('button[data-al-clic="fmAggiungi()"]');
    const r = p.locator('.fm-campo').last();
    await r.locator('.fm-etichetta').fill(etichetta);
    await r.locator('.fm-tipo').selectOption(tipo);
    return p.locator('.fm-campo').last();
  };
  let r = await aggiungi('Città', 'testo');
  await r.locator('.fm-attributo').selectOption('FB_CITTA');
  r = await aggiungi('Sono già cliente', 'spunta');
  const attrSpunta = await r.locator('.fm-attributo option').evaluateAll(o => o.map(x => x.value).filter(Boolean));
  ok('2. la casella da spuntare offre solo gli attributi Sì/No', attrSpunta.length >= 1 && attrSpunta.includes('FB_CLIENTE') && !attrSpunta.includes('FB_CITTA') && !attrSpunta.includes('NOME'), attrSpunta);
  const attrTesto = await p.locator('.fm-campo').nth(2).locator('.fm-attributo option').evaluateAll(o => o.map(x => x.value).filter(Boolean));
  ok('2. un campo di testo offre gli attributi di testo, non quelli Sì/No', attrTesto.includes('FB_CITTA') && attrTesto.includes('NOME') && !attrTesto.includes('FB_CLIENTE'), attrTesto);
  await p.fill('#fm-gdpr-testo', 'Dichiarazione FB: usiamo i dati solo per la newsletter.');
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=avanti]')]);
  ok('2. una casella senza attributo Sì/No non si salva, e si dice perché', /va legata a un attributo Sì\/No/.test(await flash()) && /passo=2/.test(p.url()), await flash());
  r = p.locator('.fm-campo', { has: p.locator('.fm-etichetta[value="Sono già cliente"]') });
  await r.locator('.fm-attributo').selectOption('FB_CLIENTE');
  r = await aggiungi('Scegli cosa ricevere', 'liste');
  await r.locator(`.fm-scelta-liste input[value="${L.novita}"]`).check();
  await r.locator(`.fm-scelta-liste input[value="${L.offerte}"]`).check();
  await aggiungi('Acconsento a far sapere se apro le email', 'tracciamento');
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=avanti]')]);
  const campi1 = JSON.parse(uno(`SELECT campi FROM {p}moduli WHERE id = ${F1}`) || '[]');
  const perNome = Object.fromEntries(campi1.map(c => [c.etichetta, c]));
  ok('2. Avanti salva i campi e va al passo 3', /passo=3/.test(p.url()) && /salvato/.test(await flash()), await flash());
  ok('2. i campi sono legati agli attributi (EMAIL, NOME, FB_CITTA, FB_CLIENTE)', perNome.Email && perNome.Email.attributo === 'EMAIL' && perNome['Città'] && perNome['Città'].attributo === 'FB_CITTA' && perNome['Sono già cliente'].attributo === 'FB_CLIENTE', campi1.map(c => [c.etichetta, c.attributo]));
  ok('2. Iscrizione a più liste con le due liste scelte; Consenso al tracciamento mai obbligatorio', JSON.stringify(perNome['Scegli cosa ricevere'].liste) === JSON.stringify([L.novita, L.offerte]) && perNome['Acconsento a far sapere se apro le email'].obbligatorio === false, perNome['Scegli cosa ricevere']);
  ok('2. la dichiarazione GDPR salvata', uno(`SELECT gdpr_testo FROM {p}moduli WHERE id = ${F1}`) === 'Dichiarazione FB: usiamo i dati solo per la newsletter.');
  // Passo 3: messaggi.
  ok('3. passo 3, Messaggi: tre scelte dopo l\'invio, messaggi d\'errore, avviso', await p.locator('input[name=dopo_invio]').count() === 3 && await p.locator('#fm-m-obb').count() === 1 && await p.locator('#fm-avviso-a').count() === 1);
  await p.check('input[name=dopo_invio][value=messaggio]');
  await p.fill('#fm-riuscita', 'FB: grazie, controlla la posta.');
  await p.fill('#fm-m-obb', 'FB: manca {campo}.');
  await p.uncheck('input[name=avviso]');
  await foto('form-3-messaggi');
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=avanti]')]);
  ok('3. Avanti: messaggi salvati, passo 4', /passo=4/.test(p.url()) && /FB: grazie, controlla la posta\./.test(uno(`SELECT messaggi FROM {p}moduli WHERE id = ${F1}`) || '') && uno(`SELECT dopo_invio FROM {p}moduli WHERE id = ${F1}`) === 'messaggio');
  // Passo 4: conferma.
  ok('4. passo 4, Conferma: doppia, semplice, nessuna; lista principale; altre liste; aggiorna esistenti; modello di doppio opt-in', await p.locator('input[name=conferma]').count() === 3 && await p.locator('#fm-lista').count() === 1 && await p.locator('input[name="liste[]"]').count() >= 3 && await p.locator('input[name=aggiorna_esistenti]').count() === 1 && await p.locator('#fm-doi').count() === 1);
  await p.selectOption('#fm-lista', String(L.principale));
  await p.check('input[name=conferma][value=doppia]');
  await p.check('input[name=aggiorna_esistenti]');
  await foto('form-4-conferma');
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=avanti]')]);
  const r4 = (q(`SELECT * FROM {p}moduli WHERE id = ${F1}`) || [])[0] || {};
  ok('4. Avanti: lista principale, doppia conferma, aggiorna esistenti; passo 5', /passo=5/.test(p.url()) && +r4.lista_id === L.principale && r4.conferma === 'doppia' && +r4.lista_conferma === 1 && +r4.aggiorna_esistenti === 1, [r4.lista_id, r4.conferma, r4.aggiorna_esistenti]);
  // Passo 5: condividi.
  const url1 = await p.inputValue('#fo-link');
  const codIframe = await p.inputValue('#fo-iframe'); const codHtml = await p.inputValue('#fo-html'); const codSemplice = await p.inputValue('#fo-semplice');
  ok('5. Condividi: link della pagina ospitata', url1 === B + '/modulo/fb-iscrizione/', url1);
  ok('5. codice iframe, HTML (con lo script del captcha) e HTML semplice (senza script)', /<iframe src="[^"]+\/modulo\/fb-iscrizione\/"/.test(codIframe) && /data-captcha/.test(codHtml) && /moduli\.js/.test(codHtml) && /modulo-esterno\.css/.test(codHtml) && !/<script|data-captcha/.test(codSemplice) && /name="semplice" value="1"/.test(codSemplice), codSemplice.slice(0, 200));
  ok('5. il codice QR nella pagina, in SVG', await p.locator('.fo-qr svg path').count() === 1);
  const qrRisp = await p.request.get(B + '/admin/form.php?id=' + F1 + '&qr=1');
  ok('5. Scarica il codice QR: un file SVG', qrRisp.status() === 200 && /image\/svg\+xml/.test(qrRisp.headers()['content-type']) && /attachment/.test(qrRisp.headers()['content-disposition'] || '') && /<svg/.test(await qrRisp.text()));
  await foto('form-5-condividi');
  fs.writeFileSync(path.join(POSTA, '..', 'fb-qr.json'), JSON.stringify(cli('qr', url1)));
  let letto = '';
  try { letto = execFileSync('python3', ['-I', path.join(__dirname, 'qr-decodifica.py'), path.join(POSTA, '..', 'fb-qr.json')]).toString(); } catch (e) { letto = String(e.stdout || e.message); }
  ok('5. il QR si legge (lettore scritto a parte: formato, Reed-Solomon, dati) e porta alla pagina del form', letto.trim().split('\n').pop() === url1, letto);
  ok('5. un passo fatto si riapre dai passi in cima', await p.locator('.fo-passi a[href*="passo=2"]').count() === 1);

  // ===== 2. La pagina ospitata: l'unica che si può mettere in un iframe di un altro sito.
  const rOsp = await p.request.get(url1);
  const rCont = await p.request.get(B + '/contatti/');
  const cspO = rOsp.headers()['content-security-policy'] || ''; const cspC = rCont.headers()['content-security-policy'] || '';
  ok('pagina ospitata: 200, frame-ancestors *, niente X-Frame-Options', rOsp.status() === 200 && /frame-ancestors \*/.test(cspO) && !rOsp.headers()['x-frame-options'], [cspO, rOsp.headers()['x-frame-options']]);
  ok('il resto del sito resta protetto: frame-ancestors \'self\' e X-Frame-Options', /frame-ancestors 'self'/.test(cspC) && rCont.headers()['x-frame-options'] === 'SAMEORIGIN', [cspC, rCont.headers()['x-frame-options']]);
  let [vc, v] = await visitatore();
  await v.goto(url1, { waitUntil: 'networkidle' });
  ok('pagina ospitata: il form, la dichiarazione GDPR e la casella del consenso vuota', await v.locator('form[data-captcha]').count() === 1 && /Dichiarazione FB/.test(await v.locator('.form-gdpr').innerText()) && !(await v.isChecked('input[name=privacy]')));
  ok('pagina ospitata: niente testata né piede del sito', await v.locator('header, footer').count() === 0);
  // Una lista non del form, scritta a mano nella pagina: l'invio si ferma.
  await v.evaluate(id => { const f = document.querySelector('.form-liste'); const l = document.createElement('label'); l.innerHTML = '<input type="checkbox" name="' + f.querySelector('input').name + '" value="' + id + '" checked>'; f.appendChild(l); }, L.nascosta);
  const nomeListe = await v.locator('.form-liste input').first().getAttribute('name');
  await compila(v, { 'input[type=email]': 'uno@fb-prova.it', 'input[name=privacy]': true });
  ok('una lista che il form non offre: l\'invio si ferma con l\'errore del campo, nessun contatto', /modulo=campo/.test(v.url()) && /FB: manca Scegli cosa ricevere\./.test(await v.locator('.modulo-errore').innerText().catch(() => '')) && !contatto('uno@fb-prova.it'), [v.url(), await v.locator('.modulo-errore').innerText().catch(() => '')]);
  // Il visitatore vero.
  await v.goto(url1, { waitUntil: 'networkidle' });
  let quante = posta().length;
  await compila(v, { 'input[type=email]': 'uno@fb-prova.it', 'input[name="c_nome"]': 'Uno', 'input[name="c_citta"]': 'Roma \u{1F600}', 'input[name="c_sono_gia_cliente"]': true, [`input[name="${nomeListe}"][value="${L.novita}"]`]: true, 'input[name=privacy]': true });
  ok('doppia conferma: dopo l\'invio la pagina ospitata mostra il messaggio di riuscita', /fatto=1/.test(v.url()) && /FB: grazie, controlla la posta\./.test(await v.locator('.fo-riuscito').innerText().catch(() => '')), v.url());
  let c1 = contatto('uno@fb-prova.it') || {};
  ok('doppia conferma: il contatto aspetta la conferma, non è ancora in nessuna lista', c1.stato === 'in_attesa' && liste(c1.id).length === 0, [c1.stato, liste(c1.id)]);
  ok('attributi del form nella scheda: FB_CITTA con l\'emoji come entità, FB_CLIENTE sì', valore(c1.id, 'FB_CITTA') === 'Roma &#x1F600;' && /^1(\.0+)?$/.test(String(valore(c1.id, 'FB_CLIENTE'))), [valore(c1.id, 'FB_CITTA'), valore(c1.id, 'FB_CLIENTE')]);
  ok('consenso al tracciamento non spuntato: tracciamento 0', +c1.tracciamento === 0);
  const reg = (q(`SELECT * FROM {p}newsletter_consensi WHERE contatto_id = ${+c1.id} AND tipo = 'opt_in' AND form_id = ${F1}`) || [])[0] || {};
  ok('registro del consenso: data, IP, testo della dichiarazione e della casella', /Dichiarazione FB/.test(reg.testo || '') && /Casella spuntata/.test(reg.testo || '') && reg.ip !== '' && !!reg.creato && +c1.opt_in === 1, reg);
  const m1 = await aspetta(quante, 'uno@fb-prova.it');
  ok('doppia conferma: arriva l\'email con il link', !!linkConferma(m1), m1 && m1.oggetto);
  await v.goto(linkConferma(m1).replace(/^https?:\/\/[^/]+(\/test)?/, B), { waitUntil: 'networkidle' });
  c1 = contatto('uno@fb-prova.it') || {};
  ok('dopo il clic: attivo, nella lista principale e in quella scelta (non nell\'altra offerta)', c1.stato === 'attivo' && JSON.stringify(liste(c1.id)) === JSON.stringify([L.principale, L.novita].sort((a, b) => a - b)), [c1.stato, liste(c1.id)]);
  await vc.close();

  // Conferma semplice e nessuna.
  const passo4 = async (conferma, extra = async () => {}) => {
    await vai('/admin/form.php?id=' + F1 + '&passo=4');
    await p.check(`input[name=conferma][value=${conferma}]`);
    await extra();
    await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=resta]')]);
    return flash();
  };
  ok('passo 4: conferma semplice salvata', /salvato/.test(await passo4('semplice')) && uno(`SELECT conferma FROM {p}moduli WHERE id = ${F1}`) === 'semplice' && +uno(`SELECT lista_conferma FROM {p}moduli WHERE id = ${F1}`) === 0);
  [vc, v] = await visitatore();
  await v.goto(url1, { waitUntil: 'networkidle' });
  quante = posta().length;
  await compila(v, { 'input[type=email]': 'due@fb-prova.it', 'input[name=privacy]': true });
  const c2 = contatto('due@fb-prova.it') || {};
  const m2 = await aspetta(quante, 'due@fb-prova.it');
  ok('conferma semplice: entra subito nella lista e riceve l\'email che è iscritto (senza link di conferma)', c2.stato === 'attivo' && liste(c2.id).includes(L.principale) && !!m2 && /Sei iscritto/.test(m2.oggetto) && !linkConferma(m2), [c2.stato, m2 && m2.oggetto]);
  ok('passo 4: nessuna conferma salvata', /salvato/.test(await passo4('nessuna')));
  await v.goto(url1, { waitUntil: 'networkidle' });
  quante = posta().length;
  await compila(v, { 'input[type=email]': 'tre@fb-prova.it', 'input[name=privacy]': true });
  await dorme(1500);
  ok('nessuna conferma: entra subito, nessuna email', (contatto('tre@fb-prova.it') || {}).stato === 'attivo' && !posta().slice(quante).some(x => x.dest.includes('tre@fb-prova.it')));
  // Doppia con il modello di doppio opt-in e la pagina dopo il clic.
  const mod = cli('modello_optin');
  ok('passo 4: doppia conferma con il modello e la pagina dopo il clic', /salvato/.test(await passo4('doppia', async () => { await p.selectOption('#fm-doi', String(mod)); await p.fill('#fm-doi-url', '/grazie-fb/'); })) && +uno(`SELECT doi_modello_id FROM {p}moduli WHERE id = ${F1}`) === +mod && uno(`SELECT doi_url FROM {p}moduli WHERE id = ${F1}`) === '/grazie-fb/', await flash());
  await v.goto(url1, { waitUntil: 'networkidle' });
  quante = posta().length;
  await compila(v, { 'input[type=email]': 'quattro@fb-prova.it', 'input[name=privacy]': true });
  const m4 = await aspetta(quante, 'quattro@fb-prova.it');
  ok('modello di doppio opt-in: l\'email è quella del modello, con il link di conferma', !!m4 && m4.oggetto === 'FB conferma con il modello' && !!linkConferma(m4), m4 && m4.oggetto);
  const dopoClic = await v.goto(linkConferma(m4).replace(/^https?:\/\/[^/]+(\/test)?/, B), { waitUntil: 'networkidle' });
  ok('dopo il clic: la pagina scelta nel form', /\/grazie-fb\/$/.test(v.url()) && (contatto('quattro@fb-prova.it') || {}).stato === 'attivo', v.url());
  await passo4('doppia', async () => { await p.selectOption('#fm-doi', '0'); await p.fill('#fm-doi-url', ''); });
  // Aggiorna i contatti esistenti.
  ok('passo 4: nessuna conferma per le prove dopo', /salvato/.test(await passo4('nessuna')));
  await v.goto(url1, { waitUntil: 'networkidle' });
  await compila(v, { 'input[type=email]': 'uno@fb-prova.it', 'input[name="c_citta"]': 'Milano', 'input[name=privacy]': true });
  ok('aggiorna i contatti esistenti, acceso: la città scritta prende il posto di quella di prima', valore(c1.id, 'FB_CITTA') === 'Milano', valore(c1.id, 'FB_CITTA'));
  await passo4('nessuna', async () => p.uncheck('input[name=aggiorna_esistenti]'));
  await v.goto(url1, { waitUntil: 'networkidle' });
  await compila(v, { 'input[type=email]': 'uno@fb-prova.it', 'input[name="c_citta"]': 'Torino', 'input[name=privacy]': true });
  ok('aggiorna i contatti esistenti, spento: la città di prima resta', valore(c1.id, 'FB_CITTA') === 'Milano', valore(c1.id, 'FB_CITTA'));
  await vc.close();

  // ===== 3. Su un altro sito: iframe, HTML, HTML semplice.
  fs.writeFileSync(path.join(altroDir, 'iframe.html'), '<!DOCTYPE html><html lang="it"><head><meta charset="utf-8"><title>Altro sito</title></head><body><h1>Un altro sito</h1>' + codIframe + '</body></html>');
  fs.writeFileSync(path.join(altroDir, 'iframe-contatti.html'), '<!DOCTYPE html><html lang="it"><head><meta charset="utf-8"><title>Altro sito</title></head><body><iframe src="' + B + '/contatti/" width="600" height="600"></iframe></body></html>');
  fs.writeFileSync(path.join(altroDir, 'html.html'), '<!DOCTYPE html><html lang="it"><head><meta charset="utf-8"><title>Altro sito</title></head><body>' + codHtml + '</body></html>');
  fs.writeFileSync(path.join(altroDir, 'semplice.html'), '<!DOCTYPE html><html lang="it"><head><meta charset="utf-8"><title>Altro sito</title></head><body>' + codSemplice + '</body></html>');
  [vc, v] = await visitatore();
  await v.goto(ALTRO + '/iframe.html', { waitUntil: 'networkidle' });
  const fr = v.frameLocator('iframe');
  ok('iframe su un\'altra origine: il form si vede dentro la cornice', await fr.locator('form[data-captcha]').count() === 1);
  await fr.locator('input[type=email]').fill('cinque@fb-prova.it');
  await fr.locator('input[name=privacy]').check();
  await fr.locator('.captcha input[type=checkbox]').check();
  await fr.locator('form.verificato').waitFor({ timeout: 30000 });
  await fr.locator('button[type=submit]').click();
  await fr.locator('.fo-riuscito').waitFor({ timeout: 15000 }).catch(() => {});
  ok('iframe: dopo l\'invio il messaggio di riuscita resta dentro la cornice, e il contatto c\'è', /FB: grazie/.test(await fr.locator('.fo-riuscito').innerText().catch(() => '')) && !!contatto('cinque@fb-prova.it'), v.url());
  await v.goto(ALTRO + '/iframe-contatti.html', { waitUntil: 'networkidle' });
  const frC = v.frames().find(f => f !== v.mainFrame());
  const dentro = frC ? await frC.evaluate(() => document.querySelectorAll('form').length).catch(() => 0) : 0;
  ok('clickjacking: un\'altra pagina del sito in un iframe di un altro sito non si apre', dentro === 0, dentro);
  await v.goto(ALTRO + '/html.html', { waitUntil: 'networkidle' });
  await compila(v, { 'input[type=email]': 'sei@fb-prova.it', 'input[name=privacy]': true });
  ok('codice HTML su un\'altra origine: il captcha si risolve da lì, l\'invio porta alla pagina ospitata con il messaggio', v.url().startsWith(B + '/modulo/fb-iscrizione/') && /fatto=1/.test(v.url()) && !!contatto('sei@fb-prova.it'), v.url());
  await vc.close();
  [vc, v] = await visitatore({ javaScriptEnabled: false });
  await v.goto(ALTRO + '/semplice.html', { waitUntil: 'load' });
  await compila(v, { 'input[type=email]': 'sette@fb-prova.it', 'input[name=privacy]': true }, { captcha: false });
  ok('HTML semplice senza JavaScript: dopo l\'invio la pagina "ultimo passo" con la domanda di controllo, i campi già scritti', /api\/modulo\.php/.test(v.url()) && await v.locator('input[name=domanda_risposta]').count() === 1 && await v.inputValue('input[type=email]') === 'sette@fb-prova.it' && !contatto('sette@fb-prova.it'), v.url());
  const domanda = await v.locator('.form-domanda label span').innerText();
  const num = ['zero', 'uno', 'due', 'tre', 'quattro', 'cinque', 'sei', 'sette', 'otto', 'nove', 'dieci'];
  const [, a1, a2] = domanda.match(/fa (\w+) più (\w+)/) || [];
  await compila(v, { 'input[name=domanda_risposta]': String(num.indexOf(a1) + num.indexOf(a2) + 1) }, { captcha: false });
  ok('risposta sbagliata: la domanda torna, con l\'errore', /non è giusta/.test(await v.locator('.modulo-errore').innerText().catch(() => '')) && !contatto('sette@fb-prova.it'));
  const d2 = await v.locator('.form-domanda label span').innerText();
  const [, b1, b2] = d2.match(/fa (\w+) più (\w+)/) || [];
  await compila(v, { 'input[name=domanda_risposta]': String(num.indexOf(b1) + num.indexOf(b2)) }, { captcha: false });
  ok('risposta giusta: il form arriva e si va alla pagina ospitata con il messaggio', /fatto=1/.test(v.url()) && !!contatto('sette@fb-prova.it'), v.url());
  // La pagina ospitata stessa, senza JavaScript: la domanda dentro <noscript>.
  await v.goto(url1, { waitUntil: 'load' });
  const d3 = await v.locator('.form-domanda label span').innerText().catch(() => '');
  const [, c1a, c1b] = d3.match(/fa (\w+) più (\w+)/) || [];
  await compila(v, { 'input[type=email]': 'otto@fb-prova.it', 'input[name=privacy]': true, 'input[name=domanda_risposta]': String(num.indexOf(c1a) + num.indexOf(c1b)) }, { captcha: false });
  ok('pagina ospitata senza JavaScript: la domanda di controllo al posto della casella, e l\'invio arriva', /fatto=1/.test(v.url()) && !!contatto('otto@fb-prova.it'), [d3, v.url()]);
  await vc.close();

  // ===== 4. Disiscrizione da una sola lista e pagina di disiscrizione della campagna.
  c1 = contatto('uno@fb-prova.it');
  const camp = cli('campagna', 'FB Campagna', `${L.principale},${L.novita}`);
  [vc, v] = await visitatore();
  await v.goto(cli('link_uscita', c1.id, camp).replace(/^https?:\/\/[^/]+(\/test)?/, B), { waitUntil: 'networkidle' });
  const bottoni = await v.locator('.nl-solo-lista button').allInnerTexts();
  ok('pagina di disiscrizione: «Esci solo da» per ogni lista della campagna in cui è il contatto', bottoni.length === 2 && bottoni.some(t => /FB Novità/i.test(t)) && bottoni.some(t => /FB Principale/i.test(t)), bottoni);
  // Una lista non della campagna, scritta a mano.
  await v.evaluate(id => { const f = document.querySelector('.nl-solo-lista'); f.querySelector('input[name=lista]').value = id; }, String(L.offerte));
  await Promise.all([v.waitForNavigation(), v.locator('.nl-solo-lista button').first().click()]);
  ok('una lista che non è della campagna non si tocca', /non è fra quelle dell/.test(await v.locator('main').innerText()) && !uno(`SELECT COUNT(*) FROM {p}newsletter_liste_uscite WHERE contatto_id = ${c1.id} AND lista_id = ${L.offerte}`), '');
  await v.goto(cli('link_uscita', c1.id, camp).replace(/^https?:\/\/[^/]+(\/test)?/, B), { waitUntil: 'networkidle' });
  await Promise.all([v.waitForNavigation(), v.locator('.nl-solo-lista', { hasText: 'FB Novità' }).locator('button').click()]);
  c1 = contatto('uno@fb-prova.it');
  ok('esci solo da una lista: resta iscritto, non riceve più quella lista', c1.stato === 'attivo' && !liste(c1.id).includes(L.novita) && liste(c1.id).includes(L.principale) && /niente più «FB Novità»/.test(await v.locator('h1').innerText()), [c1.stato, liste(c1.id)]);
  // Una pagina di disiscrizione in più, scelta nella campagna.
  await vai('/admin/newsletter-consenso.php?scheda=pagine&nuova=1');
  await p.fill('#nc-p-nome', 'FB Pagina promo');
  await p.fill('#nc-p-ut', 'FB: proprio vuoi andare?');
  await Promise.all([p.waitForNavigation(), p.click('#nc-pagina button[type=submit]')]);
  const pagId = +uno("SELECT id FROM {p}newsletter_pagine_uscita WHERE nome = 'FB Pagina promo'");
  ok('Pagine e consenso: pagina di disiscrizione creata', pagId > 0 && /salvata/.test(await flash()), await flash());
  await vai('/admin/newsletter-campagna.php?id=' + camp + '&apri=impostazioni#sez-impostazioni');
  await p.evaluate(() => { const d = document.querySelector('#sez-impostazioni'); if (d) d.open = true; });
  await p.selectOption('#nlr-pagina-uscita', String(pagId));
  await Promise.all([p.waitForNavigation(), p.click('#sez-impostazioni button[type=submit]')]);
  ok('campagna: la pagina di disiscrizione scelta in Impostazioni aggiuntive', +uno(`SELECT pagina_uscita_id FROM {p}newsletter_campagne WHERE id = ${camp}`) === pagId);
  const c4 = contatto('quattro@fb-prova.it');
  cli('contatto', 'quattro@fb-prova.it', `${L.principale}`);
  await v.goto(cli('link_uscita', c4.id, camp).replace(/^https?:\/\/[^/]+(\/test)?/, B), { waitUntil: 'networkidle' });
  ok('il link della campagna apre la pagina scelta', /FB: proprio vuoi andare\?/.test(await v.locator('h1').innerText()));
  await v.goto(cli('link_uscita', c4.id, 0).replace(/^https?:\/\/[^/]+(\/test)?/, B), { waitUntil: 'networkidle' });
  ok('senza la campagna nel link: la pagina predefinita', !/FB: proprio/.test(await v.locator('h1').innerText()));
  await vc.close();

  // ===== 5. Gruppi di consenso, centro preferenze, tracciamento.
  await vai('/admin/newsletter-consenso.php');
  ok('gruppi di consenso spenti di partenza', !(await p.isChecked('#nc-attivi input[name=attivi]')));
  await p.check('#nc-attivi input[name=attivi]');
  await Promise.all([p.waitForNavigation(), p.click('#nc-attivi button[type=submit]')]);
  const creaGruppo = async (nome, ingresso) => { await p.fill('#nc-g-nome', nome); await p.check(`#nc-gruppo input[name=ingresso][value=${ingresso}]`); await Promise.all([p.waitForNavigation(), p.click('#nc-gruppo button[type=submit]')]); };
  await creaGruppo('FB Offerte speciali', 'automatic');
  await creaGruppo('FB Eventi', 'manual');
  const gA = +uno("SELECT id FROM {p}newsletter_gruppi_consenso WHERE nome = 'FB Offerte speciali'"); const gM = +uno("SELECT id FROM {p}newsletter_gruppi_consenso WHERE nome = 'FB Eventi'");
  ok('gruppi accesi e creati (automatico e manuale)', uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_gruppi_attivi'") === '1' && gA > 0 && gM > 0);
  const c3 = contatto('tre@fb-prova.it');
  [vc, v] = await visitatore();
  const pref3 = cli('link_pref', c3.id).replace(/^https?:\/\/[^/]+(\/test)?/, B);
  await v.goto(pref3, { waitUntil: 'networkidle' });
  ok('centro preferenze: il gruppo automatico spuntato, quello manuale no; il tracciamento come detto nel form (no)', await v.isChecked(`input[name="gruppi[]"][value="${gA}"]`) && !(await v.isChecked(`input[name="gruppi[]"][value="${gM}"]`)) && !(await v.isChecked('input[name=tracciamento]')));
  await v.uncheck(`input[name="gruppi[]"][value="${gA}"]`); await v.check(`input[name="gruppi[]"][value="${gM}"]`); await v.check('input[name=tracciamento]');
  await Promise.all([v.waitForNavigation(), v.click('.nl-preferenze button[type=submit]')]);
  ok('centro preferenze: il tracciamento si riaccende', +contatto('tre@fb-prova.it').tracciamento === 1);
  await v.uncheck('input[name=tracciamento]');
  await Promise.all([v.waitForNavigation(), v.click('.nl-preferenze button[type=submit]')]);
  const g3 = Object.fromEntries((q(`SELECT gruppo_id, iscritto FROM {p}newsletter_contatti_gruppi WHERE contatto_id = ${c3.id}`) || []).map(x => [+x.gruppo_id, +x.iscritto]));
  ok('centro preferenze: gruppi e tracciamento salvati', g3[gA] === 0 && g3[gM] === 1 && +contatto('tre@fb-prova.it').tracciamento === 0, g3);
  ok('registro del consenso: le scelte dei gruppi e del tracciamento', +uno(`SELECT COUNT(*) FROM {p}newsletter_consensi WHERE contatto_id = ${c3.id} AND tipo IN ('gruppo', 'tracciamento')`) >= 3);
  await Promise.all([v.waitForNavigation(), v.click('form:has(input[value=esci_gruppi]) button')]);
  ok('esci da tutti i gruppi: fuori dai gruppi, ma ancora iscritto', contatto('tre@fb-prova.it').stato === 'attivo' && +uno(`SELECT COUNT(*) FROM {p}newsletter_contatti_gruppi WHERE contatto_id = ${c3.id} AND iscritto = 1`) === 0);
  // Il link di un altro contatto: il token non va con l'id.
  const pref2 = cli('link_pref', c2.id);
  const falso = pref3.replace(/i=\d+/, 'i=' + c2.id);
  await v.goto(falso, { waitUntil: 'networkidle' });
  ok('il link delle preferenze con l\'id di un altro contatto: non valido, niente dati', /non è valido/.test(await v.locator('main').innerText()) && !(await v.content()).includes('due@fb-prova.it'));
  await vc.close();
  // La campagna con i gruppi accesi: il gruppo è obbligatorio, e decide chi la riceve.
  await vai('/admin/newsletter-campagna.php?id=' + camp);
  ok('campagna con i gruppi accesi: senza gruppo non parte, e si dice dove sceglierlo', /Scegli il gruppo di consenso/.test(await p.locator('body').innerText()));
  const destinatari = async gid => {
    await vai('/admin/newsletter-campagna.php?id=' + camp + '#sez-destinatari');
    await p.evaluate(() => { const d = document.querySelector('#sez-destinatari'); if (d) d.open = true; });
    await p.selectOption('#nlr-gruppo', String(gid));
    await Promise.all([p.waitForNavigation(), p.click('#sez-destinatari button[type=submit]')]);
    return +(await p.locator('[data-nlr-destinatari]').innerText());
  };
  cli('contatto', 'tre@fb-prova.it', `${L.principale}`);
  const conAuto = await destinatari(gA); const conMan = await destinatari(gM);
  const attesoAuto = +uno(`SELECT COUNT(DISTINCT i.id) FROM {p}newsletter_iscritti i JOIN {p}newsletter_iscritti_liste l ON l.iscritto_id = i.id WHERE i.stato = 'attivo' AND l.lista_id IN (${L.principale},${L.novita}) AND NOT EXISTS (SELECT 1 FROM {p}newsletter_liste_uscite u WHERE u.contatto_id = l.iscritto_id AND u.lista_id = l.lista_id) AND NOT EXISTS (SELECT 1 FROM {p}newsletter_contatti_gruppi g WHERE g.contatto_id = i.id AND g.gruppo_id = ${gA} AND g.iscritto = 0)`);
  ok('gruppo automatico: tutti tranne chi è uscito; gruppo manuale: solo chi è entrato (nessuno adesso)', conAuto === attesoAuto && conAuto > 0 && conMan === 0, [conAuto, attesoAuto, conMan]);
  // Tracciamento: niente pixel, niente link tracciati, niente eventi, i segmenti non lo contano.
  const c10 = { id: cli('contatto', 'dieci@fb-prova.it', `${L.principale}`) };
  const e3 = cli('email', camp, c3.id); const e2 = cli('email', camp, c10.id);
  ok('senza consenso al tracciamento: l\'email senza pixel e con i link diretti', typeof e3 === 'string' && !/api\/nl\.php/.test(e3) && /href="https:\/\/esempio\.it\/pagina"/.test(e3), String(e3).slice(-300));
  ok('con il consenso: pixel e link tracciati come prima', typeof e2 === 'string' && /api\/nl\.php\?a=a/.test(e2) && /api\/nl\.php\?a=c/.test(e2));
  const ev3 = cli('evento', camp, c3.id); const ev2 = cli('evento', camp, c10.id);
  ok('un\'apertura firmata di chi non acconsente non si segna; di chi acconsente sì', ev3.righe === 0 && ev2.righe === 1, [ev3, ev2]);
  const regole = JSON.stringify({ op: 'e', gruppi: [{ op: 'e', condizioni: [{ campo: 'email', op: 'non_aperta', campagna: 0 }] }] });
  const prima = cli('conta', regole);
  cli('sql', `UPDATE {p}newsletter_iscritti SET tracciamento = 1 WHERE id = ${c3.id}`);
  const dopo = cli('conta', regole);
  cli('sql', `UPDATE {p}newsletter_iscritti SET tracciamento = 0 WHERE id = ${c3.id}`);
  ok('segmento "non ha aperto": chi non acconsente al tracciamento non si conta', typeof prima === 'number' && dopo === prima + 1, [prima, dopo]);

  // ===== 6. Form di profilo come centro preferenze.
  await vai('/admin/form.php?nuovo=1&tipo=profilo');
  await p.fill('#fm-nome', 'FB Profilo');
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[type=submit]')]);
  const F2 = +(new URL(p.url()).searchParams.get('id') || 0);
  r = await aggiungi('I tuoi consensi', 'gruppi');
  r = await aggiungi('Le tue liste', 'liste');
  await r.locator(`.fm-scelta-liste input[value="${L.principale}"]`).check();
  await r.locator(`.fm-scelta-liste input[value="${L.novita}"]`).check();
  await aggiungi('Statistiche delle email', 'tracciamento');
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=avanti]')]);
  ok('form di profilo: campi salvati (email, nome, cognome, gruppi, liste, tracciamento)', JSON.parse(uno(`SELECT campi FROM {p}moduli WHERE id = ${F2}`) || '[]').length === 6, await flash());
  await vai('/admin/form.php?id=' + F2 + '&passo=5');
  await p.check('#fo-centro input[name=centro]');
  await Promise.all([p.waitForNavigation(), p.click('#fo-centro button[type=submit]')]);
  ok('passo 5: il form di profilo diventa il centro preferenze', +uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_preferenze_form'") === F2);
  [vc, v] = await visitatore();
  await v.goto(cli('link_pref', c1.id).replace(/^https?:\/\/[^/]+(\/test)?/, B), { waitUntil: 'networkidle' });
  ok('il link delle preferenze porta al form di profilo, già compilato, con l\'email che non si cambia', /\/modulo\/fb-profilo\/\?i=/.test(v.url()) && await v.inputValue('input[type=email]') === 'uno@fb-prova.it' && await v.getAttribute('input[type=email]', 'readonly') !== null && await v.inputValue('input[name=c_nome]') === 'Uno', v.url());
  ok('form di profilo: le liste come sono (FB Novità tolta prima), i gruppi, il tracciamento', await v.isChecked(`.form-liste input[value="${L.principale}"]`) && !(await v.isChecked(`.form-liste input[value="${L.novita}"]`)) && await v.isChecked(`.form-gruppi input[value="${gA}"]`) && !(await v.isChecked('.form-tracciamento input')),
    [await v.isChecked(`.form-liste input[value="${L.principale}"]`), await v.isChecked(`.form-liste input[value="${L.novita}"]`), await v.locator('.form-gruppi').count(), await v.isChecked('.form-tracciamento input')]);
  await v.fill('input[name=c_nome]', 'Unoplus');
  await v.check(`.form-liste input[value="${L.novita}"]`);
  await v.evaluate(() => { document.querySelector('input[type=email]').removeAttribute('readonly'); document.querySelector('input[type=email]').value = 'ruba@fb-prova.it'; });
  await compila(v, {});
  c1 = contatto('uno@fb-prova.it');
  ok('form di profilo: nome e liste aggiornati, l\'email no (anche cambiata a mano)', c1 && c1.nome === 'Unoplus' && liste(c1.id).includes(L.novita) && !contatto('ruba@fb-prova.it') && /fatto=1/.test(v.url()), [c1 && c1.nome, v.url()]);
  await v.goto(B + '/modulo/fb-profilo/', { waitUntil: 'networkidle' });
  ok('form di profilo senza il link: niente form, si dice di usare il link delle email', await v.locator('form').count() === 0 && /link «Aggiorna le tue preferenze»/.test(await v.locator('main').innerText()));
  await v.goto(B + '/modulo/fb-profilo/?i=' + c2.id + '&t=' + new URL(cli('link_pref', c1.id)).searchParams.get('t'), { waitUntil: 'networkidle' });
  ok('form di profilo con l\'id di un altro contatto: niente dati', await v.locator('form').count() === 0 && !(await v.content()).includes('due@fb-prova.it'));
  await vc.close();
  await vai('/admin/form.php?id=' + F2 + '&passo=5');
  await p.uncheck('#fo-centro input[name=centro]');
  await Promise.all([p.waitForNavigation(), p.click('#fo-centro button[type=submit]')]);

  // ===== 7. Form di disiscrizione.
  await vai('/admin/form.php?nuovo=1&tipo=disiscrizione');
  await p.fill('#fm-nome', 'FB Esci');
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[type=submit]')]);
  [vc, v] = await visitatore();
  await v.goto(B + '/modulo/fb-esci/', { waitUntil: 'networkidle' });
  ok('form di disiscrizione: solo l\'email, senza la casella del consenso', await v.locator('input[type=email]').count() === 1 && await v.locator('input[name=privacy]').count() === 0);
  quante = posta().length;
  await compila(v, { 'input[type=email]': 'due@fb-prova.it' });
  const mE = await aspetta(quante, 'due@fb-prova.it');
  ok('disiscrizione: arriva l\'email con il link firmato per uscire, e il contatto è ancora iscritto', !!mE && /newsletter-esci\/\?i=\d+&t=[a-f0-9]{32}/.test(mE.html) && contatto('due@fb-prova.it').stato === 'attivo' && /fatto=1/.test(v.url()), mE && mE.oggetto);
  const msgSi = await v.locator('.fo-riuscito').innerText().catch(() => '');
  await v.goto(B + '/modulo/fb-esci/', { waitUntil: 'networkidle' });
  quante = posta().length;
  await compila(v, { 'input[type=email]': 'nessuno@fb-prova.it' });
  await dorme(1500);
  ok('un indirizzo che non c\'è: lo stesso messaggio, nessuna email', (await v.locator('.fo-riuscito').innerText().catch(() => '')) === msgSi && !posta().slice(quante).some(x => x.dest.includes('nessuno@fb-prova.it')));
  await vc.close();

  // ===== 8. Form pop-up.
  await vai('/admin/form.php?nuovo=1&tipo=popup');
  await p.fill('#fm-nome', 'FB Popup');
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[type=submit]')]);
  const F4 = +(new URL(p.url()).searchParams.get('id') || 0);
  await vai('/admin/form.php?id=' + F4 + '&passo=5');
  await p.check('#fo-popup input[name="pp[attivo]"]');
  await p.selectOption('#pp-dove', 'home'); await p.selectOption('#pp-innesco', 'secondi'); await p.fill('#pp-valore', '0');
  await Promise.all([p.waitForNavigation(), p.click('#fo-popup button[type=submit]')]);
  ok('pop-up: salvato e collegato al form', +uno(`SELECT popup_id FROM {p}moduli WHERE id = ${F4}`) > 0 && uno(`SELECT modulo FROM {p}popup WHERE id = (SELECT popup_id FROM {p}moduli WHERE id = ${F4})`) === 'form:' + F4);
  [vc, v] = await visitatore();
  await v.goto(B + '/', { waitUntil: 'networkidle' });
  await v.waitForSelector(`.popup:not([hidden]) form input[value="form:${F4}"]`, { state: 'attached', timeout: 8000 }).catch(() => {});
  ok('pop-up: compare sul sito, con il form dentro', await v.locator(`.popup:not([hidden]) input[name=modulo][value="form:${F4}"]`).count() === 1);
  await vc.close();
  await vai('/admin/form.php?id=' + F4 + '&passo=5');
  await p.uncheck('#fo-popup input[name="pp[attivo]"]');
  await Promise.all([p.waitForNavigation(), p.click('#fo-popup button[type=submit]')]);

  // ===== 9. Dopo l'invio: un altro indirizzo; elenco con le viste per tipo.
  await vai('/admin/form.php?id=' + F1 + '&passo=3');
  await p.check('input[name=dopo_invio][value=url]');
  await p.fill('#fm-dopo-url', '');
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=resta]')]);
  ok('«Un altro indirizzo» senza indirizzo: non si salva, e si dice cosa scrivere', /scrivi quale/.test(await flash()), await flash());
  await p.check('input[name=dopo_invio][value=url]');
  await p.fill('#fm-dopo-url', '/fb-dopo/');
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=resta]')]);
  [vc, v] = await visitatore();
  await v.goto(url1, { waitUntil: 'networkidle' });
  await compila(v, { 'input[type=email]': 'nove@fb-prova.it', 'input[name=privacy]': true });
  ok('dopo l\'invio, un altro indirizzo: si va lì', /\/fb-dopo\/$/.test(v.url()), v.url());
  await vc.close();
  await vai('/admin/form.php?vedi=iscrizione');
  const righe = await p.locator('.ct-tabella tbody tr').allInnerTexts();
  ok('elenco: la vista Iscrizione mostra solo i form di iscrizione', righe.some(t => /FB Iscrizione/.test(t)) && !righe.some(t => /FB Profilo|FB Esci/.test(t)), righe.length);
  await foto('form-elenco');

  // ===== Pulizia.
  cli('pulisci');
  ok('pulizia: nessun form, contatto, gruppo o pagina della prova', !+uno("SELECT COUNT(*) FROM {p}moduli WHERE nome LIKE 'FB %'") && !+uno("SELECT COUNT(*) FROM {p}newsletter_iscritti WHERE email LIKE '%@fb-prova.it'") && !+uno("SELECT COUNT(*) FROM {p}newsletter_gruppi_consenso WHERE nome LIKE 'FB %'"));
  ok('nessun errore JavaScript nelle pagine', err.length === 0, err);
  await br.close();
  try { process.kill(-altro.pid); } catch (e) { try { altro.kill(); } catch (e2) {} }
  console.log(`${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); console.log(`${n - ko} su ${n} OK (interrotta)`); process.exit(1); });
