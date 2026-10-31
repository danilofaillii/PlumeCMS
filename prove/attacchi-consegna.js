// Attacchi alla consegna delle email (2.1.0): webhook falsi o senza firma, CSRF, XSS nei campi, chiavi nell'HTML,
// SSRF dagli indirizzi dei servizi, conferma dei mittenti, redattore. Ogni controllo OK vuol dire attacco respinto.
// Su un CMS installato (kit Attività). Usa il server finto delle API come prova-consegna-email.js.
// uso: node prove/attacchi-consegna.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella del CMS installato> <porta api finta>
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const C = require('./consegna-comune');
const [B, UTENTE, PASSWORD, SMTP, POSTA, DIR, PORTA_API] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const dorme = ms => new Promise(r => setTimeout(r, ms));
const cli = (...a) => C.cli(DIR, ...a);
const q = s => cli('sql', s);
const uno = s => { const r = q(s); return Array.isArray(r) && r[0] ? Object.values(r[0])[0] : null; };

(async () => {
  const P = C.prepara(DIR, PORTA_API);
  await dorme(2500);
  const br = await chromium.launch();
  const ctx = await br.newContext();
  const a = await ctx.newPage();
  const err = []; a.on('pageerror', e => err.push(e.message.slice(0, 200)));
  a.on('dialog', d => d.accept());
  const vai = u => a.goto(B + u, { waitUntil: 'networkidle' });
  try {
    q('DELETE FROM {p}posta_mittenti'); q('DELETE FROM {p}posta_avvisi'); q("DELETE FROM {p}settings WHERE setting_key IN ('posta_servizio', 'posta_riscaldamento')");
    await C.entra(a, B, UTENTE, PASSWORD);
    await vai('/admin/impostazioni.php');
    await a.fill('#smtp_host', '127.0.0.1'); await a.fill('#smtp_porta', SMTP); await a.selectOption('#smtp_sicurezza', 'nessuna'); await a.fill('[name=smtp_mittente]', 'sito@buono.it');
    await Promise.all([a.waitForNavigation(), a.click('form:has(input[value=save_posta]) button[type=submit]')]);
    // Un iscritto vittima.
    q("INSERT INTO {p}newsletter_iscritti (email, nome, stato, fonte, consenso_versione, creato, aggiornato) VALUES ('vittima@persona.it', 'V', 'attivo', 'import', 'x', NOW(), NOW()) ON DUPLICATE KEY UPDATE stato = 'attivo'");
    const vit = +uno("SELECT id FROM {p}newsletter_iscritti WHERE email = 'vittima@persona.it'");
    const stato = () => uno(`SELECT stato FROM {p}newsletter_iscritti WHERE id = ${vit}`);
    const wh = s => cli('webhook', s);
    const base = B + '/api/posta-avvisi.php';

    // ===== 1. Webhook
    const bounce = { event: 'hard_bounce', email: 'vittima@persona.it', id: 1, 'message-id': '<a@b>', ts_event: 1 };
    let r = await C.posta(base + '?s=brevo', bounce);
    ok('1. webhook senza segreto: 403, iscritto intatto', r.status === 403 && stato() === 'attivo', r.status);
    r = await C.posta(base + '?s=brevo&k=' + 'a'.repeat(40), bounce);
    ok('1. webhook con un segreto inventato: 403', r.status === 403 && stato() === 'attivo', r.status);
    r = await C.posta(base + '?s=brevo&k=' + new URL(wh('mailgun')).searchParams.get('k'), bounce);
    ok('1. il segreto di un servizio non vale per un altro (Mailgun su Brevo): 403', r.status === 403 && stato() === 'attivo', r.status);
    r = await C.posta(base + '?s=ftp&k=x', bounce);
    ok('1. servizio sconosciuto: 404', r.status === 404, r.status);
    const g = await fetch(wh('brevo'));
    ok('1. GET sul webhook: 405', g.status === 405, g.status);
    r = await C.posta(wh('brevo'), 'non json <script>');
    ok('1. corpo non JSON: 400', r.status === 400, r.status);
    // Mailgun: segreto giusto ma firma assente, sbagliata, vecchia; chiave di firma non salvata.
    const ev = { event: 'failed', severity: 'permanent', id: 'x1', recipient: 'vittima@persona.it' };
    r = await C.posta(wh('mailgun'), { 'event-data': ev });
    ok('1. Mailgun senza firma (e senza chiave di firma salvata): 403', r.status === 403 && stato() === 'attivo', r.status);
    await vai('/admin/consegna.php?scheda=avvisi');
    await a.fill('#ce-mg-firma', C.CHIAVI.mailgunFirma);
    await Promise.all([a.waitForNavigation(), a.click('form:has(input[value=mailgun_firma]) button[type=submit]')]);
    r = await C.posta(wh('mailgun'), { signature: C.mailgunFirma('chiave-sbagliata-123456'), 'event-data': ev });
    ok('1. Mailgun firmato con un\'altra chiave: 403', r.status === 403 && stato() === 'attivo', r.status);
    r = await C.posta(wh('mailgun'), { signature: C.mailgunFirma(C.CHIAVI.mailgunFirma, Math.floor(Date.now() / 1000) - 3600), 'event-data': ev });
    ok('1. Mailgun con una firma vera ma di un\'ora fa (ripetuto): 403', r.status === 403 && stato() === 'attivo', r.status);
    const sig = C.mailgunFirma(C.CHIAVI.mailgunFirma);
    r = await C.posta(wh('mailgun'), { signature: { ...sig, timestamp: String(+sig.timestamp + 1) }, 'event-data': ev });
    ok('1. Mailgun con il timestamp cambiato: 403', r.status === 403 && stato() === 'attivo', r.status);
    // SES: firma falsa, firmato con un'altra chiave, campo cambiato dopo la firma, certificato non di Amazon.
    const msg = { Type: 'Notification', MessageId: 'z1', TopicArn: 'arn:aws:sns:eu-west-1:123456789012:x', Message: JSON.stringify({ notificationType: 'Complaint', complaint: { complainedRecipients: [{ emailAddress: 'vittima@persona.it' }] }, mail: {} }) };
    r = await C.posta(wh('ses'), { ...msg, SignatureVersion: '1', Signature: Buffer.from('falsa').toString('base64'), SigningCertURL: 'https://sns.eu-west-1.amazonaws.com/x.pem' });
    ok('1. SES con una firma inventata: 403', r.status === 403 && stato() === 'attivo', r.status);
    const altra = crypto.generateKeyPairSync('rsa', { modulusLength: 2048 }).privateKey.export({ type: 'pkcs1', format: 'pem' });
    r = await C.posta(wh('ses'), C.sns(P.T, msg, altra));
    ok('1. SES firmato con una chiave che non è quella del certificato: 403', r.status === 403 && stato() === 'attivo', r.status);
    const firmato = C.sns(P.T, msg);
    r = await C.posta(wh('ses'), { ...firmato, Message: firmato.Message.replace('Complaint', 'Bounce') });
    ok('1. SES con il messaggio cambiato dopo la firma: 403', r.status === 403 && stato() === 'attivo', r.status);
    // Senza il certificato delle prove il CMS scarica il certificato solo da sns.<regione>.amazonaws.com.
    // Il server PHP delle prove tiene il config in opcache fino a 2 secondi: si aspetta che lo rilegga.
    C.costanti(DIR, P.T, PORTA_API, false); await dorme(3000);
    r = await C.posta(wh('ses'), C.sns(P.T, { ...msg, MessageId: 'z2' }, null));
    const r2 = await C.posta(wh('ses'), { ...C.sns(P.T, { ...msg, MessageId: 'z3' }), SigningCertURL: `http://127.0.0.1:${PORTA_API}/cert.pem` });
    const r3 = await C.posta(wh('ses'), { ...C.sns(P.T, { ...msg, MessageId: 'z4' }), SigningCertURL: 'https://sns.eu-west-1.amazonaws.com.attacco.it/x.pem' });
    ok('1. SES con il certificato da un indirizzo non di Amazon (http locale, dominio che imita): 403 senza scaricarlo', r2.status === 403 && r3.status === 403 && stato() === 'attivo', [r.status, r2.status, r3.status]);
    C.costanti(DIR, P.T, PORTA_API, true); await dorme(3000);
    // Argomento SNS diverso da quello confermato.
    await C.posta(wh('ses'), C.sns(P.T, { Type: 'SubscriptionConfirmation', MessageId: 'c1', Token: 't', TopicArn: 'arn:aws:sns:eu-west-1:123456789012:buono', Message: 'x', SubscribeURL: 'https://sns.eu-west-1.amazonaws.com/?Action=ConfirmSubscription' }));
    r = await C.posta(wh('ses'), C.sns(P.T, { ...msg, MessageId: 'z5', TopicArn: 'arn:aws:sns:eu-west-1:999999999999:altro' }));
    ok('1. SES da un argomento diverso da quello collegato: 403', r.status === 403 && stato() === 'attivo', r.status);
    // Riferimento falsificato: l'avviso vero (Brevo) non attribuisce eventi a una campagna con una firma inventata.
    q("INSERT INTO {p}newsletter_campagne (oggetto, anteprima, corpo, liste, stato, inviate, creato, aggiornato) VALUES ('Bersaglio', '', '<p>x</p>', '', 'inviata', 10, NOW(), NOW())");
    const camp = +uno('SELECT MAX(id) FROM {p}newsletter_campagne');
    q("INSERT INTO {p}newsletter_iscritti (email, nome, stato, fonte, consenso_versione, creato, aggiornato) VALUES ('altro@persona.it', 'A', 'attivo', 'import', 'x', NOW(), NOW()) ON DUPLICATE KEY UPDATE stato = 'attivo'");
    const altro = +uno("SELECT id FROM {p}newsletter_iscritti WHERE email = 'altro@persona.it'");
    r = await C.posta(wh('brevo'), { event: 'spam', email: 'altro@persona.it', id: 7, 'message-id': '<r@x>', ts_event: 9, 'X-Mailin-custom': `c${camp}-f0-p0-i${altro}-0123456789abcdef` });
    ok('1. riferimento con la firma inventata: nessuno spam contato per la campagna', r.status === 200 && +uno(`SELECT COUNT(*) FROM {p}newsletter_eventi WHERE campagna_id = ${camp}`) === 0, r);
    const rifVero = cli('rif', camp, 0, 0, vit);
    r = await C.posta(wh('brevo'), { event: 'spam', email: 'altro@persona.it', id: 8, 'message-id': '<s@x>', ts_event: 10, 'X-Mailin-custom': rifVero });
    ok('1. riferimento vero di un\'altra persona: non colpisce la vittima', stato() === 'attivo' && +uno(`SELECT COUNT(*) FROM {p}newsletter_eventi WHERE campagna_id = ${camp} AND iscritto_id = ${vit}`) === 0, r);
    // Uscita con la campagna: k falso non conta per la campagna.
    const usc = cli('uscita', vit, camp).replace(/k=[a-f0-9]+/, 'k=' + '0'.repeat(20));
    await fetch(usc, { method: 'POST' });
    ok('1. uscita con la firma della campagna falsa: esce, ma la campagna non conta l\'uscita', stato() === 'uscito' && +uno(`SELECT COUNT(*) FROM {p}newsletter_eventi WHERE campagna_id = ${camp} AND tipo = 'uscita'`) === 0);
    q(`UPDATE {p}newsletter_iscritti SET stato = 'attivo' WHERE id = ${vit}`);

    // ===== 2. CSRF
    const prima = String(cli('impostazione', 'posta_servizio'));
    const csrf = await fetch(B + '/admin/consegna.php?scheda=servizio', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: (await ctx.cookies()).map(c => c.name + '=' + c.value).join('; ') }, body: 'azione=servizio&servizio=brevo&brevo_chiave=xkeysib-' + 'e'.repeat(40) });
    ok('2. POST senza token CSRF (con la sessione dell\'amministratore): 403, niente cambiato', csrf.status === 403 && String(cli('impostazione', 'posta_servizio')) === prima, csrf.status);
    const csrf2 = await a.request.post(B + '/admin/consegna.php?scheda=avvisi', { form: { azione: 'cambia_indirizzi', _csrf: 'sbagliato' }, maxRedirects: 0 });
    ok('2. POST con un token sbagliato: 403', csrf2.status() === 403, csrf2.status());

    // ===== 3. XSS nei campi
    const xss = '"><script>window.__x=1</script><img src=x onerror=window.__x=2>';
    await vai('/admin/consegna.php?scheda=mittenti');
    await a.fill('#ce-m-nome', xss); await a.fill('#ce-m-email', 'xss@buono.it'); await a.fill('#ce-m-risp', '');
    await Promise.all([a.waitForNavigation(), a.click('#ce-nuovo-mittente button[type=submit]')]);
    const nomeSalvato = String(uno("SELECT nome FROM {p}posta_mittenti WHERE email = 'xss@buono.it'"));
    ok('3. nome del mittente con HTML: salvato senza < > "', !/[<>"]/.test(nomeSalvato), nomeSalvato);
    await vai('/admin/consegna.php?scheda=dominio&dominio=' + encodeURIComponent(xss) + '&selettore=' + encodeURIComponent(xss));
    await vai('/admin/consegna.php?scheda=mittenti');
    ok('3. dominio, selettore e nome con HTML: niente script eseguito', await a.evaluate(() => window.__x === undefined));
    await vai('/admin/consegna.php?scheda=dominio&dominio=' + encodeURIComponent(xss));
    ok('3. il dominio scritto torna solo come testo', !(await a.content()).includes('<script>window.__x'));
    // Un avviso con HTML nel motivo e nell'email.
    q("INSERT INTO {p}newsletter_iscritti (email, nome, stato, fonte, consenso_versione, creato, aggiornato) VALUES ('h@persona.it', 'H', 'attivo', 'import', 'x', NOW(), NOW()) ON DUPLICATE KEY UPDATE stato = 'attivo'");
    await C.posta(wh('brevo'), { event: 'hard_bounce', email: 'h@persona.it', id: 11, 'message-id': '<h@x>', ts_event: 11, reason: xss });
    await vai('/admin/consegna.php?scheda=avvisi');
    await vai('/admin/newsletter.php?scheda=iscritti');
    ok('3. motivo del rimbalzo con HTML: nell\'elenco degli avvisi e degli iscritti solo come testo', await a.evaluate(() => window.__x === undefined));
    // Mittente con un indirizzo che spezza le intestazioni.
    await vai('/admin/consegna.php?scheda=mittenti');
    const r4 = await a.request.post(B + '/admin/consegna.php?scheda=mittenti', { form: { _csrf: await a.locator('input[name=_csrf]').first().inputValue(), azione: 'mittente_aggiungi', email: "a@b.it\r\nBcc: tutti@x.it", nome: "N\r\nBcc: x@y.it" } });
    ok('3. indirizzo del mittente con un a capo (intestazioni iniettate): rifiutato', +uno("SELECT COUNT(*) FROM {p}posta_mittenti WHERE email LIKE '%bcc%' OR nome LIKE '%Bcc%'") === 0, r4.status());

    // ===== 4. Chiavi mai visibili
    await vai('/admin/consegna.php?scheda=servizio');
    await a.selectOption('#ce-servizio', 'ses');
    await a.fill('#ce-ses-regione', 'eu-west-1'); await a.fill('#ce-ses-id', C.CHIAVI.sesId); await a.fill('#ce-ses-segreto', C.CHIAVI.sesSegreto);
    await Promise.all([a.waitForNavigation(), a.click('form:has(input[value=servizio]) button[type=submit]')]);
    await a.selectOption('#ce-servizio', 'brevo'); await a.fill('#ce-brevo', C.CHIAVI.brevo);
    await Promise.all([a.waitForNavigation(), a.click('form:has(input[value=servizio]) button[type=submit]')]);
    await a.selectOption('#ce-servizio', 'mailgun'); await a.fill('#ce-mg-dominio', 'mg.buono.it'); await a.fill('#ce-mg-chiave', C.CHIAVI.mailgun);
    await Promise.all([a.waitForNavigation(), a.click('form:has(input[value=servizio]) button[type=submit]')]);
    let tutto = '';
    for (const s of ['inizio', 'dominio', 'servizio', 'mittenti', 'avvisi', 'riscaldamento']) { await vai('/admin/consegna.php?scheda=' + s); tutto += await a.content(); }
    for (const u of ['/admin/impostazioni.php', '/admin/newsletter.php?scheda=impostazioni', '/admin/newsletter-statistiche.php']) { await vai(u); tutto += await a.content(); }
    const chiavi = [C.CHIAVI.brevo, C.CHIAVI.sesSegreto, C.CHIAVI.mailgun, C.CHIAVI.mailgunFirma];
    ok('4. nessuna delle quattro chiavi nell\'HTML del pannello (9 pagine)', !chiavi.some(k => tutto.includes(k)));
    const grezzo = String(cli('impostazione', 'posta_servizio'));
    ok('4. nel database nessuna chiave in chiaro', !chiavi.some(k => grezzo.includes(k)) && /"v1:/.test(grezzo));
    ok('4. i campi delle chiavi sono password e vuoti', await a.evaluate(() => true) && await (async () => { await vai('/admin/consegna.php?scheda=servizio'); return (await a.locator('#ce-brevo').getAttribute('type')) === 'password' && (await a.locator('#ce-brevo').inputValue()) === '' && (await a.locator('#ce-ses-segreto').inputValue()) === '' && (await a.locator('#ce-mg-chiave').inputValue()) === ''; })());
    // Nemmeno il messaggio d'errore di un'API porta la chiave.
    const inv = cli('invia', 'rifiuta@persona.it');
    ok('4. un errore dell\'API non riporta la chiave', inv[0] === false && !chiavi.some(k => String(inv[2]).includes(k)), inv);

    // ===== 5. SSRF dagli indirizzi dei servizi
    const prima5 = String(cli('impostazione', 'posta_servizio'));
    await vai('/admin/consegna.php?scheda=servizio');
    await a.selectOption('#ce-servizio', 'ses'); await a.fill('#ce-ses-regione', 'evil.com/x#');
    await Promise.all([a.waitForNavigation(), a.click('form:has(input[value=servizio]) button[type=submit]')]);
    await a.selectOption('#ce-servizio', 'mailgun'); await a.fill('#ce-mg-dominio', '127.0.0.1:22/x');
    await Promise.all([a.waitForNavigation(), a.click('form:has(input[value=servizio]) button[type=submit]')]);
    const dopo5 = JSON.parse(String(cli('impostazione', 'posta_servizio')));
    ok('5. regione e dominio che portano altrove: rifiutati, resta il valore di prima', dopo5.ses.regione === 'eu-west-1' && dopo5.mailgun.dominio === 'mg.buono.it', [dopo5.ses.regione, dopo5.mailgun.dominio]);
    void prima5;

    // ===== 6. Conferma dei mittenti
    const m1 = await fetch(B + '/api/mittente.php?t=' + 'a'.repeat(64), { method: 'POST' });
    ok('6. token inventato: non conferma niente', /scaduto o è già stato usato/.test(await m1.text()) && +uno('SELECT COUNT(*) FROM {p}posta_mittenti WHERE verificato_at IS NOT NULL') === 0);
    const m2 = await fetch(B + "/api/mittente.php?t=<script>alert(1)</script>");
    ok('6. token con HTML: "link non valido", niente eco', /non è valido/.test(await m2.text()));
    // Un GET (i filtri antispam aprono i link) non conferma.
    const nPosta = (fs.readdirSync(POSTA).filter(f => f.endsWith('.eml')));
    const ultimo = nPosta.sort((x, y) => parseInt(x) - parseInt(y)).map(f => fs.readFileSync(path.join(POSTA, f), 'utf8')).reverse()
      .find(e => /X-Destinatari: xss@buono\.it/.test(e)) || '';
    const dec = ultimo.replace(/\r?\n\r?\n([A-Za-z0-9+\/=\r\n]{40,})/g, (m, b) => Buffer.from(b.replace(/\s+/g, ''), 'base64').toString('utf8'));
    const tok = (dec.match(/mittente\.php\?t=([a-f0-9]{64})/) || [])[1];
    if (tok) await fetch(B + '/api/mittente.php?t=' + tok);
    ok('6. il GET del link (un filtro antispam) non conferma il mittente', !!tok && +uno("SELECT COUNT(*) FROM {p}posta_mittenti WHERE email = 'xss@buono.it' AND verificato_at IS NOT NULL") === 0, !!tok);
    const mit = await fetch(B + '/api/mittente.php?t=' + 'b'.repeat(64));
    ok('6. la pagina di conferma ha le sue intestazioni di sicurezza (CSP, niente frame)', /default-src 'none'/.test(mit.headers.get('content-security-policy') || '') && mit.headers.get('x-frame-options') === 'DENY');
    // Un From non confermato in una campagna non passa: la tendina accetta solo i confermati.
    const nonConf = +uno("SELECT id FROM {p}posta_mittenti WHERE email = 'xss@buono.it'");
    q(`UPDATE {p}newsletter_campagne SET stato = 'bozza' WHERE id = ${camp}`);
    await vai('/admin/newsletter-campagna.php?id=' + camp);
    const tok2 = await a.locator('input[name=_csrf]').first().inputValue();
    await a.request.post(B + '/admin/newsletter-campagna.php?id=' + camp, { form: { _csrf: tok2, azione: 'salva', oggetto: 'Bersaglio', corpo: '<p>x</p>', mittente_id: String(nonConf) } });
    ok('6. una campagna non può usare un mittente non confermato (resta il predefinito)', +uno(`SELECT mittente_id FROM {p}newsletter_campagne WHERE id = ${camp}`) === 0);

    // ===== 7. Redattore
    await vai('/admin/utenti.php');
    const nome = 'red_cons' + Date.now().toString(36).slice(-4);
    await a.fill('form.sc-corpo [name=username]', nome); await a.fill('form.sc-corpo [name=email]', nome + '@esempio.it');
    await a.selectOption('form.sc-corpo [name=role]', 'editor'); await a.fill('form.sc-corpo [name=password]', 'Redattore-Prova-2026'); await a.fill('form.sc-corpo [name=password2]', 'Redattore-Prova-2026');
    await Promise.all([a.waitForNavigation(), a.click('form.sc-corpo button.btn-primary')]);
    const rc = await br.newContext(); const red = await rc.newPage();
    await C.entra(red, B, nome, 'Redattore-Prova-2026');
    let fuori = 0;
    for (const s of ['inizio', 'dominio', 'servizio', 'mittenti', 'avvisi', 'riscaldamento']) { await red.goto(B + '/admin/consegna.php?scheda=' + s); if (!/consegna\.php/.test(red.url()) || /Consegna delle email/.test(await red.title()) === false) fuori++; }
    ok('7. il redattore non apre nessuna delle sei schede', fuori === 6, fuori);
    ok('7. e non vede la voce nel menu', await red.locator('nav a[href="consegna.php"]').count() === 0);
    const pr = await red.request.post(B + '/admin/consegna.php?scheda=riscaldamento', { form: { azione: 'riscaldamento', acceso: '1', partenza: '10' }, maxRedirects: 0 });
    ok('7. un suo POST non accende il riscaldamento', !JSON.parse(String(cli('impostazione', 'posta_riscaldamento') || '{}') || '{}').acceso, pr.status());
    await rc.close();
    q(`DELETE FROM {p}users WHERE username = '${nome}'`);

    // ===== 8. Senza accesso
    let alLogin = 0;
    for (const s of ['inizio', 'servizio', 'avvisi']) { const x = await fetch(`${B}/admin/consegna.php?scheda=${s}`, { redirect: 'manual' }); if (x.status >= 300 && x.status < 400 && /login\.php/.test(x.headers.get('location') || '')) alLogin++; }
    ok('8. senza accesso la pagina rimanda al login', alLogin === 3, alLogin);

    // Pulizia.
    await vai('/admin/consegna.php?scheda=servizio');
    await a.selectOption('#ce-servizio', 'smtp');
    await Promise.all([a.waitForNavigation(), a.click('form:has(input[value=servizio]) button[type=submit]')]);
    q('DELETE FROM {p}posta_mittenti');
    ok('nessun errore JavaScript', err.length === 0, err);
  } catch (e) {
    ok('la prova è arrivata in fondo', false, String(e.stack || e).slice(0, 600));
  } finally {
    await br.close();
    C.fine(P.server);
    console.log(`\n${n - ko} su ${n} OK`);
    process.exit(ko ? 1 : 0);
  }
})();
