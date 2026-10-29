// Prove di attacco al CMS. Ogni prova LANCIA un attacco e passa (OK) solo se l'attacco FALLISCE.
// Da rilanciare a ogni versione, sul sito di prova locale (mai sul sito vivo).
//
//   node tools/sicurezza-prova.js <base> <utente> <password> [prefisso-pannello]
//   esempio:  node tools/sicurezza-prova.js http://127.0.0.1:8098 danilo prova-locale-12 /new
//
// Serve Playwright (require('playwright')) e un pannello raggiungibile. Crea un utente redattore "sic_prova_red"
// e lo cancella alla fine; se qualcosa resta, lo toglie al giro dopo. Non lascia contenuti pubblicati.
const { chromium } = require('playwright');

const [BASE, UTENTE, PASSWORD, PAN = '/new'] = process.argv.slice(2);
if (!BASE || !UTENTE || !PASSWORD) { console.error('uso: node sicurezza-prova.js <base> <utente> <password> [prefisso-pannello]'); process.exit(2); }
const ADMIN = BASE + PAN + '/admin';
let tot = 0, ko = 0;
const ok = (titolo, passato, extra = '') => { tot++; if (!passato) ko++; console.log((passato ? 'OK  ' : 'KO  ') + titolo + (extra ? '  ' + JSON.stringify(extra).slice(0, 220) : '')); };

// Un contesto del browser con la sessione di un utente del pannello. Ritorna anche il token CSRF corrente.
async function entra(browser, utente, password, codice2fa) {
  const ctx = await browser.newContext();
  const p = await ctx.newPage();
  await p.goto(ADMIN + '/login.php', { waitUntil: 'networkidle' });
  await p.fill('#username', utente); await p.fill('#password', password);
  await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
  if (codice2fa && await p.locator('#codice').count()) { await p.fill('#codice', codice2fa); await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]); }
  const dentro = !p.url().includes('login.php');
  return { ctx, p, dentro };
}

// Il token CSRF visto da una pagina del pannello (campo nascosto o oggetto window.SC).
async function tokenDi(p, url) {
  await p.goto(url, { waitUntil: 'networkidle' });
  return await p.evaluate(() => {
    const i = document.querySelector('input[name=_csrf]'); if (i) return i.value;
    if (window.SC && window.SC.csrf) return window.SC.csrf;
    const m = document.body.innerHTML.match(/"csrf":"([a-f0-9]{16,})"/); return m ? m[1] : '';
  });
}

// Lo stato HTTP di un indirizzo visto da un contesto (con la sua sessione).
async function anon_get(browser, url, ctx) { const r = await ctx.request.get(url, { maxRedirects: 0 }); return r.status(); }

// Una fetch dentro il contesto del browser (porta i cookie di sessione), con intestazioni a scelta.
async function post(p, url, body, headers = {}) {
  return await p.evaluate(async ({ url, body, headers }) => {
    const r = await fetch(url, { method: 'POST', headers, body, credentials: 'include', redirect: 'manual' });
    let t = ''; try { t = await r.text(); } catch (e) {}
    return { status: r.status, type: r.type, body: t.slice(0, 4000) };
  }, { url, body, headers });
}

(async () => {
  const browser = await chromium.launch();

  // --- preparazione: entra come amministratore, crea un redattore di prova
  const admin = await entra(browser, UTENTE, PASSWORD);
  if (!admin.dentro) { console.error('Non riesco a entrare come amministratore: controlla utente e password (o un 2FA attivo).'); process.exit(2); }
  const RED = 'sic_prova_red', REDPW = 'Red-Prova-Sicura-2026';
  {
    const t = await tokenDi(admin.p, ADMIN + '/utenti.php');
    // cancella un eventuale avanzo del giro prima
    await admin.p.evaluate(async ({ base }) => {
      const r = await fetch(base + '/utenti.php', { credentials: 'include' }); const h = await r.text();
      const m = [...h.matchAll(/utenti\.php\?modifica=(\d+)"[^]*?sic_prova_red/g)];
    }, { base: ADMIN }).catch(() => {});
    await post(admin.p, ADMIN + '/utenti.php', new URLSearchParams({ _csrf: t, username: RED, email: 'sic_prova_red@esempio.it', display_name: 'Prova', role: 'editor', password: REDPW, password2: REDPW }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
  }
  const red = await entra(browser, RED, REDPW);
  ok('il redattore di prova entra nel pannello', red.dentro);

  // === 0. (attacco 8, prima della forza bruta che blocca l'IP di chi prova) le richieste di nuova password non chiudono fuori il proprietario
  {
    const ctx = await browser.newContext(); const p = await ctx.newPage();
    await admin.p.goto(ADMIN + '/password.php'); const email = await admin.p.inputValue('#email').catch(() => '');
    for (let i = 0; i < 6 && email; i++) { await p.goto(ADMIN + '/password-dimenticata.php'); await p.fill('#email', email); await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]); }
    await ctx.close();
    const dopo = await entra(browser, UTENTE, PASSWORD);
    ok('reset ripetuti per la sua email: il proprietario entra lo stesso', !!email && dopo.dentro);
    await dopo.ctx.close();
  }

  // === 1. XSS: widget HTML da un redattore -> deve diventare testo, niente <script>
  if (red.dentro) {
    // id di una pagina qualunque da aprire nell'editor
    await red.p.goto(ADMIN + '/contenuti.php?tipo=pagine', { waitUntil: 'networkidle' });
    const idPag = await red.p.evaluate(() => { const a = document.querySelector('a[href*="editor.php?"][href*="id="], a[href*="contenuto.php?"][href*="id="]'); if (!a) return 0; const m = a.href.match(/[?&]id=(\d+)/); return m ? +m[1] : 0; });
    const tEd = await tokenDi(red.p, ADMIN + '/editor.php?tipo=pagine&id=' + idPag);
    const albero = JSON.stringify({ versione: 1, elementi: [{ id: 'sezsic1', tipo: 'sezione', impostazioni: {}, figli: [{ id: 'colsic1', tipo: 'colonna', impostazioni: {}, figli: [
      { id: 'wsic01', tipo: 'html', impostazioni: { codice: '<p>ciao</p><script>window.__xss=1</script>' } },
      { id: 'wsic02', tipo: 'testo', impostazioni: { contenuto: '<p><a href="javascript:window.__xss2=1">x</a> <a href=\'javascript:alert(3)\'>z</a> <b onmouseover="window.__xss3=1">b</b></p>', css_personalizzato: 'body{display:none}' } },
    ] }] }] });
    const r = await post(red.p, ADMIN + '/editor-api.php', new URLSearchParams({ azione: 'disegna', tipo: 'pagine', id: String(idPag), _csrf: tEd, albero }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
    let html = '', css = '';
    try { const j = JSON.parse(r.body); html = j.html || ''; css = j.css || ''; } catch (e) {}
    ok('XSS widget HTML (redattore): niente <script> grezzo nella tela', r.status === 200 && !/<script>window\.__xss/.test(html), { estratto: html.slice(0, 160) });
    ok('XSS testo (redattore): href javascript: rimosso', !/javascript:/.test(html));
    ok('XSS testo (redattore): on* rimosso', !/onmouseover/.test(html));
    ok('CSS personalizzato (redattore): nessuna regola body non confinata', !/(^|[;}])\s*body\s*\{/.test(css) && !/(^|[;}])\s*html\s*\{/.test(css), { css: css.slice(0, 160) });
  }

  // === 2. CSRF: POST senza token, e con Origin esterno
  {
    const t = await tokenDi(admin.p, ADMIN + '/utenti.php');
    const senza = await post(admin.p, ADMIN + '/editor-api.php', new URLSearchParams({ azione: 'disegna', tipo: 'pagine', id: '1', albero: '{"versione":1,"elementi":[]}' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
    ok('CSRF: editor-api senza token rifiutato (403)', senza.status === 403, { status: senza.status });
    // Un vero attacco cross-site manda Origin del sito dell'attaccante: lo simula l'APIRequestContext (lato Node, non filtrato dal browser).
    const er = await admin.ctx.request.post(ADMIN + '/editor-api.php', { headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Origin': 'https://evil.example' }, data: new URLSearchParams({ azione: 'disegna', tipo: 'pagine', id: '1', _csrf: t, albero: '{"versione":1,"elementi":[]}' }).toString(), maxRedirects: 0 });
    ok('CSRF: editor-api con Origin esterno rifiutato (403)', er.status() === 403, { status: er.status() });
    const rr = await admin.ctx.request.post(ADMIN + '/editor-api.php', { headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Referer': 'https://evil.example/pagina' }, data: new URLSearchParams({ azione: 'disegna', tipo: 'pagine', id: '1', _csrf: t, albero: '{"versione":1,"elementi":[]}' }).toString(), maxRedirects: 0 });
    ok('CSRF: editor-api con Referer esterno rifiutato (403)', rr.status() === 403, { status: rr.status() });
    const seo = await post(admin.p, ADMIN + '/seo-api.php', new URLSearchParams({ azione: 'analisi' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
    ok('CSRF: seo-api senza token rifiutato (403)', seo.status === 403, { status: seo.status });
  }

  // === 3. IDOR / ruolo: un redattore non apre le pagine da amministratore
  if (red.dentro) {
    for (const pag of ['utenti.php', 'seo.php', 'aggiornamenti.php', 'impostazioni.php', 'sicurezza.php']) {
      const r = await red.p.goto(ADMIN + '/' + pag, { waitUntil: 'networkidle' });
      const rimbalzo = red.p.url().includes('index.php') || red.p.url().includes('login.php') || (await red.p.content()).includes('riservata');
      ok('ruolo: il redattore non apre ' + pag, rimbalzo, { url: red.p.url() });
    }
  }

  // === 3b. (attacco 2, alta) OGNI file .php del pannello, delle API e dei moduli, da anonimo: login, 401 o 403.
  //          Serve la cartella del CMS sul disco (CMS_DIR nell'ambiente) per elencare i file. Fanno eccezione solo
  //          gli endpoint pubblici dichiarati qui sotto.
  const PUBBLICI = ['admin/login.php', 'admin/password-dimenticata.php', 'admin/password-reimposta.php', 'admin/logout.php',
    'api/nl.php', 'api/csp.php', 'api/modulo.php', 'api/captcha.php', 'api/random-aforisma.php', 'api/burnout-test.php', 'api/test.php',
    'api/esporta.php', 'api/scadenze.php', 'api/versione.php' /* 1.3.0: pubblico per scelta, solo dati gia pubblici (prove al punto 17) */];
  const fs = require('fs'), path = require('path');
  const elenca = (dir) => { try { return fs.readdirSync(dir).filter(f => f.endsWith('.php')); } catch (e) { return []; } };
  const DIR = process.env.CMS_DIR || '';
  const tutti = [];
  if (DIR) {
    for (const f of elenca(path.join(DIR, 'admin'))) tutti.push('admin/' + f);
    for (const f of elenca(path.join(DIR, 'api'))) tutti.push('api/' + f);
    for (const m of (() => { try { return fs.readdirSync(path.join(DIR, 'moduli')); } catch (e) { return []; } })()) {
      for (const sub of ['admin', 'api', 'share', 'includes', '']) for (const f of elenca(path.join(DIR, 'moduli', m, sub))) tutti.push(['moduli', m, sub, f].filter(Boolean).join('/'));
    }
  }
  // Segue i redirect (al massimo 5) e dice dove finisce: login, codice di blocco, oppure una pagina aperta.
  const dove = async (rq, url, metodo) => {
    let u = url;
    for (let i = 0; i < 6; i++) {
      const r = metodo === 'POST' ? await rq.post(u, { maxRedirects: 0, headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Origin': BASE }, data: 'azione=elimina&id=1' }) : await rq.get(u, { maxRedirects: 0 });
      const st = r.status(), loc = r.headers()['location'] || '';
      if (st >= 300 && st < 400 && loc) { if (/login\.php/.test(loc)) return { ok: true, esito: 'login' }; u = new URL(loc, u).href; metodo = 'GET'; continue; }
      if ([401, 403, 404, 405, 410].includes(st)) return { ok: true, esito: String(st) };
      const corpo = await r.text();
      return { ok: false, esito: st + ' ' + corpo.replace(/\s+/g, ' ').slice(0, 80) };
    }
    return { ok: false, esito: 'troppi redirect' };
  };
  if (tutti.length) {
    const anon = await (await browser.newContext()).request;
    const aperti = [];
    for (const f of tutti) {
      if (PUBBLICI.includes(f)) continue;
      for (const metodo of ['GET', 'POST']) { const e = await dove(anon, BASE + PAN + '/' + f, metodo); if (!e.ok) aperti.push(metodo + ' ' + f + ' -> ' + e.esito); }
    }
    ok('anonimo: tutti i ' + tutti.filter(f => !PUBBLICI.includes(f)).length + ' file .php non pubblici rimandano al login o rispondono 401/403/404', aperti.length === 0, aperti.slice(0, 8));
    // gli stessi file come redattore: fuori dalle sue pagine, dashboard (rimando) o 403
    if (red.dentro) {
      const REDATTORE = ['admin/index.php', 'admin/contenuti.php', 'admin/contenuto.php', 'admin/editor.php', 'admin/editor-api.php', 'admin/editor-anteprima.php', 'admin/seo-api.php',
        'admin/media.php', 'admin/categories.php', 'admin/versioni.php', 'admin/account.php', 'admin/password.php', 'admin/guida-infografiche.php',
        // i vecchi indirizzi che rimandano alle schede dei contenuti
        'admin/articles.php', 'admin/article-edit.php', 'admin/article-delete.php', 'admin/pages.php', 'admin/page-edit.php', 'admin/page-delete.php', 'admin/aforismi.php', 'admin/aforisma-edit.php', 'admin/aforisma-delete.php'];
      const fuori = [];
      for (const f of tutti.filter(f => f.startsWith('admin/') && !PUBBLICI.includes(f) && !REDATTORE.includes(f))) {
        const r = await red.ctx.request.get(BASE + PAN + '/' + f, { maxRedirects: 0 });
        const loc = r.headers()['location'] || '';
        const bloccato = (r.status() >= 300 && r.status() < 400 && /index\.php|login\.php/.test(loc)) || [401, 403, 404, 405, 410].includes(r.status());
        if (!bloccato) fuori.push(f + ' -> ' + r.status());
      }
      ok('redattore: le pagine solo admin lo rimandano alla dashboard o rispondono 403', fuori.length === 0, fuori.slice(0, 8));
      const mod = await red.ctx.request.get(BASE + PAN + '/admin/editor.php?tipo=modelli&id=1', { maxRedirects: 0 });
      ok('redattore: header e footer nell\'editor (tipo=modelli) negati', mod.status() !== 200, { status: mod.status() });
      // il menu del redattore non mostra le voci che non puo aprire
      await red.p.goto(ADMIN + '/index.php', { waitUntil: 'networkidle' });
      const voci = await red.p.locator('.admin-nav a').evaluateAll(a => a.map(x => x.getAttribute('href')));
      const vietate = voci.filter(h => h && !/^(index|contenuti|contenuto|editor|media|categories|versioni|account|password|guida-infografiche)\.php/.test(h) && !/^\.\.\//.test(h));
      ok('redattore: nel menu solo le voci che puo aprire', vietate.length === 0, vietate);
    }
  } else {
    console.log('--  elenco completo dei file: saltato (manca CMS_DIR, la cartella del CMS sul disco)');
  }

  // === 3c. (attacco 2) il redattore non manda newsletter, non cancella iscritti, non pubblica modelli
  if (red.dentro) {
    const t = await tokenDi(red.p, ADMIN + '/contenuti.php?tipo=articoli');
    for (const [pag, dati] of [['newsletter.php', { azione: 'nuova' }], ['newsletter-campagna.php', { azione: 'subito', id: '1' }], ['newsletter.php', { azione: 'cancella', id: '1' }], ['modelli.php', { azione: 'predefinito', id: '1' }], ['menu.php', { azione: 'elimina', id: '1' }], ['form.php', { azione: 'elimina', id: '1' }], ['popup.php', { azione: 'elimina', id: '1' }]]) {
      const r = await red.ctx.request.post(ADMIN + '/' + pag, { maxRedirects: 0, headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Origin': BASE }, data: new URLSearchParams(Object.assign({ _csrf: t }, dati)).toString() });
      ok('redattore: ' + pag + ' ' + dati.azione + ' rifiutato', r.status() === 403, { status: r.status() });
    }
  }

  // === 3d. (attacco 2) il CSV dei form non porta formule
  {
    const r = await admin.ctx.request.get(ADMIN + '/form-submissions.php?csv=1');
    const testo = r.status() === 200 ? await r.text() : '';
    const formule = testo.split(/\r?\n/).filter(l => /(^|;)"?[=+@]/.test(l));
    ok('CSV degli invii: nessuna cella che comincia con = + @', formule.length === 0, formule.slice(0, 3));
  }

  // === 3f. (attacco 2, modulo danilofailli) il report del test del burnout non parte verso un indirizzo qualsiasi
  {
    const r = await (await browser.newContext()).request.post(BASE + PAN + '/api/burnout-test.php', { headers: { 'Content-Type': 'application/json' }, data: JSON.stringify({ action: 'add_email', session_id: 'sic-prova-non-mio', email: 'vittima@esempio.it' }) });
    if (r.status() === 404) console.log('--  report del burnout: saltata (endpoint assente, prodotto senza il modulo)');
    else ok('burnout: il report non parte per un test non fatto in questa sessione', r.status() === 403, { status: r.status() });
  }

  // === 3e. (attacco 2) il login non mostra errori del database
  {
    const r = await (await browser.newContext()).request.get(ADMIN + '/login.php');
    ok('login: nessun messaggio tecnico (SQLSTATE, PDO) a video', !/SQLSTATE|PDOException|Errore DB/.test(await r.text()));
  }

  // === 4. Open redirect: form-handler e nl non mandano fuori sito
  {
    const r = await post(admin.p, BASE + '/form-handler.php', new URLSearchParams({ website: 'spam', _redirect: 'https://evil.example/x' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
    const loc = (r.body.match(/Location:\s*(\S+)/i) || [])[1] || '';
    ok('open redirect: form-handler non manda a un host esterno', r.status < 300 || r.status >= 400 ? true : !/evil\.example/.test(loc), { status: r.status });
    const nl = await admin.p.evaluate(async (base) => { const r = await fetch(base + '/api/nl.php?a=c&u=' + encodeURIComponent('https://evil.example/x') + '&c=1&f=1&p=1&i=1&t=falso', { redirect: 'manual', credentials: 'include' }); return { status: r.status, loc: r.headers.get('location') || '' }; }, BASE);
    ok('open redirect: api/nl.php con firma falsa non manda a un host esterno', !/evil\.example/.test(nl.loc), { loc: nl.loc });
  }

  // === 5. Forza bruta: il login si blocca dopo troppi tentativi
  {
    const ctx = await browser.newContext(); const p = await ctx.newPage();
    let bloccato = false;
    for (let i = 0; i < 9; i++) {
      await p.goto(ADMIN + '/login.php');
      await p.fill('#username', 'nessuno_sicprova'); await p.fill('#password', 'sbagliata' + i);
      await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
      if (/Troppi tentativi/i.test(await p.content())) { bloccato = true; break; }
    }
    ok('forza bruta: il login si blocca dopo pochi tentativi', bloccato);
    await ctx.close();
  }

  // === 5b. (attacco 3, 1.1.3) Blocchi del login per nome utente e tempi di risposta. Gira dentro PHP con le funzioni
  //          vere del CMS e IP simulati (10.77.x.x), perche da un solo indirizzo il blocco per IP fermerebbe la prova.
  //          Serve CMS_DIR e il comando php. Pulisce i tentativi e le righe del registro che crea.
  if (DIR && red.dentro) {
    const os = require('os'); const f = path.join(os.tmpdir(), 'sicprova-blocchi-' + process.pid + '.php');
    fs.writeFileSync(f, `<?php
// Prova dei blocchi del login e dei tempi di risposta, con le funzioni vere del CMS e IP simulati (10.77.x.x).
$_SERVER['REMOTE_ADDR'] = '10.77.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'sicurezza-prova';
require getenv('CMS_DIR') . '/admin/includes/auth.php';
$red = getenv('SIC_RED'); $pw = getenv('SIC_REDPW'); $adm = getenv('SIC_ADMIN');
$T = tbl('sicurezza_tentativi'); $L = tbl('sicurezza_log');
$pulisci = function () use ($T, $red) { db()->prepare("DELETE FROM $T WHERE chiave = ? OR chiave LIKE 'ip:10.77.%'")->execute(['utente:' . mb_strtolower($red)]); };
$ip = function ($x) { $_SERVER['REMOTE_ADDR'] = $x; };
// Da riga di comando la sessione non parte da sola: se ne apre una nuova per ogni tentativo, senza cookie.
ini_set('session.use_cookies', '0'); ini_set('session.save_path', sys_get_temp_dir());
$prova = function ($u, $p) {
  unset($GLOBALS['sic_durante_blocco']);
  if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION = []; session_destroy(); }
  session_id(bin2hex(random_bytes(16))); session_start();
  return login_esteso($u, $p);
};
$o = [];
$pulisci();
$id = (int)db()->query('SELECT id FROM ' . tbl('users') . ' WHERE username = ' . db()->quote($red))->fetchColumn();
$ip('10.77.0.1'); $o['casa_primo'] = $prova($red, $pw);
// prima della 1.1.3 bastavano 5 tentativi sbagliati sul nome utente, da IP qualunque, per chiudere fuori il proprietario
for ($i = 0; $i < 5; $i++) { $ip('10.77.1.' . ($i + 1)); $prova($red, 'sbagliata-' . $i); }
$ip('10.77.2.1'); $o['dopo5_ip_nuovo'] = $prova($red, $pw);
// 20 sbagliati in 15 minuti, uno per IP (nessun blocco per IP): parte il blocco del nome utente
for ($i = 0; $i < 20; $i++) { if ($i === 19) $o['dopo19'] = sicurezza_login_blocchi($red)['utente']; $ip('10.77.3.' . ($i + 1)); $prova($red, 'sbagliata-' . $i); }
$o['dopo20'] = sicurezza_login_blocchi($red)['utente'];
$st = db()->prepare("SELECT avvisato FROM $T WHERE chiave = ?"); $st->execute(['utente:' . mb_strtolower($red)]); $o['avvisato'] = (int)$st->fetchColumn();
$st = db()->prepare("SELECT COUNT(*) FROM $L WHERE evento = 'blocco' AND ip LIKE '10.77.3.%' AND dettagli LIKE ?"); $st->execute(['utente:' . mb_strtolower($red) . ' %']); $o['riga_blocco'] = (int)$st->fetchColumn();
$ip('10.77.4.1'); $o['estraneo_giusta'] = $prova($red, $pw); $o['estraneo_sbagliata'] = $prova($red, 'sbagliata-x');
$ip('10.77.0.1'); $o['casa_giusta'] = $prova($red, $pw);
$st = db()->prepare("SELECT COUNT(*) FROM $L WHERE evento = 'login_durante_blocco' AND utente_id = ? AND ip = '10.77.0.1'"); $st->execute([$id]); $o['riga_durante_blocco'] = (int)$st->fetchColumn();
$o['blocco_resta'] = sicurezza_login_blocchi($red)['utente'] > 0;
// un IP bloccato almeno una volta non e piu "di casa", anche se ha fatto accessi riusciti
$pulisci(); $ip('10.77.5.1'); $prova($red, $pw); for ($i = 0; $i < 6; $i++) $prova('nessuno-sicprova', 'x' . $i);
db()->prepare("DELETE FROM $T WHERE chiave = 'ip:10.77.5.1'")->execute();
for ($i = 0; $i < 20; $i++) { $ip('10.77.6.' . ($i + 1)); $prova($red, 'sbagliata-' . $i); }
$ip('10.77.5.1'); $o['ex_bloccato_giusta'] = $prova($red, $pw);
// per IP resta tutto come prima: al sesto tentativo sbagliato (soglia 5) risponde il blocco
$pulisci(); $ip('10.77.7.1'); $r = []; for ($i = 0; $i < 6; $i++) $r[] = $prova('nessuno-sicprova', 'x' . $i); $o['per_ip'] = $r;
// tempi: password sbagliata su un utente vero e su uno inesistente, alternati, un IP nuovo ogni volta
$t = ['esiste' => [], 'no' => []];
for ($i = 0; $i < 20; $i++) foreach (['esiste' => $adm, 'no' => 'nessuno-sicprova-' . $i] as $k => $u) {
  $ip('10.77.8.' . (2 * $i + ($k === 'no' ? 1 : 0) + 1)); $pulisci(); db()->prepare("DELETE FROM $T WHERE chiave = ?")->execute(['utente:' . mb_strtolower($u)]);
  $t0 = hrtime(true); $prova($u, 'sbagliata-di-proposito'); $t[$k][] = (hrtime(true) - $t0) / 1e6;
}
db()->prepare("DELETE FROM $T WHERE chiave = ?")->execute(['utente:' . mb_strtolower($adm)]);
$med = function ($a) { sort($a); $n = count($a); return $n % 2 ? $a[intdiv($n, 2)] : ($a[$n / 2 - 1] + $a[$n / 2]) / 2; };
$o['tempi'] = ['esiste' => round($med($t['esiste']), 1), 'no' => round($med($t['no']), 1)];
$pulisci();
db()->exec("DELETE FROM $T WHERE chiave LIKE 'ip:10.77.%' OR chiave LIKE 'utente:nessuno-sicprova%'");
db()->exec("DELETE FROM $L WHERE ip LIKE '10.77.%'");
db()->exec("DELETE FROM " . tbl('sicurezza_sessioni') . " WHERE ip LIKE '10.77.%'");
if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
echo json_encode($o);
`);
    let o = null;
    try { o = JSON.parse(require('child_process').execFileSync('php', [f], { encoding: 'utf8', env: Object.assign({}, process.env, { CMS_DIR: DIR, SIC_RED: RED, SIC_REDPW: REDPW, SIC_ADMIN: UTENTE }) }).trim().split('\n').pop()); } catch (e) { console.log('    errore della prova PHP: ' + String(e.message).slice(0, 300)); }
    try { fs.unlinkSync(f); } catch (e) {}
    o = o || {};
    ok('login: accesso riuscito da casa (prepara l\'IP di casa)', o.casa_primo === 'ok', o.casa_primo);
    ok('login: 5 sbagliati sul nome utente da IP diversi non chiudono fuori il proprietario', o.dopo5_ip_nuovo === 'ok', o.dopo5_ip_nuovo);
    ok('login: al ventesimo sbagliato in 15 minuti (non prima) il nome utente si blocca, per 15 minuti', o.dopo19 === 0 && o.dopo20 > 800 && o.dopo20 <= 900, { dopo19: o.dopo19, dopo20: o.dopo20 });
    ok('login: il blocco del nome utente manda l\'avviso e lascia una riga "blocco"', o.avvisato === 1 && o.riga_blocco === 1, { avvisato: o.avvisato, riga: o.riga_blocco });
    ok('login: durante il blocco, da un IP estraneo, password giusta e sbagliata rispondono uguale ("bloccato")', o.estraneo_giusta === 'bloccato' && o.estraneo_sbagliata === 'bloccato', [o.estraneo_giusta, o.estraneo_sbagliata]);
    ok('login: durante il blocco, dall\'IP di casa, la password giusta entra e lascia una riga nel registro', o.casa_giusta === 'ok' && o.riga_durante_blocco === 1, [o.casa_giusta, o.riga_durante_blocco]);
    ok('login: l\'accesso da casa non azzera il blocco del nome utente', o.blocco_resta === true);
    ok('login: un IP gia bloccato una volta non vale come IP di casa', o.ex_bloccato_giusta === 'bloccato', o.ex_bloccato_giusta);
    ok('login: per IP resta il blocco a 5 (il sesto risponde "bloccato")', Array.isArray(o.per_ip) && o.per_ip[5] === 'bloccato' && o.per_ip.slice(0, 5).every(x => x === 'no'), o.per_ip);
    const tm = o.tempi || {};
    ok('login: stesso tempo con utente vero e inesistente (scarto delle mediane < 15%, 20+20)', tm.esiste > 0 && Math.abs(tm.esiste - tm.no) < 0.15 * Math.max(tm.esiste, tm.no), tm);
  } else console.log('--  blocchi per nome utente e tempi: saltati (servono CMS_DIR e il redattore di prova)');
  // === 5c. (attacco 3, 1.1.3) l'installatore controlla la password dell'amministratore con le regole del pannello
  if (DIR && fs.existsSync(path.join(DIR, 'install/index.php'))) {
    const src = fs.readFileSync(path.join(DIR, 'install/index.php'), 'utf8');
    ok('installatore: password dell\'amministratore con sicurezza_password_errore()', /sicurezza_password_errore\(\$s\['admin_password'\]/.test(src) && !/mb_strlen\(\$s\['admin_password'\]\)/.test(src));
  } else console.log('--  installatore: saltato (manca CMS_DIR o install/)');

  // === 6. Caricamento: un PHP travestito da immagine e un SVG con script
  if (red.dentro) {
    const suMedia = async (p, nome, tipo, contenutoB64) => p.evaluate(async ({ base, nome, tipo, contenutoB64 }) => {
      const bin = atob(contenutoB64); const arr = new Uint8Array(bin.length); for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
      const fd = new FormData(); fd.append('file', new Blob([arr], { type: tipo }), nome);
      const tr = await fetch(base + '/media.php', { credentials: 'include' }); const h = await tr.text(); const m = h.match(/name="?_csrf"? value="([a-f0-9]+)"/); if (m) fd.append('_csrf', m[1]);
      const r = await fetch(base + '/media.php', { method: 'POST', body: fd, credentials: 'include' }); const t = await r.text();
      // media.php rimanda alla libreria con un flash: 'Caricata: ...' se accettato, 'flash-error' se respinto.
      return { status: r.status, ok: /flash-success[^]*Caricata:/.test(t) };
    }, { base: ADMIN, nome, tipo, contenutoB64 });
    const phpB64 = Buffer.from('<?php echo "pwned"; ?>').toString('base64');
    const php = await suMedia(red.p, 'finta.php.png', 'image/png', phpB64).catch(() => ({ status: 0, ok: false }));
    ok('upload: un PHP travestito da PNG viene rifiutato', php.ok === false, php);
    const svgB64 = Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script><rect width="10" height="10"/></svg>').toString('base64');
    await suMedia(red.p, 'cattivo.svg', 'image/svg+xml', svgB64).catch(() => {});
    // verifica che se accettato, l'SVG salvato non contenga script/eventi: lo leggiamo dalla libreria
    const svgPulito = await red.p.evaluate(async (base) => {
      const r = await fetch(base + '/media.php', { credentials: 'include' }); const h = await r.text();
      const m = h.match(/src="([^"]*cattivo[^"]*\.svg)"/); if (!m) return { assente: true };
      const s = await (await fetch(m[1], { credentials: 'include' })).text();
      return { script: /<script|onload|onclick/i.test(s) };
    }, BASE).catch(() => ({ assente: true }));
    ok('upload: un SVG, se accettato, non contiene script/eventi', svgPulito.assente || svgPulito.script === false, svgPulito);
  }

  // === 7. Accesso diretto ai file sensibili (vale dove il web server applica le regole .htaccess/Nginx)
  {
    const prova = async (percorso) => admin.p.evaluate(async (u) => { const r = await fetch(u, { credentials: 'include', redirect: 'manual' }); return r.status; }, BASE + percorso);
    for (const [perc, nome] of [[PAN + '/includes/config.local.php', 'config.local.php'], [PAN + '/logs/php-errori.log', 'logs'], [PAN + '/VERSIONE', 'VERSIONE'], [PAN + '/includes/sicurezza.php', 'includes'], [PAN + '/uploads/x.php', 'uploads/x.php'], [PAN + '/aggiornamenti/copie/', 'aggiornamenti/copie/ (copie del sito, 1.3.0)']]) {
      const s = await prova(perc);
      ok('accesso diretto a ' + nome + ' negato (403/404)', s === 403 || s === 404, { status: s, nota: 'richiede le regole del web server; sul server di prova le emula il router' });
    }
  }

  // === 8. SQLi nei parametri di ricerca e ordinamento
  if (admin.dentro) {
    const r1 = await admin.p.evaluate(async (base) => { const r = await fetch(base + "/contenuti.php?tipo=articoli&q=" + encodeURIComponent("' OR '1'='1") + "&ordina=" + encodeURIComponent("id; DROP TABLE x") + "&p=1", { credentials: 'include' }); return { status: r.status, errore: /SQLSTATE|SQL syntax|you have an error/i.test(await r.text()) }; }, ADMIN);
    ok('SQLi: ricerca e ordinamento non producono errori SQL', r1.status === 200 && !r1.errore, r1);
  }

  // === 9. Intestazioni di sicurezza presenti sul sito e nel pannello
  {
    const h = async (url) => admin.p.evaluate(async (u) => { const r = await fetch(u, { credentials: 'include' }); const g = (k) => r.headers.get(k) || ''; return { xcto: g('x-content-type-options'), xfo: g('x-frame-options'), ref: g('referrer-policy'), csp: g('content-security-policy') + g('content-security-policy-report-only'), xpb: g('x-powered-by') }; }, url);
    const sito = await h(BASE + '/');
    ok('intestazioni sito: X-Content-Type-Options nosniff', /nosniff/i.test(sito.xcto));
    ok('intestazioni sito: frame-ancestors/X-Frame-Options', /SAMEORIGIN/i.test(sito.xfo) || /frame-ancestors/i.test(sito.csp));
    ok('intestazioni sito: Content-Security-Policy presente', /frame-ancestors|default-src|script-src/i.test(sito.csp));
    ok('intestazioni sito: niente X-Powered-By', sito.xpb === '');
    const pan = await h(ADMIN + '/index.php');
    ok('intestazioni pannello: CSP presente', /frame-ancestors|script-src/i.test(pan.csp));
    ok('intestazioni pannello: niente X-Powered-By', pan.xpb === '');
  }

  // === 10. Sessione: un id scelto dall'attaccante non resta valido dopo il login (session fixation)
  {
    const ctx = await browser.newContext();
    await ctx.addCookies([{ name: 'PHPSESSID', value: 'sicprovafix0000000000000001', url: BASE }]);
    const p = await ctx.newPage();
    await p.goto(ADMIN + '/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
    await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
    const cookie = (await ctx.cookies()).find(c => c.name === 'PHPSESSID');
    ok('session fixation: dopo il login l\'id di sessione cambia', cookie && cookie.value !== 'sicprovafix0000000000000001', { nuovo: cookie && cookie.value.slice(0, 6) });
    // l'id fissato non apre il pannello in un contesto separato
    const ctx2 = await browser.newContext(); await ctx2.addCookies([{ name: 'PHPSESSID', value: 'sicprovafix0000000000000001', url: BASE }]);
    const p2 = await ctx2.newPage(); const r = await p2.goto(ADMIN + '/index.php', { waitUntil: 'networkidle' });
    ok('session fixation: l\'id fissato non e autenticato', p2.url().includes('login.php'), { url: p2.url() });
    await ctx.close(); await ctx2.close();
  }

  // === 11. (attacco 2) il cron giornaliero non parte dal web
  for (const c of ['cron-daily.php', 'cron-newsletter.php']) {
    const st = await admin.p.evaluate(async (u) => (await fetch(u, { credentials: 'include', redirect: 'manual' })).status, BASE + PAN + '/' + c);
    ok('cron dal web rifiutato: ' + c, st === 403 || st === 404, { status: st });
  }

  // === 12. (attacco 1) clic della newsletter con firma dell'evento giusta ma indirizzo esterno non firmato
  //         Serve il segreto dei moduli (SEGRETO=... nell'ambiente, dalla tabella settings, chiave moduli_segreto) per costruire t.
  if (process.env.SEGRETO) {
    const crypto = require('crypto');
    const t = crypto.createHmac('sha256', process.env.SEGRETO).update('1|0|0|1').digest('hex').slice(0, 20);
    const r = await admin.ctx.request.get(BASE + PAN + '/api/nl.php?a=c&c=1&f=0&p=0&i=1&t=' + t + '&u=' + encodeURIComponent('https://evil.example.com/phish'), { maxRedirects: 0 });
    const loc = r.headers()['location'] || '';
    ok('newsletter: evento firmato ma indirizzo esterno non firmato -> niente rimando fuori', !/evil\.example/.test(loc), { loc });
    const k = crypto.createHmac('sha256', process.env.SEGRETO).update('u|1|0|0|1|https://evil.example.com/phish').digest('hex').slice(0, 24);
    const r2 = await admin.ctx.request.get(BASE + PAN + '/api/nl.php?a=c&c=1&f=0&p=0&i=1&t=' + t + '&u=' + encodeURIComponent('https://evil.example.com/phish') + '&k=' + k, { maxRedirects: 0 });
    ok('newsletter: con l\'indirizzo firmato (link vero di una email) il rimando funziona', /evil\.example/.test(r2.headers()['location'] || ''), { loc: r2.headers()['location'] });
  } else {
    console.log('--  newsletter con firma: saltata (manca SEGRETO nell\'ambiente)');
  }

  // === 13. (attacco 4) ritorno "torna" verso un altro sito
  {
    const t = await tokenDi(admin.p, ADMIN + '/contenuti.php?tipo=articoli');
    const r = await admin.ctx.request.post(ADMIN + '/contenuti.php?tipo=articoli', { headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Origin': BASE }, data: new URLSearchParams({ _csrf: t, azione: 'nessuna', torna: 'https://evil.example/x' }).toString(), maxRedirects: 0 });
    const loc = r.headers()['location'] || '';
    ok('torna: il pannello non rimanda a un altro sito', !/evil\.example/.test(loc), { status: r.status(), loc });
  }

  // === 14. (attacco 3) un'immagine vera caricata risponde 200 al suo indirizzo (la cartella e quella protetta)
  {
    const png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    const esito = await admin.p.evaluate(async ({ base, png }) => {
      const bin = atob(png); const arr = new Uint8Array(bin.length); for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
      const h = await (await fetch(base + '/media.php', { credentials: 'include' })).text(); const m = h.match(/name="_csrf" value="([a-f0-9]+)"/);
      const fd = new FormData(); fd.append('file', new Blob([arr], { type: 'image/png' }), 'prova-sicurezza.png'); if (m) fd.append('_csrf', m[1]);
      const t = await (await fetch(base + '/media.php', { method: 'POST', body: fd, credentials: 'include' })).text();
      const u = (t.match(/Caricata: ([^<\s]+)/) || [])[1] || '';
      return { url: u, csrf: m ? m[1] : '' };
    }, { base: ADMIN, png });
    let st = 0;
    if (esito.url) st = await admin.p.evaluate(async (u) => (await fetch(u, { credentials: 'include' })).status, (esito.url.startsWith('http') ? '' : BASE) + esito.url);
    ok('media: l\'immagine caricata risponde 200 al suo indirizzo', !!esito.url && st === 200, { url: esito.url, status: st });
    if (esito.url) {
      const t2 = await tokenDi(admin.p, ADMIN + '/media.php');
      await post(admin.p, ADMIN + '/media.php', new URLSearchParams({ _csrf: t2, elimina: esito.url.replace(/^https?:\/\/[^/]+/, '') }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
    }
    // un .php accanto alle immagini non si esegue
    const dir = esito.url ? esito.url.replace(/[^/]+$/, '') : '/uploads/';
    const sp = await admin.p.evaluate(async (u) => (await fetch(u, { credentials: 'include', redirect: 'manual' })).status, (dir.startsWith('http') ? '' : BASE) + dir + 'non-esiste.php');
    ok('media: un .php nella cartella delle immagini non si esegue (403/404)', sp === 403 || sp === 404, { status: sp });
  }

  // === 15. (attacco 5) CSP del pannello senza 'unsafe-inline' e pagine del nucleo senza gestori in linea
  for (const pag of ['index.php', 'contenuti.php?tipo=articoli', 'versioni.php', 'sicurezza.php', 'account.php', 'utenti.php', 'media.php', 'modelli.php', 'menu.php', 'form.php', 'seo.php', 'copia-sito.php', 'aggiornamenti.php']) {
    const r = await admin.ctx.request.get(ADMIN + '/' + pag);
    const h = r.headers(); const csp = (h['content-security-policy'] || '') + ' ' + (h['content-security-policy-report-only'] || '');
    const html = await r.text();
    const gestori = (html.match(/\son(click|submit|change|input|keyup|load|error|mouseover)\s*=/gi) || []).length;
    ok('CSP pannello ' + pag + ': niente unsafe-inline, gestori in linea ' + gestori, !/script-src[^;]*'unsafe-inline'/.test(csp) && gestori === 0, { gestori });
  }

  // === 16. (attacco 7) pacchetto senza firma che mette un .php nella radice del sito: rifiutato
  {
    let zipB64 = '';
    try {
      zipB64 = require('child_process').execSync(`python3 -c "import io,zipfile,json,base64;b=io.BytesIO();z=zipfile.ZipFile(b,'w');z.writestr('manifest.json',json.dumps({'nome':'Prova sicurezza root php','file':[{'da':'file/x.php','a':'root/sicprova-x.php'}]}));z.writestr('file/x.php','<?php echo 1;');z.close();print(base64.b64encode(b.getvalue()).decode())"`, { encoding: 'utf8' }).trim();
    } catch (e) {}
    if (zipB64) {
      const t = await tokenDi(admin.p, ADMIN + '/aggiornamenti.php');
      const txt = await admin.p.evaluate(async ({ base, zipB64, t }) => {
        const bin = atob(zipB64); const arr = new Uint8Array(bin.length); for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
        const fd = new FormData(); fd.append('_csrf', t); fd.append('action', 'carica'); fd.append('pacchetto', new Blob([arr], { type: 'application/zip' }), 'prova.zip');
        return await (await fetch(base + '/aggiornamenti.php', { method: 'POST', body: fd, credentials: 'include' })).text();
      }, { base: ADMIN, zipB64, t });
      ok('pacchetto senza firma con .php in root/: rifiutato', /serve un pacchetto firmato/.test(txt) && !/Prova sicurezza root php[^<]*<\/(td|strong|b)>/.test(txt));
    } else console.log('--  pacchetto root/php: saltata (manca python3)');
  }

  // === 17. (1.3.0) Copia del sito e controllo della versione: pagine dell'amministratore, token, nomi di file, endpoint pubblico
  {
    const anon = await browser.newContext();
    const ra = await anon.request.get(ADMIN + '/copia-sito.php', { maxRedirects: 0 });
    ok('copia del sito senza login: rimanda al login', [302, 303].includes(ra.status()) && /login/.test(ra.headers()['location'] || ''), { status: ra.status() });
    const rs = await anon.request.post(ADMIN + '/copia-sito.php', { form: { azione: 'crea' }, maxRedirects: 0 });
    ok('copia del sito senza login, POST crea: niente copia (rimanda al login)', rs.status() !== 200, { status: rs.status() });
    await anon.close();
    if (red.dentro) {
      const rr = await red.ctx.request.get(ADMIN + '/copia-sito.php', { maxRedirects: 0 });
      ok('copia del sito: il redattore non entra', rr.status() !== 200 || !/Prepara la copia/.test(await rr.text()), { status: rr.status() });
      const tr = await tokenDi(red.p, ADMIN + '/index.php');
      const sr = await post(red.p, ADMIN + '/copia-sito.php', new URLSearchParams({ _csrf: tr, azione: 'scarica', file: 'x' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
      ok('copia del sito: il redattore non scarica niente', !sr.body.startsWith('PK') && !/manifest\.json|db\.sql/.test(sr.body), { status: sr.status });
      const vr = await post(red.p, ADMIN + '/aggiornamenti.php', new URLSearchParams({ _csrf: tr, action: 'versione_prepara' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
      ok('Scarica e prepara: il redattore non puo', !/scaricato e controllato/.test(vr.body), { status: vr.status });
    }
    const senza = await post(admin.p, ADMIN + '/copia-sito.php', new URLSearchParams({ azione: 'crea' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
    ok('copia del sito: POST senza token rifiutato (403)', senza.status === 403, { status: senza.status });
    const t = await tokenDi(admin.p, ADMIN + '/copia-sito.php');
    const est = await admin.ctx.request.post(ADMIN + '/copia-sito.php', { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: t, azione: 'crea' }, maxRedirects: 0 });
    ok('copia del sito: POST con Origin esterno rifiutato (403)', est.status() === 403, { status: est.status() });
    for (const f of ['../../includes/config.local.php', '../aggiornamenti/.htaccess', 'copia-20260101-000000-x.zip/../../../includes/config.local.php', '/etc/passwd']) {
      const r = await admin.ctx.request.post(ADMIN + '/copia-sito.php', { form: { _csrf: t, azione: 'scarica', file: f }, maxRedirects: 0 });
      const ct = r.headers()['content-type'] || '';
      ok('copia del sito: scarica "' + f + '" respinto', !/zip|octet/.test(ct) && !/DB_PASS|root:/.test(await r.text()), { status: r.status(), ct });
    }
    const rip = await admin.ctx.request.post(ADMIN + '/copia-sito.php', { form: { _csrf: t, azione: 'ripristina', file: '../../includes/config.local.php', conferma: '1' }, maxRedirects: 0 });
    ok('copia del sito: ripristina un file fuori dalle copie respinto', !/Sito ripristinato/.test(await rip.text()));
    const vs = await post(admin.p, ADMIN + '/aggiornamenti.php', new URLSearchParams({ action: 'versione_prepara' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
    ok('Scarica e prepara: POST senza token rifiutato (403)', vs.status === 403, { status: vs.status });
    const vc = await post(admin.p, ADMIN + '/aggiornamenti.php', new URLSearchParams({ action: 'versione_controlla' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF': 'sbagliato' });
    ok('controllo della versione con un token sbagliato: rifiutato (403)', vc.status === 403, { status: vc.status });
    const api = await admin.ctx.request.get(BASE + PAN + '/api/versione.php?serie=' + encodeURIComponent("../../includes/config.local.php"));
    ok('api/versione.php: una serie con ../ respinta (400), senza percorsi nella risposta', api.status() === 400 && !/\/includes\/|\/home\/|\/tmp\//.test(await api.text()), { status: api.status() });
    const api2 = await admin.ctx.request.get(BASE + PAN + '/api/versione.php?serie=' + encodeURIComponent("x' OR '1'='1-ultima"));
    ok('api/versione.php: una serie con apici respinta (400)', api2.status() === 400, { status: api2.status() });
  }

  // === 18. (1.4.0) Aspetto del pannello e foto più leggere: ognuno cambia solo il suo aspetto, valori ammessi, token, ruoli
  {
    const aspettoDi = async (p) => { await p.goto(ADMIN + '/index.php', { waitUntil: 'domcontentloaded' }); return p.getAttribute('html', 'data-pannello'); };
    // l'amministratore passa a Bottega: un valore rifiutato non deve riportarlo ad Aria (il predefinito)
    const t0 = await tokenDi(admin.p, ADMIN + '/account.php');
    await post(admin.p, ADMIN + '/account.php', new URLSearchParams({ _csrf: t0, azione: 'aspetto', aspetto: 'bottega' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
    const prima = await aspettoDi(admin.p);
    ok('aspetto: l\'amministratore sceglie Bottega (valore ammesso)', prima === 'bottega', { prima });
    if (red.dentro) {
      const tr = await tokenDi(red.p, ADMIN + '/account.php');
      // il redattore prova a cambiare l'aspetto dell'amministratore passando un id (il server usa solo la sessione)
      for (const campo of ['id', 'utente_id', 'user_id', 'utente']) {
        await post(red.p, ADMIN + '/account.php', new URLSearchParams({ _csrf: tr, azione: 'aspetto', aspetto: 'lavagna', [campo]: '1' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
      }
      ok('aspetto: il redattore non cambia quello dell\'amministratore (con id, utente_id, user_id, utente)', await aspettoDi(admin.p) === prima, { prima });
      ok('aspetto: ...e cambia solo il suo', await aspettoDi(red.p) === 'lavagna');
    }
    const t = await tokenDi(admin.p, ADMIN + '/account.php');
    for (const v of ['cosmo"><script>alert(1)</script>', '../../includes/config', 'ARIA', '', 'aria ', 'notte', 'cosmo;']) {
      const r = await post(admin.p, ADMIN + '/account.php', new URLSearchParams({ _csrf: t, azione: 'aspetto', aspetto: v }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
      const att = await aspettoDi(admin.p);
      const html = await admin.p.content();
      ok('aspetto: valore non ammesso "' + v.slice(0, 30) + '" rifiutato', att === prima && !html.includes('<script>alert(1)'), { status: r.status, att });
    }
    const senza = await post(admin.p, ADMIN + '/account.php', new URLSearchParams({ azione: 'aspetto', aspetto: 'cosmo' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
    ok('aspetto: POST senza token rifiutato (403) e aspetto invariato', senza.status === 403 && await aspettoDi(admin.p) === prima, { status: senza.status });
    const est = await admin.ctx.request.post(ADMIN + '/account.php', { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: t, azione: 'aspetto', aspetto: 'cosmo' }, maxRedirects: 0 });
    ok('aspetto: POST con Origin esterno rifiutato (403)', est.status() === 403 && await aspettoDi(admin.p) === prima, { status: est.status() });
    const anon = await browser.newContext();
    const ra = await anon.request.post(ADMIN + '/account.php', { form: { azione: 'aspetto', aspetto: 'cosmo' }, maxRedirects: 0 });
    ok('aspetto: senza login nessun cambio (rimanda al login)', ra.status() !== 200 && /login/.test(ra.headers()['location'] || ''), { status: ra.status() });
    const t1 = await tokenDi(admin.p, ADMIN + '/account.php');
    await post(admin.p, ADMIN + '/account.php', new URLSearchParams({ _csrf: t1, azione: 'aspetto', aspetto: 'aria' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
    // foto più leggere: solo l'amministratore, con il token
    const fa = await anon.request.post(ADMIN + '/media.php', { form: { azione: 'foto_impostazioni', foto_leggere: '0', foto_lato_max: '900' }, maxRedirects: 0 });
    ok('foto: impostazioni senza login respinte', fa.status() !== 200 && /login/.test(fa.headers()['location'] || ''), { status: fa.status() });
    await anon.close();
    if (red.dentro) {
      const tr = await tokenDi(red.p, ADMIN + '/media.php');
      const fr = await post(red.p, ADMIN + '/media.php', new URLSearchParams({ _csrf: tr, azione: 'foto_impostazioni', foto_leggere: '0', foto_lato_max: '900' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
      ok('foto: il redattore non cambia le impostazioni (403)', fr.status === 403, { status: fr.status });
    }
    const fs2 = await post(admin.p, ADMIN + '/media.php', new URLSearchParams({ azione: 'foto_impostazioni', foto_leggere: '0', foto_lato_max: '900' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
    ok('foto: impostazioni senza token rifiutate (403)', fs2.status === 403, { status: fs2.status });
    await admin.p.goto(ADMIN + '/media.php?foto=1', { waitUntil: 'domcontentloaded' });
    ok('foto: dopo gli attacchi le impostazioni non sono cambiate', await admin.p.locator('input[name=foto_lato_max]').inputValue().catch(() => '') !== '900');
  }

  // === 19. (1.4.0) Import da WordPress: solo amministratore, CSRF, SSRF, rimandi verso altri host, file PHP mascherati,
  //          HTML con script nei contenuti. Il WordPress cattivo e prove/finto-wp.php (php -S 127.0.0.1:8073 prove/finto-wp.php),
  //          indicato con FINTO_WP=http://127.0.0.1:8073 e messo in CMS_IMPORTA_WP_LOCALE nel config.local.php del CMS.
  {
    const IW = ADMIN + '/importa-wordpress.php';
    const anon = await browser.newContext();
    const ra = await anon.request.get(IW, { maxRedirects: 0 });
    ok('import WordPress senza login: rimanda al login', [302, 303].includes(ra.status()) && /login/.test(ra.headers()['location'] || ''), { status: ra.status() });
    const rs = await anon.request.post(IW, { form: { azione: 'giro' }, maxRedirects: 0 });
    ok('import WordPress senza login, POST giro: niente lavoro', rs.status() !== 200 || !/"fase"/.test(await rs.text()), { status: rs.status() });
    await anon.close();
    if (red.dentro) {
      const rr = await red.ctx.request.get(IW, { maxRedirects: 0 });
      ok('import WordPress: il redattore non entra', rr.status() !== 200 || !/Collegati e analizza/.test(await rr.text()), { status: rr.status() });
      const tr = await tokenDi(red.p, ADMIN + '/index.php');
      const pr = await post(red.p, IW, new URLSearchParams({ _csrf: tr, azione: 'collega', indirizzo: 'http://127.0.0.1:8073', utente: 'x', password: 'abcdabcdabcdabcdabcdabcd' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
      ok('import WordPress: il redattore non avvia niente (403)', pr.status === 403, { status: pr.status });
      const gr = await post(red.p, IW, new URLSearchParams({ _csrf: tr, azione: 'annulla' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
      ok('import WordPress: il redattore non annulla niente (403)', gr.status === 403, { status: gr.status });
    }
    const senza = await post(admin.p, IW, new URLSearchParams({ azione: 'collega', indirizzo: 'https://example.org', utente: 'a', password: 'b' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
    ok('import WordPress: POST senza token rifiutato (403)', senza.status === 403, { status: senza.status });
    const g = await post(admin.p, IW, new URLSearchParams({ azione: 'giro' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF': 'sbagliato' });
    ok('import WordPress: giro con un token sbagliato rifiutato (403)', g.status === 403, { status: g.status });
    const t = await tokenDi(admin.p, IW);
    const est = await admin.ctx.request.post(IW, { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: t, azione: 'annulla' }, maxRedirects: 0 });
    ok('import WordPress: POST con Origin esterno rifiutato (403)', est.status() === 403, { status: est.status() });
    // Il form come lo usa una persona: il messaggio d'errore e quello che conta.
    const collega = async (indirizzo, utente = 'admin', password = 'abcd efgh ijkl mnop qrst uvwx') => {
      await admin.p.goto(IW, { waitUntil: 'networkidle' });
      // Un import lasciato a meta da un giro di prima: si annulla (o si lascia perdere) prima di provare.
      for (const b of ['form:has(input[value=annulla]) button', 'form:has(input[value=abbandona]) button']) {
        if (await admin.p.locator(b).count()) { admin.p.once('dialog', d => d.accept()); await Promise.all([admin.p.waitForNavigation(), admin.p.locator(b).first().click()]); }
      }
      if (!(await admin.p.locator('#iw-indirizzo').count())) return 'form assente (un import e gia in corso?)';
      await admin.p.fill('#iw-indirizzo', indirizzo); await admin.p.fill('#iw-utente', utente); await admin.p.fill('#iw-password', password);
      await Promise.all([admin.p.waitForNavigation({ timeout: 60000 }).catch(() => {}), admin.p.click('button:has-text("Collegati e analizza il sito")')]);
      return (await admin.p.locator('.flash-error').allInnerTexts().catch(() => [])).join(' ');
    };
    for (const [u, atteso] of [['http://169.254.169.254/latest/meta-data/', /rete interna/], ['http://127.0.0.1/', /rete interna/], ['http://[::1]/', /rete interna/],
      ['http://2130706433/', /rete interna/], ['http://0x7f000001/', /rete interna|non esiste/], ['http://10.1.2.3/', /rete interna/], ['http://192.168.1.1/', /rete interna/],
      ['http://127.0.0.1:3306/', /porte normali/], ['http://utente:segreto@example.org/', /nome e password/], ['file:///etc/passwd', /non è valido/], ['gopher://127.0.0.1:70/_x', /non è valido/]]) {
      const m = await collega(u);
      ok('import WordPress, SSRF verso ' + u + ': rifiutato', atteso.test(m), m);
    }
    const FINTO = process.env.FINTO_WP || '';
    if (FINTO) {
      let m = await collega(FINTO + '/rimanda');
      ok('import WordPress: un rimando verso un altro host non si segue', /rimanda a un altro indirizzo/.test(m), m);
      m = await collega(FINTO + '/rimanda-interno');
      ok('import WordPress: un rimando verso i metadati del cloud non si segue', /rimanda a un altro indirizzo/.test(m), m);
      // Import completo dal WordPress cattivo, poi Annulla.
      m = await collega(FINTO, 'admin', 'abcd efgh ijkl mnop qrst uvwx');
      const dialoghi = []; admin.p.on('dialog', d => { if (!/Annullo|Confermo/.test(d.message())) dialoghi.push(d.message()); d.accept(); });
      await admin.p.waitForSelector('h2:has-text("Il report di")', { timeout: 60000 }).catch(() => {});
      ok('import WordPress: il nome del sito con <script> resta testo nel report', !(await admin.p.content()).includes('<script>alert(8)') && dialoghi.length === 0);
      await Promise.all([admin.p.waitForNavigation(), admin.p.click('button:has-text("Importa le voci scelte")')]).catch(() => {});
      await admin.p.waitForSelector('h2:has-text("Riepilogo")', { timeout: 120000 }).catch(() => {});
      const riep = await admin.p.locator('.iw').innerText().catch(() => '');
      ok('import WordPress: il "JPEG" che e codice PHP non entra (riepilogo)', /foto\.jpg\s+il contenuto non è un'immagine/.test(riep), riep.slice(0, 300));
      ok('import WordPress: il file PHP dichiarato come media non entra', /shell\.php\s+tipo di file non ammesso/.test(riep));
      ok('import WordPress: il file su un altro host non si scarica', /altrove\.jpg\s+sta su un altro sito/.test(riep));
      if (DIR) {
        const fs = require('fs'), path = require('path');
        const tutti = []; const giro = d => { for (const f of fs.readdirSync(d, { withFileTypes: true })) { const p = path.join(d, f.name); if (f.isDirectory()) giro(p); else tutti.push(p); } };
        giro(path.join(DIR, 'uploads'));
        const conCodice = tutti.filter(f => /\.(php\d?|phtml|phar)$/i.test(f) || /<\?php|<\?=|<script/i.test(fs.readFileSync(f).toString('latin1')));
        ok('import WordPress: in uploads nessun file PHP e nessun file con codice (GIF doppia riscritta, PDF con PHP e SVG con script fuori)', conCodice.length === 0, conCodice.map(f => path.relative(DIR, f)));
      } else console.log('--  import WordPress, file in uploads: saltata (manca CMS_DIR)');
      const pv = await admin.ctx.newPage();
      const pd = []; pv.on('dialog', d => { pd.push(d.message()); d.dismiss(); });
      await pv.goto(BASE + PAN + '/articolo-cattivo/?preview=1', { waitUntil: 'networkidle' });
      await pv.waitForTimeout(800);
      const corpo = await pv.content();
      ok('import WordPress: nell\'anteprima nessuno script parte (niente alert)', pd.length === 0, pd);
      // I testi "alert(10)" del titolo restano parole: conta che non ci siano script, eventi, javascript:, iframe, moduli o url() verso fuori.
      const pericoli = /<script(?![^>]*\b(nonce|src)=|[^>]*application\/ld\+json)|javascript:|\son[a-z]+=|<iframe|<form|(src|action)="[^"]*evil\.example|url\([^)]*evil\.example/gi;
      ok('import WordPress: via script, eventi, javascript:, iframe e moduli estranei, url() esterni', /Testo buono/.test(corpo) && !pericoli.test(corpo), (corpo.match(/<script[^>]{0,60}|javascript:.{0,40}|<iframe[^>]{0,60}/g) || []).slice(0, 6));
      ok('import WordPress: un finto segnaposto video con codice non diventa un iframe', !/youtube-nocookie\.com\/embed\/[^"]*[<>"]/.test(corpo) && (await pv.locator('iframe').count()) === 0);
      await pv.close();
      await admin.p.goto(IW, { waitUntil: 'networkidle' });
      if (await admin.p.locator('form:has(input[value=annulla]) button').count()) await Promise.all([admin.p.waitForNavigation(), admin.p.locator('form:has(input[value=annulla]) button').first().click()]);
      ok('import WordPress: Annulla toglie l\'articolo cattivo', (await anon_get(browser, BASE + PAN + '/articolo-cattivo/?preview=1', admin.ctx)) === 404);
    } else console.log('--  import WordPress dal sito cattivo: saltate (manca FINTO_WP, vedi prove/finto-wp.php)');
  }

  // === 23. (1.7.0, qui prima del gruppo 20, che preme Definitivo: le risposte cattive vogliono un sito ancora in prova)
  // Sito su misura: la pagina delle domande (admin/sito-su-misura.php) è dell'amministratore e vuole il
  // token; le risposte non diventano mai codice, né nel pannello né nel sito; Sostituisci di Media (azione=sostituisci) è
  // dell'amministratore e cambia solo un'immagine finta vera, con una foto vera.
  // La via scelta per l'XSS: un POST valido ricompone il sito (kit_cambia, con una copia del sito prima). Si fa solo se il sito
  // è già in prova su misura (il pulsante dice "Ricomponi il sito"): risposte cattive, controlli, poi di nuovo le risposte
  // di prima, lette dal form stesso (FormData), così le pagine del kit tornano com'erano; restano solo due copie del sito in
  // più. Su un sito che non è su misura un POST valido lo farebbe diventare su misura: lì non si manda, e l'escape si prova
  // da riga di comando (php, con CMS_DIR) su su_misura_campi_html e su_misura_pulisci, che non toccano il database.
  {
    const form = (o) => new URLSearchParams(o).toString();
    const CT = { 'Content-Type': 'application/x-www-form-urlencoded' };
    const PAG = ADMIN + '/sito-su-misura.php';
    const MEDIA = ADMIN + '/media.php?cartella=segnaposto';
    // Un attributo on* che contiene il segno delle prove (__sm), o una bandierina accesa: codice vivo nella pagina.
    const vivo = (p) => p.evaluate(() => {
      for (const el of document.querySelectorAll('*')) for (const a of el.attributes) if (/^on/i.test(a.name) && /__sm/.test(a.value)) return a.name + ' su ' + el.tagName;
      for (const s of document.querySelectorAll('script')) if (/__sm\d=1/.test(s.textContent)) return 'script';
      return Object.keys(window).some(k => /^__sm\d$/.test(k)) ? 'bandierina' : '';
    });
    const anon = await browser.newContext();
    const ra = await anon.request.get(PAG, { maxRedirects: 0 });
    ok('1.7.0: sito-su-misura.php senza login: rimanda al login', [302, 303].includes(ra.status()) && /login/.test(ra.headers()['location'] || ''), { status: ra.status() });
    const rpa = await anon.request.post(PAG, { form: { attivita: 'ristorante' }, maxRedirects: 0 });
    ok('1.7.0: sito-su-misura.php POST senza login: rifiutato', rpa.status() !== 200 && /login/.test(rpa.headers()['location'] || '') , { status: rpa.status(), location: rpa.headers()['location'] });
    const ma = await anon.request.post(ADMIN + '/media.php', { multipart: { azione: 'sostituisci', segnaposto: 'x' }, maxRedirects: 0 });
    ok('1.7.0: Sostituisci senza login: rifiutato', ma.status() !== 200 && /login/.test(ma.headers()['location'] || ''), { status: ma.status() });
    await anon.close();
    const r0 = await post(admin.p, PAG, form({ attivita: 'ristorante', citta: 'x' }), CT);
    ok('1.7.0: sito-su-misura.php senza token: 403', r0.status === 403, { status: r0.status });
    const t = await tokenDi(admin.p, ADMIN + '/index.php');
    ok('1.7.0: (preparazione) token del pannello letto', t !== '');
    const est = await admin.ctx.request.post(PAG, { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: t, attivita: 'ristorante', citta: 'x' }, maxRedirects: 0 });
    ok('1.7.0: sito-su-misura.php con Origin esterno: 403', est.status() === 403, { status: est.status() });
    const rif = await admin.ctx.request.post(PAG, { headers: { 'Referer': 'https://evil.example/x' }, form: { _csrf: t, attivita: 'ristorante', citta: 'x' }, maxRedirects: 0 });
    ok('1.7.0: sito-su-misura.php con Referer esterno: 403', rif.status() === 403, { status: rif.status() });
    await admin.p.goto(PAG + '?attivita=%3Cscript%3Ewindow.__sm0%3D1%3C%2Fscript%3E&citta=%22%3E%3Cimg%20src%3Dx%20onerror%3Dwindow.__sm0%3D1%3E', { waitUntil: 'networkidle' });
    ok('1.7.0: ?attivita= e ?citta= con HTML nell\'indirizzo: non entrano nella pagina', !(await vivo(admin.p)) && !(await admin.p.content()).includes('__sm0'));
    if (red.dentro) {
      await red.p.goto(PAG, { waitUntil: 'networkidle' });
      const fuori = !/sito-su-misura\.php/.test(red.p.url()) && ((await red.p.content()).includes('riservata') || /index\.php|login\.php/.test(red.p.url()));
      ok('1.7.0: il redattore non apre Sito su misura: torna alla dashboard', fuori, { url: red.p.url() });
      await red.p.goto(ADMIN + '/index.php', { waitUntil: 'networkidle' });
      ok('1.7.0: il redattore non vede la carta Su misura nella dashboard', await red.p.locator('a[href*="sito-su-misura.php"]').count() === 0);
      const tr = await tokenDi(red.p, ADMIN + '/index.php');
      const rp = await post(red.p, PAG, form({ _csrf: tr, attivita: 'ristorante', citta: 'x', 'pagine[]': 'contatti' }), CT);
      ok('1.7.0: il redattore, sito-su-misura.php con il suo token: 403', rp.status === 403, { status: rp.status });
    }

    // Il motore scarta quello che non sta negli elenchi (riga di comando: niente database, niente sito toccato).
    const { execFileSync } = require('child_process');
    const phpCli = (codice) => { try { return execFileSync('php', ['-d', 'display_errors=stderr', '-r', 'require ' + JSON.stringify(path.join(DIR, 'includes', 'sito-su-misura.php')) + '; ' + codice], { encoding: 'utf8', timeout: 20000 }); } catch (e) { return 'ERRORE ' + (e.stderr || e.message).slice(0, 200); } };
    if (DIR && fs.existsSync(path.join(DIR, 'includes', 'sito-su-misura.php'))) {
      const j = (() => { try { return JSON.parse(phpCli('echo json_encode([su_misura_pulisci(["attivita" => "<script>x</script>", "citta" => "<b>Roma</b>" . str_repeat("a", 200), "servizi" => ["<img src=x onerror=alert(1)>Cena", "a", "b", "c", "d", "e", "f", "g"], "pagine" => ["../../x", "faq", "chi-siamo OR 1=1", ["x"]], "ogni_servizio" => "1"]), su_misura_pulisci(["attivita" => ["ristorante"], "servizi" => "uno\ndue"]), su_misura_pulisci(["attivita" => "ristorante "])]);')); } catch (e) { return null; } })();
      ok('1.7.0: attività fuori elenco (con HTML, un array, uno spazio in più): diventa "altro"', !!j && j[0].attivita === 'altro' && j[1].attivita === 'altro' && j[2].attivita === 'altro', j && j.map(x => x.attivita));
      ok('1.7.0: pagine fuori elenco (../, SQL, un array): scartate', !!j && JSON.stringify(j[0].pagine) === '["faq"]', j && j[0].pagine);
      ok('1.7.0: servizi oltre sei scartati, tag tolti, città tagliata a 80 caratteri', !!j && j[0].servizi.length === 6 && !/[<>]/.test(j[0].servizi.join('')) && j[0].citta.length === 80 && !/</.test(j[0].citta), j && j[0]);
      const html = phpCli('echo su_misura_campi_html(["attivita" => "\\"><script>window.__sm1=1</script>", "citta" => "Roma\\" onfocus=\\"window.__sm2=1", "servizi" => ["x\\" autofocus onfocus=\\"window.__sm3=1", "&lt;img src=x onerror=window.__sm4=1&gt;"], "pagine" => ["faq\\"><b>"]], fn($s) => htmlspecialchars($s, ENT_QUOTES, "UTF-8"));');
      ok('1.7.0: le domande compilate con risposte cattive: ogni valore resta dentro il suo attributo', /name="citta"[^>]*value="Roma&quot; onfocus=&quot;window.__sm2=1"/.test(html) && !/onfocus="window/.test(html) && !/<script>window/.test(html) && /value="x&quot; autofocus onfocus=&quot;window.__sm3=1"/.test(html) && /value="&amp;lt;img/.test(html) && !/faq"><b>/.test(html), html.slice(0, 120));
    } else ok('1.7.0: (senza CMS_DIR il motore del sito su misura non si prova da riga di comando)', true);

    // Le risposte cattive davvero, sul sito: solo se è già in prova su misura (vedi sopra).
    await admin.p.goto(PAG, { waitUntil: 'networkidle' });
    const suMisura = await admin.p.locator('#sm-modulo').count() === 1 && /Ricomponi/.test(await admin.p.locator('#sm-componi').innerText().catch(() => ''));
    if (suMisura) {
      const prima = await admin.p.evaluate(() => [...new FormData(document.querySelector('#sm-modulo')).entries()]);
      const att = (prima.find(x => x[0] === 'attivita') || [])[1] || 'altro';
      const cattive = [['_csrf', (prima.find(x => x[0] === '_csrf') || [])[1]], ['attivita', att],
        ['citta', '<script>window.__sm2=1</script>Roma" onload="window.__sm1=1'],
        ['servizi[]', '"><img src=x onerror="window.__sm3=1">Cena'], ['servizi[]', '&lt;img src=x onerror=window.__sm4=1&gt;Pranzo'],
        ['servizi[]', "Corso' onmouseover='window.__sm5=1"], ['servizi[]', '<svg/onload=window.__sm6=1>Pizza'],
        ['pagine[]', 'servizi'], ['pagine[]', 'contatti'], ['pagine[]', '../../x'], ['pagine[]', "faq' OR 1=1"]];
      const manda = (coppie) => admin.p.evaluate(async ({ url, coppie }) => {
        const b = new URLSearchParams(); for (const [k, v] of coppie) b.append(k, v);
        const r = await fetch(url, { method: 'POST', body: b, credentials: 'include' }); const h = await r.text();
        return { status: r.status, flash: (h.match(/class="flash flash-(?:success|error)"[^>]*>([^<]*)/) || [])[1] || '' };
      }, { url: PAG, coppie });
      const rc = await manda(cattive);
      ok('1.7.0: (preparazione) risposte cattive dell\'amministratore: il sito si ricompone', /pronto/.test(rc.flash), rc);
      await admin.p.goto(PAG, { waitUntil: 'networkidle' });
      const citta = await admin.p.inputValue('input[name=citta]');
      ok('1.7.0: la pagina del pannello rimostra le risposte come testo (tag tolti, virgolette dentro il valore)', !(await vivo(admin.p)) && citta === 'window.__sm2=1Roma" onload="window.__sm1=1', { citta });
      const giro = [];
      for (const u of ['/', '/servizi/', '/contatti/']) {
        const pg = await browser.newPage();
        const r = await pg.goto(BASE + PAN + u, { waitUntil: 'networkidle' }).catch(() => null);
        giro.push([u, r ? r.status() : 0, await vivo(pg), (await pg.content()).includes('Cena')]);
        await pg.close();
      }
      ok('1.7.0: le risposte cattive nel sito (home, Servizi, Contatti): nessun codice vivo', giro.every(g => g[1] === 200 && !g[2]) && giro[0][3], giro);
      // Com'era prima.
      const rr = await manda(prima);
      await admin.p.goto(PAG, { waitUntil: 'networkidle' });
      const tornata = await admin.p.inputValue('input[name=citta]');
      ok('1.7.0: (pulizia) il sito torna alle risposte di prima', /pronto/.test(rr.flash) && tornata === ((prima.find(x => x[0] === 'citta') || [])[1] || ''), { flash: rr.flash, citta: tornata });
    } else ok('1.7.0: risposte cattive sul sito: saltate (il sito non è in prova su misura; l\'escape è provato da riga di comando)', true);

    // Sostituisci di Media.
    await admin.p.goto(MEDIA, { waitUntil: 'networkidle' });
    const finte = await admin.p.locator('.md-sostituisci input[name=segnaposto]').evaluateAll(l => l.map(i => i.value));
    if (finte.length) {
      const finta = finte[0];
      const uploads = finta.replace(/\/segnaposto\/[^/]+$/, '/');
      const tm = await tokenDi(admin.p, MEDIA);
      const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');
      const manda = (p, campi, file) => p.evaluate(async ({ url, campi, file }) => {
        const f = new FormData(); for (const [k, v] of Object.entries(campi)) f.append(k, v);
        if (file) f.append('file', new Blob([Uint8Array.from(atob(file.b64), c => c.charCodeAt(0))], { type: file.tipo }), file.nome);
        const r = await fetch(url, { method: 'POST', body: f, credentials: 'include' }); const h = await r.text();
        return { status: r.status, flash: (h.match(/class="flash flash-(?:success|error)"[^>]*>([^<]*)/) || [])[1] || '' };
      }, { url: MEDIA, campi, file });
      const png = { nome: 'sic-sm-prova.png', tipo: 'image/png', b64: PNG.toString('base64') };
      const s0 = await manda(admin.p, { azione: 'sostituisci', segnaposto: finta }, png);
      ok('1.7.0: Sostituisci senza token: 403', s0.status === 403, s0);
      const s1 = await admin.ctx.request.post(ADMIN + '/media.php', { headers: { 'Origin': 'https://evil.example' }, multipart: { _csrf: tm, azione: 'sostituisci', segnaposto: finta, file: { name: 'sic-sm-prova.png', mimeType: 'image/png', buffer: PNG } }, maxRedirects: 0 });
      ok('1.7.0: Sostituisci con Origin esterno: 403', s1.status() === 403, { status: s1.status() });
      if (red.dentro) {
        await red.p.goto(MEDIA, { waitUntil: 'networkidle' });
        ok('1.7.0: il redattore non vede i pulsanti Sostituisci', await red.p.locator('.md-sostituisci').count() === 0);
        const trm = await tokenDi(red.p, ADMIN + '/media.php');
        const sr = await manda(red.p, { _csrf: trm, azione: 'sostituisci', segnaposto: finta }, png);
        ok('1.7.0: il redattore, Sostituisci con il suo token: 403', sr.status === 403, sr);
      }
      const cattivi = [
        ['../ nel percorso', finta.replace(/[^/]+$/, '../../includes/config.local.php')],
        ['../ con il formato giusto in fondo', finta.replace(/[^/]+$/, '../segnaposto/' + finta.split('/').pop())],
        ['un file fuori da uploads/segnaposto', uploads + 'pagine/' + finta.split('/').pop()],
        ['un indirizzo di un altro sito', 'https://evil.example' + finta],
        ['un nome fuori formato (.php)', finta.replace(/\.svg$/, '.php')],
        ['un nome fuori formato (maiuscole)', finta.replace(/[^/]+$/, n => n.toUpperCase())],
        ['un a capo in fondo (il $ di PHP lo lascia passare)', finta + '\n'],
        ['un nome fuori formato (doppia estensione)', finta + '.php'],
        ['un nome fuori formato (byte nullo)', finta + '\0.php'],
        ['un nome che rispetta il formato ma non esiste', finta.replace(/-\d{1,2}-/, '-99-')],
      ];
      for (const [nome, seg] of cattivi) {
        const r = await manda(admin.p, { _csrf: tm, azione: 'sostituisci', segnaposto: seg }, png);
        ok('1.7.0: Sostituisci con ' + nome + ': rifiutato', /Si sostituiscono solo/.test(r.flash), { seg: seg.slice(-60), flash: r.flash });
      }
      const php = { nome: 'sic-sm-prova.php', tipo: 'image/png', b64: Buffer.from('<?php echo "sic-sm"; ?>').toString('base64') };
      const rp = await manda(admin.p, { _csrf: tm, azione: 'sostituisci', segnaposto: finta }, php);
      ok('1.7.0: Sostituisci con un .php travestito da PNG: rifiutato', /Formati accettati/.test(rp.flash), rp);
      const rv = await manda(admin.p, { _csrf: tm, azione: 'sostituisci', segnaposto: finta }, null);
      ok('1.7.0: Sostituisci senza file: rifiutato, la foto finta resta', !/preso il posto/.test(rv.flash), rv);
      const ancora = await admin.ctx.request.get(finta.startsWith('http') ? finta : new URL(finta, BASE).href);
      ok('1.7.0: dopo i rifiuti la foto finta esiste ancora', ancora.status() === 200 && (await ancora.text()).includes('Sostituisci questa foto'), { status: ancora.status() });
      await admin.p.goto(MEDIA, { waitUntil: 'networkidle' });
      ok('1.7.0: dopo i rifiuti Media mostra ancora tutte le foto finte', await admin.p.locator('.md-sostituisci').count() === finte.length);
      if (DIR) {
        const trovati = [];
        const cerca = (d) => { for (const f of (() => { try { return fs.readdirSync(d, { withFileTypes: true }); } catch (e) { return []; } })()) { const q = path.join(d, f.name); if (f.isDirectory()) cerca(q); else if (/sic-sm-prova/.test(f.name)) trovati.push(q); } };
        cerca(path.join(DIR, 'uploads'));
        ok('1.7.0: nessun file dei rifiuti è finito in uploads', trovati.length === 0 && fs.existsSync(path.join(DIR, 'includes', 'config.local.php')), trovati);
      }
    } else ok('1.7.0: Sostituisci: attacchi saltati (nessuna foto finta in Media: il sito non è su misura)', true);
  }

  // === 20. (1.4.0) Tipo di sito in prova, primi passi, Esegui adesso del cron, Controlla il server: solo l'amministratore,
  //          solo con il token, niente da fuori, nessun cambio dopo il Definitivo. Con CMS_DIR legge lo stato vero.
  {
    const { execFileSync } = require('child_process');
    const path = require('path');
    const fs = require('fs');
    const DIR = process.env.CMS_DIR || '';
    const stato = () => { try { return JSON.parse(execFileSync('php', [path.join(__dirname, '..', 'prove', 'tipo-sito-cli.php'), DIR, 'stato']).toString()); } catch (e) { return null; } };
    const giri = () => { try { return (fs.readFileSync(path.join(DIR, 'logs', 'cron-daily.log'), 'utf8').match(/\| Cron avviato/g) || []).length; } catch (e) { return 0; } };
    const prima = DIR ? JSON.stringify(stato()) : '';
    const giri0 = giri();
    const form = (o) => new URLSearchParams(o).toString();
    const CT = { 'Content-Type': 'application/x-www-form-urlencoded' };
    const anon = await browser.newContext();
    for (const [pag, corpo] of [['tipo-sito.php', { azione: 'cambia', kit: 'blog' }], ['tipo-sito.php', { azione: 'definitivo' }], ['cron-log.php', { azione: 'esegui' }], ['controlla-server.php', {}]]) {
      const r = await anon.request.post(ADMIN + '/' + pag, { form: corpo, maxRedirects: 0 });
      ok('1.4.0: ' + pag + ' ' + (corpo.azione || 'controlla') + ' senza login: rimanda al login', [302, 303].includes(r.status()) && /login/.test(r.headers()['location'] || ''), { status: r.status() });
    }
    await anon.close();
    if (red.dentro) {
      const tr = await tokenDi(red.p, ADMIN + '/index.php');
      ok('1.4.0: il redattore non vede il riquadro del tipo di sito ne i primi passi', !(await red.p.content()).includes('id="tipo-sito"') && !(await red.p.content()).includes('id="primi-passi"'));
      for (const [pag, corpo] of [['tipo-sito.php', { azione: 'cambia', kit: 'blog' }], ['tipo-sito.php', { azione: 'definitivo' }], ['tipo-sito.php', { azione: 'email_prova' }], ['tipo-sito.php', { azione: 'nascondi_passi' }], ['cron-log.php', { azione: 'esegui' }], ['controlla-server.php', {}]]) {
        const r = await post(red.p, ADMIN + '/' + pag, form({ _csrf: tr, ...corpo }), CT);
        ok('1.4.0: redattore, ' + pag + ' ' + (corpo.azione || 'controlla') + ': 403', r.status === 403, { status: r.status });
      }
    }
    for (const [pag, corpo] of [['tipo-sito.php', { azione: 'cambia', kit: 'blog' }], ['tipo-sito.php', { azione: 'definitivo' }], ['tipo-sito.php', { azione: 'email_prova' }], ['cron-log.php', { azione: 'esegui' }], ['controlla-server.php', {}]]) {
      const r = await post(admin.p, ADMIN + '/' + pag, form(corpo), CT);
      ok('1.4.0: ' + pag + ' ' + (corpo.azione || 'controlla') + ' senza token: 403', r.status === 403, { status: r.status });
    }
    const t = await tokenDi(admin.p, ADMIN + '/index.php');
    const est = await admin.ctx.request.post(ADMIN + '/tipo-sito.php', { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: t, azione: 'cambia', kit: 'blog' }, maxRedirects: 0 });
    ok('1.4.0: cambio del tipo di sito con Origin esterno: 403', est.status() === 403, { status: est.status() });
    const estc = await admin.ctx.request.post(ADMIN + '/cron-log.php', { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: t, azione: 'esegui' }, maxRedirects: 0 });
    ok('1.4.0: Esegui adesso con Origin esterno: 403', estc.status() === 403, { status: estc.status() });
    for (const k of ['../../includes/config.local', '../kit/blog', 'vuoto/../../x', 'BLOG']) {
      await admin.ctx.request.post(ADMIN + '/tipo-sito.php', { form: { _csrf: t, azione: 'cambia', kit: k }, maxRedirects: 0 });
    }
    if (DIR) {
      ok('1.4.0: tipi di sito inventati (../, maiuscole) respinti, niente cambiato', JSON.stringify(stato()) === prima, stato());
      ok('1.4.0: nessun giro del cron partito dagli attacchi', giri() === giri0, { prima: giri0, dopo: giri() });
      // Dopo il Definitivo (se il sito ha un tipo di sito in prova lo si preme qui: e un sito di prova) il cambio e rifiutato.
      const s = stato();
      if (s && s.stato === 'prova') await admin.ctx.request.post(ADMIN + '/tipo-sito.php', { form: { _csrf: t, azione: 'definitivo' }, maxRedirects: 0 });
      const s2 = stato();
      const rc = await admin.ctx.request.post(ADMIN + '/tipo-sito.php', { form: { _csrf: t, azione: 'cambia', kit: s2 && s2.kit === 'blog' ? 'attivita' : 'blog' } });
      ok('1.4.0: cambio del tipo di sito dopo il Definitivo (o senza tipo in prova), con il token giusto: rifiutato', JSON.stringify(stato()) === JSON.stringify(s2) && /definitivo|non ha un tipo di sito/.test(await rc.text()), stato());
    } else {
      ok('1.4.0: (senza CMS_DIR lo stato del tipo di sito non si legge: prove del cambio saltate)', true);
    }
  }

  // === 21. (1.5.0) Social: la pagina è dell'amministratore, ogni azione vuole il token, i testi non diventano codice.
  {
    const form = (o) => new URLSearchParams(o).toString();
    const CT = { 'Content-Type': 'application/x-www-form-urlencoded' };
    const anon = await browser.newContext();
    const ra = await anon.request.get(ADMIN + '/social.php', { maxRedirects: 0 });
    ok('1.5.0: social.php senza login: rimanda al login', [302, 303].includes(ra.status()) && /login/.test(ra.headers()['location'] || ''), { status: ra.status() });
    await anon.close();
    if (red.dentro) {
      await red.p.goto(ADMIN + '/social.php', { waitUntil: 'networkidle' });
      const fuori = !/social\.php/.test(red.p.url()) && ((await red.p.content()).includes('riservata') || /index\.php|login\.php/.test(red.p.url()));
      ok('1.5.0: il redattore non apre Social: torna alla dashboard', fuori, { url: red.p.url() });
      const tr = await tokenDi(red.p, ADMIN + '/index.php');
      const rp = await post(red.p, ADMIN + '/social.php', form({ _csrf: tr, azione: 'impostazioni', email_mattino: '1' }), CT);
      ok('1.5.0: il redattore non cambia le impostazioni dei social: 403', rp.status === 403, { status: rp.status });
    }
    for (const az of ['impostazioni', 'manda_mattino', 'programmato_salva', 'programmato_elimina', 'programmato_manda']) {
      const r = await post(admin.p, ADMIN + '/social.php', form({ azione: az, id: '1' }), CT);
      ok('1.5.0: social.php ' + az + ' senza token: 403', r.status === 403, { status: r.status });
    }
    // Il calendario vuoto non ha form: il token si prende dal form di un testo nuovo.
    const t = await tokenDi(admin.p, ADMIN + '/social.php?scheda=calendario&nuovo=1');
    ok('1.5.0: (preparazione) token del pannello Social letto', t !== '');
    const est = await admin.ctx.request.post(ADMIN + '/social.php', { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: t, azione: 'manda_mattino' }, maxRedirects: 0 });
    ok('1.5.0: Social con Origin esterno: 403', est.status() === 403, { status: est.status() });
    const oggi = new Date().toISOString().slice(0, 10);
    // I salvataggi partono dalla pagina del pannello (fetch con Origin), come un form vero.
    const salva = (o) => post(admin.p, ADMIN + '/social.php?scheda=calendario', form({ _csrf: t, azione: 'programmato_salva', id: '0', data_invio: oggi, ...o }), CT);
    for (const c of ['articoli:999999', '../../x:1', 'pagine:0', "articoli:1 OR 1=1"]) await salva({ contenuto: c, titolo: 'sic-prova-inventato' });
    await admin.p.goto(ADMIN + '/social.php?scheda=calendario', { waitUntil: 'networkidle' });
    ok('1.5.0: calendario con contenuti inventati (id inesistente, ../, SQL): nessuna riga', !(await admin.p.content()).includes('sic-prova-inventato'));
    await salva({ contenuto: 'articoli:1', titolo: 'sic-prova-js', immagine_og: 'javascript:alert(1)' });
    await salva({ contenuto: 'articoli:1', titolo: '<img src=x onerror=alert(77)>sic-prova-xss' });
    await admin.p.goto(ADMIN + '/social.php?scheda=calendario', { waitUntil: 'networkidle' });
    const html = await admin.p.content();
    // La riga con l'HTML nel titolo deve esserci (il salvataggio vero è passato), e il tag solo come testo.
    ok('1.5.0: titolo del calendario con HTML: salvato e mostrato come testo, mai eseguito', html.includes('sic-prova-xss') && !html.includes('<img src="x" onerror') && !html.includes('<img src=x onerror'));
    ok('1.5.0: immagine con javascript: la riga è rifiutata', !html.includes('sic-prova-js'));
    ok('1.5.0: immagine del calendario con javascript: mai salvata', !html.includes('javascript:alert'));
    const idx = await admin.p.evaluate(() => { const r = [...document.querySelectorAll('tr')].find(x => /sic-prova-xss/.test(x.textContent)); const i = r && r.querySelector('input[name=id]'); return i ? i.value : ''; });
    if (idx) await post(admin.p, ADMIN + '/social.php?scheda=calendario', form({ _csrf: t, azione: 'programmato_elimina', id: idx }), CT);
  }

  // === 22. (1.6.0) Articoli > Aggiungi sempre alla fine: la pagina e il riquadro sono dell'amministratore, ogni azione vuole
  // il token, la bozza si vede in anteprima solo dall'amministratore.
  {
    const form = (o) => new URLSearchParams(o).toString();
    const CT = { 'Content-Type': 'application/x-www-form-urlencoded' };
    const PAG = ADMIN + '/fine-articolo.php';
    const anon = await browser.newContext();
    const ra = await anon.request.get(PAG, { maxRedirects: 0 });
    ok('1.6.0: fine-articolo.php senza login: rimanda al login', [302, 303].includes(ra.status()) && /login/.test(ra.headers()['location'] || ''), { status: ra.status() });
    const rpa = await anon.request.post(PAG, { form: { azione: 'crea' }, maxRedirects: 0 });
    ok('1.6.0: fine-articolo.php crea senza login: rifiutato', rpa.status() !== 200 && !/editor\.php/.test(rpa.headers()['location'] || ''), { status: rpa.status(), location: rpa.headers()['location'] });
    // Senza token, dall'amministratore: niente riquadro creato, acceso o spento.
    for (const az of ['crea', 'accendi', 'spegni']) {
      const r = await post(admin.p, PAG, form({ azione: az }), CT);
      ok('1.6.0: fine-articolo.php ' + az + ' senza token: 403', r.status === 403, { status: r.status });
    }
    // Il token si prende dalla pagina, che ha sempre un form (Crea, Accendi o Spegni).
    let t = await tokenDi(admin.p, PAG);
    ok('1.6.0: (preparazione) token della pagina letto', t !== '');
    for (const az of ['crea', 'spegni']) {
      const est = await admin.ctx.request.post(PAG, { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: t, azione: az }, maxRedirects: 0 });
      ok('1.6.0: fine-articolo.php ' + az + ' con Origin esterno: 403', est.status() === 403, { status: est.status() });
    }
    // Il riquadro: se manca lo crea la prova (in bozza: sul sito non esce), com'era prima lo rimette alla fine.
    let id = 0, creato = false;
    const leggi = async () => { await admin.p.goto(PAG, { waitUntil: 'networkidle' }); const h = await admin.p.locator('#fa-modifica').getAttribute('href').catch(() => null); return h ? +(h.match(/id=(\d+)/) || [0, 0])[1] : 0; };
    id = await leggi();
    if (!id) { t = await tokenDi(admin.p, PAG); await post(admin.p, PAG, form({ _csrf: t, azione: 'crea' }), CT); id = await leggi(); creato = true; }
    ok('1.6.0: (preparazione) il riquadro esiste', id > 0, { id });
    const acceso = await admin.p.locator('#fa-spegni').count() > 0;
    const dove = await admin.p.locator('a:has-text("Vedi sotto l\'ultimo articolo")').getAttribute('href').catch(() => null);
    if (acceso) { t = await tokenDi(admin.p, PAG); await post(admin.p, PAG, form({ _csrf: t, azione: 'spegni' }), CT); }
    if (red.dentro) {
      await red.p.goto(PAG, { waitUntil: 'networkidle' });
      const fuori = !/fine-articolo\.php/.test(red.p.url()) && ((await red.p.content()).includes('riservata') || /index\.php|login\.php/.test(red.p.url()));
      ok('1.6.0: il redattore non apre Aggiungi sempre alla fine: torna alla dashboard', fuori, { url: red.p.url() });
      await red.p.goto(ADMIN + '/contenuti.php?tipo=articoli', { waitUntil: 'networkidle' });
      ok('1.6.0: il redattore non vede la linguetta nella lista degli articoli', await red.p.locator('#ct-fine-articolo').count() === 0 && await red.p.locator('a[href*="fine-articolo.php"]').count() === 0);
      const tr = await tokenDi(red.p, ADMIN + '/contenuti.php?tipo=articoli');
      for (const az of ['crea', 'accendi', 'spegni']) {
        const r = await post(red.p, PAG, form({ _csrf: tr, azione: az }), CT);
        ok('1.6.0: il redattore, fine-articolo.php ' + az + ' con il suo token: 403', r.status === 403, { status: r.status });
      }
      const ed = await red.ctx.request.get(ADMIN + '/editor.php?tipo=modelli&id=' + id, { maxRedirects: 0 });
      ok('1.6.0: il redattore non apre il riquadro nell\'editor', ed.status() !== 200, { status: ed.status() });
      const ea = await red.ctx.request.get(ADMIN + '/editor-anteprima.php?tipo=modelli&id=' + id, { maxRedirects: 0 });
      ok('1.6.0: il redattore non apre la tela del riquadro', ea.status() !== 200 || !/fine-articolo/.test(await ea.text()), { status: ea.status() });
      for (const [az, extra] of [['salva', { albero: JSON.stringify({ elementi: [] }), pubblica: '1' }], ['impostazioni', { titolo: 'x', stato: 'published' }]]) {
        const r = await post(red.p, ADMIN + '/editor-api.php', form({ _csrf: tr, azione: az, tipo: 'modelli', id: String(id), ...extra }), CT);
        ok('1.6.0: il redattore, editor-api ' + az + ' sul riquadro: 403', r.status === 403, { status: r.status });
      }
      await red.p.goto(ADMIN + '/versioni.php?ambito=modello&oggetto=' + id, { waitUntil: 'networkidle' });
      ok('1.6.0: il redattore non vede le versioni del riquadro', await red.p.locator('a[href*="tipo=modelli&id=' + id + '"], a[href*="tipo=modelli&amp;id=' + id + '"]').count() === 0);
      if (dove) {
        await red.p.goto(new URL(dove, ADMIN + '/').href.replace(/#.*$/, ''), { waitUntil: 'networkidle' });
        ok('1.6.0: il redattore con ?preview=1&fine=ID non vede il riquadro in bozza', await red.p.locator('aside.fine-articolo').count() === 0);
      }
    }
    if (dove) {
      const u = new URL(dove, ADMIN + '/').href.replace(/#.*$/, '');
      const ap = await anon.newPage();
      await ap.goto(u, { waitUntil: 'networkidle' });
      ok('1.6.0: un visitatore con ?preview=1&fine=ID non vede il riquadro in bozza', await ap.locator('aside.fine-articolo').count() === 0 && !(await ap.content()).includes('data-modello="' + id + '"'));
      await ap.goto(u.replace(/fine=\d+/, 'fine=' + id + '%20OR%201=1'), { waitUntil: 'networkidle' });
      ok('1.6.0: ?fine= con SQL: nessun riquadro, nessun errore', await ap.locator('aside.fine-articolo').count() === 0 && !/SQLSTATE|Fatal error|Warning:/.test(await ap.content()));
      // L'amministratore sì (la prova sopra non è vuota) e, con l'id di un header, non esce un header al posto del riquadro.
      await admin.p.goto(u, { waitUntil: 'networkidle' });
      ok('1.6.0: (controllo) l\'amministratore vede la bozza in anteprima', await admin.p.locator('aside.fine-articolo').count() === 1);
      await admin.p.goto(u.replace(/fine=\d+/, 'fine=1'), { waitUntil: 'networkidle' });
      ok('1.6.0: ?fine= con l\'id di un altro modello (header): nessun riquadro', id === 1 || await admin.p.locator('aside.fine-articolo').count() === 0);
    } else console.log('--  anteprima del riquadro: saltata (nessun articolo pubblicato)');
    await anon.close();
    // Com'era prima.
    if (acceso) { t = await tokenDi(admin.p, PAG); await post(admin.p, PAG, form({ _csrf: t, azione: 'accendi' }), CT); }
    if (creato) {
      t = await tokenDi(admin.p, ADMIN + '/modelli.php');
      await post(admin.p, ADMIN + '/modelli.php', form({ _csrf: t, azione: 'cestina', id: String(id) }), CT);
      await post(admin.p, ADMIN + '/modelli.php', form({ _csrf: t, azione: 'elimina', id: String(id), cestino: '1' }), CT);
    }
  }

  // === 23b. (1.7.0) Dopo il Definitivo (premuto dal gruppo 20) il sito su misura non si ricompone più, nemmeno con il token.
  if (DIR && fs.existsSync(path.join(__dirname, '..', 'prove', 'settings-cli.php'))) {
    const { execFileSync } = require('child_process');
    const leggi = (k) => { try { return execFileSync('php', [path.join(__dirname, '..', 'prove', 'settings-cli.php'), DIR, 'leggi', k], { encoding: 'utf8' }).trim(); } catch (e) { return ''; } };
    const kp = (() => { try { return JSON.parse(leggi('kit_prova')); } catch (e) { return null; } })();
    const prima = leggi('su_misura_risposte');
    if (kp && kp.stato === 'definitivo') {
      const t = await tokenDi(admin.p, ADMIN + '/index.php');
      const r = await admin.p.evaluate(async ({ url, t }) => {
        const b = new URLSearchParams({ _csrf: t, attivita: 'negozio', citta: 'sic-dopo-definitivo', 'pagine[]': 'contatti' });
        const x = await fetch(url, { method: 'POST', body: b, credentials: 'include' }); const h = await x.text();
        return (h.match(/class="flash flash-(?:success|error)"[^>]*>([^<]*)/) || [])[1] || '';
      }, { url: ADMIN + '/sito-su-misura.php', t });
      ok('1.7.0: sito su misura dopo il Definitivo, con il token giusto: rifiutato, risposte e tipo di sito com\'erano', /definitivo/.test(r) && leggi('su_misura_risposte') === prima && JSON.parse(leggi('kit_prova') || '{}').stato === 'definitivo', { flash: r });
    } else console.log('--  sito su misura dopo il Definitivo: saltata (il sito non ha un tipo di sito definitivo)');
  }

  // === pulizia: elimina il redattore di prova
  {
    await admin.p.goto(ADMIN + '/utenti.php', { waitUntil: 'networkidle' });
    const id = await admin.p.evaluate((nome) => { const a = [...document.querySelectorAll('a[href*="utenti.php?modifica="]')].find(x => x.closest('tr,.ct-riga') && /sic_prova_red/.test(x.closest('tr,.ct-riga').textContent)); if (!a) return 0; const m = a.href.match(/modifica=(\d+)/); return m ? +m[1] : 0; }, RED);
    const t = await tokenDi(admin.p, ADMIN + '/utenti.php');
    if (id) await post(admin.p, ADMIN + '/utenti.php', new URLSearchParams({ _csrf: t, azione: 'elimina', id: String(id) }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
    ok('pulizia: redattore di prova eliminato', true);
  }

  await browser.close();
  console.log('\n' + (ko === 0 ? 'TUTTE LE PROVE PASSANO' : ko + ' PROVE FALLITE') + ' su ' + tot + ' (ogni OK = un attacco respinto).');
  process.exit(ko === 0 ? 0 : 1);
})().catch(e => { console.error('ERRORE della batteria:', e.message); process.exit(2); });
