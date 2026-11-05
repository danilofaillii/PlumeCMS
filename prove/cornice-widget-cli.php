<?php
// Aiuti da riga di comando per prova-cornice-widget.js (2.26.1, risposte in JSON).
// uso: php prove/cornice-widget-cli.php <cartella CMS> <comando>
//   prepara   un footer "Footer con contatti" (Icone social e Barra contatti), pubblicato e predefinito al posto di quello
//             di prima; la pagina /senza-widget-extra/ (titolo, testo, passi, immagine, pulsanti: nessun widget
//             extra, come la pagina Degustazioni di Cucina Creativa) e la pagina
//             /anche-nel-corpo/ con le Icone social anche nel corpo (per vedere che widget.css esce una volta sola)
//   togli     toglie footer e pagine della prova e rimette predefinito il footer di prima
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
require_once $argv[1] . '/includes/modelli.php';
editor_carica_widget_esterni();
const CW_NOME = 'Footer con contatti (prova cornice)';
define('CW_PRIMA', sys_get_temp_dir() . '/prova-cornice-' . md5(realpath($argv[1])) . '.txt'); // il footer predefinito di prima
const CW_PAGINE = ['senza-widget-extra' => 'Senza widget extra', 'anche-nel-corpo' => 'Anche nel corpo'];
$pdo = db();

function cw_togli(PDO $pdo): array {
    $tolti = [];
    $nostri = array_map('intval', array_keys(array_filter(modelli_tutti(), fn($m) => $m['nome'] === CW_NOME)));
    // Prima di nuovo predefinito il footer di prima (il predefinito non si elimina), poi via quello della prova.
    $prima = is_file(CW_PRIMA) ? (int)file_get_contents(CW_PRIMA) : 0;
    if ($nostri) {
        $pdo->prepare('UPDATE ' . tbl('modelli') . " SET predefinito = 0 WHERE tipo = 'footer'")->execute();
        if ($prima && modelli_leggi($prima)) $pdo->prepare('UPDATE ' . tbl('modelli') . ' SET predefinito = 1 WHERE id = ?')->execute([$prima]);
    }
    foreach ($nostri as $id) { modelli_elimina($id); $tolti[] = $id; }
    if (is_file(CW_PRIMA)) unlink(CW_PRIMA);
    foreach (array_keys(CW_PAGINE) as $slug) {
        $st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute([$slug]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) { $pdo->prepare('DELETE FROM ' . tbl('pages') . ' WHERE id = ?')->execute([(int)$id]); $tolti[] = 'pagina ' . $id; }
    }
    return $tolti;
}

switch ($argv[2] ?? '') {
    case 'prepara':
        cw_togli($pdo);
        $prima = 0;
        foreach (modelli_tutti() as $m) if ($m['tipo'] === 'footer' && $m['predefinito']) $prima = (int)$m['id'];
        file_put_contents(CW_PRIMA, (string)$prima);
        $social = editor_widget('social', []);
        $barra = editor_widget('barra_contatti', ['telefono' => '+39 051 123456', 'whatsapp' => '393331234567', 'email' => 'ciao@esempio.it']);
        $albero = editor_valida(['elementi' => [editor_sezione([
            editor_widget('logo_sito', []), editor_widget('descrizione_sito', []), $social, $barra, editor_widget('copyright', []),
        ], ['larghezza' => 'contenuta', 'spazio_sopra' => ['d' => 56, 'm' => 56], 'spazio_sotto' => ['d' => 40, 'm' => 40]])]]);
        $id = modelli_crea('footer', CW_NOME, $albero);
        $pdo->prepare('UPDATE ' . tbl('modelli') . " SET status = 'published', struttura = struttura_bozza, struttura_bozza = NULL WHERE id = ?")->execute([$id]);
        modelli_predefinito($id);
        $pagine = [];
        foreach (CW_PAGINE as $slug => $titolo) {
            $w = [editor_widget('titolo', ['testo' => $titolo, 'livello' => 'h1']), editor_widget('testo', ['contenuto' => '<p>Una pagina di prova con il footer che ha le icone social e la barra dei contatti.</p>']), editor_widget('passi', []), editor_widget('immagine', []), editor_widget('pulsanti', [])];
            if ($slug === 'anche-nel-corpo') $w[] = editor_widget('social', []);
            $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES (?, ?, 'published')")->execute([$titolo, $slug]);
            $pid = (int)$pdo->lastInsertId();
            editor_salva($pid, editor_valida(['elementi' => [editor_sezione($w)]]), true, 'prova', 'prova cornice');
            $pagine[] = '/' . $slug . '/';
        }
        echo json_encode(['footer' => $id, 'prima' => $prima, 'pagine' => $pagine]);
        break;
    case 'togli':
        echo json_encode(['tolti' => cw_togli($pdo)]);
        break;
    default:
        fwrite(STDERR, "comando sconosciuto\n");
        exit(1);
}
