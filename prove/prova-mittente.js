// 2.40.1: il nome del mittente nelle email (segnalazione di un sito vero con la 2.39.0: con il nome "[Cucina Creativa]" il
// programma di posta mostrava "MISSING_MAILBOX@MISSING_DOMAIN per conto di [ Cucina Creativa ]"). Il nome deve arrivare al
// programma di posta esattamente come lo scrive chi usa il pannello.
// Controlla, con i messaggi riletti dalla libreria standard di Python (prove/leggi-mittente.py, come un programma di posta):
//   A. unità: posta_nome_indirizzo(), posta_leggi_indirizzo(), posta_oggetto() e i messaggi composti dalle funzioni vere
//      (prove/mittente-cli.php unita) con i cinque nomi, uno lungo non ASCII e uno con l'a capo dentro;
//   B. per ogni via di spedizione (SMTP verso prove/smtp-finto.py, mail() di PHP verso prove/sendmail-finto.py, Brevo, Amazon SES
//      e Mailgun verso prove/api-posta-finta.py) e per ognuno dei cinque nomi, scritto nel pannello come un utente
//      (Impostazioni > Configurazione email, Nome del mittente): il nome salvato resta quello scritto; l'email di prova e
//      l'avviso di prova dal pannello; l'avviso "Accesso amministratore da un indirizzo nuovo" (accesso vero dal browser); i cinque
//      avvisi con il Reply-To del visitatore (form, richiesta di prenotazione, Prenotazioni, Vetrina, Iscrizioni agli eventi), con
//      un nome del visitatore con caratteri speciali; un'email della newsletter con il mittente confermato e il campo "A".
//      From, To e Reply-To riletti con il nome identico, carattere per carattere; nessun MISSING_MAILBOX. Brevo riceve i nomi
//      intatti nel JSON (sender.name, replyTo.name, to[].name), senza virgolette messe a mano.
// Il server del sito va acceso con sendmail_path verso prove/sendmail-finto.py e la cartella <lavoro>/mail:
//   php -d sendmail_path="python3 -I prove/sendmail-finto.py <lavoro>/mail" -S ...
// Lascia dati di prova (un form, invii, un mittente confermato) e la posta configurata sul server finto: va su un sito da buttare.
// uso: node prove/prova-mittente.js <base> <utente> <password> <cartella del CMS> <porta smtp libera> <porta api libera> <cartella di lavoro>
const { chromium } = require('playwright');
const { execFileSync, spawn } = require('child_process');
const fs = require('fs'); const path = require('path');
const pnConferme = require('./conferme-pannello');
const [B, UTENTE, PASSWORD, CMS, PORTA_SMTP, PORTA_API, LAVORO] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const dorme = ms => new Promise(r => setTimeout(r, ms));

const NOMI = ['[Cucina Creativa]', 'Rossi, Mario', 'Caffè "Da Mario"', 'Cucina Creativa', 'Pasta & Vino: il laboratorio'];
const VISITATORI = ['Bianchi, Luca', 'Maria "Mimì" Verdi', '[Ospite] Anna <VIP>', 'Paolo Neri', 'Sara: tavolo 4; ore 20.30'];
const AVVISI = ['form', 'richiesta', 'prenotazioni', 'vetrina', 'eventi'];
const SMTP = path.join(LAVORO, 'smtp'), MAIL = path.join(LAVORO, 'mail'), API = path.join(LAVORO, 'api'), UNITA = path.join(LAVORO, 'unita');
const SENDMAIL = 'sendmail_path=python3 -I ' + path.join(__dirname, 'sendmail-finto.py') + ' ' + MAIL;
const cli = (...a) => {
  try { return JSON.parse(execFileSync('php', ['-d', SENDMAIL, path.join(__dirname, 'mittente-cli.php'), CMS, ...a.map(String)], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim().split('\n').pop()); }
  catch (e) { return { errore: String(e.stderr || e.message).slice(0, 400) }; }
};
const files = (dir, est) => (fs.existsSync(dir) ? fs.readdirSync(dir) : []).filter(f => f.endsWith(est)).sort((a, b) => parseInt(a) - parseInt(b)).map(f => path.join(dir, f));
// Rilegge con Python: ogni riga OK/KO di leggi-mittente.py diventa un controllo qui.
function python(attesi, titolo) {
  const f = path.join(LAVORO, 'attesi-' + Date.now() + '-' + Math.random().toString(36).slice(2) + '.json');
  fs.writeFileSync(f, JSON.stringify(attesi));
  let out = '';
  try { out = execFileSync('python3', ['-I', path.join(__dirname, 'leggi-mittente.py'), f], { encoding: 'utf8' }); } catch (e) { out = String(e.stdout || ''); }
  const righe = out.split('\n').filter(r => /^(OK|KO)  /.test(r));
  if (!righe.length) ok(titolo + ': Python ha letto i messaggi', false, out.slice(0, 300));
  for (const r of righe) ok(titolo + ': ' + r.slice(4), r.startsWith('OK'));
}

(async () => {
  for (const d of [SMTP, MAIL, API, UNITA]) { fs.rmSync(d, { recursive: true, force: true }); fs.mkdirSync(d, { recursive: true }); }

  // A. unità
  const u = cli('unita', UNITA);
  ok('A. mittente-cli.php unita risponde', Array.isArray(u), u);
  for (const c of (Array.isArray(u) ? u : [])) ok('A. ' + c.cosa, c.ok, c.dettaglio === undefined ? '' : c.dettaglio);
  if (fs.existsSync(path.join(UNITA, 'attesi.json'))) {
    const at = JSON.parse(fs.readFileSync(path.join(UNITA, 'attesi.json'), 'utf8')).map(x => ({ ...x, file: path.join(UNITA, x.file) }));
    python(at, 'A. unità');
  }

  // I server finti e le costanti delle prove nel config.
  const cfg = path.join(CMS, 'includes', 'config.local.php');
  // Anche il blocco di prova-consegna-email.js, se c'è: la sua CMS_POSTA_API_PROVA verso un'altra porta vincerebbe sulla nostra.
  let s = fs.readFileSync(cfg, 'utf8').replace(/\n\/\/ prove del mittente: inizio[\s\S]*?\/\/ prove del mittente: fine\n/, '\n')
    .replace(/\n\/\/ prove della consegna: inizio[\s\S]*?\/\/ prove della consegna: fine\n/, '\n');
  fs.writeFileSync(cfg, s + `\n// prove del mittente: inizio\ndefine('CMS_POSTA_API_PROVA', 'http://127.0.0.1:${PORTA_API}');\n// prove del mittente: fine\n`);
  const smtp = spawn('python3', ['-I', path.join(__dirname, 'smtp-finto.py'), PORTA_SMTP, SMTP], { stdio: 'ignore' });
  const api = spawn('python3', ['-I', path.join(__dirname, 'api-posta-finta.py'), PORTA_API, API, 'xkeysib-' + 'a1b2c3d4'.repeat(8) + '-ProvaBrevo123456',
    'AKIAPROVAPROVA123456', 'Prova/Segreta+Amazon' + 'X'.repeat(20), 'key-' + 'f0e1d2c3'.repeat(4)], { stdio: 'ignore' });
  await dorme(2500);
  const br = await chromium.launch();
  try {
    const c = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await c.newPage();
    pnConferme.on(p, d => d.accept());
    const err = []; p.on('pageerror', e => err.push(e.message));
    const entra = async () => {
      await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
      await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
      return !p.url().includes('login.php');
    };
    ok('B. accesso al pannello', await entra());
    const flash = async () => (await p.locator('.flash').allInnerTexts().catch(() => [])).join(' | ');

    // Ogni via: dove arrivano i messaggi e come si leggono.
    const VIE = ['smtp', 'mail', 'brevo', 'ses', 'mailgun'];
    const cartella = via => ({ smtp: SMTP, mail: MAIL }[via] || API);
    const nuovi = async (via, prima, quanti) => {
      const est = via === 'brevo' ? '-brevo.json' : (['ses', 'mailgun'].includes(via) ? '-' + via + '.eml' : '.eml');
      for (let i = 0; i < 60; i++) { if (files(cartella(via), est).length >= prima + quanti) break; await dorme(250); }
      await dorme(400); // l'ultimo file può essere ancora a metà della scrittura
      return files(cartella(via), est).slice(prima);
    };
    const conta = via => files(cartella(via), via === 'brevo' ? '-brevo.json' : (['ses', 'mailgun'].includes(via) ? '-' + via + '.eml' : '.eml')).length;

    for (const via of VIE) {
      const servizio = ['smtp', 'mail'].includes(via) ? 'smtp' : via;
      ok(`B. ${via}: servizio scelto`, cli('consegna', servizio, PORTA_API) === (servizio === 'smtp' ? '' : servizio));
      for (let k = 0; k < NOMI.length; k++) {
        const nome = NOMI[k], vis = VISITATORI[k], t = `B. ${via}, ${JSON.stringify(nome)}`;
        // Il nome del mittente scritto nel pannello, come un utente.
        await p.goto(B + '/admin/impostazioni.php', { waitUntil: 'networkidle' });
        await p.fill('#smtp_host', via === 'mail' ? '' : '127.0.0.1');
        await p.fill('#smtp_porta', String(PORTA_SMTP));
        await p.selectOption('#smtp_sicurezza', 'nessuna');
        await p.fill('#smtp_utente', '');
        await p.fill('#smtp_mittente', 'sito@esempio.it');
        await p.fill('#smtp_nome', nome);
        await p.fill('#notification_email', 'avvisi@esempio.it');
        await Promise.all([p.waitForNavigation(), p.locator('#smtp_nome').locator('xpath=ancestor::form').locator('button[type=submit]').click()]);
        ok(`${t}: il nome salvato resta quello scritto`, (await p.inputValue('#smtp_nome')) === nome, await p.inputValue('#smtp_nome'));
        const attesi = []; const brevo = [];
        const da = [nome, 'sito@esempio.it'];
        // 1. L'email di prova e l'avviso di prova dal pannello.
        let prima = conta(via);
        await p.fill('#prova_a', 'avvisi@esempio.it');
        await Promise.all([p.waitForNavigation(), p.locator('#prova_a').locator('xpath=ancestor::form').locator('button[type=submit]').click()]);
        let f = await nuovi(via, prima, 1);
        ok(`${t}: l'email di prova è partita`, f.length === 1 && /spedita/.test(await flash()), await flash());
        if (f[0]) { attesi.push({ file: f[0], from: da, to: ['', 'avvisi@esempio.it'], subject: 'Prova di spedizione dal pannello' }); brevo.push({ file: f[0], sender: nome }); }
        prima = conta(via);
        await p.goto(B + '/admin/impostazioni.php#avvisi', { waitUntil: 'networkidle' });
        await Promise.all([p.waitForNavigation(), p.locator('#avvisi button', { hasText: 'Manda un avviso di prova' }).click()]);
        f = await nuovi(via, prima, 1);
        ok(`${t}: l'avviso di prova è partito`, f.length === 1, await flash());
        if (f[0]) { attesi.push({ file: f[0], from: da, to: ['', 'avvisi@esempio.it'] }); brevo.push({ file: f[0], sender: nome }); }
        // 2. "Accesso amministratore da un indirizzo nuovo": un accesso vero, con 127.0.0.1 che sembra nuovo.
        await p.goto(B + '/admin/logout.php');
        cli('nuovo-ip');
        prima = conta(via);
        ok(`${t}: di nuovo dentro il pannello`, await entra());
        f = await nuovi(via, prima, 1);
        ok(`${t}: l'avviso dell'accesso da un indirizzo nuovo è partito`, f.length === 1);
        if (f[0]) { attesi.push({ file: f[0], from: da, to: ['', 'avvisi@esempio.it'] }); brevo.push({ file: f[0], sender: nome, oggetto: /Accesso amministratore da un indirizzo nuovo/ }); }
        // 3. I cinque avvisi con il Reply-To del visitatore.
        prima = conta(via);
        const esiti = cli('avvisi', vis, '10.' + (k + 1) + '.' + Math.floor(Math.random() * 250) + '.' + (VIE.indexOf(via) + 1));
        ok(`${t}: i cinque avvisi sono partiti`, esiti && AVVISI.every(a => esiti[a] === ''), esiti);
        // Sei messaggi: la Vetrina manda anche il riepilogo al cliente (Reply-To il negozio, senza nome).
        f = await nuovi(via, prima, 6);
        ok(`${t}: sei messaggi arrivati (cinque avvisi e il riepilogo della Vetrina al cliente)`, f.length === 6, f.length);
        const visti = [];
        for (const x of f) {
          const grezzo = fs.readFileSync(x, 'utf8');
          const chi = via === 'brevo' ? (((JSON.parse(grezzo).replyTo || {}).email) || '') : ((grezzo.split(/\r?\n\r?\n/)[0].replace(/\r?\n[ \t]+/g, ' ').match(/^Reply-To:.*?(cliente\.[a-z]+@esempio\.it|negozio@esempio\.it)/mi) || [])[1] || '');
          visti.push(chi); // (il .eml di Mailgun lo riscrive il server finto con Python, che piega le righe lunghe: si stendono prima)
          if (chi === 'negozio@esempio.it') { attesi.push({ file: x, from: da, reply_to: ['', 'negozio@esempio.it'] }); brevo.push({ file: x, sender: nome }); }
          else { attesi.push({ file: x, from: da, reply_to: [vis, chi] }); brevo.push({ file: x, sender: nome, reply: vis }); }
        }
        ok(`${t}: un avviso per ognuno dei cinque, con il Reply-To del visitatore`, AVVISI.every(a => visti.includes('cliente.' + a + '@esempio.it')), visti);
        // 4. La newsletter: mittente confermato con lo stesso nome, campo "A" col nome del visitatore.
        prima = conta(via);
        const r = cli('newsletter', nome, vis);
        ok(`${t}: l'email della newsletter è partita`, Array.isArray(r) && r[0] === true, r);
        f = await nuovi(via, prima, 1);
        if (f[0]) { attesi.push({ file: f[0], from: [nome, 'notizie@esempio.it'], to: [vis, 'iscritto@esempio.it'] }); brevo.push({ file: f[0], sender: nome, a: vis }); }
        // Rilettura
        if (via === 'brevo') {
          for (const b of brevo) {
            const j = JSON.parse(fs.readFileSync(b.file, 'utf8'));
            ok(`${t}: Brevo riceve il nome del mittente intatto ${path.basename(b.file)}`, j.sender && j.sender.name === b.sender, j.sender);
            if (b.reply) ok(`${t}: Brevo riceve il nome del Reply-To intatto`, j.replyTo && j.replyTo.name === b.reply && /^cliente\./.test(j.replyTo.email), j.replyTo);
            if (b.a) ok(`${t}: Brevo riceve il nome del campo "A" intatto`, j.to && j.to[0] && j.to[0].name === b.a, j.to);
            if (b.oggetto) ok(`${t}: Brevo riceve l'oggetto in chiaro`, b.oggetto.test(j.subject) && !/=\?/.test(j.subject), j.subject);
            ok(`${t}: nessun MISSING nel JSON`, !/MISSING/i.test(JSON.stringify(j)));
          }
        } else python(attesi, t);
      }
    }
    ok('B. nessun errore JavaScript nelle pagine', err.length === 0, err);
  } finally {
    await br.close();
    try { smtp.kill(); } catch (e) {}
    try { api.kill(); } catch (e) {}
    cli('consegna', 'smtp');
    s = fs.readFileSync(cfg, 'utf8').replace(/\n\/\/ prove del mittente: inizio[\s\S]*?\/\/ prove del mittente: fine\n/, '\n');
    fs.writeFileSync(cfg, s);
  }
  console.log(`${n - ko} su ${n}`);
  process.exit(ko ? 1 : 0);
})();
