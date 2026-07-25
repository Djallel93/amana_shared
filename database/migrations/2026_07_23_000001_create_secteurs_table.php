<?php
// database/migrations/2026_07_23_000001_create_secteurs_table.php
//
// Déplacée depuis amana_web_familles — voir create_villes_table.php pour
// le contexte du déplacement vers amana_shared.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pur regroupement logique de quartiers — aucune géométrie propre (le
 * secteur est déduit du quartier trouvé par point-in-polygon, jamais testé
 * géométriquement lui-même).
 */
return new class extends Migration {
    public $connection = 'commun';

    public function up(): void
    {
        Schema::create('secteurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->foreignId('id_ville')
                ->constrained('villes')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secteurs');
    }
};
