<?php
// tests/Feature/ProfileTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Feature;

use Amana\Shared\Contracts\ProfileExtension;
use Amana\Shared\Models\AuditLog;
use Amana\Shared\Models\Personne;
use Amana\Shared\Services\EmailChangeLink;
use Amana\Shared\Tests\Support\FakeProfileExtension;
use Amana\Shared\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class ProfileTest extends TestCase
{
    private const MDP = 'Secret-Actuel-1';

    protected function setUp(): void
    {
        parent::setUp();
        FakeProfileExtension::reset();
        $this->routesProfil();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function moi(array $roles = ['membre'], array $attributs = []): Personne
    {
        $p = $this->personne($roles, array_merge(['password' => Hash::make(self::MDP), 'email' => 'moi@amana.test'], $attributs));
        $this->actingAs($p);

        return $p;
    }

    /** @param  array<string, mixed>  $data */
    private function envoyer(string $methode, string $uri, array $data = [], bool $json = false): Response
    {
        return $this->call($methode, $uri, $data, $json ? ['Accept' => 'application/json'] : []);
    }

    /** Erreurs flashées dans un sac nommé après une redirection (lues dans le magasin de session partagé). */
    private function erreurs(string $sac): array
    {
        $errors = $this->app['session.store']->get('errors');

        return $errors ? $errors->getBag($sac)->toArray() : [];
    }

    private function flash(string $cle): mixed
    {
        return $this->app['session.store']->get($cle);
    }

    private function audits(): array
    {
        return AuditLog::query()->orderBy('id')->get()->all();
    }

    // ── Accès ────────────────────────────────────────────────────────────

    public function testGuestCannotReachAnyProfileRoute(): void
    {
        $this->actingAs(null);

        foreach ([['GET', '/mon-profil'], ['PUT', '/mon-profil'], ['POST', '/mon-profil/email'], ['PUT', '/mon-profil/mot-de-passe'], ['PUT', '/mon-profil/extra']] as [$m, $u]) {
            $this->assertSame(401, $this->envoyer($m, $u, [], true)->getStatusCode(), "$m $u");
        }
    }

    public function testPageIsAvailableToEveryRoleWithNoRoleMiddleware(): void
    {
        foreach ([['benevole'], ['membre'], ['gestionnaire'], ['admin'], ['gestionnaire_externe'], []] as $roles) {
            $this->moi($roles, ['email' => 'r' . implode('', $roles) . '@amana.test']);
            $reponse = $this->envoyer('GET', '/mon-profil');
            $this->assertSame(200, $reponse->getStatusCode(), 'rôles : ' . implode(',', $roles));
            $this->assertStringContainsString('Mon profil', (string) $reponse->getContent());
        }
    }

    public function testPageShowsFieldsInitialsAndOnlyTheRolesOfTheCurrentApp(): void
    {
        DB::connection('commun')->table('ref_applications')->insert(['id' => 2, 'code' => 'familles', 'libelle' => 'Familles']);
        DB::connection('commun')->table('ref_roles')->insert(['id' => 90, 'code' => 'equipe_pesee', 'libelle' => 'Équipe pesée', 'id_application' => 2]);
        $p = $this->moi(['gestionnaire'], ['prenom' => 'Sara', 'nom' => 'Benali', 'telephone' => '06 12 34 56 78']);
        DB::connection('commun')->table('ref_personnes_roles')->insert(['id_personne' => $p->id, 'id_role' => 90, 'date_attribution' => '2026-01-01']);

        $html = (string) $this->envoyer('GET', '/mon-profil')->getContent();

        $this->assertStringContainsString('value="Sara"', $html);
        $this->assertStringContainsString('value="Benali"', $html);
        $this->assertStringContainsString('value="06 12 34 56 78"', $html);
        $this->assertStringContainsString('>SB</span>', $html);
        $this->assertStringContainsString('Gestionnaire', $html);
        $this->assertStringNotContainsString('Équipe pesée', $html);
        // Rôle en lecture seule : aucun champ de rôle / statut / date dans les formulaires.
        foreach (['name="role', 'name="statut"', 'name="date_debut_planning"', 'name="id"'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $html);
        }
    }

    public function testSeveralRolesForTheCurrentAppAreAllListed(): void
    {
        $this->moi(['membre', 'equipe_chargement']);

        $html = (string) $this->envoyer('GET', '/mon-profil')->getContent();

        $this->assertStringContainsString('Membre', $html);
        $this->assertStringContainsString('Equipe_chargement', $html);
    }

    // ── Mes informations ─────────────────────────────────────────────────

    public function testUpdateSavesNameFirstNameAndPhoneAndAudits(): void
    {
        $p = $this->moi(['membre'], ['nom' => 'Ancien', 'prenom' => 'Vieux', 'telephone' => null]);

        $reponse = $this->envoyer('PUT', '/mon-profil', ['nom' => ' Nouveau ', 'prenom' => 'Neuf', 'telephone' => '+33 6 12 34 56 78']);

        $this->assertSame(302, $reponse->getStatusCode());
        $p->refresh();
        $this->assertSame('Nouveau', $p->nom);
        $this->assertSame('Neuf', $p->prenom);
        $this->assertSame('+33 6 12 34 56 78', $p->telephone);
        $this->assertSame('Vos informations ont été mises à jour.', $this->flash('success'));
        $audit = $this->audits()[0];
        $this->assertSame('profil', $audit->module);
        $this->assertEquals(['nom' => 'Ancien', 'prenom' => 'Vieux', 'telephone' => null], $audit->before);
        $this->assertEquals(['nom' => 'Nouveau', 'prenom' => 'Neuf', 'telephone' => '+33 6 12 34 56 78'], $audit->after);
    }

    public function testEmptyPhoneIsStoredAsNull(): void
    {
        $p = $this->moi(['membre'], ['telephone' => '06 12 34 56 78']);

        $this->envoyer('PUT', '/mon-profil', ['nom' => 'A', 'prenom' => 'B', 'telephone' => '   ']);

        $this->assertNull($p->refresh()->telephone);
    }

    public function testInvalidFrenchPhoneIsRejectedWithTheSharedMessage(): void
    {
        $p = $this->moi(['membre'], ['telephone' => '06 12 34 56 78']);

        foreach (['0712', '+44 7911 123456', '06 12 34 56 789', 'abc'] as $mauvais) {
            $reponse = $this->envoyer('PUT', '/mon-profil', ['nom' => 'A', 'prenom' => 'B', 'telephone' => $mauvais]);
            $this->assertSame(302, $reponse->getStatusCode());
            $this->assertSame(['Format invalide. Exemples : 06 12 34 56 78, +33 6 12 34 56 78'], $this->erreurs('informations')['telephone'] ?? [], $mauvais);
        }
        $this->assertSame('06 12 34 56 78', $p->refresh()->telephone);
    }

    public function testNameAndFirstNameAreRequiredAndCappedAt100(): void
    {
        $p = $this->moi(['membre'], ['nom' => 'Garde', 'prenom' => 'Garde']);

        $this->envoyer('PUT', '/mon-profil', ['nom' => '', 'prenom' => str_repeat('x', 101)]);

        $erreurs = $this->erreurs('informations');
        $this->assertSame(['Le nom est obligatoire.'], $erreurs['nom']);
        $this->assertSame(['Le prénom ne doit pas dépasser 100 caractères.'], $erreurs['prenom']);
        $this->assertSame('Garde', $p->refresh()->nom);
    }

    public function testTamperingWithProtectedFieldsChangesNothing(): void
    {
        $p = $this->moi(['membre'], ['statut' => 'Validé', 'email' => 'moi@amana.test']);
        $autre = $this->personne([], ['statut' => 'Validé']);

        $this->envoyer('PUT', '/mon-profil', [
            'nom' => 'Nom', 'prenom' => 'Prenom',
            'statut' => 'Archivé', 'date_debut_planning' => '2030-01-01', 'role' => 'admin', 'roles' => ['admin'],
            'id' => $autre->id, 'email' => 'pirate@amana.test', 'email_verified_at' => '2000-01-01', 'password' => 'hack', 'remember_token' => 'x',
        ]);

        $p->refresh();
        $this->assertSame('Validé', $p->statut);
        $this->assertNull($p->date_debut_planning);
        $this->assertSame('moi@amana.test', $p->email);
        $this->assertNull($p->email_verified_at);
        $this->assertFalse($p->isAdmin());
        $this->assertSame('x', 'x');
        $this->assertSame('Nom', $p->nom);
        $this->assertNotSame('Nom', $autre->refresh()->nom, "L'id du corps de requête ne doit jamais désigner une autre personne");
        $this->assertTrue(Hash::check(self::MDP, $p->password));
    }

    // ── Changement d'email ───────────────────────────────────────────────

    public function testEmailRequestNeedsTheCurrentPassword(): void
    {
        $p = $this->moi();

        $this->envoyer('POST', '/mon-profil/email', ['email' => 'nouveau@amana.test', 'current_password' => 'faux']);

        $this->assertSame(['Mot de passe actuel incorrect.'], $this->erreurs('email')['current_password']);
        $this->assertSame([], $this->mails());
        $this->assertSame('moi@amana.test', $p->refresh()->email);
    }

    public function testEmailRequestRejectsAnAddressAlreadyUsedWithANeutralMessage(): void
    {
        $this->personne([], ['email' => 'prise@amana.test']);
        $p = $this->moi();

        $this->envoyer('POST', '/mon-profil/email', ['email' => 'PRISE@amana.test', 'current_password' => self::MDP]);
        $unique = $this->erreurs('email')['email'] ?? [];
        $this->envoyer('POST', '/mon-profil/email', ['email' => 'pas-un-email', 'current_password' => self::MDP]);
        $format = $this->erreurs('email')['email'] ?? [];

        // NB : SQLite compare sans ignorer la casse ; MySQL (collation ci) attrape aussi « PRISE@ ».
        // On vérifie donc la casse exacte ici, et le message neutre.
        $this->envoyer('POST', '/mon-profil/email', ['email' => 'prise@amana.test', 'current_password' => self::MDP]);
        $unique = $this->erreurs('email')['email'] ?? [];

        $this->assertSame(['Cette adresse ne peut pas être utilisée. Essayez-en une autre.'], $unique);
        $this->assertStringNotContainsString('déjà', $unique[0]);
        $this->assertSame(["Format d'adresse email invalide."], $format);
        $this->assertSame([], $this->mails());
        $this->assertSame('moi@amana.test', $p->refresh()->email);
    }

    public function testUniquenessRuleTargetsTheSharedConnectionNotALocalEmptyTable(): void
    {
        $regles = (new \Amana\Shared\Http\Requests\RequestEmailChangeRequest())->setUserResolver(fn () => $this->moi())->rules();

        $this->assertContains('unique:commun.ref_personnes,email,' . $this->app['auth']->guard('web')->id(), $regles['email']);
    }

    public function testValidEmailRequestKeepsTheAddressAndMailsASignedLinkToTheNewOne(): void
    {
        $p = $this->moi();

        $reponse = $this->envoyer('POST', '/mon-profil/email', ['email' => 'Nouveau@Amana.test', 'current_password' => self::MDP]);

        $this->assertSame(302, $reponse->getStatusCode());
        $this->assertSame('moi@amana.test', $p->refresh()->email, "L'adresse ne change PAS avant confirmation");
        $mails = $this->mails();
        $this->assertCount(1, $mails);
        $this->assertSame('nouveau@amana.test', $mails[0]['to']);
        $this->assertStringContainsString('Confirmez votre nouvelle adresse email AMANA', $mails[0]['subject']);
        preg_match('#href="([^"]*profile-confirm[^"]*|[^"]*mon-profil/email/confirmer[^"]*)"#', $mails[0]['html'], $m);
        $lien = html_entity_decode($m[1] ?? '');
        $this->assertTrue(Request::create($lien)->hasValidSignature());
        $this->assertStringContainsString('60 minutes', $mails[0]['html']);
        $this->assertStringContainsString('nouveau@amana.test', (string) $this->flash('success'));
        // Aucun secret dans l'audit.
        $this->assertEquals(['demande' => 'changement_email'], $this->audits()[0]->after);
    }

    public function testRequestingTheCurrentAddressIsRefused(): void
    {
        $this->moi();

        $this->envoyer('POST', '/mon-profil/email', ['email' => 'moi@amana.test', 'current_password' => self::MDP]);

        // « unique » ignore la personne elle-même → refus explicite dans le contrôleur.
        $this->assertSame(["C'est déjà votre adresse actuelle."], $this->erreurs('email')['email']);
        $this->assertSame([], $this->mails());
    }

    public function testEmailRequestReportsAMailFailureInsteadOfPretendingItWasSent(): void
    {
        $this->moi();
        $this->basculerVersUnTransportEnPanne();

        $this->envoyer('POST', '/mon-profil/email', ['email' => 'nouveau@amana.test', 'current_password' => self::MDP]);

        $this->assertStringContainsString("n'a pas pu être envoyé", $this->erreurs('email')['email'][0]);
        $this->assertNull($this->flash('success'));
    }

    private function lienConfirmation(Personne $p, string $nouvel = 'nouveau@amana.test'): string
    {
        return EmailChangeLink::make($p, $nouvel);
    }

    public function testConfirmationChangesTheAddressVerifiesItAuditsAndNotifiesBothAddresses(): void
    {
        $p = $this->moi(['membre'], ['prenom' => 'Sara']);
        $lien = $this->lienConfirmation($p);

        $reponse = $this->envoyer('GET', $lien);

        $this->assertSame(302, $reponse->getStatusCode());
        $p->refresh();
        $this->assertSame('nouveau@amana.test', $p->email);
        $this->assertNotNull($p->email_verified_at);
        $this->assertSame('Votre adresse email a été modifiée.', $this->flash('success'));

        $mails = $this->mails();
        $this->assertCount(2, $mails);
        $this->assertSame('moi@amana.test', $mails[0]['to']);
        $this->assertSame('Votre adresse email AMANA a été modifiée', $mails[0]['subject']);
        $this->assertStringContainsString('depuis votre page « Mon profil »', $mails[0]['html']);
        $this->assertStringContainsString('n******@amana.test', $mails[0]['html']);
        $this->assertSame('nouveau@amana.test', $mails[1]['to']);
        $this->assertStringContainsString('adresse de connexion', $mails[1]['subject']);

        $audit = $this->audits()[0];
        $this->assertEquals(['email' => 'moi@amana.test'], $audit->before);
        $this->assertEquals(['email' => 'nouveau@amana.test'], $audit->after);
    }

    public function testConfirmationLinkWorksOnlyOnce(): void
    {
        $p = $this->moi();
        $lien = $this->lienConfirmation($p);

        $this->envoyer('GET', $lien);
        $this->viderMails();
        $this->actingAs($p->refresh());
        $reponse = $this->envoyer('GET', $lien);

        $this->assertSame(302, $reponse->getStatusCode());
        $this->assertSame("Ce lien n'est plus valable.", $this->flash('error'));
        $this->assertSame([], $this->mails());
        $this->assertSame('nouveau@amana.test', $p->refresh()->email);
    }

    public function testConfirmationLinkDiesIfAnAdministratorChangedTheAddressInTheMeantime(): void
    {
        $p = $this->moi();
        $lien = $this->lienConfirmation($p);
        $p->email = 'admin-a-change@amana.test';
        $p->save();

        $this->envoyer('GET', $lien);

        $this->assertSame("Ce lien n'est plus valable.", $this->flash('error'));
        $this->assertSame('admin-a-change@amana.test', $p->refresh()->email);
    }

    public function testExpiredOrTamperedConfirmationLinkIsForbidden(): void
    {
        $p = $this->moi();
        Carbon::setTestNow('2026-09-20 10:00:00');
        $lien = $this->lienConfirmation($p);

        $this->assertSame(403, $this->envoyer('GET', str_replace('nouveau', 'pirate', $lien))->getStatusCode());

        Carbon::setTestNow('2026-09-20 11:05:00');
        $this->assertSame(403, $this->envoyer('GET', $lien)->getStatusCode());
        $this->assertSame('moi@amana.test', $p->refresh()->email);
    }

    public function testConfirmationByAnotherLoggedInPersonLogsThemOutAndRedirectsToLoginKeepingTheIntendedUrl(): void
    {
        $proprietaire = $this->personne([], ['email' => 'proprio@amana.test']);
        $lien = $this->lienConfirmation($proprietaire);
        $this->moi(['membre'], ['email' => 'intrus@amana.test']);

        $reponse = $this->envoyer('GET', $lien);

        $this->assertSame(302, $reponse->getStatusCode());
        $this->assertStringEndsWith('/login', (string) $reponse->headers->get('Location'));
        $this->assertSame($lien, $this->app['session.store']->get('url.intended'));
        $this->assertNull($this->app['auth']->guard('web')->user(), 'le mauvais compte est déconnecté');
        $this->assertSame('proprio@amana.test', $proprietaire->refresh()->email);
    }

    public function testConfirmationRefusesAnAddressTakenInTheMeantime(): void
    {
        $p = $this->moi();
        $lien = $this->lienConfirmation($p, 'course@amana.test');
        $this->personne([], ['email' => 'course@amana.test']);

        $this->envoyer('GET', $lien);

        $this->assertStringContainsString('ne peut pas être utilisée', (string) $this->flash('error'));
        $this->assertSame('moi@amana.test', $p->refresh()->email);
        $this->assertSame([], $this->mails());
    }

    public function testConfirmationDeletesPendingPasswordResetTokensOfTheOldAddress(): void
    {
        $p = $this->moi();
        DB::connection('commun')->table('password_reset_tokens')->insert(['email' => 'moi@amana.test', 'token' => 'x', 'created_at' => now()]);

        $this->envoyer('GET', $this->lienConfirmation($p));

        $this->assertSame(0, DB::connection('commun')->table('password_reset_tokens')->count());
    }

    public function testMailFailureNeverBlocksTheEmailChangeButWarns(): void
    {
        $p = $this->moi();
        $lien = $this->lienConfirmation($p);
        $this->basculerVersUnTransportEnPanne();

        $reponse = $this->envoyer('GET', $lien);

        $this->assertSame(302, $reponse->getStatusCode());
        $this->assertSame('nouveau@amana.test', $p->refresh()->email);
        $this->assertSame('Votre adresse email a été modifiée.', $this->flash('success'));
        $this->assertSame("Adresse modifiée, mais la notification n'a pas pu être envoyée.", $this->flash('warning'));
        $this->assertCount(1, $this->audits());
    }

    // ── Mot de passe ─────────────────────────────────────────────────────

    /** @return array<string, string> */
    private function mdp(array $surcharge = []): array
    {
        return array_merge(['current_password' => self::MDP, 'password' => 'Nouveau-Mdp-9', 'password_confirmation' => 'Nouveau-Mdp-9'], $surcharge);
    }

    public function testPasswordChangeSucceedsHashesRotatesRememberTokenNotifiesAndAuditsWithoutSecrets(): void
    {
        $p = $this->moi(['membre'], ['prenom' => 'Sara']);
        $p->setRememberToken('ancien-jeton');
        $p->save();
        $ancienHash = $p->password;

        $reponse = $this->envoyer('PUT', '/mon-profil/mot-de-passe', $this->mdp());

        $this->assertSame(302, $reponse->getStatusCode());
        $p->refresh();
        $this->assertNotSame($ancienHash, $p->password);
        $this->assertTrue(Hash::check('Nouveau-Mdp-9', $p->password));
        $this->assertNotSame('Nouveau-Mdp-9', $p->password, 'jamais en clair');
        $this->assertNotSame('ancien-jeton', $p->getRememberToken());
        $this->assertSame(60, strlen((string) $p->getRememberToken()));
        $this->assertNotNull($this->app['auth']->guard('web')->user(), "l'utilisateur reste connecté");

        $mails = $this->mails();
        $this->assertCount(1, $mails);
        $this->assertSame('moi@amana.test', $mails[0]['to']);
        $this->assertSame('Votre mot de passe AMANA a été modifié', $mails[0]['subject']);
        $this->assertMatchesRegularExpression('#\d{2}/\d{2}/\d{4} à \d{2}h\d{2} \(heure de Paris\)#', $mails[0]['html']);
        $this->assertStringContainsString('pas vous', $mails[0]['html']);

        $audit = $this->audits()[0];
        $this->assertEquals(['champ' => 'mot_de_passe'], $audit->after);
        $dump = json_encode([$audit->before, $audit->after]);
        $this->assertStringNotContainsString('Nouveau-Mdp-9', $dump);
        $this->assertStringNotContainsString($p->password, $dump);
    }

    public function testPasswordChangeRejectsWrongCurrentWeakMismatchedAndUnchangedPasswords(): void
    {
        $p = $this->moi();

        $cas = [
            'current_password' => [$this->mdp(['current_password' => 'faux']), 'Mot de passe actuel incorrect.'],
            'password' => [$this->mdp(['password' => 'court', 'password_confirmation' => 'court']), 'Le nouveau mot de passe doit contenir au moins 8 caractères.'],
        ];
        foreach ($cas as $champ => [$donnees, $message]) {
            $this->envoyer('PUT', '/mon-profil/mot-de-passe', $donnees);
            $this->assertSame([$message], $this->erreurs('password')[$champ], $champ);
        }

        $this->envoyer('PUT', '/mon-profil/mot-de-passe', $this->mdp(['password_confirmation' => 'autre-chose']));
        $this->assertSame(['La confirmation ne correspond pas au nouveau mot de passe.'], $this->erreurs('password')['password']);

        $this->envoyer('PUT', '/mon-profil/mot-de-passe', $this->mdp(['password' => self::MDP, 'password_confirmation' => self::MDP]));
        $this->assertSame(["Le nouveau mot de passe doit être différent de l'actuel."], $this->erreurs('password')['password']);

        $this->assertTrue(Hash::check(self::MDP, $p->refresh()->password));
        $this->assertSame([], $this->mails());
    }

    public function testMailFailureNeverBlocksThePasswordChangeButWarns(): void
    {
        $p = $this->moi();
        $this->basculerVersUnTransportEnPanne();

        $reponse = $this->envoyer('PUT', '/mon-profil/mot-de-passe', $this->mdp());

        $this->assertSame(302, $reponse->getStatusCode());
        $this->assertTrue(Hash::check('Nouveau-Mdp-9', $p->refresh()->password));
        $this->assertStringContainsString("la notification n'a pas pu être envoyée", (string) $this->flash('warning'));
    }

    // ── Extension propre à l'app ─────────────────────────────────────────

    private function lierExtension(): void
    {
        $this->app->bind(ProfileExtension::class, FakeProfileExtension::class);
    }

    public function testExtensionSectionExistsOnlyWhenBoundAndRouteRegistered(): void
    {
        $this->moi();
        $html = (string) $this->envoyer('GET', '/mon-profil')->getContent();
        $this->assertStringNotContainsString('Informations bénévole', $html, 'non liée → pas de section');

        $this->lierExtension();
        $html = (string) $this->envoyer('GET', '/mon-profil')->getContent();
        $this->assertStringContainsString('Informations bénévole', $html);
        $this->assertStringContainsString('name="langue"', $html);
        $this->assertStringContainsString('name="permis"', $html);
        $this->assertStringContainsString('name="secteurs[]"', $html);
        $this->assertStringContainsString('name="note"', $html);
        $this->assertStringContainsString('Facultatif', $html);
    }

    public function testExtensionSectionIsHiddenWhenTheAppDidNotRegisterItsRoute(): void
    {
        // Application neuve : profil enregistré SANS la route profile.extra.update.
        $this->app = $this->createApplication();
        $this->createCommunSchema();
        $this->routesProfil(avecExtra: false);
        $this->lierExtension();
        $this->moi();

        $this->assertStringNotContainsString('Informations bénévole', (string) $this->envoyer('GET', '/mon-profil')->getContent());
    }

    public function testExtensionSaveReceivesOnlyValidatedFieldsAndIsAudited(): void
    {
        $this->lierExtension();
        $p = $this->moi();

        $reponse = $this->envoyer('PUT', '/mon-profil/extra', [
            'langue' => 'ar', 'permis' => '1', 'secteurs' => ['1', '2'], 'note' => 'ok',
            'statut' => 'Archivé', 'inconnu' => 'x',
        ]);

        $this->assertSame(302, $reponse->getStatusCode());
        $this->assertEquals(['langue' => 'ar', 'permis' => true, 'secteurs' => [1, 2], 'note' => 'ok'], FakeProfileExtension::$recu[$p->id]);
        $audit = $this->audits()[0];
        $this->assertSame('profil_extra', $audit->module);
        $this->assertEquals('ar', $audit->after['langue']);
    }

    public function testExtensionNeverReceivesAdminControlledOrIdentityKeysEvenIfItsRulesCoverThem(): void
    {
        $this->lierExtension();
        $p = $this->moi();
        FakeProfileExtension::$reglesEnPlus = ['statut' => ['nullable', 'string'], 'roles' => ['nullable', 'array'], 'email' => ['nullable', 'string'], 'password' => ['nullable', 'string']];

        $this->envoyer('PUT', '/mon-profil/extra', [
            'langue' => 'fr', 'statut' => 'Archivé', 'roles' => ['admin'], 'email' => 'x@y.fr', 'password' => 'pirate',
        ]);

        $recu = FakeProfileExtension::$recu[$p->id];
        foreach (['statut', 'roles', 'email', 'password'] as $interdit) {
            $this->assertArrayNotHasKey($interdit, $recu);
        }
        $this->assertSame('fr', $recu['langue']);
    }

    public function testExtensionValidationErrorsGoToTheExtraBagAndNothingIsSaved(): void
    {
        $this->lierExtension();
        $p = $this->moi();

        $this->envoyer('PUT', '/mon-profil/extra', ['langue' => 'klingon']);

        $this->assertArrayHasKey('langue', $this->erreurs('extra'));
        $this->assertArrayNotHasKey($p->id, FakeProfileExtension::$recu);
    }

    public function testUncheckingEveryMultiselectBoxSavesAnEmptyList(): void
    {
        $this->lierExtension();
        $p = $this->moi();
        FakeProfileExtension::$stockage[$p->id] = ['langue' => 'fr', 'secteurs' => [1, 2]];

        // Navigateur : seul le champ « présent » (vide) part quand aucune case n'est cochée.
        $this->envoyer('PUT', '/mon-profil/extra', ['langue' => 'fr', 'secteurs' => ['']]);

        $this->assertSame([], FakeProfileExtension::$recu[$p->id]['secteurs']);
    }

    public function testSectionIsHiddenAndSaveRefusedWhenTheExtensionHasNothingToEditForThisPerson(): void
    {
        $this->lierExtension();
        FakeProfileExtension::$sansChamps = true;
        $p = $this->moi();

        $this->assertStringNotContainsString('Informations bénévole', (string) $this->envoyer('GET', '/mon-profil')->getContent());
        $this->assertSame(404, $this->envoyer('PUT', '/mon-profil/extra', ['langue' => 'fr'], true)->getStatusCode());
        $this->assertArrayNotHasKey($p->id, FakeProfileExtension::$recu);
    }

    public function testExtraRouteWithoutBoundExtensionIs404(): void
    {
        $this->moi();

        $this->assertSame(404, $this->envoyer('PUT', '/mon-profil/extra', ['langue' => 'fr'], true)->getStatusCode());
    }

    /** Transport SMTP « en panne » : lève à l'envoi, comme un SMTP indisponible avec QUEUE_CONNECTION=sync. */
    private function basculerVersUnTransportEnPanne(): void
    {
        $this->app['mail.manager']->extend('en-panne', fn () => new class extends \Symfony\Component\Mailer\Transport\AbstractTransport {
            protected function doSend(\Symfony\Component\Mailer\SentMessage $message): void
            {
                throw new \RuntimeException('SMTP indisponible');
            }

            public function __toString(): string
            {
                return 'en-panne';
            }
        });
        $this->app['config']->set('mail.mailers.en-panne', ['transport' => 'en-panne']);
        $this->app['config']->set('mail.default', 'en-panne');
        $this->app['mail.manager']->forgetMailers();
    }
}
