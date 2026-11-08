// L'addon AI collegate (2.32.0), come lo userebbero una persona e una AI. Un client MCP scritto qui in Node (senza
// librerie) fa tutto il collegamento OAuth 2.1: metadati della risorsa e del server, registrazione dinamica, PKCE S256,
// consenso nel pannello con il browser (Playwright: login, caselle dei permessi, Consenti), codice, token, rinnovo ruotato,
// riuso del rinnovo che chiude la connessione, revoca dall'endpoint e dal pannello. Poi tutti gli strumenti: lettura,
// audit, bozza creata e mai pubblicata (anche chiedendo "pubblicato"), bozza modificata, rifiuto su un pubblicato, proposte
// approvate (anche corrette e in gruppo) e scartate nel pannello e applicate sul sito, foto dall'archivio finto con autore
// e licenza, permessi negati, chiave personale creata nel pannello con REST e OpenAPI, il redattore che non può collegare,
// Rifiuta, e le pagine del pannello a 390 px senza scorrimento di lato.
//
// Serve: un sito installato con l'addon acceso (lo accende la prova: prove/ai-cli.php accendi), il server finto delle foto
//   php -S 127.0.0.1:<porta> prove/foto-finte.php   e nel config.local.php CMS_AI_FOTO_PROVA (lo scrive la prova).
// uso: node prove/prova-ai-collegate.js <base> <cartella CMS> <utente> <password> <porta delle foto finte> [cartella foto]
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const crypto = require('crypto');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, PORTA_FOTO, FOTO] = process.argv.slice(2);
if (!B || !DIR || !UTENTE || !PASSWORD || !PORTA_FOTO) { console.error('uso: node prove/prova-ai-collegate.js <base> <cartella CMS> <utente> <password> <porta foto finte> [cartella foto]'); process.exit(2); }
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (!c && extra !== '' ? '  ' + JSON.stringify(extra).slice(0, 400) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, 'ai-cli.php'), DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }).trim().split('\n').pop());
const MCP = B + '/api/mcp';
const CB = 'http://127.0.0.1:9/callback'; // l'indirizzo di ritorno del client: la prova lo intercetta nel browser
const b64u = (b) => b.toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

async function http(metodo, url, { corpo, tipo, token, intest = {} } = {}) {
  const h = { ...intest };
  if (token) h.Authorization = 'Bearer ' + token;
  let b;
  if (corpo !== undefined) { if (tipo === 'form') { h['Content-Type'] = 'application/x-www-form-urlencoded'; b = new URLSearchParams(corpo).toString(); } else { h['Content-Type'] = 'application/json'; b = typeof corpo === 'string' ? corpo : JSON.stringify(corpo); } }
  const r = await fetch(url, { method: metodo, headers: h, body: b, redirect: 'manual' });
  const testo = await r.text();
  let json = null; try { json = JSON.parse(testo); } catch (e) {}
  return { stato: r.status, json, testo, h: r.headers };
}
let idRpc = 1;
const rpc = (token, method, params, intest) => http('POST', MCP, { token, corpo: { jsonrpc: '2.0', id: idRpc++, method, ...(params !== undefined ? { params } : {}) }, intest: { Accept: 'application/json, text/event-stream', 'MCP-Protocol-Version': '2025-06-18', ...(intest || {}) } });
async function strumento(token, nome, args) {
  const r = await rpc(token, 'tools/call', { name: nome, arguments: args || {} });
  const res = r.json && r.json.result;
  let dati = null; try { dati = JSON.parse(res.content[0].text); } catch (e) {}
  return { http: r.stato, errore: !!(res && res.isError), dati, strutt: res && res.structuredContent, grezzo: r.json };
}
const entra = async (br, utente, password, url) => {
  const c = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await c.newPage();
  pnConferme.on(p, d => d.accept());
  await p.goto(url || B + '/admin/login.php');
  if (/login\.php/.test(p.url())) { await p.fill('#username', utente); await p.fill('#password', password); await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]); }
  return { c, p };
};

async function registra(nome) {
  return http('POST', MCP + '/register', { corpo: { client_name: nome, redirect_uris: [CB], token_endpoint_auth_method: 'none', grant_types: ['authorization_code', 'refresh_token'], response_types: ['code'] } });
}
function pkce() { const v = b64u(crypto.randomBytes(32)); return { v, c: b64u(crypto.createHash('sha256').update(v).digest()) }; }
/** Il percorso nel browser: authorize -> login -> consenso -> ritorno al client. Ritorna i parametri del ritorno. */
async function autorizza(br, clientId, sfida, { scope = 'leggi bozze proposte foto', togli = [], rifiuta = false, utente = UTENTE, password = PASSWORD, state = 'st-' + Date.now(), foto = '', segreto2fa = '' } = {}) {
  const c = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await c.newPage();
  let ritorno = null;
  await p.route('http://127.0.0.1:9/**', r => { ritorno = new URL(r.request().url()); r.fulfill({ status: 200, contentType: 'text/html', body: '<p>ritorno</p>' }); });
  const q = new URLSearchParams({ response_type: 'code', client_id: clientId, redirect_uri: CB, code_challenge: sfida, code_challenge_method: 'S256', scope, state, resource: MCP });
  await p.goto(MCP + '/authorize?' + q.toString());
  const alLogin = /login\.php\?dopo=ai-collegate&r=[a-f0-9]{32}/.test(p.url());
  await p.fill('#username', utente); await p.fill('#password', password);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  let passo2 = false;
  if (segreto2fa && await p.locator('#codice').count()) {
    passo2 = true;
    await p.fill('#codice', cli('totp', segreto2fa).codice);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  }
  const info = { alLogin, passo2, urlConsenso: p.url(), pagina: p, contesto: c, state };
  if (!/p=consenso/.test(p.url())) return info;
  info.testo = await p.locator('#aic-consenso').innerText();
  info.caselle = await p.locator('#aic-consenso input[name="permessi[]"]').evaluateAll(l => l.map(x => x.value + ':' + x.checked));
  if (foto) await p.screenshot({ path: path.join(FOTO, foto + '.png'), fullPage: true });
  for (const t of togli) await p.uncheck(`#aic-consenso input[value="${t}"]`);
  await p.click(rifiuta ? '#aic-consenso button[value=rifiuta]' : '#aic-consenso button[value=consenti]');
  await p.waitForFunction(() => !!document.getElementById('aic-fatto') || false, null, { timeout: 8000 }).catch(() => {});
  info.fatto = await p.locator('#aic-fatto').innerText().catch(() => '');
  for (let i = 0; i < 40 && !ritorno; i++) await p.waitForTimeout(100);
  info.ritorno = ritorno;
  return info;
}
async function token(corpo) { return http('POST', MCP + '/token', { corpo, tipo: 'form' }); }

(async () => {
  cli('accendi'); cli('pulisci'); cli('tentativi'); cli('redattore', 'elimina');
  cli('config-foto', 'http://127.0.0.1:' + PORTA_FOTO);
  const pub = cli('pagina-pubblicata', 'ai-prova-pubblicata');
  const br = await chromium.launch();

  // === 1. Senza token, metadati, OPTIONS e GET
  const r0 = await http('POST', MCP, { corpo: { jsonrpc: '2.0', id: 1, method: 'initialize', params: { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'prova', version: '1' } } } });
  const www = r0.h.get('www-authenticate') || '';
  ok('senza token: 401 con WWW-Authenticate Bearer resource_metadata', r0.stato === 401 && /^Bearer resource_metadata="[^"]+\/\.well-known\/oauth-protected-resource"/.test(www), www);
  const urlPrm = (www.match(/resource_metadata="([^"]+)"/) || [])[1];
  const prm = await http('GET', urlPrm);
  ok('metadati della risorsa (RFC 9728): resource, authorization_servers, scopes, bearer header', prm.stato === 200 && prm.json.resource === MCP && prm.json.authorization_servers[0] === B && prm.json.bearer_methods_supported.join() === 'header' && prm.json.scopes_supported.includes('bozze'), prm.json);
  const prm2 = await http('GET', B + '/.well-known/oauth-protected-resource/api/mcp');
  ok('metadati della risorsa anche con il percorso dell\'endpoint aggiunto', prm2.stato === 200 && prm2.json.resource === MCP);
  const asm = await http('GET', prm.json.authorization_servers[0] + '/.well-known/oauth-authorization-server');
  const oidc = await http('GET', B + '/.well-known/openid-configuration');
  const A = asm.json || {};
  ok('metadati del server (RFC 8414): issuer, endpoint, code, S256, none e client_secret_post', asm.stato === 200 && A.issuer === B && A.authorization_endpoint === MCP + '/authorize' && A.token_endpoint === MCP + '/token' && A.registration_endpoint === MCP + '/register' && A.revocation_endpoint === MCP + '/revoke'
    && A.response_types_supported.join() === 'code' && A.code_challenge_methods_supported.join() === 'S256' && A.grant_types_supported.join() === 'authorization_code,refresh_token' && A.token_endpoint_auth_methods_supported.join() === 'none,client_secret_post', A);
  ok('gli stessi metadati come openid-configuration', oidc.stato === 200 && oidc.json.issuer === B && oidc.json.token_endpoint === A.token_endpoint);
  ok('GET sul server MCP: 405', (await http('GET', MCP)).stato === 405);
  const nonSito = await http('GET', B + '/.well-known/qualcosa-altro');
  ok('un altro .well-known non lo prende l\'addon (404 del sito)', nonSito.stato === 404);

  // === 2. Registrazione dinamica
  const reg = await registra('Claude di prova');
  const CID = reg.json && reg.json.client_id;
  ok('registrazione dinamica (RFC 7591): 201 con client_id, nome e redirect_uris', reg.stato === 201 && /^aic_[a-f0-9]{32}$/.test(CID || '') && reg.json.client_name === 'Claude di prova' && reg.json.redirect_uris[0] === CB && !reg.json.client_secret, reg.json);
  const regCattiva = await http('POST', MCP + '/register', { corpo: { client_name: 'x', redirect_uris: ['javascript:alert(1)'] } });
  const regHttp = await http('POST', MCP + '/register', { corpo: { client_name: 'x', redirect_uris: ['http://evil.example/cb'] } });
  ok('registrazione con redirect javascript: o http esterno rifiutata (400 invalid_redirect_uri)', regCattiva.stato === 400 && regHttp.stato === 400 && regHttp.json.error === 'invalid_redirect_uri');

  // === 3. Consenso nel browser (login e caselle), un permesso tolto
  const k1 = pkce();
  const a1 = await autorizza(br, CID, k1.c, { togli: ['foto'], foto: FOTO ? 'ai-consenso' : '' });
  ok('authorize porta al login del pannello con la richiesta (dopo=ai-collegate)', a1.alLogin, a1.urlConsenso);
  ok('dopo il login, la pagina del consenso con il nome dell\'AI e l\'host di ritorno', /p=consenso/.test(a1.urlConsenso) && /Claude di prova/.test(a1.testo) && /127\.0\.0\.1/.test(a1.testo), a1.testo);
  ok('le caselle sono i permessi chiesti, spuntati', JSON.stringify(a1.caselle) === JSON.stringify(['leggi:true', 'bozze:true', 'proposte:true', 'foto:true']), a1.caselle);
  ok('Consenti: pagina "Permesso dato" e ritorno al client con code, state e iss', /Permesso dato/.test(a1.fatto) && a1.ritorno && a1.ritorno.searchParams.get('code') && a1.ritorno.searchParams.get('state') === a1.state && a1.ritorno.searchParams.get('iss') === B, a1.ritorno && a1.ritorno.href);
  await a1.contesto.close();
  const code1 = a1.ritorno.searchParams.get('code');
  const tSbagliato = await token({ grant_type: 'authorization_code', code: code1, redirect_uri: CB, client_id: CID, code_verifier: pkce().v });
  ok('PKCE sbagliato: invalid_grant', tSbagliato.stato === 400 && tSbagliato.json.error === 'invalid_grant');
  const tSenza = await token({ grant_type: 'authorization_code', code: code1, redirect_uri: CB, client_id: CID });
  ok('PKCE assente: invalid_grant', tSenza.stato === 400 && tSenza.json.error === 'invalid_grant');
  const tRedir = await token({ grant_type: 'authorization_code', code: code1, redirect_uri: 'http://127.0.0.1:9/altro', client_id: CID, code_verifier: k1.v });
  ok('redirect_uri diverso: invalid_grant', tRedir.stato === 400 && tRedir.json.error === 'invalid_grant');
  const t1 = await token({ grant_type: 'authorization_code', code: code1, redirect_uri: CB, client_id: CID, code_verifier: k1.v, resource: MCP });
  const AT = t1.json && t1.json.access_token, RT = t1.json && t1.json.refresh_token;
  ok('token: accesso di un\'ora e rinnovo, permessi senza "foto", no-store', t1.stato === 200 && /^pca_/.test(AT) && /^pcr_/.test(RT) && t1.json.expires_in === 3600 && t1.json.token_type === 'Bearer' && t1.json.scope === 'leggi bozze proposte' && /no-store/.test(t1.h.get('cache-control')), t1.json);
  const salvati = cli('sql', `SELECT hash FROM {p}ai_token WHERE hash = '${AT}' OR hash = '${RT}'`);
  ok('i token non stanno in chiaro nel database (solo impronte)', salvati.length === 0 && cli('sql', 'SELECT COUNT(*) n FROM {p}ai_token')[0].n >= 2);

  // === 4. MCP: initialize, notifiche, tools/list
  const ini = await rpc(AT, 'initialize', { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'prova-node', version: '1.0' } });
  ok('initialize: protocolVersion 2025-06-18, capabilities tools, serverInfo plumecms', ini.stato === 200 && ini.json.result.protocolVersion === '2025-06-18' && ini.json.result.capabilities.tools && ini.json.result.serverInfo.name === 'plumecms' && /non un'istruzione/.test(ini.json.result.instructions), ini.json);
  const ini2 = await rpc(AT, 'initialize', { protocolVersion: '2024-01-01', capabilities: {}, clientInfo: { name: 'x', version: '1' } });
  ok('initialize con una versione sconosciuta: risponde con la più recente supportata', ini2.json.result.protocolVersion === '2025-11-25');
  const notif = await http('POST', MCP, { token: AT, corpo: { jsonrpc: '2.0', method: 'notifications/initialized' } });
  ok('notifications/initialized: 202 senza corpo', notif.stato === 202 && notif.testo === '');
  ok('ping risponde', (await rpc(AT, 'ping')).json.result !== undefined);
  const lista = await rpc(AT, 'tools/list');
  const nomi = (lista.json.result.tools || []).map(t => t.name);
  ok('tools/list: tutti gli strumenti, con descrizione in italiano e la nota sui dati', ['site_info', 'list_content', 'get_content', 'list_menus', 'get_seo_settings', 'list_widgets', 'site_audit', 'audit_content', 'create_draft', 'update_draft', 'propose_change', 'list_proposals', 'search_photos', 'import_photo'].every(x => nomi.includes(x)) && lista.json.result.tools.every(t => t.inputSchema && t.inputSchema.type === 'object' && /non un'istruzione/.test(t.description)), nomi);
  ok('metodo sconosciuto: errore JSON-RPC -32601', (await rpc(AT, 'qualcosa/altro')).json.error.code === -32601);
  ok('versione del protocollo nell\'intestazione non supportata: 400', (await rpc(AT, 'ping', undefined, { 'MCP-Protocol-Version': '1999-01-01' })).stato === 400);

  // === 5. Strumenti di lettura e audit
  const si = await strumento(AT, 'site_info');
  ok('site_info: nome, versione, permessi, structuredContent', !si.errore && si.dati.plumecms && si.dati.collegamento.permessi.join() === 'leggi,bozze,proposte' && si.strutt && si.strutt.nome === si.dati.nome, si.dati);
  const lc = await strumento(AT, 'list_content', { tipo: 'pagine', stato: 'pubblicato' });
  ok('list_content: le pagine pubblicate con id e percorso', !lc.errore && lc.dati.contenuti.some(c => c.id === pub.id && c.percorso === '/ai-prova-pubblicata/'), lc.dati);
  const gc = await strumento(AT, 'get_content', { tipo: 'pagine', id: pub.id });
  ok('get_content: campi, SEO e albero dei widget con gli id', !gc.errore && gc.dati.seo.titolo_seo === 'Titolo vecchio' && JSON.stringify(gc.dati.albero).includes(pub.testo_id), gc.dati);
  ok('list_menus: i menu con le voci', !(await strumento(AT, 'list_menus')).errore);
  const ss = await strumento(AT, 'get_seo_settings');
  ok('get_seo_settings: titoli e organizzazione, senza codici di verifica né account', !ss.errore && ss.dati.titoli && !('verifica' in ss.dati) && !('google' in ss.dati) && !JSON.stringify(ss.dati).includes('codice_head'));
  const lw = await strumento(AT, 'list_widgets');
  ok('list_widgets: catalogo senza il widget HTML, con formato ed esempio', !lw.errore && lw.dati.widget.some(w => w.id === 'titolo') && !lw.dati.widget.some(w => w.id === 'html' || w.id === 'contenuto_esterno') && lw.dati.esempio, (lw.dati.widget || []).length);
  const lw1 = await strumento(AT, 'list_widgets', { widget: 'testo' });
  ok('list_widgets di un widget: i campi con tipi', !lw1.errore && lw1.dati.campi.some(c => c.nome === 'contenuto' && c.tipo === 'ricco'));
  const au = await strumento(AT, 'site_audit', { limite: 500 });
  ok('site_audit: problemi ordinati per gravità, con valore e suggerimento', !au.errore && au.dati.problemi.length > 0 && au.dati.problemi.every((x, i, l) => i === 0 || ['alta', 'media', 'bassa'].indexOf(l[i - 1].gravita) <= ['alta', 'media', 'bassa'].indexOf(x.gravita)) && au.dati.problemi.every(x => x.suggerimento), au.dati.totali);
  const ac = await strumento(AT, 'audit_content', { tipo: 'pagine', id: pub.id });
  ok('audit_content: punteggio, voci SEO, titolo e descrizione per Google', !ac.errore && typeof ac.dati.punteggio === 'number' && ac.dati.voci.length > 5 && ac.dati.titolo_per_google && ac.dati.descrizione_per_google, ac.dati);

  // === 6. Bozze: create_draft (anche chiedendo "pubblicato"), update_draft, rifiuto sul pubblicato
  const cd = await strumento(AT, 'create_draft', { tipo: 'pagine', titolo: 'Servizi di giardinaggio', slug: 'ai-prova-giardino', stato: 'pubblicato', pubblica: true,
    seo: { titolo_seo: 'Giardinaggio a domicilio', descrizione_seo: 'Cura del verde, potature e prati: preventivo gratuito in giornata.', in_breve: 'Curiamo giardini e terrazzi.', domande_frequenti: [{ domanda: 'Fate preventivi?', risposta: 'Sì, gratuiti.' }] },
    albero: { elementi: [{ tipo: 'titolo', impostazioni: { testo: 'Giardinaggio', livello: 'h1' } }, { tipo: 'testo', impostazioni: { contenuto: '<p>Il verde <b>curato</b>.</p><script>alert(1)</script>' } }] } });
  const idBozza = cd.dati && cd.dati.id;
  ok('create_draft: bozza creata con id e link all\'editor, avviso sullo stato', !cd.errore && cd.dati.stato === 'bozza' && /editor\.php\?tipo=pagine&id=\d+/.test(cd.dati.link.editor) && cd.dati.avvisi.some(a => /solo bozze/.test(a)), cd.dati);
  const riga = cli('sql', `SELECT status, struttura, struttura_bozza, seo_title FROM {p}pages WHERE id = ${idBozza}`)[0];
  ok('nel database: status draft, albero solo in bozza, niente script', riga.status === 'draft' && !riga.struttura && riga.struttura_bozza.includes('Giardinaggio') && !riga.struttura_bozza.includes('<script'), riga);
  ok('sul sito la bozza non si vede (404)', (await http('GET', B + '/ai-prova-giardino/')).stato === 404);
  const cdErr = await strumento(AT, 'create_draft', { tipo: 'pagine', titolo: 'X', albero: { elementi: [{ tipo: 'html', impostazioni: { codice: '<script>alert(1)</script>' } }, { tipo: 'inesistente' }] } });
  ok('create_draft con widget HTML e widget sconosciuto: errore chiaro con i nomi validi', cdErr.errore && /html/.test(cdErr.dati.errore) && /sconosciuto/.test(cdErr.dati.errore) && /Widget validi: .*titolo/.test(cdErr.dati.errore), cdErr.dati);
  const ud = await strumento(AT, 'update_draft', { tipo: 'pagine', id: idBozza, titolo: 'Giardinaggio e potature', seo: { descrizione_seo: 'Nuova descrizione della pagina del giardinaggio, con preventivo.' } });
  ok('update_draft su una bozza: campi cambiati, resta bozza', !ud.errore && ud.dati.stato === 'bozza' && cli('sql', `SELECT title FROM {p}pages WHERE id = ${idBozza}`)[0].title === 'Giardinaggio e potature', ud.dati);
  const udPub = await strumento(AT, 'update_draft', { tipo: 'pagine', id: pub.id, titolo: 'Ti cambio' });
  ok('update_draft su una pagina pubblicata: rifiutato, dice di usare propose_change', udPub.errore && /propose_change/.test(udPub.dati.errore) && cli('sql', `SELECT title FROM {p}pages WHERE id = ${pub.id}`)[0].title === 'Pagina pubblicata di prova', udPub.dati);

  // === 7. Foto: senza il permesso, poi con una chiave personale che ce l'ha
  const sfNo = await strumento(AT, 'search_photos', { query: 'vaso' });
  ok('search_photos senza il permesso "foto": errore che dice quale permesso manca', sfNo.errore && /"foto"/.test(sfNo.dati.errore), sfNo.dati);
  const kf = cli('chiave', 'leggi,bozze,proposte,foto', 'Chiave foto di prova').chiave;
  const sf = await strumento(kf, 'search_photos', { query: 'vaso di fiori', quante: 10 });
  ok('search_photos: solo licenze libere (la CC BY-NC esclusa), con autore e licenza', !sf.errore && sf.dati.foto.length === 3 && !sf.dati.foto.some(f => f.id === 'nc1') && sf.dati.foto.every(f => f.autore && f.licenza && f.archivio === 'openverse'), sf.dati);
  const imp = await strumento(kf, 'import_photo', { archivio: 'openverse', id: 'by1', alt: 'Un vaso di fiori sul davanzale', per: { tipo: 'pagine', id: idBozza } });
  ok('import_photo: nei Media con alt, autore e licenza CC BY e attribuzione', !imp.errore && /^\/uploads\/pagine\/\d{4}\/\d\d\/.+\.jpg$/.test(imp.dati.url) && imp.dati.licenza === 'CC BY 4.0' && imp.dati.attribuzione_obbligatoria === true && /Autrice by1/.test(imp.dati.attribuzione), imp.dati);
  ok('la foto è l\'immagine in evidenza della bozza e la licenza sta nel registro delle foto', cli('sql', `SELECT immagine_url FROM {p}pages WHERE id = ${idBozza}`)[0].immagine_url === imp.dati.url && cli('sql', `SELECT licenza, autore FROM {p}ai_foto WHERE url = '${imp.dati.url}'`)[0].autore === 'Autrice by1');
  ok('l\'alt della foto è nella libreria dei Media', cli('sql', `SELECT alt FROM {p}media_alt WHERE percorso LIKE '%${path.basename(imp.dati.url)}'`)[0].alt === 'Un vaso di fiori sul davanzale');
  const impNc = await strumento(kf, 'import_photo', { archivio: 'openverse', id: 'nc1', alt: 'Non commerciale' });
  ok('import_photo di una foto non commerciale: rifiutato', impNc.errore && /licenza/.test(impNc.dati.errore));
  const impPub = await strumento(kf, 'import_photo', { archivio: 'openverse', id: 'cc0a', alt: 'Vaso per la pagina pubblicata', per: { tipo: 'pagine', id: pub.id } });
  ok('import_photo per una pagina pubblicata: diventa una proposta, la pagina non cambia', !impPub.errore && impPub.dati.proposta > 0 && !cli('sql', `SELECT immagine_url FROM {p}pages WHERE id = ${pub.id}`)[0].immagine_url, impPub.dati);

  // === 8. Proposte
  const pr1 = await strumento(AT, 'propose_change', { tipo: 'pagine', id: pub.id, campo: 'testo', widget_id: pub.testo_id, valore: '<p>Testo nuovo scritto dall\'AI.</p>', motivo: 'Il testo di prima era troppo corto.' });
  const pr2 = await strumento(AT, 'propose_change', { tipo: 'pagine', id: pub.id, campo: 'titolo_seo', valore: 'Titolo nuovo per Google', motivo: 'Più chiaro per chi cerca.' });
  const pr3 = await strumento(AT, 'propose_change', { tipo: 'pagine', id: pub.id, campo: 'descrizione_seo', valore: 'Descrizione proposta dall\'AI.', motivo: 'La descrizione di prima non diceva niente.' });
  const pr4 = await strumento(AT, 'propose_change', { tipo: 'media', url: imp.dati.url, campo: 'alt', valore: 'Vaso di gerani rossi sul davanzale', motivo: 'Alt più preciso.' });
  ok('propose_change: quattro proposte (testo di un widget, titolo per Google, descrizione, alt della libreria)', [pr1, pr2, pr3, pr4].every(x => !x.errore && x.dati.stato === 'attesa'), [pr1, pr2, pr3, pr4].map(x => x.dati));
  ok('la pagina pubblicata non cambia finché nessuno approva', cli('sql', `SELECT seo_title FROM {p}pages WHERE id = ${pub.id}`)[0].seo_title === 'Titolo vecchio');
  const prNo = await strumento(AT, 'propose_change', { tipo: 'pagine', id: pub.id, campo: 'testo', valore: 'x', motivo: 'senza widget' });
  ok('propose_change sul testo di una pagina dell\'editor senza widget_id: errore chiaro', prNo.errore && /widget_id/.test(prNo.dati.errore));
  const admin = await entra(br, UTENTE, PASSWORD);
  const p = admin.p;
  const errJs = []; p.on('pageerror', e => errJs.push(e.message.slice(0, 160)));
  await p.goto(B + '/admin/index.php');
  const voceMenu = await p.locator('.admin-sidebar a', { hasText: 'AI collegate' }).first().innerText();
  ok('menu del pannello: "AI collegate" con il numero delle proposte da decidere', /AI collegate \(5\)/.test(voceMenu), voceMenu);
  await p.goto(B + '/admin/addon-pagina.php?a=ai-collegate&p=proposte', { waitUntil: 'networkidle' });
  ok('Proposte: cinque da decidere, con prima e dopo affiancati e il motivo', await p.locator('article.aic-proposta').count() === 5 && /Testo vecchio della pagina/.test(await p.locator(`#proposta-${pr1.dati.proposta} .aic-prima`).innerText()) && /troppo corto/.test(await p.locator(`#proposta-${pr1.dati.proposta} .aic-motivo`).innerText()));
  if (FOTO) await p.screenshot({ path: path.join(FOTO, 'ai-proposte.png'), fullPage: true });
  await p.click(`#proposta-${pr1.dati.proposta} button[value=approva]`); await p.waitForLoadState('networkidle');
  const sito1 = await http('GET', B + '/ai-prova-pubblicata/');
  ok('Approva (testo di un widget): la pagina del sito ha il testo nuovo e c\'è una versione', /Testo nuovo scritto dall/.test(sito1.testo) && cli('sql', `SELECT COUNT(*) n FROM {p}editor_versioni WHERE oggetto_id = ${pub.id}`)[0].n >= 2);
  await p.click(`#proposta-${pr2.dati.proposta} button[value=scarta]`); await p.waitForLoadState('networkidle');
  ok('Scarta: il titolo per Google resta quello di prima', cli('sql', `SELECT seo_title FROM {p}pages WHERE id = ${pub.id}`)[0].seo_title === 'Titolo vecchio' && cli('sql', `SELECT stato FROM {p}ai_proposte WHERE id = ${pr2.dati.proposta}`)[0].stato === 'scartata');
  await p.click(`#proposta-${pr3.dati.proposta} details.aic-modifica summary`);
  await p.fill(`#aic-v-${pr3.dati.proposta}`, 'Descrizione corretta a mano <b>dall\'amministratore</b>.');
  await p.click(`#proposta-${pr3.dati.proposta} details.aic-modifica button[type=submit]`); await p.waitForLoadState('networkidle');
  ok('Modifica e approva: si salva il valore corretto, ripulito', cli('sql', `SELECT seo_description FROM {p}pages WHERE id = ${pub.id}`)[0].seo_description === 'Descrizione corretta a mano dall\'amministratore.');
  await p.check(`input.aic-scelta[value="${pr4.dati.proposta}"]`); await p.check(`input.aic-scelta[value="${impPub.dati.proposta}"]`);
  await p.click('#aic-gruppo button[type=submit]'); await p.waitForLoadState('networkidle');
  ok('Approva le selezionate: alt della libreria e immagine in evidenza applicati', cli('sql', `SELECT alt FROM {p}media_alt WHERE percorso LIKE '%${path.basename(imp.dati.url)}'`)[0].alt === 'Vaso di gerani rossi sul davanzale' && /cc0a/.test(cli('sql', `SELECT immagine_url FROM {p}pages WHERE id = ${pub.id}`)[0].immagine_url));
  const lp = await strumento(AT, 'list_proposals', { stato: 'tutte' });
  ok('list_proposals: l\'AI vede approvate e scartate', !lp.errore && lp.dati.proposte.some(x => x.stato === 'approvata') && lp.dati.proposte.some(x => x.stato === 'scartata'));
  ok('nessuna proposta da decidere: il menu torna senza numero', !/\(\d+\)/.test(await p.locator('.admin-sidebar a', { hasText: 'AI collegate' }).first().innerText()));

  // === 9. Rinnovo ruotato, riuso del rinnovo, revoca
  const t2 = await token({ grant_type: 'refresh_token', refresh_token: RT, client_id: CID });
  ok('rinnovo: token nuovi, rinnovo diverso da quello di prima', t2.stato === 200 && t2.json.refresh_token !== RT && /^pca_/.test(t2.json.access_token), t2.json);
  ok('il token di accesso nuovo funziona', !(await strumento(t2.json.access_token, 'site_info')).errore);
  const t3 = await token({ grant_type: 'refresh_token', refresh_token: RT, client_id: CID });
  ok('rinnovo usato due volte: rifiutato e la connessione si chiude (token di accesso nuovo 401)', t3.stato === 400 && (await rpc(t2.json.access_token, 'ping')).stato === 401, t3.json);
  // Una seconda autorizzazione per la revoca, il codice riusato e la revoca dal pannello.
  const k2 = pkce();
  const a2 = await autorizza(br, CID, k2.c);
  await a2.contesto.close();
  const t4 = await token({ grant_type: 'authorization_code', code: a2.ritorno.searchParams.get('code'), redirect_uri: CB, client_id: CID, code_verifier: k2.v });
  ok('seconda autorizzazione: token con i quattro permessi', t4.stato === 200 && t4.json.scope === 'leggi bozze proposte foto');
  const t4b = await token({ grant_type: 'authorization_code', code: a2.ritorno.searchParams.get('code'), redirect_uri: CB, client_id: CID, code_verifier: k2.v });
  ok('codice usato due volte: rifiutato e la connessione nata dal primo uso si chiude', t4b.stato === 400 && (await rpc(t4.json.access_token, 'ping')).stato === 401);
  const k3 = pkce();
  const a3 = await autorizza(br, CID, k3.c); await a3.contesto.close();
  const t5 = await token({ grant_type: 'authorization_code', code: a3.ritorno.searchParams.get('code'), redirect_uri: CB, client_id: CID, code_verifier: k3.v });
  const rv = await http('POST', MCP + '/revoke', { corpo: { token: t5.json.access_token, client_id: CID }, tipo: 'form' });
  ok('revoca del token di accesso dall\'endpoint (RFC 7009): 200 e poi 401', rv.stato === 200 && (await rpc(t5.json.access_token, 'ping')).stato === 401);
  const t6 = await token({ grant_type: 'refresh_token', refresh_token: t5.json.refresh_token, client_id: CID });
  ok('dopo la revoca del solo accesso il rinnovo funziona ancora', t6.stato === 200);
  await p.goto(B + '/admin/addon-pagina.php?a=ai-collegate&p=collegate', { waitUntil: 'networkidle' });
  const righe = await p.locator('#collegate tbody tr').count();
  ok('Collegamenti: l\'AI collegata compare con permessi e ultima attività', righe === 1 && /Claude di prova/.test(await p.locator('#collegate').innerText()) && /leggi, bozze, proposte, foto/.test(await p.locator('#collegate').innerText()), righe);
  if (FOTO) await p.screenshot({ path: path.join(FOTO, 'ai-collegate.png'), fullPage: true });
  await p.click('#collegate tbody tr button.btn-danger'); await p.waitForLoadState('networkidle');
  ok('Revoca dal pannello: la connessione non entra più (401) e sparisce dall\'elenco', (await rpc(t6.json.access_token, 'ping')).stato === 401 && /Ancora nessuna AI collegata/.test(await p.locator('#collegate').innerText()));

  // === 10. Rifiuta e redattore
  const k4 = pkce();
  const a4 = await autorizza(br, CID, k4.c, { rifiuta: true }); await a4.contesto.close();
  ok('Rifiuta: ritorno al client con error=access_denied e niente codice', a4.ritorno && a4.ritorno.searchParams.get('error') === 'access_denied' && !a4.ritorno.searchParams.get('code'));
  cli('redattore', 'crea');
  const a5 = await autorizza(br, CID, pkce().c, { utente: 'ai_prova_red', password: 'Red-AI-Prova-2026' });
  ok('il redattore non arriva al consenso (pagina riservata all\'amministratore)', !/p=consenso/.test(a5.urlConsenso) && !a5.ritorno, a5.urlConsenso);
  await a5.contesto.close();
  cli('redattore', 'elimina');
  // Un amministratore con il secondo fattore: dopo la password chiede il codice e poi torna al consenso.
  const seg = cli('admin2fa', 'crea').segreto;
  const k5 = pkce();
  const a6 = await autorizza(br, CID, k5.c, { utente: 'ai_prova_2fa', password: 'Adm-AI-Prova-2026', segreto2fa: seg });
  await a6.contesto.close();
  const t7 = a6.ritorno ? await token({ grant_type: 'authorization_code', code: a6.ritorno.searchParams.get('code'), redirect_uri: CB, client_id: CID, code_verifier: k5.v }) : { stato: 0 };
  ok('amministratore con il secondo fattore: password, codice, consenso e token', a6.passo2 && /p=consenso/.test(a6.urlConsenso) && t7.stato === 200, [a6.passo2, a6.urlConsenso, t7.stato]);
  if (t7.json) await http('POST', MCP + '/revoke', { corpo: { token: t7.json.refresh_token, client_id: CID }, tipo: 'form' });
  cli('admin2fa', 'elimina');

  // === 11. Chiave personale dal pannello, REST e OpenAPI
  await p.fill('#aic-nome-chiave', 'GPT del negozio');
  await p.uncheck('#chiavi input[value=foto]');
  await p.click('.aic-nuova-chiave button[type=submit]'); await p.waitForLoadState('networkidle');
  const chiave = await p.inputValue('#aic-chiave-nuova');
  ok('chiave personale creata nel pannello e mostrata una volta', /^pck_[A-Za-z0-9_-]{40,}$/.test(chiave));
  await p.reload(); ok('ricaricando la pagina la chiave non si vede più', await p.locator('#aic-chiave-nuova').count() === 0 && /GPT del negozio/.test(await p.locator('#chiavi').innerText()));
  const oa = await http('GET', MCP + '/openapi');
  ok('OpenAPI 3.1 con gli strumenti come endpoint REST e la sicurezza Bearer', oa.stato === 200 && oa.json.openapi === '3.1.0' && oa.json.paths['/rest/site_info'] && oa.json.paths['/rest/create_draft'].post.requestBody && oa.json.components.securitySchemes.chiave.scheme === 'bearer', Object.keys((oa.json || {}).paths || {}));
  const rs = await http('POST', MCP + '/rest/site_info', { token: chiave, corpo: {} });
  ok('REST con la chiave: site_info', rs.stato === 200 && rs.json.collegamento.nome === 'GPT del negozio' && rs.json.collegamento.permessi.join() === 'leggi,bozze,proposte', rs.json);
  const rsNo = await http('POST', MCP + '/rest/search_photos', { token: chiave, corpo: { query: 'vaso' } });
  ok('REST senza il permesso: 403 con insufficient_scope', rsNo.stato === 403 && /insufficient_scope/.test(rsNo.h.get('www-authenticate') || ''));
  ok('REST senza chiave: 401', (await http('POST', MCP + '/rest/site_info', { corpo: {} })).stato === 401);
  const mcpK = await rpc(chiave, 'tools/call', { name: 'list_content', arguments: {} });
  ok('la stessa chiave vale sul server MCP', mcpK.stato === 200 && !mcpK.json.result.isError);
  await p.click('#chiavi tbody tr button.btn-danger'); await p.waitForLoadState('networkidle');
  ok('chiave revocata dal pannello: 401', (await http('POST', MCP + '/rest/site_info', { token: chiave, corpo: {} })).stato === 401);

  // === 12. Registro e impostazioni
  await p.goto(B + '/admin/addon-pagina.php?a=ai-collegate&p=registro', { waitUntil: 'networkidle' });
  const reg2 = await p.locator('#aic-registro').innerText();
  ok('Registro: le azioni con strumento ed esito (anche negate)', /create_draft/.test(reg2) && /negata/.test(reg2) && /Autrice by1/.test(await p.locator('#foto').innerText()));
  await p.selectOption('#aic-f-esito', 'negato'); await Promise.all([p.waitForNavigation(), p.click('.aic-filtro button')]);
  ok('Registro filtrato per esito', (await p.locator('#aic-registro tbody tr').count()) > 0 && !/riuscita/.test(await p.locator('#aic-registro tbody').innerText()));
  await p.goto(B + '/admin/addon-pagina.php?a=ai-collegate&p=impostazioni', { waitUntil: 'networkidle' });
  await p.fill('#aic-pexels', 'pexels-chiave-di-prova-123'); await p.fill('#aic-unsplash', 'unsplash-chiave-di-prova-123');
  await p.click('#archivi button[type=submit]'); await p.waitForLoadState('networkidle');
  ok('Impostazioni: chiavi di Pexels e Unsplash salvate e mai rimostrate', /Chiave salvata/.test(await p.locator('#archivi').innerText()) && await p.inputValue('#aic-pexels') === '' && !(await p.content()).includes('pexels-chiave-di-prova-123'));
  const sf3 = await strumento(kf, 'search_photos', { query: 'fiori', archivio: 'tutti' });
  ok('con le chiavi, la ricerca usa anche Pexels e Unsplash (con le loro licenze)', !sf3.errore && sf3.dati.foto.some(f => f.archivio === 'pexels' && f.licenza === 'Licenza Pexels') && sf3.dati.foto.some(f => f.archivio === 'unsplash'), sf3.dati.archivi);
  const imp3 = await strumento(kf, 'import_photo', { archivio: 'unsplash', id: 'aaa1', alt: 'Foto da Unsplash di prova' });
  ok('import_photo da Unsplash', !imp3.errore && imp3.dati.licenza === 'Licenza Unsplash', imp3.dati);

  // === 13. Telefono: le pagine dell'addon senza scorrimento di lato
  await p.setViewportSize({ width: 390, height: 800 });
  for (const pg of ['collegate', 'proposte', 'registro', 'impostazioni']) {
    await p.goto(B + '/admin/addon-pagina.php?a=ai-collegate&p=' + pg + (pg === 'proposte' ? '&stato=tutte' : ''), { waitUntil: 'networkidle' });
    const larg = await p.evaluate(() => [document.documentElement.scrollWidth, window.innerWidth]);
    ok('a 390 px la pagina ' + pg + ' non scorre di lato', larg[0] <= larg[1] + 1, larg);
  }
  ok('nessun errore JavaScript nel pannello', errJs.length === 0, errJs);
  await br.close();
  cli('pulisci'); cli('tentativi');
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
