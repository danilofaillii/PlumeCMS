# Trasloco del sito di plumeCMS su https://plumecms.it

Per Danilo, 1 novembre 2026. Stesso hosting: il sito non si copia, si collega il dominio nuovo alla cartella dove c'è già.
Tempo: mezz'ora, più l'attesa del certificato HTTPS (da pochi minuti a qualche ora).

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

## 3. Collegare plumecms.it alla stessa cartella (cPanel)

1. In cPanel apri **Domini** (Domains).
2. Guarda la riga di `plumecms.danilofailli.it` e annota la **Radice documenti** (Document Root), per esempio
   `/home/tuoutente/plumecms.danilofailli.it`.
3. Premi **Crea un nuovo dominio** (Create A New Domain):
   - Dominio: `plumecms.it`;
   - **togli** la spunta "Condividi la radice documenti" se ti propone quella del dominio principale;
   - Radice documenti: scrivi **la stessa cartella** annotata al punto 2;
   - premi **Invia**.
4. In cPanel apri **SSL/TLS Status** e premi **Run AutoSSL**. Aspetta che `plumecms.it` e `www.plumecms.it` abbiano il lucchetto verde.
5. Apri `https://plumecms.it` nel browser: deve comparire il sito di plumeCMS, con il lucchetto.

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

## 6. Chiudere il vecchio indirizzo

1. In cPanel, **Domini**: sulla riga di `plumecms.danilofailli.it` premi **Gestisci** e poi **Rimuovi il dominio**.
2. **Attenzione**: se cPanel chiede se cancellare anche la cartella (radice documenti), rispondi **NO**. È la stessa cartella
   di plumecms.it: cancellarla cancellerebbe il sito.
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

- Il sito su plumecms.it mostra la pagina dell'hosting: il punto 3 non punta alla cartella giusta. Ricontrolla la Radice documenti.
- Il pannello dice "certificato non valido": AutoSSL non ha ancora finito. Aspetta e riprova.
- Il sito non si apre più da nessun indirizzo: in cPanel la cartella è sparita (punto 6.2). Ripristina la copia del punto 1
  (installazione nuova di plumeCMS nella cartella, poi Impostazioni > Copia del sito > Carica e ripristina).
