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
- Limite noto: i colori scritti in chiaro dentro `sito.css` (i gradienti dei pulsanti `#38e1f5/#0ea5c6` e
  `#b874ff/#7c3aed`, le sfumature `rgba(168,85,247,...)`) non seguono i globali. La scheda Pulsanti permette di
  imporre uno sfondo unico; il resto si cambia con il CSS personalizzato.
