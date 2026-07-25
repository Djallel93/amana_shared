<?php
// src/Models/Secteur.php

declare(strict_types=1);

namespace Amana\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle pour secteurs (amana_commun) — voir Ville pour le contexte du
 * déplacement depuis amana_web_familles.
 *
 * @property int    $id
 * @property string $nom
 * @property int    $id_ville
 */
class Secteur extends Model
{
    protected $fillable = ['nom', 'id_ville'];

    public function getConnectionName(): ?string
    {
        return config('amana-shared.connection', 'commun');
    }

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'id_ville');
    }

    public function quartiers(): HasMany
    {
        return $this->hasMany(Quartier::class, 'id_secteur');
    }
}
