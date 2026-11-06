<?php
/**
 * Area riservata: le pagine pubbliche (evento sito_percorso, chiamato da sito/index.php prima della mappa del sito).
 *
 *   /accedi/                      email e password, "Resta collegato"
 *   /iscriviti/                   nome, email, password, privacy, captcha (non c'è con l'iscrizione solo su invito)
 *   /conferma-iscrizione/?t=      il link dell'email: un pulsante (i filtri antispam aprono i link da soli)
 *   /password-dimenticata/        l'email; il link arriva solo se l'account c'è, ma la risposta è sempre la stessa
 *   /nuova-password/?t=           la password nuova (anche per chi è stato invitato)
 *   /conferma-email/?t=           il cambio dell'indirizzo email, confermato dal nuovo indirizzo
 *   /esci/                        POST: chiude la sessione di questo browser
 *   /area-riservata/              la pagina del membro: file, contenuti per i membri, corsi (filtro membri_area)
 *   /area-riservata/profilo/      nome, email, password, cancella il mio account
 *   /area-riservata/file/<nome>/  un file riservato di Download dato ai membri
 *
 * Ogni POST: token _mt (membri_csrf_ok), Origin e Referer del sito. Ogni pagina: noindex e Cache-Control private, no-store.
 */
require_once __DIR__ . '/membri.php';

const MEMBRI_MESSAGGI = [
    'uscito' => 'Sei uscito. A presto.',
    'password' => 'Password cambiata. Entra con quella nuova.',
    'benvenuto' => 'Benvenuto: il tuo account è attivo.',
    'profilo' => 'Fatto: le modifiche sono salvate.',
    'pw-profilo' => 'Password cambiata. Sugli altri dispositivi devi entrare di nuovo.',
    'email-inviata' => 'Ti abbiamo mandato un link al nuovo indirizzo: l\'email cambia quando lo apri.',
    'email-cambiata' => 'Fatto: da adesso entri con il nuovo indirizzo email.',
    'eliminato' => 'Il tuo account è stato cancellato, con tutti i tuoi dati. Grazie di essere passato.',
    'entra' => 'Per vedere questa pagina entra con la tua email e la tua password.',
];

/** Una pagina dell'area riservata nel vestito del sito (header, footer, stile). Mai in cache, mai nei motori di ricerca. */
function membri_html(string $titolo, string $corpo, int $codice = 200): never {
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex');
    $meta = ['titolo' => $titolo . ' - ' . SITO_NOME, 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []];
    $GLOBALS['sito_senza_popup'] = true;
    sito_html($codice, $meta, $corpo, membri_testa(true));
}

/** Il foglio di stile (e, se serve, lo script del captcha) dell'area riservata. */
function membri_testa(bool $script = false): string {
    $dir = dirname(__DIR__) . '/assets/';
    $u = cms_modulo_url('membri') . '/assets/';
    return '<link rel="stylesheet" href="' . h($u . 'membri.css?v=' . (int)@filemtime($dir . 'membri.css')) . '">'
        . ($script ? '<script src="' . h($u . 'membri.js?v=' . (int)@filemtime($dir . 'membri.js')) . '" defer></script>' : '');
}

/** Il riquadro della pagina: occhiello, titolo, eventuale messaggio, contenuto. */
function membri_riquadro(string $titolo, string $dentro, string $messaggio = '', string $errore = '', string $occhiello = 'Area riservata'): string {
    return '<div class="wrap"><article class="pagina-generica membri-pagina"><span class="occhiello">' . h($occhiello) . '</span><h1>' . h($titolo) . '</h1>'
        . ($messaggio !== '' ? '<p class="membri-msg membri-ok" role="status">' . h($messaggio) . '</p>' : '')
        . ($errore !== '' ? '<p class="modulo-errore membri-msg" role="alert">' . h($errore) . '</p>' : '')
        . $dentro . '</article></div>';
}

function membri_messaggio(array $q): string {
    return MEMBRI_MESSAGGI[(string)($q['m'] ?? '')] ?? '';
}

function membri_campo(string $nome, string $etichetta, string $tipo = 'text', string $valore = '', string $auto = '', string $extra = ''): string {
    return '<label><span>' . h($etichetta) . '</span><input name="' . h($nome) . '" type="' . h($tipo) . '" value="' . h($valore) . '"'
        . ($auto !== '' ? ' autocomplete="' . h($auto) . '"' : '') . ' ' . $extra . '></label>';
}

/** Il percorso a cui tornare dopo l'accesso (?torna=), solo dentro il sito. */
function membri_torna(array $q): string {
    $t = sicurezza_percorso_interno(is_string($q['torna'] ?? null) ? $q['torna'] : '', '/area-riservata/');
    return preg_match('~^/(accedi|esci|iscriviti)/~', $t) ? '/area-riservata/' : $t;
}

/** Il POST di un form dell'area riservata è buono? Altrimenti una pagina d'errore (403). */
function membri_post_ok(): void {
    if (membri_csrf_ok()) return;
    membri_html('Richiesta scaduta', membri_riquadro('Richiesta scaduta', '<p>La pagina era aperta da troppo tempo, oppure il browser non accetta i cookie di questo sito. Torna indietro, ricarica la pagina e riprova.</p>'), 403);
}

function membri_post(string $k, int $max = 200): string {
    $v = $_POST[$k] ?? '';
    return is_string($v) ? mb_substr($v, 0, $max) : '';
}

// ------------------------------------------------------------

function membri_pagina(string $percorso, array $q): void {
    if (!str_ends_with($percorso, '/')) sito_vai($percorso . '/', 301);
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if ($post) membri_post_ok();
    switch (true) {
        case $percorso === '/accedi/': membri_pagina_accedi($q, $post);
        case $percorso === '/iscriviti/': membri_pagina_iscriviti($q, $post);
        case $percorso === '/conferma-iscrizione/': membri_pagina_conferma($q, $post);
        case $percorso === '/password-dimenticata/': membri_pagina_dimenticata($q, $post);
        case $percorso === '/nuova-password/': membri_pagina_nuova_password($q, $post);
        case $percorso === '/conferma-email/': membri_pagina_conferma_email($q, $post);
        case $percorso === '/esci/':
            if ($post) { membri_esci(); sito_vai('/accedi/?m=uscito', 303); }
            $m = membri_corrente();
            membri_html('Esci', membri_riquadro('Vuoi uscire?', $m ? '<form method="post" action="/esci/">' . membri_csrf_campo() . '<button class="btn btn-viola" type="submit">Esci dall\'area riservata</button></form>' : '<p>Non sei entrato.</p><p><a class="btn btn-viola" href="/accedi/">Accedi</a></p>'));
        case $percorso === '/area-riservata/': membri_pagina_area($q);
        case $percorso === '/area-riservata/profilo/': membri_pagina_profilo($q, $post);
        case (bool)preg_match('~^/area-riservata/file/([a-z0-9][a-z0-9._-]{0,120})/$~', $percorso, $f): membri_manda_file($f[1]);
    }
    // Un altro indirizzo sotto /area-riservata/ può essere di un altro addon (filtro membri_area_percorso); altrimenti 404.
    $m = membri_corrente();
    cms_evento('membri_area_percorso', $percorso, $q, $m);
    sito_non_trovato();
}

/** Chi non è entrato va alla pagina di accesso, e dopo torna qui. */
function membri_richiedi(string $torna): array {
    $m = membri_corrente();
    if ($m) return $m;
    sito_vai('/accedi/?m=entra&torna=' . rawurlencode($torna), 303);
}

// ------------------------------------------------------------ Accedi

function membri_modulo_accedi(string $email, string $torna, bool $ricorda = false): string {
    $iscrizione = membri_cfg()['modo'] !== 'invito';
    return '<section class="modulo membri-modulo"><form method="post" action="/accedi/" id="membri-accedi">' . membri_csrf_campo()
        . '<input type="hidden" name="torna" value="' . h($torna) . '">'
        . '<div class="modulo-campi membri-campi">'
        . membri_campo('email', 'Email', 'email', $email, 'email', 'required maxlength="190"')
        . membri_campo('password', 'Password', 'password', '', 'current-password', 'required maxlength="200"')
        . '</div>'
        . '<label class="spunta"><input type="checkbox" name="ricorda" value="1"' . ($ricorda ? ' checked' : '') . '> <span>Resta collegato su questo dispositivo per ' . MEMBRI_GIORNI_RICORDA . ' giorni</span></label>'
        . '<p class="membri-azioni"><button class="btn btn-viola" type="submit">Accedi</button> <a href="/password-dimenticata/">Password dimenticata?</a></p>'
        . '</form>'
        . ($iscrizione ? '<p class="membri-sotto">Non hai ancora un account? <a href="/iscriviti/">Iscriviti</a>.</p>' : '')
        . '</section>';
}

function membri_pagina_accedi(array $q, bool $post): never {
    $torna = membri_torna($post ? $_POST : $q);
    if (!$post) {
        if (membri_corrente()) sito_vai($torna, 303);
        membri_html('Accedi', membri_riquadro('Accedi', membri_modulo_accedi('', $torna), membri_messaggio($q)));
    }
    $email = mb_strtolower(trim(membri_post('email', 190)));
    $r = membri_verifica_accesso($email, membri_post('password', 200));
    if (isset($r['errore'])) membri_html('Accedi', membri_riquadro('Accedi', membri_modulo_accedi($email, $torna, !empty($_POST['ricorda'])), '', $r['errore']), 200);
    membri_entra($r['membro'], !empty($_POST['ricorda']));
    sito_vai($torna, 303);
}

// ------------------------------------------------------------ Iscriviti

function membri_modulo_iscriviti(array $v): string {
    return '<section class="modulo membri-modulo"><form method="post" action="/iscriviti/" id="membri-iscriviti" data-membri-captcha>' . membri_csrf_campo()
        . '<input type="hidden" name="captcha" value="">'
        . '<div class="modulo-campi membri-campi">'
        . membri_campo('nome', 'Nome', 'text', (string)($v['nome'] ?? ''), 'name', 'required maxlength="120"')
        . membri_campo('email', 'Email', 'email', (string)($v['email'] ?? ''), 'email', 'required maxlength="190"')
        . membri_campo('password', 'Password (almeno 12 caratteri)', 'password', '', 'new-password', 'required minlength="12" maxlength="200"')
        . membri_campo('password2', 'Ripeti la password', 'password', '', 'new-password', 'required minlength="12" maxlength="200"')
        . '</div>'
        . '<div class="esca" aria-hidden="true"><label>Sito web <input name="sito_web" tabindex="-1" autocomplete="off"></label></div>'
        . '<label class="spunta"><input type="checkbox" name="privacy" value="1" required> <span>Ho letto l&#039;<a href="/privacy-policy/" target="_blank">informativa privacy</a> e accetto il trattamento dei miei dati per avere un account sul sito.</span></label>'
        . '<label class="captcha"><input type="checkbox"> <span>Sono una persona</span> <span class="captcha-stato" aria-live="polite"></span></label>'
        . '<p class="membri-azioni"><button class="btn btn-viola" type="submit">Crea il mio account</button></p>'
        . '</form><p class="membri-sotto">Hai già un account? <a href="/accedi/">Accedi</a>.</p></section>';
}

function membri_pagina_iscriviti(array $q, bool $post): never {
    $cfg = membri_cfg();
    if ($cfg['modo'] === 'invito') {
        membri_html('Iscriviti', membri_riquadro('Iscrizioni su invito', '<p>Su questo sito l\'area riservata è solo su invito: l\'account lo crea il sito, e ti arriva un\'email per scegliere la password.</p><p><a class="btn btn-filo" href="/accedi/">Hai già un account? Accedi</a></p>'));
    }
    if (membri_corrente()) sito_vai('/area-riservata/', 303);
    $intro = '<p>Con un account entri nell\'area riservata del sito' . ($cfg['modo'] === 'approvazione' ? '. Dopo la conferma dell\'email, l\'iscrizione la approviamo noi: ti scriviamo quando è attiva.' : ': ti mandiamo un\'email per confermare l\'indirizzo, e sei dentro.') . '</p>';
    if (!$post) membri_html('Iscriviti', membri_riquadro('Iscriviti', $intro . membri_modulo_iscriviti([])));

    $v = ['nome' => membri_testo(membri_post('nome', 200)), 'email' => mb_strtolower(trim(membri_post('email', 190)))];
    $errore = '';
    $pw = membri_post('password', 300); $pw2 = membri_post('password2', 300);
    if (membri_post('sito_web') !== '') $errore = 'Richiesta non valida.';
    elseif ($v['nome'] === '') $errore = 'Scrivi il tuo nome.';
    elseif (!membri_email_valida($v['email'])) $errore = 'Controlla l\'indirizzo email: sembra incompleto.';
    elseif (($e = membri_password_errore($pw, $pw2, $v['email'])) !== '') $errore = $e;
    elseif (empty($_POST['privacy'])) $errore = 'Per creare l\'account serve la spunta sull\'informativa privacy.';
    elseif (!moduli_captcha_valido(membri_post('captcha', 4000))) $errore = 'Spunta la casella "Sono una persona" e aspetta il segno verde.';
    elseif (!sicurezza_limite('membri-iscrizione:' . sicurezza_ip(), 5, 3600)) $errore = 'Da questo indirizzo sono arrivate molte iscrizioni. Riprova fra un\'ora.';
    if ($errore !== '') membri_html('Iscriviti', membri_riquadro('Iscriviti', $intro . membri_modulo_iscriviti($v), '', $errore));

    $c = membri_per_email($v['email']);
    $nome = membri_nome_sito();
    if (!$c) {
        $id = membri_crea($v['email'], $v['nome'], $pw, 'da_confermare');
        $t = membri_token_crea('conferma', $id, MEMBRI_ORE_CONFERMA * 60);
        membri_manda($v['email'], 'Conferma la tua iscrizione a ' . $nome, 'Ci sei quasi', '<p>Ciao ' . h($v['nome']) . ', per attivare il tuo account su ' . h($nome) . ' premi il pulsante qui sotto. Il link vale ' . MEMBRI_ORE_CONFERMA . ' ore.</p><p>Se non sei stato tu, ignora questa email: senza conferma l\'account non esiste.</p>', 'Confermo l\'iscrizione', membri_url('/conferma-iscrizione/?t=' . $t));
    } elseif ($c['stato'] === 'da_confermare') {
        // Un link nuovo, che porta con sé la password di questa iscrizione (la applica solo chi apre l'email).
        $t = membri_token_crea('conferma', (int)$c['id'], MEMBRI_ORE_CONFERMA * 60, sicurezza_password_hash($pw));
        membri_manda($v['email'], 'Conferma la tua iscrizione a ' . $nome, 'Ci sei quasi', '<p>Per attivare il tuo account su ' . h($nome) . ' premi il pulsante qui sotto. Il link vale ' . MEMBRI_ORE_CONFERMA . ' ore.</p><p>Se non sei stato tu, ignora questa email.</p>', 'Confermo l\'iscrizione', membri_url('/conferma-iscrizione/?t=' . $t));
    } else {
        // L'account c'è già: lo diciamo solo al proprietario dell'email, non a chi ha compilato il modulo.
        membri_manda($v['email'], 'Hai già un account su ' . $nome, 'Hai già un account', '<p>Qualcuno, forse tu, ha provato a iscriversi a ' . h($nome) . ' con questo indirizzo, che ha già un account. Se hai dimenticato la password, scegline una nuova dal pulsante qui sotto.</p><p>Se non sei stato tu, ignora questa email: non è cambiato niente.</p>', 'Scegli una password nuova', membri_url('/password-dimenticata/'));
    }
    membri_html('Controlla la tua email', membri_riquadro('Controlla la tua email', '<p>Ti abbiamo scritto all\'indirizzo che hai indicato. Apri l\'email e premi il pulsante per confermare: da lì sei dentro.</p><p>Se entro qualche minuto la casella è vuota, guarda nella cartella spam o in quella delle promozioni.</p>'));
}

// ------------------------------------------------------------ Conferma dell'iscrizione

function membri_pagina_conferma(array $q, bool $post): never {
    $t = (string)($post ? membri_post('t', 80) : (is_string($q['t'] ?? null) ? $q['t'] : ''));
    $ok = membri_token_leggi('conferma', $t);
    $m = $ok ? membri_leggi($ok['membro_id']) : null;
    if (!$m || $m['stato'] !== 'da_confermare') {
        membri_html('Link non valido', membri_riquadro('Questo link non vale più', '<p>Il link è scaduto oppure è già stato usato. Se il tuo account è già attivo, <a href="/accedi/">accedi</a>; altrimenti <a href="/iscriviti/">iscriviti di nuovo</a> con la stessa email: ti arriva un link nuovo.</p>'), 410);
    }
    if (!$post) {
        membri_html('Conferma l\'iscrizione', membri_riquadro('Conferma l\'iscrizione', '<p>Un ultimo passo: premi il pulsante per attivare l\'account di <b>' . h($m['email']) . '</b>.</p><form method="post" action="/conferma-iscrizione/">' . membri_csrf_campo() . '<input type="hidden" name="t" value="' . h($t) . '"><button class="btn btn-viola" type="submit">Confermo l\'iscrizione</button></form>'));
    }
    membri_token_leggi('conferma', $t, true);
    if ($ok['dato'] !== '' && str_starts_with($ok['dato'], '$')) db()->prepare('UPDATE ' . tbl('membri') . ' SET password_hash = ? WHERE id = ?')->execute([$ok['dato'], (int)$m['id']]);
    if (membri_cfg()['modo'] === 'approvazione') {
        membri_imposta_stato((int)$m['id'], 'in_attesa');
        db()->prepare('UPDATE ' . tbl('membri') . ' SET confermato = NOW() WHERE id = ?')->execute([(int)$m['id']]);
        if (membri_cfg()['avvisa_admin'] && ($a = posta_notifica_email()) !== '') {
            membri_manda($a, 'Nuova iscrizione da approvare', 'Nuova iscrizione da approvare', '<p>' . h($m['nome']) . ' (' . h($m['email']) . ') ha confermato l\'email e aspetta la tua approvazione.</p><p>Nel pannello: Membri, Elenco dei membri, pulsante Attiva.</p>', 'Apri il pannello', cms_url_pannello() . 'addon-pagina.php?a=membri&p=elenco&stato=in_attesa');
        }
        membri_html('Email confermata', membri_riquadro('Email confermata', '<p>Grazie. Adesso la tua iscrizione aspetta la nostra approvazione: ti scriviamo appena è attiva.</p>'));
    }
    membri_imposta_stato((int)$m['id'], 'attivo');
    if (membri_cfg()['avvisa_admin'] && ($a = posta_notifica_email()) !== '') {
        membri_manda($a, 'Nuovo membro: ' . $m['nome'], 'Un nuovo membro', '<p>' . h($m['nome']) . ' (' . h($m['email']) . ') si è iscritto all\'area riservata.</p>');
    }
    membri_entra(membri_leggi((int)$m['id']), false);
    sito_vai('/area-riservata/?m=benvenuto', 303);
}

// ------------------------------------------------------------ Password dimenticata e nuova password

function membri_pagina_dimenticata(array $q, bool $post): never {
    $modulo = '<p>Scrivi l\'email con cui ti sei iscritto: ti mandiamo un link per scegliere una password nuova. Vale ' . MEMBRI_MINUTI_PASSWORD . ' minuti.</p>'
        . '<section class="modulo membri-modulo"><form method="post" action="/password-dimenticata/" id="membri-dimenticata">' . membri_csrf_campo()
        . '<div class="modulo-campi membri-campi">' . membri_campo('email', 'Email', 'email', '', 'email', 'required maxlength="190"') . '</div>'
        . '<p class="membri-azioni"><button class="btn btn-viola" type="submit">Mandami il link</button> <a href="/accedi/">Torna all\'accesso</a></p></form></section>';
    if (!$post) membri_html('Password dimenticata', membri_riquadro('Password dimenticata', $modulo));
    $email = mb_strtolower(trim(membri_post('email', 190)));
    $limiteIp = sicurezza_limite('membri-reset-ip:' . sicurezza_ip(), 10, 3600);
    $limiteEmail = sicurezza_limite('membri-reset-email:' . hash('sha256', $email), 3, 3600);
    if (!$limiteIp) membri_html('Password dimenticata', membri_riquadro('Password dimenticata', $modulo, '', 'Da questo indirizzo sono arrivate molte richieste. Riprova fra un\'ora.'));
    $m = $limiteEmail && membri_email_valida($email) ? membri_per_email($email) : null;
    if ($m && in_array($m['stato'], ['attivo', 'invitato'], true)) {
        $scopo = $m['stato'] === 'invitato' ? 'invito' : 'password';
        $t = membri_token_crea($scopo, (int)$m['id'], $scopo === 'invito' ? MEMBRI_GIORNI_INVITO * 1440 : MEMBRI_MINUTI_PASSWORD);
        membri_manda($m['email'], 'Una password nuova per ' . membri_nome_sito(), 'Scegli una password nuova', '<p>Hai chiesto una password nuova per l\'area riservata di ' . h(membri_nome_sito()) . '. Premi il pulsante: il link vale ' . ($scopo === 'invito' ? MEMBRI_GIORNI_INVITO . ' giorni' : MEMBRI_MINUTI_PASSWORD . ' minuti') . ' e si usa una volta sola.</p><p>Se non sei stato tu, ignora questa email: la password resta quella di prima.</p>', 'Scegli la password', membri_url('/nuova-password/?t=' . $t));
    }
    membri_html('Controlla la tua email', membri_riquadro('Controlla la tua email', '<p>Se l\'indirizzo che hai scritto ha un account, fra poco ti arriva un\'email con il link per scegliere una password nuova.</p><p>Non arriva niente? Guarda nella cartella spam, oppure controlla di aver scritto l\'email con cui ti sei iscritto.</p><p><a class="btn btn-filo" href="/accedi/">Torna all\'accesso</a></p>'));
}

function membri_pagina_nuova_password(array $q, bool $post): never {
    $t = (string)($post ? membri_post('t', 80) : (is_string($q['t'] ?? null) ? $q['t'] : ''));
    $scopo = 'password';
    $ok = membri_token_leggi('password', $t);
    if (!$ok && ($ok = membri_token_leggi('invito', $t))) $scopo = 'invito';
    $m = $ok ? membri_leggi($ok['membro_id']) : null;
    if (!$m || ($scopo === 'invito' ? $m['stato'] !== 'invitato' : $m['stato'] !== 'attivo')) {
        membri_html('Link non valido', membri_riquadro('Questo link non vale più', '<p>Il link è scaduto oppure è già stato usato. Chiedine uno nuovo da <a href="/password-dimenticata/">Password dimenticata</a>.</p>'), 410);
    }
    $titolo = $scopo === 'invito' ? 'Benvenuto: scegli la tua password' : 'Scegli una password nuova';
    $modulo = fn() => '<section class="modulo membri-modulo"><form method="post" action="/nuova-password/" id="membri-nuova-password">' . membri_csrf_campo() . '<input type="hidden" name="t" value="' . h($t) . '">'
        . '<p>Account: <b>' . h($m['email']) . '</b></p><div class="modulo-campi membri-campi">'
        . membri_campo('password', 'Password nuova (almeno 12 caratteri)', 'password', '', 'new-password', 'required minlength="12" maxlength="200"')
        . membri_campo('password2', 'Ripeti la password', 'password', '', 'new-password', 'required minlength="12" maxlength="200"')
        . '</div><p class="membri-azioni"><button class="btn btn-viola" type="submit">Salva la password</button></p></form></section>';
    if (!$post) membri_html($titolo, membri_riquadro($titolo, $modulo()));
    $pw = membri_post('password', 300);
    if (($e = membri_password_errore($pw, membri_post('password2', 300), (string)$m['email'])) !== '') membri_html($titolo, membri_riquadro($titolo, $modulo(), '', $e));
    membri_token_leggi($scopo, $t, true);
    membri_password_imposta((int)$m['id'], $pw);
    membri_sessioni_chiudi((int)$m['id']);
    if ($scopo === 'invito') {
        membri_imposta_stato((int)$m['id'], 'attivo');
        membri_entra(membri_leggi((int)$m['id']), false);
        sito_vai('/area-riservata/?m=benvenuto', 303);
    }
    sito_vai('/accedi/?m=password', 303);
}

// ------------------------------------------------------------ Area riservata

function membri_pagina_area(array $q): never {
    $m = membri_richiedi('/area-riservata/');
    $cfg = membri_cfg();
    $sezioni = [];
    // I contenuti per i membri pubblicati, con il loro indirizzo.
    $contenuti = '';
    try {
        foreach (sito_contenuti() as $c) if (empty($c['rediretto']) && membri_contenuto_riservato($c)) $contenuti .= '<li><a href="' . h($c['percorso']) . '">' . h($c['titolo']) . '</a></li>';
    } catch (Throwable $e) { error_log('membri: contenuti: ' . $e->getMessage()); }
    if ($contenuti !== '') $sezioni[] = ['Da leggere', '<ul class="membri-lista">' . $contenuti . '</ul>'];
    $file = '';
    foreach (membri_file() as $x) {
        $file .= '<li><a class="membri-file" href="/area-riservata/file/' . h($x['nome']) . '/"><b>' . h($x['titolo']) . '</b> <small>' . h($x['tipo'] . ', ' . scaricabili_peso($x['peso'])) . '</small></a>'
            . ($x['descrizione'] !== '' ? '<br><small>' . h($x['descrizione']) . '</small>' : '') . '</li>';
    }
    if ($file !== '') $sezioni[] = ['File da scaricare', '<ul class="membri-lista">' . $file . '</ul>'];
    // Gli altri addon (Corsi online) aggiungono le loro sezioni: [titolo, html].
    $sezioni = (array)cms_filtro('membri_area', $sezioni, $m);
    $html = '<p class="membri-ciao">Ciao <b>' . h((string)$m['nome']) . '</b>.' . ($cfg['benvenuto'] !== '' ? ' ' . nl2br(h($cfg['benvenuto'])) : '') . '</p>';
    foreach ($sezioni as $s) if (is_array($s) && count($s) >= 2) $html .= '<section class="membri-sezione"><h2>' . h((string)$s[0]) . '</h2>' . $s[1] . '</section>';
    if (!$sezioni) $html .= '<p>Per ora qui non c\'è niente di nuovo: quando il sito pubblica qualcosa per i membri, lo trovi in questa pagina.</p>';
    $html .= '<p class="membri-azioni membri-piede"><a class="btn btn-filo" href="/area-riservata/profilo/">Il mio profilo</a> '
        . '<form method="post" action="/esci/" class="membri-esci">' . membri_csrf_campo() . '<button class="btn btn-filo" type="submit">Esci</button></form></p>';
    membri_html('Area riservata', membri_riquadro('La tua area riservata', $html, membri_messaggio($q)));
}

function membri_manda_file(string $nome): never {
    $m = membri_richiedi('/area-riservata/file/' . $nome . '/');
    $x = membri_file()[$nome] ?? null;
    if (!$x || !is_file($x['percorso'])) sito_non_trovato();
    header('Content-Type: ' . SCARICABILI_TIPI[$x['ext']][0]);
    header('Content-Disposition: attachment; filename="' . basename($x['file']) . '"');
    header('Content-Length: ' . filesize($x['percorso']));
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex');
    header('Referrer-Policy: no-referrer');
    sicurezza_intestazioni_base();
    scaricabili_conta($x['file']);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') readfile($x['percorso']);
    exit;
}

// ------------------------------------------------------------ Profilo

function membri_pagina_profilo(array $q, bool $post): never {
    $m = membri_richiedi('/area-riservata/profilo/');
    $errore = '';
    if ($post) {
        $azione = membri_post('azione', 20);
        $pwOk = function () use ($m): bool {
            $r = db()->prepare('SELECT password_hash FROM ' . tbl('membri') . ' WHERE id = ?'); $r->execute([(int)$m['id']]);
            $ok = password_verify(membri_post('attuale', 200), (string)$r->fetchColumn());
            if (!$ok) membri_tentativo_fallito((string)$m['email']);
            return $ok;
        };
        if (membri_bloccato((string)$m['email']) > 0) $errore = 'Troppe password sbagliate. Riprova fra qualche minuto.';
        elseif ($azione === 'nome') {
            $nome = membri_testo(membri_post('nome', 200));
            if ($nome === '') $errore = 'Scrivi il tuo nome.';
            else { db()->prepare('UPDATE ' . tbl('membri') . ' SET nome = ? WHERE id = ?')->execute([$nome, (int)$m['id']]); sito_vai('/area-riservata/profilo/?m=profilo', 303); }
        } elseif ($azione === 'email') {
            $nuova = mb_strtolower(trim(membri_post('email', 190)));
            if (!membri_email_valida($nuova)) $errore = 'Controlla il nuovo indirizzo email.';
            elseif ($nuova === $m['email']) $errore = 'È già il tuo indirizzo.';
            elseif (!$pwOk()) $errore = 'La password attuale non è giusta.';
            elseif (!sicurezza_limite('membri-email-cambio:' . (int)$m['id'], 5, 3600)) $errore = 'Troppe richieste. Riprova fra un\'ora.';
            else {
                // Il link parte solo se l'indirizzo è libero, ma la risposta è la stessa: dal profilo non si scopre chi è iscritto.
                if (!membri_per_email($nuova)) {
                    $t = membri_token_crea('email', (int)$m['id'], 24 * 60, $nuova);
                    membri_manda($nuova, 'Conferma il tuo nuovo indirizzo', 'Conferma il nuovo indirizzo', '<p>Per usare questo indirizzo nell\'area riservata di ' . h(membri_nome_sito()) . ' premi il pulsante. Il link vale un giorno.</p><p>Se non sei stato tu, ignora questa email.</p>', 'Confermo il nuovo indirizzo', membri_url('/conferma-email/?t=' . $t));
                }
                sito_vai('/area-riservata/profilo/?m=email-inviata', 303);
            }
        } elseif ($azione === 'password') {
            $pw = membri_post('password', 300);
            if (!$pwOk()) $errore = 'La password attuale non è giusta.';
            elseif (($e = membri_password_errore($pw, membri_post('password2', 300), (string)$m['email'])) !== '') $errore = $e;
            else {
                membri_password_imposta((int)$m['id'], $pw);
                membri_sessioni_chiudi((int)$m['id'], true);
                membri_tentativo_riuscito((string)$m['email']);
                sito_vai('/area-riservata/profilo/?m=pw-profilo', 303);
            }
        } elseif ($azione === 'elimina') {
            if (empty($_POST['sicuro'])) $errore = 'Per cancellare l\'account spunta la casella di conferma.';
            elseif (!$pwOk()) $errore = 'La password attuale non è giusta.';
            else {
                membri_esci();
                membri_elimina((int)$m['id']);
                membri_html('Account cancellato', membri_riquadro('Account cancellato', '<p>' . h(MEMBRI_MESSAGGI['eliminato']) . '</p><p><a class="btn btn-filo" href="/">Torna al sito</a></p>'));
            }
        } else $errore = 'Richiesta non valida.';
    }
    $c = membri_csrf_campo();
    $html = '<p><a href="/area-riservata/">&larr; Torna all\'area riservata</a></p>'
        . '<section class="modulo membri-modulo"><h2>Il tuo nome</h2><form method="post" action="/area-riservata/profilo/" id="membri-nome">' . $c . '<input type="hidden" name="azione" value="nome"><div class="modulo-campi membri-campi">'
        . membri_campo('nome', 'Nome', 'text', (string)$m['nome'], 'name', 'required maxlength="120"') . '</div><p class="membri-azioni"><button class="btn btn-viola" type="submit">Salva il nome</button></p></form></section>'
        . '<section class="modulo membri-modulo"><h2>La tua email</h2><p>Adesso entri con <b>' . h((string)$m['email']) . '</b>. Per cambiarla scrivi il nuovo indirizzo: ti mandiamo un link per confermarlo.</p><form method="post" action="/area-riservata/profilo/" id="membri-email">' . $c . '<input type="hidden" name="azione" value="email"><div class="modulo-campi membri-campi">'
        . membri_campo('email', 'Nuovo indirizzo email', 'email', '', 'email', 'required maxlength="190"') . membri_campo('attuale', 'La tua password attuale', 'password', '', 'current-password', 'required maxlength="200"')
        . '</div><p class="membri-azioni"><button class="btn btn-viola" type="submit">Cambia l\'email</button></p></form></section>'
        . '<section class="modulo membri-modulo"><h2>La password</h2><form method="post" action="/area-riservata/profilo/" id="membri-password">' . $c . '<input type="hidden" name="azione" value="password"><div class="modulo-campi membri-campi">'
        . membri_campo('attuale', 'Password attuale', 'password', '', 'current-password', 'required maxlength="200"')
        . membri_campo('password', 'Password nuova (almeno 12 caratteri)', 'password', '', 'new-password', 'required minlength="12" maxlength="200"')
        . membri_campo('password2', 'Ripeti la password nuova', 'password', '', 'new-password', 'required minlength="12" maxlength="200"')
        . '</div><p class="membri-azioni"><button class="btn btn-viola" type="submit">Cambia la password</button></p></form></section>'
        . '<section class="modulo membri-modulo membri-pericolo"><h2>Cancella il mio account</h2><p>Cancelliamo il tuo account e tutti i tuoi dati' . (cms_modulo('corsi') ? ', anche l\'avanzamento nei corsi e i certificati' : '') . '. Non si torna indietro.</p>'
        . '<form method="post" action="/area-riservata/profilo/" id="membri-elimina">' . $c . '<input type="hidden" name="azione" value="elimina"><div class="modulo-campi membri-campi">'
        . membri_campo('attuale', 'La tua password', 'password', '', 'current-password', 'required maxlength="200"') . '</div>'
        . '<label class="spunta"><input type="checkbox" name="sicuro" value="1" required> <span>Sì, voglio cancellare il mio account</span></label>'
        . '<p class="membri-azioni"><button class="btn btn-filo" type="submit">Cancella il mio account</button></p></form></section>';
    membri_html('Il mio profilo', membri_riquadro('Il mio profilo', $html, membri_messaggio($q), $errore));
}

function membri_pagina_conferma_email(array $q, bool $post): never {
    $t = (string)($post ? membri_post('t', 80) : (is_string($q['t'] ?? null) ? $q['t'] : ''));
    $ok = membri_token_leggi('email', $t);
    $m = $ok ? membri_leggi($ok['membro_id']) : null;
    if (!$m || !membri_email_valida($ok['dato'])) membri_html('Link non valido', membri_riquadro('Questo link non vale più', '<p>Il link è scaduto oppure è già stato usato. Dal tuo profilo puoi chiederne uno nuovo.</p>'), 410);
    if (!$post) membri_html('Conferma l\'email', membri_riquadro('Conferma il nuovo indirizzo', '<p>Da adesso entrerai con <b>' . h($ok['dato']) . '</b> invece di ' . h((string)$m['email']) . '.</p><form method="post" action="/conferma-email/">' . membri_csrf_campo() . '<input type="hidden" name="t" value="' . h($t) . '"><button class="btn btn-viola" type="submit">Confermo</button></form>'));
    membri_token_leggi('email', $t, true);
    if (membri_per_email($ok['dato'])) membri_html('Indirizzo già usato', membri_riquadro('Indirizzo già usato', '<p>Nel frattempo questo indirizzo è stato usato per un altro account. La tua email resta quella di prima.</p>'), 409);
    db()->prepare('UPDATE ' . tbl('membri') . ' SET email = ? WHERE id = ?')->execute([$ok['dato'], (int)$m['id']]);
    // Il vecchio indirizzo lo sa: se non è stato il proprietario, se ne accorge.
    membri_manda((string)$m['email'], 'Il tuo indirizzo email è cambiato', 'Indirizzo email cambiato', '<p>L\'account dell\'area riservata di ' . h(membri_nome_sito()) . ' adesso usa un altro indirizzo email. Se non sei stato tu, scrivi subito al sito.</p>');
    sito_vai(membri_corrente() ? '/area-riservata/profilo/?m=email-cambiata' : '/accedi/?m=password', 303);
}

// ------------------------------------------------------------ Contenuti "solo per i membri"

/** Evento sito_contenuto: chi non è entrato vede l'invito ad accedere al posto del contenuto. */
function membri_controlla_contenuto(array $c, array $meta): void {
    if (!membri_contenuto_riservato($c)) return;
    header('Cache-Control: private, no-store');
    if (!membri_contenuto_chiuso($c)) return;
    $torna = (string)$c['percorso'];
    $iscrizione = membri_cfg()['modo'] !== 'invito';
    $corpo = '<p>' . h(membri_testo_avviso()) . '</p><p class="membri-azioni"><a class="btn btn-viola" href="/accedi/?torna=' . h(rawurlencode($torna)) . '">Accedi</a>'
        . ($iscrizione ? ' <a class="btn btn-filo" href="/iscriviti/">Iscriviti</a>' : '') . '</p>';
    $meta['robots'] = 'noindex';
    $meta['og'] = [];
    $GLOBALS['sito_senza_popup'] = true;
    header('X-Robots-Tag: noindex');
    sito_html(200, $meta, membri_riquadro((string)$c['titolo'], '<div class="membri-chiuso" data-membri-chiuso>' . $corpo . '</div>', '', '', 'Solo per i membri'), membri_testa());
}
