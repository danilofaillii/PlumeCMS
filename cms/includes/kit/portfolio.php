<?php
/**
 * Kit di partenza "Portfolio" (1.3.0): per chi mostra i suoi lavori (grafica, fotografia, architettura, artigianato).
 * Le immagini sono disegni astratti scritti dal kit in uploads/kit/, da sostituire con le foto vere dei lavori.
 * Solo dati: la forma e spiegata in includes/kit-partenza.php.
 */
$lavoro = fn(int $n, string $titolo, string $tipo) => ['riquadro_immagine', ['immagine' => '{immagine:' . $n . '}', 'alt' => $titolo, 'titolo' => $titolo, 'testo' => $tipo, 'link' => ['url' => '/lavori/', 'nuova' => false], 'margine_sotto' => ['d' => 0]]];
return [
    'nome' => 'Portfolio',
    'descrizione' => 'Per mostrare i tuoi lavori: una griglia di progetti, come lavori, chi sei. Con disegni da sostituire con le tue foto.',
    'ordine' => 40,
    'form' => ['titolo' => 'Hai un progetto?', 'intro' => 'Raccontamelo in due righe: ti rispondo con qualche domanda.'],
    'home' => [
        ['imp' => ['spazio_sopra' => ['d' => 48], 'spazio_sotto' => ['d' => 40]], 'widget' => [
            ['hero', ['disposizione' => 'mosaico', 'occhiello' => '{sito}', 'titolo' => 'Progetti che *si fanno guardare.*',
                'testo' => '{descrizione}',
                'pulsanti' => [['testo' => 'Guarda i lavori', 'link' => '/lavori/', 'stile' => 'viola'], ['testo' => 'Contattami', 'link' => '/contatti/', 'stile' => 'filo']],
                'immagine' => '{immagine:3}', 'foto_trattamento' => 'naturale', 'etichetta_onda' => 'Nuovo', 'barre' => [['etichetta' => 'Progetti', 'valore' => 85], ['etichetta' => 'Clienti felici', 'valore' => 95]], 'margine_sotto' => ['d' => 0]]],
        ]],
        ['imp' => ['spazio_sotto' => ['d' => 0, 'm' => 0]], 'widget' => [
            ['titolo', ['occhiello' => 'Lavori scelti', 'testo' => 'Gli ultimi progetti', 'livello' => 'h2', 'margine_sotto' => ['d' => 28]]],
        ]],
        ['imp' => ['spazio_sopra' => ['d' => 0, 'm' => 0]], 'colonne' => [
            [$lavoro(4, 'Il primo progetto', 'Il tipo di lavoro, il cliente, l\'anno')],
            [$lavoro(5, 'Il secondo progetto', 'Il tipo di lavoro, il cliente, l\'anno')],
            [$lavoro(6, 'Il terzo progetto', 'Il tipo di lavoro, il cliente, l\'anno')],
        ]],
        ['pronta' => 'testimonianze-una'],
        ['pronta' => 'servizi-passi'],
        ['pronta' => 'invito-fascia'],
    ],
    'pagine' => [
        ['titolo' => 'Lavori', 'slug' => 'lavori', 'sezioni' => [
            ['imp' => ['spazio_sopra' => ['d' => 40], 'spazio_sotto' => ['d' => 24]], 'widget' => [
                ['hero', ['disposizione' => 'banner', 'occhiello' => 'Lavori', 'titolo' => 'Tutti i progetti', 'testo' => 'Fai clic su un\'immagine per vederla grande.',
                    'pulsanti' => [['testo' => 'Parliamo del tuo', 'link' => '/contatti/', 'stile' => 'viola']], 'immagine' => '', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
            ]],
            ['widget' => [
                ['galleria', ['immagini' => [
                    ['immagine' => '{immagine:1}', 'alt' => 'Il primo progetto', 'didascalia' => 'Il primo progetto'], ['immagine' => '{immagine:2}', 'alt' => 'Il secondo progetto', 'didascalia' => 'Il secondo progetto'],
                    ['immagine' => '{immagine:3}', 'alt' => 'Il terzo progetto', 'didascalia' => 'Il terzo progetto'], ['immagine' => '{immagine:4}', 'alt' => 'Il quarto progetto', 'didascalia' => 'Il quarto progetto'],
                    ['immagine' => '{immagine:5}', 'alt' => 'Il quinto progetto', 'didascalia' => 'Il quinto progetto'], ['immagine' => '{immagine:6}', 'alt' => 'Il sesto progetto', 'didascalia' => 'Il sesto progetto'],
                    ['immagine' => '{immagine:7}', 'alt' => 'Il settimo progetto', 'didascalia' => 'Il settimo progetto'], ['immagine' => '{immagine:8}', 'alt' => 'L\'ottavo progetto', 'didascalia' => 'L\'ottavo progetto'],
                ], 'colonne' => ['d' => 4, 't' => 2, 'm' => 1], 'didascalia' => 'hover', 'margine_sotto' => ['d' => 0]]],
            ]],
        ]],
        ['titolo' => 'Chi sono', 'slug' => 'chi-sono', 'sezioni' => [
            ['imp' => ['spazio_sopra' => ['d' => 40], 'spazio_sotto' => ['d' => 24]], 'widget' => [
                ['hero', ['disposizione' => 'banner', 'occhiello' => 'Chi sono', 'titolo' => 'Come lavoro', 'testo' => 'Il mio modo di fare le cose, e le persone con cui le faccio.',
                    'pulsanti' => [['testo' => 'Scrivimi', 'link' => '/contatti/', 'stile' => 'viola']], 'immagine' => '', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
            ]],
            ['imp' => ['larghezza' => 'stretta'], 'widget' => [
                ['presentazione', ['immagine' => '{immagine:7}', 'foto_trattamento' => 'naturale', 'occhiello' => 'Chi sono', 'titolo' => 'Piacere, {sito}',
                    'testo' => "Racconta come lavori e cosa ti piace fare. Chi guarda un portfolio vuole capire il tuo modo di vedere le cose.\nAggiungi i clienti con cui hai lavorato, se puoi citarli.",
                    'voci' => [['etichetta' => 'Faccio', 'valore' => 'il tuo mestiere'], ['etichetta' => 'Dove', 'valore' => 'la tua città, o ovunque'], ['etichetta' => 'Dal', 'valore' => 'l\'anno in cui hai cominciato']], 'margine_sotto' => ['d' => 0]]],
            ]],
            ['pronta' => 'numeri-barre'],
            ['pronta' => 'invito-lato'],
        ]],
        ['titolo' => 'Contatti', 'slug' => 'contatti', 'sezioni' => [
            ['imp' => ['spazio_sopra' => ['d' => 40], 'spazio_sotto' => ['d' => 24]], 'widget' => [
                ['hero', ['disposizione' => 'banner', 'occhiello' => 'Contatti', 'titolo' => 'Parliamo del tuo progetto', 'testo' => 'Tempi, idee, budget: raccontami da dove partiamo.',
                    'pulsanti' => [], 'immagine' => '', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
            ]],
            ['pronta' => 'contatti-form'],
        ]],
    ],
    'menu' => [['Home', '/'], ['Lavori', '/lavori/'], ['Chi sono', '/chi-sono/'], ['Blog', '/blog/'], ['Contatti', '/contatti/']],
];
