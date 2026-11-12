// I Primi passi della dashboard (1.4.0), come un utente. Si lancia dopo prove/prova-tipo-sito.js sullo stesso sito
// (Definitivo premuto, un articolo scritto), con il server di posta finto acceso:
//   python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> &
// 1. la lista c'e, "Tutto fatto: nascondi" no, e il server rifiuta di nasconderla prima del tempo;
// 2. email di prova senza server di posta: messaggio rosso chiaro, voce da fare;
// 3. il server SMTP scritto in Impostazioni (quello finto), email di prova di nuovo: partita, arrivata davvero
//    all'indirizzo dell'amministratore, voce spuntata;
// 4. logo e un colore dallo Stile del sito: due voci spuntate;
// 5. "Esegui adesso" non spunta il cron (non e il cron vero); i due file lanciati da riga di comando, come il cron
//    dell'hosting, si;
// 6. tutto fatto: il pulsante compare, la lista sparisce per sempre.
// uso: node prova-primi-passi.js <base> <cartella CMS> <utente> <password> <porta smtp> <cartella posta> <cartella schermate>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, SMTP, POSTA, OUT] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); };
const fatto = async (p, k) => (await p.locator(`#primi-passi li[data-passo=${k}]`).getAttribute('class')).includes('fatto');
const flash = async p => (await p.locator('.flash').allInnerTexts()).join(' | ');

(async () => {
  const br = await chromium.launch(); const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });

  // 1.
  ok('Primi passi nella dashboard', await p.locator('#primi-passi').count() === 1);
  ok('voci gia fatte dalla prova del tipo di sito: contenuto e definitivo', await fatto(p, 'contenuto') && await fatto(p, 'definitivo'));
  ok('"Tutto fatto: nascondi" non c\'e finche manca qualcosa', await p.locator('#primi-passi button:has-text("nascondi")').count() === 0);
  const token = await p.locator('input[name=_csrf]').first().inputValue();
  await p.request.post(B + '/admin/tipo-sito.php', { form: { _csrf: token, azione: 'nascondi_passi' }, maxRedirects: 0 });
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('il server non la nasconde prima del tempo', await p.locator('#primi-passi').count() === 1 && (await flash(p)).includes('quando sono tutti fatti'), await flash(p));

  // 2.
  await Promise.all([p.waitForNavigation(), p.click('#passi-email')]);
  await p.waitForLoadState('networkidle');
  let f = await flash(p);
  ok('email senza server di posta: messaggio chiaro', f.includes('non è partita') && f.includes('Configurazione email'), f);
  ok('voce email ancora da fare, con il motivo', !(await fatto(p, 'email')) && (await p.locator('#primi-passi li[data-passo=email] small').innerText()).includes('non è partita'));

  // 3.
  await p.goto(B + '/admin/impostazioni.php', { waitUntil: 'networkidle' });
  await p.fill('#smtp_host', '127.0.0.1'); await p.fill('#smtp_porta', SMTP); await p.selectOption('#smtp_sicurezza', 'nessuna');
  await p.fill('#smtp_utente', ''); await p.fill('#smtp_mittente', 'sito@esempio.it');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=save_posta]) button[type=submit]')]);
  const prima = fs.existsSync(POSTA) ? fs.readdirSync(POSTA).length : 0;
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.click('#passi-email')]);
  await p.waitForLoadState('networkidle');
  f = await flash(p);
  ok('email di prova partita: messaggio verde con l\'indirizzo', f.includes('partita verso prova@esempio.it'), f);
  const arrivate = fs.readdirSync(POSTA).filter(x => x.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b));
  const ultima = arrivate.length ? fs.readFileSync(path.join(POSTA, arrivate[arrivate.length - 1]), 'utf8') : '';
  ok('l\'email e arrivata davvero al server di posta, per l\'amministratore', arrivate.length === prima + 1 && ultima.includes('X-Destinatari: prova@esempio.it'), arrivate.length);
  ok('voce email spuntata', await fatto(p, 'email'));

  // 4.
  ok('logo e colori ancora da fare', !(await fatto(p, 'identita')) && !(await fatto(p, 'colori')));
  await p.goto(B + '/admin/design.php', { waitUntil: 'networkidle' });
  const logo = new URL(B).pathname.replace(/\/$/, '') + '/sito/assets/favicon.svg';
  await p.evaluate(l => {
    document.querySelector('input[name="v[sito][logo]"]').value = l;
    const c = document.querySelector('input.dg-colore-testo[name^="v[colori][sistema]"]'); c.value = '#2a6f97';
  }, logo);
  await Promise.all([p.waitForNavigation(), p.click('#dg-salva')]);
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('voce "Nome e logo" spuntata', await fatto(p, 'identita'));
  ok('voce "I tuoi colori" spuntata', await fatto(p, 'colori'));

  // 5.
  ok('cron da fare', !(await fatto(p, 'cron')));
  await p.goto(B + '/admin/cron-log.php', { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.click('#cron-esegui')]);
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('"Esegui adesso" non conta come cron attivo', !(await fatto(p, 'cron')));
  execFileSync('php', [path.join(DIR, 'cron-daily.php')]);
  execFileSync('php', [path.join(DIR, 'cron-newsletter.php')]);
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('i due file lanciati come dal cron dell\'hosting: voce spuntata', await fatto(p, 'cron'));

  // 6.
  ok('sei su sei', (await p.locator('.db-passi-conta').innerText()).trim() === '6 su 6', await p.locator('.db-passi-conta').innerText());
  await p.screenshot({ path: `${OUT}/primi-passi-fatti.png`, fullPage: true });
  await Promise.all([p.waitForNavigation(), p.click('#primi-passi button:has-text("nascondi")')]);
  await p.waitForLoadState('networkidle');
  ok('"Tutto fatto: nascondi": la lista sparisce', await p.locator('#primi-passi').count() === 0 && (await flash(p)).includes('completati'));
  await p.reload({ waitUntil: 'networkidle' });
  ok('e non torna', await p.locator('#primi-passi').count() === 0);

  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
