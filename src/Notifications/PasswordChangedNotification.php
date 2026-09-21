<?php
// src/Notifications/PasswordChangedNotification.php

declare(strict_types=1);

namespace Amana\Shared\Notifications;

/**
 * « Mot de passe modifié / défini » — une seule classe pour tous les
 * endroits où un mot de passe est posé, avec un contexte pour que le texte
 * corresponde à la situation :
 *
 *   profil            — changement depuis « Mon profil »
 *   reinitialisation  — fin du flux « mot de passe oublié »
 *   creation          — premier mot de passe (invitation / inscription)
 *   administrateur    — modifié à la demande d'un administrateur
 */
class PasswordChangedNotification extends CompteNotification
{
    public const CONTEXTES = ['profil', 'reinitialisation', 'creation', 'administrateur'];

    public function __construct(
        private readonly string $prenom,
        private readonly string $contexte,
        private readonly string $quand,
    ) {
    }

    private function cree(): bool
    {
        return $this->contexte === 'creation';
    }

    protected function sujet(): string
    {
        return $this->cree()
            ? 'Votre mot de passe AMANA a été défini'
            : 'Votre mot de passe AMANA a été modifié';
    }

    protected function contenu(): array
    {
        $phrase = match ($this->contexte) {
            'profil' => 'Vous avez modifié votre mot de passe depuis votre page « Mon profil »',
            'reinitialisation' => 'Votre mot de passe a été réinitialisé grâce au lien reçu par email',
            'creation' => 'Votre mot de passe a été défini et votre compte est prêt',
            'administrateur' => 'Le mot de passe de votre compte a été modifié à la demande d\'un administrateur',
            default => 'Le mot de passe de votre compte a été modifié',
        };

        return [
            'badge' => 'Sécurité du compte',
            'title' => $this->cree() ? 'Votre mot de passe est défini' : 'Votre mot de passe a été modifié',
            'prenom' => $this->prenom,
            'paragraphs' => [
                "{$phrase} le {$this->quand} (heure de Paris).",
                'Un seul compte et un seul mot de passe servent pour toutes les applications AMANA : '
                . 'vous devrez peut-être vous reconnecter sur les autres.',
            ],
            'ctaUrl' => $this->urlMotDePasseOublie(),
            'ctaLabel' => '🔑  Réinitialiser mon mot de passe',
            'ctaNote' => "À n'utiliser que si vous n'êtes pas à l'origine de cette modification.",
            'warnTitle' => "Ce n'était pas vous ?",
            'warnText' => 'Réinitialisez immédiatement votre mot de passe avec le bouton ci-dessus, '
                . 'puis contactez-nous à l\'adresse ci-dessous.',
        ];
    }
}
