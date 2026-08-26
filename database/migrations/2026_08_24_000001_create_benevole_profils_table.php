<?php
// database/migrations/2026_08_24_000001_create_benevole_profils_table.php

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
 * `id_vehicule_type` : FK vers ref_vehicules (voir
 * create_ref_vehicules_table) — revenu le 24/08/2026 sur la première
 * implémentation, qui stockait vehicule_type en enum + capacite_kg/
 * nombre_part_max en saisie libre du candidat. Ces deux dernières valeurs
 * ne sont PAS des données du bénévole : ce sont des caractéristiques du
 * type de véhicule, définies par le staff et partagées par tous les
 * bénévoles ayant choisi ce type.
 *
 * Pas de disponibilités ici (retiré le 24/08/2026) : fonctionnalité
 * liée aux évènements/créneaux, hors scope de l'inscription — sera
 * réintroduite avec la phase de matching future plutôt que collectée dès
 * maintenant sans usage.
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
            $table->unsignedInteger('id_vehicule_type');
            $table->enum('statut', [
                'Reçu', 'En attente vérification', 'Vérifié', 'Validé', 'Rejeté', 'Archivé',
            ])->default('Reçu');
            $table->timestamp('derniere_maj')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('id_personne')
                ->references('id')->on('ref_personnes')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_vehicule_type')
                ->references('id')->on('ref_vehicules')
                ->onDelete('restrict')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benevole_profils');
    }
};
