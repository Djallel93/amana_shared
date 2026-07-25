<?php
// database/migrations/2026_07_23_000000_create_villes_table.php
//
// Déplacée depuis amana_web_familles (21/07/2026 → 23/07/2026) : bien que
// seule Familles l'utilise aujourd'hui, la géographie (villes/secteurs/
// quartiers de l'agglomération nantaise) est un référentiel générique que
// toute future app AMANA à dimension géographique pourra réutiliser sans
// dupliquer ~800 Ko de polygones. Voir GeoSeeder (src/Database/Seeders)
// pour le peuplement réel (19 villes / 57 secteurs / 97 quartiers).

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * boundary est NOT NULL : contrainte MySQL — un index SPATIAL ne peut
 * porter que sur une colonne NOT NULL. Aucun impact en pratique : une ville
 * sans frontière connue n'a pas sa place dans cette table.
 */
return new class extends Migration {
    public $connection = 'commun';

    public function up(): void
    {
        Schema::create('villes', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->string('code_postal', 10)->nullable();
            $table->string('departement', 100)->nullable();
            $table->geometry('boundary', subtype: 'multipolygon', srid: 4326);
            $table->timestamps();

            $table->spatialIndex('boundary');
            $table->index('nom');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villes');
    }
};
