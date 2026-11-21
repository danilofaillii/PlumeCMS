const { chromium } = require('playwright');
// 2.6.0: Download con un server dal limite basso. Avvio: php -d upload_max_filesize=1M -d post_max_size=2M -S ...
// uso: node prova-download-limite.js <base> [utente] [password]   (sito col kit; scrive i file di prova in una cartella temporanea)
const B = process.argv[2] || 'http://127.0.0.1:8702', U = process.argv[3] || 'prova', PW = process.argv[4] || 'Collaudo-Officina-2026';
const fs = require('fs'), os = require('os'), path = require('path'), crypto = require('crypto');
const D = fs.mkdtempSync(path.join(os.tmpdir(), 'dl-limite-'));
fs.writeFileSync(D + '/grande.pdf', crypto.randomBytes(1500000)); fs.writeFileSync(D + '/piccolo.pdf', '%PDF-1.4\n%%EOF\n'); fs.writeFileSync(D + '/enorme.zip', crypto.randomBytes(3000000));
let ko = 0, n = 0;
(async () => {
  const b = await chromium.launch(); const p = await b.newPage();
  const ok = (c, t, x='') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (x ? ' ' + JSON.stringify(x).slice(0, 300) : '')); };
  await p.goto(B + '/admin/login.php'); await p.fill('input[name=username]', U); await p.fill('input[name=password]', PW);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/scaricabili.php');
  ok(await p.locator('#dl-riservati h3').innerText() === 'Link dei file riservati', 'giorni in un riquadro loro');
  ok(await p.locator('#dl-carica #dl-giorni-n').count() === 0, 'i giorni non stanno nel modulo Carica');
  await p.setInputFiles('#dl-file', D + '/grande.pdf');
  const m = await p.locator('#dl-file-peso').innerText().catch(() => ''); ok(/pesa 1,4 MB.*fino a 1 MB.*MultiPHP/.test(m), 'file troppo grande detto sotto il campo', m);
  await p.click('#dl-carica button[type=submit]'); await p.waitForTimeout(500);
  ok(p.url().endsWith('/admin/scaricabili.php') && await p.locator('.flash').count() === 0, 'il form non parte');
  await p.setInputFiles('#dl-file', D + '/piccolo.pdf'); ok(await p.locator('#dl-file-peso').count() === 0, 'file piccolo: messaggio tolto');
  await p.check('#dl-riservato');
  await Promise.all([p.waitForNavigation(), p.click('#dl-carica button[type=submit]')]);
  const f = await p.locator('.flash').first().innerText(); ok(/Caricato: piccolo\.pdf\. È riservato/.test(f), 'riservato caricato', f);
  ok(await p.locator('.dl-riservato').count() > 0, 'in elenco con Riservato');
  // invio oltre post_max_size: si salta il controllo del browser
  await p.evaluate(() => document.getElementById('dl-file').removeAttribute('data-peso-massimo'));
  await p.setInputFiles('#dl-file', D + '/enorme.zip');
  await Promise.all([p.waitForNavigation(), p.click('#dl-carica button[type=submit]')]);
  const t = await p.locator('body').innerText();
  ok(!/CSRF/.test(t) && /pesa 2,9 MB.*al massimo 1 MB.*MultiPHP INI Editor/.test(t) && p.url().endsWith('/admin/scaricabili.php'), 'invio troppo grande: messaggio chiaro, niente CSRF', t.slice(0, 200));
  await b.close(); fs.rmSync(D, { recursive: true, force: true });
  console.log(`\n${n - ko} su ${n} OK`); process.exit(ko ? 1 : 0);
})();
