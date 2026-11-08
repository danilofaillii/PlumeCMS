<?php
/**
 * Addon AI collegate: le foto. Ricerca negli archivi con licenza chiara anche per uso commerciale e importazione nei Media.
 *  - Openverse (api.openverse.org, senza chiave): solo CC0, pubblico dominio e CC BY, con license_type=commercial.
 *  - Pexels e Unsplash: solo con la chiave gratuita che l'amministratore scrive nel pannello (mai nel repository).
 * Si scarica solo da host in elenco (quelli degli archivi e dei loro CDN), con i controlli del CMS sulle chiamate in uscita
 * (seo_http: host ammessi, mai indirizzi privati, IP fissato, nessun redirect), e il file passa da media_carica() (tipo letto
 * dal file, ricodifica, controllo del codice). Niente SVG. Autore e licenza restano nella tabella ai_foto.
 * Per le prove: CMS_AI_FOTO_PROVA nel config (per esempio 'http://127.0.0.1:8930') manda tutto al server finto
 * prove/foto-finte.php; solo quell'host e quella porta, nient'altro di interno. Manuale tecnico, 47.6.
 */
require_once __DIR__ . '/ai.php';

const AIC_FOTO_API = [
    'openverse' => 'https://api.openverse.org/v1/images/',
    'pexels' => 'https://api.pexels.com/v1/',
    'unsplash' => 'https://api.unsplash.com/',
];
/** Gli host da cui si scarica un file, per archivio (le foto di Openverse stanno sui siti d'origine: i più comuni). */
const AIC_FOTO_HOST = [
    'openverse' => ['api.openverse.org', 'live.staticflickr.com', 'farm1.staticflickr.com', 'farm2.staticflickr.com', 'farm3.staticflickr.com', 'farm4.staticflickr.com', 'farm5.staticflickr.com', 'farm6.staticflickr.com', 'farm66.staticflickr.com', 'upload.wikimedia.org', 'images.rawpixel.com'],
    'pexels' => ['images.pexels.com', 'api.pexels.com'],
    'unsplash' => ['images.unsplash.com', 'api.unsplash.com'],
];
const AIC_FOTO_LICENZE_OPENVERSE = ['cc0', 'pdm', 'by'];
const AIC_FOTO_MAX_BYTE = 5000000;

function aic_foto_prova(): string {
    return defined('CMS_AI_FOTO_PROVA') && is_string(CMS_AI_FOTO_PROVA) && preg_match('~^https?://[a-z0-9.-]+(:\d+)?$~i', CMS_AI_FOTO_PROVA) ? rtrim(CMS_AI_FOTO_PROVA, '/') : '';
}

/** L'indirizzo dell'API di un archivio (in prova: il server finto). */
function aic_foto_api(string $archivio): string {
    $p = aic_foto_prova();
    return $p !== '' ? $p . '/' . $archivio . '/' : AIC_FOTO_API[$archivio];
}

function aic_foto_archivi_attivi(): array {
    $s = aic_impostazioni();
    return array_values(array_filter(['openverse', $s['pexels'] !== '' ? 'pexels' : '', $s['unsplash'] !== '' ? 'unsplash' : '']));
}

/**
 * Una chiamata in uscita. In prova (CMS_AI_FOTO_PROVA) solo verso quell'host e quella porta, senza il controllo degli
 * indirizzi privati; ogni altro indirizzo passa da seo_http (host in elenco, mai privati). Ritorna ['codice', 'corpo', 'errore', 'tipo'].
 */
function aic_foto_http(string $url, array $host, array $intest = [], int $max = 1000000): array {
    $p = aic_foto_prova();
    $u = parse_url($url);
    if (!$u || empty($u['host']) || !in_array(strtolower((string)($u['scheme'] ?? '')), ['http', 'https'], true) || isset($u['user']) || isset($u['pass'])) return ['codice' => 0, 'corpo' => '', 'errore' => 'Indirizzo non valido.', 'tipo' => ''];
    $hostUrl = strtolower((string)$u['host']);
    if ($p !== '') {
        $pp = parse_url($p);
        $stesso = $hostUrl === strtolower((string)$pp['host']) && (int)($u['port'] ?? 80) === (int)($pp['port'] ?? 80);
        if ($stesso) {
            if (!function_exists('curl_init')) return ['codice' => 0, 'corpo' => '', 'errore' => 'manca curl', 'tipo' => ''];
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $intest, CURLOPT_MAXFILESIZE => $max, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]);
            $r = curl_exec($ch);
            $out = ['codice' => $r === false ? 0 : (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'corpo' => $r === false ? '' : (string)$r, 'errore' => $r === false ? curl_error($ch) : '', 'tipo' => (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE)];
            curl_close($ch);
            return $out;
        }
    }
    if (!in_array($hostUrl, $host, true)) return ['codice' => 0, 'corpo' => '', 'errore' => 'Host non in elenco per questo archivio: ' . $hostUrl . '.', 'tipo' => ''];
    if (strtolower((string)$u['scheme']) !== 'https') return ['codice' => 0, 'corpo' => '', 'errore' => 'Solo indirizzi https.', 'tipo' => ''];
    $r = seo_http($url, 'GET', null, $intest, 15, 'plumeCMS/' . cms_versione() . ' (' . cms_url_sito() . ')', [$hostUrl]);
    return $r + ['tipo' => ''];
}

/** Un indirizzo che arriva da un archivio: solo https (http solo dal server finto delle prove), senza virgolette né spazi. */
function aic_foto_url($u): string {
    $u = is_string($u) ? trim($u) : '';
    if ($u === '' || strlen($u) > 500 || preg_match('/[\s"\'<>\\\\]/', $u)) return '';
    $p = aic_foto_prova();
    if (str_starts_with($u, 'https://') || ($p !== '' && str_starts_with($u, $p . '/'))) return $u;
    return '';
}

function aic_foto_orientamento(string $o, string $archivio): string {
    $m = ['openverse' => ['orizzontale' => 'wide', 'verticale' => 'tall', 'quadrata' => 'square'], 'pexels' => ['orizzontale' => 'landscape', 'verticale' => 'portrait', 'quadrata' => 'square'], 'unsplash' => ['orizzontale' => 'landscape', 'verticale' => 'portrait', 'quadrata' => 'squarish']];
    return $m[$archivio][$o] ?? '';
}

/** Una foto di Openverse nel formato comune, o null se la licenza non è fra quelle ammesse. */
function aic_foto_da_openverse(array $x): ?array {
    $lic = strtolower((string)($x['license'] ?? ''));
    if (!in_array($lic, AIC_FOTO_LICENZE_OPENVERSE, true)) return null;
    $nomeLic = ['cc0' => 'CC0 1.0', 'pdm' => 'Pubblico dominio', 'by' => 'CC BY ' . aic_testo($x['license_version'] ?? '', 10)][$lic];
    return ['archivio' => 'openverse', 'id' => aic_testo($x['id'] ?? '', 100), 'titolo' => aic_testo($x['title'] ?? '', 300), 'anteprima' => aic_foto_url($x['thumbnail'] ?? ''),
        'file' => aic_foto_url($x['url'] ?? ''), 'larghezza' => (int)($x['width'] ?? 0), 'altezza' => (int)($x['height'] ?? 0),
        'autore' => aic_testo($x['creator'] ?? '', 200), 'autore_url' => aic_foto_url($x['creator_url'] ?? ''), 'licenza' => trim($nomeLic), 'licenza_codice' => $lic,
        'licenza_url' => aic_foto_url($x['license_url'] ?? ''), 'fonte_url' => aic_foto_url($x['foreign_landing_url'] ?? ''),
        'attribuzione_obbligatoria' => $lic === 'by'];
}

function aic_foto_da_pexels(array $x): array {
    return ['archivio' => 'pexels', 'id' => (string)(int)($x['id'] ?? 0), 'titolo' => aic_testo($x['alt'] ?? '', 300), 'anteprima' => aic_foto_url($x['src']['medium'] ?? ''),
        'file' => aic_foto_url($x['src']['large2x'] ?? ($x['src']['large'] ?? '')), 'larghezza' => (int)($x['width'] ?? 0), 'altezza' => (int)($x['height'] ?? 0),
        'autore' => aic_testo($x['photographer'] ?? '', 200), 'autore_url' => aic_foto_url($x['photographer_url'] ?? ''), 'licenza' => 'Licenza Pexels', 'licenza_codice' => 'pexels',
        'licenza_url' => 'https://www.pexels.com/license/', 'fonte_url' => aic_foto_url($x['url'] ?? ''), 'attribuzione_obbligatoria' => false];
}

function aic_foto_da_unsplash(array $x): array {
    return ['archivio' => 'unsplash', 'id' => aic_testo($x['id'] ?? '', 100), 'titolo' => aic_testo($x['alt_description'] ?? ($x['description'] ?? ''), 300), 'anteprima' => aic_foto_url($x['urls']['small'] ?? ''),
        'file' => aic_foto_url($x['urls']['regular'] ?? ''), 'larghezza' => (int)($x['width'] ?? 0), 'altezza' => (int)($x['height'] ?? 0),
        'autore' => aic_testo($x['user']['name'] ?? '', 200), 'autore_url' => aic_foto_url($x['user']['links']['html'] ?? ''), 'licenza' => 'Licenza Unsplash', 'licenza_codice' => 'unsplash',
        'licenza_url' => 'https://unsplash.com/license', 'fonte_url' => aic_foto_url($x['links']['html'] ?? ''), 'attribuzione_obbligatoria' => false,
        '_scarica' => aic_foto_url($x['links']['download_location'] ?? '')];
}

function aic_foto_intestazioni(string $archivio): array {
    $s = aic_impostazioni();
    if ($archivio === 'pexels') return ['Authorization: ' . $s['pexels'], 'Accept: application/json'];
    if ($archivio === 'unsplash') return ['Authorization: Client-ID ' . $s['unsplash'], 'Accept-Version: v1', 'Accept: application/json'];
    return ['Accept: application/json'];
}

function aic_foto_cerca(array $a): array {
    $q = aic_testo($a['query'] ?? '', 100);
    if (mb_strlen($q) < 2) throw new AicErrore('Scrivi cosa cercare in "query" (almeno due lettere, meglio in inglese per gli archivi).');
    $quante = max(1, min(20, (int)($a['quante'] ?? 8)));
    $or = (string)($a['orientamento'] ?? '');
    $attivi = aic_foto_archivi_attivi();
    $chiesto = (string)($a['archivio'] ?? 'tutti');
    if ($chiesto !== 'tutti' && $chiesto !== '') {
        if (!in_array($chiesto, ['openverse', 'pexels', 'unsplash'], true)) throw new AicErrore('Archivio sconosciuto: usa openverse, pexels, unsplash o tutti.');
        if (!in_array($chiesto, $attivi, true)) throw new AicErrore('L\'archivio ' . $chiesto . ' non è attivo: l\'amministratore deve scrivere la sua chiave in AI collegate > Impostazioni.');
        $attivi = [$chiesto];
    }
    $foto = []; $errori = [];
    foreach ($attivi as $arch) {
        $qs = match ($arch) {
            'openverse' => ['q' => $q, 'license' => implode(',', AIC_FOTO_LICENZE_OPENVERSE), 'license_type' => 'commercial', 'page_size' => $quante, 'mature' => 'false'] + (($o = aic_foto_orientamento($or, $arch)) !== '' ? ['aspect_ratio' => $o] : []),
            'pexels' => ['query' => $q, 'per_page' => $quante] + (($o = aic_foto_orientamento($or, $arch)) !== '' ? ['orientation' => $o] : []),
            'unsplash' => ['query' => $q, 'per_page' => $quante, 'content_filter' => 'high'] + (($o = aic_foto_orientamento($or, $arch)) !== '' ? ['orientation' => $o] : []),
        };
        $url = aic_foto_api($arch) . ['openverse' => '', 'pexels' => 'search', 'unsplash' => 'search/photos'][$arch] . '?' . http_build_query($qs);
        $r = aic_foto_http($url, AIC_FOTO_HOST[$arch], aic_foto_intestazioni($arch));
        $j = $r['codice'] === 200 ? json_decode($r['corpo'], true) : null;
        if (!is_array($j)) { $errori[] = $arch . ': ' . ($r['errore'] !== '' ? $r['errore'] : 'risposta ' . $r['codice']); continue; }
        foreach (array_slice((array)($j['results'] ?? $j['photos'] ?? []), 0, $quante) as $x) {
            if (!is_array($x)) continue;
            $f = match ($arch) { 'openverse' => aic_foto_da_openverse($x), 'pexels' => aic_foto_da_pexels($x), 'unsplash' => aic_foto_da_unsplash($x) };
            if ($f && $f['id'] !== '') { unset($f['_scarica']); $foto[] = $f; }
        }
    }
    if (!$foto && $errori) throw new AicErrore('Gli archivi di foto non hanno risposto: ' . implode('; ', $errori) . '.', 502);
    return ['foto' => $foto, 'archivi' => $attivi, 'avvisi' => $errori, 'nota' => 'Per usarne una chiama import_photo con archivio e id. Con licenza CC BY va citato l\'autore (didascalia o crediti).', '_registro' => count($foto) . ' foto per "' . $q . '"'];
}

/** I dati di una foto dall'archivio, per id (la licenza si rilegge sempre dall'archivio, mai dall'AI). */
function aic_foto_dettagli(string $arch, string $id): array {
    if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/', $id)) throw new AicErrore('Id della foto non valido.');
    if (!in_array($arch, aic_foto_archivi_attivi(), true)) throw new AicErrore('L\'archivio ' . $arch . ' non è attivo su questo sito.');
    $url = aic_foto_api($arch) . ['openverse' => rawurlencode($id) . '/', 'pexels' => 'photos/' . rawurlencode($id), 'unsplash' => 'photos/' . rawurlencode($id)][$arch];
    $r = aic_foto_http($url, AIC_FOTO_HOST[$arch], aic_foto_intestazioni($arch));
    $j = $r['codice'] === 200 ? json_decode($r['corpo'], true) : null;
    if (!is_array($j)) throw new AicErrore('L\'archivio non ha dato la foto ' . $id . ($r['errore'] !== '' ? ' (' . $r['errore'] . ')' : ' (risposta ' . $r['codice'] . ')') . '.', 502);
    $f = match ($arch) { 'openverse' => aic_foto_da_openverse($j), 'pexels' => aic_foto_da_pexels($j), 'unsplash' => aic_foto_da_unsplash($j) };
    if (!$f) throw new AicErrore('La licenza di questa foto non permette l\'uso commerciale senza condizioni: scegline un\'altra.');
    return $f;
}

function aic_foto_importa(array $conn, array $a): array {
    $arch = (string)($a['archivio'] ?? '');
    if (!isset(AIC_FOTO_API[$arch])) throw new AicErrore('Archivio sconosciuto: openverse, pexels o unsplash.');
    $alt = aic_testo($a['alt'] ?? '', 300);
    if (mb_strlen($alt) < 3) throw new AicErrore('Scrivi l\'alt: una frase che descrive la foto per chi non la vede.');
    $f = aic_foto_dettagli($arch, aic_testo($a['id'] ?? '', 100));
    $file = $f['file'];
    if (isset($a['url']) && trim((string)$a['url']) !== '') $file = trim((string)$a['url']);
    $hostFile = strtolower((string)parse_url($file, PHP_URL_HOST));
    $prova = aic_foto_prova();
    $inProva = $prova !== '' && str_starts_with($file, $prova . '/');
    if (!$inProva && !in_array($hostFile, AIC_FOTO_HOST[$arch], true)) {
        if (isset($a['url']) && trim((string)$a['url']) !== '') throw new AicErrore('L\'indirizzo del file non è di un host dell\'archivio ' . $arch . ': ' . mb_substr($hostFile, 0, 80) . '. Si scarica solo da: ' . implode(', ', AIC_FOTO_HOST[$arch]) . '.', 403);
        // Openverse: una foto ospitata su un sito non in elenco si prende dalla miniatura grande dell'archivio stesso (da verificare contro l'API vera).
        $file = $arch === 'openverse' ? aic_foto_api('openverse') . rawurlencode($f['id']) . '/thumb/?full_size=true' : '';
        if ($file === '') throw new AicErrore('Il file della foto sta su un host non in elenco: scegline un\'altra.');
    }
    if ($arch === 'unsplash' && ($dl = (string)($f['_scarica'] ?? '')) !== '') aic_foto_http($dl, AIC_FOTO_HOST['unsplash'], aic_foto_intestazioni('unsplash')); // le regole di Unsplash: si segnala lo scaricamento
    $r = aic_foto_http($file, AIC_FOTO_HOST[$arch], [], AIC_FOTO_MAX_BYTE);
    if ($r['codice'] !== 200 || $r['corpo'] === '') throw new AicErrore('Il file della foto non si è scaricato' . ($r['errore'] !== '' ? ': ' . $r['errore'] : ' (risposta ' . $r['codice'] . ')') . '.', 502);
    if (strlen($r['corpo']) > AIC_FOTO_MAX_BYTE) throw new AicErrore('La foto pesa più di 5 MB: scegline un\'altra.');
    $tmp = tempnam(sys_get_temp_dir(), 'aic');
    file_put_contents($tmp, $r['corpo']);
    $info = @getimagesize($tmp);
    $tipi = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($tipi[$info[2]])) { @unlink($tmp); throw new AicErrore('Il file scaricato non è una foto JPEG, PNG o WebP: non l\'ho importato.'); }
    $nome = (aic_slug($f['titolo'] ?: $alt) ?: 'foto') . '.' . $tipi[$info[2]];
    require_once cms_radice() . '/includes/media.php';
    $m = media_carica(['name' => $nome, 'type' => '', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp), 'da_server' => true], 'pagine');
    @unlink($tmp);
    if (!isset($m['url'])) throw new AicErrore('La foto non si è salvata nei Media: ' . ($m['errore'] ?? 'errore sconosciuto') . '.');
    // L'alt in libreria (la foto è nuova: nessuna pagina la usa ancora). Scritto qui perché media_elenco() della richiesta non la conosce.
    require_once cms_radice() . '/includes/media-alt.php';
    media_alt_schema();
    $k = media_alt_chiave($m['url']);
    db()->prepare('INSERT INTO ' . tbl('media_alt') . ' (percorso, alt, aggiornato) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE alt = VALUES(alt), aggiornato = NOW()')->execute([$k, media_alt_pulito($alt)]);
    save_setting('media_alt_n', (string)(int)db()->query('SELECT COUNT(*) FROM ' . tbl('media_alt') . " WHERE alt <> ''")->fetchColumn());
    aic_schema();
    db()->prepare('INSERT INTO ' . tbl('ai_foto') . ' (url, archivio, foto_id, titolo, autore, autore_url, licenza, licenza_url, fonte_url, connessione_id, creato) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$m['url'], $arch, $f['id'], $f['titolo'], $f['autore'], $f['autore_url'], $f['licenza'], $f['licenza_url'], $f['fonte_url'], (int)$conn['id']]);
    $attr = trim(($f['titolo'] !== '' ? '"' . $f['titolo'] . '" di ' : 'Foto di ') . ($f['autore'] ?: 'autore sconosciuto') . ', ' . $f['licenza'] . ($f['fonte_url'] !== '' ? ' (' . $f['fonte_url'] . ')' : ''));
    $out = ['url' => $m['url'], 'alt' => media_alt_pulito($alt), 'larghezza' => $m['larghezza'] ?? null, 'altezza' => $m['altezza'] ?? null, 'archivio' => $arch, 'autore' => $f['autore'], 'licenza' => $f['licenza'],
        'licenza_url' => $f['licenza_url'], 'fonte_url' => $f['fonte_url'], 'attribuzione' => $attr, 'attribuzione_obbligatoria' => $f['attribuzione_obbligatoria'], '_registro' => 'foto importata: ' . $m['url']];
    // "per": la foto come immagine in evidenza. Su una bozza subito; su un contenuto pubblicato diventa una proposta.
    if (is_array($a['per'] ?? null)) {
        require_once __DIR__ . '/strumenti.php';
        $tipo = aic_tipo($a['per']['tipo'] ?? null);
        $r = aic_riga($tipo, $a['per']['id'] ?? 0);
        if (contenuti_stato($tipo, $r) === 'bozza') {
            if (!in_array('bozze', $conn['permessi'], true)) throw new AicErrore('La foto è nei Media (' . $m['url'] . '), ma per metterla nella bozza serve il permesso "bozze".', 403);
            $base = array_replace(aic_base_da_riga($tipo, $r), ['immagine' => $m['url'], 'stato' => 'bozza'] + (contenuti_tipo($tipo)['attributi'] ? ['immagine_alt' => media_alt_pulito($alt)] : []));
            contenuti_salva($tipo, (int)$r['id'], $base, aic_autore($conn));
            $out['messa_in'] = ['tipo' => $tipo, 'id' => (int)$r['id'], 'come' => 'immagine in evidenza della bozza'];
        } else {
            if (!in_array('proposte', $conn['permessi'], true)) throw new AicErrore('La foto è nei Media (' . $m['url'] . '), ma il contenuto è pubblicato e per proporla serve il permesso "proposte".', 403);
            require_once __DIR__ . '/proposte.php';
            $p = aic_proposta_crea($conn, ['tipo' => $tipo, 'id' => (int)$r['id'], 'campo' => 'immagine', 'valore' => ['url' => $m['url'], 'alt' => $alt], 'motivo' => 'Foto importata da ' . $arch . ' per questo contenuto: ' . $attr]);
            $out['proposta'] = $p['id'];
        }
    }
    return $out;
}
