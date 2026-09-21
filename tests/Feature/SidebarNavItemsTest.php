<?php
// tests/Feature/SidebarNavItemsTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Feature;

use Amana\Shared\Services\NavVisibility;
use Amana\Shared\Tests\Support\FakeUser;
use Amana\Shared\Tests\Support\NavFixtures;
use Amana\Shared\Tests\Support\ViewHarness;
use Illuminate\View\Factory;
use PHPUnit\Framework\TestCase;

class SidebarNavItemsTest extends TestCase
{
    private Factory $views;

    protected function setUp(): void
    {
        $this->views = ViewHarness::boot();
        NavFixtures::$affectes = [];
        ViewHarness::$config = [
            'amana-shared.home_route' => 'home',
            'amana-shared.branding.app_name' => 'Test',
            'amana-shared.nav' => NavFixtures::nav(),
        ];
        ViewHarness::$container->singleton(NavVisibility::class);
    }

    private function sidebar(?FakeUser $user, array $badges = []): string
    {
        ViewHarness::$user = $user;

        return ViewHarness::render($this->views, 'amana-shared::layouts.partials.sidebar', ['navBadges' => $badges]);
    }

    public function testRendersOnlyVisibleItemsInSectionsAndSkipsEmptySections(): void
    {
        $html = $this->sidebar(new FakeUser(niveau: 'membre'));

        $this->assertStringContainsString('/r/a.public', $html);
        $this->assertStringContainsString('/r/a.membre', $html);
        $this->assertStringNotContainsString('/r/a.admin', $html);
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
    }

    public function testActiveItemIsHighlightedFromRoutePattern(): void
    {
        ViewHarness::$currentRoute = 'a.admin.sub';
        $html = ViewHarness::normalize($this->sidebar(new FakeUser(niveau: 'admin')));

        $this->assertMatchesRegularExpression('#href="/r/a\.admin"[^>]*nav-item-active#', $html);
        $this->assertDoesNotMatchRegularExpression('#href="/r/a\.membre"[^>]*nav-item-active#', $html);
    }
}
