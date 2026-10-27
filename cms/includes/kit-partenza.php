<?php
/**
 * Kit di partenza (1.3.0, 27 ottobre 2026): all'ultimo passo dell'installatore si sceglie "Che sito vuoi fare?".
 * Vuoto e il sito di sempre (le pagine di partenza dell'installatore); gli altri kit costruiscono con l'editor la home,
 * le pagine principali e il menu dell'header, con testi d'esempio da sostituire.
 *
 * Ogni kit e un file di soli dati in includes/kit/<id>.php:
 *   'nome', 'descrizione', 'ordine'
 *   'home'     => [blocco, ...]           le sezioni della home
 *   'pagine'   => [['titolo', 'slug', 'sezioni' => [blocco, ...]], ...]
 *   'menu'     => [['etichetta', '/indirizzo/'], ...]   il menu Principale (header)
 *   'articoli' => [['titolo', 'slug', 'estratto', 'contenuto'], ...]   articoli d'esempio, oltre a quello di benvenuto
 *   'form'     => ['titolo' => ..., 'intro' => ...]   come si presenta il form dei contatti
 * Un blocco e una sezione nella forma corta di includes/sezioni-pronte.php, oppure ['pronta' => '<id>'] per una delle
 * sezioni pronte dell'editor, cosi com'e. Nei testi {sito} diventa il nome del sito e {descrizione} la sua descrizione;
 * nelle immagini {immagine:N} diventa l'indirizzo di un disegno astratto che il kit scrive in uploads/kit/ (niente
 * immagini da fuori: le disegna kit_immagini(), sono SVG semplici e si sostituiscono dalla libreria come ogni foto).
 *
 * Gli indirizzi dei link si scrivono dalla radice del sito (/contatti/): il sito li porta da solo nella cartella del CMS
 * quando sta in una sottocartella (cms_con_cartella), come per le pagine di partenza di sempre.
 */

// sezioni-pronte.php (e con lui l'editor) si carica solo per costruire: l'elenco dei kit serve all'installatore prima
// che esista il config.local.php, quando il motore non si puo ancora caricare.

/** I kit che ci sono: id => ['nome', 'descrizione']. "vuoto" e sempre il primo e non ha un file. */
function kit_elenco(): array {
    $out = ['vuoto' => ['nome' => 'Vuoto', 'descrizione' => 'Le pagine di sempre: una home di benvenuto, Chi sono, Blog e Contatti. Per chi vuole costruire da zero.', 'ordine' => 0]];
    foreach (glob(__DIR__ . '/kit/*.php') ?: [] as $f) {
        $id = basename($f, '.php');
        if (!preg_match('/^[a-z0-9-]{2,40}$/', $id) || $id === 'vuoto') continue;
        $k = kit_leggi($id);
        if ($k) $out[$id] = ['nome' => (string)$k['nome'], 'descrizione' => (string)($k['descrizione'] ?? ''), 'ordine' => (int)($k['ordine'] ?? 50)];
    }
    uasort($out, fn($a, $b) => $a['ordine'] <=> $b['ordine']);
    return $out;
}

/** I dati di un kit, o null. Un file rotto non ferma l'installatore: il kit semplicemente non c'e. */
function kit_leggi(string $id): ?array {
    if (!preg_match('/^[a-z0-9-]{2,40}$/', $id) || !is_file(__DIR__ . '/kit/' . $id . '.php')) return null;
    try { $k = require __DIR__ . '/kit/' . $id . '.php'; } catch (Throwable $e) { error_log('kit ' . $id . ': ' . $e->getMessage()); return null; }
    return is_array($k) && !empty($k['nome']) ? $k : null;
}

/** Sostituisce i segnaposto in tutte le stringhe di un array. */
function kit_sostituisci($x, array $mappa) {
    if (is_string($x)) return strtr($x, $mappa);
    if (is_array($x)) { foreach ($x as $k => $v) $x[$k] = kit_sostituisci($v, $mappa); }
    return $x;
}

/** I blocchi di un kit -> sezioni dell'editor validate. */
function kit_sezioni(array $blocchi, array $mappa): array {
    require_once __DIR__ . '/sezioni-pronte.php';
    $corte = [];
    $pronte = sezioni_pronte();
    foreach ($blocchi as $b) {
        if (isset($b['pronta'])) { foreach ((array)($pronte[$b['pronta']]['sezioni'] ?? []) as $s) $corte[] = $s; }
        else $corte[] = $b;
    }
    $albero = editor_valida(['elementi' => sezioni_pronte_costruisci(kit_sostituisci($corte, $mappa))]);
    return $albero ? $albero['elementi'] : [];
}

/**
 * Disegni astratti per le foto dei kit: sfondo sfumato, un alone, qualche forma geometrica e una trama di punti, nei
 * colori di partenza del sito. Uno per numero (1-8), scritti una volta sola in uploads/kit/. Ritorna numero => indirizzo.
 */
function kit_immagini(): array {
    $dir = cms_uploads_dir() . '/kit';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return [];
    $coppie = [['#1e1b4b', '#0e7490'], ['#3b0764', '#1d4ed8'], ['#0f172a', '#6d28d9'], ['#164e63', '#4c1d95'], ['#312e81', '#0891b2'], ['#1f2937', '#9333ea'], ['#0c4a6e', '#701a75'], ['#172554', '#0f766e']];
    $out = [];
    foreach ($coppie as $i => [$a, $b]) {
        $n = $i + 1;
        $file = $dir . '/disegno-' . $n . '.svg';
        if (!is_file($file)) {
            // Posizioni diverse per ogni disegno, ma sempre le stesse: due installazioni hanno gli stessi disegni.
            $cx = 280 + ($n * 137) % 640; $cy = 220 + ($n * 89) % 460; $r = 260 + ($n * 53) % 160;
            $qx = 120 + ($n * 211) % 760; $qy = 160 + ($n * 157) % 520; $rot = ($n * 23) % 50 - 25;
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 900" width="1200" height="900">'
                . '<defs><linearGradient id="f" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $a . '"/><stop offset="1" stop-color="' . $b . '"/></linearGradient>'
                . '<radialGradient id="l" cx="0.5" cy="0.5" r="0.5"><stop offset="0" stop-color="#22d3ee" stop-opacity="0.55"/><stop offset="1" stop-color="#22d3ee" stop-opacity="0"/></radialGradient>'
                . '<radialGradient id="v" cx="0.5" cy="0.5" r="0.5"><stop offset="0" stop-color="#c084fc" stop-opacity="0.5"/><stop offset="1" stop-color="#c084fc" stop-opacity="0"/></radialGradient>'
                . '<pattern id="p" width="36" height="36" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.6" fill="#ffffff" fill-opacity="0.12"/></pattern></defs>'
                . '<rect width="1200" height="900" fill="url(#f)"/><rect width="1200" height="900" fill="url(#p)"/>'
                . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="url(#l)"/>'
                . '<circle cx="' . (1200 - $cx) . '" cy="' . (900 - $cy) . '" r="' . ($r + 60) . '" fill="url(#v)"/>'
                . '<g transform="rotate(' . $rot . ' 600 450)" fill="none" stroke="#ffffff" stroke-opacity="0.35" stroke-width="3">'
                . '<rect x="' . $qx . '" y="' . $qy . '" width="300" height="300" rx="48"/>'
                . '<circle cx="' . ($qx + 420) . '" cy="' . ($qy + 90) . '" r="110"/>'
                . '<path d="M' . ($qx - 60) . ' ' . ($qy + 420) . ' l180 -180 l180 180"/></g>'
                . '<circle cx="' . ($qx + 300) . '" cy="' . $qy . '" r="14" fill="#22d3ee"/>'
                . '<circle cx="' . ($qx + 40) . '" cy="' . ($qy + 300) . '" r="9" fill="#c084fc"/></svg>';
            if (@file_put_contents($file, $svg) === false) continue;
            @chmod($file, 0644);
        }
        $out[$n] = cms_uploads_url() . '/kit/disegno-' . $n . '.svg';
    }
    return $out;
}

/** La mappa dei segnaposto: {sito}, {descrizione}, {immagine:N}. */
function kit_mappa(string $sito, string $descrizione): array {
    $m = ['{sito}' => $sito, '{descrizione}' => $descrizione !== '' ? $descrizione : 'Una frase che dice cosa fai e per chi: la scrivi in Impostazioni o qui, nell\'editor.'];
    foreach (kit_immagini() as $n => $u) $m['{immagine:' . $n . '}'] = $u;
    return $m;
}
