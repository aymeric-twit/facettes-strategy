<?php

declare(strict_types=1);

namespace Facettes\Analysis;

use Facettes\Api\GoogleSuggestClient;
use Facettes\Api\SemrushClient;
use Facettes\Catalogue\CombinationEngine;
use Facettes\Infrastructure\Logger;

/**
 * Orchestre l'analyse complète d'une catégorie :
 * - Niveau 1 : qualification de chaque type de facette isolé
 * - Niveau 2 : qualification de chaque combinaison de types
 */
final class FacetQualifier
{
    /** @var array{volume: int, suggest: int, cpc: int, kd: int} */
    private readonly array $poids;

    /** @var array{volume: int, cpc: float, kd: int} */
    private readonly array $plafonds;

    private readonly int $seuilIndex;

    /** @var array{seuil_score_haut: int, seuil_score_bas: int, seuil_kd_facile: int, seuil_kd_niche: int} */
    private readonly array $configZones;

    /**
     * @param array<string, mixed> $configScoring Section 'scoring' de la config
     * @param array<string, mixed> $configZones Section 'zones' de la config
     */
    public function __construct(
        private readonly SemrushClient $semrushClient,
        private readonly GoogleSuggestClient $suggestClient,
        private readonly CombinationEngine $combinationEngine,
        private readonly MetricsAggregator $aggregator,
        private readonly Logger $logger,
        private readonly int $tailleBatch = 50,
        array $configScoring = [],
        array $configZones = [],
    ) {
        $this->poids = [
            'volume'  => (int) ($configScoring['poids']['volume'] ?? 35),
            'suggest' => (int) ($configScoring['poids']['suggest'] ?? 25),
            'cpc'     => (int) ($configScoring['poids']['cpc'] ?? 20),
            'kd'      => (int) ($configScoring['poids']['kd'] ?? 20),
        ];
        $this->plafonds = [
            'volume' => (int) ($configScoring['plafonds']['volume'] ?? 5000),
            'cpc'    => (float) ($configScoring['plafonds']['cpc'] ?? 3.0),
            'kd'     => (int) ($configScoring['plafonds']['kd'] ?? 100),
        ];
        $this->seuilIndex = (int) ($configScoring['seuil_index'] ?? 55);
        $this->configZones = [
            'seuil_score_haut' => (int) ($configZones['seuil_score_haut'] ?? 55),
            'seuil_score_bas'  => (int) ($configZones['seuil_score_bas'] ?? 30),
            'seuil_kd_facile'  => (int) ($configZones['seuil_kd_facile'] ?? 40),
            'seuil_kd_niche'   => (int) ($configZones['seuil_kd_niche'] ?? 30),
        ];
    }

    /**
     * Analyse une catégorie complète pour un genre donné.
     *
     * @param array<string, string[]> $facettes
     * @param array<string, array<string, float|int>> $seuils
     * @return array{facettes_simples: array<string, mixed>, combinaisons: array<string, mixed>}
     */
    public function analyserCategorie(
        string $categorie,
        string $genre,
        array $facettes,
        array $seuils,
        int $profondeurMaxCombinaison = 2,
    ): array {
        $this->logger->info("Début analyse : {$categorie} / {$genre}");

        // Niveau 1 : facettes simples
        $facettesSimples = [];
        foreach ($facettes as $typeFacette => $valeurs) {
            $facettesSimples[$typeFacette] = $this->analyserFacetteSimple(
                $categorie,
                $genre,
                $typeFacette,
                $valeurs,
                $seuils['simple'],
            );
        }

        // Niveau 2 : combinaisons
        $combinaisons = [];
        $requetesCombinaisons = $this->combinationEngine->genererRequetesCombinaisons(
            $categorie,
            $genre,
            $facettes,
            $profondeurMaxCombinaison,
        );

        foreach ($requetesCombinaisons as $cleCombinaison => $requetes) {
            $combinaisons[$cleCombinaison] = $this->analyserCombinaison(
                $cleCombinaison,
                $requetes,
                $seuils['combinaison'],
            );
        }

        $this->logger->info("Analyse terminée : {$categorie} / {$genre}", [
            'facettes_simples' => count($facettesSimples),
            'combinaisons'     => count($combinaisons),
        ]);

        return [
            'facettes_simples' => $facettesSimples,
            'combinaisons'     => $combinaisons,
        ];
    }

    /**
     * Analyse un type de facette isolé (Niveau 1).
     *
     * @param string[] $valeurs
     * @param array<string, float|int> $seuils
     * @return array{decision: string, score: string, score_continu: float, score_ancien: string, zone: string, metriques: array<string, mixed>, detail_valeurs: array<int, mixed>}
     */
    private function analyserFacetteSimple(
        string $categorie,
        string $genre,
        string $typeFacette,
        array $valeurs,
        array $seuils,
    ): array {
        $requetes = $this->combinationEngine->genererRequetesSimples(
            $categorie,
            $genre,
            $typeFacette,
            $valeurs,
        );

        $detailValeurs = [];
        $donneesAgregation = [];

        $batches = array_chunk($requetes, $this->tailleBatch);

        foreach ($batches as $batch) {
            foreach ($batch as $requete) {
                $metriques = $this->semrushClient->obtenirMetriques($requete['requete']);
                $dansSuggest = $this->suggestClient->estDansSuggest($requete['requete']);

                $volume = $metriques['volume'] ?? 0;
                $cpc = $metriques['cpc'] ?? 0.0;
                $kd = $metriques['kd'] ?? 0;

                $detail = [
                    'valeur'  => $requete['valeur'],
                    'requete' => $requete['requete'],
                    'volume'  => $volume,
                    'suggest' => $dansSuggest,
                    'cpc'     => $cpc,
                    'kd'      => $kd,
                ];

                if ($metriques === null) {
                    $detail['statut'] = 'no_data';
                }

                $detailValeurs[] = $detail;
                $donneesAgregation[] = [
                    'volume'  => $volume,
                    'suggest' => $dansSuggest,
                    'cpc'     => $cpc,
                    'kd'      => $kd,
                ];
            }

            gc_collect_cycles();
        }

        $metriquesAgregees = $this->aggregator->agreger($donneesAgregation);
        $scoreContinu = $this->calculerScoreContinu($metriquesAgregees);
        $scoreAncien = $this->calculerScoreAncien($metriquesAgregees, $seuils);
        $decision = $scoreContinu >= $this->seuilIndex ? Decision::INDEX : Decision::NOINDEX;
        $zone = $this->determinerZone($scoreContinu, (float) $metriquesAgregees['kd_moyen']);

        return [
            'decision'       => $decision->value,
            'score'          => number_format($scoreContinu, 1) . '/100',
            'score_continu'  => round($scoreContinu, 1),
            'score_ancien'   => "{$scoreAncien}/4",
            'zone'           => $zone,
            'metriques'      => $metriquesAgregees,
            'detail_valeurs' => $detailValeurs,
        ];
    }

    /**
     * Analyse une combinaison de types de facettes (Niveau 2).
     *
     * @param array<int, array{valeurs: string[], requete: string}> $requetes
     * @param array<string, float|int> $seuils
     * @return array{decision: string, score: string, score_continu: float, score_ancien: string, zone: string, metriques: array<string, mixed>, detail_valeurs: array<int, mixed>}
     */
    private function analyserCombinaison(
        string $cleCombinaison,
        array $requetes,
        array $seuils,
    ): array {
        $this->logger->info("Analyse combinaison : {$cleCombinaison} ({$this->nombreRequetes($requetes)} requêtes)");

        $detailValeurs = [];
        $donneesAgregation = [];

        $batches = array_chunk($requetes, $this->tailleBatch);

        foreach ($batches as $batch) {
            foreach ($batch as $requete) {
                $metriques = $this->semrushClient->obtenirMetriques($requete['requete']);
                $dansSuggest = $this->suggestClient->estDansSuggest($requete['requete']);

                $volume = $metriques['volume'] ?? 0;
                $cpc = $metriques['cpc'] ?? 0.0;
                $kd = $metriques['kd'] ?? 0;

                $detail = [
                    'valeurs' => $requete['valeurs'],
                    'requete' => $requete['requete'],
                    'volume'  => $volume,
                    'suggest' => $dansSuggest,
                    'cpc'     => $cpc,
                    'kd'      => $kd,
                ];

                if ($metriques === null) {
                    $detail['statut'] = 'no_data';
                }

                $detailValeurs[] = $detail;
                $donneesAgregation[] = [
                    'volume'  => $volume,
                    'suggest' => $dansSuggest,
                    'cpc'     => $cpc,
                    'kd'      => $kd,
                ];
            }

            gc_collect_cycles();
        }

        $metriquesAgregees = $this->aggregator->agreger($donneesAgregation);
        $scoreContinu = $this->calculerScoreContinu($metriquesAgregees);
        $scoreAncien = $this->calculerScoreAncien($metriquesAgregees, $seuils);
        $decision = $scoreContinu >= $this->seuilIndex ? Decision::INDEX : Decision::NOINDEX;
        $zone = $this->determinerZone($scoreContinu, (float) $metriquesAgregees['kd_moyen']);

        return [
            'decision'       => $decision->value,
            'score'          => number_format($scoreContinu, 1) . '/100',
            'score_continu'  => round($scoreContinu, 1),
            'score_ancien'   => "{$scoreAncien}/4",
            'zone'           => $zone,
            'metriques'      => $metriquesAgregees,
            'detail_valeurs' => $detailValeurs,
        ];
    }

    /**
     * Score continu pondéré 0-100 intégrant volume, suggest, CPC et KD.
     *
     * @param array<string, mixed> $metriques
     */
    private function calculerScoreContinu(array $metriques): float
    {
        $sommePoids = array_sum($this->poids);
        if ($sommePoids <= 0) {
            return 0.0;
        }

        $volumeTotal = (int) $metriques['volume_total'];
        $tauxSuggest = (float) $metriques['taux_suggest'];
        $cpcMoyen = (float) $metriques['cpc_moyen'];
        $kdMoyen = (float) $metriques['kd_moyen'];

        $scoreVolume = min($volumeTotal / $this->plafonds['volume'], 1.0) * $this->poids['volume'];
        $scoreSuggest = $tauxSuggest * $this->poids['suggest'];
        $scoreCpc = min($cpcMoyen / $this->plafonds['cpc'], 1.0) * $this->poids['cpc'];
        // KD inversé : faible KD = meilleur score
        $scoreKd = (1.0 - min($kdMoyen / $this->plafonds['kd'], 1.0)) * $this->poids['kd'];

        $scoreTotal = ($scoreVolume + $scoreSuggest + $scoreCpc + $scoreKd) * (100.0 / $sommePoids);

        return max(0.0, min(100.0, $scoreTotal));
    }

    /**
     * Ancien score binaire 0-4 conservé pour rétrocompatibilité.
     *
     * @param array<string, mixed> $metriques
     * @param array<string, float|int> $seuils
     */
    private function calculerScoreAncien(array $metriques, array $seuils): int
    {
        $score = 0;

        if ($metriques['volume_total'] >= $seuils['volume_total_min']) {
            $score++;
        }
        if ($metriques['volume_median'] >= $seuils['volume_median_min']) {
            $score++;
        }
        if ($metriques['taux_suggest'] >= $seuils['taux_suggest_min']) {
            $score++;
        }
        if ($metriques['cpc_moyen'] >= $seuils['cpc_moyen_min']) {
            $score++;
        }

        return $score;
    }

    /**
     * Détermine la zone d'action SEO en croisant score et difficulté.
     */
    private function determinerZone(float $score, float $kd): string
    {
        $seuilHaut = $this->configZones['seuil_score_haut'];
        $seuilBas = $this->configZones['seuil_score_bas'];
        $seuilKdFacile = $this->configZones['seuil_kd_facile'];
        $seuilKdNiche = $this->configZones['seuil_kd_niche'];

        if ($score >= $seuilHaut && $kd < $seuilKdFacile) {
            return 'QUICK_WIN';
        }
        if ($score >= $seuilHaut) {
            return 'FORT_POTENTIEL';
        }
        if ($score >= $seuilBas && $kd < $seuilKdNiche) {
            return 'NICHE';
        }
        if ($score >= $seuilBas) {
            return 'SURVEILLER';
        }

        return 'IGNORER';
    }

    /**
     * @param array<int, mixed> $requetes
     */
    private function nombreRequetes(array $requetes): int
    {
        return count($requetes);
    }
}
