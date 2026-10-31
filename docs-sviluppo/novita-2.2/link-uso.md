# Bozza per il manuale d'uso: Scegli dal sito (2.2)

Bozza scritta il 31 ottobre 2026 per chi integra `cms/docs/manuale-uso.md`. Resta solo nel repository: non va nello zip né
sul sito. Le parti fra le righe orizzontali sono testo pronto da incollare; prima di ognuna c'è dove va e cosa sostituisce.
Le schermate nuove da fare con `tools/manuale-uso-schermate.js` sono segnate con **[schermata]** e sono elencate in fondo.

---

## 1. Capitolo 8 (L'editor visuale: com'è fatto), sezione nuova dopo quella sui widget

Dopo la sezione che spiega i controlli di un widget, prima della checklist del capitolo.

---

### Un link a una pagina del sito, senza scriverlo

Dove il pannello chiede un link (il pulsante dell'Apertura, un invito all'azione, un'immagine, una parola del testo, il
pulsante di un popup, una voce del menu...) accanto alla casella c'è il pulsante **Scegli dal sito**. Lo premi, scrivi due
lettere del titolo della pagina, la scegli: il link entra da solo nella casella. Non serve più ricordare come si chiama
l'indirizzo di una pagina, né copiarlo dal browser.

La casella resta scrivibile come prima: se il link porta a un altro sito (per esempio `https://www.instagram.com/...`), lo
scrivi a mano.

**[schermata 08-scegli-dal-sito: il controllo Link del pulsante dell'Apertura, con la casella e il pulsante Scegli dal sito;
pallini 1 e 2]**

1. La **casella** del link: dopo la scelta ci leggi l'indirizzo della pagina, per esempio `/contatti/`.
2. **Scegli dal sito** apre la finestra.

**[schermata 08-scegli-finestra: la finestra con "con" scritto nella ricerca, la tendina "Tutto il sito", tre o quattro
risultati fra cui una pagina in bozza; pallini 1-5]**

Nella finestra:

1. **Cerca nel titolo**: scrivi una parte del titolo (per esempio "cont" per Contatti). Non importano maiuscole e
   minuscole. L'elenco si aggiorna mentre scrivi.
2. **Tutto il sito**: la tendina restringe a un tipo: pagine, articoli, categorie, tag e, se hai acceso gli addon, eventi,
   prodotti e negozio, corsi, area riservata.
3. Ogni riga dice il **titolo**, il **tipo** (Pagina, Articolo, Evento...) e l'**indirizzo**. Fai clic su quella che vuoi:
   la finestra si chiude e il link è nella casella. La pagina già scelta ha il bordo colorato.
4. Il segno giallo **bozza** vuol dire che la pagina non è ancora pubblicata: puoi sceglierla lo stesso (per esempio se
   stai preparando le due pagine insieme), ma chi visita il sito la vedrà solo quando la pubblichi. **programmato** vuol
   dire che uscirà da sola in un giorno che hai già deciso.
5. **Oppure scrivi un indirizzo**: per un link a un altro sito, a un'email (`mailto:...`) o a un telefono (`tel:...`).

Con la tastiera: il cursore è già nella ricerca; **freccia giù** porta alla prima riga, le **frecce** si muovono fra le
righe, **Invio** sceglie, **Esc** chiude senza cambiare niente.

Al telefono la finestra prende tutto lo schermo: si sceglie con un tocco, **Chiudi** è in fondo.

**Nei testi.** Nel widget Testo (e nella newsletter) il pulsante con la catena nella barra degli strumenti apre la stessa
finestra:
- se hai selezionato una o più parole, quelle parole diventano il link;
- se non hai selezionato niente, nel testo entra il titolo della pagina scelta, già come link.
Per cambiare o togliere un link che c'è già, fai clic sul link nel testo: compare il fumetto di sempre con **Modifica** e
**Rimuovi**. I link dei testi si aprono in una scheda nuova del browser.

**Se la pagina cambia indirizzo.** Se dopo cambi l'indirizzo di una pagina già pubblicata (nella sua scheda, campo
Indirizzo), il CMS crea da solo un rimando dal vecchio indirizzo al nuovo: i pulsanti che avevi già fatto continuano a
portare alla pagina giusta. Per le pagine **in bozza** il rimando non si crea: se cambi l'indirizzo di una bozza che avevi
già scelto in un pulsante, rifai la scelta. Lo stesso vale per eventi, prodotti e corsi: se cambi il loro indirizzo,
rifai la scelta nei pulsanti che li aprono. Le voci del menu scelte dal sito, invece, seguono la pagina da sole.

---

## 2. Capitolo 11 (L'apertura della pagina), esempio nuovo prima della checklist

---

### Esempio: il pulsante "Prenota" che porta alla pagina Contatti

Vuoi che il pulsante dell'Apertura della home dica **Prenota** e porti alla pagina **Contatti**, senza scrivere niente a
mano.

**[schermata 11-prenota-1: l'Apertura selezionata, la scheda Contenuto con l'elenco Pulsanti e la voce aperta; pallini 1-3]**

1. Apri la home nell'editor: **Pagine**, passa con il mouse sopra **Home** e premi **Editor visuale**.
2. Fai doppio clic sull'Apertura (la parte in alto con il titolo grande). A sinistra si apre **Modifica**, scheda **Contenuto**.
3. Scendi fino a **Pulsanti** e fai clic sul primo pulsante per aprirlo. Nella casella **Testo** scrivi `Prenota`.

**[schermata 11-prenota-2: la finestra Scegli dal sito con "cont" nella ricerca e la riga Contatti evidenziata; pallini 4-6]**

4. Accanto alla casella **Link** premi **Scegli dal sito**. Si apre la finestra.
5. Scrivi `cont` nella ricerca. Compare la riga **Contatti**, con sotto *Pagina* e `/contatti/`.
6. Fai clic sulla riga. La finestra si chiude e nella casella Link c'è `/contatti/`.
7. Premi **Pubblica** in alto e conferma.
8. Apri il sito (pulsante **Vedi la pagina**, in alto) e fai clic su **Prenota**: si apre la pagina Contatti.

Se il sito è in una cartella (per esempio `https://www.miosito.it/sito/`), nella casella resta `/contatti/` e il pulsante
porta comunque a `https://www.miosito.it/sito/contatti/`: il CMS aggiunge la cartella da solo. Così il link resta giusto
anche se un giorno sposti il sito.

---

## 3. Gli altri capitoli: una riga dove c'è un campo link

Testo da aggiungere nei capitoli indicati, accanto alla descrizione del campo.

- **Capitolo 17 (Il menu)**, dove spiega "Link personalizzato": "Accanto alla casella Indirizzo c'è **Scegli dal sito**: se
  scegli una pagina, un articolo o una categoria, il testo della voce si riempie con il titolo, e la voce diventa di quel
  tipo (Pagina, Articolo, Categoria): se un giorno cambi l'indirizzo della pagina, il menu la segue da solo. Lo stesso
  pulsante c'è nell'Indirizzo di ogni voce del menu, quando la apri con la freccia." **[schermata 17-menu-scegli]**
- **Capitolo 18 (I form)**, campo "Link del pulsante Continua": "Con **Scegli dal sito** scegli la pagina in cui va chi ha
  compilato il form (per esempio i Servizi)."
- **Capitolo 19 (I popup)**: "Link del pulsante: **Scegli dal sito**. Nella casella *Indirizzi* (quando il popup va solo su
  alcune pagine) **Scegli dal sito** aggiunge una pagina per riga: premilo una volta per ogni pagina."
  **[schermata 19-popup-scegli]**
- **Capitolo 20 (La newsletter)**, il pulsante in fondo alla campagna e i link nel testo: "**Scegli dal sito** scrive
  l'indirizzo intero (con `https://` e il nome del sito), quello che serve nelle email."
- **Capitolo 21 (I social)**, Impostazioni, "Un link in più nei testi di WhatsApp": "**Scegli dal sito**, per esempio la
  pagina delle prenotazioni."
- **Capitolo 23 (SEO e AEO)**: "Nella scheda **Redirect** tutte e due le caselle hanno **Scegli dal sito**: per il nuovo
  indirizzo scegli la pagina giusta senza scriverla. Anche gli indirizzi da mandare a Google e IndexNow e quello dei dati per
  Google (scheda Schema) si scelgono così."
- **Capitolo 34 (Eventi)** e **33 (Prenotazioni)**, Impostazioni, "Pagina dell'informativa privacy": "**Scegli dal sito**
  e cerca `privacy`."
- **Capitolo 24 (Utenti e ruoli)**: "Anche il redattore ha **Scegli dal sito** nell'editor: vede tutte le pagine e gli
  articoli, anche quelli in bozza; degli eventi, dei prodotti e dei corsi vede solo quelli pubblicati."

---

## 4. Checklist (in fondo al capitolo 8)

---

- [ ] Per i link a pagine del mio sito uso **Scegli dal sito**, non scrivo l'indirizzo a mano.
- [ ] Se ho scelto una pagina con il segno **bozza**, l'ho pubblicata prima di pubblicare il pulsante.
- [ ] Dopo aver pubblicato, ho fatto clic sul pulsante sul sito e si è aperta la pagina giusta.
- [ ] Se ho cambiato l'indirizzo di una pagina in bozza, di un evento o di un prodotto, ho rifatto la scelta nei pulsanti
      che la aprono.
- [ ] Nelle email (newsletter) il link comincia con `https://`.

---

## 5. Problemi frequenti (in fondo al manuale)

---

**Nella finestra Scegli dal sito non trovo la pagina.** Scrivi una parte diversa del titolo (la ricerca guarda il titolo e
l'indirizzo). Se l'elenco dice "ce ne sono altri", scrivi qualche lettera in più. Una pagina nel cestino non c'è: prima
ripristinala. Eventi, prodotti e corsi ci sono solo se il loro addon è acceso.

**Il pulsante porta a una pagina "non trovata".** La pagina era in bozza quando l'hai scelta e poi le hai cambiato
indirizzo, oppure è stata cancellata: apri il pulsante nell'editor e premi di nuovo **Scegli dal sito**.

---

## 6. Schermate da fare

Con `tools/manuale-uso-schermate.js`, sul sito delle schermate (kit Attività), dopo tutti i capitoli (trappola delle pagine di
prova che prendono un numero).

| File | Pagina | Cosa deve mostrare |
|---|---|---|
| `08-scegli-dal-sito.jpg` | `editor.php` della home, Apertura selezionata, voce del primo pulsante aperta | la casella Link vuota con accanto **Scegli dal sito** (pallini 1 casella, 2 pulsante) |
| `08-scegli-finestra.jpg` | stessa pagina, finestra aperta, "con" nella ricerca | la ricerca, la tendina "Tutto il sito", righe con titolo, tipo e indirizzo, una riga con il segno **bozza** (serve una pagina in bozza con "con" nel titolo, per esempio "Condizioni di vendita"), "Oppure scrivi un indirizzo" chiuso (pallini 1-5) |
| `11-prenota-1.jpg` | `editor.php` della home | Apertura selezionata, scheda Contenuto, elenco Pulsanti con la prima voce aperta e `Prenota` nel Testo (pallini 1-3) |
| `11-prenota-2.jpg` | stessa pagina | la finestra con `cont` nella ricerca e la riga Contatti sotto il mouse (pallini 4-6) |
| `17-menu-scegli.jpg` | `menu.php` | "Link personalizzato" aperto, la casella Indirizzo con `/contatti/` e il Testo della voce riempito con "Contatti", il pulsante **Scegli dal sito** |
| `19-popup-scegli.jpg` | `popup.php?nuovo=1` | Link del pulsante con **Scegli dal sito**, e a destra la casella Indirizzi con due righe scelte dal sito |

Da rifare perché il pannello è cambiato (compare il pulsante accanto alla casella): le schermate di Form (Dopo l'invio),
Popup, Newsletter (campagna), Social (Impostazioni), SEO (Redirect) e del Menu che mostrano quelle caselle, e quelle
dell'editor che mostrano un controllo Link (Immagine, Invito all'azione, Pulsanti).
