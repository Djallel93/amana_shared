<?php
// src/Models/VehiculeType.php

declare(strict_types=1);

namespace Amana\Shared\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Référentiel des types de véhicule (amana_commun) — voir migration
 * create_ref_vehicules_table. capacite_kg/nombre_part_max sont éditables
 * par le staff (admin/gestionnaire) via VehiculeTypesController
 * (amana_web_familles), jamais saisis par un bénévole candidat.
 *
 * @property int    $id
 * @property string $type
 * @property float  $capacite_kg
 * @property int    $nombre_part_max
 */
class VehiculeType extends Model
{
    protected $table = 'ref_vehicules';
    public $timestamps = false;

    protected $fillable = ['type', 'capacite_kg', 'nombre_part_max'];

    protected $casts = [
        'capacite_kg' => 'float',
        'nombre_part_max' => 'integer',
    ];

    public function getConnectionName(): ?string
    {
        return config('amana-shared.connection', 'commun');
    }
}
