<?php
/**
 * Kit di partenza "Personale" (1.3.0): il sito di una persona che offre il suo lavoro (consulente, insegnante,
 * libero professionista). Solo dati: la forma e spiegata in includes/kit-partenza.php.
 */
return [
    'nome' => 'Personale',
    'descrizione' => 'Il sito di una persona: chi sei, cosa offri, cosa dicono di te, come contattarti. Con il blog.',
    'ordine' => 10,
    'form' => ['titolo' => 'Scrivimi', 'intro' => 'Rispondo di solito entro un giorno.'],
    'home' => [
        ['imp' => ['spazio_sopra' => ['d' => 48], 'spazio_sotto' => ['d' => 40]], 'widget' => [
            ['hero', ['disposizione' => 'scheda', 'occhiello' => 'Ciao, sono {sito}', 'titolo' => 'Ti aiuto a fare *una cosa precisa*, meglio.',
                'testo' => '{descrizione}',
                'pulsanti' => [['testo' => 'Lavoriamo insieme', 'link' => '/contatti/', 'stile' => 'viola'], ['testo' => 'Chi sono', 'link' => '/chi-sono/', 'stile' => 'filo']],
                'immagine' => '{immagine:1}', 'foto_trattamento' => 'naturale', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
        ]],
        ['widget' => [
            ['titolo', ['occhiello' => 'Cosa faccio', 'testo' => 'Tre modi in cui *posso esserti utile*', 'livello' => 'h2', 'sottotitolo' => 'Scrivi qui, in una frase, a chi ti rivolgi e cosa cambia dopo aver lavorato con te.', 'margine_sotto' => ['d' => 28]]],
            ['schede', ['voci' => [
                ['titolo' => 'Il primo servizio', 'testo' => 'Cosa comprende, quanto dura, a chi serve. Due righe bastano.', 'link' => '/contatti/', 'invito' => 'Chiedimi come', 'evidenza' => true],
                ['titolo' => 'Il secondo servizio', 'testo' => 'Cosa comprende, quanto dura, a chi serve. Due righe bastano.', 'link' => '/contatti/', 'invito' => 'Chiedimi come', 'evidenza' => false],
                ['titolo' => 'Il terzo servizio', 'testo' => 'Cosa comprende, quanto dura, a chi serve. Due righe bastano.', 'link' => '/contatti/', 'invito' => 'Chiedimi come', 'evidenza' => false],
            ], 'colonne' => ['d' => 3, 't' => 2, 'm' => 1], 'margine_sotto' => ['d' => 0]]],
        ]],
        ['pronta' => 'testimonianze-tre'],
        ['widget' => [
            ['titolo', ['occhiello' => 'Dal blog', 'testo' => 'Gli ultimi articoli', 'livello' => 'h2', 'margine_sotto' => ['d' => 28]]],
            ['articoli', ['numero' => 3, 'solo_pillar' => false, 'margine_sotto' => ['d' => 0]]],
        ]],
        ['pronta' => 'invito-fascia'],
    ],
    'pagine' => [
        ['titolo' => 'Chi sono', 'slug' => 'chi-sono', 'sezioni' => [
            ['imp' => ['spazio_sopra' => ['d' => 40], 'spazio_sotto' => ['d' => 24]], 'widget' => [
                ['hero', ['disposizione' => 'banner', 'occhiello' => 'Chi sono', 'titolo' => 'Piacere, sono {sito}', 'testo' => 'Qui racconti la tua storia in breve.',
                    'pulsanti' => [['testo' => 'Scrivimi', 'link' => '/contatti/', 'stile' => 'viola']], 'immagine' => '', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
            ]],
            ['imp' => ['larghezza' => 'stretta'], 'widget' => [
                ['presentazione', ['immagine' => '{immagine:1}', 'foto_trattamento' => 'naturale', 'occhiello' => 'La mia storia', 'titolo' => 'Da dove vengo, *dove vado*',
                    'testo' => "Racconta in tre o quattro frasi come sei arrivato a fare questo lavoro.\nPoi di' cosa ti distingue: un metodo, un'esperienza, un modo di stare con le persone.",
                    'voci' => [['etichetta' => 'Dal', 'valore' => 'l\'anno in cui hai cominciato'], ['etichetta' => 'Dove', 'valore' => 'la tua città, o online'], ['etichetta' => 'Per chi', 'valore' => 'le persone che aiuti']], 'margine_sotto' => ['d' => 0]]],
            ]],
            ['pronta' => 'chi-valori'],
            ['pronta' => 'invito-lato'],
        ]],
        ['titolo' => 'Contatti', 'slug' => 'contatti', 'sezioni' => [
            ['imp' => ['spazio_sopra' => ['d' => 40], 'spazio_sotto' => ['d' => 24]], 'widget' => [
                ['hero', ['disposizione' => 'banner', 'occhiello' => 'Contatti', 'titolo' => 'Parliamone', 'testo' => 'Raccontami cosa ti serve: ti rispondo io, di persona.',
                    'pulsanti' => [], 'immagine' => '', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
            ]],
            ['pronta' => 'contatti-form'],
            ['pronta' => 'faq-semplice'],
        ]],
    ],
    'menu' => [['Home', '/'], ['Chi sono', '/chi-sono/'], ['Blog', '/blog/'], ['Contatti', '/contatti/']],
];
