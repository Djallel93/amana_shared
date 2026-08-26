<?php
// src/Models/BenevoleProfil.php

declare(strict_types=1);

namespace Amana\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Profil bénévole (amana_commun) — voir migration
 * create_benevole_profils_table pour le raisonnement sur l'emplacement
 * (commun plutôt que la base propre à amana_web_familles).
 *
 * Chaque app consommatrice peut étendre localement (voir
 * amana_web_familles/app/Models/Personne.php pour le même schéma
 * d'extension appliqué à Personne) si elle a besoin de scopes propres
 * à son contexte (ex : staffFamilles()).
 *
 * Pas de disponibilités ici (retiré le 24/08/2026, voir migration) —
 * fonctionnalité event-related, hors scope de l'inscription.
 *
 * @property int    $id
 * @property int    $id_personne
 * @property string $langue_preferee
 * @property bool   $permis
 * @property int    $id_vehicule_type
 * @property string $statut
 */
class BenevoleProfil extends Model
{
    protected $table = 'benevole_profils';
    public $timestamps = false;

    public const STATUTS = ['Reçu', 'En attente vérification', 'Vérifié', 'Validé', 'Rejeté', 'Archivé'];

    protected $fillable = [
        'id_personne',
        'langue_preferee',
        'permis',
        'id_vehicule_type',
        'statut',
    ];

    protected $casts = [
        'permis' => 'boolean',
        'derniere_maj' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return config('amana-shared.connection', 'commun');
    }

    public function personne(): BelongsTo
    {
        return $this->belongsTo(Personne::class, 'id_personne');
    }

    public function vehiculeType(): BelongsTo
    {
        return $this->belongsTo(VehiculeType::class, 'id_vehicule_type');
    }

    public function secteurs(): BelongsToMany
    {
        return $this->belongsToMany(Secteur::class, 'benevole_secteurs', 'id_benevole_profil', 'id_secteur');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeRecu($query)
    {
        return $query->where('statut', 'Reçu');
    }

    public function scopeValide($query)
    {
        return $query->where('statut', 'Validé');
    }
}
