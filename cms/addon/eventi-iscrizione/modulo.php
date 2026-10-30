<?php
/**
 * Addon Eventi con iscrizione (2.0.0). Eventi con una pagina loro (/evento/<nome>/), iscrizione con più posti per
 * persona, lista d'attesa automatica, email di conferma con il file per il calendario e il link per cancellarsi,
 * promemoria, dati per Google (Event). Pannello: eventi, iscritti, CSV, presenze, email a tutti gli iscritti.
 * Documentazione: docs-sviluppo/addon/eventi-iscrizione.md. Il manifesto non fa query.
 */
$evi = __DIR__ . '/includes/eventi.php';
$pag = fn(string $p) => 'addon-pagina.php?a=eventi-iscrizione&p=' . $p;
return [
    'etichetta' => 'Eventi con iscrizione',
    'versione' => '1.0.0',
    'descrizione' => 'Serate, corsi, laboratori con i posti contati: ogni evento ha la sua pagina, ci si iscrive dal sito e, quando è pieno, si entra in lista d\'attesa. Email di conferma, promemoria e presenze.',
    'installa' => function () use ($evi) { require_once $evi; return evi_installa(); },
    'pannello' => [
        'eventi' => __DIR__ . '/includes/admin-eventi.php',
        'iscritti' => __DIR__ . '/includes/admin-iscritti.php',
        'impostazioni' => __DIR__ . '/includes/admin-impostazioni.php',
    ],
    'menu' => [[
        'nome' => 'Eventi', 'url' => $pag('eventi'), 'icona' => 'calendario-giorno', 'admin' => true, 'dopo' => 'Newsletter e form',
        'attivo' => [$pag('eventi'), $pag('iscritti'), $pag('impostazioni')],
        'sotto' => [
            ['nome' => 'Eventi', 'url' => $pag('eventi'), 'attivo' => [$pag('eventi')]],
            ['nome' => 'Iscritti', 'url' => $pag('iscritti'), 'attivo' => [$pag('iscritti')]],
            ['nome' => 'Impostazioni', 'url' => $pag('impostazioni'), 'attivo' => [$pag('impostazioni')]],
        ],
    ]],
    'widget' => [__DIR__ . '/includes/widget.php'],
    'cron_spesso' => [
        'eventi' => function () use ($evi) { require_once $evi; return evi_cron(); },
    ],
    'eventi' => [
        'sito_percorso' => function (string $percorso, array $q) {
            if (!str_starts_with($percorso, '/evento/') && !str_starts_with($percorso, '/evento-iscrizione/')) return;
            require_once __DIR__ . '/includes/sito.php';
            evi_sito($percorso, $q);
        },
    ],
];
