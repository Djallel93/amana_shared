<?php
// src/Http/Controllers/ProfileController.php

declare(strict_types=1);

namespace Amana\Shared\Http\Controllers;

use Amana\Shared\Contracts\ProfileExtension;
use Amana\Shared\Http\Requests\RequestEmailChangeRequest;
use Amana\Shared\Http\Requests\UpdatePasswordRequest;
use Amana\Shared\Http\Requests\UpdateProfileRequest;
use Amana\Shared\Models\Personne;
use Amana\Shared\Services\AccountChangeNotifier;
use Amana\Shared\Services\EmailChangeLink;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Page « Mon profil » — commune à toutes les apps AMANA.
 *
 * Le package n'enregistre AUCUNE route : chaque app déclare les siennes vers
 * ce contrôleur, sous son middleware d'authentification (voir README, section
 * « Page Mon profil »).
 *
 * SÉCURITÉ — le contrôleur n'agit JAMAIS que sur Auth::user() : aucun id de
 * personne dans une route ou une requête. Chaque écriture est une liste
 * blanche explicite (jamais update($request->all()) : $fillable de Personne
 * contient statut et date_debut_planning). Le rôle, statut,
 * date_debut_planning, id, email_verified_at et remember_token ne sont ni
 * exposés ni acceptés ici.
 */
class ProfileController extends Controller
{
    /** Clés que save() d'une extension ne doit jamais recevoir, même si ses règles les couvraient. */
    private const CLES_INTERDITES = [
        'id', 'statut', 'role', 'roles', 'email', 'password', 'remember_token',
        'email_verified_at', 'date_debut_planning',
    ];

    public function __construct(private readonly AccountChangeNotifier $notifier)
    {
    }

    // ── Affichage ────────────────────────────────────────────────────────

    public function edit(): View
    {
        $personne = $this->personne();
        $extension = $this->extension();

        return view('amana-shared::profile.edit', [
            'personne' => $personne,
            'roles' => $this->libellesRoles($personne),
            'extension' => $extension,
            'extensionTitle' => $extension?->title(),
            'extensionFields' => $extension?->fields($personne) ?? [],
            'extensionView' => $extension?->view(),
        ]);
    }

    // ── Mes informations ─────────────────────────────────────────────────

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $personne = $this->personne();
        $donnees = $request->validated();

        $avant = ['nom' => $personne->nom, 'prenom' => $personne->prenom, 'telephone' => $personne->telephone];

        // Affectation explicite champ par champ — pas de fill().
        $personne->nom = $donnees['nom'];
        $personne->prenom = $donnees['prenom'];
        $personne->telephone = $donnees['telephone'] ?? null;
        $personne->save();

        $apres = ['nom' => $personne->nom, 'prenom' => $personne->prenom, 'telephone' => $personne->telephone];
        $this->auditerDiff('profil', (int) $personne->getKey(), $avant, $apres);

        return redirect()->route('profile.edit')->with('success', 'Vos informations ont été mises à jour.');
    }

    // ── Changement d'adresse email (avec confirmation) ───────────────────

    public function requestEmailChange(RequestEmailChangeRequest $request): RedirectResponse
    {
        $personne = $this->personne();
        $nouvelEmail = (string) $request->validated()['email'];

        if (mb_strtolower((string) $personne->email) === $nouvelEmail) {
            return redirect()->route('profile.edit')
                ->withErrors(['email' => "C'est déjà votre adresse actuelle."], 'email');
        }

        // On NE change PAS l'adresse ici : un lien signé et expirant part vers la NOUVELLE adresse.
        $url = EmailChangeLink::make($personne, $nouvelEmail);

        if (! $this->notifier->emailChangeConfirmation($personne, $nouvelEmail, $url)) {
            return redirect()->route('profile.edit')->withErrors([
                'email' => "L'email de confirmation n'a pas pu être envoyé. Réessayez dans quelques minutes.",
            ], 'email');
        }

        audit('update', 'profil', (int) $personne->getKey(), null, ['demande' => 'changement_email']);

        return redirect()->route('profile.edit')->with(
            'success',
            "Un lien de confirmation a été envoyé à {$nouvelEmail}. Votre adresse actuelle ne change qu'après confirmation.",
        );
    }

    public function confirmEmailChange(Request $request): RedirectResponse
    {
        // Défense en profondeur : la route porte déjà le middleware `signed`.
        abort_unless($request->hasValidSignature(), 403);

        $connecte = Auth::user();
        $id = (int) $request->query('id');

        // Le lien doit être ouvert par la personne concernée : sinon on conserve
        // l'URL demandée (intended), on déconnecte le mauvais compte et on
        // renvoie vers la connexion.
        if (! $connecte instanceof Personne || (int) $connecte->getAuthIdentifier() !== $id) {
            $request->session()->put('url.intended', $request->fullUrl());
            if ($connecte) {
                Auth::logout();
            }

            return redirect()->route('login')->with(
                'error',
                'Connectez-vous avec le compte concerné pour confirmer ce changement d\'adresse.',
            );
        }

        $personne = $connecte->fresh() ?? $connecte;
        $nouvelEmail = mb_strtolower(trim((string) $request->query('email')));

        // Le lien meurt dès que l'adresse a changé (empreinte de l'adresse actuelle).
        if (! EmailChangeLink::isCurrent($personne, (string) $request->query('fp'))) {
            return redirect()->route('profile.edit')->with('error', "Ce lien n'est plus valable.");
        }

        if (filter_var($nouvelEmail, FILTER_VALIDATE_EMAIL) === false || $this->emailPris($nouvelEmail, (int) $personne->getKey())) {
            return redirect()->route('profile.edit')
                ->with('error', 'Cette adresse ne peut pas être utilisée. Recommencez avec une autre adresse.');
        }

        $ancienEmail = (string) $personne->email;

        // Les jetons de réinitialisation en cours visaient l'ancienne adresse.
        Password::broker('personnes')->getRepository()->delete($personne);

        try {
            $personne->email = $nouvelEmail;
            // Preuve de possession de la nouvelle adresse (cohérent avec le flux du lien de mot de passe).
            $personne->email_verified_at = now();
            $personne->save();
        } catch (UniqueConstraintViolationException) {
            // Course : quelqu'un a pris l'adresse entre le contrôle et l'écriture.
            return redirect()->route('profile.edit')
                ->with('error', 'Cette adresse ne peut pas être utilisée. Recommencez avec une autre adresse.');
        }

        audit('update', 'profil', (int) $personne->getKey(), ['email' => $ancienEmail], ['email' => $nouvelEmail]);

        $envoye = $this->notifier->emailChanged($personne, $ancienEmail, $nouvelEmail, parAdministrateur: false);

        $retour = redirect()->route('profile.edit')->with('success', 'Votre adresse email a été modifiée.');

        return $envoye ? $retour : $retour->with('warning', AccountChangeNotifier::AVERTISSEMENT_ECHEC);
    }

    // ── Mot de passe ─────────────────────────────────────────────────────

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $personne = $this->personne();

        // Hash explicite : Personne n'a pas de cast `hashed`.
        $personne->password = Hash::make((string) $request->validated()['password']);
        $personne->setRememberToken(Str::random(60));
        $personne->save();

        // Invalide les cookies « se souvenir de moi » ailleurs (jeton régénéré)
        // et régénère l'identifiant de session ici ; on reste connecté.
        Auth::guard()->login($personne);
        $request->session()->regenerate();
        if ($request->session()->has('password_hash_web')) {
            // Compatible avec le middleware AuthenticateSession si l'app l'utilise.
            $request->session()->put('password_hash_web', $personne->getAuthPassword());
        }

        // Ni mot de passe ni hash dans l'audit : seulement le nom du champ.
        audit('update', 'profil', (int) $personne->getKey(), null, ['champ' => 'mot_de_passe']);

        $envoye = $this->notifier->passwordChanged($personne, 'profil');

        $retour = redirect()->route('profile.edit')->with(
            'success',
            'Votre mot de passe a été modifié. Un seul compte sert pour toutes les applications AMANA : '
            . 'vous devrez peut-être vous reconnecter sur les autres.',
        );

        return $envoye
            ? $retour
            : $retour->with('warning', "Mot de passe modifié, mais la notification n'a pas pu être envoyée.");
    }

    // ── Informations spécifiques à l'app ─────────────────────────────────

    public function updateExtra(Request $request): RedirectResponse
    {
        $extension = $this->extension();
        abort_if($extension === null, 404);

        $personne = $this->personne();
        $champs = $extension->fields($personne);

        // Rien à éditer pour cette personne (ex. pas de profil bénévole) : la section
        // n'est pas affichée, la route ne doit pas non plus accepter d'écriture.
        abort_if($champs === [] && $extension->view() === null, 404);

        // Les cases à cocher multiples envoient un champ vide « présent » (voir
        // _extension-fields) : on l'écarte, « aucune case » donne donc bien [].
        $entrees = $request->all();
        foreach ($champs as $champ) {
            if (($champ['type'] ?? null) === 'multiselect') {
                $entrees[$champ['name']] = array_values(array_filter(
                    (array) ($entrees[$champ['name']] ?? []),
                    static fn ($v) => $v !== '' && $v !== null,
                ));
            }
        }

        // validated() ne renvoie que les clés couvertes par rules() ; on
        // écarte en plus tout champ qu'une extension ne doit jamais toucher.
        $valide = Validator::make($entrees, $extension->rules($personne))->validateWithBag('extra');
        $valide = array_diff_key($valide, array_flip(self::CLES_INTERDITES));

        $avant = $this->valeursExtension($extension, $personne);
        $extension->save($personne, $valide);
        $apres = $this->valeursExtension($extension, $personne->fresh() ?? $personne);

        $this->auditerDiff('profil_extra', (int) $personne->getKey(), $avant, $apres);

        return redirect()->route('profile.edit')->with('success', 'Vos informations ont été mises à jour.');
    }

    // ── Aides ────────────────────────────────────────────────────────────

    private function personne(): Personne
    {
        $utilisateur = Auth::user();
        abort_unless($utilisateur instanceof Personne, 403);

        return $utilisateur;
    }

    /** L'extension n'existe que si l'app l'a liée ET a enregistré la route de sauvegarde. */
    private function extension(): ?ProfileExtension
    {
        if (! app()->bound(ProfileExtension::class) || ! Route::has('profile.extra.update')) {
            return null;
        }

        return app(ProfileExtension::class);
    }

    /** @return string[] Libellés des rôles de la personne pour l'app COURANTE (plusieurs possibles). */
    private function libellesRoles(Personne $personne): array
    {
        return $personne->roles()
            ->whereHas('application', fn ($q) => $q->where('code', config('amana-shared.app_code')))
            ->orderBy('ref_roles.libelle')
            ->pluck('ref_roles.libelle')
            ->all();
    }

    private function emailPris(string $email, int $sauf): bool
    {
        return Personne::query()->where('email', $email)->where('id', '!=', $sauf)->exists();
    }

    /** @return array<string, mixed> */
    private function valeursExtension(ProfileExtension $extension, Personne $personne): array
    {
        $valeurs = [];
        foreach ($extension->fields($personne) as $champ) {
            $valeurs[(string) $champ['name']] = $champ['value'] ?? null;
        }

        return $valeurs;
    }

    /**
     * Journalise uniquement les champs qui ont changé (ancienne → nouvelle
     * valeur), jamais de secret.
     *
     * @param  array<string, mixed>  $avant
     * @param  array<string, mixed>  $apres
     */
    private function auditerDiff(string $module, int $id, array $avant, array $apres): void
    {
        $changes = array_keys(array_filter(
            $apres,
            static fn ($valeur, $cle) => ($avant[$cle] ?? null) != $valeur,
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($changes === []) {
            return;
        }

        audit('update', $module, $id, array_intersect_key($avant, array_flip($changes)), array_intersect_key($apres, array_flip($changes)));
    }
}
