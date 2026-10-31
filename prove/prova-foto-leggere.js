// Foto più leggere da sole (1.4.0), come un utente: carica dalla pagina Media una foto grande scattata "di lato" con la
// posizione GPS nei metadati e controlla che arrivi dritta, rimpicciolita a 2560 px, senza metadati e con la WebP accanto;
// una PNG piccola resta delle sue misure; la libreria non mostra le WebP; la pagina del sito manda la WebP a un browser
// che la accetta (src e sfondo della sezione) e l'originale a uno che non la accetta, con Vary: Accept; il lato massimo
// cambiato a 1200; spento, la foto resta come arriva e il sito torna agli originali; i valori sbagliati e il redattore
// respinti; il caricamento della newsletter senza WebP; cancellare la foto toglie anche la sua WebP.
// uso: node prove/prova-foto-leggere.js <base> <cartella CMS installato> <utente> <password> <cartella foto>
// Servono: php prove/foto-prova.php <cartella foto> (crea le foto) e prove/tema-cli.php (crea il redattore di prova).
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs'), path = require('path');
const [B, CMS, U, P, F] = process.argv.slice(2);
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 400) : '')); };
const php = (...a) => execFileSync('php', a, { encoding: 'utf8' }).trim();
// Misure, colore di un punto e presenza dell'EXIF di un file sul disco del sito, letti con PHP
const esamina = (file, x, y) => JSON.parse(php('-r', `$f=$argv[1];$i=getimagesize($f);$im=imagecreatefromstring(file_get_contents($f));$c=imagecolorsforindex($im,imagecolorat($im,(int)($i[0]*$argv[2]),(int)($i[1]*$argv[3])));$d=file_get_contents($f);echo json_encode(['l'=>$i[0],'a'=>$i[1],'tipo'=>$i['mime'],'r'=>$c['red'],'g'=>$c['green'],'b'=>$c['blue'],'exif'=>str_contains($d,"Exif\\0\\0"),'gps'=>str_contains($d,'PROVA-GPS'),'peso'=>filesize($f)]);`, file, String(x), String(y)));
const disco = (url) => path.join(CMS, url.replace(/^.*?\/uploads\//, 'uploads/'));

(async () => {
  for (const f of ['grande-girata.jpg', 'grande.jpg', 'piccola.png']) if (!fs.existsSync(path.join(F, f))) { console.log('manca ' + f + ': lancia prima php prove/foto-prova.php ' + F); process.exit(2); }
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message));
  await p.goto(B + '/admin/login.php'); await p.fill('#username', U); await p.fill('#password', P);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  const impostazioni = async (acceso, lato) => {
    await p.goto(B + '/admin/media.php?foto=1');
    if (acceso) await p.check('input[name=foto_leggere]'); else await p.uncheck('input[name=foto_leggere]');
    // il campo ha min e max: per provare il controllo del server si tolgono (come farebbe chi scrive la richiesta a mano)
    await p.$eval('input[name=foto_lato_max]', i => { i.removeAttribute('min'); i.removeAttribute('max'); });
    await p.fill('input[name=foto_lato_max]', String(lato));
    await Promise.all([p.waitForNavigation(), p.click('#foto-leggere button:has-text("Salva")')]);
    return p.locator('.flash').first().innerText().catch(() => '');
  };
  const carica = async (file) => {
    await p.goto(B + '/admin/media.php');
    await p.setInputFiles('input[type=file][name=file]', path.join(F, file));
    await Promise.all([p.waitForNavigation(), p.click('button:has-text("Carica in uploads/pagine")')]);
    const t = await p.locator('.flash-success').innerText().catch(() => '');
    const m = t.match(/Caricata: (\S+)/);
    return { url: m ? m[1] : '', testo: t };
  };

  // 1. Acceso, 2560: la foto grande girata
  let msg = await impostazioni(true, 2560);
  ok('Media: impostazioni salvate (acceso, 2560)', /acceso, lato massimo 2560/.test(msg), msg);
  ok('Media: il riquadro dice con che cosa lavora (GD) e che la WebP c\'e', /Lavora con: GD, WebP disponibile/.test(await p.locator('#foto-leggere').textContent()));
  await p.screenshot({ path: path.join(F, 'media-foto-leggere.png') });
  const g = await carica('grande-girata.jpg');
  ok('caricata la foto grande, il messaggio parla della WebP', g.url !== '' && /WebP accanto/.test(g.testo), g.testo);
  const e1 = esamina(disco(g.url), 0.9, 0.1);
  ok(`girata dritta (era 4000x3000 di lato, ora in piedi): ${e1.l}x${e1.a}`, e1.a > e1.l);
  ok('lato lungo a 2560, proporzioni tenute (1920x2560)', e1.a === 2560 && e1.l === 1920, e1);
  ok('il quadrante rosso e finito in alto a destra (orientamento 6 applicato ai pixel)', e1.r > 180 && e1.g < 80 && e1.b < 80, e1);
  ok('via i metadati: niente EXIF, niente GPS', !e1.exif && !e1.gps, e1);
  ok('piu leggera dell\'originale', e1.peso < fs.statSync(path.join(F, 'grande-girata.jpg')).size, [e1.peso]);
  const w1 = disco(g.url) + '.webp';
  ok('la WebP accanto esiste ed e piu leggera del JPEG', fs.existsSync(w1) && fs.statSync(w1).size < e1.peso, fs.existsSync(w1) ? fs.statSync(w1).size : 'manca');
  const rw = await p.request.get(B + g.url + '.webp');
  ok('la WebP si scarica dal sito (200, image/webp)', rw.status() === 200 && /image\/webp/.test(rw.headers()['content-type'] || ''), [rw.status(), rw.headers()['content-type']]);
  const ro = await p.request.get(B + g.url);
  ok('l\'indirizzo originale funziona sempre (200, image/jpeg)', ro.status() === 200 && /image\/jpeg/.test(ro.headers()['content-type'] || ''));

  // 2. La libreria non mostra la WebP come foto a parte
  await p.goto(B + '/admin/media.php?q=' + encodeURIComponent(path.basename(g.url)));
  const nomi = await p.locator('div[title]').allInnerTexts().catch(() => []);
  ok('la libreria mostra la foto e non la sua WebP', (await p.content()).includes(path.basename(g.url)) && !(await p.content()).includes(path.basename(g.url) + '.webp</div>'), nomi.slice(0, 4));

  // 3. La PNG piccola: misure uguali
  const pk = await carica('piccola.png');
  const e2 = esamina(disco(pk.url), 0.5, 0.5);
  ok('PNG piccola: misure invariate (600x400) e trasparenza tenuta', e2.l === 600 && e2.a === 400 && e2.tipo === 'image/png', e2);

  // 4. Il sito: WebP a chi la accetta, originale agli altri
  const id = php(path.join(__dirname, 'pagina-foto.php'), CMS, g.url);
  ok('pagina di prova con la foto (immagine e sfondo di sezione)', /^\d+$/.test(id), id);
  const vis = await br.newContext({ viewport: { width: 1280, height: 900 } }); const v = await vis.newPage();
  const rv = await v.goto(B + '/prova-foto/', { waitUntil: 'networkidle' });
  ok('la pagina dice Vary: Accept', /accept/i.test(rv.headers()['vary'] || ''), rv.headers()['vary']);
  const src = await v.$eval('.ed-immagine img', i => [i.currentSrc, i.naturalWidth]);
  ok('Chromium (accetta WebP): l\'immagine arriva in WebP e si vede', src[0].endsWith('.jpg.webp') && src[1] === 1920, src);
  const sfondo = await v.evaluate(() => [...document.querySelectorAll('style')].map(s => s.textContent).join('').match(/url\([^)]*prova-foto[^)]*\)|url\([^)]*uploads[^)]*\)/g) || []);
  ok('anche lo sfondo della sezione punta alla WebP', sfondo.length > 0 && sfondo.every(u => u.includes('.webp')), sfondo);
  const og = await v.$eval('meta[property="og:image"]', m => m.content).catch(() => '');
  ok('og:image e i link non cambiano (fuori dal sito si legge l\'originale)', !og.endsWith('.webp'), og);
  await v.screenshot({ path: path.join(F, 'sito-webp.png') });
  // Un browser che non dichiara la WebP (come Safari nella richiesta della pagina): l'intestazione Accept si cambia sulla richiesta
  await v.route('**/prova-foto/', r => r.continue({ headers: { ...r.request().headers(), accept: 'text/html,application/xhtml+xml,*/*;q=0.8' } }));
  await v.goto(B + '/prova-foto/', { waitUntil: 'networkidle' });
  const src2 = await v.$eval('.ed-immagine img', i => [i.getAttribute('src'), i.naturalWidth]);
  ok('browser che non dichiara la WebP: riceve il JPEG', src2[0].endsWith('.jpg') && src2[1] === 1920, src2);
  await v.unroute('**/prova-foto/');

  // 5. Lato massimo 1200
  msg = await impostazioni(true, 1200);
  const g2 = await carica('grande.jpg');
  const e3 = esamina(disco(g2.url), 0.1, 0.1);
  ok('lato massimo 1200: la foto 3600x2400 diventa 1200x800', e3.l === 1200 && e3.a === 800, e3);

  // 6. Valori non ammessi
  msg = await impostazioni(true, 50);
  ok('lato 50 rifiutato con un messaggio', /da 800 a 8000/.test(msg), msg);
  await p.goto(B + '/admin/media.php?foto=1');
  ok('dopo il rifiuto resta 1200', await p.inputValue('input[name=foto_lato_max]') === '1200');

  // 7. Spento: la foto resta delle sue misure, niente WebP, il sito serve l'originale
  msg = await impostazioni(false, 2560);
  ok('spento: messaggio', /spento/.test(msg), msg);
  const g3 = await carica('grande.jpg');
  const e4 = esamina(disco(g3.url), 0.1, 0.1);
  ok('spento: misure invariate (3600x2400) e nessuna WebP', e4.l === 3600 && e4.a === 2400 && !fs.existsSync(disco(g3.url) + '.webp'), e4);
  ok('spento: i metadati si tolgono lo stesso (come prima della 1.4.0)', !e4.exif);
  await v.goto(B + '/prova-foto/', { waitUntil: 'networkidle' });
  ok('spento: il sito torna a servire il JPEG', (await v.$eval('.ed-immagine img', i => i.getAttribute('src'))).endsWith('.jpg'));
  msg = await impostazioni(true, 2560);

  // 8. Newsletter: rimpicciolita, senza WebP
  const csrf = await p.evaluate(() => (document.querySelector('input[name=_csrf]') || {}).value || '');
  const nl = await p.request.post(B + '/admin/newsletter-immagine.php', { multipart: { _csrf: csrf, immagine: { name: 'grande.jpg', mimeType: 'image/jpeg', buffer: fs.readFileSync(path.join(F, 'grande.jpg')) } }, headers: { Origin: B, Referer: B + '/admin/media.php' } });
  const nlj = await nl.json().catch(() => ({}));
  const nlf = nlj.url ? disco(nlj.url) : '';
  const e5 = nlf ? esamina(nlf, 0.1, 0.1) : {};
  ok('newsletter: rimpicciolita a 2560 e senza WebP (la posta non la mostra)', e5.l === 2560 && !fs.existsSync(nlf + '.webp'), [nlj, e5]);

  // 9. Il redattore non cambia le impostazioni
  php(path.join(__dirname, 'tema-cli.php'), CMS, 'redattore', 'Redattore-Prova-2026');
  const rc = await br.newContext(); const r = await rc.newPage();
  await r.goto(B + '/admin/login.php'); await r.fill('#username', 'redattore-prova'); await r.fill('#password', 'Redattore-Prova-2026');
  await Promise.all([r.waitForNavigation(), r.click('button[type=submit]')]);
  await r.goto(B + '/admin/media.php');
  ok('redattore: la pagina Media non mostra il riquadro delle impostazioni', await r.locator('#foto-leggere').count() === 0);
  const rcsrf = await r.evaluate(() => document.querySelector('input[name=_csrf]').value);
  const rr = await r.request.post(B + '/admin/media.php', { form: { _csrf: rcsrf, azione: 'foto_impostazioni', foto_leggere: '0', foto_lato_max: '900' }, headers: { Origin: B, Referer: B + '/admin/media.php' } });
  ok('redattore: il POST delle impostazioni e respinto (403)', rr.status() === 403, rr.status());
  await rc.close();

  // 10. Cancellare la foto toglie anche la sua WebP
  await p.goto(B + '/admin/media.php?q=' + encodeURIComponent(path.basename(g.url)));
  p.once('dialog', d => d.accept());
  const bott = p.locator(`form:has(input[name=elimina][value="${g.url}"]) button, button[name=elimina][value="${g.url}"]`).first();
  if (await bott.count()) { await Promise.all([p.waitForNavigation(), bott.click()]); }
  ok('cancellata: via la foto e la sua WebP', !fs.existsSync(disco(g.url)) && !fs.existsSync(w1));

  ok('nessun errore JavaScript nel pannello', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
  process.exit(ko ? 1 : 0);
})();
