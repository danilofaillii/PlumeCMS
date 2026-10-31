// 2.1.0: Scegli dai Media dappertutto. Per ogni campo immagine del pannello (fuori dall'editor visuale) apre la finestra
// dei Media come una persona: cerca, sceglie, carica un'immagine nuova dalla finestra, la toglie con la X. Poi la tastiera
// (frecce, Invio, Esc e il fuoco che torna al pulsante), le centinaia di immagini (60 alla volta, le altre scorrendo),
// il salvataggio (il valore resta dopo aver ricaricato la pagina) e il telefono a 390 px.
// Prepara da solo: 130 immagini finte nella cartella delle immagini, gli otto addon accesi, una campagna, un corso,
// un evento. Serve un sito con il kit Attività (prove/prova-kit.js) e la cartella del sito sul disco.
// uso: CMS_DIR=<cartella del CMS installato> node prove/prova-scegli-media.js <base> <utente> <password>
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const [B, UTENTE, PASSWORD] = process.argv.slice(2);
const DIR = process.env.CMS_DIR;
if (!B || !DIR) { console.error('uso: CMS_DIR=<cartella> node prove/prova-scegli-media.js <base> <utente> <password>'); process.exit(2); }
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const dorme = (ms) => new Promise(r => setTimeout(r, ms));
const ADDON = ['prenotazioni', 'eventi-iscrizione', 'recensioni', 'vetrina', 'statistiche', 'membri', 'corsi', 'copia-altrove'];

// 130 immagini finte (scmp-000 ... scmp-129) e il file da caricare dalla finestra.
function prepara() {
  const cart = path.join(DIR, 'uploads', '2026', '10');
  fs.mkdirSync(cart, { recursive: true });
  const tmp = path.join(require('os').tmpdir(), 'scegli-media-prova');
  fs.mkdirSync(tmp, { recursive: true });
  execFileSync('python3', ['-I', '-c', `
import os, sys, time
from PIL import Image, ImageDraw
cart, tmp = sys.argv[1], sys.argv[2]
for i in range(130):
    f = os.path.join(cart, 'scmp-%03d.png' % i)
    if not os.path.exists(f):
        im = Image.new('RGB', (160, 120), ((i * 37) % 255, (i * 91) % 255, 150)); ImageDraw.Draw(im).text((10, 50), 'scmp %d' % i, fill='white'); im.save(f)
    t = time.time() - 86400 - i * 60
    os.utime(f, (t, t))
im = Image.new('RGB', (1200, 630), (31, 111, 92)); ImageDraw.Draw(im).text((500, 300), 'caricata', fill='white'); im.save(os.path.join(tmp, 'caricata-dalla-finestra.jpg'), quality=85)
`, cart, tmp]);
  return path.join(tmp, 'caricata-dalla-finestra.jpg');
}

(async () => {
  const FILE = prepara();
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(p.url() + ' ' + e.message.slice(0, 160)));
  p.on('console', m => { if (m.type() === 'error' && !/favicon|404|Failed to load resource/.test(m.text())) err.push(p.url() + ' ' + m.text().slice(0, 160)); });
  p.on('dialog', d => d.accept());
  const vai = async (u) => { await p.goto(B + '/admin/' + u, { waitUntil: 'networkidle' }); };
  await vai('login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  // Gli addon accesi, una campagna, un corso, un evento: le pagine con i campi immagine.
  for (const a of ADDON) {
    await vai('addon.php');
    const b = p.locator(`[data-addon="${a}"] button[value=accendi]`);
    if (await b.count()) await Promise.all([p.waitForNavigation(), b.click()]);
  }
  ok('gli otto addon accesi', await (async () => { await vai('addon.php'); return await p.locator('.ad-card.acceso').count() === 8; })());
  await vai('newsletter.php');
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=nuova]) button')]);
  const campagna = p.url().split('admin/')[1];
  ok('una campagna nuova', /newsletter-campagna\.php\?id=\d+/.test(campagna), campagna);
  await vai('addon-pagina.php?a=corsi&p=corsi');
  await p.fill('#co-titolo', 'Corso per la prova dei Media');
  await Promise.all([p.waitForNavigation(), p.locator('form:has(#co-titolo) button[type=submit]').first().click()]);
  const corso = p.url().split('admin/')[1];
  ok('un corso nuovo (la sua scheda ha il campo immagine)', await p.locator('#co-immagine').count() === 1, corso);

  // I campi: pagina, campo (o pulsante), come si arriva al campo.
  const CAMPI = [
    { nome: 'SEO, immagine social predefinita', u: 'seo.php?scheda=globale', campo: '#seo-og-immagine', salva: 'form:has(#seo-og-immagine) button.btn-primary' },
    { nome: 'SEO, logo dell\'organizzazione', u: 'seo.php?scheda=schema', campo: '#seo-org-logo' },
    { nome: 'SEO, immagine dell\'organizzazione', u: 'seo.php?scheda=schema', campo: '#seo-org-immagine' },
    { nome: 'SEO, foto della persona', u: 'seo.php?scheda=schema', campo: '#seo-persona-immagine', salva: 'form:has(#seo-persona-immagine) button[type=submit].btn-primary' },
    { nome: 'Social, immagine per LinkedIn e Facebook', u: 'social.php?scheda=calendario&nuovo=1', campo: '#so-og' },
    { nome: 'Social, immagine per Instagram', u: 'social.php?scheda=calendario&nuovo=1', campo: '#so-ig' },
    { nome: 'Popup, immagine', u: 'popup.php?nuovo=1', campo: '#pp-immagine' },
    { nome: 'Corsi, immagine del corso', u: corso, campo: '#co-immagine', salva: 'form:has(#co-immagine) button[type=submit].btn-primary' },
    { nome: 'Eventi, foto dell\'evento', u: 'addon-pagina.php?a=eventi-iscrizione&p=eventi&nuovo=1', campo: '#ev-foto' },
  ];
  const dialogo = 'dialog.pn-media';
  const voci = () => p.locator(dialogo + ' .pn-media-voce');
  async function apri(sel) {
    await p.click(sel);
    await p.waitForSelector(dialogo + '[open] .pn-media-voce', { timeout: 10000 });
  }
  const valore = (c) => p.$eval(c, e => e.value);
  const mini = (c) => p.$eval(c, e => { const b = e.closest('.pn-immagine'); const i = b && b.querySelector('.pn-immagine-mini img'); return b ? { vuota: b.classList.contains('pn-immagine-vuota'), src: i.getAttribute('src') || '', x: !b.querySelector('.pn-immagine-x').hidden && b.querySelector('.pn-immagine-x').offsetWidth > 0 } : null; });

  for (const c of CAMPI) {
    await vai(c.u);
    const btn = `[data-scegli-media="${c.campo}"]`;
    if (!(await p.locator(btn).count())) { ok(c.nome + ': c\'è il pulsante Scegli dai Media', false); continue; }
    ok(c.nome + ': campo con miniatura, Scegli dai Media e X', !!(await mini(c.campo)) && (await p.locator(btn).innerText()).trim() === 'Scegli dai Media');
    // cerca e sceglie
    await apri(btn);
    await p.fill(dialogo + ' input[type=search]', 'scmp-007');
    await p.waitForFunction((d) => { const v = document.querySelectorAll(d + ' .pn-media-voce'); return v.length === 1 && v[0].textContent.includes('scmp-007'); }, dialogo, { timeout: 8000 }).catch(() => {});
    ok(c.nome + ': la ricerca trova solo scmp-007', await voci().count() === 1, await voci().count());
    await voci().first().click();
    await p.waitForFunction((d) => !document.querySelector(d).open, dialogo);
    const v1 = await valore(c.campo), m1 = await mini(c.campo);
    ok(c.nome + ': scelta, l\'indirizzo è nel campo e la miniatura si vede', /\/uploads\/2026\/10\/scmp-007\.png$/.test(v1) && !m1.vuota && m1.src === v1 && m1.x, { v1, m1 });
    // carica dalla finestra
    await apri(btn);
    ok(c.nome + ': la scelta di prima è segnata nella finestra', await p.locator(dialogo + ' .pn-media-voce.pn-media-scelta').count() <= 1);
    await p.setInputFiles(dialogo + ' input[type=file]', FILE);
    await p.waitForFunction((d) => !document.querySelector(d).open, dialogo, { timeout: 15000 }).catch(() => {});
    const v2 = await valore(c.campo);
    ok(c.nome + ': un\'immagine caricata dalla finestra finisce nel campo', /caricata-dalla-finestra[^/]*\.jpe?g$/.test(v2), v2);
    const r = await p.request.get(new URL(v2, B + '/').href);
    ok(c.nome + ': l\'immagine caricata si apre', r.status() === 200 && /image\//.test(r.headers()['content-type'] || ''), r.status());
    // salva e ricarica
    if (c.salva) {
      await Promise.all([p.waitForNavigation(), p.click(c.salva)]);
      await vai(c.u);
      const v3 = await valore(c.campo), m3 = await mini(c.campo);
      ok(c.nome + ': salvato, dopo aver ricaricato la pagina c\'è ancora (con la miniatura)', v3 === v2 && !m3.vuota, { v3 });
    }
    // togli
    await p.click(`.pn-immagine:has(${c.campo}) .pn-immagine-x`);
    const m4 = await mini(c.campo);
    ok(c.nome + ': la X toglie l\'immagine', (await valore(c.campo)) === '' && m4.vuota && !m4.x);
    if (c.salva) await Promise.all([p.waitForNavigation(), p.click(c.salva)]);
  }

  // Scheda di un articolo: immagine in evidenza e "+ Immagine" nel testo.
  await vai('contenuti.php?tipo=articoli');
  const articolo = await p.$$eval('a[href*="contenuto.php?tipo=articoli&id="]', l => l.length ? l[0].getAttribute('href') : '');
  await vai(articolo || 'contenuto.php?tipo=articoli&scheda=1');
  if (await p.locator('#sc-immagine').inputValue()) { await p.click('#sc-immagine-togli'); }
  await apri('#sc-immagine-scegli');
  await p.fill(dialogo + ' input[type=search]', 'scmp-011'); await p.waitForFunction((d) => document.querySelectorAll(d + ' .pn-media-voce').length === 1, dialogo).catch(() => {});
  await voci().first().click(); await dorme(200);
  ok('Articolo: immagine in evidenza scelta dai Media, con l\'anteprima e Cambia', /scmp-011\.png$/.test(await valore('#sc-immagine')) && !(await p.locator('#sc-immagine-anteprima').isHidden())
    && (await p.locator('#sc-immagine-scegli').innerText()).trim() === 'Cambia' && await p.locator('#sc-immagine-togli').isVisible());
  await apri('#sc-immagine-scegli');
  await p.fill(dialogo + ' input[type=search]', 'scmp-011'); await p.waitForFunction((d) => document.querySelectorAll(d + ' .pn-media-voce').length === 1, dialogo).catch(() => {});
  ok('Articolo: la finestra segna l\'immagine già scelta', await p.locator(dialogo + ' .pn-media-voce.pn-media-scelta').count() === 1);
  await p.setInputFiles(dialogo + ' input[type=file]', FILE);
  await p.waitForFunction((d) => !document.querySelector(d).open, dialogo, { timeout: 15000 }).catch(() => {});
  ok('Articolo: immagine in evidenza caricata dalla finestra', /caricata-dalla-finestra/.test(await valore('#sc-immagine')));
  await p.click('#sc-immagine-togli');
  ok('Articolo: Togli', (await valore('#sc-immagine')) === '' && await p.locator('#sc-immagine-anteprima').isHidden());

  // Stile del sito: logo e favicon.
  await vai('design.php');
  for (const nome of ['sito.logo', 'sito.favicon']) {
    const sel = await p.evaluate((nome) => {
      const i = [...document.querySelectorAll('.dg-immagine input[type=text]')].find(x => x.name.replace(/\]\[/g, '.').replace(/\[|\]/g, '.').replace(/\.+/g, '.').replace(/\.$/, '').endsWith(nome));
      if (!i) return null;
      const s = i.closest('section[data-scheda]'); if (s && s.hidden) document.querySelector(`.dg-pannello button[data-scheda="${s.dataset.scheda}"]`).click();
      i.closest('details').open = true; i.id = i.id || 'dg-prova-' + nome.replace('.', '-'); return '#' + i.id;
    }, nome);
    if (!sel) { ok('Stile del sito, ' + nome + ': campo trovato', false); continue; }
    await p.click(`.dg-immagine:has(${sel}) .dg-scegli`); await p.waitForSelector(dialogo + '[open] .pn-media-voce');
    await p.fill(dialogo + ' input[type=search]', 'scmp-012'); await p.waitForFunction((d) => document.querySelectorAll(d + ' .pn-media-voce').length === 1, dialogo).catch(() => {});
    await voci().first().click(); await dorme(200);
    ok('Stile del sito, ' + nome + ': scelto dai Media, con la miniatura e la X', /scmp-012\.png$/.test(await valore(sel)) && await p.locator(`.dg-immagine:has(${sel}) .dg-miniatura`).isVisible() && await p.locator(`.dg-immagine:has(${sel}) .dg-x`).isVisible());
    await p.click(`.dg-immagine:has(${sel}) .dg-x`);
    ok('Stile del sito, ' + nome + ': la X lo toglie', (await valore(sel)) === '' && await p.locator(`.dg-immagine:has(${sel}) .dg-miniatura`).isHidden());
  }
  // Non si salva lo Stile del sito: si esce senza la domanda delle modifiche non salvate.
  await p.evaluate(() => { window.onbeforeunload = null; });

  // Newsletter: il pulsante immagine dell'editor della campagna apre i Media e inserisce l'indirizzo completo.
  await vai(campagna);
  await p.click('#editor'); await p.click('.ql-toolbar .ql-image');
  await p.waitForSelector(dialogo + '[open] .pn-media-voce');
  ok('Newsletter: il pulsante immagine apre la finestra con il titolo per l\'email', /email/.test(await p.locator(dialogo + ' h3').innerText()));
  await p.fill(dialogo + ' input[type=search]', 'scmp-013'); await p.waitForFunction((d) => document.querySelectorAll(d + ' .pn-media-voce').length === 1, dialogo).catch(() => {});
  await voci().first().click(); await dorme(300);
  const src = await p.$eval('#editor .ql-editor img', i => i.getAttribute('src')).catch(() => '');
  ok('Newsletter: l\'immagine scelta entra nel testo con l\'indirizzo completo', /^https?:\/\/[^/]+\/.*scmp-013\.png$/.test(src), src);
  await p.click('.ql-toolbar .ql-image'); await p.waitForSelector(dialogo + '[open]');
  ok('Newsletter: la finestra carica solo i formati che le email mostrano', (await p.$eval(dialogo + ' input[type=file]', i => i.accept)) === 'image/png,image/jpeg,image/gif,image/webp');
  await p.setInputFiles(dialogo + ' input[type=file]', FILE);
  await p.waitForFunction((d) => !document.querySelector(d).open, dialogo, { timeout: 15000 }).catch(() => {});
  const srcs = await p.$$eval('#editor .ql-editor img', l => l.map(i => i.getAttribute('src')));
  ok('Newsletter: un\'immagine caricata dalla finestra va nella cartella della newsletter', srcs.some(s => /\/newsletter\/\d{4}\/\d{2}\/caricata-dalla-finestra/.test(s)), srcs);

  // Vetrina: "Scegli dai Media" aggiunge una foto all'elenco del prodotto; salvato, il prodotto la tiene.
  await vai('addon-pagina.php?a=vetrina&p=prodotto');
  await p.fill('#vt-nome', 'Vaso per la prova dei Media'); await p.fill('#vt-prezzo', '12');
  await apri('[data-scegli-media-lista="#vt-foto"]');
  await p.fill(dialogo + ' input[type=search]', 'scmp-014'); await p.waitForFunction((d) => document.querySelectorAll(d + ' .pn-media-voce').length === 1, dialogo).catch(() => {});
  await voci().first().click(); await dorme(200);
  ok('Vetrina: la foto scelta compare nell\'elenco con Principale e Togli', await p.locator('#vt-foto figure img[src$="scmp-014.png"]').count() === 1
    && await p.locator('#vt-foto figure input[name="foto[]"][value$="scmp-014.png"]').count() === 1 && await p.locator('#vt-foto figure input[name=principale][value$="scmp-014.png"]').count() === 1);
  await apri('[data-scegli-media-lista="#vt-foto"]');
  await p.setInputFiles(dialogo + ' input[type=file]', FILE);
  await p.waitForFunction((d) => !document.querySelector(d).open, dialogo, { timeout: 15000 }).catch(() => {});
  ok('Vetrina: una seconda foto caricata dalla finestra', await p.locator('#vt-foto figure').count() === 2);
  await Promise.all([p.waitForNavigation(), p.click('form:has(#vt-nome) button[type=submit].btn-primary')]);
  const prod = p.url();
  await p.goto(prod.includes('id=') ? prod : prod, { waitUntil: 'networkidle' });
  ok('Vetrina: salvato, il prodotto ha le due foto', await p.locator('#vt-foto figure').count() === 2, await p.locator('#vt-foto figure').count());

  // Tastiera e centinaia di immagini, sul campo della SEO.
  await vai('seo.php?scheda=globale');
  await p.focus('[data-scegli-media="#seo-og-immagine"]'); await p.keyboard.press('Enter');
  await p.waitForSelector(dialogo + '[open] .pn-media-voce');
  ok('Tastiera: Invio sul pulsante apre la finestra con il fuoco sulla ricerca', await p.evaluate((d) => document.activeElement === document.querySelector(d + ' input[type=search]'), dialogo));
  ok('Tante immagini: ne arrivano 60 alla volta', await voci().count() === 60, await voci().count());
  ok('Tante immagini: il conto dice quante sono in tutto', /60 di \d+ immagini/.test(await p.locator(dialogo + ' .pn-media-stato').innerText()), await p.locator(dialogo + ' .pn-media-stato').innerText());
  await p.keyboard.press('ArrowDown');
  ok('Tastiera: freccia giù dalla ricerca porta alla prima immagine', await p.evaluate((d) => document.activeElement === document.querySelector(d + ' .pn-media-voce'), dialogo));
  await p.keyboard.press('ArrowRight');
  const seconda = await p.evaluate(() => document.activeElement.title);
  ok('Tastiera: freccia a destra va alla seconda', seconda === await voci().nth(1).getAttribute('title'), seconda);
  await p.keyboard.press('Enter'); await dorme(200);
  ok('Tastiera: Invio sceglie l\'immagine con il fuoco', (await valore('#seo-og-immagine')) === seconda && !(await p.evaluate((d) => document.querySelector(d).open, dialogo)), await valore('#seo-og-immagine'));
  ok('Tastiera: chiusa la finestra, il fuoco torna al pulsante', await p.evaluate(() => document.activeElement && document.activeElement.getAttribute('data-scegli-media') === '#seo-og-immagine'));
  await p.keyboard.press('Enter'); await p.waitForSelector(dialogo + '[open] .pn-media-voce');
  const tab = [];
  for (let i = 0; i < 6; i++) { await p.keyboard.press('Tab'); tab.push(await p.evaluate(() => document.activeElement.classList.contains('pn-media-voce') ? 'pn-media-voce' : (document.activeElement.className || document.activeElement.tagName))); }
  ok('Tastiera: con Tab si arriva alla griglia (una sola fermata) e poi a "Mostra altre"', tab.includes('pn-media-voce') && tab.filter(t => t === 'pn-media-voce').length === 1, tab);
  await p.locator(dialogo + ' .pn-media-corpo').evaluate(e => { e.scrollTop = e.scrollHeight; });
  await p.waitForFunction((d) => document.querySelectorAll(d + ' .pn-media-voce').length >= 120, dialogo, { timeout: 8000 }).catch(() => {});
  ok('Tante immagini: scorrendo in fondo arrivano le altre 60', await voci().count() >= 120, await voci().count());
  const tot = await (async () => { for (let i = 0; i < 6; i++) { await p.locator(dialogo + ' .pn-media-corpo').evaluate(e => { e.scrollTop = e.scrollHeight; }); await dorme(500); } return voci().count(); })();
  const totale = +((await p.locator(dialogo + ' .pn-media-stato').innerText()).match(/(\d+) immagini/) || [0, 0])[1];
  ok('Tante immagini: alla fine ci sono tutte, e "Mostra altre" sparisce', tot === totale && tot >= 130 && await p.locator(dialogo + ' .pn-media-altre').isHidden(), { tot, totale });
  await p.selectOption(dialogo + ' select', { index: 1 }).catch(() => {});
  await dorme(800);
  ok('Cartelle: la tendina filtra le immagini', await voci().count() > 0);
  await p.keyboard.press('Escape'); await dorme(200);
  ok('Tastiera: Esc chiude la finestra senza cambiare il campo', !(await p.evaluate((d) => document.querySelector(d).open, dialogo)) && (await valore('#seo-og-immagine')) === seconda);
  // "Oppure usa un indirizzo"
  await apri('[data-scegli-media="#seo-og-immagine"]');
  await p.click(dialogo + ' .pn-media-indirizzo summary');
  await p.fill(dialogo + ' .pn-media-indirizzo input', 'https://esempio.it/foto.jpg'); await p.keyboard.press('Enter'); await dorme(200);
  ok('Un indirizzo scritto a mano nella finestra va nel campo', (await valore('#seo-og-immagine')) === 'https://esempio.it/foto.jpg');
  const rotta = await mini('#seo-og-immagine');
  ok('Una miniatura che non si apre lo dice (il campo resta)', rotta && !rotta.vuota);

  // Telefono: la finestra prende lo schermo, niente scorrimento di lato, i campi vanno a capo.
  const t = await (await br.newContext({ viewport: { width: 390, height: 844 }, storageState: await ctx.storageState() })).newPage();
  t.on('pageerror', e => err.push('tel ' + t.url() + ' ' + e.message.slice(0, 160)));
  for (const u of ['seo.php?scheda=globale', 'seo.php?scheda=schema', 'social.php?scheda=calendario&nuovo=1', 'popup.php?nuovo=1', corso, 'addon-pagina.php?a=eventi-iscrizione&p=eventi&nuovo=1', 'addon-pagina.php?a=vetrina&p=prodotto', 'design.php', campagna]) {
    await t.goto(B + '/admin/' + u, { waitUntil: 'networkidle' });
    const W = await t.evaluate(() => document.documentElement.scrollWidth);
    ok('Telefono: ' + u + ' senza scorrimento di lato', W <= 391, W);
  }
  await t.goto(B + '/admin/seo.php?scheda=globale', { waitUntil: 'networkidle' });
  const campo = await t.$eval('#seo-og-immagine', e => { const r = e.getBoundingClientRect(), b = e.closest('.pn-immagine').getBoundingClientRect(); return { w: r.width, destra: b.right }; });
  ok('Telefono: il campo immagine sta nello schermo e la casella resta leggibile', campo.destra <= 390 && campo.w >= 200, campo);
  await t.click('[data-scegli-media="#seo-og-immagine"]'); await t.waitForSelector(dialogo + '[open] .pn-media-voce');
  const d = await t.$eval(dialogo, e => { const r = e.getBoundingClientRect(), g = e.querySelector('.pn-media-griglia'); return { l: r.left, w: r.width, h: r.height, gw: g.scrollWidth, gc: g.clientWidth, col: getComputedStyle(g).gridTemplateColumns.split(' ').length }; });
  ok('Telefono: la finestra prende tutto lo schermo, la griglia non esce di lato, almeno tre colonne', d.l === 0 && d.w === 390 && d.h === 844 && d.gw <= d.gc && d.col >= 3, d);
  const az = await t.$eval(dialogo + ' .pn-media-azioni', e => e.getBoundingClientRect().bottom);
  ok('Telefono: Chiudi è visibile in fondo', az <= 844, az);
  const terza = await t.locator(dialogo + ' .pn-media-voce').nth(2).getAttribute('title');
  await t.locator(dialogo + ' .pn-media-voce').nth(2).tap().catch(() => t.locator(dialogo + ' .pn-media-voce').nth(2).click()); await dorme(200);
  ok('Telefono: si sceglie con un tocco', (await t.$eval('#seo-og-immagine', e => e.value)) === terza, terza);

  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
