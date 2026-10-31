<?php
/**
 * Addon "Corsi online" (2.0.0): corsi con moduli e lezioni in ordine, video (YouTube, Vimeo o un file caricato), materiali
 * da scaricare, avanzamento per membro, certificato di completamento con un codice di verifica pubblico.
 * Richiede l'Area riservata (addon membri): chi segue un corso è un membro del sito.
 * Documentazione tecnica: docs-sviluppo/addon/corsi.md.
 */
return [
    'etichetta' => 'Corsi online',
    'versione' => '1.1.0',
    'descrizione' => 'Corsi fatti di moduli e lezioni, con testo, video e materiali da scaricare. Ogni membro vede quanto gli manca, riprende da dove era e alla fine riceve un certificato.',
    'richiede' => ['membri'],
    'installa' => function () { require_once __DIR__ . '/includes/corsi.php'; return corsi_installa(); },
    'menu' => [[
        'nome' => 'Corsi', 'url' => 'addon-pagina.php?a=corsi&p=corsi', 'icona' => 'laurea', 'admin' => true, 'dopo' => 'Membri',
        'attivo' => ['addon-pagina.php?a=corsi&p=corsi', 'addon-pagina.php?a=corsi&p=corso', 'addon-pagina.php?a=corsi&p=lezione'],
    ]],
    'pannello' => [
        'corsi' => __DIR__ . '/includes/admin-corsi.php',
        'corso' => __DIR__ . '/includes/admin-corso.php',
        'lezione' => __DIR__ . '/includes/admin-lezione.php',
    ],
    'eventi' => [
        // /corsi/, /corsi/<corso>/, /corsi/<corso>/<lezione>/, il certificato e la sua verifica pubblica (/certificato/<codice>/).
        'sito_percorso' => function (string $percorso, array $q) {
            if (!preg_match('~^/(corsi|certificato)(/|$)~', $percorso)) return;
            require_once __DIR__ . '/includes/pagine.php';
            corsi_pagina($percorso, $q);
        },
        // Un membro cancellato (dal pannello o da "Cancella il mio account"): via anche avanzamento, assegnazioni e certificati.
        'membro_eliminato' => function (int $id) { require_once __DIR__ . '/includes/corsi.php'; corsi_dimentica_membro($id); },
    ],
    'filtri' => [
        // La sezione "I miei corsi" nell'area riservata del membro.
        'membri_area' => function (array $sezioni, array $membro) {
            require_once __DIR__ . '/includes/pagine.php';
            $s = corsi_sezione_area($membro);
            if ($s) $sezioni[] = $s;
            return $sezioni;
        },
    ],
    'widget' => [__DIR__ . '/includes/widget.php'],
    // 2.1: il testo delle lezioni si scrive con l'editor visuale (contenitore dell'editor, includes/editor.php).
    'editor_contenitori' => [
        'corsi_lezione' => function () { require_once __DIR__ . '/includes/corsi.php'; return corsi_contenitore(); },
    ],
];
