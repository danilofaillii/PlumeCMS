# I temi del sito

Decisi con Danilo il 27 ottobre 2026: un tema cambia l'aspetto intero del sito (colori, caratteri, pulsanti, header,
footer, CSS) e può contenere PHP, come i temi di WordPress. Menu, pagine, articoli e immagini non si toccano: sono
contenuti, non aspetto. Motore in `includes/temi.php`.

Stato: fase 1 (colori del CSS in variabili, vedi `stile-sito.md`) e fase 2 (motore) fatte. Da fare: pagina
Editor > Temi nel pannello, colori delle infografiche dal tema, i tre temi di partenza, caricamento e download degli zip.

## Com'è fatto un tema

Una cartella `temi/<nome>/` dentro la cartella del CMS. Il nome: lettere minuscole, cifre e trattini, da 2 a 40
caratteri, comincia con una lettera.

| File | Obbligatorio | Cosa contiene |
|---|---|---|
| `tema.json` | sì | `nome`, `versione`, `autore`, `descrizione`, e i nomi dei file qui sotto: `anteprima`, `css`, `stile`, `modelli` |
| `stile.json` | no | lo Stile del sito del tema, stesso formato di `design_valori()`. Contano solo le sezioni dell'aspetto: `colori`, `font`, `tipografia`, `pulsanti`, `immagini`, `campi`, `layout`, `sfondo`. `sito` (nome, logo, favicon) e `css` (il CSS personalizzato) restano di chi gestisce il sito e vengono ignorati |
| `modelli.json` | no | `{"header": albero, "footer": albero}`, alberi dell'editor come quelli dei modelli di Header e footer |
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
- Le installazioni esistenti hanno il `.htaccess` di prima, senza la regola dei temi: va aggiunta quando arriva la
  versione con i temi (da fare con il pacchetto di aggiornamento, fase 6).
- Caricare un tema con PHP da fuori (zip) passerà dalle stesse difese dei pacchetti: firma HMAC o finestra di un'ora
  aperta da Sicurezza (fase 6).

## Prove

`prove/prova-temi.js` (32 controlli) con i temi di collaudo creati da `prove/temi-collaudo.php`. Vedi `prove/LEGGIMI.md`.
