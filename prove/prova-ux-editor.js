// Controllo di UX e UI di editor, installatore, addon e sito (novembre 2026): le correzioni provate nel browser come un
// utente. Contrasti misurati, fuoco da tastiera visibile, campi con un nome, testi nuovi presenti.
// uso: node prove/prova-ux-editor.js <base di un CMS installato con il kit Attività e gli otto addon accesi> <utente> <password>
//        [base di una copia NON installata, per l'installatore] [db di prova vuoto per l'installatore, solo per il passo 2]
// Non salva niente nel sito installato: il widget aggiunto con la tastiera resta nella tela e si lascia la pagina senza salvare.
const { chromium } = require('playwright');
const [B, U, P, BINST] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 600) : '')); };

// Contrasto di ogni testo visibile (come prova-aspetti.js), con l'opacità dei genitori moltiplicata.
const contrasto = (p, esclusi) => p.evaluate((ESCLUSI) => {
  const rgba = (s) => { const m = s.match(/rgba?\(([^)]+)\)/); if (!m) return null; const v = m[1].split(/[ ,/]+/).filter(Boolean).map(Number); return { r: v[0], g: v[1], b: v[2], a: v.length > 3 ? v[3] : 1 }; };
  const sopra = (c, f) => ({ r: c.r * c.a + f.r * (1 - c.a), g: c.g * c.a + f.g * (1 - c.a), b: c.b * c.a + f.b * (1 - c.a), a: 1 });
  const lum = (c) => { const f = (x) => { x /= 255; return x <= .03928 ? x / 12.92 : Math.pow((x + .055) / 1.055, 2.4); }; return .2126 * f(c.r) + .7152 * f(c.g) + .0722 * f(c.b); };
  const sfondo = (el) => { const s = []; for (let e = el; e; e = e.parentElement) { const cs = getComputedStyle(e); if (cs.backgroundImage && cs.backgroundImage !== 'none') return null; const c = rgba(cs.backgroundColor); if (c && c.a > 0) { s.push(c); if (c.a >= 1) break; } } let b = { r: 255, g: 255, b: 255, a: 1 }; for (let i = s.length - 1; i >= 0; i--) b = sopra(s[i], b); return b; };
  const male = []; let misurati = 0;
  const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  while (w.nextNode()) {
    const t = w.currentNode; if (!t.textContent.trim()) continue;
    const el = t.parentElement; if (!el || el.closest(ESCLUSI)) continue;
    const r = el.getBoundingClientRect(); if (!r.width || !r.height) continue;
    const cs = getComputedStyle(el); if (cs.visibility !== 'visible' || cs.display === 'none' || el.closest(':disabled, [disabled]')) continue;
    let op = 1; for (let e = el; e; e = e.parentElement) op *= parseFloat(getComputedStyle(e).opacity);
    let fg = rgba(cs.color); if (!fg || fg.a === 0) continue; fg.a *= op;
    const bg = sfondo(el); if (!bg) continue; if (fg.a < 1) fg = sopra(fg, bg);
    const [a, b] = [lum(fg), lum(bg)].sort((x, y) => y - x); const c = (a + .05) / (b + .05);
    const px = parseFloat(cs.fontSize), peso = parseInt(cs.fontWeight, 10) || 400;
    const soglia = px >= 24 || (px >= 18.66 && peso >= 700) ? 3 : 4.5; misurati++;
    if (c < soglia - 0.005) male.push(`${c.toFixed(2)} "${t.textContent.trim().slice(0, 30)}" <${el.tagName.toLowerCase()} class="${typeof el.className === 'string' ? el.className : ''}">`);
  }
  return { misurati, male: [...new Set(male)] };
}, esclusi);
// Il contorno del fuoco dell'elemento attivo: almeno 2 px pieni (o un'ombra), e non tagliato da un genitore con overflow:hidden.
const fuoco = (p) => p.evaluate(() => {
  const e = document.activeElement; if (!e || e === document.body) return { visibile: false, chi: 'body' };
  const s = getComputedStyle(e); const w = parseFloat(s.outlineWidth) || 0;
  let taglio = false;
  if (w > 0 && parseFloat(s.outlineOffset) >= 0) for (let x = e.parentElement; x; x = x.parentElement) { if (getComputedStyle(x).overflow === 'hidden' && !x.matches('body, html, .ed-app')) { const r = x.getBoundingClientRect(), q = e.getBoundingClientRect(); if (q.left - w <= r.left || q.right + w >= r.right || q.top - w <= r.top || q.bottom + w >= r.bottom) taglio = true; break; } }
  return { visibile: (s.outlineStyle !== 'none' && w >= 2 && !taglio) || s.boxShadow !== 'none', chi: e.tagName + '.' + e.className, contorno: s.outlineStyle + ' ' + s.outlineWidth + ' ' + s.outlineOffset, taglio };
});
const nomiMancanti = (p, radice) => p.evaluate((R) => [...document.querySelectorAll(R + ' input:not([type=hidden]), ' + R + ' select, ' + R + ' textarea')].filter(e => e.offsetParent).filter(e => !(e.labels && e.labels.length) && !e.getAttribute('aria-label') && !e.title && !e.getAttribute('aria-labelledby')).map(e => e.tagName + '[' + e.type + '] ' + (e.closest('.ed-controllo')?.innerText || '').trim().slice(0, 30)), radice);

(async () => {
  const br = await chromium.launch();
  const err = [];
  const nuova = async (w, h) => { const c = await br.newContext({ viewport: { width: w, height: h } }); const p = await c.newPage(); p.on('pageerror', e => err.push(p.url() + ' ' + e.message.slice(0, 160))); p.on('dialog', d => d.accept()); return p; };

  // 1. L'installatore (su una copia non installata). Il passo 2 con una password sbagliata: il messaggio è in parole semplici.
  if (BINST) {
    const p = await nuova(1280, 900);
    await p.goto(BINST + '/', { waitUntil: 'networkidle' });
    await p.focus('a.btn'); let f = await fuoco(p);
    ok('installatore: il fuoco sul pulsante Avanti si vede (contorno di almeno 2 px)', f.visibile, f);
    await p.click('a.btn:has-text("Avanti")'); await p.waitForLoadState('networkidle');
    await p.fill('#host', '127.0.0.1:3308'); await p.fill('#nome', 'ub_non_esiste'); await p.fill('#utente', 'utente_che_non_esiste'); await p.fill('#password', 'sbagliata');
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    const e1 = (await p.locator('.errore').innerText().catch(() => '')).trim();
    ok('installatore: password del database sbagliata, il messaggio comincia in italiano semplice (non con SQLSTATE)', /^Utente o password del database sbagliati/.test(e1) && /Dettaglio tecnico: SQLSTATE/.test(e1), e1);
    ok('installatore: l\'errore è annunciato (role=alert)', await p.locator('.errore[role=alert]').count() === 1);
    await p.fill('#host', 'host-che-non-esiste.invalid'); await p.fill('#password', 'x');
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    const e2 = (await p.locator('.errore').innerText().catch(() => '')).trim();
    ok('installatore: server del database inesistente, si dice di controllare il campo Server', /non esiste\. Quasi sempre è localhost/.test(e2), e2);
    // Passo 3 e 4 si raggiungono solo con un database vero: si guarda l'HTML che li scrive, dal sorgente servito con la sessione.
    await p.close();
  } else ok('installatore: saltato (manca la base di una copia non installata)', true);

  // 2. L'editor della home, in Aria e in Cosmo.
  const p = await nuova(1280, 860);
  await p.goto(B + '/admin/login.php'); await p.fill('#username', U); await p.fill('#password', P);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/contenuti.php?tipo=pagine', { waitUntil: 'networkidle' });
  const idHome = await p.evaluate(() => { const a = [...document.querySelectorAll('a[href*="editor.php"]')].find(x => /id=\d+/.test(x.href) && x.closest('tr') && /Home/.test(x.closest('tr').innerText)); return a ? new URL(a.href).searchParams.get('id') : null; });
  const apriEditor = async () => { await p.goto(B + '/admin/editor.php?tipo=pagine&id=' + idHome, { waitUntil: 'networkidle' }); await p.waitForTimeout(1500); };
  await apriEditor();
  ok('editor: l\'aiuto della tavolozza sta sopra i widget', await p.evaluate(() => { const a = document.querySelector('#ed-pannello-widget .ed-aiuto-sopra'), l = document.querySelector('#ed-elenco-widget'); return !!a && !!l && (a.compareDocumentPosition(l) & Node.DOCUMENT_POSITION_FOLLOWING) > 0 && a.getBoundingClientRect().bottom < l.getBoundingClientRect().top; }));
  // la tavolozza con la tastiera: Tab dalla ricerca porta al primo widget, Invio lo aggiunge
  await p.focus('#ed-cerca-widget'); await p.keyboard.press('Tab');
  const primo = await p.evaluate(() => document.activeElement.classList.contains('ed-widget-voce') ? document.activeElement.dataset.tipo : document.activeElement.tagName);
  let f = await fuoco(p);
  ok('editor: con Tab dalla ricerca si arriva al primo widget, e il fuoco si vede', primo === 'titolo' && f.visibile && /solid/.test(f.contorno), { primo, f });
  await p.keyboard.press('Enter'); await p.waitForTimeout(600);
  ok('editor: Invio sul widget lo aggiunge alla pagina (si apre Modifica con il Titolo, la pagina ha modifiche non salvate)', /Titolo/.test(await p.locator('#ed-modifica-nome').innerText()) && /non salvate/.test(await p.locator('#ed-stato').innerText()), await p.locator('#ed-stato').innerText());
  await p.click('.ed-schede-pannello button[data-pannello=widget]');
  await p.fill('#ed-cerca-widget', 'zzqqxx'); await p.waitForTimeout(200);
  ok('editor: una ricerca senza risultati lo dice', await p.locator('#ed-widget-nessuno').isVisible() && await p.locator('.ed-gruppo-widget:visible').count() === 0);
  await p.fill('#ed-cerca-widget', 'immagine'); await p.waitForTimeout(200);
  ok('editor: con una ricerca normale il messaggio sparisce e restano solo i gruppi con risultati', !(await p.locator('#ed-widget-nessuno').isVisible()) && await p.locator('.ed-gruppo-widget:visible').count() >= 1);
  // il fuoco sui pulsanti delle misure (gruppo con overflow:hidden) e sulle schede
  for (const sel of ['.ed-misure button[data-misura=t]', '#ed-annulla', '.ed-schede-pannello button[data-pannello=sezioni]', '#ed-apri-impostazioni', '#ed-pubblica']) {
    await p.focus(sel); await p.keyboard.press('Shift+Tab'); await p.keyboard.press('Tab');
    if (sel === '#ed-annulla' && await p.locator(sel).isDisabled()) continue;
    f = await fuoco(p); ok(`editor: fuoco visibile e non tagliato su ${sel}`, f.visibile, f);
  }
  ok('editor: i pulsanti delle misure dicono quale è scelta (aria-pressed)', await p.locator('.ed-misure button[aria-pressed=true]').count() === 1);
  // un widget aperto: ogni campo di Contenuto, Stile e Avanzate ha un nome per il lettore di schermo
  const tela = p.frameLocator('.ed-tela iframe');
  await tela.locator('[data-ed-tipo="hero"]').first().click(); await p.waitForTimeout(500);
  await p.click('.ed-schede-pannello button[data-pannello=modifica]');
  for (const scheda of ['contenuto', 'stile', 'avanzate']) {
    await p.click(`#ed-schede-controlli button[data-scheda=${scheda}]`); await p.waitForTimeout(250);
    await p.evaluate(() => document.querySelectorAll('details.ed-sez').forEach(d => d.open = true)); await p.waitForTimeout(250);
    const m = await nomiMancanti(p, '#ed-controlli');
    ok(`editor, Apertura, scheda ${scheda}: ogni campo ha un nome`, m.length === 0, m);
  }
  // i colori globali: il cerchio intorno si distingue dal fondo del pannello (3 a 1, WCAG 1.4.11)
  const anello = async () => p.evaluate(() => {
    const g = document.querySelector('.ed-globale'); if (!g) return null;
    const col = getComputedStyle(g).boxShadow.match(/rgba?\([^)]+\)/)[0], fondo = getComputedStyle(document.querySelector('.ed-pannello')).backgroundColor;
    const v = (s) => s.match(/[\d.]+/g).map(Number), lum = (c) => { const f = (x) => { x /= 255; return x <= .03928 ? x / 12.92 : Math.pow((x + .055) / 1.055, 2.4); }; return .2126 * f(c[0]) + .7152 * f(c[1]) + .0722 * f(c[2]); };
    const [a, b] = [lum(v(col)), lum(v(fondo))].sort((x, y) => y - x); return +((a + .05) / (b + .05)).toFixed(2);
  });
  await p.click('#ed-schede-controlli button[data-scheda=stile]'); await p.waitForTimeout(250);
  // il riquadro delle immagini: i campi hanno un nome
  await p.click('#ed-schede-controlli button[data-scheda=contenuto]'); await p.waitForTimeout(250);
  await p.locator('.ed-riga-immagine button:has-text("Scegli")').first().click(); await p.waitForTimeout(600);
  ok('editor, finestra Immagini: cartella, ricerca e indirizzo hanno un nome', (await nomiMancanti(p, '#ed-dialogo-media')).length === 0, await nomiMancanti(p, '#ed-dialogo-media'));
  ok('editor, finestra Immagini: la casella del file non mostra il testo del browser ("Choose File")', await p.evaluate(() => { const i = document.querySelector('#ed-media-file'); const r = i.getBoundingClientRect(); return r.width <= 2 && r.height <= 2; }));
  await p.keyboard.press('Escape');
  ok('editor: la home ha l\'H1, nessun avviso', await p.locator('#ed-avviso-h1').isHidden());

  // 3. Lo stesso editor in Cosmo: contrasto e anello dei colori globali
  for (const asp of ['cosmo', 'aria']) { // alla fine resta Aria, l'aspetto di partenza
    await p.goto(B + '/admin/account.php', { waitUntil: 'networkidle' });
    await p.click(`label.asp-voce:has(input[value=${asp}])`);
    await Promise.all([p.waitForNavigation(), p.click('button:has-text("Usa questo aspetto")')]);
    await apriEditor();
    let c = await contrasto(p, '.ed-tela-cornice, .ed-mini, option, select, [aria-hidden=true], iframe');
    ok(`${asp}: editor, contrasto di ogni testo (${c.misurati}), opacità comprese`, c.male.length === 0, c.male);
    await tela.locator('[data-ed-tipo="hero"]').first().click(); await p.waitForTimeout(400);
    await p.click('.ed-schede-pannello button[data-pannello=modifica]');
    await p.click('#ed-schede-controlli button[data-scheda=stile]'); await p.waitForTimeout(300);
    const a = await anello();
    ok(`${asp}: il cerchio dei colori globali si vede sul pannello (almeno 1,5 a 1 contro il fondo, prima 1,1 su Cosmo)`, a !== null && a >= 1.5, a);
    c = await contrasto(p, '.ed-tela-cornice, .ed-mini, option, select, [aria-hidden=true], iframe');
    ok(`${asp}: editor, scheda Stile aperta, contrasto`, c.male.length === 0, c.male);
  }

  // 4. Header (Theme Builder): niente avviso H1 e niente indirizzo tecnico
  await p.goto(B + '/admin/modelli.php', { waitUntil: 'networkidle' });
  const ed = await p.locator('a[href*="editor.php?tipo=modelli"]').first().getAttribute('href');
  await p.goto(B + '/admin/' + ed.replace(/^.*admin\//, ''), { waitUntil: 'networkidle' }); await p.waitForTimeout(1500);
  const sotto = await p.locator('#ed-percorso').innerText();
  ok('header nell\'editor: sotto il nome si dice cos\'è (non /?preview=1&header=...)', /Header: la testata delle pagine/.test(sotto) && !/preview/.test(sotto), sotto);
  ok('header nell\'editor: nessun avviso del titolo H1 (un header non lo ha)', await p.locator('#ed-avviso-h1').isHidden() && await p.evaluate(() => document.querySelector('#ed-titolo-pagina').style.color === ''));

  // 5. L'editor al telefono: un avviso chiaro
  await p.setViewportSize({ width: 390, height: 844 }); await p.waitForTimeout(300);
  ok('editor a 390 px: l\'avviso "si usa da computer o tablet" si vede', await p.locator('.ed-solo-computer').isVisible());
  await p.setViewportSize({ width: 1280, height: 860 }); await p.waitForTimeout(200);
  ok('editor a 1280 px: l\'avviso del telefono non c\'è', await p.locator('.ed-solo-computer').isHidden());
  await p.goto(B + '/admin/', { waitUntil: 'networkidle' }); // si lascia l'editor senza salvare

  // 6. Addon: agenda e vetrina
  await p.goto(B + '/admin/addon-pagina.php?a=prenotazioni&p=agenda', { waitUntil: 'networkidle' });
  const barra = await p.locator('.pa-barra').innerText();
  ok('Prenotazioni, Agenda: i pulsanti dicono "Settimana prima" e "Settimana dopo"', /Settimana prima/.test(barra) && /Settimana dopo/.test(barra), barra);
  await p.goto(B + '/admin/addon-pagina.php?a=prenotazioni&p=agenda&vista=giorno', { waitUntil: 'networkidle' });
  ok('Prenotazioni, Agenda per giorno: "Giorno prima" e "Giorno dopo"', /Giorno prima/.test(await p.locator('.pa-barra').innerText()));
  for (const pg of ['prodotti', 'ordini']) {
    await p.goto(B + `/admin/addon-pagina.php?a=vetrina&p=${pg}`, { waitUntil: 'networkidle' });
    ok(`Vetrina, ${pg}: la casella di ricerca ha un nome`, (await nomiMancanti(p, '.admin-main')).length === 0, await nomiMancanti(p, '.admin-main'));
  }
  // il negozio da visitatore: il numero del carrello e il filtro scelto si leggono (4,5 a 1)
  const v = await nuova(1280, 900);
  await v.goto(B + '/negozio/', { waitUntil: 'networkidle' });
  const cn = await contrasto(v, 'iframe, option, select, [aria-hidden=true]');
  const vt = cn.male.filter(x => /vt-|"Tutti"/.test(x));
  ok('Vetrina da visitatore: numero del carrello e filtro scelto con contrasto di almeno 4,5', vt.length === 0, vt);

  ok('nessun errore JavaScript', err.length === 0, err.slice(0, 5));
  console.log(`\n${n - ko} su ${n} OK`);
  await br.close(); process.exit(ko ? 1 : 0);
})().catch(e => { console.log('ECCEZIONE', e.message.slice(0, 500)); process.exit(1); });
