# Addon Eventi con iscrizione (tecnico)

Cartella `cms/addon/eventi-iscrizione/`. Addon della 2.0.0 (nella 2.1 offre agli altri addon gli eventi della fine
dell'evento e delle presenze: li usa Raccolta recensioni). Spento non carica niente: le pagine `/evento/...`
rispondono 404 come ogni indirizzo che non c'è.

## File

| File | Cosa |
|---|---|
| `modulo.php` | manifesto: `installa`, `pannello` (eventi, iscritti, impostazioni), `menu` (dopo "Newsletter e form", solo admin), `widget`, `cron_spesso`, evento `sito_percorso` |
| `includes/eventi.php` | il motore: tabelle, eventi, iscrizioni, lista d'attesa, email, .ics, dati per Google, cron |
| `includes/vista.php` | il riquadro dell'evento e il modulo d'iscrizione, i messaggi d'errore |
| `includes/sito.php` | la pagina dell'evento e gli indirizzi `/evento-iscrizione/...` |
| `includes/widget.php` | i widget **Iscrizione all'evento** (`evento_iscrizione`) ed **Eventi con iscrizione** (`eventi_iscrizione`) |
| `includes/admin-*.php` | `admin-comune.php`, `admin-eventi.php`, `admin-iscritti.php`, `admin-impostazioni.php` |
| `assets/eventi.css`, `assets/admin.css` | sito e pannello |

## Tabelle

`evi_installa()` (`eventi.php:70`), `evi_schema()` (`:95`).

- `<prefisso>evi_eventi`: `slug` (UNIQUE), `titolo`, `inizio`, `inizio_ts`, `fine_ts` (0 = non detta), `luogo`,
  `indirizzo`, `descrizione` (testo semplice), `foto` (indirizzo), `prezzo` (testo), `posti` (0 = senza limite),
  `max_persona`, `chiusura_ts` (0 = all'inizio), `attesa` (lista d'attesa sì/no), `stato` (`bozza`, `pubblicato`,
  `annullato`), `svolto_at` (2.1: quando il cron ha visto l'evento finito e ha mandato `evento_svolto`).
- `<prefisso>evi_iscrizioni`: `evento_id`, `codice` (segreto del link), `nome`, `email`, `telefono`, `posti`, `stato`
  (`confermata`, `attesa`, `cancellata`), `email_attiva` colonna generata `IF(stato <> 'cancellata', email, NULL)` con
  **UNIQUE (evento_id, email_attiva)** (la stessa email una volta per evento, anche con richieste insieme), `presente`
  (NULL, 0, 1), `promemoria_at`, `entrato_at` (dalla lista d'attesa), `consenso_at`, `ip`, `creato`, `aggiornato`.

Impostazioni `evi_impostazioni` (JSON, `evi_impostazioni()` `:102`): `avviso_a`, `avvisa_iscrizioni`, `nota_email`,
`privacy`, `promemoria`.

`svolto_at` è nata dopo la 2.0.0: `evi_colonne_nuove()` (`eventi.php:81`, da `evi_installa()`) la aggiunge con
`ALTER TABLE` e segna subito svolti gli eventi già finiti, così un aggiornamento non manda inviti "Com'è andata?" per eventi
di mesi fa (provato in `prova-addon-eventi-iscrizione.js`, 7b). `evi_fine()` (`:91`): la fine scritta, o due ore dopo
l'inizio (la stessa regola del file .ics).

## Iscrizione, posti e lista d'attesa

`evi_iscrivi($evento, $dati)` (`eventi.php:245`), in `evi_transazione()` (`:226`, READ COMMITTED):

1. `SELECT ... FROM evi_eventi WHERE id = ? FOR UPDATE`: le iscrizioni allo stesso evento passano una alla volta;
2. aperte? (`evi_aperte()` `:172`: pubblicato e prima della chiusura o dell'inizio), posti fra 1 e `max_persona`;
3. la stessa email già iscritta: `gia` (e l'indice UNIQUE per le richieste insieme: un 1062 diventa `gia`);
4. posti liberi (`evi_liberi()` `:167`) contati dopo il blocco. Con chi aspetta già in fila, i nuovi vanno in fila;
   con meno posti del chiesto: `pochi:N`; pieno: `attesa` (se l'evento la vuole) oppure `pieno`.

`evi_cancella()` (`:289`) segna `cancellata` (una volta sola: `WHERE stato <> 'cancellata'`), manda l'email e chiama
`evi_promuovi()` (`:306`): sotto lo stesso blocco, chi aspetta entra in ordine di arrivo finché ci sta (ci si ferma al
primo che non ci sta: niente sorpassi), poi riceve "Si è liberato un posto". `evi_promuovi()` parte anche salvando
l'evento nel pannello (più posti). `evi_posizione()` (`:282`): il numero in fila.

## Link personali

`evi_firma()` (`:202`): HMAC-SHA256 di `evento-iscrizione|id|codice` con `moduli_segreto()`, 40 caratteri.
`evi_da_link()` (`:211`): firma con `hash_equals`, e niente link di eventi finiti da più di 30 giorni.

## Indirizzi pubblici (`includes/sito.php`)

`evi_sito()` (`sito.php:13`), evento `sito_percorso`:

| Indirizzo | Funzione | Cosa |
|---|---|---|
| `GET /evento/<slug>/` | `evi_sito_pagina()` `sito.php:41` | solo eventi pubblicati o annullati (le bozze: 404, cioè l'indirizzo passa al resto del sito); canonical, og, dati per Google |
| `POST /evento-iscrizione/invia/` | `evi_sito_invia()` `:67` | campo esca, Origin, nome, email, telefono facoltativo, posti, privacy, captcha, limite di 6 all'ora per IP (`evi_troppi()` `eventi.php:220`) |
| `GET/POST /evento-iscrizione/gestisci/` | `evi_sito_gestisci()` `:98` | pagina personale; `&cancella=1` chiede conferma; il POST vuole `i` e `t` |
| `GET /evento-iscrizione/calendario/` | `evi_ics()` `eventi.php:414` | il file .ics |

Un percorso `/evento/<slug>/` di un evento che non c'è non viene preso: risponde la mappa del sito (una pagina con quel
percorso continua a funzionare).

## Dati per Google

`evi_jsonld()` (`eventi.php:431`): `Event` con `startDate`/`endDate` ISO con lo scarto del fuso, `eventStatus`
(Scheduled o Cancelled), `eventAttendanceMode` Offline, `location` Place (nome, indirizzo), `organizer`, `url`, `image`,
`description`, `maximumAttendeeCapacity`. `json_encode` con `JSON_HEX_TAG | JSON_HEX_AMP` come il widget Eventi del nucleo.
Il widget dell'elenco li fa scrivere al widget Eventi del nucleo; il widget del modulo non li ripete.

## Email

`evi_email()` (`:353`): confermata, attesa (con la posizione), entrata, promemoria, cancellata, messaggio (Scrivi a
tutti). `evi_email_attivita()` (`:382`): nuova, attesa, cancellata, entrata. `evi_scrivi_a_tutti()` (`:393`).
Con `posta_invia()`, tipo `evento`. Il .ics è un link (vedi il documento delle prenotazioni: `posta_mime()` non fa allegati).

## Eventi per gli altri addon (2.1)

Si chiamano con `cms_evento()` dentro un `try` (un errore di chi ascolta finisce nel log e non ferma niente qui):

| Evento | Dove | Argomenti |
|---|---|---|
| `evento_svolto` | `evi_cron()` (`eventi.php:456`), una volta per evento: `svolto_at` si prende con `UPDATE ... WHERE svolto_at IS NULL`; solo eventi pubblicati, finiti da meno di 30 giorni | la riga dell'evento, gli iscritti confermati (con `presente`) |
| `iscrizione_presenza` | `admin-iscritti.php:26`, Presente o Assente | l'iscrizione aggiornata, l'evento |
| `iscrizione_cancellata` | `evi_cancella()` (`:289`), dalla persona o dall'attività | l'iscrizione, l'evento |
| `evento_cambiato` | `admin-eventi.php:58`, salvando un evento annullato o con la fine cambiata; se era svolto e la fine nuova è nel futuro (o è annullato), `svolto_at` torna NULL | l'evento |
| `evento_eliminato` | `admin-eventi.php:20` | la riga dell'evento tolto |

## Cron

`evi_cron()` (`:456`): promemoria agli iscritti confermati degli eventi che cominciano entro 24 ore, iscritti (o entrati)
più di 24 ore prima; presa con `UPDATE ... WHERE promemoria_at IS NULL`, rimessa a NULL se la spedizione fallisce.
Dalla 2.1 anche gli eventi finiti (`evento_svolto`, sopra), con i promemoria spenti o accesi. Riga del Log cron: "3
promemoria, 1 evento concluso".

## Widget

- `evento_iscrizione` (`widget.php:18`): evento (scelta con `accetta` numerico; vuoto = il prossimo pubblicato), titolo,
  riquadro sì/no, link alla pagina. Disegna `evi_modulo_html()` (`vista.php:47`).
- `eventi_iscrizione` (`widget.php:42`): prepara le voci (data, ora, titolo, luogo, descrizione corta, prezzo e posti
  rimasti, foto, link) e le fa disegnare al widget **Eventi** del nucleo (`editor_widget_registrati()['eventi']`):
  stesso aspetto (`widget.css`), stessi dati per Google.

## Pannello

`addon-pagina.php?a=eventi-iscrizione&p=eventi|iscritti|impostazioni`, solo amministratore, POST con `verify_csrf()`.
Il CSV passa ogni riga da `sicurezza_csv_riga()` (le celle che Excel leggerebbe come formula prendono un apice).

## Ganci del nucleo

Nessun file del nucleo cambiato. Usati: `cms_evento('sito_percorso')`, `cron_spesso`, `editor_registra_widget()`,
`editor_registra_asset()`, il widget Eventi registrato, `moduli_captcha_valido()`, `moduli_segreto()`, `posta_invia()`,
`moduli_email_html()`, `sito_html()`, `sicurezza_csv_riga()`.

## Prove

- `prove/prova-addon-eventi-iscrizione.js` (47 controlli con la 2.1, 45 con la 2.0.0; radice e `/test`).
- Gli eventi per Raccolta recensioni: `prove/prova-addon-recensioni.js`, sezione 7b.
- `prove/attacchi-addon-b.js`, gruppo B.

## Cosa non c'è

Pagamento online, biglietti con codice QR, eventi che si ripetono (ogni data è un evento), domande in più nel modulo.
