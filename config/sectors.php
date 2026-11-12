<?php

return [
    'screens' => [
        'principal' => [
            'path' => '',
            'label' => 'Painel principal',
        ],
        'hoje' => [
            'path' => 'hoje',
            'label' => 'Produção de hoje',
        ],
        'semana' => [
            'path' => 'semana',
            'label' => 'Produção semanal',
        ],
        'mes' => [
            'path' => 'mes',
            'label' => 'Produção mensal',
        ],
        'comunicado' => [
            'path' => 'comunicado',
            'label' => 'Comunicado',
        ],
    ],

    'sectors' => [
        'pvc' => [
            'name' => 'PVC',
            'production_query_key' => 'pvc_production_current',
            'uses_provisional_production_data' => false,
            'provisional_notice' => null,
            'accent_color' => '#0a6b4f',
        ],
        'aluminio' => [
            'name' => 'Alumínio',
            'production_query_key' => 'aluminium_production_current',
            'uses_provisional_production_data' => false,
            'provisional_notice' => null,
            'accent_color' => '#526478',
        ],
    ],
];
