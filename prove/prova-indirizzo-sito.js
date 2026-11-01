// 2.4.0: Impostazioni > Indirizzo del sito, per il trasloco su un altro dominio. Il sito di prova risponde sia a 127.0.0.1 sia a
// localhost (stesso server): si passa da uno all'altro e si guarda che sitemap, canonical e firma seguano. Poi un indirizzo che non
// apre questo sito (rifiutato, niente cambiato), un indirizzo scritto male, "Salva lo stesso", e la frase in fondo al sito salvata
// con il vecchio indirizzo ufficiale che passa a plumecms.it.
// uso: node prove/prova-indirizzo-sito.js <base con 127.0.0.1> <cartella CMS> <utente> <password>
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello'); // UX 2026-11: conferme del pannello
const { execFileSync } = require('child_process');
const [B, DIR, U, P] = process.argv.slice(2);
const php = c => execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='127.0.0.1'; require '${DIR}/includes/config.php'; ${c}`], { encoding: 'utf8' });
let n = 0, ko = 0; const ok = (t, c, x = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (x !== '' ? ' ' + JSON.stringify(x).slice(0, 300) : '')); };
(async () => {
  const u = new URL(B); const cartella = u.pathname.replace(/\/$/, '');
  const vecchio = php("echo get_setting('sito_url', '');").trim();
  const nuovoDominio = `${u.protocol}//localhost:${u.port}`;
  const br = await chromium.launch(); const p = await br.newPage(); const err = []; p.on('pageerror', e => err.push(e.message)); pnConferme.on(p, d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', U); await p.fill('#password', P);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  const salva = async (url, forza = false) => {
    await p.goto(B + '/admin/impostazioni.php', { waitUntil: 'networkidle' });
    await p.evaluate(() => { document.getElementById('sito_url').type = 'text'; }); // la prova è il controllo del server, non quello del browser
    await p.fill('#sito_url', url); if (forza) await p.check('#sito_url_forza');
    await Promise.all([p.waitForNavigation(), p.click('#indirizzo-salva')]);
    return (await p.locator('.admin-content').innerText()).slice(0, 400);
  };
  await p.goto(B + '/admin/impostazioni.php', { waitUntil: 'networkidle' });
  ok('Impostazioni: il riquadro Indirizzo del sito con l\'indirizzo di adesso', (await p.locator('#sito_url').inputValue()) === vecchio.replace(cartella, '').replace(/\/$/, ''), [await p.locator('#sito_url').inputValue(), vecchio]);
  let t = await salva('miosito.it/pagina');
  ok('un indirizzo scritto male è rifiutato', /Scrivi solo l'indirizzo del dominio/.test(t) && php("echo get_setting('sito_url','');").trim() === vecchio, t);
  t = await salva('http://127.0.0.2:9');
  ok('un indirizzo che non apre questo sito è rifiutato, e non cambia niente', /non apre ancora questo sito/.test(t) && php("echo get_setting('sito_url','');").trim() === vecchio, t);
  ok('e il file della prova non resta in uploads', php("echo count(glob(cms_uploads_dir() . '/prova-indirizzo-*'));").trim() === '0');
  t = await salva(nuovoDominio);
  const salvato = php("echo get_setting('sito_url','');").trim();
  ok('il nuovo indirizzo che apre davvero questo sito si salva (con la cartella del sito)', /Indirizzo del sito cambiato/.test(t) && salvato === nuovoDominio + cartella, [t, salvato]);
  const sm = await (await p.request.get(B + '/sitemap.xml')).text();
  ok('la sitemap usa il nuovo indirizzo', sm.includes(`<loc>${nuovoDominio}${cartella}/`) && !sm.includes(`<loc>${vecchio}/`), sm.slice(0, 200));
  const home = await (await p.request.get(B + '/')).text();
  ok('il canonical della home usa il nuovo indirizzo', home.includes(`rel="canonical" href="${nuovoDominio}${cartella}/`), (home.match(/rel="canonical"[^>]+>/) || [''])[0]);
  t = await salva('http://127.0.0.2:9', true);
  ok('"Salva lo stesso" salva senza la prova', /Indirizzo del sito cambiato/.test(t) && php("echo get_setting('sito_url','');").trim() === 'http://127.0.0.2:9' + cartella);
  php(`save_setting('sito_url', ${JSON.stringify(vecchio)});`);
  // La frase in fondo al sito con il vecchio indirizzo ufficiale.
  php(`save_setting('firma_sito', json_encode(['mostra' => true, 'testo' => 'Un nuovo sito plumeCMS', 'url' => 'https://plumecms.danilofailli.it']));`);
  const piede = await (await p.request.get(B + '/')).text();
  ok('la frase salvata con plumecms.danilofailli.it ora porta a https://plumecms.it', /class="firma-sito"[\s\S]{0,300}href="https:\/\/plumecms\.it"/.test(piede) && !piede.includes('plumecms.danilofailli.it'), (piede.match(/firma-sito[\s\S]{0,200}/) || [''])[0]);
  php(`save_setting('firma_sito', '');`);
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close(); console.log(`\n${n - ko} su ${n} OK`); process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
