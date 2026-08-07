<?php
// src/Contracts/NavBadgeProvider.php

declare(strict_types=1);

namespace Amana\Shared\Contracts;

/**
 * Contrat implémenté par le fournisseur de badges de navigation de chaque
 * app (ex. App\Services\NavBadges dans amana_web_planning) — permet
 * d'afficher un badge numérique sur un item de la sidebar (ex. nombre de
 * candidatures en attente sur "Candidatures") sans que config/amana-shared.php
 * (un simple tableau PHP, mis en cache par `config:cache`) ne puisse porter
 * cette logique dynamique.
 *
 * Binding optionnel : si aucune implémentation n'est liée dans le conteneur,
 * la sidebar n'affiche simplement aucun badge (voir
 * AmanaSharedServiceProvider::boot() — le View::composer résout ce contrat
 * avec un défaut vide via `app()->bound()`).
 *
 * Chaque app lie son implémentation dans son propre AppServiceProvider :
 *   $this->app->bind(NavBadgeProvider::class, NavBadges::class);
 */
interface NavBadgeProvider
{
    /**
     * @return array<string, int> Nombre à afficher, indexé par le nom de
     *   route EXACT de l'item de nav concerné (clé 'route' dans
     *   config('amana-shared.nav'), ex. 'admin.candidatures.index'). Une
     *   entrée absente ou à 0 n'affiche aucun badge pour cet item.
     */
    public function counts(): array;
}
