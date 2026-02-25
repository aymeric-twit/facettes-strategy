<?php

declare(strict_types=1);

namespace Facettes\Analysis;

/**
 * Détecte les risques de cannibalisation entre facettes indexées.
 * Compare les ensembles de requêtes via la similarité de Jaccard.
 */
final class CannibalisationDetecteur
{
    public function __construct(
        private readonly float $seuilJaccard = 0.6,
    ) {}

    /**
     * Détecte les paires de facettes potentiellement cannibalisantes.
     *
     * @param array<string, mixed> $resultats Résultats de l'analyse complète
     * @return array<int, array{facette_a: string, facette_b: string, similarite: float, requetes_communes: string[], recommandation: string}>
     */
    public function detecter(array $resultats): array
    {
        $facettesIndexees = $this->collecterFacettesIndexees($resultats);
        $alertes = [];

        $noms = array_keys($facettesIndexees);
        $nbFacettes = count($noms);

        for ($i = 0; $i < $nbFacettes; $i++) {
            for ($j = $i + 1; $j < $nbFacettes; $j++) {
                $nomA = $noms[$i];
                $nomB = $noms[$j];
                $requetesA = $facettesIndexees[$nomA];
                $requetesB = $facettesIndexees[$nomB];

                $similarite = $this->jaccard($requetesA, $requetesB);

                if ($similarite >= $this->seuilJaccard) {
                    $communes = array_values(array_intersect($requetesA, $requetesB));
                    $alertes[] = [
                        'facette_a'         => $nomA,
                        'facette_b'         => $nomB,
                        'similarite'        => round($similarite, 3),
                        'requetes_communes' => array_slice($communes, 0, 5),
                        'recommandation'    => $this->genererRecommandation($similarite),
                    ];
                }
            }
        }

        // Tri par similarité décroissante
        usort($alertes, fn(array $a, array $b): int => $b['similarite'] <=> $a['similarite']);

        return $alertes;
    }

    /**
     * Collecte les requêtes de toutes les facettes indexées.
     *
     * @param array<string, mixed> $resultats
     * @return array<string, string[]> Nom de facette => liste de requêtes
     */
    private function collecterFacettesIndexees(array $resultats): array
    {
        $facettes = [];

        foreach (['facettes_simples', 'combinaisons'] as $niveau) {
            if (!isset($resultats[$niveau]) || !is_array($resultats[$niveau])) {
                continue;
            }

            foreach ($resultats[$niveau] as $nom => $donnees) {
                if (($donnees['decision'] ?? '') !== 'index') {
                    continue;
                }

                $requetes = [];
                foreach ($donnees['detail_valeurs'] ?? [] as $detail) {
                    $requetes[] = mb_strtolower($detail['requete'] ?? '');
                }

                $facettes[(string) $nom] = $requetes;
            }
        }

        return $facettes;
    }

    /**
     * Calcule l'indice de similarité de Jaccard entre deux ensembles.
     *
     * @param string[] $a
     * @param string[] $b
     */
    private function jaccard(array $a, array $b): float
    {
        if ($a === [] && $b === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));

        if ($union === 0) {
            return 0.0;
        }

        return $intersection / $union;
    }

    private function genererRecommandation(float $similarite): string
    {
        if ($similarite >= 0.8) {
            return 'Risque élevé de cannibalisation. Envisager de fusionner ces facettes ou de rediriger l\'une vers l\'autre.';
        }
        if ($similarite >= 0.6) {
            return 'Chevauchement significatif. Différencier le contenu ou filtrer les requêtes communes.';
        }

        return 'Chevauchement modéré à surveiller.';
    }
}
