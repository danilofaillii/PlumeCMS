# Articoli e pagine come in WordPress

Deciso il 23 ottobre 2026: pagine e articoli si gestiscono come in WordPress, e le due aree sono identiche. Da qui: un solo elenco, una sola scheda, un solo editor visuale, per tutti e due. Le tabelle restano due (`<prefisso>articles`, `<prefisso>pages`); le differenze stanno in `contenuti_tipi()` dentro `includes/contenuti.php` e il resto le tratta uguali. Un modulo del sito può aggiungere tipi suoi (chiave `tipi_contenuto` del manifest, `cms-moduli.md`), che entrano negli stessi elenchi, scheda ed editor.

## Dove sta

| Pezzo | File |
|---|---|
| Motore: stati, elenco con filtri, salvataggio, modifica rapida, cestino, duplica, revisioni, tassonomie, redirect automatici | `includes/contenuti.php` |
| Elenco ("Tutti gli articoli" di WordPress) | `admin/contenuti.php?tipo=articoli` oppure `?tipo=pagine` |
| Scheda di modifica (schermata classica di WordPress) | `admin/contenuto.php?tipo=…&id=…` (senza id: nuovo) |
| Editor Visuale/Testo, tag, immagine in evidenza, finestra delle immagini | `admin/assets/contenuto.js`, `admin/assets/css/contenuti.css` |
| Configuratore delle infografiche (condiviso) | `admin/assets/infografica.js`, `admin/includes/infografica-modal.php` |
| Categorie e tag (elenco, aggiungi, rinomina, elimina) | `admin/categories.php`, `?tipo=tag` |
| Editor visuale a sezioni e widget, per tutti e due | `admin/editor.php?tipo=…&id=…` (vedi `editor-pagine.md`) |

Le schermate di prima (`articles.php`, `article-edit.php`, `pages.php`, `page-edit.php`, `article-delete.php`, `page-delete.php`) rimandano alle nuove con un 301: i vecchi segnalibri funzionano.

## Gli stati

Come in WordPress: **Bozza**, **Programmato** (pubblicato con data e ora future: esce da solo al minuto), **Pubblicato**, **Cestino**. Nel database: `status` resta `draft`/`published`; la data decide se è programmato; `cestino` (DATETIME, colonna nuova su tutte e due le tabelle) dice se sta nel cestino. Niente si elimina per sempre senza passare dal cestino. La home non va nel cestino e non va in bozza.

Il sito pubblico esclude il cestino ovunque (`sito_contenuti`, le viste `v_published_articles` e `v_scheduled_articles`) e per le pagine rispetta `publish_at`: una pagina programmata esce alla sua ora, come un articolo.

## L'elenco

Schede per stato con il conteggio; cerca; filtri per mese, categoria, pillar o cluster; ordinamento per titolo, autore, data; 20 per pagina. Azioni di gruppo: Pubblica, Metti in bozza, Sposta nel cestino; nel cestino Ripristina ed Elimina definitivamente, e Svuota il cestino. Sotto ogni titolo, al passaggio del mouse: Modifica, Modifica rapida (titolo, indirizzo, stato, data, ora, categorie, tag, senza uscire dall'elenco), Editor visuale, Duplica (copia in bozza con `-copia`), Cestina, Visualizza o Anteprima. Per gli articoli restano le colonne delle parole e della densità delle keyword.

## La scheda

Titolo e permalink (l'indirizzo si cambia in riga). Editor con due schede, **Visuale** e **Testo**, come il classico di WordPress: Visuale è un contenteditable con la barra (formato, grassetto, corsivo, elenchi, link, linea, annulla), che lascia stare l'HTML che non conosce (le infografiche dei pillar, le tabelle, i div) e pulisce quello che arriva incollato da Word; Testo è l'HTML. Pulsanti **+ Infografica** (il configuratore di prima) e **+ Immagine** (dalla libreria Media). Un articolo ancora in Markdown lo si vede convertito in Visuale: al primo salvataggio da Visuale diventa HTML, da Testo resta Markdown.

A destra, come in WordPress: **Pubblica** (stato, data e ora, autore, Salva bozza, Anteprima, Pubblica o Aggiorna, Sposta nel cestino), **Categorie** (spunte, più una nuova al volo), **Tag** (chip, Invio o virgola, suggerimenti), **Immagine in evidenza** (dalla libreria), **Attributi** (tipo pillar o cluster, pillar padre e il suo titolo). Sotto: Estratto, SEO e Google, Risposte dirette (In breve e FAQ), Revisioni.

Le pagine hanno tutto tranne categorie, tag e attributi dell'articolo: WordPress fa lo stesso.

**Indirizzo cambiato su un contenuto già pubblicato**: dal vecchio parte da solo un redirect 301 al nuovo (`contenuti_redirect`, origine `slug`), come faceva Rank Math. Vale anche per lo slug di categorie e tag (`/category/vecchio/` → `/category/nuovo/`), e gli articoli seguono. Così il contratto dei 1.005 indirizzi regge: chi arriva dal vecchio indirizzo trova il nuovo.

**Anteprima**: `/indirizzo/?preview=1` con la sessione del pannello mostra bozze e programmati come fossero usciti (`SITO_ANTEPRIMA` in `sito/index.php`, con `Cache-Control: no-store`). Senza la sessione, 404 come sempre.

**Revisioni**: a ogni salvataggio dalla scheda, la riga com'era prima finisce in `<prefisso>revisioni` (tipo, contenuto_id, dati JSON, autore, data; le ultime 20). Ripristina riporta titolo, testo, indirizzo e il resto a quella versione, e la versione di adesso finisce nelle revisioni. Lo stesso salvataggio scrive anche una versione nella tabella unica `<prefisso>editor_versioni` (ambito `contenuto:<tipo>`). Le versioni dell'editor visuale stanno lì, non più in `<prefisso>pagine_revisioni`: 50 per contenuto (da 5 a 500), pubblicate e con nota mai potate (aggiornato al 31 ottobre 2026; capitolo 13 del manuale tecnico).

## L'editor visuale anche sugli articoli

`<prefisso>articles` ha le stesse tre colonne delle pagine: `struttura`, `struttura_bozza`, `struttura_aggiornata`. Un articolo con `struttura` esce composto dall'editor: la testata dell'articolo (occhiello con la categoria, titolo, data, minuti di lettura) e il riquadro In breve restano quelli dell'articolo, sotto vengono le sezioni. Le FAQ dei widget si sommano a quelle della scheda nel JSON-LD. Si parte dal testo di oggi (intero in un widget HTML dentro una sezione stretta) o da un articolo vuoto. Il testo della scheda resta come riserva e per le ricerche.

## Trappola pagata il 23 ottobre

Nella prima versione della scheda l'editor Visuale si inizializzava copiando il proprio contenuto (vuoto) nella casella Testo, prima di leggerla: la scheda si apriva vuota e chi salvava cancellava il testo. Trovato dal collaudo nel browser su due contenuti di prova, recuperati dalle revisioni. Regola: la sincronizzazione Visuale → Testo parte solo da una scheda Visuale già aperta e visibile.

## Collaudo del 23 ottobre

Sul sito di prova con MySQL 8, nel browser: elenco e schede con i conteggi; modifica rapida; cestino di gruppo (l'articolo sparisce dal sito con 404) e ripristino; scheda: frase aggiunta da Visuale con il testo intero conservato (36.505 → 36.234 caratteri: solo entità e `<tbody>`), tag nuovi, immagine in evidenza dalla libreria, revisioni; pagina nuova salvata in bozza, 404 sul sito e 200 con `?preview=1`, poi programmata con data futura; cambio di indirizzo di una pagina pubblicata con 301 dal vecchio, e ritorno; articolo composto con l'editor visuale, pubblicato, sul sito con testata, CSS, FAQ e FAQPage; categorie: aggiunta. Contratto dei 1.005 indirizzi: 0 differenze. Giro del pannello: tutte le pagine a 200 o 301.
