// Le schermate dei cinque passi dell'installatore (notte del 27 ottobre 2026), per la pagina Installazione del sito di
// plumeCMS. Si fanno su un plumeCMS appena estratto e mai installato, con un database vuoto: lo script installa davvero,
// passo per passo, come farebbe chi lo scarica, e fotografa ogni passo prima di andare avanti.
// uso: DBPASS=<password> node sito-plumecms/installatore.js <base> <cartella> <host:porta> <database> <utente del database>
// scrive <cartella>/installa-1.png ... installa-5.png; poi python3 sito-plumecms/costruisci.py --schermate <cartella>.
//
// Nelle foto i dati di chi prova (server, database, utente, indirizzo, cartella sul disco) diventano quelli di un sito
// d'esempio, "tuosito.it": nei campi si scrivono quelli d'esempio per la foto e poi i veri per andare avanti; nei testi
// del riepilogo e della fine si sostituiscono nella pagina, solo per la foto. La password non si vede mai (pallini).
const { chromium } = require('playwright');
const path = require('path');
const [B, D, HOST, DB, UTENTE] = process.argv.slice(2);
const DBPASS = process.env.DBPASS || '';
const ESEMPIO = { host: 'localhost', db: 'tuosito_cms', utente: 'tuosito_cms', url: 'https://tuosito.it', cartella: '/home/tuosito/public_html' };
const ADMIN = { nome: 'giulia', email: 'giulia@tuosito.it', password: 'Prova-Installatore-2026' };   // solo per questa installazione di prova

(async () => {
  if (!B || !D || !DB || !UTENTE) { console.log('uso: DBPASS=... node sito-plumecms/installatore.js <base> <cartella> <host:porta> <database> <utente>'); process.exit(1); }
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 880, height: 660 }, deviceScaleFactor: 2 })).newPage();
  const foto = async (n) => { await p.evaluate(() => { if (document.activeElement) document.activeElement.blur(); scrollTo(0, 0); }); await p.waitForTimeout(300); await p.screenshot({ path: path.join(D, `installa-${n}.png`) }); console.log('installa-' + n); };
  // Sostituisce nei testi della pagina i dati veri con quelli d'esempio (solo quello che si vede, non i campi).
  const esempio = (coppie) => p.evaluate((coppie) => {
    const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    for (let t; (t = w.nextNode());) for (const [da, a] of coppie) t.nodeValue = t.nodeValue.split(da).join(a);
  }, coppie);
  const veri = [[B, ESEMPIO.url], [HOST, ESEMPIO.host], [DB, ESEMPIO.db], [UTENTE, ESEMPIO.utente]];

  // 1. Requisiti
  await p.goto(B + '/', { waitUntil: 'networkidle' });
  if (!p.url().includes('/install/')) throw new Error('il CMS risulta gia installato: serve una cartella appena estratta');
  await foto(1);
  await Promise.all([p.waitForNavigation(), p.click('a.btn:has-text("Avanti")')]);

  // 2. Database: i valori d'esempio per la foto, poi i veri.
  await p.fill('#host', ESEMPIO.host); await p.fill('#nome', ESEMPIO.db); await p.fill('#utente', ESEMPIO.utente); await p.fill('#password', 'una-password-qualsiasi');
  await foto(2);
  await p.fill('#host', HOST); await p.fill('#nome', DB); await p.fill('#utente', UTENTE); await p.fill('#password', DBPASS);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  if (!p.url().includes('passo=3')) throw new Error('il database non e stato accettato: ' + (await p.locator('.errore').innerText().catch(() => '')));

  // 3. Sito e amministratore
  const url = await p.inputValue('#url');
  await p.fill('#nome', 'Il mio sito'); await p.fill('#descrizione', 'Ricette di casa, raccontate bene.'); await p.fill('#email', 'ciao@tuosito.it');
  await p.fill('#admin_nome', ADMIN.nome); await p.fill('#admin_email', ADMIN.email);
  await p.fill('#admin_password', ADMIN.password); await p.fill('#admin_password2', ADMIN.password);
  await p.fill('#url', ESEMPIO.url);
  await foto(3);
  await p.fill('#url', url);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  if (!p.url().includes('passo=4')) throw new Error('passo 3 rifiutato: ' + (await p.locator('.errore').innerText().catch(() => '')));

  // 4. Installazione: il riepilogo, prima del pulsante.
  await esempio(veri);
  await foto(4);
  await Promise.all([p.waitForNavigation({ timeout: 120000 }), p.click('button:has-text("Installa adesso")')]);
  if (!p.url().includes('passo=5')) throw new Error('installazione non completata: ' + p.url());

  // 5. Fine: la cartella vera del CMS diventa quella d'esempio.
  const radice = await p.evaluate(() => (document.body.innerText.match(/php (\S+)\/cron-daily\.php/) || [])[1] || '');
  await esempio([[radice, ESEMPIO.cartella], ...veri]);
  await foto(5);
  await br.close();
})();
