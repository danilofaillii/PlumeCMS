// 2.2: attacchi a "Scegli dal sito" (admin/link-api.php, includes/collegamenti.php, assets/scegli-link.js).
// Senza login, senza token, con un token sbagliato, da un altro sito, con GET; ricerche cattive (SQL, jolly di LIKE, HTML,
// caratteri di controllo, emoji, 100.000 caratteri, liste al posto del testo); un titolo con <script> e <img onerror> che
// nella finestra resta testo; un link javascript: (e le sue varianti) incollato a mano nella casella di un widget e del
// popup: sul sito non diventa mai un link che esegue codice (editor_link_sicuro). Il redattore non vede le bozze degli addon.
// Prepara e pulisce da solo (prove/scegli-link-cli.php). Serve un sito con il kit Attività e gli addon Eventi accesi
// (li accende prova-scegli-link.js; qui si accende Eventi se è spento).
// uso: CMS_DIR=<cartella del CMS installato> node prove/attacchi-scegli-link.js <base> <utente> <password>
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello'); // UX 2026-11: conferme del pannello
const path = require('path');
const { execFileSync } = require('child_process');
const [B, UTENTE, PASSWORD] = process.argv.slice(2);
const DIR = process.env.CMS_DIR;
if (!B || !DIR) { console.error('uso: CMS_DIR=<cartella> node prove/attacchi-scegli-link.js <base> <utente> <password>'); process.exit(2); }
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const dorme = (ms) => new Promise(r => setTimeout(r, ms));
const cli = (...a) => { const o = execFileSync('php', [path.join(__dirname, 'scegli-link-cli.php'), DIR, ...a], { encoding: 'utf8' }).trim(); try { return JSON.parse(o); } catch (e) { return o; } };
const API = B + '/admin/link-api.php';
const D = 'dialog.pn-link';

(async () => {
  cli('pulisci');
  const dati = cli('prepara');
  const br = await chromium.launch();

  // 1. Senza login: 401 in JSON, nessuna voce.
  {
    const anon = await br.newContext();
    const r = await anon.request.post(API, { form: { q: 'Contatti', _csrf: 'x' } });
    const t = await r.text();
    ok('senza login: 401, nessuna voce', r.status() === 401 && !t.includes('voci') && !t.includes('contatti'), { stato: r.status(), t: t.slice(0, 120) });
    const g = await anon.request.get(API + '?q=Contatti');
    ok('senza login, con GET: niente', [401, 403].includes(g.status()) && !(await g.text()).includes('voci'), g.status());
    await anon.close();
  }

  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 160))); pnConferme.on(p, d => d.accept());
  const vai = async (u) => { await p.goto(B + '/admin/' + u, { waitUntil: 'networkidle' }); };
  await vai('login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await vai('addon.php');
  const ev = p.locator('[data-addon="eventi-iscrizione"] button[value=accendi]');
  if (await ev.count()) await Promise.all([p.waitForNavigation(), ev.click()]);
  cli('addon');
  await vai('index.php');
  const token = await p.$eval('meta[name=pn-csrf]', m => m.content);

  // 2. Con il login ma senza le regole: GET, senza token, token sbagliato, da un altro sito.
  {
    const g = await p.request.get(API + '?q=Contatti');
    ok('con login, GET: 403', g.status() === 403 && !(await g.text()).includes('voci'), g.status());
    const s = await p.request.post(API, { form: { q: 'Contatti' } });
    ok('con login, senza token: 403', s.status() === 403 && !(await s.text()).includes('voci'), s.status());
    const w = await p.request.post(API, { form: { q: 'Contatti', _csrf: 'a'.repeat(64) } });
    ok('con login, token sbagliato: 403', w.status() === 403, w.status());
    const o = await p.request.post(API, { form: { q: 'Contatti', _csrf: token }, headers: { Origin: 'https://sito-cattivo.example', Referer: 'https://sito-cattivo.example/' } });
    ok('con login e token giusto, ma da un altro sito (Origin): 403', o.status() === 403, o.status());
    const buona = await p.request.post(API, { form: { q: 'Contatti', _csrf: token } });
    ok('la stessa richiesta, fatta bene, risponde', buona.status() === 200 && (await buona.json()).voci.some(v => v.percorso === '/contatti/'), buona.status());
  }

  // 3. Ricerche cattive: sempre 200 in JSON, nessun errore SQL, nessuna voce inventata.
  const cerca = (q, cosa = '') => p.evaluate(async ([q, cosa, token]) => {
    const fd = new FormData(); fd.append('_csrf', token);
    if (Array.isArray(q)) q.forEach(x => fd.append('q[]', x)); else fd.append('q', q);
    if (Array.isArray(cosa)) cosa.forEach(x => fd.append('cosa[]', x)); else fd.append('cosa', cosa);
    const t0 = performance.now();
    const r = await fetch('link-api.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const testo = await r.text();
    let j = null; try { j = JSON.parse(testo); } catch (e) {}
    return { stato: r.status, tipo: r.headers.get('content-type'), testo: testo.slice(0, 400), j, ms: Math.round(performance.now() - t0) };
  }, [q, cosa, token]);
  const pulita = (r) => r.stato === 200 && /application\/json/.test(r.tipo || '') && r.j && Array.isArray(r.j.voci) && !/SQLSTATE|PDOException|Fatal|Warning|<br/.test(r.testo);
  for (const [nome, q, vuota] of [
    ['SQL: apice e OR 1=1', "' OR 1=1 -- ", true],
    ['SQL: UNION', "x' UNION SELECT username, password_hash, 1, 1, 1, 1 FROM cms_users -- ", true],
    ['SQL: barra rovesciata', '\\', true],
    ['LIKE: il jolly % vale come carattere', '%', '%'],
    ['LIKE: il jolly _ vale come carattere (trova solo i titoli con _)', '_', '_'],
    ['HTML: <script>', '<script>alert(1)</script>', true],
    ['caratteri di controllo e NUL', 'Con\u0000\u0007tatti', false],
    ['testo da destra a sinistra (U+202E)', '‮ittatnoC', true],
    ['emoji a 4 byte (si tolgono: trova Contatti, e gli addon rispondono)', 'Contatti \u{1F600}', false],
    ['UTF-8 rotto', 'Con\udc80tatti', true],
    ['100.000 caratteri', 'x'.repeat(100000), true],
  ]) {
    const r = await cerca(q);
    // vuota: true, nessuna voce; una stringa: solo voci con quel carattere nel titolo o nel percorso (il jolly vale come carattere).
    const giuste = vuota === true ? r.j && r.j.voci.length === 0 : (typeof vuota === 'string' ? r.j && r.j.voci.every(v => v.titolo.includes(vuota) || v.percorso.includes(vuota)) : true);
    ok(`ricerca cattiva (${nome}): 200, JSON, nessun errore` + (vuota === true ? ', nessuna voce' : ''), pulita(r) && giuste && r.ms < 5000, { stato: r.stato, voci: r.j && r.j.voci.length, ms: r.ms, t: r.testo.slice(0, 120) });
  }
  {
    const e = await cerca('Contatti \u{1F600}');
    ok('emoji: si tolgono dalla ricerca, Contatti si trova e i tipi degli addon restano', pulita(e) && e.j.voci.some(v => v.percorso === '/contatti/') && !!e.j.tipi.evento, e.j && e.j.tipi);
    const r = await cerca(['Contatti', 'x']);
    ok('ricerca come lista (q[]): niente errore, vale come ricerca vuota', pulita(r), r.stato);
    const t = await cerca('', "pagina' OR '1'='1");
    ok('tipo cattivo: niente errore, nessuna voce', pulita(t) && t.j.voci.length === 0, { stato: t.stato, voci: t.j && t.j.voci.length });
    const l = await cerca('', ['pagina']);
    ok('tipo come lista: niente errore', pulita(l), l.stato);
    const x = await cerca('Cattivo');
    ok('il titolo con <script> arriva nel JSON con < e > scritti come \\u003C (mai come tag)', pulita(x) && x.j.voci.some(v => v.titolo.includes('<script>')) && !x.testo.includes('<script') && !x.testo.includes('<img'), x.testo.slice(0, 200));
  }

  // 4. Il titolo cattivo nella finestra: testo, niente script.
  await vai('popup.php?nuovo=1');
  {
    await p.locator('.pn-link-campo:has(#pp-link) > .pn-link-scegli').click();
    await p.waitForSelector(D + '[open]');
    await p.fill(D + ' input[type=search]', 'Cattivo'); await dorme(600);
    await p.waitForFunction((d) => !document.querySelector(d + ' .pn-link-lista').hasAttribute('aria-busy'), D);
    const nome = await p.locator(D + ' .pn-link-nome').first().innerText().catch(() => '');
    ok('finestra: il titolo cattivo si legge come testo', nome.includes('<script>') && nome.includes('onerror'), nome);
    ok('finestra: nessuno script partito, nessuna immagine creata dal titolo', await p.evaluate(() => window.__slXss === undefined) && await p.locator(D + ' .pn-link-lista img, ' + D + ' .pn-link-lista script').count() === 0);
    await p.keyboard.press('Escape');
  }

  // 5. javascript: incollato a mano. Nel popup (salvato dal pannello) e in un widget (dall'editor, come una persona).
  const cattivi = ['javascript:alert(document.domain)', 'JaVaScRiPt:alert(1)', ' java\nscript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'vbscript:msgbox(1)', 'javascript&colon;alert(1)'];
  {
    await vai('popup.php?nuovo=1');
    await p.fill('input[name=nome]', 'SL Popup cattivo'); await p.check('input[name=attivo]');
    await p.fill('input[name=pulsante_testo]', 'Clic'); await p.fill('#pp-link', cattivi[0]);
    await p.selectOption('select[name=dove]', 'percorsi'); await p.fill('#pp-percorsi', '/sl-pulsanti/');
    await p.fill('input[name=valore]', '0'); await p.fill('input[name=frequenza_giorni]', '0');
    await Promise.all([p.waitForNavigation(), p.click('form button.btn-primary:has-text("Salva")')]);
    const html = await (await p.request.get(B + '/sl-pulsanti/')).text();
    ok('popup: un link javascript: scritto a mano non arriva sul sito come link', !/href="\s*javascript:/i.test(html) && !html.includes('alert(document.domain)'), (html.match(/popup[\s\S]{0,400}/) || [''])[0].slice(0, 200));
  }
  await vai(`editor.php?tipo=pagine&id=${dati.pagina}`);
  await p.waitForFunction(() => { const f = document.querySelector('#ed-iframe'); return f && f.contentDocument && f.contentDocument.querySelector('.ed-sezione'); }, null, { timeout: 30000 });
  if (await p.locator('#ed-chiudi-revisioni').isVisible()) await p.click('#ed-chiudi-revisioni');
  {
    const tela = p.frameLocator('#ed-iframe');
    await tela.locator('[data-ed-tipo="hero"]').first().dispatchEvent('click'); await dorme(400);
    await p.click('.ed-schede-pannello button[data-pannello="modifica"]'); await dorme(250);
    await p.locator('#ed-controlli .ed-voce-testa').first().click(); await dorme(250);
    await p.locator('#ed-controlli .ed-voce-corpo .pn-link-campo input').first().fill(cattivi[0]);
    await dorme(300);
    await p.click('#ed-pubblica');
    await p.waitForFunction(() => /Pubblicat/.test(document.querySelector('#ed-stato').textContent), null, { timeout: 20000 });
    const html = await (await p.request.get(B + '/sl-pulsanti/')).text();
    const href = (html.match(/<a[^>]*class="btn[^"]*"[^>]*href="([^"]*)"[^>]*>Prenota/) || html.match(/href="([^"]*)"[^>]*>Prenota/) || [0, ''])[1];
    ok('editor: javascript: incollato a mano nel Link del pulsante, sul sito il pulsante non lo esegue (editor_link_sicuro)', !/^\s*javascript:/i.test(href) && !html.includes('alert(document.domain)'), href);
    // Le varianti, salvate dall'API dell'editor come fa Pubblica: il widget Pulsanti e il controllo Link dell'Immagine.
    const r = await p.evaluate(async ([id, cattivi]) => {
      const D = JSON.parse(document.querySelector('#ed-dati').textContent);
      const a = JSON.parse(JSON.stringify(D.albero));
      a.elementi.push({ id: 'slatt01', tipo: 'sezione', impostazioni: {}, figli: [{ id: 'slatt02', tipo: 'colonna', impostazioni: {}, figli: [
        { id: 'slatt03', tipo: 'pulsanti', impostazioni: { voci: cattivi.map((c, i) => ({ testo: 'Cattivo ' + i, link: c, stile: 'viola' })) } },
        { id: 'slatt04', tipo: 'immagine', impostazioni: { immagine: '/uploads/kit/disegno-1.svg', alt: 'x', link: { url: cattivi[1], nuova: false } } }] }] });
      const fd = new FormData();
      for (const [k, v] of Object.entries({ _csrf: D.csrf, id, tipo: 'pagine', azione: 'salva', albero: JSON.stringify(a), pubblica: 1, nota: '' })) fd.append(k, v);
      return (await fetch('editor-api.php', { method: 'POST', body: fd, credentials: 'same-origin' })).status;
    }, [dati.pagina, cattivi]);
    const html2 = await (await p.request.get(B + '/sl-pulsanti/')).text();
    const hrefs = [...html2.matchAll(/href="([^"]*)"/g)].map(m => m[1]);
    ok('editor: le varianti (maiuscole, a capo, data:, vbscript:, entità) non escono mai come link', r === 200 && !hrefs.some(h => /^\s*(javascript|data|vbscript)\s*(:|&colon;)/i.test(h.replace(/&#\d+;|&[a-z]+;/gi, m => m === '&colon;' ? ':' : m))) && !html2.includes('alert(1)</script>') , { r, cattivi: hrefs.filter(h => /script|data:|colon/i.test(h)) });
  }

  // 6. Il redattore non vede le bozze degli addon (e il resto lo vede come nel pannello).
  {
    const rc = await br.newContext();
    const r = await rc.newPage();
    await r.goto(B + '/admin/login.php'); await r.fill('#username', dati.redattore); await r.fill('#password', dati.password);
    await Promise.all([r.waitForNavigation(), r.click('button[type=submit]')]);
    await r.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
    const t = await r.$eval('meta[name=pn-csrf]', m => m.content);
    const j = await (await r.request.post(API, { form: { q: 'SL Evento in bozza', _csrf: t } })).json();
    ok('redattore: l\'evento in bozza non c\'è', Array.isArray(j.voci) && j.voci.length === 0, j.voci);
    const k = await (await r.request.post(API, { form: { q: '', cosa: 'evento', _csrf: t } })).json();
    ok('redattore: degli eventi solo i pubblicati', k.voci.length > 0 && k.voci.every(v => v.stato === 'pubblicato'), k.voci);
    const m = await r.request.post(API, { form: { q: 'Contatti', tipo: 'modelli', _csrf: t } });
    ok('redattore: ?tipo=modelli (riservato) non apre niente in più', m.status() === 403, m.status());
    await rc.close();
  }

  ok('nessun errore JavaScript nel pannello', err.length === 0, err);
  await br.close();
  cli('pulisci');
  cli('tentativi');
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
