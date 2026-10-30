# La copia del sito altrove

*Bozza del capitolo del manuale d'uso (addon Copia del sito altrove, plumeCMS 2.0.0).*

La **Copia del sito** (Impostazioni > Copia del sito) fa uno zip di tutto il sito: database, immagini, file, temi. Ma se
resta sullo stesso server del sito, quando il server si rompe la perdi insieme al sito. Con questo addon, **ogni notte**
la copia parte da sola verso un altro spazio: un altro hosting via FTP, un Nextcloud, uno spazio cloud con WebDAV.

Cosa ti serve:

- uno spazio di destinazione con nome utente e password: un FTP (spesso lo dà l'hosting, anche un altro), oppure WebDAV
  (Nextcloud, ownCloud, Koofr, pCloud...);
- il cron del sito attivo (Impostazioni > Log cron): è lui che, ogni notte, fa partire la copia.

Google Drive e Dropbox per ora non ci sono.

## 1. Accendere

1. **Impostazioni** > **Addon** > carta **Copia del sito altrove** > **Accendi**.
2. Nel menu, dentro **Impostazioni**, subito sotto Copia del sito, c'è **Copia altrove**. Aprila.

## 2. Scrivere la destinazione

Nel riquadro **Destinazione**:

1. **Tipo**: FTP, **FTPS (FTP cifrato, meglio)**, SFTP o WebDAV. Se accanto c'è «non disponibile su questo server», il
   server del sito non ha quello che serve: per SFTP lo dice anche la frase sotto («manca l'estensione ssh2»).
2. Per FTP, FTPS e SFTP: **Server** (per esempio `ftp.miobackup.it`, senza `ftp://` davanti) e **Porta** (21 per FTP e
   FTPS, 22 per SFTP). Per WebDAV: **Indirizzo WebDAV** (in Nextcloud: File > Impostazioni in basso a sinistra > WebDAV;
   serve un indirizzo che comincia con `https://`).
3. **Nome utente** e **Password**. In Nextcloud usa una «password per le applicazioni» (Impostazioni personali > Sicurezza).
4. **Cartella sulla destinazione**: per esempio `copie-del-sito`. Se non c'è, si crea. Meglio una cartella solo per le copie.
5. **Quante copie tenere**: per esempio 7 (una settimana). Le più vecchie si cancellano da sole; gli altri file della cartella mai.
6. Spunta **Manda una copia ogni notte**.
7. Premi **Salva la destinazione**. Compare «Destinazione salvata. Adesso premi "Prova la connessione"».

La password si salva **cifrata** e non si mostra più: il campo resta vuoto, con scritto «Salvata: lasciala vuota per non
cambiarla». Per cambiarla scrivi quella nuova; per toglierla spunta **Togli la password salvata**.

## 3. Provare

1. Nel riquadro **Adesso** premi **Prova la connessione**. Il sito entra, apre la cartella, scrive un file piccolo e lo cancella.
2. Se va: «Collegamento riuscito: la cartella si apre e si può scrivere».
3. Se non va, il messaggio dice perché, per esempio «Accesso rifiutato: controlla nome utente e password», oppure
   «Il server ... non risponde sulla porta 21». Correggi, **Salva la destinazione** e riprova.

## 4. Mandare la prima copia

1. Premi **Manda una copia adesso** e conferma. Su un sito grande ci vuole qualche minuto: non chiudere la pagina.
2. Compare per esempio «Copia mandata (12,4 MB). Sulla destinazione ce ne sono 1».
3. Controlla sulla destinazione (con il programma FTP o nel Nextcloud): nella cartella c'è `copia-20261030-021500-altrove.zip`.

Da domani parte da sola ogni notte, quando gira il cron.

## 5. Il registro

Nel riquadro **Registro degli invii** c'è ogni prova e ogni invio: quando, cosa (Copia della notte, Copia mandata a mano,
Prova della connessione), **Riuscito** o **Non riuscito**, e il motivo.

Se di notte l'invio non riesce, ti arriva un'email «La copia del sito non è partita», con il motivo, all'indirizzo degli
avvisi (Impostazioni > Configurazione email). La copia resta comunque sul server, in Impostazioni > Copia del sito.

## 6. Rimettere il sito da una copia

Scarica lo zip dalla destinazione e caricalo in **Impostazioni** > **Copia del sito** > **Ripristina** (capitolo della
Copia del sito). Dopo un ripristino su un'installazione nuova la password della destinazione va riscritta: per sicurezza
la chiave che la decifra non sta nella copia.

## Esempio: le copie su Nextcloud

1. Nel Nextcloud: Impostazioni personali > Sicurezza > **Crea una password per le applicazioni** con il nome «sito». La copi.
2. Nel Nextcloud: File > Impostazioni (in basso a sinistra): copia l'indirizzo **WebDAV**, per esempio
   `https://cloud.esempio.it/remote.php/dav/files/mario/`.
3. Nel pannello: Impostazioni > Copia altrove. Tipo **WebDAV**, incolli l'indirizzo, nome utente `mario`, la password per le
   applicazioni, cartella `copie-sito`, tenere `14`, spunta ogni notte, **Salva la destinazione**.
4. **Prova la connessione**: «Collegamento riuscito».
5. **Manda una copia adesso**: «Copia mandata». Nel Nextcloud compare la cartella `copie-sito` con lo zip.

## Checklist

- [ ] Il cron del sito gira ogni notte (Impostazioni > Log cron: l'ultimo giro è di stanotte).
- [ ] La destinazione è salvata e **Prova la connessione** dice «Collegamento riuscito».
- [ ] **Manda una copia adesso** ha funzionato e lo zip c'è sulla destinazione.
- [ ] **Manda una copia ogni notte** è spuntato.
- [ ] L'indirizzo degli avvisi è giusto (Impostazioni > Configurazione email), per sapere se una notte non va.
- [ ] Domani mattina il registro ha una riga «Copia della notte», Riuscito.
- [ ] La destinazione è uno spazio tuo e protetto: la copia ha tutti i dati del sito.
