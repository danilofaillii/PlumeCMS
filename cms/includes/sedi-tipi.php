<?php
/**
 * 2.27.0: i tipi di attività delle sedi (includes/sedi.php), ognuno con il tipo di schema.org che Google riconosce
 * fra i sottotipi di LocalBusiness. Solo costanti e funzioni pure: niente database, niente HTML.
 *
 * L'ultima voce della tendina è "Altro, lo scrivo io": chi la sceglie scrive il tipo in una casella (tipo_testo), che
 * il pannello formatta con la prima lettera maiuscola e il resto com'è. Verso Google un tipo scritto a mano esce come
 * LocalBusiness (Google accetta solo le sue categorie) e il testo scritto va nella descrizione e sul sito.
 */

/** chiave => [nome in italiano, tipo di schema.org]. In ordine alfabetico per nome; "altro" sempre per ultimo. */
const SEDI_TIPI = [
    'agenzia-assicurativa' => ['Agenzia assicurativa', 'InsuranceAgency'],
    'agenzia-immobiliare' => ['Agenzia immobiliare', 'RealEstateAgent'],
    'agenzia-lavoro' => ['Agenzia per il lavoro', 'EmploymentAgency'],
    'agenzia-viaggi' => ['Agenzia di viaggi', 'TravelAgency'],
    'albergo' => ['Albergo o hotel', 'Hotel'],
    'alloggio' => ['Casa vacanze, agriturismo o altro alloggio', 'LodgingBusiness'],
    'asilo' => ['Asilo nido o spazio bambini', 'ChildCare'],
    'associazione' => ['Associazione, circolo o ente', 'LocalBusiness'],
    'autofficina' => ['Autofficina, gommista o carrozzeria', 'AutoRepair'],
    'studio-legale' => ['Avvocato o studio legale', 'LegalService'],
    'bar' => ['Bar o pub', 'BarOrPub'],
    'bed-and-breakfast' => ['Bed and breakfast', 'BedAndBreakfast'],
    'biblioteca' => ['Biblioteca', 'Library'],
    'caffe' => ['Caffè, pasticceria o gelateria', 'CafeOrCoffeeShop'],
    'centro-benessere' => ['Centro benessere o spa', 'DaySpa'],
    'centro-estetico' => ['Centro estetico', 'BeautySalon'],
    'centro-medico' => ['Centro medico o poliambulatorio', 'MedicalBusiness'],
    'centro-sportivo' => ['Centro sportivo, piscina o campo', 'SportsActivityLocation'],
    'onicotecnica' => ['Centro unghie', 'NailSalon'],
    'commercialista' => ['Commercialista o consulente del lavoro', 'AccountingService'],
    'concessionaria' => ['Concessionaria auto', 'AutoDealer'],
    'consulenza-finanziaria' => ['Consulenza finanziaria', 'FinancialService'],
    'dentista' => ['Dentista', 'Dentist'],
    'traslochi' => ['Ditta di traslochi', 'MovingCompany'],
    'elettricista' => ['Elettricista', 'Electrician'],
    'fabbro' => ['Fabbro', 'Locksmith'],
    'farmacia' => ['Farmacia o parafarmacia', 'Pharmacy'],
    'ferramenta' => ['Ferramenta', 'HardwareStore'],
    'fioraio' => ['Fioraio', 'Florist'],
    'gioielleria' => ['Gioielleria', 'JewelryStore'],
    'idraulico' => ['Idraulico', 'Plumber'],
    'impresa-casa' => ['Imbianchino, falegname o altra impresa per la casa', 'HomeAndConstructionBusiness'],
    'impianti-climatizzazione' => ['Impianti di riscaldamento e climatizzazione', 'HVACBusiness'],
    'impresa-edile' => ['Impresa edile', 'GeneralContractor'],
    'lattoniere' => ['Lattoniere o copertura dei tetti', 'RoofingContractor'],
    'medico' => ['Medico o studio medico', 'Physician'],
    'museo' => ['Museo', 'Museum'],
    'negozio' => ['Negozio', 'Store'],
    'negozio-abbigliamento' => ['Negozio di abbigliamento', 'ClothingStore'],
    'mobilificio' => ['Negozio di arredamento', 'FurnitureStore'],
    'negozio-scarpe' => ['Negozio di scarpe', 'ShoeStore'],
    'palestra' => ['Palestra o centro fitness', 'HealthClub'],
    'panificio' => ['Panificio o forno', 'Bakery'],
    'parrucchiere' => ['Parrucchiere o barbiere', 'HairSalon'],
    'ristorante' => ['Ristorante, trattoria o pizzeria', 'Restaurant'],
    'stazione-servizio' => ['Stazione di servizio', 'GasStation'],
    'studio-professionale' => ['Studio professionale o consulenza', 'ProfessionalService'],
    'veterinario' => ['Veterinario', 'VeterinaryCare'],
    'altro' => ['Altro, lo scrivo io', 'LocalBusiness'],
];

/** La chiave della voce "Altro, lo scrivo io". */
const SEDI_TIPO_ALTRO = 'altro';

/** Quanti caratteri può avere un tipo scritto a mano. */
const SEDI_TIPO_TESTO_MAX = 60;

/** Le voci della tendina: chiave => nome, nell'ordine di SEDI_TIPI ("altro" per ultimo). */
function sedi_tipi(): array {
    $out = [];
    foreach (SEDI_TIPI as $k => [$nome]) $out[$k] = $nome;
    return $out;
}

/** La chiave del tipo se è in elenco, altrimenti "altro". */
function sedi_tipo_valido(string $tipo): string {
    return isset(SEDI_TIPI[$tipo]) ? $tipo : SEDI_TIPO_ALTRO;
}

/** Il tipo di schema.org per Google: un tipo scritto a mano esce come LocalBusiness. */
function sedi_tipo_schema(string $tipo): string {
    return SEDI_TIPI[sedi_tipo_valido($tipo)][1];
}

/**
 * Il tipo scritto a mano, pulito: senza tag, senza a capo, spazi singoli, al massimo SEDI_TIPO_TESTO_MAX caratteri,
 * la prima lettera maiuscola e il resto com'è ("centro di meditazione" -> "Centro di meditazione").
 */
function sedi_tipo_testo_pulito(string $s): string {
    $s = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');
    $s = str_replace(['<', '>'], '', $s);
    $s = mb_substr($s, 0, SEDI_TIPO_TESTO_MAX);
    if ($s === '') return '';
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

/** Il nome del tipo da mostrare: la voce dell'elenco, oppure il testo scritto a mano (vuoto se non c'è). */
function sedi_tipo_nome(string $tipo, string $tipoTesto = ''): string {
    $tipo = sedi_tipo_valido($tipo);
    if ($tipo === SEDI_TIPO_ALTRO) return sedi_tipo_testo_pulito($tipoTesto);
    return SEDI_TIPI[$tipo][0];
}

/** Il tipo della sede per le dieci attività del sito su misura (includes/sito-su-misura.php). */
function sedi_tipo_da_su_misura(string $attivita): string {
    return ['negozio' => 'negozio', 'ristorante' => 'ristorante', 'studio' => 'studio-professionale', 'artigiano' => 'impresa-casa',
        'coach' => 'studio-professionale', 'benessere' => 'centro-benessere', 'creativo' => 'studio-professionale',
        'alloggio' => 'bed-and-breakfast', 'associazione' => 'associazione'][$attivita] ?? SEDI_TIPO_ALTRO;
}
