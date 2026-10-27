// Download manager (1.2.4, 27 ottobre 2026): pagina Download del pannello, widget Download, conteggio, come un utente.
// Carica un PDF con titolo, descrizione e versione; prova i caricamenti che devono essere rifiutati; modifica il titolo;
// mette il widget in una pagina (prove/pagina-download.php); guarda la pagina da visitatore; scarica e controlla che il
// conteggio salga per una persona e non per un robot; elimina il file e controlla che sparisca dal sito.
// Serve un plumeCMS 1.2.4 con il file manuale-tecnico in Download (il sito di plumeCMS lo ha).
// uso: node prova-download.js <base> <cartella CMS installato> <utente> <password>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 220) : '')); };
const PERSONA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

// I file da caricare, scritti al momento in una cartella temporanea.
const T = fs.mkdtempSync(path.join(os.tmpdir(), 'dl-'));
const scrivi = (nome, dati) => { const p = path.join(T, nome); fs.writeFileSync(p, dati); return p; };
const pdf = scrivi('Listino 2026.pdf', '%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n' + 'x'.repeat(3000));
const finto = scrivi('finto.pdf', 'Non sono un PDF, sono testo con un nome bugiardo.');
const php = scrivi('attacco.php', '<?php echo "no"; ?>');
const doppio = scrivi('listino-2026.zip', 'PK\x03\x04' + 'x'.repeat(100));

(async () => {
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 1440, height: 950 }, userAgent: PERSONA })).newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('dialog', d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok('login', !p.url().includes('login.php'), p.url());

  await p.goto(B + '/admin/scaricabili.php', { waitUntil: 'networkidle' });
  ok('la voce Download è nel menu', await p.locator('.admin-nav a[href="scaricabili.php"]').count() === 1);
  const carica = async (file, titolo = '', sostituisci = false) => {
    await p.goto(B + '/admin/scaricabili.php', { waitUntil: 'networkidle' });
    await p.setInputFiles('#dl-carica input[name=file]', file);
    if (titolo) { await p.fill('#dl-carica input[name=titolo]', titolo); await p.fill('#dl-carica textarea[name=descrizione]', 'I prezzi aggiornati, una pagina.'); await p.fill('#dl-carica input[name=versione]', '2.0'); }
    if (sostituisci) await p.check('#dl-carica input[name=sostituisci]');
    await Promise.all([p.waitForNavigation(), p.click('#dl-carica button[type=submit]')]);
    return (await p.locator('body').innerText());
  };
  let t = await carica(pdf, 'Listino 2026');
  ok('PDF caricato, indirizzo /scarica/listino-2026/', /Caricato: listino-2026\.pdf/.test(t) && /\/scarica\/listino-2026\//.test(t), t.slice(0, 200));
  ok('nell\'elenco con titolo e versione', await p.locator('#dl-listino-2026', { hasText: 'Listino 2026' }).count() === 1 && await p.locator('#dl-listino-2026 .badge', { hasText: '2.0' }).count() === 1);
  t = await carica(pdf);
  ok('stesso nome senza "Sostituisci": rifiutato', /C'è già un file listino-2026\.pdf/.test(t));
  t = await carica(pdf, '', true);
  ok('stesso nome con "Sostituisci": versione nuova accettata', /Caricato: listino-2026\.pdf/.test(t));
  ok('dopo la sostituzione titolo, descrizione e versione restano', await p.locator('#dl-listino-2026', { hasText: 'I prezzi aggiornati' }).count() === 1 && await p.locator('#dl-listino-2026 .badge', { hasText: '2.0' }).count() === 1);
  t = await carica(finto);
  ok('un testo chiamato .pdf: rifiutato', /Il contenuto non è quello di un file \.pdf/.test(t));
  t = await carica(php);
  ok('un file .php: rifiutato', /Tipo di file non ammesso/.test(t));
  ok('e non è finito nella cartella', !fs.existsSync(path.join(DIR, 'uploads', 'scaricabili', 'attacco.php')));
  t = await carica(doppio);
  ok('listino-2026.zip accanto a listino-2026.pdf: rifiutato (stesso indirizzo)', /due file con lo stesso nome/.test(t));

  // Modifica del titolo.
  await p.goto(B + '/admin/scaricabili.php', { waitUntil: 'networkidle' });
  await p.locator('#dl-listino-2026 summary', { hasText: 'Modifica' }).click();
  await p.fill('#dl-listino-2026 input[name=titolo]', 'Listino prezzi 2026');
  await Promise.all([p.waitForNavigation(), p.locator('#dl-listino-2026 button', { hasText: 'Salva' }).click()]);
  ok('titolo cambiato dal pannello', await p.locator('#dl-listino-2026', { hasText: 'Listino prezzi 2026' }).count() === 1);

  // Il widget in una pagina, e nell'editor la tendina dei file.
  const id = execFileSync('php', [path.join(__dirname, 'pagina-download.php'), DIR], { encoding: 'utf8' }).trim();
  await p.goto(`${B}/admin/editor.php?tipo=pagine&id=${id}`, { waitUntil: 'networkidle' });
  const tela = p.frameLocator('iframe').first();
  ok('nell\'editor il widget mostra i file', await tela.locator('.ed-dl-voce', { hasText: 'Listino prezzi 2026' }).count() >= 1);
  await tela.locator('[data-ed-tipo="download"]').first().click(); await p.waitForTimeout(400);
  await p.click('.ed-schede-pannello button[data-pannello="modifica"]');
  await p.locator('#ed-controlli .ed-voce-testa b').first().click(); await p.waitForTimeout(300); // apre la prima voce del ripetitore
  const opzioni = await p.locator('#ed-controlli select').evaluateAll(ss => ss.flatMap(s => [...s.options].map(o => o.textContent)));
  ok('la tendina "File" elenca i file del pannello', opzioni.some(o => /Listino prezzi 2026 \(PDF/.test(o)), opzioni.filter(o => /\(/.test(o)).slice(0, 4));

  // Il sito, da visitatore.
  const v = await (await br.newContext({ viewport: { width: 1280, height: 900 }, userAgent: PERSONA })).newPage();
  const errV = []; v.on('pageerror', e => errV.push(e.message)); v.on('console', m => { if (m.type() === 'error' && !/favicon/.test(m.text())) errV.push(m.text()); });
  await v.goto(B + '/prova-download/', { waitUntil: 'networkidle' });
  const primo = v.locator('.ed-dl-elenco .ed-dl-voce').first();
  ok('sito: titolo, descrizione, tipo e peso, versione', /Listino prezzi 2026/.test(await primo.innerText()) && /I prezzi aggiornati/.test(await primo.innerText()) && /PDF · \d+ KB/.test(await primo.innerText()) && /versione 2\.0/.test(await primo.innerText()), await primo.innerText());
  ok('sito: il titolo scritto nel widget vince su quello del pannello', await v.locator('.ed-dl-titolo', { hasText: 'Il manuale, titolo dal widget' }).count() === 1);
  ok('sito: pulsante verso /scarica/listino-2026/', await primo.locator('a.ed-dl-btn').getAttribute('href') === '/scarica/listino-2026/');
  ok('sito: le schede degli ultimi caricati, con il conteggio', await v.locator('.ed-dl-schede .ed-dl-voce').count() === 3 && /0 download/.test(await v.locator('.ed-dl-schede').innerText()));
  ok('sito: il CSS del widget è caricato', await v.locator('link[href*="download.css"]').count() === 1);
  const icona = await primo.locator('.ed-dl-icona').evaluate(e => getComputedStyle(e).display);
  ok('sito: icona del tipo di file', icona === 'grid' && await primo.locator('.ed-dl-icona svg').count() === 1);

  // Il conteggio: una persona conta, un robot no.
  const scarica = async (ua) => { const c = await br.newContext({ userAgent: ua }); const r = await c.request.get(B + '/scarica/listino-2026/'); await c.close(); return r.status(); };
  ok('una persona scarica (200)', await scarica(PERSONA) === 200);
  await scarica('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
  await v.reload({ waitUntil: 'networkidle' });
  const conta = await v.locator('.ed-dl-schede .ed-dl-voce', { hasText: 'Listino prezzi 2026' }).innerText();
  ok('conteggio: 1 download (la persona sì, Googlebot no)', /\b1 download/.test(conta), conta);
  await p.goto(B + '/admin/scaricabili.php', { waitUntil: 'networkidle' });
  ok('il pannello dice 1 volta', (await p.locator('#dl-listino-2026 .dl-conteggio').innerText()).trim() === '1');

  // Eliminare.
  await Promise.all([p.waitForNavigation(), p.locator('#dl-listino-2026 button', { hasText: 'Elimina' }).click()]);
  ok('eliminato dal pannello', await p.locator('#dl-listino-2026').count() === 0 && !fs.existsSync(path.join(DIR, 'uploads', 'scaricabili', 'listino-2026.pdf')));
  await v.reload({ waitUntil: 'networkidle' });
  ok('sparito dal sito, senza messaggi di errore', !/Listino prezzi 2026|File non trovato/.test(await v.locator('main').innerText()));
  ok('/scarica/listino-2026/ ora risponde 404', (await v.request.get(B + '/scarica/listino-2026/')).status() === 404);
  ok('nessun errore JavaScript', err.length === 0 && errV.length === 0, [...err, ...errV]);
  await br.close();
  fs.rmSync(T, { recursive: true, force: true });
  console.log(`\n${n - ko} su ${n} OK`);
})();
