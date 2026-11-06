<?php
// Non più in uso dal 23 ottobre 2026: serviva al sito statico su Cloudflare.
// Il sito nuovo legge direttamente dal database (sito/index.php). Il file resta vuoto
// perché un pacchetto può sovrascrivere un file sul server, non cancellarlo.
http_response_code(410);
