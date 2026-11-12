<?php

namespace App\Domain\Admin;

interface AdminUserRepository
{
    public function findActiveByUsername(string $username): ?AdminUser;

    public function findActiveById(int $id): ?AdminUser;

    public function updatePasswordHash(AdminUser $user, string $passwordHash): void;

    public function recordLogin(AdminUser $user): void;

    public function createInitialAdministrator(string $username, string $displayName, string $passwordHash): AdminUser;
}
