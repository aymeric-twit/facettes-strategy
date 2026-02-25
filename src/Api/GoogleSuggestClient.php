<?php

declare(strict_types=1);

namespace Facettes\Api;

use Facettes\Infrastructure\Cache;
use Facettes\Infrastructure\Logger;
use Facettes\Infrastructure\RateLimiter;

/**
 * Client pour l'API Google Suggest (autocomplete).
 * Vérifie si un mot-clé apparaît dans les suggestions Google.
 */
final class GoogleSuggestClient
{
    private const string ENDPOINT = 'https://suggestqueries.google.com/complete/search';
    private const string NOM_RATE_LIMITER = 'google_suggest';

    public function __construct(
        private readonly string $langue,
        private readonly string $pays,
        private readonly Cache $cache,
        private readonly RateLimiter $rateLimiter,
        private readonly Logger $logger,
    ) {
        $this->rateLimiter->enregistrerEndpoint(self::NOM_RATE_LIMITER, 2);
    }

    /**
     * Vérifie si le mot-clé apparaît dans les suggestions Google.
     */
    public function estDansSuggest(string $motCle): bool
    {
        $cleCachee = "suggest_{$this->langue}_{$this->pays}_{$motCle}";
        $cache = $this->cache->obtenir($cleCachee);

        if ($cache !== null) {
            return (bool) $cache;
        }

        $this->rateLimiter->attendre(self::NOM_RATE_LIMITER);

        $suggestions = $this->recupererSuggestions($motCle);

        if ($suggestions === null) {
            $this->logger->error("Échec Google Suggest pour : {$motCle}");
            return false;
        }

        $motCleNormalise = $this->normaliser($motCle);
        $present = false;

        foreach ($suggestions as $suggestion) {
            if ($this->normaliser($suggestion) === $motCleNormalise) {
                $present = true;
                break;
            }
        }

        $this->cache->stocker($cleCachee, $present);

        $this->logger->debug("Google Suggest : '{$motCle}' → " . ($present ? 'oui' : 'non'));

        return $present;
    }

    /**
     * @return string[]|null
     */
    public function recupererSuggestions(string $motCle): ?array
    {
        $url = self::ENDPOINT . '?' . http_build_query([
            'client' => 'firefox',
            'q'      => $motCle,
            'hl'     => $this->langue,
            'gl'     => $this->pays,
        ]);

        $reponse = $this->appelHttp($url);

        if ($reponse === null) {
            return null;
        }

        $donnees = json_decode($reponse, true);

        // Format Firefox : [requête, [suggestions...]]
        if (!is_array($donnees) || count($donnees) < 2 || !is_array($donnees[1])) {
            $this->logger->warning("Réponse Google Suggest invalide pour : {$motCle}");
            return null;
        }

        /** @var string[] */
        return $donnees[1];
    }

    private function normaliser(string $texte): string
    {
        return mb_strtolower(trim($texte));
    }

    private function appelHttp(string $url): ?string
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; FacettesAnalyser/1.0)',
        ]);

        $reponse = curl_exec($ch);
        $codeHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erreur = curl_error($ch);

        curl_close($ch);

        if ($reponse === false || $codeHttp !== 200) {
            $this->logger->error("Erreur HTTP Google Suggest", [
                'code'   => $codeHttp,
                'erreur' => $erreur,
            ]);
            return null;
        }

        return (string) $reponse;
    }
}
