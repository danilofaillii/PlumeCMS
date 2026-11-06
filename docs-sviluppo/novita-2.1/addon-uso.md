# Novità degli addon nella 2.1: bozze per il manuale d'uso

*Bozze dei capitoli (o dei paragrafi da aggiungere ai capitoli) del manuale d'uso, Parte seconda. Vanno rilette, messe
al loro posto nei capitoli 32-40 e illustrate con le schermate di `tools/manuale-uso-schermate.js` (da fare: le
schermate nuove non ci sono ancora, e il capitolo 39 dello script scrive ancora nell'area di testo che non c'è più).*

Le novità sono quattro:

1. Prenotazioni: la prenotazione presa al telefono o di persona, scritta da te nel pannello.
2. Prenotazioni: il file per il calendario arriva **dentro** l'email, e il calendario del cliente si aggiorna da solo.
3. Raccolta recensioni: l'email "Com'è andata?" anche dopo un evento.
4. Corsi online: il testo delle lezioni si scrive con l'editor visuale, come le pagine; l'ordine di moduli e lezioni si
   cambia trascinando.

---

## Prenotazioni: una prenotazione presa al telefono

*(Nel capitolo Prenotazioni e appuntamenti, dopo "Cosa vede il cliente".)*

Un cliente chiama il salone e chiede un taglio per giovedì. Lo scrivi tu, dal pannello, mentre sei al telefono: il sito
ti mostra gli orari liberi, gli stessi che vede chi prenota dal sito, e da quel momento quell'orario non lo offre più a
nessun altro.

### Esempio: Paolo chiama per giovedì

Paolo Verdi chiama alle 10: vuole un taglio da Anna giovedì mattina. Non ha l'email (o non vuole darla).

1. Nel menu a sinistra apri **Prenotazioni** e poi **Nuova prenotazione**. (La trovi anche come pulsante
   **+ Nuova prenotazione** in alto a destra in ogni pagina delle Prenotazioni, e nell'agenda di un giorno come
   **+ Nuova prenotazione in questo giorno**.)
2. Nel riquadro **1. Cosa, con chi, quando**:
   - in **Servizio** scegli `Taglio donna`;
   - in **Con chi** scegli `Anna` (oppure lascia `Chi è libero`);
   - in **Giorno** scegli giovedì.
   A ogni scelta la pagina si aggiorna da sola. Puoi anche premere **Giorno prima** e **Giorno dopo**.
3. Nel riquadro **2. L'ora** compaiono gli orari liberi come pulsanti: `9:00`, `9:30`, ... Premi `10:00`: il pulsante si
   colora. (Con `Chi è libero`, sotto ogni ora c'è il nome di chi la prenderà.)
4. Nel riquadro **3. Il cliente** scrivi **Nome e cognome** `Paolo Verdi` e **Telefono** `051 123456`. L'email la lasci
   vuota. In **Nota** puoi scrivere qualcosa che vedi solo tu, per esempio `Preferisce Anna, capelli lunghi`.
5. Premi **Salva la prenotazione**.
6. Si apre la scheda della prenotazione. In alto compare: "Prenotazione salvata: Paolo Verdi, giovedì ... alle 10:00 con
   Anna. Nessuna email al cliente." Nella scheda, accanto a "Prenotata il", c'è **scritta dal pannello**.

Se in un giorno non c'è più posto, la pagina lo dice e ti propone **il primo giorno libero**.

### Con l'email: il cliente riceve la conferma

Se il cliente ti dà l'email, scrivila nel campo **Email (facoltativa)** e lascia la spunta **Manda al cliente l'email di
conferma**. Riceve la stessa email di chi prenota dal sito, con il file per il calendario e il link per annullare o
spostare da solo; il giorno prima gli arriva il promemoria. Senza email non riceve niente: se sposti o annulli il suo
appuntamento, il pannello ti ricorda di **avvisarlo tu per telefono**.

### Un orario fuori dagli orari di lavoro

A volte vuoi fare un'eccezione: un cliente affezionato alle 8:15, prima dell'apertura, o un sabato in cui di solito sei
chiuso.

1. Nel riquadro **2. L'ora** premi l'ultimo pulsante, **Un altro orario**.
2. Compare un riquadro giallo: "un orario fuori dai posti liberi lo segni sotto la tua responsabilità".
3. Scrivi l'**Ora** (`8:15`) e scegli **Con chi** (qui è obbligatorio).
4. Compila il cliente e premi **Salva la prenotazione**. Il messaggio dice "È fuori dagli orari liberi".

Anche così il sito **non** ti lascia segnare due appuntamenti insieme per la stessa persona: se a quell'ora (o in parte di
quell'ora) Anna ha già un cliente, compare "Anna a quell'ora ha già un appuntamento" e niente viene salvato. I campi che
hai scritto restano: cambi l'ora e premi di nuovo **Salva la prenotazione**.

### Checklist

- [ ] Nuova prenotazione si apre dal menu o dal pulsante in alto.
- [ ] Gli orari sono quelli liberi (gli stessi del sito).
- [ ] Nome e telefono scritti; l'email solo se il cliente la dà.
- [ ] Dopo il salvataggio la scheda dice "scritta dal pannello".
- [ ] Senza email: ricordati di avvisare il cliente a voce se cambia qualcosa.

---

## Prenotazioni: il calendario del cliente si aggiorna da solo

*(Nel capitolo Prenotazioni, al posto del paragrafo sul file .ics.)*

Nelle email di **conferma**, **spostamento** e **annullamento** c'è un allegato: `appuntamento.ics` (o `annullamento.ics`).
Sul telefono e sul computer il cliente lo apre con un tocco e l'appuntamento entra nel suo calendario (Google Calendar,
Calendario di Apple, Outlook). Molti programmi di posta lo riconoscono da soli e mostrano il pulsante **Aggiungi al
calendario** direttamente nell'email.

Quando sposti l'appuntamento (o lo sposta il cliente dal suo link), la nuova email porta un allegato che il calendario
riconosce come **lo stesso appuntamento**: lo sposta, invece di aggiungerne un altro. Quando lo annulli, l'allegato lo
toglie dal calendario. Il link **Aggiungi al calendario** nell'email resta, per chi preferisce scaricarlo.

*Da verificare con le prime email vere: è provato leggendo le email arrivate a un server di prova, non con Gmail,
Outlook o il Calendario di Apple veri.*

---

## Raccolta recensioni: "Com'è andata?" anche dopo un evento

*(Nel capitolo Raccolta recensioni, dopo "Dopo un appuntamento".)*

Se usi anche l'addon **Eventi con iscrizione**, qualche giorno dopo una serata, un corso o un laboratorio gli iscritti
ricevono l'email "Com'è andata?" con le stelle, come i clienti delle prenotazioni.

### Esempio: la serata dei vini

Venerdì c'è la "Serata dei vini", con venti iscritti. Vuoi chiedere un parere solo a chi è venuto davvero.

1. Apri **Recensioni > Impostazioni**.
2. Nel riquadro **Quando chiedere** lascia la spunta **Chiedi da solo dopo ogni evento**.
3. In **Dopo un evento, a chi** scegli **Solo a chi segno Presente in Eventi > Iscritti**.
4. In **Dopo quanti giorni** lascia `2`: vale per gli appuntamenti e per gli eventi, e si conta dalla fine.
5. Premi **Salva**.
6. Durante la serata (o nei giorni dopo) apri **Eventi > Iscritti**, scegli la serata e premi **Presente** accanto a chi
   è venuto (**Assente** per chi non è venuto).
7. Due giorni dopo la fine della serata, a chi è segnato Presente parte l'email "Com'è andata?". In **Recensioni >
   Inviti** li vedi con **Dopo un evento** e il titolo dell'evento.

Le presenze puoi segnarle anche il giorno dopo: l'invito parte lo stesso. Se segni Assente qualcuno per sbaglio, il suo
invito si ferma; rimettilo Presente e riparte (sempre uno solo).

Se scegli **A tutti gli iscritti**, l'email arriva a tutti gli iscritti tranne chi segni Assente: comodo se le presenze
non le segni mai.

Chi cancella l'iscrizione non riceve l'invito. Se annulli l'evento o lo sposti più avanti, gli inviti non ancora partiti si
fermano; partono dopo la nuova data. Chi ha chiesto "Non scrivermi più" non riceve niente, come sempre.

### Checklist

- [ ] Recensioni > Impostazioni: "Chiedi da solo dopo ogni evento" acceso.
- [ ] Scelto "solo a chi segno Presente" oppure "a tutti gli iscritti".
- [ ] Le presenze segnate in Eventi > Iscritti.
- [ ] In Recensioni > Inviti gli inviti "Dopo un evento".

---

## Corsi online: il testo delle lezioni con l'editor visuale

*(Nel capitolo Corsi online, al posto di "Il testo della lezione".)*

Il testo di una lezione si scrive come una pagina del sito, con l'**editor visuale**: titoli, testo, immagini, elenchi,
pulsanti, video, schede, tabelle, e tutti gli altri widget. Non serve più l'HTML.

### Esempio: la lezione "La luce"

1. Apri **Corsi**, il corso "Fotografia per cominciare", e nel **Programma** premi **Modifica** accanto alla lezione
   "La luce" (o aggiungi una lezione nuova: si apre la sua pagina).
2. Nella pagina della lezione, a destra, metti il video e la durata come prima.
3. A sinistra, sotto il titolo, c'è il riquadro **Testo della lezione**. Premi **Scrivi il testo con l'editor visuale**.
   (Il pulsante salva anche titolo, video e materiali della pagina.)
4. Si apre l'editor. Nella tela vedi la lezione come la vedrà il membro: in alto il nome del corso, "lezione 1 di 3" e il
   titolo; sotto, un widget Testo con scritto "Scrivi qui il testo della lezione".
5. Fai doppio clic sul testo: a sinistra, nella scheda **Modifica**, si apre la casella del testo. Scrivi, per esempio:
   `La luce migliore arriva presto, appena dopo l'alba.`
6. Per aggiungere altro, trascina un widget dalla scheda **Widget** (per esempio **Titolo** o **Immagine**) nella tela.
7. Premi **Pubblica** in alto a destra. Compare "Pubblicata alle ...". (Con **Salva bozza** il membro non vede ancora
   niente di nuovo.)
8. Premi la freccia in alto a sinistra: torni alla pagina della lezione, che adesso dice **Pubblicato**.

Per vedere la lezione come un membro premi **Vedi la pagina** nell'editor (o **Vedi sul sito** nella pagina della
lezione). Nella finestra **Impostazioni** dell'editor c'è solo il titolo: indirizzo, video, materiali e modulo si cambiano
nella pagina della lezione. **Revisioni** tiene ogni salvataggio, come per le pagine.

### Le lezioni scritte prima (in HTML)

Le lezioni scritte con la versione 2.0.0 restano come sono: i membri le vedono uguali. Nella loro pagina, nel riquadro
**Testo della lezione**, vedi il testo di prima e il pulsante **Apri il testo nell'editor visuale**.

1. Premi **Apri il testo nell'editor visuale**.
2. Il testo entra nell'editor così com'è: in un widget **Testo** se è testo semplice (paragrafi, grassetto, elenchi,
   link); in un widget **HTML** se ha cose che il widget Testo non tiene (una tabella, un video incorporato).
3. È una **bozza**: finché non premi **Pubblica**, i membri vedono il testo di prima. La pagina della lezione lo dice:
   "Bozza non pubblicata. I membri vedono ancora il testo di prima."
4. Sistemalo con calma e premi **Pubblica**.

Niente si perde: il testo di prima resta salvato. Se vuoi tornare indietro, nell'editor apri **Revisioni** e premi
**Torna al contenuto della scheda**: la lezione torna al testo di prima.

### Checklist

- [ ] Il testo scritto nell'editor visuale e **Pubblica** premuto.
- [ ] La pagina della lezione dice "Pubblicato".
- [ ] Vista con **Vedi la pagina**.
- [ ] Le lezioni di prima aperte nell'editor, controllate, pubblicate.

---

## Corsi online: cambiare l'ordine trascinando

*(Nel capitolo Corsi online, al posto di "Riordinare con le frecce".)*

Nella pagina del corso, nel riquadro **Programma**, ogni modulo e ogni lezione ha a sinistra una **maniglia**: sei puntini
(&#10303;).

1. Metti il mouse (o il dito) sulla maniglia di una lezione, tieni premuto e trascina la lezione dove la vuoi: più su, più
   giù, anche dentro un altro modulo (anche vuoto).
2. Lascia andare. Sotto la spiegazione del Programma compare **Ordine salvato.** Non c'è niente da premere.
3. Allo stesso modo si spostano i moduli interi, dalla maniglia accanto al loro nome.

Le frecce **&uarr;** e **&darr;** restano: spostano di un posto alla volta, e si usano anche con la tastiera (tasto Tab
fino alla freccia, poi Invio). I membri seguono le lezioni nell'ordine del programma, modulo dopo modulo.

Se compare "L'ordine non è stato salvato", la pagina si ricarica da sola con l'ordine giusto: riprova.

### Checklist

- [ ] Lezioni e moduli trascinati dalla maniglia.
- [ ] "Ordine salvato." sotto la spiegazione.
- [ ] Ricaricando la pagina l'ordine è quello nuovo.

Portata nel manuale d'uso il 2026-10-31, capitoli 33 ("Il calendario del cliente si aggiorna da solo", "Una prenotazione presa al telefono"), 34 (cosa non c'è ancora), 35 ("Dopo un evento") e 39 ("Cambiare l'ordine trascinando", "Il testo con l'editor visuale").
