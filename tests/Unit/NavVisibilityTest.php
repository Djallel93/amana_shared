<?php
// tests/Unit/NavVisibilityTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Unit;

use Amana\Shared\Services\NavVisibility;
use Amana\Shared\Tests\Support\FakeUser;
use Amana\Shared\Tests\Support\NavFixtures;
use PHPUnit\Framework\TestCase;

class NavVisibilityTest extends TestCase
{
    private NavVisibility $visibility;

    protected function setUp(): void
    {
        $this->visibility = new NavVisibility();
        NavFixtures::$affectes = [];
    }

    private function routes(?FakeUser $user): array
    {
        return array_keys($this->visibility->visibleByRoute(NavFixtures::nav(), $user));
    }

    public function testGuestOnlySeesItemsWithoutRole(): void
    {
        $this->assertSame(['a.public'], $this->routes(null));
    }

    public function testBenevoleSeesOnlyPublicItems(): void
    {
        $this->assertSame(['a.public'], $this->routes(new FakeUser(niveau: 'benevole')));
    }

    public function testMembreSeesMembreItems(): void
    {
        $this->assertSame(['a.public', 'a.membre'], $this->routes(new FakeUser(niveau: 'membre')));
    }

    public function testGestionnaireIsNeverWidenedToAdminItemsButSeesAppRoleItems(): void
    {
        // Branche 1 : 'admin' reste réservé à admin. Branche 2 : un code
        // applicatif (equipe_pesee) est aussi visible d'un gestionnaire.
        $this->assertSame(
            ['a.public', 'a.membre', 'a.pesee', 'a.gestion'],
            $this->routes(new FakeUser(niveau: 'gestionnaire')),
        );
    }

    public function testAdminSeesEverything(): void
    {
        $this->assertSame(
            ['a.public', 'a.membre', 'a.admin', 'a.pesee', 'a.gestion', 'a.cache'],
            $this->routes(new FakeUser(niveau: 'admin')),
        );
    }

    public function testSpecificAppRoleGrantsAccessToThatItemOnly(): void
    {
        $this->assertSame(
            ['a.public', 'a.pesee'],
            $this->routes(new FakeUser(niveau: 'benevole', codes: ['equipe_pesee'])),
        );
    }

    public function testExtraCheckIsTheLastResort(): void
    {
        $user = new FakeUser(id: 42, niveau: 'benevole');
        $this->assertSame(['a.public'], $this->routes($user));

        NavFixtures::$affectes = [42];
        $this->assertSame(['a.public', 'a.pesee'], $this->routes($user));
    }

    public function testExtraCheckIsNotCalledWhenAnEarlierBranchAlreadyMatches(): void
    {
        NavFixtures::$affectes = [];
        $item = ['route' => 'x', 'role' => 'equipe_pesee', 'extra_check' => static function (): bool {
            throw new \RuntimeException('ne doit pas être appelé');
        }];

        $this->assertTrue($this->visibility->isVisible($item, new FakeUser(niveau: 'admin')));
    }

    public function testExtraCheckIsNeverCalledForHierarchyRoles(): void
    {
        $item = ['route' => 'x', 'role' => 'admin', 'extra_check' => static fn (): bool => true];

        $this->assertFalse($this->visibility->isVisible($item, new FakeUser(niveau: 'membre')));
    }

    public function testNonCallableExtraCheckIsIgnored(): void
    {
        $item = ['route' => 'x', 'role' => 'equipe_pesee', 'extra_check' => 'pas_une_fonction_xyz'];

        $this->assertFalse($this->visibility->isVisible($item, new FakeUser(niveau: 'benevole')));
    }

    public function testSectionsGroupingKeepsOrderAndLeadingItemsUnderEmptyKey(): void
    {
        $sections = $this->visibility->sections(NavFixtures::nav());

        $this->assertSame(['', 'Section 1', 'Section 2', 'Section vide'], array_keys($sections));
        $this->assertCount(1, $sections['']);
        $this->assertCount(3, $sections['Section 1']);
    }
}
