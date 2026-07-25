<?php
// src/Models/Ville.php

declare(strict_types=1);

namespace Amana\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle pour villes (amana_commun) — référentiel géographique commun
 * (agglomération nantaise), déplacé depuis amana_web_familles le 21/07/2026
 * pour être réutilisable par toute future app AMANA à dimension
 * géographique, sans dupliquer les polygones.
 *
 * @property int    $id
 * @property string $nom
 * @property string|null $code_postal
 * @property string|null $departement
 * @property mixed  $boundary  MULTIPOLYGON SRID 4326
 */
class Ville extends Model
{
    protected $fillable = ['nom', 'code_postal', 'departement', 'boundary'];

    public function getConnectionName(): ?string
    {
        return config('amana-shared.connection', 'commun');
    }

    public function secteurs(): HasMany
    {
        return $this->hasMany(Secteur::class, 'id_ville');
    }
}
