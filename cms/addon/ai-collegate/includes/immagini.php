<?php
/**
 * 2.38.0: addon AI collegate, i dati delle immagini della libreria (includes/media-dati.php del nucleo).
 *  - list_media: le immagini con i loro dati e cosa manca (alt, descrizione, crediti delle importate, peso), a pagine, con i
 *    filtri di Media > Dati delle immagini. Sola lettura.
 *  - propose_media_data: proposte di alt, titolo, didascalia e descrizione per fino a AIC_MEDIA_MAX immagini in una chiamata,
 *    tutte controllate prima di scriverne una (o tutte o nessuna), legate dal gruppo "media-0-<12 hex>": l'amministratore le
 *    vede insieme in Proposte e le approva tutte o una per una. Mai scrittura diretta: si applicano solo con l'approvazione
 *    (aic_proposta_applica: media_alt_salva o media_dati_salva).
 * Manuale tecnico, 50.6.
 */
require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/proposte.php';
require_once cms_radice() . '/includes/media-dati.php';

const AIC_MEDIA_MAX = 50;          // immagini per chiamata di propose_media_data
const AIC_MEDIA_PER_PAGINA = 50;   // massimo di list_media
/** I campi che propose_media_data accetta per ogni immagine => campo della proposta. */
const AIC_MEDIA_CAMPI = ['alt' => 'alt', 'titolo' => 'titolo_immagine', 'didascalia' => 'didascalia', 'descrizione' => 'descrizione_immagine'];

function aic_t_list_media(array $conn, array $a): array {
    $filtri = media_dati_filtri();
    $filtro = is_string($a['filtro'] ?? null) && isset($filtri[$a['filtro']]) ? $a['filtro'] : 'tutte';
    $per = max(1, min(AIC_MEDIA_PER_PAGINA, (int)($a['per_pagina'] ?? 20)));
    $pagina = max(1, (int)($a['pagina'] ?? 1));
    $tutte = media_dati_elenco($filtro);
    $tot = count($tutte);
    $voci = [];
    foreach (array_slice($tutte, ($pagina - 1) * $per, $per) as $m) {
        $info = media_dati_file_info($m['url']);
        $d = $m['dati'];
        $voci[] = ['url' => $m['url'], 'nome' => $m['nome'], 'cartella' => media_cartelle()[$m['cartella']] ?? $m['cartella'],
            'larghezza' => $info['larghezza'], 'altezza' => $info['altezza'], 'peso_kb' => (int)round($m['peso'] / 1024), 'formato' => $info['formato'],
            'alt' => $d['alt'], 'titolo' => $d['titolo'], 'didascalia' => $d['didascalia'], 'descrizione' => $d['descrizione'],
            'autore' => $d['autore'], 'licenza' => media_dati_licenze()[$d['licenza']][0] ?? '', 'importata_da' => $d['origine'],
            'manca' => array_values(array_map(fn($x) => ['alt' => 'alt', 'descrizione' => 'descrizione', 'crediti' => 'crediti (foto importata senza autore né licenza)', 'pesanti' => 'pesante (oltre 500 KB)'][$x], $m['manca']))];
    }
    return ['filtro' => $filtro, 'totale' => $tot, 'pagina' => $pagina, 'per_pagina' => $per, 'pagine' => max(1, (int)ceil($tot / $per)), 'immagini' => $voci,
        'nota' => 'Per proporre alt, titolo, didascalia o descrizione usa propose_media_data (fino a ' . AIC_MEDIA_MAX . ' immagini per chiamata). Scrivi l\'alt guardando l\'immagine: cosa si vede, da 5 a 125 caratteri, senza "immagine di". Autore e licenza non si propongono: li scrive l\'amministratore.',
        '_registro' => $tot . ' immagini (' . $filtro . '), pagina ' . $pagina];
}

function aic_t_propose_media_data(array $conn, array $a): array {
    aic_schema();
    $imm = $a['immagini'] ?? null;
    if (!is_array($imm) || !$imm || !array_is_list($imm)) throw new AicErrore('"immagini" deve essere un elenco di {url, alt?, titolo?, didascalia?, descrizione?, motivo?}.');
    if (count($imm) > AIC_MEDIA_MAX) throw new AicErrore('Troppe immagini in una chiamata: ' . count($imm) . ' (massimo ' . AIC_MEDIA_MAX . '). Dividile in più chiamate.');
    $motivo = aic_testo($a['motivo'] ?? '', 1000, true);
    if (mb_strlen($motivo) < 5) throw new AicErrore('Scrivi il motivo generale delle proposte (lo legge l\'amministratore per decidere).');
    $gruppo = 'media-0-' . bin2hex(random_bytes(6));
    $pronte = []; $visti = [];
    foreach ($imm as $i => $x) {
        try {
            if (!is_array($x)) throw new AicErrore('ogni immagine è un oggetto {url, ...}.');
            $url = is_string($x['url'] ?? null) ? $x['url'] : '';
            $campi = array_intersect_key($x, AIC_MEDIA_CAMPI);
            if (!$campi) throw new AicErrore('nessun campo da proporre: alt, titolo, didascalia o descrizione.');
            $chiave = media_alt_chiave($url);
            if ($chiave !== '' && isset($visti[$chiave])) throw new AicErrore('la stessa immagine compare due volte: metti tutti i suoi campi in una voce sola.');
            $visti[$chiave] = true;
            $mm = aic_testo($x['motivo'] ?? '', 1000, true);
            foreach ($campi as $k => $v) {
                $pronte[] = aic_proposta_prepara($conn, ['tipo' => 'media', 'url' => $url, 'campo' => AIC_MEDIA_CAMPI[$k], 'valore' => $v, 'motivo' => mb_strlen($mm) >= 5 ? $mm : $motivo], count($pronte));
            }
        } catch (AicErrore $e) {
            throw new AicErrore('Nessuna proposta salvata: immagine ' . ($i + 1) . (is_array($x) && is_string($x['url'] ?? null) ? ' (' . mb_substr(aic_testo(basename($x['url']), 80), 0, 80) . ')' : '') . ': ' . $e->getMessage(), $e->http);
        }
    }
    $fatte = [];
    foreach ($pronte as $x) {
        $p = aic_proposta_salva($conn, $x, $gruppo);
        $fatte[] = ['proposta' => $p['id'], 'url' => (string)$x['media_url'], 'campo' => $p['campo'], 'prima' => mb_substr($p['prima'], 0, 300), 'dopo' => mb_substr($p['dopo'], 0, 300)];
    }
    return ['gruppo' => $gruppo, 'proposte' => $fatte, 'immagini' => count($imm), 'stato' => 'attesa',
        'nota' => 'Proposte registrate insieme: l\'amministratore le vede in AI collegate > Proposte sotto "Dati delle immagini" e può approvarle tutte o una per una. Niente cambia finché non le approva.',
        '_registro' => count($fatte) . ' proposte sui dati di ' . count($imm) . ' immagini'];
}
