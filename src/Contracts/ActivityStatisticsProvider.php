<?php
// src/Contracts/ActivityStatisticsProvider.php

declare(strict_types=1);

namespace Amana\Shared\Contracts;

/**
 * Contrat implémenté par le service de statistiques d'usage de chaque app
 * (ex. App\Services\AuditStatistics dans amana_web_planning).
 *
 * Le calcul des métriques reste propre à chaque app (les événements
 * significatifs — régénérations de planning, imports de familles... —
 * diffèrent totalement) ; seul le contrôleur/la vue qui les affiche est
 * partagé (voir Amana\Shared\Http\Controllers\ActivityStatsController).
 *
 * Chaque app lie son implémentation dans son propre AppServiceProvider :
 *   $this->app->bind(ActivityStatisticsProvider::class, AuditStatistics::class);
 */
interface ActivityStatisticsProvider
{
    /**
     * @return array<string, mixed> Métriques sur la période, format libre
     *   (le Vue app-side qui consomme /admin/activite/data est lui aussi
     *   propre à chaque app — seul le shell Blade est partagé).
     */
    public function computeAll(string $from, string $to): array;
}
