<?php
// src/Console/Commands/MigrateSharedCommand.php

declare(strict_types=1);

namespace Amana\Shared\Console\Commands;

use Illuminate\Console\Command;

/**
 * php artisan amana:migrate-shared
 *
 * Seule et unique façon d'exécuter les migrations du schéma partagé
 * (amana_commun). Volontairement PAS auto-découvert par le migrate/
 * migrate:fresh normal d'une app consommatrice (voir
 * AmanaSharedServiceProvider::boot(), qui n'appelle jamais
 * loadMigrationsFrom() pour ces migrations).
 *
 * À exécuter une fois, manuellement, depuis l'app qui initie le changement
 * de schéma — jamais comme effet de bord d'un déploiement routine. Utilise
 * la connexion nommée dans config('amana-shared.connection') (par défaut
 * 'commun'), qui doit exister dans config/database.php et pointer vers
 * amana_commun.
 *
 * Options identiques à `migrate` standard : --pretend, --step, --force
 * (requis en prod, comme pour toute commande destructive).
 */
class MigrateSharedCommand extends Command
{
    protected $signature = 'amana:migrate-shared
                            {--pretend : Affiche les requêtes SQL sans les exécuter}
                            {--step : Exécute chaque migration dans sa propre transaction}
                            {--force : Force l\'exécution en production sans confirmation}
                            {--fresh : DANGER — supprime puis recrée toutes les tables de amana_commun}';

    protected $description = 'Exécute les migrations du schéma partagé AMANA (amana_commun) — jamais appelée automatiquement';

    public function handle(): int
    {
        $connection = config('amana-shared.connection', 'commun');
        $path = $this->sharedMigrationsPath();

        $this->warn("Connexion ciblée : {$connection}");
        $this->warn("Chemin des migrations : {$path}");

        if ($this->option('fresh')) {
            if (! $this->option('force') && ! $this->confirm(
                "⚠️  Ceci va SUPPRIMER puis recréer TOUTES les tables de la connexion '{$connection}' (amana_commun), y compris ref_personnes et audit_logs. Continuer ?",
                false
            )) {
                $this->info('Annulé.');
                return self::FAILURE;
            }

            $exitCode = $this->call('migrate:fresh', array_filter([
                '--database' => $connection,
                '--path' => $path,
                '--realpath' => true,
                '--force' => true,
            ]));

            if ($exitCode === self::SUCCESS) {
                $this->info('amana_commun recréée. Chaque app doit maintenant lancer son propre seeder (php artisan db:seed) pour s\'enregistrer dans ref_applications, créer ses ref_roles et son premier admin — ce package ne connaît pas les apps qui le consomment.');
            }

            return $exitCode;
        }

        return $this->call('migrate', array_filter([
            '--database' => $connection,
            '--path' => $path,
            '--realpath' => true,
            '--pretend' => $this->option('pretend'),
            '--step' => $this->option('step'),
            '--force' => $this->option('force'),
        ]));
    }

    private function sharedMigrationsPath(): string
    {
        return dirname(__DIR__, 3) . '/database/migrations';
    }
}
