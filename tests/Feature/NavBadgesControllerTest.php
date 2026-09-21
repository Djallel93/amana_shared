<?php
// tests/Feature/NavBadgesControllerTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Feature;

use Amana\Shared\Contracts\NavBadgeProvider;
use Amana\Shared\Http\Controllers\NavBadgesController;
use Amana\Shared\Tests\Support\FakeNavBadgeProvider;
use Amana\Shared\Tests\Support\NavFixtures;
use Amana\Shared\Tests\TestCase;
use Illuminate\Auth\Middleware\Authenticate;

class NavBadgesControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeNavBadgeProvider::reset();
        NavFixtures::$affectes = [];
        NavFixtures::$appels = 0;

        $this->app['config']->set('amana-shared.nav', NavFixtures::nav());
        $this->app['config']->set('amana-shared.nav_badges_cache_seconds', 0);
        $this->app->bind(NavBadgeProvider::class, FakeNavBadgeProvider::class);

        $this->app['router']->aliasMiddleware('auth', Authenticate::class);
        $this->route('GET', '/nav-badges', NavBadgesController::class)
            ->name('nav-badges.index')
            ->middleware('auth');
    }

    /** @return array<string, mixed> */
    private function badges(?\Amana\Shared\Models\Personne $personne): array
    {
        $this->actingAs($personne);
        $reponse = $this->call('GET', '/nav-badges', headers: ['Accept' => 'application/json']);
        $this->assertSame(200, $reponse->getStatusCode());

        return $this->json($reponse);
    }

    public function testGuestGets401(): void
    {
        $this->actingAs(null);
        $reponse = $this->call('GET', '/nav-badges', headers: ['Accept' => 'application/json']);

        $this->assertSame(401, $reponse->getStatusCode());
    }

    public function testReturnsIntegerCountsForVisibleItemsOnly(): void
    {
        FakeNavBadgeProvider::$counts = ['a.admin' => 5, 'a.membre' => 2, 'a.gestion' => 7];

        $this->assertEquals(
            ['a.admin' => 5, 'a.membre' => 2, 'a.gestion' => 7],
            $this->badges($this->personne(['admin'])),
        );
    }

    public function testUserWithoutTheRoleNeverReceivesTheCountNorTheKey(): void
    {
        FakeNavBadgeProvider::$counts = ['a.admin' => 5, 'a.membre' => 2, 'a.gestion' => 7];

        $this->actingAs($this->personne(['membre']));
        $reponse = $this->call('GET', '/nav-badges', headers: ['Accept' => 'application/json']);

        $this->assertSame(['a.membre' => 2], $this->json($reponse));
        $this->assertStringNotContainsString('a.admin', (string) $reponse->getContent());
        $this->assertStringNotContainsString('a.gestion', (string) $reponse->getContent());
    }

    public function testVisibleItemAbsentFromProviderIsOmittedAndZeroStaysZero(): void
    {
        FakeNavBadgeProvider::$counts = ['a.membre' => 0];

        $this->assertSame(['a.membre' => 0], $this->badges($this->personne(['admin'])));
    }

    public function testUnknownRoutesFromProviderAreIgnored(): void
    {
        FakeNavBadgeProvider::$counts = ['route.inconnue' => 9, 'a.membre' => 1];

        $this->assertSame(['a.membre' => 1], $this->badges($this->personne(['membre'])));
    }

    public function testResponseIsAJsonObjectEvenWhenEmptyAndNeverCached(): void
    {
        $this->actingAs($this->personne(['membre']));
        $reponse = $this->call('GET', '/nav-badges', headers: ['Accept' => 'application/json']);

        $this->assertSame('{}', (string) $reponse->getContent());
        $this->assertStringContainsString('no-store', (string) $reponse->headers->get('Cache-Control'));
    }

    public function testUnboundProviderReturnsEmptyObject(): void
    {
        $this->app->offsetUnset(NavBadgeProvider::class);
        $this->assertFalse($this->app->bound(NavBadgeProvider::class));

        $this->assertSame([], $this->badges($this->personne(['admin'])));
    }

    public function testProviderFailureReturns503WithoutLeakingTheError(): void
    {
        FakeNavBadgeProvider::$throw = true;

        $this->actingAs($this->personne(['admin']));
        $reponse = $this->call('GET', '/nav-badges', headers: ['Accept' => 'application/json']);

        $this->assertSame(503, $reponse->getStatusCode());
        $this->assertStringNotContainsString('base indisponible', (string) $reponse->getContent());
    }

    public function testCountsAreCachedAcrossRequestsWhenTtlIsPositive(): void
    {
        $this->app['config']->set('amana-shared.nav_badges_cache_seconds', 10);
        FakeNavBadgeProvider::$counts = ['a.membre' => 4];
        $membre = $this->personne(['membre']);

        $this->badges($membre);
        $this->badges($membre);

        $this->assertSame(1, FakeNavBadgeProvider::$calls);
    }

    public function testNoCacheWhenTtlIsZero(): void
    {
        FakeNavBadgeProvider::$counts = ['a.membre' => 4];
        $membre = $this->personne(['membre']);

        $this->badges($membre);
        $this->badges($membre);

        $this->assertSame(2, FakeNavBadgeProvider::$calls);
    }

    public function testExtraCheckIsOnlyEvaluatedForItemsThatHaveACount(): void
    {
        FakeNavBadgeProvider::$counts = ['a.membre' => 1];

        $this->badges($this->personne([]));

        $this->assertSame(0, NavFixtures::$appels);
    }

    public function testExtraCheckStillGovernsVisibilityOfAnItemWithACount(): void
    {
        FakeNavBadgeProvider::$counts = ['a.pesee' => 3];
        $personne = $this->personne([]);

        $this->assertSame([], $this->badges($personne));

        NavFixtures::$affectes = [$personne->id];
        $this->assertSame(['a.pesee' => 3], $this->badges($personne));
    }
}
