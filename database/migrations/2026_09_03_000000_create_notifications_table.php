<?php
// database/migrations/2026_09_03_000000_create_notifications_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centre de notifications partagé — voir le prompt du 03/09/2026
 * (amana_web_familles, domaine livraison §2.9/§2.10.4) : premier
 * consommateur, mais volontairement posé ici plutôt que dans une app pour
 * que amana_web_planning (et toute app AMANA future) puisse s'en servir
 * sans dupliquer la table/le modèle.
 *
 * Forme proche de la table `notifications` standard de Laravel
 * (Illuminate\Notifications\DatabaseNotification — voir
 * Amana\Shared\Models\Notification), avec deux colonnes en plus, en dur
 * plutôt qu'enfouies dans `data` JSON — cohérent avec le reste du schéma
 * AMANA (ex: Livraison.statut_contact plutôt qu'un blob), et nécessaires
 * pour être filtrables/indexables efficacement :
 *   - severity : 'info' | 'urgent'. Pilote l'affichage (cloche vs bandeau
 *     rouge plein écran — voir Amana\Shared\Http\Controllers\
 *     NotificationsController et amana_shared_ui/UrgentAlertBar.vue).
 *   - resolved_at : distinct de read_at. Une alerte 'urgent' reste dans
 *     le bandeau tant qu'elle n'est pas résolue (ex: incident tournée
 *     traité), même si quelqu'un l'a déjà vue/lue — lu ≠ traité.
 *     Toujours NULL pour severity = 'info' (rien à résoudre).
 */
return new class extends Migration {
    public $connection = 'commun';

    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->unsignedInteger('notifiable_id');
            $table->json('data');
            $table->enum('severity', ['info', 'urgent'])->default('info');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index(['severity', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
