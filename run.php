<?php

declare(strict_types=1);

/**
 * Script CLI pour lancer l'analyse des facettes.
 *
 * Usage :
 *   php run.php                                        # Analyse toutes les catégories
 *   php run.php robes femme                            # Analyse une catégorie/genre spécifique
 *   php run.php "vetements > pantalons > shorts" femme # Catégorie hiérarchique
 *   php run.php --export=json robes femme
 */

require_once __DIR__ . '/vendor/autoload.php';

use Facettes\Analysis\FacetQualifier;
use Facettes\Analysis\MetricsAggregator;
use Facettes\Api\GoogleSuggestClient;
use Facettes\Api\SemrushClient;
use Facettes\Catalogue\CatalogueParser;
use Facettes\Catalogue\CombinationEngine;
use Facettes\Export\CsvExporter;
use Facettes\Export\HtmlExporter;
use Facettes\Export\JsonExporter;
use Facettes\Infrastructure\Cache;
use Facettes\Infrastructure\EnvLoader;
use Facettes\Infrastructure\Logger;
use Facettes\Infrastructure\RateLimiter;

// Charger .env
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    (new EnvLoader($envPath))->charger();
}

// Configuration
/** @var array<string, mixed> $config */
$config = require __DIR__ . '/config/config.php';

$logger = new Logger(__DIR__ . '/logs/app.log', (string) ($config['log_level'] ?? 'info'));
$cache = new Cache(__DIR__ . '/cache', (int) ($config['cache_ttl'] ?? 604800), $logger);

// Vérifier la clé API
$apiKey = EnvLoader::obtenir('SEMRUSH_API_KEY');
if ($apiKey === '') {
    ecrireLn("\033[31mErreur : SEMRUSH_API_KEY non définie dans .env\033[0m");
    exit(1);
}

// Initialiser les clients
$rateLimiter = new RateLimiter();

/** @var array{database: string} $semrushConfig */
$semrushConfig = $config['semrush'];
/** @var array{lang: string, country: string} $suggestConfig */
$suggestConfig = $config['google_suggest'];

$semrushClient = new SemrushClient(
    $apiKey,
    $semrushConfig['database'],
    $cache,
    $rateLimiter,
    $logger,
);

$suggestClient = new GoogleSuggestClient(
    $suggestConfig['lang'],
    $suggestConfig['country'],
    $cache,
    $rateLimiter,
    $logger,
);

$combinationEngine = new CombinationEngine($logger);
$aggregator = new MetricsAggregator();
$tailleBatch = (int) ($config['taille_batch'] ?? 50);

$qualifier = new FacetQualifier(
    $semrushClient,
    $suggestClient,
    $combinationEngine,
    $aggregator,
    $logger,
    $tailleBatch,
);

$catalogueParser = new CatalogueParser(__DIR__ . '/config/catalogue.php', __DIR__ . '/config/catalogue.json', $logger);
$catalogueParser->charger();

// Parser les arguments
$args = array_slice($argv, 1);
$formatExport = 'json';
$categorieFiltre = null;
$genreFiltre = null;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--export=')) {
        $formatExport = substr($arg, 9);
    } elseif ($categorieFiltre === null) {
        $categorieFiltre = $arg;
    } elseif ($genreFiltre === null) {
        $genreFiltre = $arg;
    }
}

/** @var array<string, mixed> $seuils */
$seuils = $config['seuils'];
$profondeurMax = (int) ($config['profondeur_max_combinaison'] ?? 2);

$tousLesChemins = $catalogueParser->obtenirCategories();
$analysesAFaire = [];

if ($categorieFiltre !== null) {
    // Valider que le chemin existe (lève une exception si invalide)
    try {
        $catalogueParser->obtenirFacettes($categorieFiltre);
    } catch (\InvalidArgumentException $e) {
        ecrireLn("\033[31mCatégorie inconnue : {$categorieFiltre}\033[0m");
        ecrireLn("Catégories disponibles : " . implode(', ', $tousLesChemins));
        exit(1);
    }

    $genresCategorie = $catalogueParser->obtenirGenres($categorieFiltre);
    $genres = $genreFiltre !== null ? [$genreFiltre] : $genresCategorie;

    foreach ($genres as $genre) {
        if (!in_array($genre, $genresCategorie, true)) {
            ecrireLn("\033[31mGenre invalide '{$genre}' pour la catégorie '{$categorieFiltre}'\033[0m");
            exit(1);
        }
        $analysesAFaire[] = ['categorie' => $categorieFiltre, 'genre' => $genre];
    }
} else {
    foreach ($tousLesChemins as $chemin) {
        $genresCategorie = $catalogueParser->obtenirGenres($chemin);
        foreach ($genresCategorie as $genre) {
            $analysesAFaire[] = ['categorie' => $chemin, 'genre' => $genre];
        }
    }
}

// Lancer les analyses
ecrireLn("\033[1m╔══════════════════════════════════════╗");
ecrireLn("║   Analyseur de Facettes SEO          ║");
ecrireLn("╚══════════════════════════════════════╝\033[0m");
ecrireLn('');
ecrireLn(count($analysesAFaire) . ' analyse(s) à effectuer');
ecrireLn('');

$repertoireExport = __DIR__ . '/exports';

foreach ($analysesAFaire as $analyse) {
    $categorie = $analyse['categorie'];
    $genre = $analyse['genre'];

    ecrireLn("\033[36m▸ Analyse : {$categorie} / {$genre}\033[0m");

    $facettes = $catalogueParser->obtenirFacettes($categorie);

    $resultats = $qualifier->analyserCategorie(
        $categorie,
        $genre,
        $facettes,
        $seuils,
        $profondeurMax,
    );

    // Sauvegarder dans le cache
    $cleResultat = "resultats_{$categorie}_{$genre}";
    $cache->stocker($cleResultat, $resultats);

    // Afficher le résumé
    afficherResume($resultats);

    // Exporter
    $chemin = match ($formatExport) {
        'csv'  => (new CsvExporter($repertoireExport))->exporter($resultats, $categorie, $genre),
        'html' => (new HtmlExporter($repertoireExport))->exporter($resultats, $categorie, $genre),
        default => (new JsonExporter($repertoireExport))->exporter($resultats, $categorie, $genre),
    };

    ecrireLn("  \033[32m✓ Export : {$chemin}\033[0m");
    ecrireLn('');
}

ecrireLn("\033[32m✓ Toutes les analyses sont terminées.\033[0m");

// === Fonctions utilitaires ===

function ecrireLn(string $texte): void
{
    echo $texte . PHP_EOL;
}

/**
 * @param array<string, mixed> $resultats
 */
function afficherResume(array $resultats): void
{
    if (isset($resultats['facettes_simples'])) {
        ecrireLn('  Niveau 1 — Facettes simples :');
        foreach ($resultats['facettes_simples'] as $type => $donnees) {
            $icone = $donnees['decision'] === 'index' ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
            ecrireLn("    {$icone} {$type} → {$donnees['decision']} ({$donnees['score']})  vol={$donnees['metriques']['volume_total']}");
        }
    }

    if (isset($resultats['combinaisons'])) {
        ecrireLn('  Niveau 2 — Combinaisons :');
        foreach ($resultats['combinaisons'] as $combi => $donnees) {
            $icone = $donnees['decision'] === 'index' ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
            ecrireLn("    {$icone} {$combi} → {$donnees['decision']} ({$donnees['score']})  vol={$donnees['metriques']['volume_total']}");
        }
    }
}
