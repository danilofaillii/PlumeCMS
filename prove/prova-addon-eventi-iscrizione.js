// L'addon Eventi con iscrizione (2.0.0) provato come un'attività e come chi si iscrive, nel browser.
// Su un sito nuovo (kit Attività) con il server di posta finto acceso (python3 -I prove/smtp-finto.py <porta> <cartella>):
// accende l'addon dal pannello, scrive un evento con 3 posti (2 al massimo per persona) e lista d'attesa, lo apre come un
// visitatore (pagina, dati per Google, widget dell'elenco e del modulo), si iscrive al computer e al telefono, riempie
// l'evento, entra in lista d'attesa, si cancella dal link dell'email (il primo in attesa entra e riceve l'email), presenze,
// CSV (anche con un nome che Excel leggerebbe come formula), più posti dal pannello, "Scrivi a tutti", promemoria una volta
// sola, e alla fine spegne l'addon: le pagine del sito tornano identiche.
// uso: node prove/prova-addon-eventi-iscrizione.js <base> <cartella del CMS installato> <porta smtp> <cartella posta>
const { chromium } = require('playwright');
const C = require('./addon-b-comune.js');
const { ok } = C;
const [B, CMS, SMTP, POSTA] = process.argv.slice(2);
const PAG = p => B + '/admin/addon-pagina.php?a=eventi-iscrizione&p=' + p;
const norma = h => h.replace(/nonce="[^"]+"/g, '').replace(/name="_csrf" value="[^"]+"/g, '').replace(/\?v=\d+/g, '').replace(/data-ew-orari="[^"]*"/g, '').replace(/<span class="ew-orari-frase">[^<]*/g, '');
const locale = d => { const z = n => String(n).padStart(2, '0'); return `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}T${z(d.getHours())}:${z(d.getMinutes())}`; };

(async () => {
  const br = await chromium.launch();
  const err = [];
  const a = await C.pagina(br, err);
  const vai = (p, u) => p.goto(u.startsWith('http') ? u : B + u, { waitUntil: 'networkidle' });
  await C.entra(a, B);
  await C.postaFinta(a, B, SMTP);
  const PAGINE = ['/', '/servizi/', '/contatti/'];
  const prima = {};
  for (const u of PAGINE) prima[u] = norma(await (await a.request.get(B + u)).text());
  ok('spento: la pagina dell\'addon nel pannello dà 404', (await a.goto(PAG('eventi'))).status() === 404);

  const f1 = await C.addon(a, B, 'eventi-iscrizione', true);
  ok('accendo da Impostazioni > Addon', /Eventi con iscrizione» è acceso/.test(f1) && /tabelle a posto/.test(f1), f1);
  ok('nel menu compare Eventi con Iscritti e Impostazioni', await a.locator('nav.admin-nav a', { hasText: /^\s*Iscritti\s*$/ }).count() === 1);
  await vai(a, PAG('impostazioni'));
  await a.fill('[name=avviso_a]', 'eventi@esempio.it');
  await Promise.all([a.waitForNavigation(), a.click('#evi-impostazioni button.btn-primary')]);
  ok('impostazioni salvate', /salvate/.test(await a.locator('.flash').first().innerText()));

  // 1. L'evento, dal pannello.
  const inizio = new Date(Date.now() + 5 * 86400000); inizio.setHours(21, 0, 0, 0);
  await vai(a, PAG('eventi') + '&nuovo=1');
  await a.fill('#evi-evento [name=titolo]', 'Serata di degustazione');
  await a.fill('[name=inizio]', locale(inizio));
  await a.fill('[name=luogo]', 'In negozio'); await a.fill('[name=indirizzo]', 'Via Rizzoli 1, Bologna'); await a.fill('[name=prezzo]', '15 € a persona');
  await a.fill('[name=descrizione]', 'Assaggiamo insieme i vini dei colli bolognesi.\n\nPosti limitati. <script>alert(1)</script>');
  await a.fill('[name=posti]', '3'); await a.fill('[name=max_persona]', '2');
  await a.selectOption('[name=stato]', 'pubblicato');
  await Promise.all([a.waitForNavigation(), a.click('#evi-evento button.btn-primary')]);
  const fl = await a.locator('.flash').first().innerText();
  ok('evento salvato e pubblicato: il messaggio dice l\'indirizzo della pagina', /\/evento\/serata-di-degustazione\//.test(fl), fl);
  const eid = +new URL(a.url()).searchParams.get('id');

  // 2. La pagina dell'evento, come un visitatore.
  const v = await C.pagina(br, err);
  const pagEv = '/evento/serata-di-degustazione/';
  const r = await vai(v, pagEv);
  ok('la pagina dell\'evento: 200, un solo h1, data, luogo, prezzo e "3 posti liberi"', r.status() === 200 && await v.locator('h1').count() === 1 && /Serata di degustazione/.test(await v.locator('h1').innerText()) && /3 posti liberi/.test(await v.locator('.evi-scheda').first().innerText()) && /Via Rizzoli 1/.test(await v.locator('.evi-scheda').first().innerText()));
  ok('il testo scritto con <script> esce come testo, non come codice', /<script>alert\(1\)<\/script>/.test(await v.locator('.evi-descrizione').innerText()) && await v.locator('.evi-descrizione script').count() === 0);
  const ldTesto = (await v.$$eval('script[type="application/ld+json"]', s => s.map(x => x.textContent))).find(x => /"@type":"Event"/.test(x)) || '{}';
  const ld = JSON.parse(ldTesto);
  ok('dati per Google: Event con inizio nel fuso del sito, luogo, organizzatore, posti', ld['@type'] === 'Event' && /^\d{4}-\d{2}-\d{2}T21:00:00\+0[12]:00$/.test(ld.startDate) && ld.location.address === 'Via Rizzoli 1, Bologna' && ld.maximumAttendeeCapacity === 3 && ld.eventStatus === 'https://schema.org/EventScheduled', ld);
  ok('i dati per Google proteggono i < (JSON_HEX_TAG)', !/<script>alert/.test(ldTesto) && /\\u003Cscript/.test(ldTesto));

  // 3. I due widget, in due pagine (come le farebbe l'editor).
  C.cli(CMS, 'pagina', 'eventi-in-programma', 'Eventi in programma', 'eventi_iscrizione');
  C.cli(CMS, 'pagina', 'iscriviti-serata', 'Iscriviti alla serata', 'evento_iscrizione', JSON.stringify({ evento: String(eid) }));
  await vai(v, '/eventi-in-programma/');
  ok('widget Eventi con iscrizione: l\'evento con i posti rimasti e il link alla sua pagina', /3 posti liberi/.test(await v.locator('.ew-eventi').innerText()) && await v.locator(`.ew-eventi a[href$="${pagEv}"]`).count() === 1);
  await vai(v, '/iscriviti-serata/');
  ok('widget Iscrizione all\'evento: riquadro e modulo', await v.locator('.evi form[data-captcha]').count() === 1 && /3 posti liberi/.test(await v.locator('.evi-scheda').innerText()));
  await vai(a, '/admin/editor.php?id=' + C.cli(CMS, 'sql', "SELECT id FROM {p}pages WHERE slug = 'iscriviti-serata'")[0].id);
  ok('i due widget sono nella tavolozza dell\'editor', await a.locator('.ed-widget-voce[data-tipo=evento_iscrizione]').count() === 1 && await a.locator('.ed-widget-voce[data-tipo=eventi_iscrizione]').count() === 1);

  // 4. Iscrizioni.
  const iscrivi = async (p, url, nome, email, posti) => {
    await vai(p, url);
    const f = p.locator('.evi form').first();
    await f.locator('[name=nome]').fill(nome); await f.locator('[name=email]').fill(email);
    if (posti) await f.locator('[name=posti]').selectOption(String(posti));
    await f.locator('[name=privacy]').check();
    await C.captcha(f, p);
    await Promise.all([p.waitForNavigation(), f.locator('button[type=submit]').click()]);
  };
  const vecchia = await C.pagina(br, err);
  await vai(vecchia, '/iscriviti-serata/'); // aperta adesso, con 3 posti liberi
  let quante = C.posta(POSTA).length;
  await iscrivi(v, pagEv, 'Giulia Rossi', 'giulia@cliente.it', 2);
  ok('mi iscrivo con 2 posti: "Sei iscritto"', /\/evento-iscrizione\/gestisci\//.test(v.url()) && /Sei iscritto/.test(await v.locator('.evi-ok').innerText()));
  let em = await C.nuove(POSTA, quante, 2);
  const g1 = em.find(e => e.a === 'giulia@cliente.it');
  ok('email "Iscrizione confermata" con il link personale e il calendario', g1 && /Iscrizione confermata: Serata di degustazione/.test(g1.oggetto) && C.link(g1).some(l => /gestisci\/\?i=\d+&t=[a-f0-9]{40}/.test(l)) && C.link(g1).some(l => /calendario/.test(l)), g1 && g1.oggetto);
  ok('avviso all\'attività "Nuova iscrizione" con i posti presi', em.some(e => e.a === 'eventi@esempio.it' && /Nuova iscrizione: Giulia Rossi/.test(e.oggetto) && /2 su 3/.test(e.html)));
  const ics = await (await v.request.get(C.link(g1).find(l => /calendario/.test(l)))).text();
  ok('il file .ics: evento, inizio in UTC, luogo', /BEGIN:VEVENT[\s\S]*DTSTART:\d{8}T\d{6}Z[\s\S]*SUMMARY:Serata di degustazione/.test(ics) && /LOCATION:In negozio\\, Via Rizzoli 1\\, Bologna/.test(ics));
  // Chi aveva la pagina aperta da prima (con 2 posti nella tendina) e ne chiede 2: "resta un solo posto".
  const fForm = vecchia.locator('.evi form').first();
  await fForm.locator('[name=nome]').fill('Paolo Verdi'); await fForm.locator('[name=email]').fill('paolo@cliente.it'); await fForm.locator('[name=posti]').selectOption('2');
  await fForm.locator('[name=privacy]').check(); await C.captcha(fForm, vecchia);
  await Promise.all([vecchia.waitForNavigation(), fForm.locator('button[type=submit]').click()]);
  ok('pagina vecchia, 2 posti chiesti con 1 libero: "Resta un solo posto"', /Resta un solo posto/.test(await vecchia.locator('.modulo-errore').innerText().catch(() => '')));
  // Al telefono: la tendina offre solo 1 posto.
  const t = await C.pagina(br, err, { telefono: true });
  await vai(t, pagEv);
  ok('telefono: la pagina dell\'evento non scorre di lato', await C.largo(t) <= 0, await C.largo(t));
  ok('telefono: con un posto solo libero, la tendina offre solo 1', (await t.locator('.evi [name=posti] option').count()) === 1);
  await iscrivi(t, pagEv, 'Sara Neri', 'sara@cliente.it', 0);
  ok('telefono: iscritta con 1 posto', /Sei iscritto/.test(await t.locator('.evi-ok').innerText()) && await C.largo(t) <= 0);
  ok('evento pieno: 3 posti su 3', +C.cli(CMS, 'sql', `SELECT SUM(posti) n FROM {p}evi_iscrizioni WHERE evento_id = ${eid} AND stato = 'confermata'`)[0].n === 3);
  // La stessa email due volte.
  await iscrivi(vecchia, '/iscriviti-serata/', 'Giulia Rossi', 'giulia@cliente.it', 1);
  ok('la stessa email due volte: "sei già iscritto"', /sei già iscritto/.test(await vecchia.locator('.modulo-errore').innerText().catch(() => '')), vecchia.url());
  // Lista d'attesa.
  await vai(v, pagEv);
  ok('pieno: la pagina dice "Al completo" e il pulsante "Mettimi in lista d\'attesa"', /Al completo/i.test(await v.locator('.evi-scheda').first().innerText()) && /lista d.attesa/i.test(await v.locator('.evi button[type=submit]').innerText()));
  quante = C.posta(POSTA).length;
  await iscrivi(v, pagEv, 'Luca Bianchi', 'luca@cliente.it', 1);
  ok('in lista d\'attesa, al posto numero 1', /lista d.attesa, al posto numero 1/.test(await v.locator('.evi-ok').innerText()));
  em = await C.nuove(POSTA, quante, 1);
  ok('email "Sei in lista d\'attesa"', em.some(e => e.a === 'luca@cliente.it' && /lista d.attesa/.test(e.oggetto)));
  await iscrivi(v, pagEv, '=HYPERLINK("http://x")', 'marco@cliente.it', 2);
  ok('la seconda in attesa (2 posti) è al posto 2', /al posto numero 2/.test(await v.locator('.evi-ok').innerText()));

  // 5. Giulia si cancella dal link dell'email: entra Luca (1 posto), Marco (2) aspetta ancora.
  quante = C.posta(POSTA).length;
  await vai(v, C.link(g1).find(l => /gestisci/.test(l)));
  await v.click('a:has-text("Cancella l\'iscrizione")');
  await Promise.all([v.waitForNavigation(), v.click('button:has-text("Sì, cancellami")')]);
  ok('cancellata dal link personale', /cancellata/.test(await v.locator('.evi-ok').innerText()));
  em = await C.nuove(POSTA, quante, 3);
  ok('a Giulia l\'email di cancellazione, a Luca "Si è liberato un posto"', em.some(e => e.a === 'giulia@cliente.it' && /cancellata/.test(e.oggetto)) && em.some(e => e.a === 'luca@cliente.it' && /Si è liberato un posto/.test(e.oggetto)), em.map(e => e.a + ' ' + e.oggetto));
  ok('Marco (2 posti) resta in attesa: non ci sta', C.cli(CMS, 'sql', `SELECT stato FROM {p}evi_iscrizioni WHERE email = 'marco@cliente.it'`)[0].stato === 'attesa');
  const rotto = C.link(g1).find(l => /gestisci/.test(l)).replace(/t=([a-f0-9])/, (m, c) => 't=' + (c === 'a' ? 'b' : 'a'));
  ok('un link manomesso: "Questo link non funziona"', (await v.goto(rotto)).status() === 404);

  // 6. Il pannello: iscritti, presenze, CSV, più posti, scrivi a tutti.
  await vai(a, PAG('iscritti') + '&e=' + eid);
  ok('Iscritti: "2 su 3 posti", 1 in lista d\'attesa', /2 su 3/.test(await a.locator('#evi-totali').innerText()) && /1<\/b> in lista|1 in lista/.test(await a.locator('#evi-totali').innerHTML()));
  const rigaSara = a.locator('tr', { hasText: 'sara@cliente.it' });
  await Promise.all([a.waitForNavigation(), rigaSara.locator('button[value=presente]').click()]);
  ok('segno Sara presente', await a.locator('tr', { hasText: 'sara@cliente.it' }).locator('button[value=presente].btn-primary').count() === 1);
  const csv = await (await a.request.get(PAG('iscritti') + '&e=' + eid + '&csv=1')).text();
  ok('CSV: tutti gli iscritti, con la presenza, e il nome-formula disinnescato', /"?Sara Neri"?;sara@cliente\.it;;1;Iscritto;sì/.test(csv) && /'=HYPERLINK/.test(csv) && !/;=HYPERLINK/.test(csv), csv.slice(0, 300));
  await vai(a, PAG('eventi') + '&id=' + eid);
  await a.fill('[name=posti]', '5');
  quante = C.posta(POSTA).length;
  await Promise.all([a.waitForNavigation(), a.click('#evi-evento button.btn-primary')]);
  ok('più posti: "1 dalla lista d\'attesa sono entrati"', /1 dalla lista d.attesa/.test(await a.locator('.flash').first().innerText()));
  em = await C.nuove(POSTA, quante, 1);
  ok('a Marco "Si è liberato un posto"', em.some(e => e.a === 'marco@cliente.it' && /Si è liberato un posto/.test(e.oggetto)));
  await vai(a, PAG('iscritti') + '&e=' + eid);
  await a.fill('#evi-scrivi [name=oggetto]', 'Cambio di orario');
  await a.fill('#evi-scrivi [name=messaggio]', 'La serata comincia alle 21:30.\nPortate un amico.');
  quante = C.posta(POSTA).length;
  await Promise.all([a.waitForNavigation(), a.click('#evi-scrivi button')]);
  ok('Scrivi a tutti: "Email mandate: 3"', /Email mandate: 3/.test(await a.locator('.flash').first().innerText()));
  em = await C.nuove(POSTA, quante, 3);
  ok('il messaggio arriva ai tre iscritti, con il loro nome', em.filter(e => /Cambio di orario/.test(e.oggetto) && /21:30/.test(e.html)).length === 3 && em.some(e => e.a === 'luca@cliente.it' && /Ciao Luca/.test(e.html)));
  const at = await C.pagina(br, err, { telefono: true });
  await C.entra(at, B);
  await vai(at, PAG('iscritti') + '&e=' + eid);
  ok('Iscritti al telefono: niente scorrimento di lato della pagina', await C.largo(at) <= 0, await C.largo(at));
  const larghe = [];
  for (const u of ['eventi', 'eventi&id=' + eid, 'eventi&nuovo=1', 'impostazioni']) { await vai(at, PAG(u)); if (await C.largo(at) > 0) larghe.push(u); }
  ok('le altre pagine del pannello al telefono: nessuna scorre di lato', larghe.length === 0, larghe);

  // 7. Promemoria il giorno prima, una volta sola (l'orologio del cron spostato a 12 ore prima).
  C.cli(CMS, 'sql', `UPDATE {p}evi_iscrizioni SET creato = DATE_SUB(creato, INTERVAL 10 DAY), entrato_at = IF(entrato_at IS NULL, NULL, DATE_SUB(entrato_at, INTERVAL 10 DAY)) WHERE evento_id = ${eid}`);
  const ev = C.cli(CMS, 'sql', `SELECT inizio_ts FROM {p}evi_eventi WHERE id = ${eid}`)[0];
  const avanti = ev.inizio_ts - 12 * 3600 - Math.floor(Date.now() / 1000);
  quante = C.posta(POSTA).length;
  const c1 = C.cli(CMS, 'cron', String(avanti)); const c2 = C.cli(CMS, 'cron', String(avanti + 60));
  em = await C.nuove(POSTA, quante, 3);
  ok('promemoria ai tre iscritti, una volta sola', em.filter(e => /Promemoria: Serata/.test(e.oggetto)).length === 3 && /^3 promemoria/.test(c1.eventi || '') && !(c2.eventi || ''), [c1, c2]);

  // 8. Spento: il sito torna com'era.
  const f2 = await C.addon(a, B, 'eventi-iscrizione', false);
  ok('spengo: i dati restano', /spento/.test(f2) && /restano/.test(f2));
  ok('spento: la pagina dell\'evento dà 404 e il menu non ha più Eventi', (await a.request.get(B + pagEv)).status() === 404 && await a.locator('nav.admin-nav a', { hasText: /^\s*Iscritti\s*$/ }).count() === 0);
  const diverse = [];
  for (const u of PAGINE) { const h = norma(await (await a.request.get(B + u)).text()); if (h !== prima[u]) diverse.push(u); }
  ok('spento: home, Servizi e Contatti identiche a prima', diverse.length === 0, diverse);
  await C.addon(a, B, 'eventi-iscrizione', true);
  ok('riacceso: l\'evento e la sua pagina ci sono ancora', (await a.request.get(B + pagEv)).status() === 200);

  C.fine(err);
  await br.close();
})().catch(e => { console.error(e); process.exitCode = 1; });
