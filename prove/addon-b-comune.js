// I pezzi comuni delle prove degli addon Prenotazioni, Eventi con iscrizione e Recensioni (2.0.0).
// Contatore OK/KO, accesso al pannello, server di posta finto in Impostazioni, accensione di un addon dal pannello,
// lettura delle email arrivate (base64 compreso), riga di comando (prove/addon-b-cli.php), controllo del telefono.
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const conto = { n: 0, ko: 0 };
function ok(t, c, extra = '') {
  conto.n++; if (!c) conto.ko++;
  console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' && extra !== undefined ? ' ' + JSON.stringify(extra).slice(0, 260) : ''));
  return !!c;
}
function fine(err) {
  ok('nessun errore JavaScript', err.length === 0, err.slice(0, 5));
  console.log(`\n${conto.n - conto.ko} su ${conto.n} OK`);
  process.exitCode = conto.ko ? 1 : 0;
}
const dorme = ms => new Promise(r => setTimeout(r, ms));

/** Una pagina con la raccolta degli errori JavaScript (e degli errori in console, tranne la favicon). */
async function pagina(br, err, opz = {}) {
  const ctx = await br.newContext({ viewport: opz.telefono ? { width: 390, height: 844 } : { width: 1280, height: 900 }, ...(opz.telefono ? { isMobile: true, hasTouch: true, deviceScaleFactor: 2 } : {}) });
  const p = await ctx.newPage();
  p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('console', m => { if (m.type() === 'error' && !/favicon|ERR_ABORTED|status of 4(0[34]|10|22)/.test(m.text() + (m.location().url || ''))) err.push(m.text().slice(0, 200)); });
  p.on('dialog', d => d.accept());
  return p;
}

async function entra(p, B, utente = 'prova', password = 'Collaudo-Officina-2026') {
  await p.goto(B + '/admin/login.php', { waitUntil: 'networkidle' });
  await p.fill('#username', utente); await p.fill('#password', password);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
}

/** Il server di posta finto in Impostazioni > Configurazione email (come prova-form-avviso.js). */
async function postaFinta(p, B, porta, avvisi = 'avvisi-sito@esempio.it') {
  await p.goto(B + '/admin/impostazioni.php', { waitUntil: 'networkidle' });
  await p.fill('#smtp_host', '127.0.0.1'); await p.fill('#smtp_porta', String(porta)); await p.selectOption('#smtp_sicurezza', 'nessuna');
  await p.fill('[name=smtp_mittente]', 'sito@esempio.it').catch(() => {});
  await p.fill('[name=notification_email]', avvisi);
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=save_posta]) button[type=submit]')]);
}

/** Accende (o spegne) un addon da Impostazioni > Addon, come un amministratore. Ritorna il messaggio. */
async function addon(p, B, nome, acceso = true) {
  await p.goto(B + '/admin/addon.php', { waitUntil: 'networkidle' });
  const gia = await p.locator(`[data-addon="${nome}"].acceso`).count() > 0;
  if (gia === acceso) return 'già ' + (acceso ? 'acceso' : 'spento');
  await Promise.all([p.waitForNavigation(), p.click(`[data-addon="${nome}"] button`)]);
  return (await p.locator('.flash').first().innerText().catch(() => '')).trim();
}

/** Le email arrivate al server finto, decodificate: [{a, oggetto, testo, html, grezza}]. */
function posta(cartella) {
  const file = (fs.existsSync(cartella) ? fs.readdirSync(cartella) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b));
  return file.map(f => {
    const grezza = fs.readFileSync(path.join(cartella, f), 'utf8');
    const a = ((grezza.match(/^X-Destinatari: (.*)$/m) || [])[1] || '').trim();
    let ogg = ((grezza.match(/^Subject: (.*)$/m) || [])[1] || '').trim();
    ogg = ogg.replace(/=\?UTF-8\?B\?([^?]+)\?=/gi, (_, b) => Buffer.from(b, 'base64').toString('utf8'));
    let testo = '', html = '';
    const parti = grezza.split(/\r?\n--plume-[a-f0-9]+/);
    for (const parte of parti) {
      const m = parte.match(/Content-Type: (text\/(plain|html))[^\n]*\r?\n(?:[^\n]+\r?\n)*?\r?\n([\s\S]*)$/i);
      if (!m) continue;
      const corpo = Buffer.from(m[3].replace(/[^A-Za-z0-9+/=]/g, ''), 'base64').toString('utf8');
      if (m[2].toLowerCase() === 'html') html = corpo; else testo = corpo;
    }
    return { file: f, a, oggetto: ogg, testo, html, grezza };
  });
}
/** Aspetta che arrivino email nuove (dopo quante ce n'erano) e le ritorna. */
async function nuove(cartella, prima, quante = 1, ms = 8000) {
  for (let t = 0; t < ms; t += 200) { const l = posta(cartella); if (l.length >= prima + quante) { await dorme(300); return posta(cartella).slice(prima); } await dorme(200); }
  return posta(cartella).slice(prima);
}
/** I link di un'email HTML (href), decodificati. */
function link(email) {
  return [...(email.html || '').matchAll(/href="([^"]+)"/g)].map(m => m[1].replace(/&amp;/g, '&'));
}

function cli(cms, ...arg) {
  const out = execFileSync('php', [path.join(__dirname, 'addon-b-cli.php'), cms, ...arg], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
  try { return JSON.parse(out.trim().split('\n').pop()); } catch (e) { return out; }
}

/** Il telefono: niente scorrimento orizzontale della pagina. */
async function largo(p) {
  return p.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

/** Spunta "Sono una persona" di un modulo e aspetta il segno verde. */
async function captcha(form, p) {
  await form.locator('.captcha input[type=checkbox]').check();
  await p.waitForFunction(f => f.classList.contains('verificato'), await form.elementHandle(), { timeout: 30000 });
}

module.exports = { ok, fine, dorme, pagina, entra, postaFinta, addon, posta, nuove, link, cli, largo, captcha, conto };
