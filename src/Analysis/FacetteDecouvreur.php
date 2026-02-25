<?php

declare(strict_types=1);

namespace Facettes\Analysis;

use Facettes\Api\GoogleSuggestClient;
use Facettes\Infrastructure\Logger;

/**
 * Découvre des facettes potentielles non référencées dans le catalogue
 * en utilisant Google Suggest.
 */
final class FacetteDecouvreur
{
    public function __construct(
        private readonly GoogleSuggestClient $suggestClient,
        private readonly Logger $logger,
    ) {}

    /**
     * Découvre des facettes potentielles pour une catégorie/genre.
     *
     * @param array<string, string[]> $facettesExistantes Facettes déjà dans le catalogue
     * @return array<int, array{suggestion: string, source: string}>
     */
    public function decouvrir(string $categorie, string $genre, array $facettesExistantes): array
    {
        $this->logger->info("Découverte de facettes : {$categorie} / {$genre}");

        // Extraire le nom feuille
        $segments = array_map('trim', explode('>', $categorie));
        $nomFeuille = end($segments);

        // Collecter toutes les valeurs existantes pour filtrage
        $valeursExistantes = [];
        foreach ($facettesExistantes as $valeurs) {
            foreach ($valeurs as $valeur) {
                $valeursExistantes[] = mb_strtolower($valeur);
            }
        }

        $suggestions = [];
        $vuesUniques = [];

        // Variantes de requêtes pour la découverte
        $requetesDecouverte = [
            "{$nomFeuille} {$genre}",
            "{$nomFeuille} {$genre} couleur",
            "{$nomFeuille} {$genre} taille",
            "{$nomFeuille} {$genre} style",
            "{$nomFeuille} {$genre} marque",
            "{$nomFeuille} {$genre} matière",
        ];

        foreach ($requetesDecouverte as $requete) {
            $resultats = $this->suggestClient->recupererSuggestions($requete);

            if ($resultats === null) {
                continue;
            }

            foreach ($resultats as $suggestion) {
                $suggestionNormalisee = mb_strtolower(trim($suggestion));

                // Ignorer si déjà vue
                if (isset($vuesUniques[$suggestionNormalisee])) {
                    continue;
                }

                // Ignorer si c'est exactement la requête source
                if ($suggestionNormalisee === mb_strtolower($requete)) {
                    continue;
                }

                // Extraire les mots-clés candidats (différence avec la requête de base)
                $motsCandidats = $this->extraireCandidats($suggestionNormalisee, $nomFeuille, $genre);

                // Vérifier si ces mots sont déjà dans le catalogue
                $estNouveau = true;
                foreach ($motsCandidats as $mot) {
                    if (in_array($mot, $valeursExistantes, true)) {
                        $estNouveau = false;
                        break;
                    }
                }

                if ($estNouveau && $motsCandidats !== []) {
                    $vuesUniques[$suggestionNormalisee] = true;
                    $suggestions[] = [
                        'suggestion' => $suggestion,
                        'source'     => $requete,
                    ];
                }
            }
        }

        $this->logger->info("Découverte terminée : " . count($suggestions) . " suggestions");

        return $suggestions;
    }

    /**
     * Extrait les mots candidats d'une suggestion en retirant la catégorie et le genre.
     *
     * @return string[]
     */
    private function extraireCandidats(string $suggestion, string $categorie, string $genre): array
    {
        $motsRetirer = array_merge(
            explode(' ', mb_strtolower($categorie)),
            explode(' ', mb_strtolower($genre)),
        );

        $motsSuggestion = explode(' ', $suggestion);
        $candidats = [];

        foreach ($motsSuggestion as $mot) {
            $mot = trim($mot);
            if ($mot !== '' && !in_array($mot, $motsRetirer, true)) {
                $candidats[] = $mot;
            }
        }

        return $candidats;
    }
}
