<?php
/**
 * L'HTML del calendario dei posti e del modulo di prenotazione: lo usano il widget "Prenota" e la pagina personale
 * (per spostare un appuntamento). I posti li disegna assets/prenota.js chiedendoli a /prenotazione/posti/; il modulo
 * si invia normalmente (POST a /prenotazione/invia/) con il captcha dei moduli del sito (sito/assets/moduli.js).
 */
require_once __DIR__ . '/prenotazioni.php';

const PREN_ERRORI = [
    'servizio' => 'Scegli il servizio.',
    'posto' => 'Quell\'orario non è più libero: qualcuno l\'ha appena preso. Scegline un altro.',
    'ora' => 'Scegli il giorno e l\'ora.',
    'nome' => 'Scrivi il tuo nome e cognome.',
    'email' => 'Controlla l\'indirizzo email: sembra incompleto.',
    'telefono' => 'Controlla il numero di telefono.',
    'privacy' => 'Per prenotare serve la spunta sull\'informativa privacy.',
    'captcha' => 'Spunta la casella "Sono una persona" e aspetta il segno verde.',
    'troppi' => 'Da questa connessione sono arrivate troppe prenotazioni. Riprova fra un\'ora o telefonaci.',
    'robot' => 'Richiesta non valida.',
    'tardi' => 'Non si può più cambiare questa prenotazione dal sito: è troppo vicina. Telefonaci.',
];

function pren_asset(string $f): string {
    $p = dirname(__DIR__) . '/assets/' . $f;
    return cms_base() . '/addon/prenotazioni/assets/' . $f . (is_file($p) ? '?v=' . filemtime($p) : '');
}

/**
 * Il calendario e il modulo. $o: servizio (id fisso o 0), risorsa (id fissa o 0), titolo, intro, pulsante, id (per
 * l'ancora), percorso (la pagina, per tornarci con un errore), errore (codice), sposta (la prenotazione da spostare).
 */
function pren_modulo_html(array $o): string {
    $o += ['servizio' => 0, 'risorsa' => 0, 'titolo' => '', 'intro' => '', 'pulsante' => 'Prenota', 'id' => 'pren', 'percorso' => '/', 'errore' => '', 'sposta' => null, 'anteprima' => false];
    $imp = pren_impostazioni();
    $servizi = pren_servizi(true);
    $risorse = pren_risorse(true);
    if (!$servizi || !$risorse) {
        return $o['anteprima'] ? '<div class="ed-vuoto">Aggiungi almeno un servizio e una persona con i suoi orari in Prenotazioni, nel pannello.</div>'
            : '<p class="pren-chiuso">Le prenotazioni dal sito non sono ancora aperte. Contattaci per un appuntamento.</p>';
    }
    $sposta = $o['sposta'];
    $fisso = $sposta ? (int)$sposta['servizio_id'] : (int)$o['servizio'];
    if ($fisso && !array_filter($servizi, fn($s) => (int)$s['id'] === $fisso)) $fisso = 0;
    $base = cms_sito_base() . '/prenotazione/';
    $id = preg_replace('/[^a-z0-9-]/', '', (string)$o['id']) ?: 'pren';
    $err = $o['errore'] !== '' ? (PREN_ERRORI[$o['errore']] ?? '') : '';
    $out = '<section class="modulo pren" id="' . $id . '" data-pren="' . h($base . 'posti/') . '"' . ($sposta ? ' data-pren-sposta="' . (int)$sposta['id'] . '"' : '') . ' aria-label="' . h($o['titolo'] ?: 'Prenota') . '">'
        . ($o['titolo'] !== '' ? '<h2>' . h($o['titolo']) . '</h2>' : '') . ($o['intro'] !== '' ? '<p>' . nl2br(h($o['intro'])) . '</p>' : '')
        . ($err !== '' ? '<p class="modulo-errore" role="alert">' . h($err) . '</p>' : '');
    $out .= '<form method="post" action="' . h($sposta ? $base . 'gestisci/' : $base . 'invia/') . '"' . ($sposta ? '' : ' data-captcha') . '>';
    if ($sposta) {
        $out .= '<input type="hidden" name="p" value="' . (int)$sposta['id'] . '"><input type="hidden" name="t" value="' . h(pren_firma((int)$sposta['id'], (string)$sposta['codice'])) . '"><input type="hidden" name="azione" value="sposta">';
    } else {
        $out .= '<input type="hidden" name="modulo" value="pren:' . h($id) . '"><input type="hidden" name="pagina" value="' . h($o['percorso']) . '"><input type="hidden" name="ancora" value="' . h($id) . '"><input type="hidden" name="captcha" value="">';
    }
    $out .= '<input type="hidden" name="giorno" value=""><input type="hidden" name="ora" value="">';
    // 1. Il servizio e la persona
    $out .= '<div class="modulo-campi pren-scelte">';
    if ($fisso) {
        $s = array_values(array_filter($servizi, fn($x) => (int)$x['id'] === $fisso))[0];
        $out .= '<input type="hidden" name="servizio" value="' . $fisso . '"><p class="pren-servizio-fisso form-largo"><b>' . h($s['nome']) . '</b> <span>' . h(pren_durata_testo($s)) . '</span></p>';
    } else {
        $out .= '<label class="form-largo"><span>Servizio</span><select name="servizio" required><option value="">Scegli…</option>';
        foreach ($servizi as $s) $out .= '<option value="' . (int)$s['id'] . '">' . h($s['nome'] . ' - ' . pren_durata_testo($s)) . '</option>';
        $out .= '</select></label>';
    }
    $fissaR = $sposta ? 0 : (int)$o['risorsa'];
    if ($fissaR || !$imp['scegli_persona'] || count($risorse) < 2 || $sposta) {
        $out .= '<input type="hidden" name="risorsa" value="' . $fissaR . '">';
    } else {
        $out .= '<label class="form-largo pren-con"><span>Con chi</span><select name="risorsa"><option value="0">Chi è libero</option>';
        foreach ($risorse as $r) $out .= '<option value="' . (int)$r['id'] . '">' . h($r['nome']) . '</option>';
        $out .= '</select></label>';
    }
    $out .= '</div>';
    // 2. Il giorno e l'ora (li riempie prenota.js)
    $out .= '<fieldset class="pren-quando"><legend>Giorno</legend><div class="pren-settimana"><button type="button" class="pren-prima" aria-label="Giorni prima" disabled>&lsaquo;</button><span class="pren-mese" aria-live="polite"></span><button type="button" class="pren-dopo" aria-label="Giorni dopo">&rsaquo;</button></div>'
        . '<div class="pren-giorni" role="group" aria-label="Giorni"></div></fieldset>'
        . '<fieldset class="pren-quando"><legend>Ora</legend><div class="pren-orari" role="group" aria-label="Orari liberi"><p class="pren-nota">' . ($fisso ? 'Un momento…' : 'Scegli prima il servizio.') . '</p></div></fieldset>'
        . '<noscript><p class="modulo-errore">Per vedere gli orari liberi serve JavaScript acceso nel browser. Oppure telefonaci.</p></noscript>'
        . '<p class="pren-riepilogo" aria-live="polite" hidden></p>';
    // 3. Chi prenota (non per spostare)
    if (!$sposta) {
        $out .= '<div class="modulo-campi pren-dati">'
            . '<label><span>Nome e cognome</span><input name="nome" autocomplete="name" maxlength="150" required></label>'
            . '<label><span>Email</span><input type="email" name="email" autocomplete="email" maxlength="190" required></label>'
            . '<label><span>Telefono</span><input type="tel" name="telefono" autocomplete="tel" maxlength="30" required></label>'
            . '<label><span>Note <small>(facoltativo)</small></span><input name="note" maxlength="500"></label>'
            . '</div>'
            . '<div class="esca" aria-hidden="true"><label>Sito web <input name="sito_web" tabindex="-1" autocomplete="off"></label></div>'
            . '<label class="spunta"><input type="checkbox" name="privacy" value="1" required> <span>Ho letto l&#039;<a href="' . h((string)$imp['privacy']) . '" target="_blank">informativa privacy</a> e accetto che i miei dati servano per gestire la prenotazione.</span></label>'
            . '<label class="captcha"><input type="checkbox"> <span>Sono una persona</span> <span class="captcha-stato" aria-live="polite"></span></label>';
    }
    $out .= '<button class="btn btn-viola" type="submit">' . h($sposta ? 'Sposta qui l\'appuntamento' : ($o['pulsante'] ?: 'Prenota')) . '</button></form>';
    if (!$sposta) $out .= '<script src="' . (function_exists('sito_versione_asset') ? sito_versione_asset('moduli.js') : cms_base() . '/sito/assets/moduli.js') . '" defer></script>';
    return $out . '</section>';
}

function pren_durata_testo(array $s): string {
    $d = (int)$s['durata'];
    $t = $d >= 60 ? intdiv($d, 60) . ' h' . ($d % 60 ? ' ' . ($d % 60) . ' min' : '') : $d . ' min';
    return $t . (trim((string)$s['prezzo']) !== '' ? ', ' . $s['prezzo'] : '');
}
