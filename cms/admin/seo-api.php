<?php
/**
 * Chiamate del pannello SEO e AEO. POST con il token della sessione, risposta JSON.
 *   analisi   i campi di un contenuto (title, slug, seo_title, meta_description, keyword_target, keywords_secondarie,
 *             contenuto, content_format, immagine, immagine_alt, tldr, faq, type) -> punteggio, voci, html del riquadro
 *   jsonld    url -> il JSON-LD che quella pagina emette, validato
 *   ispeziona url -> verdetto di Search Console (richiede il service account)
 * Usata dalla scheda del contenuto (assets/seo-controllo.js) e dall'editor visuale (Impostazioni della pagina).
 */
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once dirname(__DIR__) . '/includes/sito-seo.php';
require_once dirname(__DIR__) . '/sito/viste.php';
require_once dirname(__DIR__) . '/sito/extra.php';
require_once dirname(__DIR__) . '/includes/moduli.php';
require_once dirname(__DIR__) . '/includes/seo.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function seo_api_esci(array $dati, int $codice = 200): never {
    http_response_code($codice);
    echo json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$azione = (string)($_POST['azione'] ?? $_GET['azione'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok((string)($_POST['_csrf'] ?? ''))) { // sicurezza: token e Origin/Referer
    seo_api_esci(['errore' => 'Sessione scaduta: ricarica la pagina.'], 403);
}

try {
    switch ($azione) {
        case 'analisi':
            $c = $_POST;
            if (isset($c['faq_testo']) && !isset($c['faq'])) { require_once __DIR__ . '/includes/admin-functions.php'; $c['faq'] = admin_faq_da_testo((string)$c['faq_testo']); }
            $a = seo_analisi($c);
            seo_api_esci(['punteggio' => $a['punteggio'], 'colore' => $a['colore'], 'voci' => $a['voci'], 'parole' => $a['parole'], 'html' => seo_analisi_html($a)]);

        case 'jsonld':
            $g = seo_jsonld_di((string)($_POST['url'] ?? '/'));
            if (isset($g['errore'])) seo_api_esci(['errore' => $g['errore']], 404);
            $testo = json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            // Controllo di forma: ogni nodo ha @type, gli @id citati esistono.
            $avvisi = [];
            $ids = array_filter(array_map(fn($n) => $n['@id'] ?? null, $g['@graph']));
            foreach ($g['@graph'] as $i => $n) {
                if (empty($n['@type'])) $avvisi[] = "Il nodo $i non ha @type.";
                array_walk_recursive($n, function ($v, $k) use ($ids, &$avvisi, $n) { if ($k === '@id' && !in_array($v, $ids, true) && !str_contains($v, '#pagina') && !str_contains($v, '#logo')) $avvisi[] = 'Riferimento ' . $v . ' senza nodo (in ' . ($n['@type'] ?? '?') . ').'; });
            }
            seo_api_esci(['testo' => $testo, 'nodi' => count($g['@graph']), 'tipi' => array_values(array_unique(array_map(fn($n) => $n['@type'] ?? '?', $g['@graph']))), 'avvisi' => array_values(array_unique($avvisi))]);

        case 'ispeziona':
            if (!is_admin()) seo_api_esci(['errore' => 'Riservato all\'amministratore.'], 403);
            $r = seo_gsc_ispeziona((string)($_POST['url'] ?? ''));
            if (isset($r['errore'])) seo_api_esci(['errore' => $r['errore']], 502);
            unset($r['grezzo']);
            $r['copertura_it'] = seo_copertura_italiano($r['copertura']);
            seo_api_esci($r);

        default:
            seo_api_esci(['errore' => 'Azione sconosciuta.'], 400);
    }
} catch (Throwable $e) {
    error_log('seo-api: ' . $e->getMessage());
    seo_api_esci(['errore' => 'Errore: ' . $e->getMessage()], 500);
}
