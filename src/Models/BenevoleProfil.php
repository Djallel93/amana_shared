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
 * @property int    $id
 * @property int    $id_personne
 * @property string $langue_preferee
 * @property bool   $permis
 * @property string $vehicule_type
 * @property float|null $capacite_kg
 * @property int|null   $nombre_part_max
 * @property string $statut
 */
class BenevoleProfil extends Model
{
    protected $table = 'benevole_profils';
    public $timestamps = false;

    public const VEHICULE_TYPES = [
        'citadine', 'berline', 'break', 'monospace',
        'camion_utilitaire', 'non_vehicule', 'autre',
    ];

    public const STATUTS = ['Reçu', 'En attente vérification', 'Vérifié', 'Validé', 'Rejeté', 'Archivé'];

    protected $fillable = [
        'id_personne',
        'langue_preferee',
        'permis',
        'vehicule_type',
        'capacite_kg',
        'nombre_part_max',
        'statut',
    ];

    protected $casts = [
        'permis' => 'boolean',
        'capacite_kg' => 'float',
        'nombre_part_max' => 'integer',
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

    public function secteurs(): BelongsToMany
    {
        return $this->belongsToMany(Secteur::class, 'benevole_secteurs', 'id_benevole_profil', 'id_secteur');
    }

    // ── Disponibilités ────────────────────────────────────────────────────
    //
    // Pas de modèle dédié pour ce pivot (clé primaire composite sans colonne
    // id, valeurs de créneau simples) — accès direct via DB::table(), même
    // pattern que RoleService::syncRolePlanning() pour ref_personnes_roles.

    /**
     * @return string[] Créneaux actuellement enregistrés (matin, apres_midi, soir, journee)
     */
    public function disponibilites(): array
    {
        return \Illuminate\Support\Facades\DB::connection($this->getConnectionName())
            ->table('benevole_disponibilites')
            ->where('id_benevole_profil', $this->id)
            ->pluck('creneau')
            ->all();
    }

    /**
     * @param string[] $creneaux
     */
    public function syncDisponibilites(array $creneaux): void
    {
        $connexion = \Illuminate\Support\Facades\DB::connection($this->getConnectionName());

        $connexion->table('benevole_disponibilites')->where('id_benevole_profil', $this->id)->delete();

        $lignes = array_map(
            fn(string $creneau) => ['id_benevole_profil' => $this->id, 'creneau' => $creneau],
            array_values(array_intersect($creneaux, ['matin', 'apres_midi', 'soir', 'journee'])),
        );

        if (!empty($lignes)) {
            $connexion->table('benevole_disponibilites')->insert($lignes);
        }
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
