<?php

declare(strict_types=1);

namespace Facettes\Catalogue;

/**
 * Gère les sélections de facettes par combinaison (catégorie + genre).
 * Stocke dans config/selections.json, séparé du catalogue.
 */
final class SelectionsManager
{
    private const FICHIER = __DIR__ . '/../../config/selections.json';

    /**
     * Charge les sélections depuis le fichier JSON.
     *
     * @return array<string, array<string, array<string, string[]>>>
     */
    public function charger(): array
    {
        if (!file_exists(self::FICHIER)) {
            return [];
        }

        $contenu = file_get_contents(self::FICHIER);

        if ($contenu === false || $contenu === '') {
            return [];
        }

        /** @var array<string, array<string, array<string, string[]>>>|null $selections */
        $selections = json_decode($contenu, true);

        if (!is_array($selections)) {
            return [];
        }

        return $selections;
    }

    /**
     * Sauvegarde les sélections dans le fichier JSON.
     *
     * @param array<string, array<string, array<string, string[]>>> $selections
     */
    public function sauvegarder(array $selections): void
    {
        $this->valider($selections);

        $json = json_encode($selections, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if ($json === false) {
            throw new \RuntimeException("Impossible d'encoder les sélections en JSON.");
        }

        $resultat = file_put_contents(self::FICHIER, $json, LOCK_EX);

        if ($resultat === false) {
            throw new \RuntimeException("Impossible d'écrire le fichier de sélections.");
        }
    }

    /**
     * Filtre les facettes du catalogue selon les sélections enregistrées.
     *
     * Si aucune sélection n'existe pour la combinaison catégorie+genre,
     * retourne toutes les facettes (comportement par défaut = tout activé).
     *
     * @param string $categorie Chemin de la catégorie (ex: "robes" ou "robes > evasees")
     * @param string $genre Genre (ex: "femme")
     * @param array<string, string[]> $facettesCompletes Facettes complètes du catalogue
     * @return array<string, string[]> Facettes filtrées
     */
    public function obtenirFacettesFiltrees(
        string $categorie,
        string $genre,
        array $facettesCompletes,
    ): array {
        $selections = $this->charger();

        if (!isset($selections[$categorie][$genre])) {
            return $facettesCompletes;
        }

        $selectionCombinaison = $selections[$categorie][$genre];
        $facettesFiltrees = [];

        foreach ($facettesCompletes as $type => $valeurs) {
            if (!isset($selectionCombinaison[$type])) {
                // Type non présent dans la sélection → on l'ignore
                continue;
            }

            // Intersection : garder uniquement les valeurs sélectionnées ET présentes dans le catalogue
            $valeursFiltrees = array_values(
                array_intersect($valeurs, $selectionCombinaison[$type]),
            );

            if ($valeursFiltrees !== []) {
                $facettesFiltrees[$type] = $valeursFiltrees;
            }
        }

        // Si le filtrage donne un résultat vide, retourner tout (sécurité)
        return $facettesFiltrees !== [] ? $facettesFiltrees : $facettesCompletes;
    }

    /**
     * Valide la structure des sélections.
     *
     * @param array<string, mixed> $selections
     */
    private function valider(array $selections): void
    {
        foreach ($selections as $categorie => $genres) {
            if (!is_string($categorie) || $categorie === '') {
                throw new \InvalidArgumentException("Nom de catégorie invalide dans les sélections.");
            }

            if (!is_array($genres)) {
                throw new \InvalidArgumentException(
                    "Structure invalide pour la catégorie '{$categorie}' : genres attendu comme tableau.",
                );
            }

            foreach ($genres as $genre => $types) {
                if (!is_string($genre) || $genre === '') {
                    throw new \InvalidArgumentException(
                        "Nom de genre invalide dans les sélections (catégorie: {$categorie}).",
                    );
                }

                if (!is_array($types)) {
                    throw new \InvalidArgumentException(
                        "Structure invalide pour {$categorie}/{$genre} : types attendu comme tableau.",
                    );
                }

                foreach ($types as $type => $valeurs) {
                    if (!is_string($type) || $type === '') {
                        throw new \InvalidArgumentException(
                            "Nom de type invalide dans les sélections ({$categorie}/{$genre}).",
                        );
                    }

                    if (!is_array($valeurs)) {
                        throw new \InvalidArgumentException(
                            "Valeurs invalides pour {$categorie}/{$genre}/{$type} : tableau attendu.",
                        );
                    }

                    foreach ($valeurs as $valeur) {
                        if (!is_string($valeur)) {
                            throw new \InvalidArgumentException(
                                "Valeur non-string dans {$categorie}/{$genre}/{$type}.",
                            );
                        }
                    }
                }
            }
        }
    }
}
