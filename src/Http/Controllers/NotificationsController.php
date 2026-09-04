<?php
// src/Http/Controllers/NotificationsController.php

declare(strict_types=1);

namespace Amana\Shared\Http\Controllers;

use Amana\Shared\Services\NotificationCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Endpoints JSON communs au centre de notifications — consommés par
 * amana_shared_ui (NotificationBell.vue / UrgentAlertBar.vue / le
 * composable useNotifications.ts qui les interroge par polling, ce stack
 * n'ayant pas de websockets). Chaque app enregistre ses propres routes
 * vers ce contrôleur, avec son propre middleware auth (même schéma que
 * ActivityStatsController/AuditLogController) :
 *
 *   Route::get('/notifications', [NotificationsController::class, 'index']);
 *   Route::post('/notifications/{id}/lue', [NotificationsController::class, 'marquerLue']);
 *
 * Pas de route de résolution ici volontairement : une alerte 'urgent' ne
 * se résout jamais depuis le centre de notifications lui-même, seulement
 * en traitant l'entité qu'elle décrit (ex: résoudre un RouteIncident) —
 * voir NotificationCenterService::resoudreParDonnee(), appelé par l'app
 * consommatrice au moment de cette résolution.
 */
class NotificationsController extends Controller
{
    public function __construct(
        private readonly NotificationCenterService $center,
    ) {
    }

    public function index(): JsonResponse
    {
        $notifications = $this->center->pourPersonne(auth()->user());

        return response()->json([
            'notifications' => $notifications->map(fn ($n) => [
                'id' => $n->id,
                'type' => class_basename($n->type),
                'severity' => $n->severity,
                'data' => $n->data,
                'read_at' => $n->read_at,
                'resolved_at' => $n->resolved_at,
                'created_at' => $n->created_at,
            ])->values(),
        ]);
    }

    public function marquerLue(string $id): JsonResponse
    {
        $ok = $this->center->marquerLue(auth()->user(), $id);

        return response()->json(['success' => $ok], $ok ? 200 : 404);
    }
}
