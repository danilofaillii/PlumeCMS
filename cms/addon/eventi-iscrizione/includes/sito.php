<?php
/**
 * Gli indirizzi pubblici dell'addon Eventi con iscrizione (evento sito_percorso, dentro sito/index.php):
 *   GET  /evento/<nome>/                          la pagina dell'evento (pubblicato o annullato; le bozze no), con i dati per Google
 *   POST /evento-iscrizione/invia/                un'iscrizione (captcha, campo esca, consenso, limite per IP)
 *   GET  /evento-iscrizione/gestisci/?i=&t=       la pagina personale (link firmato): vedere e cancellarsi
 *   POST /evento-iscrizione/gestisci/             cancella (i e t nel modulo)
 *   GET  /evento-iscrizione/calendario/?i=&t=     il file .ics
 * Un indirizzo che non è uno di questi, o un evento che non c'è, va avanti: risponde il resto del sito.
 */
require_once __DIR__ . '/vista.php';

function evi_sito(string $percorso, array $q): void {
    evi_schema();
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if (preg_match('~^/evento/([a-z0-9-]{1,120})/$~', $percorso, $m)) {
        $e = evi_evento_slug($m[1]);
        if ($e && in_array($e['stato'], ['pubblicato', 'annullato'], true)) evi_sito_pagina($e);
        return;
    }
    switch ($percorso) {
        case '/evento-iscrizione/invia/': if ($post) evi_sito_invia(); return;
        case '/evento-iscrizione/gestisci/': evi_sito_gestisci($q, $post);
        case '/evento-iscrizione/calendario/':
            $i = evi_da_link($q['i'] ?? null, $q['t'] ?? null);
            if (!$i) evi_sito_link_rotto();
            sito_rispondi(200, 'text/calendar', evi_ics(evi_evento((int)$i['evento_id']), $i), ['Content-Disposition: attachment; filename="evento.ics"', 'Cache-Control: no-store', 'X-Robots-Tag: noindex']);
    }
}

function evi_stessa_origine(): bool {
    $o = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($o === '') return true;
    $porta = fn($u) => isset($u['port']) ? ':' . $u['port'] : '';
    $u = parse_url($o) ?: [];
    $sito = parse_url(cms_url_sito()) ?: [];
    $chi = strtolower(($u['host'] ?? '') . $porta($u));
    return $chi !== '' && in_array($chi, [strtolower((string)($_SERVER['HTTP_HOST'] ?? '')), strtolower(($sito['host'] ?? '') . $porta($sito))], true);
}

function evi_sito_pagina(array $e): never {
    $codice = (string)($_GET['modulo'] ?? '');
    $foto = $e['foto'] !== '' ? '<figure class="evi-foto"><img src="' . h((string)$e['foto']) . '" alt="' . h((string)$e['titolo']) . '"></figure>' : '';
    $corpo = '<div class="wrap"><article class="pagina-generica evi-pagina"><span class="occhiello">' . h(ucfirst(evi_quando($e, false))) . '</span><h1>' . h((string)$e['titolo']) . '</h1>'
        . ($e['stato'] === 'annullato' ? '<p class="modulo-errore" role="alert">Questo evento è annullato.</p>' : '')
        . $foto . evi_scheda_html($e) . (trim((string)$e['descrizione']) !== '' ? '<div class="corpo evi-descrizione">' . evi_paragrafi((string)$e['descrizione']) . '</div>' : '')
        . ($e['stato'] === 'pubblicato' ? evi_modulo_html($e, ['titolo' => 'Iscriviti', 'id' => 'iscriviti', 'errore' => str_starts_with($codice, 'evi-') ? substr($codice, 4) : '']) : '')
        . '</article></div>';
    $desc = mb_substr(trim((string)preg_replace('/\s+/', ' ', (string)$e['descrizione'])), 0, 155);
    $og = ['og:title' => (string)$e['titolo'], 'og:type' => 'website', 'og:url' => cms_url_sito() . evi_url_evento($e)];
    if ($e['foto'] !== '') $og['og:image'] = str_starts_with((string)$e['foto'], '/') ? preg_replace('~^(https?://[^/]+).*$~', '$1', cms_url_sito()) . $e['foto'] : (string)$e['foto'];
    $testa = '<link rel="stylesheet" href="' . h(evi_asset('eventi.css')) . '">' . evi_jsonld($e);
    sito_html(200, ['titolo' => $e['titolo'] . ' - ' . SITO_NOME, 'descrizione' => $desc, 'robots' => (int)$e['inizio_ts'] < time() - 30 * 86400 ? 'noindex' : 'index', 'canonical' => cms_url_sito() . evi_url_evento($e), 'og' => $og], $corpo, $testa);
}

/** Paragrafi da un testo semplice (righe vuote = paragrafo nuovo), tutto con l'escape. */
function evi_paragrafi(string $t): string {
    $p = preg_split('/\R{2,}/', trim($t)) ?: [];
    return implode('', array_map(fn($x) => '<p>' . nl2br(h(trim($x)), false) . '</p>', $p));
}

function evi_torna(string $pagina, string $ancora, string $codice): never {
    header('Location: ' . cms_sito_base() . $pagina . '?modulo=evi-' . rawurlencode($codice) . '&f=evi#' . $ancora, true, 303);
    exit;
}

function evi_sito_invia(): never {
    $p = $_POST;
    $e = is_string($p['evento'] ?? null) && ctype_digit($p['evento']) ? evi_evento((int)$p['evento']) : null;
    $pagina = is_string($p['pagina'] ?? null) && preg_match('~^/[a-z0-9/-]*$~', $p['pagina']) ? $p['pagina'] : ($e ? evi_url_evento($e) : '/');
    $ancora = is_string($p['ancora'] ?? null) && preg_match('/^[a-z0-9-]{1,40}$/', $p['ancora']) ? $p['ancora'] : 'iscriviti';
    if (!$e || !evi_stessa_origine() || ($p['sito_web'] ?? '') !== '') evi_torna($pagina, $ancora, 'robot');
    $d = ['nome' => evi_testo($p['nome'] ?? '', 150), 'email' => mb_strtolower(evi_testo($p['email'] ?? '', 190)), 'telefono' => evi_testo($p['telefono'] ?? '', 30),
        'posti' => is_string($p['posti'] ?? null) && ctype_digit($p['posti']) ? (int)$p['posti'] : 0];
    if (mb_strlen($d['nome']) < 2) evi_torna($pagina, $ancora, 'nome');
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) evi_torna($pagina, $ancora, 'email');
    if ($d['telefono'] !== '' && !preg_match('/^\+?[0-9 .\/()-]{6,30}$/', $d['telefono'])) evi_torna($pagina, $ancora, 'telefono');
    if ($d['posti'] < 1) evi_torna($pagina, $ancora, 'posti');
    if (empty($p['privacy'])) evi_torna($pagina, $ancora, 'privacy');
    if (!moduli_captcha_valido((string)($p['captcha'] ?? ''))) evi_torna($pagina, $ancora, 'captcha');
    if (evi_troppi()) evi_torna($pagina, $ancora, 'troppi');
    try {
        $r = evi_iscrivi((int)$e['id'], $d);
    } catch (Throwable $ex) {
        error_log('eventi: ' . $ex->getMessage());
        $r = 'chiuse';
    }
    if (!is_array($r)) evi_torna($pagina, $ancora, $r);
    header('Location: ' . evi_url('gestisci', evi_iscrizione($r['id'])) . '&nuova=1', true, 303);
    exit;
}

function evi_sito_link_rotto(): never {
    sito_html(404, ['titolo' => 'Link non valido - ' . SITO_NOME, 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []],
        vista_cortesia('Iscrizione', 'Questo link non funziona', '<p>Il link dell\'iscrizione è sbagliato o è scaduto. Controlla di averlo copiato intero dall\'email, oppure contattaci.</p>', '<a class="btn btn-viola" href="/">Vai al sito</a>'));
}

function evi_sito_gestisci(array $q, bool $post): never {
    $src = $post ? $_POST : $q;
    $i = evi_da_link($src['i'] ?? null, $src['t'] ?? null);
    if (!$i) evi_sito_link_rotto();
    $link = evi_url('gestisci', $i);
    $e = evi_evento((int)$i['evento_id']);
    if ($post) {
        if (!evi_stessa_origine()) evi_sito_link_rotto();
        $fatto = ($_POST['azione'] ?? '') === 'cancella' && (int)$e['inizio_ts'] > time() && evi_cancella($i, 'persona');
        header('Location: ' . $link . ($fatto ? '&fatto=1' : '&e=chiuse'), true, 303);
        exit;
    }
    $msg = '';
    if (isset($q['nuova'])) $msg = $i['stato'] === 'attesa' ? 'L\'evento è al completo: sei in lista d\'attesa, al posto numero ' . evi_posizione($i) . '. Se si libera un posto entri da solo e ti scriviamo. Ti abbiamo mandato un\'email.' : 'Sei iscritto. Ti abbiamo mandato un\'email con il riepilogo e il file per il calendario.';
    if (isset($q['fatto'])) $msg = 'L\'iscrizione è cancellata. Ti abbiamo mandato un\'email di conferma.';
    $stato = $i['stato'] === 'attesa' ? 'In lista d\'attesa (posto ' . evi_posizione($i) . ')' : (EVI_STATI[$i['stato']] ?? '');
    $corpo = ($msg !== '' ? '<p class="evi-ok" role="status">' . h($msg) . '</p>' : '') . (isset($q['e']) ? '<p class="modulo-errore" role="alert">Non si può più cancellare dal sito: l\'evento è già cominciato.</p>' : '')
        . evi_scheda_html($e) . '<dl class="evi-scheda"><dt>A nome di</dt><dd>' . h((string)$i['nome']) . '</dd><dt>Posti</dt><dd>' . (int)$i['posti'] . '</dd><dt>Stato</dt><dd><b class="evi-stato-' . h((string)$i['stato']) . '">' . h($stato) . '</b></dd></dl>';
    $azioni = '';
    if ($i['stato'] !== 'cancellata' && (int)$e['inizio_ts'] > time() && $e['stato'] === 'pubblicato') {
        if ($i['stato'] === 'confermata') $azioni .= '<p><a class="btn btn-viola" href="' . h(evi_url('calendario', $i)) . '">Aggiungi al calendario</a></p>';
        if (isset($q['cancella'])) {
            $azioni .= '<div class="evi-conferma" role="alert"><p><b>Vuoi davvero cancellare l\'iscrizione?</b> Il tuo posto andrà a chi aspetta.</p><div class="evi-azioni">'
                . '<form method="post" action="' . h(cms_sito_base() . '/evento-iscrizione/gestisci/') . '"><input type="hidden" name="i" value="' . (int)$i['id'] . '"><input type="hidden" name="t" value="' . h(evi_firma((int)$i['id'], (string)$i['codice'])) . '"><input type="hidden" name="azione" value="cancella">'
                . '<button type="submit" class="btn evi-annulla">Sì, cancellami</button></form><a class="btn" href="' . h($link) . '">No, resto iscritto</a></div></div>';
        } else {
            $azioni .= '<div class="evi-azioni"><a class="btn evi-annulla" href="' . h($link . '&cancella=1') . '">' . ($i['stato'] === 'attesa' ? 'Esci dalla lista d\'attesa' : 'Cancella l\'iscrizione') . '</a><a class="btn" href="' . h(evi_url_evento($e)) . '">Pagina dell\'evento</a></div>';
        }
    }
    sito_html(200, ['titolo' => 'La tua iscrizione - ' . SITO_NOME, 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []],
        '<div class="wrap"><article class="pagina-generica cortesia evi-pagina"><span class="occhiello">Iscrizione</span><h1>' . h((string)$e['titolo']) . '</h1><div class="corpo">' . $corpo . '</div>' . $azioni . '</article></div>',
        '<link rel="stylesheet" href="' . h(evi_asset('eventi.css')) . '">');
}
