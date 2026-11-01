# Regole di interfaccia

Data: 2026-11-01. Richiesta di Danilo: "un documento che andrà come memoria e come regola globale per la costruzione di
qualsiasi cosa". Vale per tutto quello che si costruisce per Danilo: pannelli, siti, app, moduli, email, documenti, in
qualsiasi progetto. Nasce dal controllo di UX e UI di plumeCMS del novembre 2026 (`ux-ui-2026-11-pannello.md` e
`ux-ui-2026-11-editor-addon-sito.md`): gli esempi "sbagliato" sono errori veri trovati lì.

Come si legge: per ogni regola c'è la regola, il perché, un esempio giusto e uno sbagliato. Prima le regole generali,
poi "In plumeCMS" con variabili, classi e file. In fondo la checklist da usare prima di consegnare una schermata.

Chi usa quello che costruiamo spesso non è uno sviluppatore: deve capire da solo dove guardare, cosa scrivere e cosa
succede quando preme un pulsante.

---

## 1. Gerarchia

**1.1 Il titolo di una sezione sta in una fascia.**
Perché: chi apre la pagina deve vedere subito dove comincia ogni gruppo.
Giusto: in plumeCMS "Verifica della proprietà" ha un fondo diverso dal riquadro, una striscia d'accento e una linea sotto.
Sbagliato: in SEO > Motori di ricerca titolo e campi erano dello stesso grigio, e la pagina sembrava un elenco piatto.

**1.2 Etichette dei campi e titoli non si somigliano mai.**
Perché: se si somigliano, chi legge scambia un campo per l'inizio di una sezione.
Giusto: titolo nella fascia in grassetto, etichetta "Google Search Console" in carattere normale, a colore pieno.
Sbagliato: "VERIFICA DELLA PROPRIETÀ" e "GOOGLE SEARCH CONSOLE" erano tutti e due maiuscoletto grigio della stessa misura.

**1.3 I livelli sono tre: fascia, sottotitolo, etichetta.** Ognuno si distingue per più di una cosa (fondo, misura,
peso, colore).
Perché: due livelli diversi solo per la misura non si distinguono di colpo.
Giusto: il sottotitolo "Crawler delle intelligenze artificiali" è in maiuscoletto piccolo, con una riga sopra.
Sbagliato: un titolo diverso dal testo solo per un punto in più di carattere.

**1.4 Un testo d'aiuto è più piccolo e più tenue del testo che spiega.**
Perché: deve accompagnare il campo, non fargli concorrenza.
Giusto: in plumeCMS l'aiuto sotto il campo è piccolo e grigio, ma resta sopra 4,5:1.
Sbagliato: il paragrafo sotto "Genera la chiave IndexNow" era grande come il testo principale.

## 2. Campi

**2.1 Il bordo di un campo ha almeno 3:1 sul fondo intorno, in ogni tema chiaro o scuro.**
Perché: il bordo è l'unico segno che lì si scrive (WCAG 1.4.11).
Giusto: in plumeCMS il bordo vale 3,7:1 in Aria, 4,1 in Bottega, 4,2 in Cosmo e 4,4 in Lavagna.
Sbagliato: in tutti e quattro gli aspetti i bordi stavano fra 1,24 e 1,37:1, e in Cosmo il campo si vedeva solo dal testo d'esempio.

**2.2 Il fuoco si vede: contorno pieno di 2 px del colore d'accento.**
Perché: chi usa la tastiera deve sapere dove sta (WCAG 2.4.7).
Giusto: `outline: 2px solid` dell'accento sul campo che ha il fuoco. Se un contenitore taglia il contorno (`overflow:hidden`), il contorno va dentro.
Sbagliato: un alone trasparente al 28%, invisibile sui fondi scuri; nell'editor il contorno era tagliato dalla barra.

**2.3 Il segnaposto non sembra mai un valore.**
Perché: chi vede del testo nel campo crede che sia già compilato.
Giusto: il segnaposto è più tenue del testo scritto (ma sopra 4,5:1), ed è un esempio ("per esempio: Clienti").
Sbagliato: in Copia altrove "copie-del-sito", in un grigio vicino a quello del testo, sembrava già scritto.

**2.4 Il segnaposto non sostituisce l'etichetta.**
Perché: quando si comincia a scrivere sparisce, e il lettore di schermo non lo legge sempre.
Giusto: etichetta vera sopra il campo, oppure `aria-label` uguale al testo che si vede.
Sbagliato: la ricerca di Vetrina aveva solo il segnaposto; nell'editor 28 campi venivano letti come "casella di testo" e basta.

**2.5 La scelta di un file è un pulsante vero e dice il nome del file scelto.**
Perché: il pulsante grigio del sistema non sembra una cosa da toccare, e il suo testo cambia con la lingua del browser.
Giusto: in plumeCMS il pulsante del browser ha l'aspetto di un pulsante del pannello, dentro un riquadro tratteggiato, e accanto c'è il nome del file.
Sbagliato: "Choose File / No file chosen" senza bordo in Aggiornamenti, Copia del sito e Media.

**2.6 Un valore vuoto dice cosa succede.**
Perché: un campo vuoto con un cursore tutto a sinistra sembra zero.
Giusto: la casella del cursore dice "come il sito", e il cursore è grigio finché non si sceglie un valore.
Sbagliato: casella vuota e cursore a zero, quando il valore vero era "quello del sito".

## 3. Pulsanti

**3.1 Primario, secondario e pericoloso si distinguono a colpo d'occhio.**
Perché: chi usa la pagina deve trovare subito l'azione principale e non premere per sbaglio quella che cancella.
Giusto: primario pieno dell'accento; secondario con il bordo almeno 3:1; pericoloso rosso ("Elimina il form").
Sbagliato: in Cosmo il secondario "Salva la chiave" aveva il bordo invisibile e sembrava un'etichetta.

**3.2 Un'azione principale per riquadro.**
Perché: con due pulsanti di salvataggio non si sa quale salva cosa.
Giusto: Copia altrove ha un solo pulsante in fondo, "Salva e collega con Google Drive".
Sbagliato: due pulsanti di salvataggio nello stesso riquadro.

**3.3 L'etichetta di un pulsante è un verbo che dice cosa fa, e dice di cosa parla.**
Perché: "Prima" o "Salva" da soli non dicono cosa succede.
Giusto: "Settimana prima", "Salva i codici", "Avanti: le quattro domande".
Sbagliato: "‹ Prima" accanto a "Oggi" nell'Agenda; "Installa adesso" che invece portava alle domande.

**3.4 Un pulsante non si confonde mai con uno stato.**
Perché: "Attiva" (verbo) accanto ad "ATTIVO" (stato) si legge come un'informazione, non come un comando.
Giusto: "Usa questo tema" sul pulsante, il distintivo "In uso" sul tema scelto.
Sbagliato: in Editor > Temi il pulsante "Attiva" stava accanto al distintivo "ATTIVO" (corretto il 1 novembre 2026).

**3.5 Un pulsante spento si vede spento e dice perché.**
Perché: un pulsante pieno ma spento sembra rotto.
Giusto: opacità 0,45 e la frase "si accendono quando la destinazione è collegata".
Sbagliato: "Manda una copia adesso" spento ma blu pieno.

**3.6 Un pulsante con la sola icona ha un nome in parole.**
Perché: il lettore di schermo legge il simbolo ("trigramma").
Giusto: `aria-label="Menu del pannello"` e `aria-expanded` sul pulsante del menu.
Sbagliato: il pulsante ☰ senza nome; misure e annulla dell'editor solo con le icone.

## 4. Messaggi

**4.1 Un errore dice in parole semplici cosa è successo, cosa fare e dove.** Il testo tecnico va in fondo, fra parentesi.
Perché: chi legge deve poter correggere da solo.
Giusto: "Utente o password del database sbagliati. Controlla in cPanel, voce Database MySQL, il nome esatto
dell'utente... e riscrivi la password." e, in fondo fra parentesi, il messaggio del server.
Sbagliato: "Connessione rifiutata: SQLSTATE[HY000] [1045] Access denied for user..."; "Credenziali non valide.".

**4.2 L'errore sta sotto il campo sbagliato, e in cima c'è il riassunto.**
Perché: con quattro errori solo in cima non si capisce quale campo correggere.
Giusto: bordo rosso di 2 px e frase sotto il campo; in cima "4 campi da sistemare: li trovi segnati in rosso qui sotto".
Sbagliato: tutti gli errori del passo 3 dell'installatore solo in cima.

**4.3 I messaggi si annunciano.** Un errore ha `role="alert"`, una conferma `role="status"`. Un campo sbagliato ha
`aria-invalid` e `aria-describedby` verso il suo messaggio.
Perché: chi non vede lo schermo deve sapere che qualcosa è cambiato (WCAG 4.1.3).
Giusto: il messaggio verde e quello rosso in cima al pannello hanno ora `role`.
Sbagliato: errori della pagina di accesso e dell'installatore che il lettore di schermo non annunciava.

**4.4 Niente parole del codice nell'interfaccia.**
Perché: `preview`, `SQLSTATE` o un indirizzo con `?` non dicono cos'è la cosa.
Giusto: "Header: la testata delle pagine".
Sbagliato: sotto il nome dell'header c'era `/?preview=1&header=1`.

**4.5 Dove uno strumento non funziona, si dice e si dà la strada alternativa.**
Perché: una schermata tagliata senza spiegazione sembra un guasto.
Giusto: al telefono l'editor dice "si usa da computer o tablet; puoi cambiare i testi dalla scheda", con il link.
Sbagliato: la barra dell'editor tagliata a 390 px, senza una parola.

## 5. Contrasti (WCAG 2.2)

**5.1 Testo normale almeno 4,5:1. Testo grande almeno 3:1** (24 px, oppure 18,66 px in grassetto).
**5.2 Bordi di campi, caselle, pallini dei colori e componenti almeno 3:1** sul fondo intorno.
**5.3 Contorno del fuoco almeno 3:1**, sul fondo e sul componente.
Perché: sono le soglie minime per chi vede poco, con il sole sullo schermo, o con uno schermo economico.
Giusto: la data delle schede del blog portata da 3,61 a 7,13:1 in Bottega; la versione nella barra laterale in Bottega
portata da 1,9:1 a pieno contrasto.
Sbagliato: bianco su viola a 3,96:1 nel carrello della Vetrina; il segnaposto a 3,30:1 in Lavagna.
**5.4 I contrasti si misurano, non si stimano a occhio**, in ogni tema, contando anche l'opacità dei genitori.

## 6. Il colore non è mai l'unico segnale

**6.1 Ogni stato detto a colori ha anche un segno o una parola.**
Perché: chi non distingue i colori, o un tema con due colori vicini, perde l'informazione (WCAG 1.4.1).
Giusto: i pallini di Sicurezza > Stato hanno una spunta, "!", una x o una i, e un nome per il lettore di schermo;
nell'editor sotto il nome c'è "Manca il titolo principale (H1)".
Sbagliato: in Bottega il pallino verde e quello grigio erano quasi uguali; l'avviso H1 era solo il nome in arancione;
il pallino del monitor grigio chiaro o scuro senza "solo telefono".

## 7. Telefono

**7.1 Ogni schermata si prova a 390 px e a 768 px.**
**7.2 La pagina non scorre mai di lato.** Una tabella larga scorre dentro il suo riquadro; meglio ancora, sotto 640 px
ogni riga diventa una scheda.
Perché: lo scorrimento di lato nasconde i pulsanti e fa perdere il punto.
Giusto: `minmax(0, 1fr)` nella griglia e `min-width:0` nei contenitori; `fieldset{min-width:0}`.
Sbagliato: 29 pagine del pannello su 70 scorrevano di lato prima della 2.0.1.

**7.3 Bersagli da toccare di almeno 44 x 44 px** (WCAG 2.5.8 chiede 24; i telefoni ne vogliono 44).
Giusto: il pulsante del menu del pannello portato a 44 x 44.

**7.4 La barra in alto non va a capo.** Il titolo lungo si accorcia con i puntini, il resto resta su una riga.
Sbagliato: a 390 px "Aggiornamenti" e il nome dell'utente si sovrapponevano, e la versione andava su due righe.

**7.5 Margine laterale di 16 px al telefono.**
Perché: 32 px per parte su 390 accorciano le righe e mandano tutto a capo.

## 8. Tastiera e lettori di schermo

**8.1 Tutto quello che si fa con il mouse si fa anche con la tastiera** (Tab, Invio, Spazio, frecce nelle schede).
Giusto: i widget della tavolozza hanno `role="button"`, `tabindex="0"` e si aggiungono con Invio.
Sbagliato: i widget erano `div` senza `tabindex`: con la tastiera non si poteva aggiungere niente.

**8.2 Le schede seguono il modello delle schede ARIA**: `role=tablist/tab/tabpanel`, `aria-selected`, frecce.

**8.3 Il primo Tab porta a "Vai al contenuto"** (WCAG 2.4.1).
Sbagliato: prima di arrivare al modulo bisognava attraversare circa 25 voci del menu.

**8.4 Ogni campo ha un nome; ogni aiuto è collegato al suo campo** (`aria-describedby`).

## 9. Testi d'aiuto

**9.1 Corti, vicino al campo, prima della cosa che spiegano.**
Perché: un aiuto in fondo a un elenco lungo non lo legge nessuno.
Giusto: nell'editor "Trascina un widget nella pagina, oppure fai clic" sta sopra la tavolozza.
Sbagliato: lo stesso aiuto stava sotto cento widget.

**9.2 L'aiuto lungo si chiude.** Passi e spiegazioni lunghe vanno in un "Come si fa" richiudibile (`details`).
Sbagliato: cinque paragrafi in carattere piccolissimo sotto "Carica un file" in Download (ora in `.pn-aiuto`).

**9.3 La regola di un campo va nell'aiuto, non nell'etichetta.**
Giusto: etichetta "Password", aiuto "almeno 12 caratteri, non fra le più comuni".
Sbagliato: un'etichetta lunga che va a capo e disallinea le due caselle affiancate.

## 10. Stati vuoti

**10.1 Un elenco vuoto dice cosa fare e dove.**
Perché: "Niente qui." lascia chi legge senza la mossa successiva.
Giusto: "Ancora nessuna categoria: aggiungi la prima con il riquadro qui accanto."; nell'editor "Ancora nessun evento
in programma: aggiungili nel pannello, in Eventi > Nuovo evento".
Sbagliato: "Niente qui." in Categorie; i titoli dei gruppi vuoti dopo una ricerca senza risultati.

**10.2 Una ricerca senza risultati lo dice e suggerisce cosa provare**: la parola cercata, una parola più corta, le
pagine principali.

**10.3 Una ricerca vuota e un elenco vuoto sono due messaggi diversi.**

## 11. Coerenza fra aspetti e temi

**11.1 Ogni colore è una variabile, definita in ogni aspetto o tema, con il contrasto misurato in ognuno.**
Perché: un colore scritto a mano va bene in un tema e sparisce in un altro.
Sbagliato: la versione nella barra laterale usava il grigio del contenuto, illeggibile sulla barra scura di Bottega.

**11.2 La stessa cosa ha lo stesso aspetto in tutte le pagine.** Un componente nuovo usa le classi che ci sono già.

**11.3 Il testo sopra un colore pieno usa la sua variabile** ("testo su accento"), mai un `#fff` scritto a mano.

**11.4 Le correzioni al pannello non toccano il sito dei clienti.** Ogni pixel che cambia sul sito si spiega con le
foto prima e dopo.

## 12. Cosa non si fa mai

| Mai | Errore vero |
|---|---|
| Titoli e etichette con lo stesso stile | SEO > Motori di ricerca, la segnalazione di Danilo |
| `outline: none` senza un sostituto visibile | fuoco invisibile su campi e pulsanti, in pannello, editor e installatore |
| Bordi dei campi del colore dei bordi decorativi | campi a 1,2-1,4:1 in tutti e quattro gli aspetti |
| Solo il colore per dire uno stato | pallini di Sicurezza, avviso H1 arancione, pallino del monitor |
| Messaggi tecnici o in inglese a chi usa | `SQLSTATE[HY000] [1045]`, "Credenziali non valide.", `/?preview=1&header=1` |
| Un `div` cliccabile senza tastiera | widget della tavolozza dell'editor |
| Un pulsante che dice una cosa e ne fa un'altra | "Installa adesso" che porta alle quattro domande |
| Un verbo uguale a uno stato | "Attiva" accanto ad "ATTIVO" |
| Un `display` su un elemento che si nasconde con `hidden`, senza `[hidden]{display:none}` | casella del file visibile nella finestra Immagini, pannello Revisioni che non si chiudeva |
| Stili scritti in linea per i colori dei campi | ricerca di Prenotazioni e campi di Newsletter a 1,16:1 |
| Gestori in linea (`onclick=`, `onchange=`) | la CSP li blocca in silenzio: Sostituisci non partiva |
| Creare dati veri solo aprendo una pagina | "Aggiungi articolo" crea subito una bozza: ne sono rimaste 26 vuote |
| Consegnare una schermata provata solo al computer | barra in alto sovrapposta a 390 px |

---

## In plumeCMS

**File.** Colori in `cms/admin/assets/css/aspetti.css` (un blocco per aspetto: Aria, Bottega, Cosmo, Lavagna). Regole
generali in `cms/admin/assets/css/admin.css` (sezione "UX 2026-11" in fondo). Comportamenti in
`cms/admin/assets/pannello.js` (attributi `data-*`, mai gestori in linea). Editor in `cms/admin/assets/editor/editor.css`
ed `editor.js`. Installatore in `cms/install/install.css`. Sito in `cms/sito/assets/sito.css` e `widget.css`.

**Variabili da usare** (mai un colore scritto a mano, salvo le eccezioni con il commento `fisso`):

| Variabile | A cosa serve |
|---|---|
| `--testata`, `--testata-testo` | fascia del titolo dei riquadri |
| `--su-pericolo` | testo sopra `--errore` pieno (pulsante rosso delle azioni che distruggono) |
| `--bordo-campo` | bordo di campi, tendine, scelta file, pulsanti secondari (almeno 3:1) |
| `--campo` | fondo dei campi |
| `--segnaposto` | testo d'esempio (almeno 4,5:1, più tenue del testo) |
| `--coral`, `--su-accento`, `--accento-testo` | accento, testo sopra l'accento, accento usato come testo |
| `--text`, `--text-muted` | testo, testo secondario (aiuti, date) |
| `--border`, `--bordo-forte` | bordi decorativi, separatori marcati (mai per i campi) |
| `--errore-*`, `--successo-*`, `--attenzione-*`, `--info-*` | messaggi (fondo `-chiaro`, `-testo`, `-bordo`) |
| `--sidebar-*` | tutto quello che sta nella barra laterale |
| `--testo-2` (sito) | testo secondario del sito: date delle schede, risultati della ricerca |

**Classi.**
- Riquadro con fascia: `.form-card` con il titolo `h3` come primo figlio (anche dopo i campi nascosti di un
  `<form class="form-card">`). Altre classi con la fascia: `.seo-card`, `.agg-card`, `.cs-card`, `.db-scatola`,
  `.sc-scatola`, `.mn-scatola`, `.wg-gruppo` e le altre elencate in `admin.css`. Una classe nuova di riquadro dichiara
  `--riq-y` e `--riq-x` (il suo margine interno) e si aggiunge all'elenco.
- Campo: `.form-group` con `label` e il campo; aiuto in `.hint` sotto il campo.
- Pulsanti: `.btn .btn-primary`, `.btn .btn-secondary`, `.btn .btn-danger`, `.btn-sm` per quelli piccoli.
- Messaggi: `flash('success'|'error'|'info', ...)` e `render_flash()` (escono con `role`); nell'installatore gli
  errori per campo con `inst_campo()`, `.campo.sbagliato`, `.errore-campo`, `aria-invalid`.
- Vuoti: `.empty-state` nel pannello; `.ed-vuoto` nell'anteprima dell'editor (nascosto sul sito da `sito.css` e `widget.css`).
- Accessibilità: `.admin-salta` ("Vai al contenuto"), `.sr-solo` per il testo solo per i lettori di schermo.
- Conferme: `data-conferma="..."` su un pulsante, un link o un form (mai `onclick`, mai `confirm()` scritto a mano):
  `pannello.js` apre la finestra del pannello, con il pulsante che ripete l'azione, rosso (`.btn-pericolo`) se distrugge.
  `data-conferma-ok="Elimina il form"` cambia il nome del pulsante, `data-conferma-pericolo` lo fa rosso.
- Pulsante pericoloso pieno: `.btn-pericolo` (fondo `--errore`, testo `--su-pericolo`); `.btn-danger` resta il pulsante
  chiaro con il testo rosso dentro le pagine.
- Aiuto lungo: `<details class="pn-aiuto"><summary>Come funziona...</summary>...</details>`.
- Riquadro che si apre: `<details>` con un `id`; `pagina.php#id` lo apre da solo. Un riquadro spostato lascia il vecchio
  `id` con `data-spostato="nuovo indirizzo"`.
- Tabelle: una testata `<thead>` con almeno tre colonne e righe senza celle unite diventano schede sotto 640 px da sole
  (`data-schede`, `data-etichetta`); `data-no-schede` per una tabella che deve restare tale.
- Modulo accanto all'elenco: `.ut-griglia` (una colonna sotto 760 px). Schede di una pagina: `.imp-schede`.

**Prove.** `prove/prova-ux-pannello.js` (contrasti, bordi, segnaposto, fasce, scelta file, finestra di conferma e 390 px,
nei quattro aspetti),
`prove/prova-ux-editor.js` (editor, installatore, addon, sito), `prove/prova-pannello-telefono.js` (`LARGHEZZA=390` e
`768`), `prove/prova-aspetti.js` (contrasto e colori scritti a mano), `prove/foto-sito.js` con `quanto-foto.py` (il sito
uguale al pixel). Una prova che accetta le conferme usa `prove/conferme-pannello.js` (`pnConferme.on(p, d => d.accept())`).

**Ancora aperti**: l'editor ridotto al telefono (rimandato da Danilo). Gli altri punti dei due controlli sono stati fatti
il 1 novembre 2026 (`ux-ui-2026-11-pannello.md`, "Decisi da Danilo").

---

## Checklist prima di consegnare una schermata

1. I titoli di sezione stanno in una fascia, e le etichette non somigliano ai titoli.
2. Ogni campo ha un'etichetta vera e un bordo almeno 3:1; il segnaposto è un esempio e non sembra un valore.
3. Con Tab arrivo a tutto, vedo sempre dove sono, e Invio o Spazio fanno quello che fa il clic.
4. C'è una sola azione principale per riquadro, con un verbo che dice cosa fa; il pericoloso è rosso.
5. Gli errori dicono cosa fare e dove, stanno sotto il campo e sono annunciati (`role`).
6. Nessuno stato detto solo a colori.
7. Contrasti misurati in ogni tema o aspetto: testo 4,5:1, testo grande e bordi 3:1.
8. A 390 e 768 px niente scorrimento di lato, niente barre a capo, bersagli di 44 px.
9. Ogni elenco vuoto e ogni ricerca senza risultati dicono cosa fare.
10. Nessuna parola del codice, nessun testo in inglese, nessun colore scritto a mano, nessun gestore in linea.
11. Il sito dei clienti è uguale al pixel, oppure ogni differenza è spiegata con le foto.
12. Provato nel browser come lo userebbe Danilo, non con una richiesta che risponde 200.
