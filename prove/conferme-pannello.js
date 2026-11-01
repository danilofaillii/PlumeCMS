// UX 2026-11: le conferme del pannello non sono piu confirm() del browser ma una finestra del pannello (pannello.js,
// window.pnConferma). Per le prove che prima scrivevano  p.on('dialog', d => d.accept())  questo modulo fa la stessa cosa
// con tutte e due: il dialogo vero del browser (alert, confirm dell'editor) e la finestra del pannello. Il gestore riceve
// un oggetto come quello di Playwright: message(), type() ('confirm'), accept(), dismiss(). accept() preme il pulsante che
// conferma, dismiss() preme Annulla: la prova passa dagli stessi pulsanti di una persona.
//
//   const pnConferme = require('./conferme-pannello');
//   pnConferme.on(p, d => d.accept());                 // al posto di p.on('dialog', ...)
//   pnConferme.once(p, d => { ...; d.dismiss(); });    // al posto di p.once('dialog', ...)
const STATO = new WeakMap();

function ascolta() {
  if (window.__pnConfermeAscolta) return;
  window.__pnConfermeAscolta = true;
  document.addEventListener('pn-conferma', (e) => {
    if (typeof window.__pnConfermaDecidi !== 'function') return;
    window.__pnConfermaDecidi(e.detail.testo).then((si) => { if (si !== null) e.detail.rispondi(!!si); });
  });
}

function prepara(page) {
  let s = STATO.get(page);
  if (s) return s;
  s = { gestori: [] };
  STATO.set(page, s);
  // Le tre chiamate partono subito, in ordine, prima di qualunque goto della prova.
  page.exposeBinding('__pnConfermaDecidi', async (_src, testo) => {
    const g = s.gestori[0];
    if (!g) return null; // nessun gestore: la finestra resta aperta, come un dialogo vero senza gestore
    if (g.unaVolta) togli(page, s, g);
    return await new Promise((risolvi) => {
      const d = { type: () => 'confirm', message: () => testo, defaultValue: () => '', accept: async () => risolvi(true), dismiss: async () => risolvi(false) };
      Promise.resolve().then(() => g.f(d)).catch(() => risolvi(false));
    });
  }).catch(() => {});
  page.addInitScript(ascolta).catch(() => {});
  page.evaluate(ascolta).catch(() => {});
  return s;
}

function togli(page, s, g) {
  const i = s.gestori.indexOf(g);
  if (i >= 0) s.gestori.splice(i, 1);
  page.off('dialog', g.nativo);
}

function registra(page, f, unaVolta) {
  const s = prepara(page);
  const g = { f, unaVolta };
  g.nativo = (d) => { if (unaVolta) togli(page, s, g); return f(d); };
  s.gestori.push(g);
  page.on('dialog', g.nativo);
}

module.exports = { on: (page, f) => registra(page, f, false), once: (page, f) => registra(page, f, true) };
