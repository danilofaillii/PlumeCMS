<?php
/**
 * Pezzi comuni delle pagine del pannello della Vetrina (addon, 2.0.0). Le pagine si aprono da
 * admin/addon-pagina.php?a=vetrina&p=<pagina>: auth, CSRF e require_admin() sono già fatti lì.
 */
require_once __DIR__ . '/vetrina.php';
vt_schema();

function vt_admin_url(string $pagina, array $q = []): string {
    return 'addon-pagina.php?' . http_build_query(['a' => 'vetrina', 'p' => $pagina] + $q);
}

function vt_admin_vai(string $pagina, array $q = []): never {
    header('Location: ' . vt_admin_url($pagina, $q));
    exit;
}

/** Lo stile delle pagine della vetrina nel pannello (le variabili sono quelle degli aspetti del pannello). */
function vt_admin_stile(): string {
    return '<style>
.vt-a-schede{display:flex;gap:.4rem;flex-wrap:wrap;margin:0 0 1rem}
.vt-a-schede a{padding:.35rem .8rem;border:1px solid var(--border);border-radius:999px;text-decoration:none;color:var(--text);font-size:.88rem}
.vt-a-schede a[aria-current]{background:var(--accent,#2563eb);border-color:var(--accent,#2563eb);color:#fff}
.vt-a-foto{width:48px;height:48px;object-fit:cover;border-radius:6px;display:block;background:var(--border)}
.vt-a-griglia{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:1.5rem;align-items:start}
@media (max-width:1000px){.vt-a-griglia{grid-template-columns:minmax(0,1fr)}}
.vt-a-campi{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:0 1rem}
.form-group textarea.vt-a-corto{min-height:5rem;font-family:inherit;font-size:.92rem}
.form-group textarea.vt-a-medio{min-height:10rem;font-family:inherit;font-size:.92rem}
.vt-a-spunta{display:flex!important;gap:.5rem;align-items:center;font-weight:400!important;text-transform:none!important;letter-spacing:0!important;margin:.35rem 0}
.vt-a-spunta input{width:auto!important}
.vt-a-fotolista{display:flex;gap:.75rem;flex-wrap:wrap}
.vt-a-fotolista figure{margin:0;width:120px;font-size:.8rem}
.vt-a-fotolista img{width:120px;height:120px;object-fit:cover;border-radius:8px;display:block;margin-bottom:.3rem}
.vt-a-varianti{width:100%;border-collapse:collapse}
.vt-a-varianti th{text-align:left;font-size:.78rem;color:var(--text-muted);font-weight:600;padding:.3rem}
.vt-a-varianti td{padding:.25rem}
.vt-a-varianti input[type=text]{width:100%;padding:.4rem .5rem;border:1px solid var(--border);border-radius:6px;background:var(--campo,transparent);color:var(--text)}
.vt-a-storia{list-style:none;padding:0;margin:0;font-size:.88rem}
.vt-a-storia li{padding:.25rem 0;border-bottom:1px solid var(--border)}
.vt-a-tabella-wrap{overflow-x:auto}
.form-card p{margin:.5rem 0}
.vt-a-numeri{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}
.vt-a-numeri div{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:.7rem 1rem;min-width:8rem}
.vt-a-numeri b{display:block;font-size:1.5rem}
.vt-a-numeri span{font-size:.8rem;color:var(--text-muted)}
</style>';
}

/** Il badge di uno stato d'ordine. */
function vt_admin_badge(string $stato): string {
    $cl = ['nuovo' => 'badge-scheduled', 'pagato' => 'badge-published', 'spedito' => 'badge-published', 'consegnato' => 'badge-draft', 'annullato' => 'badge-draft'][$stato] ?? 'badge-draft';
    return '<span class="badge ' . $cl . ' vt-stato-' . h($stato) . '">' . h(VT_STATI[$stato] ?? $stato) . '</span>';
}
