// L'addon Prenotazioni e appuntamenti (2.0.0) provato come un'attività e come i suoi clienti, nel browser.
// Su un sito nuovo (kit Attività) con il server di posta finto acceso:
//   python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> &
// Accende l'addon da Impostazioni > Addon, scrive persone, servizi e impostazioni dal pannello, mette il widget Prenota
// in una pagina, prenota come un visitatore (al computer e al telefono), controlla le email arrivate davvero, il file .ics,
// sposta e annulla dal link personale, conferma a mano, annulla con un messaggio, agenda, promemoria una volta sola,
// appuntamento svolto e "non presentato", i posti a giorni e ore fissi (prove/prenotazioni-cli.php: ora legale, fine mese),
// e alla fine spegne l'addon: le pagine del sito tornano identiche a prima.
// uso: node prove/prova-addon-prenotazioni.js <base> <cartella del CMS installato> <porta smtp> <cartella posta>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const C = require('./addon-b-comune.js');
const { ok } = C;
const [B, CMS, SMTP, POSTA] = process.argv.slice(2);
const PAG = p => B + '/admin/addon-pagina.php?a=prenotazioni&p=' + p;
const norma = h => h.replace(/nonce="[^"]+"/g, '').replace(/name="_csrf" value="[^"]+"/g, '').replace(/\?v=\d+/g, '').replace(/data-ew-orari="[^"]*"/g, '').replace(/<span class="ew-orari-frase">[^<]*/g, '');

(async () => {
  const br = await chromium.launch();
  const err = [];
  const a = await C.pagina(br, err);
  const vai = (p, u) => p.goto(u.startsWith('http') ? u : B + u, { waitUntil: 'networkidle' });
  await C.entra(a, B);
  await C.postaFinta(a, B, SMTP);

  // 0. Le pagine del sito prima dell'addon, per il confronto finale.
  const PAGINE = ['/', '/servizi/', '/contatti/'];
  const prima = {};
  for (const u of PAGINE) prima[u] = norma(await (await a.request.get(B + u)).text());
  ok('spento: la pagina dell\'addon nel pannello dà 404', (await a.goto(PAG('agenda'))).status() === 404);

  // 1. Accensione dal pannello.
  await vai(a, '/admin/addon.php');
  const carta = await a.locator('[data-addon=prenotazioni]').innerText();
  ok('Impostazioni > Addon: la carta «Prenotazioni e appuntamenti», spenta', /Prenotazioni e appuntamenti/.test(carta) && /Spento/i.test(carta), carta);
  const f1 = await C.addon(a, B, 'prenotazioni', true);
  ok('accendo: il messaggio dice che è acceso e che le tabelle sono a posto', /è acceso/.test(f1) && /tabelle a posto/.test(f1), f1);
  ok('nel menu compare Prenotazioni, con le sue cinque voci', await a.locator('nav.admin-nav a', { hasText: 'Prenotazioni' }).count() >= 1 && await a.locator('nav.admin-nav a', { hasText: 'Persone e orari' }).count() === 1);

  // 2. I posti liberi a giorni e ore fissi (prima di ogni prenotazione vera: il suo cron sposta l'orologio).
  const cliOut = execFileSync('php', [path.join(__dirname, 'prenotazioni-cli.php'), CMS], { encoding: 'utf8' }).trim().split('\n').pop();
  const x = JSON.parse(cliOut);
  ok('giorno normale: 9:00-12:00 e 15:00-18:00 ogni mezz\'ora (45 minuti + 15 di pausa)', x.lunedi.join() === '9:00,9:30,10:00,10:30,11:00,11:30,12:00,15:00,15:30,16:00,16:30,17:00,17:30,18:00', x.lunedi);
  ok('anticipo minimo di 2 ore: alle 10:10 la mattina è già chiusa', x.lunedi_anticipo[0] === '15:00', x.lunedi_anticipo);
  ok('"9-13" si legge come nel widget Orari di apertura', x.martedi_9_13.join() === '9:00,9:30,10:00,10:30,11:00,11:30,12:00');
  ok('giorno chiuso, ferie, chiusura dell\'attività, oltre 60 giorni, passato, data che non esiste: nessun posto', [x.mercoledi_chiuso, x.ferie, x.chiusura_attivita, x.oltre_60_giorni, x.passato, x.data_sbagliata].every(l => l.length === 0));
  ok('ultima domenica di marzo (ora legale): le 2:00 e le 2:30 non esistono e non si offrono', x.ora_legale_marzo.join() === '0:00,0:30,1:00,1:30,3:00,3:30,4:00,4:30,5:00', x.ora_legale_marzo);
  ok('ultima domenica di ottobre (ora solare): 11 orari, la notte dura 7 ore vere', x.ora_solare_ottobre.length === 11 && x.ottobre_ore_vere === 7, [x.ora_solare_ottobre.length, x.ottobre_ore_vere]);
  ok('fine mese, fine anno e 29 febbraio: i giorni si susseguono giusti', x.fine_gennaio.join() === '2026-01-29,2026-01-30,2026-01-31,2026-02-01,2026-02-02' && x.bisestile.join() === '2028-02-27,2028-02-28,2028-02-29,2028-03-01' && x.fine_dicembre.join() === '2026-12-30,2026-12-31,2027-01-01,2027-01-02');
  ok('una prenotazione 9:30 con la pausa toglie 9:00, 9:30 e 10:00; spostandola, i suoi posti tornano', x.con_prenotazione[0] === '10:30' && x.spostando_se_stessa[0] === '9:00');
  ok('la stessa prenotazione due volte: la seconda trova il posto preso, e l\'indice UNIQUE ferma un inserimento diretto', x.doppia.join() === 'ok,posto' && x.indice_unico === 'fermata', [x.doppia, x.indice_unico]);
  ok('promemoria a ore fisse: una volta sola, poi gli appuntamenti finiti diventano svolti', /^2 promemoria/.test(x.promemoria_1) && x.promemoria_2 === '' && /2 appuntamenti svolti/.test(x.svolte), [x.promemoria_1, x.promemoria_2, x.svolte]);

  // 3. Persone, servizi, impostazioni dal pannello.
  await vai(a, PAG('agenda'));
  ok('agenda vuota: il riquadro "Per cominciare" dice cosa fare', /Per cominciare/.test(await a.locator('.pa-avviso').first().innerText()));
  const persona = async (nome, orari, ferie) => {
    await vai(a, PAG('persone') + '&nuova=1');
    await a.fill('#pa-persona [name=nome]', nome);
    for (const [g, v] of Object.entries(orari)) await a.fill(`#pa-o-${g}`, v);
    if (ferie) { await a.fill('[name="ferie[0][dal]"]', ferie); }
    await Promise.all([a.waitForNavigation(), a.click('#pa-persona button.btn-primary')]);
    return (await a.locator('.flash').first().innerText()).trim();
  };
  const dopodomani = new Date(Date.now() + 2 * 86400000).toISOString().slice(0, 10);
  ok('aggiungo Anna con gli orari proposti', /salvata/.test(await persona('Anna', {})));
  ok('aggiungo Marco con orari suoi e un giorno di ferie', /salvata/.test(await persona('Marco', { lun: '10:00-18:00', mar: '10:00-18:00', mer: '10:00-18:00', gio: '10:00-18:00', ven: '10:00-18:00', sab: 'Chiuso', dom: '' }, dopodomani)));
  await vai(a, PAG('persone'));
  ok('l\'elenco delle persone mostra orari e ferie', await a.locator('.pa-tabella tbody tr').count() === 2 && /Ferie|nessuna/.test(await a.locator('.pa-tabella').innerText()));
  const servizio = async (nome, durata, pausa, prezzo, soloAnna) => {
    await vai(a, PAG('servizi') + '&nuovo=1');
    await a.fill('#pa-servizio [name=nome]', nome); await a.fill('[name=durata]', String(durata)); await a.fill('[name=pausa]', String(pausa)); await a.fill('[name=prezzo]', prezzo);
    if (soloAnna) await a.locator('label.pa-spunta', { hasText: 'Marco' }).locator('input').uncheck();
    await Promise.all([a.waitForNavigation(), a.click('#pa-servizio button.btn-primary')]);
    return (await a.locator('.flash').first().innerText()).trim();
  };
  ok('aggiungo il servizio Taglio donna (45 minuti, 15 di pausa, 35 €)', /salvato/.test(await servizio('Taglio donna', 45, 15, '35 €', false)));
  ok('aggiungo il servizio Colore (90 minuti), solo con Anna', /salvato/.test(await servizio('Colore', 90, 0, '60 €', true)));
  await vai(a, PAG('servizi'));
  ok('l\'elenco dei servizi dice chi li fa', /Colore[\s\S]*Anna/.test(await a.locator('.pa-tabella').innerText()) && /Taglio donna[\s\S]*Tutti/.test(await a.locator('.pa-tabella').innerText()));
  await vai(a, PAG('impostazioni'));
  await a.fill('[name=avviso_a]', 'salone@esempio.it'); await a.fill('[name=indirizzo]', 'Via dell\'Indipendenza 10, Bologna');
  await Promise.all([a.waitForNavigation(), a.click('#pa-impostazioni button.btn-primary')]);
  ok('impostazioni salvate', /salvate/.test(await a.locator('.flash').first().innerText()));
  await vai(a, PAG('impostazioni'));
  await a.fill('[name=avviso_a]', 'mario@localhost'); await Promise.all([a.waitForNavigation(), a.click('#pa-impostazioni button.btn-primary')]);
  ok('un indirizzo sbagliato per gli avvisi non si salva', /non è un indirizzo email valido/.test(await a.locator('.flash').first().innerText()));

  // 4. La pagina con il widget Prenota (dalla riga di comando, come la farebbe l'editor) e il widget nella tavolozza.
  const pg = C.cli(CMS, 'pagina', 'prenota', 'Prenota', 'prenota');
  await vai(a, '/admin/editor.php?id=' + pg.id);
  ok('il widget Prenota è nella tavolozza dell\'editor', await a.locator('.ed-widget-voce[data-tipo=prenota]').count() === 1);

  // 5. Il visitatore prenota al computer.
  const v = await C.pagina(br, err);
  const scegli = async (p, servizio, conChi) => {
    await vai(p, '/prenota/');
    const box = p.locator('[data-pren]').first();
    const val = await box.locator('select[name=servizio] option', { hasText: servizio }).getAttribute('value');
    await box.locator('select[name=servizio]').selectOption(val);
    await p.waitForSelector('.pren-giorno[aria-pressed=true]', { timeout: 10000 });
    if (conChi) {
      const rv = await box.locator('select[name=risorsa] option', { hasText: conChi }).getAttribute('value');
      await box.locator('select[name=risorsa]').selectOption(rv);
      await p.waitForFunction(() => document.querySelector('.pren-nota') === null || !/Cerco/.test(document.querySelector('.pren-orari').textContent));
    }
    await p.waitForSelector('.pren-ora', { timeout: 10000 });
    return box;
  };
  const compila = async (p, box, nome, email) => {
    const f = box.locator('form');
    await f.locator('[name=nome]').fill(nome); await f.locator('[name=email]').fill(email); await f.locator('[name=telefono]').fill('+39 333 1234567');
    await f.locator('[name=privacy]').check();
    await C.captcha(f, p);
    await Promise.all([p.waitForNavigation(), f.locator('button[type=submit]').click()]);
  };
  // Un giorno libero da fra tre giorni in poi: più in là del limite di 24 ore per spostare e annullare.
  const fraTre = new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10);
  const giornoDa = async (p, box, min) => {
    const d = await box.locator('.pren-giorno:not([disabled])').evaluateAll((bs, m) => (bs.find(b => b.dataset.d >= m) || {}).dataset?.d, min);
    if (!d) return null;
    await box.locator(`.pren-giorno[data-d="${d}"]`).click();
    await p.waitForFunction(x => document.querySelector('[name=giorno]').value === x && document.querySelector('.pren-ora'), d);
    return d;
  };
  let box = await scegli(v, 'Taglio donna', 'Anna');
  ok('il calendario: 14 giorni, almeno uno libero, il primo libero già scelto', await box.locator('.pren-giorno').count() === 14 && await box.locator('.pren-giorno:not([disabled])').count() >= 1);
  const giorno1 = await giornoDa(v, box, fraTre);
  const ora1 = await box.locator('.pren-ora').first().innerText();
  await box.locator('.pren-ora').first().click();
  ok('scelgo l\'ora: il riepilogo dice servizio, giorno, ora e persona', /Hai scelto: Taglio donna, .* alle .* con Anna/.test(await box.locator('.pren-riepilogo').innerText()), await box.locator('.pren-riepilogo').innerText());
  let quante = C.posta(POSTA).length;
  await compila(v, box, 'Giulia Rossi', 'giulia@cliente.it');
  ok('prenotato: la pagina personale dice "Prenotazione fatta" con il riepilogo', /\/prenotazione\/gestisci\//.test(v.url()) && /Prenotazione fatta/.test(await v.locator('main').innerText()) && /Taglio donna[\s\S]*Anna/.test(await v.locator('.pren-dettagli').innerText()), v.url());
  let em = await C.nuove(POSTA, quante, 2);
  const cliente1 = em.find(e => e.a === 'giulia@cliente.it'), salone1 = em.find(e => e.a === 'salone@esempio.it');
  ok('al cliente arriva "Prenotazione confermata" con il link personale e il file per il calendario', cliente1 && /Prenotazione confermata/.test(cliente1.oggetto) && C.link(cliente1).some(l => /\/prenotazione\/gestisci\/\?p=\d+&t=[a-f0-9]{40}/.test(l)) && C.link(cliente1).some(l => /\/prenotazione\/calendario\//.test(l)), cliente1 && cliente1.oggetto);
  ok('all\'attività arriva l\'avviso, con la risposta al cliente', salone1 && /Nuova prenotazione: Giulia Rossi/.test(salone1.oggetto) && /Reply-To: giulia@cliente\.it/.test(salone1.grezza) && /Indipendenza 10, Bologna/.test(salone1.html), salone1 && salone1.oggetto);
  const gestisci = C.link(cliente1).find(l => /gestisci/.test(l));
  const ics = await v.request.get(C.link(cliente1).find(l => /calendario/.test(l)));
  const icsT = await ics.text();
  ok('il file .ics si scarica: text/calendar, con inizio in UTC, servizio e indirizzo', /text\/calendar/.test(ics.headers()['content-type']) && /BEGIN:VEVENT[\s\S]*DTSTART:\d{8}T\d{6}Z[\s\S]*SUMMARY:Taglio donna/.test(icsT) && /LOCATION:Via dell'Indipendenza 10\\, Bologna/.test(icsT) && /\r\n/.test(icsT), icsT.slice(0, 200));
  // Lo stesso posto non si offre più con Anna.
  box = await scegli(v, 'Taglio donna', 'Anna');
  await box.locator(`.pren-giorno[data-d="${giorno1}"]`).click();
  await v.waitForFunction(d => document.querySelector('[name=giorno]').value === d, giorno1);
  await v.waitForTimeout(300);
  ok('quell\'orario con Anna non si offre più', !(await box.locator('.pren-ora').allInnerTexts()).includes(ora1), [giorno1, ora1]);

  // 6. Sposta e annulla dal link personale.
  await vai(v, gestisci);
  await v.click('a:has-text("Sposta l\'appuntamento")');
  await v.waitForSelector('#pren-sposta .pren-ora');
  await giornoDa(v, v.locator('#pren-sposta'), fraTre);
  const ora2 = await v.locator('#pren-sposta .pren-ora').nth(1).innerText();
  await v.locator('#pren-sposta .pren-ora').nth(1).click();
  quante = C.posta(POSTA).length;
  await Promise.all([v.waitForNavigation(), v.click('#pren-sposta button[type=submit]')]);
  ok('sposto: "l\'appuntamento è spostato", con la nuova ora', /spostato/.test(await v.locator('.pren-ok').innerText()) && (await v.locator('.pren-dettagli').innerText()).includes(ora2), ora2);
  em = await C.nuove(POSTA, quante, 2);
  ok('email "Prenotazione spostata" al cliente e "spostata dal cliente" all\'attività', em.some(e => e.a === 'giulia@cliente.it' && /spostata/.test(e.oggetto)) && em.some(e => e.a === 'salone@esempio.it' && /spostata dal cliente/.test(e.oggetto) && /Prima era/.test(e.html)), em.map(e => e.oggetto));
  await v.click('a:has-text("Annulla la prenotazione")');
  ok('annullare chiede conferma', /Vuoi davvero annullare/.test(await v.locator('.pren-conferma').innerText()));
  quante = C.posta(POSTA).length;
  await Promise.all([v.waitForNavigation(), v.click('button:has-text("Sì, annulla")')]);
  ok('annullata: la pagina lo dice e non offre più i pulsanti', /annullata/.test(await v.locator('.pren-ok').innerText()) && /Annullata/.test(await v.locator('.pren-dettagli').innerText()) && await v.locator('a:has-text("Annulla la prenotazione")').count() === 0);
  em = await C.nuove(POSTA, quante, 2);
  ok('email di annullamento al cliente e all\'attività', em.some(e => e.a === 'giulia@cliente.it' && /annullata/i.test(e.oggetto)) && em.some(e => e.a === 'salone@esempio.it' && /annullata dal cliente/.test(e.oggetto)), em.map(e => e.oggetto));
  const rotto = gestisci.replace(/t=([a-f0-9])/, (m, c) => 't=' + (c === 'a' ? 'b' : 'a'));
  const r404 = await v.goto(rotto);
  ok('un link personale manomesso: "Questo link non funziona"', r404.status() === 404 && /Questo link non funziona/.test(await v.locator('h1').innerText()));

  // 7. Conferma a mano, al telefono.
  await vai(a, PAG('impostazioni')); await a.check('[name=da_confermare]'); await Promise.all([a.waitForNavigation(), a.click('#pa-impostazioni button.btn-primary')]);
  const t = await C.pagina(br, err, { telefono: true });
  box = await scegli(t, 'Colore');
  ok('telefono: "Con chi" sparisce per un servizio che fa solo Anna', await box.locator('label.pren-con').isHidden());
  ok('telefono: la pagina con il calendario non scorre di lato', await C.largo(t) <= 0, await C.largo(t));
  await box.locator('.pren-ora').first().click();
  quante = C.posta(POSTA).length;
  await compila(t, box, 'Sara Neri', 'sara@cliente.it');
  ok('telefono: "Richiesta ricevuta", da confermare', /Richiesta ricevuta/.test(await t.locator('.pren-ok').innerText()) && /In attesa di conferma/.test(await t.locator('.pren-dettagli').innerText()));
  ok('telefono: la pagina personale non scorre di lato', await C.largo(t) <= 0);
  em = await C.nuove(POSTA, quante, 2);
  ok('email "Richiesta di prenotazione ricevuta" al cliente e "da confermare" all\'attività', em.some(e => e.a === 'sara@cliente.it' && /Richiesta di prenotazione ricevuta/.test(e.oggetto)) && em.some(e => e.a === 'salone@esempio.it' && /da confermare/.test(e.oggetto)), em.map(e => e.oggetto));
  await vai(a, PAG('agenda'));
  ok('agenda: "1 prenotazione aspetta la tua conferma"', /1 prenotazione aspetta la tua conferma/.test(await a.locator('.pa-avviso').first().innerText()));
  await a.click('.pa-avviso a');
  await a.click('.pa-tabella a:has-text("Sara Neri")');
  quante = C.posta(POSTA).length;
  await Promise.all([a.waitForNavigation(), a.click('button[value=conferma]')]);
  ok('confermo dal pannello', /Confermata/.test(await a.locator('.flash').first().innerText()) && (await a.getAttribute('#pa-scheda', 'data-stato')) === 'confermata');
  em = await C.nuove(POSTA, quante, 1);
  ok('al cliente arriva la conferma', em.some(e => e.a === 'sara@cliente.it' && /confermata/.test(e.oggetto)));
  await a.fill('#pa-annulla textarea', 'Ci scusiamo: quel giorno siamo chiusi per un imprevisto.');
  quante = C.posta(POSTA).length;
  await Promise.all([a.waitForNavigation(), a.click('#pa-annulla button')]);
  ok('annullo dal pannello con un messaggio', /con il tuo messaggio/.test(await a.locator('.flash').first().innerText()));
  em = await C.nuove(POSTA, quante, 1);
  ok('il messaggio arriva al cliente, nell\'email di annullamento', em.some(e => e.a === 'sara@cliente.it' && /chiusi per un imprevisto/.test(e.html)));
  await vai(a, PAG('impostazioni')); await a.uncheck('[name=da_confermare]'); await Promise.all([a.waitForNavigation(), a.click('#pa-impostazioni button.btn-primary')]);

  // 8. Agenda, promemoria, svolto e non presentato.
  box = await scegli(v, 'Taglio donna');
  const g3 = await box.locator('.pren-giorno[aria-pressed=true]').getAttribute('data-d');
  await box.locator('.pren-ora').first().click();
  await compila(v, box, 'Luca Bianchi', 'luca@cliente.it');
  const id3 = +new URL(v.url()).searchParams.get('p');
  await vai(a, PAG('agenda') + '&d=' + g3);
  ok('agenda della settimana: c\'è la prenotazione di Luca', await a.locator(`.pa-giorno[data-giorno="${g3}"] .pa-voce`, { hasText: 'Luca Bianchi' }).count() === 1);
  await vai(a, PAG('agenda') + '&vista=giorno&d=' + g3);
  ok('agenda del giorno: Luca con orario e telefono', /Luca Bianchi[\s\S]*\+39 333/.test(await a.locator('.pa-giornata').innerText()));
  const at = await C.pagina(br, err, { telefono: true });
  await C.entra(at, B);
  await vai(at, PAG('agenda'));
  ok('agenda al telefono: niente scorrimento di lato', await C.largo(at) <= 0, await C.largo(at));
  const larghe = [];
  for (const u of ['elenco&filtro=tutte', 'elenco&id=' + id3, 'servizi', 'servizi&nuovo=1', 'persone', 'persone&nuova=1', 'impostazioni', 'agenda&vista=giorno&d=' + g3]) { await vai(at, PAG(u)); if (await C.largo(at) > 0) larghe.push(u); }
  ok('le altre pagine del pannello al telefono: nessuna scorre di lato', larghe.length === 0, larghe);
  const riga = C.cli(CMS, 'sql', `SELECT inizio_ts FROM {p}pren_prenotazioni WHERE id = ${id3}`)[0];
  C.cli(CMS, 'sql', `UPDATE {p}pren_prenotazioni SET creato = DATE_SUB(creato, INTERVAL 5 DAY) WHERE id = ${id3}`);
  const avanti = Math.max(0, riga.inizio_ts - 6 * 3600 - Math.floor(Date.now() / 1000));
  quante = C.posta(POSTA).length;
  const c1 = C.cli(CMS, 'cron', String(avanti));
  const c2 = C.cli(CMS, 'cron', String(avanti + 60));
  em = await C.nuove(POSTA, quante, 1);
  ok('il cron frequente manda il promemoria il giorno prima, una volta sola', em.filter(e => e.a === 'luca@cliente.it' && /Promemoria/.test(e.oggetto)).length === 1 && /^1 promemoria/.test(c1.prenotazioni || '') && !/^[1-9] promemoria/.test(c2.prenotazioni || ''), [c1, c2, em.map(e => e.a + ' ' + e.oggetto)]);
  await vai(a, '/admin/cron-log.php');
  await Promise.all([a.waitForNavigation(), a.click('#cron-esegui')]);
  ok('Log cron > Esegui adesso: il giro gira senza errori', /giro della newsletter fatto/.test(await a.locator('.flash').first().innerText()) && await a.locator('.flash-error').count() === 0);
  // Un appuntamento finito diventa svolto; poi "non si è presentato".
  const ora = Math.floor(Date.now() / 1000);
  C.cli(CMS, 'sql', `UPDATE {p}pren_prenotazioni SET inizio_ts = ${ora - 7200}, fine_ts = ${ora - 4500}, blocco_ts = ${ora - 3600}, inizio = FROM_UNIXTIME(${ora - 7200}), fine = FROM_UNIXTIME(${ora - 4500}) WHERE id = ${id3}`);
  C.cli(CMS, 'cron');
  ok('appuntamento finito: il cron lo segna "svolta"', C.cli(CMS, 'sql', `SELECT stato FROM {p}pren_prenotazioni WHERE id = ${id3}`)[0].stato === 'svolta');
  await vai(a, PAG('elenco') + '&id=' + id3);
  await Promise.all([a.waitForNavigation(), a.click('button[value=non_presentato]')]);
  ok('segno "Non si è presentato"', (await a.getAttribute('#pa-scheda', 'data-stato')) === 'non_presentato' && /non presentato/i.test(await a.locator('#pa-scheda .badge').innerText()));

  // 9. Spento: il sito torna com'era, i dati restano.
  const f2 = await C.addon(a, B, 'prenotazioni', false);
  ok('spengo: "i suoi dati restano"', /spento/.test(f2) && /restano/.test(f2), f2);
  ok('spento: niente più Prenotazioni nel menu, la pagina del pannello dà 404, i posti dal sito pure', await a.locator('nav.admin-nav a', { hasText: 'Persone e orari' }).count() === 0 && (await a.goto(PAG('agenda'))).status() === 404 && (await a.request.get(B + '/prenotazione/posti/?s=1')).status() === 404);
  const diverse = [];
  for (const u of PAGINE) { const h = norma(await (await a.request.get(B + u)).text()); if (h !== prima[u]) diverse.push(u); }
  ok('spento: home, Servizi e Contatti identiche a prima di accenderlo', diverse.length === 0, diverse);
  await C.addon(a, B, 'prenotazioni', true);
  await vai(a, PAG('elenco') + '&filtro=tutte');
  ok('riacceso: le prenotazioni ci sono ancora', await a.locator('.pa-tabella tbody tr[data-pren-id]').count() >= 3);

  C.fine(err);
  await br.close();
})().catch(e => { console.error(e); process.exitCode = 1; });
