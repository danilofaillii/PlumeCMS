// Prove del motore dei temi (fase 2, 27 ottobre 2026): anteprima, attivazione, PHP del tema, ritorno all'aspetto di base.
// Servono i temi di collaudo prova, rotto, fatale, mortale in <cartella CMS>/temi (vedi LEGGIMI.md) e prove/tema-cli.php.
// uso: node prova-temi.js <base, es. http://127.0.0.1:8099> <cartella CMS installato> <utente> <password>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const [B, DIR, UTENTE, PASSWORD] = process.argv.slice(2);
const cli = (...a) => execFileSync('php', [path.join(__dirname, 'tema-cli.php'), DIR, ...a], { encoding: 'utf8' });
let n = 0, ko = 0; const ok = (t, c, extra = '') => { n++; if (!c) ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra ? ' ' + JSON.stringify(extra).slice(0, 220) : '')); };
const sfondo = (p) => p.evaluate(() => getComputedStyle(document.body).backgroundColor);
const varCss = (p, v) => p.evaluate((v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim(), v);
(async () => {
  cli('disattiva'); cli('ripristina-stile');
  const br = await chromium.launch();
  const anonimo = await (await br.newContext()).newPage();
  const ctx = await br.newContext(); const p = await ctx.newPage();
  const err = []; p.on('pageerror', e => err.push(e.message.slice(0, 200)));

  // 1. Senza tema: aspetto di base
  await anonimo.goto(B + '/', { waitUntil: 'networkidle' });
  const sfondoBase = await sfondo(anonimo);
  ok('senza tema: sfondo scuro di base', sfondoBase === 'rgb(6, 10, 23)', sfondoBase);
  ok('senza tema: nessun CSS di tema', await anonimo.locator('#tema-css').count() === 0);

  // 2. Anteprima: solo per l'amministratore, senza attivare
  await p.goto(B + '/admin/login.php'); await p.fill('#username', UTENTE); await p.fill('#password', PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok('login nel pannello', !p.url().includes('login.php'), p.url());
  await p.goto(B + '/?preview=1&tema=prova', { waitUntil: 'networkidle' });
  ok('anteprima: header del tema', (await p.locator('header').innerText()).includes('Questo header viene dal tema Prova'));
  ok('anteprima: sfondo bianco dello stile del tema', await sfondo(p) === 'rgb(255, 255, 255)', await sfondo(p));
  ok('anteprima: CSS del tema caricato e vince su sito.css', await varCss(p, '--primario-chiaro') === '#ff8a00', await varCss(p, '--primario-chiaro'));
  await p.goto(B + '/non-esiste/?preview=1&tema=prova', { waitUntil: 'networkidle' });
  ok('anteprima: vista 404 del tema', (await p.locator('main').innerText()).includes('404 del tema Prova'));
  // Un visitatore che scrive lo stesso indirizzo vede la pagina normale: stesso sfondo, stesso header, nessun CSS di tema.
  await anonimo.goto(B + '/', { waitUntil: 'networkidle' });
  const headerNormale = await anonimo.locator('header').innerText();
  await anonimo.goto(B + '/?preview=1&tema=prova', { waitUntil: 'networkidle' });
  ok('anteprima chiesta da un visitatore: ignorata', await sfondo(anonimo) === sfondoBase && await anonimo.locator('header').innerText() === headerNormale && await anonimo.locator('#tema-css').count() === 0);
  await p.goto(B + '/', { waitUntil: 'networkidle' });
  ok('dopo l\'anteprima il sito dell\'amministratore resta di base', await sfondo(p) === sfondoBase);
  ok('anteprima: niente salvato', /scelto= attivo=/.test(cli('stato')) && /primario=#a855f7/.test(cli('stato')), cli('stato'));

  // 3. Attivazione
  const att = cli('attiva', 'prova');
  ok('attivazione: resoconto', att.includes('Tema attivo: Prova'), att);
  await anonimo.goto(B + '/', { waitUntil: 'networkidle' });
  ok('attivo: header del tema per tutti', (await anonimo.locator('header').innerText()).includes('Questo header viene dal tema Prova'));
  ok('attivo: sfondo bianco', await sfondo(anonimo) === 'rgb(255, 255, 255)');
  ok('attivo: CSS del tema', await anonimo.locator('#tema-css').count() === 1 && await varCss(anonimo, '--primario-chiaro') === '#ff8a00');
  const st = cli('stato');
  ok('attivo: il nome del sito non lo cambia il tema', st.includes('nome del sito=Bottega di prova'), st);
  const llms = await (await anonimo.request.get(B + '/llms.txt')).text();
  ok('attivo: filtro PHP del tema (llms.txt)', llms.includes('Riga aggiunta dal tema Prova'));
  const r404 = await anonimo.goto(B + '/non-esiste/', { waitUntil: 'networkidle' });
  ok('attivo: 404 del tema, codice 404', r404.status() === 404 && (await anonimo.locator('main').innerText()).includes('404 del tema Prova'), r404.status());
  const modelli1 = (cli('stato').match(/modelli nel database=(\d+)/) || [])[1];
  cli('attiva', 'prova');
  const modelli2 = (cli('stato').match(/modelli nel database=(\d+)/) || [])[1];
  ok('riattivare lo stesso tema non duplica header e footer', modelli1 === modelli2 && +modelli1 >= 4, [modelli1, modelli2]);
  const php = await anonimo.request.get(B + '/temi/prova/funzioni.php');
  const corpo = await php.text();
  ok('il PHP del tema non si legge come testo dal browser', !corpo.includes('llms_intestazione'), php.status());

  // 3b. Infografiche: i colori con un nome li dà il tema, quelli liberi restano
  const ig = async (pag) => pag.evaluate(() => {
    const [a, b] = document.querySelectorAll('.igx');
    const cs = (e) => getComputedStyle(e);
    return { rosso: cs(document.documentElement).getPropertyValue('--ig-rosso').trim(), sfondoCorpo: cs(a.querySelector('.igx-corpo')).backgroundColor,
      testataA: cs(a.querySelector('.igx-testa')).backgroundImage, testataB: cs(b.querySelector('.igx-testa')).backgroundImage,
      voceB: cs(b.querySelector('.igx-num')).backgroundColor, mattone: cs(document.documentElement).getPropertyValue('--ig-mattone').trim(),
      sbagliata: cs(document.documentElement).getPropertyValue('--ig-sbagliata').trim() };
  });
  await anonimo.goto(B + '/infografiche-tema/', { waitUntil: 'networkidle' });
  const igTema = await ig(anonimo);
  ok('infografiche con il tema: il rosso è quello del tema', igTema.rosso === '#dc2626', igTema);
  ok('infografiche con il tema: corpo della card del tema', igTema.sfondoCorpo === 'rgb(248, 250, 252)', igTema.sfondoCorpo);
  ok('infografiche con il tema: testata scura del tema (non più marrone)', igTema.testataA.includes('rgb(30, 41, 59)') && !igTema.testataA.includes('rgb(45, 27, 14)'), igTema.testataA);
  ok('infografiche con il tema: i colori liberi restano', igTema.testataB.includes('rgb(18, 52, 86)') && igTema.voceB === 'rgb(101, 67, 33)', [igTema.testataB, igTema.voceB]);
  ok('infografiche con il tema: tinta scritta a mano dal tema', igTema.mattone === '#b45309', igTema.mattone);
  ok('infografiche con il tema: un valore che non è un colore viene scartato', igTema.sbagliata === '', igTema.sbagliata);

  // 4. Pannello: con un tema attivo l'editor carica il CSS del tema, mai il suo PHP
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('pannello aperto con il tema attivo', p.url().includes('/admin/index.php') && !(await p.content()).includes('Fatal error'));

  // 5. Una vista che lancia un errore: si usa quella del CMS e il tema si segna come guasto
  cli('attiva', 'rotto');
  const r1 = await anonimo.goto(B + '/non-esiste/', { waitUntil: 'networkidle' });
  ok('tema rotto: la 404 del CMS al posto di quella del tema', r1.status() === 404 && /errore 404/i.test(await anonimo.locator('main').innerText()), r1.status());
  ok('tema rotto: segnato come guasto', /guasto=\{"slug":"rotto"/.test(cli('stato')), cli('stato'));

  // 6. Errore di sintassi in funzioni.php: preso, il sito risponde subito
  cli('attiva', 'fatale');
  const r2 = await anonimo.goto(B + '/', { waitUntil: 'networkidle' });
  ok('errore di sintassi nel tema: la home risponde 200', r2.status() === 200 && (await anonimo.locator('main').count()) === 1, r2.status());
  ok('errore di sintassi: tema guasto', /guasto=\{"slug":"fatale"/.test(cli('stato')));

  // 7. Errore fatale che try/catch non prende: una richiesta va persa, poi il sito torna all'aspetto di base
  cli('attiva', 'mortale');
  const r3 = await anonimo.goto(B + '/', { waitUntil: 'load' }).catch(e => null);
  ok('errore fatale: la prima richiesta fallisce (atteso)', !r3 || r3.status() >= 500 || !(await anonimo.content()).includes('<main'), r3 && r3.status());
  ok('errore fatale: tema segnato come guasto', /guasto=\{"slug":"mortale"/.test(cli('stato')), cli('stato'));
  const r4 = await anonimo.goto(B + '/', { waitUntil: 'networkidle' });
  ok('errore fatale: dalla richiesta dopo il sito torna a rispondere', r4.status() === 200 && (await anonimo.locator('main').count()) === 1, r4.status());
  await p.goto(B + '/admin/index.php', { waitUntil: 'networkidle' });
  ok('errore fatale: il pannello non ne risente', p.url().includes('/admin/index.php') && !(await p.content()).includes('Fatal error'));
  await p.goto(B + '/admin/editor.php?tipo=pagine&id=1', { waitUntil: 'networkidle' });
  ok('errore fatale: l\'editor si apre', (await p.locator('#ed-tela, iframe').count()) > 0);

  // 8. Riattivare un tema guasto toglie il segno; disattivare torna alla base
  cli('attiva', 'prova');
  ok('riattivato: niente più guasto', /guasto=null/.test(cli('stato')), cli('stato'));
  cli('disattiva'); cli('ripristina-stile');
  await anonimo.goto(B + '/', { waitUntil: 'networkidle' });
  ok('disattivato e stile ripristinato: sfondo di base', await sfondo(anonimo) === sfondoBase);
  await anonimo.goto(B + '/infografiche-tema/', { waitUntil: 'networkidle' });
  const igBase = await ig(anonimo);
  ok('infografiche senza tema: Carta (rosso, corpo crema, testata marrone)', igBase.rosso === '#e8553e' && igBase.sfondoCorpo === 'rgb(254, 249, 240)' && igBase.testataA.includes('rgb(45, 27, 14)'), igBase);
  ok('infografiche senza tema: i colori liberi sono gli stessi', igBase.testataB === igTema.testataB && igBase.voceB === igTema.voceB);
  ok('nessun errore JavaScript nelle pagine', err.length === 0, err);
  await br.close();
  console.log(`\n${n - ko} su ${n} OK`);
})();
