<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Facettes\Infrastructure\EnvLoader;
use Facettes\Web\Controller;
use Facettes\Web\Router;

// Charger les variables d'environnement
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    (new EnvLoader($envPath))->charger();
}

// Servir les fichiers statiques via le serveur intégré PHP
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$cheminStatique = __DIR__ . parse_url($uri, PHP_URL_PATH);
if (php_sapi_name() === 'cli-server' && is_file($cheminStatique)) {
    return false;
}

$controller = new Controller();
$router = new Router();

// Routes pages
$router->get('/', fn() => $controller->dashboard());

// Routes API
$router->post('/api/analyser', fn() => $controller->lancerAnalyse());
$router->get('/api/resultats', fn() => $controller->obtenirResultats());
$router->get('/api/exporter', fn() => $controller->exporter());
$router->get('/api/catalogue', fn() => $controller->obtenirCatalogue());
$router->post('/api/catalogue', fn() => $controller->mettreAJourCatalogue());
$router->get('/api/configuration', fn() => $controller->obtenirConfiguration());
$router->post('/api/configuration', fn() => $controller->mettreAJourConfiguration());
$router->get('/api/cache/info', fn() => $controller->infoCache());
$router->post('/api/cache/purger', fn() => $controller->purgerCache());
$router->get('/api/test-semrush', fn() => $controller->testerSemrush());
$router->get('/api/vue-ensemble', fn() => $controller->vueEnsemble());
$router->post('/api/decouvrir-facettes', fn() => $controller->decouvrirFacettes());
$router->get('/api/selections', fn() => $controller->obtenirSelections());
$router->post('/api/selections', fn() => $controller->mettreAJourSelections());

// Résoudre la route
$router->resoudre(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $uri,
);
