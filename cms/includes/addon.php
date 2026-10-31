<?php
/**
 * Gli addon (2.0.0): pezzi del prodotto che si accendono solo sul sito che li vuole, Impostazioni > Addon.
 * Un addon è un modulo (stesso manifesto, docs-sviluppo/cms-moduli.md) che sta in addon/<nome>/ invece di moduli/<nome>/.
 * Arriva con lo zip e con gli aggiornamenti, ma resta spento finché l'amministratore non lo accende: quelli accesi sono
 * scritti in moduli/addon-attivi.json, che gli aggiornamenti non toccano e la copia del sito porta con sé.
 * Spegnere un addon non cancella niente: tabelle e dati restano, e tornano quando lo si riaccende.
 * Le pagine del pannello di un addon passano da admin/addon-pagina.php?a=<addon>&p=<pagina> (solo amministratore).
 */
require_once __DIR__ . '/cms.php';

/** Gli addon presenti sul disco, nome => manifesto (anche spenti), in ordine di nome. Un manifesto rotto non entra. */
function addon_disponibili(): array {
    $out = [];
    foreach (glob(cms_radice() . '/addon/*/modulo.php') ?: [] as $f) {
        $nome = basename(dirname($f));
        if (!preg_match('/^[a-z][a-z0-9_-]{0,40}$/', $nome)) continue;
        $acceso = isset(cms_moduli()[$nome]);
        try { $def = $acceso ? cms_moduli()[$nome] : (require $f); } catch (Throwable $e) { error_log("cms: addon $nome illeggibile: " . $e->getMessage()); continue; }
        if (!is_array($def)) continue;
        $out[$nome] = $def + ['nome' => $nome, 'etichetta' => ucfirst($nome), 'descrizione' => '', 'versione' => '', 'richiede' => [], 'cartella' => dirname($f)];
        $out[$nome]['acceso'] = in_array($nome, cms_addon_attivi(), true);
    }
    ksort($out);
    return $out;
}

/** Accende o spegne un addon. Accendendolo si accendono anche quelli che richiede, e si creano le tabelle. Ritorna il log. */
function addon_attiva(string $nome, bool $acceso): array {
    $tutti = addon_disponibili();
    if (!isset($tutti[$nome])) throw new RuntimeException('Addon sconosciuto.');
    $attivi = cms_addon_attivi();
    $log = [];
    if ($acceso) {
        foreach ((array)$tutti[$nome]['richiede'] as $r) {
            if (!isset($tutti[$r])) throw new RuntimeException('Questo addon richiede «' . $r . '», che non c\'è.');
            if (!in_array($r, $attivi, true)) { $attivi[] = $r; $log[] = 'Acceso anche «' . $tutti[$r]['etichetta'] . '», che serve.'; }
        }
        if (!in_array($nome, $attivi, true)) $attivi[] = $nome;
    } else {
        foreach ($tutti as $n => $d) if ($n !== $nome && in_array($n, $attivi, true) && in_array($nome, (array)$d['richiede'], true)) {
            throw new RuntimeException('Prima spegni «' . $d['etichetta'] . '»: usa questo addon.');
        }
        $attivi = array_values(array_diff($attivi, [$nome]));
    }
    addon_scrivi($attivi);
    if ($acceso) {
        // Le tabelle: si caricano i manifesti appena accesi (cms_moduli() ha già letto l'elenco vecchio).
        foreach ($attivi as $n) {
            $def = $n === $nome || in_array($n, (array)$tutti[$nome]['richiede'], true) ? (require cms_radice() . '/addon/' . $n . '/modulo.php') : null;
            if (is_array($def) && !empty($def['installa']) && is_callable($def['installa'])) {
                $r = ($def['installa'])();
                $log[] = ($tutti[$n]['etichetta'] ?? $n) . ': ' . (is_string($r) && $r !== '' ? $r : 'tabelle a posto');
            }
        }
    }
    return $log;
}

/** Scrive l'elenco degli addon accesi, in modo atomico (file temporaneo e rename). */
function addon_scrivi(array $attivi): void {
    $dir = cms_radice() . '/moduli';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new RuntimeException('Non riesco a creare la cartella moduli/.');
    $f = $dir . '/addon-attivi.json';
    $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $json = json_encode(array_values(array_unique($attivi)), JSON_UNESCAPED_SLASHES);
    if (@file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $f)) { @unlink($tmp); throw new RuntimeException('Non riesco a scrivere moduli/addon-attivi.json: controlla i permessi della cartella moduli/.'); }
    @chmod($f, 0644);
    if (function_exists('opcache_invalidate')) @opcache_invalidate($f, true);
}

/** L'indirizzo di una pagina del pannello di un addon. */
function addon_url(string $addon, string $pagina, array $q = []): string {
    return 'addon-pagina.php?' . http_build_query(['a' => $addon, 'p' => $pagina] + $q);
}
