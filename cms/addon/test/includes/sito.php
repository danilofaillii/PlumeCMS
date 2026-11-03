<?php
/**
 * Gli indirizzi pubblici dell'addon Test e questionari (evento sito_percorso, dentro sito/index.php):
 *   GET  /fai-il-test/<nome>/          la presentazione e le domande (una per schermata con il JavaScript, tutte senza)
 *   POST /fai-il-test/<nome>/invia/           le risposte: il server calcola tutto, registra l'esito anonimo e porta al risultato
 *   GET  /fai-il-test/<nome>/risultato/?e=&f= il risultato del link firmato (solo l'esito); con &k= anche il riquadro dell'email
 *   POST /fai-il-test/<nome>/email/           il risultato per email, con il consenso, e l'iscrizione alla lista (doppia conferma)
 *   POST /test-api/passo/              la richiesta leggera del browser: il tentativo comincia o arriva a una domanda
 * Un test in bozza si vede solo nell'anteprima dell'amministratore (?preview=1). Un indirizzo che non è uno di questi,
 * o un test che non c'è, va avanti: risponde il resto del sito.
 */
require_once __DIR__ . '/vista.php';

function test_sito(string $percorso, array $q): void {
    test_schema();
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if ($percorso === '/test-api/passo/') { if ($post) test_sito_passo(); return; }
    if (!str_starts_with($percorso, TEST_PERCORSO) || !preg_match('~^/[a-z-]+/([a-z0-9][a-z0-9-]{0,119})/(|invia/|risultato/|email/)$~', $percorso, $m)) return;
    $t = test_per_slug($m[1]);
    $anteprima = defined('SITO_ANTEPRIMA') && SITO_ANTEPRIMA;
    if (!$t || ($t['stato'] !== 'pubblicato' && !$anteprima)) return;
    switch ($m[2]) {
        case '': test_sito_pagina($t); // never
        case 'invia/': if ($post) test_sito_invia($t); sito_vai(test_url($t), 303);
        case 'risultato/': test_sito_risultato($t, $q);
        case 'email/': if ($post) test_sito_email($t); sito_vai(test_url($t), 303);
    }
}

/** La richiesta arriva da questo sito? (Origin uguale, o assente come nei browser vecchi e nei moduli senza JavaScript.) */
function test_stessa_origine(): bool {
    $o = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($o === '') return true;
    $porta = fn($u) => isset($u['port']) ? ':' . $u['port'] : '';
    $u = parse_url($o) ?: [];
    $sito = parse_url(cms_url_sito()) ?: [];
    $chi = strtolower(($u['host'] ?? '') . $porta($u));
    return $chi !== '' && in_array($chi, [strtolower((string)($_SERVER['HTTP_HOST'] ?? '')), strtolower(($sito['host'] ?? '') . $porta($sito))], true);
}

function test_testa_html(): string {
    return '<link rel="stylesheet" href="' . h(test_asset('test.css')) . '">';
}

/** La presentazione composta con l'editor: [html, righe per la testa], oppure null. */
function test_presentazione_html(array $t): ?array {
    $anteprima = defined('SITO_ANTEPRIMA') && SITO_ANTEPRIMA;
    $albero = editor_decodifica((string)($anteprima && trim((string)$t['struttura_bozza']) !== '' ? $t['struttura_bozza'] : $t['struttura']));
    if (!$albero) return null;
    [$html, $css] = editor_disegna($albero, test_url($t));
    $testa = $css !== '' ? '<style id="ed-css">' . str_replace('</', '<\\/', $css) . '</style>' : '';
    if (function_exists('editor_serve_script') && editor_serve_script($html)) $html .= '<script src="' . sito_versione_asset('editor-pagina.js') . '" defer></script>';
    if (function_exists('editor_asset_pagina')) { $asset = editor_asset_pagina($html); foreach ($asset['css'] ?? [] as $f) $testa = '<link rel="stylesheet" href="' . sito_versione_asset($f) . '">' . $testa; foreach ($asset['js'] ?? [] as $f) $html .= '<script src="' . sito_versione_asset($f) . '" defer></script>'; }
    return ['<div class="ed-pagina tq-presentazione">' . $html . '</div>', $testa];
}

/** La pagina del test. $o come test_modulo_html() (risposte ed errori dopo un invio sbagliato). */
function test_sito_pagina(array $t, array $o = [], int $codice = 200): never {
    $pres = test_presentazione_html($t);
    $testoSemplice = $t['dati']['presentazione'] !== '' ? '<div class="corpo">' . implode('', array_map(fn($p) => '<p>' . nl2br(h(trim($p)), false) . '</p>', preg_split('/\R{2,}/', $t['dati']['presentazione']) ?: [])) . '</div>' : '';
    $conErrori = !empty($o['errori']) || !empty($o['errore']);
    $corpo = '<div class="wrap tq-pagina"><header class="tq-testa"><span class="occhiello">Test</span><h1>' . h($t['nome']) . '</h1><p class="tq-numeri">' . h(test_quanto_testo($t)) . '</p></header></div>'
        . ($pres ? $pres[0] : '<div class="wrap tq-pagina">' . $testoSemplice . '</div>')
        . '<div class="wrap tq-pagina"><p class="tq-avvia"><a class="btn btn-viola" href="#tq-modulo" id="tq-inizia" rel="nofollow">Inizia il test</a></p>'
        . '<div class="tq-app" id="tq-app"' . ($conErrori ? ' data-errori="1"' : '') . '>' . test_modulo_html($t, $o) . '</div>'
        . (trim($t['dati']['avvertenza']) !== '' ? '<p class="tq-avvertenza" role="note">' . nl2br(h($t['dati']['avvertenza']), false) . '</p>' : '')
        . '</div>'
        . '<script src="' . h(test_asset('test.js')) . '" defer></script>';
    $desc = mb_substr(trim((string)preg_replace('/\s+/', ' ', strip_tags($t['dati']['presentazione'] ?: test_quanto_testo($t)))), 0, 155);
    $url = cms_url_sito() . test_url($t);
    header('Cache-Control: no-store');
    sito_html($codice, ['titolo' => $t['nome'] . ' - ' . SITO_NOME, 'descrizione' => $desc, 'robots' => $t['stato'] === 'pubblicato' && !$conErrori ? 'index' : 'noindex',
        'canonical' => $url, 'og' => ['og:title' => (string)$t['nome'], 'og:type' => 'website', 'og:url' => $url]], $corpo, test_testa_html() . ($pres[1] ?? ''));
}

/** Le risposte del POST: domanda => valore (stringhe corte). Tutto il resto si scarta. */
function test_risposte_post(): array {
    $r = $_POST['r'] ?? [];
    $out = [];
    if (!is_array($r)) return $out;
    foreach (array_slice($r, 0, TEST_MAX_DOMANDE * 2, true) as $k => $v) {
        if (!is_string($k) || strlen($k) > 20 || !is_string($v) || strlen($v) > 20) continue;
        $out[$k] = $v;
    }
    return $out;
}

/** 2.23.0: le risposte sulla persona del POST (p[domanda] => risposta), stringhe corte. Le controlla test_persona_valida(). */
function test_persona_post(): array {
    $p = $_POST['p'] ?? [];
    $out = [];
    if (!is_array($p)) return $out;
    foreach (array_slice($p, 0, 60, true) as $k => $v) if (is_string($k) && strlen($k) <= 20 && is_string($v) && strlen($v) <= 20) $out[$k] = $v;
    return $out;
}

function test_sito_invia(array $t): never {
    $risposte = test_risposte_post();
    $personaPost = test_persona_post();
    $torna = fn(array $o) => ['risposte' => $risposte, 'persona' => $personaPost] + $o;
    // Prima le difese: stessa origine, campo esca, codice del modulo (scaduto dopo un giorno), captcha, limite per connessione.
    if (!test_stessa_origine() || ($_POST['sito_web'] ?? '') !== '') test_sito_pagina($t, $torna(['errore' => 'robot']), 400);
    if (($g = test_gettone_controlla((int)$t['id'], $_POST['gettone'] ?? null)) !== '') test_sito_pagina($t, $torna(['errore' => $g]), $g === 'presto' ? 429 : 400);
    $r = test_calcola($t, $risposte);
    [$persona, $errP] = test_persona_valida($t, $personaPost); // 2.23.0
    if (!$r['ok'] || $errP) {
        $err = [];
        foreach ($r['errori'] as $e) if ($e['codice'] !== 'sconosciuta' && !isset($err[$e['domanda']])) $err[$e['domanda']] = $e['codice']; // il valore sbagliato prima di "manca la risposta"
        test_sito_pagina($t, $torna(['errori' => $err, 'errori_persona' => $errP]), 422);
    }
    if (test_limite('invio', TEST_LIMITE_ORA)) test_sito_pagina($t, $torna(['errore' => 'troppi']), 429);
    $anteprima = defined('SITO_ANTEPRIMA') && SITO_ANTEPRIMA;
    if ($t['stato'] === 'pubblicato' && !$anteprima) {
        try { test_registra($t, $r, is_string($_POST['tentativo'] ?? null) ? (string)$_POST['tentativo'] : '', $persona); } catch (Throwable $e) { error_log('test: registra: ' . $e->getMessage()); }
    }
    $par = test_esito_parametri(test_esito_corto($t, $r));
    // 2.23.0: le risposte sulla persona viaggiano firmate con il permesso dell'email (pz), solo se c'è una lista: servono ai tag.
    $pz = test_email_attiva($t) && $t['dati']['dopo']['lista'] > 0 ? test_persona_corto($persona) : '';
    $dopo = cms_url_sito() . test_url($t) . 'risultato/?' . http_build_query($par + (test_email_attiva($t) ? ['k' => test_chiave_email($par['e'], null, $pz)] + ($pz !== '' ? ['pz' => $pz] : []) : []) + ($anteprima ? ['preview' => 1] : []));
    header('Cache-Control: no-store');
    header('Location: ' . $dopo, true, 303);
    exit;
}

function test_sito_link_rotto(array $t): never {
    sito_html(404, ['titolo' => 'Risultato non valido - ' . SITO_NOME, 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []],
        vista_cortesia('Test', 'Questo link non funziona', '<p>Il link del risultato è incompleto o è stato cambiato. Copialo di nuovo per intero, oppure rifai il test: ci vogliono pochi minuti.</p>', '<a class="btn btn-viola" href="' . h(test_url($t)) . '">Rifai il test</a>'));
}

function test_sito_risultato(array $t, array $q, array $msg = []): never {
    $e = test_esito_da_link($t, $q['e'] ?? null, $q['f'] ?? null);
    if (!$e) test_sito_link_rotto($t);
    $link = cms_url_sito() . test_url($t) . 'risultato/?' . http_build_query(['e' => $q['e'], 'f' => $q['f']]);
    $pz = is_string($q['pz'] ?? null) && strlen($q['pz']) <= 1500 ? (string)$q['pz'] : '';
    $k = test_chiave_email_valida((string)$q['e'], $q['k'] ?? null, $pz) ? (string)$q['k'] : '';
    if ($k !== '') $msg += ['pz' => $pz];
    if (isset($q['inviata'])) $msg += ['ok' => 'Fatto: ti abbiamo mandato il risultato per email.' . (isset($q['iscritto']) ? ' Per la newsletter controlla la posta: c\'è un\'email per confermare.' : '')];
    if (is_string($q['errore'] ?? null) && isset(TEST_ERRORI[$q['errore']])) $msg += ['errore' => $q['errore']];
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    sito_html(200, ['titolo' => 'Il tuo risultato: ' . $t['nome'] . ' - ' . SITO_NOME, 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []],
        test_risultato_html($t, $e, $link, isset($q['inviata']) ? '' : $k, $msg), test_testa_html());
}

function test_sito_email(array $t): never {
    $eStr = is_string($_POST['e'] ?? null) ? $_POST['e'] : '';
    $fStr = is_string($_POST['f'] ?? null) ? $_POST['f'] : '';
    $k = is_string($_POST['k'] ?? null) ? $_POST['k'] : '';
    $pz = is_string($_POST['pz'] ?? null) && strlen($_POST['pz']) <= 1500 ? $_POST['pz'] : '';
    $e = test_esito_da_link($t, $eStr, $fStr);
    if (!$e) test_sito_link_rotto($t);
    $torna = function (string $codice, array $extra = []) use ($t, $eStr, $fStr, $k, $pz): never {
        header('Location: ' . cms_url_sito() . test_url($t) . 'risultato/?' . http_build_query(['e' => $eStr, 'f' => $fStr] + ($codice !== '' ? ['k' => $k] + ($pz !== '' ? ['pz' => $pz] : []) + ['errore' => $codice] : $extra)) . ($codice !== '' ? '#tq-email' : ''), true, 303);
        exit;
    };
    // 2.23.0: pz (le risposte sulla persona) è firmato dentro k: cambiato o inventato, il permesso non vale più.
    if (!test_email_attiva($t) || !test_chiave_email_valida($eStr, $k, $pz) || !test_stessa_origine() || ($_POST['sito_web'] ?? '') !== '') $torna('robot');
    $email = mb_strtolower(test_testo($_POST['email'] ?? '', 190));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $torna('email');
    if (empty($_POST['consenso'])) $torna('consenso');
    if (!moduli_captcha_valido((string)($_POST['captcha'] ?? ''))) $torna('captcha');
    if (test_limite('email', TEST_LIMITE_EMAIL_ORA)) $torna('troppi');
    $link = cms_url_sito() . test_url($t) . 'risultato/?' . http_build_query(['e' => $eStr, 'f' => $fStr]);
    [$oggetto, $html] = test_email_corpo($t, $e, $link);
    if (!posta_invia($email, $oggetto, $html, moduli_intestazioni(), 'test')) $torna('posta');
    $extra = ['inviata' => 1];
    // L'iscrizione alla lista scelta dall'autore: doppia conferma come i form. Solo qui l'email entra nella scheda del contatto, con l'esito.
    $lista = (int)$t['dati']['dopo']['lista'];
    if (!empty($_POST['iscrivi']) && $lista > 0 && newsletter_nome_lista($lista) !== '') {
        $esito = newsletter_richiesta(['email' => $email, 'nome' => ''], ['lista' => $lista, 'fonte' => 'test', 'cosa' => newsletter_nome_lista($lista)]);
        if ($esito === '') {
            $id = newsletter_id_da_email($email);
            $rt = test_risultato_da_corto($t, $e);
            newsletter_storia_scrivi($id, 'test', 'Ha completato il test «' . $t['nome'] . '»: ' . (test_risultato_parti($t, $e)['titolo']) . (isset($e['o']) && $e['o'] !== null ? ' (' . (int)$e['o'] . ' su 100)' : ''), (int)$t['id']);
            try { test_innesca($id, $t, test_esiti($rt)); } catch (Throwable $x) { error_log('test: innesco: ' . $x->getMessage()); }
            // 2.23.0: i tag (risposte sulla persona ed esito) arrivano alla conferma dell'iscrizione, con l'innesco "Riceve un tag".
            // Solo chi si iscrive: chi lascia l'email senza la newsletter riceve il risultato e basta (scelta di Danilo).
            try {
                if (!function_exists('newsletter_tag_in_attesa')) require_once dirname(__DIR__, 3) . '/includes/newsletter-tag.php';
                newsletter_tag_in_attesa($id, test_tag_persona($t, test_persona_da_corto($t, $pz), $e), 'dal test «' . $t['nome'] . '»');
            } catch (Throwable $x) { error_log('test: tag: ' . $x->getMessage()); }
            $extra['iscritto'] = 1;
        }
    }
    $torna('', $extra);
}

/** Dall'esito corto del link, la forma di test_calcola() che serve per i codici dell'esito (test_esiti). */
function test_risultato_da_corto(array $t, array $e): array {
    $aree = [];
    foreach ((array)($e['a'] ?? []) as $k => $x) if (is_string($k) && is_array($x)) $aree[$k] = ['pct' => $x[0] ?? null, 'fascia' => (string)($x[1] ?? '')];
    return ['fascia' => (string)($e['f'] ?? ''), 'aree' => $aree, 'profilo' => (string)($e['p'] ?? ''), 'regola' => (string)($e['r'] ?? '')];
}

/** La richiesta leggera del browser: niente dati personali, solo test, codice casuale del tentativo, domanda e passo. */
function test_sito_passo(): never {
    $t = is_string($_POST['t'] ?? null) && ctype_digit($_POST['t']) ? test_leggi((int)$_POST['t']) : null;
    if ($t && $t['stato'] === 'pubblicato' && test_stessa_origine()) {
        $d = is_string($_POST['d'] ?? null) && preg_match('/^[a-z][a-z0-9]{1,15}$/', $_POST['d']) && in_array($_POST['d'], array_column($t['dati']['domande'], 'k'), true) ? $_POST['d'] : '';
        try { test_tentativo($t['id'], is_string($_POST['c'] ?? null) ? $_POST['c'] : '', $d, (int)($_POST['n'] ?? 0)); } catch (Throwable $e) { error_log('test: passo: ' . $e->getMessage()); }
    }
    http_response_code(204);
    header('Cache-Control: no-store');
    exit;
}
