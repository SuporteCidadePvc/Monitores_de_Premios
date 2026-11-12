<?php

return [
    'sector_rule_keys' => [
        'pvc' => 'production_prize_v1',
        'aluminio' => 'aluminium_prize_v1',
    ],

    'rule_sets' => [
        'production_prize_v1' => [
            'version' => 'premio-producao-v1',
            'currency' => 'EUR',
            'tiers' => [
                ['minimum_squares' => 2300, 'pool_cents' => 80000],
                ['minimum_squares' => 2530, 'pool_cents' => 120000],
                ['minimum_squares' => 2760, 'pool_cents' => 180000],
                ['minimum_squares' => 2990, 'pool_cents' => 270000],
                ['minimum_squares' => 3220, 'pool_cents' => 400000],
                ['minimum_squares' => 3450, 'pool_cents' => 570000],
                ['minimum_squares' => 3680, 'pool_cents' => 800000],
            ],
        ],
        'aluminium_prize_v1' => [
            'version' => 'premio-producao-aluminio-v1',
            'currency' => 'EUR',
            'tiers' => [
                ['minimum_squares' => 400, 'pool_cents' => 16000],
                ['minimum_squares' => 460, 'pool_cents' => 24000],
                ['minimum_squares' => 530, 'pool_cents' => 36000],
                ['minimum_squares' => 600, 'pool_cents' => 54000],
                ['minimum_squares' => 670, 'pool_cents' => 80000],
                ['minimum_squares' => 730, 'pool_cents' => 114000],
                ['minimum_squares' => 800, 'pool_cents' => 160000],
            ],
        ],
    ],
];
