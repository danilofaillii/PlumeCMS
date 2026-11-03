# Addon Test e questionari (tecnico)

Cartella `cms/addon/test/`. Addon della 2.22.0. Spento non carica niente: le pagine `/fai-il-test/...` rispondono 404
come ogni indirizzo che non c'è, e il widget Riquadro in evidenza torna com'era (niente tendina, il link scritto a mano).

**Perché `/fai-il-test/` e non `/test/`.** Il `.htaccess` della radice di ogni installazione (e gli esempi per Apache e
Nginx in `install/esempi/`, e il router delle prove) vieta la cartella `test/` del CMS:
`RewriteRule ^(includes|logs|...|test|test-definizioni)(/|$) - [F,L]`. Su ogni sito vero `/test/<nome>/` darebbe 403.
Cambiare la regola vorrebbe dire riscrivere il `.htaccess` dei siti già installati (i pacchetti lo possono fare solo con
il motore nuovo, e Danilo ha `.htaccess` scritti a mano). Il prefisso è la costante `TEST_PERCORSO` (`test.php:54`);
`modulo.php` lo ripete nel controllo veloce dell'evento `sito_percorso`.

## File

| File | Cosa |
|---|---|
| `modulo.php` | manifesto: `installa`, `pannello` (test, statistiche), `menu` (Test, dopo "Newsletter e form", solo admin), `editor_contenitori` (`test_presentazione`), filtri `collegamenti_sito`, `riquadro_test_scelte`, `riquadro_test_link`, `automazioni_inneschi`, `automazioni_inneschi_cosa`, eventi `sito_percorso` e `newsletter_confermata` |
| `includes/test.php` | il motore: tabelle, lettura e salvataggio, `test_normalizza()`, calcolo, esiti, firma dei link, codice del modulo, limiti, statistiche, simulatore, inneschi |
| `includes/modelli.php` | i tre modelli pronti (Quanto riposi davvero?, Il tuo equilibrio fra lavoro e vita, Che viaggiatore sei?), domande scritte per plumeCMS |
| `includes/vista.php` | l'HTML pubblico: modulo con tutte le domande, grafico SVG, risultato, riquadro dell'email, email del risultato |
| `includes/sito.php` | gli indirizzi pubblici |
| `includes/admin-comune.php`, `admin-test.php`, `admin-statistiche.php` | il pannello |
| `assets/test.js`, `assets/test.css` | sito: una domanda per schermata, ripresa, richieste leggere; pagina del risultato |
| `assets/admin-test.js`, `assets/admin.css` | pannello: gli editor dei passi 2, 3 e 4 |

## Tabelle

Tutte `utf8mb4` (i nomi e i testi possono avere caratteri a 4 byte), create da `test_installa()` (`test.php:130`) e
ricontrollate da `test_schema()` (`test.php:136`) con l'impronta `test_schema_impronta`.

| Tabella | Colonne | Cosa |
|---|---|---|
| `<prefisso>test_test` | `id`, `slug` (UNIQUE), `nome`, `tipo` (`punteggio`, `aree`, `profili`), `stato` (`bozza`, `pubblicato`), `durata` (minuti, 0 = calcolata), `dati` (JSON, sotto), `struttura`, `struttura_bozza`, `struttura_aggiornata` (la presentazione con l'editor), `creato`, `aggiornato` | i test |
| `<prefisso>test_risultati` | `test_id`, `giorno` (DATE), `totale` (0-100 o NULL), `fascia`, `profilo`, `regola` (chiavi), `aree` (JSON chiave => percentuale; nei profili chiave => punti) | un risultato: niente ora, niente IP, niente email |
| `<prefisso>test_risposte` | `test_id`, `domanda`, `valore`, `n` (PRIMARY sulle prime tre) | contatori delle risposte |
| `<prefisso>test_tentativi` | `codice` (16 caratteri casuali fatti dal browser, PRIMARY), `test_id`, `giorno`, `ultima` (domanda raggiunta), `passi`, `completato` | iniziati e abbandoni |
| `<prefisso>test_limiti` | `impronta` (HMAC di IP e giorno), `chiave` (`invio`, `email`, `inizio`), `creato` | i limiti per connessione; righe cancellate dopo un giorno |
| `<prefisso>test_attese` | `iscritto_id`, `test_id` (PRIMARY), `esiti`, `creato` | l'innesco di chi deve ancora confermare l'iscrizione; dopo 40 giorni si cancella |

Le risposte di una persona non stanno mai in una riga sola: `test_risultati` ha l'esito, `test_risposte` solo contatori,
`test_tentativi` un codice casuale senza legame con il risultato (il codice non va in `test_risultati`).

## Il formato di `dati`

`test_normalizza($dati, $tipo)` (`test.php:223`) lo controlla tutto, sia quello che arriva dal pannello sia quello
che si legge dal database: chiavi interne `^[a-z][a-z0-9]{1,15}$` (`test_chiave()`), numeri nei limiti, rimandi
(area, condizione, profili, regole) verso cose che esistono, testi ricchi ripuliti (`test_ricco()`, profilo `ricco` di
`sicurezza_html_pulito()`, come il widget Testo), link con `editor_link_sicuro()` (`test_link()`).

```
scala      [{t: 'Mai', v: 0}, ...]                   da 2 a 7 gradini (partenza Mai..Sempre = 0..4)
aree       [{k, nome, fasce: [...]}]                  solo nei test ad aree, al massimo 12
profili    [{k, nome, testo, pulsante_testo, pulsante_link}]   solo nei profili; l'ordine è la priorità in parità
domande    [{k, testo, aiuto, tipo: scala|scelta|sino|cursore, invertita, peso (0-10, mezzi), obbligatoria, area,
             opzioni: [{k, t, punti, profili: {chiave profilo: punti}}], condizione: {domanda, valori: [...]} | null}]
           al massimo 80; nei profili solo scelta e sì/no; sì/no ha sempre le opzioni 'si' e 'no';
           una condizione punta solo a una domanda che viene prima e non è un cursore
fasce      [{k, nome, da, a, testo, pulsante_testo, pulsante_link}]   del totale (non nei profili), al massimo 8
regole     [{k, nome, modo: e|o, condizioni: [{cosa: 'totale' | 'area:<k>', fascia}], testo, pulsante_testo, pulsante_link}]
avvertenza testo semplice (partenza "Questo test non è una diagnosi...")
casuale    ordine casuale delle domande
presentazione  testo semplice breve (descrizione per Google e i social)
dopo       {email: bool, consenso: testo, lista: id, iscrizione: testo}
```

## Il calcolo

`test_calcola($test, $risposte)` (`test.php:580`): funzione pura (non legge né scrive il database), la stessa usata
dalla pagina, dal simulatore e dalle prove (`prove/test-cli.php calcola`).

1. Le risposte: domanda => valore. Una domanda che non esiste si scarta (errore `sconosciuta`, che non ferma il calcolo);
   un valore che non è una risposta della domanda (`s9`, `11`, `forse`, un elenco) dà `valore`, e ferma.
2. Le domande mostrate (`test_mostrate()`, `test.php:506`): in ordine; una condizionale solo se quella da cui
   dipende è mostrata e ha una delle risposte. Le risposte delle domande non mostrate si scartano.
3. Una domanda mostrata e obbligatoria senza risposta: errore `obbligatoria`.
4. I punti (`test_punti_risposta()`, `test.php:495`): i punti della risposta; invertita = massimo + minimo -
   punti. Per la scala i punti della scala, per il cursore il numero (0-10).
5. Percentuale = (punti - minimo) / (massimo - minimo) x 100, arrotondata all'intero, sommando con il peso **solo le
   domande mostrate e risposte**. Con il minimo a zero (tutte le scale di partenza) è punti / massimo. Lo stesso per ogni area.
6. La fascia: quella che contiene la percentuale (`test_fascia()`). Le percentuali sono intere, quindi le fasce si
   controllano come intervalli di interi (`test_fasce_controlla()`, `test.php:526`: da 0, a 100, nessun buco,
   nessuna sovrapposizione, ogni messaggio dice dove).
7. Le regole, in ordine: vince la prima vera (E: tutte le condizioni; O: almeno una).
8. Nei profili: la somma dei punti per profilo (con il peso); vince il massimo, in parità il primo dell'elenco.

`test_problemi()` (`test.php:543`) dice cosa impedisce di pubblicare (niente domande, fasce sbagliate, area senza
domande, profilo che nessuna risposta premia, tutte le domande condizionali).

`test_simula()` (`test.php:848`): tutte al minimo, a metà, al massimo (secondo i punti, con l'inversione) e N prove a
caso (100-2000); dice le fasce, le regole o i profili che non escono mai.

## Il link del risultato

L'esito corto (`test_esito_corto()`, `test.php:678`): `t` (id del test), `g` (giorno), `o` (totale), `f` (fascia),
`a` (aree: chiave => [percentuale, fascia]), `r` (regola), `p` e `q` (profilo e quota di ogni profilo). Niente risposte,
niente dati personali. Va nel link in base64url (`e`) con la firma HMAC-SHA256 di `moduli_segreto()` (`f`, 32 caratteri,
`test_firma()`). `test_esito_da_link()` (`test.php:712`) rifiuta firma sbagliata, esito di un altro test,
esito non JSON o più lungo di 3000 caratteri: la pagina dice "Questo link non funziona" (404).

Il riquadro dell'email compare solo con `k`, un secondo permesso firmato con la scadenza (`test_chiave_email()`,
`test.php:721`, due ore): il link da condividere non ce l'ha, quindi chi lo riceve non può farsi mandare (o
mandare ad altri) l'email né far partire le automazioni.

## Gli indirizzi pubblici

`test_sito()` (`sito.php:14`):

| Indirizzo | Cosa |
|---|---|
| `GET /fai-il-test/<nome>/` | `test_sito_pagina()`: titolo, "N domande, circa M minuti" (`test_quanto_testo()`), presentazione dell'editor, Inizia il test, il modulo con tutte le domande (`test_modulo_html()`, `vista.php:52`), avvertenza. Bozza: solo nell'anteprima dell'amministratore (`?preview=1`) |
| `POST /fai-il-test/<nome>/invia/` | `test_sito_invia()` (`sito.php:88`): difese, calcolo, registrazione, 303 al risultato |
| `GET /fai-il-test/<nome>/risultato/?e=&f=[&k=]` | `test_sito_risultato()`: noindex, `no-store`, `Referrer-Policy: no-referrer` |
| `POST /fai-il-test/<nome>/email/` | `test_sito_email()` (`sito.php:129`): email del risultato, iscrizione, innesco |
| `POST /test-api/passo/` | `test_sito_passo()`: la richiesta leggera (test, codice del tentativo, domanda, passo), 204 |

**Difese dell'invio** (il test si manda anche senza JavaScript, quindi niente captcha, che è una prova di lavoro fatta
dal browser): Origin dello stesso sito, campo esca `sito_web`, codice del modulo firmato (`test_gettone()`,
`test.php:737`: test e ora di apertura della pagina; falso, di un altro test o più vecchio di un giorno: 400;
mandato prima di `TEST_SECONDI_MINIMI` secondi, come fa un robot: 429), limite di 20 invii l'ora per connessione
(`test_limite()`, `test.php:769`, impronta HMAC di IP e giorno, mai l'IP in chiaro: 429). L'email del risultato ha
anche il captcha dei moduli del sito (`moduli_captcha_valido()`) e il limite di 5 l'ora.

**Senza JavaScript** il modulo è una pagina sola con tutte le domande (`required` sulle obbligatorie), le condizionali
dicono "Rispondi solo se alla domanda N hai risposto «...»" e il server ignora le risposte delle domande non mostrate.
Un invio con errori torna alla pagina (422) con le risposte date, il riassunto in cima e l'errore sotto ogni domanda
(`role=alert`, `aria-describedby`).

**Con JavaScript** (`assets/test.js`): la presentazione e Inizia il test; una domanda per schermata (fieldset con legend),
barra `role=progressbar` con `aria-valuenow` e `aria-valuetext`, Indietro, Avanti, Invio sulle risposte va avanti, il
fuoco sulla domanda; la condizionale entra e esce dall'elenco quando cambia la risposta da cui dipende; l'ordine casuale
mescola le domande fisse e mette ogni condizionale dopo la sua. Le risposte restano in `localStorage`
(`plume-test-<id>`, con `try/catch`): "Riprendi da dove eri" o "Ricomincia". Le richieste leggere partono all'inizio e a
ogni domanda nuova (`sendBeacon`). All'invio le risposte delle domande nascoste non partono.

## Dopo il test

- **Email del risultato**: `test_email_corpo()` (`vista.php:209`), con `moduli_email_html()`, tipo `test` nel
  registro della posta. Il consenso è una casella sua, separata dall'iscrizione, e parte vuota.
- **Iscrizione**: con la casella e una lista scelta nel passo 5, `newsletter_richiesta()` (doppia conferma come i form,
  fonte `test`). Solo allora l'email entra nei Contatti, con una riga di storia `test` ("Ha completato il test «...»:
  Alto (73 su 100)"), che la Cronologia mostra fra i form (`newsletter_contatto_cronologia()`, mappa `'test' => 'form'`).
  Senza iscrizione l'indirizzo serve solo all'invio.
- **Innesco "Completa un test"** (`test_innesca()`, `test.php:901`): per un contatto attivo parte subito,
  altrimenti si ricorda in `test_attese` e parte alla conferma (evento `newsletter_confermata`, `test_alla_conferma()`).

### Le modifiche al nucleo per l'innesco

- `includes/newsletter-flussi.php`: `nl_inneschi_nomi()` (`includes/newsletter-flussi.php:24`) = `NL_INNESCHI` più il filtro
  `automazioni_inneschi` degli addon; un innesco `test` salvato resta valido anche con l'addon spento (con il nome
  "addon spento"). `nl_inneschi_validi()` tiene per `test` l'id del test e `esito` (`f:<fascia>`, `a:<area>:<fascia>`,
  `p:<profilo>`, `r:<regola>`, oppure vuoto). `newsletter_flussi_innesco()` ha un quarto argomento facoltativo `$esiti`: un
  innesco con l'esito parte solo se il suo codice è fra quelli del risultato. Gli inneschi di prima non cambiano.
- `admin/newsletter-flussi.php`: i nomi da `nl_inneschi_nomi()`, le scelte degli addon dal filtro
  `automazioni_inneschi_cosa` (i test, e in `_esiti` i risultati di ogni test), passati alla tela come `FL_DATI.esiti`.
- `admin/assets/flussi.js`: per un innesco con `esiti`, la tendina "Con quale risultato".

## Il widget Riquadro in evidenza

`includes/editor-widget.php`: `editor_riquadro_test_scelta()` aggiunge la tendina "Oppure porta a un test" (controllo
`test`, `accetta` `^\d{1,9}$`) solo se il filtro `riquadro_test_scelte` dà dei test; al disegno, con un test scelto, il
filtro `riquadro_test_link` dà `/fai-il-test/<nome>/` se il test è pubblicato. Con l'addon spento i controlli sono quelli
di prima e l'HTML è lo stesso: le pagine che usano già il widget non cambiano (provato con le foto prima e dopo).

## La presentazione con l'editor

Contenitore dell'editor `test_presentazione` (manuale tecnico 9.12, `test_contenitore()`, `test.php:433`): solo
l'amministratore; la cornice della tela ha il titolo sopra e il pulsante sotto. Un test nuovo nasce con una sezione
stretta e un widget Testo, pubblicata (`test_presentazione_partenza()`; senza il contenitore registrato nella richiesta
scrive direttamente nella tabella, perché `editor_salva()` scriverebbe nella tabella delle pagine).

## Pannello

`admin-test.php`: elenco (Modifica, Statistiche, Vedi la pagina o Anteprima, Duplica, Elimina con la conferma del
pannello), Nuovo test (tipo o modello, solo in POST: aprire la pagina non crea niente), la procedura in cinque passi:

| Passo | Campi | Salvataggio |
|---|---|---|
| 1 Presentazione | nome, indirizzo, tipo, durata, link all'editor, testo semplice | campi del test |
| 2 Domande e aree | aree o profili, scala, domande (editor in JS, SortableJS del pannello e frecce), ordine casuale | `dati_json` (le fasce delle aree già scritte restano) |
| 3 Punteggi e fasce | fasce del totale e di ogni area (controllo dal vivo), simulatore | `fasce_json` (i testi già scritti restano) |
| 4 Risultati | testi (Quill, `pnLink.quill`), pulsanti (`data-link-sito`), regole (JS), avvertenza | `testi[...]`, `regole_json`, `avvertenza` |
| 5 Dopo il test | email, consenso, lista, iscrizione; automazioni; privacy; Pubblica / Rimetti in bozza | `dopo` |

I JSON viaggiano nei campi nascosti, in base64 con `campi-protetti.js` (ModSecurity). Ogni POST con `verify_csrf()`;
le pagine sono di `addon-pagina.php` (solo amministratore).

`admin-statistiche.php`: test e periodo (7, 30, 90, 365 giorni, da sempre; `test_statistiche()`, `test.php:816`),
iniziati, completati, quota, media del totale, distribuzione per fascia, profilo e regola, media per area (o punti per
profilo), domanda per domanda (fermati qui, risposte date). Il CSV (`;`, BOM, `sicurezza_csv_riga()`) ha le stesse righe
aggregate.

## Sicurezza (riassunto)

- Pannello: solo amministratore (redattore e anonimo no, anche il CSV e l'editor della presentazione), CSRF e Origin.
- Calcolo sempre sul server; risposte inventate scartate o rifiutate; test in bozza 404 sul sito.
- XSS: testi semplici con `h()` ovunque, testi ricchi ripuliti al salvataggio e di nuovo all'uscita, link con
  `editor_link_sicuro()` (niente `javascript:`), JSON nella pagina con `JSON_HEX_*`.
- SQL: solo query preparate; le chiavi sono controllate con le espressioni regolari.
- CSV: celle con l'apice davanti a `= + - @`.
- Privacy: nessun IP in chiaro, risultati con il solo giorno, contatori senza persone, email solo con il consenso e
  nella scheda del contatto solo con l'iscrizione.

## Prove

| Prova | Cosa |
|---|---|
| `prove/prova-test.js` | i tre tipi dal pannello, il visitatore con e senza JavaScript, telefono, link, simulatore, statistiche, CSV, email, lista, automazioni, Riquadro, Scegli dal sito, Duplica, Elimina, spento e riacceso |
| `prove/attacchi-test.js` | pannello (anonimo, redattore, CSRF, Origin), invii (domande inventate, valori fuori scala, bozza, codice del modulo, tempo, esca, raffica), XSS in ogni campo, link falsificati, email senza permesso, SQL injection, CSV |
| `prove/test-cli.php` | gli aiuti da riga di comando delle due prove |

## Limiti

- Niente domande a più risposte, immagini nelle risposte, più domande per schermata.
- L'email del risultato vuole JavaScript (captcha); il test no.
- Chi si fa mandare il risultato senza iscriversi non entra nei Contatti, quindi le automazioni partono solo per chi si iscrive.
- Gli abbandoni contano chi ha cominciato con JavaScript (il codice del tentativo); senza JavaScript l'inizio si conta all'invio.
- I link condivisi portano l'esito di quel giorno: se l'autore cambia o toglie una fascia, il link mostra il punteggio
  e il testo nuovo (o il solo punteggio).
