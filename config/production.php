<?php

return [
    'timezone' => env('APP_TIMEZONE', 'Europe/Lisbon'),

    'connection' => [
        'dsn' => env('PRODUCTION_ODBC_DSN', 'BD_PREF'),
        'username' => env('PRODUCTION_ODBC_USERNAME', ''),
        'password' => env('PRODUCTION_ODBC_PASSWORD', ''),
        'timeout_seconds' => (int) env('PRODUCTION_ODBC_TIMEOUT', 5),
    ],

    'line_names' => [
        'pvc' => env('PRODUCTION_LINE_NAME', 'Linha Corte e Montagem'),
    ],
];
