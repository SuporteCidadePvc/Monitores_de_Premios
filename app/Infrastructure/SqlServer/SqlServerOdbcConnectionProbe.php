<?php

namespace App\Infrastructure\SqlServer;

use App\Domain\Operations\OdbcConnectionProbe;
use Throwable;

final readonly class SqlServerOdbcConnectionProbe implements OdbcConnectionProbe
{
    public function __construct(
        private OdbcConnectionFactory $productionConnection,
        private DashboardOdbcConnectionFactory $dashboardConnection,
    ) {}

    public function check(): array
    {
        return [
            'production' => $this->isAvailable(fn (): mixed => $this->productionConnection->connection()->query('SELECT 1')),
            'dashboard' => $this->isAvailable(fn (): mixed => $this->dashboardConnection->connection()->query('SELECT 1')),
        ];
    }

    private function isAvailable(callable $connect): string
    {
        try {
            $connect();

            return 'ok';
        } catch (Throwable) {
            return 'unavailable';
        }
    }
}
