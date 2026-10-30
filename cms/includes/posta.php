<?php
/**
 * Spedizione delle email del sito: email del mattino, avvisi dei form, newsletter, email di prova.
 *
 * Con i dati SMTP compilati in Impostazioni, tab Posta, spedisce dal server di posta
 * (accesso con utente e password, SSL sulla 465 o STARTTLS sulla 587). Senza, usa mail() di PHP.
 * Stessa firma di mail(): chi la chiama passa destinatario, oggetto, corpo e intestazioni come prima, più il tipo
 * dell'email (campagna, flusso, conferma...) per il registro.
 *
 * 1.11.0:
 *   - ogni spedizione lascia una riga in <prefisso>posta_registro (quando, tipo, esito, errore; niente indirizzi):
 *     il limite orario e giornaliero della newsletter conta tutte le email del CMS, non solo le campagne;
 *   - con mail() di PHP l'email parte con il From del mittente di Posta in uscita (o noreply@ del dominio del sito)
 *     e il Return-Path (-f); con SMTP come prima;
 *   - ogni email HTML esce in multipart/alternative, con la parte di testo semplice generata dall'HTML;
 *   - un rifiuto del destinatario al RCPT TO si riconosce: posta_esito() dice 'rimbalzo' (5xx, definitivo) o
 *     'temporaneo' (4xx); gli altri errori (connessione, accesso, server) sono 'errore'.
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

/** L'esito dell'ultima spedizione: 'ok', 'rimbalzo' (destinatario rifiutato, 5xx), 'temporaneo' (rifiutato per ora, 4xx), 'errore'. */
function posta_esito(?string $nuovo = null): string {
    static $e = '';
    if ($nuovo !== null) $e = $nuovo;
    return $e;
}

/** Il destinatario rifiutato dal server di posta al RCPT TO. Il codice dell'eccezione è quello SMTP (550, 451...). */
class PostaRifiutata extends RuntimeException {}

// ------------------------------------------------------------
// Registro di tutte le email spedite (1.11.0)
// ------------------------------------------------------------

function posta_registro_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    $sql = 'CREATE TABLE IF NOT EXISTS ' . tbl('posta_registro') . " (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `quando` DATETIME NOT NULL,
        `tipo` VARCHAR(20) NOT NULL DEFAULT 'altro',
        `esito` VARCHAR(12) NOT NULL DEFAULT 'ok',
        `errore` VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (`id`), KEY `quando` (`quando`, `esito`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    $impronta = md5($sql . DB_NAME);
    if (get_setting('posta_registro_impronta', '') !== $impronta) {
        db()->exec($sql);
        save_setting('posta_registro_impronta', $impronta);
    }
}

/** Una riga nel registro. Un errore del database non ferma la posta. */
function posta_registra(string $tipo, string $esito, string $errore = ''): void {
    try {
        posta_registro_schema();
        $tipo = preg_replace('/[^a-z0-9_]/', '', strtolower($tipo)) ?: 'altro';
        db()->prepare('INSERT INTO ' . tbl('posta_registro') . ' (quando, tipo, esito, errore) VALUES (?, ?, ?, ?)')
            ->execute([date('Y-m-d H:i:s'), substr($tipo, 0, 20), $esito, mb_substr($errore, 0, 255)]);
    } catch (Throwable $e) {
        error_log('posta registro: ' . $e->getMessage());
    }
}

/** Quante email sono partite davvero negli ultimi $secondi, di ogni tipo (o dei tipi dati). */
function posta_inviate(int $secondi, array $tipi = []): int {
    try {
        posta_registro_schema();
        $par = [date('Y-m-d H:i:s', time() - $secondi)];
        $w = '';
        if ($tipi) { $w = ' AND tipo IN (' . implode(',', array_fill(0, count($tipi), '?')) . ')'; $par = array_merge($par, array_values($tipi)); }
        $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('posta_registro') . " WHERE quando > ? AND esito = 'ok'$w");
        $st->execute($par);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Toglie dal registro le righe più vecchie di $giorni. Ritorna quante. */
function posta_pulisci_registro(int $giorni = 60): int {
    posta_registro_schema();
    $st = db()->prepare('DELETE FROM ' . tbl('posta_registro') . ' WHERE quando < ?');
    $st->execute([date('Y-m-d H:i:s', time() - $giorni * 86400)]);
    return $st->rowCount();
}

// ------------------------------------------------------------
// Spedizione
// ------------------------------------------------------------

/**
 * Il mittente delle email: [indirizzo, nome]. L'indirizzo del mittente (o l'utente) di Posta in uscita, anche senza
 * server SMTP; se manca, noreply@ il dominio del sito. L'indirizzo è '' se non ne esce uno valido (sito su un IP).
 */
function posta_mittente(): array {
    $c = json_decode((string)get_setting('posta_smtp', ''), true);
    $c = is_array($c) ? $c : [];
    $nome = trim((string)($c['nome'] ?? '')) ?: (string)cms_impostazione('sito_nome', CMS_NOME);
    $nome = str_replace(['"', "\r", "\n"], '', $nome);
    $ind = trim((string)($c['mittente'] ?? '')) ?: trim((string)($c['utente'] ?? ''));
    if (!filter_var($ind, FILTER_VALIDATE_EMAIL)) {
        $host = preg_replace('/^www\./i', '', (string)(parse_url(cms_url_sito(), PHP_URL_HOST) ?: ''));
        $ind = filter_var('noreply@' . $host, FILTER_VALIDATE_EMAIL) ? 'noreply@' . $host : '';
    }
    return [$ind, $nome];
}

/** Il testo semplice di un'email HTML: titoli e paragrafi a capo, link con l'indirizzo fra parentesi, niente immagini né parti nascoste. */
function posta_testo_da_html(string $html): string {
    $t = preg_replace('~<(head|style|script|title)\b[^>]*>.*?</\1>~is', '', $html);
    // Il testo di anteprima nascosto e il pixel di apertura non servono in chiaro.
    $t = preg_replace('~<div[^>]*display:\s*none[^>]*>.*?</div>~is', '', $t);
    $t = preg_replace('~<img\b[^>]*>~i', '', $t);
    $t = preg_replace_callback('~<a\b[^>]*href="([^"]*)"[^>]*>(.*?)</a>~is', function ($m) {
        $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $testo = trim(strip_tags($m[2]));
        if ($url === '' || str_starts_with($url, '#')) return $testo;
        if ($testo === '' || $testo === $url || html_entity_decode($testo, ENT_QUOTES | ENT_HTML5, 'UTF-8') === $url) return ' ' . $url . ' ';
        return $testo . ' (' . $url . ')';
    }, $t);
    $t = preg_replace('~<br\s*/?>~i', "\n", $t);
    $t = preg_replace('~<li\b[^>]*>~i', "\n- ", $t);
    $t = preg_replace('~</(p|h[1-6]|div|tr|table|ul|ol|blockquote)>~i', "\n\n", $t);
    $t = preg_replace('~</t[dh]>~i', ' ', $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace(["\u{200C}", "\u{00A0}"], ['', ' '], $t);
    $t = preg_replace('/[ \t]+/', ' ', $t);
    $t = preg_replace('/ *\n */', "\n", $t);
    $t = preg_replace('/\n{3,}/', "\n\n", $t);
    return trim($t) . "\n";
}

/**
 * Le intestazioni e il corpo MIME. Dalle intestazioni di chi chiama si tengono le sue (Reply-To, List-Unsubscribe...),
 * non quelle del contenuto. Un corpo HTML diventa multipart/alternative con la parte di testo; tutto in base64.
 * Ritorna [intestazioni tenute, intestazioni del contenuto, corpo], con a capo CRLF.
 */
function posta_mime(string $corpo, string $intestazioni): array {
    $tenute = []; $tipo = '';
    foreach (preg_split('/\r?\n/', trim($intestazioni)) as $h) {
        if ($h === '') continue;
        if (preg_match('/^Content-Type:\s*(.*)$/i', $h, $m)) { $tipo = trim($m[1]); continue; }
        if (preg_match('/^(Content-Transfer-Encoding|MIME-Version):/i', $h)) continue;
        $tenute[] = $h;
    }
    $b64 = fn(string $s) => rtrim(chunk_split(base64_encode($s), 76, "\r\n"));
    if ($tipo === '') $tipo = 'text/plain; charset=UTF-8';
    if (stripos($tipo, 'text/html') === 0) {
        $confine = 'plume-' . bin2hex(random_bytes(12));
        $parti = "Messaggio in formato MIME.\r\n"
            . "--$confine\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64(posta_testo_da_html($corpo)) . "\r\n"
            . "--$confine\r\nContent-Type: $tipo\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($corpo) . "\r\n"
            . "--$confine--\r\n";
        return [$tenute, ['MIME-Version: 1.0', 'Content-Type: multipart/alternative; boundary="' . $confine . '"'], $parti];
    }
    return [$tenute, ['MIME-Version: 1.0', 'Content-Type: ' . $tipo, 'Content-Transfer-Encoding: base64'], $b64($corpo) . "\r\n"];
}

/**
 * Spedisce un'email. $tipo finisce nel registro (campagna, flusso, conferma, avviso_form, prova, social...).
 * Dopo la chiamata posta_esito() dice com'è andata e posta_errore() perché.
 */
function posta_invia(string $a, string $oggetto, string $corpo, string $intestazioni = '', string $tipo = 'altro'): bool {
    posta_errore('');
    posta_esito('');
    $c = posta_config();
    if (!$c) {
        [$tenute, $contenuto, $testo] = posta_mime($corpo, $intestazioni);
        [$mittente, $nome] = posta_mittente();
        // Il From di chi chiama vince; senza, quello del sito. To e Subject li scrive mail().
        $tenute = array_values(array_filter($tenute, fn($h) => !preg_match('/^(To|Subject):/i', $h)));
        if ($mittente !== '' && !preg_grep('/^From:/i', $tenute)) array_unshift($tenute, 'From: ' . posta_oggetto($nome) . " <$mittente>");
        $testa = implode("\r\n", array_merge($tenute, $contenuto));
        $ok = false;
        // Return-Path con -f: alcuni server lo rifiutano, e allora si riprova senza.
        if ($mittente !== '' && preg_match('/^[A-Za-z0-9._%+@-]+$/', $mittente)) $ok = @mail($a, posta_oggetto($oggetto), $testo, $testa, '-f' . $mittente);
        if (!$ok) $ok = @mail($a, posta_oggetto($oggetto), $testo, $testa);
        if (!$ok) posta_errore('mail() di PHP ha rifiutato il messaggio: il server non spedisce posta da PHP. Compila i dati SMTP.');
        posta_esito($ok ? 'ok' : 'errore');
        posta_registra($tipo, posta_esito(), posta_errore());
        return $ok;
    }
    try {
        posta_smtp($c, $a, $oggetto, $corpo, $intestazioni);
        posta_esito('ok');
        posta_registra($tipo, 'ok');
        return true;
    } catch (Throwable $e) {
        posta_errore($e->getMessage());
        posta_esito($e instanceof PostaRifiutata ? ($e->getCode() >= 500 ? 'rimbalzo' : 'temporaneo') : 'errore');
        posta_registra($tipo, posta_esito(), $e->getMessage());
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
        if (!in_array((int)substr($r, 0, 3), $attesi, true)) throw new RuntimeException('Il server di posta risponde: ' . trim($r ?: 'niente'), (int)substr($r, 0, 3));
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
        try {
            $scrivi("RCPT TO:<$a>", 250, 251);
        } catch (RuntimeException $e) {
            // Il destinatario rifiutato: 5xx per sempre (indirizzo che non esiste), 4xx per ora. Gli altri codici: errore del server.
            $cod = $e->getCode();
            if ($cod >= 400 && $cod < 600) throw new PostaRifiutata('Destinatario rifiutato. ' . $e->getMessage(), $cod);
            throw $e;
        }
        $scrivi('DATA', 354);

        // Intestazioni di chi chiama, tranne quelle che decide il server di posta.
        [$tenute, $contenuto, $testo] = posta_mime($corpo, $intestazioni);
        $tenute = array_values(array_filter($tenute, fn($h) => !preg_match('/^(From|To|Subject|Date|Message-ID):/i', $h)));
        $testa = array_merge([
            'Date: ' . date('r'),
            'From: ' . posta_oggetto((string)$c['nome']) . " <$mittente>",
            "To: <$a>",
            'Subject: ' . posta_oggetto($oggetto),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($mittente, '@') ?: '@' . (parse_url(cms_url_sito(), PHP_URL_HOST) ?: 'localhost'), 1) . '>',
        ], $tenute, $contenuto);
        // Corpo in base64 (anche le parti): righe corte e nessun punto a inizio riga da proteggere.
        fwrite($s, implode("\r\n", $testa) . "\r\n\r\n" . $testo . ".\r\n");
        $leggi(250);
        // Il messaggio è già accettato: un QUIT senza risposta non è un errore.
        try { $scrivi('QUIT', 221); } catch (RuntimeException $e) {}
    } finally {
        fclose($s);
    }
}

/**
 * L'indirizzo dove arrivano l'email del mattino e gli avvisi dei form.
 * Impostazione `notifica_email`; se manca, legge quella salvata dal pannello di prima.
 */
function posta_notifica_email(): string {
    $e = trim((string)get_setting('notifica_email', ''));
    if ($e === '') $e = trim((string)((json_decode((string)get_setting('wp_api_config', ''), true) ?: [])['notification_email'] ?? ''));
    return $e;
}
