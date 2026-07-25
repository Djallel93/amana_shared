<?php
// database/migrations/2026_07_21_000001_create_ref_personnes_and_roles_tables.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Tables de référence communes : rôles et personnes.
 *
 * Extraites de amana_web_planning's create_base_tables migration — la
 * partie ref_taches de cette migration historique reste dans
 * amana_web_planning (c'est un référentiel de tâches propre au planning,
 * pas une notion partagée entre apps).
 *
 * ref_roles dépend de ref_applications (id_application) — doit donc
 * s'exécuter après elle.
 */
return new class extends Migration {
    public $connection = 'commun';

    public function up(): void
    {
        // ── ref_roles ──────────────────────────────────────────────────────
        Schema::create('ref_roles', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('code', 50)
                ->comment('Identifiant technique : admin, gestionnaire, membre, benevole...');
            $table->string('libelle', 100);
            $table->unsignedTinyInteger('id_application')
                ->comment('Application à laquelle ce rôle appartient');

            // Unique par (code, id_application), pas par code seul : un même
            // code (ex. "admin") existe pour plusieurs applications AMANA
            // partageant amana_commun.
            $table->unique(['code', 'id_application'], 'uq_roles_code_app');

            $table->foreign('id_application')
                ->references('id')->on('ref_applications')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        // ── ref_personnes ──────────────────────────────────────────────────
        Schema::create('ref_personnes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('email', 255)->unique();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('telephone', 20)->nullable();
            $table->date('date_debut_planning')->nullable()
                ->comment('NULL si la personne n\'est pas encore dans la rotation planning');
            $table->enum('statut', ['En attente', 'Validé', 'Suspendu', 'Archivé'])
                ->default('En attente');
            $table->timestamp('derniere_maj')->useCurrent()->useCurrentOnUpdate();
        });

        // ── ref_personnes_roles ────────────────────────────────────────────
        Schema::create('ref_personnes_roles', function (Blueprint $table) {
            $table->unsignedInteger('id_personne');
            $table->unsignedTinyInteger('id_role');
            $table->date('date_attribution')->default(DB::raw('(curdate())'));

            $table->primary(['id_personne', 'id_role']);

            $table->foreign('id_personne')
                ->references('id')->on('ref_personnes')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_role')
                ->references('id')->on('ref_roles')
                ->onDelete('restrict')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_personnes_roles');
        Schema::dropIfExists('ref_personnes');
        Schema::dropIfExists('ref_roles');
    }
};
