<?php
/**
 * Gli indirizzi pubblici dell'addon Raccolta recensioni (evento sito_percorso, dentro sito/index.php):
 *   GET  /recensione/?i=&t=[&s=1..5]   il modulo con le stelle (link personale firmato, con scadenza, una volta sola)
 *   POST /recensione/                  salva la recensione (i e t nel modulo), poi il grazie (e Google, se è contento)
 *   GET  /recensione/esci/?i=&t=       "Non scrivermi più": la pagina con il pulsante
 *   POST /recensione/esci/             conferma: l'indirizzo non riceve più inviti
 */
require_once __DIR__ . '/recensioni.php';

function rec_sito(string $percorso, array $q): void {
    rec_schema();
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if ($percorso === '/recensione/') rec_sito_modulo($q, $post);
    if ($percorso === '/recensione/esci/') rec_sito_esci($q, $post);
}

function rec_asset(string $f): string {
    $p = dirname(__DIR__) . '/assets/' . $f;
    return cms_base() . '/addon/recensioni/assets/' . $f . (is_file($p) ? '?v=' . filemtime($p) : '');
}

function rec_stessa_origine(): bool {
    $o = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($o === '') return true;
    $porta = fn($u) => isset($u['port']) ? ':' . $u['port'] : '';
    $u = parse_url($o) ?: [];
    $sito = parse_url(cms_url_sito()) ?: [];
    $chi = strtolower(($u['host'] ?? '') . $porta($u));
    return $chi !== '' && in_array($chi, [strtolower((string)($_SERVER['HTTP_HOST'] ?? '')), strtolower(($sito['host'] ?? '') . $porta($sito))], true);
}

function rec_pagina(int $codice, string $titolo, string $corpo): never {
    sito_html($codice, ['titolo' => $titolo . ' - ' . SITO_NOME, 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []],
        '<div class="wrap"><article class="pagina-generica cortesia rec-pagina"><span class="occhiello">Recensione</span><h1>' . h($titolo) . '</h1><div class="corpo">' . $corpo . '</div></article></div>',
        '<link rel="stylesheet" href="' . h(rec_asset('recensioni.css')) . '">');
}

function rec_sito_rotto(): never {
    rec_pagina(404, 'Questo link non funziona', '<p>Il link è sbagliato oppure non vale più. Controlla di averlo copiato intero dall\'email.</p><p><a class="btn btn-viola" href="/">Vai al sito</a></p>');
}

function rec_sito_modulo(array $q, bool $post): never {
    $src = $post ? $_POST : $q;
    $l = rec_da_link($src['i'] ?? null, $src['t'] ?? null);
    if (!$l) rec_sito_rotto();
    $i = $l['invito'];
    if ($l['stato'] === 'usato') rec_pagina(200, 'Grazie, l\'hai già lasciata', '<p>Con questo link la recensione è già arrivata. Grazie ancora del tempo che ci hai dedicato.</p>');
    if ($l['stato'] === 'scaduto') rec_pagina(410, 'Questo link è scaduto', '<p>Il link per lasciare la recensione valeva ' . (int)rec_impostazioni()['validita'] . ' giorni. Se vuoi dirci com\'è andata, scrivici: ci fa piacere lo stesso.</p>');
    $errore = '';
    if ($post) {
        if (!rec_stessa_origine()) rec_sito_rotto();
        $stelle = is_string($_POST['stelle'] ?? null) && preg_match('/^[1-5]$/', $_POST['stelle']) ? (int)$_POST['stelle'] : 0;
        $testo = rec_testo($_POST['testo'] ?? '', 3000, true);
        $nome = rec_testo($_POST['nome'] ?? '', 100);
        if (!$stelle) $errore = 'Scegli quante stelle, da 1 a 5.';
        elseif (mb_strlen($testo) < 5) $errore = 'Scrivi due parole su com\'è andata.';
        elseif (mb_strlen($nome) < 2) $errore = 'Scrivi il nome da mostrare (per esempio il tuo nome e l\'iniziale del cognome).';
        elseif (empty($_POST['consenso'])) $errore = 'Per pubblicarla ci serve la spunta sul consenso.';
        else {
            $r = rec_salva($i, $stelle, $testo, $nome);
            if (is_string($r)) rec_pagina(200, $r === 'scaduto' ? 'Questo link è scaduto' : 'Grazie, l\'hai già lasciata', '<p>' . ($r === 'scaduto' ? 'Il link non vale più.' : 'Con questo link la recensione è già arrivata.') . '</p>');
            $imp = rec_impostazioni();
            $google = trim((string)$imp['google_link']);
            $alGoogle = $google !== '' && preg_match('~^https://~', $google) && $stelle >= max(1, min(5, (int)$imp['google_da']));
            rec_pagina(200, 'Grazie!', '<p class="rec-ok" role="status">La tua recensione è arrivata. La leggiamo e la pubblichiamo presto sul sito.</p>'
                . ($alGoogle ? '<div class="rec-google"><p><b>Ti va di lasciarla anche su Google?</b> Ci aiuta tantissimo: chi ci cerca la vede subito. Puoi copiare lo stesso testo.</p>'
                    . '<p class="rec-copia">' . nl2br(h($testo)) . '</p><p><a class="btn btn-viola" href="' . h($google) . '" target="_blank" rel="noopener">Lasciala su Google</a></p></div>' : '')
                . '<p><a href="/">Torna al sito</a></p>');
        }
    }
    $pre = $post ? (int)($_POST['stelle'] ?? 0) : (is_string($q['s'] ?? null) && preg_match('/^[1-5]$/', $q['s']) ? (int)$q['s'] : 0);
    $stelle = '';
    for ($n = 5; $n >= 1; $n--) $stelle .= '<input type="radio" name="stelle" id="rec-s' . $n . '" value="' . $n . '"' . ($pre === $n ? ' checked' : '') . ' required><label for="rec-s' . $n . '" title="' . $n . ' su 5"><span class="rec-nascosto">' . $n . ($n === 1 ? ' stella' : ' stelle') . '</span>&#9733;</label>';
    $nome = $post ? rec_testo($_POST['nome'] ?? '', 100) : rec_nome_breve((string)$i['nome']);
    $corpo = ($errore !== '' ? '<p class="modulo-errore" role="alert">' . h($errore) . '</p>' : '')
        . '<p>' . ($i['servizio'] !== '' ? 'Com\'è andato <b>' . h((string)$i['servizio']) . '</b>? ' : '') . 'Bastano le stelle e due righe.</p>'
        . '<form method="post" action="' . h(cms_sito_base() . '/recensione/') . '" class="modulo rec-modulo"><input type="hidden" name="i" value="' . (int)$i['id'] . '"><input type="hidden" name="t" value="' . h(rec_firma((int)$i['id'], (string)$i['codice'])) . '">'
        . '<fieldset class="rec-stelle"><legend>Quante stelle?</legend><div class="rec-stelle-riga">' . $stelle . '</div></fieldset>'
        . '<div class="modulo-campi"><label class="form-largo"><span>Com\'è andata?</span><textarea name="testo" rows="5" maxlength="3000" required placeholder="Due righe su cosa ti è piaciuto (o no).">' . ($post ? h(rec_testo($_POST['testo'] ?? '', 3000, true)) : '') . '</textarea></label>'
        . '<label class="form-largo"><span>Il nome da mostrare</span><input name="nome" maxlength="100" required value="' . h($nome) . '"></label></div>'
        . '<label class="spunta"><input type="checkbox" name="consenso" value="1" required> <span>Acconsento che la mia recensione, con il nome qui sopra, sia pubblicata su questo sito. Posso chiedere di toglierla quando voglio.</span></label>'
        . '<button class="btn btn-viola" type="submit">Manda la recensione</button></form>';
    rec_pagina($errore !== '' ? 422 : 200, 'Com\'è andata?', $corpo);
}

function rec_sito_esci(array $q, bool $post): never {
    $src = $post && isset($_POST['i']) ? $_POST : $q; // anche il POST "in un clic" dei programmi di posta, con i e t nell'indirizzo
    $l = rec_da_link($src['i'] ?? null, $src['t'] ?? null, 'esci');
    if (!$l) rec_sito_rotto();
    $i = $l['invito'];
    if ($post && rec_stessa_origine()) {
        rec_escludi((string)$i['email']);
        rec_pagina(200, 'Fatto', '<p class="rec-ok" role="status">Non ti scriveremo più per chiederti una recensione.</p>');
    }
    rec_pagina(200, 'Non vuoi più queste email?', '<p>Se confermi, all\'indirizzo <b>' . h((string)$i['email']) . '</b> non chiederemo più recensioni.</p>'
        . '<form method="post" action="' . h(cms_sito_base() . '/recensione/esci/') . '"><input type="hidden" name="i" value="' . (int)$i['id'] . '"><input type="hidden" name="t" value="' . h(rec_firma((int)$i['id'], (string)$i['codice'], 'esci')) . '">'
        . '<button class="btn btn-viola" type="submit">Sì, non scrivetemi più</button></form>');
}
