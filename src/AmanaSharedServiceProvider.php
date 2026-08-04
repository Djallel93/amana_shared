<?php
// src/AmanaSharedServiceProvider.php

declare(strict_types=1);

namespace Amana\Shared;

use Amana\Shared\Console\Commands\MigrateSharedCommand;
use Illuminate\Support\ServiceProvider;

class AmanaSharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/amana-shared.php', 'amana-shared');
    }

    public function boot(): void
    {
        // ────────────────────────────────────────────────────────────────
        // SÉCURITÉ CRITIQUE : ne JAMAIS appeler loadMigrationsFrom() ici.
        //
        // Si les migrations de database/migrations/ étaient auto-chargées,
        // chaque app consommatrice les verrait dans son propre
        // `php artisan migrate` / `migrate:fresh`, avec sa propre table
        // `migrations` de suivi indépendante — provoquant des erreurs
        // "table already exists" ou pire, un `migrate:fresh` applicatif qui
        // toucherait accidentellement le schéma partagé amana_commun.
        //
        // Les migrations partagées ne sont exécutées QUE via la commande
        // dédiée `amana:migrate-shared` (voir MigrateSharedCommand), lancée
        // manuellement, jamais comme effet de bord d'un déploiement routine.
        // ────────────────────────────────────────────────────────────────

        // ────────────────────────────────────────────────────────────────
        // Volontairement PAS de garde $this->app->runningInConsole() ici.
        // Optimisation habituelle pour éviter d'enregistrer des commandes
        // console inutilement pendant une requête web — mais runningInConsole()
        // teste PHP_SAPI === 'cli'|'phpdbg', ce qui échoue silencieusement
        // sur les hébergements (ex. IONOS mutualisé) où le binaire "php"
        // utilisé en SSH est en réalité php-cgi (PHP_SAPI = 'cgi-fcgi') —
        // la commande amana:migrate-shared devenait alors invisible à
        // `php artisan list`, sans aucune erreur. $this->commands() est sans
        // risque à appeler inconditionnellement : elle ne fait qu'enregistrer
        // la classe auprès du noyau console, sans effet en dehors d'une
        // véritable invocation console. Bug identifié le 03/08/2026.
        $this->commands([
            MigrateSharedCommand::class,
        ]);

        // Vues Blade partagées (login, mot de passe oublié, shell de
        // paramètres/journal/statistiques) — sans risque à auto-charger,
        // contrairement aux migrations : elles n'ont pas d'effet de bord et
        // chaque app peut les surcharger en publiant ses propres vues au
        // même chemin (resources/views/vendor/amana-shared/...).
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'amana-shared');

        $this->publishes([
            __DIR__ . '/../config/amana-shared.php' => config_path('amana-shared.php'),
        ], 'amana-shared-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/amana-shared'),
        ], 'amana-shared-views');
    }
}
