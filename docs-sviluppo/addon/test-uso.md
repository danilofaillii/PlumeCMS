# Test e questionari

*Il capitolo del manuale d'uso dell'addon (2.22.0, aggiornato alla 2.24.0). Il testo che va nel PDF è il capitolo 40 di `cms/docs/manuale-uso.md`: se cambia uno, si cambia anche l'altro.*

**A cosa serve.** Fare un test sul sito: chi lo fa risponde alle domande (una per schermata, oppure divise nelle pagine che
scegli tu) e vede subito il risultato, con
un testo scritto da te e, se vuoi, un pulsante (per esempio "Prenota una chiamata"). Tre tipi:

- **A punteggio**: ogni risposta vale dei punti, il totale (da 0 a 100) cade in una **fascia** (Basso, Medio, Alto...);
- **Ad aree**: le domande sono divise in aree (Energia, Sonno, Relazioni...), ogni area ha il suo punteggio e le sue
  fasce, e il risultato ha il **grafico a barre** delle aree;
- **Che tipo sei** (profili): ogni risposta dà punti a uno o più profili, e vince quello con più punti ("Sei un
  Esploratore").

Chi ha fatto il test può farsi mandare il risultato per email e, a parte, iscriversi alla newsletter. Le risposte restano
**anonime**: il sito conserva solo il risultato, i punteggi e il giorno, mai il nome o l'indirizzo IP.

Dalla 2.23.0: alla fine del test ci possono essere delle **domande sulla persona** (età, occupazione, settore...), che non
danno punti e servono alle **statistiche per gruppi**; ogni risultato ha due testi, la **risposta breve** (a schermo) e la
**risposta completa** (per email); con **Il risultato completo solo per email** il test finisce con la risposta breve e
"Lasciami la tua email e ti mando il risultato completo"; chi si iscrive alla newsletter riceve come **tag** le sue
risposte sulla persona e l'esito del test.

Dalla 2.24.0: scegli **come si mostrano le domande** (una per pagina, a pagine scelte da te con un titolo per pagina, tutte
in una pagina), e i cinque passi del pannello sono fatti di **carte** che si aprono in una finestra, con accanto un
**telefono** che mostra dal vivo come lo vede chi fa il test.

**Esempio.** Lo studio "Respiro" di Bologna, che insegna a gestire lo stress, vuole un test "Quanto riposi davvero?": otto
domande, tre fasce, e a chi esce "Riposo in rosso" un pulsante per prenotare una chiamata. Chi lascia l'email e si
iscrive alla newsletter riceve, nei giorni dopo, tre email pensate per il suo risultato.

Accendi l'addon (capitolo 31): nel menu compare **Test**, con **Test** e **Statistiche**. Solo l'amministratore lo vede.

### Creare il test

Apri **Test > Test** e premi **+ Nuovo test**.

![Nuovo test: il tipo o un modello pronto](immagini/manuale-uso/40-nuovo.jpg)

1. Scrivi il **Nome del test**: "Quanto riposi davvero?".
2. Premi il tipo: **A punteggio**. Oppure, sotto, parti da uno dei **tre modelli pronti** (uno per tipo, con domande e
   testi d'esempio da cambiare): per l'esempio va bene "Quanto riposi davvero?".
3. Il test nasce **in bozza**: sul sito non si vede finché non lo pubblichi. Si apre la procedura in **cinque passi**,
   con la barra dei passi in alto: ogni passo si salva con **Salva** oppure con **Salva e vai avanti**.

### Come sono fatti i passi

Dalla 2.24.0 tutti e cinque i passi, per tutti e tre i tipi di test, hanno la stessa forma:

- **a sinistra le carte**: ogni cosa da sistemare è una carta compatta, con dei **bollini** che dicono com'è (per esempio
  "Scala", "Invertita", "Condizionale", "Breve: manca"). **Tocca una carta** e si apre la sua **finestra**, con i campi
  raggruppati. Mentre scrivi, la carta e il telefono si aggiornano. **Fatto** (o **Chiudi**, o il tasto Esc) chiude la
  finestra; le modifiche restano lì, e si salvano con **Salva** in fondo alla pagina;
- **a destra il telefono**: l'anteprima dal vivo di come lo vede chi fa il test, per la parte che quel passo cambia.

Al telefono (o in una finestra stretta) il telefono dell'anteprima non sta accanto alle carte: premi **Anteprima: come lo
vede chi fa il test** e si apre a schermo intero; **Chiudi l'anteprima** (o Esc) torna alle carte.

![Al telefono: l'anteprima a schermo intero](immagini/manuale-uso/40-anteprima.jpg)

### Passo 1: Presentazione

Le carte: **Nome e indirizzo**, **Tipo e durata**, **Presentazione con l'editor visuale**, **Testo breve per Google e i
social**. Il telefono mostra la pagina del test: il titolo, "8 domande, circa 2 minuti", la presentazione e **Inizia il
test**.

1. Tocca **Nome e indirizzo**: **Nome del test** e **Indirizzo della pagina** (la pagina sarà
   `/fai-il-test/quanto-riposi-davvero/`).
2. Tocca **Tipo e durata**: la **Durata in minuti** lasciala a 0 e la calcola il sito (circa 12 secondi a domanda).
3. Nella carta **Presentazione con l'editor visuale** premi **Componi la presentazione con l'editor visuale**: la
   presentazione è il testo fra il titolo e il pulsante **Inizia il test**, e si scrive con l'editor visuale come una pagina
   (capitolo 9): testo, immagini, un video. Premi **Pubblica** nell'editor e torna alla scheda con la freccia in alto.
4. **Testo breve per Google e i social**: va nella descrizione della pagina per Google e per i social.

### Passo 2: Domande e pagine

![Il passo 2: la lavagna delle pagine e il telefono](immagini/manuale-uso/40-domande.jpg)

In alto scegli **Come si mostrano le domande**:

- **Una per pagina**: una domanda per schermata (com'era sempre; i test fatti prima della 2.24.0 restano così);
- **A pagine, le scegli tu**: decidi tu quante pagine e quali domande in ognuna;
- **Tutte in una pagina**: tutte le domande in una schermata sola.

Sotto c'è la **lavagna**: ogni **colonna è una pagina**, ogni **carta è una domanda**. L'ultima colonna, tratteggiata e
sempre in fondo, è la pagina **su di te**, con le domande sulla persona. A destra il telefono mostra la pagina che scegli:
tocca il nome di una colonna (o i numeri sotto il telefono) per vederla.

1. Premi **+ Domanda**: si apre la scheda della domanda. Scrivi il **Testo della domanda**: "Ti svegli stanco anche dopo
   aver dormito le tue ore?". Sotto, se serve, un **Aiuto** ("Pensa alle ultime due settimane").
2. **Tipo di risposta**:
   - **Scala**: Mai, Raramente, A volte, Spesso, Sempre, che valgono da 0 a 4 (le parole e i punti li cambi con il pulsante
     **Le parole della scala**, sopra la lavagna);
   - **Scelta singola**: le risposte le scrivi tu, ognuna con i suoi **Punti**;
   - **Sì o no**: due risposte, con i punti che decidi;
   - **Cursore da 0 a 10**: i punti sono il numero scelto.
3. **Invertita**: per le domande "al contrario". In "Durante il giorno ti senti pieno di energia?" la risposta buona è
   **Sempre**: spunta Invertita e Sempre vale 0, Mai vale 4.
4. **Peso**: quante volte conta la domanda. 2 = il doppio delle altre.
5. **Obbligatoria**: chi fa il test non va avanti senza rispondere.
6. **Mostra questa domanda**: **Sempre**, oppure **Solo se la domanda N ha risposta...**, e spunti una o più risposte.
   Esempio: "Quante volte a settimana bevi caffè dopo le quattro?" compare solo a chi alla domanda "Bevi caffè dopo le
   quattro?" ha risposto **Sì**. Una domanda condizionale può dipendere solo da una domanda che viene **prima**.
7. **Fatto** chiude la scheda. Per riaprirla tocca la carta (con la tastiera: Tab fino alla carta e Invio), oppure tocca la
   domanda nel telefono. **Togli la domanda** la toglie.

![La scheda di una domanda, con la tendina Pagina](immagini/manuale-uso/40-scheda.jpg)

**Le pagine** (con **A pagine, le scegli tu**):

1. **Trascina una carta** in un'altra colonna per spostarla in quella pagina, o sopra o sotto un'altra carta per cambiare
   l'ordine.
2. Trascinala nel riquadro tratteggiato **+ Pagina nuova** e diventa la prima domanda di una pagina nuova. Il riquadro,
   toccato, fa una pagina vuota.
3. Senza trascinare: nella scheda della domanda c'è la tendina **Pagina** (con **Una pagina nuova** in fondo), e i pulsanti
   **Sposta prima** e **Sposta dopo**. Vanno anche con la tastiera.
4. **Titolo** (in testa alla colonna): un titolo facoltativo per la pagina, per esempio "Come inizi la giornata". Chi fa il
   test lo legge sopra le domande di quella pagina. Una pagina vuota si toglie dalla stessa finestra (**Togli la pagina**);
   una pagina vuota che resta, sul sito si salta.
5. L'ordine delle pagine è l'ordine delle domande: se sposti una domanda condizionale prima di quella da cui dipende, la
   condizione si toglie e la pagina lo dice.
6. **Ordine casuale**: ogni persona vede le domande in un ordine diverso, mescolate dentro la loro pagina; una condizionale
   resta dopo la sua.

Con **Una per pagina** e **Tutte in una pagina** le colonne le fa il sito (una per domanda, oppure una sola): per spostare
le domande fra le pagine e dare i titoli scegli **A pagine, le scegli tu**. L'ordine lo cambi con **Sposta prima** e
**Sposta dopo** nella scheda.

In un test **ad aree** sopra la lavagna c'è il pulsante **Aree**: aggiungile (+ **Aggiungi un'area**), e nella scheda di
ogni domanda scegli la sua **Area** (il bollino della carta la dice). In un test **a profili** c'è il pulsante **Profili**:
aggiungili, e in ogni risposta scrivi i **Punti** che dà a ogni profilo (per esempio "Cammino senza meta": 2 punti a
Esploratore, 0 agli altri). **L'ordine dei profili conta**: se due profili hanno gli stessi punti vince quello più in alto.

#### Le domande sulla persona

Nella colonna **su di te** premi **Scegli le domande sulla persona**: un catalogo pronto di domande su chi fa il test. Non
danno punti e compaiono **alla fine del test, nell'ultima pagina, prima del risultato**.

![Una domanda sulla persona sul sito, facoltativa](immagini/manuale-uso/40-persona.jpg)

Nel catalogo: fascia d'età (18-24, 25-34, 35-44, 45-54, 55-64, 65 e più), genere, occupazione, settore (circa settanta
settori, dalle sezioni ATECO in parole semplici), stato civile, figli, con chi vive, reddito annuo lordo, titolo di studio,
regione (le 20 regioni più Estero), grandezza del comune, persone nell'azienda, anni di esperienza, ruolo, contratto,
modalità di lavoro (in sede, da casa, misto), ore di lavoro a settimana, turni, viaggio casa-lavoro, se si occupa di un
familiare, attività fisica, ore di sonno, schermi nel tempo libero, giorni di ferie.

1. Scegli se le domande sulla persona sono **Facoltative** (con la risposta «Preferisco non rispondere», che si aggiunge da
   sola) oppure **Obbligatorie**. Vale per tutte le domande sulla persona di questo test.
2. Apri le domande che ti servono e spunta **Usa questa domanda**. Per l'esempio dello studio "Respiro": Età, Occupazione,
   Ore di lavoro, Sonno. Nella colonna **su di te** compare una carta per ognuna.
3. **Ordine**: il numero più basso viene prima.
4. **Nome breve**: è il nome della domanda nelle statistiche e nei tag ("Età: 35-44"). **Testo della domanda**: quello
   che legge chi fa il test, lo cambi come vuoi.
5. **Risposte, una per riga**: le cambi, le togli, ne aggiungi. Attenzione: una risposta con il testo cambiato conta come
   una risposta nuova nelle statistiche.
6. **Fatto**, poi **Salva**.

Le risposte sulla persona si salvano con il risultato, anonime come il resto (solo il giorno, niente IP). Diventano tag del
contatto solo per chi si iscrive alla newsletter (passo 5).

Premi **Salva e vai avanti**.

### Passo 3: Punteggi e fasce

![Il passo 3: le fasce in fila, la barra da 0 a 100 e il telefono](immagini/manuale-uso/40-fasce.jpg)

Il punteggio va da 0 a 100: è la percentuale dei punti possibili, contando **solo le domande che la persona ha visto e
a cui ha risposto** (le condizionali che non le sono comparse non contano).

1. **Fasce del totale**: una carta per fascia, in fila. Di partenza Basso (da 0 a 33), Medio (da 34 a 66), Alto (da 67 a
   100). Tocca una fascia e nella sua finestra cambia **Nome della fascia** ("Riposo buono", "Riposo da curare", "Riposo in
   rosso"), **Da** e **A**. Il telefono mostra il risultato di quella fascia, con il suo testo (che si scrive nel passo 4).
2. Le fasce devono coprire **da 0 a 100 senza buchi**: dopo una fascia che finisce a 33 la successiva comincia a 34. Sotto le
   carte c'è la **barra da 0 a 100**: ogni fascia è un tratto, un buco è a righe. Una riga verde dice "Le fasce coprono da 0 a
   100 senza buchi"; se c'è un buco o una sovrapposizione la riga diventa gialla e dice dove ("Fra Basso e Medio c'è un
   buco: da 34 a 35"). **Dividi da 0 a 100 in parti uguali** sistema i numeri da solo; **+ Aggiungi una fascia** ne aggiunge
   una; **Togli la fascia** (nella finestra) la toglie.
3. In un test ad aree, sotto ci sono le fasce di **ogni area**, nello stesso modo.
4. In un test a profili non ci sono fasce: tocca un profilo e il telefono mostra il suo risultato.
5. Il **Simulatore** calcola il risultato con tutte le risposte al minimo, a metà e al massimo, e con 200 prove a caso:
   se una fascia non esce mai lo dice ("La fascia Alto non viene mai raggiunta"). È calcolato sul test **salvato**: dopo
   un cambio premi **Salva** e guarda di nuovo. **Rifai con 1000 prove** ne fa di più.

### Passo 4: Risultati

![Il passo 4: una carta per fascia, il telefono con «A schermo» ed «Email»](immagini/manuale-uso/40-risultati.jpg)

1. Una carta per ogni fascia (o profilo); i bollini dicono se mancano i testi ("Breve: manca"). Tocca la carta e scrivi i
   due testi. La **Risposta breve** è quella che si legge a schermo alla fine del test (due o tre righe: "Il tuo riposo è in
   rosso: dormi, ma non recuperi"). La **Risposta completa** arriva per email: il testo intero, con cosa fare. Si scrivono
   come nel widget Testo: grassetto, corsivo, elenchi, link. Se la breve resta vuota, a schermo esce la completa (i test
   fatti prima della 2.23.0 hanno un testo solo, che è la completa: restano come erano).
2. **Testo del pulsante** e **Link del pulsante**, facoltativi: "Prenota una chiamata", e il link con **Scegli dal sito**
   (cerca `contatti`).
3. Il telefono mostra il risultato della carta toccata, come lo vede chi fa il test; sopra il telefono **A schermo** e
   **Email**: con **Email** vedi l'email del risultato completo.
4. In un test ad aree ci sono le carte di ogni area (i loro testi escono sotto il grafico) e le **Regole sul risultato**:
   una regola vince sulle fasce. Esempio: **+ Aggiungi una regola**, nome "Poca energia e poco tempo", **Vale quando tutte le
   condizioni sono vere (E)**, condizioni "Area Energia è in fascia Bassa" e "Area Tempo per te è in fascia Poco", e i suoi
   testi. Le regole si guardano dalla prima: vale la prima che si avvera (**Guarda prima** e **Guarda dopo** cambiano
   l'ordine). Con **almeno una condizione (O)** basta una.
5. **Avvertenza** (carta in fondo): di partenza "Questo test non è una diagnosi e non sostituisce il parere di un
   professionista.". Esce sotto le domande e sotto il risultato.

### Passo 5: Dopo il test, e Pubblica

![Il passo 5: le carte e la fine del test nel telefono](immagini/manuale-uso/40-dopo.jpg)

Le carte: **Email del risultato**, **Newsletter**, **Automazioni**, **Privacy**. Il telefono mostra la fine del test: il
riquadro dell'email, il consenso e la casella della newsletter.

1. **Email del risultato**:
   - **Il risultato completo solo per email** (acceso di partenza nei test nuovi): il test finisce con la risposta breve e
     sotto "Lasciami la tua email e ti mando il risultato completo". Spento: a schermo la risposta breve e sotto la completa;
   - **Offri il risultato per email**: sotto il risultato compare "Vuoi ricevere il risultato per email?", con l'email e
     la casella del **consenso**, che parte vuota. Un test sulla salute tratta un dato particolare: per questo il consenso
     è una casella sua (il testo lo scrivi in **Testo del consenso**). Con il risultato completo solo per email il riquadro
     c'è sempre. L'email parte subito e contiene il punteggio, la risposta completa (della fascia, della regola o del
     profilo), quella di ogni area con il grafico a barre, il pulsante e l'avvertenza.
2. **Newsletter**: scegli la lista (per esempio "Lettori del test"). Compare una seconda casella, separata, per iscriversi:
   arriva l'email per confermare, come nei form (doppia conferma). Quando conferma, il contatto entra nella lista e riceve
   come **tag** le sue risposte sulla persona ("Età: 35-44", "Occupazione: Manager"; "Preferisco non rispondere" non
   diventa un tag) e l'esito ("Test Quanto riposi davvero?: Riposo in rosso"). Chi lascia l'email **senza** spuntare la
   newsletter riceve solo il risultato: non diventa un contatto e non riceve tag.
3. **Automazioni**: la carta dice quante automazioni usano questo test; la finestra le elenca.
4. **Privacy**: riassume cosa il sito conserva.
5. Premi **Salva**, poi **Pubblica il test** (riquadro **Pubblica**, sotto). Se qualcosa manca (le fasce hanno un buco,
   un'area senza domande, un profilo che nessuna risposta premia), il riquadro lo elenca e il pulsante resta spento finché
   non lo sistemi.

### Sul sito

![Il test sul sito: una domanda per schermata](immagini/manuale-uso/40-domanda.jpg)

1. La pagina del test ha il titolo, "8 domande, circa 2 minuti", la presentazione e **Inizia il test**.
2. Con **Una per pagina** le domande arrivano **una per schermata**, con la barra di avanzamento, **Indietro** e
   **Avanti**. Va anche con la tastiera: le frecce scelgono la risposta, Invio va avanti.
3. Con **A pagine** si va **pagina per pagina**: in alto "Pagina 2 di 5", sopra le domande il titolo della pagina. Avanti
   senza le risposte obbligatorie segna in rosso quelle che mancano. Una condizionale compare appena si dà la risposta da
   cui dipende, anche nella stessa pagina; una pagina che non ha niente da mostrare si salta. L'ultima pagina ha le domande
   sulla persona e **Vedi il risultato**. Con **Tutte in una pagina** le domande stanno in una schermata, e le domande sulla
   persona nella seconda.
4. Se chi fa il test chiude la pagina a metà, al ritorno trova **Riprendi da dove eri** (le risposte restano solo nel
   suo browser).
5. Senza JavaScript il test funziona lo stesso: tutte le domande in una pagina, con i titoli delle pagine, e le
   condizionali dicono "Rispondi solo se alla domanda 4 hai risposto «Sì»".

![Il test a pagine sul sito: il titolo della pagina e le sue domande](immagini/manuale-uso/40-pagine-sito.jpg)

![Il risultato, con il grafico delle aree](immagini/manuale-uso/40-risultato.jpg)

Il risultato ha il nome della fascia (o della regola, o del profilo), il punteggio su 100, il tuo testo, il grafico delle
aree (o dei profili), il pulsante e l'avvertenza. Il calcolo lo fa sempre il sito, mai il browser.

**Condividi il risultato**: un link con solo l'esito, firmato. Chi lo apre vede lo stesso risultato; se qualcuno cambia
anche una lettera del link, il sito risponde "Questo link non funziona". Il riquadro dell'email non c'è nel link
condiviso: si chiede entro due ore dal test, dalla pagina del risultato.

Per portare le persone al test usa il widget **Riquadro in evidenza** (gruppo Dal sito): con l'addon acceso ha la
tendina **Oppure porta a un test**. Oppure un link qualunque con **Scegli dal sito**: i test ci sono.

### Le automazioni dopo il test

In **Newsletter e form > Automazioni** crea un'automazione e, fra gli **Inneschi**, scegli **Completa un test**:

1. **Quale**: il test ("Quanto riposi davvero?").
2. **Con quale risultato**: Qualunque, oppure una fascia ("Totale: Riposo in rosso"), una fascia di un'area, un profilo,
   una regola.
3. Poi i passi come in ogni automazione (capitolo 20): email, attese, divisioni.

Parte per chi si iscrive dalla pagina del risultato, **dopo** aver confermato l'iscrizione. Dalla 2.23.0 alla conferma
partono anche le automazioni con l'innesco **Riceve un tag** (capitolo 20, "I tag dei contatti"): per esempio un'email per
chi ha il tag "Occupazione: Manager", qualunque test abbia fatto. Così a chi è "in rosso"
mandi tre email sul riposo, a chi va bene una sola di saluto. Nella scheda del contatto, in **Cronologia**, c'è "Ha
completato il test «Quanto riposi davvero?»: Riposo in rosso (73 su 100)".

### Le statistiche

![Le statistiche di un test](immagini/manuale-uso/40-statistiche.jpg)

Apri **Test > Statistiche** e scegli il test e il periodo:

- **iniziati**, **completati** e quanti **arrivano alla fine**;
- **Risultati**: quante persone per fascia o profilo, e quante per regola;
- **Media per area** (o dei punti per profilo);
- **Domanda per domanda**: quanti **si sono fermati** a quella domanda, e quante volte è stata data ogni risposta;
- **Per gruppi** (con le domande sulla persona): una tabella per ogni domanda. Per ogni risposta (per esempio "25-34")
  quante persone l'hanno data, in che percentuale sono uscite in ogni fascia (o profilo) e il punteggio medio, del totale
  e di ogni area. Un gruppo con meno di 5 persone non mostra niente: si legge **meno di 5**, perché con così poche persone
  qualcuno si potrebbe riconoscere. **Non mostrato** vuol dire che si nasconde anche un gruppo più grande, altrimenti dal
  totale si ricaverebbero i numeri dei gruppi piccoli. Questa parte conta sempre tutti i risultati del test, qualunque
  periodo scegli. Le risposte che nessuno ha dato sono elencate sotto la tabella.
- **Scarica il CSV**: gli stessi numeri, gruppi compresi (con la stessa regola dei 5), da aprire con Excel. Sono tutti numeri
  aggregati, senza persone.

![Le statistiche per gruppi](immagini/manuale-uso/40-gruppi.jpg)

**Cosa non c'è ancora.** Domande con più risposte da spuntare, immagini nelle risposte, il pagamento per fare il test. Il risultato per email senza JavaScript (il riquadro
dell'email usa la casella "Sono una persona", che vuole JavaScript). Chi si fa mandare il risultato **senza** iscriversi
non entra nei Contatti: il suo indirizzo serve solo a mandare l'email.

**Checklist**

- [ ] L'addon è acceso; se offri il risultato per email, la posta del sito funziona (capitolo 25).
- [ ] Le domande hanno il tipo giusto; le invertite e i pesi sono quelli voluti; le condizionali dipendono da una
  domanda che viene prima.
- [ ] Ho scelto come si mostrano le domande; con «A pagine» ogni pagina ha le domande giuste e, se serve, il suo titolo
  (nel telefono dell'anteprima le ho viste pagina per pagina).
- [ ] Le fasce coprono da 0 a 100 senza buchi (riga verde nel passo 3), anche quelle di ogni area.
- [ ] Il simulatore non dice "non viene mai raggiunta".
- [ ] Ogni fascia, area, profilo e regola ha il suo testo; i pulsanti portano alla pagina giusta.
- [ ] L'avvertenza c'è.
- [ ] Il test è **Pubblicato** e la sua pagina si apre; l'ho fatto io dal telefono fino al risultato.
- [ ] Il link condiviso apre lo stesso risultato.
- [ ] Con l'email: il risultato è arrivato; con la lista: è arrivata l'email per confermare.
- [ ] Il widget **Riquadro in evidenza** porta al test.
- [ ] Ogni fascia ha la risposta breve (a schermo) e la completa (per email).
- [ ] Le domande sulla persona sono solo quelle che mi servono davvero, e ho scelto se sono facoltative o obbligatorie.
- [ ] Ho fatto il test lasciando l'email con la newsletter, ho confermato e nella scheda del contatto ci sono i tag giusti.

**Problemi frequenti**

| Problema | Cosa fare |
|---|---|
| Non riesco a trascinare le carte fra le colonne | Si trascina solo con **A pagine, le scegli tu**; senza mouse usa la tendina **Pagina** nella scheda della domanda |
| Al telefono non vedo l'anteprima | Premi **Anteprima: come lo vede chi fa il test**; **Chiudi l'anteprima** torna alle carte |
| Il pulsante **Pubblica il test** è spento | Il riquadro Pubblica elenca cosa manca: di solito un buco fra le fasce (passo 3) |
| Una domanda condizionale non compare mai | Controlla nel passo 2 quali risposte sono spuntate in "Una di queste risposte" |
| Il risultato esce sempre nella stessa fascia | Guarda il simulatore (passo 3): forse le fasce non sono adatte ai punti, o una domanda ha il peso troppo alto |
| L'automazione "Completa un test" non parte | Parte solo per chi si iscrive alla lista dalla pagina del risultato, dopo la conferma. Controlla anche che l'automazione sia **Attiva** |
| Chi apre il link condiviso non vede il riquadro dell'email | È voluto: l'email si chiede entro due ore dal test, dalla pagina del risultato |
| Ho cambiato una fascia e i vecchi link mostrano altro | Il link porta l'esito di allora; se la fascia non c'è più, resta il punteggio |
| Nelle statistiche per gruppi leggo solo "meno di 5" | È voluto: i gruppi piccoli non si mostrano. Aspetta che più persone facciano il test |
| Il contatto non ha i tag del test | I tag arrivano solo a chi spunta la newsletter, e solo dopo che ha confermato dall'email |
