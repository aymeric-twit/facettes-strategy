<?php

declare(strict_types=1);

namespace Facettes\Analysis;

/**
 * Agrège les métriques SEO sur un ensemble de valeurs de facette.
 * Calcule : volume total, volume médian, taux suggest, CPC moyen pondéré, KD moyen.
 */
final class MetricsAggregator
{
    /**
     * @param array<int, array{volume: int, suggest: bool, cpc: float, kd: int}> $donneesValeurs
     * @return array{volume_total: int, volume_median: float, taux_suggest: float, cpc_moyen: float, kd_moyen: float}
     */
    public function agreger(array $donneesValeurs): array
    {
        if ($donneesValeurs === []) {
            return [
                'volume_total'  => 0,
                'volume_median' => 0.0,
                'taux_suggest'  => 0.0,
                'cpc_moyen'     => 0.0,
                'kd_moyen'      => 0.0,
            ];
        }

        $volumes = array_column($donneesValeurs, 'volume');
        $suggests = array_column($donneesValeurs, 'suggest');
        $cpcs = array_column($donneesValeurs, 'cpc');
        $kds = array_column($donneesValeurs, 'kd');

        $volumeTotal = array_sum($volumes);
        $volumeMedian = $this->calculerMediane($volumes);
        $tauxSuggest = $this->calculerTauxSuggest($suggests);
        $cpcMoyen = $this->calculerMoyennePonderee($cpcs, $volumes);
        $kdMoyen = count($kds) > 0 ? array_sum($kds) / count($kds) : 0.0;

        return [
            'volume_total'  => (int) $volumeTotal,
            'volume_median' => round($volumeMedian, 1),
            'taux_suggest'  => round($tauxSuggest, 4),
            'cpc_moyen'     => round($cpcMoyen, 2),
            'kd_moyen'      => round($kdMoyen, 1),
        ];
    }

    /**
     * @param int[] $valeurs
     */
    private function calculerMediane(array $valeurs): float
    {
        if ($valeurs === []) {
            return 0.0;
        }

        sort($valeurs);
        $count = count($valeurs);
        $milieu = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ($valeurs[$milieu - 1] + $valeurs[$milieu]) / 2.0;
        }

        return (float) $valeurs[$milieu];
    }

    /**
     * @param bool[] $suggests
     */
    private function calculerTauxSuggest(array $suggests): float
    {
        if ($suggests === []) {
            return 0.0;
        }

        $nombrePresent = count(array_filter($suggests));

        return $nombrePresent / count($suggests);
    }

    /**
     * Moyenne pondérée par le volume.
     *
     * @param float[] $valeurs
     * @param int[] $poids
     */
    private function calculerMoyennePonderee(array $valeurs, array $poids): float
    {
        $sommePoidsTotal = array_sum($poids);

        if ($sommePoidsTotal <= 0) {
            return count($valeurs) > 0 ? array_sum($valeurs) / count($valeurs) : 0.0;
        }

        $sommePonderee = 0.0;
        foreach ($valeurs as $index => $valeur) {
            $sommePonderee += $valeur * ($poids[$index] ?? 0);
        }

        return $sommePonderee / $sommePoidsTotal;
    }
}
