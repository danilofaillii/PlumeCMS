<?php
/**
 * La tela dell'editor: la pagina vera (intestazione e piede del sito, stesso CSS) dentro l'iframe.
 * Il contenuto arriva dal pannello con postMessage; da qui tornano clic, trascinamenti e misure.
 */
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once dirname(__DIR__) . '/includes/sito-seo.php';
require_once dirname(__DIR__) . '/sito/viste.php';
require_once dirname(__DIR__) . '/includes/editor.php';

$tipo = editor_tipo_valido($_GET['tipo'] ?? '');
$id = (int)($_GET['id'] ?? 0);
$pagina = $id ? editor_pagina($id, $tipo) : null;
if (!$pagina) { http_response_code(404); exit('Pagina non trovata.'); }
header('X-Frame-Options: SAMEORIGIN');
// modelli: la tela mostra l'header e il footer scelti per questo contenuto; per un modello, il segnaposto al posto dell'header o del footer.
$GLOBALS['sito_contenuto_riga'] = $pagina;
if ($tipo === 'modelli') $GLOBALS['modelli_anteprima'] = ['tipo' => (string)($pagina['tipo'] ?? 'header'), 'id' => $id];
$GLOBALS['editor_contesto'] = ['tipo' => $tipo, 'id' => $id, 'titolo' => (string)$pagina['title'], 'percorso' => editor_percorso_pagina($pagina)];

$meta = ['titolo' => 'Anteprima: ' . $pagina['title'], 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []];
$testa = '<style id="ed-css"></style><link rel="stylesheet" href="' . pannello_file('assets/editor/anteprima.css') . '">';
// Un articolo tiene la sua testata (titolo, data) sopra le sezioni, come sul sito.
$corpo = ($tipo !== 'pagine' && $tipo !== 'modelli' ? vista_articolo_testa(['titolo' => $pagina['title'], 'data' => sito_iso((string)($pagina['publish_date'] ?: date('Y-m-d')), (string)$pagina['publish_time']), 'pillar' => ($pagina['type'] ?? '') === 'pillar', 'categorie' => sito_lista($pagina['categorie'] ?? '') ?: array_filter([(string)$pagina['category']])], '') : '')
    . '<div class="ed-pagina" id="ed-pagina"></div>';
// modelli: la tela sta nell'header (o nel footer); al posto del contenuto un segnaposto.
if ($tipo === 'modelli') $corpo = ($pagina['tipo'] ?? '') === 'fine' ? modelli_segnaposto_fine() : modelli_segnaposto_contenuto((string)($pagina['tipo'] ?? 'header'));
// Un articolo fatto di sezioni "Articolo" (la conversione a blocchi) sta nella colonna di lettura, come sul sito.
if ($tipo !== 'pagine' && $tipo !== 'modelli') {
    $albero = editor_decodifica((string)($pagina['struttura_bozza'] ?: $pagina['struttura'])) ?? ['elementi' => []];
    $soloArticolo = $albero['elementi'] && !array_filter($albero['elementi'], fn($s) => editor_valori('sezione', $s['impostazioni'] ?? [])['larghezza'] !== 'articolo');
    if ($soloArticolo) $corpo = substr($corpo, 0, -strlen('</div>' . '<div class="ed-pagina" id="ed-pagina"></div>')) . '<div class="art" style="grid-template-columns:minmax(0,1fr)"><article class="corpo"><div class="ed-pagina ed-articolo-pagina" id="ed-pagina"></div></article></div></div>';
}
$html = vista_pagina($meta, $corpo, $testa, editor_percorso_pagina($pagina));
// Niente link del sito: gli script dell'editor e Sortable vanno in fondo.
$html = str_replace('</body>', '<script src="' . pannello_file('assets/editor/Sortable.min.js') . '"></script><script src="' . pannello_file('assets/editor/anteprima.js') . '"></script></body>', $html);
// Gli script dei widget registrati (schede, fisarmonica, contatori...) servono anche nella tela.
// I widget registrati (esterni) e l'Apertura, l'unico widget del nucleo con fogli suoi (le disposizioni, 1.2.6).
$asset = editor_asset_pagina('ed-w-' . implode(' ed-w-', array_merge(array_keys(editor_widget_registrati()), ['hero'])));
// Al contrario, come le viste del sito (che mettono ogni <link> davanti): stesso ordine dei fogli nell'editor e sul sito.
foreach (array_reverse($asset['css']) as $f) if (is_file(dirname(__DIR__) . '/sito/assets/' . $f)) $html = str_replace('</head>', '<link rel="stylesheet" href="' . h(sito_versione_asset($f)) . '"></head>', $html);
foreach ($asset['js'] as $f) if (is_file(dirname(__DIR__) . '/sito/assets/' . $f)) $html = str_replace('</body>', '<script src="' . h(sito_versione_asset($f)) . '" defer></script></body>', $html);
echo $html;
