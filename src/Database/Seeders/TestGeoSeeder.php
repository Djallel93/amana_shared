<?php
// src/Database/Seeders/TestGeoSeeder.php
//
// Déplacé depuis amana_web_familles le 21/07/2026 — cible désormais
// explicitement DB::connection('commun') plutôt que la connexion par
// défaut de l'app appelante.

declare(strict_types=1);

namespace Amana\Shared\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder de TEST — géographie synthétique minimale et déterministe pour
 * valider une résolution point-in-polygon (ex. resoudreQuartier() dans
 * App\Jobs\ResoudreAdresseFamille côté amana_web_familles) sans dépendre du
 * vrai jeu de données de production (GeoSeeder, 19 villes / 97 quartiers
 * réels, lent à charger et non déterministe pour des assertions de
 * coordonnées précises).
 *
 * Comme GeoSeeder, DESTRUCTEUR sur ces 3 tables (delete avant réinsertion)
 * — réservé aux bases de test jetables, jamais à une base contenant de
 * vraies données géo.
 *
 * Géographie créée (coordonnées arbitraires en degrés, sans rapport avec de
 * vrais lieux — seul le confinement relatif des polygones compte) :
 *
 *   Testville                         : carré lng∈[0,10]  × lat∈[0,10]
 *     └─ Secteur Test
 *         └─ Quartier Interieur       : carré lng∈[2,4]   × lat∈[2,4]
 *
 * Points de test utiles (lng, lat) :
 *   (3, 3)     → dans Quartier Interieur (et dans Testville)
 *   (8, 8)     → dans Testville, hors de tout quartier
 *   (999, 999) → hors de toute ville
 *
 *   php artisan db:seed --class="Amana\Shared\Database\Seeders\TestGeoSeeder"
 */
class TestGeoSeeder extends Seeder
{
    public function run(): void
    {
        $db = DB::connection(config('amana-shared.connection', 'commun'));

        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        $db->table('quartiers')->delete();
        $db->table('secteurs')->delete();
        $db->table('villes')->delete();
        $db->statement('SET FOREIGN_KEY_CHECKS=1');

        $idVille = $db->table('villes')->insertGetId([
            'nom' => 'Testville',
            'code_postal' => '00000',
            'departement' => 'Test',
            'boundary' => $db->raw("ST_GeomFromText('MULTIPOLYGON(((0 0, 0 10, 10 10, 10 0, 0 0)))', 4326)"),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $idSecteur = $db->table('secteurs')->insertGetId([
            'nom' => 'Secteur Test',
            'id_ville' => $idVille,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $db->table('quartiers')->insert([
            'nom' => 'Quartier Interieur',
            'id_secteur' => $idSecteur,
            'boundary' => $db->raw("ST_GeomFromText('MULTIPOLYGON(((2 2, 2 4, 4 4, 4 2, 2 2)))', 4326)"),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command?->info('Géographie de test créée (amana_commun) : 1 ville, 1 secteur, 1 quartier.');
    }
}
