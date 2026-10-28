// Aggiornare un sito con il pacchetto del prodotto dal pannello Aggiornamenti, come farebbe Danilo (fase 7 dei temi,
// 27 ottobre 2026): carica lo zip, Applica ora, conferma, attende la fine; poi versione, pagina Temi, temi/.htaccess.
// Il confronto delle schermate prima e dopo si fa a parte con foto-sito.js e quanto-foto.py.
// uso: node prova-aggiornamento.js <base del CMS> <cartella CMS installato> <utente> <password> <pacchetto.zip> <versione attesa>
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, ZIP, VERSIONE] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 240) : '')); };

(async () => {
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('dialog', d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok('login', !p.url().includes('login.php'), p.url());
  const prima = fs.readFileSync(path.join(DIR, 'VERSIONE'), 'utf8').trim();

  await p.goto(B + '/admin/aggiornamenti.php', { waitUntil: 'networkidle' });
  await p.setInputFiles('input[name=pacchetto]', ZIP);
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[name=pacchetto]) button[type=submit]')]);
  const caricato = await p.locator('body').innerText();
  ok('pacchetto caricato e in elenco', /Applica ora/.test(caricato), caricato.slice(0, 200));
  await Promise.all([p.waitForNavigation({ timeout: 180000 }), p.locator('button:has-text("Applica ora")').first().click()]);
  await p.waitForLoadState('networkidle');
  // La pagina segue l'avanzamento e alla fine scrive il resoconto: si aspetta che il file VERSIONE cambi.
  let dopo = prima;
  for (let i = 0; i < 90 && dopo === prima; i++) { await p.waitForTimeout(1000); dopo = fs.readFileSync(path.join(DIR, 'VERSIONE'), 'utf8').trim(); }
  ok(`versione da ${prima} a ${VERSIONE}`, dopo === VERSIONE, dopo);
  await p.waitForTimeout(1500);
  const esito = await p.locator('body').innerText();
  ok('nessun errore nel resoconto dell\'applicazione', !/errore|fallit|ripristinat/i.test(esito.split('Pacchetti applicati')[0] || ''), esito.slice(0, 300));

  await p.goto(B + '/admin/informazioni.php', { waitUntil: 'networkidle' });
  ok('Informazioni mostra la versione nuova', (await p.locator('body').innerText()).includes(VERSIONE));
  await p.goto(B + '/admin/temi.php', { waitUntil: 'networkidle' });
  ok('la pagina Temi si apre', p.url().includes('temi.php') && !(await p.content()).includes('Fatal error'));
  ok('i tre temi di partenza ci sono', await p.locator('#tema-cosmo, #tema-aria, #tema-bottega').count() === 3);
  ok('il sito resta sull\'aspetto di base (nessun tema attivato dall\'aggiornamento)', await p.locator('#tema-base .badge', { hasText: 'attivo' }).count() === 1);
  ok('aprendo la pagina Temi si è scritto temi/.htaccess', fs.existsSync(path.join(DIR, 'temi', '.htaccess')));
  await p.goto(B + '/admin/editor-temi.php', { waitUntil: 'networkidle' });
  ok('il menu dice "Colori delle infografiche"', (await p.locator('nav, aside').first().innerText()).includes('Colori delle infografiche'));
  // 1.4.0: un sito che si aggiorna non vede il tipo di sito in prova ne i primi passi (sono delle installazioni nuove).
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('dashboard: niente riquadro del tipo di sito ne primi passi', await p.locator('#tipo-sito').count() === 0 && await p.locator('#primi-passi').count() === 0);
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
