<?php
// tests/Feature/ResetPasswordEmailTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Feature;

use Amana\Shared\Services\AccountChangeNotifier;
use Amana\Shared\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/** Email de réinitialisation : français, habillé AMANA, pour « mot de passe oublié » ET l'action admin. */
class ResetPasswordEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->routesProfil();
    }

    private function verifierMail(array $mail, string $email): void
    {
        $this->assertSame($email, $mail['to']);
        $this->assertSame('Réinitialisez votre mot de passe AMANA', $mail['subject']);
        $html = $mail['html'];
        $this->assertStringContainsString('Cher(e) <em>Sara</em>', $html);
        $this->assertStringContainsString('Choisir un nouveau mot de passe', $html);
        $this->assertStringContainsString('cta-button', $html, 'gabarit AMANA (bouton)');
        $this->assertStringContainsString('Ce lien est valable 60 minutes.', $html);
        $this->assertStringContainsString('reste inchangé', $html);
        $this->assertStringNotContainsString('Reset Password', $html, "plus d'email Laravel par défaut");
        $this->assertStringNotContainsString('You are receiving', $html);
    }

    public function testForgotPasswordFlowSendsTheBrandedFrenchEmailWithAWorkingLink(): void
    {
        $p = $this->personne([], ['prenom' => 'Sara', 'email' => 'sara@amana.test']);

        $statut = Password::broker('personnes')->sendResetLink(['email' => 'sara@amana.test']);

        $this->assertSame(Password::RESET_LINK_SENT, $statut);
        $mails = $this->mails();
        $this->assertCount(1, $mails);
        $this->verifierMail($mails[0], 'sara@amana.test');

        // Le lien porte le jeton EN CLAIR de l'email, dont le hash est en base, et l'adresse.
        preg_match('#href="([^"]*nouveau-mot-de-passe/[^"]*)"#', $mails[0]['html'], $m);
        $lien = html_entity_decode($m[1]);
        preg_match('#nouveau-mot-de-passe/([^?]+)\?email=([^&]+)#', $lien, $parties);
        $this->assertSame('sara%40amana.test', $parties[2]);
        $hash = DB::connection('commun')->table('password_reset_tokens')->where('email', 'sara@amana.test')->value('token');
        $this->assertTrue($this->app['hash']->check($parties[1], $hash));
    }

    public function testAdminResetLinkActionSendsTheSameBrandedEmail(): void
    {
        $this->actingAs($this->personne(['admin']));
        $cible = $this->personne([], ['prenom' => 'Sara', 'email' => 'sara@amana.test']);

        $statut = $this->app->make(AccountChangeNotifier::class)->sendResetLink($cible);

        $this->assertSame(Password::RESET_LINK_SENT, $statut);
        $this->verifierMail($this->mails()[0], 'sara@amana.test');
    }

    public function testExpiryTextFollowsTheBrokerConfiguration(): void
    {
        $this->app['config']->set('auth.passwords.personnes.expire', 90);
        $this->personne([], ['prenom' => 'Sara', 'email' => 'sara@amana.test']);

        Password::broker('personnes')->sendResetLink(['email' => 'sara@amana.test']);

        $this->assertStringContainsString('Ce lien est valable 90 minutes.', $this->mails()[0]['html']);
    }

    public function testMailContainsNoAdminNamesNorPasswords(): void
    {
        $this->personne([], ['prenom' => 'Sara', 'email' => 'sara@amana.test', 'password' => '$2y$04$abcdefghijklmnopqrstuv']);

        Password::broker('personnes')->sendResetLink(['email' => 'sara@amana.test']);

        $this->assertStringNotContainsString('$2y$', $this->mails()[0]['html']);
    }
}
