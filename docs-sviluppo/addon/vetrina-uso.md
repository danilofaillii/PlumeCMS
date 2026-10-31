# Vendere dal sito con la Vetrina (bozza del capitolo del manuale d'uso)

La Vetrina è un piccolo negozio dentro il tuo sito. I clienti guardano i prodotti, li mettono nel carrello e ti mandano una
**richiesta d'ordine**. Tu la ricevi nel pannello e per email, con un numero d'ordine. Il cliente paga come preferisci tu:
bonifico, alla consegna o al ritiro, oppure con il tuo link di PayPal o di Stripe.

Il sito non incassa soldi da solo: quando vedi il pagamento arrivato, segni l'ordine come «Pagato». Così non servono
contratti con servizi di pagamento né chiavi segrete.

**L'esempio di questo capitolo.** Anna ha una bottega di ceramiche a Bologna, «Bottega Rossi». Vuole vendere tazze e
grembiuli dal sito: spedizione a 7 euro, gratis sopra i 60 euro, oppure ritiro in bottega. I clienti pagano con il bonifico
o con PayPal.

## 1. Accendere la Vetrina

1. Nel menu a sinistra apri **Impostazioni > Addon**.
2. Trova la carta **Vetrina prodotti** e premi **Accendi**.
3. In alto compare il messaggio verde «Vetrina prodotti è acceso». Nel menu a sinistra, dopo **Media**, c'è la voce nuova
   **Vetrina** con quattro pagine: Ordini, Prodotti, Categorie, Impostazioni del negozio.

Se un giorno la spegni, i prodotti e gli ordini restano: riaccendendola li ritrovi.

## 2. Le impostazioni del negozio

Apri **Vetrina > Impostazioni del negozio**.

1. **Il negozio sul sito.** Nel nome scrivi «Bottega». L'indirizzo resta `negozio`: il negozio si apre da
   `www.tuosito.it/negozio/`. Nella frase sotto il titolo: «Ceramiche fatte a mano a Bologna.»
2. **Consegna.** Lascia spuntate *Spedizione con costo fisso* e *Ritiro in negozio*. Costo della spedizione: `7,00`.
   Gratis da: `60,00`. Nelle due frasi scrivi i tempi del corriere e dove e quando si ritira.
3. **Pagamento.**
   - *Bonifico bancario*: spuntato. Scrivi l'IBAN (gli spazi si tolgono da soli) e a chi è intestato.
   - *Pagamento alla consegna o al ritiro*: spuntalo se lo accetti.
   - *Link di pagamento*: spuntalo e incolla il tuo link, per esempio `https://paypal.me/bottegarossi`. Con PayPal.me
     l'importo dell'ordine si aggiunge da solo. Con Stripe crea un Payment Link con «il cliente sceglie l'importo».
     Il link deve cominciare con `https://`: altrimenti il pannello lo rifiuta.
4. **Condizioni di vendita.** C'è già un testo di partenza: completalo con chi vende, tempi di consegna, diritto di recesso
   di 14 giorni, resi e garanzia. Una riga vuota fra un paragrafo e l'altro.
5. **I dati dell'attività** (a destra): nome, indirizzo, partita IVA, telefono, email. Escono in fondo alle email degli ordini.
6. **Gli ordini nuovi arrivano a**: l'indirizzo dove vuoi ricevere gli avvisi, per esempio `ordini@bottegarossi.it`.
7. Premi **Salva le impostazioni**. Compare «Impostazioni del negozio salvate».

Se qualcosa non va (un IBAN sbagliato, un link che non comincia con https) la pagina lo dice in rosso in alto e non salva:
correggi e salva di nuovo.

**Importante:** le email partono solo se il sito sa spedire posta. Controlla **Impostazioni > Configurazione email**.

## 3. Le categorie

Apri **Vetrina > Categorie**. Nel riquadro *Nuova categoria* scrivi «Tazze» e premi **Aggiungi la categoria**. Poi
«Grembiuli». Le categorie diventano i bottoni in cima al negozio. La *Posizione* decide l'ordine (i numeri più bassi prima).

## 4. I prodotti

Apri **Vetrina > Prodotti** e premi **+ Nuovo prodotto**.

**Una tazza scontata, con due foto e 3 pezzi.**

1. Nome: «Tazza smaltata». Frase breve: «Fatta a mano, 300 ml». Descrizione: materiali e come si lava.
2. Prezzo: `18,00`. Prezzo scontato: `15,00` (sul sito il 18 esce barrato). I prezzi si scrivono IVA inclusa.
3. **Foto**: premi *Aggiungi foto* e scegli due foto insieme. Dopo il salvataggio le vedi in fila: *Principale* sceglie quella
   della griglia e di Google, *Togli* la toglie al prossimo salvataggio. Le foto si alleggeriscono da sole e finiscono anche in Media.
4. A destra: *Sul sito* spuntato, *In evidenza* spuntato (la tazza comparirà nel widget Prodotti). Scorte: `3`.
5. Categorie: spunta *Tazze*.
6. Premi **Salva il prodotto**. Compare «Prodotto salvato».

**Un grembiule con le taglie.**

1. Nome «Grembiule da bottega», prezzo `25,00`.
2. Nel riquadro **Varianti**: prima caratteristica «Taglia». Nelle righe: `S` con scorte `1`; `M` con scorte `0`; `L` con
   prezzo `30,00` e scorte vuote (non contate). Il prezzo vuoto vale quello del prodotto.
3. Salva. Servono più righe? Salva: ne compaiono altre tre vuote.

Sul sito la taglia M risulta esaurita e non si può scegliere; scegliendo la L il prezzo diventa 30,00.

**Esaurito a mano.** Se un prodotto finisce e non conti le scorte, spunta *Esaurito*: resta visibile ma non si ordina.
Per toglierlo dal sito senza cancellarlo, togli la spunta *Sul sito*.

## 5. Il negozio sul sito, come lo vede un cliente

Apri `www.tuosito.it/negozio/` (oppure **Vedi** accanto a un prodotto).

1. In alto il titolo, la frase, il pulsante **Carrello** con il numero di pezzi e i bottoni delle categorie. Sotto, la
   tendina **Ordina** (prezzo più basso, più alto, ultimi arrivati).
2. Clic su un prodotto: foto (le miniature cambiano la foto grande), prezzo, disponibilità, variante, quantità e
   **Aggiungi al carrello**.
3. Nel **carrello** si cambiano le quantità (poi *Aggiorna il carrello*) o si toglie un prodotto. Il totale comprende la
   spedizione; sotto c'è scritto quanto manca alla spedizione gratis.
4. **Procedi con l'ordine**: dati, consegna (con il ritiro in negozio l'indirizzo sparisce e il totale cambia), pagamento,
   note, la spunta sulle condizioni di vendita e la privacy, la casella **Sono una persona** (aspetta il segno verde),
   **Invia la richiesta d'ordine**.
5. Compare la pagina «Grazie, abbiamo ricevuto la tua richiesta» con il numero (per esempio `2026-0001`) e come pagare:
   IBAN e causale «Ordine 2026-0001», oppure il pulsante **Paga** che apre PayPal con l'importo giusto.

Il cliente riceve subito un'email con il riepilogo e le istruzioni; tu ricevi l'avviso con i suoi dati. Se rispondi
all'avviso, la risposta va al cliente.

Il carrello non chiede di registrarsi. Il sito ricorda il carrello per 30 giorni con un cookie tecnico, solo da quando si
aggiunge un prodotto.

## 6. Gestire gli ordini

Apri **Vetrina > Ordini**. In alto i filtri per stato con il numero di ordini (*Nuovo (2)*), poi la ricerca per nome,
email o numero. In dashboard il riquadro *Richieste e form* mostra anche **ordini nuovi**.

Clic sul numero di un ordine:

- a sinistra prodotti, totale, IVA compresa, dati del cliente, indirizzo, metodo di pagamento e note;
- a destra **Cambia lo stato**: scegli lo stato, lascia spuntato *Manda un'email al cliente*, se vuoi scrivi un messaggio
  (per esempio «Spedito con BRT, numero 123456») e premi **Salva lo stato**.

| Stato | Quando | Cosa legge il cliente |
|---|---|---|
| Nuovo | appena arrivato | riepilogo e come pagare |
| Pagato | hai visto il bonifico o il pagamento PayPal/Stripe | «Abbiamo ricevuto il pagamento» |
| Spedito | il pacco è partito (o è pronto per il ritiro) | «Il tuo ordine è partito» / «è pronto: puoi venire a ritirarlo» |
| Consegnato | è arrivato | «Risulta consegnato» |
| Annullato | non si fa più | «Il tuo ordine è stato annullato» |

Annullare un ordine **rimette in magazzino** i pezzi. Sotto, nella *Storia*, ogni cambio con data e ora; *Rimanda il
riepilogo al cliente* rimanda la prima email.

## 7. I widget nell'editor

Nell'editor di una pagina, nel gruppo **Negozio**:

- **Prodotti**: una griglia di prodotti. In *Quali prodotti* scegli *In evidenza*, *Di una categoria*, *Gli ultimi arrivati*
  o *In offerta*; poi quanti e quante colonne. Sotto c'è il link «Vedi tutti i prodotti».
- **Carrello**: l'icona del carrello con il numero di pezzi. Mettila nell'header (**Editor > Header e footer**).

Per mettere il negozio nel menu: **Editor > Menu di navigazione**, aggiungi un link a `/negozio/`.

## Checklist

- [ ] Vetrina accesa in Impostazioni > Addon; la voce Vetrina è nel menu.
- [ ] Configurazione email a posto (Impostazioni > Configurazione email): fai un ordine di prova e controlla che le due email arrivino.
- [ ] Impostazioni del negozio: consegna, almeno un metodo di pagamento, IBAN o link controllati, indirizzo degli avvisi.
- [ ] Condizioni di vendita complete (recesso di 14 giorni, resi, tempi) e dati dell'attività con la partita IVA.
- [ ] Nella pagina Privacy del sito un paragrafo sugli ordini: quali dati chiedi (nome, email, telefono, indirizzo) e perché.
- [ ] Categorie create; ogni prodotto con prezzo, almeno una foto, categoria, scorte (o vuote se non le conti).
- [ ] Il negozio nel menu del sito; il widget Carrello nell'header, se vuoi.
- [ ] Un ordine di prova dal telefono: carrello, richiesta, email, pagina «Grazie»; poi annullalo (le scorte tornano).
- [ ] A ogni pagamento ricevuto: ordine su «Pagato»; a ogni spedizione: «Spedito» con il numero del corriere nel messaggio.
