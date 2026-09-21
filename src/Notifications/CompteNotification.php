<?php
// src/Notifications/CompteNotification.php

declare(strict_types=1);

namespace Amana\Shared\Notifications;

use Amana\Shared\Notifications\Concerns\EmbedsLogo;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Base des emails de sécurité du compte (changement d'email / de mot de
 * passe) — gabarit unique : resources/views/emails/compte.blade.php, habillé
 * comme les autres emails AMANA (partials _head/_header/_footer, logo CID).
 *
 * Pas de ShouldQueue, volontairement (voir NouveauMembreNotification côté
 * apps) : envoi synchrone. Un échec d'envoi ne doit JAMAIS annuler ni bloquer
 * le changement lui-même — c'est AccountChangeNotifier qui attrape et journalise.
 *
 * Ne jamais placer de mot de passe, de hash ou de jeton dans le journal ou
 * dans le contenu (à part le lien de confirmation, qui est le but de l'email).
 */
abstract class CompteNotification extends Notification
{
    use EmbedsLogo;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    abstract protected function sujet(): string;

    /**
     * Données du gabarit compte.blade.php : badge, title, prenom, paragraphs
     * (textes bruts, échappés à l'affichage) et, au choix, infoTitle/infoText,
     * ctaUrl/ctaLabel/ctaNote, warnTitle/warnText.
     *
     * @return array<string, mixed>
     */
    abstract protected function contenu(): array;

    public function toMail(object $notifiable): MailMessage
    {
        Log::info('[' . class_basename($this) . '] Envoi email', [
            'mailer' => config('mail.default'),
        ]);

        return $this->embedLogo(new MailMessage)
            ->subject($this->sujet())
            ->view('amana-shared::emails.compte', array_merge([
                'titleSub' => (string) config('amana-shared.branding.app_name', 'AMANA'),
                'logoCid' => $this->logoCid(),
                'footerText' => 'Vous recevez cet email car votre compte AMANA est concerné par cette modification.',
            ], $this->contenu()));
    }

    /** URL de la page de connexion de l'app courante (repli : racine du site). */
    protected function urlConnexion(): string
    {
        return Route::has('login') ? route('login') : url('/');
    }

    /** URL de « Mot de passe oublié » (repli : page de connexion). */
    protected function urlMotDePasseOublie(): string
    {
        return Route::has('password.request') ? route('password.request') : $this->urlConnexion();
    }
}
