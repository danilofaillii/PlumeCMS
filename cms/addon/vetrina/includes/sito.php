<?php
/**
 * Le pagine pubbliche della Vetrina (addon, 2.0.0). Le chiama l'evento sito_percorso del nucleo (sito/index.php), con
 * il percorso già senza la cartella del sito. Ogni pagina esce con sito_html() del nucleo: header, footer, tema, CSP.
 *
 *   /negozio/                     elenco, ?categoria=<slug> e ?ordina=prezzo|prezzo-giu|nuovi
 *   /negozio/<prodotto>/          scheda, con i dati per Google (Product e Offer)
 *   /negozio/carrello/            carrello; in POST: aggiungi, aggiorna, togli
 *   /negozio/ordine/              richiesta d'ordine; in POST: invia
 *   /negozio/ordine/fatto/?n&k    ordine ricevuto (solo con la firma del link)
 *   /negozio/condizioni/          condizioni di vendita
 * I POST vogliono la richiesta dallo stesso sito (Origin o Referer); l'ordine anche captcha, consenso e il limite all'ora.
 */
require_once __DIR__ . '/vetrina.php';

const VT_MESSAGGI = [
    'aggiunto' => 'Aggiunto al carrello.',
    'aggiornato' => 'Carrello aggiornato.',
    'origine' => 'Richiesta non valida: ricarica la pagina e riprova.',
];

function vt_sito_rispondi(string $percorso, array $q): void {
    $b = vt_base();
    if ($percorso === rtrim($b, '/')) sito_vai($b, 301);
    $resto = substr($percorso, strlen($b));
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if ($post && !sicurezza_origine_ok()) { header('Cache-Control: no-store'); vt_pagina(403, 'Richiesta non valida', vt_cortesia('Richiesta non valida', VT_MESSAGGI['origine'])); }
    if ($resto === '') vt_pagina_elenco($q);
    if ($resto === 'carrello/') vt_pagina_carrello($post);
    if ($resto === 'ordine/') vt_pagina_ordine($post);
    if ($resto === 'ordine/fatto/') vt_pagina_fatto($q);
    if ($resto === 'condizioni/') vt_pagina_condizioni();
    if (preg_match('~^([a-z0-9][a-z0-9-]{0,189})/$~', $resto, $m) && ($p = vt_prodotto_per_slug($m[1]))) vt_pagina_prodotto($p);
    // Senza la barra finale: alla stessa pagina con la barra.
    if (!str_ends_with($resto, '/') && preg_match('~^[a-z0-9/-]{1,200}$~', $resto) && (in_array($resto, ['carrello', 'ordine', 'condizioni'], true) || vt_prodotto_per_slug($resto))) sito_vai($b . $resto . '/', 301);
    // Tutto il resto: il nucleo risponde 404.
}

/** La pagina finita, con il foglio e lo script della vetrina in testa. */
function vt_pagina(int $codice, string $titolo, string $corpo, array $meta = [], string $jsonld = ''): never {
    $css = cms_modulo_url('vetrina') . '/assets/vetrina.css';
    $f = cms_modulo_dir('vetrina') . '/assets/vetrina.css';
    $js = cms_modulo_dir('vetrina') . '/assets/vetrina.js';
    $testa = '<link rel="stylesheet" href="' . h($css . (is_file($f) ? '?v=' . filemtime($f) : '')) . '">' . "\n" . $jsonld;
    $corpo .= '<script src="' . h(cms_modulo_url('vetrina') . '/assets/vetrina.js' . (is_file($js) ? '?v=' . filemtime($js) : '')) . '" defer></script>';
    $meta += ['titolo' => seo_titolo($titolo, 'altro'), 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []];
    sito_html($codice, $meta, '<div class="wrap vt">' . $corpo . '</div>', $testa, vt_base());
}

function vt_cortesia(string $titolo, string $testo, string $azione = ''): string {
    return '<div class="vt-cortesia"><h1>' . h($titolo) . '</h1><p>' . h($testo) . '</p>' . $azione . '</div>';
}

function vt_avviso(array $q): string {
    $m = is_string($q['msg'] ?? null) ? (VT_MESSAGGI[$q['msg']] ?? '') : '';
    return $m !== '' ? '<p class="vt-avviso" role="status">' . h($m) . '</p>' : '';
}

/** Il prezzo con lo sconto barrato. */
function vt_prezzo_html(array $p, ?array $v = null): string {
    $pieno = vt_prezzo_pieno($p, $v);
    return '<span class="vt-prezzo"' . ($pieno ? ' data-sconto' : '') . '>' . ($pieno ? '<s>' . h(vt_euro($pieno)) . '</s> ' : '') . '<b>' . h(vt_euro(vt_prezzo($p, $v))) . '</b></span>';
}

/** Il prezzo "da" per un prodotto con varianti di prezzo diverso. */
function vt_prezzo_scheda_html(array $p): string {
    $prezzi = array_unique(array_map(fn($v) => vt_prezzo($p, $v), $p['varianti']));
    if (count($prezzi) > 1) return '<span class="vt-prezzo">da <b>' . h(vt_euro(min($prezzi))) . '</b></span>';
    return vt_prezzo_html($p, $p['varianti'][0] ?? null);
}

/** Una scheda nella griglia dei prodotti (anche nel widget Prodotti). */
function vt_scheda_html(array $p): string {
    $foto = $p['foto'][0] ?? '';
    $disp = vt_disponibile($p);
    return '<article class="vt-scheda' . ($disp ? '' : ' vt-esaurito') . '"><a href="' . h(vt_base() . $p['slug'] . '/') . '">'
        . '<span class="vt-foto">' . ($foto !== '' ? '<img src="' . h($foto) . '" alt="' . h($p['nome']) . '" loading="lazy">' : '<span class="vt-senza-foto" aria-hidden="true"></span>')
        . ($disp ? ($p['evidenza'] ? '<span class="vt-bollino">In evidenza</span>' : (vt_prezzo_pieno($p) ? '<span class="vt-bollino">In offerta</span>' : '')) : '<span class="vt-bollino vt-bollino-esaurito">Esaurito</span>') . '</span>'
        . '<span class="vt-nome">' . h($p['nome']) . '</span>' . ($p['breve'] !== '' ? '<span class="vt-breve">' . h($p['breve']) . '</span>' : '')
        . vt_prezzo_scheda_html($p) . '</a></article>';
}

function vt_griglia_html(array $prodotti): string {
    if (!$prodotti) return '<p class="vt-vuoto">Nessun prodotto, per ora.</p>';
    return '<div class="vt-griglia">' . implode('', array_map('vt_scheda_html', $prodotti)) . '</div>';
}

/** Il pulsante del carrello in cima alle pagine del negozio. */
function vt_barra_carrello(): string {
    $n = vt_carrello_unita();
    return '<a class="vt-al-carrello" href="' . h(vt_base() . 'carrello/') . '">Carrello <span class="vt-conta">' . $n . '</span></a>';
}

// ------------------------------------------------------------

function vt_pagina_elenco(array $q): never {
    $s = vt_impostazioni();
    $cat = null;
    if (isset($q['categoria'])) {
        $slug = is_string($q['categoria']) ? $q['categoria'] : '';
        $cat = preg_match('/^[a-z0-9-]{1,140}$/', $slug) ? vt_categoria_per_slug($slug) : null;
        if (!$cat) vt_pagina(404, 'Categoria non trovata', vt_cortesia('Categoria non trovata', 'Questa categoria non c\'è più.', '<p><a class="btn btn-viola" href="' . h(vt_base()) . '">Tutti i prodotti</a></p>'));
    }
    $ordina = is_string($q['ordina'] ?? null) && in_array($q['ordina'], ['prezzo', 'prezzo-giu', 'nuovi'], true) ? $q['ordina'] : '';
    $prodotti = vt_prodotti(['categoria' => $cat ? (int)$cat['id'] : 0, 'ordina' => $ordina]);
    $titolo = $cat ? $cat['nome'] : (string)$s['nome_negozio'];
    $filtri = '<nav class="vt-filtri" aria-label="Categorie"><a href="' . h(vt_base()) . '"' . (!$cat ? ' aria-current="page"' : '') . '>Tutti</a>';
    foreach (vt_categorie() as $c) $filtri .= '<a href="' . h(vt_base() . '?categoria=' . rawurlencode($c['slug'])) . '"' . ($cat && (int)$cat['id'] === (int)$c['id'] ? ' aria-current="page"' : '') . '>' . h($c['nome']) . '</a>';
    $filtri .= '</nav>';
    $ord = '<form class="vt-ordina" method="get" action="' . h(vt_base()) . '">' . ($cat ? '<input type="hidden" name="categoria" value="' . h($cat['slug']) . '">' : '')
        . '<label>Ordina <select name="ordina" data-vt-invia>';
    foreach (['' => 'Come li proponiamo', 'prezzo' => 'Prezzo più basso', 'prezzo-giu' => 'Prezzo più alto', 'nuovi' => 'Ultimi arrivati'] as $k => $et) $ord .= '<option value="' . h($k) . '"' . ($ordina === $k ? ' selected' : '') . '>' . h($et) . '</option>';
    $ord .= '</select></label><noscript><button class="btn btn-filo" type="submit">Ordina</button></noscript></form>';
    $intro = $cat ? (string)$cat['descrizione'] : (string)$s['intro'];
    $corpo = '<div class="vt-testa"><div><span class="occhiello">' . h((string)$s['nome_negozio']) . '</span><h1>' . h($titolo) . '</h1>'
        . ($intro !== '' ? '<p class="vt-intro">' . nl2br(h($intro)) . '</p>' : '') . '</div>' . vt_barra_carrello() . '</div>'
        . $filtri . $ord . vt_griglia_html($prodotti);
    $url = vt_url_assoluto(vt_base() . ($cat ? '?categoria=' . rawurlencode($cat['slug']) : ''));
    vt_pagina(200, $titolo, $corpo, ['robots' => $ordina === '' ? 'index' : 'noindex', 'canonical' => $url, 'descrizione' => mb_substr($intro, 0, 160),
        'og' => ['og:title' => $titolo, 'og:url' => $url, 'og:type' => 'website']]);
}

function vt_pagina_prodotto(array $p): never {
    $disp = vt_disponibile($p);
    $foto = $p['foto'];
    $url = vt_url_assoluto(vt_base() . $p['slug'] . '/');
    $assoluta = fn(string $f) => preg_match('~^https?://~', $f) ? $f : vt_origine() . $f;
    $galleria = '<div class="vt-galleria">' . ($foto ? '<figure class="vt-principale"><img src="' . h($foto[0]) . '" alt="' . h($p['nome']) . '" data-vt-principale></figure>' : '<figure class="vt-principale"><span class="vt-senza-foto" aria-hidden="true"></span></figure>');
    if (count($foto) > 1) {
        $galleria .= '<div class="vt-miniature">';
        foreach ($foto as $i => $f) $galleria .= '<a href="' . h($f) . '" data-vt-foto="' . h($f) . '"' . ($i === 0 ? ' aria-current="true"' : '') . '><img src="' . h($f) . '" alt="' . h($p['nome'] . ', foto ' . ($i + 1)) . '" loading="lazy"></a>';
        $galleria .= '</div>';
    }
    $galleria .= '</div>';
    // Il modulo "Aggiungi al carrello": variante (se ci sono), quantità. Il prezzo lo decide il server.
    $form = '<form class="vt-compra" method="post" action="' . h(vt_base() . 'carrello/') . '"><input type="hidden" name="azione" value="aggiungi"><input type="hidden" name="prodotto" value="' . $p['id'] . '">';
    if ($p['varianti']) {
        $etichetta = trim($p['opzione1'] . ($p['opzione2'] !== '' ? ' e ' . $p['opzione2'] : '')) ?: 'Variante';
        $form .= '<label class="vt-campo"><span>' . h($etichetta) . '</span><select name="variante" required data-vt-variante><option value="">Scegli</option>';
        foreach ($p['varianti'] as $v) {
            $ok = vt_disponibile($p, $v);
            $form .= '<option value="' . $v['id'] . '" data-prezzo="' . h(vt_euro(vt_prezzo($p, $v))) . '"' . ($ok ? '' : ' disabled') . '>' . h(vt_variante_nome($v)) . ' &middot; ' . h(vt_euro(vt_prezzo($p, $v))) . ($ok ? '' : ' (esaurito)') . '</option>';
        }
        $form .= '</select></label>';
    } else $form .= '<input type="hidden" name="variante" value="0">';
    $form .= '<label class="vt-campo vt-qta"><span>Quantità</span><input type="number" name="qta" value="1" min="1" max="' . VT_QTA_MAX . '" inputmode="numeric" required></label>'
        . '<button class="btn btn-viola" type="submit"' . ($disp ? '' : ' disabled') . '>' . ($disp ? 'Aggiungi al carrello' : 'Esaurito') . '</button></form>';
    $cats = array_values(array_filter(vt_categorie(), fn($c) => in_array((int)$c['id'], $p['categorie'], true)));
    $briciole = '<nav class="vt-briciole" aria-label="Sei qui"><a href="' . h(vt_base()) . '">' . h((string)vt_impostazioni()['nome_negozio']) . '</a>'
        . ($cats ? ' &rsaquo; <a href="' . h(vt_base() . '?categoria=' . rawurlencode($cats[0]['slug'])) . '">' . h($cats[0]['nome']) . '</a>' : '') . '</nav>';
    $desc = trim((string)$p['descrizione']);
    $corpo = '<div class="vt-testa vt-testa-piccola">' . $briciole . vt_barra_carrello() . '</div>'
        . '<div class="vt-prodotto">' . $galleria . '<div class="vt-dati"><h1>' . h($p['nome']) . '</h1>'
        . '<p class="vt-prezzo-grande" data-vt-prezzo>' . vt_prezzo_scheda_html($p) . '</p><p class="vt-iva">IVA inclusa' . ($p['codice'] !== '' ? ' &middot; codice ' . h($p['codice']) : '') . '</p>'
        . ($p['breve'] !== '' ? '<p class="vt-breve-grande">' . h($p['breve']) . '</p>' : '')
        . '<p class="vt-disponibilita ' . ($disp ? 'si' : 'no') . '">' . ($disp ? 'Disponibile' : 'Esaurito') . '</p>' . $form
        . ($desc !== '' ? '<div class="vt-descrizione">' . implode('', array_map(fn($par) => '<p>' . nl2br(h(trim($par))) . '</p>', preg_split('/\n\s*\n/', $desc))) . '</div>' : '')
        . '</div></div>';
    // Dati per Google: Product con un'offerta (o AggregateOffer se le varianti hanno prezzi diversi).
    $prezzi = $p['varianti'] ? array_map(fn($v) => vt_prezzo($p, $v), $p['varianti']) : [vt_prezzo($p)];
    $offerta = count(array_unique($prezzi)) > 1
        ? ['@type' => 'AggregateOffer', 'priceCurrency' => 'EUR', 'lowPrice' => number_format(min($prezzi) / 100, 2, '.', ''), 'highPrice' => number_format(max($prezzi) / 100, 2, '.', ''), 'offerCount' => count($prezzi)]
        : ['@type' => 'Offer', 'priceCurrency' => 'EUR', 'price' => number_format($prezzi[0] / 100, 2, '.', '')];
    $offerta += ['availability' => $disp ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock', 'url' => $url];
    $s = vt_impostazioni();
    if (trim((string)$s['attivita_nome']) !== '') $offerta['seller'] = ['@type' => 'Organization', 'name' => (string)$s['attivita_nome']];
    $ld = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p['nome'], 'url' => $url, 'offers' => $offerta];
    if ($foto) $ld['image'] = array_map($assoluta, $foto);
    if ($desc !== '' || $p['breve'] !== '') $ld['description'] = mb_substr($p['breve'] !== '' ? $p['breve'] : $desc, 0, 500);
    if ($p['codice'] !== '') $ld['sku'] = $p['codice'];
    $jsonld = '<script type="application/ld+json">' . json_encode($ld, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    $descr = mb_substr($p['breve'] !== '' ? $p['breve'] : preg_replace('/\s+/', ' ', $desc), 0, 160);
    vt_pagina(200, $p['nome'], $corpo, ['robots' => 'index', 'canonical' => $url, 'descrizione' => $descr,
        'og' => array_filter(['og:title' => $p['nome'], 'og:type' => 'product', 'og:url' => $url, 'og:description' => $descr, 'og:image' => $foto ? $assoluta($foto[0]) : '']) ], $jsonld);
}

function vt_pagina_carrello(bool $post): never {
    header('Cache-Control: no-store');
    $errore = '';
    if ($post) {
        $az = (string)($_POST['azione'] ?? '');
        if ($az === 'aggiungi') {
            $pid = filter_var($_POST['prodotto'] ?? '', FILTER_VALIDATE_INT); $vid = filter_var($_POST['variante'] ?? '0', FILTER_VALIDATE_INT); $qta = filter_var($_POST['qta'] ?? '1', FILTER_VALIDATE_INT);
            $errore = $pid === false || $vid === false || $qta === false ? 'Richiesta non valida.' : vt_carrello_aggiungi((int)$pid, (int)$vid, (int)$qta);
            if ($errore === '') sito_vai(vt_base() . 'carrello/?msg=aggiunto', 303);
        } elseif ($az === 'aggiorna' || $az === 'togli') {
            $q = [];
            foreach ((array)($_POST['qta'] ?? []) as $i => $n) if (is_int($i) || ctype_digit((string)$i)) $q[(int)$i] = ($n = filter_var($n, FILTER_VALIDATE_INT)) === false ? 1 : $n;
            // Il pulsante Togli di una riga manda il numero della riga: quella va a zero, le altre restano come sono.
            if ($az === 'togli' || isset($_POST['riga'])) $q = [(int)($_POST['riga'] ?? -1) => 0];
            vt_carrello_aggiorna($q);
            sito_vai(vt_base() . 'carrello/?msg=aggiornato', 303);
        } else $errore = 'Richiesta non valida.';
    }
    $c = vt_carrello_calcola();
    $corpo = '<div class="vt-testa vt-testa-piccola"><nav class="vt-briciole"><a href="' . h(vt_base()) . '">&lsaquo; Continua gli acquisti</a></nav></div><h1>Il tuo carrello</h1>'
        . vt_avviso($_GET) . ($errore !== '' ? '<p class="modulo-errore" role="alert">' . h($errore) . '</p>' : '');
    foreach ($c['problemi'] as $pr) $corpo .= '<p class="modulo-errore" role="alert">' . h($pr) . '</p>';
    if (!$c['righe']) {
        $corpo .= '<p class="vt-vuoto">Il carrello è vuoto.</p><p><a class="btn btn-viola" href="' . h(vt_base()) . '">Guarda i prodotti</a></p>';
        vt_pagina(200, 'Carrello', $corpo);
    }
    $corpo .= vt_carrello_tabella($c, true) . vt_totali_html($c, 'spedizione')
        . ($c['disponibile'] ? '<p class="vt-avanti"><a class="btn btn-viola" href="' . h(vt_base() . 'ordine/') . '">Procedi con l\'ordine</a></p>' : '<p class="modulo-errore">Un prodotto non è disponibile nella quantità scelta: cambia la quantità o toglilo.</p>');
    vt_pagina(200, 'Carrello', $corpo);
}

/** Le righe del carrello: con $modifica, quantità e Togli (un modulo solo, per Aggiorna). */
function vt_carrello_tabella(array $c, bool $modifica): string {
    $t = $modifica ? '<form method="post" action="' . h(vt_base() . 'carrello/') . '" class="vt-carrello"><input type="hidden" name="azione" value="aggiorna">' : '<div class="vt-carrello">';
    $t .= '<ul class="vt-righe">';
    foreach ($c['righe'] as $r) {
        $t .= '<li class="vt-riga' . ($r['disponibile'] ? '' : ' vt-riga-no') . '">'
            . ($r['foto'] !== '' ? '<img src="' . h($r['foto']) . '" alt="" loading="lazy">' : '<span class="vt-senza-foto" aria-hidden="true"></span>')
            . '<div class="vt-riga-nome"><a href="' . h(vt_base() . $r['slug'] . '/') . '">' . h($r['nome']) . '</a>' . ($r['variante'] !== '' ? '<small>' . h($r['variante']) . '</small>' : '')
            . '<small>' . h(vt_euro($r['prezzo_cent'])) . ' l\'uno' . ($r['disponibile'] ? '' : ' &middot; <b>' . ($r['scorta'] !== null && $r['scorta'] > 0 ? 'ne restano ' . (int)$r['scorta'] : 'esaurito') . '</b>') . '</small></div>'
            . ($modifica ? '<label class="vt-riga-qta"><span class="vt-nascosto">Quantità di ' . h($r['nome']) . '</span><input type="number" name="qta[' . (int)$r['indice'] . ']" value="' . (int)$r['qta'] . '" min="0" max="' . VT_QTA_MAX . '" inputmode="numeric"></label>'
                . '<button class="vt-togli" type="submit" name="riga" value="' . (int)$r['indice'] . '" formaction="' . h(vt_base() . 'carrello/') . '" data-vt-togli>Togli</button>' : '<span class="vt-riga-qta">&times; ' . (int)$r['qta'] . '</span>')
            . '<b class="vt-riga-tot">' . h(vt_euro($r['totale_cent'])) . '</b></li>';
    }
    $t .= '</ul>';
    return $t . ($modifica ? '<p><button class="btn btn-filo" type="submit">Aggiorna il carrello</button></p></form>' : '</div>');
}

function vt_totali_html(array $c, string $consegna): string {
    $s = vt_impostazioni();
    $nota = '';
    if ($consegna === 'spedizione' && (int)$s['gratis_oltre_cent'] > 0 && $c['spedizione_cent'] > 0) $nota = '<small>Spedizione gratis da ' . h(vt_euro((int)$s['gratis_oltre_cent'])) . ': ti mancano ' . h(vt_euro((int)$s['gratis_oltre_cent'] - $c['subtotale_cent'])) . '.</small>';
    return '<dl class="vt-totali"><dt>Prodotti</dt><dd>' . h(vt_euro($c['subtotale_cent'])) . '</dd>'
        . ($consegna === 'ritiro' ? '<dt>Ritiro in negozio</dt><dd>gratis</dd>' : (vt_consegne_attive()['spedizione'] ?? false ? '<dt>Spedizione</dt><dd>' . ($c['spedizione_cent'] ? h(vt_euro($c['spedizione_cent'])) : 'gratis') . '</dd>' : ''))
        . '<dt class="vt-totale">Totale</dt><dd class="vt-totale" data-vt-totale>' . h(vt_euro($c['totale_cent'])) . '</dd></dl>'
        . '<p class="vt-iva">IVA inclusa (' . h(vt_euro($c['iva_cent'])) . ').' . ($nota !== '' ? ' ' . $nota : '') . '</p>';
}

function vt_pagina_ordine(bool $post): never {
    header('Cache-Control: no-store');
    require_once dirname(__DIR__, 3) . '/includes/moduli.php';
    $righe = vt_carrello_leggi();
    $d = ['nome' => '', 'email' => '', 'telefono' => '', 'indirizzo' => '', 'cap' => '', 'citta' => '', 'note' => '', 'consenso' => false,
        'consegna' => array_key_first(vt_consegne_attive()) ?? '', 'metodo' => array_key_first(vt_metodi_attivi()) ?? ''];
    $err = []; $generale = '';
    if ($post) {
        [$d, $err] = vt_ordine_leggi($_POST);
        if (!$err && vt_troppi()) $generale = 'Hai già inviato diverse richieste d\'ordine. Riprova tra un\'ora, oppure scrivici.';
        elseif (!$err && !moduli_captcha_valido((string)($_POST['captcha'] ?? ''))) $err['captcha'] = 'Spunta la casella "Sono una persona" e aspetta il segno verde.';
        if (!$err && $generale === '') {
            $r = vt_ordine_crea($d, $righe);
            if (isset($r['ordine'])) {
                vt_carrello_scrivi([]);
                try { vt_email_ordine($r['ordine']); } catch (Throwable $e) { error_log('vetrina email: ' . $e->getMessage()); }
                sito_vai(vt_ordine_url($r['ordine']), 303);
            }
            $generale = $r['errore'];
        }
        if (isset($err['robot'])) { $generale = $err['robot']; unset($err['robot']); }
    }
    $c = vt_carrello_calcola($righe, $d['consegna'] === 'ritiro' ? 'ritiro' : 'spedizione');
    if (!$c['righe']) vt_pagina(200, 'Richiesta d\'ordine', vt_cortesia('Il carrello è vuoto', 'Aggiungi almeno un prodotto per fare la richiesta d\'ordine.', '<p><a class="btn btn-viola" href="' . h(vt_base()) . '">Guarda i prodotti</a></p>'));
    $consegne = vt_consegne_attive(); $metodi = vt_metodi_attivi();
    if (!$consegne || !$metodi) vt_pagina(503, 'Richiesta d\'ordine', vt_cortesia('Gli ordini sono chiusi', 'Il negozio non accetta ordini online in questo momento. Scrivici per sapere come comprare.'));
    $s = vt_impostazioni();
    $e = fn(string $k) => isset($err[$k]) ? '<small class="vt-errore" id="err-' . $k . '">' . h($err[$k]) . '</small>' : '';
    $cl = fn(string $k) => isset($err[$k]) ? ' errato' : '';
    $campo = fn(string $nome, string $et, string $tipo, string $auto, int $max, bool $req = true) => '<label class="' . trim(($nome === 'indirizzo' ? 'form-largo ' : '') . $cl($nome)) . '" data-vt-campo="' . $nome . '"><span>' . h($et) . '</span><input name="' . $nome . '" type="' . $tipo . '" autocomplete="' . $auto . '" maxlength="' . $max . '" value="' . h((string)$d[$nome]) . '"' . ($req ? ' required' : '') . (isset($err[$nome]) ? ' aria-invalid="true" aria-describedby="err-' . $nome . '"' : '') . '>' . $e($nome) . '</label>';
    $f = '<form class="modulo vt-ordine" method="post" action="' . h(vt_base() . 'ordine/') . '" data-captcha novalidate>'
        . '<input type="hidden" name="modulo" value="vetrina-ordine"><input type="hidden" name="captcha" value="">'
        . ($generale !== '' || $err ? '<p class="modulo-errore" role="alert">' . h($generale !== '' ? $generale : 'Controlla i campi segnati in rosso.') . '</p>' : '')
        . '<h2>I tuoi dati</h2><div class="modulo-campi">'
        . $campo('nome', 'Nome e cognome', 'text', 'name', 120) . $campo('email', 'Email', 'email', 'email', 190) . $campo('telefono', 'Telefono', 'tel', 'tel', 40) . '</div>'
        . '<h2>Consegna</h2><fieldset class="vt-scelte' . $cl('consegna') . '"><legend class="vt-nascosto">Come ricevi l\'ordine</legend>';
    foreach ($consegne as $k => $et) {
        $costo = $k === 'ritiro' ? 'gratis' : ((int)$s['gratis_oltre_cent'] > 0 && $c['subtotale_cent'] >= (int)$s['gratis_oltre_cent'] ? 'gratis' : vt_euro((int)$s['spedizione_cent']));
        $f .= '<label class="vt-scelta"><input type="radio" name="consegna" value="' . h($k) . '"' . ($d['consegna'] === $k ? ' checked' : '') . ' data-vt-consegna> <span><b>' . h($et) . '</b> &middot; ' . h($costo) . '<small>' . h((string)($k === 'ritiro' ? $s['ritiro_testo'] : $s['spedizione_testo'])) . '</small></span></label>';
    }
    $f .= $e('consegna') . '</fieldset><div class="modulo-campi vt-indirizzo" data-vt-indirizzo>' . $campo('indirizzo', 'Via e numero civico', 'text', 'street-address', 200, false)
        . $campo('cap', 'CAP', 'text', 'postal-code', 10, false) . $campo('citta', 'Città', 'text', 'address-level2', 100, false) . '</div>'
        . '<h2>Pagamento</h2><fieldset class="vt-scelte' . $cl('metodo') . '"><legend class="vt-nascosto">Come paghi</legend>';
    $spiega = ['bonifico' => 'Ti mandiamo IBAN e causale con il numero d\'ordine.', 'consegna' => 'Paghi quando ricevi o ritiri l\'ordine.', 'link' => 'Dopo la richiesta trovi il pulsante per pagare online.'];
    foreach ($metodi as $k => $et) $f .= '<label class="vt-scelta"><input type="radio" name="metodo" value="' . h($k) . '"' . ($d['metodo'] === $k ? ' checked' : '') . '> <span><b>' . h($et) . '</b><small>' . h($spiega[$k]) . '</small></span></label>';
    $f .= $e('metodo') . '</fieldset>'
        . '<div class="modulo-campi"><label class="form-largo"><span>Note <small>(facoltative)</small></span><textarea name="note" rows="3" maxlength="2000">' . h((string)$d['note']) . '</textarea></label></div>'
        . '<div class="esca" aria-hidden="true"><label>Sito web <input name="sito_web" tabindex="-1" autocomplete="off"></label></div>'
        . '<label class="spunta' . $cl('consenso') . '"><input type="checkbox" name="consenso" value="1"' . ($d['consenso'] ? ' checked' : '') . ' required> <span>Ho letto le <a href="' . h(vt_base() . 'condizioni/') . '" target="_blank">condizioni di vendita</a> e l&#039;<a href="/privacy-policy/" target="_blank">informativa privacy</a>, e accetto che i miei dati servano per gestire l&#039;ordine.</span></label>' . $e('consenso')
        . '<label class="captcha' . $cl('captcha') . '"><input type="checkbox"> <span>Sono una persona</span> <span class="captcha-stato" aria-live="polite"></span></label>' . $e('captcha')
        . '<p class="vt-iva">Inviando la richiesta non paghi ancora niente: ti arriva un\'email con il riepilogo e le istruzioni per pagare.</p>'
        . '<button class="btn btn-viola" type="submit">Invia la richiesta d\'ordine</button></form>'
        . '<script src="' . h(SITO_ASSET . 'moduli.js') . '" defer></script>';
    // I totali per ogni consegna: lo script mostra quello della consegna scelta (senza script resta quello di partenza).
    $tot = '';
    foreach (array_keys($consegne) as $k) $tot .= '<div data-vt-totali="' . h($k) . '"' . ($k === ($d['consegna'] === 'ritiro' ? 'ritiro' : 'spedizione') ? '' : ' hidden') . '>' . vt_totali_html(vt_carrello_calcola($righe, $k), $k) . '</div>';
    $riep = '<aside class="vt-riepilogo"><h2>Il tuo ordine</h2>' . vt_carrello_tabella($c, false) . $tot
        . '<p><a href="' . h(vt_base() . 'carrello/') . '">Modifica il carrello</a></p></aside>';
    vt_pagina($post && ($err || $generale !== '') ? 422 : 200, 'Richiesta d\'ordine', '<h1>Richiesta d\'ordine</h1><div class="vt-cassa">' . $f . $riep . '</div>');
}

function vt_pagina_fatto(array $q): never {
    header('Cache-Control: no-store');
    $n = is_string($q['n'] ?? null) ? $q['n'] : ''; $k = is_string($q['k'] ?? null) ? $q['k'] : '';
    $o = preg_match('/^\d{4}-\d{4,6}$/', $n) ? vt_ordine_per_numero($n) : null;
    if (!$o || !hash_equals(vt_ordine_firma($o), $k)) vt_pagina(404, 'Ordine non trovato', vt_cortesia('Ordine non trovato', 'Il link non è completo. Usa il link che trovi nell\'email del tuo ordine.'));
    $corpo = '<div class="vt-fatto"><span class="occhiello">Ordine ' . h($o['numero']) . '</span><h1>' . ($o['stato'] === 'nuovo' ? 'Grazie, abbiamo ricevuto la tua richiesta' : 'Il tuo ordine: ' . h(mb_strtolower(VT_STATI[$o['stato']] ?? ''))) . '</h1>'
        . '<p>Ti abbiamo mandato un\'email a <b>' . h($o['email']) . '</b> con il riepilogo. Se non la trovi, guarda nella posta indesiderata.</p>'
        . ($o['stato'] === 'nuovo' ? '<div class="vt-paga">' . vt_istruzioni_pagamento($o) . '</div>' : '')
        . '<h2>Riepilogo</h2>' . vt_carrello_tabella(['righe' => array_map(fn($r) => $r + ['foto' => '', 'slug' => '', 'disponibile' => true, 'scorta' => null, 'indice' => 0], $o['righe'])], false)
        . '<dl class="vt-totali"><dt>Prodotti</dt><dd>' . h(vt_euro($o['subtotale_cent'])) . '</dd><dt>' . ($o['consegna'] === 'ritiro' ? 'Ritiro in negozio' : 'Spedizione') . '</dt><dd>' . ($o['spedizione_cent'] ? h(vt_euro($o['spedizione_cent'])) : 'gratis') . '</dd>'
        . '<dt class="vt-totale">Totale</dt><dd class="vt-totale">' . h(vt_euro($o['totale_cent'])) . '</dd></dl><p class="vt-iva">IVA inclusa (' . h(vt_euro($o['iva_cent'])) . ').</p>'
        . '<p><a class="btn btn-filo" href="' . h(vt_base()) . '">Torna al negozio</a></p></div>';
    vt_pagina(200, 'Ordine ' . $o['numero'], $corpo);
}

function vt_pagina_condizioni(): never {
    $s = vt_impostazioni();
    $testo = trim((string)$s['condizioni']);
    $corpo = '<div class="vt-condizioni"><span class="occhiello">' . h((string)$s['nome_negozio']) . '</span><h1>Condizioni di vendita</h1>'
        . implode('', array_map(fn($par) => '<p>' . nl2br(h(trim($par))) . '</p>', preg_split('/\n\s*\n/', $testo) ?: []))
        . vt_email_dati_attivita() . '</div>';
    vt_pagina(200, 'Condizioni di vendita', $corpo, ['robots' => 'index', 'canonical' => vt_url_assoluto(vt_base() . 'condizioni/')]);
}
