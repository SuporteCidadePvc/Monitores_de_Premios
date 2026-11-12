<?php

namespace App\Infrastructure\Auth;

use App\Domain\Admin\AdminUser;
use App\Domain\Admin\AdminUserRepository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Hashing\Hasher;

final readonly class SqlServerAdminUserProvider implements UserProvider
{
    public function __construct(
        private AdminUserRepository $users,
        private Hasher $hasher,
    ) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        return is_numeric($identifier) ? $this->users->findActiveById((int) $identifier) : null;
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
        // Persistent login is intentionally disabled for this internal admin area.
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        $username = $credentials['username'] ?? null;

        return is_string($username) ? $this->users->findActiveByUsername($username) : null;
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        $password = $credentials['password'] ?? null;

        return $user instanceof AdminUser
            && is_string($password)
            && $this->hasher->check($password, $user->getAuthPassword());
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void
    {
        $password = $credentials['password'] ?? null;

        if ($user instanceof AdminUser && is_string($password) && ($force || $this->hasher->needsRehash($user->getAuthPassword()))) {
            $this->users->updatePasswordHash($user, $this->hasher->make($password));
        }
    }
}
