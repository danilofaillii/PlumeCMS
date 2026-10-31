// Addon Area riservata (membri, 2.0.0): tutto il percorso, come lo fanno l'amministratore e un visitatore nel browser.
// Su un sito nuovo installato con il kit Attività (prove/prova-kit.js), con il server di posta finto acceso:
//   python3 -I prove/smtp-finto.py <porta smtp> <cartella posta>
// Accende l'addon da Impostazioni > Addon, rende riservata la pagina Chi siamo e un articolo, poi un visitatore si iscrive,
// conferma l'email, entra, esce, sbaglia la password, la recupera, cambia nome, email e password, scarica un file riservato,
// cancella l'account. Poi l'iscrizione su approvazione e su invito, la sospensione, il CSV, il widget Accesso membri, il
// telefono (390x844) senza scorrimento di lato. Alla fine spegne l'addon: il sito torna identico a prima di accenderlo.
// uso: node prove/prova-addon-membri.js <base> <cartella CMS> <utente> <password> <porta smtp> <cartella posta>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const { conta, pagina, entraPannello, addon, postaFinta, posta, aspettaEmail, linkDa, largo } = require('./addon-a-comune');
const [B, DIR, UTENTE, PASSWORD, SMTP, POSTA] = process.argv.slice(2);
const { ok, fine } = conta();
const PW = 'Lampone-Verde-2026!';
const PW2 = 'Mirtillo-Giallo-2027!';
const PW3 = 'Ciliegia-Blu-2028!';
const dorme = ms => new Promise(r => setTimeout(r, ms));
const MEMBRI = 'addon-pagina.php?a=membri&p=';
// Il sito come lo vede un visitatore, per confrontarlo prima e dopo l'addon: senza nonce e senza i numeri che cambiano da soli.
const pulisci = h => h.replace(/ nonce="[^"]*"/g, '').replace(/\?v=\d+/g, '').replace(/data-fine="[^"]*"/g, '').replace(/<span class="ew-cd-[a-z]+">\d+<\/span>/g, '');

let br;
(async () => {
  br = await chromium.launch();
  const err = [];
  const a = await pagina(br, err);
  const vaiA = u => a.goto(B + u, { waitUntil: 'networkidle' });
  await entraPannello(a, B, UTENTE, PASSWORD);
  await postaFinta(a, B, SMTP);
  await addon(a, B, 'membri', false);
  const BASEP = new URL(B).pathname.replace(/\/$/, '');

  // 0. Il sito prima: home, Chi siamo, un articolo, e /accedi/ che non esiste.
  const v0 = await pagina(br, err);
  await v0.goto(B + '/blog/', { waitUntil: 'networkidle' });
  const articolo = new URL(await v0.locator('main a[href]:not([href$="/blog/"])').first().getAttribute('href'), B + '/').pathname.slice(BASEP.length);
  const prima = {};
  for (const u of ['/', '/chi-siamo/', articolo]) prima[u] = pulisci(await (await v0.request.get(B + u)).text());
  await v0.goto(B + '/chi-siamo/', { waitUntil: 'networkidle' });
  const rigaChi = (await v0.locator('main').innerText()).split('\n').map(x => x.trim()).filter(x => x.length > 60)[0] || '@@';
  ok('prima dell\'addon: /accedi/ non esiste (404)', (await v0.request.get(B + '/accedi/')).status() === 404);
  // 2.1: i titoli e gli elenchi pubblici prima (ricerca, sitemap, feed, llms.txt), per vedere dopo che i riservati spariscono.
  const php = codice => execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='127.0.0.1'; require '${DIR}/includes/config.php'; ${codice}`], { encoding: 'utf8' });
  const slugA = articolo.replace(/\//g, '');
  const tit = JSON.parse(php(`echo json_encode(['chi' => db()->query("SELECT title FROM " . tbl('pages') . " WHERE slug = 'chi-siamo'")->fetchColumn(), 'servizi' => db()->query("SELECT title FROM " . tbl('pages') . " WHERE slug = 'servizi'")->fetchColumn(), 'art' => db()->query("SELECT title FROM " . tbl('articles') . " WHERE slug = " . db()->quote('${slugA}'))->fetchColumn()]);`));
  const htmlArt = await (await v0.request.get(B + articolo)).text();
  const catArt = ((htmlArt.match(/href="[^"]*(\/category\/[^"\/]+\/)"/) || [])[1]) || '';
  const annoArt = '/' + php(`echo substr((string)db()->query("SELECT publish_date FROM " . tbl('articles') . " WHERE slug = " . db()->quote('${slugA}'))->fetchColumn(), 0, 4);`).trim() + '/';
  const elenchi = async ctx => {
    const g = async u => (await ctx.request.get(B + u)).text();
    // della pagina della ricerca solo i risultati (il menu del sito ha anche lui i link a Chi siamo e Servizi)
    const cerca = async q => ((await g('/?s=' + encodeURIComponent(q))).split('class="griglia"')[1] || '');
    // 2.1: anche blog, la categoria e l'anno dell'articolo (archivi), solo il corpo della pagina.
    const corpo = async u => ((await g(u)).split('<main')[1] || '').split('</main>')[0];
    return { blog: await corpo('/blog/'), categoria: catArt ? await corpo(catArt) : '', anno: await corpo(annoArt), sitemap: await g('/sitemap.xml'), feed: await g('/feed/'), llms: await g('/llms.txt'), cercaChi: await cerca(tit.chi), cercaArt: await cerca(tit.art), cercaServizi: await cerca(tit.servizi) };
  };
  const el0 = await elenchi(v0);
  ok('prima: Chi siamo, Servizi e l\'articolo sono nella sitemap e nella ricerca; l\'articolo nel feed e in llms.txt (le pagine non ci sono mai)',
    ['/chi-siamo/', '/servizi/', articolo].every(u => el0.sitemap.includes(u + '<') || el0.sitemap.includes(u + '</loc>')) && el0.cercaChi.includes('href="' + BASEP + '/chi-siamo/"') && el0.cercaArt.includes(articolo + '"')
    && el0.cercaServizi.includes('/servizi/"') && el0.feed.includes(articolo) && el0.llms.includes(articolo), tit);
  ok('prima: l\'articolo è nel blog, nella sua categoria e nel suo anno (titolo compreso)', el0.blog.includes(tit.art) && (!catArt || el0.categoria.includes(tit.art)) && el0.anno.includes(tit.art), { catArt, annoArt });

  // 1. Accendere l'addon.
  const f1 = await addon(a, B, 'membri', true);
  ok('Impostazioni > Addon: "Area riservata" si accende', /Area riservata.*acceso/.test(f1), f1);
  ok('il menu del pannello ha la voce Membri con le sue tre pagine', await a.locator('nav.admin-nav a', { hasText: 'Membri' }).count() >= 1 && await a.locator('.nav-sotto a', { hasText: 'Contenuti e file riservati' }).count() === 1);
  await vaiA('/admin/' + MEMBRI + 'elenco');
  ok('Elenco dei membri: vuoto, con Invita ed Esporta', /Ancora nessuno/.test(await a.locator('main, .admin-main, body').first().innerText()) && await a.locator('#mb-invita').count() === 1);

  // 2. Chi siamo e un articolo solo per i membri.
  await vaiA('/admin/' + MEMBRI + 'riservati');
  const slugArt = articolo.replace(/\//g, '');
  await a.locator('label.mr-voce', { hasText: '/chi-siamo/' }).locator('input').check();
  await a.locator('label.mr-voce', { hasText: '/' + slugArt + '/' }).locator('input').check();
  await Promise.all([a.waitForNavigation(), a.click('#mr-contenuti button[type=submit]')]);
  ok('Contenuti riservati: Chi siamo e l\'articolo salvati', /2 contenuti sono solo per i membri/.test(await a.locator('.flash').first().innerText()));
  ok('la home non si può rendere riservata', await a.locator('label.mr-voce input[disabled]').count() === 1);
  // 2b. (2.1) Dalla scheda del contenuto: il riquadro "Area riservata".
  const idPag = s => php(`echo db()->query("SELECT id FROM " . tbl('pages') . " WHERE slug = '${s}'")->fetchColumn();`).trim();
  await vaiA('/admin/contenuto.php?tipo=pagine&id=' + idPag('chi-siamo'));
  ok('scheda di Chi siamo: il riquadro "Area riservata" con "Solo per i membri" già spuntato (scelto dalla pagina Membri)', await a.locator('#membri-scheda').count() === 1 && await a.locator('#membri-riservato').isChecked());
  await vaiA('/admin/contenuto.php?tipo=pagine&id=' + idPag('servizi'));
  ok('scheda di Servizi: la spunta c\'è e non è messa', await a.locator('#membri-riservato').count() === 1 && !(await a.locator('#membri-riservato').isChecked()));
  await a.check('#membri-riservato');
  await Promise.all([a.waitForNavigation(), a.click('#sc-form button[data-valore=pubblica]')]);
  ok('Servizi riservata dalla scheda: dopo Aggiorna la spunta resta', /aggiornat/i.test(await a.locator('.flash').first().innerText()) && await a.locator('#membri-riservato').isChecked());
  await vaiA('/admin/' + MEMBRI + 'riservati');
  ok('e la pagina Membri > Contenuti e file riservati la mostra spuntata', await a.locator('label.mr-voce', { hasText: '/servizi/' }).locator('input').isChecked());
  const home = php("echo get_setting('pagina_home');").trim();
  await vaiA('/admin/contenuto.php?tipo=pagine&id=' + idPag(home));
  ok('scheda della home: la spunta è spenta ("La home resta di tutti")', await a.locator('#membri-riservato').isDisabled() && /La home resta di tutti/.test(await a.locator('#membri-scheda').innerText()));
  await vaiA('/admin/contenuto.php?tipo=articoli&id=' + php(`echo db()->query("SELECT id FROM " . tbl('articles') . " WHERE slug = " . db()->quote('${slugA}'))->fetchColumn();`).trim());
  ok('scheda dell\'articolo: riservato (scelto dalla pagina Membri)', await a.locator('#membri-riservato').isChecked());
  // Togliere la spunta dalla scheda e rimetterla: torna pubblico, poi di nuovo riservato.
  await vaiA('/admin/contenuto.php?tipo=pagine&id=' + idPag('servizi'));
  await a.uncheck('#membri-riservato');
  await Promise.all([a.waitForNavigation(), a.click('#sc-form button[data-valore=pubblica]')]);
  const aperta = (await (await v0.request.get(B + '/servizi/')).text());
  await a.check('#membri-riservato');
  await Promise.all([a.waitForNavigation(), a.click('#sc-form button[data-valore=pubblica]')]);
  const chiusa = (await (await v0.request.get(B + '/servizi/')).text());
  ok('spunta tolta dalla scheda: Servizi è di tutti; rimessa: di nuovo solo per i membri', !/data-membri-chiuso/.test(aperta) && /data-membri-chiuso/.test(chiusa));

  // 3. Il visitatore senza account.
  const v = await pagina(br, err);
  const vai = u => v.goto(B + u, { waitUntil: 'networkidle' });
  let r = await vai('/chi-siamo/');
  const chiuso = await v.locator('main').innerText();
  ok('Chi siamo, da fuori: titolo e invito ad accedere, il testo no', r.status() === 200 && /solo per i membri/i.test(chiuso) && /Accedi/.test(chiuso) && !chiuso.includes(rigaChi), chiuso.slice(0, 200));
  ok('Chi siamo, da fuori: noindex e niente cache', /noindex/.test(await v.locator('meta[name=robots]').getAttribute('content')) && /no-store/.test(r.headers()['cache-control'] || ''));
  r = await vai(articolo);
  ok('l\'articolo riservato, da fuori: chiuso anche lui', /solo per i membri/i.test(await v.locator('main').innerText()));
  const llms = await (await v.request.get(B + '/llms-full.txt')).text();
  ok('llms-full.txt non ha il testo di Chi siamo', !llms.includes(rigaChi.slice(0, 50)));
  // 2.1: fuori dagli elenchi pubblici, titolo e riassunto compresi.
  const el1 = await elenchi(v);
  ok('da fuori: la ricerca non trova i contenuti riservati nemmeno per titolo (Chi siamo, Servizi, l\'articolo)', !el1.cercaChi.includes('/chi-siamo/"') && !el1.cercaServizi.includes('/servizi/"') && !el1.cercaArt.includes(articolo + '"'));
  ok('da fuori: la sitemap non elenca i contenuti riservati', !el1.sitemap.includes('/chi-siamo/') && !el1.sitemap.includes('/servizi/') && !el1.sitemap.includes(articolo), el1.sitemap.length);
  ok('da fuori: il feed RSS non ha l\'articolo riservato (né titolo né riassunto)', !el1.feed.includes(articolo) && !el1.feed.includes(tit.art));
  ok('da fuori: blog, categoria e archivio dell\'anno non hanno il titolo dell\'articolo riservato', !el1.blog.includes(tit.art) && !el1.categoria.includes(tit.art) && !el1.anno.includes(tit.art) && !el1.blog.includes(articolo + '"'));
  ok('da fuori: llms.txt non elenca i contenuti riservati', !el1.llms.includes('/chi-siamo/') && !el1.llms.includes('/servizi/') && !el1.llms.includes(articolo) && !el1.llms.includes(tit.art));
  ok('da fuori: llms-full.txt non ha l\'articolo riservato', !llms.includes(articolo) && !llms.includes('## ' + tit.art));
  r = await vai('/area-riservata/');
  ok('/area-riservata/ da fuori porta a /accedi/ con il ritorno', /\/accedi\/\?m=entra&torna=/.test(v.url()) && /entra con la tua email/.test(await v.locator('.membri-ok').innerText()));

  // 4. Iscrizione con conferma dell'email.
  await vai('/iscriviti/');
  ok('Iscriviti: il modulo con nome, email, due password, privacy e "Sono una persona"', await v.locator('#membri-iscriviti input[name=password2]').count() === 1 && await v.locator('#membri-iscriviti .captcha').count() === 1);
  const iscrivi = async (nome, email, pw, pw2 = pw) => {
    await vai('/iscriviti/');
    await v.fill('#membri-iscriviti [name=nome]', nome); await v.fill('#membri-iscriviti [name=email]', email);
    await v.fill('#membri-iscriviti [name=password]', pw); await v.fill('#membri-iscriviti [name=password2]', pw2);
    await v.check('#membri-iscriviti [name=privacy]');
    // Il controllo del server, non solo quello del browser (minlength): si toglie il limite del campo.
    await v.evaluate(() => document.querySelectorAll('#membri-iscriviti [minlength]').forEach(x => x.removeAttribute('minlength')));
    await v.check('#membri-iscriviti .captcha input');
    await v.waitForFunction(() => /Verificato/.test(document.querySelector('.captcha-stato').textContent), null, { timeout: 30000 });
    await Promise.all([v.waitForNavigation(), v.click('#membri-iscriviti button[type=submit]')]);
    return (await v.locator('main').innerText()).trim();
  };
  let t = await iscrivi('Giulia Prova', 'giulia@esempio.it', 'corta', 'corta');
  ok('una password corta si rifiuta, e si dice perché', /almeno 12 caratteri/.test(t), t.slice(0, 160));
  t = await iscrivi('Giulia Prova', 'giulia@esempio.it', PW, PW + 'x');
  ok('due password diverse si rifiutano', /non sono uguali/.test(t));
  let da = posta(POSTA).length;
  t = await iscrivi('Giulia Prova', 'giulia@esempio.it', PW);
  ok('iscrizione: "Controlla la tua email"', /Controlla la tua email/.test(t));
  let m = await aspettaEmail(POSTA, 'giulia@esempio.it', da);
  const linkConferma = linkDa(m, '/conferma-iscrizione/?t=');
  ok('arriva l\'email di conferma con il link', !!linkConferma && /Conferma la tua iscrizione/.test(m.oggetto), m && m.oggetto);
  await v.goto(B + '/accedi/', { waitUntil: 'networkidle' });
  await v.fill('#membri-accedi [name=email]', 'giulia@esempio.it'); await v.fill('#membri-accedi [name=password]', PW);
  await Promise.all([v.waitForNavigation(), v.click('#membri-accedi button[type=submit]')]);
  ok('prima della conferma non si entra, e si dice di confermare', /conferma la tua email/i.test(await v.locator('.modulo-errore').innerText()));
  await v.goto(linkConferma, { waitUntil: 'networkidle' });
  ok('il link apre una pagina con il pulsante (non conferma da solo)', /Conferma l'iscrizione/.test(await v.locator('h1').innerText()));
  await Promise.all([v.waitForNavigation(), v.click('main form button[type=submit]')]);
  ok('confermato: dentro l\'area riservata, salutato per nome', /\/area-riservata\/\?m=benvenuto/.test(v.url()) && /Ciao Giulia Prova/.test(await v.locator('main').innerText()));
  ok('l\'area riservata elenca i contenuti per i membri', await v.locator('.membri-lista a[href$="/chi-siamo/"]').count() === 1);
  await v.goto(linkConferma, { waitUntil: 'networkidle' });
  ok('il link di conferma vale una volta sola', /non vale più/.test(await v.locator('h1').innerText()));
  // 2.1: il membro collegato trova i riservati nella ricerca (risposta privata, non in cache); gli elenchi pubblici restano senza.
  const rc = await v.request.get(B + '/?s=' + encodeURIComponent(tit.chi));
  const el2 = await elenchi(v);
  ok('da membro: la ricerca trova Chi siamo, Servizi e l\'articolo riservati', ((await rc.text()).split('class="griglia"')[1] || '').includes('/chi-siamo/"') && el2.cercaServizi.includes('/servizi/"') && el2.cercaArt.includes(articolo + '"'));
  ok('da membro: la pagina dei risultati è privata (Cache-Control private, no-store)', /private/.test(rc.headers()['cache-control'] || '') && /no-store/.test(rc.headers()['cache-control'] || ''), rc.headers()['cache-control']);
  ok('da membro: blog, categoria e archivio dell\'anno mostrano l\'articolo riservato', el2.blog.includes(tit.art) && (!catArt || el2.categoria.includes(tit.art)) && el2.anno.includes(tit.art));
  ok('da membro: sitemap, feed e llms.txt restano senza i riservati', !el2.sitemap.includes('/chi-siamo/') && !el2.feed.includes(articolo) && !el2.llms.includes('/servizi/'));
  r = await vai('/chi-siamo/');
  ok('Chi siamo, da membro: il testo intero', !/solo per i membri/i.test(await v.locator('main').innerText()) && /private, no-store/.test(r.headers()['cache-control'] || ''));
  const cookie = (await v.context().cookies()).find(c => /^membri_[a-f0-9]{8}$/.test(c.name));
  ok('il cookie del membro: HttpOnly, SameSite Lax, token di 64 caratteri', !!cookie && cookie.httpOnly && cookie.sameSite === 'Lax' && /^[a-f0-9]{64}$/.test(cookie.value), cookie);
  m = await aspettaEmail(POSTA, 'avvisi-sito@esempio.it', da);
  ok('l\'amministratore riceve l\'avviso del nuovo membro', !!m && /Nuovo membro/.test(m.oggetto));

  // 5. Esci, accesso sbagliato, accesso giusto.
  await vai('/area-riservata/');
  await Promise.all([v.waitForNavigation(), v.click('form.membri-esci button')]);
  ok('Esci: "Sei uscito"', /\/accedi\/\?m=uscito/.test(v.url()) && /Sei uscito/.test(await v.locator('.membri-ok').innerText()));
  r = await vai('/chi-siamo/');
  ok('dopo l\'uscita Chi siamo è di nuovo chiuso', /solo per i membri/i.test(await v.locator('main').innerText()));
  const accedi = async (email, pw, torna = '') => {
    await vai('/accedi/' + torna);
    await v.fill('#membri-accedi [name=email]', email); await v.fill('#membri-accedi [name=password]', pw);
    await Promise.all([v.waitForNavigation(), v.click('#membri-accedi button[type=submit]')]);
    return (await v.locator('main').innerText()).trim();
  };
  const e1 = await accedi('giulia@esempio.it', 'sbagliata-davvero-123');
  const e2 = await accedi('nessuno@esempio.it', 'sbagliata-davvero-123');
  ok('password sbagliata ed email che non esiste: lo stesso messaggio', /Email o password non corrette/.test(e1) && /Email o password non corrette/.test(e2));
  await vai('/chi-siamo/');
  await Promise.all([v.waitForNavigation(), v.click('main a.btn:has-text("Accedi")')]);
  await v.fill('#membri-accedi [name=email]', 'giulia@esempio.it'); await v.fill('#membri-accedi [name=password]', PW);
  await Promise.all([v.waitForNavigation(), v.click('#membri-accedi button[type=submit]')]);
  ok('Accedi dalla pagina chiusa: dopo l\'accesso si torna a Chi siamo, aperta', /\/chi-siamo\/$/.test(v.url()) && !/solo per i membri/i.test(await v.locator('main').innerText()), v.url());
  r = await v.request.get(B + '/admin/index.php', { maxRedirects: 0 });
  ok('il cookie del membro non apre il pannello', r.status() === 302 && /login\.php/.test(r.headers().location || ''));

  // 6. Profilo: nome, password, email.
  await vai('/area-riservata/profilo/');
  await v.fill('#membri-nome [name=nome]', 'Giulia Rossi');
  await Promise.all([v.waitForNavigation(), v.click('#membri-nome button')]);
  ok('profilo: il nome cambia', /salvate/.test(await v.locator('.membri-ok').innerText()) && await v.inputValue('#membri-nome [name=nome]') === 'Giulia Rossi');
  await v.fill('#membri-password [name=attuale]', 'non-e-questa-123'); await v.fill('#membri-password [name=password]', PW2); await v.fill('#membri-password [name=password2]', PW2);
  await Promise.all([v.waitForNavigation(), v.click('#membri-password button')]);
  ok('profilo: senza la password attuale giusta la password non cambia', /attuale non è giusta/.test(await v.locator('.modulo-errore').innerText()));
  await v.fill('#membri-password [name=attuale]', PW); await v.fill('#membri-password [name=password]', PW2); await v.fill('#membri-password [name=password2]', PW2);
  await Promise.all([v.waitForNavigation(), v.click('#membri-password button')]);
  ok('profilo: la password cambia', /Password cambiata/.test(await v.locator('.membri-ok').innerText()));
  da = posta(POSTA).length;
  await v.fill('#membri-email [name=email]', 'giulia.rossi@esempio.it'); await v.fill('#membri-email [name=attuale]', PW2);
  await Promise.all([v.waitForNavigation(), v.click('#membri-email button')]);
  ok('profilo: email nuova, "ti abbiamo mandato un link"', /link al nuovo indirizzo/.test(await v.locator('.membri-ok').innerText()));
  m = await aspettaEmail(POSTA, 'giulia.rossi@esempio.it', da);
  await v.goto(linkDa(m, '/conferma-email/?t='), { waitUntil: 'networkidle' });
  await Promise.all([v.waitForNavigation(), v.click('main form button[type=submit]')]);
  ok('il link al nuovo indirizzo cambia l\'email', /nuovo indirizzo email/.test(await v.locator('.membri-ok').innerText()));
  ok('e al vecchio indirizzo arriva "Il tuo indirizzo email è cambiato"', !!(await aspettaEmail(POSTA, 'giulia@esempio.it', da, x => /indirizzo email è cambiato/.test(x.oggetto))));
  await vai('/area-riservata/');
  await Promise.all([v.waitForNavigation(), v.click('form.membri-esci button')]);
  ok('con la vecchia email non si entra più, con la nuova sì', /non corrette/.test(await accedi('giulia@esempio.it', PW2)) && /La tua area riservata/.test(await accedi('giulia.rossi@esempio.it', PW2)));
  await Promise.all([v.waitForNavigation(), v.click('form.membri-esci button')]);

  // 7. Password dimenticata.
  da = posta(POSTA).length;
  await vai('/password-dimenticata/');
  await v.fill('#membri-dimenticata [name=email]', 'giulia.rossi@esempio.it');
  await Promise.all([v.waitForNavigation(), v.click('#membri-dimenticata button')]);
  const tDim = await v.locator('main').innerText();
  await vai('/password-dimenticata/');
  await v.fill('#membri-dimenticata [name=email]', 'sconosciuto@esempio.it');
  await Promise.all([v.waitForNavigation(), v.click('#membri-dimenticata button')]);
  ok('password dimenticata: la stessa risposta per un\'email che c\'è e una che non c\'è', tDim === await v.locator('main').innerText() && /Controlla la tua email/.test(tDim));
  m = await aspettaEmail(POSTA, 'giulia.rossi@esempio.it', da);
  ok('arriva solo all\'email che c\'è', !!m && posta(POSTA).slice(da).every(x => !x.dest.includes('sconosciuto')));
  await v.goto(linkDa(m, '/nuova-password/?t='), { waitUntil: 'networkidle' });
  await v.fill('#membri-nuova-password [name=password]', PW3); await v.fill('#membri-nuova-password [name=password2]', PW3);
  await Promise.all([v.waitForNavigation(), v.click('#membri-nuova-password button')]);
  ok('password nuova scelta: "Entra con quella nuova"', /\/accedi\/\?m=password/.test(v.url()));
  ok('si entra con la password nuova, non con la vecchia', /non corrette/.test(await accedi('giulia.rossi@esempio.it', PW2)) && /La tua area riservata/.test(await accedi('giulia.rossi@esempio.it', PW3)));
  await v.goto(linkDa(m, '/nuova-password/?t='), { waitUntil: 'networkidle' });
  ok('il link della password vale una volta sola', /non vale più/.test(await v.locator('h1').innerText()));

  // 8. Iscriversi di nuovo con un'email che c'è: stessa risposta, email al proprietario.
  await vai('/area-riservata/'); await Promise.all([v.waitForNavigation(), v.click('form.membri-esci button')]);
  da = posta(POSTA).length;
  t = await iscrivi('Qualcun Altro', 'giulia.rossi@esempio.it', 'Pesca-Rossa-2029!');
  m = await aspettaEmail(POSTA, 'giulia.rossi@esempio.it', da);
  ok('iscrizione con un\'email già iscritta: stessa risposta, e al proprietario "Hai già un account"', /Controlla la tua email/.test(t) && !!m && /Hai già un account/.test(m.oggetto));
  ok('e la password non è cambiata', /La tua area riservata/.test(await accedi('giulia.rossi@esempio.it', PW3)));

  // 9. Un file riservato di Download dato ai membri.
  const pdf = path.join(require('os').tmpdir(), 'listino-membri.pdf');
  fs.writeFileSync(pdf, '%PDF-1.4\n% listino per i membri\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n');
  await vaiA('/admin/scaricabili.php');
  await a.setInputFiles('#dl-carica input[type=file]', pdf);
  await a.fill('#dl-carica [name=titolo]', 'Listino per i membri');
  await a.check('#dl-riservato');
  await Promise.all([a.waitForNavigation(), a.click('#dl-carica button[type=submit]')]);
  await vaiA('/admin/' + MEMBRI + 'riservati');
  await a.locator('#mr-file label.mr-voce', { hasText: 'listino-membri.pdf' }).locator('input').check();
  await Promise.all([a.waitForNavigation(), a.click('#mr-file button[type=submit]')]);
  ok('File per i membri: il listino riservato è scelto', /1 file/.test(await a.locator('.flash').first().innerText()));
  await vai('/area-riservata/');
  const lf = v.locator('a.membri-file', { hasText: 'Listino per i membri' });
  ok('l\'area riservata mostra il file', await lf.count() === 1);
  const [dl] = await Promise.all([v.waitForEvent('download'), lf.click()]);
  const scaricato = fs.readFileSync(await dl.path(), 'utf8');
  ok('il membro scarica il file', scaricato.startsWith('%PDF') && dl.suggestedFilename() === 'listino-membri.pdf');
  r = await v0.request.get(B + '/area-riservata/file/listino-membri/', { maxRedirects: 0 });
  ok('da fuori il file non si scarica (porta all\'accesso)', r.status() === 303 && /\/accedi\//.test(r.headers().location || ''));
  r = await v0.request.get(B + '/scarica/listino-membri/');
  ok('e nemmeno dall\'indirizzo pubblico di Download (404)', r.status() === 404);

  // 10. Il widget Accesso membri in una pagina.
  execFileSync('php', [path.join(__dirname, 'pagina-addon-a.php'), DIR, 'prova-membri', 'Prova membri', 'membri_accesso']);
  await vai('/prova-membri/');
  ok('widget Accesso membri, da membro: "Ciao" e "Area riservata"', /Ciao, Giulia Rossi/.test(await v.locator('.membri-accesso').innerText()) && await v.locator('.membri-accesso a[href$="/area-riservata/"]').count() === 1);
  await v0.goto(B + '/prova-membri/', { waitUntil: 'networkidle' });
  ok('widget Accesso membri, da fuori: "Accedi" e "Iscriviti"', await v0.locator('.membri-accesso a[href$="/accedi/"]').count() === 1 && await v0.locator('.membri-accesso a[href$="/iscriviti/"]').count() === 1);
  await vaiA('/admin/editor-widget.php');
  ok('il widget compare fra i widget dell\'editor', /Accesso membri/.test(await a.locator('body').innerText()));

  // 11. Al telefono, senza scorrimento di lato.
  const tel = await pagina(br, err, 390, 844);
  await tel.context().addCookies((await v.context().cookies()));
  let larghi = [];
  for (const u of ['/accedi/', '/iscriviti/', '/password-dimenticata/', '/area-riservata/', '/area-riservata/profilo/', '/chi-siamo/', '/prova-membri/']) {
    await tel.goto(B + u, { waitUntil: 'networkidle' });
    if (await largo(tel) > 0) larghi.push(u);
  }
  ok('al telefono le pagine dell\'area riservata non scorrono di lato', larghi.length === 0, larghi);
  // 2.1: la scheda con il riquadro "Area riservata", al telefono.
  const telA = await pagina(br, err, 390, 844);
  await entraPannello(telA, B, UTENTE, PASSWORD);
  await telA.goto(B + '/admin/contenuto.php?tipo=pagine&id=' + idPag('servizi'), { waitUntil: 'networkidle' });
  ok('al telefono la scheda con il riquadro "Area riservata" non scorre di lato', await telA.locator('#membri-scheda').isVisible() && await largo(telA) <= 0, await largo(telA));
  await telA.context().close();

  // 12. Sospendere: il membro esce subito. Poi riattivarlo.
  await vaiA('/admin/' + MEMBRI + 'elenco');
  await Promise.all([a.waitForNavigation(), a.locator('tr[data-email="giulia.rossi@esempio.it"] button:has-text("Sospendi")').click()]);
  r = await vai('/area-riservata/');
  ok('sospeso dal pannello: il membro è fuori, e non rientra', /\/accedi\//.test(v.url()) && /sospeso/.test(await accedi('giulia.rossi@esempio.it', PW3)));
  await vaiA('/admin/' + MEMBRI + 'elenco');
  await Promise.all([a.waitForNavigation(), a.locator('tr[data-email="giulia.rossi@esempio.it"] button:has-text("Attiva")').click()]);
  ok('riattivato: rientra', /La tua area riservata/.test(await accedi('giulia.rossi@esempio.it', PW3)));

  // 13. Iscrizione su approvazione.
  await vaiA('/admin/' + MEMBRI + 'impostazioni');
  await a.check('[name=modo][value=approvazione]');
  await Promise.all([a.waitForNavigation(), a.click('#mi-form button[type=submit]')]);
  const v2 = await pagina(br, err);
  const vai2 = u => v2.goto(B + u, { waitUntil: 'networkidle' });
  da = posta(POSTA).length;
  await vai2('/iscriviti/');
  ok('su approvazione la pagina Iscriviti lo dice', /approviamo noi/.test(await v2.locator('main').innerText()));
  await v2.fill('#membri-iscriviti [name=nome]', 'Marco Attesa'); await v2.fill('#membri-iscriviti [name=email]', 'marco@esempio.it');
  await v2.fill('#membri-iscriviti [name=password]', PW); await v2.fill('#membri-iscriviti [name=password2]', PW);
  await v2.check('#membri-iscriviti [name=privacy]'); await v2.check('#membri-iscriviti .captcha input');
  await v2.waitForFunction(() => /Verificato/.test(document.querySelector('.captcha-stato').textContent), null, { timeout: 30000 });
  await Promise.all([v2.waitForNavigation(), v2.click('#membri-iscriviti button[type=submit]')]);
  m = await aspettaEmail(POSTA, 'marco@esempio.it', da);
  await v2.goto(linkDa(m, '/conferma-iscrizione/?t='), { waitUntil: 'networkidle' });
  await Promise.all([v2.waitForNavigation(), v2.click('main form button[type=submit]')]);
  ok('su approvazione: dopo la conferma "aspetta la nostra approvazione"', /approvazione/.test(await v2.locator('main').innerText()));
  m = await aspettaEmail(POSTA, 'avvisi-sito@esempio.it', da, x => /approvare/.test(x.oggetto));
  ok('l\'amministratore riceve "Nuova iscrizione da approvare"', !!m);
  await vai2('/accedi/');
  await v2.fill('#membri-accedi [name=email]', 'marco@esempio.it'); await v2.fill('#membri-accedi [name=password]', PW);
  await Promise.all([v2.waitForNavigation(), v2.click('#membri-accedi button[type=submit]')]);
  ok('prima dell\'approvazione non si entra', /in attesa di approvazione/.test(await v2.locator('.modulo-errore').innerText()));
  await vaiA('/admin/' + MEMBRI + 'elenco&stato=in_attesa');
  da = posta(POSTA).length;
  await Promise.all([a.waitForNavigation(), a.locator('tr[data-email="marco@esempio.it"] button:has-text("Attiva")').click()]);
  m = await aspettaEmail(POSTA, 'marco@esempio.it', da);
  ok('Attiva: il membro riceve "Il tuo account è attivo"', !!m && /attivo/.test(m.oggetto));
  await v2.fill('#membri-accedi [name=email]', 'marco@esempio.it').catch(() => {});
  await vai2('/accedi/');
  await v2.fill('#membri-accedi [name=email]', 'marco@esempio.it'); await v2.fill('#membri-accedi [name=password]', PW);
  await Promise.all([v2.waitForNavigation(), v2.click('#membri-accedi button[type=submit]')]);
  ok('approvato: entra', /La tua area riservata/.test(await v2.locator('main').innerText()));

  // 14. Solo su invito.
  await vaiA('/admin/' + MEMBRI + 'impostazioni');
  await a.check('[name=modo][value=invito]');
  await Promise.all([a.waitForNavigation(), a.click('#mi-form button[type=submit]')]);
  const v3 = await pagina(br, err);
  await v3.goto(B + '/iscriviti/', { waitUntil: 'networkidle' });
  ok('solo su invito: Iscriviti non ha il modulo e lo spiega', await v3.locator('#membri-iscriviti').count() === 0 && /solo su invito/.test(await v3.locator('main').innerText()));
  await v3.goto(B + '/prova-membri/', { waitUntil: 'networkidle' });
  ok('solo su invito: il widget non mostra "Iscriviti"', await v3.locator('.membri-accesso a[href$="/iscriviti/"]').count() === 0);
  await vaiA('/admin/' + MEMBRI + 'elenco');
  da = posta(POSTA).length;
  await a.fill('#mb-nome', 'Sara Invitata'); await a.fill('#mb-email', 'sara@esempio.it');
  await Promise.all([a.waitForNavigation(), a.click('#mb-invita button')]);
  ok('Invita: "Invito mandato"', /Invito mandato/.test(await a.locator('.flash').first().innerText()));
  m = await aspettaEmail(POSTA, 'sara@esempio.it', da);
  await v3.goto(linkDa(m, '/nuova-password/?t='), { waitUntil: 'networkidle' });
  ok('il link dell\'invito chiede di scegliere la password', /Benvenuto/.test(await v3.locator('h1').innerText()));
  await v3.fill('#membri-nuova-password [name=password]', PW); await v3.fill('#membri-nuova-password [name=password2]', PW);
  await Promise.all([v3.waitForNavigation(), v3.click('#membri-nuova-password button')]);
  ok('scelta la password, l\'invitata è dentro', /\/area-riservata\/\?m=benvenuto/.test(v3.url()) && /Ciao Sara Invitata/.test(await v3.locator('main').innerText()));

  // 15. Esporta in CSV.
  await vaiA('/admin/' + MEMBRI + 'elenco');
  const [csvDl] = await Promise.all([a.waitForEvent('download'), a.click('#mb-esporta')]);
  const csv = fs.readFileSync(await csvDl.path(), 'utf8');
  ok('Esporta: il CSV ha i tre membri, con nome, email e stato', /giulia\.rossi@esempio\.it/.test(csv) && /marco@esempio\.it/.test(csv) && /sara@esempio\.it/.test(csv) && /Attivo/.test(csv), csv.slice(0, 120));

  // 16. Cancella il mio account.
  await vai('/area-riservata/profilo/');
  await v.fill('#membri-elimina [name=attuale]', PW3); await v.check('#membri-elimina [name=sicuro]');
  await Promise.all([v.waitForNavigation(), v.click('#membri-elimina button')]);
  ok('Cancella il mio account: fatto, e si è fuori', /cancellato/.test(await v.locator('main').innerText()));
  ok('l\'account non c\'è più: l\'accesso non va', /non corrette/.test(await accedi('giulia.rossi@esempio.it', PW3)));
  await vaiA('/admin/' + MEMBRI + 'elenco');
  ok('e non è più nell\'elenco del pannello', await a.locator('tr[data-email="giulia.rossi@esempio.it"]').count() === 0);

  // 17. Spegnere l'addon: il sito torna come prima.
  await vaiA('/admin/' + MEMBRI + 'impostazioni');
  await a.check('[name=modo][value=aperta]');
  await Promise.all([a.waitForNavigation(), a.click('#mi-form button[type=submit]')]);
  execFileSync('php', ['-r', `require '${DIR}/includes/config.php'; db()->exec("DELETE FROM " . tbl('pages') . " WHERE slug = 'prova-membri'");`]);
  const f2 = await addon(a, B, 'membri', false);
  ok('Impostazioni > Addon: spento, "i dati restano"', /spento/.test(f2), f2);
  const diversi = [];
  for (const u of Object.keys(prima)) if (pulisci(await (await v0.request.get(B + u)).text()) !== prima[u]) diversi.push(u);
  ok('spento: home, Chi siamo e l\'articolo identici a prima di accendere l\'addon', diversi.length === 0, diversi);
  ok('spento: /accedi/ e /area-riservata/ danno 404', (await v0.request.get(B + '/accedi/')).status() === 404 && (await v0.request.get(B + '/area-riservata/')).status() === 404);
  ok('spento: le pagine del pannello dell\'addon danno 404', (await a.request.get(B + '/admin/' + MEMBRI + 'elenco')).status() === 404);
  await addon(a, B, 'membri', true);
  r = await v0.request.get(B + '/chi-siamo/');
  ok('riacceso: Chi siamo è di nuovo riservato (i dati erano rimasti)', /solo per i membri/i.test(await r.text()));
  // pulizia: il sito resta con l'addon acceso e i contenuti di nuovo pubblici
  await vaiA('/admin/' + MEMBRI + 'riservati');
  while (await a.locator('#mr-contenuti input[type=checkbox]:checked').count()) await a.locator('#mr-contenuti input[type=checkbox]:checked').first().uncheck();
  await Promise.all([a.waitForNavigation(), a.click('#mr-contenuti button[type=submit]')]);

  fine(err);
  await br.close();
})().catch(async e => { console.error(e); process.exitCode = 2; if (br) await br.close(); });
