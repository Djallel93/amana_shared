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

    // boundary est un MULTIPOLYGON (WKB binaire) — jamais de l'UTF-8 valide.
    // Sans ça, toJson()/response()->json() plante avec "Malformed UTF-8
    // characters" dès qu'un appelant sérialise ce modèle (directement ou via
    // une relation eager-loadée), sans lien évident avec la vraie cause.
    protected $hidden = ['boundary'];

    public function getConnectionName(): ?string
    {
        return config('amana-shared.connection', 'commun');
    }

    public function secteurs(): HasMany
    {
        return $this->hasMany(Secteur::class, 'id_ville');
    }
}
