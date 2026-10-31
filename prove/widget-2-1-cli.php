<?php
// Aiuti da riga di comando per prova-widget-2-1.js e attacchi-widget-2-1.js (risposte in JSON).
// uso: php prove/widget-2-1-cli.php <cartella CMS> <comando> [argomenti]
//   invii <n>            gli ultimi n invii dei form (con modulo, dati, allegati)
//   cartella-file        la cartella dei file dei form (percorso dal sito: uploads/riservati-.../form) e i file che ci sono
//   iscritto <email>     crea un iscritto attivo della newsletter con questa email (per "Cancella dati"); ritorna l'id
//   svuota-limiti        toglie gli invii di prova dell'ultima ora dal conto del limite (cambia l'IP a 0.0.0.0)
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/form.php';
form_schema();
$c = $argv[2] ?? '';
switch ($c) {
    case 'invii':
        $st = db()->query('SELECT i.id, i.modulo_id, m.nome AS modulo, i.email, i.nome, i.dati, i.allegati FROM ' . tbl('moduli_invii') . ' i JOIN ' . tbl('moduli') . ' m ON m.id = i.modulo_id ORDER BY i.id DESC LIMIT ' . max(1, (int)($argv[3] ?? 5)));
        echo json_encode(array_map(fn($r) => ['dati' => json_decode((string)$r['dati'], true), 'allegati' => form_allegati($r)] + $r, $st->fetchAll()), JSON_UNESCAPED_UNICODE);
        break;
    case 'cartella-file':
        $d = form_file_dir();
        echo json_encode(['url' => 'uploads' . substr($d, strlen(cms_uploads_dir())), 'file' => array_values(array_map('basename', array_filter(glob($d . '/*') ?: [], fn($f) => preg_match('/^[a-f0-9]{32}\./', basename($f)))))]);
        break;
    case 'iscritto':
        newsletter_schema();
        $e = (string)$argv[3];
        db()->prepare('DELETE FROM ' . tbl('newsletter_iscritti') . ' WHERE email = ?')->execute([$e]);
        db()->prepare('INSERT INTO ' . tbl('newsletter_iscritti') . " (email, nome, cognome, stato, fonte, consenso_versione, consenso_at, ip, creato, aggiornato) VALUES (?, 'Prova', 'File', 'attivo', 'form', ?, NOW(), '127.0.0.1', NOW(), NOW())")->execute([$e, MODULI_CONSENSO_VERSIONE]);
        echo json_encode(['id' => (int)db()->lastInsertId()]);
        break;
    case 'definizioni':
        // I controlli nuovi come li riceve il pannello dell'editor.
        require_once $argv[1] . '/includes/editor-controlli.php';
        require_once $argv[1] . '/includes/editor.php';
        editor_carica_widget_esterni();
        $d = editor_definizioni();
        $ctl = fn(string $t, string $n) => array_values(array_filter($d[$t]['controlli'] ?? [], fn($x) => ($x['nome'] ?? '') === $n))[0] ?? null;
        $out = [];
        foreach ([['articoli', 'categoria'], ['griglia_contenuti', 'categoria'], ['griglia_contenuti', 'tag'], ['video', 'da_solo'], ['video', 'ripeti'], ['mappa', 'come_arrivare'], ['richiesta_prenotazione', 'nota_addon']] as [$t, $n]) {
            $x = $ctl($t, $n);
            $out[$t . '.' . $n] = $x ? ['tipo' => $x['tipo'], 'opzioni' => $x['opzioni'] ?? null, 'accetta' => $x['accetta'] ?? null] : null;
        }
        $out['nuovi'] = array_map(fn($t) => isset($d[$t]) ? ['etichetta' => $d[$t]['etichetta'], 'gruppo' => $d[$t]['gruppo'], 'icona' => str_starts_with((string)$d[$t]['icona'], '<svg')] : null, array_combine($n5 = ['menu_ristorante', 'richiesta_prenotazione', 'galleria_filtri', 'scorrevole', 'contenuto_esterno'], $n5));
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        break;
    case 'svuota-limiti':
        $n = db()->exec('UPDATE ' . tbl('moduli_invii') . " SET ip = '0.0.0.0' WHERE ip = '127.0.0.1'");
        echo json_encode(['righe' => $n]);
        break;
    default:
        fwrite(STDERR, "comando sconosciuto\n"); exit(1);
}
