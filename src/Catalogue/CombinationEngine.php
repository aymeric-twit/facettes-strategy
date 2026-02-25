<?php

declare(strict_types=1);

namespace Facettes\Catalogue;

use Facettes\Infrastructure\Logger;

/**
 * Génère les requêtes de recherche pour les facettes simples et les combinaisons.
 */
final class CombinationEngine
{
    /** Mots français invariables (ne pas retirer le 's' final) */
    private const array EXCEPTIONS_INVARIABLES = [
        'bras', 'souris', 'corps', 'poids', 'bois', 'mois', 'noix', 'voix',
        'choix', 'prix', 'nez', 'riz', 'tapis', 'avis', 'brebis', 'fois',
        'bas', 'repas', 'matelas', 'tas', 'verglas', 'dos', 'os', 'repos',
        'fils', 'radis', 'paradis', 'permis', 'progrès', 'succès', 'accès',
        'excès', 'décès', 'palais', 'marais', 'relais', 'engrais', 'jus',
        'refus', 'abus', 'talus', 'virus', 'campus', 'consensus', 'processus',
    ];

    /** Exceptions pour -aux → -al (mots où -aux n'est pas le pluriel de -al) */
    private const array EXCEPTIONS_AUX = ['tuyaux', 'noyaux', 'boyaux', 'joyaux'];

    /** Exceptions pour -oux → -ou (mots où le pluriel est en -oux au lieu de -ous) */
    private const array MOTS_OUX = ['bijoux', 'cailloux', 'choux', 'genoux', 'hiboux', 'joujoux', 'poux'];

    public function __construct(
        private readonly Logger $logger,
        private readonly string $formatRequete = '{categorie} {genre} {facettes}',
    ) {}

    /**
     * Singularise un mot français en appliquant des règles morphologiques.
     */
    public function singulariser(string $mot): string
    {
        $motMinuscule = mb_strtolower(trim($mot));

        if ($motMinuscule === '') {
            return $mot;
        }

        // Mots invariables : aucune transformation
        if (in_array($motMinuscule, self::EXCEPTIONS_INVARIABLES, true)) {
            return $motMinuscule;
        }

        // -eaux → -eau (chapeaux → chapeau, gâteaux → gâteau)
        if (str_ends_with($motMinuscule, 'eaux')) {
            return mb_substr($motMinuscule, 0, -1);
        }

        // -aux → -al (animaux → animal, journaux → journal) sauf exceptions
        if (str_ends_with($motMinuscule, 'aux') && !in_array($motMinuscule, self::EXCEPTIONS_AUX, true)) {
            return mb_substr($motMinuscule, 0, -3) . 'al';
        }

        // -oux → -ou (bijoux → bijou, cailloux → caillou) uniquement pour les 7 mots connus
        if (str_ends_with($motMinuscule, 'oux') && in_array($motMinuscule, self::MOTS_OUX, true)) {
            return mb_substr($motMinuscule, 0, -1);
        }

        // -s final → supprimé (robes → robe, pantalons → pantalon)
        if (str_ends_with($motMinuscule, 's') && mb_strlen($motMinuscule) > 2) {
            return mb_substr($motMinuscule, 0, -1);
        }

        return $motMinuscule;
    }

    /**
     * Génère les requêtes pour un type de facette isolé (Niveau 1).
     *
     * @param string[] $valeurs
     * @return array<int, array{valeur: string, requete: string}>
     */
    public function genererRequetesSimples(
        string $categorie,
        string $genre,
        string $typeFacette,
        array $valeurs,
    ): array {
        $requetes = [];

        foreach ($valeurs as $valeur) {
            $requetes[] = [
                'valeur'  => $valeur,
                'requete' => $this->construireRequete($categorie, $genre, [$valeur]),
            ];
        }

        $this->logger->debug("Requêtes simples générées pour {$typeFacette}", [
            'categorie' => $categorie,
            'genre'     => $genre,
            'nombre'    => count($requetes),
        ]);

        return $requetes;
    }

    /**
     * Génère toutes les combinaisons de types de facettes (Niveau 2).
     *
     * @param array<string, string[]> $facettes
     * @return array<string, array<int, array{valeurs: string[], requete: string}>>
     */
    public function genererRequetesCombinaisons(
        string $categorie,
        string $genre,
        array $facettes,
        int $profondeurMax = 2,
    ): array {
        $typesFacettes = array_keys($facettes);
        $combinaisonsTypes = $this->combinerTypes($typesFacettes, $profondeurMax);

        $resultat = [];

        foreach ($combinaisonsTypes as $combinaisonTypes) {
            $cle = implode('+', $combinaisonTypes);
            $valeursParType = array_map(
                fn(string $type): array => $facettes[$type],
                $combinaisonTypes
            );

            $produitCartesien = $this->produitCartesien($valeursParType);

            $requetes = [];
            foreach ($produitCartesien as $combinaisonValeurs) {
                $requetes[] = [
                    'valeurs' => $combinaisonValeurs,
                    'requete' => $this->construireRequete($categorie, $genre, $combinaisonValeurs),
                ];
            }

            $resultat[$cle] = $requetes;

            $this->logger->debug("Requêtes combinées générées pour {$cle}", [
                'categorie' => $categorie,
                'genre'     => $genre,
                'nombre'    => count($requetes),
            ]);
        }

        return $resultat;
    }

    /**
     * Construit la requête de recherche à partir des composants.
     *
     * @param string[] $valeursFacettes
     */
    private function construireRequete(string $categorie, string $genre, array $valeursFacettes): string
    {
        // Extraire le nom feuille du chemin hiérarchique (ex: "vetements > pantalons > shorts" → "shorts")
        $segments = array_map('trim', explode('>', $categorie));
        $nomFeuille = end($segments);

        // Singulariser la catégorie
        $categorieSingulier = $this->singulariser($nomFeuille);

        $facettesStr = implode(' ', array_values($valeursFacettes));

        // Utiliser le format configurable
        $requete = str_replace(
            ['{categorie}', '{genre}', '{facettes}'],
            [$categorieSingulier, $genre, $facettesStr],
            $this->formatRequete,
        );

        return trim($requete);
    }

    /**
     * Génère les combinaisons de types de facettes de taille 2 à profondeurMax.
     *
     * @param string[] $types
     * @return string[][]
     */
    private function combinerTypes(array $types, int $profondeurMax): array
    {
        $combinaisons = [];

        for ($taille = 2; $taille <= min($profondeurMax, count($types)); $taille++) {
            $this->combinaisonsRecursives($types, $taille, 0, [], $combinaisons);
        }

        return $combinaisons;
    }

    /**
     * @param string[] $types
     * @param string[] $courant
     * @param string[][] $resultat
     */
    private function combinaisonsRecursives(
        array $types,
        int $taille,
        int $debut,
        array $courant,
        array &$resultat,
    ): void {
        if (count($courant) === $taille) {
            $resultat[] = $courant;
            return;
        }

        for ($i = $debut; $i < count($types); $i++) {
            $courant[] = $types[$i];
            $this->combinaisonsRecursives($types, $taille, $i + 1, $courant, $resultat);
            array_pop($courant);
        }
    }

    /**
     * Calcule le produit cartésien de plusieurs tableaux de valeurs.
     *
     * @param string[][] $tableaux
     * @return string[][]
     */
    private function produitCartesien(array $tableaux): array
    {
        if ($tableaux === []) {
            return [[]];
        }

        $premier = array_shift($tableaux);
        $reste = $this->produitCartesien($tableaux);

        $resultat = [];
        foreach ($premier as $valeur) {
            foreach ($reste as $combinaison) {
                $resultat[] = [$valeur, ...$combinaison];
            }
        }

        return $resultat;
    }
}
