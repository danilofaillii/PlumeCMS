<?php
/**
 * I widget del tema: quelli con cui si compongono header e footer (includes/modelli.php) e i modelli di
 * singolo contenuto. Si registrano nel framework dell'editor (editor_registra_widget, includes/editor-controlli.php)
 * e lo stile esce dai controlli con 'selettore', come per gli altri widget.
 *
 *   logo_sito          marchio: nome e sottotitolo del sito (o il logo immagine), link alla home
 *   nome_sito          il nome del sito in un tag a scelta
 *   descrizione_sito   la frase del sito (quella sotto il marchio nel piede)
 *   menu_nav           un menu di navigazione (admin/menu.php): orizzontale, verticale o a tendina, con il menu del telefono
 *   ricerca            il modulo di ricerca del sito (/?s=parola)
 *   titolo_contenuto   il titolo della pagina, dell'articolo o dell'aforisma corrente
 *   testo_contenuto    il corpo del contenuto corrente (per i modelli di singolo articolo)
 *   immagine_evidenza  l'immagine in evidenza del contenuto corrente
 *   info_contenuto     autore, data, categorie, minuti di lettura
 *   breadcrumb         il percorso: Home > Blog > Categoria > Titolo
 *   copyright          una riga con {anno}
 *
 * Il contenuto corrente lo dice modelli_contesto() (sito) oppure $GLOBALS['sito_contenuto_riga'] (anteprima dal pannello).
 * Nell'editor di un header o di un footer (GLOBALS modelli_anteprima) i widget del contenuto mostrano un esempio.
 * CSS: sito/assets/tema.css. Il set di icone: includes/icone.php (editor_icona).
 */

require_once __DIR__ . '/menu-nav.php';
require_once __DIR__ . '/modelli.php';

// ------------------------------------------------------------
// Dati del contenuto corrente
// ------------------------------------------------------------

/** Nome, sottotitolo, descrizione e logo del sito: dallo Stile del sito se c'è, altrimenti quelli di sempre. */
function tema_sito(): array {
    static $s = null;
    if ($s !== null) return $s;
    $s = ['nome' => (string)cms_impostazione('sito_nome', CMS_NOME), 'sottotitolo' => (string)cms_impostazione('sito_sottotitolo', ''), 'descrizione' => (string)cms_impostazione('sito_frase', cms_impostazione('sito_descrizione', '')), 'logo' => ''];
    if (is_file(__DIR__ . '/design.php')) {
        try {
            require_once __DIR__ . '/design.php';
            if (function_exists('design_sito')) {
                $d = design_sito();
                foreach (['nome', 'sottotitolo', 'descrizione', 'logo'] as $k) if (!empty($d[$k]) && is_string($d[$k])) $s[$k] = $d[$k];
            }
        } catch (Throwable $e) { /* senza database o senza tabella: restano i valori di sempre */ }
    }
    return $s;
}

/** Siamo nella tela dell'editor di un header o di un footer? Allora i widget del contenuto mostrano un esempio. */
function tema_esempio(): bool {
    return !empty($GLOBALS['modelli_anteprima']);
}

/** Il contesto del contenuto corrente (modelli_contesto), senza database se manca. */
function tema_contesto(): array {
    try {
        return modelli_contesto();
    } catch (Throwable $e) {
        return ['tipo' => 'altro', 'riga' => [], 'categorie' => [], 'percorso' => '', 'titolo' => '', 'c' => null];
    }
}

/** L'indirizzo "aperto" che la testata segnala con aria-current (vista_pagina lo mette in GLOBALS sito_attivo). */
function tema_attivo(): string {
    return (string)($GLOBALS['sito_attivo'] ?? ($GLOBALS['sito_percorso'] ?? ''));
}

// ------------------------------------------------------------
// Controlli comuni di stile
// ------------------------------------------------------------

function tema_ctl_testo(string $prefisso, string $selettore, string $sezione): array {
    return [
        ec($prefisso . '_tipografia', 'tipografia', 'Tipografia', [], 'stile', ['sezione' => $sezione, 'selettore' => $selettore]),
        ec($prefisso . '_colore', 'colore', 'Colore', '', 'stile', ['sezione' => $sezione, 'selettore' => $selettore, 'proprieta' => 'color']),
    ];
}

function tema_allineamento(string $selettore = '', string $sezione = 'Disposizione'): array {
    return ec('allineamento', 'allineamento', 'Allineamento', ['d' => ''], 'stile', ['sezione' => $sezione, 'responsive' => true, 'selettore' => $selettore, 'proprieta' => 'text-align']);
}

// ------------------------------------------------------------
// Logo, nome, descrizione
// ------------------------------------------------------------

editor_registra_widget('logo_sito', ['etichetta' => 'Logo del sito', 'icona' => 'DF', 'gruppo' => 'Tema', 'controlli' => [
    ec('modo', 'scelta', 'Cosa mostra', 'testo', 'contenuto', ['sezione' => 'Logo', 'opzioni' => ['testo' => 'Nome e sottotitolo', 'immagine' => 'Immagine', 'entrambi' => 'Immagine con nome e sottotitolo']]),
    ec('immagine', 'immagine', 'Immagine del logo', '', 'contenuto', ['sezione' => 'Logo', 'condizione' => ['modo' => ['immagine', 'entrambi']], 'aiuto' => 'Vuota: il logo dello Stile del sito.']),
    ec('nome', 'testo', 'Nome (vuoto: quello dello Stile del sito)', '', 'contenuto', ['sezione' => 'Logo', 'condizione' => ['modo' => ['testo', 'entrambi']]]),
    ec('sottotitolo', 'testo', 'Sottotitolo (vuoto: quello dello Stile del sito)', '', 'contenuto', ['sezione' => 'Logo', 'condizione' => ['modo' => ['testo', 'entrambi']]]),
    ec('senza_sottotitolo', 'interruttore', 'Nascondi il sottotitolo', false, 'contenuto', ['sezione' => 'Logo', 'condizione' => ['modo' => ['testo', 'entrambi']]]),
    ec('link', 'interruttore', 'Porta alla home', true, 'contenuto', ['sezione' => 'Logo']),
    ec('maiuscolo', 'interruttore', 'Nome in maiuscolo', true, 'stile', ['sezione' => 'Nome', 'selettore' => ' .marchio b', 'proprieta' => 'text-transform', 'acceso' => 'uppercase', 'spento' => 'none']),
    ec('nome_tipografia', 'tipografia', 'Tipografia del nome', [], 'stile', ['sezione' => 'Nome', 'selettore' => ' .marchio b']),
    ec('nome_colore', 'colore', 'Colore del nome', '', 'stile', ['sezione' => 'Nome', 'selettore' => ' .marchio b', 'proprieta' => 'color']),
    ec('sotto_maiuscolo', 'interruttore', 'Sottotitolo in maiuscolo', true, 'stile', ['sezione' => 'Sottotitolo', 'selettore' => ' .marchio small', 'proprieta' => 'text-transform', 'acceso' => 'uppercase', 'spento' => 'none']),
    ec('sotto_tipografia', 'tipografia', 'Tipografia del sottotitolo', [], 'stile', ['sezione' => 'Sottotitolo', 'selettore' => ' .marchio small']),
    ec('sotto_colore', 'colore', 'Colore del sottotitolo', '', 'stile', ['sezione' => 'Sottotitolo', 'selettore' => ' .marchio small', 'proprieta' => 'color']),
    ec('larghezza_immagine', 'misura', "Larghezza dell'immagine", ['d' => ['v' => 48, 'u' => 'px']], 'stile', ['sezione' => 'Immagine', 'responsive' => true, 'unita' => ['px', '%'], 'intervalli' => ['px' => [16, 600, 1]], 'selettore' => ' .marchio img', 'proprieta' => 'width']),
    ec('spazio_immagine', 'misura', 'Spazio tra immagine e nome', ['d' => ['v' => 12, 'u' => 'px']], 'stile', ['sezione' => 'Immagine', 'unita' => ['px'], 'selettore' => ' .marchio', 'proprieta' => 'gap']),
    tema_allineamento(),
]], function (array $v, array $ctx): string {
    $sito = tema_sito();
    $nome = trim((string)$v['nome']) !== '' ? $v['nome'] : $sito['nome'];
    $sotto = trim((string)$v['sottotitolo']) !== '' ? $v['sottotitolo'] : $sito['sottotitolo'];
    $img = trim((string)$v['immagine']) !== '' ? $v['immagine'] : $sito['logo'];
    $dentro = '';
    if ($v['modo'] !== 'testo') {
        $dentro .= $img !== '' ? '<img src="' . h($img) . '" alt="' . h($nome) . '" loading="eager">' : ($ctx['anteprima'] ? '<span class="ed-vuoto" style="display:inline-block">Scegli il logo</span>' : '');
    }
    if ($v['modo'] !== 'immagine') {
        // Solo testo: lo stesso HTML della testata di prima (<b> e <small> dentro .marchio). Con l'immagine, il testo sta in uno span accanto.
        $testo = '<b>' . h($nome) . '</b>' . (!$v['senza_sottotitolo'] && $sotto !== '' ? '<small>' . h($sotto) . '</small>' : '');
        $dentro .= $v['modo'] === 'testo' ? $testo : '<span class="marchio-testo">' . $testo . '</span>';
    }
    $classe = 'marchio' . ($v['modo'] !== 'testo' ? ' marchio-img' : '') . ($v['modo'] === 'entrambi' ? ' marchio-entrambi' : '');
    return $v['link'] ? '<a class="' . $classe . '" href="/" aria-label="' . h($nome) . ', home">' . $dentro . '</a>' : '<span class="' . $classe . '">' . $dentro . '</span>';
});

editor_registra_widget('nome_sito', ['etichetta' => 'Nome del sito', 'icona' => 'N', 'gruppo' => 'Tema', 'controlli' => [
    ec('testo', 'testo', 'Testo (vuoto: il nome dello Stile del sito)', '', 'contenuto', ['sezione' => 'Nome']),
    ec('tag', 'scelta', 'Tag HTML', 'p', 'contenuto', ['sezione' => 'Nome', 'opzioni' => ['p' => 'Paragrafo', 'span' => 'Testo in linea', 'div' => 'Blocco', 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3']]),
    ec('link', 'interruttore', 'Porta alla home', false, 'contenuto', ['sezione' => 'Nome']),
    ...tema_ctl_testo('nome', ' .ed-nome-sito', 'Testo'),
    tema_allineamento(),
]], function (array $v, array $ctx): string {
    $t = in_array($v['tag'], ['p', 'span', 'div', 'h1', 'h2', 'h3'], true) ? $v['tag'] : 'p';
    $nome = trim((string)$v['testo']) !== '' ? $v['testo'] : tema_sito()['nome'];
    $dentro = $v['link'] ? '<a href="/">' . h($nome) . '</a>' : h($nome);
    return "<$t class=\"ed-nome-sito\">$dentro</$t>";
});

editor_registra_widget('descrizione_sito', ['etichetta' => 'Frase del sito', 'icona' => '"', 'gruppo' => 'Tema', 'controlli' => [
    ec('testo', 'testolungo', 'Testo (vuoto: la frase dello Stile del sito)', '', 'contenuto', ['sezione' => 'Frase']),
    ...tema_ctl_testo('frase', ' .ed-descrizione-sito', 'Testo'),
    ec('margine_testo', 'dimensioni', 'Margini del paragrafo', [], 'stile', ['sezione' => 'Testo', 'selettore' => ' .ed-descrizione-sito', 'proprieta' => 'margin', 'unita' => ['px', 'em']]),
    tema_allineamento(),
]], function (array $v, array $ctx): string {
    $t = trim((string)$v['testo']) !== '' ? $v['testo'] : tema_sito()['descrizione'];
    return '<p class="ed-descrizione-sito">' . nl2br(h($t), false) . '</p>';
});

// ------------------------------------------------------------
// Menu di navigazione
// ------------------------------------------------------------

/** Le opzioni della tendina "Menu": quelli del pannello. Senza database, vuota. */
function tema_menu_opzioni(): array {
    $o = ['' => 'Scegli un menu'];
    try { foreach (menu_opzioni() as $id => $nome) $o[$id] = $nome; } catch (Throwable $e) { /* prove a secco */ }
    // 1.2.3: il menu che sta in una posizione, qualunque sia il suo id (header e footer che viaggiano da un sito all'altro).
    if (defined('MENU_POSIZIONI')) foreach (MENU_POSIZIONI as $p => $nome) if ($p !== '') $o['posizione:' . $p] = 'Quello in posizione: ' . $nome;
    return $o;
}

editor_registra_widget('menu_nav', ['etichetta' => 'Menu di navigazione', 'icona' => '=', 'gruppo' => 'Tema', 'controlli' => [
    ec('menu', 'scelta', 'Menu', '', 'contenuto', ['sezione' => 'Menu', 'opzioni' => tema_menu_opzioni(), 'aiuto' => 'I menu si creano dal pannello, voce Menu di navigazione.']),
    ec('titolo', 'testo', 'Titolo sopra il menu (facoltativo)', '', 'contenuto', ['sezione' => 'Menu']),
    ec('disposizione', 'scelta', 'Disposizione', 'orizzontale', 'contenuto', ['sezione' => 'Menu', 'opzioni' => ['orizzontale' => 'Orizzontale', 'verticale' => 'Verticale', 'tendina' => 'A tendina (pulsante che apre il menu)']]),
    ec('sottomenu', 'interruttore', 'Mostra le sottovoci', true, 'contenuto', ['sezione' => 'Menu']),
    ec('etichetta_aria', 'testo', 'Nome per i lettori di schermo', 'Principale', 'contenuto', ['sezione' => 'Menu']),
    ec('collasso', 'numero', 'Sotto questa larghezza (px) il menu diventa il pulsante del telefono (0 = mai)', 960, 'contenuto', ['sezione' => 'Menu del telefono', 'min' => 0, 'max' => 2000, 'condizione' => ['disposizione' => 'orizzontale']]),
    ec('hamburger_testo', 'testo', 'Testo del pulsante', 'Menu', 'contenuto', ['sezione' => 'Menu del telefono', 'condizione' => ['disposizione' => ['orizzontale', 'tendina']]]),
    ec('hamburger_icona', 'scelta', 'Icona del pulsante', '', 'contenuto', ['sezione' => 'Menu del telefono', 'opzioni' => ['' => 'Nessuna', 'menu' => 'Tre linee', 'punti' => 'Tre punti', 'chevron-giu' => 'Freccia in giù'], 'condizione' => ['disposizione' => ['orizzontale', 'tendina']]]),
    ec('extra_testo', 'testo', 'Voce in più in fondo al menu del telefono', '', 'contenuto', ['sezione' => 'Menu del telefono', 'condizione' => ['disposizione' => ['orizzontale', 'tendina']]]),
    ec('extra_link', 'testo', 'Link della voce in più', '', 'contenuto', ['sezione' => 'Menu del telefono', 'condizione' => ['disposizione' => ['orizzontale', 'tendina']]]),
    // Stile
    ec('spingi', 'interruttore', 'Spingi a destra (prende lo spazio libero a sinistra)', false, 'stile', ['sezione' => 'Disposizione', 'selettore' => '', 'proprieta' => 'margin-left', 'acceso' => 'auto']),
    ec('allineamento_o', 'allineamento', 'Allineamento delle voci', ['d' => ''], 'stile', ['sezione' => 'Disposizione', 'responsive' => true, 'flex' => true, 'selettore' => ' .mn-o>.mn-lista', 'proprieta' => 'justify-content', 'condizione' => ['disposizione' => 'orizzontale']]),
    ec('allineamento_v', 'allineamento', 'Allineamento del testo', ['d' => ''], 'stile', ['sezione' => 'Disposizione', 'responsive' => true, 'selettore' => '', 'proprieta' => 'text-align', 'condizione' => ['disposizione' => 'verticale']]),
    ec('spazio', 'misura', 'Spazio tra le voci', ['d' => ['v' => '', 'u' => 'px']], 'stile', ['sezione' => 'Disposizione', 'responsive' => true, 'unita' => ['px', 'em'], 'intervalli' => ['px' => [0, 120, 1]], 'selettore' => ' .mn>.mn-lista', 'proprieta' => 'gap', 'aiuto' => 'Vuoto: 26 px in orizzontale, 8 px in verticale.']),
    ec('voce_tipografia', 'tipografia', 'Tipografia delle voci', [], 'stile', ['sezione' => 'Voci', 'selettore' => ' .mn-lista a']),
    ec('voce_colore', 'colore', 'Colore', '', 'stile', ['sezione' => 'Voci', 'stati' => true, 'hover_su' => 'elemento', 'selettore' => ' .mn-lista a', 'proprieta' => 'color']),
    ec('voce_padding', 'dimensioni', 'Spazio interno delle voci', [], 'stile', ['sezione' => 'Voci', 'selettore' => ' .mn-lista a', 'proprieta' => 'padding', 'unita' => ['px', 'em']]),
    ec('attiva_colore', 'colore', 'Colore della voce attiva', '', 'stile', ['sezione' => 'Voce attiva', 'selettore' => ' .mn-attiva>a', 'proprieta' => 'color']),
    ec('indicatore', 'scelta', 'Segno della voce attiva', '', 'stile', ['sezione' => 'Voce attiva', 'opzioni' => ['' => 'Solo il colore', 'linea' => 'Linea sotto', 'sfondo' => 'Sfondo', 'punto' => 'Punto']]),
    ec('indicatore_colore', 'colore', 'Colore del segno', '', 'stile', ['sezione' => 'Voce attiva', 'selettore' => '', 'proprieta' => '--mn-ind', 'condizione' => ['!indicatore' => '']]),
    ec('sotto_sfondo', 'colore', 'Sfondo delle sottovoci', '', 'stile', ['sezione' => 'Sottovoci', 'selettore' => ' .mn-o .mn-sotto', 'proprieta' => 'background']),
    ec('sotto_colore', 'colore', 'Colore delle sottovoci', '', 'stile', ['sezione' => 'Sottovoci', 'stati' => true, 'hover_su' => 'elemento', 'selettore' => ' .mn-sotto a', 'proprieta' => 'color']),
    ec('sotto_bordo', 'bordo', 'Bordo del riquadro delle sottovoci', [], 'stile', ['sezione' => 'Sottovoci', 'selettore' => ' .mn-o .mn-sotto']),
    ...tema_ctl_testo('titolo', ' .mn-titolo', 'Titolo'),
    ec('titolo_margine', 'dimensioni', 'Margine del titolo', [], 'stile', ['sezione' => 'Titolo', 'selettore' => ' .mn-titolo', 'proprieta' => 'margin', 'unita' => ['px', 'em']]),
    ec('h_colore', 'colore', 'Colore del pulsante', '', 'stile', ['sezione' => 'Menu del telefono', 'selettore' => ' .mn-h>summary', 'proprieta' => 'color']),
    ec('h_sfondo', 'colore', 'Sfondo del pulsante', '', 'stile', ['sezione' => 'Menu del telefono', 'selettore' => ' .mn-h>summary', 'proprieta' => 'background']),
    ec('h_bordo', 'bordo', 'Bordo del pulsante', [], 'stile', ['sezione' => 'Menu del telefono', 'selettore' => ' .mn-h>summary']),
    ec('h_tipografia', 'tipografia', 'Tipografia del pulsante', [], 'stile', ['sezione' => 'Menu del telefono', 'selettore' => ' .mn-h>summary']),
    ec('p_sfondo', 'colore', 'Sfondo del pannello che si apre', '', 'stile', ['sezione' => 'Menu del telefono', 'selettore' => ' .mn-h-pannello', 'proprieta' => 'background']),
    ec('p_colore', 'colore', 'Colore delle voci nel pannello', '', 'stile', ['sezione' => 'Menu del telefono', 'selettore' => ' .mn-h-pannello a', 'proprieta' => 'color']),
    ec('p_tipografia', 'tipografia', 'Tipografia delle voci nel pannello', [], 'stile', ['sezione' => 'Menu del telefono', 'selettore' => ' .mn-h-pannello a']),
]], function (array $v, array $ctx): string {
    $menu = null;
    try {
        $menu = str_starts_with((string)$v['menu'], 'posizione:') ? menu_per_posizione(substr((string)$v['menu'], 10))
            : ($v['menu'] !== '' ? menu_leggi((int)$v['menu']) : null);
    } catch (Throwable $e) { $menu = null; }
    if (!$menu) return $ctx['anteprima'] ? '<div class="ed-vuoto">Scegli un menu nel pannello (o crealo dalla voce Menu di navigazione)</div>' : '';
    $voci = menu_voci_risolte($menu);
    if (!$v['sottomenu']) $voci = array_map(fn($x) => ['figli' => []] + $x, $voci);
    $attivo = tema_attivo();
    $aria = trim((string)$v['etichetta_aria']) !== '' ? $v['etichetta_aria'] : $menu['nome'];
    $titolo = trim((string)$v['titolo']) !== '' ? '<h4 class="mn-titolo">' . h($v['titolo']) . '</h4>' : '';
    $ind = in_array($v['indicatore'], ['linea', 'sfondo', 'punto'], true) ? ' mn-ind-' . $v['indicatore'] : '';
    $id = ltrim((string)($ctx['sel'] ?? ''), '.') ?: 'mn' . substr(md5(json_encode($v)), 0, 6);

    // Il pulsante del telefono (o della tendina): un <details> con le voci in piano.
    $hamburger = function (string $classe) use ($v, $voci, $aria): string {
        $icona = $v['hamburger_icona'] !== '' && function_exists('editor_icona') ? editor_icona($v['hamburger_icona'], 'mn-h-icona') : '';
        $extra = trim((string)$v['extra_testo']) !== '' && editor_link_sicuro((string)$v['extra_link']) !== ''
            ? '<a class="mn-h-extra" href="' . h(editor_link_sicuro((string)$v['extra_link'])) . '">' . h($v['extra_testo']) . '</a>' : '';
        return '<details class="mn-h ' . $classe . '"><summary>' . $icona . ($v['hamburger_testo'] !== '' ? '<span>' . h($v['hamburger_testo']) . '</span>' : '') . '</summary>'
            . '<nav class="mn-h-pannello" aria-label="' . h($aria) . '">' . menu_html_piatto($voci) . $extra . '</nav></details>';
    };

    if ($v['disposizione'] === 'tendina') {
        return $titolo . '<div class="mn mn-t' . $ind . '">' . $hamburger('mn-sempre') . '</div>';
    }
    if ($v['disposizione'] === 'verticale') {
        return $titolo . '<nav class="mn mn-v' . $ind . '" aria-label="' . h($aria) . '">' . menu_html_voci($voci, $attivo) . '</nav>';
    }
    $out = $titolo . '<nav class="mn mn-o' . $ind . '" aria-label="' . h($aria) . '">' . menu_html_voci($voci, $attivo) . '</nav>';
    $px = (int)$v['collasso'];
    if ($px > 0) {
        $out .= $hamburger('mn-telefono')
            . '<style>@media (max-width:' . $px . 'px){.' . $id . ' .mn-o{display:none}.' . $id . ' .mn-h{display:block}}@media (min-width:' . ($px + 1) . 'px){.' . $id . ' .mn-h{display:none}}</style>';
    }
    return $out;
});

// ------------------------------------------------------------
// Ricerca
// ------------------------------------------------------------

editor_registra_widget('ricerca', ['etichetta' => 'Ricerca', 'icona' => '?', 'gruppo' => 'Tema', 'controlli' => [
    ec('segnaposto', 'testo', 'Testo nella casella', 'Cerca nel sito', 'contenuto', ['sezione' => 'Ricerca']),
    ec('pulsante', 'scelta', 'Pulsante', 'icona', 'contenuto', ['sezione' => 'Ricerca', 'opzioni' => ['icona' => 'Icona della lente', 'testo' => 'Testo', 'nessuno' => 'Nessuno (si cerca con Invio)']]),
    ec('pulsante_testo', 'testo', 'Testo del pulsante', 'Cerca', 'contenuto', ['sezione' => 'Ricerca', 'condizione' => ['pulsante' => 'testo']]),
    ec('larghezza', 'misura', 'Larghezza', ['d' => ['v' => '', 'u' => 'px']], 'stile', ['sezione' => 'Casella', 'responsive' => true, 'unita' => ['px', '%'], 'intervalli' => ['px' => [120, 900, 1]], 'selettore' => ' .ed-cerca', 'proprieta' => 'max-width']),
    ec('casella_colore', 'colore', 'Colore del testo', '', 'stile', ['sezione' => 'Casella', 'selettore' => ' .ed-cerca input', 'proprieta' => 'color']),
    ec('casella_sfondo', 'colore', 'Sfondo', '', 'stile', ['sezione' => 'Casella', 'selettore' => ' .ed-cerca input', 'proprieta' => 'background']),
    ec('casella_bordo', 'bordo', 'Bordo', [], 'stile', ['sezione' => 'Casella', 'selettore' => ' .ed-cerca input']),
    ec('casella_tipografia', 'tipografia', 'Tipografia', [], 'stile', ['sezione' => 'Casella', 'selettore' => ' .ed-cerca input']),
    ec('bottone_colore', 'colore', 'Colore del pulsante', '', 'stile', ['sezione' => 'Pulsante', 'stati' => true, 'hover_su' => 'elemento', 'selettore' => ' .ed-cerca button', 'proprieta' => 'color']),
    ec('bottone_sfondo', 'colore', 'Sfondo del pulsante', '', 'stile', ['sezione' => 'Pulsante', 'stati' => true, 'hover_su' => 'elemento', 'selettore' => ' .ed-cerca button', 'proprieta' => 'background']),
    tema_allineamento(),
]], function (array $v, array $ctx): string {
    $b = '';
    if ($v['pulsante'] === 'icona') $b = '<button type="submit" aria-label="Cerca">' . (function_exists('editor_icona') ? editor_icona('cerca', 'ed-cerca-icona') : 'Cerca') . '</button>';
    elseif ($v['pulsante'] === 'testo') $b = '<button type="submit">' . h($v['pulsante_testo'] !== '' ? $v['pulsante_testo'] : 'Cerca') . '</button>';
    return '<form class="ed-cerca' . ($b === '' ? ' ed-cerca-solo' : '') . '" role="search" action="/" method="get"><label class="salta" for="' . h($ctx['id']) . '-s">Cerca</label>'
        . '<input id="' . h($ctx['id']) . '-s" type="search" name="s" placeholder="' . h($v['segnaposto']) . '" autocomplete="off">' . $b . '</form>';
});

// ------------------------------------------------------------
// Il contenuto corrente: titolo, testo, immagine, info, briciole
// ------------------------------------------------------------

editor_registra_widget('titolo_contenuto', ['etichetta' => 'Titolo del contenuto', 'icona' => 'T', 'gruppo' => 'Tema', 'controlli' => [
    ec('livello', 'scelta', 'Tag', 'h1', 'contenuto', ['sezione' => 'Titolo', 'opzioni' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'p' => 'Paragrafo', 'span' => 'Testo in linea'], 'aiuto' => 'Il titolo della pagina, dell\'articolo o dell\'aforisma su cui esce il modello.']),
    ec('prima', 'testo', 'Testo prima del titolo', '', 'contenuto', ['sezione' => 'Titolo']),
    ec('dopo', 'testo', 'Testo dopo il titolo', '', 'contenuto', ['sezione' => 'Titolo']),
    ec('link', 'interruttore', 'Link al contenuto', false, 'contenuto', ['sezione' => 'Titolo']),
    ...tema_ctl_testo('titolo', ' .ed-titolo-contenuto', 'Testo'),
    ec('ombra', 'ombra_testo', 'Ombra del testo', [], 'stile', ['sezione' => 'Testo', 'selettore' => ' .ed-titolo-contenuto']),
    tema_allineamento(),
]], function (array $v, array $ctx): string {
    $c = tema_contesto();
    $t = $c['titolo'] !== '' ? $c['titolo'] : (tema_esempio() || $ctx['anteprima'] ? 'Titolo della pagina' : '');
    if ($t === '') return '';
    $tag = in_array($v['livello'], ['h1', 'h2', 'h3', 'p', 'span'], true) ? $v['livello'] : 'h1';
    $dentro = h($v['prima']) . h($t) . h($v['dopo']);
    if ($v['link'] && $c['percorso'] !== '') $dentro = '<a href="' . h($c['percorso']) . '">' . $dentro . '</a>';
    return "<$tag class=\"ed-titolo-contenuto\">$dentro</$tag>";
});

editor_registra_widget('testo_contenuto', ['etichetta' => 'Testo del contenuto', 'icona' => 'P', 'gruppo' => 'Tema', 'controlli' => [
    ec('nota', 'intestazione', 'Il corpo del contenuto corrente', '', 'contenuto', ['sezione' => 'Testo', 'aiuto' => 'Mostra il testo dell\'articolo, della pagina o dell\'aforisma su cui esce il modello. Serve ai modelli di singolo contenuto; dentro una pagina normale non mostra niente.']),
    ec('testo_colore', 'colore', 'Colore del testo', '', 'stile', ['sezione' => 'Testo', 'selettore' => ' .ed-testo-contenuto', 'proprieta' => 'color']),
    ec('testo_tipografia', 'tipografia', 'Tipografia', [], 'stile', ['sezione' => 'Testo', 'selettore' => ' .ed-testo-contenuto']),
    ec('link_colore', 'colore', 'Colore dei link', '', 'stile', ['sezione' => 'Link', 'stati' => true, 'hover_su' => 'elemento', 'selettore' => ' .ed-testo-contenuto a', 'proprieta' => 'color']),
]], function (array $v, array $ctx): string {
    static $dentro = false;
    if ($dentro) return '';
    $c = tema_contesto();
    if (tema_esempio() || !$c['c'] || !function_exists('sito_html_contenuto')) {
        return $ctx['anteprima'] ? '<div class="ed-testo-contenuto corpo"><p>Qui esce il testo del contenuto su cui si applica il modello.</p><p>Nel sito prende il corpo dell\'articolo, della pagina o dell\'aforisma corrente.</p></div>' : '';
    }
    $dentro = true;
    try { $html = sito_html_contenuto($c['c']); } finally { $dentro = false; }
    return '<div class="ed-testo-contenuto corpo">' . $html . '</div>';
});

editor_registra_widget('immagine_evidenza', ['etichetta' => 'Immagine in evidenza', 'icona' => 'I', 'gruppo' => 'Tema', 'controlli' => [
    ec('nota', 'intestazione', "L'immagine in evidenza del contenuto corrente", '', 'contenuto', ['sezione' => 'Immagine']),
    ec('ripiego', 'immagine', 'Immagine se il contenuto non ne ha una', '', 'contenuto', ['sezione' => 'Immagine']),
    ec('link', 'interruttore', 'Link al contenuto', false, 'contenuto', ['sezione' => 'Immagine']),
    ec('larghezza', 'misura', 'Larghezza', ['d' => ['v' => 100, 'u' => '%']], 'stile', ['sezione' => 'Immagine', 'responsive' => true, 'unita' => ['%', 'px'], 'intervalli' => ['px' => [40, 1600, 1]], 'selettore' => ' img', 'proprieta' => 'width']),
    ec('altezza', 'misura', 'Altezza (vuota: proporzionata)', ['d' => ['v' => '', 'u' => 'px']], 'stile', ['sezione' => 'Immagine', 'responsive' => true, 'unita' => ['px', 'vh'], 'selettore' => ' img', 'proprieta' => 'height']),
    ec('bordo', 'bordo', 'Bordo e angoli', ['raggio' => ['d' => ['sopra' => 18, 'destra' => 18, 'sotto' => 18, 'sinistra' => 18, 'u' => 'px', 'legati' => true]]], 'stile', ['sezione' => 'Immagine', 'selettore' => ' img']),
    ec('ombra', 'ombra', 'Ombra', [], 'stile', ['sezione' => 'Immagine', 'selettore' => ' img']),
    tema_allineamento(),
]], function (array $v, array $ctx): string {
    $c = tema_contesto();
    $url = '';
    if ($c['c'] && !empty($c['c']['immagine']['url'])) $url = $c['c']['immagine']['url'];
    elseif (!empty($c['riga'])) $url = (string)($c['riga']['immagine_url'] ?: ($c['riga']['featured_image'] ?? ''));
    if ($url === '') $url = (string)$v['ripiego'];
    if ($url === '') return $ctx['anteprima'] ? '<div class="ed-vuoto">Immagine in evidenza del contenuto (questo contenuto non ne ha una)</div>' : '';
    $img = '<img src="' . h($url) . '" alt="' . h($c['titolo']) . '" loading="lazy">';
    if ($v['link'] && $c['percorso'] !== '') $img = '<a href="' . h($c['percorso']) . '">' . $img . '</a>';
    return '<figure class="ed-immagine ed-immagine-evidenza">' . $img . '</figure>';
});

editor_registra_widget('info_contenuto', ['etichetta' => 'Autore, data e categorie', 'icona' => 'i', 'gruppo' => 'Tema', 'controlli' => [
    ec('autore', 'interruttore', 'Autore', true, 'contenuto', ['sezione' => 'Cosa mostra']),
    ec('data', 'interruttore', 'Data', true, 'contenuto', ['sezione' => 'Cosa mostra']),
    ec('categorie', 'interruttore', 'Categorie', false, 'contenuto', ['sezione' => 'Cosa mostra']),
    ec('lettura', 'interruttore', 'Minuti di lettura', true, 'contenuto', ['sezione' => 'Cosa mostra']),
    ec('separatore', 'testo', 'Separatore', '', 'contenuto', ['sezione' => 'Cosa mostra', 'aiuto' => 'Vuoto: solo uno spazio, come negli articoli di oggi.']),
    ...tema_ctl_testo('info', ' .ed-info-contenuto', 'Testo'),
    ec('spazio', 'misura', 'Spazio tra le voci', ['d' => ['v' => '', 'u' => 'px']], 'stile', ['sezione' => 'Testo', 'unita' => ['px', 'em'], 'selettore' => ' .ed-info-contenuto', 'proprieta' => 'gap']),
    tema_allineamento(' .ed-info-contenuto'),
]], function (array $v, array $ctx): string {
    $c = tema_contesto();
    $esempio = tema_esempio() || ($c['tipo'] === 'altro' && $ctx['anteprima']);
    $r = $c['riga'];
    $pezzi = [];
    $autoreP = (string)cms_impostazione('autore_predefinito', '');
    $autore = $esempio ? ($autoreP !== '' ? $autoreP : 'Autore') : (string)($r['author'] ?? $autoreP);
    if ($v['autore'] && $autore !== '') $pezzi[] = '<span>' . h($autore) . '</span>';
    if ($v['data']) {
        $data = $esempio ? date('Y-m-d') : ($c['c']['data'] ?? (string)($r['publish_date'] ?? ($r['publish_at'] ?? '')));
        if ($data !== '' && function_exists('sito_data_estesa')) $pezzi[] = '<time datetime="' . h(substr($data, 0, 10)) . '">' . sito_data_estesa($data) . '</time>';
    }
    if ($v['categorie'] && ($esempio || $c['categorie'])) {
        $cat = $esempio ? ['categoria' => 'Categoria'] : [];
        if (!$esempio && function_exists('sito_categorie')) foreach ($c['categorie'] as $s) $cat[$s] = sito_categorie()[$s]['name'] ?? $s;
        $pezzi[] = '<span class="ed-info-cat">' . implode(', ', array_map(fn($s, $n) => '<a href="/category/' . h($s) . '/">' . h($n) . '</a>', array_keys($cat), $cat)) . '</span>';
    }
    if ($v['lettura']) {
        $min = $esempio ? 5 : 0;
        if (!$esempio && $c['c'] && function_exists('sito_html_contenuto') && function_exists('sito_minuti_lettura')) {
            static $dentro = false;
            if (!$dentro) { $dentro = true; try { $min = sito_minuti_lettura(sito_html_contenuto($c['c'])); } finally { $dentro = false; } }
        }
        if ($min > 0) $pezzi[] = '<span>' . $min . ' minuti di lettura</span>';
    }
    if (!$pezzi) return '';
    $sep = $v['separatore'] !== '' ? '<i class="ed-info-sep" aria-hidden="true">' . h($v['separatore']) . '</i>' : '';
    return '<div class="art-meta ed-info-contenuto">' . implode($sep, $pezzi) . '</div>';
});

editor_registra_widget('breadcrumb', ['etichetta' => 'Briciole di pane', 'icona' => '>', 'gruppo' => 'Tema', 'controlli' => [
    ec('home_testo', 'testo', 'Testo della prima voce', 'Home', 'contenuto', ['sezione' => 'Percorso']),
    ec('separatore', 'testo', 'Separatore', '›', 'contenuto', ['sezione' => 'Percorso']),
    ec('ultimo', 'interruttore', 'Mostra anche il titolo della pagina corrente', true, 'contenuto', ['sezione' => 'Percorso']),
    ...tema_ctl_testo('briciole', ' .ed-briciole', 'Testo'),
    ec('link_colore', 'colore', 'Colore dei link', '', 'stile', ['sezione' => 'Testo', 'stati' => true, 'hover_su' => 'elemento', 'selettore' => ' .ed-briciole a', 'proprieta' => 'color']),
    ec('sep_colore', 'colore', 'Colore del separatore', '', 'stile', ['sezione' => 'Testo', 'selettore' => ' .ed-briciole li+li::before', 'proprieta' => 'color']),
    tema_allineamento(' .ed-briciole'),
]], function (array $v, array $ctx): string {
    $c = tema_contesto();
    $voci = [[$v['home_testo'] !== '' ? $v['home_testo'] : 'Home', '/']];
    if (tema_esempio() || ($c['tipo'] === 'altro' && $ctx['anteprima'] && $c['titolo'] === '')) {
        $voci[] = ['Blog', defined('SITO_PAGINA_BLOG') ? SITO_PAGINA_BLOG : (string)cms_impostazione('pagina_blog', '/blog/')];
        $voci[] = ['Titolo della pagina', ''];
    } elseif ($c['tipo'] === 'articoli' || $c['tipo'] === 'aforismi') {
        $voci[] = ['Blog', defined('SITO_PAGINA_BLOG') ? SITO_PAGINA_BLOG : (string)cms_impostazione('pagina_blog', '/blog/')];
        $cat = $c['categorie'][0] ?? '';
        if ($cat !== '') $voci[] = [function_exists('sito_categorie') ? (sito_categorie()[$cat]['name'] ?? $cat) : $cat, "/category/$cat/"];
        $voci[] = [$c['titolo'], ''];
    } elseif ($c['tipo'] === 'pagine' && $c['percorso'] !== '/') {
        $voci[] = [$c['titolo'], ''];
    } elseif ($c['tipo'] === 'altro' && $c['percorso'] !== '' && $c['percorso'] !== '/') {
        $primo = explode('/', trim($c['percorso'], '/'))[0];
        $voci[] = [ucfirst(trim(str_replace('-', ' ', $primo))), ''];
    }
    if (!$v['ultimo'] && count($voci) > 1) array_pop($voci);
    if (count($voci) < 2) return '';
    $sep = h($v['separatore'] !== '' ? $v['separatore'] : '›');
    $li = '';
    foreach ($voci as $i => [$nome, $url]) {
        $ultimo = $i === count($voci) - 1;
        $li .= '<li>' . ($url !== '' && !$ultimo ? '<a href="' . h($url) . '">' . h($nome) . '</a>' : '<span aria-current="page">' . h($nome) . '</span>') . '</li>';
    }
    return '<nav class="ed-briciole" aria-label="Percorso" style="--sep:\'' . str_replace(["'", '\\'], '', $sep) . '\'"><ol>' . $li . '</ol></nav>';
});

// ------------------------------------------------------------
// Copyright
// ------------------------------------------------------------

editor_registra_widget('copyright', ['etichetta' => 'Copyright', 'icona' => 'C', 'gruppo' => 'Tema', 'controlli' => [
    ec('testo', 'testolungo', 'Testo', '© {anno} {sito}. Tutti i diritti riservati.', 'contenuto', ['sezione' => 'Testo', 'aiuto' => '{anno} diventa l\'anno corrente; {sito} il nome del sito.']),
    ...tema_ctl_testo('cr', ' .ed-copyright', 'Testo'),
    tema_allineamento(),
]], function (array $v, array $ctx): string {
    $t = str_replace(['{anno}', '{sito}'], [date('Y'), tema_sito()['nome']], (string)$v['testo']);
    return '<p class="ed-copyright">' . nl2br(h($t), false) . '</p>';
});

// Il CSS (sito/assets/tema.css) lo carica sempre vista_pagina: serve all'header e al footer di ogni pagina.
