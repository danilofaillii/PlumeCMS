<?php
/**
 * 2.16.0, fase 9 del piano Brevo (docs-sviluppo/brevo-confronto.md, capitoli 1.7, 1.8, 3.8): i form come Brevo.
 *
 *   - tipi: Iscrizione, Pop-up, Aggiornamento del profilo, Disiscrizione, Altri form (i form di prima: contatti, lavora
 *     con noi); il costruttore a cinque passi (admin/form.php): Impostazione, Aspetto e campi, Messaggi, Conferma, Condividi;
 *   - campi legati agli attributi dei contatti (chiave "attributo" nei campi), la casella da spuntare solo con un attributo
 *     Sì/No, Iscrizione a più liste, Gruppi di consenso, Consenso al tracciamento, GDPR con la dichiarazione;
 *   - conferma doppia, semplice (entra subito e riceve un'email) o nessuna, modello di doppio opt-in, pagina dopo il clic,
 *     liste in più oltre alla principale, "aggiorna i contatti esistenti";
 *   - condividi: pagina ospitata /modulo/<slug>/ (l'unica pagina del sito che si può mettere in un iframe di un altro sito),
 *     codice iframe, HTML (con lo script del captcha), HTML semplice (senza JavaScript: la verifica è una domanda), QR.
 *
 * Colonne nuove di <prefisso>moduli (form_schema -> fb_schema): tipo, conferma, liste, doi_modello_id, doi_url,
 * aggiorna_esistenti, gdpr, gdpr_testo, messaggi, dopo_invio, dopo_url, popup_id. Con i valori di partenza i form di prima
 * escono e funzionano al byte come prima (la migrazione dà solo tipo e conferma dalle colonne di prima).
 */

require_once __DIR__ . '/newsletter-consenso.php';
require_once __DIR__ . '/sicurezza-qr.php';

const FORM_TIPI = ['iscrizione' => 'Iscrizione', 'popup' => 'Pop-up', 'profilo' => 'Aggiornamento del profilo', 'disiscrizione' => 'Disiscrizione', 'generico' => 'Altri form'];
const FORM_TIPI_SPIEGA = [
    'iscrizione' => 'Chi lo compila entra in una lista della newsletter, con la conferma che scegli tu.',
    'popup' => 'Come Iscrizione, ma compare in una finestra sopra le pagine del sito.',
    'profilo' => 'Il contatto cambia i suoi dati, le liste e i consensi: si apre dal link delle email.',
    'disiscrizione' => 'Chi scrive la sua email riceve il link per uscire dalla newsletter.',
    'generico' => 'Contatti, richieste, candidature: le compilazioni arrivano in Invii dei form.',
];
const FORM_PASSI = [1 => 'Impostazione', 2 => 'Aspetto e campi', 3 => 'Messaggi', 4 => 'Conferma', 5 => 'Condividi'];
const FORM_CONFERME = [
    'doppia' => ['Doppia conferma (consigliata)', 'Riceve un\'email con il pulsante per confermare: entra nella lista solo dopo il clic.'],
    'semplice' => ['Conferma semplice', 'Entra subito nella lista e riceve un\'email che gli dice che è iscritto.'],
    'nessuna' => ['Nessuna conferma', 'Entra subito nella lista, senza email.'],
];
const FORM_DOPO = ['pagina' => 'La pagina di ringraziamento', 'messaggio' => 'Un messaggio al posto del form', 'url' => 'Un altro indirizzo'];
const FORM_GDPR_PARTENZA = "Usiamo i tuoi dati solo per mandarti le email che hai chiesto. Puoi uscire quando vuoi con il link in fondo a ogni email. Leggi l'informativa privacy per sapere come li trattiamo.";
/** I nomi dei campi che il server legge da sé: nessun campo li prende, e un POST che li porta non cambia niente. */
const FORM_CAMPI_SERVIZIO = ['ospitata', 'semplice', 'domanda', 'domanda_risposta', 'pi', 'pt'];

function fb_messaggi_partenza(): array {
    return [
        'riuscita' => 'Grazie: abbiamo ricevuto i tuoi dati.',
        'errore' => '',
        'obbligatorio' => '',
        'email' => '',
        'benvenuto_oggetto' => 'Sei iscritto: benvenuto',
        'benvenuto_testo' => "Ciao {nome},\nla tua iscrizione è fatta: da ora ricevi le nostre email.\nSe non sei stato tu, esci con il link in fondo a questa email.",
    ];
}

/** Le colonne nuove di moduli e la migrazione dei form di prima, una volta sola. La chiama form_schema(). */
function fb_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    $colonne = [
        'tipo' => "VARCHAR(14) NOT NULL DEFAULT 'generico'",
        'conferma' => "VARCHAR(8) NOT NULL DEFAULT 'doppia'",
        'liste' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'doi_modello_id' => 'INT(11) NOT NULL DEFAULT 0',
        'doi_url' => "VARCHAR(500) NOT NULL DEFAULT ''",
        'aggiorna_esistenti' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'gdpr' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'gdpr_testo' => 'TEXT NULL',
        'messaggi' => 'TEXT NULL',
        'dopo_invio' => "VARCHAR(10) NOT NULL DEFAULT 'pagina'",
        'dopo_url' => "VARCHAR(500) NOT NULL DEFAULT ''",
        'popup_id' => 'INT(11) NOT NULL DEFAULT 0',
    ];
    $impronta = md5(serialize($colonne) . DB_NAME);
    if (get_setting('form_brevo_impronta', '') === $impronta) return;
    $t = tbl('moduli');
    $col = db()->query('SHOW COLUMNS FROM ' . $t)->fetchAll(PDO::FETCH_COLUMN);
    foreach ($colonne as $c => $def) if (!in_array($c, $col, true)) db()->exec("ALTER TABLE $t ADD COLUMN `$c` $def");
    if (get_setting('form_brevo_migrati', '') !== '1') {
        // I form di prima: con una lista sono form di iscrizione; la spunta "Con email di conferma" diventa la conferma doppia,
        // senza la spunta "nessuna" (entravano subito, senza email). Il resto ha i valori di partenza, che danno lo stesso form.
        db()->exec("UPDATE $t SET tipo = IF(lista_id > 0, 'iscrizione', 'generico'), conferma = IF(lista_conferma = 1, 'doppia', 'nessuna')");
        save_setting('form_brevo_migrati', '1');
    }
    nl_consenso_schema();
    save_setting('form_brevo_impronta', $impronta);
}

/** Il form è di uno dei tipi che iscrivono alla newsletter. */
function fb_iscrive(array $m): bool {
    return in_array($m['tipo'] ?? 'generico', ['iscrizione', 'popup'], true) || ((($m['tipo'] ?? 'generico') === 'generico') && (int)($m['lista_id'] ?? 0) > 0);
}

// ------------------------------------------------------------
// Campi e attributi
// ------------------------------------------------------------

/** I tipi di campo che vanno con un tipo di attributo (Brevo: la casella da spuntare solo con un attributo Sì/No). */
const FORM_ATTR_CAMPI = [
    'text' => ['testo', 'testolungo', 'email', 'telefono'],
    'float' => ['numero'],
    'date' => ['data'],
    'boolean' => ['spunta'],
    'category' => ['scelta'],
    'multiple-choice' => ['multiscelta'],
];

/** Gli attributi che un campo può riempire: quelli normali e, fra quelli di sistema, EMAIL, NOME, COGNOME. */
function fb_attributi_campi(): array {
    $out = [];
    foreach (newsletter_attributi() as $n => $a) {
        if (in_array($a['categoria'], ['calculated', 'global'], true)) continue;
        if ((int)$a['sistema'] && !in_array($n, ['EMAIL', 'NOME', 'COGNOME'], true)) continue;
        $out[$n] = $a;
    }
    return $out;
}

/** Il campo può prendere questo attributo? EMAIL solo da un campo Email, e un campo Email solo EMAIL. */
function fb_attributo_compatibile(array $a, string $tipo): bool {
    if ($a['nome'] === 'EMAIL') return $tipo === 'email';
    if ($tipo === 'email') return false;
    return in_array($tipo, FORM_ATTR_CAMPI[$a['tipo']] ?? [], true);
}

/** Le opzioni di un attributo di categoria o a scelta multipla, come testo (le etichette). */
function fb_opzioni_attributo(array $a): array {
    return array_values(array_map(fn($x) => newsletter_4byte_fuori((string)$x), $a['opzioni']));
}

// ------------------------------------------------------------
// Messaggi
// ------------------------------------------------------------

/** I messaggi del form (passo 3): quelli scritti, poi quelli di partenza. */
function fb_messaggi(array $m): array {
    $s = json_decode((string)($m['messaggi'] ?? ''), true);
    $s = is_array($s) ? $s : [];
    $out = [];
    foreach (fb_messaggi_partenza() as $k => $v) $out[$k] = trim((string)($s[$k] ?? '')) !== '' ? newsletter_4byte_fuori((string)$s[$k]) : $v;
    return $out;
}

/** I messaggi da salvare: solo quelli scritti e diversi dalla partenza, puliti, con le emoji come entità. */
function fb_messaggi_da_salvare(mixed $in): ?string {
    if (!is_array($in)) return null;
    $out = [];
    foreach (fb_messaggi_partenza() as $k => $v) {
        $x = trim(str_replace("\r", '', strip_tags((string)($in[$k] ?? ''))));
        if ($k === 'benvenuto_oggetto') $x = str_replace("\n", ' ', $x);
        $x = mb_substr($x, 0, $k === 'benvenuto_testo' ? 2000 : 300);
        if ($x !== '' && $x !== $v) $out[$k] = newsletter_4byte_dentro($x);
    }
    return $out ? json_encode($out, JSON_UNESCAPED_UNICODE) : null;
}

/** Il messaggio d'errore di un form, con i messaggi scritti nel passo 3 (se ci sono). [messaggio, campo da segnare] */
function fb_errore(array $m, string $codice): array {
    [$msg, $campo] = form_errore($codice);
    $x = fb_messaggi($m);
    if (str_starts_with($codice, 'campo:') && $x['obbligatorio'] !== '') $msg = str_replace('{campo}', $campo, $x['obbligatorio']);
    elseif ($codice === 'email' && $x['email'] !== '') $msg = $x['email'];
    elseif (in_array($codice, ['posta', 'robot'], true) && $x['errore'] !== '') $msg = $x['errore'];
    elseif ($codice === 'link') $msg = 'Questa pagina si apre dal link «Aggiorna le tue preferenze» in fondo alle nostre email: usa quello, così sappiamo che sei tu.';
    elseif ($codice === 'domanda') $msg = 'La risposta alla domanda di controllo non è giusta: riprova.';
    elseif ($codice === 'liste') $msg = 'Scegli almeno una delle liste.';
    return [$msg, $campo];
}

// ------------------------------------------------------------
// La domanda di controllo (senza JavaScript)
// ------------------------------------------------------------

const FORM_NUMERI = ['zero', 'uno', 'due', 'tre', 'quattro', 'cinque', 'sei', 'sette', 'otto', 'nove', 'dieci'];

/** Una domanda di controllo firmata, per chi non ha JavaScript: [testo, token]. Vale 20 minuti e una volta sola. */
function fb_domanda(): array {
    $a = random_int(1, 9); $b = random_int(1, 9);
    $sale = bin2hex(random_bytes(12));
    $scade = time() + MODULI_CAPTCHA_DURATA;
    $firma = hash_hmac('sha256', "domanda|$sale|$scade|" . ($a + $b), moduli_segreto());
    return ['Quanto fa ' . FORM_NUMERI[$a] . ' più ' . FORM_NUMERI[$b] . '? Scrivi il numero in cifre.', base64_encode("$sale|$scade|$firma")];
}

/** La risposta alla domanda è giusta, firmata, non scaduta e mai usata prima. */
function fb_domanda_valida(array $p): bool {
    $tok = is_string($p['domanda'] ?? null) ? (string)base64_decode((string)$p['domanda'], true) : '';
    $r = is_string($p['domanda_risposta'] ?? null) ? trim((string)$p['domanda_risposta']) : '';
    if (!preg_match('/^([a-f0-9]{24})\|(\d{10})\|([a-f0-9]{64})$/', $tok, $x) || !preg_match('/^\d{1,2}$/', $r)) return false;
    if ((int)$x[2] < time()) return false;
    if (!hash_equals(hash_hmac('sha256', "domanda|{$x[1]}|{$x[2]}|" . (int)$r, moduli_segreto()), $x[3])) return false;
    moduli_schema();
    try {
        db()->prepare('INSERT INTO ' . tbl('captcha_usati') . ' (sale, scade) VALUES (?, ?)')->execute([$x[1], date('Y-m-d H:i:s', (int)$x[2])]);
    } catch (PDOException $e) {
        return false; // già usata
    }
    return true;
}

/** La domanda di controllo in HTML (dentro il form). */
function fb_domanda_html(int $id, bool $errata = false): string {
    [$testo, $tok] = fb_domanda();
    return '<div class="form-domanda' . ($errata ? ' errato' : '') . '"><label for="fd-' . $id . '"><span>Domanda di controllo: ' . h($testo) . '</span>'
        . '<input type="text" id="fd-' . $id . '" name="domanda_risposta" inputmode="numeric" pattern="[0-9]{1,2}" maxlength="2" autocomplete="off" required' . ($errata ? ' aria-invalid="true"' : '') . '></label>'
        . '<input type="hidden" name="domanda" value="' . h($tok) . '"></div>';
}

// ------------------------------------------------------------
// Condividi: pagina ospitata, codici, QR
// ------------------------------------------------------------

/** Lo schema e l'host del sito (https://esempio.it), senza la cartella. */
function fb_origine(): string {
    return (string)preg_replace('~^(https?://[^/]+).*$~', '$1', MODULI_URL);
}

function fb_url_ospitata(array $m): string {
    return MODULI_URL . '/modulo/' . rawurlencode((string)$m['slug']) . '/';
}

/** Il form attivo con questo nome breve, o null. */
function fb_form_da_slug(string $slug): ?array {
    if (!preg_match('/^[a-z0-9-]{1,100}$/', $slug)) return null;
    form_schema();
    $st = db()->prepare('SELECT id FROM ' . tbl('moduli') . ' WHERE slug = ? AND attivo = 1');
    $st->execute([$slug]);
    $id = (int)$st->fetchColumn();
    return $id ? form_leggi($id) : null;
}

/** Il codice iframe da incollare in un altro sito. */
function fb_codice_iframe(array $m): string {
    return '<iframe src="' . h(fb_url_ospitata($m)) . '" width="100%" height="' . (420 + 80 * count($m['campi'])) . '" style="border:0;max-width:640px" title="' . h($m['titolo'] !== '' ? $m['titolo'] : $m['nome']) . '" loading="lazy"></iframe>';
}

/** Il codice HTML: il form con gli indirizzi interi, lo stile e lo script del captcha dal sito. $semplice: senza JavaScript. */
function fb_codice_html(array $m, bool $semplice = false): string {
    $o = fb_origine();
    $css = $o . cms_base() . '/sito/assets/modulo-esterno.css';
    $h = form_html($m, '/modulo/' . $m['slug'] . '/', '', '', ['esterno' => true, 'semplice' => $semplice]);
    return '<link rel="stylesheet" href="' . h($css) . '">' . "\n" . $h;
}

/** Il codice QR della pagina ospitata, in SVG. */
function fb_qr_svg(array $m, int $scala = 6): string {
    return sicurezza_qr_svg(fb_url_ospitata($m), $scala, 4);
}

/**
 * Le intestazioni della pagina ospitata: le stesse del sito, ma questa pagina si può mettere in un iframe di qualunque sito
 * (frame-ancestors *). È l'unica: il resto del sito resta protetto dal clickjacking (frame-ancestors 'self').
 */
function fb_intestazioni_ospitata(): void {
    if (PHP_SAPI === 'cli' || headers_sent()) return;
    sicurezza_intestazioni('sito');
    header_remove('X-Frame-Options');
    header('Cache-Control: no-store, private');
    try { $cfg = sicurezza_cfg(); $modo = (string)$cfg['csp_sito']; } catch (Throwable $e) { $modo = 'spenta'; }
    header('Content-Security-Policy: ' . ($modo === 'applica' ? sicurezza_csp('sito') . '; ' : '') . 'frame-ancestors *', true);
}

/** Il documento della pagina ospitata: solo il form, con lo stile e il tema del sito, senza testata né piede. */
function fb_documento(string $titolo, string $corpo, bool $indicizza = false): string {
    $a = cms_base() . '/sito/assets/';
    $v = fn($f) => $a . $f . (is_file(dirname(__DIR__) . '/sito/assets/' . $f) ? '?v=' . filemtime(dirname(__DIR__) . '/sito/assets/' . $f) : '');
    $tema = function_exists('temi_css_link') ? temi_css_link() : '';
    $design = function_exists('design_head') ? design_head() : '';
    $html = '<!DOCTYPE html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="cms-base" content="' . h(cms_base()) . '"><meta name="robots" content="' . ($indicizza ? 'index, follow' : 'noindex, nofollow') . '">'
        . '<title>' . h($titolo) . '</title><link rel="stylesheet" href="' . h($v('sito.css')) . '"><link rel="stylesheet" href="' . h($v('tema.css')) . '">'
        . $tema . $design . '<link rel="icon" href="' . h($a) . 'favicon.svg" type="image/svg+xml"></head>'
        . '<body class="fo-ospitata"><main id="contenuto" class="fo-pagina">' . $corpo . '</main></body></html>';
    return function_exists('cms_con_cartella') ? cms_con_cartella($html) : $html;
}

/**
 * La pagina ospitata /modulo/<slug>/. $q: la query (modulo=errore&f=, fatto=1&o=&k=, i=&t= per il profilo).
 * Ritorna l'HTML del documento.
 */
function fb_pagina_ospitata(array $m, array $q): string {
    $titolo = $m['titolo'] !== '' ? $m['titolo'] : $m['nome'];
    $s = fn($k) => is_string($q[$k] ?? null) ? (string)$q[$k] : '';
    if ($s('fatto') === '1') {
        $x = fb_messaggi($m);
        if ($m['dopo_invio'] === 'messaggio') $corpo = '<section class="modulo fo-riuscito" role="status"><h1>' . h($titolo) . '</h1><p>' . nl2br(h($x['riuscita'])) . '</p>' . fb_omaggio_html($m, $s('o'), $s('k')) . '</section>';
        else $corpo = '<section class="modulo fo-riuscito" role="status"><h1>' . h($m['grazie_titolo']) . '</h1><p>' . ($m['grazie_testo'] !== '' ? nl2br(h($m['grazie_testo'])) : 'Ti rispondo appena posso.') . '</p>' . fb_omaggio_html($m, $s('o'), $s('k'))
            . (($l = sicurezza_url_pulito((string)$m['grazie_link'])) !== '' ? '<p><a class="btn btn-viola" href="' . h($l) . '" target="_top">Continua</a></p>' : '') . '</section>';
        return fb_documento($titolo, $corpo);
    }
    [$errore, $campo] = (int)$s('f') === (int)$m['id'] && $s('modulo') !== '' ? fb_errore($m, $s('modulo')) : ['', ''];
    $opz = ['ospitata' => true];
    if ($m['tipo'] === 'profilo') {
        $c = fb_contatto_da_link($s('i'), $s('t'));
        if (!$c) return fb_documento($titolo, '<section class="modulo"><h1>' . h($titolo) . '</h1><p role="alert">' . h(fb_errore($m, 'link')[0]) . '</p></section>');
        $opz['contatto'] = $c;
    }
    return fb_documento($titolo, form_html($m, '/modulo/' . $m['slug'] . '/', $errore, $campo, $opz));
}

/** La pagina "ultimo passo" dopo un form in HTML semplice: lo stesso form, già compilato, con la domanda di controllo. */
function fb_pagina_verifica(array $m, array $post, bool $sbagliata = false): string {
    $valori = [];
    foreach ($post as $k => $v) if (is_string($k) && str_starts_with($k, 'c_')) $valori[$k] = $v;
    $opz = ['ospitata' => true, 'verifica' => true, 'valori' => $valori, 'privacy' => !empty($post['privacy'])];
    if ($m['tipo'] === 'profilo') {
        $c = fb_contatto_da_link(is_string($post['pi'] ?? null) ? $post['pi'] : '', is_string($post['pt'] ?? null) ? $post['pt'] : '');
        if ($c) $opz['contatto'] = $c;
    }
    $msg = $sbagliata ? fb_errore($m, 'domanda')[0] : 'Ultimo passo: rispondi alla domanda di controllo qui sotto e premi di nuovo il pulsante. Così sappiamo che sei una persona.';
    return fb_documento($m['titolo'] !== '' ? $m['titolo'] : $m['nome'], form_html($m, '/modulo/' . $m['slug'] . '/', $msg, $sbagliata ? 'domanda' : '', $opz));
}

/** Il pezzo del file omaggio nel messaggio dopo l'invio (come nella pagina di ringraziamento). */
function fb_omaggio_html(array $m, string $o, string $k): string {
    if (($m['omaggio'] ?? '') === '') return '';
    require_once __DIR__ . '/scaricabili.php';
    $x = scaricabili_elenco()[$m['omaggio']] ?? null;
    $titolo = $x ? h($x['titolo']) : 'il file';
    if ($o === 'conferma') return '<p class="form-omaggio"><b>Ultimo passo:</b> ti abbiamo mandato un&#039;email. Apri il link per confermare il tuo indirizzo e ricevi subito «' . $titolo . '». Se non la vedi, guarda nello spam.</p>';
    if ($o === 'email') return '<p class="form-omaggio">Sei già nella lista: ti abbiamo mandato per email il link per scaricare «' . $titolo . '».</p>';
    if ($o === 'link' && $x && scaricabili_verifica_chiave($m['omaggio'], $k)) {
        return '<p class="form-omaggio">Ecco «' . $titolo . '». Ti abbiamo mandato il link anche per email.</p><p><a class="btn btn-viola" target="_top" href="' . h(rtrim(cms_url_sito(), '/') . '/scarica/' . $m['omaggio'] . '/?k=' . $k) . '">Scarica</a></p>';
    }
    return '';
}

// ------------------------------------------------------------
// Il contatto del link (Aggiornamento del profilo)
// ------------------------------------------------------------

/** Il contatto del link firmato delle preferenze (i, t), oppure null. */
function fb_contatto_da_link(string $i, string $t): ?array {
    if (!ctype_digit($i) || $t === '' || !function_exists('newsletter_token_preferenze') || !hash_equals(newsletter_token_preferenze((int)$i), $t)) return null;
    return newsletter_contatto((int)$i);
}

/** I valori di partenza dei campi di un form di profilo, dal contatto: [c_nome => valore]. */
function fb_valori_contatto(array $m, array $c): array {
    $id = (int)$c['id'];
    $val = newsletter_valori_di([$id])[$id] ?? [];
    $attr = newsletter_attributi();
    $out = [];
    foreach ($m['campi'] as $f) {
        $n = 'c_' . $f['nome'];
        if ($f['tipo'] === 'liste') {
            $st = db()->prepare('SELECT l.lista_id FROM ' . tbl('newsletter_iscritti_liste') . ' l WHERE l.iscritto_id = ?' . newsletter_sql_uscite_lista());
            $st->execute([$id]);
            $out[$n] = array_map('strval', array_intersect(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)), $f['liste']));
            continue;
        }
        if ($f['tipo'] === 'gruppi') { $out[$n] = array_map('strval', array_keys(array_filter(nl_contatto_gruppi($id)))); continue; }
        if ($f['tipo'] === 'tracciamento') { $out[$n] = nl_tracciamento_ok($id) ? '1' : ''; continue; }
        $a = $f['attributo'] !== '' ? ($attr[$f['attributo']] ?? null) : null;
        if (!$a) continue;
        $v = $val[$f['attributo']] ?? '';
        if ($a['nome'] === 'EMAIL') $v = (string)$c['email'];
        if ($a['tipo'] === 'boolean') $v = (int)$v === 1 ? '1' : '';
        elseif ($a['tipo'] === 'category') $v = newsletter_4byte_fuori((string)($a['opzioni'][(int)$v] ?? ''));
        elseif ($a['tipo'] === 'multiple-choice') $v = array_map(fn($o) => newsletter_4byte_fuori((string)($a['opzioni'][(int)$o] ?? '')), (array)$v);
        elseif ($a['tipo'] === 'float') $v = $v === '' ? '' : newsletter_numero_testo($v);
        else $v = newsletter_4byte_fuori((string)$v);
        $out[$n] = $v;
    }
    return $out;
}

// ------------------------------------------------------------
// Dopo l'invio
// ------------------------------------------------------------

/**
 * Quello che un form salva nel contatto oltre a nome, cognome ed email (che fa form_iscrivi): gli attributi dei campi
 * (per un contatto che c'era già solo con "Aggiorna i contatti esistenti"), i gruppi di consenso scelti, il consenso al
 * tracciamento e la riga del registro del consenso con il testo che la persona ha visto.
 * $x: ['attributi' => [NOME => valore], 'gruppi' => [id], 'gruppi_offerti' => [id], 'tracciamento' => null|bool]
 */
function fb_dopo_iscrizione(array $m, int $id, bool $cera, array $x): void {
    if (!$id) return;
    $attr = $x['attributi'] ?? [];
    unset($attr['EMAIL'], $attr['NOME'], $attr['COGNOME']);
    if ($attr && (!$cera || !empty($m['aggiorna_esistenti']))) newsletter_valori_salva($id, $attr);
    if (!empty($m['aggiorna_esistenti']) && $cera) {
        // Con "Aggiorna i contatti esistenti" anche nome e cognome scritti vincono su quelli che c'erano.
        $nc = array_intersect_key($x['attributi'] ?? [], ['NOME' => 1, 'COGNOME' => 1]);
        $nc = array_filter($nc, fn($v) => trim((string)$v) !== '');
        if ($nc) newsletter_valori_salva($id, $nc);
    }
    foreach ((array)($x['gruppi_offerti'] ?? []) as $g) nl_contatto_gruppo_imposta($id, (int)$g, in_array((int)$g, (array)($x['gruppi'] ?? []), true), 'form ' . newsletter_4byte_fuori((string)$m['nome']));
    if (($x['tracciamento'] ?? null) !== null) nl_tracciamento_imposta($id, (bool)$x['tracciamento'], 'form ' . newsletter_4byte_fuori((string)$m['nome']));
    $testo = trim((string)($m['gdpr'] ? ($m['gdpr_testo'] ?? '') : ''));
    $casella = $m['consenso'] !== '' ? (string)$m['consenso'] : "Ho letto l'informativa privacy e accetto il trattamento dei miei dati per questa richiesta.";
    nl_consenso_registra($id, 'opt_in', ($testo !== '' ? $testo . "\n" : '') . 'Casella spuntata: ' . $casella, (int)$m['id']);
    if ($m['gdpr']) db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . ' SET opt_in = 1 WHERE id = ?')->execute([$id]);
}

/** Conferma semplice: l'email che dice al contatto che è iscritto (oggetto e testo del passo 3, con {nome}). */
function fb_benvenuto(array $m, string $email, string $nome): void {
    $x = fb_messaggi($m);
    $id = newsletter_id_da_email($email);
    $testo = implode('', array_map(fn($r) => '<p>' . str_replace('{nome}', h($nome !== '' ? $nome : 'ciao'), h($r)) . '</p>', array_filter(array_map('trim', explode("\n", $x['benvenuto_testo'])), fn($r) => $r !== '')));
    $oggetto = trim(str_replace(["\r", "\n", '{nome}'], [' ', ' ', $nome], $x['benvenuto_oggetto']));
    $esci = $id ? newsletter_link_uscita($id) : MODULI_URL . NEWSLETTER_ESCI;
    $html = moduli_email_html($oggetto, $testo . '<p style="font-size:13px;color:#666"><a href="' . h($esci) . '" style="color:#666">Esci dalla newsletter</a></p>');
    if (!posta_invia($email, $oggetto, $html, moduli_intestazioni(), 'benvenuto')) error_log('form: email della conferma semplice non partita: ' . posta_errore());
}

/** Aggiornamento del profilo: salva attributi, liste, gruppi, tracciamento del contatto del link. */
function fb_salva_profilo(array $m, array $c, array $x): void {
    $id = (int)$c['id'];
    $attr = $x['attributi'] ?? [];
    unset($attr['EMAIL']); // l'email non si cambia da qui: un'email nuova è un contatto nuovo, con la sua conferma
    if ($attr) newsletter_valori_salva($id, $attr);
    foreach ((array)($x['liste_offerte'] ?? []) as $l) {
        $l = (int)$l;
        $vuole = in_array($l, (array)($x['liste'] ?? []), true);
        $dentro = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti_liste') . ' WHERE iscritto_id = ? AND lista_id = ?');
        $dentro->execute([$id, $l]);
        $dentro = (int)$dentro->fetchColumn() > 0;
        if ($vuole) {
            $u = db()->prepare('DELETE FROM ' . tbl('newsletter_liste_uscite') . ' WHERE contatto_id = ? AND lista_id = ?');
            $u->execute([$id, $l]);
            if ($u->rowCount()) newsletter_storia_scrivi($id, 'lista_rientrato', 'Dal form ' . newsletter_4byte_fuori((string)$m['nome']) . ': riceve di nuovo la lista ' . newsletter_nome_lista($l), $l);
            if (!$dentro) newsletter_in_lista($id, $l);
        } elseif ($dentro) nl_esci_da_lista($id, $l, 'form ' . newsletter_4byte_fuori((string)$m['nome']));
    }
    foreach ((array)($x['gruppi_offerti'] ?? []) as $g) nl_contatto_gruppo_imposta($id, (int)$g, in_array((int)$g, (array)($x['gruppi'] ?? []), true), 'form ' . newsletter_4byte_fuori((string)$m['nome']));
    if (($x['tracciamento'] ?? null) !== null) nl_tracciamento_imposta($id, (bool)$x['tracciamento'], 'form ' . newsletter_4byte_fuori((string)$m['nome']));
    newsletter_storia_scrivi($id, 'preferenze', 'Ha aggiornato il profilo con il form ' . newsletter_4byte_fuori((string)$m['nome']), (int)$m['id']);
}

/**
 * Disiscrizione: a chi è iscritto parte un'email con il link firmato per uscire (nessuno può far uscire l'indirizzo di un
 * altro scrivendolo nel form). Il messaggio sul sito è sempre lo stesso: non dice se l'indirizzo c'è.
 */
function fb_disiscrizione(array $m, string $email): void {
    $id = newsletter_id_da_email($email);
    if (!$id || newsletter_stato_email($email) !== 'attivo') return;
    $link = newsletter_link_uscita($id);
    $html = moduli_email_html('Vuoi uscire dalla newsletter?', '<p>Qualcuno, forse tu, ha chiesto di togliere questo indirizzo dalla nostra newsletter.</p><p>Per uscire premi il pulsante: basta un clic. Se non sei stato tu, ignora questa email.</p>', 'Esci dalla newsletter', $link);
    if (!posta_invia($email, 'Vuoi uscire dalla newsletter?', $html, moduli_intestazioni(), 'disiscrizione')) error_log('form: email di disiscrizione non partita: ' . posta_errore());
}

// ------------------------------------------------------------
// Il form pop-up
// ------------------------------------------------------------

/** Il pop-up di un form di tipo Pop-up (motore dei Popup, includes/popup.php): crea o aggiorna, e lo collega al form. */
function fb_popup_salva(array $m, array $d): int {
    require_once __DIR__ . '/popup.php';
    $pid = (int)($m['popup_id'] ?? 0);
    if ($pid && !popup_leggi($pid)) $pid = 0;
    $pid = popup_salva($pid, [
        'nome' => 'Form: ' . newsletter_4byte_fuori((string)$m['nome']), 'attivo' => !empty($d['attivo']), 'modulo' => 'form:' . (int)$m['id'],
        'titolo' => '', 'testo' => '', 'dove' => (string)($d['dove'] ?? 'tutte'), 'percorsi' => (string)($d['percorsi'] ?? ''),
        'innesco' => (string)($d['innesco'] ?? 'secondi'), 'valore' => (int)($d['valore'] ?? 8), 'frequenza_giorni' => (int)($d['frequenza_giorni'] ?? 7),
    ]);
    db()->prepare('UPDATE ' . tbl('moduli') . ' SET popup_id = ? WHERE id = ?')->execute([$pid, (int)$m['id']]);
    return $pid;
}
