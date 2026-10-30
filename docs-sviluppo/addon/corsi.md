# Addon Corsi online (`corsi`)

Versione 1.0.0, con plumeCMS 2.0.0. Cartella `cms/addon/corsi/`. Manifesto con `'richiede' => ['membri']`: accendendolo si
accende anche l'Area riservata, e l'Area riservata non si spegne finché Corsi online è acceso (`includes/addon.php`).

## File

| File | Cosa |
|---|---|
| `modulo.php` | manifesto: menu Corsi (dopo Membri), tre pagine del pannello, evento `sito_percorso`, evento `membro_eliminato`, filtro `membri_area`, widget |
| `includes/corsi.php` | il motore: tabelle, corsi, moduli, lezioni, ordine, video, accesso, avanzamento, certificati |
| `includes/pagine.php` | le pagine pubbliche: elenco, programma, lezione, video, materiale, certificato, verifica |
| `includes/admin-corsi.php` | pannello: elenco dei corsi, Nuovo corso, Elimina |
| `includes/admin-corso.php` | pannello: dati del corso, programma (moduli e lezioni con le frecce), chi lo segue, assegnazioni |
| `includes/admin-lezione.php` | pannello: una lezione (testo HTML, video, materiali, durata, modulo) |
| `includes/widget.php` | il widget "Elenco corsi" |
| `assets/corsi.css`, `assets/certificato.css`, `assets/corsi.js` | stile; certificato da stampare (A4 orizzontale); pulsante Stampa senza gestori in linea |

## Tabelle

| Tabella | Colonne |
|---|---|
| `{p}corsi` | `id`, `slug` (unico), `titolo`, `descrizione`, `immagine`, `accesso` (`tutti` / `assegnati`), `stato` (`bozza` / `pubblicato`), `ordine`, `creato`, `aggiornato` |
| `{p}corsi_moduli` | `id`, `corso_id`, `titolo`, `ordine` |
| `{p}corsi_lezioni` | `id`, `corso_id`, `modulo_id`, `slug` (unico nel corso), `titolo`, `testo` (HTML ripulito), `video_url`, `video_file`, `materiali` (JSON di nomi di file riservati), `durata`, `ordine`, `creato`, `aggiornato` |
| `{p}corsi_iscritti` | `corso_id`, `membro_id`, `assegnato`, `iniziato`, `ultimo`, `ultima_lezione`, `completato` |
| `{p}corsi_progressi` | `membro_id`, `lezione_id`, `completata` |
| `{p}corsi_certificati` | `codice` (pubblico, `XXXX-XXXX-XXXX`), `corso_id`, `membro_id`, `nome`, `corso_titolo`, `data` |

Impostazioni: `corsi_schema`, `corsi_video_cartella` (nome a caso della cartella dei video in `uploads/`).

## Funzioni principali (`includes/corsi.php`)

| Funzione | Riga | Cosa |
|---|---|---|
| `corsi_installa()`, `corsi_schema()` | 27, 65 | tabelle |
| `corsi_slug()`, `corsi_slug_libero()` | 78, 121 | slug da titolo; mai `certificato`, `video`, `materiale` per una lezione |
| `corsi_tutti()`, `corsi_leggi()`, `corsi_per_slug()`, `corsi_crea()`, `corsi_salva()`, `corsi_elimina()` | 89-146 | corsi; un corso nuovo nasce in bozza con "Modulo 1"; l'immagine passa da `sicurezza_url_pulito()` |
| `corsi_moduli()`, `corsi_modulo_crea()`, `corsi_modulo_elimina()` | 157, 168, 257 | un modulo con lezioni non si elimina; un corso ha almeno un modulo |
| `corsi_lezioni()` | 176 | le lezioni nell'ordine in cui si seguono (ordine del modulo, poi della lezione) |
| `corsi_lezione_crea()`, `corsi_lezione_salva()`, `corsi_lezione_elimina()` | 189, 207, 230 | il testo passa da `sicurezza_html_pulito($html, 'articolo')` e `sito_senza_4byte()`; i materiali solo fra i file riservati di Download |
| `corsi_sposta($cosa, $id, $verso)` | 239 | le frecce del pannello: scambia con la vicina e rinumera |
| `corsi_video_embed($url)` | 271 | YouTube (watch, youtu.be, embed, shorts, live; id di 11 caratteri) diventa `https://www.youtube-nocookie.com/embed/<id>`; Vimeo `https://player.vimeo.com/video/<n>`. Niente altro (niente porte, credenziali, `javascript:`) |
| `corsi_video_dir()`, `corsi_video_ok()`, `corsi_video_carica()`, `corsi_video_togli()` | 291-327 | video .mp4/.webm controllati dai primi byte (`ftyp`, EBML), in `uploads/corsi-<16 hex>/` con `.htaccess` che chiude tutto |
| `corsi_manda_video($p)` | 335 | manda il video con `Range` (206, 416), `private, no-store` |
| `corsi_puo($corso, $membro)` | 369 | corso pubblicato, membro attivo, e per `assegnati` la riga `assegnato = 1` |
| `corsi_assegna()`, `corsi_iscritti()` | 377, 384 | assegnazioni; iscritti con lezioni fatte e codice del certificato |
| `corsi_completate()`, `corsi_avanzamento()`, `corsi_segna_visita()`, `corsi_completa()` | 395-426 | avanzamento; "riprendi da dove eri": la prima lezione non fatta dopo l'ultima vista |
| `corsi_codice_nuovo()`, `corsi_certificato_crea()`, `corsi_certificato_di()`, `corsi_certificato_per_codice()` | 440-469 | il certificato nasce quando l'ultima lezione è fatta; codice di 12 caratteri da 32 (60 bit), nome e titolo del giorno |
| `corsi_dimentica_membro()` | 479 | evento `membro_eliminato`: via avanzamento, assegnazioni e certificati |

## Pagine pubbliche (`includes/pagine.php`)

`corsi_pagina()` (`pagine.php:41`) dall'evento `sito_percorso` per i percorsi `/corsi/...` e `/certificato/...`. I POST
usano il token dell'Area riservata (`membri_post_ok()`). Pagine `noindex`, `private, no-store`.

| Indirizzo | Funzione | Chi |
|---|---|---|
| `/corsi/` | `corsi_pagina_elenco()` (72) | tutti; schede con avanzamento per il membro |
| `/corsi/<corso>/` | `corsi_pagina_corso()` (98) | tutti vedono il programma; i link alle lezioni solo chi può seguire il corso |
| `/corsi/<corso>/<lezione>/` | `corsi_pagina_lezione()` (153) | chi può seguire il corso (altrimenti accesso o 403); POST `azione=completa` o `da-fare` |
| `/corsi/<corso>/video/<id>/` | `corsi_pagina_video()` (204) | come la lezione; l'id deve essere di una lezione di quel corso |
| `/corsi/<corso>/materiale/<id>/<file>/` | `corsi_pagina_materiale()` (213) | come la lezione; il file deve essere fra i materiali della lezione e riservato |
| `/corsi/<corso>/certificato/` | `corsi_pagina_certificato()` (233) | il membro, solo il suo, a corso finito |
| `/certificato/` e `/certificato/<codice>/` | in `corsi_pagina()` e `corsi_certificato_pagina()` (241) | chiunque abbia il codice (verifica pubblica) |

Anteprima: con `?preview=1` e la sessione del pannello (`SITO_ANTEPRIMA`) l'amministratore vede anche i corsi in bozza e le
lezioni, senza avanzamento (`corsi_anteprima()`, `pagine.php:32`).

L'area riservata del membro ha la sezione "I miei corsi" (`corsi_sezione_area()`, `pagine.php:270`, filtro `membri_area`).

## Ganci del nucleo

Nessuno in più: usa `sito_percorso` del nucleo e i ganci dell'Area riservata (`membri_area`, `membro_eliminato`). La CSP
del sito ammette già `www.youtube-nocookie.com` e `player.vimeo.com` in `frame-src` (`includes/sicurezza.php`).

## Limiti noti

- Il testo della lezione si scrive in HTML in un'area di testo (ripulito al salvataggio), non con l'editor visuale.
- Il riordino è con le frecce (un posto alla volta), non con il trascinamento.
- Il video caricato pesa quanto permette il server (`upload_max_filesize`); per video lunghi meglio YouTube o Vimeo.
  Su Nginx la cartella dei video è protetta solo dal nome a caso (come i file riservati di Download).
- Il video incorporato di YouTube o Vimeo è visibile a chi ha il suo indirizzo su quei servizi: il controllo dell'accesso
  vale per la pagina della lezione, non per il video su YouTube (per video privati: video caricato, o Vimeo con dominio limitato).
- Il certificato ha il nome del membro del giorno in cui l'ha finito; cancellando l'account il certificato non vale più.

## Prove

- `prove/prova-addon-corsi.js` (sito Attività nuovo, posta finta, ffmpeg per il video): 58 su 58 in radice.
- `prove/attacchi-addon-a.js`: lezioni, video e materiali di un corso non proprio, path traversal, certificato di un altro, XSS nei titoli.
