<?php
/**
 * Spedizione delle email del publisher: report del test, email del mattino, testi social.
 *
 * Con i dati SMTP compilati in Impostazioni, tab Posta, spedisce dal server di posta
 * (accesso con utente e password, SSL sulla 465 o STARTTLS sulla 587). Senza, usa mail() di PHP.
 * Stessa firma di mail(): chi la chiama passa destinatario, oggetto, corpo e intestazioni come prima.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/** Dati SMTP salvati nel pannello. Vuoto se il server di posta non è configurato. */
function posta_config(): array {
    $c = json_decode((string)get_setting('posta_smtp', ''), true);
    if (!is_array($c) || empty($c['host'])) return [];
    $c += ['utente' => '', 'porta' => 465, 'sicurezza' => 'ssl', 'password' => '', 'mittente' => '', 'nome' => (string)cms_impostazione('sito_nome', CMS_NOME)];
    if ((string)$c['mittente'] === '') $c['mittente'] = (string)$c['utente'];
    return $c;
}

/** L'ultimo errore di spedizione, per il pulsante di prova. */
function posta_errore(?string $nuovo = null): string {
    static $e = '';
    if ($nuovo !== null) $e = $nuovo;
    return $e;
}

function posta_invia(string $a, string $oggetto, string $corpo, string $intestazioni = ''): bool {
    posta_errore('');
    $c = posta_config();
    if (!$c) {
        $ok = @mail($a, $oggetto, $corpo, $intestazioni);
        if (!$ok) posta_errore('mail() di PHP ha rifiutato il messaggio: il server non spedisce posta da PHP. Compila i dati SMTP.');
        return $ok;
    }
    try {
        posta_smtp($c, $a, $oggetto, $corpo, $intestazioni);
        return true;
    } catch (Throwable $e) {
        posta_errore($e->getMessage());
        error_log('posta: ' . $e->getMessage());
        return false;
    }
}

/** Oggetto in UTF-8 per l'intestazione: se è già codificato resta com'è. */
function posta_oggetto(string $s): string {
    if (str_starts_with($s, '=?') || !preg_match('/[^\x20-\x7E]/', $s)) return $s;
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

function posta_smtp(array $c, string $a, string $oggetto, string $corpo, string $intestazioni): void {
    $host = (string)$c['host'];
    $porta = (int)$c['porta'];
    $ssl = $c['sicurezza'] === 'ssl';
    $errno = 0; $errstr = '';
    $s = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . $host . ':' . $porta, $errno, $errstr, 20);
    if (!$s) {
        $dettaglio = trim($errstr ?: (error_get_last()['message'] ?? ''));
        if ($ssl && ($errstr === '' || stripos($dettaglio, 'certificate') !== false)) {
            throw new RuntimeException("Connessione sicura a $host:$porta non riuscita: il certificato del server di posta non porta il nome \"$host\". "
                . "Su cPanel di solito va usato il nome del server indicato in Account email, Connetti dispositivi. ($dettaglio)");
        }
        throw new RuntimeException("Connessione a $host:$porta non riuscita ($dettaglio)");
    }
    stream_set_timeout($s, 20);

    $leggi = function (int ...$attesi) use ($s): string {
        $r = '';
        while (($riga = fgets($s, 1024)) !== false) {
            $r .= $riga;
            if (strlen($riga) < 4 || $riga[3] !== '-') break;
        }
        if (!in_array((int)substr($r, 0, 3), $attesi, true)) throw new RuntimeException('Il server di posta risponde: ' . trim($r ?: 'niente'));
        return $r;
    };
    $scrivi = function (string $riga, int ...$attesi) use ($s, $leggi): string {
        fwrite($s, $riga . "\r\n");
        return $leggi(...$attesi);
    };

    try {
        $leggi(220);
        $nome = (string)(parse_url(cms_url_sito(), PHP_URL_HOST) ?: 'localhost');
        $scrivi("EHLO $nome", 250);
        if ($c['sicurezza'] === 'tls') {
            $scrivi('STARTTLS', 220);
            if (!@stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException("STARTTLS non riuscito: il certificato del server di posta non porta il nome \"$host\". "
                    . 'Su cPanel di solito va usato il nome del server indicato in Account email, Connetti dispositivi. (' . (error_get_last()['message'] ?? '') . ')');
            }
            $scrivi("EHLO $nome", 250);
        }
        // Senza utente niente AUTH: un server che spedisce solo per la rete locale non la chiede.
        if ((string)$c['utente'] !== '') {
            $scrivi('AUTH LOGIN', 334);
            $scrivi(base64_encode((string)$c['utente']), 334);
            try {
                $scrivi(base64_encode((string)$c['password']), 235);
            } catch (RuntimeException $e) {
                throw new RuntimeException('Utente o password SMTP non accettati. ' . $e->getMessage());
            }
        }
        $mittente = (string)$c['mittente'];
        if ($mittente === '') throw new RuntimeException('Manca l\'indirizzo del mittente: compila Utente o Indirizzo del mittente in Impostazioni.');
        $scrivi("MAIL FROM:<$mittente>", 250);
        $scrivi("RCPT TO:<$a>", 250, 251);
        $scrivi('DATA', 354);

        // Intestazioni di chi chiama, tranne quelle che decide il server di posta.
        $tenute = [];
        foreach (preg_split('/\r?\n/', trim($intestazioni)) as $h) {
            if ($h === '' || preg_match('/^(From|To|Subject|Date|Message-ID|Content-Transfer-Encoding|MIME-Version):/i', $h)) continue;
            $tenute[] = $h;
        }
        if (!preg_grep('/^Content-Type:/i', $tenute)) $tenute[] = 'Content-Type: text/plain; charset=UTF-8';
        $testa = array_merge([
            'Date: ' . date('r'),
            'From: ' . posta_oggetto((string)$c['nome']) . " <$mittente>",
            "To: <$a>",
            'Subject: ' . posta_oggetto($oggetto),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($mittente, '@') ?: '@' . (parse_url(cms_url_sito(), PHP_URL_HOST) ?: 'localhost'), 1) . '>',
            'MIME-Version: 1.0',
        ], $tenute, ['Content-Transfer-Encoding: base64']);
        // Corpo in base64: righe corte e nessun punto a inizio riga da proteggere.
        fwrite($s, implode("\r\n", $testa) . "\r\n\r\n" . chunk_split(base64_encode($corpo), 76, "\r\n") . ".\r\n");
        $leggi(250);
        // Il messaggio è già accettato: un QUIT senza risposta non è un errore.
        try { $scrivi('QUIT', 221); } catch (RuntimeException $e) {}
    } finally {
        fclose($s);
    }
}

/**
 * L'indirizzo dove arrivano l'email del mattino, i testi social e gli avvisi dei moduli.
 * Impostazione `notifica_email`; se manca, legge quella salvata dal pannello di prima.
 */
function posta_notifica_email(): string {
    $e = trim((string)get_setting('notifica_email', ''));
    if ($e === '') $e = trim((string)((json_decode((string)get_setting('wp_api_config', ''), true) ?: [])['notification_email'] ?? ''));
    return $e;
}
