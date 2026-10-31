// Attacchi agli addon Area riservata, Corsi online e Copia del sito altrove (2.0.0). Ogni OK è un attacco respinto.
// Su un sito nuovo installato con il kit Attività, con il server di posta finto acceso (prove/smtp-finto.py).
// La prova accende i tre addon, prepara una pagina riservata, due membri, due corsi (uno solo su assegnazione, con
// video e materiale) e poi attacca: accesso senza login, sessioni scambiate fra sito e pannello, cookie inventati,
// lezioni, video e materiali di un corso non proprio, link scaduti o manomessi, CSRF, XSS nei nomi, CSV con formule,
// SQL injection, forza bruta all'accesso, rimandi fuori dal sito, enumerazione degli account, path traversal,
// certificato di un altro, SSRF nella destinazione della copia, password della copia mai mostrata.
// Dopo: svuotare <prefisso>sicurezza_tentativi (la forza bruta blocca 127.0.0.1 per l'area riservata).
// uso: node prove/attacchi-addon-a.js <base> <cartella CMS> <utente> <password> <porta smtp> <cartella posta>
const { chromium, request } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { conta, pagina, entraPannello, addon, postaFinta, posta, aspettaEmail, linkDa } = require('./addon-a-comune');
const [B, DIR, UTENTE, PASSWORD, SMTP, POSTA] = process.argv.slice(2);
const { ok, fine } = conta();
const PW = 'Lampone-Verde-2026!';
const BASEP = new URL(B).pathname.replace(/\/$/, '');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'attacchi-a-'));
const php = codice => execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='127.0.0.1'; require '${DIR}/includes/config.php'; ${codice}`], { encoding: 'utf8' });
const sql = q => php(`$r = db()->query(str_replace('{p}', DB_PREFIX, ${JSON.stringify(q)})); echo json_encode($r ? $r->fetchAll() : []);`);
const mt = html => (html.match(/name="_mt" value="([a-f0-9]{40})"/) || [])[1] || '';

let br;
(async () => {
  br = await chromium.launch();
  const err = [];
  const dialoghi = [];
  const a = await pagina(br, err);
  a.on('dialog', d => dialoghi.push(d.message()));
  const vaiA = u => a.goto(B + u, { waitUntil: 'networkidle' });
  await entraPannello(a, B, UTENTE, PASSWORD);
  await postaFinta(a, B, SMTP);
  for (const x of ['membri', 'corsi', 'copia-altrove']) await addon(a, B, x, true);

  // ---------------- Preparazione
  php(`db()->exec("DELETE FROM " . tbl('pages') . " WHERE slug = 'segreta'"); db()->prepare("INSERT INTO " . tbl('pages') . " (title, slug, status, html, created_at, updated_at) VALUES ('Pagina segreta', 'segreta', 'published', '<p>Codice SEGRETO-XYZ-42 solo per i membri.</p>', NOW(), NOW())")->execute();`);
  await vaiA('/admin/addon-pagina.php?a=membri&p=riservati');
  await a.locator('label.mr-voce', { hasText: '/segreta/' }).locator('input').check();
  await Promise.all([a.waitForNavigation(), a.click('#mr-contenuti button[type=submit]')]);
  const invita = async (nome, email) => {
    await vaiA('/admin/addon-pagina.php?a=membri&p=elenco');
    const da = posta(POSTA).length;
    await a.fill('#mb-nome', nome); await a.fill('#mb-email', email);
    await Promise.all([a.waitForNavigation(), a.click('#mb-invita button')]);
    const m = await aspettaEmail(POSTA, email, da);
    const p = await pagina(br, err);
    p.on('dialog', d => dialoghi.push(d.message()));
    await p.goto(linkDa(m, '/nuova-password/?t='), { waitUntil: 'networkidle' });
    await p.fill('#membri-nuova-password [name=password]', PW); await p.fill('#membri-nuova-password [name=password2]', PW);
    await Promise.all([p.waitForNavigation(), p.click('#membri-nuova-password button')]);
    return p;
  };
  const XSS = '<img src=x onerror=alert(1)><script>alert(2)</script>';
  const A = await invita('Alice ' + XSS, 'alice@esempio.it');
  const Bm = await invita('=HYPERLINK("http://cattivo.example","clic")', 'bruno@esempio.it');
  // file riservati: uno per un corso, uno mai dato a nessuno
  for (const [n, t] of [['materiale-b.pdf', 'Materiale B'], ['mai-dato.pdf', 'Mai dato']]) {
    fs.writeFileSync(path.join(tmp, n), '%PDF-1.4\n% ' + n + '\n%%EOF\n');
    await vaiA('/admin/scaricabili.php');
    await a.setInputFiles('#dl-carica input[type=file]', path.join(tmp, n)); await a.fill('#dl-carica [name=titolo]', t); await a.check('#dl-riservato');
    await Promise.all([a.waitForNavigation(), a.click('#dl-carica button[type=submit]')]);
  }
  const webm = path.join(tmp, 'v.webm');
  execFileSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', '-f', 'lavfi', '-i', 'testsrc=duration=1:size=160x120:rate=5', '-c:v', 'libvpx', webm]);
  const corso = async (titolo, accesso, lezione, conVideo) => {
    await vaiA('/admin/addon-pagina.php?a=corsi&p=corsi');
    await a.fill('#co-titolo', titolo); await Promise.all([a.waitForNavigation(), a.click('#co-nuovo button')]);
    const u = a.url().replace(B, '');
    if (accesso === 'assegnati') await a.check('[name=accesso][value=assegnati]');
    await a.selectOption('#co-stato', 'pubblicato');
    await Promise.all([a.waitForNavigation(), a.click('#co-dati button[type=submit]')]);
    const f = a.locator('.co-modulo').first().locator('form.co-nuova-lezione');
    await f.locator('input[name=titolo]').fill(lezione); await Promise.all([a.waitForNavigation(), f.locator('button').click()]);
    await a.fill('#le-testo', '<p>Testo della lezione ' + lezione + '</p>');
    if (conVideo) { await a.setInputFiles('#le-file', webm); await a.locator('label.le-mat', { hasText: 'materiale-b.pdf' }).locator('input').check(); }
    await Promise.all([a.waitForNavigation(), a.click('#le-form aside button.btn-primary')]);
    return u;
  };
  await corso('Corso aperto', 'tutti', 'Prima lezione', false);
  await corso('Corso chiuso', 'assegnati', 'Lezione chiusa', true);
  const idChiusa = JSON.parse(sql("SELECT id FROM {p}corsi_lezioni WHERE slug = 'lezione-chiusa'"))[0].id;
  const idAperta = JSON.parse(sql("SELECT id FROM {p}corsi_lezioni WHERE slug = 'prima-lezione'"))[0].id;
  // Alice finisce il corso aperto: ha un certificato.
  await A.goto(B + '/corsi/corso-aperto/prima-lezione/', { waitUntil: 'networkidle' });
  await Promise.all([A.waitForNavigation(), A.click('form.corsi-completa button')]);
  await A.goto(B + '/corsi/corso-aperto/certificato/', { waitUntil: 'networkidle' });
  const codiceA = (await A.locator('#cert-codice').innerText()).trim();

  const fuori = await request.newContext();
  const g = (ctx, u, o = {}) => ctx.get(B + u, { maxRedirects: 0, ...o });

  // ---------------- 1. Accesso senza login
  let r = await g(fuori, '/area-riservata/');
  ok('senza login /area-riservata/ porta all\'accesso', r.status() === 303 && /\/accedi\//.test(r.headers().location));
  r = await g(fuori, '/area-riservata/profilo/');
  ok('senza login il profilo porta all\'accesso', r.status() === 303);
  r = await g(fuori, '/segreta/');
  let t = await r.text();
  ok('senza login la pagina riservata non ha il testo', !t.includes('SEGRETO-XYZ-42') && /solo per i membri/i.test(t));
  for (const u of ['/llms-full.txt', '/llms.txt', '/feed/', '/sitemap.xml']) {
    t = await (await g(fuori, u)).text();
    ok('il testo riservato non esce da ' + u, !t.includes('SEGRETO-XYZ-42'));
  }
  t = await (await g(fuori, '/?s=SEGRETO-XYZ-42')).text();
  ok('la ricerca del sito non trova la pagina riservata per il suo testo', !t.includes('/segreta/'));
  // 2.1: nemmeno per il titolo, e titolo e indirizzo non escono negli elenchi pubblici.
  t = await (await g(fuori, '/?s=' + encodeURIComponent('Pagina segreta'))).text();
  ok('la ricerca del sito non trova la pagina riservata nemmeno per il titolo', !t.includes('/segreta/') && !/Pagina segreta<\/span>/.test(t));
  for (const u of ['/sitemap.xml', '/llms.txt', '/llms-full.txt']) {
    t = await (await g(fuori, u)).text();
    ok('titolo e indirizzo della pagina riservata non escono da ' + u, !t.includes('/segreta/') && !t.includes('Pagina segreta'));
  }
  const nomeCookie = php("require_once cms_radice() . '/addon/membri/includes/membri.php'; echo membri_cookie_nome();").trim();
  const finto = await request.newContext({ extraHTTPHeaders: { Cookie: nomeCookie + '=' + 'b'.repeat(64) } });
  t = await (await g(finto, '/?s=' + encodeURIComponent('Pagina segreta'))).text();
  ok('con un cookie del membro inventato la ricerca non trova la pagina riservata', !t.includes('/segreta/'));
  r = await g(fuori, '/corsi/corso-aperto/prima-lezione/');
  ok('senza login la lezione porta all\'accesso', r.status() === 303);
  r = await g(fuori, `/corsi/corso-chiuso/video/${idChiusa}/`);
  ok('senza login il video non si scarica', r.status() === 303);
  r = await g(fuori, `/corsi/corso-chiuso/materiale/${idChiusa}/materiale-b/`);
  ok('senza login il materiale non si scarica', r.status() === 303);
  r = await g(fuori, '/corsi/corso-aperto/certificato/');
  ok('senza login il certificato del corso porta all\'accesso', r.status() === 303);

  // ---------------- 2. Sessioni: sito e pannello separati, cookie inventati
  const cookieA = (await A.context().cookies()).find(c => /^membri_[a-f0-9]{8}$/.test(c.name));
  const soloMembro = await request.newContext({ extraHTTPHeaders: { Cookie: cookieA.name + '=' + cookieA.value } });
  for (const u of ['/admin/index.php', '/admin/addon-pagina.php?a=membri&p=elenco', '/admin/addon-pagina.php?a=corsi&p=corsi', '/admin/addon-pagina.php?a=copia-altrove&p=destinazione']) {
    r = await g(soloMembro, u);
    ok('il cookie del membro non apre il pannello: ' + u, r.status() === 302 && /login\.php/.test(r.headers().location || ''));
  }
  const cookieAdmin = (await a.context().cookies()).filter(c => !/^membri_/.test(c.name)).map(c => c.name + '=' + c.value).join('; ');
  const soloAdmin = await request.newContext({ extraHTTPHeaders: { Cookie: cookieAdmin } });
  r = await g(soloAdmin, '/area-riservata/');
  ok('la sessione del pannello non è un membro: /area-riservata/ porta all\'accesso', r.status() === 303);
  t = await (await g(soloAdmin, '/segreta/')).text();
  ok('l\'amministratore senza anteprima vede la pagina riservata chiusa', !t.includes('SEGRETO-XYZ-42'));
  t = await (await g(soloAdmin, '/segreta/?preview=1')).text();
  ok('(con ?preview=1 la vede: è l\'anteprima del pannello, voluta)', t.includes('SEGRETO-XYZ-42'));
  const inventato = await request.newContext({ extraHTTPHeaders: { Cookie: cookieA.name + '=' + 'a'.repeat(64) } });
  r = await g(inventato, '/area-riservata/');
  ok('un cookie del membro inventato non vale', r.status() === 303);
  const corto = await request.newContext({ extraHTTPHeaders: { Cookie: cookieA.name + '=' + cookieA.value.slice(0, 63) + "'" } });
  r = await g(corto, '/area-riservata/');
  ok('un cookie manomesso (con un apice) non vale e non rompe niente', r.status() === 303);
  const nomeSbagliato = await request.newContext({ extraHTTPHeaders: { Cookie: 'membri_00000000=' + cookieA.value } });
  r = await g(nomeSbagliato, '/area-riservata/');
  ok('il token del membro sotto il nome del cookie di un altro sito non vale', r.status() === 303);

  // ---------------- 3. Corsi: lezioni, video e materiali di un corso non proprio; certificato di un altro
  r = await A.goto(B + '/corsi/corso-chiuso/lezione-chiusa/', { waitUntil: 'networkidle' });
  ok('corso non assegnato: la lezione dà 403', r.status() === 403 && !(await A.content()).includes('Testo della lezione Lezione chiusa'));
  r = await g(soloMembro, `/corsi/corso-chiuso/video/${idChiusa}/`);
  ok('corso non assegnato: il video dà 403', r.status() === 403);
  r = await g(soloMembro, `/corsi/corso-chiuso/materiale/${idChiusa}/materiale-b/`);
  ok('corso non assegnato: il materiale dà 403', r.status() === 403);
  r = await g(soloMembro, `/corsi/corso-aperto/video/${idChiusa}/`);
  ok('il video di un altro corso dal corso aperto: 404', r.status() === 404);
  r = await g(soloMembro, `/corsi/corso-aperto/materiale/${idAperta}/materiale-b/`);
  ok('un materiale che la lezione non ha: 404', r.status() === 404);
  r = await g(soloMembro, `/corsi/corso-aperto/materiale/${idAperta}/mai-dato/`);
  ok('un file riservato mai dato: 404', r.status() === 404);
  for (const u of [`/corsi/corso-aperto/materiale/${idAperta}/..%2F..%2Fincludes%2Fconfig.local.php/`, '/area-riservata/file/..%2F..%2Fincludes%2Fconfig.local.php/', '/area-riservata/file/mai-dato/', `/corsi/..%2F..%2Fincludes/`, '/corsi/corso-aperto/..%2Fcorso-chiuso%2Flezione-chiusa/']) {
    r = await g(soloMembro, u);
    t = await r.text();
    ok('path traversal respinto: ' + decodeURIComponent(u), [303, 403, 404].includes(r.status()) && !/DB_PASS|CMS_SEGRETO|Testo della lezione Lezione chiusa/.test(t), r.status());
  }
  t = await (await g(soloMembro, '/corsi/corso-chiuso/lezione-chiusa/')).text();
  ok('la pagina di un corso non proprio non svela il nome della cartella dei video', !/corsi-[a-f0-9]{16}/.test(t));
  const certB = await Bm.goto(B + '/corsi/corso-aperto/certificato/', { waitUntil: 'networkidle' });
  ok('certificato di un altro: Bruno vede "arriva alla fine", non quello di Alice', /arriva alla fine/.test(await Bm.locator('h1').innerText()) && !(await Bm.content()).includes(codiceA));
  r = await g(fuori, '/certificato/AAAA-BBBB-CCCC/');
  ok('un codice di certificato inventato: 404', r.status() === 404);
  r = await g(fuori, '/certificato/' + codiceA.replace(/.$/, c => c === 'Z' ? 'Y' : 'Z') + '/');
  ok('un codice di certificato con un carattere cambiato: 404', r.status() === 404);
  r = await g(fuori, "/certificato/%27%20OR%201%3D1%20--/");
  ok('SQL injection nel codice del certificato: 404', r.status() === 404);
  ok('il codice del certificato ha 60 bit a caso (12 caratteri di 32)', /^[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/.test(codiceA));

  // ---------------- 4. Link scaduti o manomessi
  for (const [u, nome] of [['/nuova-password/?t=' + 'b'.repeat(64), 'password inventato'], ['/conferma-iscrizione/?t=' + 'c'.repeat(64), 'conferma inventato'], ['/conferma-email/?t=' + 'd'.repeat(64), 'cambio email inventato'], ['/nuova-password/?t=%27%20OR%201%3D1', 'con SQL']]) {
    r = await g(fuori, u);
    ok('link ' + nome + ': 410, niente modulo', r.status() === 410 && !/membri-nuova-password/.test(await r.text()));
  }
  let da = posta(POSTA).length;
  const csrfFuori = async ctx => { const h = await (await g(ctx, '/password-dimenticata/')).text(); return mt(h); };
  const v = await pagina(br, err);
  await v.goto(B + '/password-dimenticata/', { waitUntil: 'networkidle' });
  await v.fill('#membri-dimenticata [name=email]', 'bruno@esempio.it');
  await Promise.all([v.waitForNavigation(), v.click('#membri-dimenticata button')]);
  const link = linkDa(await aspettaEmail(POSTA, 'bruno@esempio.it', da), '/nuova-password/?t=');
  const tok = link.split('t=')[1];
  const manomesso = tok.slice(0, -1) + (tok.endsWith('a') ? 'b' : 'a');
  r = await g(fuori, '/nuova-password/?t=' + manomesso);
  ok('link della password con un carattere cambiato: 410', r.status() === 410);
  r = await g(fuori, '/conferma-iscrizione/?t=' + tok);
  ok('il token della password usato come conferma dell\'iscrizione: 410', r.status() === 410);
  php(`db()->exec("UPDATE " . tbl('membri_token') . " SET scade = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE scopo = 'password'");`);
  r = await g(fuori, '/nuova-password/?t=' + tok);
  ok('link della password scaduto: 410', r.status() === 410);

  // ---------------- 5. CSRF
  const post = (ctx, u, form, headers = {}) => ctx.post(B + u, { form, maxRedirects: 0, headers });
  r = await post(fuori, '/accedi/', { email: 'alice@esempio.it', password: PW });
  ok('POST /accedi/ senza token: 403', r.status() === 403);
  const altro = await request.newContext();
  const tokAltro = await csrfFuori(altro);
  r = await post(fuori, '/accedi/', { email: 'alice@esempio.it', password: PW, _mt: tokAltro });
  ok('POST /accedi/ con il token di un altro browser: 403', r.status() === 403);
  const mio = await request.newContext();
  const tokMio = await csrfFuori(mio);
  r = await post(mio, '/accedi/', { email: 'alice@esempio.it', password: PW, _mt: tokMio }, { Origin: 'https://cattivo.example' });
  ok('POST /accedi/ da un altro sito (Origin): 403', r.status() === 403);
  r = await post(soloMembro, '/area-riservata/profilo/', { azione: 'nome', nome: 'Rubato' });
  ok('CSRF sul profilo (cambio nome) senza token: 403', r.status() === 403 && !/Rubato/.test(sql("SELECT nome FROM {p}membri WHERE email = 'alice@esempio.it'")));
  r = await post(soloMembro, '/area-riservata/profilo/', { azione: 'elimina', sicuro: '1', attuale: PW });
  ok('CSRF su "Cancella il mio account" senza token: 403, l\'account c\'è ancora', r.status() === 403 && /alice@esempio\.it/.test(sql("SELECT email FROM {p}membri")));
  r = await post(soloMembro, '/esci/', {});
  ok('CSRF su Esci senza token: 403, la sessione resta', r.status() === 403 && (await g(soloMembro, '/area-riservata/')).status() === 200);
  r = await post(soloMembro, '/corsi/corso-aperto/prima-lezione/', { azione: 'da-fare' });
  ok('CSRF su "Segna come da fare" senza token: 403', r.status() === 403);
  r = await post(soloAdmin, '/admin/addon-pagina.php?a=membri&p=elenco', { azione: 'elimina', id: '1' });
  ok('CSRF nel pannello (elimina un membro) senza token: 403', r.status() === 403 && JSON.parse(sql('SELECT COUNT(*) AS n FROM {p}membri'))[0].n >= 2);
  r = await post(soloAdmin, '/admin/addon-pagina.php?a=copia-altrove&p=destinazione', { azione: 'manda' });
  ok('CSRF nel pannello (manda una copia) senza token: 403', r.status() === 403);
  r = await post(soloAdmin, '/admin/addon-pagina.php?a=corsi&p=corsi', { azione: 'nuovo', titolo: 'Corso CSRF' });
  ok('CSRF nel pannello (nuovo corso) senza token: 403', r.status() === 403 && !/Corso CSRF/.test(sql('SELECT titolo FROM {p}corsi')));

  // ---------------- 6. XSS nei nomi e CSV con formule
  await A.goto(B + '/area-riservata/', { waitUntil: 'networkidle' });
  ok('XSS nel nome del membro: nell\'area riservata esce come testo', /Ciao Alice <img src=x onerror=alert\(1\)><script>alert\(2\)<\/script>/.test(await A.locator('.membri-ciao').innerText()) && await A.locator('.membri-ciao img, .membri-ciao script').count() === 0);
  await vaiA('/admin/addon-pagina.php?a=membri&p=elenco');
  ok('XSS nel nome del membro: nell\'elenco del pannello esce come testo', await a.locator('#mb-elenco td img, #mb-elenco td script').count() === 0 && /onerror=alert/.test(await a.locator('#mb-elenco').innerText()));
  await vaiA('/admin/addon-pagina.php?a=corsi&p=corsi');
  await a.fill('#co-titolo', 'Corso ' + XSS); await Promise.all([a.waitForNavigation(), a.click('#co-nuovo button')]);
  await a.selectOption('#co-stato', 'pubblicato'); await Promise.all([a.waitForNavigation(), a.click('#co-dati button[type=submit]')]);
  const lezForm = a.locator('.co-modulo').first().locator('form.co-nuova-lezione');
  await lezForm.locator('input[name=titolo]').fill('Lezione ' + XSS); await Promise.all([a.waitForNavigation(), lezForm.locator('button').click()]);
  await a.fill('#le-video', 'javascript:alert(3)'); await Promise.all([a.waitForNavigation(), a.click('#le-form aside button.btn-primary')]);
  ok('un video "javascript:" si rifiuta', /YouTube/.test(await a.locator('.flash').first().innerText()));
  await A.goto(B + '/corsi/', { waitUntil: 'networkidle' });
  ok('XSS nel titolo del corso: sul sito esce come testo', await A.locator('.corsi-scheda h2 img, .corsi-scheda script').count() === 0 && /onerror=alert/.test(await A.locator('main').innerText()));
  const slugX = JSON.parse(sql("SELECT slug FROM {p}corsi WHERE titolo LIKE 'Corso <img%'"))[0].slug;
  await A.goto(B + '/corsi/' + slugX + '/', { waitUntil: 'networkidle' });
  const lezX = await A.locator('.corsi-lezioni a').first().getAttribute('href');
  await A.goto(new URL(lezX, B + '/').href, { waitUntil: 'networkidle' });
  ok('XSS nel titolo della lezione: esce come testo', await A.locator('h1 img, h1 script').count() === 0);
  await Promise.all([A.waitForNavigation(), A.click('form.corsi-completa button')]);
  await A.goto(B + '/corsi/' + slugX + '/certificato/', { waitUntil: 'networkidle' });
  ok('XSS nel nome e nel corso dentro il certificato: escono come testo', await A.locator('.cert img, .cert script').count() === 0 && /onerror/.test(await A.locator('#cert-nome').innerText()));
  await vaiA('/admin/addon-pagina.php?a=membri&p=elenco');
  const [csvDl] = await Promise.all([a.waitForEvent('download'), a.click('#mb-esporta')]);
  const csv = fs.readFileSync(await csvDl.path(), 'utf8');
  ok('CSV: il nome che comincia con "=" esce con l\'apice davanti (niente formule in Excel)', /"'=HYPERLINK/.test(csv) && !/;"=HYPERLINK/.test(csv) && !/^=HYPERLINK/m.test(csv));
  ok('nessuna finestra di alert si è aperta, in nessuna pagina', dialoghi.length === 0, dialoghi);

  // ---------------- 7. SQL injection, rimandi fuori dal sito, enumerazione
  const sqli = "' OR 1=1 -- ";
  let c = await request.newContext();
  let tk = await csrfFuori(c);
  r = await post(c, '/accedi/', { email: sqli, password: sqli, _mt: tk });
  ok('SQL injection nell\'accesso: non entra', r.status() === 200 && /non corrette/.test(await r.text()));
  tk = await csrfFuori(c);
  r = await post(c, '/accedi/', { email: "alice@esempio.it' -- ", password: 'x', _mt: tk });
  ok('SQL injection con un\'email vera: non entra', r.status() === 200 && /non corrette/.test(await r.text()));
  r = await g(soloAdmin, '/admin/addon-pagina.php?a=membri&p=elenco&cerca=' + encodeURIComponent("' OR 1=1 -- "));
  ok('SQL injection nella ricerca dei membri: nessun risultato, nessun errore', r.status() === 200 && /Ancora nessuno/.test(await r.text()));
  r = await g(fuori, "/corsi/x'%20or%20'1'='1/");
  ok('SQL injection nell\'indirizzo del corso: 404', r.status() === 404);
  r = await g(soloAdmin, "/admin/addon-pagina.php?a=corsi&p=corso&id=1%20OR%201=1");
  ok('SQL injection nell\'id del corso del pannello: niente di strano (l\'id è un numero)', [200, 404].includes(r.status()));
  for (const torna of ['//cattivo.example/', 'https://cattivo.example/', '/\\cattivo.example', 'javascript:alert(1)']) {
    c = await request.newContext();
    tk = await csrfFuori(c);
    r = await post(c, '/accedi/', { email: 'bruno@esempio.it', password: PW, torna, _mt: tk });
    const loc = r.headers().location || '';
    ok('rimando dopo l\'accesso fuori dal sito respinto: ' + torna, r.status() === 303 && new URL(loc, B).origin === new URL(B).origin && /\/area-riservata\/$/.test(loc), loc);
  }
  c = await request.newContext();
  const h1 = await (await g(c, '/password-dimenticata/')).text();
  r = await post(c, '/password-dimenticata/', { email: 'alice@esempio.it', _mt: mt(h1) });
  const t1 = (await r.text()).replace(/name="_mt" value="[^"]*"/g, '').replace(/nonce="[^"]*"/g, '');
  const h2 = await (await g(c, '/password-dimenticata/')).text();
  r = await post(c, '/password-dimenticata/', { email: 'nessuno-' + Date.now() + '@esempio.it', _mt: mt(h2) });
  const t2 = (await r.text()).replace(/name="_mt" value="[^"]*"/g, '').replace(/nonce="[^"]*"/g, '');
  ok('enumerazione con Password dimenticata: la pagina è identica per un\'email che c\'è e una che non c\'è', t1 === t2);
  c = await request.newContext();
  const e1 = await (async () => { const tk2 = await csrfFuori(c); return (await (await post(c, '/accedi/', { email: 'alice@esempio.it', password: 'sbagliata-123456', _mt: tk2 })).text()).match(/role="alert">([^<]*)</)[1]; })();
  const e2 = await (async () => { const tk2 = await csrfFuori(c); return (await (await post(c, '/accedi/', { email: 'nessuno@esempio.it', password: 'sbagliata-123456', _mt: tk2 })).text()).match(/role="alert">([^<]*)</)[1]; })();
  ok('enumerazione con l\'accesso: lo stesso messaggio', e1 === e2 && /non corrette/.test(e1), [e1, e2]);
  c = await request.newContext();
  tk = await csrfFuori(c);
  r = await post(c, '/iscriviti/', { nome: 'X', email: "x@esempio.it\r\nBcc: spia@cattivo.example", password: PW, password2: PW, privacy: '1', captcha: '', _mt: tk });
  ok('un\'email con un a capo (intestazione nascosta) si rifiuta', /Controlla l('|&#039;|&apos;)indirizzo email/.test(await r.text()));

  // ---------------- 8. Forza bruta all'accesso
  c = await request.newContext();
  let bloccato = false;
  for (let i = 0; i < 12; i++) {
    tk = await csrfFuori(c);
    const tt = await (await post(c, '/accedi/', { email: 'bruno@esempio.it', password: 'tentativo-' + i, _mt: tk })).text();
    if (/Troppi tentativi/.test(tt)) { bloccato = true; break; }
  }
  ok('forza bruta: dopo 10 password sbagliate l\'accesso si blocca', bloccato);
  tk = await csrfFuori(c);
  t = await (await post(c, '/accedi/', { email: 'bruno@esempio.it', password: PW, _mt: tk })).text();
  ok('durante il blocco non entra nemmeno con la password giusta', /Troppi tentativi/.test(t));
  ok('il blocco sta nel registro della pagina Sicurezza', /membri-ip:127\.0\.0\.1/.test(sql("SELECT chiave FROM {p}sicurezza_tentativi WHERE bloccato_fino > NOW()")));
  r = await g(soloAdmin, '/admin/index.php');
  ok('il blocco dell\'area riservata non chiude fuori il pannello', r.status() === 200);
  php(`db()->exec("DELETE FROM " . tbl('sicurezza_tentativi') . " WHERE chiave LIKE 'membri-%'");`);

  // ---------------- 9. Accesso diretto ai file degli addon
  for (const u of ['/addon/membri/modulo.php', '/addon/membri/includes/membri.php', '/addon/corsi/includes/corsi.php', '/addon/copia-altrove/includes/copia-altrove.php', '/moduli/addon-attivi.json', '/addon/copia-altrove/includes/']) {
    r = await g(fuori, u);
    ok('file dell\'addon non servito dal web: ' + u, r.status() === 403);
  }

  // ---------------- 10. SSRF nella destinazione della copia, e la password mai mostrata
  const salva = async campi => {
    await vaiA('/admin/addon-pagina.php?a=copia-altrove&p=destinazione');
    await a.selectOption('#ca-tipo', campi.tipo);
    for (const [k, val] of Object.entries(campi)) if (k !== 'tipo') await a.fill('#ca-' + k, String(val));
    await Promise.all([a.waitForNavigation(), a.click('#ca-salva')]);
    return (await a.locator('.flash').first().innerText()).trim();
  };
  const prova = async () => { await vaiA('/admin/addon-pagina.php?a=copia-altrove&p=destinazione'); await Promise.all([a.waitForNavigation(), a.click('#ca-prova')]); return (await a.locator('.flash').first().innerText()).trim(); };
  await salva({ tipo: 'ftp', host: '127.0.0.1', porta: 21, utente: 'u', password: 'Password-Della-Copia-77' });
  ok('la password della copia non compare nella pagina', !(await a.content()).includes('Password-Della-Copia-77'));
  for (const [host, porta] of [['127.0.0.1', 3306], ['localhost', 21], ['10.0.0.1', 21], ['192.168.1.1', 21], ['172.16.0.1', 21], ['169.254.169.254', 80], ['100.64.0.1', 21], ['0.0.0.0', 21], ['::1', 21], ['::ffff:127.0.0.1', 21], ['fd00::1', 21]]) {
    await salva({ tipo: 'ftp', host, porta });
    const f = await prova();
    ok(`SSRF verso ${host}:${porta} respinto ("rete interna")`, /rete interna/.test(f), f);
  }
  await salva({ tipo: 'webdav', url: 'http://127.0.0.1:3306/' });
  ok('SSRF WebDAV verso http://127.0.0.1:3306/ respinto', /https|rete interna/.test(await prova()));
  await salva({ tipo: 'webdav', url: 'https://169.254.169.254/latest/meta-data/' });
  ok('SSRF WebDAV verso i metadati del cloud (169.254.169.254) respinto', /rete interna/.test(await prova()));
  for (const url of ['file:///etc/passwd', 'gopher://127.0.0.1:25/', 'https://utente:pw@esempio.it/dav/', 'dict://127.0.0.1:11211/']) {
    const f = await salva({ tipo: 'webdav', url });
    ok('indirizzo WebDAV non ammesso al salvataggio: ' + url, /Non salvato/.test(f), f);
  }
  const reg = await (await g(soloAdmin, '/admin/addon-pagina.php?a=copia-altrove&p=destinazione')).text();
  ok('la password non compare nemmeno nel registro degli invii', !reg.includes('Password-Della-Copia-77'));
  ok('nel database la password della copia è cifrata', !/Password-Della-Copia-77/.test(php("echo get_setting('copia_altrove_cfg');")));
  r = await post(soloMembro, '/admin/addon-pagina.php?a=copia-altrove&p=destinazione', { azione: 'manda' });
  ok('il membro non manda copie dal pannello', r.status() === 302);

  // ---------------- 11. (2.1) OAuth di Google Drive e Dropbox: state falso, rubato, ripetuto; rimandi fuori; token mai nell'HTML.
  // Servono i server finti di prove/server-cloud-finti.py (CLOUD_GOOGLE, di partenza 8321, CLOUD_DROPBOX 8322) con il
  // redirect URI <base>/admin/oauth-ritorno.php registrato.
  const CONFIG = path.join(DIR, 'includes', 'config.local.php');
  const G = 'http://127.0.0.1:' + (process.env.CLOUD_GOOGLE || '8321'), DBX = 'http://127.0.0.1:' + (process.env.CLOUD_DROPBOX || '8322');
  const conf0 = fs.readFileSync(CONFIG, 'utf8').replace(/\ndefine\('CMS_COPIA_ALTROVE_CLOUD_PROVA'[^\n]*/g, '').replace(/\s*$/, '\n');
  fs.writeFileSync(CONFIG, conf0 + `define('CMS_COPIA_ALTROVE_CLOUD_PROVA', 'gdrive=${G},dropbox=${DBX}');\n`);
  await new Promise(x => setTimeout(x, 2500));
  const stato = async base => (await (await fuori.get(base + '/_stato')).json());
  const PCA = '/admin/addon-pagina.php?a=copia-altrove&p=destinazione';
  const RIT = '/admin/oauth-ritorno.php';
  const SEG = { gdrive: 'GOCSPX-prova-segreto-2026', dropbox: 'segretodropbox2026' };
  const ID = { gdrive: 'prova-plumecms.apps.googleusercontent.com', dropbox: 'provadropbox2026' };
  const collega = async (pg, s) => {
    await pg.goto(B + PCA, { waitUntil: 'networkidle' });
    await pg.selectOption('#ca-tipo', s);
    await pg.fill(`#ca-${s}-client_id`, ID[s]); await pg.fill(`#ca-${s}-segreto`, SEG[s]);
    await Promise.all([pg.waitForNavigation(), pg.click(`#ca-${s}-collega`)]);
    await pg.waitForURL(u => u.toString().startsWith(s === 'gdrive' ? G : DBX), { timeout: 10000 });
    return pg.url();
  };
  const flashDi = async pg => (await pg.locator('.flash').first().innerText().catch(() => '')).trim();
  for (const s of ['gdrive', 'dropbox']) {
    const base = s === 'gdrive' ? G : DBX;
    const n = s === 'gdrive' ? 'Google Drive' : 'Dropbox';
    // state falso (firmato a caso), con un codice inventato
    let s0 = await stato(base);
    const falso = `copia-altrove.${s}.${'A'.repeat(24)}.${Math.floor(Date.now() / 1000) + 600}.${'B'.repeat(43)}`;
    await a.goto(B + RIT + '?state=' + encodeURIComponent(falso) + '&code=codice-inventato', { waitUntil: 'networkidle' });
    ok(n + ': state falso respinto ("non è partita da qui"), nessuno scambio del codice', /non è partita da qui/.test(await flashDi(a)) && (await stato(base)).scambi === s0.scambi);
    // state rubato: la pagina del servizio aperta da un'altra sessione del pannello (lo stesso amministratore, un altro browser)
    const urlAuth = await collega(a, s);
    const ladro = await pagina(br, err);
    await entraPannello(ladro, B, UTENTE, PASSWORD);
    await ladro.goto(urlAuth, { waitUntil: 'networkidle' });
    await Promise.all([ladro.waitForURL(/p=destinazione/), ladro.click('#consenti')]);
    ok(n + ': state rubato e usato da un\'altra sessione: respinto, il codice non si scambia', /non è partita da qui/.test(await flashDi(ladro)) && (await stato(base)).scambi === s0.scambi);
    await ladro.context().close();
    // il giro vero, poi lo stesso ritorno ripetuto
    let ritorno = '';
    a.on('request', q => { if (q.url().includes(RIT + '?')) ritorno = q.url(); });
    await Promise.all([a.waitForURL(/p=destinazione/), a.click('#consenti')]);
    ok(n + ': il giro vero dopo il tentativo del ladro riesce', new RegExp('Collegato a ' + n).test(await flashDi(a)) && (await stato(base)).scambi === s0.scambi + 1);
    await a.goto(ritorno, { waitUntil: 'networkidle' });
    ok(n + ': lo stesso ritorno ripetuto (state e codice già usati): respinto', /già stata usata|non è partita da qui/.test(await flashDi(a)) && (await stato(base)).scambi === s0.scambi + 1, ritorno.slice(0, 120));
    // state manomesso (servizio cambiato nello state di un giro aperto)
    const urlAuth2 = await collega(a, s);
    const st2 = new URL(urlAuth2).searchParams.get('state');
    const altroServ = st2.replace('.' + s + '.', '.' + (s === 'gdrive' ? 'dropbox' : 'gdrive') + '.');
    await a.goto(B + RIT + '?state=' + encodeURIComponent(altroServ) + '&code=x', { waitUntil: 'networkidle' });
    ok(n + ': state con il servizio cambiato: respinto', /non è partita da qui/.test(await flashDi(a)));
    const scaduto = st2.split('.'); scaduto[3] = String(Math.floor(Date.now() / 1000) - 10);
    await a.goto(B + RIT + '?state=' + encodeURIComponent(scaduto.join('.')) + '&code=x', { waitUntil: 'networkidle' });
    ok(n + ': state con la scadenza cambiata: respinto (la firma non torna)', /non è partita da qui|già stata usata/.test(await flashDi(a)));
    // rimandi: nessun parametro porta fuori dal pannello
    r = await soloAdmin.get(B + RIT + '?state=' + encodeURIComponent(falso) + '&code=x&redirect_uri=https%3A%2F%2Fcattivo.example%2F&torna=https%3A%2F%2Fcattivo.example%2F&next=%2F%2Fcattivo.example', { maxRedirects: 0 });
    ok(n + ': il ritorno rimanda solo dentro il pannello, mai a un indirizzo della richiesta', [302, 303].includes(r.status()) && !/cattivo/.test(r.headers().location || '') && /^addon-pagina\.php\?a=copia-altrove/.test(r.headers().location || ''), r.headers().location);
    r = await soloAdmin.get(B + PCA + '&autorizza=1&vai=https%3A%2F%2Fcattivo.example%2F');
    t = await r.text();
    ok(n + ': la pagina "Ti porto da..." non prende indirizzi dalla richiesta', !t.includes('cattivo.example'));
    r = await request.newContext({ extraHTTPHeaders: { Cookie: cookieAdmin, Host: 'cattivo.example' } }).then(c => c.get(B + PCA));
    t = await r.text();
    ok(n + ': con un Host falso il redirect URI mostrato resta quello del sito', !/cattivo\.example\/admin\/oauth-ritorno/.test(t));
    // token e segreto mai nella pagina, nel registro, nei log, nel database in chiaro
    const st = await stato(base);
    const pagine = [];
    for (const u of [PCA, '/admin/cron-log.php', '/admin/sicurezza.php', '/admin/index.php']) pagine.push(await (await g(soloAdmin, u)).text());
    const tutto = pagine.join('\n') + php("echo get_setting('copia_altrove_cfg');") + (fs.existsSync(path.join(DIR, 'logs', 'php-errori.log')) ? fs.readFileSync(path.join(DIR, 'logs', 'php-errori.log'), 'utf8') : '');
    ok(n + ': refresh token, access token e segreto dell\'app mai nella pagina, nei log e in chiaro nel database', !!st.ultimo_refresh && !tutto.includes(st.ultimo_refresh) && !tutto.includes(st.ultimo_accesso) && !tutto.includes(SEG[s]));
    // Scollega senza token, e dal membro
    r = await post(soloAdmin, PCA, { azione: 'scollega', servizio: s });
    ok(n + ': Scollega senza token CSRF: respinto, resta collegato', /"refresh":"v1:/.test(php("echo get_setting('copia_altrove_cfg');")) && (await stato(base)).refresh_revocati === st.refresh_revocati);
    r = await post(soloMembro, PCA, { azione: 'scollega', servizio: s });
    ok(n + ': il cookie del membro non scollega', r.status() === 302 && /login/.test(r.headers().location || '') && (await stato(base)).refresh_revocati === st.refresh_revocati);
    await a.goto(B + PCA, { waitUntil: 'networkidle' });
    await Promise.all([a.waitForNavigation(), a.click('#ca-scollega')]);
  }
  r = await g(fuori, RIT + '?state=copia-altrove.gdrive.x&code=y');
  ok('ritorno OAuth senza login: rimanda al login', r.status() === 302 && /login\.php/.test(r.headers().location || ''));
  r = await g(soloMembro, RIT + '?state=copia-altrove.gdrive.x&code=y');
  ok('ritorno OAuth con il cookie del membro: rimanda al login', r.status() === 302 && /login\.php/.test(r.headers().location || ''));
  for (const sx of ['membri.qualcosa', 'nonesiste.qualcosa', '', '../copia-altrove.x', 'Copia-Altrove.x']) {
    r = await g(soloAdmin, RIT + '?state=' + encodeURIComponent(sx) + '&code=y');
    ok('ritorno OAuth con uno state senza un addon che lo aspetta (' + JSON.stringify(sx) + '): 400', r.status() === 400, r.status());
  }
  fs.writeFileSync(CONFIG, conf0);

  // ---------------- 12. (2.1) Riservato dalla scheda: il redattore non lo cambia; feed e llms di un articolo riservato.
  const art = JSON.parse(sql("SELECT a.id, a.slug, a.title FROM {p}articles a WHERE a.status = 'published' AND a.cestino IS NULL ORDER BY a.id LIMIT 1"))[0];
  await vaiA('/admin/contenuto.php?tipo=articoli&id=' + art.id);
  await a.check('#membri-riservato');
  await Promise.all([a.waitForNavigation(), a.click('#sc-form button[data-valore=pubblica]')]);
  ok('preparazione: articolo riservato dalla scheda', JSON.parse(sql(`SELECT COUNT(*) AS n FROM {p}membri_riservati WHERE fonte = 'articles' AND contenuto_id = ${art.id}`))[0].n == 1);
  const extraXml = x => x.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
  for (const u of ['/feed/', '/llms.txt', '/llms-full.txt', '/sitemap.xml']) {
    t = await (await g(fuori, u)).text();
    ok('l\'articolo riservato (titolo e indirizzo) non esce da ' + u, !t.includes('/' + art.slug + '/') && !t.includes(art.title) && !t.includes(extraXml(art.title)));
  }
  t = await (await g(fuori, '/?s=' + encodeURIComponent(art.title))).text();
  ok('la ricerca per il titolo non trova l\'articolo riservato', !t.includes('/' + art.slug + '/"'));
  r = await g(fuori, '/' + art.slug + '/feed/');
  ok('il feed del singolo articolo riservato non risponde', r.status() === 404, r.status());
  // il redattore
  const RED = 'redattore-membri', REDPW = 'Ceramica-Forno-Membri-2026';
  await vaiA('/admin/utenti.php');
  if (!(await a.locator('text=' + RED).count())) {
    await a.fill('input[name=username]', RED); await a.fill('input[name=email]', RED + '@esempio.it');
    await a.selectOption('select[name=role]', 'editor'); await a.fill('input[name=password]', REDPW); await a.fill('input[name=password2]', REDPW);
    await Promise.all([a.waitForNavigation(), a.click('form:has(input[name=username]) button.btn-primary')]);
  }
  const red = await pagina(br, err);
  await entraPannello(red, B, RED, REDPW);
  const idSeg = JSON.parse(sql("SELECT id FROM {p}pages WHERE slug = 'segreta'"))[0].id;
  await red.goto(B + '/admin/contenuto.php?tipo=articoli&id=' + art.id, { waitUntil: 'networkidle' });
  ok('il redattore non vede il riquadro "Area riservata" nella scheda', await red.locator('#membri-scheda').count() === 0);
  // il redattore aggiunge a mano i campi del riquadro e salva: niente cambia
  await red.evaluate(() => { const f = document.getElementById('sc-form'); for (const [k, v] of [['membri_scheda', '1']]) { const i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = v; f.appendChild(i); } });
  await Promise.all([red.waitForNavigation(), red.click('#sc-form button[data-valore=pubblica]')]);
  ok('il redattore che manda i campi del riquadro (senza la spunta) non rende pubblico l\'articolo riservato', JSON.parse(sql(`SELECT COUNT(*) AS n FROM {p}membri_riservati WHERE fonte = 'articles' AND contenuto_id = ${art.id}`))[0].n == 1);
  await red.goto(B + '/admin/contenuto.php?tipo=pagine&id=' + idSeg, { waitUntil: 'networkidle' }).catch(() => {});
  const puo = await red.locator('#sc-form').count();
  if (puo) {
    await red.evaluate(() => { const f = document.getElementById('sc-form'); const i = document.createElement('input'); i.type = 'hidden'; i.name = 'membri_scheda'; i.value = '1'; f.appendChild(i); });
    await Promise.all([red.waitForNavigation(), red.click('#sc-form button[data-valore=pubblica]')]);
  }
  ok('il redattore non toglie la riservatezza alla pagina segreta', JSON.parse(sql(`SELECT COUNT(*) AS n FROM {p}membri_riservati WHERE fonte = 'pages' AND contenuto_id = ${idSeg}`))[0].n == 1);
  await red.context().close();
  await vaiA('/admin/contenuto.php?tipo=articoli&id=' + art.id);
  await a.uncheck('#membri-riservato');
  await Promise.all([a.waitForNavigation(), a.click('#sc-form button[data-valore=pubblica]')]);

  fine(err);
  await br.close();
})().catch(async e => { console.error(e); process.exitCode = 2; if (br) await br.close(); });
