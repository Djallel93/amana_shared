<?php
// src/Models/Quartier.php

declare(strict_types=1);

namespace Amana\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle pour quartiers (amana_commun) — voir Ville pour le contexte du
 * déplacement depuis amana_web_familles.
 *
 * Ne porte PAS de relation vers Famille (ou toute autre entité applicative)
 * — quartiers vit dans amana_commun, Famille dans la base propre à
 * amana_web_familles ; une relation Eloquent cross-connexion HasMany
 * fonctionnerait techniquement mais couplerait ce modèle partagé à une
 * seule app consommatrice. Chaque app qui a besoin de la relation inverse
 * l'ajoute dans une extension locale, comme pour Personne (voir
 * amana_web_planning/app/Models/Personne.php pour le même schéma
 * d'extension).
 *
 * @property int    $id
 * @property string $nom
 * @property int    $id_secteur
 * @property mixed  $boundary  MULTIPOLYGON SRID 4326
 */
class Quartier extends Model
{
    protected $fillable = ['nom', 'id_secteur', 'boundary'];

    public function getConnectionName(): ?string
    {
        return config('amana-shared.connection', 'commun');
    }

    public function secteur(): BelongsTo
    {
        return $this->belongsTo(Secteur::class, 'id_secteur');
    }

    /**
     * Pas de colonne id_ville directe sur quartiers — la ville se lit via
     * secteur.ville. Pas de hasOneThrough ici : cette relation suppose une
     * chaîne hasMany, alors que quartier→secteur→ville est une chaîne de
     * belongsTo. Charger la relation imbriquée : Quartier::with('secteur.ville').
     */
    public function ville(): ?Ville
    {
        return $this->secteur?->ville;
    }
}
