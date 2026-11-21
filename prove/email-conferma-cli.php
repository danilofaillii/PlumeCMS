<?php
/**
 * Aiuti da riga di comando per prova-email-conferma.js (2.7.0), su un CMS installato. Mai nel prodotto.
 * uso: php prove/email-conferma-cli.php <cartella CMS> <comando> [argomenti]
 *   sql "<query>"                                righe in JSON ({p} diventa il prefisso delle tabelle)
 *   vecchia <principale 0|1> <cosa> <nome> <link>  l'email di conferma come la componeva la 2.6.0 (codice copiato qui
 *                                                sotto com'era, impaginazione compresa): {oggetto, html}
 *   richiesta <email> <nome>                     una richiesta come quella del modulo della newsletter ('' = partita)
 *   nuova <principale 0|1> <cosa> <nome> <link> [parti JSON]   la stessa con nl_email_conferma() della 2.7.0
 */
[$dir, $cmd] = array_pad(array_slice($argv, 1, 2), 2, '');
$arg = array_slice($argv, 3);
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: email-conferma-cli.php <cartella CMS> <comando> [argomenti]\n"); exit(2); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/newsletter.php';
newsletter_schema();

/** moduli_email_html() della 2.6.0, com'era. */
function vecchia_email_html(string $titolo, string $corpo, string $pulsante = '', string $link = ''): string {
    $btn = $pulsante !== ''
        ? '<p style="margin:28px 0"><a href="' . h($link) . '" style="display:inline-block;background:#7c3aed;color:#fff;padding:14px 26px;border-radius:10px;text-decoration:none;font-weight:700">' . h($pulsante) . '</a></p>'
          . '<p style="font-size:12px;color:#888">Se il pulsante non si apre, copia questo indirizzo nel browser:<br><span style="word-break:break-all">' . h($link) . '</span></p>'
        : '';
    return '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"></head><body style="margin:0;background:#f4f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2330">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:12px">'
        . '<tr><td style="background:#060a17;color:#fff;padding:22px 30px;font-weight:700;letter-spacing:.06em">' . moduli_email_intestazione() . '</td></tr>'
        . '<tr><td style="padding:30px;font-size:16px;line-height:1.6"><h1 style="font-size:22px;margin:0 0 16px">' . h($titolo) . '</h1>' . $corpo . $btn . moduli_firma_html() . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** La parte di newsletter_richiesta() della 2.6.0 che componeva l'email, com'era (cosa già scelto da chi chiama). */
function vecchia_richiesta_email(bool $principaleQui, string $cosa, string $nome, string $link, array $opz = []): array {
    if ($principaleQui) {
        $frase = 'confermi l&#039;iscrizione alla ' . h($cosa);
        $oggetto = "Conferma l'iscrizione a " . (trim((string)cms_impostazione('newsletter_nome', '')) ?: 'questa newsletter');
        $fuori = 'senza conferma l&#039;indirizzo resta fuori dalla newsletter';
    } else {
        $frase = 'confermi l&#039;iscrizione a «' . h($cosa) . '»';
        $oggetto = "Conferma l'iscrizione: " . $cosa;
        $fuori = 'senza conferma l&#039;indirizzo resta fuori';
    }
    $nome = trim($nome);
    $corpo = (string)($opz['testo'] ?? '') !== '' ? (string)$opz['testo']
        : '<p>Ciao' . ($nome !== '' ? ' ' . h($nome) : '') . ',</p><p>manca un clic. Premi il pulsante e ' . $frase . '.</p><p>Il link vale ' . MODULI_CONFERMA_GIORNI . ' giorni.</p>';
    $pulsante = trim((string)($opz['pulsante'] ?? '')) ?: 'Confermo l&#039;iscrizione';
    $html = str_replace(moduli_firma_html(), moduli_firma_html() . '<p style="font-size:12px;color:#888">Se la richiesta l&#039;ha fatta qualcun altro, ignora questa email: ' . $fuori . '.</p>',
        vecchia_email_html('Conferma l\'iscrizione', $corpo, html_entity_decode($pulsante, ENT_QUOTES, 'UTF-8'), $link));
    $oggetto = trim((string)($opz['oggetto'] ?? '')) ?: $oggetto;
    return ['oggetto' => str_replace(["\r", "\n"], ' ', $oggetto), 'html' => $html];
}

$out = null;
switch ($cmd) {
    case 'sql':
        $st = db()->query(str_replace('{p}', DB_PREFIX, (string)($arg[0] ?? '')));
        $out = $st->columnCount() ? $st->fetchAll() : $st->rowCount();
        break;
    case 'vecchia':
        [$pr, $cosa, $nome, $link] = array_pad($arg, 4, '');
        $out = vecchia_richiesta_email($pr === '1', $cosa, $nome, $link);
        break;
    case 'richiesta':
        // Come il modulo della newsletter del sito (api/modulo.php): newsletter_richiesta() senza opzioni. Il limite per IP si azzera.
        db()->exec('UPDATE ' . tbl('newsletter_iscritti') . " SET ip = 'x'");
        [$email, $nome] = array_pad($arg, 2, '');
        $out = newsletter_richiesta(['email' => $email, 'nome' => $nome, 'cognome' => 'Prova']);
        break;
    case 'nuova':
        [$pr, $cosa, $nome, $link, $parti] = array_pad($arg, 5, '');
        [$o, $h] = nl_email_conferma(['principale' => $pr === '1', 'cosa' => $cosa, 'nome' => $nome, 'link' => $link, 'parti' => json_decode($parti ?: '[]', true) ?: []]);
        $out = ['oggetto' => $o, 'html' => $h];
        break;
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
