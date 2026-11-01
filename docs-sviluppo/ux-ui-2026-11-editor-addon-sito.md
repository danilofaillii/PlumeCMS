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
Commit: `74ee641` installatore, `26b0c61` editor e addon, `72d3ddd` prova e contorno della tavolozza, `9b7c0b2` documento e frase dell'host che non risponde.

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

## Da decidere con Danilo

Cambiano come funziona una cosa: non li ho toccati.

1. **Installatore, passo 4 con "Su misura"**: il pulsante dice "Installa adesso" ma porta alle quattro domande. Proposta:
   con la carta Su misura scelta il pulsante dice "Avanti: le quattro domande" (serve un piccolo script, o un pulsante
   in più).
2. **Installatore, errori del passo 3**: gli errori stanno in cima e i campi sbagliati non sono segnati. Proposta: il
   bordo rosso e la frase sotto il campo sbagliato (`aria-invalid`), come nel pannello.
3. **Editor, cursori con la casella vuota** (Dimensione, Interlinea, Z-index...): vuoto vuol dire "come il sito", ma il
   cursore sta tutto a sinistra e sembra zero. Proposta: nella casella il segnaposto "auto" (o "come il sito") e il cursore
   grigio finché non si sceglie un valore.
4. **Editor, pallino del monitor accanto ai controlli** (valore per computer, tablet, telefono): grigio = eredita, scuro =
   valore proprio. Nessuno lo capisce senza il `title`. Proposta: un'etichetta breve ("solo telefono") quando il valore è
   proprio di una misura, o una riga d'aiuto la prima volta.
5. **Editor, schede del pannello** (Widget, Sezioni, Modifica, Navigatore) come vere schede ARIA (`role=tablist`, frecce
   da tastiera). Ora sono pulsanti con `aria-pressed`: funzionano, ma le frecce no.
6. **Editor al telefono**: oggi solo l'avviso. Si potrebbe aprire un editor ridotto (testi e foto della pagina) invece
   della tela.
7. **Editor, widget degli addon vuoti** (Prodotti, Recensioni dal sito senza dati): sul sito non mostrano niente, e
   nell'editor non si capisce che il widget c'è. Proposta: nella tela dell'editor un riquadro tratteggiato "Ancora
   nessun prodotto: aggiungili in Vetrina > Prodotti" (solo nell'anteprima, mai sul sito).
8. **Copia altrove**: con la destinazione "non ancora collegata", "Manda una copia adesso" è il pulsante principale e
   nello stesso riquadro ci sono due pulsanti di salvataggio ("Salva e collega con Google Drive" e "Salva la
   destinazione"). Proposta: "Manda una copia adesso" spento finché la destinazione non è collegata, e un solo salvataggio.
9. **Sito pubblico, data delle schede del blog** (`.card-meta` in `sito.css`): 4,24 a 1, sotto 4,5. È il sito di tutti i
   clienti: cambiarla cambia le foto di chi aggiorna. Proposta per una versione con cambi del sito annunciati:
   `--testo-3` un poco più chiaro nel tema di base.

## Segnalazioni sul sito pubblico (nessuna modifica)

- La ricerca senza risultati dice "Nessun risultato per questa parola.": si potrebbe proporre la ricerca di una parola
  più corta o i link del menu (bassa).
- Il form dei contatti mandato vuoto senza controllo del browser risponde "Spunta Sono una persona e aspetta il segno
  verde": il controllo del captcha arriva prima di quello dei campi obbligatori, quindi chi ha davvero lasciato vuoto un
  campo lo scopre solo dopo (bassa; nel browser normale i campi obbligatori fermano prima).

## Prove

- `php -n -d short_open_tag=1 -l` sui PHP toccati, `node --check` su `editor.js` e sulla prova nuova: a posto.
- `prove/prova-ux-editor.js` (nuova): **37 su 37** (installatore su una copia non installata, editor in Aria e Cosmo,
  header, telefono, Agenda, Vetrina nel pannello e da visitatore).
- `prove/prova-install.js` dallo zip costruito (`tools/cms-pacchetto.py`, 340 file): **37 su 37 in radice e 37 su 37 in
  cartella** (`/test`). Dalla cartella del repository senza zip manca `uploads/.htaccess` (lo mette il costruttore): 36 su 37,
  non è un difetto del prodotto.
- `prove/prova-kit.js` attività: 34 su 34. `prove/prova-addon-tutti.js` su un sito nuovo: **70 su 70**.
- `prove/prova-hero.js` con l'editor (le undici aperture senza foto): **161 su 161**.
- Foto del sito prima e dopo sullo stesso database, addon accesi, con `/tutti-i-widget/`, Servizi, Chi siamo, `/negozio/`,
  una pagina con tutti i widget degli addon, Corsi e Area riservata (32 foto). `quanto-foto.py`: differenze solo in
  `widget` (il conto alla rovescia, rumore noto, e il numero del widget Carrello), `negozio` (numero del carrello e filtro
  "Tutti") e nella pagina degli addon (numero del carrello): sono le correzioni 22, volute. Tutte le altre pagine identiche.
- Non rifatte: `prova-aspetti.js` (lunga; il contrasto dell'editor nei quattro aspetti l'ho misurato con l'esplorazione,
  zero testi sotto soglia), attacchi (nessuna modifica a permessi o richieste).

## Per il manuale tecnico (da riportare)

- `install/index.php`: `inst_errore_db()` traduce i codici 1045, 1044, 2002, 2003, 2006, 2013; `inst_campo()` collega
  l'aiuto al campo (`aria-describedby`, id `<campo>-aiuto`); `.errore` con `role="alert"`.
- `admin/editor.php`: `#ed-avviso-h1`, `.ed-solo-computer` (sotto 760 px), `#ed-widget-nessuno`, `.ed-aiuto-sopra`; per i
  modelli `#ed-percorso` dice il tipo, non l'anteprima.
- `admin/assets/editor/editor.js`: `nominaCampi()` con `MutationObserver` su `#ed-controlli`; widget della tavolozza con
  `role="button"` e `tabindex`; `avvisoH1()` spento per i modelli; `aria-pressed` su misure e schede.
- `addon/vetrina/assets/vetrina.css`: `color-mix` sul fondo di `.vt-conta`, `.vt-filtri a[aria-current]`, `.vt-bollino`.

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
