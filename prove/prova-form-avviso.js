// A chi arriva l'avviso di un form (1.9.0): Form > scheda > Dopo l'invio > "Manda l'avviso a".
// Su un CMS installato con un kit (la pagina Contatti ha il form), con il server di posta finto acceso:
//   python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> &
// La prova scrive il server finto in Impostazioni, poi compila il form dei Contatti tre volte (il limite è 5 all'ora per
// indirizzo IP): avviso a due indirizzi scelti, avviso all'indirizzo del sito con il campo vuoto, nessun avviso spento.
// Controlla anche gli indirizzi sbagliati e il testo d'aiuto dell'editor. Alla fine il form torna com'era.
// uso: node prova-form-avviso.js <base> <utente> <password> <porta smtp> <cartella posta>
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const [B, UTENTE, PASSWORD, SMTP, POSTA] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 220) : '')); };
const posta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => fs.readFileSync(path.join(POSTA, f), 'utf8'));
const dorme = ms => new Promise(r => setTimeout(r, ms));

(async () => {
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('dialog', d => d.accept());
  const vai = u => p.goto(B + u, { waitUntil: 'networkidle' });
  await vai('/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  // Il server di posta finto e l'indirizzo degli avvisi del sito.
  await vai('/admin/impostazioni.php');
  await p.fill('#smtp_host', '127.0.0.1'); await p.fill('#smtp_porta', SMTP); await p.selectOption('#smtp_sicurezza', 'nessuna');
  await p.fill('[name=smtp_mittente]', 'sito@esempio.it').catch(() => {});
  await p.fill('[name=notification_email]', 'avvisi-sito@esempio.it');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=save_posta]) button[type=submit]')]);

  // Il form della pagina Contatti.
  await vai('/contatti/');
  const id = +((await p.locator('input[name=modulo]').first().getAttribute('value')) || '').replace('form:', '');
  ok('la pagina Contatti ha un form', id > 0, id);
  const scheda = async () => { await vai('/admin/form.php?id=' + id); };
  await scheda();
  const prima = { avviso: await p.isChecked('input[name=avviso]'), a: await p.inputValue('#fm-avviso-a') };
  ok('scheda del form: il campo "Manda l\'avviso a", con l\'indirizzo del sito come suggerimento', await p.locator('label[for=fm-avviso-a]').innerText() === 'Manda l\'avviso a' && await p.getAttribute('#fm-avviso-a', 'placeholder') === 'avvisi-sito@esempio.it' && /avvisi-sito@esempio\.it/.test(await p.locator('#fm-avviso-aiuto').innerText()));
  const salva = async (avviso, a) => {
    await scheda();
    if (avviso) await p.check('input[name=avviso]'); else await p.uncheck('input[name=avviso]');
    await p.fill('#fm-avviso-a', a);
    await Promise.all([p.waitForNavigation(), p.click('#fm-form button[type=submit]:not([name=azione])')]);
    return (await p.locator('.flash').first().innerText().catch(() => '')).trim();
  };
  // Indirizzi sbagliati: il form non si salva e dice quale.
  const e1 = await salva(true, 'mario@esempio.it, pippo');
  ok('un indirizzo sbagliato ferma il salvataggio e si dice quale', /«pippo» non è un indirizzo email valido/.test(e1) && await p.inputValue('#fm-avviso-a') === 'mario@esempio.it, pippo', e1);
  const e2 = await salva(true, 'a1@x.it a2@x.it a3@x.it a4@x.it a5@x.it a6@x.it');
  ok('più di cinque indirizzi: rifiutati', /al massimo a 5 indirizzi/.test(e2), e2);
  const e3 = await salva(true, 'a@x.it\r\nBcc: spia@cattivo.it');
  ok('un a capo con un\'intestazione nascosta non passa', /non è un indirizzo email valido/.test(e3), e3);
  // Due indirizzi, scritti con spazi, punto e virgola e un doppione.
  const s1 = await salva(true, ' marta@esempio.it; Luca@Esempio.it, marta@esempio.it ');
  await scheda();
  ok('due indirizzi salvati, puliti e senza doppioni', /salvato/.test(s1) && await p.inputValue('#fm-avviso-a') === 'marta@esempio.it, Luca@Esempio.it', { s1, v: await p.inputValue('#fm-avviso-a') });
  await vai('/admin/form.php');
  ok('l\'elenco dei form dice a chi va l\'avviso', (await p.locator('tr', { hasText: 'marta@esempio.it' }).count()) === 1);

  const compila = async (chi) => {
    await vai('/contatti/');
    const f = p.locator('form[data-captcha]').first();
    for (const c of await f.locator('input:not([type=hidden]):not([type=checkbox]), textarea').all()) {
      const nome = await c.getAttribute('name'); const tipo = await c.getAttribute('type');
      if (nome === 'sito_web') continue; // la trappola per i robot resta vuota
      await c.fill(tipo === 'email' ? chi + '@persona.it' : (tipo === 'tel' ? '+39 333 1234567' : 'Prova ' + chi));
    }
    await f.locator('input[name=privacy]').check();
    await f.locator('.captcha input[type=checkbox]').check();
    await p.waitForSelector('form.verificato', { timeout: 30000 });
    const quante = posta().length;
    await Promise.all([p.waitForNavigation(), f.locator('button[type=submit]').click()]);
    for (let i = 0; i < 20 && posta().length === quante; i++) await dorme(250);
    await dorme(500);
    return posta().slice(quante);
  };
  const m1 = await compila('uno');
  const a1 = m1.map(x => (x.match(/^X-Destinatari: (.*)$/m) || [])[1]).sort();
  ok('compilato: l\'avviso arriva a tutti e due, uno per uno, e non all\'indirizzo del sito', JSON.stringify(a1) === JSON.stringify(['Luca@Esempio.it', 'marta@esempio.it']), a1);
  ok('l\'avviso dice il form e chi ha scritto', m1.every(x => /Nuova compilazione/.test(x) && /uno@persona\.it/.test(x)));
  // Vuoto: l'indirizzo degli avvisi del sito, come prima della 1.9.0.
  await salva(true, '');
  const m2 = await compila('due');
  ok('campo vuoto: l\'avviso va all\'indirizzo degli avvisi del sito', m2.length === 1 && /X-Destinatari: avvisi-sito@esempio\.it/.test(m2[0]), m2.length);
  // Spento: nessun avviso, anche con gli indirizzi scritti.
  await salva(false, 'marta@esempio.it');
  const m3 = await compila('tre');
  ok('avviso spento: nessuna email, anche con gli indirizzi scritti', m3.length === 0, m3.length);

  // L'editor dice dove si sceglie.
  await vai('/admin/');
  const casa = await p.locator('.db-azioni a', { hasText: 'Home nell\'editor' }).getAttribute('href');
  const ed = await p.request.get(B + '/admin/' + casa);
  ok('il widget Modulo dell\'editor dice dove si sceglie l\'indirizzo', /Manda l.{1,6}avviso a/.test(await ed.text()));

  // Com'era.
  await salva(prima.avviso, prima.a);
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
