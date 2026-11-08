<?php
/**
 * Addon AI collegate: l'autorizzazione OAuth 2.1. Il sito è insieme server di autorizzazione e risorsa protetta (il server
 * MCP). Metadati della risorsa (RFC 9728) e del server (RFC 8414, anche come openid-configuration), registrazione dinamica
 * dei client (RFC 7591), codice con PKCE S256 obbligatorio, parametro resource (RFC 8707), token di accesso di un'ora e di
 * rinnovo di trenta giorni ruotato a ogni uso, revoca (RFC 7009). Il consenso si dà nel pannello (admin-consenso.php),
 * solo da un amministratore. Manuale tecnico, capitolo 47.
 */
require_once __DIR__ . '/ai.php';

/** Una risposta JSON e fine. */
function aic_json(int $codice, $dati, array $intestazioni = []): never {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    foreach ($intestazioni as $h) header($h);
    http_response_code($codice); // dopo le intestazioni: WWW-Authenticate porterebbe PHP a rispondere 401 anche per un 403
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') echo json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function aic_oauth_errore(int $codice, string $errore, string $descrizione): never {
    aic_json($codice, ['error' => $errore, 'error_description' => $descrizione]);
}

/** I metadati della risorsa protetta (RFC 9728). */
function aic_metadati_risorsa(): array {
    return [
        'resource' => aic_url_mcp(),
        'authorization_servers' => [aic_issuer()],
        'scopes_supported' => array_keys(AIC_PERMESSI),
        'bearer_methods_supported' => ['header'],
        'resource_name' => (string)cms_impostazione('sito_nome', CMS_NOME),
        'resource_documentation' => aic_issuer() . '/api/mcp/openapi',
    ];
}

/** I metadati del server di autorizzazione (RFC 8414); gli stessi escono come openid-configuration. */
function aic_metadati_server(): array {
    $b = aic_issuer() . '/api/mcp';
    return [
        'issuer' => aic_issuer(),
        'authorization_endpoint' => $b . '/authorize',
        'token_endpoint' => $b . '/token',
        'registration_endpoint' => $b . '/register',
        'revocation_endpoint' => $b . '/revoke',
        'response_types_supported' => ['code'],
        'response_modes_supported' => ['query'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
        'code_challenge_methods_supported' => ['S256'],
        'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
        'revocation_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
        'scopes_supported' => array_keys(AIC_PERMESSI),
        'authorization_response_iss_parameter_supported' => true,
        'client_id_metadata_document_supported' => false,
        'subject_types_supported' => ['public'],
        'service_documentation' => aic_issuer() . '/api/mcp/openapi',
    ];
}

// ------------------------------------------------------------
// Registrazione dinamica dei client (RFC 7591)
// ------------------------------------------------------------

/** Un redirect_uri ammesso: https, oppure http solo verso localhost o 127.0.0.1 (le app sul computer). Mai frammenti né credenziali. */
function aic_redirect_valido(string $u): bool {
    if ($u === '' || strlen($u) > 500 || preg_match('/[\s"<>\\\\]/', $u)) return false;
    $p = parse_url($u);
    if (!$p || empty($p['scheme']) || empty($p['host']) || isset($p['fragment']) || isset($p['user']) || isset($p['pass'])) return false;
    $s = strtolower($p['scheme']);
    $h = strtolower(trim($p['host'], '[]'));
    if ($s === 'https') return true;
    return $s === 'http' && in_array($h, ['localhost', '127.0.0.1', '::1'], true);
}

function aic_client(string $id): ?array {
    if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $id)) return null;
    aic_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('ai_client') . ' WHERE client_id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) return null;
    $r['redirect'] = array_values(array_filter((array)json_decode((string)$r['redirect_uris'], true), 'is_string'));
    return $r;
}

function aic_registra_client(): never {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') aic_json(405, ['error' => 'invalid_request', 'error_description' => 'Usa POST.'], ['Allow: POST']);
    if (!aic_limite('reg:' . aic_ip_hash(), AIC_LIMITE_REGISTRAZIONI, 86400)) aic_oauth_errore(429, 'invalid_request', 'Troppe registrazioni da questo indirizzo oggi: riprova domani.');
    $corpo = (string)file_get_contents('php://input', false, null, 0, 20001);
    if (strlen($corpo) > 20000) aic_oauth_errore(400, 'invalid_client_metadata', 'Richiesta troppo grande.');
    $d = json_decode($corpo, true);
    if (!is_array($d)) aic_oauth_errore(400, 'invalid_client_metadata', 'Il corpo deve essere un oggetto JSON.');
    $uris = $d['redirect_uris'] ?? null;
    if (!is_array($uris) || !$uris || count($uris) > 10) aic_oauth_errore(400, 'invalid_redirect_uri', 'Servono da 1 a 10 redirect_uris.');
    foreach ($uris as $u) if (!is_string($u) || !aic_redirect_valido($u)) aic_oauth_errore(400, 'invalid_redirect_uri', 'Ogni redirect_uri deve essere https (oppure http verso localhost o 127.0.0.1), senza frammento.');
    $metodo = (string)($d['token_endpoint_auth_method'] ?? 'none');
    if (!in_array($metodo, ['none', 'client_secret_post'], true)) aic_oauth_errore(400, 'invalid_client_metadata', 'token_endpoint_auth_method ammessi: none, client_secret_post.');
    foreach ((array)($d['grant_types'] ?? ['authorization_code']) as $g) if (!in_array($g, ['authorization_code', 'refresh_token'], true)) aic_oauth_errore(400, 'invalid_client_metadata', 'grant_types ammessi: authorization_code, refresh_token.');
    foreach ((array)($d['response_types'] ?? ['code']) as $g) if ($g !== 'code') aic_oauth_errore(400, 'invalid_client_metadata', 'response_types ammesso: code.');
    $nome = aic_testo($d['client_name'] ?? '', 100) ?: 'AI senza nome';
    $sito = aic_testo($d['client_uri'] ?? '', 300);
    if ($sito !== '' && !preg_match('~^https://[^\s"<>]+$~', $sito)) $sito = '';
    $id = 'aic_' . bin2hex(random_bytes(16));
    $segreto = $metodo === 'client_secret_post' ? aic_token_nuovo('pcs_') : '';
    aic_schema();
    db()->prepare('INSERT INTO ' . tbl('ai_client') . ' (client_id, segreto_hash, nome, sito, redirect_uris, ip_hash, creato) VALUES (?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$id, $segreto !== '' ? aic_hash($segreto) : '', $nome, $sito, json_encode(array_values($uris), JSON_UNESCAPED_SLASHES), aic_ip_hash()]);
    aic_registra(['nome' => $nome], 'registrazione', 'ok', 'client registrato');
    $out = ['client_id' => $id, 'client_id_issued_at' => time(), 'client_name' => $nome, 'redirect_uris' => array_values($uris),
        'grant_types' => ['authorization_code', 'refresh_token'], 'response_types' => ['code'], 'token_endpoint_auth_method' => $metodo, 'scope' => implode(' ', array_keys(AIC_PERMESSI))];
    if ($sito !== '') $out['client_uri'] = $sito;
    if ($segreto !== '') { $out['client_secret'] = $segreto; $out['client_secret_expires_at'] = 0; }
    aic_json(201, $out);
}

// ------------------------------------------------------------
// Autorizzazione: controlli, poi il pannello (login e consenso)
// ------------------------------------------------------------

/** Una pagina d'errore semplice (prima di aver controllato il redirect_uri non si rimanda da nessuna parte). */
function aic_pagina_errore(string $titolo, string $testo): never {
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . h($titolo) . '</title>'
        . '<style>body{font:16px/1.5 system-ui,sans-serif;max-width:40rem;margin:3rem auto;padding:0 16px;color:#1d2433;background:#f6f7fb}h1{font-size:1.4rem}.box{background:#fff;border:1px solid #c5cad6;border-radius:10px;padding:1rem 1.2rem}</style></head><body>'
        . '<div class="box" role="alert"><h1>' . h($titolo) . '</h1><p>' . h($testo) . '</p></div></body></html>';
    exit;
}

function aic_rimanda_errore(string $redirect, string $state, string $errore, string $descrizione): never {
    $q = ['error' => $errore, 'error_description' => $descrizione, 'iss' => aic_issuer()];
    if ($state !== '') $q['state'] = $state;
    header('Cache-Control: no-store');
    header('Location: ' . $redirect . (str_contains($redirect, '?') ? '&' : '?') . http_build_query($q), true, 302);
    exit;
}

function aic_autorizza(array $q): never {
    aic_schema();
    $clientId = is_string($q['client_id'] ?? null) ? $q['client_id'] : '';
    $redirect = is_string($q['redirect_uri'] ?? null) ? $q['redirect_uri'] : '';
    $c = aic_client($clientId);
    if (!$c) aic_pagina_errore('Collegamento non riconosciuto', 'La tua AI non si è registrata su questo sito (client_id sconosciuto). Ricomincia il collegamento dalla tua AI.');
    if ($redirect === '' && count($c['redirect']) === 1) $redirect = $c['redirect'][0];
    if (!in_array($redirect, $c['redirect'], true)) aic_pagina_errore('Indirizzo di ritorno sbagliato', 'L\'indirizzo a cui tornare (redirect_uri) non è quello registrato dalla tua AI. Per sicurezza il collegamento si ferma qui.');
    $state = is_string($q['state'] ?? null) ? mb_substr($q['state'], 0, 500) : '';
    if (($q['response_type'] ?? '') !== 'code') aic_rimanda_errore($redirect, $state, 'unsupported_response_type', 'Solo response_type=code.');
    $sfida = is_string($q['code_challenge'] ?? null) ? $q['code_challenge'] : '';
    if (($q['code_challenge_method'] ?? '') !== 'S256' || !preg_match('/^[A-Za-z0-9_-]{43,128}$/', $sfida)) aic_rimanda_errore($redirect, $state, 'invalid_request', 'PKCE obbligatorio: code_challenge con code_challenge_method=S256.');
    $risorsa = is_string($q['resource'] ?? null) ? $q['resource'] : '';
    if ($risorsa !== '' && !aic_risorsa_valida($risorsa)) aic_rimanda_errore($redirect, $state, 'invalid_target', 'La risorsa chiesta non è il server MCP di questo sito (' . aic_url_mcp() . ').');
    $chiesti = isset($q['scope']) && trim((string)$q['scope']) !== '' ? preg_split('/\s+/', trim((string)$q['scope'])) : AIC_PERMESSI_PARTENZA;
    foreach ($chiesti as $s) if (!isset(AIC_PERMESSI[$s])) aic_rimanda_errore($redirect, $state, 'invalid_scope', 'Permesso sconosciuto: ' . mb_substr((string)$s, 0, 40) . '. Ammessi: ' . implode(', ', array_keys(AIC_PERMESSI)) . '.');
    $id = bin2hex(random_bytes(16));
    db()->prepare('INSERT INTO ' . tbl('ai_richieste') . ' (id, dati, scade) VALUES (?, ?, ?)')->execute([$id, json_encode([
        'client_id' => $clientId, 'redirect_uri' => $redirect, 'state' => $state, 'challenge' => $sfida, 'resource' => $risorsa !== '' ? aic_url_mcp() : '',
        'scope' => aic_scope_pulito($chiesti),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s', time() + AIC_RICHIESTA_SECONDI)]);
    if (random_int(1, 20) === 1) aic_pulisci();
    // Al pannello: chi non è entrato fa il login (anche con il secondo fattore) e torna al consenso; chi è entrato ci va dritto.
    header('Cache-Control: no-store');
    header('Location: ' . cms_url_pannello() . 'login.php?dopo=ai-collegate&r=' . $id, true, 302);
    exit;
}

/** Una richiesta di autorizzazione in attesa (per la pagina del consenso). */
function aic_richiesta(string $id): ?array {
    if (!preg_match('/^[a-f0-9]{32}$/', $id)) return null;
    aic_schema();
    $st = db()->prepare('SELECT dati FROM ' . tbl('ai_richieste') . ' WHERE id = ? AND scade >= NOW()');
    $st->execute([$id]);
    $d = json_decode((string)$st->fetchColumn(), true);
    if (!is_array($d)) return null;
    $c = aic_client((string)$d['client_id']);
    if (!$c || !in_array($d['redirect_uri'], $c['redirect'], true)) return null;
    return $d + ['client' => $c, 'id' => $id];
}

/** Consenso dato: il codice monouso (dieci minuti) e l'indirizzo a cui tornare. */
function aic_consenso_dato(array $r, int $utente, array $scope): string {
    $codice = aic_token_nuovo('');
    db()->prepare('INSERT INTO ' . tbl('ai_codici') . ' (hash, client_id, utente_id, redirect_uri, scope, challenge, resource, scade) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([aic_hash($codice), $r['client_id'], $utente, $r['redirect_uri'], implode(' ', $scope), $r['challenge'], (string)$r['resource'], date('Y-m-d H:i:s', time() + AIC_CODICE_SECONDI)]);
    db()->prepare('DELETE FROM ' . tbl('ai_richieste') . ' WHERE id = ?')->execute([$r['id']]);
    $q = ['code' => $codice, 'iss' => aic_issuer()];
    if ($r['state'] !== '') $q['state'] = $r['state'];
    return $r['redirect_uri'] . (str_contains($r['redirect_uri'], '?') ? '&' : '?') . http_build_query($q);
}

function aic_consenso_negato(array $r): string {
    db()->prepare('DELETE FROM ' . tbl('ai_richieste') . ' WHERE id = ?')->execute([$r['id']]);
    $q = ['error' => 'access_denied', 'error_description' => 'L\'amministratore non ha dato il permesso.', 'iss' => aic_issuer()];
    if ($r['state'] !== '') $q['state'] = $r['state'];
    return $r['redirect_uri'] . (str_contains($r['redirect_uri'], '?') ? '&' : '?') . http_build_query($q);
}

// ------------------------------------------------------------
// Token e revoca
// ------------------------------------------------------------

/** Il client della richiesta al token: client_id e, se registrato con un segreto, client_secret giusto. */
function aic_client_della_richiesta(array $p): array {
    $c = aic_client((string)($p['client_id'] ?? ''));
    if (!$c) aic_oauth_errore(401, 'invalid_client', 'client_id sconosciuto.');
    if ($c['segreto_hash'] !== '' && !hash_equals($c['segreto_hash'], aic_hash((string)($p['client_secret'] ?? '')))) aic_oauth_errore(401, 'invalid_client', 'client_secret sbagliato.');
    return $c;
}

function aic_risposta_token(int $conn, string $scope): never {
    $accesso = aic_token_crea($conn, 'accesso', AIC_ACCESSO_SECONDI);
    $rinnovo = aic_token_crea($conn, 'rinnovo', AIC_RINNOVO_GIORNI * 86400);
    aic_json(200, ['access_token' => $accesso, 'token_type' => 'Bearer', 'expires_in' => AIC_ACCESSO_SECONDI, 'refresh_token' => $rinnovo, 'scope' => $scope], ['Pragma: no-cache']);
}

function aic_token(): never {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') aic_json(405, ['error' => 'invalid_request', 'error_description' => 'Usa POST.'], ['Allow: POST']);
    if (!aic_limite('tok:' . aic_ip_hash(), AIC_LIMITE_TOKEN, 3600)) aic_oauth_errore(429, 'invalid_request', 'Troppe richieste di token da questo indirizzo: riprova fra un\'ora.');
    $p = $_POST;
    if (!$p) { parse_str((string)file_get_contents('php://input', false, null, 0, 20000), $p); }
    aic_schema();
    $tipo = (string)($p['grant_type'] ?? '');
    if ($tipo === 'authorization_code') {
        $c = aic_client_della_richiesta($p);
        $st = db()->prepare('SELECT * FROM ' . tbl('ai_codici') . ' WHERE hash = ?');
        $st->execute([aic_hash((string)($p['code'] ?? ''))]);
        $cod = $st->fetch();
        if (!$cod || $cod['client_id'] !== $c['client_id']) aic_oauth_errore(400, 'invalid_grant', 'Codice sconosciuto.');
        if ($cod['usato'] !== null) {
            // Un codice usato due volte: qualcuno lo ha rubato. Si chiude anche la connessione nata dal primo uso (RFC 6749, 4.1.2).
            if ((int)$cod['connessione_id'] > 0) aic_connessione_revoca((int)$cod['connessione_id']);
            aic_registra(['nome' => $c['nome']], 'token', 'negato', 'codice usato due volte: connessione revocata');
            aic_oauth_errore(400, 'invalid_grant', 'Codice già usato.');
        }
        if (strtotime((string)$cod['scade']) < time()) aic_oauth_errore(400, 'invalid_grant', 'Codice scaduto: ricomincia il collegamento.');
        if ((string)($p['redirect_uri'] ?? $cod['redirect_uri']) !== $cod['redirect_uri']) aic_oauth_errore(400, 'invalid_grant', 'redirect_uri diverso da quello dell\'autorizzazione.');
        $verifica = (string)($p['code_verifier'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifica) || !hash_equals($cod['challenge'], rtrim(strtr(base64_encode(hash('sha256', $verifica, true)), '+/', '-_'), '='))) aic_oauth_errore(400, 'invalid_grant', 'PKCE: code_verifier mancante o sbagliato.');
        $ris = (string)($p['resource'] ?? '');
        if ($ris !== '' && !aic_risorsa_valida($ris)) aic_oauth_errore(400, 'invalid_target', 'La risorsa chiesta non è il server MCP di questo sito.');
        $u = db()->prepare('UPDATE ' . tbl('ai_codici') . ' SET usato = NOW() WHERE id = ? AND usato IS NULL');
        $u->execute([(int)$cod['id']]);
        if ($u->rowCount() !== 1) aic_oauth_errore(400, 'invalid_grant', 'Codice già usato.');
        $utente = db()->prepare('SELECT id, role FROM ' . tbl('users') . ' WHERE id = ?');
        $utente->execute([(int)$cod['utente_id']]);
        $ut = $utente->fetch();
        if (!$ut || ($ut['role'] ?? '') !== 'admin') aic_oauth_errore(400, 'invalid_grant', 'Chi ha dato il permesso non è più amministratore.');
        $scope = aic_scope_pulito((string)$cod['scope']);
        $conn = aic_connessione_crea('oauth', $c['client_id'], (string)$c['nome'], (int)$cod['utente_id'], $scope);
        db()->prepare('UPDATE ' . tbl('ai_codici') . ' SET connessione_id = ? WHERE id = ?')->execute([$conn, (int)$cod['id']]);
        aic_registra(['id' => $conn, 'nome' => $c['nome']], 'collegamento', 'ok', 'permessi: ' . implode(', ', $scope));
        aic_risposta_token($conn, implode(' ', $scope));
    }
    if ($tipo === 'refresh_token') {
        $c = aic_client_della_richiesta($p);
        $st = db()->prepare('SELECT t.*, c.client_id, c.scope, c.revocato, c.nome FROM ' . tbl('ai_token') . ' t JOIN ' . tbl('ai_connessioni') . " c ON c.id = t.connessione_id WHERE t.hash = ? AND t.tipo = 'rinnovo'");
        $st->execute([aic_hash((string)($p['refresh_token'] ?? ''))]);
        $t = $st->fetch();
        if (!$t || $t['client_id'] !== $c['client_id'] || $t['revocato'] !== null) aic_oauth_errore(400, 'invalid_grant', 'Token di rinnovo sconosciuto o revocato.');
        if ($t['usato'] !== null) {
            // Rinnovo già usato: è stato copiato. Si chiude tutta la connessione.
            aic_connessione_revoca((int)$t['connessione_id']);
            aic_registra(['id' => (int)$t['connessione_id'], 'nome' => $t['nome']], 'token', 'negato', 'token di rinnovo usato due volte: connessione revocata');
            aic_oauth_errore(400, 'invalid_grant', 'Token di rinnovo già usato: la connessione è stata chiusa per sicurezza.');
        }
        if ($t['scade'] !== null && strtotime((string)$t['scade']) < time()) aic_oauth_errore(400, 'invalid_grant', 'Token di rinnovo scaduto: ricollega la tua AI.');
        if (!aic_risorsa_valida((string)$t['audience'])) aic_oauth_errore(400, 'invalid_grant', 'Token di un altro indirizzo.');
        $u = db()->prepare('UPDATE ' . tbl('ai_token') . ' SET usato = NOW() WHERE id = ? AND usato IS NULL');
        $u->execute([(int)$t['id']]);
        if ($u->rowCount() !== 1) aic_oauth_errore(400, 'invalid_grant', 'Token di rinnovo già usato.');
        $scope = aic_scope_pulito((string)$t['scope']);
        if (isset($p['scope']) && trim((string)$p['scope']) !== '') {
            $chiesti = preg_split('/\s+/', trim((string)$p['scope']));
            foreach ($chiesti as $s) if (!in_array($s, $scope, true)) aic_oauth_errore(400, 'invalid_scope', 'Con il rinnovo non si allargano i permessi.');
            $scope = aic_scope_pulito($chiesti);
            db()->prepare('UPDATE ' . tbl('ai_connessioni') . ' SET scope = ? WHERE id = ?')->execute([implode(' ', $scope), (int)$t['connessione_id']]);
        }
        // Il vecchio token di accesso resta valido fino alla sua scadenza (al massimo un'ora); il rinnovo usato non vale più.
        aic_risposta_token((int)$t['connessione_id'], implode(' ', $scope));
    }
    aic_oauth_errore(400, 'unsupported_grant_type', 'grant_type ammessi: authorization_code, refresh_token.');
}

function aic_revoca(): never {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') aic_json(405, ['error' => 'invalid_request'], ['Allow: POST']);
    if (!aic_limite('tok:' . aic_ip_hash(), AIC_LIMITE_TOKEN, 3600)) aic_oauth_errore(429, 'invalid_request', 'Troppe richieste.');
    $p = $_POST;
    if (!$p) { parse_str((string)file_get_contents('php://input', false, null, 0, 20000), $p); }
    aic_schema();
    $st = db()->prepare('SELECT t.id, t.tipo, t.connessione_id, c.client_id, c.nome FROM ' . tbl('ai_token') . ' t JOIN ' . tbl('ai_connessioni') . ' c ON c.id = t.connessione_id WHERE t.hash = ?');
    $st->execute([aic_hash((string)($p['token'] ?? ''))]);
    $t = $st->fetch();
    // RFC 7009: un token sconosciuto risponde 200 lo stesso. Un token di un altro client non si tocca.
    if ($t && $t['client_id'] === (string)($p['client_id'] ?? $t['client_id'])) {
        if ($t['tipo'] === 'accesso') db()->prepare('DELETE FROM ' . tbl('ai_token') . ' WHERE id = ?')->execute([(int)$t['id']]);
        else aic_connessione_revoca((int)$t['connessione_id']);
        aic_registra(['id' => (int)$t['connessione_id'], 'nome' => $t['nome']], 'revoca', 'ok', 'revocato dalla tua AI (' . $t['tipo'] . ')');
    }
    http_response_code(200);
    header('Cache-Control: no-store');
    exit;
}
