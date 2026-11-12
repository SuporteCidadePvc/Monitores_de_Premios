<?php

namespace App\Infrastructure\SqlServer;

use App\Infrastructure\SqlServer\Exceptions\DashboardConnectionUnavailable;
use PDO;
use Throwable;

final class DashboardOdbcConnectionFactory
{
    private ?PDO $connection = null;

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $dsn = config('dashboard.connection.dsn');
        $username = config('dashboard.connection.username');
        $password = config('dashboard.connection.password');
        $timeout = config('dashboard.connection.timeout_seconds');

        if (! is_string($dsn) || ! preg_match('/^[a-z0-9_. -]+$/i', $dsn)) {
            throw new DashboardConnectionUnavailable('The dashboard DSN is invalid.');
        }

        if (! is_string($username) || ! is_string($password) || ! is_int($timeout) || $timeout < 1) {
            throw new DashboardConnectionUnavailable('The dashboard connection configuration is invalid.');
        }

        try {
            $this->connection = new PDO(
                "odbc:{$dsn}",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => $timeout,
                ],
            );
        } catch (Throwable $exception) {
            throw new DashboardConnectionUnavailable(
                'The dashboard database is unavailable.',
                0,
                $exception,
                OdbcDiagnosticCode::from($exception),
            );
        }

        return $this->connection;
    }
}
