<?php
// La cartella del CMS. Se è la radice del dominio, serve il sito pubblico; se è una sottocartella (per esempio /cms/),
// il sito vive nella radice e qui c'è solo il pannello: si rimanda alla home. Senza installazione, config.php apre l'installatore.
require_once __DIR__ . '/includes/config.php';
// Il sito si serve qui quando il suo indirizzo è questa cartella: radice del dominio, oppure una cartella come /test
// (dalla 1.1.7; prima una cartella veniva sempre rimandata alla radice). Altrimenti questa è la cartella del solo
// pannello (per esempio /cms) e si va alla home del sito.
if (cms_sito_base() === cms_base()) {
    require __DIR__ . '/sito/index.php';
    exit;
}
// 302 e mai 301: il rimando dipende dalla configurazione, e un 301 resta nel browser anche dopo averla cambiata
// (fino alla 1.1.6 /test/ veniva rimandata alla radice con un 301, e i browser continuavano a farlo).
header('Cache-Control: no-store');
header('Location: ' . cms_url_sito() . '/', true, 302);
exit;
