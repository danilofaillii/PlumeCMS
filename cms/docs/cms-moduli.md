# I moduli del CMS

(`docs/moduli.md` racconta i moduli ebook e Laser Coaching del sito, cioè `includes/moduli.php`: altra cosa.)

Un modulo è una cartella `moduli/<nome>/` con un file `modulo.php` che **restituisce un array**, il manifesto. Il nucleo lo
legge a ogni richiesta (`cms_moduli()`) prima del database: nel manifesto niente query, solo dichiarazioni e funzioni anonime
che caricano i file veri quando servono (`$carica('aforismi.php')`). Un file `DISATTIVO` nella cartella spegne il modulo.

## Le chiavi del manifesto

| Chiave | Forma | Chi la usa |
|---|---|---|
| `etichetta`, `versione`, `descrizione` | testo | pagina Informazioni |
| `impostazioni` | `['chiave' => valore]` | `cms_impostazione()` come fallback. `uploads_dir` e `uploads_url` spostano le immagini |
| `tipi_contenuto` | `['aforismi' => ['tabella' => 'aforismi', 'uno' => 'aforisma', 'tanti' => 'aforismi', 'Uno', 'Tanti', 'nuovo' => 'Nuovo aforisma', 'campo_contenuto' => 'content', 'tassonomie' => true, 'attributi' => false, 'kw' => false, 'citazione' => true, 'lettura' => true, 'genere' => 'm', 'icona' => '&#128142;']]` | `contenuti_tipi()`: elenco, scheda, editor, modelli ("Tutti gli aforismi"), menu di navigazione |
| `menu` | voci `['nome', 'url', 'icona', 'attivo' => [file che la accendono], 'attivo_tipo' => '?tipo=', 'sotto' => [...], 'admin' => bool, 'dopo' => 'Voce del nucleo' oppure 'gruppo' => 'Gruppo del nucleo' (+ 'dopo' dentro il gruppo)]` | `cms_menu_integra()` nel menu del pannello |
| `pannello` | `['nome-pagina' => '/percorso/file.php']` | lo shim `admin/<nome-pagina>.php` fa `cms_pagina_modulo('<modulo>', '<nome-pagina>')` |
| `api` | come `pannello`, per `api/*.php` e `share/*.php` | idem |
| `installa` | funzione che crea le tabelle del modulo | installatore, `cms_moduli_installa()` |
| `schema` | `['nome' => funzione che torna righe di log]` | pannello Aggiornamenti, dopo lo snapshot |
| `cron` | `['nome del passo' => funzione]` | `cron-daily.php`, un passo per voce, errori nel log |
| `azioni` | `['nome-azione' => callable]` | pacchetti (`agg_azioni()`) |
| `widget` | elenco di file PHP con funzioni `ew_*` | `editor-controlli.php` li carica dopo quelli del nucleo |
| `filtri` | `['nome' => fn($valore, ...$arg) => $valore]` | `cms_filtro('nome', $valore, ...$arg)`, in catena fra i moduli |
| `eventi` | `['nome' => fn(...$arg) => void]` | `cms_evento('nome', ...$arg)` |

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
| evento `sito_query($q, $percorso)` | `sito/index.php` | una query del sito (la roulette degli aforismi) |
| evento `sito_genere($v, $meta)` | `sito/index.php` | un genere della mappa che il nucleo non conosce (il test) |

## Le impostazioni che il nucleo legge

`sito_url`, `sito_nome`, `sito_titolo`, `sito_sottotitolo`, `sito_descrizione`, `sito_frase`, `sito_favicon`, `sito_lingua`,
`autore_predefinito`, `pagina_blog`, `blog_nome`, `blog_occhiello`, `blog_titolo`, `blog_intro`, `home_titolo`, `home_descrizione`,
`glossario_pagine`, `newsletter_nome`, `newsletter_pagina`, `newsletter_promessa`, `newsletter_intestazione`, `newsletter_firma`,
`email_intestazione`, `email_firma`, `ebook_titolo`, `ebook_nome_file`, `jsonld_id_persona`, `jsonld_id_organizzazione`,
`citazione_autore`, `whatsapp_messaggio`, `w_ritratto`, `w_chi_sono_titolo`, `w_hero_occhiello`, `w_hero_foto`,
`w_onda_etichetta`, `w_firma`, `w_prezzi_titolo`. L'installatore scrive le prime (`sito_*`, `autore_predefinito`, `pagina_blog`,
`blog_*`, `pagina_home`, `home_descrizione`); le altre hanno un default generico e il modulo danilofailli dà i suoi.

## Il modulo danilofailli

`moduli/danilofailli/modulo.php` è l'esempio completo: aforismi come tipo di contenuto (`aforismi.php`), i valori di partenza
del sito (`sito.php`: home, header e footer fissi, alberi dell'editor, menu, stile), test e statistiche, social del pillar,
email del mattino, conversione degli articoli in blocchi (`includes/`), le pagine del pannello (`admin/`), le API (`api/`,
`share/`). Nel nucleo restano i file ponte agli stessi indirizzi di prima (`admin/test.php`, `api/random-aforisma.php`,
`includes/test.php`...): una riga che chiama il modulo, così link e documenti continuano a valere.
