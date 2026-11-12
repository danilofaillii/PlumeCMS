<?php
/**
 * Le automazioni (2.15.0), la parte che non serve a ogni pixel: azioni (liste, attributi, webhook), anniversari e
 * condizioni di uscita del giro, modelli di automazione, statistiche per passo, attività e registro, design a blocchi di
 * un passo Email. Il grafo e il giro stanno in includes/newsletter-flussi.php, che chiama questo file con function_exists
 * (opcache: per qualche secondo dopo l'aggiornamento può dare uno dei due file di prima).
 *
 * Webhook: POST JSON verso un indirizzo https sulla porta 443. L'host si risolve una volta, ogni IP si controlla (niente
 * 127.0.0.1, 169.254.169.254, reti private, riservate, condivise dei provider, IPv6 locali e IPv4 dentro IPv6) e la
 * connessione si fissa su quell'IP (CURLOPT_RESOLVE: niente DNS rebinding). I rimandi non si seguono mai. Tempo massimo
 * 5 secondi, nessun proxy, della risposta si legge e si tiene solo l'inizio (200 caratteri, senza tag).
 * Solo per le prove in locale: CMS_WEBHOOK_LOCALE nel config.local.php ("host:porta" separati da virgole) ammette http e
 * gli IP interni per quegli host e porte esatti. Spenta di partenza: su un sito vero non si definisce mai.
 */

const NL_AUT_REGISTRO_GIORNI = 90;
const NL_AUT_WEBHOOK_TEMPO = 5;

// ------------------------------------------------------------
// Azioni
// ------------------------------------------------------------

/** Esegue un'azione di un passo per il contatto del cammino $r. Ritorna la frase per il registro. */
function nl_aut_azione(array $f, array $nodo, array $r): string {
    $d = $nodo['dati']; $id = (int)$r['iscritto_id'];
    switch ($nodo['tipo']) {
        case 'lista_aggiungi':
            if (newsletter_nome_lista($d['lista']) === '') return 'la lista non c\'è più: niente da fare';
            newsletter_in_lista($id, $d['lista']);
            return 'aggiunto alla lista ' . newsletter_nome_lista($d['lista']);
        case 'lista_togli':
            $n = newsletter_togli_da_liste([$id], [$d['lista']]);
            return $n ? 'tolto dalla lista ' . newsletter_nome_lista($d['lista']) : 'non era nella lista';
        case 'attributo':
            $e = newsletter_valori_salva($id, [$d['nome'] => newsletter_4byte_fuori($d['valore'])]);
            return $d['nome'] . ($e['cambiati'] ? ' = ' . ($d['valore'] === '' ? '(vuoto)' : newsletter_4byte_fuori($d['valore'])) : ': già così');
        case 'webhook':
            return nl_aut_webhook($f, $nodo, $r);
    }
    return '';
}

/** Gli host:porta delle prove in locale (CMS_WEBHOOK_LOCALE), in minuscolo. */
function nl_aut_webhook_locali(): array {
    return defined('CMS_WEBHOOK_LOCALE') ? array_values(array_filter(array_map(fn($x) => strtolower(trim($x)), explode(',', (string)CMS_WEBHOOK_LOCALE)))) : [];
}

/** Un IP interno? Privati, locali, riservati, link-local (169.254.169.254), la rete condivisa dei provider (100.64.0.0/10) e
 *  gli IPv4 scritti dentro un IPv6 (::ffff:127.0.0.1, ::127.0.0.1). */
function nl_aut_ip_interno(string $ip): bool {
    if (!function_exists('sicurezza_ip_privato')) require_once __DIR__ . '/sicurezza-html.php';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $b = (string)inet_pton($ip);
        if (strlen($b) === 16 && (str_starts_with($b, str_repeat("\0", 10) . "\xff\xff") || str_starts_with($b, str_repeat("\0", 12)))) return true;
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP) || sicurezza_ip_privato($ip)) return true;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $n = ip2long($ip);
        foreach ([['100.64.0.0', 10], ['0.0.0.0', 8], ['192.0.0.0', 24], ['198.18.0.0', 15]] as [$rete, $bit]) {
            if (($n & (-1 << (32 - $bit))) === (ip2long($rete) & (-1 << (32 - $bit)))) return true;
        }
    }
    return false;
}

/**
 * Controlla l'indirizzo di un webhook. Ritorna ['errore' => '' o il motivo in parole semplici, 'host', 'porta', 'ip'].
 * $risolvi false: solo la forma (per un nome che adesso non si trova basta la forma; al momento dell'invio si risolve di nuovo).
 */
function nl_aut_webhook_controlla(string $url, bool $risolvi = true): array {
    $no = fn($e) => ['errore' => $e, 'host' => '', 'porta' => 0, 'ip' => ''];
    if ($url === '') return $no('scrivi l\'indirizzo che deve ricevere i dati (comincia con https://).');
    $p = parse_url($url);
    if (!is_array($p) || empty($p['host']) || !in_array(strtolower((string)($p['scheme'] ?? '')), ['http', 'https'], true)) return $no('l\'indirizzo non è valido: scrivilo intero, per esempio https://servizio.esempio.it/ricevi');
    if (isset($p['user']) || isset($p['pass'])) return $no('l\'indirizzo non può contenere nome e password.');
    $schema = strtolower($p['scheme']);
    $host = strtolower(trim($p['host'], '[]'));
    $porta = (int)($p['port'] ?? ($schema === 'https' ? 443 : 80));
    $locale = in_array("$host:$porta", nl_aut_webhook_locali(), true);
    if (!$locale && $schema !== 'https') return $no('serve un indirizzo https: i dati del contatto non viaggiano in chiaro.');
    if (!$locale && $porta !== 443) return $no('si chiamano solo indirizzi sulla porta normale di https (443): togli il ":' . $porta . '".');
    if (filter_var($host, FILTER_VALIDATE_IP)) $ips = [$host];
    else {
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/', $host) || strlen($host) > 253) return $no('il nome del servizio contiene caratteri non ammessi.');
        if (!$locale && ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal'))) return $no('l\'indirizzo porta a questo server o alla sua rete: per sicurezza si chiamano solo servizi pubblici su internet.');
        // Un nome fatto solo di numeri (2130706433, 0x7f000001, 127.1) è un indirizzo IP scritto in un altro modo: no.
        if (!$locale && !preg_match('/\.[a-z][a-z0-9-]*[a-z0-9]$/', $host)) return $no('scrivi il nome del servizio (per esempio servizio.esempio.it), non un numero.');
        $ips = $risolvi ? (@gethostbynamel($host) ?: []) : [];
    }
    if (!$locale) foreach ($ips as $ip) if (nl_aut_ip_interno($ip)) return $no('l\'indirizzo porta a una rete interna (' . $ip . '): per sicurezza si chiamano solo servizi pubblici su internet.');
    return ['errore' => '', 'host' => $host, 'porta' => $porta, 'ip' => $ips[0] ?? ''];
}

/** Chiama il webhook di un passo per il contatto del cammino $r. Ritorna la frase per il registro (esito, mai più di 200 caratteri della risposta). */
function nl_aut_webhook(array $f, array $nodo, array $r): string {
    $url = (string)$nodo['dati']['url'];
    $c = nl_aut_webhook_controlla($url, true);
    if ($c['errore'] !== '') return 'webhook non chiamato: ' . $c['errore'];
    if ($c['ip'] === '') return 'webhook non chiamato: il nome ' . $c['host'] . ' non si trova.';
    if (!function_exists('curl_init')) return 'webhook non chiamato: sul server manca l\'estensione curl di PHP.';
    $corpo = json_encode(['evento' => 'automazione', 'automazione' => ['id' => (int)$f['id'], 'nome' => $f['nome']], 'passo' => (int)$nodo['id'],
        'contatto' => ['id' => (int)$r['iscritto_id'], 'email' => (string)$r['email'], 'nome' => newsletter_4byte_fuori((string)$r['nome']), 'cognome' => newsletter_4byte_fuori((string)($r['cognome'] ?? ''))],
        'quando' => date('c')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $letto = ''; $codice = 0;
    $h = curl_init();
    curl_setopt_array($h, [
        CURLOPT_URL => $url, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $corpo,
        CURLOPT_RESOLVE => [$c['host'] . ':' . $c['porta'] . ':' . (str_contains($c['ip'], ':') ? '[' . $c['ip'] . ']' : $c['ip'])],
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*', CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => NL_AUT_WEBHOOK_TEMPO,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8', 'User-Agent: ' . CMS_NOME . '/' . cms_versione() . ' (automazioni)',
            'X-Plume-Firma: sha256=' . hash_hmac('sha256', $corpo, moduli_segreto())],
        CURLOPT_WRITEFUNCTION => function ($x, $pezzo) use (&$letto) { $letto .= $pezzo; return strlen($letto) > 2048 ? -1 : strlen($pezzo); },
    ]);
    $ok = curl_exec($h);
    $err = curl_errno($h);
    $codice = (int)curl_getinfo($h, CURLINFO_RESPONSE_CODE);
    curl_close($h);
    if ($codice >= 300 && $codice < 400) return "webhook: il servizio rimanda a un altro indirizzo (risposta $codice): per sicurezza non lo seguo.";
    if ($codice === 0) return 'webhook non riuscito: ' . match (true) {
        $err === CURLE_OPERATION_TIMEDOUT => 'il servizio non ha risposto entro ' . NL_AUT_WEBHOOK_TEMPO . ' secondi.',
        in_array($err, [35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91], true) => 'il certificato HTTPS del servizio non è valido.',
        default => 'il servizio non risponde.',
    };
    $testo = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags(mb_convert_encoding(substr($letto, 0, 2048), 'UTF-8', 'UTF-8')))), 0, 120);
    return 'webhook: risposta ' . $codice . ($codice < 300 ? ' (ricevuto)' : ' (errore del servizio)') . ($testo !== '' ? ': ' . $testo : '');
}

// ------------------------------------------------------------
// Giro: anniversari e condizioni di uscita
// ------------------------------------------------------------

/** Prima dei cammini: chi compie l'anniversario entra, chi soddisfa una condizione di uscita esce. Righe di log. */
function nl_aut_giro_prima(): array {
    $log = [];
    $n = nl_aut_anniversari();
    if ($n) $log[] = "automazioni: $n anniversari";
    $n = nl_aut_uscite();
    if ($n) $log[] = "automazioni: $n usciti per le condizioni di uscita";
    return $log;
}

/** L'innesco "anniversario": la data dell'attributo (giorno e mese) fra N giorni è oggi. Una volta l'anno. Ritorna quanti sono entrati. */
function nl_aut_anniversari(): int {
    $n = 0;
    foreach (nl_flussi_attivi() as $f) foreach ($f['inneschi'] as $x) {
        if ($x['tipo'] !== 'anniversario' || ($a = newsletter_attributi()[$x['attr']] ?? null) === null) continue;
        $giorno = date('m-d', strtotime('+' . (int)$x['id'] . ' days'));
        $par = [];
        if ((int)$a['sistema']) {
            if ((NL_SEG_COLONNE[$a['colonna']] ?? '') !== 'date') continue;
            $w = "DATE_FORMAT(i.`{$a['colonna']}`, '%m-%d') = ? AND DATE(i.`{$a['colonna']}`) < CURDATE()";
            $par[] = $giorno;
        } elseif ($a['tipo'] === 'date') {
            $w = 'EXISTS (SELECT 1 FROM ' . tbl('newsletter_valori') . " v WHERE v.contatto_id = i.id AND v.attributo_id = ? AND DATE_FORMAT(v.v_data, '%m-%d') = ? AND v.v_data < CURDATE())";
            array_push($par, (int)$a['id'], $giorno);
        } else continue;
        $par[] = (int)$f['id'];
        $st = db()->prepare('SELECT i.id FROM ' . tbl('newsletter_iscritti') . " i WHERE i.stato = 'attivo' AND $w AND NOT EXISTS (SELECT 1 FROM " . tbl('newsletter_corsi') . " c WHERE c.flusso_id = ? AND c.iscritto_id = i.id AND (c.stato = 'attivo' OR c.creato > DATE_SUB(NOW(), INTERVAL 300 DAY))) LIMIT 500");
        $st->execute($par);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $i) $n += newsletter_flusso_parti($f, (int)$i, 'anniversario') ? 1 : 0;
    }
    return $n;
}

/** Condizioni di uscita (si disiscrive, entra in un segmento) e automazioni inattive con qualcuno dentro. Ritorna quanti sono usciti. */
function nl_aut_uscite(): int {
    $n = 0;
    $c = tbl('newsletter_corsi'); $i = tbl('newsletter_iscritti');
    foreach (db()->query('SELECT * FROM ' . tbl('newsletter_flussi') . " f WHERE EXISTS (SELECT 1 FROM $c c WHERE c.flusso_id = f.id AND c.stato = 'attivo')")->fetchAll() as $f) {
        $f = nl_flusso_riga($f);
        if ($f['stato'] === 'inattiva') { $n += nl_aut_togli_tutti((int)$f['id'], 'automazione inattiva'); continue; }
        $prove = [];
        if ($f['uscita']['disiscritto']) $prove[] = ["i.stato <> 'attivo'", [], 'esce: non è più iscritto'];
        if ($f['uscita']['segmento'] && function_exists('newsletter_segmento_where')) {
            $par = [];
            $w = newsletter_segmento_where($f['uscita']['segmento'], $par);
            $s = newsletter_segmento($f['uscita']['segmento']);
            if ($w !== '0' && $s) $prove[] = [$w, $par, 'esce: è entrato nel segmento ' . newsletter_4byte_fuori((string)$s['nome'])];
        }
        foreach ($prove as [$w, $par, $perche]) {
            $st = db()->prepare("SELECT c.id, c.iscritto_id, c.passo FROM $c c JOIN $i i ON i.id = c.iscritto_id WHERE c.flusso_id = ? AND c.stato = 'attivo' AND $w LIMIT 1000");
            $st->execute(array_merge([(int)$f['id']], $par));
            foreach ($st->fetchAll() as $x) $n += nl_corso_chiudi((int)$x['id'], 'tolto', $perche, (int)$f['id'], (int)$x['iscritto_id'], (int)$x['passo']);
        }
    }
    return $n;
}

// ------------------------------------------------------------
// Statistiche, attività, registro
// ------------------------------------------------------------

/** I numeri di un'automazione: per passo (qui adesso, passati, rami, e per le email gli stessi numeri del report) e in tutto. */
function nl_aut_numeri(array $f): array {
    $id = (int)$f['id'];
    $out = ['nodi' => [], 'tot' => ['entrati' => 0, 'attivo' => 0, 'finito' => 0, 'tolto' => 0, 'fermato' => 0]];
    $st = db()->prepare('SELECT nodo, chiave, n FROM ' . tbl('newsletter_flussi_numeri') . ' WHERE flusso_id = ?');
    $st->execute([$id]);
    foreach ($st->fetchAll() as $r) $out['nodi'][(int)$r['nodo']][$r['chiave']] = (int)$r['n'];
    $st = db()->prepare('SELECT passo, stato, COUNT(*) AS n FROM ' . tbl('newsletter_corsi') . ' WHERE flusso_id = ? GROUP BY passo, stato');
    $st->execute([$id]);
    foreach ($st->fetchAll() as $r) {
        $out['tot']['entrati'] += (int)$r['n'];
        if (isset($out['tot'][$r['stato']])) $out['tot'][$r['stato']] += (int)$r['n'];
        if ($r['stato'] === 'attivo') $out['nodi'][(int)$r['passo']]['qui'] = (int)$r['n'];
    }
    $ev = [];
    $st = db()->prepare('SELECT passo, tipo, COUNT(DISTINCT iscritto_id) AS u, SUM(volte) AS t FROM ' . tbl('newsletter_eventi') . ' WHERE flusso_id = ? AND campagna_id = 0 GROUP BY passo, tipo');
    $st->execute([$id]);
    foreach ($st->fetchAll() as $r) $ev[(int)$r['passo']][$r['tipo']] = [(int)$r['u'], (int)$r['t']];
    foreach ($f['grafo']['nodi'] as $n) {
        if ($n['tipo'] !== 'email') continue;
        $e = $ev[$n['id']] ?? [];
        $x = ['destinatari' => 0, 'inviate' => $out['nodi'][$n['id']]['inviate'] ?? 0, 'errori' => 0, 'rimbalzati_dopo' => 0, 'soft' => $e['soft'][0] ?? 0,
            'apertura' => $e['apertura'][0] ?? 0, 'apertura_tot' => $e['apertura'][1] ?? 0, 'clic' => $e['clic'][0] ?? 0, 'clic_tot' => $e['clic'][1] ?? 0,
            'uscita' => $e['uscita'][0] ?? 0, 'spam' => $e['spam'][0] ?? 0, 'rimbalzo' => $e['rimbalzo'][0] ?? 0];
        $x['destinatari'] = $x['inviate'];
        if (!function_exists('nl_rep_tassi')) @include_once __DIR__ . '/newsletter-report.php';
        $out['nodi'][$n['id']]['email'] = function_exists('nl_rep_tassi') ? nl_rep_tassi($x) : $x;
    }
    return $out;
}

/** Chi è dentro adesso (stato attivo), 50 per pagina, filtrabile per passo (-1 = tutti). Ritorna [righe, totale]. */
function nl_aut_dentro(int $f, int $passo, int $pag): array {
    $w = 'c.flusso_id = ? AND c.stato = \'attivo\'' . ($passo >= 0 ? ' AND c.passo = ?' : '');
    $par = $passo >= 0 ? [$f, $passo] : [$f];
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_corsi') . " c WHERE $w");
    $st->execute($par);
    $tot = (int)$st->fetchColumn();
    $st = db()->prepare('SELECT c.*, i.email FROM ' . tbl('newsletter_corsi') . ' c JOIN ' . tbl('newsletter_iscritti') . " i ON i.id = c.iscritto_id WHERE $w ORDER BY c.id DESC LIMIT 50 OFFSET " . (max(1, min(100000, $pag)) - 1) * 50);
    $st->execute($par);
    return [$st->fetchAll(), $tot];
}

/** Il registro di un'automazione, il più recente prima; $email filtra un contatto. */
function nl_aut_registro(int $f, string $email = '', int $quante = 200): array {
    $par = [$f];
    $w = '';
    if ($email !== '') { $w = ' AND i.email = ?'; $par[] = mb_strtolower(trim($email)); }
    $st = db()->prepare('SELECT r.*, i.email FROM ' . tbl('newsletter_flussi_registro') . ' r LEFT JOIN ' . tbl('newsletter_iscritti') . " i ON i.id = r.iscritto_id WHERE r.flusso_id = ?$w ORDER BY r.id DESC LIMIT " . max(1, min(1000, $quante)));
    $st->execute($par);
    return $st->fetchAll();
}

/** Toglie a mano un contatto dall'automazione (il suo cammino in corso). */
function nl_aut_togli(int $f, int $corso): bool {
    $st = db()->prepare('SELECT iscritto_id, passo FROM ' . tbl('newsletter_corsi') . " WHERE id = ? AND flusso_id = ? AND stato = 'attivo'");
    $st->execute([$corso, $f]);
    $c = $st->fetch();
    return $c && nl_corso_chiudi($corso, 'tolto', 'tolto a mano dal pannello', $f, (int)$c['iscritto_id'], (int)$c['passo']) > 0;
}

/** Aggiunge a mano un contatto (l'innesco "aggiunto a mano"). Ritorna la frase per chi usa il pannello e se è riuscito. */
function nl_aut_aggiungi(array $f, string $email): array {
    if ($f['stato'] !== 'attiva') return [false, 'L\'automazione non è attiva: mettila su Attiva e salva, poi aggiungi il contatto.'];
    if (!in_array('mano', array_column($f['inneschi'], 'tipo'), true)) return [false, 'Aggiungi prima l\'innesco "Aggiunto a mano dal pannello" (scheda Inneschi della tela) e salva.'];
    $id = newsletter_id_da_email(mb_strtolower(trim($email)));
    if (!$id) return [false, 'Nessun contatto con questa email: controlla di averla scritta bene, o crealo in Contatti.'];
    $st = db()->prepare('SELECT stato FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
    $st->execute([$id]);
    if ($st->fetchColumn() !== 'attivo') return [false, 'Questo contatto non è iscritto (in attesa di conferma o in lista nera): non riceverebbe le email.'];
    return newsletter_flusso_parti($f, $id, 'mano') ? [true, 'Contatto aggiunto: parte al prossimo giro del cron (entro 5 minuti).'] : [false, 'Il contatto è già dentro, o ci è già passato e l\'automazione non permette di rientrare (Impostazioni).'];
}

/** La pulizia di ogni giorno: il registro più vecchio di 90 giorni. Ritorna quante righe ha tolto. */
function nl_aut_pulizia(): int {
    newsletter_flussi_schema();
    return db()->exec('DELETE FROM ' . tbl('newsletter_flussi_registro') . ' WHERE creato < DATE_SUB(NOW(), INTERVAL ' . NL_AUT_REGISTRO_GIORNI . ' DAY)') ?: 0;
}

/** Il design a blocchi di un passo Email, scritto dall'editor delle email. */
function nl_aut_design_salva(int $f, int $nodo, string $json): bool {
    $x = newsletter_flusso($f);
    if (!$x) return false;
    $g = $x['grafo'];
    $trovato = false;
    foreach ($g['nodi'] as &$n) if ($n['id'] === $nodo && $n['tipo'] === 'email') { $n['dati']['editor'] = 'blocchi'; $n['dati']['design'] = $json; $trovato = true; }
    unset($n);
    if (!$trovato) return false;
    db()->prepare('UPDATE ' . tbl('newsletter_flussi') . ' SET passi = ?, aggiornato = NOW() WHERE id = ?')->execute([json_encode($g, JSON_INVALID_UTF8_SUBSTITUTE), $f]);
    return true;
}

// ------------------------------------------------------------
// Modelli di automazione
// ------------------------------------------------------------

/** I modelli: chiave => [nome, descrizione, inneschi, grafo]. Si aprono come automazione nuova, che si salva solo con Salva. */
function nl_aut_modelli(): array {
    $e = fn($id, $x, $y, $ogg, $corpo, $ascolto = 0) => ['id' => $id, 'tipo' => 'email', 'x' => $x, 'y' => $y, 'dati' => ['oggetto' => $ogg, 'anteprima' => '', 'corpo' => $corpo, 'pulsante_testo' => '', 'pulsante_link' => '', 'ascolto' => $ascolto]];
    $nodo = fn($id, $tipo, $x, $y, $dati = []) => ['id' => $id, 'tipo' => $tipo, 'x' => $x, 'y' => $y, 'dati' => $dati];
    $arco = fn($da, $u, $a) => ['da' => $da, 'uscita' => $u, 'a' => $a];
    $data = '';
    foreach (newsletter_attributi() as $n => $a) if (!(int)$a['sistema'] && $a['tipo'] === 'date') { $data = $n; break; }
    return [
        'benvenuto' => ['Benvenuto', 'Chi si iscrive (dopo la conferma) riceve subito un\'email di benvenuto; due giorni dopo una seconda, diversa per chi ha aperto la prima e per chi no.',
            [['tipo' => 'iscrizione', 'id' => 0]],
            ['nodi' => [$nodo(0, 'innesco', 40, 40), $e(1, 340, 40, 'Benvenuto, {nome}', '<p>Ciao {nome},</p><p>grazie per esserti iscritto. Ecco cosa troverai nelle nostre email.</p>'),
                $nodo(2, 'attesa', 340, 300, ['minuti' => 2880]), $nodo(3, 'bivio', 340, 480, ['condizione' => 'aperta', 'valore' => 1]),
                $e(4, 120, 700, 'Le cose più lette, {nome}', '<p>Visto che ci leggi, ecco le pagine più amate del sito.</p>'),
                $e(5, 560, 700, 'Ti eri perso la nostra email, {nome}?', '<p>Ti avevamo scritto qualche giorno fa: ecco di nuovo le cose più importanti.</p>')],
             'archi' => [$arco(0, 'poi', 1), $arco(1, 'poi', 2), $arco(2, 'poi', 3), $arco(3, 'si', 4), $arco(3, 'no', 5)]]],
        'anniversario' => ['Anniversario', 'Il giorno della data scelta (per esempio il compleanno, un attributo di tipo Data) parte un\'email di auguri, una volta l\'anno.',
            [['tipo' => 'anniversario', 'id' => 0, 'attr' => $data]],
            ['nodi' => [$nodo(0, 'innesco', 40, 40), $e(1, 340, 40, 'Tanti auguri, {nome}!', '<p>Ciao {nome},</p><p>oggi è un giorno speciale: tanti auguri da tutti noi.</p>')],
             'archi' => [$arco(0, 'poi', 1)]]],
        'riattivazione' => ['Attività di marketing: chi non apre più', 'Chi non apre e non clicca niente da 90 giorni riceve un\'email "ci manchi"; se in 7 giorni non la apre, ne riceve un\'ultima e viene tolto dalla lista principale.',
            [['tipo' => 'inattivo', 'id' => 90]],
            ['nodi' => [$nodo(0, 'innesco', 40, 40), $e(1, 340, 40, 'Ci manchi, {nome}', '<p>È da un po\' che non ci leggi. Vuoi continuare a ricevere le nostre email?</p>'),
                $nodo(2, 'evento', 340, 300, ['evento' => 'apertura', 'email' => 1, 'massimo' => 10080]), $nodo(3, 'fine', 120, 520),
                $e(4, 560, 520, 'Ultima email, {nome}', '<p>Non ti scriveremo più, a meno che tu non ce lo chieda: basta un clic su un link di questa email.</p>'),
                $nodo(5, 'lista_togli', 560, 760, ['lista' => newsletter_lista_principale()])],
             'archi' => [$arco(0, 'poi', 1), $arco(1, 'poi', 2), $arco(2, 'si', 3), $arco(2, 'no', 4), $arco(4, 'poi', 5)]]],
    ];
}
