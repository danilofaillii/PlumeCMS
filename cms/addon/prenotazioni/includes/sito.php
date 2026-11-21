<?php
/**
 * Gli indirizzi pubblici dell'addon Prenotazioni (evento sito_percorso, dentro sito/index.php):
 *   GET  /prenotazione/posti/?s=&r=&d=&da=   i giorni e gli orari liberi, in JSON (per assets/prenota.js)
 *   POST /prenotazione/invia/                 una prenotazione nuova (captcha, campo esca, consenso, limite per IP)
 *   GET  /prenotazione/gestisci/?p=&t=        la pagina personale: vedere, annullare, spostare (link firmato)
 *   POST /prenotazione/gestisci/              annulla o sposta (p e t nel modulo: senza la firma non si tocca niente)
 *   GET  /prenotazione/calendario/?p=&t=      il file .ics dell'appuntamento
 * Ogni risposta esce con sito_html() o sito_rispondi() ed esce. Un indirizzo che non è uno di questi va avanti (404 del sito).
 */
require_once __DIR__ . '/vista.php';

function pren_sito(string $percorso, array $q): void {
    pren_schema();
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    switch ($percorso) {
        case '/prenotazione/posti/': pren_sito_posti($q);
        case '/prenotazione/invia/': if ($post) pren_sito_invia(); return;
        case '/prenotazione/gestisci/': pren_sito_gestisci($q, $post);
        case '/prenotazione/calendario/':
            $p = pren_da_link($q['p'] ?? null, $q['t'] ?? null);
            if (!$p) pren_sito_link_rotto();
            sito_rispondi(200, 'text/calendar', pren_ics($p), ['Content-Disposition: attachment; filename="appuntamento.ics"', 'Cache-Control: no-store', 'X-Robots-Tag: noindex']);
    }
}

/** Una richiesta che arriva da un altro sito (Origin diverso) non tocca niente. */
function pren_stessa_origine(): bool {
    $o = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($o === '') return true;
    $porta = fn($u) => isset($u['port']) ? ':' . $u['port'] : '';
    $u = parse_url($o) ?: [];
    $sito = parse_url(cms_url_sito()) ?: [];
    $chi = strtolower(($u['host'] ?? '') . $porta($u));
    return $chi !== '' && in_array($chi, [strtolower((string)($_SERVER['HTTP_HOST'] ?? '')), strtolower(($sito['host'] ?? '') . $porta($sito))], true);
}

function pren_sito_json(array $d, int $codice = 200): never {
    sito_rispondi($codice, 'application/json', json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ['Cache-Control: no-store', 'X-Robots-Tag: noindex']);
}

function pren_sito_posti(array $q): never {
    $s = pren_servizio(is_string($q['s'] ?? null) && ctype_digit($q['s']) ? (int)$q['s'] : 0);
    if (!$s || !$s['attivo']) pren_sito_json(['errore' => 'servizio'], 404);
    $r = is_string($q['r'] ?? null) && ctype_digit($q['r']) ? (int)$q['r'] : 0;
    $escludi = 0;
    // Spostare: la prenotazione che si sposta non occupa il suo posto (solo con il suo link firmato).
    if (isset($q['p'], $q['t']) && ($p = pren_da_link($q['p'], $q['t'])) && (int)$p['servizio_id'] === (int)$s['id']) $escludi = (int)$p['id'];
    $imp = pren_impostazioni();
    $tz = new DateTimeZone(date_default_timezone_get());
    $oggi = new DateTimeImmutable('today', $tz);
    $ultimo = $oggi->modify('+' . max(1, (int)$imp['anticipo_giorni']) . ' day')->format('Y-m-d');
    $da = is_string($q['da'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q['da']) ? $q['da'] : $oggi->format('Y-m-d');
    if ($da < $oggi->format('Y-m-d')) $da = $oggi->format('Y-m-d');
    if ($da > $ultimo) $da = $ultimo;
    $giorno = is_string($q['d'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q['d']) ? $q['d'] : '';
    $giorni = [];
    $inizio = DateTimeImmutable::createFromFormat('!Y-m-d', $da, $tz);
    for ($i = 0; $i < 14; $i++) {
        $d = $inizio->modify("+$i day");
        $n = count(pren_posti($s, $r, $d->format('Y-m-d'), null, $escludi));
        $giorni[] = ['d' => $d->format('Y-m-d'), 'g' => mb_substr(PREN_GIORNI_NOMI[(int)$d->format('N') - 1], 0, 3), 'num' => (int)$d->format('j'), 'mese' => PREN_MESI[(int)$d->format('n') - 1], 'n' => $n];
        if ($giorno === '' && $n > 0) $giorno = $d->format('Y-m-d');
    }
    // Nessun posto in queste due settimane: si dice il primo giorno libero più avanti, se c'è.
    $prossimo = '';
    if (!array_filter($giorni, fn($g) => $g['n'] > 0)) {
        for ($d = $inizio->modify('+14 day'); $d->format('Y-m-d') <= $ultimo; $d = $d->modify('+1 day')) {
            if (pren_posti($s, $r, $d->format('Y-m-d'), null, $escludi)) { $prossimo = $d->format('Y-m-d'); break; }
        }
    }
    $posti = [];
    $nomi = array_column(pren_risorse(true), 'nome', 'id');
    foreach ($giorno !== '' ? pren_posti($s, $r, $giorno, null, $escludi) : [] as $ora => $chi) $posti[] = ['ora' => (string)$ora, 'con' => array_values(array_map(fn($x) => (string)($nomi[$x] ?? ''), $chi))];
    $persone = array_map(fn($x) => ['id' => (int)$x['id'], 'nome' => $x['nome']], pren_risorse_servizio($s));
    pren_sito_json(['servizio' => ['id' => (int)$s['id'], 'nome' => $s['nome'], 'durata' => pren_durata_testo($s)], 'persone' => $persone, 'da' => $da, 'oggi' => $oggi->format('Y-m-d'), 'ultimo' => $ultimo,
        'giorni' => $giorni, 'giorno' => $giorno, 'giorno_testo' => $giorno !== '' ? pren_giorno_esteso(DateTimeImmutable::createFromFormat('!Y-m-d', $giorno, $tz)->getTimestamp(), false) : '', 'posti' => $posti, 'prossimo' => $prossimo]);
}

/** Torna alla pagina del widget con un codice d'errore (moduli.js rimette i campi scritti). */
function pren_torna(string $pagina, string $ancora, string $codice): never {
    header('Location: ' . cms_sito_base() . $pagina . '?modulo=pren-' . rawurlencode($codice) . '&f=pren#' . $ancora, true, 303);
    exit;
}

function pren_sito_invia(): never {
    $p = $_POST;
    $pagina = is_string($p['pagina'] ?? null) && preg_match('~^/[a-z0-9/-]*$~', $p['pagina']) ? $p['pagina'] : '/';
    $ancora = is_string($p['ancora'] ?? null) && preg_match('/^[a-z0-9-]{1,40}$/', $p['ancora']) ? $p['ancora'] : 'pren';
    if (!pren_stessa_origine() || ($p['sito_web'] ?? '') !== '') pren_torna($pagina, $ancora, 'robot');
    $d = [
        'servizio' => is_string($p['servizio'] ?? null) && ctype_digit($p['servizio']) ? (int)$p['servizio'] : 0,
        'risorsa' => is_string($p['risorsa'] ?? null) && ctype_digit($p['risorsa']) ? (int)$p['risorsa'] : 0,
        'giorno' => is_string($p['giorno'] ?? null) ? $p['giorno'] : '', 'ora' => is_string($p['ora'] ?? null) ? $p['ora'] : '',
        'nome' => pren_testo($p['nome'] ?? '', 150), 'email' => mb_strtolower(pren_testo($p['email'] ?? '', 190)),
        'telefono' => pren_testo($p['telefono'] ?? '', 30), 'note' => pren_testo($p['note'] ?? '', 500),
    ];
    if (!$d['servizio']) pren_torna($pagina, $ancora, 'servizio');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['giorno']) || !preg_match('/^\d{1,2}:\d{2}$/', $d['ora'])) pren_torna($pagina, $ancora, 'ora');
    if (mb_strlen($d['nome']) < 2) pren_torna($pagina, $ancora, 'nome');
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) pren_torna($pagina, $ancora, 'email');
    $cifre = preg_replace('/\D/', '', $d['telefono']);
    if (!preg_match('/^\+?[0-9 .\/()-]{6,30}$/', $d['telefono']) || strlen($cifre) < 6 || strlen($cifre) > 15) pren_torna($pagina, $ancora, 'telefono');
    if (empty($p['privacy'])) pren_torna($pagina, $ancora, 'privacy');
    if (!moduli_captcha_valido((string)($p['captcha'] ?? ''))) pren_torna($pagina, $ancora, 'captcha');
    if (pren_troppi()) pren_torna($pagina, $ancora, 'troppi');
    try {
        $r = pren_prenota($d);
    } catch (Throwable $e) {
        error_log('prenotazioni: ' . $e->getMessage());
        $r = 'posto';
    }
    if (!is_array($r)) pren_torna($pagina, $ancora, $r);
    $nuova = pren_leggi($r['id']);
    header('Location: ' . pren_url('gestisci', $nuova) . '&nuova=1', true, 303);
    exit;
}

function pren_sito_link_rotto(): never {
    sito_html(404, ['titolo' => 'Link non valido - ' . SITO_NOME, 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []],
        vista_cortesia('Prenotazione', 'Questo link non funziona', '<p>Il link della prenotazione è sbagliato o è scaduto. Controlla di averlo copiato intero dall\'email, oppure contattaci.</p>', '<a class="btn btn-viola" href="/">Vai al sito</a>'));
}

function pren_sito_gestisci(array $q, bool $post): never {
    $src = $post ? $_POST : $q;
    $p = pren_da_link($src['p'] ?? null, $src['t'] ?? null);
    if (!$p) pren_sito_link_rotto();
    $link = pren_url('gestisci', $p);
    if ($post) {
        if (!pren_stessa_origine()) pren_sito_link_rotto();
        $az = (string)($_POST['azione'] ?? '');
        $e = 'robot';
        if ($az === 'annulla') $e = pren_annulla($p, 'cliente');
        elseif ($az === 'sposta') $e = is_string($_POST['giorno'] ?? null) && is_string($_POST['ora'] ?? null) ? pren_sposta($p, $_POST['giorno'], $_POST['ora']) : 'ora';
        header('Location: ' . $link . ($e === '' ? '&fatto=' . ($az === 'annulla' ? 'annullata' : 'spostata') : '&e=' . rawurlencode($e) . ($az === 'sposta' ? '&sposta=1' : '')), true, 303);
        exit;
    }
    $imp = pren_impostazioni();
    $msg = '';
    if (isset($q['nuova'])) $msg = $p['stato'] === 'da_confermare' ? 'Richiesta ricevuta. Ti scriviamo appena la confermiamo: intanto ti abbiamo mandato un\'email con il riepilogo.' : 'Prenotazione fatta. Ti abbiamo mandato un\'email con il riepilogo e il file per il calendario.';
    if (($q['fatto'] ?? '') === 'annullata') $msg = 'La prenotazione è annullata. Ti abbiamo mandato un\'email di conferma.';
    if (($q['fatto'] ?? '') === 'spostata') $msg = 'Fatto: l\'appuntamento è spostato. Ti abbiamo mandato un\'email con il nuovo orario.';
    $err = is_string($q['e'] ?? null) ? (PREN_ERRORI[$q['e']] ?? '') : '';
    $stato = ['da_confermare' => 'In attesa di conferma', 'confermata' => 'Confermata', 'svolta' => 'Svolta', 'non_presentato' => 'Svolta', 'annullata' => 'Annullata'][$p['stato']] ?? '';
    $corpo = ($msg !== '' ? '<p class="pren-ok" role="status">' . h($msg) . '</p>' : '') . ($err !== '' ? '<p class="modulo-errore" role="alert">' . h($err) . '</p>' : '')
        . '<dl class="pren-dettagli"><dt>Servizio</dt><dd>' . h((string)$p['servizio']) . ($p['prezzo'] !== '' ? ' (' . h((string)$p['prezzo']) . ')' : '') . '</dd>'
        . '<dt>Quando</dt><dd>' . h(pren_quando($p)) . '</dd>' . ($p['risorsa'] !== '' && count(pren_risorse(true)) > 1 ? '<dt>Con</dt><dd>' . h((string)$p['risorsa']) . '</dd>' : '')
        . (trim((string)$imp['indirizzo']) !== '' ? '<dt>Dove</dt><dd>' . h((string)$imp['indirizzo']) . '</dd>' : '')
        . '<dt>A nome di</dt><dd>' . h((string)$p['nome']) . '</dd><dt>Stato</dt><dd><b class="pren-stato pren-stato-' . h((string)$p['stato']) . '">' . h($stato) . '</b></dd></dl>';
    $azioni = '';
    if (in_array($p['stato'], PREN_STATI_ATTIVI, true)) {
        $azioni .= '<p><a class="btn btn-viola" href="' . h(pren_url('calendario', $p)) . '">Aggiungi al calendario</a></p>';
        if (pren_modificabile($p)) {
            if (isset($q['sposta'])) {
                $corpo .= '<h2 class="pren-h2">Scegli il nuovo giorno e la nuova ora</h2>' . pren_modulo_html(['sposta' => $p, 'id' => 'pren-sposta']);
            } elseif (isset($q['annulla'])) {
                // Annullare chiede una conferma, senza JavaScript: una seconda pagina con il pulsante vero.
                $azioni .= '<div class="pren-conferma" role="alert"><p><b>Vuoi davvero annullare la prenotazione?</b></p><div class="pren-azioni">'
                    . '<form method="post" action="' . h(cms_sito_base() . '/prenotazione/gestisci/') . '"><input type="hidden" name="p" value="' . (int)$p['id'] . '"><input type="hidden" name="t" value="' . h(pren_firma((int)$p['id'], (string)$p['codice'])) . '">'
                    . '<input type="hidden" name="azione" value="annulla"><button type="submit" class="btn pren-annulla">Sì, annulla</button></form><a class="btn" href="' . h($link) . '">No, tienila</a></div></div>';
            } else {
                $azioni .= '<div class="pren-azioni"><a class="btn" href="' . h($link . '&sposta=1#pren-sposta') . '">Sposta l\'appuntamento</a><a class="btn pren-annulla" href="' . h($link . '&annulla=1') . '">Annulla la prenotazione</a></div>';
            }
            $azioni .= '<p class="pren-nota">Puoi annullare o spostare da qui fino a ' . (int)$imp['limite_ore'] . ' ore prima dell\'appuntamento.</p>';
        } else {
            $azioni .= '<p class="pren-nota">Mancano meno di ' . (int)$imp['limite_ore'] . ' ore: per cambiare qualcosa telefonaci.</p>';
        }
    }
    $testa = '<link rel="stylesheet" href="' . h(pren_asset('prenota.css')) . '">' . (isset($q['sposta']) ? '<script src="' . h(pren_asset('prenota.js')) . '" defer></script>' : '');
    sito_html(200, ['titolo' => 'La tua prenotazione - ' . SITO_NOME, 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []],
        '<div class="wrap"><article class="pagina-generica cortesia pren-pagina"><span class="occhiello">Prenotazione</span><h1>La tua prenotazione</h1><div class="corpo">' . $corpo . '</div>' . $azioni . '</article></div>', $testa);
}
