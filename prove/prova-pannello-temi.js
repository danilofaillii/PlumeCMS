// Prova della pagina Editor > Temi (fase 3, 27 ottobre 2026) come un utente: menu, schede, anteprima, attiva,
// conferma rifiutata, avviso di tema guasto, ritorno all'aspetto di base, redattore senza accesso.
// Servono i temi di collaudo (prove/temi-collaudo.php). Schermate in <cartella schermate>.
// uso: node prova-pannello-temi.js <base> <cartella CMS installato> <utente> <password> <cartella schermate>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD, OUT] = process.argv.slice(2);
const cli = (...a) => execFileSync('php', [path.join(__dirname, 'tema-cli.php'), DIR, ...a], { encoding: 'utf8' });
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 220) : '')); };
const sfondo = (p) => p.evaluate(() => getComputedStyle(document.body).backgroundColor);
(async () => {
  cli('disattiva'); cli('ripristina-stile');
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1360, height: 900 } }); const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('console', m => { if (m.type() === 'error' && !/favicon/.test(m.text())) err.push(m.text().slice(0, 200)); });
  let rispondi = true, ultimaConferma = '';
  p.on('dialog', d => { ultimaConferma = d.message(); rispondi ? d.accept() : d.dismiss(); });

  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok('login', !p.url().includes('login.php'), p.url());

  // 1. Menu
  await p.goto(B + '/admin/design.php', { waitUntil: 'networkidle' });
  const menu = await p.locator('nav, aside').first().innerText();
  ok('menu Editor: voce Temi', /\bTemi\b/.test(menu));
  ok('menu Editor: "Colori delle infografiche" al posto di "Temi delle infografiche"', menu.includes('Colori delle infografiche') && !menu.includes('Temi delle infografiche'));
  await p.click('a[href="temi.php"]'); await p.waitForLoadState('networkidle');
  ok('la voce Temi apre la pagina', p.url().endsWith('/admin/temi.php'), p.url());

  // 2. Schede
  const schede = await p.locator('.tt-card').count();
  const quanti = cli('elenco').trim().split('\n').length;
  ok('schede: aspetto di base più un tema per cartella', schede === 1 + quanti, [schede, quanti]);
  ok('i temi di partenza hanno l\'immagine di anteprima', await p.locator('#tema-aria img.tt-immagine, #tema-bottega img.tt-immagine, #tema-cosmo img.tt-immagine').count() === 3 || quanti < 7);
  ok('aspetto di base attivo', await p.locator('#tema-base .badge', { hasText: 'attivo' }).count() === 1);
  ok('tema Prova: etichetta PHP e nome della cartella', await p.locator('#tema-prova .badge', { hasText: 'PHP' }).count() === 1 && (await p.locator('#tema-prova .tt-meta').innerText()).includes('temi/prova'));
  ok('tema Prova: anteprima dei colori (sfondo bianco del suo stile)', await p.locator('#tema-prova .tt-finto').evaluate(e => getComputedStyle(e).backgroundColor) === 'rgb(255, 255, 255)');
  ok('nessun avviso di guasto', await p.locator('.tt-guasto').count() === 0);
  await p.screenshot({ path: `${OUT}/temi-1-pagina.png`, fullPage: true });

  // 3. Anteprima in una scheda nuova, senza attivare
  const [nuova] = await Promise.all([ctx.waitForEvent('page'), p.click('#tema-prova a:has-text("Anteprima")')]);
  await nuova.waitForLoadState('networkidle');
  ok('Anteprima: si apre il sito con il tema', nuova.url().includes('preview=1&tema=prova') && (await nuova.locator('header').innerText()).includes('Questo header viene dal tema Prova'), nuova.url());
  ok('Anteprima: sfondo del tema', await sfondo(nuova) === 'rgb(255, 255, 255)');
  await nuova.screenshot({ path: `${OUT}/temi-2-anteprima.png` });
  await nuova.close();
  ok('Anteprima: niente attivato', /scelto= /.test(cli('stato')));

  // 4. Attiva, prima rifiutando la conferma
  rispondi = false;
  await p.click('#tema-prova button:has-text("Attiva")'); await p.waitForTimeout(400);
  ok('Attiva: la conferma spiega cosa cambia', /Cambiano colori.*header e footer del tema.*menu e immagini non cambiano.*codice PHP/s.test(ultimaConferma), ultimaConferma);
  ok('Attiva rifiutata: niente cambia', /scelto= /.test(cli('stato')));
  rispondi = true;
  await Promise.all([p.waitForNavigation(), p.click('#tema-prova button:has-text("Attiva")')]);
  const msg = await p.locator('.flash').first().innerText().catch(() => '');
  ok('Attiva: messaggio con il resoconto', msg.includes('Tema "Prova" attivo') && msg.includes('Versioni'), msg);
  ok('Attiva: la scheda Prova ora è attiva, con Personalizza', await p.locator('#tema-prova .badge', { hasText: 'attivo' }).count() === 1 && await p.locator('#tema-prova a:has-text("Personalizza")').count() === 1);
  ok('Attiva: l\'aspetto di base non è più attivo', await p.locator('#tema-base .badge', { hasText: 'attivo' }).count() === 0);
  await p.screenshot({ path: `${OUT}/temi-3-attivo.png`, fullPage: true });
  const sito = await ctx.newPage(); await sito.goto(B + '/', { waitUntil: 'networkidle' });
  ok('il sito ha il tema', (await sito.locator('header').innerText()).includes('Questo header viene dal tema Prova') && await sfondo(sito) === 'rgb(255, 255, 255)');
  await p.goto(B + '/admin/versioni.php', { waitUntil: 'networkidle' });
  ok('Versioni: lo stile salvato con la nota del tema', (await p.locator('body').innerText()).includes('tema Prova attivato'));

  // 5. Tema guasto: avviso e riprova
  await p.goto(B + '/admin/temi.php');
  rispondi = true;
  await Promise.all([p.waitForNavigation(), p.click('#tema-rotto button:has-text("Attiva")')]);
  await sito.goto(B + '/non-esiste/', { waitUntil: 'networkidle' });
  await p.goto(B + '/admin/temi.php', { waitUntil: 'networkidle' });
  const av = await p.locator('.tt-guasto').innerText().catch(() => '');
  ok('tema guasto: avviso con il nome, l\'errore e cosa fare', av.includes('"Rotto" si è rotto') && av.includes('errore voluto nella vista 404') && av.includes('aspetto di base'), av.slice(0, 160));
  ok('tema guasto: etichetta sulla scheda', await p.locator('#tema-rotto .badge', { hasText: 'guasto' }).count() === 1);
  ok('tema guasto: l\'aspetto di base dice "in uso adesso" e la scheda guasta non è bordata come attiva', await p.locator('#tema-base .badge', { hasText: 'in uso adesso' }).count() === 1 && await p.locator('#tema-rotto.attivo').count() === 0);
  await p.screenshot({ path: `${OUT}/temi-4-guasto.png`, fullPage: true });
  await Promise.all([p.waitForNavigation(), p.click('.tt-guasto button:has-text("Resta sull\'aspetto di base")')]);
  ok('Resta sull\'aspetto di base: avviso sparito, base attivo', await p.locator('.tt-guasto').count() === 0 && await p.locator('#tema-base .badge', { hasText: 'attivo' }).count() === 1);

  // 6. Torna all'aspetto di base da un tema attivo
  await Promise.all([p.waitForNavigation(), p.click('#tema-prova button:has-text("Attiva")')]);
  await Promise.all([p.waitForNavigation(), p.click('#tema-base button:has-text("Usa l\'aspetto di base")')]);
  ok('Usa l\'aspetto di base: conferma e base attivo', /Tornare all'aspetto di base/.test(ultimaConferma) && await p.locator('#tema-base .badge', { hasText: 'attivo' }).count() === 1, ultimaConferma);

  // 7. Una richiesta POST senza il token del modulo non passa
  const r = await p.request.post(B + '/admin/temi.php', { form: { azione: 'attiva', tema: 'prova' }, maxRedirects: 0 });
  ok('POST senza token CSRF rifiutato', r.status() >= 400 || /scelto= /.test(cli('stato')), r.status());
  ok('POST senza token: niente attivato', /scelto= /.test(cli('stato')));

  // 8. Un nome di tema inventato non passa
  const token = await p.locator('input[name=_csrf]').first().inputValue();
  await p.request.post(B + '/admin/temi.php', { form: { _csrf: token, azione: 'attiva', tema: '../includes' } });
  ok('nome di tema con ../ rifiutato', /scelto= /.test(cli('stato')));

  // 9. Il redattore non vede la voce e non apre la pagina
  cli('redattore', 'Redattore-Prova-Temi-2026');
  const rctx = await br.newContext(); const rp = await rctx.newPage();
  await rp.goto(B + '/admin/login.php'); await rp.fill('#username', 'redattore-prova'); await rp.fill('#password', 'Redattore-Prova-Temi-2026');
  await Promise.all([rp.waitForNavigation(), rp.click('button[type=submit]')]);
  ok('redattore: login', !rp.url().includes('login.php'), rp.url());
  ok('redattore: nessuna voce Temi nel menu', await rp.locator('a[href="temi.php"]').count() === 0);
  await rp.goto(B + '/admin/temi.php', { waitUntil: 'networkidle' });
  ok('redattore: la pagina Temi lo rimanda indietro', !rp.url().includes('temi.php') && (await rp.locator('.flash').innerText().catch(() => '')).includes('riservata'), rp.url());
  await rctx.close();

  ok('nessun errore JavaScript', err.length === 0, err);
  cli('disattiva'); cli('ripristina-stile');
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
