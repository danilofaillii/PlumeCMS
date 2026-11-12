// Prove di attacco dei tag dei contatti (2.23.0). Ogni OK è un attacco respinto. Va lanciata dopo prova-tag.js, sullo stesso
// sito da buttare (contatti @tag-prova.it, il form "TG Iscrizione", le automazioni "TG ...").
//   T1 anonimo e T2 redattore fuori da pagina Tag, scheda del contatto, azioni di gruppo
//   T3 CSRF e Origin di un altro sito
//   T4 HTML e script nei nomi dei tag (scheda, gruppo, pagina Tag, form, importazione): salvati senza HTML, usciti come testo
//   T5 caratteri a 4 byte (emoji) con MySQL in modalità stretta; T6 nomi lunghissimi; T7 migliaia di tag
//   T8 SQL injection nei filtri, nelle condizioni dei segmenti, nell'importazione; T9 id inventati in segmenti e automazioni
//   T10 CSV dei contatti con un tag che sembra una formula
// uso: CMS_DIR non serve: node prove/attacchi-tag.js <base> <cartella del CMS installato>
const { chromium } = require('playwright');
const path = require('path');
const { execFileSync } = require('child_process');
const C = require('./addon-b-comune.js');
const { ok } = C;
const [B, CMS] = process.argv.slice(2);
const cli = (...a) => { const o = execFileSync('php', [path.join(__dirname, 'tag-cli.php'), CMS, ...a.map(String)], { encoding: 'utf8' }); return JSON.parse(o.trim().split('\n').pop()); };
const sql = q => cli('sql', q);
const n = q => +sql(q)[0].n;
const XSS = '<img src=x onerror=alert(1)><script>alert(2)</script>"\'>';
const nomi = () => sql('SELECT nome FROM {p}newsletter_tag').map(x => x.nome);

(async () => {
  const br = await chromium.launch();
  const err = [];
  const a = await C.pagina(br, err);
  a.setDefaultTimeout(15000);
  let alert = 0;
  a.on('dialog', d => { alert++; d.dismiss(); });
  await C.entra(a, B);
  const c1 = +sql(`SELECT id FROM {p}newsletter_iscritti WHERE email = 'tg1@tag-prova.it'`)[0].id;
  ok('ci sono i contatti della prova (prova-tag.js prima)', c1 > 0);
  const tag0 = n('SELECT COUNT(*) n FROM {p}newsletter_tag');

  // ================= T1 anonimo
  const pagine = ['/admin/newsletter-tag.php', '/admin/newsletter-contatto.php?id=' + c1, '/admin/newsletter-contatti.php?tag=1'];
  let login = 0;
  for (const u of pagine) { const r = await fetch(B + u, { redirect: 'manual' }); if (r.status >= 300 && r.status < 400 && /login\.php/.test(r.headers.get('location') || '')) login++; }
  ok('T1 anonimo: pagina Tag, scheda e Contatti filtrati portano al login', login === pagine.length, login);
  await fetch(B + '/admin/newsletter-tag.php', { method: 'POST', redirect: 'manual', headers: { 'content-type': 'application/x-www-form-urlencoded' }, body: 'azione=crea&nome=Anonimo' });
  await fetch(B + '/admin/newsletter-contatto.php', { method: 'POST', redirect: 'manual', headers: { 'content-type': 'application/x-www-form-urlencoded' }, body: `azione=tag_aggiungi&id=${c1}&tag=Anonimo` });
  ok('T1 anonimo: i POST non creano né danno tag', !nomi().includes('Anonimo'));

  // ================= T2 redattore
  await a.goto(B + '/admin/utenti.php', { waitUntil: 'networkidle' });
  await a.fill('form.sc-corpo [name=username]', 'red_tag'); await a.fill('form.sc-corpo [name=email]', 'red_tag@esempio.it');
  await a.selectOption('form.sc-corpo [name=role]', 'editor'); await a.fill('form.sc-corpo [name=password]', 'Redattore-Tag-2026'); await a.fill('form.sc-corpo [name=password2]', 'Redattore-Tag-2026');
  await Promise.all([a.waitForNavigation(), a.click('form.sc-corpo button.btn-primary')]);
  const red = await C.pagina(br, err);
  await C.entra(red, B, 'red_tag', 'Redattore-Tag-2026');
  let fuori = 0;
  for (const u of pagine) { const r = await red.goto(B + u); if (r.status() >= 400 || !red.url().includes(u.split('?')[0])) fuori++; }
  ok('T2 redattore: non apre pagina Tag, scheda, Contatti', fuori === pagine.length, fuori);
  ok('T2 redattore: nel menu non c\'è la voce Tag della newsletter', await red.locator('nav.admin-nav a[href="newsletter-tag.php"]').count() === 0);
  const tokR = await red.getAttribute('meta[name=pn-csrf]', 'content').catch(() => '');
  await red.request.post(B + '/admin/newsletter-tag.php', { form: { _csrf: tokR, azione: 'crea', nome: 'Dal redattore' }, maxRedirects: 0 });
  await red.request.post(B + '/admin/newsletter-tag.php', { form: { _csrf: tokR, azione: 'elimina', id: '1' }, maxRedirects: 0 });
  await red.request.post(B + '/admin/newsletter-contatti.php', { form: { _csrf: tokR, azione: 'gruppo', cosa: 'tag_aggiungi', tag_nomi: 'Dal redattore', tutti: '1' }, maxRedirects: 0 });
  ok('T2 redattore: con il suo token non crea, non elimina, non dà tag', !nomi().includes('Dal redattore') && n('SELECT COUNT(*) n FROM {p}newsletter_tag WHERE id = 1') === 1);

  // ================= T3 CSRF e Origin
  await a.request.post(B + '/admin/newsletter-tag.php', { form: { azione: 'crea', nome: 'Senza token' }, maxRedirects: 0 });
  await a.request.post(B + '/admin/newsletter-tag.php', { form: { azione: 'elimina', id: '1' }, maxRedirects: 0 });
  await a.request.post(B + '/admin/newsletter-contatto.php', { form: { azione: 'tag_togli', id: String(c1), tag_id: '1' }, maxRedirects: 0 });
  await a.goto(B + '/admin/newsletter-tag.php', { waitUntil: 'networkidle' });
  const tok = await a.getAttribute('meta[name=pn-csrf]', 'content');
  await a.request.post(B + '/admin/newsletter-tag.php', { form: { _csrf: tok, azione: 'crea', nome: 'Da fuori' }, headers: { origin: 'https://cattivo.example' }, maxRedirects: 0 });
  ok('T3 senza token o da un altro sito: niente creato, niente eliminato', !nomi().includes('Senza token') && !nomi().includes('Da fuori') && n('SELECT COUNT(*) n FROM {p}newsletter_tag WHERE id = 1') === 1);
  const post = (u, campi) => a.request.post(B + u, { form: { _csrf: tok, ...campi }, maxRedirects: 0 });

  // ================= T4 HTML e script
  await post('/admin/newsletter-tag.php', { azione: 'crea', nome: 'Pagina ' + XSS });
  await post('/admin/newsletter-contatto.php', { azione: 'tag_aggiungi', id: String(c1), tag: 'Scheda ' + XSS });
  await post('/admin/newsletter-contatti.php', { azione: 'gruppo', cosa: 'tag_aggiungi', tag_nomi: 'Gruppo ' + XSS, 'sel[]': String(c1) });
  const html = nomi().filter(x => /Pagina|Scheda|Gruppo/.test(x));
  ok('T4 i nomi si salvano senza HTML (niente <, >, script, onerror come tag)', html.length >= 3 && html.every(x => !/[<>]/.test(x)), html);
  for (const u of ['/admin/newsletter-tag.php', '/admin/newsletter-contatto.php?id=' + c1, '/admin/newsletter-contatti.php', '/admin/newsletter-contatti.php?filtro=1', '/admin/newsletter-flussi.php?nuovo=1', '/admin/newsletter-segmenti.php?nuovo=1&modello=vuoto']) await a.goto(B + u, { waitUntil: 'networkidle' });
  await a.goto(B + '/admin/newsletter-flussi.php?nuovo=1', { waitUntil: 'networkidle' });
  await a.selectOption('#fi-t0', 'tag');
  ok('T4 nessun alert in pagina Tag, scheda, Contatti, filtro, tela delle automazioni', alert === 0, alert);
  const pagTag = await (await a.request.get(B + '/admin/newsletter-tag.php')).text();
  ok('T4 nella pagina Tag i nomi escono come testo', !/<img src=x onerror/i.test(pagTag) && !/<script>alert/.test(pagTag));

  // ================= T5 4 byte, T6 lunghissimi, T7 migliaia
  await post('/admin/newsletter-contatto.php', { azione: 'tag_aggiungi', id: String(c1), tag: '\u{1F600}\u{1F680} Emoji' });
  ok('T5 un tag con le emoji si salva (entità nel database a tre byte) e torna emoji', cli('tag_di', 'tg1@tag-prova.it').includes('\u{1F600}\u{1F680} Emoji') && nomi().some(x => x === '&#x1F600;&#x1F680; Emoji'), cli('tag_di', 'tg1@tag-prova.it'));
  await post('/admin/newsletter-contatto.php', { azione: 'tag_aggiungi', id: String(c1), tag: 'L'.repeat(100000) });
  ok('T6 un nome lunghissimo: tagliato a 60 caratteri, nessun errore', nomi().includes('L'.repeat(60)) && !nomi().some(x => x.length > 255));
  const r7 = await post('/admin/newsletter-contatto.php', { azione: 'tag_aggiungi', id: String(c1), tag: [...Array(3000)].map((_, i) => 'Raffica ' + i).join(',') });
  ok('T7 3000 tag in un colpo: se ne prendono al massimo 50', r7.status() < 500 && n("SELECT COUNT(*) n FROM {p}newsletter_tag WHERE nome LIKE 'Raffica %'") === 50);
  for (let i = 0; i < 4; i++) await post('/admin/newsletter-contatto.php', { azione: 'tag_aggiungi', id: String(c1), tag: [...Array(50)].map((_, j) => `Ancora ${i} ${j}`).join(',') });
  ok('T7 un contatto ha al massimo 200 tag', n(`SELECT COUNT(*) n FROM {p}newsletter_contatti_tag WHERE contatto_id = ${c1}`) === 200);
  const molti = cli('tag_molti', 6000);
  ok('T7 al massimo 5000 tag in tutto: oltre, un messaggio in parole', n('SELECT COUNT(*) n FROM {p}newsletter_tag') === 5000 && /5000 tag: è il massimo/.test(String(molti.ultimo)), molti);
  sql("DELETE FROM {p}newsletter_tag WHERE nome LIKE 'Molti %'");

  // ================= T8 SQL injection, T9 id inventati
  const tabelle = n("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema = DATABASE()");
  let ko500 = 0;
  for (const u of ["/admin/newsletter-contatti.php?tag=1%20OR%201=1", "/admin/newsletter-contatti.php?tag=1'%3BDROP%20TABLE%20x--", "/admin/newsletter-contatti.php?tag[]=1", '/admin/newsletter-tag.php?id=1%27']) { const r = await a.request.get(B + u); if (r.status() >= 500) ko500++; }
  const filtro = await a.request.get(B + '/admin/newsletter-contatti.php?tag=1%20OR%201=1');
  const tot = n('SELECT COUNT(*) n FROM {p}newsletter_iscritti'), conTag1 = n('SELECT COUNT(*) n FROM {p}newsletter_contatti_tag WHERE tag_id = 1');
  ok('T8 «tag=1 OR 1=1» vale come il tag 1, non come tutti; nessun 500', ko500 === 0 && (await filtro.text()).split('class="nlx-sel"').length - 1 === Math.min(conTag1, 50) && conTag1 < tot, [ko500, conTag1, tot]);
  const conta = v => cli('conta', JSON.stringify({ op: 'e', gruppi: [{ op: 'e', condizioni: [{ campo: 'tag', op: 'qualsiasi', valore: v }] }] }));
  ok('T8 condizione dei segmenti con SQL nel valore: rifiutata', /scegli almeno un tag/.test(conta(["1) OR (1=1", "0 UNION SELECT 1"]).errore || ''));
  ok('T9 condizione con un tag che non esiste: rifiutata', /scegli almeno un tag/.test(conta([987654]).errore || ''));
  const opCattivo = cli('conta', JSON.stringify({ op: 'e', gruppi: [{ op: 'e', condizioni: [{ campo: 'tag', op: 'qualsiasi) OR (1', valore: [1] }] }] }));
  ok('T8 operatore inventato: rifiutato', /scegli la condizione/.test(opCattivo.errore || ''));
  const nodo = (i, tipo, dati = {}) => ({ id: i, tipo, x: 40, y: 40, dati });
  const fl = cli('salva_flusso', JSON.stringify({ nome: 'TG attacco', stato: 'in_pausa', inneschi: [{ tipo: 'tag', id: 0, tag: ['1 OR 1=1', 'x', -5, 1] }], grafo: { nodi: [nodo(0, 'innesco'), nodo(1, 'tag_aggiungi', { tag: [987654, '1; DROP'] })], archi: [{ da: 0, uscita: 'poi', a: 1 }] } }));
  ok('T9 azione con tag inventati: non si salva', /scegli almeno un tag/.test(fl.errore || ''), fl);
  const fl2 = cli('salva_flusso', JSON.stringify({ nome: 'TG attacco 2', stato: 'in_pausa', inneschi: [{ tipo: 'tag', id: 0, tag: ['1 OR 1=1', 'x', -5, 1] }], grafo: { nodi: [nodo(0, 'innesco'), nodo(1, 'tag_aggiungi', { tag: [1] })], archi: [{ da: 0, uscita: 'poi', a: 1 }] } }));
  const inn = JSON.parse(sql(`SELECT inneschi FROM {p}newsletter_flussi WHERE id = ${+fl2}`)[0].inneschi);
  ok('T9 innesco con valori sporchi: restano solo id numerici', JSON.stringify(inn) === '[{"tipo":"tag","id":0,"tag":[1]}]', inn);
  sql(`DELETE FROM {p}newsletter_flussi WHERE id = ${+fl2}`);
  ok('T8 nessuna tabella sparita', n("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema = DATABASE()") === tabelle);

  // ================= T10 CSV con le formule
  cli('tag_aggiungi', 'tg2@tag-prova.it', '=HYPERLINK("https://cattivo.example")');
  const csv = await (await a.request.get(B + '/admin/newsletter-contatti.php?scarica=tutti')).text();
  const riga = csv.split(/\r?\n/).find(r => r.startsWith('tg2@')) || '';
  ok('T10 CSV: il tag che sembra una formula esce con l\'apostrofo davanti', /'=HYPERLINK/.test(riga) && !/;"?=HYPERLINK/.test(riga), riga.slice(-80));
  ok('nessun tag creato dagli attacchi oltre quelli voluti', !nomi().some(x => /Anonimo|Dal redattore|Senza token|Da fuori/.test(x)) && n('SELECT COUNT(*) n FROM {p}newsletter_tag') > tag0);
  C.fine(err);
  await br.close();
})().catch(e => { console.log('ERRORE ' + e.stack); process.exit(1); });
