<?php
// database/migrations/2026_08_24_000000_create_ref_vehicules_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des types de véhicule — capacite_kg et nombre_part_max ne
 * sont PAS saisis par le bénévole candidat (décision du 24/08/2026,
 * revenant sur l'implémentation initiale) : ce sont des valeurs de
 * référence définies par le staff (admin ET gestionnaire, voir
 * VehiculeTypesController), destinées au futur moteur de matching
 * véhicule/capacité (amana_livraison). Le bénévole se contente de
 * choisir un type dans ref_vehicules ; benevole_profils ne stocke que
 * l'id_vehicule_type (voir create_benevole_profils_table).
 *
 * IDs et valeurs de départ imposés (voir VehiculeTypesSeeder) — issus de
 * la table `vehicule` de l'ancien projet Apps Script amana_livraison,
 * avec deux entrées ajoutées ('Permis'/'Sans permis', capacité 0) pour
 * les candidats sans véhicule propre.
 *
 * Vit dans amana_commun comme benevole_profils (même raisonnement :
 * donnée réutilisable par plusieurs apps AMANA à terme).
 */
return new class extends Migration {
    public $connection = 'commun';

    public function up(): void
    {
        Schema::create('ref_vehicules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type', 50);
            $table->decimal('capacite_kg', 6, 2)->default(0);
            $table->unsignedSmallInteger('nombre_part_max')->default(0);
            // Désactivation douce (octobre 2026) : un type ne se supprime
            // jamais (FK benevole_profils.id_vehicule_type en RESTRICT,
            // routes et disponibilités y font aussi référence) — il est
            // masqué des choix proposés (formulaire public, pickers) mais
            // reste lisible pour l'historique. Voir VehiculeType::IDS_PROTEGES
            // pour les lignes que le staff ne peut pas désactiver.
            $table->boolean('actif')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_vehicules');
    }
};
