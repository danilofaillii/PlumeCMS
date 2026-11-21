<?php
/**
 * La libreria dei media: tutto quello che il sito può mostrare, da dove viene.
 *
 *   <immagini>/AAAA/MM/      l'archivio delle immagini per anno e mese (e le loro miniature -300x300)
 *   <immagini>/contenuti/    le immagini dei contenuti, arrivate con i pacchetti
 *   <immagini>/pagine/       quelle caricate dall'editor delle pagine
 *   <immagini>/newsletter/   quelle delle email
 *   assets/img/articles/     le copertine SVG generate dal pannello (nella cartella del CMS)
 *   assets/img/uploads/      i caricamenti vecchi del pannello
 * <immagini> è cms_uploads_dir(): uploads/ nella cartella del CMS, oppure dove dice CMS_UPLOADS.
 *
 * Usata dalla voce Media del pannello e dalla finestra "Scegli" dell'editor.
 * Dalla 1.4.0 le foto caricate passano da foto_alleggerisci() (includes/foto.php): lato massimo, orientamento, WebP accanto.
 */
require_once __DIR__ . '/foto.php';

const MEDIA_ESTENSIONI = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'];
define('MEDIA_RADICE_UPLOADS', cms_uploads_url());        // le immagini caricate (anche fuori dalla cartella del CMS)
define('MEDIA_RADICE_PANNELLO', cms_base() . '/assets/img'); // le immagini generate dal pannello

/** Le cartelle di primo livello, con l'etichetta che si mostra. */
function media_cartelle(): array {
    return [
        'uploads' => 'Archivio (per anno)',
        'contenuti' => 'Pillar e contenuti',
        'pagine' => 'Editor delle pagine',
        'newsletter' => 'Newsletter',
        'articles' => 'Copertine articoli',
        'pannello' => 'Pannello (vecchi caricamenti)',
        'segnaposto' => 'Da sostituire', // 1.7.0: le immagini finte del sito su misura (includes/sito-su-misura.php)
    ];
}

/** Miniatura generata dal sito di prima: stesso nome con -300x300, -768x768 e simili. */
function media_e_miniatura(string $nome): bool {
    return (bool)preg_match('/-\d{2,4}x\d{2,4}(?:-\d+)?\.[a-z0-9]+$/i', $nome);
}

/**
 * Tutti i media, dal più recente. Ogni voce: url (pubblico, assoluto nel sito), nome, cartella (chiave di
 * media_cartelle), sotto (il percorso dentro la cartella), peso, tempo, miniatura, svg.
 * Si legge una volta per richiesta: con anni di archivio sono migliaia di file.
 */
function media_elenco(): array {
    static $tutti = null;
    if ($tutti !== null) return $tutti;
    $tutti = [];
    $aggiungi = function (string $dir, string $urlBase, string $cartella, bool $ricorsivo, string $prefisso = '') use (&$tutti) {
        if (!is_dir($dir)) return;
        $it = $ricorsivo
            ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS))
            : new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS);
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $ext = strtolower($f->getExtension());
            if (!in_array($ext, MEDIA_ESTENSIONI, true)) continue;
            if (foto_e_webp_compagna($f->getFilename())) continue; // la WebP di una foto (1.4.0): si mostra solo l'originale
            $rel = substr($f->getPathname(), strlen($dir) + 1);
            $tutti[] = [
                'url' => $urlBase . '/' . str_replace('\\', '/', $rel),
                'nome' => $f->getFilename(), 'cartella' => $cartella, 'sotto' => trim($prefisso . '/' . (dirname($rel) === '.' ? '' : str_replace('\\', '/', dirname($rel))), '/'),
                'peso' => $f->getSize(), 'tempo' => $f->getMTime(),
                'miniatura' => media_e_miniatura($f->getFilename()), 'svg' => $ext === 'svg',
            ];
        }
    };
    // sicurezza (attacco 3): la cartella vera delle immagini, la stessa che proteggono uploads/.htaccess e la pagina Sicurezza.
    $up = cms_uploads_dir();
    if (is_dir($up)) {
        foreach (new FilesystemIterator($up, FilesystemIterator::SKIP_DOTS) as $d) {
            if (!$d->isDir()) continue;
            $n = $d->getFilename();
            // cache di miniature di un vecchio plugin: non è un media del sito.
            if ($n === 'elementor') continue;
            $anno = (bool)preg_match('/^\d{4}$/', $n);
            $cartella = $anno ? 'uploads' : (isset(media_cartelle()[$n]) ? $n : 'uploads');
            $aggiungi($d->getPathname(), MEDIA_RADICE_UPLOADS . '/' . $n, $cartella, true, $anno ? $n : '');
        }
    }
    $aggiungi(cms_radice() . '/assets/img/articles', MEDIA_RADICE_PANNELLO . '/articles', 'articles', false);
    $aggiungi(cms_radice() . '/assets/img/uploads', MEDIA_RADICE_PANNELLO . '/uploads', 'pannello', false);
    usort($tutti, fn($a, $b) => $b['tempo'] <=> $a['tempo'] ?: strcmp($a['url'], $b['url']));
    return $tutti;
}

/** Filtro: cartella, testo nel nome, con o senza le miniature. */
function media_filtra(string $cartella = '', string $cerca = '', bool $miniature = false): array {
    $cerca = mb_strtolower(trim($cerca));
    return array_values(array_filter(media_elenco(), function ($m) use ($cartella, $cerca, $miniature) {
        if ($cartella !== '' && $m['cartella'] !== $cartella) return false;
        if (!$miniature && $m['miniatura']) return false;
        if ($cerca !== '' && !str_contains(mb_strtolower($m['url']), $cerca)) return false;
        return true;
    }));
}

/**
 * Carica un'immagine in <cartella delle immagini>/<cartella>/AAAA/MM/ (cms_uploads_dir(); può stare fuori dalla cartella del CMS). Ritorna ['url' => ...] oppure ['errore' => ...].
 * Il tipo si legge dal file, non dall'estensione dichiarata. SVG accettato solo senza script.
 */
function media_carica(?array $f, string $cartella = 'pagine', int $maxMb = 8, string $titolo = ''): array {
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['errore' => 'Caricamento non riuscito (limite del server: ' . ini_get('upload_max_filesize') . ').'];
    if ($f['size'] > $maxMb * 1048576) return ['errore' => "Immagine troppo pesante: massimo $maxMb MB."];
    $tipi = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp', IMAGETYPE_AVIF => 'avif'];
    $info = @getimagesize($f['tmp_name']);
    $ext = null;
    if ($info && isset($tipi[$info[2]])) $ext = $tipi[$info[2]];
    elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) === 'svg') {
        // sicurezza: l'SVG passa dal parser XML e si riscrive senza script, eventi, foreignObject, riferimenti esterni ed entita.
        require_once __DIR__ . '/sicurezza-html.php';
        $testo = (string)file_get_contents($f['tmp_name'], false, null, 0, 2_000_000);
        $pulito = sicurezza_svg_pulito($testo);
        if ($pulito === null) return ['errore' => 'Il file SVG non è leggibile (o contiene entita XML non ammesse).'];
        $svgPulito = $pulito;
        $ext = 'svg';
    }
    if ($ext === null) return ['errore' => 'Formati accettati: JPG, PNG, GIF, WebP, AVIF, SVG.'];
    $cartella = preg_replace('/[^a-z0-9-]/', '', $cartella) ?: 'pagine';
    $rel = $cartella . '/' . date('Y/m');
    $dir = cms_uploads_dir() . '/' . $rel;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return ['errore' => 'La cartella delle immagini non è scrivibile.'];
    // sicurezza: nome leggibile più un suffisso casuale (nessuno indovina l'indirizzo di un file dal suo nome originale).
    $base = trim(substr(preg_replace('/[^a-z0-9-]+/', '-', strtolower(pathinfo($f['name'], PATHINFO_FILENAME))), 0, 60), '-') ?: 'immagine';
    // 2.38.0: un nome da macchina fotografica (IMG_20261108_123456, DSC0001...) con un titolo scritto prende il nome dal titolo.
    if (trim($titolo) !== '' && is_file(__DIR__ . '/media-dati.php')) {
        require_once __DIR__ . '/media-dati.php';
        if (function_exists('media_dati_nome_da_fotocamera') && media_dati_nome_da_fotocamera((string)$f['name']) && ($slugTitolo = media_dati_slug($titolo)) !== '') $base = $slugTitolo;
    }
    $nome = $base . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
    while (file_exists("$dir/$nome")) $nome = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
    // 2.32.0: 'da_server' => true per un file scaricato dal CMS stesso (le foto dell'addon AI collegate), che non arriva da un
    // modulo: si sposta con rename, solo dalla cartella temporanea di sistema. $_FILES non può avere questa chiave.
    $daServer = ($f['da_server'] ?? false) === true && str_starts_with((string)realpath((string)$f['tmp_name']), rtrim((string)realpath(sys_get_temp_dir()), '/') . '/');
    if (!($daServer ? @rename($f['tmp_name'], "$dir/$nome") || @copy($f['tmp_name'], "$dir/$nome") : move_uploaded_file($f['tmp_name'], "$dir/$nome"))) return ['errore' => 'Il file non si è salvato.'];
    if ($ext === 'svg') file_put_contents("$dir/$nome", $svgPulito);
    else {
        // 1.4.0: foto più leggere (lato massimo, orientamento, WebP accanto); se non si può, la ripulitura di sempre.
        $foto = foto_alleggerisci("$dir/$nome", $ext);
        if (!$foto['fatto']) {
            require_once __DIR__ . '/sicurezza-html.php';
            // via metadati e qualunque cosa nascosta dopo i pixel. Se nemmeno questo riesce (un falso, una GIF animata, un
            // server senza GD) il file resta com'è: solo se dentro non c'è codice (1.7.0).
            if (!sicurezza_immagine_ricodifica("$dir/$nome", $ext) && media_ha_codice("$dir/$nome")) {
                @unlink("$dir/$nome");
                return ['errore' => 'Il file non è un\'immagine pulita (contiene codice): non è stato caricato.'];
            }
        }
        elseif ($foto['larghezza']) { $info[0] = $foto['larghezza']; $info[1] = $foto['altezza']; }
    }
    @chmod("$dir/$nome", 0644);
    return ['url' => MEDIA_RADICE_UPLOADS . '/' . $rel . '/' . $nome, 'larghezza' => $info[0] ?? null, 'altezza' => $info[1] ?? null, 'webp' => !empty($foto['webp'])];
}

/** Il file contiene codice PHP o uno script? Letto a pezzi, con la coda del pezzo prima (un'apertura spezzata a metà). */
function media_ha_codice(string $f): bool {
    $h = @fopen($f, 'rb');
    if (!$h) return true;
    $coda = '';
    while (!feof($h)) {
        $pezzo = $coda . (string)fread($h, 1048576);
        if (preg_match('/<\?(php|=)|<script\b/i', $pezzo)) { fclose($h); return true; }
        $coda = substr($pezzo, -16);
    }
    fclose($h);
    return false;
}

/** Cancella un file della libreria. Solo dentro le cartelle conosciute. */
function media_elimina(string $url): bool {
    // Solo dentro le cartelle conosciute: le immagini caricate (cms_uploads_url) e le due del pannello.
    if (str_contains($url, '..') || !preg_match('~^[A-Za-z0-9_./ -]+$~', $url)) return false;
    $radici = [MEDIA_RADICE_UPLOADS => cms_uploads_dir(), MEDIA_RADICE_PANNELLO . '/articles' => cms_radice() . '/assets/img/articles', MEDIA_RADICE_PANNELLO . '/uploads' => cms_radice() . '/assets/img/uploads'];
    foreach ($radici as $u => $d) {
        if ($u === '' || !str_starts_with($url, rtrim($u, '/') . '/')) continue;
        $p = $d . substr($url, strlen(rtrim($u, '/')));
        if (is_file($p . '.webp') && foto_e_webp_compagna(basename($p) . '.webp')) @unlink($p . '.webp'); // con la sua WebP (1.4.0)
        return is_file($p) && @unlink($p);
    }
    return false;
}

// ------------------------------------------------------------
// 2.17.0: i video del sito (MP4 e WebM), per l'Apertura "Video". Stanno in <immagini>/video/AAAA/MM/, si caricano da
// Media o dalla finestra Scegli dell'editor, si vedono con <video> (niente servizi esterni). Restano fuori da
// media_elenco(), che è delle immagini: le finestre delle foto non li mostrano.
// ------------------------------------------------------------
const MEDIA_VIDEO = ['mp4' => 'video/mp4', 'webm' => 'video/webm'];

/** I video caricati, dal più recente: url, nome, sotto (AAAA/MM), peso, tempo. */
function media_video_elenco(string $cerca = ''): array {
    $dir = cms_uploads_dir() . '/video';
    $out = [];
    if (is_dir($dir)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
            if (!$f->isFile() || !isset(MEDIA_VIDEO[strtolower($f->getExtension())])) continue;
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($dir) + 1));
            $out[] = ['url' => MEDIA_RADICE_UPLOADS . '/video/' . $rel, 'nome' => $f->getFilename(), 'sotto' => dirname($rel) === '.' ? '' : dirname($rel),
                'peso' => $f->getSize(), 'tempo' => $f->getMTime()];
        }
    }
    $cerca = mb_strtolower(trim($cerca));
    if ($cerca !== '') $out = array_values(array_filter($out, fn($v) => str_contains(mb_strtolower($v['nome']), $cerca)));
    usort($out, fn($a, $b) => $b['tempo'] <=> $a['tempo'] ?: strcmp($a['url'], $b['url']));
    return $out;
}

/** Il file è davvero un MP4 o un WebM? Si guarda l'inizio del file, non il nome né il tipo dichiarato dal browser. */
function media_video_tipo(string $percorso): ?string {
    $t = (string)@file_get_contents($percorso, false, null, 0, 16);
    if (strlen($t) >= 12 && substr($t, 4, 4) === 'ftyp') return 'mp4';
    if (str_starts_with($t, "\x1A\x45\xDF\xA3")) return 'webm';
    return null;
}

/** Carica un video in <immagini>/video/AAAA/MM/. Ritorna ['url' => ...] oppure ['errore' => ...]. */
function media_carica_video(?array $f, int $maxMb = 64): array {
    $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) return ['errore' => 'Il video supera il limite di caricamento del server (' . ini_get('upload_max_filesize') . '). Accorcialo o rimpiccioliscilo, oppure chiedi al fornitore dello spazio di alzare il limite.'];
    if (!$f || $err !== UPLOAD_ERR_OK) return ['errore' => 'Caricamento del video non riuscito: riprova.'];
    if ((int)$f['size'] > $maxMb * 1048576) return ['errore' => "Video troppo pesante: massimo $maxMb MB."];
    $ext = media_video_tipo((string)$f['tmp_name']);
    $dichiarata = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    if ($ext === null || !isset(MEDIA_VIDEO[$dichiarata])) return ['errore' => 'Formati accettati per i video: MP4 e WebM.'];
    // Niente media_ha_codice(): in qualche MB di dati compressi l'apertura di PHP capita per caso. Il nome ha solo .mp4 o .webm
    // (scelto qui, mai quello del browser) e la cartella delle immagini non esegue PHP (uploads/.htaccess).
    $rel = 'video/' . date('Y/m');
    $dir = cms_uploads_dir() . '/' . $rel;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return ['errore' => 'La cartella dei media non è scrivibile.'];
    $base = trim(substr(preg_replace('/[^a-z0-9-]+/', '-', strtolower(pathinfo((string)$f['name'], PATHINFO_FILENAME))), 0, 60), '-') ?: 'video';
    $nome = $base . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
    while (file_exists("$dir/$nome")) $nome = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
    if (!is_uploaded_file((string)$f['tmp_name']) || !move_uploaded_file((string)$f['tmp_name'], "$dir/$nome")) return ['errore' => 'Il video non si è salvato.'];
    @chmod("$dir/$nome", 0644);
    return ['url' => MEDIA_RADICE_UPLOADS . '/' . $rel . '/' . $nome];
}
