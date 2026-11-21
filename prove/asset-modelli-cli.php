<?php
/**
 * 2.38.1: prepara un sito di prova per prove/prova-asset-modelli.js (mai nel prodotto).
 * Mette un Riquadro con immagine con la foto a sinistra nel riquadro "Aggiungi sempre alla fine" (pubblicato) e uno
 * con la foto a destra nel footer predefinito, e stampa in JSON l'indirizzo dell'ultimo articolo.
 * uso: php prove/asset-modelli-cli.php <cartella CMS> [togli]
 */
[$dir, $azione] = array_pad(array_slice($argv, 1, 2), 2, '');
if ($dir === '') { fwrite(STDERR, "uso: asset-modelli-cli.php <cartella CMS> [togli]\n"); exit(2); }
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/modelli.php';
require_once $dir . '/includes/editor.php';
modelli_installa();

$foto = '';
foreach (glob($dir . '/uploads/*/*/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $f) { $foto = cms_uploads_url() . substr($f, strlen($dir . '/uploads')); break; }
if ($foto === '') $foto = cms_base() . '/sito/assets/favicon.svg';

function riquadro(string $pos, string $foto): array {
    return editor_widget('riquadro_immagine', ['immagine' => $foto, 'alt' => 'Foto di prova', 'titolo' => 'Riquadro ' . $pos, 'testo' => 'Testo accanto alla foto, a ' . $pos . '.', 'posizione_immagine' => $pos, 'immagine_larghezza' => ['d' => ['v' => 50, 'u' => '%']]]);
}

$fine = modelli_fine_crea();
$footer = 0;
foreach (modelli_tutti() as $m) if ($m['tipo'] === 'footer' && $m['predefinito'] && !$m['cestino']) $footer = (int)$m['id'];

if ($azione === 'togli') {
    modelli_stato($fine, false);
} else {
    $alb = editor_valida(['elementi' => [editor_sezione([riquadro('sinistra', $foto)])]]);
    db()->prepare('UPDATE ' . tbl('modelli') . " SET struttura = ?, struttura_bozza = NULL, status = 'published', updated_at = NOW() WHERE id = ?")->execute([modelli_json($alb), $fine]);
    if ($footer) {
        $m = modelli_leggi($footer);
        $a = json_decode((string)($m['struttura'] ?: $m['struttura_bozza']), true) ?: ['elementi' => []];
        $a['elementi'][] = editor_sezione([riquadro('destra', $foto)]);
        db()->prepare('UPDATE ' . tbl('modelli') . " SET struttura = ?, struttura_bozza = NULL, status = 'published', updated_at = NOW() WHERE id = ?")->execute([modelli_json(editor_valida($a)), $footer]);
    }
}
$slug = db()->query('SELECT slug FROM ' . tbl('articles') . " WHERE status = 'published' AND cestino IS NULL ORDER BY publish_date DESC, id DESC LIMIT 1")->fetchColumn();
echo json_encode(['articolo' => $slug ? '/' . $slug . '/' : '', 'fine' => $fine, 'footer' => $footer, 'foto' => $foto]), "\n";
