<?php
// tests/Support/NavFixtures.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Support;

/** Configuration de navigation couvrant les trois branches de visibilité. */
final class NavFixtures
{
    /** Identifiants pour lesquels l'extra_check répond « oui ». */
    public static array $affectes = [];

    /** Nombre d'appels à estAffecte() (pour vérifier qu'il n'est pas lancé pour rien). */
    public static int $appels = 0;

    public static function estAffecte(int $idPersonne, string $role): bool
    {
        self::$appels++;

        return in_array($idPersonne, self::$affectes, true);
    }

    /** @return array<int, array<string, mixed>> */
    public static function nav(): array
    {
        return [
            // Entrée AVANT toute section → rangée sous la clé '' (sans <details>).
            ['route' => 'a.public', 'label' => 'Public', 'icon' => '🏠'],
            ['section' => 'Section 1'],
            ['route' => 'a.membre', 'label' => 'Membre', 'icon' => '👤', 'role' => 'membre'],
            ['route' => 'a.admin', 'label' => 'Admin', 'icon' => '🛡️', 'role' => 'admin', 'route_pattern' => 'a.admin*'],
            [
                'route' => 'a.pesee', 'label' => 'Pesée', 'icon' => '⚖️', 'role' => 'equipe_pesee',
                'extra_check' => [self::class, 'estAffecte'],
            ],
            ['section' => 'Section 2'],
            ['route' => 'a.gestion', 'label' => 'Gestion', 'icon' => '⚙️', 'role' => 'gestionnaire'],
            ['section' => 'Section vide'],
            ['route' => 'a.cache', 'label' => 'Caché', 'role' => 'admin'],
        ];
    }
}
