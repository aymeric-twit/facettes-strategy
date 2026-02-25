<?php

declare(strict_types=1);

namespace Facettes\Infrastructure;

/**
 * Gère la persistance de l'historique des analyses.
 * Stocke chaque résultat avec un horodatage dans data/historique/.
 */
final class HistoriqueManager
{
    public function __construct(
        private readonly string $repertoireHistorique,
        private readonly Logger $logger,
    ) {
        if (!is_dir($this->repertoireHistorique)) {
            mkdir($this->repertoireHistorique, 0755, true);
        }
    }

    /**
     * Sauvegarde un résultat d'analyse avec horodatage.
     *
     * @param array<string, mixed> $resultats
     */
    public function sauvegarder(string $categorie, string $genre, array $resultats): void
    {
        $dossier = $this->obtenirDossier($categorie, $genre);

        if (!is_dir($dossier)) {
            mkdir($dossier, 0755, true);
        }

        $timestamp = date('Y-m-d_His');
        $chemin = $dossier . '/' . $timestamp . '.json';

        $donnees = [
            'date'      => date('c'),
            'categorie' => $categorie,
            'genre'     => $genre,
            'resultats' => $resultats,
        ];

        $json = json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        file_put_contents($chemin, $json, LOCK_EX);

        $this->logger->info("Historique sauvegardé : {$chemin}");
    }

    /**
     * Récupère le résultat d'analyse le plus récent avant la dernière sauvegarde.
     *
     * @return array<string, mixed>|null
     */
    public function obtenirPrecedent(string $categorie, string $genre): ?array
    {
        $fichiers = $this->listerFichiers($categorie, $genre);

        // Il faut au moins 2 fichiers pour avoir un précédent
        if (count($fichiers) < 2) {
            return null;
        }

        // L'avant-dernier fichier (le dernier est l'analyse en cours)
        $cheminPrecedent = $fichiers[count($fichiers) - 2];

        return $this->lireFichier($cheminPrecedent);
    }

    /**
     * Récupère tous les historiques d'une catégorie/genre.
     *
     * @return array<int, array{date: string, resultats: array<string, mixed>}>
     */
    public function obtenirTout(string $categorie, string $genre): array
    {
        $fichiers = $this->listerFichiers($categorie, $genre);
        $historiques = [];

        foreach ($fichiers as $chemin) {
            $donnees = $this->lireFichier($chemin);
            if ($donnees !== null) {
                $historiques[] = $donnees;
            }
        }

        return $historiques;
    }

    /**
     * Compte le nombre d'analyses historiques.
     */
    public function compterAnalyses(string $categorie, string $genre): int
    {
        return count($this->listerFichiers($categorie, $genre));
    }

    /**
     * @return string[]
     */
    private function listerFichiers(string $categorie, string $genre): array
    {
        $dossier = $this->obtenirDossier($categorie, $genre);

        if (!is_dir($dossier)) {
            return [];
        }

        $fichiers = glob($dossier . '/*.json');

        if ($fichiers === false) {
            return [];
        }

        sort($fichiers);

        return $fichiers;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lireFichier(string $chemin): ?array
    {
        if (!file_exists($chemin)) {
            return null;
        }

        $contenu = file_get_contents($chemin);

        if ($contenu === false) {
            return null;
        }

        $donnees = json_decode($contenu, true);

        return is_array($donnees) ? $donnees : null;
    }

    private function obtenirDossier(string $categorie, string $genre): string
    {
        $categorieSafe = str_replace([' > ', '>', ' ', '/'], ['_', '_', '_', '_'], $categorie);
        $genreSafe = str_replace([' ', '/'], ['_', '_'], $genre);

        return $this->repertoireHistorique . '/' . $categorieSafe . '_' . $genreSafe;
    }
}
