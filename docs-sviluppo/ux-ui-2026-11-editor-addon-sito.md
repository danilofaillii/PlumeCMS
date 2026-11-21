# Controllo di UX e UI: editor, installatore, addon, sito pubblico (novembre 2026)

Data: 1 novembre 2026. Partenza: commit `b9be2f6` (plumeCMS 2.5.0). Parte "B" del controllo globale chiesto da Danilo
("ogni parte, anche recondita"); la parte "A" (il pannello del nucleo, `admin.css` e `aspetti.css`) la fa un altro agente.

Come è stato fatto: un sito di prova col kit Attività e gli otto addon accesi, guardato con Playwright come lo userebbe
Danilo, nei quattro aspetti del pannello (Aria, Cosmo, Bottega, Lavagna) a 1280 e a 390 px. Per ogni schermata: contrasto
di ogni testo misurato (anche con l'opacità dei genitori, che `prova-aspetti.js` salta), fuoco da tastiera passo per passo
con Tab, campi senza un nome per il lettore di schermo, scorrimento di lato, messaggi d'errore provocati apposta
(database sbagliato, password debole, ricerca vuota, form vuoto), più la lettura del codice di `editor.php`, `editor.js`,
`editor.css`, `install/index.php`, `install.css` e delle pagine degli addon.

## Problemi trovati

Gravità: alta = blocca o fa sbagliare; media = confonde o esclude chi usa la tastiera o un lettore di schermo; bassa = rifinitura.
Commit, primo giro: `74ee641` installatore, `26b0c61` editor e addon, `72d3ddd` prova e contorno della tavolozza, `9b7c0b2` documento e
frase dell'host che non risponde. Secondo giro (le scelte di Danilo del 1 novembre, righe 23-33): `5fccd02` installatore, `8b12def`
editor ed eventi e corsi vuoti, `d109430` Copia altrove, `4e5a442` e `f3d94aa` sito, `6de1a77` prova estesa e caselle dei cursori.

| N. | Gravità | Dove | Cosa confonde chi usa | Criterio | Correzione o proposta | Commit |
|---|---|---|---|---|---|---|
| 1 | alta | Installatore, passo 2 (database) | Con password o utente sbagliati usciva solo `Connessione rifiutata: SQLSTATE[HY000] [1045] Access denied for user ...`: in inglese, tecnico, senza dire cosa fare | Nielsen 9 (aiutare a capire e correggere gli errori) | `inst_errore_db()`: per 1045, 1044, 2002 (host inesistente o che non risponde), 2006/2013 una frase in italiano con il campo da controllare e dove (cPanel, Database MySQL); il messaggio del server resta in fondo fra parentesi, per l'hosting | 74ee641 |
| 2 | media | Installatore, passo 2 | Database che non esiste e utente senza permesso di crearlo: il messaggio mescolava la frase italiana e quella del server | Nielsen 9 | Frase intera in italiano, dettaglio tecnico fra parentesi | 74ee641 |
| 3 | media | Installatore, passo 4 (Che sito vuoi fare?) | Il pallino della scelta delle carte era coperto dal disegnino: si capiva quale carta è scelta solo dal bordo | Nielsen 1 (stato visibile), WCAG 1.4.11 | `z-index:1` sul pallino: ora si vede in alto a destra della carta | 74ee641 |
| 4 | media | Installatore, tutti i passi | Il fuoco da tastiera sui pulsanti era il contorno sottile del browser, quasi invisibile sul blu | WCAG 2.4.7, 2.4.11 | Contorno di 3 px più alone sui pulsanti, sui link e sulle spunte | 74ee641 |
| 5 | bassa | Installatore, passo 3 | L'etichetta "Password (almeno 12 caratteri, non fra le più comuni)" andava a capo e disallineava le due caselle affiancate | Gerarchia visiva | Etichetta "Password", la regola nel testo d'aiuto sotto; "Ripeti la password" con il suo aiuto | 74ee641 |
| 6 | bassa | Installatore | Gli aiuti sotto i campi non erano collegati ai campi; gli errori non erano annunciati | WCAG 1.3.1, 4.1.3 | `aria-describedby` sui campi con aiuto, `role="alert"` sul riquadro degli errori | 74ee641 |
| 7 | alta | Editor, tavolozza dei widget | I widget erano `div` senza tabindex: con la tastiera non si poteva aggiungere niente (Tab saltava dalla ricerca alla tela) | WCAG 2.1.1 | `role="button"`, `tabindex="0"`, Invio e Spazio aggiungono il widget come il clic. Resta un `div` perché un `<button>` trascinabile in Firefox non parte | 26b0c61 |
| 8 | media | Editor, tavolozza | L'aiuto "Trascina un widget nella pagina, oppure fai clic" stava sotto cento widget: non lo leggeva nessuno | Nielsen 10 (aiuto dove serve) | Spostato sopra l'elenco | 26b0c61 |
| 9 | media | Editor, ricerca dei widget | Una ricerca senza risultati lasciava solo i titoli dei gruppi vuoti, senza dire niente | Nielsen 1 | Gruppi vuoti nascosti e la frase "Nessun widget con questo nome. Prova con un'altra parola..." | 26b0c61 |
| 10 | media | Editor, barra (misure, annulla e ripeti) e schede | Il contorno del fuoco era tagliato dal contenitore con `overflow:hidden`; nessun fuoco visibile su molti pulsanti piccoli | WCAG 2.4.7 | Contorno di 2 px del colore d'accento su ogni pulsante, link, campo e tendina dell'editor; nei gruppi attaccati il contorno va dentro (`outline-offset:-3px`) | 26b0c61 |
| 11 | media | Editor, schede Contenuto, Stile, Avanzate | 28 campi su un widget (unità, cursori, caselle numeriche, tendine di Avanzate, CSS personalizzato) senza nome: il lettore di schermo diceva solo "casella di testo" | WCAG 1.3.1, 4.1.2 | `nominaCampi()` in `editor.js`: ogni campo senza nome prende quello della sua etichetta (con "unità di misura", "cursore", "scegli il colore"); un `MutationObserver` lo rifà a ogni ridisegno. Niente cambia a vedersi | 26b0c61 |
| 12 | media | Editor, colori globali (Stile) | Su Cosmo e Lavagna i pallini dei colori scuri sparivano sul pannello scuro (anello 1,1 a 1) | WCAG 1.4.11 | Anello con `--bordo-forte` (Cosmo 2,0 a 1, Aria 1,6) | 26b0c61 |
| 13 | media | Editor di header, footer e riquadro di fine articolo | Il nome era arancione (avviso "manca l'H1", che un header non deve avere) e sotto c'era `/?preview=1&header=1` | Nielsen 2 (linguaggio di chi usa), 8 | Per i modelli niente avviso H1; sotto il nome "Header: la testata delle pagine", "Footer: il piede delle pagine", "Riquadro in fondo a ogni articolo" | 26b0c61 |
| 14 | media | Editor di una pagina senza H1 | L'avviso era solo il colore del nome e un `title` al passaggio del mouse: Danilo non lo vedeva | WCAG 1.4.1 (non solo il colore), Nielsen 1 | Una riga "Manca il titolo principale (H1)" sotto il nome, che si accende e si spegne con l'avviso | 26b0c61 |
| 15 | media | Editor al telefono (390 px) | La barra era tagliata, la tela larga 744 px, nessuna spiegazione | Nielsen 1, 9 | Un riquadro in basso: "L'editor visuale si usa da computer o tablet... Puoi cambiare i testi dalla scheda" (con il link) | 26b0c61 |
| 16 | bassa | Editor, finestra Immagini | La casella del file si vedeva con il testo del browser ("Choose File, No file chosen", in inglese su un Chrome inglese) dentro un riquadro: è la trappola `hidden` più `display` già pagata | Coerenza (Nielsen 4) | La casella resta raggiungibile ma invisibile; il riquadro "Carica un'immagine" mostra il fuoco | 26b0c61 |
| 17 | bassa | Editor, finestra Immagini e campo Foto | Cartella, ricerca, indirizzo e "×" senza nome | WCAG 4.1.2 | `aria-label` | 26b0c61 |
| 18 | bassa | Editor, barra | Pulsanti delle misure e di annulla e ripeti con le sole icone (&#128421; ▭ ▯ ↶ ↷); non si capiva quale misura fosse scelta | WCAG 4.1.2 | `aria-label` in parole e `aria-pressed` (anche sulle quattro schede del pannello); `role="status"` sullo stato "Bozza salvata..." | 26b0c61 |
| 19 | bassa | Editor, scelta iniziale "La grafica di oggi" | Il testo elencava "apertura, tre passi, frase, schede, chi sono, ultimi pillar": resti del primo sito, che le home dei kit non hanno | Nielsen 2 | "La home com'è adesso, già divisa in sezioni e widget. Cambi quello che vuoi." | 26b0c61 |
| 20 | media | Prenotazioni, Agenda | "‹ Prima" e "Dopo ›" accanto a "Oggi": prima di cosa? | Nielsen 2, 6 | "‹ Settimana prima", "Settimana dopo ›" (nella vista per giorno "Giorno prima", "Giorno dopo") | 26b0c61 |
| 21 | bassa | Vetrina, Prodotti e Ordini | Casella di ricerca senza nome (solo il segnaposto) | WCAG 3.3.2 | `aria-label` | 26b0c61 |
| 22 | media | Vetrina, sito pubblico (negozio, widget Carrello) | Numero del carrello, filtro scelto e bollino: bianco su viola pieno, 3,96 a 1 | WCAG 1.4.3 | Fondo viola scurito di un quinto (`color-mix`, 5,5 a 1), il primo `background` resta per i browser vecchi. Cambia solo l'aspetto delle parti della Vetrina | 26b0c61 |
| 23 | media | Installatore, passo 3 | Gli errori stavano solo in cima: con quattro errori non si capiva quale campo correggere | Nielsen 9, WCAG 3.3.1 | (scelta di Danilo) l'errore sotto il campo sbagliato, bordo rosso di 2 px, `aria-invalid` e `aria-describedby` verso il messaggio; in cima resta il riassunto "4 campi da sistemare: li trovi segnati in rosso qui sotto". Anche i campi del passo 2 (prefisso, nome, utente) | 5fccd02 |
| 24 | media | Installatore, passo 4 | Con la carta Su misura il pulsante diceva "Installa adesso" ma portava alle quattro domande | Nielsen 4 (coerenza) | (scelta di Danilo) con Su misura il pulsante dice "Avanti: le quattro domande"; solo CSS (`:has`), niente script | 5fccd02 |
| 25 | media | Editor, cursori (Dimensione, Interlinea, Z-index...) | Casella vuota e cursore tutto a sinistra: sembrava zero, era "come il sito" | Nielsen 1, 2 | (scelta di Danilo) `cursoriVuoti()`: la casella dice "come il sito" e il cursore è grigio (opacità 0,5) finché non si sceglie un valore; casella allargata a 94 px perché la frase ci stia | 8b12def, 6de1a77 |
| 26 | media | Editor, pallino del monitor | Grigio chiaro o scuro: nessuno capiva che il valore valeva solo per il tablet o il telefono | WCAG 1.4.1, Nielsen 1 | (scelta di Danilo) `etichettaMisura()`: accanto al pallino "solo tablet" o "solo telefono" quando il valore è proprio di quella misura | 8b12def |
| 27 | bassa | Editor, schede Widget, Sezioni, Modifica, Navigatore | Erano pulsanti: con la tastiera solo Tab, niente frecce, il lettore di schermo non diceva "scheda 2 di 4" | WCAG 4.1.2 (modello ARIA delle schede) | (scelta di Danilo) `role=tablist/tab/tabpanel`, `aria-selected`, `aria-controls`, un solo `tabindex=0`; frecce sinistra e destra (girano), Inizio e Fine | 8b12def |
| 28 | media | Editor, widget Eventi con iscrizione e Elenco corsi senza dati | Nella tela non si capiva che il widget c'era né dove aggiungere gli eventi o i corsi | Nielsen 10 | (scelta di Danilo) nell'anteprima dell'editor il riquadro tratteggiato `.ed-vuoto` ("Ancora nessun evento in programma: aggiungili nel pannello, in Eventi > Nuovo evento..."; "Ancora nessun corso pubblicato: crealo nel pannello, in Corsi..."). Prodotti, Recensioni dal sito, Prenota e Iscrizione a un evento lo avevano già. Mai sul sito: esce solo con `$ctx['anteprima']`, e `.ed-vuoto` è nascosto da `sito.css` e `widget.css` (foto identiche) | 8b12def |
| 29 | media | Copia altrove | Due pulsanti di salvataggio nello stesso riquadro; "Manda una copia adesso" spento ma blu pieno, sembrava attivo | Nielsen 4, 5 | (scelta di Danilo) un solo pulsante in fondo, dopo tutti i campi: "Salva e collega con Google Drive/Dropbox" per un servizio non collegato, "Salva la destinazione" altrimenti (nel riquadro del servizio resta, solo da collegato, "Dai di nuovo il permesso a ..."); Prova e Manda spenti con opacità 0,45 e la frase "si accendono quando la destinazione è collegata" | d109430 |
| 30 | media | Sito, data delle schede del blog (`.card-meta`, anche il testo dei risultati della ricerca) | 4,24 a 1 nel tema di base e in Cosmo, 3,61 in Bottega | WCAG 1.4.3 | (scelta di Danilo, cambio voluto) `--testo-2` al posto di `--testo-3`: base e Cosmo 8,48, Aria 7,56, Bottega 7,13, Estate 7,82, Inverno 8,09, Autunno 8,98, Primavera 8,59, Giallo 15,59, Rosso 5,25, Verde 13,33, Blu 7,01 | 4e5a442 |
| 31 | bassa | Sito, ricerca senza risultati | "Nessun risultato per questa parola." e basta | Nielsen 9 | (scelta di Danilo) `vista_cerca_niente()`: la parola cercata, "Prova con una parola più corta o diversa, per esempio <link alla parola accorciata>", e fino a sei pagine del menu della testata (senza menu: Home e Blog) | 4e5a442, f3d94aa |
| 32 | bassa | Sito, form con captcha | Con il controllo del browser spento (`novalidate`) il form vuoto rispondeva prima "Spunta Sono una persona" | Nielsen 9 | (scelta di Danilo) in `moduli.js` prima `checkValidity()` e `reportValidity()` (il fuoco va al primo campo vuoto), poi il captcha. Il captcha resta obbligatorio nel browser e sul server (nessun cambio al server): attacchi 263 su 263 | 4e5a442 |
| 33 | bassa | Editor, punto 6 (editor ridotto al telefono) | Al telefono solo l'avviso | | **Rimandato** (decisione di Danilo: "lo facciamo partire in un altro momento") | |

Cose guardate e trovate a posto: contrasto di ogni testo dell'editor nei quattro aspetti (anche con le sezioni aperte, le
finestre Impostazioni, Immagini e Revisioni); le pagine degli otto addon a 1280 e 390 px (nessuno scorrimento di lato,
contrasto a posto tranne la freccina del menu, vedi segnalazioni); form dei contatti, ricerca, 404, area riservata, corsi,
eventi da visitatore a 1280 e 390 px (fuoco visibile, campi con l'etichetta, messaggi in italiano).

## Segnalazioni per `admin.css` e `aspetti.css` (li tocca solo l'altro agente)

1. **Freccina del menu laterale** (`.nav-freccia`, "▸"): con l'opacità 0,6 il contrasto scende a 3,3 a 1 in Aria. È un segno
   decorativo, ma è l'unico indizio che la voce si apre. Proposta: `.nav-freccia{opacity:1;color:var(--text-muted)}`.
2. **Testo introduttivo attaccato al primo riquadro** (Copia altrove, e altre pagine degli addon con `<p>` prima di
   `.card`): il paragrafo tocca il bordo del riquadro sotto. Proposta: `.admin-main > p + .card, .admin-main > p + .griglia-2 { margin-top: 16px; }`
   (o un `margin-bottom` sui paragrafi diretti di `.admin-main`).
3. **Segnaposto che sembrano valori** (Copia altrove: "copie-del-sito", "1234567890-abc...apps.googleusercontent.com"):
   il grigio del segnaposto è vicino a quello del testo e Danilo può pensare che il campo sia già compilato. Proposta:
   `::placeholder{color:var(--text-muted);opacity:.75;font-style:italic}` (il contrasto del segnaposto non è obbligatorio
   per WCAG, ma qui conta la distinzione).
4. **Fuoco da tastiera nel pannello**: le pagine degli addon usano `.btn`, `.btn-sm` e i link delle schede (`pa-schede`)
   con il contorno del browser. Proposta coerente con l'editor:
   `:is(.btn,.admin-main a,.admin-main summary):focus-visible{outline:2px solid var(--coral);outline-offset:2px}`.

## Decisi da Danilo (1 novembre 2026)

Danilo ha scelto "tutto tranne il 6". Fatti (righe 23-32 della tabella): pulsante del passo 4 con Su misura, errori sotto i campi
dell'installatore, cursori vuoti "come il sito", "solo tablet"/"solo telefono", schede ARIA, addon vuoti spiegati nell'editor, Copia
altrove con un solo salvataggio, data delle schede del blog; e le due segnalazioni minori (ricerca senza risultati, campi prima del
captcha).

**Rimandato:** il punto 6, un editor ridotto al telefono (testi e foto della pagina invece della tela). Oggi al telefono c'è
l'avviso con il link alla scheda (riga 15).

## Prove

Secondo giro (codice di tutte le scelte, zip ricostruito con `tools/cms-pacchetto.py`, 340 file, 1917 KB):

- `php -n -d short_open_tag=1 -l` su ogni PHP toccato, `node --check` su `editor.js`, `moduli.js` e sulla prova: a posto.
- `prove/prova-ux-editor.js`: **71 su 71** (installatore su una copia non installata fino al passo 4 con un database vuoto, editor
  in Aria e Cosmo, schede ARIA con le frecce, cursori vuoti, "solo telefono", addon vuoti nell'editor e non sul sito, header,
  telefono, Agenda, Vetrina, Copia altrove, data del blog nel tema di base, Cosmo e Bottega, ricerca vuota, form).
- `prove/prova-install.js` dallo zip: **37 su 37 in radice e 37 su 37 in cartella** (`/test`).
- `prove/prova-kit.js` attività: 34 su 34 (due siti). `prove/prova-su-misura.js`: **34 su 34** (il pulsante con il testo nuovo).
- `prove/prova-addon-tutti.js` su un sito nuovo dallo zip: **70 su 70**.
- `prove/prova-hero.js` con l'editor: **161 su 161**.
- `tools/sicurezza-prova.js` su un sito Attività dallo zip, con `CMS_DIR`, `SEGRETO` e il WordPress cattivo finto
  (`FINTO_WP`): **263 su 263** (saltato solo il report del burnout, che non c'è nel prodotto); poi svuotata
  `cms_sicurezza_tentativi`. Il numero è più alto dei 261 delle note perché questo sito ha anche le prove con `FINTO_WP` e `SEGRETO`.
- Contrasto della data delle schede (`.card-meta`) in anteprima da amministratore con ogni tema: prima base e Cosmo 4,24,
  Bottega 3,61 (gli altri sopra 4,5); dopo tutti sopra 5 (vedi riga 30).
- Non rilanciate: `prova-aspetti.js` (lunga; il contrasto dell'editor nei quattro aspetti l'ho misurato a parte) e
  `prova-addon-copia-altrove.js` (vuole i server finti FTP, FTPS, WebDAV e cloud): l'ho adattata al pulsante unico (il caso
  dell'ID scritto male ora preme "Salva e collega"), ma non l'ho fatta girare.

### Foto del sito prima e dopo

Stesso database (kit Attività, otto addon accesi, `/tutti-i-widget/`, pagina con i widget degli addon), 34 foto a 1280 e
390 px: home, blog, articoli, contatti, widget, ricerca, 404, Servizi, Chi siamo, negozio, pagina degli addon, Corsi, Area
riservata, ricerca senza risultati.

- Primo giro (vetrina): cambiano solo il numero del carrello e il filtro "Tutti" del negozio (`piu-negozio` 2.163 pixel,
  `piu-addon-tutti` 394, il carrello in `widget`) e il conto alla rovescia (rumore noto).
- Secondo giro: **`blog-pc` e `blog-tel`**: 557 pixel, la data della scheda dell'articolo (zona di 120 x 8 px, da `#6f7a92` a
  `#a7b1c6`, scarto massimo 56 livelli); **`piu-s-zzqqxx`** (la ricerca senza risultati): pagina nuova, al telefono più alta di
  35 px (844 -> 879) per il suggerimento e i link; `widget-pc` e `widget-tel`: solo il conto alla rovescia. Tutte le altre
  identiche al pixel. Il riquadro tratteggiato degli addon vuoti non compare sul sito (la pagina degli addon è identica).
- Con un tema attivo cambia lo stesso elemento (la data delle schede del blog e il testo sotto il titolo dei risultati della
  ricerca) in tutti gli undici temi: è da annunciare nel CHANGELOG come cambio voluto.

Primo giro: `prova-ux-editor.js` 37/37, install 37/37 radice e cartella, addon 70/70, hero 161/161.

## Per il manuale tecnico (da riportare)

- `install/index.php`: `inst_errore_db()` traduce i codici 1045, 1044, 2002, 2003, 2006, 2013; `inst_campo()` collega
  l'aiuto al campo (`aria-describedby`, id `<campo>-aiuto`); `.errore` con `role="alert"`.
- `admin/editor.php`: `#ed-avviso-h1`, `.ed-solo-computer` (sotto 760 px), `#ed-widget-nessuno`, `.ed-aiuto-sopra`; per i
  modelli `#ed-percorso` dice il tipo, non l'anteprima.
- `admin/assets/editor/editor.js`: `nominaCampi()` con `MutationObserver` su `#ed-controlli`; widget della tavolozza con
  `role="button"` e `tabindex`; `avvisoH1()` spento per i modelli; `aria-pressed` su misure e schede.
- `addon/vetrina/assets/vetrina.css`: `color-mix` sul fondo di `.vt-conta`, `.vt-filtri a[aria-current]`, `.vt-bollino`.
- Secondo giro: `inst_campo()` legge `$errori['<campo>']` (errori con chiave di campo nei passi 2 e 3, `.campo.sbagliato`,
  `.errore-campo`, `aria-invalid`); pulsante del passo 4 con `.testo-installa` e `.testo-domande` (CSS `:has`); in `editor.js`
  `cursoriVuoti()`, `etichettaMisura()`, schede ARIA del pannello con la tastiera; `editor.php` con `role=tablist/tab/tabpanel`;
  `.ed-vuoto` nell'anteprima dei widget `eventi_iscrizione` e `corsi_elenco`; Copia altrove con il salvataggio unico
  (`.ca-salvataggio`, `#ca-spenti-perche`); `sito.css` `.card-meta` con `--testo-2` e `.cerca-niente`; `vista_cerca_niente()`
  in `sito/viste.php`; `moduli.js` con `checkValidity()` prima del captcha.

## Principi applicati (appunti per le regole globali d'interfaccia)

1. Ogni errore dice in italiano cosa è successo, quale campo controllare e dove; il testo tecnico va in fondo, fra parentesi.
2. Lo stato non si dice solo con il colore: un avviso arancione ha anche una frase.
3. Tutto quello che si fa con il mouse si fa anche con la tastiera (Tab, Invio, Spazio), e il fuoco si vede: 2 px pieni
   del colore d'accento, mai tagliati da un contenitore con `overflow:hidden` (allora il contorno va dentro).
4. Ogni campo ha un nome leggibile dal lettore di schermo: un'etichetta vera, o `aria-label` uguale al testo che si vede.
5. L'aiuto sta prima della cosa che spiega, non in fondo a un elenco lungo.
6. Nell'interfaccia niente indirizzi tecnici o parole del codice (`preview`, `SQLSTATE`): si dice cos'è la cosa.
7. Una ricerca senza risultati lo dice, e suggerisce cosa provare.
8. I bordi dei controlli (pallini dei colori, caselle) almeno 3 a 1 contro il fondo in tutti gli aspetti, scuri compresi.
9. Un'etichetta di un pulsante di navigazione dice di cosa: "Settimana prima", non "Prima".
10. Dove uno strumento non funziona (l'editor al telefono) lo si dice e si dà la strada alternativa.
11. Sul sito dei clienti si corregge solo l'accessibilità e solo dove non cambia l'aspetto delle pagine normali; ogni pixel
    cambiato si spiega con le foto prima e dopo.

## Schermate (prima e dopo)

- Errore del database: `immagini/ux-ui/b-installatore-errore-prima.jpg`, `b-installatore-errore-dopo.jpg`
- Carte dei kit al telefono: `b-installatore-kit-prima.jpg`, `b-installatore-kit-dopo.jpg`
- Barra dell'editor di un header: `b-editor-header-prima.jpg`, `b-editor-header-dopo.jpg`
- Editor al telefono: `b-editor-telefono-prima.jpg`, `b-editor-telefono-dopo.jpg`
- Tavolozza con l'aiuto in cima: `b-editor-tavolozza-dopo.jpg`
- Secondo giro: `b-installatore-passo3-dopo.jpg` (errori sotto i campi), `b-editor-cursori-come-il-sito-dopo.jpg`,
  `b-editor-solo-telefono-dopo.jpg`, `b-copia-altrove-dopo.jpg`, `b-sito-ricerca-vuota-dopo.jpg`,
  `b-sito-blog-data-prima.jpg` e `b-sito-blog-data-dopo.jpg`
