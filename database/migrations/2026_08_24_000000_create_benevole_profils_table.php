<?php
// database/migrations/2026_08_24_000000_create_benevole_profils_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profil bénévole — vit dans amana_commun (pas dans la base propre à
 * amana_web_familles), décision actée le 24/08/2026 : contrairement à
 * plan_restrictions (amana_web_planning), donnée destinée à être
 * consommée par plusieurs apps AMANA à terme (familles aujourd'hui,
 * amana_livraison demain pour le matching véhicule/capacité — voir
 * l'ancien projet Apps Script amana_livraison en référence).
 *
 * Un seul profil par Personne (1-1) — id_personne est donc unique, pas
 * juste indexé. FK réelle vers ref_personnes (contrairement à
 * plan_restrictions.id_personne) : même base, pas de raison de s'en
 * priver ici.
 *
 * `statut` : pipeline propre au bénévolat, distinct de ref_personnes.statut
 * (qui reste le statut de compte générique En attente/Validé/Suspendu/
 * Archivé). 'Reçu' → soumission confirmée par email, pas encore revue par
 * le staff ; 'En attente vérification' n'est pas utilisé pour l'instant
 * (réservé si un flux de re-vérification périodique façon
 * FamilleVerification est ajouté plus tard) ; 'Vérifié' idem.
 */
return new class extends Migration {
    public $connection = 'commun';

    public function up(): void
    {
        Schema::create('benevole_profils', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_personne')->unique();
            $table->enum('langue_preferee', ['fr', 'ar', 'en'])->default('fr');
            $table->boolean('permis')->default(false);
            $table->enum('vehicule_type', [
                'citadine', 'berline', 'break', 'monospace',
                'camion_utilitaire', 'non_vehicule', 'autre',
            ])->default('non_vehicule');
            // Les deux critères de capacité conservés de l'ancienne table
            // `vehicule` (amana_benevoles) — capturés dès l'inscription même
            // si le moteur de matching qui les consommera n'existe pas encore.
            $table->decimal('capacite_kg', 6, 2)->nullable();
            $table->unsignedSmallInteger('nombre_part_max')->nullable();
            $table->enum('statut', [
                'Reçu', 'En attente vérification', 'Vérifié', 'Validé', 'Rejeté', 'Archivé',
            ])->default('Reçu');
            $table->timestamp('derniere_maj')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('id_personne')
                ->references('id')->on('ref_personnes')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benevole_profils');
    }
};
