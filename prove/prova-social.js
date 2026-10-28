// Social e email del mattino (1.5.0), su un plumeCMS appena installato con un kit (l'articolo d'esempio ha id 1).
// Serve il server di posta finto: python3 -I prove/smtp-finto.py <porta> <cartella posta> &
//  1. Impostazioni dei social (hashtag, hashtag per categoria, link di WhatsApp) e link pericoloso rifiutato;
//  2. riquadro Social della scheda: testo con un'emoji e un tentativo di script, testo solo per LinkedIn, foto in
//     evidenza; le due immagini nascono a 1200x630 e 1080x1350;
//  3. Testi dei contenuti: ogni canale con il suo testo, link, hashtag, emoji vera, script come testo;
//  4. Email del mattino: anteprima e "Mandala adesso", l'email arriva con i testi (script scappato) e le immagini;
//  5. Calendario: un testo per oggi, il cron ("Esegui adesso") lo manda una volta con l'ancora, poi non più;
//     il secondo giro del mattino non rimanda "Oggi sul sito"; immagine con javascript: rifiutata.
// uso: node prova-social.js <base> <utente> <password> <porta smtp> <cartella posta>
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const [B, UTENTE, PASSWORD, SMTP, POSTA] = process.argv.slice(2);
const FOTO = path.join(__dirname, '..', 'esempi', 'manuale-uso', 'agosto.jpg');
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const posta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA).filter(x => x.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)) : []);
const leggi = (f) => fs.readFileSync(path.join(POSTA, f), 'utf8');
// L'oggetto arriva codificato (=?UTF-8?B?...?=): lo si rimette in chiaro.
const oggetto = (eml) => { const m = /^Subject: (.*)$/m.exec(eml); return m ? m[1].replace(/=\?UTF-8\?B\?([^?]+)\?=/g, (_, b) => Buffer.from(b, 'base64').toString('utf8')) : ''; };
// Il corpo può essere in quoted-printable o in base64.
const corpo = (eml) => {
  const i = eml.indexOf('\r\n\r\n') > 0 ? eml.indexOf('\r\n\r\n') : eml.indexOf('\n\n');
  let c = eml.slice(i).trim();
  if (/Content-Transfer-Encoding: base64/i.test(eml)) { try { c = Buffer.from(c.replace(/\s+/g, ''), 'base64').toString('utf8'); } catch (e) { } }
  else if (/quoted-printable/i.test(eml)) c = c.replace(/=\r?\n/g, '').replace(/=([0-9A-F]{2})/g, (_, h) => String.fromCharCode(parseInt(h, 16)));
  return c;
};

(async () => {
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('dialog', d => d.accept());
  const flash = async () => (await p.locator('.flash').allInnerTexts()).join(' ');
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  const oggi = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

  // Posta verso il server finto, avvisi a prova@esempio.it.
  await p.goto(B + '/admin/impostazioni.php', { waitUntil: 'networkidle' });
  await p.fill('#smtp_host', '127.0.0.1'); await p.fill('#smtp_porta', SMTP); await p.selectOption('#smtp_sicurezza', 'nessuna');
  await p.fill('#smtp_utente', ''); await p.fill('#smtp_mittente', 'sito@esempio.it'); await p.fill('#notification_email', 'prova@esempio.it');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=save_posta]) button[type=submit]')]);

  // 1. Impostazioni dei social
  await p.goto(B + '/admin/social.php?scheda=impostazioni', { waitUntil: 'networkidle' });
  ok('la pagina Social si apre dal menu, con le quattro linguette', await p.locator('.admin-nav a[href="social.php"]').count() === 1 && await p.locator('.so-schede a').count() === 4);
  await p.fill('#so-wa-l', 'javascript:alert(1)');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=impostazioni]) button.btn-primary')]);
  ok('link di WhatsApp con javascript: rifiutato', /comincia con https/.test(await flash()), await flash());
  await p.goto(B + '/admin/social.php?scheda=impostazioni', { waitUntil: 'networkidle' });
  await p.fill('#so-h-li', '#provalinkedin');
  await p.fill('#so-h-ig', '#provainstagram #seconda');
  await p.fill('#so-h-cat', 'Avvisi: #avvisi');
  await p.fill('#so-wa-l', '/contatti/');
  await p.fill('#so-wa-e', 'Scrivici:');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=impostazioni]) button.btn-primary')]);
  ok('impostazioni salvate', /salvate/.test(await flash()), await flash());

  // 2. Il riquadro Social della scheda, sull'articolo d'esempio
  await p.goto(B + '/admin/contenuto.php?tipo=articoli&id=1', { waitUntil: 'networkidle' });
  ok('la scheda ha il riquadro Social', await p.locator('#social textarea[name=social_testo]').count() === 1);
  await p.fill('[name=data]', oggi); await p.fill('[name=ora]', '07:30');
  await p.fill('[name=categoria_nuova]', 'Avvisi');
  await p.click('button:has-text("Scegli l\'immagine")'); await p.setInputFiles('#sc-media-file', FOTO);
  await p.waitForFunction(() => !document.querySelector('#sc-immagine-anteprima').hidden);
  await p.fill('[name=social_testo]', 'Si comincia \u{1F31E} oggi. <script>alert(1)</script>');
  await p.locator('#social details summary').click();
  await p.fill('[name=social_linkedin]', 'Solo per LinkedIn: il racconto lungo.');
  await Promise.all([p.waitForNavigation(), p.click('button[data-valore=pubblica]')]);
  await p.waitForLoadState('networkidle');
  ok('scheda salvata', /aggiornato|pubblicato/.test(await flash()), await flash());
  ok('il testo torna con l\'emoji vera', (await p.inputValue('[name=social_testo]')).includes('\u{1F31E}'), await p.inputValue('[name=social_testo]'));
  const misure = await p.evaluate(async () => Promise.all([...document.querySelectorAll('.sc-social-img img')].map(i => new Promise(r => { const x = new Image(); x.onload = () => r([x.naturalWidth, x.naturalHeight]); x.onerror = () => r([0, 0]); x.src = i.src; }))));
  ok('le due immagini per i social: 1200x630 e 1080x1350', JSON.stringify(misure) === '[[1200,630],[1080,1350]]', misure);

  // 3. Testi dei contenuti
  await p.goto(B + '/admin/social.php?scheda=testi', { waitUntil: 'networkidle' });
  const carta = p.locator('.so-carta').filter({ hasText: 'Benvenuto' }).first();
  const testo = async (canale) => carta.locator('.so-canale').filter({ has: p.locator('b', { hasText: canale }) }).locator('textarea').inputValue();
  const li = await testo('LinkedIn'), fb = await testo('Facebook'), ig = await testo('Instagram'), wa = await testo('WhatsApp');
  ok('LinkedIn: il testo solo per LinkedIn, il link, gli hashtag fissi e quelli della categoria', li.startsWith('Solo per LinkedIn') && li.includes(B + '/benvenuto/') && li.includes('#provalinkedin #avvisi'), li);
  ok('Facebook: il testo di tutti, con l\'emoji vera e lo script come testo', fb.startsWith('Si comincia \u{1F31E} oggi.') && fb.includes('<script>alert(1)</script>'), fb);
  ok('Instagram: gli hashtag di Instagram', ig.includes('#provainstagram #seconda #avvisi'), ig);
  ok('WhatsApp: il link in più con la sua frase', wa.includes('Scrivici: ' + B + '/contatti/'), wa);
  ok('Substack spento di partenza', await carta.locator('.so-canale b', { hasText: 'Substack' }).count() === 0);
  ok('i pulsanti per condividere (LinkedIn, Facebook, WhatsApp, Telegram)', await carta.locator('.so-condividi a').count() === 4);
  ok('nessuno script eseguito nel pannello', err.length === 0, err);

  // 4. Email del mattino
  await p.goto(B + '/admin/social.php?scheda=mattino&giorno=' + oggi, { waitUntil: 'networkidle' });
  ok('anteprima dell\'email di oggi, con l\'articolo', /Oggi sul sito/.test(await p.locator('.so-riga').last().innerText()) && await p.locator('.so-anteprima').filter({ hasText: 'Benvenuto' }).count() === 1);
  ok('nell\'anteprima lo script resta testo', await p.locator('.so-anteprima script').count() === 0);
  const prima = posta().length;
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Mandala adesso")')]);
  ok('Mandala adesso: messaggio con l\'indirizzo', /mandata a prova@esempio\.it/.test(await flash()), await flash());
  const nuove = posta().slice(prima);
  const e1 = nuove.length ? leggi(nuove[nuove.length - 1]) : '';
  const c1 = corpo(e1);
  // Quanti articoli escono oggi dipende dal sito (il kit ne ha uno, l'installatore senza kit due): conta la grammatica.
  ok('l\'email è arrivata, oggetto "Oggi sul sito ...: 1 articolo" o "N articoli"', nuove.length === 1 && /^Oggi sul sito .+: (1 articolo|([2-9]|\d\d+) articoli)$/.test(oggetto(e1)), oggetto(e1));
  ok('dentro: testi, link, immagini da scaricare', c1.includes('Solo per LinkedIn') && c1.includes('/benvenuto/') && /-og\.jpg/.test(c1) && /-ig\.jpg/.test(c1) && c1.includes('linkedin.com/sharing'), c1.length);
  ok('dentro: lo script scappato, mai vivo', !c1.includes('<script>alert(1)') && c1.includes('&lt;script&gt;alert(1)&lt;/script&gt;'));

  // 5. Calendario
  await p.goto(B + '/admin/social.php?scheda=calendario&nuovo=1', { waitUntil: 'networkidle' });
  await p.fill('#so-data', oggi); await p.selectOption('#so-cont', 'articoli:1');
  await p.fill('#so-tit', 'Giorno 1 di 7'); await p.fill('#so-anc', 'cosa-fare-adesso');
  await p.fill('#so-li', 'LinkedIn del giorno');
  await p.fill('#so-og', 'javascript:alert(1)');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=programmato_salva]) button.btn-primary')]);
  ok('immagine con javascript: rifiutata', /cominciano con \//.test(await flash()), await flash());
  await p.fill('#so-data', oggi); await p.selectOption('#so-cont', 'articoli:1');
  await p.fill('#so-tit', 'Giorno 1 di 7'); await p.fill('#so-anc', 'cosa-fare-adesso'); await p.fill('#so-li', 'LinkedIn del giorno'); await p.fill('#so-og', '');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=programmato_salva]) button.btn-primary')]);
  ok('testo del calendario salvato, in attesa', /salvato/.test(await flash()) && await p.locator('tr', { hasText: 'Giorno 1 di 7' }).locator('.so-stato-attesa').count() === 1, await flash());
  const prima2 = posta().length;
  await p.goto(B + '/admin/cron-log.php', { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.click('#cron-esegui')]);
  await p.waitForLoadState('networkidle');
  const dopoCron = posta().slice(prima2).map(leggi);
  const sGiorno = dopoCron.find(e => oggetto(e) === 'Social di oggi: Giorno 1 di 7');
  ok('il cron manda "Oggi sul sito" e "Social di oggi" (due email)', dopoCron.length === 2 && dopoCron.some(e => /^Oggi sul sito/.test(oggetto(e))) && !!sGiorno, dopoCron.map(oggetto));
  ok('"Social di oggi": testo del giorno e link con l\'ancora', !!sGiorno && corpo(sGiorno).includes('LinkedIn del giorno') && corpo(sGiorno).includes('/benvenuto/#cosa-fare-adesso'));
  await p.goto(B + '/admin/social.php?scheda=calendario', { waitUntil: 'networkidle' });
  ok('nel calendario il testo è segnato come inviato', await p.locator('tr', { hasText: 'Giorno 1 di 7' }).locator('.so-stato-ok').count() === 1);
  const prima3 = posta().length;
  await p.goto(B + '/admin/cron-log.php', { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.click('#cron-esegui')]);
  await p.waitForLoadState('networkidle');
  ok('il secondo giro non rimanda niente', posta().length === prima3, posta().slice(prima3).map(f => oggetto(leggi(f))));
  ok('il registro del cron dice "già inviata oggi" e "niente nel calendario"', /già inviata oggi/.test(await p.locator('body').innerText()) && /niente nel calendario/.test(await p.locator('body').innerText()));
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
