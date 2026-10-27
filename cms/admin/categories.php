<?php
/**
 * Categorie e tag: elenco con il conteggio, aggiungi, rinomina, descrizione, elimina.
 *   categories.php            categorie
 *   categories.php?tipo=tag   tag
 * Eliminare una categoria toglie il collegamento dagli articoli; gli articoli restano.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/contenuti.php';
require_login();
sito_schema_aggiorna();

$tipo = ($_GET['tipo'] ?? 'categorie') === 'tag' ? 'tag' : 'categorie';
$tab = $tipo === 'tag' ? 'tags' : 'categories';
$adminTitle = $tipo === 'tag' ? 'Tag' : 'Categorie';
$baseLink = 'categories.php' . ($tipo === 'tag' ? '?tipo=tag' : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim((string)($_POST['name'] ?? ''));
    $slug = contenuti_slug((string)($_POST['slug'] ?? '')) ?: contenuti_slug($nome);
    try {
        switch ($_POST['azione'] ?? '') {
            case 'aggiungi':
            case 'salva':
                if ($nome === '' || $slug === '') throw new RuntimeException('Nome e slug non possono restare vuoti.');
                $c = db()->prepare('SELECT COUNT(*) FROM ' . tbl($tab) . ' WHERE slug = ? AND id <> ?');
                $c->execute([$slug, $id]);
                if ($c->fetchColumn()) throw new RuntimeException("C'è già una voce con lo slug $slug.");
                if ($id) {
                    $vecchio = db()->prepare('SELECT slug FROM ' . tbl($tab) . ' WHERE id = ?');
                    $vecchio->execute([$id]);
                    $vecchio = (string)$vecchio->fetchColumn();
                    if ($tipo === 'categorie') {
                        db()->prepare('UPDATE ' . tbl('categories') . ' SET name = ?, slug = ?, description = ?, robots = ? WHERE id = ?')
                            ->execute([$nome, $slug, trim((string)($_POST['description'] ?? '')), in_array($_POST['robots'] ?? '', ['index', 'noindex'], true) ? $_POST['robots'] : '', $id]);
                    } else {
                        db()->prepare('UPDATE ' . tbl('tags') . ' SET name = ?, slug = ? WHERE id = ?')->execute([$nome, $slug, $id]);
                    }
                    if ($vecchio !== '' && $vecchio !== $slug) {
                        // Gli articoli seguono lo slug nuovo; dal vecchio archivio parte un 301.
                        tassonomia_rinomina($tipo, $vecchio, $slug);
                        contenuti_redirect('/' . ($tipo === 'tag' ? 'tag' : 'category') . "/$vecchio/", '/' . ($tipo === 'tag' ? 'tag' : 'category') . "/$slug/");
                    }
                    flash('success', 'Salvato.');
                } else {
                    if ($tipo === 'categorie') db()->prepare('INSERT INTO ' . tbl('categories') . ' (slug, name, description) VALUES (?, ?, ?)')->execute([$slug, $nome, trim((string)($_POST['description'] ?? ''))]);
                    else db()->prepare('INSERT INTO ' . tbl('tags') . ' (slug, name) VALUES (?, ?)')->execute([$slug, $nome]);
                    flash('success', ($tipo === 'tag' ? 'Tag' : 'Categoria') . ' creata: ' . $nome);
                }
                break;
            case 'elimina':
                $r = db()->prepare('SELECT slug FROM ' . tbl($tab) . ' WHERE id = ?');
                $r->execute([$id]);
                $s = (string)$r->fetchColumn();
                if ($s === '') throw new RuntimeException('Voce non trovata.');
                if ($tipo === 'categorie' && in_array($s, cms_filtro('categorie_protette', ['senza-categoria']), true)) throw new RuntimeException("La categoria «{$s}» serve al sito e non si elimina.");
                tassonomia_rinomina($tipo, $s, '');
                db()->prepare('DELETE FROM ' . tbl($tab) . ' WHERE id = ?')->execute([$id]);
                flash('success', 'Eliminata. Gli articoli restano, senza questa voce.');
                break;
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    header('Location: ' . $baseLink);
    exit;
}

/** Cambia (o toglie, con $nuovo vuoto) uno slug di categoria o tag in tutti gli articoli. */
function tassonomia_rinomina(string $tipo, string $vecchio, string $nuovo): void {
    $col = $tipo === 'tag' ? 'tag_slugs' : 'categorie';
    $righe = db()->query('SELECT id, category, categorie, tag_slugs, tags FROM ' . tbl('articles') . " WHERE FIND_IN_SET(" . db()->quote($vecchio) . ", REPLACE($col, ' ', '')) OR category = " . db()->quote($vecchio))->fetchAll();
    foreach ($righe as $a) {
        $lista = sito_lista((string)$a[$col]);
        $lista = array_values(array_unique(array_filter(array_map(fn($x) => $x === $vecchio ? $nuovo : $x, $lista))));
        $set = ["$col = " . db()->quote(implode(',', $lista))];
        if ($tipo === 'categorie') $set[] = 'category = ' . db()->quote($lista[0] ?? '');
        else { $nomi = db()->query('SELECT slug, name FROM ' . tbl('tags'))->fetchAll(PDO::FETCH_KEY_PAIR); $set[] = 'tags = ' . db()->quote(implode(', ', array_map(fn($s) => $nomi[$s] ?? $s, $lista))); }
        db()->exec('UPDATE ' . tbl('articles') . ' SET ' . implode(', ', $set) . ' WHERE id = ' . (int)$a['id']);
    }
}

$voci = db()->query('SELECT * FROM ' . tbl($tab) . ' ORDER BY name')->fetchAll();
$conta = [];
foreach (db()->query('SELECT category, categorie, tag_slugs, tags, cestino FROM ' . tbl('articles') . ' WHERE cestino IS NULL')->fetchAll() as $a) {
    $l = $tipo === 'tag' ? (sito_lista($a['tag_slugs'] ?? '') ?: array_map('contenuti_slug', sito_lista($a['tags'] ?? ''))) : (sito_lista($a['categorie'] ?? '') ?: array_filter([$a['category']]));
    foreach (array_unique($l) as $s) $conta[$s] = ($conta[$s] ?? 0) + 1;
}
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') $voci = array_values(array_filter($voci, fn($v) => stripos($v['name'] . ' ' . $v['slug'], $q) !== false));
$modifica = isset($_GET['modifica']) ? array_values(array_filter($voci, fn($v) => (int)$v['id'] === (int)$_GET['modifica']))[0] ?? null : null;

require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="assets/css/contenuti.css">
<div class="ct-testa">
  <div class="ct-schede">
    <a href="categories.php" class="<?= $tipo === 'categorie' ? 'attivo' : '' ?>">Categorie</a>
    <a href="categories.php?tipo=tag" class="<?= $tipo === 'tag' ? 'attivo' : '' ?>">Tag</a>
  </div>
  <form method="get" style="display:flex;gap:.4rem"><?php if ($tipo === 'tag'): ?><input type="hidden" name="tipo" value="tag"><?php endif; ?><input type="search" name="q" value="<?= h($q) ?>" placeholder="Cerca…" class="search-input" style="padding:.4rem .6rem;border:1px solid var(--border);border-radius:8px"><button class="btn btn-secondary btn-sm">Cerca</button></form>
</div>

<div style="display:grid;grid-template-columns:320px minmax(0,1fr);gap:1.2rem;align-items:start">
  <div class="sc-scatola">
    <h3><?= $modifica ? 'Modifica' : 'Aggiungi' ?> <?= $tipo === 'tag' ? 'tag' : 'categoria' ?></h3>
    <form method="post" class="sc-corpo">
      <?= csrf_field() ?>
      <input type="hidden" name="azione" value="<?= $modifica ? 'salva' : 'aggiungi' ?>"><input type="hidden" name="id" value="<?= $modifica['id'] ?? 0 ?>">
      <label>Nome</label><input type="text" name="name" value="<?= h($modifica['name'] ?? '') ?>" required>
      <label>Slug</label><input type="text" name="slug" value="<?= h($modifica['slug'] ?? '') ?>" placeholder="dal nome, se vuoto" pattern="[a-z0-9\-]*">
      <?php if ($tipo === 'categorie'): ?>
      <label>Descrizione</label><textarea name="description" rows="3"><?= h($modifica['description'] ?? '') ?></textarea>
      <label>Indicizzazione dell'archivio</label>
      <select name="robots"><option value="">Automatica (visibile se ha articoli)</option><option value="index" <?= ($modifica['robots'] ?? '') === 'index' ? 'selected' : '' ?>>Sempre visibile</option><option value="noindex" <?= ($modifica['robots'] ?? '') === 'noindex' ? 'selected' : '' ?>>Nascosta a Google</option></select>
      <?php endif; ?>
      <div class="hint" style="margin:.6rem 0">Se cambi lo slug di una voce già usata, gli articoli seguono e dal vecchio archivio parte un redirect 301.</div>
      <div style="display:flex;gap:.5rem"><button class="btn btn-primary btn-sm"><?= $modifica ? 'Salva' : 'Aggiungi' ?></button><?php if ($modifica): ?><a class="btn btn-secondary btn-sm" href="<?= h($baseLink) ?>">Annulla</a><?php endif; ?></div>
    </form>
  </div>

  <div class="admin-table-wrap ct-tabella">
    <table>
      <thead><tr><th>Nome</th><th>Slug</th><?php if ($tipo === 'categorie'): ?><th>Descrizione</th><?php endif; ?><th style="text-align:right">Articoli</th></tr></thead>
      <tbody>
      <?php foreach ($voci as $v): ?>
        <tr class="ct-riga">
          <td class="ct-titolo">
            <a class="ct-nome" href="<?= h($baseLink . ($tipo === 'tag' ? '&' : '?') . 'modifica=' . $v['id']) ?>"><?= h($v['name']) ?></a>
            <div class="ct-azioni">
              <a href="<?= h($baseLink . ($tipo === 'tag' ? '&' : '?') . 'modifica=' . $v['id']) ?>">Modifica</a>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= $v['id'] ?>"><button type="submit" class="ct-rosso" data-conferma="Eliminare «<?= h(($v['name'])) ?>»? Gli articoli restano.">Elimina</button></form>
              <a href="/<?= $tipo === 'tag' ? 'tag' : 'category' ?>/<?= h($v['slug']) ?>/" target="_blank">Visualizza</a>
            </div>
          </td>
          <td class="ct-muto"><?= h($v['slug']) ?></td>
          <?php if ($tipo === 'categorie'): ?><td class="ct-muto"><?= h(mb_substr((string)$v['description'], 0, 90)) ?></td><?php endif; ?>
          <td class="ct-muto" style="text-align:right"><a href="contenuti.php?tipo=articoli&amp;<?= $tipo === 'tag' ? 'q=' . urlencode($v['name']) : 'categoria=' . urlencode($v['slug']) ?>"><?= $conta[$v['slug']] ?? 0 ?></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$voci): ?><tr><td colspan="4"><div class="empty-state"><p>Niente qui.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
