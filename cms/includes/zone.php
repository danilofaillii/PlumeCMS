<?php
/**
 * 2.28.0: le pagine per zona ("Idraulico a Casalecchio di Reno"), la seconda fase della SEO geografica (richiesta di Danilo:
 * "le pagine per zona, già in programma: è dove la SEO locale si vince").
 *
 * Come funziona. Dal pannello (SEO e AEO > Zone, solo amministratore) si sceglie un servizio, le zone (una per riga, precompilate
 * dall'unione delle zone servite delle sedi) e la sede di riferimento; "Crea le bozze" fa nascere una pagina vera dell'editor per
 * ogni zona, in BOZZA, mai pubblicata da qui. Ogni bozza ha l'apertura (occhiello = la zona, titolo "%servizio% a %zona%"), tre
 * riquadri guidati (tre widget Testo con un segnaposto [DA SCRIVERE] ciascuno e una riga di aiuto: cosa fai in quella zona, un
 * caso vero, come si arriva), il widget Altre zone, Dati dell'attività e la Barra contatti letti dalla sede. Il blocco guidato
 * esiste per impedire pagine vuote tutte uguali: finché un segnaposto resta, la pagina NON si pubblica da nessuna strada
 * (editor, scheda del contenuto, azione in blocco, impostazioni dell'editor, ripristino di una versione): zone_blocco_pubblicazione().
 *
 * Il registro delle pagine per zona sta nel gruppo "zone" della configurazione SEO (impostazione seo_config, includes/seo.php):
 * niente tabelle nuove. Ogni voce: pagina (id della tabella pages), servizio, zona, sede (id, "principale" o vuoto),
 * servizio_pagina ("pagine:12", la pagina del servizio a cui collegare le briciole, facoltativa), creata. Le pagine sparite si
 * ignorano alla lettura e il pannello le toglie dal registro.
 *
 * Dati per Google (includes/sito-seo.php): nella pagina per zona un nodo Service con name "%servizio% a %zona%", areaServed la
 * zona (City o AdministrativeArea, la stessa regola delle sedi), provider la LocalBusiness della sede di riferimento (o
 * l'Organizzazione), mainEntity della WebPage e le briciole Home > pagina del servizio > zona. Senza pagine per zona il sito esce
 * identico a prima, al byte.
 */

require_once __DIR__ . '/sedi.php';

/** Quante pagine per zona in tutto. */
const ZONE_MAX = 200;
/** Quante zone in un giro di "Crea le bozze". */
const ZONE_GIRO_MAX = 30;
/** Il segnaposto dei riquadri guidati: finché c'è, la pagina resta bozza. */
const ZONE_SEGNAPOSTO = '[DA SCRIVERE]';
const ZONE_TITOLO_PREDEFINITO = '%servizio% a %zona%';
const ZONE_DESCRIZIONE_PREDEFINITA = '%servizio% a %zona%: interventi, tempi e contatti della sede più vicina. Scrivici o chiama per un preventivo.';

// ------------------------------------------------------------
// Lettura e scrittura del registro
// ------------------------------------------------------------

/** Il gruppo "zone" della configurazione SEO al valore di partenza (seo_predefiniti() lo usa). */
function zone_predefiniti(): array {
    return ['elenco' => [], 'titolo' => ZONE_TITOLO_PREDEFINITO, 'descrizione' => ZONE_DESCRIZIONE_PREDEFINITA];
}

function zone_vuota(): array {
    return ['pagina' => 0, 'servizio' => '', 'zona' => '', 'sede' => '', 'servizio_pagina' => '', 'creata' => ''];
}

/** Una voce del registro letta dal JSON, con ogni campo pulito. */
function zone_normalizza(array $z): array {
    $v = zone_vuota();
    $v['pagina'] = max(0, (int)($z['pagina'] ?? 0));
    $v['servizio'] = sedi_testo($z['servizio'] ?? '', 80);
    $v['zona'] = sedi_testo($z['zona'] ?? '', 80);
    $sede = (string)($z['sede'] ?? '');
    $v['sede'] = $sede === 'principale' || preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', $sede) ? $sede : '';
    $rif = (string)($z['servizio_pagina'] ?? '');
    $v['servizio_pagina'] = preg_match('/^[a-z0-9_]{1,40}:\d{1,10}$/', $rif) ? $rif : '';
    $v['creata'] = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)($z['creata'] ?? '')) ? (string)$z['creata'] : '';
    return $v;
}

/**
 * Tutte le pagine per zona, per id della pagina, con la riga della tabella pages in 'riga' (id, title, slug, status, cestino,
 * publish_at). Le voci la cui pagina non esiste più si ignorano (e zone_pulisci() le toglie dal registro). Una lettura per richiesta.
 */
function zone_tutte(bool $azzera = false): array {
    static $cache = null;
    if ($cache !== null && !$azzera) return $cache;
    $elenco = seo_cfg($azzera)['zone']['elenco'] ?? [];
    $voci = [];
    foreach (is_array($elenco) ? $elenco : [] as $z) {
        if (!is_array($z)) continue;
        $n = zone_normalizza($z);
        if ($n['pagina'] <= 0 || $n['servizio'] === '' || $n['zona'] === '' || isset($voci[$n['pagina']])) continue;
        $voci[$n['pagina']] = $n;
        if (count($voci) >= ZONE_MAX) break;
    }
    $righe = [];
    if ($voci) {
        $in = implode(',', array_map('intval', array_keys($voci)));
        foreach (db()->query('SELECT id, title, slug, status, cestino, publish_at FROM ' . tbl('pages') . " WHERE id IN ($in)")->fetchAll() as $r) $righe[(int)$r['id']] = $r;
    }
    $out = [];
    foreach ($voci as $id => $z) {
        if (!isset($righe[$id])) continue; // la pagina non c'è più: il registro la ignora
        $z['riga'] = $righe[$id];
        $out[$id] = $z;
    }
    return $cache = $out;
}

/** Scrive il registro (senza le righe delle pagine). Le emoji diventano entità: la tabella delle impostazioni è utf8 a 3 byte. */
function zone_salva_elenco(array $elenco): void {
    $pulito = [];
    foreach ($elenco as $z) { $n = zone_normalizza(is_array($z) ? $z : []); if ($n['pagina'] > 0) $pulito[] = $n; }
    array_walk_recursive($pulito, function (&$x) { if (is_string($x)) $x = sito_senza_4byte($x); });
    seo_salva('zone', ['elenco' => array_slice($pulito, 0, ZONE_MAX)]);
    zone_tutte(true);
}

/** Toglie dal registro le voci la cui pagina è sparita. Ritorna quante. Si chiama dal pannello, mai mentre il sito disegna una pagina. */
function zone_pulisci(): int {
    $salvate = seo_cfg()['zone']['elenco'] ?? [];
    $salvate = is_array($salvate) ? array_filter($salvate, fn($z) => is_array($z) && (int)($z['pagina'] ?? 0) > 0) : [];
    $vive = zone_tutte(true);
    $tolte = count($salvate) - count($vive);
    if ($tolte > 0) zone_salva_elenco(array_values($vive));
    return max(0, $tolte);
}

/** Aggiunge (o aggiorna) una voce del registro. */
function zone_registra(array $z): void {
    $z = zone_normalizza($z);
    if ($z['pagina'] <= 0) throw new RuntimeException('La pagina per zona non ha un id.');
    $tutte = zone_tutte();
    if (!isset($tutte[$z['pagina']]) && count($tutte) >= ZONE_MAX) throw new RuntimeException('Si possono tenere al massimo ' . ZONE_MAX . ' pagine per zona.');
    if ($z['creata'] === '') $z['creata'] = date('Y-m-d H:i:s');
    $tutte[$z['pagina']] = $z;
    zone_salva_elenco(array_values($tutte));
}

/** Toglie una pagina dal registro: la pagina resta nel sito (lo dice il pannello). */
function zone_elimina(int $pagina): bool {
    $tutte = zone_tutte();
    if (!isset($tutte[$pagina])) return false;
    unset($tutte[$pagina]);
    zone_salva_elenco(array_values($tutte));
    return true;
}

/** La voce del registro di questa pagina (id della tabella pages), oppure null. */
function zone_di_pagina(int $pagina): ?array {
    return $pagina > 0 ? (zone_tutte()[$pagina] ?? null) : null;
}

/** La voce del registro della pagina a questo percorso ("/idraulico-a-bologna/"), oppure null. */
function zone_di_percorso(string $percorso): ?array {
    $slug = trim($percorso, '/');
    if ($slug === '' || str_contains($slug, '/')) return null;
    foreach (zone_tutte() as $z) if ($z['riga']['slug'] === $slug) return $z;
    return null;
}

/** I modelli del titolo e della descrizione salvati l'ultima volta (quelli di partenza se mai cambiati). */
function zone_modelli(): array {
    $c = seo_cfg()['zone'] ?? [];
    $t = sedi_testo($c['titolo'] ?? '', 120); $d = sedi_testo($c['descrizione'] ?? '', 300);
    return ['titolo' => $t !== '' ? $t : ZONE_TITOLO_PREDEFINITO, 'descrizione' => $d !== '' ? $d : ZONE_DESCRIZIONE_PREDEFINITA];
}

function zone_modelli_salva(string $titolo, string $descrizione): void {
    seo_salva('zone', ['titolo' => sedi_testo($titolo, 120), 'descrizione' => sedi_testo($descrizione, 300)]);
    zone_tutte(true);
}

/** "%servizio% a %zona%" con i valori dentro (anche %Servizio% e %Zona%). */
function zone_componi(string $modello, string $servizio, string $zona): string {
    return trim(str_ireplace(['%servizio%', '%zona%'], [$servizio, $zona], $modello));
}

/** La voce che ha già questa coppia servizio + zona (senza badare a maiuscole e spazi), oppure null. */
function zone_esiste(string $servizio, string $zona): ?array {
    $k = fn(string $s) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s) ?? ''));
    foreach (zone_tutte() as $z) if ($k($z['servizio']) === $k($servizio) && $k($z['zona']) === $k($zona)) return $z;
    return null;
}

/** I servizi che hanno pagine per zona, senza doppioni, in ordine alfabetico. */
function zone_servizi(): array {
    $out = [];
    foreach (zone_tutte() as $z) { $k = mb_strtolower($z['servizio']); if (!isset($out[$k])) $out[$k] = $z['servizio']; }
    ksort($out);
    return array_values($out);
}

/** Lo stato della pagina di una voce: bozza, programmato, pubblicato, cestino. */
function zone_stato(array $z): string {
    return contenuti_stato('pagine', $z['riga']);
}

/** La pagina di una voce è visibile sul sito adesso? */
function zone_pubblicata(array $z): bool {
    return zone_stato($z) === 'pubblicato';
}

/** L'indirizzo assoluto della pagina di una voce. */
function zone_url(array $z): string {
    return SITO_URL . contenuti_percorso('pagine', $z['riga']);
}

// ------------------------------------------------------------
// La pagina del servizio (la tendina del pannello e le briciole)
// ------------------------------------------------------------

/**
 * Le pagine a cui collegare le pagine per zona: le pagine pubblicate del sito e, se il sito ha un tipo di contenuto "servizi"
 * (un modulo), anche quelli. Chiave "pagine:12" (o "servizi:3") => "Titolo (/indirizzo/)".
 */
function zone_pagine_sito(): array {
    $out = [];
    $home = (string)get_setting('pagina_home', '');
    foreach (db()->query('SELECT id, title, slug, status, cestino FROM ' . tbl('pages') . ' ORDER BY title')->fetchAll() as $r) {
        if (!empty($r['cestino']) || $r['slug'] === $home) continue;
        $out['pagine:' . (int)$r['id']] = $r['title'] . ' (/' . $r['slug'] . '/' . ($r['status'] === 'published' ? '' : ', bozza') . ')';
    }
    if (isset(contenuti_tipi()['servizi'])) {
        $t = contenuti_tipo('servizi');
        try {
            foreach (db()->query('SELECT id, title, slug, status, cestino FROM ' . tbl($t['tabella']) . ' ORDER BY title')->fetchAll() as $r) {
                if (!empty($r['cestino'])) continue;
                $out['servizi:' . (int)$r['id']] = $r['title'] . ' (' . $t['uno'] . ', /' . $r['slug'] . '/' . ($r['status'] === 'published' ? '' : ', bozza') . ')';
            }
        } catch (Throwable $e) { error_log('zone_pagine_sito: ' . $e->getMessage()); }
    }
    return $out;
}

/** La pagina del servizio di una voce ("pagine:12"): ['titolo', 'percorso', 'pubblicata'], oppure null se non c'è (più). */
function zone_servizio_pagina(string $rif): ?array {
    if (!preg_match('/^([a-z0-9_]{1,40}):(\d{1,10})$/', $rif, $m) || !isset(contenuti_tipi()[$m[1]])) return null;
    $r = contenuti_leggi($m[1], (int)$m[2]);
    if (!$r || !empty($r['cestino'])) return null;
    return ['titolo' => (string)$r['title'], 'percorso' => contenuti_percorso($m[1], $r), 'pubblicata' => contenuti_stato($m[1], $r) === 'pubblicato'];
}

// ------------------------------------------------------------
// Il generatore: controllo dei campi e creazione delle bozze
// ------------------------------------------------------------

/**
 * Controlla i campi arrivati dal pannello. Ritorna ['valori' => [servizio, zone, sede, servizio_pagina, titolo, descrizione],
 * 'errori' => [campo => messaggio]].
 */
function zone_valida(array $in): array {
    $errori = [];
    $v = ['servizio' => sedi_testo($in['servizio'] ?? '', 80), 'zone' => [], 'sede' => '', 'servizio_pagina' => '',
        'titolo' => sedi_testo($in['titolo'] ?? '', 120), 'descrizione' => sedi_testo($in['descrizione'] ?? '', 300)];
    if (mb_strlen($v['servizio']) < 2) $errori['servizio'] = 'Scrivi il servizio, per esempio "Idraulico" o "Pulizie di uffici".';
    $righeScritte = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($in['zone'] ?? '')))));
    $zone = [];
    foreach ($righeScritte as $r) { $r = sedi_testo($r, 80); if ($r !== '' && !in_array(mb_strtolower($r), array_map('mb_strtolower', $zone), true)) $zone[] = $r; }
    if (!$zone) $errori['zone'] = 'Scrivi almeno una zona, una per riga: per esempio "Bologna", "Casalecchio di Reno", "Provincia di Bologna".';
    elseif (count($zone) > ZONE_GIRO_MAX) $errori['zone'] = 'Al massimo ' . ZONE_GIRO_MAX . ' zone per giro: ne hai scritte ' . count($zone) . '. Togline qualcuna e rifai un altro giro per le altre.';
    $v['zone'] = $zone;
    $sedi = sedi_tutte();
    $sede = (string)($in['sede'] ?? '');
    if (count($sedi) === 1) $v['sede'] = array_key_first($sedi);
    elseif (!$sedi) $v['sede'] = '';
    elseif ($sede === 'principale' || isset($sedi[$sede])) $v['sede'] = $sede;
    else $errori['sede'] = 'Scegli la sede di riferimento dall\'elenco.';
    $rif = (string)($in['servizio_pagina'] ?? '');
    if ($rif !== '') {
        if (!preg_match('/^[a-z0-9_]{1,40}:\d{1,10}$/', $rif) || !zone_servizio_pagina($rif)) $errori['servizio_pagina'] = 'La pagina del servizio scelta non c\'è più: scegline un\'altra o lascia "Nessuna".';
        else $v['servizio_pagina'] = $rif;
    }
    if ($v['titolo'] === '') $v['titolo'] = ZONE_TITOLO_PREDEFINITO;
    if (stripos($v['titolo'], '%zona%') === false || stripos($v['titolo'], '%servizio%') === false) $errori['titolo'] = 'Il modello del titolo deve contenere %servizio% e %zona%, per esempio "%servizio% a %zona%": senza, le pagine avrebbero tutte lo stesso titolo.';
    if ($v['descrizione'] === '') $v['descrizione'] = ZONE_DESCRIZIONE_PREDEFINITA;
    return ['valori' => $v, 'errori' => $errori];
}

/** Il testo dei tre riquadri guidati di una zona: titolo, segnaposto e riga di aiuto. Tutto come testo (htmlspecialchars). */
function zone_riquadri_guidati(string $zona): array {
    $z = htmlspecialchars($zona, ENT_QUOTES, 'UTF-8');
    $seg = htmlspecialchars(ZONE_SEGNAPOSTO, ENT_QUOTES, 'UTF-8');
    return [
        '<h3>Cosa facciamo a ' . $z . '</h3><p>' . $seg . ' Cosa fai a ' . $z . ': ...</p><p><em>Aiuto per chi scrive: due o tre frasi concrete su quali interventi fai qui, per chi e in quanto tempo. Scrivi il testo al posto del segnaposto e cancella questa riga.</em></p>',
        '<h3>Un caso vero a ' . $z . '</h3><p>' . $seg . ' Un caso vero a ' . $z . ': ...</p><p><em>Aiuto per chi scrive: un lavoro fatto davvero in questa zona: il problema, cosa avete fatto, come &egrave; finita (senza nomi, se il cliente non vuole). Poi cancella questa riga.</em></p>',
        '<h3>Come si arriva a ' . $z . '</h3><p>' . $seg . ' Come si arriva a ' . $z . ': ...</p><p><em>Aiuto per chi scrive: da quale sede partite, quanto ci mettete, in quali giorni e orari coprite la zona. Poi cancella questa riga.</em></p>',
    ];
}

/**
 * L'albero dell'editor di una pagina per zona: apertura, i tre riquadri guidati, Altre zone, Dati dell'attività e Barra
 * contatti dalla sede (gli ultimi due solo se c'è almeno una sede). $sedeScelta: id della sede, "principale" o vuoto.
 */
function zone_albero(string $servizio, string $zona, string $sedeScelta, string $titolo, string $descrizione): array {
    $conSede = (bool)sedi_tutte();
    $sedeWidget = $sedeScelta !== '' ? $sedeScelta : 'principale';
    $pulsanti = [];
    $contatti = db()->prepare('SELECT slug FROM ' . tbl('pages') . " WHERE slug = 'contatti' AND status = 'published' AND cestino IS NULL");
    $contatti->execute();
    if ($contatti->fetchColumn()) $pulsanti[] = ['testo' => 'Contattaci', 'link' => '/contatti/', 'stile' => 'viola'];
    $riquadri = zone_riquadri_guidati($zona);
    $colonne = [];
    foreach ($riquadri as $html) $colonne[] = ['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => ['larghezza' => ['d' => 33, 'm' => 100]], 'figli' => [editor_widget('testo', ['contenuto' => $html])]];
    $albero = [
        editor_sezione([editor_widget('hero', ['occhiello' => $zona, 'titolo' => $servizio . ' a *' . $zona . '*', 'testo' => $descrizione, 'pulsanti' => $pulsanti, 'immagine' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]])], ['spazio_sopra' => ['d' => 48], 'spazio_sotto' => ['d' => 24]]),
        ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => ['spazio_colonne' => ['d' => 32]], 'figli' => $colonne],
    ];
    // Altre zone e Dati dell'attività nella stessa sezione: finché le altre zone sono bozze il widget non esce, e la pagina non ha un vuoto.
    $fondo = [editor_widget('zone', [])];
    if ($conSede) $fondo[] = editor_widget('dati_attivita', ['sede' => $sedeWidget, 'titolo' => 'La sede di riferimento', 'margine_sotto' => ['d' => 0]]);
    else $fondo[0]['impostazioni']['margine_sotto'] = ['d' => 0];
    $albero[] = editor_sezione($fondo, ['larghezza' => 'stretta']);
    if ($conSede) $albero[] = editor_sezione([editor_widget('barra_contatti', ['sede' => $sedeWidget, 'margine_sotto' => ['d' => 0]])], ['spazio_sopra' => ['d' => 0], 'spazio_sotto' => ['d' => 0]]);
    return $albero;
}

/**
 * Crea le bozze: una pagina (tabella pages, stato bozza) con l'albero dell'editor per ogni zona che non ha già la sua pagina
 * per questo servizio. Ritorna ['create' => [[pagina, titolo, slug]], 'esistenti' => [[zona, titolo]], 'errore' => '' o il motivo
 * dello stop (il limite di ZONE_MAX)]. Mai pubblicate da qui.
 */
function zone_crea_bozze(array $v, string $autore = ''): array {
    require_once __DIR__ . '/editor-controlli.php';
    require_once __DIR__ . '/editor.php';
    editor_carica_widget_esterni();
    $out = ['create' => [], 'esistenti' => [], 'errore' => ''];
    $servizio = sedi_testo($v['servizio'] ?? '', 80);
    $zone = array_slice(array_values(array_filter(array_map(fn($z) => sedi_testo($z, 80), (array)($v['zone'] ?? [])))), 0, ZONE_GIRO_MAX);
    if ($servizio === '' || !$zone) throw new RuntimeException('Servono il servizio e almeno una zona.');
    $sede = (string)($v['sede'] ?? '');
    if ($sede !== 'principale' && $sede !== '' && !sedi_leggi($sede)) $sede = '';
    $modTitolo = sedi_testo($v['titolo'] ?? '', 120) ?: ZONE_TITOLO_PREDEFINITO;
    $modDescr = sedi_testo($v['descrizione'] ?? '', 300) ?: ZONE_DESCRIZIONE_PREDEFINITA;
    $rif = preg_match('/^[a-z0-9_]{1,40}:\d{1,10}$/', (string)($v['servizio_pagina'] ?? '')) && zone_servizio_pagina((string)$v['servizio_pagina']) ? (string)$v['servizio_pagina'] : '';
    zone_modelli_salva($modTitolo, $modDescr);
    foreach ($zone as $zona) {
        $gia = zone_esiste($servizio, $zona);
        if ($gia) { $out['esistenti'][] = ['zona' => $zona, 'titolo' => (string)$gia['riga']['title'], 'pagina' => $gia['pagina']]; continue; }
        if (count(zone_tutte()) >= ZONE_MAX) { $out['errore'] = 'Hai raggiunto il massimo di ' . ZONE_MAX . ' pagine per zona: le zone dopo "' . $zona . '" non si sono create.'; break; }
        $titolo = zone_componi($modTitolo, $servizio, $zona);
        if ($titolo === '') $titolo = $servizio . ' a ' . $zona;
        $descrizione = mb_substr(zone_componi($modDescr, $servizio, $zona), 0, 160);
        $base = contenuti_slug($titolo) ?: contenuti_slug($servizio . '-a-' . $zona) ?: 'zona';
        $base = mb_substr($base, 0, 150);
        $slug = $base; $n = 2;
        while (!contenuti_slug_libero('pagine', $slug, 0)) $slug = $base . '-' . $n++;
        $id = contenuti_salva('pagine', 0, ['title' => $titolo, 'slug' => $slug, 'stato' => 'bozza', 'data' => date('Y-m-d'), 'contenuto' => '', 'robots' => 'index', 'seo_description' => $descrizione], $autore);
        $albero = editor_valida(['elementi' => zone_albero($servizio, $zona, $sede, $titolo, $descrizione)]);
        editor_salva($id, $albero, false, $autore, 'Pagina per zona (bozza guidata)', 'pagine');
        zone_registra(['pagina' => $id, 'servizio' => $servizio, 'zona' => $zona, 'sede' => $sede, 'servizio_pagina' => $rif]);
        $out['create'][] = ['pagina' => $id, 'titolo' => $titolo, 'slug' => $slug, 'zona' => $zona];
    }
    return $out;
}

// ------------------------------------------------------------
// I segnaposto e la pubblicazione bloccata
// ------------------------------------------------------------

/** Quanti widget dell'albero hanno ancora un testo con il segnaposto [DA SCRIVERE] (ogni widget conta una volta). */
function zone_segnaposto_restanti($albero): int {
    if (!is_array($albero)) return 0;
    $n = 0;
    $contiene = function ($x) use (&$contiene): bool {
        if (is_string($x)) return str_contains($x, ZONE_SEGNAPOSTO) || str_contains($x, htmlspecialchars(ZONE_SEGNAPOSTO, ENT_QUOTES, 'UTF-8'));
        if (is_array($x)) { foreach ($x as $y) if ($contiene($y)) return true; }
        return false;
    };
    $giro = function (array $nodi) use (&$giro, &$n, $contiene): void {
        foreach ($nodi as $e) {
            if (!is_array($e)) continue;
            $tipo = (string)($e['tipo'] ?? '');
            if (in_array($tipo, ['sezione', 'colonna'], true)) { $giro((array)($e['figli'] ?? [])); continue; }
            if ($tipo !== '' && $contiene($e['impostazioni'] ?? [])) $n++;
        }
    };
    $giro((array)($albero['elementi'] ?? []));
    return $n;
}

/** I segnaposto che restano nella pagina (la bozza dell'editor se c'è, altrimenti la versione pubblicata). */
function zone_segnaposto_pagina(int $pagina): int {
    if ($pagina <= 0) return 0;
    require_once __DIR__ . '/editor-controlli.php';
    require_once __DIR__ . '/editor.php';
    $st = db()->prepare('SELECT struttura, struttura_bozza FROM ' . tbl('pages') . ' WHERE id = ?');
    $st->execute([$pagina]);
    $r = $st->fetch();
    if (!$r) return 0;
    editor_carica_widget_esterni();
    $albero = editor_decodifica((string)($r['struttura_bozza'] ?: $r['struttura']));
    return $albero ? zone_segnaposto_restanti($albero) : 0;
}

/** Il messaggio che spiega perché la pagina resta bozza. */
function zone_messaggio_blocco(int $restanti): string {
    return 'Questa pagina per zona ha ancora ' . $restanti . ($restanti === 1 ? ' riquadro da scrivere' : ' riquadri da scrivere') . ' (cerca ' . ZONE_SEGNAPOSTO . ' nel testo): compilali, poi pubblica. Per ora resta in bozza.';
}

/**
 * Si può pubblicare? Una pagina che non è nel registro sì, sempre. Una pagina per zona solo senza segnaposto: nell'albero dato
 * (quello che l'editor sta per pubblicare) oppure in quello salvato. Ritorna ['ok' => bool, 'restanti' => N, 'messaggio' => ''].
 */
function zone_pubblicabile(int $pagina, ?array $albero = null): array {
    if (!zone_di_pagina($pagina)) return ['ok' => true, 'restanti' => 0, 'messaggio' => ''];
    $n = $albero !== null ? zone_segnaposto_restanti($albero) : zone_segnaposto_pagina($pagina);
    return ['ok' => $n === 0, 'restanti' => $n, 'messaggio' => $n === 0 ? '' : zone_messaggio_blocco($n)];
}

/** Lancia un RuntimeException con il messaggio se la pagina per zona non si può pubblicare. Chiamata da ogni strada che pubblica. */
function zone_blocco_pubblicazione(int $pagina, ?array $albero = null): void {
    $p = zone_pubblicabile($pagina, $albero);
    if (!$p['ok']) throw new RuntimeException($p['messaggio']);
}

/** Per le azioni in blocco: [gli id che si possono pubblicare, [id => messaggio] per quelli fermati]. */
function zone_pubblicabili_tra(array $ids): array {
    $ok = []; $fermate = [];
    foreach (array_map('intval', $ids) as $id) {
        $p = zone_pubblicabile($id);
        if ($p['ok']) $ok[] = $id; else $fermate[$id] = $p['messaggio'];
    }
    return [$ok, $fermate];
}

// ------------------------------------------------------------
// Per il widget Altre zone e per i dati per Google
// ------------------------------------------------------------

/** Le altre pagine per zona dello stesso servizio, pubblicate, in ordine di zona: [['zona', 'titolo', 'url']]. */
function zone_altre_pubblicate(array $z): array {
    $out = [];
    $k = mb_strtolower($z['servizio']);
    foreach (zone_tutte() as $id => $x) {
        if ($id === (int)$z['pagina'] || mb_strtolower($x['servizio']) !== $k || !zone_pubblicata($x)) continue;
        $out[] = ['zona' => $x['zona'], 'titolo' => (string)$x['riga']['title'], 'url' => zone_url($x)];
    }
    usort($out, fn($a, $b) => strcoll(mb_strtolower($a['zona']), mb_strtolower($b['zona'])));
    return $out;
}

function zone_jsonld_id(array $c): string {
    return SITO_URL . $c['percorso'] . '#servizio';
}

/** Il nodo Service della pagina per zona: name "%servizio% a %zona%", areaServed la zona, provider la sede di riferimento. */
function zone_jsonld_servizio(array $z, array $c): array {
    $sede = $z['sede'] !== '' ? sedi_per_widget($z['sede']) : null;
    $area = sedi_area_servita_di(['zona' => [$z['zona']]]);
    return array_filter([
        '@type' => 'Service', '@id' => zone_jsonld_id($c), 'name' => $c['titolo'], 'description' => $c['seo']['descrizione'] ?: null,
        'url' => SITO_URL . $c['percorso'], 'serviceType' => $z['servizio'],
        'provider' => ['@id' => $sede ? sedi_jsonld_id($sede) : seo_jsonld_id('servizio')],
        'areaServed' => $area[0] ?? null,
    ], fn($x) => $x !== null && $x !== '');
}

/** Il nodo in più della WebPage: il Service come mainEntity. */
function zone_jsonld_pagina_extra(array $z, array $c): array {
    return ['mainEntity' => ['@id' => zone_jsonld_id($c)]];
}

/** Le briciole: Home > la pagina del servizio (se collegata e pubblicata) > la zona. */
function zone_briciole_voci(array $z, array $c): array {
    $voci = [['Home', '/']];
    $serv = $z['servizio_pagina'] !== '' ? zone_servizio_pagina($z['servizio_pagina']) : null;
    if ($serv && $serv['pubblicata']) $voci[] = [$serv['titolo'], $serv['percorso']];
    $voci[] = [$z['zona'], $c['percorso']];
    return $voci;
}
