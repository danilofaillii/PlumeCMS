// Pulizia (2.38.0): Impostazioni > Pulizia provata come una persona, su un sito col kit Attività seminato da prove/pulizia-cli.php.
// Controlla: pesi della pagina uguali a quelli calcolati sul disco (fs, come du) e a information_schema; totale; telefono a 390 px senza
// scorrimento di lato; riepilogo vuoto, numeri cattivi, blocco con un ripristino in corso; password richiesta e sbagliata (niente
// tolto); "tieni gli ultimi 3"; resoconto; dopo: quello che va tenuto c'è ancora (config, log protetti, versioni pubblicate e con nota,
// pagine nel cestino recenti, accessi e blocchi del registro, tabelle di un'altra installazione, immagini usate anche solo in
// un'impostazione, in un form, in un tema, forse usate); il sito e il pannello funzionano; l'avviso della dashboard; "togli anche
// l'ultimo snapshot" con la seconda conferma; GET con i parametri non toglie niente.
// uso: node prove/prova-pulizia.js <base, es. http://127.0.0.1:9400 o http://127.0.0.1:9401/test> <cartella del CMS> [utente] [password]
const { chromium } = require('playwright');
const fs = require('fs'), path = require('path'), { execFileSync } = require('child_process');
const [B, DIR, UT = 'prova', PW = 'Collaudo-Officina-2026'] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? '  ' + JSON.stringify(extra).slice(0, 300) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, 'pulizia-cli.php'), DIR, ...a], { encoding: 'utf8' }).trim().split('\n').pop());
const du = (d) => { let t = 0; if (!fs.existsSync(d)) return 0; for (const f of fs.readdirSync(d, { withFileTypes: true })) { const p = path.join(d, f.name); t += f.isDirectory() ? du(p) : fs.statSync(p).size; } return t; };
const PUL = B + '/admin/pulizia.php';

(async () => {
  const semina = cli('semina');
  ok('semina: avanzi finti messi sul sito', semina.ok === true, semina);
  const piani = cli('piani');
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UT); await p.fill('#password', PW);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  // 1. la voce del menu e la pagina
  await p.goto(B + '/admin/impostazioni.php', { waitUntil: 'networkidle' });
  ok('menu: Impostazioni > Pulizia c\'è', await p.locator('a[href="pulizia.php"]').count() > 0);
  await p.goto(PUL, { waitUntil: 'networkidle' });
  ok('la pagina si apre con il titolo "Quanto si può liberare"', await p.locator('h3:has-text("Quanto si può liberare")').count() === 1);
  const voci = await p.$$eval('.pul-voce', l => Object.fromEntries(l.map(x => [x.dataset.voce, { byte: +x.dataset.byte, n: +x.dataset.n }])));
  const diversi = Object.keys(piani).filter(k => k !== 'media_forse' && voci[k] && k !== 'media' && ((!['registri', 'cestino', 'ottimizza'].includes(k) && voci[k].byte !== piani[k].byte) || voci[k].n !== piani[k].n));
  ok('ogni voce ha peso (le stime del database: il numero) e numero uguali al calcolo del server da riga di comando (' + Object.keys(voci).length + ' voci)', diversi.length === 0, diversi.map(k => [k, voci[k], piani[k].byte, piani[k].n]));
  // pesi confrontati con il disco (come du)
  const snapDir = path.join(DIR, 'aggiornamenti', 'snapshot');
  const duSnap = ['20261101-100000-prova-pulizia-1', '20261102-100000-prova-pulizia-2'].reduce((t, s) => t + du(path.join(snapDir, s)), 0);
  ok('snapshot: la proposta "tieni gli ultimi 3" toglie i due più vecchi, peso uguale al disco', voci.snapshot.n === 2 && voci.snapshot.byte === duSnap, [voci.snapshot, duSnap]);
  const copieDir = path.join(DIR, 'aggiornamenti', 'copie');
  const duCopie = ['copia-20261101-080000-prova.zip', 'copia-20261102-080000-prova.zip'].reduce((t, f) => t + fs.statSync(path.join(copieDir, f)).size, 0);
  ok('copie: proposte tutte tranne la più recente, peso uguale al disco', voci.copie.n === 2 && voci.copie.byte === duCopie, [voci.copie, duCopie]);
  const duScarto = du(path.join(DIR, 'aggiornamenti', 'scarto', 'ripristino-20261101-101010')) - fs.statSync(path.join(DIR, 'aggiornamenti', 'scarto', 'ripristino-20261101-101010', 'LEGGIMI.txt')).size;
  ok('file messi da parte: peso uguale al disco (senza il LEGGIMI)', voci.scarto.byte === duScarto, [voci.scarto, duScarto]);
  const totPagina = await p.$eval('#totale .pul-totale b', b => b.textContent.trim());
  const somma = Object.keys(voci).filter(k => !['ottimizza', 'media'].includes(k)).reduce((t, k) => t + voci[k].byte, 0);
  ok('in cima il totale recuperabile è la somma delle voci', totPagina === (somma >= 1048576 ? (somma / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.round(somma / 1024).toLocaleString('it-IT') + ' KB'), [totPagina, somma]);
  const stato0 = cli('stato');
  const pesiPagina = await p.$$eval('[data-tabella-peso]', l => Object.fromEntries(l.map(x => [x.dataset.tabellaPeso, +x.dataset.byte])));
  const pesiDiversi = Object.keys(pesiPagina).filter(t => pesiPagina[t] !== stato0.pesi[t]);
  ok('peso del database tabella per tabella uguale a information_schema (' + Object.keys(pesiPagina).length + ' tabelle, solo col prefisso)', Object.keys(pesiPagina).length > 30 && pesiDiversi.length === 0 && Object.keys(pesiPagina).every(t => t.startsWith('cms_') && !t.startsWith('cms_b_')), pesiDiversi.slice(0, 5));
  ok('tabelle di lavoro: proposte solo le due del sito (non quella dell\'altra installazione cms_b_, non quella senza prefisso)', JSON.stringify(piani.lavoro.chiavi) === JSON.stringify(['rip0a0b0c_cms_finta', 'vec0a0b0c_cms_finta2']), piani.lavoro.chiavi);
  ok('log: mai cron-stato.json né controllo-accesso.txt; recente.log resta', !piani.log.tutti.includes('cron-stato.json') && !piani.log.tutti.includes('controllo-accesso.txt') && !piani.log.chiavi.includes('recente.log') && piani.log.chiavi.includes('fermo.log') && piani.log.chiavi.includes('vecchio.log.1'), piani.log);
  ok('file dei contatti: l\'esportazione in corso e quella recente non sono proposte', piani.contatti.n === 2 && !piani.contatti.chiavi.some(c => /esp-4|esp-3/.test(c)), piani.contatti.chiavi);
  ok('versioni: 10 righe da togliere (30 + una pubblicata + una con la nota, tieni 20)', piani.versioni.righe === 10, piani.versioni);
  ok('cestino: solo la pagina da più di 30 giorni', piani.cestino.n === 1, piani.cestino);
  // 2. telefono
  const tel = await br.newContext({ viewport: { width: 390, height: 800 }, storageState: await ctx.storageState() });
  const pt = await tel.newPage(); await pt.goto(PUL, { waitUntil: 'networkidle' });
  const lato = await pt.evaluate(() => document.documentElement.scrollWidth - innerWidth);
  ok('telefono a 390 px: la pagina non scorre di lato', lato <= 0, lato);
  await pt.screenshot({ path: '/tmp/pulizia-telefono.png', fullPage: false }).catch(() => {});
  await tel.close();

  // 3. immagini: il controllo
  await p.goto(PUL + '?media=1', { waitUntil: 'networkidle' });
  const libere = await p.$$eval('#voce-media input[name="p[media][scelta][]"]', l => l.map(x => x.value));
  const spuntate = await p.$$eval('#voce-media input[name="p[media][scelta][]"]:checked', l => l.length);
  ok('immagini: proposte solo quelle non usate (non-usata.jpg, non-usata-2.png con la miniatura), nessuna spuntata di partenza', JSON.stringify(libere.sort()) === JSON.stringify(['2026/11/non-usata-2.png', '2026/11/non-usata.jpg']) && spuntate === 0, libere);
  const forseTesto = await p.locator('#voce-media details:has-text("Forse usate")').textContent().catch(() => '');
  ok('immagini forse usate (stesso nome altrove, spazio nel nome, solo in una versione vecchia) elencate e non proposte', /forse\.jpg/.test(forseTesto) && /spazio nome\.jpg/.test(forseTesto) && /versione-sola\.png/.test(forseTesto), forseTesto.slice(0, 200));
  ok('usate solo in un\'impostazione, in un form o in un tema: non proposte né forse', !/usata-impostazione|usata-form|usata-tema|usata-pagina/.test(forseTesto) && !libere.some(x => /usata-/.test(x) && !/non-usata/.test(x)));

  // 4. riepilogo vuoto e numero cattivo
  await p.goto(PUL, { waitUntil: 'networkidle' });
  await p.click('button:has-text("Prepara il riepilogo")'); await p.waitForLoadState('networkidle');
  ok('riepilogo senza voci: messaggio "Spunta almeno una voce", niente riepilogo', await p.locator('#pul-errori:has-text("Spunta almeno una voce")').count() === 1 && await p.locator('#riepilogo').count() === 0);
  await p.goto(PUL, { waitUntil: 'networkidle' });
  await p.check('#voce-snapshot input[name="voci[]"]');
  await p.evaluate(() => { const i = document.querySelector('#pul-snapshot-n'); i.removeAttribute('min'); i.value = '-1'; });
  await p.click('button:has-text("Prepara il riepilogo")'); await p.waitForLoadState('networkidle');
  ok('"tieni gli ultimi -1": errore sotto il campo, niente riepilogo, niente tolto', await p.locator('#pul-snapshot-n-errore').count() === 1 && await p.locator('#riepilogo').count() === 0 && cli('stato').snapshot.length === 5);

  // 5. blocco con un ripristino in corso
  cli('marcatore', 'on');
  await p.goto(PUL, { waitUntil: 'networkidle' });
  const bloccoCopie = await p.locator('#voce-copie .pul-blocco').innerText().catch(() => '');
  const bloccoLavoro = await p.locator('#voce-lavoro .pul-blocco').innerText().catch(() => '');
  ok('con un ripristino in corso: copie e tabelle di lavoro bloccate, lo dice e non si possono mettere nel riepilogo', /ripristino/.test(bloccoCopie) && /ripristino/.test(bloccoLavoro) && await p.locator('#voce-copie input[name="voci[]"], #voce-lavoro input[name="voci[]"]').count() === 0, [bloccoCopie.slice(0, 80)]);
  cli('marcatore', 'off');

  // 6. la pulizia vera: tutte le voci, con le proposte di partenza, più un'immagine non usata
  await p.goto(PUL + '?media=1', { waitUntil: 'networkidle' });
  for (const v of ['pacchetti', 'snapshot', 'copie', 'scarto', 'cambiadb', 'log', 'contatti', 'versioni', 'cestino', 'registri', 'lavoro', 'media']) await p.check('#voce-' + v + ' input[name="voci[]"]');
  const conOttimizza = await p.locator('#voce-ottimizza input[name="voci[]"]').count() === 1; // solo se qualche tabella ha spazio vuoto (la semina ne prepara una)
  ok('Ottimizza le tabelle: proposta la tabella con lo spazio vuoto lasciato dalle cancellazioni', conOttimizza && /pulprova_vuota/.test(await p.locator('#voce-ottimizza').textContent()));
  if (conOttimizza) await p.check('#voce-ottimizza input[name="voci[]"]');
  console.log('    (Ottimizza ' + (conOttimizza ? 'nel riepilogo' : 'senza tabelle da ottimizzare') + ')');
  await p.check('#voce-media input[value="2026/11/non-usata.jpg"]');
  await p.click('button:has-text("Prepara il riepilogo")'); await p.waitForLoadState('networkidle');
  const riep = await p.locator('#riepilogo').innerText().catch(() => '');
  ok('riepilogo: "Stai per togliere", le voci, "Per un totale di", la password chiesta', /Stai per togliere/.test(riep) && /Per un totale di/.test(riep) && /Snapshot degli aggiornamenti/.test(riep) && await p.locator('#pul-password').count() === 1, riep.slice(0, 200));
  ok('riepilogo: niente è stato ancora tolto', cli('stato').snapshot.length === 5);
  await p.fill('#pul-password', 'sbagliata-davvero');
  await p.click('button:has-text("Togli adesso")'); await p.waitForLoadState('networkidle');
  const st1 = cli('stato');
  ok('password sbagliata: errore sotto il campo e niente tolto', await p.locator('#pul-password-errore').count() === 1 && st1.snapshot.length === 5 && st1.copie.length === 3 && st1.lavoro.length === 4, [st1.snapshot.length, st1.copie.length]);
  await p.fill('#pul-password', PW);
  await p.click('button:has-text("Togli adesso")'); await p.waitForLoadState('networkidle');
  const reso = await p.locator('#resoconto').innerText().catch(() => '');
  ok('resoconto: "Pulizia fatta: liberati ..." con le voci', /Pulizia fatta: liberati/.test(reso) && /snapshot tolti/.test(reso) && /ottimizzat.*prima .*dopo /.test(reso), reso.slice(0, 300));
  const st = cli('stato');
  ok('snapshot: restano gli ultimi 3', JSON.stringify(st.snapshot) === JSON.stringify(['20261103-100000-prova-pulizia-3', '20261104-100000-prova-pulizia-4', '20261105-100000-prova-pulizia-5']), st.snapshot);
  ok('pacchetti: resta solo quello in attesa', st.pacchetti.filter(f => /vecchio/.test(f)).length === 0 && st.pacchetti.includes('20261103-090000-in-attesa.zip'), st.pacchetti);
  ok('copie: resta la più recente', JSON.stringify(st.copie.filter(f => /^copia-/.test(f))) === JSON.stringify(['copia-20261103-080000-prova.zip']), st.copie);
  ok('file messi da parte: tolti', st.scarto.length === 0, st.scarto);
  ok('cambio database: copia del config e stato tolti, il config vero c\'è ancora', JSON.stringify(st.includes_prima) === JSON.stringify(['config.local.php']) && !st.cambiadb.includes('ultimo.json'), [st.includes_prima, st.cambiadb]);
  ok('log: tolti i vecchi e gli archiviati; protetti e recenti restano; cron-daily.log accorciato a 50 righe con la data di prima', !st.logs.includes('fermo.log') && !st.logs.includes('vecchio.log.1') && ['cron-stato.json', 'controllo-accesso.txt', 'recente.log', 'cron-daily.log'].every(f => st.logs.includes(f)) && st.cron_righe === 50 && st.cron_mtime === stato0.cron_mtime, [st.logs, st.cron_righe, st.cron_mtime, stato0.cron_mtime]);
  ok('file dei contatti: restano il recente e quello in corso; la riga dell\'esportazione tolta non promette più il file', JSON.stringify(st.contatti.filter(f => /\.csv$/.test(f)).sort()) === JSON.stringify(['esp-3-cccccccccccc.csv', 'esp-4-dddddddddddd.csv']) && JSON.stringify(st.esportazioni) === JSON.stringify(['esp-4-dddddddddddd.csv']), [st.contatti, st.esportazioni]);
  ok('versioni: restano 20 più la pubblicata e quella con la nota', st.versioni_900001 === 22 && st.versioni_protette === 2, [st.versioni_900001, st.versioni_protette]);
  ok('cestino: tolta la pagina vecchia, resta la recente', JSON.stringify(st.cestino) === JSON.stringify(['pulizia-recente-cestino']), st.cestino);
  ok('registri: tolte le righe vecchie; accessi e blocchi del registro di sicurezza restano; il blocco in corso resta', st.log_vecchi === 0 && st.log_login === 2 && JSON.stringify(st.tentativi) === JSON.stringify(['pulprova:bloccato']), [st.log_vecchi, st.log_login, st.tentativi]);
  ok('tabelle di lavoro: tolte le due del sito, restano quella dell\'altra installazione e quella senza prefisso', JSON.stringify(st.lavoro.sort()) === JSON.stringify(['rip0a0b0c_cms_b_users', 'ripaaaaaa_altrosito_x']), st.lavoro);
  ok('immagini: tolta solo quella spuntata (con la sua WebP); usate, forse usate e la non spuntata restano', !st.immagini.includes('non-usata.jpg') && !st.immagini.includes('non-usata.jpg.webp') && ['usata-pagina.jpg', 'usata-impostazione.png', 'usata-form.png', 'usata-tema.png', 'forse.jpg', 'spazio nome.jpg', 'versione-sola.png', 'non-usata-2.png', 'non-usata-2-300x300.png'].every(f => st.immagini.includes(f)), st.immagini);
  ok('il registro di sicurezza ha la riga della pulizia', /pulizia/.test(await (await ctx.request.get(B + '/admin/sicurezza.php')).text()));

  // 7. il sito e il pannello dopo
  const pagine = ['/', '/servizi/', '/contatti/', '/blog/'];
  const stati = [];
  for (const u of pagine) { const r = await ctx.request.get(B + u); stati.push(r.status()); }
  ok('il sito risponde dopo la pulizia (' + pagine.join(' ') + ')', stati.every(s => s === 200), stati);
  for (const a of ['index.php', 'aggiornamenti.php', 'copia-sito.php', 'versioni.php', 'contenuti.php?tipo=pagine', 'cambia-db.php']) {
    await p.goto(B + '/admin/' + a, { waitUntil: 'networkidle' });
    if (!(await p.locator('.admin-content').count()) || /Fatal error|ha incontrato un errore/.test(await p.content())) { ok('il pannello si apre dopo la pulizia: ' + a, false); }
  }
  ok('il pannello si apre dopo la pulizia (dashboard, Aggiornamenti, Copia del sito, Versioni, Pagine, Cambia database)', true);

  // 8. "togli anche l'ultimo snapshot": serve la seconda conferma
  await p.goto(PUL, { waitUntil: 'networkidle' });
  await p.check('#voce-snapshot input[name="voci[]"]'); await p.fill('#pul-snapshot-n', '0');
  await p.click('button:has-text("Prepara il riepilogo")'); await p.waitForLoadState('networkidle');
  ok('tieni 0 snapshot: il riepilogo chiede la seconda conferma', await p.locator('input[name="p[snapshot][zero]"]').count() === 1);
  await p.fill('#pul-password', PW); await p.click('button:has-text("Togli adesso")'); await p.waitForLoadState('networkidle');
  ok('senza la seconda conferma: errore e niente tolto', await p.locator('#pul-zero-errore').count() === 1 && cli('stato').snapshot.length === 3);
  await p.check('input[name="p[snapshot][zero]"]'); await p.fill('#pul-password', PW); await p.click('button:has-text("Togli adesso")'); await p.waitForLoadState('networkidle');
  ok('con la seconda conferma: tolti tutti gli snapshot', cli('stato').snapshot.length === 0 && await p.locator('#resoconto').count() === 1);

  // 9. GET con i parametri non toglie niente; l'avviso della dashboard
  const prima = cli('stato');
  await p.goto(PUL + '?azione=esegui&voci[]=copie&voci[]=registri&p[copie][scelta][]=copia-20261103-080000-prova.zip&password_admin=' + encodeURIComponent(PW), { waitUntil: 'networkidle' });
  ok('in GET con i parametri non si toglie niente', JSON.stringify(cli('stato').copie) === JSON.stringify(prima.copie));
  cli('stima', String(300 * 1048576));
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('dashboard: con più di 200 MB recuperabili un avviso con il link alla Pulizia', await p.locator('a[href="pulizia.php"]:has-text("Apri")').count() === 1 || await p.locator('.admin-content a[href="pulizia.php"]').count() >= 1);
  cli('stima', '1024');
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('dashboard: sotto la soglia nessun avviso', await p.locator('.admin-content a[href="pulizia.php"]').count() === 0);
  ok('nessun errore JavaScript', err.length === 0, err);
  cli('via');
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error('ERRORE', e); process.exit(2); });
