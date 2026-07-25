<?php
// database/migrations/2026_07_23_000002_create_quartiers_table.php
//
// Déplacée depuis amana_web_familles — voir create_villes_table.php pour
// le contexte du déplacement vers amana_shared.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Résolution géographique (point-in-polygon), à charge de l'app appelante :
 *   1. ST_Contains(villes.boundary, POINT(lng, lat))    → trouve la ville
 *   2. ST_Contains(quartiers.boundary, POINT(lng, lat)) → trouve le quartier
 *      (filtré aux quartiers de la ville trouvée à l'étape 1)
 *   3. quartiers.id_secteur                             → déduit le secteur
 * Départage par ST_Area() croissant en cas de chevauchement de polygones.
 * Voir Amana\Shared\... — pas de service de résolution partagé pour
 * l'instant, chaque app fait sa propre requête (ex.
 * App\Jobs\ResoudreAdresseFamille::resoudreQuartier() dans
 * amana_web_familles) contre DB::connection('commun'). Un service partagé
 * pourrait être extrait dans amana_shared si une deuxième app en a besoin.
 */
return new class extends Migration {
    public $connection = 'commun';

    public function up(): void
    {
        Schema::create('quartiers', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->foreignId('id_secteur')
                ->constrained('secteurs')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->geometry('boundary', subtype: 'multipolygon', srid: 4326);
            $table->timestamps();

            $table->spatialIndex('boundary');
            $table->index('nom');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quartiers');
    }
};
