# Versioni: la cronologia di ogni modifica dell'editor

Dal 26 ottobre 2026 ogni cosa salvata con l'editor, o con una delle sue voci del pannello, lascia una **versione**: una copia
dei dati con la data, chi l'ha fatta e una nota. Da lì si vede cosa è cambiato, si apre l'anteprima di com'era una pagina e si
torna indietro senza perdere niente. Una sola tabella, `{prefisso}editor_versioni`; motore in `includes/versioni.php`;
pagina del pannello **Editor > Versioni** (`admin/versioni.php`).

## Cosa finisce in versione

| Ambito | Quando nasce una versione | Nota automatica |
|---|---|---|
| Pagine, articoli, aforismi (e ogni tipo di contenuto dei moduli) | ogni *Salva bozza* e ogni *Pubblica* nell'editor; il cambio dell'header o del footer scelti per il contenuto; ogni salvataggio della scheda classica | `bozza`, `pubblicato`, `header: Header 2 al posto di Predefinito`, `scheda: prima della modifica` |
| Header e footer | *Pubblica* e *Salva bozza* nell'editor del modello; la stellina del predefinito; pubblica / metti in bozza; le condizioni | `predefinito: Header 2 al posto di Header del sito` (e una riga sul modello che non lo è più), `condizioni cambiate` |
| Stile del sito | ogni *Salva*, *Versione precedente*, *Ripristina predefiniti* | `versione precedente richiamata`, `ripristinati i predefiniti` |
| Menu di navigazione | ogni *Salva il menu*; l'eliminazione (così si può ricreare) | `eliminato (un ripristino lo ricrea)` |
| Temi delle infografiche | salva, rinomina, elimina | `creato`, `rinominato`, `eliminato (un ripristino lo ricrea)` |
| Impostazioni dell'editor | ogni salvataggio della pagina Impostazioni dell'editor | |

Una versione nasce **solo se qualcosa è cambiato davvero**: di ogni salvataggio si calcola un'impronta (sha1 dei dati con le chiavi in
ordine) e, se è uguale all'ultima dello stesso oggetto, non si scrive niente. Salvare due volte la stessa bozza lascia una versione sola.

Per ogni oggetto si tengono le ultime **50** versioni (impostazione `editor_versioni_limite`, da 5 a 500, nella pagina Impostazioni
dell'editor). Le versioni **pubblicate** e quelle con una **nota scritta a mano** non si cancellano mai. I dati oltre 64 KB stanno compressi.

## La pagina Versioni

Filtro per **ambito** (tutti, tutti i contenuti, articoli, pagine, aforismi, header e footer, menu, stile, temi, impostazioni) e, dentro
un ambito, per **oggetto** (quale pagina, quale header, quale menu). Ogni riga: quando, l'oggetto con il link per aprirlo, la nota, chi,
e **cosa è cambiato** rispetto alla versione prima ("1 widget aggiunto, 2 widget modificati", "1 impostazione cambiata", "prima versione").

Per ogni riga:
- **Confronta con la precedente** e **con l'attuale**: il confronto si apre in cima alla pagina.
- **Anteprima** (contenuti e modelli): la pagina com'era in quella versione, nella grafica del sito, con una fascia scura in alto che dice di
  quale versione si tratta. Niente cambia sul sito. Per un header o un footer si vede la home con quella versione al posto del modello scelto.
- **Ripristina**, con conferma: l'oggetto torna a quella versione passando per il salvataggio normale, quindi lo stato di adesso resta a sua
  volta una versione e ne nasce una nuova con la nota "ripristino della versione N del ...". Per un contenuto: una versione nata da una bozza
  torna come bozza (la pagina pubblicata non cambia finché non si pubblica); una versione pubblicata torna pubblicata. Per lo stile, il menu e i
  temi il ripristino vale subito. Un menu o un tema eliminati rinascono con lo stesso numero, così i widget che li usavano li ritrovano.
  Chi non è amministratore ripristina solo i contenuti.
- **Aggiungi nota**: una riga scritta a mano, per ricordare perché quella versione conta. Con una nota la versione resta per sempre.

Le pagine **Stile del sito**, **Header e footer**, **Menu di navigazione** e **Temi** hanno un pulsante **Cronologia** che apre Versioni già
filtrata.

## Il confronto, in parole

Il confronto è scritto per chi non programma.

- Per le **strutture** dell'editor (pagine, articoli, modelli): sezioni, colonne e widget **aggiunti**, **tolti**, **spostati** (cambia la sezione o
  l'ordine fra i fratelli), e per ogni elemento rimasto i **controlli cambiati** con il valore di prima e di dopo, chiamati con l'etichetta del
  pannello ("Stile › Titolo › Colore", "Spazi › Spazio sopra (px)"). Un widget si riconosce dal suo testo: "Widget Titolo «Chi sono» in Sezione 2 › Colonna 1".
  I valori per desktop, tablet e telefono si leggono come "desktop 72, telefono 48"; le scelte con la loro etichetta; i testi lunghi accorciati.
- Per **stile, modelli, menu, temi e impostazioni**: chiave per chiave, con le etichette italiane ("Colori › Colori di sistema › Primario: #a855f7 → #aa3366",
  "Voci › «Blog» › Indirizzo"). Header e footer dicono il nome del modello, non il numero.

## Dentro l'editor

Il pulsante **Revisioni** apre un pannello a destra della tela, con le stesse versioni della pagina Versioni (bozze e pubblicate, le ultime 60).
Per ognuna: **Cosa è cambiato** (rispetto alla precedente), **Rispetto alla tela** (rispetto a quello che si sta modificando adesso, anche non
salvato), **Carica nella tela** (la versione entra nella tela: la pagina cambia solo a *Pubblica*), **Aggiungi nota**. In fondo il link alla pagina
Versioni per l'anteprima e il ripristino.

## Da dove vengono le versioni vecchie

Al primo uso la tabella importa una volta sola le righe di `{prefisso}pagine_revisioni` (le pubblicazioni dell'editor di prima) e di
`{prefisso}revisioni` (le copie della scheda classica, con la nota "scheda: ..."), con la loro data e il loro autore. Le due tabelle vecchie
restano al loro posto; la scheda classica continua anche a scriverle. Impostazioni `versioni_schema` e `versioni_migrate`.

## Per chi programma

Tutto in `includes/versioni.php`:

```php
editor_versione_salva($ambito, $oggettoId, $nome, array $dati, $nota = '', ['pubblicata' => bool, 'nota_mano' => bool, 'autore' => '']) // id, o 0 se niente di nuovo
editor_versioni($ambito = '', $oggettoId = 0, $limite = 300, $da = 0)   // elenco senza dati; 'contenuto' = tutti i tipi
editor_versione($id)                                                     // una versione con i dati
editor_versione_ripristina($versioneId, $autore = '')                    // frase per il pannello; RuntimeException se non può
editor_versioni_confronta($a, $b)                                        // ['riassunto', 'righe', 'uguali']; $a prima, $b dopo (id o ['ambito','oggetto_id','dati'])
versioni_confronto_html($diff, 'Prima', 'Dopo'), versioni_confronto_css()
versioni_dati_attuali($ambito, $id), versioni_nota($id, $testo), versioni_oggetti($ambito), versioni_precedente($v)
```

Gli ambiti: `contenuto:<tipo>` (dati: `albero`, `stato`, `header_id`, `footer_id`; oppure `scheda`), `modello` (`albero`, `stato`, `nome`,
`tipo`, `status`, `predefinito`, `condizioni`, `priorita`), `stile` (i valori di `design_salva`), `menu` (`nome`, `posizione`, `voci`), `tema`
(`nome`, `valori`), `impostazioni` (le righe `editor_*`). I ganci stanno nei salvataggi (`editor_salva`, `design_salva`, `menu_salva`,
`editor_tema_*`, `modelli_predefinito`, `contenuti_salva`), tutti marcati `// versioni` e tutti dentro un `try`: una versione che non si
scrive finisce nel log, mai sulla pagina. Durante un ripristino `versioni_silenzio(true)` fa tacere i ganci: la versione la scrive il ripristino,
con la sua nota. Nel nucleo il prefisso delle tabelle passa sempre da `tbl()`.

La tabella: `id, ambito, oggetto_id, oggetto_nome, dati (LONGBLOB), compresso, impronta (sha1), autore, nota, nota_mano, pubblicata, creato,
dimensione`, indice `(ambito, oggetto_id, id)`. La crea `versioni_installa()`, registrata in `cms_installa_tutto()` (`includes/schema-base.php`):
un'installazione nuova nasce con la tabella.

Prove: `$S/audit4/versioni/prova-versioni.js` (Playwright, come un utente: articolo modificato e pubblicato, colore dello stile, header
predefinito, pagina Versioni con confronto, anteprima, ripristino e nota, pannello Revisioni dell'editor, due salvataggi uguali = una versione).
