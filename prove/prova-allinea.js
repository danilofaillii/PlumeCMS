// 2.21.0: l'allineamento delle parti dei widget, misurato nel browser (richiesta di Danilo: "voglio la possibilità di allineare
// tutto: occhiello, titolo, testo sotto il titolo; vale per tutti i widget"). Le pagine le fa prove/pagina-allinea.php: ogni
// parte allineata a sinistra, al centro, a destra o giustificata, e l'allineamento di tutto il widget. Per ogni parte con del
// testo visibile si misura dove sta il testo: il testo dentro il suo riquadro (text-align) e il riquadro dentro il suo
// contenitore (un blocco più stretto della colonna, come il testo sotto il titolo, si sposta con lui). Una parte il cui testo
// riempie tutta la riga non si può giudicare: si conta a parte.
// uso: node prove/prova-allinea.js <base> <cartella CMS> <cartella dei JSON di pagina-allinea.php> [larghezza]
const { chromium } = require('playwright');
const fs = require('fs'), path = require('path');
const [B, CMS, J, LARG = '1280'] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 400) : '')); };

const misura = async (p, id, sel, val) => p.evaluate(({ id, sel, val }) => {
  const radice = document.querySelector('.ed-' + id);
  if (!radice) return { stato: 'manca il widget' };
  const els = sel === '' ? [radice] : sel.split(',').flatMap(s => [...radice.querySelectorAll(s.trim())]);
  const scatola = el => {
    const r = el.getBoundingClientRect(), cs = getComputedStyle(el);
    const pl = parseFloat(cs.paddingLeft) + parseFloat(cs.borderLeftWidth), pr = parseFloat(cs.paddingRight) + parseFloat(cs.borderRightWidth);
    return { l: r.left + pl, r: r.right - pr, w: r.width - pl - pr };
  };
  for (const el of els) {
    const r = el.getBoundingClientRect();
    if (r.width < 2 || r.height < 2 || getComputedStyle(el).visibility === 'hidden') continue;
    // Il testo: solo i nodi di testo diretti e quelli dei figli in linea (non i blocchi dentro, che hanno le loro regole).
    const rg = document.createRange(); rg.selectNodeContents(el);
    const rects = [...rg.getClientRects()].filter(x => x.width > 0.5 && x.height > 2);
    if (!rects.length || !(el.textContent || '').trim()) continue;
    // La prima riga: per un testo su più righe le righe piene non dicono niente, conta l'ultima.
    const righe = rects.sort((a, b) => a.top - b.top);
    const ultima = righe.filter(x => Math.abs(x.top - righe[righe.length - 1].top) < 3);
    const t = { l: Math.min(...ultima.map(x => x.left)), r: Math.max(...ultima.map(x => x.right)) };
    const box = scatola(el);
    const cs = getComputedStyle(el);
    const inLinea = cs.display.startsWith('inline');
    // Il contenitore: il blocco più vicino che non sia in linea.
    let pa = el.parentElement; while (pa && getComputedStyle(pa).display.startsWith('inline')) pa = pa.parentElement;
    const cont = scatola(pa || el);
    const ref = inLinea ? cont : box;                  // un elemento in linea si allinea dentro il suo blocco
    // Affiancato a un fratello sulla stessa riga (celle, colonne di una griglia, nome e prezzo): conta l'allineamento dentro la
    // sua casella, cioè text-align, non dove sta la casella.
    const pcs = el.parentElement ? getComputedStyle(el.parentElement) : null;
    const inGriglia = pcs && (pcs.display.includes('grid') || (pcs.display.includes('flex') && !pcs.flexDirection.startsWith('column')));
    const affiancato = !inLinea && (inGriglia || [...(el.parentElement ? el.parentElement.children : [])].some(f => {
      if (f === el) return false; const q = f.getBoundingClientRect();
      return q.width > 1 && q.height > 1 && q.top < r.bottom - 2 && q.bottom > r.top + 2;
    }));
    const pieno = (t.r - t.l) >= ref.w - 6;
    const d = { ta: cs.textAlign, display: cs.display, testo: { l: Math.round(t.l), r: Math.round(t.r) }, ref: { l: Math.round(ref.l), r: Math.round(ref.r) },
      box: { l: Math.round(box.l), r: Math.round(box.r) }, cont: { l: Math.round(cont.l), r: Math.round(cont.r) } };
    if (val === 'justify') return { stato: cs.textAlign === 'justify' ? 'ok' : 'ko', d };
    // Una parte fatta a riga flessibile (testo e icona): conta justify-content.
    if (cs.display.includes('flex') && val !== 'justify') {
      const j = { left: ['flex-start', 'start', 'normal', 'left'], center: ['center'], right: ['flex-end', 'end', 'right'] }[val];
      return { stato: j.includes(cs.justifyContent) && cs.textAlign === val ? 'ok' : 'ko', d: { ...d, flex: cs.justifyContent } };
    }
    // Ruotata (il retro di una scheda girevole): le misure del browser sono capovolte, conta lo stile.
    let ruotata = false; for (let x = el; x; x = x.parentElement) if (/matrix3d|rotate/.test(getComputedStyle(x).transform) && getComputedStyle(x).transform !== 'none') { ruotata = true; break; }
    if (ruotata) return { stato: cs.textAlign === val ? 'ok' : 'ko', d: { ...d, ruotata: true } };
    if (affiancato) return { stato: cs.textAlign === val ? 'ok' : 'ko', d: { ...d, affiancato: true } };
    // Il testo riempie il suo riquadro: se il riquadro è più stretto del contenitore (si stringe attorno al testo) conta dove
    // sta il riquadro; se è largo quanto il contenitore, il testo è lungo quanto la riga e non si giudica.
    if (pieno && !inLinea && box.w < cont.w - 6) {
      // Stretto attorno al testo in una colonna flessibile: si sposta con i margini (auto da una parte, 0 dall'altra).
      const ml = parseFloat(cs.marginLeft), mr = parseFloat(cs.marginRight);
      if (val === 'right' && ml > 0.5 && mr < 0.5 && cs.textAlign === 'right') return { stato: 'ok', d };
      if (val === 'center' && Math.abs(ml - mr) <= 2 && ml > 0.5 && cs.textAlign === 'center') return { stato: 'ok', d };
      if (val === 'left' && mr > 0.5 && ml < 0.5 && cs.textAlign === 'left') return { stato: 'ok', d };
      const fuori = val === 'right' ? Math.abs(box.r - cont.r) <= 3 : val === 'left' ? Math.abs(box.l - cont.l) <= 3 : Math.abs((box.l + box.r) / 2 - (cont.l + cont.r) / 2) <= 3;
      return { stato: fuori ? 'ok' : 'ko', d: { ...d, stretto: true } };
    }
    if (pieno) return { stato: 'pieno', d };
    const dentro = val === 'right' ? Math.abs(t.r - ref.r) <= 3 : val === 'left' ? Math.abs(t.l - ref.l) <= 3 : Math.abs((t.l + t.r) / 2 - (ref.l + ref.r) / 2) <= 3;
    // Il riquadro dentro il contenitore, solo se è più stretto (un blocco con una larghezza massima).
    let fuori = true;
    if (!inLinea && cs.maxWidth !== 'none' && box.w < cont.w - 6) fuori = val === 'right' ? Math.abs(box.r - cont.r) <= 3 : val === 'left' ? Math.abs(box.l - cont.l) <= 3 : Math.abs((box.l + box.r) / 2 - (cont.l + cont.r) / 2) <= 3;
    return { stato: dentro && fuori ? 'ok' : 'ko', d: { ...d, dentro, fuori } };
  }
  return { stato: 'non visto' };
}, { id, sel, val });

(async () => {
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: +LARG, height: 900 } })).newPage();
  const conti = {};
  for (const f of fs.readdirSync(J).filter(f => /^al-.*\.json$/.test(f)).sort()) {
    const dati = JSON.parse(fs.readFileSync(path.join(J, f), 'utf8'));
    const val = dati.slug.replace(/^allinea-(tutto-)?/, '');
    const tutto = dati.slug.startsWith('allinea-tutto-');
    await p.goto(B + '/' + dati.slug + '/', { waitUntil: 'networkidle' });
    await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 700) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 30)); } });
    const c = conti[dati.slug] = { ok: 0, ko: 0, pieno: 0, nonvisto: 0, ko_elenco: [] };
    for (const w of dati.widget) {
      // Con l'allineamento di tutto il widget si misurano tutte le parti del widget (che seguono il widget) e il widget stesso.
      const parti = tutto ? w.tutte.map(s => ['parte', s]) : w.parti;
      for (const [nome, sel] of parti) {
        const m = await misura(p, w.id, sel, val);
        if (m.stato === 'ok') c.ok++;
        else if (m.stato === 'pieno') c.pieno++;
        else if (m.stato === 'ko') { c.ko++; c.ko_elenco.push(`${w.tipo} ${nome} "${sel}" ${JSON.stringify(m.d)}`); }
        else c.nonvisto++;
      }
    }
    ok(`${dati.slug} a ${LARG} px: ogni parte misurata è allineata (${c.ok} ok, ${c.pieno} che riempiono la riga, ${c.nonvisto} senza testo visibile)`, c.ko === 0, c.ko_elenco.slice(0, 40));
    if (c.ko) for (const r of c.ko_elenco) console.log('    ' + r.slice(0, 330));
  }
  await br.close();
  console.log(`${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
