<?php
// src/Helpers/AuditHelper.php

declare(strict_types=1);

namespace Amana\Shared\Helpers;

use Amana\Shared\Models\Application;
use Amana\Shared\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Helper pour journaliser les actions sensibles — commun à toutes les apps.
 *
 * user_id est résolu automatiquement depuis Auth::id(). Null pour les
 * actions système (jobs en queue, webhook...).
 *
 * L'ID d'application n'est plus une constante en dur par app (c'était
 * 'planning' codé dans amana_web_planning) — il est résolu depuis
 * config('amana-shared.app_code'), mis en cache statique pour la durée
 * de la requête / du job.
 *
 * Exemples d'utilisation (inchangés côté sites d'appel) :
 *   audit('create',   'personnes',  $p->id,   null,            $p->toArray());
 *   audit('update',   'personnes',  $p->id,   $avant,          $apres);
 *   audit('delete',   'personnes',  $id,      $p->toArray(),   null);
 *   audit('generate', 'planning',   null,     null,            ['semaines' => 4]);
 *   audit('login',    'auth',       null,     null,            null);
 */
class AuditHelper
{
    private static ?int $applicationId = null;
    private static bool $applicationIdResolved = false;

    public static function log(
        string $action,
        string $module,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        AuditLog::create([
            'user_id' => Auth::id(),
            'id_application' => self::applicationId(),
            'action' => $action,
            'module' => $module,
            'entity_id' => $entityId,
            'entity_type' => null,
            'before' => $before,
            'after' => $after,
            'ip_address' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
        ]);
    }

    /**
     * ID de ref_applications pour l'app courante (config('amana-shared.app_code')),
     * mis en cache statique pour éviter une requête par appel audit() sur une
     * même requête HTTP / job.
     */
    public static function applicationId(): ?int
    {
        if (self::$applicationIdResolved) {
            return self::$applicationId;
        }

        self::$applicationId = Application::query()
            ->where('code', config('amana-shared.app_code'))
            ->value('id');

        self::$applicationIdResolved = true;

        return self::$applicationId;
    }

    /** Utilitaire de test — force la re-résolution de l'ID d'application. */
    public static function clearCache(): void
    {
        self::$applicationId = null;
        self::$applicationIdResolved = false;
    }
}
