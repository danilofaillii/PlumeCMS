# Novità della 2.1: copie su Google Drive e Dropbox, contenuti riservati dalla scheda, ripristino pulito

*Bozza per il manuale d'uso (plumeCMS 2.1, in lavorazione). Tre parti: vanno nei capitoli della Copia altrove, dell'Area
riservata e della Copia del sito. Le schermate sono da fare con `tools/manuale-uso-schermate.js` quando le pagine sono
definitive.*

**Da sapere prima:** Google Drive e Dropbox sono stati provati solo contro due "finti Google e Dropbox" che si comportano
come quelli veri. Con i servizi veri non si è potuto provare: i nomi delle voci nelle loro pagine possono essere un po'
diversi da quelli scritti qui.

---

## Parte 1. La copia del sito su Google Drive o su Dropbox

Con l'addon **Copia del sito altrove** ogni notte una copia di tutto il sito parte verso un altro spazio. Adesso lo spazio
può essere anche **il tuo Google Drive** o **il tuo Dropbox**.

Si fa una volta sola e ci vogliono circa 10 minuti. Il sito non conosce la tua password di Google o di Dropbox: tu crei una
piccola "app" tua, che dà al sito il permesso di scrivere le copie, e lo puoi togliere quando vuoi.

### 1.1 Google Drive

**Cosa ti serve:** l'account Google dove vuoi le copie.

1. Nel pannello: **Impostazioni** > **Copia altrove**.
2. In **Dove mandare le copie** scegli **Google Drive**. Compare il riquadro Google Drive con scritto «Non ancora collegato» e
   i passi qui sotto.
3. Fai clic sull'indirizzo nel campo **Indirizzo da incollare**: si copia da solo. Ti serve al passo 9.
4. Apri, in un'altra scheda del browser, **console.cloud.google.com** ed entra con il tuo account Google.
5. In alto premi il nome del progetto (o **Seleziona un progetto**), poi **Nuovo progetto**. Nome: «Copie del sito». Premi
   **Crea** e poi scegli il progetto appena creato.
6. Nel menu a sinistra: **API e servizi** > **Libreria**. Cerca «Google Drive API», aprila e premi **Abilita**.
7. Nel menu: **Google Auth Platform** (prima si chiamava «Schermata consenso OAuth»). Premi **Inizia**: nome dell'app
   («Copie del sito»), la tua email, Pubblico **Esterno**, di nuovo la tua email, accetta e premi **Crea**.
8. Sempre lì, in **Pubblico**, premi **Pubblica app** e conferma. **Non saltarlo**: un'app lasciata «in test» perde il
   permesso dopo 7 giorni e le copie si fermano. Google non chiede di verificare l'app: chiede solo di vedere i file che crea lei.
9. In **Client** premi **Crea client**. Tipo di applicazione: **Applicazione web**. In **URI di reindirizzamento autorizzati**
   premi **Aggiungi URI** e incolla l'indirizzo del passo 3. Premi **Crea**.
10. Google mostra **ID client** e **Client secret**. Copiali, uno alla volta, nei campi **ID client** e **Client secret** del pannello.
11. Scrivi la **Cartella sulla destinazione** (per esempio `copie-del-sito`), **Quante copie tenere** (per esempio 7) e spunta
    **Manda una copia ogni notte**.
12. Premi **Salva e collega con Google Drive**. Compare «Ti porto alla pagina di Google Drive» e si apre la pagina di Google.
13. Scegli il tuo account e premi **Continua** (o **Consenti**). Se Google mostra un avviso sull'app, è la tua: premi
    **Avanzate** e poi **Vai a ...**.
14. Torni da solo nel pannello. Compare «Collegato a Google Drive come Mario Rossi (mario@gmail.com). Adesso premi "Prova la
    connessione"». Il riquadro diventa verde: «Collegato come ... dal ...».
15. Premi **Prova la connessione**: «Collegamento riuscito: la cartella si apre e si può scrivere».
16. Premi **Manda una copia adesso** e conferma. Dopo qualche minuto: «Copia mandata (12,4 MB)». Nel tuo Google Drive c'è la
    cartella `copie-del-sito` con dentro `copia-...-altrove.zip`.

**Checklist Google Drive**
- [ ] Google Drive API abilitata nel progetto
- [ ] App **pubblicata** (Pubblico > Pubblica app)
- [ ] Client di tipo **Applicazione web**, con l'indirizzo del pannello fra gli URI di reindirizzamento
- [ ] ID client e Client secret incollati, «Collegato come ...»
- [ ] Prova la connessione riuscita, una copia mandata a mano, «Manda una copia ogni notte» spuntato

### 1.2 Dropbox

**Cosa ti serve:** il tuo account Dropbox.

1. Nel pannello: **Impostazioni** > **Copia altrove**. In **Dove mandare le copie** scegli **Dropbox**.
2. Fai clic sull'indirizzo nel campo **Indirizzo da incollare**: si copia da solo.
3. Apri **dropbox.com/developers/apps** ed entra.
4. Premi **Create app**. Scegli **Scoped access**, poi **App folder** (l'app vede solo la sua cartella, non il resto del tuo
   Dropbox). Nome: per esempio «copie-sito-» e il nome del tuo sito. Premi **Create app**.
5. Scheda **Permissions**: spunta **files.metadata.read**, **files.content.write** e **files.content.read**. Premi **Submit** in fondo.
   Va fatto **prima** di collegare: i permessi aggiunti dopo valgono solo collegando di nuovo.
6. Scheda **Settings**: in **Redirect URIs** incolla l'indirizzo del passo 2 e premi **Add**.
7. Sempre in Settings: **App key** e **App secret** (premi **Show**). Copiali nei campi **App key** e **App secret** del pannello.
8. Cartella, quante copie tenere, «Manda una copia ogni notte» come per Google Drive.
9. Premi **Salva e collega con Dropbox**, poi nella pagina di Dropbox **Consenti** (Allow). Torni nel pannello: «Collegato a
   Dropbox come ...».
10. **Prova la connessione**, poi **Manda una copia adesso**. Le copie sono nel tuo Dropbox in **Applicazioni** > (nome dell'app) > `copie-del-sito`.

**Checklist Dropbox**
- [ ] App «App folder» con i tre permessi spuntati e salvati (Submit)
- [ ] Redirect URI aggiunto
- [ ] App key e App secret incollati, «Collegato come ...»
- [ ] Prova la connessione riuscita, una copia mandata

### 1.3 Cosa fa da solo il sito

- **Il permesso si rinnova da solo**: Google e Dropbox danno un accesso che dura un'ora; il sito ne chiede uno nuovo quando
  serve, senza che tu faccia niente.
- **Le copie grandi viaggiano a pezzi** (8 MB alla volta): se un pezzo non arriva, il sito riprende da dove era.
- **Restano le ultime copie** che hai scelto; le più vecchie si cancellano da sole. Gli altri file della cartella mai.
- **Il segreto dell'app e i permessi si salvano cifrati** e non si vedono mai più nel pannello.

### 1.4 Scollegare

Nel riquadro **Adesso** premi **Scollega Google Drive** (o Dropbox) e conferma. Il sito chiede al servizio di togliere il
permesso e lo dimentica; l'invio di ogni notte si spegne. Le copie già mandate restano nel tuo Drive o Dropbox.
Per ricollegare basta **Salva e collega** (ID e segreto dell'app restano salvati).

### 1.5 Se qualcosa non va

| Messaggio | Cosa fare |
|---|---|
| «L'ID client di Google finisce con .apps.googleusercontent.com» | Hai incollato un'altra cosa: copia di nuovo l'ID client dalla pagina Client |
| Pagina di Google con «redirect_uri_mismatch» | L'indirizzo nell'app non è identico a quello del pannello: copialo di nuovo (passo 3) e incollalo (passo 9) |
| «Non hai dato il permesso a ...» | Hai premuto Annulla: premi di nuovo Salva e collega e poi Consenti |
| «Questa risposta non è partita da qui, oppure è già stata usata» | Sono passati più di 15 minuti, o hai ricaricato la pagina: premi di nuovo Salva e collega |
| «Il collegamento con ... non vale più» (anche per email, di notte) | Il permesso è stato tolto, o l'app Google è rimasta «in test»: pubblicala e premi Salva e collega |
| «La Google Drive API non è abilitata» | Passo 6 di Google Drive |
| «All'app di Dropbox manca un permesso» | Passo 5 di Dropbox, poi Salva e collega di nuovo |
| «... è pieno» | Libera spazio o tieni meno copie |

---

## Parte 2. «Solo per i membri» dalla scheda del contenuto

Con l'addon **Area riservata** un articolo o una pagina può essere **solo per i membri**. Prima si sceglieva solo in
**Membri** > **Contenuti e file riservati**; adesso anche dalla scheda del contenuto.

1. Apri l'articolo o la pagina dalla scheda (Articoli o Pagine, clic sul titolo, poi **Modifica** nella scheda classica).
2. Nella colonna di destra c'è il riquadro **Area riservata** con la spunta **Solo per i membri**.
3. Spuntala e premi **Aggiorna** (o **Pubblica**). Per renderlo di nuovo di tutti: togli la spunta e **Aggiorna**.
4. In **Membri** > **Contenuti e file riservati** lo trovi spuntato anche lì: sono la stessa scelta.

Da sapere:
- La **home** non si può rendere riservata: la spunta è spenta e c'è scritto «La home resta di tutti».
- Il riquadro lo vede solo l'**amministratore**, come le pagine Membri. Il redattore non lo vede.

**Cosa cambia per chi visita il sito.** Un contenuto solo per i membri, **titolo e riassunto compresi**, non compare più:
- nella **ricerca del sito** (tranne per un membro collegato, che lo trova come gli altri);
- nella **sitemap** (quindi Google e Bing non lo trovano da lì);
- nel **feed RSS**;
- in **llms.txt** e **llms-full.txt** (i file per le intelligenze artificiali).

Restano come prima: l'elenco del blog, delle categorie e dei tag e il widget Griglia di contenuti mostrano titolo e
riassunto; chi apre il contenuto senza essere entrato trova l'invito ad accedere.

**Checklist**
- [ ] Riquadro «Area riservata» nella scheda, spunta messa, Aggiorna
- [ ] Da un'altra finestra in navigazione privata: `/?s=` con il titolo non lo trova; `/sitemap.xml` e `/feed/` non lo hanno
- [ ] Da membro collegato la ricerca lo trova

---

## Parte 3. Il ripristino pulito della Copia del sito

In **Impostazioni** > **Copia del sito**, ripristinare una copia rimette il database e i file della copia. Fino alla 2.0
i file che c'erano nel sito ma non nella copia (una foto caricata dopo, un tema provato e poi tolto) **restavano**. Adesso
immagini, temi e moduli diventano **esattamente come nella copia**.

1. Ripristina come sempre: dall'elenco **Copie sul server** (**Ripristina questa copia**, spunta, **Ripristina adesso**) o
   con **Carica e ripristina**.
2. Nell'esito, se c'erano file in più, compare un riquadro: «N file c'erano nel sito ma non nella copia: li ho spostati da
   parte», con l'elenco dei file.
3. **Non sono cancellati**: stanno sul server, nella cartella `aggiornamenti/scarto/ripristino-<data>/`, per **30 giorni**.
   Da internet non si aprono. Per rimetterne uno, riportalo nella stessa cartella del sito con il gestore dei file del tuo hosting.
4. In fondo alla pagina, la sezione **File messi da parte dai ripristini** elenca ogni ripristino con quanti file e quanto
   pesano. **Elimina** li cancella per sempre (chiede conferma). Dopo 30 giorni si cancellano da soli.

**Vuoi il comportamento di prima?** Prima di ripristinare spunta **Tieni i file in più**: i file che non sono nella copia
restano dove sono.

Non si spostano mai: i file nascosti (come `.htaccess`), i temi che arrivano con plumeCMS (Cosmo, Aria, Bottega).

**Due siti nello stesso database.** Se sullo stesso database ci sono due installazioni con prefissi che cominciano uguale
(per esempio `cms_` e `cms_b_`), la copia di una non prende più le tabelle dell'altra, e il ripristino non le tocca mai.
Il registro lo dice: «Tabelle di un'altra installazione nello stesso database, lasciate stare».

**Checklist**
- [ ] Dopo il ripristino: riquadro con i file spostati (o «Nessun file in più»)
- [ ] Sezione «File messi da parte dai ripristini» con la riga del ripristino
- [ ] Con «Tieni i file in più» i file restano

Portata nel manuale d'uso il 2026-10-31, capitoli 40 ("Google Drive", "Dropbox"), 38 ("Dove non compare più", "Solo per i membri, dalla scheda del contenuto") e 29 ("Il ripristino pulito").
