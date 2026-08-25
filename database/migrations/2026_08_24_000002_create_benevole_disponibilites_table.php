<?php
// database/migrations/2026_08_24_000002_create_benevole_disponibilites_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Disponibilités générales d'un bénévole — matin/après-midi/soir/journée,
 * sélection MULTIPLE (contrairement au RADIO unique de l'ancien Google
 * Form : un bénévole peut être libre le matin ET le soir sur des jours
 * différents). Pré-filtre grossier uniquement — ne sert pas encore de base
 * à un moteur de matching (phase future, hors scope actuel).
 */
return new class extends Migration {
    public $connection = 'commun';

    public function up(): void
    {
        Schema::create('benevole_disponibilites', function (Blueprint $table) {
            $table->unsignedInteger('id_benevole_profil');
            $table->enum('creneau', ['matin', 'apres_midi', 'soir', 'journee']);

            $table->primary(['id_benevole_profil', 'creneau']);

            $table->foreign('id_benevole_profil')
                ->references('id')->on('benevole_profils')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benevole_disponibilites');
    }
};
