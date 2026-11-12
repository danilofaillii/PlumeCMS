<?php
// Schermata di prima: ora articoli e pagine stanno in contenuti.php e contenuto.php, uguali per tutti e due.
header('Location: contenuti.php?tipo=articoli' . (isset($_GET['category']) ? '&categoria=' . urlencode($_GET['category']) : ''), true, 301);
exit;
