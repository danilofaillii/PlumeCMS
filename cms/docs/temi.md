# I temi del sito

Decisi con Danilo il 27 ottobre 2026: un tema cambia l'aspetto intero del sito (colori, caratteri, pulsanti, header,
footer, CSS) e può contenere PHP, come i temi di WordPress. Menu, pagine, articoli e immagini non si toccano: sono
contenuti, non aspetto. Motore in `includes/temi.php`.

Stato: fase 1 (colori del CSS in variabili, vedi `stile-sito.md`), fase 2 (motore) e fase 3 (pagina Editor > Temi,
`admin/temi.php`, vedi `pannello.md`) fase 4 (colori delle infografiche dal tema), fase 5 (i tre temi di partenza) e
fase 6 (caricare, scaricare, eliminare) fatte.

## I temi di partenza

| Tema | Aspetto | Cosa contiene |
|---|---|---|
| **Cosmo** (`temi/cosmo`) | lo scuro di plumeCMS: notte blu, ciano e viola, sfumature e stelle | stile vuoto (i predefiniti), header e footer di partenza, infografiche Carta. **Attivarlo lascia il sito identico al pixel** (provato con le 20 schermate): è il punto da cui tornare |
| **Aria** (`temi/aria`) | chiaro e pulito: bianco, un solo blu (`#2563eb`), Inter ovunque, niente sfumature né bagliori, card bianche con bordo | stile, header e footer (testo del footer in `globale:testo-2`), `tema.css`, infografiche ardesia e colori vivi |
| **Bottega** (`temi/bottega`) | caldo editoriale: carta crema, titoli Georgia (carattere di sistema, nessuna chiamata a Google), terracotta `#b4532a` e verde salvia `#3f6b5a` | stile, header e footer, `tema.css`, infografiche calde intonate |

Ogni tema ha `anteprima.png` (800 per 500, la home del tema con il nome "Il tuo sito"). Nessuno dei tre contiene PHP.
Nei temi il menu dell'header si indica con `"menu": "posizione:principale"`: all'attivazione (e in anteprima)
`temi_albero_locale()` lo traduce nell'id del menu con quella posizione su quel sito.

### Colori di partenza dei widget che seguono il tema

Un colore di un controllo dell'editor può essere `ig:<nome>` (per esempio `ig:ciano-2`): diventa `var(--ig-<nome>)`,
la tavolozza delle infografiche. I valori di partenza della CTA (sfumatura `ig:ciano-2` e `ig:ciano-profondo`,
pulsante `ig:rosso`) e del Riquadro del test (`ig:sfondo`, `ig:oro`, `ig:titolo`, `ig:testo`, `ig:scuro`) sono così:
senza tema sono gli stessi colori di prima (identico al pixel), con un tema prendono i suoi. Un colore scelto a mano
nel widget resta quello. Anche la testata della Scheda (`.scheda-testa`) usa `--ig-scuro`.

## Com'è fatto un tema

Una cartella `temi/<nome>/` dentro la cartella del CMS. Il nome: lettere minuscole, cifre e trattini, da 2 a 40
caratteri, comincia con una lettera.

| File | Obbligatorio | Cosa contiene |
|---|---|---|
| `tema.json` | sì | `nome`, `versione`, `autore`, `descrizione`, e i nomi dei file qui sotto: `anteprima`, `css`, `stile`, `modelli` |
| `stile.json` | no | lo Stile del sito del tema, stesso formato di `design_valori()`. Contano solo le sezioni dell'aspetto: `colori`, `font`, `tipografia`, `pulsanti`, `immagini`, `campi`, `layout`, `sfondo`. `sito` (nome, logo, favicon) e `css` (il CSS personalizzato) restano di chi gestisce il sito e vengono ignorati |
| `modelli.json` | no | `{"header": albero, "footer": albero}`, alberi dell'editor come quelli dei modelli di Header e footer |
| `infografiche` (dentro `tema.json`) | no | i colori delle infografiche, vedi sotto |
| `tema.css` (o il nome scritto in `css`) | no | CSS in più. Si carica dopo `sito.css` e `tema.css` del CMS e prima dello Stile del sito, così le scelte fatte nel pannello vincono |
| un'immagine (`anteprima`) | no | png, jpg, webp o svg, per la scheda nel pannello |
| `funzioni.php` | no | ritorna `['filtri' => [...], 'eventi' => [...]]`, come il manifesto di un modulo (`cms-moduli.md`) |
| `viste/<nome>.php` | no | ridisegna una pagina e **ritorna l'HTML come stringa** (vedi sotto) |

I percorsi scritti in `tema.json` sono relativi alla cartella del tema, senza `..`, solo con i tipi ammessi.

### Le viste

| File | Cosa ridisegna | Variabili |
|---|---|---|
| `documento.php` | la pagina intera | `$testa` (dentro `<head>`, già con CSS e metadati), `$testata`, `$corpo`, `$piede`, `$popup`, `$meta` |
| `home.php` | la home scritta nel codice (quando la pagina iniziale non è fatta con l'editor e nessun modulo la dà) | nessuna |
| `articolo.php` | un articolo senza editor | `$c` (il contenuto), `$html` (il testo pronto) |
| `pagina.php` | una pagina senza editor | `$c`, `$html` |
| `blog.php` | la pagina del blog | `$v`: `articoli`, `pagina`, `totale` |
| `archivio.php` | categoria o tag | `$v`: `tassonomia`, `nome`, `descrizione`, `articoli`, `base`, `pagina`, `totale` |
| `cerca.php` | la ricerca | `$q`, `$trovati` (elenco di `['c' => contenuto, 'e' => estratto]`) |
| `404.php` | pagina non trovata (il codice resta 404) | nessuna |

Una vista che non ritorna una stringa non conta: si usa quella del CMS. Le pagine fatte con l'editor non passano
dalle viste: il loro aspetto lo danno lo stile, il CSS e i widget.

### Le infografiche

Deciso con Danilo il 27 ottobre 2026: le infografiche hanno i colori del tema attivo. Come funziona:

- Nei widget `ig_*` i colori con un nome (le voci rosso, oro, verde...; le testate scuro, verde, rosso...; il corpo
  crema; gli occhielli) sono variabili `--ig-*` di `sito.css`, con i valori di Carta in `:where(:root)`. **Il tema
  le cambia; senza tema restano Carta**, identiche al pixel a prima (14 infografiche e la pagina dei 79 widget, e il
  CSS risolto confrontato riga per riga).
- I colori liberi scritti a mano su un'infografica (`testata_colore`, `colore_libero`, `corpo_colore`, `bordo_colore`)
  restano sempre quelli: sono una scelta fatta apposta. Il preimpostato Carta usa solo colori con un nome e si chiama
  ora "Carta (segue il tema del sito)"; gli altri nove preimpostati hanno colori scritti a mano e restano uguali con
  ogni tema.
- Il corpo "Scuro come il sito" usa `--bg-2`, `--testo`, `--testo-2`, `--testo-3`: segue lo Stile del sito.

Nel `tema.json`:

```json
"infografiche": {
  "sfondo": "#f8fafc", "bianco": "#ffffff", "bordo": "#cbd5e1", "riga": "#e2e8f0",
  "titolo": "#0f172a", "testo": "#334155", "muto": "#64748b", "situazione": "#f1f5f9",
  "su_testata": "#ffffff", "su_scuro": "#cbd5e1",
  "voci": {"rosso": "#dc2626", "oro": "#d97706", "verde": "#059669", "ciano": "#0891b2", "viola": "#7c3aed", "scuro": "#1e293b"},
  "variabili": {"mattone": "#b45309"}
}
```

Tutto facoltativo. Dalle sei voci il motore (`temi_ig_css`) ricava il resto con `color-mix()`: la seconda tinta (più
scura; per lo scuro un po' più chiara), le tinte chiare degli occhielli, arancio, giallo, oro scuro, verde scuro, ciano
scuro, rosso scuro, mattone, lilla, marrone. `variabili` scrive a mano qualsiasi `--ig-*` (senza il prefisso). Solo
colori validi: il resto si scarta. Le variabili escono in `<style id="tema-ig">:root{...}</style>` accanto al CSS del
tema, anche nella tela dell'editor. Nella pagina Temi ogni scheda mostra i sei colori.

### Filtri ed eventi

Gli stessi del nucleo (`cms-moduli.md`, "Filtri ed eventi che il nucleo offre oggi"). Il tema passa per ultimo, dopo i
moduli: se un modulo e il tema cambiano la stessa cosa, vince il tema.

## Come lavora il motore

- **Attivare** (`temi_attiva`): ogni sezione dell'aspetto dello Stile del sito riparte dai predefiniti e prende i
  valori del tema; il salvataggio passa da `design_salva`, quindi lo stile di prima resta nelle Versioni e in
  "Versione precedente". Header e footer del tema diventano modelli ("Header Nometema", "Footer Nometema") pubblicati e
  predefiniti; quelli di prima restano in Header e footer. Riattivando lo stesso tema i suoi modelli si riusano (gli
  id stanno nell'impostazione `tema_modelli`), non si duplicano. Attivare non esegue il PHP del tema.
- **Il tema scelto** sta nell'impostazione `tema_attivo`. `temi_disattiva()` la svuota: il sito usa `sito.css` e lo
  Stile del sito così com'è (header e footer restano quelli predefiniti in quel momento).
- **Sul sito pubblico** `sito/index.php` chiama `temi_avvia_sito()`: legge il tema, carica `funzioni.php`, arma il
  controllo degli errori. Le viste del sito (`sito/viste.php`) chiedono prima `vista_tema('<nome>')`.
- **Nel pannello** il PHP del tema non gira mai. La tela dell'editor e le anteprime caricano solo il CSS del tema
  attivo, letto da `tema.json` (`temi_css_link`).
- **Anteprima** senza attivare: `/?preview=1&tema=<nome>` con la sessione dell'amministratore (`tema=base` per vedere
  il sito senza tema). Lo stile e gli header del tema si usano solo per quella richiesta; un visitatore che scrive lo
  stesso indirizzo vede il sito normale.
- **Variabili CSS**: i valori ricavati dai colori (`--primario-chiaro`, `--inchiostro`, `--accento`...) stanno in
  `:where(:root)`, che non pesa: il `:root` di un tema li scavalca anche se il suo CSS viene prima dello Stile del sito.

## Se il tema sbaglia

- Un errore nel PHP del tema (eccezione, errore di sintassi in `funzioni.php` o in una vista, errore in un filtro) viene
  preso: la richiesta continua con l'aspetto del CMS, l'errore va nel log (`temi: <nome>: ...`) e il tema si segna come
  guasto nell'impostazione `tema_guasto` (nome, errore, quando). Da lì il sito usa l'aspetto di base finché qualcuno non
  attiva di nuovo un tema.
- Un errore fatale che PHP non lascia prendere (per esempio un tema che ridefinisce una funzione del CMS) fa fallire
  **quella richiesta** con un 500; alla fine della richiesta `temi_controllo_fatale` lo riconosce dal file e segna il
  tema come guasto. Dalla richiesta dopo il sito risponde con l'aspetto di base.
- Il pannello non carica mai il PHP del tema, quindi resta sempre aperto per cambiare o riattivare un tema.

## Sicurezza

- Dal browser i file PHP dentro `temi/` non si aprono mai: regola nel `.htaccess` del CMS e negli esempi di
  `install/esempi/` (`RewriteRule ^temi/.*\.(php|phtml|phar|php[0-9]|phps)$ - [F,L,NC]`), e in `nginx.txt`. Provata su
  Apache 2.4 con PHP 8.4.27: `.php` e `.PHP` 403, CSS 200. I `.json` erano già vietati da una regola generale.
- **`temi/.htaccess`** (costante `TEMI_HTACCESS` in `includes/temi.php`): nella cartella dei temi niente PHP, script,
  JSON, `.htaccess` e `.ini` dal web, maiuscole comprese, e niente elenco dei file. Lo scrive il CMS da solo quando manca
  o è diverso (`temi_proteggi()`): aprendo la pagina Temi, attivando o caricando un tema, alla fine dell'installazione.
  Serve alle installazioni esistenti, che hanno il `.htaccess` della radice di prima: il pannello Aggiornamenti non
  accetta `.htaccess` in sottocartelle, quindi non può arrivare con un pacchetto. Provato su Apache 2.4 con PHP 8.4.27 e
  il `.htaccess` della 1.1.9: PHP (anche in `viste/` e `.PHP`), JSON e `.htaccess` 403, CSS e immagini 200. La pagina
  Sicurezza ne mostra lo stato.

## Caricare, scaricare, eliminare (pagina Temi)

- **Carica un tema** (`temi_installa_zip`): uno zip con `tema.json` nella radice o in un'unica cartella. Il nome della
  cartella in `temi/` è quello della cartella nello zip, altrimenti `slug` di `tema.json`, altrimenti viene dal nome.
  Prima di scrivere si controlla tutto lo zip (`temi_zip_leggi`): nomi puliti (niente `..`, percorsi assoluti, file
  nascosti come `.htaccess` e `.user.ini`, spazi), solo i tipi di `TEMI_ZIP_ESTENSIONI` (JSON, CSS, png, jpg, webp,
  gif, avif, caratteri, txt, md, PHP; niente SVG, che possono portare script), al massimo 400 file, 10 MB per file,
  40 MB in tutto, niente compressioni sospette (zip bomba). I file si scrivono uno per uno (mai `extractTo`) in
  `aggiornamenti/temi-nuovi/`, che dal web non si legge, con permessi 0644, poi la cartella si sposta in `temi/`.
  Il tema caricato non si attiva da solo.
- **Un tema con PHP** passa solo firmato con la chiave del sito oppure con la finestra di un'ora aperta in Sicurezza
  (la stessa dei pacchetti che mettono PHP nella radice). Se Sicurezza chiede pacchetti sempre firmati, anche un tema
  senza PHP deve esserlo. Firma: `PACCHETTO_CHIAVE=... python3 tools/pacchetto.py --rifirma tema.zip` scrive
  `tema-firmato.zip` con un `manifest.json` che porta solo la firma (non si installa). Uno zip firmato e poi modificato
  si rifiuta. Ogni caricamento resta nel registro di Sicurezza.
- **Sostituisci**: un tema con lo stesso nome si sostituisce solo spuntando la casella; la versione di prima va in
  `aggiornamenti/temi-precedenti/<nome>-<data>`. Se è il tema attivo, per applicare stile, header e footer nuovi si
  preme di nuovo Attiva.
- **Scarica**: lo zip di un tema, con la sua cartella. **Scarica l'aspetto di adesso come tema**
  (`temi_zip_aspetto_attuale`): lo Stile del sito (sezioni dell'aspetto), header e footer predefiniti con il menu per
  posizione, il CSS del tema attivo e il CSS personalizzato in `tema.css`, i colori delle infografiche del tema attivo.
  Il PHP del tema attivo non entra. Ricaricato su un altro sito (o sullo stesso) diventa il tema "Il mio aspetto".
- **Elimina**: solo un tema non scelto; la cartella va in `aggiornamenti/temi-precedenti/`.

## Prove

`prove/prova-temi.js` (40 controlli, il motore e le infografiche), `prove/prova-temi-partenza.js` (36 controlli: i tre temi attivati, pagine, menu, contrasto del testo almeno 4,5 a 1) `prove/prova-carica-temi.js` (45 controlli: caricare, sostituire, firma, finestra, zip malevoli, scaricare, eliminare) e `prove/prova-pannello-temi.js` (32 controlli, la pagina del pannello come un utente,
anche con un redattore che non deve entrare), con i temi di collaudo creati da `prove/temi-collaudo.php`. Vedi `prove/LEGGIMI.md`.
