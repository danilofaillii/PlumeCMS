<?php
/**
 * La firma in fondo al sito (1.8.0, richiesta di Danilo): una riga sotto il piede, "Un nuovo sito plumeCMS", con il
 * link al sito ufficiale. Chi installa la cambia o la spegne in Impostazioni > Sito pubblico: testo, indirizzo, acceso.
 * Impostazione firma_sito (JSON). Senza impostazione: accesa, con il testo e l'indirizzo di partenza.
 * La esce vista_pagina() (sito/viste.php) dopo il piede, anche con un tema che ridisegna la pagina (variabile piede).
 */
// Lo stesso indirizzo di VERSIONE_SITO_UFFICIALE (includes/versione-nuova.php), scritto qui per non caricare il motore
// degli aggiornamenti a ogni visita del sito. Se cambia uno, cambia anche l'altro.
const FIRMA_URL = 'https://plumecms.danilofailli.it';
const FIRMA_IMPOSTAZIONE = 'firma_sito';
const FIRMA_TESTO = 'Un nuovo sito plumeCMS';

function firma_predefinita(): array {
    return ['mostra' => true, 'testo' => FIRMA_TESTO, 'url' => FIRMA_URL];
}

/** La firma com'è adesso: sempre le tre chiavi, ripulite. */
function firma_valori(): array {
    $j = json_decode((string)get_setting(FIRMA_IMPOSTAZIONE, ''), true);
    return is_array($j) ? firma_pulisci($j) : firma_predefinita();
}

/** Testo fino a 120 caratteri senza HTML ed emoji; indirizzo solo http o https (altrimenti quello ufficiale). */
function firma_pulisci(array $p): array {
    $testo = is_scalar($p['testo'] ?? null) ? (string)$p['testo'] : '';
    $testo = mb_substr(trim((string)preg_replace(['/[\x{10000}-\x{10FFFF}]/u', '/\s+/u'], ['', ' '], strip_tags($testo))), 0, 120);
    $url = is_scalar($p['url'] ?? null) ? trim((string)$p['url']) : '';
    if ($url !== '' && (!preg_match('~^https?://[^\s"<>]+$~i', $url) || !filter_var($url, FILTER_VALIDATE_URL))) $url = FIRMA_URL;
    return ['mostra' => !empty($p['mostra']), 'testo' => $testo, 'url' => $url];
}

function firma_salva(array $p): array {
    $f = firma_pulisci($p);
    save_setting(FIRMA_IMPOSTAZIONE, json_encode($f, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $f;
}

/** L'HTML della firma per il sito, o '' se spenta o senza testo. Senza indirizzo, solo il testo. */
function firma_html(): string {
    try { $f = firma_valori(); } catch (Throwable $e) { $f = firma_predefinita(); }
    if (!$f['mostra'] || $f['testo'] === '') return '';
    $t = htmlspecialchars($f['testo'], ENT_QUOTES, 'UTF-8');
    $dentro = $f['url'] !== '' ? '<a href="' . htmlspecialchars($f['url'], ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . $t . '</a>' : $t;
    return '<p class="firma-sito" id="firma-sito">' . $dentro . '</p>';
}
