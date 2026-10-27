<?php
/**
 * Kit di partenza "Attività" (1.3.0): il sito di un negozio, uno studio, una piccola impresa. Servizi con i prezzi,
 * chi siamo, contatti. Solo dati: la forma e spiegata in includes/kit-partenza.php.
 */
return [
    'nome' => 'Attività',
    'descrizione' => 'Per un negozio, uno studio, una piccola impresa: servizi e prezzi, chi siamo, come trovarci.',
    'ordine' => 20,
    'form' => ['titolo' => 'Scrivici', 'intro' => 'Ti rispondiamo entro un giorno lavorativo.'],
    'home' => [
        ['imp' => ['spazio_sopra' => ['d' => 32], 'spazio_sotto' => ['d' => 40]], 'widget' => [
            ['hero', ['disposizione' => 'copertina', 'occhiello' => '{sito}', 'titolo' => 'Il vostro lavoro in una frase, *detta bene.*',
                'testo' => '{descrizione}',
                'pulsanti' => [['testo' => 'Chiedi un preventivo', 'link' => '/contatti/', 'stile' => 'viola'], ['testo' => 'I servizi', 'link' => '/servizi/', 'stile' => 'filo']],
                'immagine' => '{immagine:2}', 'foto_trattamento' => 'naturale', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
        ]],
        ['pronta' => 'numeri-fascia'],
        ['pronta' => 'servizi-icone'],
        ['pronta' => 'testimonianze-carosello'],
        ['pronta' => 'invito-lato'],
    ],
    'pagine' => [
        ['titolo' => 'Servizi', 'slug' => 'servizi', 'sezioni' => [
            ['imp' => ['spazio_sopra' => ['d' => 40], 'spazio_sotto' => ['d' => 24]], 'widget' => [
                ['hero', ['disposizione' => 'banner', 'occhiello' => 'Servizi', 'titolo' => 'Cosa facciamo, e quanto costa', 'testo' => 'Tutti i servizi in una pagina, con i prezzi.',
                    'pulsanti' => [['testo' => 'Chiedi un preventivo', 'link' => '/contatti/', 'stile' => 'viola']], 'immagine' => '', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
            ]],
            ['pronta' => 'servizi-elenco'],
            ['pronta' => 'servizi-passi'],
            ['pronta' => 'prezzi-tre-piani'],
            ['pronta' => 'faq-due-colonne'],
        ]],
        ['titolo' => 'Chi siamo', 'slug' => 'chi-siamo', 'sezioni' => [
            ['imp' => ['spazio_sopra' => ['d' => 40], 'spazio_sotto' => ['d' => 24]], 'widget' => [
                ['hero', ['disposizione' => 'banner', 'occhiello' => 'Chi siamo', 'titolo' => 'Le persone dietro {sito}', 'testo' => 'Una riga su chi siete e da quanto tempo.',
                    'pulsanti' => [], 'immagine' => '', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
            ]],
            ['pronta' => 'chi-storia'],
            ['pronta' => 'chi-valori'],
            ['pronta' => 'invito-fascia'],
        ]],
        ['titolo' => 'Contatti', 'slug' => 'contatti', 'sezioni' => [
            ['imp' => ['spazio_sopra' => ['d' => 40], 'spazio_sotto' => ['d' => 24]], 'widget' => [
                ['hero', ['disposizione' => 'banner', 'occhiello' => 'Contatti', 'titolo' => 'Come trovarci', 'testo' => 'Scrivici, chiamaci o passa a trovarci.',
                    'pulsanti' => [], 'immagine' => '', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
            ]],
            ['pronta' => 'contatti-riquadri'],
            ['pronta' => 'contatti-solo-form'],
        ]],
    ],
    'menu' => [['Home', '/'], ['Servizi', '/servizi/'], ['Chi siamo', '/chi-siamo/'], ['Blog', '/blog/'], ['Contatti', '/contatti/']],
];
