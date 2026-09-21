<?php
// src/Services/NavVisibility.php

declare(strict_types=1);

namespace Amana\Shared\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

/**
 * Règles de visibilité des items de navigation (config('amana-shared.nav')),
 * extraites de la sidebar Blade pour être partagées entre :
 *   - la sidebar elle-même (layouts/partials/sidebar.blade.php) ;
 *   - le point de terminaison JSON des badges (NavBadgesController), qui ne
 *     doit JAMAIS renvoyer un compteur que la sidebar masquerait.
 *
 * Une seule implémentation : c'est ce qui garantit que les deux ne peuvent
 * pas diverger. Le comportement est celui, à l'identique, de l'ancienne
 * closure $itemsVisibles :
 *
 *   1. item sans 'role' → visible pour tout le monde ;
 *   2. pas d'utilisateur → invisible dès qu'un 'role' est demandé ;
 *   3. 'role' ∈ {membre, gestionnaire, admin, benevole} → hasAtLeastRole()
 *      UNIQUEMENT (ne jamais élargir un lien 'admin' à un gestionnaire) ;
 *   4. tout autre code de rôle applicatif (ex. equipe_pesee) → hasRole($code)
 *      OU hasAtLeastRole('gestionnaire') ;
 *   5. sinon, 'extra_check' optionnel — callable(int $idPersonne, string $role):
 *      bool, tel [FQCN::class, 'methode'] pour rester compatible avec
 *      `config:cache` — testé en dernier.
 */
class NavVisibility
{
    /** Codes de la hiérarchie interne, résolus par hasAtLeastRole() seulement. */
    private const HIERARCHIE = ['membre', 'gestionnaire', 'admin', 'benevole'];

    /**
     * Regroupe la liste plate de config('amana-shared.nav') par section, en
     * conservant l'ordre. Une entrée sans section précédente est rangée sous
     * la clé '' plutôt que d'être perdue.
     *
     * @param  array<int, array<string, mixed>>  $nav
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function sections(array $nav): array
    {
        $sections = [];
        $sectionCourante = null;

        foreach ($nav as $item) {
            if (isset($item['section'])) {
                $sectionCourante = $item['section'];
                $sections[$sectionCourante] ??= [];
            } elseif ($sectionCourante !== null) {
                $sections[$sectionCourante][] = $item;
            } else {
                $sections[''][] = $item;
            }
        }

        return $sections;
    }

    /**
     * @param  iterable<array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    public function filter(iterable $items, ?Authenticatable $user): Collection
    {
        return collect($items)->filter(fn ($item) => $this->isVisible($item, $user));
    }

    /**
     * Tous les items de navigation (hors titres de section) visibles pour
     * cet utilisateur, indexés par nom de route.
     *
     * @param  array<int, array<string, mixed>>  $nav
     * @return array<string, array<string, mixed>>
     */
    public function visibleByRoute(array $nav, ?Authenticatable $user): array
    {
        $visibles = [];

        foreach ($nav as $item) {
            if (isset($item['section']) || empty($item['route'])) {
                continue;
            }
            if ($this->isVisible($item, $user)) {
                $visibles[$item['route']] = $item;
            }
        }

        return $visibles;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function isVisible(array $item, ?Authenticatable $user): bool
    {
        if (empty($item['role'])) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if (in_array($item['role'], self::HIERARCHIE, true)) {
            return $user->hasAtLeastRole($item['role']);
        }

        if ($user->hasRole($item['role']) || $user->hasAtLeastRole('gestionnaire')) {
            return true;
        }

        if (! empty($item['extra_check']) && is_callable($item['extra_check'])) {
            return (bool) call_user_func($item['extra_check'], $user->id, $item['role']);
        }

        return false;
    }
}
