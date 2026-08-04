<?php

declare(strict_types=1);

use Amana\Shared\Helpers\AuditHelper;

if (!function_exists('audit')) {
    /**
     * Enregistre une entrée dans le journal d'audit partagé (audit_logs).
     * Voir Amana\Shared\Helpers\AuditHelper pour le détail.
     */
    function audit(
        string $action,
        string $module,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        AuditHelper::log($action, $module, $entityId, $before, $after);
    }
}
