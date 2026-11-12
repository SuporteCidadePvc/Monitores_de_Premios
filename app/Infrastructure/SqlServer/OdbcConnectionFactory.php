<?php

namespace App\Infrastructure\SqlServer;

use App\Domain\Production\Exceptions\ProductionDataUnavailable;
use PDO;
use Throwable;

final class OdbcConnectionFactory
{
    private ?PDO $connection = null;

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $dsn = config('production.connection.dsn');
        $username = config('production.connection.username');
        $password = config('production.connection.password');
        $timeout = config('production.connection.timeout_seconds');

        if (! is_string($dsn) || ! preg_match('/^[a-z0-9_. -]+$/i', $dsn)) {
            throw ProductionDataUnavailable::duringConnection();
        }

        if (! is_string($username) || ! is_string($password) || ! is_int($timeout) || $timeout < 1) {
            throw ProductionDataUnavailable::duringConnection();
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
            throw ProductionDataUnavailable::duringConnection($exception, OdbcDiagnosticCode::from($exception));
        }

        return $this->connection;
    }
}
