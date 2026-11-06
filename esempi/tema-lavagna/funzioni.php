<?php
/**
 * Lavagna: il PHP del tema. Ritorna filtri ed eventi, come un modulo (docs/cms-moduli.md).
 * Gira solo sul sito pubblico, mai nel pannello. Un errore qui spegne il tema, non il sito.
 */
return [
    'filtri' => [
        // llms.txt: una riga in più nell'intestazione, per dire che il sito usa questo tema.
        'llms_intestazione' => function ($righe) {
            if (!is_array($righe)) return $righe;
            $righe[] = '> Aspetto: tema Lavagna di plumeCMS.';
            return $righe;
        },
    ],
    'eventi' => [],
];
