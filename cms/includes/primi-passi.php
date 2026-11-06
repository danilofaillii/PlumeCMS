<?php
/**
 * Primi passi (1.4.0): la lista della dashboard che si spunta da sola guardando lo stato vero del sito.
 * Solo nelle installazioni nuove: l'installatore della 1.4.0 scrive l'impostazione primi_passi = "mostra"; un sito che
 * si aggiorna non la ha e non vede niente. Quando ogni voce e fatta, la lista si nasconde ("nascosto", per sempre).
 *
 * Ogni voce: ['chiave', 'titolo', 'fatto' => bool, 'detto' => a che punto e, 'link' => dove si fa, 'invito' => testo del link].
 */

require_once __DIR__ . '/config.php';

/** La lista si mostra? (installazione nuova, non nascosta) */
function primi_passi_attivi(): bool {
    return (string)get_setting('primi_passi', '') === 'mostra';
}

function primi_passi(): array {
    require_once __DIR__ . '/design.php';
    require_once __DIR__ . '/kit-partenza.php';
    require_once __DIR__ . '/cron.php';
    require_once __DIR__ . '/controllo-server.php';
    $out = [];
    $v = fn(string $chiave, string $titolo, bool $fatto, string $detto, string $link, string $invito) => compact('chiave', 'titolo', 'fatto', 'detto', 'link', 'invito');

    // 1. Nome e logo: il nome lo scrive l'installatore, il logo (o l'icona del sito) lo mette la persona.
    $id = design_sito();
    $haLogo = trim((string)$id['logo']) !== '' || trim((string)$id['favicon']) !== '';
    $out[] = $v('identita', 'Nome e logo del sito', $haLogo, $haLogo ? 'Il sito si chiama «' . $id['nome'] . '» e ha il suo logo.' : 'Il sito si chiama «' . $id['nome'] . '». Manca il logo (o l\'icona nella scheda del browser).', 'design.php', 'Apri Stile del sito, Identità');

    // 2. I colori: lo Stile del sito diverso da quello di partenza (anche con un tema).
    $adesso = design_valori(); $partenza = design_pulisci(design_predefiniti());
    $cambiato = false;
    foreach (['colori', 'font', 'tipografia', 'pulsanti'] as $s) if (json_encode($adesso[$s] ?? null) !== json_encode($partenza[$s] ?? null)) $cambiato = true;
    $out[] = $v('colori', 'I tuoi colori', $cambiato, $cambiato ? 'Lo stile del sito è tuo: colori o caratteri cambiati.' : 'Il sito ha ancora i colori di partenza.', 'design.php', 'Cambia i colori');

    // 3. Le email: l'ultima prova partita.
    $p = server_email_ultima();
    $out[] = $v('email', 'Le email partono', $p !== null && !empty($p['ok']),
        $p === null ? 'Manda un\'email di prova al tuo indirizzo: così sai che conferme e avvisi arrivano.' : (!empty($p['ok']) ? 'L\'email di prova è partita il ' . date('d/m/Y', (int)$p['quando']) . '.' : 'L\'ultima prova non è partita: ' . $p['errore']),
        'impostazioni.php', 'Configurazione email');

    // 4. Il cron: tutti e due i giri arrivati dal cron dell'hosting, non dal pulsante.
    $c = cron_attivo();
    $out[] = $v('cron', 'Il cron è attivo', $c['giornaliero'] && $c['newsletter'],
        $c['giornaliero'] && $c['newsletter'] ? 'Il cron del mattino e quello della newsletter girano da soli.' : 'Il cron fa uscire da solo gli aggiornamenti programmati e la newsletter: metti le due righe nell\'hosting.',
        'cron-log.php', 'Le due righe da copiare');

    // 5. La prima pagina o il primo articolo scritto dalla persona.
    $scritto = kit_contenuto_scritto();
    $out[] = $v('contenuto', 'La prima pagina o il primo articolo', $scritto, $scritto ? 'Hai scritto o cambiato qualcosa nel sito.' : 'Riscrivi una pagina d\'esempio o scrivi il tuo primo articolo.', 'contenuti.php?tipo=articoli', 'Vai agli articoli');

    // 5 bis (1.7.0). Il sito su misura: le foto vere al posto di quelle finte. Solo se il sito è su misura o ne mostra ancora.
    require_once __DIR__ . '/sito-su-misura.php';
    $finte = count(su_misura_segnaposto_usati());
    $kp = kit_prova();
    if ($finte > 0 || ($kp && $kp['kit'] === 'su-misura')) {
        $out[] = $v('foto', 'Le tue foto al posto di quelle finte', $finte === 0,
            $finte === 0 ? 'Il sito mostra solo foto tue.' : ($finte === 1 ? 'Resta una foto finta da sostituire.' : 'Restano ' . $finte . ' foto finte da sostituire.'),
            'media.php?cartella=segnaposto', 'Apri Media, Da sostituire');
    }

    // 6. Il tipo di sito definitivo.
    $k = kit_prova();
    $def = $k === null || $k['stato'] === 'definitivo';
    $out[] = $v('definitivo', 'Il tipo di sito è definitivo', $def, $def ? 'Il tipo di sito è deciso.' : 'Quando il sito ti piace, premi Definitivo nel riquadro qui sopra.', 'index.php#tipo-sito', 'Vai al riquadro');
    return $out;
}

/** Si nasconde solo quando e tutto fatto. Ritorna false se manca qualcosa. */
function primi_passi_nascondi(): bool {
    foreach (primi_passi() as $x) if (!$x['fatto']) return false;
    save_setting('primi_passi', 'nascosto');
    return true;
}
