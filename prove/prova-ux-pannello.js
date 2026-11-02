// UX 2026-11: il pannello si legge e si usa in tutti e quattro gli aspetti (Aria, Cosmo, Bottega, Lavagna).
// Per ogni aspetto (scelto da Il mio accesso, come farebbe una persona) apre ogni pagina del menu e alcune pagine interne
// e misura nel browser:
//  - i titoli dei riquadri: fascia con un fondo diverso dal corpo del riquadro, testo almeno 4,5:1 sulla fascia;
//  - i campi (testo, tendine, aree di testo): bordo almeno 3:1 sul fondo che li circonda (WCAG 1.4.11);
//  - i testi: almeno 4,5:1 sul loro fondo (3:1 se grandi: 24 px, o 18,66 px in grassetto) (WCAG 1.4.3);
//  - il testo d'esempio dei campi (segnaposto) almeno 4,5:1 sul fondo del campo;
//  - la scelta di un file: il pulsante del browser ha l'aspetto di un pulsante del pannello;
//  - a 390 px nessuna pagina scorre di lato.
// Alla fine rimette l'aspetto Aria.
// uso: node prove/prova-ux-pannello.js <base> <utente> <password> [aspetti separati da virgola] [filtro pagine, regex]
const { chromium } = require('playwright');
const [B, UTENTE, PASSWORD, ASPETTI = 'aria,cosmo,bottega,lavagna', FILTRO = ''] = process.argv.slice(2);
let n = 0, ko = 0;
const ok = (t, c, extra = '') => { n++; if (!c) ko++; if (!c || process.env.TUTTO) console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 900) : '')); };
const PIU = ['seo.php?scheda=motori', 'seo.php?scheda=copertura', 'seo.php?scheda=prestazioni', 'seo.php?scheda=analisi', 'seo.php?scheda=globale', 'seo.php?scheda=schema',
  'seo.php?scheda=redirect', 'seo.php?scheda=registro', 'newsletter.php?scheda=liste', 'newsletter.php?scheda=impostazioni', 'account.php', 'password.php', 'newsletter-campagna.php',
  'tipo-sito.php', 'fine-articolo.php', 'sicurezza.php?scheda=impostazioni', 'sicurezza.php?scheda=registro', 'consegna.php?scheda=dominio',
  'form.php?id=1', // 2.7.0: la scheda di un form, con il riquadro Email di conferma
  // 2.8.0: Contatti, scheda del contatto, Attributi dei contatti (le voci del menu ci sono già)
  'newsletter-contatti.php?scheda=liste', 'newsletter-contatto.php?id=1', 'newsletter-contatto.php?id=1&scheda=cronologia', 'newsletter-contatto.php?id=1&scheda=liste',
  'newsletter-contatto.php?id=1&scheda=preferenze', 'newsletter-contatto.php?id=5&scheda=preferenze', 'newsletter-contatto.php?nuovo=1', 'newsletter-attributi.php?scheda=calcolati', 'newsletter-attributi.php?scheda=globali',
  // 2.9.0: Importa contatti (passo 1 e importazioni recenti)
  'newsletter-importa.php',
  // 2.10.0: Segmenti (elenco, modelli, costruttore), Aggiungi filtro aperto nella pagina Contatti
  'newsletter-segmenti.php', 'newsletter-segmenti.php?nuovo=1', 'newsletter-segmenti.php?nuovo=1&modello=coinvolti', 'newsletter-contatti.php?filtro=1',
  // 2.11.0: Crea una campagna, Duplica, le viste, il riepilogo con ogni sezione aperta (la campagna 1 deve esserci)
  'newsletter.php?crea=1', 'newsletter.php?duplica=1', 'newsletter.php?vista=bozze', 'newsletter-campagna.php?id=1', 'newsletter-campagna.php?id=1&apri=mittente',
  'newsletter-campagna.php?id=1&apri=destinatari', 'newsletter-campagna.php?id=1&apri=oggetto', 'newsletter-campagna.php?id=1&apri=design', 'newsletter-campagna.php?id=1&apri=impostazioni',
  'newsletter-campagna.php?id=1&apri=prova',
  // 2.12.0: Modelli (i tuoi, la galleria); la voce del menu c'è già
  'newsletter-modelli.php?scheda=galleria',
  // 2.13.0: il test A/B (la campagna 2 è un test A/B sul contenuto: sezione Test A/B, Design e Anteprima della versione B)
  'newsletter-campagna.php?id=2&apri=ab', 'newsletter-campagna.php?id=2&versione=b&apri=design', 'newsletter-campagna.php?id=2&versione=b&apri=prova'];

// La misura dentro la pagina: tutto quello che serve sta qui, la pagina non carica niente.
function misura() {
  const rgb = (s) => { const m = (s || '').match(/rgba?\(([^)]+)\)/); if (!m) return null; const p = m[1].split(/[ ,/]+/).filter(Boolean).map(Number); return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 }; };
  const lum = (c) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }; return 0.2126 * f(c.r) + 0.7152 * f(c.g) + 0.0722 * f(c.b); };
  const cr = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
  const sopra = (c, f) => ({ r: c.r * c.a + f.r * (1 - c.a), g: c.g * c.a + f.g * (1 - c.a), b: c.b * c.a + f.b * (1 - c.a), a: 1 });
  // Il fondo che si vede dietro un elemento: si sale finche si trova un colore pieno, componendo le trasparenze.
  const fondo = (el) => {
    const strati = []; let e = el;
    while (e && e.nodeType === 1) {
      const s = getComputedStyle(e);
      if (s.backgroundImage && s.backgroundImage !== 'none' && !/^url\(/.test(s.backgroundImage) && e !== document.documentElement) return null; // gradiente: non misurabile
      const c = rgb(s.backgroundColor); if (c && c.a > 0) { strati.push(c); if (c.a >= 1) break; }
      e = e.parentElement;
    }
    let f = { r: 255, g: 255, b: 255, a: 1 };
    for (let i = strati.length - 1; i >= 0; i--) f = sopra(strati[i], f);
    return f;
  };
  const visibile = (el) => { const b = el.getBoundingClientRect(); if (b.width < 2 || b.height < 2) return false; const s = getComputedStyle(el); return s.visibility !== 'hidden' && s.display !== 'none'; };
  const trasparente = (el) => { for (let e = el; e && e.nodeType === 1; e = e.parentElement) if (+getComputedStyle(e).opacity < 1) return true; return false; };
  const nome = (el) => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '');
  const r = { scroll: document.documentElement.scrollWidth, testi: [], bordi: [], fasce: [], fasceKo: [], segnaposto: [], file: [] };

  // Testi: ogni elemento con un testo suo (nodo di testo diretto non vuoto).
  for (const el of document.querySelectorAll('body *')) {
    if (/^(SCRIPT|STYLE|NOSCRIPT|OPTION|SVG|PATH|TEXTAREA|SELECT|INPUT|IFRAME|TEMPLATE)$/i.test(el.tagName)) continue;
    if (el.closest('[hidden], dialog:not([open]), .ql-editor, [aria-hidden=true], .asp-mini, [data-pannello]:not(html)')) continue;
    const testo = [...el.childNodes].filter(x => x.nodeType === 3).map(x => x.textContent).join('').trim();
    if (!testo || !visibile(el) || trasparente(el)) continue;
    if (el.closest('[disabled], .disabled, :disabled')) continue;
    const s = getComputedStyle(el); const c = rgb(s.color); const f = fondo(el); if (!c || !f) continue;
    const cc = c.a < 1 ? sopra(c, f) : c;
    const px = parseFloat(s.fontSize), grassetto = +s.fontWeight >= 700;
    const soglia = (px >= 24 || (px >= 18.66 && grassetto)) ? 3 : 4.5;
    const v = cr(cc, f);
    if (v < soglia) r.testi.push(nome(el) + ' "' + testo.slice(0, 30) + '" ' + v.toFixed(2));
  }
  // Campi: bordo contro il fondo intorno (quello del genitore).
  for (const el of document.querySelectorAll('.admin-content input:not([type=checkbox]):not([type=radio]):not([type=hidden]):not([type=submit]):not([type=button]):not([type=color]):not([type=range]):not([type=image]), .admin-content select, .admin-content textarea, :is(.login-box, .box) input:not([type=checkbox]):not([type=hidden])')) {
    if (!visibile(el) || trasparente(el) || el.closest('[hidden]')) continue;
    const s = getComputedStyle(el);
    if (s.borderTopStyle === 'none' || parseFloat(s.borderTopWidth) === 0) { r.bordi.push(nome(el) + ' senza bordo'); continue; }
    const fuori = fondo(el.parentElement); const b = rgb(s.borderTopColor); if (!fuori || !b) continue;
    const v = cr(b.a < 1 ? sopra(b, fuori) : b, fuori);
    if (v < 3) r.bordi.push(nome(el) + ' ' + v.toFixed(2));
    if ((el.placeholder || '') !== '' && el.type !== 'file') {
      const p = rgb(getComputedStyle(el, '::placeholder').color); const fc = fondo(el);
      if (p && fc) { const vp = cr(p.a < 1 ? sopra(p, fc) : p, fc); if (vp < 4.5) r.segnaposto.push(nome(el) + ' ' + vp.toFixed(2)); }
    }
    if (el.type === 'file') {
      const fb = getComputedStyle(el, '::file-selector-button');
      r.file.push({ el: nome(el), raggio: fb.borderTopLeftRadius, bordo: fb.borderTopStyle + ' ' + fb.borderTopColor, peso: fb.fontWeight, fondo: fb.backgroundColor });
    }
  }
  // Fasce dei titoli dei riquadri.
  const RIQ = '.form-card,.seo-card,.agg-card,.cs-card,.cr-card,.ei-card,.fa-scatola,.info-card,.sm-scatola,.iw-card,.db-scatola,.vs-box,.cs-gruppo,.tt-carica,.sc-scatola,.mn-scatola,.wg-gruppo';
  for (const q of document.querySelectorAll('.admin-content :is(' + RIQ + ')')) {
    if (!visibile(q)) continue;
    const h = [...q.children].find(x => /^H[23]$/.test(x.tagName)); if (!h) continue;
    const prima = [...q.children].slice(0, [...q.children].indexOf(h)).filter(x => !(x.tagName === 'INPUT' && x.type === 'hidden'));
    if (prima.length) continue; // il titolo non apre il riquadro: non e una testata
    const fh = fondo(h), fq = rgb(getComputedStyle(q).backgroundColor), ct = rgb(getComputedStyle(h).color);
    const diverso = fh && fq && (Math.abs(fh.r - fq.r) + Math.abs(fh.g - fq.g) + Math.abs(fh.b - fq.b)) > 6;
    const v = fh && ct ? cr(ct, fh) : 0;
    r.fasce.push(nome(q));
    if (!diverso || v < 4.5) r.fasceKo.push(nome(q) + ' "' + h.textContent.trim().slice(0, 25) + '" fondo ' + (diverso ? 'diverso' : 'uguale') + ' testo ' + v.toFixed(2));
  }
  return r;
}

(async () => {
  const br = await chromium.launch();
  const c = await br.newContext({ viewport: { width: 1280, height: 900 }, locale: 'it-IT' }); const p = await c.newPage();
  await p.goto(B + '/admin/login.php');
  const login = await p.evaluate(misura);
  ok('login: testi almeno 4,5:1', login.testi.length === 0, login.testi);
  ok('login: bordi dei campi almeno 3:1', login.bordi.length === 0, login.bordi);
  await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(B + '/admin/', { waitUntil: 'networkidle' });
  let pagine = [...new Set(await p.locator('.admin-sidebar a[href]').evaluateAll(l => l.map(a => a.getAttribute('href')).filter(h => h && !/^(https?:|#|mailto:|\.\.|\/)/.test(h) && !/logout|editor\.php|anteprima/.test(h))))];
  pagine = [...new Set(pagine.concat(PIU))].filter(u => !FILTRO || new RegExp(FILTRO).test(u));
  const err = []; p.on('pageerror', e => err.push(p.url() + ' ' + e.message.slice(0, 120)));
  // La finestra di conferma del pannello (al posto di confirm()): fuoco dentro, Esc chiude, il fuoco torna al pulsante,
  // il pulsante che conferma ripete l'azione ed e rosso se distrugge. Nessun dialogo del browser.
  if (!FILTRO || /temi/.test(FILTRO)) {
    const nativi = []; p.on('dialog', d => { nativi.push(d.message()); d.dismiss(); });
    await p.goto(B + '/admin/temi.php', { waitUntil: 'networkidle' });
    const scheda = p.locator('.tt-card', { has: p.locator('button', { hasText: 'Elimina' }) }).first();
    const quante = await p.locator('.tt-card').count();
    await scheda.locator('button', { hasText: 'Elimina' }).click();
    const dlg = p.locator('dialog.pn-conferma[open]');
    ok('conferma: si apre la finestra del pannello, non quella del browser', await dlg.count() === 1 && nativi.length === 0, nativi);
    const info = await p.evaluate(() => { const d = document.querySelector('dialog.pn-conferma'); const si = d.querySelector('.pn-conferma-si'); return { dentro: d.contains(document.activeElement), fuoco: document.activeElement.textContent, si: si.textContent, rosso: si.classList.contains('btn-pericolo'), ruolo: d.getAttribute('role'), testo: d.querySelector('p').textContent }; });
    ok('conferma: fuoco dentro, su Annulla per un\'azione che distrugge', info.dentro && info.fuoco === 'Annulla', info);
    ok('conferma: il pulsante dice l\'azione ("Elimina") ed e rosso', info.si === 'Elimina' && info.rosso, info);
    ok('conferma: role alertdialog e il testo della domanda', info.ruolo === 'alertdialog' && /Togliere il tema/.test(info.testo), info);
    await p.keyboard.press('Escape');
    const dopo = await p.evaluate(() => ({ aperta: !!document.querySelector('dialog.pn-conferma[open]'), fuoco: document.activeElement && document.activeElement.textContent }));
    ok('conferma: Esc chiude, il fuoco torna al pulsante, niente cambia', !dopo.aperta && dopo.fuoco === 'Elimina' && await p.locator('.tt-card').count() === quante, dopo);
    const usa = p.locator('.tt-card button', { hasText: 'Usa questo tema' }).first();
    if (await usa.count()) {
      await usa.click();
      const u = await p.evaluate(() => { const si = document.querySelector('dialog.pn-conferma .pn-conferma-si'); return { si: si.textContent, rosso: si.classList.contains('btn-pericolo'), fuoco: document.activeElement === si }; });
      ok('conferma: un\'azione che non distrugge ha il pulsante primario, con il fuoco', u.si === 'Usa questo tema' && !u.rosso && u.fuoco, u);
      await p.locator('dialog.pn-conferma .pn-conferma-no').click();
      ok('conferma: Annulla chiude senza inviare', await p.locator('dialog.pn-conferma[open]').count() === 0 && /temi\.php$/.test(p.url()));
    }
    p.removeAllListeners('dialog');
  }
  const tel = await (await br.newContext({ viewport: { width: 390, height: 844 }, locale: 'it-IT', storageState: await c.storageState() })).newPage();
  for (const asp of ASPETTI.split(',')) {
    await p.goto(B + '/admin/account.php', { waitUntil: 'networkidle' });
    await p.check(`input[name=aspetto][value=${asp}]`);
    await Promise.all([p.waitForNavigation(), p.locator('#aspetto button.btn-primary').click()]);
    ok(asp + ': aspetto scelto da Il mio accesso', await p.evaluate(() => document.documentElement.dataset.pannello) === asp);
    let fasce = 0, file = 0;
    for (const u of pagine) {
      const r = await p.goto(B + '/admin/' + u, { waitUntil: 'networkidle' }).catch(() => null);
      if (!r || r.status() !== 200) { ok(`${asp} ${u}: si apre`, false, r && r.status()); continue; }
      const m = await p.evaluate(misura);
      ok(`${asp} ${u}: testi almeno 4,5:1`, m.testi.length === 0, m.testi.slice(0, 12));
      ok(`${asp} ${u}: bordi dei campi almeno 3:1`, m.bordi.length === 0, m.bordi.slice(0, 8));
      ok(`${asp} ${u}: segnaposto almeno 4,5:1`, m.segnaposto.length === 0, m.segnaposto.slice(0, 5));
      ok(`${asp} ${u}: titoli dei riquadri in una fascia`, m.fasceKo.length === 0, m.fasceKo.slice(0, 6));
      fasce += m.fasce.length;
      for (const f of m.file) { file++; ok(`${asp} ${u}: scelta file con il pulsante del pannello`, f.raggio !== '0px' && /solid/.test(f.bordo) && +f.peso >= 600, f); }
      const t = await tel.goto(B + '/admin/' + u, { waitUntil: 'networkidle' }).catch(() => null);
      if (t) { const W = await tel.evaluate(() => document.documentElement.scrollWidth); ok(`${asp} ${u}: niente scorrimento di lato a 390 px`, W <= 391, W); }
    }
    ok(`${asp}: riquadri con la fascia del titolo trovati (${fasce})`, fasce >= 20 || !!FILTRO, fasce);
    ok(`${asp}: scelte di file controllate (${file})`, file >= 3 || !!FILTRO, file);
  }
  await p.goto(B + '/admin/account.php'); await p.check('input[name=aspetto][value=aria]');
  await Promise.all([p.waitForNavigation(), p.locator('#aspetto button.btn-primary').click()]);
  ok('nessun errore JavaScript', err.length === 0, err.slice(0, 5));
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
