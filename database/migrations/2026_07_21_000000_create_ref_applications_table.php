<?php
// database/migrations/2026_07_21_000000_create_ref_applications_table.php
//
// Exécutée uniquement via `php artisan amana:migrate-shared`, jamais par le
// cycle migrate/migrate:fresh d'une app consommatrice (voir
// AmanaSharedServiceProvider::boot() — pas de loadMigrationsFrom()).

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référence toutes les applications AMANA partageant amana_commun
 * (planning, familles, et les futures apps).
 *
 * Chaque rôle dans ref_roles est lié à une application spécifique,
 * permettant à une même personne d'avoir des rôles différents selon l'app.
 * Créée en tout premier : ref_roles (id_application) et audit_logs
 * (id_application) en dépendent toutes deux.
 *
 * Contrairement à la version historique dans amana_web_planning, cette
 * migration n'insère PAS de ligne 'planning' par défaut — c'est au
 * seeder/migration applicative de chaque app de s'enregistrer elle-même
 * (voir docs/installation.md de chaque app consommatrice).
 */
return new class extends Migration {
    /**
     * Nom de connexion utilisé pendant l'exécution de cette migration.
     * Le Migrator de Laravel bascule temporairement la connexion par
     * défaut sur celle-ci (voir Migrator::runMethod()) — Schema::create()
     * ci-dessous cible donc automatiquement 'commun', sans appel explicite
     * à Schema::connection(). Correspond à --database=commun passé par
     * `php artisan amana:migrate-shared`.
     */
    public $connection = 'commun';

    public function up(): void
    {
        Schema::create('ref_applications', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('code', 50)->unique()
                ->comment('Identifiant technique : planning, familles, ...');
            $table->string('libelle', 100)
                ->comment('Nom lisible : AMANA Planning, AMANA Familles, etc.');
            $table->boolean('actif')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_applications');
    }
};
