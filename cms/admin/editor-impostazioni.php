<?php
/**
 * Impostazioni dell'editor (gruppo Editor): le opzioni generali (quante versioni tenere, chi pubblica, colonne massime,
 * animazioni d'ingresso), il rimando alle larghezze e alle soglie di tablet e telefono (Stile del sito > Layout),
 * e il trasloco: Esporta tutte le impostazioni dell'editor in un JSON unico (stile, header e footer con le strutture,
 * menu, temi, impostazioni, widget attivi) e Importa con l'anteprima di cosa cambierebbe e la conferma.
 * Motore: includes/editor-opzioni.php.
 *
 *   POST azione=salva                 le opzioni
 *   GET  esporta=1                    scarica il JSON
 *   POST azione=anteprima file        carica il JSON, lo mette da parte e mostra cosa cambierebbe
 *   POST azione=importa token ambiti[] applica gli ambiti scelti dal file messo da parte
 *   POST azione=annulla token         butta via il file
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/editor.php';
require_once dirname(__DIR__) . '/includes/design.php';
require_admin();
$adminTitle = "Impostazioni dell'editor";
$autore = (string)(current_user()['username'] ?? '');

/** La cartella dove aspetta il file caricato fra l'anteprima e la conferma (quella dei pacchetti, altrimenti quella temporanea). */
function ei_cartella(): string {
    $d = cms_radice() . '/aggiornamenti';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return is_dir($d) && is_writable($d) ? $d : sys_get_temp_dir();
}

function ei_file(string $token): string {
    return ei_cartella() . '/editor-importa-' . $token . '.json';
}

/** Il token valido solo per questa sessione e con il file ancora lì. */
function ei_token_valido(string $token): bool {
    return preg_match('/^[a-f0-9]{32}$/', $token) && ($_SESSION['editor_importa_token'] ?? '') === $token && is_file(ei_file($token));
}

if (isset($_GET['esporta'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="editor-impostazioni-' . date('Y-m-d') . '.json"');
    echo editor_esporta_json();
    exit;
}

$anteprima = null;
$token = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? 'salva');
    try {
        if ($azione === 'salva') {
            editor_opzioni_salva([
                'versioni_limite' => (int)($_POST['versioni_limite'] ?? 50),
                'pubblica' => (string)($_POST['pubblica'] ?? 'tutti'),
                'colonne_max' => (int)($_POST['colonne_max'] ?? 4),
                'animazioni' => !empty($_POST['animazioni']) ? 1 : 0,
            ], $autore, 'dal pannello');
            flash('success', 'Impostazioni salvate: l\'editor le usa dalla prossima apertura.');
        } elseif ($azione === 'anteprima') {
            $f = $_FILES['file'] ?? null;
            if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException('Nessun file ricevuto: scegli il file JSON esportato e riprova.');
            $json = (string)file_get_contents($f['tmp_name']);
            $dati = editor_importa_leggi($json);
            $token = bin2hex(random_bytes(16));
            if (file_put_contents(ei_file($token), $json) === false) throw new RuntimeException('Non riesco a mettere da parte il file (cartella aggiornamenti/ non scrivibile).');
            $_SESSION['editor_importa_token'] = $token;
            $anteprima = editor_importa_anteprima($dati);
        } elseif ($azione === 'importa') {
            $token = (string)($_POST['token'] ?? '');
            if (!ei_token_valido($token)) throw new RuntimeException('L\'anteprima è scaduta: carica di nuovo il file.');
            $dati = editor_importa_leggi((string)file_get_contents(ei_file($token)));
            $ambiti = array_values(array_intersect(array_map('strval', (array)($_POST['ambiti'] ?? [])), array_keys(EDITOR_ESPORTA_AMBITI)));
            if (!$ambiti) throw new RuntimeException('Scegli almeno una voce da importare.');
            $log = editor_importa($dati, $ambiti, $autore);
            @unlink(ei_file($token));
            unset($_SESSION['editor_importa_token']);
            flash('success', 'Importazione fatta. ' . implode(' ', $log));
            $token = '';
        } elseif ($azione === 'annulla') {
            $token = (string)($_POST['token'] ?? '');
            if (ei_token_valido($token)) @unlink(ei_file($token));
            unset($_SESSION['editor_importa_token']);
            $token = '';
            flash('info', 'Importazione annullata: niente è cambiato.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        $anteprima = null;
    }
    if ($anteprima === null) { header('Location: editor-impostazioni.php'); exit; }
}

$o = editor_opzioni();
$layout = design_valori()['layout'];
$csrf = csrf_token();
require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.ei-griglia{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1rem;align-items:start}
.ei-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.1rem 1.25rem}
.ei-card h2{font-size:1rem;margin:0 0 .7rem}
.ei-card p{font-size:.86rem;color:var(--text-muted);margin:.3rem 0 .6rem}
.ei-campo{margin-bottom:.9rem}
.ei-campo label{display:block;font-weight:600;font-size:.86rem;margin-bottom:.25rem}
.ei-campo input[type=number],.ei-campo select{padding:.4rem .6rem;border:1px solid var(--border);border-radius:8px;font:inherit;font-size:.9rem;background:var(--campo);color:var(--text);width:160px}
.ei-campo .ei-radio label{font-weight:400;display:flex;gap:.4rem;align-items:center;margin:.2rem 0}
.ei-campo small{display:block;color:var(--text-muted);font-size:.78rem;margin-top:.2rem}
.ei-layout td{padding:.25rem .4rem;font-size:.88rem;border-bottom:1px solid var(--border)}
.ei-layout td:last-child{text-align:right;font-weight:600}
.ei-anteprima h3{font-size:.92rem;margin:.9rem 0 .3rem;display:flex;gap:.5rem;align-items:center}
.ei-anteprima ul{margin:0 0 .4rem 1.1rem;font-size:.84rem;max-height:220px;overflow:auto}
.ei-anteprima li.nuovo{color:var(--successo-testo)}.ei-anteprima li.cambia{color:var(--attenzione-testo)}.ei-anteprima li.uguale{color:var(--text-muted)}
.ei-anteprima .ei-stato{font-size:.7rem;padding:.05rem .4rem;border-radius:999px;background:var(--bg);border:1px solid var(--border);text-transform:uppercase;letter-spacing:.05em}
</style>

<div class="ei-griglia">
  <form method="post" class="ei-card" data-senza-protezione>
    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="azione" value="salva">
    <h2>Opzioni generali</h2>
    <div class="ei-campo">
      <label for="versioni_limite">Versioni da tenere per ogni oggetto</label>
      <input type="number" id="versioni_limite" name="versioni_limite" min="5" max="500" value="<?= (int)$o['versioni_limite'] ?>">
      <small>Pagine, articoli, header e footer, menu, stile, temi: oltre questo numero le versioni più vecchie si eliminano da sole. Quelle pubblicate e quelle con una nota scritta a mano restano. Le vedi nella voce <a href="versioni.php">Versioni</a>.</small>
    </div>
    <div class="ei-campo">
      <label>Chi può pubblicare dall'editor</label>
      <div class="ei-radio">
        <label><input type="radio" name="pubblica" value="tutti" <?= $o['pubblica'] === 'tutti' ? 'checked' : '' ?>> Amministratori e redattori</label>
        <label><input type="radio" name="pubblica" value="admin" <?= $o['pubblica'] === 'admin' ? 'checked' : '' ?>> Solo gli amministratori (il redattore salva la bozza)</label>
      </div>
    </div>
    <div class="ei-campo">
      <label for="colonne_max">Colonne massime in una sezione</label>
      <select id="colonne_max" name="colonne_max"><?php for ($i = 2; $i <= 6; $i++): ?><option value="<?= $i ?>" <?= (int)$o['colonne_max'] === $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?></select>
      <small>Quante colonne propone il pulsante "Struttura" di una sezione. Le sezioni che hanno già più colonne restano come sono.</small>
    </div>
    <div class="ei-campo">
      <label style="display:flex;gap:.5rem;align-items:center"><input type="checkbox" name="animazioni" value="1" <?= (int)$o['animazioni'] === 1 ? 'checked' : '' ?>> Animazioni d'ingresso sul sito</label>
      <small>Le animazioni scelte nella scheda Avanzate (dissolvenza, dal basso, zoom...). Spente, gli elementi compaiono fermi; le scelte restano salvate per quando le riaccendi.</small>
    </div>
    <button class="btn btn-primary btn-sm">Salva</button>
  </form>

  <div class="ei-card">
    <h2>Larghezze e soglie</h2>
    <p>La larghezza del contenuto, gli spazi fra widget e colonne e le soglie di tablet e telefono stanno in <a href="design.php?scheda=layout">Stile del sito &gt; Layout</a>: valgono per il sito e per l'anteprima dell'editor.</p>
    <table class="ei-layout" style="width:100%;border-collapse:collapse">
      <tr><td>Larghezza del contenuto</td><td><?= (int)$layout['larghezza'] ?> px</td></tr>
      <tr><td>Spazio fra i widget</td><td><?= (int)$layout['spazio_widget'] ?> px</td></tr>
      <tr><td>Spazio fra le colonne</td><td><?= (int)$layout['spazio_colonne'] ?> px</td></tr>
      <tr><td>Tablet fino a</td><td><?= (int)$layout['tablet'] ?> px</td></tr>
      <tr><td>Telefono fino a</td><td><?= (int)$layout['telefono'] ?> px</td></tr>
    </table>
    <p style="margin-top:.8rem"><a class="btn btn-secondary btn-sm" href="design.php?scheda=layout">Apri Stile del sito &gt; Layout</a></p>
  </div>

  <div class="ei-card">
    <h2>Esporta</h2>
    <p>Un file JSON con tutto quello che è dell'editor: Stile del sito, header e footer con le loro strutture e condizioni, menu di navigazione, temi delle infografiche salvati, le opzioni di questa pagina e l'elenco dei widget spenti. Serve per portare l'aspetto in un'altra installazione del CMS o per tenere una copia.</p>
    <a class="btn btn-primary btn-sm" href="editor-impostazioni.php?esporta=1">Scarica il JSON</a>
  </div>

  <div class="ei-card ei-anteprima" id="importa">
    <h2>Importa</h2>
    <?php if ($anteprima === null): ?>
    <p>Carica un file esportato da qui o da un'altra installazione. Prima di cambiare qualcosa vedi l'elenco di cosa cambierebbe e scegli quali parti importare. Header, footer, menu e temi con lo stesso nome si aggiornano; gli altri si aggiungono. Niente si cancella.</p>
    <form method="post" enctype="multipart/form-data" data-senza-protezione style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="azione" value="anteprima">
      <input type="file" name="file" accept=".json,application/json" required>
      <button class="btn btn-secondary btn-sm">Mostra cosa cambierebbe</button>
    </form>
    <?php else: ?>
    <p>Ecco cosa cambierebbe. Spunta le parti da importare e conferma: le altre restano come sono.</p>
    <form method="post" data-senza-protezione>
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="azione" value="importa"><input type="hidden" name="token" value="<?= h($token) ?>">
      <?php foreach (EDITOR_ESPORTA_AMBITI as $k => $etichetta): if (!isset($anteprima[$k])) continue; $righe = $anteprima[$k]['righe']; $cambia = count(array_filter($righe, fn($r) => $r['stato'] !== 'uguale')); ?>
      <h3><label style="display:flex;gap:.5rem;align-items:center;font-weight:600"><input type="checkbox" name="ambiti[]" value="<?= h($k) ?>" <?= $cambia ? 'checked' : '' ?>> <?= h($etichetta) ?></label><span class="ei-stato"><?= $cambia ? $cambia . ($cambia === 1 ? ' modifica' : ' modifiche') : 'identico' ?></span></h3>
      <ul>
        <?php foreach (array_slice($righe, 0, 80) as $r): ?><li class="<?= h($r['stato']) ?>"><b><?= h($r['nome']) ?></b>: <?= h($r['dettaglio']) ?></li><?php endforeach; ?>
        <?php if (count($righe) > 80): ?><li class="uguale">e altre <?= count($righe) - 80 ?> righe</li><?php endif; ?>
      </ul>
      <?php endforeach; ?>
      <?php foreach (EDITOR_ESPORTA_AMBITI as $k => $etichetta): if (isset($anteprima[$k])) continue; ?><p><?= h($etichetta) ?>: non c'è nel file.</p><?php endforeach; ?>
      <div style="display:flex;gap:.5rem;margin-top:.8rem">
        <button class="btn btn-primary btn-sm" data-conferma="Importare le parti spuntate? Le versioni di oggi restano nella Cronologia.">Importa le parti spuntate</button>
        <button class="btn btn-secondary btn-sm" name="azione" value="annulla">Annulla</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
