<?php
/**
 * Addon Vetrina (2.0.0): un piccolo negozio. Prodotti con foto, varianti e scorte, categorie, carrello senza account,
 * richiesta d'ordine con bonifico, pagamento alla consegna o link di pagamento (PayPal.me, Stripe Payment Link).
 * Niente chiavi segrete di servizi di pagamento: l'attività conferma il pagamento a mano dal pannello.
 * Motore in includes/vetrina.php, pagine del sito in includes/sito.php, pannello in includes/admin-*.php.
 * Documentazione: docs-sviluppo/addon/vetrina.md (tecnico) e vetrina-uso.md (manuale d'uso).
 */
return [
    'etichetta' => 'Vetrina prodotti',
    'versione' => '1.0.0',
    'descrizione' => 'Un piccolo negozio: prodotti con foto, prezzi e varianti, carrello e richieste d\'ordine con bonifico, pagamento alla consegna o link di pagamento. Gli ordini arrivano nel pannello e per email.',
    'installa' => function () {
        require_once __DIR__ . '/includes/vetrina.php';
        return vt_installa();
    },
    'menu' => [
        ['nome' => 'Vetrina', 'url' => 'addon-pagina.php?a=vetrina&p=ordini', 'icona' => 'carrello', 'admin' => true, 'dopo' => 'Media',
            'attivo' => ['addon-pagina.php?a=vetrina&p=ordini', 'addon-pagina.php?a=vetrina&p=prodotti', 'addon-pagina.php?a=vetrina&p=prodotto', 'addon-pagina.php?a=vetrina&p=categorie', 'addon-pagina.php?a=vetrina&p=impostazioni'],
            'sotto' => [
                ['nome' => 'Ordini', 'url' => 'addon-pagina.php?a=vetrina&p=ordini', 'attivo' => ['addon-pagina.php?a=vetrina&p=ordini']],
                ['nome' => 'Prodotti', 'url' => 'addon-pagina.php?a=vetrina&p=prodotti', 'attivo' => ['addon-pagina.php?a=vetrina&p=prodotti', 'addon-pagina.php?a=vetrina&p=prodotto']],
                ['nome' => 'Categorie', 'url' => 'addon-pagina.php?a=vetrina&p=categorie', 'attivo' => ['addon-pagina.php?a=vetrina&p=categorie']],
                ['nome' => 'Impostazioni del negozio', 'url' => 'addon-pagina.php?a=vetrina&p=impostazioni', 'attivo' => ['addon-pagina.php?a=vetrina&p=impostazioni']],
            ]],
    ],
    'pannello' => [
        'ordini' => __DIR__ . '/includes/admin-ordini.php',
        'prodotti' => __DIR__ . '/includes/admin-prodotti.php',
        'prodotto' => __DIR__ . '/includes/admin-prodotto.php',
        'categorie' => __DIR__ . '/includes/admin-categorie.php',
        'impostazioni' => __DIR__ . '/includes/admin-impostazioni.php',
    ],
    'widget' => [__DIR__ . '/includes/widget.php'],
    'eventi' => [
        // Gli indirizzi del negozio (/negozio/ e quelli sotto): la vetrina risponde e chiude.
        'sito_percorso' => function (string $percorso, array $q) {
            require_once __DIR__ . '/includes/vetrina.php';
            if (!vt_mio_percorso($percorso)) return;
            require_once __DIR__ . '/includes/sito.php';
            vt_sito_rispondi($percorso, $q);
        },
    ],
    'filtri' => [
        // Le schede dei prodotti nella sitemap del sito.
        'sitemap_voci' => function (array $voci) {
            require_once __DIR__ . '/includes/vetrina.php';
            try { return array_merge($voci, vt_sitemap_voci()); } catch (Throwable $e) { error_log('vetrina sitemap: ' . $e->getMessage()); return $voci; }
        },
        'dashboard_numeri' => function (array $n) {
            require_once __DIR__ . '/includes/vetrina.php';
            try {
                $n[] = ['valore' => vt_ordini_conta('nuovo'), 'etichetta' => 'ordini nuovi'];
            } catch (Throwable $e) {}
            return $n;
        },
    ],
    'cron' => [
        'vetrina' => function () {
            require_once __DIR__ . '/includes/vetrina.php';
            return vt_pulizia();
        },
    ],
];
