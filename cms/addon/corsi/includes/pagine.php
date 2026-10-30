<?php
/**
 * Corsi online: le pagine pubbliche (evento sito_percorso).
 *
 *   /corsi/                                   l'elenco dei corsi pubblicati
 *   /corsi/<corso>/                           il programma: moduli e lezioni, avanzamento, "Inizia" o "Riprendi da dove eri"
 *   /corsi/<corso>/<lezione>/                 la lezione (solo a chi può seguire il corso); POST: completata o no
 *   /corsi/<corso>/video/<id>/                il video caricato della lezione, a pezzi (Range), con lo stesso controllo
 *   /corsi/<corso>/materiale/<id>/<file>/     un materiale della lezione (file riservato di Download), con lo stesso controllo
 *   /corsi/<corso>/certificato/               il certificato del membro, da stampare (solo il suo, solo a corso finito)
 *   /certificato/                             la verifica pubblica: si scrive il codice
 *   /certificato/<codice>/                    il certificato con quel codice, per chi lo vuole controllare
 */
require_once __DIR__ . '/corsi.php';
require_once dirname(__DIR__, 2) . '/membri/includes/pagine.php';

function corsi_testa(): string {
    return membri_testa() . corsi_css();
}

function corsi_css(): string {
    return '<link rel="stylesheet" href="' . h(cms_modulo_url('corsi') . '/assets/corsi.css?v=' . (int)@filemtime(dirname(__DIR__) . '/assets/corsi.css')) . '">';
}

function corsi_html(string $titolo, string $corpo, int $codice = 200, string $descrizione = ''): never {
    header('Cache-Control: private, no-store');
    $meta = ['titolo' => $titolo . ' - ' . SITO_NOME, 'descrizione' => $descrizione, 'robots' => 'noindex', 'canonical' => '', 'og' => []];
    sito_html($codice, $meta, $corpo, corsi_testa());
}

/** L'anteprima dell'amministratore (?preview=1 con la sessione del pannello): vede anche le bozze, senza avanzamento. */
function corsi_anteprima(): bool {
    return defined('SITO_ANTEPRIMA') && SITO_ANTEPRIMA;
}

function corsi_barra(array $av): string {
    return '<div class="corsi-barra" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $av['percento'] . '" aria-label="Avanzamento del corso"><span style="width:' . $av['percento'] . '%"></span></div>'
        . '<p class="corsi-numeri">' . $av['fatte'] . ' lezioni fatte su ' . $av['totale'] . ' (' . $av['percento'] . '%)</p>';
}

function corsi_pagina(string $percorso, array $q): void {
    if (!str_ends_with($percorso, '/')) sito_vai($percorso . '/', 301);
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if ($post) membri_post_ok();
    if ($percorso === '/corsi/') corsi_pagina_elenco();
    if ($percorso === '/certificato/') {
        $c = is_string($q['codice'] ?? null) ? trim($q['codice']) : '';
        if ($c !== '') { $x = corsi_certificato_per_codice($c); if ($x) sito_vai('/certificato/' . $x['codice'] . '/', 303); }
        corsi_html('Verifica un certificato', membri_riquadro('Verifica un certificato', '<p>Scrivi il codice che sta in fondo al certificato (per esempio ABCD-EFGH-JKLM).</p>'
            . '<section class="modulo membri-modulo"><form method="get" action="/certificato/" id="corsi-verifica"><div class="modulo-campi membri-campi">' . membri_campo('codice', 'Codice del certificato', 'text', $c, 'off', 'required maxlength="20"') . '</div>'
            . '<p class="membri-azioni"><button class="btn btn-viola" type="submit">Verifica</button></p></form></section>', '', $c !== '' ? 'Nessun certificato con questo codice. Controlla di averlo scritto bene.' : '', 'Corsi'), $c !== '' ? 404 : 200);
    }
    if (preg_match('~^/certificato/([A-Za-z0-9-]{12,20})/$~', $percorso, $m)) {
        $x = corsi_certificato_per_codice($m[1]);
        if (!$x) sito_non_trovato();
        corsi_certificato_pagina($x, true);
    }
    if (!preg_match('~^/corsi/([a-z0-9][a-z0-9-]{0,119})/(.*)$~', $percorso, $m)) sito_non_trovato();
    $corso = corsi_per_slug($m[1]);
    if (!$corso || ($corso['stato'] !== 'pubblicato' && !corsi_anteprima())) sito_non_trovato();
    $resto = $m[2];
    if ($resto === '') corsi_pagina_corso($corso);
    if ($resto === 'certificato/') corsi_pagina_certificato($corso);
    if (preg_match('~^video/(\d{1,10})/$~', $resto, $r)) corsi_pagina_video($corso, (int)$r[1]);
    if (preg_match('~^materiale/(\d{1,10})/([a-z0-9][a-z0-9._-]{0,120})/$~', $resto, $r)) corsi_pagina_materiale($corso, (int)$r[1], $r[2]);
    if (preg_match('~^([a-z0-9][a-z0-9-]{0,119})/$~', $resto, $r)) corsi_pagina_lezione($corso, $r[1], $post);
    sito_non_trovato();
}

// ------------------------------------------------------------ Elenco

function corsi_pagina_elenco(): never {
    $m = membri_corrente();
    $schede = '';
    foreach (corsi_tutti(true) as $c) $schede .= corsi_scheda($c, $m);
    $corpo = '<div class="wrap corsi-pagina"><header class="corsi-testa"><span class="occhiello">Corsi</span><h1>I corsi</h1>'
        . (!$m ? '<p>Per seguire un corso entra nell\'area riservata: <a href="/accedi/?torna=%2Fcorsi%2F">accedi</a>' . (membri_cfg()['modo'] !== 'invito' ? ' oppure <a href="/iscriviti/">iscriviti</a>' : '') . '.</p>' : '')
        . '</header>' . ($schede !== '' ? '<div class="corsi-griglia">' . $schede . '</div>' : '<p>Ancora nessun corso. Torna a trovarci.</p>') . '</div>';
    corsi_html('Corsi', $corpo);
}

/** La scheda di un corso (elenco e widget): immagine, titolo, descrizione, lezioni, avanzamento per chi è dentro. */
function corsi_scheda(array $c, ?array $m, bool $descrizione = true, string $pulsante = ''): string {
    $lezioni = corsi_lezioni((int)$c['id']);
    $av = $m && corsi_puo($c, $m) ? corsi_avanzamento((int)$m['id'], $c) : null;
    $img = $c['immagine'] !== '' ? sicurezza_url_pulito((string)$c['immagine'], true) : '';
    $testo = $pulsante !== '' ? $pulsante : ($av ? ($av['completato'] ? 'Rivedi il corso' : ($av['iniziato'] ? 'Riprendi' : 'Inizia')) : 'Scopri il corso');
    return '<article class="corsi-scheda">' . ($img !== '' ? '<img src="' . h($img) . '" alt="" loading="lazy">' : '')
        . '<div class="corsi-scheda-corpo"><h2><a href="/corsi/' . h($c['slug']) . '/">' . h($c['titolo']) . '</a></h2>'
        . ($descrizione && $c['descrizione'] !== '' ? '<p>' . h(mb_strimwidth((string)$c['descrizione'], 0, 220, '…')) . '</p>' : '')
        . '<p class="corsi-numeri">' . count($lezioni) . (count($lezioni) === 1 ? ' lezione' : ' lezioni') . ($c['accesso'] === 'assegnati' ? ' &middot; su iscrizione' : '') . '</p>'
        . ($av ? corsi_barra($av) : '')
        . '<p><a class="btn btn-viola" href="/corsi/' . h($c['slug']) . '/">' . h($testo) . '</a></p></div></article>';
}

// ------------------------------------------------------------ Corso

function corsi_pagina_corso(array $corso): never {
    $m = membri_corrente();
    $puo = corsi_puo($corso, $m) || corsi_anteprima();
    $av = $m && corsi_puo($corso, $m) ? corsi_avanzamento((int)$m['id'], $corso) : null;
    $fatte = $av ? array_flip(corsi_completate((int)$m['id'], (int)$corso['id'])) : [];
    $prog = '';
    foreach (corsi_moduli((int)$corso['id']) as $mod) {
        $voci = '';
        $st = db()->prepare('SELECT * FROM ' . tbl('corsi_lezioni') . ' WHERE modulo_id = ? ORDER BY ordine, id'); $st->execute([(int)$mod['id']]);
        foreach ($st->fetchAll() as $l) {
            $f = isset($fatte[(int)$l['id']]);
            $nome = h($l['titolo']) . ($l['durata'] !== '' ? ' <small>' . h($l['durata']) . '</small>' : '');
            $voci .= '<li class="' . ($f ? 'fatta' : '') . '"><span class="corsi-segno" aria-hidden="true">' . ($f ? '&#10003;' : '&#9675;') . '</span>'
                . ($puo ? '<a href="/corsi/' . h($corso['slug']) . '/' . h($l['slug']) . '/' . (corsi_anteprima() ? '?preview=1' : '') . '">' . $nome . '</a>' : '<span>' . $nome . '</span>')
                . ($f ? '<span class="visually-hidden"> (fatta)</span>' : '') . '</li>';
        }
        if ($voci !== '') $prog .= '<section class="corsi-modulo"><h2>' . h($mod['titolo']) . '</h2><ol class="corsi-lezioni">' . $voci . '</ol></section>';
    }
    if ($av) {
        $cert = $av['completato'] ? corsi_certificato_di((int)$m['id'], (int)$corso['id']) : null;
        $azione = $av['completato']
            ? '<p class="corsi-finito">Hai completato il corso.</p><p class="membri-azioni">' . ($cert ? '<a class="btn btn-viola" href="/corsi/' . h($corso['slug']) . '/certificato/">Il tuo certificato</a>' : '') . '</p>'
            : ($av['prossima'] ? '<p class="membri-azioni"><a class="btn btn-viola corsi-riprendi" href="/corsi/' . h($corso['slug']) . '/' . h($av['prossima']['slug']) . '/">' . ($av['iniziato'] ? 'Riprendi da dove eri: ' : 'Inizia: ') . h($av['prossima']['titolo']) . '</a></p>' : '');
        $stato = corsi_barra($av) . $azione;
    } elseif (corsi_anteprima()) {
        $stato = '<p class="membri-msg membri-ok">Anteprima dell\'amministratore: vedi le lezioni, ma l\'avanzamento è solo dei membri.</p>';
    } elseif (!$m) {
        $stato = '<div class="membri-chiuso"><p>Per seguire il corso entra nell\'area riservata.</p><p class="membri-azioni"><a class="btn btn-viola" href="/accedi/?torna=' . h(rawurlencode('/corsi/' . $corso['slug'] . '/')) . '">Accedi</a>'
            . (membri_cfg()['modo'] !== 'invito' ? ' <a class="btn btn-filo" href="/iscriviti/">Iscriviti</a>' : '') . '</p></div>';
    } else {
        $stato = '<div class="membri-chiuso" data-corsi-chiuso><p>Questo corso è riservato a chi è iscritto. Per avere l\'accesso scrivi al sito: te lo apriamo noi.</p></div>';
    }
    $img = $corso['immagine'] !== '' ? sicurezza_url_pulito((string)$corso['immagine'], true) : '';
    $corpo = '<div class="wrap corsi-pagina"><p class="corsi-briciole"><a href="/corsi/">Corsi</a></p><header class="corsi-testa"><span class="occhiello">Corso</span><h1>' . h($corso['titolo']) . '</h1>'
        . ($corso['descrizione'] !== '' ? '<p class="corsi-descrizione">' . nl2br(h((string)$corso['descrizione'])) . '</p>' : '') . '</header>'
        . ($img !== '' ? '<img class="corsi-copertina" src="' . h($img) . '" alt="">' : '')
        . '<div class="corsi-stato">' . $stato . '</div><div class="corsi-programma"><h2 class="visually-hidden">Programma</h2>' . ($prog ?: '<p>Le lezioni arrivano presto.</p>') . '</div></div>';
    corsi_html((string)$corso['titolo'], $corpo, 200, mb_strimwidth((string)$corso['descrizione'], 0, 160, '…'));
}

/** Chi apre una lezione, un video o un materiale deve poter seguire il corso. Ritorna il membro (null in anteprima). */
function corsi_richiedi(array $corso, string $torna): ?array {
    if (corsi_anteprima()) return null;
    $m = membri_richiedi($torna);
    if (corsi_puo($corso, $m)) return $m;
    corsi_html('Corso riservato', membri_riquadro('Questo corso non è fra i tuoi', '<p>Il corso <b>' . h($corso['titolo']) . '</b> è riservato a chi è iscritto. Per avere l\'accesso scrivi al sito.</p><p><a class="btn btn-filo" href="/area-riservata/">Torna alla tua area riservata</a></p>', '', '', 'Corsi'), 403);
}

function corsi_lezione_per_slug(int $corso, string $slug): ?array {
    $st = db()->prepare('SELECT * FROM ' . tbl('corsi_lezioni') . ' WHERE corso_id = ? AND slug = ?'); $st->execute([$corso, $slug]);
    return $st->fetch() ?: null;
}

// ------------------------------------------------------------ Lezione

function corsi_pagina_lezione(array $corso, string $slug, bool $post): never {
    $l = corsi_lezione_per_slug((int)$corso['id'], $slug);
    if (!$l) sito_non_trovato();
    $url = '/corsi/' . $corso['slug'] . '/' . $l['slug'] . '/';
    $m = corsi_richiedi($corso, $url);
    if ($post) {
        if (!$m) sito_vai($url, 303);
        $si = membri_post('azione', 20) === 'completa';
        corsi_completa($m, $l, $si);
        $av = corsi_avanzamento((int)$m['id'], $corso);
        // Fatta: si va alla prossima lezione (se c'è); finito il corso, alla pagina del corso con il certificato.
        if ($si && $av['completato']) sito_vai('/corsi/' . $corso['slug'] . '/?finito=1', 303);
        if ($si && $av['prossima']) sito_vai('/corsi/' . $corso['slug'] . '/' . $av['prossima']['slug'] . '/', 303);
        sito_vai($url, 303);
    }
    if ($m) corsi_segna_visita((int)$m['id'], $l);
    $lezioni = corsi_lezioni((int)$corso['id']);
    $ids = array_map(fn($x) => (int)$x['id'], $lezioni);
    $i = (int)array_search((int)$l['id'], $ids, true);
    $prima = $lezioni[$i - 1] ?? null; $dopo = $lezioni[$i + 1] ?? null;
    $fatta = $m && in_array((int)$l['id'], corsi_completate((int)$m['id'], (int)$corso['id']), true);
    $mod = corsi_modulo((int)$l['modulo_id']);
    $video = '';
    if ($l['video_file'] !== '' && is_file(corsi_video_dir() . '/' . basename((string)$l['video_file']))) {
        $video = '<div class="corsi-video"><video controls preload="metadata" controlslist="nodownload" src="/corsi/' . h($corso['slug']) . '/video/' . (int)$l['id'] . '/' . (corsi_anteprima() ? '?preview=1' : '') . '"></video></div>';
    } elseif ($l['video_url'] !== '' && ($e = corsi_video_embed((string)$l['video_url']))) {
        $video = '<div class="corsi-video corsi-video-esterno"><iframe src="' . h($e) . '" title="' . h($l['titolo']) . '" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
    }
    $mat = '';
    $tutti = scaricabili_elenco();
    foreach (corsi_materiali($l) as $n) {
        $x = $tutti[$n] ?? null;
        if (!$x || !$x['riservato']) continue;
        $mat .= '<li><a class="corsi-materiale" href="/corsi/' . h($corso['slug']) . '/materiale/' . (int)$l['id'] . '/' . h($n) . '/"><b>' . h($x['titolo']) . '</b> <small>' . h($x['tipo'] . ', ' . scaricabili_peso($x['peso'])) . '</small></a></li>';
    }
    $azioni = $m ? '<form method="post" action="' . h($url) . '" class="corsi-completa">' . membri_csrf_campo()
        . ($fatta ? '<input type="hidden" name="azione" value="da-fare"><p class="corsi-finito">Lezione fatta.</p><button class="btn btn-filo" type="submit">Segna come da fare</button>'
                  : '<input type="hidden" name="azione" value="completa"><button class="btn btn-viola" type="submit">Segna come completata' . ($dopo ? ' e vai avanti' : '') . '</button>')
        . '</form>' : '';
    $nav = '<nav class="corsi-nav" aria-label="Lezioni">' . ($prima ? '<a href="/corsi/' . h($corso['slug']) . '/' . h($prima['slug']) . '/">&larr; ' . h($prima['titolo']) . '</a>' : '<span></span>')
        . ($dopo ? '<a href="/corsi/' . h($corso['slug']) . '/' . h($dopo['slug']) . '/">' . h($dopo['titolo']) . ' &rarr;</a>' : '') . '</nav>';
    $av = $m ? corsi_avanzamento((int)$m['id'], $corso) : null;
    $corpo = '<div class="wrap corsi-pagina corsi-lezione"><p class="corsi-briciole"><a href="/corsi/">Corsi</a> &rsaquo; <a href="/corsi/' . h($corso['slug']) . '/">' . h($corso['titolo']) . '</a></p>'
        . '<header class="corsi-testa"><span class="occhiello">' . h((string)($mod['titolo'] ?? '')) . ' &middot; lezione ' . ($i + 1) . ' di ' . count($lezioni) . '</span><h1>' . h($l['titolo']) . '</h1>'
        . ($l['durata'] !== '' ? '<p class="corsi-numeri">' . h($l['durata']) . '</p>' : '') . '</header>'
        . $video . ($l['testo'] !== '' ? '<div class="corpo corsi-testo">' . $l['testo'] . '</div>' : '')
        . ($mat !== '' ? '<section class="corsi-materiali"><h2>Materiali</h2><ul class="membri-lista">' . $mat . '</ul></section>' : '')
        . $azioni . ($av ? corsi_barra($av) : '') . $nav . '</div>';
    corsi_html($l['titolo'] . ' - ' . $corso['titolo'], $corpo);
}

function corsi_pagina_video(array $corso, int $id): never {
    $l = corsi_lezione($id);
    if (!$l || (int)$l['corso_id'] !== (int)$corso['id'] || $l['video_file'] === '') sito_non_trovato();
    corsi_richiedi($corso, '/corsi/' . $corso['slug'] . '/');
    $p = corsi_video_dir() . '/' . basename((string)$l['video_file']);
    if (!is_file($p)) sito_non_trovato();
    corsi_manda_video($p);
}

function corsi_pagina_materiale(array $corso, int $id, string $nome): never {
    $l = corsi_lezione($id);
    if (!$l || (int)$l['corso_id'] !== (int)$corso['id'] || !in_array($nome, corsi_materiali($l), true)) sito_non_trovato();
    corsi_richiedi($corso, '/corsi/' . $corso['slug'] . '/' . $l['slug'] . '/');
    $x = scaricabili_elenco()[$nome] ?? null;
    if (!$x || !$x['riservato'] || !is_file($x['percorso'])) sito_non_trovato();
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

// ------------------------------------------------------------ Certificato

function corsi_pagina_certificato(array $corso): never {
    $m = corsi_richiedi($corso, '/corsi/' . $corso['slug'] . '/certificato/');
    $c = $m ? corsi_certificato_di((int)$m['id'], (int)$corso['id']) : null;
    if (!$c) corsi_html('Certificato', membri_riquadro('Il certificato arriva alla fine', '<p>Il certificato di <b>' . h($corso['titolo']) . '</b> c\'è quando hai completato tutte le lezioni.</p><p><a class="btn btn-viola" href="/corsi/' . h($corso['slug']) . '/">Torna al corso</a></p>', '', '', 'Corsi'));
    corsi_certificato_pagina($c, false);
}

/** Il certificato, una pagina a sé da stampare: nome, corso, data, codice e indirizzo per verificarlo. */
function corsi_certificato_pagina(array $c, bool $verifica): never {
    $mesi = ['', 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
    $t = strtotime((string)$c['data']);
    $data = (int)date('j', $t) . ' ' . $mesi[(int)date('n', $t)] . ' ' . date('Y', $t);
    $url = rtrim(cms_url_sito(), '/') . '/certificato/' . $c['codice'] . '/';
    $u = cms_modulo_url('corsi') . '/assets/';
    $d = dirname(__DIR__) . '/assets/';
    $html = '<!DOCTYPE html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>Certificato: ' . h($c['corso_titolo']) . ' - ' . h(SITO_NOME) . '</title>'
        . '<link rel="stylesheet" href="' . h($u . 'certificato.css?v=' . (int)@filemtime($d . 'certificato.css')) . '">'
        . '<script src="' . h($u . 'corsi.js?v=' . (int)@filemtime($d . 'corsi.js')) . '" defer></script></head><body>'
        . ($verifica ? '<p class="cert-verifica" role="status">&#10003; Certificato valido: è stato rilasciato da ' . h(SITO_NOME) . ' a questa persona.</p>' : '')
        . '<main class="cert"><div class="cert-cornice">'
        . '<p class="cert-sito">' . h(SITO_NOME) . '</p><h1>Certificato di completamento</h1>'
        . '<p class="cert-frase">Si certifica che</p><p class="cert-nome" id="cert-nome">' . h($c['nome']) . '</p>'
        . '<p class="cert-frase">ha completato il corso</p><p class="cert-corso" id="cert-corso">' . h($c['corso_titolo']) . '</p>'
        . '<p class="cert-data">il ' . h($data) . '</p>'
        . '<p class="cert-codice">Codice di verifica: <b id="cert-codice">' . h($c['codice']) . '</b><br><span>' . h($url) . '</span></p>'
        . '</div></main>'
        . '<p class="cert-azioni"><button type="button" data-corsi-stampa>Stampa o salva in PDF</button> ' . (!$verifica ? '<a href="' . h(cms_sito_base()) . '/area-riservata/">Torna all\'area riservata</a>' : '<a href="' . h(cms_sito_base()) . '/">Vai al sito</a>') . '</p>'
        . '</body></html>';
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex');
    sito_rispondi(200, 'text/html', $html);
}

// ------------------------------------------------------------ Area riservata

/** La sezione "I miei corsi" dell'area riservata: i corsi che il membro può seguire, con l'avanzamento. */
function corsi_sezione_area(array $m): ?array {
    $schede = '';
    foreach (corsi_tutti(true) as $c) if (corsi_puo($c, $m)) $schede .= corsi_scheda($c, $m, false);
    if ($schede === '') return null;
    return ['I miei corsi', '<div class="corsi-griglia corsi-griglia-area">' . $schede . '</div><p><a href="/corsi/">Tutti i corsi</a></p>' . corsi_css()];
}
