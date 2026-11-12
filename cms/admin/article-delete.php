<?php
// Prima cancellava per sempre. Ora si passa dal cestino: contenuti.php.
header('Location: contenuti.php?tipo=articoli', true, 301);
exit;
