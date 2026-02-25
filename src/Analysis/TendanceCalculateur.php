<?php

declare(strict_types=1);

namespace Facettes\Analysis;

/**
 * Compare deux analyses successives pour calculer les tendances.
 * Retourne la variation en % et l'icône de tendance pour chaque facette.
 */
final class TendanceCalculateur
{
    /**
     * Compare les résultats actuels avec les précédents et ajoute les tendances.
     *
     * @param array<string, mixed> $resultatsActuels
     * @param array<string, mixed>|null $resultatsPrecedents
     * @return array<string, mixed> Résultats enrichis avec les tendances
     */
    public function calculerTendances(array $resultatsActuels, ?array $resultatsPrecedents): array
    {
        if ($resultatsPrecedents === null) {
            return $resultatsActuels;
        }

        $precedent = $resultatsPrecedents['resultats'] ?? $resultatsPrecedents;

        // Tendances Niveau 1
        if (isset($resultatsActuels['facettes_simples'])) {
            foreach ($resultatsActuels['facettes_simples'] as $type => &$donnees) {
                $donneesPrecedentes = $precedent['facettes_simples'][$type] ?? null;
                $donnees['tendance'] = $this->calculerTendanceFacette($donnees, $donneesPrecedentes);
            }
            unset($donnees);
        }

        // Tendances Niveau 2
        if (isset($resultatsActuels['combinaisons'])) {
            foreach ($resultatsActuels['combinaisons'] as $combi => &$donnees) {
                $donneesPrecedentes = $precedent['combinaisons'][$combi] ?? null;
                $donnees['tendance'] = $this->calculerTendanceFacette($donnees, $donneesPrecedentes);
            }
            unset($donnees);
        }

        return $resultatsActuels;
    }

    /**
     * @param array<string, mixed> $actuel
     * @param array<string, mixed>|null $precedent
     * @return array{volume: array{variation: float, icone: string}, score: array{variation: float, icone: string}}
     */
    private function calculerTendanceFacette(array $actuel, ?array $precedent): array
    {
        if ($precedent === null) {
            return [
                'volume' => ['variation' => 0.0, 'icone' => 'nouveau'],
                'score'  => ['variation' => 0.0, 'icone' => 'nouveau'],
            ];
        }

        $volumeActuel = (int) ($actuel['metriques']['volume_total'] ?? 0);
        $volumePrecedent = (int) ($precedent['metriques']['volume_total'] ?? 0);
        $variationVolume = $this->calculerVariation($volumeActuel, $volumePrecedent);

        $scoreActuel = (float) ($actuel['score_continu'] ?? 0);
        $scorePrecedent = (float) ($precedent['score_continu'] ?? 0);
        $variationScore = $scoreActuel - $scorePrecedent;

        return [
            'volume' => [
                'variation' => round($variationVolume, 1),
                'icone'     => $this->icone($variationVolume),
            ],
            'score' => [
                'variation' => round($variationScore, 1),
                'icone'     => $this->icone($variationScore),
            ],
        ];
    }

    private function calculerVariation(float $actuel, float $precedent): float
    {
        if ($precedent <= 0) {
            return $actuel > 0 ? 100.0 : 0.0;
        }

        return (($actuel - $precedent) / $precedent) * 100.0;
    }

    /**
     * Retourne l'icône de tendance basée sur la variation.
     */
    private function icone(float $variation): string
    {
        if ($variation >= 20) {
            return '↑';
        }
        if ($variation >= 5) {
            return '↗';
        }
        if ($variation > -5) {
            return '→';
        }
        if ($variation > -20) {
            return '↘';
        }

        return '↓';
    }
}
