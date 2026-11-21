// I sette widget della 1.10.0 (Orari di apertura, Barra contatti, Recensioni, Prima e dopo, Squadra, Slider, Eventi),
// come li vede un visitatore al computer e al telefono, più i calcoli degli orari a giorni e ore fissi e il pannello Widget.
// Prima: php prove/pagina-widget-nuovi.php <cartella CMS> (crea /widget-nuovi/ con i sette widget compilati).
// uso: node prove/prova-widget-nuovi.js <base> <cartella CMS> <utente> <password>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 240) : '')); };

(async () => {
  // 1. Orari: i calcoli, a giorni e ore fissi (fuso di Roma).
  const o = JSON.parse(execFileSync('php', [path.join(__dirname, 'orari-cli.php'), DIR], { encoding: 'utf8' }));
  const casi = {
    lunedi_10: [true, 'Aperto ora, chiude alle 13:00'], lunedi_13_30: [false, 'Chiuso ora, apre alle 15:00'], lunedi_20: [false, 'Chiuso ora, apre domani alle 9:00'],
    martedi_8: [false, 'Chiuso ora, apre alle 9:00'], mercoledi_12_29: [true, 'Aperto ora, chiude alle 12:30'], giovedi_3: [true, 'Aperto ora, 24 ore su 24'],
    venerdi_23: [true, 'Aperto ora, chiude alle 2:00'], sabato_1: [true, 'Aperto ora, chiude alle 2:00'], sabato_10: [false, 'Chiuso ora, apre lunedì alle 9:00'],
    natale: [false, 'Chiuso ora: Natale, fino al 26/12'], vigilia_mattina_prima: [false, 'Chiuso ora, apre lunedì alle 9:00'],
  };
  for (const [k, atteso] of Object.entries(casi)) ok('orari, ' + k.replace(/_/g, ' ') + ': ' + atteso[1], JSON.stringify(o[k]) === JSON.stringify(atteso), o[k]);
  ok('orari: "9-13", "chiuso", vuoto e un testo che non si legge', JSON.stringify(o.intervalli.slice(1, 5)) === '[[[540,780]],[],[],[]]', o.intervalli);

  const br = await chromium.launch();
  const err = [];
  const pagina = async (w, h) => { const p = await (await br.newContext({ viewport: { width: w, height: h } })).newPage(); p.on('pageerror', e => err.push(e.message.slice(0, 200))); p.on('console', m => { if (m.type() === 'error') err.push(m.text().slice(0, 200)); }); return p; };
  const p = await pagina(1280, 900);
  const r = await p.goto(B + '/widget-nuovi/', { waitUntil: 'networkidle' });
  ok('la pagina con i sette widget risponde 200', r.status() === 200);
  const html = await (await p.request.get(B + '/widget-nuovi/')).text();
  ok('ci sono tutti e sette', ['orari', 'barra_contatti', 'recensioni', 'prima_dopo', 'squadra', 'slider', 'eventi'].every(t => html.includes('ed-w-' + t)));
  const ld = await p.locator('script[type="application/ld+json"]').evaluateAll(l => l.map(s => { try { return JSON.parse(s.textContent); } catch (e) { return null; } }));
  ok('nessun dato per Google rotto (JSON leggibile)', ld.every(Boolean), ld.length);

  // Orari sul sito
  const stato = await p.locator('.ew-orari-frase').innerText();
  const statoServer = (html.match(/class="ew-orari-frase">([^<]*)</) || [])[1];
  ok('orari: lo stato ricalcolato nel browser è uguale a quello del server', stato === statoServer.replace(/&#039;/g, "'"), { stato, statoServer });
  ok('orari: il giorno di oggi è evidenziato (uno solo)', await p.locator('.ew-orari-riga.ew-oggi').count() === 1 && await p.locator('.ew-orari-riga[aria-current="date"]').count() === 1);
  ok('orari: le ferie in arrivo sono scritte', /Ferie estive: dal \d\d\/\d\d al \d\d\/\d\d/.test(await p.locator('.ew-orari-ferie').innerText()));
  const lb = ld.find(x => x && x['@type'] === 'LocalBusiness');
  ok('orari: dati per Google con gli orari (lunedì-venerdì due fasce, sabato una)', lb && lb.openingHoursSpecification.length === 11 && lb.openingHoursSpecification[0].opens === '09:00', lb && lb.openingHoursSpecification.length);

  // Recensioni
  ok('recensioni: 4,6 su 5, le stelle piene al 92%', (await p.locator('.ew-rec-media').innerText()) === '4,6' && (await p.locator('.ew-rec-piene').getAttribute('style')).includes('92%'));
  ok('recensioni: tre schede e il pulsante verso la pagina delle recensioni, in una scheda nuova', await p.locator('.ew-rec-voce').count() === 3 && await p.locator('.ew-rec-azione a[target=_blank][href="https://g.page/r/esempio/review"]').count() === 1);
  ok('recensioni: nessun dato per Google sulle stelle (Google non le mostra per le attività che si recensiscono da sole)', !ld.some(x => x && JSON.stringify(x).includes('AggregateRating')));

  // Prima e dopo
  const pd = p.locator('.ew-pd');
  await pd.scrollIntoViewIfNeeded();
  const prima = await pd.evaluate(e => getComputedStyle(e).getPropertyValue('--ew-pos').trim());
  await p.locator('.ew-pd-cursore').focus(); for (let i = 0; i < 10; i++) await p.keyboard.press('ArrowRight');
  const dopo = await pd.evaluate(e => getComputedStyle(e).getPropertyValue('--ew-pos').trim());
  ok('prima e dopo: parte dal 50% e con la tastiera la linea si sposta', prima === '50%' && dopo === '60%', { prima, dopo });
  const box = await pd.boundingBox();
  await p.mouse.click(box.x + box.width * 0.2, box.y + box.height / 2);
  const clic = parseFloat(await pd.evaluate(e => getComputedStyle(e).getPropertyValue('--ew-pos')));
  ok('prima e dopo: con un clic (o il dito) la linea va dove si tocca', clic > 15 && clic < 25, clic);
  ok('prima e dopo: le due foto hanno il testo alternativo', (await p.locator('.ew-pd img').evaluateAll(l => l.map(i => i.alt))).every(a => /Prima|Dopo/.test(a)));

  // Squadra
  const anna = p.locator('.ew-sq-voce', { hasText: 'Anna Rossi' });
  const link = await anna.locator('.ew-sq-social a').evaluateAll(l => l.map(a => a.getAttribute('href')));
  ok('squadra: email, telefono e LinkedIn; il link "javascript:" scartato', JSON.stringify(link) === JSON.stringify(['mailto:anna@esempio.it', 'tel:+39051123456', 'https://www.linkedin.com/in/esempio']), link);
  ok('squadra: senza foto, le iniziali; un\'email sbagliata non diventa un link', (await p.locator('.ew-sq-voce', { hasText: 'Luca Bianchi' }).locator('.ew-sq-iniziali').innerText()) === 'LB' && await p.locator('.ew-sq-voce', { hasText: 'Luca Bianchi' }).locator('.ew-sq-social').count() === 0);

  // Slider
  const sl = p.locator('.ew-slider');
  ok('slider: tre slide, una alla volta, frecce e pallini', await sl.locator('.ew-car-voce').count() === 3 && await sl.locator('.ew-car-pallini button').count() === 3);
  const x0 = await sl.locator('.ew-car-traccia').evaluate(e => e.scrollLeft);
  await sl.locator('.ew-car-next').click(); await p.waitForTimeout(900);
  const x1 = await sl.locator('.ew-car-traccia').evaluate(e => e.scrollLeft);
  ok('slider: la freccia porta alla seconda slide', x1 > x0 + 200 && await sl.locator('.ew-car-pallini button[aria-selected="true"]').getAttribute('data-i') === '1', { x0, x1 });
  ok('slider: un solo titolo h2 (la prima slide), le altre h3', await sl.locator('h2.ew-sl-titolo').count() === 1 && await sl.locator('h3.ew-sl-titolo').count() === 2);

  // Eventi
  const titoli = await p.locator('.ew-ev-titolo').allInnerTexts();
  ok('eventi: in ordine di data, quello passato tolto', JSON.stringify(titoli) === '["Evento prima","Evento dopo"]', titoli);
  const ev = ld.find(x => x && x['@graph'] && x['@graph'].some(g => g['@type'] === 'Event'));
  ok('eventi: dati per Google per i due eventi in programma, con data e ora', ev && ev['@graph'].length === 2 && /^\d{4}-\d{2}-\d{2}T21:00$/.test(ev['@graph'][0].startDate), ev);

  // Barra dei contatti: al computer non c'è, al telefono sì, fissa in fondo.
  ok('barra contatti: al computer non si vede ("solo sul telefono")', !(await p.locator('.ew-barra-contatti').isVisible()));
  const t = await pagina(390, 844);
  await t.goto(B + '/widget-nuovi/', { waitUntil: 'networkidle' });
  const barra = t.locator('.ew-barra-contatti');
  const bb = await barra.boundingBox();
  ok('barra contatti: al telefono è fissa in fondo allo schermo', await barra.isVisible() && Math.abs(bb.y + bb.height - 844) < 2 && await barra.evaluate(e => getComputedStyle(e).position) === 'fixed', bb);
  const voci = await barra.locator('a').evaluateAll(l => l.map(a => a.getAttribute('href')));
  ok('barra contatti: Chiama, WhatsApp, Indicazioni, Scrivici con i link giusti', voci.length === 4 && voci[0] === 'tel:+39051123456' && voci[1].startsWith('https://wa.me/39333123456?text=') && voci[2] === 'https://www.google.com/maps/dir/?api=1&destination=Via%20Rizzoli%201%2C%20Bologna' && voci[3] === 'mailto:info@esempio.it', voci);
  ok('barra contatti: la pagina ha spazio in fondo, la barra non copre il piede', await t.evaluate(() => parseFloat(getComputedStyle(document.body).paddingBottom)) >= 60);
  ok('telefono: niente scorrimento orizzontale', await t.evaluate(() => document.documentElement.scrollWidth) <= 390);
  ok('telefono: nello slider le frecce non coprono il testo (nascoste)', !(await t.locator('.ew-slider .ew-car-next').isVisible()));

  // Il pannello: i sette widget nell'elenco dell'editor.
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/', { waitUntil: 'networkidle' });
  const casa = await p.locator('.db-azioni a', { hasText: "Home nell'editor" }).getAttribute('href');
  await p.goto(B + '/admin/' + casa, { waitUntil: 'networkidle' }); await p.waitForTimeout(1200);
  const nomi = await p.locator('#ed-pannello-widget, .ed-griglia-widget').first().innerText().catch(() => '');
  const elenco = await p.evaluate(() => document.body.innerText);
  ok('editor: i sette widget sono nell\'elenco', ['Orari di apertura', 'Barra contatti (telefono)', 'Recensioni', 'Prima e dopo', 'Squadra', 'Slider', 'Eventi'].every(x => elenco.includes(x)), nomi.slice(0, 120));
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
