<?php
/**
 * Kit di partenza "Blog" (1.3.0): un sito per scrivere. La home mostra gli articoli; tre articoli d'esempio spiegano
 * come si scrive, si programma e si ordina. Solo dati: la forma e spiegata in includes/kit-partenza.php.
 */
return [
    'nome' => 'Blog',
    'descrizione' => 'Per chi scrive: gli articoli in primo piano nella home, chi scrive, i contatti. Con tre articoli d\'esempio.',
    'ordine' => 30,
    'form' => ['titolo' => 'Scrivimi', 'intro' => 'Leggo tutto, rispondo appena posso.'],
    'home' => [
        ['imp' => ['spazio_sopra' => ['d' => 56], 'spazio_sotto' => ['d' => 24]], 'widget' => [
            ['hero', ['disposizione' => 'minimale', 'occhiello' => '{sito}', 'titolo' => 'Storie, idee e appunti *su quello che conta.*',
                'testo' => '{descrizione}',
                'pulsanti' => [['testo' => 'Leggi gli articoli', 'link' => '/blog/', 'stile' => 'viola']], 'immagine' => '', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
        ]],
        ['widget' => [
            ['titolo', ['occhiello' => 'Gli ultimi', 'testo' => 'Appena usciti', 'livello' => 'h2', 'margine_sotto' => ['d' => 28]]],
            ['griglia_contenuti', ['fonte' => 'articoli', 'quanti' => 6, 'aspetto' => 'classico', 'colonne' => ['d' => 3, 't' => 2, 'm' => 1], 'escludi_corrente' => false, 'mostra_immagine' => false, 'margine_sotto' => ['d' => 0]]],
        ]],
        ['imp' => ['larghezza' => 'stretta'], 'widget' => [
            ['presentazione', ['immagine' => '', 'occhiello' => 'Chi scrive', 'titolo' => 'Due righe su di te',
                'testo' => 'Chi sei, di cosa scrivi, ogni quanto esce un articolo nuovo. Chi legge torna se sa cosa aspettarsi.',
                'voci' => [['etichetta' => 'Scrivo di', 'valore' => 'i tuoi argomenti'], ['etichetta' => 'Ogni', 'valore' => 'quanto pubblichi']], 'margine_sotto' => ['d' => 0]]],
        ]],
        ['pronta' => 'invito-lato'],
    ],
    'pagine' => [
        ['titolo' => 'Chi sono', 'slug' => 'chi-sono', 'sezioni' => [
            ['imp' => ['spazio_sopra' => ['d' => 40], 'spazio_sotto' => ['d' => 24]], 'widget' => [
                ['hero', ['disposizione' => 'banner', 'occhiello' => 'Chi sono', 'titolo' => 'Chi c\'è dietro {sito}', 'testo' => 'La persona che scrive, in breve.',
                    'pulsanti' => [['testo' => 'Scrivimi', 'link' => '/contatti/', 'stile' => 'viola']], 'immagine' => '', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
            ]],
            ['imp' => ['larghezza' => 'articolo'], 'widget' => [
                ['testo', ['contenuto' => '<p>Qui racconti chi sei e perché scrivi. Due o tre paragrafi bastano: da dove vieni, di cosa ti occupi, cosa troverà chi legge.</p><p>Questa pagina è un esempio: aprila nell\'editor e riscrivila con le tue parole.</p>', 'margine_sotto' => ['d' => 0]]],
            ]],
        ]],
        ['titolo' => 'Contatti', 'slug' => 'contatti', 'sezioni' => [
            ['imp' => ['spazio_sopra' => ['d' => 40], 'spazio_sotto' => ['d' => 24]], 'widget' => [
                ['hero', ['disposizione' => 'banner', 'occhiello' => 'Contatti', 'titolo' => 'Scrivimi', 'testo' => 'Un\'idea, una domanda, un errore da segnalare: leggo tutto.',
                    'pulsanti' => [], 'immagine' => '', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
            ]],
            ['pronta' => 'contatti-solo-form'],
        ]],
    ],
    'articoli' => [
        ['titolo' => 'Come si scrive un articolo', 'slug' => 'come-si-scrive-un-articolo', 'estratto' => 'Titolo, testo, capitoli e immagine in evidenza: un giro veloce della scheda dell\'articolo.',
            'contenuto' => '<h2>La scheda dell\'articolo</h2><p>Dal pannello, voce Articoli, premi Aggiungi. Scrivi il titolo, poi il testo: i titoli di secondo livello diventano i capitoli del sommario.</p><h2>L\'immagine in evidenza</h2><p>Compare in cima all\'articolo e nelle anteprime sui social. Scegline una larga, almeno 1200 pixel.</p><h2>Prima di pubblicare</h2><p>Guarda l\'anteprima, controlla il riassunto per Google, poi premi Pubblica.</p>'],
        ['titolo' => 'Programmare un articolo', 'slug' => 'programmare-un-articolo', 'estratto' => 'Scrivi oggi, esce domani: data e ora di uscita, e il sito fa il resto.',
            'contenuto' => '<h2>Data e ora</h2><p>Ogni articolo ha una data e un\'ora di uscita. Se le metti nel futuro, l\'articolo resta nascosto e compare da solo al momento giusto.</p><h2>Un ritmo regolare</h2><p>Chi legge torna se sa quando aspettarsi un articolo nuovo: una volta a settimana, o al mese. Meglio poco ma sempre.</p>'],
    ],
    'menu' => [['Home', '/'], ['Blog', '/blog/'], ['Chi sono', '/chi-sono/'], ['Contatti', '/contatti/']],
];
