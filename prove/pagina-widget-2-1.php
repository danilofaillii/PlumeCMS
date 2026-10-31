<?php
// Le pagine di prova dei widget della 2.1 (per prova-widget-2-1.js e attacchi-widget-2-1.js), un widget per sezione.
// uso: php prove/pagina-widget-2-1.php <cartella CMS> vecchi   -> /widget-vecchi/: i widget di prima (mappa, video, tabella,
//                                                                 ultimi articoli, griglia, galleria, modulo) con i valori di
//                                                                 una pagina vera, SENZA le opzioni nuove (foto prima e dopo)
//      php prove/pagina-widget-2-1.php <cartella CMS> nuovi    -> /widget-2-1/: i cinque widget nuovi e le opzioni nuove
//      php prove/pagina-widget-2-1.php <cartella CMS> cattivi  -> /widget-2-1-cattivi/: testo cattivo in ogni campo
// Le foto: i disegni del kit di partenza (uploads/kit/).
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
require_once $argv[1] . '/includes/form.php';
editor_carica_widget_esterni();
$modo = $argv[2] ?? 'nuovi';
$foto = array_map(fn($f) => cms_uploads_url() . '/' . basename(dirname($f)) . '/' . basename($f), array_merge(glob(cms_uploads_dir() . '/kit/*.svg') ?: [], glob(cms_uploads_dir() . '/segnaposto/*.svg') ?: []));
$f = fn(int $i) => $foto[$i % max(1, count($foto))] ?? '';
$w = fn(string $t, array $imp) => ['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [array_replace(editor_widget($t), ['impostazioni' => $imp])]];
$sez = fn(array $col) => ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => $col];

// Il form del Contatti (dal kit) per il widget Modulo.
$form = db()->query('SELECT id FROM ' . tbl('moduli') . ' ORDER BY id LIMIT 1')->fetchColumn();
$cat = (string)(db()->query('SELECT slug FROM ' . tbl('categories') . ' ORDER BY id LIMIT 1')->fetchColumn() ?: '');

if ($modo === 'vecchi') {
    $slug = 'widget-vecchi'; $titolo = 'Widget vecchi';
    $sezioni = [
        $sez([$w('mappa', ['indirizzo' => 'Piazza Maggiore, Bologna'])]),
        $sez([$w('mappa', ['fornitore' => 'osm', 'al_clic' => true, 'testo_pulsante' => 'Apri la mappa'])]),
        $sez([$w('video', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'titolo' => 'Il nostro laboratorio'])]),
        $sez([$w('video', ['url' => 'https://vimeo.com/76979871', 'titolo' => 'Su Vimeo', 'copertina' => $f(1)])]),
        $sez([$w('tabella', ['righe' => [['celle' => 'Servizio | Durata | Prezzo'], ['celle' => 'Taglio | 30 minuti | 25 €'], ['celle' => 'Colore * piega | 90 minuti | 5 * 12 €'], ['celle' => 'Visita [gratis] | (10 minuti) | 0 €']]])]),
        $sez([$w('articoli', ['numero' => 3, 'solo_pillar' => false])]),
        $sez([$w('articoli', ['numero' => 2, 'solo_pillar' => false, 'categoria' => $cat])]),
        $sez([$w('griglia_contenuti', ['quanti' => 3, 'categoria' => $cat])]),
        $sez([$w('galleria', ['immagini' => [['immagine' => $f(0), 'alt' => 'Una', 'didascalia' => 'Prima', 'link' => ''], ['immagine' => $f(1), 'alt' => 'Due', 'didascalia' => 'Seconda', 'link' => ''], ['immagine' => $f(2), 'alt' => 'Tre', 'didascalia' => '', 'link' => '']], 'colonne' => ['d' => 3]])]),
        $sez([$w('modulo', ['tipo' => $form ? 'form:' . $form : 'newsletter'])]),
    ];
} elseif ($modo === 'cattivi') {
    $slug = 'widget-2-1-cattivi'; $titolo = 'Widget 2.1 cattivi';
    $x = '"><img src=x onerror=alert(1)><script>alert(2)</script>';
    $js = 'javascript:alert(1)';
    $sezioni = [
        $sez([$w('menu_ristorante', ['titolo' => $x, 'sezioni' => [['nome' => $x, 'nota' => $x, 'giorni' => $x]], 'piatti' => [['sezione' => $x, 'nome' => $x, 'descrizione' => $x, 'prezzo' => $x, 'immagine' => $js, 'allergeni' => $x, 'etichette' => $x]]])]),
        $sez([$w('richiesta_prenotazione', ['titolo' => $x, 'intro' => $x, 'pulsante' => $x, 'consenso' => $x, 'modalita' => $x, 'email_a' => $x])]),
        $sez([$w('galleria_filtri', ['tutti' => $x, 'immagini' => [['immagine' => $js, 'alt' => $x, 'didascalia' => $x, 'categorie' => $x], ['immagine' => $f(0), 'alt' => $x, 'didascalia' => $x, 'categorie' => $x . ', ' . $x]]])]),
        $sez([$w('scorrevole', ['tipo' => 'testo', 'frase' => $x, 'loghi' => [['immagine' => $js, 'alt' => $x, 'link' => $js]]])]),
        $sez([$w('scorrevole', ['tipo' => 'loghi', 'loghi' => [['immagine' => $f(0), 'alt' => $x, 'link' => $js]]])]),
        $sez([$w('contenuto_esterno', ['indirizzo' => 'https://cattivo.example/embed', 'servizio' => 'calendly'])]),
        $sez([$w('contenuto_esterno', ['indirizzo' => 'https://calendly.com.cattivo.example/x', 'servizio' => 'calendly'])]),
        $sez([$w('contenuto_esterno', ['indirizzo' => 'javascript:alert(1)//calendly.com/x', 'servizio' => 'calendly'])]),
        $sez([$w('contenuto_esterno', ['indirizzo' => '<iframe src="https://cattivo.example"></iframe>', 'servizio' => 'spotify'])]),
        $sez([$w('tabella', ['righe' => [['celle' => '[clic](javascript:alert(1)) | [dati](data:text/html,<script>alert(1)</script>) | ' . $x], ['celle' => '**<b onclick=alert(1)>x</b>** | [a](" onmouseover="alert(1)) | *<i>y</i>*']]])]),
        $sez([$w('mappa', ['indirizzo' => $x, 'come_arrivare' => 'sotto', 'come_arrivare_testo' => $x])]),
        $sez([$w('video', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ"onload="alert(1)', 'titolo' => $x, 'da_solo' => true, 'ripeti' => true])]),
    ];
} else {
    $slug = 'widget-2-1'; $titolo = 'Widget 2.1';
    $oggiG = ['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'][(int)date('N') - 1];
    $altroG = ['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'][((int)date('N')) % 7];
    $sezioni = [
        $sez([$w('menu_ristorante', [
            'titolo' => 'Il nostro menu',
            'sezioni' => [
                ['nome' => 'Antipasti', 'nota' => '', 'giorni' => ''],
                ['nome' => 'Primi', 'nota' => 'Pasta fatta a mano ogni mattina', 'giorni' => ''],
                ['nome' => 'Menu del giorno', 'nota' => 'Solo a pranzo', 'giorni' => $oggiG],
                ['nome' => 'Menu della domenica', 'nota' => '', 'giorni' => $altroG === $oggiG ? 'xx' : $altroG],
            ],
            'piatti' => [
                ['sezione' => 'Antipasti', 'nome' => 'Tagliere di salumi', 'descrizione' => 'Mortadella, prosciutto, crescentine', 'prezzo' => '14', 'immagine' => $f(0), 'allergeni' => 'glutine, latte', 'etichette' => ''],
                ['sezione' => 'Antipasti', 'nome' => 'Verdure grigliate', 'descrizione' => 'Con olio del Garda', 'prezzo' => '9,50', 'immagine' => '', 'allergeni' => '', 'etichette' => 'vegano, senza_glutine'],
                ['sezione' => 'Primi', 'nome' => 'Tagliatelle al ragù', 'descrizione' => 'La ricetta depositata', 'prezzo' => '13 €', 'immagine' => '', 'allergeni' => 'glutine, uova, sedano', 'etichette' => 'novita'],
                ['sezione' => 'Primi', 'nome' => 'Penne all\'arrabbiata', 'descrizione' => '', 'prezzo' => '10', 'immagine' => '', 'allergeni' => 'glutine', 'etichette' => 'piccante, vegetariano'],
                ['sezione' => 'Menu del giorno', 'nome' => 'Piatto del giorno', 'descrizione' => 'Chiedi al personale', 'prezzo' => '12', 'immagine' => '', 'allergeni' => 'pesce, molluschi', 'etichette' => ''],
                ['sezione' => 'Menu della domenica', 'nome' => 'Lasagne della domenica', 'descrizione' => '', 'prezzo' => '15', 'immagine' => '', 'allergeni' => 'glutine', 'etichette' => ''],
            ],
        ])]),
        $sez([$w('richiesta_prenotazione', ['modalita' => 'tavolo', 'titolo' => 'Prenota un tavolo', 'email_a' => 'ristorante@esempio.it'])]),
        $sez([$w('richiesta_prenotazione', ['modalita' => 'alloggio', 'titolo' => 'Chiedi la disponibilità', 'email_a' => 'bnb@esempio.it'])]),
        $sez([$w('galleria_filtri', ['immagini' => [
            ['immagine' => $f(0), 'alt' => 'Sposi in giardino', 'didascalia' => 'Matrimonio in collina', 'categorie' => 'Matrimoni'],
            ['immagine' => $f(1), 'alt' => 'Ritratto di Anna', 'didascalia' => 'Ritratto', 'categorie' => 'Ritratti'],
            ['immagine' => $f(2), 'alt' => 'Il taglio della torta', 'didascalia' => 'La torta', 'categorie' => 'Matrimoni, Eventi'],
            ['immagine' => $f(3), 'alt' => 'Ritratto di Luca', 'didascalia' => '', 'categorie' => 'Ritratti'],
            ['immagine' => $f(4), 'alt' => 'Festa aziendale', 'didascalia' => 'Festa', 'categorie' => 'Eventi'],
        ]])]),
        $sez([$w('scorrevole', ['tipo' => 'loghi', 'grigio' => true, 'loghi' => [
            ['immagine' => $f(0), 'alt' => 'Cliente uno', 'link' => 'https://esempio.it'], ['immagine' => $f(1), 'alt' => 'Cliente due', 'link' => ''],
            ['immagine' => $f(2), 'alt' => 'Cliente tre', 'link' => ''], ['immagine' => $f(3), 'alt' => 'Cliente quattro', 'link' => '']]])]),
        $sez([$w('scorrevole', ['tipo' => 'testo', 'frase' => 'Aperti anche la domenica · Prenota online · Consegna a domicilio', 'direzione' => 'destra'])]),
        $sez([$w('contenuto_esterno', ['indirizzo' => 'https://calendly.com/esempio/30min'])]),
        $sez([$w('contenuto_esterno', ['indirizzo' => 'https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M'])]),
        $sez([$w('mappa', ['indirizzo' => 'Piazza Maggiore, Bologna', 'come_arrivare' => 'sotto', 'come_arrivare_app' => 'google'])]),
        $sez([$w('mappa', ['indirizzo' => 'Via Rizzoli 1, Bologna', 'come_arrivare' => 'sopra', 'come_arrivare_app' => 'apple', 'come_arrivare_testo' => 'Portami lì'])]),
        $sez([$w('video', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'titolo' => 'Da solo', 'da_solo' => true, 'ripeti' => true])]),
        $sez([$w('video', ['url' => 'https://vimeo.com/76979871', 'titolo' => 'Si ripete', 'ripeti' => true])]),
        $sez([$w('tabella', ['righe' => [['celle' => 'Servizio | Prezzo | Note'], ['celle' => '**Taglio** | 25 € | *su prenotazione*'], ['celle' => 'Colore | 40 € | [Vedi i colori](/contatti/)'], ['celle' => 'Visita | gratis | [sito](www.esempio.it)']]])]),
        $sez([$w('articoli', ['numero' => 2, 'solo_pillar' => false, 'categoria' => $cat])]),
    ];
}
$pdo = db();
$st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute([$slug]);
$id = (int)$st->fetchColumn();
if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES (?, ?, 'published')")->execute([$titolo, $slug]); $id = (int)$pdo->lastInsertId(); }
$albero = editor_valida(['elementi' => $sezioni]);
editor_salva($id, $albero, true, 'prova', 'pagina di prova');
echo "pagina $id /$slug/, sezioni: " . count($albero['elementi']) . ", form: $form, categoria: $cat\n";
