<?php
// Schermata di prima: gli aforismi stanno in contenuti.php e contenuto.php, come articoli e pagine.
header('Location: contenuto.php?tipo=aforismi' . (isset($_GET['id']) ? '&id=' . (int)$_GET['id'] : ''), true, 301);
exit;
