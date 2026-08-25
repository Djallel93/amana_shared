<?php
// src/Services/PersonneIntakeService.php

declare(strict_types=1);

namespace Amana\Shared\Services;

/**
 * Bloc "informations personnelles" commun à tous les formulaires publics
 * AMANA qui collectent nom/prénom/téléphone/email/langue (intake familles,
 * candidature bénévole, et toute future app publique) — extrait le
 * 24/08/2026 pour éviter la duplication entre apps, sans coupler ce
 * package à la structure précise d'un formulaire donné (chaque app garde
 * son propre wizard, ses propres champs additionnels, sa propre
 * validation métier).
 *
 * Deux responsabilités séparées, à utiliser indépendamment selon le
 * contexte :
 *  - validationRules() : uniquement les règles Laravel du bloc identité,
 *    à fusionner avec les règles propres à l'app appelante.
 *  - trouverOuPreparerPersonne() : la logique de rapprochement par email
 *    contre ref_personnes — pertinente seulement pour les formulaires qui
 *    créent/lient un compte (candidature bénévole, staff), PAS pour
 *    l'intake familles (les familles bénéficiaires n'ont pas de compte
 *    ref_personnes, voir App\Models\Famille dans amana_web_familles).
 *    Même logique que Admin\PersonnesController::store() (familles) et
 *    CandidatureController (planning), désormais centralisée ici.
 */
class PersonneIntakeService
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(
        bool $emailUniqueDansRefPersonnes = false,
        int $telephoneMax = 30,
    ): array {
        $regleEmail = ['required', 'email', 'max:255'];
        if ($emailUniqueDansRefPersonnes) {
            $regleEmail[] = 'unique:commun.ref_personnes,email';
        }

        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => $regleEmail,
            // Même regex que les formulaires existants (IntakeController,
            // amana_web_familles) — accepte espaces/points/tirets/parenthèses.
            // $telephoneMax=30 par défaut, comme les formulaires publics
            // existants (les admins internes utilisent parfois max:20 pour
            // des saisies plus courtes — passer une valeur différente si besoin).
            'telephone' => ['required', 'string', "max:{$telephoneMax}", 'regex:/^[0-9+\s().-]{6,}$/'],
            'langue' => ['required', 'string', 'in:fr,ar,en'],
        ];
    }

    /**
     * Retrouve une Personne existante par email (jamais de doublon —
     * ref_personnes est une table partagée entre toutes les apps AMANA),
     * ou en prépare une nouvelle NON SAUVEGARDÉE avec le statut fourni.
     * L'appelant reste responsable du ->save() : cette méthode ne modifie
     * jamais la base, pour rester utilisable y compris quand la
     * transaction globale peut encore échouer plus loin (voir
     * BenevoleIntakeConfirmationController dans amana_web_familles).
     *
     * @param class-string $personneClass Modèle Personne local de l'app appelante (ex: App\Models\Personne)
     * @param array{nom: string, prenom: string, email: string, telephone?: string|null} $donnees
     *
     * @return array{personne: \Amana\Shared\Models\Personne, existante: bool}
     */
    public static function trouverOuPreparerPersonne(
        string $personneClass,
        array $donnees,
        string $statutSiNouvelle = 'En attente',
    ): array {
        /** @var \Amana\Shared\Models\Personne|null $personne */
        $personne = $personneClass::where('email', $donnees['email'])->first();
        $existante = $personne !== null;

        if ($personne) {
            // Personne déjà connue : on met à jour ses coordonnées avec ce
            // qu'elle vient de saisir, mais on ne touche ni à son mot de
            // passe ni à son statut de compte existant.
            $personne->fill(array_intersect_key($donnees, array_flip(['nom', 'prenom', 'telephone'])));
        } else {
            $personne = new $personneClass(array_merge(
                array_intersect_key($donnees, array_flip(['nom', 'prenom', 'email', 'telephone'])),
                ['statut' => $statutSiNouvelle],
            ));
        }

        return ['personne' => $personne, 'existante' => $existante];
    }
}
