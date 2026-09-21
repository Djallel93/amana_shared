<?php
// tests/Feature/SidebarNavItemsTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Feature;

use Amana\Shared\Contracts\NavBadgeProvider;
use Amana\Shared\Tests\Support\FakeNavBadgeProvider;
use Amana\Shared\Tests\Support\FakeUser;
use Amana\Shared\Tests\Support\NavFixtures;
use Amana\Shared\Tests\TestCase;

class SidebarNavItemsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        NavFixtures::$affectes = [];
        NavFixtures::$appels = 0;

        $this->app['config']->set('amana-shared.home_route', 'home');
        $this->app['config']->set('amana-shared.branding.app_name', 'Test');
        $this->app['config']->set('amana-shared.nav', NavFixtures::nav());

        // La sidebar appelle route() pour chaque item visible.
        foreach (['home', 'logout', 'a.public', 'a.membre', 'a.admin', 'a.pesee', 'a.gestion', 'a.cache'] as $nom) {
            $this->route('GET', '/' . str_replace('.', '-', $nom), fn () => $this->sidebar(null))->name($nom);
        }
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    private function sidebar(?FakeUser $user, array $badges = []): string
    {
        if ($user) {
            $this->app['auth']->guard('web')->setUser($user);
        }

        // Les compteurs viennent du fournisseur lié par l'app (le composer de
        // la sidebar écrase toute valeur passée à la vue).
        FakeNavBadgeProvider::reset();
        FakeNavBadgeProvider::$counts = $badges;
        $this->app->bind(NavBadgeProvider::class, FakeNavBadgeProvider::class);

        return view('amana-shared::layouts.partials.sidebar')->render();
    }

    private function normalise(string $html): string
    {
        return trim(preg_replace('/>\s+</', '><', preg_replace('/\s+/', ' ', $html)));
    }

    public function testRendersOnlyVisibleItemsInSectionsAndSkipsEmptySections(): void
    {
        $html = $this->sidebar(new FakeUser(niveau: 'membre'));

        $this->assertStringContainsString('/a-public', $html);
        $this->assertStringContainsString('/a-membre', $html);
        $this->assertStringNotContainsString('/a-admin', $html);
        $this->assertStringNotContainsString('Section vide', $html);
        $this->assertStringContainsString('Section 1', $html);
        // 'Section 2' n'a aucun item visible pour un membre → pas de <details>.
        $this->assertStringNotContainsString('Section 2', $html);
    }

    public function testBadgeShownOnlyWhenCountIsPositiveAndCappedAt99Plus(): void
    {
        $html = $this->sidebar(new FakeUser(niveau: 'admin'), ['a.admin' => 3, 'a.membre' => 250, 'a.gestion' => 0]);

        $this->assertStringContainsString('aria-label="3 en attente">3</span>', $html);
        $this->assertStringContainsString('aria-label="250 en attente">99+</span>', $html);
        $this->assertStringNotContainsString('aria-label="0 en attente"', $html);
        // App qui n'a pas adopté le rafraîchissement : aucun repère, aucun script.
        $this->assertStringNotContainsString('data-nav-badge', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function testActiveItemIsHighlightedFromRoutePattern(): void
    {
        $this->app['auth']->guard('web')->setUser(new FakeUser(niveau: 'admin'));

        $html = $this->normalise($this->call('GET', '/a-admin')->getContent());

        $this->assertMatchesRegularExpression('#href="[^"]*/a-admin"[^>]*nav-item-active#', $html);
        $this->assertDoesNotMatchRegularExpression('#href="[^"]*/a-membre"[^>]*nav-item-active#', $html);
    }
}
