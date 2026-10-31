<?php
/**
 * Addon "Area riservata" (2.0.0): gli account dei visitatori del sito (membri), separati dagli utenti del pannello.
 * Iscrizione con conferma dell'email, accesso, password dimenticata, profilo, cancella il mio account; pagine e articoli
 * "solo per i membri"; file riservati di Download dati ai membri; widget "Accesso membri".
 * Documentazione tecnica: docs-sviluppo/addon/membri.md.
 */
return [
    'etichetta' => 'Area riservata',
    'versione' => '1.1.0',
    'descrizione' => 'Account per i visitatori del sito: iscrizione con conferma dell\'email, accesso, profilo. Pagine, articoli e file solo per chi è iscritto. Spento, i contenuti riservati tornano visibili a tutti.',
    'installa' => function () { require_once __DIR__ . '/includes/membri.php'; return membri_installa(); },
    'menu' => [[
        'nome' => 'Membri', 'url' => 'addon-pagina.php?a=membri&p=elenco', 'icona' => 'utenti', 'admin' => true, 'dopo' => 'Utenti',
        'attivo' => ['addon-pagina.php?a=membri&p=elenco', 'addon-pagina.php?a=membri&p=riservati', 'addon-pagina.php?a=membri&p=impostazioni'],
        'sotto' => [
            ['nome' => 'Elenco dei membri', 'url' => 'addon-pagina.php?a=membri&p=elenco', 'attivo' => ['addon-pagina.php?a=membri&p=elenco']],
            ['nome' => 'Contenuti e file riservati', 'url' => 'addon-pagina.php?a=membri&p=riservati', 'attivo' => ['addon-pagina.php?a=membri&p=riservati']],
            ['nome' => 'Impostazioni', 'url' => 'addon-pagina.php?a=membri&p=impostazioni', 'attivo' => ['addon-pagina.php?a=membri&p=impostazioni']],
        ],
    ]],
    'pannello' => [
        'elenco' => __DIR__ . '/includes/admin-elenco.php',
        'riservati' => __DIR__ . '/includes/admin-riservati.php',
        'impostazioni' => __DIR__ . '/includes/admin-impostazioni.php',
    ],
    'eventi' => [
        // Le pagine pubbliche: /accedi/, /iscriviti/, /area-riservata/ e le altre (includes/pagine.php).
        'sito_percorso' => function (string $percorso, array $q) {
            if (!preg_match('~^/(accedi|iscriviti|esci|password-dimenticata|nuova-password|conferma-iscrizione|conferma-email|area-riservata)(/|$)~', $percorso)) return;
            require_once __DIR__ . '/includes/pagine.php';
            membri_pagina($percorso, $q);
        },
        // Un contenuto "solo per i membri" visto da chi non è entrato: la pagina con l'invito ad accedere.
        'sito_contenuto' => function (array $c, array $meta) {
            require_once __DIR__ . '/includes/pagine.php';
            membri_controlla_contenuto($c, $meta);
        },
        // 2.1: la spunta "Solo per i membri" della scheda di un articolo o di una pagina (riquadro qui sotto, scheda_riquadri).
        'scheda_salvata' => function (string $tipo, int $id, array $d) {
            require_once __DIR__ . '/includes/membri.php';
            membri_scheda_salva($tipo, $id, $d);
        },
    ],
    'filtri' => [
        // Lo stesso contenuto nei feed, in llms-full.txt e nei widget che mostrano il testo: al posto del testo, un avviso.
        'contenuto_riservato' => function ($valore, array $c) {
            if (is_string($valore)) return $valore;
            require_once __DIR__ . '/includes/membri.php';
            return membri_contenuto_chiuso($c) ? '<p>' . h(membri_testo_avviso()) . '</p>' : null;
        },
        // 2.1: i contenuti riservati non escono nella sitemap, nel feed, in llms.txt e nella ricerca (tranne per un membro).
        'contenuti_elenco' => function (array $tutti, string $dove) {
            require_once __DIR__ . '/includes/membri.php';
            return membri_elenco_filtra($tutti, $dove);
        },
        // 2.1: il riquadro "Area riservata" nella scheda del contenuto (admin/contenuto.php).
        'scheda_riquadri' => function (array $riquadri, string $tipo, int $id, $r) {
            require_once __DIR__ . '/includes/membri.php';
            $html = membri_scheda_riquadro($tipo, $id, is_array($r) ? $r : null);
            if ($html !== '') $riquadri[] = $html;
            return $riquadri;
        },
        'dashboard_numeri' => function (array $n) {
            require_once __DIR__ . '/includes/membri.php';
            try { $n[] = ['valore' => (string)membri_conta('attivo'), 'etichetta' => 'Membri attivi']; } catch (Throwable $e) {}
            return $n;
        },
    ],
    'widget' => [__DIR__ . '/includes/widget.php'],
    'cron' => [
        'Area riservata, pulizia' => function () { require_once __DIR__ . '/includes/membri.php'; return membri_pulizia(); },
    ],
];
