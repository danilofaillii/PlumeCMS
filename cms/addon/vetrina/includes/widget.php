<?php
/**
 * I widget della Vetrina (addon, 2.0.0), gruppo "Negozio" dell'editor:
 *   vetrina_prodotti  la griglia dei prodotti: in evidenza, di una categoria, gli ultimi arrivati o in offerta;
 *   vetrina_carrello  l'icona del carrello con il numero di articoli (da mettere nell'header).
 * Il foglio è addon/vetrina/assets/vetrina.css (il percorso parte da sito/assets/, come per i widget del nucleo).
 */
require_once __DIR__ . '/sito.php';

/** Le categorie per la tendina del widget. Un id che non c'è più resta (accetta) e il widget mostra tutti i prodotti. */
function vt_widget_categorie(): array {
    $o = ['' => 'Tutte le categorie'];
    try { foreach (vt_categorie() as $c) $o[(string)$c['id']] = (string)$c['nome']; } catch (Throwable $e) {}
    return $o;
}

$vtCss = ['css' => '../../addon/vetrina/assets/vetrina.css'];

editor_registra_widget('vetrina_prodotti', ['etichetta' => 'Prodotti', 'icona' => function_exists('icona_svg') ? (icona_svg('borsa') ?: '&#9632;') : '&#9632;', 'gruppo' => 'Negozio', 'controlli' => [
    ec('quali', 'scelta', 'Quali prodotti', 'evidenza', 'contenuto', ['sezione' => 'Prodotti', 'opzioni' => ['evidenza' => 'In evidenza', 'categoria' => 'Di una categoria', 'ultimi' => 'Gli ultimi arrivati', 'offerta' => 'In offerta (con lo sconto)']]),
    ec('categoria', 'scelta', 'Categoria', '', 'contenuto', ['sezione' => 'Prodotti', 'opzioni' => vt_widget_categorie(), 'accetta' => '/^\d{0,10}$/', 'condizione' => ['quali' => 'categoria'], 'aiuto' => 'Le categorie le crei in Vetrina > Categorie.']),
    ec('quanti', 'numero', 'Quanti al massimo', 6, 'contenuto', ['sezione' => 'Prodotti', 'min' => 1, 'max' => 24]),
    ec('colonne', 'numero', 'Colonne (al computer)', 3, 'contenuto', ['sezione' => 'Prodotti', 'min' => 1, 'max' => 6]),
    ec('pulsante', 'testo', 'Testo del link al negozio (vuoto: niente link)', 'Vedi tutti i prodotti', 'contenuto', ['sezione' => 'Prodotti']),
]], function (array $v, array $ctx): string {
    $f = ['limite' => 200];
    $quali = (string)($v['quali'] ?? 'evidenza');
    if ($quali === 'evidenza') $f['evidenza'] = true;
    if ($quali === 'categoria' && ctype_digit((string)($v['categoria'] ?? ''))) $f['categoria'] = (int)$v['categoria'];
    if ($quali === 'ultimi') $f['ordina'] = 'nuovi';
    try { $tutti = vt_prodotti($f); } catch (Throwable $e) { return '<div class="ed-vuoto">La vetrina non risponde.</div>'; }
    if ($quali === 'offerta') $tutti = array_values(array_filter($tutti, fn($p) => vt_prezzo_pieno($p) !== null));
    $quanti = max(1, min(24, (int)($v['quanti'] ?? 6)));
    $p = array_slice($tutti, 0, $quanti);
    if (!$p) return !empty($ctx['anteprima']) ? '<div class="ed-vuoto">Nessun prodotto da mostrare: aggiungine in Vetrina &gt; Prodotti (e segna «In evidenza» quelli da mettere qui).</div>' : '';
    $col = max(1, min(6, (int)($v['colonne'] ?? 3)));
    $btn = trim((string)($v['pulsante'] ?? ''));
    return '<div class="vt vt-widget" style="--vt-col:' . $col . ';padding:0">' . vt_griglia_html($p)
        . ($btn !== '' ? '<p style="text-align:center;margin-top:20px"><a class="btn btn-viola" href="' . h(vt_base()) . '">' . h($btn) . '</a></p>' : '') . '</div>';
});
editor_registra_asset('vetrina_prodotti', $vtCss);

editor_registra_widget('vetrina_carrello', ['etichetta' => 'Carrello', 'icona' => function_exists('icona_svg') ? (icona_svg('carrello') ?: '&#9632;') : '&#9632;', 'gruppo' => 'Negozio', 'controlli' => [
    ec('testo', 'testo', 'Testo accanto all\'icona (vuoto: solo l\'icona)', 'Carrello', 'contenuto', ['sezione' => 'Carrello']),
    ec('allinea', 'scelta', 'Allineamento', '', 'contenuto', ['sezione' => 'Carrello', 'opzioni' => ['' => 'Sinistra', 'center' => 'Centro', 'right' => 'Destra']]),
]], function (array $v, array $ctx): string {
    $n = vt_carrello_unita();
    $testo = trim((string)($v['testo'] ?? ''));
    $al = in_array($v['allinea'] ?? '', ['center', 'right'], true) ? $v['allinea'] : 'left';
    return '<div style="text-align:' . $al . '"><a class="vt-carrello-w" href="' . h(vt_base() . 'carrello/') . '" aria-label="Carrello, ' . $n . ' ' . ($n === 1 ? 'articolo' : 'articoli') . '">'
        . (function_exists('icona_svg') ? icona_svg('carrello') : '') . ($testo !== '' ? '<span>' . h($testo) . '</span>' : '') . '<span class="vt-conta">' . $n . '</span></a></div>';
});
editor_registra_asset('vetrina_carrello', $vtCss);
