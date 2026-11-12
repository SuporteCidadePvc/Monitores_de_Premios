<?php

declare(strict_types=1);

use App\Infrastructure\SqlServer\DashboardOdbcConnectionFactory;
use App\Infrastructure\SqlServer\OdbcConnectionFactory;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @return array<string, mixed> */
function inventory(\PDO $connection): array
{
    return [
        'status' => 'available',
        'database' => $connection->query('SELECT DB_NAME() AS database_name')->fetch(),
        'object_counts' => $connection->query(<<<'SQL'
            SELECT TABLE_TYPE, COUNT(*) AS object_count
            FROM INFORMATION_SCHEMA.TABLES
            GROUP BY TABLE_TYPE
            ORDER BY TABLE_TYPE
            SQL)->fetchAll(\PDO::FETCH_ASSOC),
        'aluminium_candidates' => $connection->query(<<<'SQL'
            SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE LOWER(TABLE_NAME) LIKE '%alum%'
               OR LOWER(COLUMN_NAME) LIKE '%alum%'
            ORDER BY TABLE_SCHEMA, TABLE_NAME, ORDINAL_POSITION
            SQL)->fetchAll(\PDO::FETCH_ASSOC),
    ];
}

$sources = [
    'production' => [OdbcConnectionFactory::class, (array) config('production.connection')],
    'dashboard' => [DashboardOdbcConnectionFactory::class, (array) config('dashboard.connection')],
];
$results = [];

foreach ($sources as $name => [$factory, $connectionConfiguration]) {
    $configurationStatus = [
        'dsn_configured' => isset($connectionConfiguration['dsn']) && $connectionConfiguration['dsn'] !== '',
        'username_configured' => isset($connectionConfiguration['username']) && $connectionConfiguration['username'] !== '',
        'password_configured' => isset($connectionConfiguration['password']) && $connectionConfiguration['password'] !== '',
    ];

    try {
        $results[$name] = inventory($app->make($factory)->connection()) + ['configuration' => $configurationStatus];
    } catch (Throwable) {
        $results[$name] = ['status' => 'unavailable', 'configuration' => $configurationStatus];
    }
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
