<?php

declare(strict_types=1);

namespace Facettes\Infrastructure;

/**
 * Charge les variables d'environnement depuis un fichier .env
 */
final class EnvLoader
{
    public function __construct(
        private readonly string $cheminFichier,
    ) {}

    public function charger(): void
    {
        if (!file_exists($this->cheminFichier)) {
            throw new \RuntimeException(
                "Fichier .env introuvable : {$this->cheminFichier}"
            );
        }

        $lignes = file($this->cheminFichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lignes === false) {
            throw new \RuntimeException(
                "Impossible de lire le fichier .env : {$this->cheminFichier}"
            );
        }

        foreach ($lignes as $ligne) {
            $ligne = trim($ligne);

            if ($ligne === '' || str_starts_with($ligne, '#')) {
                continue;
            }

            $positionEgal = strpos($ligne, '=');
            if ($positionEgal === false) {
                continue;
            }

            $cle = trim(substr($ligne, 0, $positionEgal));
            $valeur = trim(substr($ligne, $positionEgal + 1));

            // Retirer les guillemets entourant la valeur
            if (
                (str_starts_with($valeur, '"') && str_ends_with($valeur, '"'))
                || (str_starts_with($valeur, "'") && str_ends_with($valeur, "'"))
            ) {
                $valeur = substr($valeur, 1, -1);
            }

            // Ne pas écraser les valeurs déjà propagées par la plateforme
            if (!getenv($cle)) {
                $_ENV[$cle] = $valeur;
                putenv("{$cle}={$valeur}");
            }
        }
    }

    public static function obtenir(string $cle, string $defaut = ''): string
    {
        return $_ENV[$cle] ?? getenv($cle) ?: $defaut;
    }
}
