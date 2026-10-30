// Attacchi agli addon Vetrina e Statistiche (2.0.0). Ogni prova è un attacco che deve essere respinto.
// Su un sito installato (kit Attività), con il server di posta finto acceso; la prova accende i due addon se sono spenti,
// crea i suoi prodotti ("Attacco ...") e un redattore. Il limite degli ordini è per indirizzo IP e per ora: va lanciata
// su un sito dove nell'ultima ora non sono stati fatti ordini (per esempio non subito dopo prova-addon-vetrina.js).
//   python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> &
// uso: node prove/attacchi-addon-c.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella del CMS installato>
const { chromium, request } = require('playwright');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { execFileSync } = require('child_process');
const [B, UTENTE, PASSWORD, SMTP, POSTA, CMS] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const posta = () => (fs.existsSync(POSTA) ? fs.readdirSync(POSTA) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => fs.readFileSync(path.join(POSTA, f), 'utf8'));
const dorme = ms => new Promise(r => setTimeout(r, ms));
const BROWSER = { 'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36 Attacchi', 'Accept': 'text/html,*/*;q=0.8', 'Accept-Language': 'it-IT,it;q=0.9' };
const dump = () => JSON.parse(execFileSync('php', [path.join(__dirname, 'statistiche-cli.php'), CMS, 'dump']).toString());

// Il captcha dei moduli risolto qui (la stessa prova di lavoro che fa il browser).
async function captcha(rq) {
  const s = await (await rq.get(B + '/api/captcha.php')).json();
  for (let i = 0; i <= s.max; i++) if (crypto.createHash('sha256').update(s.sale + i).digest('hex') === s.sfida) return Buffer.from(JSON.stringify({ ...s, n: i })).toString('base64');
  throw new Error('captcha senza soluzione');
}
const ordineBase = { nome: 'Cliente Prova', email: 'cliente@prova.it', telefono: '+39 333 1112223', indirizzo: 'Via Roma 1', cap: '40100', citta: 'Bologna', consegna: 'spedizione', metodo: 'bonifico', consenso: '1', note: '', modulo: 'vetrina-ordine', sito_web: '' };

(async () => {
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 }, userAgent: BROWSER['User-Agent'] });
  const p = await ctx.newPage();
  const dialoghi = []; p.on('dialog', d => { if (/^attacco/.test(d.message()) || d.type() === 'alert') dialoghi.push(d.message()); d.accept(); });
  const vai = u => p.goto(B + u, { waitUntil: 'networkidle' });
  const invia = sel => Promise.all([p.waitForNavigation(), p.click(sel)]);
  const flash = async () => (await p.locator('.flash').first().innerText().catch(() => '')).trim();
  await vai('/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD); await invia('button[type=submit]');
  // Posta finta, addon accesi.
  await vai('/admin/impostazioni.php');
  await p.fill('#smtp_host', '127.0.0.1'); await p.fill('#smtp_porta', SMTP); await p.selectOption('#smtp_sicurezza', 'nessuna');
  await p.fill('[name=notification_email]', 'avvisi-sito@esempio.it');
  await invia('form:has(input[value=save_posta]) button[type=submit]');
  for (const a of ['vetrina', 'statistiche']) {
    await vai('/admin/addon.php');
    if (/Spento/.test(await p.locator(`[data-addon=${a}] .badge`).textContent())) await invia(`[data-addon=${a}] button`);
  }
  // Negozio: bonifico e alla consegna, avvisi al negozio.
  await vai('/admin/addon-pagina.php?a=vetrina&p=impostazioni');
  await p.fill('#vt-iban', 'IT60X0542811101000000123456'); await p.check('input[name=bonifico]'); await p.check('input[name=consegna_pag]');
  await p.fill('#vt-email_avvisi', 'negozio@esempio.it'); await invia('#vt-salva');
  const csrf = async () => p.locator('input[name=_csrf]').first().inputValue();

  // I prodotti dell'attacco.
  const prodotto = async (nome, prezzo, extra = async () => {}) => {
    await vai('/admin/addon-pagina.php?a=vetrina&p=prodotto');
    await p.fill('#vt-nome', nome); await p.fill('#vt-prezzo', prezzo); await extra();
    await invia('#vt-salva');
    return +new URL(p.url()).searchParams.get('id');
  };
  const idA = await prodotto('Attacco A', '10,00');
  const idB = await prodotto('Attacco B', '20,00', async () => { await p.fill('#vt-opz1', 'Misura'); await p.fill('input[name="var[0][valore1]"]', 'X'); await p.fill('input[name="var[0][scorta]"]', '2'); await p.fill('input[name="var[1][valore1]"]', 'Y'); });
  const idE = await prodotto('Attacco esaurito', '5,00', async () => p.check('input[name=esaurito]'));
  const idXss = await prodotto('<b onmouseover="alert(\'attacco nome\')">Attacco XSS</b>', '3,00');
  const slugB = 'attacco-b';
  const pagB = await (await p.request.get(B + '/negozio/' + slugB + '/')).text();
  const variantiB = [...pagB.matchAll(/<option value="(\d+)"[^>]*>([XY]) /g)].map(m => [+m[1], m[2]]);
  const vX = variantiB.find(v => v[1] === 'X')[0];
  ok('preparazione: quattro prodotti, B con due varianti', idA && idB && idE && idXss && variantiB.length === 2, { idA, idB, idE, idXss, variantiB });

  // Un cliente con il suo barattolo di cookie.
  const cliente = async () => request.newContext({ extraHTTPHeaders: BROWSER });
  const carrello = async (rq, dati) => (await rq.post(B + '/negozio/carrello/', { form: { azione: 'aggiungi', variante: '0', qta: '1', ...dati } })).text();
  const totale = html => ((html.match(/data-vt-totale>([^<]+)</) || [])[1] || '').replace(/&nbsp;|\s/g, ' ').trim();

  // 1. Prezzo manomesso nel carrello e nell'ordine.
  let c1 = await cliente();
  let h = await carrello(c1, { prodotto: String(idA), prezzo: '0.01', prezzo_cent: '1', totale: '1' });
  ok('prezzo mandato nel carrello: ignorato, vale quello del negozio (10 + 7 di spedizione)', /17,00/.test(totale(h)), totale(h));
  const ck = (await c1.storageState()).cookies.find(x => x.name === 'vetrina_carrello');
  const [dati, firma] = ck.value.split('.');
  const finto = Buffer.from(JSON.stringify([[idA, 0, 50], [idE, 0, 1]])).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '') + '.' + firma;
  const c1b = await request.newContext({ extraHTTPHeaders: { ...BROWSER, Cookie: 'vetrina_carrello=' + finto } });
  h = await (await c1b.get(B + '/negozio/carrello/')).text();
  ok('cookie del carrello cambiato a mano (firma vecchia): carrello vuoto', /Il carrello è vuoto/.test(h));
  const c1c = await request.newContext({ extraHTTPHeaders: { ...BROWSER, Cookie: 'vetrina_carrello=' + dati + '.' + '0'.repeat(64) } });
  ok('cookie con una firma inventata: carrello vuoto', /Il carrello è vuoto/.test(await (await c1c.get(B + '/negozio/carrello/')).text()));

  // 2. Quantità negative, zero, enormi, non intere.
  for (const q of ['-5', '0', '1000000', '2.5', '1e3', 'abc', '99999999999999999999']) {
    const c = await cliente();
    const r = await carrello(c, { prodotto: String(idA), qta: q });
    ok(`quantità ${q}: rifiutata, carrello vuoto`, !(await c.storageState()).cookies.some(x => x.name === 'vetrina_carrello') && /modulo-errore/.test(r), q);
  }
  h = await (await c1.post(B + '/negozio/carrello/', { form: { azione: 'aggiorna', 'qta[0]': '-3' } })).text();
  ok('aggiorna con quantità -3: la riga si toglie (mai quantità negative)', /Il carrello è vuoto/.test(h));
  await carrello(c1, { prodotto: String(idA) });
  h = await (await c1.post(B + '/negozio/carrello/', { form: { azione: 'aggiorna', 'qta[0]': '500000' } })).text();
  ok('aggiorna con quantità 500000: si ferma a 99', /value="99"/.test(h) && /990,00/.test(h));
  h = await (await c1.post(B + '/negozio/carrello/', { form: { azione: 'aggiorna', 'qta[0]': '1', 'qta[x]': '5', 'qta[7]': '9' } })).text();
  ok('aggiorna con righe che non esistono: ignorate', (h.match(/class="vt-riga[ "]/g) || []).length === 1 && /value="1"/.test(h));

  // 3. Variante di un altro prodotto, prodotto con varianti senza variante, prodotti che non esistono.
  let c2 = await cliente();
  h = await carrello(c2, { prodotto: String(idA), variante: String(vX) });
  ok('variante di un altro prodotto: rifiutata', /non è di questo prodotto/.test(h) && !(await c2.storageState()).cookies.length);
  h = await carrello(c2, { prodotto: String(idB), variante: '0' });
  ok('prodotto con varianti senza variante: rifiutato', /Scegli misura/.test(h));
  h = await carrello(c2, { prodotto: '999999' });
  ok('prodotto che non esiste: rifiutato', /non c(.|&apos;|&#0?39;)è più/.test(h));
  h = await carrello(c2, { prodotto: String(idE) });
  ok('prodotto esaurito: non entra nel carrello', /esaurito/.test(h) && !(await c2.storageState()).cookies.length);

  // 4. L'ultimo pezzo venduto due volte (due carrelli, scorte 2): il secondo ordine si rifiuta.
  const ordina = async (rq, extra = {}) => {
    const r = await rq.post(B + '/negozio/ordine/', { form: { ...ordineBase, captcha: await captcha(rq), ...extra } });
    return { stato: r.status(), url: r.url(), html: await r.text() };
  };
  const cx1 = await cliente(), cx2 = await cliente();
  await carrello(cx1, { prodotto: String(idB), variante: String(vX), qta: '2' });
  await carrello(cx2, { prodotto: String(idB), variante: String(vX), qta: '1' });
  const o1 = await ordina(cx1);
  const o2 = await ordina(cx2);
  ok('scorte 2: il primo ordine (2 pezzi) passa, il secondo (1 pezzo) si rifiuta', /ordine\/fatto/.test(o1.url) && o2.stato === 422 && /appena finito|non è più disponibile/.test(o2.html), [o1.url, o2.stato]);
  ok('il prezzo dell\'ordine è quello del negozio anche con un totale mandato nel modulo', /40,00/.test(o1.html) || /47,00/.test(o1.html));

  // 5. XSS nel nome e nelle note, header injection, consenso e captcha mancanti.
  const cx3 = await cliente(); await carrello(cx3, { prodotto: String(idA) });
  const q0 = posta().length;
  const o3 = await ordina(cx3, { nome: '<script>alert("attacco nome")</script>Mario', note: '<img src=x onerror=alert("attacco note")> ciao', email: 'mario@prova.it' });
  ok('ordine con HTML nel nome e nelle note: accettato come testo', /ordine\/fatto/.test(o3.url) && !/<script>alert/.test(o3.html));
  for (let i = 0; i < 30 && posta().length < q0 + 2; i++) await dorme(250);
  const m3 = posta().slice(q0).join('\n');
  ok('le email dell\'ordine hanno l\'HTML del cliente scritto come testo', m3.length > 0 && !/<script>alert/.test(m3) && !/<img src=3Dx|<img src=x/.test(m3), m3.length);
  const cx4 = await cliente(); await carrello(cx4, { prodotto: String(idA) });
  const o4 = await ordina(cx4, { email: 'mario@prova.it\r\nBcc: spia@cattivo.it' });
  ok('email con un a capo e un Bcc: rifiutata', o4.stato === 422 && /indirizzo email/.test(o4.html));
  const q1 = posta().length;
  const o5 = await ordina(cx4, { nome: 'Mario\r\nBcc: spia@cattivo.it\r\nSubject: finto' });
  for (let i = 0; i < 30 && posta().length < q1 + 2; i++) await dorme(250);
  const m5 = posta().slice(q1);
  ok('nome con a capo e intestazioni: nessun Bcc e nessuna intestazione in più nelle email', /ordine\/fatto/.test(o5.url) && m5.length >= 1 && m5.every(x => !/spia@cattivo/.test(x.split('\r\n\r\n')[0]) && !/^Bcc:/mi.test(x) && (x.match(/^Subject:/gmi) || []).length === 1), m5.map(x => x.split('\r\n')[0]));
  const cx5 = await cliente(); await carrello(cx5, { prodotto: String(idA) });
  const o6 = await ordina(cx5, { consenso: '' });
  ok('senza consenso: rifiutato', o6.stato === 422 && /condizioni di vendita/.test(o6.html));
  const r7 = await cx5.post(B + '/negozio/ordine/', { form: { ...ordineBase, captcha: '' } });
  ok('senza captcha: rifiutato', r7.status() === 422 && /Sono una persona/.test(await r7.text()));
  // Lo stesso captcha due volte: la prima va (ordine fatto), la seconda, con il carrello di nuovo pieno, no.
  const cap = await captcha(cx5);
  const r8a = await cx5.post(B + '/negozio/ordine/', { form: { ...ordineBase, captcha: cap } });
  await carrello(cx5, { prodotto: String(idA) });
  const r8 = await cx5.post(B + '/negozio/ordine/', { form: { ...ordineBase, captcha: cap } });
  ok('captcha usato una volta non vale una seconda', /ordine\/fatto/.test(r8a.url()) && r8.status() === 422 && /Sono una persona/.test(await r8.text()), r8.status());
  const r9 = await cx5.post(B + '/negozio/ordine/', { form: { ...ordineBase, captcha: await captcha(cx5), sito_web: 'http://spam' } });
  ok('campo esca compilato (un programma): rifiutato', r9.status() !== 200 || !/ordine\/fatto/.test(r9.url()));
  await carrello(cx4, { prodotto: String(idA) });
  const r10 = await cx4.post(B + '/negozio/ordine/', { form: { ...ordineBase, metodo: 'link', captcha: await captcha(cx4) } });
  ok('metodo di pagamento non acceso (link): rifiutato', r10.status() === 422 && /come vuoi pagare/.test(await r10.text()));

  // 6. Troppe richieste dallo stesso indirizzo: al massimo 5 ordini in un'ora.
  let fatti = 4, bloccato = false; // finora: o1, o3, o5, r8a
  for (let i = 0; i < 4 && !bloccato; i++) {
    const c = await cliente(); await carrello(c, { prodotto: String(idA) });
    const o = await ordina(c);
    if (/ordine\/fatto/.test(o.url)) fatti++; else bloccato = /diverse richieste/.test(o.html);
  }
  ok('troppe richieste: dopo 5 ordini in un\'ora dallo stesso IP il sesto si rifiuta', bloccato && fatti === 5, { fatti, bloccato });

  // 7. Il link di pagamento: solo https vero.
  for (const l of ['javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html,<script>alert(1)</script>', 'http://paypal.me/x', 'https://paypal.me/x" onmouseover="alert(1)', 'https://user:pass@paypal.me/x', 'https://', ' javascript:alert(1)']) {
    await vai('/admin/addon-pagina.php?a=vetrina&p=impostazioni');
    // Senza il controllo del browser sul campo (type=url): l'attacco arriva al server così com'è.
    await p.evaluate(() => { document.getElementById('vt-impostazioni').noValidate = true; });
    await p.check('input[name=link]'); await p.fill('#vt-link_url', l); await invia('#vt-salva');
    ok(`link di pagamento ${JSON.stringify(l)}: rifiutato`, /deve cominciare con https/.test(await p.locator('#vt-errori').innerText().catch(() => '')));
  }
  await vai('/admin/addon-pagina.php?a=vetrina&p=impostazioni');
  ok('dopo i tentativi il link di pagamento non è acceso', !(await p.isChecked('input[name=link]')) || await p.inputValue('#vt-link_url') === '');
  // Header injection nell'indirizzo degli avvisi.
  await p.evaluate(() => { document.getElementById('vt-impostazioni').noValidate = true; });
  await p.fill('#vt-email_avvisi', 'negozio@esempio.it\nBcc: spia@cattivo.it'); await invia('#vt-salva');
  ok('indirizzo degli avvisi con un a capo: rifiutato', /non è un.email valida/.test(await p.locator('#vt-errori').innerText().catch(() => '')));

  // 8. CSRF: il pannello senza token, o con il token ma da un altro sito; il negozio da un altro sito.
  const token = await csrf();
  const senza = [
    ['addon-pagina.php?a=vetrina&p=ordini', { azione: 'stato', id: '1', stato: 'annullato' }],
    ['addon-pagina.php?a=vetrina&p=prodotti', { azione: 'elimina', id: String(idA) }],
    ['addon-pagina.php?a=vetrina&p=categorie', { azione: 'salva', id: '0', nome: 'Categoria CSRF' }],
    ['addon-pagina.php?a=vetrina&p=impostazioni', { iban: 'IT60X0542811101000000654321', bonifico: '1' }],
    ['addon-pagina.php?a=vetrina&p=prodotto&id=' + idA, { nome: 'Rubato', prezzo: '0,01' }],
    ['addon-pagina.php?a=statistiche&p=impostazioni', { giorni: '90', dnt: '1' }],
    ['addon.php', { addon: 'statistiche', azione: 'spegni' }],
  ];
  for (const [u, f] of senza) {
    const r = await p.request.post(B + '/admin/' + u, { form: f, maxRedirects: 0 });
    const r2 = await p.request.post(B + '/admin/' + u, { form: { ...f, _csrf: token }, headers: { Origin: 'https://cattivo.example' }, maxRedirects: 0 });
    ok(`CSRF ${u.replace('addon-pagina.php?', '')}: senza token 403, con token ma da un altro sito 403`, r.status() === 403 && r2.status() === 403, [r.status(), r2.status()]);
  }
  await vai('/admin/addon-pagina.php?a=vetrina&p=prodotti');
  ok('dopo i CSRF il prodotto A c\'è ancora con il suo prezzo', /10,00/.test(await p.locator('#vt-prodotti tr[data-slug="attacco-a"]').innerText()));
  const cEst = await request.newContext({ extraHTTPHeaders: { ...BROWSER, Origin: 'https://cattivo.example' } });
  const rEst = await cEst.post(B + '/negozio/carrello/', { form: { azione: 'aggiungi', prodotto: String(idA), variante: '0', qta: '1' } });
  ok('negozio: un POST da un altro sito si rifiuta (403) e non crea il carrello', rEst.status() === 403 && !(await cEst.storageState()).cookies.length);

  // 9. XSS nel pannello: l'ordine con l'HTML e il prodotto con l'HTML nel nome.
  await vai('/admin/addon-pagina.php?a=vetrina&p=ordini');
  ok('elenco ordini: il nome con <script> è testo, nessuno script eseguito', await p.locator('#vt-ordini script').count() === 0 && /<script>alert/.test(await p.locator('#vt-ordini').innerText()));
  await p.locator('#vt-ordini tr', { hasText: 'attacco nome' }).first().locator('a').click();
  await p.waitForLoadState('networkidle');
  await p.hover('.vt-a-note').catch(() => {});
  ok('scheda ordine: note con <img onerror> mostrate come testo', await p.locator('#vt-ordine img').count() === 0 && /onerror/.test(await p.locator('.vt-a-note').innerText()));
  const cXss = await br.newPage({ userAgent: BROWSER['User-Agent'] });
  cXss.on('dialog', d => { dialoghi.push(d.message()); d.dismiss(); });
  await cXss.goto(B + '/negozio/', { waitUntil: 'networkidle' });
  await cXss.hover('.vt-scheda:has-text("Attacco XSS")').catch(() => {});
  ok('negozio: il nome del prodotto con HTML esce come testo', await cXss.locator('b[onmouseover]').count() === 0 && /<b onmouseover/.test(await cXss.locator('.vt-griglia').innerText()));

  // 10. SQL injection nei filtri del negozio e del pannello.
  const iniezioni = ["' OR 1=1 -- ", '1 UNION SELECT password_hash FROM cms_users', "x'); DROP TABLE cms_vetrina_ordini; --", '%27%20OR%20%271%27=%271'];
  for (const s of iniezioni) {
    const r = await p.request.get(B + '/negozio/?categoria=' + encodeURIComponent(s));
    const r2 = await p.request.get(B + '/negozio/?ordina=' + encodeURIComponent(s));
    const r3 = await p.request.get(B + '/admin/addon-pagina.php?a=vetrina&p=ordini&stato=' + encodeURIComponent(s) + '&q=' + encodeURIComponent(s));
    const r4 = await p.request.get(B + '/admin/addon-pagina.php?a=vetrina&p=prodotti&q=' + encodeURIComponent(s));
    const r5 = await p.request.get(B + '/admin/addon-pagina.php?a=statistiche&p=visite&periodo=personalizzato&dal=' + encodeURIComponent(s) + '&al=' + encodeURIComponent(s));
    const t3 = await r3.text(), t5 = await r5.text();
    ok(`SQL ${JSON.stringify(s).slice(0, 30)}: categoria 404, ordina ignorato, ricerche senza risultati né errori`, r.status() === 404 && r2.status() === 200 && r3.status() === 200 && /Nessun ordine con questi filtri/.test(t3) && r4.status() === 200 && /Nessun prodotto con questo nome/.test(await r4.text()) && r5.status() === 200 && !/SQLSTATE|Fatal/.test(t3 + t5), [r.status(), r2.status(), r3.status(), r4.status(), r5.status()]);
  }
  ok('/negozio/<slug con apici>/ dà 404', (await p.request.get(B + "/negozio/x'%20OR%201=1/")).status() === 404);
  await vai('/admin/addon-pagina.php?a=vetrina&p=ordini');
  ok('dopo le iniezioni gli ordini ci sono ancora', await p.locator('#vt-ordini tbody tr').count() >= 5);

  // 11. La pagina "ordine ricevuto" non si apre senza la firma giusta.
  const fattoUrl = o1.url;
  const numero = new URL(fattoUrl).searchParams.get('n');
  for (const [nome, u] of [['firma vuota', `/negozio/ordine/fatto/?n=${numero}&k=`], ['firma sbagliata', `/negozio/ordine/fatto/?n=${numero}&k=${'a'.repeat(32)}`], ['numero di un altro ordine', fattoUrl.replace(/n=[^&]+/, 'n=' + numero.replace(/\d$/, d => String((+d + 1) % 10))).replace(B, '')], ['numero con SQL', `/negozio/ordine/fatto/?n=${encodeURIComponent("' OR 1=1 --")}&k=x`]]) {
    ok(`pagina dell'ordine con ${nome}: 404`, (await (await cliente()).get(B + u)).status() === 404);
  }

  // 12. Statistiche: provenienza e utm con HTML o lunghissimi.
  const visita = async (u, h = {}) => fetch(B + u, { headers: { ...BROWSER, ...h } }).then(r => r.text());
  await visita('/?utm_source=' + encodeURIComponent('<script>alert("attacco utm")</script>') + '&utm_medium=' + encodeURIComponent('"><img src=x onerror=alert(1)>') + '&utm_campaign=' + 'A'.repeat(5000), { 'User-Agent': BROWSER['User-Agent'] + ' utm', Referer: 'https://cattivo.example/"><script>alert(1)</script>' });
  await visita('/servizi/', { 'User-Agent': BROWSER['User-Agent'] + ' lungo', Referer: 'https://' + 'a'.repeat(250) + '.example/x' });
  await visita('/contatti/', { 'User-Agent': BROWSER['User-Agent'] + ' finto', Referer: 'javascript:alert(1)' });
  await visita('/' + '%3Cscript%3E', { 'User-Agent': BROWSER['User-Agent'] + ' 404' }); // 404: non si conta
  await dorme(300);
  const d = dump();
  const testo = JSON.stringify(d.stat_visite);
  ok('statistiche: nessun < o > salvato (utm e provenienze ripuliti)', !/[<>]/.test(testo) && d.stat_visite.some(r => r.fonte === 'cattivo.example'));
  ok('statistiche: utm al massimo 80 caratteri, provenienza lunghissima scartata', d.stat_visite.every(r => r.utm_campaign.length <= 80 && r.utm_source.length <= 80 && r.fonte.length <= 100) && !d.stat_visite.some(r => /aaaaaaaaaa/.test(r.fonte)));
  ok('statistiche: provenienza javascript: scartata', !d.stat_visite.some(r => /javascript/i.test(r.fonte)));
  ok('statistiche: una pagina 404 con HTML nel percorso non si conta', !d.stat_visite.some(r => /script/i.test(r.pagina)));
  await vai('/admin/addon-pagina.php?a=statistiche&p=visite&periodo=7');
  ok('pannello statistiche: nessuno script o immagine dai dati delle visite', await p.locator('.st-tabella script, .st-tabella img').count() === 0);

  // 13. Le pagine del pannello senza login e da redattore; i file degli addon dal web.
  const pagine = ['vetrina&p=ordini', 'vetrina&p=prodotti', 'vetrina&p=prodotto', 'vetrina&p=categorie', 'vetrina&p=impostazioni', 'statistiche&p=visite', 'statistiche&p=esporta', 'statistiche&p=impostazioni'];
  const anon = await request.newContext({ extraHTTPHeaders: BROWSER });
  for (const pg of pagine) {
    const r = await anon.get(B + '/admin/addon-pagina.php?a=' + pg, { maxRedirects: 0 });
    ok(`senza login ${pg}: rimandato al login, niente contenuto`, r.status() === 302 && /login\.php/.test(r.headers()['location'] || '') && !/vt-|st-|giorno;pagina/.test(await r.text()), r.status());
  }
  // Un redattore.
  await vai('/admin/utenti.php');
  if (!(await p.locator('text=redattore-addon').count())) {
    await p.fill('input[name=username]', 'redattore-addon'); await p.fill('input[name=email]', 'redattore-addon@esempio.it');
    await p.selectOption('select[name=role]', 'editor'); await p.fill('input[name=password]', 'Ceramica-Forno-Bologna-77'); await p.fill('input[name=password2]', 'Ceramica-Forno-Bologna-77');
    await invia('form:has(input[name=username]) button.btn-primary');
    ok('redattore creato', /Utente creato/.test(await flash()), await flash());
  }
  const rctx = await br.newContext({ userAgent: BROWSER['User-Agent'] });
  const rp = await rctx.newPage();
  await rp.goto(B + '/admin/login.php'); await rp.fill('#username', 'redattore-addon'); await rp.fill('#password', 'Ceramica-Forno-Bologna-77');
  await Promise.all([rp.waitForNavigation(), rp.click('button[type=submit]')]);
  ok('il redattore entra nel pannello e non vede le voci Vetrina e Statistiche', /admin\/(index\.php)?$/.test(rp.url()) && await rp.locator('.admin-nav a', { hasText: 'Vetrina' }).count() === 0 && await rp.locator('.admin-nav a', { hasText: 'Statistiche' }).count() === 0, rp.url());
  for (const pg of pagine) {
    const r = await rp.request.get(B + '/admin/addon-pagina.php?a=' + pg, { maxRedirects: 0 });
    ok(`redattore ${pg}: rimandato alla dashboard, niente contenuto`, r.status() === 302 && /index\.php/.test(r.headers()['location'] || '') && !/giorno;pagina/.test(await r.text()), r.status());
  }
  await rp.goto(B + '/admin/account.php', { waitUntil: 'networkidle' });
  const rTok = await rp.locator('input[name=_csrf]').first().inputValue({ timeout: 5000 }).catch(() => '');
  const rPost = await rp.request.post(B + '/admin/addon-pagina.php?a=vetrina&p=ordini', { form: { _csrf: rTok, azione: 'stato', id: '1', stato: 'annullato' }, maxRedirects: 0 });
  ok('redattore: un POST con il suo token a Ordini si rifiuta (403 o rimando alla dashboard)', rPost.status() === 403 || (rPost.status() === 302 && /index\.php/.test(rPost.headers()['location'] || '')), [rPost.status(), rPost.headers()['location'], rTok.length]);
  await vai('/admin/addon-pagina.php?a=vetrina&p=ordini&id=1');
  ok('l\'ordine 1 non è stato annullato né dai CSRF né dal redattore', !/annullato/i.test(await p.locator('#vt-ordine .badge').first().textContent()));
  for (const f of ['/addon/vetrina/modulo.php', '/addon/vetrina/includes/vetrina.php', '/addon/statistiche/includes/statistiche.php', '/addon/statistiche/modulo.php', '/moduli/addon-attivi.json']) {
    ok(`dal web ${f}: 403`, (await anon.get(B + f)).status() === 403);
  }
  ok('i fogli e gli script degli addon invece si servono (200)', (await anon.get(B + '/addon/vetrina/assets/vetrina.css')).status() === 200 && (await anon.get(B + '/addon/vetrina/assets/vetrina.js')).status() === 200);
  ok('nessuna finestra di alert aperta dagli attacchi', dialoghi.length === 0, dialoghi);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
