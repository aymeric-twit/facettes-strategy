<?php

declare(strict_types=1);

namespace Facettes\Infrastructure;

/**
 * Token bucket rate limiter par client API.
 * Bloque (usleep) si le débit est dépassé.
 */
final class RateLimiter
{
    /** @var array<string, float> Dernier timestamp d'appel par endpoint */
    private array $derniersAppels = [];

    /** @var array<string, float> Tokens restants par endpoint */
    private array $tokens = [];

    /** @var array<string, float> Taux max par endpoint (requêtes/seconde) */
    private array $tauxMax = [];

    public function enregistrerEndpoint(string $nom, float $requetesParSeconde): void
    {
        $this->tauxMax[$nom] = $requetesParSeconde;
        $this->tokens[$nom] = $requetesParSeconde;
        $this->derniersAppels[$nom] = microtime(true);
    }

    public function attendre(string $nom): void
    {
        if (!isset($this->tauxMax[$nom])) {
            throw new \RuntimeException("Endpoint non enregistré : {$nom}");
        }

        $maintenant = microtime(true);
        $ecart = $maintenant - $this->derniersAppels[$nom];
        $tauxMax = $this->tauxMax[$nom];

        // Recharger les tokens proportionnellement au temps écoulé
        $this->tokens[$nom] = min(
            $tauxMax,
            $this->tokens[$nom] + $ecart * $tauxMax
        );

        if ($this->tokens[$nom] < 1.0) {
            $attenteSecondes = (1.0 - $this->tokens[$nom]) / $tauxMax;
            usleep((int) ($attenteSecondes * 1_000_000));
            $this->tokens[$nom] = 0.0;
        } else {
            $this->tokens[$nom] -= 1.0;
        }

        $this->derniersAppels[$nom] = microtime(true);
    }
}
