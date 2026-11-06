// 2.9.0, fase 2 del piano Brevo (docs-sviluppo/brevo-confronto.md): importazione ed esportazione dei contatti e lista dei
// test, provate come un utente. Importazione nei quattro passi (Carica, Abbina i dati, Scegli la lista, Concludi) con file
// veri: CSV con il punto e virgola e con la virgola, Windows-1252 con gli accenti, XLSX come lo scrivono Excel e
// LibreOffice (testi condivisi, testi in linea, date come numeri seriali, sì/no), Copia e incolla (anche solo indirizzi),
// 20000 righe a giri, doppioni, email non valide, chi è in lista nera saltato, chi ha cancellato i dati fuori, celle vuote
// con e senza l'opzione, colonna nuova come attributo data, lista nuova in una cartella, opzione lista nera, resoconto e
// CSV degli scarti, un'importazione finita dal cron. Esportazione dalla finestra: attributi, separatore, lista, campagna
// (aperto, non aperto, cliccato, non cliccato), formule neutralizzate, file grande a giri, andata e ritorno uguali.
// Lista dei test in Impostazioni (regole di Brevo) e prova di una campagna ai suoi contatti, con i loro dati.
// 390 px senza scorrimento di lato; il redattore non vede e non salva.
// Prima: un CMS installato (prova-kit.js) e, per la prova della campagna, il server di posta finto:
//   python3 -I prove/smtp-finto.py <porta> <cartella posta>
// uso: node prove/prova-importa.js <base> <utente> <password> <cartella CMS installato> [porta smtp] [cartella posta] [cartella schermate]
const { chromium } = require('playwright');
const pnConferme = require('./conferme-pannello');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const [B, UTENTE, PASSWORD, DIR, SMTP, POSTA, FOTO] = process.argv.slice(2);
const CLI = path.join(__dirname, 'importa-cli.php');
const T = fs.mkdtempSync(path.join(os.tmpdir(), 'prova-importa-'));
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
const cli = (...a) => JSON.parse(execFileSync('php', ['-d', 'memory_limit=512M', CLI, DIR, ...a], { encoding: 'utf8', maxBuffer: 64 << 20 }).trim().split('\n').pop());
const q = (sql) => cli('sql', sql);
const uno = (sql) => { const r = q(sql); return r && r[0] ? Object.values(r[0])[0] : null; };
const idDi = (e) => +uno(`SELECT id FROM {p}newsletter_iscritti WHERE email = '${e}'`);
const val = (e) => cli('valori', e) || {};
const scrivi = (nome, testo, cod = 'utf8') => { const f = path.join(T, nome); fs.writeFileSync(f, Buffer.from(testo, cod)); return f; };
const D = '@csv.prova-importa.it';

(async () => {
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 }, acceptDownloads: true });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  pnConferme.on(p, d => d.accept());
  let giri = 0; p.on('request', r => { if (r.method() === 'POST' && /newsletter-importa\.php/.test(r.url()) && /azione=giro|name="azione"\r\n\r\ngiro/.test(r.postData() || '')) giri++; });
  const vai = async (u) => { await p.goto(B + '/admin/' + u, { waitUntil: 'networkidle' }); };
  const flash = async () => (await p.locator('.flash, [role=alert], [role=status]').allInnerTexts()).join(' | ');
  const invia = async (sel) => { await Promise.all([p.waitForNavigation(), p.click(sel)]); };
  const foto = async (nome, sel) => { if (!FOTO) return; if (sel) await p.locator(sel).first().screenshot({ path: path.join(FOTO, nome + '.png') }); else await p.screenshot({ path: path.join(FOTO, nome + '.png'), fullPage: true }); };
  const posta = async (url, campi) => p.evaluate(async ({ url, campi }) => {
    const r = await fetch(url, { method: 'POST', body: new URLSearchParams(campi), credentials: 'same-origin', redirect: 'manual' });
    return r.status;
  }, { url: B + '/admin/' + url, campi });
  const token = async () => p.evaluate(() => (document.querySelector('input[name=_csrf]') || {}).value || '');
  const numeri = async () => {
    const o = {};
    for (const d of await p.locator('#nli-resoconto dl div').all()) o[(await d.locator('dt').innerText()).trim()] = +(await d.locator('dd').innerText()).trim();
    return o;
  };
  // Le quattro tappe di Brevo. src: { file } o { testo }; map: { "colonna": valore della tendina | { nuovo: NOME, tipo } };
  // liste: [id]; nuova: { nome, cartella }; opz: { aggiorna, vuote, nera, flussi }.
  const passo1 = async (src) => {
    await vai('newsletter-importa.php');
    if (src.file) { await p.check('input[name=modo][value=file]'); await p.setInputFiles('#nli-file', src.file); }
    else { await p.check('input[name=modo][value=incolla]'); await p.fill('#nli-testo', src.testo); }
    await invia('#nli-carica button[type=submit]');
    return +(new URL(p.url()).searchParams.get('id') || 0);
  };
  const colonna = (nome) => p.locator(`table.nli-abbina tr:has(td:first-child b:text-is("${nome}")) select[data-nli-col]`);
  const passo2 = async (map = {}) => {
    for (const [c, v] of Object.entries(map)) {
      if (typeof v === 'object') {
        await colonna(c).selectOption('_NUOVO');
        const riga = p.locator(`table.nli-abbina tr:has(td:first-child b:text-is("${c}"))`);
        await riga.locator('input[name$="[nome]"]').fill(v.nuovo); await riga.locator('select[name$="[tipo]"]').selectOption(v.tipo);
      } else await colonna(c).selectOption(v);
    }
    await invia('#nli-abbina button[type=submit]');
  };
  const passo3 = async (liste = [], nuova = null) => {
    for (const l of liste) await p.check(`#nli-liste input[name="liste[]"][value="${l}"]`);
    if (nuova) { await p.fill('#nli-nuova', nuova.nome); if (nuova.cartella) await p.selectOption('#nli-cartella', String(nuova.cartella)); }
    await invia('#nli-liste button[type=submit]');
  };
  const passo4 = async (opz = {}, attesa = 60000) => {
    for (const k of ['aggiorna', 'vuote', 'nera', 'flussi']) if (opz[k]) await p.check(`input[name="opz[${k}]"]`);
    await p.check('input[name=consenso]');
    await invia('#nli-conferma button[type=submit]');
    await p.waitForSelector('#nli-resoconto', { timeout: attesa });
    return numeri();
  };
  const importa = async (src, { map = {}, liste = [], nuova = null, opz = {} } = {}) => {
    const id = await passo1(src); await passo2(map); await passo3(liste, nuova);
    return { id, ...(await passo4(opz)) };
  };
  const scarica = async (azione) => { const [dl] = await Promise.all([p.waitForEvent('download'), azione()]); return fs.readFileSync(await dl.path(), 'utf8'); };
  const righeCsv = (t, sep = ';') => t.replace(/^﻿/, '').trim().split(/\r?\n/).map(l => { const out = []; let c = '', dentro = false; for (let i = 0; i < l.length; i++) { const ch = l[i]; if (dentro) { if (ch === '"' && l[i + 1] === '"') { c += '"'; i++; } else if (ch === '"') dentro = false; else c += ch; } else if (ch === '"') dentro = true; else if (ch === sep) { out.push(c); c = ''; } else c += ch; } out.push(c); return out; });

  // ===== Pulizia (anche di una prova interrotta) e preparazione
  const prova = "email LIKE '%prova-importa.it'";
  const pulisci = () => {
    q(`DELETE v FROM {p}newsletter_valori v JOIN {p}newsletter_iscritti i ON i.id = v.contatto_id WHERE i.${prova}`);
    for (const t of ['newsletter_iscritti_liste:iscritto_id', 'newsletter_storia:contatto_id', 'newsletter_consensi:contatto_id', 'newsletter_coda:iscritto_id', 'newsletter_eventi:iscritto_id']) {
      const [tab, col] = t.split(':');
      q(`DELETE x FROM {p}${tab} x JOIN {p}newsletter_iscritti i ON i.id = x.${col} WHERE i.${prova}`);
    }
    q(`DELETE FROM {p}newsletter_iscritti WHERE ${prova}`);
    q("DELETE il FROM {p}newsletter_iscritti_liste il JOIN {p}newsletter_liste l ON l.id = il.lista_id WHERE l.nome LIKE 'PI %'");
    q("DELETE FROM {p}newsletter_liste WHERE nome LIKE 'PI %'");
    q("DELETE FROM {p}newsletter_cartelle WHERE nome LIKE 'PI %'");
    q("DELETE FROM {p}newsletter_campagne WHERE oggetto LIKE 'PI %'");
    q('DELETE FROM {p}newsletter_importazioni'); q('DELETE FROM {p}newsletter_importa_viste'); q('DELETE FROM {p}newsletter_esportazioni');
    q("DELETE FROM {p}settings WHERE setting_key = 'newsletter_lista_test'");
    q("DELETE v FROM {p}newsletter_valori v JOIN {p}newsletter_attributi a ON a.id = v.attributo_id WHERE a.sistema = 0");
    q("DELETE o FROM {p}newsletter_attributi_opzioni o JOIN {p}newsletter_attributi a ON a.id = o.attributo_id WHERE a.sistema = 0");
    q('DELETE FROM {p}newsletter_attributi WHERE sistema = 0');
    q("DELETE FROM {p}newsletter_soppressi WHERE impronta = SHA2('cancellato@csv.prova-importa.it', 256)");
  };
  pulisci();
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await vai('newsletter-attributi.php');
  let tk = await token();
  for (const a of [{ nome: 'CITTA', tipo: 'text' }, { nome: 'PUNTI', tipo: 'float' }, { nome: 'CLIENTE', tipo: 'boolean' }, { nome: 'TIPO', tipo: 'category', opzioni: 'Privato\nAzienda' }, { nome: 'INTERESSI', tipo: 'multiple-choice', opzioni: 'Corsi\nEventi\nLibri' }]) {
    await posta('newsletter-attributi.php', { _csrf: tk, azione: 'crea', ...a });
  }
  ok('preparazione: cinque attributi dei cinque tipi', +uno("SELECT COUNT(*) FROM {p}newsletter_attributi WHERE nome IN ('CITTA','PUNTI','CLIENTE','TIPO','INTERESSI')") === 5);
  await vai('newsletter-contatti.php?scheda=liste');
  await p.fill('#nlx-cart-nome', 'PI Cartella'); await invia('button:has-text("Crea la cartella")');
  const cartella = +uno("SELECT id FROM {p}newsletter_cartelle WHERE nome = 'PI Cartella'");
  const principale = +uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_lista_principale'");

  // ===== Menu e pagina Contatti: Importa contatti porta ai quattro passi
  await vai('newsletter-contatti.php');
  await invia('a.btn:has-text("Importa contatti")');
  ok('Contatti > Importa contatti apre la pagina dei quattro passi', /newsletter-importa\.php$/.test(p.url()) && (await p.locator('ol.nli-passi li').count()) === 4, p.url());
  ok('i passi di Brevo: Carica, Abbina i dati, Scegli la lista, Concludi', (await p.locator('ol.nli-passi').innerText()).replace(/\s+/g, ' ').includes('Carica') && /Abbina i dati.*Scegli la lista.*Concludi/s.test(await p.locator('ol.nli-passi').innerText()));
  ok('passo 1: scelta del file con il limite del server scritto, e Copia e incolla', (await p.locator('#nli-file[data-peso-massimo]').count()) === 1 && (await p.locator('input[name=modo][value=incolla]').count()) === 1);
  await vai('newsletter-contatti.php#nlx-importa');
  await p.waitForURL(/newsletter-importa\.php/, { timeout: 5000 }).catch(() => {});
  ok('il vecchio indirizzo del riquadro (#nlx-importa) porta alla pagina nuova', /newsletter-importa\.php/.test(p.url()), p.url());
  await foto('importa-1');

  // ===== 1. CSV con il punto e virgola, UTF-8
  const csv1 = scrivi('contatti.csv', ['EMAIL;NOME;COGNOME;CITTA;PUNTI;CLIENTE;TIPO;INTERESSI;Arrivo;Note',
    `a1${D};Anna;Rossi;Roma;12,5;sì;Azienda;Corsi, Eventi;15/03/2026;una nota`,
    `a2${D};Bruno;Verdi;;3;no;Privato;Libri;2026-01-02;`,
    `a1${D};Anna doppia;;Milano;;;;;;`,
    'non è una email;Zeta;;;;;;;;',
    `a3${D};Carla;;Milano;non-numero;forse;Sconosciuto;Pesca;31/02/2026;`,
    `A4${D.toUpperCase()};Dario;;;;;;;;`].join('\n') + '\n');
  const id1 = await passo1({ file: csv1 });
  ok('passo 1: il file diventa un\'importazione in bozza e si va al passo 2', id1 > 0 && /passo|id=/.test(p.url()) && (await p.locator('#nli-abbina').count()) === 1, p.url());
  const sel = async (c) => colonna(c).inputValue();
  ok('passo 2: le colonne con il nome di un attributo sono già abbinate (EMAIL, NOME, CITTA, TIPO...)', (await sel('EMAIL')) === 'EMAIL' && (await sel('NOME')) === 'NOME' && (await sel('CITTA')) === 'CITTA' && (await sel('TIPO')) === 'TIPO' && (await sel('INTERESSI')) === 'INTERESSI');
  ok('passo 2: le colonne sconosciute restano "Non importare"', (await sel('Arrivo')) === '' && (await sel('Note')) === '');
  const anteprima = await p.locator('table.nli-abbina').innerText();
  ok('passo 2: l\'anteprima mostra le prime righe del file', /Roma/.test(anteprima) && /a1@csv/.test(anteprima) && /Corsi, Eventi/.test(anteprima));
  await foto('importa-2', '#nli-abbina');
  // Due colonne sullo stesso attributo: errore sotto la colonna
  await colonna('Note').selectOption('CITTA');
  await invia('#nli-abbina button[type=submit]');
  ok('passo 2: lo stesso attributo su due colonne è un errore, detto sotto la colonna sbagliata', /colonna da sistemare/.test(await flash()) && (await p.locator('table.nli-abbina tr:has(b:text-is("Note")) .errore-campo[role=alert]').count()) === 1, await flash());
  await colonna('Note').selectOption('');
  await colonna('EMAIL').selectOption('');
  await invia('#nli-abbina button[type=submit]');
  ok('passo 2: senza EMAIL non si va avanti', /EMAIL/.test(await flash()) && (await p.locator('#nli-abbina').count()) === 1, await flash());
  await passo2({ EMAIL: 'EMAIL', Arrivo: { nuovo: 'DATA_ARRIVO', tipo: 'date' } });
  ok('passo 2: "Crea un nuovo attributo" crea DATA_ARRIVO di tipo Data', uno("SELECT tipo FROM {p}newsletter_attributi WHERE nome = 'DATA_ARRIVO'") === 'date' && (await p.locator('#nli-liste').count()) === 1);
  await invia('#nli-liste button[type=submit]');
  ok('passo 3: senza lista non si va avanti, e lo dice', /almeno una lista/.test(await flash()) && (await p.locator('#nli-liste .errore-campo[role=alert]').count()) === 1);
  await foto('importa-3', '#nli-liste');
  await passo3([], { nome: 'PI Clienti', cartella });
  const clienti = +uno("SELECT id FROM {p}newsletter_liste WHERE nome = 'PI Clienti'");
  ok('passo 3: lista nuova nella cartella scelta', clienti > 0 && +uno(`SELECT cartella_id FROM {p}newsletter_liste WHERE id = ${clienti}`) === cartella && (await p.locator('#nli-conferma').count()) === 1);
  const riep = await p.locator('#nli-conferma').innerText();
  ok('passo 4: riepilogo, le quattro opzioni, l\'avviso "non si torna indietro" e il consenso', /PI Clienti/.test(riep) && /DATA_ARRIVO/.test(riep) && (await p.locator('input[name^="opz["]').count()) === 4 && /non si torna indietro/.test(riep) && (await p.locator('input[name=consenso][required]').count()) === 1);
  ok('passo 4: "celle vuote" è spenta finché non si accende "aggiorna" (come Brevo)', await p.locator('input[name="opz[vuote]"]').isDisabled());
  await p.check('input[name="opz[aggiorna]"]');
  ok('passo 4: con "aggiorna" acceso "celle vuote" si accende', !(await p.locator('input[name="opz[vuote]"]').isDisabled()));
  await p.uncheck('input[name="opz[aggiorna]"]');
  await foto('importa-4', '#nli-conferma');
  // Senza la spunta del consenso (anche togliendo il required dal browser): il server rifiuta
  await p.evaluate(() => document.querySelector('input[name=consenso]').removeAttribute('required'));
  await invia('#nli-conferma button[type=submit]');
  ok('passo 4: senza il consenso il server non parte, e lo dice sotto la casella', (await p.locator('#nli-consenso-err[role=alert]').count()) === 1 && uno(`SELECT stato FROM {p}newsletter_importazioni WHERE id = ${id1}`) === 'bozza');
  let r = await passo4();
  ok('resoconto: 4 nuovi, 1 doppione, 1 errore', r['Nuovi'] === 4 && r['Doppioni nel file'] === 1 && r['Errori'] === 1 && r['Aggiornati'] === 0, r);
  await foto('importa-resoconto', '#nli-resoconto');
  let v = val(`a1${D}`);
  ok('valori: testo, numero con la virgola, sì/no, categoria per etichetta, scelta multipla, data italiana', v.CITTA === 'Roma' && v.PUNTI === 12.5 && v.CLIENTE === 1 && v.TIPO === 2 && JSON.stringify(v.INTERESSI) === '[1,2]' && v.DATA_ARRIVO === '2026-03-15' && v.NOME === 'Anna' && v.COGNOME === 'Rossi', v);
  v = val(`a3${D}`);
  ok('valori del tipo sbagliato ignorati senza errore (numero, sì/no, categoria, opzione, data del 31 febbraio)', v.CITTA === 'Milano' && v.PUNTI === undefined && v.CLIENTE === undefined && v.TIPO === undefined && v.INTERESSI === undefined && v.DATA_ARRIVO === undefined, v);
  ok('doppione: vale la prima riga; email in minuscolo', val(`a1${D}`).CITTA === 'Roma' && idDi(`a4${D}`) > 0);
  ok('i nuovi: fonte import, nella lista, registro del consenso e cronologia', +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti i JOIN {p}newsletter_iscritti_liste l ON l.iscritto_id = i.id AND l.lista_id = ${clienti} WHERE i.fonte = 'import' AND i.stato = 'attivo' AND i.${prova}`) === 4
    && +uno(`SELECT COUNT(*) FROM {p}newsletter_consensi c JOIN {p}newsletter_iscritti i ON i.id = c.contatto_id WHERE c.tipo = 'import' AND i.${prova}`) === 4 && +uno(`SELECT COUNT(*) FROM {p}newsletter_storia s JOIN {p}newsletter_iscritti i ON i.id = s.contatto_id WHERE s.tipo = 'importato' AND i.${prova}`) === 4);
  const scarti = await scarica(() => p.click('a:has-text("Scarica le righe scartate")'));
  const sr = righeCsv(scarti);
  ok('CSV degli scarti: RIGA, MOTIVO e le colonne del file; doppione e email non valida con il motivo', sr[0][0] === 'RIGA' && sr[0][1] === 'MOTIVO' && sr[0][2] === 'EMAIL' && sr.length === 3 && /Doppione/.test(scarti) && /Email non valida/.test(scarti) && /Anna doppia/.test(scarti), sr);
  await vai('newsletter-importa.php');
  ok('Importazioni recenti: la riga con i numeri e il link al resoconto', /4\s+0\s+0\s+2/.test((await p.locator('.form-card:has(h3:text-is("Importazioni recenti")) tbody tr').first().innerText()).replace(/\t/g, ' ')), await p.locator('.form-card:has(h3:text-is("Importazioni recenti")) tbody tr').first().innerText());

  // ===== 2. CSV con la virgola in Windows-1252, gli esistenti senza aggiornamento, due liste
  const csv2 = scrivi('windows.csv', ['EMAIL,NOME,CITTA', `w1${D},Niccolò,Forlì`, `a1${D},Annamaria,Torino`, `"w2${D}","Pàola, detta ""Pà""",Città di Castello`].join('\r\n') + '\r\n', 'latin1');
  r = await importa({ file: csv2 }, { liste: [clienti, principale] });
  ok('Windows-1252 con la virgola: accenti giusti, virgolette e virgole dentro le celle', val(`w1${D}`).NOME === 'Niccolò' && val(`w1${D}`).CITTA === 'Forlì' && val(`w2${D}`).NOME === 'Pàola, detta "Pà"' && val(`w2${D}`).CITTA === 'Città di Castello', [val(`w1${D}`), val(`w2${D}`)]);
  ok('senza "aggiorna" chi c\'è già resta com\'è ma entra nelle liste (invariato)', r['Nuovi'] === 2 && r['Invariati'] === 1 && val(`a1${D}`).NOME === 'Anna' && val(`a1${D}`).CITTA === 'Roma' && +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE iscritto_id = ${idDi(`a1${D}`)} AND lista_id = ${principale}`) === 1, r);

  // ===== 3. Aggiorna, celle vuote con e senza l'opzione
  const csv3 = scrivi('aggiorna.csv', `EMAIL;NOME;CITTA;PUNTI\na1${D};Annalisa;;\n`);
  r = await importa({ file: csv3 }, { liste: [clienti], opz: { aggiorna: true } });
  v = val(`a1${D}`);
  ok('aggiorna senza "celle vuote": cambia il nome, le celle vuote non toccano città e punti', r['Aggiornati'] === 1 && v.NOME === 'Annalisa' && v.CITTA === 'Roma' && v.PUNTI === 12.5, [r, v]);
  r = await importa({ file: csv3 }, { liste: [clienti], opz: { aggiorna: true, vuote: true } });
  v = val(`a1${D}`);
  ok('aggiorna con "celle vuote": la città e i punti si svuotano', r['Aggiornati'] === 1 && v.CITTA === undefined && v.PUNTI === undefined && v.NOME === 'Annalisa', [r, v]);
  r = await importa({ file: csv3 }, { liste: [clienti], opz: { aggiorna: true, vuote: true } });
  ok('lo stesso file una seconda volta: niente da cambiare, invariato', r['Aggiornati'] === 0 && r['Invariati'] === 1, r);
  q(`UPDATE {p}newsletter_iscritti SET nome = 'Anna' WHERE email = 'a1${D}'`);
  await vai('newsletter-contatto.php?id=' + idDi(`a1${D}`) + '&scheda=cronologia');
  ok('la cronologia del contatto dice che l\'ha aggiornato un\'importazione', /importazione n\. \d+/.test(await p.locator('.admin-content').innerText()));

  // ===== 4. Chi è in lista nera e chi ha cancellato i dati
  q(`UPDATE {p}newsletter_iscritti SET stato = 'bloccato', blocco_motivo = 'adminBlocked', blocco_at = NOW() WHERE email = 'a2${D}'`);
  q("INSERT IGNORE INTO {p}newsletter_soppressi (impronta, creato) VALUES (SHA2('cancellato@csv.prova-importa.it', 256), NOW())");
  const csv4 = scrivi('nera.csv', `EMAIL;NOME\na2${D};Bruno di nuovo\ncancellato${D};Chi\nw1${D};Niccolò\n`);
  r = await importa({ file: csv4 }, { nuova: { nome: 'PI Terza' }, opz: { aggiorna: true } });
  const terza = +uno("SELECT id FROM {p}newsletter_liste WHERE nome = 'PI Terza'");
  ok('chi è in lista nera resta in lista nera, invariato e fuori dalla lista', uno(`SELECT stato FROM {p}newsletter_iscritti WHERE email = 'a2${D}'`) === 'bloccato' && val(`a2${D}`).NOME === 'Bruno' && +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE iscritto_id = ${idDi(`a2${D}`)} AND lista_id = ${terza}`) === 0 && r['Invariati'] === 2, r);
  ok('chi ha chiesto di cancellare i suoi dati non rientra (errore, con il motivo nel resoconto)', idDi(`cancellato${D}`) === 0 && r['Errori'] === 1 && /cancellare i loro dati/.test(await p.locator('#nli-resoconto').innerText()), r);

  // ===== 5. Opzione "Metti in lista nera"
  r = await importa({ testo: `EMAIL\nn1@nera.prova-importa.it\nw1${D}` }, { liste: [terza], opz: { nera: true } });
  ok('"Metti in lista nera email": il nuovo e quello che c\'era vanno in lista nera con il motivo importazione', r['Nuovi'] === 1 && r['Aggiornati'] === 1 && q("SELECT stato, blocco_motivo FROM {p}newsletter_iscritti WHERE email IN ('n1@nera.prova-importa.it', 'w1@csv.prova-importa.it')").every(x => x.stato === 'bloccato' && x.blocco_motivo === 'import'), r);

  // ===== 6. XLSX come Excel e come LibreOffice
  const xe = path.join(T, 'excel.xlsx'), xl = path.join(T, 'libreoffice.xlsx');
  cli('xlsx', xe, 'excel'); cli('xlsx', xl, 'libreoffice');
  r = await importa({ file: xe }, { liste: [clienti] });
  v = val('excel1@xlsx.prova-importa.it');
  ok('XLSX di Excel: testi condivisi, data dal numero seriale, numero con i decimali', r['Nuovi'] === 2 && v.DATA_ARRIVO === '2026-03-15' && v.PUNTI === 12.5 && /^Màrio/.test(v.NOME || ''), [r, v]);
  const v2 = val('excel2@xlsx.prova-importa.it');
  ok('XLSX di Excel: testo ricco unito, risultato della formula, 1 gennaio 2025', v2.NOME === 'Rosa' && v2.PUNTI === 2 && v2.DATA_ARRIVO === '2025-01-01', v2);
  await vai('newsletter-contatto.php?id=' + idDi('excel1@xlsx.prova-importa.it'));
  ok('XLSX: l\'emoji del nome arriva intera (tabelle a 3 byte: entità dentro, carattere fuori)', (await p.locator('.admin-content').innerText()).includes('Màrio \u{1F600}'));
  r = await importa({ file: xl }, { liste: [clienti] });
  v = val('libre1@xlsx.prova-importa.it');
  ok('XLSX di LibreOffice: testi in linea, formato di data scritto, numero negativo, sì/no, foglio con un altro nome', r['Nuovi'] === 2 && v.NOME === 'Niccolò' && v.DATA_ARRIVO === '2025-12-31' && v.PUNTI === -3.25 && v.CLIENTE === 1 && val('libre2@xlsx.prova-importa.it').CLIENTE === 0, [r, v]);
  const xx = path.join(T, 'cattivo.xlsx'); cli('xlsx', xx, 'xxe');
  await passo1({ file: xx });
  ok('XLSX con un\'entità esterna: rifiutato con il motivo sotto il campo', /DTD/.test(await p.locator('#nli-file-err').innerText().catch(() => '')) && (await p.locator('#nli-carica').count()) === 1);
  await passo1({ file: scrivi('vecchio.xls', 'non un excel') });
  ok('un .xls vecchio: il messaggio dice di salvarlo come .xlsx o .csv', /\.xlsx o \.csv/.test(await p.locator('#nli-file-err').innerText().catch(() => '')));

  // ===== 7. Copia e incolla
  r = await importa({ testo: `EMAIL\tNOME\tCITTA\nincolla1@pi.prova-importa.it\tIvo\tBari\nincolla2@pi.prova-importa.it\tUgo\tLecce` }, { liste: [clienti] });
  ok('Copia e incolla da un foglio di calcolo (tabulazioni)', r['Nuovi'] === 2 && val('incolla1@pi.prova-importa.it').CITTA === 'Bari', r);
  const idSolo = await passo1({ testo: 'solo1@pi.prova-importa.it\nsolo2@pi.prova-importa.it\n' });
  ok('Copia e incolla di soli indirizzi, senza intestazioni: la colonna si abbina da sola a EMAIL', (await colonna('Colonna 1').inputValue()) === 'EMAIL', idSolo);
  await passo2(); await passo3([clienti]); r = await passo4();
  ok('soli indirizzi: entrano tutti e due', r['Nuovi'] === 2, r);

  // ===== 8. 20000 righe a giri dalla pagina
  const grande = path.join(T, 'grande.csv'); cli('righe', grande, '20000', ';');
  const t0 = Date.now(); giri = 0;
  await passo1({ file: grande }); await passo2(); await passo3([], { nome: 'PI Grande' });
  r = await passo4({}, 600000);
  const grandeId = +uno("SELECT id FROM {p}newsletter_liste WHERE nome = 'PI Grande'");
  ok('20000 righe: tutte dentro, nella lista nuova', r['Nuovi'] === 20000 && +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti_liste WHERE lista_id = ${grandeId}`) === 20000, r);
  ok('20000 righe: a giri (la pagina ne ha chiesti ' + giri + ', in ' + Math.round((Date.now() - t0) / 1000) + ' s)', giri >= 1, giri);
  ok('20000 righe: le email viste per i doppioni si tolgono alla fine', +uno('SELECT COUNT(*) FROM {p}newsletter_importa_viste') === 0);

  // ===== 9. Un'importazione confermata che finisce con il cron
  const cronFile = path.join(T, 'cron.csv'); fs.writeFileSync(cronFile, 'EMAIL;NOME\n' + Array.from({ length: 300 }, (_, i) => `cron${i}@cron.prova-importa.it;Cron ${i}`).join('\n') + '\n');
  const idCron = await passo1({ file: cronFile }); await passo2(); await passo3([clienti]);
  q(`UPDATE {p}newsletter_importazioni SET stato = 'pronta', opzioni = '{"aggiorna":false,"vuote":false,"nera":false,"flussi":false}' WHERE id = ${idCron}`);
  execFileSync('php', [path.join(DIR, 'cron-newsletter.php')], { encoding: 'utf8' });
  ok('un\'importazione confermata la finisce il cron (cron-newsletter.php)', uno(`SELECT stato FROM {p}newsletter_importazioni WHERE id = ${idCron}`) === 'finita' && +uno("SELECT COUNT(*) FROM {p}newsletter_iscritti WHERE email LIKE '%@cron.prova-importa.it'") === 300);

  // ===== 10. Esportazione dalla finestra
  q(`UPDATE {p}newsletter_iscritti SET nome = '=CMD("calc")', cognome = '@SOMMA(A1)' WHERE email = 'a3${D}'`);
  const a3 = idDi(`a3${D}`); const citta = +uno("SELECT id FROM {p}newsletter_attributi WHERE nome = 'CITTA'");
  q(`DELETE FROM {p}newsletter_valori WHERE contatto_id = ${a3} AND attributo_id = ${citta}`);
  q(`INSERT INTO {p}newsletter_valori (contatto_id, attributo_id, opzione, v_testo) VALUES (${a3}, ${citta}, 0, '+39 Milano')`);
  await vai('newsletter-contatti.php');
  await p.click('button:has-text("Esporta i contatti")');
  ok('Esporta: la finestra con quali contatti, gli attributi e il separatore', await p.locator('#nlx-esporta').isVisible() && (await p.locator('#nlx-esporta input[name="esp_col[]"]').count()) >= 12 && (await p.locator('#nlx-esporta input[name=esp_sep]').count()) === 2);
  await p.check('#nlx-esporta input[name=esp_origine][value=lista]');
  await p.selectOption('#nlx-esp-lista', String(clienti)); await p.selectOption('#nlx-esp-lista-chi', 'iscritti');
  for (const c of await p.locator('#nlx-esporta input[name="esp_col[]"]:not([disabled])').all()) {
    if (!['NOME', 'CITTA', 'PUNTI'].includes(await c.getAttribute('value'))) await c.uncheck();
  }
  await p.check('#nlx-esporta input[name=esp_sep][value=","]');
  await foto('esporta-finestra', '#nlx-esporta');
  let csv = await scarica(() => p.click('#nlx-esporta-avvia'));
  let rr = righeCsv(csv, ',');
  const iscrittiClienti = +uno(`SELECT COUNT(*) FROM {p}newsletter_iscritti i JOIN {p}newsletter_iscritti_liste l ON l.iscritto_id = i.id WHERE l.lista_id = ${clienti} AND i.stato = 'attivo'`);
  ok('Esporta una lista, solo gli iscritti, tre attributi, con la virgola', rr[0].join(',') === 'EMAIL,NOME,CITTA,PUNTI' && rr.length - 1 === iscrittiClienti && !csv.includes(`a2${D}`) && !csv.includes(`w1${D}`), [rr[0], rr.length - 1, iscrittiClienti]);
  const riga3 = rr.find(x => x[0] === `a3${D}`) || [];
  ok('Esporta: le celle che sembrano formule (=, +, @) escono con l\'apostrofo; il numero negativo resta un numero', riga3[1] === `'=CMD("calc")` && riga3[2] === "'+39 Milano" && (rr.find(x => x[0] === 'libre1@xlsx.prova-importa.it') || [])[3] === '-3,25', [riga3, rr.find(x => x[0] === 'libre1@xlsx.prova-importa.it')]);
  // Una campagna inviata: aperto, non aperto, cliccato, non cliccato
  q(`INSERT INTO {p}newsletter_campagne (oggetto, anteprima, corpo, stato, destinatari, inviate, liste, creato, aggiornato) VALUES ('PI Campagna', '', '<p>x</p>', 'inviata', 2, 2, '${clienti}', NOW(), NOW())`);
  const camp = +uno("SELECT MAX(id) FROM {p}newsletter_campagne WHERE oggetto = 'PI Campagna'");
  const ia1 = idDi(`a1${D}`), ia4 = idDi(`a4${D}`);
  q(`INSERT INTO {p}newsletter_coda (campagna_id, iscritto_id, stato, inviata_at) VALUES (${camp}, ${ia1}, 'inviata', NOW()), (${camp}, ${ia4}, 'inviata', NOW())`);
  q(`INSERT INTO {p}newsletter_eventi (campagna_id, iscritto_id, tipo, url, creato) VALUES (${camp}, ${ia1}, 'apertura', '', NOW()), (${camp}, ${ia1}, 'clic', 'https://esempio.it/', NOW())`);
  const perCampagna = {};
  for (const chi of ['aperto', 'non_aperto', 'cliccato', 'non_cliccato']) {
    await vai('newsletter-contatti.php');
    await p.click('button:has-text("Esporta i contatti")');
    await p.check('#nlx-esporta input[name=esp_origine][value=campagna]');
    await p.selectOption('#nlx-esp-camp', String(camp)); await p.selectOption('#nlx-esp-camp-chi', chi);
    perCampagna[chi] = righeCsv(await scarica(() => p.click('#nlx-esporta-avvia'))).slice(1).map(x => x[0]).join(' ');
  }
  ok('Esporta una campagna: aperto, non aperto, cliccato, non cliccato', perCampagna.aperto === `a1${D}` && perCampagna.non_aperto === `a4${D}` && perCampagna.cliccato === `a1${D}` && perCampagna.non_cliccato === `a4${D}`, perCampagna);
  // Azione di gruppo Esporta sulle righe spuntate: apre la stessa finestra
  await vai('newsletter-contatti.php?cerca=' + encodeURIComponent(`a1${D}`));
  await p.check(`input.nlx-sel[value="${ia1}"]`); await p.selectOption('#nlx-cosa', 'esporta'); await p.click('#nlx-applica');
  ok('azione di gruppo Esporta: apre la finestra (non la conferma)', await p.locator('#nlx-esporta').isVisible());
  csv = await scarica(() => p.click('#nlx-esporta-avvia'));
  rr = righeCsv(csv);
  ok('Esporta i contatti scelti: tutte le colonne di partenza, il punto e virgola, una riga', rr[0][0] === 'EMAIL' && rr[0].includes('DATA_ARRIVO') && rr[0].includes('LISTE') && rr.length === 2 && rr[1][0] === `a1${D}`, rr[0]);
  // File grande: a giri, poi Scarica dal riquadro Esportazioni
  await vai('newsletter-contatti.php?lista=' + grandeId);
  await p.check('#nlx-tutti'); await p.selectOption('#nlx-cosa', 'esporta'); await p.click('#nlx-applica');
  let grandeCsv = '';
  const attesa = Promise.race([p.waitForEvent('download', { timeout: 600000 }).then(d => ['file', d]), p.waitForURL(/#nlx-esportazioni/, { timeout: 600000 }).then(() => ['giri'])]).catch(e => ['errore', e]);
  await p.click('#nlx-esporta-avvia');
  const esito = await attesa;
  if (esito[0] === 'file') grandeCsv = fs.readFileSync(await esito[1].path(), 'utf8');
  else {
    // La pagina chiede i giri da sola e si ricarica quando il file è pronto: allora c'è il suo Scarica.
    const ultima = +uno('SELECT MAX(id) FROM {p}newsletter_esportazioni');
    await p.waitForSelector(`#nlx-esportazioni a[href$="esportazione=${ultima}"]`, { timeout: 600000 });
    grandeCsv = await scarica(() => p.click(`#nlx-esportazioni a[href$="esportazione=${ultima}"]`));
  }
  ok('Esporta 20000 contatti (' + (esito[0] === 'file' ? 'pronto in un giro' : 'a giri, poi dal riquadro Esportazioni') + ')', righeCsv(grandeCsv).length === 20001, righeCsv(grandeCsv).length);
  await vai('newsletter-contatti.php');
  ok('il riquadro Esportazioni elenca i file con la scadenza di 7 giorni', /Pronta, fino al/.test(await p.locator('#nlx-esportazioni').innerText()) && /7 giorni/.test(await p.locator('#nlx-esportazioni').innerText()));

  // ===== 11. Andata e ritorno: esportazione reimportata uguale
  await p.click('button:has-text("Esporta i contatti")');
  await p.check('#nlx-esporta input[name=esp_origine][value=lista]'); await p.selectOption('#nlx-esp-lista', String(clienti)); await p.selectOption('#nlx-esp-lista-chi', 'tutti');
  const andata = await scarica(() => p.click('#nlx-esporta-avvia'));
  const fa = path.join(T, 'andata.csv'); fs.writeFileSync(fa, andata);
  r = await importa({ file: fa }, { liste: [clienti], opz: { aggiorna: true, vuote: true } });
  ok('reimportare l\'esportazione con "aggiorna" e "celle vuote" non cambia niente (0 aggiornati)', r['Aggiornati'] === 0 && r['Nuovi'] === 0 && r['Errori'] === 0, r);
  // Via i contatti della lista (non con Elimina definitivamente, che tiene l'impronta), poi dentro di nuovo dal file
  const ids = q(`SELECT iscritto_id AS id FROM {p}newsletter_iscritti_liste WHERE lista_id = ${clienti}`).map(x => +x.id);
  for (const t of ['newsletter_valori:contatto_id', 'newsletter_iscritti_liste:iscritto_id', 'newsletter_storia:contatto_id', 'newsletter_consensi:contatto_id', 'newsletter_coda:iscritto_id', 'newsletter_eventi:iscritto_id', 'newsletter_iscritti:id']) {
    const [tab, col] = t.split(':'); q(`DELETE FROM {p}${tab} WHERE ${col} IN (${ids.join(',')})`);
  }
  r = await importa({ file: fa }, { liste: [clienti] });
  await vai('newsletter-contatti.php');
  await p.click('button:has-text("Esporta i contatti")');
  await p.check('#nlx-esporta input[name=esp_origine][value=lista]'); await p.selectOption('#nlx-esp-lista', String(clienti)); await p.selectOption('#nlx-esp-lista-chi', 'tutti');
  const ritorno = await scarica(() => p.click('#nlx-esporta-avvia'));
  const confronta = (t) => { const rr = righeCsv(t); const via = ['CREATION_DATE', 'LAST_CHANGED', 'ISCRITTO', 'LISTA_NERA', 'MOTIVO', 'LISTE'].map(c => rr[0].indexOf(c)); return rr.map(x => x.filter((_, i) => !via.includes(i)).join('|')).sort().join('\n'); };
  ok('andata e ritorno: contatti tolti e reimportati dal file escono uguali (' + ids.length + ' contatti, tutti gli attributi)', r['Nuovi'] === ids.length && confronta(andata) === confronta(ritorno), [r, confronta(andata).split('\n').find(l => !confronta(ritorno).includes(l))]);

  // ===== 12. Lista dei test
  await vai('newsletter.php?scheda=impostazioni#nl-test');
  ok('Impostazioni: il riquadro Lista dei test', (await p.locator('#nl-test h3:text-is("Lista dei test")').count()) === 1);
  await p.fill('#nl-test-indirizzi', 'nessuno@pi.prova-importa.it'); await invia('#nl-test button[type=submit]');
  ok('Lista dei test: un indirizzo che non è un contatto è rifiutato (Brevo)', /non sono ancora contatti/.test(await flash()) && !uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_lista_test'"), await flash());
  await p.fill('#nl-test-indirizzi', Array.from({ length: 21 }, (_, i) => `t${i}@pi.prova-importa.it`).join('\n')); await invia('#nl-test button[type=submit]');
  ok('Lista dei test: al massimo 20 indirizzi', /Al massimo 20/.test(await flash()), await flash());
  await p.fill('#nl-test-indirizzi', `incolla1@pi.prova-importa.it\nincolla2@pi.prova-importa.it`); await invia('#nl-test button[type=submit]');
  ok('Lista dei test salvata con due contatti', JSON.parse(uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_lista_test'") || '[]').length === 2, await flash());
  await foto('lista-test', '#nl-test');
  q(`INSERT INTO {p}newsletter_campagne (oggetto, anteprima, corpo, stato, liste, creato, aggiornato) VALUES ('PI Prova {{ contact.NOME }}', '', '<p>Ciao {{ contact.NOME }} da {{ contact.CITTA | default:"ovunque" }}.</p>', 'bozza', '${clienti}', NOW(), NOW())`);
  const cp = +uno("SELECT MAX(id) FROM {p}newsletter_campagne WHERE oggetto LIKE 'PI Prova%'");
  await vai('newsletter-campagna.php?id=' + cp + '&apri=prova'); // 2.11.0: la prova sta nella sezione Anteprima e prova del riepilogo
  ok('campagna: la prova propone i contatti della lista dei test, spuntati', (await p.locator('#nlc-prova input[name="prova_test[]"]:checked').count()) === 2);
  await foto('prova-campagna', '#nlc-prova');
  if (SMTP && POSTA) {
    // La posta del sito verso il server finto
    await vai('impostazioni.php');
    await p.fill('#smtp_host', '127.0.0.1'); await p.fill('#smtp_porta', SMTP); await p.selectOption('#smtp_sicurezza', 'nessuna');
    await p.fill('[name=smtp_mittente]', 'sito@esempio.it').catch(() => {});
    await invia('form:has(input[value=save_posta]) button[type=submit]');
    const prima = fs.readdirSync(POSTA).length;
    await vai('newsletter-campagna.php?id=' + cp + '&apri=prova'); // 2.11.0: la prova sta nella sezione Anteprima e prova del riepilogo
    await p.fill('#nlc-prova-a', `w2${D}`); await p.check('input[name=prova_aggiungi]');
    await invia('#nlc-prova button[value=prova]');
    const f = await flash();
    const nuove = fs.readdirSync(POSTA).map(x => [x, fs.statSync(path.join(POSTA, x)).mtimeMs]).sort((a, b) => a[1] - b[1]).slice(prima).map(x => fs.readFileSync(path.join(POSTA, x[0]), 'utf8'));
    const per = (e) => nuove.find(m => new RegExp('^To:.*' + e.replace(/[.@]/g, '\\$&'), 'mi').test(m)) || '';
    const dec = (m) => { const qp = m.replace(/=\r?\n/g, '').replace(/=([0-9A-F]{2})/g, (_, h) => String.fromCharCode(parseInt(h, 16))); const b = [...m.matchAll(/\r?\n\r?\n([A-Za-z0-9+/=\r\n]{40,})/g)].map(x => { try { return Buffer.from(x[1].replace(/\s/g, ''), 'base64').toString('utf8'); } catch (e) { return ''; } }).join(' '); return qp + ' ' + b; };
    ok('la prova parte per i contatti della lista dei test e per l\'indirizzo scritto', /Prova spedita a/.test(f) && nuove.length === 3, [f, nuove.length]);
    ok('ognuno la riceve con i suoi dati (Ivo da Bari, Ugo da Lecce)', /Ciao Ivo da Bari/.test(dec(per('incolla1@pi.prova-importa.it'))) && /Ciao Ugo da Lecce/.test(dec(per('incolla2@pi.prova-importa.it'))), dec(per('incolla1@pi.prova-importa.it')).slice(0, 300));
    ok('la prova non porta il pixel delle aperture né i link tracciati', !nuove.some(m => /width="1" height="1"/.test(dec(m))), '');
    ok('"Aggiungi alla lista dei test": l\'indirizzo scritto ora è nella lista', JSON.parse(uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_lista_test'") || '[]').length === 3);
    ok('le prove non contano nelle statistiche della campagna', +uno(`SELECT COUNT(*) FROM {p}newsletter_coda WHERE campagna_id = ${cp}`) === 0 && +uno(`SELECT COUNT(*) FROM {p}newsletter_eventi WHERE campagna_id = ${cp}`) === 0);
  }

  // ===== 13. Telefono: 390 px senza scorrimento di lato
  const bozza = await passo1({ file: csv1 });
  for (const w of [390, 768]) {
    await p.setViewportSize({ width: w, height: 800 });
    for (const u of ['newsletter-importa.php', `newsletter-importa.php?id=${bozza}&passo=2`, `newsletter-importa.php?id=${id1}`, 'newsletter-contatti.php', 'newsletter.php?scheda=impostazioni', 'newsletter-campagna.php?id=' + cp]) {
      await vai(u);
      const lato = await p.evaluate(() => document.documentElement.scrollWidth - innerWidth);
      ok(`${w} px: ${u} non scorre di lato`, lato <= 1, lato);
    }
    await vai('newsletter-contatti.php'); await p.click('button:has-text("Esporta i contatti")');
    ok(`${w} px: la finestra Esporta sta nello schermo`, await p.evaluate(() => { const d = document.getElementById('nlx-esporta').getBoundingClientRect(); return d.left >= 0 && d.right <= innerWidth + 1; }) && await p.evaluate(() => document.documentElement.scrollWidth - innerWidth) <= 1);
    if (w === 390) await foto('importa-390');
  }
  await p.setViewportSize({ width: 1280, height: 900 });
  await vai(`newsletter-importa.php?id=${bozza}&passo=2`); await invia('.nli-lascia button[type=submit]');
  ok('"Lascia perdere": la bozza e il suo file se ne vanno', +uno(`SELECT COUNT(*) FROM {p}newsletter_importazioni WHERE id = ${bozza}`) === 0);

  // ===== 14. Redattore: non vede e non salva
  execFileSync('php', [path.join(__dirname, 'tema-cli.php'), DIR, 'redattore', 'Redattore-Prova-2026!']);
  const ctxR = await br.newContext(); const pr = await ctxR.newPage();
  await pr.goto(B + '/admin/login.php'); await pr.fill('#username', 'redattore-prova'); await pr.fill('#password', 'Redattore-Prova-2026!');
  await Promise.all([pr.waitForNavigation(), pr.click('button[type=submit]')]);
  const espId = +uno("SELECT MAX(id) FROM {p}newsletter_esportazioni WHERE stato = 'finita'");
  let visto = [];
  for (const u of ['newsletter-importa.php', `newsletter-importa.php?id=${id1}`, `newsletter-importa.php?id=${id1}&scarica=scarti`, `newsletter-contatti.php?esportazione=${espId}`, 'newsletter.php?scheda=impostazioni']) {
    const rr2 = await pr.goto(B + '/admin/' + u, { waitUntil: 'networkidle' }).catch(() => null);
    if (!/index\.php/.test(pr.url()) || /a1@csv|RIGA;MOTIVO/.test(await pr.content())) visto.push(u);
  }
  ok('redattore: importazione, scarti, esportazioni e lista dei test lo rimandano alla dashboard', visto.length === 0, visto);
  const tkR = await pr.evaluate(() => (document.querySelector('input[name=_csrf]') || {}).value || '');
  const quanteImp = +uno('SELECT COUNT(*) FROM {p}newsletter_importazioni');
  await pr.request.post(B + '/admin/newsletter-importa.php', { multipart: { _csrf: tkR, azione: 'carica', modo: 'incolla', testo: 'EMAIL\nred@pi.prova-importa.it' }, maxRedirects: 0 });
  await pr.request.post(B + '/admin/newsletter.php', { form: { _csrf: tkR, azione: 'lista_test', indirizzi: `a1${D}` }, maxRedirects: 0 });
  await pr.request.post(B + '/admin/newsletter-contatti.php', { form: { _csrf: tkR, azione: 'gruppo', cosa: 'esporta', tutti: '1' }, maxRedirects: 0 });
  ok('redattore: i POST non importano, non esportano e non cambiano la lista dei test', +uno('SELECT COUNT(*) FROM {p}newsletter_importazioni') === quanteImp && JSON.parse(uno("SELECT setting_value FROM {p}settings WHERE setting_key = 'newsletter_lista_test'") || '[]').length >= 2
    && !idDi('red@pi.prova-importa.it'));
  await ctxR.close();

  ok('nessun errore JavaScript', err.length === 0, err);
  console.log(`\n${n - ko} su ${n} OK`);
  if (!process.env.LASCIA) pulisci();
  fs.rmSync(T, { recursive: true, force: true });
  await br.close();
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
