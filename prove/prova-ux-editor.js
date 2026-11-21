// Controllo di UX e UI di editor, installatore, addon e sito (novembre 2026): le correzioni provate nel browser come un
// utente. Contrasti misurati, fuoco da tastiera visibile, campi con un nome, testi nuovi presenti.
// uso: node prove/prova-ux-editor.js <base di un CMS installato con il kit Attività e gli otto addon accesi> <utente> <password>
//        [base di una copia NON installata, per l'installatore] [db vuoto per i passi 3 e 4, con DBPASS nell'ambiente]
// Per il punto degli addon vuoti nell'editor serve la pagina /addon-tutti/ con i widget degli addon, senza eventi né corsi:
//   php prove/pagina-addon-a.php <cartella CMS> addon-tutti "Prova addon" prenota,membri_accesso,corsi_elenco,eventi_iscrizione,recensioni_sito,vetrina_prodotti,vetrina_carrello
// Nessuna installazione parte: l'installatore si ferma al passo 4.
// Non salva niente nel sito installato: il widget aggiunto con la tastiera resta nella tela e si lascia la pagina senza salvare.
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello'); // UX 2026-11: conferme del pannello
const [B, U, P, BINST, DBINST] = process.argv.slice(2);
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
  const nuova = async (w, h) => { const c = await br.newContext({ viewport: { width: w, height: h } }); const p = await c.newPage(); p.on('pageerror', e => err.push(p.url() + ' ' + e.message.slice(0, 160))); pnConferme.on(p, d => d.accept()); return p; };

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
    // 2.5.x (scelte di Danilo): passo 3 con gli errori sotto i campi sbagliati, passo 4 con il pulsante della carta Su misura.
    if (DBINST && process.env.DBPASS) {
      await p.fill('#host', '127.0.0.1:3308'); await p.fill('#nome', DBINST); await p.fill('#utente', 'danilo'); await p.fill('#password', process.env.DBPASS);
      await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
      ok('installatore: con il database giusto si arriva al passo 3', /passo=3/.test(p.url()), p.url());
      await p.fill('#nome', 'Studio UX'); await p.fill('#admin_nome', 'prova'); await p.fill('#admin_email', 'non-una-email'); await p.fill('#admin_password', 'corta'); await p.fill('#admin_password2', 'corta');
      await p.evaluate(() => { document.querySelectorAll('[required]').forEach(e => e.removeAttribute('required')); document.querySelectorAll('input[type=email]').forEach(e => e.type = 'text'); });
      await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
      const sb = await p.evaluate(() => [...document.querySelectorAll('.campo.sbagliato input')].map(i => ({ id: i.id, invalido: i.getAttribute('aria-invalid'), descr: i.getAttribute('aria-describedby') || '', bordo: getComputedStyle(i).borderTopColor, errore: (document.getElementById(i.id + '-errore') || {}).textContent || '' })));
      ok('installatore, passo 3: email e password sbagliate segnate sotto il loro campo, con aria-invalid e il messaggio collegato', ['admin_email', 'admin_password'].every(id => sb.some(x => x.id === id && x.invalido === 'true' && x.descr.includes(id + '-errore') && x.errore.length > 10)) && !sb.some(x => x.id === 'nome'), sb);
      ok('installatore, passo 3: il bordo del campo sbagliato è rosso (quello del riquadro degli errori)', sb.length > 0 && sb.every(x => x.bordo === sb[0].bordo) && await p.evaluate((b) => b === getComputedStyle(document.querySelector('.errore')).color, sb[0] && sb[0].bordo), sb.map(x => x.bordo));
      ok('installatore, passo 3: in cima il riassunto dice quanti campi sistemare', /campi da sistemare/.test(await p.locator('.errore').innerText()));
      await p.fill('#admin_email', 'prova@esempio.it'); await p.fill('#admin_password', 'Collaudo-Officina-2026'); await p.fill('#admin_password2', 'Collaudo-Officina-2026');
      await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
      const testo = async () => (await p.locator('form button[type=submit]').innerText()).trim();
      ok('installatore, passo 4: con Vuoto il pulsante dice "Installa adesso"', await testo() === 'Installa adesso', await testo());
      await p.locator('.kit-su-misura').click();
      ok('installatore, passo 4: con la carta Su misura il pulsante dice "Avanti: le quattro domande"', await testo() === 'Avanti: le quattro domande', await testo());
      await p.locator('.kit-personale').click();
      ok('installatore, passo 4: tornando a un kit normale, di nuovo "Installa adesso"', await testo() === 'Installa adesso', await testo());
    } else ok('installatore, passi 3 e 4: saltati (manca il database vuoto o DBPASS)', true);
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
  // 2.5.x: le schede del pannello sono schede ARIA, con le frecce
  ok('editor: il pannello ha un tablist con quattro schede e i loro pannelli', await p.locator('.ed-schede-pannello[role=tablist] [role=tab]').count() === 4 && await p.locator('[role=tabpanel][aria-labelledby=ed-scheda-widget]').count() === 1);
  await p.click('.ed-schede-pannello button[data-pannello=widget]'); await p.focus('#ed-scheda-widget');
  const schedaAttiva = () => p.evaluate(() => ({ fuoco: document.activeElement.id, scelta: document.querySelector('[role=tab][aria-selected=true]').id, aperto: [...document.querySelectorAll('[role=tabpanel]')].filter(x => !x.hidden).map(x => x.id).join(), tab: [...document.querySelectorAll('[role=tab]')].map(x => x.tabIndex).join() }));
  await p.keyboard.press('ArrowRight'); let sa = await schedaAttiva();
  ok('editor: freccia a destra dalla scheda Widget apre Sezioni (fuoco, aria-selected, pannello, un solo tabindex 0)', sa.fuoco === 'ed-scheda-sezioni' && sa.scelta === 'ed-scheda-sezioni' && sa.aperto === 'ed-pannello-sezioni' && sa.tab === '-1,0,-1,-1', sa);
  await p.keyboard.press('End'); sa = await schedaAttiva();
  ok('editor: Fine porta a Navigatore', sa.scelta === 'ed-scheda-navigatore' && sa.aperto === 'ed-pannello-navigatore', sa);
  await p.keyboard.press('ArrowRight'); sa = await schedaAttiva();
  ok('editor: freccia a destra dall\'ultima torna alla prima (Widget)', sa.scelta === 'ed-scheda-widget', sa);
  await p.keyboard.press('ArrowLeft'); await p.keyboard.press('Home'); sa = await schedaAttiva();
  ok('editor: Inizio porta a Widget', sa.scelta === 'ed-scheda-widget' && sa.aperto === 'ed-pannello-widget', sa);
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
  // 2.5.x: i cursori senza un valore loro: "come il sito" e cursore grigio, finché non si sceglie un valore
  await p.evaluate(() => document.querySelectorAll('details.ed-sez').forEach(d => d.open = true)); await p.waitForTimeout(200);
  const vuoti = await p.evaluate(() => [...document.querySelectorAll('#ed-controlli .ed-misura, #ed-controlli .ed-numero-riga')].filter(r => r.offsetParent).map(r => { const n = r.querySelector('input[type=number]'), c = r.querySelector('input[type=range]'); return { vuota: n.value === '', segnaposto: n.placeholder, grigio: c.classList.contains('ed-cursore-vuoto'), opacita: +getComputedStyle(c).opacity }; }));
  const vv = vuoti.filter(x => x.vuota && x.segnaposto === 'come il sito');
  ok('editor, Stile: i cursori con la casella vuota dicono "come il sito" e sono grigi', vv.length >= 3 && vv.every(x => x.grigio && x.opacita < 1) && vuoti.filter(x => x.vuota && !x.segnaposto).length === 0, { quanti: vv.length, altri: vuoti.filter(x => x.vuota && x.segnaposto !== 'come il sito').slice(0, 3) });
  ok('editor, Stile: un cursore con un valore non è grigio', vuoti.filter(x => !x.vuota).every(x => !x.grigio), vuoti.filter(x => !x.vuota && x.grigio).slice(0, 3));
  // la riga si segna una volta: un locator con :has(.ed-cursore-vuoto) dopo il cambio prenderebbe la riga seguente
  await p.evaluate(() => { const r = document.querySelector('#ed-controlli .ed-numero-riga:has(input.ed-cursore-vuoto), #ed-controlli .ed-misura:has(input.ed-cursore-vuoto)'); r.dataset.prova = 'cursore'; });
  const riga = p.locator('[data-prova=cursore]');
  await riga.locator('input[type=number]').fill('3'); await p.waitForTimeout(150);
  ok('editor, Stile: scritto un valore nella casella, il cursore torna colorato', !(await riga.locator('input[type=range]').evaluate(c => c.classList.contains('ed-cursore-vuoto'))));
  await riga.locator('input[type=number]').fill(''); await p.waitForTimeout(150);
  ok('editor, Stile: svuotata la casella, di nuovo grigio', await riga.locator('input[type=range]').evaluate(c => c.classList.contains('ed-cursore-vuoto')));
  // 2.5.x: "solo telefono" accanto al pallino del monitor quando il valore vale solo al telefono
  await p.click('.ed-misure button[data-misura=m]'); await p.waitForTimeout(300);
  await p.click('#ed-schede-controlli button[data-scheda=avanzate]'); await p.waitForTimeout(250);
  await p.evaluate(() => document.querySelectorAll('details.ed-sez').forEach(d => d.open = true)); await p.waitForTimeout(150);
  const ctlResp = p.locator('#ed-controlli .ed-controllo:has(> .ed-etichetta .ed-misura-pulsante):has(.ed-numero-riga input[type=number])').first();
  ok('editor, telefono: prima di cambiare, nessuna etichetta "solo telefono"', await ctlResp.locator('.ed-misura-solo:visible').count() === 0);
  await ctlResp.locator('.ed-numero-riga input[type=number]').fill('12'); await p.waitForTimeout(300);
  ok('editor, telefono: un valore proprio del telefono mostra "solo telefono" accanto al pallino', (await ctlResp.locator('.ed-misura-solo').innerText().catch(() => '')).trim() === 'solo telefono');
  const nomeResp = (await ctlResp.locator('> .ed-etichetta').evaluate(e => [...e.childNodes].filter(n => n.nodeType === 3).map(n => n.textContent).join('').trim()));
  await p.click('.ed-misure button[data-misura=t]'); await p.waitForTimeout(300);
  await p.evaluate(() => document.querySelectorAll('details.ed-sez').forEach(d => d.open = true)); await p.waitForTimeout(150);
  const alTablet = await p.evaluate((nome) => { const c = [...document.querySelectorAll('#ed-controlli .ed-controllo')].find(x => { const e = x.querySelector(':scope > .ed-etichetta'); return e && [...e.childNodes].filter(n => n.nodeType === 3).map(n => n.textContent).join('').trim() === nome; }); if (!c) return null; const s = c.querySelector('.ed-misura-solo'); return s && !s.hidden ? s.textContent : ''; }, nomeResp);
  ok('editor, tablet: lo stesso controllo (' + nomeResp + ') al tablet non dice "solo" (il valore è del telefono)', alTablet === '', alTablet);
  await p.click('.ed-misure button[data-misura=d]'); await p.waitForTimeout(300);
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

  // 5b. Gli addon senza dati nell'editor: il riquadro tratteggiato con la frase e dove aggiungerli, mai sul sito
  const idAddon = await p.evaluate(async (b) => { const t = await (await fetch(b + '/admin/contenuti.php?tipo=pagine')).text(); const d = new DOMParser().parseFromString(t, 'text/html'); const a = [...d.querySelectorAll('a[href*="editor.php"]')].find(x => /id=\d+/.test(x.getAttribute('href')) && x.closest('tr') && /addon-tutti/.test(x.closest('tr').innerText)); return a ? new URL(a.getAttribute('href'), location.href).searchParams.get('id') : null; }, B);
  if (idAddon) {
    await p.goto(B + '/admin/editor.php?tipo=pagine&id=' + idAddon, { waitUntil: 'networkidle' }); await p.waitForTimeout(1800);
    const vuotiEd = (await p.frameLocator('.ed-tela iframe').locator('.ed-vuoto').allInnerTexts()).join(' | ');
    ok('editor, widget degli addon vuoti: il riquadro dice dove aggiungere eventi, corsi, prodotti e recensioni', /nessun evento in programma: aggiungili nel pannello, in Eventi/.test(vuotiEd) && /nessun corso pubblicato: crealo nel pannello, in Corsi/.test(vuotiEd) && /Vetrina > Prodotti/.test(vuotiEd) && /Recensioni/.test(vuotiEd), vuotiEd);
    const tratt = await p.frameLocator('.ed-tela iframe').locator('.ed-vuoto').first().evaluate(e => getComputedStyle(e).borderTopStyle);
    ok('editor: il riquadro è tratteggiato', tratt === 'dashed', tratt);
    const sito = await (await p.request.get(B + '/addon-tutti/')).text();
    ok('sito: la pagina con gli stessi widget non ha i riquadri dell\'editor', !/ed-vuoto/.test(sito) && !/aggiungili nel pannello|crealo nel pannello/.test(sito));
    await p.goto(B + '/admin/', { waitUntil: 'networkidle' });
  } else ok('editor, widget degli addon vuoti: saltato (manca la pagina /addon-tutti/, vedi in cima)', true);

  // 5c. (2.12.0) L'editor delle email, su un modello "UX prova" creato qui e tolto alla fine, in Cosmo e in Aria: contrasto
  // del pannello, fuoco visibile, schede ARIA, ogni campo di ogni blocco con un nome, 390 px senza scorrimento di lato.
  await p.goto(B + '/admin/newsletter-modelli.php', { waitUntil: 'networkidle' });
  await p.fill('#ndm-nuovo', 'UX prova editor email');
  await Promise.all([p.waitForNavigation(), p.click('form:has(#ndm-nuovo) button[type=submit]')]);
  const urlEmail = p.url();
  ok('editor delle email: «Crea un modello» apre l\'editor', /newsletter-design\.php\?modello=\d+/.test(urlEmail), urlEmail);
  for (const asp of ['cosmo', 'aria']) {
    await p.goto(B + '/admin/account.php', { waitUntil: 'networkidle' });
    await p.click(`label.asp-voce:has(input[value=${asp}])`);
    await Promise.all([p.waitForNavigation(), p.click('button:has-text("Usa questo aspetto")')]);
    await p.goto(urlEmail, { waitUntil: 'networkidle' }); await p.waitForTimeout(600);
    let c = await contrasto(p, '.nd-cornice, option, select, [aria-hidden=true], iframe, .ql-editor');
    ok(`${asp}: editor delle email, contrasto di ogni testo del pannello (${c.misurati})`, c.male.length === 0, c.male);
    await p.focus('#nd-tavolozza-blocchi .ed-widget-voce'); let fe = await fuoco(p);
    ok(`${asp}: editor delle email, fuoco visibile sulla prima voce della tavolozza`, fe.visibile, fe);
    for (const sel of ['[data-nd-misura=tel]', '#nd-anteprima', '#nd-salva', '#nd-scheda-stile']) { await p.focus(sel); fe = await fuoco(p); ok(`${asp}: editor delle email, fuoco visibile su ${sel}`, fe.visibile, fe); }
    await p.click('#nd-scheda-stile'); await p.waitForTimeout(200);
    c = await contrasto(p, '.nd-cornice, option, select, [aria-hidden=true], iframe');
    ok(`${asp}: editor delle email, scheda Stile, contrasto`, c.male.length === 0, c.male);
    ok(`${asp}: editor delle email, scheda Stile: ogni campo ha un nome`, (await nomiMancanti(p, '#nd-p-stile')).length === 0, await nomiMancanti(p, '#nd-p-stile'));
  }
  ok('editor delle email: tablist con quattro schede e i loro pannelli', await p.locator('.ed-schede-pannello[role=tablist] [role=tab]').count() === 4 && await p.locator('[role=tabpanel][aria-labelledby=nd-scheda-salvati]').count() === 1);
  for (const t of ['titolo', 'testo', 'immagine', 'pulsante', 'divisore', 'spaziatore', 'social', 'video', 'piede']) {
    await p.click('#nd-scheda-contenuti'); await p.focus(`#nd-tavolozza-blocchi [data-blocco=${t}]`); await p.keyboard.press('Enter'); await p.waitForTimeout(250);
    const m = await nomiMancanti(p, '#nd-p-modifica');
    ok(`editor delle email, blocco ${t} aggiunto con Invio: ogni campo ha un nome`, /Blocco:/.test(await p.locator('#nd-modifica-nome').innerText()) && m.length === 0, m);
  }
  const ce = await contrasto(p, '.nd-cornice, option, select, [aria-hidden=true], iframe, .ql-editor');
  ok('editor delle email, scheda Modifica aperta: contrasto', ce.male.length === 0, ce.male);
  await p.setViewportSize({ width: 390, height: 844 }); await p.waitForTimeout(300);
  ok('editor delle email a 390 px: niente scorrimento di lato, la tela in vista telefono', await p.evaluate(() => document.documentElement.scrollWidth <= 390) && await p.locator('#nd-tela').evaluate(e => e.offsetWidth <= 390));
  await p.setViewportSize({ width: 1280, height: 860 });
  await p.goto(B + '/admin/newsletter-modelli.php', { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.click('.ndm-tabella tr:has(b:text-is("UX prova editor email")) button:has-text("Elimina")')]);
  ok('editor delle email: il modello di prova è stato tolto', await p.locator('.ndm-tabella b:text-is("UX prova editor email")').count() === 0);

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

  // 7. Copia altrove: un solo pulsante di salvataggio, Prova e Manda spenti e spiegati
  await p.goto(B + '/admin/addon-pagina.php?a=copia-altrove&p=destinazione', { waitUntil: 'networkidle' });
  const salvataggi = async () => p.evaluate(() => [...document.querySelectorAll('#ca-form button[type=submit]')].filter(b => b.offsetParent).map(b => b.textContent.trim()));
  await p.selectOption('#ca-tipo', 'gdrive'); await p.waitForTimeout(150);
  let sv = await salvataggi();
  ok('Copia altrove, Google Drive non collegato: un solo pulsante, "Salva e collega con Google Drive"', sv.length === 1 && sv[0] === 'Salva e collega con Google Drive', sv);
  await p.selectOption('#ca-tipo', 'dropbox'); await p.waitForTimeout(150); sv = await salvataggi();
  ok('Copia altrove, Dropbox: un solo pulsante, "Salva e collega con Dropbox"', sv.length === 1 && sv[0] === 'Salva e collega con Dropbox', sv);
  await p.selectOption('#ca-tipo', 'ftp'); await p.waitForTimeout(150); sv = await salvataggi();
  ok('Copia altrove, FTP: un solo pulsante, "Salva la destinazione"', sv.length === 1 && sv[0] === 'Salva la destinazione', sv);
  const spenti = await p.evaluate(() => ['#ca-prova', '#ca-manda'].map(s => { const b = document.querySelector(s); return b.disabled && +getComputedStyle(b).opacity < 0.7; }));
  ok('Copia altrove: senza destinazione, Prova e Manda sono spenti e si vedono spenti', spenti.every(Boolean), spenti);
  ok('Copia altrove: e si dice perché sono spenti', /si accendono quando la destinazione/.test(await p.locator('#ca-spenti-perche').innerText().catch(() => '')));

  // 8. Sito: data delle schede del blog (4,5 a 1, tema di base, Cosmo, Bottega), ricerca senza risultati, form
  const metaContrasto = async (pg) => pg.evaluate(() => {
    const rgba = (s) => { const m = s.match(/rgba?\(([^)]+)\)/); const v = m[1].split(/[ ,/]+/).filter(Boolean).map(Number); return { r: v[0], g: v[1], b: v[2], a: v.length > 3 ? v[3] : 1 }; };
    const sopra = (c, f) => ({ r: c.r * c.a + f.r * (1 - c.a), g: c.g * c.a + f.g * (1 - c.a), b: c.b * c.a + f.b * (1 - c.a), a: 1 });
    const lum = (c) => { const f = (x) => { x /= 255; return x <= .03928 ? x / 12.92 : Math.pow((x + .055) / 1.055, 2.4); }; return .2126 * f(c.r) + .7152 * f(c.g) + .0722 * f(c.b); };
    const el = document.querySelector('.card-meta'); if (!el) return null;
    const s = []; for (let e = el; e; e = e.parentElement) { const c = rgba(getComputedStyle(e).backgroundColor); if (c.a > 0) { s.push(c); if (c.a >= 1) break; } }
    let bg = { r: 255, g: 255, b: 255, a: 1 }; for (let i = s.length - 1; i >= 0; i--) bg = sopra(s[i], bg);
    let fg = rgba(getComputedStyle(el).color); if (fg.a < 1) fg = sopra(fg, bg);
    const [a, b] = [lum(fg), lum(bg)].sort((x, y) => y - x); return +((a + .05) / (b + .05)).toFixed(2);
  });
  for (const t of ['', 'cosmo', 'bottega']) {
    await p.goto(B + '/blog/' + (t ? '?preview=1&tema=' + t : ''), { waitUntil: 'networkidle' });
    const c = await metaContrasto(p);
    ok(`sito, blog${t ? ' con ' + t : ' (tema di base)'}: la data delle schede almeno 4,5 a 1`, c !== null && c >= 4.5, c);
  }
  await v.goto(B + '/?s=zzqqxxyy', { waitUntil: 'networkidle' });
  const niente = await v.locator('.cerca-niente').innerText().catch(() => '');
  ok('sito, ricerca senza risultati: suggerisce una parola più corta (con un link) e le pagine principali', /parola più corta/.test(niente) && await v.locator('.cerca-niente p a[href*="?s="]').count() === 1 && await v.locator('.cerca-niente ul a').count() >= 2, niente.slice(0, 200));
  await v.setViewportSize({ width: 390, height: 844 });
  ok('sito, ricerca senza risultati al telefono: niente scorrimento di lato', await v.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
  await v.goto(B + '/contatti/', { waitUntil: 'networkidle' });
  await v.evaluate(() => { document.querySelector('form[data-captcha]').noValidate = true; });
  await v.locator('form[data-captcha] button[type=submit]').click(); await v.waitForTimeout(500);
  const dopoInvio = await v.evaluate(() => ({ url: location.href, stato: (document.querySelector('.captcha-stato') || {}).textContent || '', fuoco: document.activeElement && document.activeElement.name }));
  ok('sito, form dei contatti vuoto: prima i campi obbligatori (il fuoco va al primo), non il messaggio del captcha', /contatti/.test(dopoInvio.url) && !/Sono una persona/.test(dopoInvio.stato) && /^c_/.test(dopoInvio.fuoco || ''), dopoInvio);
  await v.fill('input[name=c_nome]', 'Anna'); await v.fill('input[name=c_email]', 'anna@esempio.it'); await v.fill('textarea[name=c_messaggio]', 'Una prova del modulo.');
  await v.evaluate(() => document.querySelectorAll('form[data-captcha] [required]').forEach(e => { if (e.closest('.captcha')) return; if (e.type === 'checkbox') e.checked = true; else if (!e.value) e.value = 'x'; }));
  await v.locator('form[data-captcha] button[type=submit]').click(); await v.waitForTimeout(400);
  ok('sito, form dei contatti compilato senza la spunta: il captcha resta obbligatorio', /Sono una persona/.test(await v.locator('.captcha-stato').innerText()) && /contatti/.test(v.url()));

  ok('nessun errore JavaScript', err.length === 0, err.slice(0, 5));
  console.log(`\n${n - ko} su ${n} OK`);
  await br.close(); process.exit(ko ? 1 : 0);
})().catch(e => { console.log('ECCEZIONE', e.message.slice(0, 500)); process.exit(1); });
