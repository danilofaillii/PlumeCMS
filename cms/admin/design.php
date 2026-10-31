<?php
/**
 * Stile del sito: le impostazioni globali dell'aspetto (le "Site Settings" di Elementor).
 * Colori e caratteri globali, tipografia per elemento, pulsanti, immagini, campi dei moduli, layout,
 * sfondo, identità del sito, CSS personalizzato. A destra l'anteprima dal vivo: il sito vero in un riquadro,
 * con il CSS ricalcolato a ogni modifica. Niente cambia sul sito finché non si preme Salva.
 *
 *   POST azione=salva        salva tutte le schede
 *   POST azione=ripristina   cancella tutto: il sito torna com'è oggi
 *   POST azione=indietro     richiama la versione salvata prima dell'ultima
 *   POST azione=css          (dal JavaScript) il CSS per i valori del modulo, in JSON, senza salvare
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/design.php';
require_admin();
$adminTitle = 'Stile del sito';

/** I valori arrivati dal modulo: gli elenchi dei personalizzati devono arrivare anche vuoti (se no tornano i predefiniti). */
function dg_post_valori(array $post): array {
    $v = isset($post['v']) && is_array($post['v']) ? $post['v'] : [];
    $v['colori']['personalizzati'] = array_values(is_array($v['colori']['personalizzati'] ?? null) ? $v['colori']['personalizzati'] : []);
    $v['font']['personalizzati'] = array_values(is_array($v['font']['personalizzati'] ?? null) ? $v['font']['personalizzati'] : []);
    return $v;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? 'salva');
    if ($azione === 'css') {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        try {
            $v = design_pulisci(design_fondi(design_predefiniti(), dg_post_valori($_POST)));
            $colori = [];
            foreach ($v['colori']['sistema'] as $k => $x) $colori[$k] = $x;
            foreach ($v['colori']['personalizzati'] as $c) $colori[$c['id']] = $c['valore'];
            echo json_encode(['css' => design_css($v), 'link' => design_google_link($v), 'colori' => $colori, 'favicon' => $v['sito']['favicon']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['errore' => $e->getMessage()]);
        }
        exit;
    }
    $autore = (string)(current_user()['username'] ?? '');
    try {
        if ($azione === 'ripristina') {
            design_ripristina();
            flash('success', 'Stile riportato ai valori di partenza: il sito è tornato com\'era.');
        } elseif ($azione === 'indietro') {
            if (design_torna_indietro($autore)) flash('success', 'Richiamata la versione precedente dello stile.');
            else flash('error', 'Non c\'è una versione precedente da richiamare.');
        } else {
            design_salva(dg_post_valori($_POST), $autore);
            flash('success', 'Stile salvato: il sito lo usa da adesso.');
        }
    } catch (Throwable $e) {
        flash('error', 'Salvataggio non riuscito: ' . $e->getMessage());
    }
    $scheda = preg_replace('/[^a-z]/', '', (string)($_POST['scheda'] ?? ''));
    header('Location: design.php' . ($scheda !== '' ? '?scheda=' . $scheda : ''));
    exit;
}

$v = design_valori();
$globali = design_globali();
$schedaAperta = preg_replace('/[^a-z]/', '', (string)($_GET['scheda'] ?? '')) ?: 'colori';

// Le pagine dell'anteprima: home, l'ultimo articolo uscito, le prime pagine pubblicate, il blog.
$anteprime = [['/', 'Home']];
try {
    $a = db()->query('SELECT slug, title FROM ' . tbl('articles') . " WHERE status = 'published' AND publish_date <= CURDATE() ORDER BY publish_date DESC, id DESC LIMIT 1")->fetch();
    if ($a) $anteprime[] = ['/' . $a['slug'] . '/', 'Articolo: ' . mb_substr($a['title'], 0, 40)];
    foreach (db()->query('SELECT slug, title FROM ' . tbl('pages') . " WHERE status = 'published' AND slug <> " . db()->quote((string)get_setting('pagina_home', '')) . ' ORDER BY id LIMIT 3') as $p) {
        $anteprime[] = ['/' . $p['slug'] . '/', 'Pagina: ' . mb_substr($p['title'], 0, 40)];
    }
} catch (Throwable $e) {
}
$anteprime[] = [(string)cms_impostazione('pagina_blog', '/blog/'), 'Blog'];

// ------------------------------------------------------------
// Pezzi del modulo
// ------------------------------------------------------------

/** 'colori.sistema.primario' -> 'v[colori][sistema][primario]' */
function dg_nome(string $percorso): string {
    $p = explode('.', $percorso);
    return 'v[' . implode('][', $p) . ']';
}

function dg_aiuto(string $aiuto): string {
    return $aiuto !== '' ? '<div class="dg-aiuto">' . h($aiuto) . '</div>' : '';
}

/** Un colore: campo di testo, selettore nativo, tendina dei globali (facoltativa), pulsante per tornare al colore del sito. */
function dg_colore(string $percorso, string $val, string $etichetta, bool $conGlobali = true, string $aiuto = '', bool $obbligatorio = false): string {
    global $globali;
    $hex = preg_match('/^#[0-9a-fA-F]{6}$/', $val) ? $val : '#888888';
    $o = '';
    if ($conGlobali) {
        $o .= '<select class="dg-globali" title="Usa un colore globale"><option value="">Globale…</option>';
        foreach ($globali['colori'] as $c) $o .= '<option value="globale:' . h($c['id']) . '"' . ($val === 'globale:' . $c['id'] ? ' selected' : '') . '>' . h($c['nome']) . '</option>';
        $o .= '</select>';
    }
    return '<div class="dg-campo dg-colore"' . ($obbligatorio ? ' data-obbligatorio="1"' : '') . '><label>' . h($etichetta) . '</label><div class="dg-colore-riga">'
        . '<span class="dg-swatch"></span><input type="color" class="dg-picker" value="' . h($hex) . '" tabindex="-1">'
        . '<input type="text" class="dg-colore-testo" name="' . dg_nome($percorso) . '" value="' . h($val) . '" placeholder="' . ($obbligatorio ? '#rrggbb' : 'come il sito') . '" autocomplete="off" spellcheck="false">'
        . $o . ($obbligatorio ? '' : '<button type="button" class="dg-x" title="Torna al colore del sito">×</button>')
        . '</div>' . dg_aiuto($aiuto) . '</div>';
}

function dg_scelta(string $percorso, string $val, array $opzioni, string $etichetta, string $aiuto = ''): string {
    $o = '';
    foreach ($opzioni as $k => $n) $o .= '<option value="' . h((string)$k) . '"' . ((string)$k === $val ? ' selected' : '') . '>' . h($n) . '</option>';
    return '<div class="dg-campo"><label>' . h($etichetta) . '</label><select name="' . dg_nome($percorso) . '">' . $o . '</select>' . dg_aiuto($aiuto) . '</div>';
}

function dg_testo(string $percorso, string $val, string $etichetta, string $placeholder = 'come il sito', string $aiuto = '', string $tipo = 'text'): string {
    return '<div class="dg-campo"><label>' . h($etichetta) . '</label><input type="' . $tipo . '" name="' . dg_nome($percorso) . '" value="' . h($val) . '" placeholder="' . h($placeholder) . '" autocomplete="off">' . dg_aiuto($aiuto) . '</div>';
}

function dg_numero(string $percorso, $val, string $etichetta, int $min, int $max, string $unita = 'px', string $aiuto = ''): string {
    return '<div class="dg-campo dg-numero"><label>' . h($etichetta) . '</label><div class="dg-riga"><input type="number" name="' . dg_nome($percorso) . '" value="' . h((string)$val) . '" min="' . $min . '" max="' . $max . '" step="1"><span class="dg-unita">' . h($unita) . '</span></div>' . dg_aiuto($aiuto) . '</div>';
}

/** Un'immagine: indirizzo più il pulsante che apre la libreria del pannello. */
function dg_immagine(string $percorso, string $val, string $etichetta, string $aiuto = ''): string {
    return '<div class="dg-campo dg-immagine"><label>' . h($etichetta) . '</label><div class="dg-riga">'
        . '<img class="dg-miniatura" src="' . h($val) . '" alt=""' . ($val === '' ? ' hidden' : '') . '>'
        . '<input type="text" name="' . dg_nome($percorso) . '" value="' . h($val) . '" placeholder="Nessuna immagine" autocomplete="off">'
        . '<button type="button" class="btn btn-secondary btn-sm dg-scegli">Scegli dai Media</button><button type="button" class="dg-x" title="Togli l\'immagine" aria-label="Togli l\'immagine"' . ($val === '' ? ' hidden' : '') . '>×</button></div>' . dg_aiuto($aiuto) . '</div>';
}

/** Tre caselle, una per misura: desktop, tablet, telefono. */
function dg_misure3(string $percorso, array $val, string $etichetta, string $placeholder = 'come il sito'): string {
    $o = '';
    foreach (['d' => 'Desktop', 't' => 'Tablet', 'm' => 'Telefono'] as $m => $n) {
        $o .= '<label class="dg-misura"><span>' . $n . '</span><input type="text" name="' . dg_nome("$percorso.$m") . '" value="' . h((string)($val[$m] ?? '')) . '" placeholder="' . h($placeholder) . '" autocomplete="off" spellcheck="false"></label>';
    }
    return '<div class="dg-campo"><label>' . h($etichetta) . '</label><div class="dg-misure">' . $o . '</div></div>';
}

/** La tendina delle famiglie: globali (se richiesti), in casa, sistema, Google Fonts. */
function dg_famiglia(string $percorso, string $val, bool $conGlobali, bool $obbligatoria = false): string {
    global $globali;
    $o = '<select name="' . dg_nome($percorso) . '" class="dg-famiglia">';
    if (!$obbligatoria) $o .= '<option value="">Come il sito</option>';
    if ($conGlobali) {
        $o .= '<optgroup label="Caratteri globali">';
        foreach ($globali['font'] as $f) {
            $k = 'globale:font-' . $f['id'];
            $o .= '<option value="' . h($k) . '"' . ($val === $k ? ' selected' : '') . '>' . h($f['nome'] . ' (' . $f['famiglia'] . ')') . '</option>';
        }
        $o .= '</optgroup>';
    }
    $gruppi = [];
    foreach (design_font_elenco() as $n => $f) $gruppi[$f['gruppo']][] = $n;
    foreach ($gruppi as $g => $nomi) {
        $o .= '<optgroup label="' . h($g) . ($g === 'Google Fonts' ? ' (si caricano da Google solo se scelti)' : '') . '">';
        foreach ($nomi as $n) $o .= '<option value="' . h($n) . '"' . ($val === $n ? ' selected' : '') . '>' . h($n) . '</option>';
        $o .= '</optgroup>';
    }
    return $o . '</select>';
}

/** Un gruppo tipografico completo, a tendina, con il riassunto in testa. */
function dg_tipografia(string $percorso, array $t, string $etichetta = 'Tipografia', bool $conGlobali = true, bool $famigliaObbligatoria = false, bool $aperta = false): string {
    return '<details class="dg-tip"' . ($aperta ? ' open' : '') . '><summary><span class="dg-tip-etichetta">' . h($etichetta) . '</span><span class="dg-tip-riassunto">come il sito</span></summary><div class="dg-tip-corpo">'
        . '<div class="dg-campo"><label>Famiglia</label>' . dg_famiglia("$percorso.famiglia", (string)$t['famiglia'], $conGlobali, $famigliaObbligatoria) . '</div>'
        . dg_misure3("$percorso.dimensione", $t['dimensione'], 'Dimensione', 'es. 18px, 1.2em')
        . '<div class="dg-due">' . dg_scelta("$percorso.peso", (string)$t['peso'], DESIGN_PESI, 'Peso') . dg_scelta("$percorso.stile", (string)$t['stile'], DESIGN_STILI, 'Stile') . '</div>'
        . '<div class="dg-due">' . dg_scelta("$percorso.trasformazione", (string)$t['trasformazione'], DESIGN_TRASFORMAZIONI, 'Trasformazione') . dg_scelta("$percorso.decorazione", (string)$t['decorazione'], DESIGN_DECORAZIONI, 'Decorazione') . '</div>'
        . dg_misure3("$percorso.interlinea", $t['interlinea'], 'Altezza della riga', 'es. 1.5 oppure 28px')
        . '<div class="dg-due">' . dg_testo("$percorso.spaziatura_lettere", (string)$t['spaziatura_lettere'], 'Spaziatura lettere', 'es. 0.05em') . dg_testo("$percorso.spaziatura_parole", (string)$t['spaziatura_parole'], 'Spaziatura parole', 'es. 2px') . '</div>'
        . '</div></details>';
}

/** Una scatola richiudibile dentro una scheda. */
function dg_scatola(string $titolo, string $dentro, bool $aperta = true, string $aiuto = ''): string {
    return '<details class="dg-scatola"' . ($aperta ? ' open' : '') . '><summary>' . h($titolo) . '</summary><div class="dg-scatola-corpo">' . dg_aiuto($aiuto) . $dentro . '</div></details>';
}

/** Un colore personalizzato dell'elenco. $i può essere il segnaposto __I__ del modello. */
function dg_riga_colore(string $i, array $c): string {
    return '<div class="dg-voce"><span class="dg-maniglia" title="Colore personalizzato">C</span>'
        . '<input type="hidden" name="v[colori][personalizzati][' . $i . '][id]" value="' . h($c['id']) . '">'
        . '<input type="text" class="dg-voce-nome" name="v[colori][personalizzati][' . $i . '][nome]" value="' . h($c['nome']) . '" placeholder="Nome del colore" maxlength="60" required>'
        . dg_colore("colori.personalizzati.$i.valore", $c['valore'], 'Valore', false, '', true)
        . '<span class="dg-voce-var" title="La variabile CSS di questo colore">' . ($c['id'] !== '' ? '--g-colore-' . h($c['id']) : 'variabile dal nome') . '</span>'
        . '<button type="button" class="dg-x dg-elimina" title="Elimina questo colore">×</button></div>';
}

function dg_riga_font(string $i, array $f): string {
    return '<div class="dg-voce dg-voce-font"><div class="dg-voce-testa"><span class="dg-maniglia">Aa</span>'
        . '<input type="hidden" name="v[font][personalizzati][' . $i . '][id]" value="' . h($f['id']) . '">'
        . '<input type="text" class="dg-voce-nome" name="v[font][personalizzati][' . $i . '][nome]" value="' . h($f['nome']) . '" placeholder="Nome del carattere" maxlength="60" required>'
        . '<span class="dg-voce-var">' . ($f['id'] !== '' ? '--g-font-' . h($f['id']) : 'variabile dal nome') . '</span>'
        . '<button type="button" class="dg-x dg-elimina" title="Elimina questo carattere">×</button></div>'
        . dg_tipografia("font.personalizzati.$i", $f, 'Tipografia', false, true, true) . '</div>';
}

// ------------------------------------------------------------
// Le schede
// ------------------------------------------------------------

$schede = [
    'colori' => 'Colori globali', 'font' => 'Caratteri globali', 'tipografia' => 'Tipografia', 'pulsanti' => 'Pulsanti',
    'immagini' => 'Immagini', 'campi' => 'Campi dei moduli', 'layout' => 'Layout', 'sfondo' => 'Sfondo del sito',
    'sito' => 'Identità del sito', 'css' => 'CSS personalizzato',
];
if (!isset($schede[$schedaAperta])) $schedaAperta = 'colori';

$dove = [
    'primario' => 'Il viola dei pulsanti pieni e dei gradienti (la variabile --viola del sito).',
    'secondario' => 'Il ciano dei link attivi, degli occhielli e del pulsante a contorno (--ciano).',
    'testo' => 'Il colore del testo di tutto il sito (--testo).',
    'accento' => 'Il verde delle conferme (captcha verificato, indirizzi).',
    'sfondo' => 'Lo sfondo di tutte le pagine (--bg). La scheda Sfondo del sito può aggiungere un\'immagine.',
];
$contenuto = [];

// Colori
$o = '<p class="dg-intro">I colori di sistema hanno un nome fisso e li usa tutto il sito. I personalizzati li aggiungi tu, con il nome che vuoi: ogni colore diventa una variabile CSS e compare nelle tendine dell\'editor, così cambiandolo qui cambia ovunque.</p>';
$s = '';
foreach (DESIGN_SISTEMA_COLORI as $k => $n) $s .= '<div class="dg-sistema"><span class="dg-swatch dg-swatch-grande" data-globale="' . $k . '"></span><div>' . dg_colore("colori.sistema.$k", $v['colori']['sistema'][$k], $n, false, $dove[$k], true) . '<div class="dg-var">--g-colore-' . $k . '</div></div></div>';
$o .= dg_scatola('Colori di sistema', $s);
$s = '<div id="dg-colori-personalizzati">';
foreach ($v['colori']['personalizzati'] as $i => $c) $s .= dg_riga_colore((string)$i, $c);
$s .= '</div><button type="button" class="btn btn-secondary btn-sm" id="dg-aggiungi-colore">+ Aggiungi un colore</button>';
$o .= dg_scatola('Colori personalizzati', $s, true, 'I quattro di partenza (sfondo secondario, testo attenuato, testo discreto, bordi) sono quelli che il sito usa oggi: rinominarli non cambia niente, cambiarne il valore cambia il sito. Eliminarne uno riporta quella parte al colore scritto nel foglio di stile.');
$contenuto['colori'] = $o;

// Caratteri globali
$o = '<p class="dg-intro">Quattro caratteri di sistema e quelli che aggiungi tu. Inter e Space Grotesk sono serviti dal sito; i caratteri di sistema non si scaricano; un Google Font si carica da Google soltanto se lo scegli davvero da qualche parte (oggi il sito non chiama Google).</p>';
$dovef = ['primario' => 'I titoli del sito (--f-titolo): H1, H2, H3, il marchio.', 'secondario' => 'Libero: per i widget che vuoi distinguere dai titoli.', 'testo' => 'Il testo di tutto il sito (--f-testo).', 'accento' => 'Libero: per citazioni, numeri, etichette.'];
$s = '';
foreach (DESIGN_SISTEMA_FONT as $k => $n) $s .= '<div class="dg-sistema dg-sistema-font"><div class="dg-sistema-nome"><b>' . $n . '</b><div class="dg-var">--g-font-' . $k . '</div>' . dg_aiuto($dovef[$k]) . '</div>' . dg_tipografia("font.sistema.$k", $v['font']['sistema'][$k], $n, false, true) . '</div>';
$o .= dg_scatola('Caratteri di sistema', $s);
$s = '<div id="dg-font-personalizzati">';
foreach ($v['font']['personalizzati'] as $i => $f) $s .= dg_riga_font((string)$i, $f);
$s .= '</div><button type="button" class="btn btn-secondary btn-sm" id="dg-aggiungi-font">+ Aggiungi un carattere</button>';
$o .= dg_scatola('Caratteri personalizzati', $s, true, 'Un carattere personalizzato è una tipografia completa con un nome (per esempio "Citazione": Lora corsivo 22px). Lo ritrovi nelle tendine dell\'editor.');
$contenuto['font'] = $o;

// Tipografia
$o = '<p class="dg-intro">Il colore e la tipografia di ogni elemento del testo. Un valore vuoto vuol dire "come il sito oggi": niente cambia finché non scrivi qualcosa. I colori possono essere uno dei globali.</p>';
$t = $v['tipografia'];
$o .= dg_scatola('Corpo', dg_colore('tipografia.corpo.colore', $t['corpo']['colore'], 'Colore del testo') . dg_tipografia('tipografia.corpo.tipografia', $t['corpo']['tipografia']) . dg_testo('tipografia.corpo.spazio_paragrafi', (string)$t['corpo']['spazio_paragrafi'], 'Spazio fra i paragrafi', 'es. 1.2em'), true, 'Vale sul body: tutto quello che non ha una regola sua eredita da qui.');
$o .= dg_scatola('Paragrafi', dg_colore('tipografia.paragrafo.colore', $t['paragrafo']['colore'], 'Colore') . dg_tipografia('tipografia.paragrafo.tipografia', $t['paragrafo']['tipografia']), false);
$o .= dg_scatola('Link', '<div class="dg-stato"><h4>Normale</h4>' . dg_colore('tipografia.link.colore', $t['link']['colore'], 'Colore') . dg_tipografia('tipografia.link.tipografia', $t['link']['tipografia']) . '</div><div class="dg-stato"><h4>Al passaggio del mouse</h4>' . dg_colore('tipografia.link_hover.colore', $t['link_hover']['colore'], 'Colore') . dg_tipografia('tipografia.link_hover.tipografia', $t['link_hover']['tipografia']) . '</div>', false, 'I link nel testo delle pagine. Pulsanti, schede e menu hanno i loro stili.');
foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $k) {
    $o .= dg_scatola(strtoupper($k), dg_colore("tipografia.$k.colore", $t[$k]['colore'], 'Colore') . dg_tipografia("tipografia.$k.tipografia", $t[$k]['tipografia']), false);
}
$contenuto['tipografia'] = $o;

// Pulsanti
$b = $v['pulsanti'];
$o = '<p class="dg-intro">Tutti i pulsanti del sito (la classe .btn): quelli dell\'editor, della testata, dei moduli. Oggi hanno tre stili (pieno viola, pieno ciano, contorno): restano finché qui non imposti uno sfondo o un colore, che allora vale per tutti.</p>';
$o .= dg_scatola('Testo', dg_tipografia('pulsanti.tipografia', $b['tipografia']) . dg_testo('pulsanti.ombra_testo', $b['ombra_testo'], 'Ombra del testo', 'es. 0 1px 2px rgba(0,0,0,.4)'));
$stato = fn(string $s, string $n) => '<div class="dg-stato"><h4>' . $n . '</h4>' . dg_colore("pulsanti.$s.colore", $b[$s]['colore'], 'Colore del testo') . dg_colore("pulsanti.$s.sfondo", $b[$s]['sfondo'], 'Sfondo') . dg_colore("pulsanti.$s.bordo_colore", $b[$s]['bordo_colore'], 'Colore del bordo') . dg_testo("pulsanti.$s.ombra", $b[$s]['ombra'], 'Ombra', 'es. 0 0 28px rgba(168,85,247,.35)') . '</div>';
$o .= dg_scatola('Colori', $stato('normale', 'Normale') . $stato('hover', 'Al passaggio del mouse'));
$o .= dg_scatola('Bordo e spazi', '<div class="dg-due">' . dg_scelta('pulsanti.bordo_tipo', $b['bordo_tipo'], DESIGN_BORDI, 'Tipo di bordo') . dg_testo('pulsanti.bordo_larghezza', $b['bordo_larghezza'], 'Larghezza del bordo', 'es. 1px oppure 1px 2px') . '</div><div class="dg-due">' . dg_testo('pulsanti.raggio', $b['raggio'], 'Raggio degli angoli', 'oggi 12px') . dg_testo('pulsanti.padding', $b['padding'], 'Spazio interno', 'oggi 15px 22px') . '</div>');
$contenuto['pulsanti'] = $o;

// Immagini
$i = $v['immagini'];
$filtri = fn(string $p, array $f) => '<div class="dg-filtri">' . dg_testo("$p.sfocatura", $f['sfocatura'], 'Sfocatura (px)', '0') . dg_testo("$p.luminosita", $f['luminosita'], 'Luminosità (%)', '100') . dg_testo("$p.contrasto", $f['contrasto'], 'Contrasto (%)', '100') . dg_testo("$p.saturazione", $f['saturazione'], 'Saturazione (%)', '100') . dg_testo("$p.tonalita", $f['tonalita'], 'Tonalità (gradi)', '0') . '</div>';
$o = '<p class="dg-intro">Le immagini dentro il contenuto delle pagine e degli articoli. Vuoto = come oggi.</p>';
$o .= dg_scatola('Normale', '<div class="dg-due">' . dg_testo('immagini.raggio', $i['raggio'], 'Raggio degli angoli', 'es. 18px') . dg_testo('immagini.opacita', $i['opacita'], 'Opacità', 'da 0 a 1, es. 0.9') . '</div><div class="dg-due">' . dg_scelta('immagini.bordo_tipo', $i['bordo_tipo'], DESIGN_BORDI, 'Tipo di bordo') . dg_testo('immagini.bordo_larghezza', $i['bordo_larghezza'], 'Larghezza del bordo', 'es. 2px') . '</div>' . dg_colore('immagini.bordo_colore', $i['bordo_colore'], 'Colore del bordo') . dg_testo('immagini.ombra', $i['ombra'], 'Ombra', 'es. 0 10px 30px rgba(0,0,0,.4)') . '<label class="dg-etichetta">Filtri</label>' . $filtri('immagini.filtri', $i['filtri']) . dg_testo('immagini.transizione', $i['transizione'], 'Durata della transizione (ms)', 'es. 300'));
$o .= dg_scatola('Al passaggio del mouse', dg_testo('immagini.hover.opacita', $i['hover']['opacita'], 'Opacità', 'da 0 a 1') . dg_colore('immagini.hover.bordo_colore', $i['hover']['bordo_colore'], 'Colore del bordo') . dg_testo('immagini.hover.ombra', $i['hover']['ombra'], 'Ombra', '') . '<label class="dg-etichetta">Filtri</label>' . $filtri('immagini.hover.filtri', $i['hover']['filtri']), false);
$contenuto['immagini'] = $o;

// Campi dei moduli
$c = $v['campi'];
$o = '<p class="dg-intro">I campi dei moduli del sito: newsletter, i form del costruttore, la ricerca del glossario e quelli dei moduli.</p>';
$o .= dg_scatola('Etichetta', dg_colore('campi.etichetta.colore', $c['etichetta']['colore'], 'Colore') . dg_tipografia('campi.etichetta.tipografia', $c['etichetta']['tipografia']));
$o .= dg_scatola('Campo', dg_tipografia('campi.campo.tipografia', $c['campo']['tipografia']) . dg_colore('campi.campo.colore', $c['campo']['colore'], 'Colore del testo') . dg_colore('campi.campo.sfondo', $c['campo']['sfondo'], 'Sfondo') . dg_colore('campi.campo.bordo_colore', $c['campo']['bordo_colore'], 'Colore del bordo') . '<div class="dg-due">' . dg_testo('campi.campo.bordo_larghezza', $c['campo']['bordo_larghezza'], 'Larghezza del bordo', 'oggi 1px') . dg_testo('campi.campo.raggio', $c['campo']['raggio'], 'Raggio degli angoli', 'oggi 12px') . '</div>' . dg_testo('campi.campo.padding', $c['campo']['padding'], 'Spazio interno', 'oggi 14px 16px'));
$o .= dg_scatola('A fuoco (mentre si scrive)', dg_colore('campi.fuoco.colore', $c['fuoco']['colore'], 'Colore del testo') . dg_colore('campi.fuoco.sfondo', $c['fuoco']['sfondo'], 'Sfondo') . dg_colore('campi.fuoco.bordo_colore', $c['fuoco']['bordo_colore'], 'Colore del bordo') . dg_colore('campi.fuoco.contorno', $c['fuoco']['contorno'], 'Colore del contorno di fuoco', true, 'Oggi è il ciano del sito.'), false);
$contenuto['campi'] = $o;

// Layout
$l = $v['layout'];
$o = '<p class="dg-intro">Le misure di base delle pagine. I punti di rottura dicono all\'editor e al sito quando una pagina è "tablet" e quando è "telefono".</p>';
$o .= dg_scatola('Contenuto', dg_numero('layout.larghezza', $l['larghezza'], 'Larghezza del contenuto', 600, 2400, 'px', 'La larghezza massima del contenitore delle pagine (oggi 1180).') . dg_numero('layout.spazio_widget', $l['spazio_widget'], 'Spazio fra i widget', 0, 200, 'px', 'Lo spazio sotto ogni widget dell\'editor, quando il widget non ne ha uno suo (oggi 24).') . dg_numero('layout.spazio_colonne', $l['spazio_colonne'], 'Spazio fra le colonne', 0, 200, 'px', 'Lo spazio fra le colonne di una sezione, quando la sezione non ne ha uno suo (oggi 32).'));
$o .= dg_scatola('Punti di rottura', dg_numero('layout.tablet', $l['tablet'], 'Tablet: fino a', 600, 1600, 'px', 'Sopra questa larghezza la pagina è "desktop" (oggi 1024).') . dg_numero('layout.telefono', $l['telefono'], 'Telefono: fino a', 320, 1000, 'px', 'Fino a questa larghezza la pagina è "telefono" (oggi 767).'));
$contenuto['layout'] = $o;

// Sfondo
$s = $v['sfondo'];
$o = '<p class="dg-intro">Lo sfondo di tutte le pagine. Il colore di partenza è quello dei Colori globali ("Sfondo del sito"); qui puoi scegliere un colore diverso o mettere un\'immagine.</p>';
$o .= dg_scatola('Sfondo', dg_colore('sfondo.colore', $s['colore'], 'Colore', true, 'Vuoto = il colore globale "Sfondo del sito".') . dg_immagine('sfondo.immagine', $s['immagine'], 'Immagine') . '<div class="dg-due">' . dg_scelta('sfondo.posizione', $s['posizione'], DESIGN_SFONDO_POSIZIONI, 'Posizione') . dg_scelta('sfondo.ripetizione', $s['ripetizione'], DESIGN_SFONDO_RIPETIZIONI, 'Ripetizione') . '</div><div class="dg-due">' . dg_scelta('sfondo.dimensione', $s['dimensione'], DESIGN_SFONDO_DIMENSIONI, 'Dimensione') . dg_scelta('sfondo.allegato', $s['allegato'], DESIGN_SFONDO_ALLEGATI, 'Scorrimento') . '</div>' . dg_colore('sfondo.velo', $s['velo'], 'Velo sopra l\'immagine', true, 'Un colore, anche trasparente (es. rgba(6,10,23,.7)), steso sopra l\'immagine per far leggere il testo.')
    . '<div class="dg-campo"><label class="dg-spunta"><input type="hidden" name="v[sfondo][sfumature_sito]" value="0"><input type="checkbox" name="v[sfondo][sfumature_sito]" value="1"' . ($s['sfumature_sito'] ? ' checked' : '') . '> Mantieni le sfumature e le stelline del sito sopra lo sfondo</label></div>');
$contenuto['sfondo'] = $o;

// Identità del sito
$id = $v['sito'];
$o = '<p class="dg-intro">Nome, sottotitolo, logo e icona del sito. Li leggono la testata e il piede costruiti con l\'editor e la scheda del browser.</p>';
$o .= dg_scatola('Identità', dg_testo('sito.nome', $id['nome'], 'Nome del sito', (string)cms_impostazione('sito_nome', CMS_NOME), 'Nella testata oggi è scritto in maiuscolo. I titoli delle pagine (la scheda del browser) tengono il nome di oggi.') . dg_testo('sito.sottotitolo', $id['sottotitolo'], 'Sottotitolo', 'Una frase su cosa fai') . dg_testo('sito.descrizione', $id['descrizione'], 'Descrizione in una riga', '') . dg_immagine('sito.logo', $id['logo'], 'Logo', 'Vuoto = la testata scrive il nome in lettere, come oggi.') . dg_immagine('sito.favicon', $id['favicon'], 'Icona del sito (favicon)', 'L\'icona nella scheda del browser. Meglio quadrata, almeno 64 px.'));
$contenuto['sito'] = $o;

// CSS personalizzato
$x = $v['css'];
$o = '<p class="dg-intro">Per chi conosce il CSS. Quello che scrivi qui esce dopo tutto il resto, su ogni pagina del sito. Un errore di sintassi non rompe il sito, ma la regola sbagliata viene ignorata.</p>';
$o .= dg_scatola('CSS del sito', '<div class="dg-campo"><textarea name="v[css][sito]" rows="12" class="dg-codice" spellcheck="false" placeholder=".corpo h2 { letter-spacing: 0; }">' . h($x['sito']) . '</textarea></div>');
$contenuto['css'] = $o;

require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/design.css') ?>">
<form method="post" id="dg-form" class="dg" data-scheda="<?= h($schedaAperta) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="scheda" id="dg-scheda" value="<?= h($schedaAperta) ?>">
  <div class="dg-testa">
    <p class="dg-testa-testo">Le impostazioni che valgono per tutto il sito. A destra vedi il sito vero con le modifiche dal vivo; <b>niente cambia finché non premi Salva</b>.<?= design_ha_salvataggi() ? '' : ' Oggi non c\'è nessuna modifica salvata: il sito è esattamente com\'è scritto nel foglio di stile.' ?></p>
    <div class="dg-azioni">
      <span class="dg-stato-modifiche" id="dg-modificato" hidden>Modifiche non salvate</span>
      <a href="versioni.php?ambito=stile" class="btn btn-secondary" title="Tutte le versioni dello stile: confronta e ripristina">Cronologia</a><!-- versioni -->
      <?php if (design_ha_precedente()): ?><button type="submit" name="azione" value="indietro" class="btn btn-secondary" formnovalidate data-conferma="Richiamo la versione dello stile salvata prima dell'ultima. Confermi?">Versione precedente</button><?php endif; ?>
      <button type="submit" name="azione" value="ripristina" class="btn btn-danger" formnovalidate data-conferma="Cancello tutte le impostazioni dello Stile del sito: il sito torna esattamente com'era prima di toccarle. Confermi?">Ripristina predefiniti</button>
      <button type="submit" name="azione" value="salva" class="btn btn-primary" id="dg-salva">Salva</button>
    </div>
  </div>
  <div class="dg-layout">
    <aside class="dg-pannello">
      <nav class="dg-schede" aria-label="Schede dello stile">
        <?php foreach ($schede as $k => $n): ?><button type="button" data-scheda="<?= $k ?>" class="<?= $k === $schedaAperta ? 'attiva' : '' ?>"><?= h($n) ?></button><?php endforeach; ?>
      </nav>
      <div class="dg-contenuto">
        <?php foreach ($schede as $k => $n): ?>
        <section class="dg-sezione" data-scheda="<?= $k ?>"<?= $k === $schedaAperta ? '' : ' hidden' ?>><h2><?= h($n) ?></h2><?= $contenuto[$k] ?></section>
        <?php endforeach; ?>
      </div>
    </aside>
    <div class="dg-anteprima">
      <div class="dg-anteprima-barra">
        <select id="dg-anteprima-pagina" title="Quale pagina vedere">
          <?php foreach ($anteprime as [$u, $n]): ?><option value="<?= h($u) ?>"><?= h($n) ?></option><?php endforeach; ?>
        </select>
        <div class="dg-misure-anteprima" role="group" aria-label="Misura dell'anteprima">
          <button type="button" data-misura="d" class="attiva" title="Desktop">Desktop</button>
          <button type="button" data-misura="t" title="Tablet">Tablet</button>
          <button type="button" data-misura="m" title="Telefono">Telefono</button>
        </div>
        <span class="dg-anteprima-stato" id="dg-anteprima-stato"></span>
        <a href="<?= h(cms_sito_base()) ?>/" target="_blank" class="dg-apri">Apri il sito ↗</a>
      </div>
      <div class="dg-cornice" data-misura="d"><iframe id="dg-iframe" src="<?= h(cms_sito_base()) ?>/" data-base="<?= h(cms_sito_base()) ?>/" title="Anteprima del sito"></iframe></div>
    </div>
  </div>
</form>

<template id="dg-tpl-colore"><?= dg_riga_colore('__I__', ['id' => '', 'nome' => '', 'valore' => '#ffffff']) ?></template>
<template id="dg-tpl-font"><?= dg_riga_font('__I__', ['id' => '', 'nome' => ''] + design_tipografia_vuota(['famiglia' => 'Inter'])) ?></template>


<script id="dg-dati" type="application/json"><?= str_replace('</', '<\/', json_encode(['globali' => $globali, 'tablet' => (int)$l['tablet'], 'telefono' => (int)$l['telefono'], 'famiglieCss' => $globali['famiglie_css']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></script>
<script src="<?= pannello_file('assets/design.js') ?>"></script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
