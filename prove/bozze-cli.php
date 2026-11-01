<?php
// Per prova-bozze-vuote.js (UX 2026-11). Mai nel prodotto.
// uso: php bozze-cli.php <cartella CMS> invecchia <tipo> <id> <ore> [ore-dopo] | testo <tipo> <id> <frase> | stato <tipo> <id> | cestina <tipo> <id>
//   invecchia  porta created_at indietro di <ore> ore; updated_at uguale, o <ore-dopo> ore indietro (una modifica successiva)
//   testo      salva dall'editor una bozza con un widget Testo che contiene la frase (come farebbe l'editor)
//   stato      {"cestino": bool, "titolo": "..."} in JSON
chdir($argv[1]);
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/contenuti.php';
require_once $argv[1] . '/includes/editor.php';
$tipo = (string)($argv[3] ?? 'articoli');
$tab = tbl(contenuti_tipo($tipo)['tabella']);
$id = (int)($argv[4] ?? 0);
switch ($argv[2] ?? '') {
    case 'invecchia':
        $ore = (int)$argv[5]; $dopo = isset($argv[6]) ? (int)$argv[6] : $ore;
        db()->prepare("UPDATE $tab SET created_at = NOW() - INTERVAL $ore HOUR, updated_at = NOW() - INTERVAL $dopo HOUR WHERE id = ?")->execute([$id]);
        echo "ok\n";
        break;
    case 'testo':
        $albero = editor_valida(['elementi' => [editor_sezione([editor_widget('testo', ['contenuto' => '<p>' . htmlspecialchars((string)$argv[5]) . '</p>'])])]]);
        editor_salva($id, $albero, false, 'prova', 'bozza', $tipo);
        echo "ok\n";
        break;
    case 'cestina':
        echo contenuti_cestina($tipo, [$id]), "\n";
        break;
    case 'stato':
        $st = db()->prepare("SELECT title, cestino FROM $tab WHERE id = ?");
        $st->execute([$id]);
        $r = $st->fetch();
        echo json_encode($r ? ['cestino' => $r['cestino'] !== null, 'titolo' => $r['title']] : null, JSON_UNESCAPED_UNICODE), "\n";
        break;
    default:
        fwrite(STDERR, "comando sconosciuto\n"); exit(2);
}
