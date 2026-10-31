# Bozza per il manuale d'uso: i widget della 2.1

Bozza da portare in `cms/docs/manuale-uso.md` (un capitolo nuovo dopo quello dei widget per le attività, più due righe nel
capitolo dei form). Le schermate non ci sono ancora: vanno fatte con `tools/manuale-uso-schermate.js` sul sito di prova.
I passi sono scritti per chi non è tecnico: dove cliccare e cosa deve comparire.

Due esempi lungo tutto il capitolo: la **Trattoria da Anna** (ristorante, Bologna) e il **B&B Le Colline** (tre camere,
Langhe).

---

## Il menu del ristorante

Mostra il menu diviso in sezioni (Antipasti, Primi...), con prezzi, foto, allergeni e etichette come Vegano o Piccante. Google
lo legge come menu.

**Esempio: il menu della Trattoria da Anna.**

1. Apri la pagina Menu nell'editor (Pagine, passa sopra la pagina, **Editor visuale**).
2. Nella colonna a sinistra, scheda **Widget**, cerca "Menu" e trascina **Menu del ristorante** nella pagina. Compare un menu
   d'esempio con quattro piatti.
3. Nella sezione **Piatti** apri il primo piatto e scrivi:
   - Sezione: `Antipasti`
   - Nome del piatto: `Tagliere di salumi`
   - Descrizione: `Mortadella, prosciutto, crescentine`
   - Prezzo: `14` (sul sito diventa "14 €"; scrivi `9,50` per i centesimi)
   - Allergeni: `1, 7` oppure `glutine, latte`. L'aiuto sotto la casella ricorda i 14 numeri.
   - Le spunte Vegano, Vegetariano, Senza glutine, Piccante, Novità se servono.
4. **+ Aggiungi** per ogni piatto. I piatti con la stessa sezione stanno insieme; le sezioni escono nell'ordine in cui le
   scrivi la prima volta.
5. **Il menu del giorno.** Nella sezione **Sezioni con una nota o solo in certi giorni**, **+ Aggiungi**:
   - Sezione: `Menu del giorno` (lo stesso nome scritto nei piatti)
   - Nota: `Solo a pranzo`
   - Solo in questi giorni: `lun-ven`
   Sul sito il sabato e la domenica la sezione non c'è; negli altri giorni ha l'etichetta "Oggi". Nell'editor si vede sempre,
   con scritto "Solo lunedì, martedì...".
6. In **Allergeni** scegli la legenda: solo quelli presenti nel menu oppure tutti e 14. Correggi la nota se vuoi.
7. In **Aspetto**: due colonne al computer, una al telefono (si cambia con l'icona dello schermo accanto a Colonne).
8. **Pubblica**, poi **Vedi la pagina**.

Cosa deve comparire: le sezioni con una riga sotto il titolo, i piatti con i puntini fino al prezzo, i numeri degli allergeni in
piccoli cerchi, in fondo la legenda "Allergeni".

## La richiesta di prenotazione

Un modulo per chiedere un tavolo (data, ora, persone) o una camera (arrivo, partenza, ospiti). La richiesta arriva per email:
**non è una prenotazione confermata**, la confermi tu rispondendo. Il modulo lo dice anche a chi scrive. Se vuoi orari, posti e
conferme automatiche, c'è l'addon Prenotazioni (Impostazioni > Addon): con l'addon acceso, il widget te lo ricorda.

**Esempio: la Trattoria da Anna.**

1. Nell'editor trascina **Richiesta di prenotazione** (gruppo Dal sito).
2. Cosa si prenota: **Un tavolo**.
3. A chi arriva la richiesta: `prenotazioni@trattoriadaanna.it`. Vuota, va all'indirizzo degli avvisi del sito (Impostazioni,
   Posta in uscita). Fino a tre indirizzi, separati da una virgola.
4. Quante persone al massimo: `12`.
5. **Pubblica**. Prova tu: compila il modulo sul sito, spunta la privacy e "Sono una persona", **Invia la richiesta**. Deve
   comparire "Grazie, abbiamo ricevuto la tua richiesta...", e nella casella di posta l'email "Richiesta di prenotazione: ...".
   Con **Rispondi** scrivi direttamente al cliente.

**Esempio: il B&B Le Colline.** Come sopra, ma Cosa si prenota: **Un alloggio**, Titolo `Chiedi la disponibilità`,
Quante persone al massimo `6`. Il cliente sceglie arrivo e partenza (la partenza propone da sola il giorno dopo l'arrivo);
l'email dice anche quante notti.

Tutte le richieste restano anche nel pannello: **Newsletter e form > Invii dei form**, scheda **Richieste di prenotazione**.

## La galleria con filtri

Una galleria con dei pulsanti sopra ("Tutti / Matrimoni / Ritratti") che mostrano solo le foto di quel tipo.

**Esempio: le camere del B&B Le Colline.**

1. Trascina **Galleria con filtri** (gruppo Gallerie).
2. In **Immagini**, **+ Aggiungi** per ogni foto: scegli la foto, scrivi cosa si vede (per chi non vede), e in **Categorie**
   `Camera Rosa` oppure `Colazione`. Una foto può stare in due categorie: `Esterni, Colazione`.
3. I pulsanti nascono da soli dalle categorie. Il pulsante **Tutti** si può togliere o rinominare (sezione Filtri).
4. **Pubblica** e prova: un clic su "Colazione" lascia solo quelle foto; un clic su una foto la apre a tutto schermo e le
   frecce scorrono solo le foto del filtro. Funziona anche con la tastiera (Tab e Invio).

## Loghi e testo scorrevoli

Una striscia che scorre senza fine: i loghi dei clienti o dei fornitori, oppure una frase ("Aperti anche la domenica · Prenota
online").

1. Trascina **Loghi e testo scorrevoli** (gruppo Elementi). Parte con una frase d'esempio: cambiala in **Frase**.
2. Per i loghi: Cosa scorre **Una fila di loghi**, poi **+ Aggiungi** per ogni logo (immagine, nome, link facoltativo). Loghi
   della stessa altezza, su sfondo trasparente, vengono meglio.
3. **Movimento**: Durata di un giro (più alto, più lento: 30 secondi va bene), Direzione, Si ferma al passaggio del mouse.
4. **Aspetto**: "Loghi in grigio che si colorano al passaggio", altezza dei loghi.

Chi ha chiesto al telefono o al computer meno animazioni vede la striscia ferma. Per tutti c'è un piccolo pulsante per fermarla.

## Il contenuto esterno (Calendly, Spotify, Google Calendar, Typeform, Booking.com)

Mette nella pagina un calendario per gli appuntamenti, una playlist, un modulo... **solo dopo il clic** di chi visita, con la
frase sulla privacy. Prima del clic il servizio non riceve niente. Non si incolla codice: solo l'indirizzo.

**Esempio: gli appuntamenti con Calendly.**

1. Su Calendly copia il link della tua pagina (per esempio `https://calendly.com/trattoriadaanna/degustazione`).
2. Trascina **Contenuto esterno (con consenso)** (gruppo Interazione) e incolla il link in **Indirizzo da incollare**.
3. Nell'editor compare il riquadro "Contenuto di Calendly" con il pulsante (finto: nell'editor non si carica niente).
4. **Pubblica** e prova sul sito: **Mostra il contenuto di Calendly** carica il calendario.

Gli altri indirizzi che vanno bene:
- Spotify: il link di un brano, un album o una playlist (Condividi > Copia link).
- Google Calendar: Impostazioni del calendario > Integra il calendario > l'indirizzo che comincia con
  `https://calendar.google.com/calendar/embed?src=`, oppure l'ID del calendario.
- Typeform: il link del modulo (`https://...typeform.com/to/...`).
- Booking.com: il link della tua struttura. Booking non lascia mettere le sue pagine in altri siti: esce un riquadro con il
  pulsante "Vedi la disponibilità su Booking.com", che apre Booking in una scheda nuova.

Se l'indirizzo non è di questi servizi, nell'editor compare un messaggio e sul sito non esce niente.

## Le novità dei widget di prima

- **Mappa: "Come arrivare".** Nel widget Mappa, sezione Come arrivare: scegli Sotto la mappa (o Sopra), con quale app (Google
  Maps, Apple Mappe o tutte e due) e il testo. Sul telefono il pulsante apre le indicazioni stradali.
- **Video: "Parte da solo, senza audio" e "Si ripete".** Sezione Riproduzione. Con "parte da solo" il video si carica con la
  pagina, senza il clic: YouTube o Vimeo ricevono la visita (ricordalo nell'informativa privacy).
- **Tabella: grassetto, corsivo e link.** Nelle celle scrivi `**Taglio**` per il grassetto, `*su prenotazione*` per il
  corsivo, `[Vedi i colori](/colori/)` per un link. Niente HTML.
- **Ultimi articoli e Griglia di articoli:** la categoria si sceglie da una tendina (non si scrive più).

## Form: i campi Ora e File

In **Newsletter e form > Form**, apri un form, **+ Aggiungi un campo**, e nel tipo scegli:

- **Ora**: chi compila sceglie un orario (per esempio "Ora preferita per essere richiamato").
- **File da allegare**: chi compila allega un documento (un preventivo, una foto). Sotto il campo scegli quali file accetta
  (PDF, JPG, PNG, Word...) e il peso massimo. Il sito controlla il file vero, non solo il nome: un file pericoloso rinominato
  non passa.

**Esempio: "Lavora con noi" della Trattoria da Anna.** Campo File "Curriculum", solo PDF e Word, 5 MB.

Dove finiscono i file: in una cartella riservata, che dal sito non si apre. Li scarichi in **Invii dei form** (link "Scarica
curriculum.pdf" sotto i dati). Se sono piccoli (fino a 4 MB in tutto) arrivano anche allegati all'email di avviso. Eliminando
l'invio, o con **Cancella dati** di un iscritto, il file si cancella.

## Checklist

- [ ] Menu: ogni piatto ha sezione, nome e prezzo; gli allergeni sono scritti per ogni piatto che ne ha.
- [ ] Menu del giorno: i giorni sono giusti (prova a guardare la pagina in un giorno in cui non deve esserci).
- [ ] Richiesta di prenotazione: l'indirizzo email è giusto e la prova arriva davvero (guarda anche nello spam).
- [ ] Galleria con filtri: ogni foto ha la sua categoria e il testo alternativo.
- [ ] Scorrevole: i loghi hanno il nome; la velocità si legge senza fatica.
- [ ] Contenuto esterno: il pulsante carica il contenuto sul sito; l'informativa privacy nomina il servizio.
- [ ] Mappa: "Come arrivare" porta all'indirizzo giusto (provalo dal telefono).
- [ ] Form con File: prova a mandare un file; scaricalo da Invii dei form.
- [ ] Guarda tutto anche dal telefono.
