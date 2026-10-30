<?php
/**
 * L'HTML del modulo d'iscrizione a un evento (lo usano la pagina dell'evento e il widget "Iscrizione all'evento") e del
 * riquadro con data, luogo e posti. Il modulo si invia normalmente (POST a /evento-iscrizione/invia/) con il captcha dei
 * moduli del sito (sito/assets/moduli.js).
 */
require_once __DIR__ . '/eventi.php';

const EVI_ERRORI = [
    'nome' => 'Scrivi il tuo nome e cognome.',
    'email' => 'Controlla l\'indirizzo email: sembra incompleto.',
    'telefono' => 'Controlla il numero di telefono.',
    'posti' => 'Scegli quanti posti ti servono.',
    'privacy' => 'Per iscriverti serve la spunta sull\'informativa privacy.',
    'captcha' => 'Spunta la casella "Sono una persona" e aspetta il segno verde.',
    'troppi' => 'Da questa connessione sono arrivate troppe iscrizioni. Riprova fra un\'ora.',
    'gia' => 'Con questa email sei già iscritto a questo evento: controlla la posta, trovi il link per vedere o cancellare l\'iscrizione.',
    'chiuse' => 'Le iscrizioni a questo evento sono chiuse.',
    'pieno' => 'L\'evento è al completo.',
    'pochi' => 'Restano meno posti di quelli che hai chiesto.',
    'robot' => 'Richiesta non valida.',
];

function evi_asset(string $f): string {
    $p = dirname(__DIR__) . '/assets/' . $f;
    return cms_base() . '/addon/eventi-iscrizione/assets/' . $f . (is_file($p) ? '?v=' . filemtime($p) : '');
}

function evi_errore_testo(string $codice): string {
    if (str_starts_with($codice, 'pochi')) {
        $n = (int)substr($codice, 6);
        return $n > 0 ? ($n === 1 ? 'Resta un solo posto: scegli 1 posto per iscriverti.' : 'Restano solo ' . $n . ' posti: scegline al massimo ' . $n . '.') : EVI_ERRORI['pochi'];
    }
    return EVI_ERRORI[$codice] ?? '';
}

/** Il riquadro con data, ora, luogo, prezzo e posti. */
function evi_scheda_html(array $e): string {
    $l = evi_liberi($e);
    $righe = ['Quando' => evi_quando($e), 'Dove' => trim($e['luogo'] . ($e['indirizzo'] !== '' ? ', ' . $e['indirizzo'] : ''), ', '), 'Prezzo' => (string)$e['prezzo'], 'Posti' => evi_posti_testo($e)];
    $out = '<dl class="evi-scheda">';
    foreach ($righe as $k => $v) if ($v !== '') $out .= '<dt>' . h($k) . '</dt><dd' . ($k === 'Posti' ? ' class="evi-posti' . ($l === 0 ? ' evi-pieno' : '') . '"' : '') . '>' . h($v) . '</dd>';
    return $out . '</dl>';
}

/** Il modulo d'iscrizione. $o: id (ancora), percorso (la pagina, per tornarci con un errore), errore, titolo. */
function evi_modulo_html(array $e, array $o = []): string {
    $o += ['id' => 'evi-' . (int)$e['id'], 'percorso' => evi_url_evento($e), 'errore' => '', 'titolo' => '', 'scheda' => false];
    $id = preg_replace('/[^a-z0-9-]/', '', (string)$o['id']) ?: 'evi';
    $imp = evi_impostazioni();
    $out = '<section class="modulo evi" id="' . h($id) . '" aria-label="' . h($o['titolo'] ?: 'Iscriviti') . '">' . ($o['titolo'] !== '' ? '<h2>' . h($o['titolo']) . '</h2>' : '') . ($o['scheda'] ? evi_scheda_html($e) : '');
    if (!evi_aperte($e)) return $out . '<p class="evi-chiuse">' . h(evi_posti_testo($e)) . '.</p></section>';
    $liberi = evi_liberi($e);
    $err = $o['errore'] !== '' ? evi_errore_testo($o['errore']) : '';
    $max = max(1, (int)$e['max_persona']);
    if ($liberi > 0 && $liberi !== PHP_INT_MAX) $maxScelta = min($max, $liberi); else $maxScelta = $max;
    $pieno = $liberi === 0;
    $out .= ($pieno ? '<p class="evi-attesa-nota">L\'evento è al completo: iscrivendoti entri in <b>lista d\'attesa</b>. Se si libera un posto entri da solo e ti scriviamo.</p>' : '')
        . ($err !== '' ? '<p class="modulo-errore" role="alert">' . h($err) . '</p>' : '')
        . '<form method="post" action="' . h(cms_sito_base() . '/evento-iscrizione/invia/') . '" data-captcha>'
        . '<input type="hidden" name="modulo" value="evi:' . (int)$e['id'] . '"><input type="hidden" name="evento" value="' . (int)$e['id'] . '"><input type="hidden" name="pagina" value="' . h($o['percorso']) . '"><input type="hidden" name="ancora" value="' . h($id) . '"><input type="hidden" name="captcha" value="">'
        . '<div class="modulo-campi">'
        . '<label><span>Nome e cognome</span><input name="nome" autocomplete="name" maxlength="150" required></label>'
        . '<label><span>Email</span><input type="email" name="email" autocomplete="email" maxlength="190" required></label>'
        . '<label><span>Telefono <small>(facoltativo)</small></span><input type="tel" name="telefono" autocomplete="tel" maxlength="30"></label>';
    if ($max > 1) {
        $out .= '<label><span>Quanti posti</span><select name="posti">';
        for ($n = 1; $n <= ($pieno ? $max : $maxScelta); $n++) $out .= '<option value="' . $n . '">' . $n . ($n === 1 ? ' posto' : ' posti') . '</option>';
        $out .= '</select></label>';
    } else {
        $out .= '<input type="hidden" name="posti" value="1">';
    }
    $out .= '</div><div class="esca" aria-hidden="true"><label>Sito web <input name="sito_web" tabindex="-1" autocomplete="off"></label></div>'
        . '<label class="spunta"><input type="checkbox" name="privacy" value="1" required> <span>Ho letto l&#039;<a href="' . h((string)$imp['privacy']) . '" target="_blank">informativa privacy</a> e accetto che i miei dati servano per gestire l&#039;iscrizione.</span></label>'
        . '<label class="captcha"><input type="checkbox"> <span>Sono una persona</span> <span class="captcha-stato" aria-live="polite"></span></label>'
        . '<button class="btn btn-viola" type="submit">' . ($pieno ? 'Mettimi in lista d&#039;attesa' : 'Iscriviti') . '</button></form>'
        . '<script src="' . (function_exists('sito_versione_asset') ? sito_versione_asset('moduli.js') : cms_base() . '/sito/assets/moduli.js') . '" defer></script>';
    return $out . '</section>';
}
