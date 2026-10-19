<?php
// tests/Feature/GuideSidebarTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Feature;

use Amana\Shared\Tests\Support\FakeUser;
use Amana\Shared\Tests\TestCase;

/**
 * Footer de la sidebar (18/10/2026) : bouton « Guide d'utilisation » opt-in entre
 * « Se déconnecter » et la bascule de thème, et confirmation Oui/Non avant la
 * déconnexion.
 */
class GuideSidebarTest extends TestCase
{
    private function html(): string
    {
        return (string) preg_replace('/\s+/', ' ', view('amana-shared::layouts.partials.sidebar')->render());
    }

    private function enregistrerLeGuide(): void
    {
        $this->route('GET', '/guide', fn () => 'guide')->name('guide.index');
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    public function testGuideButtonSitsBetweenLogoutAndThemeToggleWhenTheAppRegisteredTheRoute(): void
    {
        $this->routesProfil();
        $this->enregistrerLeGuide();
        $this->actingAs($this->personne(['membre']));

        $html = $this->html();

        $this->assertStringContainsString('href="' . route('guide.index') . '"', $html);
        $this->assertStringContainsString('aria-label="Guide d\'utilisation"', $html);

        $logout = strpos($html, 'Se déconnecter');
        $guide = strpos($html, 'aria-label="Guide d\'utilisation"');
        $theme = strpos($html, 'toggleAppTheme()');

        $this->assertNotFalse($logout);
        $this->assertNotFalse($guide);
        $this->assertNotFalse($theme);
        $this->assertTrue($logout < $guide && $guide < $theme, 'Ordre attendu : Se déconnecter, Guide, Thème.');
        // Tap target ≥ 44 px, comme la bascule de thème.
        $this->assertStringContainsString('shrink-0 min-h-[44px] min-w-[44px] flex items-center justify-center rounded-sm border text-base no-underline', $html);
    }

    public function testGuideButtonIsAbsentWhenTheAppHasNoGuideRoute(): void
    {
        $this->routesProfil();
        $this->actingAs($this->personne(['membre']));

        $html = $this->html();

        $this->assertStringNotContainsString('Guide d\'utilisation', $html);
        // Sans guide, le bouton de déconnexion garde son icône.
        $this->assertStringContainsString('🚪', $html);
    }

    public function testGuideButtonFollowsTheConfiguredRouteName(): void
    {
        $this->routesProfil();
        $this->route('GET', '/mode-emploi', fn () => 'guide')->name('mode-emploi');
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['config']->set('amana-shared.guide_route', 'mode-emploi');
        $this->actingAs($this->personne(['membre']));

        $this->assertStringContainsString('href="' . route('mode-emploi') . '"', $this->html());
    }

    public function testGuideButtonIsHighlightedOnGuidePagesOnly(): void
    {
        $this->routesProfil();
        $this->enregistrerLeGuide();
        $this->actingAs($this->personne(['membre']));
        $this->route('GET', '/guide-test', fn () => $this->html())->name('guide.test');
        $this->route('GET', '/ailleurs-test', fn () => $this->html())->name('ailleurs.test');
        $this->app['router']->getRoutes()->refreshNameLookups();

        $sur = $this->call('GET', '/guide-test')->getContent();
        $ailleurs = $this->call('GET', '/ailleurs-test')->getContent();

        $this->assertStringContainsString('aria-label="Guide d\'utilisation" aria-current="page"', (string) $sur);
        $this->assertStringNotContainsString('aria-current="page"', (string) $ailleurs);
    }

    public function testGuideButtonIsNotRenderedForAGuestEvenWithTheRoute(): void
    {
        $this->routesProfil();
        $this->enregistrerLeGuide();

        $this->assertStringNotContainsString('Guide d\'utilisation', $this->html());
    }

    public function testGuideButtonAlsoShowsForAUserThatIsNotAPersonne(): void
    {
        $this->routesProfil();
        $this->enregistrerLeGuide();
        $this->app['auth']->guard('web')->setUser(new FakeUser(niveau: 'membre'));

        $this->assertStringContainsString('aria-label="Guide d\'utilisation"', $this->html());
    }

    public function testLogoutAsksForAYesNoConfirmationBeforeSubmitting(): void
    {
        $this->routesProfil();
        $this->actingAs($this->personne(['membre']));

        $html = $this->html();

        $this->assertMatchesRegularExpression(
            '#<form action="[^"]*/logout" method="POST"[^>]*data-confirm="Voulez-vous vraiment vous déconnecter \?"[^>]*data-confirm-label="Oui"[^>]*data-confirm-cancel-label="Non"[^>]*>#',
            $html,
        );
        $this->assertStringContainsString('data-confirm-title="Déconnexion"', $html);
        // Le formulaire reste un vrai POST + CSRF : sans JS, la déconnexion fonctionne.
        $this->assertMatchesRegularExpression('#<form action="[^"]*/logout" method="POST"[^>]*> <input type="hidden" name="_token"#', $html);
    }
}
