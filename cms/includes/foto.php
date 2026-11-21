<?php
/**
 * Foto più leggere da sole (1.4.0).
 *
 * Al caricamento (media_carica() in media.php, che usano la pagina Media e l'editor; newsletter-immagine.php) una foto
 * JPEG o PNG passa da foto_alleggerisci():
 *   - orientamento EXIF applicato ai pixel (la foto del telefono resta dritta anche senza metadati: tutti gli otto casi);
 *   - se il lato lungo supera il limite (2560 px di partenza) si rimpicciolisce, con le stesse proporzioni;
 *   - si riscrive: via i metadati (posizione GPS, modello del telefono) e qualunque cosa nascosta dopo i pixel;
 *   - accanto nasce la versione WebP, "<nome>.jpg.webp", solo se pesa meno dell'originale (non per le email).
 * Con GD (il caso normale) o Imagick; senza nessuno dei due, o con una foto che non si riesce a leggere, il file resta
 * com'è e non esce nessun errore. GIF (animate), SVG, AVIF e WebP caricati come tali non si ridimensionano.
 *
 * Il sito serve la WebP ai browser che dichiarano di accettarla (intestazione Accept con image/webp): foto_webp_nel_html()
 * cambia l'indirizzo nelle src/srcset e negli url() dei fogli della pagina, solo per le foto che hanno la loro WebP accanto.
 * Gli indirizzi di prima non cambiano e funzionano sempre: la foto originale resta al suo posto.
 * Le foto caricate prima della 1.4.0 non si toccano.
 *
 * Impostazioni (pagina Media, solo amministratore): foto_leggere ('1' acceso, '0' spento: le foto restano come arrivano,
 * ripulite dai metadati come prima, e il sito serve gli originali), foto_lato_max (px, da 800 a 8000).
 */

const FOTO_LATO_PREDEFINITO = 2560;
const FOTO_QUALITA_JPEG = 85;
const FOTO_QUALITA_WEBP = 80;

/** Le impostazioni, già controllate. */
function foto_impostazioni(): array {
    $lato = (int)get_setting('foto_lato_max', (string)FOTO_LATO_PREDEFINITO);
    return [
        'attivo' => get_setting('foto_leggere', '1') !== '0',
        'lato' => $lato >= 800 && $lato <= 8000 ? $lato : FOTO_LATO_PREDEFINITO,
    ];
}

/** Con che cosa si lavorano le foto: 'gd', 'imagick' o '' (nessuno: le foto restano come arrivano). */
function foto_motore(): string {
    if (function_exists('imagecreatefromstring') && function_exists('imagecopyresampled')) return 'gd';
    if (class_exists('Imagick')) return 'imagick';
    return '';
}

/** Si possono scrivere WebP con il motore che c'è? */
function foto_webp_possibile(): bool {
    $m = foto_motore();
    if ($m === 'gd') return function_exists('imagewebp');
    if ($m === 'imagick') { try { return in_array('WEBP', \Imagick::queryFormats('WEBP'), true); } catch (Throwable $e) { return false; } }
    return false;
}

/**
 * L'orientamento EXIF di un JPEG (1-8), letto dai byte: non serve l'estensione exif di PHP.
 * 1 = dritta; 3 = capovolta; 6 e 8 = girata di 90 gradi; 2, 4, 5, 7 = gli stessi casi allo specchio.
 */
function foto_orientamento(string $file): int {
    $f = @fopen($file, 'rb');
    if (!$f) return 1;
    $d = (string)fread($f, 131072);
    fclose($f);
    if (substr($d, 0, 2) !== "\xFF\xD8") return 1;
    $i = 2; $n = strlen($d);
    while ($i + 4 <= $n && $d[$i] === "\xFF") {
        $tipo = ord($d[$i + 1]);
        $lung = unpack('n', substr($d, $i + 2, 2))[1];
        if ($tipo === 0xE1 && substr($d, $i + 4, 6) === "Exif\0\0") {
            $t = substr($d, $i + 10, $lung - 8);
            if (strlen($t) < 8) return 1;
            $le = substr($t, 0, 2) === 'II';
            $u16 = fn(int $o) => strlen($t) >= $o + 2 ? unpack($le ? 'v' : 'n', substr($t, $o, 2))[1] : 0;
            $u32 = fn(int $o) => strlen($t) >= $o + 4 ? unpack($le ? 'V' : 'N', substr($t, $o, 4))[1] : 0;
            $ifd = $u32(4); $voci = $u16($ifd);
            for ($k = 0; $k < $voci && $k < 200; $k++) {
                $o = $ifd + 2 + $k * 12;
                if ($u16($o) === 0x0112) { $v = $u16($o + 8); return $v >= 1 && $v <= 8 ? $v : 1; }
            }
            return 1;
        }
        if ($tipo === 0xDA) break; // inizio dei pixel: niente EXIF
        $i += 2 + $lung;
    }
    return 1;
}

/** Applica l'orientamento ai pixel (GD). */
function foto_gd_raddrizza(\GdImage $img, int $o): \GdImage {
    if (in_array($o, [2, 4, 5, 7], true)) imageflip($img, IMG_FLIP_HORIZONTAL);
    // GD gira in senso antiorario: 6 = un quarto in senso orario; 5 e 7 sono 8 e 6 allo specchio (trasposta e trasversa).
    $gradi = match ($o) { 3, 4 => 180, 6, 7 => -90, 5, 8 => 90, default => 0 };
    if ($gradi !== 0) { $r = imagerotate($img, $gradi, 0); if ($r) { imagedestroy($img); $img = $r; } }
    return $img;
}

/** Quanta memoria serve a GD per una foto di queste misure, e se c'è (provando ad alzare il limite). */
function foto_memoria_basta(int $l, int $a): bool {
    $serve = (int)($l * $a * 5 * 2.2) + memory_get_usage() + 16 * 1048576; // originale e copia rimpicciolita, con margine
    $lim = (string)ini_get('memory_limit');
    if ($lim === '-1') return true;
    $byte = (int)$lim * match (strtolower(substr($lim, -1))) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 };
    if ($byte >= $serve) return true;
    return @ini_set('memory_limit', (string)(int)ceil($serve / 1048576) . 'M') !== false;
}

/**
 * Alleggerisce una foto appena caricata (vedi in testa). $ext è quella vera, letta dal contenuto.
 * Ritorna ['fatto' => bool, 'motore', 'larghezza', 'altezza', 'ridimensionata' => bool, 'webp' => percorso o null, 'prima', 'dopo'].
 * Con fatto false il file non è stato toccato: chi chiama ripiega sulla ripulitura di sempre.
 */
function foto_alleggerisci(string $file, string $ext, bool $conWebp = true): array {
    $esito = ['fatto' => false, 'motore' => foto_motore(), 'larghezza' => null, 'altezza' => null, 'ridimensionata' => false, 'webp' => null, 'prima' => (int)@filesize($file), 'dopo' => null];
    $ext = strtolower($ext === 'jpeg' ? 'jpg' : $ext);
    $cfg = foto_impostazioni();
    if (!$cfg['attivo'] || !in_array($ext, ['jpg', 'png'], true) || $esito['motore'] === '') return $esito;
    $info = @getimagesize($file);
    if (!$info || $info[0] < 1 || $info[1] < 1) return $esito;
    try {
        $r = $esito['motore'] === 'gd' ? foto_con_gd($file, $ext, $info, $cfg['lato'], $conWebp && foto_webp_possibile())
            : foto_con_imagick($file, $ext, $cfg['lato'], $conWebp && foto_webp_possibile());
    } catch (Throwable $e) {
        error_log('foto: ' . $e->getMessage());
        return $esito;
    }
    if ($r === null) return $esito;
    clearstatcache();
    return array_merge($esito, $r, ['fatto' => true, 'dopo' => (int)@filesize($file)]);
}

/** Il lavoro con GD. null = non riuscito, file intatto. */
function foto_con_gd(string $file, string $ext, array $info, int $lato, bool $webp): ?array {
    [$l, $a] = $info;
    if (!foto_memoria_basta($l, $a)) return null;
    $img = @imagecreatefromstring((string)file_get_contents($file));
    if (!$img) return null;
    if ($ext === 'jpg') $img = foto_gd_raddrizza($img, foto_orientamento($file));
    $l = imagesx($img); $a = imagesy($img);
    $piu = false;
    if (max($l, $a) > $lato) {
        $k = $lato / max($l, $a);
        $nl = max(1, (int)round($l * $k)); $na = max(1, (int)round($a * $k));
        $nuova = imagecreatetruecolor($nl, $na);
        if ($ext === 'png') { imagealphablending($nuova, false); imagesavealpha($nuova, true); imagefill($nuova, 0, 0, imagecolorallocatealpha($nuova, 0, 0, 0, 127)); }
        imagecopyresampled($nuova, $img, 0, 0, 0, 0, $nl, $na, $l, $a);
        imagedestroy($img); $img = $nuova; $l = $nl; $a = $na; $piu = true;
    }
    if ($ext === 'png' || !imageistruecolor($img)) { imagealphablending($img, false); imagesavealpha($img, true); }
    $tmp = $file . '.tmp';
    $ok = $ext === 'jpg' ? imagejpeg($img, $tmp, FOTO_QUALITA_JPEG) : imagepng($img, $tmp, 6);
    if (!$ok || !is_file($tmp) || filesize($tmp) === 0 || !rename($tmp, $file)) { @unlink($tmp); imagedestroy($img); return null; }
    $w = null;
    if ($webp) {
        if (!imageistruecolor($img)) imagepalettetotruecolor($img);
        $w = $file . '.webp';
        if (!@imagewebp($img, $w, FOTO_QUALITA_WEBP) || !is_file($w) || filesize($w) === 0 || filesize($w) >= filesize($file)) { @unlink($w); $w = null; }
        else @chmod($w, 0644);
    }
    imagedestroy($img);
    return ['larghezza' => $l, 'altezza' => $a, 'ridimensionata' => $piu, 'webp' => $w];
}

/** Il lavoro con Imagick, quando GD manca. Non provato su un server vero (nell'ambiente delle prove c'è solo GD). */
function foto_con_imagick(string $file, string $ext, int $lato, bool $webp): ?array {
    $im = new \Imagick($file);
    $o = (int)$im->getImageOrientation();
    if (in_array($o, [2, 4, 5, 7], true)) $im->flopImage();
    $gradi = match ($o) { 3, 4 => 180, 6, 7 => 90, 5, 8 => -90, default => 0 }; // Imagick gira in senso orario
    if ($gradi !== 0) $im->rotateImage('#000', $gradi);
    $im->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    $l = $im->getImageWidth(); $a = $im->getImageHeight(); $piu = false;
    if (max($l, $a) > $lato) { $im->resizeImage($l >= $a ? $lato : 0, $a > $l ? $lato : 0, \Imagick::FILTER_LANCZOS, 1); $piu = true; $l = $im->getImageWidth(); $a = $im->getImageHeight(); }
    $im->stripImage();
    if ($ext === 'jpg') $im->setImageCompressionQuality(FOTO_QUALITA_JPEG);
    $tmp = $file . '.tmp';
    if (!$im->writeImage(($ext === 'jpg' ? 'jpeg:' : 'png:') . $tmp) || !is_file($tmp) || filesize($tmp) === 0 || !rename($tmp, $file)) { @unlink($tmp); return null; }
    $w = null;
    if ($webp) {
        $w = $file . '.webp';
        $c = clone $im; $c->setImageFormat('webp'); $c->setImageCompressionQuality(FOTO_QUALITA_WEBP);
        if (!$c->writeImage('webp:' . $w) || !is_file($w) || filesize($w) >= filesize($file)) { @unlink($w); $w = null; } else @chmod($w, 0644);
        $c->clear();
    }
    $im->clear();
    return ['larghezza' => $l, 'altezza' => $a, 'ridimensionata' => $piu, 'webp' => $w];
}

/** La WebP che accompagna una foto: "foto-abc123.jpg.webp". Le nasconde la libreria dei media. */
function foto_e_webp_compagna(string $nome): bool {
    return (bool)preg_match('/\.(jpe?g|png)\.webp$/i', $nome);
}

/** Il browser di questa richiesta dichiara di accettare le WebP? */
function foto_browser_webp(): bool {
    return str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'image/webp');
}

/**
 * Nella pagina del sito, le foto caricate che hanno la WebP accanto passano alla WebP: negli attributi src, srcset,
 * data-src, data-srcset, style e nei blocchi <style> (gli sfondi delle sezioni). Non tocca link, meta (og:image) e dati
 * strutturati: chi li legge da fuori (social, motori) riceve sempre l'originale. Da chiamare solo se foto_browser_webp().
 */
function foto_webp_nel_html(string $html): string {
    if (!foto_impostazioni()['attivo']) return $html;
    $url = cms_uploads_url(); $dir = cms_uploads_dir();
    if (!str_contains($html, $url . '/')) return $html;
    $visti = [];
    $re = '~((?:https?://[A-Za-z0-9.:-]+)?' . preg_quote($url, '~') . ')(/[A-Za-z0-9_./%-]+?\.(?:jpe?g|png))(?=[\s"\'),&?#]|$)~i';
    $cambia = function (string $testo) use ($re, $dir, &$visti): string {
        return (string)preg_replace_callback($re, function ($m) use ($dir, &$visti) {
            $rel = rawurldecode($m[2]);
            if (str_contains($rel, '..')) return $m[0];
            $visti[$rel] ??= is_file($dir . $rel . '.webp');
            return $visti[$rel] ? $m[0] . '.webp' : $m[0];
        }, $testo);
    };
    $html = (string)preg_replace_callback('~(\s(?:src|srcset|data-src|data-srcset|style)=)(["\'])(.*?)\2~is', fn($m) => $m[1] . $m[2] . $cambia($m[3]) . $m[2], $html);
    return (string)preg_replace_callback('~(<style\b[^>]*>)(.*?)(</style>)~is', fn($m) => $m[1] . $cambia($m[2]) . $m[3], $html);
}
