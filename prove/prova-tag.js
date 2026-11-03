// I tag dei contatti (2.23.0) provati nel browser come un amministratore: la scheda del contatto (aggiungi scrivendo con i
// suggerimenti, togli con la x, la cronologia), l'elenco dei Contatti (azione di gruppo "Aggiungi un tag" e "Togli un tag",
// filtro "Con il tag"), la pagina Tag (crea, rinomina, elimina con la conferma del pannello, link ai contatti filtrati),
// i segmenti (ha / non ha, qualsiasi / tutti, contati a mano), le automazioni (innesco "Riceve un tag", azioni "Aggiungi tag"
// e "Togli tag": le email partono e arrivano al server finto, e un tag dato da un'automazione fa partire un'altra), il form
// (passo 1, "A chi compila dai questi tag": con la doppia conferma i tag arrivano alla conferma, senza subito), l'importazione
// (una colonna abbinata da sola a "Tag", "aggiungi" e "sostituisci") e l'esportazione (la colonna TAG). Telefono a 390 px.
// Su un sito appena installato (va su un sito da buttare), con il server di posta finto acceso:
//   python3 -I prove/smtp-finto.py <porta> <cartella posta> &
// uso: node prove/prova-tag.js <base> <cartella del CMS installato> <porta smtp> <cartella posta> [cartella foto]
const { chromium } = require('playwright');
const path = require('path');
const { execFileSync } = require('child_process');
const C = require('./addon-b-comune.js');
const { ok } = C;
const [B, CMS, SMTP, POSTA, FOTO] = process.argv.slice(2);
const cli = (...a) => { const o = execFileSync('php', [path.join(__dirname, 'tag-cli.php'), CMS, ...a.map(String)], { encoding: 'utf8' }); return JSON.parse(o.trim().split('\n').pop()); };
const sql = q => cli('sql', q);
const foto = async (p, nome) => { if (FOTO) await p.screenshot({ path: path.join(FOTO, 'tag-' + nome + '.png'), fullPage: true }); };
const vai = (p, u) => p.goto(u.startsWith('http') ? u : B + u, { waitUntil: 'networkidle' });
const flash = async p => (await p.locator('.flash').first().innerText().catch(() => '')).trim();
const id = email => +(sql(`SELECT id FROM {p}newsletter_iscritti WHERE email = '${email}'`)[0] || {}).id || 0;
const tagDi = email => cli('tag_di', email);
const b64 = o => Buffer.from(JSON.stringify(o)).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

(async () => {
  const br = await chromium.launch();
  const err = [];
  const a = await C.pagina(br, err);
  a.setDefaultTimeout(15000);
  await C.entra(a, B);
  cli('smtp', SMTP);
  const lista = cli('semina', 6);
  ok('sei contatti iscritti nella lista «TG Prova»', lista > 0 && sql(`SELECT COUNT(*) n FROM {p}newsletter_iscritti WHERE email LIKE '%@tag-prova.it'`)[0].n == 6);

  // ============================================================ Pagina Tag e menu
  await vai(a, '/admin/newsletter-tag.php');
  ok('menu: «Tag» sotto Newsletter e form, e la scheda Tag accanto a Contatti, Liste, Segmenti', await a.locator('nav.admin-nav a[href="newsletter-tag.php"]').count() >= 1 && await a.locator('.imp-schede a[href="newsletter-tag.php"].attiva').count() === 1);
  ok('pagina Tag vuota: dice cosa fare', /Ancora nessun tag/.test(await a.locator('.empty-state').innerText()));
  await a.fill('#nlt-nome', '  <b>Cliente</b> ');
  await Promise.all([a.waitForNavigation(), a.click('#nlt-crea button[type=submit]')]);
  ok('Crea un tag: l\'HTML si toglie, il nome è «Cliente»', /Tag «Cliente» pronto/.test(await flash(a)) && sql(`SELECT nome FROM {p}newsletter_tag`).map(x => x.nome).join() === 'Cliente', await flash(a));
  await a.fill('#nlt-nome', 'cliente');
  await Promise.all([a.waitForNavigation(), a.click('#nlt-crea button[type=submit]')]);
  ok('maiuscole e minuscole non contano: «cliente» è lo stesso tag', sql(`SELECT COUNT(*) n FROM {p}newsletter_tag`)[0].n == 1);
  await a.fill('#nlt-nome', ' , ; ');
  await Promise.all([a.waitForNavigation(), a.click('#nlt-crea button[type=submit]')]);
  ok('un nome fatto solo di virgole: errore sotto il campo (role=alert, aria-invalid)', await a.locator('#nlt-nome-err[role=alert]').count() === 1 && await a.getAttribute('#nlt-nome', 'aria-invalid') === 'true');

  // ============================================================ Scheda del contatto
  const c1 = id('tg1@tag-prova.it');
  await vai(a, '/admin/newsletter-contatto.php?id=' + c1);
  ok('scheda: il riquadro Tag, vuoto, dice cosa fare', /Nessun tag/.test(await a.locator('#nlk-tag').innerText()));
  ok('scheda: i suggerimenti dei tag che ci sono già (datalist)', await a.locator('#nlk-tag-suggeriti option[value="Cliente"]').count() === 1 && await a.getAttribute('#nlk-tag-nuovo', 'list') === 'nlk-tag-suggeriti');
  await a.fill('#nlk-tag-nuovo', 'Cliente, VIP \u{1F31F}, Fiera 2026');
  await Promise.all([a.waitForNavigation(), a.click('#nlk-tag button[type=submit]')]);
  ok('scheda: tre tag aggiunti scrivendo (uno nuovo con un\'emoji nasce da solo)', /3 tag aggiunti/.test(await flash(a)) && JSON.stringify(tagDi('tg1@tag-prova.it')) === JSON.stringify(['Cliente', 'Fiera 2026', 'VIP \u{1F31F}']), tagDi('tg1@tag-prova.it'));
  ok('scheda: i tag come pillole, ognuna con il link ai contatti con quel tag e la x con un nome', await a.locator('#nlk-tag .nlk-tag-voce').count() === 3 && await a.locator('#nlk-tag button[aria-label="Togli il tag VIP \u{1F31F}"]').count() === 1
    && /newsletter-contatti\.php\?tag=\d+/.test(await a.locator('#nlk-tag .nlk-tag-voce a').first().getAttribute('href')));
  await foto(a, 'scheda');
  await Promise.all([a.waitForNavigation(), a.click('#nlk-tag button[aria-label="Togli il tag VIP \u{1F31F}"]')]);
  ok('scheda: la x toglie il tag', /Tag tolto/.test(await flash(a)) && !tagDi('tg1@tag-prova.it').includes('VIP \u{1F31F}'));
  await vai(a, '/admin/newsletter-contatto.php?id=' + c1 + '&scheda=cronologia&tipo=tag');
  const cron = await a.locator('table.table tbody').innerText();
  ok('cronologia: «Tag aggiunto» e «Tag tolto», con il filtro Tag', /Tag aggiunto: «Cliente» \(dalla scheda del contatto\)/.test(cron) && /Tag tolto: «VIP/.test(cron) && await a.locator('#nlk-tipo option[value=tag]').count() === 1, cron.slice(0, 300));

  // ============================================================ Elenco dei contatti: azioni di gruppo e filtro
  await vai(a, '/admin/newsletter-contatti.php?lista=' + lista);
  for (const n of [2, 3, 4]) await a.locator(`input.nlx-sel[aria-label="Scegli tg${n}@tag-prova.it"]`).check();
  await a.selectOption('#nlx-cosa', 'tag_aggiungi');
  ok('azione di gruppo: con «Aggiungi un tag» compare il campo «Quali tag», con i suggerimenti', await a.locator('#nlx-tag-nomi').isVisible() && await a.locator('#nlx-tag-suggeriti option').count() >= 2);
  await a.fill('#nlx-tag-nomi', 'Newsletter speciale');
  await Promise.all([a.waitForNavigation(), a.click('#nlx-applica')]);
  ok('azione di gruppo: tag aggiunto a 3 contatti', /aggiunto a 3 contatti/.test(await flash(a)), await flash(a));
  const tSp = +sql(`SELECT id FROM {p}newsletter_tag WHERE nome = 'Newsletter speciale'`)[0].id;
  await vai(a, '/admin/newsletter-contatti.php');
  await a.selectOption('#nlx-tag', String(tSp));
  await Promise.all([a.waitForNavigation(), a.click('.nlx-filtri button[type=submit]')]);
  ok('filtro «Con il tag»: tre contatti', await a.locator('.nlx-tabella tbody tr').count() === 3 && /tag=\d+/.test(a.url()));
  await a.locator('input.nlx-sel[aria-label="Scegli tg2@tag-prova.it"]').check();
  await a.selectOption('#nlx-cosa', 'tag_togli');
  await a.fill('#nlx-tag-nomi', 'newsletter SPECIALE');
  await Promise.all([a.waitForNavigation(), a.click('#nlx-applica')]);
  ok('togli un tag (maiuscole diverse): tolto a 1, il filtro resta e ne mostra 2', /tolto a 1 contatto/.test(await flash(a)) && /tag=\d+/.test(a.url()) && await a.locator('.nlx-tabella tbody tr').count() === 2, await flash(a));
  await a.check('#nlx-tutti');
  await a.selectOption('#nlx-cosa', 'tag_aggiungi');
  await a.fill('#nlx-tag-nomi', 'Tutti filtrati');
  await Promise.all([a.waitForNavigation(), a.click('#nlx-applica')]);
  ok('«Tutti i contatti del filtro»: il tag va ai 2 del filtro, non agli altri', sql(`SELECT COUNT(*) n FROM {p}newsletter_contatti_tag ct JOIN {p}newsletter_tag t ON t.id = ct.tag_id WHERE t.nome = 'Tutti filtrati'`)[0].n == 2);
  await foto(a, 'elenco');

  // ============================================================ Pagina Tag: numeri, rinomina, elimina
  await vai(a, '/admin/newsletter-tag.php');
  const rigaSp = a.locator('.nlt-tabella tbody tr', { hasText: 'Newsletter speciale' });
  ok('pagina Tag: il tag con 2 contatti e il link ai contatti filtrati', (await rigaSp.locator('td').nth(1).innerText()).trim() === '2' && await rigaSp.locator(`a[href="newsletter-contatti.php?tag=${tSp}"]`).count() === 1);
  await rigaSp.locator('summary').click();
  await rigaSp.locator('input[name=nome]').fill('Cliente');
  await Promise.all([a.waitForNavigation(), rigaSp.locator('button', { hasText: 'Rinomina il tag' }).click()]);
  ok('rinomina con il nome di un altro tag: rifiutata, lo dice', /Esiste già un tag «Cliente»/.test(await flash(a)));
  const rigaSp2 = a.locator('.nlt-tabella tbody tr', { hasText: 'Newsletter speciale' });
  await rigaSp2.locator('summary').click();
  await rigaSp2.locator('input[name=nome]').fill('Speciale');
  await Promise.all([a.waitForNavigation(), rigaSp2.locator('button', { hasText: 'Rinomina il tag' }).click()]);
  ok('rinomina: i contatti tengono il tag con il nome nuovo', /rinominato/.test(await flash(a)) && tagDi('tg3@tag-prova.it').includes('Speciale'));
  const rigaE = a.locator('.nlt-tabella tbody tr', { hasText: 'Tutti filtrati' });
  await rigaE.locator('summary').click();
  const dialogo = await rigaE.locator('form[data-conferma]').getAttribute('data-conferma');
  await Promise.all([a.waitForNavigation(), rigaE.locator('button', { hasText: 'Elimina il tag' }).click()]);
  ok('elimina con la conferma del pannello (dice quanti contatti lo perdono): tag e legami via', /Lo perdono 2 contatti/.test(dialogo) && /eliminato/.test(await flash(a)) && sql(`SELECT COUNT(*) n FROM {p}newsletter_tag WHERE nome = 'Tutti filtrati'`)[0].n == 0, dialogo);
  await foto(a, 'pagina');

  // ============================================================ Segmenti
  const tCl = +sql(`SELECT id FROM {p}newsletter_tag WHERE nome = 'Cliente'`)[0].id;
  const tFi = +sql(`SELECT id FROM {p}newsletter_tag WHERE nome = 'Fiera 2026'`)[0].id;
  cli('tag_aggiungi', 'tg5@tag-prova.it', 'Cliente');
  // A mano: Cliente = tg1, tg5; Fiera 2026 = tg1; Speciale = tg3, tg4.
  const conta = (op, v) => cli('conta', JSON.stringify({ op: 'e', gruppi: [{ op: 'e', condizioni: [{ campo: 'tag', op, valore: v }] }] }));
  const tot = sql(`SELECT COUNT(*) n FROM {p}newsletter_iscritti`)[0].n;
  ok('segmenti: «ha almeno uno di» Cliente o Fiera = 2 (tg1, tg5)', conta('qualsiasi', [tCl, tFi]).n === 2);
  ok('segmenti: «ha tutti» Cliente e Fiera = 1 (tg1)', conta('tutti', [tCl, tFi]).n === 1);
  ok('segmenti: «non ha nessuno di» Cliente = tutti meno 2', conta('nessuno', [tCl]).n === tot - 2, [conta('nessuno', [tCl]), tot]);
  ok('segmenti: «non ha tutti» Cliente e Fiera = tutti meno 1', conta('non_tutti', [tCl, tFi]).n === tot - 1);
  ok('segmenti: un tag che non esiste non vale («scegli almeno un tag»)', /scegli almeno un tag/.test(conta('qualsiasi', [999999]).errore || ''));
  await vai(a, '/admin/newsletter-contatti.php?filtro=1');
  await a.click('#nlx-filtro [data-aggiungi="0"]').catch(() => {});
  const selCampo = a.locator('#nlx-filtro [data-k=campo]').last();
  await selCampo.selectOption('tag');
  await a.locator('#nlx-filtro .nls-caselle label', { hasText: 'Cliente' }).locator('input').check();
  await a.waitForFunction(() => /2 contatti|2 contatto/.test(document.querySelector('[data-nls-numero]').textContent), null, { timeout: 8000 }).catch(() => {});
  ok('Aggiungi filtro: il campo «Tag» con le caselle dei tag, il conteggio dal vivo (2)', /\b2\b/.test(await a.locator('[data-nls-numero]').innerText()), await a.locator('[data-nls-numero]').innerText());
  await Promise.all([a.waitForNavigation(), a.click('#nlx-filtro button[type=submit]')]);
  ok('Aggiungi filtro applicato: 2 righe e la frase «Tag ha almeno uno di questi tag Cliente»', await a.locator('.nlx-tabella tbody tr').count() === 2 && /Tag ha almeno uno di questi tag Cliente/.test(await a.locator('.nl-avviso').first().innerText()));
  await foto(a, 'filtro');

  // ============================================================ Automazioni
  await vai(a, '/admin/newsletter-flussi.php?nuovo=1');
  await a.selectOption('#fi-t0', 'tag');
  ok('tela: l\'innesco «Riceve un tag» con le caselle dei tag', await a.locator('#fl-inneschi .fl-tag-scelta input[type=checkbox]').count() >= 3 && /Riceve un tag: qualunque tag/.test(await a.locator('.fl-nodo[data-id="0"]').innerText()));
  await a.locator('#fl-inneschi .fl-tag-scelta label', { hasText: 'Cliente' }).locator('input').check();
  ok('tela: il riassunto dell\'innesco dice il tag scelto', /Riceve un tag: Cliente/.test(await a.locator('.fl-nodo[data-id="0"]').innerText()));
  await a.click('#fl-scheda-azioni');
  ok('tela: le azioni «Aggiungi tag» e «Togli tag» nella tavolozza', await a.locator('.fl-pezzo[data-tipo=tag_aggiungi]').isVisible() && await a.locator('.fl-pezzo[data-tipo=tag_togli]').isVisible());
  await foto(a, 'tela');
  const tSe = cli('tag_crea', 'Seguito');
  const nodo = (i, tipo, dati = {}) => ({ id: i, tipo, x: 40, y: 40 + i * 140, dati });
  const em = (i, ogg) => nodo(i, 'email', { oggetto: ogg, anteprima: '', corpo: '<p>' + ogg + '</p>', pulsante_testo: '', pulsante_link: '', ascolto: 0 });
  const f1 = cli('salva_flusso', JSON.stringify({ nome: 'TG Clienti', stato: 'attiva', inneschi: [{ tipo: 'tag', id: 0, tag: [tCl] }],
    grafo: { nodi: [nodo(0, 'innesco'), em(1, 'TG benvenuto clienti'), nodo(2, 'tag_aggiungi', { tag: [tSe] }), nodo(3, 'tag_togli', { tag: [tCl] })], archi: [{ da: 0, uscita: 'poi', a: 1 }, { da: 1, uscita: 'poi', a: 2 }, { da: 2, uscita: 'poi', a: 3 }] } }));
  const f2 = cli('salva_flusso', JSON.stringify({ nome: 'TG Seguiti', stato: 'attiva', inneschi: [{ tipo: 'tag', id: 0, tag: [tSe] }], grafo: { nodi: [nodo(0, 'innesco'), em(1, 'TG ora ti seguiamo')], archi: [{ da: 0, uscita: 'poi', a: 1 }] } }));
  ok('due automazioni salvate (innesco tag, azioni aggiungi e togli)', typeof f1 === 'number' && typeof f2 === 'number', [f1, f2]);
  ok('un\'azione tag senza tag non si salva, e lo dice', /scegli almeno un tag/.test(cli('salva_flusso', JSON.stringify({ nome: 'TG vuota', stato: 'attiva', inneschi: [{ tipo: 'iscrizione', id: 0 }], grafo: { nodi: [nodo(0, 'innesco'), nodo(1, 'tag_aggiungi', { tag: [] })], archi: [{ da: 0, uscita: 'poi', a: 1 }] } })).errore || ''));
  await vai(a, '/admin/newsletter-flussi.php?id=' + f1);
  ok('tela riaperta: innesco con la casella Cliente spuntata, i blocchi dicono i tag', await a.locator('#fl-inneschi .fl-tag-scelta label', { hasText: 'Cliente' }).locator('input').isChecked()
    && /Seguito/.test(await a.locator('.fl-nodo[data-id="2"]').innerText()) && /Cliente/.test(await a.locator('.fl-nodo[data-id="3"]').innerText()));
  await a.locator('.fl-nodo[data-id="2"]').click();
  ok('tela: il pannello del blocco «Aggiungi tag» con le caselle', await a.locator('#fl-pannello .fl-tag-scelta label', { hasText: 'Seguito' }).locator('input').isChecked());
  await Promise.all([a.waitForNavigation(), a.click('#fl-form button[name=azione][value=salva]')]);
  ok('tela: salvata di nuovo dal pannello senza cambiare niente, i tag restano', /salvata/.test(await flash(a)) && JSON.stringify(JSON.parse(sql(`SELECT passi FROM {p}newsletter_flussi WHERE id = ${f1}`)[0].passi).nodi.find(n => n.id === 2).dati.tag) === JSON.stringify([tSe]));
  // tg6 riceve il tag Cliente dalla scheda: entra in TG Clienti.
  const c6 = id('tg6@tag-prova.it');
  await vai(a, '/admin/newsletter-contatto.php?id=' + c6);
  await a.fill('#nlk-tag-nuovo', 'Cliente');
  await Promise.all([a.waitForNavigation(), a.click('#nlk-tag button[type=submit]')]);
  ok('innesco «Riceve un tag»: tg6 entra in «TG Clienti»', sql(`SELECT COUNT(*) n FROM {p}newsletter_corsi WHERE flusso_id = ${f1} AND iscritto_id = ${c6}`)[0].n == 1);
  let nP = C.posta(POSTA).length;
  cli('giro'); cli('giro'); cli('giro');
  const mail = await C.nuove(POSTA, nP, 2);
  ok('il giro manda l\'email di «TG Clienti» e poi, per il tag dato dall\'automazione, quella di «TG Seguiti»', mail.some(m => /TG benvenuto clienti/.test(m.oggetto) && /tg6@/.test(m.a)) && mail.some(m => /TG ora ti seguiamo/.test(m.oggetto) && /tg6@/.test(m.a)), mail.map(m => m.oggetto + ' ' + m.a));
  ok('azioni: tg6 ha «Seguito» e non ha più «Cliente»; il registro lo dice', JSON.stringify(tagDi('tg6@tag-prova.it')) === '["Seguito"]'
    && sql(`SELECT dettaglio FROM {p}newsletter_flussi_registro WHERE flusso_id = ${f1} AND evento = 'azione'`).map(x => x.dettaglio).join('|').includes('tag aggiunti: Seguito'), tagDi('tg6@tag-prova.it'));
  ok('le automazioni salvate prima restano valide (nessun innesco tag nelle altre)', sql(`SELECT COUNT(*) n FROM {p}newsletter_flussi`)[0].n >= 2);

  // ============================================================ Form
  await vai(a, '/admin/form.php?nuovo=1&tipo=iscrizione');
  ok('form, passo 1: «A chi compila dai questi tag (facoltativo)» con i suggerimenti', await a.locator('#fm-tag').count() === 1 && await a.locator('#fm-tag-suggeriti option').count() >= 3);
  await a.fill('#fm-nome', 'TG Iscrizione');
  await a.check('input[name=gdpr]');
  await a.fill('#fm-tag', 'Dal form, Interessato al corso');
  await Promise.all([a.waitForNavigation(), a.click('#fm-form button[type=submit]')]);
  const F = +(new URL(a.url()).searchParams.get('id') || 0);
  ok('form creato con i suoi tag', sql(`SELECT tag FROM {p}moduli WHERE id = ${F}`)[0].tag === 'Dal form, Interessato al corso');
  await vai(a, '/admin/form.php?id=' + F + '&passo=1');
  ok('form, passo 1 riaperto: i tag scritti', await a.inputValue('#fm-tag') === 'Dal form, Interessato al corso');
  await foto(a, 'form');
  const slug = sql(`SELECT slug FROM {p}moduli WHERE id = ${F}`)[0].slug;
  const v = await C.pagina(br, err);
  const compila = async email => {
    await vai(v, '/modulo/' + slug + '/');
    await v.fill('input[type=email]', email);
    await v.check('input[name=privacy]');
    await C.captcha(v.locator('form[data-captcha]'), v);
    await Promise.all([v.waitForNavigation({ timeout: 20000 }).catch(() => {}), v.click('form button[type=submit]')]);
  };
  nP = C.posta(POSTA).length;
  await compila('modulo1@tag-prova.it');
  const conf = (await C.nuove(POSTA, nP, 1)).find(m => /modulo1@/.test(m.a));
  ok('form con doppia conferma: il contatto aspetta, senza tag; i tag aspettano la conferma', id('modulo1@tag-prova.it') > 0 && tagDi('modulo1@tag-prova.it').length === 0 && sql(`SELECT COUNT(*) n FROM {p}newsletter_tag_attese WHERE iscritto_id = ${id('modulo1@tag-prova.it')}`)[0].n == 2);
  await vai(v, C.link(conf).find(l => /conferma/.test(l)));
  ok('form: dopo la conferma i tag «Dal form» e «Interessato al corso»', JSON.stringify(tagDi('modulo1@tag-prova.it')) === '["Dal form","Interessato al corso"]', tagDi('modulo1@tag-prova.it'));
  sql(`UPDATE {p}moduli SET conferma = 'nessuna', lista_conferma = 0 WHERE id = ${F}`);
  sql(`UPDATE {p}moduli_invii SET aggiornato = aggiornato - INTERVAL 2 HOUR`);
  sql(`UPDATE {p}newsletter_iscritti SET aggiornato = aggiornato - INTERVAL 2 HOUR WHERE email LIKE '%@tag-prova.it'`);
  await compila('modulo2@tag-prova.it');
  ok('form senza conferma: i tag subito', JSON.stringify(tagDi('modulo2@tag-prova.it')) === '["Dal form","Interessato al corso"]', tagDi('modulo2@tag-prova.it'));

  // ============================================================ Importa ed esporta
  const importa = async (testo, modo, conferma = true) => {
    await vai(a, '/admin/newsletter-importa.php');
    await a.check('input[name=modo][value=incolla]').catch(() => {});
    await a.locator('textarea[name=testo]').evaluate((t, x) => { t.value = x; }, testo);
    await Promise.all([a.waitForNavigation(), a.click('#nli-carica button[type=submit]')]);
    return a;
  };
  await importa('EMAIL;NOME;TAG\nimp1@tag-prova.it;Imp1;Uno, Due\nimp2@tag-prova.it;Imp2;"Tre;Quattro"\ntg1@tag-prova.it;Tg1;Importato', 'aggiungi');
  ok('importa, passo 2: la colonna TAG si abbina da sola a «Tag»', await a.locator('select[name="col[2]"]').inputValue() === '_TAG' && await a.locator('select[name="col[2]"] option[value=_TAG]').innerText().then(t => /Tag/.test(t)));
  await Promise.all([a.waitForNavigation(), a.click('button:has-text("Avanti: scegli la lista")')]);
  await a.locator(`input[name="liste[]"][value="${lista}"]`).check();
  await Promise.all([a.waitForNavigation(), a.click('#nli-liste button.btn-primary')]);
  ok('importa, passo 4: «aggiungi ai tag che ha» o «sostituisci», di partenza aggiungi', await a.locator('#nli-tag input[value=aggiungi]').isChecked() && await a.locator('#nli-tag input[value=sostituisci]').count() === 1);
  await foto(a, 'importa');
  await a.check('input[name=consenso]');
  await Promise.all([a.waitForNavigation(), a.click('#nli-conferma button.btn-primary')]);
  await a.waitForFunction(() => !document.querySelector('[data-nl-barra]') || /finit|Fatto|nuovi/i.test(document.body.innerText), null, { timeout: 30000 }).catch(() => {});
  ok('importa (aggiungi): i nuovi con i loro tag; «Tre;Quattro» con il punto e virgola sono due tag', JSON.stringify(tagDi('imp1@tag-prova.it')) === '["Due","Uno"]' && JSON.stringify(tagDi('imp2@tag-prova.it')) === '["Quattro","Tre"]', [tagDi('imp1@tag-prova.it'), tagDi('imp2@tag-prova.it')]);
  ok('importa (aggiungi): chi c\'era tiene i suoi tag e prende quello nuovo', JSON.stringify(tagDi('tg1@tag-prova.it')) === '["Cliente","Fiera 2026","Importato"]', tagDi('tg1@tag-prova.it'));
  await importa('EMAIL;TAG\ntg1@tag-prova.it;Solo questo\nimp1@tag-prova.it;', 'sostituisci');
  await Promise.all([a.waitForNavigation(), a.click('button:has-text("Avanti: scegli la lista")')]);
  await a.locator(`input[name="liste[]"][value="${lista}"]`).check();
  await Promise.all([a.waitForNavigation(), a.click('#nli-liste button.btn-primary')]);
  await a.check('#nli-tag input[value=sostituisci]');
  await a.check('input[name=consenso]');
  await Promise.all([a.waitForNavigation(), a.click('#nli-conferma button.btn-primary')]);
  await a.waitForTimeout(1500);
  ok('importa (sostituisci): tg1 tiene solo «Solo questo»; una cella vuota non toglie niente', JSON.stringify(tagDi('tg1@tag-prova.it')) === '["Solo questo"]' && JSON.stringify(tagDi('imp1@tag-prova.it')) === '["Due","Uno"]', [tagDi('tg1@tag-prova.it'), tagDi('imp1@tag-prova.it')]);
  const csv = await (await a.request.get(B + '/admin/newsletter-contatti.php?scarica=tutti')).text();
  const testa = csv.replace(/^﻿/, '').split(/\r?\n/)[0].split(';');
  const rigaImp2 = csv.split(/\r?\n/).find(r => r.startsWith('imp2@')) || '';
  ok('esporta: la colonna TAG, con i tag del contatto', testa.includes('TAG') && /Quattro, Tre/.test(rigaImp2), [testa.slice(-3), rigaImp2]);

  // ============================================================ Telefono
  const tel = await C.pagina(br, err, { telefono: true });
  await C.entra(tel, B);
  for (const [u, n] of [['/admin/newsletter-tag.php', 'pagina Tag'], ['/admin/newsletter-contatti.php', 'Contatti'], ['/admin/newsletter-contatto.php?id=' + c1, 'scheda del contatto']]) {
    await vai(tel, u);
    ok('telefono 390, ' + n + ': niente scorrimento di lato', await C.largo(tel) <= 0, await C.largo(tel));
  }
  const xb = await tel.locator('#nlk-tag .nlk-tag-togli button').first().boundingBox().catch(() => null);
  ok('telefono: la x di un tag è alta almeno 44 px', xb && xb.height >= 44 && xb.width >= 44, xb);
  await foto(tel, 'telefono-scheda');
  C.fine(err);
  await br.close();
})().catch(e => { console.log('ERRORE ' + e.stack); process.exit(1); });
