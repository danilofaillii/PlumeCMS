<?php
/**
 * Aiuti da riga di comando per le prove degli addon Prenotazioni, Eventi con iscrizione e Recensioni (2.0.0).
 * Mai nel prodotto. uso: php prove/addon-b-cli.php <cartella CMS> <comando> [argomenti]
 *   pagina <slug> <titolo> <tipo widget> [impostazioni JSON]   crea (o rifà) una pagina pubblicata con quel widget
 *   sql "<query>"            esegue una query; {p} diventa il prefisso delle tabelle; stampa le righe in JSON
 *   cron [secondi]           il giro frequente degli addon accesi, con l'orologio spostato in avanti di N secondi
 */
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
[$dir, $cmd] = [$argv[1] ?? '', $argv[2] ?? ''];
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/cms.php';

switch ($cmd) {
    case 'pagina':
        require_once $dir . '/includes/editor-controlli.php';
        require_once $dir . '/includes/editor.php';
        editor_carica_widget_esterni();
        [$slug, $titolo, $tipo] = [$argv[3], $argv[4], $argv[5]];
        $imp = json_decode($argv[6] ?? '{}', true) ?: [];
        $w = array_replace_recursive(editor_widget($tipo), ['impostazioni' => $imp]);
        $albero = ['elementi' => [['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]]]];
        $st = db()->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?');
        $st->execute([$slug]);
        $id = (int)$st->fetchColumn();
        if (!$id) { db()->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES (?, ?, 'published')")->execute([$titolo, $slug]); $id = (int)db()->lastInsertId(); }
        editor_salva($id, editor_valida($albero), true, 'prova', 'pagina di prova');
        echo json_encode(['id' => $id, 'widget' => $w['tipo']]), "\n";
        break;
    case 'sql':
        $q = str_replace('{p}', DB_PREFIX, (string)($argv[3] ?? ''));
        $st = db()->query($q);
        echo json_encode($st->columnCount() ? $st->fetchAll() : ['righe' => $st->rowCount()], JSON_UNESCAPED_UNICODE), "\n";
        break;
    case 'cron':
        $avanti = (int)($argv[3] ?? 0);
        $out = [];
        foreach (cms_moduli() as $nome => $m) foreach ((array)($m['cron_spesso'] ?? []) as $k => $f) {
            // Le funzioni dei passi accettano l'istante (per le prove): si chiamano direttamente quando c'è uno spostamento.
            if ($avanti && $nome === 'prenotazioni') { require_once $dir . '/addon/prenotazioni/includes/prenotazioni.php'; $out[$k] = pren_cron(time() + $avanti); continue; }
            if ($avanti && $nome === 'eventi-iscrizione') { require_once $dir . '/addon/eventi-iscrizione/includes/eventi.php'; $out[$k] = evi_cron(time() + $avanti); continue; }
            if ($avanti && $nome === 'recensioni') { require_once $dir . '/addon/recensioni/includes/recensioni.php'; $out[$k] = rec_cron(time() + $avanti); continue; }
            $out[$k] = $f();
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE), "\n";
        break;
    default:
        fwrite(STDERR, "comando sconosciuto\n");
        exit(2);
}
