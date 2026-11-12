# Editor delle pagine

Deciso il 23 ottobre 2026: un editor come Elementor, dentro il CMS, senza WordPress. Il modello è quello: la pagina è un albero di **sezioni**, dentro **colonne**, dentro **widget**; ogni elemento ha le sue impostazioni divise in schede (Contenuto, Stile, Avanzate); le misure di schermo sono tre (desktop, tablet fino a 1024 px, telefono fino a 767 px) e un valore può cambiare misura per misura; si trascina; si annulla; si salva in bozza e si pubblica; ogni pubblicazione lascia una revisione.

## Dove sta

| Pezzo | File |
|---|---|
| Definizioni dei widget e loro disegno in HTML | `includes/editor-widget.php` |
| Albero, controllo dei dati, CSS per elemento, lettura e salvataggio, revisioni, punti di partenza | `includes/editor.php` |
| Pannello (barra, widget, controlli, navigatore, dialoghi) | `admin/editor.php`, `admin/assets/editor/editor.js`, `editor.css` |
| Tela: la pagina vera dentro un iframe | `admin/editor-anteprima.php`, `admin/assets/editor/anteprima.js`, `anteprima.css` |
| Chiamate: disegna, salva, revisioni, impostazioni, immagini | `admin/editor-api.php` |
| Trascinamento | `admin/assets/editor/Sortable.min.js` (SortableJS 1.15.6, MIT, preso con `npm pack`, servito in casa) |
| Testo ricco | Quill, già in `admin/assets/quill/` per la newsletter |
| Stili pubblici dei widget (`.ed-*`) | `sito/assets/sito.css`, in fondo |
| Video al clic | `sito/assets/editor-pagina.js` |

## Nel database

`<prefisso>pages` ha tre colonne in più, create da `sito_schema_aggiorna()` con la sua impronta:

- `struttura`: la versione pubblicata, in JSON
- `struttura_bozza`: quella in lavorazione
- `struttura_aggiornata`: ultima modifica

Le versioni (aggiornato al 31 ottobre 2026): non più `<prefisso>pagine_revisioni` con le ultime 30, ma la tabella unica `<prefisso>editor_versioni` (`includes/versioni.php`), con bozze e pubblicazioni di ogni pagina, articolo, modello, menu e stile; se ne tengono 50 per oggetto (da 5 a 500, Editor > Impostazioni), mai potate le pubblicate e quelle con una nota scritta a mano. Dettagli nel capitolo 13 del manuale tecnico.

Il JSON:

```json
{"versione":1,"elementi":[
  {"id":"a1b2c3d","tipo":"sezione","impostazioni":{"sfondo":"vetro","spazio_sopra":{"d":72,"m":48}},"figli":[
    {"id":"e4f5g6h","tipo":"colonna","impostazioni":{"larghezza":{"d":50,"m":100}},"figli":[
      {"id":"i7j8k9l","tipo":"titolo","impostazioni":{"testo":"Tre passi. *Uno alla volta.*","livello":"h2"}}
    ]}
  ]}
]}
```

I valori responsive sono `{"d":..,"t":..,"m":..}`: tablet e telefono, se mancano, ereditano dalla misura più grande. Gli id sono di 7 lettere e numeri; il CSS di ogni elemento si genera sulla classe `.ed-ID`, con le media query, e finisce in un `<style id="ed-css">` nella testa della pagina.

Emoji e caratteri a 4 byte: `editor_json()` li scrive come coppie `\ud83d\ude00` dentro il JSON, così la colonna utf8 a 3 byte non taglia niente. Decodificati tornano uguali.

## Sul sito pubblico

Una pagina con `struttura` esce disegnata dall'editor (`sito_pagina_editor()` in `sito-dati.php`, `vista_pagina_editor()` in `viste.php`): sezioni a tutta larghezza, senza il `.wrap` e l'H1 forzati della pagina generica. Senza `struttura`, esce l'HTML portato da WordPress, come prima. Vale anche per la home: con `struttura` esce quella, altrimenti `vista_home()`.

Le domande dei widget FAQ entrano nel JSON-LD `FAQPage`, insieme a quelle del campo `faq` della pagina.

**Niente cambia da solo.** Nessuna pagina viene convertita. Chi amministra il sito apre una pagina nell'editor e sceglie da dove partire: la grafica di oggi (solo la home, rifatta in widget da `editor_albero_home()`, che un modulo del sito può dare con il filtro omonimo), il contenuto di oggi (l'HTML intero in widget HTML, da `editor_albero_da_html()`; un modulo Brevo incollato fra i segnaposti `Begin/End Brevo Form` diventa il widget Modulo della newsletter) oppure pagina vuota. Fino a Pubblica, il sito mostra la pagina di prima.

Il contratto dei 1.005 indirizzi regge: l'editor non tocca gli indirizzi. Lo slug si cambia solo su una pagina mai pubblicata e mai venuta da WordPress.

## I widget

| Gruppo | Widget |
|---|---|
| Base | Titolo (con occhiello e sottotitolo, H1-H4), Testo (Quill), Immagine, Pulsanti, Spazio, Linea, Elenco |
| Contenuto | Frase in evidenza, Passi numerati, Schede con link, Presentazione, Apertura (hero), Numeri, Domande frequenti, Video |
| Dal sito | Ultimi articoli (`sito_ultimi`, filtro pillar e categoria), Modulo (newsletter, form del pannello e i moduli dei moduli del sito: `vista_modulo`, `moduli.md`) |
| Avanzati | HTML |

Nei titoli e nelle frasi, `*parola*` diventa `<em>` con il gradiente del sito. Il widget Video non chiama YouTube o Vimeo finché la persona non preme play (`youtube-nocookie`, `dnt=1`): nessun cookie di terzi al caricamento.

Controlli comuni (scheda Avanzate): spazio sotto, ancora, nascondi su desktop/tablet/telefono, classe CSS. La sezione ha larghezza (contenuta, stretta, piena), altezza minima, allineamento verticale, spazio tra colonne, sfondo (nessuno, vetro, gradiente, colore, immagine con velo), colori, spazi. La colonna ha larghezza in percentuale per misura, riquadro di vetro, spazio interno, allineamento del testo.

Per aggiungere un widget (aggiornato al 31 ottobre 2026): non più una voce in `editor_definizioni()` e un `case` in `editor_disegna_widget()`, ma `editor_registra_widget($tipo, $definizione, $disegna)` da un file in `includes/editor-widget*.php`, da un modulo o da un addon, con i suoi fogli e script dichiarati con `editor_registra_asset()` (capitolo 9.8 del manuale tecnico).

## Nel pannello

Voce **Pagine**: ogni pagina ha il pulsante **Editor**; **+ Nuova pagina** crea una bozza `nuova-pagina` e la apre. La voce **Homepage** del menu apre la home nell'editor. La scheda vecchia (`page-edit.php`) resta dietro l'icona della matita.

Nell'editor:

- **Widget**: trascina nella pagina, oppure clic (entra sotto l'elemento selezionato).
- **Modifica**: i controlli dell'elemento selezionato. Un clic nella tela seleziona; doppio clic apre Modifica. L'icona accanto a un controllo dice la misura: piena se il valore è proprio di quella misura, sbiadita se eredita; clic per tornare a ereditare.
- **Navigatore**: l'albero.
- Barra: tre misure, annulla e ripeti (Ctrl+Z, Ctrl+Y), Impostazioni (titolo, indirizzo, titolo e descrizione per Google, noindex, stato), Revisioni (carica nella tela; **Torna alla pagina di prima** toglie la struttura), Vedi la pagina, Salva bozza (Ctrl+S; anche da sola ogni 45 secondi se ci sono modifiche), Pubblica.
- Nella tela: la maniglia in alto a destra trascina la sezione, quella in alto al centro trascina la colonna, i widget si trascinano direttamente; il `+` sotto una sezione ne aggiunge una; la barra sopra l'elemento selezionato sposta, duplica, elimina.
- Il titolo in alto diventa arancione se la pagina non ha un H1.

Le immagini caricate dall'editor vanno in `pagine/AAAA/MM/` dentro la cartella delle immagini (`cms_uploads_dir()`, di solito `uploads/`, mai `wp-content/uploads/pagine`): JPG, PNG, GIF, WebP, AVIF e SVG (ripulito da script ed entità), massimo 8 MB (`media_carica()`). Dalla 2.1.0 la stessa chiamata (`editor-api.php` azione `carica`) la usa la finestra Scegli dai Media di tutto il pannello.

## Sicurezza

Tutte le chiamate sono POST con il token di sessione. L'albero viene ripulito a ogni salvataggio e a ogni disegno (`editor_valida`): tipi sconosciuti scartati, numeri nei limiti, colori esadecimali, `rgb()`/`rgba()`, `hsl()`/`hsla()`, `transparent`, `globale:<id>` e `ig:<id>`, link `http(s)`, `/`, `#`, `?`, `mailto:`, `tel:` (dalla 2.0.2 `www.sito.it` e `sito.it/pagina` diventano `https://...` e un nome di pagina diventa `/nome`; ogni altro schema resta fuori, `editor_link_sicuro()`), testo ricco senza script e senza attributi (`editor_ricco_pulito`). Il widget HTML passa com'è solo per l'amministratore: per chi non lo è ogni blocco HTML nuovo o cambiato diventa testo pulito (`editor_blinda_codice()`, `editor_puo_codice()`).

## Collaudo del 23 ottobre

Sul sito di prova con MySQL 8.0.46, dal browser: home rifatta in widget identica a quella di oggi sulle tre misure; selezione, modifica del titolo, widget nuovo, telefono, annulla e ripeti, bozza, duplica ed elimina, pubblicazione, tre revisioni, il sito mostra la versione pubblicata; una pagina dal contenuto di oggi con il modulo al suo posto; trascinamento di un widget sopra l'altro e in un'altra colonna; sezione a due colonne; larghezza di colonna solo su telefono; pagina nuova con indirizzo e stato dalle impostazioni, pubblicata. Contratto dei 1.005 indirizzi: 0 differenze. Giro del pannello: tutte le pagine a 200.

Pacchetto: `releases/pacchetti/2026-10-23-editor-pagine.zip`, cumulativo (contiene anche newsletter e moduli).

## Libreria Media (sera del 23 ottobre)

Nella voce Media compaiono anche le immagini di un sito venuto da WordPress (`wp-content/uploads`). Motore in `includes/media.php`, usato sia da `admin/media.php` sia dalla finestra "Scegli" dell'editor: WordPress per anno (`AAAA/MM`), pillar e contenuti, editor delle pagine, newsletter, copertine SVG degli articoli, vecchi caricamenti del pannello. Le miniature di WordPress (`-300x300` e simili) stanno nascoste finché non si spunta la casella; la cartella `elementor/` (cache del plugin) resta fuori. Filtro per cartella, ricerca nel nome e nel percorso, 60 per pagina. Il caricamento va in `uploads/pagine/AAAA/MM/` e accetta anche SVG senza script. Si cancella solo dalle cartelle nostre (pagine, newsletter, vecchi caricamenti). Sul sito di prova: 548 immagini lette in 53 ms.

## Articoli nell'editor

Un articolo aperto nell'editor entra in una sezione di lettura con un widget HTML (`editor_albero_da_articolo()` in
`editor.php`). Un modulo del sito può convertirlo in blocchi veri, un widget per capitolo, testo, immagine, tabella,
con il filtro `editor_albero_articolo`. La colonna `struttura_origine` dice da dove viene la struttura (`conversione N data`
se l'ha scritta una conversione, vuota se nasce dall'editor), così una conversione ripetuta lascia stare gli articoli
salvati dall'editor.

**Sul sito**: `vista_articolo_editor` riconosce un articolo fatto solo di sezioni "Articolo" e lo mette nella cornice
degli articoli (testata, sommario laterale, colonna di lettura da 700 px, FAQ). Un articolo con anche sezioni a tutta
larghezza esce come una pagina dell'editor. L'anteprima dell'editor fa lo stesso. CSS in fondo a `sito.css`
(`.ed-articolo-pagina`, `.scheda`).

Fra i widget nati per gli articoli: Tabella, Riquadro in evidenza (`riquadro_test`), Invito (`cta`, classe `article-cta-box`),
Leggi anche.

### Le infografiche per tipo

**14 widget `ig_*`** in `includes/editor-widget.php` (gruppo Infografiche, CSS `igx-*` in `sito.css`): `ig_passi` (passi numerati), `ig_timeline` (linea del tempo), `ig_checklist`, `ig_tabella` (tabella comparativa: criteri in riga, due colonne con intestazione), `ig_liste` (liste affiancate), `ig_numeri` (numeri grandi), `ig_card` (griglia di schede, anche con emoji), `ig_mappa` (mappa mentale, nodo centrale e rami, con `colonne`), `ig_flusso` (diagramma di flusso: nodi, frecce, rami, `larghezza_nodi`, bordo per nodo, etichette colorate), `ig_fasi` (blocchi, imbuto, righe, schede, piene; `tinta` crescente o calante; colore per fase), `ig_barre` (barre con percentuale), `ig_prima_dopo` (scenari), `ig_zone` (termometro e zone), `ig_domande` (domande con due risposte). Le figure disegnate da `infographic.php` restano sul widget "Infografica".

**Ogni oggetto è modificabile**: testata con colore o sfumatura libera, colore del testo, allineamento, occhiello; corpo con sfondo (crema, bianco, scuro, libero), bordo, angoli, ombra, spaziatura, dimensione del testo, carattere; per ogni voce colore di palette o libero, segno o emoji, forma del segno; le varianti di forma come campo `forma`.

**Temi** (`includes/editor-temi.php`, tabella `<prefisso>editor_temi`, azione `temi` in `admin/editor-api.php`, pannello in `editor.js`): 10 preimpostati (Carta è l'aspetto originale delle infografiche, poi Notte, Ardesia, Salvia, Terracotta, Oro, Viola profondo, Ciano, Bianco e nero, Pastello); il campo Tema applica i valori ai campi di stile, che restano modificabili; "Salva come tema" chiede un nome; Rinomina ed Elimina per i temi salvati. Un widget nuovo parte con Carta.

### Nuovo contenuto = editor visuale (25 ottobre, pomeriggio)

Creando una pagina o un articolo parte l'editor visuale. Il pulsante "+ Aggiungi" degli elenchi (articoli, pagine e i tipi dei moduli) e i pulsanti rapidi della dashboard portano a `editor.php?nuova=1&tipo=...`: il contenuto nasce in bozza con `contenuti_salva()` (titolo "Nuovo articolo" / "Nuova pagina", per i tipi dei moduli la chiave `nuovo`; indirizzo libero), riceve una struttura di partenza (pagine: titolo H1 e un testo; articoli e altri tipi: una sezione "Articolo" con un testo) e si apre nell'editor. Titolo, indirizzo e il resto si cambiano da Impostazioni dell'editor o dalla scheda. Negli elenchi resta il pulsante "Scheda classica" per chi vuole la scheda senza editor.

### Tre correzioni dal primo uso vero (25 ottobre, sera)

Dal primo uso su un server vero: (1) nell'editor il testo di un widget selezionato sbordava a destra senza andare a capo; (2) "Vedi la pagina" su una bozza dava 404; (3) in un articolo un link stava su una riga da solo, con il paragrafo spezzato in tre.

- Il paragrafo spezzato veniva da `markdown_to_html()`: una riga che comincia con un tag (`<a>`, `<strong>`...) veniva trattata come blocco e chiudeva il paragrafo. Ora solo i tag di blocco lo chiudono; i tag inline continuano il paragrafo. Vale per il sito e per la conversione: gli articoli in markdown escono con i paragrafi interi.
- Lo sbordamento non si riproduce in locale (colonna a 700 px anche dopo la modifica). Chiuso da due lati: `editor_ricco_pulito()` trasforma gli spazi insecabili (da Word o da copia e incolla) in spazi normali, e il CSS obbliga il testo dei widget ad andare a capo (`overflow-wrap:anywhere`, `white-space:normal`).
- "Vedi la pagina" su un contenuto non pubblicato apre `/slug/?preview=1`: con la sessione del pannello il sito mostra la bozza, anche quella dell'editor non ancora pubblicata (`sito_pagina_editor` legge `struttura_bozza` in anteprima). Un contenuto pubblicato apre l'indirizzo pubblico.

## Controlli di gruppo, scheda Avanzate e Stile di ogni widget (25 ottobre 2026, notte)

L'editor è la copia di Elementor: i colori di ogni cosa si possono cambiare. Il framework sta in
`includes/editor-controlli.php`; il contratto per chi scrive widget in altri file è lo stesso documento di lavoro
(`CONTROLLI-CONTRATTO.md` dell'audit, riassunto qui).

**Tipi di controllo nuovi** (oltre a testo, testolungo, ricco, numero, scelta, interruttore, colore, immagine, link, ripetitore, codice):
`misura` (valore + unità px/%/em/rem/vw/vh, cursore e casella, `{"v":12,"u":"px"}`), `dimensioni` (quattro lati con lucchetto e unità,
`{"sopra","destra","sotto","sinistra","u","legati"}`), `allineamento` e `scelta_icone` (pulsanti con icone), `tipografia` (famiglia,
dimensione per misura, peso, stile, maiuscole, decorazione, interlinea, spaziature), `sfondo` (classico con colore, immagine, posizione,
ripetizione, dimensione, allegato e velo; oppure sfumatura a due colori, lineare o radiale, con angolo), `bordo` (tipo, spessore per lato,
colore, raggio per angolo), `ombra` e `ombra_testo`, `trasformazione` (rotazione, scala, spostamento), `icona` (griglia con ricerca sul set
di `includes/icone.php`), `galleria`, `data_ora`, `carattere`, `intestazione`. Ogni controllo può essere `responsive` (un valore per
desktop, tablet, telefono: il pulsante accanto all'etichetta dice se il valore è proprio o ereditato) e avere `stati` (le due linguette
Normale / Al passaggio del mouse). I controlli stanno in sezioni a tendina (`sezione`) e possono dipendere da altri (`condizione`).

**Il CSS esce dai controlli.** Un controllo dichiarato con `selettore` (per esempio `' .ed-titolo'`, relativo al contenitore `.ed-ID`
del widget) scrive da solo le sue regole, per misura e per stato: chi disegna un widget non legge i valori di stile. Un valore vuoto
non scrive niente: così i valori di partenza riproducono il sito di oggi. I colori accettano i globali dello Stile del sito
(`globale:primario` → `var(--g-colore-primario, #...)`), le famiglie pure (`globale:testo` → `var(--g-font-testo, ...)`).

**Scheda Avanzate**, uguale per sezioni, colonne e widget, come Elementor: Layout (margine, padding, larghezza del widget piena / in
linea / personalizzata con allineamento, z-index, ID CSS che è anche l'ancora, classi, tag HTML per sezioni e colonne), Effetti di
movimento (animazione all'ingresso con durata e ritardo: dissolvenza, dal basso, dall'alto, da sinistra, da destra, zoom; sticky in alto
o in basso con distanza e classe `ed-sticky-attivo` dopo N px), Trasformazione (normale e al passaggio), Sfondo e Bordo con ombra e
transizione (normale e al passaggio; per sezioni e colonne stanno nello Stile), Posizionamento (assoluta dentro la sezione o fissa, con
spostamenti da sinistra/destra e alto/basso), Responsive (nascondi per misura, inverti le colonne su tablet e telefono), Attributi
(`chiave=valore`, solo data-*, aria-*, title, lang, role, tabindex, rel, itemprop), CSS personalizzato (`selector` diventa `.ed-ID`).
Le animazioni e lo sticky li anima `sito/assets/editor-pagina.js`, caricato solo se la pagina ne ha bisogno (`editor_serve_script`).

**Scheda Stile di ogni widget**: colore e tipografia di ogni parte (titolo, occhiello, testo, link, didascalia, numero, invito), sfondo,
bordo, ombra e spazio interno dei riquadri (passi, schede, numeri, domande, articoli, modulo, tabella, scheda riassuntiva), stato al
passaggio dove ha senso (link, pulsanti, schede). Le sezioni hanno sfondo avanzato (sfumatura, immagine, al passaggio), bordo, ombra,
colori del testo, dei titoli e dei link, tipografia, larghezza personalizzata in pixel, overflow; le colonne sfondo, bordo, ombra,
spazio fra i widget, allineamento dei widget, colori e tipografia.

**Leggi anche, Invito, Riquadro in evidenza** non hanno più stili scritti dentro l'HTML: hanno classi (`ed-leggi-*`, `ed-cta-*`,
`ed-test-*`) e i loro colori sono controlli con i valori di oggi come partenza. "Leggi anche" cambia i colori di partenza: titolo e
schede leggibili sul fondo scuro (prima il titolo era marrone su blu), con il bordo che si accende al passaggio.

**Widget scritti in altri file**: `editor_registra_widget(tipo, definizione, disegna)` in `includes/editor-widget-tema.php` ed
`editor-widget-extra.php`, che `editor_definizioni()` include da sola (un file rotto finisce nel log, il sito resta in piedi).
`editor_registra_asset()` dice quali css e js caricare sul sito solo se la pagina contiene quel widget.

Prove: `audit3/controlli/prova-controlli.js` (Playwright: ogni tipo di controllo cambiato dal pannello e verificato nella tela e sul
sito dopo la pubblicazione), `conv-prova.php` 178 su 178, `prova-editor-ig.js` delle infografiche ancora tutto OK,
`verifica_contratto.py` a 0 differenze.

## I trenta widget di Elementor e il set di icone (25 ottobre 2026, notte)

`includes/icone.php`: 252 icone SVG disegnate in casa (24x24, tratto 2, `currentColor`, 14 categorie, social a riempimento), funzioni
`icone_elenco()`, `icone_set()`, `icona_svg($id)`. Il selettore di icone del pannello cerca per nome italiano.

`includes/editor-widget-extra.php` (con le parti `-a`, `-b`, `-c`) registra trenta widget con `editor_registra_widget()`; l'aspetto sta in
`sito/assets/widget.css` (42 KB) e il comportamento in `sito/assets/widget.js` (20 KB), caricati sul sito solo se la pagina contiene uno di
questi widget. I controlli di stile scrivono variabili `--ew-*` sul widget, che il CSS legge con i valori di partenza. Niente librerie
esterne, accessibili (tab, fisarmoniche, caroselli, lightbox, timer), funzionano senza JS dove possibile.

| Tipo | Nome nel pannello | Gruppo | Elementor | Contenuto | Stile |
| --- | --- | --- | --- | --- | --- |
| `icona` | Icona | Elementi | Icon (free) | icona dal set, vista (semplice, impilata, incorniciata), forma, link, allineamento | colori principale e secondario (normale/hover), dimensione, spazio intorno, rotazione, spessore cornice, angoli, animazione hover |
| `riquadro_icona` | Riquadro con icona | Elementi | Icon Box | icona, vista, forma, titolo, testo, livello, link, posizione icona (sopra, sinistra, destra), allineamento | icona (colori, dimensione, spazio), titolo e testo (colore, tipografia, spazi), riquadro (sfondo, bordo, ombra, padding) |
| `riquadro_immagine` | Riquadro con immagine | Elementi | Image Box | immagine, alt, titolo, testo, livello, link, posizione, allineamento verticale, allineamento | larghezza immagine, spazio, bordo e angoli, opacita hover, titolo, testo, riquadro |
| `pulsante` | Pulsante | Elementi | Button | testo, link, stile (viola, ciano, contorno, neutro), dimensione xs..xl, icona e posizione, allineamento, largo, id | tipografia, colore, sfondo, bordo, ombra, padding (normale/hover), animazione hover |
| `elenco_icone` | Elenco con icone | Elementi | Icon List | voci (testo, icona, link), verticale o in riga, divisore, spazio, allineamento | divisore (stile, colore), icona (colore hover, dimensione, spazio, allineamento verticale), testo (colore hover, tipografia) |
| `valutazione` | Valutazione a stelle | Elementi | Star Rating | voto decimale, scala 5 o 10, simbolo, titolo e posizione, allineamento | dimensione, spazio, colore piene e vuote, titolo |
| `contatore` | Contatore animato | Numeri e tempo | Counter | da, a, durata, prefisso, suffisso, separatore migliaia, titolo, allineamento | numero e titolo (colore, tipografia), spazio |
| `barra_avanzamento` | Barra di avanzamento | Numeri e tempo | Progress Bar | titolo, percentuale, mostra percentuale, testo interno | colore barra e sfondo, altezza, angoli, testo interno, titolo |
| `conto_alla_rovescia` | Conto alla rovescia | Numeri e tempo | Countdown (Pro) | scadenza (data e ora), unita on/off, etichette, alla scadenza (resta, sparisce, messaggio, vai a), allineamento | riquadri (sfondo, bordo, larghezza, spazio, padding), numeri, etichette |
| `testimonianza` | Testimonianza | Elementi | Testimonial | testo, foto, nome, ruolo, stelle, posizione foto (sopra, sotto, sinistra, destra), allineamento | testo, foto (dimensione, bordo, spazio), nome, ruolo, stelle, riquadro |
| `carosello_testimonianze` | Carosello di testimonianze | Gallerie | Testimonial Carousel (Pro) | voci, aspetto (riquadro, fumetto), opzioni di scorrimento, stile testimonianza | frecce, pallini, spazio |
| `galleria` | Galleria | Gallerie | Basic Gallery + Gallery (Pro, ridotta) | immagini (controllo galleria), disposizione (griglia, mosaico, giustificata), colonne per misura, altezza riga, al clic (lightbox, link, niente), didascalia (nessuna, sotto, al passaggio) | proporzioni, spazio, bordo e angoli, effetto hover (zoom, schiarisce, grigio), velo, didascalia |
| `carosello` | Carosello di immagini | Gallerie | Image Carousel | immagini, elementi visibili per misura, scorri di, frecce, pallini, autoplay con intervallo, pausa al passaggio, ciclo, clic e didascalia | spazio, frecce (posizione, dimensione, colori hover), pallini (dimensione, colori), immagini |
| `fisarmonica` | Fisarmonica | Interazione | Accordion | voci (titolo, contenuto ricco), livello, prima aperta, una sola aperta, schema FAQ, icone chiusa/aperta e posizione | voci (bordo, spazio), titolo (sfondo e colore normale/hover/aperta, tipografia, padding), icona, contenuto |
| `alterna` | Interruttore (toggle) | Interazione | Toggle | come la fisarmonica, con piu voci aperte insieme | idem |
| `schede_tab` | Schede (tab) | Interazione | Tabs | schede (titolo, icona, contenuto ricco), direzione, allineamento linguette, su telefono (schede o fisarmonica), scheda iniziale | linguette (colori normale/hover/attiva, bordo, tipografia, padding, spazio), contenuto |
| `social` | Icone social | Marketing | Social Icons | profili (15 reti, link, etichetta), forma, colori ufficiali o personalizzati, colonne, allineamento | dimensione, spazio intorno, spazio, colori personalizzati hover, bordo, animazione hover |
| `condividi` | Condividi | Marketing | Share Buttons (Pro) | reti (Facebook, X, LinkedIn, WhatsApp, Telegram, email, copia link, stampa), vista, aspetto, forma, colori, colonne, allineamento, indirizzo e testo | dimensione, spazio, padding, colori personalizzati, tipografia, animazione hover |
| `avviso` | Avviso | Elementi | Alert | tipo (informazione, successo, attenzione, pericolo), titolo, testo, icona, chiudibile | sfondo, bordo, spessore sinistro, angoli, padding, titolo, testo, icona |
| `ancora_menu` | Ancora di menu | Elementi | Menu Anchor | nome | (nell'anteprima si vede un segnaposto con il nome) |
| `citazione` | Citazione | Elementi | Blockquote (Pro) | aspetto (bordo, virgolette, riquadro, pulito), testo, autore, allineamento, pulsante di condivisione (X, LinkedIn, WhatsApp, copia) | testo, autore, bordo e virgolette, riquadro, pulsante |
| `invito_libero` | Invito all'azione | Marketing | Call to Action (Pro) | aspetto (classico, copertina), immagine e posizione, icona, titolo, testo, pulsante, link su pulsante o tutto, nastro, allineamenti, altezza minima | effetto hover immagine, veli, larghezza immagine, titolo, testo, icona, pulsante (completo), nastro, riquadro |
| `flip_box` | Flip box | Gallerie | Flip Box (Pro) | fronte (icona o immagine, titolo, testo, sfondo), retro (titolo, testo, pulsante, link, sfondo), effetto (gira, scivola, spinge, zoom, dissolve), direzione, altezza, allineamenti | padding, bordo, icona, immagine, testi dei due lati, pulsante |
| `titolo_animato` | Titolo animato | Marketing | Animated Headline (Pro) | modo (parola evidenziata con disegno SVG: cerchio, sottolineatura, doppia, zigzag, barrato, evidenziatore, X; oppure parole che si alternano: scrittura, scorri, dissolvi, zoom, gira), testi, livello, link, ciclo, durata | titolo, parole, colore e spessore del disegno, disegno sopra il testo |
| `listino` | Listino | Marketing | Price List (Pro) | voci (titolo, prezzo, descrizione, immagine, link), separatore (puntini, linea), posizione immagine, riga fra le voci | titolo, prezzo, descrizione, separatori, immagine |
| `tabella_prezzi` | Tabella prezzi | Marketing | Price Table (Pro) | intestazione, valuta e posizione, prezzo, decimali, prezzo barrato, periodo, voci con icona, pulsante, nota, nastro, in evidenza | intestazione, prezzo, voci, pulsante, nota, nastro, riquadro |
| `sommario` | Sommario | Interazione | Table of Contents (Pro) | titolo, livelli (H2, H2+H3, H2..H4), contenitore, esclusioni, segno (numeri, pallini, niente), gerarchico, richiudibile e stato iniziale, sticky con distanza, evidenzia la voce letta | testata, titolo, icona, voci (colori hover e attiva, tipografia, spazio, rientro, altezza massima), riquadro |
| `griglia_contenuti` | Griglia di articoli | Dal sito | Posts (Pro) | fonte (articoli, i tipi di contenuto dei moduli, tutti), categoria, tag, solo pillar, escludi corrente, ordine, quanti, salta, aspetto (classico, schede del sito, compatto, copertina), colonne, paginazione (numeri o carica altri), cosa mostrare (immagine e proporzioni, livello, riassunto e lunghezza, data, categoria, autore, link Leggi), messaggio vuoto | spazio, scheda, immagine, titolo hover, meta, riassunto, link, paginazione |
| `mappa` | Mappa | Elementi | Google Maps | Google Maps (indirizzo, zoom, strade o satellite) oppure OpenStreetMap (coordinate), caricamento al clic con testo del pulsante | altezza, bordo e angoli, scala di grigi, opacita |
| `pulsante_fisso` | Pulsante fisso (torna su) | Elementi | Floating button / Scroll to top | tipo (torna su, link, WhatsApp con numero e messaggio), icona, etichetta, descrizione, angolo, compare dopo N px, distanze | dimensione, colori hover, angoli, ombra |

Non fatti (priorità B o C della specifica): slide, hotspot, playlist video, audio, filtri della galleria, effetti del carosello oltre lo
scorrimento, conto alla rovescia evergreen, contatori di condivisione (servizi esterni).

Prove: `audit3/widget/prova-sito.js` (42 controlli sul sito con la pagina di prova in bozza `prova-widget-extra`, interazioni e telefono
senza scorrimento orizzontale) e `prova-editor.js` (23 controlli nell'editor: tavolozza, selettore icone, Stile, pubblicazione).

## Widget e Impostazioni dell'editor (26 ottobre 2026)

Il gruppo **Editor** del menu (`docs-sviluppo/pannello.md`) ha due pagine nuove che parlano con l'editor, più i Colori delle infografiche e le Versioni.

**Widget** (`admin/editor-widget.php`): l'elenco di `editor_definizioni()` senza sezione e colonna, per gruppo, con la provenienza. `editor_registra_widget()` scrive nella definizione la chiave `origine` (`nucleo`, oppure `modulo:<nome>` quando il file che registra sta in `moduli/<nome>/`); i widget scritti direttamente in `editor-widget.php` (base, contenuto, `ig_*`) sono del nucleo. L'interruttore salva i tipi spenti in `editor_widget_disattivi`; `admin/editor.php` li passa all'editor come `D.widgetDisattivi` e `disegnaElencoWidget()` in `editor.js` li salta. Niente altro cambia: `editor_disegna()` continua a disegnare ogni widget che trova nell'albero, la scheda Modifica si apre anche su un widget spento, e un tipo spento che un modulo non registra più resta spento finché il modulo torna.

**Impostazioni dell'editor** (`admin/editor-impostazioni.php`, motore `includes/editor-opzioni.php`): `editor_opzioni()` legge le righe `editor_*` della tabella delle impostazioni con i predefiniti di `EDITOR_OPZIONI_PREDEFINITE` (`versioni_limite` 50, `pubblica` tutti, `colonne_max` 4, `animazioni` 1, `widget_disattivi` vuoto), `editor_opzioni_salva()` le scrive dopo la pulizia e registra una versione dell'ambito `impostazioni` tramite `versioni_impostazioni_salva()`. Chi le legge: `editor.js` (`D.colonneMax` per i pulsanti della Struttura di una sezione), `admin/editor.php` e `editor-api.php` (`editor_puo_pubblicare()`: con "solo admin" il redattore vede Pubblica spento e l'API risponde 403 tenendo la bozza), `editor_attributi_avanzate()` (`editor_animazioni_attive()`: spente, le classi `ed-anima-*` non escono e il sito resta fermo; le scelte della scheda Avanzate restano salvate), `includes/versioni.php` (`versioni_limite()`).

**Esporta e importa.** `editor_esporta()` mette in un array `formato: editor-impostazioni, versione 1`: lo Stile del sito completo (`design_valori()`), gli header e i footer non cestinati con struttura, bozza, condizioni, priorità, stato e predefinito, i menu con le voci, i temi salvati, le opzioni e i widget spenti; `editor_esporta_json()` lo scrive in JSON solo ASCII. `editor_importa_leggi()` controlla il file (formato, versione, forma degli elenchi), `editor_importa_anteprima()` dice cosa cambierebbe (stile e opzioni chiave per chiave con prima e dopo; modelli per tipo e nome, menu e temi per nome: nuovo, cambia o identico) e `editor_importa()` applica gli ambiti scelti passando dai salvataggi normali (`design_salva`, `modelli_crea` più aggiornamento, `menu_salva`, `editor_tema_salva`), così ogni cosa importata lascia la sua versione con la nota "importazione da file". Niente si cancella: un modello o un menu che nel file non c'è resta. Il JSON si porta in un'altra installazione del CMS: i test del 26 ottobre lo hanno esportato dal sito di prova e riportato il colore primario dopo averlo cambiato, e lo hanno esportato dall'installazione pulita con prefisso `cms_`.
