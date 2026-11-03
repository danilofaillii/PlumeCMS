<?php
/**
 * Addon Test e questionari (2.22.0). Test a punteggio, ad aree (con il grafico) e "che tipo sei" (profili), con domande
 * condizionali, invertite e con il peso, fasce e regole sul risultato, calcolo sempre sul server, link al risultato
 * firmato e senza dati personali, risultato per email con il consenso, iscrizione a una lista, innesco "ha completato il
 * test" delle automazioni con il filtro sul risultato, statistiche anonime. Pannello: Test (procedura in cinque passi) e
 * Statistiche. Documentazione: docs-sviluppo/addon/test.md. Il manifesto non fa query.
 */
$tst = __DIR__ . '/includes/test.php';
$pag = fn(string $p) => 'addon-pagina.php?a=test&p=' . $p;
return [
    'etichetta' => 'Test e questionari',
    'versione' => '1.0.0',
    'descrizione' => 'Test a punteggio, ad aree con il grafico, oppure "che tipo sei": una domanda per schermata, il risultato subito con il suo testo e un pulsante, e se la persona vuole lo riceve per email. Le risposte restano anonime.',
    'installa' => function () use ($tst) { require_once $tst; return test_installa(); },
    'pannello' => [
        'test' => __DIR__ . '/includes/admin-test.php',
        'statistiche' => __DIR__ . '/includes/admin-statistiche.php',
    ],
    'menu' => [[
        'nome' => 'Test', 'url' => $pag('test'), 'icona' => 'lista-spunte', 'admin' => true, 'dopo' => 'Newsletter e form',
        'attivo' => [$pag('test'), $pag('statistiche')],
        'sotto' => [
            ['nome' => 'Test', 'url' => $pag('test'), 'attivo' => [$pag('test')]],
            ['nome' => 'Statistiche', 'url' => $pag('statistiche'), 'attivo' => [$pag('statistiche')]],
        ],
    ]],
    'editor_contenitori' => [
        // La presentazione del test si compone con l'editor visuale (manuale tecnico 9.12).
        'test_presentazione' => function () use ($tst) { require_once $tst; return test_contenitore(); },
    ],
    'filtri' => [
        // "Scegli dal sito": i test nella finestra dei link del pannello (includes/collegamenti.php). Le bozze solo all'amministratore.
        'collegamenti_sito' => function (array $voci, string $q, bool $admin) use ($tst) {
            require_once $tst;
            foreach (test_tutti(!$admin) as $t) $voci[] = ['tipo' => 'test', 'tipo_nome' => 'Test', 'titolo' => (string)$t['nome'], 'percorso' => test_url($t), 'stato' => $t['stato'] === 'pubblicato' ? 'pubblicato' : 'bozza'];
            return $voci;
        },
        // Il widget "Riquadro in evidenza" (includes/editor-widget.php): l'elenco dei test e l'indirizzo di quello scelto.
        'riquadro_test_scelte' => function (array $scelte) use ($tst) {
            require_once $tst;
            foreach (test_tutti() as $t) $scelte[(string)$t['id']] = $t['nome'] . ($t['stato'] !== 'pubblicato' ? ' (bozza)' : '');
            return $scelte;
        },
        'riquadro_test_link' => function (string $link, int $id) use ($tst) {
            require_once $tst;
            $t = test_leggi($id);
            return $t && $t['stato'] === 'pubblicato' ? test_url($t) : $link;
        },
        // Le automazioni: l'innesco "Completa un test" e, per ogni test, i risultati per il filtro.
        'automazioni_inneschi' => function (array $nomi) { $nomi['test'] = 'Completa un test'; return $nomi; },
        'automazioni_inneschi_cosa' => function (array $cosa) use ($tst) {
            require_once $tst;
            foreach (test_tutti() as $t) { $cosa['test'][(string)$t['id']] = (string)$t['nome']; $cosa['_esiti']['test'][(string)$t['id']] = test_esiti_nomi($t); }
            return $cosa;
        },
        // I test fuori dalla sitemap? No: le pagine dei test pubblicati sono pagine vere. Le pagine dei risultati sono noindex.
    ],
    'eventi' => [
        'sito_percorso' => function (string $percorso, array $q) {
            if (!str_starts_with($percorso, '/fai-il-test/') && !str_starts_with($percorso, '/test-api/')) return; // TEST_PERCORSO (includes/test.php)
            require_once __DIR__ . '/includes/sito.php';
            test_sito($percorso, $q);
        },
        // Chi ha fatto il test e si è iscritto: alla conferma partono le automazioni "Completa un test".
        'newsletter_confermata' => function (array $esito) use ($tst) { require_once $tst; try { test_alla_conferma($esito); } catch (Throwable $e) { error_log('test: alla conferma: ' . $e->getMessage()); } },
    ],
];
