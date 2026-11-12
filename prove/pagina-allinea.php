<?php
/**
 * 2.21.0: le pagine di prova dell'allineamento delle parti dei widget (per prove/prova-allinea.js). Un widget per sezione, tutti
 * i tipi, i testi vuoti riempiti, e:
 *   php prove/pagina-allinea.php <cartella CMS> parti <left|center|right|justify>  -> /allinea-<valore>/: ogni parte allineata così
 *   php prove/pagina-allinea.php <cartella CMS> tutto <left|center|right>          -> /allinea-tutto-<valore>/: l'allineamento di
 *                                                                                    tutto il widget, le parti come il widget
 * Stampa in JSON, per ogni widget, l'id e le parti (nome del controllo, selettore) che la prova misura.
 */
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
[$modo, $val] = [$argv[2] ?? 'parti', $argv[3] ?? 'right'];
$defs = editor_definizioni();
$foto = array_map(fn($f) => cms_uploads_url() . '/' . basename(dirname($f)) . '/' . basename($f), glob(cms_uploads_dir() . '/kit/*.svg') ?: []);
$sezioni = []; $mappa = [];
foreach ($defs as $tipo => $d) {
    if (in_array($tipo, ['sezione', 'colonna'], true)) continue;
    $w = editor_widget($tipo);
    $imp = [];
    // I testi vuoti si riempiono: una parte senza testo non si vede e non si misura.
    foreach ($d['controlli'] as $c) {
        if (($c['scheda'] ?? 'contenuto') !== 'contenuto' || !in_array($c['tipo'], ['testo', 'testolungo'], true)) continue;
        if (preg_match('/url|link|email|codice|ancora|indirizzo|telefono|whatsapp|slug|chiave|id$/i', $c['nome'])) continue;
        if ((string)($c['predefinito'] ?? '') === '') $imp[$c['nome']] = 'Una prova di allineamento';
    }
    if ($tipo === 'immagine' && $foto) $imp += ['immagine' => $foto[0], 'didascalia' => 'Una didascalia di prova'];
    $parti = [];
    foreach ($d['controlli'] as $c) {
        if (($c['tipo'] ?? '') !== 'allineamento' && ($c['proprieta'] ?? '') !== 'text-align') continue;
        if ($modo === 'parti' && !empty($c['parte']) && $c['parte'] !== 'contenuto') { $imp[$c['nome']] = ['d' => $val]; $parti[] = [$c['nome'], trim((string)$c['selettore'])]; }
        if ($modo === 'tutto' && (($c['parte'] ?? '') === 'contenuto' || ($c['nome'] === 'allineamento' && empty($c['parte'])))) {
            $opz = editor_opzioni_allineamento($c);
            $imp[$c['nome']] = isset($opz[$val]) ? ['d' => $val] : ['d' => array_search(['left' => 'Sinistra', 'center' => 'Centro', 'right' => 'Destra'][$val], $opz, true) ?: $val];
            $parti[] = [$c['nome'], ''];
        }
    }
    if (!$parti) continue;
    $w['impostazioni'] = array_replace($w['impostazioni'] ?? [], $imp);
    $mappa[] = ['tipo' => $tipo, 'id' => $w['id'], 'parti' => $parti,
        'tutte' => array_values(array_map(fn($c) => trim((string)$c['selettore']), array_filter($d['controlli'], fn($c) => !empty($c['parte']) && $c['parte'] !== 'contenuto')))];
    $sezioni[] = ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]];
}
$slug = $modo === 'parti' ? "allinea-$val" : "allinea-tutto-$val";
$pdo = db();
$st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute([$slug]);
$id = (int)$st->fetchColumn();
if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES (?, ?, 'published')")->execute(['Allineamento ' . $val, $slug]); $id = (int)$pdo->lastInsertId(); }
$albero = editor_valida(['elementi' => $sezioni]);
editor_salva($id, $albero, true, 'prova', 'pagina di prova');
// Gli id dopo la validazione (restano gli stessi) e le impostazioni come le ha tenute il validatore.
echo json_encode(['slug' => $slug, 'widget' => $mappa], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
