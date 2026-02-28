<?php

// Facettes module boot: load Composer autoloader and environment
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// Propager la clé API Semrush depuis le .env plateforme vers getenv()
// Si la clé est vide côté plateforme, tenter le .env local du plugin (fallback standalone)
$semrushApiKey = $_ENV['SEMRUSH_API_KEY'] ?? '';
if ($semrushApiKey === '' && file_exists(__DIR__ . '/.env')) {
    foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || $ligne[0] === '#') continue;
        if (str_starts_with($ligne, 'SEMRUSH_API_KEY=')) {
            $semrushApiKey = substr($ligne, strlen('SEMRUSH_API_KEY='));
            $_ENV['SEMRUSH_API_KEY'] = $semrushApiKey;
            break;
        }
    }
}
if ($semrushApiKey !== '') {
    putenv("SEMRUSH_API_KEY={$semrushApiKey}");
}
