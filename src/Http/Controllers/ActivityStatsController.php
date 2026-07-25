<?php
// src/Http/Controllers/ActivityStatsController.php

declare(strict_types=1);

namespace Amana\Shared\Http\Controllers;

use Amana\Shared\Contracts\ActivityStatisticsProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Page "Statistiques d'activité" (usage de l'app elle-même) — commune à
 * toutes les apps AMANA. Le shell (vue + JSON) est partagé ; le calcul
 * des métriques est délégué à l'implémentation de
 * Amana\Shared\Contracts\ActivityStatisticsProvider que chaque app lie
 * dans son propre AppServiceProvider.
 *
 * Routes (déclarées par chaque app avec son propre middleware role:) :
 *   GET /admin/activite        → index()  shell Blade
 *   GET /admin/activite/data   → data()   JSON des métriques sur une période
 */
class ActivityStatsController extends Controller
{
    public function __construct(
        private readonly ActivityStatisticsProvider $stats,
    ) {
    }

    public function index(): View
    {
        return view('amana-shared::admin.activite.index');
    }

    public function data(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return response()->json(
            $this->stats->computeAll($request->query('from'), $request->query('to'))
        );
    }
}
