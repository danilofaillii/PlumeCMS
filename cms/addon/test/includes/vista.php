<?php
/**
 * L'HTML pubblico dell'addon Test e questionari: il modulo con tutte le domande (che il JavaScript mostra una per
 * schermata, e che senza JavaScript funziona in una pagina sola), la pagina del risultato con il grafico in SVG disegnato
 * qui, il riquadro per ricevere il risultato per email. Tutto il testo esce con h(); i testi ricchi (risultati) sono già
 * ripuliti al salvataggio con sicurezza_html_pulito() e si ripuliscono di nuovo qui prima di uscire.
 */
require_once __DIR__ . '/test.php';
require_once dirname(__DIR__, 3) . '/includes/sicurezza-html.php';
require_once dirname(__DIR__, 3) . '/includes/editor.php'; // la presentazione scritta con l'editor visuale, editor_link_sicuro()

function test_asset(string $f): string {
    $p = dirname(__DIR__) . '/assets/' . $f;
    return cms_base() . '/addon/test/assets/' . $f . (is_file($p) ? '?v=' . filemtime($p) : '');
}

/** Un testo ricco salvato, ripulito di nuovo prima di uscire (difesa in più). */
function test_ricco_html(string $html): string {
    return $html === '' ? '' : sicurezza_html_pulito($html, 'ricco');
}

const TEST_ERRORI = [
    'obbligatoria' => 'Manca la risposta a questa domanda.',
    'valore' => 'Questa risposta non va bene: sceglila di nuovo.',
    'captcha' => 'Spunta la casella "Sono una persona" e aspetta il segno verde.',
    'presto' => 'Hai mandato le risposte molto in fretta: controllale e rimandale fra qualche secondo.',
    'troppi' => 'Da questa connessione sono arrivati troppi test in poco tempo. Riprova fra un\'ora.',
    'scaduto' => 'La pagina era aperta da troppo tempo: le tue risposte sono qui sotto, rimandale.',
    'robot' => 'Richiesta non valida.',
    'email' => 'Controlla l\'indirizzo email: sembra incompleto.',
    'consenso' => 'Per ricevere il risultato serve la spunta sul consenso.',
    'posta' => 'L\'email non è partita. Riprova fra qualche minuto.',
];

/** La frase "Rispondi solo se..." di una domanda condizionale (per la pagina senza JavaScript). */
function test_condizione_testo(array $t, array $q): string {
    $c = $q['condizione'];
    if (!$c) return '';
    $num = 0; $padre = null;
    foreach ($t['dati']['domande'] as $i => $x) if ($x['k'] === $c['domanda']) { $num = $i + 1; $padre = $x; }
    if (!$padre) return '';
    $nomi = array_column(test_opzioni($padre, $t['dati']), 't', 'k');
    $val = array_map(fn($v) => '«' . ($nomi[$v] ?? $v) . '»', $c['valori']);
    $ult = array_pop($val);
    return 'Rispondi solo se alla domanda ' . $num . ' hai risposto ' . ($val ? implode(', ', $val) . ' o ' : '') . $ult . '.';
}

/**
 * Il modulo con tutte le domande. $o: risposte (per rimetterle dopo un errore), errori (domanda => codice), errore
 * (generale), anteprima (true: nella tela dell'editor, senza invio).
 */
function test_modulo_html(array $t, array $o = []): string {
    $o += ['risposte' => [], 'errori' => [], 'errore' => ''];
    $d = $t['dati'];
    $azione = cms_sito_base() . test_url($t) . 'invia/';
    $out = '<form method="post" action="' . h($azione) . '" class="tq-modulo" id="tq-modulo" data-test="' . (int)$t['id'] . '"'
        . ($d['casuale'] ? ' data-casuale="1"' : '') . ' data-api="' . h(cms_sito_base() . '/test-api/passo/') . '">'
        . '<input type="hidden" name="gettone" value="' . h(test_gettone((int)$t['id'])) . '"><input type="hidden" name="tentativo" value="">';
    if ($o['errore'] !== '') $out .= '<p class="modulo-errore" role="alert" id="tq-errore">' . h(TEST_ERRORI[$o['errore']] ?? $o['errore']) . '</p>';
    elseif ($o['errori']) $out .= '<p class="modulo-errore" role="alert" id="tq-errore">' . (count($o['errori']) === 1 ? 'Una domanda da sistemare: la trovi segnata qui sotto.' : count($o['errori']) . ' domande da sistemare: le trovi segnate qui sotto.') . '</p>';
    $tot = count($d['domande']);
    foreach ($d['domande'] as $i => $q) {
        $k = $q['k'];
        $err = $o['errori'][$k] ?? '';
        $idAiuto = 'ta-' . $k;
        $cond = $q['condizione'] ? ' data-cond="' . h(json_encode($q['condizione'])) . '"' : '';
        $out .= '<fieldset class="tq-domanda' . ($err !== '' ? ' errato' : '') . '" id="td-' . h($k) . '" data-k="' . h($k) . '" data-tipo="' . h($q['tipo']) . '" data-obbl="' . ($q['obbligatoria'] ? '1' : '0') . '"' . $cond
            . ($q['aiuto'] !== '' || $q['condizione'] || $err !== '' ? ' aria-describedby="' . h(trim(($q['aiuto'] !== '' || $q['condizione'] ? $idAiuto : '') . ($err !== '' ? ' te-' . $k : ''))) . '"' : '') . '>'
            . '<legend><span class="tq-num">Domanda ' . ($i + 1) . ' di ' . $tot . ($q['obbligatoria'] && !$q['condizione'] ? '' : ' <small>(facoltativa)</small>') . '</span> <span class="tq-testo-domanda">' . h($q['testo']) . '</span></legend>';
        $note = [];
        if ($q['aiuto'] !== '') $note[] = h($q['aiuto']);
        if ($q['condizione']) $note[] = '<span class="tq-se">' . h(test_condizione_testo($t, $q)) . '</span>';
        if ($note) $out .= '<p class="tq-aiuto" id="' . h($idAiuto) . '">' . implode(' ', $note) . '</p>';
        $val = (string)($o['risposte'][$k] ?? '');
        $obbl = $q['obbligatoria'] && !$q['condizione'] ? ' required' : '';
        if ($q['tipo'] === 'cursore') {
            $v = $val !== '' && ctype_digit($val) ? (int)$val : 5;
            $out .= '<div class="tq-cursore"><span aria-hidden="true">0</span><input type="range" min="0" max="10" step="1" name="r[' . h($k) . ']" id="tr-' . h($k) . '" value="' . $v . '" aria-label="' . h($q['testo']) . ' (da 0 a 10)" data-cursore><span aria-hidden="true">10</span>'
                . '<output for="tr-' . h($k) . '" class="tq-valore" aria-hidden="true">' . $v . '</output></div>';
        } else {
            $out .= '<div class="tq-opzioni tq-opzioni-' . h($q['tipo']) . '">';
            foreach (test_opzioni($q, $d) as $j => $op) {
                $out .= '<label class="tq-opz"><input type="radio" name="r[' . h($k) . ']" value="' . h($op['k']) . '"' . ($val === $op['k'] ? ' checked' : '') . ($j === 0 ? $obbl : '') . '> <span>' . h($op['t']) . '</span></label>';
            }
            $out .= '</div>';
        }
        if ($err !== '') $out .= '<p class="errore-campo" id="te-' . h($k) . '" role="alert">' . h(TEST_ERRORI[$err] ?? '') . '</p>';
        $out .= '</fieldset>';
    }
    $out .= '<div class="tq-fine" id="tq-fine"><div class="esca" aria-hidden="true"><label>Sito web <input name="sito_web" tabindex="-1" autocomplete="off"></label></div>'
        . '<p class="tq-nota">Le risposte restano anonime: si conservano solo il risultato e i punteggi, senza il tuo nome né il tuo indirizzo IP.</p>'
        . '<button class="btn btn-viola" type="submit">Vedi il risultato</button></div>'
        . '</form>';
    return $out;
}

/** Le barre del grafico in SVG: [['nome', 'pct', 'fascia']]. Accessibile: role img, titolo e descrizione con i numeri. */
function test_grafico_svg(array $barre, string $titolo): string {
    if (!$barre) return '';
    $alto = 46; $h = count($barre) * $alto + 8; $w = 640; $x0 = 0; $lung = $w - 70;
    $id = 'tg-' . substr(md5($titolo . json_encode($barre)), 0, 8);
    $desc = implode('; ', array_map(fn($b) => $b['nome'] . ': ' . $b['pct'] . ' su 100' . ($b['fascia'] !== '' ? ' (' . $b['fascia'] . ')' : ''), $barre));
    $s = '<svg class="tq-grafico" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-labelledby="' . $id . '-t ' . $id . '-d" preserveAspectRatio="xMinYMin meet">'
        . '<title id="' . $id . '-t">' . h($titolo) . '</title><desc id="' . $id . '-d">' . h($desc) . '</desc>';
    foreach (array_values($barre) as $i => $b) {
        $y = $i * $alto + 4;
        $pct = max(0, min(100, (int)$b['pct']));
        $s .= '<text class="tq-g-nome" x="' . $x0 . '" y="' . ($y + 14) . '">' . h($b['nome']) . ($b['fascia'] !== '' ? ' &#183; ' . h($b['fascia']) : '') . '</text>'
            . '<rect class="tq-g-fondo" x="' . $x0 . '" y="' . ($y + 22) . '" width="' . $lung . '" height="14" rx="7"/>'
            . ($pct > 0 ? '<rect class="tq-g-barra" x="' . $x0 . '" y="' . ($y + 22) . '" width="' . max(14, round($lung * $pct / 100)) . '" height="14" rx="7"/>' : '')
            . '<text class="tq-g-pct" x="' . ($w - 2) . '" y="' . ($y + 34) . '" text-anchor="end">' . $pct . '</text>';
    }
    return $s . '</svg>';
}

/** Il pulsante finale di un risultato (testo e link), oppure niente. */
function test_pulsante_html(array $x): string {
    $l = editor_link_sicuro_test((string)($x['pulsante_link'] ?? ''));
    return trim((string)($x['pulsante_testo'] ?? '')) !== '' && $l !== '' ? '<p class="tq-azione"><a class="btn btn-viola" href="' . h($l) . '">' . h($x['pulsante_testo']) . '</a></p>' : '';
}

/** Il link di un pulsante, con le regole del widget Riquadro (editor_link_sicuro). */
function editor_link_sicuro_test(string $u): string {
    return function_exists('editor_link_sicuro') ? editor_link_sicuro($u) : test_link($u);
}

/**
 * Le parti di un risultato da un esito corto (quello del link): titolo, percentuale, testo, pulsante, barre.
 * Se il test nel frattempo è cambiato e una fascia non c'è più, resta la percentuale.
 */
function test_risultato_parti(array $t, array $e): array {
    $d = $t['dati'];
    $per = fn(array $l, string $k) => array_values(array_filter($l, fn($x) => $x['k'] === $k))[0] ?? null;
    $parti = ['titolo' => '', 'pct' => null, 'testo' => '', 'pulsante' => '', 'barre' => [], 'sotto' => ''];
    if ($t['tipo'] === 'profili') {
        $p = $per($d['profili'], (string)($e['p'] ?? ''));
        $parti['titolo'] = $p ? $p['nome'] : 'Il tuo profilo';
        $parti['testo'] = $p ? test_ricco_html($p['testo']) : '';
        $parti['pulsante'] = $p ? test_pulsante_html($p) : '';
        foreach ($d['profili'] as $x) $parti['barre'][] = ['nome' => $x['nome'], 'pct' => (int)($e['q'][$x['k']] ?? 0), 'fascia' => ''];
        return $parti;
    }
    $parti['pct'] = isset($e['o']) && is_numeric($e['o']) ? max(0, min(100, (int)$e['o'])) : null;
    $f = $per($d['fasce'], (string)($e['f'] ?? ''));
    $r = isset($e['r']) ? $per($d['regole'], (string)$e['r']) : null;
    $parti['titolo'] = $r ? $r['nome'] : ($f ? $f['nome'] : 'Il tuo risultato');
    $parti['testo'] = test_ricco_html($r ? $r['testo'] : ($f['testo'] ?? ''));
    $parti['pulsante'] = test_pulsante_html($r ?: ($f ?: []));
    foreach ($d['aree'] as $a) {
        $x = $e['a'][$a['k']] ?? null;
        if (!is_array($x) || !is_numeric($x[0] ?? null)) continue;
        $fa = $per($a['fasce'], (string)($x[1] ?? ''));
        $parti['barre'][] = ['nome' => $a['nome'], 'pct' => (int)$x[0], 'fascia' => $fa['nome'] ?? '', 'testo' => $fa ? test_ricco_html($fa['testo']) : ''];
    }
    return $parti;
}

/** Il risultato in HTML (pagina e email usano le stesse parti). */
function test_risultato_html(array $t, array $e, string $link, string $chiaveEmail = '', array $msg = []): string {
    $p = test_risultato_parti($t, $e);
    $d = $t['dati'];
    $out = '<div class="wrap tq-pagina tq-risultato" data-tq-fatto="' . (int)$t['id'] . '"><header class="tq-testa"><span class="occhiello">Il tuo risultato &middot; ' . h($t['nome']) . '</span><h1>' . h($p['titolo']) . '</h1>';
    if ($p['pct'] !== null) $out .= '<p class="tq-punteggio"><b>' . $p['pct'] . '</b> <span>su 100</span></p><div class="tq-barra" role="img" aria-label="' . $p['pct'] . ' su 100"><span style="width:' . $p['pct'] . '%"></span></div>';
    $out .= '</header>';
    if (($msg['ok'] ?? '') !== '') $out .= '<p class="tq-ok" role="status">' . h($msg['ok']) . '</p>';
    if ($p['testo'] !== '') $out .= '<div class="corpo tq-testo">' . $p['testo'] . '</div>';
    if ($p['barre']) {
        $titolo = $t['tipo'] === 'profili' ? 'Quanto somigli a ogni profilo' : 'Le aree';
        $out .= '<section class="tq-aree" aria-label="' . h($titolo) . '"><h2>' . h($titolo) . '</h2>' . test_grafico_svg($p['barre'], $titolo);
        if ($t['tipo'] === 'aree') {
            $out .= '<dl class="tq-aree-testi">';
            foreach ($p['barre'] as $b) $out .= '<dt>' . h($b['nome']) . ': ' . (int)$b['pct'] . ' su 100' . ($b['fascia'] !== '' ? ', ' . h($b['fascia']) : '') . '</dt><dd>' . ($b['testo'] ?? '') . '</dd>';
            $out .= '</dl>';
        }
        $out .= '</section>';
    }
    $out .= $p['pulsante'];
    if (trim($d['avvertenza']) !== '') $out .= '<p class="tq-avvertenza" role="note">' . nl2br(h($d['avvertenza']), false) . '</p>';
    // Il link da condividere: solo l'esito, firmato.
    $out .= '<section class="tq-condividi"><h2>Condividi il risultato</h2><p class="tq-nota">Il link mostra solo l\'esito, non le tue risposte né chi sei.</p>'
        . '<div class="tq-link"><label for="tq-link" class="sr-solo">Link al risultato</label><input id="tq-link" type="text" readonly value="' . h($link) . '" data-copia><button type="button" class="btn btn-filo" data-copia-link hidden>Copia il link</button></div></section>';
    if ($d['dopo']['email'] && $chiaveEmail !== '') $out .= test_email_html($t, $e, $link, $chiaveEmail, $msg);
    $out .= '<p class="tq-rifai"><a href="' . h(test_url($t)) . '">Rifai il test</a></p></div>'
        . '<script src="' . h(test_asset('test.js')) . '" defer></script>';
    return $out;
}

/** Il riquadro "Vuoi ricevere il risultato per email?": email, consenso separato, iscrizione alla lista (se c'è). */
function test_email_html(array $t, array $e, string $link, string $chiave, array $msg): string {
    $dp = $t['dati']['dopo'];
    $q = parse_url($link, PHP_URL_QUERY);
    parse_str((string)$q, $qq);
    $err = (string)($msg['errore'] ?? '');
    $lista = $dp['lista'] > 0 && function_exists('newsletter_nome_lista') && newsletter_nome_lista($dp['lista']) !== '';
    return '<section class="modulo tq-email" id="tq-email" aria-labelledby="tq-email-t"><h2 id="tq-email-t">Vuoi ricevere il risultato per email?</h2>'
        . ($err !== '' ? '<p class="modulo-errore" role="alert">' . h(TEST_ERRORI[$err] ?? $err) . '</p>' : '')
        . '<form method="post" action="' . h(cms_sito_base() . test_url($t) . 'email/') . '" data-captcha>'
        . '<input type="hidden" name="modulo" value="test-email:' . (int)$t['id'] . '"><input type="hidden" name="e" value="' . h((string)($qq['e'] ?? '')) . '"><input type="hidden" name="f" value="' . h((string)($qq['f'] ?? '')) . '"><input type="hidden" name="k" value="' . h($chiave) . '"><input type="hidden" name="captcha" value="">'
        . '<div class="modulo-campi"><label><span>Email</span><input type="email" name="email" autocomplete="email" maxlength="190" required></label></div>'
        . '<div class="esca" aria-hidden="true"><label>Sito web <input name="sito_web" tabindex="-1" autocomplete="off"></label></div>'
        . '<label class="spunta"><input type="checkbox" name="consenso" value="1" required> <span>' . h($dp['consenso']) . '</span></label>'
        . ($lista ? '<label class="spunta"><input type="checkbox" name="iscrivi" value="1"> <span>' . h($dp['iscrizione']) . '</span></label>' : '')
        . '<label class="captcha"><input type="checkbox"> <span>Sono una persona</span> <span class="captcha-stato" aria-live="polite"></span></label>'
        . '<button class="btn btn-viola" type="submit">Mandami il risultato</button></form>'
        . '<script src="' . (function_exists('sito_versione_asset') ? sito_versione_asset('moduli.js') : cms_base() . '/sito/assets/moduli.js') . '" defer></script></section>';
}

/** L'email con il risultato. */
function test_email_corpo(array $t, array $e, string $link): array {
    $p = test_risultato_parti($t, $e);
    $corpo = ($p['pct'] !== null ? '<p style="font-size:28px;font-weight:700;margin:0 0 12px">' . $p['pct'] . ' su 100</p>' : '') . $p['testo'];
    foreach ($p['barre'] as $b) $corpo .= '<p style="margin:6px 0"><b>' . h($b['nome']) . '</b>: ' . (int)$b['pct'] . ' su 100' . ($b['fascia'] !== '' ? ' (' . h($b['fascia']) . ')' : '') . '</p>';
    if (trim($t['dati']['avvertenza']) !== '') $corpo .= '<p style="font-size:13px;color:#555;margin-top:20px">' . nl2br(h($t['dati']['avvertenza']), false) . '</p>';
    return ['Il tuo risultato: ' . $t['nome'], moduli_email_html($t['nome'] . ': ' . $p['titolo'], $corpo, 'Rivedi il risultato', $link)];
}
