# Prenotazioni e appuntamenti

*Bozza del capitolo del manuale d'uso (addon della 2.0.0).*

Con questo addon i tuoi clienti prenotano dal sito: scelgono il servizio, con chi (se siete in più di uno), il giorno e
l'ora fra quelli **davvero liberi**. Ricevono subito un'email di conferma con il file per il calendario del telefono e
un link personale per annullare o spostare da soli. Il giorno prima arriva il promemoria. Tu ricevi un'email a ogni
prenotazione e vedi tutto nell'agenda del pannello.

Il sito non offre mai lo stesso orario a due persone: anche se due clienti premono "Prenota" nello stesso istante,
uno solo lo ottiene, e all'altro il sito dice di sceglierne un altro.

## Esempio: il salone "Forbici d'Oro" a Bologna

Anna e Marco lavorano nel salone di via dell'Indipendenza. Anna lavora dal lunedì al sabato, 9-13 e 15-19; Marco dal
lunedì al venerdì, 10-18. Offrono "Taglio donna" (45 minuti, 35 €, con 15 minuti per riordinare dopo) e "Colore"
(90 minuti, 60 €, solo Anna). Vogliono che i clienti prenotino almeno 2 ore prima e possano annullare fino al giorno
prima.

## 1. Accendi l'addon

1. Nel pannello, a sinistra, apri **Impostazioni** e poi **Addon**.
2. Trova la carta **Prenotazioni e appuntamenti** e premi **Accendi**.
3. In alto compare: "«Prenotazioni e appuntamenti» è acceso: lo trovi nel menu a sinistra".
4. Nel menu a sinistra, sotto **Newsletter e form**, adesso c'è **Prenotazioni**, con cinque voci: Agenda, Prenotazioni,
   Servizi, Persone e orari, Impostazioni.

## 2. Chi lavora e quando: Persone e orari

1. Apri **Prenotazioni > Persone e orari** e premi **Aggiungi una persona**.
2. In **Nome** scrivi `Anna`.
3. Negli **Orari della settimana** trovi già proposti 9:00-13:00, 15:00-19:00 dal lunedì al venerdì, 9:00-13:00 il sabato,
   domenica Chiuso. Per Anna vanno bene così. Si scrivono come nel widget Orari di apertura: due turni separati da una
   virgola, `Chiuso` (o vuoto) per un giorno libero.
4. In **Ferie e giorni liberi** scrivi le date in cui Anna non c'è (Dal, Al, Motivo). Per un giorno solo lascia vuoto "Al".
5. Premi **Salva**. Compare "«Anna» salvata".
6. Ripeti per Marco: lunedì-venerdì `10:00-18:00`, sabato `Chiuso`.

Se lavori da solo basta una persona: il sito non chiederà "con chi".

## 3. Cosa si prenota: Servizi

1. Apri **Prenotazioni > Servizi** e premi **Aggiungi un servizio**.
2. **Nome**: `Taglio donna`. **Durata**: `45`. **Pausa dopo**: `15` (il cliente non la vede: serve a te per riordinare).
   **Prezzo**: `35 €` (facoltativo).
3. In **Chi lo fa** lascia spuntati Anna e Marco.
4. Premi **Salva**.
5. Aggiungi **Colore**: durata `90`, prezzo `60 €`, e in **Chi lo fa** togli la spunta a Marco.

Un servizio che per un periodo non vuoi far prenotare: aprilo e togli la spunta **Si può prenotare dal sito**.

## 4. Le regole: Impostazioni

Apri **Prenotazioni > Impostazioni**:

- **Anticipo minimo (ore)**: `2`. Alle 10 il primo orario prenotabile è a mezzogiorno.
- **Fino a quanti giorni avanti**: `60`.
- **Un orario ogni (minuti)**: `30`: il sito propone 9:00, 9:30, 10:00...
- **Annullare o spostare dal sito fino a (ore prima)**: `24`.
- **Confermo io ogni prenotazione a mano**: lasciala spenta se vuoi che le prenotazioni siano subito confermate. Accesa,
  il cliente riceve prima "richiesta ricevuta" e la conferma arriva quando premi Conferma.
- **Avvisi delle prenotazioni a**: l'email del salone, per esempio `salone@forbicidoro.it`.
- **Indirizzo dell'attività**: `Via dell'Indipendenza 10, Bologna` (esce nelle email e nel calendario del cliente).
- **Chiusure di tutta l'attività**: Natale, ferie di agosto. In quei giorni nessuno si prenota.

Premi **Salva**.

## 5. Il calendario sul sito: il widget Prenota

1. Apri la pagina dove vuoi le prenotazioni (per esempio crea la pagina "Prenota" in **Pagine**) con l'editor.
2. Nell'elenco dei widget, gruppo **Dal sito**, trascina **Prenota** nella pagina.
3. Nella scheda del widget puoi cambiare il titolo, il testo sopra il calendario e il testo del pulsante. In **Servizio**
   puoi fissarne uno solo (per una pagina "Prenota il colore"); in **Con chi** puoi fissare una persona.
4. Pubblica la pagina.

Sul sito il cliente vede: il menu dei servizi, "Con chi" (Chi è libero, Anna, Marco), le due settimane con i giorni
(quelli pieni o chiusi sono barrati), gli orari liberi del giorno scelto, poi nome, email, telefono, note, la spunta
della privacy e "Sono una persona". Dopo "Prenota" arriva alla sua pagina personale: "Prenotazione fatta".

## 6. Cosa riceve il cliente

- Subito: **Prenotazione confermata** con servizio, giorno, ora, indirizzo, il link **Aggiungi al calendario** (un file
  .ics che il telefono apre nel calendario) e il pulsante **Annulla o sposta**.
- Il giorno prima: **Promemoria**.
- Se sposta o annulla: un'email che lo conferma.

Dal pulsante **Annulla o sposta** il cliente apre la sua pagina: vede l'appuntamento, può **Spostare** (sceglie un altro
giorno e un'altra ora fra quelli liberi) o **Annullare** (il sito chiede "Vuoi davvero annullare?"). Lo può fare fino a
24 ore prima; dopo, la pagina gli dice di telefonare.

## 7. L'agenda e le prenotazioni nel pannello

- **Prenotazioni > Agenda**: la settimana, un giorno per colonna (al telefono uno sotto l'altro). Premi **Giorno** per
  vedere un giorno solo con orari, telefono e note. Con **Prima**, **Oggi**, **Dopo** ti sposti.
- **Prenotazioni > Prenotazioni**: l'elenco (Prossime, Da confermare, Passate, Annullate, Tutte) e la ricerca per nome,
  email o telefono. Un clic su una prenotazione apre la sua scheda:
  - **Conferma** (se le confermi a mano);
  - **Sposta**: nuovo giorno e nuova ora, il cliente riceve l'email;
  - **Annulla**: con un **Messaggio per il cliente** facoltativo ("Ci scusiamo: quel giorno siamo chiusi per un
    imprevisto"), che arriva nell'email;
  - **Non si è presentato**: dopo l'ora dell'appuntamento (se l'hai segnato per sbaglio: **Si è presentato**);
  - **Elimina i dati**: per un appuntamento passato o annullato, se il cliente chiede di cancellare i suoi dati.

Un appuntamento confermato, finita l'ora, diventa da solo **Svolto**.

## 8. Il cron

Promemoria e appuntamenti svolti li fa il giro frequente del cron (quello della newsletter, ogni 5 minuti). Controlla in
**Impostazioni > Log cron** che giri; per provarlo subito premi **Esegui adesso**.

## 9. Spegnere

**Impostazioni > Addon > Spegni**. Il widget e le voci del menu spariscono, il sito torna com'era. Prenotazioni,
servizi e persone restano nel database: se lo riaccendi, li ritrovi.

## Checklist

- [ ] Addon acceso (Impostazioni > Addon).
- [ ] Almeno una persona con gli orari giusti, e le sue ferie.
- [ ] Almeno un servizio, con durata e "Chi lo fa".
- [ ] Impostazioni: anticipo, limite per annullare, email degli avvisi, indirizzo, chiusure.
- [ ] Configurazione email a posto (Impostazioni > Configurazione email): senza, le email non partono.
- [ ] Il widget Prenota in una pagina pubblicata, e la pagina nel menu del sito.
- [ ] Una prenotazione di prova fatta da te, con la tua email: arriva la conferma? Il file del calendario si apre?
- [ ] La prenotazione di prova si vede nell'Agenda; annullala dal link dell'email.
- [ ] Il cron gira (Log cron).
