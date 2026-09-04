<?php
// src/Models/Notification.php

declare(strict_types=1);

namespace Amana\Shared\Models;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Remplace le modèle `notifications` stock de Laravel — voir
 * create_notifications_table.php pour le schéma (severity/resolved_at en
 * plus) et Amana\Shared\Notifications\Channels\AmanaDatabaseChannel pour
 * qui écrit dedans.
 *
 * @property string $severity     info|urgent
 * @property \Illuminate\Support\Carbon|null $resolved_at
 */
class Notification extends DatabaseNotification
{
    /**
     * Même précaution que Personne::getConnectionName() : ce modèle vit
     * dans amana_commun, pas la connexion par défaut de l'app
     * consommatrice. Sans cette déclaration explicite, Eloquent
     * hériterait en pratique la connexion 'commun' via
     * Personne::notifications() (le parent Personne la porte déjà) — mais
     * on la déclare quand même en dur, à l'identique de tous les autres
     * modèles partagés de ce package, plutôt que de compter sur ce
     * comportement implicite.
     */
    public function getConnectionName(): ?string
    {
        return config('amana-shared.connection', 'commun');
    }

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function estResolue(): bool
    {
        return $this->severity !== 'urgent' || $this->resolved_at !== null;
    }

    public function marquerResolue(): void
    {
        if ($this->resolved_at === null) {
            $this->forceFill(['resolved_at' => now()])->save();
        }
    }

    public function marquerLue(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }
}
