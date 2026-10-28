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
  moduli/       i moduli del sito che installa (vuota nel prodotto)
  temi/         i temi del sito (motore in includes/temi.php, docs/temi.md)
  docs/         la documentazione del CMS
  VERSIONE, CHANGELOG.md, README.md, INSTALLA.md
tools/          cms-pacchetto.py (costruisce gli zip), pacchetto.py (pacchetti per il
                pannello Aggiornamenti), sicurezza-prova.js (79 prove di attacco)
prove/          prova-install.js (installazione da zero con Playwright), prova-temi.js, prova-pannello-temi.js,
                prova-temi-partenza.js, prova-carica-temi.js (temi), prova-foto-effetti.js (velo ed effetti delle foto), prova-hero.js (le undici aperture),
                foto-sito.js e quanto-foto.py (schermate prima e dopo, al pixel)
esempi/         tema-lavagna/: il tema d'esempio del manuale dei temi (provato, non entra nello zip)
sito-plumecms/  il sito da cui si scarica plumeCMS, fatto con plumeCMS: costruisci.py scrive il pacchetto
                releases/sito/sito-plumecms-<versione>-parte-<k>-di-<n>.zip, ognuna sotto 1900 KB, da caricare in ordine
tools/manuali-pdf.js  i tre manuali in PDF (releases/manuali/); tools/manuale-uso-schermate.js le schermate del manuale d'uso;
tools/consegna.py     lo zip unico da dare a Danilo (releases/consegna/)
```

Stato, decisioni, contesto e diario non stanno qui: stanno nel second brain
(`danilofaillii/second-brain`, cartella `plumecms/`), unica copia.

## Documenti, in quest'ordine

0. `cms/docs/manuale-uso.md`: il manuale d'uso per chi usa plumeCMS (29 capitoli, esempio, schermate con i passi
   numerati, checklist). Le schermate le fa `tools/manuale-uso-schermate.js` eseguendo gli esempi su un sito di prova
   (kit Attività, in `/home/studioprova/public_html`): **quando cambia il pannello si rifanno le schermate e si
   ricontrolla il capitolo**. Il PDF deve restare sotto 1900 KB (oggi 1,8 MB).
   `cms/docs/manuale-tecnico.md`: il manuale tecnico, tutto il CMS scritto leggendo il codice, con `file:riga`.
   Se un documento qui sotto dice una cosa diversa, vale il manuale (appendice A: i punti superati).
   `cms/docs/manuale-temi.md`: il manuale per chi costruisce un tema, con il tema d'esempio Lavagna.
   Quando si cambia il codice, si aggiorna anche il manuale tecnico.
1. `cms/docs/cms.md`: architettura (nucleo, moduli, prefisso, base URL, installatore, pacchetti).
2. `cms/docs/cms-moduli.md`: come si scrive un modulo (manifest `modulo.php`, filtri, eventi, tipi di contenuto, menu, cron).
3. `cms/docs/editor-pagine.md`: l'editor (sezioni, colonne, widget, controlli di gruppo, schede Stile e Avanzate, i trenta widget extra, le infografiche `ig_*`, temi).
4. `cms/docs/stile-sito.md`, `header-footer-menu.md`, `versioni.md`.
5. `cms/docs/sicurezza.md`: cosa c'è, come si configura, cosa fare se ci si chiude fuori.
6. `cms/docs/temi.md`: i temi del sito (com'è fatto un tema, motore, errori, sicurezza).
7. `cms/docs/seo.md`, `newsletter.md`, `moduli.md` (form ebook e Laser, nati per danilofailli.it), `contenuti.md`, `pannello.md`, `aggiornamenti.md`.

## Come si lavora

- **Costruire il prodotto**: `python3 tools/cms-pacchetto.py` scrive
  `releases/cms/plumecms-<VERSIONE>.zip`. Rifiuta: errori di sintassi PHP
  (controllati con `short_open_tag` acceso), il prefisso `newdf_` scritto nel
  codice fuori da `cms.php` e `db.php`, il dominio danilofailli.it nel codice.
- **Regola di Danilo (27 ottobre 2026): ogni funzionalità nuova aggiorna tre cose, sempre, senza che lo chieda.**
  1. *Il progetto*: codice, prove, `cms/CHANGELOG.md`, `cms/VERSIONE`, il manuale tecnico (e quello dei temi, se tocca i temi),
     il manuale d'uso se cambia quello che vede chi usa il pannello (capitolo, passi e schermate con `tools/manuale-uso-schermate.js`).
  2. *I PDF*: `node tools/manuali-pdf.js`, dopo aver aggiornato i manuali.
  3. *Il sito di plumeCMS* (`sito-plumecms/costruisci.py`): `NOVITA`; un blocco nella pagina Funzionalità con la schermata
     (pagina aggiunta a `sito-plumecms/schermate.js`, immagine in `USATE`); home, Domande frequenti o Installazione se la
     funzionalità le cambia; i numeri scritti nei testi (widget, attacchi). Poi le schermate rifatte sul sito di prova e
     `--schermate`, le parti ricostruite, `prove/prova-sito-plumecms.js` con un controllo per il blocco nuovo.
  Dalla 1.2.5 il numero di versione nelle pagine è automatico (`{versione:plumecms-ultima}`): per una versione del CMS
  senza pagine nuove Danilo carica solo i due zip da Download; le parti del sito servono quando cambiano le pagine.
  Ordine dei comandi: `python3 tools/cms-pacchetto.py --aggiornamento`, `node tools/manuali-pdf.js`,
  `python3 sito-plumecms/costruisci.py`, `python3 tools/consegna.py`. **Regola di Danilo (28 ottobre 2026): a lui si consegna
  un solo zip**, `releases/consegna/plumecms-<versione>-consegna.zip`, con dentro l'aggiornamento e lo zip completo del CMS,
  le parti del sito, i tre PDF e un LEGGIMI con l'ordine di caricamento. Non più i file uno per uno.
- **Pacchetto di aggiornamento** per un'installazione esistente:
  `python3 tools/cms-pacchetto.py --aggiornamento`. Si carica dal pannello,
  Impostazioni, Aggiornamenti.
- **Versione**: `cms/VERSIONE` (oggi 1.4.2) e una voce in `cms/CHANGELOG.md` a ogni
  zip con contenuto diverso. Mai cambiare il contenuto di una versione già data.
- **Prove** prima di dire finito:
  - `php -n -d short_open_tag=1 -l` su ogni file PHP toccato, `node --check` sui js;
  - installazione da zero: `prove/prova-install.js` (vedi `prove/LEGGIMI.md`);
  - attacco: `CMS_DIR=<cartella del CMS installato> node tools/sicurezza-prova.js <base> <utente> <password> [prefisso-pannello]`,
    poi svuotare `<prefisso>sicurezza_tentativi`;
  - le pagine si provano nel browser come un utente (Playwright), non con un `curl` che risponde 200.
- **PHP come sui server veri**: Hostcare (il primo cliente) ha PHP 8.4.27, MySQL 8.0.46
  in modalità stretta, Apache 2.4 con cPanel e ModSecurity. In locale si prova con
  PHP 8.4.27 in Docker: `dockerd`, immagine `php:8.4-cli` con `pdo_mysql`.
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

## Stato al 28 ottobre 2026

Fatto: plumeCMS 1.4.2 (28 ottobre): il manuale d'uso (richiesta di Danilo: "deve spiegare come fare qualsiasi cosa con il
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
- i documenti vecchi di `docs/` superati in alcuni punti (elenco nell'appendice A del manuale tecnico), da allineare;
- `CMS_PREFISSO` ha come valore predefinito `newdf_` perché danilofailli.it non lo
  scrive nel suo config. L'installatore lo scrive sempre, quindi le installazioni
  nuove non ne risentono. Se un giorno si cambia il predefinito, prima va aggiunto
  `define('CMS_PREFISSO', 'newdf_');` al `config.local.php` di danilofailli.it;
- prova vera delle chiamate a Google Search Console e IndexNow da un server con rete aperta;
- **indirizzo del sito ufficiale** per "c'è una versione nuova": dalla 1.3.1 si sceglie in Aggiornamenti; il predefinito
  `VERSIONE_SITO_UFFICIALE` (`includes/versione-nuova.php`) vale ancora `https://duhtron.it/test`, da confermare con Danilo; quel sito
  deve avere la 1.3.0 o più nuova (per `api/versione.php`);
- copia del sito: il ripristino non toglie i file che stanno nel sito ma non nella copia; con due installazioni nello stesso database
  e prefissi che cominciano uguale (`cms_` e `cms_b_`) la copia prende anche le tabelle dell'altra;
- "c'è una versione nuova" provato solo in http locale (`CMS_AGGIORNAMENTI_HTTP_PROVA`): HTTPS vero da provare su un server;
- stessa famiglia del Riquadro con icona (corretto nella 1.4.0): "Animazione delle parole" del Titolo animato e `padding` di Social,
  Condividi, Avviso e Flip box hanno lo stesso nome di un controllo delle Avanzate;
- import da WordPress provato contro un WordPress locale, non su un hosting vero; Apache, HTTPS vero, SMTP vero e Imagick non provati per la 1.4.0;
- `tools/` e `prove/` hanno ancora riferimenti a danilofailli.it (strumenti di sviluppo, non vanno nello zip): `--sito`, la prova del burnout;
- i temi a danilofailli.it arrivano solo con un pacchetto `--sito` dal suo repository (mai lo zip del prodotto).

## Rapporto con danilofailli.it

**Regola di Danilo (28 ottobre 2026): non chiedergli più nulla di danilofailli.it.** Se servono informazioni, le dà lui.
Il 28 ottobre ha detto di aver già aggiornato tutto su danilofailli.it.

- danilofailli.it usa plumeCMS con il CMS in `/new/` e il modulo `moduli/danilofailli/`.
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
