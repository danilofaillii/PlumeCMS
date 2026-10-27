# Novità

Il prodotto si chiama **plumeCMS** (nome scelto il 26 ottobre 2026; prima, per poche ore, "CarbonaraCMS", e ancora prima il provvisorio "Officina": gli zip vecchi si chiamavano `officina-*` e `carbonaracms-*`).

## 1.3.0 (28 ottobre 2026) - Si parte con un sito vero

- **Installazione, "Che sito vuoi fare?"**: all'ultimo passo si sceglie Vuoto (il sito di sempre), Personale, Attività,
  Blog o Portfolio. Il CMS parte con la home, le pagine principali e il menu dell'header già fatti con l'editor, e testi
  d'esempio brevi che dicono cosa scriverci. Dove servono immagini ci sono disegni astratti, da sostituire con le tue foto
  dalla libreria. Funziona anche con il CMS in una cartella. Se non si sceglie niente, resta Vuoto.
- **Editor, linguetta Sezioni**: 30 sezioni già impaginate in nove gruppi (apertura, servizi, chi siamo, numeri,
  testimonianze, prezzi, domande frequenti, contatti, invito all'azione), con un'anteprima piccola. Un clic la mette sotto
  la sezione selezionata, oppure la trascini nel punto che vuoi. Una volta dentro è una sezione normale: cambi testi,
  colori e widget come sempre. Seguono i colori del sito e dei temi, chiari e scuri.
- **Aggiornamenti, "C'è una versione nuova"**: una volta al giorno il pannello chiede al sito di plumeCMS qual è l'ultima
  versione. Se è più nuova, lo dice nella dashboard e in Aggiornamenti; con **Scarica e prepara** lo zip arriva da solo,
  controllato (HTTPS, dimensione e impronta sha256), nella lista dei pacchetti, dove premi Applica ora come sempre. Si spegne
  dalla stessa pagina. Senza rete il pannello funziona come prima.
- **Impostazioni, Copia del sito**: "Prepara la copia" mette in uno zip database, immagini e file caricati, temi e moduli
  (la password del database no); poi la scarichi. "Ripristina" rimette tutto da una copia, caricata o già sul server, dopo
  averne fatta una del sito di adesso. Per i siti con un limite di caricamento basso, la copia si mette nella cartella
  `aggiornamenti/copie/` con il gestore dei file e si ripristina dall'elenco. Da una copia di un altro sito i file con
  codice PHP non entrano.
- **Per chi fa siti con plumeCMS**: `api/versione.php?serie=<serie>` dice versione, nome, sha256 e dimensione dell'ultimo
  file di una serie del download manager.

## 1.2.8 (27 ottobre 2026) - Software libero, di tutti

- **Licenza**: plumeCMS è software libero con la GNU GPL, versione 3 o successiva. Il testo è nel file `LICENSE` e la
  pagina Informazioni del pannello lo dice.
- **Niente più cose di un sito solo**: plumeCMS nasce dal pannello di danilofailli.it, e se ne portava dietro dei pezzi.
  Ora sono fuori dal prodotto:
  - i moduli ebook e Laser Coaching (motore, pagina Contatti del pannello, pagine di conferma e di download);
  - l'invito finale con le offerte di quel sito nella scheda articolo, e il box del test sul burnout negli articoli;
  - i testi d'esempio dei widget (Apertura, Frase, Schede, Numeri, Invito, Riquadro in evidenza, Listino, Pulsanti):
    ora sono neutri, e i siti che usavano quelli di partenza li vedono cambiare;
  - il nome del mittente delle email di partenza, ora il nome del sito; "Glossario PNL", ora "Glossario"
    (impostazione `glossario_titolo`); il tipo "Coaching" nei dati strutturati dei servizi;
  - gli aforismi come tipo fisso: SEO e widget Griglia elencano da soli i tipi di contenuto dei moduli.
- Per chi scrive moduli: filtri `moduli_disponibili`, `modulo_html`, `pagina_moduli` ed evento `sito_percorso`, per
  portare nel proprio modulo i moduli da compilare e le pagine che il nucleo non ha più (manuale tecnico, 15.4).
- **Correzioni**: in un sito installato nella radice il captcha di newsletter e form chiedeva la sfida a un indirizzo
  sbagliato; nel pannello la scheda articolo non è più ingrandita dal vecchio foglio delle infografiche.
- **Installazione di notte**: l'installatore pubblicava le pagine di partenza "oggi alle 6:00"; chi installava fra mezzanotte
  e le sei trovava il sito vuoto (pagina non trovata) fino al mattino. Ora escono subito.
- **Pubblica vuol dire adesso**: un articolo o una pagina nuova parte con l'ora di adesso, non più con le 6:00 di oggi
  (prima, di notte, "Pubblica" lo programmava per il mattino). Per programmarlo si cambiano data e ora, come sempre.
- Nella pagina Newsletter il percorso del cron è quello vero della tua installazione.

## 1.2.7 (27 ottobre 2026) - Il margine delle aperture

- **Apertura**: Centrata, Copertina, Rivista e Scheda ritrovano il loro margine interno. Nella 1.2.6 una regola del
  foglio del sito lo azzerava: nella Copertina testo e pulsanti toccavano il bordo del riquadro.
- Le altre sette disposizioni non cambiano.

## 1.2.6 (27 ottobre 2026) - Undici aperture

- **Apertura (hero), tendina Disposizione**: oltre alla classica, dieci disposizioni nuove. Centrata, Copertina (foto a
  tutto sfondo), Divisa a metà, Rivista, Scheda (ritratto rotondo), Minimale (solo parole), Mosaico, Inclinata, Banner
  (fascia bassa per le pagine interne), Diagonale. Stessi campi per tutte: cambiando disposizione i testi restano.
- Tutte seguono i colori e i caratteri del sito e dei temi, chiari e scuri, e si leggono al telefono.
- La classica è identica al pixel alla 1.2.5: i siti che ci sono non cambiano.
- Per chi scrive widget: `editor_registra_asset()` accetta più file CSS o JS in un elenco.

## 1.2.5 (27 ottobre 2026) - Sempre l'ultima versione

- **Download, serie**: un file con la versione nel nome (`guida-1.2.pdf`) entra in una serie. `/scarica/guida-ultima/`
  porta sempre alla versione più alta caricata, e nelle pagine `{versione:guida-ultima}` diventa il suo numero (anche nel
  titolo della scheda). Per pubblicare una versione nuova basta caricare il file nuovo: link, pulsanti e titoli la seguono.
- Il widget Download offre le serie ("Sempre l'ultima") e `{versione}` nei titoli; la pagina Download mostra l'indirizzo
  "sempre l'ultima versione" da copiare.
- **Temi con PHP**: il messaggio di Editor > Temi dice i passi esatti; il riquadro di Sicurezza si chiama "Finestra di un'ora
  per i file PHP (pacchetti e temi)" e spiega che serve anche per i temi.
- Il sito di plumeCMS usa le serie: le sue pagine non vanno più ricostruite a ogni versione del CMS.

## 1.2.4 (27 ottobre 2026) - Download manager

- **Pannello, voce Download** (sotto Media, solo amministratore): i file che il sito offre da scaricare. Carichi un file
  con titolo, descrizione e versione; con **Sostituisci** metti la versione nuova allo stesso indirizzo; vedi quante
  volte è stato scaricato e quando l'ultima; copi l'indirizzo con un clic; elimini.
- **Widget Download** (gruppo "Dal sito"): i file scelti, o gli ultimi caricati, con icona del tipo, titolo,
  descrizione, tipo e peso, versione, data, quante volte è stato scaricato e il pulsante. A elenco o a schede.
- Tipi ammessi: pdf, zip, epub, docx, xlsx, pptx, odt, ods, mp3, txt, csv. Il contenuto si controlla: un file con il nome
  sbagliato non entra.
- Il conteggio è delle persone: i robot dei motori di ricerca e i programmi automatici non contano.
- Ogni file si scarica da `/scarica/<nome>/`, come nella 1.2.3.

## 1.2.3 (27 ottobre 2026) - Il sito di plumeCMS e i manuali in PDF

- **Manuali in PDF**: `tools/manuali-pdf.js` stampa il manuale tecnico e quello dei temi, con copertina, indice
  cliccabile e numeri di pagina.
- **File da scaricare**: gli zip e i PDF in `uploads/scaricabili/` si aprono da `/scarica/<nome>/` (il `.htaccess`
  vieta gli zip dal web, quindi passano da PHP).
- **Pacchetti che portano un sito**: voci nuove del manifest `pagine` (pagine dell'editor), `editor` (stile, header,
  footer, menu nel formato di Esporta) e `impostazioni` (nome e descrizioni del sito). Con l'azione
  `sito-pagine-e-stile`, che fa rifiutare il pacchetto ai motori più vecchi.
- **Il sito di plumeCMS**, fatto con plumeCMS: `sito-plumecms/costruisci.py` scrive il pacchetto.
- Widget Menu di navigazione: si può scegliere "quello in posizione: ...", qualunque sia l'id del menu.
- Correzioni:
  - nell'editor il pannello **Revisioni** non si chiudeva mai e toglieva 380 pixel alla tela;
  - lo sfondo **Colore** di una sezione non funzionava con i colori globali;
  - il pannello si presentava come "DF Pannello": ora "plumeCMS".

## 1.2.2 (27 ottobre 2026) - Velo ed effetti sulle foto

- **Apertura (hero)** e **Presentazione**: nella scheda Stile c'è la sezione nuova **Foto**.
  - **Trattamento della foto**: velo colorato sul bianco e nero (com'era), solo bianco e nero, oppure foto naturale.
  - **Colori del velo** (tre nell'Apertura, due nella Presentazione), anche presi dai colori globali, e
    **intensità del velo** da 0 a 100%.
  - **Effetto animato**, cinque a scelta: zoom lento, bagliore che attraversa, cornice luminosa che gira, alone che
    pulsa, scintille che salgono. Ognuno con due colori e la velocità (lenta, normale, veloce). Chi ha chiesto al
    proprio sistema meno movimento vede la foto ferma.
- **Apertura**: interruttore **Mostra l'onda sopra la foto** (scheda Contenuto) per tenere o togliere il riquadro
  con le barre che ondeggiano.
- Le pagine già fatte non cambiano: con i valori di partenza le foto sono identiche al pixel a quelle di prima.
- Un tema può dare i suoi colori al velo e agli effetti con le variabili `--velo-*` e `--fx-*` (`docs/manuale-temi.md`).

## 1.2.1 (27 ottobre 2026) - I due manuali

Solo documentazione: il codice è quello della 1.2.0.

- **Manuale tecnico** (`docs/manuale-tecnico.md`): tutto il CMS pezzo per pezzo, scritto leggendo il codice.
  Installazione, configurazione e costanti, percorso di una richiesta, cartelle e base URL, database e tabelle,
  pannello e ruoli, contenuti, editor (albero, limiti, controlli, schede, i 79 widget, come se ne scrive uno nuovo),
  Stile del sito, header, footer e menu, temi, versioni, media, form, popup, newsletter, posta, SEO e AEO, moduli con
  l'elenco completo di filtri ed eventi, sicurezza, aggiornamenti, cron, `.htaccess` e Nginx, log, strumenti e prove,
  risoluzione dei problemi, riferimento delle funzioni. In appendice, i punti in cui i documenti più vecchi non
  corrispondono più al codice.
- **Manuale dei temi** (`docs/manuale-temi.md`): come si costruisce un tema da zero, file per file, con un tema
  d'esempio completo (Lavagna) provato su un plumeCMS vero.
- Nello zip entrano anche `docs/cms-moduli.md` e `docs/versioni.md`, che prima restavano fuori.

## 1.2.0 (27 ottobre 2026) - I temi

- **Editor > Temi**, pagina nuova (solo amministratore): un tema cambia l'aspetto intero del sito in un colpo solo,
  cioè colori, caratteri, pulsanti, sfondo, header, footer e colori delle infografiche. Pagine, articoli, menu e immagini
  non cambiano. **Anteprima** apre il sito con il tema solo per te; **Attiva** chiede conferma e spiega cosa cambia; lo
  stile di prima resta nelle Versioni e gli header e footer di prima restano in Header e footer.
- Tre temi di partenza: **Cosmo** (l'aspetto di sempre: attivarlo lascia il sito identico), **Aria** (chiaro, un solo
  blu) e **Bottega** (carta crema, titoli con le grazie, terracotta e salvia).
- Un tema può contenere PHP, come in WordPress (filtri, eventi, pagine ridisegnate). Il pannello non lo esegue mai; se
  il PHP del tema sbaglia, il tema si segna come guasto e il sito torna da solo all'aspetto di base.
- **Carica un tema** da zip, **Scarica** un tema, **Scarica l'aspetto di adesso come tema**, **Elimina**. Un tema con PHP
  si carica solo firmato con la chiave del sito o con la finestra di un'ora aperta in Sicurezza. Nella cartella `temi/`
  il CMS scrive da solo un `.htaccess` che dal web non esegue niente.
- Le infografiche prendono i colori del tema attivo; i colori scelti a mano su un'infografica restano. "Temi delle
  infografiche" si chiama ora **Colori delle infografiche**, e Carta "segue il tema del sito".
- Lo Stile del sito arriva dappertutto: i gradienti dei pulsanti, le sfumature, i vetri e il riquadro CTA seguono i
  colori globali (prima restavano viola e ciano). Con i colori di partenza il sito è identico al pixel.
- Correzione: con uno sfondo chiaro nello Stile del sito il testo degli articoli era quasi invisibile; ora segue il
  colore del testo.

## 1.1.9 (27 ottobre 2026) - "Vedi sito" e la cartella che tornava alla radice

- Fino alla 1.1.6 la cartella del CMS (`/test/`) veniva rimandata alla radice del dominio con un 301, cioè un rimando
  permanente: il browser lo ricorda e continua a farlo anche dopo l'aggiornamento, senza più chiedere al server. Il
  rimando che resta (cartella del solo pannello, con il sito nella radice) ora è un 302 con `no-store`, che il
  browser non conserva. Chi ha visto la 1.1.6 deve svuotare una volta la cache del browser.
- "Vedi sito" nel menu del pannello apre l'indirizzo del sito scritto in configurazione, invece della cartella sopra il
  pannello.

## 1.1.8 (27 ottobre 2026) - I file dello zip leggibili dal server web

- Lo zip del prodotto salvava ogni file con permessi 0600. Estratto sul server, le pagine PHP funzionavano (girano con
  l'utente del sito) ma il server web non poteva leggere fogli di stile, script e immagini e rispondeva 403: installatore e
  pannello senza grafica e con i pulsanti fermi. Ora i file escono 0644 e le cartelle 0755.

## 1.1.7 (26 ottobre 2026) - Installare in una cartella

- L'installatore accettava come indirizzo del sito solo il dominio (`https://esempio.it`): installando in una cartella
  (`https://esempio.it/test`) l'indirizzo con la cartella veniva rifiutato. Ora la propone già, la accetta e scrive
  la cartella nel `config.local.php` (`CMS_BASE`), così il CMS non deve indovinarla.
- Gli `.htaccess` dichiarano il tipo di fogli di stile, script, SVG e caratteri: alcuni server li mandano senza tipo
  e il browser, con `nosniff`, li scarta, lasciando il pannello senza grafica.

## 1.1.6 (26 ottobre 2026) - Pagina Aggiornamenti pulita

- Il riquadro "Diagnosi articoli" nella pagina Aggiornamenti compare solo dove c'è il modulo che la contiene: su
  un'installazione nuova non c'era la pagina e il link portava a un errore.

## 1.1.5 (26 ottobre 2026) - Il pannello senza grafica con un indirizzo "sporco"

- Con un pezzo in più dopo il nome del file (per esempio `/admin/index.php/`) il pannello si apriva ma cercava fogli
  di stile e script nel posto sbagliato e appariva senza grafica. Ora ogni pagina del pannello torna da sola
  all'indirizzo pulito, con la stessa query.

## 1.1.4 (26 ottobre 2026) - La pagina SEO e AEO su PHP con short_open_tag

- La pagina SEO e AEO dava errore 500 sui server con `short_open_tag` acceso, che è il valore predefinito di PHP
  (Hostcare compreso). L'esempio del file BingSiteAuth.xml nel segnaposto cominciava con `<?xml` e PHP lo leggeva
  come inizio di codice. Ora l'esempio è scritto con le entità.
- `tools/pacchetto.py` e `tools/cms-pacchetto.py` controllano la sintassi di ogni file PHP con `short_open_tag` acceso
  e rifiutano il pacchetto se c'è un errore.
- Provato su PHP 8.4.27: tutte le pagine del pannello e le pagine pubbliche principali, senza errori.

## 1.1.3 (26 ottobre 2026) - Sicurezza, quarta passata

Le tre basse del terzo attacco:
- Login: utente inesistente e password sbagliata rispondono nello stesso tempo. L'hash finto ha lo stesso algoritmo e lo
  stesso costo degli account veri (argon2id 64 MB, 4 passate, o bcrypt 12 dove argon2id manca), calcolato una volta e
  conservato nell'impostazione `sicurezza_hash_finto`.
- Blocco per nome utente: 20 tentativi sbagliati in 15 minuti, da qualunque IP, bloccano il nome per 15 minuti (poi
  raddoppia), con un'email all'amministratore. Durante il blocco entra lo stesso, con la password giusta, chi arriva da
  un IP che ha gia fatto accessi riusciti con quell'utente e non e mai stato bloccato; resta la riga "Accesso durante un
  blocco (IP di casa)" nel registro. Da un IP estraneo password giusta e sbagliata rispondono uguale. Il blocco per IP
  resta com'era (5 tentativi, poi raddoppia).
- Installatore: la password dell'amministratore passa da `sicurezza_password_errore()` come nel pannello (12 caratteri,
  non comune, diversa da nome ed email, niente sequenze). Le funzioni delle password stanno in
  `includes/sicurezza-password.php`, senza database.
- `docs/sicurezza.md`: le regole dei blocchi, e perche i campi `~b64~` e il controllo dell'origine restano come sono.
- `tools/sicurezza-prova.js`: 11 prove nuove (blocchi per nome utente con IP simulati, tempi del login, installatore).

## 1.1.2 (26 ottobre 2026) - Sicurezza, terza passata

- Ruoli applicati lato server con un elenco chiuso: il redattore gestisce solo articoli, pagine (e i tipi di contenuto dei
  moduli), media, categorie e tag; newsletter, form, popup, menu, header e footer, Stile, temi, widget, impostazioni
  dell'editor e le versioni di stile, modelli e menu sono dell'amministratore. Il menu e la dashboard seguono le stesse regole.
  In `modelli.php` pubblica e predefinito rispettano anche "Chi pubblica".
- Nessun file PHP dei moduli si chiama direttamente dal web (`.htaccess`, Nginx); gli script di manutenzione dei moduli
  chiedono l'amministratore, e quelli che scrivono partono solo con POST e CSRF.
- CSV senza formule: le celle che cominciano con = + - @, tab o a capo prendono un apice (`sicurezza_csv_cella`), in tutti
  gli export del nucleo e del modulo.
- Login e password dimenticata non mostrano piu dettagli del database a video (vanno nel log).
- CSP del pannello solo a nonce, senza hash ricavati dall'output; le installazioni nuove partono con la CSP applicata su
  sito e pannello.
- Test del burnout (modulo): il report parte solo per un test fatto nella stessa sessione, una volta, con limiti per IP e per email.
- `tools/sicurezza-prova.js`: giro di tutti i file PHP del pannello, delle API e dei moduli da anonimo e da redattore.

## 1.1.1 (26 ottobre 2026) - Sicurezza, seconda passata

Correzioni trovate dal collaudo di attacco sul prodotto pulito:
- Newsletter: la firma dei link copre anche l'indirizzo di destinazione (niente rimando aperto da `api/nl.php`); i link
  gia spediti portano solo dentro il sito.
- Cron: `cron-daily.php` (come `cron-newsletter.php`) gira solo da riga di comando, o via web con `CMS_CRON_TOKEN`;
  negato anche dal `.htaccess` e dalla regola Nginx.
- Immagini caricate sempre nella cartella del CMS (`cms_uploads_dir()`/`cms_uploads_url()`): sul prodotto pulito finivano
  fuori dalla radice web e la protezione era sulla cartella sbagliata. Ricodifica anche per le immagini della newsletter.
- Ritorni `torna` del pannello solo interni.
- Pannello senza gestori in linea (attributi `data-*` e `admin/assets/pannello.js`): la CSP non ripiega piu su
  `'unsafe-inline'`, salvo tre pagine vecchie del modulo dichiarate con il filtro `csp_pannello_inline`.
- Chiamate in uscita senza curl rifiutate (niente DNS rebinding).
- Pacchetti: file eseguibili nella radice (`root/`) solo con firma o nella finestra di un'ora aperta con la password;
  `tools/pacchetto.py --rifirma`.
- Password dimenticata: limite per email e per IP a parte dal login (nessuno chiude fuori il proprietario).

## 1.1.0 (26 ottobre 2026) - Sicurezza

- **Autenticazione**: hash argon2id (o bcrypt costo 12) con migrazione trasparente al primo login; requisiti della
  password (12 caratteri, diversa dal nome/email, non tra le più comuni); limite ai tentativi di login per IP e per
  utente con blocco crescente e avviso email; registro degli accessi (riusciti, falliti, blocchi) con IP e browser.
- **Secondo fattore TOTP** fatto in casa (QR in SVG generato dal server, otto codici di recupero, obbligatorio
  opzionale per gli amministratori). Nasce spento, si attiva da "Il mio accesso".
- **Sessione**: id rigenerato al login (niente session fixation), cookie HttpOnly/Secure/SameSite, scadenza assoluta
  e per inattività, "esci da tutti i dispositivi", il cambio password chiude le altre sessioni.
- **Password dimenticata** con token firmato monouso via email (30 minuti, senza rivelare se l'utente esiste).
- **Lista IP consentiti** per il pannello (opzionale, nasce spenta).
- **Intestazioni di sicurezza** su ogni risposta (nosniff, Referrer-Policy, X-Frame-Options, Permissions-Policy,
  niente X-Powered-By) e **Content-Security-Policy a nonce** sul sito e sul pannello, con modalità "solo rapporto"
  (endpoint api/csp.php) prima di "applica"; le sorgenti dei tag di analisi entrano solo se attivi nella voce SEO;
  HSTS su https e COOP.
- **Contenuti dell'editor**: sanificatore del testo ricco su parser DOM (niente script, on*, javascript:, data: nei
  link); widget HTML e codice head/body solo amministratore (per i ruoli inferiori diventano testo pulito); CSS
  personalizzato confinato a .ed-ID, senza import/expression/behavior/url() esterni; Attributi su lista stretta.
- **Caricamenti**: controllo del tipo reale e nuova codifica delle immagini; SVG sanificato e servito in sandbox;
  nomi con suffisso casuale.
- **Pacchetti di aggiornamento**: controllo zip slip, dimensione massima, firma HMAC opzionale (tools/pacchetto.py
  --firma); applicazione registrata nel log di sicurezza.
- **Chiamate in uscita** con allowlist di host e blocco degli indirizzi privati (niente SSRF); redirect solo interni;
  LIKE con escape dei jolly nella ricerca; cron-log svuotato solo in POST.
- **Pagina Sicurezza** nel pannello (gruppo Impostazioni): checklist dello stato, impostazioni, registro con filtri,
  sessioni per utente, rapporti CSP. Prove di attacco in tools/sicurezza-prova.js. Documento: docs/sicurezza.md.
- **.htaccess del CMS rifatto**: nega includes, logs, aggiornamenti, sql, docs, tools, test, VERSIONE, *.md; PHP
  spento in uploads/ con SVG in sandbox; esempi Apache e Nginx aggiornati.

## 1.0.0 (26 ottobre 2026)

- Prima versione del CMS come prodotto: nucleo generico (articoli, pagine, editor a sezioni e widget, header e footer come modelli, menu, Stile del sito, SEO e AEO, newsletter con flussi, form, popup, utenti, pacchetti di aggiornamento) separato dal modulo del sito di partenza.
- Installatore da browser in install/: requisiti, database con prefisso a scelta, dati del sito e amministratore, contenuti di partenza.
- Moduli in moduli/<nome>/modulo.php: tipi di contenuto, voci di menu, pagine del pannello, tabelle, passi del cron, widget, filtri ed eventi.
- Il CMS gira nella radice del dominio o in una sottocartella (cms_base()), con le immagini in uploads/.
- Pagina Informazioni nel pannello: versione, moduli, stato del server, tabelle, widget.
- Pacchetto del prodotto e pacchetto di aggiornamento da tools/cms-pacchetto.py.
