<?php
/**
 * Aiuti da riga di comando per prova-mittente.js (2.40.1, il nome del mittente nelle email), su un CMS installato. Mai nel prodotto.
 * uso: php prove/mittente-cli.php <cartella CMS> <comando> [argomenti]
 *   unita <cartella>              per ogni nome di prova scrive in <cartella> i messaggi composti dalle funzioni vere
 *                                 (consegna_mime_intero(), che passa da posta_mime() e posta_nome_indirizzo(), e posta_mime() da
 *                                 sola come per mail()) e <cartella>/attesi.json con i nomi attesi; stampa i controlli fatti in PHP
 *   sql "<query>"                 righe in JSON ({p} diventa il prefisso delle tabelle)
 *   consegna <servizio> [porta]   sceglie il servizio di Consegna delle email: smtp, brevo, ses, mailgun (chiavi delle prove,
 *                                 API finte su 127.0.0.1:<porta> con CMS_POSTA_API_PROVA scritto nel config dalla prova)
 *   nuovo-ip                      fa sembrare nuovo l'IP 127.0.0.1 per il prossimo accesso dell'amministratore
 *                                 (l'avviso "Accesso amministratore da un indirizzo nuovo")
 *   impostazione <chiave>         il valore grezzo di un'impostazione
 *   avvisi <visitatore> <ip>      i cinque avvisi con il Reply-To del visitatore, dalle funzioni vere (come prove/avvisi-cli.php:
 *                                 form, richiesta di prenotazione, Prenotazioni, Vetrina, Iscrizioni agli eventi), con quel nome del
 *                                 visitatore; la posta resta quella scelta dal pannello. Stampa gli esiti
 *   newsletter <mittente> <campo A>  un'email della newsletter come la compone una campagna (newsletter_email()), con il mittente
 *                                 confermato notizie@esempio.it chiamato <mittente> e il campo "A" <campo A>, a iscritto@esempio.it
 *   oggetto <testo> [a]           un'email con quell'oggetto (anche con gli a capo dentro) all'indirizzo degli avvisi, o ad <a>
 */
[$dir, $cmd] = array_pad(array_slice($argv, 1, 2), 2, '');
$arg = array_slice($argv, 3);
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: mittente-cli.php <cartella CMS> <comando> [argomenti]\n"); exit(2); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/moduli.php';
require_once $dir . '/includes/posta.php';
require_once $dir . '/includes/consegna.php';

/** I nomi delle prove: i cinque della richiesta, uno lungo non ASCII e uno con l'a capo dentro (iniezione). */
const MITT_NOMI = [
    '[Cucina Creativa]',
    'Rossi, Mario',
    'Caffè "Da Mario"',
    'Cucina Creativa',
    'Pasta & Vino: il laboratorio',
    'Trattoria della Città Àlta: cucina più genuina, già così da trent\'anni',
    "Cucina\r\nBcc: spia@esempio.it",
];

$out = null;
switch ($cmd) {
    case 'unita':
        $cart = (string)($arg[0] ?? '');
        if ($cart === '') { fwrite(STDERR, "manca la cartella\n"); exit(2); }
        @mkdir($cart, 0777, true);
        $attesi = []; $controlli = []; $n = 0;
        $ha = function_exists('posta_nome_indirizzo');
        foreach (MITT_NOMI as $i => $nome) {
            // il nome atteso: com'è scritto, con gli a capo diventati uno spazio
            $atteso = trim((string)preg_replace('/[\x00-\x1F\x7F]+/', ' ', $nome));
            $visitatore = 'Visitatore ' . $nome;
            $attVis = trim((string)preg_replace('/[\x00-\x1F\x7F]+/', ' ', $visitatore));
            $int = moduli_intestazioni('cliente' . $i . '@esempio.it', $visitatore) . 'X-Plume-A: ' . base64_encode($nome) . "\r\n";
            $in = posta_interne($int);
            // 1. il messaggio intero come per SES e Mailgun (e come SMTP: From, To, Subject, poi posta_mime())
            $raw = consegna_mime_intero('destinatario' . $i . '@esempio.it', 'Oggetto: ' . $nome, '<p>Ciao</p>', $int, [], 'mittente@esempio.it', $nome, $in);
            file_put_contents("$cart/" . (++$n) . '.eml', $raw);
            $attesi[] = ['file' => "$n.eml", 'from' => [$atteso, 'mittente@esempio.it'], 'to' => [$atteso, 'destinatario' . $i . '@esempio.it'],
                'reply_to' => [$attVis, 'cliente' . $i . '@esempio.it'], 'subject' => 'Oggetto: ' . $atteso];
            // 2. come mail(): il From scritto da posta_invia() con posta_nome_indirizzo() e il resto da posta_mime()
            [$tenute, $contenuto, $testo] = posta_mime('<p>Ciao</p>', $int);
            $da = $ha ? posta_nome_indirizzo($nome, 'mittente@esempio.it') : posta_oggetto($nome) . ' <mittente@esempio.it>';
            $a = $ha ? posta_to('destinatario' . $i . '@esempio.it', $nome) : posta_oggetto($nome) . ' <destinatario' . $i . '@esempio.it>';
            $testa = array_merge(['From: ' . $da, 'To: ' . $a, 'Subject: ' . posta_oggetto('Oggetto: ' . $nome)], $tenute, $contenuto);
            file_put_contents("$cart/" . (++$n) . '.eml', implode("\r\n", $testa) . "\r\n\r\n" . $testo);
            $attesi[] = end($attesi); $attesi[count($attesi) - 1]['file'] = "$n.eml";
            if ($ha) {
                $l = posta_leggi_indirizzo(posta_nome_indirizzo($nome, 'x@esempio.it'));
                $controlli[] = ['ok' => $l !== null && $l[0] === $atteso && $l[1] === 'x@esempio.it', 'cosa' => 'posta_leggi_indirizzo rilegge ' . json_encode($atteso, JSON_UNESCAPED_UNICODE)];
                $mitt = consegna_mittente_per('From: ' . posta_nome_indirizzo($nome, 'x@esempio.it'), ['', '']);
                $controlli[] = ['ok' => true, 'cosa' => 'consegna_mittente_per letto (mittente non confermato: torna al predefinito)', 'dettaglio' => $mitt];
            } else {
                $controlli[] = ['ok' => false, 'cosa' => 'posta_nome_indirizzo() non esiste (versione prima della 2.40.1)'];
            }
        }
        // nome vuoto: solo l'indirizzo
        if ($ha) $controlli[] = ['ok' => posta_nome_indirizzo('', 'a@esempio.it') === 'a@esempio.it', 'cosa' => 'nome vuoto: solo l\'indirizzo'];
        // oggetto lungo con lettere accentate: parole da 75 caratteri al massimo
        $og = posta_oggetto(str_repeat('Più è lungo, più si spezza: ', 6));
        $max = max(array_map('strlen', preg_split('/\s+/', $og)));
        $controlli[] = ['ok' => $max <= 75, 'cosa' => 'oggetto lungo in parole codificate da 75 caratteri al massimo', 'dettaglio' => $max];
        file_put_contents("$cart/attesi.json", json_encode($attesi, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $out = $controlli;
        break;
    case 'sql':
        $st = db()->query(str_replace('{p}', DB_PREFIX, (string)($arg[0] ?? '')));
        $out = $st->columnCount() ? $st->fetchAll() : $st->rowCount();
        break;
    case 'consegna':
        $s = (string)($arg[0] ?? 'smtp');
        consegna_schema();
        $c = consegna_config();
        $c['servizio'] = $s;
        if ($s === 'brevo') $c['brevo']['chiave'] = consegna_cifra('xkeysib-' . str_repeat('a1b2c3d4', 8) . '-ProvaBrevo123456');
        if ($s === 'mailgun') {
            $c['mailgun']['dominio'] = 'mg.esempio.it';
            $c['mailgun']['regione'] = 'eu';
            $c['mailgun']['chiave'] = consegna_cifra('key-' . str_repeat('f0e1d2c3', 4));
        }
        if ($s === 'ses') {
            $c['ses']['regione'] = 'eu-west-1';
            $c['ses']['id_chiave'] = 'AKIAPROVAPROVA123456';
            $c['ses']['segreto'] = consegna_cifra('Prova/Segreta+Amazon' . str_repeat('X', 20));
        }
        consegna_config_salva($c);
        $out = consegna_servizio_attivo();
        break;
    case 'nuovo-ip':
        $out = db()->exec('UPDATE ' . tbl('sicurezza_log') . " SET ip = '10.250.250.250' WHERE evento = 'login'");
        break;
    case 'impostazione':
        $out = get_setting((string)($arg[0] ?? ''), null);
        break;
    case 'avvisi':
        $vis = (string)($arg[0] ?? 'Maria Rossi');
        $_SERVER['REMOTE_ADDR'] = (string)($arg[1] ?? '10.9.9.9');
        $_SERVER['REQUEST_URI'] = '/';
        save_setting('notifica_email', 'negozio@esempio.it');
        $risolvi = function (): string {
            $s = moduli_captcha_sfida();
            for ($n = 0; $n <= $s['max']; $n++) if (hash('sha256', $s['sale'] . $n) === $s['sfida']) break;
            return base64_encode(json_encode($s + ['n' => $n]));
        };
        $out = [];
        // 1. Form, con il campo Nome legato al nome del contatto (meta "nome")
        require_once $dir . '/includes/form.php';
        form_schema();
        $id = (int)db()->query('SELECT id FROM ' . tbl('moduli') . " WHERE slug = 'prova-mittente'")->fetchColumn();
        if (!$id) $id = form_salva(0, ['nome' => 'Prova mittente', 'slug' => 'prova-mittente', 'attivo' => 1, 'avviso' => 1, 'campi' => [
            ['etichetta' => 'Nome', 'tipo' => 'testo', 'obbligatorio' => 1, 'meta' => 'nome'], ['etichetta' => 'Email', 'tipo' => 'email', 'obbligatorio' => 1], ['etichetta' => 'Messaggio', 'tipo' => 'testo_lungo']]]);
        db()->prepare('UPDATE ' . tbl('moduli') . ' SET attivo = 1, avviso = 1 WHERE id = ?')->execute([$id]);
        $m = form_leggi($id);
        $p = ['privacy' => '1', 'captcha' => $risolvi()];
        foreach ($m['campi'] as $c) $p['c_' . $c['nome']] = ['email' => 'cliente.form@esempio.it', 'testo' => $vis][$c['tipo']] ?? 'Avete posto sabato?';
        $out['form'] = form_ricevi($m, $p, '/contatti/');
        // 2. Richiesta di prenotazione
        require_once $dir . '/includes/richiesta-prenotazione.php';
        [$b, $f] = rp_firma(['modalita' => 'tavolo', 'max' => 20, 'a' => ['negozio@esempio.it'], 'titolo' => 'Cena']);
        $out['richiesta'] = rp_ricevi(['rp_cfg' => $b, 'rp_firma' => $f, 'data' => date('Y-m-d', strtotime('+3 days')), 'ora' => '20:30', 'persone' => '4',
            'nome' => $vis, 'email' => 'cliente.richiesta@esempio.it', 'privacy' => '1', 'captcha' => $risolvi()], '/prenota/');
        // 3. Addon Prenotazioni
        require_once $dir . '/addon/prenotazioni/includes/prenotazioni.php';
        pren_schema();
        $pr = ['id' => 1, 'nome' => $vis, 'email' => 'cliente.prenotazioni@esempio.it', 'telefono' => '', 'note' => '', 'stato' => 'da_confermare',
            'inizio' => date('Y-m-d 10:00:00', strtotime('+2 days')), 'fine' => date('Y-m-d 10:45:00', strtotime('+2 days')), 'servizio_id' => 0, 'risorsa_id' => 0, 'inizio_ts' => strtotime('+2 days 10:00'), 'fine_ts' => strtotime('+2 days 10:45')];
        try { $out['prenotazioni'] = pren_email_attivita($pr, 'nuova') ? '' : 'non partito'; } catch (Throwable $e) { $out['prenotazioni'] = 'errore: ' . $e->getMessage(); }
        // 4. Addon Vetrina (l'email al negozio)
        require_once $dir . '/addon/vetrina/includes/vetrina.php';
        vt_schema();
        $o = ['id' => 1, 'numero' => 'V-0001', 'nome' => $vis, 'email' => 'cliente.vetrina@esempio.it', 'telefono' => '333 1234567', 'metodo' => array_key_first(VT_METODI),
            'note' => '', 'totale_cent' => 1200, 'subtotale_cent' => 1200, 'spedizione_cent' => 0, 'iva_cent' => 216, 'stato' => 'nuovo', 'consegna' => 'ritiro', 'indirizzo' => '', 'righe' => [['nome' => 'Torta', 'variante' => '', 'qta' => 1, 'prezzo_cent' => 1200, 'totale_cent' => 1200]], 'chiave' => str_repeat('a', 32)];
        $vs = vt_impostazioni(); $vs['email_avvisi'] = 'negozio@esempio.it'; vt_impostazioni_salva($vs);
        try { $e = vt_email_ordine($o); $out['vetrina'] = $e['negozio'] ? '' : 'non partito'; } catch (Throwable $e) { $out['vetrina'] = 'errore: ' . $e->getMessage(); }
        // 5. Addon Iscrizioni agli eventi
        require_once $dir . '/addon/eventi-iscrizione/includes/eventi.php';
        evi_schema();
        $ev = ['id' => 1, 'titolo' => 'Corso di pasta fresca', 'posti' => 10, 'inizio' => date('Y-m-d 18:00:00', strtotime('+5 days')), 'fine' => date('Y-m-d 20:00:00', strtotime('+5 days')), 'luogo' => 'Bologna', 'indirizzo' => '', 'prezzo' => ''];
        $is = ['id' => 1, 'nome' => $vis, 'email' => 'cliente.eventi@esempio.it', 'telefono' => '', 'posti' => 1, 'stato' => 'confermata', 'note' => ''];
        try { $out['eventi'] = evi_email_attivita($ev, $is, 'nuova') ? '' : 'non partito'; } catch (Throwable $e) { $out['eventi'] = 'errore: ' . $e->getMessage(); }
        break;
    case 'newsletter':
        require_once $dir . '/includes/newsletter.php';
        newsletter_schema();
        consegna_schema();
        $mn = (string)($arg[0] ?? 'Notizie');
        db()->exec('DELETE FROM ' . tbl('posta_mittenti') . " WHERE email = 'notizie@esempio.it'");
        db()->prepare('INSERT INTO ' . tbl('posta_mittenti') . " (email, nome, rispondi_a, creato, verificato_at) VALUES ('notizie@esempio.it', ?, '', NOW(), NOW())")->execute([$mn]);
        $mid = (int)db()->lastInsertId();
        $c = ['id' => 0, 'oggetto' => 'Novità della settimana', 'anteprima' => '', 'corpo' => '<p>Ciao!</p>', 'editor' => 'semplice', 'pulsante_testo' => '', 'pulsante_link' => '',
            'campo_a' => (string)($arg[1] ?? ''), 'mittente_id' => $mid, 'rispondi_a' => ''];
        [$ogg, $html, $intest] = newsletter_email($c, ['id' => 0, 'nome' => 'Giulia', 'email' => 'iscritto@esempio.it'], []);
        $ok = posta_invia('iscritto@esempio.it', $ogg, $html, $intest, 'campagna');
        $out = [$ok, posta_esito(), posta_errore()];
        break;
    case 'oggetto':
        $a = (string)($arg[1] ?? '') ?: posta_notifica_email();
        $ok = posta_invia($a, (string)($arg[0] ?? ''), '<p>Prova</p>', moduli_intestazioni(), 'prova');
        $out = [$ok, posta_esito(), posta_errore()];
        break;
    default:
        fwrite(STDERR, "comando sconosciuto\n");
        exit(2);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
