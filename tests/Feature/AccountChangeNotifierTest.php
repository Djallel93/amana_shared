<?php
// tests/Feature/AccountChangeNotifierTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Feature;

use Amana\Shared\Models\AuditLog;
use Amana\Shared\Services\AccountChangeNotifier;
use Amana\Shared\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class AccountChangeNotifierTest extends TestCase
{
    private AccountChangeNotifier $notifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routesProfil();
        $this->notifier = $this->app->make(AccountChangeNotifier::class);
        // 2026-09-20 12:00 UTC = 14h00 à Paris (heure d'été) — APP_TIMEZONE=UTC comme en production.
        Carbon::setTestNow('2026-09-20 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testEmailChangedMailsOldAddressAndNewAddressWithParisTime(): void
    {
        $p = $this->personne([], ['prenom' => 'Sara', 'email' => 'nouveau@amana.test']);

        $ok = $this->notifier->emailChanged($p, 'ancien@amana.test', 'nouveau@amana.test', parAdministrateur: true);

        $this->assertTrue($ok);
        $mails = $this->mails();
        $this->assertCount(2, $mails);
        $this->assertSame('ancien@amana.test', $mails[0]['to']);
        $this->assertStringContainsString('par un administrateur', $mails[0]['html']);
        $this->assertStringContainsString('20/09/2026 à 14h00 (heure de Paris)', $mails[0]['html']);
        $this->assertStringContainsString('n******@amana.test', $mails[0]['html']);
        $this->assertStringNotContainsString('nouveau@amana.test', $mails[0]['html'], "l'ancienne adresse ne voit pas la nouvelle en entier");
        $this->assertSame('nouveau@amana.test', $mails[1]['to']);
        $this->assertStringContainsString('par un administrateur', $mails[1]['html']);
        $this->assertStringContainsString('adresse de connexion', $mails[1]['subject']);
    }

    public function testEmailChangedByThePersonSaysSo(): void
    {
        $p = $this->personne([], ['email' => 'nouveau@amana.test']);

        $this->notifier->emailChanged($p, 'ancien@amana.test', 'nouveau@amana.test', parAdministrateur: false);

        $this->assertStringContainsString('depuis votre page « Mon profil »', $this->mails()[0]['html']);
    }

    public function testPasswordChangedWordingFollowsTheContext(): void
    {
        $p = $this->personne([], ['prenom' => 'Sara', 'email' => 'sara@amana.test']);
        $attendus = [
            'profil' => ['Votre mot de passe AMANA a été modifié', 'depuis votre page « Mon profil »'],
            'reinitialisation' => ['Votre mot de passe AMANA a été modifié', 'réinitialisé grâce au lien reçu par email'],
            'creation' => ['Votre mot de passe AMANA a été défini', 'a été défini et votre compte est prêt'],
            'administrateur' => ['Votre mot de passe AMANA a été modifié', "à la demande d'un administrateur"],
        ];

        foreach ($attendus as $contexte => [$sujet, $phrase]) {
            $this->viderMails();
            $this->assertTrue($this->notifier->passwordChanged($p, $contexte));
            $mail = $this->mails()[0];
            $this->assertSame('sara@amana.test', $mail['to']);
            $this->assertSame($sujet, $mail['subject'], $contexte);
            $this->assertStringContainsString(htmlspecialchars($phrase, ENT_QUOTES), $mail['html'], $contexte);
            $this->assertStringContainsString('20/09/2026 à 14h00', $mail['html']);
        }
    }

    public function testUnknownContextFallsBackToProfileWording(): void
    {
        $p = $this->personne([], ['email' => 'sara@amana.test']);

        $this->assertTrue($this->notifier->passwordChanged($p, 'nimporte-quoi'));
        $this->assertStringContainsString('Mon profil', $this->mails()[0]['html']);
    }

    public function testMailNeverContainsPasswordOrHash(): void
    {
        $p = $this->personne([], ['email' => 'sara@amana.test', 'password' => '$2y$04$abcdefghijklmnopqrstuv']);

        $this->notifier->passwordChanged($p, 'profil');

        $this->assertStringNotContainsString('$2y$', $this->mails()[0]['html']);
    }

    public function testAMailFailureIsCaughtAndReportedAsFalse(): void
    {
        $this->app['config']->set('mail.mailers.array', ['transport' => 'inexistant']);
        $this->app['mail.manager']->forgetMailers();
        $p = $this->personne([], ['email' => 'sara@amana.test']);

        $this->assertFalse($this->notifier->passwordChanged($p, 'profil'));
        $this->assertFalse($this->notifier->emailChanged($p, 'a@amana.test', 'b@amana.test', false));
        $this->assertFalse($this->notifier->emailChangeConfirmation($p, 'b@amana.test', 'https://x'));
    }

    public function testMasking(): void
    {
        $this->assertSame('j********@exemple.fr', $this->notifier->masquer('jean.dupont@exemple.fr'));
        $this->assertSame('a*@x.fr', $this->notifier->masquer('ab@x.fr'));
        $this->assertSame('a*@x.fr', $this->notifier->masquer('a@x.fr'));
        $this->assertSame('***', $this->notifier->masquer('pas-un-email'));
    }

    // ── Action admin « Envoyer un lien de réinitialisation » ─────────────

    public function testAdminResetLinkSendsTheStandardResetEmailAndAuditsWithoutTheToken(): void
    {
        $admin = $this->personne(['admin']);
        $this->actingAs($admin);
        $cible = $this->personne(['membre'], ['email' => 'cible@amana.test']);

        $statut = $this->notifier->sendResetLink($cible);

        $this->assertSame(Password::RESET_LINK_SENT, $statut);
        $mails = $this->mails();
        $this->assertCount(1, $mails);
        $this->assertSame('cible@amana.test', $mails[0]['to']);
        $this->assertStringContainsString('/nouveau-mot-de-passe/', $mails[0]['html']);

        $token = DB::connection('commun')->table('password_reset_tokens')->where('email', 'cible@amana.test')->value('token');
        $this->assertNotNull($token, 'un jeton a bien été créé');

        $audit = AuditLog::query()->latest('id')->first();
        $this->assertSame($admin->id, (int) $audit->user_id, "l'acteur est l'administrateur");
        $this->assertSame($cible->id, (int) $audit->entity_id, 'la cible est la personne');
        $this->assertEquals(['action' => 'lien_reinitialisation_envoye', 'statut_envoi' => Password::RESET_LINK_SENT], $audit->after);
        $this->assertStringNotContainsString((string) $token, json_encode([$audit->before, $audit->after]));
    }

    public function testAdminResetLinkFailureIsCaughtAndAudited(): void
    {
        $this->actingAs($this->personne(['admin']));
        $cible = $this->personne(['membre'], ['email' => 'cible@amana.test']);
        $this->app['config']->set('mail.mailers.array', ['transport' => 'inexistant']);
        $this->app['mail.manager']->forgetMailers();

        $statut = $this->notifier->sendResetLink($cible);

        $this->assertSame(AccountChangeNotifier::STATUT_ECHEC_ENVOI, $statut);
        $this->assertSame(AccountChangeNotifier::STATUT_ECHEC_ENVOI, AuditLog::query()->latest('id')->first()->after['statut_envoi']);
    }
}
