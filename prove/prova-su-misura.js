// Il sito su misura (1.7.0): installazione da zero con le quattro domande, il sito che ne esce (pagine, menu, foto finte),
// Media > Da sostituire con Sostituisci, la voce dei Primi passi, il sito ricomposto dalla dashboard con risposte nuove.
// Serve una cartella del CMS appena copiata, senza config.local.php, servita su <base>, e MySQL come per prova-install.js.
// Il 404 di /galleria/ (pagina non scelta) è cercato apposta, non è un errore.
// uso: DBPASS=... node prova-su-misura.js <base, es. http://127.0.0.1:8101> <db> [<cartella screenshot>]
const { chromium } = require('playwright');
const [B, DB, OUT = ''] = process.argv.slice(2);
const UTENTE = 'prova', PASSWORD = 'Collaudo-Officina-2026';
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 220) : '')); };

(async () => {
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('console', m => { if (m.type() === 'error' && !(m.location().url || '').endsWith('/galleria/')) err.push(m.text().slice(0, 200) + ' ' + (m.location().url || '')); });
  p.on('dialog', d => d.accept());
  const shot = async nome => { if (OUT) await p.screenshot({ path: `${OUT}/su-misura-${nome}.png`, fullPage: false }); };
  const vai = async u => p.goto(B + u, { waitUntil: 'networkidle' });

  // 1. Installatore, passi 1-3 come prova-install.js.
  await vai('/install/');
  await p.click('a.btn:has-text("Avanti")'); await p.waitForLoadState('networkidle');
  await p.fill('#host', '127.0.0.1:3308'); await p.fill('#nome', DB); await p.fill('#utente', 'danilo'); await p.fill('#password', process.env.DBPASS || '');
  await p.fill('#prefisso', 'cms_'); await p.check('input[name=crea]');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.fill('#nome', 'Trattoria da Prova'); await p.fill('#descrizione', 'Cucina di casa, da provare.'); await p.fill('#email', 'sito@esempio.it');
  await p.fill('#admin_nome', UTENTE); await p.fill('#admin_email', 'prova@esempio.it'); await p.fill('#admin_password', PASSWORD); await p.fill('#admin_password2', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok('passo 4 con la carta "Su misura"', p.url().includes('passo=4') && await p.locator('label.kit-su-misura').count() === 1);

  // 2. La carta Su misura porta alle domande, e Indietro torna alle carte con Su misura ancora scelta.
  await p.click('label.kit-su-misura');
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Installa adesso")')]);
  ok('scelta la carta, le domande (ancora passo 4, niente installato)', (await p.locator('main h2').innerText()) === 'Il tuo sito su misura' && await p.locator('.sm-carta').count() === 10 && await p.locator('input[name="servizi[]"]').count() === 6);
  ok('nessuna attività scelta all\'inizio', await p.locator('.sm-carta input:checked').count() === 0);
  ok('pagine scelte all\'inizio: Chi siamo, Servizi, Galleria, Contatti', JSON.stringify(await p.locator('input[name="pagine[]"]:checked').evaluateAll(l => l.map(x => x.value))) === '["chi-siamo","servizi","galleria","contatti"]');
  await shot('1-domande');
  await Promise.all([p.waitForNavigation(), p.click('a:has-text("Indietro")')]);
  ok('Indietro: le carte, con Su misura scelta', await p.locator('label.kit-su-misura input:checked').count() === 1);
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Installa adesso")')]);

  // 3. Le risposte: ristorante a Bologna, tre servizi (uno con HTML e uno con un'emoji), FAQ in più, Galleria in meno.
  await p.check('.sm-carta input[value=ristorante]');
  await p.fill('input[name=citta]', 'Bologna');
  const servizi = ['Pranzo di lavoro', '<b>Cena</b> degustazione', 'Catering 🍝'];
  for (let i = 0; i < servizi.length; i++) await p.locator('input[name="servizi[]"]').nth(i).fill(servizi[i]);
  await p.check('input[name="pagine[]"][value=faq]'); await p.uncheck('input[name="pagine[]"][value=galleria]');
  await Promise.all([p.waitForNavigation({ timeout: 120000 }), p.click('button:has-text("Installa adesso")')]);
  ok('installazione completata (passo 5)', p.url().includes('passo=5'), p.url());

  // 4. Il sito che ne esce.
  const home = await vai('/');
  ok('home 200', home.status() === 200);
  const menu = (await p.locator('header nav a').allInnerTexts()).map(t => t.trim()).filter(Boolean);
  ok('menu come le risposte: Chi siamo, Servizi, Domande frequenti, Contatti; niente Galleria né Blog', ['Chi siamo', 'Servizi', 'Domande frequenti', 'Contatti'].every(v => menu.includes(v)) && !menu.includes('Galleria') && !menu.includes('Blog'), menu);
  const finte = await p.locator('img[src*="/segnaposto/"]').evaluateAll(l => l.map(i => i.getAttribute('src')));
  ok('la home mostra le foto finte del ristorante', finte.length >= 2 && finte.every(s => /\/segnaposto\/ristorante-\d+-(largo|quadro|ritratto)\.svg$/.test(s)), finte);
  const corpo = await p.locator('body').innerText();
  ok('i servizi scritti nella home, ripuliti (niente HTML, niente emoji)', corpo.includes('Pranzo di lavoro') && corpo.includes('Cena degustazione') && /Catering\n/.test(corpo) && !/<b>Cena<\/b>|&amp;#|🍝/.test(await p.content()));
  ok('Bologna nei testi', corpo.includes('Bologna'));
  await shot('2-home');
  if (OUT) await p.screenshot({ path: `${OUT}/su-misura-2-home-tutta.png`, fullPage: true });
  const img = await p.request.get(B + finte[0]);
  ok('la foto finta si apre ed è un SVG con "Sostituisci questa foto"', img.status() === 200 && (await img.text()).includes('Sostituisci questa foto'));
  ok('/servizi/ con le ancore dei servizi', (await vai('/servizi/')).status() === 200 && await p.locator('#pranzo-di-lavoro').count() === 1);
  ok('/domande-frequenti/ 200', (await vai('/domande-frequenti/')).status() === 200);
  ok('/galleria/ non c\'è (non scelta)', (await vai('/galleria/')).status() === 404);
  const cont = await vai('/contatti/');
  ok('/contatti/ con il form e la mappa di Bologna', cont.status() === 200 && await p.locator('form').count() >= 1 && /Bologna/.test(await p.content()));

  // 5. Il pannello: dashboard, Primi passi, Media.
  await vai('/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok('dashboard: "Stai provando il sito Su misura", carta Su misura in prova', /Su misura/.test(await p.locator('#tipo-sito-titolo').innerText()) && await p.locator('.db-kit-carta.attuale a[data-kit=su-misura]').count() === 1);
  const voce = p.locator('#primi-passi li', { hasText: 'foto finte' });
  ok('Primi passi: "Le tue foto al posto di quelle finte", da fare', await voce.count() === 1 && /Restano \d+ foto finte/.test(await voce.innerText()), await voce.innerText().catch(() => ''));
  await vai('/admin/media.php?cartella=segnaposto');
  const quante = await p.locator('.md-sostituisci').count();
  ok('Media > Da sostituire: le foto finte, ognuna con Sostituisci', quante >= 4 && /foto finte da sostituire/.test(await p.locator('#md-da-sostituire').innerText()), quante);
  await shot('3-media');

  // 6. Sostituisci: la foto della home (la prima) con un PNG vero.
  const finta = finte[0];
  const png = await p.screenshot({ clip: { x: 0, y: 0, width: 320, height: 180 } });
  const form = p.locator('.md-sostituisci', { has: p.locator(`input[name=segnaposto][value="${finta}"]`) });
  ok('la foto finta della home è in Media', await form.count() === 1, finta);
  await Promise.all([p.waitForNavigation(), form.locator('input[type=file]').setInputFiles({ name: 'sala-vera.png', mimeType: 'image/png', buffer: png })]);
  const msg = await p.locator('.flash').first().innerText().catch(() => '');
  ok('Sostituisci: "la foto nuova ha preso il posto di quella finta"', /preso il posto/.test(msg), msg);
  ok('in Media una foto finta in meno', await p.locator('.md-sostituisci').count() === quante - 1);
  ok('il file finto non c\'è più', (await p.request.get(B + finta)).status() === 404);
  await vai('/');
  const dopo = await p.locator('img').evaluateAll(l => l.map(i => i.getAttribute('src')));
  ok('la home mostra la foto vera al posto di quella finta', !dopo.includes(finta) && dopo.some(s => /\/uploads\/pagine\/\d{4}\/\d{2}\/sala-vera-[0-9a-f]+\.png(\.webp)?$/.test(s || '')), dopo.slice(0, 6));
  // Sostituisci rifiuta quello che non è un'immagine finta e quello che non è un'immagine.
  await vai('/admin/media.php?cartella=segnaposto');
  const rifiuti = await p.evaluate(async ({ finta }) => {
    const csrf = document.querySelector('input[name=_csrf]').value;
    const manda = async (segnaposto, nome, tipo, testo) => {
      const f = new FormData(); f.append('_csrf', csrf); f.append('azione', 'sostituisci'); f.append('segnaposto', segnaposto);
      f.append('file', new Blob([testo], { type: tipo }), nome);
      const r = await fetch('media.php?cartella=segnaposto', { method: 'POST', body: f, credentials: 'include' });
      return (await r.text()).match(/class="flash[^"]*"[^>]*>([^<]*)/)?.[1] || '';
    };
    const altra = document.querySelector('.md-sostituisci input[name=segnaposto]').value;
    return [
      await manda(finta.replace(/ristorante-\d+/, '../../includes/config.local'), 'x.png', 'image/png', 'x'),
      await manda('/uploads/pagine/qualunque.png', 'x.png', 'image/png', 'x'),
      await manda(altra, 'pagina.php', 'image/png', '<?php echo 1; ?>'),
    ];
  }, { finta });
  ok('Sostituisci rifiuta un percorso fuori dalle foto finte e un file che non è un\'immagine', /Si sostituiscono solo/.test(rifiuti[0]) && /Si sostituiscono solo/.test(rifiuti[1]) && /Formati accettati/.test(rifiuti[2]), rifiuti);

  // 7. Dalla dashboard: risposte nuove, il sito si ricompone.
  await vai('/admin/');
  await Promise.all([p.waitForNavigation(), p.click('.db-kit-carta a[data-kit=su-misura]')]);
  ok('la pagina del pannello riapre le risposte di prima', await p.inputValue('input[name=citta]') === 'Bologna' && await p.locator('.sm-carta input[value=ristorante]').isChecked() && await p.locator('input[name="servizi[]"]').nth(1).inputValue() === 'Cena degustazione');
  await shot('4-pannello');
  await p.check('input[name="pagine[]"][value=listino]'); await p.check('input[name=ogni_servizio]');
  await Promise.all([p.waitForNavigation({ timeout: 120000 }), p.click('#sm-componi')]);
  const fatto = await p.locator('.flash').first().innerText().catch(() => '');
  ok('ricomposto: torna alla dashboard con "Il sito su misura è pronto" e la copia del sito', /Il sito su misura è pronto/.test(fatto) && /copia del sito/.test(fatto), fatto);
  const tenuti = await p.locator('#tipo-sito-tenuti').innerText().catch(() => '');
  ok('la home con la foto vera resta quella della persona', /home/i.test(tenuti), tenuti);
  ok('/listino/ ora c\'è', (await vai('/listino/')).status() === 200);
  ok('una pagina per ogni servizio: /pranzo-di-lavoro/', (await vai('/pranzo-di-lavoro/')).status() === 200 && /Pranzo di lavoro/.test(await p.locator('h1').first().innerText()));
  const sotto = await p.locator('header nav a').allInnerTexts();
  ok('menu: i servizi sotto Servizi, e il listino (per un ristorante si chiama "Il menù")', sotto.some(t => /Cena degustazione/.test(t)) && sotto.some(t => /Il menù/.test(t)), sotto);
  await vai('/');
  ok('la home tenuta mostra ancora la foto vera', (await p.locator('img').evaluateAll(l => l.map(i => i.getAttribute('src')))).some(s => /sala-vera-/.test(s || '')));

  // 8. Definitivo: la pagina del pannello non ricompone più.
  await vai('/admin/');
  await Promise.all([p.waitForNavigation(), p.click('#kit-definitivo')]);
  await vai('/admin/sito-su-misura.php');
  ok('dopo Definitivo la pagina dice che il sito non si ricompone più', await p.locator('#sm-modulo').count() === 0 && /definitivo/.test(await p.locator('#sito-su-misura').innerText()));
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
