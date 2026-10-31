// La frase in fondo al sito e il banner del sito ufficiale nella dashboard (1.8.0).
// Sito: la frase "Un nuovo sito plumeCMS" sotto il piede, con il link al sito ufficiale; Impostazioni > Sito pubblico la
// cambia (testo, indirizzo) o la spegne; HTML nel testo e indirizzi javascript: non passano. Dashboard: il banner c'è sempre.
// Alla fine la frase torna com'era all'inizio.
// uso: node prova-firma.js <base del CMS> <utente> <password>
const { chromium } = require('playwright');
const [B, UTENTE, PASSWORD] = process.argv.slice(2);
const UFFICIALE = 'https://plumecms.danilofailli.it';
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 220) : '')); };

(async () => {
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('console', m => { if (m.type() === 'error') err.push(m.text().slice(0, 200)); });
  const vai = u => p.goto(B + u, { waitUntil: 'networkidle' });
  const firma = async (u = '/') => { await vai(u); const l = p.locator('#firma-sito'); return (await l.count()) ? { testo: (await l.innerText()).trim(), href: await l.locator('a').getAttribute('href').catch(() => null), html: await l.innerHTML() } : null; };
  const salva = async (mostra, testo, url) => {
    await vai('/admin/impostazioni.php');
    if (mostra) await p.check('#firma_mostra'); else await p.uncheck('#firma_mostra');
    await p.fill('#firma_testo', testo);
    await p.evaluate(u => { document.querySelector('#firma_url').type = 'text'; document.querySelector('#firma_url').value = u; }, url); // anche quello che il browser rifiuterebbe
    await Promise.all([p.waitForNavigation(), p.click('#firma-salva')]);
    return (await p.locator('.flash').first().innerText().catch(() => '')).trim();
  };

  await vai('/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await vai('/admin/impostazioni.php');
  const inizio = { mostra: await p.isChecked('#firma_mostra'), testo: await p.inputValue('#firma_testo'), url: await p.inputValue('#firma_url') };

  // 1. Il banner della dashboard.
  await vai('/admin/');
  const banner = p.locator('#db-ufficiale');
  ok('dashboard: il banner "Visita il sito ufficiale plumeCMS"', await banner.count() === 1 && /Visita il sito ufficiale plumeCMS/.test(await banner.innerText()));
  ok('il banner: "plumeCMS" porta a ' + UFFICIALE + ' in una scheda nuova', await banner.locator('a').getAttribute('href') === UFFICIALE && await banner.locator('a').innerText() === 'plumeCMS' && await banner.locator('a').getAttribute('target') === '_blank');
  ok('il banner non ha un pulsante per chiuderlo', await banner.locator('button').count() === 0);

  // 2. Di partenza (Impostazioni com'erano): la frase sul sito.
  await salva(true, 'Un nuovo sito plumeCMS', UFFICIALE);
  const f0 = await firma();
  ok('sito: "Un nuovo sito plumeCMS" in fondo, con il link al sito ufficiale', f0 && f0.testo === 'Un nuovo sito plumeCMS' && f0.href === UFFICIALE, f0);
  const dopoPiede = await p.evaluate(() => { const f = document.getElementById('firma-sito'); const piede = document.querySelector('footer'); return !!piede && !!(piede.compareDocumentPosition(f) & Node.DOCUMENT_POSITION_FOLLOWING); });
  ok('la frase sta sotto il piede', dopoPiede);
  const f0b = await firma('/contatti/');
  ok('anche nelle altre pagine (/contatti/)', f0b && f0b.href === UFFICIALE, f0b);
  const leggibile = await p.evaluate(() => { const e = document.querySelector('#firma-sito a'); const s = getComputedStyle(e); return parseFloat(s.fontSize) >= 12 && s.visibility !== 'hidden' && e.getBoundingClientRect().height > 0; });
  ok('la frase si vede (non nascosta, carattere almeno 12 px)', leggibile);

  // 3. Si cambia.
  const m1 = await salva(true, 'Fatto con amore e plumeCMS', 'https://www.esempio.it/');
  const f1 = await firma();
  ok('Impostazioni: testo e indirizzo nuovi sul sito', /salvata/.test(m1) && f1 && f1.testo === 'Fatto con amore e plumeCMS' && f1.href === 'https://www.esempio.it/', { m1, f1 });
  // 4. Senza indirizzo: solo il testo.
  await salva(true, 'Solo testo', '');
  const f2 = await firma();
  ok('senza indirizzo: la frase esce senza link', f2 && f2.testo === 'Solo testo' && f2.href === null, f2);
  // 5. HTML e indirizzi cattivi.
  await salva(true, '<img src=x onerror=alert(1)>Ciao <b>forte</b>', 'javascript:alert(1)');
  const f3 = await firma();
  ok('HTML nel testo tolto, javascript: sostituito dal sito ufficiale', f3 && f3.testo === 'Ciao forte' && !/<img|onerror|<b>/.test(f3.html) && f3.href === UFFICIALE, f3);
  await salva(true, 'Prova', 'https://x.it/"><script>alert(1)</script>');
  const f4 = await firma();
  ok('un indirizzo con virgolette e script non passa', f4 && f4.href === UFFICIALE && !/script/.test(f4.html), f4);
  // 6. Spenta.
  const m5 = await salva(false, 'Un nuovo sito plumeCMS', UFFICIALE);
  ok('spenta: sul sito non c\'è più', /spenta/.test(m5) && await firma() === null, m5);
  await vai('/admin/impostazioni.php');
  ok('spenta, il pannello ricorda testo e indirizzo', !(await p.isChecked('#firma_mostra')) && await p.inputValue('#firma_testo') === 'Un nuovo sito plumeCMS');

  // Com'era all'inizio.
  await salva(inizio.mostra, inizio.testo, inizio.url);
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
