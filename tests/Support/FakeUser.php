<?php
// tests/Support/FakeUser.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Utilisateur factice : reproduit la hiérarchie de rôles de
 * Personne (admin ⊇ gestionnaire ⊇ membre ⊇ benevole) et hasRole() sur des
 * codes applicatifs, sans base de données.
 */
class FakeUser implements Authenticatable
{
    private const NIVEAUX = ['benevole' => 1, 'membre' => 2, 'gestionnaire' => 3, 'admin' => 4];

    /**
     * @param  string       $niveau  benevole|membre|gestionnaire|admin
     * @param  string[]     $codes   codes de rôles applicatifs détenus (ex. equipe_pesee)
     */
    public function __construct(
        public int $id = 1,
        public string $niveau = 'benevole',
        public array $codes = [],
        public string $prenom = 'Test',
        public string $nom = 'User',
    ) {
    }

    public function hasAtLeastRole(string $role): bool
    {
        return (self::NIVEAUX[$this->niveau] ?? 0) >= (self::NIVEAUX[$role] ?? PHP_INT_MAX);
    }

    public function hasRole(string $code, ?string $appCode = null): bool
    {
        return in_array($code, $this->codes, true) || $code === $this->niveau;
    }

    public function isAdmin(): bool
    {
        return $this->niveau === 'admin';
    }

    public function isGestionnaire(): bool
    {
        return $this->niveau === 'gestionnaire';
    }

    public function isMembre(): bool
    {
        return $this->niveau === 'membre';
    }

    public function getAuthIdentifierName()
    {
        return 'id';
    }

    public function getAuthIdentifier()
    {
        return $this->id;
    }

    public function getAuthPasswordName()
    {
        return 'password';
    }

    public function getAuthPassword()
    {
        return '';
    }

    public function getRememberToken()
    {
        return null;
    }

    public function setRememberToken($value)
    {
    }

    public function getRememberTokenName()
    {
        return 'remember_token';
    }
}
