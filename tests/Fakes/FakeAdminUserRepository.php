<?php

namespace Tests\Fakes;

use App\Domain\Admin\AdminUser;
use App\Domain\Admin\AdminUserRepository;
use LogicException;

final class FakeAdminUserRepository implements AdminUserRepository
{
    /** @var array<int, AdminUser> */
    private array $users = [];

    public int $recordedLogins = 0;

    public function add(AdminUser $user): void
    {
        $this->users[$user->id] = $user;
    }

    public function findActiveByUsername(string $username): ?AdminUser
    {
        foreach ($this->users as $user) {
            if ($user->isActive && $user->username === $username) {
                return $user;
            }
        }

        return null;
    }

    public function findActiveById(int $id): ?AdminUser
    {
        $user = $this->users[$id] ?? null;

        return $user?->isActive ? $user : null;
    }

    public function updatePasswordHash(AdminUser $user, string $passwordHash): void {}

    public function recordLogin(AdminUser $user): void
    {
        $this->recordedLogins++;
    }

    public function createInitialAdministrator(string $username, string $displayName, string $passwordHash): AdminUser
    {
        if ($this->users !== []) {
            throw new LogicException('An administrative user already exists.');
        }

        $user = new AdminUser(1, $username, $displayName, $passwordHash, true);
        $this->add($user);

        return $user;
    }
}
