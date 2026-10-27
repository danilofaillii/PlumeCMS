<?php
/**
 * Le sezioni pronte (1.3.0): solo dati, nella forma corta spiegata in includes/sezioni-pronte.php.
 * id => ['categoria' => ..., 'nome' => ..., 'descrizione' => ..., 'sezioni' => [ ... ]]
 *
 * Testi d'esempio brevi e neutri, scritti per farsi sostituire: dicono cosa ci va. Colori solo dalle variabili
 * globali e dagli sfondi che seguono il tema (vetro, gradiente). Nessuna immagine: dove servirebbe una foto, la
 * variante ne fa a meno oppure lascia il campo vuoto, che l'editor mostra pronto da riempire.
 */

// Pezzi che ritornano: un titolo di sezione centrato e uno a sinistra.
$titoloCentro = fn(string $occhiello, string $titolo, string $sotto = '') => ['titolo', ['occhiello' => $occhiello, 'testo' => $titolo, 'livello' => 'h2', 'sottotitolo' => $sotto, 'allineamento' => ['d' => 'center'], 'margine_sotto' => ['d' => 44],
    'css_personalizzato' => 'selector .sez-intro{margin-left:auto;margin-right:auto}']];
$titoloSinistra = fn(string $occhiello, string $titolo, string $sotto = '') => ['titolo', ['occhiello' => $occhiello, 'testo' => $titolo, 'livello' => 'h2', 'sottotitolo' => $sotto, 'margine_sotto' => ['d' => 28]]];
// La sezione sopra e quella sotto di un blocco in due parti: la prima senza spazio sotto, la seconda senza spazio sopra.
$sopra = ['spazio_sotto' => ['d' => 0, 'm' => 0]];
$sotto = ['spazio_sopra' => ['d' => 0, 'm' => 0]];

return [

    // ------------------------------------------------------------------ Apertura
    'apertura-centrata' => ['categoria' => 'apertura', 'nome' => 'Centrata', 'descrizione' => 'Titolo grande al centro, una riga e due pulsanti.', 'sezioni' => [
        ['imp' => ['spazio_sopra' => ['d' => 56], 'spazio_sotto' => ['d' => 40]], 'widget' => [
            ['hero', ['disposizione' => 'centrata', 'occhiello' => 'La riga piccola sopra', 'titolo' => 'Il titolo della pagina, *con la parola che conta.*',
                'testo' => 'Una o due frasi che dicono cosa trova qui chi arriva, e perché dovrebbe restare.',
                'pulsanti' => [['testo' => 'Il pulsante principale', 'link' => '/contatti/', 'stile' => 'viola'], ['testo' => 'Un secondo invito', 'link' => '#', 'stile' => 'filo']],
                'immagine' => '', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
        ]],
    ]],
    'apertura-minimale' => ['categoria' => 'apertura', 'nome' => 'Minimale', 'descrizione' => 'Solo parole, grandi, con tre numeri sotto una linea sottile.', 'sezioni' => [
        ['imp' => ['spazio_sopra' => ['d' => 56], 'spazio_sotto' => ['d' => 40]], 'widget' => [
            ['hero', ['disposizione' => 'minimale', 'occhiello' => 'La riga piccola sopra', 'titolo' => 'Una frase sola, *detta bene.*',
                'testo' => 'Qui va la promessa del sito in una riga: a chi parli e cosa gli offri.',
                'pulsanti' => [['testo' => 'Comincia da qui', 'link' => '/contatti/', 'stile' => 'viola']],
                'immagine' => '', 'etichetta_onda' => '', 'barre' => [['etichetta' => 'Un numero che conta', 'valore' => 80], ['etichetta' => 'Un altro numero', 'valore' => 60], ['etichetta' => 'Il terzo', 'valore' => 40]], 'margine_sotto' => ['d' => 0]]],
        ]],
    ]],
    'apertura-diagonale' => ['categoria' => 'apertura', 'nome' => 'Diagonale', 'descrizione' => 'Il gradiente dei colori del sito, tagliato in obliquo.', 'sezioni' => [
        ['imp' => ['spazio_sopra' => ['d' => 40], 'spazio_sotto' => ['d' => 40]], 'widget' => [
            ['hero', ['disposizione' => 'diagonale', 'occhiello' => 'La riga piccola sopra', 'titolo' => 'Il titolo della pagina, *in due righe.*',
                'testo' => 'Una frase che spiega in concreto cosa fai e per chi.',
                'pulsanti' => [['testo' => 'Il pulsante principale', 'link' => '/contatti/', 'stile' => 'viola'], ['testo' => 'Scopri di più', 'link' => '#', 'stile' => 'filo']],
                'immagine' => '', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
        ]],
    ]],
    'apertura-banner' => ['categoria' => 'apertura', 'nome' => 'Banner', 'descrizione' => 'Fascia bassa per le pagine interne: titolo e pulsante sulla stessa riga.', 'sezioni' => [
        ['imp' => ['spazio_sopra' => ['d' => 40], 'spazio_sotto' => ['d' => 24]], 'widget' => [
            ['hero', ['disposizione' => 'banner', 'occhiello' => 'Il nome della sezione', 'titolo' => 'Il titolo di questa pagina',
                'testo' => 'Una riga che dice cosa si trova qui sotto.',
                'pulsanti' => [['testo' => 'Scrivici', 'link' => '/contatti/', 'stile' => 'viola']],
                'immagine' => '', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
        ]],
    ]],

    // ------------------------------------------------------------------ Servizi
    'servizi-schede' => ['categoria' => 'servizi', 'nome' => 'Tre schede', 'descrizione' => 'Tre servizi in schede, la prima in evidenza, ognuna con il suo link.', 'sezioni' => [
        ['widget' => [
            $titoloSinistra('Cosa facciamo', 'I servizi, *in breve*', 'Una frase che presenta i servizi: a chi servono e cosa cambia dopo.'),
            ['schede', ['voci' => [
                ['titolo' => 'Il primo servizio', 'testo' => 'Due righe che dicono cosa comprende e a chi serve.', 'link' => '/contatti/', 'invito' => 'Chiedi informazioni', 'evidenza' => true],
                ['titolo' => 'Il secondo servizio', 'testo' => 'Due righe che dicono cosa comprende e a chi serve.', 'link' => '/contatti/', 'invito' => 'Chiedi informazioni', 'evidenza' => false],
                ['titolo' => 'Il terzo servizio', 'testo' => 'Due righe che dicono cosa comprende e a chi serve.', 'link' => '/contatti/', 'invito' => 'Chiedi informazioni', 'evidenza' => false],
            ], 'colonne' => ['d' => 3, 't' => 2, 'm' => 1], 'margine_sotto' => ['d' => 0]]],
        ]],
    ]],
    'servizi-icone' => ['categoria' => 'servizi', 'nome' => 'Tre icone', 'descrizione' => 'Titolo centrato e tre colonne, ognuna con icona, titolo e due righe.', 'sezioni' => [
        ['imp' => $sopra, 'widget' => [$titoloCentro('Cosa facciamo', 'Tre cose che facciamo bene', 'Una riga che le tiene insieme.')]],
        ['imp' => $sotto, 'colonne' => [
            [['riquadro_icona', ['icona' => 'lampadina', 'vista' => 'impilata', 'titolo' => 'Il primo servizio', 'testo' => 'Due righe che spiegano cosa comprende, senza gergo.', 'margine_sotto' => ['d' => 0]]]],
            [['riquadro_icona', ['icona' => 'ingranaggi', 'vista' => 'impilata', 'titolo' => 'Il secondo servizio', 'testo' => 'Due righe che spiegano cosa comprende, senza gergo.', 'margine_sotto' => ['d' => 0]]]],
            [['riquadro_icona', ['icona' => 'scudo-spunta', 'vista' => 'impilata', 'titolo' => 'Il terzo servizio', 'testo' => 'Due righe che spiegano cosa comprende, senza gergo.', 'margine_sotto' => ['d' => 0]]]],
        ]],
    ]],
    'servizi-elenco' => ['categoria' => 'servizi', 'nome' => 'Testo ed elenco', 'descrizione' => 'A sinistra cosa offri, a destra l\'elenco di cosa comprende.', 'sezioni' => [
        ['imp' => ['allineamento_verticale' => 'center', 'spazio_colonne' => ['d' => 56]], 'colonne' => [
            ['imp' => ['larghezza' => ['d' => 55, 'm' => 100]], 'widget' => [
                $titoloSinistra('Il servizio', 'Il nome del servizio principale'),
                ['testo', ['contenuto' => '<p>Racconta in due o tre frasi come lavori: da dove si parte, cosa succede, cosa resta alla fine. Scrivi come parleresti a un cliente seduto davanti a te.</p>']],
                ['pulsanti', ['voci' => [['testo' => 'Chiedi un preventivo', 'link' => '/contatti/', 'stile' => 'viola', 'nuova' => false]], 'margine_sotto' => ['d' => 0]]],
            ]],
            ['imp' => ['larghezza' => ['d' => 45, 'm' => 100], 'riquadro' => true, 'spazio_interno' => ['d' => 32, 'm' => 24]], 'widget' => [
                ['elenco_icone', ['voci' => [
                    ['testo' => 'La prima cosa compresa', 'icona' => 'spunta-cerchio', 'link' => ''], ['testo' => 'La seconda cosa compresa', 'icona' => 'spunta-cerchio', 'link' => ''],
                    ['testo' => 'La terza cosa compresa', 'icona' => 'spunta-cerchio', 'link' => ''], ['testo' => 'Una garanzia che dai', 'icona' => 'spunta-cerchio', 'link' => ''],
                ], 'divisore' => true, 'margine_sotto' => ['d' => 0]]],
            ]],
        ]],
    ]],
    'servizi-passi' => ['categoria' => 'servizi', 'nome' => 'Come funziona', 'descrizione' => 'I passi del lavoro, numerati: dal primo contatto alla consegna.', 'sezioni' => [
        ['widget' => [
            $titoloSinistra('Come funziona', 'Tre passi, *senza sorprese*'),
            ['passi', ['voci' => [
                ['titolo' => 'Ci sentiamo', 'testo' => 'Racconti cosa ti serve. Una telefonata o una email bastano.'],
                ['titolo' => 'Ti proponiamo', 'testo' => 'Una proposta chiara: cosa, quando, quanto costa.'],
                ['titolo' => 'Lo facciamo', 'testo' => 'Lavoriamo e ti teniamo aggiornato fino alla consegna.'],
            ], 'margine_sotto' => ['d' => 0]]],
        ]],
    ]],

    // ------------------------------------------------------------------ Chi siamo
    'chi-presentazione' => ['categoria' => 'chi', 'nome' => 'Presentazione', 'descrizione' => 'Ritratto, chi sei in poche righe e tre dati in colonna.', 'sezioni' => [
        ['imp' => ['larghezza' => 'stretta'], 'widget' => [
            ['presentazione', ['immagine' => '', 'occhiello' => 'Chi siamo', 'titolo' => 'Due righe su *chi c\'è dietro*',
                'testo' => "Racconta da dove vieni e perché fai questo lavoro. Tre o quattro frasi bastano.\nChi legge vuole capire se può fidarsi: dai un motivo concreto.",
                'voci' => [['etichetta' => 'Dal', 'valore' => 'l\'anno in cui hai cominciato'], ['etichetta' => 'Dove', 'valore' => 'la tua città'], ['etichetta' => 'Per chi', 'valore' => 'le persone che aiuti']], 'margine_sotto' => ['d' => 0]]],
        ]],
    ]],
    'chi-storia' => ['categoria' => 'chi', 'nome' => 'La storia e i numeri', 'descrizione' => 'Il racconto a sinistra, tre numeri a destra.', 'sezioni' => [
        ['imp' => ['allineamento_verticale' => 'center', 'spazio_colonne' => ['d' => 56]], 'colonne' => [
            ['imp' => ['larghezza' => ['d' => 58, 'm' => 100]], 'widget' => [
                $titoloSinistra('La nostra storia', 'Come è cominciato tutto'),
                ['testo', ['contenuto' => '<p>Il primo paragrafo racconta l\'inizio: un\'idea, un problema da risolvere, una persona.</p><p>Il secondo dice dove siete oggi e cosa vi distingue. Frasi corte, fatti veri.</p>', 'margine_sotto' => ['d' => 0]]],
            ]],
            ['imp' => ['larghezza' => ['d' => 42, 'm' => 100]], 'widget' => [
                ['numeri', ['voci' => [['valore' => '10', 'etichetta' => 'anni di lavoro'], ['valore' => '250', 'etichetta' => 'clienti seguiti'], ['valore' => '3', 'etichetta' => 'persone nel gruppo']], 'colonne' => ['d' => 1, 't' => 3, 'm' => 1], 'margine_sotto' => ['d' => 0]]],
            ]],
        ]],
    ]],
    'chi-valori' => ['categoria' => 'chi', 'nome' => 'I valori', 'descrizione' => 'Una frase in evidenza e tre principi con la loro icona.', 'sezioni' => [
        ['imp' => $sopra, 'widget' => [
            ['frase', ['testo' => 'Una frase che dice come lavorate. *Quella che vorreste sentirvi dire.*', 'autore' => '', 'margine_sotto' => ['d' => 40]]],
        ]],
        ['imp' => $sotto, 'colonne' => [
            [['riquadro_icona', ['icona' => 'stretta-di-mano', 'titolo' => 'Il primo principio', 'testo' => 'Una riga che lo spiega con un esempio.', 'margine_sotto' => ['d' => 0]]]],
            [['riquadro_icona', ['icona' => 'orologio', 'titolo' => 'Il secondo principio', 'testo' => 'Una riga che lo spiega con un esempio.', 'margine_sotto' => ['d' => 0]]]],
            [['riquadro_icona', ['icona' => 'cuore', 'titolo' => 'Il terzo principio', 'testo' => 'Una riga che lo spiega con un esempio.', 'margine_sotto' => ['d' => 0]]]],
        ]],
    ]],

    // ------------------------------------------------------------------ Numeri
    'numeri-fascia' => ['categoria' => 'numeri', 'nome' => 'Fascia di numeri', 'descrizione' => 'Quattro numeri su una fascia di vetro.', 'sezioni' => [
        ['imp' => ['sfondo' => 'vetro', 'spazio_sopra' => ['d' => 48], 'spazio_sotto' => ['d' => 48]], 'widget' => [
            ['numeri', ['voci' => [['valore' => '12', 'etichetta' => 'anni di esperienza'], ['valore' => '480', 'etichetta' => 'lavori consegnati'], ['valore' => '98%', 'etichetta' => 'clienti che tornano'], ['valore' => '24 h', 'etichetta' => 'per una risposta']],
                'colonne' => ['d' => 4, 't' => 2, 'm' => 2], 'margine_sotto' => ['d' => 0]]],
        ]],
    ]],
    'numeri-contatori' => ['categoria' => 'numeri', 'nome' => 'Contatori animati', 'descrizione' => 'Tre numeri che contano da zero quando entrano nello schermo.', 'sezioni' => [
        ['imp' => $sopra, 'widget' => [$titoloCentro('In numeri', 'Quello che abbiamo fatto finora')]],
        ['imp' => $sotto, 'colonne' => [
            [['contatore', ['da' => 0, 'a' => 150, 'suffisso' => '+', 'titolo' => 'progetti completati', 'margine_sotto' => ['d' => 0]]]],
            [['contatore', ['da' => 0, 'a' => 12, 'titolo' => 'anni di lavoro', 'margine_sotto' => ['d' => 0]]]],
            [['contatore', ['da' => 0, 'a' => 30, 'suffisso' => '', 'titolo' => 'città raggiunte', 'margine_sotto' => ['d' => 0]]]],
        ]],
    ]],
    'numeri-barre' => ['categoria' => 'numeri', 'nome' => 'Barre', 'descrizione' => 'Testo a sinistra e tre barre di avanzamento a destra.', 'sezioni' => [
        ['imp' => ['allineamento_verticale' => 'center', 'spazio_colonne' => ['d' => 56]], 'colonne' => [
            [$titoloSinistra('Competenze', 'In cosa siamo *più forti*'), ['testo', ['contenuto' => '<p>Una frase che spiega cosa misurano le barre: esperienza, richieste, risultati.</p>', 'margine_sotto' => ['d' => 0]]]],
            [['barra_avanzamento', ['titolo' => 'La prima competenza', 'percentuale' => 90]], ['barra_avanzamento', ['titolo' => 'La seconda competenza', 'percentuale' => 75]], ['barra_avanzamento', ['titolo' => 'La terza competenza', 'percentuale' => 60, 'margine_sotto' => ['d' => 0]]]],
        ]],
    ]],

    // ------------------------------------------------------------------ Testimonianze
    'testimonianze-tre' => ['categoria' => 'testimonianze', 'nome' => 'Tre testimonianze', 'descrizione' => 'Titolo centrato e tre citazioni con nome e ruolo.', 'sezioni' => [
        ['imp' => $sopra, 'widget' => [$titoloCentro('Dicono di noi', 'Le parole di chi ci ha scelto')]],
        ['imp' => $sotto, 'colonne' => [
            ['imp' => ['riquadro' => true, 'spazio_interno' => ['d' => 28]], 'widget' => [['testimonianza', ['testo' => 'Qui va una frase vera di un cliente: cosa è cambiato dopo aver lavorato con voi.', 'nome' => 'Nome Cognome', 'ruolo' => 'Ruolo, azienda', 'stelle' => 5, 'margine_sotto' => ['d' => 0]]]]],
            ['imp' => ['riquadro' => true, 'spazio_interno' => ['d' => 28]], 'widget' => [['testimonianza', ['testo' => 'Una seconda frase, di un cliente diverso. Meglio se racconta un risultato preciso.', 'nome' => 'Nome Cognome', 'ruolo' => 'Ruolo, azienda', 'stelle' => 5, 'margine_sotto' => ['d' => 0]]]]],
            ['imp' => ['riquadro' => true, 'spazio_interno' => ['d' => 28]], 'widget' => [['testimonianza', ['testo' => 'La terza frase. Chiedi sempre il permesso prima di pubblicare nome e ruolo.', 'nome' => 'Nome Cognome', 'ruolo' => 'Ruolo, azienda', 'stelle' => 5, 'margine_sotto' => ['d' => 0]]]]],
        ]],
    ]],
    'testimonianze-carosello' => ['categoria' => 'testimonianze', 'nome' => 'Carosello', 'descrizione' => 'Le testimonianze scorrono, tre alla volta sul computer, una sul telefono.', 'sezioni' => [
        ['widget' => [
            $titoloCentro('Dicono di noi', 'Chi ha lavorato con noi'),
            ['carosello_testimonianze', ['voci' => [
                ['testo' => 'La frase di un cliente soddisfatto, con un dettaglio concreto.', 'nome' => 'Nome Cognome', 'ruolo' => 'Ruolo', 'immagine' => '', 'stelle' => 5],
                ['testo' => 'Un secondo cliente racconta cosa ha trovato di diverso.', 'nome' => 'Nome Cognome', 'ruolo' => 'Ruolo', 'immagine' => '', 'stelle' => 5],
                ['testo' => 'Un terzo cliente dice perché vi consiglierebbe.', 'nome' => 'Nome Cognome', 'ruolo' => 'Ruolo', 'immagine' => '', 'stelle' => 5],
                ['testo' => 'Una quarta voce: più testimonianze, più fiducia.', 'nome' => 'Nome Cognome', 'ruolo' => 'Ruolo', 'immagine' => '', 'stelle' => 5],
            ], 'aspetto' => 'bolla', 'frecce' => false, 'margine_sotto' => ['d' => 0]]],
        ]],
    ]],
    'testimonianze-una' => ['categoria' => 'testimonianze', 'nome' => 'Una citazione grande', 'descrizione' => 'Una sola testimonianza, grande, su una fascia di colore.', 'sezioni' => [
        ['imp' => ['sfondo' => 'gradiente', 'larghezza' => 'stretta', 'spazio_sopra' => ['d' => 64], 'spazio_sotto' => ['d' => 64]], 'widget' => [
            ['citazione', ['aspetto' => 'virgolette', 'testo' => 'La frase più bella che un cliente vi abbia scritto. Lasciatela intera, senza ritocchi.', 'autore' => 'Nome Cognome, ruolo', 'margine_sotto' => ['d' => 0]]],
        ]],
    ]],

    // ------------------------------------------------------------------ Prezzi
    'prezzi-tre-piani' => ['categoria' => 'prezzi', 'nome' => 'Tre piani', 'descrizione' => 'Tre tabelle di prezzo, quella centrale in evidenza.', 'sezioni' => [
        ['imp' => $sopra, 'widget' => [$titoloCentro('Prezzi', 'Scegli il piano *che ti serve*', 'Prezzi chiari, senza costi nascosti. Scrivi qui cosa è sempre compreso.')]],
        ['imp' => $sotto + ['allineamento_verticale' => 'center'], 'colonne' => [
            [['tabella_prezzi', ['titolo' => 'Base', 'sottotitolo' => 'Per cominciare', 'prezzo' => '29', 'valuta' => '€', 'periodo' => '/ mese', 'voci' => [['testo' => 'La prima cosa compresa', 'icona' => 'spunta'], ['testo' => 'La seconda cosa compresa', 'icona' => 'spunta'], ['testo' => 'Assistenza via email', 'icona' => 'spunta']], 'pulsante_testo' => 'Scegli Base', 'link' => ['url' => '/contatti/', 'nuova' => false], 'margine_sotto' => ['d' => 0]]]],
            [['tabella_prezzi', ['titolo' => 'Completo', 'sottotitolo' => 'Il più scelto', 'prezzo' => '59', 'valuta' => '€', 'periodo' => '/ mese', 'voci' => [['testo' => 'Tutto quello del piano Base', 'icona' => 'spunta'], ['testo' => 'Una cosa in più che conta', 'icona' => 'spunta'], ['testo' => 'Un\'altra cosa in più', 'icona' => 'spunta'], ['testo' => 'Assistenza al telefono', 'icona' => 'spunta']], 'pulsante_testo' => 'Scegli Completo', 'link' => ['url' => '/contatti/', 'nuova' => false], 'nastro' => 'Consigliato', 'evidenza' => true, 'margine_sotto' => ['d' => 0]]]],
            [['tabella_prezzi', ['titolo' => 'Su misura', 'sottotitolo' => 'Per esigenze grandi', 'prezzo' => '99', 'valuta' => '€', 'periodo' => '/ mese', 'voci' => [['testo' => 'Tutto quello del piano Completo', 'icona' => 'spunta'], ['testo' => 'Una persona dedicata', 'icona' => 'spunta'], ['testo' => 'Tempi garantiti', 'icona' => 'spunta']], 'pulsante_testo' => 'Parliamone', 'link' => ['url' => '/contatti/', 'nuova' => false], 'margine_sotto' => ['d' => 0]]]],
        ]],
    ]],
    'prezzi-listino' => ['categoria' => 'prezzi', 'nome' => 'Listino', 'descrizione' => 'Un elenco di voci con il prezzo a destra, come un menu.', 'sezioni' => [
        ['imp' => ['larghezza' => 'stretta'], 'widget' => [
            $titoloCentro('Listino', 'I prezzi'),
            ['listino', ['voci' => [
                ['titolo' => 'La prima voce del listino', 'prezzo' => '40 €', 'testo' => 'Una riga che dice cosa comprende.', 'immagine' => '', 'link' => ''],
                ['titolo' => 'La seconda voce', 'prezzo' => '65 €', 'testo' => 'Una riga che dice cosa comprende.', 'immagine' => '', 'link' => ''],
                ['titolo' => 'La terza voce', 'prezzo' => '90 €', 'testo' => 'Una riga che dice cosa comprende.', 'immagine' => '', 'link' => ''],
                ['titolo' => 'Una voce su richiesta', 'prezzo' => 'su richiesta', 'testo' => 'Per i lavori che non stanno in un prezzo fisso.', 'immagine' => '', 'link' => ''],
            ], 'margine_sotto' => ['d' => 0]]],
        ]],
    ]],
    'prezzi-due-piani' => ['categoria' => 'prezzi', 'nome' => 'Due piani affiancati', 'descrizione' => 'Testo a sinistra, due tabelle di prezzo a destra.', 'sezioni' => [
        ['imp' => ['allineamento_verticale' => 'center', 'spazio_colonne' => ['d' => 40]], 'colonne' => [
            ['imp' => ['larghezza' => ['d' => 34, 't' => 100, 'm' => 100]], 'widget' => [
                $titoloSinistra('Prezzi', 'Due modi per lavorare insieme'),
                ['testo', ['contenuto' => '<p>Spiega in due righe la differenza fra i due piani e a chi conviene ciascuno.</p>', 'margine_sotto' => ['d' => 0]]],
            ]],
            ['imp' => ['larghezza' => ['d' => 33, 't' => 50, 'm' => 100]], 'widget' => [['tabella_prezzi', ['titolo' => 'Una volta', 'sottotitolo' => 'Un lavoro singolo', 'valuta' => '€', 'prezzo' => '150', 'periodo' => '', 'voci' => [['testo' => 'Cosa comprende', 'icona' => 'spunta'], ['testo' => 'Cosa comprende ancora', 'icona' => 'spunta']], 'pulsante_testo' => 'Chiedi', 'link' => ['url' => '/contatti/', 'nuova' => false], 'margine_sotto' => ['d' => 0]]]]],
            ['imp' => ['larghezza' => ['d' => 33, 't' => 50, 'm' => 100]], 'widget' => [['tabella_prezzi', ['titolo' => 'Continuativo', 'sottotitolo' => 'Ogni mese', 'prezzo' => '90', 'valuta' => '€', 'periodo' => '/ mese', 'voci' => [['testo' => 'Cosa comprende', 'icona' => 'spunta'], ['testo' => 'Cosa comprende ancora', 'icona' => 'spunta'], ['testo' => 'Un vantaggio in più', 'icona' => 'spunta']], 'pulsante_testo' => 'Chiedi', 'link' => ['url' => '/contatti/', 'nuova' => false], 'evidenza' => true, 'margine_sotto' => ['d' => 0]]]]],
        ]],
    ]],

    // ------------------------------------------------------------------ Domande frequenti
    'faq-semplice' => ['categoria' => 'faq', 'nome' => 'Elenco di domande', 'descrizione' => 'Titolo e domande che si aprono, in una colonna di lettura.', 'sezioni' => [
        ['imp' => ['larghezza' => 'stretta'], 'widget' => [
            $titoloCentro('Domande frequenti', 'Le domande che ci fanno più spesso'),
            ['faq', ['voci' => [
                ['domanda' => 'La prima domanda che vi fanno?', 'risposta' => 'Una risposta breve e diretta. Se serve un dettaglio, un link alla pagina giusta.'],
                ['domanda' => 'Quanto costa?', 'risposta' => 'Dite da quanto si parte, o dove si trovano i prezzi.'],
                ['domanda' => 'Quanto tempo ci vuole?', 'risposta' => 'Un tempo indicativo, e da cosa dipende.'],
                ['domanda' => 'Come si comincia?', 'risposta' => 'Il primo passo concreto: scrivere, chiamare, prenotare.'],
            ], 'margine_sotto' => ['d' => 0]]],
        ]],
    ]],
    'faq-due-colonne' => ['categoria' => 'faq', 'nome' => 'Domande e contatto', 'descrizione' => 'A sinistra un invito a scrivere, a destra le domande.', 'sezioni' => [
        ['imp' => ['spazio_colonne' => ['d' => 56]], 'colonne' => [
            ['imp' => ['larghezza' => ['d' => 38, 'm' => 100]], 'widget' => [
                $titoloSinistra('Domande frequenti', 'Hai un dubbio?', 'Qui le risposte alle domande più comuni. Per tutto il resto, scrivici.'),
                ['pulsanti', ['voci' => [['testo' => 'Scrivici', 'link' => '/contatti/', 'stile' => 'filo', 'nuova' => false]], 'margine_sotto' => ['d' => 0]]],
            ]],
            ['imp' => ['larghezza' => ['d' => 62, 'm' => 100]], 'widget' => [
                ['faq', ['voci' => [
                    ['domanda' => 'La prima domanda che vi fanno?', 'risposta' => 'Una risposta breve e diretta.'],
                    ['domanda' => 'Una seconda domanda?', 'risposta' => 'Una risposta breve e diretta.'],
                    ['domanda' => 'Una terza domanda?', 'risposta' => 'Una risposta breve e diretta.'],
                    ['domanda' => 'Una quarta domanda?', 'risposta' => 'Una risposta breve e diretta.'],
                ], 'margine_sotto' => ['d' => 0]]],
            ]],
        ]],
    ]],
    'faq-griglia' => ['categoria' => 'faq', 'nome' => 'Domande a griglia', 'descrizione' => 'Domande e risposte sempre visibili, su due colonne.', 'sezioni' => [
        ['imp' => $sopra, 'widget' => [$titoloCentro('Domande frequenti', 'Prima di cominciare')]],
        ['imp' => $sotto + ['spazio_colonne' => ['d' => 48]], 'colonne' => [
            [['riquadro_icona', ['icona' => 'domanda', 'titolo' => 'La prima domanda?', 'testo' => 'La risposta, in due righe al massimo.', 'livello' => 'h3']],
             ['riquadro_icona', ['icona' => 'domanda', 'titolo' => 'La terza domanda?', 'testo' => 'La risposta, in due righe al massimo.', 'livello' => 'h3', 'margine_sotto' => ['d' => 0]]]],
            [['riquadro_icona', ['icona' => 'domanda', 'titolo' => 'La seconda domanda?', 'testo' => 'La risposta, in due righe al massimo.', 'livello' => 'h3']],
             ['riquadro_icona', ['icona' => 'domanda', 'titolo' => 'La quarta domanda?', 'testo' => 'La risposta, in due righe al massimo.', 'livello' => 'h3', 'margine_sotto' => ['d' => 0]]]],
        ]],
    ]],

    // ------------------------------------------------------------------ Contatti
    'contatti-form' => ['categoria' => 'contatti', 'nome' => 'Recapiti e form', 'descrizione' => 'Email, telefono e indirizzo a sinistra, il form dei contatti a destra.', 'sezioni' => [
        ['imp' => ['spazio_colonne' => ['d' => 56]], 'colonne' => [
            ['imp' => ['larghezza' => ['d' => 40, 'm' => 100]], 'widget' => [
                $titoloSinistra('Contatti', 'Scrivici', 'Rispondiamo entro un giorno lavorativo.'),
                ['elenco_icone', ['voci' => [
                    ['testo' => 'nome@tuosito.it', 'icona' => 'email', 'link' => 'mailto:nome@tuosito.it'], ['testo' => '+39 000 000 0000', 'icona' => 'telefono', 'link' => 'tel:+390000000000'],
                    ['testo' => 'Via Esempio 1, Città', 'icona' => 'pin-mappa', 'link' => ''], ['testo' => 'Dal lunedì al venerdì, 9-18', 'icona' => 'orologio', 'link' => ''],
                ], 'margine_sotto' => ['d' => 0]]],
            ]],
            ['imp' => ['larghezza' => ['d' => 60, 'm' => 100]], 'widget' => [
                ['modulo', ['tipo' => 'form:contatti', 'margine_sotto' => ['d' => 0]]],
            ]],
        ]],
    ]],
    'contatti-riquadri' => ['categoria' => 'contatti', 'nome' => 'Tre recapiti', 'descrizione' => 'Tre riquadri con icona: scrivere, chiamare, venire a trovarci.', 'sezioni' => [
        ['imp' => $sopra, 'widget' => [$titoloCentro('Contatti', 'Come raggiungerci')]],
        ['imp' => $sotto, 'colonne' => [
            ['imp' => ['riquadro' => true, 'spazio_interno' => ['d' => 28]], 'widget' => [['riquadro_icona', ['icona' => 'email', 'vista' => 'incorniciata', 'titolo' => 'Scrivici', 'testo' => 'nome@tuosito.it', 'link' => ['url' => 'mailto:nome@tuosito.it', 'nuova' => false], 'margine_sotto' => ['d' => 0]]]]],
            ['imp' => ['riquadro' => true, 'spazio_interno' => ['d' => 28]], 'widget' => [['riquadro_icona', ['icona' => 'telefono', 'vista' => 'incorniciata', 'titolo' => 'Chiamaci', 'testo' => '+39 000 000 0000', 'link' => ['url' => 'tel:+390000000000', 'nuova' => false], 'margine_sotto' => ['d' => 0]]]]],
            ['imp' => ['riquadro' => true, 'spazio_interno' => ['d' => 28]], 'widget' => [['riquadro_icona', ['icona' => 'pin-mappa', 'vista' => 'incorniciata', 'titolo' => 'Vieni a trovarci', 'testo' => 'Via Esempio 1, Città', 'margine_sotto' => ['d' => 0]]]]],
        ]],
    ]],
    'contatti-solo-form' => ['categoria' => 'contatti', 'nome' => 'Solo il form', 'descrizione' => 'Titolo centrato e il form dei contatti in una colonna stretta.', 'sezioni' => [
        ['imp' => ['larghezza' => 'stretta'], 'widget' => [
            $titoloCentro('Contatti', 'Raccontaci cosa ti serve', 'Compila il modulo: ti rispondiamo presto.'),
            ['modulo', ['tipo' => 'form:contatti', 'margine_sotto' => ['d' => 0]]],
        ]],
    ]],

    // ------------------------------------------------------------------ Invito all'azione
    'invito-fascia' => ['categoria' => 'invito', 'nome' => 'Fascia di colore', 'descrizione' => 'Titolo e pulsanti al centro, su una fascia con il gradiente del sito.', 'sezioni' => [
        ['imp' => ['sfondo' => 'gradiente', 'spazio_sopra' => ['d' => 64], 'spazio_sotto' => ['d' => 64]], 'widget' => [
            ['titolo', ['testo' => 'Pronto a cominciare?', 'livello' => 'h2', 'sottotitolo' => 'Una riga che toglie l\'ultimo dubbio: è gratis, è veloce, non impegna.', 'allineamento' => ['d' => 'center'], 'margine_sotto' => ['d' => 28],
                'css_personalizzato' => 'selector .sez-intro{margin-left:auto;margin-right:auto}']],
            ['pulsanti', ['voci' => [['testo' => 'Il pulsante principale', 'link' => '/contatti/', 'stile' => 'viola', 'nuova' => false], ['testo' => 'Un\'alternativa', 'link' => '#', 'stile' => 'filo', 'nuova' => false]], 'allineamento' => ['d' => 'center'], 'margine_sotto' => ['d' => 0]]],
        ]],
    ]],
    'invito-riquadro' => ['categoria' => 'invito', 'nome' => 'Riquadro', 'descrizione' => 'Un riquadro con icona, titolo, testo e pulsante.', 'sezioni' => [
        ['imp' => ['larghezza' => 'stretta'], 'widget' => [
            ['invito_libero', ['icona' => 'razzo', 'titolo' => 'Il titolo dell\'invito', 'livello' => 'h2', 'testo' => 'Una frase che dice cosa succede dopo il clic, e perché conviene adesso.', 'pulsante_testo' => 'Il pulsante', 'link' => ['url' => '/contatti/', 'nuova' => false], 'margine_sotto' => ['d' => 0]]],
        ]],
    ]],
    'invito-lato' => ['categoria' => 'invito', 'nome' => 'Testo e pulsante', 'descrizione' => 'Titolo a sinistra e pulsante a destra, su una fascia di vetro.', 'sezioni' => [
        ['imp' => ['sfondo' => 'vetro', 'allineamento_verticale' => 'center', 'spazio_sopra' => ['d' => 48], 'spazio_sotto' => ['d' => 48]], 'colonne' => [
            ['imp' => ['larghezza' => ['d' => 68, 'm' => 100]], 'widget' => [['titolo', ['testo' => 'Il titolo dell\'invito, *in una riga*', 'livello' => 'h2', 'sottotitolo' => 'Una frase sotto, se serve.', 'margine_sotto' => ['d' => 0, 'm' => 20]]]]],
            ['imp' => ['larghezza' => ['d' => 32, 'm' => 100], 'allineamento_verticale' => 'center'], 'widget' => [['pulsanti', ['voci' => [['testo' => 'Il pulsante', 'link' => '/contatti/', 'stile' => 'viola', 'nuova' => false]], 'allineamento' => ['d' => 'right', 'm' => 'left'], 'margine_sotto' => ['d' => 0]]]]],
        ]],
    ]],
    'invito-vantaggi' => ['categoria' => 'invito', 'nome' => 'Invito con i vantaggi', 'descrizione' => 'Titolo e pulsante a sinistra, tre buone ragioni a destra, su una fascia di colore.', 'sezioni' => [
        ['imp' => ['sfondo' => 'gradiente', 'allineamento_verticale' => 'center', 'spazio_colonne' => ['d' => 48]], 'colonne' => [
            [['titolo', ['occhiello' => 'Perché adesso', 'testo' => 'Il titolo dell\'invito', 'livello' => 'h2', 'sottotitolo' => 'Una frase che spiega cosa succede dopo il clic.', 'margine_sotto' => ['d' => 28]]],
             ['pulsanti', ['voci' => [['testo' => 'Il pulsante', 'link' => '/contatti/', 'stile' => 'viola', 'nuova' => false]], 'margine_sotto' => ['d' => 0]]]],
            [['elenco_icone', ['voci' => [
                ['testo' => 'La prima buona ragione', 'icona' => 'spunta-cerchio', 'link' => ''], ['testo' => 'La seconda buona ragione', 'icona' => 'spunta-cerchio', 'link' => ''], ['testo' => 'La terza buona ragione', 'icona' => 'spunta-cerchio', 'link' => ''],
            ], 'divisore' => true, 'margine_sotto' => ['d' => 0]]]],
        ]],
    ]],
];
