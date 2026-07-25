<?php
// database/migrations/2026_07_21_000003_create_audit_logs_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table des journaux d'audit — partagée par toutes les apps AMANA.
 * Toute action sensible (create, update, delete, generate...) est loguée ici.
 */
return new class extends Migration {
    public $connection = 'commun';

    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Pas de contrainte FK sur user_id intentionnellement — même
            // approche que la table sessions. L'historique d'audit est
            // conservé même si la personne est supprimée. NULL = action
            // système sans utilisateur identifié.
            $table->unsignedInteger('user_id')->nullable()
                ->comment('ID de ref_personnes — null pour les actions système');

            $table->unsignedTinyInteger('id_application')->nullable()
                ->comment('ID de ref_applications — application à l\'origine de l\'entrée');

            $table->string('action', 100)->comment('create, update, delete, generate, login, logout, webhook');
            $table->string('module', 100)->comment('Module concerné, propre à chaque app');
            $table->unsignedBigInteger('entity_id')->nullable()->comment('ID de l\'entité concernée');
            $table->string('entity_type', 100)->nullable()->comment('Classe du modèle concerné');
            $table->json('before')->nullable()->comment('État avant modification (null pour create)');
            $table->json('after')->nullable()->comment('État après modification (null pour delete)');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('id_application');

            $table->foreign('id_application')
                ->references('id')->on('ref_applications')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
