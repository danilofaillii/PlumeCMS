// Le correzioni SEO e AEO della 2.35.0 (audit di plumecms.it), come le vede una persona, su un sito col kit Attività.
//  1. llms.txt calcolato: la sezione Pagine (home per prima, mai bozze né pagine nascoste a Google), niente sezioni vuote, niente
//     Glossario senza termini, la descrizione generale di SEO e AEO in testa, quello scritto a mano che vince.
//  2. I dati per Google "Programma" dal pannello, nel browser: il riquadro, il salvataggio, il nodo SoftwareApplication nel
//     JSON-LD del sito; i valori cattivi (licenza javascript:, prezzo non numerico, nome con script) respinti; la parola chiave
//     della pagina; 1280 e 390 px senza scorrimento di lato.
//  3. Una pagina importata come da un pacchetto con tldr, faq, keyword e seo_schema; una con seo_schema cattivo respinta; una
//     senza le chiavi nuove che lascia i campi com'erano.
//  4. La voce "seo" del manifest: letta se buona, respinta se chiede chiavi non ammesse o un logo javascript:.
//  Con SALVA_HTML=<file> salva l'HTML di alcune pagine e esce (prima dell'aggiornamento); con CONFRONTA_HTML=<file> lo confronta
//  per primo (dopo l'aggiornamento: il sito deve uscire identico al byte, a parte llms.txt).
// Vuole un sito col kit Attività (prove/prova-kit.js ... attivita ...): il controllo dell'In breve nel llms.txt usa la pagina /servizi/.
// Su un sito appena installato senza quel kit dà 41 su 42, non è un difetto.
// uso: node prove/prova-seo-plume.js <base> <cartella CMS> <utente> <password> [cartella foto]
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, FOTO] = process.argv.slice(2);
if (!B || !DIR || !UTENTE || !PASSWORD) { console.error('uso: node prove/prova-seo-plume.js <base> <cartella CMS> <utente> <password> [cartella foto]'); process.exit(2); }
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? '  ' + JSON.stringify(extra).slice(0, 400) : '')); };
const run = (file, a) => JSON.parse(execFileSync('php', [path.join(__dirname, file), DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }).trim().split('\n').pop());
const cli = (...a) => run('seo-plume-cli.php', a);
const scli = (...a) => run('seo-strumenti-cli.php', a);
const foto = async (p, nome) => { if (FOTO) await p.screenshot({ path: path.join(FOTO, nome + '.png'), fullPage: true }); };
const grafo = async (ctx, u) => { const r = await ctx.request.get(B + u); const h = await r.text(); const m = h.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/); try { return m ? JSON.parse(m[1])['@graph'] : null; } catch (e) { return null; } };
const testo = async (ctx, u) => (await (await ctx.request.get(B + u)).text());
const pulito = h => h.replace(/\?v=\d+/g, '').replace(/ nonce="[^"]*"/g, '').replace(/data-ora="[^"]*"/g, '');
const PAGINE_CONFRONTO = ['/', '/servizi/', '/chi-siamo/', '/contatti/', '/sitemap.xml', '/robots.txt', '/feed/'];

(async () => {
  const br = await chromium.launch();
  const anon = await br.newContext();
  // --- Prima e dopo l'aggiornamento: il sito identico al byte
  if (process.env.SALVA_HTML) {
    const out = {};
    for (const u of PAGINE_CONFRONTO) out[u] = pulito(await testo(anon, u));
    fs.writeFileSync(process.env.SALVA_HTML, JSON.stringify(out));
    console.log('salvate ' + Object.keys(out).length + ' pagine in ' + process.env.SALVA_HTML);
    await br.close(); return;
  }
  if (process.env.CONFRONTA_HTML) {
    const prima = JSON.parse(fs.readFileSync(process.env.CONFRONTA_HTML, 'utf8'));
    for (const u of PAGINE_CONFRONTO) {
      const dopo = pulito(await testo(anon, u));
      let i = 0; while (i < dopo.length && dopo[i] === prima[u][i]) i++;
      ok('dopo l\'aggiornamento ' + u + ' è identica al byte a prima', dopo === prima[u], dopo === prima[u] ? '' : { prima: prima[u].slice(Math.max(0, i - 80), i + 120), dopo: dopo.slice(Math.max(0, i - 80), i + 120) });
    }
  }

  // Pulizia di un giro prima
  const pulisci = () => {
    for (const s of ['prova-plume-programma', 'prova-plume-bozza', 'prova-plume-nascosta', 'prova-plume-import', 'prova-plume-import2']) scli('sql', `DELETE FROM {p}pages WHERE slug = '${s}'`);
    scli('sql', "DELETE FROM {p}articles WHERE slug = 'prova-plume-articolo'");
    cli('seo_azzera', 'llms'); cli('seo_azzera', 'meta');
  };
  pulisci();

  // === 1. llms.txt
  const nasc = scli('pagina', 'prova-plume-nascosta', 'Pagina nascosta a Google', JSON.stringify([{ tipo: 'testo', impostazioni: { contenuto: '<p>Da non elencare.</p>' } }]));
  scli('sql', `UPDATE {p}pages SET robots = 'noindex' WHERE id = ${nasc.id}`);
  const bozza = scli('pagina', 'prova-plume-bozza', 'Pagina in bozza', JSON.stringify([{ tipo: 'testo', impostazioni: { contenuto: '<p>Bozza.</p>' } }]));
  scli('sql', `UPDATE {p}pages SET status = 'draft' WHERE id = ${bozza.id}`);
  const pagine = scli('sql', "SELECT slug, title, robots FROM {p}pages WHERE status = 'published' AND cestino IS NULL ORDER BY id");
  const home = scli('sql', "SELECT setting_value FROM {p}settings WHERE setting_key = 'pagina_home'")[0]?.setting_value || '';
  scli('sql', `UPDATE {p}pages SET tldr = 'Il riquadro In breve della pagina dei servizi, scritto per la prova.' WHERE slug = 'servizi'`);
  let llms = await testo(anon, '/llms.txt');
  const sezioni = llms.split('\n').filter(r => r.startsWith('## '));
  ok('llms.txt risponde e ha la sezione Pagine', llms.includes('\n## Pagine\n'), sezioni);
  ok('llms.txt: nessuna intestazione vuota (ogni "## " è seguita da una voce)', llms.split('\n').every((r, i, a) => !r.startsWith('## ') || (a[i + 1] || '').startsWith('- ')), llms.slice(0, 600));
  ok('llms.txt: niente "Guide complete" senza pillar e niente Glossario senza termini', !llms.includes('Guide complete') && !llms.includes('/glossario/'));
  const righePagine = llms.split('## Pagine\n')[1].split('\n\n')[0].split('\n');
  ok('llms.txt: la home è la prima delle pagine', righePagine[0].includes('](' + B.replace(/\/$/, '') + '/)'), righePagine[0]);
  const attese = pagine.filter(p => p.robots !== 'noindex' && p.slug !== home);
  ok('llms.txt: ci sono tutte le pagine pubblicate e indicizzabili (' + attese.length + ' più la home)', attese.every(p => llms.includes('/' + p.slug + '/)')), attese.map(p => p.slug));
  ok('llms.txt: la pagina nascosta a Google (noindex) e la bozza non ci sono', !llms.includes('prova-plume-nascosta') && !llms.includes('prova-plume-bozza'));
  ok('llms.txt: accanto a una pagina il suo In breve', llms.includes('/servizi/): Il riquadro In breve della pagina dei servizi'));
  cli('seo_salva', 'meta', JSON.stringify({ descrizione: 'Descrizione generale del sito scritta in SEO e AEO per la prova.' }));
  llms = await testo(anon, '/llms.txt');
  ok('llms.txt: in testa la descrizione generale di SEO e AEO', /^# .+\n\n> Descrizione generale del sito scritta in SEO e AEO per la prova\.\n/.test(llms), llms.slice(0, 200));
  scli('articolo', 'prova-plume-articolo', 'Articolo di prova per il llms', '', 'Un articolo di prova pubblicato.', JSON.stringify([{ tipo: 'testo', impostazioni: { contenuto: '<p>Testo.</p>' } }]));
  llms = await testo(anon, '/llms.txt');
  ok('llms.txt: con un articolo compaiono la sezione Articoli e il feed', llms.includes('\n## Articoli\n') && llms.includes('- [Articolo di prova per il llms](') && llms.includes('/feed/)'));
  cli('seo_salva', 'llms', JSON.stringify({ personale: '# Scritto a mano\n\n- una riga mia' }));
  llms = await testo(anon, '/llms.txt');
  ok('llms.txt: quello scritto a mano nel pannello vince', llms.trim() === '# Scritto a mano\n\n- una riga mia', llms.slice(0, 100));
  cli('seo_azzera', 'llms');

  // === 2. Programma dal pannello
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await ctx.newPage();
  pnConferme.on(p, d => d.accept());
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 160)));
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok('login amministratore', !p.url().includes('login.php'), p.url());
  const prog = scli('pagina', 'prova-plume-programma', 'Agenda Studio', JSON.stringify([{ tipo: 'testo', impostazioni: { contenuto: '<p>Un programma per lo studio.</p>' } }]));
  const scheda = B + '/admin/contenuto.php?tipo=pagine&id=' + prog.id;
  await p.goto(scheda, { waitUntil: 'networkidle' });
  ok('la tendina Dati per Google in più ha "Programma"', await p.locator('#sc-schema-tipo option[value="programma"]').count() === 1);
  ok('i campi del Programma sono nascosti finché non lo scegli', await p.locator('[data-schema-campi="programma"]').isHidden());
  await p.selectOption('#sc-schema-tipo', 'programma');
  ok('scelto Programma, compaiono i suoi campi (nome, versione, categoria, prezzo, licenza, download)', await p.locator('[data-schema-campi="programma"]').isVisible()
    && await p.locator('#sc-schema-programma-nome').isVisible() && await p.locator('#sc-schema-programma-categoria option').count() >= 6);
  ok('la scheda ha il campo Parola chiave principale anche per le pagine', await p.locator('#sc-keyword').count() === 1);
  await p.fill('#sc-schema-programma-nome', 'Agenda Studio');
  await p.fill('#sc-schema-programma-versione', '2.1.0');
  await p.selectOption('#sc-schema-programma-categoria', 'BusinessApplication');
  await p.fill('#sc-schema-programma-sistema', 'Windows e macOS');
  await p.fill('#sc-schema-programma-prezzo', '0');
  await p.fill('#sc-schema-programma-licenza', 'https://www.gnu.org/licenses/gpl-3.0.html');
  await p.fill('#sc-schema-programma-download', '/scarica/agenda/');
  await p.fill('#sc-schema-programma-requisiti', 'PHP 8.2 e MySQL 8');
  await p.fill('#sc-keyword', 'agenda per lo studio');
  await p.fill('#sc-meta', 'Agenda Studio: il programma per gli appuntamenti dello studio, gratuito, per Windows e macOS.');
  await foto(p, 'programma-scheda');
  await Promise.all([p.waitForNavigation(), p.locator('button.btn-primary[data-valore="pubblica"]').click()]);
  ok('salvata: nessun messaggio d\'errore', await p.locator('.flash-error').count() === 0, await p.locator('.flash-error').allTextContents());
  const riga = cli('pagina', 'prova-plume-programma');
  ok('la parola chiave della pagina è salvata', riga.keyword_target === 'agenda per lo studio', riga.keyword_target);
  let g = await grafo(anon, '/prova-plume-programma/');
  const sw = (g || []).find(x => x['@type'] === 'SoftwareApplication');
  ok('nel JSON-LD del sito il nodo SoftwareApplication con nome, versione, categoria, sistema', sw && sw.name === 'Agenda Studio' && sw.softwareVersion === '2.1.0' && sw.applicationCategory === 'BusinessApplication' && sw.operatingSystem === 'Windows e macOS', sw);
  ok('offerta gratuita (price 0, EUR), licenza e download interi, requisiti', sw && sw.offers && sw.offers.price === '0' && sw.offers.priceCurrency === 'EUR'
    && sw.license === 'https://www.gnu.org/licenses/gpl-3.0.html' && /^http:\/\/127\.0\.0\.1:\d+(\/test)?\/scarica\/agenda\/$/.test(sw.downloadUrl) && sw.softwareRequirements === 'PHP 8.2 e MySQL 8', sw && [sw.offers, sw.downloadUrl]);
  ok('autore ed editore sono l\'Organizzazione; niente valutazioni inventate', sw && sw.author && /#organizzazione$/.test(sw.author['@id']) && sw.publisher && !('aggregateRating' in sw) && !('review' in sw), sw && sw.author);
  const wp = (g || []).find(x => x['@type'] === 'WebPage');
  ok('la WebPage indica il programma come mainEntity', wp && wp.mainEntity && wp.mainEntity['@id'] === sw['@id'], wp && wp.mainEntity);
  // valori cattivi
  await p.goto(scheda, { waitUntil: 'networkidle' });
  ok('riaperta la scheda, i campi tengono i valori salvati', await p.inputValue('#sc-schema-programma-nome') === 'Agenda Studio' && await p.inputValue('#sc-schema-programma-prezzo') === '0' && await p.inputValue('#sc-schema-programma-categoria') === 'BusinessApplication');
  await p.fill('#sc-schema-programma-licenza', 'javascript:alert(1)');
  await p.fill('#sc-schema-programma-prezzo', 'gratis');
  await Promise.all([p.waitForNavigation(), p.locator('button.btn-primary[data-valore="pubblica"]').click()]);
  const avviso = (await p.locator('body').innerText());
  ok('licenza javascript: e prezzo "gratis" respinti, con il motivo in parole', avviso.includes('I dati per Google in più non sono stati salvati') && avviso.includes('Licenza') && avviso.includes('Prezzo del programma'), avviso.slice(0, 300));
  g = await grafo(anon, '/prova-plume-programma/');
  const sw2 = (g || []).find(x => x['@type'] === 'SoftwareApplication');
  ok('dopo il rifiuto restano i dati di prima', sw2 && sw2.license === 'https://www.gnu.org/licenses/gpl-3.0.html' && sw2.offers.price === '0');
  await p.goto(scheda, { waitUntil: 'networkidle' });
  await p.fill('#sc-schema-programma-nome', '<script>alert(1)</script>Agenda<b>Pro</b>');
  await p.fill('#sc-schema-programma-versione', '"><script>');
  await Promise.all([p.waitForNavigation(), p.locator('button.btn-primary[data-valore="pubblica"]').click()]);
  ok('una versione con virgolette e tag è respinta', (await p.locator('body').innerText()).includes('Versione'));
  await p.goto(scheda, { waitUntil: 'networkidle' });
  await p.fill('#sc-schema-programma-nome', '<script>alert(1)</script>Agenda<b>Pro</b>');
  await Promise.all([p.waitForNavigation(), p.locator('button.btn-primary[data-valore="pubblica"]').click()]);
  const h = await testo(anon, '/prova-plume-programma/');
  g = await grafo(anon, '/prova-plume-programma/');
  const sw3 = (g || []).find(x => x['@type'] === 'SoftwareApplication');
  ok('i tag nel nome si tolgono: nel JSON-LD solo il testo, nessuno <script> in più nella pagina', sw3 && sw3.name === 'alert(1)AgendaPro' && !h.includes('<script>alert(1)'), sw3 && sw3.name);
  // telefono
  const tel = await br.newContext({ viewport: { width: 390, height: 844 } }); const pt = await tel.newPage();
  await pt.goto(B + '/admin/login.php'); await pt.fill('#username', UTENTE); await pt.fill('#password', PASSWORD);
  await Promise.all([pt.waitForNavigation(), pt.click('button[type=submit]')]);
  await pt.goto(scheda, { waitUntil: 'networkidle' });
  const largo = await pt.evaluate(() => document.documentElement.scrollWidth);
  ok('al telefono (390 px) la scheda con il Programma non scorre di lato', largo <= 392, largo);
  await foto(pt, 'programma-telefono');
  await tel.close();
  // tornare a Nessuno: la pagina esce senza il nodo
  await p.goto(scheda, { waitUntil: 'networkidle' });
  await p.selectOption('#sc-schema-tipo', '');
  await Promise.all([p.waitForNavigation(), p.locator('button.btn-primary[data-valore="pubblica"]').click()]);
  g = await grafo(anon, '/prova-plume-programma/');
  ok('con "Nessuno" il nodo SoftwareApplication sparisce', g && !g.some(x => x['@type'] === 'SoftwareApplication'));
  ok('nessun errore JavaScript nel pannello', err.length === 0, err);

  // === 3. Pagine dai pacchetti con i campi SEO
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'seoplume-'));
  const albero = { versione: 1, elementi: [{ id: 'ps01', tipo: 'sezione', impostazioni: {}, figli: [{ id: 'ps02', tipo: 'colonna', impostazioni: {}, figli: [{ id: 'ps03', tipo: 'testo', impostazioni: { contenuto: '<p>Pagina importata.</p>' } }] }] }] };
  const j = { slug: 'prova-plume-import', titolo: 'Pagina importata', struttura: albero, seo_titolo: 'Pagina importata con i campi SEO', seo_descrizione: 'Una pagina che arriva da un pacchetto con In breve, domande, parola chiave e dati per Google.',
    tldr: 'In breve <b>della</b> pagina importata.', keyword: 'pagina importata', faq: [{ domanda: 'Una domanda <i>vera</i>?', risposta: 'La sua risposta.' }, { domanda: 'Vuota', risposta: '' }],
    seo_schema: { tipo: 'programma', programma: { nome: 'Prova import', versione: '{versione:plumecms-ultima}', categoria: 'WebApplication', prezzo: '0', licenza: 'https://www.gnu.org/licenses/gpl-3.0.html', download: '/scarica/plumecms-ultima/' } } };
  fs.writeFileSync(path.join(tmp, 'a.json'), JSON.stringify(j));
  const imp = cli('importa', path.join(tmp, 'a.json'));
  ok('la pagina con tldr, faq, keyword e seo_schema si importa', !imp.errore && /creata|aggiornata/.test(imp.esito), imp);
  const ri = cli('pagina', 'prova-plume-import');
  const faqi = JSON.parse(ri.faq || '[]');
  ok('In breve senza tag, parola chiave, una sola domanda (la vuota scartata) senza tag', ri.tldr === 'In breve della pagina importata.' && ri.keyword_target === 'pagina importata' && faqi.length === 1 && faqi[0].domanda === 'Una domanda vera?', ri);
  g = await grafo(anon, '/prova-plume-import/');
  const swi = (g || []).find(x => x['@type'] === 'SoftwareApplication'), fq = (g || []).find(x => x['@type'] === 'FAQPage');
  ok('nel sito: SoftwareApplication (con la versione dalla serie: vuota senza zip nel Download) e FAQPage dalla scheda', swi && swi.name === 'Prova import' && fq && fq.mainEntity.length === 1, [swi && swi.softwareVersion, fq && fq.mainEntity]);
  const llms2 = await testo(anon, '/llms.txt');
  ok('il llms.txt elenca la pagina importata con il suo In breve', llms2.includes('/prova-plume-import/): In breve della pagina importata.'));
  // seo_schema cattivo: il pacchetto si ferma
  fs.writeFileSync(path.join(tmp, 'b.json'), JSON.stringify({ ...j, slug: 'prova-plume-import2', seo_schema: { tipo: 'programma', programma: { nome: 'X', prezzo: 'gratis', licenza: 'javascript:alert(1)' } } }));
  const imp2 = cli('importa', path.join(tmp, 'b.json'));
  ok('una pagina con seo_schema cattivo è respinta, con il motivo, e non nasce', imp2.errore && /dati per Google in più non validi/.test(imp2.errore) && cli('pagina', 'prova-plume-import2').errore, imp2);
  fs.writeFileSync(path.join(tmp, 'c.json'), JSON.stringify({ ...j, seo_schema: { tipo: 'sconosciuto' } }));
  ok('un tipo di dati per Google sconosciuto è respinto', !!cli('importa', path.join(tmp, 'c.json')).errore);
  // senza le chiavi nuove i campi restano
  fs.writeFileSync(path.join(tmp, 'd.json'), JSON.stringify({ slug: 'prova-plume-import', titolo: 'Pagina importata', struttura: albero, seo_titolo: 'Pagina importata con i campi SEO', seo_descrizione: j.seo_descrizione }));
  cli('importa', path.join(tmp, 'd.json'));
  const rd = cli('pagina', 'prova-plume-import');
  ok('reimportata senza tldr, faq, keyword e seo_schema: i campi restano com\'erano', rd.tldr === ri.tldr && rd.faq === ri.faq && rd.keyword_target === ri.keyword_target && rd.seo_schema === ri.seo_schema);

  // === 4. La voce "seo" del manifest
  const zipCon = (nome, manifest) => { const d = path.join(tmp, nome); fs.mkdirSync(d); fs.writeFileSync(path.join(d, 'manifest.json'), JSON.stringify(manifest)); const z = path.join(tmp, nome + '.zip'); execFileSync('python3', ['-c', 'import sys,zipfile,os\nz=zipfile.ZipFile(sys.argv[2],"w")\nz.write(os.path.join(sys.argv[1],"manifest.json"),"manifest.json")\nz.close()', d, z]); return z; };
  const buono = cli('manifest', zipCon('buono', { nome: 'SEO di prova', seo: { titolo: { sito: 'plumeCMS' }, org: { nome: 'Org <b>prova</b>', logo: '/uploads/plumecms/marchio.png', tipo: 'Organization' }, persona: { nome: 'Mario Rossi', ruolo: 'Fondatore' }, schema: { searchaction: 1 } } }));
  ok('la voce "seo" buona si legge, con i tag tolti e il logo con l\'indirizzo davanti', buono.seo && buono.seo.org.nome === 'Org prova' && /^https?:\/\/.+\/uploads\/plumecms\/marchio\.png$/.test(buono.seo.org.logo) && buono.seo.schema.searchaction === 1, buono);
  const cattivi = [
    ['una chiave non ammessa (verifica di Google)', { verifica: { google: 'x' } }],
    ['una chiave non ammessa dentro un gruppo ammesso (persona.sameas)', { persona: { sameas: 'https://x' } }],
    ['un logo javascript:', { org: { logo: 'javascript:alert(1)' } }],
    ['un interruttore che non è 0 o 1', { schema: { searchaction: 'si' } }],
    ['il tipo dell\'organizzazione con caratteri strani', { org: { tipo: 'Org"><script>' } }],
  ];
  for (const [nome, seo] of cattivi) { const r = cli('manifest', zipCon('c' + Math.random().toString(36).slice(2, 8), { nome: 'cattivo', seo })); ok('voce "seo" respinta: ' + nome, !!r.errore, r); }

  // pulizia
  pulisci();
  fs.rmSync(tmp, { recursive: true, force: true });
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
