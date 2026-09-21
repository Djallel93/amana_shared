<?php
// tests/Feature/SidebarLiveBadgesTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Feature;

use Amana\Shared\Contracts\NavBadgeProvider;
use Amana\Shared\Tests\Support\FakeNavBadgeProvider;
use Amana\Shared\Tests\Support\FakeUser;
use Amana\Shared\Tests\Support\NavFixtures;
use Amana\Shared\Tests\TestCase;

/**
 * Règle « opt-in par app » : le rafraîchissement en direct n'existe que si
 * l'app a lié NavBadgeProvider ET enregistré la route ET qu'un utilisateur
 * est connecté. Sinon : pas de script, pas de repère, pas de requête.
 */
class SidebarLiveBadgesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeNavBadgeProvider::reset();
        NavFixtures::$affectes = [];
        $this->app['config']->set('amana-shared.home_route', 'home');
        $this->app['config']->set('amana-shared.nav', NavFixtures::nav());

        foreach (['home', 'logout', 'a.public', 'a.membre', 'a.admin', 'a.pesee', 'a.gestion', 'a.cache'] as $nom) {
            $this->route('GET', '/' . str_replace('.', '-', $nom), fn () => '')->name($nom);
        }
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    private function liaisonEtRoute(bool $lie, bool $route): void
    {
        if ($lie) {
            $this->app->bind(NavBadgeProvider::class, FakeNavBadgeProvider::class);
        }
        if ($route) {
            $this->route('GET', '/nav-badges', fn () => '{}')->name('nav-badges.index');
            $this->app['router']->getRoutes()->refreshNameLookups();
        }
    }

    private function donnees(): array
    {
        // Les composers s'exécutent au rendu, pas à la création de la vue.
        $vue = view('amana-shared::layouts.partials.sidebar');
        $vue->render();

        return $vue->getData();
    }

    private function html(): string
    {
        return view('amana-shared::layouts.partials.sidebar')->render();
    }

    public function testFullyAdoptedAppGetsUrlAndDefaultInterval(): void
    {
        $this->liaisonEtRoute(true, true);
        $this->app['auth']->guard('web')->setUser(new FakeUser(niveau: 'admin'));

        $data = $this->donnees();

        $this->assertSame(url('/nav-badges'), $data['navBadgesUrl']);
        $this->assertSame(45, $data['navBadgesPollSeconds']);
    }

    public function testNoProviderBoundMeansNoUrl(): void
    {
        $this->liaisonEtRoute(false, true);
        $this->app['auth']->guard('web')->setUser(new FakeUser(niveau: 'admin'));

        $this->assertNull($this->donnees()['navBadgesUrl']);
    }

    public function testMissingRouteMeansNoUrl(): void
    {
        $this->liaisonEtRoute(true, false);
        $this->app['auth']->guard('web')->setUser(new FakeUser(niveau: 'admin'));

        $this->assertNull($this->donnees()['navBadgesUrl']);
    }

    public function testNoAuthenticatedUserMeansNoUrl(): void
    {
        $this->liaisonEtRoute(true, true);
        $this->actingAs(null);

        // Composer appelé directement : la sidebar complète n'est pas rendue
        // pour un invité (voir ProfileSidebarTest pour le rendu null-safe).
        $vue = view('amana-shared::layouts.partials.sidebar');
        $this->app->make(\Amana\Shared\Http\ViewComposers\SidebarComposer::class)->compose($vue);

        $this->assertNull($vue->getData()['navBadgesUrl']);
    }

    public function testConfiguredRouteNameAndIntervalFloor(): void
    {
        $this->liaisonEtRoute(true, false);
        $this->route('GET', '/badges-perso', fn () => '{}')->name('perso.badges');
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['config']->set('amana-shared.nav_badges_route', 'perso.badges');
        $this->app['config']->set('amana-shared.nav_badges_poll_seconds', 2);
        $this->app['auth']->guard('web')->setUser(new FakeUser(niveau: 'admin'));

        $data = $this->donnees();

        $this->assertSame(url('/badges-perso'), $data['navBadgesUrl']);
        $this->assertSame(15, $data['navBadgesPollSeconds']);
    }

    public function testAdoptedAppEmitsScriptAndAlwaysRendersHookedBadges(): void
    {
        $this->liaisonEtRoute(true, true);
        FakeNavBadgeProvider::$counts = ['a.admin' => 3];
        $this->app['auth']->guard('web')->setUser(new FakeUser(niveau: 'admin'));

        $html = $this->html();

        $this->assertStringContainsString('<script>', $html);
        $this->assertStringContainsString(json_encode(url('/nav-badges')), $html);
        // Badge présent (compteur > 0) avec repère et libellé…
        $this->assertMatchesRegularExpression('#data-nav-badge="a\.admin"\s+aria-label="3 en attente">3</span>#', $html);
        // …et badge masqué mais présent quand le compteur est 0 (0 → N doit marcher).
        $this->assertMatchesRegularExpression('#data-nav-badge="a\.membre"\s+style="display:none"></span>#', $html);
    }

    public function testUnadoptedAppEmitsNothingLive(): void
    {
        $this->liaisonEtRoute(true, false);
        FakeNavBadgeProvider::$counts = ['a.admin' => 3];
        $this->app['auth']->guard('web')->setUser(new FakeUser(niveau: 'admin'));

        $html = $this->html();

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('data-nav-badge', $html);
        $this->assertStringContainsString('aria-label="3 en attente">3</span>', $html);
    }

    public function testOnlyVisibleItemsGetAHook(): void
    {
        $this->liaisonEtRoute(true, true);
        $this->app['auth']->guard('web')->setUser(new FakeUser(niveau: 'membre'));

        $html = $this->html();

        $this->assertStringContainsString('data-nav-badge="a.membre"', $html);
        $this->assertStringNotContainsString('data-nav-badge="a.admin"', $html);
    }
}
