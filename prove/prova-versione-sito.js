// L'indirizzo del sito ufficiale non si scrive più nel pannello (1.6.2, decisione di Danilo): è quello del codice e arriva
// con gli aggiornamenti. Qui si controlla che in Aggiornamenti il campo della 1.3.1 non ci sia più, che la pagina dica
// l'indirizzo ufficiale, che il vecchio POST "versione_sito" non faccia niente e che un indirizzo salvato in passato
// (impostazione versione_sito di un sito aggiornato) venga ignorato.
// Serve un sito senza CMS_AGGIORNAMENTI_URL nel config.local.php (con la costante la pagina mostra quella).
// uso: node prova-versione-sito.js <base> <utente> <password> [cartella CMS installato: scrive l'impostazione vecchia con settings-cli]
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const [B, UTENTE, PASSWORD, DIR] = process.argv.slice(2);
const UFFICIALE = 'https://plumecms.danilofailli.it';
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 200) : '')); };

(async () => {
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  const pagina = () => p.goto(B + '/admin/aggiornamenti.php', { waitUntil: 'networkidle' });
  const nota = async () => (await p.locator('.agg-note', { hasText: 'qual è l\'ultima versione' }).first().innerText());

  await pagina();
  ok('il campo "Indirizzo del sito da cui arrivano gli aggiornamenti" non c\'è più', await p.locator('input[name=sito]').count() === 0 && !(await p.locator('body').innerText()).includes('Salva l\'indirizzo'));
  ok('la pagina dice che chiede a ' + UFFICIALE, (await nota()).includes(UFFICIALE), await nota());
  // Il vecchio POST della 1.3.1: la pagina lo ignora, niente errori, l'indirizzo non cambia.
  const t = await p.evaluate(async () => {
    const csrf = document.querySelector('input[name=_csrf]').value;
    const r = await fetch('aggiornamenti.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _csrf: csrf, action: 'versione_sito', sito: 'https://www.esempio.it/plume' }).toString(), credentials: 'include' });
    return { status: r.status, testo: (await r.text()).slice(0, 4000) };
  });
  await pagina();
  ok('il vecchio POST "versione_sito" non cambia niente', t.status < 400 && !/Fatal|Warning/.test(t.testo) && (await nota()).includes(UFFICIALE) && !(await nota()).includes('esempio.it'), { status: t.status });
  if (DIR) {
    // Un sito aggiornato che aveva salvato un indirizzo suo: si ignora.
    execFileSync('php', [path.join(__dirname, 'settings-cli.php'), DIR, 'scrivi', 'versione_sito', 'https://www.esempio.it/plume']);
    await pagina();
    ok('un indirizzo salvato in passato (impostazione versione_sito) viene ignorato', (await nota()).includes(UFFICIALE) && !(await nota()).includes('esempio.it'), await nota());
    execFileSync('php', [path.join(__dirname, 'settings-cli.php'), DIR, 'scrivi', 'versione_sito', '']);
  } else {
    ok('(senza la cartella del CMS la prova dell\'impostazione vecchia si salta)', true);
  }
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
