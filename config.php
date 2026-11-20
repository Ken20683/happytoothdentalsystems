<?php
// config.php - System configuration
define('CLINIC_NAME', 'HAPPY TOOTH DENTAL');
define('CLINIC_LOCATION', 'Lilongwe Area 25C near Nazareen Church, EML Complex, First Floor, Room #4');
define('CLINIC_CONTACT', '0992239889');
define('CLINIC_WHATSAPP', '0882407607');
define('AIRTEL_MERCHANT_PHONE', '0992239889');

// Airtel Money Open API settings. Keep secrets outside the repository.
define('AIRTEL_API_BASE_URL', getenv('AIRTEL_API_BASE_URL') ?: 'https://openapi.airtel.africa');
define('AIRTEL_CLIENT_ID', getenv('AIRTEL_CLIENT_ID') ?: '');
define('AIRTEL_CLIENT_SECRET', getenv('AIRTEL_CLIENT_SECRET') ?: '');
define('AIRTEL_COUNTRY', getenv('AIRTEL_COUNTRY') ?: 'MW');
define('AIRTEL_CURRENCY', getenv('AIRTEL_CURRENCY') ?: 'MWK');
define('AIRTEL_MODE', getenv('AIRTEL_MODE') ?: 'demo');

$procedures = [
    1 => 'Extraction',
    2 => 'Disimpaction/Surgical Extraction',
    3 => 'Infected/Dry Socket',
    4 => 'Scaling and Polishing',
    5 => 'Composite Filling',
    6 => 'Amalgam Filling',
    7 => 'Temporary Filling',
    8 => 'Root Canal Therapy - Single Canal',
    9 => 'Root Canal Therapy - Two Canal',
    10 => 'Root Canal Therapy - Multiple Canals',
    11 => 'Dentures up to Tooth',
    12 => 'Acrylic Crown',
    13 => 'Acrylic Bridge',
    14 => 'Porcelain Crown',
    15 => 'Zirconium Crown',
    16 => 'Zirconium Bridge',
    17 => 'Gold/Silver Inlays and Crowns',
    18 => 'Gold/Silver Bridge',
    19 => 'Star and Other Tooth Jewellery Installation',
    20 => 'Orthodontic Treatment (Braces)',
    21 => 'Suturing',
    22 => 'Teeth Splinting',
    23 => 'Consultation',
    24 => 'Dentures - Any Addition Tooth'
];

$procedure_prices = [
    1 => 20000,
    2 => 50000,
    3 => 8000,
    4 => 20000,
    5 => 20000,
    6 => 20000,
    7 => 15000,
    8 => 45000,
    9 => 50000,
    10 => 70000,
    11 => 80000,
    12 => 250000,
    13 => 750000,
    14 => 300000,
    15 => 900000,
    16 => 400000,
    17 => 1200000,
    18 => 500000,
    19 => 1500000,
    20 => 100000,
    21 => 150000,
    22 => 20000,
    23 => 40000,
    24 => 8000
];
?>