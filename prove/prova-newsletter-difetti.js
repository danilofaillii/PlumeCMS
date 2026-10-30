// I difetti della newsletter corretti nella 1.11.0, un controllo (o più) per punto. Su un CMS di prima della 1.11.0 i
// controlli dei punti danno KO (il difetto si vede); sulla 1.11.0 tutti OK.
//   1. la doppia conferma di un form iscriveva anche alla newsletter principale (e l'email parlava di newsletter);
//      aggancio newsletter_conferma_esito(): chi ha confermato e da quale form;
//   2. un flusso "Compila un form" su un form con doppia conferma si perdeva (il giro lo fermava prima della conferma);
//   3. il limite orario contava solo le campagne: ora il registro di tutte le email, e il limite al giorno;
//   4. con mail() le email partivano senza From; ora From, Return-Path (-f) e la parte di testo semplice;
//   5. "Cancella dati" lasciava aperture, clic, flussi e invii dei form; ora tutto, più la lista di soppressione;
//   6. l'importazione segnava il consenso di adesso, rimetteva dentro chi era uscito e faceva partire i flussi;
//   7. nessuna gestione dei rimbalzi: un 5xx al RCPT TO segna "rimbalzato", tre 4xx di fila si segnalano;
//   minori: l'errore "troppe richieste" del form, la pulizia degli iscritti mai confermati e del registro.
// Su un CMS installato con il kit Attività (la pagina Contatti ha il form), con il server di posta finto acceso con i rimbalzi:
//   python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> rimbalzi &
// uso: node prove/prova-newsletter-difetti.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella del CMS installato>
// Lascia dati di prova nel sito (iscritti, liste, flussi, campagne): va lanciata su un sito di prova da buttare.
const { chromium } = require('playwright');
const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');
const { execFileSync } = require('child_process');
const [B, UTENTE, PASSWORD, SMTP, POSTA, DIR] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); };
const dorme = ms => new Promise(r => setTimeout(r, ms));
const CLI = path.join(__dirname, 'newsletter-cli.php');
const cli = (...a) => { try { return JSON.parse(execFileSync('php', [CLI, DIR, ...a.map(String)], { stdio: ['ignore', 'pipe', 'pipe'] }).toString().trim().split('\n').pop()); } catch (e) { return { errore: String(e.stderr || e.message).slice(0, 300) }; } };
const q = s => cli('sql', s);
const uno = s => { const r = q(s); return Array.isArray(r) && r[0] ? Object.values(r[0])[0] : null; };
// Un numero dal database; NaN se la query non riesce (una tabella che su un CMS vecchio non c'è): mai un falso OK.
const num = s => { const v = uno(s); return v === null ? NaN : +v; };

// La posta: ogni .eml del server finto, letta (intestazioni, oggetto decodificato, parti in base64 decodificate).
function leggi(eml) {
  const i = eml.search(/\r?\n\r?\n/);
  const testa = eml.slice(0, i).replace(/\r?\n[ \t]+/g, ' '); const corpo = eml.slice(i).replace(/^\r?\n\r?\n/, '');
  const h = (t, nome) => ((t.match(new RegExp('^' + nome + ':[ \\t]*(.*)$', 'mi')) || [])[1] || '').trim();
  const dec = s => s.replace(/=\?UTF-8\?B\?([^?]+)\?=/gi, (_, b) => Buffer.from(b, 'base64').toString('utf8'));
  const parte = (t, c) => /base64/i.test(h(t, 'Content-Transfer-Encoding')) ? Buffer.from(c.replace(/\s+/g, ''), 'base64').toString('utf8') : c;
  const ct = h(testa, 'Content-Type');
  let html = '', testo = '';
  const b = (ct.match(/boundary="([^"]+)"/) || [])[1];
  if (b) {
    for (const p of corpo.split('--' + b).slice(1)) {
      const j = p.search(/\r?\n\r?\n/); if (j < 0) continue;
      const pt = p.slice(0, j).trim(); const pc = p.slice(j).trim();
      if (/text\/plain/i.test(h(pt, 'Content-Type'))) testo = parte(pt, pc); else if (/text\/html/i.test(h(pt, 'Content-Type'))) html = parte(pt, pc);
    }
  } else if (/text\/plain/i.test(ct)) testo = parte(testa, corpo); else html = parte(testa, corpo);
  return { dest: h(testa, 'X-Destinatari'), args: h(testa, 'X-Argomenti'), oggetto: dec(h(testa, 'Subject')), from: dec(h(testa, 'From')), ct, html, testo, multipart: !!b };
}
const posta = (dir = POSTA) => (fs.existsSync(dir) ? fs.readdirSync(dir) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => leggi(fs.readFileSync(path.join(dir, f), 'utf8')));
const per = (chi, da = 0) => posta().slice(da).filter(m => m.dest.includes(chi));
const senzaTag = s => s.replace(/<style[\s\S]*?<\/style>/gi, '').replace(/<[^>]+>/g, ' ').replace(/&#0?39;/g, "'").replace(/\s+/g, ' ');

(async () => {
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('dialog', d => d.accept());
  const vai = u => p.goto(B + u, { waitUntil: 'networkidle' });
  await vai('/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  // Preparazione: il server di posta finto, una lista Clienti, il form dei Contatti con doppia conferma nella lista,
  // due flussi (Compila il form; Entra nella lista Clienti), limite orario largo.
  await vai('/admin/impostazioni.php');
  await p.fill('#smtp_host', '127.0.0.1'); await p.fill('#smtp_porta', SMTP); await p.selectOption('#smtp_sicurezza', 'nessuna');
  await p.fill('[name=smtp_mittente]', 'sito@esempio.it');
  await p.fill('[name=notification_email]', 'avvisi@esempio.it');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=save_posta]) button[type=submit]')]);
  await vai('/admin/newsletter.php?scheda=liste');
  await p.fill('form:has(input[value=lista_crea]) input[name=nome]', 'Clienti');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=lista_crea]) button[type=submit]')]);
  const clienti = num("SELECT id FROM {p}newsletter_liste WHERE nome = 'Clienti'");
  const principale = num("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_lista_principale'");
  await vai('/contatti/');
  const form = +((await p.locator('input[name=modulo]').first().getAttribute('value')) || '').replace('form:', '');
  q(`UPDATE {p}moduli SET lista_id = ${clienti}, lista_conferma = 1, titolo = 'Scrivici' WHERE id = ${form}`);
  const fForm = cli('flusso', 'Dopo il form', 'form', form, 'Grazie per averci scritto');
  const fClienti = cli('flusso', 'Benvenuto clienti', 'lista', clienti, 'Benvenuto fra i clienti');
  const impostazioni = async (ora, giorno) => {
    await vai('/admin/newsletter.php?scheda=impostazioni');
    await p.fill('#per_ora', String(ora));
    let fatto = true;
    try { await p.fill('#per_giorno', String(giorno), { timeout: 2000 }); } catch (e) { fatto = false; }
    await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=impostazioni]) button[type=submit]')]);
    return fatto;
  };
  await impostazioni(2000, 0);
  ok('preparazione: form, lista Clienti e due flussi', form > 0 && clienti > 0 && principale > 0 && fForm > 0 && fClienti > 0, { form, clienti, principale, fForm, fClienti });

  // Compila il form dei Contatti come una persona. Il limite di 5 invii all'ora per IP si azzera prima (tranne nella prova dei troppi).
  const compila = async (chi, azzera = true) => {
    if (azzera) { q("UPDATE {p}moduli_invii SET ip = ''"); q("UPDATE {p}newsletter_iscritti SET ip = ''"); }
    await vai('/contatti/');
    const f = p.locator('form[data-captcha]').first();
    for (const c of await f.locator('input:not([type=hidden]):not([type=checkbox]), textarea').all()) {
      const nome = await c.getAttribute('name'); const tipo = await c.getAttribute('type');
      if (nome === 'sito_web') continue;
      await c.fill(tipo === 'email' ? chi + '@persona.it' : (tipo === 'tel' ? '+39 333 1234567' : 'Prova ' + chi));
    }
    await f.locator('input[name=privacy]').check();
    await f.locator('.captcha input[type=checkbox]').check();
    await p.waitForSelector('form.verificato', { timeout: 30000 });
    const quante = posta().length;
    await Promise.all([p.waitForNavigation(), f.locator('button[type=submit]').click()]);
    for (let i = 0; i < 20 && posta().length === quante; i++) await dorme(250);
    await dorme(600);
    return { nuove: posta().slice(quante), pagina: await p.locator('body').innerText().catch(() => '') };
  };
  const liste = email => (q(`SELECT l.lista_id FROM {p}newsletter_iscritti_liste l JOIN {p}newsletter_iscritti i ON i.id = l.iscritto_id WHERE i.email = '${email}'`) || []).map(r => +r.lista_id).sort();
  const token = m => ((m.html + m.testo).match(/newsletter-conferma\/\?t=([a-f0-9]{64})/) || [])[1] || '';

  // ===== 1. La doppia conferma di un form: solo la lista del form, e l'email dice per cosa è.
  const a = await compila('anna');
  const confA = a.nuove.find(m => m.dest === 'anna@persona.it');
  const testoA = confA ? (confA.testo || senzaTag(confA.html)).replace(/https?:\/\/\S+/g, '') : '';
  ok('1. email di conferma del form: parla del form («Scrivici»), non della newsletter', !!confA && /Scrivici/.test(confA.oggetto) && /«Scrivici»/.test(testoA) && !/newsletter/i.test(testoA), confA ? [confA.oggetto, testoA.slice(0, 200)] : 'nessuna email');
  ok('1. prima della conferma non sta in nessuna lista (prima: già nella principale e in quella del form)', JSON.stringify(liste('anna@persona.it')) === '[]', liste('anna@persona.it'));
  // Un giro del cron prima che confermi (succede: il cron gira ogni 5 minuti).
  cli('giro');
  ok('2. un giro prima della conferma: nessun cammino fermato per chi aspetta la conferma', num("SELECT COUNT(*) FROM {p}newsletter_corsi c JOIN {p}newsletter_iscritti i ON i.id = c.iscritto_id WHERE i.email = 'anna@persona.it' AND c.stato = 'fermato'") === 0);
  await vai('/newsletter-conferma/?t=' + token(confA || { html: '', testo: '' }));
  const pagConf = await p.locator('body').innerText().catch(() => '');
  ok('1. il link di conferma si apre: iscrizione confermata', /Sei dentro/.test(pagConf), pagConf.slice(0, 120));
  ok('1. dopo la conferma: attiva, nella lista del form e non nella principale', uno("SELECT stato FROM {p}newsletter_iscritti WHERE email = 'anna@persona.it'") === 'attivo' && JSON.stringify(liste('anna@persona.it')) === JSON.stringify([clienti]), liste('anna@persona.it'));
  // L'aggancio: chi ha confermato e da quale form.
  const b = await compila('bruno');
  const confB = b.nuove.find(m => m.dest === 'bruno@persona.it');
  const esito = cli('conferma', token(confB || { html: '', testo: '' }));
  ok('1. aggancio: newsletter_conferma_esito() dice l\'iscritto, la lista e il form d\'origine', !!esito && esito.form === form && esito.lista === clienti && esito.iscritto > 0 && esito.email === 'bruno@persona.it' && esito.nuovo === true, esito);
  ok('1. aggancio: lo stesso link una seconda volta non vale più', cli('conferma', token(confB || { html: '', testo: '' })) === null);

  // ===== 2. Il flusso "Compila un form" parte alla conferma.
  const prima2 = posta().length;
  cli('giro');
  await dorme(500);
  const flussoA = per('anna@persona.it', prima2);
  ok('2. dopo la conferma il flusso "Compila un form" manda la sua email', flussoA.some(m => m.oggetto === 'Grazie per averci scritto'), flussoA.map(m => m.oggetto));
  ok('2. e partono anche i flussi della lista del form', flussoA.some(m => m.oggetto === 'Benvenuto fra i clienti'), flussoA.map(m => m.oggetto));
  ok('2. anche per chi ha confermato con l\'aggancio (bruno)', per('bruno@persona.it', prima2).some(m => m.oggetto === 'Grazie per averci scritto'));

  // ===== 3. Il registro di tutte le email, il limite orario e quello giornaliero.
  const tipi = q("SELECT tipo, COUNT(*) n FROM {p}posta_registro WHERE esito = 'ok' GROUP BY tipo");
  const tipiN = Array.isArray(tipi) ? Object.fromEntries(tipi.map(r => [r.tipo, +r.n])) : {};
  ok('3. il registro della posta conta conferme, avvisi dei form e flussi', tipiN.conferma >= 2 && tipiN.avviso_form >= 2 && tipiN.flusso >= 4, tipi);
  cli('invia', 10);
  // Una persona attiva nella principale e una in attesa: la campagna va solo alla prima.
  q(`INSERT INTO {p}newsletter_iscritti (email, nome, stato, fonte, creato, aggiornato) VALUES ('carla@persona.it', 'Carla', 'attivo', 'modulo', NOW(), NOW()), ('dario@persona.it', 'Dario', 'in_attesa', 'modulo', NOW(), NOW())`);
  q(`INSERT INTO {p}newsletter_iscritti_liste (iscritto_id, lista_id, aggiunto) SELECT id, ${principale}, NOW() FROM {p}newsletter_iscritti WHERE email IN ('carla@persona.it', 'dario@persona.it')`);
  await impostazioni(10, 0);
  const c1 = cli('campagna', principale, 'Novità di ottobre');
  const prima3 = posta().length;
  const g1 = cli('giro');
  ok('3. limite orario a 10 con più di 10 email già partite (non campagne): la campagna aspetta', JSON.stringify(g1).includes('limite orario raggiunto') && per('carla@persona.it', prima3).length === 0, g1);
  const conGiorno = await impostazioni(2000, 15);
  const g2 = cli('giro');
  ok('3. limite al giorno nelle impostazioni (15 nelle ultime 24 ore): la campagna aspetta', conGiorno && JSON.stringify(g2).includes('limite giornaliero raggiunto') && per('carla@persona.it', prima3).length === 0, { conGiorno, g2 });
  await impostazioni(2000, 0);
  const g3 = cli('giro');
  await dorme(500);
  const camp = per('carla@persona.it', prima3);
  ok('3. senza limite la campagna parte, solo agli attivi', camp.length === 1 && per('dario@persona.it', prima3).length === 0, { g3, n: camp.length });

  // ===== 4. Mittente e testo semplice.
  const cm = camp[0] || { ct: '', testo: '', from: '' };
  ok('4. SMTP: l\'email HTML esce in multipart/alternative con la parte di testo', cm.multipart && /multipart\/alternative/i.test(cm.ct) && /ecco le novit/.test(cm.testo) && /<html/i.test(cm.html), { ct: cm.ct, testo: cm.testo.slice(0, 120) });
  const smDir = fs.mkdtempSync(path.join(os.tmpdir(), 'sendmail-'));
  let smOk = '';
  try { smOk = execFileSync('php', ['-d', `sendmail_path=python3 -I ${path.join(__dirname, 'sendmail-finto.py')} ${smDir}`, CLI, DIR, 'senza_smtp', 'ada@persona.it']).toString().trim(); } catch (e) { smOk = 'errore ' + e.message.slice(0, 200); }
  const sm = posta(smDir)[0] || { from: '', args: '', ct: '', testo: '', multipart: false };
  ok('4. mail() senza SMTP: From dal mittente di Posta in uscita', /<sito@esempio\.it>/.test(sm.from), { smOk, from: sm.from });
  ok('4. mail() senza SMTP: Return-Path con -f', /-fsito@esempio\.it/.test(sm.args), sm.args);
  ok('4. mail() senza SMTP: multipart con il testo semplice generato dall\'HTML', sm.multipart && /Testo della prova con un link \(https:\/\/esempio\.it\/x\)/.test(sm.testo), { ct: sm.ct, testo: sm.testo.slice(0, 160) });
  fs.rmSync(smDir, { recursive: true, force: true });

  // ===== 5. Cancella dati.
  const idA = num("SELECT id FROM {p}newsletter_iscritti WHERE email = 'anna@persona.it'");
  // Un'apertura vera: il pixel dell'email del flusso.
  const pixel = ((flussoA[0] || { html: '' }).html.match(/<img src="([^"]*api\/nl\.php[^"]*)"/) || [])[1];
  if (pixel) await p.request.get(pixel.replace(/&amp;/g, '&'));
  const conta = () => ({
    eventi: num(`SELECT COUNT(*) FROM {p}newsletter_eventi WHERE iscritto_id = ${idA}`), corsi: num(`SELECT COUNT(*) FROM {p}newsletter_corsi WHERE iscritto_id = ${idA}`),
    invii: num("SELECT COUNT(*) FROM {p}moduli_invii WHERE email = 'anna@persona.it'"), iscritto: num("SELECT COUNT(*) FROM {p}newsletter_iscritti WHERE email = 'anna@persona.it'"),
  });
  const primaC = conta();
  await vai('/admin/newsletter.php?scheda=iscritti&cerca=anna%40persona.it');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=cancella]) button[type=submit]')]);
  const dopoC = conta();
  const impronta = crypto.createHash('sha256').update('anna@persona.it').digest('hex');
  ok('5. prima: aperture, cammini nei flussi e invii del form ci sono', primaC.eventi > 0 && primaC.corsi > 0 && primaC.invii > 0 && primaC.iscritto === 1, primaC);
  ok('5. "Cancella dati" toglie iscritto, aperture, cammini nei flussi e invii dei form', dopoC.eventi === 0 && dopoC.corsi === 0 && dopoC.invii === 0 && dopoC.iscritto === 0, dopoC);
  ok('5. l\'impronta dell\'email (sha256) entra nella lista di soppressione', num(`SELECT COUNT(*) FROM {p}newsletter_soppressi WHERE impronta = '${impronta}'`) === 1);

  // ===== 6. L'importazione.
  q("INSERT INTO {p}newsletter_iscritti (email, nome, stato, fonte, uscito_at, creato, aggiornato) VALUES ('ugo@persona.it', 'Ugo', 'uscito', 'modulo', NOW(), NOW(), NOW())");
  const importa = async (righe, flussi, lista) => {
    const f = path.join(os.tmpdir(), 'importa-' + Date.now() + '.csv');
    fs.writeFileSync(f, righe.join('\n') + '\n');
    await vai('/admin/newsletter.php?scheda=iscritti');
    const imp = p.locator('form:has(input[value=importa])');
    await imp.locator('input[type=file]').setInputFiles(f);
    await imp.locator('select[name=lista]').selectOption(String(lista));
    await imp.locator('input[name=consenso_brevo]').check();
    let spunta = true;
    if (flussi) { try { await imp.locator('input[name=flussi_import]').check({ timeout: 2000 }); } catch (e) { spunta = false; } }
    await Promise.all([p.waitForNavigation(), imp.locator('button[type=submit]').click()]);
    fs.rmSync(f, { force: true });
    return { spunta, flash: (await p.locator('.flash').first().innerText().catch(() => '')).trim() };
  };
  const i1 = await importa(['EMAIL;NOME;CONSENSO', 'anna@persona.it;Anna;', 'ugo@persona.it;Ugo;', 'xena@imp.it;Xena;2025-05-01', 'yuri@imp.it;Yuri;'], false, clienti);
  const riga = e => (q(`SELECT stato, fonte, consenso_at FROM {p}newsletter_iscritti WHERE email = '${e}'`) || [])[0] || {};
  ok('6. importati: fonte "import", consenso con la data del file (o vuoto se il file non la porta)', riga('xena@imp.it').fonte === 'import' && String(riga('xena@imp.it').consenso_at).startsWith('2025-05-01') && riga('yuri@imp.it').consenso_at === null, [riga('xena@imp.it'), riga('yuri@imp.it'), i1.flash]);
  ok('6. chi ha chiesto la cancellazione dei dati non rientra con l\'importazione', num("SELECT COUNT(*) FROM {p}newsletter_iscritti WHERE email = 'anna@persona.it'") === 0);
  ok('6. chi è uscito resta fuori, anche dalla lista', riga('ugo@persona.it').stato === 'uscito' && JSON.stringify(liste('ugo@persona.it')) === '[]', liste('ugo@persona.it'));
  ok('6. senza la spunta i flussi della lista non partono per gli importati', num(`SELECT COUNT(*) FROM {p}newsletter_corsi c JOIN {p}newsletter_iscritti i ON i.id = c.iscritto_id WHERE i.email IN ('xena@imp.it', 'yuri@imp.it')`) === 0);
  const i2 = await importa(['EMAIL', 'zeno@imp.it'], true, clienti);
  ok('6. con la spunta "Fai partire i flussi per gli importati" partono', i2.spunta && num(`SELECT COUNT(*) FROM {p}newsletter_corsi c JOIN {p}newsletter_iscritti i ON i.id = c.iscritto_id WHERE i.email = 'zeno@imp.it' AND c.flusso_id = ${fClienti}`) === 1, i2);

  // ===== 7. Rimbalzi.
  await importa(['EMAIL;NOME', 'rimbalza@persona.it;Rita', 'rinvia@persona.it;Rino'], false, principale);
  cli('campagna', principale, 'Novità di novembre');
  const giri = [cli('giro'), cli('giro'), cli('giro')];
  const rb = (q("SELECT stato, errori_di_fila, ultimo_errore FROM {p}newsletter_iscritti WHERE email = 'rimbalza@persona.it'") || [])[0] || {};
  const rv = (q("SELECT stato, errori_di_fila FROM {p}newsletter_iscritti WHERE email = 'rinvia@persona.it'") || [])[0] || {};
  ok('7. un 550 al RCPT TO: l\'iscritto passa a "rimbalzato"', rb.stato === 'rimbalzato' && /550/.test(rb.ultimo_errore || ''), { rb, giri });
  ok('7. tre 451 di fila: resta attivo, con tre errori di fila', rv.stato === 'attivo' && +rv.errori_di_fila === 3, rv);
  const c3 = cli('campagna', principale, 'Novità di dicembre');
  cli('giro');
  ok('7. il rimbalzato non riceve più le campagne', num(`SELECT COUNT(*) FROM {p}newsletter_coda q JOIN {p}newsletter_iscritti i ON i.id = q.iscritto_id WHERE q.campagna_id = ${c3} AND i.email = 'rimbalza@persona.it'`) === 0 && num(`SELECT COUNT(*) FROM {p}newsletter_coda q JOIN {p}newsletter_iscritti i ON i.id = q.iscritto_id WHERE q.campagna_id = ${c3} AND i.email = 'carla@persona.it'`) === 1);
  q(`INSERT IGNORE INTO {p}newsletter_iscritti_liste (iscritto_id, lista_id, aggiunto) SELECT id, ${clienti}, NOW() FROM {p}newsletter_iscritti WHERE email = 'rimbalza@persona.it'`);
  q(`INSERT IGNORE INTO {p}newsletter_corsi (flusso_id, iscritto_id, passo, prossimo, stato, creato, aggiornato) SELECT ${fClienti}, id, 1, NOW(), 'attivo', NOW(), NOW() FROM {p}newsletter_iscritti WHERE email = 'rimbalza@persona.it'`);
  const prima7 = posta().length;
  cli('giro');
  ok('7. il rimbalzato non riceve più i flussi', num("SELECT COUNT(*) FROM {p}newsletter_corsi c JOIN {p}newsletter_iscritti i ON i.id = c.iscritto_id WHERE i.email = 'rimbalza@persona.it' AND c.stato = 'attivo'") === 0 && per('rimbalza', prima7).length === 0);
  await vai('/admin/newsletter.php?scheda=iscritti&stato=rimbalzato');
  const tR = await p.locator('table.table').innerText().catch(() => '');
  ok('7. Iscritti e liste: il filtro Rimbalzati mostra lo stato "rimbalzato"', /rimbalza@persona\.it/.test(tR) && /rimbalzato/.test(tR) && !/carla@persona\.it/.test(tR), tR.slice(0, 200));
  await vai('/admin/newsletter.php?scheda=iscritti');
  const tE = await p.locator('body').innerText().catch(() => '');
  ok('7. Iscritti e liste: gli errori temporanei di fila segnalati (avviso in cima e nella riga)', /rinvia@persona\.it\s+Rino\s+attivo\s+\d+ errori temporanei di fila/.test(tE) && /avuto tre errori temporanei di fila/.test(tE), tE.slice(tE.indexOf('rimbalzati'), tE.indexOf('rimbalzati') + 200));

  // ===== Minori. L'errore "troppe richieste" del form con doppia conferma.
  q("UPDATE {p}moduli_invii SET ip = ''");
  q("INSERT INTO {p}newsletter_iscritti (email, stato, fonte, ip, creato, aggiornato) VALUES ('t1@troppi.it','in_attesa','modulo','127.0.0.1',NOW(),NOW()),('t2@troppi.it','in_attesa','modulo','127.0.0.1',NOW(),NOW()),('t3@troppi.it','in_attesa','modulo','127.0.0.1',NOW(),NOW()),('t4@troppi.it','in_attesa','modulo','127.0.0.1',NOW(),NOW()),('t5@troppi.it','in_attesa','modulo','127.0.0.1',NOW(),NOW())");
  const t = await compila('elena', false);
  ok('minori: troppe richieste di iscrizione dallo stesso IP, il form mostra l\'errore (prima: grazie e nessuna email)', /Hai già inviato diverse richieste/.test(t.pagina) && !t.nuove.some(m => m.dest === 'elena@persona.it'), t.pagina.slice(0, 160));
  q("DELETE FROM {p}newsletter_iscritti WHERE email LIKE '%@troppi.it'");

  // La pulizia del cron: in attesa con la conferma scaduta da più di 30 giorni, registro oltre 60 giorni.
  q("INSERT INTO {p}newsletter_iscritti (email, stato, fonte, conferma_scade, creato, aggiornato) VALUES ('vecchio@attesa.it','in_attesa','modulo',DATE_SUB(NOW(), INTERVAL 40 DAY),DATE_SUB(NOW(), INTERVAL 47 DAY),DATE_SUB(NOW(), INTERVAL 47 DAY)),('recente@attesa.it','in_attesa','modulo',DATE_SUB(NOW(), INTERVAL 10 DAY),DATE_SUB(NOW(), INTERVAL 17 DAY),DATE_SUB(NOW(), INTERVAL 17 DAY))");
  q("INSERT INTO {p}posta_registro (quando, tipo, esito) VALUES (DATE_SUB(NOW(), INTERVAL 70 DAY), 'prova', 'ok')");
  q("DELETE FROM {p}settings WHERE setting_key = 'newsletter_pulizia_giorno'");
  try { execFileSync('php', [path.join(DIR, 'cron-newsletter.php')], { stdio: 'ignore' }); } catch (e) {}
  ok('minori: il cron toglie gli iscritti in attesa con la conferma scaduta da più di 30 giorni, non gli altri', num("SELECT COUNT(*) FROM {p}newsletter_iscritti WHERE email = 'vecchio@attesa.it'") === 0 && num("SELECT COUNT(*) FROM {p}newsletter_iscritti WHERE email = 'recente@attesa.it'") === 1);
  ok('minori: il cron toglie il registro della posta più vecchio di 60 giorni', num("SELECT COUNT(*) FROM {p}posta_registro WHERE quando < DATE_SUB(NOW(), INTERVAL 60 DAY)") === 0 && num('SELECT COUNT(*) FROM {p}posta_registro') > 0);

  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
