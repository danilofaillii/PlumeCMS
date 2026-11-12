// La password dimenticata dall'inizio alla fine, come un utente (2.39.1): login, "Password dimenticata?", email
// dell'amministratore, l'email arriva al server di posta finto, il link dell'email apre "Scegli la password nuova" con i due
// campi e il pulsante (fino alla 2.39.0 la pagina si fermava su h() non definita e restava senza campi), password corta e
// password diverse rifiutate con i campi ancora lì, salvataggio, login con la nuova sì e con la vecchia no, link usato una
// seconda volta non valido, token inventato e token storto con il messaggio giusto, telefono a 390 px senza scorrimento di lato,
// nessun errore fatale nel log. Alla fine rimette la password di partenza (con lo stesso giro), che usano le altre prove.
//
// uso: node prove/prova-password-reimposta.js <base> <cartella del CMS> <porta smtp> [utente] [password]
//   base: http://127.0.0.1:9700 oppure http://127.0.0.1:9701/test (cartella); cartella del CMS: quella con includes/ e logs/.
//   La prova accende da sé il server di posta finto (prove/smtp-finto.py) sulla porta data e mette quel server in
//   Impostazioni > Configurazione email del sito (resta lì: va su un sito di prova).
const fs = require('fs'), os = require('os'), path = require('path');
const { spawn, execFileSync } = require('child_process');
const { chromium } = require('playwright');
const { conta, pagina, entraPannello, postaFinta, posta, aspettaEmail, linkDa, largo } = require('./addon-a-comune');

const [B, DIR, SMTP, UTENTE = 'prova', PARTENZA = 'Collaudo-Officina-2026'] = process.argv.slice(2);
if (!B || !DIR || !SMTP) { console.error('uso: node prove/prova-password-reimposta.js <base> <cartella del CMS> <porta smtp> [utente] [password]'); process.exit(2); }
const NUOVA = 'Nuova-Chiave-Sicura-2026';
const sql = (az, q) => execFileSync('php', [path.join(__dirname, 'popup-cli.php'), DIR, az, q], { encoding: 'utf8' }).trim();
const LOG = path.join(DIR, 'logs', 'php-errori.log');
const logOra = () => (fs.existsSync(LOG) ? fs.readFileSync(LOG, 'utf8') : '');
const MALI = /Fatal error|Eccezione non gestita|undefined function|Uncaught/i;

(async () => {
  const s = conta(); const err = [];
  const POSTA = fs.mkdtempSync(path.join(os.tmpdir(), 'posta-pwd-'));
  const smtp = spawn('python3', ['-I', path.join(__dirname, 'smtp-finto.py'), String(SMTP), POSTA], { stdio: 'ignore' });
  await new Promise(r => setTimeout(r, 800));
  const logPrima = logOra().length;
  // Le richieste di link contano 3 l'ora per email e i login sbagliati bloccano: una prova rifatta sullo stesso sito non deve fermarsi lì.
  sql('esegui', "DELETE FROM {p}sicurezza_tentativi WHERE chiave LIKE 'reset-%' OR chiave LIKE 'ip:%' OR chiave LIKE 'utente:%'");
  const EMAIL = sql('uno', `SELECT email FROM {p}users WHERE username = '${UTENTE.replace(/'/g, '')}'`);
  const br = await chromium.launch();
  try {
    // 0. il server di posta finto nel sito
    const adm = await pagina(br, err);
    await entraPannello(adm, B, UTENTE, PARTENZA);
    s.ok('preparazione: entro nel pannello con la password di partenza', !adm.url().includes('login.php'), adm.url());
    await postaFinta(adm, B, SMTP);
    await adm.context().close();

    /** Il giro dal login all'email: ritorna il link dell'email (o ''). */
    const chiediLink = async (p, titolo) => {
      const da = posta(POSTA).length;
      await p.goto(B + '/admin/login.php', { waitUntil: 'networkidle' });
      const a = p.locator('a[href="password-dimenticata.php"]');
      const c = await a.count();
      if (c) await Promise.all([p.waitForNavigation(), a.first().click()]);
      if (titolo) s.ok('dal login, "Password dimenticata?" apre la pagina per chiedere il link', c === 1 && /password-dimenticata\.php/.test(p.url()) && /Password dimenticata/.test(await p.locator('h1').innerText()), p.url());
      await p.fill('#email', EMAIL);
      await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
      const testo = await box(p);
      if (titolo) s.ok('dopo l\'invio la pagina dice che arriva un\'email (senza dire se l\'utente esiste)', /fra poco arriva un'email/.test(testo) && !/login-error/.test(await p.content()), testo.slice(0, 120));
      const m = await aspettaEmail(POSTA, EMAIL, da, x => /Nuova password/.test(x.oggetto));
      const link = linkDa(m, '/admin/password-reimposta.php?t=');
      if (titolo) {
        s.ok('l\'email arriva all\'indirizzo dell\'amministratore, con l\'oggetto giusto', !!m, m && [m.dest, m.oggetto]);
        s.ok('l\'email ha il link con il token, nella cartella giusta del sito', /\/admin\/password-reimposta\.php\?t=[a-f0-9]{64}$/.test(link) && link.startsWith(new URL(B).origin + new URL(B).pathname.replace(/\/$/, '') + '/admin/'), link);
      }
      return link;
    };
    const box = async (p) => p.locator('.login-box').innerText({ timeout: 3000 }).catch(() => '');
    const campiVisibili = async (p) => (await p.locator('#password').isVisible()) && (await p.locator('#password2').isVisible()) && (await p.locator('form button[type=submit]').isVisible());
    /** Manda il modulo senza i controlli del browser (minlength, required): conta la risposta del server. */
    const manda = async (p, p1, p2) => {
      if (!(await campiVisibili(p))) return; // 2.39.0: la pagina senza campi, i controlli dopo danno KO senza fermare la prova
      await p.evaluate(() => document.querySelectorAll('#password,#password2').forEach(i => { i.removeAttribute('minlength'); i.removeAttribute('required'); }));
      await p.fill('#password', p1); await p.fill('#password2', p2);
      await Promise.all([p.waitForNavigation(), p.click('form button[type=submit]')]);
    };

    // 1. il giro completo
    const p = await pagina(br, err);
    const link = await chiediLink(p, true);
    const r = await p.goto(link || B + '/admin/password-reimposta.php', { waitUntil: 'networkidle' });
    const h1 = await p.locator('h1').innerText({ timeout: 3000 }).catch(() => '');
    s.ok('il link apre "Scegli la password nuova" (200)', r && r.status() === 200 && /Scegli la password nuova/.test(h1), [r && r.status(), h1]);
    s.ok('la pagina ha i due campi della password e il pulsante, visibili', await campiVisibili(p));
    s.ok('la pagina dice i requisiti (almeno 12 caratteri)', /Almeno 12 caratteri/.test(await box(p)));
    s.ok('il token è nel modulo come campo nascosto', (await p.locator('input[type=hidden][name=t]').getAttribute('value').catch(() => '')) === (link.split('t=')[1] || 'x'));
    // telefono
    const tel = await pagina(br, err, 390, 844);
    await tel.goto(link, { waitUntil: 'networkidle' });
    s.ok('al telefono (390 px) la pagina non scorre di lato e i campi si vedono', (await largo(tel)) <= 0 && await campiVisibili(tel), await largo(tel));
    await tel.goto(B + '/admin/password-dimenticata.php', { waitUntil: 'networkidle' });
    s.ok('al telefono (390 px) anche "Password dimenticata" non scorre di lato', (await largo(tel)) <= 0, await largo(tel));
    await tel.context().close();
    // password corta
    await manda(p, 'corta12', 'corta12');
    let e = await p.locator('.login-error').innerText({ timeout: 3000 }).catch(() => '');
    s.ok('password corta: errore sotto il titolo ("almeno 12 caratteri")', /almeno 12 caratteri/i.test(e), e);
    s.ok('password corta: i campi e il pulsante restano visibili', await campiVisibili(p));
    // password diverse
    await manda(p, NUOVA, NUOVA + 'x');
    e = await p.locator('.login-error').innerText({ timeout: 3000 }).catch(() => '');
    s.ok('password diverse: "Le due password sono diverse." e i campi restano', /diverse/.test(e) && await campiVisibili(p), e);
    // password con il nome utente
    await manda(p, 'Una-' + UTENTE + '-lunga-2026', 'Una-' + UTENTE + '-lunga-2026');
    e = await p.locator('.login-error').innerText({ timeout: 3000 }).catch(() => '');
    s.ok('password con il nome utente: rifiutata con il motivo', /nome utente|email/.test(e) && await campiVisibili(p), e);
    // salvataggio
    if (await campiVisibili(p)) { await p.fill('#password', NUOVA); await p.fill('#password2', NUOVA); await Promise.all([p.waitForNavigation(), p.click('form button[type=submit]')]); }
    s.ok('salva: si torna al login con "Password reimpostata: entra con quella nuova."', /login\.php\?motivo=reimpostata/.test(p.url()) && /Password reimpostata/.test(await p.locator('body').innerText()), p.url());
    // login con la nuova
    await p.goto(B + '/admin/login.php', { waitUntil: 'networkidle' }).catch(() => {});
    await p.fill('#username', UTENTE); await p.fill('#password', NUOVA);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    s.ok('login con la password nuova: si entra nel pannello', !p.url().includes('login.php'), p.url());
    await p.context().close();
    // login con la vecchia
    const v = await pagina(br, err);
    await entraPannello(v, B, UTENTE, PARTENZA);
    s.ok('login con la password vecchia: rifiutato', v.url().includes('login.php') && /non corretti/.test(await v.locator('[role=alert]').innerText({ timeout: 3000 }).catch(() => '')), v.url());
    await v.context().close();
    // il link una seconda volta, in un contesto nuovo (senza sessione: chi è dentro viene mandato alla dashboard)
    const v2 = await pagina(br, err);
    await v2.goto(link, { waitUntil: 'networkidle' });
    let t = await box(v2);
    s.ok('il link usato una seconda volta non vale più (messaggio e niente campi)', /non è valido o è scaduto/.test(t) && (await v2.locator('#password').count()) === 0, t.slice(0, 120));
    // token inventato e token storto
    await v2.goto(B + '/admin/password-reimposta.php?t=' + require('crypto').randomBytes(32).toString('hex'), { waitUntil: 'networkidle' });
    t = await box(v2);
    s.ok('un token inventato: "non è valido o è scaduto" e il link per chiederne uno nuovo', /non è valido o è scaduto/.test(t) && (await v2.locator('a[href="password-dimenticata.php"]').count()) === 1, t.slice(0, 120));
    const storto = '"><script>alert(1)</script>';
    const rs = await v2.goto(B + '/admin/password-reimposta.php?t=' + encodeURIComponent(storto), { waitUntil: 'networkidle' });
    const hs = await v2.content();
    s.ok('un token storto (con HTML): stesso messaggio, niente HTML ripetuto nella pagina', rs.status() === 200 && /non è valido o è scaduto/.test(hs) && !hs.includes('<script>alert(1)'), rs.status());
    const rp = await v2.context().request.post(B + '/admin/password-reimposta.php', { form: { t: 'a'.repeat(64), password: NUOVA, password2: NUOVA }, maxRedirects: 0 });
    s.ok('un POST con un token inventato: nessuna password cambiata, messaggio del link non valido', rp.status() === 200 && /non è valido o è scaduto/.test(await rp.text()));
    await v2.context().close();

    // 2. la password di partenza, con lo stesso giro (le altre prove la usano)
    const q = await pagina(br, err);
    const link2 = await chiediLink(q, false);
    await q.goto(link2 || B + '/admin/password-reimposta.php', { waitUntil: 'networkidle' });
    if (await campiVisibili(q)) { await q.fill('#password', PARTENZA); await q.fill('#password2', PARTENZA); await Promise.all([q.waitForNavigation(), q.click('form button[type=submit]')]); }
    await q.context().close();
    const w = await pagina(br, err);
    await entraPannello(w, B, UTENTE, PARTENZA);
    s.ok('alla fine la password di partenza torna (secondo giro con un link nuovo) e si entra', !w.url().includes('login.php'), [link2 !== '' && link2 !== link, w.url()]);
    await w.context().close();
  } catch (x) {
    s.ok('la prova arriva in fondo senza eccezioni', false, x.message);
  } finally {
    const nuovi = logOra().slice(logPrima).split('\n').filter(l => MALI.test(l));
    s.ok('nessun errore fatale nel log degli errori del sito (logs/php-errori.log)', nuovi.length === 0, nuovi.slice(0, 2));
    await br.close();
    smtp.kill();
    fs.rmSync(POSTA, { recursive: true, force: true });
    s.fine(err);
  }
})();
