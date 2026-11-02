<?php
// Le disposizioni dell'Apertura (1.2.6, 2.17.0 ventisei): una pagina /hero-<id>/ per disposizione, con gli stessi testi e
// la stessa foto, e /tutte-le-hero/ con tutte una sotto l'altra. Le disposizioni nuove hanno anche i loro campi
// (righe del terminale, parola, etichette, punto della lente, forma, foto "prima"; il video se si passa VIDEO=<url>).
// Uso: php pagina-hero.php <cartella CMS> <url della foto> [id,id,...]
// Con SUFFISSO=-nf (e la foto vuota) le pagine diventano /hero-<id>-nf/ e /tutte-le-hero-nf/: per le prove senza foto.
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$foto = $argv[2] ?? '';
$suffisso = (string)(getenv('SUFFISSO') ?: '');
$ids = isset($argv[3]) && $argv[3] !== '' ? explode(',', $argv[3]) : array_keys(EDITOR_HERO_DISPOSIZIONI);
$hero = function (string $d) use ($foto) {
    $w = editor_widget('hero');
    $w['impostazioni'] = ['disposizione' => $d, 'occhiello' => 'plumeCMS · PHP e MySQL', 'titolo' => 'Il tuo sito, *leggero come una piuma.*',
        'testo' => 'Un CMS con l\'editor visuale, i temi, la SEO e la newsletter già dentro. Lo carichi, apri il browser, rispondi a cinque domande.',
        'pulsanti' => [['testo' => 'Scarica plumeCMS', 'link' => '/scarica/', 'stile' => 'viola'], ['testo' => 'Guarda cosa sa fare', 'link' => '/funzionalita/', 'stile' => 'filo']],
        'immagine' => $foto, 'etichetta_onda' => 'Leggero', 'barre' => [['etichetta' => 'Meno peso', 'valore' => 80], ['etichetta' => 'Più tempo', 'valore' => 65]],
        'foto_trattamento' => 'naturale'] + $w['impostazioni'];
    // 2.17.0: i campi delle disposizioni nuove (sulle versioni di prima non esistono e editor_valida li toglie).
    $w['impostazioni'] = array_merge($w['impostazioni'], ['terminale_righe' => "\$ carico plumecms.zip\n\u{2713} requisiti a posto\n\u{2713} database collegato\n\$ sito pronto in 5 minuti",
        'parola' => 'Piuma', 'etichette' => [['testo' => 'Trascini qui', 'x' => 22, 'y' => 18], ['testo' => 'Cambi i colori', 'x' => 96, 'y' => 55], ['testo' => 'Pubblichi', 'x' => 45, 'y' => 94]],
        'lente_x' => 52, 'lente_y' => 56, 'forma' => 'piuma', 'video' => (string)(getenv('VIDEO') ?: '')]);
    return ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]];
};
$testo = function () {
    $w = editor_widget('testo'); $w['impostazioni'] = ['contenuto' => '<p>Il resto della pagina comincia qui.</p>'] + $w['impostazioni'];
    return ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]];
};
$pdo = db();
$salva = function (string $slug, string $titolo, array $sezioni) use ($pdo) {
    $st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute([$slug]);
    $id = (int)$st->fetchColumn();
    if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES (?, ?, 'published')")->execute([$titolo, $slug]); $id = (int)$pdo->lastInsertId(); }
    editor_salva($id, editor_valida(['elementi' => $sezioni]), true, 'prova', 'pagina di prova');
    return $id;
};
$tutte = [];
foreach ($ids as $d) { $salva('hero-' . $d . $suffisso, 'Hero ' . $d, [$hero($d), $testo()]); $tutte[] = $hero($d); }
$salva('tutte-le-hero' . $suffisso, 'Tutte le hero', $tutte);
echo implode(' ', $ids) . "\n";
