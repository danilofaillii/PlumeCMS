<?php
/**
 * I temi delle infografiche (widget ig_*): un tema è un insieme di valori per i campi di stile
 * (testata, testo della testata, sfondo e bordo della card, angoli, ombra, dimensione del testo,
 * carattere, palette delle voci). Dieci temi preimpostati più quelli salvati dal pannello,
 * nella tabella <prefisso>editor_temi. "Carta" è l'aspetto originale delle infografiche degli articoli:
 * un widget senza tema, o con i valori di partenza, si vede così.
 */

require_once __DIR__ . '/editor-widget.php';

/** I campi di stile che un tema può impostare, nell'ordine del pannello. */
function editor_temi_campi(): array {
    return ['testata', 'testata_colore', 'testata_colore2', 'testata_testo', 'occhiello_colore', 'corpo', 'corpo_colore', 'bordo_colore', 'bordo_spessore',
        'angoli', 'ombra', 'spaziatura', 'testo_dimensione', 'carattere', 'colore', 'colore_libero'];
}

/** I dieci temi preimpostati. Chiave → nome e valori. Il primo è l'originale. */
function editor_temi_preimpostati(): array {
    $base = ['testata' => 'scuro', 'testata_colore' => '', 'testata_colore2' => '', 'testata_testo' => '', 'occhiello_colore' => 'auto', 'corpo' => 'crema', 'corpo_colore' => '',
        'bordo_colore' => '', 'bordo_spessore' => 1, 'angoli' => 'arrotondati', 'ombra' => false, 'spaziatura' => 'normale', 'testo_dimensione' => 'normale', 'carattere' => 'sistema', 'colore_libero' => ''];
    return [
        // temi del sito (fase 4): Carta usa solo colori con un nome, e i nomi li colora il tema del sito. Senza tema: l'aspetto originale.
        'carta' => ['nome' => 'Carta (segue il tema del sito)', 'descrizione' => 'Prende i colori delle infografiche dal tema del sito. Senza tema è l\'aspetto originale: card color carta, testata scura, carattere di sistema.', 'valori' => $base],
        'notte' => ['nome' => 'Notte', 'descrizione' => 'Scuro come il sito, testata blu notte, scritte chiare.', 'valori' => array_merge($base, ['testata_colore' => '#1b1340', 'testata_colore2' => '#0a1024', 'testata_testo' => '#e6ebf5', 'occhiello_colore' => 'ciano', 'corpo' => 'scuro', 'bordo_colore' => '#2a3350', 'ombra' => true, 'carattere' => 'sito'])],
        'ardesia' => ['nome' => 'Ardesia', 'descrizione' => 'Grigio pietra, angoli squadrati, segni ciano scuro.', 'valori' => array_merge($base, ['testata_colore' => '#334155', 'testata_colore2' => '#1e293b', 'occhiello_colore' => 'ciano', 'corpo' => 'libero', 'corpo_colore' => '#f1f5f9', 'bordo_colore' => '#cbd5e1', 'angoli' => 'squadrati', 'carattere' => 'sito', 'colore' => 'ciano_scuro'])],
        'salvia' => ['nome' => 'Salvia', 'descrizione' => 'Verde salvia, angoli molto arrotondati, carattere con le grazie.', 'valori' => array_merge($base, ['testata' => 'verde', 'corpo' => 'libero', 'corpo_colore' => '#f3f7f2', 'bordo_colore' => '#cfdccf', 'angoli' => 'molto', 'carattere' => 'serif', 'colore' => 'verde'])],
        'terracotta' => ['nome' => 'Terracotta', 'descrizione' => 'Mattone e sabbia, segni rosso scuro, ombra leggera.', 'valori' => array_merge($base, ['testata' => 'mattone', 'corpo' => 'libero', 'corpo_colore' => '#fbf1e8', 'bordo_colore' => '#e8c9b0', 'ombra' => true, 'colore' => 'rosso_scuro'])],
        'oro' => ['nome' => 'Oro', 'descrizione' => 'Testata oro scuro su card bianca, carattere dei titoli del sito.', 'valori' => array_merge($base, ['testata' => 'oro_scuro', 'occhiello_colore' => 'oro', 'corpo' => 'bianco', 'bordo_colore' => '#e8d9a8', 'carattere' => 'titoli', 'colore' => 'oro_scuro'])],
        'viola' => ['nome' => 'Viola profondo', 'descrizione' => 'Viola intenso, card lilla chiarissimo, ombra.', 'valori' => array_merge($base, ['testata_colore' => '#5a3d7a', 'testata_colore2' => '#2d1b4e', 'occhiello_colore' => 'viola', 'corpo' => 'libero', 'corpo_colore' => '#f6f2fb', 'bordo_colore' => '#d9cdee', 'ombra' => true, 'carattere' => 'sito', 'colore' => 'viola'])],
        'ciano' => ['nome' => 'Ciano', 'descrizione' => 'Verde acqua, card celeste chiaro, angoli molto arrotondati.', 'valori' => array_merge($base, ['testata' => 'ciano', 'corpo' => 'libero', 'corpo_colore' => '#eef7f8', 'bordo_colore' => '#bfdde0', 'angoli' => 'molto', 'carattere' => 'sito', 'colore' => 'ciano'])],
        'bianco_nero' => ['nome' => 'Bianco e nero', 'descrizione' => 'Solo nero su bianco, bordo spesso, angoli squadrati, carattere a spaziatura fissa.', 'valori' => array_merge($base, ['testata_colore' => '#111111', 'testata_colore2' => '#111111', 'occhiello_colore' => 'bianco', 'corpo' => 'bianco', 'bordo_colore' => '#111111', 'bordo_spessore' => 2, 'angoli' => 'squadrati', 'carattere' => 'mono', 'colore_libero' => '#111111'])],
        'pastello' => ['nome' => 'Pastello', 'descrizione' => 'Rosa e celeste tenui, testo scuro, angoli morbidi.', 'valori' => array_merge($base, ['testata_colore' => '#f7c8d0', 'testata_colore2' => '#c8d8f7', 'testata_testo' => '#3b2d4a', 'occhiello_colore' => 'viola', 'corpo' => 'libero', 'corpo_colore' => '#fffaf5', 'bordo_colore' => '#f0dfe6', 'angoli' => 'molto', 'ombra' => true, 'carattere' => 'serif'])],
    ];
}

function editor_temi_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    db()->exec('CREATE TABLE IF NOT EXISTS ' . tbl('editor_temi') . " (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `nome` VARCHAR(100) NOT NULL,
        `valori` TEXT NOT NULL,
        `creato` DATETIME NOT NULL,
        `aggiornato` DATETIME NOT NULL,
        PRIMARY KEY (`id`)
    )");
}

/** I temi salvati dal pannello: chiave "t<id>" → nome, valori, id. */
function editor_temi_salvati(): array {
    try {
        editor_temi_schema();
        $out = [];
        foreach (db()->query('SELECT id, nome, valori FROM ' . tbl('editor_temi') . ' ORDER BY nome, id') as $r) {
            $val = json_decode((string)$r['valori'], true);
            $out['t' . (int)$r['id']] = ['nome' => (string)$r['nome'], 'descrizione' => 'Tema salvato da te.', 'valori' => editor_tema_pulisci(is_array($val) ? $val : []), 'id' => (int)$r['id']];
        }
        return $out;
    } catch (Throwable $e) {
        return []; // senza database (prove a secco) restano i preimpostati
    }
}

/** Tutti i temi: preimpostati e salvati. */
function editor_temi_infografica(): array {
    return editor_temi_preimpostati() + editor_temi_salvati();
}

/** Le opzioni del campo "tema" nel pannello. */
function editor_temi_opzioni(): array {
    $o = [];
    foreach (editor_temi_infografica() as $k => $t) $o[$k] = $t['nome'] . (isset($t['id']) ? ' (salvato)' : '');
    return $o;
}

/** Tiene solo i campi di stile, ripuliti con le regole dei controlli del widget. */
function editor_tema_pulisci(array $valori): array {
    // Senza passare da editor_definizioni(): le definizioni chiedono l'elenco dei temi, e i temi non devono richiamare le definizioni.
    require_once __DIR__ . '/editor.php';
    $ctl = [];
    foreach (array_merge(igx_controlli_stile_testata(), igx_controlli_coda(), [igx_ctl_colore()]) as $c) $ctl[$c['nome']] = $c;
    $out = [];
    foreach (editor_temi_campi() as $k) {
        if (array_key_exists($k, $valori) && isset($ctl[$k])) $out[$k] = editor_pulisci_valore($ctl[$k], $valori[$k]);
    }
    return $out;
}

function editor_tema_nome_valido(string $nome): string {
    $nome = trim(preg_replace('/\s+/u', ' ', $nome));
    if ($nome === '') throw new RuntimeException('Dai un nome al tema.');
    if (preg_match('/[\x{10000}-\x{10FFFF}]/u', $nome)) throw new RuntimeException('Il nome del tema non può contenere emoji.');
    return mb_substr($nome, 0, 100);
}

function editor_tema_salva(string $nome, array $valori): int {
    editor_temi_schema();
    $nome = editor_tema_nome_valido($nome);
    $json = json_encode(editor_tema_pulisci($valori), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    db()->prepare('INSERT INTO ' . tbl('editor_temi') . ' (nome, valori, creato, aggiornato) VALUES (?, ?, NOW(), NOW())')->execute([$nome, $json]);
    $id = (int)db()->lastInsertId();
    editor_tema_versione($id, 'creato'); // versioni
    return $id;
}

function editor_tema_rinomina(int $id, string $nome): void {
    editor_temi_schema();
    $st = db()->prepare('UPDATE ' . tbl('editor_temi') . ' SET nome = ?, aggiornato = NOW() WHERE id = ?');
    $st->execute([editor_tema_nome_valido($nome), $id]);
    if (!$st->rowCount()) throw new RuntimeException('Tema non trovato.');
    editor_tema_versione($id, 'rinominato'); // versioni
}

function editor_tema_elimina(int $id): void {
    editor_temi_schema();
    editor_tema_versione($id, 'eliminato (un ripristino lo ricrea)'); // versioni
    db()->prepare('DELETE FROM ' . tbl('editor_temi') . ' WHERE id = ?')->execute([$id]);
}

/** versioni: una copia del tema in {prefisso}editor_versioni (ambito "tema"). Mai un errore da qui. */
function editor_tema_versione(int $id, string $nota): void {
    try {
        $st = db()->prepare('SELECT nome, valori FROM ' . tbl('editor_temi') . ' WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) return;
        $val = json_decode((string)$r['valori'], true);
        require_once __DIR__ . '/versioni.php';
        editor_versione_salva('tema', $id, (string)$r['nome'], ['nome' => (string)$r['nome'], 'valori' => editor_tema_pulisci(is_array($val) ? $val : [])], $nota);
    } catch (Throwable $e) {
        error_log('versioni (tema): ' . $e->getMessage());
    }
}
