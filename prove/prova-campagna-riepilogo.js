// 2.11.0, fase 4 del piano Brevo (docs-sviluppo/brevo-confronto.md): le campagne a riepilogo, la pagina di
// disiscrizione con il questionario e il centro preferenze, provati come un utente.
// Crea campagna con il nome; sezioni incomplete che bloccano l'invio; destinatari con liste e segmenti (doppioni tolti),
// esclusioni che vincono; segnaposti con attributi e valore predefinito; anteprima con i dati di un contatto e prova alla
// lista dei test; programmata che parte all'ora; lotti con gli orari giusti; versione web; testo semplice (generato e
// scritto a mano); codice HTML; piede con l'indirizzo dell'azienda; UTM, Rispondi a, campo "A"; Duplica con la scelta;
// Rimanda ai non aperti una volta sola (non per i lotti); Invia ai nuovi contatti; Archivia, Annulla, Elimina solo in
// bozza; disiscrizione con il questionario (motivo salvato, lista nera con il motivo giusto); centro preferenze (uscire da
// una lista sola e non riceverla più, rientrare, cambiare il nome, liste non pubbliche ignorate, uscire da tutto);
// statistiche dei motivi; 390 px senza scorrimento di lato.
// uso: node prove/prova-campagna-riepilogo.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella CMS> [cartella foto]
// Il server di posta finto: python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> &
// Lascia i dati di prova (CR ...): va su un sito da buttare.
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const [B, UTENTE, PASSWORD, SMTP, POSTA, DIR, FOTO] = process.argv.slice(2);
const CLI = path.join(__dirname, 'campagne-cli.php');
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (!c && extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 400) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [CLI, DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 << 20 }).trim().split('\n').pop());
const q = (sql) => cli('sql', sql);
const uno = (sql) => { const r = q(sql); return r && r[0] ? Object.values(r[0])[0] : null; };
const num = (sql) => { const v = uno(sql); return v === null ? NaN : +v; };

function leggi(eml) {
  const i = eml.search(/\r?\n\r?\n/);
  const testa = eml.slice(0, i).replace(/\r?\n[ \t]+/g, ' '); const corpo = eml.slice(i).replace(/^\r?\n\r?\n/, '');
  const h = (t, nome) => ((t.match(new RegExp('^' + nome + ':[ \\t]*(.*)$', 'mi')) || [])[1] || '').trim();
  const dec = s => s.replace(/=\?UTF-8\?B\?([^?]+)\?=/gi, (_, b) => Buffer.from(b, 'base64').toString('utf8'));
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
  } else if (/text\/plain/i.test(ct)) testo = parte(testa, corpo); else html = parte(testa, corpo);
  return { dest: h(testa, 'X-Destinatari'), to: dec(h(testa, 'To')), replyTo: h(testa, 'Reply-To'), oggetto: dec(h(testa, 'Subject')), testa, html, testo, interne: /X-Plume-(A|Testo):/i.test(testa) };
}
const posta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => leggi(fs.readFileSync(path.join(POSTA, f), 'utf8')));
const da = () => posta().length;
const arrivate = (dopo) => posta().slice(dopo);
const chi = (lista) => lista.map(m => m.dest.replace(/@cr-prova\.it/, '')).sort().join(',');
const href = (html, re) => { const m = html.match(new RegExp('href="([^"]*' + re + '[^"]*)"')); return m ? m[1].replace(/&amp;/g, '&') : ''; };

(async () => {
  cli('pulisci');
  const S = cli('semina');
  const L = S.liste, C = S.contatti, SG = S.segmenti;
  ok('semina: liste, contatti, segmenti e lista dei test della prova', L['CR Alfa'] > 0 && C.a1 > 0 && SG.roma > 0 && SG.a4 > 0, S);

  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  pnConferme.on(p, d => d.accept());
  const vai = async (u) => p.goto(B + (u.startsWith('/') ? u : '/admin/' + u), { waitUntil: 'networkidle' });
  const flash = async () => (await p.locator('.flash, [role=alert]').allInnerTexts()).join(' | ');
  const invia = async (sel) => { await Promise.all([p.waitForNavigation(), p.click(sel)]); };
  const foto = async (nome, sel) => { if (!FOTO) return; if (sel) await p.locator(sel).first().screenshot({ path: path.join(FOTO, nome + '.png') }); else await p.screenshot({ path: path.join(FOTO, nome + '.png'), fullPage: true }); };
  const token = async (u) => { await vai(u); return p.locator('input[name=_csrf]').first().inputValue(); };
  await vai('/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  // Posta verso il server finto, limite orario largo, indirizzo dell'azienda vuoto.
  await vai('impostazioni.php');
  await p.fill('#smtp_host', '127.0.0.1'); await p.fill('#smtp_porta', SMTP); await p.selectOption('#smtp_sicurezza', 'nessuna');
  await p.fill('[name=smtp_mittente]', 'sito@esempio.it');
  await invia('form:has(input[value=save_posta]) button[type=submit]');
  await vai('newsletter.php?scheda=impostazioni');
  await p.fill('#per_ora', '2000');
  await invia('form:has(input[value=impostazioni]) button[type=submit]');
  q("DELETE FROM {p}settings WHERE setting_key IN ('newsletter_indirizzo', 'newsletter_pagine')");

  // ===== Crea campagna
  await vai('newsletter.php');
  ok('elenco: avviso dell\'indirizzo dell\'azienda che manca, con il link alle Impostazioni', /Manca l'indirizzo dell'azienda/.test(await p.locator('#nl-avviso-indirizzo').innerText().catch(() => '')));
  await vai('newsletter.php?crea=1');
  ok('Crea una campagna: tipo Normale e il nome', await p.locator('#nl-crea input[name=tipo][value=normale]').isChecked() && await p.locator('#nl-crea-nome').isVisible());
  const tk = await p.locator('#nl-crea input[name=_csrf]').inputValue();
  const r0 = await ctx.request.post(B + '/admin/newsletter.php', { form: { _csrf: tk, azione: 'nuova', nome: '  ' }, maxRedirects: 0 });
  ok('senza nome la campagna non nasce', r0.status() === 302 && !/newsletter-campagna/.test(r0.headers()['location'] || '') && num("SELECT COUNT(*) FROM {p}newsletter_campagne WHERE nome = ''") === 0, r0.headers()['location']);
  await vai('newsletter.php?crea=1');
  await p.fill('#nl-crea-nome', 'CR Prima');
  await invia('#nl-crea button[type=submit]');
  const c1 = +new URL(p.url()).searchParams.get('id');
  ok('Crea la campagna porta al riepilogo', /newsletter-campagna\.php\?id=\d+/.test(p.url()) && uno(`SELECT nome FROM {p}newsletter_campagne WHERE id = ${c1}`) === 'CR Prima', p.url());
  const sezioni = await p.locator('details.nlr-sez > summary h3').allInnerTexts();
  ok('riepilogo: Mittente, Destinatari, Oggetto, Design, Impostazioni aggiuntive, Anteprima e prova', JSON.stringify(sezioni) === JSON.stringify(['Mittente', 'Destinatari', 'Oggetto', 'Design', 'Impostazioni aggiuntive', 'Anteprima e prova']), sezioni);
  ok('le sezioni partono chiuse', (await p.locator('details.nlr-sez[open]').count()) === 0);
  const manca = await p.locator('#nlr-manca').innerText();
  ok('sezioni incomplete: dice cosa manca (destinatari, oggetto, testo)', /mancano 3 cose/.test(manca) && /lista o un segmento/.test(manca) && /oggetto/.test(manca) && /testo/.test(manca), manca);
  ok('Mittente è a posto (il predefinito), le altre tre da completare', /A posto/.test(await p.locator('#sez-mittente > summary').innerText()) && (await p.locator('details.nlr-sez .nlr-manca').count()) === 3);
  ok('il pulsante dell\'invio è spento finché manca qualcosa', await p.locator('#nlr-invio button.btn-primary').isDisabled());
  const tk1 = await p.locator('#nlr-invio input[name=_csrf]').inputValue();
  await ctx.request.post(B + '/admin/newsletter-campagna.php?id=' + c1, { form: { _csrf: tk1, azione: 'invia', quando: 'subito' } });
  ok('un invio forzato con le sezioni incomplete non parte', uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c1}`) === 'bozza');
  await foto('riepilogo-vuoto');

  // ===== Destinatari: liste, segmento, esclusioni, doppioni
  await vai(`newsletter-campagna.php?id=${c1}&apri=destinatari`);
  for (const l of ['CR Alfa', 'CR Beta']) await p.check(`#sez-destinatari input[name="liste[]"][value="${L[l]}"]`);
  await p.check(`#sez-destinatari input[name="segmenti[]"][value="${SG.roma}"]`);
  await p.click('#sez-destinatari details.pn-aiuto > summary');
  await p.check(`#sez-destinatari input[name="escludi_liste[]"][value="${L['CR Escl']}"]`);
  await p.check(`#sez-destinatari input[name="escludi_segmenti[]"][value="${SG.a4}"]`);
  await invia('#sez-destinatari button[type=submit]');
  // Alfa (a1, a2, a3, a5, a6 uscito, a8 uscito da Alfa) + Beta (a3, a4) + Roma (a2, a7) - Escl (a5) - segmento a4 = a1, a2, a3, a7
  ok('destinatari: 4 contatti (liste più segmento, doppioni una volta, esclusioni tolte, usciti fuori)', +(await p.locator('[data-nlr-destinatari]').innerText()) === 4 && cli('conta', c1) === 4, await p.locator('[data-nlr-destinatari]').innerText());
  ok('Destinatari a posto, il riassunto nomina liste e segmento', /A posto/.test(await p.locator('#sez-destinatari > summary').innerText()) && /CR Alfa, CR Beta, segmento CR Roma/.test(await p.locator('#sez-destinatari .nlr-riassunto').innerText()));

  // ===== Oggetto con i segnaposti
  await vai(`newsletter-campagna.php?id=${c1}&apri=oggetto`);
  await p.fill('#oggetto', 'CR Ciao {{ contact.NOME | default:"amico" }} da {{ contact.CITTA | upper }}');
  await p.fill('#anteprima', 'Le novità per {{ contact.NOME | default:"te" }}');
  await invia('#sez-oggetto button[type=submit]');
  ok('Oggetto salvato e a posto', /A posto/.test(await p.locator('#sez-oggetto > summary').innerText()));

  // ===== Design: editor semplice con un pulsante verso il sito
  await vai(`newsletter-campagna.php?id=${c1}&apri=design`);
  await p.locator('#editor .ql-editor').click();
  await p.keyboard.type('Ciao {{ contact.NOME | default:"amico" }}, ecco le novità di questo mese.');
  await p.fill('#pulsante_testo', 'Scrivici');
  await p.fill('#nl-pulsante-link', B + '/contatti/');
  await invia('#nl-form button[type=submit]');
  ok('Design salvato e a posto', /A posto/.test(await p.locator('#sez-design > summary').innerText()) && /ecco le novità/.test(uno(`SELECT corpo FROM {p}newsletter_campagne WHERE id = ${c1}`) || ''));

  // ===== Impostazioni aggiuntive
  await vai(`newsletter-campagna.php?id=${c1}&apri=impostazioni`);
  ok('campagna nuova: versione web e link delle preferenze accesi di partenza', await p.isChecked('input[name=link_web]') && await p.isChecked('input[name=link_preferenze]'));
  await p.fill('#nlr-rispondi', 'nonvalido');
  await p.locator('#nlr-rispondi').evaluate(e => e.type = 'text');
  await invia('#sez-impostazioni button[type=submit]');
  ok('Rispondi a sbagliato: errore in parole, niente salvato', /non è valido/.test(await flash()) && uno(`SELECT rispondi_a FROM {p}newsletter_campagne WHERE id = ${c1}`) === '' && await p.locator('#sez-impostazioni[open]').count() === 1);
  await p.fill('#nlr-rispondi', 'risposte@cr-prova.it');
  await p.fill('#nlr-campo-a', '{{ contact.NOME }} {{ contact.COGNOME }}');
  await p.fill('#nlr-tag', 'promo');
  await p.check('input[name=utm]'); await p.fill('#nlr-utm', 'cr-prima');
  await p.check('input[name=lotti_attivi]'); await p.fill('#nlr-lotti', '2'); await p.fill('#nlr-intervallo', '30');
  await invia('#sez-impostazioni button[type=submit]');
  const imp = q(`SELECT rispondi_a, campo_a, tag, utm, utm_campagna, lotti, lotti_intervallo FROM {p}newsletter_campagne WHERE id = ${c1}`)[0];
  ok('Impostazioni aggiuntive salvate (Rispondi a, campo A, tag, UTM, 2 lotti ogni 30 minuti)', imp.rispondi_a === 'risposte@cr-prova.it' && imp.tag === 'promo' && +imp.utm === 1 && imp.utm_campagna === 'cr-prima' && +imp.lotti === 2 && +imp.lotti_intervallo === 30, imp);
  ok('nessuna sezione da completare: il pulsante si accende', (await p.locator('#nlr-manca').count()) === 0 && !(await p.locator('#nlr-invio button.btn-primary').isDisabled()));

  // ===== Anteprima con i dati di un contatto, e prova alla lista dei test
  await vai(`newsletter-campagna.php?id=${c1}&contatto=t1@cr-prova.it`);
  const srcdoc = await p.locator('#nlr-anteprima iframe').getAttribute('srcdoc');
  ok('anteprima con i dati di t1: nome, città in maiuscolo, nessun pixel', /Ciao Tina, ecco/.test(srcdoc) && /api\/nl\.php\?a=a/.test(srcdoc) === false && /CR Ciao Tina da MILANO/.test(await p.locator('[data-nlr-oggetto]').innerText()), (await p.locator('[data-nlr-oggetto]').innerText()));
  await p.click('[data-nlr-largo=telefono]'); await p.waitForTimeout(500);
  ok('anteprima: Telefono stringe la cornice a 390 px', Math.round((await p.locator('#nlr-anteprima iframe').boundingBox()).width) === 390);
  await vai(`newsletter-campagna.php?id=${c1}&contatto=nessuno@cr-prova.it`);
  ok('anteprima con un indirizzo che non è un contatto: lo dice, e usa «Giulia»', /non è un contatto/.test(await p.locator('#nlr-contatto-aiuto').innerText()) && /Ciao Giulia/.test(await p.locator('#nlr-anteprima iframe').getAttribute('srcdoc')));
  await vai(`newsletter-campagna.php?id=${c1}&apri=prova`);
  ok('prova: la lista dei test è proposta e spuntata', (await p.locator('#nlc-prova input[name="prova_test[]"]:checked').count()) === 1);
  let d0 = da();
  await invia('#nlc-prova button[value=prova]');
  await p.waitForTimeout(300);
  let m = arrivate(d0);
  ok('prova arrivata solo a t1, con i suoi dati, oggetto [PROVA], campo A col suo nome', m.length === 1 && m[0].dest === 't1@cr-prova.it' && /^\[PROVA\] CR Ciao Tina da MILANO$/.test(m[0].oggetto) && /Tina Testa/.test(m[0].to) && /Ciao Tina/.test(m[0].html), m.map(x => [x.dest, x.oggetto, x.to]));
  ok('la prova non tocca le statistiche: niente pixel', m[0] && !/api\/nl\.php\?a=a/.test(m[0].html));

  // ===== Indirizzo dell'azienda nel piede
  await vai('newsletter.php?scheda=impostazioni#nl-indirizzo');
  await p.fill('#nl-indirizzo-testo', 'Studio CR\nvia Roma 1, 00100 Roma');
  await invia('#nl-indirizzo button[type=submit]');
  await vai('newsletter.php');
  ok('con l\'indirizzo scritto l\'avviso sparisce', (await p.locator('#nl-avviso-indirizzo').count()) === 0);

  // ===== Programma: data futura, non parte prima; poi all'ora
  await vai(`newsletter-campagna.php?id=${c1}`);
  await p.check('#nlr-invio input[name=quando][value=programma]');
  const futuro = new Date(Date.now() + 3 * 3600 * 1000);
  const loc = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}T${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
  await p.fill('#nlr-quando', loc(futuro));
  await invia('#nlr-invio button.btn-primary');
  ok('Programma: campagna programmata, il messaggio dice il fuso e "entro 5 minuti"', uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c1}`) === 'programmata' && /ora di Europe\/Rome/.test(await flash()) && /entro 5 minuti/.test(await flash()), await flash());
  cli('giro');
  ok('prima dell\'ora non parte', uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c1}`) === 'programmata' && num(`SELECT COUNT(*) FROM {p}newsletter_coda WHERE campagna_id = ${c1}`) === 0);
  // Il tempo passa: l'ora programmata adesso è un minuto fa.
  q(`UPDATE {p}newsletter_campagne SET invio_at = DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:00'), INTERVAL 1 MINUTE) WHERE id = ${c1}`);
  const ora = uno(`SELECT invio_at FROM {p}newsletter_campagne WHERE id = ${c1}`);
  d0 = da();
  cli('giro');
  const coda = q(`SELECT iscritto_id, lotto, dopo, stato FROM {p}newsletter_coda WHERE campagna_id = ${c1} ORDER BY id`);
  const piu30 = q(`SELECT DATE_ADD('${ora}', INTERVAL 30 MINUTE) AS x`)[0].x;
  ok('all\'ora parte: coda di 4, una riga a testa', coda.length === 4 && new Set(coda.map(r => r.iscritto_id)).size === 4, coda);
  ok('lotti: 2 lotti da 2, il primo all\'ora programmata, il secondo 30 minuti dopo', coda.filter(r => +r.lotto === 1).length === 2 && coda.filter(r => +r.lotto === 2).length === 2 && coda.filter(r => +r.lotto === 1).every(r => r.dopo === ora) && coda.filter(r => +r.lotto === 2).every(r => r.dopo === piu30), [ora, piu30, coda]);
  m = arrivate(d0);
  ok('primo giro: partono solo le 2 email del primo lotto', m.length === 2 && coda.filter(r => r.stato === 'inviata').length === 2, chi(m));
  cli('giro');
  ok('il secondo lotto aspetta la sua ora', da() - d0 === 2 && uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c1}`) === 'in_invio');
  q(`UPDATE {p}newsletter_coda SET dopo = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE campagna_id = ${c1} AND lotto = 2`);
  cli('giro');
  m = arrivate(d0);
  ok('esclusione che vince e doppioni: arrivano a a1, a2, a3, a7, una a testa', chi(m) === 'a1,a2,a3,a7', chi(m));
  ok('la campagna è inviata', uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c1}`) === 'inviata');
  const ma2 = m.find(x => x.dest === 'a2@cr-prova.it'), ma7 = m.find(x => x.dest === 'a7@cr-prova.it'), ma1 = m.find(x => x.dest === 'a1@cr-prova.it'), ma3 = m.find(x => x.dest === 'a3@cr-prova.it');
  ok('segnaposti: oggetto con nome e città in maiuscolo, valore predefinito per chi non ha il nome', ma2.oggetto === 'CR Ciao Bruno da ROMA' && ma7.oggetto === 'CR Ciao amico da ROMA' && /Ciao amico, ecco/.test(ma7.html), [ma2.oggetto, ma7.oggetto]);
  ok('campo A col nome e cognome, Rispondi a della campagna', /^Bruno Bianchi <a2@cr-prova\.it>$/.test(ma2.to) && /risposte@cr-prova\.it/.test(ma2.replyTo) && ma7.to === '<a7@cr-prova.it>', [ma2.to, ma2.replyTo, ma7.to]);
  ok('le intestazioni interne (campo A, testo) non partono', !ma2.interne);
  ok('piede: indirizzo dell\'azienda e link delle preferenze', /Studio CR<br>via Roma 1, 00100 Roma/.test(ma2.html) && /Aggiorna le tue preferenze/.test(ma2.html) && /newsletter-preferenze\/\?i=\d+&amp;t=[a-f0-9]{32}/.test(ma2.html));
  const linkWeb = href(ma2.html, '/newsletter-web/');
  ok('in cima il link «Vedi questa email nel browser», firmato', /Vedi questa email nel browser/.test(ma2.html) && /\/newsletter-web\/\?c=\d+&i=\d+&t=[a-f0-9]{24}$/.test(linkWeb), linkWeb);
  const clic = decodeURIComponent((ma2.html.match(/href="[^"]*api\/nl\.php\?a=c[^"]*u=([^"&]+)/) || [])[1] || '');
  ok('UTM sul link verso il sito (dentro il link tracciato)', /\/contatti\/\?utm_source=newsletter&utm_medium=email&utm_campaign=cr-prima$/.test(clic), clic);
  ok('testo semplice generato: c\'è, con il link per uscire e l\'indirizzo', /Ciao Bruno, ecco le novità/.test(ma2.testo) && /newsletter-esci\/\?i=/.test(ma2.testo) && /Studio CR/.test(ma2.testo), ma2.testo.slice(0, 300));

  // ===== Versione web
  const pw = await ctx.newPage();
  const rw = await pw.goto(linkWeb, { waitUntil: 'load' });
  ok('versione web: si apre (200) con l\'email di quel contatto, senza indicizzazione, in una pagina che non esegue niente', rw.status() === 200 && /Ciao Bruno, ecco/.test(await pw.content()) && /noindex/.test(rw.headers()['x-robots-tag'] || '') && /sandbox/.test(rw.headers()['content-security-policy'] || ''), rw.headers());
  ok('versione web: niente pixel di apertura', !/api\/nl\.php\?a=a/.test(await pw.content()));
  const rw2 = await pw.goto(linkWeb.replace(/t=[a-f0-9]{4}/, 't=0000'), { waitUntil: 'load' });
  const rw3 = await pw.goto(linkWeb.replace(/i=(\d+)/, (x, i) => 'i=' + (+i + 1)), { waitUntil: 'load' });
  ok('versione web con la firma sbagliata o di un altro contatto: pagina non trovata', rw2.status() === 404 && rw3.status() === 404, [rw2.status(), rw3.status()]);
  await pw.close();

  // ===== Rimanda ai non aperti: non per una campagna a lotti
  await vai(`newsletter.php?rimanda=${c1}`);
  ok('Rimanda ai non aperti: una campagna mandata a lotti non si rimanda', /a lotti non si rimanda/.test(await p.locator('#nl-rimanda').innerText()));

  // ===== Duplica con la scelta delle parti
  await vai(`newsletter.php?duplica=${c1}`);
  const parti = await p.locator('#nl-duplica input[name="parti[]"]').count();
  await p.uncheck('#nl-duplica input[value=impostazioni]');
  await invia('#nl-duplica button[type=submit]');
  const c2 = +new URL(p.url()).searchParams.get('id');
  const d2 = q(`SELECT nome, oggetto, liste, segmenti, escludi_liste, corpo, tag, lotti, utm, rispondi_a, stato, link_web FROM {p}newsletter_campagne WHERE id = ${c2}`)[0];
  ok('Duplica: cinque parti da scegliere; senza le impostazioni copia mittente, destinatari, oggetto, design', parti === 5 && d2.nome === 'Copia di CR Prima' && d2.stato === 'bozza' && /Ciao/.test(d2.oggetto) && d2.liste === `${L['CR Alfa']},${L['CR Beta']}` && d2.escludi_liste === String(L['CR Escl']) && /ecco le novità/.test(d2.corpo) && d2.tag === '' && +d2.lotti === 1 && +d2.utm === 0 && d2.rispondi_a === '' && +d2.link_web === 1, d2);
  // Invia subito
  await vai(`newsletter-campagna.php?id=${c2}`);
  ok('Invia subito è la scelta di partenza', await p.isChecked('#nlr-invio input[value=subito]'));
  await invia('#nlr-invio button.btn-primary');
  ok('Invia subito: programmata adesso', uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c2}`) === 'programmata' && /entro 5 minuti/.test(await flash()));
  d0 = da();
  cli('giro');
  m = arrivate(d0);
  ok('la copia arriva agli stessi 4', chi(m) === 'a1,a2,a3,a7' && uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c2}`) === 'inviata', chi(m));
  // a1 apre l'email (il pixel)
  const pixel = (m.find(x => x.dest === 'a1@cr-prova.it').html.match(/<img src="([^"]*api\/nl\.php\?a=a[^"]*)"/) || [])[1].replace(/&amp;/g, '&');
  const rpx = await ctx.request.get(pixel);
  // 2.11.0: anche con il sito in una cartella il pixel si apre (prima la cartella era due volte nell'indirizzo).
  ok('il pixel di apertura si apre (anche in cartella)', /image\/gif/.test(rpx.headers()['content-type'] || '') && !/\/([^/]+)\/\1\/api\/nl\.php/.test(pixel), pixel.replace(/\?.*/, ''));
  ok('a1 apre la copia (pixel)', num(`SELECT COUNT(*) FROM {p}newsletter_eventi WHERE campagna_id = ${c2} AND tipo = 'apertura' AND iscritto_id = ${C.a1}`) === 1);

  // ===== Rimanda ai non aperti, una volta sola
  await vai(`newsletter.php?rimanda=${c2}`);
  const rimTesto = await p.locator('#nl-rimanda').innerText();
  ok('Rimanda ai non aperti: arriva a 3 contatti (chi non l\'ha aperta)', /Arriva a 3 contatti/.test(rimTesto), rimTesto);
  await p.fill('#nl-rim-oggetto', 'CR Te la eri persa, {{ contact.NOME | default:"amico" }}?');
  await invia('#nl-rimanda button[type=submit]');
  const c3 = +new URL(p.url()).searchParams.get('id');
  ok('il rinvio nasce in bozza, legato alla prima, e la prima risulta rimandata', uno(`SELECT rinvio_di FROM {p}newsletter_campagne WHERE id = ${c3}`) == c2 && +uno(`SELECT rinvio_fatto FROM {p}newsletter_campagne WHERE id = ${c2}`) === 1 && /Rinvio ai non aperti di/.test(await p.locator('.nlr-stato-box').first().innerText()));
  const tkR = await p.locator('input[name=_csrf]').first().inputValue();
  await ctx.request.post(B + '/admin/newsletter.php', { form: { _csrf: tkR, azione: 'rinvio', id: String(c2), oggetto: 'CR di nuovo' } });
  ok('una volta sola: un secondo rinvio non nasce', num(`SELECT COUNT(*) FROM {p}newsletter_campagne WHERE rinvio_di = ${c2}`) === 1);
  await vai('newsletter.php');
  ok('nell\'elenco «Rimanda ai non aperti» non c\'è più per la prima', (await p.locator(`a[href="newsletter.php?rimanda=${c2}#nl-rimanda"]`).count()) === 0);
  await vai(`newsletter-campagna.php?id=${c3}`);
  await invia('#nlr-invio button.btn-primary');
  d0 = da();
  cli('giro');
  m = arrivate(d0);
  ok('il rinvio arriva solo ai non aperti (a2, a3, a7), con l\'oggetto nuovo', chi(m) === 'a2,a3,a7' && m.every(x => /^CR Te la eri persa/.test(x.oggetto)), [chi(m), m.map(x => x.oggetto)]);

  // ===== Invia ai nuovi contatti
  q(`INSERT INTO {p}newsletter_iscritti (email, nome, stato, fonte, creato, aggiornato) VALUES ('a9@cr-prova.it', 'Ivo', 'attivo', 'manuale', NOW(), NOW())`);
  const idA9 = num("SELECT id FROM {p}newsletter_iscritti WHERE email = 'a9@cr-prova.it'");
  q(`INSERT INTO {p}newsletter_iscritti_liste (iscritto_id, lista_id, aggiunto) VALUES (${idA9}, ${L['CR Alfa']}, NOW())`);
  await vai('newsletter.php');
  await p.click(`tr:has(a[href="newsletter-campagna.php?id=${c2}"]) details.nl-azioni > summary`);
  await invia(`tr:has(a[href="newsletter-campagna.php?id=${c2}"]) form:has(input[value=nuovi]) button`);
  const c4 = +new URL(p.url()).searchParams.get('id');
  ok('Invia ai nuovi contatti: una copia in bozza, che arriva solo al nuovo (a9)', uno(`SELECT padre_id FROM {p}newsletter_campagne WHERE id = ${c4}`) == c2 && cli('conta', c4) === 1 && /Copia per i nuovi contatti/.test(await p.locator('.nlr-stato-box').first().innerText()));
  await invia('#nlr-invio button.btn-primary');
  d0 = da(); cli('giro'); m = arrivate(d0);
  ok('la copia per i nuovi arriva solo ad a9', chi(m) === 'a9', chi(m));
  q(`INSERT INTO {p}newsletter_iscritti (email, nome, stato, fonte, creato, aggiornato) VALUES ('a10@cr-prova.it', 'Lia', 'attivo', 'manuale', NOW(), NOW())`);
  q(`INSERT INTO {p}newsletter_iscritti_liste (iscritto_id, lista_id, aggiunto) SELECT id, ${L['CR Alfa']}, NOW() FROM {p}newsletter_iscritti WHERE email = 'a10@cr-prova.it'`);
  const tkN = await token('newsletter.php');
  await ctx.request.post(B + '/admin/newsletter.php', { form: { _csrf: tkN, azione: 'nuovi', id: String(c2) } });
  const c5 = num(`SELECT MAX(id) FROM {p}newsletter_campagne WHERE padre_id = ${c2}`);
  ok('una seconda copia per i nuovi salta anche chi ha ricevuto la prima copia: solo a10', c5 > c4 && cli('conta', c5) === 1);

  // ===== Archivia, Annulla, Elimina solo in bozza
  await vai('newsletter.php');
  await p.click(`tr:has(a[href="newsletter-campagna.php?id=${c2}"]) details.nl-azioni > summary`);
  await invia(`tr:has(a[href="newsletter-campagna.php?id=${c2}"]) form:has(input[value=archivia]) button`);
  ok('Archivia: la campagna inviata esce dalla vista Tutte e sta in Archiviate', +uno(`SELECT archiviata FROM {p}newsletter_campagne WHERE id = ${c2}`) === 1 && (await p.locator(`a[href="newsletter-campagna.php?id=${c2}"]`).count()) === 0);
  await vai('newsletter.php?vista=archiviate');
  ok('vista Archiviate: c\'è', (await p.locator(`a[href="newsletter-campagna.php?id=${c2}"]`).count()) > 0);
  const viste = await p.locator('nav.nl-viste a').allInnerTexts();
  ok('le viste dell\'elenco con i numeri (Tutte, Bozze, Inviate, Archiviate...)', viste.some(v => /^Tutte/.test(v)) && viste.some(v => /^Bozze\s+\d/.test(v)) && viste.some(v => /^Inviate\s+\d/.test(v)) && viste.some(v => /^Archiviate\s+1/.test(v)), viste);
  const tkA = await token('newsletter.php');
  await ctx.request.post(B + '/admin/newsletter.php', { form: { _csrf: tkA, azione: 'archivia', id: String(c5) } });
  ok('una bozza non si archivia', +uno(`SELECT archiviata FROM {p}newsletter_campagne WHERE id = ${c5}`) === 0);
  await ctx.request.post(B + '/admin/newsletter.php', { form: { _csrf: tkA, azione: 'elimina', id: String(c1) } });
  ok('una campagna inviata non si elimina', num(`SELECT COUNT(*) FROM {p}newsletter_campagne WHERE id = ${c1}`) === 1);
  await vai('newsletter.php');
  await p.click(`tr:has(a[href="newsletter-campagna.php?id=${c5}"]) details.nl-azioni > summary`);
  await invia(`tr:has(a[href="newsletter-campagna.php?id=${c5}"]) form:has(input[value=elimina]) button`);
  ok('Elimina: la bozza sparisce (con la conferma rossa)', num(`SELECT COUNT(*) FROM {p}newsletter_campagne WHERE id = ${c5}`) === 0 && /Bozza eliminata/.test(await flash()));
  // Annulla una programmata
  const c6 = (() => { q(`INSERT INTO {p}newsletter_campagne (nome, oggetto, corpo, liste, stato, invio_at, link_web, link_preferenze, creato, aggiornato) VALUES ('CR Da annullare', 'CR Da annullare', '<p>x</p>', '${L['CR Alfa']}', 'programmata', DATE_ADD(NOW(), INTERVAL 1 DAY), 1, 1, NOW(), NOW())`); return num("SELECT MAX(id) FROM {p}newsletter_campagne WHERE nome = 'CR Da annullare'"); })();
  await vai(`newsletter-campagna.php?id=${c6}`);
  await invia('#nlr-invio form:has(input[value=annulla]) button');
  ok('Annulla la campagna programmata: stato Annullata, non parte più', uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${c6}`) === 'annullata');
  await vai('newsletter.php?vista=annullate');
  ok('vista Annullate', (await p.locator(`a[href="newsletter-campagna.php?id=${c6}"]`).count()) > 0);

  // ===== Testo semplice scritto a mano e codice HTML
  await vai('newsletter.php?crea=1'); await p.fill('#nl-crea-nome', 'CR Codice'); await invia('#nl-crea button[type=submit]');
  const c7 = +new URL(p.url()).searchParams.get('id');
  q(`UPDATE {p}newsletter_campagne SET liste = '${L['CR Beta']}', oggetto = 'CR Codice' WHERE id = ${c7}`);
  await vai(`newsletter-campagna.php?id=${c7}&apri=design`);
  await p.locator('#editor .ql-editor').click(); await p.keyboard.type('Testo di partenza per il codice.');
  await invia('#nl-form button[type=submit]');
  await vai(`newsletter-campagna.php?id=${c7}&apri=design`);
  // 2.12.0: le cinque strade del design sono riquadri con il loro pulsante (prima: una scelta e "Usa questo editor").
  await invia('#sez-design form.nd-strada:has(input[name=editor][value=html]) button');
  const partenza = await p.inputValue('#nlr-html');
  ok('passando al codice HTML si parte dall\'email dell\'editor semplice, con {{ unsubscribe }}', /Testo di partenza per il codice/.test(partenza) && /\{\{ unsubscribe \}\}/.test(partenza) && /<!DOCTYPE html>/.test(partenza));
  await p.fill('#nlr-html', '<html><body><h1>Ciao {{ contact.NOME | default:"amico" }}</h1><script>alert(1)</script><p><a href="{{ mirror }}">Web</a></p></body></html>');
  await p.click('#sez-design details.pn-aiuto > summary');
  await p.check('#sez-design input[name=testo_modificato]');
  await p.fill('#nlr-testo', 'Ciao {{ contact.NOME | default:"amico" }}, versione in testo.');
  await invia('#nl-form button[type=submit]');
  await vai(`newsletter-campagna.php?id=${c7}`);
  await invia('#nlr-invio button.btn-primary');
  d0 = da(); cli('giro'); m = arrivate(d0);
  const mc = m.find(x => x.dest === 'a3@cr-prova.it');
  ok('codice HTML: arriva a Beta senza a4 (nessuna esclusione qui: a3 e a4)', chi(m) === 'a3,a4', chi(m));
  ok('codice HTML: segnaposti, niente script, versione web al posto di {{ mirror }}, link per uscire aggiunto in fondo', mc && /<h1>Ciao Carla<\/h1>/.test(mc.html) && !/<script/i.test(mc.html) && /href="[^"]*newsletter-web\/\?c=/.test(mc.html) && /Esci dalla newsletter<\/a>, basta un clic/.test(mc.html) && /Studio CR/.test(mc.html), mc && mc.html.slice(0, 600));
  ok('testo semplice scritto a mano: con il nome, e il link per uscire aggiunto', mc && /^Ciao Carla, versione in testo\./.test(mc.testo) && /Esci dalla newsletter: http[^\s]*newsletter-esci\/\?i=/.test(mc.testo), mc && mc.testo);

  // ===== Disiscrizione con il questionario
  const uscitaA3 = href(ma3.html, '/newsletter-esci/');
  const pu = await ctx.newPage();
  await pu.goto(uscitaA3, { waitUntil: 'networkidle' });
  ok('pagina di uscita: chiede conferma con il pulsante, e offre le preferenze', /Vuoi uscire dalla newsletter/.test(await pu.content()) && (await pu.locator('a[href*="newsletter-preferenze"]').count()) === 1);
  ok('aprire il link non fa uscire (i filtri antispam aprono i link)', uno(`SELECT stato FROM {p}newsletter_iscritti WHERE id = ${C.a3}`) === 'attivo');
  await Promise.all([pu.waitForNavigation(), pu.click('button:has-text("toglimi")')]);
  ok('dopo il pulsante: fuori, lista nera con il motivo «dal link dell\'email»', uno(`SELECT CONCAT(stato, ' ', blocco_motivo) FROM {p}newsletter_iscritti WHERE id = ${C.a3}`) === 'uscito unsubscribedViaEmail');
  ok('il questionario con i cinque motivi', (await pu.locator('.nl-questionario input[name=motivo]').count()) === 5 && /Ci dici perché/.test(await pu.content()));
  if (FOTO) await pu.screenshot({ path: path.join(FOTO, 'uscita-questionario.png'), fullPage: true });
  await pu.check('.nl-questionario input[value=troppe]');
  await Promise.all([pu.waitForNavigation(), pu.click('.nl-questionario button')]);
  const mot = q(`SELECT motivo, campagna_id FROM {p}newsletter_uscite_motivi WHERE contatto_id = ${C.a3}`);
  ok('il motivo è salvato, con la campagna da cui è uscito; la pagina ringrazia', mot.length === 1 && mot[0].motivo === 'troppe' && +mot[0].campagna_id === c1 && /Grazie/.test(await pu.content()), mot);
  ok('l\'uscita conta nelle statistiche della campagna', num(`SELECT COUNT(*) FROM {p}newsletter_eventi WHERE campagna_id = ${c1} AND tipo = 'uscita' AND iscritto_id = ${C.a3}`) === 1);
  // a7: "Altro" con del testo
  await pu.goto(href(ma7.html, '/newsletter-esci/'), { waitUntil: 'networkidle' });
  await Promise.all([pu.waitForNavigation(), pu.click('button:has-text("toglimi")')]);
  await pu.check('.nl-questionario input[value=altro]');
  await pu.fill('#nl-altro', 'Cambio <b>lavoro</b>');
  await Promise.all([pu.waitForNavigation(), pu.click('.nl-questionario button')]);
  ok('«Altro» con il testo scritto (senza HTML)', uno(`SELECT altro FROM {p}newsletter_uscite_motivi WHERE contatto_id = ${C.a7}`) === 'Cambio lavoro');
  await vai('newsletter-statistiche.php');
  const st = await p.locator('#st-motivi').innerText();
  ok('Statistiche: i motivi delle uscite, con le risposte «Altro»', /Ricevo troppe email\s+1/.test(st) && /Altro\s+1/.test(st) && /Cambio lavoro/.test(st), st);
  await vai(`newsletter-campagna.php?id=${c1}`);
  ok('il report della campagna dice perché sono usciti', /Ricevo troppe email: 1/.test(await p.locator('#nlr-report').innerText()));
  // Uscita in un clic dei programmi di posta (List-Unsubscribe-Post): POST senza motivo
  const ru = await ctx.request.post(href(ma1.html, '/newsletter-esci/'), { form: { 'List-Unsubscribe': 'One-Click' } });
  ok('uscita in un clic (List-Unsubscribe-Post): fuori', ru.status() === 200 && uno(`SELECT stato FROM {p}newsletter_iscritti WHERE id = ${C.a1}`) === 'uscito');

  // ===== Centro preferenze
  await vai('newsletter.php?scheda=impostazioni#nl-pagine');
  for (const l of ['CR Alfa', 'CR Pubblica']) await p.check(`#nl-pagine input[name="pubbliche[]"][value="${L[l]}"]`);
  await p.fill('#pg-pt', 'Cosa vuoi ricevere');
  await invia('#nl-pagine button[type=submit]');
  ok('Impostazioni: liste del centro preferenze e titolo salvati', num(`SELECT pubblica FROM {p}newsletter_liste WHERE id = ${L['CR Alfa']}`) === 1 && num(`SELECT pubblica FROM {p}newsletter_liste WHERE id = ${L['CR Beta']}`) === 0 && /Cosa vuoi ricevere/.test(uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_pagine'") || ''));
  const prefA2 = href(ma2.html, '/newsletter-preferenze/');
  const pp = await ctx.newPage();
  await pp.goto(prefA2, { waitUntil: 'networkidle' });
  const pt = await pp.content();
  ok('centro preferenze: titolo scritto in Impostazioni, nome, liste pubbliche (Alfa spuntata, Pubblica no, Beta nascosta)', /Cosa vuoi ricevere/.test(pt) && await pp.locator(`input[name="liste[]"][value="${L['CR Alfa']}"]`).isChecked() && !(await pp.locator(`input[name="liste[]"][value="${L['CR Pubblica']}"]`).isChecked()) && (await pp.locator(`input[name="liste[]"][value="${L['CR Beta']}"]`).count()) === 0 && await pp.inputValue('#nlp-nome') === 'Bruno');
  if (FOTO) await pp.screenshot({ path: path.join(FOTO, 'preferenze.png'), fullPage: true });
  await pp.uncheck(`input[name="liste[]"][value="${L['CR Alfa']}"]`);
  await pp.check(`input[name="liste[]"][value="${L['CR Pubblica']}"]`);
  await pp.fill('#nlp-nome', 'Brunone');
  await Promise.all([pp.waitForNavigation(), pp.click('form.nl-preferenze button')]);
  ok('salvate: esce dalla sola lista Alfa (resta dentro, con l\'uscita), entra in Pubblica, nome cambiato', /Preferenze salvate/.test(await pp.content()) && num(`SELECT COUNT(*) FROM {p}newsletter_liste_uscite WHERE contatto_id = ${C.a2} AND lista_id = ${L['CR Alfa']}`) === 1 && num(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE iscritto_id = ${C.a2} AND lista_id = ${L['CR Pubblica']}`) === 1 && uno(`SELECT nome FROM {p}newsletter_iscritti WHERE id = ${C.a2}`) === 'Brunone' && uno(`SELECT stato FROM {p}newsletter_iscritti WHERE id = ${C.a2}`) === 'attivo');
  // Campagna ad Alfa: a2 non la riceve più
  const alfa = (nome) => { q(`INSERT INTO {p}newsletter_campagne (nome, oggetto, corpo, liste, stato, invio_at, link_web, link_preferenze, creato, aggiornato) VALUES ('${nome}', '${nome}', '<p>Solo Alfa</p>', '${L['CR Alfa']}', 'programmata', NOW(), 1, 1, NOW(), NOW())`); return num(`SELECT MAX(id) FROM {p}newsletter_campagne WHERE nome = '${nome}'`); };
  alfa('CR Solo Alfa 1');
  d0 = da(); cli('giro'); m = arrivate(d0);
  ok('una campagna alla lista Alfa non arriva più ad a2 (uscito da quella lista)', !chi(m).split(',').includes('a2') && chi(m).split(',').includes('a9'), chi(m));
  // Rientra
  await pp.goto(prefA2, { waitUntil: 'networkidle' });
  await pp.check(`input[name="liste[]"][value="${L['CR Alfa']}"]`);
  await Promise.all([pp.waitForNavigation(), pp.click('form.nl-preferenze button')]);
  ok('rientra nella lista Alfa: l\'uscita si toglie', num(`SELECT COUNT(*) FROM {p}newsletter_liste_uscite WHERE contatto_id = ${C.a2} AND lista_id = ${L['CR Alfa']}`) === 0);
  alfa('CR Solo Alfa 2');
  d0 = da(); cli('giro'); m = arrivate(d0);
  ok('e la riceve di nuovo', chi(m).split(',').includes('a2'), chi(m));
  // Lista non pubblica e campi non consentiti: ignorati
  const rp = await ctx.request.post(prefA2, { form: { azione: 'salva', 'liste[]': [String(L['CR Alfa']), String(L['CR Privata'])], 'a[EMAIL]': 'rubata@cattivo.it', 'a[CITTA]': 'Atlantide', 'a[NOME]': 'Bruno' } });
  ok('centro preferenze: lista non pubblica, email e attributi non consentiti ignorati', rp.status() === 200 && num(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE iscritto_id = ${C.a2} AND lista_id = ${L['CR Privata']}`) === 0 && uno(`SELECT email FROM {p}newsletter_iscritti WHERE id = ${C.a2}`) === 'a2@cr-prova.it' && uno(`SELECT v_testo FROM {p}newsletter_valori v JOIN {p}newsletter_attributi a ON a.id = v.attributo_id WHERE a.nome = 'CITTA' AND v.contatto_id = ${C.a2}`) === 'Roma');
  const rf = await ctx.request.get(prefA2.replace(/i=\d+/, 'i=' + C.a1));
  ok('centro preferenze con la firma di un altro contatto: link non valido', /non è valido/.test(await rf.text()) && !/a1@cr-prova/.test(await rf.text()));
  // Esci da tutto
  await pp.goto(prefA2, { waitUntil: 'networkidle' });
  await Promise.all([pp.waitForNavigation(), pp.click('button:has-text("Esci da tutte le email")')]);
  ok('Esci da tutte le email: lista nera con il motivo giusto, e la pagina col questionario', uno(`SELECT CONCAT(stato, ' ', blocco_motivo) FROM {p}newsletter_iscritti WHERE id = ${C.a2}`) === 'uscito unsubscribedViaEmail' && /newsletter-esci/.test(pp.url()) && (await pp.locator('.nl-questionario').count()) === 1, pp.url());
  await pp.goto(prefA2, { waitUntil: 'networkidle' });
  ok('dopo l\'uscita il centro preferenze offre «Iscrivimi di nuovo» (con la conferma per email)', (await pp.locator('button:has-text("Iscrivimi di nuovo")').count()) === 1);
  d0 = da();
  await Promise.all([pp.waitForNavigation(), pp.click('button:has-text("Iscrivimi di nuovo")')]);
  await p.waitForTimeout(300);
  ok('Iscrivimi di nuovo: parte l\'email di conferma, e resta fuori finché non conferma', arrivate(d0).some(x => x.dest === 'a2@cr-prova.it' && /newsletter-conferma/.test(x.html)) && uno(`SELECT stato FROM {p}newsletter_iscritti WHERE id = ${C.a2}`) === 'uscito');

  // ===== Telefono: 390 px senza scorrimento di lato
  const tel = await br.newContext({ viewport: { width: 390, height: 800 }, storageState: await ctx.storageState() });
  const pt3 = await tel.newPage();
  for (const u of ['/admin/newsletter.php', `/admin/newsletter-campagna.php?id=${c1}&apri=destinatari`, `/admin/newsletter-campagna.php?id=${c7}&apri=impostazioni`, '/admin/newsletter.php?scheda=impostazioni', '/admin/newsletter-statistiche.php', uscitaA3, prefA2]) {
    await pt3.goto(u.startsWith('http') ? u : B + u, { waitUntil: 'networkidle' });
    const lato = await pt3.evaluate(() => document.documentElement.scrollWidth - innerWidth);
    ok(`390 px senza scorrimento di lato: ${u.replace(B, '').replace(/\?.*t=.*/, '?...')}`, lato <= 0, lato);
  }
  if (FOTO) { await pt3.goto(B + `/admin/newsletter-campagna.php?id=${c1}`, { waitUntil: 'networkidle' }); await pt3.screenshot({ path: path.join(FOTO, 'riepilogo-390.png'), fullPage: true }); }
  await tel.close();

  ok('nessun errore JavaScript nelle pagine', err.length === 0, err);
  console.log(`\n${n - ko} su ${n} OK`);
  await br.close();
  process.exit(ko ? 1 : 0);
})().catch(e => { console.log('KO  eccezione: ' + e.message.split('\n')[0]); console.log(`\n${n - ko} su ${n + 1} OK`); process.exit(1); });
