<?php

return [
    'connection' => [
        'dsn' => env('DASHBOARD_ODBC_DSN', 'Conecta-Conexao'),
        'username' => env('DASHBOARD_ODBC_USERNAME', env('PRODUCTION_ODBC_USERNAME', '')),
        'password' => env('DASHBOARD_ODBC_PASSWORD', env('PRODUCTION_ODBC_PASSWORD', '')),
        'timeout_seconds' => (int) env('DASHBOARD_ODBC_TIMEOUT', 5),
    ],
];
