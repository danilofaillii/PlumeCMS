<?php
/**
 * Il widget "Richiesta di prenotazione" (2.1): un modulo per chiedere un tavolo (data, ora, persone) o un alloggio (arrivo,
 * partenza, ospiti). Non è l'addon Prenotazioni: niente calendario né posti, la richiesta arriva per email all'attività,
 * che risponde lei.
 *
 * Perché un ingresso suo e non un form del pannello: a chi va l'email lo decide chi fa la pagina, nel widget. Il browser non
 * può sceglierlo (il sito diventerebbe un ripetitore di posta), quindi il widget mette nel modulo la sua configurazione
 * (modalità, indirizzi, massimo di persone) firmata con HMAC (rp_firma), e api/modulo.php accetta solo una configurazione con
 * la firma giusta. Il resto sono le difese dei moduli fissi (moduli.php): captcha a prova di lavoro, campo esca, spunta
 * della privacy, limite di 5 invii all'ora per indirizzo IP. Le richieste si vedono in Invii dei form, sotto il form
 * "Richieste di prenotazione" che nasce da solo alla prima richiesta (spento: non si mette in pagina con il widget Modulo).
 */

require_once __DIR__ . '/moduli.php';
require_once __DIR__ . '/form.php';

const RP_SLUG = 'richieste-prenotazione';
const RP_MAX_INDIRIZZI = 3;
const RP_ERRORI = [
    'data' => 'Controlla la data: deve essere da oggi in avanti.',
    'ora' => "Controlla l'ora.",
    'persone' => 'Controlla quante persone.',
    'partenza' => "La partenza deve essere dopo l'arrivo.",
    'nome' => 'Scrivi il tuo nome.',
    'telefono' => 'Controlla il numero di telefono.',
];

/** La configurazione del widget, firmata: [testo base64, firma]. */
function rp_firma(array $cfg): array {
    $b = base64_encode(json_encode($cfg, JSON_UNESCAPED_UNICODE));
    return [$b, hash_hmac('sha256', 'prenota|' . $b, moduli_segreto())];
}

/** La configurazione arrivata con il modulo, se la firma è giusta; altrimenti null. */
function rp_verifica(string $b, string $firma): ?array {
    if ($b === '' || strlen($b) > 4000 || !hash_equals(hash_hmac('sha256', 'prenota|' . $b, moduli_segreto()), $firma)) return null;
    $c = json_decode((string)base64_decode($b, true), true);
    return is_array($c) ? $c : null;
}

/** Gli indirizzi a cui va la richiesta: validi, al massimo RP_MAX_INDIRIZZI; vuoti, quello degli avvisi del sito. */
function rp_indirizzi(string $testo): array {
    $out = [];
    foreach (preg_split('/[\s,;]+/', $testo, -1, PREG_SPLIT_NO_EMPTY) as $e) {
        if (filter_var($e, FILTER_VALIDATE_EMAIL) && strlen($e) <= 190) $out[strtolower($e)] = $e;
    }
    return array_slice(array_values($out), 0, RP_MAX_INDIRIZZI);
}

/** Il form (spento) sotto cui finiscono le richieste, in Invii dei form. Nasce alla prima richiesta. */
function rp_form_id(): int {
    form_schema();
    $st = db()->prepare('SELECT id FROM ' . tbl('moduli') . ' WHERE slug = ?');
    $st->execute([RP_SLUG]);
    $id = (int)$st->fetchColumn();
    if ($id) return $id;
    $campi = json_encode([['nome' => 'richiesta', 'etichetta' => 'Richiesta', 'tipo' => 'testolungo', 'obbligatorio' => false, 'segnaposto' => '', 'opzioni' => [], 'meta' => '']], JSON_UNESCAPED_UNICODE);
    db()->prepare('INSERT INTO ' . tbl('moduli') . " (nome, slug, titolo, intro, campi, pulsante, consenso, lista_id, lista_conferma, avviso, avviso_a, omaggio, grazie_titolo, grazie_testo, grazie_link, attivo, creato, aggiornato)
        VALUES ('Richieste di prenotazione', ?, '', 'Le richieste che arrivano dal widget Richiesta di prenotazione.', ?, 'Invia', '', 0, 0, 0, '', '', '', '', '', 0, NOW(), NOW())")->execute([RP_SLUG, $campi]);
    return (int)db()->lastInsertId();
}

function rp_data_it(string $d): string {
    $x = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    return $x ? $x->format('d/m/Y') : $d;
}

/**
 * Riceve una richiesta. $p: il POST. Ritorna '' oppure il codice d'errore (RP_ERRORI o MODULI_ERRORI).
 * L'indirizzo a cui va l'email viene solo dalla configurazione firmata.
 */
function rp_ricevi(array $p, string $pagina): string {
    if (($p['sito_web'] ?? '') !== '') return 'robot';
    $cfg = rp_verifica((string)($p['rp_cfg'] ?? ''), (string)($p['rp_firma'] ?? ''));
    if (!$cfg) return 'robot';
    $alloggio = ($cfg['modalita'] ?? '') === 'alloggio';
    $max = max(1, min(500, (int)($cfg['max'] ?? 20)));
    $t = fn(string $k, int $n = 300) => mb_substr(preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', trim((string)(is_scalar($p[$k] ?? '') ? ($p[$k] ?? '') : ''))), 0, $n);
    $oggi = date('Y-m-d');
    $limite = date('Y-m-d', strtotime('+2 years'));
    $data = $t('data', 10);
    $okData = fn(string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && DateTimeImmutable::createFromFormat('!Y-m-d', $d) && $d >= $oggi && $d <= $limite;
    if (!$okData($data)) return 'data';
    $dati = [];
    if ($alloggio) {
        $partenza = $t('partenza', 10);
        if (!$okData($partenza)) return 'data';
        if ($partenza <= $data) return 'partenza';
        $notti = (int)round((strtotime($partenza) - strtotime($data)) / 86400);
        $dati['Arrivo'] = rp_data_it($data);
        $dati['Partenza'] = rp_data_it($partenza) . ' (' . $notti . ($notti === 1 ? ' notte' : ' notti') . ')';
    } else {
        $ora = $t('ora', 5);
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $ora)) return 'ora';
        $dati['Data'] = rp_data_it($data);
        $dati['Ora'] = $ora;
    }
    $persone = $t('persone', 4);
    if (!ctype_digit($persone) || (int)$persone < 1 || (int)$persone > $max) return 'persone';
    $dati[$alloggio ? 'Ospiti' : 'Persone'] = (string)(int)$persone;
    $nome = $t('nome', 100);
    if ($nome === '') return 'nome';
    $email = mb_strtolower($t('email', 190));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'email';
    $tel = $t('telefono', 30);
    if ($tel !== '' && !preg_match('/^\+?[0-9 .\/()-]{6,30}$/', $tel)) return 'telefono';
    if (!empty($cfg['tel_obbligatorio']) && $tel === '') return 'telefono';
    $dati['Nome'] = $nome;
    $dati['Email'] = $email;
    if ($tel !== '') $dati['Telefono'] = $tel;
    $note = $t('note', 2000);
    if ($note !== '') $dati['Note'] = $note;
    if (empty($p['privacy'])) return 'privacy';
    if (!moduli_captcha_valido((string)($p['captcha'] ?? ''))) return 'captcha';
    form_schema();
    if (moduli_troppi('moduli_invii')) return 'troppi';
    $ora = moduli_adesso();
    $quale = mb_substr(trim((string)($cfg['titolo'] ?? '')), 0, 120);
    $dati = ['Tipo' => ($alloggio ? 'Alloggio' : 'Tavolo') . ($quale !== '' ? ' (' . $quale . ')' : '')] + $dati;
    db()->prepare('INSERT INTO ' . tbl('moduli_invii') . ' (modulo_id, email, nome, dati, pagina, ip, creato, aggiornato) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([rp_form_id(), $email, $nome, json_encode($dati, JSON_UNESCAPED_UNICODE), mb_substr($pagina, 0, 190), moduli_ip(), $ora, $ora]);
    $a = rp_indirizzi(implode(',', (array)($cfg['a'] ?? [])));
    if (!$a && posta_notifica_email() !== '') $a = [posta_notifica_email()];
    $oggetto = 'Richiesta di prenotazione: ' . ($alloggio ? 'dal ' . $dati['Arrivo'] . ', ' . $persone . ' ospiti' : $dati['Data'] . ' alle ' . $dati['Ora'] . ', ' . $persone . ($persone === '1' ? ' persona' : ' persone'));
    $righe = implode('', array_map(fn($k, $v) => '<p style="margin:0 0 10px"><b>' . h($k) . ':</b> ' . nl2br(h($v)) . '</p>', array_keys($dati), $dati));
    $corpo = moduli_email_html('Nuova richiesta di prenotazione', $righe . '<p>Rispondi a questa email per scrivere a ' . h($nome) . ': la richiesta non è ancora confermata.</p><p style="color:#888;font-size:13px">Dalla pagina ' . h($pagina) . ', il ' . date('d/m/Y H:i') . '.</p>', 'Apri nel pannello', cms_url_pannello() . 'form-submissions.php?modulo=' . rp_form_id());
    foreach ($a as $x) {
        if (!posta_invia($x, $oggetto, $corpo, moduli_intestazioni($email), 'avviso_form')) error_log('richiesta di prenotazione: avviso non partito: ' . posta_errore());
    }
    return '';
}

/** Il messaggio di un codice d'errore della richiesta. */
function rp_errore(string $codice): string {
    return RP_ERRORI[$codice] ?? (MODULI_ERRORI[$codice] ?? '');
}
