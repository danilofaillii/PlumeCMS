// UX 2026-11 (punto 2 deciso da Danilo): "Aggiungi articolo" crea subito una bozza; se la si apre e la si chiude senza
// scrivere niente, il giro del mattino del cron la mette nel cestino dopo un giorno. Mai una bozza con dentro qualcosa.
// La prova crea, come una persona, cinque bozze dal pannello:
//   A articolo vuoto, vecchio di due giorni           -> nel cestino
//   B pagina vuota, vecchia di due giorni             -> nel cestino
//   C articolo con un testo scritto nell'editor, vecchio -> resta
//   D articolo con il titolo cambiato nella scheda, vecchio -> resta
//   E articolo vuoto di adesso                        -> resta (meno di un giorno)
// poi preme Esegui adesso in Impostazioni > Log cron e controlla la riga nel registro. Alla fine toglie le sue bozze.
// uso: node prove/prova-bozze-vuote.js <base> <cartella CMS installato> <utente> <password>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const pnConferme = require('./conferme-pannello');
const [B, DIR, U, P] = process.argv.slice(2);
const cli = (...a) => execFileSync('php', [path.join(__dirname, 'bozze-cli.php'), DIR, ...a], { encoding: 'utf8' }).trim();
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 300) : '')); };
(async () => {
  const br = await chromium.launch(); const p = await br.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message)); pnConferme.on(p, d => d.accept());
  await p.goto(B + '/admin/login.php'); await p.fill('#username', U); await p.fill('#password', P);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  // Una bozza nuova come la crea "+ Aggiungi": l'editor si apre su editor.php?tipo=...&id=N
  const nuova = async (tipo) => {
    await p.goto(`${B}/admin/contenuto.php?tipo=${tipo}`, { waitUntil: 'networkidle' });
    const id = +(new URL(p.url()).searchParams.get('id') || 0);
    await p.goto(`${B}/admin/contenuti.php?tipo=${tipo}`, { waitUntil: 'networkidle' }); // e la si chiude senza fare niente
    return id;
  };
  const A = await nuova('articoli'), Bp = await nuova('pagine'), C = await nuova('articoli'), D = await nuova('articoli'), E = await nuova('articoli');
  ok('cinque bozze nuove dal pannello', [A, Bp, C, D, E].every(x => x > 0) && new Set([A, C, D, E]).size === 4, [A, Bp, C, D, E]);
  // C: un testo scritto nell'editor (salvataggio della bozza dell'editor). D: il titolo cambiato nella scheda.
  cli('testo', 'articoli', String(C), 'Un testo vero, da non perdere.');
  await p.goto(`${B}/admin/contenuto.php?tipo=articoli&id=${D}`, { waitUntil: 'networkidle' });
  const titolo = p.locator('#sc-title');
  const haTitolo = await titolo.count() === 1;
  if (haTitolo) {
    await titolo.fill('Un titolo scritto da me');
    await Promise.all([p.waitForNavigation(), p.locator('button:has-text("Salva bozza")').first().click()]);
  }
  ok('D: titolo cambiato dalla scheda', haTitolo && JSON.parse(cli('stato', 'articoli', String(D))).titolo === 'Un titolo scritto da me', cli('stato', 'articoli', String(D)));
  for (const [t, id] of [['articoli', A], ['pagine', Bp], ['articoli', C]]) cli('invecchia', t, String(id), '48');
  cli('invecchia', 'articoli', String(D), '48', '30'); // nata due giorni fa, salvata il giorno dopo
  // Il giro del mattino dal pannello.
  await p.goto(B + '/admin/cron-log.php', { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation({ timeout: 120000 }), p.click('#cron-esegui')]);
  const pagina = await p.locator('.admin-content').innerText();
  ok('Log cron: la riga delle bozze vuote messe nel cestino', /Bozze vuote mai salvate messe nel cestino: [2-9]/.test(pagina), (pagina.match(/[^\n]*[Bb]ozze vuote[^\n]*/) || [''])[0]);
  const st = (t, id) => JSON.parse(cli('stato', t, String(id)));
  ok('A: articolo vuoto di due giorni fa nel cestino', st('articoli', A).cestino === true);
  ok('B: pagina vuota di due giorni fa nel cestino', st('pagine', Bp).cestino === true);
  ok('C: articolo con un testo scritto nell\'editor resta', st('articoli', C).cestino === false);
  ok('D: articolo con il titolo cambiato resta', st('articoli', D).cestino === false);
  ok('E: bozza vuota di adesso resta (meno di un giorno)', st('articoli', E).cestino === false);
  // Dal cestino si ripristina: la bozza A compare nel filtro Cestino degli articoli.
  await p.goto(B + '/admin/contenuti.php?tipo=articoli&stato=cestino', { waitUntil: 'networkidle' });
  ok('A si ritrova in Articoli > Cestino', await p.locator(`input[value="${A}"], a[href*="id=${A}"]`).count() > 0);
  // Un secondo giro non tocca le altre.
  await p.goto(B + '/admin/cron-log.php', { waitUntil: 'networkidle' });
  await Promise.all([p.waitForNavigation({ timeout: 120000 }), p.click('#cron-esegui')]);
  ok('secondo giro: C, D ed E restano', !st('articoli', C).cestino && !st('articoli', D).cestino && !st('articoli', E).cestino);
  ok('nessun errore JavaScript', err.length === 0, err);
  // Pulizia: anche le altre bozze della prova vanno nel cestino (si possono ripristinare).
  for (const id of [C, D, E]) cli('cestina', 'articoli', String(id));
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
