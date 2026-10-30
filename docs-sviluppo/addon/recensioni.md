# Addon Raccolta recensioni (tecnico)

Cartella `cms/addon/recensioni/`. Addon della 2.0.0. Non richiede l'addon Prenotazioni: se è acceso ne ascolta gli
eventi, se è spento quegli eventi non arrivano e gli inviti si fanno solo a mano.

## File

| File | Cosa |
|---|---|
| `modulo.php` | manifesto: `installa`, `pannello` (recensioni, inviti, impostazioni), `menu`, `widget`, `cron_spesso`, eventi `sito_percorso`, `prenotazione_svolta`, `prenotazione_non_presentata`, `prenotazione_annullata`, `prenotazione_eliminata` |
| `includes/recensioni.php` | il motore: tabelle, inviti, link, recensioni, esclusi, email, cron |
| `includes/sito.php` | il modulo con le stelle e "Non scrivermi più" |
| `includes/widget.php` | il widget **Recensioni dal sito** (`recensioni_sito`, gruppo Marketing) |
| `includes/admin-*.php` | `admin-comune.php`, `admin-recensioni.php`, `admin-inviti.php`, `admin-impostazioni.php` |
| `assets/recensioni.css`, `assets/admin.css` | sito e pannello |

## Tabelle

`rec_installa()` (`recensioni.php:65`), `rec_schema()` (`:71`).

- `<prefisso>rec_inviti`: `codice` (segreto del link), `email`, `nome`, `origine` (`prenotazione`, `manuale`), `rif`
  (UNIQUE, `pren:<id>`; NULL a mano), `servizio`, `invia_dopo`, `inviato_at`, `scade_at`, `usato_at`, `annullato`,
  `tentativi`, `creato`.
- `<prefisso>rec_recensioni`: `invito_id` (**UNIQUE**: una recensione per invito), `nome` (da mostrare), `stelle` (1-5),
  `testo`, `servizio`, `stato` (`da_approvare`, `approvata`, `rifiutata`), `risposta`, `risposta_at`, `ip`, `creato`,
  `aggiornato`.
- `<prefisso>rec_esclusi`: `impronta` (sha256 dell'email in minuscolo) di chi ha chiesto "Non scrivermi più". Non si
  tiene l'indirizzo in chiaro.

Impostazioni `rec_impostazioni` (JSON, `rec_impostazioni()` `:78`, `REC_PREDEFINITE` `:14`): `giorni_dopo`, `validita`,
`da_prenotazioni`, `google_link`, `google_da`, `avviso_a`, `avvisa`, `oggetto`, `testo`.

## Inviti

`rec_invito_crea()` (`:119`) non crea niente per un indirizzo non valido, escluso, già invitato con un invito ancora
aperto, o per una prenotazione già invitata (`rif` UNIQUE). Da dove arrivano:

- `rec_da_prenotazione()` (`:137`), sull'evento `prenotazione_svolta` dell'addon Prenotazioni: parte `giorni_dopo` giorni
  dopo la fine dell'appuntamento;
- `rec_annulla_rif()` (`:144`), sugli eventi `prenotazione_non_presentata` e `prenotazione_annullata` (l'invito non
  ancora partito si annulla) e `prenotazione_eliminata` (si toglie la riga);
- a mano, `admin-inviti.php` (`rec_admin_leggi_elenco()` `admin-inviti.php:11`: "email", "Nome <email>", "Nome; email"),
  al massimo 200 alla volta e solo con la spunta "sono miei clienti e mi hanno dato l'indirizzo".

`rec_cron()` (`:276`, `cron_spesso`, e il pulsante "Manda adesso"): fino a 30 inviti arrivati alla loro ora; ognuno si
prende con `UPDATE ... SET inviato_at = ?, scade_at = ? WHERE id = ? AND inviato_at IS NULL` prima di spedire (mai due
volte). Se la spedizione fallisce torna in coda; dopo cinque tentativi si annulla.

## L'email e i link

`rec_email_invito()` (`:243`): cinque stelle che sono cinque link (`?s=1..5`: il modulo si apre con il voto scelto),
il pulsante, in fondo "Non scrivermi più" e l'intestazione `List-Unsubscribe`. `rec_firma($id, $codice, $scopo)`
(`:157`): HMAC-SHA256 di `scopo|id|codice` con `moduli_segreto()`; gli scopi `recensione` ed `esci` hanno firme diverse
(una non apre l'altra). `rec_da_link()` (`:169`): firma con `hash_equals`; invito mai partito o annullato: null (404);
già usato: `usato`; oltre `scade_at`: `scaduto` (410).

## Una recensione per invito

`rec_salva()` (`:184`): in una transazione, `UPDATE rec_inviti SET usato_at = ? WHERE id = ? AND usato_at IS NULL AND
annullato = 0 AND scade_at > ?`; solo con una riga presa si scrive la recensione, e `invito_id` UNIQUE ferma comunque la
seconda. Provato con cinque POST in parallelo sullo stesso link (`attacchi-addon-b.js`, C1). Avviso all'attività con
`rec_avvisa_attivita()` (`:259`).

## Indirizzi pubblici (`includes/sito.php`)

| Indirizzo | Funzione | Cosa |
|---|---|---|
| `GET /recensione/?i=&t=[&s=]` | `rec_sito_modulo()` `sito.php:43` | il modulo: stelle (radio, cinque), testo, nome da mostrare (proposto "Giulia R.", `rec_nome_breve()` `recensioni.php:105`), consenso alla pubblicazione |
| `POST /recensione/` | idem | stelle 1-5 (`/^[1-5]$/`), testo di almeno 5 caratteri, nome, consenso, Origin; errori: 422 con il modulo; poi "Grazie!" e, da `google_da` stelle in su con un link `https://`, la proposta di Google (`rel=noopener`) |
| `GET/POST /recensione/esci/?i=&t=` | `rec_sito_esci()` `:87` | "Non scrivermi più" con un pulsante; il POST mette l'impronta in `rec_esclusi` e annulla gli inviti in coda (`rec_escludi()` `recensioni.php:206`) |

## Widget Recensioni dal sito

`includes/widget.php:9`. Le approvate (`rec_approvate()` `recensioni.php:224`: le ultime N da M stelle in su, più media e
numero di tutte le approvate) con **lo stesso aspetto del widget Recensioni** del nucleo: classi `ew-rec-*`,
`widget.css`, le stelle del riepilogo da `ewx_stelle_media()`, le colonne con `ewx_colonne()`/`ewx_css_colonne()`;
sotto ogni recensione la risposta (`.rec-risposta`). Testi con `editor_paragrafi()` (escape). Nessun dato per Google
sulle stelle, come il widget Recensioni: Google non mostra le stelle delle recensioni raccolte sul proprio sito.

Scelta fatta: un widget nuovo invece di un'opzione nel widget Recensioni, così il nucleo non cambia e con l'addon
spento il widget Recensioni è quello di prima, identico.

## Pannello

`addon-pagina.php?a=recensioni&p=recensioni|inviti|impostazioni`, solo amministratore, POST con `verify_csrf()`:
approva, rifiuta, rispondi, elimina; inviti a mano, Manda adesso, annulla, elimina.

## Consenso e cancellazione

- inviti a mano solo con la spunta dell'attività; dopo un appuntamento solo a chi ha prenotato (con il consenso della
  prenotazione) e solo se l'impostazione è accesa;
- "Non scrivermi più" in ogni email e in `List-Unsubscribe`;
- la recensione esce solo con il consenso alla pubblicazione e con il nome scelto da chi la scrive;
- Elimina nel pannello (recensione o invito); i dati di una prenotazione eliminati tolgono anche il suo invito.

## Ganci del nucleo

Nessun file del nucleo cambiato. Usati: `cms_evento()` (eventi `sito_percorso` e quelli dell'addon Prenotazioni),
`cron_spesso`, `editor_registra_widget()`, `editor_registra_asset()`, `ewx_stelle_media()`, `ewx_colonne()`,
`ewx_css_colonne()`, `editor_paragrafi()`, `icona_svg()`, `moduli_segreto()`, `posta_invia()`, `moduli_email_html()`,
`sito_html()`.

## Prove

- `prove/prova-addon-recensioni.js` (radice e `/test`).
- `prove/attacchi-addon-b.js`, gruppo C.

## Cosa non c'è

Foto nelle recensioni, recensioni dopo gli eventi dell'addon Eventi con iscrizione (il gancio si aggiunge allo stesso
modo), importazione delle recensioni da Google.
