<?php

declare(strict_types=1);

namespace Facettes\Catalogue;

use Facettes\Infrastructure\Logger;

/**
 * Charge et valide le catalogue de facettes.
 * Priorité au JSON, fallback sur le PHP avec auto-migration.
 * Supporte les catégories hiérarchiques multi-niveaux via sous_categories.
 */
final class CatalogueParser
{
    /** @var array<string, mixed> */
    private array $catalogue = [];

    public function __construct(
        private readonly string $cheminPhp,
        private readonly string $cheminJson,
        private readonly Logger $logger,
    ) {}

    /**
     * Charge le catalogue : JSON en priorité, sinon PHP avec auto-migration.
     *
     * @return array<string, mixed>
     */
    public function charger(): array
    {
        if (file_exists($this->cheminJson)) {
            $contenu = file_get_contents($this->cheminJson);

            if ($contenu === false) {
                throw new \RuntimeException(
                    "Impossible de lire le fichier JSON : {$this->cheminJson}"
                );
            }

            /** @var array<string, mixed>|null $catalogue */
            $catalogue = json_decode($contenu, true);

            if (!is_array($catalogue) || $catalogue === []) {
                throw new \RuntimeException('Le fichier JSON du catalogue est vide ou invalide.');
            }

            $this->valider($catalogue);
            $this->catalogue = $catalogue;

            $this->logger->info('Catalogue chargé depuis JSON', [
                'categories' => count($this->obtenirCategories()),
            ]);

            return $this->catalogue;
        }

        if (!file_exists($this->cheminPhp)) {
            throw new \RuntimeException(
                "Aucun fichier catalogue trouvé (ni JSON ni PHP)."
            );
        }

        /** @var array<string, mixed> $catalogue */
        $catalogue = require $this->cheminPhp;

        if (!is_array($catalogue) || $catalogue === []) {
            throw new \RuntimeException('Le catalogue PHP est vide ou invalide.');
        }

        $this->valider($catalogue);
        $this->catalogue = $catalogue;

        $this->logger->info('Catalogue chargé depuis PHP, auto-migration JSON', [
            'categories' => count($this->obtenirCategories()),
        ]);

        $this->ecrireJson($catalogue);

        return $this->catalogue;
    }

    /**
     * Sauvegarde le catalogue complet dans le fichier JSON.
     *
     * @param array<string, mixed> $catalogue
     */
    public function sauvegarder(array $catalogue): void
    {
        $this->valider($catalogue);
        $this->ecrireJson($catalogue);
        $this->catalogue = $catalogue;

        $this->logger->info('Catalogue sauvegardé', [
            'categories' => count($this->collecterChemins($catalogue, '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenirCatalogue(): array
    {
        if ($this->catalogue === []) {
            $this->charger();
        }

        return $this->catalogue;
    }

    /**
     * Retourne tous les chemins de catégories récursivement.
     * Ex: ["vetements", "vetements > pantalons", "vetements > pantalons > shorts", "vetements > robes"]
     *
     * @return string[]
     */
    public function obtenirCategories(): array
    {
        return $this->collecterChemins($this->obtenirCatalogue(), '');
    }

    /**
     * Résout un chemin hiérarchique (ex: "vetements > pantalons > shorts") en noeud du catalogue.
     *
     * @return array{genres: string[], facettes: array<string, string[]>, sous_categories?: array<string, mixed>}
     */
    public function resoudreNoeud(string $chemin): array
    {
        $segments = array_map('trim', explode('>', $chemin));
        $catalogue = $this->obtenirCatalogue();

        $noeud = null;
        foreach ($segments as $index => $segment) {
            if ($index === 0) {
                if (!isset($catalogue[$segment])) {
                    throw new \InvalidArgumentException("Catégorie inconnue : {$segment} (chemin: {$chemin})");
                }
                $noeud = $catalogue[$segment];
            } else {
                if (!isset($noeud['sous_categories'][$segment])) {
                    throw new \InvalidArgumentException(
                        "Sous-catégorie inconnue : {$segment} (chemin: {$chemin})"
                    );
                }
                $noeud = $noeud['sous_categories'][$segment];
            }
        }

        if ($noeud === null) {
            throw new \InvalidArgumentException("Chemin vide.");
        }

        /** @var array{genres: string[], facettes: array<string, string[]>, sous_categories?: array<string, mixed>} $noeud */
        return $noeud;
    }

    /**
     * @return string[]
     */
    public function obtenirGenres(string $categorie): array
    {
        $noeud = $this->resoudreNoeud($categorie);

        return $noeud['genres'];
    }

    /**
     * @return array<string, string[]>
     */
    public function obtenirFacettes(string $categorie): array
    {
        $noeud = $this->resoudreNoeud($categorie);

        return $noeud['facettes'];
    }

    /**
     * Retourne le dernier segment du chemin (nom feuille).
     * Ex: "vetements > pantalons > shorts" → "shorts"
     */
    public function obtenirNomFeuille(string $chemin): string
    {
        $segments = array_map('trim', explode('>', $chemin));

        return end($segments) ?: $chemin;
    }

    /**
     * Retourne la profondeur du chemin (nombre de ">").
     * Ex: "vetements" → 0, "vetements > pantalons" → 1
     */
    public function obtenirProfondeur(string $chemin): int
    {
        return substr_count($chemin, '>');
    }

    /**
     * @param array<string, mixed> $catalogue
     */
    private function valider(array $catalogue): void
    {
        foreach ($catalogue as $nomCategorie => $categorie) {
            $this->validerNoeud((string) $nomCategorie, $categorie);
        }

        $this->logger->debug('Validation du catalogue réussie.');
    }

    /**
     * Valide récursivement un noeud du catalogue.
     */
    private function validerNoeud(string $chemin, mixed $noeud): void
    {
        if (!is_array($noeud)) {
            throw new \RuntimeException("'{$chemin}' : noeud invalide (non-tableau).");
        }

        if ($chemin === '') {
            throw new \RuntimeException('Nom de catégorie invalide (vide).');
        }

        if (!isset($noeud['genres']) || !is_array($noeud['genres']) || $noeud['genres'] === []) {
            throw new \RuntimeException(
                "Catégorie '{$chemin}' : genres manquants ou vides."
            );
        }

        if (!isset($noeud['facettes']) || !is_array($noeud['facettes']) || $noeud['facettes'] === []) {
            throw new \RuntimeException(
                "Catégorie '{$chemin}' : facettes manquantes ou vides."
            );
        }

        foreach ($noeud['facettes'] as $nomFacette => $valeurs) {
            if (!is_string($nomFacette) || $nomFacette === '') {
                throw new \RuntimeException(
                    "Catégorie '{$chemin}' : nom de facette invalide."
                );
            }

            if (!is_array($valeurs) || $valeurs === []) {
                throw new \RuntimeException(
                    "Catégorie '{$chemin}', facette '{$nomFacette}' : aucune valeur définie."
                );
            }
        }

        // Valider récursivement les sous-catégories
        if (isset($noeud['sous_categories']) && is_array($noeud['sous_categories'])) {
            foreach ($noeud['sous_categories'] as $nomSous => $sousNoeud) {
                $this->validerNoeud("{$chemin} > {$nomSous}", $sousNoeud);
            }
        }
    }

    /**
     * Collecte récursivement tous les chemins de catégories.
     *
     * @param array<string, mixed> $noeuds
     * @return string[]
     */
    private function collecterChemins(array $noeuds, string $prefixe): array
    {
        $chemins = [];

        foreach ($noeuds as $nom => $noeud) {
            if (!is_array($noeud) || !isset($noeud['genres'])) {
                continue;
            }

            $chemin = $prefixe === '' ? (string) $nom : "{$prefixe} > {$nom}";
            $chemins[] = $chemin;

            if (isset($noeud['sous_categories']) && is_array($noeud['sous_categories'])) {
                $chemins = array_merge($chemins, $this->collecterChemins($noeud['sous_categories'], $chemin));
            }
        }

        return $chemins;
    }

    /**
     * @param array<string, mixed> $catalogue
     */
    private function ecrireJson(array $catalogue): void
    {
        $json = json_encode($catalogue, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if ($json === false) {
            throw new \RuntimeException('Impossible d\'encoder le catalogue en JSON.');
        }

        $resultat = file_put_contents($this->cheminJson, $json, LOCK_EX);

        if ($resultat === false) {
            throw new \RuntimeException(
                "Impossible d'écrire le fichier JSON : {$this->cheminJson}"
            );
        }
    }
}
