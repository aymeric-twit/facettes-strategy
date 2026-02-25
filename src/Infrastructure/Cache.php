<?php

declare(strict_types=1);

namespace Facettes\Infrastructure;

/**
 * Cache fichier JSON avec TTL configurable.
 * Clé = md5(requête), valeur = JSON sérialisé.
 */
final class Cache
{
    public function __construct(
        private readonly string $repertoire,
        private readonly int $ttl,
        private readonly Logger $logger,
    ) {
        if (!is_dir($this->repertoire)) {
            mkdir($this->repertoire, 0755, true);
        }
    }

    public function obtenir(string $cle): mixed
    {
        $chemin = $this->cheminFichier($cle);

        if (!file_exists($chemin)) {
            return null;
        }

        $contenu = file_get_contents($chemin);
        if ($contenu === false) {
            return null;
        }

        $donnees = json_decode($contenu, true);

        if (!is_array($donnees) || !isset($donnees['expire_a'], $donnees['valeur'])) {
            return null;
        }

        if (time() > $donnees['expire_a']) {
            $this->supprimer($cle);
            $this->logger->debug("Cache expiré pour la clé : {$cle}");
            return null;
        }

        $this->logger->debug("Cache hit pour la clé : {$cle}");
        return $donnees['valeur'];
    }

    public function stocker(string $cle, mixed $valeur): void
    {
        $chemin = $this->cheminFichier($cle);
        $donnees = [
            'cree_a'   => time(),
            'expire_a' => time() + $this->ttl,
            'valeur'   => $valeur,
        ];

        file_put_contents(
            $chemin,
            json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );

        $this->logger->debug("Cache stocké pour la clé : {$cle}");
    }

    public function supprimer(string $cle): void
    {
        $chemin = $this->cheminFichier($cle);
        if (file_exists($chemin)) {
            unlink($chemin);
        }
    }

    public function purgerParPrefixe(string $prefixe): int
    {
        $compteur = 0;
        $fichiers = glob($this->repertoire . '/' . $prefixe . '_*.json');

        if ($fichiers === false) {
            return 0;
        }

        foreach ($fichiers as $fichier) {
            unlink($fichier);
            $compteur++;
        }

        $this->logger->info("Cache purgé : {$compteur} entrées avec préfixe '{$prefixe}'");
        return $compteur;
    }

    public function purgerTout(): int
    {
        $compteur = 0;
        $fichiers = glob($this->repertoire . '/*.json');

        if ($fichiers === false) {
            return 0;
        }

        foreach ($fichiers as $fichier) {
            unlink($fichier);
            $compteur++;
        }

        $this->logger->info("Cache intégralement purgé : {$compteur} entrées");
        return $compteur;
    }

    public function taille(): array
    {
        $fichiers = glob($this->repertoire . '/*.json');
        $nombreFichiers = $fichiers === false ? 0 : count($fichiers);
        $tailleOctets = 0;

        if ($fichiers !== false) {
            foreach ($fichiers as $fichier) {
                $tailleOctets += filesize($fichier) ?: 0;
            }
        }

        return [
            'nombre_entrees' => $nombreFichiers,
            'taille_octets'  => $tailleOctets,
            'taille_lisible' => $this->formaterTaille($tailleOctets),
        ];
    }

    private function cheminFichier(string $cle): string
    {
        $hash = md5($cle);
        return $this->repertoire . '/' . $hash . '.json';
    }

    private function formaterTaille(int $octets): string
    {
        $unites = ['o', 'Ko', 'Mo', 'Go'];
        $index = 0;
        $taille = (float) $octets;

        while ($taille >= 1024 && $index < count($unites) - 1) {
            $taille /= 1024;
            $index++;
        }

        return round($taille, 2) . ' ' . $unites[$index];
    }
}
