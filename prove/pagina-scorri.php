<?php
// Il widget "Scorri di lato" (2.25.0): una pagina /scorri-<disposizione>/ per ognuna delle cinque disposizioni (testo prima,
// il widget con quattro pagine, due con la foto e due senza, testo dopo con un link in fondo), /scorri-due/ con due widget
// nella stessa pagina (pellicola e carte), /scorri-tutte/ con le cinque una sotto l'altra e /scorri-editor/ (solo un testo).
// Uso: php pagina-scorri.php <cartella CMS> [foto.webp foto2.webp ...]
// Le foto si copiano in <uploads>/prova-scorri/ (immagini del sito: il widget non prende immagini esterne).
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$cartella = rtrim($argv[1], '/') . '/uploads/prova-scorri';
@mkdir($cartella, 0755, true);
$foto = [];
foreach (array_slice($argv, 2) as $i => $f) {
    if (!is_file($f)) continue;
    $nome = 'scorri-' . ($i + 1) . '.' . pathinfo($f, PATHINFO_EXTENSION);
    copy($f, $cartella . '/' . $nome);
    $foto[] = cms_uploads_url() . '/prova-scorri/' . $nome;
}
$f = fn($i) => $foto ? $foto[$i % count($foto)] : '';
$pagine = [
    ['occhiello' => '01 · Scrivi', 'titolo' => 'Il tuo sito, *leggero* come una piuma.', 'testo' => 'Apri il pannello e scrivi. Il resto lo fa plumeCMS.', 'data' => '25 ottobre',
        'immagine' => $f(0), 'alt' => 'Il pannello di plumeCMS', 'pulsante' => 'Scarica plumeCMS', 'pulsante_link' => '/scarica/', 'pulsante2' => '', 'pulsante2_link' => ''],
    ['occhiello' => '02 · Trascina', 'titolo' => 'Sezioni, colonne, *widget*.', 'testo' => 'Prendi un blocco e lo lasci dove vuoi. Lo vedi subito come lo vedrà chi visita.', 'data' => '26 ottobre',
        'immagine' => '', 'alt' => '', 'pulsante' => '', 'pulsante_link' => '', 'pulsante2' => 'Guarda l\'editor', 'pulsante2_link' => '/editor/'],
    ['occhiello' => '03 · Vesti', 'titolo' => 'Undici temi, *un clic*.', 'testo' => 'Cambi vestito al sito e i tuoi testi restano dove sono.', 'data' => '30 ottobre',
        'immagine' => $f(1), 'alt' => 'I temi', 'pulsante' => 'Leggi la storia', 'pulsante_link' => 'storia', 'pulsante2' => '', 'pulsante2_link' => ''],
    ['occhiello' => '04 · Pubblica', 'titolo' => 'Premi Pubblica. *Fatto.*', 'testo' => 'Se sbagli, torni indietro: ogni modifica resta nelle versioni.', 'data' => '3 novembre',
        'immagine' => '', 'alt' => '', 'pulsante' => 'Comincia adesso', 'pulsante_link' => '/comincia/', 'pulsante2' => 'Guarda cosa sa fare', 'pulsante2_link' => '/funzionalita/'],
];
$sez = fn(array $w) => ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]];
$scorri = function (string $d) use ($pagine, $sez) {
    $w = editor_widget('scorri_lato', ['disposizione' => $d, 'pagine' => $pagine, 'occhiello' => 'La storia', 'titolo' => 'Da un\'idea a *plumeCMS*.']);
    return $sez($w);
};
$testo = function (string $h, string $id = '') use ($sez) {
    $w = editor_widget('testo', ['contenuto' => '<h2' . ($id ? ' id="' . $id . '"' : '') . '>' . $h . '</h2><p>Il resto della pagina. ' . str_repeat('Una riga di testo per avere una pagina lunga come quelle vere. ', 18) . '</p><p><a href="/contatti/">Scrivici</a></p>']);
    return $sez($w);
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
foreach (array_keys(SDL_DISPOSIZIONI) as $d) {
    $salva('scorri-' . $d, 'Scorri ' . $d, [$testo('Prima del widget'), $scorri($d), $testo('Dopo il widget', 'dopo')]);
    $tutte[] = $scorri($d);
}
$salva('scorri-due', 'Scorri due', [$testo('Prima'), $scorri('pellicola'), $testo('In mezzo', 'mezzo'), $scorri('carte'), $testo('Dopo il widget', 'dopo')]);
// Per la prova dell'editor: una pagina con un testo solo, dove il widget si aggiunge dal pannello.
$salva('scorri-editor', 'Scorri editor', [$testo('Pagina per la prova dell\'editor')]);
$salva('scorri-tutte', 'Scorri tutte', array_merge([$testo('Prima')], $tutte, [$testo('Dopo il widget', 'dopo')]));
echo implode(' ', array_keys(SDL_DISPOSIZIONI)) . ' ' . count($foto) . " foto\n";
