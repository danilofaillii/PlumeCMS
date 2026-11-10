<?php
// Comandi da riga di comando per prova-immagini.js e per il gruppo 52 di tools/sicurezza-prova.js (2.38.0, dati delle immagini).
// Stampa JSON.
// uso: php prove/immagini-cli.php <cartella CMS> <comando> [argomenti]
//   foto <nome> <larghezza> <altezza> [pesante]  una JPEG (o, con "pesante", una PNG di rumore oltre 500 KB) in uploads/pagine/2026/11/; stampa l'indirizzo
//   pagina <slug> <url A> <url B> <url C>        una pagina pubblicata fatta con l'editor: Immagine A senza alt con "Mostra la didascalia
//                                                 della libreria", Immagine A con l'alt "Alt scritto nel widget", Galleria con B (senza alt
//                                                 né didascalia) e C, didascalie sotto e interruttore acceso; immagine in evidenza A
//   pagina-semplice <slug> <url>                 una pagina pubblicata con l'Apertura con quella foto (per il JSON-LD senza dati)
//   articolo <slug> <url>                        un articolo pubblicato con l'immagine in evidenza
//   dati <url>                                   i dati della libreria di un'immagine
//   origine <url> <archivio>                     segna una foto come importata da un archivio (per il filtro "crediti")
//   pulisci                                      toglie i contenuti "img-prova*", i dati della libreria, le foto "imgprova-*" caricate
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
$dir = $argv[1];
require $dir . '/includes/config.php';
require_once $dir . '/includes/sito-seo.php';
require_once $dir . '/includes/contenuti.php';
require_once $dir . '/includes/editor-controlli.php';
require_once $dir . '/includes/editor.php';
require_once $dir . '/includes/media-dati.php';
editor_carica_widget_esterni();
$cmd = $argv[2] ?? '';
$esci = function ($x) { echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n"; exit; };
switch ($cmd) {
    case 'foto':
        $nome = preg_replace('/[^a-z0-9.-]/', '', strtolower((string)($argv[3] ?? 'imgprova.jpg')));
        $w = max(10, (int)($argv[4] ?? 640)); $h = max(10, (int)($argv[5] ?? 420));
        $d = cms_uploads_dir() . '/pagine/2026/11';
        @mkdir($d, 0755, true);
        $im = imagecreatetruecolor($w, $h);
        if (($argv[6] ?? '') === 'pesante') {
            for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) imagesetpixel($im, $x, $y, mt_rand(0, 0xFFFFFF));
            imagepng($im, "$d/$nome", 0);
        } else {
            imagefill($im, 0, 0, imagecolorallocate($im, 30 + strlen($nome) * 7 % 200, 110, 170));
            imagestring($im, 5, 10, 10, $nome, imagecolorallocate($im, 255, 255, 255));
            imagejpeg($im, "$d/$nome", 80);
        }
        @chmod("$d/$nome", 0644);
        $esci(['url' => cms_uploads_url() . '/pagine/2026/11/' . $nome, 'peso' => filesize("$d/$nome")]);
    case 'pagina':
        [$slug, $a, $b, $c] = array_slice($argv, 3, 4);
        db()->prepare('DELETE FROM ' . tbl('pages') . ' WHERE slug = ?')->execute([$slug]);
        $id = contenuti_salva('pagine', 0, ['title' => 'Immagini di prova', 'slug' => $slug, 'stato' => 'pubblicato', 'contenuto' => '', 'immagine' => $a]);
        $w1 = editor_widget('immagine', ['immagine' => $a, 'alt' => '', 'didascalia' => '', 'didascalia_libreria' => true]);
        $w2 = editor_widget('immagine', ['immagine' => $a, 'alt' => 'Alt scritto nel widget', 'didascalia' => '']);
        $w3 = editor_widget('galleria', ['immagini' => [['immagine' => $b, 'alt' => '', 'didascalia' => '', 'link' => ''], ['immagine' => $c, 'alt' => 'Alt di C nel widget', 'didascalia' => 'Didascalia di C nel widget', 'link' => '']], 'didascalia' => 'sotto', 'didascalia_libreria' => true, 'clic' => 'niente']);
        $t = editor_widget('titolo', ['testo' => 'Immagini di prova', 'livello' => 'h1']);
        editor_salva($id, editor_valida(['elementi' => [editor_sezione([$t]), editor_sezione([$w1]), editor_sezione([$w2]), editor_sezione([$w3])]]), true, 'prova');
        $esci(['id' => $id, 'percorso' => '/' . $slug . '/']);
    case 'pagina-semplice':
        [$slug, $a] = array_slice($argv, 3, 2);
        db()->prepare('DELETE FROM ' . tbl('pages') . ' WHERE slug = ?')->execute([$slug]);
        $id = contenuti_salva('pagine', 0, ['title' => 'Pagina con apertura', 'slug' => $slug, 'stato' => 'pubblicato', 'contenuto' => '']);
        $h = editor_widget('hero', ['titolo' => 'Apertura di prova', 'immagine' => $a]);
        editor_salva($id, editor_valida(['elementi' => [editor_sezione([$h])]]), true, 'prova');
        $esci(['id' => $id, 'percorso' => '/' . $slug . '/']);
    case 'articolo':
        [$slug, $a] = array_slice($argv, 3, 2);
        db()->prepare('DELETE FROM ' . tbl('articles') . ' WHERE slug = ?')->execute([$slug]);
        $id = contenuti_salva('articoli', 0, ['title' => 'Articolo con la foto', 'slug' => $slug, 'stato' => 'pubblicato', 'contenuto' => '<p>Testo di prova dell\'articolo con la foto.</p>', 'immagine' => $a, 'immagine_alt' => '']);
        $r = contenuti_leggi('articoli', $id);
        $esci(['id' => $id, 'percorso' => contenuti_percorso('articoli', $r)]);
    case 'dati':
        $esci(media_dati_di((string)($argv[3] ?? ''), true));
    case 'origine':
        media_alt_schema();
        $k = media_alt_chiave((string)($argv[3] ?? ''));
        db()->prepare('INSERT INTO ' . tbl('media_alt') . " (percorso, origine, aggiornato) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE origine = VALUES(origine)")->execute([$k, (string)($argv[4] ?? 'openverse')]);
        $esci(['ok' => true, 'chiave' => $k]);
    case 'pulisci':
        foreach (['pages', 'articles'] as $t) db()->exec('DELETE FROM ' . tbl($t) . " WHERE slug LIKE 'img-prova%'");
        try { media_alt_schema(); db()->exec('DELETE FROM ' . tbl('media_alt')); } catch (Throwable $e) {}
        save_setting('media_alt_n', '0'); save_setting('media_dati_n', '0');
        $n = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(cms_uploads_dir(), FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && preg_match('/^(imgprova|vetrina-di-sera|negozio-prova|foto-non-usata|img-20261108)/i', $f->getFilename())) { @unlink($f->getPathname()); $n++; }
        }
        $esci(['ok' => true, 'file_tolti' => $n]);
}
$esci(['errore' => 'comando sconosciuto']);
