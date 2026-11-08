<?php
/**
 * Addon AI collegate (2.32.0). Il connettore fra il sito e una qualsiasi AI (Claude, ChatGPT, altre) via MCP, con
 * l'autorizzazione OAuth 2.1 data dall'amministratore nel pannello, oppure con una chiave personale e le chiamate REST
 * descritte in OpenAPI. L'AI legge il sito, fa l'audit SEO, crea e modifica SOLO bozze, propone modifiche ai contenuti
 * pubblicati (le approva l'amministratore) e importa foto con licenza libera. Non pubblica mai: non esiste un'opzione per
 * farlo. Manuale tecnico, capitolo 47. Il manifesto non fa query.
 */
$aic = __DIR__ . '/includes/ai.php';
$pag = fn(string $p) => 'addon-pagina.php?a=ai-collegate&p=' . $p;
return [
    'etichetta' => 'AI collegate',
    'versione' => '1.0.0',
    'descrizione' => 'Collega la tua AI al sito: legge le pagine, fa l\'audit SEO, scrive bozze complete con i widget dell\'editor e trova foto con licenza libera. Non pubblica mai da sola: le bozze le pubblichi tu, e le modifiche ai contenuti già online arrivano come proposte da approvare.',
    'installa' => function () use ($aic) { require_once $aic; return aic_installa(); },
    'pannello' => [
        'collegate' => __DIR__ . '/includes/admin-collegate.php',
        'proposte' => __DIR__ . '/includes/admin-proposte.php',
        'registro' => __DIR__ . '/includes/admin-registro.php',
        'impostazioni' => __DIR__ . '/includes/admin-impostazioni.php',
        'consenso' => __DIR__ . '/includes/admin-consenso.php',
    ],
    'menu' => [[
        'nome' => 'AI collegate', 'url' => $pag('collegate'), 'icona' => 'scintilla', 'admin' => true, 'dopo' => 'SEO e AEO',
        'conta' => function () use ($aic) { require_once $aic; return aic_proposte_in_attesa(); },
        'attivo' => [$pag('collegate'), $pag('proposte'), $pag('registro'), $pag('impostazioni'), $pag('consenso')],
        'sotto' => [
            ['nome' => 'Collegamenti', 'url' => $pag('collegate'), 'attivo' => [$pag('collegate'), $pag('consenso')]],
            ['nome' => 'Proposte', 'url' => $pag('proposte'), 'attivo' => [$pag('proposte')], 'conta' => function () use ($aic) { require_once $aic; return aic_proposte_in_attesa(); }],
            ['nome' => 'Registro', 'url' => $pag('registro'), 'attivo' => [$pag('registro')]],
            ['nome' => 'Impostazioni', 'url' => $pag('impostazioni'), 'attivo' => [$pag('impostazioni')]],
        ],
    ]],
    'cron' => [
        'AI collegate: pulizia' => function () use ($aic) { require_once $aic; aic_pulisci(); return 'richieste, codici e token scaduti tolti'; },
    ],
    'eventi' => [
        // Il server MCP, l'OAuth, l'OpenAPI e i .well-known: tutti dal front controller del sito (anche in una cartella).
        'sito_percorso' => function (string $percorso, array $q) {
            if (!str_starts_with($percorso, '/api/mcp') && !str_starts_with($percorso, '/.well-known/')) return;
            require_once __DIR__ . '/includes/mcp.php';
            aic_sito_percorso($percorso, $q);
        },
    ],
];
