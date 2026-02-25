<?php

declare(strict_types=1);

return [
    'profondeur_max_combinaison' => 2,
    'seuils' => [
        'simple' => [
            'volume_total_min'  => 500,
            'volume_median_min' => 50,
            'taux_suggest_min'  => 0.30,
            'cpc_moyen_min'     => 0.20,
        ],
        'combinaison' => [
            'volume_total_min'  => 200,
            'volume_median_min' => 20,
            'taux_suggest_min'  => 0.15,
            'cpc_moyen_min'     => 0.10,
        ],
    ],
    'scoring' => [
        'poids' => [
            'volume'  => 35,
            'suggest' => 25,
            'cpc'     => 20,
            'kd'      => 20,
        ],
        'plafonds' => [
            'volume' => 5000,
            'cpc'    => 3.0,
            'kd'     => 100,
        ],
        'seuil_index' => 55,
    ],
    'zones' => [
        'seuil_score_haut'  => 55,
        'seuil_score_bas'   => 30,
        'seuil_kd_facile'   => 40,
        'seuil_kd_niche'    => 30,
    ],
    'cannibalisation' => [
        'seuil_jaccard' => 0.6,
    ],
    'format_requete' => '{categorie} {genre} {facettes}',
    'taille_batch' => 50,
    'cache_ttl' => 86400 * 7,
    'semrush' => [
        'database' => 'fr',
        'requests_per_second' => 5,
    ],
    'google_suggest' => [
        'lang' => 'fr',
        'country' => 'fr',
        'requests_per_second' => 2,
    ],
    'log_level' => 'info',
];
