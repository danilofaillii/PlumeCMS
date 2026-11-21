// 2.2.0: "A chi arriva la richiesta" del widget Richiesta di prenotazione lo cambia solo l'amministratore. Un redattore vede il
// campo spento nell'editor e, se manda lo stesso un altro indirizzo all'API dell'editor (con il suo token vero), il server rimette
// quello di prima; un widget nuovo messo dal redattore ha il campo vuoto (gli avvisi del sito).
// uso: node prove/prova-solo-admin.js <base> <cartella CMS> <utente admin> <password>   (sito installato, nessun addon richiesto)
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const { pagina, entraPannello } = require('./addon-a-comune');
const [B, DIR, UTENTE, PASSWORD] = process.argv.slice(2);
const php = codice => execFileSync('php', ['-r', `$_SERVER['HTTP_HOST']='127.0.0.1'; $_SERVER['REQUEST_URI']='/'; require '${DIR}/includes/config.php'; ${codice}`], { encoding: 'utf8' });
let n = 0, ko = 0; const ok = (t, c, x = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (x !== '' ? ' ' + JSON.stringify(x).slice(0, 300) : '')); };
(async () => {
  // La pagina, preparata dall'amministratore (da riga di comando, come farebbe lui dall'editor).
  const id = +php(`require_once '${DIR}/includes/editor-controlli.php'; require_once '${DIR}/includes/editor.php'; editor_carica_widget_esterni();
    $pdo = db(); $st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute(['solo-admin']); $id = (int)$st->fetchColumn();
    if (!$id) { $pdo->exec('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES ('Solo admin', 'solo-admin', 'published')"); $id = (int)$pdo->lastInsertId(); }
    $w = array_replace_recursive(editor_widget('richiesta_prenotazione'), ['id' => 'rpprova1', 'impostazioni' => ['email_a' => 'titolare@esempio.it']]);
    editor_salva($id, editor_valida(['elementi' => [['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]]]]), true, 'prova', 'prova');
    echo $id;`).trim();
  const struttura = () => php(`$st = db()->prepare('SELECT CONCAT(COALESCE(struttura,""), " ", COALESCE(struttura_bozza,"")) FROM ' . tbl('pages') . ' WHERE id = ?'); $st->execute([${id}]); echo $st->fetchColumn();`);
  ok('(preparazione) la pagina con la richiesta che arriva a titolare@esempio.it', id > 0 && struttura().includes('titolare@esempio.it'), id);
  const br = await chromium.launch(); const err = [];
  const a = await pagina(br, err); await entraPannello(a, B, UTENTE, PASSWORD);
  // Un redattore.
  await a.goto(B + '/admin/utenti.php', { waitUntil: 'networkidle' });
  if (!(await a.content()).includes('red_solo')) {
    await a.fill('form.sc-corpo [name=username]', 'red_solo'); await a.fill('form.sc-corpo [name=email]', 'red_solo@esempio.it');
    await a.selectOption('form.sc-corpo [name=role]', 'editor'); await a.fill('form.sc-corpo [name=password]', 'Redattore-Solo-2026'); await a.fill('form.sc-corpo [name=password2]', 'Redattore-Solo-2026');
    await Promise.all([a.waitForNavigation(), a.click('form.sc-corpo button.btn-primary')]);
  }
  const red = await pagina(br, err); await entraPannello(red, B, 'red_solo', 'Redattore-Solo-2026');
  const apri = async (p) => {
    await p.goto(B + `/admin/editor.php?tipo=pagine&id=${id}`, { waitUntil: 'networkidle' }); await p.waitForTimeout(1200);
    if (await p.locator('#ed-chiudi-revisioni').isVisible()) await p.click('#ed-chiudi-revisioni');
    await p.frameLocator('iframe').first().locator('[data-ed-tipo="richiesta_prenotazione"]').first().click(); await p.waitForTimeout(500);
    await p.click('.ed-schede-pannello button[data-pannello="modifica"]'); await p.waitForTimeout(300);
    const c = p.locator('#ed-controlli .ed-controllo', { hasText: 'A chi arriva la richiesta' });
    if (!(await c.isVisible())) { const s = p.locator('#ed-controlli details.ed-sez', { has: p.locator('summary', { hasText: /^Richiesta$/ }) }); if (await s.count() && await s.getAttribute('open') === null) await s.locator('summary').click(); }
    return c;
  };
  let c = await apri(red);
  ok('redattore: nell\'editor "A chi arriva la richiesta" si vede ma è spento', await c.isVisible() && await c.locator('input').isDisabled() && /titolare@esempio\.it/.test(await c.locator('input').inputValue()));
  // Il redattore manda all'API un albero con l'indirizzo cambiato e un widget nuovo con un suo indirizzo.
  const manda = (p, email) => p.evaluate(async ([id, email]) => {
    const D = JSON.parse(document.querySelector('#ed-dati').textContent);
    const al = JSON.parse(JSON.stringify(D.albero));
    const col = al.elementi[0].figli[0];
    col.figli[0].impostazioni.email_a = email;
    col.figli.push({ id: 'rpnuovo' + Math.floor(Math.random() * 1e6), tipo: 'richiesta_prenotazione', impostazioni: { email_a: email } });
    const fd = new FormData();
    for (const [k, v] of Object.entries({ _csrf: D.csrf, id, tipo: 'pagine', azione: 'salva', albero: JSON.stringify(al), pubblica: 0, nota: '' })) fd.append(k, v);
    const x = await fetch('editor-api.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    return x.status;
  }, [String(id), email]);
  let r = await manda(red, 'ladro@cattivo.example');
  let s = struttura();
  ok('redattore: l\'API salva la pagina ma l\'indirizzo resta quello del titolare, e il widget nuovo non ha il suo', r === 200 && !s.includes('cattivo.example') && s.includes('titolare@esempio.it'), r);
  // L'amministratore lo cambia.
  c = await apri(a);
  ok('amministratore: il campo è acceso', await c.isVisible() && !(await c.locator('input').isDisabled()));
  r = await manda(a, 'nuovo-titolare@esempio.it');
  s = struttura();
  ok('amministratore: l\'indirizzo nuovo si salva', r === 200 && s.includes('nuovo-titolare@esempio.it'), r);
  ok('nessun errore JavaScript', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
