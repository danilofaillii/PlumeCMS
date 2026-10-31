<!-- MODAL INFOGRAFICA -->
<div id="ig-overlay" style="display:none;position:fixed;inset:0;background:var(--velo);z-index:1000;overflow-y:auto;padding:2rem">
<div style="background:var(--surface);color:var(--text);max-width:900px;margin:0 auto;border-radius:16px;box-shadow:var(--ombra-grande)">
  <div style="display:flex;justify-content:space-between;align-items:center;padding:1.25rem 1.5rem;border-bottom:1px solid var(--border)">
    <h2 style="font-size:1.1rem;font-weight:700;margin:0">Configuratore Infografica</h2>
    <button type="button" data-al-clic="closeIgModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-muted)">&times;</button>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:0">
    <!-- FORM -->
    <div style="padding:1.5rem;border-right:1px solid var(--border);max-height:70vh;overflow-y:auto">
      <div class="form-group">
        <label>Tipo infografica</label>
        <select id="ig-type" data-al-cambio="igTypeChanged()" style="width:100%;padding:0.5rem;border:1px solid var(--border);border-radius:8px;font-size:0.9rem">
          <option value="steps">Steps (passaggi numerati)</option>
          <option value="checklist">Checklist (lista ✓/✕)</option>
          <option value="compare">Compare (confronto 2 colonne)</option>
          <option value="stats">Stats (numeri/percentuali)</option>
          <option value="quote">Quote (citazione)</option>
          <option value="flow">Flow (timeline/percorso)</option>
        </select>
      </div>
      <div class="form-group">
        <label>Titolo infografica</label>
        <input type="text" id="ig-title" data-al-input="igUpdatePreview()" style="width:100%;padding:0.5rem;border:1px solid var(--border);border-radius:8px;font-size:0.9rem" placeholder="Es: 5 Passi per Gestire l'Ansia">
      </div>
      <div id="ig-fields"></div>
      <button type="button" id="ig-add-row" data-al-clic="igAddRow()" style="margin-top:0.75rem;padding:0.4rem 1rem;background:var(--fondo-2);color:var(--text);border:1px solid var(--border);border-radius:8px;cursor:pointer;font-size:0.85rem;font-weight:600">+ Aggiungi riga</button>
    </div>
    <!-- PREVIEW -->
    <div style="padding:1.5rem;background:#fef9f0 /* fisso: anteprima dell infografica, come sul sito */;max-height:70vh;overflow-y:auto;border-radius:0 0 16px 0">
      <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#6b5848 /* fisso: sul fondo dell anteprima */;margin-bottom:0.75rem">Anteprima</div>
      <link rel="stylesheet" href="../assets/css/style.css">
      <div id="ig-preview" style="font-size:0.9rem"></div>
    </div>
  </div>
  <div style="display:flex;justify-content:flex-end;gap:0.75rem;padding:1rem 1.5rem;border-top:1px solid var(--border)">
    <button type="button" data-al-clic="closeIgModal()" class="btn btn-secondary">Annulla</button>
    <button type="button" data-al-clic="igInsert()" class="btn btn-primary">Inserisci nel testo</button>
  </div>
</div>
</div>

