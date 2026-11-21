<?php
// Comandi da riga di comando per le prove degli strumenti SEO (2.29.0: prova-seo-strumenti.js, gruppo 44 di tools/sicurezza-prova.js). Stampa JSON.
// uso: php prove/seo-strumenti-cli.php <cartella CMS> <comando> [argomenti]
//   sql <query>                     una query con {p} al posto del prefisso (SELECT stampa le righe)
//   analisi <tipo> <id>             seo_analisi() del contenuto salvato (con i link suggeriti)
//   suggeriti <tipo> <id>           solo i link interni suggeriti
//   jsonld <percorso>               il JSON-LD calcolato di quell'indirizzo (seo_jsonld_di)
//   tabella                         le righe della Tabella SEO (seo_tabella_righe)
//   alt_pagine                      le immagini senza alt nei contenuti pubblicati (media_alt_nelle_pagine)
//   alt_libreria                    le immagini della libreria senza alt (media_alt_senza)
//   alt_azzera                      svuota la tabella degli alt della libreria
//   pagina <slug> <titolo> <json albero widget[]>   crea (o rifà) una pagina pubblicata con quei widget (uno per sezione)
//   articolo <slug> <titolo> <keyword> <descrizione> <json widget[]>   crea (o rifà) un articolo pubblicato fatto con l'editor
//   addon <nome> <1|0>              accende o spegne un addon (come Impostazioni > Addon)
//   prodotto <json>                 un prodotto della Vetrina pubblicato (vt_prodotto_salva); stampa id e slug
//   foto <nome>                     scrive un PNG minimo in <uploads>/pagine/AAAA/MM/<nome>.png (una foto della libreria); stampa url
//   foto_togli <nome>               lo cancella
//   foto_copia <file> <nome>        copia un JPG o PNG vero in <uploads>/pagine/AAAA/MM/<nome>.<ext> (per le schermate del manuale); stampa url
//   redattore <crea|elimina>        l'utente redattore "seo_prova_red" (password Red-Seo-Prova-2026)
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/sito-seo.php';
require_once $argv[1] . '/includes/seo.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
require_once $argv[1] . '/includes/media-alt.php';
require_once $argv[1] . '/includes/seo-tabella.php';
$cmd = $argv[2] ?? '';
$esci = function ($x) { echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n"; exit; };
switch ($cmd) {
    case 'sql':
        $q = str_replace('{p}', DB_PREFIX, (string)($argv[3] ?? ''));
        $st = db()->query($q);
        $esci($st && stripos(ltrim($q), 'SELECT') === 0 ? $st->fetchAll(PDO::FETCH_ASSOC) : ['ok' => true]);
    case 'analisi': case 'suggeriti':
        editor_carica_widget_esterni();
        $tipo = (string)($argv[3] ?? 'articoli'); $id = (int)($argv[4] ?? 0);
        $r = contenuti_leggi($tipo, $id);
        if (!$r) $esci(['errore' => 'contenuto non trovato']);
        $t = contenuti_tipo($tipo);
        $riga = $r + ['contenuto' => (string)($r[$t['campo_contenuto']] ?? ''), 'tipo_contenuto' => $tipo];
        if (trim((string)($r['struttura'] ?? '')) !== '') { $a = editor_decodifica((string)$r['struttura']); if ($a) { [$h] = editor_disegna($a, contenuti_percorso($tipo, $r)); $riga['contenuto'] = $h; $riga['content_format'] = 'html'; } }
        $an = seo_analisi($riga);
        $esci($cmd === 'suggeriti' ? $an['suggeriti'] : ['punteggio' => $an['punteggio'], 'voci' => $an['voci'], 'suggeriti' => $an['suggeriti']]);
    case 'jsonld':
        editor_carica_widget_esterni();
        $esci(seo_jsonld_di((string)($argv[3] ?? '/')));
    case 'tabella':
        editor_carica_widget_esterni();
        $esci(seo_tabella_righe());
    case 'alt_pagine':
        editor_carica_widget_esterni();
        $esci(media_alt_nelle_pagine());
    case 'alt_libreria':
        $esci(media_alt_senza());
    case 'alt_azzera':
        media_alt_schema();
        db()->exec('DELETE FROM ' . tbl('media_alt'));
        save_setting('media_alt_n', '0');
        $esci(['ok' => true]);
    case 'pagina':
        editor_carica_widget_esterni();
        [$slug, $titolo] = [(string)$argv[3], (string)$argv[4]];
        $widget = json_decode((string)($argv[5] ?? '[]'), true) ?: [];
        $sezioni = [];
        foreach ($widget as $w) {
            $x = array_replace_recursive(editor_widget((string)$w['tipo']), ['impostazioni' => (array)($w['impostazioni'] ?? [])]);
            $sezioni[] = ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$x]]]];
        }
        $st = db()->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute([$slug]);
        $id = (int)$st->fetchColumn();
        if (!$id) { db()->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status, publish_at) VALUES (?, ?, 'published', NOW())")->execute([$titolo, $slug]); $id = (int)db()->lastInsertId(); }
        editor_salva($id, editor_valida(['elementi' => $sezioni]), true, 'prova', 'pagina di prova');
        $esci(['id' => $id]);
    case 'articolo':
        editor_carica_widget_esterni();
        [$slug, $titolo, $kw, $descr] = [(string)$argv[3], (string)$argv[4], (string)($argv[5] ?? ''), (string)($argv[6] ?? '')];
        $widget = json_decode((string)($argv[7] ?? '[]'), true) ?: [];
        $sezioni = [];
        foreach ($widget as $w) {
            $x = array_replace_recursive(editor_widget((string)$w['tipo']), ['impostazioni' => (array)($w['impostazioni'] ?? [])]);
            $sezioni[] = ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$x]]]];
        }
        $st = db()->prepare('SELECT id FROM ' . tbl('articles') . ' WHERE slug = ?'); $st->execute([$slug]);
        $id = (int)$st->fetchColumn();
        if (!$id) { db()->prepare('INSERT INTO ' . tbl('articles') . " (title, slug, status, publish_date, publish_time, content, content_format, keyword_target, meta_description, category) VALUES (?, ?, 'published', CURDATE(), '00:00:00', '', 'html', ?, ?, '')")->execute([$titolo, $slug, $kw, $descr]); $id = (int)db()->lastInsertId(); }
        else db()->prepare('UPDATE ' . tbl('articles') . ' SET keyword_target = ?, meta_description = ? WHERE id = ?')->execute([$kw, $descr, $id]);
        editor_salva($id, editor_valida(['elementi' => $sezioni]), true, 'prova', 'articolo di prova', 'articoli');
        $esci(['id' => $id]);
    case 'addon':
        require_once $argv[1] . '/includes/addon.php';
        $esci(['log' => addon_attiva((string)$argv[3], ($argv[4] ?? '1') === '1')]);
    case 'prodotto':
        require_once $argv[1] . '/addon/vetrina/includes/vetrina.php';
        $d = json_decode((string)($argv[3] ?? '{}'), true) ?: [];
        $r = vt_prodotto_salva(0, $d + ['pubblicato' => 1]);
        if (!is_int($r)) $esci(['errore' => $r]);
        $esci(['id' => $r, 'slug' => vt_prodotto($r, false)['slug'] ?? '']);
    case 'foto': case 'foto_togli':
        require_once $argv[1] . '/includes/media.php';
        $nome = preg_replace('/[^a-z0-9-]/', '', (string)($argv[3] ?? 'prova-seo'));
        $rel = 'pagine/' . date('Y/m') . '/' . $nome . '.png';
        $file = cms_uploads_dir() . '/' . $rel;
        if ($cmd === 'foto_togli') { @unlink($file); @unlink($file . '.webp'); $esci(['tolto' => $rel]); }
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);
        file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
        chmod($file, 0644);
        $esci(['url' => MEDIA_RADICE_UPLOADS . '/' . $rel, 'file' => $file]);
    case 'foto_copia':
        require_once $argv[1] . '/includes/media.php';
        $da = (string)($argv[3] ?? ''); $nome = preg_replace('/[^a-z0-9-]/', '', (string)($argv[4] ?? 'foto'));
        $ext = strtolower(pathinfo($da, PATHINFO_EXTENSION)) === 'png' ? 'png' : 'jpg';
        if (!is_file($da)) $esci(['errore' => 'file sorgente non trovato']);
        $rel = 'pagine/' . date('Y/m') . '/' . $nome . '.' . $ext;
        $file = cms_uploads_dir() . '/' . $rel;
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);
        copy($da, $file); chmod($file, 0644);
        $esci(['url' => MEDIA_RADICE_UPLOADS . '/' . $rel, 'file' => $file]);
    case 'redattore':
        $u = 'seo_prova_red';
        db()->prepare('DELETE FROM ' . tbl('users') . ' WHERE username = ?')->execute([$u]);
        if (($argv[3] ?? '') === 'elimina') $esci(['eliminato' => $u]);
        db()->prepare('INSERT INTO ' . tbl('users') . ' (username, password_hash, display_name, email, role) VALUES (?, ?, ?, ?, ?)')->execute([$u, function_exists('sicurezza_password_hash') ? sicurezza_password_hash('Red-Seo-Prova-2026') : password_hash('Red-Seo-Prova-2026', PASSWORD_DEFAULT), 'Redattore prova SEO', 'seo_prova_red@esempio.it', 'editor']);
        $esci(['creato' => $u]);
    default: $esci(['errore' => 'comando sconosciuto']);
}
