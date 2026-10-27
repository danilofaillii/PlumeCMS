/* Configuratore delle infografiche: steps, checklist, compare, stats, quote, flow. Condiviso dalla scheda di articoli e pagine. */
let igCursorPos = 0;

// Chi apre il configuratore dice dove inserire: una funzione che riceve il testo (shortcode o HTML).
let igInserisci = null, igFormato = 'html';
function openIgModal(inserisci, formato) {
  igInserisci = inserisci || null; igFormato = formato || 'html';
  const ta = document.getElementById('content');
  igCursorPos = ta ? ta.selectionStart : 0;
  document.getElementById('ig-type').value = 'steps';
  document.getElementById('ig-title').value = '';
  igTypeChanged();
  document.getElementById('ig-overlay').style.display = 'block';
  document.body.style.overflow = 'hidden';
}

function closeIgModal() {
  document.getElementById('ig-overlay').style.display = 'none';
  document.body.style.overflow = '';
}

const igFieldDefs = {
  steps: { label: 'Passaggi', fields: [['label','Titolo passaggio'],['text','Descrizione']], canAdd: true },
  checklist: { label: 'Elementi', fields: [['text','Testo'],['icon','Icona','select',['check:✓ Positivo','x:✕ Negativo']]], canAdd: true },
  compare: { label: 'Righe', fields: [['left','Colonna sinistra'],['right','Colonna destra']], canAdd: true, extra: [['left_label','Etichetta sinistra','Es: Da evitare'],['right_label','Etichetta destra','Es: Da fare']] },
  stats: { label: 'Statistiche', fields: [['value','Valore (es: 70%)'],['label','Etichetta breve'],['caption','Descrizione (opz.)']], canAdd: true },
  quote: { label: 'Citazione', fields: [['text','Testo citazione'],['author','Autore (opz.)']], canAdd: false },
  flow: { label: 'Nodi', fields: [['label','Titolo nodo'],['text','Descrizione']], canAdd: true },
};

function igTypeChanged() {
  const type = document.getElementById('ig-type').value;
  const def = igFieldDefs[type];
  const container = document.getElementById('ig-fields');
  container.innerHTML = '';

  if (def.extra) {
    def.extra.forEach(([name, label, ph]) => {
      container.innerHTML += `<div class="form-group" style="margin-bottom:0.75rem">
        <label style="font-size:0.78rem;font-weight:600;color:#9c7b65;text-transform:uppercase;display:block;margin-bottom:0.25rem">${label}</label>
        <input type="text" data-extra="${name}" data-al-input="igUpdatePreview()" placeholder="${ph||''}" style="width:100%;padding:0.45rem 0.6rem;border:1px solid #e8ddd0;border-radius:6px;font-size:0.88rem">
      </div>`;
    });
  }

  container.innerHTML += `<div style="font-size:0.78rem;font-weight:600;color:#9c7b65;text-transform:uppercase;margin:1rem 0 0.5rem">${def.label}</div>`;
  container.innerHTML += '<div id="ig-rows"></div>';

  document.getElementById('ig-add-row').style.display = def.canAdd ? '' : 'none';

  const startRows = type === 'quote' ? 1 : 3;
  for (let i = 0; i < startRows; i++) igAddRow();
  igUpdatePreview();
}

// Toglie una riga e aggiorna l'anteprima (data-al-clic, senza onclick in linea).
function igTogliRiga(el) { if (el.parentElement) el.parentElement.remove(); igUpdatePreview(); }

function igAddRow() {
  const type = document.getElementById('ig-type').value;
  const def = igFieldDefs[type];
  const rows = document.getElementById('ig-rows');
  const idx = rows.children.length + 1;

  let html = `<div class="ig-row" style="background:#f4f1ec;padding:0.75rem;border-radius:8px;margin-bottom:0.5rem;position:relative">`;
  if (def.canAdd) {
    html += `<button type="button" data-al-clic="igTogliRiga(this)" style="position:absolute;top:0.4rem;right:0.5rem;background:none;border:none;cursor:pointer;color:#b91c1c;font-size:1rem" title="Rimuovi">&times;</button>`;
  }
  html += `<div style="font-size:0.72rem;color:#9c7b65;margin-bottom:0.4rem;font-weight:600">#${idx}</div>`;

  def.fields.forEach(([name, label, inputType, options]) => {
    if (inputType === 'select') {
      html += `<select data-field="${name}" data-al-cambio="igUpdatePreview()" style="width:100%;padding:0.35rem 0.5rem;border:1px solid #e8ddd0;border-radius:6px;font-size:0.85rem;margin-bottom:0.4rem">`;
      options.forEach(o => { const [v,l] = o.split(':'); html += `<option value="${v}">${l}</option>`; });
      html += `</select>`;
    } else {
      html += `<input type="text" data-field="${name}" data-al-input="igUpdatePreview()" placeholder="${label}" style="width:100%;padding:0.35rem 0.5rem;border:1px solid #e8ddd0;border-radius:6px;font-size:0.85rem;margin-bottom:0.4rem">`;
    }
  });
  html += '</div>';
  rows.insertAdjacentHTML('beforeend', html);
  igUpdatePreview();
}

function igGetData() {
  const type = document.getElementById('ig-type').value;
  const def = igFieldDefs[type];
  const data = {};

  document.querySelectorAll('[data-extra]').forEach(el => { data[el.dataset.extra] = el.value; });

  if (type === 'quote') {
    const row = document.querySelector('.ig-row');
    if (row) {
      row.querySelectorAll('[data-field]').forEach(el => { data[el.dataset.field] = el.value; });
    }
  } else {
    data.items = [];
    document.querySelectorAll('.ig-row').forEach(row => {
      const item = {};
      row.querySelectorAll('[data-field]').forEach(el => { item[el.dataset.field] = el.value; });
      if (Object.values(item).some(v => v.trim())) data.items.push(item);
    });
  }
  return data;
}

function igRenderPreview(type, title, data) {
  const e = s => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

  if (type === 'steps' && data.items) {
    let h = `<figure class="ig ig-steps"><h3 class="ig-title">${e(title)}</h3><ol class="ig-steps-list">`;
    data.items.forEach((it, i) => {
      h += `<li class="ig-step"><span class="ig-step-num">${String(i+1).padStart(2,'0')}</span><div class="ig-step-body"><strong class="ig-step-label">${e(it.label||'')}</strong><p class="ig-step-text">${e(it.text||'')}</p></div></li>`;
    });
    return h + '</ol></figure>';
  }
  if (type === 'checklist' && data.items) {
    let h = `<figure class="ig ig-checklist"><h3 class="ig-title">${e(title)}</h3><ul class="ig-check-list">`;
    data.items.forEach(it => {
      const icon = it.icon === 'x' ? 'x' : 'check';
      h += `<li class="ig-check-item ig-icon-${icon}"><span class="ig-check-icon">${icon==='x'?'✕':'✓'}</span><span class="ig-check-text">${e(it.text||'')}</span></li>`;
    });
    return h + '</ul></figure>';
  }
  if (type === 'compare' && data.rows) {
    let h = `<figure class="ig ig-compare"><h3 class="ig-title">${e(title)}</h3><div class="ig-compare-grid">`;
    h += `<div class="ig-compare-head ig-compare-left">${e(data.left_label||'Invece di')}</div>`;
    h += `<div class="ig-compare-head ig-compare-right">${e(data.right_label||'Fai')}</div>`;
    (data.rows||data.items).forEach(r => {
      h += `<div class="ig-compare-cell ig-compare-left">${e(r.left||'')}</div>`;
      h += `<div class="ig-compare-cell ig-compare-right">${e(r.right||'')}</div>`;
    });
    return h + '</div></figure>';
  }
  if (type === 'stats' && data.items) {
    let h = `<figure class="ig ig-stats"><h3 class="ig-title">${e(title)}</h3><div class="ig-stats-grid">`;
    data.items.forEach(it => {
      h += `<div class="ig-stat"><div class="ig-stat-value">${e(it.value||'')}</div><div class="ig-stat-label">${e(it.label||'')}</div>`;
      if (it.caption) h += `<div class="ig-stat-caption">${e(it.caption)}</div>`;
      h += '</div>';
    });
    return h + '</div></figure>';
  }
  if (type === 'quote') {
    let h = `<figure class="ig ig-quote"><blockquote class="ig-quote-text">${e(data.text||'')}</blockquote>`;
    if (data.author) h += `<figcaption class="ig-quote-author">— ${e(data.author)}</figcaption>`;
    return h + '</figure>';
  }
  if (type === 'flow' && data.items) {
    let h = `<figure class="ig ig-flow"><h3 class="ig-title">${e(title)}</h3><div class="ig-flow-track">`;
    data.items.forEach(it => {
      h += `<div class="ig-flow-step"><div class="ig-flow-dot"></div><div class="ig-flow-label">${e(it.label||'')}</div><div class="ig-flow-text">${e(it.text||'')}</div></div>`;
    });
    return h + '</div></figure>';
  }
  return '<p style="color:#9c7b65;text-align:center;padding:2rem">Compila i campi per vedere l\'anteprima</p>';
}

function igUpdatePreview() {
  const type = document.getElementById('ig-type').value;
  const title = document.getElementById('ig-title').value;
  const data = igGetData();

  if (type === 'compare' && data.items && !data.rows) {
    data.rows = data.items;
    delete data.items;
  }

  document.getElementById('ig-preview').innerHTML = igRenderPreview(type, title, data);
}

function igInsert() {
  const type = document.getElementById('ig-type').value;
  const title = document.getElementById('ig-title').value;
  const data = igGetData();
  if (type === 'compare' && data.items && !data.rows) { data.rows = data.items; delete data.items; }
  const json = JSON.stringify(data);
  const shortcode = `\n[IG:${type}:${title}]\n${json}\n[/IG]\n`;
  if (igInserisci) { igInserisci(shortcode, igRenderPreview(type, title, data)); closeIgModal(); return; }
  const ta = document.getElementById('content');
  const before = ta.value.substring(0, igCursorPos);
  const after = ta.value.substring(igCursorPos);
  ta.value = before + shortcode + after;
  closeIgModal();
  ta.focus();
  ta.setSelectionRange(igCursorPos + shortcode.length, igCursorPos + shortcode.length);
}
document.addEventListener('DOMContentLoaded', function () {
  var o = document.getElementById('ig-overlay');
  if (o) o.addEventListener('click', function (ev) { if (ev.target === this) closeIgModal(); });
});
