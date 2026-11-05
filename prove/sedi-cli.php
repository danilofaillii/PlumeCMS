<?php
// Comandi da riga di comando per le prove delle sedi (2.27.0: prova-sedi.js, gruppo 42 di tools/sicurezza-prova.js). Stampa JSON.
// uso: php prove/sedi-cli.php <cartella CMS> <comando> [argomenti]
//   elenco                      le sedi salvate
//   azzera                      toglie tutte le sedi; le pagine delle sedi con lo slug che comincia per "sede-" o "prova-sede" vanno nel cestino e poi via
//   salva <json>                salva una sede (come dal pannello: sedi_valida + sedi_salva); stampa sede ed errori
//   geocodifica <indirizzo> [url] [prova 0|1]   sedi_geocodifica, per provare i rifiuti degli indirizzi interni
//   jsonld <percorso>           il JSON-LD calcolato di quell'indirizzo (seo_jsonld_di)
//   sql <query>                 una query (SELECT) con {p} al posto del prefisso
//   redattore <crea|elimina>    l'utente redattore "sedi_prova_red" (password Red-Sedi-Prova-2026)
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/sito-seo.php';
require_once $argv[1] . '/includes/seo.php';
require_once $argv[1] . '/includes/sedi.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
$cmd = $argv[2] ?? '';
$esci = function ($x) { echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n"; exit; };
switch ($cmd) {
    case 'elenco': $esci(array_values(sedi_tutte()));
    case 'azzera':
        $tolte = [];
        foreach (sedi_tutte() as $s) { if ($s['pagina'] > 0) { $r = contenuti_leggi('pagine', $s['pagina']); if ($r && (str_starts_with((string)$r['slug'], 'sede-') || str_starts_with((string)$r['slug'], 'prova-sede'))) { db()->prepare('DELETE FROM ' . tbl('pages') . ' WHERE id = ?')->execute([$s['pagina']]); $tolte[] = $r['slug']; } } }
        sedi_salva_elenco([]);
        $esci(['sedi' => 0, 'pagine_tolte' => $tolte]);
    case 'salva':
        $in = json_decode((string)($argv[3] ?? '{}'), true) ?: [];
        $v = sedi_valida($in, (string)($in['id'] ?? ''));
        if ($v['errori']) $esci(['errori' => $v['errori']]);
        $esci(['sede' => sedi_salva($v['sede']), 'errori' => []]);
    case 'geocodifica':
        $esci(sedi_geocodifica((string)($argv[3] ?? ''), isset($argv[4]) && $argv[4] !== '' ? $argv[4] : null, isset($argv[5]) ? $argv[5] === '1' : null));
    case 'jsonld':
        editor_carica_widget_esterni();
        $esci(seo_jsonld_di((string)($argv[3] ?? '/')));
    case 'sql':
        $q = str_replace('{p}', DB_PREFIX, (string)($argv[3] ?? ''));
        $st = db()->query($q);
        $esci($st && stripos(ltrim($q), 'SELECT') === 0 ? $st->fetchAll(PDO::FETCH_ASSOC) : ['ok' => true]);
    case 'redattore':
        $u = 'sedi_prova_red';
        if (($argv[3] ?? '') === 'elimina') { db()->prepare('DELETE FROM ' . tbl('users') . ' WHERE username = ?')->execute([$u]); $esci(['eliminato' => $u]); }
        db()->prepare('DELETE FROM ' . tbl('users') . ' WHERE username = ?')->execute([$u]);
        db()->prepare('INSERT INTO ' . tbl('users') . ' (username, password_hash, display_name, email, role) VALUES (?, ?, ?, ?, ?)')->execute([$u, function_exists('sicurezza_password_hash') ? sicurezza_password_hash('Red-Sedi-Prova-2026') : password_hash('Red-Sedi-Prova-2026', PASSWORD_DEFAULT), 'Redattore prova sedi', 'sedi_prova_red@esempio.it', 'editor']);
        $esci(['creato' => $u]);
    default: $esci(['errore' => 'comando sconosciuto']);
}
