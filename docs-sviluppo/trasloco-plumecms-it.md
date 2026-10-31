# Trasloco del sito di plumeCMS su https://plumecms.it

Per Danilo, 1 novembre 2026. Stesso hosting, **cartella nuova** per plumecms.it, **stesso database**: si copiano i file del sito
nella cartella nuova e il sito nuovo usa lo stesso database del vecchio. Tempo: mezz'ora, più l'attesa del certificato HTTPS.

Finché esistono tutti e due, i due siti leggono e scrivono lo stesso database: quello che salvi in uno compare nell'altro.
Durante il trasloco **non modificare contenuti**.

## Prima di cominciare

- [ ] Il dominio plumecms.it punta al tuo hosting (i DNS li hai impostati da chi te l'ha venduto: se nel browser
      `plumecms.it` apre la pagina dell'hosting o un errore del server, ci siamo; se apre la pagina del registrar, aspetta).
- [ ] Hai il file `plumecms-2.4.0-pacchetto-unico.zip`.

## 1. Una copia di sicurezza del sito com'è adesso

1. Apri il pannello del sito di plumeCMS (ancora sul vecchio indirizzo).
2. **Impostazioni > Copia del sito > Prepara la copia**.
3. Quando è pronta, premi **Scarica** e tienila sul computer. Serve solo se qualcosa va storto.

## 2. Caricare la 2.4.0

1. **Impostazioni > Aggiornamenti > Carica un pacchetto**: scegli `plumecms-2.4.0-pacchetto-unico.zip`, poi **Applica ora**.
2. In alto a sinistra deve comparire **v. 2.4.0**.

## 3. Copiare i file nella cartella di plumecms.it (cPanel)

1. In cPanel apri **Domini** (Domains) e annota due cartelle (colonna **Radice documenti**):
   - quella di `plumecms.danilofailli.it` (la **vecchia**), per esempio `/home/tuoutente/plumecms.danilofailli.it`;
   - quella di `plumecms.it` (la **nuova**), per esempio `/home/tuoutente/plumecms.it`.
2. Apri **File Manager**. In alto a destra **Impostazioni** (Settings): spunta **Mostra i file nascosti (dotfiles)** e salva.
   Serve per copiare anche i file `.htaccess`, che proteggono il sito.
3. Se nella cartella **nuova** c'è già qualcosa (per esempio una pagina di benvenuto dell'hosting), selezionalo e cancellalo.
4. Entra nella cartella **vecchia**, premi **Seleziona tutto** (Select All), poi **Copia** (Copy). Come destinazione scrivi la
   cartella **nuova** (per esempio `/plumecms.it`) e premi **Copia i file**.
5. Controlla nella cartella nuova che ci siano: `index.php`, `.htaccess`, le cartelle `admin`, `includes`, `uploads`, `sito`,
   e il file `includes/config.local.php` (è lui che dice al sito quale database usare: va copiato così com'è).
6. In cPanel apri **SSL/TLS Status** e premi **Run AutoSSL**. Aspetta che `plumecms.it` e `www.plumecms.it` abbiano il lucchetto verde.
7. Apri `https://plumecms.it` nel browser: deve comparire il sito di plumeCMS, con il lucchetto.

## 4. Dire al sito il suo indirizzo nuovo

1. Apri il pannello dal nuovo indirizzo: `https://plumecms.it/admin/`, ed entra.
2. **Impostazioni**, riquadro **Indirizzo del sito**: scrivi `https://plumecms.it`.
3. Premi **Salva l'indirizzo** e conferma. In alto deve comparire **"Indirizzo del sito cambiato: https://plumecms.it"**.
   Se invece dice "non apre ancora questo sito", il certificato o il collegamento del punto 3 non sono finiti: aspetta e riprova.

## 5. Controlli (cinque minuti)

- [ ] `https://plumecms.it` apre la home; Funzionalità, Download e Documentazione si aprono.
- [ ] In **Download** premi "Scarica l'aggiornamento": arriva `plumecms-2.4.0-aggiornamento.zip`.
- [ ] `https://plumecms.it/api/versione.php?serie=plumecms-ultima-aggiornamento` mostra un testo con `"versione":"2.4.0"` e
      un indirizzo che comincia con `https://plumecms.it/scarica/`.
- [ ] `https://plumecms.it/sitemap.xml` elenca indirizzi che cominciano con `https://plumecms.it/`.
- [ ] Nel pannello, **Impostazioni > Controlla il server > Controlla adesso**: nessuna voce rossa nuova.

## 5b. I compiti automatici (cron)

Il cron di cPanel lancia i file del sito con il percorso della cartella **vecchia**: va spostato sulla nuova, prima di chiudere il vecchio.

1. Nel pannello di plumecms.it: **Impostazioni > Log cron**. In alto ci sono le due righe giuste da mettere nel cron, già con il
   percorso della cartella nuova.
2. In cPanel apri **Processi Cron** (Cron Jobs). Trovi le due righe di plumeCMS con dentro il percorso vecchio.
3. Per ognuna premi **Modifica** e sostituisci il comando con quello del punto 1, oppure cancellala e aggiungi quella nuova
   con gli stessi orari. Salva.
4. Il giorno dopo, in **Impostazioni > Log cron**, "Ultimo giro" deve avere la data di oggi.

## 6. Chiudere il vecchio indirizzo

1. In cPanel, **Domini**: sulla riga di `plumecms.danilofailli.it` premi **Gestisci** e poi **Rimuovi il dominio**.
2. Se cPanel chiede se cancellare anche la sua cartella, qui puoi dire di sì: è la cartella **vecchia**, il sito ora vive in
   quella nuova. Per stare tranquillo, prima scarica la copia del punto 1 (se non l'hai già fatto) e controlla ancora una volta
   che `https://plumecms.it` funzioni.
3. Facoltativo: se vuoi che `www.plumecms.it` porti a `plumecms.it`, in cPanel **Reindirizzamenti** (Redirects):
   tipo **Permanente (301)**, da `www.plumecms.it` a `https://plumecms.it`.

## 7. Google e IndexNow

1. Search Console: **Aggiungi proprietà**, tipo **Prefisso URL**, `https://plumecms.it/`. Per la verifica scegli
   **Tag HTML**, copia il codice e incollalo nel pannello in **SEO e AEO > Motori di ricerca > Google Search Console**
   (Salva i codici), poi in Search Console premi **Verifica**.
2. Search Console, **Sitemap**: aggiungi `sitemap.xml`.
3. IndexNow: niente da fare, la chiave vale anche sul dominio nuovo. Se vuoi, **Invia tutta la sitemap adesso**.

## 8. Gli altri siti con plumeCMS

I siti con la 2.3.2 o prima cercano gli aggiornamenti sul vecchio indirizzo, che ora è chiuso. Su ognuno, una volta sola:
**Impostazioni > Aggiornamenti > Carica un pacchetto**, il file `plumecms-2.4.0-aggiornamento.zip` (lo scarichi da
`https://plumecms.it/scarica/`), poi **Applica ora**. Dalla 2.4.0 in poi vedono le versioni nuove da soli e funziona Installa ora.

## Se qualcosa va storto

- Il sito su plumecms.it mostra la pagina dell'hosting o un elenco di file: la copia del punto 3 è finita in un'altra cartella,
  oppure è rimasto il file di benvenuto dell'hosting (`index.html`): cancellalo.
- Il sito su plumecms.it dice "Manca includes/config.local.php": quel file non è stato copiato. Copialo dalla cartella vecchia.
- Le pagine si aprono ma le immagini no, o il pannello è senza grafica: mancano i file nascosti (`.htaccess`) o la cartella
  `uploads`. Rifai il punto 3 con "Mostra i file nascosti" acceso.
- Il pannello dice "certificato non valido": AutoSSL non ha ancora finito. Aspetta e riprova.
- Qualcosa è andato storto e non sai come tornare indietro: hai la copia del punto 1. Installazione nuova di plumeCMS nella cartella
  di plumecms.it, poi Impostazioni > Copia del sito > Carica e ripristina.
