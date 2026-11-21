<?php
/**
 * Statistiche > Visite (addon, 2.0.0): periodo (7, 30, 90 giorni o date scelte), visite e visitatori con il confronto
 * con il periodo prima, il grafico giorno per giorno (SVG disegnato qui, niente librerie), pagine più viste, fonti,
 * campagne, dispositivi, e il CSV. Si apre da admin/addon-pagina.php?a=statistiche&p=visite (solo amministratore).
 */
require_once __DIR__ . '/statistiche.php';

[$periodo, $dal, $al] = st_periodo($_GET);
[$pDal, $pAl] = st_periodo_prima($dal, $al);
$tot = st_totali($dal, $al);
$prima = st_totali($pDal, $pAl);
$giorni = st_per_giorno($dal, $al);
$pagine = st_classifica('pagina', $dal, $al, 20);
$fonti = st_classifica('fonte', $dal, $al, 20);
$campagne = st_classifica('campagna', $dal, $al, 20);
$dispositivi = st_classifica('dispositivo', $dal, $al, 3);
$url = fn(array $q) => 'addon-pagina.php?' . http_build_query(['a' => 'statistiche', 'p' => 'visite'] + $q);
$data = fn(string $g) => date('d/m/Y', strtotime($g));
$num = fn(int $n) => number_format($n, 0, ',', '.');

/** Il grafico: barre per le visite, linea per i visitatori, stessa scala (stessa unità: persone e pagine). */
function st_grafico(array $giorni): string {
    $W = 900; $H = 260; $sx = 44; $dx = 8; $su = 12; $giu = 30;
    $n = max(1, count($giorni));
    $max = max(1, ...array_map(fn($g) => $g['visite'], array_values($giorni)));
    // Una scala tonda: 1, 2, 5, 10, 20, 50...
    $grezzo = $max / 4;
    $e = 10 ** floor(log10($grezzo));
    $passo = 10 * $e;
    foreach ([1, 2, 5, 10] as $m) if ($m * $e >= $grezzo) { $passo = $m * $e; break; }
    $passo = max(1, (int)ceil($passo));
    $cima = $passo * 4;
    $pw = $W - $sx - $dx; $ph = $H - $su - $giu;
    $y = fn(float $v) => round($su + $ph - $v / $cima * $ph, 1);
    $col = $pw / $n; $bw = max(1.5, min(28, $col - 2));
    $s = '<svg class="st-grafico" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-labelledby="st-graf-tit"><title id="st-graf-tit">Visite e visitatori giorno per giorno</title>';
    for ($i = 0; $i <= 4; $i++) {
        $v = $passo * $i; $yy = $y($v);
        $s .= '<line x1="' . $sx . '" x2="' . ($W - $dx) . '" y1="' . $yy . '" y2="' . $yy . '" class="st-griglia"/><text x="' . ($sx - 6) . '" y="' . ($yy + 4) . '" class="st-asse" text-anchor="end">' . number_format($v, 0, ',', '.') . '</text>';
    }
    $punti = []; $i = 0;
    $ogni = max(1, (int)ceil($n / 8));
    foreach ($giorni as $g => $r) {
        $x = $sx + $i * $col;
        $cx = round($x + $col / 2, 1);
        $s .= '<g class="st-giorno"><title>' . date('d/m', strtotime($g)) . ': ' . $r['visite'] . ' visite, ' . $r['visitatori'] . ' visitatori</title>'
            . '<rect x="' . round($x, 1) . '" y="' . $su . '" width="' . round($col, 1) . '" height="' . $ph . '" class="st-area"/>'
            . ($r['visite'] > 0 ? '<rect x="' . round($cx - $bw / 2, 1) . '" y="' . $y($r['visite']) . '" width="' . round($bw, 1) . '" height="' . round($ph + $su - $y($r['visite']), 1) . '" rx="' . min(4, $bw / 2) . '" class="st-barra"/>' : '') . '</g>';
        $punti[] = $cx . ',' . $y($r['visitatori']);
        if ($i % $ogni === 0) $s .= '<text x="' . $cx . '" y="' . ($H - 10) . '" class="st-asse" text-anchor="middle">' . date('d/m', strtotime($g)) . '</text>';
        $i++;
    }
    $s .= '<polyline points="' . implode(' ', $punti) . '" class="st-linea"/>';
    if ($n <= 31) foreach ($punti as $pt) { [$px, $py] = explode(',', $pt); $s .= '<circle cx="' . $px . '" cy="' . $py . '" r="4" class="st-punto"/>'; }
    return $s . '</svg>';
}

$adminTitle = 'Statistiche delle visite';
require CMS_ADMIN . '/includes/admin-header.php';
?>
<style>
.st-schede{display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;margin:0 0 1rem}
.st-schede a{padding:.35rem .8rem;border:1px solid var(--border);border-radius:999px;text-decoration:none;color:var(--text);font-size:.88rem}
.st-schede a[aria-current]{background:var(--accent,#2563eb);border-color:var(--accent,#2563eb);color:#fff}
.st-schede form{display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;font-size:.88rem}
.st-schede input[type=date]{padding:.3rem .4rem;border:1px solid var(--border);border-radius:6px;background:var(--campo,transparent);color:var(--text)}
.st-numeri{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;margin-bottom:1rem}
.st-numeri div{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.2rem}
.st-numeri b{display:block;font-size:2rem;line-height:1.1}
.st-numeri span{font-size:.82rem;color:var(--text-muted)}
.st-numeri small{display:block;margin-top:.3rem;font-size:.8rem;color:var(--text-muted)}
.st-scatola{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.2rem;margin-bottom:1rem;min-width:0;overflow-x:auto}
.st-scatola h3{margin:0 0 .6rem;font-size:1rem}
.st-legenda{display:flex;gap:1.2rem;font-size:.85rem;color:var(--text-muted);margin-bottom:.4rem}
.st-legenda i{display:inline-block;width:12px;height:12px;border-radius:3px;margin-right:.35rem;vertical-align:-1px}
.st-grafico{width:100%;height:auto;display:block}
.st-griglia{stroke:var(--border);stroke-width:1}
.st-asse{fill:var(--text-muted);font-size:12px}
.st-area{fill:transparent}
.st-barra{fill:#2a78d6}
.st-giorno:hover .st-area{fill:rgba(42,120,214,.08)}
.st-linea{fill:none;stroke:#eb6834;stroke-width:2;stroke-linejoin:round}
.st-punto{fill:#eb6834;stroke:var(--surface,#fff);stroke-width:2}
.st-due{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr));gap:1rem}
.st-tabella{width:100%;border-collapse:collapse;font-size:.9rem}
.st-tabella th{text-align:left;font-size:.78rem;color:var(--text-muted);font-weight:600;padding:.3rem .4rem;border-bottom:1px solid var(--border)}
.st-tabella td{padding:.35rem .4rem;border-bottom:1px solid var(--border);overflow-wrap:anywhere}
.st-tabella td.n,.st-tabella th.n{text-align:right;white-space:nowrap}
.st-vuoto{color:var(--text-muted);font-size:.9rem;margin:0}
</style>
<p style="max-width:62rem;color:var(--text-muted);margin:0 0 1rem">Le visite del sito pubblico, contate dal sito stesso: senza cookie, senza servizi esterni, senza salvare indirizzi IP. Non contano i robot dei motori di ricerca, le anteprime e chi è entrato nel pannello. <b>Visitatori</b>: persone diverse in un giorno (chi torna il giorno dopo conta di nuovo).</p>
<nav class="st-schede" aria-label="Periodo">
  <?php foreach (['7' => 'Ultimi 7 giorni', '30' => 'Ultimi 30 giorni', '90' => 'Ultimi 90 giorni'] as $k => $et): ?><a href="<?= h($url(['periodo' => $k])) ?>"<?= $periodo === (string)$k ? ' aria-current="page"' : '' ?>><?= h($et) ?></a><?php endforeach; ?>
  <form method="get" id="st-personalizzato"><input type="hidden" name="a" value="statistiche"><input type="hidden" name="p" value="visite"><input type="hidden" name="periodo" value="personalizzato">
    <label>dal <input type="date" name="dal" value="<?= h($dal) ?>" max="<?= date('Y-m-d') ?>"></label> <label>al <input type="date" name="al" value="<?= h($al) ?>" max="<?= date('Y-m-d') ?>"></label>
    <button type="submit" class="btn btn-secondary btn-sm"<?= $periodo === 'personalizzato' ? ' aria-current="true"' : '' ?>>Mostra</button></form>
  <a href="<?= h('addon-pagina.php?' . http_build_query(['a' => 'statistiche', 'p' => 'esporta', 'periodo' => 'personalizzato', 'dal' => $dal, 'al' => $al])) ?>" id="st-csv">Scarica il CSV</a>
</nav>
<p style="font-size:.85rem;color:var(--text-muted);margin:-.4rem 0 1rem">Dal <?= h($data($dal)) ?> al <?= h($data($al)) ?>, confrontato con il periodo prima (dal <?= h($data($pDal)) ?> al <?= h($data($pAl)) ?>).</p>
<div class="st-numeri" id="st-numeri">
  <div><b data-st="visite"><?= $num($tot['visite']) ?></b><span>visite (pagine viste)</span><small>prima <?= $num($prima['visite']) ?> &middot; <?= h(st_cambio($tot['visite'], $prima['visite'])) ?></small></div>
  <div><b data-st="visitatori"><?= $num($tot['visitatori']) ?></b><span>visitatori</span><small>prima <?= $num($prima['visitatori']) ?> &middot; <?= h(st_cambio($tot['visitatori'], $prima['visitatori'])) ?></small></div>
  <div><b data-st="per-visitatore"><?= $tot['visitatori'] ? number_format($tot['visite'] / $tot['visitatori'], 1, ',', '.') : '0' ?></b><span>pagine per visitatore</span><small>prima <?= $prima['visitatori'] ? number_format($prima['visite'] / $prima['visitatori'], 1, ',', '.') : '0' ?></small></div>
</div>
<div class="st-scatola">
  <h3>Giorno per giorno</h3>
  <div class="st-legenda"><span><i style="background:#2a78d6"></i>Visite</span><span><i style="background:#eb6834"></i>Visitatori</span></div>
  <?= st_grafico($giorni) ?>
  <details style="margin-top:.6rem"><summary style="cursor:pointer;font-size:.85rem">Vedi i numeri giorno per giorno</summary>
    <table class="st-tabella" id="st-giorni"><thead><tr><th>Giorno</th><th class="n">Visite</th><th class="n">Visitatori</th></tr></thead><tbody>
    <?php foreach (array_reverse($giorni, true) as $g => $r): ?><tr><td><?= h($data($g)) ?></td><td class="n"><?= $num($r['visite']) ?></td><td class="n"><?= $num($r['visitatori']) ?></td></tr><?php endforeach; ?>
    </tbody></table></details>
</div>
<div class="st-due">
  <div class="st-scatola"><h3>Pagine più viste</h3>
    <?php if (!$pagine): ?><p class="st-vuoto">Nessuna visita in questo periodo.</p><?php else: ?>
    <table class="st-tabella" id="st-pagine"><thead><tr><th>Pagina</th><th class="n">Visite</th><th class="n" title="Persone che sono entrate nel sito da questa pagina">Entrati da qui</th></tr></thead><tbody>
    <?php foreach ($pagine as $r): ?><tr><td><a href="<?= h(rtrim(cms_url_sito(), '/') . $r['pagina']) ?>" target="_blank" rel="noopener"><?= h($r['pagina']) ?></a></td><td class="n"><?= $num($r['visite']) ?></td><td class="n"><?= $num($r['visitatori']) ?></td></tr><?php endforeach; ?>
    </tbody></table><?php endif; ?>
    <p class="st-vuoto" style="margin-top:.5rem;font-size:.8rem">«Entrati da qui»: le persone per cui questa è stata la prima pagina del giorno.</p>
  </div>
  <div class="st-scatola"><h3>Da dove arrivano</h3>
    <?php if (!$fonti): ?><p class="st-vuoto">Nessuna visita in questo periodo.</p><?php else: ?>
    <table class="st-tabella" id="st-fonti"><thead><tr><th>Provenienza</th><th class="n">Visitatori</th></tr></thead><tbody>
    <?php foreach ($fonti as $r): if ($r['visitatori'] === 0) continue; ?><tr><td><?= $r['fonte'] === '' ? '<i>Diretto o sconosciuto</i>' : h($r['fonte']) ?></td><td class="n"><?= $num($r['visitatori']) ?></td></tr><?php endforeach; ?>
    </tbody></table><?php endif; ?>
    <p class="st-vuoto" style="margin-top:.5rem;font-size:.8rem">Il sito da cui la persona è arrivata alla prima pagina del giorno. «Diretto»: indirizzo scritto a mano, un preferito, un'app che non dice da dove viene.</p>
  </div>
  <div class="st-scatola"><h3>Campagne</h3>
    <?php if (!$campagne): ?><p class="st-vuoto">Nessuna visita da link con utm_source, utm_medium o utm_campaign.</p><?php else: ?>
    <table class="st-tabella" id="st-campagne"><thead><tr><th>Fonte</th><th>Mezzo</th><th>Campagna</th><th class="n">Visitatori</th><th class="n">Visite</th></tr></thead><tbody>
    <?php foreach ($campagne as $r): ?><tr><td><?= h((string)$r['utm_source']) ?></td><td><?= h((string)$r['utm_medium']) ?></td><td><?= h((string)$r['utm_campaign']) ?></td><td class="n"><?= $num($r['visitatori']) ?></td><td class="n"><?= $num($r['visite']) ?></td></tr><?php endforeach; ?>
    </tbody></table><?php endif; ?>
  </div>
  <div class="st-scatola"><h3>Dispositivi</h3>
    <?php if (!$dispositivi): ?><p class="st-vuoto">Nessuna visita in questo periodo.</p><?php else: ?>
    <table class="st-tabella" id="st-dispositivi"><thead><tr><th>Dispositivo</th><th class="n">Visitatori</th><th class="n">Visite</th></tr></thead><tbody>
    <?php foreach ($dispositivi as $r): ?><tr data-dispositivo="<?= h((string)$r['dispositivo']) ?>"><td><?= h(ST_DISPOSITIVI[$r['dispositivo']] ?? (string)$r['dispositivo']) ?></td><td class="n"><?= $num($r['visitatori']) ?></td><td class="n"><?= $num($r['visite']) ?></td></tr><?php endforeach; ?>
    </tbody></table><?php endif; ?>
  </div>
</div>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
