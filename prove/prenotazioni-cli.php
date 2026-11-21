<?php
/**
 * I posti liberi dell'addon Prenotazioni a giorni e ore fissi (per prova-addon-prenotazioni.js), nel fuso Europe/Rome:
 * giorno normale, anticipo minimo e massimo, ferie, chiusure, sovrapposizione con la pausa, cambio dell'ora legale
 * (marzo e ottobre), fine mese e anno bisestile. Crea un servizio e una persona di prova e li toglie alla fine;
 * le impostazioni tornano com'erano. Stampa JSON: caso => risultato. Mai nel prodotto.
 * uso: php prove/prenotazioni-cli.php <cartella CMS>
 */
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
$dir = $argv[1];
define('CMS_FUSO', 'Europe/Rome');
require $dir . '/includes/config.php';
require_once $dir . '/addon/prenotazioni/includes/prenotazioni.php';
pren_schema();
$z = new DateTimeZone('Europe/Rome');
$ts = fn(string $t) => (new DateTimeImmutable($t, $z))->getTimestamp();
$prima = get_setting('pren_impostazioni', '');
$imp = PREN_PREDEFINITE;
$imp['passo'] = 30; $imp['anticipo_ore'] = 2; $imp['anticipo_giorni'] = 60;
save_setting('pren_impostazioni', json_encode($imp));
$ora = date('Y-m-d H:i:s');
// Una persona sola per il servizio di prova (gli altri non lo fanno), con turni anche di notte per l'ora legale.
db()->prepare('INSERT INTO ' . tbl('pren_risorse') . " (nome, descrizione, orari, ferie, attivo, ordine, creato) VALUES ('Prova CLI', '', ?, ?, 1, 99, ?)")
    ->execute([json_encode(['lun' => '9:00-13:00, 15:00-19:00', 'mar' => '9-13', 'mer' => 'Chiuso', 'gio' => '9:00-13:00', 'ven' => '9:00-13:00', 'sab' => '9:00-13:00', 'dom' => '0:00-6:00']),
        json_encode([['dal' => '2026-11-10', 'al' => '2026-11-12', 'motivo' => 'Ferie']]), $ora]);
$rid = (int)db()->lastInsertId();
db()->prepare('INSERT INTO ' . tbl('pren_servizi') . " (nome, durata, pausa, prezzo, risorse, attivo, ordine, creato) VALUES ('Servizio CLI', 45, 15, '', ?, 1, 99, ?)")->execute([(string)$rid, $ora]);
$sid = (int)db()->lastInsertId();
$s = pren_servizio($sid);
$k = fn(array $posti) => array_keys($posti);
$out = [];
try {
    $out['lunedi'] = $k(pren_posti($s, 0, '2026-11-02', $ts('2026-10-30 10:00')));
    $out['lunedi_anticipo'] = $k(pren_posti($s, 0, '2026-11-02', $ts('2026-11-02 10:10')));
    $out['martedi_9_13'] = $k(pren_posti($s, 0, '2026-11-03', $ts('2026-10-30 10:00')));
    $out['mercoledi_chiuso'] = $k(pren_posti($s, 0, '2026-11-04', $ts('2026-10-30 10:00')));
    $out['ferie'] = $k(pren_posti($s, 0, '2026-11-11', $ts('2026-10-30 10:00')));
    $out['oltre_60_giorni'] = $k(pren_posti($s, 0, '2027-01-04', $ts('2026-10-30 10:00')));
    $out['passato'] = $k(pren_posti($s, 0, '2026-10-26', $ts('2026-10-30 10:00')));
    $out['data_sbagliata'] = $k(pren_posti($s, 0, '2026-02-30', $ts('2026-01-30 10:00')));
    $out['ora_legale_marzo'] = $k(pren_posti($s, 0, '2027-03-28', $ts('2027-03-20 10:00')));
    $out['ora_solare_ottobre'] = $k(pren_posti($s, 0, '2026-10-25', $ts('2026-10-20 10:00')));
    // In ottobre la domenica dalle 0 alle 6 ha 7 ore vere: 0:00 + 45 minuti finisce alle 0:45 come sempre.
    $o = pren_posti($s, 0, '2026-10-25', $ts('2026-10-20 10:00'));
    $out['ottobre_ore_vere'] = ($ts('2026-10-25 06:00') - $ts('2026-10-25 00:00')) / 3600;
    $out['fine_gennaio'] = array_column(pren_giorni($s, 0, '2026-01-29', 5, $ts('2026-01-20 10:00')), 'd');
    $out['bisestile'] = array_column(pren_giorni($s, 0, '2028-02-27', 4, $ts('2028-02-20 10:00')), 'd');
    $out['fine_dicembre'] = array_column(pren_giorni($s, 0, '2026-12-30', 4, $ts('2026-12-20 10:00')), 'd');
    // Chiusura di tutta l'attività.
    $imp['chiusure'] = [['dal' => '2026-11-02', 'al' => '', 'motivo' => 'Inventario']];
    save_setting('pren_impostazioni', json_encode($imp));
    $out['chiusura_attivita'] = $k(pren_posti($s, 0, '2026-11-02', $ts('2026-10-30 10:00')));
    $imp['chiusure'] = [];
    save_setting('pren_impostazioni', json_encode($imp));
    // Una prenotazione 9:30-10:15 con la pausa di 15 minuti occupa fino alle 10:30.
    $iTs = $ts('2026-11-02 09:30');
    db()->prepare('INSERT INTO ' . tbl('pren_prenotazioni') . " (codice, servizio_id, risorsa_id, inizio, fine, inizio_ts, fine_ts, blocco_ts, nome, email, stato, consenso_at, creato, aggiornato) VALUES (?,?,?,?,?,?,?,?,'Cli','cli@esempio.it','confermata',?,?,?)")
        ->execute([bin2hex(random_bytes(16)), $sid, $rid, '2026-11-02 09:30:00', '2026-11-02 10:15:00', $iTs, $iTs + 2700, $iTs + 3600, $ora, $ora, $ora]);
    $pid = (int)db()->lastInsertId();
    $out['con_prenotazione'] = $k(pren_posti($s, 0, '2026-11-02', $ts('2026-10-30 10:00')));
    $out['spostando_se_stessa'] = $k(pren_posti($s, 0, '2026-11-02', $ts('2026-10-30 10:00'), $pid));
    // La stessa prenotazione due volte: la seconda trova il posto preso (e l'indice UNIQUE farebbe lo stesso).
    $d = ['servizio' => $sid, 'risorsa' => 0, 'giorno' => '2026-11-02', 'ora' => '11:00', 'nome' => 'Uno', 'email' => 'uno@esempio.it', 'telefono' => '3331234567'];
    $r1 = pren_prenota($d, $ts('2026-10-30 10:00'));
    $r2 = pren_prenota($d, $ts('2026-10-30 10:00'));
    $out['doppia'] = [is_array($r1) ? 'ok' : $r1, is_array($r2) ? 'ok' : $r2];
    try {
        db()->prepare('INSERT INTO ' . tbl('pren_prenotazioni') . " (codice, servizio_id, risorsa_id, inizio, fine, inizio_ts, fine_ts, blocco_ts, nome, email, stato, consenso_at, creato, aggiornato) VALUES (?,?,?,?,?,?,?,?,'Cli','cli@esempio.it','confermata',?,?,?)")
            ->execute([bin2hex(random_bytes(16)), $sid, $rid, '2026-11-02 09:30:00', '2026-11-02 10:15:00', $iTs, $iTs + 2700, $iTs + 3600, $ora, $ora, $ora]);
        $out['indice_unico'] = 'passata';
    } catch (PDOException $e) {
        $out['indice_unico'] = $e->errorInfo[1] === 1062 ? 'fermata' : 'altro errore';
    }
    // Il promemoria: una volta sola, e non a chi ha prenotato da poco.
    db()->exec('UPDATE ' . tbl('pren_prenotazioni') . " SET creato = '2026-10-01 10:00:00' WHERE id = " . $pid);
    $out['promemoria_1'] = pren_cron($ts('2026-11-01 12:00'));
    $out['promemoria_2'] = pren_cron($ts('2026-11-01 12:05'));
    $out['svolte'] = pren_cron($ts('2026-11-02 12:00'));
} finally {
    db()->exec('DELETE FROM ' . tbl('pren_prenotazioni') . ' WHERE servizio_id = ' . $sid);
    db()->exec('DELETE FROM ' . tbl('pren_servizi') . ' WHERE id = ' . $sid);
    db()->exec('DELETE FROM ' . tbl('pren_risorse') . ' WHERE id = ' . $rid);
    save_setting('pren_impostazioni', (string)$prima);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE), "\n";
