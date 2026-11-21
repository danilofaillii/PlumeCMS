<?php
// Per le prove del tipo di sito in prova e dei primi passi (1.4.0). Mai nel prodotto.
// uso: php tipo-sito-cli.php <cartella CMS> stato | pagina <slug> | articolo <slug> | copie | disegni | passi | togli-prova
//   stato     il kit, lo stato e quante voci ha il registro (JSON)
//   pagina    titolo e stato della pagina con quell'indirizzo, o "nessuna"
//   copie     quante copie "prima-del-cambio" ci sono
//   disegni   quanti disegni del kit ci sono in uploads/kit/
//   togli-prova  toglie kit_prova e primi_passi, come un sito che si e aggiornato
chdir($argv[1]);
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/kit-partenza.php';
switch ($argv[2] ?? '') {
    case 'stato':
        $s = kit_prova();
        echo json_encode($s ? ['kit' => $s['kit'], 'stato' => $s['stato'], 'creati' => count($s['creati']), 'tenuti' => $s['tenuti'], 'storia' => count($s['storia'])] : null, JSON_UNESCAPED_UNICODE), "\n";
        break;
    case 'pagina': case 'articolo':
        $st = db()->prepare('SELECT id, title, status FROM ' . tbl($argv[2] === 'pagina' ? 'pages' : 'articles') . ' WHERE slug = ?');
        $st->execute([$argv[3]]);
        $r = $st->fetch();
        echo $r ? $r['id'] . ' | ' . $r['title'] . ' | ' . $r['status'] : 'nessuna', "\n";
        break;
    case 'copie':
        require_once $argv[1] . '/includes/copia-sito.php';
        echo count(array_filter(copia_elenco(), fn($c) => str_contains($c['file'], 'prima-del-cambio'))), "\n";
        break;
    case 'disegni':
        // quanti disegni ci sono, e quanti di questi nessuna pagina usa (dopo un cambio devono essere 0)
        $f = glob(cms_uploads_dir() . '/kit/disegno-*.svg') ?: [];
        echo count($f), ' ', count(array_filter($f, fn($x) => !kit_immagine_usata('kit/' . basename($x)))), "\n";
        break;
    case 'togli-prova':
        db()->prepare('DELETE FROM ' . tbl('settings') . " WHERE setting_key IN ('kit_prova', 'primi_passi')")->execute();
        echo "tolto\n";
        break;
    default:
        echo "comando sconosciuto\n";
}
