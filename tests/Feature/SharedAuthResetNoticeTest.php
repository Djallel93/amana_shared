<?php
// tests/Feature/SharedAuthResetNoticeTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Feature;

use Amana\Shared\Http\Controllers\AuthController;
use Amana\Shared\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/** « Mot de passe modifié/défini » à la fin du flux de réinitialisation du AuthController partagé. */
class SharedAuthResetNoticeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->routesProfil();
        $this->route('POST', '/nouveau-mot-de-passe', [AuthController::class, 'resetPassword'])->name('password.update');
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    private function reinitialiser(?string $motDePasseActuel): array
    {
        $p = $this->personne([], ['email' => 'sara@amana.test', 'prenom' => 'Sara', 'password' => $motDePasseActuel === null ? null : Hash::make($motDePasseActuel)]);
        $token = Password::broker('personnes')->createToken($p);
        $reponse = $this->call('POST', '/nouveau-mot-de-passe', [
            'token' => $token, 'email' => 'sara@amana.test', 'password' => 'Nouveau-Mdp-9', 'password_confirmation' => 'Nouveau-Mdp-9',
        ]);

        return [$p, $reponse];
    }

    public function testResetOfAnExistingPasswordSendsTheReinitialisationNotice(): void
    {
        [$p, $reponse] = $this->reinitialiser('ancien-mdp-1');

        $this->assertSame(302, $reponse->getStatusCode());
        $this->assertTrue(Hash::check('Nouveau-Mdp-9', $p->refresh()->password));
        $mails = $this->mails();
        $this->assertCount(1, $mails);
        $this->assertSame('sara@amana.test', $mails[0]['to']);
        $this->assertSame('Votre mot de passe AMANA a été modifié', $mails[0]['subject']);
        $this->assertStringContainsString('réinitialisé grâce au lien reçu par email', $mails[0]['html']);
    }

    public function testFirstPasswordOfAnAccountWithoutOneSendsTheCreationNotice(): void
    {
        [, $reponse] = $this->reinitialiser(null);

        $this->assertSame(302, $reponse->getStatusCode());
        $this->assertSame('Votre mot de passe AMANA a été défini', $this->mails()[0]['subject']);
    }

    public function testMailFailureNeverBlocksTheResetButWarns(): void
    {
        $this->app['config']->set('mail.mailers.array', ['transport' => 'inexistant']);
        $this->app['mail.manager']->forgetMailers();

        [$p, $reponse] = $this->reinitialiser('ancien-mdp-1');

        $this->assertSame(302, $reponse->getStatusCode());
        $this->assertTrue(Hash::check('Nouveau-Mdp-9', $p->refresh()->password));
        $this->assertStringContainsString("notification n'a pas pu être envoyée", (string) $this->app['session.store']->get('warning'));
    }

    public function testInvalidTokenSendsNothing(): void
    {
        $p = $this->personne([], ['email' => 'sara@amana.test']);
        $reponse = $this->call('POST', '/nouveau-mot-de-passe', [
            'token' => 'mauvais', 'email' => 'sara@amana.test', 'password' => 'Nouveau-Mdp-9', 'password_confirmation' => 'Nouveau-Mdp-9',
        ]);

        $this->assertSame(302, $reponse->getStatusCode());
        $this->assertSame([], $this->mails());
        $this->assertSame('x', $p->refresh()->password);
    }
}
