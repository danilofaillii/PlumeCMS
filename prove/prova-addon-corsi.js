// Addon Corsi online (corsi, 2.0.0): tutto il percorso, come lo fanno l'amministratore e due membri nel browser.
// Su un sito nuovo installato con il kit Attività (prove/prova-kit.js), con il server di posta finto acceso
// (python3 -I prove/smtp-finto.py <porta smtp> <cartella posta>) e ffmpeg per il video di prova.
// L'amministratore accende Corsi online (si accende anche l'Area riservata), crea un corso con due moduli e tre lezioni
// (2.1: il testo con l'editor visuale, due lezioni di prima convertite senza perdere niente; video di YouTube, video
// caricato, materiale riservato), le riordina con le frecce e trascinando (resta dopo il ricaricamento), crea un secondo
// corso solo su assegnazione.
// Due membri invitati: uno segue il corso fino al certificato (riprendi da dove eri, percentuale, codice di verifica
// pubblico), l'altro no; il corso su assegnazione si apre solo dopo l'assegnazione. Widget Elenco corsi, telefono
// 390x844, bozza e anteprima, account cancellato. Alla fine spegne l'addon: il sito torna identico a prima.
// uso: node prove/prova-addon-corsi.js <base> <cartella CMS> <utente> <password> <porta smtp> <cartella posta>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { conta, pagina, entraPannello, addon, postaFinta, posta, aspettaEmail, linkDa, largo } = require('./addon-a-comune');
const [B, DIR, UTENTE, PASSWORD, SMTP, POSTA] = process.argv.slice(2);
const { ok, fine } = conta();
const PW = 'Lampone-Verde-2026!';
const C = 'addon-pagina.php?a=corsi&p=';
const pulisci = h => h.replace(/ nonce="[^"]*"/g, '').replace(/\?v=\d+/g, '').replace(/data-fine="[^"]*"/g, '').replace(/<span class="ew-cd-[a-z]+">\d+<\/span>/g, '');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'corsi-'));

let br;
(async () => {
  br = await chromium.launch();
  const err = [];
  const a = await pagina(br, err);
  const vaiA = u => a.goto(B + u, { waitUntil: 'networkidle' });
  const flash = async () => (await a.locator('.flash').first().innerText().catch(() => '')).trim();
  await entraPannello(a, B, UTENTE, PASSWORD);
  await postaFinta(a, B, SMTP);
  await addon(a, B, 'corsi', false);
  const v0 = await pagina(br, err);
  const prima = {};
  for (const u of ['/', '/servizi/', '/blog/']) prima[u] = pulisci(await (await v0.request.get(B + u)).text());
  ok('prima dell\'addon: /corsi/ non esiste (404)', (await v0.request.get(B + '/corsi/')).status() === 404);

  // 1. Accendere Corsi online: si accende anche l'Area riservata.
  await addon(a, B, 'membri', false).catch(() => {});
  const f1 = await addon(a, B, 'corsi', true);
  ok('Accendi "Corsi online": acceso, e acceso anche "Area riservata", che serve', /Corsi online.*acceso/.test(f1) && /Acceso anche «Area riservata»/.test(f1), f1);
  await vaiA('/admin/addon.php');
  await Promise.all([a.waitForNavigation(), a.click('[data-addon="membri"] button[type=submit]')]);
  ok('... e lo dice: "Prima spegni «Corsi online»"', /Prima spegni «Corsi online»/.test(await flash()));
  ok('il menu del pannello ha la voce Corsi', await a.locator('nav.admin-nav a', { hasText: 'Corsi' }).count() >= 1);

  // 2. Un corso con due moduli e tre lezioni.
  await vaiA('/admin/' + C + 'corsi');
  await a.fill('#co-titolo', 'Fotografia per cominciare');
  await Promise.all([a.waitForNavigation(), a.click('#co-nuovo button')]);
  ok('Nuovo corso: si apre la sua pagina, in bozza, con un primo modulo', /Corso creato/.test(await flash()) && await a.locator('.co-modulo').count() === 1 && await a.inputValue('#co-stato') === 'bozza');
  const urlCorso = a.url().replace(B, '');
  await a.fill('#co-descrizione', 'Le basi per fare belle foto con il telefono: luce, inquadratura, primo scatto.');
  await a.selectOption('#co-stato', 'pubblicato');
  await Promise.all([a.waitForNavigation(), a.click('#co-dati button[type=submit]')]);
  ok('Salva il corso: pubblicato', /Corso salvato/.test(await flash()) && await a.inputValue('#co-stato') === 'pubblicato');
  const modulo = a.locator('.co-modulo').first();
  await modulo.locator('input[name=titolo]').first().fill('Le basi');
  await Promise.all([a.waitForNavigation(), modulo.locator('.co-modulo-testa button:has-text("Salva")').click()]);
  const nuovaLezione = async (i, titolo, dove = urlCorso) => {
    await vaiA(dove);
    const f = a.locator('.co-modulo').nth(i).locator('form.co-nuova-lezione');
    await f.locator('input[name=titolo]').fill(titolo);
    await Promise.all([a.waitForNavigation(), f.locator('button').click()]);
    return a.url().replace(B, '');
  };
  const uLuce = await nuovaLezione(0, 'La luce');
  ok('Aggiungi lezione: si apre la scheda della lezione', /Lezione aggiunta/.test(await flash()) && await a.inputValue('#le-titolo') === 'La luce');
  const uInq = await nuovaLezione(0, 'L\'inquadratura');
  await vaiA(urlCorso);
  await a.fill('#co-nuovo-modulo input[name=titolo]', 'Pratica');
  await Promise.all([a.waitForNavigation(), a.click('#co-nuovo-modulo button')]);
  const uScatto = await nuovaLezione(1, 'Il primo scatto');
  await vaiA(urlCorso);
  ok('programma: due moduli, tre lezioni', /2 moduli, 3 lezioni/.test(await a.locator('#programma h3').innerText()));

  // 3. Le lezioni (2.1): il testo con l'editor visuale; video di YouTube, indirizzo sbagliato, video caricato, materiale.
  const sqlC = q => JSON.parse(execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='127.0.0.1'; require '${DIR}/includes/config.php'; $r = db()->query(str_replace('{p}', DB_PREFIX, ${JSON.stringify(q)})); echo json_encode($r && $r->columnCount() ? $r->fetchAll() : []);`], { encoding: 'utf8' }));
  const tela = () => a.frameLocator('#ed-iframe');
  const telaPronta = () => a.waitForFunction(() => { const f = document.querySelector('#ed-iframe'); return f && f.contentDocument && f.contentDocument.querySelector('.ed-sezione'); }, null, { timeout: 30000 });
  await vaiA(uLuce);
  ok('la lezione non ha più l\'area di testo in HTML: c\'è «Scrivi il testo con l\'editor visuale»', await a.locator('#le-testo').count() === 0 && /Scrivi il testo con l'editor visuale/.test(await a.locator('#le-apri-editor').innerText()) && /non ha ancora un testo/.test(await a.locator('#le-editor').innerText()));
  await a.fill('#le-video', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
  await a.fill('#le-durata', '10 minuti');
  await Promise.all([a.waitForNavigation(), a.click('#le-apri-editor')]);
  await telaPronta();
  ok('il pulsante salva la scheda (video e durata) e apre l\'editor visuale della lezione', /editor\.php\?tipo=corsi_lezione&id=\d+/.test(a.url()) && sqlC("SELECT durata, video_url FROM {p}corsi_lezioni WHERE slug = 'la-luce'")[0].durata === '10 minuti');
  ok('la tela ha la cornice della lezione: briciole, «lezione 1 di 3», il titolo, e il widget Testo pronto', /Fotografia per cominciare/.test(await tela().locator('.corsi-briciole').innerText()) && /lezione 1 di 3/i.test(await tela().locator('.corsi-testa .occhiello').innerText()) && (await tela().locator('.corsi-testa h1').innerText()) === 'La luce' && await tela().locator('.ed-w-testo').count() === 1);
  ok('in alto il percorso della lezione, e «Vedi la pagina» apre l\'anteprima', /\/corsi\/fotografia-per-cominciare\/la-luce\//.test(await a.locator('#ed-percorso').innerText()) && /\/corsi\/fotografia-per-cominciare\/la-luce\/\?preview=1$/.test(await a.getAttribute('#ed-vedi', 'href')));
  await tela().locator('.ed-w-testo').first().dblclick(); await a.waitForTimeout(500);
  await a.locator('#ed-controlli .ql-editor').click(); await a.keyboard.press('Control+A');
  await a.keyboard.type('La luce migliore arriva presto, appena dopo l\'alba.');
  await a.waitForTimeout(700);
  // Un titolo in più dalla tavolozza: clic sul widget Titolo, entra sotto quello selezionato.
  await a.click('[data-pannello="widget"]');
  await a.locator('.ed-widget-voce[data-tipo=titolo]').click(); await a.waitForTimeout(800);
  await a.click('#ed-pubblica');
  await a.waitForFunction(() => /Pubblicata alle/.test(document.querySelector('#ed-stato').textContent), null, { timeout: 15000 });
  ok('scrivo nel widget Testo, aggiungo un Titolo, Pubblica: «Pubblicata»', true);
  const strL = sqlC("SELECT struttura, struttura_bozza, testo FROM {p}corsi_lezioni WHERE slug = 'la-luce'")[0];
  ok('nel database: la struttura pubblicata con il testo scritto, nessuna bozza, la colonna di prima vuota com\'era', /La luce migliore arriva presto/.test(strL.struttura) && /"tipo":"titolo"/.test(strL.struttura) && strL.struttura_bozza === null && strL.testo === '');
  await a.click('#ed-apri-impostazioni');
  ok('Impostazioni dell\'editor: solo il titolo (niente indirizzo, Google, stato)', await a.locator('#ed-form-impostazioni [name=titolo]').isVisible() && !(await a.locator('#ed-form-impostazioni [name=slug]').isVisible()) && !(await a.locator('#ed-form-impostazioni [name=seo_titolo]').isVisible()) && !(await a.locator('#ed-form-impostazioni [name=stato]').isVisible()) && /lezione/.test(await a.locator('#ed-form-impostazioni h2').innerText()));
  await a.click('#ed-form-impostazioni [data-chiudi]');
  await a.click('#ed-apri-revisioni'); await a.waitForTimeout(800);
  ok('Revisioni: le versioni della lezione (la prima e la pubblicata)', await a.locator('#ed-elenco-revisioni > *').count() >= 2 && /ambito=contenitore%3Acorsi_lezione|ambito=contenitore:corsi_lezione/.test(await a.getAttribute('#ed-tutte-versioni', 'href')));
  await Promise.all([a.waitForNavigation(), a.click('.ed-indietro')]);
  ok('la freccia in alto torna alla scheda della lezione, che dice «Pubblicato»', /p=lezione&id=/.test(a.url()) && /Pubblicato/i.test(await a.locator('#le-editor').innerText()) && (await a.getAttribute('#le-editor', 'data-stato')) === 'pubblicata');
  await a.fill('#le-video', 'https://cattivo.example/video.mp4');
  await Promise.all([a.waitForNavigation(), a.click('#le-form aside button.btn-primary')]);
  ok('un indirizzo video che non è YouTube né Vimeo si rifiuta, e si dice perché', /YouTube/.test(await flash()) && await a.inputValue('#le-video') === 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
  // il materiale: un file riservato di Download
  const pdf = path.join(tmp, 'scheda-inquadratura.pdf');
  fs.writeFileSync(pdf, '%PDF-1.4\n% scheda\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n');
  await vaiA('/admin/scaricabili.php');
  await a.setInputFiles('#dl-carica input[type=file]', pdf); await a.fill('#dl-carica [name=titolo]', 'Scheda dell\'inquadratura'); await a.check('#dl-riservato');
  await Promise.all([a.waitForNavigation(), a.click('#dl-carica button[type=submit]')]);
  // il video caricato: due secondi fatti con ffmpeg
  const webm = path.join(tmp, 'inquadratura.webm');
  execFileSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', '-f', 'lavfi', '-i', 'testsrc=duration=2:size=320x240:rate=10', '-c:v', 'libvpx', '-b:v', '200k', webm]);
  // Due lezioni "di prima" (2.0.0): il testo in HTML scritto nella colonna testo, come lo lasciava l'area di testo.
  execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='127.0.0.1'; require '${DIR}/includes/config.php'; $u = db()->prepare("UPDATE " . tbl('corsi_lezioni') . " SET testo = ? WHERE slug = ?"); $u->execute(['<p>La regola dei <b>terzi</b>.</p><ul><li>Linee</li><li>Punti forti</li></ul>', 'l-inquadratura']); $u->execute(['<p>Esci e scatta.</p><table><tbody><tr><td>ISO</td><td>100</td></tr></tbody></table>', 'il-primo-scatto']);`]);
  await vaiA(uInq);
  ok('lezione di prima: «Apri il testo nell\'editor visuale», e sotto il testo di prima', /Apri il testo nell'editor visuale/.test(await a.locator('#le-apri-editor').innerText()) && /regola dei terzi/.test(await a.locator('.le-vecchio').innerText()));
  await a.setInputFiles('#le-file', webm);
  await a.locator('label.le-mat', { hasText: 'scheda-inquadratura.pdf' }).locator('input').check();
  await Promise.all([a.waitForNavigation(), a.click('#le-form aside button.btn-primary')]);
  ok('video caricato e materiale scelti', /Lezione salvata/.test(await flash()) && await a.locator('#le-video-caricato').count() === 1 && await a.locator('label.le-mat input:checked').count() === 1);
  const finto = path.join(tmp, 'finto.webm'); fs.writeFileSync(finto, '<?php echo 1; ?>');
  await a.setInputFiles('#le-file', finto);
  await Promise.all([a.waitForNavigation(), a.click('#le-form aside button.btn-primary')]);
  ok('un file che non è un video si rifiuta', /non è un video/.test(await flash()));
  await Promise.all([a.waitForNavigation(), a.click('#le-apri-editor')]);
  await telaPronta();
  ok('lezione di prima aperta nell\'editor: il testo entra intero in un widget Testo (grassetto ed elenco compresi)', /La regola dei terzi/.test(await tela().locator('.ed-w-testo').first().innerText()) && await tela().locator('.ed-w-testo b').count() === 1 && await tela().locator('.ed-w-testo li').count() === 2);
  ok('ed è una bozza: «Mai pubblicata», il testo di prima è ancora quello dei membri', /Mai pubblicata/.test(await a.locator('#ed-stato').innerText()) && sqlC("SELECT struttura FROM {p}corsi_lezioni WHERE slug = 'l-inquadratura'")[0].struttura === null);
  await vaiA(uInq);
  ok('la scheda dice «Bozza non pubblicata: i membri vedono ancora il testo di prima»', /Bozza non pubblicata/i.test(await a.locator('#le-editor').innerText()) && /vedono ancora il testo di prima/.test(await a.locator('#le-editor').innerText()));
  await vaiA(uScatto);
  await a.fill('#le-video', 'https://vimeo.com/76979871');
  await Promise.all([a.waitForNavigation(), a.click('#le-apri-editor')]);
  await telaPronta();
  ok('una lezione di prima con una tabella: entra intera in un widget HTML (il widget Testo la perderebbe)', await tela().locator('.ed-w-html table td', { hasText: 'ISO' }).count() === 1 && /Esci e scatta/.test(await tela().locator('.ed-w-html').innerText()));
  await a.click('#ed-pubblica');
  await a.waitForFunction(() => /Pubblicata alle/.test(document.querySelector('#ed-stato').textContent), null, { timeout: 15000 });
  ok('il testo di prima resta salvato nella sua colonna (niente si perde)', /<table>/.test(sqlC("SELECT testo FROM {p}corsi_lezioni WHERE slug = 'il-primo-scatto'")[0].testo) && sqlC("SELECT video_url FROM {p}corsi_lezioni WHERE slug = 'il-primo-scatto'")[0].video_url === 'https://vimeo.com/76979871');
  // riordinare: con le frecce (la tastiera) e trascinando.
  await vaiA(urlCorso);
  await Promise.all([a.waitForNavigation(), a.locator('li[data-lezione="l-inquadratura"] button.co-su').click()]);
  const ordineDi = () => a.locator('.co-modulo').evaluateAll(ms => ms.map(m => m.querySelector('.co-modulo-testa input[name=titolo]').value + ':' + [...m.querySelectorAll('li[data-lezione]')].map(x => x.dataset.lezione).join(',')));
  ok('le frecce riordinano le lezioni', JSON.stringify(await ordineDi()) === '["Le basi:l-inquadratura,la-luce","Pratica:il-primo-scatto"]', await ordineDi());
  ok('ogni modulo e ogni lezione ha la sua maniglia per trascinare', await a.locator('.co-maniglia-modulo').count() === 2 && await a.locator('.co-maniglia-lezione').count() === 3);
  // Trascina dalla maniglia (il mouse a passi, come una persona) e aspetta che il server risponda.
  const trascina = async (da, a2, sotto = true) => {
    const p0 = await da.boundingBox(), p1 = await a2.boundingBox();
    const prima = await a.getAttribute('#co-moduli', 'data-salvato');
    await a.mouse.move(p0.x + p0.width / 2, p0.y + p0.height / 2); await a.mouse.down();
    await a.mouse.move(p0.x + p0.width / 2, p0.y + p0.height / 2 + 6, { steps: 3 });
    const y = sotto ? p1.y + p1.height - 4 : p1.y + 4;
    await a.mouse.move(p1.x + p1.width / 2, y, { steps: 20 }); await a.waitForTimeout(200);
    await a.mouse.move(p1.x + p1.width / 2, y + (sotto ? 2 : -2), { steps: 2 }); await a.mouse.up();
    await a.waitForFunction(x => { const b = document.querySelector('#co-moduli'); return b.getAttribute('data-salvato') && b.getAttribute('data-salvato') !== x; }, prima, { timeout: 8000 }).catch(() => {});
    return (await a.locator('#co-ordine-stato').innerText()).trim();
  };
  let st = await trascina(a.locator('li[data-lezione="il-primo-scatto"] .co-maniglia-lezione'), a.locator('li[data-lezione="l-inquadratura"]'), false);
  ok('trascino «Il primo scatto» in cima al primo modulo: «Ordine salvato.»', st === 'Ordine salvato.' && JSON.stringify(await ordineDi()) === '["Le basi:il-primo-scatto,l-inquadratura,la-luce","Pratica:"]', [st, await ordineDi()]);
  ok('le frecce si aggiornano: la prima lezione non sale, l\'ultima non scende', await a.locator('li[data-lezione="il-primo-scatto"] button.co-su').isDisabled() && await a.locator('li[data-lezione="la-luce"] button.co-giu').isDisabled() && !(await a.locator('li[data-lezione="l-inquadratura"] button.co-su').isDisabled()));
  await vaiA(urlCorso);
  ok('ricaricando la pagina l\'ordine trascinato resta', JSON.stringify(await ordineDi()) === '["Le basi:il-primo-scatto,l-inquadratura,la-luce","Pratica:"]', await ordineDi());
  st = await trascina(a.locator('.co-modulo').nth(1).locator('.co-maniglia-modulo'), a.locator('.co-modulo').nth(0), false);
  await vaiA(urlCorso);
  ok('trascino il modulo «Pratica» sopra «Le basi»: resta dopo il ricaricamento', st === 'Ordine salvato.' && JSON.stringify(await ordineDi()) === '["Pratica:","Le basi:il-primo-scatto,l-inquadratura,la-luce"]', [st, await ordineDi()]);
  // Si rimette com'era: il modulo con le frecce, la lezione trascinata nel modulo vuoto.
  await Promise.all([a.waitForNavigation(), a.locator('.co-modulo').nth(0).locator('button.co-modulo-giu').click()]);
  st = await trascina(a.locator('li[data-lezione="il-primo-scatto"] .co-maniglia-lezione'), a.locator('.co-modulo').nth(1).locator('.co-lez'), true);
  await vaiA(urlCorso);
  ok('la freccia rimette giù il modulo, e una lezione si trascina anche in un modulo vuoto', st === 'Ordine salvato.' && JSON.stringify(await ordineDi()) === '["Le basi:l-inquadratura,la-luce","Pratica:il-primo-scatto"]', [st, await ordineDi()]);
  const at = await pagina(br, err, 390, 844);
  await at.context().addCookies(await a.context().cookies());
  const larghiP = [];
  for (const u of [urlCorso, uLuce, uInq]) { await at.goto(B + u, { waitUntil: 'networkidle' }); if (await largo(at) > 0) larghiP.push(u); }
  ok('al telefono il programma e le schede delle lezioni non scorrono di lato', larghiP.length === 0, larghiP);
  // un secondo corso, solo per chi lo riceve
  await vaiA('/admin/' + C + 'corsi');
  await a.fill('#co-titolo', 'Corso avanzato');
  await Promise.all([a.waitForNavigation(), a.click('#co-nuovo button')]);
  const urlAv = a.url().replace(B, '');
  await a.check('[name=accesso][value=assegnati]'); await a.selectOption('#co-stato', 'pubblicato');
  await Promise.all([a.waitForNavigation(), a.click('#co-dati button[type=submit]')]);
  await nuovaLezione(0, 'Luce artificiale', urlAv);

  // 4. Due membri, invitati dal pannello.
  const invita = async (nome, email) => {
    await vaiA('/admin/addon-pagina.php?a=membri&p=elenco');
    const da = posta(POSTA).length;
    await a.fill('#mb-nome', nome); await a.fill('#mb-email', email);
    await Promise.all([a.waitForNavigation(), a.click('#mb-invita button')]);
    const m = await aspettaEmail(POSTA, email, da);
    const p = await pagina(br, err);
    await p.goto(linkDa(m, '/nuova-password/?t='), { waitUntil: 'networkidle' });
    await p.fill('#membri-nuova-password [name=password]', PW); await p.fill('#membri-nuova-password [name=password2]', PW);
    await Promise.all([p.waitForNavigation(), p.click('#membri-nuova-password button')]);
    return p;
  };
  const luca = await invita('Luca Allievo', 'luca@esempio.it');
  const anna = await invita('Anna Curiosa', 'anna@esempio.it');
  ok('due membri invitati ed entrati', /Ciao Luca Allievo/.test(await luca.locator('main').innerText()) && /Ciao Anna Curiosa/.test(await anna.locator('main').innerText()));
  ok('l\'area riservata ha "I miei corsi" con il corso aperto a tutti (non quello su assegnazione)', /I miei corsi/.test(await luca.locator('main').innerText()) && await luca.locator('.corsi-scheda').count() === 1);

  // 5. Da fuori: l'elenco e il programma sì, le lezioni no.
  await v0.goto(B + '/corsi/', { waitUntil: 'networkidle' });
  ok('/corsi/ da fuori: i due corsi pubblicati e l\'invito ad accedere', await v0.locator('.corsi-scheda').count() === 2 && /accedi/i.test(await v0.locator('main').innerText()));
  await v0.goto(B + '/corsi/fotografia-per-cominciare/', { waitUntil: 'networkidle' });
  ok('il programma da fuori: titoli delle lezioni senza link, e "Accedi"', await v0.locator('.corsi-lezioni li').count() === 3 && await v0.locator('.corsi-lezioni a').count() === 0 && await v0.locator('.corsi-stato a:has-text("Accedi")').count() === 1);
  let r = await v0.request.get(B + '/corsi/fotografia-per-cominciare/la-luce/', { maxRedirects: 0 });
  ok('una lezione da fuori porta all\'accesso', r.status() === 303 && /\/accedi\//.test(r.headers().location || ''));

  // 6. Luca segue il corso.
  const vaiL = u => luca.goto(B + u, { waitUntil: 'networkidle' });
  await vaiL('/corsi/fotografia-per-cominciare/');
  ok('il programma da membro: barra a 0% e "Inizia: L\'inquadratura"', /0 lezioni fatte su 3 \(0%\)/.test(await luca.locator('.corsi-numeri').last().innerText()) && /Inizia: L.inquadratura/i.test(await luca.locator('a.corsi-riprendi').innerText()));
  await Promise.all([luca.waitForNavigation(), luca.click('a.corsi-riprendi')]);
  ok('lezione 1: titolo, "lezione 1 di 3", testo', /L.inquadratura/.test(await luca.locator('h1').innerText()) && /lezione 1 di 3/i.test(await luca.locator('.corsi-testa .occhiello').innerText()) && /regola dei terzi/.test(await luca.locator('.corsi-testo').innerText()));
  await luca.waitForFunction(() => { const v = document.querySelector('.corsi-video video'); return v && v.readyState >= 1; }, null, { timeout: 15000 }).catch(() => {});
  const vid = await luca.evaluate(() => { const v = document.querySelector('.corsi-video video'); return v ? { stato: v.readyState, durata: v.duration, src: v.getAttribute('src') } : null; });
  ok('il video caricato si carica nel browser del membro (due secondi)', vid && vid.stato >= 1 && Math.round(vid.durata) === 2, vid);
  r = await luca.request.get(B + vid.src.replace(new URL(B).pathname.replace(/\/$/, ''), ''), { headers: { Range: 'bytes=0-99' } });
  ok('il video si manda a pezzi (206, Range)', r.status() === 206 && /^bytes 0-99\//.test(r.headers()['content-range'] || '') && (await r.body()).length === 100);
  r = await v0.request.get(B + vid.src.replace(new URL(B).pathname.replace(/\/$/, ''), ''), { maxRedirects: 0 });
  ok('lo stesso video da fuori: no', r.status() === 303);
  const mat = luca.locator('a.corsi-materiale');
  const [dl] = await Promise.all([luca.waitForEvent('download'), mat.click()]);
  ok('il materiale si scarica', fs.readFileSync(await dl.path(), 'utf8').startsWith('%PDF') && dl.suggestedFilename() === 'scheda-inquadratura.pdf');
  ok('il file riservato non ha un indirizzo pubblico', (await v0.request.get(B + '/scarica/scheda-inquadratura/')).status() === 404);
  await Promise.all([luca.waitForNavigation(), luca.click('form.corsi-completa button')]);
  ok('"Segna come completata e vai avanti": si va alla lezione 2, la luce', /\/la-luce\/$/.test(luca.url()) && /1 lezioni fatte su 3 \(33%\)/.test(await luca.locator('.corsi-numeri').last().innerText()));
  ok('la lezione scritta con l\'editor visuale: il membro vede il testo e il titolo aggiunto, a sezioni, e non l\'area di testo di prima', /La luce migliore arriva presto/.test(await luca.locator('.corsi-lezione-testo .ed-w-testo').innerText()) && await luca.locator('.corsi-lezione-testo .ed-w-titolo').count() === 1 && await luca.locator('.corsi-testo').count() === 0 && await luca.locator('form.corsi-completa').count() === 1);
  ok('la lezione con YouTube incorpora la versione senza cookie', /^https:\/\/www\.youtube-nocookie\.com\/embed\/dQw4w9WgXcQ$/.test(await luca.locator('.corsi-video iframe').getAttribute('src')));
  await vaiL('/area-riservata/');
  await vaiL('/corsi/fotografia-per-cominciare/');
  ok('riprendi da dove eri: "Riprendi da dove eri: La luce", lezione 1 spuntata', /Riprendi da dove eri: La luce/i.test(await luca.locator('a.corsi-riprendi').innerText()) && await luca.locator('.corsi-lezioni li.fatta').count() === 1);
  await vaiL('/area-riservata/');
  ok('area riservata: "I miei corsi" con il 33% e "Riprendi"', /33%/.test(await luca.locator('.corsi-scheda').innerText()) && /Riprendi/i.test(await luca.locator('.corsi-scheda a.btn').innerText()));
  await vaiL('/corsi/fotografia-per-cominciare/la-luce/');
  await Promise.all([luca.waitForNavigation(), luca.click('form.corsi-completa button')]);
  r = await luca.goto(B + '/corsi/fotografia-per-cominciare/certificato/', { waitUntil: 'networkidle' });
  ok('a corso non finito il certificato non c\'è ancora', /arriva alla fine/.test(await luca.locator('h1').innerText()));
  await vaiL('/corsi/fotografia-per-cominciare/il-primo-scatto/');
  ok('la lezione di prima convertita e pubblicata: il membro vede la tabella intera', await luca.locator('.corsi-lezione-testo table td', { hasText: 'ISO' }).count() === 1 && /Esci e scatta/.test(await luca.locator('.corsi-lezione-testo').innerText()));
  ok('la lezione con Vimeo', /^https:\/\/player\.vimeo\.com\/video\/76979871$/.test(await luca.locator('.corsi-video iframe').getAttribute('src')));
  await Promise.all([luca.waitForNavigation(), luca.click('form.corsi-completa button')]);
  ok('ultima lezione fatta: il corso dice "Hai completato il corso", 100%', /Hai completato il corso/.test(await luca.locator('main').innerText()) && /3 lezioni fatte su 3 \(100%\)/.test(await luca.locator('.corsi-numeri').last().innerText()));
  await Promise.all([luca.waitForNavigation(), luca.click('a:has-text("Il tuo certificato")')]);
  const codice = (await luca.locator('#cert-codice').innerText()).trim();
  ok('il certificato: nome, corso, data e codice di verifica', (await luca.locator('#cert-nome').innerText()) === 'Luca Allievo' && /Fotografia per cominciare/.test(await luca.locator('#cert-corso').innerText()) && /^[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/.test(codice) && /il \d{1,2} [a-z]+ \d{4}/.test(await luca.locator('.cert-data').innerText()), codice);
  ok('il certificato ha il pulsante Stampa (che apre la stampa del browser)', await luca.locator('[data-corsi-stampa]').count() === 1);
  await luca.emulateMedia({ media: 'print' });
  ok('in stampa il pulsante non c\'è', !(await luca.locator('[data-corsi-stampa]').isVisible()));
  await luca.emulateMedia({ media: 'screen' });
  await v0.goto(B + '/certificato/', { waitUntil: 'networkidle' });
  await v0.fill('#corsi-verifica [name=codice]', codice.toLowerCase().replace(/-/g, ''));
  await Promise.all([v0.waitForNavigation(), v0.click('#corsi-verifica button')]);
  ok('verifica pubblica: il codice (anche minuscolo, senza trattini) porta al certificato valido', /Certificato valido/.test(await v0.locator('.cert-verifica').innerText()) && (await v0.locator('#cert-nome').innerText()) === 'Luca Allievo');
  await v0.goto(B + '/certificato/?codice=AAAA-BBBB-CCCC', { waitUntil: 'networkidle' });
  ok('un codice che non c\'è: "Nessun certificato con questo codice"', /Nessun certificato/.test(await v0.locator('.modulo-errore').innerText()));

  // 7. Il corso su assegnazione.
  await vaiL('/corsi/corso-avanzato/');
  ok('corso su assegnazione, non assegnato: "riservato a chi è iscritto", lezioni senza link', /riservato a chi è iscritto/.test(await luca.locator('.corsi-stato').innerText()) && await luca.locator('.corsi-lezioni a').count() === 0);
  r = await luca.goto(B + '/corsi/corso-avanzato/luce-artificiale/', { waitUntil: 'networkidle' });
  ok('e la lezione dà 403 "non è fra i tuoi"', r.status() === 403 && /non è fra i tuoi/.test(await luca.locator('h1').innerText()));
  await vaiA(urlAv);
  const da = posta(POSTA).length;
  await a.selectOption('#co-membro', { label: 'Luca Allievo (luca@esempio.it)' });
  await Promise.all([a.waitForNavigation(), a.click('#co-assegna button')]);
  ok('Assegna: fatto, e a Luca arriva l\'email "Hai un corso nuovo"', /può seguire il corso/.test(await flash()) && !!(await aspettaEmail(POSTA, 'luca@esempio.it', da)));
  r = await luca.goto(B + '/corsi/corso-avanzato/luce-artificiale/', { waitUntil: 'networkidle' });
  ok('assegnato: Luca apre la lezione', r.status() === 200 && /Luce artificiale/.test(await luca.locator('h1').innerText()));
  r = await anna.goto(B + '/corsi/corso-avanzato/luce-artificiale/', { waitUntil: 'networkidle' });
  ok('Anna no', r.status() === 403);
  await vaiA(urlCorso);
  ok('pannello: chi segue il corso, con avanzamento e codice del certificato', /3 su 3 \(100%\)/.test(await a.locator('#co-iscritti tr[data-email="luca@esempio.it"]').innerText()) && (await a.locator('#co-iscritti tr[data-email="luca@esempio.it"] code').innerText()) === codice);

  // 8. Il widget Elenco corsi.
  execFileSync('php', [path.join(__dirname, 'pagina-addon-a.php'), DIR, 'prova-corsi', 'I nostri corsi', 'corsi_elenco']);
  await vaiL('/prova-corsi/');
  ok('widget Elenco corsi, da membro: due schede, il primo corso al 100%', await luca.locator('.corsi-widget .corsi-scheda').count() === 2 && /100%/.test(await luca.locator('.corsi-widget .corsi-scheda').first().innerText()));
  await v0.goto(B + '/prova-corsi/', { waitUntil: 'networkidle' });
  ok('widget Elenco corsi, da fuori: due schede senza avanzamento', await v0.locator('.corsi-widget .corsi-scheda').count() === 2 && await v0.locator('.corsi-widget .corsi-barra').count() === 0);
  await vaiA('/admin/editor-widget.php');
  ok('il widget compare fra i widget dell\'editor', /Elenco corsi/.test(await a.locator('body').innerText()));

  // 9. Al telefono.
  const tel = await pagina(br, err, 390, 844);
  await tel.context().addCookies(await luca.context().cookies());
  const larghi = [];
  for (const u of ['/corsi/', '/corsi/fotografia-per-cominciare/', '/corsi/fotografia-per-cominciare/l-inquadratura/', '/corsi/fotografia-per-cominciare/la-luce/', '/corsi/fotografia-per-cominciare/certificato/', '/certificato/' + codice + '/', '/area-riservata/', '/prova-corsi/']) {
    await tel.goto(B + u, { waitUntil: 'networkidle' });
    if (await largo(tel) > 0) larghi.push(u);
  }
  ok('al telefono le pagine dei corsi non scorrono di lato', larghi.length === 0, larghi);
  await tel.goto(B + '/admin/login.php');

  // 10. Bozza e anteprima.
  await vaiA(urlAv);
  await a.selectOption('#co-stato', 'bozza');
  await Promise.all([a.waitForNavigation(), a.click('#co-dati button[type=submit]')]);
  r = await luca.goto(B + '/corsi/corso-avanzato/', { waitUntil: 'networkidle' });
  ok('in bozza il corso non si vede sul sito (404)', r.status() === 404);
  r = await a.goto(B + '/corsi/corso-avanzato/?preview=1', { waitUntil: 'networkidle' });
  ok('l\'amministratore lo vede in anteprima', r.status() === 200 && /Anteprima dell.amministratore/.test(await a.locator('main').innerText()));

  // 11. Elimina lezione e modulo.
  await vaiA(urlCorso);
  await Promise.all([a.waitForNavigation(), a.locator('.co-modulo').nth(1).locator('.co-modulo-testa button:has-text("Elimina")').click()]);
  ok('un modulo con lezioni non si elimina, e si dice perché', /ha ancora delle lezioni/.test(await flash()));

  // 12. Anna cancella l'account; Luca anche: il suo certificato non vale più.
  await vaiL('/area-riservata/profilo/');
  await luca.fill('#membri-elimina [name=attuale]', PW); await luca.check('#membri-elimina [name=sicuro]');
  await Promise.all([luca.waitForNavigation(), luca.click('#membri-elimina button')]);
  r = await v0.goto(B + '/certificato/' + codice + '/', { waitUntil: 'networkidle' });
  ok('account cancellato: via avanzamento e certificato (il codice dà 404)', r.status() === 404);
  await vaiA(urlCorso);
  ok('e non è più fra chi segue il corso', await a.locator('#co-iscritti tr[data-email="luca@esempio.it"]').count() === 0);

  // 13. Spegnere Corsi online: il sito torna come prima.
  execFileSync('php', ['-r', `require '${DIR}/includes/config.php'; db()->exec("DELETE FROM " . tbl('pages') . " WHERE slug = 'prova-corsi'");`]);
  const f2 = await addon(a, B, 'corsi', false);
  ok('Spegni Corsi online: "i dati restano"', /spento/.test(f2), f2);
  ok('spento: /corsi/ e /certificato/ danno 404', (await v0.request.get(B + '/corsi/')).status() === 404 && (await v0.request.get(B + '/certificato/')).status() === 404);
  await addon(a, B, 'membri', false);
  const diversi = [];
  for (const u of Object.keys(prima)) if (pulisci(await (await v0.request.get(B + u)).text()) !== prima[u]) diversi.push(u);
  ok('spenti tutti e due: home, Servizi e Blog identici a prima', diversi.length === 0, diversi);
  await addon(a, B, 'corsi', true);
  r = await v0.request.get(B + '/corsi/');
  ok('riacceso: il corso è ancora lì', r.status() === 200 && /Fotografia per cominciare/.test(await r.text()));

  fine(err);
  await br.close();
})().catch(async e => { console.error(e); process.exitCode = 2; if (br) await br.close(); });
