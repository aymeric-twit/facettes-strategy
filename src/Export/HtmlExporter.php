<?php

declare(strict_types=1);

namespace Facettes\Export;

/**
 * Exporte les résultats d'analyse en page HTML statique autonome.
 */
final class HtmlExporter
{
    private const array COULEURS_ZONES = [
        'QUICK_WIN'      => '#2e7d32',
        'FORT_POTENTIEL' => '#1565c0',
        'NICHE'          => '#f9a825',
        'SURVEILLER'     => '#ef6c00',
        'IGNORER'        => '#757575',
    ];

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
        $nomFichier = "facettes_{$categorieFichier}_{$genre}_" . date('Y-m-d_His') . '.html';
        $chemin = $this->repertoireExport . '/' . $nomFichier;

        $html = $this->genererHtml($resultats, $categorie, $genre);
        file_put_contents($chemin, $html, LOCK_EX);

        return $chemin;
    }

    /**
     * @param array<string, mixed> $resultats
     */
    private function genererHtml(array $resultats, string $categorie, string $genre): string
    {
        $date = date('d/m/Y à H:i');
        $categorieAffichee = implode(' &rsaquo; ', array_map(
            fn(string $s): string => ucfirst(trim($s)),
            explode('>', $categorie)
        ));

        $tableauSimples = $this->genererTableau($resultats['facettes_simples'] ?? [], 'Type');
        $tableauCombinaisons = $this->genererTableau($resultats['combinaisons'] ?? [], 'Combinaison');
        $sectionCannibalisation = $this->genererCannibalisation($resultats['cannibalisation'] ?? []);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Analyse Facettes — {$categorieAffichee} / {$genre}</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f5; color: #333; padding: 2rem; }
                h1 { color: #004c4c; margin-bottom: 0.5rem; }
                h2 { color: #333; margin: 2rem 0 1rem; border-bottom: 2px solid #004c4c; padding-bottom: 0.5rem; }
                .meta { color: #666; margin-bottom: 2rem; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                th { background: #f1f5f9; padding: 12px; text-align: left; font-weight: 600; font-size: 14px; color: #0f172a; }
                td { padding: 10px 12px; border-top: 1px solid #e2e8f0; font-size: 14px; }
                .index { background: #e8f5e9; }
                .noindex { background: #ffebee; }
                .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
                .badge-index { background: #2e7d32; color: #fff; }
                .badge-noindex { background: #c62828; color: #fff; }
                .badge-zone { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; color: #fff; text-transform: uppercase; }
                .recommandation { font-size: 13px; color: #555; font-style: italic; max-width: 350px; }
                .alerte { background: #fff3e0; border: 1px solid #ef6c00; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
                .alerte-titre { font-weight: 700; color: #ef6c00; }
            </style>
        </head>
        <body>
            <h1>Analyse des Facettes</h1>
            <p class="meta">{$categorieAffichee} / {$genre} — Généré le {$date}</p>

            <h2>Niveau 1 — Facettes simples</h2>
            {$tableauSimples}

            <h2>Niveau 2 — Combinaisons</h2>
            {$tableauCombinaisons}

            {$sectionCannibalisation}
        </body>
        </html>
        HTML;
    }

    /**
     * @param array<string, mixed> $facettes
     */
    private function genererTableau(array $facettes, string $labelPremierCol): string
    {
        if ($facettes === []) {
            return '<p>Aucune donnée disponible.</p>';
        }

        $lignes = '';
        foreach ($facettes as $nom => $donnees) {
            $classeDecision = $donnees['decision'] === 'index' ? 'index' : 'noindex';
            $badgeClasse = $donnees['decision'] === 'index' ? 'badge-index' : 'badge-noindex';
            $tauxPct = number_format($donnees['metriques']['taux_suggest'] * 100, 1);
            $zone = $donnees['zone'] ?? 'IGNORER';
            $couleurZone = self::COULEURS_ZONES[$zone] ?? '#757575';
            $recommandation = htmlspecialchars($this->recommandeur->generer($donnees));

            $lignes .= <<<HTML
                <tr class="{$classeDecision}">
                    <td><strong>{$nom}</strong></td>
                    <td><span class="badge {$badgeClasse}">{$donnees['decision']}</span></td>
                    <td>{$donnees['score']}</td>
                    <td><span class="badge-zone" style="background: {$couleurZone}">{$zone}</span></td>
                    <td>{$donnees['metriques']['volume_total']}</td>
                    <td>{$donnees['metriques']['volume_median']}</td>
                    <td>{$tauxPct}%</td>
                    <td>{$donnees['metriques']['cpc_moyen']}€</td>
                    <td>{$donnees['metriques']['kd_moyen']}</td>
                    <td class="recommandation">{$recommandation}</td>
                </tr>
            HTML;
        }

        return <<<HTML
            <table>
                <thead>
                    <tr>
                        <th>{$labelPremierCol}</th><th>Décision</th><th>Score</th><th>Zone</th>
                        <th>Volume total</th><th>Volume médian</th><th>Taux Suggest</th>
                        <th>CPC moyen</th><th>KD moyen</th><th>Recommandation</th>
                    </tr>
                </thead>
                <tbody>{$lignes}</tbody>
            </table>
        HTML;
    }

    /**
     * @param array<int, mixed> $alertes
     */
    private function genererCannibalisation(array $alertes): string
    {
        if ($alertes === []) {
            return '';
        }

        $html = '<h2>Alertes de cannibalisation</h2>';

        foreach ($alertes as $alerte) {
            $similaritePct = number_format(($alerte['similarite'] ?? 0) * 100, 0);
            $facetteA = htmlspecialchars($alerte['facette_a'] ?? '');
            $facetteB = htmlspecialchars($alerte['facette_b'] ?? '');
            $recommandation = htmlspecialchars($alerte['recommandation'] ?? '');

            $html .= <<<HTML
                <div class="alerte">
                    <p class="alerte-titre">⚠ {$facetteA} ↔ {$facetteB} — Similarité : {$similaritePct}%</p>
                    <p>{$recommandation}</p>
                </div>
            HTML;
        }

        return $html;
    }
}
