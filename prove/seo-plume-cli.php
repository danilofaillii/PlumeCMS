<?php
// Comandi da riga di comando per prove/prova-seo-plume.js (2.35.0) e per il gruppo 49 di tools/sicurezza-prova.js. Stampa JSON.
// uso: php prove/seo-plume-cli.php <cartella CMS> <comando> [argomenti]
//   llms                            il llms.txt che il sito serve (seo_llms: quello scritto a mano, altrimenti calcolato)
//   importa <file json>             sito_importa_pagina() del JSON (come la voce "pagine" di un pacchetto); errore se rifiuta
//   pagina <slug>                   le colonne SEO della pagina (tldr, faq, keyword_target, seo_schema, status, robots)
//   manifest <file zip>             agg_leggi_manifest() dello zip: la voce "seo" letta, oppure l'errore
//   seo_salva <gruppo> <json>       seo_salva() di un gruppo di SEO e AEO (come il pannello)
//   seo_azzera <gruppo>             toglie il gruppo da seo_config (torna ai predefiniti)
//   seo_cfg                         i gruppi titolo, meta, org, persona, schema, llms di seo_config
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/sito-seo.php';
require_once $argv[1] . '/includes/seo.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
require_once $argv[1] . '/includes/aggiornamenti.php';
require_once $argv[1] . '/sito/extra.php';
$cmd = $argv[2] ?? '';
$esci = function ($x) { echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n"; exit; };
try {
    switch ($cmd) {
        case 'llms':
            editor_carica_widget_esterni();
            $esci(['testo' => seo_llms()]);
        case 'importa':
            sito_schema_aggiorna();
            $j = json_decode((string)file_get_contents((string)$argv[3]), true);
            $esci(['esito' => sito_importa_pagina(is_array($j) ? $j : [])]);
        case 'pagina':
            sito_schema_aggiorna();
            $st = db()->prepare('SELECT id, status, robots, tldr, faq, keyword_target, seo_schema, seo_title, meta_description FROM ' . tbl('pages') . ' WHERE slug = ?');
            $st->execute([(string)$argv[3]]);
            $esci($st->fetch(PDO::FETCH_ASSOC) ?: ['errore' => 'pagina non trovata']);
        case 'manifest':
            $m = agg_leggi_manifest((string)$argv[3]);
            $esci(['seo' => $m['seo'], 'nome' => $m['nome']]);
        case 'seo_salva':
            seo_salva((string)$argv[3], json_decode((string)($argv[4] ?? '{}'), true) ?: []);
            $esci(['ok' => true]);
        case 'seo_azzera':
            $tutto = json_decode((string)get_setting('seo_config', ''), true) ?: [];
            unset($tutto[(string)$argv[3]]);
            save_setting('seo_config', json_encode($tutto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $esci(['ok' => true]);
        case 'seo_cfg':
            $c = seo_cfg(true);
            $esci(array_intersect_key($c, array_flip(['titolo', 'meta', 'org', 'persona', 'schema', 'llms'])));
        default: $esci(['errore' => 'comando sconosciuto']);
    }
} catch (Throwable $e) {
    $esci(['errore' => $e->getMessage()]);
}
