<?php
// src/Services/AccountChangeNotifier.php

declare(strict_types=1);

namespace Amana\Shared\Services;

use Amana\Shared\Models\Personne;
use Amana\Shared\Notifications\EmailChangeConfirmationNotification;
use Amana\Shared\Notifications\EmailChangedNotification;
use Amana\Shared\Notifications\EmailNowLoginNotification;
use Amana\Shared\Notifications\PasswordChangedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * Point d'entrée UNIQUE des emails de sécurité du compte — utilisé par la
 * page « Mon profil » et par les écrans d'administration / flux de mot de
 * passe de chaque app (voir README « Notifications de sécurité du compte »).
 *
 * Règle d'or : un échec d'envoi ne doit jamais annuler ni bloquer le
 * changement. QUEUE_CONNECTION=sync (envoi inline, hébergement IONOS) : un
 * SMTP en panne lèverait une exception au milieu de la requête. Chaque envoi
 * est donc attrapé et journalisé ici ; les méthodes renvoient false si (au
 * moins) un envoi a échoué, pour que l'appelant affiche l'avertissement
 * « … mais la notification n'a pas pu être envoyée. »
 *
 * Aucun secret dans les journaux (ni mot de passe, ni hash, ni jeton).
 */
class AccountChangeNotifier
{
    /** Message d'avertissement standard quand le changement est fait mais l'email non parti. */
    public const AVERTISSEMENT_ECHEC = "Adresse modifiée, mais la notification n'a pas pu être envoyée.";

    /** Statut renvoyé par sendResetLink() quand l'envoi a échoué (hors statuts du broker). */
    public const STATUT_ECHEC_ENVOI = 'amana.mail_error';

    /** Lien de confirmation → NOUVELLE adresse. */
    public function emailChangeConfirmation(Personne $personne, string $nouvelEmail, string $url): bool
    {
        return $this->envoyer(
            'confirmation email',
            fn () => Notification::route('mail', $nouvelEmail)->notify(new EmailChangeConfirmationNotification(
                (string) $personne->prenom,
                $url,
                EmailChangeLink::VALIDITE_MINUTES,
            )),
        );
    }

    /**
     * Notice à l'ANCIENNE adresse + message à la NOUVELLE. À appeler seulement
     * si l'adresse a réellement changé. $ancienEmail doit être capturé AVANT
     * l'enregistrement (ensuite $personne->email est déjà la nouvelle) :
     * envoi « à la demande » (Notification::route), pas via le modèle.
     */
    public function emailChanged(Personne $personne, string $ancienEmail, string $nouvelEmail, bool $parAdministrateur): bool
    {
        $quand = $this->maintenant();
        $prenom = (string) $personne->prenom;

        $ancien = $this->envoyer(
            'notice ancien email',
            fn () => Notification::route('mail', $ancienEmail)->notify(new EmailChangedNotification(
                $prenom,
                $this->masquer($nouvelEmail),
                $parAdministrateur,
                $quand,
            )),
        );

        $nouveau = $this->envoyer(
            'message nouvel email',
            fn () => Notification::route('mail', $nouvelEmail)->notify(new EmailNowLoginNotification(
                $prenom,
                $parAdministrateur,
                $quand,
            )),
        );

        return $ancien && $nouveau;
    }

    /**
     * « Mot de passe modifié / défini », à l'adresse du compte.
     *
     * @param  string  $contexte  profil | reinitialisation | creation | administrateur
     */
    public function passwordChanged(Personne $personne, string $contexte): bool
    {
        if (! in_array($contexte, PasswordChangedNotification::CONTEXTES, true)) {
            $contexte = 'profil';
        }

        return $this->envoyer(
            'mot de passe modifié',
            fn () => Notification::route('mail', (string) $personne->email)->notify(new PasswordChangedNotification(
                (string) $personne->prenom,
                $contexte,
                $this->maintenant(),
            )),
        );
    }

    /**
     * Action admin « Envoyer un lien de réinitialisation » : e-mail standard
     * du flux « mot de passe oublié » (broker 'personnes'). L'administrateur ne
     * voit ni ne saisit jamais de mot de passe ni de jeton. Audité (acteur =
     * utilisateur connecté, cible = la personne ; jamais le jeton).
     *
     * @return string  Password::RESET_LINK_SENT, Password::RESET_THROTTLED,
     *                 Password::INVALID_USER… ou STATUT_ECHEC_ENVOI
     */
    public function sendResetLink(Personne $cible): string
    {
        try {
            $statut = Password::broker('personnes')->sendResetLink(['email' => $cible->email]);
        } catch (Throwable $e) {
            Log::warning('[AccountChangeNotifier] Envoi du lien de réinitialisation impossible', [
                'personne_id' => $cible->getKey(),
                'erreur' => $e->getMessage(),
            ]);
            $statut = self::STATUT_ECHEC_ENVOI;
        }

        audit('update', 'personnes', (int) $cible->getKey(), null, [
            'action' => 'lien_reinitialisation_envoye',
            'statut_envoi' => $statut,
        ]);

        return $statut;
    }

    /** « jean.dupont@exemple.fr » → « j*******@exemple.fr » (l'ancienne adresse ne doit pas révéler la nouvelle en entier). */
    public function masquer(string $email): string
    {
        [$local, $domaine] = array_pad(explode('@', $email, 2), 2, '');
        if ($domaine === '' || $local === '') {
            return '***';
        }

        return mb_substr($local, 0, 1) . str_repeat('*', min(8, max(mb_strlen($local) - 1, 1))) . '@' . $domaine;
    }

    private function maintenant(): string
    {
        // APP_TIMEZONE=UTC en production : on formate explicitement à Paris.
        return CarbonImmutable::now('Europe/Paris')->format('d/m/Y à H\hi');
    }

    private function envoyer(string $quoi, callable $envoi): bool
    {
        try {
            $envoi();

            return true;
        } catch (Throwable $e) {
            Log::warning("[AccountChangeNotifier] Échec d'envoi ({$quoi})", ['erreur' => $e->getMessage()]);

            return false;
        }
    }
}
