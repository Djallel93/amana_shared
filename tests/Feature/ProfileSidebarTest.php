<?php
// tests/Feature/ProfileSidebarTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Feature;

use Amana\Shared\Tests\Support\FakeUser;
use Amana\Shared\Tests\TestCase;

/** Bloc marque, topbar mobile et footer de la sidebar : profil opt-in, null-safe. */
class ProfileSidebarTest extends TestCase
{
    private function html(): string
    {
        return view('amana-shared::layouts.partials.sidebar')->render();
    }

    public function testInitialsCircleReplacesTheLogoAndOpensTheProfileWhenTheAppRegisteredTheRoute(): void
    {
        $this->routesProfil();
        $p = $this->personne(['membre'], ['prenom' => 'Émilie', 'nom' => 'Durand']);
        $this->actingAs($p);

        $html = $this->html();

        $this->assertStringContainsString('aria-label="Mon profil"', $html);
        $this->assertStringContainsString('href="' . route('profile.edit') . '"', $html);
        $this->assertStringContainsString('>ÉD</span>', $html);
        $this->assertStringContainsString('background-color:' . $p->couleur_avatar, $html);
        $this->assertStringNotContainsString('favicon-96x96.png', $html);
        // Tap target ≥ 44 px sur le lien-pastille (desktop ET topbar mobile).
        $this->assertSame(2, substr_count($html, 'min-w-[44px] min-h-[44px] rounded-full'));
    }

    public function testWordmarkStillLinksToHome(): void
    {
        $this->routesProfil();
        $this->actingAs($this->personne(['membre']));

        $html = preg_replace('/\s+/', ' ', $this->html());

        // Desktop : le texte AMANA est un lien distinct vers l'accueil.
        $this->assertMatchesRegularExpression('#<a href="' . preg_quote(route('home'), '#') . '" class="flex flex-col no-underline min-w-0">\s*<span[^>]*>AMANA</span>#', $html);
        // Mobile : le nom de l'app aussi.
        $this->assertMatchesRegularExpression('#<a href="' . preg_quote(route('home'), '#') . '"[^>]*>' . preg_quote((string) config('amana-shared.branding.app_name'), '#') . '</a>#', $html);
    }

    public function testAppWithoutTheProfileRouteKeepsTheOldLogoAndHasNoProfileLink(): void
    {
        $this->routesProfil();
        // Route de profil absente : on la retire en changeant le nom attendu.
        $this->app['config']->set('amana-shared.profile_route', 'profile.absent');
        $this->actingAs($this->personne(['membre']));

        $html = $this->html();

        $this->assertStringContainsString('favicon-96x96.png', $html);
        $this->assertStringNotContainsString('aria-label="Mon profil"', $html);
        $this->assertStringNotContainsString('ring-white/25', $html);
    }

    public function testUserThatIsNotAPersonneKeepsTheOldLogo(): void
    {
        $this->routesProfil();
        $this->app['auth']->guard('web')->setUser(new FakeUser(niveau: 'membre'));

        $this->assertStringContainsString('favicon-96x96.png', $this->html());
        $this->assertStringNotContainsString('aria-label="Mon profil"', $this->html());
    }

    public function testComposerExposesNoProfileUrlForANonPersonneUser(): void
    {
        $this->routesProfil();
        $this->app['auth']->guard('web')->setUser(new FakeUser(niveau: 'membre'));

        $vue = view('amana-shared::layouts.partials.sidebar');
        $this->app->make(\Amana\Shared\Http\ViewComposers\SidebarComposer::class)->compose($vue);

        $this->assertNull($vue->getData()['profileUrl']);
    }

    public function testGuestRendersWithoutErrorWithOldLogoNoProfileLinkAndNoFooter(): void
    {
        $this->routesProfil();
        $this->actingAs(null);

        $html = $this->html();

        $this->assertStringContainsString('favicon-96x96.png', $html);
        $this->assertStringNotContainsString('aria-label="Mon profil"', $html);
        $this->assertStringNotContainsString('Se déconnecter', $html);
        $this->assertStringNotContainsString('name="_token"', $html);
    }

    public function testProfileCircleIsHighlightedOnProfilePagesOnly(): void
    {
        $this->routesProfil();
        $p = $this->personne(['membre']);
        $this->actingAs($p);
        $this->route('GET', '/mon-profil-test', fn () => $this->html())->name('profile.test');
        $this->app['router']->getRoutes()->refreshNameLookups();

        $sur = $this->call('GET', '/mon-profil-test')->getContent();
        $ailleurs = $this->call('GET', '/')->getContent();

        $this->assertStringContainsString('aria-current="page"', (string) $sur);
        $this->assertStringContainsString('ring-accent-light', (string) $sur);
        $this->assertStringNotContainsString('aria-current="page"', (string) $ailleurs);
    }

    public function testFooterHasNoInitialsCircleAndALabelledLogoutButton(): void
    {
        $this->routesProfil();
        $this->actingAs($this->personne(['membre'], ['prenom' => 'Amana', 'nom' => 'Test']));

        $html = preg_replace('/\s+/', ' ', $this->html());

        // Ancien cercle du footer (w-8 h-8 bg-accent) et ancienne icône ↪ disparus.
        $this->assertStringNotContainsString('w-8 h-8 bg-accent rounded-full', $html);
        $this->assertStringNotContainsString('↪', $html);
        // Bouton de déconnexion : POST + CSRF, libellé, ≥ 44 px, contraste réel (pas text-white/30).
        $this->assertMatchesRegularExpression('#<form action="[^"]*/logout" method="POST"[^>]*> <input type="hidden" name="_token"#', $html);
        $this->assertStringContainsString('Se déconnecter', $html);
        $this->assertStringContainsString('min-h-[44px] flex items-center justify-center gap-2', $html);
        $this->assertStringContainsString('text-white/90', $html);
        // Nom et rôle conservés + bascule de thème + contrat DOM de MobileSidebar.vue.
        $this->assertStringContainsString('Amana Test', $html);
        $this->assertStringContainsString('toggleAppTheme()', $html);
        foreach (['id="mainSidebar"', 'id="sidebarOverlay"', 'id="hamburgerBtn"', 'sidebar-hidden', 'closeSidebar()'] as $contrat) {
            $this->assertStringContainsString($contrat, $html);
        }
    }
}
