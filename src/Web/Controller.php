<?php

declare(strict_types=1);

namespace Facettes\Web;

use Facettes\Analysis\CannibalisationDetecteur;
use Facettes\Analysis\FacetQualifier;
use Facettes\Analysis\FacetteDecouvreur;
use Facettes\Analysis\MetricsAggregator;
use Facettes\Analysis\TendanceCalculateur;
use Facettes\Api\GoogleSuggestClient;
use Facettes\Api\SemrushClient;
use Facettes\Catalogue\CatalogueParser;
use Facettes\Catalogue\CombinationEngine;
use Facettes\Catalogue\SelectionsManager;
use Facettes\Export\CsvExporter;
use Facettes\Export\HtmlExporter;
use Facettes\Export\JsonExporter;
use Facettes\Export\RecommandationGenerateur;
use Facettes\Infrastructure\Cache;
use Facettes\Infrastructure\EnvLoader;
use Facettes\Infrastructure\HistoriqueManager;
use Facettes\Infrastructure\Logger;
use Facettes\Infrastructure\RateLimiter;

/**
 * Contrôleur principal du dashboard web.
 */
final class Controller
{
    private readonly CatalogueParser $catalogueParser;
    private readonly Cache $cache;
    private readonly Logger $logger;
    /** @var array<string, mixed> */
    private readonly array $config;
    private readonly string $racineProjet;

    public function __construct()
    {
        $this->racineProjet = dirname(__DIR__, 2);

        /** @var array<string, mixed> $config */
        $config = require $this->racineProjet . '/config/config.php';
        $this->config = $config;

        $this->logger = new Logger(
            $this->racineProjet . '/logs/app.log',
            (string) ($this->config['log_level'] ?? 'info'),
        );

        $this->cache = new Cache(
            $this->racineProjet . '/cache',
            (int) ($this->config['cache_ttl'] ?? 604800),
            $this->logger,
        );

        $this->catalogueParser = new CatalogueParser(
            $this->racineProjet . '/config/catalogue.php',
            $this->racineProjet . '/config/catalogue.json',
            $this->logger,
        );
    }

    /**
     * Page d'accueil — Dashboard.
     */
    public function dashboard(): void
    {
        $catalogue = $this->catalogueParser->obtenirCatalogue();
        $chemins = $this->catalogueParser->obtenirCategories();
        $categories = [];

        foreach ($chemins as $chemin) {
            $noeud = $this->catalogueParser->resoudreNoeud($chemin);
            $categories[] = [
                'chemin'      => $chemin,
                'nom'         => $this->catalogueParser->obtenirNomFeuille($chemin),
                'profondeur'  => $this->catalogueParser->obtenirProfondeur($chemin),
                'genres'      => $noeud['genres'],
                'nb_facettes' => count($noeud['facettes']),
            ];
        }

        // Compter les résultats existants
        $resultatsExistants = $this->listerResultatsExistants();

        $this->rendreHtml('dashboard', [
            'categories'          => $categories,
            'resultats_existants' => $resultatsExistants,
            'catalogue_complet'   => $catalogue,
        ]);
    }

    /**
     * API — Lancer une analyse.
     */
    public function lancerAnalyse(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input') ?: '{}', true);

        if (!is_array($input) || !isset($input['categorie'], $input['genre'])) {
            http_response_code(400);
            echo json_encode(['erreur' => 'Paramètres categorie et genre requis.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $categorie = (string) $input['categorie'];
        $genre = (string) $input['genre'];

        try {
            $facettesCatalogue = $this->catalogueParser->obtenirFacettes($categorie);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['erreur' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Vérifier que le genre est valide
        $genresValides = $this->catalogueParser->obtenirGenres($categorie);
        if (!in_array($genre, $genresValides, true)) {
            http_response_code(400);
            echo json_encode(['erreur' => "Genre invalide : {$genre}"], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Si des facettes sélectionnées sont fournies, les valider et les utiliser
        if (isset($input['facettes']) && is_array($input['facettes']) && $input['facettes'] !== []) {
            $facettes = $this->validerFacettesSelectionnees($input['facettes'], $facettesCatalogue);

            if ($facettes === null) {
                http_response_code(400);
                echo json_encode(['erreur' => 'Facettes sélectionnées invalides : types ou valeurs inexistants dans le catalogue.'], JSON_UNESCAPED_UNICODE);
                return;
            }
        } else {
            $facettes = $facettesCatalogue;
        }

        // Filtrer selon les sélections par combinaison (catégorie + genre)
        $selectionsManager = new SelectionsManager();
        $facettes = $selectionsManager->obtenirFacettesFiltrees($categorie, $genre, $facettes);

        try {
            $qualifier = $this->creerQualifier();
            /** @var array<string, mixed> $seuils */
            $seuils = $this->config['seuils'];
            $profondeurMax = (int) ($this->config['profondeur_max_combinaison'] ?? 2);

            $resultats = $qualifier->analyserCategorie(
                $categorie,
                $genre,
                $facettes,
                $seuils,
                $profondeurMax,
            );

            // Historique : récupérer l'analyse précédente avant de sauvegarder
            $historiqueManager = $this->creerHistoriqueManager();
            $precedent = $historiqueManager->obtenirPrecedent($categorie, $genre);

            // Tendances : enrichir les résultats avec les variations
            $tendanceCalculateur = new TendanceCalculateur();
            $resultats = $tendanceCalculateur->calculerTendances($resultats, $precedent);

            // Détection de cannibalisation entre facettes indexées
            $seuilJaccard = (float) ($this->config['cannibalisation']['seuil_jaccard'] ?? 0.6);
            $detecteur = new CannibalisationDetecteur($seuilJaccard);
            $alertesCannibalisation = $detecteur->detecter($resultats);
            $resultats['cannibalisation'] = $alertesCannibalisation;

            // Sauvegarder dans l'historique
            $historiqueManager->sauvegarder($categorie, $genre, $resultats);

            // Sauvegarder les résultats en cache
            $cleResultat = "resultats_{$categorie}_{$genre}";
            $this->cache->stocker($cleResultat, $resultats);

            echo json_encode([
                'donnees' => $resultats,
                'message' => "Analyse terminée pour {$categorie} / {$genre}",
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            $this->logger->error("Erreur lors de l'analyse", [
                'categorie' => $categorie,
                'genre'     => $genre,
                'erreur'    => $e->getMessage(),
            ]);

            http_response_code(500);
            echo json_encode([
                'erreur' => "Erreur lors de l'analyse : {$e->getMessage()}",
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * API — Récupérer les résultats d'une analyse.
     */
    public function obtenirResultats(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $categorie = $_GET['categorie'] ?? '';
        $genre = $_GET['genre'] ?? '';

        if ($categorie === '' || $genre === '') {
            http_response_code(400);
            echo json_encode(['erreur' => 'Paramètres categorie et genre requis.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $cleResultat = "resultats_{$categorie}_{$genre}";
        $resultats = $this->cache->obtenir($cleResultat);

        if ($resultats === null) {
            http_response_code(404);
            echo json_encode(['erreur' => 'Aucun résultat trouvé pour cette catégorie/genre.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'donnees'   => $resultats,
            'categorie' => $categorie,
            'genre'     => $genre,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * API — Exporter les résultats.
     */
    public function exporter(): void
    {
        $categorie = $_GET['categorie'] ?? '';
        $genre = $_GET['genre'] ?? '';
        $format = $_GET['format'] ?? 'json';

        if ($categorie === '' || $genre === '') {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['erreur' => 'Paramètres categorie, genre et format requis.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $cleResultat = "resultats_{$categorie}_{$genre}";
        $resultats = $this->cache->obtenir($cleResultat);

        if ($resultats === null || !is_array($resultats)) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['erreur' => 'Aucun résultat à exporter.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $repertoireExport = $this->racineProjet . '/exports';

        match ($format) {
            'csv' => $this->exporterCsv($resultats, $categorie, $genre, $repertoireExport),
            'html' => $this->exporterHtmlStatique($resultats, $categorie, $genre, $repertoireExport),
            default => $this->exporterJson($resultats, $categorie, $genre, $repertoireExport),
        };
    }

    /**
     * API — Configuration : lecture.
     */
    public function obtenirConfiguration(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'donnees' => $this->config,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * API — Configuration : mise à jour des seuils.
     */
    public function mettreAJourConfiguration(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input') ?: '{}', true);

        if (!is_array($input) || !isset($input['seuils'])) {
            http_response_code(400);
            echo json_encode(['erreur' => 'Seuils manquants.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $cheminConfig = $this->racineProjet . '/config/config.php';

        /** @var array<string, mixed> $configActuelle */
        $configActuelle = require $cheminConfig;
        $configActuelle['seuils'] = $input['seuils'];

        $contenu = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($configActuelle, true) . ";\n";
        file_put_contents($cheminConfig, $contenu, LOCK_EX);

        $this->logger->info('Configuration mise à jour', ['seuils' => $input['seuils']]);

        echo json_encode([
            'message' => 'Configuration sauvegardée.',
            'donnees' => $configActuelle,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * API — Informations sur le cache.
     */
    public function infoCache(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'donnees' => $this->cache->taille(),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * API — Purger le cache.
     */
    public function purgerCache(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input') ?: '{}', true);
        $prefixe = is_array($input) ? ($input['prefixe'] ?? '') : '';

        if ($prefixe !== '') {
            $nombre = $this->cache->purgerParPrefixe((string) $prefixe);
        } else {
            $nombre = $this->cache->purgerTout();
        }

        echo json_encode([
            'message' => "{$nombre} entrées supprimées du cache.",
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * API — Tester la connexion SEMrush.
     */
    public function testerSemrush(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $apiKey = EnvLoader::obtenir('SEMRUSH_API_KEY');

        if ($apiKey === '') {
            echo json_encode([
                'statut'  => 'erreur',
                'message' => 'Clé API SEMrush non configurée dans .env',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $rateLimiter = new RateLimiter();
        /** @var array{database: string} $semrushConfig */
        $semrushConfig = $this->config['semrush'];

        $client = new SemrushClient(
            $apiKey,
            $semrushConfig['database'],
            $this->cache,
            $rateLimiter,
            $this->logger,
        );

        $ok = $client->testerConnexion();

        echo json_encode([
            'statut'  => $ok ? 'ok' : 'erreur',
            'message' => $ok ? 'Connexion SEMrush fonctionnelle' : 'Impossible de joindre SEMrush',
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * API — Lister le catalogue.
     */
    public function obtenirCatalogue(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $catalogue = $this->catalogueParser->obtenirCatalogue();

        echo json_encode([
            'donnees' => $catalogue,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * API — Mettre à jour le catalogue complet.
     */
    public function mettreAJourCatalogue(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input') ?: '{}', true);

        if (!is_array($input) || !isset($input['catalogue']) || !is_array($input['catalogue'])) {
            http_response_code(400);
            echo json_encode(['erreur' => 'Catalogue manquant ou invalide.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            /** @var array<string, array{genres: string[], facettes: array<string, string[]>}> $catalogue */
            $catalogue = $input['catalogue'];
            $this->catalogueParser->sauvegarder($catalogue);

            echo json_encode([
                'message' => 'Catalogue sauvegardé.',
                'donnees' => $catalogue,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\RuntimeException $e) {
            http_response_code(422);
            echo json_encode([
                'erreur' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Valide les facettes sélectionnées par l'utilisateur contre le catalogue réel.
     *
     * @param array<string, mixed> $facettesSelectionnees Facettes envoyées par le client {type: [valeurs]}
     * @param array<string, string[]> $facettesCatalogue Facettes existantes dans le catalogue
     * @return array<string, string[]>|null Les facettes validées ou null si invalides
     */
    private function validerFacettesSelectionnees(array $facettesSelectionnees, array $facettesCatalogue): ?array
    {
        $facettesValidees = [];

        foreach ($facettesSelectionnees as $type => $valeurs) {
            if (!is_string($type) || !isset($facettesCatalogue[$type])) {
                return null;
            }

            if (!is_array($valeurs) || $valeurs === []) {
                continue;
            }

            $valeursValidees = [];
            foreach ($valeurs as $valeur) {
                if (!is_string($valeur) || !in_array($valeur, $facettesCatalogue[$type], true)) {
                    return null;
                }
                $valeursValidees[] = $valeur;
            }

            if ($valeursValidees !== []) {
                $facettesValidees[$type] = $valeursValidees;
            }
        }

        return $facettesValidees !== [] ? $facettesValidees : null;
    }

    private function creerQualifier(): FacetQualifier
    {
        $apiKey = EnvLoader::obtenir('SEMRUSH_API_KEY');

        if ($apiKey === '') {
            throw new \RuntimeException('Clé API SEMrush non configurée dans .env');
        }

        $rateLimiter = new RateLimiter();

        /** @var array{database: string, requests_per_second: int} $semrushConfig */
        $semrushConfig = $this->config['semrush'];
        /** @var array{lang: string, country: string, requests_per_second: int} $suggestConfig */
        $suggestConfig = $this->config['google_suggest'];

        $semrushClient = new SemrushClient(
            $apiKey,
            $semrushConfig['database'],
            $this->cache,
            $rateLimiter,
            $this->logger,
        );

        $suggestClient = new GoogleSuggestClient(
            $suggestConfig['lang'],
            $suggestConfig['country'],
            $this->cache,
            $rateLimiter,
            $this->logger,
        );

        $formatRequete = (string) ($this->config['format_requete'] ?? '{categorie} {genre} {facettes}');
        $combinationEngine = new CombinationEngine($this->logger, $formatRequete);
        $aggregator = new MetricsAggregator();
        $tailleBatch = (int) ($this->config['taille_batch'] ?? 50);

        /** @var array<string, mixed> $configScoring */
        $configScoring = $this->config['scoring'] ?? [];
        /** @var array<string, mixed> $configZones */
        $configZones = $this->config['zones'] ?? [];

        return new FacetQualifier(
            $semrushClient,
            $suggestClient,
            $combinationEngine,
            $aggregator,
            $this->logger,
            $tailleBatch,
            $configScoring,
            $configZones,
        );
    }

    /**
     * @param array<string, mixed> $resultats
     */
    private function exporterCsv(array $resultats, string $categorie, string $genre, string $repertoire): void
    {
        $recommandeur = new RecommandationGenerateur();
        $exporter = new CsvExporter($repertoire, $recommandeur);
        $chemin = $exporter->exporter($resultats, $categorie, $genre);
        $nomFichier = basename($chemin);

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$nomFichier}\"");
        readfile($chemin);
    }

    /**
     * @param array<string, mixed> $resultats
     */
    private function exporterJson(array $resultats, string $categorie, string $genre, string $repertoire): void
    {
        $recommandeur = new RecommandationGenerateur();
        $exporter = new JsonExporter($repertoire, $recommandeur);
        $chemin = $exporter->exporter($resultats, $categorie, $genre);
        $nomFichier = basename($chemin);

        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$nomFichier}\"");
        readfile($chemin);
    }

    /**
     * @param array<string, mixed> $resultats
     */
    private function exporterHtmlStatique(array $resultats, string $categorie, string $genre, string $repertoire): void
    {
        $recommandeur = new RecommandationGenerateur();
        $exporter = new HtmlExporter($repertoire, $recommandeur);
        $chemin = $exporter->exporter($resultats, $categorie, $genre);
        $nomFichier = basename($chemin);

        header('Content-Type: text/html; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$nomFichier}\"");
        readfile($chemin);
    }

    /**
     * API — Vue d'ensemble : agrège les résultats de toutes les analyses en cache.
     */
    public function vueEnsemble(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $resultatsExistants = $this->listerResultatsExistants();
        $vueEnsemble = [];

        foreach ($resultatsExistants as $r) {
            $cle = "resultats_{$r['categorie']}_{$r['genre']}";
            $resultats = $this->cache->obtenir($cle);

            if (!is_array($resultats)) {
                continue;
            }

            $nbIndex = 0;
            $nbNoindex = 0;
            $scoreMoyen = 0.0;
            $volumeTotal = 0;
            $nbFacettes = 0;
            $meilleurQuickWin = null;
            $meilleurScore = 0.0;

            $toutesLesFacettes = array_merge(
                $resultats['facettes_simples'] ?? [],
                $resultats['combinaisons'] ?? [],
            );

            foreach ($toutesLesFacettes as $nom => $donnees) {
                $nbFacettes++;
                if ($donnees['decision'] === 'index') {
                    $nbIndex++;
                } else {
                    $nbNoindex++;
                }

                $score = (float) ($donnees['score_continu'] ?? 0);
                $scoreMoyen += $score;
                $volumeTotal += (int) ($donnees['metriques']['volume_total'] ?? 0);

                if (($donnees['zone'] ?? '') === 'QUICK_WIN' && $score > $meilleurScore) {
                    $meilleurScore = $score;
                    $meilleurQuickWin = $nom;
                }
            }

            $vueEnsemble[] = [
                'categorie'         => $r['categorie'],
                'genre'             => $r['genre'],
                'nb_facettes'       => $nbFacettes,
                'nb_index'          => $nbIndex,
                'nb_noindex'        => $nbNoindex,
                'score_moyen'       => $nbFacettes > 0 ? round($scoreMoyen / $nbFacettes, 1) : 0,
                'volume_total'      => $volumeTotal,
                'meilleur_quick_win' => $meilleurQuickWin,
            ];
        }

        echo json_encode([
            'donnees' => $vueEnsemble,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * API — Récupérer les sélections de facettes par combinaison.
     */
    public function obtenirSelections(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $selectionsManager = new SelectionsManager();
        $selections = $selectionsManager->charger();

        echo json_encode([
            'donnees' => $selections,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * API — Sauvegarder les sélections de facettes par combinaison.
     */
    public function mettreAJourSelections(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input') ?: '{}', true);

        if (!is_array($input) || !isset($input['selections']) || !is_array($input['selections'])) {
            http_response_code(400);
            echo json_encode(['erreur' => 'Sélections manquantes ou invalides.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $selectionsManager = new SelectionsManager();
            /** @var array<string, array<string, array<string, string[]>>> $selections */
            $selections = $input['selections'];
            $selectionsManager->sauvegarder($selections);

            $this->logger->info('Sélections de facettes sauvegardées');

            echo json_encode([
                'message' => 'Sélections sauvegardées.',
                'donnees' => $selections,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode([
                'erreur' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * API — Découvrir des facettes manquantes via Google Suggest.
     */
    public function decouvrirFacettes(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input') ?: '{}', true);

        if (!is_array($input) || !isset($input['categorie'], $input['genre'])) {
            http_response_code(400);
            echo json_encode(['erreur' => 'Paramètres categorie et genre requis.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $categorie = (string) $input['categorie'];
        $genre = (string) $input['genre'];

        try {
            $facettesCatalogue = $this->catalogueParser->obtenirFacettes($categorie);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['erreur' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $suggestClient = $this->creerSuggestClient();
            $decouvreur = new FacetteDecouvreur($suggestClient, $this->logger);

            $suggestions = $decouvreur->decouvrir($categorie, $genre, $facettesCatalogue);

            echo json_encode([
                'donnees' => $suggestions,
                'message' => count($suggestions) . " facettes potentielles découvertes.",
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'erreur' => "Erreur lors de la découverte : {$e->getMessage()}",
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function creerHistoriqueManager(): HistoriqueManager
    {
        return new HistoriqueManager(
            $this->racineProjet . '/data/historique',
            $this->logger,
        );
    }

    private function creerSuggestClient(): GoogleSuggestClient
    {
        $rateLimiter = new RateLimiter();
        /** @var array{lang: string, country: string, requests_per_second: int} $suggestConfig */
        $suggestConfig = $this->config['google_suggest'];

        return new GoogleSuggestClient(
            $suggestConfig['lang'],
            $suggestConfig['country'],
            $this->cache,
            $rateLimiter,
            $this->logger,
        );
    }

    /**
     * @return array<int, array{categorie: string, genre: string}>
     */
    private function listerResultatsExistants(): array
    {
        $resultats = [];
        $chemins = $this->catalogueParser->obtenirCategories();

        foreach ($chemins as $chemin) {
            $noeud = $this->catalogueParser->resoudreNoeud($chemin);
            foreach ($noeud['genres'] as $genre) {
                $cle = "resultats_{$chemin}_{$genre}";
                if ($this->cache->obtenir($cle) !== null) {
                    $resultats[] = [
                        'categorie' => $chemin,
                        'genre'     => $genre,
                    ];
                }
            }
        }

        return $resultats;
    }

    /**
     * @param array<string, mixed> $donnees
     */
    private function rendreHtml(string $nomTemplate, array $donnees = []): void
    {
        header('Content-Type: text/html; charset=utf-8');

        extract($donnees);

        $cheminTemplate = $this->racineProjet . '/templates/' . $nomTemplate . '.php';

        if (!file_exists($cheminTemplate)) {
            http_response_code(500);
            echo "Template introuvable : {$nomTemplate}";
            return;
        }

        ob_start();
        require $cheminTemplate;
        $contenu = ob_get_clean();

        echo $contenu;
    }
}
