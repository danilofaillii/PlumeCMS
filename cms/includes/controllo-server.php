<?php
/**
 * Controlla il server (1.4.0): le prove vere, fatte dal server dove gira il sito, dietro la pagina del pannello
 * Impostazioni > Controlla il server (admin/controlla-server.php), e l'email di prova dei Primi passi della dashboard.
 *
 * Ogni controllo e ['titolo', 'esito' => 'ok' | 'avviso' | 'ko', 'detto' => cosa si e visto, 'fare' => cosa fare,
 * in parole per chi non e tecnico, 'link' => la pagina del pannello dove si fa, se c'e]. 'avviso' e per le cose che
 * servono solo ad alcune funzioni (senza curl, per esempio, il sito va lo stesso).
 *
 * Le richieste HTTP vanno al sito stesso (HTTPS, .htaccess) e al sito ufficiale degli aggiornamenti: timeout brevi,
 * nessun redirect seguito, al massimo 64 KB letti. Con il server di prova di PHP (php -S) le richieste al sito stesso
 * vogliono piu processi: PHP_CLI_SERVER_WORKERS=4 nell'ambiente, altrimenti il server aspetta se stesso.
 */

require_once __DIR__ . '/config.php';

/** Una GET senza redirect: [codice, corpo (al massimo $max byte), errore]. Certificato verificato con https. */
function server_http(string $url, int $timeout = 6, int $max = 65536): array {
    $ctx = stream_context_create([
        'http' => ['method' => 'GET', 'timeout' => $timeout, 'follow_location' => 0, 'max_redirects' => 0, 'ignore_errors' => true,
            'header' => 'User-Agent: ' . CMS_NOME . '/' . cms_versione() . " (controllo del server)\r\nConnection: close\r\n"],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
    ]);
    $vecchio = ini_get('default_socket_timeout');
    ini_set('default_socket_timeout', (string)$timeout);
    $h = @fopen($url, 'rb', false, $ctx);
    ini_set('default_socket_timeout', (string)$vecchio);
    if (!$h) {
        $e = (string)(error_get_last()['message'] ?? '');
        return [0, '', stripos($e, 'certificate') !== false || stripos($e, 'SSL') !== false ? 'certificato non valido' : 'nessuna risposta'];
    }
    $codice = 0;
    foreach ((array)(stream_get_meta_data($h)['wrapper_data'] ?? []) as $r) if (preg_match('~^HTTP/\S+\s+(\d{3})~', (string)$r, $m)) $codice = (int)$m[1];
    $corpo = (string)stream_get_contents($h, $max);
    fclose($h);
    return [$codice, $corpo, ''];
}

/** L'indirizzo della cartella del CMS sul web, senza barra finale (https://esempio.it/test). */
function server_base_web(): string {
    return rtrim(preg_replace('~/admin/?$~', '', cms_url_pannello()), '/');
}

/** Tutti i controlli, nell'ordine in cui la pagina li mostra: [gruppo => [controllo, ...]]. */
function server_controlli(): array {
    $g = [];
    $c = fn(string $titolo, string $esito, string $detto, string $fare = '', string $link = '') => compact('titolo', 'esito', 'detto', 'fare', 'link');

    // PHP e le estensioni
    $php = [];
    $php[] = $c('PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'ko', 'Il sito gira con PHP ' . PHP_VERSION . '.',
        version_compare(PHP_VERSION, '8.2.0', '>=') ? '' : 'Serve PHP 8.2 o più recente: in cPanel si cambia da "Seleziona versione PHP" (Select PHP Version).');
    $estensioni = [
        'pdo_mysql' => ['il database', true], 'mbstring' => ['i testi con gli accenti', true], 'openssl' => ['le connessioni sicure e le password', true],
        'json' => ['i dati dell\'editor', true], 'zlib' => ['le copie del sito', true], 'zip' => ['i pacchetti di aggiornamento e le copie', true],
        'dom' => ['la lettura dei testi incollati', false], 'gd' => ['il ridimensionamento delle immagini', false], 'curl' => ['Search Console e IndexNow', false],
    ];
    foreach ($estensioni as $e => [$aCosa, $serve]) {
        $ha = extension_loaded($e) || ($e === 'zip' && class_exists('ZipArchive')) || ($e === 'gd' && extension_loaded('imagick'));
        $php[] = $c('Estensione ' . $e, $ha ? 'ok' : ($serve ? 'ko' : 'avviso'), $ha ? 'Attiva: serve per ' . $aCosa . '.' : 'Non attiva: serve per ' . $aCosa . '.',
            $ha ? '' : 'Si accende dal pannello dell\'hosting (in cPanel: "Seleziona versione PHP", scheda Estensioni, spunta ' . $e . ').');
    }
    $mysql = '';
    try { $mysql = (string)db()->query('SELECT VERSION()')->fetchColumn(); } catch (Throwable $e) {}
    $php[] = $c('Database', $mysql !== '' ? 'ok' : 'ko', $mysql !== '' ? 'MySQL ' . $mysql . ' risponde.' : 'Il database non risponde.', $mysql !== '' ? '' : 'Controlla i dati del database in includes/config.local.php, o chiedi all\'hosting se il database è acceso.');
    $g['PHP e database'] = $php;

    // L'indirizzo del sito: HTTPS e risposta vera
    $sito = cms_url_sito();
    $ind = [];
    if (!str_starts_with($sito, 'https://')) {
        $ind[] = $c('HTTPS', 'ko', 'L\'indirizzo del sito è ' . $sito . ': senza https il browser lo segna come "non sicuro" e i motori di ricerca lo penalizzano.',
            'Attiva il certificato gratuito dall\'hosting (in cPanel: SSL/TLS Status, "Run AutoSSL"), poi scrivi l\'indirizzo con https:// nell\'installazione (impostazione sito_url) o chiedi a chi ti segue il sito.');
    } else {
        [$cod, , $err] = server_http($sito . '/robots.txt');
        $ind[] = $cod === 200
            ? $c('HTTPS', 'ok', 'Il sito risponde in https con un certificato valido (' . $sito . ').')
            : $c('HTTPS', 'ko', $err === 'certificato non valido' ? 'Il certificato di ' . $sito . ' non è valido o è scaduto.' : 'L\'indirizzo ' . $sito . ' non risponde come dovrebbe (' . ($cod ?: $err) . ').',
                'Controlla il certificato dal pannello dell\'hosting (in cPanel: SSL/TLS Status) e che il dominio punti a questo server.');
    }
    $g['Indirizzo del sito'] = $ind;

    // Il cron
    require_once __DIR__ . '/cron.php';
    $a = cron_attivo();
    $quando = fn(int $t) => $t ? date('d/m/Y \a\l\l\e H:i', $t) : 'mai';
    $cron = [];
    $cron[] = $c('Cron del mattino', $a['giornaliero'] ? 'ok' : 'ko', 'Ultimo giro arrivato dal cron: ' . $quando($a['giornaliero_quando']) . '.',
        $a['giornaliero'] ? '' : 'Metti la riga di cron-daily.php nei Processi cron dell\'hosting: la pagina Log cron la scrive gia pronta.', 'cron-log.php');
    $cron[] = $c('Cron della newsletter', $a['newsletter'] ? 'ok' : 'ko', 'Ultimo giro arrivato dal cron: ' . $quando($a['newsletter_quando']) . '.',
        $a['newsletter'] ? '' : 'Metti la riga di cron-newsletter.php (ogni 5 minuti) nei Processi cron dell\'hosting: senza, la newsletter e i flussi non partono.', 'cron-log.php');
    $g['Cron'] = $cron;

    // L'email
    $p = server_email_ultima();
    $g['Email'] = [$p === null
        ? $c('Email che partono', 'avviso', 'Non è ancora stata mandata un\'email di prova.', 'Premi "Manda un\'email di prova": arriva al tuo indirizzo di amministratore.')
        : ($p['ok'] ? $c('Email che partono', 'ok', 'L\'email di prova a ' . $p['a'] . ' è partita il ' . date('d/m/Y \a\l\l\e H:i', (int)$p['quando']) . '.', 'Se non ti è arrivata, guarda nello spam: se è lì, configura il server SMTP in Impostazioni, Configurazione email.', 'impostazioni.php')
                    : $c('Email che partono', 'ko', 'L\'ultima prova (' . date('d/m/Y H:i', (int)$p['quando']) . ') non è partita: ' . $p['errore'], 'In Impostazioni, Configurazione email, metti i dati SMTP della casella del tuo hosting (server, porta, utente, password).', 'impostazioni.php'))];

    // Il sito degli aggiornamenti
    require_once __DIR__ . '/versione-nuova.php';
    $uff = versione_sito();
    try {
        $v = versione_chiedi();
        $agg = $c('Sito degli aggiornamenti', 'ok', $uff . ' risponde: l\'ultima versione è la ' . $v['versione'] . ' (questa è la ' . cms_versione() . ').', '', 'aggiornamenti.php');
    } catch (Throwable $e) {
        $agg = $c('Sito degli aggiornamenti', 'avviso', $uff . ': ' . $e->getMessage(), 'Il sito funziona lo stesso: solo l\'avviso "c\'è una versione nuova" non arriva. Controlla l\'indirizzo in Impostazioni, Aggiornamenti; se è giusto, l\'hosting può bloccare le connessioni in uscita.', 'aggiornamenti.php');
    }
    $g['Aggiornamenti'] = [$agg];

    // Le cartelle in cui il CMS scrive, con una scrittura vera
    $cart = [];
    require_once __DIR__ . '/temi.php';
    foreach (['uploads' => [cms_uploads_dir(), 'le immagini e i file caricati'], 'logs' => [cms_radice() . '/logs', 'i registri del cron e degli errori'],
        'aggiornamenti' => [cms_radice() . '/aggiornamenti', 'i pacchetti e le copie del sito'], 'temi' => [temi_dir(), 'i temi caricati']] as $nome => [$dir, $aCosa]) {
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $f = $dir . '/.prova-scrittura-' . bin2hex(random_bytes(4));
        $ok = @file_put_contents($f, 'ok') === 2;
        @unlink($f);
        $cart[] = $c('Cartella ' . $nome . '/', $ok ? 'ok' : 'ko', ($ok ? 'Si scrive: ' : 'Non si scrive: ') . 'serve per ' . $aCosa . '.',
            $ok ? '' : 'Dal gestore dei file dell\'hosting dai alla cartella ' . $nome . '/ i permessi 755 (o 775).');
    }
    $g['Cartelle'] = $cart;

    // I divieti del .htaccess: una richiesta vera a file che dal web non si devono leggere
    $base = server_base_web();
    $segreto = 'controllo-' . bin2hex(random_bytes(8));
    $sonda = cms_radice() . '/logs/controllo-accesso.txt';
    @file_put_contents($sonda, $segreto);
    $prove = [['/includes/config.local.php', 'il file con la password del database'], ['/logs/controllo-accesso.txt', 'i registri'], ['/VERSIONE', 'i file del CMS']];
    $aperti = []; $senzaRisposta = 0;
    foreach ($prove as [$per, $cosa]) {
        [$cod, $corpo, $err] = server_http($base . $per, 5);
        if ($cod === 0) { $senzaRisposta++; continue; }
        if (!in_array($cod, [403, 404], true) || str_contains($corpo, $segreto)) $aperti[] = $per . ' (' . $cosa . ', risposta ' . $cod . ')';
    }
    @unlink($sonda);
    $g['Protezioni (.htaccess)'] = [$senzaRisposta === count($prove)
        ? $c('File vietati dal web', 'avviso', 'Il server non risponde alle richieste fatte da se stesso: le protezioni non si possono provare da qui.', 'Prova da un browser: ' . $base . '/includes/config.local.php deve dare "Forbidden" (403).')
        : ($aperti ? $c('File vietati dal web', 'ko', 'Si aprono dal web: ' . implode('; ', $aperti) . '.', 'Il file .htaccess della cartella del CMS manca o il server non lo legge (Nginx, o AllowOverride spento). Ricopialo da install/esempi/htaccess-radice.txt, oppure chiedi all\'hosting di attivare i file .htaccess.')
                   : $c('File vietati dal web', 'ok', 'config.local.php, i registri e i file del CMS rispondono "vietato" (403): il .htaccess funziona.'))];
    return $g;
}

/** L'ultima email di prova: ['quando', 'a', 'ok', 'errore'] oppure null. */
function server_email_ultima(): ?array {
    $p = json_decode((string)get_setting('posta_prova', ''), true);
    return is_array($p) && isset($p['quando']) ? $p : null;
}

/** Manda l'email di prova a $a e ricorda com'e andata. Ritorna [ok, frase da mostrare]. */
function server_email_prova(string $a): array {
    require_once __DIR__ . '/posta.php';
    if (!filter_var($a, FILTER_VALIDATE_EMAIL)) return [false, 'Il tuo utente non ha un indirizzo email valido: scrivilo in Utenti.'];
    $nome = (string)cms_impostazione('sito_nome', CMS_NOME);
    $ok = posta_invia($a, 'Prova di spedizione da ' . $nome, "Ciao,\n\nquesta è l'email di prova di " . $nome . ' (' . cms_url_sito() . ").\nSe la leggi, il sito spedisce le email: conferme della newsletter, avvisi dei form, password dimenticate.\n\nInviata il " . date('d/m/Y \a\l\l\e H:i') . ".\n");
    $errore = $ok ? '' : (posta_errore() ?: 'motivo sconosciuto');
    save_setting('posta_prova', json_encode(['quando' => time(), 'a' => $a, 'ok' => $ok, 'errore' => mb_substr($errore, 0, 300)], JSON_UNESCAPED_UNICODE));
    return $ok
        ? [true, 'Email di prova partita verso ' . $a . '. Controlla la casella (anche lo spam): se non arriva entro qualche minuto, configura il server SMTP in Impostazioni, Configurazione email.']
        : [false, 'L\'email di prova non è partita: ' . rtrim($errore, '. ') . '. In Impostazioni, Configurazione email, metti i dati SMTP della casella del tuo hosting.'];
}
