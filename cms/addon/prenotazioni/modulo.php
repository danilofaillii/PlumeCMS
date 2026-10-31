<?php
/**
 * Addon Prenotazioni e appuntamenti (2.0.0). Servizi, persone con i loro orari, posti liberi calcolati dal server nel
 * fuso del sito, prenotazione dal sito con il widget "Prenota", email con il file per il calendario e il link personale
 * per annullare o spostare, promemoria il giorno prima, avviso all'attività. Documentazione: docs-sviluppo/addon/prenotazioni.md.
 * Il manifesto non fa query: dichiara e carica i file veri solo quando servono.
 */
$pren = __DIR__ . '/includes/prenotazioni.php';
$pag = fn(string $p) => 'addon-pagina.php?a=prenotazioni&p=' . $p;
$pagine = ['agenda', 'nuova', 'elenco', 'servizi', 'persone', 'impostazioni'];
return [
    'etichetta' => 'Prenotazioni e appuntamenti',
    'versione' => '1.1.0',
    'descrizione' => 'I clienti prenotano dal sito un servizio, con chi e quando, fra gli orari davvero liberi. Ricevono l\'email di conferma con il promemoria del giorno prima, e possono annullare o spostare da soli.',
    'installa' => function () use ($pren) { require_once $pren; return pren_installa(); },
    'pannello' => [
        'agenda' => __DIR__ . '/includes/admin-agenda.php',
        'nuova' => __DIR__ . '/includes/admin-nuova.php', // 2.1: la prenotazione presa al telefono o di persona
        'elenco' => __DIR__ . '/includes/admin-elenco.php',
        'servizi' => __DIR__ . '/includes/admin-servizi.php',
        'persone' => __DIR__ . '/includes/admin-persone.php',
        'impostazioni' => __DIR__ . '/includes/admin-impostazioni.php',
    ],
    'menu' => [[
        'nome' => 'Prenotazioni', 'url' => $pag('agenda'), 'icona' => 'calendario-spunta', 'admin' => true, 'dopo' => 'Newsletter e form',
        'attivo' => array_map($pag, $pagine),
        'sotto' => [
            ['nome' => 'Agenda', 'url' => $pag('agenda'), 'attivo' => [$pag('agenda')]],
            ['nome' => 'Nuova prenotazione', 'url' => $pag('nuova'), 'attivo' => [$pag('nuova')]],
            ['nome' => 'Prenotazioni', 'url' => $pag('elenco'), 'attivo' => [$pag('elenco')]],
            ['nome' => 'Servizi', 'url' => $pag('servizi'), 'attivo' => [$pag('servizi')]],
            ['nome' => 'Persone e orari', 'url' => $pag('persone'), 'attivo' => [$pag('persone')]],
            ['nome' => 'Impostazioni', 'url' => $pag('impostazioni'), 'attivo' => [$pag('impostazioni')]],
        ],
    ]],
    'widget' => [__DIR__ . '/includes/widget.php'],
    'cron_spesso' => [
        'prenotazioni' => function () use ($pren) { require_once $pren; return pren_cron(); },
    ],
    'eventi' => [
        // Gli indirizzi pubblici: /prenotazione/posti/, /prenotazione/invia/, /prenotazione/gestisci/, /prenotazione/calendario/.
        'sito_percorso' => function (string $percorso, array $q) {
            if (!str_starts_with($percorso, '/prenotazione/')) return;
            require_once __DIR__ . '/includes/sito.php';
            pren_sito($percorso, $q);
        },
    ],
];
