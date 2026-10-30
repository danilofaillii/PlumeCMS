// Password dimenticata e Nuova password con un messaggio d'errore (1.11.0): fino alla 1.10.0 le due pagine usavano h()
// senza caricarla, e ogni errore ("Troppe richieste", "Richiesta rifiutata", password diverse) dava pagina bianca (500).
// Anche un link valido di password-reimposta.php dava 500 (h($t) nel campo nascosto).
// uso: node prove/prova-password-dimenticata.js <base> <utente> <cartella del CMS>
// Usa prove/password-cli.php (token e conteggi). Non cambia la password: si ferma agli errori.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const [B, UTENTE, DIR] = process.argv.slice(2);
if (!B || !UTENTE || !DIR) { console.error('uso: node prove/prova-password-dimenticata.js <base> <utente> <cartella del CMS>'); process.exit(2); }
const cli = (...a) => execFileSync('php', [path.join(__dirname, 'password-cli.php'), DIR, ...a], { encoding: 'utf8' }).trim();
const LOG = path.join(DIR, 'logs', 'php-errori.log');
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 200) : '')); };
(async () => {
  cli('svuota');
  const logPrima = fs.existsSync(LOG) ? fs.statSync(LOG).size : 0;
  const br = await chromium.launch(); const p = await (await br.newContext()).newPage();
  let stato = 0; p.on('response', r => { if (r.request().isNavigationRequest()) stato = r.status(); });
  const errore = () => p.locator('.login-error').innerText({ timeout: 2000 }).catch(() => '');
  // Un altro sito con un modulo che manda il POST al pannello (CSRF): il browser mette Origin: http://cattivo.example
  const daFuori = async (pagina, campi) => {
    const html = `<form method="post" action="${B}/admin/${pagina}">` + Object.entries(campi).map(([k, v]) => `<input name="${k}" value="${v}">`).join('') + '<button>Invia</button></form>';
    await p.route('http://cattivo.example/**', r => r.fulfill({ contentType: 'text/html', body: html }));
    await p.goto('http://cattivo.example/'); await p.click('button'); await p.waitForLoadState();
    await p.unroute('http://cattivo.example/**');
  };

  // 1. Password dimenticata
  await p.goto(B + '/admin/password-dimenticata.php');
  ok('Password dimenticata si apre (200, con il campo email)', stato === 200 && await p.locator('#email').count() === 1, stato);

  await daFuori('password-dimenticata.php', { email: 'nessuno@esempio.it' });
  let e = await errore();
  ok('POST da un altro sito: 200 e "Richiesta rifiutata" a video', stato === 200 && /Richiesta rifiutata/.test(e), { stato, e });

  // tre richieste per la stessa email vanno, la quarta no (sicurezza_reset_attesa: 3 per email in un'ora)
  for (let i = 1; i <= 3; i++) {
    await p.goto(B + '/admin/password-dimenticata.php');
    await p.fill('#email', 'nessuno@esempio.it'); await p.click('button[type=submit]'); await p.waitForLoadState();
  }
  const fatto = await p.locator('.login-box').innerText();
  ok('la terza richiesta risponde come sempre ("fra poco arriva un\'email")', stato === 200 && /fra poco arriva/.test(fatto), stato);
  await p.goto(B + '/admin/password-dimenticata.php');
  await p.fill('#email', 'nessuno@esempio.it'); await p.click('button[type=submit]'); await p.waitForLoadState();
  e = await errore();
  ok('quarta richiesta: 200 e "Troppe richieste: riprova fra N minuti" a video', stato === 200 && /Troppe richieste: riprova fra \d+ minuti/.test(e), { stato, e });
  ok('il campo email resta lì per riprovare', await p.locator('#email').count() === 1);

  // 2. Nuova password (link dell'email)
  await p.goto(B + '/admin/password-reimposta.php?t=' + 'a'.repeat(64));
  ok('link non valido: 200 e "non è valido o è scaduto"', stato === 200 && /non è valido o è scaduto/.test(await p.locator('.login-box').innerText()), stato);

  const t = cli('token', UTENTE);
  await p.goto(B + '/admin/password-reimposta.php?t=' + t);
  ok('link valido: 200 e il modulo della password nuova', stato === 200 && await p.locator('#password2').count() === 1, stato);
  ok('il token sta nel campo nascosto, uguale', await p.locator('input[name=t]').inputValue().catch(() => '') === t);

  if (await p.locator('#password2').count()) { // con il codice vecchio la pagina è bianca: niente modulo da compilare
    await p.fill('#password', 'Una-Password-Lunga-2026'); await p.fill('#password2', 'Una-Password-Diversa-2026');
    await p.click('button[type=submit]'); await p.waitForLoadState();
    e = await errore();
  } else e = '';
  ok('password diverse: 200 e "Le due password sono diverse." a video', stato === 200 && /Le due password sono diverse/.test(e), { stato, e });

  // password diverse apposta: se il controllo dell'origine non ci fosse, la password non cambierebbe lo stesso
  await daFuori('password-reimposta.php', { t, password: 'Una-Password-Lunga-2026', password2: 'Una-Password-Diversa-2026' });
  e = await errore();
  ok('POST da un altro sito: 200 e "Richiesta rifiutata" a video (password non cambiata)', stato === 200 && /Richiesta rifiutata/.test(e), { stato, e });
  await br.close();

  // 3. Il log degli errori
  const nuovo = fs.existsSync(LOG) ? fs.readFileSync(LOG, 'utf8').slice(logPrima) : '';
  ok('logs/php-errori.log: nessun "undefined function h()" durante la prova', !/undefined function h\(/.test(nuovo), nuovo.split('\n').filter(Boolean).slice(-2));
  cli('svuota');
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
