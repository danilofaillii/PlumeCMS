# Addon Prenotazioni e appuntamenti (tecnico)

Cartella `cms/addon/prenotazioni/`. Addon della 2.0.0, versione 1.1.0 con la 2.1 (prenotazione scritta dal pannello,
file .ics allegato alle email). Manifesto come i moduli, `docs-sviluppo/cms-moduli.md`, sezione
"Gli addon"). Spento non carica niente: nessuna tabella letta, nessun widget, nessun indirizzo pubblico.

## File

| File | Cosa |
|---|---|
| `modulo.php` | manifesto: `installa`, `pannello` (6 pagine, con `nuova` dalla 2.1), `menu` (dopo "Newsletter e form", solo admin), `widget`, `cron_spesso`, evento `sito_percorso` |
| `includes/prenotazioni.php` | il motore: tabelle, impostazioni, posti liberi, prenota/sposta/annulla, email, .ics, cron |
| `includes/vista.php` | l'HTML del calendario e del modulo (widget e pagina "sposta"), i messaggi d'errore |
| `includes/sito.php` | gli indirizzi pubblici `/prenotazione/...` |
| `includes/widget.php` | il widget **Prenota** (`prenota`, gruppo Dal sito) |
| `includes/admin-*.php` | le pagine del pannello: `admin-comune.php` (schede, "+ Nuova prenotazione", chiusure), `admin-agenda.php`, `admin-nuova.php` (2.1), `admin-elenco.php`, `admin-servizi.php`, `admin-persone.php`, `admin-impostazioni.php` |
| `assets/prenota.js`, `assets/prenota.css` | il calendario nel browser; `assets/admin.css` le pagine del pannello |

## Tabelle

Si creano accendendo l'addon (`pren_installa()`, `prenotazioni.php:96`) e, dopo un aggiornamento che le cambia, alla
prima richiesta (`pren_schema()`, `prenotazioni.php:116`, impronta in `pren_schema_impronta`). Tutte `utf8` a 3 byte,
InnoDB, provate con MySQL 8 in modalità stretta.

- `<prefisso>pren_servizi`: `nome`, `descrizione`, `durata` e `pausa` (minuti), `prezzo` (testo), `risorse` (id separati
  da virgola; vuoto = tutte), `attivo`, `ordine`.
- `<prefisso>pren_risorse`: le persone (o cabine, campi): `nome`, `orari` (JSON `{lun: "9:00-13:00, 15:00-19:00", ...}`),
  `ferie` (JSON `[{dal, al, motivo}]`), `attivo`, `ordine`.
- `<prefisso>pren_prenotazioni`: `codice` (32 hex casuali, segreto del link), `servizio_id`, `risorsa_id`, copie di
  `servizio`, `risorsa`, `prezzo`; `inizio`/`fine` (DATETIME nel fuso del sito) e `inizio_ts`/`fine_ts`/`blocco_ts`
  (secondi veri; `blocco_ts` = fine + pausa); `nome`, `email`, `telefono`, `note`; `stato` (`da_confermare`,
  `confermata`, `svolta`, `non_presentato`, `annullata`); `slot_attivo` colonna generata
  `IF(stato IN ('confermata','da_confermare'), inizio_ts, NULL)` con **UNIQUE (risorsa_id, slot_attivo)**;
  `messaggio` (dell'attività, annullando), `origine` (2.1: `sito` o `pannello`), `sequenza` (2.1: la SEQUENCE del file
  .ics, cresce a ogni spostamento e all'annullamento), `promemoria_at`, `consenso_at`, `ip` (vuoto per quelle scritte dal
  pannello), `creato`, `aggiornato`.

Colonne nate dopo la 2.0.0: `CREATE TABLE IF NOT EXISTS` non le aggiunge a una tabella che c'è già, quindi
`pren_colonne_nuove()` (`prenotazioni.php:108`, chiamata da `pren_installa()`) le aggiunge con `ALTER TABLE` se mancano;
l'impronta dello schema cambia con le colonne, e la prima richiesta dopo l'aggiornamento le crea. Le righe che c'erano
prendono `origine = 'sito'` e `sequenza = 0` (provato in `prova-addon-prenotazioni.js`, 8b: colonne tolte, impronta
azzerata, giro del cron).

Impostazioni: `pren_impostazioni` (JSON, `pren_impostazioni()` `prenotazioni.php:127`, predefiniti in `PREN_PREDEFINITE`
`prenotazioni.php:23`): `anticipo_ore`, `anticipo_giorni`, `passo`, `limite_ore`, `da_confermare`, `scegli_persona`,
`avviso_a`, `promemoria`, `chiusure`, `indirizzo`, `nota_email`, `privacy`.

## Posti liberi

`pren_posti($servizio, $risorsa, $giorno, $adesso, $escludi)` (`prenotazioni.php:248`): per ogni persona che fa il
servizio (`pren_risorse_servizio()` `:196`), fuori da ferie e chiusure (`pren_chiuso()` `:213`), gli intervalli del giorno
letti con **la funzione del widget Orari di apertura** (`pren_intervalli()` `:219` chiama `ewx_orari_intervalli()` di
`includes/editor-widget-extra-d.php`), a passi di `passo` minuti. Un orario si offre se:

- esiste quel giorno: `setTime()` dell'ora scritta deve ridare la stessa ora (l'ultima domenica di marzo le 2:00 e le
  2:30 non esistono e si saltano);
- inizio + durata (in secondi veri) sta dentro l'intervallo: nell'ultima domenica di ottobre la notte ha un'ora in più e
  i conti tornano;
- è fra `adesso + anticipo_ore` e `adesso + anticipo_giorni`;
- `[inizio, inizio + durata + pausa)` non tocca nessun `[inizio_ts, blocco_ts)` di una prenotazione attiva della persona
  (`pren_occupati()` `:233`), tranne `$escludi` (quella che si sposta).

Ritorna `'H:MM' => [id delle persone libere]`. `pren_giorni()` (`:289`) conta i posti di N giorni (fine mese, fine anno,
29 febbraio: `prove/prenotazioni-cli.php`). Tutto nel fuso del sito, `date_default_timezone_get()` (`CMS_FUSO`, di
solito Europe/Rome). `$adesso` esiste per le prove a ore fisse.

## Niente doppie prenotazioni

`pren_prenota()` (`:374`) e `pren_sposta()` (`:450`) lavorano in `pren_transazione()` (`:329`, READ COMMITTED):
`pren_blocca()` (`:322`) prende con `SELECT ... FOR UPDATE` le righe delle persone del servizio in ordine di id (niente
incroci), poi si ricalcolano i posti e si scrive. La seconda richiesta aspetta la prima e vede il posto preso. In più
l'indice UNIQUE su `slot_attivo` ferma lo stesso inizio due volte anche fuori da queste funzioni; un 1062 diventa
l'errore `posto`. Provato con otto richieste HTTP in parallelo sullo stesso posto (`prove/attacchi-addon-b.js`, A1).

## Prenotazione scritta dal pannello (2.1)

Per le prenotazioni prese al telefono o al banco. Pagina `addon-pagina.php?a=prenotazioni&p=nuova` (`includes/admin-nuova.php`),
voce del menu "Nuova prenotazione" e pulsante "+ Nuova prenotazione" in ogni scheda (`pren_admin_schede()`,
`admin-comune.php:14`), "+ Nuova prenotazione in questo giorno" nell'agenda del giorno.

- Passo 1, modulo GET (`s`, `r`, `d`): servizio, persona ("Chi è libero" o una di quelle che fanno il servizio), giorno;
  le tendine e la data hanno `data-invia-al-cambio` (`pannello.js`): cambiando, la pagina si ricarica con gli orari.
  "Giorno prima", "Giorno dopo"; senza posti, il primo giorno libero nelle quattro settimane dopo.
- Passo 2: gli orari liberi come pulsanti grandi (radio), gli stessi del sito: `pren_posti(..., $pannello = true)`
  (`prenotazioni.php:248`) toglie solo gli anticipi minimo e massimo (l'attività prenota anche fra un'ora o fra sei mesi;
  mai nel passato). Con "Chi è libero" sotto l'ora c'è il nome della persona che la prenderà.
- "Un altro orario": ora scritta a mano e persona obbligatoria, con l'avviso "sotto la tua responsabilità". Si apre con
  il CSS (`.pa-modulo:not(:has(.pa-ora-altra input:checked)) .pa-forza{display:none}`), niente JavaScript.
- Il cliente: nome e telefono obbligatori (anche nel browser), email facoltativa (validata, niente a capo), nota
  (solo per l'attività), spunta "Manda al cliente l'email di conferma" (accesa).
- Il server (POST con `verify_csrf()`): servizio e persone devono essere numeri e quelli della pagina (niente ripieghi
  su un altro servizio), poi `pren_prenota_pannello($d, $forza, $email)` (`prenotazioni.php:412`).

`pren_prenota_pannello()`: le stesse regole del sito contro le doppie prenotazioni. Dentro `pren_transazione()`, dopo
`pren_blocca()`, prima i posti liberi (`pren_posti` con `$pannello`); se l'ora non è libera e non si forza: `posto`.
Forzando: persona obbligatoria (`persona`), e `[inizio, inizio + durata + pausa)` non deve toccare nessun appuntamento
attivo della stessa persona (`pren_occupati()`): altrimenti `occupato`. In più l'indice UNIQUE. L'ora deve esistere quel
giorno (`pren_istante()`, `:352`: data vera, ore 0-23, minuti 0-59, e niente ore saltate dal cambio dell'ora): altrimenti
`quando`. La riga nasce `confermata`, `origine = 'pannello'`, `ip` vuoto (`pren_inserisci()`, `:360`, la stessa del
sito); evento `prenotazione_nuova`; l'email di conferma (con l'allegato) solo con un indirizzo valido e la spunta;
nessun avviso all'attività. Un errore ripresenta il modulo con i campi scritti.

Senza email il cliente non riceve niente (conferma, promemoria, spostamento, annullamento): la scheda lo dice
("nessuna: il cliente non riceve email") e i messaggi del pannello dicono "avvisalo tu per telefono". Recensioni non lo
invita (`rec_invito_crea()` scarta un indirizzo non valido).

## Stati e azioni

- nuova: `confermata`, oppure `da_confermare` con l'impostazione; evento `prenotazione_nuova`;
- `pren_modificabile()` (`:445`): dal link del cliente si annulla o sposta fino a `limite_ore` prima;
- `pren_annulla($p, 'cliente'|'attivita', $messaggio)` (`:480`): evento `prenotazione_annullata`;
- `pren_cambia_stato()` (`:493`): conferma (manda l'email), `non_presentato` (solo dopo l'inizio; evento
  `prenotazione_non_presentata`), ritorno a `svolta`;
- il cron rende `svolta` le confermate finite (evento `prenotazione_svolta`);
- eliminare i dati dal pannello: evento `prenotazione_eliminata`.

Gli eventi sono il gancio per altri addon (Raccolta recensioni li ascolta) senza dipendenze: chi non li ascolta non se
ne accorge.

## Link personali

`pren_firma($id, $codice)` (`:141`): HMAC-SHA256 di `prenotazione|id|codice` con `moduli_segreto()` (lo stesso segreto
del captcha), 40 caratteri. `pren_da_link()` (`:146`) vuole id numerico, firma di 40 hex, `hash_equals`, e rifiuta i
link di appuntamenti finiti da più di `PREN_GIORNI_LINK` (30) giorni. `pren_url('gestisci'|'calendario', $p)` (`:154`).

## Indirizzi pubblici (`includes/sito.php`)

Evento `sito_percorso` (dentro `sito/index.php`, dopo i redirect): `pren_sito()` (`sito.php:13`) risponde solo a:

| Indirizzo | Funzione | Cosa |
|---|---|---|
| `GET /prenotazione/posti/?s=&r=&d=&da=[&p=&t=]` | `pren_sito_posti()` `sito.php:42` | JSON: 14 giorni da `da` con i posti, gli orari del giorno `d` (o del primo libero), le persone del servizio, il primo giorno libero più avanti |
| `POST /prenotazione/invia/` | `pren_sito_invia()` `:86` | campo esca, Origin (`pren_stessa_origine()` `:28`), nome, email, telefono, privacy, captcha (`moduli_captcha_valido()`), limite di 6 all'ora per IP (`pren_troppi()`), poi `pren_prenota()`; errore: 303 alla pagina con `?modulo=pren-<codice>` (moduli.js rimette i campi), riuscita: 303 alla pagina personale |
| `GET/POST /prenotazione/gestisci/` | `pren_sito_gestisci()` `:124` | la pagina personale; `&sposta=1` il calendario per spostare, `&annulla=1` la conferma; il POST vuole `p` e `t` nel modulo |
| `GET /prenotazione/calendario/` | `pren_ics()` `prenotazioni.php:610` | il file .ics (UTC, righe piegate a 75 byte, testo protetto) |

Le pagine escono con `sito_html()`, `robots noindex`, il foglio `assets/prenota.css` passato nella testa.

## Email

Con le funzioni del nucleo, senza toccarle: `posta_invia()`, `moduli_email_html()`, `moduli_intestazioni()`.
`pren_email_cliente()` (`:531`): ricevuta, confermata, spostata, promemoria, annullata (con il messaggio dell'attività).
`pren_email_attivita()` (`:577`): nuova, annullata, spostata, all'indirizzo `avviso_a` o a `posta_notifica_email()`, con
`Reply-To` del cliente. Oggetti passati da `pren_riga()` (`:519`, niente a capo). Tipo nel registro della posta:
`prenotazione`.

**Il file .ics come allegato vero (2.1).** `posta_invia()` della 2.0.3 accetta gli allegati (`multipart/mixed`).
`pren_email_cliente()` allega il file a tre email, oltre al link "Aggiungi al calendario" che resta:

| Email | Allegato | Content-Type | METHOD | STATUS |
|---|---|---|---|---|
| confermata (anche dal pannello) | `appuntamento.ics` | `text/calendar; charset=UTF-8; method=REQUEST` | REQUEST | CONFIRMED |
| spostata | `appuntamento.ics` | idem | REQUEST | CONFIRMED |
| annullata | `annullamento.ics` | `text/calendar; charset=UTF-8; method=CANCEL` | CANCEL | CANCELLED |

`pren_ics($p, $metodo)` (`prenotazioni.php:610`): lo stesso `UID` per sempre (`prenotazione-<16 hex del codice>@<host>`,
uguale anche nel file scaricato dal link, che resta `METHOD:PUBLISH`), `SEQUENCE` dalla colonna `sequenza`
(0 alla conferma, +1 a ogni spostamento e all'annullamento: `pren_sposta()` e `pren_annulla()` la aumentano nello stesso
UPDATE). Per REQUEST e CANCEL (RFC 5546) `ORGANIZER` è l'indirizzo dell'attività (`pren_indirizzo_attivita()`, o il
mittente del sito) e `ATTENDEE` il cliente (`PARTSTAT=ACCEPTED`, `DECLINED` annullando, `RSVP=FALSE`); i nomi nei
parametri `CN` perdono virgolette, barre, punti e virgola, due punti e virgole. Così il calendario del cliente aggiorna lo
stesso appuntamento (lo sposta, lo toglie) invece di aggiungerne un altro. Ricevuta (da confermare) e promemoria non
hanno allegato; nemmeno gli avvisi all'attività.

Provato leggendo davvero le email del server finto: parte MIME `text/calendar` con `method=`, `METHOD`, `UID` uguale a
quello del link, `SEQUENCE` 0, 1, 2 (`prova-addon-prenotazioni.js`, funzione `C.allegati()` di `addon-b-comune.js`).
Non provato con Gmail, Outlook o il Calendario di Apple veri (la sessione non li raggiunge).

## Cron

`cron_spesso` => `pren_cron()` (`:643`), nel giro frequente (`cron_newsletter()`, anche da Log cron > Esegui adesso):

1. promemoria alle confermate che cominciano entro 24 ore, fatte più di 24 ore prima dell'inizio: la riga si prende con
   `UPDATE ... SET promemoria_at = ? WHERE id = ? AND promemoria_at IS NULL` prima di spedire (mai due volte); se la
   spedizione fallisce `promemoria_at` torna NULL e si riprova al giro dopo;
2. confermate finite: `svolta`, evento `prenotazione_svolta`.

Ritorna una riga per il Log cron ("1 promemoria, 2 appuntamenti svolti") o niente.

## Widget Prenota

`includes/widget.php:18`, `editor_registra_widget('prenota', ...)`: titolo, testo, servizio fisso (scelta con
`accetta => /^\d{1,9}$/`, perché un servizio può arrivare dopo la pagina), persona fissa, testo del pulsante. Disegna
`pren_modulo_html()` (`vista.php:32`). Asset con `editor_registra_asset()`: i percorsi sono relativi a `sito/assets/`
(`sito_versione_asset()`), quindi `../../addon/prenotazioni/assets/prenota.css` e `.js`.

`assets/prenota.js`: chiede i posti, disegna giorni e ore, scrive `giorno` e `ora` nei campi nascosti; un ascoltatore in
fase di cattura ferma l'invio senza ora scelta, prima di `moduli.js`. Nessun gestore in linea (CSP a nonce).

## Pannello

`addon-pagina.php?a=prenotazioni&p=agenda|elenco|servizi|persone|impostazioni`, solo amministratore (lo controlla
`admin/addon-pagina.php`). Ogni POST con `verify_csrf()`. Le pagine usano `data-conferma` di `pannello.js`, nessun
`onclick`. Al telefono le tabelle `.pa-tabella` diventano schede (`assets/admin.css`).

## Ganci del nucleo

Nessun file del nucleo cambiato (anche nella 2.1: l'allegato usa `posta_invia(..., $allegati)` della 2.0.3). Usati: `cms_evento('sito_percorso')`, `cms_moduli_elenco('cron_spesso')`,
`editor_registra_widget()`, `editor_registra_asset()`, `ewx_orari_intervalli()`, `moduli_captcha_valido()`,
`moduli_segreto()`, `moduli_ip()`, `posta_invia()`, `moduli_email_html()`, `sito_html()`, `vista_cortesia()`.
Eventi nuovi offerti agli altri: `prenotazione_nuova`, `prenotazione_annullata`, `prenotazione_non_presentata`,
`prenotazione_svolta`, `prenotazione_eliminata` (argomento: la riga della prenotazione).

## Prove

- `prove/prova-addon-prenotazioni.js` (85 controlli nella radice; 60 con la 2.0.0): tutto il percorso, nel browser, con
  (2.1) gli allegati .ics, la prenotazione dal pannello (orari uguali al sito, senza email, forzata alle 7:15 con l'email,
  sopra un appuntamento: no) e le colonne nuove su un sito della 2.0.0.
- `prove/prenotazioni-cli.php`: posti a ore fisse (ora legale, fine mese, anticipi, ferie, pausa, doppia, promemoria).
- `prove/attacchi-addon-b.js`, gruppo A (A18, 2.1: prenotazione dal pannello senza token, sul posto di un altro,
  forzata sopra o a metà di un appuntamento, SQL injection nel servizio, a capo nell'email, ora che non esiste, codice nel
  nome) e D (la pagina `nuova` e il suo POST dal redattore).

## Cosa non c'è

- spostare dal pannello fuori dagli orari liberi (solo la prenotazione nuova si forza);
- pagamenti, caparre, servizi con più persone insieme, turni oltre la mezzanotte (si prenotano fino alle 24).
