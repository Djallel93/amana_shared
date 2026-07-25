<?php
// src/Models/Role.php

declare(strict_types=1);

namespace Amana\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Modèle pour ref_roles (amana_commun).
 *
 * Un rôle est toujours lié à une application spécifique.
 * Exemples : admin → planning, benevole → familles.
 */
class Role extends Model
{
    protected $table = 'ref_roles';
    public $timestamps = false;

    protected $fillable = ['code', 'libelle', 'id_application'];

    public function getConnectionName(): ?string
    {
        return config('amana-shared.connection', 'commun');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'id_application');
    }

    public function personnes(): BelongsToMany
    {
        return $this->belongsToMany(
            Personne::class,
            'ref_personnes_roles',
            'id_role',
            'id_personne'
        )->withPivot('date_attribution');
    }
}
