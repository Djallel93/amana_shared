<?php
// src/Notifications/ResetPasswordNotification.php

declare(strict_types=1);

namespace Amana\Shared\Notifications;

/**
 * Email « Réinitialiser votre mot de passe » — remplace l'email par défaut de
 * Laravel (anglais, sans habillage) pour TOUS les flux qui passent par le
 * broker 'personnes' :
 *   - « Mot de passe oublié » (AuthController::sendResetLink, partagé ou propre à l'app) ;
 *   - l'action admin « Envoyer un lien de réinitialisation »
 *     (AccountChangeNotifier::sendResetLink) ;
 *   - toute autre app qui appelle Password::broker('personnes')->sendResetLink().
 *
 * Branché via Personne::sendPasswordResetNotification() : aucune app n'a rien à
 * changer. Le texte reste neutre sur l'origine de la demande (la personne
 * elle-même ou un administrateur), car le broker ne les distingue pas.
 * Les invitations (candidatures validées, accès attribué) ont leurs propres
 * emails côté apps et ne sont pas concernées.
 */
class ResetPasswordNotification extends CompteNotification
{
    public function __construct(
        private readonly string $prenom,
        private readonly string $url,
        private readonly int $minutes,
    ) {
    }

    protected function sujet(): string
    {
        return 'Réinitialisez votre mot de passe AMANA';
    }

    protected function contenu(): array
    {
        return [
            'badge' => 'Mot de passe',
            'title' => 'Réinitialiser votre mot de passe',
            'prenom' => $this->prenom,
            'paragraphs' => [
                'Une demande de réinitialisation du mot de passe de votre compte AMANA a été effectuée '
                . '(par vous ou par un administrateur). Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe.',
                'Un seul compte et un seul mot de passe servent pour toutes les applications AMANA.',
            ],
            'ctaUrl' => $this->url,
            'ctaLabel' => '🔑  Choisir un nouveau mot de passe',
            'ctaNote' => "Ce lien est valable {$this->minutes} minutes.",
            'warnTitle' => "Vous n'êtes pas à l'origine de cette demande ?",
            'warnText' => 'Ignorez simplement cet email : sans clic sur le lien, votre mot de passe actuel reste inchangé.',
        ];
    }
}
