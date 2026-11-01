<?php
// Le pagine di prova delle quindici infografiche disegnate, una per sezione (più di sei colonne in una sezione spariscono).
// uso: php prove/pagina-infografiche-15.php <cartella CMS> normali  -> /infografiche-15/: le quindici con i valori di partenza
//      php prove/pagina-infografiche-15.php <cartella CMS> estremi  -> /infografiche-15-estremi/: minimi, massimi, testi lunghi,
//                                                                       zero voci, totali sbagliati, colori liberi
//      php prove/pagina-infografiche-15.php <cartella CMS> cattivi  -> /infografiche-15-cattivi/: HTML e script in ogni testo,
//                                                                       colori e numeri manipolati, troppe voci
//      php prove/pagina-infografiche-15.php <cartella CMS> notte    -> /infografiche-15-notte/: le quindici con il tema Notte (corpo scuro)
//      php prove/pagina-infografiche-15.php <cartella CMS> vuota    -> /infografiche-15-editor/ vuota (stampa {"id": N}); togli la cancella
// Tutto passa da editor_valida(), come un salvataggio dall'editor.
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$modo = $argv[2] ?? 'normali';
$w = fn(string $t, array $imp = []) => ['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [array_replace(editor_widget($t), ['impostazioni' => $imp])]];
$sez = fn(array $col) => ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => $col];
$tipi = IGS_TIPI;

if ($modo === 'vuota' || $modo === 'togli') {
    // La pagina vuota per la prova dall'editor (si crea qui: aprire editor.php?nuova=1 lascerebbe una bozza vera), e la pulizia.
    $pdo = db();
    $pdo->prepare('DELETE FROM ' . tbl('pages') . ' WHERE slug = ?')->execute(['infografiche-15-editor']);
    if ($modo === 'togli') { echo "tolta\n"; exit; }
    $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES ('Infografiche 15 editor', 'infografiche-15-editor', 'published')")->execute();
    echo json_encode(['id' => (int)$pdo->lastInsertId()]) . "\n";
    exit;
}
if ($modo === 'notte') {
    // Le quindici con il tema "Notte" delle infografiche: corpo scuro come il sito, scritte chiare.
    require_once $argv[1] . '/includes/editor-temi.php';
    $notte = editor_temi_preimpostati()['notte']['valori'] + ['tema' => 'notte'];
    unset($notte['colore']);
    $slug = 'infografiche-15-notte'; $titolo = 'Infografiche 15 notte';
    $sezioni = array_map(fn($t) => $sez([$w($t, $notte)]), $tipi);
} elseif ($modo === 'normali') {
    $slug = 'infografiche-15'; $titolo = 'Infografiche 15';
    $sezioni = array_map(fn($t) => $sez([$w($t)]), $tipi);
} elseif ($modo === 'estremi') {
    $slug = 'infografiche-15-estremi'; $titolo = 'Infografiche 15 estremi';
    $lungo = 'Un testo molto lungo che non finisce mai, scritto apposta per vedere se va a capo e si taglia con i puntini senza uscire dal disegno né sovrapporsi alle altre scritte vicine';
    $parola = 'Precipitevolissimevolmenteprecipitevolissimevolmente';
    $sei = fn(string $k, array $extra = []) => array_map(fn($i) => [$k => ($i % 2 ? $lungo : $parola . ' ' . $i)] + $extra, range(1, 6));
    $sezioni = [
        // minimi e massimi
        $sez([$w('ig_imbuto', ['gradini' => [['nome' => $lungo, 'valore' => 1000000000], ['nome' => $parola, 'valore' => 0], ['nome' => 'Zero', 'valore' => 0], ['nome' => 'Quattro', 'valore' => 7], ['nome' => 'Cinque', 'valore' => 3], ['nome' => 'Sei', 'valore' => 1], ['nome' => 'Sette (fuori)', 'valore' => 1]]])]),
        $sez([$w('ig_imbuto', ['gradini' => []])]),
        $sez([$w('ig_piramide', ['livelli' => array_map(fn($x) => ['nome' => $x['nome'], 'testo' => $x['testo']], $sei('nome', ['testo' => $lungo]))])]),
        $sez([$w('ig_piramide', ['livelli' => [['nome' => 'Uno solo', 'testo' => '']]])]),
        $sez([$w('ig_matrice', ['asse_x_a' => $lungo, 'asse_x_b' => $parola, 'asse_y_a' => $lungo, 'asse_y_b' => $parola, 'quadranti' => [['titolo' => $lungo, 'testo' => $lungo . "\n" . $lungo . "\n" . $lungo], ['titolo' => $parola, 'testo' => $parola], ['titolo' => '', 'testo' => ''], ['titolo' => 'Quattro', 'testo' => "uno\ndue\ntre\nquattro\ncinque\nsei\nsette"], ['titolo' => 'Quinto (fuori)']]])]),
        $sez([$w('ig_matrice', ['quadranti' => [['titolo' => 'Solo uno', 'testo' => 'Gli altri vuoti']]])]),
        $sez([$w('ig_ciclo', ['tappe' => array_map(fn($x) => ['nome' => $x['nome']], $sei('nome')), 'centro_sopra' => $lungo, 'centro' => $parola])]),
        $sez([$w('ig_ciclo', ['tappe' => [['nome' => 'Sola']]])]),
        $sez([$w('ig_ciambella', ['voci' => array_map(fn($i) => ['nome' => $i % 2 ? $lungo : $parola, 'percentuale' => 30], range(1, 7))])]),
        $sez([$w('ig_ciambella', ['voci' => [['nome' => 'Tutto', 'percentuale' => 100]], 'centro_testo' => $lungo])]),
        $sez([$w('ig_ciambella', ['voci' => [['nome' => 'Zero', 'percentuale' => 0], ['nome' => 'Zero bis', 'percentuale' => 0]]])]),
        $sez([$w('ig_venn', ['cerchi' => [['sopra' => $lungo, 'nome' => $parola], ['sopra' => $parola, 'nome' => $lungo], ['sopra' => $lungo, 'nome' => $lungo], ['nome' => 'Quarto (fuori)']], 'centro_sopra' => $parola, 'centro' => $lungo])]),
        $sez([$w('ig_venn', ['cerchi' => [['sopra' => 'Uno', 'nome' => 'A'], ['sopra' => 'Due', 'nome' => 'B']]])]),
        $sez([$w('ig_radar', ['aspetti' => array_map(fn($i) => ['nome' => $i % 2 ? $lungo : $parola, 'prima' => $i % 2 ? 0 : 10, 'dopo' => $i % 2 ? 10 : 0], range(1, 9))])]),
        $sez([$w('ig_radar', ['aspetti' => [['nome' => 'Uno', 'prima' => 1, 'dopo' => 2], ['nome' => 'Due', 'prima' => 1, 'dopo' => 2]]])]),
        $sez([$w('ig_lancetta', ['valore' => 1000000, 'minimo' => 0, 'massimo' => 0, 'soglia_1' => 50, 'soglia_2' => -50, 'decimali' => '2', 'suffisso' => '%', 'testo' => $lungo])]),
        $sez([$w('ig_lancetta', ['valore' => -3, 'minimo' => -10, 'massimo' => 10, 'soglia_1' => -10, 'soglia_2' => 10, 'verso' => 'basso', 'decimali' => '0', 'suffisso' => '€'])]),
        $sez([$w('ig_iceberg', ['sopra_titolo' => $lungo, 'sopra_testo' => $parola, 'voci' => array_map(fn($x) => ['testo' => $x['testo']], $sei('testo')), 'etichetta_sopra' => $lungo, 'etichetta_sotto' => $parola])]),
        $sez([$w('ig_iceberg', ['voci' => []])]),
        $sez([$w('ig_omini', ['quanti' => 20, 'totale' => 20, 'frase' => $lungo, 'simbolo' => 'stella'])]),
        $sez([$w('ig_omini', ['quanti' => 15, 'totale' => 3, 'frase' => '', 'simbolo' => 'cuore'])]),
        $sez([$w('ig_omini', ['quanti' => 0, 'totale' => 1, 'simbolo' => 'casa'])]),
        $sez([$w('ig_viaggio', ['tappe' => array_map(fn($i) => ['nome' => $i % 2 ? $lungo : $parola], range(1, 8))])]),
        $sez([$w('ig_viaggio', ['tappe' => [['nome' => 'Partenza'], ['nome' => 'Arrivo']], 'bandiera' => false])]),
        $sez([$w('ig_bilancia', ['pro' => array_map(fn($i) => ['testo' => $i % 2 ? $lungo : $parola], range(1, 9)), 'contro' => [], 'etichetta_pro' => $lungo])]),
        $sez([$w('ig_bilancia', ['pro' => [], 'contro' => [['testo' => 'Solo contro']], 'inclinazione' => 'pari'])]),
        $sez([$w('ig_calore', ['colonne' => implode(',', array_map(fn($i) => 'Colonna lunga ' . $i, range(1, 14))), 'righe' => array_map(fn($i) => ['nome' => $lungo, 'valori' => '4 0 9 -3 2 1 3 4 0 1 2 3 4 4 4'], range(1, 13))])]),
        $sez([$w('ig_calore', ['colonne' => '', 'righe' => [['nome' => 'Solo', 'valori' => '1 2']]])]),
        $sez([$w('ig_cerchi', ['cerchi' => array_map(fn($i) => ['nome' => $i % 2 ? $lungo : $parola, 'testo' => $lungo], range(1, 5))])]),
        $sez([$w('ig_cerchi', ['cerchi' => [['nome' => 'Uno', 'testo' => '']]])]),
        $sez([$w('ig_barre100', ['voci' => array_map(fn($i) => ['nome' => $i % 2 ? $lungo : $parola], range(1, 7)), 'barre' => array_map(fn($i) => ['nome' => $lungo, 'valori' => '1 2 3 4 5 6 7 8'], range(1, 5))])]),
        $sez([$w('ig_barre100', ['voci' => [['nome' => 'Una', 'colore' => '', 'colore_libero' => '#ffee00'], ['nome' => 'Due', 'colore_libero' => '#101010']], 'barre' => [['nome' => 'Vuota', 'valori' => '0 0'], ['nome' => 'Piena', 'valori' => '50 50']]])]),
    ];
} else {
    $slug = 'infografiche-15-cattivi'; $titolo = 'Infografiche 15 cattivi';
    $x = '"><svg onload=alert(1)><script>alert(2)</script></text><text>&lt;b&gt;';
    $c = 'red;}</style><script>alert(3)</script>';
    $voci = fn(array $campi, int $n = 45) => array_map(fn($i) => $campi, range(1, $n));
    $sezioni = [
        $sez([$w('ig_imbuto', ['gradini' => $voci(['nome' => $x, 'valore' => '1e99', 'colore' => $c, 'colore_libero' => $c]), 'etichetta_resa' => $x, 'colore' => $c, 'colore_libero' => 'url(javascript:alert(1))'])]),
        $sez([$w('ig_piramide', ['livelli' => $voci(['nome' => $x, 'testo' => $x, 'colore' => 'rosso" onmouseover="alert(1)', 'colore_libero' => '#12345g'])])]),
        $sez([$w('ig_matrice', ['asse_x_a' => $x, 'asse_x_b' => $x, 'asse_y_a' => $x, 'asse_y_b' => $x, 'quadranti' => $voci(['titolo' => $x, 'testo' => $x, 'colore' => $c])])]),
        $sez([$w('ig_ciclo', ['tappe' => $voci(['nome' => $x, 'colore_libero' => 'expression(alert(1))']), 'centro_sopra' => $x, 'centro' => $x])]),
        $sez([$w('ig_ciambella', ['voci' => $voci(['nome' => $x, 'percentuale' => 'NaN']), 'centro' => $x, 'centro_testo' => $x])]),
        $sez([$w('ig_venn', ['cerchi' => $voci(['sopra' => $x, 'nome' => $x, 'colore' => $c]), 'centro_sopra' => $x, 'centro' => $x])]),
        $sez([$w('ig_radar', ['aspetti' => $voci(['nome' => $x, 'prima' => -1e9, 'dopo' => 1e9]), 'etichetta_prima' => $x, 'etichetta_dopo' => $x, 'colore_dopo' => $c, 'colore_dopo_libero' => $c])]),
        $sez([$w('ig_lancetta', ['valore' => 'INF', 'minimo' => 1e99, 'massimo' => -1e99, 'soglia_1' => 'x', 'soglia_2' => [1], 'decimali' => '9', 'verso' => $x, 'suffisso' => $x, 'testo' => $x])]),
        $sez([$w('ig_iceberg', ['sopra_titolo' => $x, 'sopra_testo' => $x, 'voci' => $voci(['testo' => $x]), 'etichetta_sopra' => $x, 'etichetta_sotto' => $x, 'colore' => $c, 'colore_libero' => $c])]),
        $sez([$w('ig_omini', ['quanti' => 1e9, 'totale' => -5, 'frase' => $x, 'simbolo' => '"><script>alert(1)</script>', 'colore' => $c])]),
        $sez([$w('ig_viaggio', ['tappe' => $voci(['nome' => $x, 'colore' => $c]), 'bandiera' => $x])]),
        $sez([$w('ig_bilancia', ['pro' => $voci(['testo' => $x]), 'contro' => $voci(['testo' => $x]), 'etichetta_pro' => $x, 'etichetta_contro' => $x, 'inclinazione' => $x, 'colore_pro' => $c, 'colore_contro' => $c])]),
        $sez([$w('ig_calore', ['colonne' => str_repeat($x . ',', 60), 'righe' => $voci(['nome' => $x, 'valori' => str_repeat('99999 ', 300)]), 'etichetta_poco' => $x, 'etichetta_tanto' => $x, 'colore' => $c])]),
        $sez([$w('ig_cerchi', ['cerchi' => $voci(['nome' => $x, 'testo' => $x]), 'colore' => $c, 'colore_libero' => $c])]),
        $sez([$w('ig_barre100', ['voci' => $voci(['nome' => $x, 'colore' => $c, 'colore_libero' => $c]), 'barre' => $voci(['nome' => $x, 'valori' => str_repeat('-5 1e9 ', 100)])])]),
    ];
}
$pdo = db();
$st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute([$slug]);
$id = (int)$st->fetchColumn();
if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES (?, ?, 'published')")->execute([$titolo, $slug]); $id = (int)$pdo->lastInsertId(); }
$albero = editor_valida(['elementi' => $sezioni]);
editor_salva($id, $albero, true, 'prova', 'pagina di prova');
echo "pagina $id /$slug/, sezioni: " . count($albero['elementi']) . "\n";
