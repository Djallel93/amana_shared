<?php
// src/Notifications/EmailNowLoginNotification.php

declare(strict_types=1);

namespace Amana\Shared\Notifications;

/**
 * Envoyé à la NOUVELLE adresse quand un administrateur (ou le changement
 * confirmé) la substitue à l'ancienne : elle devient l'adresse de connexion.
 */
class EmailNowLoginNotification extends CompteNotification
{
    public function __construct(
        private readonly string $prenom,
        private readonly bool $parAdministrateur,
        private readonly string $quand,
    ) {
    }

    protected function sujet(): string
    {
        return 'Cette adresse est désormais votre adresse de connexion AMANA';
    }

    protected function contenu(): array
    {
        $par = $this->parAdministrateur ? 'par un administrateur' : 'à votre demande';

        return [
            'badge' => 'Votre compte',
            'title' => 'Votre adresse de connexion',
            'prenom' => $this->prenom,
            'paragraphs' => [
                "Cette adresse email a été associée à votre compte AMANA {$par} le {$this->quand} (heure de Paris). "
                . 'Utilisez-la désormais pour vous connecter, avec votre mot de passe habituel.',
            ],
            'ctaUrl' => $this->urlConnexion(),
            'ctaLabel' => '🔐  Se connecter',
            'warnTitle' => 'Vous ne reconnaissez pas cette modification ?',
            'warnText' => 'Contactez-nous à l\'adresse ci-dessous.',
        ];
    }
}
