<?php

namespace App\Infrastructure\SqlServer;

use App\Domain\Admin\AdminUser;
use App\Domain\Admin\AdminUserRepository;
use App\Domain\Admin\Exceptions\AdminDataUnavailable;
use App\Infrastructure\SqlServer\Exceptions\DashboardConnectionUnavailable;
use PDOException;
use Throwable;

final readonly class SqlServerAdminUserRepository implements AdminUserRepository
{
    private const USER_COLUMNS = 'UserId, Username, DisplayName, PasswordHash, IsActive';

    public function __construct(private DashboardOdbcConnectionFactory $connectionFactory) {}

    public function findActiveByUsername(string $username): ?AdminUser
    {
        return $this->findOne(
            'SELECT '.self::USER_COLUMNS.' FROM dbo.MonitorDePremios_Usuarios WHERE Username = ? AND IsActive = 1',
            [$username],
        );
    }

    public function findActiveById(int $id): ?AdminUser
    {
        return $this->findOne(
            'SELECT '.self::USER_COLUMNS.' FROM dbo.MonitorDePremios_Usuarios WHERE UserId = ? AND IsActive = 1',
            [$id],
        );
    }

    public function updatePasswordHash(AdminUser $user, string $passwordHash): void
    {
        $this->execute(
            'UPDATE dbo.MonitorDePremios_Usuarios SET PasswordHash = ?, UpdatedAt = SYSUTCDATETIME() WHERE UserId = ? AND IsActive = 1',
            [$passwordHash, $user->id],
        );
    }

    public function recordLogin(AdminUser $user): void
    {
        $this->execute(
            'UPDATE dbo.MonitorDePremios_Usuarios SET LastLoginAt = SYSUTCDATETIME(), UpdatedAt = SYSUTCDATETIME() WHERE UserId = ? AND IsActive = 1',
            [$user->id],
        );
    }

    public function createInitialAdministrator(string $username, string $displayName, string $passwordHash): AdminUser
    {
        try {
            $connection = $this->connectionFactory->connection();
            $connection->beginTransaction();

            $existing = (int) $connection->query('SELECT COUNT(*) FROM dbo.MonitorDePremios_Usuarios')->fetchColumn();

            if ($existing !== 0) {
                $connection->rollBack();
                throw new \LogicException('An administrative user already exists.');
            }

            $insert = $connection->prepare(
                'INSERT INTO dbo.MonitorDePremios_Usuarios (Username, DisplayName, PasswordHash, UserRole, IsActive) VALUES (?, ?, ?, ?, 1)',
            );
            $insert->execute([$username, $displayName, $passwordHash, 'administrator']);
            $id = (int) $connection->query('SELECT CAST(SCOPE_IDENTITY() AS BIGINT)')->fetchColumn();

            $audit = $connection->prepare(
                'INSERT INTO dbo.MonitorDePremios_Auditoria (ActionName, EntityType, EntityId, ActorLabel, MetadataJson) VALUES (?, ?, ?, ?, ?)',
            );
            $audit->execute([
                'admin.user.created',
                'admin_user',
                $id,
                $username,
                json_encode(['source' => 'artisan', 'role' => 'administrator'], JSON_THROW_ON_ERROR),
            ]);

            $connection->commit();

            return new AdminUser($id, $username, $displayName, $passwordHash, true);
        } catch (\LogicException $exception) {
            throw $exception;
        } catch (DashboardConnectionUnavailable|PDOException $exception) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw new AdminDataUnavailable('The administrative database is unavailable.', 0, $exception);
        } catch (Throwable $exception) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw new AdminDataUnavailable('The administrative user could not be created.', 0, $exception);
        }
    }

    /** @param list<int|string> $values */
    private function findOne(string $sql, array $values): ?AdminUser
    {
        try {
            $statement = $this->connectionFactory->connection()->prepare($sql);
            $statement->execute($values);
            $row = $statement->fetch();

            return is_array($row) ? $this->toUser($row) : null;
        } catch (DashboardConnectionUnavailable|PDOException $exception) {
            throw new AdminDataUnavailable('The administrative database is unavailable.', 0, $exception);
        } catch (Throwable $exception) {
            throw new AdminDataUnavailable('The administrative user could not be read.', 0, $exception);
        }
    }

    /** @param list<int|string> $values */
    private function execute(string $sql, array $values): void
    {
        try {
            $statement = $this->connectionFactory->connection()->prepare($sql);
            $statement->execute($values);
        } catch (DashboardConnectionUnavailable|PDOException $exception) {
            throw new AdminDataUnavailable('The administrative database is unavailable.', 0, $exception);
        } catch (Throwable $exception) {
            throw new AdminDataUnavailable('The administrative user could not be updated.', 0, $exception);
        }
    }

    /** @param array<string, mixed> $row */
    private function toUser(array $row): AdminUser
    {
        $row = array_change_key_case($row, CASE_LOWER);

        return new AdminUser(
            id: (int) ($row['userid'] ?? 0),
            username: (string) ($row['username'] ?? ''),
            displayName: (string) ($row['displayname'] ?? ''),
            passwordHash: (string) ($row['passwordhash'] ?? ''),
            isActive: (bool) ($row['isactive'] ?? false),
        );
    }
}
