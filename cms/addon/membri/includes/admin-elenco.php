<?php
/**
 * Pannello, Membri > Elenco dei membri (addon membri): cerca, attiva, sospendi, elimina, invita, esporta in CSV.
 * Si apre da admin/addon-pagina.php?a=membri&p=elenco: auth e CSRF del pannello sono già caricati (solo amministratore).
 */
require_once __DIR__ . '/membri.php';
membri_schema();
$qui = membri_admin_url('elenco');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $m = $id ? membri_leggi($id) : null;
    $admin = (string)($_SESSION['admin_username'] ?? '');
    try {
        switch ($azione) {
            case 'esporta':
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="membri-' . date('Y-m-d') . '.csv"');
                header('Cache-Control: no-store');
                $out = fopen('php://output', 'w');
                fputs($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Nome', 'Email', 'Stato', 'Iscritto il', 'Email confermata il', 'Ultimo accesso'], ';', '"', '\\');
                foreach (membri_elenco('', '', 5000) as $r) {
                    fputcsv($out, sicurezza_csv_riga([$r['nome'], $r['email'], MEMBRI_STATI[$r['stato']] ?? $r['stato'], $r['creato'], (string)$r['confermato'], (string)$r['ultimo_accesso']]), ';', '"', '\\');
                }
                fclose($out);
                sicurezza_log('impostazioni', $admin, (int)($_SESSION['admin_user_id'] ?? 0), 'membri: elenco esportato in CSV');
                exit;
            case 'attiva':
                if (!$m) throw new RuntimeException('Membro non trovato.');
                $prima = $m['stato'];
                membri_imposta_stato($id, 'attivo');
                if (in_array($prima, ['in_attesa', 'sospeso', 'da_confermare'], true)) {
                    membri_manda($m['email'], 'Il tuo account è attivo', 'Il tuo account è attivo', '<p>Ciao ' . h($m['nome']) . ', da adesso puoi entrare nell\'area riservata di ' . h(membri_nome_sito()) . ' con la tua email e la tua password.</p>', 'Accedi', membri_url('/accedi/'));
                }
                flash('success', '«' . $m['email'] . '» è attivo' . ($prima === 'invitato' ? ' (ma deve ancora scegliere la password dall\'invito).' : ': gli abbiamo scritto un\'email.'));
                break;
            case 'sospendi':
                if (!$m) throw new RuntimeException('Membro non trovato.');
                membri_imposta_stato($id, 'sospeso');
                flash('success', '«' . $m['email'] . '» è sospeso: non entra più, e se era dentro è uscito. I suoi dati restano.');
                break;
            case 'elimina':
                if (!$m) throw new RuntimeException('Membro non trovato.');
                membri_elimina($id);
                sicurezza_log('impostazioni', $admin, (int)($_SESSION['admin_user_id'] ?? 0), 'membri: eliminato il membro ' . $id);
                flash('success', '«' . $m['email'] . '» è stato eliminato, con tutti i suoi dati.');
                break;
            case 'invita':
                $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
                $nome = membri_testo((string)($_POST['nome'] ?? ''));
                if (!membri_email_valida($email)) throw new RuntimeException('Controlla l\'indirizzo email.');
                if ($nome === '') throw new RuntimeException('Scrivi il nome della persona da invitare.');
                $c = membri_per_email($email);
                if ($c && $c['stato'] !== 'invitato') throw new RuntimeException('C\'è già un membro con questa email (' . (MEMBRI_STATI[$c['stato']] ?? $c['stato']) . ').');
                $nid = $c ? (int)$c['id'] : membri_crea($email, $nome, '', 'invitato');
                $t = membri_token_crea('invito', $nid, MEMBRI_GIORNI_INVITO * 1440);
                $ok = membri_manda($email, 'Un invito da ' . membri_nome_sito(), 'Sei stato invitato', '<p>Ciao ' . h($nome) . ', ' . h(membri_nome_sito()) . ' ti ha creato un account nell\'area riservata del sito. Premi il pulsante per scegliere la tua password: il link vale ' . MEMBRI_GIORNI_INVITO . ' giorni.</p>', 'Scegli la password', membri_url('/nuova-password/?t=' . $t));
                flash($ok ? 'success' : 'error', $ok ? 'Invito mandato a «' . $email . '». Il link vale ' . MEMBRI_GIORNI_INVITO . ' giorni.' : 'Il membro è creato, ma l\'email non è partita: controlla Impostazioni, Configurazione email, poi premi di nuovo Invita.');
                break;
            default:
                throw new RuntimeException('Azione sconosciuta.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    header('Location: ' . $qui . (isset($_POST['ritorno_stato']) && isset(MEMBRI_STATI[$_POST['ritorno_stato']]) ? '&stato=' . $_POST['ritorno_stato'] : ''));
    exit;
}

$stato = isset(MEMBRI_STATI[$_GET['stato'] ?? '']) ? (string)$_GET['stato'] : '';
$cerca = mb_substr(trim((string)($_GET['cerca'] ?? '')), 0, 100);
$righe = membri_elenco($stato, $cerca);
$cfg = membri_cfg();
$adminTitle = 'Membri';
require CMS_ADMIN . '/includes/admin-header.php';
?>
<style>.mb-filtri{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin:0 0 1rem}.mb-filtri a{padding:.3rem .7rem;border:1px solid var(--border);border-radius:999px;text-decoration:none;font-size:.85rem}.mb-filtri a.attivo{background:var(--accent,#2563eb);color:#fff;border-color:transparent}.mb-griglia{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:1.25rem}@media(max-width:1000px){.mb-griglia{grid-template-columns:1fr}}.mb-azioni form{display:inline}.mb-azioni{white-space:nowrap}.mb-tab{overflow-x:auto}</style>
<p style="max-width:62rem">Le persone che hanno un account nell'area riservata del sito. Non sono utenti del pannello: entrano solo nelle pagine per i membri, da <code>/accedi/</code>.
Iscrizione: <b><?= h(MEMBRI_MODI[$cfg['modo']]) ?></b> (si cambia in <a href="<?= h(membri_admin_url('impostazioni')) ?>">Impostazioni</a>).</p>
<div class="mb-griglia">
<div>
  <div class="mb-filtri">
    <a href="<?= h(membri_admin_url('elenco')) ?>" class="<?= $stato === '' ? 'attivo' : '' ?>">Tutti (<?= membri_conta() ?>)</a>
    <?php foreach (MEMBRI_STATI as $k => $et): $n = membri_conta($k); if (!$n && $stato !== $k) continue; ?>
      <a href="<?= h(membri_admin_url('elenco', ['stato' => $k])) ?>" class="<?= $stato === $k ? 'attivo' : '' ?>"><?= h($et) ?> (<?= $n ?>)</a>
    <?php endforeach; ?>
    <form method="get" style="display:flex;gap:.4rem;margin-left:auto"><input type="hidden" name="a" value="membri"><input type="hidden" name="p" value="elenco">
      <input type="search" name="cerca" value="<?= h($cerca) ?>" placeholder="Cerca nome o email" aria-label="Cerca nome o email"><button class="btn btn-secondary btn-sm" type="submit">Cerca</button></form>
  </div>
  <div class="sc-scatola"><h3><?= count($righe) ?> membri</h3><div class="sc-corpo mb-tab" style="padding:0">
  <?php if (!$righe): ?>
    <p style="padding:1rem;margin:0;color:var(--text-muted)">Ancora nessuno<?= $stato || $cerca !== '' ? ' con questo filtro' : '' ?>. Chi si iscrive da <code>/iscriviti/</code> compare qui.</p>
  <?php else: ?>
    <div class="ad-tabella"><table class="admin-table" id="mb-elenco" style="width:100%">
      <thead><tr><th>Nome</th><th>Email</th><th>Stato</th><th>Iscritto</th><th>Ultimo accesso</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($righe as $r): $badge = ['attivo' => 'badge-published', 'sospeso' => 'badge-draft', 'in_attesa' => 'badge-scheduled', 'invitato' => 'badge-scheduled', 'da_confermare' => 'badge-draft'][$r['stato']] ?? 'badge-draft'; ?>
        <tr data-email="<?= h($r['email']) ?>">
          <td><?= h($r['nome']) ?></td>
          <td><?= h($r['email']) ?></td>
          <td><span class="badge <?= $badge ?> mb-stato"><?= h(MEMBRI_STATI[$r['stato']] ?? $r['stato']) ?></span></td>
          <td><?= h(date('d/m/Y', strtotime($r['creato']))) ?></td>
          <td><?= $r['ultimo_accesso'] ? h(date('d/m/Y H:i', strtotime($r['ultimo_accesso']))) : '<span style="color:var(--text-muted)">mai</span>' ?></td>
          <td class="mb-azioni">
            <?php if ($r['stato'] !== 'attivo' && $r['stato'] !== 'invitato'): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="attiva"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="ritorno_stato" value="<?= h($stato) ?>"><button class="btn btn-primary btn-sm" type="submit">Attiva</button></form>
            <?php endif; ?>
            <?php if ($r['stato'] === 'attivo'): ?>
              <form method="post" data-conferma="Sospendere <?= h($r['email']) ?>? Non potrà più entrare finché non lo riattivi."><?= csrf_field() ?><input type="hidden" name="azione" value="sospendi"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="ritorno_stato" value="<?= h($stato) ?>"><button class="btn btn-secondary btn-sm" type="submit">Sospendi</button></form>
            <?php endif; ?>
            <?php if ($r['stato'] === 'invitato'): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="invita"><input type="hidden" name="email" value="<?= h($r['email']) ?>"><input type="hidden" name="nome" value="<?= h($r['nome']) ?>"><button class="btn btn-secondary btn-sm" type="submit">Rimanda l'invito</button></form>
            <?php endif; ?>
            <form method="post" data-conferma="Eliminare <?= h($r['email']) ?> e tutti i suoi dati? Non si torna indietro."><?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="ritorno_stato" value="<?= h($stato) ?>"><button class="btn btn-danger btn-sm" type="submit">Elimina</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
  </div></div>
</div>
<aside>
  <div class="sc-scatola"><h3>Invita una persona</h3><div class="sc-corpo">
    <form method="post" id="mb-invita"><?= csrf_field() ?><input type="hidden" name="azione" value="invita">
      <label for="mb-nome">Nome</label><input type="text" id="mb-nome" name="nome" maxlength="120" required>
      <label for="mb-email">Email</label><input type="email" id="mb-email" name="email" maxlength="190" required>
      <button type="submit" class="btn btn-primary" style="margin-top:.8rem">Invita</button>
    </form>
    <p class="hint">Le arriva un'email con il link per scegliere la password (vale <?= MEMBRI_GIORNI_INVITO ?> giorni). Funziona con ogni tipo di iscrizione.</p>
  </div></div>
  <div class="sc-scatola"><h3>Esporta</h3><div class="sc-corpo">
    <form method="post" data-senza-protezione><?= csrf_field() ?><input type="hidden" name="azione" value="esporta"><button type="submit" class="btn btn-secondary" id="mb-esporta">Scarica l'elenco (CSV)</button></form>
    <p class="hint">Nome, email, stato e date di tutti i membri, da aprire con Excel o LibreOffice. Sono dati personali: tienili al sicuro.</p>
  </div></div>
</aside>
</div>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
