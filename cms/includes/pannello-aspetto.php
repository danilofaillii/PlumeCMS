<?php
/**
 * Gli aspetti del pannello (1.4.0): ogni utente sceglie i colori del suo pannello, per sé.
 *
 *   pannello_aspetti()            i quattro aspetti, con nome e descrizione (l'ordine è quello della scelta)
 *   pannello_aspetto(?array $u)   l'aspetto dell'utente (quello collegato se non se ne passa uno); 'aria' se manca o non vale
 *   pannello_aspetto_salva($id, $aspetto)   lo scrive nella colonna users.pannello_aspetto; false se il valore non è ammesso
 *
 * La colonna nasce con sicurezza_schema() (includes/sicurezza.php, elenco $colonne), con 'aria' come predefinito:
 * dopo l'aggiornamento tutti gli utenti che c'erano vedono Aria. I colori stanno in admin/assets/css/aspetti.css;
 * la pagina li prende dall'attributo data-pannello sulla radice (admin-header.php, editor.php), senza JavaScript.
 */

const PANNELLO_ASPETTO_PREDEFINITO = 'aria';

function pannello_aspetti(): array {
    return [
        'aria' => ['nome' => 'Aria', 'descrizione' => 'Chiaro, bianco e blu, con molto spazio.'],
        'bottega' => ['nome' => 'Bottega', 'descrizione' => 'Caldo: carta crema, terracotta e verde salvia.'],
        'cosmo' => ['nome' => 'Cosmo', 'descrizione' => 'Scuro: notte blu e viola.'],
        'lavagna' => ['nome' => 'Lavagna', 'descrizione' => 'Scuro: verde ardesia e testo color gesso.'],
    ];
}

function pannello_aspetto(?array $utente = null): string {
    if ($utente === null && function_exists('current_user') && function_exists('is_logged_in') && is_logged_in()) $utente = current_user();
    $a = (string)($utente['pannello_aspetto'] ?? '');
    return isset(pannello_aspetti()[$a]) ? $a : PANNELLO_ASPETTO_PREDEFINITO;
}

function pannello_aspetto_salva(int $utenteId, string $aspetto): bool {
    if ($utenteId <= 0 || !isset(pannello_aspetti()[$aspetto])) return false;
    db()->prepare('UPDATE ' . tbl('users') . ' SET pannello_aspetto = ? WHERE id = ?')->execute([$aspetto, $utenteId]);
    return true;
}
