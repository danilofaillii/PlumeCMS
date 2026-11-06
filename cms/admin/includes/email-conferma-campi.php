<?php
/**
 * 2.7.0: i campi dell'email di conferma dell'iscrizione, uguali in Form (scheda di un form, riquadro Email di conferma) e in
 * Newsletter > Impostazioni (valori comuni e modulo della newsletter del sito). Motore in includes/newsletter-email.php,
 * anteprima e prova in admin/email-conferma-api.php, comportamenti in assets/email-conferma.js (niente gestori in linea).
 *
 * I campi si chiamano ce[<parte>]. Vuoto vuol dire "come sopra": il valore comune, se c'è, altrimenti il testo di partenza.
 * Sotto ogni campo l'aiuto dice cosa si usa quando è vuoto; "Riempi i campi vuoti" li copia nei campi per partire da lì.
 */

require_once dirname(__DIR__, 2) . '/includes/newsletter.php';
require_once dirname(__DIR__, 2) . '/includes/newsletter-email.php';

/**
 * $valori:   le parti già scritte qui (con le emoji in chiaro)
 * $eredita:  quello che un campo vuoto usa (nl_email_ereditati())
 * $contesto: 'form' oppure 'comuni'
 * $origine:  per gli aiuti, da dove arriva il valore ereditato, per parte: 'comune' o 'partenza'
 */
function ec_campi_html(array $valori, array $eredita, string $contesto, array $origine): string {
    $campi = [
        'oggetto' => ['Oggetto', 'input', 'Una riga sola, senza a capo.'],
        'intestazione' => ['Intestazione in cima', 'input', 'La riga scura in cima all\'email. Cambia solo l\'email di conferma: le altre email del sito (avvisi dei form, file, campagne) tengono la loro.'],
        'titolo' => ['Titolo dentro l\'email', 'input', ''],
        'testo' => ['Testo', 'textarea', 'Una riga vuota separa i paragrafi. Puoi usare &lt;b&gt;grassetto&lt;/b&gt;, &lt;i&gt;corsivo&lt;/i&gt; e i link &lt;a href="https://..."&gt;: tutto il resto (script, stili, eventi) si toglie da solo.'],
        'pulsante' => ['Testo del pulsante', 'input', 'Il pulsante porta sempre al link che conferma l\'iscrizione: quello non si cambia.'],
        'colore' => ['Colore del pulsante', 'colore', 'Un colore come #2563eb. Il testo del pulsante diventa bianco o nero da solo, per leggersi bene.'],
        'sotto' => ['Frase sotto il pulsante', 'input', 'Sotto la frase esce sempre il link intero, per chi non riesce ad aprire il pulsante.'],
        'firma' => ['Firma', 'textarea', 'Cambia solo l\'email di conferma: le altre email del sito tengono la firma di sempre.'],
        'nota' => ['Nota in fondo', 'textarea', 'In piccolo, sotto la firma.'],
    ];
    $da = fn(string $k) => ($origine[$k] ?? 'partenza') === 'comune' ? 'il testo comune di Newsletter, Impostazioni' : 'il testo di partenza';
    $o = '<div class="ec-campi">';
    foreach ($campi as $k => [$etichetta, $tipo, $aiuto]) {
        $id = 'ec-' . $k;
        $v = (string)($valori[$k] ?? '');
        $e = (string)($eredita[$k] ?? '');
        [$max] = NL_EMAIL_PARTI[$k];
        $attr = ' id="' . $id . '" name="ce[' . $k . ']" maxlength="' . $max . '" aria-describedby="' . $id . '-aiuto" data-ec-eredita="' . h($e) . '" autocomplete="off"';
        $o .= '<div class="form-group ec-campo"><label for="' . $id . '">' . h($etichetta) . '</label>';
        if ($tipo === 'textarea') $o .= '<textarea' . $attr . ' rows="' . ($k === 'testo' ? 7 : 2) . '">' . h($v) . '</textarea>';
        elseif ($tipo === 'colore') $o .= '<div class="ec-colore"><input type="text"' . $attr . ' value="' . h($v) . '" pattern="#[0-9a-fA-F]{6}" spellcheck="false">'
            . '<input type="color" value="' . h($v !== '' ? $v : ($e !== '' ? $e : NL_EMAIL_COLORE)) . '" data-ec-colore="' . $id . '" aria-label="Scegli il colore del pulsante"></div>';
        else $o .= '<input type="text"' . $attr . ' value="' . h($v) . '">';
        $vuoto = $e !== '' ? 'Vuoto: si usa ' . $da($k) . ':<span class="ec-eredita">' . nl2br(h($e), false) . '</span>' : 'Vuoto: niente.';
        $o .= '<div class="hint" id="' . $id . '-aiuto">' . ($aiuto !== '' ? $aiuto . '<br>' : '') . $vuoto . '</div></div>';
    }
    return $o . '</div>';
}

/** Il riquadro dei segnaposti, uguale nelle due pagine. */
function ec_segnaposti_html(string $contesto): string {
    return '<details class="pn-aiuto ec-segnaposti"><summary>Come si scrivono il nome e le altre parti che cambiano</summary>'
        . '<p>Nei campi puoi scrivere questi segnaposti: nell\'email diventano il valore vero.</p><ul>'
        . '<li><code>{nome}</code>: il nome di chi si iscrive. Se non l\'ha scritto, sparisce insieme allo spazio davanti ("Ciao {nome}," diventa "Ciao,").</li>'
        . '<li><code>{lista}</code>: ' . ($contesto === 'form' ? 'per cosa si iscrive: il titolo del form (o il suo nome, o il nome della lista).' : 'per cosa si iscrive: "newsletter" con il nome della newsletter; da un form, il titolo del form.') . '</li>'
        . '<li><code>{giorni}</code>: quanti giorni vale il link di conferma (' . MODULI_CONFERMA_GIORNI . ').</li>'
        . '<li><code>{sito}</code>: il nome del sito (' . h(nl_email_sito()) . ').</li></ul>'
        . '<p>Il link del pulsante è sempre quello vero, che conferma l\'iscrizione: non si scrive e non si cambia. Le emoji vanno bene.</p></details>';
}

/** Anteprima e prova: i pulsanti, il messaggio e la cornice. */
function ec_anteprima_html(): string {
    $io = (string)(current_user()['email'] ?? '');
    return '<div class="ec-anteprima-barra">'
        . '<button type="button" class="btn btn-secondary btn-sm" data-ec-riempi>Riempi i campi vuoti con i testi in uso</button>'
        . '<button type="button" class="btn btn-secondary btn-sm" data-ec-anteprima>Aggiorna l\'anteprima</button>'
        . '<button type="button" class="btn btn-secondary btn-sm" data-ec-prova>Manda una prova a me</button></div>'
        . '<p class="hint">La prova va a ' . ($io !== '' ? '<b>' . h($io) . '</b>, l\'email del tuo utente' : 'l\'email del tuo utente (Il mio account)') . ', con un link finto che non conferma niente. Anteprima e prova usano quello che c\'è scritto adesso nei campi, anche se non hai ancora salvato.</p>'
        . '<p class="ec-stato" role="status" aria-live="polite"></p>'
        . '<p class="ec-oggetto-vedi">Oggetto: <b class="ec-oggetto-testo">-</b></p>'
        . '<iframe class="ec-cornice" title="Anteprima dell\'email di conferma" sandbox></iframe>';
}

/** Lo stile del riquadro (una volta per pagina). */
function ec_stile_html(): string {
    return '<style>
.ec-campi .form-group{margin-bottom:.8rem}
.ec-campi input[type=text],.ec-campi textarea{width:100%;padding:.45rem .6rem;border:1px solid var(--bordo-campo);border-radius:8px;font:inherit;font-size:.9rem;background:var(--campo);color:var(--text)}
.ec-campi textarea{resize:vertical;min-height:3.2rem}
.ec-campi .hint{font-size:.78rem;color:var(--text-muted);margin-top:.25rem}
.ec-eredita{display:block;color:var(--text);border-left:3px solid var(--border);padding:.15rem 0 .15rem .6rem;margin-top:.2rem;overflow-wrap:anywhere}
.ec-dettagli>summary{cursor:pointer;font-weight:600;padding:.4rem 0;color:var(--accento-testo)}
.ec-dettagli[open]>summary{margin-bottom:.6rem}
.ec-colore{display:flex;gap:.5rem;align-items:center}.ec-colore input[type=text]{flex:1;min-width:0}
.ec-colore input[type=color]{width:44px;height:44px;padding:2px;border:1px solid var(--bordo-campo);border-radius:8px;background:var(--campo);flex:0 0 auto;cursor:pointer}
.ec-anteprima-barra{display:flex;flex-wrap:wrap;gap:.5rem;margin:.6rem 0 .3rem}
.ec-anteprima-barra .btn{white-space:normal;text-align:left;min-height:44px}
.sc-griglia>*{min-width:0}
.ec-stato:empty{display:none}.ec-stato{margin:.4rem 0;font-size:.88rem}
.ec-stato.errore{color:var(--errore-testo)}.ec-stato.fatto{color:var(--successo-testo)}
.ec-oggetto-vedi{font-size:.88rem;margin:.4rem 0;overflow-wrap:anywhere}
.ec-cornice{display:block;width:100%;height:560px;border:1px solid var(--border);border-radius:8px;background:#f4f4f6 /* fisso: il fondo delle email */}
.ec-segnaposti{margin:0 0 .8rem}
</style>';
}
