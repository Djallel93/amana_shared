<?php
// src/Database/Seeders/VehiculeTypesSeeder.php

declare(strict_types=1);

namespace Amana\Shared\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder : peuplement du référentiel ref_vehicules (amana_commun) — voir
 * create_ref_vehicules_table pour le raisonnement.
 *
 * IDs explicites (mêmes que la table `vehicule` de l'ancien projet
 * amana_livraison pour les 6 premiers, + 'Non véhiculé'/'Sans permis'
 * ajoutés — le premier a été rebaptisé le 26/08/2026, initialement
 * "Permis", trop ambigu à côté de la question "avez-vous le permis ?"
 * déjà posée à l'étape précédente du formulaire) — préservés pour rester
 * stables si benevole_profils.id_vehicule_type y fait déjà référence au
 * moment d'un reseed.
 *
 * upsert() plutôt que delete()+insert() (corrigé le 28/08/2026) : la
 * contrainte FK benevole_profils.id_vehicule_type est en ON DELETE
 * RESTRICT (volontaire — voir create_benevole_profils_table), donc un
 * DELETE sur cette table échoue dès qu'un seul bénévole existe déjà. Pas
 * ce problème avec upsert() (INSERT ... ON DUPLICATE KEY UPDATE), qui ne
 * supprime jamais de ligne — relançable à tout moment, y compris avec des
 * bénévoles déjà en base. Contrepartie assumée : un reseed écrase les
 * valeurs capacite_kg/nombre_part_max éventuellement déjà personnalisées
 * via l'écran Paramètres (VehiculeTypesController, amana_web_familles) —
 * comportement voulu pour un seeder (réétablir l'état de référence), pas
 * un bug.
 *
 * PAS appelé par `amana:migrate-shared` (voir ce fichier) — à lancer
 * manuellement.
 *
 * Lancement : php artisan db:seed --class="Amana\Shared\Database\Seeders\VehiculeTypesSeeder"
 */
class VehiculeTypesSeeder extends Seeder
{
    private const VEHICULES = [
        ['id' => 1, 'type' => 'Citadine', 'capacite_kg' => 150, 'nombre_part_max' => 6],
        ['id' => 2, 'type' => 'Berline', 'capacite_kg' => 250, 'nombre_part_max' => 8],
        ['id' => 3, 'type' => 'Break', 'capacite_kg' => 300, 'nombre_part_max' => 15],
        ['id' => 4, 'type' => 'Monospace', 'capacite_kg' => 400, 'nombre_part_max' => 20],
        ['id' => 5, 'type' => 'Fourgon moyen', 'capacite_kg' => 700, 'nombre_part_max' => 30],
        ['id' => 6, 'type' => 'Grand fourgon', 'capacite_kg' => 1000, 'nombre_part_max' => 50],
        ['id' => 7, 'type' => 'Non véhiculé', 'capacite_kg' => 0, 'nombre_part_max' => 0],
        ['id' => 8, 'type' => 'Sans permis', 'capacite_kg' => 0, 'nombre_part_max' => 0],
    ];

    public function run(): void
    {
        $connection = DB::connection(config('amana-shared.connection', 'commun'));

        $connection->table('ref_vehicules')->upsert(
            self::VEHICULES,
            ['id'],
            ['type', 'capacite_kg', 'nombre_part_max'],
        );
    }
}
