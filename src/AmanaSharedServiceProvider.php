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

        // ────────────────────────────────────────────────────────────────
        // Assets de marque (logo, favicons, icônes PWA) — identiques entre
        // amana_web_planning et amana_web_familles (vérifié octet pour
        // octet le 04/08/2026, avant cette centralisation). Contrairement à
        // config/vues ci-dessus, ces fichiers doivent être PHYSIQUEMENT
        // présents dans public/ de chaque app (Laravel sert public/
        // directement, aucun mécanisme de service de fichiers depuis
        // vendor/ sans route dédiée) — publiés une fois via :
        //   php artisan vendor:publish --tag=amana-shared-assets
        // À republier après toute mise à jour du logo/des favicons dans ce
        // package (voir amana_shared/README.md, section "Assets de marque").
        // site.webmanifest n'est PAS ici : son contenu (name/short_name)
        // diffère légitimement par app, il reste propre à chaque app.
        $this->publishes([
            __DIR__ . '/../resources/images/amana-logo.png' => public_path('images/amana-logo.png'),
            __DIR__ . '/../resources/images/favicon.ico' => public_path('favicon.ico'),
            __DIR__ . '/../resources/images/favicon.svg' => public_path('favicon.svg'),
            __DIR__ . '/../resources/images/favicon-96x96.png' => public_path('favicon-96x96.png'),
            __DIR__ . '/../resources/images/apple-touch-icon.png' => public_path('apple-touch-icon.png'),
            __DIR__ . '/../resources/images/web-app-manifest-192x192.png' => public_path('web-app-manifest-192x192.png'),
            __DIR__ . '/../resources/images/web-app-manifest-512x512.png' => public_path('web-app-manifest-512x512.png'),
        ], 'amana-shared-assets');
    }
}
