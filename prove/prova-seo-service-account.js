// 2.3.2: il service account di Google si salva e il riquadro SEO lo mostra (email, Prova il collegamento). Fino alla 2.3.1 il menu del
// pannello (admin/includes/admin-header.php) sovrascriveva $sa con una sua variabile: la chiave era salvata ma il riquadro la ignorava.
// Una chiave finta con una chiave RSA vera (openssl), nessuna chiamata a Google.
// uso: node prove/prova-seo-service-account.js <base> <utente> <password>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const [B, U, P] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, x = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (x !== '' ? ' ' + JSON.stringify(x).slice(0, 300) : '')); };
(async () => {
  const chiave = execFileSync('openssl', ['genpkey', '-algorithm', 'RSA', '-pkeyopt', 'rsa_keygen_bits:2048'], { encoding: 'utf8' });
  const sa = JSON.stringify({ type: 'service_account', project_id: 'prova-seo', private_key_id: 'abc', private_key: chiave, client_email: 'prova-seo@prova-seo.iam.gserviceaccount.com', client_id: '1', token_uri: 'https://oauth2.googleapis.com/token' }, null, 2);
  const br = await chromium.launch(); const p = await br.newPage({ viewport: { width: 1280, height: 900 } }); const err = []; p.on('pageerror', e => err.push(e.message));
  p.on('dialog', d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', U); await p.fill('#password', P);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/seo.php?scheda=motori', { waitUntil: 'networkidle' });
  const card = () => p.locator('.seo-card', { has: p.locator('h3', { hasText: 'service account' }) });
  // Se c'è già un service account (giro ripetuto), si toglie.
  if (await card().locator('button', { hasText: 'Rimuovi' }).count()) await Promise.all([p.waitForNavigation(), card().locator('button', { hasText: 'Rimuovi' }).click()]);
  ok('prima: niente service account nel riquadro', !(await card().innerText()).includes('Service account:'));
  ok('il link per creare il progetto Google porta a projectcreate', await card().locator('a[href="https://console.cloud.google.com/projectcreate"]').count() === 1);
  await card().locator('textarea[name=sa_json]').fill(sa);
  await Promise.all([p.waitForNavigation(), card().locator('button', { hasText: 'Salva la chiave' }).click()]);
  const t = await card().innerText();
  ok('dopo il salvataggio il riquadro mostra l\'email (non solo il messaggio verde)', t.includes('Service account: prova-seo@prova-seo.iam.gserviceaccount.com') && t.includes('salvata (mascherata)'), t.slice(0, 300));
  ok('e il pulsante Prova il collegamento', await card().locator('button', { hasText: 'Prova il collegamento' }).count() === 1);
  // UX 2026-11: con la chiave salvata passi e casella stanno nel riquadro chiuso "Rifai il collegamento o sostituisci la chiave".
  ok('la casella ora si chiama "Sostituisci la chiave JSON" (nel riquadro che si apre)', /sostituisci la chiave/i.test(t) && await card().locator('label', { hasText: 'Sostituisci la chiave JSON' }).count() === 1);
  await p.goto(B + '/admin/seo.php?scheda=copertura', { waitUntil: 'networkidle' });
  ok('la scheda Copertura non dice più "Serve il service account"', !/Serve il service account Google/.test(await p.locator('main').innerText()));
  await p.goto(B + '/admin/seo.php?scheda=motori', { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), card().locator('button', { hasText: 'Rimuovi' }).click()]);
  ok('Rimuovi lo toglie', !(await card().innerText()).includes('Service account:'));
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`); process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
