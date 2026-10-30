// Addon Corsi online (corsi, 2.0.0): tutto il percorso, come lo fanno l'amministratore e due membri nel browser.
// Su un sito nuovo installato con il kit Attività (prove/prova-kit.js), con il server di posta finto acceso
// (python3 -I prove/smtp-finto.py <porta smtp> <cartella posta>) e ffmpeg per il video di prova.
// L'amministratore accende Corsi online (si accende anche l'Area riservata), crea un corso con due moduli e tre lezioni
// (testo, video di YouTube, video caricato, materiale riservato), le riordina, crea un secondo corso solo su assegnazione.
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

  // 3. Le lezioni: testo (con uno script che deve sparire), video di YouTube, indirizzo sbagliato, video caricato, materiale.
  await vaiA(uLuce);
  await a.fill('#le-testo', '<h2>La luce del mattino</h2><p>La luce migliore arriva <b>presto</b>.</p><script>alert(1)</script><p><img src=x onerror="alert(2)"></p>');
  await a.fill('#le-video', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
  await a.fill('#le-durata', '10 minuti');
  await Promise.all([a.waitForNavigation(), a.click('#le-form aside button.btn-primary')]);
  const testo = await a.inputValue('#le-testo');
  ok('lezione salvata: il testo resta, script e onerror no', /Lezione salvata/.test(await flash()) && /La luce migliore/.test(testo) && !/script|onerror/i.test(testo), testo);
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
  await vaiA(uInq);
  await a.fill('#le-testo', '<p>La regola dei terzi.</p>');
  await a.setInputFiles('#le-file', webm);
  await a.locator('label.le-mat', { hasText: 'scheda-inquadratura.pdf' }).locator('input').check();
  await Promise.all([a.waitForNavigation(), a.click('#le-form aside button.btn-primary')]);
  ok('video caricato e materiale scelti', /Lezione salvata/.test(await flash()) && await a.locator('#le-video-caricato').count() === 1 && await a.locator('label.le-mat input:checked').count() === 1);
  const finto = path.join(tmp, 'finto.webm'); fs.writeFileSync(finto, '<?php echo 1; ?>');
  await a.setInputFiles('#le-file', finto);
  await Promise.all([a.waitForNavigation(), a.click('#le-form aside button.btn-primary')]);
  ok('un file che non è un video si rifiuta', /non è un video/.test(await flash()));
  await vaiA(uScatto);
  await a.fill('#le-testo', '<p>Esci e scatta.</p>'); await a.fill('#le-video', 'https://vimeo.com/76979871');
  await Promise.all([a.waitForNavigation(), a.click('#le-form aside button.btn-primary')]);
  ok('un video di Vimeo va bene', /Lezione salvata/.test(await flash()));
  // riordinare: L'inquadratura sale prima della luce
  await vaiA(urlCorso);
  await Promise.all([a.waitForNavigation(), a.locator('li[data-lezione="l-inquadratura"] button.co-su').click()]);
  const ordine = await a.locator('.co-modulo').first().locator('li[data-lezione]').evaluateAll(l => l.map(x => x.dataset.lezione));
  ok('le frecce riordinano le lezioni', JSON.stringify(ordine) === '["l-inquadratura","la-luce"]', ordine);
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
