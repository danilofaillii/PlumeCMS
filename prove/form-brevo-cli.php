<?php
/**
 * Aiuti da riga di comando per prova-form-brevo.js e prova-form-vecchi.js (2.16.0), su un CMS installato. Mai nel prodotto.
 * uso: php prove/form-brevo-cli.php <cartella CMS> <comando> [argomenti]
 *   sql "<query>"                 righe in JSON ({p} diventa il prefisso delle tabelle)
 *   smtp <porta>                  la posta verso il server finto su quella porta, limite orario largo
 *   pulisci                       toglie i dati della prova: form, liste, gruppi, pagine, campagne, attributi "FB"; contatti @fb-prova.it
 *   prepara                       liste FB Principale, FB Novità, FB Offerte, FB Nascosta; attributi FB_CLIENTE (Sì/No),
 *                                 FB_CITTA (testo), FB_SETTORE (categoria); stampa gli id
 *   contatto <email> [liste]      un contatto attivo nelle liste (id separati da virgola); stampa l'id
 *   campagna <nome> <liste> [pagina]   una campagna in bozza sulle liste, con oggetto e testo; stampa l'id
 *   link_uscita <contatto> [campagna]  il link "Esci dalla newsletter" (firmato con la campagna)
 *   link_pref <contatto>          il link del centro preferenze
 *   email <campagna> <contatto>   l'email della campagna per il contatto, come parte (con pixel e link tracciati se acconsente)
 *   evento <campagna> <contatto>  un'apertura firmata, come dal pixel; stampa {segnato, righe}
 *   conta <json regole>           quanti contatti soddisfano le regole di un segmento
 *   qr <testo>                    la matrice del codice QR (righe di 0 e 1), per qr-decodifica.py
 *   form <id>                     form_leggi() in JSON
 *   html <id> [opzioni json]      form_html() del form (per confrontare i form di prima)
 *   ricevi <id> <json post>       form_ricevi() con un captcha valido: stampa il codice ('' = riuscito)
 *   crea_form <json>              form_salva(0, json): stampa l'id o {errore}
 *   modello_optin                 un modello di doppio opt-in "FB Conferma" (editor a blocchi, etichetta optin); stampa l'id
 */
[$dir, $cmd] = array_pad(array_slice($argv, 1, 2), 2, '');
$arg = array_slice($argv, 3);
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: form-brevo-cli.php <cartella CMS> <comando>\n"); exit(2); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.9';
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/newsletter.php';
require_once $dir . '/includes/form.php';
newsletter_schema();
newsletter_campagne_schema();
form_schema();
if (function_exists('newsletter_segmenti_schema')) newsletter_segmenti_schema();
$t = fn($n) => tbl($n);
$lista = function (string $n) use ($t): int {
    $id = (int)db()->query('SELECT id FROM ' . $t('newsletter_liste') . ' WHERE nome = ' . db()->quote($n))->fetchColumn();
    if ($id) return $id;
    db()->prepare('INSERT INTO ' . $t('newsletter_liste') . ' (nome, descrizione, creato) VALUES (?, \'\', NOW())')->execute([$n]);
    return (int)db()->lastInsertId();
};
$out = null;
switch ($cmd) {
case 'sql':
    $st = db()->query(str_replace('{p}', DB_PREFIX, (string)($arg[0] ?? '')));
    $out = $st->columnCount() ? $st->fetchAll() : $st->rowCount();
    break;
case 'smtp':
    save_setting('posta_smtp', json_encode(['host' => '127.0.0.1', 'porta' => (int)$arg[0], 'sicurezza' => 'nessuna', 'utente' => '', 'password' => '', 'mittente' => 'sito@esempio.it', 'nome' => 'Studio Prova']));
    save_setting('newsletter_config', json_encode(['per_ora' => 50000, 'per_giorno' => 0, 'rispondi_a' => '']));
    $out = true;
    break;
case 'pulisci':
    foreach (db()->query('SELECT id FROM ' . $t('moduli') . " WHERE nome LIKE 'FB %'")->fetchAll(PDO::FETCH_COLUMN) as $f) {
        $m = form_leggi((int)$f);
        if ($m && $m['popup_id']) db()->prepare('DELETE FROM ' . $t('popup') . ' WHERE id = ?')->execute([$m['popup_id']]);
        form_elimina((int)$f);
    }
    $ids = db()->query('SELECT id FROM ' . $t('newsletter_iscritti') . " WHERE email LIKE '%@fb-prova.it'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (array_chunk($ids, 1000) as $pezzo) newsletter_togli_iscritti($pezzo);
    foreach (db()->query('SELECT id FROM ' . $t('newsletter_liste') . " WHERE nome LIKE 'FB %'")->fetchAll(PDO::FETCH_COLUMN) as $l) newsletter_lista_elimina((int)$l);
    foreach (nl_gruppi() as $gid => $g) if (str_starts_with($g['nome'], 'FB ')) nl_gruppo_elimina($gid);
    foreach (nl_pagine_uscita() as $pid => $p) if (str_starts_with($p['nome'], 'FB ')) nl_pagina_uscita_elimina($pid);
    db()->exec('DELETE FROM ' . $t('newsletter_campagne') . " WHERE nome LIKE 'FB %'");
    if (function_exists('nd_schema')) { nd_schema(); db()->exec('DELETE FROM ' . $t('newsletter_modelli') . " WHERE nome LIKE 'FB %'"); }
    foreach (newsletter_attributi(true) as $n => $a) if (str_starts_with($n, 'FB_')) newsletter_attributo_elimina((int)$a['id']);
    save_setting('newsletter_gruppi_attivi', '0');
    save_setting('newsletter_preferenze_form', '0');
    db()->exec('DELETE FROM ' . $t('sicurezza_tentativi'));
    try { db()->exec('UPDATE ' . $t('posta_registro') . " SET quando = quando - INTERVAL 2 DAY WHERE quando > NOW() - INTERVAL 2 DAY"); } catch (Throwable $e) {}
    $out = true;
    break;
case 'prepara':
    $out = ['principale' => $lista('FB Principale'), 'novita' => $lista('FB Novità'), 'offerte' => $lista('FB Offerte'), 'nascosta' => $lista('FB Nascosta')];
    foreach ([['FB_CLIENTE', 'boolean', 'Cliente', ''], ['FB_CITTA', 'text', 'Città', ''], ['FB_SETTORE', 'category', 'Settore', "Negozio\nStudio\nArtigiano"]] as [$n, $tipo, $et, $op]) {
        if (!isset(newsletter_attributi(true)[$n])) newsletter_attributo_crea(['nome' => $n, 'tipo' => $tipo, 'etichetta' => $et, 'opzioni' => $op]);
    }
    break;
case 'contatto':
    $email = mb_strtolower((string)$arg[0]);
    $id = newsletter_id_da_email($email);
    if (!$id) {
        db()->prepare('INSERT INTO ' . $t('newsletter_iscritti') . " (email, nome, stato, fonte, consenso_at, creato, aggiornato) VALUES (?, 'Prova', 'attivo', 'prova', NOW(), NOW(), NOW())")->execute([$email]);
        $id = (int)db()->lastInsertId();
    }
    foreach (array_filter(array_map('intval', explode(',', (string)($arg[1] ?? '')))) as $l) newsletter_in_lista($id, $l);
    $out = $id;
    break;
case 'campagna':
    $id = newsletter_campagna_crea((string)$arg[0]);
    db()->prepare('UPDATE ' . $t('newsletter_campagne') . " SET oggetto = 'Prova FB', corpo = '<p>Ciao, una prova.</p><p><a href=\"https://esempio.it/pagina\">Un link</a></p>', liste = ?, pagina_uscita_id = ? WHERE id = ?")
        ->execute([(string)$arg[1], (int)($arg[2] ?? 0), $id]);
    $out = $id;
    break;
case 'link_uscita':
    $out = newsletter_link_uscita((int)$arg[0], (int)($arg[1] ?? 0) ? ['c' => (int)$arg[1]] : null);
    break;
case 'link_pref':
    $out = newsletter_link_preferenze((int)$arg[0]);
    break;
case 'email':
    $c = newsletter_campagna((int)$arg[0]);
    $i = newsletter_contatto((int)$arg[1]);
    [, $html] = newsletter_email($c, ['id' => (int)$i['id'], 'nome' => (string)$i['nome'], 'email' => (string)$i['email']]);
    $out = $html;
    break;
case 'evento':
    $c = (int)$arg[0]; $i = (int)$arg[1];
    $ok = newsletter_evento('apertura', $c, 0, 0, $i, nl_firma($c, 0, 0, $i));
    $out = ['segnato' => $ok, 'righe' => (int)db()->query('SELECT COUNT(*) FROM ' . $t('newsletter_eventi') . " WHERE campagna_id = $c AND iscritto_id = $i")->fetchColumn()];
    break;
case 'conta':
    $r = newsletter_regole_valida((string)$arg[0]);
    $out = isset($r['regole']) ? newsletter_regole_conta($r['regole'])['n'] : $r;
    break;
case 'qr':
    $m = sicurezza_qr_matrice((string)$arg[0]);
    $out = $m === null ? null : array_map(fn($r) => implode('', array_map('intval', $r)), $m);
    break;
case 'form':
    $out = form_leggi((int)$arg[0]);
    break;
case 'html':
    $f = form_leggi((int)$arg[0]);
    echo form_html($f, '/prova/', '', '', json_decode((string)($arg[1] ?? '[]'), true) ?: []);
    exit;
case 'ricevi':
    $f = form_leggi((int)$arg[0]);
    $p = json_decode((string)$arg[1], true) ?: [];
    // Un captcha risolto davvero (come moduli.js): la sfida e il numero che la risolve.
    $s = moduli_captcha_sfida();
    for ($n = 0; $n <= $s['max']; $n++) if (hash('sha256', $s['sale'] . $n) === $s['sfida']) break;
    $s['n'] = $n;
    $p['captcha'] = base64_encode(json_encode($s));
    $out = form_ricevi($f, $p, '/prova/');
    break;
case 'crea_form':
    try { $out = form_salva(0, json_decode((string)$arg[0], true) ?: []); } catch (RuntimeException $e) { $out = ['errore' => $e->getMessage()]; }
    break;
case 'modello_optin':
    $d = nd_design_vuoto();
    $out = nd_modello_crea('FB Conferma', $d, ['tag' => 'optin', 'attivo' => 1, 'oggetto' => 'FB conferma con il modello']);
    break;
default:
    fwrite(STDERR, "comando sconosciuto: $cmd\n");
    exit(2);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
