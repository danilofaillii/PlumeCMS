# Header, footer e menu di navigazione (Theme Builder)

Scritto il 25 ottobre 2026. Una sezione per creare più header e più footer e scegliere quelli predefiniti; ogni pagina,
articolo o contenuto di un tipo dei moduli può avere header e footer suoi, diversi da quelli standard.

## Dove si trova nel pannello

- **Pagine > Header e footer** (`admin/modelli.php`): due schede, Header e Footer. Ogni riga è un modello con la
  stellina del predefinito, lo stato, le condizioni, l'uso. Azioni: Modifica (apre l'editor visuale), Anteprima sul sito,
  Condizioni, Rinomina, Duplica, Pubblica / Metti in bozza, Cestino. "+ Aggiungi" crea un modello in bozza e apre l'editor.
- **Pagine > Menu di navigazione** (`admin/menu.php`): come Aspetto > Menu di WordPress. A sinistra pagine, articoli,
  categorie e link personalizzato; a destra la struttura da ordinare e annidare trascinando (fino a tre livelli).
- Nella **scheda classica** di ogni articolo, pagina e contenuto dei tipi dei moduli: riquadro "Header e footer" con due tendine
  (Predefinito, Nessuno, ogni modello pubblicato). Le stesse due tendine stanno nelle **Impostazioni** dell'editor visuale.

## Come decide il sito quale modello mostrare (`modelli_scegli`)

1. La scelta del contenuto (`header_id`, `footer_id`: 0 predefinito, -1 nessuno, N quel modello).
2. Le condizioni dei modelli pubblicati: tutto il sito, blog e archivi, tutti gli articoli / pagine / contenuti di un tipo dei moduli,
   una categoria, un contenuto preciso. Vince la più specifica; a parità la priorità più alta; "Escludi" toglie il modello.
3. Il predefinito del tipo (la stellina).
4. Se non esiste nessun modello: la testata e il piede scritti in `sito/viste.php` (`vista_header_footer`), come prima.

In anteprima dal pannello, `/?preview=1&header=N` (o `footer=N`) forza un modello anche in bozza.

## Il primo avvio

`modelli_installa()` crea la tabella `<prefisso>modelli`, le colonne `header_id` e `footer_id` sui contenuti e i due modelli
"Header del sito" e "Footer del sito", costruiti con i widget del tema in modo da riprodurre la testata e il piede di prima
(screenshot identici al pixel su desktop e telefono, con uno scarto sotto il pixel nelle colonne del piede).
`menu_installa()` crea `<prefisso>menu` con "Principale" e i tre menu del piede.

## I widget del tema (`includes/editor-widget-tema.php`, gruppo "Tema" nella tavolozza)

Logo del sito, Nome del sito, Frase del sito, Menu di navigazione (orizzontale, verticale, a tendina; menu del telefono
sotto una larghezza a scelta, con voce in più; sottovoci a comparsa; segno della voce attiva), Ricerca (va a `/?s=`),
Titolo del contenuto, Testo del contenuto, Immagine in evidenza, Autore data e categorie, Briciole di pane, Copyright
(`{anno}`, `{sito}`). Ogni widget ha la scheda Stile con i controlli di gruppo del framework (tipografia, colori con stati,
bordi, spazi). Nome, sottotitolo, logo e frase, se lasciati vuoti, vengono dallo Stile del sito (`design_sito()`).

## Dettagli tecnici

- Le cornici: `<header class="ed-testata ed-modello">` e `<footer class="ed-piede ed-modello">`, CSS in
  `sito/assets/tema.css` (riproduce `.top` e `.piede`). Dentro un header le colonne sono righe orizzontali.
- I modelli si modificano con `editor.php?tipo=modelli&id=N`: la tela mette l'header (o il footer) al suo posto e un
  segnaposto per il contenuto. Revisioni in `<prefisso>pagine_revisioni` con `tipo = modelli`.
- Il menu salva le voci in un JSON ad albero; una voce che punta a una pagina, a un articolo o a una categoria ricorda lo
  slug e ricalcola l'indirizzo (`menu_voci_risolte`).
