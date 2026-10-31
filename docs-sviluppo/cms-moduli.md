# I moduli del CMS

(`docs/moduli.md` racconta i moduli da compilare del sito, newsletter e form, cioè `includes/moduli.php`: altra cosa.)

Un modulo è una cartella `moduli/<nome>/` con un file `modulo.php` che **restituisce un array**, il manifesto. Il nucleo lo
legge a ogni richiesta (`cms_moduli()`) prima del database: nel manifesto niente query, solo dichiarazioni e funzioni anonime
che caricano i file veri quando servono (`require_once __DIR__ . '/includes/ricette.php'`). Un file `DISATTIVO` nella cartella spegne il modulo.

## Le chiavi del manifesto

| Chiave | Forma | Chi la usa |
|---|---|---|
| `etichetta`, `versione`, `descrizione` | testo | pagina Informazioni |
| `impostazioni` | `['chiave' => valore]` | `cms_impostazione()` come fallback. `uploads_dir` e `uploads_url` spostano le immagini |
| `tipi_contenuto` | `['ricette' => ['tabella' => 'ricette', 'uno' => 'ricetta', 'tanti' => 'ricette', 'Uno', 'Tanti', 'nuovo' => 'Nuova ricetta', 'campo_contenuto' => 'content', 'tassonomie' => true, 'attributi' => false, 'kw' => false, 'citazione' => false, 'lettura' => true, 'genere' => 'f', 'icona' => '&#127858;']]` | `contenuti_tipi()`: elenco, scheda, editor, modelli ("Tutte le ricette"), menu di navigazione; da soli anche titoli e indicizzazione per tipo della SEO (`seo_tipi_moduli()`) e il widget Griglia di contenuti |
| `menu` | voci `['nome', 'url', 'icona', 'attivo' => [file che la accendono], 'attivo_tipo' => '?tipo=', 'sotto' => [...], 'admin' => bool, 'dopo' => 'Voce del nucleo' oppure 'gruppo' => 'Gruppo del nucleo' (+ 'dopo' dentro il gruppo)]` | `cms_menu_integra()` nel menu del pannello |
| `pannello` | `['nome-pagina' => '/percorso/file.php']` | lo shim `admin/<nome-pagina>.php` fa `cms_pagina_modulo('<modulo>', '<nome-pagina>')` |
| `api` | come `pannello`, per `api/*.php` | idem |
| `installa` | funzione che crea le tabelle del modulo | installatore, `cms_moduli_installa()` |
| `schema` | `['nome' => funzione che torna righe di log]` | pannello Aggiornamenti, dopo lo snapshot |
| `cron` | `['nome del passo' => funzione]` | `cron-daily.php`, un passo per voce, errori nel log |
| `azioni` | `['nome-azione' => callable]` | pacchetti (`agg_azioni()`) |
| `widget` | elenco di file PHP con funzioni `ew_*` | `editor-controlli.php` li carica dopo quelli del nucleo |
| `filtri` | `['nome' => fn($valore, ...$arg) => $valore]` | `cms_filtro('nome', $valore, ...$arg)`, in catena fra i moduli |
| `eventi` | `['nome' => fn(...$arg) => void]` | `cms_evento('nome', ...$arg)` |
| `oauth_ritorno` (2.1, solo addon) | `fn(array $q): never` | `admin/oauth-ritorno.php`, quando lo `state` comincia con il nome dell'addon e un punto |

## I percorsi dentro un modulo

Dai file in `moduli/<nome>/includes/`, `dirname(__DIR__, 3)` è la cartella del CMS. Una pagina del pannello comincia con
`require_once dirname(__DIR__, 3) . '/includes/cms.php';` e poi `require_once CMS_ADMIN . '/includes/auth.php';`.

## Filtri ed eventi che il nucleo offre oggi

| Nome | Dove | Cosa |
|---|---|---|
| `schema_colonne($colonne)` | `sito_schema_aggiorna` | colonne da garantire, anche sulle tabelle dei tipi dei moduli |
| `sito_contenuti($tutti, $daRedirect)` | `sito-dati.php` | contenuti pubblici in più, nello stesso formato |
| `sito_html_contenuto(null, $c)` | `sito_html_contenuto` | l'HTML di un tipo di un modulo |
| `categorie_noindex([])` | `sito.php` | categorie i cui archivi e contenuti sono noindex |
| `importa_contenuto(null, $j, $riga, $categoria)` | `sito_importa_articolo` | un modulo si prende un contenuto di un pacchetto |
| `sito_mappa($m, $attivi)` | `sito-mappa.php` | cambia il genere di una voce o ne aggiunge |
| `vista_home(null)`, `testata_fissa(null, $menu, $menuMobile)`, `sito_menu([...])`, `blog_filtri([])` | `viste.php` | home scritta nel codice, header e footer fissi, menu fisso, filtri del blog |
| `servizi_predefiniti([])`, `jsonld_about([])`, `seo_predefiniti($cfg)` | `sito-seo.php`, `seo.php` | pagine servizio, about della home, organizzazione e persona del JSON-LD |
| `llms_intestazione($righe, $tuttiConTldr)`, `llms_servizi([])` | `extra.php` | llms.txt |
| `design_predefiniti($p)` | `design.php` | colori, font e identità di partenza |
| `editor_albero_home(null)`, `editor_albero_articolo(null, $a)` | `editor.php` | alberi di partenza nell'editor |
| `modelli_partenza([...])`, `menu_partenza([...])`, `menu_scelte_speciali([...])` | `modelli.php`, `menu-nav.php` | header, footer e menu creati la prima volta |
| `pacchetto_test(null, $def)` | `aggiornamenti.php` | importa la definizione di un test |
| `moduli_disponibili($o)`, `modulo_html('', $tipo, $percorso)` | `editor-widget.php`, `viste.php` | moduli da compilare in più nel widget Modulo, e il loro HTML (`moduli.md`) |
| `pagina_moduli($html, $percorso)` | `viste.php` | moduli aggiunti al testo di una pagina scritta nella scheda classica |
| `categorie_protette([...])`, `redattore_pagine([])`, `dashboard_numeri([])` | pannello | categorie che non si eliminano, pagine del pannello aperte al redattore, numeri della bacheca |
| `sicurezza_predefiniti($p)`, `csp_pannello_inline([])`, `csp_pannello_sorgenti([])`, `uscita_host($h)` | `sicurezza.php`, `sicurezza-html.php` | predefiniti della sicurezza, eccezioni della CSP del pannello, siti esterni ammessi nei link |
| evento `sito_query($q, $percorso)` | `sito/index.php` | una query del sito (per esempio `/?a_caso=1` per un contenuto a caso) |
| evento `sito_percorso($percorso, $q)` | `sito/index.php` | gli indirizzi del modulo (conferme, download, pagine di cortesia): il modulo risponde e chiude |
| evento `sito_genere($v, $meta)` | `sito/index.php` | un genere della mappa che il nucleo non conosce |
| `contenuti_elenco($tutti, $dove)` (2.1) | `sito_contenuti_elenco()`, `sito-dati.php` | i contenuti che escono negli elenchi pubblici: `$dove` è `cerca` (ricerca), `feed`, `sitemap` (anche IndexNow), `llms` (llms.txt e llms-full.txt). Un modulo toglie i suoi |
| `scheda_riquadri([], $tipo, $id, $r)` (2.1) | `admin/contenuto.php` | riquadri in più nella colonna di destra della scheda di un contenuto che esiste già: un elenco di HTML, ognuno un `<div class="sc-scatola">` |
| evento `scheda_salvata($tipo, $id, $d)` (2.1) | `admin/contenuto.php` | dopo il salvataggio della scheda, con il POST (`$d`): i campi dei riquadri qui sopra. Chi ascolta controlla da sé il ruolo (la scheda la apre anche il redattore) e che il suo campo sia arrivato |

## Le impostazioni che il nucleo legge

`sito_url`, `sito_nome`, `sito_titolo`, `sito_sottotitolo`, `sito_descrizione`, `sito_frase`, `sito_favicon`, `sito_lingua`,
`autore_predefinito`, `pagina_blog`, `blog_nome`, `blog_occhiello`, `blog_titolo`, `blog_intro`, `home_titolo`, `home_descrizione`,
`glossario_titolo`, `glossario_sottotitolo`, `glossario_pagine`, `newsletter_nome`, `newsletter_pagina`, `newsletter_descrizione`,
`newsletter_intestazione`, `newsletter_firma`, `email_intestazione`, `email_firma`, `jsonld_id_persona`, `jsonld_id_organizzazione`,
`servizio_tipo`, `servizio_zona`, `editor_versioni_limite`,
`citazione_autore`, `whatsapp_messaggio`, `w_ritratto`, `w_chi_sono_titolo`, `w_hero_occhiello`, `w_hero_foto`,
`w_onda_etichetta`, `w_firma`, `w_prezzi_titolo`. L'installatore scrive le prime (`sito_*`, `autore_predefinito`, `pagina_blog`,
`blog_*`, `pagina_home`, `home_descrizione`); le altre hanno un default generico e un modulo può dare i suoi.

## Un modulo d'esempio

Il prodotto non ne contiene: la cartella `moduli/` è vuota. Un modulo completo tiene i suoi file in `includes/`, le pagine
del pannello in `admin/` (servite da `pannello`), le API in `api/`, e dichiara nel manifesto tipi di contenuto, valori di
partenza del sito (home, header e footer, menu, stile, con i filtri qui sopra), passi del cron e widget.

## Gli addon (2.0.0)

Un addon è un modulo che arriva con il prodotto: stesso manifesto, ma sta in `cms/addon/<nome>/modulo.php` ed è spento
finché l'amministratore non lo accende da Impostazioni > Addon (`admin/addon.php`, `includes/addon.php`).

- Quelli accesi sono in `moduli/addon-attivi.json` (fuori dagli zip e dagli aggiornamenti, dentro la copia del sito, 403 dal web).
  `cms_moduli()` carica prima `moduli/*` e poi gli addon accesi, con `'addon' => true`; `cms_modulo_dir()` e `cms_modulo_url()` li trovano in `addon/`.
- Chiavi in più del manifesto: `descrizione` (la carta in Impostazioni > Addon), `richiede` (`['membri']`: accesi insieme, e non si
  spegne un addon che un altro acceso usa), `cron_spesso` (passi del giro frequente, `cron_newsletter()`, ogni pochi minuti).
- Accendendo si chiama `installa` (le tabelle). Spegnendo non si cancella niente.
- Le pagine del pannello: chiave `pannello` => `['agenda' => __DIR__ . '/includes/admin-agenda.php']`, aperte da
  `admin/addon-pagina.php?a=<addon>&p=<pagina>` (solo amministratore, 404 se l'addon è spento). Nella voce di menu
  `'attivo' => ['addon-pagina.php?a=<addon>&p=<pagina>']`. La pagina comincia con `$adminTitle = '...'` e
  `require CMS_ADMIN . '/includes/admin-header.php';`, finisce con `admin-footer.php`; auth e CSRF sono già caricati (`verify_csrf()`, `csrf_field()`).
- Gli indirizzi pubblici: evento `sito_percorso($percorso, $q)` (il modulo risponde ed esce). I widget: chiave `widget`.
- (2.1) Un collegamento OAuth 2 (per esempio Google Drive o Dropbox): il redirect URI da registrare presso il servizio è
  sempre `admin/oauth-ritorno.php`, fisso e senza query. Lo `state` comincia con `<addon>.`; la pagina (solo amministratore)
  chiama la funzione della chiave `oauth_ritorno` del manifesto con `$_GET`, che controlla lo state e chiude con il suo
  redirect. Senza un addon acceso con quella chiave: 400. Esempio: `addon/copia-altrove/`.
- Dal web: `addon/*/includes|sql|docs` e ogni `.php` di `addon/` danno 403 (`.htaccess`, esempi, Nginx, router delle prove).
