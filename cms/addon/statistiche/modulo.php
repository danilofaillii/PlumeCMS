<?php
/**
 * Addon Statistiche (2.0.0): le visite del sito contate sul server, senza cookie e senza salvare l'indirizzo IP.
 * Ascolta l'evento sito_pagina_servita del nucleo (sito/index.php). Motore in includes/statistiche.php, pagine del
 * pannello in includes/admin-*.php. Documentazione: docs-sviluppo/addon/statistiche.md e statistiche-uso.md.
 */
return [
    'etichetta' => 'Statistiche delle visite',
    'versione' => '1.0.0',
    'descrizione' => 'Quante persone visitano il sito, quali pagine leggono, da dove arrivano e con che dispositivo. Senza cookie, senza servizi esterni e senza salvare l\'indirizzo IP: non serve il banner dei cookie.',
    'installa' => function () {
        require_once __DIR__ . '/includes/statistiche.php';
        return st_installa();
    },
    'menu' => [
        ['nome' => 'Statistiche', 'url' => 'addon-pagina.php?a=statistiche&p=visite', 'icona' => 'grafico-barre', 'admin' => true, 'dopo' => 'Dashboard',
            'attivo' => ['addon-pagina.php?a=statistiche&p=visite', 'addon-pagina.php?a=statistiche&p=impostazioni'],
            'sotto' => [
                ['nome' => 'Visite', 'url' => 'addon-pagina.php?a=statistiche&p=visite', 'attivo' => ['addon-pagina.php?a=statistiche&p=visite']],
                ['nome' => 'Impostazioni e privacy', 'url' => 'addon-pagina.php?a=statistiche&p=impostazioni', 'attivo' => ['addon-pagina.php?a=statistiche&p=impostazioni']],
            ]],
    ],
    'pannello' => [
        'visite' => __DIR__ . '/includes/admin-visite.php',
        'esporta' => __DIR__ . '/includes/admin-esporta.php',
        'impostazioni' => __DIR__ . '/includes/admin-impostazioni.php',
    ],
    'eventi' => [
        // Una pagina HTML del sito pubblico è stata servita: si conta (dopo la risposta), se è una visita vera.
        'sito_pagina_servita' => function (int $codice, string $percorso) {
            if ($codice !== 200) return;
            require_once __DIR__ . '/includes/statistiche.php';
            st_conta($codice, $percorso);
        },
    ],
    'filtri' => [
        'dashboard_numeri' => function (array $n) {
            require_once __DIR__ . '/includes/statistiche.php';
            try {
                $oggi = date('Y-m-d');
                $n[] = ['valore' => st_totali($oggi, $oggi)['visite'], 'etichetta' => 'visite oggi'];
                $n[] = ['valore' => st_totali(date('Y-m-d', strtotime('-6 days')), $oggi)['visite'], 'etichetta' => 'visite, 7 giorni'];
            } catch (Throwable $e) {}
            return $n;
        },
    ],
    'cron' => [
        'statistiche' => function () {
            require_once __DIR__ . '/includes/statistiche.php';
            return st_pulizia();
        },
    ],
];
