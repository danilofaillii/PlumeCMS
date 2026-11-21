<?php
/**
 * Informazioni sul CMS: versione, novità (CHANGELOG.md), moduli attivi, stato del server (PHP, estensioni, cartelle),
 * tabelle con i conteggi, widget dell'editor. Sola lettura: serve a capire cosa c'è installato e se sta in piedi.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_admin(); // sicurezza (ruoli): solo amministratore
require_once dirname(__DIR__) . '/includes/sito.php';
require_once dirname(__DIR__) . '/includes/contenuti.php';

$adminTitle = 'Informazioni';
$ok = fn(bool $b) => $b ? '<span class="badge badge-published">ok</span>' : '<span class="badge badge-draft">no</span>';

// Il changelog in HTML semplice: titoli ## e righe puntate.
$changelog = '';
$fileLog = dirname(__DIR__) . '/CHANGELOG.md';
if (is_file($fileLog)) {
    $inLista = false;
    foreach (preg_split('/\r?\n/', (string)file_get_contents($fileLog)) as $riga) {
        if (preg_match('/^##\s+(.*)$/', $riga, $m)) { if ($inLista) { $changelog .= '</ul>'; $inLista = false; } $changelog .= '<h3>' . h($m[1]) . '</h3>'; continue; }
        if (preg_match('/^#\s/', $riga)) continue;
        if (preg_match('/^[-*]\s+(.*)$/', $riga, $m)) { if (!$inLista) { $changelog .= '<ul>'; $inLista = true; } $changelog .= '<li>' . h($m[1]) . '</li>'; continue; }
        if (trim($riga) !== '') { if ($inLista) { $changelog .= '</ul>'; $inLista = false; } $changelog .= '<p>' . h($riga) . '</p>'; }
    }
    if ($inLista) $changelog .= '</ul>';
}

// Server
$estensioni = ['pdo_mysql' => 'PDO MySQL (database)', 'mbstring' => 'mbstring (testi con accenti)', 'openssl' => 'openssl', 'json' => 'json', 'dom' => 'DOM (conversione degli articoli)', 'gd' => 'GD (immagini)', 'zip' => 'zip (pacchetti)', 'curl' => 'curl (IndexNow, Search Console)'];
$cartelle = ['uploads' => cms_uploads_dir(), 'logs' => cms_radice() . '/logs', 'aggiornamenti' => cms_radice() . '/aggiornamenti'];
$mysql = '';
try { $mysql = (string)db()->query('SELECT VERSION()')->fetchColumn(); } catch (Throwable $e) { $mysql = 'non raggiungibile'; }

// Tabelle con i conteggi
$tabelle = [];
try {
    foreach (db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        if (!str_starts_with((string)$t, DB_PREFIX)) continue;
        $tabelle[(string)$t] = (int)db()->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    }
} catch (Throwable $e) { /* senza database la tabella resta vuota */ }
$viste = [];
try { $viste = db()->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = DATABASE()")->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) {}

// Widget dell'editor
$widget = [];
try {
    require_once dirname(__DIR__) . '/includes/editor.php';
    foreach (editor_definizioni() as $tipo => $d) { if (in_array($tipo, ['sezione', 'colonna'], true)) continue; $widget[(string)($d['gruppo'] ?? 'Altri')][] = (string)($d['etichetta'] ?? $tipo); }
    ksort($widget);
} catch (Throwable $e) {}

require_once __DIR__ . '/includes/admin-header.php';
?>
<style>.info-griglia{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1rem}.info-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.1rem 1.25rem}.info-card h2{font-size:1rem;margin:0 0 .6rem}.info-card table{width:100%;font-size:.88rem;border-collapse:collapse}.info-card td{padding:.3rem .2rem;border-bottom:1px solid var(--border);vertical-align:top}.info-card td:last-child{text-align:right;white-space:nowrap}.info-card h3{font-size:.95rem;margin:.9rem 0 .3rem}.info-card ul{margin:0 0 .4rem 1.1rem;font-size:.9rem}.info-card p{font-size:.9rem;margin:.3rem 0}.info-ver{font-size:2rem;font-weight:700}</style>
<div class="info-griglia">
  <div class="info-card">
    <h2><?= h(CMS_NOME) ?></h2>
    <div class="info-ver"><?= h(cms_versione()) ?></div>
    <p>Installato in <code><?= h(cms_radice()) ?></code><?= cms_base() !== '' ? ', sottocartella <code>' . h(cms_base()) . '</code>' : ', nella radice del dominio' ?>.<br>Sito: <code><?= h(cms_url_sito()) ?></code>. Prefisso delle tabelle: <code><?= h(DB_PREFIX) ?></code>.</p>
    <p>Pacchetti di aggiornamento: voce <a href="aggiornamenti.php">Aggiornamenti</a>. Manuali: <code>docs/manuale-uso.md</code>, <code>docs/manuale-temi.md</code>.</p>
    <p>Software libero con licenza <a href="https://www.gnu.org/licenses/gpl-3.0.html" target="_blank" rel="noopener">GNU GPL, versione 3 o successiva</a>: il testo è nel file <code>LICENSE</code>.</p>
    <h3>Moduli attivi</h3>
    <?php if (!cms_moduli()): ?><p style="color:var(--text-muted)">Nessun modulo: il CMS gira con il solo nucleo.</p><?php else: ?>
    <table><?php foreach (cms_moduli() as $nome => $m): ?><tr><td><b><?= h($m['etichetta'] ?? $nome) ?></b> <span style="color:var(--text-muted)">(<?= h($nome) ?>)</span><br><small><?= h($m['descrizione'] ?? '') ?></small></td><td><?= h($m['versione'] ?? '') ?></td></tr><?php endforeach; ?></table>
    <?php endif; ?>
  </div>
  <div class="info-card">
    <h2>Server</h2>
    <table>
      <tr><td>PHP <?= h(PHP_VERSION) ?></td><td><?= $ok(version_compare(PHP_VERSION, '8.2.0', '>=')) ?></td></tr>
      <tr><td>MySQL <?= h($mysql) ?></td><td><?= $ok($mysql !== 'non raggiungibile') ?></td></tr>
      <?php foreach ($estensioni as $e => $nome): ?><tr><td><?= h($nome) ?></td><td><?= $ok(extension_loaded($e) || ($e === 'zip' && class_exists('ZipArchive'))) ?></td></tr><?php endforeach; ?>
      <?php foreach ($cartelle as $nome => $p): ?><tr><td>Cartella <?= h($nome) ?> scrivibile<br><small style="color:var(--text-muted)"><?= h($p) ?></small></td><td><?= $ok(is_dir($p) && is_writable($p)) ?></td></tr><?php endforeach; ?>
      <tr><td>Fuso orario</td><td><?= h(date_default_timezone_get()) ?></td></tr>
    </table>
    <p style="margin-top:.7rem"><a class="btn btn-secondary btn-sm" href="controlla-server.php">Controlla il server</a> prova davvero HTTPS, cron, email, aggiornamenti e protezioni dei file.</p>
  </div>
  <div class="info-card">
    <h2>Tabelle (<?= count($tabelle) ?>)</h2>
    <table><?php foreach ($tabelle as $t => $n): ?><tr><td><?= h($t) ?><?= in_array($t, $viste, true) ? ' <small style="color:var(--text-muted)">vista</small>' : '' ?></td><td><?= number_format($n, 0, ',', '.') ?></td></tr><?php endforeach; ?></table>
  </div>
  <div class="info-card">
    <h2>Widget dell'editor (<?= array_sum(array_map('count', $widget)) ?>)</h2>
    <?php foreach ($widget as $gruppo => $voci): ?><h3><?= h($gruppo) ?> (<?= count($voci) ?>)</h3><p style="color:var(--text-muted)"><?= h(implode(', ', $voci)) ?></p><?php endforeach; ?>
  </div>
  <div class="info-card" style="grid-column:1/-1">
    <h2>Novità per versione</h2>
    <?= $changelog !== '' ? $changelog : '<p style="color:var(--text-muted)">Manca CHANGELOG.md.</p>' ?>
  </div>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
