<?php
// src/Notifications/EmailChangedNotification.php

declare(strict_types=1);

namespace Amana\Shared\Notifications;

/**
 * Envoyé à l'ANCIENNE adresse une fois le changement effectué (par la
 * personne ou par un administrateur) : c'est l'alerte qui permet de détecter
 * une prise de contrôle du compte. La nouvelle adresse y est masquée.
 */
class EmailChangedNotification extends CompteNotification
{
    public function __construct(
        private readonly string $prenom,
        private readonly string $nouvelEmailMasque,
        private readonly bool $parAdministrateur,
        private readonly string $quand,
    ) {
    }

    protected function sujet(): string
    {
        return 'Votre adresse email AMANA a été modifiée';
    }

    protected function contenu(): array
    {
        $par = $this->parAdministrateur ? 'par un administrateur' : 'depuis votre page « Mon profil »';

        return [
            'badge' => 'Sécurité du compte',
            'title' => 'Votre adresse email a été modifiée',
            'prenom' => $this->prenom,
            'paragraphs' => [
                "Votre adresse email AMANA a été modifiée {$par} le {$this->quand} (heure de Paris). "
                . "Votre nouvelle adresse de connexion est {$this->nouvelEmailMasque}.",
                "Cette adresse-ci n'est donc plus associée à votre compte.",
            ],
            'warnTitle' => "Ce n'était pas vous ?",
            'warnText' => 'Contactez-nous sans attendre à l\'adresse ci-dessous, afin que nous sécurisions votre compte.',
        ];
    }
}
