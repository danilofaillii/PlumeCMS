<?php
/**
 * 2.31.0 (prova-avvisi-pannello.js): manda i cinque avvisi che hanno come Reply-To il visitatore, dalle funzioni vere,
 * al server di posta finto: form (form_ricevi), richiesta di prenotazione (rp_ricevi), addon Prenotazioni
 * (pren_email_attivita), Vetrina (vt_email_ordine, l'email al negozio), Iscrizioni agli eventi (evi_email_attivita).
 * Gli addon non serve accenderli: si caricano i loro file e si chiamano le funzioni dell'avviso con dati finti.
 * Il pulsante segue l'impostazione avvisi_pulsante_pannello già scritta (dal pannello o con "imposta").
 * Lascia dati di prova (un form "Prova avvisi", invii): va su un sito da buttare. Mai nel prodotto.
 * uso: php prove/avvisi-cli.php <cartella CMS> manda <porta smtp> <ip finto>
 *      php prove/avvisi-cli.php <cartella CMS> imposta <0|1>
 */
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
[, $dir, $cosa] = $argv;
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/posta.php';
require_once $dir . '/includes/moduli.php';

if ($cosa === 'imposta') { save_setting('avvisi_pulsante_pannello', $argv[3] === '1' ? '1' : '0'); echo "ok\n"; exit; }

$porta = (int)$argv[3];
$_SERVER['REMOTE_ADDR'] = $argv[4];
save_setting('posta_smtp', json_encode(['host' => '127.0.0.1', 'porta' => $porta, 'sicurezza' => 'nessuna', 'utente' => '', 'password' => '', 'mittente' => 'sito@esempio.it', 'nome' => 'Prova']));
save_setting('notifica_email', 'negozio@esempio.it');
$out = [];
$risolvi = function (): string {
    $s = moduli_captcha_sfida();
    for ($n = 0; $n <= $s['max']; $n++) if (hash('sha256', $s['sale'] . $n) === $s['sfida']) break;
    return base64_encode(json_encode($s + ['n' => $n]));
};

// 1. Form
require_once $dir . '/includes/form.php';
form_schema();
$id = (int)db()->query('SELECT id FROM ' . tbl('moduli') . " WHERE slug = 'prova-avvisi'")->fetchColumn();
if (!$id) $id = form_salva(0, ['nome' => 'Prova avvisi', 'slug' => 'prova-avvisi', 'attivo' => 1, 'avviso' => 1, 'campi' => [
    ['etichetta' => 'Nome', 'tipo' => 'testo', 'obbligatorio' => 1], ['etichetta' => 'Email', 'tipo' => 'email', 'obbligatorio' => 1], ['etichetta' => 'Messaggio', 'tipo' => 'testo_lungo']]]);
db()->prepare('UPDATE ' . tbl('moduli') . ' SET attivo = 1, avviso = 1 WHERE id = ?')->execute([$id]);
$m = form_leggi($id);
$p = ['privacy' => '1', 'captcha' => $risolvi()];
foreach ($m['campi'] as $c) $p['c_' . $c['nome']] = ['email' => 'cliente.form@esempio.it', 'testo' => 'Maria Rossi'][$c['tipo']] ?? 'Avete posto sabato?';
$out['form'] = form_ricevi($m, $p, '/contatti/');

// 2. Richiesta di prenotazione
require_once $dir . '/includes/richiesta-prenotazione.php';
[$b, $f] = rp_firma(['modalita' => 'tavolo', 'max' => 20, 'a' => ['negozio@esempio.it'], 'titolo' => 'Cena']);
$out['richiesta'] = rp_ricevi(['rp_cfg' => $b, 'rp_firma' => $f, 'data' => date('Y-m-d', strtotime('+3 days')), 'ora' => '20:30', 'persone' => '4',
    'nome' => 'Luca Bianchi', 'email' => 'cliente.richiesta@esempio.it', 'privacy' => '1', 'captcha' => $risolvi()], '/prenota/');

// 3. Addon Prenotazioni (l'avviso all'attività)
require_once $dir . '/addon/prenotazioni/includes/prenotazioni.php';
pren_schema();
$pr = ['id' => 1, 'nome' => 'Anna Verdi', 'email' => 'cliente.prenotazioni@esempio.it', 'telefono' => '', 'note' => '', 'stato' => 'da_confermare',
    'inizio' => date('Y-m-d 10:00:00', strtotime('+2 days')), 'fine' => date('Y-m-d 10:45:00', strtotime('+2 days')), 'servizio_id' => 0, 'risorsa_id' => 0, 'inizio_ts' => strtotime('+2 days 10:00'), 'fine_ts' => strtotime('+2 days 10:45')];
try { $out['prenotazioni'] = pren_email_attivita($pr, 'nuova') ? '' : 'non partito'; } catch (Throwable $e) { $out['prenotazioni'] = 'errore: ' . $e->getMessage(); }

// 4. Addon Vetrina (l'email al negozio)
require_once $dir . '/addon/vetrina/includes/vetrina.php';
vt_schema();
$o = ['id' => 1, 'numero' => 'V-0001', 'nome' => 'Paolo Neri', 'email' => 'cliente.vetrina@esempio.it', 'telefono' => '333 1234567', 'metodo' => array_key_first(VT_METODI),
    'note' => '', 'totale_cent' => 1200, 'subtotale_cent' => 1200, 'spedizione_cent' => 0, 'iva_cent' => 216, 'stato' => 'nuovo', 'consegna' => 'ritiro', 'indirizzo' => '', 'righe' => [['nome' => 'Torta', 'variante' => '', 'qta' => 1, 'prezzo_cent' => 1200, 'totale_cent' => 1200]], 'chiave' => str_repeat('a', 32)];
$s = vt_impostazioni(); $s['email_avvisi'] = 'negozio@esempio.it'; vt_impostazioni_salva($s);
try { $e = vt_email_ordine($o); $out['vetrina'] = $e['negozio'] ? '' : 'non partito'; } catch (Throwable $e) { $out['vetrina'] = 'errore: ' . $e->getMessage(); }

// 5. Addon Iscrizioni agli eventi
require_once $dir . '/addon/eventi-iscrizione/includes/eventi.php';
evi_schema();
$ev = ['id' => 1, 'titolo' => 'Corso di pasta fresca', 'posti' => 10, 'inizio' => date('Y-m-d 18:00:00', strtotime('+5 days')), 'fine' => date('Y-m-d 20:00:00', strtotime('+5 days')), 'luogo' => 'Bologna', 'indirizzo' => '', 'prezzo' => ''];
$is = ['id' => 1, 'nome' => 'Sara Gialli', 'email' => 'cliente.eventi@esempio.it', 'telefono' => '', 'posti' => 1, 'stato' => 'confermata', 'note' => ''];
try { $out['eventi'] = evi_email_attivita($ev, $is, 'nuova') ? '' : 'non partito'; } catch (Throwable $e) { $out['eventi'] = 'errore: ' . $e->getMessage(); }

echo json_encode($out, JSON_UNESCAPED_UNICODE), "\n";
