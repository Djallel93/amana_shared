<?php
// src/Http/ViewComposers/SidebarComposer.php

declare(strict_types=1);

namespace Amana\Shared\Http\ViewComposers;

use Amana\Shared\Contracts\NavBadgeProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\View;
use Amana\Shared\Models\Personne;
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
 *
 * Point d'entrée « Mon profil » :
 *   - $profileUrl : URL de la page de profil, ou null. Non null SEULEMENT si un
 *                   utilisateur (Personne) est connecté ET que l'app a
 *                   enregistré la route (config 'profile_route', défaut
 *                   'profile.edit') ; sinon la sidebar garde l'ancien logo.
 *
 * Bouton « Guide d'utilisation » (18/10/2026) :
 *   - $guideUrl : URL du guide, ou null. Non null SEULEMENT si un utilisateur
 *                 est connecté ET que l'app a enregistré la route (config
 *                 'guide_route', défaut 'guide.index') ; sinon le footer de la
 *                 sidebar n'affiche aucun bouton, sans erreur.
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

        $nomProfil = (string) config('amana-shared.profile_route', 'profile.edit');
        $profil = auth()->user() instanceof Personne && $this->router->has($nomProfil);

        $nomGuide = (string) config('amana-shared.guide_route', 'guide.index');
        $guide = $connecte && $this->router->has($nomGuide);

        $view->with('navBadges', $navBadges)
            ->with('profileUrl', $profil ? route($nomProfil) : null)
            ->with('guideUrl', $guide ? route($nomGuide) : null)
            ->with('navBadgesUrl', $vue ? route($nomRoute) : null)
            ->with('navBadgesPollSeconds', max(15, (int) config('amana-shared.nav_badges_poll_seconds', 45)));
    }
}
