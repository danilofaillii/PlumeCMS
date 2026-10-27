<?php
// Schermata di prima: ora articoli e pagine stanno in contenuti.php e contenuto.php, uguali per tutti e due.
header('Location: contenuto.php?tipo=articoli' . (isset($_GET['id']) ? '&id=' . (int)$_GET['id'] : ''), true, 301);
exit;
