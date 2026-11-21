# Addon Corsi online (`corsi`)

Versione 1.1.0, con plumeCMS 2.1 (la 1.0.0 è della 2.0.0). Cartella `cms/addon/corsi/`. Manifesto con `'richiede' => ['membri']`:
accendendolo si accende anche l'Area riservata, e l'Area riservata non si spegne finché Corsi online è acceso (`includes/addon.php`).

Novità della 2.1: il testo delle lezioni si scrive con l'**editor visuale** (non più in HTML in un'area di testo), le
lezioni di prima passano all'editor senza perdere niente, e il programma si riordina **trascinando** (le frecce restano per
la tastiera).

## File

| File | Cosa |
|---|---|
| `modulo.php` | manifesto: menu Corsi (dopo Membri), tre pagine del pannello, evento `sito_percorso`, evento `membro_eliminato`, filtro `membri_area`, widget, (2.1) `editor_contenitori` => `corsi_lezione` |
| `includes/corsi.php` | il motore: tabelle, corsi, moduli, lezioni, ordine, testo con l'editor, video, accesso, avanzamento, certificati |
| `includes/pagine.php` | le pagine pubbliche: elenco, programma, lezione, video, materiale, certificato, verifica |
| `includes/admin-corsi.php` | pannello: elenco dei corsi, Nuovo corso, Elimina |
| `includes/admin-corso.php` | pannello: dati del corso, programma (moduli e lezioni da trascinare o con le frecce), chi lo segue, assegnazioni |
| `includes/admin-lezione.php` | pannello: una lezione (titolo, modulo, il pulsante dell'editor visuale, video, materiali, durata) |
| `includes/widget.php` | il widget "Elenco corsi" |
| `assets/corsi.css`, `assets/certificato.css`, `assets/corsi.js` | stile; certificato da stampare (A4 orizzontale); pulsante Stampa senza gestori in linea |
| `assets/programma.js` (2.1) | il trascinamento del programma, con SortableJS del pannello (`admin/assets/editor/Sortable.min.js`) |

## Tabelle

| Tabella | Colonne |
|---|---|
| `{p}corsi` | `id`, `slug` (unico), `titolo`, `descrizione`, `immagine`, `accesso` (`tutti` / `assegnati`), `stato` (`bozza` / `pubblicato`), `ordine`, `creato`, `aggiornato` |
| `{p}corsi_moduli` | `id`, `corso_id`, `titolo`, `ordine` |
| `{p}corsi_lezioni` | `id`, `corso_id`, `modulo_id`, `slug` (unico nel corso), `titolo`, `testo` (HTML ripulito, il testo "di prima"), `video_url`, `video_file`, `materiali` (JSON di nomi di file riservati), `durata`, `ordine`, `creato`, `aggiornato`; 2.1: `struttura` (l'albero dell'editor pubblicato), `struttura_bozza`, `struttura_aggiornata` |
| `{p}corsi_iscritti` | `corso_id`, `membro_id`, `assegnato`, `iniziato`, `ultimo`, `ultima_lezione`, `completato` |
| `{p}corsi_progressi` | `membro_id`, `lezione_id`, `completata` |
| `{p}corsi_certificati` | `codice` (pubblico, `XXXX-XXXX-XXXX`), `corso_id`, `membro_id`, `nome`, `corso_titolo`, `data` |

Impostazioni: `corsi_schema` (impronta delle tabelle), `corsi_video_cartella` (nome a caso della cartella dei video in `uploads/`).

Schema: `corsi_tabelle()` (`corsi.php:27`), `corsi_installa()` (`:63`). Dalla 2.1 `corsi_schema()` (`:85`) confronta
l'impronta con le tabelle di adesso (prima guardava solo se c'era): dopo un aggiornamento `corsi_installa()` riparte e
`corsi_colonne_nuove()` (`:76`) aggiunge con `ALTER TABLE` le tre colonne dell'editor alle lezioni che c'erano.

## Funzioni principali (`includes/corsi.php`)

| Funzione | Riga | Cosa |
|---|---|---|
| `corsi_slug()`, `corsi_slug_libero()` | 98, 141 | slug da titolo; mai `certificato`, `video`, `materiale` per una lezione |
| `corsi_tutti()`, `corsi_leggi()`, `corsi_per_slug()`, `corsi_crea()`, `corsi_salva()`, `corsi_elimina()` | 109-166 | corsi; un corso nuovo nasce in bozza con "Modulo 1"; l'immagine passa da `sicurezza_url_pulito()` |
| `corsi_moduli()`, `corsi_modulo_crea()`, `corsi_modulo_elimina()` | 177, 188, 430 | un modulo con lezioni non si elimina; un corso ha almeno un modulo |
| `corsi_lezioni()` | 196 | le lezioni nell'ordine in cui si seguono (ordine del modulo, poi della lezione) |
| `corsi_lezione_crea()`, `corsi_lezione_salva()`, `corsi_lezione_elimina()` | 209, 227, 250 | `testo` (se arriva) passa da `sicurezza_html_pulito($html, 'articolo')`; dalla 2.1 il pannello non lo manda più e la colonna resta com'è; i materiali solo fra i file riservati di Download |
| `corsi_contenitore()` | 269 | il contenitore dell'editor per le lezioni (sotto) |
| `corsi_lezione_riga_editor()`, `corsi_lezione_salva_struttura()`, `corsi_lezione_togli_editor()` | 282, 293, 299 | la riga per l'editor, il salvataggio (bozza, o pubblicata togliendo la bozza), "Torna al contenuto della scheda" |
| `corsi_lezione_editor_stato()` | 304 | `pubblicata`, `bozza` o `''` (testo di prima) |
| `corsi_lezione_prepara_editor()` | 315 | prima di aprire l'editor: l'albero dal testo di prima, in bozza (sotto) |
| `corsi_lezione_albero()` | 336 | l'albero da mostrare: il pubblicato; nell'anteprima dell'amministratore, senza pubblicato, la bozza |
| `corsi_lezione_testa_html()`, `corsi_lezione_cornice()` | 345, 355 | la testa della lezione (briciole, modulo e numero, titolo, durata), uguale sul sito e nella tela |
| `corsi_riordina($corso, $ordine)` | 374 | l'ordine dopo un trascinamento, tutto o niente (sotto) |
| `corsi_sposta($cosa, $id, $verso)` | 412 | le frecce: scambia con la vicina e rinumera |
| `corsi_video_embed($url)` | 444 | YouTube (watch, youtu.be, embed, shorts, live; id di 11 caratteri) diventa `https://www.youtube-nocookie.com/embed/<id>`; Vimeo `https://player.vimeo.com/video/<n>`. Niente altro (niente porte, credenziali, `javascript:`) |
| `corsi_video_dir()`, `corsi_video_ok()`, `corsi_video_carica()`, `corsi_video_togli()` | 464-500 | video .mp4/.webm controllati dai primi byte (`ftyp`, EBML), in `uploads/corsi-<16 hex>/` con `.htaccess` che chiude tutto |
| `corsi_manda_video($p)` | 508 | manda il video con `Range` (206, 416), `private, no-store` |
| `corsi_puo($corso, $membro)` | 542 | corso pubblicato, membro attivo, e per `assegnati` la riga `assegnato = 1` |
| `corsi_assegna()`, `corsi_iscritti()` | 550, 557 | assegnazioni; iscritti con lezioni fatte e codice del certificato |
| `corsi_completate()`, `corsi_avanzamento()`, `corsi_segna_visita()`, `corsi_completa()` | 568-599 | avanzamento; "riprendi da dove eri": la prima lezione non fatta dopo l'ultima vista |
| `corsi_codice_nuovo()`, `corsi_certificato_crea()`, `corsi_certificato_di()`, `corsi_certificato_per_codice()` | 613-642 | il certificato nasce quando l'ultima lezione è fatta; codice di 12 caratteri da 32 (60 bit), nome e titolo del giorno |
| `corsi_dimentica_membro()` | 652 | evento `membro_eliminato`: via avanzamento, assegnazioni e certificati |

## Il testo della lezione con l'editor visuale (2.1)

**Strada scelta: un contenitore dell'editor.** Le strade erano due: fare di ogni lezione una "pagina" (un tipo di
contenuto, `tipi_contenuto` del manifesto) oppure dare all'editor un contenitore suo. Un tipo di contenuto sarebbe
diventato un contenuto del sito: elenco in Contenuti, sitemap, SEO, menu, ricerca, `llms.txt`, un indirizzo pubblico del
nucleo che non sa niente del controllo dell'accesso ai corsi. Il contenitore invece lascia la lezione dov'è (una riga di
`corsi_lezioni`, servita solo da `/corsi/<corso>/<lezione>/` con `corsi_puo()`), e l'editor la apre con lo stesso motore
delle pagine: stessi widget, stesso `editor_valida()` (il sanificatore dei widget Testo), annulla e ripeti, bozza e
Pubblica, versioni. Il nucleo ha avuto il gancio generico (`includes/editor.php`, "contenitori dell'editor",
`docs-sviluppo/cms-moduli.md` e manuale tecnico 32), non qualcosa di scritto per i corsi.

**Il contenitore** (`corsi_contenitore()`, `corsi.php:269`; manifesto `'editor_contenitori' => ['corsi_lezione' => fn]`):

- `riga`: la lezione con le chiavi di una pagina (`title`, `slug`, `status` = `published` se c'è `struttura`,
  `struttura`, `struttura_bozza`, `content` = il testo di prima, `percorso` = `/corsi/<corso>/<lezione>/`, `vedi` =
  l'anteprima `?preview=1`, `scheda` = la pagina della lezione nel pannello);
- `salva`: `corsi_lezione_salva_struttura()`; `impostazioni`: solo il titolo (`corsi_lezione_salva()`);
- `togli`: "Torna al contenuto della scheda" (la lezione torna al testo di prima, le versioni restano);
- `permesso`: solo l'amministratore (come tutte le pagine dei Corsi): `editor.php`, `editor-api.php` ed
  `editor-anteprima.php` rispondono 403 a un redattore;
- `anteprima`: la cornice della tela (`corsi_lezione_cornice()`): sopra la testa della lezione e, se c'è, una riga che dice
  dove sta il video; sotto una riga per materiali e "Segna come completata"; il foglio `corsi.css`.

Versioni: ambito `contenitore:corsi_lezione` (pagina Versioni, solo l'amministratore; anteprima e ripristino).

**Il pannello** (`admin-lezione.php`): al posto dell'area di testo un riquadro "Testo della lezione" con lo stato
(`data-stato` = `vecchio`, `bozza`, `pubblicata`) e un pulsante: "Scrivi il testo con l'editor visuale" (lezione senza
testo), "Apri il testo nell'editor visuale" (lezione di prima, con il testo di prima mostrato sotto), "Continua con
l'editor visuale" (bozza), "Modifica il testo con l'editor visuale" (pubblicato). Il pulsante è un `submit` del modulo
della lezione (`azione=editor`): salva titolo, video, materiali e durata, poi `corsi_lezione_prepara_editor()` e apre
`editor.php?tipo=corsi_lezione&id=N`. La freccia in alto a sinistra dell'editor torna alla lezione.

**Le lezioni di prima (2.0.0)** (`corsi_lezione_prepara_editor()`, `:315`). Alla prima apertura nell'editor l'albero nasce
dal testo di prima: una sezione "stretta" con dentro

- un widget **Testo**, se il widget tiene tutto: `editor_ricco_pulito($html)` e `sicurezza_html_pulito($html, 'articolo')`
  danno lo stesso HTML (a meno di spazi: `corsi_html_uguale()`, `:330`);
- altrimenti un widget **HTML** con il testo intero (tabelle, video incorporati, attributi che il widget Testo toglierebbe).

L'albero si salva in **bozza** (con il salvataggio normale: resta anche la prima versione), quindi i membri vedono il testo
di prima finché l'amministratore non preme Pubblica. La colonna `testo` non si tocca mai: senza `struttura` la lezione
la mostra come prima, e "Torna al contenuto della scheda" la rimette. Niente si perde.

**Sul sito** (`corsi_pagina_lezione()`, `pagine.php:169`; `corsi_lezione_editor_html()`, `:37`): con un albero pubblicato
la lezione esce così: testa e video nella colonna della lezione, poi le sezioni dell'editor a tutta larghezza (`.ed-pagina
.corsi-lezione-testo`, CSS degli elementi nella testa, fogli e script dei widget che servono, `editor-pagina.js` per video
e animazioni: come una pagina, `vista_pagina_editor()`), poi materiali, "Segna come completata", avanzamento e frecce.
Senza albero, il testo di prima in `.corpo.corsi-testo` come nella 2.0.0. Il controllo dell'accesso è lo stesso.

## Il programma da trascinare (2.1)

`admin-corso.php`: ogni modulo e ogni lezione ha una maniglia (`.co-maniglia-modulo`, `.co-maniglia-lezione`, il segno
&#10303;, `aria-hidden`); `assets/programma.js` crea due SortableJS (i moduli in `#co-moduli`, le lezioni di ogni
`ol.co-lez` nel gruppo `lezioni`: una lezione passa anche in un altro modulo, anche vuoto, `min-height`) con
`forceFallback` (lo stesso trascinamento con il mouse, il dito e le prove). Alla fine di uno spostamento manda l'ordine
intero in POST alla stessa pagina: `azione=ordine`, `ordine` = `[{"modulo": id, "lezioni": [id, ...]}, ...]`, `_csrf` della
pagina; risposta JSON (`admin-corso.php:18`). Poi aggiorna le frecce (la prima non sale, l'ultima non scende) e scrive
"Ordine salvato." in `#co-ordine-stato` (`role=status`); se il server dice di no, lo scrive e ricarica la pagina.

`corsi_riordina()` (`corsi.php:374`), tutto o niente: ogni id è un intero; ogni modulo e ogni lezione del corso c'è, una
volta sola, e nessun id di un altro corso (altrimenti 400 con l'errore, niente cambia); poi in una transazione `ordine` dei
moduli e `modulo_id` + `ordine` delle lezioni, con `AND corso_id = ?` in ogni UPDATE.

Le frecce (moduli e lezioni) restano, con `aria-label` ("Sposta «La luce» in su"): sono la strada per la tastiera.

## Pagine pubbliche (`includes/pagine.php`)

`corsi_pagina()` (`pagine.php:57`) dall'evento `sito_percorso` per i percorsi `/corsi/...` e `/certificato/...`. I POST
usano il token dell'Area riservata (`membri_post_ok()`). Pagine `noindex`, `private, no-store`.

| Indirizzo | Funzione | Chi |
|---|---|---|
| `/corsi/` | `corsi_pagina_elenco()` (88) | tutti; schede con avanzamento per il membro |
| `/corsi/<corso>/` | `corsi_pagina_corso()` (114) | tutti vedono il programma; i link alle lezioni solo chi può seguire il corso |
| `/corsi/<corso>/<lezione>/` | `corsi_pagina_lezione()` (169) | chi può seguire il corso (altrimenti accesso o 403); POST `azione=completa` o `da-fare` |
| `/corsi/<corso>/video/<id>/` | `corsi_pagina_video()` (220) | come la lezione; l'id deve essere di una lezione di quel corso |
| `/corsi/<corso>/materiale/<id>/<file>/` | `corsi_pagina_materiale()` (229) | come la lezione; il file deve essere fra i materiali della lezione e riservato |
| `/corsi/<corso>/certificato/` | `corsi_pagina_certificato()` (249) | il membro, solo il suo, a corso finito |
| `/certificato/` e `/certificato/<codice>/` | in `corsi_pagina()` e `corsi_certificato_pagina()` (257) | chiunque abbia il codice (verifica pubblica) |

Anteprima: con `?preview=1` e la sessione del pannello (`SITO_ANTEPRIMA`) l'amministratore vede anche i corsi in bozza e le
lezioni (con la bozza del testo, se non c'è ancora un testo pubblicato), senza avanzamento (`corsi_anteprima()`, `pagine.php:48`).

L'area riservata del membro ha la sezione "I miei corsi" (`corsi_sezione_area()`, `pagine.php:286`, filtro `membri_area`).

## Ganci del nucleo

Dalla 2.1 i contenitori dell'editor (`editor_contenitori` nel manifesto, `includes/editor.php`). Per il resto
`sito_percorso` del nucleo e i ganci dell'Area riservata (`membri_area`, `membro_eliminato`). La CSP del sito ammette già
`www.youtube-nocookie.com` e `player.vimeo.com` in `frame-src` (`includes/sicurezza.php`).

## Limiti noti

- Il video caricato pesa quanto permette il server (`upload_max_filesize`); per video lunghi meglio YouTube o Vimeo.
  Su Nginx la cartella dei video è protetta solo dal nome a caso (come i file riservati di Download).
- Il video incorporato di YouTube o Vimeo è visibile a chi ha il suo indirizzo su quei servizi: il controllo dell'accesso
  vale per la pagina della lezione, non per il video su YouTube (per video privati: video caricato, o Vimeo con dominio limitato).
- Il certificato ha il nome del membro del giorno in cui l'ha finito; cancellando l'account il certificato non vale più.
- Video e materiali restano nella pagina della lezione del pannello, fuori dall'editor (nella tela c'è una riga che lo dice).
- Le versioni di una lezione eliminata restano nella pagina Versioni (come quelle di una pagina eliminata).
- `tools/manuale-uso-schermate.js` (capitolo 39 del manuale d'uso, la lezione) scrive ancora in `#le-testo`: va rifatto
  con l'editor visuale insieme al capitolo.

## Prove

- `prove/prova-addon-corsi.js` (sito Attività nuovo, posta finta, ffmpeg per il video): 80 su 80 con la 2.1 (58 con la
  2.0.0). Nuovi: lezione scritta nell'editor visuale (cornice, Impostazioni con il solo titolo, Revisioni, Pubblica) e vista
  dal membro; due lezioni di prima (widget Testo, widget HTML con la tabella), la bozza che non cambia il sito, la colonna
  di prima intatta; riordino con le frecce e trascinando (anche in un altro modulo, anche vuoto) che resta dopo il
  ricaricamento; pannello al telefono.
- `prove/attacchi-addon-a.js`: lezioni, video e materiali di un corso non proprio, path traversal, certificato di un altro,
  XSS nei titoli; 2.1: HTML cattivo nel widget Testo di una lezione (script, onerror, `javascript:`, iframe esterni), editor,
  tela, API, revisioni e "torna indietro" di una lezione da redattore (403, con il suo token vero), riordino con lezioni o
  moduli di un altro corso, incompleto, con id non numerici, senza token, da un altro sito, da membro.
