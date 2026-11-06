# Addon Raccolta recensioni (tecnico)

Cartella `cms/addon/recensioni/`. Addon della 2.0.0, versione 1.1.0 con la 2.1 (inviti anche dopo gli eventi). Non
richiede l'addon Prenotazioni né l'addon Eventi con iscrizione: se sono accesi ne ascolta gli eventi, se sono spenti quegli
eventi non arrivano e gli inviti si fanno solo a mano.

## File

| File | Cosa |
|---|---|
| `modulo.php` | manifesto: `installa`, `pannello` (recensioni, inviti, impostazioni), `menu`, `widget`, `cron_spesso`, eventi `sito_percorso`, `prenotazione_svolta`, `prenotazione_non_presentata`, `prenotazione_annullata`, `prenotazione_eliminata` e (2.1) `evento_svolto`, `iscrizione_presenza`, `iscrizione_cancellata`, `evento_cambiato`, `evento_eliminato` |
| `includes/recensioni.php` | il motore: tabelle, inviti, link, recensioni, esclusi, email, cron |
| `includes/sito.php` | il modulo con le stelle e "Non scrivermi più" |
| `includes/widget.php` | il widget **Recensioni dal sito** (`recensioni_sito`, gruppo Marketing) |
| `includes/admin-*.php` | `admin-comune.php`, `admin-recensioni.php`, `admin-inviti.php`, `admin-impostazioni.php` |
| `assets/recensioni.css`, `assets/admin.css` | sito e pannello |

## Tabelle

`rec_installa()` (`recensioni.php:65`), `rec_schema()` (`:71`).

- `<prefisso>rec_inviti`: `codice` (segreto del link), `email`, `nome`, `origine` (`prenotazione`, `evento` dalla 2.1,
  `manuale`), `rif` (UNIQUE, `pren:<id>` o `evi:<evento>:<iscrizione>`; NULL a mano), `servizio`, `invia_dopo`, `inviato_at`, `scade_at`, `usato_at`, `annullato`,
  `tentativi`, `creato`.
- `<prefisso>rec_recensioni`: `invito_id` (**UNIQUE**: una recensione per invito), `nome` (da mostrare), `stelle` (1-5),
  `testo`, `servizio`, `stato` (`da_approvare`, `approvata`, `rifiutata`), `risposta`, `risposta_at`, `ip`, `creato`,
  `aggiornato`.
- `<prefisso>rec_esclusi`: `impronta` (sha256 dell'email in minuscolo) di chi ha chiesto "Non scrivermi più". Non si
  tiene l'indirizzo in chiaro.

Impostazioni `rec_impostazioni` (JSON, `rec_impostazioni()` `:78`, `REC_PREDEFINITE` `:14`): `giorni_dopo`, `validita`,
`da_prenotazioni`, `da_eventi` (2.1, acceso), `eventi_chi` (2.1: `presenti` o `tutti`), `google_link`, `google_da`,
`avviso_a`, `avvisa`, `oggetto`, `testo`. Le impostazioni salvate con la 2.0.0 prendono i due nuovi dai predefiniti.

## Inviti

`rec_invito_crea()` (`:119`) non crea niente per un indirizzo non valido, escluso, già invitato con un invito ancora
aperto, o per una prenotazione già invitata (`rif` UNIQUE). Da dove arrivano:

- `rec_da_prenotazione()` (`:137`), sull'evento `prenotazione_svolta` dell'addon Prenotazioni: parte `giorni_dopo` giorni
  dopo la fine dell'appuntamento;
- `rec_annulla_rif()` (`:209`), sugli eventi `prenotazione_non_presentata` e `prenotazione_annullata` (l'invito non
  ancora partito si annulla) e `prenotazione_eliminata` (si toglie la riga);
- (2.1) dopo un evento dell'addon Eventi con iscrizione: vedi sotto;
- a mano, `admin-inviti.php` (`rec_admin_leggi_elenco()` `admin-inviti.php:11`: "email", "Nome <email>", "Nome; email"),
  al massimo 200 alla volta e solo con la spunta "sono miei clienti e mi hanno dato l'indirizzo".

`rec_cron()` (`:341`, `cron_spesso`, e il pulsante "Manda adesso"): fino a 30 inviti arrivati alla loro ora; ognuno si
prende con `UPDATE ... SET inviato_at = ?, scade_at = ? WHERE id = ? AND inviato_at IS NULL` prima di spedire (mai due
volte). Se la spedizione fallisce torna in coda; dopo cinque tentativi si annulla.

## Dopo gli eventi (2.1)

L'addon Eventi con iscrizione offre i suoi eventi (`docs-sviluppo/addon/eventi-iscrizione.md`); qui si ascoltano nel
manifesto, senza dipendenze. Riferimento di un invito: `rec_rif_evento()` (`recensioni.php:148`), `evi:<evento>:<iscrizione>`.
La fine dell'evento: `rec_fine_evento()` (`:153`), la stessa regola dell'addon Eventi (la fine scritta, o due ore dopo
l'inizio). Impostazione "Dopo un evento, a chi" (`eventi_chi`):

- `presenti`: solo chi è segnato **Presente** in Eventi > Iscritti;
- `tutti`: tutti gli iscritti confermati tranne chi è segnato **Assente** (`rec_invita_iscritto()`, `:171`).

| Evento | Funzione | Cosa |
|---|---|---|
| `evento_svolto($evento, $iscritti)` | `rec_da_evento()` `:178` | per ogni iscritto confermato da invitare, un invito che parte `giorni_dopo` giorni dopo la fine |
| `iscrizione_presenza($iscrizione, $evento)` | `rec_da_presenza()` `:191` | solo a evento finito (`svolto_at`): presente (con `presenti`) prepara l'invito, alla data di prima o subito se è già passata; assente annulla quello non partito |
| `iscrizione_cancellata($iscrizione, $evento)` | `rec_annulla_rif()` `:209` | l'invito non partito si annulla |
| `evento_cambiato($evento)` | `rec_annulla_evento()` `:200` | evento annullato o rinviato: gli inviti non partiti dei suoi iscritti si annullano |
| `evento_eliminato($evento)` | `rec_annulla_evento(..., true)` | le righe non usate si tolgono |

`rec_invito_evento()` (`:161`): un invito nuovo con `rec_invito_crea()`; se la riga di quell'iscrizione c'è già, annullata
e mai partita (presenza corretta, evento rinviato e poi svolto), torna attiva con la data nuova invece di fermarsi
sull'indice UNIQUE di `rif`. Sempre uno solo per iscrizione. Le regole di `rec_invito_crea()` valgono anche qui: indirizzo
valido, non escluso, nessun invito ancora aperto per la stessa email.

## L'email e i link

`rec_email_invito()` (`:308`): cinque stelle che sono cinque link (`?s=1..5`: il modulo si apre con il voto scelto),
il pulsante, in fondo "Non scrivermi più" e l'intestazione `List-Unsubscribe`. `rec_firma($id, $codice, $scopo)`
(`:157`): HMAC-SHA256 di `scopo|id|codice` con `moduli_segreto()`; gli scopi `recensione` ed `esci` hanno firme diverse
(una non apre l'altra). `rec_da_link()` (`:234`): firma con `hash_equals`; invito mai partito o annullato: null (404);
già usato: `usato`; oltre `scade_at`: `scaduto` (410).

## Una recensione per invito

`rec_salva()` (`:249`): in una transazione, `UPDATE rec_inviti SET usato_at = ? WHERE id = ? AND usato_at IS NULL AND
annullato = 0 AND scade_at > ?`; solo con una riga presa si scrive la recensione, e `invito_id` UNIQUE ferma comunque la
seconda. Provato con cinque POST in parallelo sullo stesso link (`attacchi-addon-b.js`, C1). Avviso all'attività con
`rec_avvisa_attivita()` (`:324`).

## Indirizzi pubblici (`includes/sito.php`)

| Indirizzo | Funzione | Cosa |
|---|---|---|
| `GET /recensione/?i=&t=[&s=]` | `rec_sito_modulo()` `sito.php:43` | il modulo: stelle (radio, cinque), testo, nome da mostrare (proposto "Giulia R.", `rec_nome_breve()` `recensioni.php:105`), consenso alla pubblicazione |
| `POST /recensione/` | idem | stelle 1-5 (`/^[1-5]$/`), testo di almeno 5 caratteri, nome, consenso, Origin; errori: 422 con il modulo; poi "Grazie!" e, da `google_da` stelle in su con un link `https://`, la proposta di Google (`rel=noopener`) |
| `GET/POST /recensione/esci/?i=&t=` | `rec_sito_esci()` `:87` | "Non scrivermi più" con un pulsante; il POST mette l'impronta in `rec_esclusi` e annulla gli inviti in coda (`rec_escludi()` `recensioni.php:271`) |

## Widget Recensioni dal sito

`includes/widget.php:9`. Le approvate (`rec_approvate()` `recensioni.php:289`: le ultime N da M stelle in su, più media e
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

- `prove/prova-addon-recensioni.js` (radice e `/test`; 51 controlli con la 2.1, 39 con la 2.0.0): con l'addon Eventi
  acceso, «solo i presenti» (presente sì, non segnato no, assente no, cancellato no), presenze segnate dopo l'evento,
  assente e poi di nuovo presente (un invito solo), email partite tre giorni dopo con il titolo dell'evento, «a tutti gli
  iscritti», cancellazione che annulla l'invito.
- `prove/attacchi-addon-b.js`, gruppo C.

## Cosa non c'è

Foto nelle recensioni, importazione delle recensioni da Google, un giorno diverso per gli eventi e per gli appuntamenti
(`giorni_dopo` vale per tutti e due).
