<?php
/**
 * Sito su misura (1.7.0, 29 ottobre 2026): chi installa risponde a poche domande (che attività è, dove, che servizi
 * offre, quali pagine vuole) e il CMS compone il sito: home, pagine, menu, form dei contatti, con testi scritti per
 * quel tipo di attività e immagini finte da sostituire. Niente intelligenza artificiale e niente servizi esterni:
 * cataloghi di dati qui sotto, sezioni dell'editor e disegni SVG fatti sul server.
 *
 * Il sito su misura e un kit di partenza (includes/kit-partenza.php) con l'id "su-misura": i suoi dati non stanno in
 * un file di includes/kit/, li compone su_misura_kit() dalle risposte salvate nell'impostazione su_misura_risposte.
 * Così l'installatore, il cambio di tipo di sito in prova (kit_cambia, anche "su misura" con risposte nuove) e il
 * Definitivo lavorano come per gli altri kit, con lo stesso registro di cio che si e creato.
 *
 * Le risposte (su_misura_pulisci):
 *   attivita   un id di SU_MISURA_ATTIVITA (altrimenti "altro")
 *   citta      dove si trova (facoltativa, al massimo 80 caratteri): va nei testi, nei contatti e nella mappa
 *   servizi    da 1 a 6 nomi brevi (al massimo 60 caratteri); vuoti, i primi tre dell'attività
 *   pagine     le pagine scelte, fra le chiavi di SU_MISURA_PAGINE (la home c'è sempre, il Blog esiste sempre)
 *   ogni_servizio  true: una pagina per ogni servizio (sotto Servizi nel menu), false: tutti in una pagina
 *
 * Le immagini finte (su_misura_foto): SVG in uploads/segnaposto/, nei colori dello Stile del sito di quel momento,
 * con l'icona dell'attività, il soggetto ("La sala", "Un piatto") e la scritta "Sostituisci questa foto". La libreria
 * Media le mostra nella cartella "Da sostituire" e ha il pulsante Sostituisci (media_sostituisci_segnaposto in
 * includes/media.php), che mette la foto vera al posto di quella finta in ogni pagina. I Primi passi le contano.
 *
 * Solo dati e funzioni pure fino a su_misura_kit(): l'installatore mostra le domande prima che esista il database.
 */

const SU_MISURA_ID = 'su-misura';
const SU_MISURA_IMPOSTAZIONE = 'su_misura_risposte';
const SU_MISURA_MAX_SERVIZI = 6;
const SU_MISURA_CARTELLA = 'segnaposto';

/** Le pagine che si possono scegliere: chiave => [nome nel modulo, slug, spiegazione, scelta di partenza]. */
const SU_MISURA_PAGINE = [
    'chi-siamo'  => ['Chi siamo', 'chi-siamo', 'Chi siete, da quanto, perché fidarsi.', true],
    'servizi'    => ['Servizi', 'servizi', 'Cosa offrite, uno per uno.', true],
    'listino'    => ['Listino', 'listino', 'Le voci con il prezzo (per un ristorante: il menù).', false],
    'galleria'   => ['Galleria', 'galleria', 'Le foto: il locale, i lavori, le camere.', true],
    'recensioni' => ['Recensioni', 'recensioni', 'Le parole di chi vi ha già scelto.', false],
    'faq'        => ['Domande frequenti', 'domande-frequenti', 'Le risposte alle domande di sempre.', false],
    'blog'       => ['Blog', 'blog', 'Notizie e articoli (la pagina c\'è sempre: qui decidi se va nel menu).', false],
    'contatti'   => ['Contatti', 'contatti', 'Form, recapiti e mappa.', true],
];

/**
 * Le attività: i testi di partenza scritti per ognuna. Le parole fra *asterischi* nei titoli escono in evidenza.
 * soggetti: i sei soggetti delle foto finte (il primo per l'apertura, il sesto per il ritratto di Chi siamo).
 */
function su_misura_attivita(): array {
    return [
        'negozio' => [
            'nome' => 'Negozio', 'descrizione' => 'Un negozio, una bottega, una libreria, un\'erboristeria.', 'icona' => 'borsa',
            'clienti' => 'clienti', 'invito' => 'Passa a trovarci', 'listino' => 'Prodotti e prezzi', 'galleria' => 'Il negozio',
            'titolo' => 'Le cose belle, *scelte una per una.*', 'sotto' => 'Un negozio dove trovi quello che cerchi e qualcuno che ti sa consigliare.',
            'servizi' => ['Prodotti selezionati', 'Consigli su misura', 'Confezioni regalo', 'Ordini su richiesta', 'Ritiro in negozio', 'Consegna a domicilio'],
            'soggetti' => ['La vetrina', 'Gli scaffali', 'Un prodotto', 'Il banco', 'Un dettaglio', 'Chi ti accoglie'],
            'chi' => "Abbiamo aperto per far trovare in un posto solo le cose che cercavamo noi.\nOgni prodotto sullo scaffale lo abbiamo provato, e ti sappiamo dire perché è lì.",
            'valori' => [['stretta-di-mano', 'Ti consigliamo davvero', 'Se una cosa non fa per te, te lo diciamo.'], ['diamante', 'Pochi prodotti, buoni', 'Meglio dieci cose giuste che cento a caso.'], ['regalo', 'Il regalo è pronto', 'Te lo incartiamo noi, con un biglietto se vuoi.']],
            'numeri' => [['10', 'anni in negozio'], ['1.200', 'prodotti scelti'], ['6', 'giorni aperti su 7'], ['1', 'consiglio sempre gratis']],
            'faq' => [['Posso ordinare un prodotto che non vedo?', 'Sì: scrivici o passa in negozio, lo ordiniamo noi e ti avvisiamo quando arriva.'], ['Fate consegne?', 'In città sì, in giornata per gli ordini entro le 12. Fuori città con il corriere.'], ['Si può cambiare un acquisto?', 'Entro 14 giorni, con lo scontrino e il prodotto integro.'], ['Avete il parcheggio?', 'Scrivi qui dove si parcheggia più comodo vicino al negozio.']],
            'recensioni' => ['Entri per una cosa ed esci con quella giusta, che non sapevi di cercare.', 'Il regalo più azzeccato che abbia fatto: merito del consiglio al banco.', 'Gentili, preparati, e mi hanno ordinato un prodotto introvabile in due giorni.'],
        ],
        'ristorante' => [
            'nome' => 'Ristorante o bar', 'descrizione' => 'Un ristorante, una trattoria, un bar, una pizzeria.', 'icona' => 'fiamma',
            'clienti' => 'ospiti', 'invito' => 'Prenota un tavolo', 'listino' => 'Il menù', 'galleria' => 'Il locale e i piatti',
            'titolo' => 'La cucina di casa, *con un tavolo per te.*', 'sotto' => 'Piatti fatti ogni giorno con quello che offre la stagione.',
            'servizi' => ['Pranzo', 'Cena', 'Aperitivo', 'Menù del giorno', 'Eventi privati', 'Asporto'],
            'soggetti' => ['La sala', 'Un piatto', 'La cucina', 'Il bancone', 'La tavola apparecchiata', 'Lo chef'],
            'chi' => "In cucina ci siamo noi, ogni giorno, dalla spesa del mattino all'ultimo caffè.\nCuciniamo le ricette che conosciamo bene, con i prodotti di chi conosciamo per nome.",
            'valori' => [['germoglio', 'La stagione decide', 'Il menù cambia quando cambiano i prodotti.'], ['cuore', 'Fatto in casa', 'Pasta, dolci e pane li facciamo noi.'], ['sorriso', 'Come a casa', 'Ti accogliamo per nome dalla seconda volta.']],
            'numeri' => [['40', 'coperti'], ['15', 'piatti in menù'], ['3', 'menù diversi all\'anno'], ['1', 'cucina, la nostra']],
            'faq' => [['Serve prenotare?', 'Nel fine settimana sì, gli altri giorni è consigliato: chiamaci o scrivici dal form.'], ['Avete piatti senza glutine o vegetariani?', 'Sì, sono segnati nel menù. Dillo quando prenoti e ti prepariamo qualcosa su misura.'], ['Organizzate cene private?', 'Sì, fino a 30 persone, con un menù concordato. Scrivici con la data.'], ['Si può portare il cane?', 'Scrivi qui la vostra regola sugli animali.']],
            'recensioni' => ['Piatti semplici fatti benissimo: il tipo di posto in cui torni.', 'Abbiamo festeggiato il compleanno di mia madre: menù su misura e servizio perfetto.', 'La pasta fresca più buona della zona, e un vino della casa che non ti aspetti.'],
        ],
        'studio' => [
            'nome' => 'Studio professionale', 'descrizione' => 'Commercialista, avvocato, architetto, ingegnere, consulente.', 'icona' => 'valigetta',
            'clienti' => 'clienti', 'invito' => 'Fissa un appuntamento', 'listino' => 'Gli onorari', 'galleria' => 'Lo studio',
            'titolo' => 'Le pratiche le seguiamo noi, *tu pensi al resto.*', 'sotto' => 'Uno studio che ti risponde, ti spiega e ti dice cosa fare, in parole chiare.',
            'servizi' => ['Consulenza iniziale', 'Pratiche e adempimenti', 'Assistenza continuativa', 'Pareri e verifiche', 'Progetti su misura', 'Formazione'],
            'soggetti' => ['Lo studio', 'Una riunione', 'La scrivania', 'Il team', 'Un dettaglio', 'Il titolare'],
            'chi' => "Lavoriamo con privati e piccole imprese che vogliono qualcuno di cui fidarsi.\nOgni pratica ha un responsabile, che conosci e che ti risponde.",
            'valori' => [['scudo-spunta', 'Precisione', 'Scadenze segnate, documenti controllati due volte.'], ['messaggio', 'Parole chiare', 'Ti spieghiamo cosa succede, senza gergo.'], ['orologio', 'Tempi certi', 'Ti diciamo quando e lo rispettiamo.']],
            'numeri' => [['20', 'anni di esperienza'], ['300', 'clienti seguiti'], ['48 h', 'per una risposta'], ['1', 'responsabile per pratica']],
            'faq' => [['Il primo incontro è a pagamento?', 'Il primo colloquio di conoscenza è gratuito: ci racconti la tua situazione e ti diciamo come possiamo aiutarti.'], ['Lavorate anche a distanza?', 'Sì, con videochiamate e documenti condivisi. In studio quando serve firmare.'], ['Quanto costa seguire una pratica?', 'Dipende dalla pratica: dopo il primo incontro ricevi un preventivo scritto.'], ['Che documenti devo portare?', 'Scrivi qui l\'elenco dei documenti utili per il primo appuntamento.']],
            'recensioni' => ['Finalmente qualcuno che mi spiega le cose prima che diventino un problema.', 'Precisi e sempre raggiungibili: in due anni nessuna scadenza persa.', 'Mi hanno seguito in una pratica complicata con una pazienza rara.'],
        ],
        'artigiano' => [
            'nome' => 'Artigiano o impresa', 'descrizione' => 'Idraulico, elettricista, falegname, impresa edile, giardiniere.', 'icona' => 'martello',
            'clienti' => 'clienti', 'invito' => 'Chiedi un preventivo', 'listino' => 'Prezzi indicativi', 'galleria' => 'I nostri lavori',
            'titolo' => 'Lavori fatti bene, *la prima volta.*', 'sotto' => 'Preventivo chiaro, tempi rispettati, cantiere pulito quando ce ne andiamo.',
            'servizi' => ['Sopralluogo gratuito', 'Interventi su misura', 'Riparazioni', 'Manutenzione', 'Pronto intervento', 'Garanzia sui lavori'],
            'soggetti' => ['Un lavoro finito', 'Il laboratorio', 'Gli attrezzi', 'Il cantiere', 'Un dettaglio', 'La squadra'],
            'chi' => "Facciamo questo mestiere da tanti anni, e lo facciamo con le nostre mani.\nIl preventivo lo scriviamo dopo aver visto il lavoro: niente sorprese a fine cantiere.",
            'valori' => [['chiave-inglese', 'Lavoro a regola d\'arte', 'Materiali buoni e certificazioni in ordine.'], ['calendario-spunta', 'Tempi rispettati', 'Ti diciamo quando finiamo, e finiamo.'], ['scudo-spunta', 'Garanzia', 'Se qualcosa non va, torniamo noi.']],
            'numeri' => [['25', 'anni di lavori'], ['900', 'interventi'], ['24 h', 'per il pronto intervento'], ['2', 'anni di garanzia']],
            'faq' => [['Il sopralluogo si paga?', 'No, il sopralluogo e il preventivo sono gratuiti e senza impegno.'], ['In quanto tempo intervenite?', 'Per le urgenze in giornata, per gli altri lavori fissiamo una data entro la settimana.'], ['In che zona lavorate?', 'Scrivi qui i comuni in cui lavorate.'], ['Fate fattura e lavori con detrazione?', 'Sì, sempre fattura, e ti aiutiamo con i documenti per le detrazioni.']],
            'recensioni' => ['Puntuali, puliti e con un preventivo rispettato al centesimo.', 'Chiamati per un\'urgenza di domenica: arrivati in un\'ora.', 'Lavoro fatto benissimo, e ci hanno spiegato come evitare il problema in futuro.'],
        ],
        'coach' => [
            'nome' => 'Coach o consulente', 'descrizione' => 'Coach, formatore, consulente, psicologo, nutrizionista.', 'icona' => 'bersaglio',
            'clienti' => 'persone', 'invito' => 'Prenota una chiamata conoscitiva', 'listino' => 'Percorsi e prezzi', 'galleria' => 'Momenti di lavoro',
            'titolo' => 'Il prossimo passo, *fatto insieme.*', 'sotto' => 'Ti aiuto a capire dove sei, dove vuoi arrivare e cosa fare da domani.',
            'servizi' => ['Sessione conoscitiva', 'Percorso individuale', 'Percorso per team', 'Workshop', 'Formazione in azienda', 'Incontri online'],
            'soggetti' => ['Una sessione', 'Un momento di ascolto', 'Un workshop', 'Lo studio', 'Un dettaglio', 'Il ritratto'],
            'chi' => "Ho scelto questo lavoro perché credo che ognuno possa cambiare la parte che dipende da sé.\nLavoro con metodo, con domande precise e con obiettivi che si possono verificare.",
            'valori' => [['messaggio', 'Ascolto', 'Prima capisco, poi propongo.'], ['bersaglio', 'Obiettivi concreti', 'Ogni incontro finisce con un passo da fare.'], ['lucchetto', 'Riservatezza', 'Quello che dici resta fra noi.']],
            'numeri' => [['10', 'anni di pratica'], ['500', 'persone accompagnate'], ['8', 'incontri in media'], ['1', 'obiettivo alla volta']],
            'faq' => [['Come funziona la chiamata conoscitiva?', 'Venti minuti gratuiti per conoscerci: mi racconti cosa cerchi e ti dico se e come posso aiutarti.'], ['Gli incontri si fanno online?', 'Sì, online o in presenza: scegli tu.'], ['Quanto dura un percorso?', 'Dipende dall\'obiettivo: di solito da sei a dieci incontri, uno ogni due settimane.'], ['Quanto costa?', 'Scrivi qui da quanto partono i tuoi percorsi, o rimanda alla pagina dei prezzi.']],
            'recensioni' => ['In poche sessioni ho messo in ordine cose che rimandavo da anni.', 'Domande precise, nessun giudizio, e alla fine di ogni incontro sapevo cosa fare.', 'Il percorso con il team ha cambiato il modo in cui ci parliamo in riunione.'],
        ],
        'benessere' => [
            'nome' => 'Salute e benessere', 'descrizione' => 'Centro estetico, fisioterapista, palestra, studio di yoga, parrucchiere.', 'icona' => 'respiro',
            'clienti' => 'clienti', 'invito' => 'Prenota', 'listino' => 'Trattamenti e prezzi', 'galleria' => 'Il centro',
            'titolo' => 'Un\'ora tutta per te, *fatta con cura.*', 'sotto' => 'Trattamenti scelti con te, in un posto dove stare bene.',
            'servizi' => ['Prima visita', 'Trattamenti', 'Percorsi personalizzati', 'Massaggi', 'Consulenza', 'Pacchetti regalo'],
            'soggetti' => ['La cabina', 'Un trattamento', 'L\'ingresso', 'Un dettaglio', 'Un ambiente rilassante', 'Chi ti segue'],
            'chi' => "Ci prendiamo cura delle persone con calma, una alla volta.\nOgni percorso parte da un colloquio: prima ti ascoltiamo, poi proponiamo.",
            'valori' => [['respiro', 'Con calma', 'Nessuna fretta: il tempo è tuo.'], ['foglia', 'Prodotti scelti', 'Usiamo solo quello che useremmo su di noi.'], ['utente-spunta', 'Su misura', 'Un percorso pensato per te, non un listino.']],
            'numeri' => [['12', 'anni di attività'], ['20', 'trattamenti'], ['4', 'professionisti'], ['1', 'colloquio prima di tutto']],
            'faq' => [['Come si prenota?', 'Dal form, per telefono o passando in centro. Ti confermiamo l\'orario entro la giornata.'], ['Posso disdire un appuntamento?', 'Sì, avvisaci almeno 24 ore prima e lo spostiamo senza costi.'], ['Fate buoni regalo?', 'Sì, per un trattamento o per un importo: li prepariamo in giornata.'], ['Serve portare qualcosa?', 'Scrivi qui cosa serve portare al primo appuntamento.']],
            'recensioni' => ['Un posto in cui stai bene già dall\'ingresso.', 'Mi hanno ascoltato davvero e proposto il percorso giusto, senza vendermi niente.', 'Professionali e gentilissimi: ormai è il mio appuntamento fisso del mese.'],
        ],
        'creativo' => [
            'nome' => 'Fotografo o creativo', 'descrizione' => 'Fotografo, videomaker, grafico, illustratore, designer.', 'icona' => 'fotocamera',
            'clienti' => 'clienti', 'invito' => 'Raccontami il tuo progetto', 'listino' => 'Pacchetti', 'galleria' => 'Portfolio',
            'titolo' => 'Immagini che *raccontano chi sei.*', 'sotto' => 'Progetti pensati con te, dall\'idea alla consegna.',
            'servizi' => ['Servizi fotografici', 'Ritratti', 'Eventi', 'Foto di prodotto', 'Video', 'Grafica e identità'],
            'soggetti' => ['Uno scatto', 'Il set', 'Un ritratto', 'Un evento', 'Un dettaglio', 'Dietro l\'obiettivo'],
            'chi' => "Lavoro con le immagini da anni, per persone e aziende che vogliono farsi riconoscere.\nOgni progetto parte da una chiacchierata: prima capisco cosa vuoi dire, poi scelgo come.",
            'valori' => [['occhio', 'Sguardo', 'Uno stile riconoscibile, al servizio della tua storia.'], ['calendario-spunta', 'Consegne puntuali', 'Ti dico quando, e lo rispetto.'], ['stretta-di-mano', 'Insieme', 'Ogni scelta la facciamo parlandone.']],
            'numeri' => [['8', 'anni di lavori'], ['200', 'progetti consegnati'], ['15', 'giorni per la consegna'], ['1', 'chiacchierata per cominciare']],
            'faq' => [['Come funziona un servizio?', 'Ci sentiamo per capire cosa ti serve, ti mando un preventivo, fissiamo la data e ti consegno il lavoro finito.'], ['Quando ricevo le foto?', 'Di solito entro 15 giorni, in una galleria online da scaricare.'], ['Lavori anche fuori città?', 'Sì: scrivi qui come funzionano trasferte e spese.'], ['Posso usare le foto sui social?', 'Sì, sono tue: ti chiedo solo di citarmi quando le pubblichi.']],
            'recensioni' => ['Ha capito al volo cosa volevamo, e ci ha dato qualcosa di meglio.', 'Le foto del nostro matrimonio sono il regalo più bello che ci siamo fatti.', 'Un\'identità visiva nuova che i clienti hanno notato subito.'],
        ],
        'alloggio' => [
            'nome' => 'B&B o alloggio', 'descrizione' => 'B&B, agriturismo, casa vacanze, piccolo hotel.', 'icona' => 'casa',
            'clienti' => 'ospiti', 'invito' => 'Verifica le date', 'listino' => 'Le tariffe', 'galleria' => 'La casa',
            'titolo' => 'Un posto tranquillo *per fermarti un po\'.*', 'sotto' => 'Camere curate, colazione fatta in casa e i nostri consigli per scoprire i dintorni.',
            'servizi' => ['Camere', 'Colazione fatta in casa', 'Parcheggio gratuito', 'Consigli sui dintorni', 'Biciclette a disposizione', 'Wi-Fi in tutta la casa'],
            'soggetti' => ['Una camera', 'La colazione', 'La casa da fuori', 'Il panorama', 'Un dettaglio', 'Il giardino'],
            'chi' => "Abbiamo aperto la nostra casa agli ospiti perché ci piace far scoprire il posto in cui viviamo.\nTi accogliamo noi, e ti diciamo dove mangiare bene e cosa vedere.",
            'valori' => [['casa', 'Come a casa', 'Pochi ospiti, seguiti uno per uno.'], ['germoglio', 'Colazione vera', 'Torte, pane e marmellate fatti da noi.'], ['pin-mappa', 'I posti giusti', 'I nostri indirizzi, quelli che non trovi sulle guide.']],
            'numeri' => [['5', 'camere'], ['9,4', 'il voto degli ospiti'], ['2 km', 'dal centro'], ['1', 'colazione che si ricorda']],
            'faq' => [['A che ora sono check-in e check-out?', 'Check-in dalle 15 alle 20, check-out entro le 10:30. Se arrivi più tardi, scrivici.'], ['La colazione è compresa?', 'Sì, sempre compresa, dalle 8 alle 10.'], ['Si possono portare animali?', 'Scrivi qui la vostra regola sugli animali.'], ['Come si arriva?', 'In auto il parcheggio è gratuito; dalla stazione ti diamo noi le indicazioni.']],
            'recensioni' => ['Camere bellissime e una colazione che da sola vale il viaggio.', 'Ci hanno fatto sentire a casa, e i loro consigli sui ristoranti erano perfetti.', 'Silenzio, pulizia, gentilezza: torneremo di sicuro.'],
        ],
        'associazione' => [
            'nome' => 'Associazione o ente', 'descrizione' => 'Associazione, circolo, società sportiva, onlus, parrocchia.', 'icona' => 'utenti',
            'clienti' => 'soci', 'invito' => 'Unisciti a noi', 'listino' => 'Quote e tessere', 'galleria' => 'Le nostre attività',
            'titolo' => 'Insieme si fa di più, *e meglio.*', 'sotto' => 'Persone che si ritrovano per fare qualcosa di utile, e di bello, insieme.',
            'servizi' => ['Attività', 'Eventi', 'Corsi', 'Volontariato', 'Iniziative sul territorio', 'Tesseramento'],
            'soggetti' => ['Un evento', 'I volontari', 'La sede', 'Un\'attività', 'Un dettaglio', 'Il gruppo'],
            'chi' => "Siamo nati dall'idea di poche persone, oggi siamo una comunità.\nOgni attività la organizziamo con i soci, e ogni idea nuova è benvenuta.",
            'valori' => [['utenti', 'Comunità', 'Ci conosciamo per nome.'], ['cuore-mano', 'Aiuto concreto', 'Le nostre iniziative servono a chi ne ha bisogno.'], ['porta', 'Porte aperte', 'Chiunque può partecipare.']],
            'numeri' => [['15', 'anni insieme'], ['250', 'soci'], ['40', 'eventi all\'anno'], ['1', 'tessera per cominciare']],
            'faq' => [['Come si diventa soci?', 'Compila il form o passa in sede: la tessera si fa in pochi minuti.'], ['Quanto costa la tessera?', 'Scrivi qui la quota annuale e cosa comprende.'], ['Posso partecipare senza essere socio?', 'A molti eventi sì: li trovi segnati nel calendario.'], ['Come posso aiutare?', 'Scrivici: c\'è sempre bisogno di una mano, anche per poche ore.']],
            'recensioni' => ['Sono entrato per un corso e ho trovato un gruppo di amici.', 'Organizzano iniziative che fanno davvero la differenza nel quartiere.', 'Accoglienti con tutti: i miei figli non vedono l\'ora del prossimo evento.'],
        ],
        'altro' => [
            'nome' => 'Un\'altra attività', 'descrizione' => 'Se nessuna delle altre ti somiglia: testi neutri da adattare.', 'icona' => 'scintille',
            'clienti' => 'clienti', 'invito' => 'Contattaci', 'listino' => 'Listino', 'galleria' => 'Galleria',
            'titolo' => 'Il vostro lavoro in una frase, *detta bene.*', 'sotto' => 'Una riga che dice cosa fate e per chi.',
            'servizi' => ['Il primo servizio', 'Il secondo servizio', 'Il terzo servizio', 'Il quarto servizio', 'Il quinto servizio', 'Il sesto servizio'],
            'soggetti' => ['La foto principale', 'Al lavoro', 'Un risultato', 'Il luogo', 'Un dettaglio', 'Il ritratto'],
            'chi' => "Racconta da dove venite e perché fate questo lavoro. Tre o quattro frasi bastano.\nChi legge vuole capire se può fidarsi: date un motivo concreto.",
            'valori' => [['stretta-di-mano', 'Il primo principio', 'Una riga che lo spiega con un esempio.'], ['orologio', 'Il secondo principio', 'Una riga che lo spiega con un esempio.'], ['cuore', 'Il terzo principio', 'Una riga che lo spiega con un esempio.']],
            'numeri' => [['12', 'anni di esperienza'], ['480', 'lavori consegnati'], ['98%', 'clienti che tornano'], ['24 h', 'per una risposta']],
            'faq' => [['La prima domanda che vi fanno?', 'Una risposta breve e diretta.'], ['Quanto costa?', 'Dite da quanto si parte, o dove si trovano i prezzi.'], ['Quanto tempo ci vuole?', 'Un tempo indicativo, e da cosa dipende.'], ['Come si comincia?', 'Il primo passo concreto: scrivere, chiamare, prenotare.']],
            'recensioni' => ['Qui va una frase vera di un cliente: cosa è cambiato dopo.', 'Una seconda frase, di un cliente diverso, con un risultato preciso.', 'La terza frase. Chiedi sempre il permesso prima di pubblicarla.'],
        ],
    ];
}

/** Il valore di partenza delle risposte (il modulo appena aperto). */
function su_misura_risposte_vuote(): array {
    $pagine = [];
    foreach (SU_MISURA_PAGINE as $k => $p) if ($p[3]) $pagine[] = $k;
    return ['attivita' => '', 'citta' => '', 'servizi' => [], 'pagine' => $pagine, 'ogni_servizio' => false];
}

/** Ripulisce le risposte di un modulo (installatore o pannello). Mai un errore: quello che non va si scarta. */
function su_misura_pulisci(array $p): array {
    $att = su_misura_attivita();
    $testo = fn($s, int $max) => mb_substr(trim(preg_replace('/\s+/u', ' ', su_misura_senza_4byte(strip_tags(is_scalar($s) ? (string)$s : '')))), 0, $max);
    $r = ['attivita' => is_string($p['attivita'] ?? null) && isset($att[$p['attivita']]) ? $p['attivita'] : 'altro', 'citta' => $testo($p['citta'] ?? '', 80), 'servizi' => [], 'pagine' => [], 'ogni_servizio' => !empty($p['ogni_servizio'])];
    $servizi = is_array($p['servizi'] ?? null) ? $p['servizi'] : preg_split('/\r?\n/', (string)($p['servizi'] ?? ''));
    foreach ($servizi as $s) {
        $s = $testo($s, 60);
        if ($s !== '' && !in_array(mb_strtolower($s), array_map('mb_strtolower', $r['servizi']), true)) $r['servizi'][] = $s;
        if (count($r['servizi']) >= SU_MISURA_MAX_SERVIZI) break;
    }
    $scelte = is_array($p['pagine'] ?? null) ? $p['pagine'] : [];
    foreach (array_keys(SU_MISURA_PAGINE) as $k) if (in_array($k, $scelte, true)) $r['pagine'][] = $k;
    return $r;
}

/**
 * Via le emoji e gli altri caratteri a 4 byte: un servizio diventa anche il titolo e l'indirizzo di una pagina, e le
 * colonne del CMS possono essere utf8 a 3 byte. Come entità uscivano scritte (&#127837;) nei titoli delle schede.
 */
function su_misura_senza_4byte(string $s): string {
    return (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $s);
}

/** Le risposte salvate (impostazione su_misura_risposte), o null se il sito su misura non c'è mai stato. */
function su_misura_risposte(): ?array {
    $j = json_decode((string)get_setting(SU_MISURA_IMPOSTAZIONE, ''), true);
    return is_array($j) && isset($j['attivita']) ? su_misura_pulisci($j) : null;
}

function su_misura_salva_risposte(array $r): void {
    save_setting(SU_MISURA_IMPOSTAZIONE, json_encode(su_misura_pulisci($r), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/** Quante pagine farà il sito con queste risposte (la home compresa, senza il blog): il riepilogo del modulo. */
function su_misura_quante_pagine(array $r): int {
    $n = 1 + count(array_diff($r['pagine'], ['blog']));
    if ($r['ogni_servizio'] && in_array('servizi', $r['pagine'], true)) $n += count($r['servizi'] ?: array_slice(su_misura_attivita()[$r['attivita']]['servizi'], 0, 3));
    return $n;
}

// ------------------------------------------------------------
// Le immagini finte
// ------------------------------------------------------------

/** I formati: nome => [larghezza, altezza]. */
const SU_MISURA_FORMATI = ['largo' => [1600, 900], 'quadro' => [1000, 1000], 'ritratto' => [900, 1100]];

/** Due colori per i disegni: primario e secondario dello Stile del sito, se si leggono; altrimenti quelli di partenza. */
function su_misura_colori(): array {
    $a = '#6d28d9'; $b = '#0e7490';
    try {
        require_once __DIR__ . '/design.php';
        $c = design_valori()['colori']['sistema'] ?? [];
        if (preg_match('/^#[0-9a-f]{6}$/i', (string)($c['primario'] ?? ''))) $a = (string)$c['primario'];
        if (preg_match('/^#[0-9a-f]{6}$/i', (string)($c['secondario'] ?? ''))) $b = (string)$c['secondario'];
    } catch (Throwable $e) {}
    return [$a, $b];
}

/** Un colore più scuro (per il fondo dei disegni: la scritta bianca deve leggersi sempre). */
function su_misura_scurisci(string $hex, float $f): string {
    $hex = ltrim($hex, '#');
    $c = array_map(fn($i) => (int)round(hexdec(substr($hex, $i, 2)) * $f), [0, 2, 4]);
    return sprintf('#%02x%02x%02x', ...$c);
}

/**
 * Disegna (una volta sola) l'immagine finta di un soggetto in un formato e ritorna ['url', 'file'] (file relativo alla
 * cartella delle immagini). $n distingue i disegni: posizioni e colori cambiano un poco, sempre uguali per lo stesso n.
 */
function su_misura_foto(string $attivitaId, int $n, string $formato): array {
    $att = su_misura_attivita()[$attivitaId] ?? su_misura_attivita()['altro'];
    [$w, $h] = SU_MISURA_FORMATI[$formato] ?? SU_MISURA_FORMATI['largo'];
    $soggetto = (string)($att['soggetti'][($n - 1) % 6] ?? 'Una foto');
    $rel = SU_MISURA_CARTELLA . '/' . $attivitaId . '-' . $n . '-' . $formato . '.svg';
    $dir = cms_uploads_dir() . '/' . SU_MISURA_CARTELLA;
    $file = cms_uploads_dir() . '/' . $rel;
    if (!is_file($file)) {
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        [$a, $b] = su_misura_colori();
        // Icona e scritte: al centro; nel formato largo (lo sfondo delle aperture) in alto a destra, lontano dal titolo,
        // che nelle aperture sta a sinistra e in basso.
        [$mx, $my] = $formato === 'largo' ? [(int)($w * .82), (int)($h * .24)] : [(int)($w / 2), (int)($h / 2)];
        // Fondo scuro e scritte bianche: si legge con qualunque colore del sito.
        [$a, $b] = $n % 2 ? [su_misura_scurisci($a, .55), su_misura_scurisci($b, .45)] : [su_misura_scurisci($b, .45), su_misura_scurisci($a, .55)];
        $icona = '';
        try {
            require_once __DIR__ . '/icone.php';
            $svg = icona_esiste((string)$att['icona']) ? icona_svg((string)$att['icona']) : '';
            if (preg_match('~^<svg([^>]*)>(.*)</svg>$~s', $svg, $m)) {
                $pieno = str_contains($m[1], 'fill="currentColor"');
                $lato = (int)round(min($w, $h) * .2);
                $icona = '<svg x="' . (int)($mx - $lato / 2) . '" y="' . (int)($my - $lato * 1.25) . '" width="' . $lato . '" height="' . $lato . '" viewBox="0 0 24 24" '
                    . ($pieno ? 'fill="#ffffff" fill-opacity="0.9" stroke="none"' : 'fill="none" stroke="#ffffff" stroke-opacity="0.9" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"') . '>'
                    . str_replace('currentColor', '#ffffff', $m[2]) . '</svg>';
            }
        } catch (Throwable $e) {}
        $cx = (int)($w * (.2 + ($n * 37 % 60) / 100)); $cy = (int)($h * (.25 + ($n * 53 % 50) / 100));
        $grande = (int)round(min($w, $h) * .055); $piccolo = (int)round(min($w, $h) * .034);
        $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" role="img" aria-label="' . $esc($soggetto . ': foto da sostituire') . '">'
            . '<defs><linearGradient id="f" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $a . '"/><stop offset="1" stop-color="' . $b . '"/></linearGradient>'
            . '<radialGradient id="l" cx="0.5" cy="0.5" r="0.5"><stop offset="0" stop-color="#ffffff" stop-opacity="0.18"/><stop offset="1" stop-color="#ffffff" stop-opacity="0"/></radialGradient>'
            . '<pattern id="p" width="32" height="32" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.4" fill="#ffffff" fill-opacity="0.10"/></pattern></defs>'
            . '<rect width="' . $w . '" height="' . $h . '" fill="url(#f)"/><rect width="' . $w . '" height="' . $h . '" fill="url(#p)"/>'
            . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . (int)(min($w, $h) * .55) . '" fill="url(#l)"/>'
            . $icona
            . '<text x="' . $mx . '" y="' . (int)($my + $grande * 1.1) . '" text-anchor="middle" font-family="Inter, Helvetica, Arial, sans-serif" font-size="' . $grande . '" font-weight="700" fill="#ffffff">' . $esc($soggetto) . '</text>'
            . '<text x="' . $mx . '" y="' . (int)($my + $grande * 1.1 + $piccolo * 2) . '" text-anchor="middle" font-family="Inter, Helvetica, Arial, sans-serif" font-size="' . $piccolo . '" fill="#ffffff" fill-opacity="0.8">Sostituisci questa foto</text>'
            . '</svg>';
        if (@file_put_contents($file, $svg) !== false) @chmod($file, 0644);
    }
    return ['url' => cms_uploads_url() . '/' . $rel, 'file' => $rel];
}

// ------------------------------------------------------------
// Il kit: dalle risposte alle pagine
// ------------------------------------------------------------

/**
 * I dati del kit "su misura" per queste risposte, nella forma di un file di includes/kit/ (home, pagine, menu, form),
 * più 'segnaposto' => [file delle immagini finte usate], che kit_costruisci mette nel registro.
 */
function su_misura_kit(?array $r = null): array {
    $r = su_misura_pulisci($r ?? (su_misura_risposte() ?? su_misura_risposte_vuote()));
    $att = su_misura_attivita()[$r['attivita']];
    $aid = $r['attivita'];
    $servizi = $r['servizi'] ?: array_slice($att['servizi'], 0, 3);
    $vuoi = fn(string $k) => in_array($k, $r['pagine'], true);
    $dove = $r['citta'] !== '' ? $r['citta'] : 'la tua città';
    $usate = [];
    $foto = function (int $n, string $formato) use ($aid, &$usate): string {
        $f = su_misura_foto($aid, $n, $formato);
        $usate[$f['file']] = true;
        return $f['url'];
    };
    $contatti = $vuoi('contatti') ? '/contatti/' : '/';
    $sopra = ['spazio_sotto' => ['d' => 0, 'm' => 0]];
    $sotto = ['spazio_sopra' => ['d' => 0, 'm' => 0]];
    $titoloCentro = fn(string $occhiello, string $titolo, string $sotto = '') => ['titolo', ['occhiello' => $occhiello, 'testo' => $titolo, 'livello' => 'h2', 'sottotitolo' => $sotto, 'allineamento' => ['d' => 'center'], 'margine_sotto' => ['d' => 44],
        'css_personalizzato' => 'selector .sez-intro{margin-left:auto;margin-right:auto}']];
    $banner = fn(string $occhiello, string $titolo, string $testo, array $pulsanti = []) => ['imp' => ['spazio_sopra' => ['d' => 40], 'spazio_sotto' => ['d' => 24]], 'widget' => [
        ['hero', ['disposizione' => 'banner', 'occhiello' => $occhiello, 'titolo' => $titolo, 'testo' => $testo, 'pulsanti' => $pulsanti, 'immagine' => '', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]]]];
    $invito = fn(string $titolo, string $sottotitolo) => ['imp' => ['sfondo' => 'gradiente', 'spazio_sopra' => ['d' => 64], 'spazio_sotto' => ['d' => 64]], 'widget' => [
        ['titolo', ['testo' => $titolo, 'livello' => 'h2', 'sottotitolo' => $sottotitolo, 'allineamento' => ['d' => 'center'], 'margine_sotto' => ['d' => 28], 'css_personalizzato' => 'selector .sez-intro{margin-left:auto;margin-right:auto}']],
        ['pulsanti', ['voci' => [['testo' => $att['invito'], 'link' => $contatti, 'stile' => 'viola', 'nuova' => false]], 'allineamento' => ['d' => 'center'], 'margine_sotto' => ['d' => 0]]]]];
    // Gli indirizzi dei servizi: una pagina ognuno, oppure un'ancora nella pagina Servizi, oppure i contatti.
    $slugServizio = [];
    foreach ($servizi as $i => $s) $slugServizio[$i] = su_misura_slug($s, 'servizio-' . ($i + 1));
    $linkServizio = function (int $i) use ($r, $vuoi, $slugServizio, $contatti): string {
        if ($vuoi('servizi') && $r['ogni_servizio']) return '/' . $slugServizio[$i] . '/';
        if ($vuoi('servizi')) return '/servizi/#' . $slugServizio[$i];
        return $contatti;
    };
    $schedeServizi = function (bool $evidenza) use ($servizi, $linkServizio, $att): array {
        $voci = [];
        foreach ($servizi as $i => $s) $voci[] = ['titolo' => $s, 'testo' => 'Due righe che dicono cosa comprende e a chi serve: le scrivi tu, nell\'editor.', 'link' => $linkServizio($i), 'invito' => 'Scopri di più', 'evidenza' => $evidenza && $i === 0];
        return ['schede', ['voci' => $voci, 'colonne' => ['d' => min(3, max(1, count($voci))), 't' => 2, 'm' => 1], 'margine_sotto' => ['d' => 0]]];
    };
    $recensioni = function () use ($att, $titoloCentro, $sopra, $sotto): array {
        $col = [];
        foreach ($att['recensioni'] as $t) $col[] = ['imp' => ['riquadro' => true, 'spazio_interno' => ['d' => 28]], 'widget' => [['testimonianza', ['testo' => $t, 'nome' => 'Nome Cognome', 'ruolo' => 'Cliente', 'stelle' => 5, 'margine_sotto' => ['d' => 0]]]]];
        return [['imp' => $sopra, 'widget' => [$titoloCentro('Dicono di noi', 'Le parole di chi ci ha scelto', 'Esempi da sostituire con recensioni vere, chiedendo il permesso.')]], ['imp' => $sotto, 'colonne' => $col]];
    };
    $galleria = function (int $quante) use ($att, $foto): array {
        $imm = [];
        for ($k = 1; $k <= $quante; $k++) $imm[] = ['immagine' => $foto((($k - 1) % 6) + 1, 'quadro'), 'alt' => $att['soggetti'][($k - 1) % 6], 'didascalia' => ''];
        return ['galleria', ['immagini' => $imm, 'disposizione' => 'griglia', 'colonne' => ['d' => 3, 't' => 3, 'm' => 2], 'margine_sotto' => ['d' => 0]]];
    };
    $faq = function () use ($att): array {
        return ['faq', ['voci' => array_map(fn($x) => ['domanda' => $x[0], 'risposta' => $x[1]], $att['faq']), 'margine_sotto' => ['d' => 0]]];
    };
    $presentazione = function (string $occhiello) use ($att, $foto, $dove): array {
        return ['presentazione', ['immagine' => $foto(6, 'ritratto'), 'occhiello' => $occhiello, 'titolo' => 'Le persone dietro *{sito}*', 'testo' => $att['chi'],
            'voci' => [['etichetta' => 'Dal', 'valore' => 'l\'anno in cui avete cominciato'], ['etichetta' => 'Dove', 'valore' => $dove], ['etichetta' => 'Per chi', 'valore' => 'i nostri ' . $att['clienti']]], 'margine_sotto' => ['d' => 0]]];
    };

    // La home
    $home = [
        ['imp' => ['spazio_sopra' => ['d' => 32], 'spazio_sotto' => ['d' => 40]], 'widget' => [
            ['hero', ['disposizione' => 'copertina', 'occhiello' => '{sito}' . ($r['citta'] !== '' ? ' · ' . $r['citta'] : ''), 'titolo' => $att['titolo'], 'testo' => '{descrizione}',
                'pulsanti' => array_values(array_filter([['testo' => $att['invito'], 'link' => $contatti, 'stile' => 'viola'], $vuoi('servizi') ? ['testo' => 'I servizi', 'link' => '/servizi/', 'stile' => 'filo'] : null])),
                'immagine' => $foto(1, 'largo'), 'foto_trattamento' => 'naturale', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]]],
        ]],
        ['imp' => $sopra, 'widget' => [$titoloCentro('Cosa facciamo', count($servizi) > 1 ? 'I nostri servizi' : 'Il nostro servizio', 'Quello che trovi da noi, in breve.')]],
        ['imp' => $sotto, 'widget' => [$schedeServizi(true)]],
        ['imp' => ['larghezza' => 'stretta'], 'widget' => [$presentazione('Chi siamo')]],
        ['imp' => ['sfondo' => 'vetro', 'spazio_sopra' => ['d' => 48], 'spazio_sotto' => ['d' => 48]], 'widget' => [
            ['numeri', ['voci' => array_map(fn($x) => ['valore' => $x[0], 'etichetta' => $x[1]], $att['numeri']), 'colonne' => ['d' => 4, 't' => 2, 'm' => 2], 'margine_sotto' => ['d' => 0]]]]],
    ];
    if ($vuoi('galleria')) {
        $home[] = ['imp' => $sopra, 'widget' => [$titoloCentro($att['galleria'], 'Uno sguardo da vicino', 'Foto finte da sostituire con le tue: in Media, cartella "Da sostituire".')]];
        $home[] = ['imp' => $sotto, 'widget' => [$galleria(6)]];
    }
    if ($vuoi('recensioni')) foreach ($recensioni() as $s) $home[] = $s;
    $home[] = $invito('Ti aspettiamo' . ($r['citta'] !== '' ? ' a ' . $r['citta'] : ''), 'Scrivici o passa a trovarci: ti rispondiamo presto.');

    // Le pagine
    $pagine = []; $menu = [['Home', '/']];
    if ($vuoi('chi-siamo')) {
        $valori = [];
        foreach ($att['valori'] as $v) $valori[] = [['riquadro_icona', ['icona' => $v[0], 'titolo' => $v[1], 'testo' => $v[2], 'margine_sotto' => ['d' => 0]]]];
        $pagine[] = ['titolo' => 'Chi siamo', 'slug' => 'chi-siamo', 'sezioni' => [
            $banner('Chi siamo', 'Le persone dietro {sito}', 'Chi siamo, da quanto lo facciamo e perché.'),
            ['imp' => ['larghezza' => 'stretta'], 'widget' => [$presentazione('La nostra storia')]],
            ['imp' => $sopra, 'widget' => [$titoloCentro('Come lavoriamo', 'Tre cose a cui teniamo')]],
            ['imp' => $sotto, 'colonne' => $valori],
            $invito('Vuoi conoscerci?', 'Scrivici: ti rispondiamo noi, non un risponditore.'),
        ]];
        $menu[] = ['Chi siamo', '/chi-siamo/'];
    }
    if ($vuoi('servizi')) {
        $sezioni = [$banner('Servizi', 'Cosa facciamo per i nostri ' . $att['clienti'], 'Tutti i servizi in una pagina: scegli quello che ti serve.', [['testo' => $att['invito'], 'link' => $contatti, 'stile' => 'viola']]),
            ['widget' => [$schedeServizi(false)]]];
        if (!$r['ogni_servizio']) {
            // Una sezione per servizio, con la sua ancora: le schede della home portano qui.
            foreach ($servizi as $i => $s) {
                $sezioni[] = ['imp' => ['allineamento_verticale' => 'center', 'spazio_colonne' => ['d' => 56], 'ancora' => $slugServizio[$i]], 'colonne' => [
                    [['titolo', ['occhiello' => 'Servizio', 'testo' => $s, 'livello' => 'h2', 'sottotitolo' => 'Cosa comprende, per chi è, quanto dura. Due o tre frasi: le scrivi tu.', 'margine_sotto' => ['d' => 20]]],
                     ['pulsanti', ['voci' => [['testo' => $att['invito'], 'link' => $contatti, 'stile' => 'viola', 'nuova' => false]], 'margine_sotto' => ['d' => 0]]]],
                    [['galleria', ['immagini' => [['immagine' => $foto((($i + 1) % 6) + 1, 'largo'), 'alt' => $s, 'didascalia' => '']], 'disposizione' => 'griglia', 'colonne' => ['d' => 1, 't' => 1, 'm' => 1], 'margine_sotto' => ['d' => 0]]]],
                ]];
            }
        }
        $sezioni[] = $invito('Non sai cosa scegliere?', 'Raccontaci cosa ti serve: ti consigliamo noi.');
        $pagine[] = ['titolo' => 'Servizi', 'slug' => 'servizi', 'sezioni' => $sezioni];
        $voce = ['Servizi', '/servizi/'];
        if ($r['ogni_servizio']) {
            foreach ($servizi as $i => $s) {
                $pagine[] = ['titolo' => $s, 'slug' => $slugServizio[$i], 'sezioni' => [
                    $banner('Servizi', $s, 'Una riga che dice cosa comprende e a chi serve.', [['testo' => $att['invito'], 'link' => $contatti, 'stile' => 'viola']]),
                    ['imp' => ['allineamento_verticale' => 'center', 'spazio_colonne' => ['d' => 56]], 'colonne' => [
                        [['titolo', ['occhiello' => 'In breve', 'testo' => 'Cosa comprende', 'livello' => 'h2', 'margine_sotto' => ['d' => 20]]],
                         ['testo', ['contenuto' => '<p>Racconta questo servizio in tre o quattro frasi: cosa fate, come, in quanto tempo. Chi legge deve capire se fa per lui.</p><p>Poi una frase su cosa succede dopo: il risultato, concreto.</p>', 'margine_sotto' => ['d' => 0]]]],
                        [['galleria', ['immagini' => [['immagine' => $foto((($i + 1) % 6) + 1, 'largo'), 'alt' => $s, 'didascalia' => '']], 'disposizione' => 'griglia', 'colonne' => ['d' => 1, 't' => 1, 'm' => 1], 'margine_sotto' => ['d' => 0]]]],
                    ]],
                    ['imp' => ['larghezza' => 'stretta'], 'widget' => [['faq', ['voci' => [['domanda' => 'Quanto costa?', 'risposta' => 'Scrivi da quanto si parte, o rimanda al listino.'], ['domanda' => 'Quanto tempo serve?', 'risposta' => 'Un tempo indicativo, e da cosa dipende.']], 'margine_sotto' => ['d' => 0]]]]],
                    $invito('Ti interessa ' . mb_strtolower(mb_substr($s, 0, 1)) . mb_substr($s, 1) . '?', 'Scrivici e ti diciamo come funziona.'),
                ]];
            }
            $voce[] = array_map(fn($i) => [$servizi[$i], '/' . $slugServizio[$i] . '/'], array_keys($servizi));
        }
        $menu[] = $voce;
    }
    if ($vuoi('listino')) {
        $voci = [];
        foreach ($servizi as $i => $s) $voci[] = ['titolo' => $s, 'prezzo' => ($i + 1) * 20 + 20 . ' €', 'testo' => 'Una riga che dice cosa comprende. Il prezzo è d\'esempio: cambialo.', 'immagine' => '', 'link' => ''];
        $voci[] = ['titolo' => 'Su richiesta', 'prezzo' => 'su richiesta', 'testo' => 'Per quello che non sta in un prezzo fisso.', 'immagine' => '', 'link' => ''];
        $pagine[] = ['titolo' => $att['listino'], 'slug' => 'listino', 'sezioni' => [
            $banner('Prezzi', $att['listino'], 'Prezzi chiari, aggiornati. I numeri qui sono d\'esempio: cambiali nell\'editor.'),
            ['imp' => ['larghezza' => 'stretta'], 'widget' => [['listino', ['voci' => $voci, 'margine_sotto' => ['d' => 0]]]]],
            $invito('Un dubbio sui prezzi?', 'Scrivici: ti mandiamo un preventivo su misura.'),
        ]];
        $menu[] = [$att['listino'], '/listino/'];
    }
    if ($vuoi('galleria')) {
        $pagine[] = ['titolo' => $att['galleria'], 'slug' => 'galleria', 'sezioni' => [
            $banner('Galleria', $att['galleria'], 'Le foto qui sono finte: sostituiscile in Media, cartella "Da sostituire", e cambiano in ogni pagina.'),
            ['widget' => [$galleria(9)]],
            $invito('Ti piace quello che vedi?', 'Vieni a vederlo di persona.'),
        ]];
        $menu[] = [$att['galleria'], '/galleria/'];
    }
    if ($vuoi('recensioni')) {
        $pagine[] = ['titolo' => 'Recensioni', 'slug' => 'recensioni', 'sezioni' => array_merge(
            [$banner('Recensioni', 'Dicono di noi', 'Le parole dei nostri ' . $att['clienti'] . '. Qui ci sono esempi: mettici le recensioni vere.')],
            $recensioni(),
            [$invito('Vuoi essere il prossimo?', 'Scrivici: cominciamo da una chiacchierata.')])];
        $menu[] = ['Recensioni', '/recensioni/'];
    }
    if ($vuoi('faq')) {
        $pagine[] = ['titolo' => 'Domande frequenti', 'slug' => 'domande-frequenti', 'sezioni' => [
            $banner('Domande frequenti', 'Le domande che ci fanno più spesso', 'Le risposte veloci. Se manca la tua, scrivici.'),
            ['imp' => ['larghezza' => 'stretta'], 'widget' => [$faq()]],
            $invito('Non hai trovato la risposta?', 'Scrivici: ti rispondiamo noi.'),
        ]];
        $menu[] = ['Domande frequenti', '/domande-frequenti/'];
    }
    if ($vuoi('blog')) $menu[] = ['Blog', '/blog/'];
    if ($vuoi('contatti')) {
        $sezioni = [
            $banner('Contatti', 'Come trovarci', 'Scrivici, chiamaci o passa a trovarci' . ($r['citta'] !== '' ? ' a ' . $r['citta'] : '') . '.'),
            ['imp' => ['spazio_colonne' => ['d' => 56]], 'colonne' => [
                ['imp' => ['larghezza' => ['d' => 40, 'm' => 100]], 'widget' => [
                    ['titolo', ['occhiello' => 'Contatti', 'testo' => 'Scrivici', 'livello' => 'h2', 'sottotitolo' => 'Ti rispondiamo entro un giorno lavorativo.', 'margine_sotto' => ['d' => 28]]],
                    ['elenco_icone', ['voci' => [
                        ['testo' => 'nome@tuosito.it', 'icona' => 'email', 'link' => 'mailto:nome@tuosito.it'], ['testo' => '+39 000 000 0000', 'icona' => 'telefono', 'link' => 'tel:+390000000000'],
                        ['testo' => 'Via Esempio 1, ' . ($r['citta'] !== '' ? $r['citta'] : 'Città'), 'icona' => 'pin-mappa', 'link' => ''], ['testo' => 'Dal lunedì al venerdì, 9-18', 'icona' => 'orologio', 'link' => ''],
                    ], 'margine_sotto' => ['d' => 0]]],
                ]],
                ['imp' => ['larghezza' => ['d' => 60, 'm' => 100]], 'widget' => [['modulo', ['tipo' => 'form:contatti', 'margine_sotto' => ['d' => 0]]]]],
            ]],
        ];
        // La mappa solo se si sa dove: si carica al clic (niente contatti con Google prima).
        if ($r['citta'] !== '') $sezioni[] = ['widget' => [['mappa', ['fornitore' => 'google', 'indirizzo' => $r['citta'], 'zoom' => 13, 'al_clic' => true, 'margine_sotto' => ['d' => 0]]]]];
        $pagine[] = ['titolo' => 'Contatti', 'slug' => 'contatti', 'sezioni' => $sezioni];
        $menu[] = ['Contatti', '/contatti/'];
    }
    return [
        'nome' => 'Su misura', 'descrizione' => 'Rispondi a quattro domande e il sito si compone da solo.',
        'form' => ['titolo' => 'Scrivici', 'intro' => 'Ti rispondiamo entro un giorno lavorativo.'],
        'home' => $home, 'pagine' => $pagine, 'menu' => $menu,
        'segnaposto' => array_keys($usate),
    ];
}

/**
 * Le domande, come campi HTML: le usano l'installatore (passo 4) e il pannello (admin/sito-su-misura.php), ognuno con
 * il suo form intorno e il suo foglio di stile. Classi sm-*. $h e la funzione che fa l'escape (ih nell'installatore, h nel pannello).
 */
function su_misura_campi_html(array $r, callable $h): string {
    // Nessuna attività scelta all'inizio (pulisci la farebbe diventare "altro"): la domanda è obbligatoria.
    $scelta = isset(su_misura_attivita()[(string)($r['attivita'] ?? '')]) ? (string)$r['attivita'] : '';
    $r = su_misura_pulisci($r + su_misura_risposte_vuote());
    $out = '<fieldset class="sm-gruppo"><legend>1. Che attività è?</legend><div class="sm-attivita">';
    foreach (su_misura_attivita() as $id => $a) {
        $out .= '<label class="sm-carta"><input type="radio" name="attivita" value="' . $h($id) . '"' . ($scelta === $id ? ' checked' : '') . ' required>'
            . '<b>' . $h($a['nome']) . '</b><small>' . $h($a['descrizione']) . '</small></label>';
    }
    $out .= '</div></fieldset>';
    $out .= '<fieldset class="sm-gruppo"><legend>2. Dove si trova?</legend><label class="sm-campo"><span>Città o paese (facoltativo)</span>'
        . '<input type="text" name="citta" maxlength="80" value="' . $h($r['citta']) . '" placeholder="Per esempio: Bologna"></label>'
        . '<p class="sm-nota">Va nei testi, nei contatti e nella mappa della pagina Contatti.</p></fieldset>';
    $out .= '<fieldset class="sm-gruppo"><legend>3. Cosa offri?</legend><p class="sm-nota">Fino a ' . SU_MISURA_MAX_SERVIZI . ' servizi o prodotti, in due o tre parole ciascuno. Se li lasci vuoti, mettiamo quelli tipici della tua attività.</p><div class="sm-servizi">';
    for ($i = 0; $i < SU_MISURA_MAX_SERVIZI; $i++) {
        $out .= '<input type="text" name="servizi[]" maxlength="60" value="' . $h((string)($r['servizi'][$i] ?? '')) . '" placeholder="Servizio ' . ($i + 1) . '" aria-label="Servizio ' . ($i + 1) . '">';
    }
    $out .= '</div></fieldset>';
    $out .= '<fieldset class="sm-gruppo"><legend>4. Quali pagine vuoi?</legend><p class="sm-nota">La home c\'è sempre. Le pagine si aggiungono e si tolgono anche dopo, dal pannello.</p><div class="sm-pagine">';
    foreach (SU_MISURA_PAGINE as $k => $p) {
        $out .= '<label class="sm-spunta"><input type="checkbox" name="pagine[]" value="' . $h($k) . '"' . (in_array($k, $r['pagine'], true) ? ' checked' : '') . '> <span><b>' . $h($p[0]) . '</b> ' . $h($p[2]) . '</span></label>';
    }
    $out .= '<label class="sm-spunta sm-ogni"><input type="checkbox" name="ogni_servizio" value="1"' . ($r['ogni_servizio'] ? ' checked' : '') . '> <span><b>Una pagina per ogni servizio</b> sotto Servizi nel menu, invece di tutti in una pagina.</span></label>';
    $out .= '</div></fieldset>';
    return $out;
}

/** Uno slug per un nome di servizio; se resta vuoto, quello di riserva. */
function su_misura_slug(string $s, string $riserva): string {
    $s = mb_strtolower(html_entity_decode($s, ENT_QUOTES, 'UTF-8'));
    $s = strtr($s, ['à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', '\'' => '-']);
    $s = trim((string)preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    return $s !== '' ? mb_substr($s, 0, 60) : $riserva;
}

// ------------------------------------------------------------
// Le immagini da sostituire
// ------------------------------------------------------------

/** Le immagini finte ancora sul disco: [['file' => 'segnaposto/...', 'url' => ...], ...]. */
function su_misura_segnaposto(): array {
    $dir = cms_uploads_dir() . '/' . SU_MISURA_CARTELLA;
    $out = [];
    foreach (glob($dir . '/*.svg') ?: [] as $f) {
        $rel = SU_MISURA_CARTELLA . '/' . basename($f);
        $out[] = ['file' => $rel, 'url' => cms_uploads_url() . '/' . $rel];
    }
    return $out;
}

/** Il nome di un file delle immagini finte, controllato: "segnaposto/<attività>-<n>-<formato>.svg", o ''. */
function su_misura_file_valido(string $rel): string {
    return preg_match('~^' . SU_MISURA_CARTELLA . '/[a-z]{2,20}-\d{1,2}-(largo|quadro|ritratto)\.svg$~', $rel) ? $rel : '';
}

/** Le immagini finte ancora mostrate da una pagina, un articolo o un modello: la voce dei Primi passi e il filtro di Media. */
function su_misura_segnaposto_usati(): array {
    require_once __DIR__ . '/kit-partenza.php';
    return array_values(array_filter(su_misura_segnaposto(), fn($s) => kit_immagine_usata($s['file'])));
}

/**
 * Sostituisci (Media, 1.7.0): una foto vera al posto di un'immagine finta, dappertutto. La foto si carica come in Media
 * (media_carica: tipo letto dal file, SVG ripulito, foto alleggerita), poi il suo indirizzo prende il posto di quello finto
 * in pagine, articoli e modelli (struttura, bozza e HTML già pronto, anche con le barre scritte \/ dal JSON), e il
 * file finto si cancella. Ritorna ['url', 'righe'] oppure ['errore'].
 */
function su_misura_sostituisci(string $url, ?array $foto): array {
    require_once __DIR__ . '/media.php';
    $base = rtrim(cms_uploads_url(), '/') . '/';
    $rel = str_starts_with($url, $base) ? su_misura_file_valido(substr($url, strlen($base))) : '';
    if ($rel === '' || !is_file(cms_uploads_dir() . '/' . $rel)) return ['errore' => 'Si sostituiscono solo le immagini finte del sito su misura (Media, Da sostituire).'];
    $r = media_carica($foto, 'pagine');
    if (!isset($r['url'])) return $r;
    $nuovo = (string)$r['url'];
    $righe = 0;
    $tabelle = ['pages', 'articles', 'modelli'];
    foreach ($tabelle as $t) {
        try {
            $colonne = db()->query('SHOW COLUMNS FROM ' . tbl($t))->fetchAll();
        } catch (Throwable $e) { continue; }
        foreach ($colonne as $c) {
            if (!preg_match('/text|char/i', (string)$c['Type'])) continue;
            $col = '`' . str_replace('`', '', (string)$c['Field']) . '`';
            foreach ([[$url, $nuovo], [str_replace('/', '\\/', $url), str_replace('/', '\\/', $nuovo)]] as [$da, $a]) {
                $st = db()->prepare('UPDATE ' . tbl($t) . " SET $col = REPLACE($col, ?, ?) WHERE $col LIKE ?");
                $st->execute([$da, $a, '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $da) . '%']);
                $righe += $st->rowCount();
            }
        }
    }
    @unlink(cms_uploads_dir() . '/' . $rel);
    return ['url' => $nuovo, 'righe' => $righe];
}
