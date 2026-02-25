<?php

declare(strict_types=1);

namespace Facettes\Api;

use Facettes\Infrastructure\Cache;
use Facettes\Infrastructure\Logger;
use Facettes\Infrastructure\RateLimiter;

/**
 * Client pour l'API SEMrush Keyword Overview.
 * Récupère volume, CPC et Keyword Difficulty pour un mot-clé.
 */
final class SemrushClient
{
    private const string ENDPOINT = 'https://api.semrush.com/';
    private const string NOM_RATE_LIMITER = 'semrush';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $database,
        private readonly Cache $cache,
        private readonly RateLimiter $rateLimiter,
        private readonly Logger $logger,
    ) {
        $this->rateLimiter->enregistrerEndpoint(self::NOM_RATE_LIMITER, 5);
    }

    /**
     * Récupère les métriques SEMrush pour un mot-clé.
     *
     * @return array{volume: int, cpc: float, kd: int}|null
     */
    public function obtenirMetriques(string $motCle): ?array
    {
        $cleCachee = "semrush_{$this->database}_{$motCle}";
        $cache = $this->cache->obtenir($cleCachee);

        if ($cache !== null) {
            /** @var array{volume: int, cpc: float, kd: int} $cache */
            return $cache;
        }

        $this->rateLimiter->attendre(self::NOM_RATE_LIMITER);

        $url = self::ENDPOINT . '?' . http_build_query([
            'type'           => 'phrase_this',
            'key'            => $this->apiKey,
            'phrase'         => $motCle,
            'database'       => $this->database,
            'export_columns' => 'Ph,Nq,Cp,Kd',
        ]);

        $this->logger->debug("Appel SEMrush : {$motCle}");

        $reponse = $this->appelHttp($url);

        if ($reponse === null) {
            $this->logger->error("Échec appel SEMrush pour : {$motCle}");
            return null;
        }

        $metriques = $this->parserReponse($reponse, $motCle);

        if ($metriques !== null) {
            $this->cache->stocker($cleCachee, $metriques);
        }

        return $metriques;
    }

    /**
     * @return array{volume: int, cpc: float, kd: int}|null
     */
    private function parserReponse(string $reponse, string $motCle): ?array
    {
        // Format CSV : en-tête + données sur 2 lignes
        $lignes = explode("\n", trim($reponse));

        if (count($lignes) < 2) {
            $this->logger->warning("Réponse SEMrush vide pour : {$motCle}", [
                'reponse' => substr($reponse, 0, 200),
            ]);
            return null;
        }

        // Vérifier si c'est une erreur SEMrush
        if (str_contains($lignes[0], 'ERROR')) {
            $this->logger->warning("Erreur SEMrush pour : {$motCle}", [
                'erreur' => $lignes[0],
            ]);
            return null;
        }

        $colonnes = str_getcsv($lignes[1], ';');

        if (count($colonnes) < 4) {
            $this->logger->warning("Colonnes insuffisantes pour : {$motCle}");
            return null;
        }

        return [
            'volume' => max(0, (int) $colonnes[1]),
            'cpc'    => max(0.0, (float) $colonnes[2]),
            'kd'     => max(0, (int) $colonnes[3]),
        ];
    }

    private function appelHttp(string $url): ?string
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'FacettesAnalyser/1.0',
        ]);

        $reponse = curl_exec($ch);
        $codeHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erreur = curl_error($ch);

        curl_close($ch);

        if ($reponse === false || $codeHttp !== 200) {
            $this->logger->error("Erreur HTTP SEMrush", [
                'code'   => $codeHttp,
                'erreur' => $erreur,
            ]);
            return null;
        }

        return (string) $reponse;
    }

    /**
     * Teste la connexion à l'API SEMrush.
     */
    public function testerConnexion(): bool
    {
        $resultat = $this->obtenirMetriques('test');
        return $resultat !== null;
    }
}
