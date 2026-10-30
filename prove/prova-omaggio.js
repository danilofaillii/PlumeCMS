// Il file dato da un form dopo la conferma dell'email (1.11.0): Download > file Riservato, Form > Dopo l'invio >
// "Dopo la conferma, dai questo file". Su un CMS installato con il kit Attività (la pagina Contatti ha il form),
// con il server di posta finto acceso: python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> &
// Percorsi: persona nuova con conferma (email di conferma, pagina con Scarica, email con il link), stessa persona di nuovo
// (link per email, non sulla pagina), form senza conferma (link sulla pagina), link manomesso, scaduto, file pubblico 404.
// Alla fine il form torna com'era e il file si elimina.
// uso: node prove/prova-omaggio.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella del CMS installato>
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const [B, UTENTE, PASSWORD, SMTP, POSTA, DIR] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 220) : '')); };
const posta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => fs.readFileSync(path.join(POSTA, f), 'utf8'));
const dorme = ms => new Promise(r => setTimeout(r, ms));
// Il corpo delle email del server finto può essere in quoted-printable: si ricompone prima di cercare i link.
// Le parti in base64 si decodificano (1.11.0: ogni email ha la parte di testo e quella HTML).
const testo = e => (e + '\n' + [...e.matchAll(/Content-Transfer-Encoding: base64\r?\n\r?\n([A-Za-z0-9+\/=\r\n]+)/g)].map(m => Buffer.from(m[1].replace(/\s/g, ''), 'base64').toString('utf8')).join('\n'))
  .replace(/=\r?\n/g, '').replace(/=3D/g, '=').replace(/&amp;/g, '&');
const linkIn = (e, re) => (testo(e).match(re) || [])[0];

(async () => {
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('dialog', d => d.accept());
  const vai = u => p.goto(u.startsWith('http') ? u : B + u, { waitUntil: 'networkidle' });
  await vai('/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await vai('/admin/impostazioni.php');
  await p.fill('#smtp_host', '127.0.0.1'); await p.fill('#smtp_porta', SMTP); await p.selectOption('#smtp_sicurezza', 'nessuna');
  await p.fill('[name=smtp_mittente]', 'sito@esempio.it').catch(() => {});
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=save_posta]) button[type=submit]')]);

  // 1. Un PDF riservato in Download.
  const pdf = path.join(POSTA, '..', 'guida-omaggio.pdf');
  fs.writeFileSync(pdf, '%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n');
  await vai('/admin/scaricabili.php');
  await p.setInputFiles('#dl-carica input[type=file]', pdf);
  await p.fill('#dl-carica input[name=titolo]', 'La guida omaggio');
  await p.check('#dl-riservato');
  await Promise.all([p.waitForNavigation(), p.click('#dl-carica button[type=submit]')]);
  const voce = await p.locator('body').innerText();
  ok('Download: il file è caricato come riservato', /guida-omaggio\.pdf/.test(voce) && /Riservato/.test(voce) && /caricato|Caricato/.test(await p.locator('.flash').first().innerText().catch(() => '')));
  ok('dall\'indirizzo pubblico il file riservato non si scarica (404)', (await p.request.get(B + '/scarica/guida-omaggio/')).status() === 404);

  // 2. Il form dei Contatti: lista, conferma, file.
  await vai('/contatti/');
  const id = +((await p.locator('input[name=modulo]').first().getAttribute('value')) || '').replace('form:', '');
  const scheda = () => vai('/admin/form.php?id=' + id);
  await scheda();
  const prima = { lista: await p.inputValue('select[name=lista_id]'), conferma: await p.isChecked('input[name=lista_conferma]'), omaggio: await p.inputValue('#fm-omaggio') };
  const salva = async (lista, conferma, omaggio) => {
    await scheda();
    await p.selectOption('select[name=lista_id]', lista);
    if (conferma) await p.check('input[name=lista_conferma]'); else await p.uncheck('input[name=lista_conferma]');
    await p.selectOption('#fm-omaggio', omaggio);
    await Promise.all([p.waitForNavigation(), p.click('#fm-form button[type=submit]:not([name=azione])')]);
    return (await p.locator('.flash').first().innerText().catch(() => '')).trim();
  };
  ok('la tendina offre il file riservato', (await p.locator('#fm-omaggio option').allInnerTexts()).some(t => /La guida omaggio/.test(t)));
  const e0 = await salva('0', true, 'guida-omaggio');
  ok('senza una lista il file non si può dare (e si dice perché)', /serve una lista/.test(e0), e0);
  const lista = await p.locator('select[name=lista_id] option').nth(1).getAttribute('value');
  const s1 = await salva(lista, true, 'guida-omaggio');
  ok('form salvato con lista, conferma e file', /salvato/.test(s1), s1);

  const compila = async (chi) => {
    const q = await br.newContext(); const v = await q.newPage(); v.on('pageerror', e => err.push(e.message.slice(0, 200)));
    await v.goto(B + '/contatti/', { waitUntil: 'networkidle' });
    const f = v.locator('form[data-captcha]').first();
    for (const c of await f.locator('input:not([type=hidden]):not([type=checkbox]), textarea').all()) {
      const nome = await c.getAttribute('name'); const tipo = await c.getAttribute('type');
      if (nome === 'sito_web') continue;
      await c.fill(tipo === 'email' ? chi + '@persona.it' : (tipo === 'tel' ? '+39 333 1234567' : 'Prova ' + chi));
    }
    await f.locator('input[name=privacy]').check();
    await f.locator('.captcha input[type=checkbox]').check();
    await v.waitForSelector('form.verificato', { timeout: 30000 });
    const quante = posta().length;
    await Promise.all([v.waitForNavigation(), f.locator('button[type=submit]').click()]);
    for (let i = 0; i < 20 && posta().length === quante; i++) await dorme(250);
    await dorme(600);
    const pag = await v.locator('main, body').first().innerText();
    return { v, q, pag, mail: posta().slice(quante) };
  };

  // 3. Persona nuova: conferma, poi il file.
  const a = await compila('omaggio1');
  ok('grazie: "Ultimo passo", conferma la tua email per ricevere il file', /Ultimo passo/.test(a.pag) && /La guida omaggio/.test(a.pag), a.pag.slice(-300));
  ok('nessun link al file sulla pagina prima della conferma', await a.v.locator('a[href*="/scarica/"]').count() === 0);
  const conf = a.mail.find(x => /Conferma/.test(x) && /omaggio1@persona\.it/.test(x));
  ok('email di conferma: il titolo con l\'apostrofo giusto, anche nella parte di testo', conf && /Conferma l'iscrizione/.test(testo(conf)) && !/&amp;#0?39;|&amp;apos;/.test(conf + [...conf.matchAll(/base64\r?\n\r?\n([A-Za-z0-9+\/=\r\n]+)/g)].map(m => Buffer.from(m[1].replace(/\s/g, ''), 'base64').toString('utf8')).join('')));
  const linkConf = conf && linkIn(conf, /https?:\/\/[^\s"<]+\?t=[a-f0-9]{64}/);
  ok('arriva l\'email di conferma (e non ancora quella del file)', !!linkConf && !a.mail.some(x => /Ecco il tuo file/.test(x)), a.mail.length);
  const quante = posta().length;
  await a.v.goto(linkConf, { waitUntil: 'networkidle' });
  const pc = await a.v.locator('body').innerText();
  ok('conferma: "Ecco il tuo file" con il pulsante Scarica, non la pagina della newsletter', /Ecco il tuo file/.test(pc) && !/ricevi la/.test(pc) && await a.v.locator('a.btn', { hasText: 'Scarica' }).count() === 1, pc.slice(0, 300));
  const href = await a.v.locator('a.btn', { hasText: 'Scarica' }).getAttribute('href');
  const r1 = await a.v.request.get(href);
  ok('il pulsante scarica il PDF', r1.status() === 200 && /pdf/.test(r1.headers()['content-type']) && (await r1.body()).toString().startsWith('%PDF'), r1.status());
  ok('il file personale non resta nelle cache e non dà il referrer', /no-store/.test(r1.headers()['cache-control'] || '') && r1.headers()['referrer-policy'] === 'no-referrer');
  for (let i = 0; i < 20 && posta().length === quante; i++) await dorme(250);
  const mf = posta().slice(quante).find(x => /Ecco il tuo file/.test(testo(x)));
  const linkMail = mf && linkIn(mf, /https?:\/\/[^\s"<]+\/scarica\/guida-omaggio\/\?k=[0-9.a-f]+/);
  ok('arriva l\'email "Ecco il tuo file" con il link personale', !!linkMail && /omaggio1@persona\.it/.test(mf), !!mf);
  ok('il link dell\'email funziona', (await a.v.request.get(linkMail)).status() === 200);
  const di = await a.v.goto(linkConf, { waitUntil: 'networkidle' });
  ok('il link di conferma usato una seconda volta non ridà il file', /scaduto/i.test(await a.v.locator('body').innerText()) && await a.v.locator('a', { hasText: 'Scarica' }).count() === 0);
  const k = linkMail.split('?k=')[1];
  const [iid, sc, fi] = k.split('.');
  ok('link manomesso (altro iscritto): 404', (await p.request.get(B + '/scarica/guida-omaggio/?k=' + (+iid + 1) + '.' + sc + '.' + fi)).status() === 404);
  ok('link manomesso (scadenza allungata): 404', (await p.request.get(B + '/scarica/guida-omaggio/?k=' + iid + '.' + (+sc + 86400) + '.' + fi)).status() === 404);
  ok('chiave di un altro file: 404', (await p.request.get(B + '/scarica/altro-file/?k=' + k)).status() === 404);
  const scaduta = execFileSync('php', [path.join(__dirname, 'omaggio-cli.php'), DIR, 'guida-omaggio', iid], { encoding: 'utf8' }).trim();
  const r410 = await p.request.get(B + '/scarica/guida-omaggio/?k=' + scaduta);
  ok('link scaduto: 410 con la pagina che spiega cosa fare', r410.status() === 410 && /scaduto/.test(await r410.text()));
  await a.q.close();

  // 4. La stessa persona compila di nuovo: già nella lista, il link arriva per email, non sulla pagina.
  const b = await compila('omaggio1');
  ok('già iscritto: la pagina dice che il link è arrivato per email, senza link', /già nella lista/.test(b.pag) && await b.v.locator('a[href*="/scarica/"]').count() === 0, b.pag.slice(-200));
  ok('già iscritto: arriva subito l\'email con il file, senza conferma', b.mail.some(x => /Ecco il tuo file/.test(testo(x))) && !b.mail.some(x => /\?t=[a-f0-9]{64}/.test(testo(x))));
  await b.q.close();

  // 5. Form senza conferma: il file sulla pagina e per email.
  await salva(lista, false, 'guida-omaggio');
  const c = await compila('omaggio2');
  const hc = await c.v.locator('a.btn', { hasText: 'Scarica' }).getAttribute('href').catch(() => '');
  ok('senza conferma: la pagina di ringraziamento ha il pulsante Scarica che funziona', !!hc && (await c.v.request.get(hc)).status() === 200, c.pag.slice(-200));
  ok('senza conferma: arriva anche l\'email con il file', c.mail.some(x => /Ecco il tuo file/.test(testo(x)) && /omaggio2@persona\.it/.test(x)));
  const fals = await c.v.goto(B + '/grazie/?f=' + id + '&o=link&k=1.9999999999.' + 'a'.repeat(32), { waitUntil: 'networkidle' });
  ok('ringraziamento con una chiave falsa: nessun pulsante Scarica', await c.v.locator('a', { hasText: 'Scarica' }).count() === 0);
  await c.q.close();

  // 6. Il pannello dice chi l'ha ricevuto; il widget Download non lo mostra.
  await vai('/admin/scaricabili.php');
  ok('Download: "Chi l\'ha ricevuto (2)"', /Chi l'ha ricevuto \(2\)/.test(await p.locator('body').innerText()));
  const home = await (await p.request.get(B + '/')).text();
  ok('nessuna pagina pubblica mostra il file riservato', !/guida-omaggio/.test(home) && !/guida-omaggio/.test(await (await p.request.get(B + '/contatti/')).text()));

  // Com'era.
  await scheda();
  await p.selectOption('select[name=lista_id]', prima.lista); if (prima.conferma) await p.check('input[name=lista_conferma]'); else await p.uncheck('input[name=lista_conferma]');
  await p.selectOption('#fm-omaggio', prima.omaggio);
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[type=submit]:not([name=azione])')]);
  await vai('/admin/scaricabili.php');
  await Promise.all([p.waitForNavigation(), p.locator('form:has(input[name=nome][value="guida-omaggio"]) button.btn-danger').click()]);
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
