<?php
// src/Http/Controllers/AuthController.php

declare(strict_types=1);

namespace Amana\Shared\Http\Controllers;

use Amana\Shared\Models\Personne;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Authentification commune à toutes les apps AMANA : connexion,
 * déconnexion, mot de passe oublié / réinitialisation.
 *
 * Ce qui N'EST PAS ici, volontairement : les flux métier propres à une
 * app (ex. inscription publique / candidature de amana_web_planning).
 * Chaque app garde son propre contrôleur pour ça et route vers
 * AuthController uniquement pour les 6 méthodes ci-dessous.
 *
 * Personnalisation par app via config('amana-shared') :
 *   - home_route            : route de redirection une fois connecté
 *   - branding.app_name      : titre affiché ("AMANA Planning", "AMANA Familles")
 *   - branding.tagline       : sous-titre du panneau gauche
 *   - branding.features      : liste [emoji, libellé] du panneau gauche (login uniquement)
 *   - branding.signup_route_name / signup_label : lien optionnel sous le formulaire
 */
class AuthController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────
    // CONNEXION
    // ──────────────────────────────────────────────────────────────────────

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(config('amana-shared.home_route'));
        }

        return view('amana-shared::auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ], [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'Format d\'email invalide.',
            'password.required' => 'Le mot de passe est obligatoire.',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        $personne = Personne::where('email', $credentials['email'])->first();

        if ($personne && $personne->statut === 'En attente') {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Votre candidature est en attente de validation par un administrateur.']);
        }

        if ($personne && $personne->statut === 'Suspendu') {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Votre compte a été suspendu. Contactez un administrateur.']);
        }

        if ($personne && $personne->statut === 'Archivé') {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Ce compte est archivé.']);
        }

        if ($personne && empty($personne->password)) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Vous n\'avez pas encore créé votre mot de passe. Vérifiez vos emails ou contactez un administrateur.']);
        }

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            audit('login', 'auth');
            session()->flash('success', 'Bienvenue ' . Auth::user()->prenom . ' !');
            return redirect()->intended(route(config('amana-shared.home_route')));
        }

        return back()->withInput($request->only('email'))
            ->withErrors(['email' => 'Email ou mot de passe incorrect.']);
    }

    public function logout(Request $request): RedirectResponse
    {
        audit('logout', 'auth');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'Vous avez été déconnecté.');
    }

    // ──────────────────────────────────────────────────────────────────────
    // MOT DE PASSE OUBLIÉ
    // ──────────────────────────────────────────────────────────────────────

    public function showForgotPassword(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(config('amana-shared.home_route'));
        }

        return view('amana-shared::auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'Format d\'email invalide.',
        ]);

        $status = Password::broker('personnes')->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('success', 'Un lien de réinitialisation a été envoyé à votre adresse email.');
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput()
                ->withErrors(['email' => 'Veuillez patienter avant de demander un nouveau lien.']);
        }

        return back()->with('success', 'Si cette adresse est connue, un lien vous a été envoyé.');
    }

    // ──────────────────────────────────────────────────────────────────────
    // RÉINITIALISATION DU MOT DE PASSE
    // ──────────────────────────────────────────────────────────────────────

    public function showResetPassword(Request $request, string $token): View
    {
        return view('amana-shared::auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ], [
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation ne correspond pas au mot de passe.',
        ]);

        $status = Password::broker('personnes')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Personne $personne, string $password) {
                $personne->forceFill([
                    'password' => \Illuminate\Support\Facades\Hash::make($password),
                ])->setRememberToken(\Illuminate\Support\Str::random(60));

                $personne->save();

                event(new \Illuminate\Auth\Events\PasswordReset($personne));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('success', 'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.');
        }

        return back()->withErrors(['email' => 'Ce lien de réinitialisation est invalide ou a expiré.']);
    }
}
