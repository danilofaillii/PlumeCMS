<?php
/**
 * AI collegate > Collegamenti: l'indirizzo del connettore con le istruzioni, le AI collegate (con Revoca), le chiavi
 * personali (crea, mostrata una volta, revoca).
 */
require_once __DIR__ . '/admin-comune.php';
$adminTitle = 'AI collegate';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    $chi = (string)(current_user()['username'] ?? '');
    if ($azione === 'revoca') {
        $id = (int)($_POST['id'] ?? 0);
        $c = db()->prepare('SELECT * FROM ' . tbl('ai_connessioni') . ' WHERE id = ?'); $c->execute([$id]); $c = $c->fetch();
        if ($c && aic_connessione_revoca($id)) {
            aic_registra($c, 'revoca', 'ok', 'revocata dal pannello da ' . $chi);
            sicurezza_log('ai_revoca', $chi, (int)(current_user()['id'] ?? 0), 'connessione ' . $id . ' (' . $c['nome'] . ')');
            flash('success', ($c['tipo'] === 'chiave' ? 'Chiave revocata' : 'Collegamento revocato') . ': «' . $c['nome'] . '» non può più usare il sito. Le bozze e le proposte che ha lasciato restano.');
        } else flash('error', 'Collegamento non trovato o già revocato.');
        aic_admin_vai('collegate', [], $c && $c['tipo'] === 'chiave' ? 'chiavi' : 'collegate');
    }
    if ($azione === 'chiave') {
        $nome = aic_testo($_POST['nome'] ?? '', 100);
        $perm = aic_scope_pulito((array)($_POST['permessi'] ?? []));
        if (!cms_modulo('statistiche')) $perm = array_values(array_diff($perm, ['statistiche']));
        if ($nome === '') { flash('error', 'Scrivi un nome per la chiave (per esempio: "GPT del negozio"), così sai poi quale revocare.'); aic_admin_vai('collegate', [], 'chiavi'); }
        if (!$perm) { flash('error', 'Scegli almeno un permesso per la chiave.'); aic_admin_vai('collegate', [], 'chiavi'); }
        $conn = aic_connessione_crea('chiave', '', $nome, (int)(current_user()['id'] ?? 0), $perm);
        $_SESSION['aic_chiave_nuova'] = ['id' => $conn, 'chiave' => aic_token_crea($conn, 'chiave', null), 'nome' => $nome];
        aic_registra(['id' => $conn, 'nome' => $nome], 'chiave', 'ok', 'chiave creata da ' . $chi . ', permessi: ' . implode(', ', $perm));
        sicurezza_log('ai_chiave', $chi, (int)(current_user()['id'] ?? 0), 'chiave ' . $conn . ' (' . $nome . ')');
        aic_admin_vai('collegate', [], 'chiavi');
    }
    aic_admin_vai('collegate');
}
$nuova = $_SESSION['aic_chiave_nuova'] ?? null;
unset($_SESSION['aic_chiave_nuova']);
$tutte = aic_connessioni();
$oauth = array_values(array_filter($tutte, fn($c) => $c['tipo'] === 'oauth'));
$chiavi = array_values(array_filter($tutte, fn($c) => $c['tipo'] === 'chiave'));
$url = aic_url_mcp();
require CMS_ADMIN . '/includes/admin-header.php';
aic_admin_schede('collegate');
?>
<div class="form-card" id="indirizzo">
  <h3>L'indirizzo del connettore</h3>
  <div class="form-group">
    <label for="aic-url">Indirizzo da incollare nella tua AI</label>
    <input type="text" id="aic-url" class="aic-copia" value="<?= h($url) ?>" readonly data-copia-se-stesso>
    <div class="hint">Un clic sulla casella la seleziona e la copia. La tua AI chiede poi il permesso: si apre questo pannello, entri e scegli cosa può fare.</div>
  </div>
  <p class="aic-regola"><b>Regola fissa:</b> un'AI collegata non pubblica mai. Scrive bozze, che pubblichi tu dall'editor o dalla scheda; le modifiche a quello che è già online arrivano in <a href="<?= h(aic_admin_url('proposte')) ?>">Proposte</a> e le approvi tu.</p>
  <details class="pn-aiuto" id="come-claude"><summary>Come collegare Claude</summary>
    <ol class="aic-passi">
      <li>Apri Claude (claude.ai o l'app) ed entra in <b>Impostazioni</b>, poi <b>Connettori</b>.</li>
      <li>Premi <b>Aggiungi connettore personalizzato</b>.</li>
      <li>Scrivi un nome (per esempio il nome del sito) e incolla l'indirizzo qui sopra. Premi <b>Aggiungi</b>.</li>
      <li>Premi <b>Connetti</b> accanto al connettore: si apre la pagina di accesso di questo pannello. Entra con il tuo nome e la tua password (e il codice, se hai il secondo fattore).</li>
      <li>Nella pagina "Collegare un'AI al sito" controlla il nome e l'indirizzo di ritorno, lascia spuntati i permessi che vuoi dare e premi <b>Consenti</b>.</li>
      <li>Torni in Claude: il connettore è collegato. In una chat chiedi per esempio "Fai l'audit SEO del sito".</li>
    </ol>
    <p class="hint">I nomi dei menu di Claude possono cambiare con gli aggiornamenti dell'app: cerca la voce dei connettori (MCP).</p>
  </details>
  <details class="pn-aiuto" id="come-chatgpt"><summary>Come collegare ChatGPT</summary>
    <ol class="aic-passi">
      <li>Apri ChatGPT ed entra in <b>Impostazioni</b>, poi <b>App e connettori</b>.</li>
      <li>Se serve, accendi la <b>Modalità sviluppatore</b> (in <b>Impostazioni avanzate</b>).</li>
      <li>Premi <b>Crea</b> (o <b>Aggiungi connettore</b>), scrivi un nome, incolla l'indirizzo qui sopra come <b>URL del server MCP</b> e scegli l'autenticazione <b>OAuth</b>.</li>
      <li>Conferma: si apre la pagina di accesso di questo pannello. Entra e premi <b>Consenti</b> nella pagina del consenso.</li>
      <li>In una chat scegli il connettore fra gli strumenti e chiedi quello che ti serve.</li>
    </ol>
    <p class="hint">Se il tuo ChatGPT non ha i connettori MCP, usa una chiave personale e le Azioni di un GPT (sotto, "Altre AI").</p>
  </details>
  <details class="pn-aiuto" id="come-altre"><summary>Altre AI: chiave personale e OpenAPI</summary>
    <ol class="aic-passi">
      <li>Crea una chiave personale qui sotto (riquadro "Chiavi personali") con i permessi che servono, e copiala: si vede una volta sola.</li>
      <li>Nella tua AI aggiungi un'azione (o uno strumento) dallo schema OpenAPI a questo indirizzo: <code><?= h(aic_issuer() . '/api/mcp/openapi') ?></code></li>
      <li>Come autenticazione scegli <b>Chiave API</b> di tipo <b>Bearer</b> e incolla la chiave.</li>
      <li>Le AI che parlano MCP ma non OAuth usano lo stesso indirizzo del connettore con l'intestazione <code>Authorization: Bearer</code> e la chiave.</li>
    </ol>
  </details>
</div>

<div class="form-card" id="collegate">
  <h3>AI collegate</h3>
  <?php if (!$oauth): ?>
  <div class="empty-state">Ancora nessuna AI collegata: incolla l'indirizzo del connettore nella tua AI (passi qui sopra) e dai il permesso quando si apre questo pannello.</div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>AI</th><th>Permesso dato da</th><th>Permessi</th><th>Collegata il</th><th>Ultima attività</th><th>Azioni</th></tr></thead>
    <tbody>
    <?php foreach ($oauth as $c): ?>
    <tr data-connessione="<?= (int)$c['id'] ?>">
      <td><b><?= h((string)$c['nome']) ?></b></td>
      <td><?= h((string)($c['username'] ?? '')) ?></td>
      <td><?= h(aic_permessi_testo((string)$c['scope'])) ?></td>
      <td><?= h(aic_data($c['creato'])) ?></td>
      <td><?= h(aic_data($c['ultimo'])) ?></td>
      <td><form method="post" data-conferma="Revocare il collegamento con «<?= h((string)$c['nome']) ?>»? Non potrà più leggere né scrivere sul sito; per ricollegarla serve di nuovo il tuo permesso." data-conferma-ok="Revoca il collegamento" data-conferma-pericolo><?= csrf_field() ?><input type="hidden" name="azione" value="revoca"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Revoca</button></form></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="form-card" id="chiavi">
  <h3>Chiavi personali</h3>
  <?php if ($nuova): ?>
  <div class="aic-chiave" role="status">
    <p><b>Chiave «<?= h($nuova['nome']) ?>» creata.</b> Copiala adesso: per sicurezza non si vedrà più. Se la perdi, revocala e creane un'altra.</p>
    <label for="aic-chiave-nuova">La chiave</label>
    <input type="text" id="aic-chiave-nuova" class="aic-copia" value="<?= h($nuova['chiave']) ?>" readonly data-copia-se-stesso>
  </div>
  <?php endif; ?>
  <p class="hint">Per le AI che non fanno il collegamento con il permesso (OAuth): un GPT con le Azioni, uno script, un'altra app. Ha gli stessi permessi e le stesse regole: niente pubblicazioni.</p>
  <?php if ($chiavi): ?>
  <table class="table">
    <thead><tr><th>Nome</th><th>Creata da</th><th>Permessi</th><th>Creata il</th><th>Ultima attività</th><th>Azioni</th></tr></thead>
    <tbody>
    <?php foreach ($chiavi as $c): ?>
    <tr data-chiave="<?= (int)$c['id'] ?>">
      <td><b><?= h((string)$c['nome']) ?></b></td>
      <td><?= h((string)($c['username'] ?? '')) ?></td>
      <td><?= h(aic_permessi_testo((string)$c['scope'])) ?></td>
      <td><?= h(aic_data($c['creato'])) ?></td>
      <td><?= h(aic_data($c['ultimo'])) ?></td>
      <td><form method="post" data-conferma="Revocare la chiave «<?= h((string)$c['nome']) ?>»? Chi la usa non entra più." data-conferma-ok="Revoca la chiave" data-conferma-pericolo><?= csrf_field() ?><input type="hidden" name="azione" value="revoca"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Revoca</button></form></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="empty-state">Ancora nessuna chiave personale: creane una qui sotto se la tua AI non fa il collegamento con il permesso.</div>
  <?php endif; ?>
  <form method="post" class="aic-nuova-chiave" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="azione" value="chiave">
    <div class="form-group"><label for="aic-nome-chiave">Nome della chiave</label><input type="text" name="nome" id="aic-nome-chiave" maxlength="100" placeholder="per esempio: GPT del negozio"><div class="hint">Serve a te, per riconoscerla nell'elenco e nel registro.</div></div>
    <?= aic_caselle_permessi('permessi', AIC_PERMESSI_PARTENZA) ?>
    <button class="btn btn-primary" type="submit">Crea la chiave</button>
  </form>
</div>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
