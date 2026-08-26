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
 * amana_livraison pour les 6 premiers, + 'Permis'/'Sans permis' ajoutés) —
 * préservés pour rester stables si benevole_profils.id_vehicule_type y
 * fait déjà référence au moment d'un reseed.
 *
 * Comme GeoSeeder : destructif sur cette table (delete avant réinsertion),
 * à lancer manuellement une seule fois — PAS appelé par
 * `amana:migrate-shared` (voir ce fichier). Après un reseed, les valeurs
 * capacite_kg/nombre_part_max redeviennent éditables au besoin via
 * l'écran Paramètres (VehiculeTypesController, amana_web_familles) sans
 * repasser par ce seeder.
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
        ['id' => 7, 'type' => 'Permis', 'capacite_kg' => 0, 'nombre_part_max' => 0],
        ['id' => 8, 'type' => 'Sans permis', 'capacite_kg' => 0, 'nombre_part_max' => 0],
    ];

    public function run(): void
    {
        $connection = DB::connection(config('amana-shared.connection', 'commun'));

        $connection->table('ref_vehicules')->delete();
        $connection->table('ref_vehicules')->insert(self::VEHICULES);
    }
}
