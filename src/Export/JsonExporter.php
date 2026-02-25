<?php

declare(strict_types=1);

namespace Facettes\Export;

/**
 * Exporte les résultats d'analyse en JSON.
 */
final class JsonExporter
{
    public function __construct(
        private readonly string $repertoireExport,
        private readonly RecommandationGenerateur $recommandeur = new RecommandationGenerateur(),
    ) {
        if (!is_dir($this->repertoireExport)) {
            mkdir($this->repertoireExport, 0755, true);
        }
    }

    /**
     * @param array<string, mixed> $resultats
     */
    public function exporter(array $resultats, string $categorie, string $genre): string
    {
        $categorieFichier = str_replace([' > ', '>', ' '], ['_', '_', '_'], $categorie);
        $nomFichier = "facettes_{$categorieFichier}_{$genre}_" . date('Y-m-d_His') . '.json';
        $chemin = $this->repertoireExport . '/' . $nomFichier;

        $donnees = $this->enrichirAvecRecommandations([
            'generated_at'     => date('c'),
            'categorie'        => $categorie,
            'genre'            => $genre,
            'facettes_simples' => $resultats['facettes_simples'] ?? [],
            'combinaisons'     => $resultats['combinaisons'] ?? [],
            'cannibalisation'  => $resultats['cannibalisation'] ?? [],
        ]);

        $json = json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        file_put_contents($chemin, $json, LOCK_EX);

        return $chemin;
    }

    /**
     * Retourne le JSON en string sans écrire de fichier.
     *
     * @param array<string, mixed> $resultats
     */
    public function versJson(array $resultats, string $categorie, string $genre): string
    {
        $donnees = $this->enrichirAvecRecommandations([
            'generated_at'     => date('c'),
            'categorie'        => $categorie,
            'genre'            => $genre,
            'facettes_simples' => $resultats['facettes_simples'] ?? [],
            'combinaisons'     => $resultats['combinaisons'] ?? [],
            'cannibalisation'  => $resultats['cannibalisation'] ?? [],
        ]);

        return json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $donnees
     * @return array<string, mixed>
     */
    private function enrichirAvecRecommandations(array $donnees): array
    {
        foreach (['facettes_simples', 'combinaisons'] as $niveau) {
            if (!isset($donnees[$niveau]) || !is_array($donnees[$niveau])) {
                continue;
            }

            foreach ($donnees[$niveau] as $nom => &$facette) {
                $facette['recommandation'] = $this->recommandeur->generer($facette);
            }
            unset($facette);
        }

        return $donnees;
    }
}
