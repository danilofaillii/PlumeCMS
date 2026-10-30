// I pezzi comuni delle prove degli addon Area riservata, Corsi online e Copia del sito altrove (2.0.0):
// conteggio OK/KO, accesso al pannello, posta del server finto (prove/smtp-finto.py) letta e decodificata, addon da accendere.
const fs = require('fs');
const path = require('path');

function conta() {
  const s = { n: 0, ko: 0 };
  s.ok = (t, c, extra = '') => { s.n++; if (!c) s.ko++; console.log((c ? 'OK  ' : 'KO  ') + t + (extra !== '' ? ' ' + JSON.stringify(extra).slice(0, 260) : '')); return !!c; };
  s.fine = (err) => { s.ok('nessun errore JavaScript', err.length === 0, err); console.log(`\n${s.n - s.ko} su ${s.n} OK`); process.exitCode = s.ko ? 1 : 0; };
  return s;
}

/** Una pagina nuova (contesto suo, cookie suoi), con gli errori JavaScript raccolti in err. */
async function pagina(br, err, w = 1280, h = 900) {
  const ctx = await br.newContext({ viewport: { width: w, height: h }, acceptDownloads: true });
  const p = await ctx.newPage();
  p.on('pageerror', e => err.push(e.message.slice(0, 200)));
  p.on('console', m => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) err.push(m.text().slice(0, 200)); });
  p.on('dialog', d => d.accept());
  return p;
}

async function entraPannello(p, B, utente, password) {
  await p.goto(B + '/admin/login.php', { waitUntil: 'networkidle' });
  await p.fill('#username', utente); await p.fill('#password', password);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
}

/** Accende (o spegne) un addon da Impostazioni > Addon, come un utente. Ritorna il testo del messaggio. */
async function addon(p, B, nome, acceso = true) {
  await p.goto(B + '/admin/addon.php', { waitUntil: 'networkidle' });
  const b = p.locator(`[data-addon="${nome}"] button[type=submit]`);
  const t = (await b.innerText()).trim();
  if ((acceso && t === 'Accendi') || (!acceso && t === 'Spegni')) await Promise.all([p.waitForNavigation(), b.click()]);
  return (await p.locator('.flash').first().innerText().catch(() => '')).trim();
}

/** Il server di posta finto nelle Impostazioni (Configurazione email), con l'indirizzo degli avvisi. */
async function postaFinta(p, B, porta, avvisi = 'avvisi-sito@esempio.it') {
  await p.goto(B + '/admin/impostazioni.php', { waitUntil: 'networkidle' });
  await p.fill('#smtp_host', '127.0.0.1'); await p.fill('#smtp_porta', String(porta)); await p.selectOption('#smtp_sicurezza', 'nessuna');
  await p.fill('[name=smtp_mittente]', 'sito@esempio.it').catch(() => {});
  await p.fill('[name=notification_email]', avvisi);
  await Promise.all([p.waitForNavigation(), p.click('form:has(input[value=save_posta]) button[type=submit]')]);
}

function leggi(eml) {
  const i = eml.search(/\r?\n\r?\n/);
  const testa = eml.slice(0, i).replace(/\r?\n[ \t]+/g, ' '); const corpo = eml.slice(i).replace(/^\r?\n\r?\n/, '');
  const h = (t, nome) => ((t.match(new RegExp('^' + nome + ':[ \\t]*(.*)$', 'mi')) || [])[1] || '').trim();
  const dec = s => s.replace(/=\?UTF-8\?B\?([^?]+)\?=/gi, (_, b) => Buffer.from(b, 'base64').toString('utf8'));
  const parte = (t, c) => /base64/i.test(h(t, 'Content-Transfer-Encoding')) ? Buffer.from(c.replace(/\s+/g, ''), 'base64').toString('utf8') : c;
  const ct = h(testa, 'Content-Type');
  let html = '', testo = '';
  const b = (ct.match(/boundary="([^"]+)"/) || [])[1];
  if (b) {
    for (const pz of corpo.split('--' + b).slice(1)) {
      const j = pz.search(/\r?\n\r?\n/); if (j < 0) continue;
      const pt = pz.slice(0, j).trim(); const pc = pz.slice(j).trim();
      if (/text\/plain/i.test(h(pt, 'Content-Type'))) testo = parte(pt, pc); else if (/text\/html/i.test(h(pt, 'Content-Type'))) html = parte(pt, pc);
    }
  } else if (/text\/plain/i.test(ct)) testo = parte(testa, corpo); else html = parte(testa, corpo);
  return { dest: h(testa, 'X-Destinatari') || h(testa, 'To'), oggetto: dec(h(testa, 'Subject')), html, testo };
}

function posta(dir) {
  return (fs.existsSync(dir) ? fs.readdirSync(dir) : []).filter(f => f.endsWith('.eml')).sort((a, b) => parseInt(a) - parseInt(b)).map(f => leggi(fs.readFileSync(path.join(dir, f), 'utf8')));
}

/** Aspetta (fino a 10 secondi) un'email nuova per chi, dopo le prime "da" email. */
async function aspettaEmail(dir, chi, da, filtro = () => true) {
  for (let i = 0; i < 50; i++) {
    const m = posta(dir).slice(da).filter(x => x.dest.includes(chi) && filtro(x));
    if (m.length) return m[m.length - 1];
    await new Promise(r => setTimeout(r, 200));
  }
  return null;
}

/** Il primo link dell'email che contiene il pezzo dato (per esempio "/conferma-iscrizione/"). */
function linkDa(m, pezzo) {
  const t = ((m && (m.html + ' ' + m.testo)) || '').replace(/&amp;/g, '&');
  const r = new RegExp('https?://[^\\s"<>]*' + pezzo.replace(/[.*+?^${}()|[\]\\/]/g, '\\$&') + '[^\\s"<>]*');
  return (t.match(r) || [''])[0];
}

/** La pagina scorre di lato? (telefono) */
async function largo(p) { return p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth); }

module.exports = { conta, pagina, entraPannello, addon, postaFinta, posta, aspettaEmail, linkDa, largo };
