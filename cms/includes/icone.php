<?php
/**
 * Il set di icone del sito: SVG inline disegnati in casa, stile a tratto (24x24, tratto 2, currentColor).
 * Nessuna libreria esterna, nessuna chiamata di rete: l'icona entra nell'HTML come <svg>.
 *
 *   icone_elenco()               id => ['nome' => 'Nome italiano', 'cat' => 'categoria']
 *   icone_categorie()            chiave => etichetta italiana
 *   icona_svg($id, $classe = '') l'SVG dell'icona (stringa vuota se l'id non esiste)
 *   icona_esiste($id)
 *
 * I tracciati sono scritti per il riquadro 0 0 24 24. Le icone social sono riempite (fill) perché il loro
 * segno non ha senso a filo; tutte le altre sono a tratto. Il colore viene da currentColor: lo decide il CSS.
 */

const ICONE_SVG_APRI = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"';
const ICONE_SVG_APRI_PIENO = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" stroke="none" aria-hidden="true" focusable="false"';

function icone_categorie(): array {
    return [
        'frecce' => 'Frecce',
        'segni' => 'Spunte e segni',
        'interfaccia' => 'Interfaccia',
        'comunicazione' => 'Comunicazione',
        'social' => 'Social',
        'lavoro' => 'Lavoro e coaching',
        'persone' => 'Persone',
        'tempo' => 'Tempo',
        'luoghi' => 'Luoghi',
        'documenti' => 'Documenti',
        'media' => 'Media',
        'natura' => 'Natura',
        'forme' => 'Forme',
        'dati' => 'Dati e grafici',
    ];
}

/** Tutte le icone: id => [nome, categoria, tracciati SVG, pieno?]. */
function icone_dati(): array {
    static $d = null;
    if ($d !== null) return $d;
    $d = [
        // ---- frecce
        'freccia-destra' => ['Freccia a destra', 'frecce', '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>'],
        'freccia-sinistra' => ['Freccia a sinistra', 'frecce', '<path d="M19 12H5"/><path d="m11 18-6-6 6-6"/>'],
        'freccia-su' => ['Freccia in su', 'frecce', '<path d="M12 19V5"/><path d="m6 11 6-6 6 6"/>'],
        'freccia-giu' => ['Freccia in giu', 'frecce', '<path d="M12 5v14"/><path d="m18 13-6 6-6-6"/>'],
        'freccia-alto-destra' => ['Freccia in alto a destra', 'frecce', '<path d="M7 17 17 7"/><path d="M8 7h9v9"/>'],
        'freccia-basso-destra' => ['Freccia in basso a destra', 'frecce', '<path d="m7 7 10 10"/><path d="M17 8v9H8"/>'],
        'freccia-tonda-destra' => ['Freccia in un cerchio', 'frecce', '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/><path d="m12 8 4 4-4 4"/>'],
        'freccia-indietro' => ['Freccia indietro', 'frecce', '<path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/>'],
        'freccia-ricarica' => ['Ricarica', 'frecce', '<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/>'],
        'frecce-scambia' => ['Scambia', 'frecce', '<path d="m16 3 4 4-4 4"/><path d="M20 7H4"/><path d="m8 21-4-4 4-4"/><path d="M4 17h16"/>'],
        'chevron-destra' => ['Angolo a destra', 'frecce', '<path d="m9 6 6 6-6 6"/>'],
        'chevron-sinistra' => ['Angolo a sinistra', 'frecce', '<path d="m15 6-6 6 6 6"/>'],
        'chevron-su' => ['Angolo in su', 'frecce', '<path d="m6 15 6-6 6 6"/>'],
        'chevron-giu' => ['Angolo in giu', 'frecce', '<path d="m6 9 6 6 6-6"/>'],
        'chevron-doppio-destra' => ['Doppio angolo a destra', 'frecce', '<path d="m6 17 5-5-5-5"/><path d="m13 17 5-5-5-5"/>'],
        'chevron-doppio-giu' => ['Doppio angolo in giu', 'frecce', '<path d="m7 6 5 5 5-5"/><path d="m7 13 5 5 5-5"/>'],
        'espandi' => ['Espandi', 'frecce', '<path d="M8 3H3v5"/><path d="M21 8V3h-5"/><path d="M3 16v5h5"/><path d="M16 21h5v-5"/>'],
        'riduci' => ['Riduci', 'frecce', '<path d="M4 14h6v6"/><path d="M20 10h-6V4"/><path d="m14 10 7-7"/><path d="m3 21 7-7"/>'],
        'torna-su' => ['Torna su', 'frecce', '<path d="M5 4h14"/><path d="M12 20V8"/><path d="m6 14 6-6 6 6"/>'],
        'entra' => ['Entra', 'frecce', '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/>'],
        'esci' => ['Esci', 'frecce', '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>'],

        // ---- segni
        'spunta' => ['Spunta', 'segni', '<path d="m5 12 5 5L20 7"/>'],
        'spunta-cerchio' => ['Spunta in un cerchio', 'segni', '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 5-5"/>'],
        'spunta-quadrato' => ['Spunta in un quadrato', 'segni', '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="m8.5 12 2.5 2.5 5-5"/>'],
        'spunta-doppia' => ['Doppia spunta', 'segni', '<path d="m2 12 5 5 9-9"/><path d="m13 16 1 1 8-8"/>'],
        'croce' => ['Croce', 'segni', '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>'],
        'croce-cerchio' => ['Croce in un cerchio', 'segni', '<circle cx="12" cy="12" r="9"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>'],
        'piu' => ['Piu', 'segni', '<path d="M12 5v14"/><path d="M5 12h14"/>'],
        'meno' => ['Meno', 'segni', '<path d="M5 12h14"/>'],
        'piu-cerchio' => ['Piu in un cerchio', 'segni', '<circle cx="12" cy="12" r="9"/><path d="M12 8v8"/><path d="M8 12h8"/>'],
        'meno-cerchio' => ['Meno in un cerchio', 'segni', '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>'],
        'uguale' => ['Uguale', 'segni', '<path d="M5 9h14"/><path d="M5 15h14"/>'],
        'percento' => ['Percentuale', 'segni', '<path d="M19 5 5 19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>'],
        'asterisco' => ['Asterisco', 'segni', '<path d="M12 4v16"/><path d="m5 8 14 8"/><path d="m5 16 14-8"/>'],
        'domanda' => ['Punto di domanda', 'segni', '<circle cx="12" cy="12" r="9"/><path d="M9.3 9.5a2.8 2.8 0 0 1 5.4.9c0 1.9-2.7 2.2-2.7 4"/><path d="M12 17.5h.01"/>'],
        'esclamazione' => ['Punto esclamativo', 'segni', '<circle cx="12" cy="12" r="9"/><path d="M12 7v6"/><path d="M12 17h.01"/>'],
        'info' => ['Informazione', 'segni', '<circle cx="12" cy="12" r="9"/><path d="M12 11v6"/><path d="M12 7h.01"/>'],
        'avviso' => ['Avviso (triangolo)', 'segni', '<path d="M10.3 4.3 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>'],
        'divieto' => ['Divieto', 'segni', '<circle cx="12" cy="12" r="9"/><path d="m5.5 5.5 13 13"/>'],
        'citazione' => ['Virgolette', 'segni', '<path d="M4 15a3 3 0 0 0 3-3V8H4v4"/><path d="M4 12a3 3 0 0 1 3-3"/><path d="M14 15a3 3 0 0 0 3-3V8h-3v4"/><path d="M14 12a3 3 0 0 1 3-3"/>'],
        'citazione-piena' => ['Virgolette piene', 'segni', '<path d="M6 17c3 0 5-2 5-6V6H5v6h3c0 1.7-1 3-3 3v2z"/><path d="M15 17c3 0 5-2 5-6V6h-6v6h3c0 1.7-1 3-3 3v2z"/>', true],
        'cancelletto' => ['Cancelletto', 'segni', '<path d="M4 9h16"/><path d="M4 15h16"/><path d="M10 3 8 21"/><path d="M16 3l-2 18"/>'],
        'chiocciola' => ['Chiocciola', 'segni', '<circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/>'],

        // ---- interfaccia
        'menu' => ['Menu (tre righe)', 'interfaccia', '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>'],
        'punti' => ['Tre punti', 'interfaccia', '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>'],
        'griglia' => ['Griglia', 'interfaccia', '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>'],
        'elenco' => ['Elenco', 'interfaccia', '<path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/>'],
        'cerca' => ['Cerca', 'interfaccia', '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>'],
        'zoom-piu' => ['Ingrandisci', 'interfaccia', '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/><path d="M11 8v6"/><path d="M8 11h6"/>'],
        'filtro' => ['Filtro', 'interfaccia', '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>'],
        'impostazioni' => ['Impostazioni', 'interfaccia', '<circle cx="12" cy="12" r="3"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.9 4.9 1.4 1.4"/><path d="m17.7 17.7 1.4 1.4"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m4.9 19.1 1.4-1.4"/><path d="m17.7 6.3 1.4-1.4"/>'],
        'cursori' => ['Regolazioni', 'interfaccia', '<path d="M4 6h10"/><path d="M18 6h2"/><circle cx="16" cy="6" r="2"/><path d="M4 12h2"/><path d="M10 12h10"/><circle cx="8" cy="12" r="2"/><path d="M4 18h8"/><path d="M16 18h4"/><circle cx="14" cy="18" r="2"/>'],
        'download' => ['Scarica', 'interfaccia', '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>'],
        'upload' => ['Carica', 'interfaccia', '<path d="M12 15V3"/><path d="m7 8 5-5 5 5"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>'],
        'link' => ['Link', 'interfaccia', '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>'],
        'link-esterno' => ['Link esterno', 'interfaccia', '<path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>'],
        'stella' => ['Stella', 'interfaccia', '<path d="m12 3 2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.4l-5.7 3.1 1.2-6.4L2.8 9.7l6.4-.8z"/>'],
        'stella-piena' => ['Stella piena', 'interfaccia', '<path d="m12 2.5 3 6.3 6.9.9-5.1 4.7 1.3 6.9L12 18l-6.1 3.3 1.3-6.9L2.1 9.7 9 8.8z"/>', true],
        'stella-mezza' => ['Mezza stella', 'interfaccia', '<path d="m12 3 2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.4l-5.7 3.1 1.2-6.4L2.8 9.7l6.4-.8z"/><path d="M12 3v14.4l-5.7 3.1 1.2-6.4L2.8 9.7l6.4-.8z" fill="currentColor" stroke="none"/>'],
        'cuore' => ['Cuore', 'interfaccia', '<path d="M12 20.5s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.8a4.3 4.3 0 0 1 7.5 2.7c0 5.4-7.5 10-7.5 10z"/>'],
        'cuore-pieno' => ['Cuore pieno', 'interfaccia', '<path d="M12 21s-8-5-8-10.5A4.6 4.6 0 0 1 12 7.6a4.6 4.6 0 0 1 8 2.9C20 16 12 21 12 21z"/>', true],
        'pollice-su' => ['Pollice in su', 'interfaccia', '<path d="M7 11v9H4a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1z"/><path d="M7 11l4-7a2 2 0 0 1 3 2v4h5a2 2 0 0 1 2 2.3l-1 6a2 2 0 0 1-2 1.7H7"/>'],
        'pollice-giu' => ['Pollice in giu', 'interfaccia', '<path d="M7 13V4H4a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1z"/><path d="M7 13l4 7a2 2 0 0 0 3-2v-4h5a2 2 0 0 0 2-2.3l-1-6A2 2 0 0 0 18 4H7"/>'],
        'segnalibro' => ['Segnalibro', 'interfaccia', '<path d="M6 3h12v18l-6-4-6 4z"/>'],
        'etichetta' => ['Etichetta', 'interfaccia', '<path d="M3 3h8l10 10-8 8L3 11z"/><path d="M7.5 7.5h.01"/>'],
        'campanella' => ['Campanella', 'interfaccia', '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 21a2 2 0 0 0 4 0"/>'],
        'occhio' => ['Occhio', 'interfaccia', '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>'],
        'occhio-chiuso' => ['Occhio chiuso', 'interfaccia', '<path d="M3 3l18 18"/><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M6.6 6.7C3.8 8.4 2 12 2 12s3.5 7 10 7c1.9 0 3.5-.5 4.8-1.2"/><path d="M9.9 5.2C10.6 5.1 11.3 5 12 5c6.5 0 10 7 10 7s-.9 1.8-2.7 3.6"/>'],
        'matita' => ['Matita', 'interfaccia', '<path d="m16 3 5 5L8 21H3v-5z"/><path d="m13 6 5 5"/>'],
        'cestino' => ['Cestino', 'interfaccia', '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/><path d="M10 11v6"/><path d="M14 11v6"/>'],
        'copia' => ['Copia', 'interfaccia', '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>'],
        'condividi' => ['Condividi', 'interfaccia', '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4"/><path d="m15.4 6.5-6.8 4"/>'],
        'casa' => ['Casa', 'interfaccia', '<path d="m3 11 9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/>'],
        'cuore-casa' => ['Casa con cuore', 'interfaccia', '<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M12 17s-3-1.9-3-4a1.7 1.7 0 0 1 3-1 1.7 1.7 0 0 1 3 1c0 2.1-3 4-3 4z"/>'],
        'lucchetto' => ['Lucchetto', 'interfaccia', '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>'],
        'lucchetto-aperto' => ['Lucchetto aperto', 'interfaccia', '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.5-2"/>'],
        'chiave' => ['Chiave', 'interfaccia', '<circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 9.2-9.2"/><path d="m16 7 3 3"/>'],
        'scudo' => ['Scudo', 'interfaccia', '<path d="M12 3 4 6v6c0 4.5 3.4 7.9 8 9 4.6-1.1 8-4.5 8-9V6z"/>'],
        'scudo-spunta' => ['Scudo con spunta', 'interfaccia', '<path d="M12 3 4 6v6c0 4.5 3.4 7.9 8 9 4.6-1.1 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/>'],
        'carrello' => ['Carrello', 'interfaccia', '<circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M2 3h3l2.7 12.4a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 2-1.6L22 7H6"/>'],
        'regalo' => ['Regalo', 'interfaccia', '<rect x="3" y="8" width="18" height="4"/><path d="M5 12v8h14v-8"/><path d="M12 8v12"/><path d="M12 8a3 3 0 0 1-3-3 1.5 1.5 0 0 1 3 0z"/><path d="M12 8a3 3 0 0 0 3-3 1.5 1.5 0 0 0-3 0z"/>'],
        'borsa' => ['Borsa', 'interfaccia', '<path d="M5 8h14l-1 13H6z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>'],
        'carta' => ['Carta di pagamento', 'interfaccia', '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/>'],
        'euro' => ['Euro', 'interfaccia', '<path d="M18 6a7 7 0 1 0 0 12"/><path d="M3 10h11"/><path d="M3 14h11"/>'],
        'puzzle' => ['Puzzle', 'interfaccia', '<path d="M10 3h4v3a2 2 0 0 0 4 0h3v4h-3a2 2 0 0 0 0 4h3v4h-4v-3a2 2 0 0 0-4 0v3H6v-4H3v-4h3a2 2 0 0 0 0-4H3V6h7z"/>'],
        'bacchetta' => ['Bacchetta magica', 'interfaccia', '<path d="m3 21 12-12"/><path d="m15 9 2.5 2.5"/><path d="M17 3v2"/><path d="M21 7h-2"/><path d="m19.5 4.5-1.4 1.4"/><path d="M11 5v2"/><path d="M8 8H6"/>'],
        'accessibilita' => ['Accessibilita', 'interfaccia', '<circle cx="12" cy="4.5" r="1.5"/><path d="M5 9h14"/><path d="M12 9v5"/><path d="m8 21 4-7 4 7"/>'],

        // ---- comunicazione
        'email' => ['Email', 'comunicazione', '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>'],
        'email-aperta' => ['Email aperta', 'comunicazione', '<path d="m3 9 9-6 9 6v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="m3 9 9 6 9-6"/>'],
        'telefono' => ['Telefono', 'comunicazione', '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z"/>'],
        'telefono-chiamata' => ['Telefono che chiama', 'comunicazione', '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z"/><path d="M15 3a6 6 0 0 1 6 6"/><path d="M15 7a2 2 0 0 1 2 2"/>'],
        'cellulare' => ['Cellulare', 'comunicazione', '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M12 18h.01"/>'],
        'messaggio' => ['Messaggio', 'comunicazione', '<path d="M21 12a8 8 0 0 1-8 8H5l-2 2V12a8 8 0 0 1 8-8h2a8 8 0 0 1 8 8z"/>'],
        'messaggi' => ['Conversazione', 'comunicazione', '<path d="M14 9a5 5 0 0 1-5 5H5l-3 3V9a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5z"/><path d="M14 11h1a5 5 0 0 1 5 5v5l-3-3h-3a5 5 0 0 1-4.6-3"/>'],
        'messaggio-punti' => ['Messaggio con punti', 'comunicazione', '<path d="M21 12a8 8 0 0 1-8 8H5l-2 2V12a8 8 0 0 1 8-8h2a8 8 0 0 1 8 8z"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/>'],
        'invia' => ['Invia', 'comunicazione', '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>'],
        'megafono' => ['Megafono', 'comunicazione', '<path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1z"/><path d="M15 9a4 4 0 0 1 0 6"/><path d="M18 6a8 8 0 0 1 0 12"/><path d="m6 14 1 6h3"/>'],
        'altoparlante' => ['Altoparlante', 'comunicazione', '<path d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/>'],
        'microfono' => ['Microfono', 'comunicazione', '<rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0"/><path d="M12 18v4"/><path d="M8 22h8"/>'],
        'cuffie' => ['Cuffie', 'comunicazione', '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="5" height="6" rx="2"/><rect x="16" y="14" width="5" height="6" rx="2"/>'],
        'video-chiamata' => ['Videochiamata', 'comunicazione', '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3"/>'],
        'rss' => ['Feed RSS', 'comunicazione', '<path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/>'],
        'wifi' => ['Wifi', 'comunicazione', '<path d="M2 9a16 16 0 0 1 20 0"/><path d="M5 12.5a11 11 0 0 1 14 0"/><path d="M8.5 16a6 6 0 0 1 7 0"/><path d="M12 20h.01"/>'],
        'globo' => ['Globo', 'comunicazione', '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/>'],

        // ---- social (riempite)
        'facebook' => ['Facebook', 'social', '<path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.2c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12z"/>', true],
        'instagram' => ['Instagram', 'social', '<path d="M12 2.2c3.2 0 3.6 0 4.8.1 3.2.1 4.8 1.7 4.9 4.9.1 1.2.1 1.6.1 4.8s0 3.6-.1 4.8c-.1 3.2-1.7 4.8-4.9 4.9-1.2.1-1.6.1-4.8.1s-3.6 0-4.8-.1c-3.3-.1-4.8-1.7-4.9-4.9C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.8C2.4 4 4 2.4 7.2 2.3c1.2-.1 1.6-.1 4.8-.1zM12 4c-3.1 0-3.5 0-4.7.1-2.2.1-3.2 1.1-3.3 3.3C4 8.5 4 8.9 4 12s0 3.5.1 4.7c.1 2.2 1.1 3.2 3.3 3.3 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c2.2-.1 3.2-1.1 3.3-3.3.1-1.2.1-1.6.1-4.7s0-3.5-.1-4.7c-.1-2.2-1.1-3.2-3.3-3.3C15.5 4 15.1 4 12 4zm0 2.9a5.1 5.1 0 1 1 0 10.2 5.1 5.1 0 0 1 0-10.2zm0 1.8a3.3 3.3 0 1 0 0 6.6 3.3 3.3 0 0 0 0-6.6zm5.3-3.2a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4z"/>', true],
        'linkedin' => ['LinkedIn', 'social', '<path d="M20.4 2H3.6A1.6 1.6 0 0 0 2 3.6v16.8A1.6 1.6 0 0 0 3.6 22h16.8a1.6 1.6 0 0 0 1.6-1.6V3.6A1.6 1.6 0 0 0 20.4 2zM8 19H5V9.5h3V19zM6.5 8.2a1.7 1.7 0 1 1 0-3.5 1.7 1.7 0 0 1 0 3.5zM19 19h-3v-4.6c0-1.1 0-2.5-1.5-2.5s-1.8 1.2-1.8 2.4V19h-3V9.5h2.9v1.3c.4-.8 1.4-1.5 2.8-1.5 3 0 3.6 2 3.6 4.6V19z"/>', true],
        'whatsapp' => ['WhatsApp', 'social', '<path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.4.1-.2 0-.3 0-.5l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 12 12 0 0 0 4.6 4c1.7.7 2.1.6 2.8.5a2.4 2.4 0 0 0 1.6-1.1c.2-.6.2-1 .1-1.1l-.5-.3z"/>', true],
        'youtube' => ['YouTube', 'social', '<path d="M23 7.2a2.9 2.9 0 0 0-2-2C19.2 4.7 12 4.7 12 4.7s-7.2 0-9 .5a2.9 2.9 0 0 0-2 2C.5 9 .5 12 .5 12s0 3 .5 4.8a2.9 2.9 0 0 0 2 2c1.8.5 9 .5 9 .5s7.2 0 9-.5a2.9 2.9 0 0 0 2-2c.5-1.8.5-4.8.5-4.8s0-3-.5-4.8zM9.7 15.1V8.9l6 3.1-6 3.1z"/>', true],
        'x-social' => ['X (Twitter)', 'social', '<path d="M17.8 3h3.1l-6.8 7.8L22 21h-6.3l-4.9-6.4L5.2 21H2.1l7.3-8.3L1.8 3h6.4l4.4 5.9L17.8 3zm-1.1 16.2h1.7L7.4 4.7H5.6l11.1 14.5z"/>', true],
        'telegram' => ['Telegram', 'social', '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm4.6 6.9-1.6 7.7c-.1.6-.5.7-.9.4l-2.5-1.9-1.2 1.2c-.1.1-.3.3-.5.3l.2-2.6 4.7-4.2c.2-.2 0-.3-.3-.1l-5.8 3.6-2.5-.8c-.5-.2-.5-.5.1-.8l9.8-3.8c.5-.1.9.1.5 1z"/>', true],
        'tiktok' => ['TikTok', 'social', '<path d="M16.6 5.8A4.3 4.3 0 0 1 15.5 3h-3.2v12.4a2.6 2.6 0 1 1-1.8-2.5V9.6a5.8 5.8 0 1 0 5 5.8V9a7.4 7.4 0 0 0 4.3 1.4V7.2a4.3 4.3 0 0 1-3.2-1.4z"/>', true],
        'pinterest' => ['Pinterest', 'social', '<path d="M12 2a10 10 0 0 0-3.6 19.3c-.1-.8-.2-2 0-2.9l1.2-5s-.3-.6-.3-1.5c0-1.4.8-2.4 1.8-2.4.9 0 1.3.6 1.3 1.4 0 .9-.5 2.1-.8 3.3-.2 1 .5 1.8 1.5 1.8 1.8 0 3.1-1.9 3.1-4.5 0-2.4-1.7-4-4.2-4-2.8 0-4.5 2.1-4.5 4.3 0 .9.3 1.8.8 2.3.1.1.1.2.1.3l-.3 1.1c0 .2-.1.2-.3.1-1.3-.6-2-2.4-2-3.9 0-3.2 2.3-6.1 6.6-6.1 3.5 0 6.2 2.5 6.2 5.8 0 3.4-2.2 6.2-5.2 6.2-1 0-2-.5-2.3-1.1l-.6 2.4c-.2.9-.8 2-1.2 2.6A10 10 0 1 0 12 2z"/>', true],
        'spotify' => ['Spotify', 'social', '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm4.6 14.4a.6.6 0 0 1-.9.2c-2.4-1.5-5.4-1.8-8.9-1a.6.6 0 1 1-.3-1.2c3.9-.9 7.2-.5 9.8 1.1.3.2.4.6.3.9zm1.2-2.7a.8.8 0 0 1-1.1.3c-2.7-1.7-6.9-2.2-10.1-1.2a.8.8 0 1 1-.5-1.5c3.7-1.1 8.3-.6 11.4 1.3.4.2.5.7.3 1.1zm.1-2.9c-3.3-2-8.7-2.1-11.8-1.2a.9.9 0 1 1-.5-1.8c3.6-1.1 9.6-.9 13.3 1.4a.9.9 0 1 1-1 1.6z"/>', true],
        'github' => ['GitHub', 'social', '<path d="M12 2a10 10 0 0 0-3.2 19.5c.5.1.7-.2.7-.5v-1.8c-2.8.6-3.4-1.3-3.4-1.3-.4-1.2-1.1-1.5-1.1-1.5-.9-.6.1-.6.1-.6 1 .1 1.5 1 1.5 1 .9 1.5 2.3 1.1 2.9.8.1-.6.3-1.1.6-1.3-2.2-.3-4.6-1.1-4.6-5 0-1.1.4-2 1-2.7-.1-.2-.4-1.3.1-2.6 0 0 .8-.3 2.8 1a9.5 9.5 0 0 1 5 0c1.9-1.3 2.8-1 2.8-1 .5 1.4.2 2.4.1 2.6.6.7 1 1.6 1 2.7 0 3.9-2.4 4.7-4.6 5 .4.3.7.9.7 1.9v2.8c0 .3.2.6.7.5A10 10 0 0 0 12 2z"/>', true],

        // ---- lavoro e coaching
        'bersaglio' => ['Bersaglio', 'lavoro', '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>'],
        'lampadina' => ['Lampadina', 'lavoro', '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M8.5 14a6 6 0 1 1 7 0c-.8.7-1.5 1.6-1.5 3h-4c0-1.4-.7-2.3-1.5-3z"/>'],
        'cervello' => ['Cervello', 'lavoro', '<path d="M12 5a3 3 0 0 0-5.5 1.6A3 3 0 0 0 5 12a3 3 0 0 0 1 5.8A3 3 0 0 0 12 19"/><path d="M12 5a3 3 0 0 1 5.5 1.6A3 3 0 0 1 19 12a3 3 0 0 1-1 5.8A3 3 0 0 1 12 19"/><path d="M12 5v14"/><path d="M9 9a2 2 0 0 0 3 1"/><path d="M15 13a2 2 0 0 1-3 1"/>'],
        'scala' => ['Scala', 'lavoro', '<path d="M8 3v18"/><path d="M16 3v18"/><path d="M8 7h8"/><path d="M8 12h8"/><path d="M8 17h8"/>'],
        'scalini' => ['Scalini', 'lavoro', '<path d="M3 21h4v-4h4v-4h4V9h4V5h2"/>'],
        'bussola' => ['Bussola', 'lavoro', '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>'],
        'mano' => ['Mano', 'lavoro', '<path d="M8 12V5a1.5 1.5 0 0 1 3 0v6"/><path d="M11 10V4a1.5 1.5 0 0 1 3 0v7"/><path d="M14 11V6a1.5 1.5 0 0 1 3 0v8a6 6 0 0 1-6 6h-1a6 6 0 0 1-5-2.7L3 13.5a1.4 1.4 0 0 1 2.3-1.6L8 14"/>'],
        'stretta-di-mano' => ['Stretta di mano', 'lavoro', '<path d="m11 17 2 2a1.5 1.5 0 0 0 2.1-2.1"/><path d="m14 14 2.5 2.5a1.5 1.5 0 0 0 2.1-2.1l-4.4-4.4a2 2 0 0 0-2.8 0L10 11.4a1.5 1.5 0 0 1-2.1-2.1L11 6.2a4 4 0 0 1 5.6 0L21 10.5"/><path d="m21 3-3 3"/><path d="M3 3l3 3"/><path d="M3 10.5 7.5 15a1.5 1.5 0 0 0 2.1-2.1"/>'],
        'bilancia' => ['Bilancia', 'lavoro', '<path d="M12 3v18"/><path d="M8 21h8"/><path d="M4 7h16"/><path d="m6 7-3 7a3 3 0 0 0 6 0z"/><path d="m18 7-3 7a3 3 0 0 0 6 0z"/>'],
        'trofeo' => ['Trofeo', 'lavoro', '<path d="M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4a3 3 0 0 0 3 4"/><path d="M17 6h3a3 3 0 0 1-3 4"/><path d="M12 14v4"/><path d="M8 21h8"/><path d="M9 18h6v3H9z"/>'],
        'medaglia' => ['Medaglia', 'lavoro', '<circle cx="12" cy="15" r="5"/><path d="m8.5 11 -3-8h5l2 5"/><path d="m15.5 11 3-8h-5l-2 5"/>'],
        'coccarda' => ['Coccarda', 'lavoro', '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-3 5 3-1.5-7"/>'],
        'fiamma' => ['Fiamma', 'lavoro', '<path d="M12 22c4 0 7-3 7-7 0-3-1.5-5-3-7-.5 2-1.5 3-2.5 3.5C13 8 12.5 5 10 2c0 4-3 6-4 9-.6 1.8-1 3.5-1 4 0 4 3 7 7 7z"/>'],
        'fulmine' => ['Fulmine', 'lavoro', '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>'],
        'razzo' => ['Razzo', 'lavoro', '<path d="M12 15c-2 0-4-1-5-2 1-6 5-10 11-11-1 6-5 10-6 13z"/><path d="M7 13 4 16c1.5 0 3 .5 4 1.5s1.5 2.5 1.5 4l3-3"/><circle cx="14" cy="10" r="1.5"/>'],
        'diamante' => ['Diamante', 'lavoro', '<path d="M6 3h12l4 6-10 12L2 9z"/><path d="M2 9h20"/><path d="m8 9 4 12 4-12"/><path d="m6 3 2 6 4-6 4 6 2-6"/>'],
        'corona' => ['Corona', 'lavoro', '<path d="m3 17 1-10 5 4 3-7 3 7 5-4 1 10z"/><path d="M3 20h18"/>'],
        'valigetta' => ['Valigetta', 'lavoro', '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/>'],
        'scrivania' => ['Scrivania', 'lavoro', '<path d="M2 9h20"/><path d="M4 9v11"/><path d="M20 9v11"/><path d="M4 14h6v6"/><path d="M4 17h6"/><rect x="8" y="4" width="8" height="5" rx="1"/>'],
        'computer' => ['Computer', 'lavoro', '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8"/><path d="M12 16v4"/>'],
        'edificio' => ['Edificio', 'lavoro', '<rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 7h2"/><path d="M13 7h2"/><path d="M9 11h2"/><path d="M13 11h2"/><path d="M9 15h2"/><path d="M13 15h2"/><path d="M10 21v-3h4v3"/>'],
        'orologio-lavoro' => ['Orario di lavoro', 'lavoro', '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2"/><path d="M9 2h6"/>'],
        'ingranaggi' => ['Ingranaggi', 'lavoro', '<circle cx="9" cy="9" r="2.5"/><path d="M9 3v2"/><path d="M9 13v2"/><path d="M3 9h2"/><path d="M13 9h2"/><path d="m4.8 4.8 1.4 1.4"/><path d="m11.8 11.8 1.4 1.4"/><path d="m4.8 13.2 1.4-1.4"/><path d="m11.8 6.2 1.4-1.4"/><circle cx="17" cy="17" r="2"/><path d="M17 13v1.5"/><path d="M17 19.5V21"/><path d="M13 17h1.5"/><path d="M19.5 17H21"/>'],
        'lista-spunte' => ['Lista con spunte', 'lavoro', '<path d="m3 6 1.5 1.5L7 5"/><path d="m3 12 1.5 1.5L7 11"/><path d="m3 18 1.5 1.5L7 17"/><path d="M10 6h11"/><path d="M10 12h11"/><path d="M10 18h11"/>'],
        'blocco-note' => ['Blocco note', 'lavoro', '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6"/><path d="M9 11h6"/><path d="M9 15h4"/>'],
        'bandiera' => ['Bandiera', 'lavoro', '<path d="M5 21V4"/><path d="M5 4h12l-2 4 2 4H5"/>'],
        'bandiera-traguardo' => ['Traguardo', 'lavoro', '<path d="M5 21V3"/><path d="M5 3h14v10H5"/><path d="M5 8h14"/><path d="M9.7 3v10"/><path d="M14.3 3v10"/>'],
        'chiave-inglese' => ['Chiave inglese', 'lavoro', '<path d="M14.7 6.3a4 4 0 0 0 5 5L9.3 21.7a2 2 0 0 1-3-3L16.7 8.3"/><path d="M14.7 6.3a4 4 0 0 1 5-2.8L17 6.2l.8.8 2.7-2.7a4 4 0 0 1-.8 6.3"/>'],
        'martello' => ['Martello', 'lavoro', '<path d="m14 10-9.5 9.5a1.5 1.5 0 0 0 2 2L16 12"/><path d="m12 8 4-4 2 2 3 1-5 5-1-3z"/>'],
        'cuore-mano' => ['Cura', 'lavoro', '<path d="M12 11s-4-2.5-4-5.5A2.3 2.3 0 0 1 12 4a2.3 2.3 0 0 1 4 1.5c0 3-4 5.5-4 5.5z"/><path d="M3 16h3l3 2h5a1.5 1.5 0 0 0 0-3H9"/><path d="M14 15h2l3-3a1.5 1.5 0 0 1 2 2l-4 4a3 3 0 0 1-2 1H6l-3 1"/>'],
        'equilibrio' => ['Equilibrio', 'lavoro', '<circle cx="12" cy="5" r="2"/><path d="M4 12c3 2 13 2 16 0"/><path d="M12 7v12"/><path d="M8 21h8"/>'],
        'respiro' => ['Respiro', 'lavoro', '<path d="M12 21c-5 0-8-3-8-7 0-5 4-8 8-11 4 3 8 6 8 11 0 4-3 7-8 7z"/><path d="M12 21V8"/><path d="M12 13c-2-1-4-3-4-5"/><path d="M12 16c2-1 4-3 4-5"/>'],
        'onda' => ['Onda', 'lavoro', '<path d="M2 12c2-3 4-3 6 0s4 3 6 0 4-3 6 0"/><path d="M2 18c2-3 4-3 6 0s4 3 6 0 4-3 6 0"/><path d="M2 6c2-3 4-3 6 0s4 3 6 0 4-3 6 0"/>'],
        'ponte' => ['Ponte', 'lavoro', '<path d="M2 18V9"/><path d="M22 18V9"/><path d="M2 12c3-5 7-6 10-6s7 1 10 6"/><path d="M2 18h20"/><path d="M7 9v9"/><path d="M12 6v12"/><path d="M17 9v9"/>'],

        // ---- persone
        'utente' => ['Utente', 'persone', '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'],
        'utenti' => ['Gruppo di persone', 'persone', '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0 1 14 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7"/><path d="M18 14a6 6 0 0 1 4 6"/>'],
        'utente-spunta' => ['Utente con spunta', 'persone', '<circle cx="10" cy="8" r="4"/><path d="M2 21a8 8 0 0 1 13-6"/><path d="m16 19 2 2 4-4"/>'],
        'utente-piu' => ['Aggiungi utente', 'persone', '<circle cx="10" cy="8" r="4"/><path d="M2 21a8 8 0 0 1 13-6"/><path d="M19 15v6"/><path d="M16 18h6"/>'],
        'utente-cerchio' => ['Utente in un cerchio', 'persone', '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="10" r="3"/><path d="M6.5 18.5a6 6 0 0 1 11 0"/>'],
        'sorriso' => ['Sorriso', 'persone', '<circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/>'],
        'neutro' => ['Faccia neutra', 'persone', '<circle cx="12" cy="12" r="9"/><path d="M8 15h8"/><path d="M9 9h.01"/><path d="M15 9h.01"/>'],
        'triste' => ['Faccia triste', 'persone', '<circle cx="12" cy="12" r="9"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><path d="M9 9h.01"/><path d="M15 9h.01"/>'],
        'cravatta' => ['Cravatta', 'persone', '<path d="m9 3 3 3 3-3"/><path d="m10 6-1 4 3 11 3-11-1-4z"/>'],
        'laurea' => ['Tocco di laurea', 'persone', '<path d="m2 9 10-5 10 5-10 5z"/><path d="M6 11v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/><path d="M22 9v6"/>'],
        'braccio' => ['Forza', 'persone', '<path d="M5 20a4 4 0 0 1-1-8l2-8h5l1 3 2-1a5 5 0 0 1 7 4.5V20z"/><path d="M8 20c0-3 2-5 5-5s4 1 5 3"/>'],

        // ---- tempo
        'orologio' => ['Orologio', 'tempo', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
        'cronometro' => ['Cronometro', 'tempo', '<circle cx="12" cy="13" r="8"/><path d="M12 9v4"/><path d="M10 2h4"/><path d="m18 6 1.5-1.5"/>'],
        'sveglia' => ['Sveglia', 'tempo', '<circle cx="12" cy="13" r="7"/><path d="M12 9v4l2 2"/><path d="m5 3-2 2"/><path d="m19 3 2 2"/><path d="m6 19-1 2"/><path d="m18 19 1 2"/>'],
        'clessidra' => ['Clessidra', 'tempo', '<path d="M6 2h12"/><path d="M6 22h12"/><path d="M7 2v5l5 5 5-5V2"/><path d="M7 22v-5l5-5 5 5v5"/>'],
        'calendario' => ['Calendario', 'tempo', '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18"/><path d="M8 3v4"/><path d="M16 3v4"/>'],
        'calendario-spunta' => ['Calendario con spunta', 'tempo', '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18"/><path d="M8 3v4"/><path d="M16 3v4"/><path d="m9 15 2 2 4-4"/>'],
        'calendario-giorno' => ['Giorno sul calendario', 'tempo', '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18"/><path d="M8 3v4"/><path d="M16 3v4"/><path d="M12 14h.01"/>'],
        'storia' => ['Cronologia', 'tempo', '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 3v6h6"/><path d="M12 8v4l3 2"/>'],
        'timer-sabbia' => ['Pausa', 'tempo', '<rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/>'],
        'play' => ['Avvia', 'tempo', '<path d="m6 4 14 8-14 8z"/>'],
        'play-cerchio' => ['Avvia in un cerchio', 'tempo', '<circle cx="12" cy="12" r="9"/><path d="m10 8 5 4-5 4z"/>'],

        // ---- luoghi
        'mappa' => ['Mappa', 'luoghi', '<path d="m3 6 6-2 6 2 6-2v14l-6 2-6-2-6 2z"/><path d="M9 4v14"/><path d="M15 6v14"/>'],
        'pin' => ['Segnaposto', 'luoghi', '<path d="M12 22s7-6.5 7-12a7 7 0 0 0-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>'],
        'pin-mappa' => ['Segnaposto sulla mappa', 'luoghi', '<path d="M12 15s4-3.7 4-7a4 4 0 0 0-8 0c0 3.3 4 7 4 7z"/><circle cx="12" cy="8" r="1.3"/><path d="M6 14l-3 7h18l-3-7"/>'],
        'navigazione' => ['Navigazione', 'luoghi', '<path d="m3 11 18-8-8 18-2-8z"/>'],
        'treno' => ['Treno', 'luoghi', '<rect x="5" y="3" width="14" height="14" rx="3"/><path d="M5 10h14"/><path d="M9 14h.01"/><path d="M15 14h.01"/><path d="m8 21 2-4"/><path d="m16 21-2-4"/>'],
        'auto' => ['Auto', 'luoghi', '<path d="M4 16v-5l2-5h12l2 5v5z"/><path d="M4 11h16"/><circle cx="7.5" cy="16.5" r="1.5"/><circle cx="16.5" cy="16.5" r="1.5"/><path d="M6 18v2"/><path d="M18 18v2"/>'],
        'aereo' => ['Aereo', 'luoghi', '<path d="M2 13 22 4l-5 17-4-6z"/><path d="m13 15 4-4"/>'],
        'porta' => ['Porta', 'luoghi', '<path d="M4 21h16"/><path d="M6 21V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17"/><path d="M14 12h.01"/>'],
        'finestra' => ['Finestra', 'luoghi', '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M4 12h16"/><path d="M12 3v18"/>'],

        // ---- documenti
        'documento' => ['Documento', 'documenti', '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/>'],
        'documento-testo' => ['Documento con testo', 'documenti', '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/><path d="M9 13h6"/><path d="M9 17h6"/>'],
        'documento-piu' => ['Nuovo documento', 'documenti', '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/><path d="M12 12v6"/><path d="M9 15h6"/>'],
        'documenti' => ['Piu documenti', 'documenti', '<path d="M8 7h8a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"/><path d="M11 3h7a1 1 0 0 1 1 1v12"/>'],
        'cartella' => ['Cartella', 'documenti', '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>'],
        'cartella-aperta' => ['Cartella aperta', 'documenti', '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v2"/><path d="m3 19 2.5-8h16L19 19z"/><path d="M3 19V7"/>'],
        'libro' => ['Libro', 'documenti', '<path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>'],
        'libro-aperto' => ['Libro aperto', 'documenti', '<path d="M2 5h6a3 3 0 0 1 3 3v12a3 3 0 0 0-3-3H2z"/><path d="M22 5h-6a3 3 0 0 0-3 3v12a3 3 0 0 1 3-3h6z"/>'],
        'giornale' => ['Giornale', 'documenti', '<path d="M4 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="M6 8h5v4H6z"/><path d="M14 8h4"/><path d="M14 12h4"/><path d="M6 16h12"/>'],
        'penna' => ['Penna', 'documenti', '<path d="m12 19 7-7 3 3-7 7-3-3z"/><path d="m18 13-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="m2 2 7.6 7.6"/><circle cx="11" cy="11" r="2"/>'],
        'graffetta' => ['Graffetta', 'documenti', '<path d="m21 11.5-8.5 8.5a5.5 5.5 0 0 1-7.8-7.8l8.8-8.8a3.5 3.5 0 0 1 5 5l-8.8 8.8a1.5 1.5 0 0 1-2.1-2.1L15.5 7"/>'],
        'archivio' => ['Archivio', 'documenti', '<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8"/><path d="M10 12h4"/>'],
        'stampante' => ['Stampante', 'documenti', '<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v7H6z"/>'],
        'pdf' => ['PDF', 'documenti', '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/><path d="M8 17v-5h1.5a1.5 1.5 0 0 1 0 3H8"/><path d="M13 17v-5h1a2.5 2.5 0 0 1 0 5z"/>'],
        'certificato' => ['Certificato', 'documenti', '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M7 9h6"/><path d="M7 12h4"/><circle cx="16" cy="12" r="2"/><path d="m14.5 14 -1 6 2.5-1.5L18.5 20l-1-6"/>'],

        // ---- media
        'immagine' => ['Immagine', 'media', '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m21 16-5-5-9 9"/>'],
        'fotocamera' => ['Fotocamera', 'media', '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.5"/>'],
        'video' => ['Video', 'media', '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/><path d="M7 4v5"/><path d="M17 4v5"/><path d="m10 12 5 3-5 3z"/>'],
        'musica' => ['Musica', 'media', '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>'],
        'podcast' => ['Podcast', 'media', '<circle cx="12" cy="11" r="2"/><path d="M8.5 14.5a5 5 0 1 1 7 0"/><path d="M6 17a9 9 0 1 1 12 0"/><path d="M11 15h2l1 7h-4z"/>'],
        'film' => ['Film', 'media', '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 3v18"/><path d="M17 3v18"/><path d="M3 8h4"/><path d="M3 12h4"/><path d="M3 16h4"/><path d="M17 8h4"/><path d="M17 12h4"/><path d="M17 16h4"/>'],
        'pennello' => ['Pennello', 'media', '<path d="M9.4 14.6 20 4l-10.6 10.6z"/><path d="M9.4 14.6a3 3 0 0 0-4.4 4.4c0 1-1 2-2 2 3 0 6-1 6-4a2 2 0 0 0 .4-2.4z"/>'],
        'tavolozza' => ['Tavolozza', 'media', '<path d="M12 3a9 9 0 1 0 0 18c1.5 0 2-1 2-2v-1a2 2 0 0 1 2-2h2a3 3 0 0 0 3-3 9 9 0 0 0-9-10z"/><circle cx="7.5" cy="11.5" r="1"/><circle cx="10.5" cy="7.5" r="1"/><circle cx="15.5" cy="7.5" r="1"/>'],
        'codice' => ['Codice', 'media', '<path d="m8 8-5 4 5 4"/><path d="m16 8 5 4-5 4"/><path d="m14 4-4 16"/>'],
        'terminale' => ['Terminale', 'media', '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="m7 9 3 3-3 3"/><path d="M12 15h5"/>'],

        // ---- natura
        'sole' => ['Sole', 'natura', '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.9 4.9 1.4 1.4"/><path d="m17.7 17.7 1.4 1.4"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m4.9 19.1 1.4-1.4"/><path d="m17.7 6.3 1.4-1.4"/>'],
        'luna' => ['Luna', 'natura', '<path d="M20 15A8.5 8.5 0 0 1 9 4a8.5 8.5 0 1 0 11 11z"/>'],
        'nuvola' => ['Nuvola', 'natura', '<path d="M7 18a4 4 0 0 1-.5-8A6 6 0 0 1 18 9a4.5 4.5 0 0 1 0 9z"/>'],
        'pioggia' => ['Pioggia', 'natura', '<path d="M7 15a4 4 0 0 1-.5-8A6 6 0 0 1 18 6a4.5 4.5 0 0 1 0 9"/><path d="M8 18v3"/><path d="M12 17v4"/><path d="M16 18v3"/>'],
        'foglia' => ['Foglia', 'natura', '<path d="M4 20c0-9 6-16 16-16 0 10-6 16-16 16z"/><path d="M4 20 15 9"/>'],
        'albero' => ['Albero', 'natura', '<path d="M12 22v-6"/><path d="M8 16h8l-2-4h2l-2-4h1l-3-5-3 5h1l-2 4h2z"/>'],
        'germoglio' => ['Germoglio', 'natura', '<path d="M12 22V12"/><path d="M12 12c0-4 3-6 7-6 0 4-3 6-7 6z"/><path d="M12 15c0-3-2.5-5-6-5 0 3 2.5 5 6 5z"/>'],
        'fiore' => ['Fiore', 'natura', '<circle cx="12" cy="10" r="2.5"/><path d="M12 7.5a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/><path d="M14.5 10a3 3 0 1 1 6 0 3 3 0 0 1-6 0z"/><path d="M9.5 10a3 3 0 1 0-6 0 3 3 0 0 0 6 0z"/><path d="M12 12.5a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/><path d="M12 18v4"/>'],
        'montagna' => ['Montagna', 'natura', '<path d="m3 20 6-11 4 6 2-3 6 8z"/><path d="m9 9 2 3"/>'],
        'montagne' => ['Montagne con sole', 'natura', '<path d="m2 20 7-12 4 7 2-3 7 8z"/><circle cx="18" cy="6" r="2.5"/>'],
        'goccia' => ['Goccia', 'natura', '<path d="M12 3s6 7 6 11.5a6 6 0 0 1-12 0C6 10 12 3 12 3z"/>'],
        'vento' => ['Vento', 'natura', '<path d="M3 8h11a2.5 2.5 0 1 0-2.3-3.5"/><path d="M3 12h16a2.5 2.5 0 1 1-2.3 3.5"/><path d="M3 16h8a2 2 0 1 1-1.8 2.8"/>'],
        'neve' => ['Neve', 'natura', '<path d="M12 2v20"/><path d="m3.3 7 17.4 10"/><path d="m3.3 17 17.4-10"/><path d="m9 4 3 2 3-2"/><path d="m9 20 3-2 3 2"/>'],
        'arcobaleno' => ['Arcobaleno', 'natura', '<path d="M3 18a9 9 0 0 1 18 0"/><path d="M7 18a5 5 0 0 1 10 0"/><path d="M11 18a1 1 0 0 1 2 0"/>'],
        'terra' => ['Terra', 'natura', '<circle cx="12" cy="12" r="9"/><path d="M3.5 10c3 .5 4 2 5 4s3 3 5 3 2-3 3-4 3-1 4.5-1"/><path d="M8 3.5c1 2 3 2.5 3 4.5s-2 2-3 3"/>'],
        'zampa' => ['Zampa', 'natura', '<circle cx="8" cy="7" r="2"/><circle cx="16" cy="7" r="2"/><circle cx="4.5" cy="12" r="2"/><circle cx="19.5" cy="12" r="2"/><path d="M12 11c-3 0-5 3-5 5.5a2.5 2.5 0 0 0 4 2 1.4 1.4 0 0 1 2 0 2.5 2.5 0 0 0 4-2c0-2.5-2-5.5-5-5.5z"/>'],

        // ---- forme
        'cerchio' => ['Cerchio', 'forme', '<circle cx="12" cy="12" r="9"/>'],
        'cerchio-pieno' => ['Cerchio pieno', 'forme', '<circle cx="12" cy="12" r="9"/>', true],
        'punto' => ['Punto', 'forme', '<circle cx="12" cy="12" r="4"/>', true],
        'quadrato' => ['Quadrato', 'forme', '<rect x="4" y="4" width="16" height="16" rx="2"/>'],
        'quadrato-pieno' => ['Quadrato pieno', 'forme', '<rect x="4" y="4" width="16" height="16" rx="2"/>', true],
        'triangolo' => ['Triangolo', 'forme', '<path d="M12 4 3 20h18z"/>'],
        'rombo' => ['Rombo', 'forme', '<path d="m12 3 9 9-9 9-9-9z"/>'],
        'esagono' => ['Esagono', 'forme', '<path d="M12 2.5 20.2 7v10L12 21.5 3.8 17V7z"/>'],
        'pentagono' => ['Pentagono', 'forme', '<path d="m12 3 9 6.5-3.5 10.5h-11L3 9.5z"/>'],
        'ottagono' => ['Ottagono', 'forme', '<path d="M8 3h8l5 5v8l-5 5H8l-5-5V8z"/>'],
        'scintilla' => ['Scintilla', 'forme', '<path d="M12 3c.6 4.5 3 7.5 9 9-6 1.5-8.4 4.5-9 9-.6-4.5-3-7.5-9-9 6-1.5 8.4-4.5 9-9z"/>'],
        'scintille' => ['Scintille', 'forme', '<path d="M10 4c.4 3.5 2 5.5 6 6.5-4 1-5.6 3-6 6.5-.4-3.5-2-5.5-6-6.5 4-1 5.6-3 6-6.5z"/><path d="M18 14c.2 1.8 1 2.8 3 3.3-2 .5-2.8 1.5-3 3.3-.2-1.8-1-2.8-3-3.3 2-.5 2.8-1.5 3-3.3z"/>'],
        'infinito' => ['Infinito', 'forme', '<path d="M12 12c-2-2.7-3.5-4-5.5-4a4 4 0 0 0 0 8c2 0 3.5-1.3 5.5-4s3.5-4 5.5-4a4 4 0 0 1 0 8c-2 0-3.5-1.3-5.5-4z"/>'],
        'spirale' => ['Spirale', 'forme', '<path d="M12 12a1 1 0 0 1 2 0 3 3 0 0 1-6 0 5 5 0 0 1 10 0 7 7 0 0 1-14 0 9 9 0 0 1 18 0"/>'],
        'mirino' => ['Mirino', 'forme', '<circle cx="12" cy="12" r="7"/><path d="M12 2v4"/><path d="M12 18v4"/><path d="M2 12h4"/><path d="M18 12h4"/>'],
        'livelli' => ['Livelli', 'forme', '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/>'],
        'cubo' => ['Cubo', 'forme', '<path d="m12 2 9 5v10l-9 5-9-5V7z"/><path d="m3 7 9 5 9-5"/><path d="M12 12v10"/>'],

        // ---- dati e grafici
        'grafico-barre' => ['Grafico a barre', 'dati', '<path d="M3 21h18"/><path d="M6 17v-6"/><path d="M11 17V7"/><path d="M16 17v-3"/><path d="M21 17V4"/>'],
        'grafico-linea' => ['Grafico a linea', 'dati', '<path d="M3 3v18h18"/><path d="m6 15 4-5 4 3 6-7"/>'],
        'grafico-torta' => ['Grafico a torta', 'dati', '<path d="M21 12A9 9 0 1 1 12 3v9z"/><path d="M21 8a9 9 0 0 0-6-5v6h6z"/>'],
        'grafico-su' => ['Andamento in su', 'dati', '<path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>'],
        'grafico-giu' => ['Andamento in giu', 'dati', '<path d="m3 7 6 6 4-4 8 8"/><path d="M15 17h6v-6"/>'],
        'tabella' => ['Tabella', 'dati', '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/><path d="M3 15h18"/><path d="M10 4v16"/>'],
        'attivita' => ['Attivita', 'dati', '<path d="M3 12h4l3-8 4 16 3-8h4"/>'],
        'battito' => ['Battito', 'dati', '<path d="M3 12h3l2-5 4 10 3-7 2 2h4"/>'],
        'termometro' => ['Termometro', 'dati', '<path d="M10 14.5V5a2 2 0 0 1 4 0v9.5a4 4 0 1 1-4 0z"/><path d="M12 10v6"/>'],
        'tachimetro' => ['Tachimetro', 'dati', '<path d="M4 18a9 9 0 1 1 16 0"/><path d="m12 15 4-6"/><circle cx="12" cy="16" r="1.5"/>'],
        'calcolatrice' => ['Calcolatrice', 'dati', '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/><path d="M8 16h.01"/><path d="M12 16h.01"/><path d="M16 16h.01"/>'],
        'database' => ['Database', 'dati', '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>'],
        'binario' => ['Rete', 'dati', '<circle cx="12" cy="5" r="2.5"/><circle cx="5" cy="19" r="2.5"/><circle cx="19" cy="19" r="2.5"/><path d="M12 7.5V12"/><path d="m12 12-5.5 5"/><path d="m12 12 5.5 5"/>'],
        'imbuto' => ['Imbuto', 'dati', '<path d="M4 4h16l-6 8v7l-4 2v-9z"/>'],
    ];
    return $d;
}

/** id => ['nome' => ..., 'cat' => ...]. Per il selettore del pannello. */
function icone_elenco(): array {
    $out = [];
    foreach (icone_dati() as $id => $r) $out[$id] = ['nome' => $r[0], 'cat' => $r[1]];
    return $out;
}

/** id => svg, per il selettore di icone del pannello (editor_icone_set()). */
function icone_set(): array {
    static $s = null;
    if ($s !== null) return $s;
    $s = [];
    foreach (array_keys(icone_dati()) as $id) $s[$id] = icona_svg($id);
    return $s;
}

function icona_esiste(string $id): bool {
    return isset(icone_dati()[$id]);
}

/** L'SVG inline dell'icona, con la classe "ico ico-ID" e quelle in piu. Stringa vuota se l'id non esiste. */
function icona_svg(string $id, string $classe = ''): string {
    $r = icone_dati()[$id] ?? null;
    if ($r === null) return '';
    $cl = 'ico ico-' . $id . ($classe !== '' ? ' ' . preg_replace('/[^A-Za-z0-9 _-]/', '', $classe) : '');
    return (!empty($r[3]) ? ICONE_SVG_APRI_PIENO : ICONE_SVG_APRI) . ' class="' . $cl . '">' . $r[2] . '</svg>';
}

/** Un'icona a scelta fra quelle del set, altrimenti quella di ripiego. */
function icona_o(string $id, string $ripiego, string $classe = ''): string {
    return icona_svg(icona_esiste($id) ? $id : $ripiego, $classe);
}
