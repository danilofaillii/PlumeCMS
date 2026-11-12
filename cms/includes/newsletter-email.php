<?php
/**
 * L'email di conferma dell'iscrizione (doppio consenso), modificabile in ogni sua parte (2.7.0).
 *
 * Le parti: intestazione in cima, oggetto, titolo, testo, testo del pulsante, colore del pulsante, frase sotto il pulsante,
 * firma, nota in fondo. Il link del pulsante resta sempre quello vero e non si cambia.
 *
 * Da dove arriva ogni parte, nell'ordine: il form che iscrive (colonna moduli.conferma_email), i valori comuni
 * (Newsletter > Impostazioni, impostazione newsletter_conferma_email), il testo di partenza (quello di prima della 2.7.0,
 * uguale al byte: chi non cambia niente riceve l'email di sempre). Una parte vuota, o uguale a quella che erediterebbe,
 * non si salva: segue quella sopra.
 *
 * Segnaposti: {nome} (il nome di chi si iscrive; " {nome}" sparisce tutto, spazio compreso, quando il nome manca),
 * {lista} (per cosa si iscrive: il titolo del form, o "newsletter ..."), {giorni} (quanto vale il link), {sito} (il nome
 * del sito). Nei testi semplici i valori entrano come testo; nel testo dell'email, che è HTML limitato, entrano dopo la
 * pulizia (sicurezza_html_pulito, profilo 'ricco') e con l'escape, così un nome non porta mai codice dentro l'email.
 *
 * Emoji: le tabelle sono a 3 byte, i caratteri a 4 byte si salvano come entità (newsletter_4byte_dentro) e tornano
 * caratteri alla lettura (newsletter_4byte_fuori), prima dell'escape.
 */

require_once __DIR__ . '/moduli.php';
require_once __DIR__ . '/sicurezza-html.php';
require_once __DIR__ . '/newsletter.php'; // newsletter_4byte_dentro() e _fuori(); se lo include lui, qui non succede niente

/** Le parti, con il massimo di caratteri e se sono su una riga sola. */
const NL_EMAIL_PARTI = [
    'intestazione' => [150, true], 'oggetto' => [255, true], 'titolo' => [255, true], 'testo' => [5000, false],
    'pulsante' => [100, true], 'colore' => [7, true], 'sotto' => [300, true], 'firma' => [300, false], 'nota' => [500, false],
];
const NL_EMAIL_COLORE = '#7c3aed';

/**
 * Una parte scritta nel pannello, pulita: righe singole senza a capo, lunghezza, colore come #rrggbb, testo HTML passato
 * dal filtro. Ritorna il valore com'è da salvare (emoji come entità), '' se non va bene o è vuoto.
 */
function nl_email_parte_pulita(string $k, mixed $v): string {
    if (!isset(NL_EMAIL_PARTI[$k]) || !is_scalar($v)) return '';
    [$max, $riga] = NL_EMAIL_PARTI[$k];
    $v = str_replace("\0", '', (string)$v);
    $v = $riga ? trim((string)preg_replace('/[\r\n\t]+/', ' ', $v)) : trim(str_replace("\r\n", "\n", $v));
    if ($v === '') return '';
    if ($k === 'colore') return preg_match('/^#[0-9a-f]{6}$/i', $v) ? strtolower($v) : '';
    if ($k === 'testo' && str_contains($v, '<')) $v = trim(sicurezza_html_pulito($v, 'ricco'));
    return mb_substr(newsletter_4byte_dentro($v), 0, $max);
}

/** Tutte le parti pulite (solo quelle scritte). */
function nl_email_pulisci(mixed $in): array {
    $out = [];
    if (!is_array($in)) return $out;
    foreach (array_keys(NL_EMAIL_PARTI) as $k) {
        $v = nl_email_parte_pulita($k, $in[$k] ?? '');
        if ($v !== '') $out[$k] = $v;
    }
    return $out;
}

/** Da JSON (colonna o impostazione) alle parti, con le emoji di nuovo caratteri. */
function nl_email_da_json(mixed $j): array {
    $a = is_array($j) ? $j : json_decode((string)$j, true);
    $out = [];
    if (!is_array($a)) return $out;
    foreach (nl_email_pulisci($a) as $k => $v) $out[$k] = newsletter_4byte_fuori($v);
    return $out;
}

/** Dalle parti al JSON da salvare (a 3 byte). Vuoto se non c'è niente. */
function nl_email_a_json(array $parti): string {
    $p = nl_email_pulisci($parti);
    return $p ? (string)json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
}

/** I valori comuni (Newsletter > Impostazioni). */
function nl_email_comuni(): array {
    return nl_email_da_json(get_setting('newsletter_conferma_email', ''));
}

/** Il nome del sito per {sito} e per la firma di partenza. */
function nl_email_sito(): string {
    return (string)cms_impostazione('sito_nome', CMS_NOME);
}

/**
 * I testi di partenza, come si scrivono nei campi (con i segnaposti). $principale: il modulo della newsletter del sito
 * (frasi "alla newsletter"); altrimenti un form o una lista (frasi "a «...»").
 */
function nl_email_partenza(bool $principale): array {
    $nomeNl = trim((string)cms_impostazione('newsletter_nome', ''));
    return [
        'intestazione' => trim(html_entity_decode(strip_tags(moduli_email_intestazione()), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        'oggetto' => $principale ? "Conferma l'iscrizione a " . ($nomeNl ?: 'questa newsletter') : "Conferma l'iscrizione: {lista}",
        'titolo' => "Conferma l'iscrizione",
        'testo' => "Ciao {nome},\n\nmanca un clic. Premi il pulsante e confermi l'iscrizione " . ($principale ? 'alla {lista}' : 'a «{lista}»') . ".\n\nIl link vale {giorni} giorni.",
        'pulsante' => "Confermo l'iscrizione",
        'colore' => NL_EMAIL_COLORE,
        'sotto' => 'Se il pulsante non si apre, copia questo indirizzo nel browser:',
        'firma' => (string)cms_impostazione('email_firma', nl_email_sito()),
        'nota' => "Se la richiesta l'ha fatta qualcun altro, ignora questa email: senza conferma l'indirizzo resta fuori" . ($principale ? ' dalla newsletter' : '') . '.',
    ];
}

/** Quello che un campo vuoto usa: i comuni (se $conComuni) sopra i testi di partenza. */
function nl_email_ereditati(bool $principale, bool $conComuni): array {
    return ($conComuni ? nl_email_comuni() : []) + nl_email_partenza($principale);
}

/**
 * Le parti da salvare: pulite, senza quelle uguali a quello che erediterebbero (così seguono i comuni o la partenza).
 * Ritorna il JSON ('' se non resta niente).
 */
function nl_email_da_salvare(mixed $in, bool $principale, bool $conComuni): string {
    $eredita = nl_email_ereditati($principale, $conComuni);
    $p = [];
    foreach (nl_email_pulisci($in) as $k => $v) {
        $chiaro = newsletter_4byte_fuori($v);
        if (nl_email_uguali($chiaro, (string)($eredita[$k] ?? ''))) continue;
        $p[$k] = $chiaro;
    }
    return nl_email_a_json($p);
}

function nl_email_uguali(string $a, string $b): bool {
    $n = fn($s) => trim((string)preg_replace('/[ \t]+/', ' ', str_replace("\r\n", "\n", $s)));
    return $n($a) === $n($b);
}

/** Un segnaposto dentro un testo. $html: i valori con l'escape. " {nome}" sparisce con lo spazio quando il nome manca. */
function nl_email_segnaposti(string $s, array $val, bool $html): string {
    if (($val['nome'] ?? '') === '') $s = str_replace(' {nome}', '', $s);
    $r = [];
    foreach (['nome', 'lista', 'giorni', 'sito'] as $k) $r['{' . $k . '}'] = $html ? h((string)($val[$k] ?? '')) : (string)($val[$k] ?? '');
    return strtr($s, $r);
}

/**
 * Il testo dell'email scritto nel pannello, in HTML pulito: senza paragrafi scritti a mano, una riga vuota separa i
 * paragrafi e un a capo va a capo. Dopo la pulizia entrano i segnaposti, con l'escape.
 */
function nl_email_testo_html(string $t, array $val): string {
    $t = newsletter_4byte_fuori(trim(str_replace("\r\n", "\n", $t)));
    if (!preg_match('~<(p|ul|ol|h[2-4]|blockquote)[\s>]~i', $t)) {
        $par = preg_split('/\n[ \t]*\n+/', $t) ?: [];
        $t = implode('', array_map(fn($x) => '<p>' . nl2br(trim($x), false) . '</p>', array_filter($par, fn($x) => trim($x) !== '')));
    }
    return nl_email_segnaposti(sicurezza_html_pulito($t, 'ricco'), $val, true);
}

/** Il colore del testo sul pulsante: bianco, o quasi nero se il fondo è chiaro (contrasto). */
function nl_email_su_colore(string $c): string {
    if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $c, $m)) return '#fff';
    $l = function (string $x): float { $v = hexdec($x) / 255; return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4; };
    $lum = 0.2126 * $l($m[1]) + 0.7152 * $l($m[2]) + 0.0722 * $l($m[3]);
    return (1.05 / ($lum + 0.05)) >= (($lum + 0.05) / 0.05) ? '#fff' : '#111';
}

/**
 * L'email di conferma. Ritorna [oggetto, html].
 * $c: principale (bool), cosa (per cosa si iscrive), nome, link, parti (dal form: già lette), comuni (bool, di partenza
 * true), opz (le vecchie opzioni di newsletter_richiesta: oggetto, testo HTML, pulsante, che vincono su tutto).
 * Senza parti scritte esce uguale al byte a quella di prima della 2.7.0.
 */
function nl_email_conferma(array $c): array {
    $principale = !empty($c['principale']);
    $cosa = (string)($c['cosa'] ?? '');
    $nome = trim((string)($c['nome'] ?? ''));
    $link = (string)($c['link'] ?? '');
    $opz = (array)($c['opz'] ?? []);
    $partenza = nl_email_partenza($principale);
    $parti = [];
    foreach ((array)($c['parti'] ?? []) + (($c['comuni'] ?? true) ? nl_email_comuni() : []) as $k => $v) {
        $v = newsletter_4byte_fuori((string)$v);
        if (isset(NL_EMAIL_PARTI[$k]) && trim($v) !== '' && !nl_email_uguali($v, (string)$partenza[$k])) $parti[$k] = $v;
    }
    $val = ['nome' => $nome, 'lista' => $cosa, 'giorni' => (string)MODULI_CONFERMA_GIORNI, 'sito' => nl_email_sito()];
    $testo = fn(string $k) => nl_email_segnaposti($parti[$k], $val, false);

    // Le frasi di prima della 2.7.0, uguali al byte.
    if ($principale) {
        $frase = 'confermi l&#039;iscrizione alla ' . h($cosa);
        $oggetto = "Conferma l'iscrizione a " . (trim((string)cms_impostazione('newsletter_nome', '')) ?: 'questa newsletter');
        $fuori = 'senza conferma l&#039;indirizzo resta fuori dalla newsletter';
    } else {
        $frase = 'confermi l&#039;iscrizione a «' . h($cosa) . '»';
        $oggetto = "Conferma l'iscrizione: " . $cosa;
        $fuori = 'senza conferma l&#039;indirizzo resta fuori';
    }
    if (isset($parti['oggetto'])) $oggetto = $testo('oggetto');
    if (trim((string)($opz['oggetto'] ?? '')) !== '') $oggetto = trim((string)$opz['oggetto']);

    if ((string)($opz['testo'] ?? '') !== '') $corpo = (string)$opz['testo'];
    elseif (isset($parti['testo'])) $corpo = nl_email_testo_html($parti['testo'], $val);
    else $corpo = '<p>Ciao' . ($nome !== '' ? ' ' . h($nome) : '') . ',</p><p>manca un clic. Premi il pulsante e ' . $frase . '.</p><p>Il link vale ' . MODULI_CONFERMA_GIORNI . ' giorni.</p>';

    // Il testo del pulsante arriva a moduli_email_html() in chiaro: lì prende l'escape.
    $pulsante = trim((string)($opz['pulsante'] ?? '')) !== '' ? html_entity_decode(trim((string)$opz['pulsante']), ENT_QUOTES, 'UTF-8')
        : (isset($parti['pulsante']) ? $testo('pulsante') : html_entity_decode('Confermo l&#039;iscrizione', ENT_QUOTES, 'UTF-8'));
    $nota = isset($parti['nota']) ? nl2br(h($testo('nota')), false) : 'Se la richiesta l&#039;ha fatta qualcun altro, ignora questa email: ' . $fuori . '.';
    $impag = ['dopo_firma' => '<p style="font-size:12px;color:#888">' . $nota . '</p>'];
    if (isset($parti['intestazione'])) $impag['intestazione'] = h($testo('intestazione'));
    if (isset($parti['firma'])) $impag['firma'] = '<p style="margin-top:28px">' . nl2br(h($testo('firma')), false) . '</p>';
    if (isset($parti['sotto'])) $impag['sotto'] = h($testo('sotto'));
    if (isset($parti['colore'])) $impag['colore'] = $parti['colore'];
    $titolo = isset($parti['titolo']) ? $testo('titolo') : 'Conferma l\'iscrizione';

    $html = moduli_email_html($titolo, $corpo, $pulsante, $link, $impag);
    return [str_replace(["\r", "\n"], ' ', $oggetto), $html];
}

/** Il link finto delle anteprime e delle prove: porta alla pagina di conferma, che lo dà per scaduto. Non conferma niente. */
function nl_email_link_finto(): string {
    return MODULI_URL . '/newsletter-conferma/?t=prova-senza-valore';
}
