# Stile del sito (25 ottobre 2026)

La voce **Pagine > Stile del sito** del pannello (`admin/design.php`, motore `includes/design.php`) raccoglie le
impostazioni che valgono per tutto il sito, come le Site Settings di Elementor: colori globali, caratteri globali,
tipografia per elemento, pulsanti, immagini, campi dei moduli, layout, sfondo, identità del sito, CSS personalizzato.
Solo l'amministratore la vede.

## Come funziona

- I valori stanno nella tabella `newdf_design` (una riga per scheda, JSON; la riga `_precedente` tiene l'insieme
  salvato prima dell'ultimo salvataggio). La tabella si crea da sola alla prima lettura.
- `design_head()` scrive nel `<head>` di ogni pagina, dopo `sito.css`, lo `<style id="g-css">` con le variabili
  `--g-colore-*`, `--g-font-*`, `--g-larghezza`... e il ponte con le variabili di `sito.css`
  (`--viola: var(--g-colore-primario)`, `--ciano`, `--testo`, `--bg`, `--bordo`, `--f-titolo`, `--f-testo`, `--max`).
  Così tutto il foglio di stile segue i globali senza essere toccato.
- **Con i valori di partenza il sito è identico a prima**: i globali ripetono i colori e i caratteri di `sito.css`
  e le regole per elemento (body, p, a, h1..h6, .btn, img, campi) escono solo per i valori che Danilo ha scritto.
  Un campo vuoto vuol dire "come il sito".
- L'anteprima nel pannello è il sito vero dentro un riquadro: a ogni modifica il JavaScript chiede al server il CSS
  (`POST azione=css`, senza salvare) e lo mette nell'iframe. Il sito cambia solo con **Salva**.
- **Ripristina predefiniti** svuota la tabella; **Versione precedente** richiama l'insieme di prima.
- Google Fonts: si caricano da Google (`<link id="g-fonts">`, `display=swap`) solo se una famiglia di Google è
  davvero scelta da qualche parte. Oggi il sito non chiama Google e, senza toccare nulla, continua a non chiamarlo.

## Per chi scrive codice

- `design_valori()` tutti i valori; `design_css()` il CSS; `design_css_valore('globale:primario')` ->
  `var(--g-colore-primario)`; `design_css_valore('Lora', 'font')` -> `"Lora",serif`; `design_globali()` colori e
  font per il pannello dell'editor; `design_sito()` nome, sottotitolo, descrizione, logo, favicon;
  `design_breakpoint('tablet'|'telefono')` le soglie in px.
- Un controllo colore dell'editor salva `globale:<id>` e il CSS generato scrive `var(--g-colore-<id>)`: cambiando il
  globale cambia la pagina. Lo stesso per le famiglie con `globale:font-<id>`.
- Validazione al salvataggio: colori (hex, rgb/rgba, hsl/hsla, transparent, globale), misure con unità, famiglie
  dall'elenco, scelte dalle liste; quello che non passa torna vuoto. Il CSS personalizzato passa intero, senza
  `</style>`.
- Dal 27 ottobre 2026 (temi, fase 1) `sito.css`, `widget.css` e `tema.css` non hanno più colori del sito scritti in
  chiaro: le trasparenze sono `rgba(var(--viola-rgb),.2)` (anche `--ciano-rgb`, `--bg-rgb`, `--inchiostro-rgb`,
  `--errore-rgb`, `--successo-rgb`), i gradienti dei pulsanti `--primario-chiaro/--primario-scuro` e
  `--secondario-chiaro/--secondario-scuro`, poi `--su-secondario`, `--accento`, `--successo*`, `--errore*`,
  `--testo-corpo`, `--inchiostro`. I valori di partenza in `:root` sono quelli di prima: sito identico al pixel
  (20 schermate confrontate con `prove/confronta-foto.py`). Si usa `rgba(var(--x-rgb),a)` e non `color-mix()`, perché
  `color-mix()` fa calcolare le sfumature in un altro spazio colore e cambia i pixel di 1-5 livelli.
- Il ponte di `design_css()` scrive sempre `--viola-rgb`, `--ciano-rgb`, `--bg-rgb` dai globali (`design_rgb()` legge
  hex, rgb, hsl). I gradienti dei pulsanti e `--accento` si ricavano dal globale solo quando è diverso dal predefinito
  (così un sito che non ha toccato i colori resta com'era). Con uno sfondo chiaro `--inchiostro` diventa nero: i vetri
  si vedono grigi invece che bianchi.
- Con un colore del testo diverso dal predefinito, `--testo-corpo` (il testo degli articoli, una sfumatura pensata per lo
  sfondo scuro) segue il testo: prima su uno sfondo chiaro gli articoli erano quasi invisibili (27 ottobre 2026). Con un
  secondario diverso, `--su-secondario` diventa bianco su un secondario scuro e quasi nero su uno chiaro.
- Restano fissi apposta: ombre nere, il bianco dietro le immagini, i colori ufficiali dei social, le stelle, la lightbox,
  le testate colorate della Scheda, la grafica della home di danilofailli (`.finestra`, `.ritratto`, `.chip`), le
  infografiche (`.igx`: dalla fase 4 hanno le loro variabili `--ig-*`, vedi `temi.md`). I colori di partenza scritti nelle impostazioni dei widget
  (per esempio la CTA verde scuro) non sono CSS: li cambia il tema.
