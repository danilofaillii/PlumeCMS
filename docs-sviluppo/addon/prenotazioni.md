# Addon Prenotazioni e appuntamenti (tecnico)

Cartella `cms/addon/prenotazioni/`. Addon della 2.0.0 (manifesto come i moduli, `docs-sviluppo/cms-moduli.md`, sezione
"Gli addon"). Spento non carica niente: nessuna tabella letta, nessun widget, nessun indirizzo pubblico.

## File

| File | Cosa |
|---|---|
| `modulo.php` | manifesto: `installa`, `pannello` (5 pagine), `menu` (dopo "Newsletter e form", solo admin), `widget`, `cron_spesso`, evento `sito_percorso` |
| `includes/prenotazioni.php` | il motore: tabelle, impostazioni, posti liberi, prenota/sposta/annulla, email, .ics, cron |
| `includes/vista.php` | l'HTML del calendario e del modulo (widget e pagina "sposta"), i messaggi d'errore |
| `includes/sito.php` | gli indirizzi pubblici `/prenotazione/...` |
| `includes/widget.php` | il widget **Prenota** (`prenota`, gruppo Dal sito) |
| `includes/admin-*.php` | le pagine del pannello: `admin-comune.php` (schede, chiusure), `admin-agenda.php`, `admin-elenco.php`, `admin-servizi.php`, `admin-persone.php`, `admin-impostazioni.php` |
| `assets/prenota.js`, `assets/prenota.css` | il calendario nel browser; `assets/admin.css` le pagine del pannello |

## Tabelle

Si creano accendendo l'addon (`pren_installa()`, `prenotazioni.php:94`) e, dopo un aggiornamento che le cambia, alla
prima richiesta (`pren_schema()`, `prenotazioni.php:100`, impronta in `pren_schema_impronta`). Tutte `utf8` a 3 byte,
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
  `messaggio` (dell'attività, annullando), `promemoria_at`, `consenso_at`, `ip`, `creato`, `aggiornato`.

Impostazioni: `pren_impostazioni` (JSON, `pren_impostazioni()` `prenotazioni.php:111`, predefiniti in `PREN_PREDEFINITE`
`prenotazioni.php:23`): `anticipo_ore`, `anticipo_giorni`, `passo`, `limite_ore`, `da_confermare`, `scegli_persona`,
`avviso_a`, `promemoria`, `chiusure`, `indirizzo`, `nota_email`, `privacy`.

## Posti liberi

`pren_posti($servizio, $risorsa, $giorno, $adesso, $escludi)` (`prenotazioni.php:232`): per ogni persona che fa il
servizio (`pren_risorse_servizio()` `:180`), fuori da ferie e chiusure (`pren_chiuso()` `:197`), gli intervalli del giorno
letti con **la funzione del widget Orari di apertura** (`pren_intervalli()` `:203` chiama `ewx_orari_intervalli()` di
`includes/editor-widget-extra-d.php`), a passi di `passo` minuti. Un orario si offre se:

- esiste quel giorno: `setTime()` dell'ora scritta deve ridare la stessa ora (l'ultima domenica di marzo le 2:00 e le
  2:30 non esistono e si saltano);
- inizio + durata (in secondi veri) sta dentro l'intervallo: nell'ultima domenica di ottobre la notte ha un'ora in più e
  i conti tornano;
- è fra `adesso + anticipo_ore` e `adesso + anticipo_giorni`;
- `[inizio, inizio + durata + pausa)` non tocca nessun `[inizio_ts, blocco_ts)` di una prenotazione attiva della persona
  (`pren_occupati()` `:217`), tranne `$escludi` (quella che si sposta).

Ritorna `'H:MM' => [id delle persone libere]`. `pren_giorni()` (`:272`) conta i posti di N giorni (fine mese, fine anno,
29 febbraio: `prove/prenotazioni-cli.php`). Tutto nel fuso del sito, `date_default_timezone_get()` (`CMS_FUSO`, di
solito Europe/Rome). `$adesso` esiste per le prove a ore fisse.

## Niente doppie prenotazioni

`pren_prenota()` (`:332`) e `pren_sposta()` (`:375`) lavorano in `pren_transazione()` (`:312`, READ COMMITTED):
`pren_blocca()` (`:305`) prende con `SELECT ... FOR UPDATE` le righe delle persone del servizio in ordine di id (niente
incroci), poi si ricalcolano i posti e si scrive. La seconda richiesta aspetta la prima e vede il posto preso. In più
l'indice UNIQUE su `slot_attivo` ferma lo stesso inizio due volte anche fuori da queste funzioni; un 1062 diventa
l'errore `posto`. Provato con otto richieste HTTP in parallelo sullo stesso posto (`prove/attacchi-addon-b.js`, A1).

## Stati e azioni

- nuova: `confermata`, oppure `da_confermare` con l'impostazione; evento `prenotazione_nuova`;
- `pren_modificabile()` (`:370`): dal link del cliente si annulla o sposta fino a `limite_ore` prima;
- `pren_annulla($p, 'cliente'|'attivita', $messaggio)` (`:405`): evento `prenotazione_annullata`;
- `pren_cambia_stato()` (`:418`): conferma (manda l'email), `non_presentato` (solo dopo l'inizio; evento
  `prenotazione_non_presentata`), ritorno a `svolta`;
- il cron rende `svolta` le confermate finite (evento `prenotazione_svolta`);
- eliminare i dati dal pannello: evento `prenotazione_eliminata`.

Gli eventi sono il gancio per altri addon (Raccolta recensioni li ascolta) senza dipendenze: chi non li ascolta non se
ne accorge.

## Link personali

`pren_firma($id, $codice)` (`:125`): HMAC-SHA256 di `prenotazione|id|codice` con `moduli_segreto()` (lo stesso segreto
del captcha), 40 caratteri. `pren_da_link()` (`:130`) vuole id numerico, firma di 40 hex, `hash_equals`, e rifiuta i
link di appuntamenti finiti da più di `PREN_GIORNI_LINK` (30) giorni. `pren_url('gestisci'|'calendario', $p)` (`:138`).

## Indirizzi pubblici (`includes/sito.php`)

Evento `sito_percorso` (dentro `sito/index.php`, dopo i redirect): `pren_sito()` (`sito.php:13`) risponde solo a:

| Indirizzo | Funzione | Cosa |
|---|---|---|
| `GET /prenotazione/posti/?s=&r=&d=&da=[&p=&t=]` | `pren_sito_posti()` `sito.php:42` | JSON: 14 giorni da `da` con i posti, gli orari del giorno `d` (o del primo libero), le persone del servizio, il primo giorno libero più avanti |
| `POST /prenotazione/invia/` | `pren_sito_invia()` `:86` | campo esca, Origin (`pren_stessa_origine()` `:28`), nome, email, telefono, privacy, captcha (`moduli_captcha_valido()`), limite di 6 all'ora per IP (`pren_troppi()`), poi `pren_prenota()`; errore: 303 alla pagina con `?modulo=pren-<codice>` (moduli.js rimette i campi), riuscita: 303 alla pagina personale |
| `GET/POST /prenotazione/gestisci/` | `pren_sito_gestisci()` `:124` | la pagina personale; `&sposta=1` il calendario per spostare, `&annulla=1` la conferma; il POST vuole `p` e `t` nel modulo |
| `GET /prenotazione/calendario/` | `pren_ics()` `prenotazioni.php:522` | il file .ics (UTC, righe piegate a 75 byte, testo protetto) |

Le pagine escono con `sito_html()`, `robots noindex`, il foglio `assets/prenota.css` passato nella testa.

## Email

Con le funzioni del nucleo, senza toccarle: `posta_invia()`, `moduli_email_html()`, `moduli_intestazioni()`.
`pren_email_cliente()` (`:456`): ricevuta, confermata, spostata, promemoria, annullata (con il messaggio dell'attività).
`pren_email_attivita()` (`:495`): nuova, annullata, spostata, all'indirizzo `avviso_a` o a `posta_notifica_email()`, con
`Reply-To` del cliente. Oggetti passati da `pren_riga()` (`:444`, niente a capo). Tipo nel registro della posta:
`prenotazione`.

**Il file .ics è un link nell'email, non un allegato**: `posta_mime()` di `includes/posta.php` (che questo lavoro non
poteva toccare) sa fare solo testo e HTML. Per l'allegato vero servirebbe una parte `multipart/mixed` in `posta_mime()`.

## Cron

`cron_spesso` => `pren_cron()` (`:546`), nel giro frequente (`cron_newsletter()`, anche da Log cron > Esegui adesso):

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

Nessun file del nucleo cambiato. Usati: `cms_evento('sito_percorso')`, `cms_moduli_elenco('cron_spesso')`,
`editor_registra_widget()`, `editor_registra_asset()`, `ewx_orari_intervalli()`, `moduli_captcha_valido()`,
`moduli_segreto()`, `moduli_ip()`, `posta_invia()`, `moduli_email_html()`, `sito_html()`, `vista_cortesia()`.
Eventi nuovi offerti agli altri: `prenotazione_nuova`, `prenotazione_annullata`, `prenotazione_non_presentata`,
`prenotazione_svolta`, `prenotazione_eliminata` (argomento: la riga della prenotazione).

## Prove

- `prove/prova-addon-prenotazioni.js` (59 controlli nella radice, 60 in `/test`): tutto il percorso, nel browser.
- `prove/prenotazioni-cli.php`: posti a ore fisse (ora legale, fine mese, anticipi, ferie, pausa, doppia, promemoria).
- `prove/attacchi-addon-b.js`, gruppo A.

## Cosa non c'è

- prenotazioni scritte a mano dal pannello (una telefonata): oggi solo dal sito;
- il file .ics allegato (vedi Email);
- pagamenti, caparre, servizi con più persone insieme, turni oltre la mezzanotte (si prenotano fino alle 24).
