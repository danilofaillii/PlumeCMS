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
prove/          prova-install.js (installazione da zero con Playwright), prova-temi.js (motore dei temi),
                foto-sito.js e quanto-foto.py (schermate prima e dopo, al pixel)
```

Stato, decisioni, contesto e diario non stanno qui: stanno nel second brain
(`danilofaillii/second-brain`, cartella `plumecms/`), unica copia.

## Documenti, in quest'ordine

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
- **Pacchetto di aggiornamento** per un'installazione esistente:
  `python3 tools/cms-pacchetto.py --aggiornamento`. Si carica dal pannello,
  Impostazioni, Aggiornamenti.
- **Versione**: `cms/VERSIONE` (oggi 1.1.9) e una voce in `cms/CHANGELOG.md` a ogni
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

## Stato al 27 ottobre 2026

Fatto: plumeCMS 1.1.9, installazione provata da zero in radice e in sottocartella,
79 prove di attacco respinte, pagine del pannello provate su PHP 8.4.27.
Prima installazione vera su `https://duhtron.it/test` (cartella), funzionante con la 1.1.9.
Dal 27 ottobre plumeCMS vive nel suo progetto e repository (`SPOSTARE.md`).

Aperto:
- qualche testo d'esempio dei widget (Apertura, Schede, Prezzi, riquadro del test) ancora
  pensato per danilofailli.it, da rendere neutro;
- commenti nel codice che citano danilofailli.it come esempio (innocui, da ripulire);
- `CMS_PREFISSO` ha come valore predefinito `newdf_` perché danilofailli.it non lo
  scrive nel suo config. L'installatore lo scrive sempre, quindi le installazioni
  nuove non ne risentono. Se un giorno si cambia il predefinito, prima va aggiunto
  `define('CMS_PREFISSO', 'newdf_');` al `config.local.php` di danilofailli.it;
- prova vera delle chiamate a Google Search Console e IndexNow da un server con rete aperta;
- favicon di partenza (l'installazione pulita risponde 404 su `/favicon.ico`).

## Rapporto con danilofailli.it

- danilofailli.it usa plumeCMS con il CMS in `/new/` e il modulo `moduli/danilofailli/`.
- Le versioni nuove di plumeCMS arrivano a danilofailli.it come pacchetti del suo
  pannello Aggiornamenti, costruiti nel suo repository con
  `tools/cms-pacchetto.py --sito <commit> <nome>` (contengono anche il modulo).
- **Lo zip di aggiornamento del prodotto non va mai applicato a danilofailli.it**: non
  contiene il modulo e romperebbe le sue pagine.
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
