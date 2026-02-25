<?php

declare(strict_types=1);

namespace Facettes\Infrastructure;

/**
 * Logger fichier avec horodatage et niveaux de log
 */
final class Logger
{
    private readonly NiveauLog $niveauMinimum;

    public function __construct(
        private readonly string $cheminFichier,
        string $niveauLog = 'info',
    ) {
        $this->niveauMinimum = NiveauLog::from($niveauLog);

        $repertoire = dirname($this->cheminFichier);
        if (!is_dir($repertoire)) {
            mkdir($repertoire, 0755, true);
        }
    }

    public function debug(string $message, array $contexte = []): void
    {
        $this->ecrire(NiveauLog::DEBUG, $message, $contexte);
    }

    public function info(string $message, array $contexte = []): void
    {
        $this->ecrire(NiveauLog::INFO, $message, $contexte);
    }

    public function warning(string $message, array $contexte = []): void
    {
        $this->ecrire(NiveauLog::WARNING, $message, $contexte);
    }

    public function error(string $message, array $contexte = []): void
    {
        $this->ecrire(NiveauLog::ERROR, $message, $contexte);
    }

    private function ecrire(NiveauLog $niveau, string $message, array $contexte): void
    {
        if ($niveau->priorite() < $this->niveauMinimum->priorite()) {
            return;
        }

        $horodatage = date('Y-m-d H:i:s');
        $niveauTexte = strtoupper($niveau->value);

        $ligneLog = "[{$horodatage}] [{$niveauTexte}] {$message}";

        if ($contexte !== []) {
            $ligneLog .= ' ' . json_encode($contexte, JSON_UNESCAPED_UNICODE);
        }

        $ligneLog .= PHP_EOL;

        file_put_contents($this->cheminFichier, $ligneLog, FILE_APPEND | LOCK_EX);
    }
}
