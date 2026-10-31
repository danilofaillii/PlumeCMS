// La consegna delle email (2.1.0), nel browser come un amministratore:
//   A. la pagina Impostazioni > Consegna delle email e il menu;
//   B. il controllo del dominio con un DNS finto (record a posto, mancanti, doppi, "+all", selettore scritto, posta gratuita,
//      chiave corta, il servizio scelto, DNS che non risponde);
//   C. i mittenti: aggiunta, link di conferma (il GET non conferma, il pulsante sì), predefinito, scelta nella campagna e nel flusso;
//   D. Brevo, Amazon SES e Mailgun contro il server finto delle API (prove/api-posta-finta.py, che controlla chiave, firma
//      SigV4 e Basic): prova dal pannello con l'allegato, una campagna vera con il mittente scelto e il riferimento;
//   E. gli avvisi dei tre servizi (webhook): rimbalzo, spam, doppione, iscrizione di Amazon SNS; uscita dal link con la campagna;
//   F. le statistiche per campagna con rimbalzi, uscite e spam;
//   G. il riscaldamento: stato, limite che sale, giro fermato al limite;
//   H. telefono a 390 px senza scorrimento di lato, nessun errore JavaScript.
// Su un CMS installato (kit Attività), con il server di posta finto acceso:
//   python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> &
// uso: node prove/prova-consegna-email.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella del CMS installato> <porta api finta>
// Scrive nel config.local.php le costanti delle prove (blocco "prove della consegna") e lascia dati di prova: sito da buttare.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const C = require('./consegna-comune');
const [B, UTENTE, PASSWORD, SMTP, POSTA, DIR, PORTA_API] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const dorme = ms => new Promise(r => setTimeout(r, ms));
const cli = (...a) => C.cli(DIR, ...a);
const q = s => cli('sql', s);
const uno = s => { const r = q(s); return Array.isArray(r) && r[0] ? Object.values(r[0])[0] : null; };
const emlPosta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => fs.readFileSync(path.join(POSTA, f), 'utf8'));
const decodifica = eml => eml.replace(/\r?\n\r?\n([A-Za-z0-9+\/=\r\n]{40,})/g, (m, b) => '\n\n' + Buffer.from(b.replace(/\s+/g, ''), 'base64').toString('utf8'));

(async () => {
  const P = C.prepara(DIR, PORTA_API);
  await dorme(2500);
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('dialog', d => d.accept());
  const vai = u => p.goto(B + u, { waitUntil: 'networkidle' });
  const testo = async () => (await p.locator('body').innerText()).replace(/\s+/g, ' ');
  const flash = async () => (await p.locator('.flash').allInnerTexts().catch(() => [])).join(' | ');
  try {
    // Da capo: niente mittenti, servizio e riscaldamento di una prova di prima.
    q('DELETE FROM {p}posta_mittenti'); q('DELETE FROM {p}posta_avvisi'); q("DELETE FROM {p}settings WHERE setting_key IN ('posta_servizio', 'posta_riscaldamento', 'consegna_ultimo_controllo')");
    await C.entra(p, B, UTENTE, PASSWORD);
    // Il server di posta finto per i link di conferma (finché non c'è un servizio con le API).
    await vai('/admin/impostazioni.php');
    await p.fill('#smtp_host', '127.0.0.1'); await p.fill('#smtp_porta', SMTP); await p.selectOption('#smtp_sicurezza', 'nessuna');
    await p.fill('[name=smtp_utente]', ''); await p.fill('[name=smtp_mittente]', 'sito@buono.it'); await p.fill('[name=notification_email]', 'avvisi@buono.it');
    await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=save_posta]) button[type=submit]')]);
    ok('preparazione: Configurazione email con il server finto, nota di Brevo SMTP con l\'host giusto', /smtp-relay\.brevo\.com/.test(await p.locator('#posta-brevo-smtp').innerText()) && /587/.test(await p.locator('#posta-brevo-smtp').innerText()));

    // ===== A. La pagina e il menu
    await vai('/admin/index.php');
    const voce = p.locator('nav a[href="consegna.php"]');
    ok('A. nel menu Impostazioni c\'è "Consegna delle email"', await voce.count() === 1 && /Consegna delle email/.test(await voce.textContent()));
    await vai('/admin/consegna.php');
    const passi = await p.locator('.ce-passi li').count();
    ok('A. Da dove cominciare: cinque passi, il mittente di adesso è quello di Configurazione email', passi === 5 && /sito@buono\.it/.test(await testo()), passi);

    // ===== B. Il controllo del dominio
    const dominio = async (d, sel = '') => { await vai('/admin/consegna.php?scheda=dominio&dominio=' + encodeURIComponent(d) + (sel ? '&selettore=' + sel : '')); };
    const statoVoce = async k => ((await p.locator('#ce-' + k + ' .ce-stato').first().innerText().catch(() => '')) || '').trim();
    const valore = async k => (await p.locator('#ce-' + k + ' .ce-valore').inputValue().catch(() => ''));
    await dominio('buono.it');
    ok('B. buono.it: SPF, DKIM, DMARC e MX a posto, niente messaggio per l\'hosting', (await statoVoce('spf')) === 'A posto' && (await statoVoce('dkim')) === 'A posto' && (await statoVoce('dmarc')) === 'A posto' && (await statoVoce('mx')) === 'A posto' && await p.locator('#ce-messaggio').count() === 0,
      [await statoVoce('spf'), await statoVoce('dkim'), await statoVoce('dmarc'), await statoVoce('mx')]);
    ok('B. buono.it: dice il selettore DKIM trovato (default)', /selettore default/.test(await p.locator('#ce-dkim').innerText()));
    await dominio('vuoto.it');
    const msg = await p.locator('#ce-messaggio').inputValue().catch(() => '');
    ok('B. vuoto.it: SPF, DKIM e DMARC mancano, MX da sistemare', (await statoVoce('spf')) === 'Manca' && (await statoVoce('dkim')) === 'Manca' && (await statoVoce('dmarc')) === 'Manca' && (await statoVoce('mx')) === 'Da sistemare');
    ok('B. vuoto.it: il record SPF da incollare (v=spf1 +a +mx ~all) e quello DMARC con il nome _dmarc', (await valore('spf')) === 'v=spf1 +a +mx ~all' && /^v=DMARC1; p=none/.test(await valore('dmarc'))
      && (await p.locator('#ce-dmarc .ce-record input').nth(1).inputValue()) === '_dmarc.vuoto.it', [await valore('spf'), await valore('dmarc')]);
    ok('B. vuoto.it: il messaggio per l\'hosting con il dominio e i record', /vuoto\.it/.test(msg) && /v=spf1 \+a \+mx ~all/.test(msg) && /_dmarc\.vuoto\.it/.test(msg) && /DKIM/.test(msg) && /Buongiorno/.test(msg), msg.slice(0, 200));
    ok('B. le caselle si copiano con un tocco (data-copia-se-stesso), niente gestori in linea', await p.locator('#ce-spf [data-copia-se-stesso]').count() === 3 && await p.locator('[onclick],[onchange]').count() === 0);
    await dominio('doppio.it');
    const unito = await valore('spf');
    ok('B. doppio.it: due SPF da unire in uno, con tutti i pezzi una volta e -all', (await statoVoce('spf')) === 'Da sistemare' && unito === 'v=spf1 include:_spf.google.com a mx -all', unito);
    await dominio('aperto.it');
    ok('B. aperto.it: "+all" segnalato, proposto ~all', (await statoVoce('spf')) === 'Da sistemare' && /\+all/.test(await p.locator('#ce-spf').innerText()) && / ~all$/.test(await valore('spf')), await valore('spf'));
    await dominio('sel.it');
    const senzaSel = await statoVoce('dkim');
    await dominio('sel.it', 'abc123');
    ok('B. sel.it: DKIM non trovato con i selettori comuni, trovato con quello scritto (abc123); DMARC p=reject spiegato', senzaSel === 'Manca' && (await statoVoce('dkim')) === 'A posto' && /p=reject/.test(await p.locator('#ce-dmarc').innerText()), senzaSel);
    await dominio('solo.it');
    ok('B. solo.it: chiave DKIM corta (1024 bit) da rifare', (await statoVoce('dkim')) === 'Da sistemare' && /2048/.test(await p.locator('#ce-dkim').innerText()));
    await dominio('gmail.com');
    ok('B. gmail.com: avviso della posta gratuita come mittente', await p.locator('#ce-gratuito').count() === 1);
    await dominio('non un dominio');
    ok('B. un dominio scritto male: lo dice', /non è un dominio/.test(await testo()));
    const dnsVero = fs.readFileSync(P.dns, 'utf8');
    fs.writeFileSync(P.dns, JSON.stringify({ _spento: true }));
    await dominio('buono.it');
    ok('B. DNS che non risponde: lo dice, senza inventare record', await p.locator('#ce-dns-muto').count() === 1 && await p.locator('.ce-voce').count() === 0);
    fs.writeFileSync(P.dns, dnsVero);

    // ===== C. I mittenti
    const quanteMail = emlPosta().length;
    await vai('/admin/consegna.php?scheda=mittenti');
    await p.fill('#ce-m-nome', 'Notizie di Buono'); await p.fill('#ce-m-email', 'notizie@buono.it');
    await Promise.all([p.waitForNavigation(), p.click('#ce-nuovo-mittente button[type=submit]')]);
    let conferma = '';
    for (let i = 0; i < 20 && !conferma; i++) { await dorme(250); const m = emlPosta().slice(quanteMail).map(decodifica).find(e => /X-Destinatari: notizie@buono\.it/.test(e)); if (m) conferma = (m.match(/api\/mittente\.php\?t=[a-f0-9]{64}/) || [''])[0]; }
    ok('C. mittente aggiunto: "Aspetta la conferma" e un\'email con il link al suo indirizzo', /Aspetta la conferma/.test(await p.locator('tr[data-mittente="notizie@buono.it"]').innerText()) && conferma !== '', conferma);
    const pc = await ctx.newPage();
    await pc.goto(B + '/' + conferma, { waitUntil: 'networkidle' });
    const dopoGet = uno("SELECT COUNT(*) FROM {p}posta_mittenti WHERE email = 'notizie@buono.it' AND verificato_at IS NOT NULL");
    await Promise.all([pc.waitForNavigation(), pc.click('button[type=submit]')]);
    ok('C. il link apre una pagina con il pulsante: il GET non conferma, il pulsante sì', +dopoGet === 0 && /Mittente confermato/.test(await pc.locator('body').innerText()), dopoGet);
    await pc.goto(B + '/' + conferma, { waitUntil: 'networkidle' }); await Promise.all([pc.waitForNavigation(), pc.click('button[type=submit]')]);
    ok('C. lo stesso link una seconda volta: scaduto o già usato', /già stato usato/.test(await pc.locator('body').innerText()));
    await pc.close();
    await vai('/admin/consegna.php?scheda=mittenti');
    ok('C. confermato e predefinito (il primo)', /Confermato/.test(await p.locator('tr[data-mittente="notizie@buono.it"]').innerText()) && /Predefinito/.test(await p.locator('tr[data-mittente="notizie@buono.it"]').innerText()));
    // Un secondo mittente, per una campagna.
    const prima2 = emlPosta().length;
    await p.fill('#ce-m-nome', 'Anna di Buono'); await p.fill('#ce-m-email', 'anna@buono.it'); await p.fill('#ce-m-risp', 'risposte@buono.it');
    await Promise.all([p.waitForNavigation(), p.click('#ce-nuovo-mittente button[type=submit]')]);
    let conf2 = '';
    for (let i = 0; i < 20 && !conf2; i++) { await dorme(250); const m = emlPosta().slice(prima2).map(decodifica).find(e => /X-Destinatari: anna@buono\.it/.test(e)); if (m) conf2 = (m.match(/api\/mittente\.php\?t=[a-f0-9]{64}/) || [''])[0]; }
    const fromConf = (emlPosta().slice(prima2).find(e => /X-Destinatari: anna@buono\.it/.test(e)) || '').match(/^From: .*$/m);
    ok('C. con SMTP la conferma del secondo parte dal predefinito (notizie@buono.it)', !!fromConf && /notizie@buono\.it/.test(fromConf[0]), fromConf && fromConf[0]);
    const pc2 = await ctx.newPage(); await pc2.goto(B + '/' + conf2, { waitUntil: 'networkidle' }); await Promise.all([pc2.waitForNavigation(), pc2.click('button[type=submit]')]); await pc2.close();
    const anna = +uno("SELECT id FROM {p}posta_mittenti WHERE email = 'anna@buono.it' AND verificato_at IS NOT NULL");
    ok('C. secondo mittente confermato, non predefinito', anna > 0 && +uno('SELECT predefinito FROM {p}posta_mittenti WHERE id = ' + anna) === 0);
    await vai('/admin/consegna.php?scheda=mittenti');
    await p.fill('#ce-m-email', 'notizie@buono.it');
    await Promise.all([p.waitForNavigation(), p.click('#ce-nuovo-mittente button[type=submit]')]);
    ok('C. lo stesso indirizzo due volte: lo dice', /c'è già/.test(await flash()));

    // Iscritti e campagne di prova.
    const principale = +uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_lista_principale'");
    const iscrivi = nomi => nomi.map(e => {
      q(`INSERT INTO {p}newsletter_iscritti (email, nome, stato, fonte, consenso_versione, consenso_at, creato, aggiornato) VALUES ('${e}', 'Prova', 'attivo', 'import', 'prova', NOW(), NOW(), NOW()) ON DUPLICATE KEY UPDATE stato = 'attivo', errori_di_fila = 0`);
      const id = +uno(`SELECT id FROM {p}newsletter_iscritti WHERE email = '${e}'`);
      q(`INSERT IGNORE INTO {p}newsletter_iscritti_liste (iscritto_id, lista_id, aggiunto) VALUES (${id}, ${principale}, NOW())`);
      return id;
    });
    const lista = 'cg' + Date.now().toString(36);
    q(`INSERT INTO {p}newsletter_liste (nome, descrizione, creato) VALUES ('${lista}', '', NOW())`);
    const listaId = +uno(`SELECT id FROM {p}newsletter_liste WHERE nome = '${lista}'`);
    const campagna = (oggetto, chi) => {
      for (const e of chi) { const id = +uno(`SELECT id FROM {p}newsletter_iscritti WHERE email = '${e}'`); q(`INSERT IGNORE INTO {p}newsletter_iscritti_liste (iscritto_id, lista_id, aggiunto) VALUES (${id}, ${listaId}, NOW())`); }
      q(`DELETE FROM {p}newsletter_iscritti_liste WHERE lista_id = ${listaId} AND iscritto_id NOT IN (SELECT id FROM {p}newsletter_iscritti WHERE email IN ('${chi.join("','")}'))`);
      q(`INSERT INTO {p}newsletter_campagne (oggetto, anteprima, corpo, liste, stato, creato, aggiornato) VALUES ('${oggetto}', '', '<p>Ciao {nome}, novità.</p><p><a href="https://buono.it/x">Leggi</a></p>', '${listaId}', 'bozza', NOW(), NOW())`);
      return +uno(`SELECT MAX(id) FROM {p}newsletter_campagne`);
    };
    const parti = id => { q(`UPDATE {p}newsletter_campagne SET stato = 'programmata', invio_at = NOW() WHERE id = ${id}`); return cli('giro'); };

    // ===== D. I tre servizi
    const servizio = async (s, campi) => {
      await vai('/admin/consegna.php?scheda=servizio');
      await p.selectOption('#ce-servizio', s);
      for (const [sel, v] of Object.entries(campi)) { if ((await p.locator(sel).evaluate(e => e.tagName)) === 'SELECT') await p.selectOption(sel, v); else await p.fill(sel, v); }
      await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=servizio]) button[type=submit]')]);
      return flash();
    };
    const prova = async a => { await vai('/admin/consegna.php?scheda=servizio'); await p.fill('#ce-prova', a); await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=prova]) button[type=submit]')]); return flash(); };
    const nuovi = da => C.arrivati(P.api).slice(da);
    const tutteLeSchede = async () => { let h = ''; for (const s of ['inizio', 'dominio', 'servizio', 'mittenti', 'avvisi', 'riscaldamento']) { await vai('/admin/consegna.php?scheda=' + s); h += await p.content(); } return h; };

    // Brevo
    let f = await servizio('brevo', { '#ce-brevo': 'xsmtpsib-' + 'a'.repeat(40) });
    ok('D. Brevo: la chiave SMTP nella casella delle API si riconosce e si spiega', /chiave SMTP di Brevo/.test(f), f);
    f = await servizio('brevo', { '#ce-brevo': C.CHIAVI.brevo });
    ok('D. Brevo: salvato, "le email partono da Brevo"', /partono da Brevo/.test(f), f);
    let da = C.arrivati(P.api).length;
    f = await prova('prova@persona.it');
    let m = nuovi(da).find(x => x.servizio === 'brevo');
    ok('D. Brevo: la prova dal pannello arriva al server finto con la chiave giusta, l\'allegato e il mittente predefinito', /Prova spedita/.test(f) && !!m && m.sender.email === 'notizie@buono.it' && m.to[0].email === 'prova@persona.it'
      && m._allegati.length === 1 && m._allegati[0].nome === 'prova-plumecms.txt' && /Allegato di prova/.test(m._allegati[0].dati) && /<p>/.test(m.htmlContent) && m.textContent.length > 20, [f, m && m.sender]);
    da = C.arrivati(P.api).length;
    const inv = cli('invia', 'cli@persona.it');
    m = nuovi(da)[0];
    ok('D. Brevo: posta_invia() con l\'allegato .ics (firma invariata, sesto argomento)', inv[0] === true && !!m && m._allegati[0].nome === 'evento.ics', inv);
    const iB = iscrivi(['b1@persona.it', 'b2@persona.it', 'b3@persona.it']);
    const cB = campagna('Novità Brevo', ['b1@persona.it', 'b2@persona.it', 'b3@persona.it']);
    await vai('/admin/newsletter-campagna.php?id=' + cB);
    const opzioni = await p.locator('#mittente_id option').allInnerTexts();
    await p.selectOption('#mittente_id', String(anna));
    await Promise.all([p.waitForNavigation(), p.click('button[name=azione][value=salva]')]);
    ok('C. la campagna sceglie il mittente fra i confermati (tendina con il predefinito e i due)', opzioni.length === 3 && /Il predefinito/.test(opzioni[0]) && +uno('SELECT mittente_id FROM {p}newsletter_campagne WHERE id = ' + cB) === anna, opzioni);
    da = C.arrivati(P.api).length;
    const giroB = parti(cB);
    const mB = nuovi(da).filter(x => x.servizio === 'brevo');
    const rifB = Object.fromEntries(mB.map(x => [x.to[0].email, x.headers && x.headers['X-Mailin-custom']]));
    ok('D. Brevo: la campagna parte a 3 persone con il mittente scelto (Anna) e il Reply-To suo', mB.length === 3 && mB.every(x => x.sender.email === 'anna@buono.it' && x.sender.name === 'Anna di Buono' && x.replyTo && x.replyTo.email === 'risposte@buono.it'), [giroB, mB.map(x => x.sender)]);
    ok('D. Brevo: ogni email porta il riferimento firmato (X-Mailin-custom) e List-Unsubscribe con la campagna', mB.every(x => /^c\d+-f0-p0-i\d+-[a-f0-9]{16}$/.test(x.headers['X-Mailin-custom'] || '') && /c=\d+&f=0&p=0&k=/.test(x.headers['List-Unsubscribe'] || '') && x.headers['List-Unsubscribe-Post']), mB.map(x => x.headers));

    // Amazon SES
    f = await servizio('ses', { '#ce-ses-regione': 'eu-south-1', '#ce-ses-id': C.CHIAVI.sesId, '#ce-ses-segreto': C.CHIAVI.sesSegreto, '#ce-ses-set': 'plumecms' });
    ok('D. SES: salvato, "le email partono da Amazon SES"', /partono da Amazon SES/.test(f), f);
    ok('D. SES: i due vettori di prova di AWS SigV4 (get-vanilla e query ordinata) danno le firme di AWS', (() => { const v = cli('sigv4'); return Array.isArray(v) && /Signature=5fa00fa31553b73ebf1942676e86291e8372ff2a2260956d9b8aae1d763fbf31$/.test(v[0]) && /Signature=b97d918cfa904a5beff61c982a1b6f458b799221646efd99d3219ec94cdf2500$/.test(v[1]); })());
    da = C.arrivati(P.api).length;
    f = await prova('prova@persona.it');
    m = nuovi(da).find(x => x.servizio === 'ses');
    ok('D. SES: la prova passa la firma SigV4 del server finto, regione eu-south-1, allegato nel MIME, set di configurazione', /Prova spedita/.test(f) && !!m && m.regione === 'eu-south-1' && m.FromEmailAddress === 'notizie@buono.it' && m.allegati.length === 1 && m.allegati[0].nome === 'prova-plumecms.txt' && m.ConfigurationSetName === 'plumecms' && m.firmate.includes('host'), [f, m && m.regione]);
    const iS = iscrivi(['s1@persona.it', 's2@persona.it', 's3@persona.it']);
    const cS = campagna('Novità SES', ['s1@persona.it', 's2@persona.it', 's3@persona.it']);
    q(`UPDATE {p}newsletter_campagne SET mittente_id = ${anna} WHERE id = ${cS}`);
    da = C.arrivati(P.api).length;
    parti(cS);
    const mS = nuovi(da).filter(x => x.servizio === 'ses');
    ok('D. SES: la campagna parte a 3 con From Anna nel MIME, il tag "plume" e X-Plume-Rif', mS.length === 3 && mS.every(x => /anna@buono\.it/.test(x.from) && x.EmailTags[0] && x.EmailTags[0].Name === 'plume' && x.EmailTags[0].Value === x.rif), mS.map(x => [x.from, x.EmailTags]));

    // Mailgun
    f = await servizio('mailgun', { '#ce-mg-dominio': 'mg.buono.it', '#ce-mg-regione': 'eu', '#ce-mg-chiave': C.CHIAVI.mailgun });
    ok('D. Mailgun: salvato, "le email partono da Mailgun"', /partono da Mailgun/.test(f), f);
    da = C.arrivati(P.api).length;
    f = await prova('prova@persona.it');
    m = nuovi(da).find(x => x.servizio === 'mailgun');
    ok('D. Mailgun: la prova arriva con Basic api:chiave, dominio mg.buono.it, allegato nel MIME', /Prova spedita/.test(f) && !!m && m.dominio === 'mg.buono.it' && m.campi.to === 'prova@persona.it' && m.allegati.length === 1 && m.allegati[0].nome === 'prova-plumecms.txt', [f, m && m.campi]);
    const iM = iscrivi(['m1@persona.it', 'm2@persona.it', 'm3@persona.it']);
    const cM = campagna('Novità Mailgun', ['m1@persona.it', 'm2@persona.it', 'm3@persona.it']);
    da = C.arrivati(P.api).length;
    parti(cM);
    const mM = nuovi(da).filter(x => x.servizio === 'mailgun');
    ok('D. Mailgun: la campagna parte a 3 con il predefinito e la variabile v:plume', mM.length === 3 && mM.every(x => /notizie@buono\.it/.test(x.from) && x.campi['v:plume'] === x.rif && /^c\d+-/.test(x.rif)), mM.map(x => x.campi));

    // Chiavi mai nella pagina, cifrate nel database.
    const html = await tutteLeSchede();
    const grezzo = String(cli('impostazione', 'posta_servizio'));
    ok('D. nessuna chiave nell\'HTML delle sei schede', ![C.CHIAVI.brevo, C.CHIAVI.sesSegreto, C.CHIAVI.mailgun].some(k => html.includes(k)));
    ok('D. nel database le chiavi sono cifrate (v1:...), mai in chiaro', ![C.CHIAVI.brevo, C.CHIAVI.sesSegreto, C.CHIAVI.mailgun].some(k => grezzo.includes(k)) && (grezzo.match(/"v1:/g) || []).length === 3, grezzo.slice(0, 120));
    // Una chiave sbagliata: errore in parole semplici.
    await servizio('brevo', { '#ce-brevo': 'xkeysib-' + 'f'.repeat(64) + '-sbagliata1234567' });
    f = await prova('prova@persona.it');
    ok('D. Brevo con la chiave sbagliata: "non accetta la chiave", niente al server', /non accetta la chiave/.test(f), f);
    await servizio('brevo', { '#ce-brevo': C.CHIAVI.brevo });
    await servizio('mailgun', {});
    ok('D. tornando a Mailgun la chiave salvata resta (campo vuoto = tienila)', /partono da Mailgun/.test(await flash()));

    // ===== E. Gli avvisi dei servizi
    const url = s => cli('webhook', s);
    const statoDi = e => uno(`SELECT stato FROM {p}newsletter_iscritti WHERE email = '${e}'`);
    let r = await C.posta(url('brevo'), { event: 'hard_bounce', email: 'b1@persona.it', id: 901, 'message-id': '<1@x>', ts_event: 1, reason: '550 5.1.1 utente sconosciuto', 'X-Mailin-custom': rifB['b1@persona.it'] });
    ok('E. Brevo hard_bounce: iscritto rimbalzato', r.status === 200 && statoDi('b1@persona.it') === 'rimbalzato', r);
    r = await C.posta(url('brevo'), { event: 'spam', email: 'b2@persona.it', id: 902, 'message-id': '<2@x>', ts_event: 2, 'X-Mailin-custom': rifB['b2@persona.it'] });
    const soppB2 = +uno(`SELECT COUNT(*) FROM {p}newsletter_soppressi WHERE impronta = SHA2('b2@persona.it', 256)`);
    ok('E. Brevo spam: stato "ha segnalato spam", in soppressione, fuori dalla coda', r.status === 200 && statoDi('b2@persona.it') === 'spam' && soppB2 === 1, [r, statoDi('b2@persona.it'), soppB2]);
    r = await C.posta(url('brevo'), { event: 'spam', email: 'b2@persona.it', id: 902, 'message-id': '<2@x>', ts_event: 2, 'X-Mailin-custom': rifB['b2@persona.it'] });
    ok('E. lo stesso avviso due volte conta una volta ("già ricevuto")', r.status === 200 && r.j.esiti[0] === 'già ricevuto', r);
    // Amazon SNS: prima l'iscrizione all'argomento, poi rimbalzo e reclamo.
    const arn = 'arn:aws:sns:eu-south-1:123456789012:plumecms-avvisi';
    r = await C.posta(url('ses'), C.sns(P.T, { Type: 'SubscriptionConfirmation', MessageId: 'm-1', Token: 'tok', TopicArn: arn, Message: 'You have chosen to subscribe', SubscribeURL: 'https://sns.eu-south-1.amazonaws.com/?Action=ConfirmSubscription&Token=tok' }));
    ok('E. SES: l\'iscrizione di Amazon SNS si conferma e l\'argomento si ricorda', r.status === 200 && /confermata/.test(r.j.esiti[0]) && String(cli('impostazione', 'posta_servizio')).includes(arn), r);
    const rifS = Object.fromEntries(mS.map(x => [x.to.replace(/[<>]/g, ''), x.rif]));
    r = await C.posta(url('ses'), C.sns(P.T, { Type: 'Notification', MessageId: 'm-2', TopicArn: arn, Message: JSON.stringify({ notificationType: 'Bounce', bounce: { bounceType: 'Permanent', bouncedRecipients: [{ emailAddress: 's1@persona.it', diagnosticCode: 'smtp; 550 5.1.1 user unknown' }] }, mail: { tags: { plume: [rifS['s1@persona.it']] } } }) }));
    ok('E. SES Bounce Permanent firmato: rimbalzato', r.status === 200 && statoDi('s1@persona.it') === 'rimbalzato', r);
    r = await C.posta(url('ses'), C.sns(P.T, { Type: 'Notification', MessageId: 'm-3', TopicArn: arn, Message: JSON.stringify({ notificationType: 'Complaint', complaint: { complainedRecipients: [{ emailAddress: 's2@persona.it' }], complaintFeedbackType: 'abuse' }, mail: { tags: { plume: [rifS['s2@persona.it']] } } }) }));
    ok('E. SES Complaint firmato: ha segnalato spam', r.status === 200 && statoDi('s2@persona.it') === 'spam', r);
    r = await C.posta(url('ses'), C.sns(P.T, { Type: 'Notification', MessageId: 'm-4', TopicArn: arn, Message: JSON.stringify({ notificationType: 'Bounce', bounce: { bounceType: 'Transient', bouncedRecipients: [{ emailAddress: 's3@persona.it' }] }, mail: {} }) }));
    ok('E. SES Bounce Transient: resta attivo, un errore di fila', r.status === 200 && statoDi('s3@persona.it') === 'attivo' && +uno("SELECT errori_di_fila FROM {p}newsletter_iscritti WHERE email = 's3@persona.it'") === 1, r);
    // Mailgun: la chiave di firma dal pannello, poi gli avvisi firmati.
    await vai('/admin/consegna.php?scheda=avvisi');
    await p.fill('#ce-mg-firma', C.CHIAVI.mailgunFirma);
    await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=mailgun_firma]) button[type=submit]')]);
    ok('E. Mailgun: chiave di firma salvata dal pannello', /firma di Mailgun salvata/.test(await flash()));
    const rifM = Object.fromEntries(mM.map(x => [x.campi.to, x.campi['v:plume']]));
    r = await C.posta(url('mailgun'), { signature: C.mailgunFirma(C.CHIAVI.mailgunFirma), 'event-data': { event: 'failed', severity: 'permanent', id: 'mg-1', recipient: 'm1@persona.it', 'user-variables': { plume: rifM['m1@persona.it'] }, 'delivery-status': { description: 'No such mailbox' } } });
    ok('E. Mailgun failed permanent firmato: rimbalzato', r.status === 200 && statoDi('m1@persona.it') === 'rimbalzato', r);
    r = await C.posta(url('mailgun'), { signature: C.mailgunFirma(C.CHIAVI.mailgunFirma), 'event-data': { event: 'complained', id: 'mg-2', recipient: 'm2@persona.it', 'user-variables': { plume: rifM['m2@persona.it'] } } });
    ok('E. Mailgun complained firmato: ha segnalato spam', r.status === 200 && statoDi('m2@persona.it') === 'spam', r);
    // L'uscita dal link dell'email (List-Unsubscribe della campagna Mailgun): si conta per la campagna.
    const uscita = (mM.find(x => x.campi.to === 'm3@persona.it').list_unsubscribe || '').replace(/^<|>$/g, '');
    const pu = await ctx.newPage();
    await pu.goto(uscita, { waitUntil: 'networkidle' });
    await Promise.all([pu.waitForNavigation(), pu.click('form button[type=submit]')]);
    ok('E. uscita dal link con la campagna firmata: fuori', /Sei fuori/.test(await pu.locator('body').innerText()) && statoDi('m3@persona.it') === 'uscito', uscita);
    await pu.close();
    // Uscita dal link di Brevo per la campagna Brevo, e da Brevo stesso (unsubscribed).
    const uB = (mB.find(x => x.to[0].email === 'b3@persona.it').headers['List-Unsubscribe'] || '').replace(/^<|>$/g, '');
    const ub = await (await fetch(uB, { method: 'POST', body: 'List-Unsubscribe=One-Click', headers: { 'Content-Type': 'application/x-www-form-urlencoded' } })).text();
    ok('E. uscita in un clic (List-Unsubscribe-Post) dal programma di posta: fuori', /Sei fuori/.test(ub) && statoDi('b3@persona.it') === 'uscito');
    r = await C.posta(url('mailgun'), { signature: C.mailgunFirma(C.CHIAVI.mailgunFirma), 'event-data': { event: 'unsubscribed', id: 'mg-3', recipient: 's3@persona.it' } });
    ok('E. Mailgun unsubscribed (senza riferimento): esce lo stesso, trovato dall\'email', r.status === 200 && statoDi('s3@persona.it') === 'uscito', r);
    await vai('/admin/consegna.php?scheda=avvisi');
    const righeAvvisi = await p.locator('#ce-avvisi tbody tr').count();
    ok('E. il pannello elenca gli avvisi ricevuti e dice "Ultimo avviso" per i tre servizi', righeAvvisi >= 8 && await p.locator('#ce-avvisi-brevo .ce-ok, #ce-avvisi-ses .ce-ok, #ce-avvisi-mailgun .ce-ok').count() === 3, righeAvvisi);
    ok('E. il pannello dà un indirizzo per servizio, con il segreto, da copiare', (await p.locator('#ce-avvisi-brevo .ce-record input').inputValue()).includes('/api/posta-avvisi.php?s=brevo&k=') && (await p.locator('#ce-avvisi-ses .ce-record input').inputValue()).includes('s=ses') && (await p.locator('#ce-avvisi-mailgun .ce-record input').inputValue()).includes('s=mailgun'));
    await vai('/admin/newsletter.php?scheda=iscritti&stato=spam');
    ok('E. Iscritti: filtro "Hanno segnalato spam" con le tre persone e il riquadro del numero', await (async () => { const t = await p.locator('table').innerText(); return ['b2@persona.it', 's2@persona.it', 'm2@persona.it'].every(e => t.includes(e)) && !/b1@persona\.it|b3@persona\.it/.test(t); })() && /hanno segnalato spam/.test(await testo()));

    // ===== F. Statistiche per campagna
    await vai('/admin/newsletter-statistiche.php');
    const riga = async id => { const t = p.locator(`tr:has(a[href="newsletter-statistiche.php?campagna=${id}"])`); return [await t.locator('.st-rimbalzi b').innerText(), await t.locator('.st-uscite b').innerText(), await t.locator('.st-spam b').innerText()]; };
    const sB = await riga(cB), sS = await riga(cS), sM = await riga(cM);
    ok('F. Brevo: 1 rimbalzo, 1 uscita, 1 spam', JSON.stringify(sB) === '["1","1","1"]', sB);
    ok('F. SES: 1 rimbalzo, 0 uscite (quella di Mailgun era senza riferimento), 1 spam', JSON.stringify(sS) === '["1","0","1"]', sS);
    ok('F. Mailgun: 1 rimbalzo, 1 uscita, 1 spam', JSON.stringify(sM) === '["1","1","1"]', sM);
    ok('F. lo spam al 33% e i rimbalzi sopra il 2% sono in rosso, con la spiegazione delle soglie', await p.locator(`tr:has(a[href="newsletter-statistiche.php?campagna=${cB}"]) .st-spam .kw-bad`).count() === 1 && /0,3%/.test(await testo()));

    // ===== G. Riscaldamento
    await vai('/admin/consegna.php?scheda=riscaldamento');
    await p.check('#ce-acceso'); await p.fill('#ce-partenza', '10'); await p.fill('#ce-arrivo', '200'); await p.selectOption('#ce-fattore', '1.5');
    await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=riscaldamento]) button[type=submit]')]);
    const statoCaldo = await p.locator('#ce-caldo-stato').innerText().catch(() => '');
    ok('G. acceso: giorno 1, oggi al massimo 10, domani 15, arriva a 200 fra 8 giorni', /Giorno 1/.test(statoCaldo) && /al massimo 10 email/.test(statoCaldo) && /domani 15/.test(statoCaldo) && /fra 8 giorni/.test(statoCaldo), statoCaldo);
    const giorni = await p.locator('#ce-giorni li').allInnerTexts();
    ok('G. la tabella dei giorni sale da sola: 10, 15, 23, 34... fino a 200', giorni[0].includes('10') && giorni[1].includes('15') && giorni[2].includes('23') && giorni[3].includes('34') && giorni[giorni.length - 1].includes('200'), giorni);
    const tre = cli('riscaldamento', 3);
    ok('G. partito tre giorni fa: oggi 34, giorno 4', tre.oggi === 34 && tre.giorno === 4, tre);
    await vai('/admin/newsletter.php');
    ok('G. la pagina della newsletter dice a che punto è il riscaldamento', /Riscaldamento acceso, giorno 1/.test(await p.locator('#nl-riscaldamento').innerText().catch(() => '')));
    const registro = +uno("SELECT COUNT(*) FROM {p}posta_registro WHERE quando > DATE_SUB(NOW(), INTERVAL 1 DAY) AND esito = 'ok'");
    const iW = iscrivi(['w1@persona.it']);
    const cW = campagna('Riscaldamento', ['w1@persona.it']);
    const giroW = parti(cW);
    ok('G. con ' + registro + ' email partite nelle 24 ore e il limite di oggi a 10, il giro si ferma: "limite del riscaldamento"', registro >= 10 && JSON.stringify(giroW).includes('limite del riscaldamento') && +uno('SELECT inviate FROM {p}newsletter_campagne WHERE id = ' + cW) === 0, giroW);
    await vai('/admin/consegna.php?scheda=riscaldamento');
    await p.uncheck('#ce-acceso');
    await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=riscaldamento]) button[type=submit]')]);
    ok('G. spento: la campagna ferma riparte al giro dopo', JSON.stringify(cli('giro')).includes('inviate 1') && +uno('SELECT inviate FROM {p}newsletter_campagne WHERE id = ' + cW) === 1);

    // Il flusso sceglie il suo mittente.
    await vai('/admin/newsletter-flussi.php?nuovo=1');
    const opzFl = await p.locator('#fl-mittente option').count();
    ok('C. il flusso ha la tendina del mittente (predefinito e i due confermati)', opzFl === 3, opzFl);
    await p.fill('input[name=nome]', 'Flusso con Anna'); await p.check('input[name=attivo]'); await p.selectOption('#fl-mittente', String(anna));
    await Promise.all([p.waitForNavigation(), p.click('.fl-testa button[type=submit]')]);
    const fl = +uno("SELECT id FROM {p}newsletter_flussi WHERE nome = 'Flusso con Anna' ORDER BY id DESC LIMIT 1");
    ok('C. il flusso salva il mittente scelto', fl > 0 && +uno('SELECT mittente_id FROM {p}newsletter_flussi WHERE id = ' + fl) === anna, fl);
    const [w2] = iscrivi(['flusso@persona.it']);
    const nodoEmail = (JSON.parse(uno('SELECT passi FROM {p}newsletter_flussi WHERE id = ' + fl)).nodi.find(x => x.tipo === 'email') || {}).id;
    q(`INSERT INTO {p}newsletter_corsi (flusso_id, iscritto_id, passo, prossimo, stato, creato, aggiornato) VALUES (${fl}, ${w2}, ${nodoEmail}, NOW(), 'attivo', NOW(), NOW())`);
    da = C.arrivati(P.api).length;
    cli('giro');
    const mF = nuovi(da).find(x => x.campi && x.campi.to === 'flusso@persona.it');
    ok('C. l\'email del flusso parte con il suo mittente (Anna) e il riferimento del flusso', !!mF && /anna@buono\.it/.test(mF.from) && new RegExp(`^c0-f${fl}-p${nodoEmail}-i${w2}-`).test(mF.rif), mF && [mF.from, mF.rif]);
    await vai('/admin/consegna.php');
    ok('A. Da dove cominciare: dominio controllato e servizio scelto si vedono', /Mailgun/.test(await p.locator('.ce-passi').innerText()) && /Collegato/.test(await p.locator('.ce-passi').innerText()));

    // ===== H. Telefono
    const tel = await br.newContext({ viewport: { width: 390, height: 844 }, storageState: await ctx.storageState() });
    const t = await tel.newPage();
    t.on('pageerror', e => err.push('telefono: ' + e.message.slice(0, 200)));
    const larghe = [];
    for (const u of ['consegna.php?scheda=inizio', 'consegna.php?scheda=dominio&dominio=vuoto.it', 'consegna.php?scheda=dominio&dominio=doppio.it', 'consegna.php?scheda=servizio', 'consegna.php?scheda=mittenti', 'consegna.php?scheda=avvisi', 'consegna.php?scheda=riscaldamento', 'newsletter-statistiche.php', 'newsletter-campagna.php?id=' + cB, 'newsletter.php?scheda=iscritti&stato=spam', 'impostazioni.php']) {
      await t.goto(B + '/admin/' + u, { waitUntil: 'networkidle' });
      const w = await t.evaluate(() => document.documentElement.scrollWidth);
      if (w > 391) larghe.push([u, w]);
    }
    ok('H. a 390 px nessuna delle 11 pagine scorre di lato', larghe.length === 0, larghe);
    await tel.close();

    // Pulizia: di nuovo SMTP, niente mittenti (le altre prove sul sito si aspettano Configurazione email).
    await servizio('smtp', {});
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
