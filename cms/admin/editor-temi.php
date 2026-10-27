<?php
/**
 * Temi delle infografiche (gruppo Editor): l'elenco dei temi dei widget ig_* con l'anteprima dei colori.
 * I dieci preimpostati si vedono e si possono duplicare; quelli salvati (tabella editor_temi) si rinominano,
 * si duplicano, si eliminano con conferma, si esportano in JSON e si importano da un JSON.
 *
 *   POST azione=rinomina id nome      POST azione=duplica chiave      POST azione=elimina id
 *   POST azione=importa file (JSON con un tema o un elenco di temi)
 *   GET  esporta=1                     scarica tutti i temi salvati     GET esporta=<chiave>  uno solo
 * Un widget che usa un tema eliminato torna all'aspetto "Carta" (il tema di partenza).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/editor.php';
require_once dirname(__DIR__) . '/includes/editor-temi.php';
require_admin(); // sicurezza (ruoli): solo amministratore
$adminTitle = 'Temi delle infografiche';
$autore = (string)(current_user()['username'] ?? '');

/** Un tema come viaggia nel JSON: nome, descrizione e i valori di stile. */
function et_tema_json(array $t): array {
    return ['nome' => $t['nome'], 'valori' => $t['valori']];
}

if (isset($_GET['esporta'])) {
    $tutti = editor_temi_infografica();
    $k = (string)$_GET['esporta'];
    if ($k !== '1' && !isset($tutti[$k])) { http_response_code(404); exit('Tema non trovato.'); }
    $lista = $k === '1' ? array_values(array_map('et_tema_json', editor_temi_salvati())) : [et_tema_json($tutti[$k])];
    $nome = $k === '1' ? 'temi-infografiche' : 'tema-' . preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($tutti[$k]['nome']));
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nome . '-' . date('Y-m-d') . '.json"');
    echo json_encode(['formato' => 'editor-temi', 'versione' => 1, 'creato' => date('Y-m-d H:i:s'), 'temi' => $lista], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    try {
        switch ($_POST['azione'] ?? '') {
            case 'rinomina':
                $nome = (string)($_POST['nome'] ?? '');
                editor_tema_rinomina($id, $nome);
                flash('success', 'Nome cambiato. I widget che usano il tema lo trovano con il nome nuovo.');
                break;
            case 'duplica':
                $tutti = editor_temi_infografica();
                $k = (string)($_POST['chiave'] ?? '');
                if (!isset($tutti[$k])) throw new RuntimeException('Tema non trovato.');
                $nome = mb_substr($tutti[$k]['nome'] . ' (copia)', 0, 100);
                editor_tema_salva($nome, $tutti[$k]['valori']); // versioni: editor_tema_salva registra la versione da solo
                flash('success', 'Copia creata: "' . $nome . '". Rinominala e aprila su un widget per cambiare i colori.');
                break;
            case 'elimina':
                $t = editor_temi_salvati()['t' . $id] ?? null;
                if (!$t) throw new RuntimeException('Tema non trovato (i preimpostati non si eliminano).');
                editor_tema_elimina($id); // versioni: registra l'ultima copia prima di cancellare
                flash('success', 'Tema "' . $t['nome'] . '" eliminato. I widget che lo usavano tornano all\'aspetto Carta.');
                break;
            case 'importa':
                $f = $_FILES['file'] ?? null;
                if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException('Nessun file ricevuto: scegli un file JSON e riprova.');
                if ((int)$f['size'] > 2 * 1024 * 1024) throw new RuntimeException('Il file supera i 2 MB.');
                $d = json_decode((string)file_get_contents($f['tmp_name']), true);
                if (!is_array($d)) throw new RuntimeException('Il file non è un JSON leggibile.');
                // Si accettano: un elenco "temi", un tema solo (nome + valori), oppure l'esportazione completa dell'editor.
                $lista = isset($d['temi']) && is_array($d['temi']) ? $d['temi'] : (isset($d['nome'], $d['valori']) ? [$d] : []);
                if (!$lista) throw new RuntimeException('Nel file non c\'è nessun tema (serve "temi": [...] oppure "nome" e "valori").');
                $miei = [];
                foreach (editor_temi_salvati() as $t) $miei[mb_strtolower($t['nome'])] = $t;
                $nuovi = 0; $aggiornati = 0;
                foreach ($lista as $t) {
                    if (!is_array($t) || trim((string)($t['nome'] ?? '')) === '' || !is_array($t['valori'] ?? null)) continue;
                    $nome = editor_tema_nome_valido((string)$t['nome']);
                    $valori = editor_tema_pulisci($t['valori']);
                    $k = mb_strtolower($nome);
                    if (isset($miei[$k])) {
                        db()->prepare('UPDATE ' . tbl('editor_temi') . ' SET valori = ?, aggiornato = NOW() WHERE id = ?')->execute([json_encode($valori, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int)$miei[$k]['id']]);
                        if (function_exists('editor_tema_versione')) editor_tema_versione((int)$miei[$k]['id'], 'importato da file'); // versioni
                        $aggiornati++;
                    } else {
                        editor_tema_salva($nome, $valori);
                        $nuovi++;
                    }
                }
                flash('success', "Importazione fatta: $nuovi temi nuovi, $aggiornati aggiornati (stesso nome).");
                break;
            default:
                throw new RuntimeException('Azione sconosciuta.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    header('Location: editor-temi.php');
    exit;
}

$temi = editor_temi_infografica();
$palette = igx_palette();
// I colori con un nome (testata, corpo) come li disegna il CSS delle infografiche, per l'anteprima a pastiglie.
$testate = ['scuro' => '#2d1b0e', 'scuro_sfumato' => '#2d1b0e', 'verde' => '#6b8f71', 'ciano' => '#4a7c82', 'rosso' => '#e8553e', 'mattone' => '#c0392b', 'viola' => '#7b5ea7', 'oro' => '#e8a830', 'oro_scuro' => '#b8860b',
    'ciano_viola' => '#4a7c82', 'rosso_viola' => '#e8553e', 'viola_rosso' => '#7b5ea7', 'rosso_scuro' => '#c0392b'];
$corpi = ['crema' => '#fbf6ee', 'bianco' => '#ffffff', 'scuro' => '#0a1024'];
$pastiglie = function (array $v) use ($palette, $testate, $corpi): array {
    $t1 = ($v['testata_colore'] ?? '') !== '' ? $v['testata_colore'] : ($testate[$v['testata'] ?? 'scuro'] ?? '#2d1b0e');
    $t2 = ($v['testata_colore2'] ?? '') !== '' ? $v['testata_colore2'] : $t1;
    $corpo = ($v['corpo'] ?? 'crema') === 'libero' && ($v['corpo_colore'] ?? '') !== '' ? $v['corpo_colore'] : ($corpi[$v['corpo'] ?? 'crema'] ?? '#fbf6ee');
    $bordo = ($v['bordo_colore'] ?? '') !== '' ? $v['bordo_colore'] : '#e8ddd0';
    $voci = ($v['colore_libero'] ?? '') !== '' ? $v['colore_libero'] : ($palette[$v['colore'] ?? ''] ?? '');
    $out = [['Testata', $t1, $t2], ['Corpo', $corpo, $corpo], ['Bordo', $bordo, $bordo]];
    if ($voci !== '') $out[] = ['Voci', $voci, $voci];
    return $out;
};
$usi = [];
// Quanti contenuti usano ogni tema (le strutture pubblicate e le bozze di pagine, articoli e modelli).
try {
    foreach (['pages', 'articles', 'modelli'] as $tab) {
        foreach (db()->query('SELECT struttura, struttura_bozza FROM ' . tbl($tab) . " WHERE (struttura IS NOT NULL AND struttura <> '') OR (struttura_bozza IS NOT NULL AND struttura_bozza <> '')") as $r) {
            foreach ([$r['struttura'], $r['struttura_bozza']] as $j) {
                if (!$j || !preg_match_all('/"tema"\s*:\s*"([a-z0-9_]+)"/', (string)$j, $m)) continue;
                foreach (array_unique($m[1]) as $k) $usi[$k] = ($usi[$k] ?? 0) + 1;
            }
        }
    }
} catch (Throwable $e) { /* senza una tabella l'elenco resta senza i conteggi */ }
$csrf = csrf_token();
require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="assets/css/contenuti.css">
<style>
.tm-testa{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem}
.tm-testa p{margin:0;font-size:.88rem;color:var(--text-muted);flex:1 1 100%}
.tm-griglia{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:1rem}
.tm-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.1rem;display:flex;flex-direction:column;gap:.6rem}
.tm-card h3{margin:0;font-size:1rem;display:flex;justify-content:space-between;gap:.5rem;align-items:center}
.tm-card h3 .badge{font-size:.68rem}
.tm-desc{font-size:.84rem;color:var(--text-muted);margin:0;min-height:2.4em}
.tm-anteprima{border-radius:10px;overflow:hidden;border:1px solid var(--border);font-size:.8rem}
.tm-anteprima .tm-t{padding:.5rem .7rem;font-weight:700;color:#fff}
.tm-anteprima .tm-c{padding:.55rem .7rem;display:flex;gap:.4rem;align-items:center;color:#5a4a3a}
.tm-anteprima .tm-c i{display:inline-block;width:10px;height:10px;border-radius:50%}
.tm-pastiglie{display:flex;gap:.5rem;flex-wrap:wrap;font-size:.74rem;color:var(--text-muted)}
.tm-pastiglie span{display:inline-flex;align-items:center;gap:.3rem}
.tm-pastiglie i{display:inline-block;width:16px;height:16px;border-radius:4px;border:1px solid rgba(0,0,0,.12)}
.tm-azioni{display:flex;gap:.4rem;flex-wrap:wrap;margin-top:auto}
.tm-azioni form{display:inline}
.tm-rinomina{display:none;gap:.4rem;align-items:center}
.tm-rinomina input{padding:.35rem .5rem;border:1px solid var(--border);border-radius:6px;font:inherit;font-size:.85rem;flex:1}
.tm-card.rinomina .tm-rinomina{display:flex}
.tm-card.rinomina .tm-azioni{display:none}
.tm-importa{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.1rem;margin-top:1.4rem;display:flex;gap:.8rem;align-items:center;flex-wrap:wrap}
.tm-importa p{margin:0;font-size:.85rem;color:var(--text-muted);flex:1 1 100%}
</style>

<div class="tm-testa">
  <a class="btn btn-secondary btn-sm" href="editor-temi.php?esporta=1">Esporta i temi salvati (JSON)</a>
  <a class="btn btn-secondary btn-sm" href="versioni.php?ambito=tema">Cronologia</a>
  <p>Un tema è un insieme di colori e forme per le infografiche (testata, corpo, bordo, angoli, carattere, palette delle voci). Si applica dal campo <b>Tema</b> di ogni widget infografica nell'editor; da lì, con "Salva come tema", nasce un tema nuovo. I dieci preimpostati restano sempre; quelli salvati da te si rinominano, si duplicano e si eliminano qui.</p>
</div>

<div class="tm-griglia">
<?php foreach ($temi as $k => $t): $p = $pastiglie($t['valori']); $salvato = isset($t['id']); $n = $usi[$k] ?? 0; ?>
  <div class="tm-card" id="tema-<?= h($k) ?>">
    <h3><span class="tm-nome"><?= h($t['nome']) ?></span><span class="badge <?= $salvato ? 'badge-published' : 'badge-cluster' ?>"><?= $salvato ? 'salvato' : 'preimpostato' ?></span></h3>
    <p class="tm-desc"><?= h($t['descrizione']) ?><?= $n ? ' Usato in ' . $n . ($n === 1 ? ' contenuto.' : ' contenuti.') : '' ?></p>
    <div class="tm-anteprima">
      <div class="tm-t" style="background:linear-gradient(135deg,<?= h($p[0][1]) ?>,<?= h($p[0][2]) ?>);color:<?= h(($t['valori']['testata_testo'] ?? '') !== '' ? $t['valori']['testata_testo'] : '#fff') ?>">Titolo dell'infografica</div>
      <div class="tm-c" style="background:<?= h($p[1][1]) ?>;border-top:<?= (int)($t['valori']['bordo_spessore'] ?? 1) ?>px solid <?= h($p[2][1]) ?>;color:<?= ($t['valori']['corpo'] ?? '') === 'scuro' ? '#e6ebf5' : '#5a4a3a' ?>"><i style="background:<?= h($p[3][1] ?? '#e8553e') ?>"></i> Prima voce <i style="background:<?= h($p[3][1] ?? '#e8a830') ?>"></i> Seconda voce</div>
    </div>
    <div class="tm-pastiglie"><?php foreach ($p as [$nome, $c1]): ?><span><i style="background:<?= h($c1) ?>"></i><?= h($nome) ?></span><?php endforeach; ?><span>Angoli: <?= h($t['valori']['angoli'] ?? 'arrotondati') ?></span><span>Carattere: <?= h($t['valori']['carattere'] ?? 'sistema') ?></span></div>
    <form method="post" class="tm-rinomina" data-senza-protezione>
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="azione" value="rinomina"><input type="hidden" name="id" value="<?= (int)($t['id'] ?? 0) ?>">
      <input type="text" name="nome" value="<?= h($t['nome']) ?>" maxlength="100" required aria-label="Nuovo nome del tema">
      <button class="btn btn-primary btn-sm">Salva</button>
      <button type="button" class="btn btn-secondary btn-sm" data-al-clic="tmRinomina(this, false)">Annulla</button>
    </form>
    <div class="tm-azioni">
      <?php if ($salvato): ?><button type="button" class="btn btn-secondary btn-sm tm-apri-rinomina" data-al-clic="tmRinomina(this, true)">Rinomina</button><?php endif; ?>
      <form method="post"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="azione" value="duplica"><input type="hidden" name="chiave" value="<?= h($k) ?>"><button class="btn btn-secondary btn-sm">Duplica</button></form>
      <a class="btn btn-secondary btn-sm" href="editor-temi.php?esporta=<?= h($k) ?>">Esporta</a>
      <?php if ($salvato): ?>
      <form method="post" data-conferma="Eliminare il tema &quot;<?= h(($t['nome'])) ?>&quot;?<?= $n ? ' Lo usano ' . $n . ' contenuti: torneranno all\'aspetto Carta.' : '' ?> Non si può annullare."><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn btn-danger btn-sm">Elimina</button></form>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>

<form method="post" enctype="multipart/form-data" class="tm-importa" data-senza-protezione>
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="azione" value="importa">
  <p><b>Importa temi da un file JSON</b> esportato da qui (o da un'altra installazione, anche dall'esportazione completa di Impostazioni dell'editor). Un tema con lo stesso nome di uno salvato lo aggiorna; gli altri si aggiungono.</p>
  <input type="file" name="file" accept=".json,application/json" required>
  <button class="btn btn-primary btn-sm">Importa</button>
</form>
<script<?= nonce_attr() ?>>
// Rinomina di un tema: apre e chiude la casella dentro la sua scheda (chiamata da pannello.js, data-al-clic).
function tmRinomina(el, apri) { var c = el.closest('.tm-card'); if (!c) return; c.classList.toggle('rinomina', apri); if (apri) { var i = c.querySelector('input[name=nome]'); if (i) i.focus(); } }
</script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
