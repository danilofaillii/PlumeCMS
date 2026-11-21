<?php
/**
 * Lavagna: la pagina "non trovata". Una vista ritorna l'HTML del contenuto della pagina come stringa
 * (header, footer e <head> li mette il CMS). Il codice di risposta resta 404.
 * Funzioni del CMS usate: h() per scrivere testo sicuro, sito_ultimi() e vista_griglia() per gli ultimi articoli,
 * vista_testa_sezione() per l'occhiello, il titolo e l'introduzione con le classi del sito.
 */
$ultimi = function_exists('sito_ultimi') ? sito_ultimi(3) : [];
$html = '<div class="wrap" style="padding:96px 0 72px">'
    . vista_testa_sezione('Errore 404', 'Qui la lavagna è pulita.', 'La pagina che cercavi non c&#039;è, o ha cambiato indirizzo.')
    . '<p><a class="btn btn-viola" href="/">Torna alla home</a></p>';
if ($ultimi) {
    $html .= '<h2 class="sez-titolo" style="margin-top:56px;font-size:28px">Intanto, gli ultimi articoli</h2>'
        . '<div style="margin-top:24px">' . vista_griglia($ultimi) . '</div>';
}
return $html . '</div>';
