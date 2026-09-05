<?php
// database/migrations/2026_07_21_000002_create_ref_settings_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table ref_settings.
 *
 * Stocke les paramètres de configuration par application AMANA.
 * Toujours filtrer par id_application lors de la lecture.
 *
 * Le type 'encrypted' est inclus dès la création ici (fusionné depuis la
 * migration additive 2026_07_17_000001_add_encrypted_type_to_ref_settings.php
 * d'amana_web_familles) — plus besoin d'une migration additive séparée
 * pour les nouvelles apps.
 *
 * Type 'float' (ajouté le 05/09/2026, décision amana_web_familles) : avant
 * son ajout, les réglages numériques décimaux (ex. ratios/distances de
 * clustering) étaient stockés en 'string' et castés manuellement par
 * chaque app consommatrice (voir App\Support\RouteOptimizationConfig,
 * amana_web_familles) — Setting::cast() n'avait pas d'équivalent décimal
 * à 'integer'. La valeur reste stockée en chaîne dans `valeur` (pas de
 * colonne DECIMAL) : seul le cast à la lecture change, voir Setting::cast().
 */
return new class extends Migration {
    public $connection = 'commun';

    public function up(): void
    {
        Schema::create('ref_settings', function (Blueprint $table) {
            $table->tinyIncrements('id');

            $table->unsignedTinyInteger('id_application')->nullable()
                ->comment('NULL = paramètre global, sinon lié à une application');

            $table->string('cle', 100)
                ->comment('Identifiant technique du paramètre (ex: heure_cours)');

            $table->string('valeur', 500)
                ->comment('Valeur stockée sous forme de chaîne, castée (ou déchiffrée) à la lecture');

            $table->enum('type', ['string', 'integer', 'float', 'time', 'boolean', 'encrypted'])
                ->default('string')
                ->comment('Type de casting appliqué à la valeur lors de la lecture');

            $table->string('libelle', 200)
                ->comment('Label lisible affiché dans l\'UI (ex: Heure du cours)');

            $table->text('description')->nullable()
                ->comment('Description longue optionnelle pour l\'aide contextuelle');

            $table->unique(['id_application', 'cle'], 'uq_settings_app_cle');

            $table->foreign('id_application')
                ->references('id')->on('ref_applications')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_settings');
    }
};
