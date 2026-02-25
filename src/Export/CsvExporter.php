<?php

declare(strict_types=1);

namespace Facettes\Export;

/**
 * Exporte les résultats d'analyse en CSV.
 */
final class CsvExporter
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
        $nomFichier = "facettes_{$categorieFichier}_{$genre}_" . date('Y-m-d_His') . '.csv';
        $chemin = $this->repertoireExport . '/' . $nomFichier;

        $handle = fopen($chemin, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Impossible de créer le fichier : {$chemin}");
        }

        // BOM UTF-8 pour Excel
        fwrite($handle, "\xEF\xBB\xBF");

        // En-tête
        fputcsv($handle, [
            'Niveau', 'Type/Combinaison', 'Décision', 'Score', 'Score continu',
            'Zone', 'Volume total', 'Volume médian', 'Taux Suggest',
            'CPC moyen', 'KD moyen', 'Recommandation',
        ], ';');

        // Facettes simples
        if (isset($resultats['facettes_simples'])) {
            foreach ($resultats['facettes_simples'] as $type => $donnees) {
                fputcsv($handle, [
                    'Simple',
                    $type,
                    strtoupper($donnees['decision']),
                    $donnees['score'],
                    $donnees['score_continu'] ?? '',
                    $donnees['zone'] ?? '',
                    $donnees['metriques']['volume_total'],
                    $donnees['metriques']['volume_median'],
                    number_format($donnees['metriques']['taux_suggest'] * 100, 1) . '%',
                    number_format($donnees['metriques']['cpc_moyen'], 2) . '€',
                    $donnees['metriques']['kd_moyen'],
                    $this->recommandeur->generer($donnees),
                ], ';');
            }
        }

        // Ligne vide de séparation
        fputcsv($handle, [], ';');

        // Combinaisons
        if (isset($resultats['combinaisons'])) {
            foreach ($resultats['combinaisons'] as $combinaison => $donnees) {
                fputcsv($handle, [
                    'Combinaison',
                    $combinaison,
                    strtoupper($donnees['decision']),
                    $donnees['score'],
                    $donnees['score_continu'] ?? '',
                    $donnees['zone'] ?? '',
                    $donnees['metriques']['volume_total'],
                    $donnees['metriques']['volume_median'],
                    number_format($donnees['metriques']['taux_suggest'] * 100, 1) . '%',
                    number_format($donnees['metriques']['cpc_moyen'], 2) . '€',
                    $donnees['metriques']['kd_moyen'],
                    $this->recommandeur->generer($donnees),
                ], ';');
            }
        }

        // Détail valeurs
        fputcsv($handle, [], ';');
        fputcsv($handle, ['--- DÉTAIL VALEURS ---'], ';');
        fputcsv($handle, [
            'Type', 'Valeur(s)', 'Requête', 'Volume', 'Suggest', 'CPC', 'KD',
        ], ';');

        if (isset($resultats['facettes_simples'])) {
            foreach ($resultats['facettes_simples'] as $type => $donnees) {
                foreach ($donnees['detail_valeurs'] as $detail) {
                    fputcsv($handle, [
                        $type,
                        $detail['valeur'],
                        $detail['requete'],
                        $detail['volume'],
                        $detail['suggest'] ? 'Oui' : 'Non',
                        number_format($detail['cpc'], 2) . '€',
                        $detail['kd'],
                    ], ';');
                }
            }
        }

        if (isset($resultats['combinaisons'])) {
            foreach ($resultats['combinaisons'] as $combinaison => $donnees) {
                foreach ($donnees['detail_valeurs'] as $detail) {
                    fputcsv($handle, [
                        $combinaison,
                        implode(' + ', $detail['valeurs']),
                        $detail['requete'],
                        $detail['volume'],
                        $detail['suggest'] ? 'Oui' : 'Non',
                        number_format($detail['cpc'], 2) . '€',
                        $detail['kd'],
                    ], ';');
                }
            }
        }

        fclose($handle);

        return $chemin;
    }
}
