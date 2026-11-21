<?php
/**
 * Requisiti e hash delle password, senza database: li usano il pannello (includes/sicurezza.php) e l'installatore,
 * così l'amministratore nasce con le stesse regole di chi cambia la password dopo.
 */

/** Il messaggio d'errore se la password non va bene; stringa vuota se va bene. */
function sicurezza_password_errore(string $pw, string $utente = '', string $email = ''): string {
    if (strlen($pw) < 12) return 'La password deve avere almeno 12 caratteri.';
    if (strlen($pw) > 200) return 'La password è troppo lunga (massimo 200 caratteri).';
    $basso = mb_strtolower($pw);
    if ($utente !== '' && mb_strlen($utente) >= 3 && str_contains($basso, mb_strtolower($utente))) return 'La password non può contenere il nome utente.';
    $localEmail = strtolower((string)strstr($email, '@', true));
    if ($localEmail !== '' && strlen($localEmail) >= 4 && str_contains($basso, $localEmail)) return 'La password non può contenere la tua email.';
    if (preg_match('/^(.)\1+$/u', $pw)) return 'Una password fatta di un solo carattere ripetuto non va bene.';
    $nudo = preg_replace('/[^a-z0-9]/', '', $basso);
    if (preg_match('/^(0123456789|1234567890|abcdefghijklmnopqrstuvwxyz|qwertyuiop|qwertyuiopasdfghjkl)/', $nudo) || preg_match('/^(\d{2,})\1+$/', $nudo)) return 'Una sequenza di tastiera o di numeri si indovina in un attimo: scegli un\'altra password.';
    foreach (sicurezza_password_comuni() as $c) {
        if ($nudo === $c || str_starts_with($nudo, $c) && strlen($nudo) - strlen($c) <= 4) return 'Questa password compare negli elenchi delle più usate: scegline un\'altra.';
    }
    return '';
}

/** Le password (e radici) più usate al mondo, in minuscolo senza punteggiatura. Chi le usa viene fermato. */
function sicurezza_password_comuni(): array {
    return ['password', 'passw0rd', 'password1', 'password123', '123456789012', '1234567890123', 'qwertyuiop', 'qwertyuiopasdfghjkl', 'iloveyou', 'letmein', 'welcome', 'admin', 'administrator',
        'adminadmin', 'adminpassword', 'changeme', 'default', 'football', 'baseball', 'dragon', 'monkey', 'sunshine', 'princess', 'superman', 'batman', 'trustno1', 'master', 'shadow',
        'michael', 'jennifer', 'computer', 'internet', 'whatever', 'starwars', 'pokemon', 'qazwsxedc', 'zaq12wsx', 'abc123', 'abcd1234', 'a1b2c3d4', 'aaaaaaaaaaaa', 'passwordpassword',
        'ciao', 'ciaociao', 'amore', 'amoremio', 'juventus', 'napoli', 'inter', 'milan', 'roma', 'francesco', 'alessandro', 'giuseppe', 'antonio', 'andrea', 'giovanni', 'martina', 'giulia',
        'benvenuto', 'segreto', 'italia', 'pippo', 'pluto', 'topolino', 'cambiami', 'wordpress', 'wordpress123', 'adminadmin123', 'temporary', 'passwort', 'contrasena', 'motdepasse',
        'secret', 'test', 'testtest', 'provaprova', 'prova', 'demo', 'guest', 'user', 'login', 'root', 'toor', 'oracle', 'mysql', 'ubuntu', 'linux', 'windows', 'hello', 'hallo', 'summer',
        'winter', 'autumn', 'spring', 'january', 'december', 'november', 'liverpool', 'chelsea', 'arsenal', 'barcelona', 'realmadrid', 'ferrari', 'lamborghini', 'maserati'];
}

/** L'hash della password: argon2id dove c'è, altrimenti bcrypt con costo 12. */
function sicurezza_password_hash(string $pw): string {
    if (defined('PASSWORD_ARGON2ID')) return password_hash($pw, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);
    return password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
}
