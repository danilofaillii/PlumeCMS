// Audit di novembre 2026 (docs-sviluppo/audit-2026-11.md): una prova per ogni difetto corretto, guardando il campo o la
// pagina dopo il salvataggio (non il messaggio verde). Ogni controllo dice il numero del problema (P1, P2...).
// Su un CMS installato con il kit Attività (prove/prova-kit.js ... attivita ...), in radice o in cartella. Accende da sé
// Prenotazioni ed Eventi con iscrizione se sono spenti. Cambia dati veri: va su un sito da buttare.
// uso: node prove/prova-audit-2026-11.js <base> <utente> <password>
const { chromium } = require('playwright');
const [B, U, P] = process.argv.slice(2);
if (!B || !P) { console.error('uso: node prove/prova-audit-2026-11.js <base> <utente> <password>'); process.exit(2); }
let n = 0, ko = 0; const ok = (t, c, x = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (x !== '' ? ' ' + JSON.stringify(x).slice(0, 300) : '')); };
const VINO = '\u{1F377}', FESTA = '\u{1F389}', PACCO = '\u{1F381}';
const CARTELLA = new URL(B).pathname.replace(/\/$/, '');

(async () => {
  const br = await chromium.launch();
  const a = await br.newPage({ viewport: { width: 1280, height: 900 } });
  const err = []; a.on('pageerror', e => err.push(e.message.slice(0, 160)));
  a.on('dialog', d => d.accept());
  const vai = async u => { await a.goto(B + '/admin/' + u, { waitUntil: 'domcontentloaded' }); };
  const flash = async () => (await a.locator('.flash').allInnerTexts()).join(' | ');
  const invia = async (form, bottone) => { await Promise.all([a.waitForNavigation(), a.locator(form).locator(bottone || 'button[type=submit], button:not([type])').first().click()]); };
  await a.goto(B + '/admin/login.php'); await a.fill('#username', U); await a.fill('#password', P);
  await Promise.all([a.waitForNavigation(), a.click('button[type=submit]')]);

  // ---------------------------------------------------------------- P1: un form con le emoji si salva
  await vai('form.php');
  const hrefForm = await a.locator('a[href*="form.php?id="]').first().getAttribute('href');
  await vai(hrefForm);
  const introPrima = await a.inputValue('[name=intro]');
  await a.fill('[name=grazie_titolo]', 'Grazie! ' + FESTA);
  await a.fill('[name=intro]', introPrima.replace(/ \S+$/u, m => /[\u{10000}-\u{10FFFF}]/u.test(m) ? '' : m) + ' ' + VINO);
  await invia('form:has([name=grazie_titolo])');
  const f1 = await flash();
  ok('P1 form: salvato con le emoji, senza errore del database', !/SQLSTATE|3988|1366/.test(f1) && /salvat/i.test(f1), f1);
  await vai(hrefForm);
  ok('P1 form: riaperto, il titolo del grazie ha la sua emoji', await a.inputValue('[name=grazie_titolo]') === 'Grazie! ' + FESTA, await a.inputValue('[name=grazie_titolo]'));
  const contatti = await (await a.request.get(B + '/contatti/')).text();
  ok('P1 form: sul sito l\'introduzione del form ha l\'emoji vera (non &#x...)', contatti.includes(VINO) && !/&amp;#x1F377;|&#x1F377;/.test(contatti));
  // Rimesso com'era (il resto delle prove guarda il sito).
  await a.fill('[name=intro]', introPrima); await a.fill('[name=grazie_titolo]', 'Grazie, ricevuto.'); await invia('form:has([name=grazie_titolo])');

  // ---------------------------------------------------------------- P2: un popup con le emoji si salva
  await vai('popup.php?nuovo=1');
  await a.fill('[name=nome]', 'Audit ' + PACCO); await a.fill('[name=titolo]', 'Un regalo per te ' + PACCO);
  await invia('form:has([name=titolo])');
  const f2 = await flash();
  ok('P2 popup: salvato con le emoji, senza errore del database', !/SQLSTATE|3988|1366/.test(f2) && /salvato/i.test(f2) && /popup\.php\?id=\d+/.test(a.url()), f2);
  if (/popup\.php\?id=\d+/.test(a.url())) {
    await a.reload({ waitUntil: 'domcontentloaded' });
    ok('P2 popup: riaperto, il titolo ha la sua emoji', await a.inputValue('[name=titolo]') === 'Un regalo per te ' + PACCO, await a.inputValue('[name=titolo]'));
    await Promise.all([a.waitForNavigation(), a.locator('button[name=azione][value=elimina]').first().click()]);
  } else ok('P2 popup: riaperto, il titolo ha la sua emoji', false, a.url());

  // ---------------------------------------------------------------- P3: una lista con un'emoji: il messaggio giusto
  await vai('newsletter.php?scheda=liste');
  const lista = a.locator('form:has([name=descrizione]):has(input[name=id])').first();
  const descrPrima = await lista.locator('[name=descrizione]').inputValue();
  await lista.locator('[name=descrizione]').fill(descrPrima + ' ' + VINO);
  await Promise.all([a.waitForNavigation(), lista.locator('button').first().click()]);
  const f3 = await flash();
  ok('P3 lista: con un\'emoji non dice più "Esiste già una lista con questo nome", dice delle emoji', !/Esiste già/.test(f3) && /emoji/.test(f3), f3);
  ok('P3 lista: la descrizione resta quella di prima', await a.locator('form:has([name=descrizione]):has(input[name=id])').first().locator('[name=descrizione]').inputValue() === descrPrima);

  // ---------------------------------------------------------------- P4: emoji nel titolo del sito (SEO) e nel riassunto
  await vai('seo.php?scheda=globale');
  const tit = a.locator('[name="titolo[sito]"]');
  const titPrima = await tit.inputValue();
  await tit.fill('Studio Prova ' + VINO);
  await invia('form:has([name="titolo[sito]"])');
  await vai('seo.php?scheda=globale');
  ok('P4 SEO: riaperto, il titolo del sito ha l\'emoji (non "&#x1F377;")', await a.locator('[name="titolo[sito]"]').inputValue() === 'Studio Prova ' + VINO, await a.locator('[name="titolo[sito]"]').inputValue());
  const home = await (await a.request.get(B + '/')).text();
  const titolo = (home.match(/<title>([^<]*)<\/title>/) || ['', ''])[1];
  ok('P4 SEO: il <title> della home ha l\'emoji vera', titolo.includes(VINO) && !titolo.includes('&amp;#x'), titolo);
  await a.locator('[name="titolo[sito]"]').fill(titPrima); await invia('form:has([name="titolo[sito]"])');
  await vai('seo.php?scheda=schema');
  const org = a.locator('[name="org[nome]"]'); const orgPrima = await org.inputValue();
  await org.fill('Studio Prova ' + VINO); await invia('form:has([name="org[nome]"])');
  const home2 = await (await a.request.get(B + '/')).text();
  ok('P4 SEO: nei dati per Google (JSON-LD) il nome ha l\'emoji, non "&#x1F377;"', /"name":"Studio Prova (\u{1F377}|\\ud83c\\udf77)"/u.test(home2) && !/"name":"[^"]*&#x1F377;/.test(home2), (home2.match(/"name":"Studio Prova[^"]*"/) || [''])[0]);
  await vai('seo.php?scheda=schema'); await a.locator('[name="org[nome]"]').fill(orgPrima); await invia('form:has([name="org[nome]"])');
  await vai('contenuti.php?tipo=articoli');
  const hrefArt = await a.locator('a[href*="contenuto.php?tipo=articoli&id="], a[href*="contenuto.php?tipo=articoli&amp;id="]').first().getAttribute('href').catch(() => null);
  if (hrefArt) {
    await vai(hrefArt);
    const exPrima = await a.inputValue('textarea[name=excerpt]');
    await a.fill('textarea[name=excerpt]', 'Un riassunto con il vino ' + VINO);
    await Promise.all([a.waitForNavigation(), a.locator('button.btn-primary[data-valore=pubblica]').click()]);
    await vai(hrefArt);
    ok('P4 riassunto: riaperto, il riassunto ha l\'emoji', await a.inputValue('textarea[name=excerpt]') === 'Un riassunto con il vino ' + VINO, await a.inputValue('textarea[name=excerpt]'));
    const feed = await (await a.request.get(B + '/feed/')).text();
    ok('P4 riassunto: nel feed l\'emoji vera, non "&amp;#x1F377;"', feed.includes('Un riassunto con il vino ' + VINO), (feed.match(/Un riassunto[^<]{0,40}/) || [''])[0]);
    await a.fill('textarea[name=excerpt]', exPrima);
    await Promise.all([a.waitForNavigation(), a.locator('button.btn-primary[data-valore=pubblica]').click()]);
  } else ok('P4 riassunto: un articolo da aprire', false);

  // ---------------------------------------------------------------- P5: la pagina della privacy scritta a mano (addon)
  for (const [addon, campo] of [['prenotazioni', '#pren-privacy'], ['eventi-iscrizione', '#evi-privacy']]) {
    await vai('addon.php');
    const acc = a.locator(`[data-addon="${addon}"] button[value=accendi]`);
    if (await acc.count()) await Promise.all([a.waitForNavigation(), acc.click()]);
    const pag = 'addon-pagina.php?a=' + addon + '&p=impostazioni';
    for (const [scritto, atteso] of [['privacy-policy', '/privacy-policy'], ['www.esempio.it/privacy', 'https://www.esempio.it/privacy'], ['/privacy-policy/', '/privacy-policy/']]) {
      await vai(pag); await a.fill(campo, scritto);
      await Promise.all([a.waitForNavigation(), a.locator('form:has(' + campo + ') button.btn-primary').click()]);
      await vai(pag);
      ok(`P5 ${addon}: privacy scritta "${scritto}", riaperta: "${atteso}"`, await a.inputValue(campo) === atteso, await a.inputValue(campo));
    }
    await vai(pag); await a.fill(campo, 'javascript:alert(1)');
    await Promise.all([a.waitForNavigation(), a.locator('form:has(' + campo + ') button.btn-primary').click()]);
    const f5 = await flash();
    await vai(pag);
    ok(`P5 ${addon}: un indirizzo che non è un indirizzo si rifiuta e si dice, il valore resta`, /non è l.indirizzo di una pagina/.test(f5) && await a.inputValue(campo) === '/privacy-policy/', f5);
  }

  // ---------------------------------------------------------------- P6: "Pagina aggiornata", non "Pagina aggiornato"
  await vai('contenuti.php?tipo=pagine');
  const hrefPag = await a.locator('a[href*="contenuto.php?tipo=pagine&id="]').first().getAttribute('href').catch(() => null);
  if (hrefPag) {
    await vai(hrefPag);
    await Promise.all([a.waitForNavigation(), a.locator('button.btn-primary[data-valore=pubblica]').click()]);
    const f6 = await flash();
    ok('P6 pagina: il messaggio dice "Pagina aggiornata"', /Pagina aggiornata\./.test(f6), f6);
  } else ok('P6 pagina: una pagina da aprire', false);
  await vai('contenuti.php?tipo=pagine&q=nessuna-pagina-ha-questo-titolo');
  ok('P6 pagine: un elenco vuoto delle pagine non dice "Nessun pagina"', !/Nessun pagina/.test(await a.locator('main').innerText()));

  // ---------------------------------------------------------------- P7: l'anteprima di una versione in un sito in cartella
  if (CARTELLA) {
    await vai('versioni.php');
    const hv = [...new Set(await a.$$eval('a[href*="anteprima="]', as => as.map(x => x.getAttribute('href'))))].slice(0, 15);
    let viste = 0; const fuori = [];
    for (const h of hv) {
      const html = await (await a.request.get(B + '/admin/' + h.replace(/&amp;/g, '&'))).text();
      if (!/Anteprima della versione/.test(html)) continue;
      viste++;
      for (const m of html.matchAll(/\s(?:href|src)="(\/[^"]*)"/g)) if (!m[1].startsWith('//') && !m[1].startsWith(CARTELLA + '/') && m[1] !== CARTELLA) fuori.push(h + ' ' + m[1]);
    }
    ok('P7 versioni: le anteprime delle versioni (' + viste + ') hanno i link con la cartella ' + CARTELLA, viste > 0 && fuori.length === 0, fuori.slice(0, 5));
  } else ok('P7 versioni: (sito nella radice: la prova della cartella si fa con una base /test)', true);

  // ---------------------------------------------------------------- P8: gli accenti nei messaggi
  const lg = await br.newPage();
  await lg.goto(B + '/admin/login.php?motivo=ip');
  ok('P8 accenti: "non è fra quelli consentiti" (prima "non e")', /non è fra quelli consentiti/.test(await lg.locator('body').innerText()));
  await lg.close();

  // ---------------------------------------------------------------- P9: un redirect con un'emoji: un messaggio, non SQLSTATE
  await vai('seo.php?scheda=redirect');
  const fr = a.locator('form:has([name=da]):has([name=a])').first();
  await fr.locator('[name=da]').fill('/offerta-' + VINO + '/'); await fr.locator('[name=a]').fill('/contatti/');
  await Promise.all([a.waitForNavigation(), fr.locator('button[type=submit]').first().click()]);
  const f9 = await flash();
  ok('P9 redirect: con un\'emoji il pannello lo dice, senza SQLSTATE', !/SQLSTATE/.test(f9) && /emoji/.test(f9), f9);

  // ---------------------------------------------------------------- P10: ID del Meta Pixel scritto male, non più tolto in silenzio
  await vai('seo.php?scheda=analisi');
  const fa = 'form:has([name="analisi[pixel]"])';
  await a.fill('[name="analisi[pixel]"]', '12345 67890'); await invia(fa);
  await vai('seo.php?scheda=analisi');
  ok('P10 pixel: scritto con uno spazio, riaperto senza (1234567890)', await a.inputValue('[name="analisi[pixel]"]') === '1234567890', await a.inputValue('[name="analisi[pixel]"]'));
  await a.fill('[name="analisi[pixel]"]', 'Pixel 123'); await invia(fa);
  const f10 = await flash();
  await vai('seo.php?scheda=analisi');
  ok('P10 pixel: un ID sbagliato si rifiuta e si dice, il valore di prima resta', /solo di numeri/.test(f10) && await a.inputValue('[name="analisi[pixel]"]') === '1234567890', f10);
  await a.fill('[name="analisi[pixel]"]', ''); await invia(fa);

  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`); process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
