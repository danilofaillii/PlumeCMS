// L'email di conferma dell'iscrizione modificabile in ogni parte (2.7.0): Form > scheda > Email di conferma e
// Newsletter > Impostazioni > Email di conferma dell'iscrizione. Su un CMS installato con il kit Attività (la pagina Contatti
// ha il form), con il server di posta finto acceso:
//   python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> &
// Controlla: senza modifiche l'email è uguale al byte a quella della 2.6.0 (codice copiato in prove/email-conferma-cli.php),
// per un form e per il modulo della newsletter; con le modifiche cambia ogni parte, i segnaposti si sostituiscono, le emoji
// passano (oggetto e testo), script, eventi e javascript: escono puliti, l'oggetto non porta a capo né intestazioni,
// anteprima e prova funzionano (la prova non conferma niente), il link vero conferma ancora, il redattore non vede e non salva.
// Alla fine form e impostazioni tornano come erano.
// uso: node prove/prova-email-conferma.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella del CMS installato>
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const [B, UTENTE, PASSWORD, SMTP, POSTA, DIR] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const dorme = ms => new Promise(r => setTimeout(r, ms));
const CLI = path.join(__dirname, 'email-conferma-cli.php');
const cli = (...a) => { try { return JSON.parse(execFileSync('php', [CLI, DIR, ...a.map(String)], { stdio: ['ignore', 'pipe', 'pipe'] }).toString().trim().split('\n').pop()); } catch (e) { return { errore: String(e.stderr || e.message).slice(0, 300) }; } };
const q = s => cli('sql', s);
const uno = s => { const r = q(s); return Array.isArray(r) && r[0] ? Object.values(r[0])[0] : null; };

// La posta del server finto: intestazioni, oggetto decodificato, parti in base64 decodificate.
function leggi(eml) {
  const i = eml.search(/\r?\n\r?\n/);
  const testa = eml.slice(0, i).replace(/\r?\n[ \t]+/g, ' '); const corpo = eml.slice(i).replace(/^\r?\n\r?\n/, '');
  const h = (t, nome) => ((t.match(new RegExp('^' + nome + ':[ \\t]*(.*)$', 'mi')) || [])[1] || '').trim();
  const dec = s => s.replace(/=\?UTF-8\?B\?([^?]+)\?=\s*/gi, (_, b) => Buffer.from(b, 'base64').toString('utf8'));
  const parte = (t, c) => /base64/i.test(h(t, 'Content-Transfer-Encoding')) ? Buffer.from(c.replace(/\s+/g, ''), 'base64').toString('utf8') : c;
  const ct = h(testa, 'Content-Type');
  let html = '', testo = '';
  const b = (ct.match(/boundary="([^"]+)"/) || [])[1];
  if (b) {
    for (const p of corpo.split('--' + b).slice(1)) {
      const j = p.search(/\r?\n\r?\n/); if (j < 0) continue;
      const pt = p.slice(0, j).trim(); const pc = p.slice(j).trim();
      if (/text\/plain/i.test(h(pt, 'Content-Type'))) testo = parte(pt, pc); else if (/text\/html/i.test(h(pt, 'Content-Type'))) html = parte(pt, pc);
    }
  } else html = parte(testa, corpo);
  return { dest: h(testa, 'X-Destinatari'), oggetto: dec(h(testa, 'Subject')), testa, html, testo };
}
const posta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => leggi(fs.readFileSync(path.join(POSTA, f), 'utf8')));
const aspetta = async (prima, chi) => { for (let i = 0; i < 40; i++) { const m = posta().slice(prima).find(x => x.dest.includes(chi)); if (m) return m; await dorme(250); } return null; };
const linkDi = m => ((m && m.html || '').match(/https?:\/\/[^"<\s]+\/newsletter-conferma\/\?t=[a-z0-9-]+/) || [])[0] || '';

(async () => {
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  pnConferme.on(p, d => d.accept());
  const vai = u => p.goto(u.startsWith('http') ? u : B + u, { waitUntil: 'networkidle' });
  const flash = async () => (await p.locator('.flash').first().innerText().catch(() => '')).trim();
  await vai('/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  // Preparazione: posta finta, lista Ebook, form dei Contatti con doppia conferma nella lista, nessun testo cambiato.
  await vai('/admin/impostazioni.php');
  await p.fill('#smtp_host', '127.0.0.1'); await p.fill('#smtp_porta', SMTP); await p.selectOption('#smtp_sicurezza', 'nessuna');
  await p.fill('[name=smtp_mittente]', 'sito@esempio.it').catch(() => {});
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=save_posta]) button[type=submit]')]);
  if (!uno("SELECT id FROM {p}newsletter_liste WHERE nome = 'Ebook'")) {
    await vai('/admin/newsletter.php?scheda=liste');
    await p.fill('form:has(input[value=lista_crea]) input[name=nome]', 'Ebook');
    await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=lista_crea]) button[type=submit]')]);
  }
  const ebook = +uno("SELECT id FROM {p}newsletter_liste WHERE nome = 'Ebook'");
  await vai('/contatti/');
  const form = +((await p.locator('input[name=modulo]').first().getAttribute('value')) || '').replace('form:', '');
  const prima = (q(`SELECT lista_id, lista_conferma, conferma, titolo, conferma_email FROM {p}moduli WHERE id = ${form}`) || [])[0] || {};
  q(`UPDATE {p}moduli SET lista_id = ${ebook}, lista_conferma = 1, conferma = 'doppia', titolo = 'L''ebook gratis', conferma_email = NULL WHERE id = ${form}`);
  q("DELETE FROM {p}settings WHERE setting_key = 'newsletter_conferma_email'");
  // Le persone di un giro precedente: chi è già attivo non riceve più l'email di conferma.
  q("DELETE l FROM {p}newsletter_iscritti_liste l JOIN {p}newsletter_iscritti i ON i.id = l.iscritto_id WHERE i.email LIKE '%@persona.it'");
  q("DELETE FROM {p}newsletter_iscritti WHERE email LIKE '%@persona.it'");
  ok('preparazione: lista Ebook, form con la doppia conferma, colonna conferma_email nella tabella dei form', form > 0 && ebook > 0 && Array.isArray(q("SHOW COLUMNS FROM {p}moduli LIKE 'conferma_email'")) && q("SHOW COLUMNS FROM {p}moduli LIKE 'conferma_email'").length === 1, { form, ebook });

  const compila = async chi => {
    q("UPDATE {p}moduli_invii SET ip = ''"); q("UPDATE {p}newsletter_iscritti SET ip = ''");
    await vai('/contatti/');
    const f = p.locator('form[data-captcha]').first();
    for (const c of await f.locator('input:not([type=hidden]):not([type=checkbox]), textarea').all()) {
      const nome = await c.getAttribute('name'); const tipo = await c.getAttribute('type');
      if (nome === 'sito_web') continue;
      await c.fill(tipo === 'email' ? chi + '@persona.it' : (tipo === 'tel' ? '+39 333 1234567' : 'Prova ' + chi));
    }
    await f.locator('input[name=privacy]').check();
    await f.locator('.captcha input[type=checkbox]').check();
    await p.waitForSelector('form.verificato', { timeout: 30000 });
    const quante = posta().length;
    await Promise.all([p.waitForNavigation(), f.locator('button[type=submit]').click()]);
    return aspetta(quante, chi + '@persona.it');
  };

  // ===== 1. Senza modifiche: uguale al byte alla 2.6.0.
  const mA = await compila('anna');
  const lA = linkDi(mA);
  const vA = cli('vecchia', 0, "L'ebook gratis", 'Prova anna', lA);
  ok('1. form, senza modifiche: l\'email è uguale al byte a quella della 2.6.0 (HTML)', !!mA && mA.html === vA.html, mA ? [mA.html.length, (vA.html || '').length] : 'nessuna email');
  ok('1. form, senza modifiche: oggetto uguale', !!mA && mA.oggetto === vA.oggetto, [mA && mA.oggetto, vA.oggetto]);
  let quante = posta().length;
  ok('1. modulo della newsletter: la richiesta parte', cli('richiesta', 'bruno@persona.it', 'Bruno') === '');
  const mB = await aspetta(quante, 'bruno@persona.it');
  const vB = cli('vecchia', 1, 'newsletter', 'Bruno', linkDi(mB));
  ok('1. modulo della newsletter, senza modifiche: email e oggetto uguali al byte alla 2.6.0', !!mB && mB.html === vB.html && mB.oggetto === vB.oggetto, mB ? [mB.oggetto, vB.oggetto] : 'nessuna email');

  // ===== 2. La scheda del form: il riquadro, l'anteprima, i segnaposti spiegati.
  await vai('/admin/form.php?id=' + form + '&passo=4');
  await p.waitForFunction(() => (document.querySelector('#fm-conferma .ec-cornice') || {}).srcdoc, null, { timeout: 10000 }).catch(() => {});
  const fr = p.frameLocator('#fm-conferma .ec-cornice');
  ok('2. scheda del form: riquadro Email di conferma con l\'anteprima dell\'email di oggi', /Conferma l'iscrizione/.test(await fr.locator('h1').innerText().catch(() => '')) && /«L'ebook gratis»/.test(await fr.locator('body').innerText().catch(() => '')));
  ok('2. i segnaposti sono spiegati accanto ai campi', /\{nome\}[\s\S]*\{lista\}[\s\S]*\{giorni\}[\s\S]*\{sito\}/.test(await p.locator('#fm-conferma .ec-segnaposti').textContent()));
  ok('2. un campo vuoto dice cosa si usa, e non ha un segnaposto che sembra un valore', /Vuoto: si usa il testo di partenza/.test(await p.locator('#ec-oggetto-aiuto').textContent()) && !(await p.getAttribute('#ec-oggetto', 'placeholder')));
  ok('2. ogni campo ha la sua etichetta', await p.locator('#fm-conferma label[for^=ec-]').count() === 9);

  // ===== 3. Valori comuni in Newsletter > Impostazioni, con un'emoji e un segnaposto.
  await vai('/admin/newsletter.php?scheda=impostazioni');
  await p.fill('#nl-conferma #ec-titolo', 'Titolo comune \uD83C\uDF88');
  await p.fill('#nl-conferma #ec-firma', 'Il team di {sito}');
  await Promise.all([p.waitForNavigation(), p.click('#nl-conferma button[type=submit]')]);
  ok('3. valori comuni salvati', /Email di conferma salvata/.test(await flash()), await flash());
  const comuni = String(uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_conferma_email'") || '');
  ok('3. l\'emoji nel database è un\'entità (tabelle a 3 byte), e torna emoji nel campo', /&#x1F388;/.test(comuni) && await p.inputValue('#nl-conferma #ec-titolo') === 'Titolo comune \uD83C\uDF88', comuni);
  ok('3. solo le parti scritte si salvano', Object.keys(JSON.parse(comuni || '{}')).sort().join() === 'firma,titolo', comuni);

  // ===== 4. Il form: ogni parte, con segnaposti, emoji e codice cattivo.
  await vai('/admin/form.php?id=' + form + '&passo=4');
  ok('4. nella scheda del form l\'aiuto dice che il titolo vuoto usa quello comune', /testo comune[\s\S]*Titolo comune \uD83C\uDF88/.test(await p.locator('#ec-titolo-aiuto').textContent()));
  await p.click('#fm-conferma-campi > summary');
  const parti = {
    oggetto: '\uD83C\uDF89 {nome}, conferma per {lista}',
    intestazione: 'TESTA {sito} <img src=x onerror=alert(1)>',
    testo: 'Buongiorno {nome} \uD83D\uDE00,\n\nun ultimo passo per «{lista}» su {sito}: vale {giorni} giorni.\n<script>alert(1)</script><b onclick="alert(2)">Grazie</b> <a href="javascript:alert(3)">qui</a> <a href="https://esempio.it/info">info</a>',
    pulsante: 'Sì, voglio {lista}',
    colore: '#0a7d32',
    sotto: 'Pulsante bloccato? Copia questo:',
    nota: 'Non sei stato tu? Ignora, {nome}.',
  };
  for (const [k, v] of Object.entries(parti)) await p.fill('#fm-conferma #ec-' + k, v);
  await Promise.all([p.waitForNavigation(), p.click('#fm-form button[name=vai][value=resta]')]);
  ok('4. form salvato', /salvato/.test(await flash()), await flash());
  const salvato = String(uno(`SELECT conferma_email FROM {p}moduli WHERE id = ${form}`) || '');
  ok('4. nel database: niente script, eventi, javascript:; emoji come entità', salvato !== '' && !/<script|onclick|onerror="|javascript:/i.test(salvato) && /&#x1F389;/.test(salvato) && !/[\u{10000}-\u{10FFFF}]/u.test(salvato), salvato.slice(0, 300));
  ok('4. i campi tornano come scritti, con le emoji', await p.inputValue('#ec-oggetto') === parti.oggetto && /\uD83D\uDE00/.test(await p.inputValue('#ec-testo')) && await p.inputValue('#ec-colore') === '#0a7d32');
  ok('4. il riquadro dice quante parti ha cambiato il form', /Parti cambiate per questo form: 7/.test(await p.locator('#fm-conferma').innerText()));

  // Anteprima con un campo cambiato e non salvato.
  await p.fill('#ec-pulsante', 'Sì, {nome} \uD83D\uDE80');
  await p.click('#fm-conferma [data-ec-anteprima]');
  await p.waitForFunction(() => /Sì, Maria/.test((document.querySelector('#fm-conferma .ec-cornice') || {}).srcdoc || ''), null, { timeout: 10000 }).catch(() => {});
  const src = await p.evaluate(() => document.querySelector('#fm-conferma .ec-cornice').srcdoc);
  ok('5. anteprima: usa anche il campo non salvato, con il segnaposto e l\'emoji', /Sì, Maria \uD83D\uDE80/.test(src), src.slice(0, 100));
  ok('5. anteprima: l\'oggetto con l\'emoji e i segnaposti sostituiti', /\uD83C\uDF89 Maria, conferma per L'ebook gratis/.test(await p.locator('#fm-conferma .ec-oggetto-testo').innerText()));
  ok('5. anteprima: niente script né eventi; la cornice è sandbox', !/<script|<[^>]*\son[a-z]+=|javascript:/i.test(src) && (await p.getAttribute('#fm-conferma .ec-cornice', 'sandbox')) === '');
  // Prova a me.
  quante = posta().length;
  await p.click('#fm-conferma [data-ec-prova]');
  await p.waitForFunction(() => /Prova mandata|non/.test(document.querySelector('#fm-conferma .ec-stato').textContent), null, { timeout: 15000 }).catch(() => {});
  ok('6. prova: il messaggio dice a chi è andata', /Prova mandata a prova@esempio\.it/.test(await p.locator('#fm-conferma .ec-stato').innerText()), await p.locator('#fm-conferma .ec-stato').innerText());
  const mP = await aspetta(quante, 'prova@esempio.it');
  ok('6. prova: arriva con [PROVA] e il link finto', !!mP && /^\[PROVA\] \uD83C\uDF89 Maria/.test(mP.oggetto) && /t=prova-senza-valore/.test(mP.html), mP && mP.oggetto);
  const attiviPrima = +uno("SELECT COUNT(*) FROM {p}newsletter_iscritti WHERE stato = 'attivo'");
  if (mP) await vai(linkDi(mP).replace(/^https?:\/\/[^/]+/, B));
  ok('6. il link della prova non conferma niente', +uno("SELECT COUNT(*) FROM {p}newsletter_iscritti WHERE stato = 'attivo'") === attiviPrima);
  await p.goto(B + '/admin/form.php?id=' + form + '&passo=4', { waitUntil: 'networkidle' }); // il campo non salvato si perde

  // ===== 7. L'email vera: ogni parte cambiata, il link conferma ancora.
  const mC = await compila('carla');
  const hC = mC ? mC.html : '';
  ok('7. oggetto: emoji e segnaposti sostituiti', !!mC && mC.oggetto === "\uD83C\uDF89 Prova carla, conferma per L'ebook gratis", mC && mC.oggetto);
  ok('7. intestazione cambiata, il tag scritto esce come testo', /TESTA Studio Prova &lt;img src=x onerror=alert\(1\)&gt;/.test(hC) || /TESTA [^<]+ &lt;img/.test(hC), (hC.match(/letter-spacing:\.06em">([^<]*)/) || [])[1]);
  ok('7. titolo comune (il form non lo cambia), con l\'emoji', /<h1[^>]*>Titolo comune \uD83C\uDF88<\/h1>/.test(hC));
  ok('7. testo: paragrafi, segnaposti, emoji', /<p>Buongiorno Prova carla \uD83D\uDE00,<\/p><p>un ultimo passo per «L&apos;ebook gratis» su [^:]+: vale 7 giorni\./.test(hC) || /Buongiorno Prova carla \uD83D\uDE00,<\/p><p>un ultimo passo per «L(&apos;|&#039;|')ebook gratis»/.test(hC), (hC.match(/<h1[\s\S]{0,400}/) || [''])[0]);
  ok('7. testo: niente script, onclick, javascript:; il link buono resta', !/<script|alert\(1\)<\/script|onclick|javascript:/i.test(hC) && /<b>Grazie<\/b>/.test(hC) && /href="https:\/\/esempio\.it\/info"/.test(hC));
  ok('7. pulsante: testo, colore e testo leggibile sul colore', /background:#0a7d32;color:#fff;[^"]*">Sì, voglio L&apos;ebook gratis<\/a>/.test(hC) || /background:#0a7d32;color:#fff;[^"]*">Sì, voglio L(&#039;|&apos;)ebook gratis<\/a>/.test(hC));
  ok('7. frase sotto il pulsante, firma comune con {sito}, nota con {nome}', /Pulsante bloccato\? Copia questo:<br>/.test(hC) && /Il team di Studio Prova/.test(hC) && /Non sei stato tu\? Ignora, Prova carla\./.test(hC));
  ok('7. nessun segnaposto rimasto', !/\{(nome|lista|giorni|sito)\}/.test(hC + mC.oggetto));
  const lC = linkDi(mC);
  ok('7. il pulsante porta al link vero, con il token', /newsletter-conferma\/\?t=[a-f0-9]{64}"/.test(hC), lC);
  await vai(lC.replace(/^https?:\/\/[^/]+/, B));
  const carla = (q("SELECT i.stato, (SELECT COUNT(*) FROM {p}newsletter_iscritti_liste l WHERE l.iscritto_id = i.id AND l.lista_id = " + ebook + ") AS in_lista FROM {p}newsletter_iscritti i WHERE i.email = 'carla@persona.it'") || [])[0] || {};
  ok('7. il link conferma: iscrizione attiva e nella lista Ebook', carla.stato === 'attivo' && +carla.in_lista === 1, carla);

  // ===== 8. Il modulo della newsletter usa i comuni, non quelli del form.
  quante = posta().length;
  cli('richiesta', 'dario@persona.it', 'Dario');
  const mD = await aspetta(quante, 'dario@persona.it');
  ok('8. modulo della newsletter: titolo e firma comuni, il resto come prima', !!mD && /Titolo comune \uD83C\uDF88/.test(mD.html) && /Il team di Studio Prova/.test(mD.html) && mD.oggetto === vB.oggetto && /Ciao Dario,<\/p><p>manca un clic/.test(mD.html) && !/0a7d32/.test(mD.html), mD && mD.oggetto);

  // ===== 9. Attacchi dall'amministratore: a capo nell'oggetto, colore con CSS, intestazione.
  await vai('/admin/newsletter.php?scheda=impostazioni');
  const tok = await p.locator('#nl-conferma input[name=_csrf]').inputValue();
  await p.request.post(B + '/admin/newsletter.php', { form: { _csrf: tok, azione: 'conferma_email', 'ce[oggetto]': 'Ciao\r\nBcc: spia@cattivo.it', 'ce[colore]': 'red;background:url(javascript:alert(1))', 'ce[titolo]': 'Titolo comune \uD83C\uDF88', 'ce[firma]': 'Il team di {sito}' }, headers: { Origin: B, Referer: B + '/admin/newsletter.php' } });
  const c2 = JSON.parse(String(uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_conferma_email'") || '{}'));
  ok('9. oggetto senza a capo, colore non valido scartato', c2.oggetto === 'Ciao Bcc: spia@cattivo.it' && !('colore' in c2), c2);
  quante = posta().length;
  cli('richiesta', 'elsa@persona.it', 'Elsa');
  const mE = await aspetta(quante, 'elsa@persona.it');
  ok('9. nessuna intestazione Bcc nascosta: va solo a chi si iscrive', !!mE && mE.dest === 'elsa@persona.it' && !/^Bcc:/mi.test(mE.testa), mE && [mE.dest, mE.oggetto]);

  // ===== 10. Il redattore: non vede e non salva.
  execFileSync('php', [path.join(__dirname, 'tema-cli.php'), DIR, 'redattore', 'Redattore-Prova-2026-xyz']);
  const rctx = await br.newContext(); const rp = await rctx.newPage();
  await rp.goto(B + '/admin/login.php'); await rp.fill('#username', 'redattore-prova'); await rp.fill('#password', 'Redattore-Prova-2026-xyz');
  await Promise.all([rp.waitForNavigation(), rp.click('button[type=submit]')]);
  await rp.goto(B + '/admin/form.php?id=' + form + '&passo=4', { waitUntil: 'networkidle' });
  const rForm = await rp.content();
  await rp.goto(B + '/admin/newsletter.php?scheda=impostazioni', { waitUntil: 'networkidle' });
  const rNl = await rp.content();
  ok('10. redattore: Form e Newsletter non si aprono, nessun campo dell\'email', !/ec-oggetto|Email di conferma/.test(rForm + rNl) && /index\.php|admin\/?$/.test(rp.url()), rp.url());
  await rp.goto(B + '/admin/', { waitUntil: 'networkidle' });
  const rTok = await rp.locator('meta[name=pn-csrf]').getAttribute('content').catch(() => '') || '';
  const rApi = await rp.request.post(B + '/admin/email-conferma-api.php', { form: { _csrf: rTok, azione: 'anteprima', contesto: 'comuni' }, headers: { Origin: B } });
  ok('10. redattore: anteprima e prova rifiutate (403)', rApi.status() === 403, rApi.status());
  const r1 = await rp.request.post(B + '/admin/newsletter.php', { form: { _csrf: rTok, azione: 'conferma_email', 'ce[oggetto]': 'Redattore' }, headers: { Origin: B }, maxRedirects: 0 });
  const r2 = await rp.request.post(B + '/admin/form.php', { form: { _csrf: rTok, id: String(form), nome: 'Contatti', 'ce[oggetto]': 'Redattore' }, headers: { Origin: B }, maxRedirects: 0 });
  ok('10. redattore: i salvataggi con il suo token non cambiano niente', !/Redattore/.test(String(uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_conferma_email'"))) && !/Redattore/.test(String(uno(`SELECT conferma_email FROM {p}moduli WHERE id = ${form}`))), [r1.status(), r2.status()]);
  const anon = await (await br.newContext()).request.post(B + '/admin/email-conferma-api.php', { form: { azione: 'anteprima' }, maxRedirects: 0 });
  ok('10. senza login: niente anteprima', [401, 302, 403].includes(anon.status()) && !/<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><\/head>/.test(await anon.text()), anon.status());
  await rctx.close();

  // ===== 11. "Riempi i campi vuoti" e salva: i testi uguali a quelli in uso non si salvano.
  q("DELETE FROM {p}settings WHERE setting_key = 'newsletter_conferma_email'");
  await vai('/admin/newsletter.php?scheda=impostazioni');
  await p.click('#nl-conferma [data-ec-riempi]');
  ok('11. Riempi: i campi vuoti prendono i testi di partenza', /manca un clic/.test(await p.inputValue('#nl-conferma #ec-testo')) && await p.inputValue('#nl-conferma #ec-colore') === '#7c3aed', await p.inputValue('#nl-conferma #ec-oggetto'));
  await Promise.all([p.waitForNavigation(), p.click('#nl-conferma button[type=submit]')]);
  ok('11. salvati senza cambiarli: niente si salva e l\'email resta quella di partenza', /testi di partenza/.test(await flash()) && !uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_conferma_email' AND setting_value <> ''"), await flash());

  // ===== 12. Al telefono la scheda non scorre di lato.
  await p.setViewportSize({ width: 390, height: 800 });
  await vai('/admin/form.php?id=' + form + '&passo=4');
  const largo1 = await p.evaluate(() => document.documentElement.scrollWidth);
  await vai('/admin/newsletter.php?scheda=impostazioni');
  const largo2 = await p.evaluate(() => document.documentElement.scrollWidth);
  ok('12. a 390 px Form e Newsletter non scorrono di lato', largo1 <= 390 && largo2 <= 390, [largo1, largo2]);
  await p.setViewportSize({ width: 1280, height: 900 });

  // Com'era.
  const esc = s => s == null ? 'NULL' : "'" + String(s).replace(/'/g, "''") + "'";
  q(`UPDATE {p}moduli SET lista_id = ${+prima.lista_id || 0}, lista_conferma = ${+prima.lista_conferma || 0}, conferma = ${esc(prima.conferma || 'nessuna')}, titolo = ${esc(prima.titolo || '')}, conferma_email = ${esc(prima.conferma_email)} WHERE id = ${form}`);
  q("DELETE FROM {p}users WHERE username = 'redattore-prova'");
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
