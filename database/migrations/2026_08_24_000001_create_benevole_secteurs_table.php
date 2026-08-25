<?php
// database/migrations/2026_08_24_000001_create_benevole_secteurs_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Couverture géographique d'un bénévole — pivot vers secteurs (granularité
 * retenue le 24/08/2026 : secteurs plutôt que quartiers, plus simple en UX
 * pour le formulaire public, cohérent avec le grain grossier des 3 choix
 * de l'ancien Google Form).
 *
 * Une ligne par secteur couvert. Le choix "Nantes + extérieur" du
 * formulaire ne se traduit PAS par une absence de lignes ici — voir
 * BenevoleIntakeAttenteService, qui décide de la liste de secteurs à
 * synchroniser selon la réponse à la question de zone.
 */
return new class extends Migration {
    public $connection = 'commun';

    public function up(): void
    {
        Schema::create('benevole_secteurs', function (Blueprint $table) {
            $table->unsignedInteger('id_benevole_profil');
            $table->unsignedInteger('id_secteur');

            $table->primary(['id_benevole_profil', 'id_secteur']);

            $table->foreign('id_benevole_profil')
                ->references('id')->on('benevole_profils')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_secteur')
                ->references('id')->on('secteurs')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benevole_secteurs');
    }
};
