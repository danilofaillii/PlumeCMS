<?php
/**
 * Aiuti da riga di comando per prova-tag.js e attacchi-tag.js (i tag dei contatti, 2.23.0), su un CMS installato. Mai nel prodotto.
 * uso: php prove/tag-cli.php <cartella CMS> <comando> [argomenti]
 *   sql "<query>"                 righe in JSON ({p} diventa il prefisso delle tabelle)
 *   smtp <porta>                  la posta verso il server finto, limite orario largo
 *   semina <n>                    n contatti iscritti tg1..tgN@tag-prova.it nella lista "TG Prova" (e l'id della lista)
 *   tag_crea <nome>               l'id del tag (o il messaggio d'errore)
 *   tag_di <email>                i nomi dei tag del contatto
 *   tag_aggiungi <email> <nomi>   newsletter_tag_aggiungi (nomi separati da virgola)
 *   conta <json regole>           newsletter_regole_conta: il numero dei contatti, o l'errore
 *   salva_flusso <json>           newsletter_flusso_salva(0, json): l'id o {errore}
 *   giro                          un giro della newsletter (campagne e automazioni)
 *   rinomina <id> <nome>, elimina <id>
 *   tag_molti <n>                 crea n tag "Molti N" (fino al tetto): quanti creati e l'ultimo messaggio
 */
[$dir, $cmd] = array_pad(array_slice($argv, 1, 2), 2, '');
$arg = array_slice($argv, 3);
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: tag-cli.php <cartella CMS> <comando>\n"); exit(2); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/newsletter.php';
require_once $dir . '/includes/newsletter-tag.php';
require_once $dir . '/includes/newsletter-segmenti.php';
newsletter_schema();
newsletter_flussi_schema();
newsletter_tag_schema();
newsletter_segmenti_schema();
$out = null;
switch ($cmd) {
    case 'sql':
        $q = str_replace('{p}', DB_PREFIX, (string)$arg[0]);
        $st = db()->query($q);
        $out = $st->columnCount() ? $st->fetchAll(PDO::FETCH_ASSOC) : $st->rowCount();
        break;
    case 'smtp':
        save_setting('posta_smtp', json_encode(['host' => '127.0.0.1', 'porta' => (int)$arg[0], 'sicurezza' => 'nessuna', 'utente' => '', 'password' => '', 'mittente' => 'sito@esempio.it', 'nome' => 'Studio Prova']));
        save_setting('newsletter_config', json_encode(['per_ora' => 50000, 'per_giorno' => 0, 'rispondi_a' => '']));
        $out = true;
        break;
    case 'semina':
        $l = (int)db()->query('SELECT id FROM ' . tbl('newsletter_liste') . " WHERE nome = 'TG Prova'")->fetchColumn();
        if (!$l) { $r = newsletter_lista_crea('TG Prova', 0); $l = is_int($r) ? $r : 0; }
        for ($i = 1; $i <= (int)$arg[0]; $i++) {
            $r = newsletter_contatto_crea(['EMAIL' => "tg$i@tag-prova.it", 'NOME' => "Tg$i"], [$l]);
        }
        $out = $l;
        break;
    case 'tag_crea':
        $out = newsletter_tag_crea((string)$arg[0]);
        break;
    case 'tag_di':
        $out = array_values(newsletter_tag_di(newsletter_id_da_email((string)$arg[0])));
        break;
    case 'tag_aggiungi':
        $out = newsletter_tag_aggiungi(newsletter_id_da_email((string)$arg[0]), newsletter_tag_dividi((string)$arg[1]), 'riga di comando');
        break;
    case 'conta':
        $r = newsletter_regole_valida((string)$arg[0]);
        $out = isset($r['errore']) ? ['errore' => $r['errore']] : newsletter_regole_conta($r['regole']);
        break;
    case 'salva_flusso':
        try { $out = newsletter_flusso_salva(0, json_decode((string)$arg[0], true)); } catch (Throwable $e) { $out = ['errore' => $e->getMessage()]; }
        break;
    case 'giro':
        $out = newsletter_invia_giro();
        break;
    case 'tag_molti':
        $fatti = 0; $ultimo = null;
        for ($i = 1; $i <= (int)$arg[0]; $i++) { $ultimo = newsletter_tag_crea('Molti ' . $i); if (is_int($ultimo)) $fatti++; else break; }
        $out = ['creati' => $fatti, 'ultimo' => $ultimo];
        break;
    case 'rinomina':
        $out = newsletter_tag_rinomina((int)$arg[0], (string)$arg[1]);
        break;
    case 'elimina':
        $out = newsletter_tag_elimina((int)$arg[0]);
        break;
    default:
        fwrite(STDERR, "comando sconosciuto\n"); exit(2);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
