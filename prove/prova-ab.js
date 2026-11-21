// 2.13.0, fase 6 del piano Brevo (docs-sviluppo/brevo-confronto.md, capitolo 1.14): il test A/B delle campagne, provato
// come un utente, con il tempo spostato (campagne-cli.php ab_indietro).
// Crea campagna > Test A/B; sezione Test A/B nel riepilogo (stato, cursore, criterio, durata, valori fuori dai limiti
// rifiutati); prova delle due versioni; gruppi delle dimensioni giuste, una versione a persona; vincitore per aperture
// (con due cron lanciati insieme: il resto parte una volta sola), per clic, e pari (parte la A); test sull'oggetto e sul
// contenuto (versione B nata come copia della A, poi fatta con l'editor a blocchi); programmato; versione web della B;
// risultati nel report; tipo riconoscibile nell'elenco; Invia ai nuovi contatti con la vincente; Duplica; Annulla durante
// il test; 390 px senza scorrimento di lato.
// uso: node prove/prova-ab.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella CMS> [cartella foto]
// Il server di posta finto: python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> &
// Lascia i dati di prova (CR ...): va su un sito da buttare.
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync, execFile } = require('child_process');
const fs = require('fs');
const path = require('path');
const [B, UTENTE, PASSWORD, SMTP, POSTA, DIR, FOTO] = process.argv.slice(2);
const CLI = path.join(__dirname, 'campagne-cli.php');
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (!c && extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 500) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [CLI, DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 << 20 }).trim().split('\n').pop());
const cliAsync = (...a) => new Promise((ris) => execFile('php', [CLI, DIR, ...a.map(String)], { encoding: 'utf8' }, (e, out) => ris(e ? ['ERRORE ' + e.message] : JSON.parse(out.trim().split('\n').pop()))));
const q = (sql) => cli('sql', sql);
const uno = (sql) => { const r = q(sql); return r && r[0] ? Object.values(r[0])[0] : null; };
const num = (sql) => { const v = uno(sql); return v === null ? NaN : +v; };

function leggi(eml) {
  const i = eml.search(/\r?\n\r?\n/);
  const testa = eml.slice(0, i).replace(/\r?\n[ \t]+/g, ' '); const corpo = eml.slice(i).replace(/^\r?\n\r?\n/, '');
  const h = (t, nome) => ((t.match(new RegExp('^' + nome + ':[ \\t]*(.*)$', 'mi')) || [])[1] || '').trim();
  const dec = s => s.replace(/=\?UTF-8\?B\?([^?]+)\?=/gi, (_, b) => Buffer.from(b, 'base64').toString('utf8'));
  const parte = (t, c) => /base64/i.test(h(t, 'Content-Transfer-Encoding')) ? Buffer.from(c.replace(/\s+/g, ''), 'base64').toString('utf8') : c;
  let html = '';
  const b = (h(testa, 'Content-Type').match(/boundary="([^"]+)"/) || [])[1];
  if (b) for (const p of corpo.split('--' + b).slice(1)) {
    const j = p.search(/\r?\n\r?\n/); if (j < 0) continue;
    if (/text\/html/i.test(h(p.slice(0, j).trim(), 'Content-Type'))) html = parte(p.slice(0, j).trim(), p.slice(j).trim());
  } else html = parte(testa, corpo);
  return { dest: h(testa, 'X-Destinatari'), oggetto: dec(h(testa, 'Subject')), html };
}
const posta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => leggi(fs.readFileSync(path.join(POSTA, f), 'utf8')));
const da = () => posta().length;
const dopo = (k) => posta().slice(k);
const conta = (lista) => { const m = {}; for (const x of lista) m[x.dest] = (m[x.dest] || 0) + 1; return m; };
const pixel = (html) => ((html.match(/<img src="([^"]*api\/nl\.php[^"]*)"/) || [])[1] || '').replace(/&amp;/g, '&');
const clic = (html) => ((html.match(/href="([^"]*api\/nl\.php[^"]*)"/) || [])[1] || '').replace(/&amp;/g, '&');
const webLink = (html) => ((html.match(/href="([^"]*newsletter-web[^"]*)"/) || [])[1] || '').replace(/&amp;/g, '&');

(async () => {
  cli('pulisci');
  const LA = cli('semina_ab', 40);
  ok('semina: 40 contatti nella lista CR AB', LA > 0 && num(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE lista_id = ${LA}`) === 40);
  cli('smtp', SMTP);
  q("DELETE FROM {p}settings WHERE setting_key = 'newsletter_indirizzo'");

  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  pnConferme.on(p, d => d.accept());
  const vai = async (u) => p.goto(B + (u.startsWith('/') ? u : '/admin/' + u), { waitUntil: 'networkidle' });
  const flash = async () => (await p.locator('.flash, [role=alert]').allInnerTexts()).join(' | ');
  const invia = async (sel) => { await Promise.all([p.waitForNavigation(), p.click(sel)]); };
  const foto = async (nome, sel) => { if (!FOTO) return; if (sel) await p.locator(sel).first().screenshot({ path: path.join(FOTO, nome + '.png') }); else await p.screenshot({ path: path.join(FOTO, nome + '.png'), fullPage: true }); };
  const apri = async (url) => (await ctx.request.get(url, { maxRedirects: 0 })).status();
  await vai('/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  // Una campagna A/B dal pannello, fino alla sezione Test A/B a posto.
  const crea = async (nome) => {
    await vai('newsletter.php?crea=1');
    await p.check('#nl-crea input[name=tipo][value=ab]');
    await p.fill('#nl-crea-nome', nome);
    await invia('#nl-crea button[type=submit]');
    return +new URL(p.url()).searchParams.get('id');
  };
  const destinatari = async (c) => {
    await vai(`newsletter-campagna.php?id=${c}&apri=destinatari`);
    await p.check(`#sez-destinatari input[name="liste[]"][value="${LA}"]`);
    await invia('#sez-destinatari button[type=submit]');
  };
  const oggetto = async (c, o) => { await vai(`newsletter-campagna.php?id=${c}&apri=oggetto`); await p.fill('#oggetto', o); await invia('#sez-oggetto button[type=submit]'); };
  const testo = async (c, t, qv = '') => {
    await vai(`newsletter-campagna.php?id=${c}${qv}&apri=design`);
    await p.locator('#editor .ql-editor').click(); await p.keyboard.press('Control+A'); await p.keyboard.type(t);
    await p.fill('#pulsante_testo', 'Scopri'); await p.fill('#nl-pulsante-link', B + '/contatti/');
    await invia('#nl-form button[type=submit]');
  };
  const cursore = async (v) => p.evaluate((v) => { const r = document.getElementById('nlr-ab-perc'); r.value = v; r.dispatchEvent(new Event('input', { bubbles: true })); }, v);
  const sezioneAb = async (c, imp) => {
    await vai(`newsletter-campagna.php?id=${c}&apri=ab`);
    if (imp.cosa) await p.check(`#sez-ab input[name=cosa][value=${imp.cosa}]`);
    if (imp.b !== undefined) await p.fill('#nlr-oggetto-b', imp.b);
    if (imp.perc) await cursore(imp.perc);
    if (imp.criterio) await p.check(`#sez-ab input[name=criterio][value=${imp.criterio}]`);
    if (imp.durata) await p.fill('#nlr-ab-durata', String(imp.durata));
    if (imp.unita) await p.selectOption('#nlr-ab-unita', imp.unita);
    await invia('#sez-ab button[type=submit]');
  };
  const ab = (c) => cli('ab', c);
  const giro = () => cli('giro');

  // ===== Crea campagna > Test A/B
  await vai('newsletter.php?crea=1');
  ok('Crea una campagna: c\'è il tipo Test A/B accanto a Normale', await p.locator('#nl-crea input[name=tipo][value=ab]').count() === 1 && await p.locator('#nl-crea input[name=tipo][value=normale]').isChecked());
  const c1 = await crea('CR AB Oggetto');
  ok('la campagna A/B nasce: tipo ab e la sua riga del test', uno(`SELECT tipo FROM {p}newsletter_campagne WHERE id = ${c1}`) === 'ab' && ab(c1).ab.fase === '' && +ab(c1).ab.percentuale === 20, ab(c1));
  const sez = await p.locator('details.nlr-sez > summary h3').allInnerTexts();
  ok('riepilogo: la sezione Test A/B sta dopo Destinatari', JSON.stringify(sez) === JSON.stringify(['Mittente', 'Destinatari', 'Test A/B', 'Oggetto', 'Design', 'Impostazioni aggiuntive', 'Anteprima e prova']), sez);
  ok('Test A/B da completare, con la frase di cosa manca (oggetto B)', /Da completare/.test(await p.locator('#sez-ab > summary').innerText()) && /oggetto della versione B/.test(await p.locator('#nlr-manca').innerText()));
  await vai(`newsletter-campagna.php?id=${c1}&apri=impostazioni`);
  ok('niente lotti nel test A/B: la sezione lo dice', await p.locator('#sez-impostazioni input[name=lotti_attivi]').count() === 0 && /non c'è nel test A\/B/.test(await p.locator('#sez-impostazioni').innerText()));
  await destinatari(c1);
  await oggetto(c1, 'CR Oggetto A');
  await testo(c1, 'Il testo uguale per tutte e due le versioni.');
  await sezioneAb(c1, { b: 'CR Oggetto A' });
  ok('oggetto B uguale alla A: la sezione resta da completare e lo dice', /uguale a quello della A/.test(await p.locator('#nlr-manca').innerText()) && await p.locator('#nlr-invio button.btn-primary').isDisabled());
  await vai(`newsletter-campagna.php?id=${c1}&apri=ab`);
  await cursore(25);
  ok('il cursore dice quanti contatti va a ogni versione (25% di 40: 10, 10, il resto 20)', /Versione A a 10 contatti, versione B a 10, la vincente agli altri 20/.test(await p.locator('[data-ab-gruppi]').innerText()) && (await p.locator('#nlr-ab-perc-val').innerText()) === '25%', await p.locator('[data-ab-gruppi]').innerText());
  await cursore(50);
  ok('al massimo 50% per gruppo: il resto è zero', +(await p.locator('#nlr-ab-perc').inputValue()) === 50 && /agli altri 0/.test(await p.locator('[data-ab-gruppi]').innerText()));
  await foto('ab-sezione', '#sez-ab');
  await sezioneAb(c1, { b: 'CR Oggetto B', perc: 25, criterio: 'aperture', durata: 2, unita: 'ore' });
  const r1 = ab(c1).ab;
  ok('Salva il test A/B: oggetto B, 25%, aperture, 2 ore', r1.oggetto_b === 'CR Oggetto B' && +r1.percentuale === 25 && r1.criterio === 'aperture' && +r1.durata === 2 && r1.unita === 'ore', r1);
  ok('Test A/B a posto, il riassunto dice cosa prova', /A posto/.test(await p.locator('#sez-ab > summary').innerText()) && /L'oggetto · gruppi del 25% · il tasso di apertura · 2 ore/.test(await p.locator('#sez-ab .nlr-riassunto').innerText()), await p.locator('#sez-ab .nlr-riassunto').innerText());

  // Valori fuori dai limiti, mandati a mano: rifiutati, niente cambia.
  const tk = await p.locator('#sez-ab input[name=_csrf]').inputValue();
  const manda = async (campi) => { await ctx.request.post(B + '/admin/newsletter-campagna.php?id=' + c1, { form: Object.assign({ _csrf: tk, azione: 'sezione', sez: 'ab', cosa: 'oggetto', oggetto_b: 'CR Oggetto B', percentuale: '25', criterio: 'aperture', durata: '2', unita: 'ore' }, campi), maxRedirects: 0 }); return ab(c1).ab; };
  const fuori = [{ percentuale: '60' }, { percentuale: '0' }, { percentuale: '-5' }, { percentuale: '20abc' }, { durata: '24' }, { durata: '8', unita: 'giorni' }, { durata: '0' }, { cosa: 'mittente' }, { criterio: 'spam' }, { unita: 'settimane' }];
  let tutti = true;
  for (const f of fuori) { const r = await manda(f); if (+r.percentuale !== 25 || +r.durata !== 2 || r.unita !== 'ore' || r.cosa !== 'oggetto' || r.criterio !== 'aperture') tutti = false; }
  ok('percentuali, durate, criteri e cose da provare fuori dai limiti: rifiutati, il test resta com\'era', tutti, ab(c1).ab);
  await vai(`newsletter-campagna.php?id=${c1}`);
  const errori = await flash();
  ok('e la pagina dice gli errori in parole', /Ogni gruppo di prova va da 1% a 50%/.test(errori) && /La durata in ore va da 1 a 23/.test(errori) && /La durata in giorni va da 1 a 7/.test(errori), errori);
  const r7 = await manda({ durata: '7', unita: 'giorni' });
  ok('7 giorni sì (il massimo)', +r7.durata === 7 && r7.unita === 'giorni');
  await manda({});

  // Anteprima e prova delle due versioni
  await vai(`newsletter-campagna.php?id=${c1}&versione=b&apri=prova`);
  ok('anteprima: la versione B ha l\'oggetto B', (await p.locator('[data-nlr-oggetto]').innerText()) === 'CR Oggetto B' && /versione B/.test(await p.locator('#sez-prova').innerText()));
  await vai(`newsletter-campagna.php?id=${c1}&apri=prova`);
  ok('anteprima: la versione A ha l\'oggetto A', (await p.locator('[data-nlr-oggetto]').innerText()) === 'CR Oggetto A');
  let k = da();
  await p.fill('#nlc-prova-a', 'prova-ab@esempio.it');
  await invia('#nlc-prova button[type=submit]');
  const prove = dopo(k).map(m => m.oggetto).sort();
  ok('la prova manda le due versioni: [PROVA A] e [PROVA B]', JSON.stringify(prove) === JSON.stringify(['[PROVA A] CR Oggetto A', '[PROVA B] CR Oggetto B']), prove);
  ok('le prove non entrano nella coda', num(`SELECT COUNT(*) FROM {p}newsletter_coda WHERE campagna_id = ${c1}`) === 0);

  // Invia subito: due gruppi di prova da 10, il resto aspetta
  await vai(`newsletter-campagna.php?id=${c1}`);
  ok('la finestra di conferma parla del test A/B', /test A\/B/.test(await p.locator('#nlr-invio form[data-conferma]').getAttribute('data-conferma') || ''));
  ok('solo Invia subito o Programma', (await p.locator('#nlr-invio input[name=quando]').count()) === 2);
  await invia('#nlr-invio button.btn-primary');
  k = da();
  const g1 = giro();
  const coda1 = ab(c1).coda;
  ok('al primo giro: 10 contatti con la A, 10 con la B, 20 in attesa della vincente', coda1['a|inviata'] === 10 && coda1['b|inviata'] === 10 && coda1['r|riserva'] === 20, [coda1, g1]);
  const m1 = dopo(k);
  const og1 = m1.map(m => m.oggetto);
  ok('partite 20 email: 10 con l\'oggetto A e 10 con il B', m1.length === 20 && og1.filter(o => o === 'CR Oggetto A').length === 10 && og1.filter(o => o === 'CR Oggetto B').length === 10, og1);
  ok('nessuno riceve due versioni', Object.values(conta(m1)).every(x => x === 1) && Object.keys(conta(m1)).length === 20);
  const verDi = Object.fromEntries(q(`SELECT i.email, q.versione FROM {p}newsletter_coda q JOIN {p}newsletter_iscritti i ON i.id = q.iscritto_id WHERE q.campagna_id = ${c1}`).map(r => [r.email, r.versione]));
  ok('ognuno ha l\'oggetto della sua versione', m1.every(m => (verDi[m.dest] === 'a' ? 'CR Oggetto A' : 'CR Oggetto B') === m.oggetto));
  ok('la campagna resta in invio (il resto aspetta)', uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c1}`) === 'in_invio' && ab(c1).ab.fase === 'prova');
  k = da(); giro();
  ok('prima della fine del test il resto non parte', da() === k && ab(c1).coda['r|riserva'] === 20);
  await vai(`newsletter-campagna.php?id=${c1}`);
  ok('il riepilogo dice il test in corso e quando sceglie', /Test A\/B in corso: 20 di 20 email di prova partite \(10 per versione\)/.test(await p.locator('.nlr-stato-box').first().innerText()) && /agli altri 20 contatti/.test(await p.locator('.nlr-stato-box').first().innerText()), await p.locator('.nlr-stato-box').first().innerText());
  ok('risultati: «Non ancora»', /Non ancora/.test(await p.locator('#nlr-ab-risultati').innerText()));

  // Aperture: 3 della A, 6 della B (dal pixel vero delle email)
  const diA = m1.filter(m => m.oggetto === 'CR Oggetto A'), diB = m1.filter(m => m.oggetto === 'CR Oggetto B');
  for (const m of diA.slice(0, 3)) await apri(pixel(m.html));
  for (const m of diB.slice(0, 6)) await apri(pixel(m.html));
  await apri(pixel(diB[0].html));
  const ris = num(`SELECT COUNT(DISTINCT iscritto_id) FROM {p}newsletter_eventi WHERE campagna_id = ${c1} AND tipo = 'apertura'`);
  ok('le aperture si contano (9 persone)', ris === 9, ris);
  cli('ab_indietro', c1, 2 * 3600 + 60);
  k = da();
  const [ga, gb] = await Promise.all([cliAsync('giro'), cliAsync('giro')]);
  const righe = [...ga, ...gb].join(' | ');
  giro(); // il giro dopo manda quello che il limite del giro non ha mandato
  const resto = dopo(k);
  const ab1 = ab(c1);
  ok('due cron insieme: il vincitore si sceglie una volta sola', (righe.match(/vince la versione B/g) || []).length === 1 && ab1.ab.vincitore === 'b' && ab1.ab.fase === 'resto', righe);
  ok('il resto (20) parte una volta sola, con la versione B', resto.length === 20 && resto.every(m => m.oggetto === 'CR Oggetto B') && Object.values(conta(resto)).every(x => x === 1), resto.map(m => m.oggetto));
  ok('il resto va proprio a chi non era nei gruppi di prova', resto.every(m => verDi[m.dest] === 'r'));
  const tutte = conta([...m1, ...resto]);
  ok('in tutto: 40 contatti, una email a testa', Object.keys(tutte).length === 40 && Object.values(tutte).every(x => x === 1));
  ok('la campagna è inviata: 40 su 40', uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c1}`) === 'inviata' && num(`SELECT inviate FROM {p}newsletter_campagne WHERE id = ${c1}`) === 40);
  k = da(); giro(); giro();
  ok('altri giri non mandano più niente', da() === k);
  await vai(`newsletter-campagna.php?id=${c1}`);
  const rep = await p.locator('#nlr-ab-risultati').innerText();
  ok('report: ha vinto la B, per il tasso di apertura, scelta e mandata agli altri 20', /Ha vinto la versione B/.test(rep) && /tasso di apertura/.test(rep) && /agli altri 20 contatti/.test(rep), rep);
  const rigaA = await p.locator('#nlr-ab-risultati tr[data-versione=a]').innerText(), rigaB = await p.locator('#nlr-ab-risultati tr[data-versione=b]').innerText();
  ok('report: A 10 inviate, 3 aperture (30%); B 10 inviate, 6 aperture (60%) e «Vincente»', /10\s+3 \(30%\)/.test(rigaA) && /10\s+6 \(60%\)/.test(rigaB) && /Vincente/.test(rigaB) && !/Vincente/.test(rigaA), [rigaA, rigaB]);
  ok('report: il resto, 20 di 20 con la versione B', /versione B\s+20 di 20/.test(await p.locator('#nlr-ab-risultati tr[data-versione=r]').innerText()));
  await foto('ab-report', '#nlr-ab-risultati');
  await vai('newsletter.php');
  ok('elenco: la campagna si riconosce come Test A/B', /Test A\/B/.test(await p.locator(`tr:has(a[href="newsletter-campagna.php?id=${c1}"])`).innerText()));
  await foto('ab-elenco', '.nl-elenco');

  // Invia ai nuovi contatti: una campagna normale con la versione vincente
  const tkE = await p.locator('input[name=_csrf]').first().inputValue();
  cli('semina_ab', 42);
  await ctx.request.post(B + '/admin/newsletter.php', { form: { _csrf: tkE, azione: 'nuovi', id: String(c1) } });
  const figlia = q(`SELECT id, tipo, oggetto, padre_id FROM {p}newsletter_campagne WHERE padre_id = ${c1}`)[0];
  ok('Invia ai nuovi contatti: campagna normale con l\'oggetto della vincente, solo ai 2 nuovi', figlia && figlia.tipo === 'normale' && figlia.oggetto === 'CR Oggetto B' && cli('conta', figlia.id) === 2, figlia);
  // Duplica: un test A/B con le stesse impostazioni, da capo
  await ctx.request.post(B + '/admin/newsletter.php', { form: { _csrf: tkE, azione: 'duplica', id: String(c1), 'parti[]': ['oggetto', 'design', 'destinatari'] } });
  const dup = q(`SELECT id, tipo FROM {p}newsletter_campagne WHERE nome = 'Copia di CR AB Oggetto'`)[0];
  const dab = dup ? ab(dup.id).ab : {};
  ok('Duplica: un test A/B con lo stesso oggetto B e gli stessi gruppi, che riparte da zero', dup && dup.tipo === 'ab' && dab.oggetto_b === 'CR Oggetto B' && +dab.percentuale === 25 && dab.fase === '' && dab.vincitore === '', [dup, dab]);

  // ===== Test sul contenuto, per clic, programmato, 50%: versione B con l'editor a blocchi
  const c2 = await crea('CR AB Contenuto');
  await destinatari(c2);
  await oggetto(c2, 'CR Contenuto');
  await testo(c2, 'Versione A del testo.');
  await sezioneAb(c2, { cosa: 'design', perc: 50, criterio: 'clic', durata: 1, unita: 'giorni' });
  const r2 = ab(c2).ab;
  ok('contenuto: la versione B nasce come copia della A', r2.cosa === 'design' && r2.editor_b === 'semplice' && /Versione A del testo/.test(r2.corpo_b || '') && r2.pulsante_testo_b === 'Scopri', r2);
  await vai(`newsletter-campagna.php?id=${c2}&apri=design`);
  ok('Design: le schede Versione A e Versione B', (await p.locator('#sez-design .nlr-versioni a').allInnerTexts()).join(',') === 'Versione A,Versione B' && /versione A/.test(await p.locator('#sez-design').innerText()));
  await vai(`newsletter-campagna.php?id=${c2}&versione=b&apri=design`);
  await invia('#sez-design form.nd-strada:has(input[name=editor][value=blocchi]) button');
  ok('Versione B > Usa l\'editor a blocchi: si apre l\'editor della versione B', p.url().includes('newsletter-design.php?campagna=' + c2 + '&versione=b') && /versione B/.test(await p.title()), p.url());
  await p.waitForSelector('.nd-b');
  await p.click('#nd-salva');
  await p.waitForFunction(() => !window.ndEditor.stato.sporco && !window.ndEditor.stato.salvando, null, { timeout: 8000 }).catch(() => {});
  const r2b = ab(c2).ab;
  ok('il design B si salva nella versione B, la A resta con l\'editor semplice', r2b.editor_b === 'blocchi' && (r2b.design_b || '').length > 50 && uno(`SELECT editor FROM {p}newsletter_campagne WHERE id = ${c2}`) === 'semplice', r2b.editor_b);
  await vai(`newsletter-campagna.php?id=${c2}&versione=b&apri=prova`);
  const antB = await p.locator('#nlr-anteprima iframe').getAttribute('srcdoc');
  await vai(`newsletter-campagna.php?id=${c2}&apri=prova`);
  const antA = await p.locator('#nlr-anteprima iframe').getAttribute('srcdoc');
  ok('anteprima: la A con il testo semplice, la B fatta a blocchi', /Versione A del testo/.test(antA) && !/Versione A del testo/.test(antB) && /nd-corpo/.test(antB) && !/nd-corpo/.test(antA));
  ok('Design e Test A/B a posto', /A posto/.test(await p.locator('#sez-ab > summary').innerText()) && /A posto/.test(await p.locator('#sez-design > summary').innerText()));
  // Programma fra un'ora
  await vai(`newsletter-campagna.php?id=${c2}`);
  await p.check('#nlr-invio input[name=quando][value=programma]'); // giorno e ora di partenza: domani alle 9
  await invia('#nlr-invio button.btn-primary');
  k = da(); giro();
  ok('programmata: prima dell\'ora non parte', uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c2}`) === 'programmata' && da() === k);
  q(`UPDATE {p}newsletter_campagne SET invio_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ${c2}`);
  giro(); giro(); // 42 email: il giro ne manda 40 alla volta
  const m2 = dopo(k);
  const coda2 = ab(c2).coda;
  ok('all\'ora: 50% e 50% di 42, 21 con la A e 21 con la B, nessun resto', coda2['a|inviata'] === 21 && coda2['b|inviata'] === 21 && !coda2['r|riserva'] && m2.length === 42, coda2);
  const conA = m2.filter(m => /Versione A del testo/.test(m.html)), conB = m2.filter(m => /nd-corpo/.test(m.html) && !/Versione A del testo/.test(m.html));
  ok('le email: 21 con il contenuto A, 21 con il contenuto B, una a testa', conA.length === 21 && conB.length === 21 && Object.values(conta(m2)).every(x => x === 1));
  ok('a 50% la campagna è inviata, ma il test aspetta ancora la sua fine', uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c2}`) === 'inviata' && ab(c2).ab.fase === 'prova');
  // Versione web di chi ha ricevuto la B
  const wb = webLink(conB[0].html);
  await p.goto(wb); const htmlWeb = await p.content();
  ok('versione web: chi ha ricevuto la B vede la B', wb !== '' && /nd-corpo/.test(htmlWeb) && !/Versione A del testo/.test(htmlWeb), wb);
  // Clic: 3 della A, 1 della B
  for (const m of conA.slice(0, 3)) await apri(clic(m.html));
  for (const m of conB.slice(0, 1)) { const u = clic(m.html); await apri(u); }
  cli('ab_indietro', c2, 86400 + 60);
  const g2 = giro().join(' | ');
  ok('per clic: vince la A (14,3% contro 4,8%)', ab(c2).ab.vincitore === 'a' && /vince la versione A \(14\.3% contro 4\.8%\)/.test(g2) && ab(c2).ab.fase === 'finita', g2);
  await vai(`newsletter-campagna.php?id=${c2}`);
  const rep2 = await p.locator('#nlr-ab-risultati').innerText();
  ok('report: ha vinto la A per il tasso di clic, e non c\'è resto', /Ha vinto la versione A/.test(rep2) && /tasso di clic/.test(rep2) && (await p.locator('#nlr-ab-risultati tr[data-versione=r]').count()) === 0, rep2);
  ok('report: A 3 clic (14.3%), B 1 clic (4.8%)', /3 \(14\.3%\)/.test(await p.locator('#nlr-ab-risultati tr[data-versione=a]').innerText()) && /1 \(4\.8%\)/.test(await p.locator('#nlr-ab-risultati tr[data-versione=b]').innerText()));

  // ===== Pari: nessuno apre, parte la A al resto
  const c3 = await crea('CR AB Pari');
  await destinatari(c3); await oggetto(c3, 'CR Pari A'); await testo(c3, 'Testo del pari.');
  await sezioneAb(c3, { b: 'CR Pari B', perc: 10, criterio: 'aperture', durata: 3, unita: 'ore' });
  await vai(`newsletter-campagna.php?id=${c3}`); await invia('#nlr-invio button.btn-primary');
  k = da(); giro();
  const coda3 = ab(c3).coda;
  ok('10% di 42: 4 e 4, il resto 34', coda3['a|inviata'] === 4 && coda3['b|inviata'] === 4 && coda3['r|riserva'] === 34, coda3);
  const tkL = await (async () => { await vai('newsletter.php'); return p.locator('input[name=_csrf]').first().inputValue(); })();
  await ctx.request.post(B + '/admin/newsletter.php', { form: { _csrf: tkL, azione: 'nuovi', id: String(c3) }, maxRedirects: 0 });
  ok('durante il test «Invia ai nuovi contatti» aspetta il vincitore', num(`SELECT COUNT(*) FROM {p}newsletter_campagne WHERE padre_id = ${c3}`) === 0 && /non ha ancora scelto/.test(await (async () => { await vai('newsletter.php'); return flash(); })()));
  cli('ab_indietro', c3, 3 * 3600 + 60);
  k = da(); giro();
  const m3 = dopo(k);
  ok('pari (nessuna apertura): al resto parte la versione A', ab(c3).ab.vincitore === 'pari' && m3.length === 34 && m3.every(m => m.oggetto === 'CR Pari A'), [ab(c3).ab.vincitore, m3.length]);
  await vai(`newsletter-campagna.php?id=${c3}`);
  ok('report: «Pari», è partita la versione A', /Pari/.test(await p.locator('#nlr-ab-risultati').innerText()) && /versione A/.test(await p.locator('#nlr-ab-risultati').innerText()) && /Partita al resto/.test(await p.locator('#nlr-ab-risultati tr[data-versione=a]').innerText()));

  // ===== Annullata durante il test: il resto non parte più
  const c4 = await crea('CR AB Annullata');
  await destinatari(c4); await oggetto(c4, 'CR Ann A'); await testo(c4, 'Testo annullato.');
  await sezioneAb(c4, { b: 'CR Ann B', perc: 20, durata: 1, unita: 'ore' });
  await vai(`newsletter-campagna.php?id=${c4}`); await invia('#nlr-invio button.btn-primary');
  giro();
  await vai(`newsletter-campagna.php?id=${c4}`);
  await invia('#nlr-invio form:has(input[value=annulla]) button');
  cli('ab_indietro', c4, 3700);
  k = da(); giro();
  ok('annullata durante il test: il resto non parte e il vincitore non si sceglie', da() === k && ab(c4).ab.fase === 'prova' && !ab(c4).coda['r|riserva'] && ab(c4).coda['r|saltata'] > 0, ab(c4).coda);

  // ===== Campagna normale: niente sezione A/B, niente versione nella coda
  await vai('newsletter.php?crea=1'); await p.fill('#nl-crea-nome', 'CR Normale'); await invia('#nl-crea button[type=submit]');
  ok('una campagna normale non ha la sezione Test A/B', (await p.locator('#sez-ab').count()) === 0 && uno(`SELECT tipo FROM {p}newsletter_campagne WHERE nome = 'CR Normale'`) === 'normale');

  // ===== Telefono
  await p.setViewportSize({ width: 390, height: 844 });
  for (const u of [`newsletter-campagna.php?id=${c1}`, `newsletter-campagna.php?id=${c3}&apri=ab`, 'newsletter.php']) {
    await vai(u);
    const lato = await p.evaluate(() => document.documentElement.scrollWidth - innerWidth);
    ok(`390 px: ${u} senza scorrimento di lato`, lato <= 0, lato);
  }
  await foto('ab-telefono');
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); console.log(`${n - ko} su ${n} OK (interrotta)`); process.exit(1); });
