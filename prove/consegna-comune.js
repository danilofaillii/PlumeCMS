// Pezzi comuni di prova-consegna-email.js e attacchi-consegna.js (2.1.0). Mai nel prodotto.
//  - prepara(): il DNS finto, il certificato con cui le prove firmano gli avvisi di Amazon SNS, le costanti delle prove nel
//    config.local.php del CMS (CMS_POSTA_API_PROVA, CMS_DNS_PROVA, CMS_SNS_CERT_PROVA) e il server finto delle API;
//  - cli(): prove/consegna-cli.php sul CMS installato;
//  - sns(): un messaggio di Amazon SNS firmato con la chiave delle prove.
const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');
const { execFileSync, spawn } = require('child_process');

const CHIAVI = {
  brevo: 'xkeysib-' + 'a1b2c3d4'.repeat(8) + '-ProvaBrevo123456',
  sesId: 'AKIAPROVAPROVA123456',
  sesSegreto: 'Prova/Segreta+Amazon' + 'X'.repeat(20),
  mailgun: 'key-' + 'f0e1d2c3'.repeat(4),
  mailgunFirma: 'b7c6d5e4'.repeat(4),
};

// Una chiave DKIM finta lunga come una vera da 2048 bit (392 caratteri), e una corta come una da 1024.
const P2048 = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA' + 'A'.repeat(348);
const P1024 = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQ' + 'B'.repeat(178);
const DNS = {
  'buono.it': { NS: ['ns1.buono.it'], TXT: ['v=spf1 +a +mx include:spf.brevo.com include:amazonses.com include:mailgun.org ~all', 'google-site-verification=abc'], MX: ['10 mail.buono.it'] },
  'default._domainkey.buono.it': { TXT: ['v=DKIM1; k=rsa; p=' + P2048] },
  '_dmarc.buono.it': { TXT: ['v=DMARC1; p=none; rua=mailto:dmarc@buono.it'] },
  'vuoto.it': { NS: ['ns1.vuoto.it'] },
  'doppio.it': { NS: ['ns1.doppio.it'], TXT: ['v=spf1 include:_spf.google.com ~all', 'v=spf1 a mx -all'], MX: ['10 mx.doppio.it'] },
  'aperto.it': { NS: ['ns1.aperto.it'], TXT: ['v=spf1 a mx +all'], MX: ['10 mx.aperto.it'] },
  'sel.it': { NS: ['ns1.sel.it'], TXT: ['v=spf1 mx ~all'], MX: ['10 mx.sel.it'] },
  'abc123._domainkey.sel.it': { TXT: ['v=DKIM1; k=rsa; p=' + P2048] },
  '_dmarc.sel.it': { TXT: ['v=DMARC1; p=reject'] },
  'solo.it': { NS: ['ns1.solo.it'], TXT: ['v=spf1 +a +mx ~all'], MX: ['10 mx.solo.it'] },
  'default._domainkey.solo.it': { TXT: ['v=DKIM1; k=rsa; p=' + P1024] },
  '_dmarc.solo.it': { TXT: ['v=DMARC1; p=quarantine'] },
  'gmail.com': { NS: ['ns1.google.com'], TXT: ['v=spf1 redirect=_spf.google.com'], MX: ['5 gmail-smtp-in.l.google.com'] },
  '_dmarc.gmail.com': { TXT: ['v=DMARC1; p=none; sp=quarantine'] },
};

function prepara(DIR, portaApi) {
  const T = path.join(os.tmpdir(), 'plume-consegna-' + portaApi);
  fs.rmSync(T, { recursive: true, force: true });
  fs.mkdirSync(path.join(T, 'api'), { recursive: true });
  const dns = path.join(T, 'dns.json');
  fs.writeFileSync(dns, JSON.stringify(DNS));
  execFileSync('openssl', ['req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-keyout', path.join(T, 'sns-chiave.pem'), '-out', path.join(T, 'sns-cert.pem'), '-days', '2', '-subj', '/CN=sns.prova'], { stdio: 'ignore' });
  const cfg = path.join(DIR, 'includes', 'config.local.php');
  let s = fs.readFileSync(cfg, 'utf8');
  s = s.replace(/\n\/\/ prove della consegna: inizio[\s\S]*?\/\/ prove della consegna: fine\n/, '\n');
  s += `\n// prove della consegna: inizio\ndefine('CMS_POSTA_API_PROVA', 'http://127.0.0.1:${portaApi}');\ndefine('CMS_DNS_PROVA', '${dns}');\ndefine('CMS_SNS_CERT_PROVA', '${path.join(T, 'sns-cert.pem')}');\n// prove della consegna: fine\n`;
  fs.writeFileSync(cfg, s);
  const server = spawn('python3', ['-I', path.join(__dirname, 'api-posta-finta.py'), String(portaApi), path.join(T, 'api'), CHIAVI.brevo, CHIAVI.sesId, CHIAVI.sesSegreto, CHIAVI.mailgun], { stdio: 'ignore', detached: true });
  return { T, dns, server, api: path.join(T, 'api') };
}

/** Toglie le costanti delle prove dal config (o le rimette senza il certificato SNS, per provare il controllo del suo indirizzo). */
function costanti(DIR, T, portaApi, conCert = true) {
  const cfg = path.join(DIR, 'includes', 'config.local.php');
  let s = fs.readFileSync(cfg, 'utf8');
  s = s.replace(/\n\/\/ prove della consegna: inizio[\s\S]*?\/\/ prove della consegna: fine\n/, '\n');
  if (T) s += `\n// prove della consegna: inizio\ndefine('CMS_POSTA_API_PROVA', 'http://127.0.0.1:${portaApi}');\ndefine('CMS_DNS_PROVA', '${path.join(T, 'dns.json')}');\n` + (conCert ? `define('CMS_SNS_CERT_PROVA', '${path.join(T, 'sns-cert.pem')}');\n` : '') + '// prove della consegna: fine\n';
  fs.writeFileSync(cfg, s);
}

function fine(server) {
  try { process.kill(server.pid); } catch (e) {}
}

function cli(DIR, ...a) {
  try {
    return JSON.parse(execFileSync('php', [path.join(__dirname, 'consegna-cli.php'), DIR, ...a.map(String)], { stdio: ['ignore', 'pipe', 'pipe'] }).toString().trim().split('\n').pop());
  } catch (e) {
    return { errore: String(e.stderr || e.message).slice(0, 400) };
  }
}

/** I messaggi arrivati al server finto delle API, in ordine. */
function arrivati(dir) {
  return (fs.existsSync(dir) ? fs.readdirSync(dir) : []).filter(f => f.endsWith('.json')).sort((a, b) => parseInt(a) - parseInt(b))
    .map(f => ({ file: f, servizio: f.replace(/^\d+-|\.json$/g, ''), ...JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8')) }));
}

/** Un messaggio di Amazon SNS firmato come lo firma Amazon (SignatureVersion 1, SHA1 con RSA). */
function sns(T, campi, chiave = null) {
  const m = { SignatureVersion: '1', SigningCertURL: 'https://sns.eu-west-1.amazonaws.com/SimpleNotificationService-prova.pem', Timestamp: new Date().toISOString(), ...campi };
  const nomi = m.Type === 'Notification' ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'] : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
  let s = '';
  for (const k of nomi) if (typeof m[k] === 'string') s += `${k}\n${m[k]}\n`;
  m.Signature = crypto.createSign('RSA-SHA1').update(s).sign(chiave || fs.readFileSync(path.join(T, 'sns-chiave.pem')), 'base64');
  return m;
}

/** La firma di un avviso di Mailgun. */
function mailgunFirma(chiave, ts = Math.floor(Date.now() / 1000)) {
  const token = crypto.randomBytes(25).toString('hex');
  return { timestamp: String(ts), token, signature: crypto.createHmac('sha256', chiave).update(String(ts) + token).digest('hex') };
}

async function posta(url, dati) {
  const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: typeof dati === 'string' ? dati : JSON.stringify(dati) });
  let j = null; try { j = await r.json(); } catch (e) {}
  return { status: r.status, j };
}

async function entra(p, B, utente, password) {
  await p.goto(B + '/admin/login.php', { waitUntil: 'networkidle' });
  await p.fill('#username', utente); await p.fill('#password', password);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
}

module.exports = { CHIAVI, DNS, prepara, costanti, fine, cli, arrivati, sns, mailgunFirma, posta, entra };
