<?php
/**
 * I tre modelli pronti dell'addon Test e questionari, uno per tipo. Domande e testi scritti per plumeCMS (nessuna domanda
 * presa da test pubblicati con diritti): sono esempi da cambiare, non strumenti di valutazione.
 * Ogni modello: nome, tipo, descrizione, dati (nella forma di test_normalizza()).
 */

function test_modelli_pronti(): array {
    $f = fn(string $k, string $nome, int $da, int $a, string $testo, string $bt = '', string $bl = '') => ['k' => $k, 'nome' => $nome, 'da' => $da, 'a' => $a, 'testo' => $testo, 'pulsante_testo' => $bt, 'pulsante_link' => $bl];
    $scala = fn(string $k, string $testo, array $extra = []) => $extra + ['k' => $k, 'testo' => $testo, 'tipo' => 'scala', 'peso' => 1, 'obbligatoria' => true];
    return [
        'riposo' => [
            'nome' => 'Quanto riposi davvero?', 'tipo' => 'punteggio',
            'descrizione' => 'A punteggio: otto domande su sonno, pause e stanchezza, con una domanda invertita, una con peso doppio e una che compare solo se serve.',
            'dati' => [
                'presentazione' => "Otto domande per capire se il tuo riposo ti ricarica davvero.\n\nRispondi pensando alle ultime due settimane. Non ci sono risposte giuste o sbagliate.",
                'domande' => [
                    $scala('d1', 'Ti svegli stanco anche dopo aver dormito le tue ore?'),
                    $scala('d2', 'Durante il giorno ti senti pieno di energia?', ['invertita' => true, 'aiuto' => 'Qui "Sempre" è la risposta migliore.']),
                    $scala('d3', 'Fai fatica ad addormentarti perché pensi al lavoro?', ['peso' => 2]),
                    $scala('d4', 'Salti le pause perché "non c\'è tempo"?'),
                    ['k' => 'd5', 'testo' => 'Bevi caffè o bevande energetiche dopo le quattro del pomeriggio?', 'tipo' => 'sino', 'obbligatoria' => true, 'peso' => 1,
                        'opzioni' => [['k' => 'si', 't' => 'Sì', 'punti' => 2], ['k' => 'no', 't' => 'No', 'punti' => 0]]],
                    ['k' => 'd6', 'testo' => 'Quante volte a settimana?', 'tipo' => 'scelta', 'obbligatoria' => false, 'peso' => 1, 'condizione' => ['domanda' => 'd5', 'valori' => ['si']],
                        'opzioni' => [['k' => 'o1', 't' => 'Una o due', 'punti' => 1], ['k' => 'o2', 't' => 'Da tre a cinque', 'punti' => 2], ['k' => 'o3', 't' => 'Tutti i giorni', 'punti' => 4]]],
                    $scala('d7', 'Il fine settimana ti basta per recuperare?', ['invertita' => true]),
                    ['k' => 'd8', 'testo' => 'Quanto ti senti stanco in questo momento, da 0 (per niente) a 10 (moltissimo)?', 'tipo' => 'cursore', 'obbligatoria' => true, 'peso' => 1],
                ],
                'fasce' => [
                    $f('basso', 'Riposo buono', 0, 33, '<p>Il tuo riposo funziona: dormi, fai le tue pause e recuperi. Continua così e tieni d\'occhio i periodi più pieni.</p>'),
                    $f('medio', 'Riposo da curare', 34, 66, '<p>Qualcosa non torna: il riposo c\'è, ma non sempre ti ricarica. Prova a proteggere una pausa vera ogni giorno e a staccare dagli schermi un\'ora prima di dormire.</p>', 'Scrivici', '/contatti/'),
                    $f('alto', 'Riposo in rosso', 67, 100, '<p>La stanchezza sembra accumularsi da un po\'. Vale la pena parlarne con qualcuno di cui ti fidi e, se dura, con il tuo medico.</p>', 'Parliamone', '/contatti/'),
                ],
            ],
        ],
        'equilibrio' => [
            'nome' => 'Il tuo equilibrio fra lavoro e vita', 'tipo' => 'aree',
            'descrizione' => 'Ad aree: nove domande in tre aree (Energia, Tempo per te, Relazioni), con il grafico a barre e una regola che vince sulle fasce.',
            'dati' => [
                'presentazione' => "Nove domande in tre aree: energia, tempo per te, relazioni.\n\nAlla fine vedi dove stai bene e dove c'è da lavorare.",
                'aree' => [
                    ['k' => 'energia', 'nome' => 'Energia', 'fasce' => [$f('bassa', 'Bassa', 0, 40, '<p>L\'energia è poca: il corpo chiede pause.</p>'), $f('media', 'Media', 41, 70, '<p>Energia altalenante.</p>'), $f('alta', 'Alta', 71, 100, '<p>Buona energia.</p>')]],
                    ['k' => 'tempo', 'nome' => 'Tempo per te', 'fasce' => [$f('bassa', 'Poco', 0, 40, '<p>Il tempo per te è quasi sparito.</p>'), $f('media', 'Abbastanza', 41, 70, '<p>Qualche spazio c\'è.</p>'), $f('alta', 'Tanto', 71, 100, '<p>Ti ritagli il tuo tempo.</p>')]],
                    ['k' => 'relazioni', 'nome' => 'Relazioni', 'fasce' => [$f('bassa', 'Trascurate', 0, 40, '<p>Le persone care restano indietro.</p>'), $f('media', 'Nella media', 41, 70, '<p>Ci sei, ma potresti esserci di più.</p>'), $f('alta', 'Curate', 71, 100, '<p>Le relazioni sono una risorsa.</p>')]],
                ],
                'domande' => [
                    $scala('e1', 'A fine giornata hai ancora voglia di fare qualcosa che ti piace?', ['area' => 'energia']),
                    $scala('e2', 'Ti senti esausto già a metà settimana?', ['area' => 'energia', 'invertita' => true]),
                    $scala('e3', 'Riesci a fare movimento almeno due volte a settimana?', ['area' => 'energia']),
                    $scala('t1', 'Hai un momento della giornata tutto per te?', ['area' => 'tempo', 'peso' => 2]),
                    $scala('t2', 'Rispondi a messaggi di lavoro la sera o nel fine settimana?', ['area' => 'tempo', 'invertita' => true]),
                    $scala('t3', 'Ti dedichi a un interesse che non c\'entra con il lavoro?', ['area' => 'tempo']),
                    $scala('r1', 'Passi del tempo di qualità con le persone a cui tieni?', ['area' => 'relazioni']),
                    $scala('r2', 'Rimandi incontri con gli amici per stanchezza?', ['area' => 'relazioni', 'invertita' => true]),
                    $scala('r3', 'Ti senti ascoltato a casa?', ['area' => 'relazioni']),
                ],
                'fasce' => [
                    $f('basso', 'Da ritrovare', 0, 40, '<p>L\'equilibrio è da ritrovare: comincia dall\'area più bassa del grafico, un passo alla volta.</p>', 'Scopri come', '/contatti/'),
                    $f('medio', 'In cammino', 41, 70, '<p>Sei in cammino: alcune aree vanno bene, altre chiedono attenzione.</p>'),
                    $f('alto', 'In equilibrio', 71, 100, '<p>Complimenti: lavoro e vita stanno bene insieme.</p>'),
                ],
                'regole' => [
                    ['k' => 'rallenta', 'nome' => 'Poca energia e poco tempo', 'modo' => 'e', 'condizioni' => [['cosa' => 'area:energia', 'fascia' => 'bassa'], ['cosa' => 'area:tempo', 'fascia' => 'bassa']],
                        'testo' => '<p>Poca energia e poco tempo per te: è il momento di rallentare. Scegli una cosa da togliere dalla settimana, prima di aggiungerne altre.</p>', 'pulsante_testo' => 'Parliamone', 'pulsante_link' => '/contatti/'],
                ],
            ],
        ],
        'viaggiatore' => [
            'nome' => 'Che viaggiatore sei?', 'tipo' => 'profili',
            'descrizione' => 'Che tipo sei: sei domande a scelta, tre profili (Esploratore, Rilassato, Organizzatore); in parità vince il primo dell\'elenco.',
            'dati' => [
                'presentazione' => "Sei domande per scoprire che viaggiatore sei.\n\nScegli la risposta che ti somiglia di più, anche se non è perfetta.",
                'profili' => [
                    ['k' => 'esploratore', 'nome' => 'Esploratore', 'testo' => '<p>Ti muovi per scoprire: strade nuove, cibo locale, persone del posto. Il programma lo fai strada facendo.</p>', 'pulsante_testo' => 'Le mete per te', 'pulsante_link' => '/'],
                    ['k' => 'rilassato', 'nome' => 'Rilassato', 'testo' => '<p>In viaggio vuoi staccare davvero: un bel posto, ritmi lenti, nessuna sveglia.</p>', 'pulsante_testo' => 'Le mete per te', 'pulsante_link' => '/'],
                    ['k' => 'organizzatore', 'nome' => 'Organizzatore', 'testo' => '<p>Parti con un piano: orari, prenotazioni, cose da vedere. Così ti godi tutto senza sorprese.</p>', 'pulsante_testo' => 'Le mete per te', 'pulsante_link' => '/'],
                ],
                'domande' => [
                    ['k' => 'v1', 'testo' => 'Il primo giorno in una città nuova...', 'tipo' => 'scelta', 'opzioni' => [
                        ['k' => 'a', 't' => 'Cammino senza meta', 'profili' => ['esploratore' => 2]], ['k' => 'b', 't' => 'Mi siedo in un caffè e guardo la gente', 'profili' => ['rilassato' => 2]], ['k' => 'c', 't' => 'Seguo il giro che ho preparato', 'profili' => ['organizzatore' => 2]]]],
                    ['k' => 'v2', 'testo' => 'La valigia la prepari...', 'tipo' => 'scelta', 'opzioni' => [
                        ['k' => 'a', 't' => 'La sera prima, al volo', 'profili' => ['esploratore' => 1, 'rilassato' => 1]], ['k' => 'b', 't' => 'Con una lista, giorni prima', 'profili' => ['organizzatore' => 2]], ['k' => 'c', 't' => 'Leggera: il resto lo compro lì', 'profili' => ['esploratore' => 2]]]],
                    ['k' => 'v3', 'testo' => 'In vacanza la sveglia...', 'tipo' => 'scelta', 'opzioni' => [
                        ['k' => 'a', 't' => 'Non esiste', 'profili' => ['rilassato' => 2]], ['k' => 'b', 't' => 'Presto: c\'è tanto da vedere', 'profili' => ['organizzatore' => 1, 'esploratore' => 1]], ['k' => 'c', 't' => 'Dipende da cosa succede la sera', 'profili' => ['esploratore' => 1]]]],
                    ['k' => 'v4', 'testo' => 'Ti piace prenotare tutto prima di partire?', 'tipo' => 'sino', 'opzioni' => [
                        ['k' => 'si', 't' => 'Sì', 'profili' => ['organizzatore' => 2]], ['k' => 'no', 't' => 'No', 'profili' => ['esploratore' => 1, 'rilassato' => 1]]]],
                    ['k' => 'v5', 'testo' => 'Il ricordo più bello di un viaggio è...', 'tipo' => 'scelta', 'opzioni' => [
                        ['k' => 'a', 't' => 'Un posto trovato per caso', 'profili' => ['esploratore' => 2]], ['k' => 'b', 't' => 'Un tramonto senza pensieri', 'profili' => ['rilassato' => 2]], ['k' => 'c', 't' => 'Il museo che sognavo da anni', 'profili' => ['organizzatore' => 2]]]],
                    ['k' => 'v6', 'testo' => 'Se piove tutto il giorno...', 'tipo' => 'scelta', 'opzioni' => [
                        ['k' => 'a', 't' => 'Esco lo stesso', 'profili' => ['esploratore' => 2]], ['k' => 'b', 't' => 'Libro, coperta e tè', 'profili' => ['rilassato' => 2]], ['k' => 'c', 't' => 'Ho già il piano per la pioggia', 'profili' => ['organizzatore' => 2]]]],
                ],
            ],
        ],
    ];
}
