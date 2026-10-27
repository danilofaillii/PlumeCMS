# Il pannello: menu, dashboard, form, popup, utenti, newsletter, test

Richiesta di Danilo, 24 ottobre 2026: "Sistemiamo il menu laterale. La dashboard non ha senso costruita così." Con l'elenco delle voci che voleva, quelle da creare e quelle da spostare.

## Il menu laterale

`admin/includes/admin-header.php` disegna il menu da un solo elenco `$menu`: ogni voce ha nome, indirizzo, icona, se è quella aperta, e le sue sottovoci. Le voci con sottovoci sono un `<details class="nav-gruppo">` che si apre da solo quando una delle sue pagine è quella corrente. Il nome del gruppo è un link alla sua pagina (Pagine apre l'elenco delle pagine, Impostazioni apre la Configurazione email); la freccia a destra apre e chiude l'elenco delle sottovoci senza cambiare pagina (revisione del 24 ottobre: prima il nome del gruppo Pagine non portava da nessuna parte).

## Ruoli e permessi (dalla 1.1.2)

Due ruoli: **amministratore** (`admin`) e **redattore** (`editor`). Il confine vale lato server su ogni pagina e ogni azione,
non solo nel menu: `require_login()` in `admin/includes/auth.php` lascia al redattore solo le pagine dell'elenco
`AUTH_REDATTORE_PAGINE` (un modulo ne aggiunge con il filtro `redattore_pagine`); fuori da lì una pagina lo rimanda alla
dashboard con un avviso, una chiamata API o un POST risponde 403. Il menu usa le stesse regole (`auth_redattore_puo()`):
il redattore non vede le voci che non può aprire. La prova `tools/sicurezza-prova.js` le controlla tutte, file per file.

| Area | Pagine | Redattore | Amministratore |
|---|---|---|---|
| Articoli, pagine, aforismi (modulo) | `contenuti.php`, `contenuto.php`, `editor.php` (tipi di contenuto), `editor-api.php`, `editor-anteprima.php`, `seo-api.php` (analisi) | sì (pubblica secondo "Chi pubblica" nelle Impostazioni dell'editor; widget HTML e testo grezzo diventano testo pulito) | sì |
| Media | `media.php` | sì | sì |
| Categorie e tag | `categories.php` | sì | sì |
| Versioni dei contenuti | `versioni.php` (solo ambiti `contenuto:*`) | sì | sì, tutte |
| Il mio accesso, password | `account.php`, `password.php` | sì | sì |
| Dashboard | `index.php` | solo contenuti (niente newsletter, richieste, sistema, avvisi) | tutto |
| Guida delle infografiche (modulo) | `guida-infografiche.php` | sì | sì |
| Newsletter: campagne, iscritti, liste, flussi, statistiche, invio, cancellazione | `newsletter*.php`, `contatti.php` | no | sì |
| Form e invii | `form.php`, `form-submissions.php` | no | sì |
| Popup | `popup.php` | no | sì |
| Menu di navigazione, header e footer | `menu.php`, `modelli.php`, `editor.php?tipo=modelli` | no | sì (pubblica e predefinito rispettano anche "Chi pubblica") |
| Stile del sito, temi, widget, impostazioni dell'editor | `design.php`, `editor-temi.php`, `editor-widget.php`, `editor-impostazioni.php` | no | sì |
| Versioni di stile, modelli, menu, temi, impostazioni | `versioni.php` | no | sì |
| SEO, Utenti, Impostazioni, Sicurezza, Aggiornamenti, Log cron, Informazioni | `seo.php`, `utenti.php`, `impostazioni.php`, `sicurezza.php`, `aggiornamenti.php`, `cron-log.php`, `informazioni.php` | no | sì |
| Pagine del modulo danilofailli (Test, Social copy, Diagnosi, Importa SQL, script di manutenzione) | `test*.php`, `social-copy.php`, `diagnosi-articoli.php`, `importa-sql.php`, `seed-infographics.php`, `generate-*.php`, `import-articles.php`, `export-articles-sql.php` | no | sì (gli script che scrivono partono solo con il pulsante Esegui, POST e CSRF; da riga di comando come prima) |

| Voce | Pagina | Sottovoci |
|---|---|---|
| Dashboard | `index.php` | |
| Articoli | `contenuti.php?tipo=articoli` | |
| Aforismi | `contenuti.php?tipo=aforismi` | |
| Pagine | `contenuti.php?tipo=pagine` | Home nell'editor |
| Editor (dal 26 ottobre) | `design.php` | Temi (`temi.php`, solo admin, dal 27 ottobre), Stile del sito, Header e footer, Menu di navigazione, Colori delle infografiche (`editor-temi.php`), Widget (`editor-widget.php`, solo admin), Versioni (`versioni.php`), Impostazioni dell'editor (`editor-impostazioni.php`, solo admin), Informazioni (`informazioni.php`) |
| Form | `form.php` (nuovo) | |
| Popup | `popup.php` (nuovo) | |
| Categorie | `categories.php` | |
| Tag | `categories.php?tipo=tag` | |
| Media | `media.php` | |
| Utenti | `utenti.php` (nuovo) | |
| Newsletter e contatti | `newsletter.php` | Campagne, Iscritti e liste, Contatti (Laser, ebook), Invii dei form, Flussi automatici (nuovo), Statistiche (nuovo) |
| Test | `test.php` (nuovo) | I test, Statistiche (`test-statistiche.php`) |
| SEO e AEO | `seo.php` (solo admin) | Panoramica, Motori di ricerca, Copertura, Prestazioni, Analisi e tag, Globale SEO, Schema e AEO, Redirect e 404, Registro |
| Impostazioni | `impostazioni.php` | Configurazione email, Sicurezza (`sicurezza.php`, solo admin), Aggiornamenti, Importa SQL, Log cron, Diagnosi articoli (Informazioni è passata nel gruppo Editor) |
| Social copy | `social-copy.php` | |

Sparite dal menu: Invii form (sta dentro Newsletter e contatti), Contatti (idem), Test e statistiche (è la sottovoce Statistiche di Test), Guida Infografiche (le infografiche sono un widget dell'editor; il file `guida-infografiche.php` resta raggiungibile dall'indirizzo). Aforismi resta: Danilo lo aveva chiesto il giorno prima. Social copy resta com'è, come chiesto.

## La dashboard

`admin/index.php` rifatta. In alto i pulsanti rapidi (articolo, pagina, aforisma, campagna, home nell'editor). Poi gli avvisi, solo quando c'è qualcosa da fare: cron del mattino fermo da più di 36 ore, persone da richiamare per la Laser Coaching, compilazioni nuove dei form, pacchetti caricati e non applicati, indirizzo degli avvisi mancante. Poi cinque scatole: Contenuti (quanti online, programmati, bozze, pagine, aforismi, e le prossime otto uscite), Newsletter (iscritti attivi, da confermare, usciti, e l'ultima campagna con aperture e clic), Richieste e form (da richiamare, ebook da confermare, form e test degli ultimi sette giorni), Ultime modifiche, Sistema (quando sono girati i due cron, pacchetti da applicare, posta).

## Form

Costruttore di moduli, `admin/form.php` e `includes/form.php`, tabelle `newdf_moduli` e `newdf_moduli_invii`.

- Un form ha nome, titolo, introduzione, campi (testo, email, telefono, area di testo, scelta singola, scelta multipla, casella, numero, data: costanti `FORM_TIPI_CAMPO`), testo del pulsante, testo del consenso, pagina di ringraziamento (titolo, testo, link). I campi si ordinano trascinando (SortableJS servito in casa).
- Opzioni: avviso per email a ogni compilazione (`posta_notifica_email()`), iscrizione a una lista della newsletter (serve un campo di tipo Email; con o senza doppia conferma).
- Sul sito il form si mette con il widget **Modulo** dell'editor, voce `Form: <nome>` (`editor_moduli_disponibili()`), o in un popup. Il POST va a `api/modulo.php` come `modulo=form:ID`, con lo stesso captcha fatto in casa dei moduli ebook e Laser (`moduli.js`), il campo nascosto anti-bot, il consenso privacy obbligatorio e i limiti per indirizzo IP. Dopo l'invio: `/grazie/?f=ID`, pagina di ringraziamento del form (`form_grazie_html`). Un errore riporta alla pagina di partenza con `?modulo=campo:nome` o `?modulo=email`, e il campo sbagliato viene segnato.
- Ogni compilazione innesca i flussi della newsletter legati a quel form (`newsletter_flussi_innesco('form', id, iscritto)`), quando la persona è un iscritto.
- **Invii dei form** (`admin/form-submissions.php`): filtro per form, letto / da leggere, elimina, CSV. In fondo gli invii del vecchio modulo di contatto (`form_submissions`), finché ce ne sono.

- **Revisione del 24 ottobre sera, form e popup provati nel browser** (prima erano state provate solo le funzioni PHP). Costruttore: le opzioni di una scelta, appena scritte, mandavano in errore ogni ridisegno della scheda (`opzioni.join` su una stringa) e i campi dopo la scelta sparivano dalla vista; ora le opzioni si scrivono in una casella a più righe, una per riga (così un'opzione può avere la virgola), e il ridisegno non si rompe. Un errore di salvataggio (nome vuoto, nessun campo, lista senza email, nome breve già usato) non svuota più la scheda: si ridisegna con quello che era stato scritto. Due campi con la stessa etichetta si distinguono («Email (2)») così nei dati non si coprono; i nomi tecnici non prendono quelli riservati del modulo; il nome breve si ferma a 100 caratteri (colonna in modalità stretta). Tipo nuovo **Scelta multipla (caselle)**. Sul sito: ogni form ha il suo id (`modulo-f<ID>`), l'errore torna solo al form che l'ha prodotto (`?modulo=...&f=ID`), il campo sbagliato è segnato in rosso e quello che la persona aveva scritto si ritrova compilato (`moduli.js`, `sessionStorage`); textarea e caselle hanno lo stile degli altri campi; i numeri si controllano. Il titolo della pagina di ringraziamento e il nome del form nell'email d'avviso sono escapati una volta sola. **Invii dei form**: una riga del vecchio modulo di contatto con telefono o messaggio NULL mandava la pagina in errore fatale (`h(null)`); la tabella del vecchio modulo può mancare senza fermare la pagina; `fputcsv` con l'escape esplicito (deprecazione di PHP 8.4). **Popup**: i popup compaiono anche su blog, glossario e archivi ("altro"), prima solo su home, articoli e pagine; gli indirizzi elencati valgono anche scritti senza slash finale o con il dominio; un form dentro un popup porta il campo nascosto `popup=ID`, con un errore la pagina riapre quel popup sulla risposta (`data-subito`), e se lo stesso form sta anche in pagina gli id dentro il popup cambiano (`popup-N-modulo-f4`); `moduli.js` lavora una volta sola anche se la pagina lo carica due volte. Prove: `audit/form-popup/prova-costruttore.js`, `prova-sito.js`, `prova-popup.js`, `prova-invii.js` (Playwright).

## Popup

`admin/popup.php`, `includes/popup.php`, tabella `newdf_popup`, `sito/assets/popup.js`.

Un popup ha occhiello, titolo, testo (editor Quill), immagine, pulsante con link, oppure un modulo dentro (newsletter, ebook, Laser, o un form). Dove: tutte le pagine, solo la home, solo gli articoli, solo le pagine, o un elenco di percorsi (`POPUP_DOVE`). Quando: dopo tanti secondi, a tanto scorrimento, quando il mouse esce dalla pagina (`POPUP_INNESCHI`). Frequenza: ogni quanti giorni rivederlo (0 = sempre), ricordata nel browser con `localStorage`. Il front controller mette percorso e genere della pagina in `$GLOBALS['sito_percorso']` e `sito_genere`; `vista_popup()` dopo `</main>` stampa i popup che valgono per quella pagina (`popup_per`), e `popup.js` li apre al momento giusto. Chiuso con la X, con Esc o cliccando fuori.

## Utenti

`admin/utenti.php` sulla tabella `newdf_users`: elenco, aggiungi, modifica, elimina. Ruoli `admin` e `editor` (`UTENTI_RUOLI`). Password di almeno 12 caratteri, due volte. Solo un amministratore gestisce gli utenti; nessuno elimina sé stesso, nessuno si toglie il ruolo di amministratore, e deve restarne almeno uno.

## Sicurezza (dal 26 ottobre)

`admin/sicurezza.php` (gruppo Impostazioni, solo amministratore), motore in `includes/sicurezza.php`. Cinque schede:
**Stato** (lista di controllo: https, CSP, intestazioni, permessi dei file, config e logs leggibili dal web,
installatore, PHP spento in uploads, 2FA degli admin, accessi falliti, blocchi, versione, con i pulsanti "rimuovi
install" e "scrivi uploads/.htaccess"); **Impostazioni** (tentativi e durata del blocco, scadenze della sessione,
2FA obbligatorio, lista IP, CSP sito/pannello rapporto o applica, chi usa il widget HTML e il codice head/body,
HSTS, firma dei pacchetti, host per le chiamate in uscita); **Registro** (accessi riusciti, falliti, blocchi, con
filtri e sblocco); **Sessioni** (le sessioni aperte di ogni utente, "esci da tutti i dispositivi"); **Rapporti CSP**
(le violazioni raccolte da `api/csp.php`). Il secondo fattore di ciascun utente si configura da `admin/account.php`
("Il mio accesso"). Password dimenticata: `admin/password-dimenticata.php`. Guida completa in `docs/sicurezza.md`.

## Newsletter e contatti

La newsletter di `php-site/docs/newsletter.md` cresce di due pezzi, in `includes/newsletter-flussi.php` (caricato da `newsletter.php`).

**Statistiche** (`admin/newsletter-statistiche.php`). Ogni email di una campagna o di un flusso porta un pixel e i link riscritti verso `api/nl.php`, con una firma HMAC (`nl_firma`, segreto di `moduli_segreto()`): senza la firma giusta non si segna niente. Tabella `newdf_newsletter_eventi`, una riga per persona e per tipo (apertura, clic con l'indirizzo): chi apre tre volte conta una. La pagina mostra per campagna inviate, aperte, clic e tassi, i link più cliccati, i nuovi iscritti per mese (con attivi e usciti), e per ogni flusso quanti sono in corso, finiti, quante email mandate. Il link di uscita resta diretto, non passa dal tracciamento. I numeri sono per difetto: chi blocca le immagini non risulta tra le aperture, e la pagina lo dice.

**Flussi automatici** (`admin/newsletter-flussi.php`, tela in `admin/assets/flussi.js` e `flussi.css`). Richiesta di Danilo, 24 ottobre, dopo la prima consegna: "ogni email può avere più uscite in base agli eventi; se non ha interagito entro il tempo stabilito o non interagisce da x tempo, allora...; un ambiente drag & drop come in Brevo". Quindi un flusso è un **grafo** disegnato su una tela:

- **Blocchi**: Parte quando (l'innesco, fisso), Email, Attesa (minuti, ore, giorni), Bivio (Sì / No su una condizione), Fine. Si trascinano dalla colonna a sinistra sulla tela, o si aggiungono con un clic; si spostano trascinandoli; un clic apre il pannello a destra con i campi del blocco (per l'email anche l'editor del testo).
- **Uscite**: ogni blocco ha i suoi pallini. Email: *Subito dopo*, *Se apre*, *Se clicca*, *Se non fa niente entro il tempo*. Bivio: *Sì* e *No*. Attesa e innesco: *Poi*. Si collega tirando dal pallino fino a un altro blocco; una linea si toglie cliccandola e premendo Canc (o con la ✕). Un'uscita lasciata libera chiude il cammino. "Riordina i blocchi" li dispone in colonne per profondità.
- **Inneschi** (`NL_INNESCHI`): iscrizione confermata, ingresso in una lista, form compilato, apertura o clic di una campagna, e **non apre e non clicca niente da tanti giorni** (il giro del cron cerca gli iscritti attivi fermi da N giorni, iscritti da almeno N giorni, e li mette in cammino una volta sola).
- **Condizioni del bivio** (`NL_CONDIZIONI`): sta o non sta in una lista; ha aperto, non ha aperto, ha cliccato, non ha cliccato una certa email di questo flusso; non fa niente da almeno N giorni; ha fatto qualcosa negli ultimi N giorni; è iscritto da almeno N giorni.
- **Come cammina una persona** (`newdf_newsletter_corsi`: nodo dove sta, quando si muove, se è in ascolto, quanti salti). L'innesco la mette sul primo blocco. Il giro del cron (`newsletter_flussi_giro`, ogni cinque minuti, nei posti che le campagne lasciano liberi) la fa scorrere: un'attesa fissa l'ora del prossimo passo, un bivio sceglie l'uscita, un'email parte e, se ha un tempo di ascolto e almeno un'uscita fra *Se apre*, *Se clicca* e *Se non fa niente* collegata, resta in ascolto fino alla scadenza; senza tempo di ascolto prosegue da *Subito dopo*. Un'apertura o un clic sull'email (`newsletter_flusso_evento`, dentro `api/nl.php`) la sposta subito dall'uscita giusta: un clic preferisce *Se clicca* e, se è libera, vale come apertura; un'apertura con solo *Se clicca* collegato resta in ascolto. Alla scadenza senza niente: *Se non fa niente*, o *Subito dopo* se quella è libera. Un contatore di salti ferma i giri senza fine (60 salti). Spegnere un flusso ferma i cammini; eliminarlo li cancella; una persona entra in un flusso una volta sola.
- **Salvataggio che regge alle condizioni vere** (revisione del 24 ottobre sera, dopo "il salvataggio dei flussi automatici non funziona"). Le colonne sono utf8 a tre byte e il server sta in modalità stretta: un'emoji nel nome o nell'oggetto faceva rifiutare la riga (errore 1366) e il disegno si perdeva. Adesso il grafo si salva in JSON con i caratteri fuori ASCII scritti come `\uXXXX`, il nome passa dalle entità HTML come il corpo. Un errore di validazione (email senza oggetto, innesco senza giorni) non rimanda più alla pagina vuota: la pagina si ridisegna con quello che era arrivato dal form. Il campo nascosto con il grafo passa dalla protezione generica di `campi-protetti.js` (base64 con `~b64~`, sciolto in `auth.php`), così il firewall di cPanel non blocca il POST per l'HTML che c'è dentro. Ogni cinque minuti la pagina chiama `newsletter-flussi.php?ping=1` e la sessione non scade mentre si disegna. Una copia del lavoro sta nel browser (`localStorage`, chiave `fl-bozza-<id>`): se il salvataggio va male per qualunque motivo (sessione scaduta, errore del server, rete), alla riapertura compare "C'è una versione di questo flusso non salvata: Riprendi o Scarta". Il link del pulsante senza `https://` lo aggiunge il server, il campo non blocca più il Salva con l'avviso del browser; il nome vuoto dice "Dai un nome al flusso prima di salvarlo"; Elimina non chiede il nome. Sotto i 1100 px la tela sta su una colonna sola: tavolozza sopra con i blocchi in riga, tela alta, pannello sotto. `api/nl.php` rimanda a un altro sito solo con la firma giusta (senza, verso il sito stesso o la home), e manda il GIF con la lunghezza vera.
- **Flussi scritti prima** come sequenza (attesa in giorni e condizione per passo) si convertono in grafo alla lettura (`newsletter_flusso_grafo`): attesa → blocco Attesa, condizione → Bivio con il No che salta l'email, email → blocco Email con uscita *Subito dopo*. Nessuno dei dati si perde.
- Sulla tela, a fianco di ogni blocco: quante persone stanno lì adesso e quante aperture ha avuto l'email.

## Test

`admin/test.php`: l'elenco dei test di `newdf_test` (il primo è il Test del burnout), con versione, numero di domande, stato, compilazioni totali, degli ultimi 30 giorni, con consenso alla ricerca, e l'indirizzo sul sito. Chiudi e Riapri: a test chiuso la pagina pubblica (`/test-burnout/`) dice che il test è fermo e non mostra il vecchio modulo di WordPress salvato nel contenuto della pagina (`sito_test_per_pagina()` tiene anche l'ultima versione chiusa, con `chiuso => true`, e `vista_test()` stampa l'avviso). Da ogni riga si va alle Statistiche (`test-statistiche.php`, le stesse di `php-site/docs/test-statistiche.md`) e, per il burnout, alle risposte una per una. Un test nuovo arriva con un pacchetto.

## Infografiche come widget

Nell'editor visuale, gruppo Contenuto, widget **Infografica**: tipo (passi, checklist, confronto, numeri, citazione, flusso), titolo, voci con etichetta, testo, valore, didascalia, segno, sinistra e destra secondo il tipo. Lo disegna `render_infographic()` di `includes/infographic.php`, lo stesso delle infografiche negli articoli, con le classi `ig-` già nel CSS del sito.

## Correzioni fatte durante il collaudo

- `popup_salva` usava `sito_senza_4byte()`, che vive in `includes/sito.php` e nel pannello non c'è: ora `newsletter_4byte_dentro()`, stessa cosa.
- `form_salva` leggeva `$d['slug']` anche quando non arriva.
- Il primo motore dei flussi (una sequenza di passi) è durato un'ora: Danilo ha chiesto le uscite multiple e la tela, e la sequenza è diventata un grafo.
- `form_html` cercava `sito_versione_asset()` anche fuori dal sito pubblico (anteprima, popup dal pannello): ora, se manca, scrive l'indirizzo di `moduli.js` così com'è.
- `newsletter_email` non pretende più `pulsante_testo` e `pulsante_link` nella campagna.
- Il server PHP di prova teneva in memoria i file vecchi: quattro pagine rispondevano "funzione non definita" con il codice giusto su disco. Riavviato, tutto a posto. Sul server vero, con l'opcache, la stessa cosa passa da sola entro due secondi (`opcache.revalidate_freq`).

## Revisione generale del 24 ottobre sera (pacchetto 12)

Danilo, dopo aver applicato il pacchetto 11 su Hostcare: "Ci sono molti problemi, compreso il salvataggio di flussi automatici". Cinque revisioni in parallelo, una per area, ognuna con prove nel browser (Playwright) e dalla riga di comando, con l'obbligo di dimostrare ogni difetto prima e dopo. Rapporti completi fuori dal repository (scratchpad `audit/*/RAPPORTO.md`); qui il sunto. In tutto 45 difetti corretti in 46 file.

**Le cause più probabili dei "molti problemi" sul server vero**

1. **Ogni scheda di articolo, pagina o aforisma andava in errore fatale** (`admin/contenuto.php`): il menu nuovo di `admin-header.php` fa `foreach ($menu as $v)` e sovrascriveva la `$v` della scheda. Regressione del pacchetto 11, non vista dal giro del pannello perché la scheda senza id rispondeva comunque. Variabile rinominata.
2. **La dashboard andava in errore fatale senza SMTP configurato** (`h(null)` sulla riga Posta). Su un server appena installato, senza posta, la prima pagina del pannello non si apriva.
3. **Un'emoji nel nome del flusso o nell'oggetto di un'email faceva rifiutare il salvataggio** (colonne utf8 a 3 byte, MySQL stretto, errore 1366) e il redirect a `?nuovo=1` buttava via il disegno. Ora il grafo si salva in JSON con `\uXXXX`, il nome con le entità, e un errore di validazione ridisegna la pagina con i dati arrivati.
4. **PHP 8.4 con `display_errors` acceso nel php.ini** stampa le deprecazioni prima di `header()`: redirect saltati, JSON sporco, "headers already sent". In locale (8.3, display_errors spento) non si vedeva mai. Ora `config.php` manda tutto nel log `new/logs/php-errori.log` e niente a video fuori dal pannello; `auth.php` bufferizza l'output del pannello e manda le deprecazioni nel log.
5. **ModSecurity di cPanel** blocca con un 403 muto i POST con HTML o JSON dentro (grafo dei flussi, struttura dell'editor, corpo delle campagne). Protezione generale: `admin/assets/campi-protetti.js` codifica in base64 (`~b64~`) le textarea e i campi nascosti con HTML/JSON all'invio di ogni form del pannello, `auth.php` decodifica `$_POST` prima di tutto; `editor.js` e `contenuto.js` fanno lo stesso nelle `fetch` e spiegano un 403/406 (firewall), 413 (post_max_size), 5xx.
6. **Modalità SQL**: `db.php` imposta a ogni connessione la modalità predefinita di MySQL 8. Con `ANSI_QUOTES` attivo sull'hosting, le stringhe tra virgolette doppie del codice (`"published"`) diventavano nomi di colonna e tutto falliva.

**Altro, per area**

- *Flussi*: condizione del bivio arrivata come array (fatale); campo link `type=url` che bloccava il Salva; nome vuoto senza messaggio; tela inutilizzabile sotto 1100 px (ora una colonna); `ALTER TABLE` delle colonne nuove una per volta; battito `?ping=1` ogni 5 minuti e copia del disegno in `localStorage` con "Riprendi / Scarta" se la sessione scade; `newsletter_conferma` faceva partire i flussi per l'ultima riga toccata nel minuto invece che per chi aveva il token; `api/nl.php`: redirect aperto con firma falsa (ora solo verso il sito), `u[]` e a capo negli header rifiutati, `Content-Length` del GIF sbagliato di un byte; la codifica `b64:` del solo grafo tolta a favore di quella generica.
- *Form e popup*: le opzioni di una scelta rompevano il costruttore a ogni ridisegno (`opzioni.join`), con i campi successivi spariti: ora opzioni una per riga in una casella; errore di salvataggio che perdeva tutto; etichette doppie che si coprivano nei dati; tipo nuovo "Scelta multipla (caselle)"; sul sito il campo sbagliato è segnato in rosso e i valori tornano (`sessionStorage`), id dei moduli unici (`modulo-f<ID>`); titolo del ringraziamento non escapato; invii del vecchio modulo con `phone` NULL (fatale); popup "tutte le pagine" che non usciva su blog, glossario e categorie (`sito_percorso` non impostato), percorsi normalizzati, form dentro il popup che dopo un errore tornava con il popup chiuso (ora `popup=ID` e `data-subito`), `moduli.js` caricato due volte.
- *Pannello*: ruoli (`require_admin()` su Utenti, Impostazioni, Aggiornamenti, Importa SQL; menu per ruolo); il nome di un gruppo del menu ora è un link; link rotti nei tag e nelle statistiche dei test; test chiuso che serviva ancora il vecchio contenuto; `importa-sql.php` da 9 MB a 82 KB; `"«$s»"` che in PHP è la variabile `$s»`; `fputcsv` senza escape (8.4); `posta.php` con server senza AUTH.
- *Contenuti*: ricerca fra gli aforismi fatale (`keyword_target`); ripristino di una revisione con slug diverso che spezzava l'indirizzo; "metti in bozza" di gruppo che spegneva la home (404 su `/`); autore della citazione salvato e mai mostrato; eliminazione definitiva che lascia revisioni orfane.
- *Server*: `.htaccess` di `/new/` con `Require all denied` dentro `<IfModule>` (il pacchetto non può scriverlo: si cambia a mano solo se `/new/` risponde 500); i cron caricano `config.php` per primo e controllano la versione di PHP.

**Da controllare sul server di Danilo** (nessuna di queste cose si prova da qui): il log "Errori" di cPanel cercando `ModSecurity: Access denied` su `/new/admin/`; il nuovo `public_html/new/logs/php-errori.log` dopo un giorno di uso; versione PHP 8.4 del dominio e limiti (`post_max_size` e `upload_max_filesize` almeno 64M, `max_input_vars` 3000); le due righe cron con il binario di PHP 8.4 di cPanel.

**Trappola emersa**: il sito di prova girava con `display_errors` spento ed `error_reporting` senza `E_DEPRECATED`, quindi in locale le deprecazioni non si vedevano mai. Un giro del pannello che risponde 200 non prova che una pagina funzioni: la scheda del contenuto rispondeva 200 con un errore fatale dentro. Le prove nel browser, come un utente, restano l'unica verifica che conta.

## Revisione del 24 ottobre sera, area pannello (dettaglio)

- La dashboard si rompeva (errore fatale, `h(null)`) quando la posta SMTP non era configurata: la riga Posta leggeva `posta_config()['host']` su un array vuoto. Sul server di Danilo, senza SMTP compilato, la dashboard non si apriva.
- `posta_config()` scartava la configurazione se mancava l'utente, e la prova di invio tornava a `mail()` senza dirlo. Ora un server senza utente si usa senza AUTH, e senza mittente l'errore lo dice.
- Nella scheda Tag i link Modifica e Annulla erano rotti (`categories.php&modifica=`): `admin-header.php` sovrascrive `$qui`, la pagina ora usa `$baseLink`.
- Il link Statistiche di `test.php` passava lo slug dove la pagina vuole l'id: apriva sempre "Nessun test ancora caricato".
- `"«$s»"` in una stringa PHP è la variabile `$s»` (le lettere oltre l'ASCII valgono come nome): i messaggi di categorie e form perdevano il nome. Si scrive `{$s}`.
- `importa-sql.php` incorporava nella pagina il contenuto di tutti i file di `sql/` (9 MB a ogni apertura, e 8 MB di POST per eseguirne uno, oltre il `post_max_size` di cPanel): ora la pagina manda solo il nome, il server legge il file, l'anteprima è `?vedi=nome`.
- Ruoli: `require_admin()` e menu per ruolo (vedi sopra). Prima un redattore apriva e usava Utenti, Impostazioni, Aggiornamenti e Importa SQL dall'indirizzo.
- `cron-log.php?clear` senza token dava un avviso di chiave mancante; `form-submissions.php` chiamava `fputcsv` senza il parametro di escape (deprecato su PHP 8.4).
- Download con il buffer di `auth.php` attivo: CSV degli iscritti, dei contatti, degli invii dei form, dei test, JSON della diagnosi, testo della nota di metodo, JSON di `?progresso`: tutti con il tipo giusto e i primi byte puliti (BOM o `{`), nessun HTML davanti. Il pannello non ha download di zip o immagini da PHP.

## Pacchetto

`releases/pacchetti/2026-10-24-revisione-pannello.zip` (12): tutto il codice, 131 file, cumulativo: sostituisce dal 4 all'11. Il 10 (file da togliere) resta a parte e va applicato se non lo è già. Le tabelle nuove si creano da sole alla prima apertura delle pagine. Il pacchetto 11 (`2026-10-24-pannello-nuovo.zip`) resta nel repository come storia.

## Collaudo del 24 ottobre

Tela dei flussi, nel browser (Playwright): flusso nuovo, oggetto e ascolto di 2 ore sulla prima email, blocchi Attesa (3 giorni), Bivio (non ha aperto la prima email), seconda Email e Fine aggiunti con un clic e trascinando dalla tavolozza, sei collegamenti tirati dai pallini, uno tolto con Canc e rimesso, un blocco spostato, innesco "non fa niente da 45 giorni", salvato e ricaricato uguale (6 blocchi, 6 collegamenti). Motore, dalla riga di comando: due iscritti fermi da 60 giorni entrano al giro, un terzo che ha aperto ieri resta fuori; prima email mandata a entrambi e cammini in ascolto; A apre (firma giusta) e finisce da *Se apre*; B scade, passa da *Se non fa niente* all'attesa, poi al bivio (non ha aperto: Sì), riceve la seconda email e finisce; al giro dopo nessuno rientra. Il flusso vecchio "Benvenuto" convertito in grafo parte ancora all'iscrizione e manda la sua email.

Sul sito di prova con MySQL 8: le 30 pagine del pannello senza errori PHP (giro del pannello). Form creato, messo in un widget Modulo e in un popup, compilato dal sito con il captcha risolto come fa `moduli.js`: 303 verso `/grazie/?f=3`, riga in `moduli_invii`, visibile in Invii dei form e come avviso in dashboard; con l'email sbagliata torna indietro con `?modulo=email`. Popup sulla home con il form dentro e `popup.js`. Iscritto confermato: il flusso "Benvenuto" ha creato il cammino, il giro lo ha mandato al finto server SMTP, con pixel e link riscritti; il pixel e il clic hanno scritto una riga a testa in `eventi`, la ripetizione non ne ha aggiunte, una firma falsa non ha scritto niente; Statistiche mostra 1 apertura e 1 clic. Infografica di quattro tipi dal widget, con le classi `ig-`. Utente creato ed eliminato dalla pagina Utenti. Pacchetto 11 applicato dal motore degli Aggiornamenti: 128 file scritti, poi di nuovo giro del pannello pulito e contratto dei 1.005 indirizzi con 0 differenze.

## Voci aggiunte la sera del 25 ottobre (pacchetto 15)

Quattro voci nuove, ognuna con il suo documento:

| Voce | Dove | Documento |
|---|---|---|
| Stile del sito | sotto Pagine fino al 26 ottobre, poi nel gruppo Editor, `admin/design.php` | `stile-sito.md` |
| Header e footer | idem, `admin/modelli.php` | `header-footer-menu.md` |
| Menu di navigazione | idem, `admin/menu.php` | `header-footer-menu.md` |
| SEO e AEO | voce a sé, `admin/seo.php` | `seo.md` |

Nella scheda classica di articoli, pagine e aforismi (`contenuto.php`) ci sono il riquadro **Header e footer** (Predefinito, Nessuno, o un modello) e il riquadro **Controllo SEO** con il punteggio e il pulsante Ricontrolla. Le stesse tendine stanno nelle Impostazioni dell'editor visuale. Tutte le tabelle nuove si creano da sole al primo uso: il pacchetto 15 porta solo codice.

## Il gruppo Editor (26 ottobre 2026)

Richiesta di Danilo: "Voglio che tutte le impostazioni dell'editor che abbiamo creato siano tutte racchiuse in un unico menu." Il gruppo **Editor** (icona a entità `&#9998;`, come le altre voci nuove: niente emoji a 4 byte nel codice) raccoglie tutto quello che è dell'editor; sotto Pagine resta solo "Home nell'editor". L'elenco delle pagine che accendono il gruppo sta nella costante `EDITOR_MENU_PAGINE` di `admin-header.php`. Le voci del modulo danilofailli continuano a entrare da `cms_menu_integra()` (Importa SQL e Diagnosi articoli sotto Impostazioni, Aforismi, Test, Social copy).

| Voce | Pagina | Cosa fa |
|---|---|---|
| Stile del sito | `design.php` | com'era (`stile-sito.md`), più il pulsante Cronologia dell'agente versioni |
| Header e footer | `modelli.php` | com'era (`header-footer-menu.md`) |
| Menu di navigazione | `menu.php` | com'era |
| Temi | `temi.php` (nuova, 27 ottobre, solo admin) | i temi del sito in schede: Aspetto di base (nessun tema) e un tema per cartella di `temi/`, con l'anteprima dei colori (o l'immagine del tema), versione, autore, cartella, etichette attivo, guasto, PHP. **Anteprima** apre il sito con quel tema in una scheda nuova (solo per l'amministratore, niente salvato); **Attiva** chiede conferma spiegando cosa cambia (e avvisa se il tema contiene PHP); **Personalizza** porta a Stile del sito. Se il tema attivo si è rotto, avviso rosso con l'errore e i pulsanti Riprova ad attivarlo e Resta sull'aspetto di base; la scheda Aspetto di base dice "in uso adesso". Legge solo i JSON dei temi: il PHP di un tema non gira nel pannello. Dalla fase 6: **Carica un tema** (zip, con PHP solo firmato o con la finestra di Sicurezza aperta, casella Sostituisci), **Scarica** per ogni tema, **Scarica l'aspetto di adesso come tema**, **Elimina** per i temi non in uso (copia in `aggiornamenti/temi-precedenti`); aprendo la pagina si scrive `temi/.htaccess` se manca. Motore e formato in `docs/temi.md` |
| Colori delle infografiche (fino al 27 ottobre "Temi delle infografiche"; Carta segue il tema del sito, vedi `temi.md`) | `editor-temi.php` (nuova) | i temi dei widget `ig_*`: i 10 preimpostati e quelli salvati dall'editor, ognuno con l'anteprima dei colori (testata, corpo, bordo, voci) e il conteggio dei contenuti che lo usano. Rinomina, duplica, elimina con conferma (solo i salvati), esporta uno o tutti in JSON, importa da JSON (stesso nome = aggiorna). Un widget che usava un tema eliminato torna all'aspetto Carta |
| Widget | `editor-widget.php` (nuova, solo admin) | tutti i widget registrati, per gruppo, con tipo, provenienza (nucleo o modulo, dalla chiave `origine` che `editor_registra_widget()` scrive guardando il file che registra) e in quanti contenuti compaiono. Interruttore attivo/spento, cerca, accendi o spegni un gruppo. Un widget spento sparisce dalla tavolozza dell'editor (`D.widgetDisattivi` in `editor.js`) ma le pagine che lo contengono lo mostrano e lo disegnano ancora; l'elenco sta nell'impostazione `editor_widget_disattivi` |
| Versioni | `versioni.php` | la cronologia di ogni modifica (agente versioni, `docs/versioni.md`) |
| Impostazioni dell'editor | `editor-impostazioni.php` (nuova, solo admin) | quante versioni tenere per oggetto (`editor_versioni_limite`, da 5 a 500), chi pubblica dall'editor (`editor_pubblica`: tutti oppure solo admin; il redattore vede Pubblica spento e l'API rifiuta con 403), colonne massime di una sezione (`editor_colonne_max`, da 2 a 6), animazioni d'ingresso sul sito on/off (`editor_animazioni`: spente, `editor_attributi_avanzate()` non scrive le classi `ed-anima-*`), le larghezze e le soglie come rimando a Stile del sito > Layout. **Esporta** scarica un JSON unico con stile, header e footer con strutture e condizioni, menu, temi salvati, opzioni e widget spenti; **Importa** mostra prima cosa cambierebbe (chiave per chiave per lo stile e le opzioni; nuovo, cambia o identico per modelli, menu e temi, riconosciuti dal nome) e applica solo gli ambiti spuntati. Motore in `includes/editor-opzioni.php` |
| Informazioni | `informazioni.php` | versione del CMS, moduli, server, tabelle, widget, novità (era sotto Impostazioni) |

Le opzioni vivono nella tabella delle impostazioni con il prefisso `editor_` e si leggono con `editor_opzione('chiave')`; i predefiniti stanno in `EDITOR_OPZIONI_PREDEFINITE`, quindi un'installazione senza righe nel database si comporta come prima. Il file caricato per l'importazione aspetta in `aggiornamenti/editor-importa-<token>.json` fra l'anteprima e la conferma, con il token legato alla sessione, e sparisce a conferma o annullamento.

Provato il 26 ottobre con Playwright come un utente (`audit4/menu/prova-menu.js`, 44 controlli): gruppo e voci, ogni pagina 200 e pulita, widget icona spento e sparito dalla tavolozza mentre la pagina che lo usa lo mostra ancora sul sito e nella tela, tema Carta duplicato, rinominato, esportato, eliminato con conferma e reimportato dal JSON, opzioni salvate (6 colonne nel pulsante Struttura), esportazione completa, colore primario cambiato in rosso e tornato al valore di prima importando solo lo stile. Stesso giro sull'installazione pulita del CMS (prefisso `cms_`, senza moduli).
