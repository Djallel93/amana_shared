<?php
// src/Models/Application.php

declare(strict_types=1);

namespace Amana\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle pour ref_applications (amana_commun).
 *
 * Référentiel de toutes les applications AMANA partageant amana_commun.
 *
 * @property int    $id
 * @property string $code    ex: planning, familles
 * @property string $libelle ex: AMANA Planning
 * @property bool   $actif
 */
class Application extends Model
{
    protected $table = 'ref_applications';
    public $timestamps = false;

    protected $fillable = ['code', 'libelle', 'actif'];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function getConnectionName(): ?string
    {
        return config('amana-shared.connection', 'commun');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'id_application');
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
