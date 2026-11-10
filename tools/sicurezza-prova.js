// Prove di attacco al CMS. Ogni prova LANCIA un attacco e passa (OK) solo se l'attacco FALLISCE.
// Da rilanciare a ogni versione, sul sito di prova locale (mai sul sito vivo).
//
//   node tools/sicurezza-prova.js <base> <utente> <password> [prefisso-pannello]
//   esempio:  node tools/sicurezza-prova.js http://127.0.0.1:8098 danilo prova-locale-12 /new
//
// Serve Playwright (require('playwright')) e un pannello raggiungibile. Crea un utente redattore "sic_prova_red"
// e lo cancella alla fine; se qualcosa resta, lo toglie al giro dopo. Non lascia contenuti pubblicati.
const { chromium } = require('playwright');
const pnConferme = require('../prove/conferme-pannello'); // UX 2026-11: conferme del pannello

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
        'admin/seo-tabella.php', // 2.29.0: la Tabella SEO (contenuti, non configurazione)
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
      const vietate = voci.filter(h => h && !/^(index|contenuti|contenuto|editor|media|categories|versioni|account|password|guida-infografiche|seo-tabella)\.php/.test(h) && !/^\.\.\//.test(h)); // 2.29.0: seo-tabella.php
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
    // 1.7.0: una GIF finta (intestazione GIF89a, poi PHP) che GD non riscrive: prima restava in uploads.
    const gifB64 = Buffer.from('GIF89a<?php echo "pwned"; ?>').toString('base64');
    const gif = await suMedia(red.p, 'finta.gif', 'image/gif', gifB64).catch(() => ({ status: 0, ok: false }));
    ok('upload: una GIF finta con il PHP dentro viene rifiutata', gif.ok === false, gif);
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
    if (esito.url) st = await admin.p.evaluate(async (u) => (await fetch(u, { credentials: 'include' })).status, (esito.url.startsWith('http') ? '' : new URL(BASE).origin) + esito.url);
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
    const phpCli = (codice) => { try { return execFileSync('php', ['-d', 'display_errors=stderr', '-r', 'require ' + JSON.stringify(path.join(DIR, 'includes', 'sito-su-misura.php')) + '; ' + codice], { encoding: 'utf8', timeout: 20000, stdio: ['ignore', 'pipe', 'pipe'] }); } catch (e) { return 'ERRORE ' + (e.stderr || e.message).slice(0, 200); } };
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
      // Una GIF finta con il PHP dentro (GD non la sa riscrivere): fino alla 1.7.0 restava in uploads e la foto finta spariva.
      const gif = { nome: 'sic-sm-prova.png', tipo: 'image/png', b64: Buffer.from('GIF89a<?php echo "sic-sm"; ?>').toString('base64') };
      const rg = await manda(admin.p, { _csrf: tm, azione: 'sostituisci', segnaposto: finta }, gif);
      ok('1.7.0: Sostituisci con una GIF finta con il PHP dentro: rifiutata', /contiene codice/.test(rg.flash), rg);
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
    // Un articolo vero, dalla tendina del form (il cambio del tipo di sito, gruppo 23, rifà gli articoli del kit con id nuovi).
    await admin.p.goto(ADMIN + '/social.php?scheda=calendario&nuovo=1', { waitUntil: 'networkidle' });
    const art = await admin.p.evaluate(() => ([...document.querySelectorAll('select[name=contenuto] option')].map(o => o.value).find(v => /^articoli:\d+$/.test(v))) || 'articoli:1');
    await salva({ contenuto: art, titolo: 'sic-prova-js', immagine_og: 'javascript:alert(1)' });
    await salva({ contenuto: art, titolo: '<img src=x onerror=alert(77)>sic-prova-xss' });
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

  // === 24. (1.8.0) La frase in fondo al sito: la cambia solo l'amministratore, con il token; il testo non diventa codice.
  {
    const form = (o) => new URLSearchParams(o).toString();
    const CT = { 'Content-Type': 'application/x-www-form-urlencoded' };
    const PAG = ADMIN + '/impostazioni.php';
    const cattiva = { action: 'save_firma', firma_mostra: '1', firma_testo: 'sic-firma-<script>alert(1)</script>', firma_url: 'javascript:alert(1)' };
    const sitoFirma = async () => { const r = await admin.ctx.request.get(BASE + '/'); const h = await r.text(); return (h.match(/<p class="firma-sito"[^]*?<\/p>/) || [''])[0]; };
    const prima = await sitoFirma();
    const anon = await browser.newContext();
    const ra = await anon.request.post(PAG, { form: cattiva, maxRedirects: 0 });
    ok('1.8.0: frase in fondo al sito senza login: rifiutata', ra.status() !== 200 && !(await sitoFirma()).includes('sic-firma'), { status: ra.status() });
    await anon.close();
    const r1 = await post(admin.p, PAG, form(cattiva), CT);
    ok('1.8.0: frase in fondo al sito senza token: 403', r1.status === 403 && !(await sitoFirma()).includes('sic-firma'), { status: r1.status });
    const tk = await tokenDi(admin.p, PAG);
    const est = await admin.ctx.request.post(PAG, { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: tk, ...cattiva }, maxRedirects: 0 });
    ok('1.8.0: frase in fondo al sito con Origin esterno: 403', est.status() === 403 && !(await sitoFirma()).includes('sic-firma'), { status: est.status() });
    if (red.dentro) {
      const tr = await tokenDi(red.p, ADMIN + '/media.php');
      const rr = await post(red.p, PAG, form({ _csrf: tr, ...cattiva }), CT);
      ok('1.8.0: il redattore non cambia la frase in fondo al sito', rr.status !== 200 || !/salvata/.test(rr.body), { status: rr.status });
      ok('1.8.0: (controllo) dopo il redattore la frase è com\'era', !(await sitoFirma()).includes('sic-firma'));
    }
    // L'amministratore con il token: il testo con lo script esce come testo, l'indirizzo javascript: non passa.
    const r2 = await post(admin.p, PAG, form({ _csrf: await tokenDi(admin.p, PAG), ...cattiva }), CT);
    const dopo = await sitoFirma();
    ok('1.8.0: frase con <script> e javascript: dall\'amministratore: niente codice sul sito', r2.status < 400 && dopo.includes('sic-firma') && !/<script|javascript:/i.test(dopo), { dopo: dopo.slice(0, 200) });
    // Com'era: dai valori di partenza, o quelli letti prima.
    const m = prima.match(/href="([^"]*)"[^>]*>([^<]*)</);
    await post(admin.p, PAG, form({ _csrf: await tokenDi(admin.p, PAG), action: 'save_firma', firma_mostra: prima ? '1' : '', firma_testo: m ? m[2] : (prima.replace(/<[^>]+>/g, '') || 'Un nuovo sito plumeCMS'), firma_url: m ? m[1] : '' }), CT);
    ok('1.8.0: (pulizia) la frase torna com\'era', (await sitoFirma()) === prima, { prima: prima.slice(0, 120) });
  }

  // === 25. (1.9.0) A chi va l'avviso di un form: lo sceglie solo l'amministratore, con il token; niente intestazioni nascoste.
  {
    const form = (o) => new URLSearchParams(o).toString();
    const CT = { 'Content-Type': 'application/x-www-form-urlencoded' };
    await admin.p.goto(ADMIN + '/form.php', { waitUntil: 'networkidle' });
    const link = await admin.p.locator('a.ct-nome').first().getAttribute('href').catch(() => null);
    const fid = link ? +(link.match(/id=(\d+)/) || [0, 0])[1] : 0;
    if (fid) {
      const PAG = ADMIN + '/form.php?id=' + fid + '&passo=3'; // 2.16.0: "Manda l'avviso a" sta nel passo Messaggi
      const leggi = async () => { await admin.p.goto(PAG, { waitUntil: 'networkidle' }); return admin.p.inputValue('#fm-avviso-a'); };
      const prima = await leggi();
      // Tutti i campi del form come sono, con un indirizzo diverso: quello che manderebbe chi attacca.
      const campiDi = async (avviso) => admin.p.evaluate((avviso) => { const f = document.getElementById('fm-form'); const cj = f.querySelector('#fm-campi-json'); if (cj) cj.value = JSON.stringify(window.fmCampi || []); const d = new FormData(f); d.set('avviso_a', avviso); d.delete('azione'); return [...d.entries()].filter(([, v]) => typeof v === 'string'); }, avviso);
      const corpo = async (avviso, senzaToken) => { const c = await campiDi(avviso); return form(Object.fromEntries(c.filter(([k]) => !(senzaToken && k === '_csrf')))); };
      const r1 = await post(admin.p, ADMIN + '/form.php', await corpo('spia@cattivo.it', true), CT);
      ok('1.9.0: indirizzo dell\'avviso senza token: 403', r1.status === 403 && (await leggi()) === prima, { status: r1.status });
      const tk = await tokenDi(admin.p, PAG);
      const est = await admin.ctx.request.post(ADMIN + '/form.php', { headers: { 'Origin': 'https://evil.example', 'Content-Type': CT['Content-Type'] }, data: (await corpo('spia@cattivo.it', false)).replace(/_csrf=[^&]*/, '_csrf=' + tk), maxRedirects: 0 });
      ok('1.9.0: indirizzo dell\'avviso con Origin esterno: 403', est.status() === 403 && (await leggi()) === prima, { status: est.status() });
      if (red.dentro) {
        const tr = await tokenDi(red.p, ADMIN + '/media.php');
        const rr = await post(red.p, ADMIN + '/form.php', (await corpo('spia@cattivo.it', false)).replace(/_csrf=[^&]*/, '_csrf=' + tr), CT);
        ok('1.9.0: il redattore non cambia a chi va l\'avviso', (rr.status !== 200 || !/salvato/.test(rr.body)) && (await leggi()) === prima, { status: rr.status });
      }
      for (const [nome, v] of [['un a capo con Bcc', 'a@x.it\r\nBcc: spia@cattivo.it'], ['un indirizzo con le virgolette', '"spia"@x.it<script>'], ['sei indirizzi', 'a@x.it,b@x.it,c@x.it,d@x.it,e@x.it,f@x.it']]) {
        await post(admin.p, ADMIN + '/form.php', await corpo(v, false), CT);
        ok('1.9.0: avviso a ' + nome + ': rifiutato, l\'indirizzo resta com\'era', (await leggi()) === prima);
      }
    } else ok('1.9.0: avviso dei form: attacchi saltati (nessun form)', true);
  }

  // === 26. (1.10.0) I sette widget nuovi con testo cattivo in ogni campo (prove/widget-cattivi.php: passano da editor_valida
  // come dall'editor vero): niente attributi on*, niente script tranne i dati per Google (JSON valido), niente link javascript: o data:.
  if (DIR && fs.existsSync(path.join(__dirname, '..', 'prove', 'widget-cattivi.php'))) {
    const { execFileSync } = require('child_process');
    let html = '';
    try { html = execFileSync('php', [path.join(__dirname, '..', 'prove', 'widget-cattivi.php'), DIR], { encoding: 'utf8' }); } catch (e) { html = ''; }
    ok('1.10.0: (preparazione) i sette widget disegnati con i valori cattivi', html.length > 2000 && ['orari', 'barra_contatti', 'recensioni', 'squadra', 'slider', 'eventi'].every(x => html.includes('ed-w-' + x)), { lunghezza: html.length });
    const anon = await browser.newContext({ javaScriptEnabled: false });
    const pg = await anon.newPage();
    await pg.setContent('<!doctype html><body>' + html + '</body>');
    const esame = await pg.evaluate(() => {
      const tutti = [...document.querySelectorAll('*')];
      return {
        on: tutti.filter(e => [...e.attributes].some(a => /^on/i.test(a.name))).map(e => e.outerHTML.slice(0, 80)),
        script: [...document.scripts].map(s => ({ tipo: s.type, json: (() => { try { JSON.parse(s.textContent); return true; } catch (e) { return false; } })() })),
        link: tutti.flatMap(e => ['href', 'src', 'action', 'formaction'].map(n => e.getAttribute(n)).filter(Boolean)).filter(u => /^\s*(javascript|data|vbscript):/i.test(u)),
        testo: document.body.innerText.includes('<script>alert(1)</script>'),
      };
    });
    ok('1.10.0: widget nuovi, nessun attributo on* nell\'HTML', esame.on.length === 0, esame.on);
    ok('1.10.0: widget nuovi, solo script di dati per Google, con JSON valido', esame.script.every(s => s.tipo === 'application/ld+json' && s.json), esame.script);
    ok('1.10.0: widget nuovi, nessun link o immagine javascript: o data:', esame.link.length === 0, esame.link);
    ok('1.10.0: widget nuovi, il testo cattivo si legge come testo', esame.testo);
    await anon.close();
  } else console.log('--  widget nuovi con testo cattivo: saltata (serve CMS_DIR)');

  // === 26b. (2.25.0) Il widget Scorri di lato con valori cattivi in ogni campo (prove/scorri-cattivi.php: passano da editor_valida
  // come dall'editor vero, e la pagina si salva): niente attributi on*, niente script, niente link o immagini javascript: o data:,
  // nessuna immagine esterna, al massimo 12 pagine per widget, CSS senza codice, disposizioni solo quelle note, 4 byte salvati.
  if (DIR && fs.existsSync(path.join(__dirname, '..', 'prove', 'scorri-cattivi.php'))) {
    const { execFileSync } = require('child_process');
    let html = '';
    try { html = execFileSync('php', [path.join(__dirname, '..', 'prove', 'scorri-cattivi.php'), DIR], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }); } catch (e) { html = ''; }
    ok('2.25.0: (preparazione) sette Scorri di lato disegnati con 5000 pagine cattive ciascuno', html.length > 2000 && (html.match(/class="sdl sdl-d-/g) || []).length === 7, { lunghezza: html.length });
    const anon = await browser.newContext({ javaScriptEnabled: false });
    const pg = await anon.newPage();
    await pg.setContent('<!doctype html><head><meta charset="utf-8"></head><body>' + html + '</body>');
    const esame = await pg.evaluate(() => {
      const tutti = [...document.querySelectorAll('*')];
      return {
        on: tutti.filter(e => [...e.attributes].some(a => /^on/i.test(a.name))).map(e => e.outerHTML.slice(0, 80)),
        script: document.scripts.length,
        link: tutti.flatMap(e => ['href', 'src', 'action', 'formaction', 'srcset'].map(n => e.getAttribute(n)).filter(Boolean)).filter(u => /^\s*(javascript|data|vbscript):/i.test(u)),
        esterne: [...document.images].map(i => i.getAttribute('src')).filter(u => !/^\/[^/]/.test(u) || u.includes('..')),
        pagine: [...document.querySelectorAll('.sdl')].map(r => r.querySelectorAll('.sdl-pagina').length),
        disp: [...document.querySelectorAll('.sdl')].map(r => r.getAttribute('data-sdl')),
        vel: [...document.querySelectorAll('.sdl')].map(r => r.getAttribute('data-sdl-vel')),
        stile: [...document.querySelectorAll('style')].map(s => s.textContent).join(''),
        testo: document.body.innerText.includes('<script>alert(1)</script>'),
      };
    });
    ok('2.25.0: Scorri di lato, nessun attributo on* e nessuno script', esame.on.length === 0 && esame.script === 0, esame.on);
    ok('2.25.0: Scorri di lato, nessun link o immagine javascript: o data:', esame.link.length === 0, esame.link);
    ok('2.25.0: Scorri di lato, nessuna immagine esterna (solo percorsi del sito, senza ..)', esame.esterne.length === 0, esame.esterne);
    ok('2.25.0: Scorri di lato, al massimo 12 pagine per widget su 5000 mandate', esame.pagine.every(x => x === 12), esame.pagine);
    ok('2.25.0: Scorri di lato, solo disposizioni e velocità note (una inventata diventa la Pellicola)', esame.disp.every(d => ['pellicola', 'carte', 'fermo', 'tempo', 'profondita'].includes(d)) && esame.vel.every(v => ['0.6', '1', '1.6'].includes(v)), [esame.disp, esame.vel]);
    ok('2.25.0: Scorri di lato, il CSS non porta codice (colori con </style> scartati)', !/<\/style|<script|alert\(/i.test(esame.stile), esame.stile.slice(0, 200));
    ok('2.25.0: Scorri di lato, il testo cattivo si legge come testo', esame.testo);
    await anon.close();
    const sito = await browser.newContext();
    const r = await sito.request.get(BASE + '/scorri-cattivi/');
    const t = await r.text();
    ok('2.25.0: Scorri di lato salvato con le emoji (4 byte) e le 5000 pagine: la pagina pubblicata risponde 200 e le emoji ci sono', r.status() === 200 && t.includes('\u{1F600}') && (t.match(/class="sdl-pagina /g) || []).length === 84 && !/<img src=x onerror/i.test(t), { s: r.status(), pagine: (t.match(/class="sdl-pagina /g) || []).length });
    await sito.close();
  } else console.log('--  Scorri di lato con valori cattivi: saltata (serve CMS_DIR)');

  // === 27. (2.0.0) File riservati e file dato dai form: senza la chiave firmata il file non esce, la cartella non si legge
  // dal web, le chiavi manomesse o scadute non valgono, il redattore e le richieste senza token non cambiano niente.
  if (DIR && fs.existsSync(path.join(__dirname, '..', 'prove', 'omaggio-cli.php'))) {
    const { execFileSync } = require('child_process');
    const cli = (...a) => { try { return execFileSync('php', [path.join(__dirname, '..', 'prove', 'omaggio-cli.php'), DIR, ...a], { encoding: 'utf8' }).trim(); } catch (e) { return ''; } };
    const os = require('os');
    const pdf = path.join(os.tmpdir(), 'sic-riservato.pdf');
    fs.writeFileSync(pdf, '%PDF-1.4\n%segreto-di-prova\n%%EOF\n');
    const PAG = ADMIN + '/scaricabili.php';
    await admin.p.goto(PAG, { waitUntil: 'networkidle' });
    await admin.p.setInputFiles('#dl-carica input[type=file]', pdf);
    await admin.p.check('#dl-riservato');
    await Promise.all([admin.p.waitForNavigation(), admin.p.click('#dl-carica button[type=submit]')]);
    const anon = await browser.newContext();
    const g = async (u) => { const r = await anon.request.get(u, { maxRedirects: 0 }); return { s: r.status(), t: (await r.text()).slice(0, 3000) }; };
    const pub = await g(BASE + '/scarica/sic-riservato/');
    ok('2.0.0: file riservato dall\'indirizzo pubblico: 404, il contenuto non esce', pub.s === 404 && !pub.t.includes('segreto-di-prova'), pub.s);
    const cart = cli('sic-riservato', '0', 'cartella');
    const dir = await g(BASE + PAN.replace(/\/$/, '') + '/uploads/' + cart + '/sic-riservato.pdf');
    ok('2.0.0: la cartella dei riservati non si legge dal web, anche sapendo il nome', cart !== '' && dir.s >= 403 && !dir.t.includes('segreto-di-prova'), { cart: cart.slice(0, 12), s: dir.s });
    const buona = cli('sic-riservato', '424242', 'valida');
    const [id, sc, fi] = buona.split('.');
    const v = await g(BASE + '/scarica/sic-riservato/?k=' + buona);
    ok('2.0.0: (preparazione) la chiave vera scarica il file', v.s === 200 && v.t.includes('segreto-di-prova'), v.s);
    const falsi = [
      ['firma inventata', id + '.' + sc + '.' + 'f'.repeat(32)], ['altro iscritto', (+id + 1) + '.' + sc + '.' + fi], ['scadenza allungata', id + '.' + (+sc + 999) + '.' + fi],
      ['firma maiuscola', id + '.' + sc + '.' + fi.toUpperCase()], ['chiave con SQL', id + "' OR '1'='1"], ['chiave lunghissima', '1.' + '9'.repeat(5000) + '.' + fi],
    ];
    for (const [nome, k] of falsi) { const r = await g(BASE + '/scarica/sic-riservato/?k=' + encodeURIComponent(k)); ok('2.0.0: chiave falsa (' + nome + '): il file non esce', r.s !== 200 && !r.t.includes('segreto-di-prova'), r.s); }
    const altro = await g(BASE + '/scarica/altro-file/?k=' + buona);
    ok('2.0.0: la chiave di un file non apre un altro file', altro.s === 404);
    for (const pz of ['..%2f..%2fincludes%2fconfig', '..%252f..%252fincludes', 'sic-riservato%00', 'sic-riservato.pdf/..']) {
      const r = await g(BASE + '/scarica/' + pz + '/?k=' + buona);
      ok('2.0.0: percorso cattivo (' + pz + '): niente file', r.s !== 200 || (!r.t.includes('segreto-di-prova') && !/DB_PASS|define\(/.test(r.t)), r.s);
    }
    const scad = cli('sic-riservato', id);
    const r410 = await g(BASE + '/scarica/sic-riservato/?k=' + scad);
    ok('2.0.0: chiave scaduta: 410 e il file non esce', r410.s === 410 && !r410.t.includes('segreto-di-prova'), r410.s);
    const gr = await g(BASE + '/grazie/?f=1&o=link&k=' + encodeURIComponent(id + '.' + sc + '.' + 'a'.repeat(32)));
    ok('2.0.0: pagina di ringraziamento con una chiave falsa: nessun link al file', !/\/scarica\/[^"]*\?k=/.test(gr.t));
    const gx = await g(BASE + '/grazie/?f=1&o=' + encodeURIComponent('"><script>alert(1)</script>') + '&k=' + encodeURIComponent('"><img src=x onerror=alert(1)>'));
    ok('2.0.0: pagina di ringraziamento con HTML nell\'indirizzo: non esce', !gx.t.includes('<script>alert(1)') && !gx.t.includes('onerror=alert'));
    await anon.close();
    // Il pannello: senza token, con Origin esterno, dal redattore.
    const form = (o) => new URLSearchParams(o).toString();
    const CT = { 'Content-Type': 'application/x-www-form-urlencoded' };
    const stato = async () => { await admin.p.goto(PAG, { waitUntil: 'networkidle' }); return (await admin.p.locator('body').innerText()).match(/Il link personale vale|riservat/gi)?.length + '|' + await admin.p.inputValue('#dl-giorni input[name=giorni]'); };
    const prima = await stato();
    const r1 = await post(admin.p, PAG, form({ azione: 'pubblico', nome: 'sic-riservato' }), CT);
    ok('2.0.0: "Rendi pubblico" senza token: 403', r1.status === 403 && (await g2(BASE + '/scarica/sic-riservato/')) === 404, r1.status);
    const r2 = await post(admin.p, PAG, form({ azione: 'giorni', giorni: '3650' }), CT);
    ok('2.0.0: validità dei link senza token: 403, non cambia', r2.status === 403 && (await stato()) === prima, r2.status);
    const tk = await tokenDi(admin.p, PAG);
    const est = await admin.ctx.request.post(PAG, { headers: { 'Origin': 'https://evil.example', ...CT }, data: form({ _csrf: tk, azione: 'pubblico', nome: 'sic-riservato' }), maxRedirects: 0 });
    ok('2.0.0: "Rendi pubblico" con Origin esterno: 403', est.status() === 403 && (await g2(BASE + '/scarica/sic-riservato/')) === 404, est.status());
    if (red.dentro) {
      const tr = await tokenDi(red.p, ADMIN + '/media.php');
      const rr = await post(red.p, PAG, form({ _csrf: tr, azione: 'pubblico', nome: 'sic-riservato' }), CT);
      ok('2.0.0: il redattore non rende pubblico un file riservato', (await g2(BASE + '/scarica/sic-riservato/')) === 404, rr.status);
      const rf = await post(red.p, ADMIN + '/form.php', form({ _csrf: tr, id: '1', nome: 'X', omaggio: 'sic-riservato', lista_id: '1' }), CT);
      ok('2.0.0: il redattore non mette un file in un form', !/salvato/.test(rf.body), rf.status);
    }
    const tk2 = await tokenDi(admin.p, PAG);
    await post(admin.p, PAG, form({ _csrf: tk2, azione: 'elimina', nome: 'sic-riservato' }), CT);
    ok('2.0.0: pulizia: file riservato di prova eliminato', (await admin.ctx.request.get(BASE + '/scarica/sic-riservato/?k=' + buona)).status() === 404);
    async function g2(u) { return (await admin.ctx.request.get(u, { maxRedirects: 0 })).status(); }
  } else console.log('--  file riservati: saltata (serve CMS_DIR)');

  // === 28. (2.7.0) L'email di conferma dell'iscrizione: la cambia solo l'amministratore, con il token; i testi non diventano
  // codice, l'oggetto non porta intestazioni nascoste, il colore non porta CSS; anteprima e prova solo per l'amministratore.
  {
    const form = (o) => new URLSearchParams(o).toString();
    const CT = { 'Content-Type': 'application/x-www-form-urlencoded' };
    const PAG = ADMIN + '/newsletter.php?scheda=impostazioni';
    const API = ADMIN + '/email-conferma-api.php';
    const cattiva = { azione: 'conferma_email', 'ce[oggetto]': 'sic-ec\r\nBcc: spia@cattivo.it', 'ce[testo]': 'sic-ec <script>alert(1)</script><b onclick="alert(2)">x</b> <a href="javascript:alert(3)">y</a>',
      'ce[colore]': 'red;background:url(javascript:alert(4))', 'ce[intestazione]': 'sic-ec <img src=x onerror=alert(5)>', 'ce[firma]': '<svg onload=alert(6)>', 'ce[nota]': 'sic-ec' };
    const leggi = async () => { await admin.p.goto(PAG, { waitUntil: 'networkidle' }); return admin.p.evaluate(() => [...document.querySelectorAll('#nl-conferma [name^="ce["]')].map(e => e.value).join('|')); };
    const prima = await leggi();
    const anon = await browser.newContext();
    const ra = await anon.request.post(ADMIN + '/newsletter.php', { form: cattiva, maxRedirects: 0 });
    const rapi = await anon.request.post(API, { form: { azione: 'anteprima', contesto: 'comuni' }, maxRedirects: 0 });
    ok('2.7.0: email di conferma senza login: rifiutata, anche l\'anteprima', ra.status() !== 200 && rapi.status() !== 200 && !(await leggi()).includes('sic-ec'), { status: ra.status(), api: rapi.status() });
    await anon.close();
    const r1 = await post(admin.p, ADMIN + '/newsletter.php', form(cattiva), CT);
    ok('2.7.0: email di conferma senza token: 403', r1.status === 403 && !(await leggi()).includes('sic-ec'), { status: r1.status });
    const tk = await tokenDi(admin.p, PAG);
    const est = await admin.ctx.request.post(ADMIN + '/newsletter.php', { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: tk, ...cattiva }, maxRedirects: 0 });
    ok('2.7.0: email di conferma con Origin esterno: 403', est.status() === 403 && !(await leggi()).includes('sic-ec'), { status: est.status() });
    const estApi = await admin.ctx.request.post(API, { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: tk, azione: 'prova', contesto: 'comuni' }, maxRedirects: 0 });
    ok('2.7.0: prova dell\'email con Origin esterno: 403', estApi.status() === 403, estApi.status());
    if (red.dentro) {
      const tr = await tokenDi(red.p, ADMIN + '/media.php');
      const rr = await post(red.p, ADMIN + '/newsletter.php', form({ _csrf: tr, ...cattiva }), CT);
      ok('2.7.0: il redattore non cambia l\'email di conferma', !(await leggi()).includes('sic-ec'), { status: rr.status });
      const rp = await post(red.p, API, form({ _csrf: tr, azione: 'prova', contesto: 'comuni' }), CT);
      ok('2.7.0: il redattore non vede l\'anteprima e non manda la prova (403)', rp.status === 403, rp.status);
      const rf = await post(red.p, ADMIN + '/form.php', form({ _csrf: tr, id: '1', nome: 'X', 'ce[oggetto]': 'sic-ec' }), CT);
      ok('2.7.0: il redattore non cambia l\'email di conferma di un form', !/salvato/.test(rf.body), rf.status);
    }
    // L'amministratore con il token: tutto pulito.
    await post(admin.p, ADMIN + '/newsletter.php', form({ _csrf: await tokenDi(admin.p, PAG), ...cattiva }), CT);
    await admin.p.goto(PAG, { waitUntil: 'networkidle' });
    const v = await admin.p.evaluate(() => Object.fromEntries([...document.querySelectorAll('#nl-conferma [name^="ce["]')].map(e => [e.name.slice(3, -1), e.value])));
    ok('2.7.0: oggetto senza a capo, colore con CSS scartato, testo senza script né eventi', /^sic-ec Bcc/.test(v.oggetto || '') && (v.colore || '') === '' && !/<script|onclick|javascript:/i.test(v.testo || ''), v);
    const an = await admin.ctx.request.post(API, { headers: { 'Origin': BASE.replace(/(https?:\/\/[^/]+).*/, '$1') }, form: { ...cattiva, _csrf: await tokenDi(admin.p, PAG), azione: 'anteprima', contesto: 'comuni' }, maxRedirects: 0 });
    const j = an.status() === 200 ? await an.json() : {};
    const h = String(j.html || '');
    ok('2.7.0: l\'email che ne esce non ha codice: niente script, eventi, javascript:, SVG; l\'oggetto è su una riga', h.includes('sic-ec') && !/<script|<svg|<img|<[^>]*\son[a-z]+=|javascript:/i.test(h) && !/[\r\n]/.test(String(j.oggetto || '')), { status: an.status(), ogg: j.oggetto });
    // Com'era: i campi vuoti (o quelli letti prima).
    const vuoti = Object.fromEntries(['oggetto', 'intestazione', 'titolo', 'testo', 'pulsante', 'colore', 'sotto', 'firma', 'nota'].map((k, i) => ['ce[' + k + ']', (prima.split('|')[i] || '')]));
    await post(admin.p, ADMIN + '/newsletter.php', form({ _csrf: await tokenDi(admin.p, PAG), azione: 'conferma_email', ...vuoti }), CT);
    ok('2.7.0: (pulizia) l\'email di conferma torna com\'era', (await leggi()) === prima);
  }

  // === 29. (2.8.0) Contatti, scheda del contatto, Attributi dei contatti: solo l'amministratore, solo con il token e dal
  // pannello; nomi, etichette, opzioni e valori non diventano codice; i filtri non arrivano all'SQL; il CSV non porta formule.
  {
    const form = (o) => new URLSearchParams(o).toString();
    const CT = { 'Content-Type': 'application/x-www-form-urlencoded' };
    const PC = ADMIN + '/newsletter-contatti.php', PK = ADMIN + '/newsletter-contatto.php', PA = ADMIN + '/newsletter-attributi.php';
    const attrPagina = async () => { await admin.p.goto(PA, { waitUntil: 'networkidle' }); return admin.p.content(); };
    const nuovoAttr = { azione: 'crea', tipo: 'text', nome: 'SIC_ATTR' };
    // Senza login, senza token, con Origin esterno
    const anon = await browser.newContext();
    const ra = await anon.request.post(PA, { form: nuovoAttr, maxRedirects: 0 });
    const rc = await anon.request.get(PC + '?scarica=tutti', { maxRedirects: 0 });
    ok('2.8.0: senza login niente attributi e niente CSV dei contatti', ra.status() !== 200 && rc.status() !== 200 && !(await attrPagina()).includes('SIC_ATTR') && !/text\/csv/.test(rc.headers()['content-type'] || ''), { a: ra.status(), c: rc.status() });
    await anon.close();
    const r1 = await post(admin.p, PA, form(nuovoAttr), CT);
    ok('2.8.0: attributo senza token: 403', r1.status === 403 && !(await attrPagina()).includes('SIC_ATTR'), r1.status);
    const tk = await tokenDi(admin.p, PC);
    const est = await admin.ctx.request.post(PK, { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: tk, azione: 'crea', 'a[EMAIL]': 'sic-origin@sic-prova.it' }, maxRedirects: 0 });
    await admin.p.goto(PC + '?cerca=sic-origin', { waitUntil: 'networkidle' });
    ok('2.8.0: Crea un contatto con Origin esterno: 403', est.status() === 403 && !(await admin.p.content()).includes('sic-origin@'), est.status());
    if (red.dentro) {
      const tr = await tokenDi(red.p, ADMIN + '/media.php');
      await post(red.p, PA, form({ _csrf: tr, ...nuovoAttr }), CT);
      await post(red.p, PK, form({ _csrf: tr, azione: 'crea', 'a[EMAIL]': 'sic-red@sic-prova.it' }), CT);
      await post(red.p, PC, form({ _csrf: tr, azione: 'gruppo', cosa: 'elimina', tutti: '1' }), CT);
      const vede = await red.ctx.request.get(PC + '?scarica=tutti', { maxRedirects: 0 });
      await admin.p.goto(PC + '?cerca=sic-red', { waitUntil: 'networkidle' });
      const nessuno = (await admin.p.content()).includes('Nessun contatto');
      ok('2.8.0: il redattore non crea attributi né contatti, non elimina tutti, non scarica il CSV', !(await attrPagina()).includes('SIC_ATTR') && nessuno && !/text\/csv/.test(vede.headers()['content-type'] || ''), vede.status());
    }
    // XSS negli attributi (nome, etichetta, opzioni), nei contatti, nelle liste e nelle cartelle
    const t2 = await tokenDi(admin.p, PA);
    const x = '<img src=x onerror="window.__sic29=1">';
    await post(admin.p, PA, form({ _csrf: t2, azione: 'crea', tipo: 'category', nome: 'SIC_CAT"><script>', etichetta: 'sic ' + x, opzioni: 'uno ' + x + '\ndue <script>window.__sic29=2</script>' }), CT);
    await post(admin.p, PA, form({ _csrf: t2, azione: 'crea', tipo: 'text', nome: 'SIC_TESTO', etichetta: 'sic testo' }), CT);
    const t3 = await tokenDi(admin.p, PC);
    await post(admin.p, PC, form({ _csrf: t3, azione: 'cartella_crea', nome: 'sic cartella ' + x }), CT);
    await post(admin.p, PC, form({ _csrf: t3, azione: 'lista_crea', nome: 'sic lista ' + x, descrizione: x }), CT);
    await post(admin.p, PK, form({ _csrf: t3, azione: 'crea', 'a[EMAIL]': 'sic-xss@sic-prova.it', 'a[NOME]': 'Sic ' + x, 'a[SIC_TESTO]': '=HYPERLINK("http://cattivo.it","clic") \u{1F600} ' + x }), CT);
    await admin.p.goto(PA, { waitUntil: 'networkidle' });
    const pagA = await admin.p.content();
    ok('2.8.0: il nome dell\'attributo con caratteri strani diventa SIC_CATSCRIPT (solo lettere, numeri, trattini bassi)', pagA.includes('SIC_CATSCRIPT') && !pagA.includes('SIC_CAT"'));
    await admin.p.goto(PC + '?cerca=sic-xss', { waitUntil: 'networkidle' });
    const idx = await admin.p.evaluate(() => { const a = document.querySelector('a[href*="newsletter-contatto.php?id="]'); return a ? +a.href.split('id=')[1] : 0; });
    const visti = [];
    for (const u of [PA, PC, PC + '?scheda=liste', PK + '?id=' + idx, PK + '?id=' + idx + '&scheda=liste', PK + '?id=' + idx + '&scheda=cronologia', PK + '?nuovo=1']) {
      await admin.p.goto(u, { waitUntil: 'networkidle' });
      if (await admin.p.evaluate(() => window.__sic29 !== undefined)) visti.push(u);
    }
    ok('2.8.0: etichette, opzioni, nomi di liste e cartelle e valori con HTML: solo testo in tutte le pagine', idx > 0 && visti.length === 0, visti);
    // Il valore con l'emoji si salva (tabella a 3 byte, entità) e torna come carattere vero
    await admin.p.goto(PK + '?id=' + idx, { waitUntil: 'networkidle' });
    const valore = await admin.p.inputValue('#nlk-sic-testo').catch(() => '');
    ok('2.8.0: un valore con un\'emoji si salva e torna intero', valore.includes('\u{1F600}') && valore.startsWith('=HYPERLINK'), valore.slice(0, 60));
    // CSV con le formule neutralizzate
    const t4 = await tokenDi(admin.p, PC + '?cerca=sic-xss');
    const csv = await admin.ctx.request.post(PC, { headers: { 'Origin': BASE.replace(/(https?:\/\/[^/]+).*/, '$1') }, form: { _csrf: t4, azione: 'gruppo', cosa: 'esporta', cerca: 'sic-xss', tutti: '1' }, maxRedirects: 0 });
    const testoCsv = await csv.text();
    ok('2.8.0: Esporta: le celle che cominciano con = escono con l\'apostrofo davanti', /text\/csv/.test(csv.headers()['content-type'] || '') && testoCsv.includes("'=HYPERLINK") && !/;=HYPERLINK/.test(testoCsv), testoCsv.split('\n')[1]);
    // Iniezioni nei filtri e nei nomi degli attributi
    const strani = ["x' OR '1'='1", "1 OR 1=1", "motivo:x') OR 1=1 -- ", "-1 UNION SELECT password FROM users"];
    let rotte = [];
    for (const s of strani) {
      const r = await admin.ctx.request.get(PC + '?cerca=' + encodeURIComponent(s) + '&stato=' + encodeURIComponent(s) + '&lista=' + encodeURIComponent(s) + '&per=' + encodeURIComponent(s) + '&pag=' + encodeURIComponent(s));
      const t = await r.text();
      if (r.status() !== 200 || /SQLSTATE|PDOException|Fatal error|sic-xss@/.test(t) || !/Nessun contatto/.test(t)) rotte.push([s, r.status()]);
    }
    ok('2.8.0: ricerca, stato, lista e pagina con SQL: nessun errore, nessun contatto in più (la ricerca è solo testo)', rotte.length === 0, rotte);
    const t5 = await tokenDi(admin.p, PK + '?id=' + idx);
    await post(admin.p, PK, form({ _csrf: t5, azione: 'salva', id: String(idx), "a[NOME' OR 1=1 -- ]": 'iniettato', 'a[EMAIL]': 'sic-xss@sic-prova.it' }), CT);
    await post(admin.p, PC, form({ _csrf: t5, azione: 'colonne', 'colonne[]': 'id) FROM users; --' }), CT);
    await admin.p.goto(PC + '?cerca=sic-xss', { waitUntil: 'networkidle' });
    const dopo = await admin.p.content();
    ok('2.8.0: un nome di attributo inventato nel modulo o nelle colonne si ignora', !dopo.includes('iniettato') && !/FROM users/.test(dopo) && !/SQLSTATE|Fatal/.test(dopo));
    const t6 = await tokenDi(admin.p, PA + '?scheda=calcolati');
    await post(admin.p, PA, form({ _csrf: t6, azione: 'crea', scheda: 'calcolati', nome: 'SIC_FORMULA', formula: "COUNT[READS];DELETE FROM users" }), CT);
    await post(admin.p, PA, form({ _csrf: t6, azione: 'crea', scheda: 'globali', nome: 'SIC_SOMMA', formula: "SUM[EMAIL]" }), CT);
    await admin.p.goto(PA + '?scheda=calcolati', { waitUntil: 'networkidle' });
    const calc = await admin.p.content();
    await admin.p.goto(PA + '?scheda=globali', { waitUntil: 'networkidle' });
    ok('2.8.0: formule con SQL o SUM su un testo: rifiutate', !calc.includes('SIC_FORMULA') && !(await admin.p.content()).includes('SIC_SOMMA'));
    // Pulizia: contatto, attributi, lista, cartella di prova
    const t7 = await tokenDi(admin.p, PK + '?id=' + idx);
    await post(admin.p, PK, form({ _csrf: t7, azione: 'elimina', id: String(idx) }), CT);
    await admin.p.goto(PA, { waitUntil: 'networkidle' });
    for (const n of ['SIC_CATSCRIPT', 'SIC_TESTO']) {
      const id = await admin.p.evaluate((n) => { const tr = [...document.querySelectorAll('tr')].find(r => r.querySelector('code') && r.querySelector('code').textContent === n); const i = tr && tr.querySelector('input[name=id]'); return i ? i.value : ''; }, n);
      if (id) await post(admin.p, PA, form({ _csrf: await tokenDi(admin.p, PA), azione: 'elimina', id }), CT);
    }
    await admin.p.goto(PC + '?scheda=liste', { waitUntil: 'networkidle' });
    const ids = await admin.p.evaluate(() => {
      const l = [...document.querySelectorAll('form')].filter(f => f.querySelector('input[name=azione][value=lista_elimina]') && /sic lista/.test(f.getAttribute('data-conferma') || '')).map(f => f.querySelector('input[name=id]').value);
      const c = [...document.querySelectorAll('form')].filter(f => f.querySelector('input[name=azione][value=cartella_elimina]') && /sic cartella/.test(f.getAttribute('data-conferma') || '')).map(f => f.querySelector('input[name=id]').value);
      return { l, c };
    });
    for (const id of ids.l) await post(admin.p, PC, form({ _csrf: await tokenDi(admin.p, PC + '?scheda=liste'), azione: 'lista_elimina', id }), CT);
    for (const id of ids.c) await post(admin.p, PC, form({ _csrf: await tokenDi(admin.p, PC + '?scheda=liste'), azione: 'cartella_elimina', id }), CT);
    ok('2.8.0: (pulizia) contatto, attributi, lista e cartella di prova tolti', !(await attrPagina()).includes('SIC_'));
  }

  // === 30. (2.9.0) Importazione, esportazione, lista dei test: solo l'amministratore, solo con il token e dal pannello; il
  // CSV esportato non porta formule; un XLSX con entità esterne, DTD, zip bomba o percorsi nel pacchetto non si legge; i file
  // dei contatti non si scaricano dal web.
  {
    const fs = require('fs'), path = require('path'), os = require('os'), { execFileSync } = require('child_process');
    const DIR30 = process.env.CMS_DIR || '';
    const PI = ADMIN + '/newsletter-importa.php', PC = ADMIN + '/newsletter-contatti.php', PN = ADMIN + '/newsletter.php';
    const ORIG = { 'Origin': BASE.replace(/(https?:\/\/[^/]+).*/, '$1') };
    const CT = { 'Content-Type': 'application/x-www-form-urlencoded' };
    const form = (o) => new URLSearchParams(o).toString();
    const carica = async (ctx, tk, campi, headers = ORIG) => ctx.request.post(PI, { headers, multipart: { _csrf: tk, azione: 'carica', ...campi }, maxRedirects: 0 });
    const idDa = (r) => { const m = (r.headers()['location'] || '').match(/id=(\d+)/); return m ? +m[1] : 0; };
    // Senza login
    const anon = await browser.newContext();
    const ra = await anon.request.get(PI, { maxRedirects: 0 });
    const rb = await anon.request.post(PI, { multipart: { azione: 'carica', modo: 'incolla', testo: 'EMAIL\nanon@sic-prova.it' }, maxRedirects: 0 });
    ok('2.9.0: senza login la pagina Importa non si apre e niente si carica', ra.status() !== 200 && rb.status() !== 200 && !/newsletter-importa\.php\?id=/.test(rb.headers()['location'] || ''), [ra.status(), rb.status()]);
    await anon.close();
    // Token mancante, Origin esterno, giro senza token
    const tk = await tokenDi(admin.p, PI);
    const r1 = await carica(admin.ctx, '', { modo: 'incolla', testo: 'EMAIL\nsenza-token@sic-prova.it' });
    const r2 = await carica(admin.ctx, tk, { modo: 'incolla', testo: 'EMAIL\norigine@sic-prova.it' }, { 'Origin': 'https://evil.example' });
    const r3 = await admin.ctx.request.post(PI, { headers: ORIG, form: { azione: 'giro', id: '1' }, maxRedirects: 0 });
    ok('2.9.0: Carica senza token o da un altro sito: 403; un giro senza token: 403', r1.status() === 403 && r2.status() === 403 && r3.status() === 403, [r1.status(), r2.status(), r3.status()]);
    const r4 = await admin.ctx.request.post(PC, { headers: ORIG, form: { azione: 'gruppo', cosa: 'esporta', tutti: '1' }, maxRedirects: 0 });
    const r5 = await admin.ctx.request.post(PN, { headers: ORIG, form: { azione: 'lista_test', indirizzi: 'csrf@sic-prova.it' }, maxRedirects: 0 });
    ok('2.9.0: Esporta e Lista dei test senza token: 403, niente CSV', r4.status() === 403 && !/text\/csv/.test(r4.headers()['content-type'] || '') && r5.status() === 403, [r4.status(), r5.status()]);
    // Il redattore: non vede e non salva
    if (red.dentro) {
      const tr = await tokenDi(red.p, ADMIN + '/media.php');
      const v1 = await red.ctx.request.get(PI, { maxRedirects: 0 });
      const v2 = await carica(red.ctx, tr, { modo: 'incolla', testo: 'EMAIL\nred@sic-prova.it' });
      const v3 = await red.ctx.request.post(PC, { headers: ORIG, form: { _csrf: tr, azione: 'gruppo', cosa: 'esporta', tutti: '1', esp_origine: 'scelti' }, maxRedirects: 0 });
      const v4 = await red.ctx.request.post(PN, { headers: ORIG, form: { _csrf: tr, azione: 'lista_test', indirizzi: 'red@sic-prova.it' }, maxRedirects: 0 });
      const v5 = await red.ctx.request.get(PC + '?esportazione=1', { maxRedirects: 0 });
      const v6 = await red.ctx.request.get(PC + '?scarica=tutti', { maxRedirects: 0 });
      await admin.p.goto(PN + '?scheda=impostazioni', { waitUntil: 'networkidle' });
      const lt = await admin.p.inputValue('#nl-test-indirizzi').catch(() => '');
      ok('2.9.0: il redattore non apre Importa, non carica, non esporta, non scarica, non cambia la lista dei test',
        v1.status() === 302 && !/newsletter-importa\.php\?id=/.test(v2.headers()['location'] || '') && !/text\/csv/.test((v3.headers()['content-type'] || '') + (v5.headers()['content-type'] || '') + (v6.headers()['content-type'] || '')) && !lt.includes('red@sic-prova.it'),
        [v1.status(), v2.headers()['location'], v3.status(), v4.status(), v5.status(), v6.status()]);
    }
    // XLSX cattivi (servono CMS_DIR e php per costruirli)
    if (DIR30 && fs.existsSync(path.join(DIR30, 'includes/newsletter-importa.php'))) {
      const cli = path.join(__dirname, '..', 'prove', 'importa-cli.php');
      const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'sic30-'));
      for (const [tipo, attesa] of [['xxe', /DTD/], ['dtd', /DTD/], ['bomba', /troppo grande/], ['percorsi', /nomi non ammessi/]]) {
        const f = path.join(tmp, tipo + '.xlsx');
        execFileSync('php', [cli, DIR30, 'xlsx', f, tipo], { encoding: 'utf8' });
        const t0 = Date.now();
        const r = await admin.ctx.request.post(PI, { headers: ORIG, multipart: { _csrf: await tokenDi(admin.p, PI), azione: 'carica', modo: 'file', file: { name: tipo + '.xlsx', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', buffer: fs.readFileSync(f) } }, maxRedirects: 0 });
        await admin.p.goto(PI, { waitUntil: 'networkidle' });
        const pag = await admin.p.content();
        ok(`2.9.0: XLSX ${tipo === 'xxe' ? 'con un\'entità esterna (file:///etc/passwd)' : tipo === 'dtd' ? 'con una DTD che si moltiplica' : tipo === 'bomba' ? 'zip bomba (100 MB di spazi)' : 'con ../ e / nei nomi interni'}: rifiutato, con il motivo`,
          !/id=\d+/.test(r.headers()['location'] || '') && attesa.test(pag) && !/root:x:0/.test(pag) && Date.now() - t0 < 30000, [r.headers()['location'], Date.now() - t0]);
      }
      ok('2.9.0: i percorsi del pacchetto XLSX non scrivono file fuori (niente percorso-cattivo.php)', !fs.existsSync(path.join(DIR30, 'percorso-cattivo.php')) && !fs.existsSync(path.join(DIR30, '..', 'percorso-cattivo.php')) && !fs.existsSync(path.join(DIR30, 'uploads', 'percorso-cattivo.php')));
      fs.rmSync(tmp, { recursive: true, force: true });
    } else console.log('--  XLSX cattivi: saltati (serve CMS_DIR)');
    // Formule: importate con Copia e incolla, poi esportate: escono con l'apostrofo davanti
    // Indirizzi nuovi a ogni giro: quelli di un giro prima sono stati cancellati con l'impronta e un'importazione li scarta (giusto).
    const g30 = Date.now().toString(36);
    const testo = `EMAIL;NOME;COGNOME\nformula1-${g30}@sic-prova.it;=HYPERLINK("http://cattivo.it","clic");+cmd|' /C calc'!A0\nformula2-${g30}@sic-prova.it;@SOMMA(1,2);-2+3*cmd|' /C calc'!A0\n`;
    const rc = await carica(admin.ctx, await tokenDi(admin.p, PI), { modo: 'incolla', testo });
    const id30 = idDa(rc);
    const tk30 = await tokenDi(admin.p, PI + '?id=' + id30);
    await admin.ctx.request.post(PI, { headers: ORIG, form: { _csrf: tk30, azione: 'abbina', id: String(id30), 'col[0]': 'EMAIL', 'col[1]': 'NOME', 'col[2]': 'COGNOME' }, maxRedirects: 0 });
    await admin.p.goto(PC + '?scheda=liste', { waitUntil: 'networkidle' });
    const lista = await admin.p.evaluate(() => { const a = document.querySelector('a[href*="newsletter-contatti.php?lista="]'); return a ? a.href.match(/lista=(\d+)/)[1] : ''; });
    await admin.ctx.request.post(PI, { headers: ORIG, form: { _csrf: tk30, azione: 'liste', id: String(id30), 'liste[]': lista }, maxRedirects: 0 });
    await admin.ctx.request.post(PI, { headers: ORIG, form: { _csrf: tk30, azione: 'conferma', id: String(id30), consenso: '1' }, maxRedirects: 0 });
    const csv = await admin.ctx.request.post(PC, { headers: ORIG, form: { _csrf: await tokenDi(admin.p, PC), azione: 'gruppo', cosa: 'esporta', cerca: 'formula', tutti: '1', esp_origine: 'scelti', esp_sep: ';' }, maxRedirects: 0 });
    const tc = (await csv.text()).replace(/^﻿/, '');
    const righe30 = tc.trim().split(/\r?\n/), testa30 = righe30[0].split(';'), iN = testa30.indexOf('NOME'), iC = testa30.indexOf('COGNOME');
    const celle = righe30.slice(1).flatMap(l => { const c = l.match(/("([^"]|"")*"|[^;]*)(;|$)/g).map(x => x.replace(/;$/, '').replace(/^"|"$/g, '')); return [c[iN], c[iC]]; });
    ok('2.9.0: Esporta: = + - @ all\'inizio di una cella escono con l\'apostrofo (niente formule nel foglio di calcolo)',
      /text\/csv/.test(csv.headers()['content-type'] || '') && celle.length === 4 && celle.every(c => c.startsWith("'")), celle);
    // I file dei contatti non si scaricano dal web
    if (DIR30) {
      const cart = fs.readdirSync(path.join(DIR30, 'uploads')).find(n => /^contatti-[a-f0-9]{16}$/.test(n));
      const file = cart ? fs.readdirSync(path.join(DIR30, 'uploads', cart)).find(n => /\.(csv|jsonl)$/.test(n)) : '';
      const cx = await browser.newContext();
      const rf = cart ? await cx.request.get(BASE + PAN + '/uploads/' + cart + '/' + (file || 'index.html'), { maxRedirects: 0 }) : null;
      ok('2.9.0: la cartella dei file dei contatti è chiusa al web (403/404, .htaccess "Require all denied")', !!cart && fs.readFileSync(path.join(DIR30, 'uploads', cart, '.htaccess'), 'utf8').includes('Require all denied') && !!rf && rf.status() >= 400, [cart, file, rf && rf.status()]);
      await cx.close();
    }
    // Pulizia: i contatti di prova
    const tkp = await tokenDi(admin.p, PC + '?cerca=formula');
    await post(admin.p, PC, form({ _csrf: tkp, azione: 'gruppo', cosa: 'elimina', cerca: 'formula', tutti: '1' }), CT);
  }

  // === 32. (infografiche disegnate, 2.9.0; sedici dalla 2.26.0) Le infografiche disegnate con valori cattivi in ogni campo (prove/pagina-infografiche-15.php cattivi:
  // passano da editor_valida come dall'editor vero): HTML e script nei testi, colori fuori dalla palette e colori liberi che
  // provano a chiudere lo stile, numeri enormi, negativi, NaN e INF, 45 voci in ogni ripetitore. La pagina si apre nel browser
  // con JavaScript acceso: nessuna finestra, nessun attributo on*, nessuno script, stili solo con le variabili dei colori
  // validate, numeri veri nelle coordinate, al massimo le voci ammesse.
  if (DIR && fs.existsSync(path.join(__dirname, '..', 'prove', 'pagina-infografiche-15.php'))) {
    const { execFileSync } = require('child_process');
    let out = '';
    try { out = execFileSync('php', [path.join(__dirname, '..', 'prove', 'pagina-infografiche-15.php'), DIR, 'cattivi'], { encoding: 'utf8' }); } catch (e) { out = String(e.message).slice(0, 200); }
    ok('infografiche: (preparazione) la pagina delle sedici infografiche con i valori cattivi', /infografiche-15-cattivi/.test(out), out.trim());
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const pg = await ctx.newPage();
    const finestre = [], errori = [];
    pg.on('dialog', d => { finestre.push(d.message()); d.dismiss(); });
    pg.on('pageerror', e => errori.push(e.message.slice(0, 120)));
    const r = await pg.goto(BASE + '/infografiche-15-cattivi/', { waitUntil: 'networkidle' });
    const esame = await pg.evaluate(() => {
      const ig = [...document.querySelectorAll('.igx')];
      const dentro = ig.flatMap(x => [...x.querySelectorAll('*')]);
      const larghe = [...document.querySelectorAll('.igs-largo')];
      const conta = (t, sel) => Math.max(0, ...larghe.filter(s => s.closest('.ed-w-ig_' + t)).map(s => s.querySelectorAll(sel).length));
      return {
        widget: ig.length,
        on: dentro.filter(e => [...e.attributes].some(a => /^on/i.test(a.name))).map(e => e.outerHTML.slice(0, 80)),
        script: dentro.filter(e => /^(script|iframe|object|embed|foreignobject|img|a|use|image)$/i.test(e.tagName)).map(e => e.tagName),
        stili: dentro.filter(e => e.closest('.igs') && e.hasAttribute('style')).map(e => e.getAttribute('style')).filter(s => !/^--c:#[0-9a-fA-F]{3,8};--c2:#[0-9a-fA-F]{3,8}$/.test(s)),
        classi: dentro.filter(e => e.closest('.igs')).map(e => e.getAttribute('class') || '').filter(c => !/^[a-z0-9_ -]*$/.test(c)),
        numeri: dentro.filter(e => e.closest('.igs')).flatMap(e => ['x', 'y', 'cx', 'cy', 'r', 'width', 'height', 'points', 'd', 'viewBox'].map(n => e.getAttribute(n)).filter(v => v !== null && /NaN|INF|e\+/i.test(v))),
        testo: [...document.querySelectorAll('.igs text')].some(t => t.textContent.includes('<svg onload=alert(1)>')),
        voci: { imbuto: conta('imbuto', 'polygon'), piramide: conta('piramide', 'polygon'), matrice: conta('matrice', 'rect.igs-tinta'), ciclo: conta('ciclo', 'circle'), ciambella: conta('ciambella', 'circle.igs-tratto'), venn: conta('venn', 'circle.igs-tinta'), radar: conta('radar', 'circle.igs-p2'), iceberg: conta('iceberg', 'text.igs-su'), omini: conta('omini', 'circle'), viaggio: conta('viaggio', 'circle.igs-anello-carta'), bilancia: conta('bilancia', 'text.igs-tm'), calore: conta('calore', 'rect title'), cerchi: conta('cerchi', 'g > circle'), barre: conta('barre100', 'rect.igs-p'), confronto: conta('confronto', 'rect.igs-p') },
        peso: document.documentElement.outerHTML.length,
      };
    });
    ok('infografiche: le sedici infografiche cattive si aprono (200), tutte e sedici, senza errori JavaScript', r.status() === 200 && esame.widget === 16 && errori.length === 0, { stato: r.status(), widget: esame.widget, errori });
    ok('infografiche: nessuna finestra aperta da script nascosti nei testi', finestre.length === 0, finestre);
    ok('infografiche: nessun attributo on* e nessun elemento attivo (script, iframe, immagini, link, use) dentro le infografiche', esame.on.length === 0 && esame.script.length === 0, { on: esame.on, el: esame.script.slice(0, 5) });
    ok('infografiche: i colori manipolati non entrano: stili solo con --c e --c2 esadecimali, classi pulite', esame.stili.length === 0 && esame.classi.length === 0, { stili: esame.stili.slice(0, 3), classi: esame.classi.slice(0, 3) });
    ok('infografiche: i numeri manipolati (enormi, negativi, NaN, INF) non arrivano nelle coordinate del disegno', esame.numeri.length === 0, esame.numeri.slice(0, 5));
    ok('infografiche: il testo cattivo si legge come testo', esame.testo);
    const v = esame.voci, lim = { imbuto: 6, piramide: 6, matrice: 4, ciclo: 6, ciambella: 6, venn: 3, radar: 8, iceberg: 6, omini: 20, viaggio: 7, bilancia: 16, calore: 144, cerchi: 4, barre: 24, confronto: 8 };
    ok('infografiche: 45 voci per ripetitore: si disegnano al massimo quelle ammesse (6 gradini, 3 cerchi, 8 aspetti, 12 x 12 caselle...)', Object.entries(lim).every(([k, m]) => v[k] <= m) && v.imbuto === 6 && v.radar === 8 && v.calore > 0, v);
    ok('infografiche: la pagina resta leggera (meno di 1,5 MB di HTML con 45 voci per ripetitore)', esame.peso < 1500000, esame.peso);
    await ctx.close();
  } else console.log('--  infografiche disegnate con valori cattivi: saltata (serve CMS_DIR)');

  // === 31. (2.10.0) Segmenti, Aggiungi filtro, viste: solo l'amministratore, solo con il token e dal pannello; le condizioni
  // sono JSON controllato con un elenco chiuso: nomi di colonna, operatori, valori, annidamento e quantità manipolati non
  // arrivano all'SQL; un segmento non fa giri; i nomi non diventano codice.
  {
    const PS = ADMIN + '/newsletter-segmenti.php', PC = ADMIN + '/newsletter-contatti.php';
    const ORIG = { 'Origin': BASE.replace(/(https?:\/\/[^/]+).*/, '$1') };
    const b64 = (o) => Buffer.from(typeof o === 'string' ? o : JSON.stringify(o)).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const g1 = (...c) => ({ op: 'e', gruppi: [{ op: 'e', condizioni: c }] });
    const anon = await browser.newContext();
    const a1 = await anon.request.get(PS, { maxRedirects: 0 });
    const a2 = await anon.request.post(PS, { form: { azione: 'conta', regole: b64(g1({ campo: 'stato', op: 'iscritto' })) }, maxRedirects: 0 });
    ok('2.10.0: senza login la pagina Segmenti non si apre e non conta', a1.status() !== 200 && !/"n":/.test(await a2.text()), [a1.status(), a2.status()]);
    await anon.close();
    const tk = await tokenDi(admin.p, PS);
    const conta = async (regole, tok = tk, h = ORIG) => { const r = await admin.ctx.request.post(PS, { headers: h, form: { _csrf: tok, azione: 'conta', regole: typeof regole === 'string' ? regole : b64(regole) }, maxRedirects: 0 }); let j = {}; try { j = JSON.parse(await r.text()); } catch (e) { j = { stato: r.status() }; } j.status = r.status(); return j; };
    const c1 = await conta(g1({ campo: 'stato', op: 'iscritto' }), '');
    const c2 = await conta(g1({ campo: 'stato', op: 'iscritto' }), tk, { 'Origin': 'https://evil.example' });
    const prima = await admin.ctx.request.post(PS, { headers: ORIG, form: { azione: 'salva', nome: 'Sic senza token', regole: b64(g1({ campo: 'stato', op: 'iscritto' })) }, maxRedirects: 0 });
    await admin.p.goto(PS, { waitUntil: 'networkidle' });
    ok('2.10.0: conta senza token o da un altro sito: 403; Salva senza token: 403 e niente segmento', c1.status === 403 && c2.status === 403 && prima.status() === 403 && !(await admin.p.content()).includes('Sic senza token'), [c1.status, c2.status, prima.status()]);
    const tutti = (await conta({ op: 'e', gruppi: [] })).n;
    // Condizioni manipolate
    const casi = [
      ['nome di colonna inventato', g1({ campo: 'i.id) OR 1=1 -- ', op: 'uguale', valore: 'x' })],
      ['attributo con un pezzo di SQL nel nome', g1({ campo: 'attr:EMAIL`; DROP TABLE x; --', op: 'uguale', valore: 'x' })],
      ['operatore inventato', g1({ campo: 'attr:EMAIL', op: '= 1 OR 1=1', valore: 'x' })],
      ['operatore di un altro tipo', g1({ campo: 'attr:EMAIL', op: 'maggiore', valore: '1' })],
      ['lista con SQL al posto dell\'id', g1({ campo: 'lista', op: 'in', valore: ['1) OR (1=1'] })],
      ['motivo della lista nera inventato', g1({ campo: 'lista_nera', op: 'motivo', valore: ["x' OR '1'='1"] })],
      ['condizione del flusso fuori da un flusso', g1({ campo: 'flusso_email', op: 'aperta', valore: 2 })],
      ['campo come oggetto', g1({ campo: { a: 1 }, op: 'uguale' })],
      ['1000 condizioni', { op: 'e', gruppi: [{ op: 'o', condizioni: Array.from({ length: 1000 }, () => ({ campo: 'stato', op: 'iscritto' })) }] }],
      ['101 condizioni in tre gruppi', { op: 'e', gruppi: [0, 1, 2].map(k => ({ op: 'e', condizioni: Array.from({ length: k === 2 ? 35 : 33 }, () => ({ campo: 'stato', op: 'iscritto' })) })) }],
      ['21 gruppi', { op: 'e', gruppi: Array.from({ length: 21 }, () => ({ op: 'e', condizioni: [{ campo: 'stato', op: 'iscritto' }] })) }],
      ['annidamento enorme (5000 livelli)', '['.repeat(5000) + ']'.repeat(5000)],
      ['gruppi dentro i gruppi', { op: 'e', gruppi: [{ op: 'e', condizioni: [{ op: 'o', gruppi: [{ op: 'e', condizioni: [{ campo: 'stato', op: 'iscritto' }] }] }] }] }],
      ['JSON rotto', '{"op":"e","gruppi":[{"op":'],
      ['testo di 300 KB', 'x'.repeat(300000)],
    ];
    const risposte = [];
    for (const [nome, r] of casi) {
      const t0 = Date.now();
      const j = await conta(typeof r === 'string' ? b64(r) : r);
      risposte.push([nome, j.errore || j.n, Date.now() - t0]);
      ok(`2.10.0: condizioni manipolate (${nome}): rifiutate in parole, niente all'SQL`, !!j.errore && j.n === undefined && !/SQLSTATE|PDO|Fatal/i.test(j.errore) && Date.now() - t0 < 5000, [j.errore, j.n, j.status]);
    }
    // Valori cattivi: passano come parametri, non come codice
    const v1 = await conta(g1({ campo: 'attr:EMAIL', op: 'uguale', valore: "x' OR '1'='1" }));
    const v2 = await conta(g1({ campo: 'attr:EMAIL', op: 'contiene', valore: "%' OR 1=1 -- " }));
    const v3 = await conta(g1({ campo: 'attr:CREATION_DATE', op: 'ultimi', n: '1) OR (1=1', unita: 'DAY) OR (1=1' }));
    const v4 = await conta(g1({ campo: 'email', op: 'aperta', campagna: '1 OR 1=1', n: 30, unita: 'giorni; DROP' }));
    const v4b = await conta(g1({ campo: 'email', op: 'aperta', campagna: 0, n: 30, unita: 'giorni' }));
    ok('2.10.0: valori con SQL dentro (email, contiene, date relative, campagna, unità): sono valori, non codice',
      v1.n === 0 && v2.n === 0 && (v3.errore || v3.n < tutti) && v4.n === v4b.n && !/SQLSTATE/i.test(JSON.stringify([v1, v2, v3, v4])), [tutti, v1, v2, v3, v4.n, v4b.n]);
    // Filtro della pagina Contatti con condizioni cattive: niente errore del database, il filtro si scarta e lo dice
    await admin.p.goto(PC + '?f=' + b64(g1({ campo: 'i.id) OR 1=1 -- ', op: 'x' })), { waitUntil: 'networkidle' });
    const pc = await admin.p.content();
    ok('2.10.0: Contatti con un filtro manipolato nell\'indirizzo: scartato e detto in parole, niente errore del database', /Il filtro non si è potuto usare/.test(pc) && !/SQLSTATE|Fatal error/i.test(pc));
    // Giri fra segmenti: A usa B, poi B prova a usare A
    const salva = async (o) => admin.ctx.request.post(PS, { headers: ORIG, form: { _csrf: tk, azione: 'salva', ...o }, maxRedirects: 0 });
    await salva({ id: '0', nome: 'Sic A', regole: b64(g1({ campo: 'stato', op: 'iscritto' })) });
    await admin.p.goto(PS, { waitUntil: 'networkidle' });
    const idDi = async (nome) => admin.p.evaluate((n) => { const a = [...document.querySelectorAll('a[href*="newsletter-segmenti.php?id="]')].find(x => x.textContent.trim() === n); return a ? +a.href.match(/id=(\d+)/)[1] : 0; }, nome);
    const idA = await idDi('Sic A');
    await salva({ id: '0', nome: 'Sic B', regole: b64(g1({ campo: 'segmento', op: 'in', valore: idA })) });
    await admin.p.goto(PS, { waitUntil: 'networkidle' });
    const idB = await idDi('Sic B');
    await salva({ id: String(idA), nome: 'Sic A', regole: b64(g1({ campo: 'segmento', op: 'in', valore: idB })) });
    const giro = await conta(g1({ campo: 'segmento', op: 'in', valore: idB }));
    await admin.p.goto(PS + '?id=' + idA, { waitUntil: 'networkidle' }); // la prima volta mostra la bozza rifiutata
    await admin.p.goto(PS + '?id=' + idA, { waitUntil: 'networkidle' });
    ok('2.10.0: un segmento che userebbe se stesso attraverso un altro: rifiutato, e il conteggio non gira all\'infinito', idA > 0 && idB > 0 && !/"campo":"segmento"/.test(await admin.p.evaluate(() => atob((document.querySelector('#nls-regole') || {}).value.replace(/-/g, '+').replace(/_/g, '/') || ''))) && typeof giro.n === 'number', [idA, idB, giro]);
    // Nomi con codice: escono come testo
    await salva({ id: '0', nome: '<script>alert(31)</script><img src=x onerror=alert(31)>', cartella_nuova: '<b onmouseover=alert(31)>cat</b>', regole: b64(g1({ campo: 'stato', op: 'iscritto' })) });
    await admin.p.goto(PS, { waitUntil: 'networkidle' });
    const hs = await admin.p.content();
    ok('2.10.0: nome e categoria con codice: escono come testo, niente script', !/<script>alert\(31\)|<img src=x onerror|<b onmouseover/.test(hs) && /&lt;script&gt;alert\(31\)/.test(hs));
    await admin.ctx.request.post(PC, { headers: ORIG, form: { _csrf: await tokenDi(admin.p, PC), azione: 'vista_salva', nome_vista: '<svg onload=alert(31)>' }, maxRedirects: 0 });
    await admin.p.goto(PC, { waitUntil: 'networkidle' });
    ok('2.10.0: nome di una vista con codice: esce come testo', !/<svg onload=alert\(31\)>/.test(await admin.p.content()));
    const vv = await admin.ctx.request.post(PC, { headers: ORIG, form: { azione: 'vista_salva', nome_vista: 'Sic senza token' }, maxRedirects: 0 });
    ok('2.10.0: Salva come vista senza token: 403', vv.status() === 403, vv.status());
    // Il redattore: non vede e non salva
    if (red.dentro) {
      const tr = await tokenDi(red.p, ADMIN + '/media.php');
      const r1 = await red.ctx.request.get(PS, { maxRedirects: 0 });
      const r2 = await red.ctx.request.post(PS, { headers: ORIG, form: { _csrf: tr, azione: 'salva', nome: 'Sic dal redattore', regole: b64(g1({ campo: 'stato', op: 'iscritto' })) }, maxRedirects: 0 });
      const r3 = await red.ctx.request.post(PS, { headers: ORIG, form: { _csrf: tr, azione: 'conta', regole: b64(g1({ campo: 'stato', op: 'iscritto' })) }, maxRedirects: 0 });
      const r4 = await red.ctx.request.post(PS, { headers: ORIG, form: { _csrf: tr, azione: 'elimina', id: String(idB) }, maxRedirects: 0 });
      const r5 = await red.ctx.request.post(PC, { headers: ORIG, form: { _csrf: tr, azione: 'gruppo', cosa: 'esporta', esporta_avvia: '1', esp_origine: 'segmento', esp_segmento: String(idB) }, maxRedirects: 0 });
      await admin.p.goto(PS, { waitUntil: 'networkidle' });
      const lista = await admin.p.content();
      ok('2.10.0: il redattore non apre Segmenti, non salva, non conta, non elimina, non esporta un segmento',
        r1.status() === 302 && !/"n":/.test(await r3.text()) && !lista.includes('Sic dal redattore') && lista.includes('Sic B') && !/text\/csv/.test(r5.headers()['content-type'] || ''), [r1.status(), r2.status(), r3.status(), r4.status(), r5.status()]);
    }
    // Pulizia: i segmenti e la vista di prova
    await admin.p.goto(PS, { waitUntil: 'networkidle' });
    for (const n of ['<script>alert(31)</script><img src=x onerror=alert(31)>', 'Sic B', 'Sic A']) {
      const id = await idDi(n);
      if (id) await salva({ azione: 'elimina', id: String(id) });
    }
    await admin.p.goto(PC, { waitUntil: 'networkidle' });
    const vid = await admin.p.evaluate(() => { const a = [...document.querySelectorAll('.nlv-viste a')].find(x => /svg/.test(x.textContent)); return a ? a.href.match(/vista=(\d+)/)[1] : ''; });
    if (vid) await admin.ctx.request.post(PC, { headers: ORIG, form: { _csrf: await tokenDi(admin.p, PC), azione: 'vista_elimina', vista_id: vid }, maxRedirects: 0 });
  }

  // === 33. (2.11.0) Campagne a riepilogo, pagina di disiscrizione con il questionario, centro preferenze. Le azioni nuove
  // delle campagne: solo l'amministratore, solo con il token e dal pannello; destinatari manipolati (id con SQL, liste e
  // segmenti che non esistono) non entrano; le pagine pubbliche valgono solo con la firma del contatto giusto, mostrano e
  // cambiano solo le liste pubbliche e gli attributi consentiti, il questionario salva testo e mai HTML; la versione web
  // si apre solo firmata, senza eseguire niente. Serve CMS_DIR (prove/campagne-cli.php legge e scrive il database).
  {
    const path = require('path'), { execFileSync } = require('child_process');
    const DIR33 = process.env.CMS_DIR || '';
    const PN = ADMIN + '/newsletter.php', PCm = ADMIN + '/newsletter-campagna.php';
    const ORIG = { 'Origin': BASE.replace(/(https?:\/\/[^/]+).*/, '$1') };
    const SITO = BASE; // il sito pubblico sta nella base
    if (!DIR33) ok('2.11.0: (senza CMS_DIR le prove delle campagne e delle pagine pubbliche sono saltate)', true);
    else {
      const cli = (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, '..', 'prove', 'campagne-cli.php'), DIR33, ...a.map(String)], { encoding: 'utf8' }).trim().split('\n').pop());
      const q = (s) => cli('sql', s);
      const uno = (s) => { const r = q(s); return r && r[0] ? Object.values(r[0])[0] : null; };
      // Dati: due contatti, una lista pubblica e una privata, una campagna in bozza e una inviata
      q("DELETE FROM {p}newsletter_iscritti WHERE email LIKE 'sic%@sic33.it'");
      q("DELETE FROM {p}newsletter_liste WHERE nome IN ('Sic33 pubblica', 'Sic33 privata')");
      q("DELETE FROM {p}newsletter_campagne WHERE nome LIKE 'Sic33%'");
      q("INSERT INTO {p}newsletter_liste (nome, descrizione, creato, pubblica) VALUES ('Sic33 pubblica', '', NOW(), 1), ('Sic33 privata', '', NOW(), 0)");
      const LP = +uno("SELECT id FROM {p}newsletter_liste WHERE nome = 'Sic33 pubblica'"), LR = +uno("SELECT id FROM {p}newsletter_liste WHERE nome = 'Sic33 privata'");
      q("INSERT INTO {p}newsletter_iscritti (email, nome, stato, fonte, creato, aggiornato) VALUES ('sic1@sic33.it', 'Uno', 'attivo', 'manuale', NOW(), NOW()), ('sic2@sic33.it', 'Due', 'attivo', 'manuale', NOW(), NOW())");
      const I1 = +uno("SELECT id FROM {p}newsletter_iscritti WHERE email = 'sic1@sic33.it'"), I2 = +uno("SELECT id FROM {p}newsletter_iscritti WHERE email = 'sic2@sic33.it'");
      q(`INSERT INTO {p}newsletter_iscritti_liste (iscritto_id, lista_id, aggiunto) VALUES (${I1}, ${LP}, NOW()), (${I2}, ${LP}, NOW())`);
      q(`INSERT INTO {p}newsletter_campagne (nome, oggetto, corpo, liste, stato, link_web, link_preferenze, creato, aggiornato) VALUES ('Sic33 bozza', 'Sic33', '<p>x</p>', '${LP}', 'bozza', 1, 1, NOW(), NOW()), ('Sic33 inviata', 'Sic33', '<p>x</p>', '${LP}', 'inviata', 1, 1, NOW(), NOW())`);
      const CB = +uno("SELECT id FROM {p}newsletter_campagne WHERE nome = 'Sic33 bozza'"), CI = +uno("SELECT id FROM {p}newsletter_campagne WHERE nome = 'Sic33 inviata'");
      const quante = () => +uno("SELECT COUNT(*) FROM {p}newsletter_campagne WHERE nome LIKE 'Sic33%' OR nome LIKE '%Sic33%'");
      const n0 = quante();
      // Senza login
      const anon = await browser.newContext();
      const a1 = await anon.request.get(PCm + '?id=' + CB, { maxRedirects: 0 });
      const a2 = await anon.request.post(PN, { form: { azione: 'nuova', nome: 'Sic33 anonimo' }, maxRedirects: 0 });
      const a3 = await anon.request.post(PCm + '?id=' + CB, { form: { azione: 'sezione', sez: 'oggetto', oggetto: 'Sic33 da fuori' }, maxRedirects: 0 });
      ok('2.11.0: senza login il riepilogo non si apre, niente campagna creata né cambiata', a1.status() !== 200 && quante() === n0 && uno(`SELECT oggetto FROM {p}newsletter_campagne WHERE id = ${CB}`) === 'Sic33', [a1.status(), a2.status(), a3.status()]);
      await anon.close();
      // Token e origine
      const tk = await tokenDi(admin.p, PCm + '?id=' + CB);
      const s1 = await admin.ctx.request.post(PN, { headers: ORIG, form: { azione: 'nuova', nome: 'Sic33 senza token' }, maxRedirects: 0 });
      const s2 = await admin.ctx.request.post(PN, { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: tk, azione: 'nuova', nome: 'Sic33 da un altro sito' }, maxRedirects: 0 });
      const s3 = await admin.ctx.request.post(PCm + '?id=' + CB, { headers: ORIG, form: { azione: 'invia', quando: 'subito' }, maxRedirects: 0 });
      const s4 = await admin.ctx.request.post(PN, { headers: ORIG, form: { azione: 'elimina', id: String(CB) }, maxRedirects: 0 });
      ok('2.11.0: Crea, Invia, Elimina senza token o da un altro sito: 403 e niente fatto', [s1, s2, s3, s4].every(r => r.status() === 403) && quante() === n0 && uno(`SELECT stato FROM {p}newsletter_campagne WHERE id = ${CB}`) === 'bozza', [s1.status(), s2.status(), s3.status(), s4.status()]);
      // Destinatari manipolati
      const dm = await admin.ctx.request.post(PCm + '?id=' + CB, { headers: ORIG, form: { _csrf: tk, azione: 'sezione', sez: 'destinatari', 'liste[]': ['1) OR (1=1', String(LP), '999999', String(LR) + ' OR 1'], 'segmenti[]': ['999999', "1' OR '1'='1"], 'escludi_liste[]': ['-5', 'abc'], 'escludi_segmenti[]': ['0x10'], non_coinvolti: '1; DROP TABLE x' }, maxRedirects: 0 });
      const r = q(`SELECT liste, segmenti, escludi_liste, escludi_segmenti, non_coinvolti FROM {p}newsletter_campagne WHERE id = ${CB}`)[0];
      const soloNumeri = (s) => s === '' || /^\d+(,\d+)*$/.test(s);
      ok('2.11.0: destinatari manipolati: entrano solo id veri di liste e segmenti, nient\'altro', dm.status() === 302 && soloNumeri(r.liste) && soloNumeri(r.segmenti) && soloNumeri(r.escludi_liste) && soloNumeri(r.escludi_segmenti) && !r.liste.split(',').includes('999999') && r.segmenti === '' && ['0', '1'].includes(String(r.non_coinvolti)), r);
      const conta = cli('conta', CB);
      ok('2.11.0: il conteggio dei destinatari con quei valori resta un numero (niente errore del database)', typeof conta === 'number', conta);
      // Sezione inventata, lotti fuori misura, Rispondi a con a capo
      const sx = await admin.ctx.request.post(PCm + '?id=' + CB, { headers: ORIG, form: { _csrf: tk, azione: 'sezione', sez: 'stato', stato: 'inviata' }, maxRedirects: 0 });
      await admin.ctx.request.post(PCm + '?id=' + CB, { headers: ORIG, form: { _csrf: tk, azione: 'sezione', sez: 'impostazioni', rispondi_a: "a@b.it\r\nBcc: spia@cattivo.it", lotti_attivi: '1', lotti: '500', lotti_intervallo: '1' }, maxRedirects: 0 });
      await admin.ctx.request.post(PCm + '?id=' + CB, { headers: ORIG, form: { _csrf: tk, azione: 'sezione', sez: 'impostazioni', rispondi_a: '', campo_a: "Nome\r\nBcc: spia@cattivo.it <x>", lotti_attivi: '' }, maxRedirects: 0 });
      const ri = q(`SELECT stato, rispondi_a, lotti, campo_a FROM {p}newsletter_campagne WHERE id = ${CB}`)[0];
      ok('2.11.0: sezione inventata ignorata; Rispondi a con un a capo rifiutato; lotti oltre 10 rifiutati; campo «A» senza a capo né < >', sx.status() === 302 && ri.stato === 'bozza' && ri.rispondi_a === '' && +ri.lotti === 1 && !/[\r\n<>]/.test(ri.campo_a), ri);
      const h1 = cli('html', CB, I1);
      ok('2.11.0: nell\'email il campo «A» non aggiunge intestazioni (niente Bcc)', !/\r\nBcc:/i.test(h1.intest) && !/^Bcc:/mi.test(h1.intest), h1.intest);
      // Azioni sulle campagne che non si possono fare
      const tkn = await tokenDi(admin.p, PN);
      await admin.ctx.request.post(PN, { headers: ORIG, form: { _csrf: tkn, azione: 'elimina', id: String(CI) }, maxRedirects: 0 });
      await admin.ctx.request.post(PN, { headers: ORIG, form: { _csrf: tkn, azione: 'archivia', id: String(CB) }, maxRedirects: 0 });
      await admin.ctx.request.post(PN, { headers: ORIG, form: { _csrf: tkn, azione: 'rinvio', id: String(CB) }, maxRedirects: 0 });
      await admin.ctx.request.post(PN, { headers: ORIG, form: { _csrf: tkn, azione: 'nuovi', id: String(CB) }, maxRedirects: 0 });
      ok('2.11.0: non si elimina una inviata, non si archivia una bozza, non si rimanda né si manda ai nuovi una bozza', +uno(`SELECT COUNT(*) FROM {p}newsletter_campagne WHERE id = ${CI}`) === 1 && +uno(`SELECT archiviata FROM {p}newsletter_campagne WHERE id = ${CB}`) === 0 && quante() === n0);
      // Nome con codice nell'elenco
      await admin.ctx.request.post(PN, { headers: ORIG, form: { _csrf: tkn, azione: 'nuova', nome: 'Sic33 <script>alert(33)</script><img src=x onerror=alert(33)>' }, maxRedirects: 0 });
      await admin.p.goto(PN, { waitUntil: 'networkidle' });
      const hl = await admin.p.content();
      ok('2.11.0: nome della campagna con codice: esce come testo nell\'elenco', !/<script>alert\(33\)|<img src=x onerror=alert\(33\)/.test(hl) && /&lt;script&gt;alert\(33\)/.test(hl));
      // Redattore
      if (red.dentro) {
        const tr = await tokenDi(red.p, ADMIN + '/media.php');
        const prima = JSON.stringify(q(`SELECT * FROM {p}newsletter_campagne WHERE id IN (${CB}, ${CI}) ORDER BY id`));
        const rr = [];
        rr.push(await red.ctx.request.get(PCm + '?id=' + CB, { maxRedirects: 0 }));
        for (const f of [{ azione: 'nuova', nome: 'Sic33 dal redattore' }, { azione: 'duplica', id: String(CI), 'parti[]': 'oggetto' }, { azione: 'nuovi', id: String(CI) }, { azione: 'rinvio', id: String(CI) }, { azione: 'archivia', id: String(CI) }, { azione: 'annulla', id: String(CB) }, { azione: 'elimina', id: String(CB) }, { azione: 'pagine', 'pubbliche[]': String(LR) }, { azione: 'indirizzo', indirizzo: 'Sic33 dal redattore' }])
          rr.push(await red.ctx.request.post(PN, { headers: ORIG, form: { _csrf: tr, ...f }, maxRedirects: 0 }));
        for (const f of [{ azione: 'sezione', sez: 'oggetto', oggetto: 'Sic33 dal redattore' }, { azione: 'invia', quando: 'subito' }, { azione: 'prova', prova_a: 'red@sic33.it' }])
          rr.push(await red.ctx.request.post(PCm + '?id=' + CB, { headers: ORIG, form: { _csrf: tr, ...f }, maxRedirects: 0 }));
        ok('2.11.0: il redattore non apre il riepilogo e non fa nessuna delle azioni nuove (crea, duplica, nuovi, rinvio, archivia, annulla, elimina, impostazioni, sezioni, invio, prova)',
          rr.every(x => [302, 403].includes(x.status())) && JSON.stringify(q(`SELECT * FROM {p}newsletter_campagne WHERE id IN (${CB}, ${CI}) ORDER BY id`)) === prima && quante() === n0 + 1 && +uno(`SELECT pubblica FROM {p}newsletter_liste WHERE id = ${LR}`) === 0 && !/Sic33/.test(uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_indirizzo'") || ''), rr.map(x => x.status()));
      }
      // ----- Pagine pubbliche
      const pub = await browser.newContext();
      const pref1 = cli('preferenze', I1), pref2 = cli('preferenze', I2);
      const t1 = pref1.match(/t=([a-f0-9]+)/)[1];
      const g1 = await pub.request.get(pref1.replace(/i=\d+/, 'i=' + I2));
      const g2 = await pub.request.get(pref1.replace(/t=[a-f0-9]+/, 't=' + '0'.repeat(32)));
      const g3 = await pub.request.get(pref1.replace(/t=[a-f0-9]+/, 't[]=x'));
      ok('2.11.0: centro preferenze con la firma di un altro contatto, sbagliata o storta: link non valido, nessun dato mostrato', [g1, g2, g3].every(x => x.status() === 200) && !/sic2@sic33|sic1@sic33/.test((await g1.text()) + (await g2.text()) + (await g3.text())) && /non è valido/.test(await g1.text()));
      const pp1 = await pub.request.post(pref1.replace(/i=\d+/, 'i=' + I2), { form: { azione: 'esci' }, maxRedirects: 0 });
      ok('2.11.0: «Esci da tutto» con la firma di un altro contatto: non esce nessuno', pp1.status() === 200 && uno(`SELECT stato FROM {p}newsletter_iscritti WHERE id = ${I2}`) === 'attivo');
      const pp2 = await pub.request.post(pref1, { form: { azione: 'salva', 'liste[]': [String(LP), String(LR), '1) OR (1=1'], 'a[EMAIL]': 'rubata@cattivo.it', 'a[NOME]': '<script>alert(33)</script>Uno', 'a[EXT_ID]': 'sic33', 'a[OPT_IN]': '1', 'a[COGNOME]': 'x'.repeat(5000) }, maxRedirects: 0 });
      const c1 = q(`SELECT email, nome, cognome, ext_id FROM {p}newsletter_iscritti WHERE id = ${I1}`)[0];
      ok('2.11.0: centro preferenze: niente lista privata, niente email né attributi non consentiti, testi tagliati', pp2.status() === 200 && +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE iscritto_id = ${I1} AND lista_id = ${LR}`) === 0 && c1.email === 'sic1@sic33.it' && !c1.ext_id && c1.cognome.length <= 100, c1);
      const pg = await pub.newPage();
      await pg.goto(pref1, { waitUntil: 'networkidle' });
      const hp = await pg.content();
      ok('2.11.0: un nome con codice scritto dal contatto esce come testo nella pagina', !/<script>alert\(33\)/.test(hp) && (await pg.inputValue('#nlp-nome')).includes('<script>'));
      // Disiscrizione: firma sbagliata, motivo inventato, HTML e testo lungo nel questionario, campagna non firmata
      const us1 = cli('uscita', I1), us2 = cli('uscita', I2, CI);
      const e1 = await pub.request.post(us1.replace(/t=[a-f0-9]+/, 't=' + '0'.repeat(32)), { form: {}, maxRedirects: 0 });
      const e2 = await pub.request.post(us1.replace(/i=\d+/, 'i=' + I2), { form: {}, maxRedirects: 0 });
      ok('2.11.0: uscita con la firma sbagliata o di un altro contatto: link non valido, nessuno esce', /Link non valido/.test(await e1.text()) && /Link non valido/.test(await e2.text()) && uno(`SELECT stato FROM {p}newsletter_iscritti WHERE id = ${I2}`) === 'attivo');
      const e3 = await pub.request.post(us2, { form: { motivo: 'troppe' }, maxRedirects: 0 });
      ok('2.11.0: il questionario di un contatto ancora iscritto non si salva (prima si esce)', e3.status() === 200 && +uno(`SELECT COUNT(*) FROM {p}newsletter_uscite_motivi WHERE contatto_id = ${I2}`) === 0 && uno(`SELECT stato FROM {p}newsletter_iscritti WHERE id = ${I2}`) === 'attivo');
      await pub.request.post(us2, { form: {}, maxRedirects: 0 });
      await pub.request.post(us2, { form: { motivo: "troppe' OR 1=1" }, maxRedirects: 0 });
      const m0 = +uno(`SELECT COUNT(*) FROM {p}newsletter_uscite_motivi WHERE contatto_id = ${I2}`);
      await pub.request.post(us2, { form: { motivo: 'altro', altro: '<img src=x onerror=alert(33)><b>Troppe</b> \u{1F600} ' + 'z'.repeat(3000) }, maxRedirects: 0 });
      const m1 = q(`SELECT motivo, altro, campagna_id FROM {p}newsletter_uscite_motivi WHERE contatto_id = ${I2}`);
      ok('2.11.0: questionario: motivo inventato non salvato; «Altro» senza HTML, emoji come entità, al massimo 500 caratteri; campagna firmata dal link', m0 === 0 && m1.length === 1 && m1[0].motivo === 'altro' && !/[<>]/.test(m1[0].altro) && /&#x1F600;/.test(m1[0].altro) && m1[0].altro.length <= 500 && +m1[0].campagna_id === CI, m1);
      // La campagna non firmata (c senza k, o con k di un'altra) non si attribuisce
      q(`UPDATE {p}newsletter_iscritti SET stato = 'attivo', blocco_motivo = '' WHERE id = ${I1}`);
      await pub.request.post(us1 + `&c=${CI}&f=0&p=0&k=` + 'a'.repeat(20), { form: {}, maxRedirects: 0 });
      await pub.request.post(us1 + `&c=${CI}&f=0&p=0&k=` + 'a'.repeat(20), { form: { motivo: 'troppe' }, maxRedirects: 0 });
      ok('2.11.0: la campagna di un link senza la firma giusta non riceve il motivo né l\'uscita', +uno(`SELECT campagna_id FROM {p}newsletter_uscite_motivi WHERE contatto_id = ${I1}`) === 0 && +uno(`SELECT COUNT(*) FROM {p}newsletter_eventi WHERE campagna_id = ${CI} AND iscritto_id = ${I1}`) === 0);
      await admin.p.goto(ADMIN + '/newsletter-statistiche.php', { waitUntil: 'networkidle' });
      ok('2.11.0: le risposte «Altro» escono come testo nelle Statistiche', !/<img src=x onerror=alert\(33\)>/.test(await admin.p.content()));
      // Versione web
      const h2 = cli('html', CI, I2);
      const web = (h2.html.match(/href="([^"]*newsletter-web\/[^"]*)"/) || [])[1].replace(/&amp;/g, '&');
      const w1 = await pub.request.get(web, { maxRedirects: 0 });
      const w2 = await pub.request.get(web.replace(/i=\d+/, 'i=' + I1), { maxRedirects: 0 });
      const w3 = await pub.request.get(web.replace(/c=\d+/, 'c=' + CB), { maxRedirects: 0 });
      const w4 = await pub.request.get(SITO + '/newsletter-web/?c=' + CI + '&i=' + I2 + '&t[]=1', { maxRedirects: 0 });
      ok('2.11.0: versione web: firmata si apre in una pagina che non esegue niente (CSP sandbox, noindex); di un altro contatto, di un\'altra campagna o storta: 404', w1.status() === 200 && /sandbox/.test(w1.headers()['content-security-policy'] || '') && /default-src 'none'/.test(w1.headers()['content-security-policy'] || '') && /noindex/.test(w1.headers()['x-robots-tag'] || '') && [w2, w3, w4].every(x => x.status() === 404), [w1.status(), w2.status(), w3.status(), w4.status()]);
      await pub.close();
      // Pulizia
      q(`DELETE FROM {p}newsletter_uscite_motivi WHERE contatto_id IN (${I1}, ${I2})`);
      q(`DELETE FROM {p}newsletter_iscritti_liste WHERE iscritto_id IN (${I1}, ${I2})`);
      q(`DELETE FROM {p}newsletter_storia WHERE contatto_id IN (${I1}, ${I2})`);
      q(`DELETE FROM {p}newsletter_eventi WHERE iscritto_id IN (${I1}, ${I2})`);
      q("DELETE FROM {p}newsletter_iscritti WHERE email LIKE 'sic%@sic33.it'");
      q("DELETE FROM {p}newsletter_liste WHERE nome IN ('Sic33 pubblica', 'Sic33 privata')");
      q("DELETE FROM {p}newsletter_campagne WHERE nome LIKE 'Sic33%'");
    }
  }

  // === 34. (2.12.0) Editor delle email a blocchi, modelli, sezioni salvate. Il design arriva in JSON e passa dagli elenchi
  // chiusi del server: niente HTML né codice nei blocchi, link solo https, mailto, tel e i quattro speciali (niente
  // javascript:, data:, vbscript:, nemmeno in base64), colori solo #rrggbb (niente expression né url()), tipi e proprietà
  // inventati via; JSON enorme o annidato rifiutato; modelli e sezioni che non esistono, non sincronizzate o di campagne già
  // partite non si toccano; senza token, da un altro sito o dal redattore niente. Serve CMS_DIR.
  {
    const path = require('path'), { execFileSync } = require('child_process');
    const DIR34 = process.env.CMS_DIR || '';
    const PD = ADMIN + '/newsletter-design.php', PM = ADMIN + '/newsletter-modelli.php', PCm = ADMIN + '/newsletter-campagna.php';
    const ORIG = { 'Origin': BASE.replace(/(https?:\/\/[^/]+).*/, '$1') };
    if (!DIR34) ok('2.12.0: (senza CMS_DIR le prove dell\'editor delle email sono saltate)', true);
    else {
      const php = (f, ...a) => JSON.parse(execFileSync('php', [path.join(__dirname, '..', 'prove', f), DIR34, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 << 20 }).trim().split('\n').pop());
      const q = (s) => php('campagne-cli.php', 'sql', s);
      const uno = (s) => { const r = q(s); return r && r[0] ? Object.values(r[0])[0] : null; };
      q("DELETE FROM {p}newsletter_campagne WHERE nome LIKE 'Sic34%'");
      q("DELETE FROM {p}newsletter_modelli WHERE nome LIKE 'Sic34%' OR nome LIKE '%alert(34)%'");
      q("DELETE FROM {p}newsletter_sezioni_salvate WHERE nome LIKE 'Sic34%'");
      q("INSERT INTO {p}newsletter_iscritti (email, nome, stato, fonte, creato, aggiornato) VALUES ('sic1@sic34.it', 'Uno', 'attivo', 'manuale', NOW(), NOW())");
      const I1 = +uno("SELECT id FROM {p}newsletter_iscritti WHERE email = 'sic1@sic34.it'");
      const base = JSON.stringify({ sezioni: [{ id: 's1', salvata: 0, colonne: [[{ id: 'b1', t: 'titolo', testo: 'Sic34 originale' }]] }] });
      const conSync = (id) => JSON.stringify({ sezioni: [{ id: 's1', salvata: id, colonne: [[{ id: 'b1', t: 'titolo', testo: 'Sic34 originale' }]] }] });
      q("INSERT INTO {p}newsletter_sezioni_salvate (nome, json, sincronizzata, creato, aggiornato) VALUES ('Sic34 sync', '{}', 1, NOW(), NOW()), ('Sic34 copia', '{}', 0, NOW(), NOW())");
      const SS = +uno("SELECT id FROM {p}newsletter_sezioni_salvate WHERE nome = 'Sic34 sync'"), SC = +uno("SELECT id FROM {p}newsletter_sezioni_salvate WHERE nome = 'Sic34 copia'");
      q(`INSERT INTO {p}newsletter_campagne (nome, oggetto, corpo, liste, stato, editor, design, creato, aggiornato) VALUES ('Sic34 bozza', 'Sic34', '', '', 'bozza', 'blocchi', '${base}', NOW(), NOW()), ('Sic34 inviata', 'Sic34', '', '', 'inviata', 'blocchi', '${conSync(SS)}', NOW(), NOW())`);
      const CB = +uno("SELECT id FROM {p}newsletter_campagne WHERE nome = 'Sic34 bozza'"), CI = +uno("SELECT id FROM {p}newsletter_campagne WHERE nome = 'Sic34 inviata'");
      q(`INSERT INTO {p}newsletter_modelli (nome, editor, design, attivo, creato, aggiornato) VALUES ('Sic34 modello', 'blocchi', '${base}', 0, NOW(), NOW())`);
      const MO = +uno("SELECT id FROM {p}newsletter_modelli WHERE nome = 'Sic34 modello'");
      const des = (c) => uno(`SELECT design FROM {p}newsletter_campagne WHERE id = ${c}`) || '';
      const contaMod = () => +uno("SELECT COUNT(*) FROM {p}newsletter_modelli");
      const m0 = contaMod();
      // Senza login
      const anon = await browser.newContext();
      const a1 = await anon.request.get(PD + '?campagna=' + CB, { maxRedirects: 0 });
      const a2 = await anon.request.post(PD + '?campagna=' + CB, { form: { azione: 'salva', design: '{"sezioni":[]}' }, maxRedirects: 0 });
      const a3 = await anon.request.get(PM, { maxRedirects: 0 });
      const a4 = await anon.request.post(PM, { form: { azione: 'nuovo', nome: 'Sic34 anonimo' }, maxRedirects: 0 });
      ok('2.12.0: senza login l\'editor e i Modelli non si aprono, niente salvato né creato', [a1, a3].every(r => r.status() !== 200) && des(CB) === base && contaMod() === m0, [a1.status(), a2.status(), a3.status(), a4.status()]);
      await anon.close();
      // Token e origine
      const tk = await tokenDi(admin.p, PD + '?campagna=' + CB);
      const s1 = await admin.ctx.request.post(PD + '?campagna=' + CB, { headers: ORIG, form: { azione: 'salva', design: '{"sezioni":[]}' }, maxRedirects: 0 });
      const s2 = await admin.ctx.request.post(PD + '?campagna=' + CB, { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: tk, azione: 'salva', design: '{"sezioni":[]}' }, maxRedirects: 0 });
      const s3 = await admin.ctx.request.post(PM, { headers: ORIG, form: { azione: 'elimina', id: String(MO) }, maxRedirects: 0 });
      const s4 = await admin.ctx.request.post(PD + '?campagna=' + CB, { headers: ORIG, form: { azione: 'modello', nome: 'Sic34 senza token', design: base }, maxRedirects: 0 });
      ok('2.12.0: salva, elimina, salva come modello senza token o da un altro sito: 403 e niente fatto', [s1, s2, s3, s4].every(r => r.status() === 403) && des(CB) === base && contaMod() === m0, [s1.status(), s2.status(), s3.status(), s4.status()]);
      // Un design cattivo in ogni punto
      const cattivo = {
        stile: { fondo: 'red;background:url(javascript:alert(34))', corpo: 'expression(alert(34))', link: '#fff" onmouseover="alert(34)', font: "arial;}</style><script>alert(34)</script>", larghezza: 99999, btn_raggio: '1;x:expression(1)' },
        sezioni: [{ id: '"><script>', layout: '100;drop', fondo: 'url(//evil.example/x.png)', spazio: -50, salvata: '1 OR 1=1', onclick: 'alert(34)', colonne: [[
          { id: 'x1', t: 'titolo', testo: '<script>alert(34)</script>Titolo', colore: '#12345g', livello: 'h1 onclick=alert(34)' },
          { id: 'x2', t: 'testo', html: '<p onclick="alert(34)" style="background:url(javascript:alert(34))">Ciao<script>alert(34)</script><img src=x onerror=alert(34)><iframe src="//evil.example"></iframe><a href="javascript:alert(34)">a</a> <a href=" java&#x09;script:alert(34)">b</a> <a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgzNCk8L3NjcmlwdD4=">c</a> <a href="vbscript:msgbox(34)">d</a> <a href="https://ok.example/">ok</a></p><style>*{x:expression(alert(34))}</style>' },
          { id: 'x3', t: 'immagine', src: 'javascript:alert(34)', alt: '"><script>alert(34)</script>', link: 'data:text/html;base64,PHNjcmlwdD4=' },
          { id: 'x4', t: 'immagine', src: 'data:image/svg+xml;base64,PHN2ZyBvbmxvYWQ9YWxlcnQoMzQpPg==', alt: 'svg' },
          { id: 'x5', t: 'pulsante', testo: 'B64', link: '~b64~amF2YXNjcmlwdDphbGVydCgzNCk=' },
          { id: 'x6', t: 'pulsante', testo: 'Vb', link: 'VBScript:msgbox(34)', fondo: 'expression(alert(34))' },
          { id: 'x7', t: 'social', reti: [{ r: 'javascript', url: 'javascript:alert(34)' }, { r: 'facebook', url: 'javascript:alert(34)' }, { r: 'x', url: 'https://x.example/ok' }] },
          { id: 'x8', t: 'video', url: 'javascript:alert(34)', src: 'https://evil.example/x.png" onerror="alert(34)' },
          { id: 'x9', t: 'html', html: '<script>alert(34)</script>' },
          { id: 'x10', t: 'piede', html: '<p><a href="{{ unsubscribe }}" onclick="alert(34)">Esci</a><svg onload=alert(34)></svg></p>', colore: 'black;behavior:url(x.htc)' },
        ]] }],
      };
      const sv = await admin.ctx.request.post(PD + '?campagna=' + CB, { headers: ORIG, form: { _csrf: tk, azione: 'salva', design: JSON.stringify(cattivo) } });
      const salvatoJ = des(CB);
      const vieti = /script|javascript|vbscript|expression|onerror|onclick|onmouseover|onload|data:|behavior|evil\.example|<iframe|<svg|<style|url\(/i;
      const d = JSON.parse(salvatoJ), st = d.stile, b = d.sezioni[0].colonne[0];
      // Il titolo e il testo alternativo sono testo semplice: possono contenere "<script>" come parole (all'uscita si fa l'escape).
      const senzaTesti = JSON.parse(salvatoJ); senzaTesti.sezioni[0].colonne[0].forEach(x => { if (x.id === 'x1') delete x.testo; if (x.id === 'x3') delete x.alt; });
      ok('2.12.0: design cattivo salvato solo dopo la pulizia: nel database niente codice, eventi, schemi pericolosi, url() né expression', sv.status() === 200 && salvatoJ !== base && !vieti.test(JSON.stringify(senzaTesti)) && !/"t":"html"/.test(salvatoJ), JSON.stringify(senzaTesti).match(new RegExp('.{0,80}(' + vieti.source + ').{0,80}', 'i')));
      ok('2.12.0: colori e caratteri fuori elenco tornano quelli di partenza; numeri dentro i limiti; il layout inventato torna a una colonna', st.fondo === '#f4f4f6' && st.corpo === '#ffffff' && st.link === '#6d28d9' && st.font === 'arial' && st.larghezza === 700 && st.btn_raggio === 8 && d.sezioni[0].layout === '100' && d.sezioni[0].fondo === '' && d.sezioni[0].spazio === 0 && d.sezioni[0].salvata === 0 && /^[a-z0-9]{1,16}$/.test(d.sezioni[0].id), { st, s: d.sezioni[0] });
      ok('2.12.0: il tipo di blocco inventato («html») sparisce, la proprietà inventata (onclick) anche', b.length === 9 && !b.some(x => x.t === 'html') && !('onclick' in d.sezioni[0]), b.map(x => x.t));
      const byId = Object.fromEntries(b.map(x => [x.id, x]));
      ok('2.12.0: link: javascript:, data:, vbscript: e il base64 non passano; https sì; social e video senza schemi pericolosi', byId.x3.src === '' && byId.x3.link === '' && byId.x4.src === '' && byId.x5.link === '' && byId.x6.link === '' && byId.x7.reti.length === 2 && byId.x7.reti[0].url === '' && byId.x7.reti[1].url === 'https://x.example/ok' && byId.x8.url === '' && byId.x8.src === '', [byId.x5.link, byId.x7.reti, byId.x8]);
      ok('2.12.0: testo con la formattazione: solo i tag dell\'elenco, link https tenuti, gli altri senza href', /<a href="https:\/\/ok\.example\/">ok<\/a>/.test(byId.x2.html) && (byId.x2.html.match(/<a>/g) || []).length === 4 && !/<img|<iframe|<style|style=|onclick/.test(byId.x2.html) && /<a href="\{\{ unsubscribe \}\}">Esci<\/a>/.test(byId.x10.html), byId.x2.html);
      const em = php('editor-email-cli.php', 'html', CB, I1).html;
      ok('2.12.0: l\'email di quel design: niente script, eventi, schemi pericolosi; il titolo con <script> esce come testo', !/<script|javascript:|vbscript:|onerror=|onclick=|onmouseover=|expression\(/i.test(em) && /&lt;script&gt;alert\(34\)&lt;\/script&gt;Titolo/.test(em), em.slice(0, 200));
      // JSON enorme, annidato, troppe sezioni e blocchi
      const big = JSON.stringify({ sezioni: [{ colonne: [[{ t: 'testo', html: '<p>' + 'x'.repeat(450000) + '</p>' }]] }] });
      const e1 = await (await admin.ctx.request.post(PD + '?campagna=' + CB, { headers: ORIG, form: { _csrf: tk, azione: 'salva', design: big } })).json();
      let nido = '1'; for (let i = 0; i < 300; i++) nido = '[' + nido + ']';
      const e2 = await (await admin.ctx.request.post(PD + '?campagna=' + CB, { headers: ORIG, form: { _csrf: tk, azione: 'salva', design: '{"sezioni":' + nido + '}' } })).json();
      const e3 = await (await admin.ctx.request.post(PD + '?campagna=' + CB, { headers: ORIG, form: { _csrf: tk, azione: 'salva', design: '"solo una stringa"' } })).json();
      ok('2.12.0: JSON enorme (oltre 400 KB), annidato 300 volte o non un oggetto: rifiutato con un messaggio, il design resta', !!e1.errore && !!e2.errore && !!e3.errore && des(CB) === salvatoJ, [e1.errore, e2.errore, e3.errore]);
      const tante = { sezioni: Array.from({ length: 200 }, () => ({ colonne: [Array.from({ length: 30 }, () => ({ t: 'spaziatore' }))] })) };
      await admin.ctx.request.post(PD + '?campagna=' + CB, { headers: ORIG, form: { _csrf: tk, azione: 'salva', design: JSON.stringify(tante) } });
      const dt = JSON.parse(des(CB));
      ok('2.12.0: 200 sezioni da 30 blocchi: ne restano al massimo 60 e 300 blocchi in tutto', dt.sezioni.length <= 60 && dt.sezioni.flatMap(s => s.colonne.flat()).length <= 300, [dt.sezioni.length, dt.sezioni.flatMap(s => s.colonne.flat()).length]);
      // Campagne partite, modelli e sezioni che non esistono o non sono sincronizzate
      const ci0 = des(CI);
      const tki = await tokenDi(admin.p, PD + '?campagna=' + CI);
      const r1 = await (await admin.ctx.request.post(PD + '?campagna=' + CI, { headers: ORIG, form: { _csrf: tki, azione: 'salva', design: base } })).json();
      ok('2.12.0: una campagna già inviata non cambia design dall\'editor', !!r1.errore && des(CI) === ci0, r1);
      const r2 = await admin.ctx.request.post(PD + '?modello=999999', { headers: ORIG, form: { _csrf: tk, azione: 'salva', design: base }, maxRedirects: 0 });
      ok('2.12.0: un modello che non esiste: nessun salvataggio (rimanda all\'elenco)', r2.status() === 302);
      const sez = JSON.stringify({ colonne: [[{ t: 'titolo', testo: 'Sic34 cambiata' }]] });
      const r3 = await (await admin.ctx.request.post(PD + '?campagna=' + CB, { headers: ORIG, form: { _csrf: tk, azione: 'sezione_aggiorna', id: String(SC), sezione: sez } })).json();
      const r4 = await (await admin.ctx.request.post(PD + '?campagna=' + CB, { headers: ORIG, form: { _csrf: tk, azione: 'sezione_aggiorna', id: '999999', sezione: sez } })).json();
      const r5 = await (await admin.ctx.request.post(PD + '?campagna=' + CB, { headers: ORIG, form: { _csrf: tk, azione: 'sezione_aggiorna', id: String(SS) + ' OR 1=1', sezione: sez } })).json();
      ok('2.12.0: «Aggiorna in tutte le email» su una sezione non sincronizzata o inesistente: rifiutato', !!r3.errore && !!r4.errore && uno(`SELECT json FROM {p}newsletter_sezioni_salvate WHERE id = ${SC}`) === '{}', [r3.errore, r4.errore, r5.errore || r5.messaggio]);
      ok('2.12.0: la campagna inviata che usa la sezione sincronizzata non cambia mai', des(CI) === ci0);
      // Modelli: usa un modello inattivo o inesistente, galleria con un nome storto, email di conferma con un modello non adatto
      const tkc = await tokenDi(admin.p, PCm + '?id=' + CB);
      const dPrima = des(CB);
      await admin.ctx.request.post(PCm + '?id=' + CB, { headers: ORIG, form: { _csrf: tkc, azione: 'usa_modello', modello_id: String(MO) }, maxRedirects: 0 });
      await admin.ctx.request.post(PCm + '?id=' + CB, { headers: ORIG, form: { _csrf: tkc, azione: 'usa_modello', modello_id: '999999' }, maxRedirects: 0 });
      ok('2.12.0: un modello inattivo o inesistente non entra nella campagna', des(CB) === dPrima);
      const tkm = await tokenDi(admin.p, PM);
      await admin.ctx.request.post(PM, { headers: ORIG, form: { _csrf: tkm, azione: 'galleria', slug: '../../includes/config' }, maxRedirects: 0 });
      await admin.ctx.request.post(PM, { headers: ORIG, form: { _csrf: tkm, azione: 'galleria', slug: '1-benvenuto', campagna: String(CI), in_campagna: '1' }, maxRedirects: 0 });
      await admin.ctx.request.post(PM, { headers: ORIG, form: { _csrf: tkm, azione: 'doi', doi: String(MO) }, maxRedirects: 0 });
      ok('2.12.0: galleria con un nome storto: niente; galleria in una campagna inviata: niente; email di conferma con un modello senza il tag: rifiutata', contaMod() === m0 && des(CI) === ci0 && (uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_doi_modello'") || '0') !== String(MO));
      // Nomi con codice
      await admin.ctx.request.post(PD + '?campagna=' + CB, { headers: ORIG, form: { _csrf: tk, azione: 'modello', nome: 'Sic34 <script>alert(34)</script><img src=x onerror=alert(34)>', design: base, attivo: '1' } });
      await admin.p.goto(PM, { waitUntil: 'networkidle' });
      const hm = await admin.p.content();
      ok('2.12.0: nome del modello con codice: esce come testo nell\'elenco dei Modelli', !/<script>alert\(34\)|<img src=x onerror=alert\(34\)/.test(hm) && /&lt;script&gt;alert\(34\)/.test(hm));
      // Redattore
      if (red.dentro) {
        const tr = await tokenDi(red.p, ADMIN + '/media.php');
        const prima = des(CB) + '|' + contaMod() + '|' + uno("SELECT COUNT(*) FROM {p}newsletter_sezioni_salvate");
        const rr = [];
        rr.push(await red.ctx.request.get(PD + '?campagna=' + CB, { maxRedirects: 0 }));
        rr.push(await red.ctx.request.get(PM, { maxRedirects: 0 }));
        for (const f of [{ azione: 'salva', design: base }, { azione: 'modello', nome: 'Sic34 dal redattore', design: base }, { azione: 'sezione_salva', nome: 'Sic34 dal redattore', sezione: sez }, { azione: 'sezione_aggiorna', id: String(SS), sezione: sez }, { azione: 'sezione_elimina', id: String(SC) }])
          rr.push(await red.ctx.request.post(PD + '?campagna=' + CB, { headers: ORIG, form: { _csrf: tr, ...f }, maxRedirects: 0 }));
        for (const f of [{ azione: 'nuovo', nome: 'Sic34 dal redattore' }, { azione: 'elimina', id: String(MO) }, { azione: 'attiva', id: String(MO), si: '1' }, { azione: 'galleria', slug: '1-benvenuto' }, { azione: 'doi', doi: '0' }])
          rr.push(await red.ctx.request.post(PM, { headers: ORIG, form: { _csrf: tr, ...f }, maxRedirects: 0 }));
        ok('2.12.0: il redattore non apre l\'editor delle email né i Modelli e non fa nessuna delle loro azioni',
          rr.every(x => [302, 403].includes(x.status())) && des(CB) + '|' + contaMod() + '|' + uno("SELECT COUNT(*) FROM {p}newsletter_sezioni_salvate") === prima && +uno(`SELECT attivo FROM {p}newsletter_modelli WHERE id = ${MO}`) === 0, rr.map(x => x.status()));
      }
      // Pulizia
      q("DELETE FROM {p}newsletter_campagne WHERE nome LIKE 'Sic34%'");
      q("DELETE FROM {p}newsletter_modelli WHERE nome LIKE 'Sic34%'");
      q("DELETE FROM {p}newsletter_sezioni_salvate WHERE nome LIKE 'Sic34%'");
      q("DELETE FROM {p}newsletter_iscritti WHERE email = 'sic1@sic34.it'");
    }
  }

  // === 35. (2.13.0) Il test A/B delle campagne: solo l'amministratore, solo con il token e dal pannello; percentuali
  // (oltre 50, zero, negative, con SQL), durate (oltre 23 ore o 7 giorni, zero), unità, criteri e cose da provare fuori
  // elenco rifiutati senza cambiare niente; tipo di campagna inventato rifiutato; la versione B non si scrive in una campagna
  // normale, in un test sull'oggetto o in una campagna già partita; il vincitore non si sceglie dal pannello; l'oggetto B con
  // codice esce come testo. Serve CMS_DIR.
  {
    const path = require('path'), { execFileSync } = require('child_process');
    const DIR35 = process.env.CMS_DIR || '';
    const PN = ADMIN + '/newsletter.php', PCm = ADMIN + '/newsletter-campagna.php', PD = ADMIN + '/newsletter-design.php';
    const ORIG = { 'Origin': BASE.replace(/(https?:\/\/[^/]+).*/, '$1') };
    if (!DIR35) ok('2.13.0: (senza CMS_DIR le prove del test A/B sono saltate)', true);
    else {
      const cli = (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, '..', 'prove', 'campagne-cli.php'), DIR35, ...a.map(String)], { encoding: 'utf8' }).trim().split('\n').pop());
      const q = (s) => cli('sql', s);
      const uno = (s) => { const r = q(s); return r && r[0] ? Object.values(r[0])[0] : null; };
      q("DELETE FROM {p}newsletter_campagne WHERE nome LIKE 'Sic35%'");
      q("INSERT INTO {p}newsletter_campagne (nome, tipo, oggetto, corpo, liste, stato, creato, aggiornato) VALUES ('Sic35 ab', 'ab', 'Sic35 A', '<p>A</p>', '', 'bozza', NOW(), NOW()), ('Sic35 normale', 'normale', 'Sic35', '<p>N</p>', '', 'bozza', NOW(), NOW()), ('Sic35 inviata', 'ab', 'Sic35 A', '<p>A</p>', '', 'inviata', NOW(), NOW())");
      const CA = +uno("SELECT id FROM {p}newsletter_campagne WHERE nome = 'Sic35 ab'"), CN = +uno("SELECT id FROM {p}newsletter_campagne WHERE nome = 'Sic35 normale'"), CI = +uno("SELECT id FROM {p}newsletter_campagne WHERE nome = 'Sic35 inviata'");
      q(`DELETE FROM {p}newsletter_ab WHERE campagna_id IN (${CA}, ${CN}, ${CI})`);
      q(`INSERT INTO {p}newsletter_ab (campagna_id, cosa, oggetto_b, percentuale, criterio, durata, unita) VALUES (${CA}, 'oggetto', 'Sic35 B', 25, 'aperture', 2, 'ore')`);
      q(`INSERT INTO {p}newsletter_ab (campagna_id, cosa, oggetto_b, editor_b, corpo_b, percentuale, criterio, durata, unita, fase, vincitore) VALUES (${CI}, 'design', 'Sic35 B', 'semplice', '<p>B</p>', 25, 'aperture', 2, 'ore', 'resto', 'b')`);
      const riga = (c) => JSON.stringify(q(`SELECT cosa, oggetto_b, anteprima_b, editor_b, corpo_b, html_b, percentuale, criterio, durata, unita, fase, vincitore FROM {p}newsletter_ab WHERE campagna_id = ${c}`)[0] || null);
      const ra0 = riga(CA), ri0 = riga(CI);
      const quante = () => +uno("SELECT COUNT(*) FROM {p}newsletter_campagne WHERE nome LIKE 'Sic35%'");
      const n0 = quante();
      const buono = { azione: 'sezione', sez: 'ab', cosa: 'oggetto', oggetto_b: 'Sic35 B', percentuale: '25', criterio: 'aperture', durata: '2', unita: 'ore' };
      // Senza login
      const anon = await browser.newContext();
      const a1 = await anon.request.post(PCm + '?id=' + CA, { form: { ...buono, percentuale: '50', oggetto_b: 'Sic35 da fuori' }, maxRedirects: 0 });
      const a2 = await anon.request.post(PN, { form: { azione: 'nuova', tipo: 'ab', nome: 'Sic35 anonimo' }, maxRedirects: 0 });
      ok('2.13.0: senza login il test A/B non cambia e non nasce nessuna campagna A/B', riga(CA) === ra0 && quante() === n0, [a1.status(), a2.status()]);
      await anon.close();
      // Token e origine
      const tk = await tokenDi(admin.p, PCm + '?id=' + CA);
      const s1 = await admin.ctx.request.post(PCm + '?id=' + CA, { headers: ORIG, form: { ...buono, percentuale: '50' }, maxRedirects: 0 });
      const s2 = await admin.ctx.request.post(PCm + '?id=' + CA, { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: tk, ...buono, percentuale: '50' }, maxRedirects: 0 });
      const s3 = await admin.ctx.request.post(PN, { headers: ORIG, form: { azione: 'nuova', tipo: 'ab', nome: 'Sic35 senza token' }, maxRedirects: 0 });
      ok('2.13.0: sezione Test A/B e Crea campagna A/B senza token o da un altro sito: 403 e niente fatto', [s1, s2, s3].every(r => r.status() === 403) && riga(CA) === ra0 && quante() === n0, [s1.status(), s2.status(), s3.status()]);
      // Valori manipolati
      const storti = [{ percentuale: '51' }, { percentuale: '100' }, { percentuale: '0' }, { percentuale: '-1' }, { percentuale: '25 OR 1=1' }, { percentuale: '1e2' }, { percentuale: '' }, { 'percentuale[]': '25' },
        { durata: '24' }, { durata: '0' }, { durata: '2; DROP TABLE x' }, { durata: '8', unita: 'giorni' }, { durata: '-3', unita: 'giorni' }, { unita: 'secondi' }, { unita: "ore' OR '1'='1" },
        { cosa: 'html' }, { cosa: '' }, { criterio: 'rimbalzi' }, { criterio: "aperture'--" }];
      for (const f of storti) { const x = { _csrf: tk, ...buono, oggetto_b: 'Sic35 cambiato', ...f }; if (f['percentuale[]']) delete x.percentuale; await admin.ctx.request.post(PCm + '?id=' + CA, { headers: ORIG, form: x, maxRedirects: 0 }); }
      ok('2.13.0: ' + storti.length + ' percentuali, durate, unità, criteri e cose da provare manipolati: rifiutati, il test non cambia', riga(CA) === ra0, riga(CA));
      // Tipo di campagna inventato
      for (const t of ['flusso', 'ab<script>', "ab' OR '1'='1", 'AB ']) await admin.ctx.request.post(PN, { headers: ORIG, form: { _csrf: tk, azione: 'nuova', tipo: t, nome: 'Sic35 tipo storto' }, maxRedirects: 0 });
      ok('2.13.0: tipo di campagna inventato: nessuna campagna nasce', quante() === n0 && +uno("SELECT COUNT(*) FROM {p}newsletter_campagne WHERE tipo NOT IN ('normale', 'ab')") === 0);
      // La versione B dove non c'è
      const cn0 = uno(`SELECT corpo FROM {p}newsletter_campagne WHERE id = ${CN}`);
      await admin.ctx.request.post(PCm + '?id=' + CN, { headers: ORIG, form: { _csrf: tk, ...buono, percentuale: '30' }, maxRedirects: 0 });
      await admin.ctx.request.post(PCm + '?id=' + CN, { headers: ORIG, form: { _csrf: tk, azione: 'sezione', sez: 'design', versione: 'b', editor: 'semplice', corpo: '<p>Sic35 B intrusa</p>' }, maxRedirects: 0 });
      await admin.ctx.request.post(PCm + '?id=' + CA, { headers: ORIG, form: { _csrf: tk, azione: 'sezione', sez: 'design', versione: 'b', editor: 'html', html: '<p>Sic35 B intrusa</p>' }, maxRedirects: 0 });
      ok('2.13.0: la sezione Test A/B e la versione B non si scrivono in una campagna normale né in un test sull\'oggetto', uno(`SELECT COUNT(*) FROM {p}newsletter_ab WHERE campagna_id = ${CN}`) == 0 && uno(`SELECT corpo FROM {p}newsletter_campagne WHERE id = ${CN}`) === cn0 && riga(CA) === ra0);
      // Campagna già partita
      await admin.ctx.request.post(PCm + '?id=' + CI, { headers: ORIG, form: { _csrf: tk, ...buono, cosa: 'design', percentuale: '50' }, maxRedirects: 0 });
      await admin.ctx.request.post(PCm + '?id=' + CI, { headers: ORIG, form: { _csrf: tk, azione: 'sezione', sez: 'design', versione: 'b', editor: 'semplice', corpo: '<p>Sic35 dopo</p>' }, maxRedirects: 0 });
      const tkd = await tokenDi(admin.p, PD + '?campagna=' + CI + '&versione=b');
      await admin.ctx.request.post(PD + '?campagna=' + CI + '&versione=b', { headers: ORIG, form: { _csrf: tkd, azione: 'salva', design: JSON.stringify({ sezioni: [{ id: 's1', colonne: [[{ id: 'b1', t: 'titolo', testo: 'Sic35 dopo' }]] }] }) }, maxRedirects: 0 });
      ok('2.13.0: in una campagna A/B già partita né il test né la versione B cambiano (pannello ed editor a blocchi)', riga(CI) === ri0, riga(CI));
      // Il vincitore non si sceglie dal pannello
      for (const az of ['scegli', 'vincitore', 'ab_scegli']) await admin.ctx.request.post(PCm + '?id=' + CI, { headers: ORIG, form: { _csrf: tk, azione: az, vincitore: 'a' }, maxRedirects: 0 });
      ok('2.13.0: azioni inventate per scegliere il vincitore: niente cambia', riga(CI) === ri0);
      // Oggetto B con codice
      await admin.ctx.request.post(PCm + '?id=' + CA, { headers: ORIG, form: { _csrf: tk, ...buono, oggetto_b: 'Sic35 <script>alert(35)</script><img src=x onerror=alert(35)>' }, maxRedirects: 0 });
      await admin.p.goto(PCm + '?id=' + CA + '&apri=ab', { waitUntil: 'networkidle' });
      const ha = await admin.p.content();
      ok('2.13.0: oggetto B con codice: salvato come testo, esce come testo nel riepilogo', !/<script>alert\(35\)|<img src=x onerror=alert\(35\)/.test(ha) && /&lt;script&gt;alert\(35\)/.test(ha));
      // Redattore
      if (red.dentro) {
        const tr = await tokenDi(red.p, ADMIN + '/media.php');
        const prima = riga(CA) + '|' + quante();
        const rr = [];
        rr.push(await red.ctx.request.get(PCm + '?id=' + CA, { maxRedirects: 0 }));
        rr.push(await red.ctx.request.post(PCm + '?id=' + CA, { headers: ORIG, form: { _csrf: tr, ...buono, percentuale: '50' }, maxRedirects: 0 }));
        rr.push(await red.ctx.request.post(PN, { headers: ORIG, form: { _csrf: tr, azione: 'nuova', tipo: 'ab', nome: 'Sic35 dal redattore' }, maxRedirects: 0 }));
        rr.push(await red.ctx.request.post(PD + '?campagna=' + CA + '&versione=b', { headers: ORIG, form: { _csrf: tr, azione: 'salva', design: '{"sezioni":[]}' }, maxRedirects: 0 }));
        ok('2.13.0: il redattore non apre il riepilogo e non cambia né crea un test A/B', rr.every(x => [302, 403].includes(x.status())) && riga(CA) + '|' + quante() === prima, rr.map(x => x.status()));
      }
      q(`DELETE FROM {p}newsletter_ab WHERE campagna_id IN (${CA}, ${CN}, ${CI})`);
      q("DELETE FROM {p}newsletter_campagne WHERE nome LIKE 'Sic35%'");
    }
  }

  // === 36. (2.14.0) Report delle campagne, statistiche, orario migliore: solo l'amministratore; id di campagne inesistenti,
  // non partite o storti non aprono niente; elenchi e CSV fuori elenco rifiutati; nel CSV le celle con le formule escono con
  // l'apostrofo; "Salva come lista" solo con il token e dal pannello; parametri del periodo, della lista e del confronto
  // manipolati senza errori del database; orario migliore con ore e giorni storti, o in un test A/B, rifiutato. Serve CMS_DIR.
  {
    const path = require('path'), { execFileSync } = require('child_process');
    const DIR36 = process.env.CMS_DIR || '';
    const PR = ADMIN + '/newsletter-report.php', PS = ADMIN + '/newsletter-statistiche.php', PCm = ADMIN + '/newsletter-campagna.php';
    const ORIG = { 'Origin': BASE.replace(/(https?:\/\/[^/]+).*/, '$1') };
    if (!DIR36) ok('2.14.0: (senza CMS_DIR le prove del report sono saltate)', true);
    else {
      const cli = (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, '..', 'prove', 'report-cli.php'), DIR36, ...a.map(String)], { encoding: 'utf8' }).trim().split('\n').pop());
      const q = (s) => cli('sql', s);
      const uno = (s) => { const r = q(s); return r && r[0] ? Object.values(r[0])[0] : null; };
      const pulisci = () => {
        const ids = (q("SELECT id FROM {p}newsletter_campagne WHERE nome LIKE 'Sic36%'") || []).map(r => r.id).join(',') || '0';
        q(`DELETE FROM {p}newsletter_eventi WHERE campagna_id IN (${ids})`); q(`DELETE FROM {p}newsletter_coda WHERE campagna_id IN (${ids})`);
        q(`DELETE FROM {p}newsletter_ab WHERE campagna_id IN (${ids})`); q("DELETE FROM {p}newsletter_campagne WHERE nome LIKE 'Sic36%'");
        q("DELETE FROM {p}newsletter_iscritti_liste WHERE iscritto_id IN (SELECT id FROM {p}newsletter_iscritti WHERE email LIKE '%@sic36.it')");
        q("DELETE FROM {p}newsletter_iscritti WHERE email LIKE '%@sic36.it'"); q("DELETE FROM {p}newsletter_liste WHERE nome LIKE 'Sic36%' OR nome LIKE '%alert(36)%' OR nome = 'Lista36 sic'");
      };
      pulisci();
      q("INSERT INTO {p}newsletter_iscritti (email, nome, cognome, stato, fonte, creato, aggiornato) VALUES ('sic36a@sic36.it', '=cmd|'' /C calc''!A0', '@SUM(1+1)', 'attivo', 'manuale', NOW(), NOW()), ('-2+3@sic36.it', '+39 333', '', 'attivo', 'manuale', NOW(), NOW())");
      q("INSERT INTO {p}newsletter_campagne (nome, oggetto, corpo, liste, stato, invio_at, destinatari, inviate, creato, aggiornato) VALUES ('Sic36 inviata', 'Sic36', '<p>x</p>', '', 'inviata', NOW(), 2, 2, NOW(), NOW()), ('Sic36 bozza', 'Sic36', '<p>x</p>', '', 'bozza', NULL, 0, 0, NOW(), NOW()), ('Sic36 ab', 'Sic36', '<p>x</p>', '', 'bozza', NULL, 0, 0, NOW(), NOW())");
      const CI = +uno("SELECT id FROM {p}newsletter_campagne WHERE nome = 'Sic36 inviata'"), CB = +uno("SELECT id FROM {p}newsletter_campagne WHERE nome = 'Sic36 bozza'"), CA = +uno("SELECT id FROM {p}newsletter_campagne WHERE nome = 'Sic36 ab'");
      q(`UPDATE {p}newsletter_campagne SET tipo = 'ab' WHERE id = ${CA}`);
      // Le due bozze vanno a una lista vera: così l'invio non si ferma prima per i destinatari, e la prova finale (valori buoni) passa.
      q("INSERT INTO {p}newsletter_liste (nome, descrizione, creato) VALUES ('Lista36 sic', '', NOW())");
      const L36 = +uno("SELECT id FROM {p}newsletter_liste WHERE nome = 'Lista36 sic'");
      q(`INSERT INTO {p}newsletter_iscritti_liste (iscritto_id, lista_id, aggiunto) SELECT id, ${L36}, NOW() FROM {p}newsletter_iscritti WHERE email LIKE '%@sic36.it'`);
      q(`UPDATE {p}newsletter_campagne SET liste = '${L36}' WHERE id IN (${CB}, ${CA})`);
      try { q(`INSERT IGNORE INTO {p}newsletter_ab (campagna_id, cosa, oggetto_b, percentuale, criterio, durata, unita) VALUES (${CA}, 'oggetto', 'Sic36 B', 25, 'aperture', 2, 'ore')`); } catch (e) {}
      q(`INSERT INTO {p}newsletter_coda (campagna_id, iscritto_id, stato, inviata_at) SELECT ${CI}, id, 'inviata', NOW() FROM {p}newsletter_iscritti WHERE email LIKE '%@sic36.it'`);
      q(`INSERT INTO {p}newsletter_eventi (campagna_id, flusso_id, passo, iscritto_id, tipo, url, creato) SELECT ${CI}, 0, 0, id, 'apertura', '', NOW() FROM {p}newsletter_iscritti WHERE email LIKE '%@sic36.it'`);
      q(`INSERT INTO {p}newsletter_eventi (campagna_id, flusso_id, passo, iscritto_id, tipo, url, creato) SELECT ${CI}, 0, 0, id, 'clic', 'https://esempio.it/sic36', NOW() FROM {p}newsletter_iscritti WHERE email = 'sic36a@sic36.it'`);
      const liste = () => +uno("SELECT COUNT(*) FROM {p}newsletter_liste WHERE nome LIKE 'Sic36%' OR nome LIKE '%alert(36)%'");
      // Senza login
      const anon = await browser.newContext();
      const a1 = await anon.request.get(PR + '?id=' + CI, { maxRedirects: 0 });
      const a2 = await anon.request.get(PR + '?id=' + CI + '&csv=aperto', { maxRedirects: 0 });
      const a3 = await anon.request.post(PR, { form: { id: String(CI), azione: 'lista', elenco: 'aperto', nome: 'Sic36 anonimo' }, maxRedirects: 0 });
      ok('2.14.0: senza login niente report, niente CSV, nessuna lista', [a1, a2, a3].every(r => [302, 403].includes(r.status())) && !/sic36a@sic36\.it/.test(await a2.text()) && liste() === 0, [a1.status(), a2.status(), a3.status()]);
      await anon.close();
      // Id storti, inesistenti o di campagne non partite
      const storti = [String(CB), '999999', '-1', '0', 'abc', `${CI} OR 1=1`, `${CI}'`, '1e3'];
      const esiti = [];
      for (const s of storti) { const r = await admin.ctx.request.get(PR + '?id=' + encodeURIComponent(s), { maxRedirects: 0 }); esiti.push([s, r.status(), (r.headers()['location'] || '').replace(/.*\//, '')]); }
      const rArr = await admin.ctx.request.get(PR + '?id[]=' + CI, { maxRedirects: 0 });
      esiti.push(['id[]', rArr.status(), (rArr.headers()['location'] || '').replace(/.*\//, '')]);
      ok('2.14.0: report con ' + esiti.length + ' id storti, inesistenti, di una bozza o in un array: nessun report, si torna alle campagne', esiti.every(([, st, loc]) => st === 302 && !/newsletter-report/.test(loc)), esiti);
      const c1 = await admin.ctx.request.get(PR + '?id=' + CB + '&csv=aperto', { maxRedirects: 0 });
      const c2 = await admin.ctx.request.get(PR + '?id=' + CI + '&csv=' + encodeURIComponent('../../includes/config.local.php'), { maxRedirects: 0 });
      const c3 = await admin.ctx.request.get(PR + '?id=' + CI + '&csv[]=aperto', { maxRedirects: 0 });
      const c4 = await admin.ctx.request.get(PR + '?id=' + CI + '&csv=' + encodeURIComponent("aperto' OR '1'='1"), { maxRedirects: 0 });
      ok('2.14.0: CSV di una bozza, con un percorso, in un array o con SQL: nessun file', [c1, c2, c4].every(r => r.status() === 302) && ![c1, c2, c3, c4].some(r => /text\/csv/.test(r.headers()['content-type'] || '')), [c1.status(), c2.status(), c3.status(), c4.status()]);
      // CSV con le formule
      const csv = await (await admin.ctx.request.get(PR + '?id=' + CI + '&csv=aperto')).text();
      const righe = csv.replace(/^﻿/, '').trim().split(/\r?\n/).slice(1);
      ok('2.14.0: CSV: le celle che cominciano con = + - @ escono con l\'apostrofo (nome, cognome, email)', righe.length === 2 && righe.some(r => r.includes("'=cmd|") && r.includes("'@SUM(1+1)")) && righe.some(r => r.startsWith("'-2+3@sic36.it") && r.includes("'+39 333")), righe);
      const csvL = await (await admin.ctx.request.get(PR + '?id=' + CI + '&csv=cliccato&url=' + encodeURIComponent('https://evil.example/'))).text();
      ok('2.14.0: CSV per un link che non è della campagna: il link si ignora (escono tutti quelli che hanno cliccato, niente errori)', csvL.trim().split(/\r?\n/).length === 2 && /sic36a@sic36\.it/.test(csvL));
      // CSRF e valori storti di "Salva come lista"
      const tk = await tokenDi(admin.p, PR + '?id=' + CI + '&scheda=aperture');
      const p1 = await admin.ctx.request.post(PR, { headers: ORIG, form: { id: String(CI), azione: 'lista', elenco: 'aperto', nome: 'Sic36 senza token' }, maxRedirects: 0 });
      const p2 = await admin.ctx.request.post(PR, { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: tk, id: String(CI), azione: 'lista', elenco: 'aperto', nome: 'Sic36 da fuori' }, maxRedirects: 0 });
      ok('2.14.0: «Salva come lista» senza token o da un altro sito: 403 e nessuna lista', [p1, p2].every(r => r.status() === 403) && liste() === 0, [p1.status(), p2.status()]);
      for (const f of [{ elenco: 'tutti' }, { elenco: "aperto' OR '1'='1" }, { 'elenco[]': 'aperto' }, { id: String(CB) }, { id: '999999' }, { azione: 'segmento', elenco: 'uscito' }, { azione: 'segmento', elenco: 'rimbalzo' }]) {
        const x = { _csrf: tk, id: String(CI), azione: 'lista', elenco: 'aperto', nome: 'Sic36 storta', ...f }; if (f['elenco[]']) delete x.elenco;
        await admin.ctx.request.post(PR, { headers: ORIG, form: x, maxRedirects: 0 });
      }
      ok('2.14.0: elenco inventato, in un array o con SQL, campagna non partita o inesistente, segmento di un elenco che non lo ammette: nessuna lista né segmento', liste() === 0 && +uno("SELECT COUNT(*) FROM {p}newsletter_segmenti WHERE nome LIKE 'Sic36%'") === 0);
      await admin.ctx.request.post(PR, { headers: ORIG, form: { _csrf: tk, id: String(CI), azione: 'lista', elenco: 'aperto', nome: 'Sic36 <img src=x onerror=alert(36)>' }, maxRedirects: 0 });
      const nomeL = uno("SELECT nome FROM {p}newsletter_liste WHERE nome LIKE 'Sic36%'") || '';
      await admin.p.goto(ADMIN + '/newsletter-contatti.php?scheda=liste', { waitUntil: 'networkidle' });
      ok('2.14.0: il nome della lista con del codice si salva senza tag e non esce come codice', !/[<>]/.test(nomeL) && !/<img src=x onerror=alert\(36\)>/.test(await admin.p.content()), nomeL);
      // Parametri del periodo, della lista e del confronto
      const periodi = ["periodo=' OR 1=1--", 'periodo[]=30', 'periodo=scelto&da=2025-01-01;DROP TABLE x&a=2025-02-01', 'periodo=scelto&da=9999-99-99&a=0000-00-00', 'periodo=scelto&da=1900-01-01&a=2999-12-31',
        'periodo=scelto&da[]=1&a[]=2', 'scheda=liste&lista=1 OR 1=1', 'scheda=liste&lista[]=1', 'scheda=liste&lista=-5', `confronta[]=${CI} OR 1=1&confronta[]=${CI}`, 'confronta[][]=1', 'confronta=1', `campagna[]=${CI}`, 'campagna=1 OR 1=1'];
      const male = [];
      for (const u of periodi) {
        const r = await admin.ctx.request.get(PS + '?' + u.replace(/ /g, '%20').replace(/'/g, '%27').replace(/;/g, '%3B'), { maxRedirects: 0 });
        const t = await r.text();
        if (![200, 302].includes(r.status()) || /SQLSTATE|Fatal error|Warning:|Uncaught/.test(t) || (r.status() === 302 && /newsletter-report\.php\?id=[1-9]/.test(r.headers()['location'] || ''))) male.push([u, r.status()]);
      }
      ok('2.14.0: ' + periodi.length + ' parametri di periodo, lista, confronto e campagna manipolati: nessun errore del database, nessun report aperto per sbaglio', male.length === 0, male);
      // Orario migliore storto e nel test A/B
      const tkc = await tokenDi(admin.p, PCm + '?id=' + CB);
      const prima = () => JSON.stringify(q(`SELECT stato, invio_at, orario_migliore, orario_ora FROM {p}newsletter_campagne WHERE id IN (${CB}, ${CA}) ORDER BY id`));
      const p0 = prima();
      for (const f of [{ orario_ora: '25' }, { orario_ora: '-1' }, { orario_ora: '10; DROP TABLE x' }, { 'orario_ora[]': '10' }, { orario_giorno: '2025-02-30' }, { orario_giorno: '2020-01-01' }, { 'orario_giorno[]': '2030-01-01' }, { orario_giorno: "2030-01-01' OR '1'='1" }]) {
        const x = { _csrf: tkc, azione: 'invia', quando: 'migliore', orario_giorno: '2030-01-01', orario_ora: '10', ...f };
        if (f['orario_ora[]']) delete x.orario_ora; if (f['orario_giorno[]']) delete x.orario_giorno;
        await admin.ctx.request.post(PCm + '?id=' + CB, { headers: ORIG, form: x, maxRedirects: 0 });
      }
      await admin.ctx.request.post(PCm + '?id=' + CA, { headers: ORIG, form: { _csrf: tkc, azione: 'invia', quando: 'migliore', orario_giorno: '2030-01-01', orario_ora: '10' }, maxRedirects: 0 });
      await admin.ctx.request.post(PCm + '?id=' + CB, { form: { azione: 'invia', quando: 'migliore', orario_giorno: '2030-01-01', orario_ora: '10' }, maxRedirects: 0 });
      ok('2.14.0: orario migliore con ore e giorni storti, senza token o in un test A/B: la campagna non cambia', prima() === p0, [p0, prima()]);
      await admin.ctx.request.post(PCm + '?id=' + CB, { headers: ORIG, form: { _csrf: tkc, azione: 'invia', quando: 'migliore', orario_giorno: '2030-01-01', orario_ora: '7' }, maxRedirects: 0 });
      const buona = q(`SELECT stato, invio_at, orario_migliore, orario_ora FROM {p}newsletter_campagne WHERE id = ${CB}`)[0] || {};
      ok('2.14.0: la prova di controllo con i valori buoni programma la campagna (le prove sopra sono vere)', buona.stato === 'programmata' && buona.invio_at === '2030-01-01 00:00:00' && +buona.orario_migliore === 1 && +buona.orario_ora === 7, buona);
      // Redattore
      if (red.dentro) {
        const tr = await tokenDi(red.p, ADMIN + '/media.php');
        const rr = [];
        rr.push(await red.ctx.request.get(PR + '?id=' + CI, { maxRedirects: 0 }));
        rr.push(await red.ctx.request.get(PR + '?id=' + CI + '&csv=aperto', { maxRedirects: 0 }));
        rr.push(await red.ctx.request.get(PS, { maxRedirects: 0 }));
        rr.push(await red.ctx.request.post(PR, { headers: ORIG, form: { _csrf: tr, id: String(CI), azione: 'lista', elenco: 'aperto', nome: 'Sic36 dal redattore' }, maxRedirects: 0 }));
        ok('2.14.0: il redattore non apre report, CSV e statistiche e non crea liste', rr.every(x => [302, 403].includes(x.status()) && !/text\/csv/.test(x.headers()['content-type'] || '')) && liste() <= 1 && +uno("SELECT COUNT(*) FROM {p}newsletter_liste WHERE nome = 'Sic36 dal redattore'") === 0, rr.map(x => x.status()));
      }
      pulisci();
    }
  }

  // === 37. (2.15.0) Automazioni: solo l'amministratore, solo con il token e dal pannello. Webhook verso indirizzi interni
  // (127.0.0.1, 169.254.169.254, 10.x, 192.168.x, [::1], IPv4 dentro IPv6, numeri decimali ed esadecimali, localhost, porte e
  // schemi strani) rifiutati al salvataggio; al momento della chiamata un indirizzo interno non parte e un rimando non si segue
  // (server finto locale, AU_LOCALE solo per la prova del rimando). Grafi con giri chiusi o enormi, tipi e campi inventati,
  // JSON annidato, inneschi e impostazioni manipolati; testo con codice nel nome, nei rami, nei tag. Serve CMS_DIR.
  {
    const path = require('path'), { execFileSync, spawn } = require('child_process');
    const DIR37 = process.env.CMS_DIR || '';
    const PF = ADMIN + '/newsletter-flussi.php';
    const ORIG = { 'Origin': BASE.replace(/(https?:\/\/[^/]+).*/, '$1') };
    if (!DIR37) ok('2.15.0: (senza CMS_DIR le prove delle automazioni sono saltate)', true);
    else {
      const cli = (env, ...a) => JSON.parse(execFileSync('php', [path.join(__dirname, '..', 'prove', 'automazioni-cli.php'), DIR37, ...a.map(String)], { encoding: 'utf8', env: Object.assign({}, process.env, env) }).trim().split('\n').pop());
      const q = (s) => cli({}, 'sql', s);
      const uno = (s) => { const r = q(s); return r && r[0] ? Object.values(r[0])[0] : null; };
      const quante = () => +uno("SELECT COUNT(*) FROM {p}newsletter_flussi WHERE nome LIKE 'Sic37%' OR nome LIKE '%alert(37)%'");
      const pulisci = () => { for (const r of q("SELECT id FROM {p}newsletter_flussi WHERE nome LIKE 'Sic37%' OR nome LIKE '%alert(37)%'") || []) { for (const t of ['newsletter_corsi', 'newsletter_flussi_registro', 'newsletter_flussi_numeri']) q(`DELETE FROM {p}${t} WHERE flusso_id = ${r.id}`); q(`DELETE FROM {p}newsletter_flussi WHERE id = ${r.id}`); } };
      pulisci();
      const n0 = (tipo, dati = {}, id = 1) => ({ id, tipo, x: 40, y: 40 + id * 100, dati });
      const grafo = (nodi, archi) => JSON.stringify({ nodi: [{ id: 0, tipo: 'innesco', x: 0, y: 0, dati: {} }].concat(nodi), archi: archi || nodi.map((x, i) => ({ da: i ? nodi[i - 1].id : 0, uscita: i ? 'poi' : 'poi', a: x.id })) });
      const tk = await tokenDi(admin.p, PF + '?nuovo=1');
      const salva = (campi, headers = ORIG, ctx = admin.ctx) => ctx.request.post(PF, { headers, form: Object.assign({ _csrf: tk, id: '0', azione: 'salva', nome: 'Sic37', stato: 'attiva', inneschi: '[{"tipo":"lista","id":0}]', grafo: grafo([n0('lista_aggiungi', { lista: 1 })]) }, campi), maxRedirects: 0 });
      // Senza login, senza token, da fuori, redattore
      const anon = await browser.newContext();
      const a1 = await anon.request.get(PF, { maxRedirects: 0 });
      const a2 = await salva({}, ORIG, anon);
      await anon.close();
      ok('2.15.0: senza login niente pagina delle automazioni e nessun salvataggio', [302, 403].includes(a1.status()) && [302, 403].includes(a2.status()) && quante() === 0, [a1.status(), a2.status()]);
      const s1 = await admin.ctx.request.post(PF, { headers: ORIG, form: { id: '0', nome: 'Sic37 senza token', grafo: grafo([n0('fine')]) }, maxRedirects: 0 });
      const s2 = await salva({ nome: 'Sic37 da fuori' }, { 'Origin': 'https://evil.example' });
      ok('2.15.0: salvare senza token o da un altro sito: 403 e nessuna automazione', [s1, s2].every(r => r.status() === 403) && quante() === 0, [s1.status(), s2.status()]);
      if (red.dentro) {
        const tr = await tokenDi(red.p, ADMIN + '/media.php');
        const r1 = await red.ctx.request.get(PF, { maxRedirects: 0 });
        const r2 = await salva({ _csrf: tr, nome: 'Sic37 redattore' }, ORIG, red.ctx);
        const r3 = await red.ctx.request.get(ADMIN + '/newsletter-design.php?flusso=1&nodo=1', { maxRedirects: 0 });
        ok('2.15.0: il redattore non apre le automazioni, non le salva, non apre il design di un passo', [r1, r2, r3].every(r => [302, 403].includes(r.status())) && quante() === 0, [r1.status(), r2.status(), r3.status()]);
      }
      // Webhook verso indirizzi interni: rifiutati al salvataggio
      const interni = ['http://127.0.0.1/x', 'https://127.0.0.1/x', 'https://169.254.169.254/latest/meta-data/', 'https://10.0.0.5/x', 'https://192.168.1.1/x', 'https://172.16.0.1/x', 'https://[::1]/x',
        'https://[::ffff:127.0.0.1]/x', 'https://[::ffff:a9fe:a9fe]/x', 'https://[fd00::1]/x', 'https://[fe80::1]/x', 'https://localhost/x', 'https://sito.localhost/x', 'https://2130706433/x', 'https://0x7f000001/x', 'https://127.1/x',
        'https://0.0.0.0/x', 'https://100.64.0.1/x', 'https://utente:pw@esempio.it/x', 'https://esempio.it:8080/x', 'javascript:alert(1)', 'file:///etc/passwd', 'gopher://127.0.0.1:25/x', 'ftp://esempio.it/x', 'https://esem pio.it/x', ''];
      const passati = [];
      for (const u of interni) { const r = await salva({ nome: 'Sic37 webhook', grafo: grafo([n0('webhook', { url: u })]) }); if (quante() > 0) { passati.push([u, r.status()]); pulisci(); } }
      ok('2.15.0: ' + interni.length + ' webhook verso indirizzi interni, nascosti, con credenziali, porte o schemi strani: nessuno si salva', passati.length === 0, passati);
      const rv = await salva({ nome: 'Sic37 webhook buono', grafo: grafo([n0('webhook', { url: 'https://93.184.216.34/ricevi' })]) });
      ok('2.15.0: la prova di controllo con un indirizzo pubblico in https si salva (le prove sopra sono vere)', quante() === 1, rv.status());
      pulisci();
      // Al momento della chiamata: senza la costante delle prove un indirizzo interno non parte; un rimando non si segue mai.
      const PW = +(process.env.SIC_WEBHOOK || 9296), FILE = path.join(require('os').tmpdir(), 'sic37-arrivi-' + process.pid + '.txt');
      try { require('fs').unlinkSync(FILE); } catch (e) {}
      const SRV = `require('http').createServer((q, s) => { require('fs').appendFileSync(process.argv[2], process.argv[1] + q.url + '\\n'); if (q.url === '/rimanda') { s.writeHead(302, { Location: 'http://127.0.0.1:' + (+process.argv[1] + 1) + '/segreto' }); return s.end(); } s.end('ok'); }).listen(+process.argv[1], '127.0.0.1');`;
      const srv = [spawn(process.execPath, ['-e', SRV, String(PW), FILE], { stdio: 'ignore' }), spawn(process.execPath, ['-e', SRV, String(PW + 1), FILE], { stdio: 'ignore' })];
      await new Promise(r => setTimeout(r, 600));
      const arrivi = () => { try { return require('fs').readFileSync(FILE, 'utf8').trim().split('\n').filter(Boolean); } catch (e) { return []; } };
      // I due server si spengono anche se una chiamata fallisce: rimasti accesi, il giro dopo trova le porte occupate.
      try {
      const w1 = cli({}, 'webhook', `http://127.0.0.1:${PW}/ok`);
      const w2 = cli({}, 'webhook', `https://[::1]:${PW}/ok`);
      ok('2.15.0: alla chiamata, senza la costante delle prove, 127.0.0.1 e [::1] non partono', /non chiamato/.test(w1.esito) && /non chiamato/.test(w2.esito) && arrivi().length === 0, [w1, w2, arrivi()]);
      const w3 = cli({ AU_LOCALE: `127.0.0.1:${PW}` }, 'webhook', `http://127.0.0.1:${PW}/rimanda`);
      ok('2.15.0: un rimando verso un indirizzo interno non si segue (il server interno non riceve niente)', /non lo seguo/.test(w3.esito) && arrivi().length === 1 && !arrivi().some(x => x.startsWith(String(PW + 1))), [w3, arrivi()]);
      } finally { srv.forEach(s => s.kill()); }
      try { require('fs').unlinkSync(FILE); } catch (e) {}
      // Grafi
      const g150 = grafo(Array.from({ length: 150 }, (_, i) => n0('attesa', { minuti: 5 }, i + 1)));
      const giro = grafo([n0('attesa', { minuti: 5 }, 1), n0('attesa', { minuti: 5 }, 2)], [{ da: 0, uscita: 'poi', a: 1 }, { da: 1, uscita: 'poi', a: 2 }, { da: 2, uscita: 'poi', a: 1 }]);
      let annidato = '{"a":1}'; for (let i = 0; i < 200; i++) annidato = '{"nodi":[' + annidato + ']}';
      for (const g of [g150, giro, annidato, 'non è json', '[]', '{"nodi":"x","archi":5}']) await salva({ nome: 'Sic37 grafo', grafo: g });
      await admin.ctx.request.post(PF, { headers: ORIG, form: { _csrf: tk, id: '0', azione: 'salva', nome: 'Sic37 grafo', stato: 'attiva', 'grafo[]': 'x' }, maxRedirects: 0 });
      ok('2.15.0: grafi con 150 blocchi, un giro chiuso, JSON annidato 200 volte, testo, elenchi o un array al posto del JSON: nessuno si salva', quante() === 0);
      // Tipi, campi, inneschi e impostazioni inventati o fuori misura: si salva solo l'elenco chiuso, nei limiti.
      const strano = JSON.stringify({ nodi: [{ id: 0, tipo: 'innesco', x: -5, y: 1e12, dati: { eval: 1 } }, { id: 1, tipo: 'email', x: 99999999, y: 0, dati: { oggetto: 'Sic37 ogg', ascolto: 1e12, orario: 'mezzanotte', ora: 99, editor: 'php', mittente_id: -4, design: { x: 1 } } },
        { id: 2, tipo: 'php', dati: { codice: 'system("id")' } }, { id: 3, tipo: 'percento', dati: { rami: [60, 40, 'x', { a: 1 }] } }, { id: 4, tipo: 'attesa', dati: { minuti: -10 } }, { id: 'x', tipo: 'fine' }],
        archi: [{ da: 0, uscita: 'poi', a: 1 }, { da: 1, uscita: 'poi', a: 3 }, { da: 3, uscita: 'p0', a: 4 }, { da: 3, uscita: 'p9', a: 2 }, { da: 1, uscita: 'segreta', a: 4 }, { da: 4, uscita: 'poi', a: 0 }, { da: 4, uscita: 'poi', a: 999 }] });
      const innStrani = JSON.stringify([{ tipo: 'eval', id: 1 }, { tipo: 'lista', id: 'abc' }, { tipo: 'anniversario', id: 9999, attr: 'AU_DATA_NON_CE' }].concat(Array.from({ length: 30 }, (_, i) => ({ tipo: 'form', id: i + 1 }))));
      await salva({ nome: 'Sic37 strano <img src=x onerror=alert(37)>', stato: 'hackerata', tag: '<script>alert(37)</script>, ok', inneschi: innStrani, rientro: '1', rientro_attesa: '99999999', uscita_segmento: '1 OR 1=1', grafo: strano });
      const rS = q("SELECT * FROM {p}newsletter_flussi WHERE nome LIKE 'Sic37 strano%' OR nome LIKE '%alert(37)%'")[0] || {};
      const gS = JSON.parse(rS.passi || '{"nodi":[],"archi":[]}'), iS = JSON.parse(rS.inneschi || '[]');
      const eS = gS.nodi.find(x => x.id === 1) || { dati: {} };
      ok('2.15.0: tipi inventati tolti, campi nei limiti (ascolto, ora, orario, editor, coordinate), archi da uscite inventate o verso nodi che non ci sono tolti',
        !gS.nodi.some(x => x.tipo === 'php') && eS.dati.ascolto === 525600 && eS.dati.ora === 23 && eS.dati.orario === 'subito' && eS.dati.editor === 'semplice' && eS.dati.mittente_id === 0 && eS.x === 6000 && (gS.nodi.find(x => x.id === 4) || { dati: {} }).dati.minuti === 1 && gS.archi.every(a => ['0poi1', '1poi3', '3p04'].includes(`${a.da}${a.uscita}${a.a}`)), [gS.nodi, gS.archi]);
      ok('2.15.0: inneschi inventati tolti, al massimo 10; stato inventato -> Inattiva; rientro al massimo un anno; tag senza tag HTML', iS.length === 10 && iS.every(x => ['lista', 'form', 'anniversario'].includes(x.tipo)) && rS.stato === 'inattiva' && +rS.rientro_attesa === 8760 && !/[<>]/.test(rS.tag || '') && JSON.parse(rS.uscita).segmento === 0, [iS, rS.stato, rS.rientro_attesa, rS.tag, rS.uscita]);
      await admin.p.goto(PF, { waitUntil: 'networkidle' });
      const html = await admin.p.content();
      ok('2.15.0: il nome con del codice esce come testo nell\'elenco', !/<img src=x onerror=alert\(37\)>/.test(html) && /&lt;img src=x onerror=alert\(37\)&gt;/.test(html));
      // Rami con condizioni inventate o con SQL; percentuali storte
      const reg = (c) => ({ op: 'e', gruppi: [{ op: 'e', condizioni: [c] }] });
      for (const r of [reg({ campo: "lista'; DROP TABLE x", op: 'in', valore: [1] }), reg({ campo: 'lista', op: 'in OR 1=1', valore: [1] }), reg({ campo: 'segmento', op: 'in', valore: '1 OR 1=1' }), { gruppi: 'x' }])
        await salva({ nome: 'Sic37 rami', grafo: grafo([n0('dividi', { rami: [{ nome: 'R', regole: r }] })]) });
      await salva({ nome: 'Sic37 percento', grafo: grafo([n0('percento', { rami: [99, 99, 99] })]) });
      await salva({ nome: 'Sic37 attributo', grafo: grafo([n0('attributo', { nome: 'EMAIL', valore: 'rubata@evil.example' })]) });
      await salva({ nome: 'Sic37 rami', inneschi: JSON.stringify([{ tipo: 'anniversario', id: 0, attr: "X'; DROP TABLE x" }]) });
      ok('2.15.0: rami con condizioni inventate o con SQL, percentuali oltre 100, attributo EMAIL, anniversario con un attributo inventato: nessuno si salva', +uno("SELECT COUNT(*) FROM {p}newsletter_flussi WHERE nome IN ('Sic37 rami', 'Sic37 percento', 'Sic37 attributo')") === 0);
      // Attività: togliere il cammino di un'altra automazione, parametri storti
      const fS = +rS.id || 0;
      q(`INSERT INTO {p}newsletter_corsi (flusso_id, iscritto_id, passo, prossimo, stato, giro, creato, aggiornato) VALUES (${fS}, 999999, 1, NOW(), 'attivo', 1, NOW(), NOW())`);
      const corso = +uno(`SELECT id FROM {p}newsletter_corsi WHERE flusso_id = ${fS} AND iscritto_id = 999999`);
      await admin.ctx.request.post(PF, { headers: ORIG, form: { _csrf: tk, id: String(fS + 100000), azione: 'togli', corso: String(corso) }, maxRedirects: 0 });
      await admin.ctx.request.post(PF, { headers: ORIG, form: { id: String(fS), azione: 'togli', corso: String(corso) }, maxRedirects: 0 });
      ok('2.15.0: "Togli" con l\'automazione sbagliata o senza token: il cammino resta', uno(`SELECT stato FROM {p}newsletter_corsi WHERE id = ${corso}`) === 'attivo');
      const male = [];
      for (const u of [`id=${fS}&scheda=attivita&passo=abc`, `id=${fS}&scheda=attivita&passo[]=1`, `id=${fS}&scheda=attivita&pag=-5`, `id=${fS}&scheda=attivita&email=${encodeURIComponent("x' OR '1'='1")}`, `id=${fS}&scheda=attivita&email[]=x`, 'id=1 OR 1=1', 'id[]=1', `id=${fS}&scheda[]=x`, 'nuovo=1&modello[]=x', 'nuovo=1&modello=../../x']) {
        const r = await admin.ctx.request.get(PF + '?' + u, { maxRedirects: 0 });
        const t = await r.text();
        if (![200, 302, 404].includes(r.status()) || /SQLSTATE|Fatal error|Warning:|Uncaught/.test(t)) male.push([u, r.status()]);
      }
      ok('2.15.0: parametri della pagina e dell\'attività manipolati: nessun errore', male.length === 0, male);
      const d1 = await admin.ctx.request.get(ADMIN + `/newsletter-design.php?flusso=${fS}&nodo=4`, { maxRedirects: 0 });
      const d2 = await admin.ctx.request.get(ADMIN + `/newsletter-design.php?flusso=${fS}&nodo=999`, { maxRedirects: 0 });
      ok('2.15.0: l\'editor a blocchi non si apre su un passo che non è un\'email o che non c\'è', d1.status() === 302 && d2.status() === 302, [d1.status(), d2.status()]);
      q(`DELETE FROM {p}newsletter_corsi WHERE iscritto_id = 999999`);
      pulisci();
    }
  }

  // === 38. (2.16.0) Form come Brevo e pagine pubbliche (includes/form-brevo.php, includes/newsletter-consenso.php).
  // Iframe e clickjacking: frame-ancestors * solo sulla pagina ospitata /modulo/<slug>/, ogni altra pagina (anche il 404 di
  // /modulo/, un form spento, le pagine della newsletter, il pannello) resta 'self'. Link delle preferenze e form di profilo
  // con l'id di un altro contatto. Questionario della disiscrizione con testo lunghissimo, emoji e codice. Campi nascosti e
  // campi del server forzati nel POST, iscrizione a liste e gruppi che il form non offre, uscita da una lista non della
  // campagna. XSS nei messaggi, nella dichiarazione GDPR, nei nomi di gruppi e pagine. Indirizzi dopo l'invio pericolosi.
  // Domanda di controllo riusata o falsa. Redattore, senza token, da fuori. Serve CMS_DIR (prove/form-brevo-cli.php).
  {
    const path = require('path'), { execFileSync } = require('child_process');
    const DIR38 = process.env.CMS_DIR || '';
    const ORIG = { 'Origin': BASE.replace(/(https?:\/\/[^/]+).*/, '$1') };
    const PFo = ADMIN + '/form.php', PNc = ADMIN + '/newsletter-consenso.php', API = BASE + PAN + '/api/modulo.php';
    if (!DIR38) ok('2.16.0: (senza CMS_DIR le prove dei form e delle pagine pubbliche sono saltate)', true);
    else {
      const cli = (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, '..', 'prove', 'form-brevo-cli.php'), DIR38, ...a.map(String)], { encoding: 'utf8' }).trim().split('\n').pop());
      const q = (s) => cli('sql', s);
      const uno = (s) => { const r = q(s); return r && r[0] ? Object.values(r[0])[0] : null; };
      const sblocca = () => { q('DELETE FROM {p}sicurezza_tentativi'); q('UPDATE {p}moduli_invii SET aggiornato = aggiornato - INTERVAL 2 HOUR WHERE aggiornato > NOW() - INTERVAL 2 HOUR'); q("UPDATE {p}newsletter_iscritti SET aggiornato = aggiornato - INTERVAL 2 HOUR WHERE email LIKE '%@fb-prova.it'"); };
      cli('pulisci');
      const L = cli('prepara');
      const campi = [{ etichetta: 'Email', tipo: 'email', obbligatorio: true, attributo: 'EMAIL' }, { etichetta: 'Nome', tipo: 'testo', attributo: 'NOME' },
        { etichetta: 'Liste', tipo: 'liste', liste: [L.novita] }, { etichetta: 'Gruppi', tipo: 'gruppi' }, { etichetta: 'Statistiche', tipo: 'tracciamento' }];
      const F = cli('crea_form', JSON.stringify({ nome: 'FB Sic38', tipo: 'iscrizione', lista_id: L.principale, conferma: 'nessuna', attivo: 1, campi, gdpr: 1, dopo_invio: 'messaggio' }));
      const FP = cli('crea_form', JSON.stringify({ nome: 'FB Sic38 profilo', tipo: 'profilo', attivo: 1, campi: [{ etichetta: 'Email', tipo: 'email', obbligatorio: true, attributo: 'EMAIL' }, { etichetta: 'Nome', tipo: 'testo', attributo: 'NOME' }] }));
      const FS = cli('crea_form', JSON.stringify({ nome: 'FB Sic38 spento', tipo: 'iscrizione', lista_id: L.principale, conferma: 'nessuna', attivo: 0, campi }));
      ok('2.16.0: preparazione (tre form della prova)', F > 0 && FP > 0 && FS > 0, [F, FP, FS]);
      const slug = uno(`SELECT slug FROM {p}moduli WHERE id = ${F}`), slugP = uno(`SELECT slug FROM {p}moduli WHERE id = ${FP}`), slugS = uno(`SELECT slug FROM {p}moduli WHERE id = ${FS}`);
      const A = cli('contatto', 'sic38a@fb-prova.it', `${L.principale}`), Bc = cli('contatto', 'sic38b@fb-prova.it', `${L.principale}`);
      const anon = await browser.newContext();
      // Clickjacking: solo la pagina ospitata si mette in un iframe.
      const fa = (r) => (r.headers()['content-security-policy'] || '') + ' | XFO ' + (r.headers()['x-frame-options'] || '');
      const osp = await anon.request.get(BASE + '/modulo/' + slug + '/', { maxRedirects: 0 });
      ok('2.16.0: la pagina ospitata del form si mette in un iframe (frame-ancestors *, niente X-Frame-Options)', osp.status() === 200 && /frame-ancestors \*/.test(fa(osp)) && !osp.headers()['x-frame-options'], fa(osp));
      const prefA = cli('link_pref', A).replace(/^https?:\/\/[^/]+(\/test)?/, BASE);
      const altre = [BASE + '/', BASE + '/modulo/non-esiste/', BASE + '/modulo/' + slugS + '/', BASE + '/modulo/' + slug + '/../', BASE + '/modulo/' + slug + '/x/', prefA, cli('link_uscita', A, 0).replace(/^https?:\/\/[^/]+(\/test)?/, BASE), BASE + '/newsletter-conferma/?t=00', ADMIN + '/login.php', PFo];
      const male = [];
      for (const u of altre) {
        const r = await anon.request.get(u, { maxRedirects: 0 });
        if (/frame-ancestors \*/.test(fa(r)) || (r.status() === 200 && !/frame-ancestors 'self'|frame-ancestors 'none'/.test(fa(r)) && !/SAMEORIGIN|DENY/.test(fa(r)))) male.push([u, r.status(), fa(r)]);
      }
      ok('2.16.0: clickjacking: ogni altra pagina (404 di /modulo/, form spento, preferenze, uscita, pannello) resta protetta', male.length === 0, male);
      const capt = await anon.request.get(BASE + PAN + '/api/captcha.php');
      const mod = await anon.request.post(API, { form: { modulo: 'form:' + F }, maxRedirects: 0 });
      ok('2.16.0: la sfida del captcha si legge da un altro sito (serve al codice HTML), la risposta del form no', capt.headers()['access-control-allow-origin'] === '*' && !mod.headers()['access-control-allow-origin'], [capt.headers()['access-control-allow-origin'], mod.headers()['access-control-allow-origin']]);
      // Il link delle preferenze di un altro contatto.
      const tA = new URL(cli('link_pref', A)).searchParams.get('t');
      const sbagliato = BASE + '/newsletter-preferenze/?i=' + Bc + '&t=' + tA;
      const g1 = await anon.request.get(sbagliato);
      const p1 = await anon.request.post(sbagliato, { form: { azione: 'esci' }, maxRedirects: 0 });
      ok('2.16.0: link delle preferenze con l\'id di un altro contatto: non valido, niente dati, niente uscita', /non è valido/.test(await g1.text()) && !(await g1.text()).includes('sic38b@') && uno(`SELECT stato FROM {p}newsletter_iscritti WHERE id = ${Bc}`) === 'attivo', p1.status());
      const g2 = await anon.request.get(BASE + '/modulo/' + slugP + '/?i=' + Bc + '&t=' + tA);
      sblocca();
      const r2 = cli('ricevi', FP, JSON.stringify({ pi: String(Bc), pt: tA, c_email: 'sic38b@fb-prova.it', c_nome: 'Rubato' }));
      ok('2.16.0: form di profilo con l\'id di un altro contatto: niente form, e l\'invio forzato si ferma ("link")', !/<form/.test(await g2.text()) && r2 === 'link' && uno(`SELECT nome FROM {p}newsletter_iscritti WHERE id = ${Bc}`) !== 'Rubato', r2);
      const r3 = cli('ricevi', FP, JSON.stringify({ pi: String(A), pt: tA, c_email: 'sic38b@fb-prova.it', c_nome: 'Cambiato' }));
      ok('2.16.0: form di profilo: l\'email di un altro scritta nel campo non cambia l\'email né tocca l\'altro contatto', r3 === '' && uno(`SELECT email FROM {p}newsletter_iscritti WHERE id = ${A}`) === 'sic38a@fb-prova.it' && uno(`SELECT nome FROM {p}newsletter_iscritti WHERE id = ${Bc}`) === 'Prova', r3);
      // Il questionario: testo lunghissimo, emoji, codice, motivo inventato.
      const esci = cli('link_uscita', Bc, 0).replace(/^https?:\/\/[^/]+(\/test)?/, BASE);
      await anon.request.post(esci, { form: {}, maxRedirects: 0 });
      await anon.request.post(esci, { form: { motivo: 'inventato', altro: 'x' }, maxRedirects: 0 });
      ok('2.16.0: questionario con un motivo inventato: non si salva', +uno(`SELECT COUNT(*) FROM {p}newsletter_uscite_motivi WHERE contatto_id = ${Bc}`) === 0);
      const lungo = '<script>alert(38)</script>\u{1F600}' + 'a'.repeat(20000);
      const qr = await anon.request.post(esci, { form: { motivo: 'altro', altro: lungo }, maxRedirects: 0 });
      const salvato = String(uno(`SELECT altro FROM {p}newsletter_uscite_motivi WHERE contatto_id = ${Bc} AND motivo = 'altro'`) || '');
      ok('2.16.0: questionario con 20000 caratteri, emoji e codice: tagliato a 500, emoji come entità, niente tag', qr.status() === 200 && salvato.length > 0 && salvato.length <= 500 && /&#x1F600;/.test(salvato) && !/<script/.test(salvato), [qr.status(), salvato.length, salvato.slice(0, 40)]);
      // Iscrizione a liste e gruppi che il form non offre; campi del server forzati.
      sblocca();
      const nCont = () => +uno("SELECT COUNT(*) FROM {p}newsletter_iscritti WHERE email LIKE 'sic38%@fb-prova.it'");
      const n0 = nCont();
      const rl = [cli('ricevi', F, JSON.stringify({ c_email: 'sic38c@fb-prova.it', privacy: '1', c_liste: [String(L.nascosta)] })),
        cli('ricevi', F, JSON.stringify({ c_email: 'sic38c@fb-prova.it', privacy: '1', c_liste: ['1 OR 1=1'] })),
        cli('ricevi', F, JSON.stringify({ c_email: 'sic38c@fb-prova.it', privacy: '1', c_liste: Array.from({ length: 2000 }, (_, i) => String(i)) }))];
      ok('2.16.0: liste che il form non offre (un altro id, SQL, 2000 id): l\'invio si ferma, nessun contatto', rl.every(x => /^campo:/.test(x)) && nCont() === n0, rl);
      sblocca();
      const rf = cli('ricevi', F, JSON.stringify({ c_email: 'sic38d@fb-prova.it', privacy: '1', c_liste: [String(L.novita)], stato: 'bloccato', tracciamento: '1', lista_id: String(L.nascosta), liste: [String(L.nascosta)], tipo: 'profilo', pi: String(A), pt: tA, c_inventato: '<b>x</b>', ospitata: '1', fonte: 'admin', aggiorna_esistenti: '1' }));
      const D = uno("SELECT id FROM {p}newsletter_iscritti WHERE email = 'sic38d@fb-prova.it'");
      const dl = (q(`SELECT lista_id FROM {p}newsletter_iscritti_liste WHERE iscritto_id = ${+D} ORDER BY lista_id`) || []).map(x => +x.lista_id);
      const dati = String(uno(`SELECT dati FROM {p}moduli_invii WHERE email = 'sic38d@fb-prova.it'`) || '');
      ok('2.16.0: campi forzati nel POST (stato, lista, liste, tipo, link di profilo, campo inventato): entra solo nelle liste del form, i campi in più non si salvano', rf === '' && uno(`SELECT stato FROM {p}newsletter_iscritti WHERE id = ${+D}`) === 'attivo' && JSON.stringify(dl) === JSON.stringify([L.principale, L.novita].sort((a, b) => a - b)) && !/inventato|bloccato/.test(dati) && uno(`SELECT nome FROM {p}newsletter_iscritti WHERE id = ${A}`) === 'Cambiato', [rf, dl, dati]);
      q("UPDATE {p}settings SET setting_value = '1' WHERE setting_key = 'newsletter_gruppi_attivi'");
      cli('sql', "INSERT INTO {p}newsletter_gruppi_consenso (nome, descrizione, ingresso, creato) VALUES ('FB Sic38 <img src=x onerror=alert(381)>', '<script>alert(382)</script>', 'manual', NOW())");
      const G = +uno("SELECT id FROM {p}newsletter_gruppi_consenso WHERE nome LIKE 'FB Sic38%'");
      sblocca();
      const rg = cli('ricevi', F, JSON.stringify({ c_email: 'sic38e@fb-prova.it', privacy: '1', c_gruppi: [String(G + 1000)] }));
      ok('2.16.0: un gruppo di consenso che non c\'è: l\'invio si ferma', /^campo:/.test(rg), rg);
      const pref = await anon.request.get(prefA);
      const ph = await pref.text();
      ok('2.16.0: nome e descrizione di un gruppo con del codice escono come testo nel centro preferenze', !/<img src=x onerror=alert\(381\)>|<script>alert\(382\)/.test(ph) && /&lt;img src=x onerror=alert\(381\)&gt;/.test(ph), ph.length);
      // Uscita da una lista: una lista non della campagna, una firma della campagna falsa.
      const camp = cli('campagna', 'FB Sic38', `${L.principale}`);
      const lu = cli('link_uscita', A, camp).replace(/^https?:\/\/[^/]+(\/test)?/, BASE);
      const pl = await anon.request.post(lu, { form: { azione: 'lista', lista: String(L.novita) }, maxRedirects: 0 });
      const pl2 = await anon.request.post(lu.replace(/k=[a-f0-9]+/, 'k=' + '0'.repeat(24)), { form: { azione: 'lista', lista: String(L.principale) }, maxRedirects: 0 });
      ok('2.16.0: uscita da una lista non della campagna, o con la firma della campagna falsa: niente cambia', +uno(`SELECT COUNT(*) FROM {p}newsletter_liste_uscite WHERE contatto_id = ${A}`) === 0 && uno(`SELECT stato FROM {p}newsletter_iscritti WHERE id = ${A}`) === 'attivo', [pl.status(), pl2.status()]);
      // XSS nei messaggi, nella dichiarazione, nel nome di una pagina di disiscrizione, dal pannello.
      const tk = await tokenDi(admin.p, PFo + '?id=' + F + '&passo=3');
      await admin.ctx.request.post(PFo + '?id=' + F + '&passo=3', { headers: ORIG, form: { _csrf: tk, id: String(F), passo: '3', dopo_invio: 'messaggio', 'messaggi[riuscita]': '<script>alert(383)</script>Fatto', 'messaggi[obbligatorio]': '<img src=x onerror=alert(384)> {campo}', vai: 'resta' }, maxRedirects: 0 });
      await admin.ctx.request.post(PFo + '?id=' + F + '&passo=2', { headers: ORIG, form: { _csrf: tk, id: String(F), passo: '2', titolo: '<svg onload=alert(385)>', gdpr_testo: '<img src=x onerror=alert(386)>Dichiarazione', campi: JSON.stringify(campi.concat([{ etichetta: '<img src=x onerror=alert(387)>', tipo: 'testo' }])), vai: 'resta' }, maxRedirects: 0 });
      const ms = String(uno(`SELECT CONCAT(COALESCE(messaggi, ''), '|', COALESCE(gdpr_testo, '')) FROM {p}moduli WHERE id = ${F}`));
      const h1 = await (await anon.request.get(BASE + '/modulo/' + slug + '/')).text();
      const h2 = await (await anon.request.get(BASE + '/modulo/' + slug + '/?fatto=1&f=' + F)).text();
      const h3 = await (await anon.request.get(BASE + '/modulo/' + slug + '/?modulo=campo%3ANome&f=' + F)).text();
      ok('2.16.0: messaggi e dichiarazione salvati senza tag', !/<script|<img/i.test(ms) && /alert\(383\)/.test(ms), ms);
      ok('2.16.0: titolo, dichiarazione, etichette, messaggio di riuscita e d\'errore con del codice escono come testo nella pagina ospitata', !/<svg onload|<img src=x onerror|<script>alert\(38[3-7]\)/.test(h1 + h2 + h3) && /&lt;svg onload=alert\(385\)&gt;/.test(h1) && /&lt;img src=x onerror=alert\(387\)&gt;/.test(h1), [h1.length, h2.length, h3.length]);
      const tkN = await tokenDi(admin.p, PNc + '?scheda=pagine&nuova=1');
      await admin.ctx.request.post(PNc, { headers: ORIG, form: { _csrf: tkN, azione: 'pagina', scheda: 'pagine', id: '0', 'pg[nome]': 'FB Sic38 <script>alert(388)</script>', 'pg[uscita_titolo]': '<img src=x onerror=alert(389)>Esci' }, maxRedirects: 0 });
      const pid = +uno("SELECT id FROM {p}newsletter_pagine_uscita WHERE nome LIKE 'FB Sic38%'");
      q(`UPDATE {p}newsletter_campagne SET pagina_uscita_id = ${pid} WHERE id = ${camp}`);
      const hp = await (await anon.request.get(lu)).text();
      const ha = await (await admin.ctx.request.get(PNc + '?scheda=pagine')).text();
      ok('2.16.0: nome e titolo di una pagina di disiscrizione con del codice: salvati senza tag, escono come testo', pid > 0 && !/<script>alert\(388\)|<img src=x onerror=alert\(389\)/.test(hp + ha) && /alert\(389\)|Esci/.test(hp), pid);
      // Indirizzi dopo l'invio e dopo il clic pericolosi.
      const tk4 = await tokenDi(admin.p, PFo + '?id=' + F + '&passo=4');
      const urlMale = ['javascript:alert(1)', '//evil.example/x', '/\\evil.example', 'data:text/html,x', 'https://evil.example/"><script>'];
      for (const u of urlMale) {
        await admin.ctx.request.post(PFo + '?id=' + F + '&passo=3', { headers: ORIG, form: { _csrf: tk, id: String(F), passo: '3', dopo_invio: 'url', dopo_url: u, vai: 'resta' }, maxRedirects: 0 });
        await admin.ctx.request.post(PFo + '?id=' + F + '&passo=4', { headers: ORIG, form: { _csrf: tk4, id: String(F), passo: '4', lista_id: String(L.principale), conferma: 'doppia', doi_url: u, vai: 'resta' }, maxRedirects: 0 });
      }
      const du = q(`SELECT dopo_url, doi_url, dopo_invio FROM {p}moduli WHERE id = ${F}`)[0] || {};
      ok('2.16.0: indirizzi pericolosi dopo l\'invio e dopo il clic (javascript:, //, data:, virgolette): non si salvano', du.dopo_url === '' && du.doi_url === '' && du.dopo_invio === 'messaggio', du);
      // La domanda di controllo: riusata, falsa, risposta sbagliata.
      const dom = await (await (await browser.newContext({ javaScriptEnabled: false })).request.get(BASE + '/modulo/' + slug + '/')).text();
      const tok = (dom.match(/name="domanda" value="([^"]+)"/) || [])[1] || '';
      const [, x1, x2] = dom.match(/fa (\w+) più (\w+)/) || [];
      const num = ['zero', 'uno', 'due', 'tre', 'quattro', 'cinque', 'sei', 'sette', 'otto', 'nove', 'dieci'];
      const giusta = String(num.indexOf(x1) + num.indexOf(x2));
      const base = { modulo: 'form:' + F, pagina: '/modulo/' + slug + '/', ospitata: '1', c_email: 'sic38f@fb-prova.it', privacy: '1' };
      sblocca();
      const d1 = await anon.request.post(API, { form: { ...base, domanda: tok, domanda_risposta: String(+giusta + 1) }, maxRedirects: 0 });
      const d2 = await anon.request.post(API, { form: { ...base, domanda: Buffer.from('a'.repeat(24) + '|9999999999|' + 'b'.repeat(64)).toString('base64'), domanda_risposta: '5' }, maxRedirects: 0 });
      const d3 = await anon.request.post(API, { form: { ...base, domanda: tok, domanda_risposta: giusta }, maxRedirects: 0 });
      sblocca();
      const d4 = await anon.request.post(API, { form: { ...base, c_email: 'sic38g@fb-prova.it', domanda: tok, domanda_risposta: giusta }, maxRedirects: 0 });
      ok('2.16.0: domanda di controllo: sbagliata e falsa no, giusta una volta sola (riusata: no)', d1.status() === 200 && d2.status() === 200 && [302, 303].includes(d3.status()) && d4.status() === 200 && !!uno("SELECT id FROM {p}newsletter_iscritti WHERE email = 'sic38f@fb-prova.it'") && !uno("SELECT id FROM {p}newsletter_iscritti WHERE email = 'sic38g@fb-prova.it'"), [d1.status(), d2.status(), d3.status(), d4.status()]);
      const dPag = await anon.request.post(API, { form: { ...base, pagina: '//evil.example/', ospitata: '' }, maxRedirects: 0 });
      ok('2.16.0: una pagina di ritorno verso un altro sito: si torna dentro il sito', [302, 303].includes(dPag.status()) && !/evil\.example/.test(dPag.headers()['location'] || ''), dPag.headers()['location']);
      // Redattore, senza token, da fuori.
      const tkC = await tokenDi(admin.p, PNc);
      const s1 = await admin.ctx.request.post(PNc, { headers: ORIG, form: { azione: 'gruppo', scheda: 'gruppi', id: '0', nome: 'FB Sic38 senza token' }, maxRedirects: 0 });
      const s2 = await admin.ctx.request.post(PNc, { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: tkC, azione: 'gruppi_attivi', scheda: 'gruppi' }, maxRedirects: 0 });
      const s3 = await admin.ctx.request.post(PFo, { headers: { 'Origin': 'https://evil.example' }, form: { _csrf: tk, id: String(F), azione: 'elimina' }, maxRedirects: 0 });
      ok('2.16.0: Pagine e consenso e Form senza token o da un altro sito: 403, niente cambia', [s1, s2, s3].every(r => r.status() === 403) && !uno("SELECT id FROM {p}newsletter_gruppi_consenso WHERE nome = 'FB Sic38 senza token'") && uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_gruppi_attivi'") === '1' && !!uno(`SELECT id FROM {p}moduli WHERE id = ${F}`), [s1.status(), s2.status(), s3.status()]);
      if (red.dentro) {
        const tr = await tokenDi(red.p, ADMIN + '/media.php');
        const r1 = await red.ctx.request.get(PNc, { maxRedirects: 0 });
        const r2 = await red.ctx.request.get(PFo + '?id=' + F + '&passo=5', { maxRedirects: 0 });
        const r3 = await red.ctx.request.post(PNc, { headers: ORIG, form: { _csrf: tr, azione: 'gruppi_attivi', scheda: 'gruppi' }, maxRedirects: 0 });
        const r4 = await red.ctx.request.get(PFo + '?id=' + F + '&qr=1', { maxRedirects: 0 });
        ok('2.16.0: il redattore non apre Pagine e consenso né i form (nemmeno il QR) e non cambia niente', r1.status() !== 200 && r2.status() !== 200 && r4.status() !== 200 && uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_gruppi_attivi'") === '1', [r1.status(), r2.status(), r3.status(), r4.status()]);
      } else ok('2.16.0: (redattore di prova non disponibile: prove del redattore saltate)', true);
      const qrMale = [];
      for (const u of ['id=999999&qr=1', 'id=abc&qr=1', 'id[]=1&qr=1', 'id=' + F + '&passo=99', 'id=' + F + '&passo[]=1', 'vedi[]=x', 'nuovo=1&tipo[]=x', 'nuovo=1&tipo=<script>']) {
        const r = await admin.ctx.request.get(PFo + '?' + u, { maxRedirects: 0 });
        const t = await r.text();
        if (![200, 302, 404].includes(r.status()) || /SQLSTATE|Fatal error|Warning:|Uncaught|<script>alert/.test(t)) qrMale.push([u, r.status()]);
      }
      ok('2.16.0: parametri del costruttore manipolati (id, passo, tipo, vista, QR): nessun errore', qrMale.length === 0, qrMale);
      await anon.close();
      cli('pulisci');
    }
  }

  // === 39. (2.17.0) Le quindici aperture nuove: valori cattivi nei campi nuovi (righe del terminale, parola, etichette,
  // posizioni, video, forma, foto "prima") e in quelli di sempre, salvati con editor_valida come dall'editor vero
  // (prove/pagina-hero-cattive.php). Le pagine si aprono nel browser con JavaScript acceso: nessuna finestra, nessun
  // attributo on*, nessuno script, video solo del sito, posizioni dentro 0-100, forma fra le tre. Poi il caricamento dei
  // video: solo MP4 e WebM veri, solo con il token.
  if (DIR && fs.existsSync(path.join(__dirname, '..', 'prove', 'pagina-hero-cattive.php'))) {
    const { execFileSync } = require('child_process');
    let t39 = {};
    try { t39 = JSON.parse(execFileSync('php', [path.join(__dirname, '..', 'prove', 'pagina-hero-cattive.php'), DIR], { encoding: 'utf8' }).trim().split('\n').pop()); } catch (e) { t39 = { errore: String(e.message).slice(0, 200) }; }
    ok('2.17.0: (preparazione) le quindici aperture nuove con i valori cattivi', Array.isArray(t39.ids) && t39.ids.length === 15, t39.errore || t39.ids);
    const ids39 = t39.ids || [];
    ok('2.17.0: video esterni, javascript:, data:, "//" e ".." non entrano: restano solo i percorsi del sito .mp4/.webm', (t39.video || []).every(v => v === '' || v === '/uploads/video/buono.mp4') && (t39.video || []).includes('/uploads/video/buono.mp4'), t39.video);
    ok('2.17.0: forma inventata (../../etc/passwd, svg): torna alla piuma', t39.forma === 'piuma', t39.forma);
    ok('2.17.0: posizioni delle etichette e punto della lente fuori misura: tenuti fra 0 e 100', (t39.x || []).every(([x, y]) => x >= 0 && x <= 100 && y >= 0 && y <= 100) && (t39.lente || []).every(v => v >= 0 && v <= 100), { x: (t39.x || []).slice(0, 2), lente: t39.lente });
    ok('2.17.0: una disposizione con caratteri cattivi non entra (torna la classica); una sconosciuta pulita resta e si vede la classica', t39.disposizione === 'classica' && t39.futura === 'futura' && t39.futura_classica === true, { d: t39.disposizione, f: t39.futura, c: t39.futura_classica });
    const ctx39 = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const pg39 = await ctx39.newPage();
    const fin39 = [], err39 = [], male39 = [];
    pg39.on('dialog', d => { fin39.push(d.message()); d.dismiss(); });
    pg39.on('pageerror', e => err39.push(e.message.slice(0, 120)));
    for (const d of ids39) {
      const r = await pg39.goto(BASE + '/hero-cattiva-' + d + '/', { waitUntil: 'networkidle' });
      if (d === 'video') { const b = pg39.locator('.ed-hvi-play'); if (await b.count()) await b.first().click().catch(() => {}); await pg39.waitForTimeout(300); }
      if (d === 'primadopo') { const c = pg39.locator('.ed-hpd-cursore'); if (await c.count()) { await c.first().focus(); await pg39.keyboard.press('ArrowRight'); } }
      const e = await pg39.evaluate(() => {
        const h = document.querySelector('.ed-w-hero'); if (!h) return { manca: true };
        const tutti = [...h.querySelectorAll('*')];
        return {
          on: tutti.filter(x => [...x.attributes].some(a => /^on/i.test(a.name))).length,
          script: tutti.filter(x => /^(script|iframe|object|embed|foreignobject)$/i.test(x.tagName)).length,
          link: tutti.flatMap(x => ['href', 'src', 'data-video', 'poster'].map(n => x.getAttribute(n)).filter(Boolean)).filter(u => /^\s*(javascript|data|vbscript):|^\s*\/\/|^https?:\/\/(?!127\.0\.0\.1)/i.test(u) || u.includes('..')),
          video: [...h.querySelectorAll('video')].map(v => v.getAttribute('src')).filter(s => s !== '/uploads/video/buono.mp4' && !/\/uploads\/video\/buono\.mp4$/.test(s || '')),
          stili: tutti.map(x => x.getAttribute('style') || '').filter(s => /script|expression|url\((?!&quot;|")|<|javascript/i.test(s)),
          note: [...h.querySelectorAll('.ed-hno-note li')].length, righe: h.querySelectorAll('.ed-hte-riga').length, nastro: h.querySelectorAll('.ed-hna-foto').length,
          parola: (h.querySelector('.ed-htr-parola') || {}).textContent || '',
          testo: h.innerText.includes('<script>alert(39)</script>'),
        };
      });
      if (r.status() !== 200 || e.manca || e.on || e.script || e.link.length || e.video.length || e.stili.length || e.note > 3 || e.righe > 5 || e.nastro > 16 || e.parola.length > 14) male39.push([d, r.status(), e]);
    }
    ok('2.17.0: le quindici aperture cattive si aprono (200) senza errori JavaScript e senza finestre aperte da script nascosti', male39.filter(m => m[1] !== 200).length === 0 && err39.length === 0 && fin39.length === 0, { err: err39.slice(0, 3), fin: fin39.slice(0, 3) });
    ok('2.17.0: nessun attributo on*, nessuno script, nessun link o video esterno o javascript:, stili puliti, al massimo 3 etichette, 5 righe, 14 lettere', male39.length === 0, male39.slice(0, 3));
    // Caricamento dei video: un PHP travestito, un HTML con nome .mp4, un MP4 vero con nome .php; senza token e da anonimo.
    const tk39 = await tokenDi(admin.p, ADMIN + '/media.php');
    const carica39 = async (nome, buf, tipo, token = tk39, ctxr = admin.ctx) => {
      const r = await ctxr.request.post(ADMIN + '/editor-api.php', { headers: { Origin: BASE, Referer: ADMIN + '/editor.php' }, multipart: { _csrf: token, azione: 'carica_video', tipo: 'pagine', id: '0', immagine: { name: nome, mimeType: tipo, buffer: buf } }, maxRedirects: 0 });
      let j = {}; try { j = await r.json(); } catch (x) { j = { stato: r.status() }; }
      return j;
    };
    const mp4 = Buffer.concat([Buffer.from([0, 0, 0, 0x18]), Buffer.from('ftypisom'), Buffer.alloc(64)]);
    const r39a = await carica39('x.mp4', Buffer.from('<?php echo 1; ?>'), 'video/mp4');
    const r39b = await carica39('x.mp4', Buffer.from('<html><script>alert(1)</script></html>'), 'video/mp4');
    const r39c = await carica39('x.php', mp4, 'video/mp4');
    const r39d = await carica39('x.mp4.php', mp4, 'video/mp4');
    ok('2.17.0: video falsi (PHP o HTML con nome .mp4, un MP4 con nome .php) rifiutati', !r39a.url && !r39b.url && !r39c.url && !r39d.url, [r39a, r39b, r39c, r39d].map(j => j.errore || j.url || j.stato));
    const r39e = await carica39('x.mp4', mp4, 'video/mp4', 'tokenfalso');
    const anon39 = await browser.newContext();
    const r39f = await carica39('x.mp4', mp4, 'video/mp4', tk39, anon39);
    ok('2.17.0: senza il token giusto o senza essere entrati il video non si carica', !r39e.url && !r39f.url, [r39e.errore || r39e.stato, r39f.errore || r39f.stato]);
    const r39g = await carica39('vero.mp4', mp4, 'video/mp4');
    ok('2.17.0: un MP4 vero si carica in uploads/video/, con il nome .mp4 scelto dal sito', !!r39g.url && /\/uploads\/video\/\d{4}\/\d{2}\/vero-[a-f0-9]{6}\.mp4$/.test(r39g.url), r39g);
    if (r39g.url) {
      const tm = await tokenDi(admin.p, ADMIN + '/media.php');
      await post(admin.p, ADMIN + '/media.php', new URLSearchParams({ _csrf: tm, elimina: r39g.url }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
    }
    await anon39.close(); await ctx39.close();
  } else console.log('--  aperture nuove con valori cattivi: saltata (serve CMS_DIR)');

  // === 40. (2.18.0) Il passo Email che richiama un modello. Id del modello inventato, negativo, testo o con SQL, e il modello
  // dell'email di conferma (tag optin): nessuna automazione si salva. "Stacca dal modello" con valori storti: nessun errore.
  // Il redattore non salva automazioni né modelli, non apre la pagina del codice. La pagina del codice di un modello a blocchi
  // non cambia niente; senza token niente. Nome del modello e dell'automazione con codice: escono come testo sulla tela, nel
  // pannello del passo, in "Usato in", nelle conferme e nella dashboard (nessuna finestra aperta da script). Serve CMS_DIR.
  if (DIR) {
    const { execFileSync } = require('child_process');
    const cli40 = (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, '..', 'prove', 'automazioni-cli.php'), DIR, ...a.map(String)], { encoding: 'utf8' }).trim().split('\n').pop());
    const q40 = (s) => cli40('sql', s);
    const uno40 = (s) => { const r = q40(s); return r && r[0] ? Object.values(r[0])[0] : null; };
    const PF = ADMIN + '/newsletter-flussi.php', PM = ADMIN + '/newsletter-modelli.php';
    const ORIG = { 'Origin': BASE.replace(/(https?:\/\/[^/]+).*/, '$1') };
    const pulisci40 = () => {
      for (const r of q40("SELECT id FROM {p}newsletter_flussi WHERE nome LIKE 'Sic40%'") || []) { for (const t of ['newsletter_corsi', 'newsletter_flussi_registro', 'newsletter_flussi_numeri']) q40(`DELETE FROM {p}${t} WHERE flusso_id = ${r.id}`); q40(`DELETE FROM {p}newsletter_flussi WHERE id = ${r.id}`); }
      q40("DELETE FROM {p}newsletter_modelli WHERE nome LIKE 'Sic40%'");
    };
    pulisci40();
    const quante40 = () => +uno40("SELECT COUNT(*) FROM {p}newsletter_flussi WHERE nome LIKE 'Sic40%'");
    const MB = cli40('modello_crea', 'Sic40 buono', 'blocchi', 'Sic40 testo', 'Sic40 oggetto');
    q40(`INSERT INTO {p}newsletter_modelli (nome, editor, design, oggetto, attivo, tag, origine, creato, aggiornato) SELECT 'Sic40 conferma', 'blocchi', design, 'Conferma', 1, 'optin', '', NOW(), NOW() FROM {p}newsletter_modelli WHERE id = ${MB}`);
    q40(`INSERT INTO {p}newsletter_modelli (nome, editor, design, oggetto, attivo, tag, origine, creato, aggiornato) SELECT 'Sic40 <img src=x onerror=alert(40)>', 'blocchi', design, '<script>alert(40)</script>', 1, '', '', NOW(), NOW() FROM {p}newsletter_modelli WHERE id = ${MB}`);
    const MO = +uno40("SELECT id FROM {p}newsletter_modelli WHERE nome = 'Sic40 conferma'"), MX = +uno40("SELECT id FROM {p}newsletter_modelli WHERE nome LIKE 'Sic40 <img%'");
    const g40 = (dati) => JSON.stringify({ nodi: [{ id: 0, tipo: 'innesco', x: 0, y: 0, dati: {} }, { id: 1, tipo: 'email', x: 40, y: 140, dati: Object.assign({ oggetto: 'Sic40', anteprima: '', corpo: '', editor: 'modello' }, dati) }], archi: [{ da: 0, uscita: 'poi', a: 1 }] });
    const tk40 = await tokenDi(admin.p, PF + '?nuovo=1');
    const salva40 = (campi, ctx = admin.ctx, headers = ORIG) => ctx.request.post(PF, { headers, form: Object.assign({ _csrf: tk40, id: '0', azione: 'salva', nome: 'Sic40', stato: 'in_pausa', inneschi: '[{"tipo":"lista","id":0}]' }, campi), maxRedirects: 0 });
    const strani = [99999999, -5, 'abc', '1 OR 1=1', `${MB}'; DROP TABLE x`, '', 0, MO, [MB], { id: MB }];
    const passati40 = [];
    for (const v of strani) { await salva40({ grafo: g40({ modello: v }) }); if (quante40() > 0) { passati40.push(v); pulisci40(); } }
    ok('2.18.0: id del modello inventato, negativo, testo, con SQL, vuoto, elenco, oggetto e il modello della conferma: nessuna automazione si salva', passati40.length === 0, passati40);
    // Un passo che richiama un modello vero si salva (la prova di controllo), poi "Stacca" con valori storti non rompe niente.
    await salva40({ nome: 'Sic40 <svg onload=alert(40)>', grafo: g40({ modello: MX, oggetto: '' }) });
    const F40 = +uno40("SELECT id FROM {p}newsletter_flussi WHERE nome LIKE 'Sic40%'") || 0;
    ok('2.18.0: la prova di controllo con un modello vero si salva, e tiene il riferimento', F40 > 0 && JSON.parse(uno40(`SELECT passi FROM {p}newsletter_flussi WHERE id = ${F40}`)).nodi[1].dati.modello === MX);
    const male40 = [];
    for (const st of ['abc', '-1', '99999', '1 OR 1=1', '0']) {
      const r = await admin.ctx.request.post(PF, { headers: ORIG, form: { _csrf: tk40, id: String(F40), azione: 'stacca', stacca: st, nome: 'Sic40 <svg onload=alert(40)>', stato: 'in_pausa', inneschi: '[{"tipo":"lista","id":0}]', grafo: g40({ modello: MX, oggetto: '' }) }, maxRedirects: 0 });
      if (r.status() !== 302) male40.push([st, r.status()]);
    }
    const d40 = JSON.parse(uno40(`SELECT passi FROM {p}newsletter_flussi WHERE id = ${F40}`)).nodi[1].dati;
    ok('2.18.0: "Stacca" con un passo che non c\'è, testo o SQL: nessun errore e il passo resta collegato al modello', male40.length === 0 && d40.editor === 'modello' && d40.modello === MX, [male40, d40.editor]);
    // Redattore e senza token
    const anon40 = await browser.newContext();
    const a40 = await anon40.request.get(PM + '?codice=' + MB, { maxRedirects: 0 });
    await anon40.close();
    const tkm = await tokenDi(admin.p, PM);
    const h0 = uno40(`SELECT design FROM {p}newsletter_modelli WHERE id = ${MB}`);
    const c1 = await admin.ctx.request.post(PM, { headers: ORIG, form: { _csrf: tkm, azione: 'codice', id: String(MB), html: '<p>rubato</p>', oggetto: 'x' }, maxRedirects: 0 });
    const c2 = await admin.ctx.request.post(PM, { headers: ORIG, form: { azione: 'oggetto', id: String(MB), oggetto: 'Sic40 senza token', optin: '1' }, maxRedirects: 0 });
    ok('2.18.0: la pagina del codice non cambia un modello a blocchi; senza token niente; anonimo fuori', [302, 403].includes(a40.status()) && c1.status() === 302 && c2.status() === 403 && uno40(`SELECT design FROM {p}newsletter_modelli WHERE id = ${MB}`) === h0 && uno40(`SELECT html FROM {p}newsletter_modelli WHERE id = ${MB}`) === null && uno40(`SELECT oggetto FROM {p}newsletter_modelli WHERE id = ${MB}`) === 'Sic40 oggetto', [a40.status(), c1.status(), c2.status()]);
    if (red.dentro) {
      const tr = await tokenDi(red.p, ADMIN + '/media.php');
      const r1 = await red.ctx.request.post(PF, { headers: ORIG, form: { _csrf: tr, id: '0', azione: 'salva', nome: 'Sic40 redattore', stato: 'attiva', inneschi: '[{"tipo":"lista","id":0}]', grafo: g40({ modello: MB }) }, maxRedirects: 0 });
      const r2 = await red.ctx.request.get(PM + '?codice=' + MB, { maxRedirects: 0 });
      const r3 = await red.ctx.request.post(PM, { headers: ORIG, form: { _csrf: tr, azione: 'elimina', id: String(MB) }, maxRedirects: 0 });
      ok('2.18.0: il redattore non salva un passo da modello, non apre la pagina del codice, non elimina un modello', [r1, r2, r3].every(r => [302, 403].includes(r.status())) && +uno40("SELECT COUNT(*) FROM {p}newsletter_flussi WHERE nome = 'Sic40 redattore'") === 0 && +uno40(`SELECT COUNT(*) FROM {p}newsletter_modelli WHERE id = ${MB}`) === 1, [r1.status(), r2.status(), r3.status()]);
    }
    // Il codice nel nome del modello e dell'automazione esce come testo, nel pannello e nelle conferme.
    const fin40 = [];
    const ascolta40 = d => { fin40.push(d.message()); d.dismiss(); };
    admin.p.on('dialog', ascolta40);
    await admin.p.goto(PF + '?id=' + F40, { waitUntil: 'networkidle' });
    await admin.p.locator('.fl-nodo[data-id="1"] .fl-nodo-corpo').click();
    await admin.p.waitForTimeout(300);
    const tela40 = await admin.p.evaluate(() => ({ html: document.querySelector('.fl-nodo[data-id="1"]').innerHTML + document.getElementById('fl-pannello').innerHTML, on: [...document.querySelectorAll('#fl-tela *, #fl-pannello *')].filter(x => [...x.attributes].some(a => /^on/i.test(a.name))).length }));
    ok('2.18.0: nome e oggetto del modello con codice: sulla tela e nel pannello del passo escono come testo', tela40.on === 0 && !/<img src=x/.test(tela40.html) && /&lt;img src=x onerror=alert\(40\)&gt;/.test(tela40.html) && !/<script>alert\(40\)/.test(tela40.html), tela40.html.slice(0, 300));
    await admin.p.goto(PM, { waitUntil: 'networkidle' });
    const pm40 = await admin.p.content();
    ok('2.18.0: "Usato in" e le conferme con il nome dell\'automazione con codice: come testo', !/<svg onload=alert\(40\)>/.test(pm40) && /&lt;svg onload=alert\(40\)&gt;/.test(pm40) && await admin.p.locator('svg[onload]').count() === 0);
    q40(`DELETE FROM {p}newsletter_modelli WHERE id = ${MX}`);
    await admin.p.goto(ADMIN + '/index.php', { waitUntil: 'networkidle' });
    const db40 = await admin.p.locator('.db-avvisi').innerHTML().catch(() => '');
    // L'automazione è in pausa: la dashboard la segnala (solo le inattive no), con il nome come testo.
    ok('2.18.0: dashboard con il modello sparito: il nome dell\'automazione con codice esce come testo', /&lt;svg onload=alert\(40\)&gt;/.test(db40) && !/<svg onload/.test(db40) && fin40.length === 0, [db40.slice(0, 200), fin40]);
    admin.p.off('dialog', ascolta40);
    pulisci40();
  } else console.log('--  passo Email da un modello: saltata (serve CMS_DIR)');

  // === 42. (2.27.0) Le sedi (admin/sedi.php, includes/sedi.php, seo-api.php sedi_cerca). Codice in ogni campo (anche il tipo
  // scritto a mano): i tag si tolgono al salvataggio (sedi_testo) e quello che resta esce come testo nel pannello, nel JSON-LD
  // (JSON_HEX_TAG), nella pagina della sede e nei widget: niente attributi on*, niente script, niente finestre. javascript: e host non di Google nei link rifiutati; coordinate 1e99, NaN e testo rifiutate;
  // al massimo 50 sedi e 30 righe di zona; redattore, senza token e da un altro sito: niente; il servizio delle coordinate finto
  // che risponde HTML cattivo non entra nel pannello; CMS_NOMINATIM_URL verso 127.0.0.1 o una rete interna rifiutato. Serve CMS_DIR.
  if (DIR && fs.existsSync(path.join(__dirname, '..', 'prove', 'sedi-cli.php'))) {
    const { execFileSync } = require('child_process');
    const cli42 = (...a) => { try { return JSON.parse(execFileSync('php', [path.join(__dirname, '..', 'prove', 'sedi-cli.php'), DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }).trim().split('\n').pop()); } catch (e) { return { errore_cli: String(e.message).slice(0, 200) }; } };
    const primaDi42 = cli42('elenco');
    const ripristina42 = () => { cli42('azzera'); for (const s of Array.isArray(primaDi42) ? primaDi42 : []) cli42('salva', JSON.stringify(Object.assign({}, s, { zona: (s.zona || []).join('\n'), chiusure: (s.chiusure || []).map(c => c.dal + ' - ' + c.al + ': ' + c.motivo).join('\n'), principale: s.principale ? '1' : '' }))); };
    cli42('azzera');
    const CATTIVO = '<img src=x onerror=alert(42)>', SCRIPT = '</script><script>alert(42)</script>';
    const ORIG42 = { 'Origin': BASE.replace(/(https?:\/\/[^/]+).*/, '$1') };
    const t42 = await tokenDi(admin.p, ADMIN + '/sedi.php');
    const campi42 = (extra) => Object.assign({ _csrf: t42, azione: 'salva', id: '', 'sede[nome]': 'Sic42 ' + CATTIVO, 'sede[tipo]': 'altro', 'sede[tipo_testo]': 'centro ' + SCRIPT + CATTIVO, 'sede[via]': 'Via ' + CATTIVO, 'sede[cap]': '40125', 'sede[citta]': 'Bologna ' + SCRIPT,
      'sede[provincia]': 'BO', 'sede[paese]': 'Italia', 'sede[lat]': '44.4949', 'sede[lng]': '11.3426', 'sede[telefono]': '+39 051 123456' + CATTIVO, 'sede[email]': 'sic42@esempio.it', 'sede[prezzo]': '\u20ac',
      'sede[zona]': 'Bologna ' + CATTIVO + '\nImola', 'sede[orari][lun]': '9-13 ' + CATTIVO, 'sede[chiusure]': '2026-12-25: Natale ' + CATTIVO, 'sede[google_scheda]': 'https://maps.app.goo.gl/sic42', 'sede[google_recensione]': 'https://g.page/r/sic42/review',
      'sede[descrizione]': 'Descrizione ' + SCRIPT + CATTIVO, 'sede[principale]': '1', crea_pagina: '1' }, extra || {});
    const s1 = await admin.ctx.request.post(ADMIN + '/sedi.php', { form: campi42(), headers: ORIG42, maxRedirects: 0 });
    const sedi42 = cli42('elenco');
    const sic = Array.isArray(sedi42) ? sedi42.find(x => /^Sic42/.test(x.nome)) : null;
    ok('2.27.0: la sede con codice in ogni campo si salva come testo: nome senza tag, tipo scritto senza tag e con la maiuscola, zona e orari puliti, pagina creata', [302, 303].includes(s1.status()) && !!sic && !/</.test(sic.nome + sic.tipo_testo + sic.via + sic.citta + sic.descrizione + sic.zona.join('') + sic.orari.lun) && /^Centro /.test(sic.tipo_testo) && sic.pagina > 0, sic ? [sic.nome, sic.tipo_testo, sic.citta] : s1.status());
    await admin.p.goto(ADMIN + '/sedi.php', { waitUntil: 'networkidle' });
    const pan42 = await admin.p.content();
    ok('2.27.0: nel pannello Sedi il codice non c\'è più (tag tolti) e niente on*, niente script, niente finestre', !/<img src=x onerror/.test(pan42) && /Sic42/.test(pan42) && await admin.p.locator('img[onerror], svg[onload]').count() === 0 && !/<script>alert\(42\)/.test(pan42));
    await admin.p.goto(ADMIN + '/sedi.php?modifica=' + encodeURIComponent(sic ? sic.id : 'x'), { waitUntil: 'networkidle' });
    ok('2.27.0: il modulo di modifica riporta i valori come testo nei campi', await admin.p.locator('img[onerror]').count() === 0 && (await admin.p.inputValue('#sd-nome').catch(() => '')).startsWith('Sic42'));
    // Il JSON-LD e la pagina della sede sul sito.
    const sito42 = await browser.newContext();
    const pg42 = await sito42.newPage();
    const fin42 = []; pg42.on('dialog', d => { fin42.push(d.message()); d.dismiss().catch(() => {}); });
    await pg42.goto(BASE + '/', { waitUntil: 'networkidle' });
    const ldHome = await pg42.locator('script[type="application/ld+json"]').evaluateAll(l => l.map(s => s.textContent));
    const loc42 = (() => { try { const g = JSON.parse(ldHome[0])['@graph']; const o = g.find(x => x['@id'] && /#organizzazione$/.test(x['@id'])); return o && o.location ? o.location : []; } catch (e) { return []; } })();
    ok('2.27.0: il JSON-LD della home porta la sede senza codice (tag tolti, JSON_HEX_TAG: nessun </script> dentro lo script), LocalBusiness per il tipo scritto a mano', ldHome.length === 1 && !/<\/script/i.test(ldHome[0]) && !/<img|onerror/.test(ldHome[0]) && loc42.length === 1 && loc42[0]['@type'] === 'LocalBusiness' && /^Sic42/.test(loc42[0].name) && /^Descrizione/.test(loc42[0].description || '') && !/</.test(loc42[0].description) && fin42.length === 0, loc42[0] && [loc42[0]['@type'], loc42[0].name, loc42[0].description]);
    const pagina42 = sic && sic.pagina ? cli42('sql', 'SELECT slug FROM {p}pages WHERE id = ' + (+sic.pagina)) : [];
    const slug42 = Array.isArray(pagina42) && pagina42[0] ? pagina42[0].slug : '';
    const rp42 = slug42 ? await pg42.goto(BASE + '/' + slug42 + '/', { waitUntil: 'networkidle' }) : null;
    const html42 = rp42 ? await pg42.content() : '';
    ok('2.27.0: la pagina della sede (slug pulito dal nome) si apre e mostra il codice come testo: niente on*, niente script, niente finestre', !!rp42 && rp42.status() === 200 && /^sic42/.test(slug42) && !/onerror=alert/.test(html42.replace(/&lt;img src=x onerror=alert\(42\)&gt;/g, '')) && await pg42.locator('img[onerror], svg[onload]').count() === 0 && fin42.length === 0 && (html42.match(/<script>alert\(42\)/g) || []).length === 0, [slug42, rp42 && rp42.status()]);
    // I widget Sedi e Dati dell'attività con la sede cattiva (prove/pagina-sedi.php).
    try { execFileSync('php', [path.join(__dirname, '..', 'prove', 'pagina-sedi.php'), DIR, sic ? sic.id : 'principale'], { encoding: 'utf8' }); } catch (e) { console.log('    pagina-sedi.php: ' + String(e.message).slice(0, 200)); }
    const rw42 = await pg42.goto(BASE + '/prova-sedi/', { waitUntil: 'networkidle' });
    const esame42 = await pg42.evaluate(() => ({ on: [...document.querySelectorAll('.ew-sede *, .ew-dati-attivita *, .ed-w-orari *, .ed-w-barra_contatti *')].filter(e => [...e.attributes].some(a => /^on/i.test(a.name))).length,
      testo: document.body.innerText.includes('Sic42') && !document.body.innerHTML.includes('onerror=alert'), link: [...document.querySelectorAll('.ew-sede a, .ew-dati-attivita a, .ew-barra-contatti a')].map(a => a.getAttribute('href')).filter(u => /^\s*(javascript|data|vbscript):/i.test(u || '')) }));
    ok('2.27.0: i widget Sedi, Dati dell\'attività, Orari e Barra con la sede cattiva: solo testo, niente on*, niente link javascript:', rw42.status() === 200 && esame42.on === 0 && esame42.testo && esame42.link.length === 0 && fin42.length === 0, esame42);
    await sito42.close();
    cli42('sql', "DELETE FROM {p}pages WHERE slug = 'prova-sedi'");
    // javascript: e host non di Google nei link; coordinate storte; tipo fuori elenco.
    const rifiuti = [];
    for (const [k, v] of [['sede[google_scheda]', 'javascript:alert(42)'], ['sede[google_scheda]', 'https://evil.example/google.com/x'], ['sede[google_recensione]', 'http://google.com/x'], ['sede[google_recensione]', 'https://google.com.evil.example/x'], ['sede[lat]', '1e99'], ['sede[lat]', 'NaN'], ['sede[lng]', 'testo'], ['sede[lat]', '95']]) {
      const r = await admin.ctx.request.post(ADMIN + '/sedi.php', { form: campi42({ 'sede[nome]': 'Sic42 rifiuto', [k]: v }), headers: ORIG42, maxRedirects: 0 });
      const t = await r.text();
      const salvata = (cli42('elenco') || []).some(x => x.nome === 'Sic42 rifiuto');
      if (!(r.status() === 200 && /sd-errore/.test(t) && !salvata)) rifiuti.push([k, v, r.status(), salvata]);
    }
    const tipoFuori = await admin.ctx.request.post(ADMIN + '/sedi.php', { form: campi42({ 'sede[nome]': 'Sic42 rifiuto', 'sede[tipo]': 'Pippo<script>' }), headers: ORIG42, maxRedirects: 0 });
    if (!(tipoFuori.status() === 200 && !(cli42('elenco') || []).some(x => x.nome === 'Sic42 rifiuto'))) rifiuti.push(['tipo', 'Pippo<script>', tipoFuori.status()]);
    ok('2.27.0: javascript: e host non di Google nei link, coordinate 1e99, NaN, testo e 95, tipo fuori elenco: errori sotto i campi, niente salvato', rifiuti.length === 0, rifiuti);
    // 60 sedi e 200 righe di zona.
    const zona200 = Array.from({ length: 200 }, (_, i) => 'Comune ' + i).join('\n');
    const z = cli42('salva', JSON.stringify({ nome: 'Sic42 zona', tipo: 'negozio', citta: 'Bologna', zona: zona200 }));
    ok('2.27.0: 200 righe di zona: ne restano 30, e il pannello lo dice', z && z.errori && z.errori.zona && /30/.test(z.errori.zona) && (cli42('elenco') || []).every(x => (x.zona || []).length <= 30), z && z.errori);
    let troppe = null, quante = 0;
    for (let i = 0; i < 60; i++) { const r = cli42('salva', JSON.stringify({ nome: 'Sic42 n' + i, tipo: 'negozio', citta: 'Bologna' })); if (r && r.errori && r.errori._) { troppe = r.errori._; break; } quante++; }
    ok('2.27.0: 60 sedi: dopo la cinquantesima il salvataggio si ferma con il messaggio del limite', quante <= 50 && !!troppe && /50/.test(troppe) && (cli42('elenco') || []).length <= 50, [quante, troppe]);
    // Redattore, senza token, da un altro sito.
    if (red.dentro) {
      const tRed42 = await tokenDi(red.p, ADMIN + '/index.php');
      const r1 = await red.ctx.request.post(ADMIN + '/sedi.php', { form: campi42({ _csrf: tRed42, 'sede[nome]': 'Sic42 redattore' }), headers: ORIG42, maxRedirects: 0 });
      const r2 = await red.ctx.request.post(ADMIN + '/sedi.php', { form: { _csrf: tRed42, azione: 'crea_pagina', id: sic ? sic.id : 'x' }, headers: ORIG42, maxRedirects: 0 });
      const r3 = await red.ctx.request.post(ADMIN + '/seo-api.php', { form: { _csrf: tRed42, azione: 'sedi_cerca', indirizzo: 'Via Rizzoli 1, Bologna' }, headers: ORIG42, maxRedirects: 0 });
      const r4 = await red.ctx.request.get(ADMIN + '/sedi.php', { maxRedirects: 0 });
      ok('2.27.0: il redattore non salva una sede, non crea la pagina, non cerca le coordinate, non apre la pagina Sedi', [r1, r2, r4].every(r => [302, 303, 403].includes(r.status())) && r3.status() === 403 && !(cli42('elenco') || []).some(x => x.nome === 'Sic42 redattore'), [r1.status(), r2.status(), r3.status(), r4.status()]);
    }
    const c1 = await admin.ctx.request.post(ADMIN + '/sedi.php', { form: campi42({ _csrf: '', 'sede[nome]': 'Sic42 senza token' }), headers: ORIG42, maxRedirects: 0 });
    const c2 = await admin.ctx.request.post(ADMIN + '/sedi.php', { form: campi42({ 'sede[nome]': 'Sic42 altro sito' }), headers: { 'Origin': 'https://evil.example' }, maxRedirects: 0 });
    const c3 = await admin.ctx.request.post(ADMIN + '/seo-api.php', { form: { azione: 'sedi_cerca', indirizzo: 'x' }, maxRedirects: 0 });
    ok('2.27.0: senza token o da un altro sito niente si salva (403), sedi_cerca senza token 403', c1.status() === 403 && c2.status() === 403 && c3.status() === 403 && !(cli42('elenco') || []).some(x => /senza token|altro sito/.test(x.nome)), [c1.status(), c2.status(), c3.status()]);
    // Il servizio delle coordinate: indirizzi interni rifiutati dal server; HTML cattivo dal finto non entra nel pannello.
    const interni = [];
    for (const u of ['http://127.0.0.1:1/search', 'https://10.0.0.1/search', 'https://192.168.1.1/search', 'http://localhost/search', 'https://[::1]/search', 'https://169.254.169.254/latest', 'https://100.64.0.1/search', 'https://0.0.0.0/search']) {
      const g = cli42('geocodifica', 'Via Rizzoli 1, Bologna', u, '0');
      if (!g || g.ok || !/interno|privato/.test(g.errore || '')) interni.push([u, g]);
    }
    ok('2.27.0: CMS_NOMINATIM_URL verso 127.0.0.1, 10.x, 192.168.x, localhost, ::1, 169.254.x, 100.64.x, 0.0.0.0: rifiutato come i webhook', interni.length === 0, interni.slice(0, 2));
    const cfg42 = fs.existsSync(path.join(DIR, 'includes', 'config.local.php')) ? fs.readFileSync(path.join(DIR, 'includes', 'config.local.php'), 'utf8') : '';
    if (/CMS_NOMINATIM_URL/.test(cfg42) && /CMS_NOMINATIM_PROVA/.test(cfg42)) {
      const h1 = await admin.ctx.request.post(ADMIN + '/seo-api.php', { form: { _csrf: t42, azione: 'sedi_cerca', indirizzo: 'Via cattivo 1, Bologna' }, headers: ORIG42, maxRedirects: 0 });
      const j1 = await h1.json().catch(() => null);
      const h2 = await admin.ctx.request.post(ADMIN + '/seo-api.php', { form: { _csrf: t42, azione: 'sedi_cerca', indirizzo: 'Via numeri 1, Bologna' }, headers: ORIG42, maxRedirects: 0 });
      const j2 = await h2.json().catch(() => null);
      ok('2.27.0: il servizio finto che risponde HTML con uno script, o coordinate 1e99 e NaN: il pannello riceve solo un messaggio di errore in parole, senza HTML', h1.status() === 422 && j1 && !j1.ok && !/</.test(j1.errore) && h2.status() === 422 && j2 && !j2.ok && !/</.test(j2.errore), [j1 && j1.errore, j2 && j2.errore]);
    } else ok('2.27.0: (senza CMS_NOMINATIM_URL e CMS_NOMINATIM_PROVA nel config del sito di prova, la prova del servizio finto con l\'HTML cattivo è saltata)', true);
    // Pulizia: le pagine delle sedi di prova e le sedi di prima.
    for (const s of cli42('elenco') || []) if (/^Sic42/.test(s.nome) && s.pagina > 0) cli42('sql', 'DELETE FROM {p}pages WHERE id = ' + (+s.pagina));
    ripristina42();
  } else console.log('--  sedi (2.27.0): saltata (serve CMS_DIR)');

  // === 43. (2.28.0) Le pagine per zona (admin/zone.php, includes/zone.php, widget Altre zone, pubblicazione bloccata dai
  // segnaposto). Codice in servizio, zone e modelli: i tag si tolgono (sedi_testo) e quello che resta esce come testo nel
  // pannello, nei titoli, nel JSON-LD e nel widget; lo slug dal titolo passa da contenuti_slug (niente ../, niente punti);
  // il modello senza %zona% e 500 zone in un colpo rifiutati con l'errore sotto il campo; la pagina del servizio inventata
  // rifiutata; 2.36.0: niente tetto al totale (210 in tre giri passano) ma al massimo 100 zone a giro; paginazione, ricerca,
  // "torna" e "continua" con valori cattivi (parametri legati, %/_ come testo, niente tag riflessi); "Quante mostrarne" e "Quali
  // mostrare" del widget con valori cattivi (fra 0 e 200, solo le due scelte); una bozza con [DA SCRIVERE] non si pubblica da nessuna strada (editor, scheda,
  // azione in blocco, Impostazioni dell'editor), nemmeno dall'amministratore, e il segnaposto non entra mai nel JSON-LD;
  // il redattore non crea le bozze e non toglie dal registro; senza token o da un altro sito niente. Serve CMS_DIR.
  if (DIR && fs.existsSync(path.join(__dirname, '..', 'prove', 'zone-cli.php'))) {
    const { execFileSync } = require('child_process');
    const cli43 = (...a) => { try { return JSON.parse(execFileSync('php', [path.join(__dirname, '..', 'prove', 'zone-cli.php'), DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }).trim().split('\n').pop()); } catch (e) { return { errore_cli: String(e.message).slice(0, 200) }; } };
    const mie43 = () => (cli43('elenco') || []).filter(z => /^Sic43/.test(z.servizio));
    const pulisci43 = () => { for (const z of mie43()) cli43('togli', z.pagina); };
    pulisci43();
    const CATTIVO = '<img src=x onerror=alert(43)>', SCRIPT = '</script><script>alert(43)</script>';
    const ORIG43 = { 'Origin': BASE.replace(/(https?:\/\/[^/]+).*/, '$1') };
    const t43 = await tokenDi(admin.p, ADMIN + '/zone.php');
    const campi43 = (extra) => Object.assign({ _csrf: t43, azione: 'crea', 'zone[servizio]': 'Sic43 ' + CATTIVO, 'zone[zone]': 'Bologna ' + SCRIPT + '\nImola ' + CATTIVO, 'zone[sede]': 'principale', 'zone[servizio_pagina]': '',
      'zone[titolo]': '../../etc/passwd %servizio% a %zona% ' + CATTIVO, 'zone[descrizione]': 'Descrizione ' + SCRIPT + CATTIVO }, extra || {});
    const c1 = await admin.ctx.request.post(ADMIN + '/zone.php', { form: campi43(), headers: ORIG43, maxRedirects: 0 });
    const z43 = mie43();
    ok('2.28.0: servizio, zone, titolo e descrizione con codice: due bozze create come testo (niente tag nel servizio, nella zona, nel titolo), slug pulito senza ../ e punti', [302, 303].includes(c1.status()) && z43.length === 2 && z43.every(z => !/</.test(z.servizio + z.zona + z.titolo) && /^[a-z0-9-]+$/.test(z.slug) && !/\.\./.test(z.slug) && z.stato === 'bozza' && z.segnaposto === 3), z43.map(z => [z.titolo, z.slug]) || c1.status());
    const descr43 = z43.length ? cli43('sql', 'SELECT seo_description FROM {p}pages WHERE id = ' + (+z43[0].pagina)) : [];
    ok('2.28.0: la descrizione per Google è testo senza tag', Array.isArray(descr43) && descr43[0] && /^Descrizione/.test(descr43[0].seo_description) && !/</.test(descr43[0].seo_description), descr43);
    await admin.p.goto(ADMIN + '/zone.php', { waitUntil: 'networkidle' });
    const pan43 = await admin.p.content();
    ok('2.28.0: nel pannello Zone il codice non c\'è (tag tolti): niente on*, niente script, niente finestre', /Sic43/.test(pan43) && !/<img src=x onerror/.test(pan43) && await admin.p.locator('img[onerror], svg[onload]').count() === 0 && !/<script>alert\(43\)/.test(pan43));
    // La bozza con i segnaposto non si pubblica: editor (salva con pubblica=1 e Impostazioni), scheda, azione in blocco. Anche dall'amministratore.
    const idZ = z43.length ? +z43[0].pagina : 0;
    const alberoZ = idZ ? (cli43('sql', 'SELECT struttura_bozza FROM {p}pages WHERE id = ' + idZ)[0] || {}).struttura_bozza || '' : '';
    const tE = await tokenDi(admin.p, ADMIN + '/editor.php?tipo=pagine&id=' + idZ);
    const s1 = await admin.ctx.request.post(ADMIN + '/editor-api.php', { form: { _csrf: tE, azione: 'salva', tipo: 'pagine', id: String(idZ), albero: alberoZ, pubblica: '1', nota: '' }, headers: ORIG43, maxRedirects: 0 });
    const s2 = await admin.ctx.request.post(ADMIN + '/editor-api.php', { form: { _csrf: tE, azione: 'impostazioni', tipo: 'pagine', id: String(idZ), titolo: 'Sic43 zona', slug: z43[0] ? z43[0].slug : 'x', stato: 'published', seo_titolo: '', seo_descrizione: '', robots: '' }, headers: ORIG43, maxRedirects: 0 });
    const s3 = await admin.ctx.request.post(ADMIN + '/contenuto.php?tipo=pagine&id=' + idZ, { form: { _csrf: tE, azione: 'salva', title: 'Sic43 zona', slug: z43[0] ? z43[0].slug : 'x', invio: 'pubblica', stato: 'pubblicato', contenuto: '', faq_testo: '' }, headers: ORIG43, maxRedirects: 0 });
    const s4 = await admin.ctx.request.post(ADMIN + '/contenuti.php?tipo=pagine', { form: { _csrf: tE, azione: 'pubblica', 'ids[]': String(idZ) }, headers: ORIG43, maxRedirects: 0 });
    const s5 = await admin.ctx.request.post(ADMIN + '/contenuti.php?tipo=pagine', { form: { _csrf: tE, azione: 'rapido', uno: String(idZ), title: 'Sic43 zona', slug: z43[0] ? z43[0].slug : 'x', stato: 'pubblicato', data: '', ora: '' }, headers: ORIG43, maxRedirects: 0 });
    const j1 = await s1.json().catch(() => null), j2 = await s2.json().catch(() => null);
    const dopo43 = cli43('elenco').find(z => z.pagina === idZ) || {};
    ok('2.28.0: una bozza con [DA SCRIVERE] non si pubblica da nessuna strada (editor 409, Impostazioni 400, scheda, azione in blocco, modifica rapida): resta bozza con i 3 segnaposto', s1.status() === 409 && j1 && /riquadri da scrivere/.test(j1.errore) && s2.status() === 400 && j2 && /riquadri da scrivere/.test(j2.errore) && [302, 303].includes(s3.status()) && [302, 303].includes(s4.status()) && [302, 303].includes(s5.status()) && dopo43.stato === 'bozza' && dopo43.segnaposto === 3, [s1.status(), s2.status(), s3.status(), s4.status(), s5.status(), dopo43.stato]);
    const an43 = await browser.newContext();
    const r404 = await an43.request.get(BASE + '/' + (z43[0] ? z43[0].slug : 'x') + '/', { maxRedirects: 0 });
    ok('2.28.0: la bozza per un visitatore è 404 (il segnaposto non esce mai sul sito)', r404.status() === 404, r404.status());
    // Scritta e pubblicata, la pagina mostra il codice come testo e il JSON-LD è pulito; ripubblicarla con il segnaposto è rifiutato.
    for (const z of z43) { cli43('scrivi', z.pagina); cli43('pubblica', z.pagina); }
    const pg43 = await an43.newPage();
    const fin43 = []; pg43.on('dialog', d => { fin43.push(d.message()); d.dismiss().catch(() => {}); });
    const rp43 = z43[0] ? await pg43.goto(BASE + '/' + z43[0].slug + '/', { waitUntil: 'networkidle' }) : null;
    const html43 = rp43 ? await pg43.content() : '';
    const ld43 = await pg43.locator('script[type="application/ld+json"]').evaluateAll(l => l.map(s => s.textContent));
    const serv43 = (() => { try { return JSON.parse(ld43[0])['@graph'].find(x => x['@type'] === 'Service'); } catch (e) { return null; } })();
    ok('2.28.0: la pagina per zona pubblicata mostra il codice come testo: niente on*, niente script, niente finestre; Altre zone con il link pulito', !!rp43 && rp43.status() === 200 && /Sic43/.test(html43) && await pg43.locator('img[onerror], svg[onload]').count() === 0 && (html43.match(/<script>alert\(43\)/g) || []).length === 0 && fin43.length === 0 && await pg43.locator('.ew-zone .ew-zone-link').count() === 1 && !/</.test(await pg43.locator('.ew-zone .ew-zone-link').first().innerText()), [rp43 && rp43.status(), fin43]);
    ok('2.28.0: il JSON-LD della pagina per zona: Service con name e serviceType senza tag, niente </script> e niente segnaposto', ld43.length === 1 && !/<\/script/i.test(ld43[0]) && !/<img|onerror/.test(ld43[0]) && !ld43[0].includes('[DA SCRIVERE]') && serv43 && /^Sic43/.test(serv43.serviceType) && !/</.test(serv43.name + serv43.serviceType) && serv43.areaServed && !/</.test(serv43.areaServed.name), serv43 && [serv43.name, serv43.serviceType]);
    const s6 = await admin.ctx.request.post(ADMIN + '/editor-api.php', { form: { _csrf: tE, azione: 'salva', tipo: 'pagine', id: String(idZ), albero: alberoZ, pubblica: '1', nota: '' }, headers: ORIG43, maxRedirects: 0 });
    const rp43b = z43[0] ? await pg43.goto(BASE + '/' + z43[0].slug + '/', { waitUntil: 'networkidle' }) : null;
    ok('2.28.0: ripubblicare la pagina con il segnaposto rimesso è rifiutato (409) e sul sito resta la versione scritta', s6.status() === 409 && !!rp43b && rp43b.status() === 200 && !(await pg43.content()).includes('[DA SCRIVERE]'), s6.status());
    await an43.close();
    // Rifiuti: modello senza %zona%, 500 zone in un colpo, pagina del servizio inventata o con SQL, servizio vuoto.
    const prima43 = (cli43('elenco') || []).length;
    const rifiuti43 = [];
    for (const [nome, extra] of [['modello senza %zona%', { 'zone[titolo]': 'Servizio in zona' }], ['500 zone', { 'zone[zone]': Array.from({ length: 500 }, (_, i) => 'Comune ' + i).join('\n') }],
      ['pagina del servizio inventata', { 'zone[servizio_pagina]': 'pagine:99999999' }], ['pagina del servizio con SQL', { 'zone[servizio_pagina]': "pages:1' OR 1=1" }], ['pagina del servizio con ../', { 'zone[servizio_pagina]': '../../etc/passwd' }], ['servizio vuoto', { 'zone[servizio]': '' }]]) {
      const r = await admin.ctx.request.post(ADMIN + '/zone.php', { form: campi43(Object.assign({ 'zone[servizio]': 'Sic43 rifiuto', 'zone[zone]': 'Ferrara' }, extra)), headers: ORIG43, maxRedirects: 0 });
      const t = await r.text();
      const creato = (cli43('elenco') || []).length !== prima43;
      if (!(r.status() === 200 && /zn-errore/.test(t) && !creato)) rifiuti43.push([nome, r.status(), creato]);
    }
    ok('2.28.0: modello senza %zona%, 500 zone in un colpo (più di 100), pagina del servizio inventata, con SQL o con ../, servizio vuoto: errore sotto il campo, niente creato', rifiuti43.length === 0, rifiuti43);
    // 2.36.0: niente tetto al totale: 210 zone in tre giri (100, 100, 10) si creano tutte, senza messaggio di limite.
    let creati43 = 0; const errLim43 = [];
    for (const [g, q] of [[0, 100], [1, 100], [2, 10]]) {
      const r = cli43('crea', JSON.stringify({ servizio: 'Sic43 limite', zone: Array.from({ length: q }, (_, i) => 'Zona ' + (g * 100 + i + 1)).join('\n'), sede: 'principale' }));
      creati43 += r && r.create ? r.create.length : 0; if (r && (r.errore || (r.errori && Object.keys(r.errori).length))) errLim43.push(r.errore || r.errori);
    }
    ok('2.36.0: 210 zone in tre giri: tutte create (nessun tetto al totale), registro in tabella', creati43 === 210 && errLim43.length === 0 && (cli43('elenco') || []).filter(z => z.servizio === 'Sic43 limite').length === 210 && cli43('cfg').migrato === 1, [creati43, errLim43]);
    // Paginazione e ricerca con valori cattivi: 200, niente errori SQL, niente tag riflessi, % e _ presi come testo.
    const cattivi43 = [];
    for (const [qs, nessuna] of [['pag=-1'], ['pag=abc'], ['pag[]=1'], ['pag=99999999999999'], ['pag=1%27%20OR%201=1'], ["cerca=%27%20OR%201=1%20--%20", 1], ['cerca=' + encodeURIComponent('" UNION SELECT password_hash FROM users -- '), 1], ['cerca=' + encodeURIComponent(SCRIPT)], ['cerca[]=x'], ['servizio[]=x'], ['servizio=' + encodeURIComponent("Sic43 limite' OR '1'='1")], ['cerca=%25', 1], ['cerca=_', 1], ['cerca=%5C', 1], ['cerca=' + 'x'.repeat(5000), 1]]) {
      const r = await admin.ctx.request.get(ADMIN + '/zone.php?' + qs, { maxRedirects: 0 });
      const t = await r.text();
      const male = r.status() !== 200 || /SQLSTATE|Fatal|Warning:|Uncaught/.test(t) || /<script>alert\(43\)/.test(t) || /\$2y\$|\$argon2/.test(t) || (nessuna && /id="zn-tabella"/.test(t));
      if (male) cattivi43.push([qs.slice(0, 40), r.status()]);
    }
    ok('2.36.0: paginazione e ricerca con valori cattivi (pag negativa, testo, array, enorme; ricerca con SQL, UNION, codice, array, %, _, \\, 5000 caratteri; servizio con SQL): 200, nessun errore, nessun codice riflesso, nessuna riga trovata dalle iniezioni', cattivi43.length === 0, cattivi43);
    const tcont = await tokenDi(admin.p, ADMIN + '/zone.php');
    const c43 = await admin.ctx.request.post(ADMIN + '/zone.php', { form: { _csrf: tcont, azione: 'continua' }, headers: ORIG43, maxRedirects: 0 });
    const torna43 = await admin.ctx.request.post(ADMIN + '/zone.php', { form: { _csrf: tcont, azione: 'togli', id: '0', torna: 'servizio=x&cerca=' + encodeURIComponent(SCRIPT) + '&pag=1;evil' }, headers: ORIG43, maxRedirects: 0 });
    ok('2.36.0: "Continua" senza un giro in corso non crea niente (rimanda con l\'avviso); "torna" con codice non entra nel rimando', [302, 303].includes(c43.status()) && !/[<>]/.test(c43.headers()['location'] || '') && [302, 303].includes(torna43.status()) && !/[<>"]|evil|alert/.test(torna43.headers()['location'] || ''), [c43.status(), c43.headers()['location'], torna43.headers()['location']]);
    // Quante mostrarne e Quali mostrare con valori cattivi (salvati dall'editor come bozza): restano fra 0 e 200 e fra le due scelte.
    const zLim = (cli43('elenco') || []).find(z => z.servizio === 'Sic43 limite');
    const alberoL = zLim ? (cli43('sql', 'SELECT struttura_bozza FROM {p}pages WHERE id = ' + (+zLim.pagina))[0] || {}).struttura_bozza || '' : '';
    const wq43 = [];
    for (const [q, o] of [[-5, '<script>'], [99999, "vicine' OR 1=1"], ['1; DROP TABLE x', 'tutte'], [[1, 2], { a: 1 }], ['3e9', null]]) {
      let a; try { a = JSON.parse(alberoL); } catch (e) { a = null; }
      if (!a) { wq43.push('albero'); break; }
      const giro = (n) => { for (const e of n) { if (e.tipo === 'sezione' || e.tipo === 'colonna') giro(e.figli || []); else if (e.tipo === 'zone') { e.impostazioni = Object.assign({}, e.impostazioni, { quante: q, ordine: o }); } } };
      giro(a.elementi);
      const tE2 = await tokenDi(admin.p, ADMIN + '/editor.php?tipo=pagine&id=' + zLim.pagina);
      const r = await admin.ctx.request.post(ADMIN + '/editor-api.php', { form: { _csrf: tE2, azione: 'salva', tipo: 'pagine', id: String(zLim.pagina), albero: JSON.stringify(a), pubblica: '0', nota: '' }, headers: ORIG43, maxRedirects: 0 });
      const salvato = JSON.parse((cli43('sql', 'SELECT struttura_bozza FROM {p}pages WHERE id = ' + (+zLim.pagina))[0] || {}).struttura_bozza || '{}');
      let imp = null; const trova = (n) => { for (const e of n || []) { if (e.tipo === 'sezione' || e.tipo === 'colonna') trova(e.figli); else if (e.tipo === 'zone') imp = e.impostazioni || {}; } }; trova(salvato.elementi);
      const qq = imp && imp.quante !== undefined ? imp.quante : 0, oo = imp && imp.ordine !== undefined ? imp.ordine : 'alfabetico';
      if (r.status() !== 200 || !(Number.isInteger(qq) && qq >= 0 && qq <= 200) || !['alfabetico', 'vicine'].includes(oo)) wq43.push([JSON.stringify(q), JSON.stringify(o), r.status(), qq, oo]);
    }
    ok('2.36.0: "Quante mostrarne" e "Quali mostrare" con valori cattivi (negativo, enorme, SQL, array, codice): salvati come numero fra 0 e 200 e una delle due scelte', wq43.length === 0, wq43);
    for (const z of (cli43('elenco') || []).filter(z => z.servizio === 'Sic43 limite')) cli43('togli', z.pagina);
    // Redattore: niente bozze, niente Togli dal registro, niente pannello; ma una bozza con i segnaposto non la pubblica nemmeno lui.
    if (red.dentro) {
      const tr = await tokenDi(red.p, ADMIN + '/index.php');
      const r1 = await red.ctx.request.post(ADMIN + '/zone.php', { form: campi43({ _csrf: tr, 'zone[servizio]': 'Sic43 redattore', 'zone[zone]': 'Ferrara' }), headers: ORIG43, maxRedirects: 0 });
      const r2 = await red.ctx.request.post(ADMIN + '/zone.php', { form: { _csrf: tr, azione: 'togli', id: String(idZ) }, headers: ORIG43, maxRedirects: 0 });
      const r3 = await red.ctx.request.get(ADMIN + '/zone.php', { maxRedirects: 0 });
      const b43 = cli43('crea', JSON.stringify({ servizio: 'Sic43 bozza', zone: 'Modena', sede: 'principale' }));
      const idB43 = b43 && b43.create && b43.create[0] ? +b43.create[0].pagina : 0;
      const alberoB = idB43 ? (cli43('sql', 'SELECT struttura_bozza FROM {p}pages WHERE id = ' + idB43)[0] || {}).struttura_bozza || '' : '';
      const trE = await tokenDi(red.p, ADMIN + '/editor.php?tipo=pagine&id=' + idB43);
      const r4 = await red.ctx.request.post(ADMIN + '/editor-api.php', { form: { _csrf: trE, azione: 'salva', tipo: 'pagine', id: String(idB43), albero: alberoB, pubblica: '1', nota: '' }, headers: ORIG43, maxRedirects: 0 });
      const r5 = await red.ctx.request.post(ADMIN + '/contenuto.php?tipo=pagine&id=' + idB43, { form: { _csrf: trE, azione: 'salva', title: 'Sic43 bozza a Modena', slug: b43.create[0].slug, invio: 'pubblica', stato: 'pubblicato', contenuto: '', faq_testo: '' }, headers: ORIG43, maxRedirects: 0 });
      const vB = cli43('elenco').find(z => z.pagina === idB43) || {};
      ok('2.28.0: il redattore non crea bozze, non toglie dal registro, non apre Zone; una bozza con i segnaposto non la pubblica (editor 409 o 403, scheda): resta bozza', [r1, r2, r3].every(r => [302, 303, 403].includes(r.status())) && !(cli43('elenco') || []).some(z => z.servizio === 'Sic43 redattore') && !!(cli43('elenco') || []).find(z => z.pagina === idZ) && [403, 409].includes(r4.status()) && [302, 303].includes(r5.status()) && vB.stato === 'bozza' && vB.segnaposto === 3, [r1.status(), r2.status(), r3.status(), r4.status(), r5.status(), vB.stato]);
    }
    // Senza token e da un altro sito.
    const n1 = await admin.ctx.request.post(ADMIN + '/zone.php', { form: campi43({ _csrf: '', 'zone[servizio]': 'Sic43 senza token', 'zone[zone]': 'Ferrara' }), headers: ORIG43, maxRedirects: 0 });
    const n2 = await admin.ctx.request.post(ADMIN + '/zone.php', { form: campi43({ 'zone[servizio]': 'Sic43 altro sito', 'zone[zone]': 'Ferrara' }), headers: { 'Origin': 'https://evil.example' }, maxRedirects: 0 });
    const n3 = await admin.ctx.request.post(ADMIN + '/zone.php', { form: { azione: 'togli', id: String(idZ) }, headers: { 'Origin': 'https://evil.example' }, maxRedirects: 0 });
    ok('2.28.0: senza token o da un altro sito niente si crea e niente si toglie (403)', n1.status() === 403 && n2.status() === 403 && n3.status() === 403 && !(cli43('elenco') || []).some(z => /senza token|altro sito/.test(z.servizio)) && !!(cli43('elenco') || []).find(z => z.pagina === idZ), [n1.status(), n2.status(), n3.status()]);
    pulisci43();
  } else console.log('--  pagine per zona (2.28.0): saltata (serve CMS_DIR)');
  // === 44. (2.29.0) Gli strumenti SEO: la Tabella SEO (seo-api.php tabella_salva), i dati per Google in più (schema[...] nella scheda,
  // includes/seo-schema.php), i link interni suggeriti (includes/seo-link.php), gli alt della libreria (media_alt_salva). Codice nei
  // campi della tabella e degli schema (tag tolti, escape in uscita, JSON_HEX_TAG nel JSON-LD); redattore che prova a salvare la
  // configurazione SEO o un contenuto che non esiste; senza token e da un altro sito; video javascript:, data: e http:; 500 passi di
  // how-to; una keyword con codice nei suggerimenti; un alt per un file fuori dalla libreria. Serve CMS_DIR.
  if (DIR && fs.existsSync(path.join(__dirname, '..', 'prove', 'seo-strumenti-cli.php'))) {
    const { execFileSync } = require('child_process');
    const cli44 = (...a) => { try { return JSON.parse(execFileSync('php', [path.join(__dirname, '..', 'prove', 'seo-strumenti-cli.php'), DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }).trim().split('\n').pop()); } catch (e) { return { errore_cli: String(e.message).slice(0, 200) }; } };
    const CATTIVO = '<img src=x onerror=alert(44)>', SCRIPT = '</script><script>alert(44)</script>';
    const ORIG44 = { 'Origin': BASE.replace(/(https?:\/\/[^/]+).*/, '$1') };
    const foto44 = cli44('foto', 'sic44');
    const pag44 = cli44('pagina', 'sic44-pagina', 'Pagina sic44 sui vasi in terracotta', JSON.stringify([{ tipo: 'testo', impostazioni: { contenuto: '<p>Vasi in terracotta per il giardino.</p>' } }, { tipo: 'immagine', impostazioni: { immagine: foto44.url || '', alt: '' } }]));
    const art44 = cli44('articolo', 'sic44-articolo', 'Articolo sic44 sui vasi', CATTIVO + ' vasi terracotta', 'Descrizione sic44.', JSON.stringify([{ tipo: 'testo', impostazioni: { contenuto: '<p>Un articolo sui vasi.</p>' } }]));
    // Il token: la Tabella SEO non ha un form, ma il meta pn-csrf (quello che usa seo-tabella.js).
    const tokenMeta44 = async (p, url) => { await p.goto(url, { waitUntil: 'networkidle' }); return await p.evaluate(() => { const m = document.querySelector('meta[name=pn-csrf]'); return m ? m.content : ''; }); };
    const t44 = await tokenMeta44(admin.p, ADMIN + '/seo-tabella.php');
    // 1. Codice nella Tabella SEO: titolo per Google e descrizione.
    const s1 = await admin.ctx.request.post(ADMIN + '/seo-api.php', { form: { _csrf: t44, azione: 'tabella_salva', tipo: 'pagine', id: String(pag44.id || 0), seo_title: 'Sic44 ' + CATTIVO + SCRIPT, descrizione: 'Descr sic44 ' + SCRIPT + CATTIVO }, headers: ORIG44, maxRedirects: 0 });
    const j1 = await s1.json().catch(() => null);
    const riga44 = cli44('sql', 'SELECT seo_title, seo_description, meta_description FROM {p}pages WHERE id = ' + (+pag44.id));
    const salvati44 = Array.isArray(riga44) && riga44[0] ? riga44[0] : {};
    ok('2.29.0: la Tabella SEO salva titolo e descrizione con codice come testo (tag tolti), risposta senza HTML grezzo', s1.status() === 200 && j1 && j1.ok && !/</.test((salvati44.seo_title || '') + (salvati44.seo_description || '') + (salvati44.meta_description || '')) && /^Sic44/.test(salvati44.seo_title || '') && !/<img src=x/.test(JSON.stringify(j1)), [s1.status(), salvati44]);
    await admin.p.goto(ADMIN + '/seo-tabella.php', { waitUntil: 'networkidle' });
    ok('2.29.0: nella Tabella SEO i valori escono nelle caselle come testo, niente on*, niente script', await admin.p.locator('img[onerror], svg[onload]').count() === 0 && !/<script>alert\(44\)/.test(await admin.p.content()) && (await admin.p.locator(`#st-tabella tr[data-tipo="pagine"][data-id="${pag44.id}"] input[data-campo=seo_title]`).inputValue().catch(() => '')).startsWith('Sic44'));
    const sito44 = await browser.newContext();
    const pg44 = await sito44.newPage();
    const fin44 = []; pg44.on('dialog', d => { fin44.push(d.message()); d.dismiss().catch(() => {}); });
    const rs1 = await pg44.goto(BASE + '/sic44-pagina/', { waitUntil: 'networkidle' });
    const h44 = await pg44.content();
    ok('2.29.0: sul sito <title> e meta description con il testo salvato escono con l\'escape: niente script, niente finestre', !!rs1 && rs1.status() === 200 && /<title>Sic44/.test(h44) && !/<script>alert\(44\)/.test(h44) && await pg44.locator('img[onerror]').count() === 0 && fin44.length === 0);
    // 2. Codice nei dati per Google in più (Prodotto) dalla scheda, e un video javascript:, data: e http:.
    const tSc = await tokenDi(admin.p, ADMIN + '/contenuto.php?tipo=pagine&id=' + pag44.id);
    const scheda44 = (extra) => Object.assign({ _csrf: tSc, azione: 'salva', invio: 'pubblica', title: 'Pagina sic44 sui vasi in terracotta', slug: 'sic44-pagina', contenuto: '', stato: 'pubblicato', robots: '' }, extra || {});
    const s2 = await admin.ctx.request.post(ADMIN + '/contenuto.php?tipo=pagine&id=' + pag44.id, { form: scheda44({ 'schema[tipo]': 'prodotto', 'schema[prodotto][nome]': 'Vaso ' + CATTIVO + SCRIPT, 'schema[prodotto][prezzo]': '24,90', 'schema[prodotto][valuta]': 'EUR', 'schema[prodotto][disponibilita]': 'InStock', 'schema[prodotto][codice]': 'C' + CATTIVO, 'schema[prodotto][marca]': 'M' + SCRIPT, 'schema[prodotto][foto]': 'javascript:alert(44)' }), headers: ORIG44, maxRedirects: 0 });
    const sc44 = cli44('sql', "SELECT COALESCE(seo_schema, '') s FROM {p}pages WHERE id = " + (+pag44.id));
    const schemaS = Array.isArray(sc44) && sc44[0] ? String(sc44[0].s) : '';
    // La foto javascript: è un errore: i dati non si salvano affatto (restano quelli di prima, cioè niente).
    ok('2.29.0: Prodotto con codice nei campi e foto javascript: il salvataggio dei dati in più si rifiuta (campo foto), la colonna resta vuota', [302, 303].includes(s2.status()) && schemaS === '', [s2.status(), schemaS.slice(0, 100)]);
    const s3 = await admin.ctx.request.post(ADMIN + '/contenuto.php?tipo=pagine&id=' + pag44.id, { form: scheda44({ 'schema[tipo]': 'prodotto', 'schema[prodotto][nome]': 'Vaso ' + CATTIVO + SCRIPT, 'schema[prodotto][prezzo]': '24,90', 'schema[prodotto][valuta]': 'EUR', 'schema[prodotto][disponibilita]': 'InStock', 'schema[prodotto][codice]': 'C' + CATTIVO, 'schema[prodotto][marca]': 'M' + SCRIPT, 'schema[prodotto][foto]': '' }), headers: ORIG44, maxRedirects: 0 });
    const sc44b = cli44('sql', "SELECT COALESCE(seo_schema, '') s FROM {p}pages WHERE id = " + (+pag44.id));
    const schemaB = Array.isArray(sc44b) && sc44b[0] ? String(sc44b[0].s) : '';
    let dati44 = null; try { dati44 = JSON.parse(schemaB); } catch (e) {}
    ok('2.29.0: Prodotto con codice nei campi: salvato come testo, senza tag', [302, 303].includes(s3.status()) && dati44 && dati44.tipo === 'prodotto' && !/</.test(dati44.prodotto.nome + dati44.prodotto.codice + dati44.prodotto.marca) && /^Vaso /.test(dati44.prodotto.nome), dati44 && dati44.prodotto);
    const rs2 = await pg44.goto(BASE + '/sic44-pagina/', { waitUntil: 'networkidle' });
    const ld44 = await pg44.locator('script[type="application/ld+json"]').evaluateAll(l => l.map(s => s.textContent));
    const grafo44 = (() => { try { return JSON.parse(ld44[0])['@graph']; } catch (e) { return []; } })();
    const prod44 = grafo44.find(x => x['@type'] === 'Product');
    ok('2.29.0: il JSON-LD del sito porta il Product con i testi puliti, JSON_HEX_TAG (nessun </script> dentro lo script), niente finestre', !!rs2 && rs2.status() === 200 && ld44.length === 1 && !/<\/script/i.test(ld44[0]) && !/<img|onerror/.test(ld44[0]) && !!prod44 && /^Vaso /.test(prod44.name) && fin44.length === 0, prod44 && [prod44.name, prod44.sku]);
    await admin.p.goto(ADMIN + '/contenuto.php?tipo=pagine&id=' + pag44.id, { waitUntil: 'networkidle' });
    ok('2.29.0: la scheda rimostra i dati in più come testo nei campi', await admin.p.locator('img[onerror]').count() === 0 && (await admin.p.inputValue('#sc-schema-prodotto-nome').catch(() => '')).startsWith('Vaso '));
    const rifiutiV = [];
    for (const [campo, valore] of [['url', 'javascript:alert(44)'], ['url', 'data:text/html,<script>alert(44)</script>'], ['url', 'http://video.esempio.it/x.mp4'], ['incorporo', 'javascript:alert(44)'], ['incorporo', 'http://player.esempio.it/x'], ['miniatura', 'javascript:alert(44)']]) {
      const f = { 'schema[tipo]': 'video', 'schema[video][nome]': 'Video sic44', 'schema[video][descrizione]': 'Descrizione.', 'schema[video][miniatura]': foto44.url || '/uploads/x.png', 'schema[video][data]': '2026-11-05', 'schema[video][url]': 'https://video.esempio.it/ok.mp4', 'schema[video][incorporo]': '', 'schema[video][durata]': '1:00' };
      f['schema[video][' + campo + ']'] = valore;
      const r = await admin.ctx.request.post(ADMIN + '/contenuto.php?tipo=pagine&id=' + pag44.id, { form: scheda44(f), headers: ORIG44, maxRedirects: 0 });
      const dopo = cli44('sql', "SELECT COALESCE(seo_schema, '') s FROM {p}pages WHERE id = " + (+pag44.id));
      const ds = Array.isArray(dopo) && dopo[0] ? String(dopo[0].s) : '';
      if (!([302, 303].includes(r.status()) && !/video/.test(ds) && !/javascript|data:/.test(ds))) rifiutiV.push([campo, valore, r.status(), ds.slice(0, 80)]);
    }
    ok('2.29.0: video con indirizzo javascript:, data: o http:, incorporo javascript: o http:, miniatura javascript:: i dati in più non si salvano (restano quelli di prima)', rifiutiV.length === 0, rifiutiV);
    // 3. 500 passi di how-to: ne restano 20, niente errore fatale.
    const passi500 = {};
    for (let i = 0; i < 500; i++) passi500['schema[howto][passi][' + i + '][nome]'] = 'Passo ' + i;
    const s4 = await admin.ctx.request.post(ADMIN + '/contenuto.php?tipo=pagine&id=' + pag44.id, { form: scheda44(Object.assign({ 'schema[tipo]': 'howto', 'schema[howto][nome]': 'Guida sic44', 'schema[howto][tempo]': '10' }, passi500)), headers: ORIG44, maxRedirects: 0 });
    const sc44c = cli44('sql', "SELECT COALESCE(seo_schema, '') s FROM {p}pages WHERE id = " + (+pag44.id));
    let howto44 = null; try { howto44 = JSON.parse(Array.isArray(sc44c) && sc44c[0] ? String(sc44c[0].s) : ''); } catch (e) {}
    const rs3 = await pg44.goto(BASE + '/sic44-pagina/', { waitUntil: 'networkidle' });
    const grafo44c = await pg44.locator('script[type="application/ld+json"]').evaluateAll(l => l.map(s => { try { return JSON.parse(s.textContent)['@graph']; } catch (e) { return []; } }));
    const howtoNodo = (grafo44c[0] || []).find(x => x['@type'] === 'HowTo');
    ok('2.29.0: 500 passi di how-to: si tengono i primi 20, la pagina risponde 200 con 20 HowToStep', [302, 303].includes(s4.status()) && howto44 && howto44.tipo === 'howto' && howto44.howto.passi.length === 20 && !!rs3 && rs3.status() === 200 && !!howtoNodo && howtoNodo.step.length === 20, [s4.status(), howto44 && howto44.howto.passi.length, howtoNodo && howtoNodo.step.length]);
    // 4. Senza token, da un altro sito, redattore.
    const titPrima44 = (cli44('sql', 'SELECT seo_title t FROM {p}pages WHERE id = ' + (+pag44.id)) || [{}])[0].t;
    const c1 = await admin.ctx.request.post(ADMIN + '/seo-api.php', { form: { azione: 'tabella_salva', tipo: 'pagine', id: String(pag44.id), seo_title: 'senza token' }, headers: ORIG44, maxRedirects: 0 });
    const c2 = await admin.ctx.request.post(ADMIN + '/seo-api.php', { form: { _csrf: t44, azione: 'tabella_salva', tipo: 'pagine', id: String(pag44.id), seo_title: 'altro sito' }, headers: { 'Origin': 'https://evil.example' }, maxRedirects: 0 });
    const c3 = await admin.ctx.request.post(ADMIN + '/seo-api.php', { form: { azione: 'media_alt_salva', url: foto44.url || '', alt: 'senza token' }, headers: ORIG44, maxRedirects: 0 });
    const tit44 = cli44('sql', 'SELECT seo_title t FROM {p}pages WHERE id = ' + (+pag44.id));
    ok('2.29.0: tabella_salva senza token o da un altro sito, media_alt_salva senza token: 403 e niente salvato', c1.status() === 403 && c2.status() === 403 && c3.status() === 403 && Array.isArray(tit44) && tit44[0].t === titPrima44 && !/senza token|altro sito/.test(String(tit44[0].t)), [c1.status(), c2.status(), c3.status(), tit44 && tit44[0].t]);
    const c4 = await admin.ctx.request.post(ADMIN + '/seo-api.php', { form: { _csrf: t44, azione: 'tabella_salva', tipo: 'modelli', id: '1', seo_title: 'x' }, headers: ORIG44, maxRedirects: 0 });
    const c5 = await admin.ctx.request.post(ADMIN + '/seo-api.php', { form: { _csrf: t44, azione: 'tabella_salva', tipo: 'pagine', id: '999999', seo_title: 'x' }, headers: ORIG44, maxRedirects: 0 });
    const c6 = await admin.ctx.request.post(ADMIN + '/seo-api.php', { form: { _csrf: t44, azione: 'media_alt_salva', url: '../../includes/config.local.php', alt: 'x' }, headers: ORIG44, maxRedirects: 0 });
    const c7 = await admin.ctx.request.post(ADMIN + '/seo-api.php', { form: { _csrf: t44, azione: 'media_alt_salva', url: '/includes/config.local.php', alt: 'x' }, headers: ORIG44, maxRedirects: 0 });
    cli44('alt_libreria'); // la tabella media_alt nasce al primo uso: su un sito dove nessun alt è mai stato salvato la query sotto fallirebbe (trovato il 5 novembre sui siti della batteria)
    const nAlt = cli44('sql', "SELECT COUNT(*) c FROM {p}media_alt WHERE percorso LIKE '%config%'");
    ok('2.29.0: tabella_salva con tipo modelli o id inesistente, media_alt_salva con un file fuori dalla libreria: rifiutati (404 o 400), niente scritto', c4.status() === 404 && c5.status() === 400 && c6.status() === 400 && c7.status() === 400 && Array.isArray(nAlt) && +nAlt[0].c === 0, [c4.status(), c5.status(), c6.status(), c7.status()]);
    if (red.dentro) {
      const tRed44 = await tokenMeta44(red.p, ADMIN + '/seo-tabella.php');
      const r1 = await red.ctx.request.post(ADMIN + '/seo.php?scheda=globale', { form: { _csrf: tRed44, azione: 'salva_titoli', 'titolo[sep]': 'x' }, headers: ORIG44, maxRedirects: 0 });
      const r2 = await red.ctx.request.post(ADMIN + '/seo-api.php', { form: { _csrf: tRed44, azione: 'sedi_cerca', indirizzo: 'x' }, headers: ORIG44, maxRedirects: 0 });
      const r3 = await red.ctx.request.post(ADMIN + '/seo-api.php', { form: { _csrf: tRed44, azione: 'tabella_salva', tipo: 'pagine', id: '999999', seo_title: 'x' }, headers: ORIG44, maxRedirects: 0 });
      const r4 = await red.ctx.request.get(ADMIN + '/seo-tabella.php', { maxRedirects: 0 });
      ok('2.29.0: il redattore apre la Tabella SEO (200) ma non salva la configurazione SEO (302/403), non cerca le coordinate (403), non salva un contenuto che non esiste (400)', r4.status() === 200 && [302, 303, 403].includes(r1.status()) && r2.status() === 403 && r3.status() === 400, [r4.status(), r1.status(), r2.status(), r3.status()]);
    }
    // 5. Una keyword con codice nei suggerimenti: l'analisi (scheda ed editor) la mostra come testo.
    const a1 = await admin.ctx.request.post(ADMIN + '/seo-api.php', { form: { _csrf: t44, azione: 'analisi', title: 'Articolo sic44 sui vasi', slug: 'sic44-articolo', keyword_target: CATTIVO + ' vasi terracotta', contenuto: '<p>Un articolo sui vasi in terracotta.</p>', tipo_contenuto: 'articoli', id: String(art44.id || 0) }, headers: ORIG44, maxRedirects: 0 });
    const ja1 = await a1.json().catch(() => null);
    const tEd44 = await tokenDi(admin.p, ADMIN + '/editor.php?tipo=articoli&id=' + art44.id);
    const a2 = await post(admin.p, ADMIN + '/editor-api.php', new URLSearchParams({ azione: 'seo', tipo: 'articoli', id: String(art44.id), _csrf: tEd44, albero: '{"versione":1,"elementi":[]}', titolo: 'Articolo sic44 sui vasi' }).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
    let ja2 = null; try { ja2 = JSON.parse(a2.body); } catch (e) {}
    const htmlA = (ja1 && ja1.html || '') + (ja2 && ja2.html || '');
    ok('2.29.0: keyword con codice: il riquadro Collega e la riga dei link (scheda ed editor) escono con l\'escape, niente tag', a1.status() === 200 && ja1 && /seo-collega|Collegamenti interni|link interni/.test(ja1.html || '') && !/<img src=x onerror/.test(htmlA) && /&lt;img src=x onerror=alert\(44\)&gt;|&lt;img/.test(htmlA) && (a2.status === 200), [a1.status(), a2.status, (htmlA.match(/parlano di [^.]{0,80}/) || [''])[0]]);
    // 6. Un alt con codice: salvato come testo, sul sito con l'escape.
    const alt1 = await admin.ctx.request.post(ADMIN + '/seo-api.php', { form: { _csrf: t44, azione: 'media_alt_salva', url: foto44.url || '', alt: 'Alt "sic44" ' + SCRIPT + CATTIVO }, headers: ORIG44, maxRedirects: 0 });
    const jalt = await alt1.json().catch(() => null);
    const rs4 = await pg44.goto(BASE + '/sic44-pagina/', { waitUntil: 'networkidle' });
    const img44 = await pg44.locator('img[src*="sic44"]').first().getAttribute('alt').catch(() => null);
    const h44b = await pg44.content();
    ok('2.29.0: un alt con codice: salvato senza tag e sul sito esce nell\'attributo con l\'escape (virgolette comprese), niente script', alt1.status() === 200 && jalt && jalt.ok && !/</.test(jalt.alt) && /^Alt "sic44"/.test(jalt.alt) && !!rs4 && img44 !== null && img44.startsWith('Alt "sic44"') && !/<script>alert\(44\)/.test(h44b) && fin44.length === 0, [jalt && jalt.alt, img44]);
    await sito44.close();
    // Pulizia.
    cli44('sql', "DELETE FROM {p}pages WHERE slug = 'sic44-pagina'"); cli44('sql', "DELETE FROM {p}articles WHERE slug = 'sic44-articolo'");
    cli44('sql', "DELETE FROM {p}revisioni WHERE (tipo = 'pagine' AND contenuto_id = " + (+pag44.id) + ") OR (tipo = 'articoli' AND contenuto_id = " + (+art44.id) + ")");
    cli44('sql', "DELETE FROM {p}pagine_revisioni WHERE (tipo = 'pagine' AND pagina_id = " + (+pag44.id) + ") OR (tipo = 'articoli' AND pagina_id = " + (+art44.id) + ")");
    cli44('sql', "DELETE FROM {p}media_alt WHERE percorso LIKE '%sic44%'");
    cli44('sql', "UPDATE {p}settings SET setting_value = (SELECT COUNT(*) FROM {p}media_alt WHERE alt <> '') WHERE setting_key = 'media_alt_n'");
    cli44('foto_togli', 'sic44');
  } else console.log('--  strumenti SEO (2.29.0): saltata (serve CMS_DIR)');

  // === 45. (2.31.0) Il pulsante "Apri nel pannello" negli avvisi (Impostazioni > Configurazione email, "Gli avvisi che ricevi",
  // impostazione avvisi_pulsante_pannello). Il redattore non la accende e non manda la prova; senza token o da un altro sito si
  // rifiuta; l'anteprima è un iframe sandbox con srcdoc (niente script, niente tag fuori dall'attributo).
  {
    const ORIG45 = { 'Content-Type': 'application/x-www-form-urlencoded', 'Origin': BASE.replace(/(https?:\/\/[^/]+).*/, '$1') };
    const stato45 = async () => { await admin.p.goto(ADMIN + '/impostazioni.php', { waitUntil: 'networkidle' }); return await admin.p.locator('#avvisi_pulsante_pannello').isChecked().catch(() => null); };
    const prima45 = await stato45();
    const tRed45 = await tokenDi(red.p, ADMIN + '/contenuti.php?tipo=articoli');
    const r1 = await red.ctx.request.post(ADMIN + '/impostazioni.php', { maxRedirects: 0, headers: ORIG45, data: new URLSearchParams({ _csrf: tRed45, action: 'save_avvisi', avvisi_pulsante_pannello: prima45 ? '' : '1' }).toString() });
    const r2 = await red.ctx.request.post(ADMIN + '/impostazioni.php', { maxRedirects: 0, headers: ORIG45, data: new URLSearchParams({ _csrf: tRed45, action: 'prova_avviso' }).toString() });
    const dopoRed45 = await stato45();
    ok('2.31.0: il redattore non cambia il pulsante degli avvisi e non manda la prova (rimando o 403, valore uguale)', [302, 303, 403].includes(r1.status()) && [302, 303, 403].includes(r2.status()) && dopoRed45 === prima45, [r1.status(), r2.status(), prima45, dopoRed45]);
    const a1 = await admin.ctx.request.post(ADMIN + '/impostazioni.php', { maxRedirects: 0, headers: ORIG45, data: new URLSearchParams({ action: 'save_avvisi', avvisi_pulsante_pannello: prima45 ? '' : '1' }).toString() });
    const tAd45 = await tokenDi(admin.p, ADMIN + '/impostazioni.php');
    const a2 = await admin.ctx.request.post(ADMIN + '/impostazioni.php', { maxRedirects: 0, headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Origin': 'https://evil.example' }, data: new URLSearchParams({ _csrf: tAd45, action: 'save_avvisi', avvisi_pulsante_pannello: prima45 ? '' : '1' }).toString() });
    const dopoAd45 = await stato45();
    ok('2.31.0: senza token o da un altro sito il pulsante degli avvisi non cambia', a1.status() === 403 && [400, 403].includes(a2.status()) && dopoAd45 === prima45, [a1.status(), a2.status(), dopoAd45]);
    const cornice45 = admin.p.locator('#avvisi iframe.av-cornice');
    const sb45 = await cornice45.getAttribute('sandbox').catch(() => null);
    const html45 = await admin.p.content();
    ok('2.31.0: l\'anteprima dell\'avviso sta in un iframe sandbox senza permessi, e la pagina non ha tag dell\'email fuori dall\'attributo', sb45 === '' && (await admin.p.locator('body > table, #avvisi > table').count()) === 0 && !/<iframe[^>]*srcdoc="[^"]*<[a-z]/i.test(html45), [sb45]);
  }

  // === 46. (2.32.0) L'addon AI collegate: il server MCP (/api/mcp), l'OAuth 2.1 (authorize, token, register, revoke), le chiavi
  // personali e il REST. Senza token, token scaduto, revocato o di un altro sito; PKCE sbagliato o assente; redirect_uri diverso;
  // codice riusato; registrazione con javascript:, data:, http esterno o frammento; permessi gonfiati (anche con il rinnovo);
  // nessun modo di pubblicare; update_draft su un pubblicato; HTML, script e caratteri a 4 byte nei testi; alberi giganti e
  // corpo oltre 1 MB; import_photo da host fuori elenco, interni (127.0.0.1, 169.254.169.254), con codice nel file o SVG;
  // CSRF e Origin estraneo sul consenso; Origin estraneo sul server MCP; limite di velocità; redattore. Accende l'addon (se era
  // spento lo rispegne alla fine) e il server finto delle foto (prove/foto-finte.php, porta SIC_FOTO, 9298). Serve CMS_DIR.
  if (DIR && fs.existsSync(path.join(DIR, 'addon', 'ai-collegate', 'modulo.php')) && fs.existsSync(path.join(__dirname, '..', 'prove', 'ai-cli.php'))) {
    const { execFileSync, spawn } = require('child_process');
    const crypto = require('crypto');
    const cli46 = (...a) => { try { return JSON.parse(execFileSync('php', [path.join(__dirname, '..', 'prove', 'ai-cli.php'), DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }).trim().split('\n').pop()); } catch (e) { return { errore_cli: String(e.message).slice(0, 200) }; } };
    const attiviFile = path.join(DIR, 'moduli', 'addon-attivi.json');
    const eraAcceso = (() => { try { return JSON.parse(fs.readFileSync(attiviFile, 'utf8')).includes('ai-collegate'); } catch (e) { return false; } })();
    cli46('accendi');
    const PF = +(process.env.SIC_FOTO || 9298);
    const srvFoto = spawn('php', ['-S', '127.0.0.1:' + PF, path.join(__dirname, '..', 'prove', 'foto-finte.php')], { stdio: 'ignore' });
    cli46('config-foto', 'http://127.0.0.1:' + PF);
    await new Promise(r => setTimeout(r, 2500)); // opcache del server di prova: il config nuovo si legge dopo un paio di secondi
    const MCP = BASE + '/api/mcp', CB = 'http://127.0.0.1:9/callback';
    const b64u = (b) => b.toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const pk = () => { const v = b64u(crypto.randomBytes(32)); return { v, c: b64u(crypto.createHash('sha256').update(v).digest()) }; };
    const h46 = async (metodo, url, { corpo, form, token, intest = {} } = {}) => {
      const h = { ...intest }; let b;
      if (token) h.Authorization = 'Bearer ' + token;
      if (form) { h['Content-Type'] = 'application/x-www-form-urlencoded'; b = new URLSearchParams(form).toString(); }
      else if (corpo !== undefined) { h['Content-Type'] = 'application/json'; b = typeof corpo === 'string' ? corpo : JSON.stringify(corpo); }
      const r = await fetch(url, { method: metodo, headers: h, body: b, redirect: 'manual' });
      const testo = await r.text(); let json = null; try { json = JSON.parse(testo); } catch (e) {}
      return { stato: r.status, json, testo, h: r.headers };
    };
    let idr = 1;
    const call = async (token, nome, args, intest) => {
      const r = await h46('POST', MCP, { token, intest, corpo: { jsonrpc: '2.0', id: idr++, method: 'tools/call', params: { name: nome, arguments: args || {} } } });
      const res = r.json && r.json.result; let dati = null; try { dati = JSON.parse(res.content[0].text); } catch (e) {}
      return { stato: r.stato, errore: !res || !!res.isError, dati };
    };
    const sqlUno = (q) => { const x = cli46('sql', q); return Array.isArray(x) ? x[0] || {} : {}; };
    try {
      cli46('pulisci');
      const pub = cli46('pagina-pubblicata', 'ai-prova-sic46');
      const tutto = cli46('chiave', 'leggi,bozze,proposte,foto', 'Sic46 tutto').chiave;
      // 1. Senza token, token inventato, scaduto, revocato, di un altro sito.
      const s0 = await h46('POST', MCP, { corpo: { jsonrpc: '2.0', id: 1, method: 'tools/list' } });
      const s1 = await call('pca_' + b64u(crypto.randomBytes(32)), 'site_info');
      const kScad = cli46('chiave', 'leggi', 'Sic46 scaduta').chiave; cli46('token-scaduto', kScad);
      const kAltro = cli46('chiave', 'leggi', 'Sic46 altro sito').chiave; cli46('token-altro-sito', kAltro);
      const kRev = cli46('chiave', 'leggi', 'Sic46 revocata'); cli46('sql', 'UPDATE {p}ai_connessioni SET revocato = NOW() WHERE id = ' + (+kRev.connessione));
      const [s2, s3, s4] = [await call(kScad, 'site_info'), await call(kAltro, 'site_info'), await call(kRev.chiave, 'site_info')];
      ok('2.32.0: server MCP senza token, con un token inventato, scaduto, di un altro sito o revocato: 401 con WWW-Authenticate', [s0, s1, s2, s3, s4].every(x => (x.stato || x.stato) === 401) && /resource_metadata=/.test(s0.h.get('www-authenticate') || ''), [s0.stato, s1.stato, s2.stato, s3.stato, s4.stato]);
      const rNo = await h46('POST', MCP + '/rest/site_info', { corpo: {}, token: kScad });
      ok('2.32.0: REST con una chiave scaduta: 401', rNo.stato === 401, rNo.stato);
      // 2. Origin estraneo, gruppi JSON-RPC, corpo oltre 1 MB, GET.
      const o1 = await call(tutto, 'site_info', {}, { Origin: 'https://evil.example' });
      const o2 = await h46('POST', MCP, { token: tutto, corpo: [{ jsonrpc: '2.0', id: 1, method: 'ping' }, { jsonrpc: '2.0', id: 2, method: 'ping' }] });
      const o3 = await h46('POST', MCP, { token: tutto, corpo: '{"jsonrpc":"2.0","id":1,"method":"ping","x":"' + 'a'.repeat(1100000) + '"}' });
      ok('2.32.0: Origin estraneo 403, gruppo di messaggi JSON-RPC 400, corpo oltre 1 MB 413', o1.stato === 403 && o2.stato === 400 && o3.stato === 413, [o1.stato, o2.stato, o3.stato]);
      // 3. Registrazione dinamica con redirect cattivi.
      const regCattivi = [];
      for (const u of ['javascript:alert(46)', 'data:text/html,<script>alert(46)</script>', 'http://evil.example/cb', 'https://buono.example/cb#frammento', 'https://utente:pw@buono.example/cb', 'file:///etc/passwd', 'https://x.example/"><script>']) {
        const r = await h46('POST', MCP + '/register', { corpo: { client_name: 'Sic46', redirect_uris: [u] } });
        if (r.stato !== 400) regCattivi.push([u, r.stato]);
      }
      const regNome = await h46('POST', MCP + '/register', { corpo: { client_name: '<img src=x onerror=alert(46)>Sic46 &#128512; \u{1F600}', redirect_uris: [CB] } });
      ok('2.32.0: registrazione con redirect javascript:, data:, http esterno, frammento, credenziali, file: o virgolette: tutte 400', regCattivi.length === 0, regCattivi);
      ok('2.32.0: il nome del client con tag ed emoji si salva come testo pulito, senza caratteri a 4 byte', regNome.stato === 201 && !/[<>]/.test(regNome.json.client_name) && !/[\u{10000}-\u{10FFFF}]/u.test(regNome.json.client_name), regNome.json && regNome.json.client_name);
      const CID = regNome.json.client_id;
      // 4. authorize: senza PKCE, con plain, con scope gonfiati, con redirect diverso, risorsa di un altro sito.
      const auth = (q) => h46('GET', MCP + '/authorize?' + new URLSearchParams({ response_type: 'code', client_id: CID, redirect_uri: CB, state: 's46', ...q }).toString());
      const k1 = pk();
      const a1 = await auth({});
      const a2 = await auth({ code_challenge: k1.c, code_challenge_method: 'plain' });
      const a3 = await auth({ code_challenge: k1.c, code_challenge_method: 'S256', scope: 'leggi utenti pubblica' });
      const a4 = await auth({ code_challenge: k1.c, code_challenge_method: 'S256', redirect_uri: 'https://evil.example/cb' });
      const a5 = await auth({ code_challenge: k1.c, code_challenge_method: 'S256', resource: 'https://altro-sito.example/api/mcp' });
      const loc = (r) => r.h.get('location') || '';
      ok('2.32.0: authorize senza PKCE o con "plain": rimanda con error=invalid_request, senza codice', /error=invalid_request/.test(loc(a1)) && /error=invalid_request/.test(loc(a2)) && !/code=/.test(loc(a1) + loc(a2)), [loc(a1).slice(0, 90), loc(a2).slice(0, 90)]);
      ok('2.32.0: authorize con permessi inventati (utenti, pubblica): invalid_scope; con una risorsa di un altro sito: invalid_target', /error=invalid_scope/.test(loc(a3)) && /error=invalid_target/.test(loc(a5)), [loc(a3).slice(0, 80), loc(a5).slice(0, 80)]);
      ok('2.32.0: authorize con un redirect_uri non registrato: pagina d\'errore, nessun rimando', a4.stato === 400 && loc(a4) === '' && !/evil\.example/.test(a4.testo.replace(/<[^>]+>/g, '')), a4.stato);
      // 5. Consenso: CSRF e Origin estraneo, poi un consenso vero dal browser per i codici.
      const aOk = await auth({ code_challenge: k1.c, code_challenge_method: 'S256', scope: 'leggi bozze' });
      const urlLogin = new URL(loc(aOk), BASE);
      const rid = urlLogin.searchParams.get('r');
      const urlCons = ADMIN + '/addon-pagina.php?a=ai-collegate&p=consenso&r=' + rid;
      const c1 = await admin.ctx.request.post(urlCons, { form: { azione: 'consenti', 'permessi[]': 'leggi' }, maxRedirects: 0 });
      const tCons = await tokenDi(admin.p, urlCons);
      const c2 = await admin.ctx.request.post(urlCons, { form: { _csrf: tCons, azione: 'consenti', 'permessi[]': 'leggi' }, headers: { Origin: 'https://evil.example' }, maxRedirects: 0 });
      const codiciDopo = sqlUno('SELECT COUNT(*) n FROM {p}ai_codici').n;
      ok('2.32.0: consenso senza token CSRF o da un altro sito: rifiutato (403), nessun codice creato', [400, 403].includes(c1.status()) && [400, 403].includes(c2.status()) && +codiciDopo === 0, [c1.status(), c2.status(), codiciDopo]);
      if (red.dentro) {
        const rc = await red.ctx.request.get(urlCons, { maxRedirects: 0 });
        const tRed = await tokenDi(red.p, ADMIN + '/contenuti.php?tipo=articoli');
        const rc2 = await red.ctx.request.post(urlCons, { form: { _csrf: tRed, azione: 'consenti', 'permessi[]': 'leggi' }, maxRedirects: 0 });
        const rc3 = await red.ctx.request.post(ADMIN + '/addon-pagina.php?a=ai-collegate&p=collegate', { form: { _csrf: tRed, azione: 'chiave', nome: 'red', 'permessi[]': 'bozze' }, maxRedirects: 0 });
        ok('2.32.0: il redattore non apre il consenso, non lo dà e non crea chiavi (rimando o 403, niente nel database)', ![200].includes(rc.status()) && ![200].includes(rc2.status()) && ![200].includes(rc3.status()) && +sqlUno('SELECT COUNT(*) n FROM {p}ai_codici').n === 0 && +sqlUno("SELECT COUNT(*) n FROM {p}ai_connessioni WHERE nome = 'red'").n === 0, [rc.status(), rc2.status(), rc3.status()]);
      }
      let ritorno = null;
      await admin.ctx.route('http://127.0.0.1:9/**', r => { ritorno = new URL(r.request().url()); r.fulfill({ status: 200, contentType: 'text/html', body: 'ok' }); });
      const consenti = async (sfida, scope) => {
        ritorno = null;
        const r = await auth({ code_challenge: sfida, code_challenge_method: 'S256', scope });
        await admin.p.goto(new URL(loc(r), BASE).href, { waitUntil: 'networkidle' });
        if (!(await admin.p.locator('#aic-consenso').count())) return null;
        await admin.p.click('#aic-consenso button[value=consenti]');
        for (let i = 0; i < 50 && !ritorno; i++) await admin.p.waitForTimeout(100);
        return ritorno && ritorno.searchParams.get('code');
      };
      const tok = (f) => h46('POST', MCP + '/token', { form: { client_id: CID, redirect_uri: CB, ...f } });
      const code1 = await consenti(k1.c, 'leggi bozze');
      const t1 = await tok({ grant_type: 'authorization_code', code: code1 || 'x', code_verifier: pk().v });
      const t2 = await tok({ grant_type: 'authorization_code', code: code1 || 'x' });
      const t3 = await tok({ grant_type: 'authorization_code', code: code1 || 'x', code_verifier: k1.v, redirect_uri: 'http://127.0.0.1:9/altro' });
      const t4 = await tok({ grant_type: 'authorization_code', code: code1 || 'x', code_verifier: k1.v, client_id: 'aic_' + '0'.repeat(32) });
      ok('2.32.0: token con PKCE sbagliato, senza code_verifier, con un redirect_uri diverso o un altro client: rifiutati', !!code1 && t1.stato === 400 && t2.stato === 400 && t3.stato === 400 && [400, 401].includes(t4.stato), [!!code1, t1.stato, t2.stato, t3.stato, t4.stato]);
      const t5 = await tok({ grant_type: 'authorization_code', code: code1 || 'x', code_verifier: k1.v });
      const t6 = await tok({ grant_type: 'authorization_code', code: code1 || 'x', code_verifier: k1.v });
      const dopoRiuso = await call(t5.json && t5.json.access_token, 'site_info');
      ok('2.32.0: codice usato due volte: la seconda volta invalid_grant e i token del primo uso non valgono più', t5.stato === 200 && t6.stato === 400 && dopoRiuso.stato === 401, [t5.stato, t6.stato, dopoRiuso.stato]);
      const k2 = pk();
      const code2 = await consenti(k2.c, 'leggi bozze');
      const t7 = await tok({ grant_type: 'authorization_code', code: code2 || 'x', code_verifier: k2.v });
      const gonfio = await tok({ grant_type: 'refresh_token', refresh_token: t7.json && t7.json.refresh_token, scope: 'leggi bozze proposte foto' });
      ok('2.32.0: rinnovo che chiede più permessi di quelli dati: invalid_scope', t7.stato === 200 && gonfio.stato === 400 && gonfio.json && gonfio.json.error === 'invalid_scope', [t7.stato, gonfio.stato, gonfio.json]);
      const AT = t7.json && t7.json.access_token;
      const fotoNo = await call(AT, 'search_photos', { query: 'vaso' });
      ok('2.32.0: un collegamento con "leggi bozze" non cerca foto e non propone modifiche', fotoNo.errore && /permesso "foto"/.test(fotoNo.dati.errore) && (await call(AT, 'propose_change', { tipo: 'pagine', id: pub.id, campo: 'titolo', valore: 'x', motivo: 'prova sic46' })).errore, fotoNo.dati);
      await admin.ctx.unroute('http://127.0.0.1:9/**');
      // 6. Nessun modo di pubblicare.
      const pubbl = await call(tutto, 'create_draft', { tipo: 'articoli', titolo: 'Sic46 pubblica', slug: 'ai-prova-sic46-pub', stato: 'pubblicato', pubblica: true, status: 'published', publish: true, data: '2020-01-01' });
      const st46 = sqlUno("SELECT status, struttura FROM {p}articles WHERE slug = 'ai-prova-sic46-pub'");
      const nomi46 = (await h46('POST', MCP, { token: tutto, corpo: { jsonrpc: '2.0', id: 9, method: 'tools/list' } })).json.result.tools.map(t => t.name);
      const pubT = await h46('POST', MCP, { token: tutto, corpo: { jsonrpc: '2.0', id: 10, method: 'tools/call', params: { name: 'publish_content', arguments: { tipo: 'pagine', id: pub.id } } } });
      const pubR = await h46('POST', MCP + '/rest/publish', { token: tutto, corpo: { id: 1 } });
      ok('2.32.0: create_draft che chiede "pubblicato" in ogni modo resta bozza; nessuno strumento pubblica (publish_content e /rest/publish non esistono)', !pubbl.errore && st46.status === 'draft' && !nomi46.some(n => /publish|pubblic|user|password|update_cms|theme|backup/.test(n)) && pubT.json && pubT.json.error && pubR.stato === 404, [st46.status, nomi46.length, pubR.stato]);
      // 7. update_draft su un pubblicato e su un programmato.
      const udp = await call(tutto, 'update_draft', { tipo: 'pagine', id: pub.id, titolo: 'Sic46 cambiato', albero: { elementi: [{ tipo: 'titolo', impostazioni: { testo: 'cambiato' } }] } });
      const dopoPub = sqlUno('SELECT title, struttura_bozza FROM {p}pages WHERE id = ' + (+pub.id));
      cli46('sql', "UPDATE {p}articles SET status = 'published', publish_date = DATE_ADD(CURDATE(), INTERVAL 10 DAY) WHERE slug = 'ai-prova-sic46-pub'");
      const prog = sqlUno("SELECT id FROM {p}articles WHERE slug = 'ai-prova-sic46-pub'");
      const udq = await call(tutto, 'update_draft', { tipo: 'articoli', id: prog.id, titolo: 'Sic46 cambiato' });
      ok('2.32.0: update_draft su una pagina pubblicata e su un articolo programmato: rifiutato, niente cambia (né la scheda né la bozza dell\'editor)', udp.errore && udq.errore && dopoPub.title === 'Pagina pubblicata di prova' && !dopoPub.struttura_bozza && sqlUno('SELECT title FROM {p}articles WHERE id = ' + (+prog.id)).title === 'Sic46 pubblica', [udp.dati, udq.dati]);
      // 8. HTML, script, 4 byte e slug.
      const cat = await call(tutto, 'create_draft', { tipo: 'articoli', titolo: '<script>alert(46)</script>Titolo \u{1F600} sic46', slug: '../../ai-prova-sic46-<b>slug', estratto: '<img src=x onerror=alert(46)>estratto', testo: '<p onclick="alert(46)">Ciao</p><script>alert(46)</script><iframe src="https://evil.example"></iframe><a href="javascript:alert(46)">x</a> \u{1F680}',
        seo: { titolo_seo: '<svg onload=alert(46)>', descrizione_seo: 'desc \u{1F600} <script>x</script>', domande_frequenti: [{ domanda: '<b>Q</b>\u{1F600}', risposta: '<script>r</script>R' }], in_breve: '<style>*{}</style>breve' },
        albero: { elementi: [{ tipo: 'testo', impostazioni: { contenuto: '<p>ok</p><script>alert(46)</script><img src=x onerror=alert(46)> \u{1F600}', css_personalizzato: 'body{display:none}', attributi: 'onclick|alert(46)' } }, { tipo: 'pulsante', impostazioni: { testo: 'Vai', link: { url: 'javascript:alert(46)' } } }] } });
      const rc = cat.dati && cat.dati.id ? cli46('sql', 'SELECT * FROM {p}articles WHERE id = ' + (+cat.dati.id))[0] || {} : {};
      const tuttoTesto = JSON.stringify(rc);
      ok('2.32.0: testi dell\'AI con script, eventi, iframe, javascript:, stili e CSS personalizzato: nel database niente di tutto questo', !cat.errore && !/<script|onerror|onclick|onload|<iframe|javascript:|<style|css_personalizzato|display:none/i.test(tuttoTesto) && /Ciao/.test(rc.content || rc.contenuto || tuttoTesto), cat.dati);
      ok('2.32.0: nessun carattere a 4 byte nel database, slug pulito senza ../', !/[\u{10000}-\u{10FFFF}]/u.test(tuttoTesto) && /^[a-z0-9-]+$/.test(rc.slug || '') && !/\.\./.test(rc.slug || ''), rc.slug);
      // 9. Alberi giganti.
      const tanti = Array.from({ length: 260 }, () => ({ tipo: 'titolo', impostazioni: { testo: 'x' } }));
      const g1 = await call(tutto, 'create_draft', { tipo: 'pagine', titolo: 'Sic46 grande', slug: 'ai-prova-sic46-g1', albero: { elementi: tanti } });
      const g2 = await call(tutto, 'create_draft', { tipo: 'pagine', titolo: 'Sic46 colonne', slug: 'ai-prova-sic46-g2', albero: { elementi: [{ tipo: 'sezione', figli: Array.from({ length: 7 }, () => ({ tipo: 'colonna', figli: [{ tipo: 'titolo', impostazioni: { testo: 'x' } }] })) }] } });
      const g3 = await call(tutto, 'create_draft', { tipo: 'pagine', titolo: 'Sic46 pesante', slug: 'ai-prova-sic46-g3', albero: { elementi: [{ tipo: 'testo', impostazioni: { contenuto: 'x'.repeat(450000) } }] } });
      const g4 = await call(tutto, 'create_draft', { tipo: 'pagine', titolo: 'Sic46 sezioni', slug: 'ai-prova-sic46-g4', albero: { elementi: Array.from({ length: 70 }, () => ({ tipo: 'sezione', figli: [{ tipo: 'colonna', figli: [] }] })) } });
      ok('2.32.0: alberi con 260 widget, 7 colonne, oltre 400 KB o 70 sezioni: rifiutati con un errore chiaro, nessuna bozza', [g1, g2, g3, g4].every(x => x.errore && x.dati && x.dati.errore) && +sqlUno("SELECT COUNT(*) n FROM {p}pages WHERE slug LIKE 'ai-prova-sic46-g%'").n === 0, [g1, g2, g3, g4].map(x => (x.dati && x.dati.errore || '').slice(0, 50)));
      // 10. Foto: host fuori elenco, interni, file con codice, SVG.
      const fot = [];
      for (const u of ['https://evil.example/x.jpg', 'http://127.0.0.1:' + (PF + 1) + '/x.jpg', 'http://169.254.169.254/latest/meta-data/', 'https://169.254.169.254/x.jpg', 'http://[::1]/x.jpg', 'file:///etc/passwd', 'http://127.0.0.1:' + PF + '.evil.example/x.jpg']) {
        const r = await call(tutto, 'import_photo', { archivio: 'openverse', id: 'cc0a', alt: 'Prova sic46', url: u });
        if (!r.errore) fot.push([u, r.dati && r.dati.url]);
      }
      ok('2.32.0: import_photo con un indirizzo fuori elenco o interno (127.0.0.1 su un\'altra porta, 169.254.169.254, ::1, file:): rifiutati', fot.length === 0, fot);
      const fc = await call(tutto, 'import_photo', { archivio: 'openverse', id: 'cc0a', alt: 'Prova sic46 codice', url: 'http://127.0.0.1:' + PF + '/file/codice.jpg' });
      const fileC = fc.dati && fc.dati.url ? path.join(DIR, fc.dati.url.slice(new URL(BASE).pathname.replace(/\/$/, '').length)) : ''; // 2.33.0: nei siti in cartella l'indirizzo ha la cartella davanti
      const contC = fileC && fs.existsSync(fileC) ? fs.readFileSync(fileC, 'latin1') : '';
      ok('2.32.0: una foto con codice PHP e script in coda: o rifiutata o ricodificata senza codice', fc.errore || (contC !== '' && !/<\?php|<script/i.test(contC)), [fc.errore, fc.dati && (fc.dati.url || fc.dati.errore)]);
      const fs1 = await call(tutto, 'import_photo', { archivio: 'openverse', id: 'cc0a', alt: 'Prova sic46 svg', url: 'http://127.0.0.1:' + PF + '/file/svg.jpg' });
      const fs2 = await call(tutto, 'import_photo', { archivio: 'openverse', id: 'cc0a', alt: 'Prova sic46 html', url: 'http://127.0.0.1:' + PF + '/file/html.jpg' });
      const fs3 = await call(tutto, 'import_photo', { archivio: 'openverse', id: 'nc1', alt: 'Prova sic46 nc' });
      const fs4 = await call(tutto, 'import_photo', { archivio: 'openverse', id: '../../etc', alt: 'Prova sic46 id' });
      ok('2.32.0: import_photo di un SVG con script, di una pagina HTML, di una foto non commerciale o con un id con ../: rifiutati', fs1.errore && fs2.errore && fs3.errore && fs4.errore, [fs1.dati, fs2.dati, fs3.dati, fs4.dati].map(x => (x && x.errore || '').slice(0, 50)));
      // 11. Proposte con codice: escape nel pannello.
      const pc = await call(tutto, 'propose_change', { tipo: 'pagine', id: pub.id, campo: 'titolo_seo', valore: 'Sic46 <img src=x onerror=alert(46)>', motivo: 'Motivo <script>alert(46)</script><img src=x onerror=alert(46)> sic46' });
      const pc2 = await call(tutto, 'propose_change', { tipo: 'pagine', id: pub.id, campo: 'testo', widget_id: pub.testo_id, valore: '<p>Nuovo</p><script>alert(46)</script><img src=x onerror=alert(46)>', motivo: 'Testo con codice sic46' });
      const fin46 = []; admin.p.on('dialog', d => { fin46.push(d.message()); d.dismiss().catch(() => {}); });
      await admin.p.goto(ADMIN + '/addon-pagina.php?a=ai-collegate&p=proposte', { waitUntil: 'networkidle' });
      const htmlP = await admin.p.content();
      ok('2.32.0: proposte con codice nel valore e nel motivo: salvate senza tag pericolosi e mostrate come testo nel pannello (niente finestre)', !pc.errore && !pc2.errore && await admin.p.locator('img[onerror], #proposta-' + (pc.dati && pc.dati.proposta) + ' script').count() === 0 && !/<script>alert\(46\)/.test(htmlP) && fin46.length === 0 && !/<script|onerror/i.test(JSON.stringify(cli46('sql', 'SELECT dopo, motivo FROM {p}ai_proposte'))), [pc.dati, pc2.dati && pc2.dati.dopo]);
      // 12. Il registro non contiene token.
      const reg46 = JSON.stringify(cli46('sql', 'SELECT dettagli FROM {p}ai_registro'));
      ok('2.32.0: nel registro delle AI e nella tabella dei token nessun token in chiaro', !/pc[akrs]_[A-Za-z0-9_-]{20}/.test(reg46) && !JSON.stringify(cli46('sql', 'SELECT hash FROM {p}ai_token')).includes(tutto.slice(4, 30)));
      // 13. Limite di velocità per connessione (60 al minuto).
      const kLim = cli46('chiave', 'leggi', 'Sic46 limite').chiave;
      let primo429 = 0;
      for (let i = 1; i <= 64 && !primo429; i++) { const r = await h46('POST', MCP, { token: kLim, corpo: { jsonrpc: '2.0', id: i, method: 'ping' } }); if (r.stato === 429) primo429 = i; }
      ok('2.32.0: oltre 60 chiamate in un minuto con la stessa chiave: 429', primo429 === 61, primo429);
      cli46('sql', "DELETE FROM {p}sicurezza_tentativi WHERE chiave LIKE 'limite:aic:fall:%'"); // i token sbagliati delle prove sopra contano anche loro
      let fall429 = 0;
      for (let i = 1; i <= 32 && !fall429; i++) { const r = await h46('POST', MCP, { token: 'pck_' + b64u(crypto.randomBytes(32)), corpo: { jsonrpc: '2.0', id: i, method: 'ping' } }); if (r.stato === 429) fall429 = i; }
      ok('2.32.0: oltre 30 token sbagliati in dieci minuti dallo stesso indirizzo: 429', fall429 === 31, fall429);
    } finally {
      srvFoto.kill();
      cli46('sql', "DELETE FROM {p}sicurezza_tentativi WHERE chiave LIKE 'limite:aic:%'");
      cli46('pulisci'); cli46('config-foto', '');
      if (!eraAcceso) cli46('spegni');
    }
  } else console.log('--  AI collegate (2.32.0): saltata (serve CMS_DIR e l\'addon nella cartella)');

  // === 47. (2.33.0) AI collegate, navigazione e schede degli articoli: analyze_navigation, propose_menu, propose_navigation_fix,
  // review_article, list_articles_to_review, propose_article_changes e le azioni nuove della pagina Proposte (Approva il menu,
  // Approva tutte e Scarta tutte di una scheda). Etichette con HTML e script, indirizzi javascript:, data:, vbscript:, //evil,
  // http, con la barra rovescia o con spazi davanti; id di bozze, di contenuti inesistenti, di un menu che non c'è o scritti come
  // SQL; menu di quattro livelli o di 201 voci; etichette lunghissime; oltre il limite delle proposte in attesa (anche a metà
  // di una scheda: nessuna salvata); approvazione senza CSRF, da un'altra origine, dal redattore, con un codice di scheda
  // falsato; il token di una connessione revocata. Serve CMS_DIR e il kit Attività (la pagina Contatti).
  const DIR47 = process.env.CMS_DIR || '';
  if (DIR47 && fs.existsSync(path.join(DIR47, 'addon', 'ai-collegate', 'includes', 'navigazione.php')) && fs.existsSync(path.join(__dirname, '..', 'prove', 'ai-cli.php'))) {
    const { execFileSync } = require('child_process');
    const cli47 = (...a) => { try { return JSON.parse(execFileSync('php', [path.join(__dirname, '..', 'prove', 'ai-cli.php'), DIR47, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }).trim().split('\n').pop()); } catch (e) { return { errore_cli: String(e.message).slice(0, 200) }; } };
    const attivi47 = path.join(DIR47, 'moduli', 'addon-attivi.json');
    const eraAcceso47 = (() => { try { return JSON.parse(fs.readFileSync(attivi47, 'utf8')).includes('ai-collegate'); } catch (e) { return false; } })();
    cli47('accendi');
    const MCP47 = BASE + '/api/mcp';
    let id47 = 1;
    const call47 = async (token, nome, args) => {
      const r = await fetch(MCP47, { method: 'POST', headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' }, body: JSON.stringify({ jsonrpc: '2.0', id: id47++, method: 'tools/call', params: { name: nome, arguments: args || {} } }) });
      let j = null; try { j = await r.json(); } catch (e) {}
      const res = j && j.result; let dati = null; try { dati = JSON.parse(res.content[0].text); } catch (e) {}
      return { stato: r.status, errore: !res || !!res.isError, dati };
    };
    const conta47 = (dove = '') => { const x = cli47('sql', 'SELECT COUNT(*) n FROM {p}ai_proposte' + dove); return Array.isArray(x) ? +x[0].n : -1; };
    try {
      cli47('pulisci'); cli47('nav-ripristina');
      const prep = cli47('nav-prepara');
      const art = cli47('articolo-prova', 'ai-prova-art-sic47');
      const kk = cli47('chiave', 'leggi,bozze,proposte', 'Sic47');
      const K = kk.chiave, MID = prep.menu;
      // 1. Indirizzi cattivi nelle voci: tutti rifiutati, nessuna proposta.
      const p0 = conta47();
      const brutti = ['javascript:alert(47)', 'JaVaScRiPt:alert(47)', ' javascript:alert(47)', 'data:text/html,<script>alert(47)</script>', 'vbscript:msgbox(47)', '//evil.example/', '/\\evil.example', 'http://evil.example/', 'https://utente:pw@evil.example/', '/"><script>alert(47)</script>', '/../../admin/', 'https://evil.example/"onmouseover="alert(47)'];
      const passati = [];
      for (const u of brutti) { const r = await call47(K, 'propose_menu', { menu_id: MID, voci: [{ etichetta: 'Sic47', url: u }], motivo: 'Attacco 47 agli indirizzi.' }); if (!r.errore) passati.push(u); }
      ok('2.33.0: propose_menu con indirizzi javascript:, data:, vbscript:, //evil, barra rovescia, http, credenziali, virgolette o ../: tutti rifiutati, nessuna proposta', passati.length === 0 && conta47() === p0, passati);
      // 2. Id di contenuti sbagliati e menu che non c'è.
      const ids = [
        await call47(K, 'propose_menu', { menu_id: MID, voci: [{ etichetta: 'Sic47', tipo: 'pagina', id: prep.bozza }], motivo: 'Attacco 47: bozza.' }),
        await call47(K, 'propose_menu', { menu_id: MID, voci: [{ etichetta: 'Sic47', tipo: 'pagina', id: 99999999 }], motivo: 'Attacco 47: inesistente.' }),
        await call47(K, 'propose_menu', { menu_id: MID, voci: [{ etichetta: 'Sic47', tipo: 'pagina', rif: "x' OR 1=1 --" }], motivo: 'Attacco 47: rif SQL.' }),
        await call47(K, 'propose_menu', { menu_id: MID, voci: [{ etichetta: 'Sic47', tipo: 'categoria', rif: '../../etc' }], motivo: 'Attacco 47: categoria.' }),
        await call47(K, 'propose_menu', { menu_id: '1 OR 1=1', voci: [{ etichetta: 'Sic47', url: '/' }], motivo: 'Attacco 47: menu SQL.' }),
        await call47(K, 'propose_menu', { menu_id: -1, voci: [{ etichetta: 'Sic47', url: '/' }], motivo: 'Attacco 47: menu negativo.' }),
        await call47(K, 'propose_menu', { menu_id: art.id + 1000, voci: [{ etichetta: 'Sic47', url: '/' }], motivo: 'Attacco 47: id di un altro tipo.' }),
      ];
      ok('2.33.0: voci verso una bozza, un id inesistente, un rif o un menu_id con SQL, una categoria con ../, un menu che non c\'è: rifiutate', ids.every(x => x.errore) && conta47() === p0, ids.map(x => x.dati && x.dati.errore && x.dati.errore.slice(0, 60)));
      // 3. Troppo profondo, troppo grande, etichette lunghissime o solo HTML.
      const prof = await call47(K, 'propose_menu', { menu_id: MID, voci: [{ etichetta: 'A', url: '/', figli: [{ etichetta: 'B', url: '/', figli: [{ etichetta: 'C', url: '/', figli: [{ etichetta: 'D', url: '/' }] }] }] }], motivo: 'Attacco 47: profondo.' });
      const tante = await call47(K, 'propose_menu', { menu_id: MID, voci: Array.from({ length: 201 }, (_, i) => ({ etichetta: 'V' + i, url: '/' })), motivo: 'Attacco 47: enorme.' });
      const nidi = await call47(K, 'propose_menu', { menu_id: MID, voci: [{ etichetta: 'A', url: '/', figli: Array.from({ length: 250 }, (_, i) => ({ etichetta: 'F' + i, url: '/' })) }], motivo: 'Attacco 47: figli enormi.' });
      const soloHtml = await call47(K, 'propose_menu', { menu_id: MID, voci: [{ etichetta: '<svg onload=alert(47)></svg>', url: '/' }], motivo: 'Attacco 47: solo HTML.' });
      ok('2.33.0: menu di quattro livelli, di 201 voci, con 250 sottovoci o con un\'etichetta solo HTML: rifiutati', [prof, tante, nidi, soloHtml].every(x => x.errore) && conta47() === p0, [prof, tante, nidi, soloHtml].map(x => x.dati && x.dati.errore && x.dati.errore.slice(0, 60)));
      // 4. Etichette con codice: salvate come testo, mostrate come testo, lunghe tagliate a 100.
      const lungo = 'L'.repeat(5000);
      const pc = await call47(K, 'propose_menu', { menu_id: MID, motivo: 'Attacco 47: <script>alert(471)</script><img src=x onerror=alert(472)> motivo.', voci: [{ etichetta: 'Home<script>alert(473)</script>', url: '/' }, { etichetta: '<img src=x onerror=alert(474)>Contatti', url: '/contatti/' }, { etichetta: lungo, url: '/contatti/#modulo' }] });
      const salvata = pc.dati && pc.dati.proposta ? (cli47('sql', 'SELECT dopo, motivo FROM {p}ai_proposte WHERE id = ' + (+pc.dati.proposta))[0] || {}) : {};
      ok('2.33.0: etichette e motivo con script e eventi: salvati senza tag, etichetta lunga tagliata a 100', !pc.errore && !/<script|onerror|<img|<svg/i.test(JSON.stringify(salvata)) && (JSON.parse(salvata.dopo || '[]')[2] || {}).etichetta.length === 100, [pc.dati && pc.dati.errore, salvata.dopo && salvata.dopo.slice(0, 200)]);
      const fin47 = []; const onDialog = d => { fin47.push(d.message()); d.dismiss().catch(() => {}); };
      admin.p.on('dialog', onDialog);
      await admin.p.goto(ADMIN + '/addon-pagina.php?a=ai-collegate&p=proposte', { waitUntil: 'networkidle' });
      const box47 = '#proposta-' + (pc.dati ? pc.dati.proposta : 0);
      ok('2.33.0: nella pagina Proposte gli alberi del menu mostrano le etichette come testo (niente finestre, niente tag)', fin47.length === 0 && await admin.p.locator(box47 + ' img[onerror], ' + box47 + ' script').count() === 0 && /Home/.test(await admin.p.locator(box47 + ' .aic-dopo').innerText()), fin47);
      admin.p.off('dialog', onDialog);
      // 5. Approvazione senza CSRF, da un'altra origine, dal redattore; codice di scheda falsato.
      const URLP = ADMIN + '/addon-pagina.php?a=ai-collegate&p=proposte';
      const pid = pc.dati ? String(pc.dati.proposta) : '0';
      const a1 = await admin.ctx.request.post(URLP, { form: { id: pid, azione: 'approva' }, maxRedirects: 0 });
      const tk = await tokenDi(admin.p, URLP);
      const a2 = await admin.ctx.request.post(URLP, { form: { _csrf: tk, id: pid, azione: 'approva' }, headers: { Origin: 'https://evil.example' }, maxRedirects: 0 });
      let a3s = 'saltato';
      if (red.dentro) {
        const tr = await tokenDi(red.p, ADMIN + '/contenuti.php?tipo=articoli');
        const a3 = await red.ctx.request.post(URLP, { form: { _csrf: tr, id: pid, azione: 'approva' }, maxRedirects: 0 });
        const a3b = await red.ctx.request.post(URLP, { form: { _csrf: tr, gruppo: 'x', azione: 'scheda_approva' }, maxRedirects: 0 });
        a3s = [a3.status(), a3b.status()];
      }
      const statoPc = (cli47('sql', 'SELECT stato FROM {p}ai_proposte WHERE id = ' + (+pid))[0] || {}).stato;
      const menuIntatto = (cli47('menu', MID) || []).some(v => v.etichetta === 'Offerte');
      ok('2.33.0: Approva il menu senza CSRF, da un\'altra origine o dal redattore: rifiutato, la proposta resta da decidere e il menu non cambia', [400, 403].includes(a1.status()) && [400, 403].includes(a2.status()) && statoPc === 'attesa' && menuIntatto, [a1.status(), a2.status(), a3s, statoPc, menuIntatto]);
      const sch = await call47(K, 'propose_article_changes', { tipo: 'articoli', id: art.id, motivo: 'Attacco 47: scheda.', modifiche: [{ campo: 'titolo_seo', valore: 'Titolo sic47 nuovo per la scheda' }, { campo: 'in_breve', valore: 'In breve sic47.' }] });
      const tk2 = await tokenDi(admin.p, URLP);
      const falsi = ["' OR 1=1 --", sch.dati && sch.dati.gruppo ? sch.dati.gruppo + "' OR '1'='1" : 'x', '%', ''];
      for (const g of falsi) await admin.ctx.request.post(URLP, { form: { _csrf: tk2, gruppo: g, azione: 'scheda_approva' }, headers: { Origin: BASE.replace(/(https?:\/\/[^/]+).*/, '$1') }, maxRedirects: 0 });
      ok('2.33.0: Approva tutte con un codice di scheda falsato (SQL, %, vuoto): nessuna proposta approvata', !sch.errore && conta47(" WHERE stato = 'approvata'") === 0, [sch.dati && sch.dati.errore, conta47(" WHERE stato = 'approvata'")]);
      // 6. Oltre il limite delle proposte in attesa: anche a metà scheda non se ne salva nessuna.
      cli47('sql', `INSERT INTO {p}ai_proposte (connessione_id, client, tipo, contenuto_id, campo, prima, dopo, motivo, stato, creato) SELECT ${+kk.connessione}, 'Sic47', 'articoli', 0, 'titolo', '', '', 'riempi', 'attesa', NOW() FROM (SELECT 1 FROM {p}settings a, {p}settings b LIMIT 196) t`);
      const inAttesa = conta47(` WHERE connessione_id = ${+kk.connessione} AND stato = 'attesa'`);
      const oltre = await call47(K, 'propose_article_changes', { tipo: 'articoli', id: art.id, motivo: 'Attacco 47: oltre il limite.', modifiche: Array.from({ length: 5 }, (_, i) => ({ campo: ['titolo_seo', 'descrizione_seo', 'estratto', 'parola_chiave', 'titolo'][i], valore: 'Valore sic47 numero ' + i })) });
      const oltre2 = await call47(K, 'propose_article_changes', { tipo: 'articoli', id: art.id, motivo: 'Attacco 47: 21 modifiche.', modifiche: Array.from({ length: 21 }, (_, i) => ({ campo: 'titolo_seo', valore: 'T' + i })) });
      ok('2.33.0: oltre 200 proposte in attesa (a metà scheda) o 21 modifiche in una chiamata: rifiutate, nessuna salvata', inAttesa >= 196 && oltre.errore && /Nessuna proposta salvata/.test(oltre.dati.errore) && oltre2.errore && conta47(` WHERE connessione_id = ${+kk.connessione} AND stato = 'attesa'`) === inAttesa, [inAttesa, oltre.dati, oltre2.dati && oltre2.dati.errore]);
      cli47('sql', "DELETE FROM {p}ai_proposte WHERE motivo = 'riempi'");
      // 7. propose_navigation_fix cattive.
      const chi = (cli47('sql', "SELECT id FROM {p}pages WHERE slug = 'chi-siamo'")[0] || {}).id;
      const nf = [
        await call47(K, 'propose_navigation_fix', { tipo: 'pagine', id: chi, motivo: 'Attacco 47: bozza.', leggi_anche: { voci: [{ tipo: 'pagine', id: prep.bozza }] } }),
        await call47(K, 'propose_navigation_fix', { tipo: 'pagine', id: chi, motivo: 'Attacco 47: inesistente.', leggi_anche: { voci: [{ tipo: 'pagine', id: 99999999 }] } }),
        await call47(K, 'propose_navigation_fix', { tipo: 'pagine', id: chi, motivo: 'Attacco 47: sé stessa.', leggi_anche: { voci: [{ tipo: 'pagine', id: chi }] } }),
        await call47(K, 'propose_navigation_fix', { tipo: 'pagine', id: prep.orfana, motivo: 'Attacco 47: invito.', invito: { link: 'javascript:alert(47)' } }),
        await call47(K, 'propose_navigation_fix', { tipo: 'pagine', id: prep.orfana, motivo: 'Attacco 47: invito.', invito: { link: '//evil.example' } }),
        await call47(K, 'propose_navigation_fix', { tipo: 'utenti', id: 1, motivo: 'Attacco 47: tipo.', invito: {} }),
        await call47(K, 'propose_navigation_fix', { tipo: 'pagine', id: prep.orfana, motivo: 'Attacco 47: troppe.', leggi_anche: { voci: Array.from({ length: 7 }, () => ({ tipo: 'pagine', id: chi })) } }),
      ];
      ok('2.33.0: propose_navigation_fix verso una bozza, un id inesistente, sé stessa, con link javascript: o //evil, tipo inventato, 7 voci: rifiutate', nf.every(x => x.errore), nf.map(x => x.dati && x.dati.errore && x.dati.errore.slice(0, 50)));
      const nfOk = await call47(K, 'propose_navigation_fix', { tipo: 'pagine', id: prep.orfana, motivo: 'Attacco 47: titoli con codice.', invito: { titolo: '<script>alert(475)</script>Scrivici', testo: '<img src=x onerror=alert(476)>Ciao', pulsante: '"><b>Vai</b>' } });
      const dNf = nfOk.dati && nfOk.dati.proposta ? (cli47('sql', 'SELECT dopo FROM {p}ai_proposte WHERE id = ' + (+nfOk.dati.proposta))[0] || {}).dopo || '' : '';
      ok('2.33.0: i testi dei blocchi proposti (titolo, testo, pulsante) senza tag', !nfOk.errore && dNf !== '' && !/<script|onerror|<img|<b>/i.test(dNf), [nfOk.dati, dNf.slice(0, 200)]);
      // 8. Letture con id sbagliati, token revocato.
      const rv = [await call47(K, 'review_article', { tipo: 'articoli', id: 99999999 }), await call47(K, 'review_article', { tipo: 'utenti', id: 1 }), await call47(K, 'review_article', { tipo: 'pagine', id: '1 OR 1=1' })];
      ok('2.33.0: review_article con id inesistente, tipo inventato o id con SQL: errore', rv.every(x => x.errore), rv.map(x => x.dati));
      const kr = cli47('chiave', 'leggi,proposte', 'Sic47 revocata');
      cli47('sql', 'UPDATE {p}ai_connessioni SET revocato = NOW() WHERE id = ' + (+kr.connessione));
      const rr = [await call47(kr.chiave, 'analyze_navigation'), await call47(kr.chiave, 'propose_menu', { menu_id: MID, voci: [{ etichetta: 'Home', url: '/' }], motivo: 'Attacco 47: revocata.' })];
      ok('2.33.0: il token di una connessione revocata: 401 su analyze_navigation e propose_menu, nessuna proposta', rr.every(x => x.stato === 401) && conta47(` WHERE connessione_id = ${+kr.connessione}`) === 0, rr.map(x => x.stato));
      const kl = cli47('chiave', 'leggi', 'Sic47 lettura').chiave;
      const pl = [await call47(kl, 'propose_menu', { menu_id: MID, voci: [{ etichetta: 'Home', url: '/' }], motivo: 'Attacco 47: sola lettura.' }), await call47(kl, 'propose_article_changes', { tipo: 'articoli', id: art.id, motivo: 'Attacco 47.', modifiche: [{ campo: 'titolo_seo', valore: 'x sic47' }] }), await call47(kl, 'propose_navigation_fix', { tipo: 'pagine', id: chi, motivo: 'Attacco 47.', invito: {} })];
      ok('2.33.0: una chiave di sola lettura non propone menu, schede o blocchi (permesso "proposte")', pl.every(x => x.errore && /"proposte"/.test(x.dati.errore)), pl.map(x => x.dati));
    } finally {
      cli47('sql', "DELETE FROM {p}sicurezza_tentativi WHERE chiave LIKE 'limite:aic:%'");
      cli47('nav-ripristina'); cli47('pulisci');
      if (!eraAcceso47) cli47('spegni');
    }
  } else console.log('--  AI collegate, navigazione (2.33.0): saltata (serve CMS_DIR e l\'addon nella cartella)');

  // === 48. (2.34.0) AI collegate, stile del sito e idee per articoli: analyze_style, save_style_profile, get_style_profile,
  // suggest_topics, propose_article_ideas, list_article_ideas, create_draft e update_draft con idea_id, le pagine Stile del sito
  // e Idee per articoli. HTML e script nel profilo e nelle idee; profilo enorme, non testo, con caratteri a 4 byte; troppe idee
  // (in una chiamata e in attesa); idea_id inesistenti, in uno stato sbagliato, scritti come SQL o su una pagina; collegati
  // verso bozze o scritti come SQL; Ripristina, Salva, Approva e Scarta senza CSRF, da un'altra origine o dal redattore; permessi
  // del token insufficienti; token revocato; non più di dieci versioni del profilo. Serve CMS_DIR.
  const DIR48 = process.env.CMS_DIR || '';
  if (DIR48 && fs.existsSync(path.join(DIR48, 'addon', 'ai-collegate', 'includes', 'idee.php')) && fs.existsSync(path.join(__dirname, '..', 'prove', 'ai-cli.php'))) {
    const { execFileSync } = require('child_process');
    const cli48 = (...a) => { try { return JSON.parse(execFileSync('php', [path.join(__dirname, '..', 'prove', 'ai-cli.php'), DIR48, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }).trim().split('\n').pop()); } catch (e) { return { errore_cli: String(e.message).slice(0, 200) }; } };
    const attivi48 = path.join(DIR48, 'moduli', 'addon-attivi.json');
    const eraAcceso48 = (() => { try { return JSON.parse(fs.readFileSync(attivi48, 'utf8')).includes('ai-collegate'); } catch (e) { return false; } })();
    cli48('accendi');
    const MCP48 = BASE + '/api/mcp';
    let id48 = 1;
    const call48 = async (token, nome, args) => {
      const r = await fetch(MCP48, { method: 'POST', headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' }, body: JSON.stringify({ jsonrpc: '2.0', id: id48++, method: 'tools/call', params: { name: nome, arguments: args || {} } }) });
      let j = null; try { j = await r.json(); } catch (e) {}
      const res = j && j.result; let dati = null; try { dati = JSON.parse(res.content[0].text); } catch (e) {}
      return { stato: r.status, errore: !res || !!res.isError, dati };
    };
    const uno48 = (q) => { const x = cli48('sql', q); return Array.isArray(x) ? x[0] || {} : {}; };
    const n48 = (t, dove = '') => +(uno48('SELECT COUNT(*) n FROM {p}' + t + dove).n || 0);
    try {
      cli48('pulisci'); cli48('stile-pulisci');
      const sem = cli48('stile-semina');
      const kk = cli48('chiave', 'leggi,bozze,proposte', 'Sic48');
      const K = kk.chiave;
      // 1. Il profilo con HTML e script: salvato come testo semplice; nella pagina nessuna finestra e nessun tag.
      const cattivo = 'Tono: <script>alert(481)</script>diretto.\n<img src=x onerror=alert(482)>Persona: tu.\n&lt;script&gt;alert(483)&lt;/script&gt;\n"><svg onload=alert(484)>Regole: frasi brevi e chiare per tutti.';
      const sp = await call48(K, 'save_style_profile', { profilo: cattivo });
      const salvato = uno48('SELECT testo FROM {p}ai_stile ORDER BY id DESC LIMIT 1').testo || '';
      ok('2.34.0: save_style_profile con script, eventi, entità e svg: salvato senza tag e senza il contenuto degli script', !sp.errore && salvato !== '' && !/<|alert\(481\)|alert\(483\)|onerror|onload/i.test(salvato) && /Regole: frasi brevi/.test(salvato), [sp.dati && sp.dati.errore, salvato.slice(0, 200)]);
      const fin48 = []; const onDialog48 = d => { fin48.push(d.message()); d.dismiss().catch(() => {}); };
      admin.p.on('dialog', onDialog48);
      const URLS = ADMIN + '/addon-pagina.php?a=ai-collegate&p=stile', URLI = ADMIN + '/addon-pagina.php?a=ai-collegate&p=idee';
      await admin.p.goto(URLS, { waitUntil: 'networkidle' });
      ok('2.34.0: la pagina Stile del sito mostra il profilo come testo (niente finestre, niente tag fuori dal campo)', fin48.length === 0 && await admin.p.locator('#profilo img, #profilo svg[onload], #profilo script').count() === 0, fin48);
      // 2. Profilo enorme, non testo, con caratteri a 4 byte.
      const v0 = n48('ai_stile');
      const enorme = await call48(K, 'save_style_profile', { profilo: 'Regola lunghissima. '.repeat(5000) });
      const lista = await call48(K, 'save_style_profile', { profilo: ['Tono: diretto', 'Persona: tu'] });
      const ogg = await call48(K, 'save_style_profile', { profilo: { tono: 'diretto e chiaro per tutti quanti i lettori del sito' } });
      ok('2.34.0: profilo da 100.000 caratteri, come elenco o come oggetto: rifiutato, nessuna versione nuova', enorme.errore && lista.errore && ogg.errore && n48('ai_stile') === v0, [enorme.dati, lista.dati, ogg.dati]);
      const emoji = await call48(K, 'save_style_profile', { profilo: 'Tono: allegro \u{1F600}\u{1F331} con le emoji.\nPersona: tu.\nRegole: frasi brevi e chiare.' });
      const te = uno48('SELECT testo FROM {p}ai_stile ORDER BY id DESC LIMIT 1').testo || '';
      ok('2.34.0: profilo con caratteri a 4 byte: salvato senza (nessun errore del database)', !emoji.errore && /allegro/.test(te) && !/[\u{10000}-\u{10FFFF}]/u.test(te), [emoji.dati, te]);
      for (let i = 0; i < 13; i++) await call48(K, 'save_style_profile', { profilo: 'Versione numero ' + i + ' del profilo: tono diretto, persona tu, frasi brevi.' });
      ok('2.34.0: dopo 13 salvataggi in più restano al massimo 11 versioni (quella in uso e le dieci precedenti)', n48('ai_stile') === 11, n48('ai_stile'));
      // 3. Ripristina e Salva senza CSRF, da un'altra origine, dal redattore.
      const vecchia = uno48('SELECT id FROM {p}ai_stile ORDER BY id ASC LIMIT 1').id;
      const ultimaPrima = uno48('SELECT id, testo FROM {p}ai_stile ORDER BY id DESC LIMIT 1');
      const r1 = await admin.ctx.request.post(URLS, { form: { azione: 'ripristina', id: String(vecchia) }, maxRedirects: 0 });
      const tk = await tokenDi(admin.p, URLS);
      const r2 = await admin.ctx.request.post(URLS, { form: { _csrf: tk, azione: 'ripristina', id: String(vecchia) }, headers: { Origin: 'https://evil.example' }, maxRedirects: 0 });
      const r3 = await admin.ctx.request.post(URLS, { form: { azione: 'salva', testo: 'Tono: cambiato senza CSRF da un attacco esterno al sito.' }, maxRedirects: 0 });
      let rr = 'saltato';
      if (red.dentro) {
        const tr = await tokenDi(red.p, ADMIN + '/contenuti.php?tipo=articoli');
        const a = await red.ctx.request.post(URLS, { form: { _csrf: tr, azione: 'ripristina', id: String(vecchia) }, maxRedirects: 0 });
        const b = await red.ctx.request.post(URLS, { form: { _csrf: tr, azione: 'salva', testo: 'Tono: cambiato dal redattore che non dovrebbe poterlo fare.' }, maxRedirects: 0 });
        rr = [a.status(), b.status()];
      }
      const ultimaDopo = uno48('SELECT id, testo FROM {p}ai_stile ORDER BY id DESC LIMIT 1');
      ok('2.34.0: Ripristina e Salva il profilo senza CSRF, da un\'altra origine o dal redattore: rifiutati, il profilo non cambia', [400, 403].includes(r1.status()) && [400, 403].includes(r2.status()) && [400, 403].includes(r3.status()) && ultimaDopo.id === ultimaPrima.id && ultimaDopo.testo === ultimaPrima.testo, [r1.status(), r2.status(), r3.status(), rr]);
      // 4. Idee con HTML e script, troppe, oltre il limite in attesa, collegati sbagliati.
      const bozza = cli48('sql', "SELECT id FROM {p}articles WHERE status = 'draft' LIMIT 1");
      const iC = await call48(K, 'propose_article_ideas', { idee: [{ titolo: '<script>alert(485)</script>Titolo <b>idea</b> sic48', parola_chiave: '<img src=x onerror=alert(486)>chiave', a_chi_serve: '"><svg onload=alert(487)>lettori', scaletta: Array.from({ length: 50 }, (_, i) => '<i>punto</i> ' + i), collegati: [{ id: "1 OR 1=1" }, { id: -5 }, { tipo: 'utenti', id: 1 }, { id: sem.ids[0] }].concat(bozza[0] ? [{ id: +bozza[0].id }] : []), motivo: '<script>alert(488)</script>Motivo valido per la prova.' }] });
      const ri = iC.dati && iC.dati.idee ? uno48('SELECT * FROM {p}ai_idee WHERE id = ' + (+iC.dati.idee[0].idea_id)) : {};
      const coll = JSON.parse(ri.collegati || '[]');
      ok('2.34.0: idee con script, eventi e svg: salvate senza tag; scaletta tagliata a 15; collegati solo pubblicati (niente SQL, negativi, tipi inventati, bozze)', !iC.errore && !/<|alert\(48[5-8]\)|onerror|onload/i.test(JSON.stringify(ri)) && JSON.parse(ri.scaletta || '[]').length === 15 && coll.length === 1 && coll[0].id === sem.ids[0], [iC.dati, ri.titolo, coll]);
      await admin.p.goto(URLI + '&vista=attesa', { waitUntil: 'networkidle' });
      ok('2.34.0: la pagina Idee per articoli mostra i testi delle idee come testo (niente finestre, niente tag)', fin48.length === 0 && await admin.p.locator('article.aic-idea script, article.aic-idea img[onerror], article.aic-idea svg').count() === 0, fin48);
      admin.p.off('dialog', onDialog48);
      const i0 = n48('ai_idee');
      const t21 = await call48(K, 'propose_article_ideas', { idee: Array.from({ length: 21 }, (_, i) => ({ titolo: 'Idea sic48 numero ' + i, motivo: 'Troppe idee.' })) });
      const tNo = await call48(K, 'propose_article_ideas', { idee: 'non un elenco' });
      const tObj = await call48(K, 'propose_article_ideas', { idee: { a: { titolo: 'Idea oggetto', motivo: 'x motivo' } } });
      cli48('sql', `INSERT INTO {p}ai_idee (connessione_id, client, titolo, motivo, stato, creato) SELECT ${+kk.connessione}, 'Sic48', 'riempi', 'riempi', 'attesa', NOW() FROM (SELECT 1 FROM {p}settings a, {p}settings b LIMIT 98) t`);
      const pieno = n48('ai_idee', ` WHERE connessione_id = ${+kk.connessione} AND stato = 'attesa'`);
      const oltre = await call48(K, 'propose_article_ideas', { idee: Array.from({ length: 5 }, (_, i) => ({ titolo: 'Oltre il limite ' + i, motivo: 'Oltre il limite delle idee in attesa.' })) });
      ok('2.34.0: 21 idee, idee non in elenco, oltre 100 idee in attesa: rifiutate, nessuna salvata', t21.errore && tNo.errore && tObj.errore && pieno >= 99 && oltre.errore && oltre.stato === 200 && n48('ai_idee', " WHERE titolo LIKE 'Oltre il limite%'") === 0 && n48('ai_idee') === i0 + 98, [t21.dati, tNo.dati, oltre.dati, pieno]);
      cli48('sql', "DELETE FROM {p}ai_idee WHERE motivo = 'riempi'");
      // 5. idea_id sbagliati: nessuna bozza.
      const ideaAtt = iC.dati.idee[0].idea_id;
      const scart = await call48(K, 'propose_article_ideas', { idee: [{ titolo: 'Idea da scartare sic48', motivo: 'Per la prova degli stati.' }, { titolo: 'Idea approvata sic48', motivo: 'Per la prova delle pagine.' }] });
      const [idS, idOk] = scart.dati.idee.map(x => x.idea_id);
      cli48('sql', `UPDATE {p}ai_idee SET stato = 'scartata' WHERE id = ${idS}`); cli48('sql', `UPDATE {p}ai_idee SET stato = 'da_scrivere' WHERE id = ${idOk}`);
      const a0 = n48('articles');
      const prove = [];
      for (const v of [99999999, ideaAtt, idS, '1 OR 1=1', -1, 1.5, [idOk], { id: idOk }, '0x10']) prove.push(await call48(K, 'create_draft', { tipo: 'articoli', titolo: 'Bozza sic48 idea', idea_id: v, testo: '<p>x</p>' }));
      prove.push(await call48(K, 'create_draft', { tipo: 'pagine', titolo: 'Pagina sic48 idea', idea_id: idOk, testo: '<p>x</p>' }));
      ok('2.34.0: create_draft con idea_id inesistente, da decidere, scartato, come SQL, negativo, decimale, elenco, oggetto, esadecimale o su una pagina: rifiutata, nessuna bozza', prove.every(x => x.errore) && n48('articles') === a0, prove.map(x => x.dati && x.dati.errore && x.dati.errore.slice(0, 50)));
      const bz = cli48('sql', "SELECT id FROM {p}articles WHERE status = 'published' AND slug LIKE 'ai-prova-stile-%' LIMIT 1")[0];
      const upPub = await call48(K, 'update_draft', { tipo: 'articoli', id: +bz.id, idea_id: idOk });
      ok('2.34.0: update_draft con idea_id su un articolo pubblicato: rifiutata, l\'idea resta da scrivere', upPub.errore && uno48('SELECT stato FROM {p}ai_idee WHERE id = ' + idOk).stato === 'da_scrivere', upPub.dati);
      // 6. Approva e Scarta dal pannello senza CSRF, da un'altra origine, dal redattore, con id falsati.
      const q1 = await admin.ctx.request.post(URLI, { form: { azione: 'approva', id: String(ideaAtt) }, maxRedirects: 0 });
      const tk2 = await tokenDi(admin.p, URLI);
      const q2 = await admin.ctx.request.post(URLI, { form: { _csrf: tk2, azione: 'approva', id: String(ideaAtt) }, headers: { Origin: 'https://evil.example' }, maxRedirects: 0 });
      let qr = 'saltato';
      if (red.dentro) {
        const tr = await tokenDi(red.p, ADMIN + '/contenuti.php?tipo=articoli');
        qr = [(await red.ctx.request.post(URLI, { form: { _csrf: tr, azione: 'approva', id: String(ideaAtt) }, maxRedirects: 0 })).status(), (await red.ctx.request.post(URLI, { form: { _csrf: tr, azione: 'scarta', id: String(ideaAtt) }, maxRedirects: 0 })).status()];
      }
      const orig = BASE.replace(/(https?:\/\/[^/]+).*/, '$1');
      for (const v of ["1 OR 1=1", "0", "-1", ideaAtt + "' OR '1'='1"]) await admin.ctx.request.post(URLI, { form: { _csrf: tk2, azione: 'approva', id: v }, headers: { Origin: orig }, maxRedirects: 0 });
      const statoAtt = uno48('SELECT stato FROM {p}ai_idee WHERE id = ' + ideaAtt).stato;
      ok('2.34.0: Approva un\'idea senza CSRF, da un\'altra origine, dal redattore o con un id falsato: l\'idea resta da decidere', [400, 403].includes(q1.status()) && [400, 403].includes(q2.status()) && statoAtt === 'attesa' && n48('ai_idee', " WHERE stato = 'da_scrivere'") === 1, [q1.status(), q2.status(), qr, statoAtt]);
      // 7. Permessi insufficienti e token revocato.
      const kl = cli48('chiave', 'leggi', 'Sic48 lettura').chiave;
      const pl = [await call48(kl, 'save_style_profile', { profilo: 'Tono: tentativo con la sola lettura per cambiare il profilo.' }), await call48(kl, 'propose_article_ideas', { idee: [{ titolo: 'Idea sola lettura', motivo: 'senza permesso' }] }), await call48(kl, 'create_draft', { tipo: 'articoli', titolo: 'Bozza sola lettura', idea_id: idOk })];
      ok('2.34.0: una chiave di sola lettura non salva il profilo ("bozze"), non propone idee ("proposte"), non scrive bozze da idee', /"bozze"/.test(pl[0].dati.errore) && /"proposte"/.test(pl[1].dati.errore) && /"bozze"/.test(pl[2].dati.errore), pl.map(x => x.dati));
      const kb = cli48('chiave', 'bozze', 'Sic48 senza lettura').chiave;
      const pb = [await call48(kb, 'analyze_style'), await call48(kb, 'suggest_topics'), await call48(kb, 'list_article_ideas'), await call48(kb, 'get_style_profile')];
      ok('2.34.0: una chiave senza "leggi" non misura lo stile, non legge spunti, idee o profilo', pb.every(x => x.errore && /"leggi"/.test(x.dati.errore)), pb.map(x => x.dati));
      const kr = cli48('chiave', 'leggi,bozze,proposte', 'Sic48 revocata');
      cli48('sql', 'UPDATE {p}ai_connessioni SET revocato = NOW() WHERE id = ' + (+kr.connessione));
      const v1 = n48('ai_stile'), i1 = n48('ai_idee');
      const rv = [await call48(kr.chiave, 'analyze_style'), await call48(kr.chiave, 'save_style_profile', { profilo: 'Tono: tentativo con un token revocato per cambiare il profilo.' }), await call48(kr.chiave, 'propose_article_ideas', { idee: [{ titolo: 'Idea revocata', motivo: 'token revocato' }] })];
      ok('2.34.0: il token di una connessione revocata: 401 su analyze_style, save_style_profile e propose_article_ideas, niente salvato', rv.every(x => x.stato === 401) && n48('ai_stile') === v1 && n48('ai_idee') === i1, rv.map(x => x.stato));
    } finally {
      cli48('sql', "DELETE FROM {p}sicurezza_tentativi WHERE chiave LIKE 'limite:aic:%'");
      cli48('stile-pulisci'); cli48('pulisci');
      if (!eraAcceso48) cli48('spegni');
    }
  } else console.log('--  AI collegate, stile e idee (2.34.0): saltata (serve CMS_DIR e l\'addon nella cartella)');

  // === 49. (2.35.0) SEO e AEO dell'audit di plumecms.it: i dati per Google "Programma" dalla scheda (script, javascript:, data:,
  // prezzo non numerico, testi enormi), le pagine dei pacchetti con seo_schema e tldr cattivi, la voce "seo" del manifest fuori
  // dalle chiavi ammesse, il llms.txt che non deve mostrare pagine nascoste a Google, bozze, pagine nel cestino. Serve CMS_DIR.
  const DIR49 = process.env.CMS_DIR || '';
  if (DIR49 && fs.existsSync(path.join(__dirname, '..', 'prove', 'seo-plume-cli.php')) && fs.existsSync(path.join(DIR49, 'includes', 'seo-schema.php'))
      && fs.readFileSync(path.join(DIR49, 'includes', 'seo-schema.php'), 'utf8').includes("'programma'")) {
    const { execFileSync } = require('child_process');
    const os49 = require('os');
    const run49 = (file, a) => { try { return JSON.parse(execFileSync('php', [path.join(__dirname, '..', 'prove', file), DIR49, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }).trim().split('\n').pop()); } catch (e) { return { errore_cli: String(e.message).slice(0, 200) }; } };
    const cli49 = (...a) => run49('seo-plume-cli.php', a), scli49 = (...a) => run49('seo-strumenti-cli.php', a);
    const tmp49 = fs.mkdtempSync(path.join(os49.tmpdir(), 'sic49-'));
    const SLUGS49 = ['sic49-programma', 'sic49-import', 'sic49-nascosta', 'sic49-bozza', 'sic49-cestino'];
    const pulisci49 = () => { for (const x of SLUGS49) scli49('sql', `DELETE FROM {p}pages WHERE slug = '${x}'`); };
    pulisci49();
    try {
      const grafo49 = async (u) => { const r = await admin.ctx.request.get(BASE + u); const h = await r.text(); const m = h.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/); let g = null; try { g = m ? JSON.parse(m[1])['@graph'] : null; } catch (e) {} return { h, g }; };
      const pg = scli49('pagina', 'sic49-programma', 'Pagina programma', JSON.stringify([{ tipo: 'testo', impostazioni: { contenuto: '<p>Prova.</p>' } }]));
      const scheda = ADMIN + '/contenuto.php?tipo=pagine&id=' + pg.id;
      const salva = async (campi) => {
        await admin.p.goto(scheda, { waitUntil: 'networkidle' });
        await admin.p.evaluate((campi) => {
          const s = document.querySelector('#sc-schema-tipo'); s.value = 'programma'; s.dispatchEvent(new Event('change'));
          for (const [k, v] of Object.entries(campi)) { const el = document.querySelector('[name="schema[programma][' + k + ']"]'); if (el) { if (el.tagName === 'SELECT') { const o = document.createElement('option'); o.value = v; el.appendChild(o); } el.removeAttribute('maxlength'); el.value = v; } }
        }, campi);
        await Promise.all([admin.p.waitForNavigation().catch(() => {}), admin.p.locator('button.btn-primary[data-valore="pubblica"]').click()]);
        return await admin.p.locator('body').innerText();
      };
      // 1. un programma buono, poi i tentativi: devono lasciare i dati buoni
      await salva({ nome: 'Programma buono', prezzo: '0', licenza: 'https://www.gnu.org/licenses/gpl-3.0.html', download: '/scarica/x/' });
      const g0 = await grafo49('/sic49-programma/'); const base = g0.g;
      const buono = (base || []).find(x => x['@type'] === 'SoftwareApplication');
      const tentativi = [
        ['licenza javascript:', { licenza: 'javascript:alert(document.cookie)' }],
        ['download data:', { download: 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==' }],
        ['download //altro-sito', { download: '//cattivo.example/x' }],
        ['licenza con le virgolette', { licenza: 'https://x.example/"><script>alert(1)</script>' }],
        ['prezzo non numerico', { prezzo: '1e9; DROP TABLE x' }],
        ['categoria fuori elenco', { categoria: 'EvilApplication' }],
        ['versione con lo script', { versione: '</script><script>alert(1)</script>' }],
        ['valuta strana', { valuta: '<b>' }],
      ];
      const esiti = [];
      for (const [nome, campi] of tentativi) {
        const t = await salva(campi);
        const g = (await grafo49('/sic49-programma/')).g; const sw = (g || []).find(x => x['@type'] === 'SoftwareApplication');
        esiti.push([nome, t.includes('non sono stati salvati'), JSON.stringify(sw) === JSON.stringify(buono)]);
      }
      ok('2.35.0: Programma con licenza javascript:, download data: o //altro sito, virgolette, prezzo non numerico, categoria, versione o valuta cattive: respinto, restano i dati buoni', !!buono && esiti.every(e => e[1] && e[2]), esiti.filter(e => !(e[1] && e[2])));
      // 2. script e testi enormi: tolti e tagliati, mai uno <script> in più nella pagina
      await salva({ nome: '<script>alert(1)</script>' + 'N'.repeat(5000), sistema: '<img src=x onerror=alert(1)>' + 'S'.repeat(100000), requisiti: '</script><script>alert(2)</script>' + 'R'.repeat(5000), prezzo: '0' });
      const { h, g } = await grafo49('/sic49-programma/');
      const sw = (g || []).find(x => x['@type'] === 'SoftwareApplication');
      ok('2.35.0: Programma con script e testi enormi: tag tolti, testi tagliati (200 e 300), nessuno script in più nella pagina', sw && sw.name.length <= 200 && !/[<>]/.test(sw.name + sw.operatingSystem + sw.softwareRequirements) && sw.operatingSystem.length <= 200 && sw.softwareRequirements.length <= 300
        && !h.includes('<script>alert') && !h.includes('onerror=alert') && (h.match(/<script/g) || []).length === (g0.h.match(/<script/g) || []).length, sw && [sw.name.length, sw.operatingSystem.length, sw.softwareRequirements.length]);
      // 3. pacchetti: pagina con seo_schema cattivo (si ferma) e tldr e faq cattivi (puliti)
      const albero = { versione: 1, elementi: [{ id: 'si01', tipo: 'sezione', impostazioni: {}, figli: [{ id: 'si02', tipo: 'colonna', impostazioni: {}, figli: [{ id: 'si03', tipo: 'testo', impostazioni: { contenuto: '<p>x</p>' } }] }] }] };
      const scrivi = (nome, j) => { const f = path.join(tmp49, nome + '.json'); fs.writeFileSync(f, JSON.stringify(j)); return f; };
      const cattivi = [
        { tipo: 'programma', programma: { nome: 'X', prezzo: '0', licenza: 'javascript:alert(1)' } },
        { tipo: 'programma', programma: { nome: 'X', prezzo: '0', download: 'data:text/html,<script>alert(1)</script>' } },
        { tipo: 'programma', programma: { nome: '', prezzo: '0' } },
        { tipo: 'video', video: { nome: 'v', descrizione: 'd', miniatura: 'javascript:x', data: '2026-01-01', url: 'https://x.example/v.mp4' } },
        { tipo: '<script>' }, 'non un oggetto',
      ];
      const r3 = cattivi.map((sc, i) => cli49('importa', scrivi('c' + i, { slug: 'sic49-import', titolo: 'Import', struttura: albero, seo_schema: sc })));
      const nato = cli49('pagina', 'sic49-import');
      ok('2.35.0: pacchetto con seo_schema cattivo (javascript:, data:, senza nome, video con miniatura javascript:, tipo inventato, non oggetto): la pagina si ferma e non nasce', r3.every(r => r.errore && /non validi/.test(r.errore)) && !!nato.errore, r3.map(r => r.errore || r.esito));
      const r4 = cli49('importa', scrivi('t', { slug: 'sic49-import', titolo: 'Import', struttura: albero, tldr: '<script>alert(1)</script>' + 'T'.repeat(50000) + '\u{1F600}', keyword: '<b>k</b>' + 'K'.repeat(1000), faq: [{ domanda: '<script>alert(1)</script>D', risposta: '<img src=x onerror=alert(1)>R' }, 'nonoggetto', { domanda: ['a'], risposta: { x: 1 } }] }));
      const ri = cli49('pagina', 'sic49-import');
      const fq = JSON.parse(ri.faq || '[]');
      const pagina49 = await (await admin.ctx.request.get(BASE + '/sic49-import/')).text();
      ok('2.35.0: pacchetto con tldr, keyword e faq cattivi: senza tag, tagliati (2000, 255), niente caratteri a 4 byte, voci rotte scartate, niente script nella pagina', !r4.errore && ri.tldr && ri.tldr.length <= 2000 && !/[<>]/.test(ri.tldr) && !/[\u{10000}-\u{10FFFF}]/u.test(ri.tldr)
        && ri.keyword_target.length <= 255 && !/[<>]/.test(ri.keyword_target) && fq.length === 1 && !/[<>]/.test(fq[0].domanda + fq[0].risposta) && !pagina49.includes('<script>alert') && !pagina49.includes('onerror=alert'), [r4, ri.tldr && ri.tldr.length, fq]);
      // 4. la voce "seo" del manifest fuori dalle chiavi ammesse
      const zip49 = (nome, seo) => { const d = path.join(tmp49, nome); fs.mkdirSync(d); fs.writeFileSync(path.join(d, 'manifest.json'), JSON.stringify({ nome: 'sic49', seo })); const z = d + '.zip'; execFileSync('python3', ['-c', 'import sys,zipfile,os\nz=zipfile.ZipFile(sys.argv[2],"w")\nz.write(os.path.join(sys.argv[1],"manifest.json"),"manifest.json")\nz.close()', d, z]); return z; };
      const seoCattive = [{ analisi: { codice_head: '<script>alert(1)</script>' } }, { llms: { personale: 'x' } }, { robots_txt: { personale: 'Disallow: /' } }, { verifica: { google: 'x' } },
        { org: { logo: 'javascript:alert(1)' } }, { org: { logo: '//cattivo.example/x.png' } }, { meta: { og_immagine: 'data:image/svg+xml,<svg onload=alert(1)>' } }, { persona: { credenziale: 'x' } }, { org: { telefono: '1' } },
        { schema: { searchaction: '<script>' } }, { titolo: { sito: ['array'] } }, 'stringa', { org: { tipo: 'X"><script>' } }];
      const r5 = seoCattive.map((seo, i) => cli49('manifest', zip49('m' + i, seo)));
      ok('2.35.0: voce "seo" del manifest con codice delle statistiche, llms, robots, verifiche, logo javascript: o //, og:image data:, credenziali, telefono, interruttore non 0/1, testi non testo: il pacchetto è respinto', r5.every(r => !!r.errore), r5.map((r, i) => r.errore ? 'ok' : i));
      const r6 = cli49('manifest', zip49('mb', { org: { nome: '<script>alert(1)</script>Org', descrizione: 'D'.repeat(3000) } }));
      ok('2.35.0: voce "seo" buona ma con tag e testi lunghi: tag tolti, 500 caratteri al massimo', r6.seo && r6.seo.org.nome === 'alert(1)Org' && r6.seo.org.descrizione.length === 500, r6);
      // 5. llms.txt: niente noindex, bozze, cestino; testo semplice
      const tre = ['sic49-nascosta', 'sic49-bozza', 'sic49-cestino'].map(x => scli49('pagina', x, 'Pagina ' + x, JSON.stringify([{ tipo: 'testo', impostazioni: { contenuto: '<p>x</p>' } }])));
      scli49('sql', `UPDATE {p}pages SET robots = 'noindex' WHERE id = ${tre[0].id}`);
      scli49('sql', `UPDATE {p}pages SET status = 'draft' WHERE id = ${tre[1].id}`);
      scli49('sql', `UPDATE {p}pages SET cestino = NOW() WHERE id = ${tre[2].id}`);
      const rl = await admin.ctx.request.get(BASE + '/llms.txt');
      const tl = await rl.text();
      const rlA = await (await browser.newContext()).request.get(BASE + '/llms.txt?preview=1');
      const tlA = await rlA.text();
      ok('2.35.0: llms.txt non mostra le pagine nascoste a Google, le bozze, quelle nel cestino (nemmeno all\'amministratore o con ?preview=1); è testo semplice', rl.status() === 200 && /^text\/plain/.test(rl.headers()['content-type'] || '')
        && !/sic49-(nascosta|bozza|cestino)/.test(tl + tlA) && tl.includes('## Pagine'), [rl.headers()['content-type'], tl.match(/sic49-[a-z]+/g)]);
    } finally {
      pulisci49();
      fs.rmSync(tmp49, { recursive: true, force: true });
    }
  } else console.log('--  SEO e AEO 2.35.0 (Programma, pacchetti, llms.txt): saltata (serve CMS_DIR con la 2.35.0)');

  // === 50. Cambia database (2.37.0): solo l'amministratore, CSRF, campi controllati, limite delle prove, la password del database
  //          nuovo mai nella pagina né nei registri, il config (e le sue copie) mai dal web, il ritorno solo con conferma e password.
  {
    const fs = require('fs'), path = require('path');
    const U50 = ADMIN + '/cambia-db.php';
    const DIR50 = process.env.CMS_DIR || '';
    const esiste = (await admin.ctx.request.get(U50, { maxRedirects: 0 })).status() === 200;
    if (esiste) {
      const form = (o) => new URLSearchParams(o).toString();
      const H = { 'Content-Type': 'application/x-www-form-urlencoded' };
      // La risposta intera (post() ne tiene 4000 caratteri) e i rimandi come 302: con la sessione del contesto, Origin del sito.
      const post50 = async (chi, o) => { const rx = await chi.ctx.request.post(U50, { form: o, headers: { Origin: new URL(BASE).origin }, maxRedirects: 0 }); return { status: rx.status(), body: await rx.text() }; };
      const t50 = await tokenDi(admin.p, U50);
      const buono = { azione: 'prova', host: '127.0.0.1', porta: '9338', nome: 'cdb_attacco', utente: 'nessuno', password: 'x', prefisso: 'cms_' };
      // 1. senza CSRF
      let r = await post(admin.p, U50, form(buono), H);
      ok('2.37.0: Cambia database senza token CSRF: rifiutato (403)', r.status === 403, r.status);
      for (const az of ['avvia', 'annulla', 'torna', 'ricomincia', 'controlla']) {
        r = await post(admin.p, U50, form({ azione: az, password_admin: PASSWORD, conferma: '1' }), H);
        if (r.status !== 403) { ok('2.37.0: azione ' + az + ' senza token CSRF: rifiutata', false, r.status); break; }
      }
      ok('2.37.0: avvia, annulla, torna, ricomincia, controlla senza token CSRF: tutte rifiutate (403)', true);
      r = await post(admin.p, U50, form({ azione: 'giro' }), Object.assign({ Accept: 'application/json' }, H));
      ok('2.37.0: un giro della copia senza token: 403 in JSON', r.status === 403, r.status);
      // 2. dal redattore
      const rr = await red.ctx.request.get(U50, { maxRedirects: 0 });
      ok('2.37.0: il redattore non apre Cambia database (rimandato alla dashboard)', rr.status() === 302 && !/cambia-db/.test(rr.headers()['location'] || ''), [rr.status(), rr.headers()['location']]);
      const tr = await tokenDi(red.p, ADMIN + '/index.php');
      r = await post(red.p, U50, form(Object.assign({ _csrf: tr }, buono)), H);
      ok('2.37.0: il redattore non prova connessioni (403)', r.status === 403, r.status);
      r = await post(red.p, U50, form({ _csrf: tr, azione: 'torna', conferma: '1', password_admin: REDPW }), H);
      ok('2.37.0: il redattore non torna al database di prima (403)', r.status === 403, r.status);
      // 3. campi cattivi: niente connessione, errori sotto i campi, niente codice riflesso
      const cattivi = [
        ['host con schema', { host: 'http://127.0.0.1' }, /nome del server o il suo indirizzo IP/],
        ['host con percorso', { host: '127.0.0.1/admin' }, /nome del server o il suo indirizzo IP/],
        ['host con la porta dentro', { host: '127.0.0.1:3306' }, /nome del server o il suo indirizzo IP/],
        ['host con un punto e virgola (DSN)', { host: '127.0.0.1;dbname=altro' }, /nome del server o il suo indirizzo IP/],
        ['porta non numerica', { porta: '3306 OR 1=1' }, /La porta è un numero/],
        ['porta 0', { porta: '0' }, /La porta è un numero/],
        ['porta 70000', { porta: '70000' }, /La porta è un numero/],
        ['nome del database con apice inverso e SQL', { nome: 'x`; DROP DATABASE y; --' }, /Il nome del database può avere solo/],
        ['nome del database con ;', { nome: 'a;charset=latin1' }, /Il nome del database può avere solo/],
        ['nome del database con codice', { nome: '"><script>alert(1)</script>' }, /Il nome del database può avere solo/],
        ['prefisso con apice inverso', { prefisso: 'a`b' }, /Il prefisso può avere solo/],
        ['prefisso con SQL', { prefisso: "cms_'; DROP TABLE x; --" }, /Il prefisso può avere solo/],
        ['prefisso con i nomi di lavoro', { prefisso: 'rip0a0b0c_' }, /nomi di lavoro/],
        ['utente con a capo', { utente: "a\nb" }, /utente del database non è valido/],
      ];
      let sbagli = [];
      for (const [nome, o, re] of cattivi) {
        const tk = await tokenDi(admin.p, U50);
        r = await post50(admin, Object.assign({}, buono, { _csrf: tk }, o));
        if (r.status !== 200 || !re.test(r.body) || r.body.includes('<script>alert(1)</script>') || /Connessione riuscita/.test(r.body)) sbagli.push(nome);
      }
      ok('2.37.0: ' + cattivi.length + ' campi cattivi (host con schema, percorso, porta o DSN; porta non numerica o fuori misura; nome e prefisso con SQL o codice; utente con a capo): fermati sul campo, nessuna connessione, niente codice nella pagina', sbagli.length === 0, sbagli);
      // 4. la password del database nuovo: mai nella risposta, nei registri, nella pagina della sicurezza
      const segreto = 'SicSegreto50-' + Math.random().toString(36).slice(2);
      const tk4 = await tokenDi(admin.p, U50);
      r = await post50(admin, Object.assign({}, buono, { _csrf: tk4, porta: process.env.SIC_CDB_PORTA || '3308', utente: 'sic50_nessuno', password: segreto }));
      const regSic = await (await admin.ctx.request.get(ADMIN + '/sicurezza.php')).text();
      let nelLog = false;
      if (DIR50) for (const f of fs.readdirSync(path.join(DIR50, 'logs'))) { try { if (fs.readFileSync(path.join(DIR50, 'logs', f), 'utf8').includes(segreto)) nelLog = true; } catch (e) {} }
      let nelloStato = false;
      if (DIR50) { const d = path.join(DIR50, 'aggiornamenti', 'cambia-db'); if (fs.existsSync(d)) for (const f of fs.readdirSync(d)) { try { if (fs.readFileSync(path.join(d, f), 'utf8').includes(segreto)) nelloStato = true; } catch (e) {} } }
      ok('2.37.0: la password del database nuovo (sbagliata) non torna nella risposta, nel registro di sicurezza, nei log, nei file di stato; l\'errore è in parole semplici senza il messaggio del server', !r.body.includes(segreto) && !regSic.includes(segreto) && !nelLog && !nelloStato && /sbagliati|non risponde|non esiste/.test(r.body) && !/SQLSTATE|Access denied/.test(r.body), { risposta: r.body.includes(segreto), registro: regSic.includes(segreto), nelLog, nelloStato });
      // 5. troppi tentativi: dieci ogni dieci minuti per sessione
      {
        const altro = await entra(browser, UTENTE, PASSWORD);
        let fermo = false, giri = 0;
        for (; giri < 12 && !fermo; giri++) {
          const tk = await tokenDi(altro.p, U50);
          const rx = await post50(altro, Object.assign({}, buono, { _csrf: tk }));
          fermo = /troppe volte/.test(rx.body);
        }
        ok('2.37.0: "Prova la connessione" ripetuta: dopo dieci tentativi in dieci minuti si ferma (il pannello non diventa uno scanner di porte)', fermo && giri === 11, giri);
        await altro.ctx.close();
      }
      // 6. il config e le sue copie mai dal web; lo stato del cambio nemmeno
      const daFuori = await browser.newContext();
      const vietati = ['/includes/config.local.php', '/aggiornamenti/cambia-db/lavoro.json', '/aggiornamenti/cambia-db/ultimo.json'];
      let finto = '';
      if (DIR50) {
        const copie = fs.readdirSync(path.join(DIR50, 'includes')).filter(f => /^config\.local\.prima-/.test(f));
        if (!copie.length) { finto = path.join(DIR50, 'includes', 'config.local.prima-20260101-000000.php'); fs.writeFileSync(finto, "<?php http_response_code(404); exit; /* plumeCMS, Cambia database: copia del config.local.php di prima. Non si apre dal web. */ ?>\n<?php define('DB_PASS', 'sic50-finta');\n"); copie.push(path.basename(finto)); }
        for (const c of copie) vietati.push('/includes/' + c);
      }
      const aperti = [];
      for (const v of vietati) { const rv = await daFuori.request.get(BASE + PAN + v, { maxRedirects: 0 }); const tv = await rv.text(); if (![403, 404].includes(rv.status()) || /DB_PASS|define\(|"dest"/.test(tv)) aperti.push(v + ' ' + rv.status()); }
      if (finto) fs.unlinkSync(finto);
      ok('2.37.0: il config, le sue copie (config.local.prima-*.php) e lo stato del cambio non si aprono dal web (' + vietati.length + ' indirizzi)', aperti.length === 0, aperti);
      await daFuori.close();
      // 7. tornare indietro: mai senza conferma e password; avvia senza una prova riuscita
      const conf0 = DIR50 ? fs.readFileSync(path.join(DIR50, 'includes', 'config.local.php'), 'utf8') : '';
      let tk7 = await tokenDi(admin.p, U50);
      const t1 = await post50(admin, { _csrf: tk7, azione: 'torna', password_admin: PASSWORD });
      tk7 = await tokenDi(admin.p, U50);
      const t2 = await post50(admin, { _csrf: tk7, azione: 'torna', conferma: '1', password_admin: 'sbagliata' });
      tk7 = await tokenDi(admin.p, U50);
      const t3 = await post50(admin, { _csrf: tk7, azione: 'avvia', password_admin: PASSWORD });
      tk7 = await tokenDi(admin.p, U50);
      const t4 = await post50(admin, { _csrf: tk7, azione: 'ricomincia', password_admin: 'sbagliata' });
      const conf1 = DIR50 ? fs.readFileSync(path.join(DIR50, 'includes', 'config.local.php'), 'utf8') : '';
      ok('2.37.0: "Torna al database di prima" senza conferma o con la password sbagliata, "avvia" senza una prova riuscita, "ricomincia" senza password: niente cambia (config uguale)', [t1, t2, t3, t4].every(x => x.status === 302) && conf0 === conf1 && !fs.existsSync(path.join(DIR50 || '/nessuna', 'aggiornamenti', 'cambia-db', 'lavoro.json')), [t1.status, t2.status, t3.status, t4.status]);
      // 8. GET con i parametri: niente prova (solo POST)
      const rg = await admin.ctx.request.get(U50 + '?azione=prova&host=127.0.0.1&porta=3308&nome=x&utente=y&password=z');
      ok('2.37.0: i parametri in GET non fanno partire niente (solo POST con token)', rg.status() === 200 && !/Connessione riuscita|troppe volte|sbagliati/.test(await rg.text()));
    } else console.log('--  Cambia database: saltata (pagina assente, CMS prima della 2.37.0)');
  }

  // === 51. Pulizia (2.38.0): solo l'amministratore, CSRF e Origin, niente in GET, nomi di file e di tabelle mai dal browser (percorsi con
  //          .., fuori dalle cartelle ammesse, tabelle senza prefisso o inventate), numeri negativi o enormi nei "tieni le ultime N",
  //          password sbagliata: in ogni caso niente si toglie. Serve CMS_DIR per mettere snapshot e copie finte e guardare i file.
  {
    const fs = require('fs'), path = require('path');
    const U51 = ADMIN + '/pulizia.php';
    const DIR51 = process.env.CMS_DIR || '';
    const esiste = (await admin.ctx.request.get(U51, { maxRedirects: 0 })).status() === 200;
    if (esiste && DIR51) {
      const snapDir = path.join(DIR51, 'aggiornamenti', 'snapshot'), copieDir = path.join(DIR51, 'aggiornamenti', 'copie');
      fs.mkdirSync(snapDir, { recursive: true }); fs.mkdirSync(copieDir, { recursive: true });
      const snap = [1, 2, 3, 4].map(i => '2026010' + i + '-100000-sic51-' + i);
      for (const [i, id] of snap.entries()) { fs.mkdirSync(path.join(snapDir, id), { recursive: true }); fs.writeFileSync(path.join(snapDir, id, 'db.sql.gz'), 'x'.repeat(1000)); fs.writeFileSync(path.join(snapDir, id, 'meta.json'), JSON.stringify({ id, etichetta: 'sic51', creato: '2026-01-0' + (i + 1) + ' 10:00:00', db: [], files: [] })); }
      const copie = ['copia-20260101-080000-sic51.zip', 'copia-20260102-080000-sic51.zip'];
      for (const c of copie) fs.writeFileSync(path.join(copieDir, c), 'y'.repeat(500));
      const sentinella = path.join(DIR51, 'uploads', 'sic51-sentinella.png'); fs.writeFileSync(sentinella, 'z');
      const conta = () => snap.filter(id => fs.existsSync(path.join(snapDir, id))).length + copie.filter(c => fs.existsSync(path.join(copieDir, c))).length;
      const intatto = () => conta() === 6 && fs.existsSync(path.join(DIR51, 'includes', 'config.local.php')) && fs.existsSync(sentinella) && fs.existsSync(path.join(DIR51, 'index.php'));
      const ORIG = { Origin: new URL(BASE).origin };
      const post51 = async (chi, o) => { const rx = await chi.ctx.request.post(U51, { form: o, headers: ORIG, maxRedirects: 0 }); return { status: rx.status(), body: await rx.text() }; };
      const tutto = (extra) => Object.assign({ azione: 'esegui', 'voci[]': 'snapshot', 'p[snapshot][n]': '0', 'p[snapshot][zero]': '1', password_admin: PASSWORD }, extra);
      // 1. senza CSRF, con Origin esterno
      let r = await post(admin.p, U51, new URLSearchParams(tutto({})).toString(), { 'Content-Type': 'application/x-www-form-urlencoded' });
      ok('2.38.0: Pulizia senza token CSRF: rifiutata (403), niente tolto', r.status === 403 && intatto(), r.status);
      let tk = await tokenDi(admin.p, U51);
      const ev = await admin.ctx.request.post(U51, { form: tutto({ _csrf: tk }), headers: { Origin: 'https://evil.example' }, maxRedirects: 0 });
      ok('2.38.0: Pulizia con Origin esterno: rifiutata (403), niente tolto', ev.status() === 403 && intatto(), ev.status());
      // 2. dal redattore
      const rr = await red.ctx.request.get(U51, { maxRedirects: 0 });
      ok('2.38.0: il redattore non apre la Pulizia (rimandato alla dashboard)', rr.status() === 302 && !/pulizia/.test(rr.headers()['location'] || ''), [rr.status(), rr.headers()['location']]);
      const tr = await tokenDi(red.p, ADMIN + '/index.php');
      r = await post51(red, tutto({ _csrf: tr, password_admin: REDPW }));
      ok('2.38.0: il redattore non toglie niente (con il suo token e la sua password)', [302, 403].includes(r.status) && intatto(), r.status);
      // 3. GET con i parametri
      await admin.ctx.request.get(U51 + '?' + new URLSearchParams(tutto({ _csrf: tk })).toString());
      ok('2.38.0: in GET con tutti i parametri (anche il token e la password) niente si toglie', intatto());
      // 4. numeri cattivi nei "tieni le ultime N" e nei giorni
      const cattivi = ['-1', '-999', '99999999', '1e9', '3 OR 1=1', '2;DROP TABLE x', '0x10', ' ', '1.5'];
      let passati = [];
      for (const v of cattivi) { tk = await tokenDi(admin.p, U51); r = await post51(admin, { _csrf: tk, azione: 'esegui', 'voci[]': 'snapshot', 'p[snapshot][n]': v, password_admin: PASSWORD }); if (!intatto() || /Pulizia fatta/.test(r.body)) passati.push(v); }
      tk = await tokenDi(admin.p, U51);
      const arr = await admin.ctx.request.post(U51, { multipart: undefined, form: undefined, data: '_csrf=' + tk + '&azione=esegui&voci[]=snapshot&p[snapshot][n][]=1&password_admin=' + encodeURIComponent(PASSWORD), headers: Object.assign({ 'Content-Type': 'application/x-www-form-urlencoded' }, ORIG), maxRedirects: 0 });
      if (!intatto()) passati.push('lista');
      for (const v of ['-30', '99999999', 'tutti']) { tk = await tokenDi(admin.p, U51); r = await post51(admin, { _csrf: tk, azione: 'esegui', 'voci[]': 'registri', 'p[registri][giorni]': v, password_admin: PASSWORD }); if (/Pulizia fatta/.test(r.body)) passati.push('giorni ' + v); }
      tk = await tokenDi(admin.p, U51); r = await post51(admin, { _csrf: tk, azione: 'esegui', 'voci[]': 'registri', 'p[registri][giorni]': '5', password_admin: PASSWORD });
      if (/Pulizia fatta/.test(r.body) || !/da 30 a/.test(r.body)) passati.push('registri 5 giorni (sotto il minimo di 30)');
      ok('2.38.0: ' + (cattivi.length + 5) + ' numeri cattivi (negativi, enormi, con SQL, esadecimali, decimali, liste, registri sotto i 30 giorni): errore sotto il campo, niente tolto', passati.length === 0 && arr.status() === 200, passati);
      // 5. nomi di file e di tabelle dal browser: mai usati
      tk = await tokenDi(admin.p, U51);
      const form5 = '_csrf=' + tk + '&azione=esegui&password_admin=' + encodeURIComponent(PASSWORD) + '&' + [
        'voci[]=copie', 'voci[]=media', 'voci[]=pacchetti', 'voci[]=lavoro', 'voci[]=../../includes', 'voci[]=tabella', 'voci[]=settings',
        'p[copie][scelta][]=../../includes/config.local.php', 'p[copie][scelta][]=../../index.php', 'p[copie][scelta][]=' + encodeURIComponent('/etc/passwd'),
        'p[media][scelta][]=../../includes/config.local.php', 'p[media][scelta][]=sic51-sentinella.png', 'p[media][scelta][]=' + encodeURIComponent('/uploads/sic51-sentinella.png'),
        'p[pacchetti][scelta][]=../copie/copia-20260101-080000-sic51', 'p[lavoro][scelta][]=cms_settings', 'p[lavoro][scelta][]=users', 'p[tabella][nome]=cms_users',
      ].join('&');
      const x5 = await admin.ctx.request.post(U51, { data: form5, headers: Object.assign({ 'Content-Type': 'application/x-www-form-urlencoded' }, ORIG), maxRedirects: 0 });
      const t5 = await x5.text();
      const sitoOk = (await admin.ctx.request.get(BASE + '/')).status() === 200 && (await admin.ctx.request.get(ADMIN + '/index.php')).status() === 200;
      ok('2.38.0: percorsi con .., fuori dalle cartelle ammesse, tabelle senza prefisso o inventate, voci inventate: niente tolto (config, index.php, sentinella, copie, snapshot ci sono), il sito e il pannello rispondono', intatto() && sitoOk && !/Pulizia fatta: liberati [1-9]/.test(t5), [x5.status(), conta()]);
      // 6. la password sbagliata
      tk = await tokenDi(admin.p, U51);
      r = await post51(admin, tutto({ _csrf: tk, password_admin: 'sbagliata-sic51' }));
      ok('2.38.0: password sbagliata: "La password non è giusta", niente tolto', /La password non è giusta/.test(r.body) && intatto());
      // 7. senza la seconda conferma, "tieni 0" non toglie l'ultimo snapshot
      tk = await tokenDi(admin.p, U51);
      r = await post51(admin, { _csrf: tk, azione: 'esegui', 'voci[]': 'snapshot', 'p[snapshot][n]': '0', password_admin: PASSWORD });
      ok('2.38.0: "tieni 0 snapshot" senza la seconda conferma: niente tolto', /seconda conferma|spunta la conferma/.test(r.body) && intatto());
      // 8. il codice nelle scelte non torna nella pagina come HTML
      tk = await tokenDi(admin.p, U51);
      r = await post51(admin, { _csrf: tk, azione: 'riepilogo', 'voci[]': 'copie', 'p[copie][scelta][]': '"><script>alert(51)</script>' });
      ok('2.38.0: il codice in una scelta non torna nella pagina come HTML', !r.body.includes('<script>alert(51)</script>'));
      for (const id of snap) fs.rmSync(path.join(snapDir, id), { recursive: true, force: true });
      for (const c of copie) fs.rmSync(path.join(copieDir, c), { force: true });
      fs.rmSync(sentinella, { force: true });
    } else console.log('--  Pulizia: saltata (' + (esiste ? 'serve CMS_DIR' : 'pagina assente, CMS prima della 2.38.0') + ')');
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
