<?php
/**
 * 2.29.0: i dati per Google in più di un contenuto: Prodotto, How-to (una guida a passi), Video.
 * 2.35.0: Programma (SoftwareApplication: nome, versione, categoria, sistema, prezzo, licenza, download, requisiti), per chi
 * presenta un software come la home di plumecms.it. Niente valutazioni (aggregateRating): inventarle è vietato da Google.
 *
 * Si scelgono nel riquadro "SEO e Google" della scheda di un articolo o di una pagina (admin/contenuto.php, tendina "Dati per
 * Google in più") e stanno nella colonna `seo_schema` (JSON) di articles e pages, aggiunta anche alle tabelle vecchie da
 * contenuti_schema_colonne() come le altre colonne dei contenuti. Il sito li scrive come nodi del @graph del JSON-LD della
 * pagina (Product con Offer, HowTo con HowToStep, VideoObject) con la WebPage che li indica in mainEntity
 * (seo_schema_applica(), chiamata da sito/index.php e da seo_jsonld_di()). Un contenuto senza niente scelto esce identico a prima.
 *
 * Sicurezza: nessun HTML nei campi (strip_tags), numeri e date controllati, indirizzi dei video solo https (o un file del sito),
 * al massimo SEO_SCHEMA_PASSI_MAX passi; il JSON-LD esce con JSON_HEX_TAG come tutto il resto (sito_jsonld_script).
 */

const SEO_SCHEMA_TIPI = ['' => 'Nessuno', 'prodotto' => 'Prodotto (prezzo e disponibilità)', 'howto' => 'How-to (una guida a passi)', 'video' => 'Video', 'programma' => 'Programma (un software da scaricare o usare)'];
/** 2.35.0: le categorie di schema.org per un programma (applicationCategory), con il nome per il pannello. */
const SEO_SCHEMA_CATEGORIE = ['WebApplication' => 'Applicazione web', 'DeveloperApplication' => 'Per sviluppatori', 'BusinessApplication' => 'Per il lavoro',
    'DesignApplication' => 'Grafica e design', 'EducationalApplication' => 'Istruzione', 'MultimediaApplication' => 'Foto, audio e video', 'UtilitiesApplication' => 'Utilità', 'GameApplication' => 'Gioco'];
const SEO_SCHEMA_PASSI_MAX = 20;
const SEO_SCHEMA_DISPONIBILITA = ['InStock' => 'Disponibile', 'OutOfStock' => 'Esaurito', 'PreOrder' => 'In prenotazione', 'LimitedAvailability' => 'Ultimi pezzi'];
const SEO_SCHEMA_TABELLE = ['articles', 'pages'];

/** I dati per Google in più si possono scegliere per questo tipo di contenuto (articoli e pagine: hanno la colonna). */
function seo_schema_per_tipo(string $tipo): bool {
    try { return in_array((string)(contenuti_tipo($tipo)['tabella'] ?? ''), SEO_SCHEMA_TABELLE, true); } catch (Throwable $e) { return false; }
}

/** I campi di partenza, tutti vuoti. */
function seo_schema_vuoto(): array {
    return [
        'tipo' => '',
        'prodotto' => ['nome' => '', 'prezzo' => '', 'valuta' => 'EUR', 'disponibilita' => 'InStock', 'codice' => '', 'marca' => '', 'foto' => ''],
        'howto' => ['nome' => '', 'tempo' => '', 'passi' => []],
        'video' => ['nome' => '', 'descrizione' => '', 'miniatura' => '', 'data' => '', 'url' => '', 'incorporo' => '', 'durata' => ''],
        'programma' => ['nome' => '', 'versione' => '', 'categoria' => 'WebApplication', 'sistema' => '', 'prezzo' => '', 'valuta' => 'EUR', 'licenza' => '', 'download' => '', 'requisiti' => ''],
    ];
}

/** Un testo semplice: senza tag, senza righe, senza caratteri a 4 byte, tagliato. */
function seo_schema_testo($x, int $max): string {
    $t = trim(preg_replace('/\s+/u', ' ', strip_tags((string)(is_scalar($x) ? $x : ''))));
    if (function_exists('sito_senza_4byte')) $t = sito_senza_4byte($t);
    return mb_substr($t, 0, $max);
}

/** Un testo su più righe (il testo di un passo, la descrizione del video): senza tag, tagliato. */
function seo_schema_testo_lungo($x, int $max): string {
    $t = trim(str_replace("\r", '', strip_tags((string)(is_scalar($x) ? $x : ''))));
    if (function_exists('sito_senza_4byte')) $t = sito_senza_4byte($t);
    return mb_substr($t, 0, $max);
}

/**
 * Un indirizzo di un'immagine o di un video: https:// (http solo per le immagini), oppure un percorso del sito (/uploads/...).
 * Niente javascript:, data:, "..", spazi o virgolette. Ritorna '' se non va bene.
 */
function seo_schema_url($x, bool $soloHttps): string {
    $u = trim((string)(is_scalar($x) ? $x : ''));
    if ($u === '' || strlen($u) > 500 || str_contains($u, '..') || preg_match('/[\s"\'<>\\\\]/', $u)) return '';
    if (preg_match('~^/[A-Za-z0-9/_.%\-]+$~', $u) && !str_starts_with($u, '//')) return $u;
    if (preg_match('~^https://[A-Za-z0-9.\-]+(:\d+)?(/[^\s]*)?$~', $u)) return $u;
    if (!$soloHttps && preg_match('~^http://[A-Za-z0-9.\-]+(:\d+)?(/[^\s]*)?$~', $u)) return $u;
    return '';
}

/** I dati salvati di un contenuto (la colonna seo_schema), normalizzati. Vuoto o rotto: niente scelto. */
function seo_schema_leggi($json): array {
    $s = seo_schema_vuoto();
    $d = is_array($json) ? $json : json_decode((string)$json, true);
    if (!is_array($d) || !isset(SEO_SCHEMA_TIPI[(string)($d['tipo'] ?? '')]) || (string)$d['tipo'] === '') return $s;
    $s['tipo'] = (string)$d['tipo'];
    foreach (['prodotto', 'howto', 'video', 'programma'] as $k) {
        if (!is_array($d[$k] ?? null)) continue;
        foreach ($s[$k] as $campo => $pred) {
            if ($campo === 'passi') {
                $passi = [];
                foreach (array_slice(is_array($d[$k]['passi'] ?? null) ? array_values($d[$k]['passi']) : [], 0, SEO_SCHEMA_PASSI_MAX) as $p) {
                    if (!is_array($p)) continue;
                    $passi[] = ['nome' => seo_schema_testo($p['nome'] ?? '', 200), 'testo' => seo_schema_testo_lungo($p['testo'] ?? '', 1000)];
                }
                $s[$k]['passi'] = $passi;
            } else $s[$k][$campo] = is_scalar($d[$k][$campo] ?? null) ? (string)$d[$k][$campo] : $pred;
        }
    }
    return $s;
}

/** Il prezzo scritto come 24,90 o 24.90 -> "24.90"; null se non è un numero da 0 a 99999999. */
function seo_schema_prezzo(string $x): ?string {
    $x = str_replace([' ', '€'], '', trim($x));
    $x = str_replace(',', '.', $x);
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $x)) return null;
    return number_format((float)$x, 2, '.', '');
}

/**
 * 2.35.0: la versione di un programma: numeri, lettere, punti, trattini e più (2.35.0, 1.0-beta), fino a 30 caratteri, oppure
 * {versione:<serie>} del download manager, che il sito sostituisce con l'ultima versione caricata (sito_html). '' se non va bene.
 */
function seo_schema_versione(string $x): string {
    $x = trim($x);
    if (preg_match('/^[0-9A-Za-z][0-9A-Za-z.+\-]{0,29}$/', $x)) return $x;
    if (preg_match('/^\{versione:[a-z0-9][a-z0-9._-]{0,120}\}$/', $x)) return $x;
    return '';
}

/** Minuti e secondi ("1:30", "90", "1.30") -> secondi; null se non si legge. */
function seo_schema_secondi(string $x): ?int {
    $x = trim($x);
    if ($x === '') return null;
    if (preg_match('/^(\d{1,4})[:.](\d{1,2})$/', $x, $m)) return (int)$m[1] * 60 + (int)$m[2];
    if (ctype_digit($x)) return (int)$x;
    return null;
}

/** Una durata in secondi nel formato ISO 8601 di schema.org (PT1H2M3S). */
function seo_schema_durata_iso(int $secondi): string {
    $h = intdiv($secondi, 3600); $m = intdiv($secondi % 3600, 60); $s = $secondi % 60;
    return 'PT' . ($h ? $h . 'H' : '') . ($m ? $m . 'M' : '') . (($s || (!$h && !$m)) ? $s . 'S' : '');
}

/**
 * Controlla i campi arrivati dal modulo (schema[tipo], schema[prodotto][...], schema[howto][...], schema[video][...]).
 * Ritorna ['dati' => i dati puliti, 'errori' => [campo => messaggio]]. Con il tipo vuoto i dati sono quelli di partenza e
 * nessun errore (il contenuto torna senza dati in più).
 */
function seo_schema_valida($in): array {
    $in = is_array($in) ? $in : [];
    $s = seo_schema_vuoto();
    $errori = [];
    $tipo = (string)($in['tipo'] ?? '');
    if (!isset(SEO_SCHEMA_TIPI[$tipo])) { $tipo = ''; $errori['tipo'] = 'Scegli un tipo fra quelli dell\'elenco.'; }
    $s['tipo'] = $tipo;
    if ($tipo === 'prodotto') {
        $p = is_array($in['prodotto'] ?? null) ? $in['prodotto'] : [];
        $s['prodotto']['nome'] = seo_schema_testo($p['nome'] ?? '', 200);
        if ($s['prodotto']['nome'] === '') $errori['prodotto_nome'] = 'Scrivi il nome del prodotto.';
        $prezzo = seo_schema_prezzo((string)($p['prezzo'] ?? ''));
        if ($prezzo === null) $errori['prodotto_prezzo'] = 'Scrivi il prezzo come numero, per esempio 24,90.';
        $s['prodotto']['prezzo'] = $prezzo ?? '';
        $valuta = strtoupper(trim((string)($p['valuta'] ?? 'EUR'))) ?: 'EUR';
        if (!preg_match('/^[A-Z]{3}$/', $valuta)) $errori['prodotto_valuta'] = 'La valuta è un codice di tre lettere (EUR, USD, GBP).';
        $s['prodotto']['valuta'] = $valuta;
        $s['prodotto']['disponibilita'] = isset(SEO_SCHEMA_DISPONIBILITA[(string)($p['disponibilita'] ?? '')]) ? (string)$p['disponibilita'] : 'InStock';
        $s['prodotto']['codice'] = seo_schema_testo($p['codice'] ?? '', 60);
        $s['prodotto']['marca'] = seo_schema_testo($p['marca'] ?? '', 100);
        $foto = trim((string)($p['foto'] ?? ''));
        $s['prodotto']['foto'] = seo_schema_url($foto, false);
        if ($foto !== '' && $s['prodotto']['foto'] === '') $errori['prodotto_foto'] = 'La foto è un indirizzo del sito (/uploads/...) oppure un indirizzo https.';
    } elseif ($tipo === 'howto') {
        $h = is_array($in['howto'] ?? null) ? $in['howto'] : [];
        $s['howto']['nome'] = seo_schema_testo($h['nome'] ?? '', 200);
        if ($s['howto']['nome'] === '') $errori['howto_nome'] = 'Scrivi il nome della guida (cosa si impara a fare).';
        $tempo = trim((string)($h['tempo'] ?? ''));
        if ($tempo !== '' && (!ctype_digit($tempo) || (int)$tempo > 100000)) $errori['howto_tempo'] = 'Il tempo totale è un numero di minuti (per esempio 30).';
        $s['howto']['tempo'] = $tempo !== '' && ctype_digit($tempo) ? (string)min(100000, (int)$tempo) : '';
        $passi = [];
        $grezzi = is_array($h['passi'] ?? null) ? array_values($h['passi']) : [];
        foreach ($grezzi as $p) {
            if (!is_array($p)) continue;
            $nome = seo_schema_testo($p['nome'] ?? '', 200); $testo = seo_schema_testo_lungo($p['testo'] ?? '', 1000);
            if ($nome === '' && $testo === '') continue; // una riga lasciata vuota
            if ($nome === '') $nome = mb_substr($testo, 0, 80);
            $passi[] = ['nome' => $nome, 'testo' => $testo];
        }
        if (count($passi) > SEO_SCHEMA_PASSI_MAX) $passi = array_slice($passi, 0, SEO_SCHEMA_PASSI_MAX); // oltre il massimo: si tengono i primi, senza fermarsi (il pannello ne fa scrivere al massimo venti)
        if (!$passi) $errori['howto_passi'] = 'Scrivi almeno un passo.';
        $s['howto']['passi'] = $passi;
    } elseif ($tipo === 'video') {
        $v = is_array($in['video'] ?? null) ? $in['video'] : [];
        $s['video']['nome'] = seo_schema_testo($v['nome'] ?? '', 200);
        if ($s['video']['nome'] === '') $errori['video_nome'] = 'Scrivi il nome del video.';
        $s['video']['descrizione'] = seo_schema_testo_lungo($v['descrizione'] ?? '', 1000);
        if ($s['video']['descrizione'] === '') $errori['video_descrizione'] = 'Scrivi una descrizione del video (Google la vuole).';
        $min = trim((string)($v['miniatura'] ?? ''));
        $s['video']['miniatura'] = seo_schema_url($min, false);
        if ($s['video']['miniatura'] === '') $errori['video_miniatura'] = 'La miniatura è obbligatoria: un\'immagine del sito (/uploads/...) oppure un indirizzo https.';
        $data = trim((string)($v['data'] ?? ''));
        $s['video']['data'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) && checkdate((int)substr($data, 5, 2), (int)substr($data, 8, 2), (int)substr($data, 0, 4)) ? $data : '';
        if ($s['video']['data'] === '') $errori['video_data'] = 'La data di caricamento è obbligatoria (AAAA-MM-GG).';
        $url = trim((string)($v['url'] ?? '')); $inc = trim((string)($v['incorporo'] ?? ''));
        $s['video']['url'] = seo_schema_url($url, true);
        $s['video']['incorporo'] = seo_schema_url($inc, true);
        if ($url !== '' && $s['video']['url'] === '') $errori['video_url'] = 'L\'indirizzo del video deve cominciare con https:// (oppure essere un file del sito, /uploads/video/...).';
        if ($inc !== '' && $s['video']['incorporo'] === '') $errori['video_incorporo'] = 'L\'indirizzo dell\'incorporo deve cominciare con https://.';
        if ($s['video']['url'] === '' && $s['video']['incorporo'] === '' && !isset($errori['video_url']) && !isset($errori['video_incorporo'])) $errori['video_url'] = 'Serve l\'indirizzo del video oppure quello dell\'incorporo.';
        $durata = trim((string)($v['durata'] ?? ''));
        $sec = seo_schema_secondi($durata);
        if ($durata !== '' && ($sec === null || $sec > 86400 * 7)) $errori['video_durata'] = 'La durata si scrive come minuti:secondi (1:30) oppure in secondi.';
        $s['video']['durata'] = $sec !== null && $sec <= 86400 * 7 ? (string)$sec : '';
    } elseif ($tipo === 'programma') {
        $g = is_array($in['programma'] ?? null) ? $in['programma'] : [];
        $s['programma']['nome'] = seo_schema_testo($g['nome'] ?? '', 200);
        if ($s['programma']['nome'] === '') $errori['programma_nome'] = 'Scrivi il nome del programma.';
        $ver = trim((string)(is_scalar($g['versione'] ?? null) ? $g['versione'] : ''));
        $s['programma']['versione'] = seo_schema_versione($ver);
        if ($ver !== '' && $s['programma']['versione'] === '') $errori['programma_versione'] = 'La versione si scrive con numeri e punti (per esempio 2.35.0), oppure {versione:<serie>} del Download.';
        $cat = (string)(is_scalar($g['categoria'] ?? null) ? $g['categoria'] : '');
        if ($cat !== '' && !isset(SEO_SCHEMA_CATEGORIE[$cat])) $errori['programma_categoria'] = 'Scegli una categoria fra quelle dell\'elenco.';
        $s['programma']['categoria'] = isset(SEO_SCHEMA_CATEGORIE[$cat]) ? $cat : 'WebApplication';
        $s['programma']['sistema'] = seo_schema_testo($g['sistema'] ?? '', 200);
        $prezzo = seo_schema_prezzo((string)(is_scalar($g['prezzo'] ?? null) ? $g['prezzo'] : ''));
        if ($prezzo === null) $errori['programma_prezzo'] = 'Scrivi il prezzo come numero: 0 se è gratuito, per esempio 24,90 se si paga.';
        $s['programma']['prezzo'] = $prezzo ?? '';
        $valuta = strtoupper(trim((string)(is_scalar($g['valuta'] ?? null) ? $g['valuta'] : 'EUR'))) ?: 'EUR';
        if (!preg_match('/^[A-Z]{3}$/', $valuta)) { $errori['programma_valuta'] = 'La valuta è un codice di tre lettere (EUR, USD, GBP).'; $valuta = 'EUR'; }
        $s['programma']['valuta'] = $valuta;
        foreach (['licenza' => 'L\'indirizzo della licenza', 'download' => 'L\'indirizzo per scaricarlo'] as $campo => $nomeCampo) {
            $x = trim((string)(is_scalar($g[$campo] ?? null) ? $g[$campo] : ''));
            $s['programma'][$campo] = seo_schema_url($x, true);
            if ($x !== '' && $s['programma'][$campo] === '') $errori['programma_' . $campo] = $nomeCampo . ' comincia con https:// oppure è un percorso del sito (per esempio /scarica/).';
        }
        $s['programma']['requisiti'] = seo_schema_testo($g['requisiti'] ?? '', 300);
    }
    return ['dati' => $s, 'errori' => $errori];
}

/** Il JSON da salvare: '' con il tipo vuoto (la colonna torna vuota: il contenuto esce come prima). */
function seo_schema_json(array $s): string {
    if (($s['tipo'] ?? '') === '') return '';
    $out = ['tipo' => $s['tipo'], $s['tipo'] => $s[$s['tipo']]];
    return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Salva i dati in più di un contenuto dai campi del modulo (schema[...]). Ritorna gli errori (vuoto se tutto bene); con errori
 * non scrive niente e i dati di prima restano. Scrive solo la colonna seo_schema: l'albero dell'editor e il resto non si toccano.
 */
function seo_schema_salva(string $tipo, int $id, array $post): array {
    if (!seo_schema_per_tipo($tipo) || $id <= 0) return [];
    if (!isset($post['schema']) || !is_array($post['schema'])) return [];
    $v = seo_schema_valida($post['schema']);
    if ($v['errori']) return $v['errori'];
    $t = contenuti_tipo($tipo);
    db()->prepare('UPDATE ' . tbl($t['tabella']) . ' SET seo_schema = ? WHERE id = ?')->execute([seo_schema_json($v['dati']), $id]);
    return [];
}

/** Il messaggio per il pannello quando i dati in più non si sono salvati: i campi, in parole. */
function seo_schema_errori_testo(array $errori): string {
    $nomi = ['prodotto_nome' => 'Nome del prodotto', 'prodotto_prezzo' => 'Prezzo', 'prodotto_valuta' => 'Valuta', 'prodotto_foto' => 'Foto del prodotto', 'howto_nome' => 'Nome della guida', 'howto_tempo' => 'Tempo totale', 'howto_passi' => 'Passi',
        'video_nome' => 'Nome del video', 'video_descrizione' => 'Descrizione del video', 'video_miniatura' => 'Miniatura', 'video_data' => 'Data di caricamento', 'video_url' => 'Indirizzo del video', 'video_incorporo' => 'Indirizzo dell\'incorporo', 'video_durata' => 'Durata', 'tipo' => 'Dati per Google in più',
        'programma_nome' => 'Nome del programma', 'programma_versione' => 'Versione', 'programma_categoria' => 'Categoria', 'programma_prezzo' => 'Prezzo del programma', 'programma_valuta' => 'Valuta',
        'programma_licenza' => 'Licenza', 'programma_download' => 'Indirizzo per scaricarlo'];
    $righe = [];
    foreach ($errori as $k => $m) $righe[] = ($nomi[$k] ?? $k) . ': ' . $m;
    return 'I dati per Google in più non sono stati salvati (il resto sì). ' . implode(' ', $righe);
}

/** Un indirizzo assoluto per il JSON-LD: i percorsi del sito prendono SITO_URL davanti. */
function seo_schema_assoluto(string $u): string {
    if ($u === '' || preg_match('~^https?://~', $u)) return $u;
    return (defined('SITO_URL') ? SITO_URL : (function_exists('cms_url_sito') ? cms_url_sito() : '')) . $u;
}

/** Il nodo del JSON-LD per un contenuto di sito_contenuti() (con 'riga', 'percorso', 'titolo', 'seo', 'estratto', 'tldr'), oppure null. */
function seo_schema_nodo(array $c): ?array {
    $s = seo_schema_leggi((string)($c['riga']['seo_schema'] ?? ''));
    if ($s['tipo'] === '') return null;
    $u = seo_schema_assoluto((string)($c['percorso'] ?? '/'));
    $descrizione = trim((string)($c['seo']['descrizione'] ?? '')) ?: (trim((string)($c['tldr'] ?? '')) ?: trim((string)($c['estratto'] ?? '')));
    switch ($s['tipo']) {
        case 'prodotto':
            $p = $s['prodotto'];
            if ($p['nome'] === '' || $p['prezzo'] === '') return null;
            $nodo = ['@type' => 'Product', '@id' => "$u#prodotto", 'name' => $p['nome'], 'url' => $u];
            if ($descrizione !== '') $nodo['description'] = mb_substr($descrizione, 0, 500);
            if ($p['foto'] !== '') $nodo['image'] = seo_schema_assoluto($p['foto']);
            elseif (!empty($c['immagine']['url'])) $nodo['image'] = seo_schema_assoluto((string)$c['immagine']['url']);
            if ($p['codice'] !== '') $nodo['sku'] = $p['codice'];
            if ($p['marca'] !== '') $nodo['brand'] = ['@type' => 'Brand', 'name' => $p['marca']];
            $nodo['offers'] = ['@type' => 'Offer', 'price' => $p['prezzo'], 'priceCurrency' => $p['valuta'] ?: 'EUR', 'availability' => 'https://schema.org/' . (isset(SEO_SCHEMA_DISPONIBILITA[$p['disponibilita']]) ? $p['disponibilita'] : 'InStock'), 'url' => $u];
            return $nodo;
        case 'howto':
            $h = $s['howto'];
            if ($h['nome'] === '' || !$h['passi']) return null;
            $nodo = ['@type' => 'HowTo', '@id' => "$u#howto", 'name' => $h['nome']];
            if ($descrizione !== '') $nodo['description'] = mb_substr($descrizione, 0, 500);
            if ($h['tempo'] !== '' && (int)$h['tempo'] > 0) $nodo['totalTime'] = seo_schema_durata_iso((int)$h['tempo'] * 60);
            $i = 0;
            $nodo['step'] = array_map(function ($p) use ($u, &$i) { $i++; return array_filter(['@type' => 'HowToStep', 'position' => $i, 'name' => $p['nome'], 'text' => $p['testo'] !== '' ? $p['testo'] : $p['nome'], 'url' => "$u#passo-$i"]); }, array_slice($h['passi'], 0, SEO_SCHEMA_PASSI_MAX));
            return $nodo;
        case 'video':
            $v = $s['video'];
            if ($v['nome'] === '' || $v['miniatura'] === '' || $v['data'] === '' || ($v['url'] === '' && $v['incorporo'] === '')) return null;
            $nodo = ['@type' => 'VideoObject', '@id' => "$u#video", 'name' => $v['nome'], 'description' => $v['descrizione'] !== '' ? $v['descrizione'] : ($descrizione !== '' ? mb_substr($descrizione, 0, 500) : $v['nome']),
                'thumbnailUrl' => seo_schema_assoluto($v['miniatura']), 'uploadDate' => $v['data']];
            if ($v['url'] !== '') $nodo['contentUrl'] = seo_schema_assoluto($v['url']);
            if ($v['incorporo'] !== '') $nodo['embedUrl'] = $v['incorporo'];
            if ($v['durata'] !== '' && (int)$v['durata'] > 0) $nodo['duration'] = seo_schema_durata_iso((int)$v['durata']);
            return $nodo;
        case 'programma':
            // 2.35.0: SoftwareApplication. Google chiede nome e offerta (il prezzo, anche 0); il riquadro ricco lo mostra solo con
            // valutazioni vere, che qui non si scrivono mai: i dati servono a Google e alle AI per capire che cos'è il programma.
            $g = $s['programma'];
            if ($g['nome'] === '' || $g['prezzo'] === '') return null;
            $nodo = ['@type' => 'SoftwareApplication', '@id' => "$u#programma", 'name' => $g['nome'], 'url' => $u,
                'applicationCategory' => isset(SEO_SCHEMA_CATEGORIE[$g['categoria']]) ? $g['categoria'] : 'WebApplication'];
            if ($descrizione !== '') $nodo['description'] = mb_substr($descrizione, 0, 500);
            if ($g['sistema'] !== '') $nodo['operatingSystem'] = $g['sistema'];
            if ($g['versione'] !== '') $nodo['softwareVersion'] = $g['versione'];
            if (!empty($c['immagine']['url'])) $nodo['image'] = seo_schema_assoluto((string)$c['immagine']['url']);
            $nodo['offers'] = ['@type' => 'Offer', 'price' => $g['prezzo'] === '0.00' ? '0' : $g['prezzo'], 'priceCurrency' => $g['valuta'] ?: 'EUR'];
            if ($g['download'] !== '') { $nodo['downloadUrl'] = seo_schema_assoluto($g['download']); $nodo['offers']['url'] = $nodo['downloadUrl']; }
            if ($g['licenza'] !== '') $nodo['license'] = seo_schema_assoluto($g['licenza']);
            if ($g['requisiti'] !== '') $nodo['softwareRequirements'] = $g['requisiti'];
            // Autore ed editore: l'Organizzazione di SEO e AEO, se il suo nodo è acceso (seo_jsonld_fissi()).
            if (function_exists('seo_jsonld_id') && function_exists('seo_cfg') && !empty(seo_cfg()['schema']['organization'])) {
                $nodo['author'] = ['@id' => seo_jsonld_id('servizio')];
                $nodo['publisher'] = ['@id' => seo_jsonld_id('servizio')];
            }
            return $nodo;
    }
    return null;
}

/**
 * Aggiunge ai nodi di una pagina il nodo dei dati in più e lo indica come mainEntity della WebPage (se non ne ha già una, come
 * la pagina di una sede). Senza dati scelti i nodi tornano identici: il sito esce come prima, al byte.
 */
function seo_schema_applica(array $nodi, array $c): array {
    if (trim((string)($c['riga']['seo_schema'] ?? '')) === '') return $nodi;
    $nodo = seo_schema_nodo($c);
    if ($nodo === null) return $nodi;
    foreach ($nodi as &$n) {
        if (is_array($n) && ($n['@type'] ?? '') === 'WebPage') { if (!isset($n['mainEntity'])) $n['mainEntity'] = ['@id' => $nodo['@id']]; break; }
    }
    unset($n);
    $nodi[] = $nodo;
    return $nodi;
}

/** Il nome del tipo scelto, per il pannello ("Prodotto", "How-to", "Video") o '' se niente. */
function seo_schema_nome_tipo(string $tipo): string {
    return ['prodotto' => 'Prodotto', 'howto' => 'How-to', 'video' => 'Video', 'programma' => 'Programma'][$tipo] ?? '';
}

/**
 * I campi del riquadro "Dati per Google in più" della scheda (admin/contenuto.php). I valori arrivano da seo_schema_leggi();
 * la tendina mostra i campi del tipo scelto (admin/assets/seo-schema.js). Niente gestori in linea.
 */
function seo_schema_form_html(array $s): string {
    $sel = fn(string $a, string $b) => $a === $b ? ' selected' : '';
    $campo = fn(string $nome, string $etichetta, string $valore, string $extra = '', string $tipoCampo = 'text') => '<div class="form-group"><label for="sc-schema-' . str_replace(['[', ']'], ['-', ''], $nome) . '">' . $etichetta . '</label><input type="' . $tipoCampo . '" name="schema[' . $nome . ']" id="sc-schema-' . str_replace(['[', ']'], ['-', ''], $nome) . '" value="' . h($valore) . '"' . $extra . '></div>';
    $out = '<div class="sc-schema" id="sc-schema"><label for="sc-schema-tipo">Dati per Google in più</label><select name="schema[tipo]" id="sc-schema-tipo" data-schema-tipo>';
    foreach (SEO_SCHEMA_TIPI as $k => $n) $out .= '<option value="' . h($k) . '"' . $sel($s['tipo'], (string)$k) . '>' . h($n) . '</option>';
    $out .= '</select><div class="hint">Un riquadro più ricco nei risultati di Google: un prodotto con prezzo e disponibilità, una guida a passi, un video. "Nessuno" lascia la pagina com\'è.</div>';
    // Prodotto
    $p = $s['prodotto'];
    $out .= '<div class="sc-schema-campi" data-schema-campi="prodotto"' . ($s['tipo'] === 'prodotto' ? '' : ' hidden') . '>'
        . $campo('prodotto][nome', 'Nome del prodotto', $p['nome'], ' maxlength="200" placeholder="per esempio: Vaso in terracotta, 30 cm"')
        . '<div class="sc-riga">' . $campo('prodotto][prezzo', 'Prezzo', $p['prezzo'], ' inputmode="decimal" placeholder="per esempio: 24,90"') . $campo('prodotto][valuta', 'Valuta (tre lettere)', $p['valuta'] ?: 'EUR', ' maxlength="3" placeholder="EUR"') . '</div>'
        . '<div class="sc-riga"><div class="form-group"><label for="sc-schema-prodotto-disponibilita">Disponibilità</label><select name="schema[prodotto][disponibilita]" id="sc-schema-prodotto-disponibilita">';
    foreach (SEO_SCHEMA_DISPONIBILITA as $k => $n) $out .= '<option value="' . $k . '"' . $sel($p['disponibilita'], $k) . '>' . h($n) . '</option>';
    $out .= '</select></div>' . $campo('prodotto][codice', 'Codice (facoltativo)', $p['codice'], ' maxlength="60" placeholder="per esempio: VT-30"') . '</div>'
        . '<div class="sc-riga">' . $campo('prodotto][marca', 'Marca (facoltativa)', $p['marca'], ' maxlength="100"') . '<div class="form-group"><label for="sc-schema-prodotto-foto">Foto (vuota = l\'immagine in evidenza)</label><input type="text" name="schema[prodotto][foto]" id="sc-schema-prodotto-foto" value="' . h($p['foto']) . '" data-immagine maxlength="500" placeholder="/uploads/..."></div></div>'
        . '<div class="hint">Google mostra prezzo e disponibilità accanto al risultato. La descrizione è quella per Google della pagina.</div></div>';
    // How-to
    $hw = $s['howto'];
    $out .= '<div class="sc-schema-campi" data-schema-campi="howto"' . ($s['tipo'] === 'howto' ? '' : ' hidden') . '>'
        . '<div class="sc-riga">' . $campo('howto][nome', 'Nome della guida (cosa si impara a fare)', $hw['nome'], ' maxlength="200" placeholder="per esempio: Come rinvasare una pianta"') . $campo('howto][tempo', 'Tempo totale in minuti (facoltativo)', $hw['tempo'], ' inputmode="numeric" placeholder="per esempio: 30"') . '</div>'
        . '<label>I passi (fino a ' . SEO_SCHEMA_PASSI_MAX . ')</label><div class="sc-schema-passi" id="sc-schema-passi">';
    $passi = $hw['passi'] ?: [['nome' => '', 'testo' => '']];
    foreach ($passi as $i => $ps) {
        $out .= '<div class="sc-schema-passo"><span class="sc-schema-n">' . ($i + 1) . '</span><div><input type="text" name="schema[howto][passi][' . $i . '][nome]" value="' . h($ps['nome']) . '" maxlength="200" placeholder="Nome del passo" aria-label="Nome del passo ' . ($i + 1) . '"><textarea name="schema[howto][passi][' . $i . '][testo]" rows="2" maxlength="1000" placeholder="Cosa si fa in questo passo" aria-label="Testo del passo ' . ($i + 1) . '">' . h($ps['testo']) . '</textarea></div><button type="button" class="btn btn-secondary btn-sm" data-schema-togli-passo aria-label="Togli il passo ' . ($i + 1) . '">Togli</button></div>';
    }
    $out .= '</div><button type="button" class="btn btn-secondary btn-sm" data-schema-aggiungi-passo>Aggiungi un passo</button>'
        . '<div class="hint">Un passo per riga: il nome breve e, sotto, cosa si fa. Vanno a Google nell\'ordine in cui li scrivi.</div></div>';
    // Video
    $vd = $s['video'];
    $out .= '<div class="sc-schema-campi" data-schema-campi="video"' . ($s['tipo'] === 'video' ? '' : ' hidden') . '>'
        . $campo('video][nome', 'Nome del video', $vd['nome'], ' maxlength="200"')
        . '<div class="form-group"><label for="sc-schema-video-descrizione">Descrizione del video</label><textarea name="schema[video][descrizione]" id="sc-schema-video-descrizione" rows="2" maxlength="1000">' . h($vd['descrizione']) . '</textarea></div>'
        . '<div class="sc-riga"><div class="form-group"><label for="sc-schema-video-miniatura">Miniatura (un\'immagine)</label><input type="text" name="schema[video][miniatura]" id="sc-schema-video-miniatura" value="' . h($vd['miniatura']) . '" data-immagine maxlength="500" placeholder="/uploads/..."></div>' . $campo('video][data', 'Data di caricamento', $vd['data'], '', 'date') . '</div>'
        . '<div class="sc-riga">' . $campo('video][url', 'Indirizzo del file video (https o /uploads/video/...)', $vd['url'], ' maxlength="500" placeholder="https://..."') . $campo('video][incorporo', 'Oppure l\'indirizzo dell\'incorporo (https)', $vd['incorporo'], ' maxlength="500" placeholder="https://..."') . '</div>'
        . $campo('video][durata', 'Durata (minuti:secondi, facoltativa)', $vd['durata'] !== '' ? intdiv((int)$vd['durata'], 60) . ':' . str_pad((string)((int)$vd['durata'] % 60), 2, '0', STR_PAD_LEFT) : '', ' placeholder="per esempio: 1:30"')
        . '<div class="hint">Solo indirizzi https (o un video caricato nei Media). Miniatura e data sono obbligatorie per Google.</div></div>';
    // 2.35.0: Programma (SoftwareApplication)
    $pg = $s['programma'];
    $out .= '<div class="sc-schema-campi" data-schema-campi="programma"' . ($s['tipo'] === 'programma' ? '' : ' hidden') . '>'
        . '<div class="sc-riga">' . $campo('programma][nome', 'Nome del programma', $pg['nome'], ' maxlength="200" placeholder="per esempio: Agenda Studio"') . $campo('programma][versione', 'Versione (facoltativa)', $pg['versione'], ' maxlength="140" placeholder="per esempio: 2.1.0"') . '</div>'
        . '<div class="sc-riga"><div class="form-group"><label for="sc-schema-programma-categoria">Categoria</label><select name="schema[programma][categoria]" id="sc-schema-programma-categoria">';
    foreach (SEO_SCHEMA_CATEGORIE as $k => $n) $out .= '<option value="' . $k . '"' . $sel($pg['categoria'], $k) . '>' . h($n) . '</option>';
    $out .= '</select></div>' . $campo('programma][sistema', 'Dove funziona (facoltativo)', $pg['sistema'], ' maxlength="200" placeholder="per esempio: Windows, macOS, server con PHP"') . '</div>'
        . '<div class="sc-riga">' . $campo('programma][prezzo', 'Prezzo (0 se è gratuito)', $pg['prezzo'] === '0.00' ? '0' : $pg['prezzo'], ' inputmode="decimal" placeholder="per esempio: 0"') . $campo('programma][valuta', 'Valuta (tre lettere)', $pg['valuta'] ?: 'EUR', ' maxlength="3" placeholder="EUR"') . '</div>'
        . '<div class="sc-riga">' . $campo('programma][licenza', 'Indirizzo della licenza (facoltativo)', $pg['licenza'], ' maxlength="500" placeholder="https://..."') . $campo('programma][download', 'Indirizzo per scaricarlo (facoltativo)', $pg['download'], ' maxlength="500" placeholder="https://... oppure /scarica/..."') . '</div>'
        . $campo('programma][requisiti', 'Cosa serve per usarlo (facoltativo)', $pg['requisiti'], ' maxlength="300" placeholder="per esempio: PHP 8.2 e MySQL 8"')
        . '<div class="hint">Dice a Google e alle intelligenze artificiali che questa pagina presenta un programma. La descrizione è quella per Google della pagina. Le stelle delle recensioni non si scrivono qui: Google le vuole solo vere.</div></div>';
    return $out . '</div>';
}
