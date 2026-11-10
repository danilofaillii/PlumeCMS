// Popup del sito: compaiono dopo qualche secondo, a una certa profondità di scorrimento, o quando il mouse
// esce dalla pagina. Chi li chiude non li rivede per i giorni scelti nel pannello (memoria del browser).
// 2.39.0: un link con indirizzo "#chiudi" dentro il popup lo chiude (e conta come chiuso); il fuoco entra nella finestra
// e torna dov'era; al centro la finestra tiene il fuoco dentro (Tab), nelle altre posizioni la pagina resta usabile.
(function () {
  function ricorda(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function salva(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  var aperto = null, prima = null;
  function modale(p) { return !/\bpopup-p-/.test(p.className); }
  function fuocabili(p) {
    return Array.prototype.filter.call(p.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'), function (el) {
      return el.offsetWidth > 0 || el.offsetHeight > 0 || el === document.activeElement;
    });
  }
  function apri(p) {
    if (aperto) return;
    aperto = p;
    prima = document.activeElement;
    p.hidden = false;
    if (modale(p)) document.body.style.overflow = 'hidden';
    var primo = p.querySelector('input,button,a');
    if (primo) primo.focus();
    // I widget del disegno (caroselli, schede...) si misurano di nuovo ora che la finestra si vede.
    try { window.dispatchEvent(new Event('resize')); } catch (e) {}
  }
  function chiudi(p) {
    p.hidden = true; aperto = null;
    document.body.style.overflow = '';
    if (p.dataset.prova !== '1') salva('popup-' + p.id, String(Date.now()));
    if (prima && typeof prima.focus === 'function' && document.contains(prima)) { try { prima.focus(); } catch (e) {} }
    prima = null;
  }
  document.querySelectorAll('.popup').forEach(function (p) {
    var giorni = parseInt(p.dataset.giorni, 10) || 0;
    var ultimo = parseInt(ricorda('popup-' + p.id), 10) || 0;
    // Modulo del popup appena inviato con un errore: il popup si riapre subito sulla risposta, memoria o no.
    var subito = p.dataset.subito === '1';
    if (!subito && giorni && ultimo && Date.now() - ultimo < giorni * 86400000) return;
    p.querySelector('.popup-chiudi').addEventListener('click', function () { chiudi(p); });
    p.addEventListener('click', function (e) {
      if (e.target === p) { chiudi(p); return; }
      var a = e.target.closest ? e.target.closest('a[href]') : null;
      if (a && p.contains(a) && a.getAttribute('href') === '#chiudi') { e.preventDefault(); chiudi(p); }
    });
    document.addEventListener('keydown', function (e) {
      if (aperto !== p) return;
      if (e.key === 'Escape') { chiudi(p); return; }
      if (e.key === 'Tab' && modale(p)) {
        var f = fuocabili(p);
        if (!f.length) return;
        var i = f.indexOf(document.activeElement);
        if (e.shiftKey && (i <= 0)) { e.preventDefault(); f[f.length - 1].focus(); }
        else if (!e.shiftKey && (i === -1 || i === f.length - 1)) { e.preventDefault(); f[0].focus(); }
      }
    });
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
