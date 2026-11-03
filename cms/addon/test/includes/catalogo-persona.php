<?php
/**
 * Addon Test e questionari, 2.23.0: il catalogo delle domande sulla persona. Non danno punti: compaiono alla fine del test,
 * prima del risultato, e servono alle statistiche per gruppi (anonime) e ai tag di chi si iscrive alla newsletter.
 *
 * Ogni voce: chiave => [nome (breve, per i tag: "Età: 35-44"), testo (la domanda), opzioni (le risposte, in ordine)].
 * Quando l'autore accende una domanda in un test, domanda e risposte si copiano nel test (dati.persona) e lì si cambiano:
 * cambiare questo catalogo non tocca i test già fatti. Le chiavi delle risposte sono o1, o2... nell'ordine qui sotto.
 * "Preferisco non rispondere" non sta qui: si aggiunge da solo alle domande facoltative (chiave TEST_PERSONA_NR).
 *
 * I settori seguono le sezioni e le divisioni ATECO 2025 dell'ISTAT, raggruppate e scritte in parole semplici.
 */

/** Il catalogo: chiave => ['nome', 'testo', 'opzioni' => [testo, ...]]. */
function test_persona_catalogo_grezzo(): array {
    return [
        'eta' => ['Età', 'Quanti anni hai?', ['18-24', '25-34', '35-44', '45-54', '55-64', '65 e più']],
        'genere' => ['Genere', 'Come ti identifichi?', ['Donna', 'Uomo', 'Non binario', 'In un altro modo']],
        'occupazione' => ['Occupazione', 'Qual è la tua occupazione?', ['Disoccupato', 'Studente', 'In cerca di prima occupazione', 'Operaio', 'Impiegato', 'Quadro', 'Manager', 'Dirigente o direttore',
            'Libero professionista', 'Lavoratore autonomo o artigiano', 'Imprenditore o proprietario d\'azienda', 'Insegnante', 'Forze dell\'ordine o militare', 'Sanitario', 'Pensionato',
            'Casalinga o casalingo', 'Altro']],
        'settore' => ['Settore', 'In quale settore lavori (o hai lavorato per ultimo)?', [
            'Agricoltura e allevamento', 'Silvicoltura e legno da bosco', 'Pesca e acquacoltura', 'Estrazione di minerali e cave', 'Alimentari e bevande (produzione)', 'Tessile, abbigliamento e moda',
            'Pelle, calzature e accessori', 'Legno, carta e stampa', 'Chimica, petrolio e plastica', 'Farmaceutica', 'Vetro, ceramica e materiali da costruzione', 'Metallurgia e lavorazione dei metalli',
            'Elettronica, computer e ottica', 'Apparecchi elettrici', 'Macchinari e impianti', 'Auto, moto e mezzi di trasporto', 'Mobili e arredamento', 'Gioielli, giocattoli e altre manifatture',
            'Riparazione e installazione di macchine', 'Energia: elettricità e gas', 'Acqua, rifiuti e riciclo', 'Costruzioni ed edilizia', 'Impianti per edifici (elettrici, idraulici, termici)',
            'Commercio di auto e moto, officine', 'Commercio all\'ingrosso', 'Negozi e commercio al dettaglio', 'Commercio online', 'Trasporto di persone', 'Trasporto merci e logistica',
            'Magazzini, spedizioni e corrieri', 'Poste', 'Alberghi e alloggi', 'Ristoranti e bar', 'Editoria (libri, giornali, riviste)', 'Cinema, video, televisione e radio', 'Musica',
            'Telecomunicazioni', 'Informatica e software', 'Servizi digitali e internet', 'Banche', 'Assicurazioni', 'Finanza e investimenti', 'Immobiliare', 'Studi legali', 'Contabilità e consulenza fiscale',
            'Consulenza aziendale e direzione', 'Architettura e ingegneria', 'Ricerca scientifica', 'Pubblicità, marketing e comunicazione', 'Design, fotografia e traduzioni', 'Veterinaria',
            'Agenzie per il lavoro e risorse umane', 'Agenzie di viaggio e turismo', 'Vigilanza e sicurezza', 'Pulizie e manutenzione di edifici', 'Servizi alle imprese (segreteria, call center, fiere)',
            'Pubblica amministrazione', 'Forze armate e forze dell\'ordine', 'Scuola e istruzione', 'Università e formazione', 'Sanità (ospedali, studi medici)', 'Assistenza sociale, anziani e disabili',
            'Arte, spettacolo e intrattenimento', 'Biblioteche, musei e beni culturali', 'Sport, palestre e tempo libero', 'Associazioni, sindacati ed enti religiosi', 'Riparazioni per la casa e le persone',
            'Parrucchieri, estetica e benessere', 'Lavoro domestico e assistenza familiare (colf, badanti)', 'Organizzazioni internazionali', 'Altro']],
        'stato' => ['Stato civile', 'Qual è la tua situazione sentimentale?', ['Single', 'Fidanzato o fidanzata', 'Convivente', 'Sposato o sposata o unito civilmente', 'Separato o divorziato', 'Vedovo o vedova']],
        'figli' => ['Figli', 'Quanti figli hai?', ['0', '1', '2', '3', '4 o più']],
        'convive' => ['Vive con', 'Con chi vivi?', ['Da solo o da sola', 'Con il partner', 'Con il partner e i figli', 'Solo con i figli', 'Con i genitori', 'Con amici o coinquilini', 'In un altro modo']],
        'reddito' => ['Reddito', 'Qual è il tuo reddito annuo lordo?', ['Meno di 15.000 euro', '15.000-28.000 euro', '28.000-50.000 euro', '50.000-80.000 euro', '80.000-120.000 euro', 'Più di 120.000 euro']],
        'studio' => ['Studio', 'Qual è il tuo titolo di studio più alto?', ['Licenza elementare o nessun titolo', 'Licenza media', 'Qualifica professionale', 'Diploma di scuola superiore', 'Laurea triennale',
            'Laurea magistrale o a ciclo unico', 'Master o specializzazione', 'Dottorato di ricerca']],
        'regione' => ['Regione', 'In quale regione vivi?', ['Abruzzo', 'Basilicata', 'Calabria', 'Campania', 'Emilia-Romagna', 'Friuli-Venezia Giulia', 'Lazio', 'Liguria', 'Lombardia', 'Marche', 'Molise',
            'Piemonte', 'Puglia', 'Sardegna', 'Sicilia', 'Toscana', 'Trentino-Alto Adige', 'Umbria', 'Valle d\'Aosta', 'Veneto', 'Estero']],
        'comune' => ['Comune', 'Quanto è grande il comune in cui vivi?', ['Meno di 5.000 abitanti', '5.000-20.000 abitanti', '20.000-100.000 abitanti', '100.000-500.000 abitanti', 'Più di 500.000 abitanti']],
        'azienda' => ['Azienda', 'Quante persone lavorano nella tua azienda?', ['Lavoro da solo', '2-9', '10-49', '50-249', '250 e più']],
        'esperienza' => ['Esperienza', 'Da quanti anni lavori?', ['Meno di 2 anni', '2-5 anni', '6-10 anni', '11-20 anni', '21-30 anni', 'Più di 30 anni']],
        'ruolo' => ['Ruolo', 'Qual è il tuo ruolo?', ['Operativo', 'Coordinatore', 'Responsabile', 'Direzione', 'Titolare']],
        'contratto' => ['Contratto', 'Che tipo di contratto hai?', ['Tempo indeterminato', 'Tempo determinato', 'Apprendistato o stage', 'Partita IVA', 'Collaborazione occasionale', 'Contratto in somministrazione',
            'Senza contratto', 'Non lavoro']],
        'modalita' => ['Modalità di lavoro', 'Dove lavori di solito?', ['In sede', 'Da casa', 'Misto (un po\' in sede e un po\' da casa)', 'In giro (dai clienti, in viaggio)']],
        'ore' => ['Ore di lavoro', 'Quante ore lavori in una settimana normale?', ['Meno di 20', '20-30', '31-40', '41-50', '51-60', 'Più di 60']],
        'turni' => ['Turni', 'Lavori a turni, di notte o nel fine settimana?', ['No', 'A volte', 'Spesso', 'Sempre']],
        'pendolarismo' => ['Viaggio casa-lavoro', 'Quanto tempo ci metti ad andare al lavoro (solo andata)?', ['Lavoro da casa', 'Meno di 15 minuti', '15-30 minuti', '31-60 minuti', 'Più di un\'ora']],
        'caregiver' => ['Si occupa di un familiare', 'Ti occupi di un familiare che ha bisogno di assistenza (anziano, malato, con disabilità)?', ['No', 'Sì, qualche ora a settimana', 'Sì, ogni giorno']],
        'sport' => ['Attività fisica', 'Quanto spesso fai sport o attività fisica?', ['Mai', 'Meno di una volta a settimana', '1-2 volte a settimana', '3-4 volte a settimana', '5 volte o più a settimana']],
        'sonno' => ['Sonno', 'Quante ore dormi a notte, di solito?', ['Meno di 5', '5-6', '6-7', '7-8', 'Più di 8']],
        'schermi' => ['Schermi nel tempo libero', 'Quante ore al giorno passi davanti a uno schermo fuori dal lavoro?', ['Meno di 1', '1-2', '3-4', '5-6', 'Più di 6']],
        'ferie' => ['Ferie', 'Quanti giorni di ferie hai fatto negli ultimi 12 mesi?', ['Nessuno', '1-5', '6-10', '11-20', 'Più di 20']],
    ];
}

/** Il catalogo pronto: chiave => ['k', 'nome', 'testo', 'opzioni' => [['k' => 'o1', 't' => ...], ...]]. */
function test_persona_catalogo(): array {
    static $c = null;
    if ($c !== null) return $c;
    $c = [];
    foreach (test_persona_catalogo_grezzo() as $k => [$nome, $testo, $ops]) {
        $c[$k] = ['k' => $k, 'nome' => $nome, 'testo' => $testo, 'opzioni' => array_map(fn($i, $t) => ['k' => 'o' . ($i + 1), 't' => $t], array_keys($ops), $ops)];
    }
    return $c;
}
