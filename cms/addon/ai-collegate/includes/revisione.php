<?php
/**
 * Addon AI collegate (2.33.0): le schede degli articoli. review_article estende audit_content (stessa seo_analisi(), stessa
 * riga della Tabella SEO, stessi link interni suggeriti di seo_link_suggeriti()) con quello che serve a riscrivere un articolo:
 * l'attacco, i sottotitoli in ordine, i paragrafi lunghi, la parola chiave nei punti giusti, le domande frequenti, le frasi
 * datate con il punto preciso (widget o paragrafo), la data dell'ultima modifica e un punteggio di revisione.
 * list_articles_to_review ordina i pubblicati per bisogno; propose_article_changes lascia un gruppo di proposte legate da un
 * codice (approvate o scartate tutte insieme nel pannello). Si carica da strumenti.php. Manuale tecnico, 47.14-47.16.
 */

/** Oltre queste parole un paragrafo è lungo da leggere (sul telefono è un muro). */
const AIC_REV_PARAGRAFO_MAX = 120;
/** Le frasi che invecchiano: chi legge fra un anno non sa più a cosa si riferiscono. */
const AIC_REV_DATATE = [
    '~\bquest[\'\x{2019}]anno\b~iu' => 'quest\'anno',
    '~\bl[\'\x{2019}]anno (scorso|passato|prossimo)\b~iu' => 'l\'anno scorso o prossimo',
    '~\b(recentemente|di recente|ultimamente|negli ultimi mesi)\b~iu' => 'recentemente',
    '~\bil mese (scorso|prossimo)\b~iu' => 'il mese scorso',
    '~\bquest[ao] (mese|settimana|estate|inverno|autunno|primavera|stagione)\b~iu' => 'questo periodo',
    '~\b(attualmente|ad oggi|a oggi|al momento attuale|in questo momento)\b~iu' => 'attualmente',
    '~\b(nuovissim[aoie]|appena uscit[aoie])\b~iu' => 'appena uscito',
];

/** I blocchi di testo di un contenuto con il loro posto: [dove, widget_id, widget, testo]. Dall'albero pubblicato o dalla scheda. */
function aic_rev_blocchi(string $tipo, array $r): array {
    $out = [];
    $json = trim((string)($r['struttura'] ?? ''));
    if ($json !== '') {
        $a = json_decode($json, true);
        if (is_array($a)) {
            $n = 0;
            foreach ((array)($a['elementi'] ?? []) as $si => $s) foreach ((array)($s['figli'] ?? []) as $c) foreach ((array)($c['figli'] ?? []) as $w) {
                if (!is_array($w)) continue;
                $n++;
                $testi = [];
                array_walk_recursive($w['impostazioni'], function ($v, $k) use (&$testi) {
                    if (!is_string($v) || $v === '' || preg_match(AIC_NAV_CHIAVI_LINK, (string)$k) || preg_match('~^(#|/|https?:|\{)~', $v) || in_array((string)$k, ['immagine', 'icona', 'livello', 'tipo', 'disposizione', 'id'], true)) return;
                    $testi[] = $v;
                });
                $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('~<[^>]+>~', ' $0 ', implode(' ', $testi))), ENT_QUOTES, 'UTF-8')));
                if ($t !== '') $out[] = ['dove' => 'sezione ' . ($si + 1) . ', widget ' . $n, 'widget_id' => (string)($w['id'] ?? ''), 'widget' => (string)($w['tipo'] ?? ''), 'testo' => $t];
            }
            return $out;
        }
    }
    $h = (string)($r[contenuti_tipo($tipo)['campo_contenuto']] ?? '');
    if (preg_match_all('~<(p|h[1-6]|li|blockquote|td)\b[^>]*>([\s\S]*?)</\1>~i', $h, $m)) {
        foreach ($m[2] as $i => $x) { $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($x), ENT_QUOTES, 'UTF-8'))); if ($t !== '') $out[] = ['dove' => 'blocco ' . ($i + 1) . ' del testo (' . strtolower($m[1][$i]) . ')', 'widget_id' => '', 'widget' => '', 'testo' => $t]; }
    } elseif (trim(strip_tags($h)) !== '') $out[] = ['dove' => 'testo', 'widget_id' => '', 'widget' => '', 'testo' => trim(strip_tags($h))];
    return $out;
}

/** Le frasi datate, con il punto preciso e un pezzo di testo intorno. Anche nel titolo e nei campi per Google. */
function aic_rev_datate(string $tipo, array $r): array {
    $anno = (int)date('Y');
    $out = [];
    $guarda = function (string $testo, array $dove) use (&$out, $anno) {
        $trova = [];
        foreach (AIC_REV_DATATE as $re => $nome) if (preg_match_all($re, $testo, $m, PREG_OFFSET_CAPTURE)) foreach ($m[0] as $x) $trova[] = [$x[1], $x[0], $nome];
        if (preg_match_all('~\b(19[5-9]\d|20\d\d)\b~', $testo, $m, PREG_OFFSET_CAPTURE)) foreach ($m[0] as $x) if ((int)$x[0] < $anno && (int)$x[0] >= $anno - 15) $trova[] = [$x[1], $x[0], 'anno passato'];
        foreach ($trova as [$pos, $parola, $nome]) {
            if (count($out) >= 40) return;
            $inizio = max(0, $pos - 60);
            $pezzo = substr($testo, $inizio, strlen($parola) + 120);
            $pezzo = mb_scrub($pezzo, 'UTF-8');
            $out[] = $dove + ['frase' => $parola, 'genere' => $nome, 'estratto' => ($inizio > 0 ? '...' : '') . trim($pezzo) . '...'];
        }
    };
    $guarda((string)$r['title'], ['dove' => 'titolo', 'campo' => 'titolo', 'widget_id' => '']);
    $guarda((string)($r['seo_title'] ?? ''), ['dove' => 'titolo per Google', 'campo' => 'titolo_seo', 'widget_id' => '']);
    $guarda((string)(($r['meta_description'] ?? '') ?: ($r['seo_description'] ?? '')), ['dove' => 'descrizione per Google', 'campo' => 'descrizione_seo', 'widget_id' => '']);
    foreach (aic_rev_blocchi($tipo, $r) as $b) $guarda($b['testo'], ['dove' => $b['dove'], 'campo' => 'testo', 'widget_id' => $b['widget_id'], 'widget' => $b['widget']]);
    return $out;
}

/** I sottotitoli in ordine dall'HTML disegnato, con i salti di livello (H2 seguito da H4) e gli H1 in più. */
function aic_rev_sottotitoli(string $html, string $tipo): array {
    preg_match_all('~<h([1-6])\b[^>]*>([\s\S]*?)</h\1>~i', $html, $m, PREG_SET_ORDER);
    $elenco = []; $salti = []; $h1 = 0;
    // Il titolo dell'articolo è l'H1 della pagina (lo mette il sito fuori dal testo): si parte da 1. Una pagina dell'editor ha il suo H1.
    $prec = $tipo === 'articoli' ? 1 : 0;
    foreach ($m as $x) {
        $l = (int)$x[1];
        $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($x[2]), ENT_QUOTES, 'UTF-8')));
        if ($t === '') continue;
        if ($l === 1) $h1++;
        if (count($elenco) < 60) $elenco[] = ['livello' => 'H' . $l, 'testo' => mb_substr($t, 0, 140)];
        if ($prec > 0 && $l > $prec + 1) $salti[] = 'Dopo un H' . $prec . ' c\'è un H' . $l . ' («' . mb_substr($t, 0, 60) . '»): manca il livello H' . ($prec + 1) . '.';
        $prec = $l;
    }
    $h1Troppi = $tipo === 'articoli' ? $h1 > 0 : $h1 > 1;
    return ['elenco' => $elenco, 'salti' => $salti, 'h1_nel_testo' => $h1, 'h1_di_troppo' => $h1Troppi,
        'nota' => $h1Troppi ? ($tipo === 'articoli' ? 'Nel testo c\'è un H1: il titolo dell\'articolo è già l\'H1 della pagina. Usa H2 per le parti.' : 'La pagina ha più di un H1: tienine uno solo (il titolo principale).') : ''];
}

/** I paragrafi (testo) dall'HTML disegnato. */
function aic_rev_paragrafi(string $html): array {
    preg_match_all('~<p\b[^>]*>([\s\S]*?)</p>~i', $html, $m);
    $out = [];
    foreach ($m[1] as $p) { $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($p), ENT_QUOTES, 'UTF-8'))); if ($t !== '') $out[] = $t; }
    return $out;
}

/** L'attacco: le prime 2-3 frasi del primo paragrafo vero. Rispondono alla domanda (parola chiave o parole del titolo)? */
function aic_rev_attacco(array $paragrafi, string $kw, string $titolo): array {
    $primo = '';
    foreach ($paragrafi as $p) if (count(seo_parole($p)) >= 8) { $primo = $p; break; }
    if ($primo === '') return ['testo' => '', 'frasi' => 0, 'risponde' => false, 'nota' => 'Non trovo un primo paragrafo di testo: l\'articolo comincia con titoli, elenchi o immagini. Apri con due o tre frasi che rispondono subito alla domanda.'];
    $frasi = array_slice(preg_split('/(?<=[.!?])\s+/u', $primo, -1, PREG_SPLIT_NO_EMPTY) ?: [$primo], 0, 3);
    $testo = implode(' ', $frasi);
    $domanda = $kw !== '' ? $kw : $titolo;
    $termini = function_exists('seo_link_termini') ? seo_link_termini($domanda) : array_filter(preg_split('/\s+/u', mb_strtolower($domanda)), fn($x) => mb_strlen($x) > 3);
    $norm = function_exists('seo_norm') ? seo_norm($testo) : mb_strtolower($testo);
    $presi = 0;
    foreach ($termini as $t) if (str_contains($norm, function_exists('seo_norm') ? trim(seo_norm($t)) : $t)) $presi++;
    $risponde = $kw !== '' ? (seo_contiene($testo, $kw) || ($termini && $presi >= (int)ceil(count($termini) * 0.6))) : ($termini ? $presi >= (int)ceil(count($termini) / 2) : true);
    $domandaRetorica = str_ends_with(trim($frasi[0]), '?');
    $nota = $risponde ? ($domandaRetorica ? 'L\'attacco comincia con una domanda: meglio la risposta diretta, poi la spiegazione.' : 'L\'attacco parla subito dell\'argomento.')
        : 'Le prime frasi non nominano ' . ($kw !== '' ? 'la parola chiave «' . $kw . '»' : 'l\'argomento del titolo') . ': chi arriva da Google (e le AI che citano) vuole la risposta nelle prime righe.';
    return ['testo' => mb_substr($testo, 0, 600), 'frasi' => count($frasi), 'risponde' => $risponde && !$domandaRetorica, 'nota' => $nota];
}

/** La revisione di un contenuto (review_article). $leggera: solo il punteggio e i problemi principali (per l'elenco). */
function aic_rev_analizza(string $tipo, array $r, bool $leggera = false): array {
    $html = aic_html_contenuto($tipo, $r);
    $an = aic_analisi($tipo, $r, $html);
    $kw = trim((string)($r['keyword_target'] ?? ''));
    $paragrafi = aic_rev_paragrafi($html);
    $attacco = aic_rev_attacco($paragrafi, $kw, (string)$r['title']);
    $sott = aic_rev_sottotitoli($html, $tipo);
    $lunghi = [];
    foreach ($paragrafi as $i => $p) { $n = count(seo_parole($p)); if ($n > AIC_REV_PARAGRAFO_MAX) $lunghi[] = ['paragrafo' => $i + 1, 'parole' => $n, 'inizio' => mb_substr($p, 0, 90) . '...']; }
    $datate = aic_rev_datate($tipo, $r);
    $faq = sito_faq_da_json((string)($r['faq'] ?? ''));
    $domandeNeiTitoli = array_values(array_filter(array_column($sott['elenco'], 'testo'), fn($t) => str_ends_with($t, '?')));
    $parole = (int)($an['parole'] ?? 0);
    // Il punteggio di revisione: quello di seo_analisi, meno quello che seo_analisi non guarda.
    $pt = (int)$an['punteggio']; $problemi = [];
    if (!$attacco['risponde']) { $pt -= 8; $problemi[] = 'L\'attacco non risponde subito alla domanda.'; }
    if ($sott['salti']) { $pt -= 6; $problemi[] = 'Sottotitoli con salti di livello.'; }
    if ($sott['h1_di_troppo']) { $pt -= 4; $problemi[] = 'H1 di troppo nel testo.'; }
    if (!$sott['elenco'] && $parole >= 300) { $pt -= 6; $problemi[] = 'Testo lungo senza sottotitoli.'; }
    if ($lunghi) { $pt -= min(9, 3 * count($lunghi)); $problemi[] = count($lunghi) . ' paragrafi oltre ' . AIC_REV_PARAGRAFO_MAX . ' parole.'; }
    if ($datate) { $pt -= min(12, 4 * count($datate)); $problemi[] = count($datate) . ' frasi datate.'; }
    foreach ($an['voci'] as $v) if ($v['stato'] === 'no' && count($problemi) < 8) $problemi[] = strip_tags((string)$v['testo']);
    $pt = max(0, min(100, $pt));
    $mod = (string)($r['updated_at'] ?? '');
    $giorni = $mod !== '' ? (int)floor((time() - strtotime($mod)) / 86400) : null;
    $base = ['tipo' => $tipo, 'id' => (int)$r['id'], 'titolo' => (string)$r['title'], 'percorso' => contenuti_percorso($tipo, $r), 'stato' => contenuti_stato($tipo, $r),
        'punteggio_revisione' => $pt, 'punteggio_seo' => (int)$an['punteggio'], 'ultima_modifica' => $mod, 'giorni_dall_ultima_modifica' => $giorni];
    if ($leggera) return $base + ['problemi_principali' => array_slice($problemi, 0, 4)];
    $riga = null;
    foreach (seo_tabella_righe() as $x) if ($x['tipo'] === $tipo && $x['id'] === (int)$r['id']) { $riga = $x; break; }
    $sug = aic_suggeriti($an['suggeriti'] ?? null);
    $daCollegare = array_values(array_filter($sug['voci'] ?? [], fn($v) => !$v['collegato']));
    [$mappa, $bozze, $redirect] = aic_audit_basi();
    $c = ['tipo' => $tipo, 'id' => (int)$r['id'], 'titolo' => (string)$r['title'], 'percorso' => $base['percorso'], 'stato' => $base['stato']];
    $kwIn = $kw === '' ? null : [
        'titolo_per_google' => seo_contiene((string)(($r['seo_title'] ?? '') ?: $r['title']), $kw),
        'attacco' => seo_contiene($attacco['testo'], $kw),
        'sottotitoli' => seo_contiene(implode("\n", array_column($sott['elenco'], 'testo')), $kw),
        'indirizzo' => seo_contiene(str_replace('-', ' ', (string)$r['slug']), $kw),
    ];
    $consigli = [];
    if (!$attacco['risponde']) $consigli[] = 'Riscrivi le prime due o tre frasi perché rispondano alla domanda principale (propose_article_changes, campo "testo" del primo widget Testo).';
    if ($kw === '' && $tipo === 'articoli') $consigli[] = 'Scegli una parola chiave (campo "parola_chiave"): serve per controllare titolo, attacco e sottotitoli.';
    elseif ($kwIn) foreach ($kwIn as $dove => $si) if (!$si) $consigli[] = 'La parola chiave «' . $kw . '» manca ' . ['titolo_per_google' => 'nel titolo per Google', 'attacco' => 'nelle prime frasi', 'sottotitoli' => 'nei sottotitoli', 'indirizzo' => 'nell\'indirizzo (non si cambia su un pubblicato senza un motivo forte)'][$dove] . '.';
    foreach ($sott['salti'] as $s) $consigli[] = $s;
    if ($sott['nota'] !== '') $consigli[] = $sott['nota'];
    if (!$sott['elenco'] && $parole >= 300) $consigli[] = 'Dividi il testo con due o tre sottotitoli H2 che dicono di cosa parla ogni parte.';
    if ($lunghi) $consigli[] = 'Spezza i paragrafi lunghi (' . implode(', ', array_map(fn($x) => 'il ' . $x['paragrafo'] . '° di ' . $x['parole'] . ' parole', array_slice($lunghi, 0, 4))) . ').';
    if (count($faq) < 2) $consigli[] = $domandeNeiTitoli ? 'Alcuni sottotitoli sono domande: diventano domande frequenti (campo "domande_frequenti").' : 'Aggiungi due o tre domande frequenti con risposte brevi (campo "domande_frequenti").';
    if ($daCollegare) $consigli[] = 'Collega ' . implode(', ', array_map(fn($v) => '«' . $v['titolo'] . '» (' . $v['percorso'] . ')', array_slice($daCollegare, 0, 5))) . ' dentro il testo.';
    if ($datate) $consigli[] = 'Togli o precisa le frasi datate (' . implode(', ', array_unique(array_column(array_slice($datate, 0, 5), 'frase'))) . '): scrivi la data vera o una frase che non invecchia.';
    $senzaAlt = array_map(fn($x) => ['immagine' => $x['immagine'], 'dove' => $x['dove'], 'widget_id' => $x['widget_id'] ?? ''], media_alt_contenuto($tipo, $r));
    if ($senzaAlt) $consigli[] = (count($senzaAlt) === 1 ? 'Un\'immagine senza alt' : count($senzaAlt) . ' immagini senza alt') . ': proponi un alt che le descriva (campo "alt", tipo "media", con l\'indirizzo dell\'immagine).';
    $widgetTesto = [];
    foreach (aic_rev_blocchi($tipo, $r) as $b) if ($b['widget_id'] !== '' && in_array($b['widget'], ['testo', 'titolo', 'frase', 'citazione'], true) && count($widgetTesto) < 40) $widgetTesto[] = ['widget_id' => $b['widget_id'], 'widget' => $b['widget'], 'dove' => $b['dove'], 'inizio' => mb_substr($b['testo'], 0, 100)];
    return $base + [
        'titolo_per_google' => $riga ? ['valore' => $riga['titolo_effettivo'], 'lunghezza' => $riga['lunghezza_titolo'], 'semaforo' => $riga['sem_titolo']['colore'], 'nota' => strip_tags((string)($riga['sem_titolo']['testo'] ?? ''))] : null,
        'descrizione_per_google' => $riga ? ['valore' => $riga['descrizione'], 'lunghezza' => $riga['lunghezza_descrizione'], 'semaforo' => $riga['sem_descrizione']['colore'], 'nota' => strip_tags((string)($riga['sem_descrizione']['testo'] ?? ''))] : null,
        'slug' => (string)$r['slug'], 'parola_chiave' => $kw, 'parola_chiave_in' => $kwIn,
        'attacco' => $attacco, 'sottotitoli' => $sott, 'parole' => $parole, 'paragrafi' => count($paragrafi), 'paragrafi_lunghi' => $lunghi,
        'domande_frequenti' => ['quante' => count($faq), 'domande' => array_slice(array_column($faq, 'domanda'), 0, 20), 'sottotitoli_che_sono_domande' => array_slice($domandeNeiTitoli, 0, 10)],
        'link_interni_suggeriti' => array_slice($daCollegare, 0, 5), 'link_interni_gia_collegati' => array_values(array_filter($sug['voci'] ?? [], fn($v) => $v['collegato'])),
        'link_con_problemi' => aic_link_problemi($html, $c, $mappa, $bozze, $redirect),
        'immagini_senza_alt' => $senzaAlt, 'frasi_datate' => $datate, 'widget_di_testo' => $widgetTesto,
        'voci_seo' => array_map(fn($v) => ['chiave' => $v['chiave'], 'stato' => $v['stato'], 'testo' => strip_tags((string)$v['testo'])], $an['voci']),
        'consigli' => $consigli ?: ['La scheda è in buono stato: niente di urgente da cambiare.'],
        'come_proporre' => 'Usa propose_article_changes con tipo e id di questo contenuto e fino a ' . AIC_SCHEDA_MAX . ' modifiche (titolo_seo, descrizione_seo, parola_chiave, in_breve, domande_frequenti, testo con widget_id, alt_immagine, alt con url): l\'amministratore le vede raggruppate e le approva tutte insieme o una per una.',
        '_registro' => 'punteggio ' . $pt,
    ];
}

/** list_articles_to_review: i pubblicati con il punteggio di revisione più basso prima, poi i più vecchi. */
function aic_rev_elenco(array $a): array {
    $tipo = in_array($a['tipo'] ?? 'articoli', ['articoli', 'pagine'], true) ? ($a['tipo'] ?? 'articoli') : 'articoli';
    $per = max(1, min(20, (int)($a['per_pagina'] ?? 10)));
    $pagina = max(1, (int)($a['pagina'] ?? 1));
    $righe = db()->query('SELECT * FROM ' . tbl(contenuti_tipo($tipo)['tabella']) . ' WHERE ' . contenuti_sql_stato($tipo, 'pubblicato') . ' ORDER BY updated_at ASC LIMIT 1000')->fetchAll();
    $voci = [];
    foreach ($righe as $r) {
        if ($tipo === 'pagine' && contenuti_percorso($tipo, $r) === (defined('SITO_PAGINA_BLOG') ? SITO_PAGINA_BLOG : '/blog/')) continue;
        $voci[] = aic_rev_analizza($tipo, $r, true);
    }
    usort($voci, fn($x, $y) => $x['punteggio_revisione'] <=> $y['punteggio_revisione'] ?: strcmp((string)$x['ultima_modifica'], (string)$y['ultima_modifica']));
    $tot = count($voci);
    return ['tipo' => $tipo, 'totale' => $tot, 'pagina' => $pagina, 'per_pagina' => $per, 'pagine' => max(1, (int)ceil($tot / $per)),
        'contenuti' => array_slice($voci, ($pagina - 1) * $per, $per),
        'nota' => 'Lavorali uno alla volta: review_article sul primo, poi propose_article_changes, poi il successivo. Quando un articolo ha proposte in attesa, il suo punteggio cambia solo dopo che l\'amministratore le approva.',
        '_registro' => $tot . ' ' . $tipo . ', pagina ' . $pagina];
}

/**
 * propose_article_changes: fino a AIC_SCHEDA_MAX proposte sullo stesso contenuto, tutte controllate prima di scriverne una,
 * legate dal codice "gruppo" (<tipo>-<id>-<12 hex>).
 */
function aic_rev_proponi(array $conn, array $a): array {
    aic_schema();
    $tipo = aic_tipo($a['tipo'] ?? null);
    $r = aic_riga($tipo, $a['id'] ?? 0);
    $mod = $a['modifiche'] ?? null;
    if (!is_array($mod) || !$mod || !array_is_list($mod)) throw new AicErrore('"modifiche" deve essere un elenco di {campo, valore, widget_id?, url?, motivo?}.');
    if (count($mod) > AIC_SCHEDA_MAX) throw new AicErrore('Troppe modifiche in una chiamata: ' . count($mod) . ' (massimo ' . AIC_SCHEDA_MAX . '). Dividile in più chiamate.');
    $motivo = aic_testo($a['motivo'] ?? '', 1000, true);
    if (mb_strlen($motivo) < 5) throw new AicErrore('Scrivi il motivo generale delle proposte (lo legge l\'amministratore per decidere).');
    $gruppo = $tipo . '-' . (int)$r['id'] . '-' . bin2hex(random_bytes(6));
    // Prima si controllano tutte (nessuna scrittura), poi si scrivono: o tutte o nessuna.
    $visti = []; $pronte = [];
    foreach ($mod as $i => $m) {
        $campo = is_array($m) ? (string)($m['campo'] ?? '') : '';
        try {
            if (!is_array($m)) throw new AicErrore('ogni modifica è un oggetto {campo, valore}.');
            if (!array_key_exists('valore', $m)) throw new AicErrore('manca "valore".');
            $chiave = $campo . '|' . (is_string($m['widget_id'] ?? null) ? $m['widget_id'] : '') . '|' . (is_string($m['url'] ?? null) ? $m['url'] : '');
            if (isset($visti[$chiave])) throw new AicErrore('il campo "' . mb_substr($campo, 0, 40) . '" compare due volte: una modifica per campo (e per widget o immagine).');
            $visti[$chiave] = true;
            $mm = aic_testo($m['motivo'] ?? '', 1000, true);
            $x = ['tipo' => $campo === 'alt' ? 'media' : $tipo, 'id' => (int)$r['id'], 'campo' => $campo, 'valore' => $m['valore'], 'widget_id' => $m['widget_id'] ?? '', 'url' => $m['url'] ?? '',
                'motivo' => mb_strlen($mm) >= 5 ? $mm : $motivo];
            $pronte[] = [$m, aic_proposta_prepara($conn, $x, count($pronte))];
        } catch (AicErrore $e) {
            throw new AicErrore('Nessuna proposta salvata: modifica ' . ($i + 1) . ' (' . mb_substr(aic_testo($campo, 40), 0, 40) . '): ' . $e->getMessage(), $e->http);
        }
    }
    $fatte = [];
    foreach ($pronte as [$m, $x]) {
        $p = aic_proposta_salva($conn, $x, $gruppo);
        $fatte[] = ['proposta' => $p['id'], 'campo' => $p['campo'], 'widget_id' => (string)$x['widget_id'], 'url' => (string)$x['media_url'], 'prima' => mb_substr($p['prima'], 0, 300), 'dopo' => mb_substr($p['dopo'], 0, 300)];
    }
    return ['gruppo' => $gruppo, 'tipo' => $tipo, 'id' => (int)$r['id'], 'titolo' => (string)$r['title'], 'proposte' => $fatte, 'stato' => 'attesa',
        'nota' => 'Proposte registrate insieme: l\'amministratore le vede in AI collegate > Proposte sotto "Scheda ' . ($tipo === 'articoli' ? 'dell\'articolo' : 'della pagina') . ' ' . (string)$r['title'] . '" e può approvarle tutte o una per una.',
        '_registro' => count($fatte) . ' proposte nella scheda ' . (string)$r['title']];
}
