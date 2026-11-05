// 2.26.1: i fogli e gli script dei widget extra che stanno nel footer (o nell'header) escono in ogni pagina, non solo in
// quelle che hanno un widget della stessa famiglia nel corpo. Trovato costruendo il sito di Cucina Creativa: footer con
// Icone social e Barra contatti, pagina con solo hero, titolo, passi, immagine e pulsanti, footer senza stile.
// uso: node prove/prova-cornice-widget.js <base> <cartella del CMS installato> [cartella foto] [php]
//   php: il comando PHP per l'aiuto da riga di comando (predefinito "php"; per esempio "docker exec php84 php").
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const path = require('path');
const [B, CMS, FOTO = '', PHP = 'php'] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 240) : '')); };
const cli = (c) => JSON.parse(execSync(`${PHP} ${path.join(__dirname, 'cornice-widget-cli.php')} ${CMS} ${c}`, { encoding: 'utf8' }));

(async () => {
  const dati = cli('prepara');
  ok('footer di prova pubblicato e predefinito, due pagine create', dati.footer > 0 && dati.pagine.length === 2, dati);
  const br = await chromium.launch();
  const errori = [];
  // Quante volte la pagina carica un file di sito/assets (link o script), e il suo stato nel browser.
  const guarda = async (p) => p.evaluate(() => {
    const quanti = (sel, nome) => [...document.querySelectorAll(sel)].filter(e => (e.getAttribute('href') || e.getAttribute('src') || '').split('?')[0].endsWith('/' + nome)).length;
    const barra = document.querySelector('footer .ew-barra-contatti');
    const voce = document.querySelector('footer .ew-social-voce');
    const fogli = [...document.styleSheets].filter(s => (s.href || '').split('?')[0].endsWith('/widget.css'));
    let regole = -1; try { regole = fogli[0] ? fogli[0].cssRules.length : -1; } catch (e) { regole = -2; }
    // Il foglio del widget deve stare prima del CSS dei modelli (le regole dei singoli elementi vincono).
    const link = [...document.querySelectorAll('link[rel=stylesheet]')].find(e => (e.getAttribute('href') || '').split('?')[0].endsWith('/widget.css'));
    const modelli = document.getElementById('ed-css-tema');
    return {
      css: quanti('link[rel=stylesheet]', 'widget.css'), js: quanti('script[src]', 'widget.js'), regole,
      primaDeiModelli: !!(link && modelli && (link.compareDocumentPosition(modelli) & Node.DOCUMENT_POSITION_FOLLOWING)),
      barra: barra ? { display: getComputedStyle(barra).display, position: getComputedStyle(barra).position, alta: barra.getBoundingClientRect().height } : null,
      social: voce ? { raggio: getComputedStyle(voce).borderRadius, sfondo: getComputedStyle(voce).backgroundColor } : null,
    };
  });
  for (const [larghezza, nome] of [[1280, 'pc'], [390, 'tel']]) {
    const ctx = await br.newContext({ viewport: { width: larghezza, height: larghezza === 390 ? 844 : 900 } });
    const p = await ctx.newPage();
    p.on('pageerror', e => errori.push(e.message.slice(0, 200)));
    p.on('response', r => { if (r.status() >= 400 && /\/sito\/assets\//.test(r.url())) errori.push(r.status() + ' ' + r.url()); });
    for (const [percorso, quale] of [['/senza-widget-extra/', 'pagina senza widget extra'], ['/anche-nel-corpo/', 'pagina con le Icone social anche nel corpo'], ['/', 'home'], ['/pagina-che-non-esiste-cornice/', 'pagina 404 (vista senza editor)']]) {
      await p.goto(B + percorso, { waitUntil: 'networkidle' });
      const g = await guarda(p);
      const dove = `${quale}, ${larghezza} px:`;
      ok(`${dove} widget.css caricato una volta sola`, g.css === 1 && g.regole > 0, g);
      ok(`${dove} widget.js caricato una volta sola`, g.js === 1, g.js);
      if (percorso === '/senza-widget-extra/' || percorso.includes('non-esiste')) ok(`${dove} widget.css prima del CSS di header e footer`, g.primaDeiModelli, g.primaDeiModelli);
      ok(`${dove} l'icona social del footer ha il cerchio colorato`, g.social && g.social.raggio === '50%' && g.social.sfondo !== 'rgba(0, 0, 0, 0)', g.social);
      if (larghezza === 1280) ok(`${dove} la barra contatti (solo telefono) è nascosta`, g.barra && g.barra.display === 'none', g.barra);
      else ok(`${dove} la barra contatti si vede, fissa in fondo`, g.barra && g.barra.display === 'flex' && g.barra.position === 'fixed' && g.barra.alta > 40, g.barra);
      if (FOTO && percorso === '/senza-widget-extra/') await p.screenshot({ path: `${FOTO}/cornice-${nome}.png`, fullPage: true });
    }
    await ctx.close();
  }
  ok('nessun errore JavaScript e nessun file di sito/assets mancante', errori.length === 0, errori);
  await br.close();
  const t = cli('togli');
  ok('pulizia: footer e pagine di prova tolti', t.tolti.length === 3, t);
  console.log(`\n${n - ko} su ${n}`);
  process.exit(ko ? 1 : 0);
})().catch(e => { console.error(e); try { cli('togli'); } catch (x) {} process.exit(1); });
