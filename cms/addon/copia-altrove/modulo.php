<?php
/**
 * Addon "Copia del sito altrove" (2.0.0): ogni notte la copia del sito (includes/copia-sito.php, la stessa di
 * Impostazioni > Copia del sito) parte verso un altro spazio: Google Drive o Dropbox (2.1, collegati con OAuth 2), FTP,
 * FTPS, SFTP (se il server ha l'estensione ssh2) o WebDAV (Nextcloud, e molti spazi cloud). Sulla destinazione restano
 * le ultime N copie.
 * Documentazione tecnica: docs-sviluppo/addon/copia-altrove.md.
 */
return [
    'etichetta' => 'Copia del sito altrove',
    'versione' => '1.1.0',
    'descrizione' => 'Ogni notte una copia di tutto il sito parte verso un altro spazio: Google Drive, Dropbox, FTP, FTPS, SFTP o WebDAV (come Nextcloud). Se il server si rompe, la copia è al sicuro da un\'altra parte.',
    'installa' => function () { require_once __DIR__ . '/includes/copia-altrove.php'; return copia_altrove_installa(); },
    'menu' => [[
        'nome' => 'Copia altrove', 'url' => 'addon-pagina.php?a=copia-altrove&p=destinazione', 'admin' => true,
        'gruppo' => 'Impostazioni', 'dopo' => 'Copia del sito', 'attivo' => ['addon-pagina.php?a=copia-altrove&p=destinazione'],
    ]],
    'pannello' => ['destinazione' => __DIR__ . '/includes/admin-destinazione.php'],
    // 2.1: il ritorno da Google o da Dropbox (admin/oauth-ritorno.php, state "copia-altrove.<...>").
    'oauth_ritorno' => function (array $q) { require_once __DIR__ . '/includes/copia-altrove.php'; copia_altrove_oauth_ritorno($q); },
    'cron' => [
        'Copia del sito altrove' => function () { require_once __DIR__ . '/includes/copia-altrove.php'; return copia_altrove_notte(); },
    ],
];
