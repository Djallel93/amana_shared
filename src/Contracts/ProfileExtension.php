<?php
// src/Contracts/ProfileExtension.php

declare(strict_types=1);

namespace Amana\Shared\Contracts;

use Amana\Shared\Models\Personne;

/**
 * Section « Informations spécifiques à l'app » de la page « Mon profil ».
 *
 * Même schéma que Contracts\NavBadgeProvider : une app lie son
 * implémentation dans son AppServiceProvider ; la page partagée ne la
 * résout que si elle est liée (`app()->bound()`) ET que l'app a enregistré la
 * route `profile.extra.update`. Sans cela, la section n'existe pas.
 *
 *   $this->app->bind(ProfileExtension::class, \App\Services\BenevoleProfileExtension::class);
 *
 * La page est rendue de façon déclarative (aucune vue à écrire côté app) à
 * partir de fields() ; rules() valide et save() persiste dans les tables /
 * la connexion propres à l'app.
 *
 * SÉCURITÉ — l'extension décide quels champs sont modifiables par la
 * personne elle-même et ne doit JAMAIS exposer de donnée pilotée par un
 * administrateur (statuts, indicateurs de validation, rôles). Le contrôleur
 * ne transmet à save() que les clés couvertes par rules() (validated()), et
 * écarte de toute façon id/statut/rôles/email/mot de passe.
 */
interface ProfileExtension
{
    /** Titre de la section, ex. « Informations bénévole ». */
    public function title(): string;

    /**
     * Définition déclarative des champs, dans l'ordre d'affichage. Chaque
     * champ est un tableau :
     *
     *   'name'     string  — nom du champ de formulaire (clé de rules())
     *   'label'    string  — libellé français
     *   'type'     string  — text | tel | number | date | select | multiselect
     *                        | checkbox | textarea
     *   'value'    mixed   — valeur actuelle (multiselect : liste de valeurs ;
     *                        checkbox : bool)
     *   'options'  array   — [valeur => libellé] pour select / multiselect
     *   'hint'     string  — aide sous le champ (optionnel)
     *   'required' bool    — affiche l'astérisque (optionnel ; la vraie
     *                        contrainte est dans rules())
     *
     * Tableau vide = rien à éditer pour CETTE personne (ex. elle n'a pas de profil
     * bénévole) : la section est masquée et la route de sauvegarde répond 404.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fields(Personne $personne): array;

    /**
     * Règles de validation Laravel, indexées par nom de champ.
     *
     * @return array<string, mixed>
     */
    public function rules(Personne $personne): array;

    /**
     * Persiste les valeurs validées (uniquement les clés de rules()).
     *
     * @param  array<string, mixed>  $validated
     */
    public function save(Personne $personne, array $validated): void;

    /**
     * Échappatoire pour une section trop complexe pour le rendu déclaratif :
     * nom d'une vue Blade (reçoit $personne, $fields et $title) qui REMPLACE
     * le rendu des champs — le <form>, le jeton CSRF et le bouton restent
     * ceux de la page. null = rendu déclaratif.
     */
    public function view(): ?string;
}
