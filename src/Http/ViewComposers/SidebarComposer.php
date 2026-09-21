<?php
// src/Http/ViewComposers/SidebarComposer.php

declare(strict_types=1);

namespace Amana\Shared\Http\ViewComposers;

use Amana\Shared\Contracts\NavBadgeProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Router;

/**
 * Données de la vue amana-shared::layouts.partials.sidebar.
 *
 * Badges de navigation (voir Contracts\NavBadgeProvider) — résolus à chaque
 * rendu complet de la page, uniquement si l'app a lié une implémentation :
 *   - $navBadges     : compteurs initiaux, indexés par nom de route ;
 *   - $navBadgesUrl  : URL du point de terminaison de rafraîchissement, ou
 *                      null. Non null SEULEMENT si le fournisseur est lié,
 *                      un utilisateur est connecté ET l'app a enregistré la
 *                      route (config 'nav_badges_route'). Sinon la sidebar
 *                      n'émet aucun script et l'app se comporte comme avant :
 *                      aucune requête supplémentaire, aucun 404.
 *   - $navBadgesPollSeconds : intervalle de rafraîchissement (plancher 15 s).
 */
class SidebarComposer
{
    public function __construct(
        private readonly Container $app,
        private readonly Router $router,
    ) {
    }

    public function compose(View $view): void
    {
        $lie = $this->app->bound(NavBadgeProvider::class);

        $navBadges = $lie ? $this->app->make(NavBadgeProvider::class)->counts() : [];

        $nomRoute = (string) config('amana-shared.nav_badges_route', 'nav-badges.index');
        $connecte = auth()->user() !== null;

        $vue = $lie && $connecte && $this->router->has($nomRoute);

        $view->with('navBadges', $navBadges)
            ->with('navBadgesUrl', $vue ? route($nomRoute) : null)
            ->with('navBadgesPollSeconds', max(15, (int) config('amana-shared.nav_badges_poll_seconds', 45)));
    }
}
