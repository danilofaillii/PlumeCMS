// 2.40.0: le proposte P1-P4 del controllo SEO del 19 novembre 2026 (docs-sviluppo/controllo-seo-2026-11-19.md).
//  P1 la pagina del blog fatta con l'editor esce con il suo disegno; il primo widget che elenca tutti gli articoli mostra la pagina N
//     con i link page/N/ veri; senza un widget così l'elenco del blog esce sotto; la pagina Blog di partenza (solo il titolo) e il
//     blog scritto nella scheda escono come prima (uguali al byte alla 2.39.2, tolte le righe og: e twitter: nuove della P3);
//  P2 la descrizione della categoria è la meta description (pulita e tagliata), con "Pagina N di M." dalla 2;
//  P3 Open Graph e twitter:card su blog, categorie e tag, og:url uguale al canonical;
//  P4 eventi futuri, test pubblicati e condizioni del negozio nella sitemap (senza l'evento vecchio, le bozze, i risultati).
// uso: node prove/prova-seo-proposte.js <base> <cartella CMS> [base della 2.39.2 sullo stesso database]
//   (base senza barra finale, con la cartella se c'è; il terzo argomento, facoltativo, è un server con i file della 2.39.2 e lo
//   stesso config.local.php: serve per i confronti al byte). Vuole un sito installato col kit Attività. Semina con
//   prove/paginazione-cli.php e prove/seo-proposte-cli.php e alla fine toglie quello che ha messo.
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const [B, DIR, PRIMA] = process.argv.slice(2);
if (!B || !DIR) { console.error('uso: node prove/prova-seo-proposte.js <base> <cartella CMS> [base 2.39.2]'); process.exit(2); }
const php = (f, a) => JSON.parse(execFileSync('php', [__dirname + '/' + f, DIR, ...a], { encoding: 'utf8' }).trim().split('\n').pop());
const pcli = (...a) => php('paginazione-cli.php', a);
const cli = (...a) => php('seo-proposte-cli.php', a);
let ok_ = 0, ko = 0;
const ok = (nome, cond, extra) => { if (cond) { ok_++; console.log('OK  ' + nome); } else { ko++; console.log('KO  ' + nome + (extra !== undefined ? '  ' + JSON.stringify(extra).slice(0, 400) : '')); } };
const cartella = new URL(B).pathname.replace(/\/$/, '');
const dec = s => s.replace(/&apos;/g, "'").replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>');
async function leggi(u, base = B) {
  const r = await fetch(base + u, { redirect: 'manual' });
  const h = await r.text();
  const g = rx => { const m = h.match(rx); return m ? dec(m[1]) : null; };
  const og = {};
  for (const m of h.matchAll(/<meta property="(og:[a-z_:]+)" content="([^"]*)"/g)) og[m[1]] = dec(m[2]);
  const nodi = [];
  for (const m of h.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)) { try { const j = JSON.parse(m[1]); nodi.push(...(j['@graph'] || [j])); } catch (e) { nodi.push({ rotto: true }); } }
  return { s: r.status, l: r.headers.get('location') || '', titolo: g(/<title>([\s\S]*?)<\/title>/), desc: g(/<meta name="description" content="([^"]*)"/),
    robots: g(/<meta name="robots" content="([^"]*)"/), canonical: g(/<link rel="canonical" href="([^"]*)"/), og,
    tw: g(/<meta name="twitter:card" content="([^"]*)"/), twImg: g(/<meta name="twitter:image" content="([^"]*)"/), nodi, h,
    h1: (h.match(/<h1[\s>]/g) || []).length,
    pag: [...h.matchAll(/<nav class="paginazione"[\s\S]*?<\/nav>/g)].map(m => [...m[0].matchAll(/href="([^"]+)"/g)].map(x => x[1])),
    schede: [...h.matchAll(/<a class="(?:ew-post )?card[^"]*" href="([^"]+)"/g)].map(m => m[1]).concat([...h.matchAll(/<h3 class="ew-post-titolo"><a href="([^"]+)"/g)].map(m => m[1])) };
}
const pagina = (base, n) => n === 1 ? base : base + 'page/' + n + '/';
// Per il confronto al byte con la 2.39.2: via le righe og: e twitter: (nuove della P3) e i numeri di versione dei file.
const pulito = h => h.split('\n').filter(r => !/<meta (property="og:|name="twitter:)/.test(r)).join('\n').replace(/\?v=\d+/g, '?v=').replace(/nonce="[^"]*"/g, '');

// I controlli SEO di una pagina N di un elenco (come la 2.39.2) e l'Open Graph della P3.
function seoPagina(nome, x, u, n, m, conCanonical = true) {
  ok(`${nome} pagina ${n}: 200`, x.s === 200, x.s);
  if (conCanonical) ok(`${nome} pagina ${n}: canonical sul suo indirizzo`, x.canonical === B + u, x.canonical);
  if (n > 1) ok(`${nome} pagina ${n}: titolo con "Pagina ${n}"`, (x.titolo || '').includes(' Pagina ' + n + ' '), x.titolo);
  if (n > 1 && x.desc !== null) ok(`${nome} pagina ${n}: descrizione con "Pagina ${n} di ${m}."`, x.desc.endsWith('Pagina ' + n + ' di ' + m + '.'), x.desc);
  ok(`${nome} pagina ${n}: og:type website, og:title uguale al titolo, og:site_name e og:locale`, x.og['og:type'] === 'website' && x.og['og:title'] === x.titolo && !!x.og['og:site_name'] && x.og['og:locale'] === 'it_IT', x.og);
  ok(`${nome} pagina ${n}: og:url uguale al canonical${conCanonical ? '' : ' (assente senza canonical)'}`, conCanonical ? x.og['og:url'] === x.canonical : !('og:url' in x.og) && x.canonical === null, [x.og['og:url'], x.canonical]);
  ok(`${nome} pagina ${n}: og:description uguale alla descrizione (o assente)`, x.desc === null ? !('og:description' in x.og) : x.og['og:description'] === x.desc, [x.og['og:description'], x.desc]);
  ok(`${nome} pagina ${n}: twitter:card summary_large_image`, x.tw === 'summary_large_image', x.tw);
  ok(`${nome} pagina ${n}: niente cartella due volte`, !cartella || !x.h.includes(cartella + cartella + '/'));
}

async function blogEditor(variante, per, attesoTesto) {
  cli('blog', variante);
  const N = cli('conta').articoli, M = Math.max(1, Math.ceil(N / per));
  const base = '/blog-di-prova/';
  const v = [];
  for (let n = 1; n <= M; n++) v.push(await leggi(pagina(base, n)));
  const nome = `blog ${variante}`;
  ok(`${nome}: ${M} pagine (${N} articoli, ${per} per pagina) tutte 200`, v.every(x => x.s === 200), v.map(x => x.s));
  for (let n = 1; n <= M; n++) {
    const x = v[n - 1];
    if (attesoTesto) ok(`${nome} pagina ${n}: si vedono il titolo e il testo del disegno`, /Il blog fatto con l(&#0?39;|&apos;|')editor/.test(x.h) && x.h.includes('Un testo scritto nel disegno del blog.'));
    ok(`${nome} pagina ${n}: un solo h1`, x.h1 === 1, x.h1);
    ok(`${nome} pagina ${n}: ${n < M ? per : N - per * (M - 1)} articoli`, x.schede.length === (n < M ? per : N - per * (M - 1)), x.schede.length);
    const attesi = []; for (let k = 1; k <= M; k++) if (k !== n) attesi.push(cartella + pagina(base, k));
    ok(`${nome} pagina ${n}: una paginazione con i link page/N/ veri`, x.pag.length === 1 && attesi.every(a => x.pag[0].includes(a)) && x.pag[0].length === M - 1, x.pag);
    seoPagina(nome, x, pagina(base, n), n, M);
    const wp = x.nodi.find(k => k['@type'] === 'WebPage');
    ok(`${nome} pagina ${n}: JSON-LD WebPage con il suo indirizzo`, wp && wp.url === B + pagina(base, n) && wp['@id'] === B + pagina(base, n) + '#pagina', wp && wp.url);
  }
  const tutte = v.flatMap(x => x.schede);
  ok(`${nome}: gli articoli delle ${M} pagine sono tutti diversi e sono tutti (${N})`, new Set(tutte).size === N && tutte.length === N, [new Set(tutte).size, tutte.length]);
  ok(`${nome}: pagina 1 con la descrizione della scheda (senza "Pagina")`, v[0].desc === 'Gli articoli del blog di prova: consigli pratici, novità e storie, ogni settimana.', v[0].desc);
  let d = await leggi(base + 'page/1/');
  ok(`${nome}: page/1/ -> 301 alla prima pagina`, d.s === 301 && d.l === B + base, [d.s, d.l]);
  for (const strano of [String(M + 1), '0', 'abc']) {
    d = await leggi(base + 'page/' + strano + '/');
    ok(`${nome}: page/${strano}/ -> 404 vera`, d.s === 404 && /Pagina non trovata/.test(d.titolo || '') && !/Fatal|Warning:/.test(d.h), [d.s, d.titolo]);
  }
  const sm = await (await fetch(B + '/sitemap.xml')).text();
  ok(`${nome}: sitemap con la pagina del blog e senza page/N/`, sm.includes('<loc>' + B + base + '</loc>') && !sm.includes('/page/'));
  return v;
}

async function confronto(nome, u) {
  if (!PRIMA) return;
  const a = await (await fetch(PRIMA + u)).text(), b = await (await fetch(B + u)).text();
  const pa = pulito(a), pb = pulito(b);
  let i = 0; while (i < pa.length && pa[i] === pb[i]) i++;
  ok(`${nome} (${u}): uguale al byte alla 2.39.2, tolte le righe og: e twitter:`, pa === pb, [pa.length, pb.length, pa.slice(i - 60, i + 80), pb.slice(i - 60, i + 80)]);
}

(async () => {
  cli('pulisci'); pcli('pulisci'); pcli('seo-salva'); pcli('seo-rimetti');
  pcli('semina', '30');

  // P1. Il disegno con Ultimi articoli (10 per pagina), con la Griglia (15 per pagina), senza widget che elenca gli articoli.
  const art = await blogEditor('articoli', 10, true);
  ok('blog articoli: la paginazione sta dentro il widget del disegno (nessun elenco standard sotto)', !art[0].h.includes('class="sez-titolo"') && /ed-w-articoli[^>]*>[\s\S]*?<nav class="paginazione"/.test(art[0].h));
  const gr = await blogEditor('griglia', 15, true);
  ok('blog griglia: paginazione del server, non quella nel browser (niente data-ew-pagina)', !gr[0].h.includes('data-ew-pagina') && gr[0].h.includes('ew-posts'));
  const se = await blogEditor('senza', 12, true);
  ok('blog senza widget elenco: sotto il disegno esce l\'elenco del blog (filtri e schede), senza un secondo titolo', se[0].h.includes('class="filtri"') && !se[0].h.includes('class="sez-titolo"') && se[0].h.indexOf('Un testo scritto nel disegno') < se[0].h.indexOf('class="filtri"'));

  // P1 nel browser: dalla pagina 1 al 2 della paginazione del widget.
  cli('blog', 'articoli');
  const br = await chromium.launch();
  try {
    const p = await br.newPage();
    await p.goto(B + '/blog-di-prova/', { waitUntil: 'domcontentloaded' });
    ok('browser: il testo del disegno si vede', await p.getByText('Un testo scritto nel disegno del blog.').isVisible());
    await p.locator('nav.paginazione a', { hasText: /^2$/ }).first().click();
    await p.waitForLoadState('domcontentloaded');
    ok('browser: il 2 porta a page/2/ con il disegno e il titolo "Pagina 2"', p.url() === B + '/blog-di-prova/page/2/' && (await p.title()).includes('Pagina 2') && await p.getByText('Un testo scritto nel disegno del blog.').isVisible(), [p.url(), await p.title()]);
  } finally { await br.close(); }

  // P1. La pagina Blog di partenza (solo il titolo) e il blog scritto nella scheda: la vista standard, come prima.
  for (const variante of ['titolo', 'classica']) {
    cli('blog', variante);
    const x = await leggi('/blog-di-prova/'), x2 = await leggi('/blog-di-prova/page/2/');
    ok(`blog ${variante}: vista standard ("Gli articoli", 12 per pagina), un solo h1`, x.s === 200 && x.h.includes('class="sez-titolo"') && x.schede.length === 12 && x.h1 === 1 && x2.s === 200, [x.s, x.schede.length, x.h1]);
    seoPagina(`blog ${variante}`, x2, '/blog-di-prova/page/2/', 2, Math.ceil(cli('conta').articoli / 12));
    await confronto(`blog ${variante} pagina 1`, '/blog-di-prova/');
    await confronto(`blog ${variante} pagina 2`, '/blog-di-prova/page/2/');
  }

  // P3. L'immagine per i social: quella della pagina del blog, altrimenti la predefinita di SEO e AEO.
  pcli('seo', 'meta', 'og_immagine', '/uploads/prova-predefinita.jpg');
  let x = await leggi('/category/pagine-prova/');
  ok('categoria: og:image è l\'immagine predefinita di SEO e AEO (e twitter:image uguale)', (x.og['og:image'] || '').endsWith('/uploads/prova-predefinita.jpg') && x.twImg === x.og['og:image'], [x.og['og:image'], x.twImg]);
  cli('blog', 'articoli'); cli('immagine', '/uploads/prova-blog.jpg');
  x = await leggi('/blog-di-prova/page/2/');
  ok('blog fatto con l\'editor: og:image è l\'immagine della sua scheda', (x.og['og:image'] || '').endsWith('/uploads/prova-blog.jpg'), x.og['og:image']);
  cli('immagine', ''); pcli('seo-rimetti');

  // P2. La descrizione della categoria (semina: "Gli articoli di prova della paginazione, uno dopo l'altro.").
  const Mc = Math.ceil(30 / 10);
  const c1 = await leggi('/category/pagine-prova/'), c2 = await leggi('/category/pagine-prova/page/2/');
  ok('categoria pagina 1: la descrizione di Categorie è la meta description', c1.desc === "Gli articoli di prova della paginazione, uno dopo l'altro.", c1.desc);
  ok('categoria pagina 2: la stessa con "Pagina 2 di ' + Mc + '."', c2.desc === "Gli articoli di prova della paginazione, uno dopo l'altro. Pagina 2 di " + Mc + '.', c2.desc);
  seoPagina('categoria', c1, '/category/pagine-prova/', 1, Mc);
  seoPagina('categoria', c2, '/category/pagine-prova/page/2/', 2, Mc);
  cli('categoria', '<p>Una <b>descrizione</b> con l\'HTML <script>alert(1)</script>e &egrave; lunga: ' + 'parole di prova ripetute '.repeat(12) + '</p>');
  x = await leggi('/category/pagine-prova/');
  ok('categoria con HTML e script nella descrizione: esce pulita (niente tag, niente script) e tagliata intorno a 160 caratteri', x.desc && !/[<>]/.test(x.desc) && !x.desc.includes('alert') && x.desc.startsWith("Una descrizione con l'HTML e è lunga:") && x.desc.length <= 161 && x.desc.endsWith('…') && !x.h.includes('<script>alert(1)'), x.desc);
  cli('categoria', "Gli articoli di prova della paginazione, uno dopo l'altro.");
  cli('senza-descrizione');
  x = await leggi('/category/prova-senza-descrizione/');
  ok('categoria senza descrizione: nessuna meta description, come prima', x.s === 200 && x.desc === null && !('og:description' in x.og), [x.s, x.desc]);

  // P3. Il tag (noindex di partenza: niente canonical e niente og:url) e il tag indicizzato.
  const t1 = await leggi('/tag/pagine-prova-tag/'), t2 = await leggi('/tag/pagine-prova-tag/page/2/');
  seoPagina('tag noindex', t1, '/tag/pagine-prova-tag/', 1, 3, false);
  seoPagina('tag noindex', t2, '/tag/pagine-prova-tag/page/2/', 2, 3, false);
  pcli('seo', 'robots', 'tag', 'index');
  x = await leggi('/tag/pagine-prova-tag/page/2/');
  seoPagina('tag indicizzato', x, '/tag/pagine-prova-tag/page/2/', 2, 3);
  pcli('seo-rimetti');

  // P4. La sitemap con eventi, test e condizioni del negozio.
  const ad = cli('addon');
  const sm = await (await fetch(B + '/sitemap.xml')).text();
  const locs = [...sm.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1]);
  ok('sitemap: l\'evento futuro c\'è', locs.includes(B + '/evento/prova-sitemap-futuro/'), locs.filter(u => u.includes('/evento/')));
  ok('sitemap: l\'evento di 40 giorni fa e quello in bozza no', !locs.includes(B + '/evento/prova-sitemap-vecchio/') && !locs.includes(B + '/evento/prova-sitemap-bozza/'));
  ok('sitemap: il test pubblicato c\'è, la bozza no', locs.includes(B + ad.test.pubblicato) && !locs.includes(B + ad.test.bozza), [ad.test, locs.filter(u => u.includes('/fai-il-test/'))]);
  ok('sitemap: le condizioni del negozio ci sono', locs.includes(B + '/negozio/condizioni/'));
  ok('sitemap: niente risultati dei test, iscrizioni, carrello', !locs.some(u => /risultato|evento-iscrizione|carrello|ordine/.test(u)));
  const nuove = locs.filter(u => u.includes('/evento/') || u.includes('/fai-il-test/') || u.endsWith('/negozio/condizioni/'));
  const titoli = [], descr = [];
  for (const u of nuove) {
    const y = await leggi(u.slice(B.length));
    ok(`${u.slice(B.length)}: 200, index, canonical uguale al loc della sitemap, og:url uguale`, y.s === 200 && (y.robots || '').startsWith('index') && y.canonical === u && y.og['og:url'] === u, [y.s, y.robots, y.canonical, y.og['og:url']]);
    titoli.push(y.titolo); if (y.desc) descr.push(y.desc);
  }
  ok('le pagine nuove della sitemap hanno titoli tutti diversi e descrizioni non doppie', new Set(titoli).size === titoli.length && new Set(descr).size === descr.length && descr.length === nuove.length, [titoli, descr]);
  const vecchio = await leggi('/evento/prova-sitemap-vecchio/');
  ok('l\'evento di 40 giorni fa esce noindex (la stessa regola della sitemap)', vecchio.s === 200 && (vecchio.robots || '').startsWith('noindex'), vecchio.robots);

  cli('pulisci'); pcli('pulisci'); pcli('seo-rimetti', 'fine');
  // Dopo la pulizia il blog torna su /blog/ (la pagina Blog di partenza del kit): vista standard, uguale alla 2.39.2.
  x = await leggi('/blog/');
  ok('/blog/ del kit (pagina di partenza, solo il titolo): 200 con la vista standard', x.s === 200 && x.h.includes('class="sez-titolo"') && x.h1 === 1, [x.s, x.h1]);
  await confronto('/blog/ del kit', '/blog/');
  console.log(`\n${ok_} su ${ok_ + ko} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); try { cli('pulisci'); pcli('pulisci'); pcli('seo-rimetti', 'fine'); } catch (x) {} process.exit(1); });
