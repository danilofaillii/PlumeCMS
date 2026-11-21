// 2.33.0: robots.txt con una sola riga Sitemap e i vecchi indirizzi /articoli/<slug>/ verso l'articolo, anche con una regola
// "start" sulla stessa radice nella tabella dei redirect.
// uso: node prove/prova-robots-articoli.js <base> <cartella CMS>      (base senza barra finale, con la cartella se c'è: http://127.0.0.1:8970/test)
// Vuole un sito appena installato, con l'articolo di partenza /benvenuto/.
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const [B, DIR] = process.argv.slice(2);
if (!B || !DIR) { console.error('uso: node prove/prova-robots-articoli.js <base> <cartella CMS>'); process.exit(2); }
const CLI = __dirname + '/redirect-cli.php';
const cli = (...a) => execFileSync('php', [CLI, DIR, ...a], { encoding: 'utf8' });
let ok_ = 0, ko = 0;
const ok = (nome, cond, extra) => { if (cond) { ok_++; console.log('OK  ' + nome); } else { ko++; console.log('KO  ' + nome + (extra !== undefined ? '  ' + JSON.stringify(extra) : '')); } };
const cartella = new URL(B).pathname.replace(/\/$/, '');
async function dove(u) { const r = await fetch(B + u, { redirect: 'manual' }); return { s: r.status, l: r.headers.get('location') || '' }; }

(async () => {
  cli('togli', '/articoli/'); cli('togli-articolo', 'prova-bozza-vecchia');
  // 1. robots.txt
  const rb = await fetch(B + '/robots.txt'); const t = await rb.text();
  const righe = t.split('\n').filter(r => /^Sitemap:/i.test(r));
  ok('robots.txt 200', rb.status === 200);
  ok('una sola riga Sitemap', righe.length === 1, righe);
  ok('la riga porta a ' + B + '/sitemap.xml', righe[0] === 'Sitemap: ' + B + '/sitemap.xml', righe[0]);
  const sm = await fetch(righe[0] ? righe[0].slice(9) : B + '/x', { redirect: 'manual' });
  ok('la sitemap indicata risponde 200 con l\'XML', sm.status === 200 && (await sm.text()).includes('<urlset'));
  ok('nessuna cartella doppia', !cartella || !t.includes(cartella + cartella));

  // 2. /articoli/<slug>/ senza regole
  cli('bozza', 'benvenuto', 'prova-bozza-vecchia');
  let d = await dove('/articoli/benvenuto/');
  ok('/articoli/benvenuto/ -> 301 a /benvenuto/', d.s === 301 && d.l === B + '/benvenuto/', d);
  d = await dove('/articoli/benvenuto');
  ok('/articoli/benvenuto (senza barra) -> 301 a /benvenuto/', d.s === 301 && d.l === B + '/benvenuto/', d);
  d = await dove('/articoli/non-esiste-davvero/');
  ok('/articoli/<slug inesistente>/ non va su un articolo (404)', d.s === 404, d);
  d = await dove('/articoli/prova-bozza-vecchia/');
  ok('/articoli/<bozza>/ non svela la bozza (404)', d.s === 404, d);
  d = await dove('/articoli/benvenuto/altro/');
  ok('/articoli/benvenuto/altro/ non è un articolo (404)', d.s === 404, d);

  // 3. con una regola "start" su /articoli/ verso il blog
  cli('aggiungi', '/articoli/', '/blog/', 'start');
  d = await dove('/articoli/benvenuto/');
  ok('con la regola start: /articoli/benvenuto/ -> /benvenuto/ (non il blog)', d.s === 301 && d.l === B + '/benvenuto/', d);
  d = await dove('/articoli/non-esiste-davvero/');
  ok('con la regola start: slug inesistente -> il blog (la regola vale ancora)', d.s === 301 && d.l === B + '/blog/', d);
  d = await dove('/articoli/prova-bozza-vecchia/');
  ok('con la regola start: bozza -> il blog, non l\'articolo', d.s === 301 && d.l === B + '/blog/', d);

  // 4. nel browser: il vecchio indirizzo apre l'articolo
  const br = await chromium.launch(); const p = await br.newPage();
  const r = await p.goto(B + '/articoli/benvenuto/', { waitUntil: 'domcontentloaded' });
  ok('nel browser si apre l\'articolo', r.status() === 200 && p.url() === B + '/benvenuto/' && (await p.locator('h1').count()) >= 1, p.url());
  await br.close();

  cli('togli', '/articoli/'); cli('togli-articolo', 'prova-bozza-vecchia');
  console.log(`\n${ok_}/${ok_ + ko} controlli passati`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); try { cli('togli', '/articoli/'); cli('togli-articolo', 'prova-bozza-vecchia'); } catch (_) {} process.exit(1); });
