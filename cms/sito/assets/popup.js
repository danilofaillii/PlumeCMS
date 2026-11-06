// Popup del sito: compaiono dopo qualche secondo, a una certa profondità di scorrimento, o quando il mouse
// esce dalla pagina. Chi li chiude non li rivede per i giorni scelti nel pannello (memoria del browser).
(function () {
  function ricorda(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function salva(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  var aperto = null;
  function apri(p) {
    if (aperto) return;
    aperto = p;
    p.hidden = false;
    document.body.style.overflow = 'hidden';
    var primo = p.querySelector('input,button,a');
    if (primo) primo.focus();
  }
  function chiudi(p) {
    p.hidden = true; aperto = null;
    document.body.style.overflow = '';
    salva('popup-' + p.id, String(Date.now()));
  }
  document.querySelectorAll('.popup').forEach(function (p) {
    var giorni = parseInt(p.dataset.giorni, 10) || 0;
    var ultimo = parseInt(ricorda('popup-' + p.id), 10) || 0;
    // Modulo del popup appena inviato con un errore: il popup si riapre subito sulla risposta, memoria o no.
    var subito = p.dataset.subito === '1';
    if (!subito && giorni && ultimo && Date.now() - ultimo < giorni * 86400000) return;
    p.querySelector('.popup-chiudi').addEventListener('click', function () { chiudi(p); });
    p.addEventListener('click', function (e) { if (e.target === p) chiudi(p); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && aperto === p) chiudi(p); });
    var valore = parseInt(p.dataset.valore, 10) || 0;
    if (subito) apri(p);
    else if (p.dataset.innesco === 'secondi') setTimeout(function () { apri(p); }, valore * 1000);
    else if (p.dataset.innesco === 'scroll') {
      var fatto = false;
      window.addEventListener('scroll', function () {
        if (fatto) return;
        var h = document.documentElement;
        var pct = (window.scrollY + window.innerHeight) / Math.max(1, h.scrollHeight) * 100;
        if (pct >= valore) { fatto = true; apri(p); }
      }, { passive: true });
    } else if (p.dataset.innesco === 'uscita') {
      var fatto2 = false;
      document.addEventListener('mouseout', function (e) {
        if (fatto2 || e.relatedTarget || e.clientY > 10) return;
        fatto2 = true; apri(p);
      });
    }
  });
})();
