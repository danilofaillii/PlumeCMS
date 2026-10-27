// Kit di partenza (1.3.0): installa plumeCMS da zero scegliendo un kit all'ultimo passo, poi visita come un visitatore
// ogni pagina del menu, al computer e al telefono. Controlla: risposta 200, un solo h1, niente scorrimento orizzontale,
// immagini caricate, nessun segnaposto {..} rimasto, link interni che restano nella cartella del sito, menu del kit
// nell'header, nessun errore JavaScript; poi apre la home nell'editor. Lascia <kit>-<pagina>-pc.png e -tel.png.
// uso: DBPASS=... node prova-kit.js <base, es. http://127.0.0.1:8081 oppure http://127.0.0.1:8081/test> <kit> <db> <cartella schermate>
const { chromium } = require('playwright');
const [B, KIT, DB, OUT] = process.argv.slice(2);
const MENU = { personale: ['Home', 'Chi sono', 'Blog', 'Contatti'], attivita: ['Home', 'Servizi', 'Chi siamo', 'Blog', 'Contatti'], blog: ['Home', 'Blog', 'Chi sono', 'Contatti'], portfolio: ['Home', 'Lavori', 'Chi sono', 'Blog', 'Contatti'], vuoto: ['Home', 'Chi sono', 'Blog', 'Contatti'] };
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 220) : '')); };
(async () => {
  const br = await chromium.launch(); const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200))); p.on('console', m => { if (m.type() === 'error' && !/favicon/.test(m.location().url || '')) err.push(m.text().slice(0, 200)); });
  // L'installatore fino al passo 4
  await p.goto(B + '/', { waitUntil: 'networkidle' });
  ok('senza config la home apre l\'installatore', p.url().includes('/install/'), p.url());
  await p.click('a.btn:has-text("Avanti")'); await p.waitForLoadState('networkidle');
  await p.fill('#host', '127.0.0.1:3308'); await p.fill('#nome', DB); await p.fill('#utente', 'danilo'); await p.fill('#password', process.env.DBPASS || '');
  await p.fill('#prefisso', 'cms_'); await p.check('input[name=crea]');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.fill('#nome', 'Studio Prova'); await p.fill('#descrizione', 'Un sito di prova costruito dal kit di partenza.'); await p.fill('#email', 'sito@esempio.it');
  await p.fill('#admin_nome', 'prova'); await p.fill('#admin_email', 'prova@esempio.it'); await p.fill('#admin_password', 'Collaudo-Officina-2026'); await p.fill('#admin_password2', 'Collaudo-Officina-2026');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  // Passo 4: la scelta del kit
  const carte = await p.locator('.kit-scelta .kit').count();
  ok('passo 4: "Che sito vuoi fare?" con cinque carte', carte === 5 && await p.locator('h3:has-text("Che sito vuoi fare")').count() === 1, carte);
  ok('passo 4: Vuoto scelto se non si tocca niente', await p.locator('.kit input[value=vuoto]').isChecked());
  await p.locator('.kit-' + KIT).click();
  ok('passo 4: il kit ' + KIT + ' si sceglie con un clic sulla carta', await p.locator('.kit input[value=' + KIT + ']').isChecked());
  await p.screenshot({ path: `${OUT}/${KIT}-installatore.png`, fullPage: true });
  await Promise.all([p.waitForNavigation({ timeout: 120000 }), p.click('button:has-text("Installa adesso")')]);
  ok('installazione completata', p.url().includes('passo=5'), p.url());
  const voci = await p.locator('.elenco.piccolo li').allInnerTexts();
  if (KIT !== 'vuoto') ok('log: il kit ha costruito la home', voci.some(v => v.includes('kit ') && v.includes('Home')), voci.filter(v => /kit|pagina/.test(v)));
  // Il sito, pagina per pagina dal menu dell'header
  await p.goto(B + '/', { waitUntil: 'networkidle' });
  const menu = (await p.locator('header nav a').allInnerTexts()).map(t => t.trim().toLowerCase());
  const attese = MENU[KIT] || [];
  ok('menu dell\'header con le voci del kit', attese.every(v => menu.includes(v.toLowerCase())), menu);
  const links = await p.$$eval('header nav a', as => as.map(a => [a.textContent.trim(), a.getAttribute('href')]));
  const pagine = [];
  for (const [t, h] of links) if (h && !pagine.some(x => x[1] === h)) pagine.push([t, h]);
  const base = new URL(B);
  for (const [titolo, href] of pagine) {
    const nome = titolo.toLowerCase().replace(/[^a-z]+/g, '-');
    ok(nome + ': il link del menu sta nella cartella del sito', href.startsWith(base.pathname === '/' ? '/' : base.pathname + '/') || href.startsWith(B), href);
    for (const [misura, w, h] of [['pc', 1280, 900], ['tel', 390, 844]]) {
      await p.setViewportSize({ width: w, height: h });
      const r = await p.goto(new URL(href, B).href, { waitUntil: 'networkidle' });
      await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 40)); } window.scrollTo(0, 0); });
      await p.waitForTimeout(700);
      const d = await p.evaluate(() => ({
        h1: document.querySelectorAll('h1').length,
        largo: document.documentElement.scrollWidth - window.innerWidth,
        rotte: Array.from(document.images).filter(i => i.complete && i.naturalWidth === 0).map(i => i.src),
        segnaposto: (document.body.innerText.match(/\{[a-z]+(:\d+)?\}/g) || []),
        fuori: Array.from(document.querySelectorAll('main a[href^="/"], .ed-pagina a[href^="/"], section a[href^="/"]')).map(a => a.getAttribute('href')),
      }));
      ok(`${nome} ${misura}: 200, un solo h1, niente scorrimento orizzontale`, r.status() === 200 && d.h1 === 1 && d.largo <= 1, { stato: r.status(), h1: d.h1, largo: d.largo });
      ok(`${nome} ${misura}: immagini caricate, nessun segnaposto rimasto`, !d.rotte.length && !d.segnaposto.length, { rotte: d.rotte, segnaposto: d.segnaposto });
      if (base.pathname !== '/') ok(`${nome} ${misura}: i link interni restano nella cartella`, d.fuori.every(u => u.startsWith(base.pathname + '/')), d.fuori.filter(u => !u.startsWith(base.pathname + '/')).slice(0, 5));
      await p.screenshot({ path: `${OUT}/${KIT}-${nome}-${misura}.png`, fullPage: true });
    }
  }
  await p.setViewportSize({ width: 1280, height: 900 });
  // La home nel pannello: si apre nell'editor con le sue sezioni
  await p.goto(B + '/admin/login.php'); await p.fill('#username', 'prova'); await p.fill('#password', 'Collaudo-Officina-2026');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/contenuti.php?tipo=pagine', { waitUntil: 'networkidle' });
  const righe = await p.locator('table a').allInnerTexts();
  const idHome = await p.evaluate(() => { const a = Array.from(document.querySelectorAll('a[href*="editor.php"]')).find(x => /id=\d+/.test(x.href) && x.closest('tr') && /Home/.test(x.closest('tr').innerText)); return a ? new URL(a.href).searchParams.get('id') : null; });
  if (idHome) {
    await p.goto(B + '/admin/editor.php?tipo=pagine&id=' + idHome, { waitUntil: 'networkidle' }); await p.waitForTimeout(1500);
    const fr = p.frames().find(f => f.url().includes('anteprima'));
    const sez = fr ? await fr.locator('.ed-sezione').count() : 0;
    ok('la home del kit si apre nell\'editor, divisa in sezioni', sez >= (KIT === 'vuoto' ? 3 : 4), sez);
  } else ok('la home del kit si apre nell\'editor, divisa in sezioni', false, righe.slice(0, 10));
  ok('nessun errore JavaScript', err.length === 0, err.slice(0, 5));
  console.log(`\n${n - ko} su ${n} OK`); await br.close(); process.exit(ko ? 1 : 0);
})().catch(e => { console.log('ECCEZIONE', e.message.slice(0, 500)); process.exit(1); });
