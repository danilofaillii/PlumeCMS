<?php
/**
 * Addon Raccolta recensioni (2.0.0). Dopo un appuntamento svolto (evento prenotazione_svolta dell'addon Prenotazioni, se è
 * acceso: nessuna dipendenza obbligatoria), dopo un evento (2.1: evento_svolto dell'addon Eventi con iscrizione, ai presenti
 * o a tutti gli iscritti) o da un elenco di email scritto a mano, dopo N giorni parte l'email "Com'è
 * andata?" con un link personale firmato e con scadenza a un modulo con le stelle. Le recensioni arrivano nel pannello da
 * approvare; quelle approvate escono nel widget "Recensioni dal sito" (stesso aspetto del widget Recensioni, senza dati
 * per Google sulle stelle). Documentazione: docs-sviluppo/addon/recensioni.md.
 */
$rec = __DIR__ . '/includes/recensioni.php';
$pag = fn(string $p) => 'addon-pagina.php?a=recensioni&p=' . $p;
return [
    'etichetta' => 'Raccolta recensioni',
    'versione' => '1.1.0',
    'descrizione' => 'Qualche giorno dopo il servizio (o dopo un evento) i clienti ricevono l\'email "Com\'è andata?" e lasciano stelle e due righe. Tu le approvi, rispondi, e quelle buone escono sul sito. A chi è contento puoi proporre anche Google.',
    'installa' => function () use ($rec) { require_once $rec; return rec_installa(); },
    'pannello' => [
        'recensioni' => __DIR__ . '/includes/admin-recensioni.php',
        'inviti' => __DIR__ . '/includes/admin-inviti.php',
        'impostazioni' => __DIR__ . '/includes/admin-impostazioni.php',
    ],
    'menu' => [[
        'nome' => 'Recensioni', 'url' => $pag('recensioni'), 'icona' => 'stella-piena', 'admin' => true, 'dopo' => 'Newsletter e form',
        'attivo' => [$pag('recensioni'), $pag('inviti'), $pag('impostazioni')],
        'sotto' => [
            ['nome' => 'Da approvare e approvate', 'url' => $pag('recensioni'), 'attivo' => [$pag('recensioni')]],
            ['nome' => 'Inviti', 'url' => $pag('inviti'), 'attivo' => [$pag('inviti')]],
            ['nome' => 'Impostazioni', 'url' => $pag('impostazioni'), 'attivo' => [$pag('impostazioni')]],
        ],
    ]],
    'widget' => [__DIR__ . '/includes/widget.php'],
    'cron_spesso' => [
        'recensioni' => function () use ($rec) { require_once $rec; return rec_cron(); },
    ],
    'eventi' => [
        'sito_percorso' => function (string $percorso, array $q) {
            if (!str_starts_with($percorso, '/recensione/')) return;
            require_once __DIR__ . '/includes/sito.php';
            rec_sito($percorso, $q);
        },
        // Dall'addon Prenotazioni, se è acceso. Spento, questi eventi non arrivano mai e qui non succede niente.
        'prenotazione_svolta' => function (array $p) use ($rec) { require_once $rec; rec_da_prenotazione($p); },
        'prenotazione_non_presentata' => function (?array $p) use ($rec) { if ($p) { require_once $rec; rec_annulla_rif('pren:' . (int)$p['id']); } },
        'prenotazione_annullata' => function (?array $p) use ($rec) { if ($p) { require_once $rec; rec_annulla_rif('pren:' . (int)$p['id']); } },
        'prenotazione_eliminata' => function (?array $p) use ($rec) { if ($p) { require_once $rec; rec_annulla_rif('pren:' . (int)$p['id'], true); } },
        // 2.1: dall'addon Eventi con iscrizione, se è acceso (stessa regola: spento, questi eventi non arrivano).
        'evento_svolto' => function (array $e, array $iscritti = []) use ($rec) { require_once $rec; rec_da_evento($e, $iscritti); },
        'iscrizione_presenza' => function (?array $i, ?array $e = null) use ($rec) { require_once $rec; rec_da_presenza($i, $e); },
        'iscrizione_cancellata' => function (?array $i, ?array $e = null) use ($rec) { if ($i && $e) { require_once $rec; rec_annulla_rif(rec_rif_evento($e, $i)); } },
        'evento_cambiato' => function (?array $e) use ($rec) { require_once $rec; rec_annulla_evento($e); },
        'evento_eliminato' => function (?array $e) use ($rec) { require_once $rec; rec_annulla_evento($e, true); },
    ],
];
