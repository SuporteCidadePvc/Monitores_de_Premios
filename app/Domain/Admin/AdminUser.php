<?php

namespace App\Domain\Admin;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;

final class AdminUser implements Authenticatable
{
    use AuthenticatableTrait;

    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $displayName,
        private readonly string $passwordHash,
        public readonly bool $isActive,
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'UserId';
    }

    public function getAuthIdentifier(): int
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'PasswordHash';
    }

    public function getAuthPassword(): string
    {
        return $this->passwordHash;
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // Persistent login is deliberately not supported for administrators.
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
