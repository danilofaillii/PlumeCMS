// L'addon Raccolta recensioni (2.0.0) provato come un'attività e come i suoi clienti, nel browser.
// Su un sito nuovo (kit Attività) con il server di posta finto acceso (python3 -I prove/smtp-finto.py <porta> <cartella>):
// accende l'addon, imposta Google, aggiunge clienti a mano, manda gli inviti, lascia due recensioni dal link dell'email
// (le stelle dell'email aprono il modulo con il voto già scelto), Google proposto solo a chi è contento, una recensione per
// invito, link scaduto, "Non scrivermi più", approva, rispondi, rifiuta, il widget Recensioni dal sito (senza dati per
// Google sulle stelle), e il legame con l'addon Prenotazioni (invito dopo un appuntamento svolto, annullato se non si è
// presentato). Alla fine spegne l'addon: le pagine del sito tornano identiche.
// uso: node prove/prova-addon-recensioni.js <base> <cartella del CMS installato> <porta smtp> <cartella posta>
const { chromium } = require('playwright');
const C = require('./addon-b-comune.js');
const { ok } = C;
const [B, CMS, SMTP, POSTA] = process.argv.slice(2);
const PAG = p => B + '/admin/addon-pagina.php?a=recensioni&p=' + p;
const norma = h => h.replace(/nonce="[^"]+"/g, '').replace(/name="_csrf" value="[^"]+"/g, '').replace(/\?v=\d+/g, '').replace(/data-ew-orari="[^"]*"/g, '').replace(/<span class="ew-orari-frase">[^<]*/g, '');

(async () => {
  const br = await chromium.launch();
  const err = [];
  const a = await C.pagina(br, err);
  const vai = (p, u) => p.goto(u.startsWith('http') ? u : B + u, { waitUntil: 'networkidle' });
  const flash = async () => (await a.locator('.flash').first().innerText().catch(() => '')).trim();
  await C.entra(a, B);
  await C.postaFinta(a, B, SMTP);
  const PAGINE = ['/', '/servizi/', '/contatti/'];
  const prima = {};
  for (const u of PAGINE) prima[u] = norma(await (await a.request.get(B + u)).text());
  ok('spento: la pagina dell\'addon nel pannello dà 404', (await a.goto(PAG('recensioni'))).status() === 404);
  const f1 = await C.addon(a, B, 'recensioni', true);
  ok('accendo da Impostazioni > Addon', /Raccolta recensioni» è acceso/.test(f1), f1);

  // 1. Impostazioni.
  await vai(a, PAG('impostazioni'));
  await a.fill('[name=google_link]', 'http://non-sicuro.example');
  await Promise.all([a.waitForNavigation(), a.click('#rec-impostazioni button.btn-primary')]);
  ok('un link di Google senza https non si salva', /https/.test(await flash()));
  await vai(a, PAG('impostazioni'));
  await a.fill('[name=google_link]', 'https://g.page/r/esempio/review'); await a.selectOption('[name=google_da]', '4');
  await a.fill('[name=avviso_a]', 'recensioni@esempio.it');
  await Promise.all([a.waitForNavigation(), a.click('#rec-impostazioni button.btn-primary')]);
  ok('impostazioni salvate', /salvate/.test(await flash()));

  // 2. Clienti a mano.
  await vai(a, PAG('inviti'));
  await a.fill('#rec-aggiungi [name=elenco]', 'Giulia Rossi <giulia@cliente.it>\nmarco@cliente.it\nnon-una-email@\nGiulia Rossi <giulia@cliente.it>');
  await a.fill('#rec-aggiungi [name=servizio]', 'Taglio e piega');
  await a.selectOption('#rec-aggiungi [name=quando]', 'subito');
  await a.check('#rec-aggiungi [name=consenso]');
  await Promise.all([a.waitForNavigation(), a.click('#rec-aggiungi button')]);
  const fa = await flash();
  ok('due inviti in coda, due saltati (indirizzo sbagliato e doppione)', /2 inviti in coda/.test(fa) && /Saltati 2/.test(fa), fa);
  let quante = C.posta(POSTA).length;
  await Promise.all([a.waitForNavigation(), a.click('#rec-manda')]);
  ok('Manda adesso: "2 inviti mandati"', /2 inviti mandati/.test(await flash()), await flash());
  let em = await C.nuove(POSTA, quante, 2);
  const eg = em.find(e => e.a === 'giulia@cliente.it'), emr = em.find(e => e.a === 'marco@cliente.it');
  ok('email "Com\'è andata?" con cinque stelle cliccabili, il pulsante e "Non scrivermi più"', eg && /Com'è andata\?/.test(eg.oggetto) && C.link(eg).filter(l => /\/recensione\/\?i=\d+&t=[a-f0-9]{40}&s=[1-5]$/.test(l)).length === 5 && C.link(eg).some(l => /\/recensione\/esci\//.test(l)) && /Ciao Giulia/.test(eg.html) && /Taglio e piega/.test(eg.html), eg && eg.oggetto);
  ok('l\'email ha List-Unsubscribe', /^List-Unsubscribe: <http[^>]+\/recensione\/esci\/\?i=/m.test(eg.grezza));

  // 3. Giulia lascia 5 stelle dal link dell'email.
  const v = await C.pagina(br, err);
  const cinque = C.link(eg).find(l => /s=5$/.test(l));
  await vai(v, cinque);
  ok('il modulo si apre con le 5 stelle già scelte e il nome "Giulia R."', await v.isChecked('#rec-s5') && await v.inputValue('[name=nome]') === 'Giulia R.' && /Taglio e piega/.test(await v.locator('main').innerText()));
  await v.fill('[name=testo]', 'Bravissimi, <b>gentili</b> e puntuali.');
  await v.check('[name=consenso]');
  quante = C.posta(POSTA).length;
  await Promise.all([v.waitForNavigation(), v.click('.rec-modulo button[type=submit]')]);
  ok('grazie, e con 5 stelle la proposta di lasciarla anche su Google', /Grazie/.test(await v.locator('h1').innerText()) && await v.locator('.rec-google a[href="https://g.page/r/esempio/review"][rel=noopener]').count() === 1);
  em = await C.nuove(POSTA, quante, 1);
  ok('all\'attività l\'avviso "Nuova recensione: 5 stelle"', em.some(e => e.a === 'recensioni@esempio.it' && /5 stelle da Giulia R\./.test(e.oggetto)));
  await vai(v, cinque);
  ok('lo stesso link una seconda volta: "l\'hai già lasciata"', /già lasciata/.test(await v.locator('h1').innerText()));
  // Marco: 2 stelle, niente Google.
  await vai(v, C.link(emr).find(l => /s=2$/.test(l)));
  await v.fill('[name=nome]', 'Marco'); await v.fill('[name=testo]', 'Ho aspettato troppo.'); await v.check('[name=consenso]');
  await Promise.all([v.waitForNavigation(), v.click('.rec-modulo button[type=submit]')]);
  ok('con 2 stelle niente proposta di Google', /Grazie/.test(await v.locator('h1').innerText()) && await v.locator('.rec-google').count() === 0);
  // Il modulo senza consenso non passa (dal server).
  const t = await C.pagina(br, err, { telefono: true });
  await vai(t, C.link(eg).find(l => /s=4$/.test(l)));
  ok('telefono: la pagina del modulo (già usato) non scorre di lato', await C.largo(t) <= 0);

  // 4. Il pannello: approva, rispondi, rifiuta.
  await vai(a, PAG('recensioni'));
  ok('Recensioni: "2 da approvare" nella scheda, due recensioni in attesa', /2 da approvare/.test(await a.locator('.pa-schede').first().innerText()) && await a.locator('article[data-stato=da_approvare]').count() === 2);
  const giulia = a.locator('article', { hasText: 'Giulia R.' });
  ok('il testo con <b> si vede come testo', /<b>gentili<\/b>/.test(await giulia.locator('.rec-testo-pannello').innerText()));
  await Promise.all([a.waitForNavigation(), giulia.locator('button[value=approva]').click()]);
  ok('approvata', /Approvata/.test(await flash()));
  await vai(a, PAG('recensioni') + '&filtro=approvata');
  await a.locator('article', { hasText: 'Giulia R.' }).locator('textarea[name=risposta]').fill('Grazie Giulia, ti aspettiamo!');
  await Promise.all([a.waitForNavigation(), a.locator('article', { hasText: 'Giulia R.' }).locator('button[value=rispondi]').click()]);
  ok('risposta salvata', /si vede sul sito/.test(await flash()));
  await vai(a, PAG('recensioni'));
  await Promise.all([a.waitForNavigation(), a.locator('article', { hasText: 'Marco' }).locator('button[value=rifiuta]').click()]);
  ok('quella di Marco rifiutata', /Rifiutata/.test(await flash()));

  // 5. Il widget Recensioni dal sito.
  C.cli(CMS, 'pagina', 'dicono-di-noi', 'Dicono di noi', 'recensioni_sito', JSON.stringify({ pulsante_google: true }));
  await vai(v, '/dicono-di-noi/');
  const w = await v.locator('.rec-sito').innerText();
  ok('il widget mostra la recensione approvata, con la risposta e il voto medio', /Bravissimi/.test(w) && /Grazie Giulia, ti aspettiamo!/.test(w) && /1 recensione/.test(w) && await v.locator('.rec-sito .ew-rec-media').innerText() === '5');
  ok('la rifiutata non c\'è', !/Ho aspettato/.test(w));
  ok('stesso aspetto del widget Recensioni (classi ew-rec e widget.css) e pulsante per Google', await v.locator('.rec-sito .ew-rec-voce').count() === 1 && await v.locator('link[href*="widget.css"]').count() >= 1 && await v.locator('.rec-sito a[href="https://g.page/r/esempio/review"]').count() === 1);
  const ld = await v.locator('script[type="application/ld+json"]').allTextContents();
  ok('niente dati per Google sulle stelle (né Review né AggregateRating)', !ld.some(x => /Review|AggregateRating|ratingValue/.test(x)));
  await vai(t, '/dicono-di-noi/');
  ok('telefono: la pagina con il widget non scorre di lato', await C.largo(t) <= 0, await C.largo(t));
  const at = await C.pagina(br, err, { telefono: true });
  await C.entra(at, B);
  const larghe = [];
  for (const u of ['recensioni&filtro=tutte', 'inviti', 'impostazioni']) { await vai(at, PAG(u)); if (await C.largo(at) > 0) larghe.push(u); }
  ok('le pagine del pannello al telefono: nessuna scorre di lato', larghe.length === 0, larghe);
  await vai(a, '/admin/editor.php?id=' + C.cli(CMS, 'sql', "SELECT id FROM {p}pages WHERE slug = 'dicono-di-noi'")[0].id);
  ok('il widget è nella tavolozza dell\'editor', await a.locator('.ed-widget-voce[data-tipo=recensioni_sito]').count() === 1);

  // 6. "Non scrivermi più" e link scaduto.
  await vai(v, C.link(emr).find(l => /esci/.test(l)));
  await Promise.all([v.waitForNavigation(), v.click('button:has-text("non scrivetemi più")')]);
  ok('"Non scrivermi più": fatto', /Non ti scriveremo più/.test(await v.locator('.rec-ok').innerText()));
  await vai(a, PAG('inviti'));
  await a.fill('#rec-aggiungi [name=elenco]', 'marco@cliente.it\nsara@cliente.it'); await a.selectOption('#rec-aggiungi [name=quando]', 'subito'); await a.check('#rec-aggiungi [name=consenso]');
  await Promise.all([a.waitForNavigation(), a.click('#rec-aggiungi button')]);
  ok('chi ha chiesto di non ricevere più queste email viene saltato', /1 invito in coda/.test(await flash()) && /marco@cliente\.it/.test(await flash()), await flash());
  quante = C.posta(POSTA).length;
  await Promise.all([a.waitForNavigation(), a.click('#rec-manda')]);
  em = await C.nuove(POSTA, quante, 1);
  const es = em.find(e => e.a === 'sara@cliente.it');
  C.cli(CMS, 'sql', "UPDATE {p}rec_inviti SET scade_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE email = 'sara@cliente.it'");
  const r410 = await v.goto(C.link(es).find(l => /s=4$/.test(l)));
  ok('link scaduto: 410 "Questo link è scaduto"', r410.status() === 410 && /scaduto/.test(await v.locator('h1').innerText()));

  // 7. Con l'addon Prenotazioni acceso: l'invito parte da solo dopo un appuntamento svolto.
  await C.addon(a, B, 'prenotazioni', true);
  const ora = Math.floor(Date.now() / 1000);
  C.cli(CMS, 'sql', "INSERT INTO {p}pren_risorse (nome, orari, ferie, creato) VALUES ('Anna', '{}', '[]', NOW())");
  C.cli(CMS, 'sql', "INSERT INTO {p}pren_servizi (nome, durata, creato) VALUES ('Colore', 60, NOW())");
  const ids = C.cli(CMS, 'sql', 'SELECT (SELECT MAX(id) FROM {p}pren_risorse) r, (SELECT MAX(id) FROM {p}pren_servizi) s')[0];
  for (const [chi, email, h] of [['Luca Bianchi', 'luca@cliente.it', 5], ['Paolo Verdi', 'paolo@cliente.it', 4]]) {
    C.cli(CMS, 'sql', `INSERT INTO {p}pren_prenotazioni (codice, servizio_id, risorsa_id, servizio, risorsa, inizio, fine, inizio_ts, fine_ts, blocco_ts, nome, email, stato, consenso_at, creato, aggiornato) VALUES (MD5(RAND()), ${ids.s}, ${ids.r}, 'Colore', 'Anna', FROM_UNIXTIME(${ora - h * 3600}), FROM_UNIXTIME(${ora - h * 3600 + 3600}), ${ora - h * 3600}, ${ora - h * 3600 + 3600}, ${ora - h * 3600 + 3600}, '${chi}', '${email}', 'confermata', NOW(), NOW(), NOW())`);
  }
  const c1 = C.cli(CMS, 'cron');
  ok('il cron segna svolti i due appuntamenti', /2 appuntamenti svolti/.test(c1.prenotazioni || ''), c1);
  await vai(a, PAG('inviti'));
  ok('due inviti "Appuntamento svolto" in coda, con il servizio', await a.locator('tr', { hasText: 'luca@cliente.it' }).filter({ hasText: 'Appuntamento svolto' }).filter({ hasText: 'In coda' }).count() === 1 && /Colore/.test(await a.locator('tr', { hasText: 'luca@cliente.it' }).innerText()));
  const pid = C.cli(CMS, 'sql', "SELECT id FROM {p}pren_prenotazioni WHERE email = 'paolo@cliente.it'")[0].id;
  await vai(a, B + '/admin/addon-pagina.php?a=prenotazioni&p=elenco&id=' + pid);
  await Promise.all([a.waitForNavigation(), a.click('button[value=non_presentato]')]);
  await vai(a, PAG('inviti'));
  ok('Paolo non si è presentato: il suo invito è annullato', /Annullato/.test(await a.locator('tr', { hasText: 'paolo@cliente.it' }).innerText()));
  quante = C.posta(POSTA).length;
  const c2 = C.cli(CMS, 'cron', String(3 * 86400));
  em = await C.nuove(POSTA, quante, 1);
  ok('tre giorni dopo parte l\'invito a Luca (e non a Paolo)', em.some(e => e.a === 'luca@cliente.it' && /Com'è andata/.test(e.oggetto) && /Colore/.test(e.html)) && !em.some(e => e.a === 'paolo@cliente.it') && /1 invito mandato/.test(c2.recensioni || ''), [c2, em.map(e => e.a)]);
  await C.addon(a, B, 'prenotazioni', false);

  // 8. Elimina e spento.
  await vai(a, PAG('recensioni') + '&filtro=rifiutata');
  await Promise.all([a.waitForNavigation(), a.locator('article', { hasText: 'Marco' }).locator('button[value=elimina]').click()]);
  ok('una recensione si elimina (per esempio su richiesta di chi l\'ha scritta)', /eliminata/.test(await flash()) && +C.cli(CMS, 'sql', "SELECT COUNT(*) n FROM {p}rec_recensioni WHERE nome = 'Marco'")[0].n === 0);
  const f2 = await C.addon(a, B, 'recensioni', false);
  ok('spengo: i dati restano', /spento/.test(f2) && /restano/.test(f2));
  ok('spento: il link del modulo dà 404', (await a.request.get(cinque)).status() === 404);
  const diverse = [];
  for (const u of PAGINE) { const h = norma(await (await a.request.get(B + u)).text()); if (h !== prima[u]) diverse.push(u); }
  ok('spento: home, Servizi e Contatti identiche a prima', diverse.length === 0, diverse);
  await C.addon(a, B, 'recensioni', true);
  await vai(v, '/dicono-di-noi/');
  ok('riacceso: la recensione approvata è ancora sul sito', /Bravissimi/.test(await v.locator('.rec-sito').innerText()));

  C.fine(err);
  await br.close();
})().catch(e => { console.error(e); process.exitCode = 1; });
