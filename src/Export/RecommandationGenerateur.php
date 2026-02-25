<?php

declare(strict_types=1);

namespace Facettes\Export;

/**
 * Génère des recommandations textuelles actionnables basées sur la zone et les métriques.
 */
final class RecommandationGenerateur
{
    /**
     * Génère une recommandation textuelle pour une facette.
     *
     * @param array<string, mixed> $donnees Données complètes de la facette
     */
    public function generer(array $donnees): string
    {
        $zone = $donnees['zone'] ?? 'IGNORER';
        $volume = (int) ($donnees['metriques']['volume_total'] ?? 0);
        $kd = (float) ($donnees['metriques']['kd_moyen'] ?? 0);
        $cpc = (float) ($donnees['metriques']['cpc_moyen'] ?? 0);
        $score = (float) ($donnees['score_continu'] ?? 0);

        $volumeFormate = number_format($volume, 0, ',', ' ');
        $kdFormate = number_format($kd, 0);

        return match ($zone) {
            'QUICK_WIN' => "Indexer en priorité. Volume {$volumeFormate}/mois, KD faible ({$kdFormate}). Optimiser la balise title et le contenu de la page facette.",
            'FORT_POTENTIEL' => "Indexer avec stratégie de contenu renforcé (KD élevé : {$kdFormate}). Renforcer le maillage interne et envisager du contenu éditorial de soutien.",
            'NICHE' => "Potentiel limité mais accessible (KD {$kdFormate}). Indexer si le coût de création/maintenance est faible. Volume : {$volumeFormate}/mois.",
            'SURVEILLER' => "Zone intermédiaire (score {$score}/100). Surveiller l'évolution du volume et de la concurrence avant d'investir.",
            default => "Ne pas indexer. Volume insuffisant ({$volumeFormate}/mois) et/ou score trop faible ({$score}/100).",
        };
    }
}
