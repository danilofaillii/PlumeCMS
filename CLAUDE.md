# plumeCMS

Memoria di progetto. Questo file viene caricato all'avvio di ogni sessione.
Leggilo tutto prima di toccare qualsiasi cosa.

## Cos'è

plumeCMS è un CMS in PHP e MySQL con un editor visuale "come Elementor" al
centro. Si carica nella radice di un sito (o in una sottocartella), si apre
il browser, un installatore in cinque passi chiede database, nome del sito e
amministratore, e il CMS è pronto.

Proprietario: Danilo Failli. Il nome l'ha scelto lui il 26 ottobre 2026,
dopo "Officina" (provvisorio) e "CarbonaraCMS". Lingua del prodotto, dei
documenti e dei messaggi: italiano.

Nasce dal pannello del sito danilofailli.it (il "publisher" in `/new/`), che
il 26 ottobre 2026 è stato diviso in un nucleo generico e un modulo con le
cose di quel sito. **Questo progetto contiene solo il nucleo.** Il modulo
`moduli/danilofailli/` e tutto ciò che riguarda quel sito (articoli, pillar,
social, test del burnout) restano nel repository `danilofailli.it`, che è la
prima installazione di plumeCMS.

## Struttura

```
cms/            il CMS: è esattamente quello che si carica sul server
  index.php     front controller della radice
  includes/     motore: cms.php (nome, prefisso, base URL), config.php, db.php,
                contenuti, media, editor*, design, modelli, menu-nav, seo,
                sicurezza*, versioni, form, popup, newsletter*, posta, aggiornamenti
  admin/        il pannello
  sito/         il sito pubblico (viste, feed, sitemap, assets)
  api/          endpoint pubblici (nl.php firma HMAC, csp.php rapporti)
  install/      l'installatore e gli esempi .htaccess / Nginx
  moduli/       i moduli del sito che installa (vuota nel prodotto); addon-attivi.json = gli addon accesi
  addon/        gli otto addon (2.0.0), moduli del prodotto spenti finché non si accendono da Impostazioni > Addon
  temi/         i temi del sito (motore in includes/temi.php, docs/temi.md)
  docs/         la documentazione del CMS
  VERSIONE, CHANGELOG.md, README.md, INSTALLA.md
tools/          cms-pacchetto.py (costruisce gli zip), pacchetto.py (pacchetti per il
                pannello Aggiornamenti), sicurezza-prova.js (261 prove di attacco; quelle degli addon e dei widget stanno in prove/attacchi-*.js)
prove/          prova-install.js (installazione da zero con Playwright), prova-social.js (social ed email del mattino), prova-fine-articolo.js (il riquadro in fondo agli articoli), prova-su-misura.js (il sito su misura), prova-temi.js, prova-pannello-temi.js,
                prova-temi-partenza.js, prova-carica-temi.js (temi), prova-foto-effetti.js (velo ed effetti delle foto), prova-hero.js (le undici aperture),
                foto-sito.js e quanto-foto.py (schermate prima e dopo, al pixel)
esempi/         tema-lavagna/: il tema d'esempio del manuale dei temi (provato, non entra nello zip)
sito-plumecms/  il sito da cui si scarica plumeCMS, fatto con plumeCMS: costruisci.py scrive il pacchetto
                releases/sito/sito-plumecms-<versione>.zip (dalla 2.1.0 uno solo; --max-kb lo divide di nuovo in parti)
tools/manuali-pdf.js  i due manuali in PDF (releases/manuali/: manuale-uso.pdf, manuale-temi.pdf); tools/manuale-uso-schermate.js le schermate del manuale d'uso;
tools/consegna.py     lo zip unico da dare a Danilo (releases/consegna/)
```

Stato, decisioni, contesto e diario non stanno qui: stanno nel second brain
(`danilofaillii/second-brain`, cartella `plumecms/`), unica copia.

## Documenti

**Regola di Danilo (29 ottobre 2026): con plumeCMS restano solo due documenti**, il manuale d'uso e il manuale dei temi.
Sono gli unici in `cms/docs/`, nello zip, nel sito di plumeCMS e nella pagina Download.

0. `cms/docs/manuale-uso.md`: il manuale d'uso per chi usa plumeCMS (40 capitoli in due parti, la seconda per gli addon; esempi, schermate con i passi
   numerati, checklist). Le schermate le fa `tools/manuale-uso-schermate.js` eseguendo gli esempi su un sito di prova
   (kit Attività, in `/home/studioprova/public_html`): **quando cambia il pannello si rifanno le schermate e si
   ricontrolla il capitolo**. Il PDF è uno solo (dalla 2.1.0) e deve restare sotto 14 MB (`MAX_KB` in `tools/manuali-pdf.js`, alzato da 8 a 12 MB il 1 novembre 2026 e da 12 a 14 MB il
   5 novembre 2026, decisioni di Danilo; oggi 12,3 MB con le schermate JPEG a qualità 55: quando supererà i 14 MB si chiede di nuovo a lui). Nel PDF vanno JPEG: Chromium rimette i PNG senza tavolozza e pesano di più.
   `cms/docs/manuale-temi.md`: il manuale per chi costruisce un tema, con il tema d'esempio Lavagna.
   **Copia nel second brain** (richiesta di Danilo, 1 novembre 2026): i tre manuali (uso, temi, tecnico) stanno anche in
   `second-brain/_comune/manuali-plumecms/` come `plumecms-manuale-*.md`, per tutti i progetti. A ogni versione si rimpiazzano
   (il manuale d'uso senza le immagini: le righe `![...](immagini/...)` diventano "Schermata: ..., nel PDF") e si aggiorna la
   versione nel `LEGGIMI.md` di quella cartella, poi commit e push su `main` del second brain.
1. `docs-sviluppo/` (solo nel repository, mai nello zip): il manuale tecnico (`manuale-tecnico.md`, tutto il CMS letto dal
   codice, con `file:riga`: **quando si cambia il codice si aggiorna anche lui**) e i documenti vecchi (`cms.md`,
   `cms-moduli.md`, `editor-pagine.md`, `sicurezza.md`, `temi.md`, `seo.md`...), superati in alcuni punti: se dicono una
   cosa diversa dal manuale tecnico, vale il manuale (appendice A).

## Come si lavora

- **Costruire il prodotto**: `python3 tools/cms-pacchetto.py` scrive
  `releases/cms/plumecms-<VERSIONE>.zip`. Rifiuta: errori di sintassi PHP
  (controllati con `short_open_tag` acceso), il prefisso `newdf_` scritto nel
  codice fuori da `cms.php` e `db.php`, il dominio danilofailli.it nel codice.
- **Regola di Danilo (27 ottobre 2026): ogni funzionalità nuova aggiorna tre cose, sempre, senza che lo chieda.**
  1. *Il progetto*: codice, prove, `cms/CHANGELOG.md`, `cms/VERSIONE`, il manuale tecnico (e quello dei temi, se tocca i temi),
     il manuale d'uso se cambia quello che vede chi usa il pannello (capitolo, passi e schermate con `tools/manuale-uso-schermate.js`).
  2. *I PDF*: `node tools/manuali-pdf.js`, dopo aver aggiornato i manuali.
  3. *Il sito di plumeCMS* (`sito-plumecms/costruisci.py`): `NOVITA` (regola di Danilo, 1 novembre 2026: nella pagina Scarica **non un changelog** ma un elenco
     breve delle ultime versioni, un titolo per riga (`NOVITA_MOSTRATE`), che non scende sotto il riquadro "Cosa serve" accanto, e
     **mai nomi di aziende o servizi di terzi** nelle novità; lo controlla `prova-sito-plumecms.js`. Fuori dalle novità i nomi restano:
     Importa da WordPress, Google e gli orari, WhatsApp, LinkedIn e Facebook); nella pagina Funzionalità **solo le 10 caratteristiche principali** (regola di Danilo,
     2 novembre 2026: "non può essere una sbrodolata di roba che esce a ogni versione"; stile e grafica vanno bene): una funzione nuova
     si aggiunge dentro la caratteristica a cui appartiene (una riga del suo elenco), mai come blocco nuovo; **unica eccezione** (Danilo, 8 novembre 2026, 2.34.0): il collegamento con le AI ha una fascia sua
     in evidenza (`#ai`) prima delle dieci, con la schermata `ai-collegate.webp`; la schermata solo se rimpiazza
     quella della caratteristica (immagine in `USATE`); home, Domande frequenti o Installazione se la
     funzionalità le cambia; i numeri scritti nei testi (widget, attacchi). Poi le schermate rifatte sul sito di prova e
     `--schermate`, le parti ricostruite, `prove/prova-sito-plumecms.js` con un controllo per il blocco nuovo.
  Dalla 1.2.5 il numero di versione nelle pagine è automatico (`{versione:plumecms-ultima}`): per una versione del CMS
  senza pagine nuove Danilo carica solo i due zip da Download; le parti del sito servono quando cambiano le pagine.
  Ordine dei comandi: `python3 tools/cms-pacchetto.py --aggiornamento`, `node tools/manuali-pdf.js`,
  `python3 sito-plumecms/costruisci.py`, `python3 tools/consegna.py` (con `--uno` se il motore dei pacchetti è cambiato senza voci
  nuove del manifest). A Danilo si consegna il pacchetto unico `releases/consegna/plumecms-<versione>-pacchetto-unico.zip` (regola
  del 31 ottobre, sotto), più lo zip completo del CMS e il PDF del manuale da conservare.
- **Regola di Danilo (31 ottobre 2026): niente più file spezzettati. "Dai prossimi aggiornamenti non serve più che spezzetti i file.
  Me ne dai uno solo che contiene tutte le modifiche."** Ha alzato il limite di caricamento del suo hosting (php.ini). Quindi:
  le modifiche di una versione (codice del CMS ed eventuali pagine del sito di plumeCMS) arrivano in **un solo pacchetto** da caricare
  in Impostazioni > Aggiornamenti; le parti del sito non si dividono più sotto 1900 KB (`costruisci.py --max-kb` alto). Supera le regole
  vecchie "file uno per uno" e "parti sotto 1900 KB" per quello che si consegna a lui. Attenzione alla trappola del motore nuovo: se
  una versione cambia il motore dei pacchetti (`includes/aggiornamenti.php`) e il pacchetto usa voci nuove del manifest, codice e
  pagine non stanno nello stesso pacchetto (`consegna.py` controlla le voci; se il motore cambia solo dentro, `--uno`).
- **Pacchetto di aggiornamento** per un'installazione esistente:
  `python3 tools/cms-pacchetto.py --aggiornamento`. Si carica dal pannello,
  Impostazioni, Aggiornamenti.
- **Versione**: `cms/VERSIONE` (oggi 2.38.0; sempre tre numeri: "beta" va nel CHANGELOG, mai nel nome dello zip, se no la serie "ultima" del Download non lo riconosce) e una voce in `cms/CHANGELOG.md` a ogni
  zip con contenuto diverso. Mai cambiare il contenuto di una versione già data.
- **Prove** prima di dire finito:
  - `php -n -d short_open_tag=1 -l` su ogni file PHP toccato, `node --check` sui js;
  - installazione da zero: `prove/prova-install.js` (vedi `prove/LEGGIMI.md`);
  - attacco: `CMS_DIR=<cartella del CMS installato> node tools/sicurezza-prova.js <base> <utente> <password> [prefisso-pannello]`,
    poi svuotare `<prefisso>sicurezza_tentativi`;
  - le pagine si provano nel browser come un utente (Playwright), non con un `curl` che risponde 200.
- **PHP come sui server veri**: Hostcare (il primo cliente) ha PHP 8.4.27, MySQL 8.0.46
  in modalità stretta, Apache 2.4 con cPanel e ModSecurity. In locale si prova con
  PHP 8.4.27 in Docker: `dockerd`, immagine `php:8.4-cli` con `pdo_mysql` (l'immagine di serie NON lo ha: si compila con
  `docker-php-ext-install pdo_mysql`, fatto il 5 novembre 2026 dal revisore della 2.25.1).
- **Regole di interfaccia** (regola globale di Danilo, 1 novembre 2026): prima di toccare pannello, editor, installatore, addon o sito
  si legge `docs-sviluppo/regole-interfaccia.md` (copia in `second-brain/_comune/regole-interfaccia.md`, valida per tutti i progetti;
  se cambia una, si aggiorna l'altra) e si chiude con la sua checklist. Le prove sono `prove/prova-ux-pannello.js` e `prove/prova-ux-editor.js`.
- **Regola di Danilo (2 novembre 2026): le aperture (hero) nuove hanno l'aspetto e lo stile di quelle che ci sono già.** Proporre
  disegni in un altro stile (una fioreria chiara con caratteri da rivista) è stato respinto: "non c'entrano nulla con le hero che sono già
  nel sito". Ogni proposta o disposizione nuova si disegna nella lingua delle undici di `includes/editor-hero*.php` (foglio
  `sito-plumecms/immagini/aperture.webp`), tema Cosmo: fondo scuro blu notte (`--bg #060a17`) con aloni viola e ciano; occhiello piccolo
  maiuscolo spaziato in ciano (`.occhiello`); titolo in Space Grotesk 700 maiuscolo, con la parte in evidenza sfumata ciano-viola
  (`.hero h1 em`, `--grad`); testo Inter grigio (`--testo-2`); pulsante viola pieno e pulsante a filo ciano (`.btn-viola`, `.btn-filo`);
  la foto è una schermata in una finestra di vetro, in bianco e nero con il velo ciano-viola (`.finestra`); riquadri di vetro con onda e
  barre "Leggero", "Meno peso", "Più tempo" (`.chip`). Testo d'esempio quello del sito: "Il tuo sito, leggero come una piuma."
  Cambia solo la disposizione; con gli altri temi i colori seguono il tema. Proposte del 2 novembre: 15 tavole
  (`https://claude.ai/artifact/D72dbZ333x6ctQLqUk9Uzt`): Danilo ha scelto tutte e 15, fatte nella 2.17.0.
- **Regola di Danilo (3 novembre 2026): niente import da WordPress.** "Funziona malissimo e non ho intenzione di proseguire
  questa cosa. Non facciamo import da wordpress." Tolto nella 2.20.0 con il plugin Ponte, da codice, prove, manuali, sito di
  plumeCMS e CHANGELOG. Non riproporlo.
- **Regola di Danilo (3 novembre 2026): ogni consegna finisce con una riga con data e ora di inizio e di fine del lavoro**, ora
  italiana (Europe/Rome), per esempio "Iniziato: 3 novembre 2026, 16:20. Finito: 3 novembre 2026, 19:05." L'inizio è il momento in cui
  si è partiti con il lavoro di quella versione (il "parti" di Danilo, il primo commit o il lancio dell'agente).
  **Anche nel SAL ogni voce ha data e ora** (regola di Danilo, 3 novembre 2026): Fatto con l'ora in cui è finita, In corso con l'ora
  in cui è cominciata (e l'ultimo segno di vita, per esempio l'ultimo file scritto dall'agente), Da fare con l'ora prevista. Le ore si
  prendono dai commit, dai file e da `TZ=Europe/Rome date`, mai a memoria.
- Niente librerie esterne nel CMS (SortableJS e Quill sono serviti in casa).
- Niente credenziali nel repository né in chat.
- Italiano ovunque, niente trattini lunghi, niente caratteri a 4 byte nei file
  (si scrivono come entità HTML).

## Decisioni prese

Le decisioni complete, con data e perché, stanno nel second brain, `plumecms/decisioni.md`. In breve:

- **L'editor è la copia di Elementor** (25 ottobre 2026): controlli di gruppo,
  schede Stile e Avanzate per ogni elemento, colori e caratteri globali, Theme
  Builder per header e footer, trenta widget di Elementor free e Pro, 252 icone
  disegnate in casa. Ricreato dall'analisi del codice di Elementor Pro 4.3.2.
- **CMS installabile, una sola base di codice** (26 ottobre 2026): nucleo generico
  più moduli, installatore dal browser, prefisso delle tabelle e base URL configurabili.
- **Versioni di ogni modifica dell'editor** in una tabella unica, con confronto
  leggibile, anteprima e ripristino.
- **Un solo menu "Editor"** nel pannello per tutte le impostazioni dell'editor, con esporta e importa in JSON.
- **A prova di attacco prima di pubblicare** (26 ottobre 2026): audit, blindatura,
  tre giri di attacco con agenti nuovi fino a zero brecce sopra il livello basso.
  Il redattore gestisce solo contenuti, media, categorie e tag.
- **Nome: plumeCMS** (26 ottobre 2026).

## Stato al 31 ottobre 2026

Fatto: plumeCMS 2.38.0 (10 novembre, 12:43-14:10): **Pulizia e dati delle immagini** (richieste di Danilo: "pulizia: fa un check di cosa c'è
di eliminabile e propone la cancellazione totale o parziale, indicando il peso per ogni voce, compreso il DB" e "dati per le immagini: alt, descrizione,
altro"). Due agenti in parallelo nelle worktree `/home/user/wt-pulizia` (ramo `pulizia`, 12:46-13:31) e `/home/user/wt-img` (ramo `immagini`, 12:46-13:41),
uniti da me (conflitti in CHANGELOG, manuale tecnico, `prova-sito-plumecms.js`, `sicurezza-prova.js`, NOVITA). **Pulizia** (`includes/pulizia.php`,
`admin/pulizia.php`, Impostazioni > Pulizia, solo admin): file (pacchetti applicati, snapshot tieni N, copie, file messi da parte, avanzi del cambio database,
log, file dei contatti), database (versioni dell'editor tieni N mai pubblicate o con nota, cestino, righe vecchie dei registri senza accessi e blocchi,
tabelle `rip`/`vec` solo del sito, Ottimizza), immagini non usate a richiesta (cerca il percorso in tutte le colonne di testo e nei temi; i "forse usati"
non si propongono), riepilogo, password, resoconto, avviso in dashboard oltre 200 MB; niente si cancella da solo. Manuale tecnico 50, `prove/prova-pulizia.js`
48 (con `pulizia-cli.php`), gruppo 51. **Dati delle immagini** (`includes/media-dati.php`, `admin/includes/media-scheda.php`, `media-scheda.js`): scheda
della foto in Media (alt, titolo, didascalia, descrizione, autore, licenza, fonte, dove è usata), colonne nuove di `media_alt`, Media > Dati delle immagini
(era Senza alt), interruttore "Mostra la didascalia della libreria" in Immagine e Galleria, ImageObject nell'immagine principale del JSON-LD, nome leggibile
al caricamento, AI collegate `list_media` e `propose_media_data` (`addon/ai-collegate/includes/immagini.php`); corretto og:image con la cartella ripetuta
tre volte (`sito_immagine()`). Manuale tecnico 51, `prove/prova-immagini.js` 58, gruppo 52. Prove mie sullo zip (`$S/v20/batteria.sh`, 13:43-14:01): install
38/38 radice e cartella, kit, immagini 58/58 e pulizia 48/48 su MySQL 8 radice e cartella, **MariaDB 10.6 e MySQL 5.7**, strumenti SEO 71/71, AI 90/90,
aggiornamento 2.37.0 -> 2.38.0 13/13 con 0 foto su 20, pulizia sul sito aggiornato 47/48 per un difetto della prova (righe di accesso seminate a 200 giorni
che `sicurezza.php:179` toglie da sé una volta su 200 oltre i 180: seminate a 150) poi 48/48 due volte, attacchi 561/561, pacchetto unico 13/13, sito di
plumeCMS 134/134, zero errori fatali. PDF 13537 KB (tetto 14 MB: margine 800 KB), zip 2684 KB. **Non provato**: hosting vero con cartelle enormi, un
validatore di Google sull'ImageObject, Claude vero sugli strumenti nuovi.

Fatto: plumeCMS 2.37.0 (9 novembre, 13:29-15:40): **Cambia database** in Impostazioni (richiesta di Danilo: "migrare tutto il db su un altro DB e
a fine copia utilizzare il nuovo DB"). Agente nella worktree `/home/user/wt-db`, ramo `cambia-db` (13:30-14:46, in parallelo alla 2.36.0), unito da me alla
2.36.0 (conflitti solo in VERSIONE, CHANGELOG e NOVITA). `includes/cambia-db.php` e `admin/cambia-db.php`: Prova la connessione (3 s, versione, permessi
con tabella e vista di prova, tabelle col prefisso già presenti e spunta "Sostituisci", stesso database riconosciuto dal server, errori in parole, 10 prove
ogni 10 minuti), copia a giri in tabelle di appoggio `rip<hex>_` e una RENAME sola, collazioni del server di arrivo (`copia_collazioni_di(PDO)` in
`copia-sito.php`), conteggio delle righe tabella per tabella, sito in 503 e pannello solo sulla pagina del cambio durante la copia (controllo in
`config.php` e `auth.php`; dopo 10 minuti senza giri torna normale da solo), config salvato in `includes/config.local.prima-<data>.php` (comincia con
404 ed exit) e scritto con file temporaneo e rinomina, controllo dopo il passaggio con ritorno indietro se fallisce, righe da incollare se il config non
si scrive, "Torna al database di prima", password del database nuovo cifrata con CMS_SEGRETO e mai nei log; il database di prima non si cancella mai.
Costanti per le prove `CMS_CAMBIADB_PAUSA`, `_SCADENZA`, `_GIRO`, `_GUASTO`. Manuale d'uso capitolo 29 "Cambiare database" (schermate 27-cambia-db,
27-cambia-db-fatto), tecnico 49, `prove/prova-cambia-db.js` (siti serviti dall'utente non root `cdbprova` in `/srv/cdb-prova`, `$S/db/sito-utente.sh`;
utenti MySQL `cdbutente` e `cdbpoco` sui tre server; lo scenario errori vuole il database `cdb_poco`), gruppo 50 degli attacchi (14). Prove mie sullo zip
(`$S/v19/batteria.sh`, rilanciata dopo il riavvio del contenitore delle 15:12 che aveva spento Docker): install 38/38 radice e cartella, cambia database
verso MariaDB 10.6 e MySQL 5.7 22/22, verso MySQL 8 con tutti gli scenari 52/53 e poi 13/13 sullo scenario errori rifatto con `cdb_poco` (il KO era la
prova senza il database), in cartella 22/22, aggiornamento 2.36.0 -> 2.37.0 13/13 con 1 foto su 20 a 1 livello, zone sul sito aggiornato 81/81, attacchi
538/538, pacchetto unico 13/13, sito di plumeCMS 132/132, zero errori fatali. PDF 13321 KB, zip 2632 KB. **Non provato**: hosting cPanel vero, un MySQL
remoto vero, database grandi (provato con circa 3.800 righe), PHP 8.4.27 sul pannello intero.

Fatto: plumeCMS 2.36.0 (9 novembre, 12:49-14:52): **pagine per zona senza limite** (richiesta di Danilo: "il limite di 200 va tolto, ci sono siti
che lo superano di tanto"; il 200 era una scelta prudente della 2.28.0, registro in un'unica impostazione letta a ogni pagina). Agente nella worktree
`/home/user/wt-zone2`, ramo `zone-senza-limite` (12:50-13:36), riletto, unito e riprovato da me. Registro nella tabella `<prefisso>zone` (`zone_installa()`,
utf8mb4, `pagina` unica), migrazione una volta da `seo_config.zone.elenco` (`zone_migra()`, transazione, `migrato` e `voci` nel JSON; con `voci` 0 nessuna
query e nessuna tabella), `ZONE_MAX` tolto, `ZONE_GIRO_MAX` 100 con il giro a pezzi dopo 15 s (`CMS_ZONE_PEZZO_SECONDI`), pannello Zone con 50 righe per
pagina, ricerca e avviso oltre 30 pubblicate, widget Altre zone con "Quante mostrarne" (0 = tutte, identico a prima) e "Quali mostrare", llms.txt
raggruppato oltre 50, `zone_url()` senza la lettura della home a ogni link (5.099 query diventate 101). Prova di carico con 5.000 zone (manuale tecnico
45.8): pagina per zona 125-217 ms, pagine normali con le stesse query; il collo vero oltre le decine di migliaia di pagine è `sito_contenuti()` del nucleo.
Corretti anche `tools/consegna.py` (la voce `seo` vale nel pacchetto unico quando la versione di prima è almeno la 2.35.0) e `prova-consegna-unica.js`
(voce seo, 8 pagine). Prove mie sullo zip (`$S/v18/batteria.sh` e `rifai.sh`; il primo giro in parallelo con l'agente della 2.37.0 ha avuto timeout di
Chromium per il carico e il disco pieno: rifatte una alla volta): install 38/38 radice e cartella, kit e zone 81/81 su MySQL 8 radice e cartella, **MariaDB
10.6 e MySQL 5.7**, seo-plume 42/42, aggiornamento 2.35.0 -> 2.36.0 13/13 con la migrazione (prima 6/6, dopo 7/7, pannello 3/3) e 2 foto su 20 diverse
(la 404 con l'alone animato e la ricerca a 1 livello: rumore), attacchi 526/526, pacchetto unico 13/13, sito di plumeCMS 131/131, zero errori fatali.
PDF 13241 KB, zip 2604 KB. **Disco del contenitore**: arrivato al 99% (641 MB liberi) con due agenti; tolti siti e database di prova vecchi, 4,3 GB liberi.
**Non provato**: oltre 5.000 zone, un hosting lento vero (il giro a pezzi è provato a 0,2 s), Apache vero.

Fatto: plumeCMS 2.35.0 (8 novembre, 22:32-23:22): **le correzioni SEO e AEO dell'audit di plumecms.it** (audit del 8 novembre, rapporto
`https://claude.ai/artifact/9s9NSqmh15jtfe1FQpP8Hm`, fatto con il connettore AI collegate di plumecms.it: prima prova vera con un client Claude). Agente nella
worktree `/home/user/wt-seo2`, ramo `seo-plume` (22:34-23:10), riletto, unito e riprovato da me. Prodotto: llms.txt calcolato con la sezione Pagine, senza
sezioni vuote né Glossario senza termini (prima linkava /glossario/ in 404); tipo "Programma" (SoftwareApplication) nei Dati per Google in più
(`seo-schema.php`, senza aggregateRating); i dati in più ora escono anche nella home (difetto dalla 2.29.0); colonna `pages.keyword_target`;
`sito_importa_pagina_seo()` (tldr, faq, keyword, seo_schema dai pacchetti); voce `seo` del manifest (`AGG_SEO`, `agg_seo_valida()`: solo titolo.sito e sep,
meta.descrizione e og_immagine, org, persona nome e ruolo, interruttori di schema). Il motore dei pacchetti è cambiato e il sito usa le voci nuove:
**consegna in due pacchetti** (`-1-cms.zip` poi `-2-sito.zip`; con il motore vecchio il sito esce senza i campi nuovi: 121/128, poi 128/128).
Sito di plumeCMS: titoli e descrizioni dell'audit, In breve e parola chiave su ogni pagina (in home anche visibile sotto l'apertura tenuta), domande
nuove (gratis, WordPress, ChatGPT e Claude, Plume CMS, hosting, chi c'è dietro), pagina `/plumecms-wordpress/`, Leggi anche, Domande frequenti nel menu
principale, Programma in home e How-to in Installazione, voce seo (nome "plumeCMS", Organizzazione, Persona Danilo Failli "Creatore di plumeCMS").
Danilo: niente GitHub pubblico (decisione), Bing Webmaster collegato. Prove mie sullo zip (`$S/v17/batteria.sh`, 23:12-23:21): install 38/38 radice e
cartella, robots 14/14, kit 34/34 e 44/44, AI 90/90, navigazione 52/52, stile 53/53, strumenti SEO 71/71 su kit in radice e cartella, seo-plume 42/42 su
kit radice e cartella e sul sito aggiornato (41/42 su un sito senza kit: vuole /servizi/), aggiornamento 2.34.0 -> 2.35.0 13/13 con 0 foto su 20, attacchi
523/523 (gruppo 49), sito di plumeCMS con i due pacchetti su un 2.34.0 132/132, zero errori fatali. `prova-consegna-unica.js` dà 11/13 perché si aspetta
un pacchetto solo (con due pacchetti non è adattata). PDF 13223 KB, zip 2594 KB. **Non provato**: validatori di Google sul JSON-LD, Apache vero; l'alt
della copertina (l'Apertura non ha il campo alt e la hero è tenuta).

Fatto: plumeCMS 2.34.0 (8 novembre, 17:54-18:52): **AI collegate, stile del sito e idee per articoli** (terza fase del connettore; richiesta
di Danilo: "articoli nuovi in base all'argomento, nello stile di quelli già scritti"). Agente nella worktree `/home/user/wt-ai3`, ramo `ai-stile`
(17:55-18:35), riletto, unito e riprovato da me. `cms/addon/ai-collegate/includes/stile.php` e `idee.php`, pagine Stile del sito e Idee per articoli
(piano editoriale per mese, data solo promemoria), tabelle `ai_stile` (10 versioni) e `ai_idee`; strumenti `analyze_style` (misure calcolate dal
server, nessun modello di linguaggio sul server), `save_style_profile`, `get_style_profile`, `suggest_topics`, `propose_article_ideas`,
`list_article_ideas`; `create_draft` e `update_draft` con `idea_id` e gli avvisi `coerenza` verso il profilo (non bloccano); `aic_testo()` toglie
anche il contenuto di script e style. Permessi: profilo con "bozze", idee con "proposte" (niente permesso nuovo). Richiesta di Danilo nella stessa
versione: la fascia "Collega la tua AI" in evidenza in cima a Funzionalità (`#ai`, schermata pulita del menu proposto fatta con `$S/v15/foto-ai.js`,
nomi delle pagine di prova sostituiti nella foto), riga breve negli addon che rimanda lì; eccezione alla regola delle dieci caratteristiche. Manuale
d'uso 41 (schermate 41-stile, 41-idee, 41-piano), tecnico 47.17-47.22, `prove/prova-ai-stile.js` 53 (con 300 articoli), gruppo 48 degli attacchi (15).
Prove mie sullo zip (`$S/v16/batteria.sh`, 18:43-18:51): install 38/38 radice e cartella, robots e articoli 14/14 radice e cartella, kit 34/34 e
44/44, AI 90/90, navigazione 52/52, stile e idee 53/53 su kit in radice e in cartella, aggiornamento 2.33.0 -> 2.34.0 13/13 con 0 foto su 20 e poi
AI 90/90 e stile 53/53, attacchi 516/516, pacchetto unico 13/13, sito di plumeCMS 98/98, zero errori fatali. PDF 13218 KB, zip 2588 KB.
**Non provato**: Claude e ChatGPT veri, misure dello stile su articoli scritti da persone (solo testi seminati), `suggest_topics` con i dati veri
delle Statistiche, Apache vero.

Fatto: plumeCMS 2.33.0 (8 novembre, 16:49-17:55): **AI collegate, navigazione e schede degli articoli** (seconda fase del connettore; richiesta
di Danilo: "analizzi tutte le pagine richiamate dai menu e standardizzi la navigazione; analizza gli articoli e proponi le modifiche SEO").
Agente nella worktree `/home/user/wt-ai2`, ramo `ai-navigazione` (16:52-17:31), riletto, unito e riprovato da me. Strumenti nuovi in
`cms/addon/ai-collegate/includes/navigazione.php` e `revisione.php`: `analyze_navigation` (menu, header e footer, voci rotte, doppioni, etichette,
profondità, orfane, punteggio), `propose_menu` (proposta di tipo `menu`, approvata con `menu_salva()`, ferma se il menu è cambiato; voci solo verso
pubblicati e link sicuri, 200 voci e 3 livelli), `propose_navigation_fix` (Leggi anche e invito ai Contatti; proposta `navigazione`),
`review_article`, `list_articles_to_review`, `propose_article_changes` (fino a 20, colonna `gruppo` in `ai_proposte`, tutte o nessuna; Approva
tutte e Scarta tutte). Pagina Proposte con i due menu affiancati; "Modifica" tolto per i menu; descrizioni degli strumenti in italiano come nella
2.32.0. Più due correzioni segnalate da Danilo: robots.txt con una sola riga `Sitemap:` (`sito/extra.php`; la seconda con `cms_base()` era doppia
in radice e con la cartella due volte in cartella) e il passo 2 di `sito/index.php` rimesso: `/articoli/<slug>/` di un articolo uscito va
all'articolo con un 301 prima dei redirect (una regola `start` su `/articoli/` lo mandava al blog). Manuale d'uso capitolo 41 (schermate 41-menu,
41-scheda), tecnico 47.11-47.16, 16.6 e la tabella dell'instradamento, `prove/prova-ai-navigazione.js` 52 (vuole il kit Attività: su un sito
appena installato senza kit dà KO, non è un difetto), `prove/prova-robots-articoli.js` 14 (con `prove/redirect-cli.php`), gruppo 47 degli attacchi
(15; corretto anche il gruppo 46 in cartella). Prove mie sullo zip (`$S/v11`, `$S/v13`, `$S/v14`): install 38/38 radice e cartella, AI 90/90
radice, cartella, aggiornato e cartella col kit, navigazione 52/52 sito aggiornato col kit e cartella col kit, robots e articoli 9/14 sulla
2.33.0 senza correzioni (difetti riprodotti) e 14/14 radice, cartella e sito aggiornato, aggiornamento 2.32.0 -> 2.33.0 13/13 con 0 foto su 20,
attacchi 501/501 (prima e dopo le correzioni), pacchetto unico 13/13, sito di plumeCMS 95/95, zero errori fatali. PDF 13052 KB, zip 2554 KB.
**Non provato**: Claude e ChatGPT veri sugli strumenti nuovi, Apache vero, la pagina Proposte nei quattro aspetti con `prova-ux-pannello.js`.

Fatto: plumeCMS 2.32.0 (8 novembre, 13:24-16:19): **addon "AI collegate"**, il connettore MCP fra il CMS e qualsiasi AI (richiesta di Danilo:
"le persone, connettendo la loro AI, possano creare pagine, gestire e migliorare la SEO, fare un audit"; decisioni sue: addon, l'AI crea contenuti
completi e li lascia sempre in bozza, mai pubblicare; il resto è "proposta" da approvare). `cms/addon/ai-collegate/` (ai.php, oauth.php, mcp.php,
strumenti.php, proposte.php, foto.php, pagine admin-*): server MCP Streamable HTTP su `/api/mcp` (JSON-RPC, risposte JSON, GET 405), OAuth 2.1 con il CMS
come server di autorizzazione (metadati RFC 9728 e 8414 anche con la cartella, registrazione dinamica, consenso nel pannello solo per l'amministratore,
anche con 2FA, PKCE S256 obbligatorio, codice monouso, token come hash, rinnovo ruotato che chiude la connessione se riusato, revoca), chiavi personali e
OpenAPI per le AI senza MCP; strumenti site_info, list_content, get_content, list_menus, get_seo_settings, list_widgets, site_audit, audit_content,
create_draft, update_draft, propose_change, search_photos, import_photo (Openverse senza chiave, Pexels e Unsplash con chiave; host in elenco;
`CMS_AI_FOTO_PROVA` per le prove), list_proposals, get_stats; pagine "AI collegate" (connessioni, chiavi, registro) e "Proposte dell'AI" (prima e dopo,
Approva, Modifica, Scarta, in gruppo). Nucleo toccato poco: `login.php` (ritorno al consenso solo con `r` di 32 esadecimali), `cms_menu_integra()` con la
chiave `conta`, `media_carica()` con `da_server` solo dalla cartella temporanea, `.htaccess` e esempi con `Authorization` e `/api/mcp` in Nginx. Fatto in
due agenti: il primo fermato a metà dal riavvio del contenitore delle 15:10 (lavoro salvato in `a83fff6`), il secondo ha riletto, corretto e finito;
controllo automatico ogni 40 minuti con `send_later` (Danilo: "non fermarti mai; se ti fermi riprendi da solo"). Manuale d'uso capitolo 41 (3
schermate), tecnico 47 (47.3 elenca cosa verificare con i client veri), `prove/prova-ai-collegate.js` 90, gruppo 46 degli attacchi (26). Prove
dell'agente sullo zip: install 38/38 radice e cartella, AI 90/90 radice, cartella e aggiornato, aggiornamento 2.31.0 -> 2.32.0 13/13 con HTML uguale,
addon tutti 84/84, ux 95/95, telefono 61/61, attacchi 488/488 nuovo e aggiornato, sito 92/92, pacchetto unico 13/13, Apache vero (mod_php) con
`/api/mcp`, `.well-known` e `Authorization`. Prove mie sullo zip unito (`$S/v10/batteria.sh`, 16:11-16:18): install 38/38 radice e cartella, AI 90/90 radice, cartella e sito aggiornato, aggiornamento 2.31.0 -> 2.32.0 13/13 con 1 foto su 20 a 1 livello, attacchi 488/488, zero errori fatali. PDF 12894 KB, zip 2523 KB. **Non provato**: Claude e ChatGPT veri (rete chiusa: documenti ufficiali
non raggiungibili, costruito sulla specifica MCP 2025-06-18 e 2025-11-25), le API vere degli archivi di foto, PHP-FPM, Nginx, un hosting vero.

Fatto: plumeCMS 2.31.0 (6 novembre, 22:48-23:19): **avvisi senza il pannello in vista** (segnalazione di Danilo dal sito di Cucina Creativa:
rispondendo a una richiesta di prenotazione, il cliente riceveva citati il pulsante "Apri nel pannello" e l'indirizzo del pannello in chiaro).
`moduli_email_avviso()` e `moduli_avviso_pannello()` (`includes/moduli.php`, impostazione `avvisi_pulsante_pannello`, **spenta di partenza anche sui
siti aggiornati**) nei cinque avvisi con il Reply-To del visitatore: form, Richiesta di prenotazione, addon Prenotazioni, Vetrina (email al negozio),
Iscrizioni agli eventi; ognuno con `function_exists` e la riserva senza pulsante. Gli avvisi solo interni (recensione, copia altrove) restano col
pulsante. Pannello: Impostazioni > Configurazione email, riquadro `#avvisi` "Gli avvisi che ricevi" con casella, anteprima in iframe sandbox
(srcdoc dal server) e "Manda un avviso di prova". Link nell'oggetto scartato: torna al cliente con "Re:". Manuale d'uso capitoli 8, 18, 25 (schermata
nuova 23-avvisi, rifatta anche 23-email), 32, 33, 35; tecnico 15.3. Prove (zip, PHP 8.3 locale per le prove nel browser, sintassi anche su PHP 8.4.27):
install 38/38 radice e cartella, kit attività 34/34, `prova-avvisi-pannello.js` 36/36 radice, cartella e sito aggiornato, attacchi 462/462 (gruppo 45
nuovo), aggiornamento 2.30.0 -> 2.31.0 13/13 con 20 foto su 20 identiche, ux pannello 159/159 su Impostazioni nei quattro aspetti, sito di plumeCMS
col pacchetto unico 93/93. PDF 12696 KB. Prima che `consegna.py` faccia il pacchetto unico serve lo zip della versione prima in `releases/cms`
(altrimenti escono due pacchetti). **Non provato**: un programma di posta vero (Gmail, Outlook) che cita l'avviso; Apache vero.

Fatto: plumeCMS 2.30.0 (6 novembre, 17:20-18:04): **infografiche grandi quanto vuoi** (segnalazione di Danilo con la schermata: un Iceberg
largo 310 px in una pagina larga 1400, "devo poterle ingrandire quanto voglio; vale per tutte"). Due cause: la scheda `.igx` ha
`max-width:700px` e il disegno `.igs-svg` 600 px; e in una colonna con "Widget allineati: centro" il widget si stringe al contenuto
(riprodotto: 246 px con il disegno a 180). Ora il primo campo di Stile delle trenta `ig_*` (`igx_controlli_testa()`) è `larghezza_ig`, misura
responsive `%`/`px`/`vw` sul contenitore del widget (selettore vuoto); con un valore `igx_larghezza_libera()` mette `igx-libera` sulla scheda e il
blocco 2.30.0 di `sito.css` toglie i due tetti (mai oltre la colonna, al centro). Senza valore niente classe e niente CSS. Il widget generico
"Infografica" (gruppo Contenuto) non ha tetti: si allarga da Avanzate, Larghezza. Manuale d'uso capitolo 8 ("Più grande", cinque passi, senza
schermata nuova), tecnico 41. Prove: `$S/v8/misura.js` 60/60 (sezione normale, Articolo, colonna centrata; 1440 e 390 px; 100%, 1000 px con
100% al telefono, 60%), sullo zip (`$S/v9/batteria.sh`): install 38/38 radice e cartella, infografiche disegnate 47/47, larghezza 60/60, attacchi 459/459, aggiornamento 2.29.1 -> 2.30.0 13/13 con HTML identico a parte il numero dei fogli e il conto alla rovescia (anche sul foglio con tutti i widget), pacchetto unico 13/13 con la hero tenuta, zero errori fatali. PDF 12639 KB.

Fatto: plumeCMS 2.29.1 (6 novembre, 15:30-15:50): **installazione senza iconv** (segnalazione di Danilo con la schermata: la 2.27.0 su
Hostcare si fermava al passo 4 con "Call to undefined function iconv()" subito dopo "amministratore admin"; l'estensione PHP iconv su quel
server non c'è). Quattro punti la chiamavano senza `function_exists`: `contenuti_slug()` (il primo a scattare, dal kit), `form.php` (nomi dei
campi, slug del form), `editor-widget-extra-e.php`; `design.php` aveva il controllo. Ora tutti passano da `cms_ascii()` (`includes/cms.php`: iconv,
altrimenti `Transliterator` di intl, altrimenti tabella delle accentate), chiamata con `function_exists('cms_ascii')` e iconv di riserva. Manuale
tecnico 2.1. Prove con `PHPOPT="-d disable_functions=iconv"` (`$S/v7/batteria.sh`, `$S/v7/fine.sh`): la 2.29.0 riproduce l'errore; la 2.29.1 installa
38/38 radice e cartella, kit attività 34/34, sito su misura 34/34, zone 58/58, strumenti SEO 71/71, attacchi 459/459; con iconv aggiornamento
2.29.0 -> 2.29.1 13/13 con 1 foto su 20 a 1 livello e JSON-LD e sitemap identici, pacchetto unico 13/13 con la hero tenuta; zero errori fatali.
Il sito rimasto a metà si ripara così (provato): file 2.29.1 sopra, cancellare `includes/config.local.php`, rifare l'installazione con lo stesso
database e lo stesso prefisso (le tabelle già fatte restano, l'amministratore del primo tentativo resta con la sua password).

Fatto: plumeCMS 2.29.0 (5 novembre, 13:10-15:10): **gli strumenti SEO**, con la 2.28.0 nella stessa consegna (richiesta di Danilo: "fai le pagine per
zona" più quattro strumenti: tabella SEO di tutto il sito con semafori e modifica in riga; link interni suggeriti nell'editor; schema Prodotto per la
Vetrina, How-to e Video dal pannello; immagini senza alt in un elenco con la correzione in posto). Due agenti in parallelo in due worktree
(`/home/user/wt-zone`, ramo `zone`; `/home/user/wt-seo`, ramo `seo-strumenti`), riletti e uniti da me con tredici file in conflitto (menu e fila delle
schede ora Sedi, Zone, Tabella SEO; `includes/seo.php` e `sito/index.php` con i nodi JSON-LD di zone e schema insieme; manuale tecnico 45 e 46;
`costruisci.py`; gruppi 43 e 44 degli attacchi; capitoli 23z, 23t, 23u delle schermate). **Tabella SEO** (`includes/seo-tabella.php`, `admin/seo-tabella.php`,
`admin/assets/seo-tabella.js`, azione `tabella_salva` di `seo-api.php`; anche il redattore, che è in `AUTH_REDATTORE_PAGINE` e vede solo quella voce del
gruppo SEO e AEO): una riga per contenuto con titolo per Google, descrizione, lunghezze, robots, immagini senza alt, semafori (titolo 30-60, rosso
oltre 70 o doppio; descrizione 70-160, rossa se manca o doppia); salva da `contenuti_salva_rapido()` (che ora accetta `seo_title`, `seo_description`,
`meta_description`, `robots`) e la risposta ricalcola tutte le righe. **Link interni suggeriti** (`includes/seo-link.php`, `seo_link_suggeriti()`:
indice per richiesta da `sito_contenuti_elenco('cerca')`, punti per termine, al massimo 5; voce `link_interni` di `seo_analisi()`, riquadro Collega con
Copia il link e, nell'editor, Inserisci nel Testo selezionato). **Schema** (`includes/seo-schema.php`, colonna `seo_schema` JSON di articles e pages via
`contenuti_schema_colonne()`, riquadro "Dati per Google in più" nella scheda, `seo_schema_applica()` nei nodi del sito; video solo https o file del
sito, miniatura e data obbligatorie, 20 passi; la Vetrina aveva già Product+Offer). **Senza alt** (`includes/media-alt.php`, tabella `media_alt` nata al
primo uso, `media_alt_applica()` in `sito_html()` sulle `<img>` con alt vuoto, zero query con `media_alt_n` a 0; Media > Senza alt con "Nelle pagine" e
"Nella libreria"). Manuale d'uso capitoli 7 e 23 (schermate 23-tabella, 23-collega, 23-schema, 07-senza-alt, rifatte da me sul sito col kit: quelle
dell'agente avevano 26 contenuti di prova), tecnico 46, `prove/prova-seo-strumenti.js` 71, gruppo 44 degli attacchi. **PDF 12638 KB: tetto alzato da 12 a
14 MB, decisione di Danilo del 5 novembre** (`MAX_KB`, `prova-consegna-unica.js`). Prove mie sullo zip (`$S/v2290.sh` e `$S/v6/*.sh`, 14:36-15:08; il
contenitore si è riavviato alle 14:40 a metà batteria: server riaccesi sui siti già installati con `$S/v6/riprendi.sh`, prove rimaste rifatte): kit
attività 34/34 radice e 44/44 cartella, sedi 58/58 radice e cartella, zone 58/58 radice, cartella e sito aggiornato, strumenti SEO 71/71 radice e
cartella, install 38/38 radice e cartella, widget nuovi 40/40, telefono 57/57, addon tutti 74/74, ux pannello 103/103 sulle pagine nuove (Tabella SEO, Media, Zone, Sedi) nei quattro aspetti (la prova completa su un sito col kit senza dati dà 404 sulle pagine che vogliono un flusso o un test: non di questa versione), attacchi 459/459 su un sito nuovo e su uno aggiornato, lanciati uno alla volta (il primo giro
in parallelo aveva 2 KO per difetti della batteria: porta 9296 presa dall'altro giro, tabella `media_alt` non ancora nata; prova corretta), aggiornamento 2.27.0 -> 2.29.0 13/13 con 1 foto su 20 a 1 livello (ricerca al telefono, rumore) e JSON-LD e sitemap di 7
indirizzi identici al byte, sito su misura 34/34, pacchetto unico 13/13 con la hero tenuta, sito di plumeCMS 91/91, zero errori fatali. Zip 2452 KB.
**Non provato**: il JSON-LD di Product, HowTo e VideoObject nel validatore di Google (rete chiusa), la Tabella SEO con migliaia di contenuti, Safari e
Firefox, lettore di schermo vero.

Fatto: plumeCMS 2.28.0 (5 novembre, 13:26-14:04, agente; nella consegna della 2.29.0): **le pagine per zona**, la seconda fase della SEO geografica
(richiesta di Danilo: "è dove la SEO locale si vince"). `includes/zone.php` (registro nel gruppo `zone` di `seo_config`, niente tabelle: `zone_crea_bozze`,
`zone_albero`, `zone_riquadri_guidati` con il segnaposto `[DA SCRIVERE]`, `zone_segnaposto_restanti`, `zone_pubblicabile`, `zone_blocco_pubblicazione`
chiamata da `editor_salva`, `contenuti_salva`, `contenuti_stato_massa`, `editor-api.php` risponde 409; `zone_jsonld_servizio`: Service, `areaServed`,
`provider` = la LocalBusiness della sede o l'Organizzazione, `mainEntity`, briciole Home > servizio > zona; `ZONE_MAX` 200, `ZONE_GIRO_MAX` 30),
`admin/zone.php` (solo amministratore; il redattore scrive e pubblica le bozze con la stessa regola), widget `zone` "Altre zone"
(`includes/editor-widget-zone.php`, `sito/assets/zone.css`, gruppo Dal sito; **112 widget**), `sito-dati.php`: l'anteprima da amministratore mostra una
bozza mai pubblicata. Le bozze nascono con l'apertura, tre riquadri Testo da scrivere a mano e la pubblicazione bloccata da ogni strada finché un
segnaposto resta, anche per l'amministratore: niente pagine vuote tutte uguali. Senza pagine per zona il sito esce identico al byte. Manuale d'uso
capitolo 23 (sezione "Le pagine per zona", schermate 23-zone-modulo, -elenco, -editor), tecnico 45, `prove/prova-zone.js` 58, `prove/zone-cli.php`,
gruppo 43 degli attacchi, sito (novità, riga nel blocco SEO, 112 widget). Il commit dell'agente aveva l'identità git del sito di prova
(`plumecms@localhost`): rifatto con `--reset-author`.

Fatto: plumeCMS 2.27.0 (5 novembre, 9:58-11:15): **le sedi e la SEO geografica** (richiesta di Danilo: "Come gestiamo la SEO geografica?
Voglio implementare anche questa parte"; decisioni sue: più sedi fin dall'inizio, "così lo apriamo ad attività multi sede"; tipo di attività
dall'elenco e, se manca, scritto a mano con l'iniziale maiuscola; le pagine per zona restano per una versione loro, con le bozze guidate).
Agente nella worktree `/home/user/wt-sedi`, ramo `sedi`, riletto e riprovato da me. `includes/sedi.php` (dati nel gruppo `sedi` di
`seo_config`, niente tabelle: `sedi_tutte/salva/valida/normalizza`, `sedi_jsonld*`, `sedi_crea_pagina`, `sedi_geocodifica`, `sedi_da_su_misura`),
`includes/sedi-tipi.php` (48 tipi con la categoria di schema.org, `altro` + `tipo_testo` con `mb_strtoupper` sul primo carattere; verso Google
un tipo scritto a mano è `LocalBusiness`), `admin/sedi.php` (pagina sua, non la decima scheda di seo.php; solo admin; "Cerca sulla mappa" dal
server via `seo-api.php` azione `sedi_cerca`: Nominatim di OpenStreetMap con `seo_http` e il controllo degli host come i webhook,
`CMS_NOMINATIM_URL` e `CMS_NOMINATIM_PROVA` nel config per le prove con `prove/nominatim-finto.php`), `admin/assets/sedi.js` (mini mappa 3x3 di
tessere OSM senza librerie, clic sposta il segnaposto, + e -), JSON-LD: `location` dell'Organizzazione con una LocalBusiness o sottotipo per
sede (`@id`, indirizzo a campi, geo, orari, prezzo, areaServed City/AdministrativeArea, hasMap, sameAs, url, parentOrganization), `mainEntity`
nella pagina della sede, `areaServed` dei servizi dall'unione delle zone, `%citta%` nei titoli; pagina della sede vera dell'editor (apertura,
Dati dell'attività, Mappa, Orari, Testo, Barra contatti "dalla sede"); widget `sedi` e `dati_attivita` (`includes/editor-widget-sedi.php`,
`sito/assets/sedi.css`, gruppo "Dal sito"; 111 widget); Mappa, Orari e Barra contatti con la tendina "Dati dalla sede" (`'accetta'`); sito su
misura: la città diventa la sede `su-misura` principale e la Mappa dei Contatti legge da lì. Senza sedi il sito esce identico al byte (criterio
di accettazione, provato). Manuale d'uso capitolo 23 (sezione "Le sedi e la SEO geografica", schermate 23-sedi-*: la mini mappa è disegnata con
TESSERE FINTE perché la rete della sessione non raggiunge OpenStreetMap, `TESSERE_FINTE=1` nello script), tecnico 44 (nuovo) e 9, 16, 29,
`prove/prova-sedi.js` 58, gruppo 42 degli attacchi (13), sito (novità senza nomi di terzi, riga nel blocco SEO, 111 widget). Prove mie
(`$S/v2270.sh`, 11:05-11:12, sullo zip): kit attività 34/34 radice e 44/44 cartella, sedi 58/58 radice e cartella, install 38/38 radice e
cartella, widget nuovi 40/40, telefono 55/55, addon tutti 74/74, attacchi 433/433 su un sito nuovo e su uno aggiornato, aggiornamento 2.26.0 ->
2.27.0 13/13 con 0 foto diverse su 20 e JSON-LD e sitemap di 7 indirizzi identici al byte, sito su misura 34/34 con la sede "Sede di Bologna"
(Restaurant nel JSON-LD dei Contatti), pacchetto unico 13/13 con la hero tenuta, sito di plumeCMS 89/89, zero errori fatali. Zip 2395 KB,
**PDF 12191 KB su 12288: è l'ultima versione che ci sta; la prossima con una schermata nuova lo supera e si chiede a Danilo prima**.
**Non provato**: Nominatim e le tessere di OpenStreetMap veri (rete chiusa), il JSON-LD nel validatore di Google, `prova-ux-pannello.js`
sulla pagina Sedi, un clic sulla mini mappa da tastiera (le coordinate si scrivono nei campi).

Fatto: plumeCMS 2.26.0 (5 novembre, 9:13-9:40): **Barre a confronto**, la sedicesima infografica disegnata (richiesta di Danilo: portare nel
nucleo il widget `tp_barre` del modulo del sito TascaPiena, `migrazione-plumecms/pacchetto/modulo/tascapiena/widget-infografiche.php` nel
repository `danilofaillii/TascaPiena`; travaso registrato in `second-brain/_ponti.md`). Copiata com'è in `includes/editor-infografiche.php`
(`igs_confronto()`, definizione in `igs_definizioni()`, `IGS_TIPI`): il nome proposto `ig_barre` era già "Barre di avanzamento" (una delle
quattordici in HTML di `editor-widget.php`), quindi l'id è **`ig_confronto`**. 109 widget, 30 infografiche. Per TascaPiena: file del modulo
nuovo (`$S/tp/widget-infografiche.php`, consegnato a Danilo) che con un nucleo 2.26.0 rinomina una volta i widget `tp_barre` salvati in
`ig_confronto` (REPLACE su `struttura` e `struttura_bozza` di pages, articles e modelli, impostazione `tp_barre_rinominato`) e non registra
più `tp_barre`; provato su un sito 2.25.1 aggiornato (la prima richiesta dopo il cambio esce senza il widget, dalla seconda è `ig_confronto`).
Manuale d'uso capitolo 8 (tabella e schermata 08-infografiche con le sedici: `tools/manuale-uso-schermate.js` aveva 15 scritto nel ciclo),
tecnico 41 e 9.7, `prova-infografiche-15.js` 47 (era 44), gruppo 32 degli attacchi a 16, sito (novità, 109 widget, 30 infografiche,
controllo 2.26.0). Prove (`$S/v2260.sh`, 9:25-9:33): install 38/38 radice e cartella dallo zip, infografiche 47/47 radice e cartella (dallo zip) e 47/47 dal repository con la schermata, telefono 54/54, addon tutti 74/74 (sul sito aggiornato col kit: su un sito senza kit la prova cerca `/servizi/` e dà 73/74, non è un difetto), attacchi 420/420 su un sito nuovo e su uno aggiornato (gruppo 32 con le sedici), aggiornamento 2.25.1 -> 2.26.0 13/13 con 1 foto su 20 a 1 livello (ricerca al telefono, rumore) e le quindici di prima intatte, pacchetto unico 13/13 con la hero tenuta, sito di plumeCMS 88/88, modulo di TascaPiena rinominato 1 pagina su 1, zero errori fatali. Zip 2360 KB, PDF 12032 KB (tetto 12288).

Fatto: plumeCMS 2.25.1 (4-5 novembre): **ripristino sicuro della copia** (segnalazione di Danilo del 4 novembre: la copia di un sito
rimessa su un altro dominio e un altro database ha lasciato il sito senza tabelle, "Table ... users doesn't exist" a tutti, con i percorsi del
server a schermo; lui lo ha rimesso in piedi da solo). Causa dimostrata: `copia_ripristina_db()` faceva DROP e poi CREATE tabella per
tabella, e una copia scritta da MySQL 8 porta `COLLATE=utf8mb4_0900_ai_ci` che MariaDB 10.6 e MySQL 5.7 rifiutano (1273): tabella cancellata e
non ricreata, `save_setting('sito_url')` in eccezione, pannello morto perché `auth.php` accendeva `display_errors`. Ora (catena di agenti
`Workflow`: causa, verifica, correzione, tre giri di revisione, worktree `/home/user/wt-ripristino`, ramo `ripristino-sicuro`, riletto e
riprovato da me): tabelle di appoggio `rip<6hex>_<prefisso><tabella>`, parcheggio `vec<6hex>_`, **una sola RENAME TABLE** a più coppie,
`GET_LOCK plume_ripristino_<md5(DB_NAME|DB_PREFIX)>`, marcatore `aggiornamenti/copie/.ripristino-in-corso.json` a coppie [marchio, tabella] con il
prefisso, collazioni e set di caratteri sostituiti con i più vicini (`copia_collazione_vicina()`), INSERT solo letterali, CREATE senza
vincoli o rimandi (REFERENCES, UNION=, TABLESPACE...), **niente istruzioni impilate** (`agg_split_sql()` con apici semplici, doppi e inversi;
`db()` e l'installatore con `MULTI_STATEMENTS` spento: il revisore del giro 2 aveva trovato che una copia confezionata eseguiva un secondo
comando), registro su file `aggiornamenti/copie/ripristino-<data>-<copia>.log` uno per tentativo, `includes/soccorso.php` (pagina 503 del
pannello senza users o settings; i dati del database solo con `aggiornamenti/soccorso.txt` creato dal proprietario, valido 24 ore, mai
l'utente), `includes/errori.php` (eccezioni non gestite: pagina sobria nel pannello con file:riga relativi, 503 "Torno subito" nel sito;
`display_errors` spento anche nel pannello; `CMS_DEBUG` nel config rimette tutto a video), snapshot degli Aggiornamenti sullo stesso motore e
**solo con le tabelle del prefisso**, `agg_colonne_generate()` corretta (`DEFAULT_GENERATED` non è generata: created_at e updated_at si
perdevano nelle copie fatte su MySQL 8), `COPIA_INSERT_MAX` a 1 MB meno 1 KB. Manuale d'uso capitolo 29 (schermata 27-soccorso), tecnico
6.1, 19.4, 19.7-19.9, 22-25. Prove (batteria mia sullo zip, 5 novembre 8:43-9:07, `$S/v2251.sh` e `$S/v1/stream*.sh`): install 38/38 radice e cartella, ripristino sicuro 114/114 su sei siti (A, C, B2 stesso database, D, MariaDB 10.6, MySQL 5.7; il registro su MariaDB dice le 17 collazioni adattate), copia pulita 24/24, attacchi alla copia 36/36, copia 30/30, copia altrove 91/91, tag 57/57 e attacchi 25/25, test 196/196 e attacchi 82/82 radice e cartella, automazioni 96/96, passo da modello 57/57, telefono 56/56, scorri 601/601, aggiornamento 2.25.0 -> 2.25.1 13/13 con 0 foto diverse su 20 (rifatto: 1 foto a 1 livello), addon tutti 74/74, contatti 94/94, segmenti 179/179, attacchi 420/420 su un sito nuovo e su uno aggiornato, pacchetto unico 13/13 con la hero tenuta, sito di plumeCMS 87/87, zero errori fatali nei log. Zip 2359 KB, PDF 12024 KB (tetto 12288: margine 264 KB, la prossima versione con schermate nuove lo supera: chiedere a Danilo). Trappola pagata qui: il filtro dell'altra installazione messo dentro `agg_dump_db()` rompeva `prova-copia-pulita.js` (che con quella funzione simula una copia vecchia): il filtro sta in `agg_crea_snapshot()`, con il parametro `$solo`.
Il contenitore della sessione si è riavviato una volta (23:22) e il limite settimanale ha fermato la catena alle 3 (ripresa alle 6:30 col
"Riprova" di Danilo). Docker: `cmsdb` (MySQL 8.0.46, 3308), `mariadbprova` (MariaDB 10.6, 3309), `mysql57prova` (MySQL 5.7, 3310).
**Non provato**: hosting vero (Aruba, cPanel), phpMyAdmin vero, Apache con il CMS intero, CMS_DEBUG su un server vero.

Fatto: plumeCMS 2.25.0 (4 novembre): il widget **Scorri di lato** (richiesta di Danilo: "come il sito Apple", pagine che scorrono di
lato quando si arriva al widget, poi di nuovo in verticale; cinque proposte `https://claude.ai/artifact/2EErn1pJPESzSKTJm9EQZd`, "mi piacciono
tutti": cinque disposizioni Pellicola, Carte, Titolo fermo, Linea del tempo, Profondità; agente nella worktree `/home/user/wt-scorri`, ramo
`scorri-di-lato`, riletto e riprovato da me). Id `scorri_lato`, `includes/editor-widget-scorri.php`, `sito/assets/scorri.css|js` (classi `sdl-*`),
pagine a campi (da 2 a 12; niente sezioni salvate nelle pagine dell'editor, manuale tecnico 43.1), elenco ripetibile con `max_voci`, frecce
Sposta su e giù e campi con condizione (vale per tutti i widget), meno movimento e senza JavaScript con le pagine una sotto l'altra, tela con le
pagine in fila. 108 widget. Corretto dall'agente anche `tools/sicurezza-prova.js` gruppo 14 (cartella due volte nei siti in cartella). Prove:
install 38/38 radice e cartella, scorri 601/601 radice e cartella, tag 57/57, test 196/196 e attacchi 82/82 radice e cartella, automazioni
96/96, passo da modello 57/57, telefono 56/56, aggiornamento 2.24.0 -> 2.25.0 13/13 con 0 foto diverse su 20, addon tutti 74/74, contatti
94/94, segmenti 179/179, attacchi 420/420 (gruppo 26b), pacchetto unico 13/13 con la hero tenuta, sito di plumeCMS 86/86. Zip 2324 KB, PDF
11900 KB: Danilo vuole il PDF così com'è (4 novembre); quando supererà i 12 MB si chiede a lui prima di consegnare. Le altre schermate del
manuale non mostrano ancora le frecce nuove degli elenchi dell'editor.
**4 novembre**: Danilo ha applicato per errore a danilofailli.it il pacchetto unico della 2.24.0 (con il sito di plumeCMS): menu cambiato.
Rimedio dato: snapshot delle 07:07 "plumeCMS 2.24.0 e il sito di plumeCMS", poi solo `plumecms-<v>-aggiornamento.zip`. danilofailli.it dal 1
novembre è plumeCMS senza moduli e prende gli zip di aggiornamento del prodotto, **mai il pacchetto unico**.

Fatto: plumeCMS 2.24.0 (3-4 novembre): **test a pagine e passi del test a carte con l'anteprima** (richiesta di Danilo; scelta B + D fra
cinque proposte, prototipo `https://claude.ai/artifact/EKQT5RjxR5WuSd4xzN7nD7`; agente nella worktree `/home/user/wt-test-pagine`, ramo
`test-pagine`, riletto e riprovato da me). Modo `una|pagine|tutte` e pagine `[{titolo, domande}]` in `dati`, normalizzati in `test_normalizza()`;
i test vecchi senza modo restano "Una per pagina", identici al byte e alla foto. Sito pagina per pagina con JavaScript, senza tutto in una
pagina con i titoli. Pannello: in tutti e cinque i passi e per i tre tipi carte compatte che si aprono in una finestra, telefono
dell'anteprima a destra (sotto 900 px pulsante "Anteprima" a schermo intero); nel passo 2 la lavagna con le colonne-pagine e il trascinamento,
tendina "Pagina" e "Sposta prima/dopo" senza mouse. Manuale d'uso capitolo 40 riscritto, tecnico 42.2. Prove: install 38/38 radice e
cartella, tag 57/57 e attacchi 25/25, test 196/196 e attacchi del test 82/82 radice e cartella, automazioni 96/96, passo da modello 57/57,
telefono 56/56, aggiornamento 2.23.0 -> 2.24.0 13/13 con 0 foto diverse su 20, addon tutti 74/74, contatti 94/94, segmenti 179/179,
attacchi 411/411, pacchetto unico 13/13 con la hero tenuta, sito di plumeCMS 85/85 (la prova vuole il server con il limite alzato: il
pacchetto del sito pesa 17,6 MB). Zip 2309 KB, PDF 11663 KB (tetto 12 MB: margine di 600 KB). Non provato: trascinamento col dito su un
telefono vero, Safari e Firefox, lettore di schermo vero.

Fatto: plumeCMS 2.23.0 (3 novembre): i **tag dei contatti** e i test più completi (richiesta di Danilo; agente in worktree, ramo `tag-test`,
riletto e riprovato da me). Tag in `includes/newsletter-tag.php` (tabelle `newsletter_tag`, `newsletter_contatti_tag`, `newsletter_tag_attese`,
colonna `moduli.tag`, nate al primo uso), pagina `admin/newsletter-tag.php`, scheda del contatto, azioni di gruppo e filtro dei Contatti,
segmenti (ha / non ha), automazioni (innesco "Riceve un tag", azioni Aggiungi e Togli tag), form (tag a chi compila, con la doppia conferma
alla conferma), importa ed esporta. Addon Test: catalogo di 25 domande sulla persona (`addon/test/includes/catalogo-persona.php`, settore
con le voci ATECO), scelte per ogni test, obbligatorie o facoltative, poste alla fine; risultato breve a schermo e completo per email
(firma della chiave con le risposte sulla persona, `test_chiave_email()`); spunta newsletter con la lista scelta nel pannello; le risposte
sulla persona diventano tag **solo** per chi si iscrive alla newsletter (chi lascia solo l'email riceve il risultato e basta: niente contatto,
niente tag); statistiche per gruppi (colonna `test_risultati.persona`). Prove: install 38/38 radice e cartella, test 141/141 e attacchi del
test 71/71 radice e cartella, tag 57/57 e attacchi ai tag 25/25 radice e cartella (`prova-tag.js` su un sito appena installato, prima di
`prova-test.js`), automazioni 96/96, passo da modello 57/57, telefono 56/56, aggiornamento 2.22.0 -> 2.23.0 13/13 con 0 foto diverse su 20,
addon tutti 74/74, segmenti 179/179, contatti 94/94 (sito aggiornato e seminato), attacchi 411/411, pacchetto unico 13/13 con la hero tenuta.
Zip 2287 KB, PDF 11411 KB.

Fatto: plumeCMS 2.22.0 (3 novembre): addon **Test e questionari** (`cms/addon/test/`, richiesta di Danilo: un motore per i test come
quello del burnout; scelte sue: addon, risposte anonime, una sola versione; agente in worktree, ramo `addon-test`, riletto e riprovato da me).
Tre tipi (punteggio, aree con il grafico, profili), scala, scelta, sì/no, cursore, invertite, pesi, condizionali, fasce in percentuale,
regole E/O, simulatore, statistiche, CSV, tre modelli, email facoltativa con consenso separato e lista, innesco "Completa un test" con il
filtro sul risultato, Riquadro in evidenza verso un test. Indirizzo `/fai-il-test/<nome>/` (`TEST_PERCORSO`): `/test/` è chiuso dal
`.htaccess` di ogni sito. L'invio del test non usa il captcha (funziona senza JavaScript): codice del modulo firmato, 3 secondi minimi,
campo esca, Origin, 20 invii l'ora per impronta HMAC dell'IP. Classi pubbliche `tq-*` (`.test-*` sono di `sito.css`). Manuale d'uso
capitolo 40 (schermate dalle prove, non da `manuale-uso-schermate.js`), tecnico 42, `docs-sviluppo/addon/test.md`. Prove: test 102/102 e
attacchi del test 54/54 in radice e cartella, automazioni 96/96, passo da modello 57/57, addon tutti 74/74 (nove addon, su un sito con il
kit: `/servizi/`), telefono 55/55, install 38/38 radice e cartella, aggiornamento 2.21.0 -> 2.22.0 13/13 con 20 foto uguali, attacchi
411/411, pacchetto unico 13/13 con la hero tenuta. Zip 2257 KB, PDF 11296 KB.

Fatto: plumeCMS 2.21.0 (3 novembre): l'allineamento di ogni parte di ogni widget (richiesta di Danilo: nel Titolo il testo sotto restava a
sinistra, largo 620 px, con occhiello e titolo al centro; "vale per tutti i widget"). `editor_allineamenti_parti()` mette un Allineamento
(`'parte' => true`) dopo ogni Tipografia con selettore (120 parti), `editor_css_allinea_parte()` (text-align, margini, justify-content,
radice ripetuta tre volte), `allineamento_contenuto` nei 37 widget senza allineamento, l'allineamento di tutto il widget vale per le parti
(`editor_css_elemento()`, radice due volte), manuale tecnico 9.6b. **Trappola**: con l'allineamento che il widget aveva già, le parti in
linea (occhiello) non diventano blocco: farlo cambiava l'altezza delle righe (home e Contatti 32 px più basse). Unico cambio voluto sulle
pagine già fatte: nell'Invito all'azione e nel riquadro del test (allineamento di partenza al centro) il testo sotto ora è centrato.
Prove: `prova-allinea.js` 7/7 a 1280 e 390 (ogni parte a destra, centro, sinistra, giustificata, e tutto il widget), install 38/38 radice e
cartella, telefono 53/53, aggiornamento 2.20.0 -> 2.21.0 13/13 con 20 foto uguali, confronto widget per widget (tolto il movimento: contatore,
slider, caroselli, modulo), attacchi 411/411, pacchetto unico 13/13 con la hero tenuta. Zip 2190 KB, PDF 10829 KB.

Fatto: plumeCMS 2.20.0 (3 novembre): tolto l'import da WordPress (regola sopra). Via `includes/importa-wp*.php`, `includes/ponte-wp/`,
`admin/importa-wordpress.php`, la voce del menu, `sito_nascosti_import()`, le prove (`prova-importa-wp.js`, `prova-ponte.js`, il WordPress
di prova), i gruppi di attacchi 19 e 41, il capitolo 31 del manuale d'uso (gli addon ora 31-39) e il 26 del tecnico (numerazione con il
buco: i rimandi del codice ai capitoli dopo restano giusti), la caratteristica del sito (al suo posto "Social ogni mattina", `social.webp`),
la domanda frequente, le novità 2.19.x. `nl_aut_ip_interno()` ha le sue reti interne (prima le prendeva dall'import). L'aggiornamento
cancella i file (`TOLTI`) e la dashboard cancella una volta impostazioni `importa_wp_*` e tabella `importa_wp_voci`. Prove: install 38/38 radice e
cartella, pannello senza WordPress (cron) 13/13 nuovo e aggiornato (la voce 2.0.3 del CHANGELOG diceva "(WordPress)": tolto), telefono
53/53, aggiornamento 2.19.1 -> 2.20.0 13/13 con 20 foto uguali e un import lasciato a metà (dopo: 0 impostazioni, 0 tabelle, 0 file,
pagina 404), attacchi 411/411, reti interne dei webhook 8/8, pacchetto unico 13/13 con la hero cambiata a mano che resta. Zip 2187 KB,
PDF 10827 KB.

Fatto (poi tolto nella 2.20.0): plumeCMS 2.19.1 (3 novembre): l'import regge gli errori di WordPress (il primo sito vero, arketypos.it su Aruba: collegamento con
il ponte riuscito, poi 500). Ponte 1.0.1: risposta in `try` con `register_shutdown_function`, `WP_SANDBOX_SCRAPING` per far tacere il
gestore degli errori fatali di WordPress (il filtro `wp_should_handle_php_error` non vale per i fatali), ultimo errore nella pagina del
plugin, filtri di `rest_post_dispatch` saltati dopo un errore lì (`plume_ponte_senza_filtri`). Il motivo vero su arketypos.it: un'immagine
(id 32) con larghezza e altezza testo vuoto, `string * float` in `wp-includes/media.php` quando la REST calcola `media_details`, anche
nella REST pubblica: l'analisi ora rilegge la pagina dei media senza `media_details`. Plugin 1.0.1 dato a Danilo anche da solo
(`releases/ponte/`, fuori dal repository). Prove: install 38/38 radice e cartella, ponte 57/57 radice e cartella (guasti finti
`plume_prova_guasto`: eccezione, fatale, media), import 75/75, aggiornamento 2.19.0 -> 2.19.1 13/13 con 20 foto uguali, attacchi 434/434,
sito 82/82, pacchetto unico 13/13 con la hero cambiata a mano che resta. Zip 2240 KB, PDF 11087 KB.

Fatto (poi tolto nella 2.20.0): plumeCMS 2.19.0 (2 novembre): il plugin Ponte per l'import da WordPress (richiesta di Danilo: arketypos.it su Aruba dava 401
con la password per le applicazioni anche con le righe nel `.htaccess`). `includes/ponte-wp/plumecms-ponte.php` (plugin WordPress, sola
lettura, POST a `/?plume_ponte=1` firmata HMAC nel corpo, codice `PLUME1-` di 48 ore, blocco dopo 20 firme sbagliate), zip da
`importa-wordpress.php?scarica=ponte`, scelta "Con il plugin Ponte", `importa_wp_ponte()` e `importa_wp_collega_ponte()`, manuale tecnico
26.6. **Attenzione**: WordPress 6.6 risponde `rest_not_logged_in` sia con utente o password sbagliati sia con le credenziali tolte dal
server: da fuori non si distinguono (la prova fatta su arketypos.it con credenziali inventate non dimostrava niente). Ponte non ancora
provato su un hosting vero; il ponte non porta la grafica di Elementor (legge quello che legge l'import). Prove: install 38/38 radice e
cartella, ponte 49/49 radice e cartella (WordPress vero con `WP_TOGLI_AUTH=1` in `router-wp.php`), import con la password 75/75 in
cartella (senza la REST chiusa), telefono 54/54, aggiornamento 2.18.0 -> 2.19.0 13/13 con 20 foto uguali, attacchi 434/434 (gruppo 41),
pacchetto unico 13/13 su un 2.18.0 nuovo e su un 2.18.0 con la home già scritta da un pacchetto: la hero cambiata a mano resta. Le prove
del pacchetto unico contavano `pagine/home.json` senza il `sito/` davanti: corrette. Zip 2238 KB, PDF 11085 KB.

Fatto: plumeCMS 2.18.0 (2 novembre): quattro richieste di Danilo. (1) Il passo Email delle automazioni RICHIAMA il modello (agente, ramo
`passo-modello`): `editor:"modello"` e `modello:<id>` restano nel nodo, `nl_passo_modello()` compone dal modello a ogni invio, oggetto e
anteprima vuoti dal modello (colonna nuova `newsletter_modelli.anteprima`), "Usato in" e conferme in Modelli, passo fermo con registro e
avviso in dashboard se il modello manca, Stacca dal modello, modelli in codice HTML dal pannello, gruppo di attacchi 40. Formato del nodo nel
manuale tecnico. Corretto da me `nl_email_quando()`: dentro l'ora scelta l'email parte subito ("attesa 7 giorni, alle 8" dava 8 giorni).
(2) Funzionalità del sito a 10 caratteristiche (regola sopra). (3) La hero della home resta: `tieni_apertura` in `sito_importa_pagina()`
(solo pagine già scritte da un pacchetto, `sito_pagina_da_pacchetto()`), `costruisci.py --senza-home` per i siti sotto la 2.18.0 (il pacchetto
lo applica il motore vecchio, `sito.php` già caricato): la consegna 2.18.0 è senza home, dalla 2.19.0 la home torna con la hero tenuta.
(4) Menu del pannello più leggibile (`admin.css`, blocco 2.18.0, solo variabili degli aspetti). Prove: install 38/38 radice e cartella,
passo da modello 57/57, automazioni 96/96, campagne 100/100, telefono 54/54, aggiornamento 2.17.1 -> 2.18.0 13/13 con 20 foto uguali e i
passi copiati con la 2.17.1 uguali al byte (9 impronte, colonna `passi` identica), flussi di prima (2.14.0 -> 2.18.0) 18/18, attacchi
430/430, pacchetto unico 13/13 (il controllo delle pagine accetta 6 senza home), hero tenuta (`$S/hh.sh`). Zip 2228 KB, PDF 10976 KB.
Le schermate del manuale fuori dal capitolo delle automazioni hanno ancora il menu di prima.

Fatto: plumeCMS 2.17.1 (2 novembre): segnalazione di Danilo, "devo poter scegliere l'email da inserire in un passaggio dai modelli".
La scelta c'era ma stava dentro "Editor a blocchi" e mostrava solo i modelli attivi a blocchi. Ora il Contenuto del passo Email ha tre
scelte (Testo semplice, Editor a blocchi, Da un modello) con tutti i modelli tranne `optin`, anche in codice HTML e non attivi (segnati);
al salvataggio il modello si copia (`design` o `html`, editor `blocchi` o `html`), con l'oggetto se vuoto. Due difetti trovati dalle prove:
`nl_email_extra()` azzerava `html` quando il grafo si rilegge (l'email usciva in testo semplice) e `nl_email_campagna()` carica
`newsletter-campagne.php` per `nl_camp_html()`. Prove: install 38/38, automazioni 96/96, flussi di prima (sito 2.14.0 -> 2.17.1) 18/18
(la prova vuole flussi seminati su un sito prima della 2.15.0), aggiornamento 2.17.0 -> 2.17.1 13/13 con 20 foto uguali, attacchi 422/422,
pacchetto unico 13/13. Schermate del capitolo 19p rifatte. Due giri caduti per MySQL delle prove ripartito: aspettare `SELECT 1`.

Fatto: plumeCMS 2.17.0 (2 novembre): quindici aperture nuove, ventisei in tutto (richiesta di Danilo, tutte e 15 dalle tavole approvate;
agente, ramo `aperture-15`): `includes/editor-hero-c.php|d.php`, `sito/assets/hero-c.css|d.css`, `hero-c.js` (solo video e prima e dopo,
caricato con il `'segno'` di `editor_asset_pagina()`), campi in più con `'condizione'`, tendina con `'accetta'`, video nei Media
(`media_carica_video()`, `uploads/video/AAAA/MM/`, campo `video` dell'editor, `editor_video_locale()`). Ritocchi chiesti da Danilo: Orizzonte
più visibile, Trittico "Installazione 5 min" (campi Numero grande); Faro più luminoso (io). Corretto da me: il Titolo traforato scriveva la
foto in `url()` senza la cartella (`editor_hero_con_cartella()`). Gruppo di attacchi 39. Prove: install 38/38 radice e cartella, aperture
364/364 con e senza foto e in cartella, campi nuovi 46/46 (il video va in `uploads/video/.../prova.mp4`, come dal pannello, e `VIDEO=` a
`pagina-hero.php`), pulsanti 1570/1570 senza tema, Cosmo e Aria (su un sito col kit: le pagine puntano a `/servizi/`), aggiornamento 2.16.1 ->
2.17.0 13/13 con 20 foto del sito e 22 delle undici aperture identiche, attacchi 422/422, addon 70/70, pacchetto unico 13/13. Foglio delle 26
in `sito-plumecms/foglio-aperture.py` (tre colonne). Zip 2218 KB, PDF 10910 KB. Aperto: Aggiornamenti a 768 px scorre di lato con un
pacchetto applicato (da vedere se c'era già).

Fatto: plumeCMS 2.16.1 (2 novembre): limite di caricamento di almeno 8 MB fra i requisiti (scelta A di Danilo): avviso nell'installatore
(`inst_limite_caricamento()`, non blocca), riga NO in Aggiornamenti (`agg_limite_caricamento()` in `agg_controlli()`), riga in Controlla il
server, INSTALLA, README, manuale d'uso e tecnico, "Cosa serve" del sito, `prova-install.js` con il controllo (38). Il motore dei pacchetti
è cambiato solo dentro: `consegna.py --uno`. Prove: install 38/38 radice e cartella con il limite a 2 MB, controlla il server 21/21,
aggiornamento 2.16.0 -> 2.16.1 13/13 (1 foto su 20 a 1 livello), conteggi uguali, attacchi 412/412, pacchetto unico 13/13. Il primo giro
è caduto per MySQL delle prove spento (Docker): si riaccende con dockerd e `docker start cmsdb`, e al riavvio MySQL ci mette minuti.

Fatto: plumeCMS 2.16.0 (2 novembre): fase 9 del piano Brevo, l'ultima: form a cinque passi e pagine pubbliche (agente, ramo
`fase9-form-brevo`: `admin/form.php` riscritto, `includes/form-brevo.php`, `includes/newsletter-consenso.php`, `admin/newsletter-consenso.php`
(voce Pagine e consenso), `admin/assets/form-campi.js`, `sito/assets/modulo-esterno.css`; pagina ospitata `/modulo/<slug>/`, l'unica con
`frame-ancestors *`; QR con `sicurezza_qr_svg()` letto da `prove/qr-decodifica.py`; gruppi di consenso spenti di partenza, attributo di sistema
TRACCIAMENTO; tabelle `newsletter_gruppi_consenso|contatti_gruppi|pagine_uscita`, colonne nuove in `moduli`, `newsletter_campagne`,
`newsletter_conferme`). Gruppo di attacchi 38. Corretto da me: Statistiche senza tabelle vuote (al telefono scorreva di lato su un sito senza
automazioni). Prove: install 37/37 radice e cartella, form 90/90 radice, cartella e aggiornato, form di prima 25/25 (HTML ed email al byte),
aggiornamento 2.15.0 -> 2.16.0 13/13 con 20 foto uguali, campagne vecchie al byte 15/15, automazioni 89/89 x3, campagne 100/100 (la prova
ora conta solo le campagne senza nome nuove: `contatti-cli.php semina` ne mette due vecchie), contatti, editor, A/B, report, segmenti, form-avviso
13/13, omaggio 27/27, email di conferma 44/44, newsletter 37/37, addon 70/70, attacchi 412/412, telefono 54/54, pacchetto unico 13/13.
Zip 2193 KB, PDF 10837 KB. Con la 2.16.0 il piano in nove fasi è finito.

Fatto: plumeCMS 2.15.0 (2 novembre): fase 8 del piano Brevo, le automazioni (agente, ramo `fase8-automazioni-brevo`:
`includes/newsletter-automazioni.php` nuovo, `newsletter-flussi.php` e la tela riscritti; inneschi, azioni con webhook firmato solo https
e mai verso indirizzi interni, regole, statistiche per passo, Attività e registro; tabelle `newsletter_flussi_registro|numeri`, colonne
`newsletter_flussi.stato|inneschi|tag|rientro|rientro_attesa|uscita` e `newsletter_corsi.giro`; un flusso spento diventa Inattiva). Gruppo
di attacchi 37. Prove: install 37/37 radice e cartella, automazioni 89/89 radice, cartella e aggiornato (4872 contatti: l'innesco degli
inattivi ne fa entrare 200 a giro, la prova gira fino a 60 volte), flussi di prima in corso 18/18 (su un sito loro: con le campagne vecchie
sullo stesso sito le email dei flussi finiscono nella posta dell'altra prova), campagne vecchie al byte 15/15, aggiornamento 2.14.0 -> 2.15.0
13/13 con 20 foto uguali, contatti 94/94, campagne 100/100, editor email 107/107, A/B 65/65, report 72/72, segmenti 179/179, newsletter 37/37,
addon 70/70, attacchi 389/389, pacchetto unico su un sito 2.14.0 13/13. Lo zip del CMS si costruisce prima di `consegna.py`
(`cms-pacchetto.py --aggiornamento` in testa a `$S/v2150.sh`). La prova degli attacchi delle formule ora usa indirizzi nuovi a ogni giro
(quelli cancellati restano nell'impronta e l'importazione li scarta). Zip 2151 KB, PDF 10463 KB.

Fatto: plumeCMS 2.14.0 (2 novembre): fase 7 del piano Brevo, report e statistiche (agente, ramo `fase7-report-brevo`:
`includes/newsletter-report.php`, `admin/newsletter-report.php`, `assets/css/report.css`, statistiche riscritte, colonne `newsletter_eventi.volte`
e `ultimo`, evento `soft`, orario migliore con `newsletter_campagne.orario_migliore|orario_ora`, gruppo di attacchi 36). Tassi sulle consegnate.
Prove: install 37/37 radice e cartella, report 72/72 nuovo e aggiornato (`prova-report.js` vuole il server finto con `rimbalzi`), contatti
94/94, campagne 100/100, editor 107/107, A/B 65/65, aggiornamento 2.13.0 -> 2.14.0 13/13 con 20 foto uguali, campagne vecchie al byte 15/15,
segmenti 179/179, newsletter 37/37, addon 70/70, attacchi 374/374, pacchetto unico su un sito 2.13.0 13/13. Zip 2123 KB, PDF 10350 KB.

Fatto: plumeCMS 2.13.0 (2 novembre): fase 6 del piano Brevo, test A/B (agente, ramo `fase6-ab-brevo`: `includes/newsletter-ab.php`,
tabella `newsletter_ab`, colonna `newsletter_coda.versione`, gruppo di attacchi 35). Corretto da me il blocco `GET_LOCK` dell'invio
(`newsletter_invia_giro()`): ora con `md5(DB_NAME|DB_PREFIX)`, prima due siti sullo stesso MySQL si fermavano a vicenda. Prove: install 37/37
radice e cartella, test A/B 65/65 nuovo e aggiornato, campagne 100/100 radice e cartella, editor email 107/107, aggiornamento 2.12.0 -> 2.13.0
13/13 con 20 foto uguali, campagne vecchie al byte 15/15, contatti 94/94 su un sito pulito (`prova-contatti-brevo.js` va lanciata prima di
`prova-ab.js`: con più di 50 contatti la riga cercata va alla seconda pagina), segmenti 179/179, newsletter 37/37, addon 70/70, attacchi
362/362, pacchetto unico su un sito 2.12.0 13/13. Zip di aggiornamento 2099 KB, PDF 10077 KB.

Fatto: plumeCMS 2.12.0 (2 novembre): fase 5 del piano Brevo (agente, ramo `fase5-editor-email`): editor delle email a blocchi
(`admin/newsletter-design.php`, `assets/newsletter-design.js|css`, `includes/newsletter-design.php` da JSON a HTML per la posta), modelli
(`admin/newsletter-modelli.php`, galleria in `includes/newsletter-modelli/*.json`), sezioni salvate e sincronizzate, icone dei social PNG in
`sito/assets/email/` (da `tools/icone-email.js`). Prove: install 37/37 radice e cartella, editor email 107/107, campagne 100/100 radice e
cartella, aggiornamento 2.11.0 -> 2.12.0 13/13 con 20 foto uguali, campagne vecchie al byte 15/15 (il seme `semina_vecchie` ora mette
nome = oggetto, come il pannello dalla 2.11.0), attacchi 353/353 (gruppo 34), pacchetto unico su un sito 2.11.0 13/13.
**Zip di aggiornamento 2088 KB, sopra i 2 MB predefiniti di PHP**: decisione chiesta a Danilo (requisito 8 MB o due file). Le prove di
aggiornamento vogliono il server con `-d upload_max_filesize=64M` (`PHPOPT` in `$S/addon-nuovo.sh`). PDF 9890 KB.

Fatto: plumeCMS 2.11.0 (2 novembre): fase 4 del piano Brevo (agente, ramo `fase4-campagne-brevo`): campagne a riepilogo
(`includes/newsletter-campagne.php`, `admin/newsletter-campagna.php` riscritta), elenco con viste e azioni, versione web
(`/newsletter-web/`), pagina per uscire con il questionario (`/newsletter-esci/`), centro preferenze (`/newsletter-preferenze/`),
indirizzo dell'azienda nel piede. Corretto da me `nl_url_evento()`: nei siti in cartella pixel e link avevano la cartella due volte.
Prove: install 37/37 radice e cartella, campagne 100/100 radice e cartella (il pixel in cartella si apre), aggiornamento 2.10.0 -> 2.11.0
13/13 con 20 foto uguali e conteggi uguali, campagne vecchie identiche al byte 15/15, contatti 94/94, segmenti 179/179, newsletter 37/37,
addon 70/70, attacchi 335/335 (gruppo 33), pacchetto unico su un sito 2.10.0 13/13. Zip di aggiornamento 2017 KB, PDF 9365 KB.

Fatto: plumeCMS 2.10.0 (2 novembre): fase 3 del piano Brevo, i segmenti (agente, ramo `fase3-segmenti-brevo`: `includes/newsletter-segmenti.php`,
`admin/newsletter-segmenti.php`, `assets/segmenti.js`, Aggiungi filtro, viste, bivi dei flussi sul motore; campagne con segmenti nella fase 4),
e le 15 infografiche disegnate (agente, ramo `infografiche-15`: `includes/editor-infografiche.php`, `sito/assets/infografiche-svg.css`, 107 widget;
su testate chiare il titolo esce nero per il contrasto). Anteprime di Aria, Bottega, Cosmo in WebP (`TOLTI` toglie i PNG): zip di aggiornamento
1986 KB. Gruppi di attacchi 31 (segmenti) e 32 (infografiche). Prove: install 37/37 radice e cartella, segmenti 179/179 nuovo e aggiornato,
infografiche 44/44, importa 85/85, contatti 94/94, aggiornamento 2.9.0 -> 2.10.0 13/13 con 20 foto uguali e conteggi uguali, newsletter 37/37,
addon 70/70, attacchi 316/316, pacchetto unico su un sito 2.9.0 13/13. `prova-sito-plumecms.js` scorre Funzionalità prima di guardare le
immagini (loading=lazy). `prova-pannello-temi.js` vuole prima il tema finto "prova". PDF 8865 KB.
Attenzione: dalla 2.10.0 le versioni hanno due cifre in mezzo; confronti di versione sempre numerici, mai come testo.

Fatto: plumeCMS 2.9.0 (2 novembre): fase 2 del piano Brevo (agente, ramo `fase2-importa-brevo`): Importa contatti in quattro passi
(`admin/newsletter-importa.php`, motore `includes/newsletter-importa.php`, XLSX senza librerie con difese DTD e zip bomba, lavoro a giri dalla
pagina e dal cron), Esporta con la finestra, lista dei test, cartella privata `uploads/contatti-<casuale>/`. Pannello e manuale senza "come
in Brevo" (decisione di Danilo; Brevo resta solo come servizio di invio). Novità del sito in elenco breve. Prove: install 37/37 radice e
cartella, importa 85/85, contatti 94/94 (nuovo e aggiornato), aggiornamento 2.8.0 -> 2.9.0 13/13 (1 foto su 20 a 1 livello), conteggi uguali,
newsletter 37/37 (su un sito aggiornato con esportazioni la pagina Contatti ha due `table.table`: la prova usa `.first()`), addon 70/70,
attacchi 283/283, pacchetto unico su un sito 2.8.0 13/13 con il sito di plumeCMS. Zip di aggiornamento 2019 KB, PDF 8528 KB.

Fatto: plumeCMS 2.8.0 (1 novembre, notte): fase 1 del piano Brevo (`docs-sviluppo/brevo-confronto.md`, richiesta di Danilo: "newsletter
e form esattamente come Brevo"; agente in worktree, ramo `fase1-contatti-brevo`). Contatti (`admin/newsletter-contatti.php`), scheda del
contatto, Attributi (sei tipi, calcolati, globali, `{{ contact.X }}`), liste in cartelle, lista nera con i motivi di Brevo, 5 soft bounce
di fila (messaggi, non tentativi), Elimina definitivamente con l'impronta. Motore `includes/newsletter-contatti.php`, sette tabelle nuove,
nessuna rinominata. Prove in `prove/prova-contatti-brevo.js` (con `MIGRATO=1` su un sito 2.7.0 seminato da `contatti-cli.php semina`).
Fasi dopo: 2.9.0 importazione, 2.10.0 segmenti, 2.11.0 campagne con disiscrizione e preferenze, 2.12.0 editor a blocchi, 2.13.0 A/B,
2.14.0 statistiche, 2.15.0 automazioni, 2.16.0 form e gruppi di consenso. Zip di aggiornamento 1988 KB: oltre la 2.8.0 supera i 2 MB.
Attenzione: `/tmp/manuale-uso-png` ha 173 PNG vecchi che `tools/manuale-uso-schermate.js` con `SOLO_CAPITOLI` ricopia (usare `TMPDIR`).

Fatto: plumeCMS 2.7.0 (1 novembre, sera): l'email di conferma dell'iscrizione modificabile in ogni parte (richiesta di Danilo; agente
in worktree): `includes/newsletter-email.php` (`nl_email_conferma()`), riquadro "Email di conferma" nella scheda del form e in Newsletter >
Impostazioni (impostazione `newsletter_conferma_email`, colonna `moduli.conferma_email`), anteprima e prova, solo admin; senza modifiche
l'email è identica al byte alla 2.6.0 (`prove/email-conferma-cli.php`). Prove: email conferma 44/44 su un sito aggiornato, install 37/37
radice e cartella, aggiornamento 2.6.0 -> 2.7.0 13/13 con le foto uguali (una a 1 livello), addon 70/70, attacchi 260/260 (gruppo 28 nuovo),
pacchetto unico su un sito 2.6.0 13/13 senza errori fatali. PDF del manuale d'uso 7989 KB: vicino al tetto di 8 MB.
Lo stesso giorno: su danilofailli.it la regola `RewriteRule ^new/?(.*)$` del suo `.htaccess` mangiava "new" di /newsletter-conferma/
(pagina 404 dopo il clic nell'email): data a Danilo la regola `^new(?:/(.*))?$`.

Fatto: plumeCMS 2.6.0 (1 novembre): controllo di UX e UI con due agenti (pannello; editor, installatore, addon, sito), scelte di
Danilo su 18 punti (tutti tranne l'editor ridotto al telefono, rimandato), regole globali in `docs-sviluppo/regole-interfaccia.md`
(copia in `second-brain/_comune/`). Titoli dei riquadri in fascia, campi a 3:1, conferme in una finestra del pannello, tabelle a schede
al telefono, Impostazioni a schede (Sito), bozze vuote nel cestino dal cron, Download con i giorni in un riquadro loro e il peso del file
controllato (`data-peso-massimo`, `pannello_invio_troppo_grande()`), avviso del logo coperto dal widget in Stile del sito,
`allow_url_fopen` in Controlla il server (su plumecms.it era spento). Sito: data delle schede e ricerca vuota cambiate apposta.
Prove: install 37/37 radice e cartella, aggiornamento 2.5.0 -> 2.6.0 13/13 (4 foto su 20 diverse, tutte volute: blog e ricerca),
pacchetto unico su un sito 2.5.0 13/13 senza errori fatali, addon 70/70, ux pannello 1234/1234, ux editor 59/59 (senza copia non
installata e pagina /addon-tutti/), telefono 50/50 a 390 e 768, bozze vuote 11/11, download limite 8/8, attacchi 250/250 (senza
FINTO_WP e SEGRETO), sito di plumeCMS 95/95. Manuale d'uso con 173 schermate rifatte, PDF 7840 KB. Le prove che caricano pacchetti
grandi vogliono il server con `-d upload_max_filesize=64M -d post_max_size=80M`: dalla 2.6.0 il browser blocca prima dell'invio un file
oltre il limite. `prova-addon-tutti.js` spegne gli addon alla fine: `prova-ux-editor.js` li vuole accesi.

Fatto: plumeCMS 2.5.0 (1 novembre): otto temi nuovi (richiesta di Danilo: Estate, Inverno, Autunno, Primavera, Giallo 255,255,0,
Rosso, Verde, Blu; agente temi in worktree) con contrasto almeno 4,5 misurato su sette pagine (`prove/prova-temi-nuovi.js` 611/611),
e un audit di tutto il CMS (agente auditor): 16 problemi risolti, quasi tutti emoji nelle tabelle a 3 byte, documento
`docs-sviluppo/audit-2026-11.md` con Gantt e sette punti aperti, `prove/prova-audit-2026-11.js` 34/34. Cosmo, Aria e Bottega hanno
ancora testi sotto 4,5 (Bottega 3,97): non toccati. Le anteprime dei temi nuovi sono WebP: con i PNG lo zip di aggiornamento
pesava 2182 KB, oltre i 2 MB predefiniti di PHP (ora 1908 KB). Prove: install 37/37 radice e cartella, pacchetto unico su un sito 2.4.0 13/13 due volte (dopo la correzione di opcache), aggiornamento 2.4.0 -> 2.5.0
13/13 con 24 foto uguali, addon 70/70 (nuovo e aggiornato), attacchi 261/261, temi nuovi 451/451 (nuovo e aggiornato), audit 34/34
radice e cartella, pannello temi 32/32, motore temi 39/40 (il KO cerca il nome "Bottega di prova" dell'installatore, sito fatto col kit),
service account 8/8, telefono 49/49 a 390 e 768, firma 14/14, indirizzo sito 10/10, sito di plumeCMS 92/92.

Fatto: plumeCMS 2.4.0 (1 novembre): il sito ufficiale passa a **https://plumecms.it** (Danilo ha comprato il dominio; stesso hosting;
il vecchio plumecms.danilofailli.it si chiudeva subito; dal 1 novembre, decisione di Danilo, resta e rimanda a plumecms.it con un 301 in `.htaccess`, cartella vecchia vuota). `VERSIONE_SITO_UFFICIALE` e `FIRMA_URL` cambiati, la frase salvata col
vecchio indirizzo migra da sola, e il riquadro nuovo Impostazioni > Indirizzo del sito (`sito_url` dal pannello, con la prova del file).
I siti fino alla 2.3.2 vanno aggiornati a mano una volta. Foglio dei passi del trasloco in `docs-sviluppo/trasloco-plumecms-it.md`.
Prove: indirizzo sito 10/10 radice e cartella, firma 14/14 (la prova vecchia contava "Installa ora" come pulsante di chiusura: corretta),
versione sito 5/5, install 37/37 radice e cartella, aggiornamento 2.3.2 -> 2.4.0 13/13 con 24 foto uguali, addon 70/70, attacchi 261/261,
service account 8/8, telefono 49/49 a 390 e 768, sito 89/89, pacchetto unico 13/13.

Fatto: plumeCMS 2.3.2 (1 novembre): segnalazione di Danilo, la chiave del service account di Google si salvava ma il riquadro SEO restava
vuoto: `admin-header.php` sovrascriveva `$sa` della pagina con il suo sottomenu. Variabili del menu con prefisso e `unset`. Link
`projectcreate` nel passo 1. Prove: service account 8/8 (4 KO col menu vecchio), install 37/37 radice e cartella, aggiornamento 2.3.1 -> 2.3.2
13/13 con 24 foto uguali, addon 70/70, attacchi 261/261, telefono 49/49 a 390 e 768, sito 89/89, pacchetto unico 13/13.
Danilo ha incollato la chiave privata del service account in chat e ha scelto di tenerla (avvisato del rischio, proposta l'autorizzazione Limitata).

Fatto: plumeCMS 2.3.1 (1 novembre): segnalazione di Danilo, "Scarica l'aggiornamento" dava la 2.2.0 dopo aver caricato la 2.3.0.
Causa: `/scarica/<serie>/` mandava il file con `public, max-age=3600` e il browser ridava la copia vecchia. Ora 302 con `no-store` verso
`/scarica/<versione>/` (`sito/index.php`). `prova-download.js` aggiornata (mancava il file manuale-tecnico dalla 1.6.4, link senza la cartella):
36/36 radice e cartella. Install 37/37, aggiornamento 2.3.0 -> 2.3.1 13/13 con 24 foto uguali, addon 70/70, attacchi 261/261, sito 89/89,
pacchetto unico 13/13. Danilo ha caricato la 2.3.1 ("tutto ok") e ha provato **Installa ora** su un hosting vero: funziona.

Fatto: plumeCMS 2.3.0 (31 ottobre, notte): la versione sempre in alto a sinistra del pannello (`#admin-versione`, al telefono
`.admin-versione-tel`) e, nel banner della dashboard dopo "Visita il sito ufficiale plumeCMS", "Nuova versione disponibile v. x.x.x" con
**Installa ora** (azione `versione_installa`: scarica, controlla, applica con snapshot; solo admin; con moduli del sito resta "Scarica e
prepara", `versione_installabile_qui()`). Prove: installa ora 11/11 (sito 2.2.0 e ufficiale finto 2.3.0), install 37/37 radice e
cartella, aggiornamento 2.2.0 -> 2.3.0 13/13 con 24 foto uguali, addon 70/70, attacchi 261/261, nomi doppi 9/9, telefono 49/49 a 390 e
768, sito di plumeCMS 89/89, pacchetto unico su un sito 2.2.0 13/13. `prova-versione.js` adattata al banner nuovo ma non rilanciata.

Fatto: plumeCMS 2.2.0 (31 ottobre, sera): Scegli dal sito (richiesta di Danilo, agente in worktree rifatto da me): `includes/collegamenti.php`,
`admin/link-api.php`, `admin/assets/scegli-link.js` (`window.pnLink`, `data-link-sito`), filtro `collegamenti_sito` per gli addon, in tutti i
campi link (editor, Quill, popup, form, newsletter, social, SEO, menu, privacy di eventi e prenotazioni); il valore è `/percorso/` (la
cartella la mette `cms_con_cartella()`), nelle email l'indirizzo intero. Il redirect 301 da solo c'è per pagine e articoli pubblicati, non per
le bozze né per i contenuti degli addon. Poi: Controlla il server con il gruppo "Google e IndexNow" (`server_servizi_esterni()`), certificato
HTTPS non valido detto per nome, `email_a` della Richiesta di prenotazione `solo_admin` (`editor_blinda_solo_admin()`), nomi doppi
dell'editor risolti con `'prima'` (anche per dimensioni e misure), PDF del manuale a 7,3 MB (JPEG a qualità 55). Prove: scegli link 80/80 radice
e 81/81 cartella, attacchi link 32/32, solo admin 6/6, nomi doppi 9/9, versione HTTPS 5/5, controlla server 21/21, install 37/37 radice e
cartella, aggiornamento 2.1.0 -> 2.2.0 13/13 (foto uguali tranne il cursore del Titolo animato, che prima non partiva), addon 70/70,
attacchi 261/261, Scegli dai Media 112/112, telefono 72/72 a 390 e 768, hero 670/670.

Fatto: plumeCMS 2.1.0 (31 ottobre): i punti scelti da Danilo dall'elenco del sospeso, con quattro agenti e uno per i widget, tutto
rifatto e provato da me sul codice unito. Consegna delle email (Impostazioni > Consegna delle email, Brevo, SES, Mailgun, rimbalzi,
riscaldamento), prenotazioni dal pannello con .ics, recensioni dopo gli eventi, corsi con l'editor e trascinamento, Google Drive e
Dropbox, "Solo per i membri" dalla scheda e riservati fuori da ricerca, blog, archivi, widget, feed, sitemap (`sito_contenuti_elenco()`),
ripristino pulito, Scegli dai Media dappertutto, cinque widget nuovi (92) e form con Ora e File, manuale d'uso in un PDF (8089 KB, tetto
8 MB), copia e snapshot con le colonne generate (`agg_colonne_generate()`: prima errore 3105). Il backend nuovo l'ha fermato Danilo.
Prove: install 37/37 radice e cartella, aggiornamento 2.0.3 -> 2.1.0 13/13 con 20 foto uguali, addon tutti 70/70, attacchi 261/261,
widget 68/68 (radice e cartella) e 40/40, membri 95/95, telefono 49/49 a 390 e 768, Scegli dai Media 112/112, hero 670/670,
colonne generate 5/5, sito di plumeCMS 87/87. Consegna con `consegna.py --uno`: un pacchetto unico da 14,3 MB (il motore è cambiato
solo nei dump, nessuna voce nuova del manifest).

Fatto: plumeCMS 2.0.3 (31 ottobre): l'immagine per i social (Danilo: su LinkedIn niente immagine, mentre i suoi siti WordPress sullo
stesso hosting vanno, quindi non è l'hosting). Il sito di plumeCMS era ancora senza la 2.0.x e aveva `og:image` con le barre doppie
(`https://plumecms.danilofailli.it//uploads/...`); pagina e immagine arrivano al robot di LinkedIn con 200. Ora barre doppie corrette,
`og:image:secure_url`, `twitter:image`, `prefix` di Open Graph su `<html>`. Non provato contro LinkedIn (la sessione non lo raggiunge).
Aggiornamento 2.0.2 -> 2.0.3 13/13 con 20 foto uguali, install 37/37 radice e cartella, pulsanti 670/670.
Fatto: plumeCMS 2.0.2 (31 ottobre): i link scritti a mano (segnalazione di Danilo: "i pulsanti della hero non funzionano").
`editor_link_sicuro()` scartava in silenzio `laser-coaching`, `www.sito.it`, `sito.it/pagina` e il pulsante puntava a `#`, in tutti i
widget; ora diventano `/laser-coaching` e `https://...`, gli schemi pericolosi restano fuori. `prova-hero-pulsanti.js` 670/670
(11 disposizioni per 6 effetti più i link a mano, 1280 e 390 px, senza tema e con Cosmo, Aria, Bottega in anteprima `?preview=1&tema=`):
nessun pulsante coperto da velo, foto o onda. Attacchi 261/261, install 37/37 radice e cartella, aggiornamento 2.0.1 -> 2.0.2 13/13 con
20 foto uguali. danilofailli.it non si apre dalla sessione (proxy 403): la sua pagina non è stata guardata.
Fatto: plumeCMS 2.0.1 (30 ottobre, notte): il pannello al telefono (richiesta di Danilo). `.admin-layout` a `minmax(0, 1fr)` e
`.admin-main{min-width:0}` (la colonna `1fr` si allargava per l'elemento più largo: 29 pagine su 70 scorrevano di lato), sotto 640 px
form, etichette, filtri, `nav` e schede vanno a capo. `prove/prova-pannello-telefono.js` 81/81 a 390, 768 e 1280 px (menu più venti
pagine interne, otto addon accesi), al computer 12 pagine identiche al pixel; aggiornamento 2.0.0 -> 2.0.1 13/13 con 20 foto del sito
uguali; install 37/37 radice e cartella. Solo `admin.css`: niente parti nuove del sito.
Fatto: plumeCMS 2.0.0 beta (30 ottobre, sera; la 1.11.0 non è uscita, decisione di Danilo: dentro la 2.0.0). Base degli addon
(`includes/addon.php`, `admin/addon.php`, `admin/addon-pagina.php`, `cron_spesso`, manuale tecnico 32, `docs-sviluppo/cms-moduli.md`) e
otto addon scelti da Danilo, fatti da tre agenti in worktree e rifatti da me: Prenotazioni 60, Eventi con iscrizione 45, Recensioni 39
(agente B, attacchi 80), Vetrina 60, Statistiche 32 (agente C, attacchi 94), Area riservata 75, Corsi 58, Copia altrove 39 (agente A,
attacchi 108); `prova-addon-tutti.js` 68/68 su sito nuovo e su sito aggiornato dalla 1.10.0 (salva una persona nelle Prenotazioni:
il difetto delle pagine vuote, trovato dall'agente del manuale). Manuale d'uso con la Parte seconda (capitoli 32-40) in due PDF:
`manuale-uso.pdf` 1889 KB (11 KB di margine: la prossima aggiunta non ci sta) e `manuale-uso-addon.pdf` 1330 KB; sito di plumeCMS in
cinque parti 87/87 (server a 2 MB). Dettaglio in `docs-sviluppo/addon/<nome>.md`.
Correzioni della newsletter (agente, `prova-newsletter-difetti.js` 37/37), file riservati e "Dopo la conferma, dai questo file"
(`prova-omaggio.js` 27/27, attacchi gruppo 27), Scegli dai Media e og:image intero con misure (manuale tecnico 33-34). Attacchi
261/261, install 37/37 radice e cartella dallo zip, aggiornamento 1.10.0 -> 2.0.0 12/13 (il KO vuole un sito di prima della 1.4.0:
riquadri uguali prima e dopo) con 20 foto identiche al pixel. Limiti della beta: niente pagamento con carta, Google Drive e Dropbox,
prenotazione inserita dal pannello, inviti alle recensioni dopo gli eventi; alcune pagine del nucleo (Articoli, SEO, Form, Newsletter,
Media, Download, Utenti) scorrono ancora di lato al telefono (c'era già prima).
1.10.0 (30 ottobre): sette widget per le attività scelti da Danilo (Orari di apertura con "Aperto ora" calcolato
dal server e ricalcolato nel browser nel fuso del sito, Barra contatti fissa sul telefono, Recensioni scritte a mano senza dati per Google,
Prima e dopo, Squadra, Slider sul motore del carosello, Eventi che spariscono passati) in `includes/editor-widget-extra-d.php`; 87 widget.
Dati per Google (LocalBusiness, Event) in `<script type="application/ld+json">` dentro il widget. Manuale d'uso capitolo 8 (foto
08-attivita fatta dopo tutti i capitoli: la pagina di prova prende un numero di pagina), tecnico 31; JPEG del manuale a qualità 25
(PDF 1826 KB). Prove: `prova-widget-nuovi.js` 40/40 (sito nuovo e sito aggiornato), attacchi 238/238 (gruppo 26 con
`prove/widget-cattivi.php`), install 37/37 radice e cartella, aggiornamento 1.9.2 -> 1.10.0 13/13 (sito uguale tranne il conto alla
rovescia), sito di plumeCMS 82/82.
1.9.2 (30 ottobre): sezioni e gruppi del pannello dell'editor più riconoscibili (segnalazione di Danilo):
`details.ed-sez` con intestazione piena e, aperta, bordo e striscia d'accento; `.ed-gruppo` a riquadro con il nome in maiuscoletto
(`.ed-controllo:has(>.ed-gruppo)`), solo `admin/assets/editor/editor.css`. Schermate del manuale rifatte (PDF 1884 KB, al limite: la
prossima volta alleggerire). Prove: install 37/37, aggiornamento 1.9.1 -> 1.9.2 13/13, file del pannello 6/6, pannello guardato in Aria e
Lavagna. Analisi dei widget (agente, 80 widget, 15 proposte) consegnata a Danilo il 30 ottobre: nessuna scelta ancora.
1.9.1 (30 ottobre): la voce Form del menu del pannello spostata dentro "Newsletter e form" (richiesta di Danilo),
fra Iscritti e liste e Invii dei form, solo amministratore (`admin/includes/admin-header.php`). Manuale d'uso capitolo 18 e schermata
01-dashboard. Prove: install 37/37, aggiornamento 1.9.0 -> 1.9.1 13/13, menu controllato nel browser. Pagine del sito di plumeCMS
invariate: niente parti nuove.
1.9.0 (30 ottobre): a chi arriva l'avviso di un form (segnalazione di Danilo: non si poteva scegliere). Colonna
`moduli.avviso_a` (aggiunta anche alle tabelle vecchie da `form_schema()`), casella "Manda l'avviso a" in Form > Dopo l'invio, fino a
5 indirizzi validati (`form_avviso_indirizzi()`), un'email per destinatario; vuota = `posta_notifica_email()` come prima. Manuale d'uso
capitolo 18 (schermata 17-avviso), tecnico 15.1. Prove: `prova-form-avviso.js` 13/13 (sito nuovo e sito aggiornato), attacchi 233/233
(gruppo 25), install 37/37 radice e cartella, aggiornamento 1.8.0 -> 1.9.0 13/13 (sito uguale tranne il conto alla rovescia), sito di
plumeCMS 81/81. PDF del manuale d'uso 1877 KB: vicino al limite di 1900.
1.8.0 (29 ottobre): la frase in fondo al sito "Un nuovo sito plumeCMS" con il link a `https://plumecms.danilofailli.it`
(richiesta di Danilo), modificabile in Impostazioni > Sito pubblico (`includes/firma.php`, impostazione `firma_sito`: testo, indirizzo,
acceso); banner "Visita il sito ufficiale plumeCMS" in cima alla dashboard, sempre. `FIRMA_URL` ripete `VERSIONE_SITO_UFFICIALE`: se cambia
uno, cambia l'altro. Manuale d'uso capitoli 1 e 15, tecnico 30. Prove: `prova-firma.js` 14/14 (radice e cartella), attacchi 249/249
(gruppo 24), install 37/37 radice e cartella (la prova ammette ora `plumecms.danilofailli.it` nella home), aggiornamento 1.7.0 -> 1.8.0
13/13 (pagine uguali tranne la riga nuova), sito di plumeCMS 80/80.
1.7.0 (29 ottobre): il sito su misura (richiesta di Danilo, strada A senza intelligenza artificiale):
carta "Su misura" al passo 4 dell'installatore con quattro domande (attività fra dieci, città, fino a sei servizi, pagine), motore
`includes/sito-su-misura.php` che compone il kit dalle risposte, foto finte SVG in `uploads/segnaposto/`, Media > Da sostituire con
Sostituisci (solo admin, sostituisce l'indirizzo in pagine, articoli e modelli), voce dei Primi passi, `admin/sito-su-misura.php` per
ricomporre dalla dashboard finché il tipo di sito è in prova. Manuale d'uso capitolo 2 (e 3, 7), tecnico capitolo 29; JPEG del manuale a
qualità 28 (PDF 1838 KB). `media_carica()` rifiuta un'immagine non riscrivibile che contiene codice (`media_ha_codice()`).
Prove: `prova-su-misura.js` 34/34, attacchi 242/242 (gruppi 23 e 23b, su un sito su misura in prova), install radice e cartella 37/37,
kit 34/34 e 44/44, tipo di sito 39/39, primi passi 19/19, aggiornamento 1.6.4 -> 1.7.0 13/13 (sito uguale tranne il conto alla
rovescia, poi ricomposto su misura dal pannello), sito di plumeCMS in quattro parti 78/78. Attacchi e regressioni fatti da un agente
e rifatti a mano (su misura, attacchi, install, aggiornamento, sito).
1.6.4 (29 ottobre): solo due documenti nel prodotto (decisione di Danilo): manuale tecnico e documenti vecchi
spostati in `docs-sviluppo/`, fuori dallo zip; sito di plumeCMS con due manuali (Documentazione, Download, piede); pannello
(Informazioni, Temi), README e INSTALLA rimandano ai due manuali. L'aggiornamento toglie dai siti i 15 documenti vecchi
(`TOLTI` in `cms-pacchetto.py`), la parte 1 del sito toglie `manuale-tecnico.pdf` dal Download. Il sito ora è in quattro parti.
Prove: install 37/37, aggiornamento 1.6.3 -> 1.6.4 13/13 (in `docs/` restano i due manuali), sito di plumeCMS 77/77 (il manuale
tecnico dà 404).
1.6.3 (28 ottobre, notte): due "Invito all'azione" affiancati alti uguali (segnalazione di Danilo sulla pagina
Scarica, già su plumecms.danilofailli.it): `widget.css`, un Invito da solo nella colonna la riempie. Aggiornamento 1.6.2 -> 1.6.3
13/13, foto del sito uguali tranne il conto alla rovescia, schede 321/321 px.
1.6.2 (28 ottobre, sera): l'indirizzo del sito ufficiale non si scrive più nel pannello (decisione di Danilo:
"non inseriranno più manualmente il dominio, gli verrà passato dagli aggiornamenti"): via il campo della 1.3.1 e `versione_sito_salva()`,
l'impostazione `versione_sito` vecchia si ignora, `CMS_AGGIORNAMENTI_URL` resta solo per le prove. `prova-versione-sito.js` riscritta
(5 controlli, con `prove/settings-cli.php`); `prova-versione.js` e `finto-ufficiale.php` non hanno più le versioni scritte dentro
(28/28 sulla 1.6.2). Schermata 26 del manuale d'uso rifatta. Prove: install 37/37, aggiornamento 1.6.1 -> 1.6.2 13/13 (anche con un
indirizzo salvato a mano, ignorato) e 1.6.0 -> 1.6.2 13/13, sito di plumeCMS 81/81.
1.6.1 (28 ottobre, sera): il sito ufficiale diventa `https://plumecms.danilofailli.it` (decisione di Danilo:
trasferire il sito da duhtron.it/test a un sottodominio suo). `VERSIONE_SITO_UFFICIALE` cambiata; il costruttore rifiuta ancora
`danilofailli.it` nel nucleo ma ammette il sottodominio. Il trasloco si fa con Copia del sito: provato qui (sito in `/test` con
le sei parti, copia ripristinata su un'installazione nuova in radice, 13 pagine uguali, download ok). Prove 1.6.1: install
37/37, versione-sito 7/7, aggiornamento 1.6.0 -> 1.6.1 13/13.
1.6.0 (28 ottobre): Articoli > linguetta "Aggiungi sempre alla fine" (richiesta di Danilo): un riquadro
ombreggiato composto con l'editor visuale che esce in fondo a ogni articolo. Modello di tipo `fine` (`includes/modelli.php`,
uno solo, acceso quando è pubblicato), pagina `admin/fine-articolo.php`, `vista_fine_articolo()` e `vista_chiusura_article()`
in `sito/viste.php` (dentro `<article class="corpo">`, fuori per gli articoli a tutta pagina), `.fine-articolo` in `sito.css`
(i link dell'articolo non colorano più i widget del riquadro). Anteprime dei modelli in bozza solo all'amministratore
(`modelli_anteprima_admin()`, anche header e footer). Corretti il testo d'esempio del kit vuoto (chiave `contenuto`) e tre
emoji scritte nei file. Manuale d'uso capitolo 16 (31 capitoli), tecnico capitolo 28. Prove: install radice e cartella 37/37,
`prova-fine-articolo.js` 41/41 (38 senza l'articolo largo), attacchi 201/201 (gruppo 22), social 28/28, aggiornamento
1.5.0 -> 1.6.0 13/13 (sito uguale tranne il conto alla rovescia), aspetti 56/56, sito di plumeCMS in sei parti 81/81, file del
pannello 6/6. Lavoro fatto con due agenti (prove, manuali) e controllato rifacendo le prove principali.
1.5.0 (28 ottobre): email del mattino "Oggi sul sito" e testi social del giorno portati dal modulo di
danilofailli.it nel CMS, per qualunque sito (`includes/social.php`, `admin/social.php`, riquadro Social della scheda, passo
del cron; tabelle `social_contenuti` e `social_programmati`; immagini 1200x630 e 1080x1350 con GD; i dati vecchi del
modulo, `articles.social_summary` e `social_pillar`, entrano da soli una volta). Capitolo 20 del manuale d'uso, 27 del
tecnico. Colori dei canali scuriti per il contrasto (4,5 a 1). Prove: install radice e cartella 37/37, `prova-social.js`
28/28, attacchi 177/177 (gruppo 21, Social), aggiornamento 1.4.2 -> 1.5.0 13/13 su tre siti (sito uguale al pixel tranne
il conto alla rovescia), aspetti 56/56, sito di plumeCMS in cinque parti 76/76, file del pannello 6/6.
**Da fare quando si porta danilofailli.it alla 1.5.0**: togliere dal suo modulo i passi del cron "email del mattino" e
"social del pillar" (altrimenti le email arrivano due volte) e rimettere hashtag e link del laser in Social > Impostazioni.
1.4.2 (28 ottobre): il manuale d'uso (richiesta di Danilo: "deve spiegare come fare qualsiasi cosa con il
CMS, con esempi pratici, immagini e checklist precise"), e quello che lo script delle schermate ha trovato eseguendo gli
esempi: articolo scritto nell'editor e pubblicato dalla scheda usciva senza testo, titolo dell'articolo non aggiornato nella
tela, campi senza stile in Menu, Form (etichetta larga pochi pixel) e Utenti, "testi social" nella pagina Email, accenti
mancanti in 54 file. Prove: install radice e cartella 37/37, attacchi 163/163, aggiornamento 1.4.1 -> 1.4.2 13/13 (sito
uguale tranne "1 minuto di lettura" e il conto alla rovescia), sito di plumeCMS in cinque parti 74/74, file del pannello 6/6.
1.4.1: fogli e script del pannello con la data nell'indirizzo (gli aspetti non cambiavano sul primo sito aggiornato:
memoria del browser), `prova-file-pannello.js`. 1.4.0 (28 ottobre): import da WordPress (`includes/importa-wp*.php`, `admin/importa-wordpress.php`: report, scelta,
import a giri, prova, conferma o annulla; password per le applicazioni, protezione SSRF), tipo di sito in prova fino a Definitivo
(`admin/tipo-sito.php`, solo installazioni nuove), quattro aspetti del pannello per utente (Aria predefinito, `users.pannello_aspetto`),
Primi passi nella dashboard, Controlla il server, foto leggere (WebP, `includes/foto.php`), favicon di partenza, Log cron in parole
chiare (`includes/cron.php`), riquadri con la posizione salvata (`'prima'=>'posizione'`). 163 attacchi respinti (2 saltati: burnout,
newsletter firmata senza `SEGRETO`); install radice e cartella 37/37; import contro un WordPress 6.6.2 vero 76/76; aggiornamento
1.3.1 -> 1.4.0 12/12; sito di plumeCMS in quattro parti 69/69. 1.3.1: l'indirizzo del sito ufficiale è un parametro (Aggiornamenti, impostazione `versione_sito`; `CMS_AGGIORNAMENTI_URL`
nel config vince); `prova-versione-sito.js` 7 su 7. 1.3.0 (notte del 27 ottobre, un agente con il ciclo PDCA): kit di partenza nell'installatore (`includes/kit-partenza.php`,
`includes/kit/`), 30 sezioni pronte nell'editor (`includes/sezioni-pronte*.php`), "c'è una versione nuova" (`includes/versione-nuova.php`,
`api/versione.php`), copia del sito (`includes/copia-sito.php`, Impostazioni > Copia del sito); prove `prova-kit.js`, `prova-sezioni.js`,
`prova-copia.js`, `prova-versione.js`; 97 attacchi respinti. 1.2.8: licenza GNU GPL 3 o successiva (`LICENSE`); tolto dal prodotto tutto ciò che era di danilofailli.it
(moduli ebook e Laser, CTA, box del burnout, aforismi come tipo fisso, testi d'esempio, glossario PNL, mittente); agganci
`moduli_disponibili`, `modulo_html`, `pagina_moduli`, `sito_percorso`; corretti captcha nella radice, Contatti con il modulo laser,
pubblicazione di notte. Il sito di plumeCMS ha la piuma disegnata in copertina e "Installalo in 5 minuti" con le schermate vere. 1.2.7: margine di Centrata, Copertina, Rivista e Scheda; la home del sito di plumeCMS apre con la Copertina (scelta di Danilo). 1.2.6: tendina Disposizione dell'Apertura, dieci disposizioni nuove più la classica (identica al pixel
alla 1.2.5), `prove/prova-hero.js` 154 su 154 con base, Cosmo, Aria e Bottega, anche senza foto. 1.2.5: serie nel download manager (`/scarica/<nome>-ultima/`, `{versione:<serie>}` nei testi), il sito di
plumeCMS segue da solo l'ultima versione caricata; messaggi chiari per i temi con PHP. 1.2.4: download manager (pannello Download, widget Download, conteggio senza robot; `prove/prova-download.js`
27 su 27). 1.2.3: manuali in PDF, `/scarica/<nome>/`, pacchetti con pagine, stile e impostazioni, il sito di plumeCMS
(`sito-plumecms/`, provato applicandolo dal pannello: `prove/prova-sito-plumecms.js` 36 su 36, quattro parti, server limitato a 2 MB), pannello Revisioni che si
chiude, sfondo colore globale delle sezioni. 1.2.2: sezione Foto di Apertura e Presentazione (trattamento, colori e intensità del velo, cinque effetti
animati) e interruttore dell'onda; provata con `prove/prova-foto-effetti.js` (20 su 20) e foto identiche al pixel con i
valori di partenza. 1.2.1: manuale tecnico e manuale dei temi.
plumeCMS 1.2.0 con i temi (Editor > Temi, `docs/temi.md`): tre temi di partenza (Cosmo, Aria,
Bottega), temi con PHP protetti, caricamento e download da zip. Installazione provata da zero in radice e in
sottocartella; aggiornamento 1.1.9 -> 1.2.0 dal pannello provato con sito identico al pixel prima e dopo;
78 prove di attacco respinte (la 79esima è del modulo danilofailli). Le prove del CMS girano su PHP 8.3 con
MySQL 8.0: su PHP 8.4.27 sono provate la sintassi di ogni file e i `.htaccess` su Apache, non il CMS intero
(senza `pdo_mysql` nell'immagine, e i pacchetti Debian bloccati dalla rete della sessione).
Prima installazione vera su `https://duhtron.it/test` (cartella), funzionante con la 1.1.9: da aggiornare alla 1.3.1.
Dal 27 ottobre plumeCMS vive nel suo progetto e repository (`SPOSTARE.md`).

Aperto:
- `CMS_PREFISSO` ha come valore predefinito `newdf_` perché danilofailli.it non lo
  scrive nel suo config. L'installatore lo scrive sempre, quindi le installazioni
  nuove non ne risentono. Se un giorno si cambia il predefinito, prima va aggiunto
  `define('CMS_PREFISSO', 'newdf_');` al `config.local.php` di danilofailli.it;
- Google Search Console e IndexNow su un server vero: dalla 2.2.0 li prova Impostazioni > Controlla il server (gruppo "Google e
  IndexNow": il service account legge le proprietà, il file della chiave si legge da fuori, api.indexnow.org risponde). Da fare
  sul sito di Danilo con i suoi dati;
- **indirizzo del sito ufficiale** per "c'è una versione nuova": è solo `VERSIONE_SITO_UFFICIALE` (`includes/versione-nuova.php`),
  dalla 2.4.0 `https://plumecms.it` (prima `https://plumecms.danilofailli.it`), nessun campo nel pannello (decisione di Danilo);
- "c'è una versione nuova" in HTTPS: dalla 2.2.0 provato con un certificato vero firmato da un'autorità di prova
  (`prove/prova-versione-https.py`: valido, autorità sconosciuta, altro nome, scaduto); manca la prova contro plumecms.danilofailli.it;
- Apache, SMTP vero e Imagick non provati per la 1.4.0;
- `tools/` e `prove/` hanno ancora riferimenti a danilofailli.it (strumenti di sviluppo, non vanno nello zip): `--sito`, la prova del burnout;
- i temi a danilofailli.it arrivano solo con un pacchetto `--sito` dal suo repository (mai lo zip del prodotto).

## Rapporto con danilofailli.it

**Regola di Danilo (28 ottobre 2026): non chiedergli più nulla di danilofailli.it.** Se servono informazioni, le dà lui.
**Regola di Danilo (31 ottobre 2026), ripetuta più volte: "devi lasciar stare danilofailli.it. Se mi serve, te lo chiedo io."**
Non proporre controlli, pacchetti, aggiornamenti o cose da fare su danilofailli.it, non metterlo negli elenchi di cose in sospeso,
non nominarlo nelle risposte. Se ne parla solo quando è Danilo a chiederlo.
Il 28 ottobre ha detto di aver già aggiornato tutto su danilofailli.it.

- danilofailli.it usa plumeCMS con il modulo `moduli/danilofailli/`. **Dal 28 ottobre 2026 gira dalla radice** (detto da Danilo il
  31 ottobre: pacchetti 18 e 18-b del suo repository): pannello in `danilofailli.it/admin/`, `/new/` e `/new/admin/` rimandano agli
  indirizzi senza `/new`, la cartella `new/` resta sul server solo per i rimandi. Il `new/` nei manifesti dei pacchetti è la cartella
  del CMS dovunque sia (`includes/aggiornamenti.php`), non la cartella `/new/` del sito.
- **28 ottobre 2026, sera**: Danilo ha applicato lo zip di aggiornamento del prodotto 1.6.0 a danilofailli.it e il pannello
  lo ha preso. (Quello che segue era vero prima del trasloco nella radice, ora superato: pagine servite da WordPress e `/new/`
  statico.) Rischio: email del mattino
  doppie (CMS e modulo); pagine del modulo nel pannello da controllare. Consigliato a Danilo: spegnere le due email in
  Social > Impostazioni finché il modulo non è portato alla 1.6.0; se il pannello si rompe, Aggiornamenti > Snapshot > Ripristina.
- Le versioni nuove di plumeCMS arrivano a danilofailli.it come pacchetti del suo
  pannello Aggiornamenti, costruiti nel suo repository con
  `tools/cms-pacchetto.py --sito <commit> <nome>` (contengono anche il modulo).
- **Lo zip di aggiornamento del prodotto non va mai applicato a danilofailli.it**: non
  contiene il modulo e romperebbe le sue pagine.
- **Nemmeno il pacchetto del sito di plumeCMS** (`sito-plumecms-*.zip`): sostituisce home, stile, header, footer e menu.
  Va solo sul sito da cui si scarica plumeCMS.
- Una correzione fatta qui va riportata anche lì, e viceversa, finché i due repository
  non condividono il nucleo in un modo automatico.

## Trappole già incontrate

Errori veri, già pagati. Non ripeterli.

| Trappola | Cosa succede |
|---|---|
| `<?xml` (o qualunque `<?`) scritto come testo in un file PHP | con `short_open_tag` acceso, il predefinito di PHP e di Hostcare, diventa codice: errore di sintassi e pagina in 500. Ubuntu lo spegne, quindi in locale non si vede. Si scrive `&lt;?`. Il costruttore ora controlla la sintassi con `short_open_tag` acceso |
| Caratteri a 4 byte (emoji) nei dati | colonne utf8 a 3 byte: in modalità stretta MySQL rifiuta (1366), fuori da quella taglia il testo in silenzio. Nei nomi e nei JSON si salvano come entità o `\uXXXX` |
| Database in modalità stretta | `db()` imposta una modalità SQL esplicita; ogni tabella nuova si crea con `*_installa()` e si prova anche con `sql_mode` stretto |
| Apostrofo dentro un commento `/* */` di un file SQL di pacchetto | lo spezzatore del pannello lo legge come inizio di stringa e l'SQL fallisce (il pannello ripristina). Niente apostrofi nei commenti SQL |
| Pacchetto che porta un motore nuovo e usa insieme le sue voci nuove | lo applica il motore vecchio, che salta o rifiuta ciò che non conosce. Due pacchetti: prima il codice, poi il resto |
| `.htaccess` in un pacchetto | i motori fino alla 1.1.0 accettano solo `root/.htaccess`; `new/.htaccess` e `uploads/.htaccess` solo con il motore nuovo |
| `.php` nella radice dentro un pacchetto | dalla 1.1.1 il pannello lo rifiuta senza firma HMAC o senza la "finestra di un'ora" aperta da Sicurezza |
| `agg_registra_pacchetto()` sul file originale | **sposta** lo zip: si passa sempre una copia |
| Prove lanciate subito dopo `sicurezza-prova.js` | la forza bruta blocca 127.0.0.1 e tutto il resto fallisce al login: prima si svuota `<prefisso>sicurezza_tentativi` |
| Le prove nel browser lasciano dati | `editor.php?nuova=1` crea una bozza vera; una prova interrotta lascia i suoi modelli. Pulizia prima e dopo ogni batteria |
| Una pagina del pannello che risponde 200 | non prova che funzioni: un errore fatale può stare dentro la pagina. Solo le prove nel browser contano |
| `display_errors` acceso sul server | una deprecazione stampata prima di `header()` rompe i redirect. Fuori dal pannello gli errori vanno nel log, nel pannello l'output è bufferizzato |
| ModSecurity sui POST con HTML | li blocca: i campi con HTML viaggiano in base64 (`campi-protetti.js`) e `auth.php` li decodifica |
| Un editor che si inizializza copiando sé stesso nella casella di testo | la scheda Visuale vuota ha sovrascritto due testi. La sincronizzazione parte solo da una scheda aperta e visibile |
| `boundingBox()` di Playwright dentro un iframe | le coordinate sono già della pagina principale: non sommare la posizione dell'iframe |
| Un widget generico al posto dei tipi | 92 infografiche di 14 forme diverse schiacciate in un solo widget: tabelle diventate elenchi. Prima si catalogano le forme, poi un widget per forma, poi si converte, e il controllo si fa a occhio, originale e risultato affiancati |
| `pkill -f` con un pezzo del proprio comando | uccide anche la shell. Si cerca il pid con `ps -eo pid,comm,args` |
| Un server PHP avviato dentro una pipeline (`| tail`) | eredita la pipe e il comando non finisce mai. Si avvia con `setsid nohup ... >/dev/null 2>&1 </dev/null &` |
| Un indirizzo del pannello con un pezzo in più dopo il file (`/admin/index.php/`) | Apache apre la pagina lo stesso, ma gli assets relativi si cercano dentro `index.php/` e il pannello appare senza grafica. Dalla 1.1.5 `auth.php` riporta all'indirizzo pulito |
| Il CMS installato in una cartella (`https://esempio.it/test`) | fino alla 1.1.6 l'installatore rifiutava l'indirizzo con la cartella, `index.php` rimandava ogni pagina alla radice e i link del sito partivano da `/`. Dalla 1.1.7 `CMS_BASE` scritto dall'installatore, sito servito quando `cms_sito_base() === cms_base()`, `cms_con_cartella()` sui link. Le prove d'installazione si fanno in radice **e** in cartella, su Apache vero |
| Uno zip costruito con `zipfile.writestr()` | ogni file esce 0600: sul server PHP funziona (gira con l'utente del sito) ma il server web non legge CSS, JS e immagini e risponde 403. Pannello e installatore senza grafica sul primo server vero (27 ottobre 2026). Ora `scrivi_voce` mette 0644 e cartelle 0755. Le prove si fanno estraendo i file con un utente diverso da quello del server web |
| Un 301 che dipende dalla configurazione | il browser lo ricorda e lo ripete senza chiedere più al server: fino alla 1.1.6 la cartella `/test/` andava alla radice con un 301, e dopo la correzione il browser ci andava ancora (la pagina si apriva solo con `?=0`). I rimandi che dipendono dalle impostazioni sono 302 con `no-store`; a chi ha già il 301 in memoria serve svuotare la cache |
| Una chiave numerica scritta come testo in un array PHP (`'404' => ...`) | PHP la trasforma nel numero 404: `array_keys()` restituisce un intero e `in_array('404', ..., true)` dice di no. La vista 404 dei temi non partiva (27 ottobre 2026). Si confronta come testo: `(string)$n` |
| `color-mix()` al posto di `rgba()` per una trasparenza | il colore esce in un altro spazio e i gradienti cambiano di 1-5 livelli: non si vede, ma il sito non è più identico. Per le trasparenze dei temi si usa `rgba(var(--x-rgb),a)` |
| Un elemento con l'attributo `hidden` e un `display` nel suo CSS | il `display` vince e `hidden` non nasconde niente: il pannello Revisioni dell'editor non si chiudeva mai e toglieva 380 pixel alla tela (fino alla 1.2.2). A ogni `display` su un elemento che si nasconde, la regola `[hidden]{display:none}` |
| Un valore di un controllo "scelta" che non sta fra le sue opzioni | `editor_valida` lo svuota in silenzio: un header importato con il menu `posizione:principale` usciva senza menu (prima della 1.2.3), e il widget Download importato dalla parte 1 del sito perdeva i file che arrivano con le parti dopo (1.2.4). Una tendina i cui valori possono non esistere ancora dichiara `'accetta' => regex` |
| Un pacchetto più grande del limite di caricamento del server | "Il file supera il limite di caricamento del server": il pacchetto unico del sito di plumeCMS (5 MB) è stato rifiutato dal primo hosting vero (27 ottobre 2026), che accetta 1,2 MB. Ora il costruttore lo divide in parti sotto 1900 KB (il predefinito di PHP è 2M). Anche i PDF: con i tag di accessibilità e Inter variabile pesavano il doppio |
| `editor_widget_registrati()` per sapere quali fogli caricare | dà solo i widget esterni, non quelli del nucleo: l'anteprima dell'editor non caricava i fogli delle disposizioni dell'Apertura e le mostrava senza stile (1.2.6, trovato dalla prova dell'editor, non da quella del sito). E le viste mettono ogni `<link>` davanti al precedente: l'ordine dei fogli di un widget si capovolge se non lo si dà al contrario |
| Una regola di un foglio nuovo con un solo nome di classe (`.ed-hero-copertina{padding:...}`) | perde contro `.ed-w-hero .hero{padding:0}` di `sito.css`: nella 1.2.6 testo e pulsanti della Copertina toccavano il bordo, e nessuna prova lo vedeva (passavano tutte). Le regole sul contenitore di una disposizione si scrivono `.ed-hero.ed-hero-<id>`; le foto si guardano anche sul sito vero, non solo sulla pagina di prova |
| Cercare i resti di un sito con le sue parole (danilofailli, laser, ebook) | sfuggono le frasi: i widget extra avevano ancora "Non lavoro per renderti più forte", "Fai chiarezza in 15 minuti", un listino a incontri. Si guarda la pagina con tutti i widget, a occhio |
| Una consegna in un solo zip da 15 MB | arrivata rotta a Danilo due volte (1.5.0 e 1.6.0) pur essendo integra qui; anche i pezzi da 4,8 MB lo hanno confuso (li ha caricati nel pannello: "supera il limite"). Funziona mandare **i file uno per uno** (aggiornamento, parti del sito, PDF), ognuno sotto 1,9 MB, nell'ordine di caricamento (28 ottobre 2026) |
| `open(f, 'w').write(f(open(f).read()))` in Python | `open(f, 'w')` svuota il file prima che la lettura avvenga: il `config.local.php` di un sito di prova è finito vuoto (28 ottobre 2026) e la prova dopo falliva in modo incomprensibile. Prima si legge in una variabile, poi si scrive |
| Un'emoji salvata come entità (`&#127837;`) in un campo di testo semplice di un widget | il widget fa l'escape e sul sito esce scritto `&amp;#127837;` (sito su misura, 1.7.0). Le entità valgono solo nei campi che le decodificano (Testo); nell'albero dell'editor ci pensa `editor_json()`; nei nomi che diventano titoli e slug le emoji si tolgono |
| Un gestore in linea (`onchange="..."`) nel pannello | la CSP a nonce lo blocca in silenzio: Sostituisci non partiva. Si usano gli attributi di `pannello.js` (`data-invia-al-cambio`, `data-conferma`...) |
| Più di sei colonne in una sezione | `editor_valida()` tiene le prime sei e toglie le altre in silenzio: la settima colonna (con il suo widget) sparisce. Nelle pagine di prova un widget per sezione |
| Una pagina di prova creata a metà del giro delle schermate | prende il numero di pagina che un capitolo dopo si aspetta (`editor.php?id=6`, "Lavora con noi") e lo script si ferma. Le pagine di prova per le foto si creano dopo tutti i capitoli |
| Un agente con `isolation: worktree` | la sua worktree nasce dal primo commit del repository, senza il CMS: tutti e tre gli agenti degli addon hanno dovuto fare `git reset --hard` sul ramo di lavoro. Nel compito si scrive da quale commit partire |
| Un agente con `isolation: worktree` lanciato mentre la cartella di lavoro è un altro repository | la sua worktree nasce in quel repository (il 31 ottobre 2026 dentro il second brain): prima di lanciarlo, `cd /home/user/PlumeCMS`; nel compito, "se il commit di partenza non esiste, fermati" |
| `quanto-foto.py` con due file | vuole due **cartelle** di schermate, non due file; senza differenze non stampa niente |
| `prova-install.js` in cartella | la base è `http://127.0.0.1:<porta>/test` e il quinto argomento resta vuoto (il quinto è per il CMS in una sottocartella con il sito nella radice) |
| Un `fieldset` o un `<code>` con `white-space:nowrap` in una pagina del pannello | il fieldset non si stringe sotto il suo contenuto e la pagina scorre di lato al telefono: dalla 2.0.0 `admin.css` mette `fieldset{min-width:0}` e manda a capo i codici nelle tabelle |
| Una variabile in cima a un file di widget (`$r`, `$f`, `$file`) | il file si caricava nello spazio di `editor_carica_widget_esterni()` e ne sovrascriveva il registro: con una persona nelle Prenotazioni tutte le pagine del sito uscivano vuote (2.0.0, trovato dall'agente del manuale, non dalle prove degli addon). Ora ogni file si carica in una funzione sua; le prove degli addon mettono dati veri prima di guardare le pagine |
| Centinaia di server di prova accesi (agenti e giri di prove) | le porte si esauriscono: "Address already in use" e la prova fallisce in modo strano. Alla fine di un lavoro si spengono i `php -S` e i server finti che non servono (cercando il pid con `ps -eo pid=,comm=,args=` e `comm` uguale a php, mai `pkill -f`) |
| `tema_attivo` scritto da riga di comando per provare un tema | non basta: il tema si applica dal pannello (copia stile e modelli). Per le prove si usa l'anteprima da amministratore, `?preview=1&tema=<slug>` |
| Un link scritto senza `https://` o senza `/` in un widget | fino alla 2.0.1 `editor_link_sicuro()` lo scartava in silenzio e il pulsante puntava a `#` ("i pulsanti non funzionano") |
| Una tabella con colonne generate (`GENERATED ALWAYS AS`) in un dump | l'INSERT con il valore della colonna fallisce (MySQL 3105): ripristino della copia "con errori" e snapshot non ripristinabile con dati in Eventi o Prenotazioni (fino alla 2.0.3). Nei dump si scrive `DEFAULT` (`agg_riga_sql()`) |
| `tbl()` in uno script di prova | restituisce il nome fra apici inversi: per `SHOW COLUMNS` o funzioni che li aggiungono si tolgono con `trim($t, '`')` |
| Una variabile con un nome comune in un file incluso dalle pagine (`admin-header.php`: `$sa`, `$v`, `$nome`) | sovrascrive quella della pagina che lo include: il service account di Google salvato non compariva in SEO (fino alla 2.3.1). Nei file inclusi, nomi con prefisso (`$_menu...`) e `unset` alla fine. E una prova che cerca un testo deve guardare nel riquadro giusto: il messaggio verde conteneva la stessa email e la prova passava lo stesso |
| Una funzione nuova in un file usata da un altro file nella stessa versione | subito dopo l'aggiornamento opcache (revalidate_freq 2 s, su alcuni hosting mai) può dare il file vecchio insieme al nuovo: "Call to undefined function", home in 500 (2.5.0, trovato dalla prova del pacchetto unico una volta su due). Dalla 2.5.0 il motore chiama `opcache_invalidate` sui file scritti; ma l'aggiornamento lo applica il motore vecchio, quindi chi chiama una funzione nuova di un altro file mette una copia di riserva con `function_exists`. E `php -S` usa opcache (`opcache.enable`, non `enable_cli`) |
| Uno zip di tema con la cartella che ha un nome diverso dallo `slug` di `tema.json` | il pannello installa nella cartella dello zip (`temi_zip_leggi()`): le pagine e le viste che chiamano `/temi/<slug>/...` danno 404. Su simonescarsella.it (2 novembre 2026, tema `scarsellaproject` installato in `temi/scarsella/`) niente foto e niente popup; sistemato copiando la cartella in cPanel. Proposta a Danilo per la 2.19.0: usare lo slug del tema o avvisare, e servire i file del tema attivo dalla cartella vecchia |
| `DROP TABLE` e poi `CREATE TABLE` tabella per tabella in un ripristino | se la CREATE fallisce (una collazione che il server non conosce: `utf8mb4_0900_ai_ci` di MySQL 8 su MariaDB o MySQL 5.7) la tabella resta cancellata e il sito muore (arketypos.it, 4 novembre 2026). Si crea in tabelle di appoggio e si scambia con una RENAME sola, dalla 2.25.1 |
| `display_errors` acceso nel pannello | un errore fatale mostra a chiunque i percorsi del server e il nome delle tabelle. Dalla 2.25.1 spento ovunque: `includes/errori.php` fa la pagina sobria, `CMS_DEBUG` nel config rimette tutto a video |
| Uno spezzatore di SQL che conosce solo gli apici semplici (`agg_split_sql()` fino alla 2.25.0) | ``CREATE TABLE `x` (`c'` int); DROP TABLE `y` `` passa come una istruzione sola e PDO, con le istruzioni impilate accese, esegue anche la seconda. Tre regioni fra virgolette (', ", `) e `MULTI_STATEMENTS` spento alla creazione della connessione |
| `SHOW COLUMNS` con `Extra` che contiene `GENERATED` | `DEFAULT_GENERATED` (created_at e updated_at di MySQL 8) non è una colonna generata: `agg_colonne_generate()` la scriveva come `DEFAULT` nei dump e le date si perdevano (fino alla 2.25.0). Si cerca `VIRTUAL GENERATED` o `STORED GENERATED` |
| Il contenitore della sessione che si riavvia o il limite settimanale che ferma gli agenti | Docker spento (`dockerd` da riaccendere, `docker start cmsdb mariadbprova mysql57prova`), catena `Workflow` da riprendere con `resumeFromRunId`; i server php delle prove spariscono (le prove vanno rilanciate) |
| Un id di widget nuovo scelto senza guardare `igx_definizioni()` | `ig_barre` esisteva già (Barre di avanzamento, HTML): `editor_definizioni()` fa `igx + igs` e tiene la prima, il disegno andava a `igs_disegna()` con i campi dell'altra: widget vuoto senza errori (2.26.0). Prima di un id nuovo: `grep "'ig_<nome>'"` in tutti i file dei widget |
| Un numero di infografiche scritto nei cicli degli script (`i < 15` in `manuale-uso-schermate.js`, `'L'.repeat(15)` nelle prove) | la foto d'insieme del manuale usciva con quindici anche con la sedicesima sul sito. I cicli contano `IGS_TIPI` o `.igx`, non un numero fisso; dove resta un numero, grep di `15` e `quindici` a ogni infografica nuova |
| La rete della sessione verso un servizio esterno (OpenStreetMap: tessere e Nominatim rispondono 000 anche via proxy) | le schermate del manuale che mostrano una mappa escono con tessere finte (`TESSERE_FINTE=1` in `manuale-uso-schermate.js`) e la ricerca vera non si prova: si dice a Danilo e si rifanno con la rete aperta |
| Un agente che fa commit nella sua worktree senza `git config user.email` | il commit esce con l'identità del sito di prova (`plumecms@localhost`) e il ramo risulta "Unverified" (2.28.0): nel compito dell'agente si scrive di impostare `user.email noreply@anthropic.com` e `user.name Claude`, oppure si amenda con `--reset-author` prima dell'unione |
| Il contenitore che si riavvia a metà batteria (5 novembre, 14:40) | i server `php -S`, i server finti e gli script delle prove spariscono; i siti installati e i database (volumi Docker) restano. Si riaccende Docker (`rm -f /var/run/docker.pid`, `dockerd`, `docker start`), si aspetta `SELECT 1`, si riaccendono i server sui siti già installati e si rifanno solo le prove mancanti (`$S/v6/riprendi.sh`); gli attacchi lanciati nello stesso istante in cui MySQL risponde non entrano nel pannello ("Non riesco a entrare come amministratore"): si aspetta qualche secondo e si rilancia |
| Le schermate del manuale fatte sul sito dove sono girate le prove dell'agente | la Tabella SEO mostrava 26 contenuti di prova e "25 con qualcosa da sistemare" (2.29.0). Le schermate si rifanno su un sito col kit appena installato (`SOLO_CAPITOLI`, `CMS_DIR_MANUALE`, `TMPDIR` vuota) prima del PDF |
| Due batterie di attacchi lanciate insieme sullo stesso contenitore | il gruppo 37 (webhook) accende due server finti sulle porte 9296 e 9297 (`SIC_WEBHOOK`): la seconda batteria trova le porte prese e "il servizio non risponde" (5 novembre, sito aggiornato). E il controllo del gruppo 44 contava le righe di `media_alt`, tabella che nasce al primo uso: su un sito dove nessun alt è mai stato salvato la query falliva (ora la prova crea prima la tabella). Gli attacchi si lanciano uno alla volta, o con `SIC_WEBHOOK` diverso |
| Una funzione di un'estensione PHP non richiesta dall'installatore (`iconv()`) chiamata senza `function_exists` | su un hosting che non ce l'ha (Hostcare) l'installazione si ferma a metà con "Call to undefined function" (2.29.1). Le estensioni fuori dalla lista dei requisiti si usano solo con una riserva; si prova con `php -d disable_functions=<funzione>` |
| Un agente in sottofondo quando il contenitore si riavvia | l'agente muore senza resoconto e non riparte da solo (8 novembre, 15:10: la 2.32.0 ferma un'ora). Nel compito: commit di lavoro ogni 20-30 minuti e un file di avanzamento (`$S/ai/AVANZAMENTO.md`); e un controllo programmato con `send_later` ogni 40 minuti che salva, rilancia un agente nuovo dall'ultimo commit e lo dice a Danilo |
| Fidarsi del resoconto di un agente | è successo che un agente dichiarasse lavoro mai scritto su disco. Si controllano i file veri e si rifanno le prove |

## Tono con l'utente

Danilo è il proprietario e non è uno sviluppatore. Le istruzioni operative vanno
scritte passo per passo, dicendo dove cliccare e cosa deve comparire. I risultati
si riportano per quello che sono: se una cosa non è verificata, si dice.

## Second brain
Second brain: progetto plumecms

- Memoria nel repository `danilofaillii/second-brain`, cartella `plumecms/`.
- Istruzioni: skill `second-brain`. Se la skill non è installata (sessioni cloud), leggi `_sistema/skill/second-brain/SKILL.md` nel repository `second-brain` e seguila.
- A inizio sessione esegui `riprendi`. Durante il lavoro `segna`. A fine sessione `salva`.
- Non usare informazioni di altri progetti senza che Danilo lo chieda.
