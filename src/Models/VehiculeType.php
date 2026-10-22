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
 * @property bool   $actif
 */
class VehiculeType extends Model
{
    /**
     * Lignes à logique métier particulière (candidats sans véhicule propre,
     * voir VehiculeTypesSeeder) — jamais désactivables depuis l'écran
     * Paramètres.
     *
     * @var list<int>
     */
    public const IDS_PROTEGES = [7, 8];

    protected $table = 'ref_vehicules';
    public $timestamps = false;

    protected $fillable = ['type', 'capacite_kg', 'nombre_part_max', 'actif'];

    protected $casts = [
        'capacite_kg' => 'float',
        'nombre_part_max' => 'integer',
        'actif' => 'boolean',
    ];

    /**
     * Types proposés dans les choix (formulaire bénévole, pickers).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<VehiculeType>  $query
     * @return \Illuminate\Database\Eloquent\Builder<VehiculeType>
     */
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    public function estProtege(): bool
    {
        return in_array($this->getKey(), self::IDS_PROTEGES, true);
    }

    public function getConnectionName(): ?string
    {
        return config('amana-shared.connection', 'commun');
    }
}
