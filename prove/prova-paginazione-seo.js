// 2.39.2: le pagine 2, 3... di un elenco per i motori di ricerca. Ogni pagina ha il suo canonical, il suo titolo ("Pagina N") e la
// sua descrizione ("Pagina N di M"), il robots della prima (o quello di "Paginazione" in SEO e AEO), il JSON-LD con il suo indirizzo;
// page/1/ va alla prima con un 301, una pagina oltre l'ultima risponde 404; la sitemap resta senza page/N/; la Tabella SEO e
// site_audit (AI collegate) non contano le pagine dell'elenco come doppioni. Più le correzioni del controllo profondo: barre doppie
// in 301, sitemap con il robots per tipo, titolo della home senza il nome due volte.
// uso: node prove/prova-paginazione-seo.js <base> <cartella CMS>      (base senza barra finale, con la cartella se c'è)
// Vuole un sito installato (meglio col kit Attività). Semina 30 articoli "articolo-paginato-NN", la categoria "pagine-prova", il
// tag "pagine-prova-tag" e la pagina "Blog di prova" fatta con l'editor e scelta come pagina del blog; alla fine li toglie e
// rimette il blog su /blog/ (prove/paginazione-cli.php).
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const [B, DIR] = process.argv.slice(2);
if (!B || !DIR) { console.error('uso: node prove/prova-paginazione-seo.js <base> <cartella CMS>'); process.exit(2); }
const cli = (...a) => JSON.parse(execFileSync('php', [__dirname + '/paginazione-cli.php', DIR, ...a], { encoding: 'utf8' }).trim().split('\n').pop());
let ok_ = 0, ko = 0;
const ok = (nome, cond, extra) => { if (cond) { ok_++; console.log('OK  ' + nome); } else { ko++; console.log('KO  ' + nome + (extra !== undefined ? '  ' + JSON.stringify(extra).slice(0, 400) : '')); } };
const cartella = new URL(B).pathname.replace(/\/$/, '');
const dec = s => s.replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>');
async function leggi(u) {
  const r = await fetch(B + u, { redirect: 'manual' });
  const h = await r.text();
  const g = rx => { const m = h.match(rx); return m ? dec(m[1]) : null; };
  const nodi = [];
  for (const m of h.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)) { try { const j = JSON.parse(m[1]); nodi.push(...(j['@graph'] || [j])); } catch (e) { nodi.push({ rotto: true }); } }
  return { s: r.status, l: r.headers.get('location') || '', titolo: g(/<title>([\s\S]*?)<\/title>/), desc: g(/<meta name="description" content="([^"]*)"/),
    robots: g(/<meta name="robots" content="([^"]*)"/), canonical: g(/<link rel="canonical" href="([^"]*)"/), ogUrl: g(/<meta property="og:url" content="([^"]*)"/),
    ogTitle: g(/<meta property="og:title" content="([^"]*)"/), nodi, h };
}
const pagina = (base, n) => n === 1 ? base : base + 'page/' + n + '/';

async function elenco(nome, base, pagine, attesoIndex) {
  const v = [];
  for (let n = 1; n <= pagine; n++) v.push(await leggi(pagina(base, n)));
  ok(`${nome}: le ${pagine} pagine rispondono 200`, v.every(x => x.s === 200), v.map(x => x.s));
  for (let n = 2; n <= pagine; n++) {
    const x = v[n - 1], u = B + pagina(base, n);
    if (attesoIndex) ok(`${nome} pagina ${n}: canonical sul suo indirizzo (${pagina(base, n)})`, x.canonical === u, x.canonical);
    else ok(`${nome} pagina ${n}: noindex senza canonical verso un'altra pagina`, x.canonical === null || x.canonical === u, x.canonical);
    ok(`${nome} pagina ${n}: titolo con "Pagina ${n}" e diverso dalla prima`, x.titolo !== v[0].titolo && x.titolo.includes(' Pagina ' + n + ' '), [v[0].titolo, x.titolo]);
    ok(`${nome} pagina ${n}: robots come la prima (${attesoIndex ? 'index' : 'noindex'})`, (x.robots || '').startsWith(attesoIndex ? 'index' : 'noindex') && (v[0].robots || '').startsWith(attesoIndex ? 'index' : 'noindex'), [v[0].robots, x.robots]);
    ok(`${nome} pagina ${n}: descrizione diversa dalla prima ("Pagina ${n} di ${pagine}." o nessuna)`, v[0].desc === null ? x.desc === null : (x.desc !== v[0].desc && x.desc.endsWith('Pagina ' + n + ' di ' + pagine + '.')), [v[0].desc, x.desc]);
    ok(`${nome} pagina ${n}: og:url assente o uguale al canonical`, x.ogUrl === null || x.ogUrl === x.canonical, [x.ogUrl, x.canonical]);
    const wp = x.nodi.find(k => ['WebPage', 'CollectionPage'].includes(k['@type']));
    ok(`${nome} pagina ${n}: JSON-LD della pagina (se c'è) con il suo indirizzo come url e @id`, !wp || (wp.url === u && wp['@id'] === u + '#pagina'), wp && [wp.url, wp['@id']]);
    ok(`${nome} pagina ${n}: niente cartella due volte`, !cartella || !x.h.includes(cartella + cartella + '/'));
  }
  ok(`${nome}: i titoli delle ${pagine} pagine sono tutti diversi`, new Set(v.map(x => x.titolo)).size === pagine, v.map(x => x.titolo));
  let d = await leggi(pagina(base, 1) + 'page/1/');
  ok(`${nome}: page/1/ -> 301 alla prima pagina`, d.s === 301 && d.l === B + base, d.l);
  for (const strano of [String(pagine + 1), '99', '0', '02', 'abc', '-1', '99999999999999999999999']) {
    d = await leggi(base + 'page/' + strano + '/');
    ok(`${nome}: page/${strano}/ -> 404 vera (pagina 404 del sito, niente percorsi del server)`, d.s === 404 && /Pagina non trovata/.test(d.titolo || '') && !d.h.includes('/includes/') && !/Fatal|Warning:/.test(d.h), [d.s, d.l, d.titolo]);
  }
  return v;
}

(async () => {
  cli('pulisci'); cli('seo-salva'); cli('seo-rimetti');
  cli('semina', '30'); cli('blog-editor');

  // 1. Il blog (pagina fatta con l'editor, scelta come pagina del blog): 30 articoli, 12 per pagina = 3 pagine.
  const blog = await elenco('blog', '/blog-di-prova/', 3, true);
  const sito = (blog[0].titolo.match(/ - (.+)$/) || [])[1] || '';
  ok('blog pagina 1: canonical sul suo indirizzo, come prima', blog[0].canonical === B + '/blog-di-prova/', blog[0].canonical);
  ok('blog pagina 2: titolo "Blog di prova - Pagina 2 - ' + sito + '"', blog[1].titolo === 'Blog di prova - Pagina 2 - ' + sito, blog[1].titolo);
  ok('blog pagina 1: descrizione come prima (senza "Pagina")', blog[0].desc === 'Gli articoli del blog di prova: consigli pratici, novità e storie, ogni settimana.', blog[0].desc);

  // 2. Categoria (10 per pagina = 3 pagine, indicizzata) e tag (noindex di partenza).
  await elenco('categoria', '/category/pagine-prova/', 3, true);
  await elenco('tag', '/tag/pagine-prova-tag/', 3, false);
  cli('seo', 'robots', 'tag', 'index');
  let x = await leggi('/tag/pagine-prova-tag/page/2/');
  ok('tag indicizzato da SEO e AEO: pagina 2 index con il canonical sul suo indirizzo', (x.robots || '').startsWith('index') && x.canonical === B + '/tag/pagine-prova-tag/page/2/', [x.robots, x.canonical]);
  cli('seo-rimetti');

  // 3. "Paginazione: noindex" in SEO e AEO vale per tutti gli elenchi, solo dalla pagina 2.
  cli('seo', 'robots', 'paginazione', 'noindex');
  const p1 = await leggi('/blog-di-prova/'), p2 = await leggi('/blog-di-prova/page/2/'), c2 = await leggi('/category/pagine-prova/page/2/'), c1 = await leggi('/category/pagine-prova/');
  ok('Paginazione noindex: blog e categoria pagina 1 restano index', (p1.robots || '').startsWith('index') && (c1.robots || '').startsWith('index'), [p1.robots, c1.robots]);
  ok('Paginazione noindex: blog e categoria pagina 2 noindex', (p2.robots || '').startsWith('noindex') && (c2.robots || '').startsWith('noindex'), [p2.robots, c2.robots]);
  cli('seo-rimetti');

  // 4. Il formato dei titoli di SEO e AEO e il titolo per Google scritto a mano.
  cli('seo', 'titolo', 'sep', '|');
  x = await leggi('/blog-di-prova/page/2/');
  ok('separatore "|": "Blog di prova | Pagina 2 | ' + sito + '"', x.titolo === 'Blog di prova | Pagina 2 | ' + sito, x.titolo);
  cli('seo-rimetti');
  cli('titolo-blog', 'Il nostro blog - ' + sito);
  x = await leggi('/blog-di-prova/page/2/');
  ok('titolo per Google scritto a mano: "Il nostro blog - Pagina 2 - ' + sito + '"', x.titolo === 'Il nostro blog - Pagina 2 - ' + sito, x.titolo);
  cli('titolo-blog', '');

  // 5. Sitemap: niente page/N/, la pagina del blog sì.
  const sm = await (await fetch(B + '/sitemap.xml')).text();
  const locs = [...sm.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1]);
  ok('sitemap senza indirizzi page/N/', locs.length > 30 && !locs.some(u => u.includes('/page/')), locs.filter(u => u.includes('/page/')));
  ok('sitemap con la pagina del blog e la categoria', locs.includes(B + '/blog-di-prova/') && locs.includes(B + '/category/pagine-prova/'));

  // 6. Doppioni: la Tabella SEO e site_audit non contano le pagine dell'elenco (e vedono un doppione vero).
  let a = cli('audit');
  ok('Tabella SEO e site_audit: nessun doppione e nessun indirizzo page/N/', a.tabella.length === 0 && a.site_audit.length === 0 && a.percorsi_paginati === 0, a);
  cli('doppio', '1'); a = cli('audit'); cli('doppio', '0');
  ok('con due articoli con la stessa descrizione il doppione si vede (Tabella SEO e, se c\'è l\'addon, site_audit)', a.tabella.length === 2 && (a.site_audit_totali === undefined || a.site_audit.length === 2), a);

  // 7. Controllo profondo: barre doppie, sitemap con il robots per tipo, titolo della home.
  let d = await leggi('//blog-di-prova/');
  ok('//blog-di-prova/ -> 301 a /blog-di-prova/ (prima: la home con 200)', d.s === 301 && d.l === B + '/blog-di-prova/', [d.s, d.l]);
  d = await leggi('/blog-di-prova//?utm_source=x');
  ok('/blog-di-prova//?utm_source=x -> 301 a /blog-di-prova/?utm_source=x', d.s === 301 && d.l === B + '/blog-di-prova/?utm_source=x', [d.s, d.l]);
  d = await leggi('/blog-di-prova/?utm_source=prova&fbclid=x');
  ok('/blog-di-prova/?utm_source=...: 200 con il canonical senza parametri', d.s === 200 && d.canonical === B + '/blog-di-prova/', d.canonical);
  cli('seo', 'robots', 'articoli', 'noindex');
  const art = await leggi('/articolo-paginato-01/');
  const sm2 = await (await fetch(B + '/sitemap.xml')).text();
  ok('Articoli noindex in SEO e AEO: l\'articolo è noindex e non è più nella sitemap', (art.robots || '').startsWith('noindex') && !sm2.includes('/articolo-paginato-01/'), art.robots);
  cli('seo-rimetti');
  const sm3 = await (await fetch(B + '/sitemap.xml')).text();
  ok('con "auto" l\'articolo torna nella sitemap', sm3.includes('/articolo-paginato-01/'));
  const home = await leggi('/');
  const parti = (home.titolo || '').split(' - ');
  ok('titolo della home senza il nome del sito due volte', !(parti.length === 2 && parti[0] === parti[1]), home.titolo);

  // 8. Nel browser, come un visitatore: dalla pagina 2 al numero 3 della paginazione.
  const br = await chromium.launch();
  try {
    const p = await br.newPage();
    await p.goto(B + '/blog-di-prova/page/2/', { waitUntil: 'domcontentloaded' });
    const canon = await p.evaluate(() => (document.querySelector('link[rel=canonical]') || {}).href || '');
    ok('browser: pagina 2 con il canonical sul suo indirizzo e il titolo nella scheda', canon === B + '/blog-di-prova/page/2/' && (await p.title()).includes('Pagina 2'), [canon, await p.title()]);
    await p.locator('nav.paginazione a', { hasText: /^3$/ }).first().click();
    await p.waitForLoadState('domcontentloaded');
    ok('browser: il link 3 della paginazione porta a page/3/ con il suo titolo', p.url() === B + '/blog-di-prova/page/3/' && (await p.title()).includes('Pagina 3'), [p.url(), await p.title()]);
  } finally { await br.close(); }

  cli('pulisci'); cli('seo-rimetti', 'fine');
  console.log(`\n${ok_} su ${ok_ + ko} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); try { cli('pulisci'); cli('seo-rimetti', 'fine'); } catch (x) {} process.exit(1); });
