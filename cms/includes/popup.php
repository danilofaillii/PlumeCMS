<?php
/**
 * Popup del sito: una finestra che compare dopo qualche secondo, a metà pagina o quando il mouse esce,
 * con un titolo, un testo, un pulsante e, se si vuole, un modulo (la newsletter, un form o un modulo del sito).
 * Si decide su quali pagine, e ogni quanti giorni la stessa persona la rivede (ricordato nel suo browser).
 */

require_once __DIR__ . '/form.php';
require_once __DIR__ . '/editor-widget.php';

const POPUP_DOVE = ['tutte' => 'Tutte le pagine', 'home' => 'Solo la home', 'articoli' => 'Solo gli articoli (e gli altri contenuti da leggere)', 'pagine' => 'Solo le pagine fisse', 'percorsi' => 'Solo gli indirizzi elencati'];
const POPUP_INNESCHI = ['secondi' => 'Dopo un certo numero di secondi', 'scroll' => 'Quando si scorre fino a una percentuale della pagina', 'uscita' => 'Quando il mouse esce dalla pagina (solo desktop)'];

function popup_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    $sql = 'CREATE TABLE IF NOT EXISTS ' . tbl('popup') . " (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `nome` VARCHAR(100) NOT NULL,
        `attivo` TINYINT(1) NOT NULL DEFAULT 0,
        `occhiello` VARCHAR(100) NOT NULL DEFAULT '',
        `titolo` VARCHAR(255) NOT NULL DEFAULT '',
        `testo` TEXT,
        `immagine` VARCHAR(500) NOT NULL DEFAULT '',
        `pulsante_testo` VARCHAR(100) NOT NULL DEFAULT '',
        `pulsante_link` VARCHAR(500) NOT NULL DEFAULT '',
        `modulo` VARCHAR(30) NOT NULL DEFAULT '',
        `dove` VARCHAR(20) NOT NULL DEFAULT 'tutte',
        `percorsi` TEXT,
        `innesco` VARCHAR(20) NOT NULL DEFAULT 'secondi',
        `valore` INT(11) NOT NULL DEFAULT 8,
        `frequenza_giorni` INT(11) NOT NULL DEFAULT 7,
        `creato` DATETIME NOT NULL,
        `aggiornato` DATETIME NOT NULL,
        PRIMARY KEY (`id`), KEY `attivo` (`attivo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    $impronta = md5($sql . DB_NAME);
    if (get_setting('popup_schema_impronta', '') !== $impronta) {
        db()->exec($sql);
        save_setting('popup_schema_impronta', $impronta);
    }
}

/** Le colonne di testo che possono essere NULL diventano stringhe: chi le usa confronta con ''. */
function popup_riga(array $p): array {
    foreach (['testo', 'percorsi', 'occhiello', 'titolo', 'immagine', 'pulsante_testo', 'pulsante_link', 'modulo'] as $k) $p[$k] = (string)($p[$k] ?? '');
    return $p;
}

function popup_tutti(): array {
    popup_schema();
    return array_map('popup_riga', db()->query('SELECT * FROM ' . tbl('popup') . ' ORDER BY attivo DESC, nome')->fetchAll());
}

function popup_leggi(int $id): ?array {
    popup_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('popup') . ' WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    return $p ? popup_riga($p) : null;
}

function popup_salva(int $id, array $d): int {
    popup_schema();
    $nome = mb_substr(trim((string)($d['nome'] ?? '')), 0, 100);
    if ($nome === '') throw new RuntimeException('Il popup ha bisogno di un nome.');
    $v = [
        'nome' => $nome, 'attivo' => !empty($d['attivo']) ? 1 : 0,
        'occhiello' => mb_substr(trim((string)($d['occhiello'] ?? '')), 0, 100), 'titolo' => mb_substr(trim((string)($d['titolo'] ?? '')), 0, 255),
        'testo' => newsletter_4byte_dentro(trim((string)($d['testo'] ?? ''))), 'immagine' => mb_substr(trim((string)($d['immagine'] ?? '')), 0, 500),
        'pulsante_testo' => mb_substr(trim((string)($d['pulsante_testo'] ?? '')), 0, 100), 'pulsante_link' => mb_substr(trim((string)($d['pulsante_link'] ?? '')), 0, 500),
        'modulo' => preg_match('/^[a-z][a-z0-9_:-]{0,40}$/', (string)($d['modulo'] ?? '')) ? $d['modulo'] : '',
        'dove' => isset(POPUP_DOVE[$d['dove'] ?? '']) ? $d['dove'] : 'tutte', 'percorsi' => trim((string)($d['percorsi'] ?? '')),
        'innesco' => isset(POPUP_INNESCHI[$d['innesco'] ?? '']) ? $d['innesco'] : 'secondi', 'valore' => max(0, min(600, (int)($d['valore'] ?? 8))),
        'frequenza_giorni' => max(0, min(365, (int)($d['frequenza_giorni'] ?? 7))),
    ];
    if ($v['titolo'] === '' && $v['testo'] === '' && $v['modulo'] === '') throw new RuntimeException('Un popup vuoto: metti almeno un titolo, un testo o un modulo.');
    if ($id) {
        $set = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($v)));
        $p = []; foreach ($v as $k => $x) $p[":$k"] = $x; $p[':id'] = $id;
        db()->prepare('UPDATE ' . tbl('popup') . " SET $set, aggiornato = NOW() WHERE id = :id")->execute($p);
        return $id;
    }
    $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($v)));
    $segna = implode(', ', array_map(fn($k) => ":$k", array_keys($v)));
    $p = []; foreach ($v as $k => $x) $p[":$k"] = $x;
    db()->prepare('INSERT INTO ' . tbl('popup') . " ($cols, creato, aggiornato) VALUES ($segna, NOW(), NOW())")->execute($p);
    return (int)db()->lastInsertId();
}

/** I popup che valgono per questo indirizzo. $genere: home, post, page, altro. */
function popup_per(string $percorso, string $genere): array {
    popup_schema();
    $out = [];
    foreach (db()->query('SELECT * FROM ' . tbl('popup') . ' WHERE attivo = 1 ORDER BY id')->fetchAll() as $p) {
        $p = popup_riga($p);
        $ok = match ($p['dove']) {
            'home' => $genere === 'home',
            'articoli' => $genere === 'post',
            'pagine' => $genere === 'page',
            'percorsi' => in_array(popup_percorso_pulito($percorso), array_map('popup_percorso_pulito', preg_split('/\R|,/', (string)$p['percorsi'])), true),
            default => true,
        };
        if ($ok) $out[] = $p;
    }
    return $out;
}

/** Un indirizzo scritto a mano, con o senza slash finale o dominio, nella forma del sito: /contatti/ */
function popup_percorso_pulito(string $p): string {
    $p = trim($p);
    if ($p === '') return '';
    $p = preg_replace('~^https?://[^/]+~i', '', $p);
    $p = parse_url($p, PHP_URL_PATH) ?: '/';
    return '/' . trim($p, '/') . (trim($p, '/') === '' ? '' : '/');
}

/** L'HTML dei popup per la pagina, più lo script. Vuoto se non ce ne sono. */
function popup_html(array $popup, string $percorso): string {
    if (!$popup) return '';
    $h = '';
    $riapri = (int)($_GET['popup'] ?? 0);
    foreach ($popup as $p) {
        $modulo = $p['modulo'] !== '' ? vista_modulo($p['modulo'], $percorso) : '';
        // Il modulo dice da quale popup arriva: con un errore la pagina riapre il popup sulla risposta.
        if ($modulo !== '') {
            $modulo = str_replace('<input type="hidden" name="captcha" value="">', '<input type="hidden" name="captcha" value=""><input type="hidden" name="popup" value="' . (int)$p['id'] . '">', $modulo);
            // Lo stesso modulo può stare anche in pagina: dentro il popup gli id cambiano, così non si ripetono.
            $modulo = preg_replace('/\b(id|aria-labelledby)="modulo(["-])/', '$1="popup-' . (int)$p['id'] . '-modulo$2', $modulo);
        }
        $subito = $riapri === (int)$p['id'] && $modulo !== '' && isset($_GET['modulo']);
        $h .= '<div class="popup" id="popup-' . (int)$p['id'] . '" data-innesco="' . h($p['innesco']) . '" data-valore="' . (int)$p['valore'] . '" data-giorni="' . (int)$p['frequenza_giorni'] . '"' . ($subito ? ' data-subito="1"' : '') . ' hidden>'
            . '<div class="popup-finestra" role="dialog" aria-modal="true" aria-labelledby="popup-titolo-' . (int)$p['id'] . '">'
            . '<button type="button" class="popup-chiudi" aria-label="Chiudi">×</button>'
            . ($p['immagine'] !== '' ? '<img class="popup-immagine" src="' . h($p['immagine']) . '" alt="" loading="lazy">' : '')
            . ($p['occhiello'] !== '' ? '<span class="occhiello">' . h($p['occhiello']) . '</span>' : '')
            . ($p['titolo'] !== '' ? '<h2 id="popup-titolo-' . (int)$p['id'] . '">' . h($p['titolo']) . '</h2>' : '')
            . ($p['testo'] !== '' ? '<div class="popup-testo">' . editor_ricco_pulito((string)$p['testo']) . '</div>' : '')
            . ($p['pulsante_testo'] !== '' && $p['pulsante_link'] !== '' ? '<p class="azioni"><a class="btn btn-viola" href="' . h($p['pulsante_link']) . '">' . h($p['pulsante_testo']) . '</a></p>' : '')
            . $modulo . '</div></div>';
    }
    return $h . '<script src="' . sito_versione_asset('popup.js') . '" defer></script>';
}
