<?php
/**
 * Versioni: la cronologia di ogni modifica fatta con l'editor e con le sue voci (Stile del sito, Header e footer,
 * Menu, Temi, Impostazioni). Motore in includes/versioni.php, tabella {prefisso}editor_versioni.
 *
 *   versioni.php                                  tutte, dalle più recenti
 *   versioni.php?ambito=stile                     un ambito (contenuto:articoli, contenuto:pagine, modello, menu, stile, tema, impostazioni)
 *   versioni.php?ambito=modello&oggetto=3         un oggetto preciso
 *   versioni.php?confronta=120&con=precedente     il confronto (con = precedente | attuale | id di un'altra versione)
 *   versioni.php?anteprima=120                    la pagina com'era in quella versione, senza ripristinare (contenuti e modelli)
 *   POST azione=ripristina id=N                   ripristina (passa per il salvataggio normale: nasce la versione "ripristino della versione N")
 *   POST azione=nota id=N testo=...               una nota scritta a mano (la versione non si pota più)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/versioni.php';
require_once dirname(__DIR__) . '/includes/editor.php';
require_login();
versioni_pronto();
$adminTitle = 'Versioni';

$ambiti = versioni_ambiti();
$ambito = (string)($_GET['ambito'] ?? '');
if ($ambito !== '' && $ambito !== 'contenuto' && !isset($ambiti[$ambito])) $ambito = '';
$oggetto = (int)($_GET['oggetto'] ?? 0);
// sicurezza (attacco 2, ruoli): il redattore vede e tocca solo le versioni dei contenuti, mai di stile, modelli, menu, temi, impostazioni.
if (!is_admin()) {
    if ($ambito === '' || ($ambito !== 'contenuto' && !str_starts_with($ambito, 'contenuto:'))) $ambito = 'contenuto';
    foreach ([(int)($_POST['id'] ?? 0), (int)($_GET['anteprima'] ?? 0), (int)($_GET['confronta'] ?? 0), ctype_digit((string)($_GET['con'] ?? '')) ? (int)$_GET['con'] : 0] as $vid) {
        if (!$vid) continue;
        $vv = editor_versione($vid);
        if ($vv && !str_starts_with((string)$vv['ambito'], 'contenuto:')) { http_response_code(403); flash('error', 'Questa versione è riservata all\'amministratore.'); header('Location: versioni.php?ambito=contenuto'); exit; }
    }
}
$indietro = 'versioni.php' . ($ambito !== '' ? '?ambito=' . rawurlencode($ambito) . ($oggetto ? '&oggetto=' . $oggetto : '') : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    try {
        if (($_POST['azione'] ?? '') === 'ripristina') {
            $v = editor_versione($id);
            if (!$v) throw new RuntimeException('Versione non trovata.');
            // Pubblicare o mettere in bozza un contenuto resta un gesto dell'editor: chi non è amministratore ripristina solo i contenuti.
            if (!is_admin() && !str_starts_with((string)$v['ambito'], 'contenuto:')) throw new RuntimeException('Solo un amministratore ripristina stile, modelli, menu, temi e impostazioni.');
            flash('success', editor_versione_ripristina($id, (string)(current_user()['username'] ?? '')));
        } elseif (($_POST['azione'] ?? '') === 'nota') {
            versioni_nota($id, (string)($_POST['testo'] ?? ''));
            flash('success', trim((string)($_POST['testo'] ?? '')) !== '' ? 'Nota salvata: questa versione resta per sempre.' : 'Nota tolta.');
        }
    } catch (Throwable $e) {
        flash('error', 'Non riuscito: ' . $e->getMessage());
    }
    header('Location: ' . sicurezza_ritorno_pannello((string)($_POST['torna'] ?? ''), $indietro)); // sicurezza: solo dentro il pannello
    exit;
}

// ------------------------------------------------------------ anteprima di una versione (contenuti e modelli), fuori dal pannello
if (!empty($_GET['anteprima'])) {
    $v = editor_versione((int)$_GET['anteprima']);
    if (!$v) { http_response_code(404); exit('Versione non trovata.'); }
    $albero = is_array($v['dati']['albero'] ?? null) ? editor_valida($v['dati']['albero']) : null;
    if (!$albero) { http_response_code(400); exit('Questa versione non ha una struttura dell\'editor da mostrare. <a href="' . h($indietro) . '">Torna alle versioni</a>'); }
    require_once dirname(__DIR__) . '/includes/sito-seo.php';
    require_once dirname(__DIR__) . '/sito/viste.php';
    require_once dirname(__DIR__) . '/includes/moduli.php';
    if (is_file(dirname(__DIR__) . '/includes/modelli.php')) require_once dirname(__DIR__) . '/includes/modelli.php';
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    $fascia = '<div style="position:sticky;top:0;z-index:9999;background:#1f2937;color:#fff;font:13px/1.4 system-ui,sans-serif; /* fisso: fascia sopra l anteprima del sito */padding:8px 16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">'
        . '<b>Anteprima della versione ' . (int)$v['id'] . '</b> <span>' . h(versioni_data_breve((string)$v['creato'])) . ($v['autore'] ? ' · ' . h($v['autore']) : '') . ($v['nota'] ? ' · ' . h($v['nota']) : '') . '</span>'
        . '<span style="opacity:.8">Niente è cambiato sul sito: è solo una finestra sul passato.</span>'
        . '<a href="' . h($indietro) . '" style="margin-left:auto;color:#fff /* fisso */">Torna alle versioni</a></div>';
    if ($v['ambito'] === 'modello') {
        // Il sito (la home) con quella versione dell'header o del footer al posto di quello scelto.
        $tipoModello = (string)($v['dati']['tipo'] ?? (modelli_leggi((int)$v['oggetto_id'])['tipo'] ?? 'header'));
        $GLOBALS['sito_percorso'] = '/';
        $GLOBALS['modelli_versione'] = ['tipo' => $tipoModello, 'id' => (int)$v['oggetto_id'], 'albero' => $albero];
        if ($tipoModello === 'fine') {
            // 1.6.0: il riquadro di fine articolo si vede in fondo a un articolo d'esempio.
            [$corpo, $cssFine] = vista_fine_articolo('<div class="wrap"><div class="art" style="grid-template-columns:minmax(0,1fr)"><article class="corpo">'
                . '<p style="opacity:.7">Qui c\'è il testo dell\'articolo. Sotto, il riquadro che esce in fondo a ogni articolo, con la versione ' . (int)$v['id'] . '.</p></article></div></div>');
            $html = vista_pagina(['titolo' => 'Anteprima della versione ' . (int)$v['id'], 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []], $corpo, $cssFine, '/');
            echo preg_replace('~(<body[^>]*>)~', '$1' . $fascia, $html, 1);
            exit;
        }
        $corpo = '<div class="wrap" style="padding:72px 0;text-align:center"><p style="opacity:.7">Qui sotto e qui sopra: l\'header e il footer come li vedrebbe chi visita il sito, con la versione ' . (int)$v['id'] . ' del ' . h($tipoModello) . '.</p></div>';
        $html = vista_pagina(['titolo' => 'Anteprima della versione ' . (int)$v['id'], 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []], $corpo, '', '/');
        echo preg_replace('~(<body[^>]*>)~', '$1' . $fascia, $html, 1);
        exit;
    }
    if (str_starts_with((string)$v['ambito'], 'contenitore:')) {
        // 2.1: un contenitore di un modulo (una lezione): la tela com'era, con la cornice del modulo.
        $tipo = substr((string)$v['ambito'], 12);
        $cont = editor_contenitore($tipo);
        $pagina = $cont ? editor_pagina((int)$v['oggetto_id'], $tipo) : null;
        if (!$pagina) { http_response_code(404); exit('Il contenuto non esiste più (o il suo modulo è spento). <a href="' . h($indietro) . '">Torna alle versioni</a>'); }
        [$dis, $css] = editor_disegna($albero, editor_percorso_pagina($pagina));
        $cornice = is_callable($cont['anteprima'] ?? null) ? (array)$cont['anteprima']($pagina) : [];
        $testa = ($css !== '' ? '<style id="ed-css">' . str_replace('</', '<\\/', $css) . '</style>' : '') . (string)($cornice['testa'] ?? '');
        if (function_exists('editor_asset_pagina')) { $asset = editor_asset_pagina($dis); foreach ($asset['css'] ?? [] as $f) $testa = '<link rel="stylesheet" href="' . sito_versione_asset($f) . '">' . $testa; foreach ($asset['js'] ?? [] as $f) $dis .= '<script src="' . sito_versione_asset($f) . '" defer></script>'; }
        $html = vista_pagina(['titolo' => 'Anteprima della versione ' . (int)$v['id'] . ': ' . $pagina['title'], 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []], (string)($cornice['prima'] ?? '') . '<div class="ed-pagina">' . $dis . '</div>' . (string)($cornice['dopo'] ?? ''), $testa, editor_percorso_pagina($pagina));
        echo preg_replace('~(<body[^>]*>)~', '$1' . $fascia, $html, 1);
        exit;
    }
    $tipo = editor_tipo_valido(substr((string)$v['ambito'], 10));
    $pagina = editor_pagina((int)$v['oggetto_id'], $tipo);
    if (!$pagina) { http_response_code(404); exit('Il contenuto non esiste più. <a href="' . h($indietro) . '">Torna alle versioni</a>'); }
    $percorso = editor_percorso_pagina($pagina);
    $GLOBALS['sito_contenuto_riga'] = $pagina;
    $GLOBALS['editor_contesto'] = ['tipo' => $tipo, 'id' => (int)$pagina['id'], 'titolo' => (string)$pagina['title'], 'percorso' => $percorso];
    [$dis, $css] = editor_disegna($albero, $percorso);
    $testa = $css !== '' ? '<style id="ed-css">' . str_replace('</', '<\\/', $css) . '</style>' : '';
    if (function_exists('editor_asset_pagina')) { $asset = editor_asset_pagina($dis); foreach ($asset['css'] ?? [] as $f) $testa = '<link rel="stylesheet" href="' . sito_versione_asset($f) . '">' . $testa; foreach ($asset['js'] ?? [] as $f) $dis .= '<script src="' . sito_versione_asset($f) . '" defer></script>'; }
    if ($tipo !== 'pagine') {
        $c = ['titolo' => (string)$pagina['title'], 'data' => sito_iso((string)($pagina['publish_date'] ?: date('Y-m-d')), (string)($pagina['publish_time'] ?? '')), 'pillar' => ($pagina['type'] ?? '') === 'pillar', 'categorie' => sito_lista((string)($pagina['categorie'] ?? '')) ?: array_values(array_filter([(string)($pagina['category'] ?? '')]))];
        $soloArticolo = $albero['elementi'] && !array_filter($albero['elementi'], fn($s) => editor_valori('sezione', $s['impostazioni'] ?? [])['larghezza'] !== 'articolo');
        $corpo = $soloArticolo
            ? substr(vista_articolo_testa($c, $dis), 0, -6) . '<div class="art" style="grid-template-columns:minmax(0,1fr)"><article class="corpo"><div class="ed-pagina ed-articolo-pagina">' . $dis . '</div></article></div></div>'
            : vista_articolo_testa($c, $dis) . '<div class="ed-pagina">' . $dis . '</div>';
    } else {
        $corpo = '<div class="ed-pagina">' . $dis . '</div>';
    }
    $html = vista_pagina(['titolo' => 'Anteprima della versione ' . (int)$v['id'] . ': ' . $pagina['title'], 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []], $corpo, $testa, $percorso);
    echo preg_replace('~(<body[^>]*>)~', '$1' . $fascia, $html, 1);
    exit;
}

// ------------------------------------------------------------ dati per la pagina
$confronta = (int)($_GET['confronta'] ?? 0);
$con = (string)($_GET['con'] ?? 'precedente');
$confronto = null; $errConfronto = '';
if ($confronta) {
    try {
        $vb = editor_versione($confronta);
        if (!$vb) throw new RuntimeException('Versione non trovata.');
        if ($con === 'attuale') {
            $adesso = versioni_dati_attuali((string)$vb['ambito'], (int)$vb['oggetto_id']);
            if ($adesso === null) throw new RuntimeException('L\'oggetto di questa versione non esiste più: si può solo ripristinarlo.');
            $diff = editor_versioni_confronta($vb, ['ambito' => $vb['ambito'], 'oggetto_id' => $vb['oggetto_id'], 'dati' => $adesso]);
            $confronto = ['a' => $vb, 'b' => null, 'etA' => 'Versione ' . $vb['id'] . ' (' . versioni_data_breve((string)$vb['creato']) . ')', 'etB' => 'Adesso', 'diff' => $diff];
        } else {
            $va = $con === 'precedente' ? versioni_precedente($vb) : editor_versione((int)$con);
            if (!$va) { $confronto = ['a' => null, 'b' => $vb, 'etA' => '', 'etB' => '', 'diff' => null]; }
            else {
                if ((int)$va['id'] > (int)$vb['id']) [$va, $vb] = [$vb, $va];
                $diff = editor_versioni_confronta($va, $vb);
                $confronto = ['a' => $va, 'b' => $vb, 'etA' => 'Versione ' . $va['id'] . ' (' . versioni_data_breve((string)$va['creato']) . ')', 'etB' => 'Versione ' . $vb['id'] . ' (' . versioni_data_breve((string)$vb['creato']) . ')', 'diff' => $diff];
            }
        }
    } catch (Throwable $e) {
        $errConfronto = $e->getMessage();
    }
}

$perPagina = 40;
$paginaN = max(1, (int)($_GET['p'] ?? 1));
$totale = versioni_conta($ambito, $oggetto);
$righe = editor_versioni($ambito, $oggetto, $perPagina, ($paginaN - 1) * $perPagina);
$oggetti = $ambito !== '' && $ambito !== 'contenuto' && !in_array($ambito, ['stile', 'impostazioni'], true) ? versioni_oggetti($ambito) : [];
$csrf = csrf_token();
$conAnteprima = fn(array $r) => str_starts_with((string)$r['ambito'], 'contenuto:') || $r['ambito'] === 'modello';
$linkOggetto = function (array $r): string {
    $a = (string)$r['ambito']; $id = (int)$r['oggetto_id'];
    if (str_starts_with($a, 'contenuto:')) return 'editor.php?tipo=' . rawurlencode(substr($a, 10)) . '&id=' . $id;
    return ['modello' => 'editor.php?tipo=modelli&id=' . $id, 'menu' => 'menu.php?id=' . $id, 'stile' => 'design.php', 'tema' => 'editor-temi.php', 'impostazioni' => 'editor-impostazioni.php'][$a] ?? '';
};
require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/contenuti.css') ?>">
<style>
<?= versioni_confronto_css() ?>
.vs-filtri{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem}
.vs-filtri select{min-width:200px}
.vs-intro{color:var(--text-muted);font-size:.88rem;margin:0 0 1rem;max-width:760px}
.vs-box{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.2rem;margin-bottom:1.2rem}
.vs-box h2{margin:0 0 .6rem;font-size:1.05rem}
.vs-box .vs-colonne{display:grid;grid-template-columns:1fr 1fr;gap:1rem;font-size:.85rem;color:var(--text-muted);margin-bottom:.8rem}
.vs-azioni{display:flex;flex-wrap:wrap;gap:.3rem .7rem;font-size:.82rem;margin-top:.25rem}
.vs-azioni a,.vs-azioni button{color:var(--coral);background:none;border:0;padding:0;cursor:pointer;font:inherit;font-size:.82rem;text-decoration:none}
.vs-azioni a:hover,.vs-azioni button:hover{text-decoration:underline}
.vs-azioni form{display:inline}
.vs-nota-mano{font-weight:600;color:var(--text)}
.vs-riassunto-cella{color:var(--text-muted);font-size:.82rem}
.vs-pagine{display:flex;gap:.3rem;margin-top:.8rem;flex-wrap:wrap}
.vs-pagine a,.vs-pagine b{padding:.3rem .6rem;border:1px solid var(--border);border-radius:6px;text-decoration:none;font-size:.82rem}
.vs-pagine b{background:var(--coral);color:var(--su-accento);border-color:var(--coral)}
</style>

<p class="vs-intro">Ogni volta che salvi o pubblichi qualcosa con l'editor (una pagina, un articolo, un header, lo Stile del sito, un menu, un tema) resta una versione.
Qui le trovi tutte: puoi vedere <b>cosa è cambiato</b> fra due versioni, aprire l'<b>anteprima</b> di come era la pagina, e <b>ripristinare</b> una versione (quella di adesso non si perde: diventa a sua volta una versione).
Si tengono le ultime <?= versioni_limite() ?> per oggetto; le versioni pubblicate e quelle con una nota scritta a mano restano per sempre.</p>

<form method="get" class="vs-filtri">
  <label>Ambito
    <select name="ambito" data-al-cambio="vsCambiaAmbito(this)">
      <option value="">Tutti</option>
      <option value="contenuto" <?= $ambito === 'contenuto' ? 'selected' : '' ?>>Tutti i contenuti</option>
      <?php foreach ($ambiti as $k => $n): ?><option value="<?= h($k) ?>" <?= $ambito === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?>
    </select>
  </label>
  <?php if ($oggetti): ?>
  <label>Oggetto
    <select name="oggetto" data-invia-al-cambio>
      <option value="0">Tutti</option>
      <?php foreach ($oggetti as $oid => $nome): ?><option value="<?= (int)$oid ?>" <?= $oggetto === (int)$oid ? 'selected' : '' ?>><?= h($nome !== '' ? $nome : 'n. ' . $oid) ?></option><?php endforeach; ?>
    </select>
  </label>
  <?php elseif ($oggetto): ?><input type="hidden" name="oggetto" value="<?= $oggetto ?>"><?php endif; ?>
  <noscript><button type="submit" class="btn btn-secondary btn-sm">Filtra</button></noscript>
  <span style="color:var(--text-muted);font-size:.84rem"><?= $totale ?> <?= $totale === 1 ? 'versione' : 'versioni' ?></span>
</form>

<?php if ($errConfronto !== ''): ?>
<div class="vs-box"><h2>Confronto</h2><p class="vs-uguali"><?= h($errConfronto) ?></p></div>
<?php elseif ($confronto): ?>
<div class="vs-box" id="confronto">
  <h2>Confronto</h2>
  <?php if ($confronto['diff'] === null): ?>
    <p class="vs-uguali">La versione <?= (int)$confronto['b']['id'] ?> è la prima di questo oggetto: non c'è niente di precedente con cui confrontarla.
      <a href="versioni.php?confronta=<?= (int)$confronto['b']['id'] ?>&amp;con=attuale&amp;ambito=<?= h(rawurlencode($ambito)) ?>&amp;oggetto=<?= $oggetto ?>">Confrontala con adesso</a>.</p>
  <?php else: ?>
    <div class="vs-colonne">
      <div><b>Prima:</b> <?= h($confronto['etA']) ?><?= $confronto['a'] && $confronto['a']['nota'] ? ' · ' . h($confronto['a']['nota']) : '' ?><?= $confronto['a'] && $confronto['a']['autore'] ? ' · ' . h($confronto['a']['autore']) : '' ?></div>
      <div><b>Dopo:</b> <?= h($confronto['etB']) ?><?= $confronto['b'] && $confronto['b']['nota'] ? ' · ' . h($confronto['b']['nota']) : '' ?><?= $confronto['b'] && $confronto['b']['autore'] ? ' · ' . h($confronto['b']['autore']) : '' ?></div>
    </div>
    <?= versioni_confronto_html($confronto['diff'], 'Prima', 'Dopo') ?>
  <?php endif; ?>
  <p style="margin:.8rem 0 0;font-size:.84rem"><a href="<?= h($indietro) ?>">Chiudi il confronto</a></p>
</div>
<?php endif; ?>

<div class="admin-table-wrap ct-tabella">
  <table>
    <thead><tr><th style="width:150px">Quando</th><?php if (!$oggetto): ?><th>Oggetto</th><?php endif; ?><th>Nota</th><th>Autore</th><th>Cosa è cambiato</th></tr></thead>
    <tbody>
    <?php if (!$righe): ?><tr><td colspan="5"><div class="empty-state"><p>Nessuna versione qui. La prima nasce al primo salvataggio.</p></div></td></tr><?php endif; ?>
    <?php foreach ($righe as $r): $torna = $indietro . ($paginaN > 1 ? (str_contains($indietro, '?') ? '&' : '?') . 'p=' . $paginaN : ''); $filtro = '&amp;ambito=' . h(rawurlencode($ambito)) . '&amp;oggetto=' . $oggetto; ?>
      <tr class="ct-riga">
        <td class="ct-muto"><b style="color:var(--text)"><?= h(versioni_data_breve((string)$r['creato'])) ?></b><br><small>n. <?= (int)$r['id'] ?><?= $r['pubblicata'] ? ' · <span class="badge badge-published">pubblicata</span>' : '' ?></small></td>
        <?php if (!$oggetto): ?>
        <td><span class="badge badge-cluster"><?= h(versioni_ambito_etichetta((string)$r['ambito'])) ?></span>
          <?php $lk = $linkOggetto($r); $nomeOgg = $r['oggetto_nome'] !== '' ? $r['oggetto_nome'] : versioni_nome_oggetto((string)$r['ambito'], (int)$r['oggetto_id']); ?>
          <?php if (!in_array($r['ambito'], ['stile', 'impostazioni'], true)): ?><br><a href="versioni.php?ambito=<?= h(rawurlencode((string)$r['ambito'])) ?>&amp;oggetto=<?= (int)$r['oggetto_id'] ?>" title="Solo le versioni di questo oggetto"><?= h($nomeOgg) ?></a><?php endif; ?>
          <?php if ($lk !== ''): ?> <small><a href="<?= h($lk) ?>" style="color:var(--text-muted)">apri</a></small><?php endif; ?>
        </td>
        <?php endif; ?>
        <td class="<?= $r['nota_mano'] ? 'vs-nota-mano' : 'ct-muto' ?>" style="font-size:.86rem"><?= h($r['nota']) ?: '–' ?>
          <div class="vs-azioni">
            <a href="versioni.php?confronta=<?= (int)$r['id'] ?>&amp;con=precedente<?= $filtro ?>#confronto">Confronta con la precedente</a>
            <a href="versioni.php?confronta=<?= (int)$r['id'] ?>&amp;con=attuale<?= $filtro ?>#confronto">con l'attuale</a>
            <?php if ($conAnteprima($r)): ?><a href="versioni.php?anteprima=<?= (int)$r['id'] ?><?= $filtro ?>" target="_blank">Anteprima</a><?php endif; ?>
            <form method="post" data-conferma="Ripristino la versione <?= (int)$r['id'] ?> del <?= h((versioni_data_breve((string)$r['creato']))) ?>. Lo stato di adesso non si perde: resta come versione. Confermi?"><?= csrf_field() ?><input type="hidden" name="azione" value="ripristina"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="torna" value="<?= h($torna) ?>"><button type="submit">Ripristina</button></form>
            <button type="button" data-al-clic="vsNota(<?= (int)$r['id'] ?>, <?= h(json_encode($r['nota_mano'] ? $r['nota'] : '', JSON_UNESCAPED_UNICODE)) ?>)"><?= $r['nota_mano'] ? 'Cambia nota' : 'Aggiungi nota' ?></button>
          </div>
        </td>
        <td class="ct-muto" style="font-size:.86rem"><?= h($r['autore']) ?: '–' ?></td>
        <td class="vs-riassunto-cella"><?= h(versioni_riassunto_di($r)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if ($totale > $perPagina): ?>
<div class="vs-pagine">
  <?php for ($i = 1; $i <= (int)ceil($totale / $perPagina); $i++): $u = $indietro . (str_contains($indietro, '?') ? '&' : '?') . 'p=' . $i; ?>
    <?php if ($i === $paginaN): ?><b><?= $i ?></b><?php else: ?><a href="<?= h($u) ?>"><?= $i ?></a><?php endif; ?>
  <?php endfor; ?>
</div>
<?php endif; ?>

<form method="post" id="vs-form-nota" hidden><?= csrf_field() ?><input type="hidden" name="azione" value="nota"><input type="hidden" name="id"><input type="hidden" name="testo"><input type="hidden" name="torna" value="<?= h($indietro) ?>"></form>
<script<?= nonce_attr() ?>>
function vsCambiaAmbito(el) { if (el.form.oggetto) el.form.oggetto.value = ''; el.form.submit(); }
function vsNota(id, nota) {
  var t = prompt('Una nota per ricordare questa versione (con una nota non viene mai cancellata):', nota || '');
  if (t === null) return;
  var f = document.getElementById('vs-form-nota'); f.id.value = id; f.testo.value = t; f.submit();
}
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
