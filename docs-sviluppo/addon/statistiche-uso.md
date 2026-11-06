# Le statistiche delle visite (bozza del capitolo del manuale d'uso)

Le Statistiche ti dicono quante persone visitano il sito, quali pagine leggono, da dove arrivano (Google, Facebook, una
newsletter) e se usano il telefono o il computer. Le conta il sito stesso: **niente cookie, niente Google Analytics, niente
indirizzi IP salvati**. Per questo non serve il banner dei cookie.

**L'esempio di questo capitolo.** Anna, di «Bottega Rossi», ha mandato la newsletter d'autunno con un link al sito e ha
pubblicato un post su Facebook. Vuole sapere quante persone sono arrivate da ognuno, e quali pagine guardano.

## 1. Accendere le Statistiche

1. Nel menu a sinistra apri **Impostazioni > Addon**.
2. Sulla carta **Statistiche delle visite** premi **Accendi**.
3. Compare il messaggio verde «Statistiche delle visite è acceso». Nel menu, subito sotto **Dashboard**, c'è la voce
   **Statistiche** con due pagine: *Visite* e *Impostazioni e privacy*.

Da questo momento il sito conta. Le visite di prima non ci sono: i numeri partono da oggi.

**Tu non conti.** Le pagine che apri mentre sei entrato nel pannello (con lo stesso browser) non si contano. Per vedere
crescere i numeri, apri il sito dal telefono o da una finestra in incognito.

## 2. Leggere le statistiche

Apri **Statistiche > Visite**.

1. **Il periodo**, in alto: *Ultimi 7 giorni*, *Ultimi 30 giorni*, *Ultimi 90 giorni*, oppure scegli le date in *dal* e
   *al* e premi **Mostra**. Sotto c'è scritto il periodo e quello prima, con cui si confronta.
2. **I tre numeri grandi**:
   - *visite (pagine viste)*: quante pagine sono state aperte;
   - *visitatori*: quante persone diverse, giorno per giorno (chi torna il giorno dopo conta di nuovo);
   - *pagine per visitatore*: quante pagine guarda in media una persona.
   Sotto ognuno: il numero del periodo prima e la differenza (per esempio «+12%»).
3. **Giorno per giorno**: le barre blu sono le visite, la linea arancione i visitatori. Passa con il mouse su un giorno per
   leggere i numeri; *Vedi i numeri giorno per giorno* apre la tabella.
4. **Pagine più viste**: le pagine con più visite. *Entrati da qui*: per quante persone è stata la prima pagina del sito.
5. **Da dove arrivano**: il sito da cui la persona è arrivata (google.com, m.facebook.com...). *Diretto o sconosciuto*:
   indirizzo scritto a mano, un preferito, un'app che non lo dice (WhatsApp, molte app di posta).
6. **Campagne**: le visite arrivate da link con le etichette utm (vedi il punto 3).
7. **Dispositivi**: computer, telefono, tablet.

**Scarica il CSV** (in alto) salva tutti i numeri del periodo in un file che si apre con Excel o Fogli di Google.

In **Dashboard**, nel riquadro *Richieste e form*, trovi anche **visite oggi** e **visite, 7 giorni**.

## 3. Sapere quanto rende una newsletter o un post: i link con le etichette utm

Per distinguere le persone arrivate dalla newsletter, aggiungi al link tre etichette:

`https://www.bottegarossi.it/?utm_source=newsletter&utm_medium=email&utm_campaign=autunno`

- `utm_source`: da dove (newsletter, facebook, instagram, volantino);
- `utm_medium`: il mezzo (email, social, qr);
- `utm_campaign`: il nome della campagna (autunno, natale-2026).

Dopo l'invio, in **Statistiche > Visite**, nel riquadro **Campagne** compare la riga *newsletter / email / autunno* con le
persone arrivate. Usa lettere, numeri e trattini, senza spazi. Per un volantino con il codice QR: `utm_source=volantino&utm_medium=qr`.

## 4. Impostazioni e privacy

Apri **Statistiche > Impostazioni e privacy**.

1. **Tieni i numeri per**: 3 mesi, 6 mesi, 13 mesi (consigliato: confronti un anno con l'altro), 2 anni, 5 anni. Ogni notte
   i giorni più vecchi si cancellano da soli.
2. **Rispetta Do Not Track e Global Privacy Control**: se lo spunti, chi ha chiesto al browser di non essere tracciato non
   si conta. Non è obbligatorio (le statistiche non tracciano nessuno); spuntato, i numeri escono un po' più bassi.
3. Premi **Salva**.
4. **Testo per l'informativa privacy**, a destra: un paragrafo già scritto che spiega cosa si conta e cosa no. Clic sul
   riquadro per copiarlo, poi incollalo nella pagina **Privacy policy** del sito. Se cambi le impostazioni, il testo cambia:
   copialo di nuovo.

## Cosa si conta e cosa no

| Si conta | Non si conta |
|---|---|
| ogni pagina del sito aperta in un browser (anche le pagine del negozio) | robot dei motori di ricerca, programmi automatici |
| | anteprime dei link (WhatsApp, Facebook, Telegram...) |
| | pagine non trovate (errore 404) |
| | le anteprime del pannello, e chi è entrato nel pannello |
| | chi chiede «non tracciarmi», se lo hai scelto |

**Come fa a sapere quante persone diverse senza cookie?** Per ogni visita il sito calcola un codice anonimo dall'indirizzo
di rete e dal browser, mescolati con un valore casuale che cambia ogni giorno e poi si cancella. Il codice serve solo per
non contare due volte la stessa persona nello stesso giorno: non si può risalire a chi è, e il giorno dopo è un altro.
L'indirizzo IP non si salva.

## Checklist

- [ ] Statistiche accese in Impostazioni > Addon; la voce Statistiche è nel menu.
- [ ] Una visita di prova dal telefono (fuori dal pannello): il giorno dopo, o subito, in Statistiche > Visite c'è.
- [ ] Periodo di conservazione scelto (13 mesi va bene quasi sempre).
- [ ] Deciso se rispettare Do Not Track.
- [ ] Testo per l'informativa copiato nella pagina Privacy policy del sito.
- [ ] I link di newsletter, social e volantini hanno le etichette utm.
- [ ] Ogni mese: guarda le pagine più viste e da dove arrivano; scarica il CSV se vuoi tenere uno storico più lungo.
