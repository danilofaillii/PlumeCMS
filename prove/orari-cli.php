<?php
// I calcoli del widget Orari di apertura a giorni e ore fissi (per prova-widget-nuovi.js). Stampa JSON: caso => frase.
// uso: php prove/orari-cli.php <cartella CMS>
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$v = ['lun' => '9:00-13:00, 15:00-19:00', 'mar' => '9-13', 'mer' => '9.30 - 12.30', 'gio' => '24 ore', 'ven' => '18:00-02:00', 'sab' => 'Chiuso', 'dom' => '',
    'chiusure' => [['dal' => '2026-12-24', 'al' => '2026-12-26', 'motivo' => 'Natale'], ['dal' => 'sbagliata', 'al' => '', 'motivo' => 'x']],
    'testo_aperto' => 'Aperto ora', 'testo_chiuso' => 'Chiuso ora'];
$z = new DateTimeZone('Europe/Rome');
$casi = [
    'lunedi_10' => '2026-11-02 10:00', 'lunedi_13_30' => '2026-11-02 13:30', 'lunedi_20' => '2026-11-02 20:00', 'martedi_8' => '2026-11-03 08:00',
    'mercoledi_12_29' => '2026-11-04 12:29', 'giovedi_3' => '2026-11-05 03:00', 'venerdi_23' => '2026-11-06 23:00', 'sabato_1' => '2026-11-07 01:00',
    'sabato_10' => '2026-11-07 10:00', 'natale' => '2026-12-25 10:00', 'vigilia_mattina_prima' => '2026-12-23 20:00',
];
$out = [];
foreach ($casi as $k => $t) $out[$k] = ewx_orari_stato($v, new DateTimeImmutable($t, $z));
$out['intervalli'] = array_map('ewx_orari_intervalli', ['9:00-13:00, 15:00-19:00', '9-13', 'chiuso', '', 'boh', '24 ore', '18:00-02:00']);
echo json_encode($out, JSON_UNESCAPED_UNICODE), "\n";
