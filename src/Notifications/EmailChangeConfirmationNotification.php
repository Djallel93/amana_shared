<?php
// src/Notifications/EmailChangeConfirmationNotification.php

declare(strict_types=1);

namespace Amana\Shared\Notifications;

/**
 * Envoyé à la NOUVELLE adresse : lien signé, à durée limitée, pour confirmer
 * le changement d'adresse email demandé depuis « Mon profil ». Tant qu'il
 * n'est pas ouvert, l'adresse actuelle reste inchangée.
 */
class EmailChangeConfirmationNotification extends CompteNotification
{
    public function __construct(
        private readonly string $prenom,
        private readonly string $url,
        private readonly int $minutes,
    ) {
    }

    protected function sujet(): string
    {
        return 'Confirmez votre nouvelle adresse email AMANA';
    }

    protected function contenu(): array
    {
        return [
            'badge' => 'Sécurité du compte',
            'title' => 'Confirmez votre nouvelle adresse',
            'prenom' => $this->prenom,
            'paragraphs' => [
                'Vous avez demandé à utiliser cette adresse email pour vous connecter à AMANA. '
                . 'Pour confirmer, cliquez sur le bouton ci-dessous.',
            ],
            'ctaUrl' => $this->url,
            'ctaLabel' => '✉️  Confirmer cette adresse',
            'ctaNote' => "Ce lien est valable {$this->minutes} minutes et ne peut servir qu'une fois.",
            'warnTitle' => "Vous n'êtes pas à l'origine de cette demande ?",
            'warnText' => 'Ignorez simplement cet email : sans votre confirmation, rien ne change.',
        ];
    }
}
