// Le pagine per zona (2.28.0), come le userebbe una persona. Nel pannello (SEO e AEO, Zone), su un sito con il kit Attività e una
// sede "Sede di Bologna" (prove/sedi-cli.php): crea tre bozze per "Idraulico" (Bologna, Casalecchio di Reno, Provincia di Bologna)
// con la pagina del servizio collegata; controlla titoli, indirizzi, stato bozza, i tre segnaposto [DA SCRIVERE], il registro; gli
// errori sotto i campi (servizio vuoto, modello senza %zona%, 31 zone); la stessa coppia non si ricrea. Poi prova a pubblicare una
// bozza con i segnaposto da ogni strada (editor, scheda del contenuto, azione in blocco, Impostazioni dell'editor, salvataggio
// diretto): rifiutata con il messaggio, resta bozza. Compila i riquadri (prove/zone-cli.php scrivi, come farebbe una persona
// nell'editor) e pubblica dall'editor: la pagina esce 200 con il JSON-LD (Service con name, serviceType, areaServed City o
// AdministrativeArea, provider = la sede; mainEntity; briciole Home > Servizi > zona), il widget Altre zone con le sole zone
// pubblicate, la sitemap, il titolo; il filtro per servizio, Togli dal registro, una pagina cancellata che sparisce dal registro;
// il redattore fuori dal pannello ma che scrive e pubblica la bozza con la stessa regola; 1280 e 390 px senza scorrimento di lato.
//
// uso: node prove/prova-zone.js <base> <cartella CMS> <utente> <password> [cartella foto]
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, FOTO] = process.argv.slice(2);
if (!B || !DIR || !UTENTE || !PASSWORD) { console.error('uso: node prove/prova-zone.js <base> <cartella CMS> <utente> <password> [cartella foto]'); process.exit(2); }
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? '  ' + JSON.stringify(extra).slice(0, 300) : '')); };
const cliDi = (file) => (...a) => JSON.parse(execFileSync('php', [path.join(__dirname, file), DIR, ...a.map(String)], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }).trim().split('\n').pop());
const cli = cliDi('zone-cli.php'), sedi = cliDi('sedi-cli.php');
const SEG = '[DA SCRIVERE]';
const foto = async (p, nome) => { if (FOTO) await p.screenshot({ path: path.join(FOTO, nome + '.png'), fullPage: true }); };
const ld = async (p) => (await p.locator('script[type="application/ld+json"]').evaluateAll(l => l.map(s => { try { return JSON.parse(s.textContent); } catch (e) { return null; } }))).filter(Boolean);
const grafo = async (p, u) => { await p.goto(B + u, { waitUntil: 'networkidle' }); const g = (await ld(p)).find(x => x && x['@graph']); return g ? g['@graph'] : []; };
const orgDi = (g) => g.find(x => x['@id'] && /#organizzazione$/.test(x['@id']));
const ORIG = { 'Origin': B.replace(/(https?:\/\/[^/]+).*/, '$1') };
const entra = async (br, utente, password) => {
  const c = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await c.newPage();
  pnConferme.on(p, d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', utente); await p.fill('#password', password);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  return { c, p, dentro: !p.url().includes('login.php') };
};
const token = (p) => p.evaluate(() => (document.querySelector('meta[name="pn-csrf"]') || {}).content || (document.querySelector('input[name=_csrf]') || {}).value || '');
const voce = (id) => cli('elenco').find(z => z.pagina === id) || null;
// Nell'editor: Pubblica, con la conferma accettata; ritorna i dialoghi dopo la conferma (l'avviso di rifiuto) e il testo dello stato.
async function pubblicaDallEditor(p, id) {
  await p.goto(B + '/admin/editor.php?tipo=pagine&id=' + id, { waitUntil: 'networkidle' });
  await p.waitForFunction(() => { const f = document.querySelector('#ed-iframe'); return f && f.contentDocument && f.contentDocument.querySelector('.ed-sezione'); }, null, { timeout: 20000 });
  const fin = []; const ascolta = d => { if (d.type() === 'alert') fin.push(d.message()); d.accept().catch(() => {}); };
  p.on('dialog', ascolta);
  await p.click('#ed-pubblica'); await p.waitForTimeout(2500);
  p.off('dialog', ascolta);
  return { avvisi: fin, stato: await p.locator('#ed-stato').innerText().catch(() => '') };
}

(async () => {
  const br = await chromium.launch();
  // Pulizia: nessuna pagina per zona, una sede sola nota, niente redattore di prova.
  cli('azzera'); sedi('azzera'); cli('redattore', 'elimina');
  const sd = sedi('salva', JSON.stringify({ nome: 'Sede di Bologna', tipo: 'idraulico', citta: 'Bologna', provincia: 'BO', via: 'Via Rizzoli 1', cap: '40125', telefono: '+39 051 123456', email: 'bologna@esempio.it', lat: '44.4949', lng: '11.3426', zona: 'Bologna\nCasalecchio di Reno\nProvincia di Bologna', orari: { lun: '9-13, 15-19' } }));
  ok('preparazione: una sede "Sede di Bologna" con tre zone servite', sd && sd.sede && sd.sede.id === 'sede-di-bologna' && sd.sede.zona.length === 3, sd && sd.errori);
  const admin = await entra(br, UTENTE, PASSWORD);
  ok('login amministratore', admin.dentro, admin.p.url());
  const p = admin.p;
  const err = []; p.on('pageerror', e => err.push(p.url() + ' ' + e.message.slice(0, 160)));

  // === 1. La pagina Zone vuota, dal menu e dalle schede
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('il menu SEO e AEO ha la voce Zone accanto a Sedi', await p.locator('.admin-sidebar a[href="zone.php"]').count() >= 1 && await p.locator('.admin-sidebar a[href="sedi.php"]').count() >= 1);
  await p.goto(B + '/admin/seo.php?scheda=schema', { waitUntil: 'networkidle' });
  ok('la fila delle schede di SEO e AEO ha Zone', await p.locator('.seo-schede a[href="zone.php"]').count() === 1);
  await p.goto(B + '/admin/sedi.php', { waitUntil: 'networkidle' });
  ok('la fila delle schede di Sedi ha Zone', await p.locator('.imp-schede a[href="zone.php"]').count() === 1);
  const r1 = await p.goto(B + '/admin/zone.php', { waitUntil: 'networkidle' });
  ok('zone.php si apre (200) senza pagine per zona, con lo stato vuoto che dice cosa fare', r1.status() === 200 && /Ancora nessuna pagina per zona/.test(await p.locator('.empty-state').innerText()));
  ok('le zone sono già scritte dall\'unione delle zone servite delle sedi', (await p.inputValue('#zn-zone')) === 'Bologna\nCasalecchio di Reno\nProvincia di Bologna', await p.inputValue('#zn-zone'));
  ok('con una sede sola la sede di riferimento è quella, senza scelta', await p.locator('#zn-sede').isDisabled() && /Sede di Bologna/.test(await p.locator('#zn-sede').innerText()) && (await p.inputValue('input[name="zone[sede]"]')) === 'sede-di-bologna');
  ok('i modelli di partenza: "%servizio% a %zona%" e una descrizione con i due segnaposto', (await p.inputValue('#zn-titolo')) === '%servizio% a %zona%' && /%servizio%/.test(await p.inputValue('#zn-descrizione')) && /%zona%/.test(await p.inputValue('#zn-descrizione')));
  const opzServizio = await p.locator('#zn-servizio-pagina option').evaluateAll(os => os.map(o => o.value + ':' + o.text));
  const vServizi = (opzServizio.find(x => /Servizi \(\/servizi\/\)/.test(x)) || '').replace(/^(pagine:\d+):.*$/, '$1');
  ok('la tendina della pagina del servizio ha le pagine del sito (Servizi), senza la home', /^pagine:\d+$/.test(vServizi) && !opzServizio.some(x => /\(\/home\/|Home \(/.test(x)), opzServizio.slice(0, 4));

  // === 2. Tre bozze per "Idraulico"
  await p.fill('#zn-servizio', 'Idraulico');
  await p.selectOption('#zn-servizio-pagina', vServizi);
  await p.fill('#zn-descrizione', '%servizio% a %zona%: pronto intervento, preventivi gratuiti e la sede più vicina.');
  await foto(p, 'zone-modulo');
  await Promise.all([p.waitForNavigation(), p.click('#zn-modulo button[type=submit]')]);
  const fl1 = await p.locator('.flash-success').innerText().catch(() => '');
  ok('"Crea le bozze": 3 bozze create, con i tre titoli e l\'avviso sui segnaposto', /3 bozze create/.test(fl1) && /«Idraulico a Bologna»/.test(fl1) && /«Idraulico a Casalecchio di Reno»/.test(fl1) && /«Idraulico a Provincia di Bologna»/.test(fl1) && fl1.includes(SEG), fl1.slice(0, 200));
  ok('dopo la creazione la pagina è filtrata sul servizio (zone.php?servizio=Idraulico)', /servizio=Idraulico/.test(p.url()), p.url());
  const reg = cli('elenco');
  const bol = reg.find(z => z.zona === 'Bologna'), cas = reg.find(z => z.zona === 'Casalecchio di Reno'), prov = reg.find(z => z.zona === 'Provincia di Bologna');
  ok('il registro ha le tre pagine: servizio, zona, sede, pagina del servizio, data', reg.length === 3 && bol && cas && prov && reg.every(z => z.servizio === 'Idraulico' && z.sede === 'sede-di-bologna' && z.servizio_pagina === vServizi && /^\d{4}-\d{2}-\d{2} /.test(z.creata)), reg);
  ok('titoli e indirizzi dal modello: "Idraulico a Casalecchio di Reno", /idraulico-a-casalecchio-di-reno/', cas && cas.titolo === 'Idraulico a Casalecchio di Reno' && cas.slug === 'idraulico-a-casalecchio-di-reno' && bol.slug === 'idraulico-a-bologna' && prov.slug === 'idraulico-a-provincia-di-bologna');
  ok('tutte e tre in bozza, con tre riquadri [DA SCRIVERE] ciascuna', reg.every(z => z.stato === 'bozza' && z.segnaposto === 3), reg.map(z => [z.stato, z.segnaposto]));
  const righe = cli('sql', `SELECT id, status, seo_description, robots FROM {p}pages WHERE id IN (${reg.map(z => z.pagina).join(',')})`);
  ok('nella tabella pages: status draft, descrizione per Google dal modello, index', righe.length === 3 && righe.every(r => r.status === 'draft' && /^Idraulico a .*: pronto intervento, preventivi gratuiti/.test(r.seo_description) && r.robots === 'index'), righe);
  const tab = await p.locator('#zn-tabella').innerText();
  ok('la tabella mostra le tre pagine con Bozza e "riquadri da scrivere: 3", Apri nell\'editor e Togli dal registro', await p.locator('#zn-tabella tbody tr').count() === 3 && (tab.match(/riquadri da scrivere: 3/g) || []).length === 3 && await p.locator('#zn-tabella .zn-stato.zn-bozza').count() === 3 && await p.locator('#zn-tabella a[href*="editor.php?tipo=pagine&id="]').count() === 3 && await p.locator('#zn-tabella form[data-conferma] button').count() === 3);
  ok('con un servizio solo non c\'è il filtro', await p.locator('#zn-filtro').count() === 0);
  await foto(p, 'zone-elenco');

  // === 3. Gli errori sotto i campi; la stessa coppia non si ricrea
  await p.goto(B + '/admin/zone.php', { waitUntil: 'networkidle' });
  await p.fill('#zn-servizio', 'I');
  await p.fill('#zn-titolo', 'Servizio in zona');
  await p.fill('#zn-zone', Array.from({ length: 31 }, (_, i) => 'Comune ' + (i + 1)).join('\n'));
  await p.locator('#zn-servizio').evaluate(e => e.removeAttribute('required'));
  await Promise.all([p.waitForNavigation(), p.click('#zn-modulo button[type=submit]')]);
  const errori = await p.locator('.zn-errore[role=alert]').allInnerTexts();
  ok('servizio di una lettera, modello senza %zona%, 31 zone: tre errori sotto i campi e il riassunto in cima, niente creato', errori.length === 3 && /3 campi da sistemare/.test(await p.locator('.flash-error').innerText()) && await p.locator('#zn-titolo[aria-invalid=true]').count() === 1 && cli('elenco').length === 3, errori);
  ok('i valori scritti restano nel modulo', (await p.inputValue('#zn-titolo')) === 'Servizio in zona' && (await p.inputValue('#zn-zone')).split('\n').length === 31);
  await p.goto(B + '/admin/zone.php', { waitUntil: 'networkidle' });
  await p.fill('#zn-servizio', 'idraulico');
  await p.fill('#zn-zone', 'bologna\nCasalecchio di Reno\nImola');
  await Promise.all([p.waitForNavigation(), p.click('#zn-modulo button[type=submit]')]);
  const flInfo = await p.locator('.flash-info').innerText().catch(() => '');
  const flOk2 = await p.locator('.flash-success').innerText().catch(() => '');
  ok('la stessa coppia (anche con maiuscole diverse) non si rifà: "C\'è già" per Bologna e Casalecchio, una bozza nuova per Imola', /C'è già: «Idraulico a Bologna», «Idraulico a Casalecchio di Reno»/.test(flInfo) && /1 bozza creata: «idraulico a Imola»/.test(flOk2) && cli('elenco').length === 4, [flInfo.slice(0, 120), flOk2.slice(0, 80)]);
  const imola = cli('elenco').find(z => z.zona === 'Imola');
  cli('sql', `DELETE FROM {p}pages WHERE id = ${imola.pagina}`);
  await p.goto(B + '/admin/zone.php', { waitUntil: 'networkidle' });
  ok('una pagina cancellata per sempre sparisce dal registro alla prima apertura del pannello', cli('elenco').length === 3 && await p.locator('#zn-tabella tbody tr').count() === 3);

  // === 4. Pubblicare una bozza con i segnaposto: rifiutata da ogni strada
  const idC = cas.pagina;
  const e1 = await pubblicaDallEditor(p, idC);
  ok('editor: Pubblica su una bozza con i segnaposto è rifiutato con il messaggio ("3 riquadri da scrivere"), la pagina resta bozza', e1.avvisi.length === 1 && /ha ancora 3 riquadri da scrivere/.test(e1.avvisi[0]) && e1.avvisi[0].includes(SEG) && /3 riquadri da scrivere/.test(e1.stato) && voce(idC).stato === 'bozza', e1);
  const tela = p.frameLocator('#ed-iframe');
  ok('editor: la tela mostra i tre riquadri guidati con il segnaposto e la riga di aiuto', (await tela.locator('.ed-testo').allInnerTexts()).filter(t => t.includes(SEG) && /Aiuto per chi scrive/.test(t)).length === 3);
  await foto(p, 'zone-editor');
  // La scheda del contenuto: Pubblica.
  await p.goto(B + '/admin/contenuto.php?tipo=pagine&id=' + idC, { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation(), p.click('button[data-valore="pubblica"]')]);
  const flS = await p.locator('.flash-error').innerText().catch(() => '');
  ok('scheda del contenuto: Pubblica è rifiutato con lo stesso messaggio, la pagina resta bozza', /3 riquadri da scrivere/.test(flS) && voce(idC).stato === 'bozza', flS.slice(0, 160));
  // L'azione in blocco dall'elenco delle pagine.
  const tk = await token(p);
  await p.request.post(B + '/admin/contenuti.php?tipo=pagine', { form: { _csrf: tk, azione: 'pubblica', 'ids[]': String(idC) }, headers: ORIG, maxRedirects: 0 });
  await p.goto(B + '/admin/contenuti.php?tipo=pagine', { waitUntil: 'networkidle' });
  const flB = (await p.locator('.flash-error').allInnerTexts()).join(' | ');
  ok('azione in blocco "Pubblica": la pagina per zona resta bozza e il pannello dice quale e perché', /Idraulico a Casalecchio di Reno/.test(flB) && /3 riquadri da scrivere/.test(flB) && voce(idC).stato === 'bozza', flB.slice(0, 200));
  // Le Impostazioni dell'editor (stato Pubblicata) e il salvataggio diretto con pubblica=1.
  const api = async (campi) => p.evaluate(async ({ campi }) => { const fd = new FormData(); for (const [k, v] of Object.entries(campi)) fd.append(k, v); const r = await fetch('editor-api.php', { method: 'POST', body: fd, credentials: 'include' }); return { s: r.status, j: await r.json().catch(() => null) }; }, { campi });
  await p.goto(B + '/admin/editor.php?tipo=pagine&id=' + idC, { waitUntil: 'networkidle' });
  const tkE = await token(p);
  const imp = await api({ _csrf: tkE, azione: 'impostazioni', tipo: 'pagine', id: String(idC), titolo: 'Idraulico a Casalecchio di Reno', slug: 'idraulico-a-casalecchio-di-reno', stato: 'published', seo_titolo: '', seo_descrizione: '', robots: '' });
  const alberoC = cli('sql', `SELECT struttura_bozza FROM {p}pages WHERE id = ${idC}`)[0].struttura_bozza;
  const sal = await api({ _csrf: tkE, azione: 'salva', tipo: 'pagine', id: String(idC), albero: alberoC, pubblica: '1', nota: '' });
  ok('Impostazioni dell\'editor (stato Pubblicata) e salvataggio diretto con pubblica=1: rifiutati con il messaggio (400 e 409), resta bozza', imp.s === 400 && imp.j && /riquadri da scrivere/.test(imp.j.errore) && sal.s === 409 && sal.j && /riquadri da scrivere/.test(sal.j.errore) && voce(idC).stato === 'bozza' && voce(idC).segnaposto === 3, [imp.s, sal.s]);

  // === 5. Compila i riquadri e pubblica
  const s2 = cli('scrivi', idC, 2);
  const e2 = await pubblicaDallEditor(p, idC);
  ok('con due riquadri scritti e uno ancora da scrivere: rifiutato con "1 riquadro da scrivere"', s2.scritti === 2 && s2.restanti === 1 && e2.avvisi.length === 1 && /ha ancora 1 riquadro da scrivere/.test(e2.avvisi[0]) && voce(idC).stato === 'bozza', [s2, e2.avvisi]);
  const s3 = cli('scrivi', idC);
  const e3 = await pubblicaDallEditor(p, idC);
  ok('con tutti i riquadri scritti Pubblica dall\'editor va a buon fine: la pagina è pubblicata', s3.restanti === 0 && e3.avvisi.length === 0 && /Pubblicata alle/.test(e3.stato) && voce(idC).stato === 'pubblicato', [s3, e3]);
  const idB = bol.pagina, idP = prov.pagina;
  cli('scrivi', idB); const pb = cli('pubblica', idB);
  ok('la seconda zona (Bologna) scritta e pubblicata; la terza (Provincia) resta bozza', pb.ok && voce(idB).stato === 'pubblicato' && voce(idP).stato === 'bozza' && voce(idP).segnaposto === 3, pb);
  await p.goto(B + '/admin/zone.php', { waitUntil: 'networkidle' });
  const tab2 = await p.locator('#zn-tabella').innerText();
  ok('il registro nel pannello: due Pubblicata "pronta", una Bozza con 3 riquadri da scrivere', await p.locator('#zn-tabella .zn-stato.zn-pubblicata').count() === 2 && await p.locator('#zn-tabella .zn-pronta').count() === 2 && (tab2.match(/riquadri da scrivere: 3/g) || []).length === 1);

  // === 6. Il sito: la pagina per zona, i dati per Google, Altre zone, la sitemap
  const v = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const errSito = []; v.on('pageerror', e => errSito.push(v.url() + ' ' + e.message.slice(0, 160)));
  const rs = await v.goto(B + '/idraulico-a-casalecchio-di-reno/', { waitUntil: 'networkidle' });
  const html = await v.content();
  ok('la pagina per zona pubblicata si apre (200): occhiello = la zona, H1 "Idraulico a Casalecchio di Reno", i tre riquadri scritti, i dati della sede, la barra dei contatti', rs.status() === 200 && /Casalecchio di Reno/i.test(await v.locator('.hero .occhiello').first().innerText()) && /Idraulico a Casalecchio di Reno/i.test(await v.locator('h1').first().innerText()) && (await v.locator('.ed-testo h3').allInnerTexts()).length === 3 && await v.locator('.ew-dati-attivita').count() === 1 && await v.locator('.ew-barra-contatti').count() === 1);
  ok('nessun segnaposto e nessuna riga di aiuto nella pagina pubblicata', !html.includes(SEG) && !/Aiuto per chi scrive/.test(html));
  ok('il titolo della pagina: "Idraulico a Casalecchio di Reno - Studio Prova"', (await v.title()) === 'Idraulico a Casalecchio di Reno - Studio Prova', await v.title());
  const altre = v.locator('.ew-zone');
  ok('il widget Altre zone elenca solo le altre zone pubblicate dello stesso servizio: Bologna (la Provincia è bozza), con zone.css', await altre.count() === 1 && (await altre.locator('.ew-zone-link').allInnerTexts()).join(',') === 'Bologna' && (await altre.locator('.ew-zone-link').first().getAttribute('href')) === B + '/idraulico-a-bologna/' && /Lavoriamo anche a/.test(await altre.locator('.ew-zone-titolo').innerText()) && await v.locator('link[href*="zone.css"]').count() === 1, await altre.locator('.ew-zone-link').allInnerTexts());
  const g = (await ld(v)).find(x => x['@graph'])['@graph'];
  const web = g.find(x => x['@type'] === 'WebPage'), serv = g.find(x => x['@type'] === 'Service'), bric = g.find(x => x['@type'] === 'BreadcrumbList'), org = orgDi(g);
  ok('JSON-LD: il Service ha name "Idraulico a Casalecchio di Reno", serviceType, url, areaServed City "Casalecchio di Reno", provider = la LocalBusiness della sede', serv && serv.name === 'Idraulico a Casalecchio di Reno' && serv.serviceType === 'Idraulico' && serv.url === B + '/idraulico-a-casalecchio-di-reno/' && serv.areaServed && serv.areaServed['@type'] === 'City' && serv.areaServed.name === 'Casalecchio di Reno' && serv.provider && serv.provider['@id'] === org.location[0]['@id'] && /#sede-sede-di-bologna$/.test(serv.provider['@id']), serv);
  ok('JSON-LD: la WebPage ha mainEntity = il Service; la descrizione dal modello', web && web.mainEntity && web.mainEntity['@id'] === serv['@id'] && /pronto intervento, preventivi gratuiti/.test(web.description), web && web.mainEntity);
  ok('JSON-LD: le briciole Home > Servizi > Casalecchio di Reno', bric && bric.itemListElement.length === 3 && bric.itemListElement.map(x => x.name).join(' > ') === 'Home > Servizi > Casalecchio di Reno' && bric.itemListElement[1].item === B + '/servizi/' && bric.itemListElement[2].item === B + '/idraulico-a-casalecchio-di-reno/', bric && bric.itemListElement.map(x => x.name));
  ok('JSON-LD: uno script solo, nessun segnaposto dentro', (html.match(/application\/ld\+json/g) || []).length === 1 && !JSON.stringify(g).includes(SEG));
  await foto(v, 'zone-sito');
  const gB = await grafo(v, '/idraulico-a-bologna/');
  ok('la pagina di Bologna: Altre zone elenca Casalecchio di Reno; il suo Service ha areaServed City "Bologna"', (await v.locator('.ew-zone .ew-zone-link').allInnerTexts()).join(',') === 'Casalecchio di Reno' && gB.find(x => x['@type'] === 'Service').areaServed.name === 'Bologna');
  const rp = await v.goto(B + '/idraulico-a-provincia-di-bologna/', { waitUntil: 'networkidle' });
  ok('la bozza (Provincia di Bologna) per un visitatore è 404', rp.status() === 404);
  const gHome = await grafo(v, '/');
  const gServ = await grafo(v, '/servizi/');
  ok('home e Servizi: nessun Service di zona, nessuna mainEntity, nessuna briciola in più (il resto del sito è com\'era)', !gHome.find(x => x['@type'] === 'Service') && !gHome.find(x => x['@type'] === 'WebPage').mainEntity && !gServ.find(x => x['@type'] === 'WebPage').mainEntity && !gServ.find(x => x['@type'] === 'BreadcrumbList'));
  const sm = await (await v.context().request.get(B + '/sitemap.xml')).text();
  ok('la sitemap ha le due pagine pubblicate e non la bozza', /idraulico-a-casalecchio-di-reno\//.test(sm) && /idraulico-a-bologna\//.test(sm) && !/idraulico-a-provincia-di-bologna/.test(sm));
  // La terza zona pubblicata: AdministrativeArea e Altre zone con due link.
  cli('scrivi', idP); cli('pubblica', idP);
  const gP = await grafo(v, '/idraulico-a-provincia-di-bologna/');
  ok('"Provincia di Bologna" pubblicata: areaServed AdministrativeArea, Altre zone con Bologna e Casalecchio di Reno', gP.find(x => x['@type'] === 'Service').areaServed['@type'] === 'AdministrativeArea' && (await v.locator('.ew-zone .ew-zone-link').allInnerTexts()).join(',') === 'Bologna,Casalecchio di Reno');
  // Nell'anteprima del pannello (Schema e AEO) lo stesso JSON-LD.
  const anteprima = cli('jsonld', '/idraulico-a-casalecchio-di-reno/');
  ok('l\'anteprima del JSON-LD del pannello calcola gli stessi nodi (Service, mainEntity, briciole)', anteprima['@graph'] && !!anteprima['@graph'].find(x => x['@type'] === 'Service' && x.name === 'Idraulico a Casalecchio di Reno') && !!anteprima['@graph'].find(x => x['@type'] === 'BreadcrumbList'));

  // === 7. Il filtro per servizio e Togli dal registro
  const c2 = cli('crea', JSON.stringify({ servizio: 'Elettricista', zone: 'Imola', sede: 'sede-di-bologna' }));
  await p.goto(B + '/admin/zone.php', { waitUntil: 'networkidle' });
  ok('con due servizi compare il filtro, con "Tutti i servizi (4)" e i due servizi', c2.create.length === 1 && await p.locator('#zn-filtro-servizio').count() === 1 && /Tutti i servizi \(4\)/.test(await p.locator('#zn-filtro-servizio').innerText()) && await p.locator('#zn-tabella tbody tr').count() === 4);
  await Promise.all([p.waitForNavigation(), p.selectOption('#zn-filtro-servizio', 'Idraulico')]);
  ok('filtro Idraulico: tre righe, e il modulo ha già il servizio scritto', /servizio=Idraulico/.test(p.url()) && await p.locator('#zn-tabella tbody tr').count() === 3 && (await p.inputValue('#zn-servizio')) === 'Idraulico');
  await p.goto(B + '/admin/zone.php?servizio=Elettricista', { waitUntil: 'networkidle' });
  const idE = c2.create[0].pagina;
  await Promise.all([p.waitForNavigation(), p.locator('#zn-tabella tr', { hasText: 'Elettricista' }).locator('form[data-conferma] button').click()]);
  const flT = await p.locator('.flash-success').innerText().catch(() => '');
  ok('Togli dal registro: la voce sparisce, la pagina resta (in bozza) e il messaggio lo dice', /tolta dal registro/.test(flT) && /La pagina resta/.test(flT) && !voce(idE) && +cli('sql', `SELECT COUNT(*) AS n FROM {p}pages WHERE id = ${idE}`)[0].n === 1, flT.slice(0, 160));
  cli('sql', `DELETE FROM {p}pages WHERE id = ${idE}`);

  // === 8. Il redattore: fuori dal pannello Zone, ma scrive e pubblica le bozze con la stessa regola
  const c3 = cli('crea', JSON.stringify({ servizio: 'Idraulico', zone: 'San Lazzaro di Savena', sede: 'sede-di-bologna' }));
  const idS = c3.create[0].pagina;
  cli('redattore', 'crea');
  const red = await entra(br, 'zone_prova_red', 'Red-Zone-Prova-2026');
  ok('il redattore entra nel pannello', red.dentro);
  await red.p.goto(B + '/admin/zone.php', { waitUntil: 'networkidle' });
  ok('il redattore non apre Zone (torna alla dashboard con l\'avviso) e il menu non gliela mostra', !red.p.url().includes('zone.php') && /riservata/.test(await red.p.content()) && await red.p.locator('.admin-sidebar a[href="zone.php"]').count() === 0);
  const tRed = await token(red.p);
  const rr = await red.c.request.post(B + '/admin/zone.php', { form: { _csrf: tRed, azione: 'crea', 'zone[servizio]': 'Idraulico', 'zone[zone]': 'Ferrara', 'zone[titolo]': '%servizio% a %zona%' }, headers: ORIG, maxRedirects: 0 });
  ok('il redattore non crea bozze (rimandato, niente nel registro)', [302, 303, 403].includes(rr.status()) && !cli('elenco').some(z => z.zona === 'Ferrara'), rr.status());
  const eR = await pubblicaDallEditor(red.p, idS);
  ok('il redattore apre la bozza nell\'editor e Pubblica è rifiutato con il messaggio dei segnaposto', eR.avvisi.length === 1 && /3 riquadri da scrivere/.test(eR.avvisi[0]) && voce(idS).stato === 'bozza', eR.avvisi);
  cli('scrivi', idS);
  const eR2 = await pubblicaDallEditor(red.p, idS);
  ok('scritti i riquadri, il redattore pubblica la pagina per zona', eR2.avvisi.length === 0 && voce(idS).stato === 'pubblicato', eR2);
  await red.c.close();
  cli('redattore', 'elimina');

  // === 9. Il pannello e il sito a 1280 e 390 px
  await p.goto(B + '/admin/zone.php', { waitUntil: 'networkidle' });
  ok('zone.php a 1280 px non scorre di lato', await p.evaluate(() => document.documentElement.scrollWidth <= 1281));
  const tp = await (await br.newContext({ viewport: { width: 390, height: 844 }, storageState: await admin.c.storageState() })).newPage();
  await tp.goto(B + '/admin/zone.php', { waitUntil: 'networkidle' });
  ok('zone.php a 390 px non scorre di lato', await tp.evaluate(() => document.documentElement.scrollWidth <= 391), await tp.evaluate(() => document.documentElement.scrollWidth));
  await foto(tp, 'zone-telefono');
  const t = await (await br.newContext({ viewport: { width: 390, height: 844 } })).newPage();
  await t.goto(B + '/idraulico-a-casalecchio-di-reno/', { waitUntil: 'networkidle' });
  ok('la pagina per zona al telefono non scorre di lato e i riquadri stanno in una colonna', await t.evaluate(() => document.documentElement.scrollWidth <= 391) && await t.evaluate(() => { const c = [...document.querySelectorAll('.ed-testo h3')]; return c.length === 3 && c[1].getBoundingClientRect().top > c[0].getBoundingClientRect().bottom; }));

  // Pulizia finale
  cli('azzera'); sedi('azzera');
  await v.goto(B + '/idraulico-a-casalecchio-di-reno/', { waitUntil: 'networkidle' });
  ok('dopo la pulizia le pagine per zona non ci sono più (404) e il registro è vuoto', cli('elenco').length === 0 && (await v.goto(B + '/idraulico-a-casalecchio-di-reno/', { waitUntil: 'networkidle' })).status() === 404);
  ok('nessun errore JavaScript nel pannello e sul sito', err.length === 0 && errSito.length === 0, err.concat(errSito));
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error('ERRORE della prova:', e); process.exit(2); });
