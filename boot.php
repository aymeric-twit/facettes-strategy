<?php

// Facettes module boot: load Composer autoloader and environment
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// Propager la clé API Semrush depuis la plateforme vers l'environnement du module
if (!empty($_ENV['SEMRUSH_API_KEY'])) {
    putenv("SEMRUSH_API_KEY={$_ENV['SEMRUSH_API_KEY']}");
}
