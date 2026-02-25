<?php

declare(strict_types=1);

return [
    'robes' => [
        'genres' => ['femme'],
        'facettes' => [
            'couleur'   => ['blanche', 'noire', 'rouge', 'bleue', 'verte'],
            'matiere'   => ['lin', 'coton', 'soie', 'polyester'],
            'style'     => ['boheme', 'chic', 'casual', 'vintage'],
            'occasion'  => ['mariage', 'soiree', 'quotidien'],
        ],
    ],
    'pantalons' => [
        'genres' => ['femme', 'homme'],
        'facettes' => [
            'couleur'   => ['noir', 'bleu', 'beige', 'gris'],
            'matiere'   => ['jean', 'coton', 'lin', 'velours'],
            'coupe'     => ['slim', 'droit', 'large', 'cargo'],
        ],
    ],
];
