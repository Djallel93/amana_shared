<?php
// src/Http/Controllers/AuditLogController.php

declare(strict_types=1);

namespace Amana\Shared\Http\Controllers;

use Amana\Shared\Helpers\AuditHelper;
use Amana\Shared\Models\AuditLog;
use Amana\Shared\Models\Personne;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Journal d'audit (lecture seule) — commun à toutes les apps AMANA.
 *
 * Contrairement à SettingsControllerBase, ce contrôleur n'a pas besoin
 * d'être étendu : la seule chose qui variait par app (les vocabulaires
 * modules/actions utilisés pour peupler les filtres) vient maintenant de
 * config('amana-shared.audit.modules'/'actions'), publiée et adaptée par
 * chaque app plutôt que codée en dur ici.
 *
 * Aucune action de "revert" n'est proposée volontairement : les entrées
 * avant/après sont de simples instantanés JSON, et restaurer un état
 * passé sans rejouer les effets de bord associés pourrait mettre la base
 * dans un état incohérent. Ce contrôleur est donc strictement consultatif.
 *
 * Routes (déclarées par chaque app, avec son propre middleware role:) :
 *   GET /admin/journal        → index()  shell Blade
 *   GET /admin/journal/data   → data()   JSON paginé, filtrable
 */
class AuditLogController extends Controller
{
    public function index(): View
    {
        $personnes = Personne::orderBy('nom')->get(['id', 'nom', 'prenom']);

        return view('amana-shared::admin.journal.index', [
            'personnes' => $personnes,
            'modules' => config('amana-shared.audit.modules', []),
            'actions' => config('amana-shared.audit.actions', []),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $modules = config('amana-shared.audit.modules', []);
        $actions = config('amana-shared.audit.actions', []);

        $request->validate([
            'module' => ['nullable', 'string', ...($modules ? ['in:' . implode(',', $modules)] : [])],
            'action' => ['nullable', 'string', ...($actions ? ['in:' . implode(',', $actions)] : [])],
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        // Scopé à cette application — audit_logs est partagée entre
        // plusieurs apps AMANA (voir id_application), le journal ne doit
        // montrer que ses propres entrées.
        $query = AuditLog::with('personne')
            ->where('id_application', AuditHelper::applicationId())
            ->when($request->filled('module'), fn($q) => $q->where('module', $request->query('module')))
            ->when($request->filled('action'), fn($q) => $q->where('action', $request->query('action')))
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->query('user_id')))
            ->when($request->filled('from'), fn($q) => $q->whereDate('created_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn($q) => $q->whereDate('created_at', '<=', $request->query('to')))
            ->orderByDesc('created_at');

        $page = $query->paginate(40)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn(AuditLog $log) => $this->serialize($log)),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    private function serialize(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'date' => $log->created_at->locale('fr')->isoFormat('D MMM YYYY [à] HH:mm:ss'),
            'utilisateur' => $log->personne
                ? "{$log->personne->prenom} {$log->personne->nom}"
                : 'Système',
            'action' => $log->action,
            'module' => $log->module,
            'entityId' => $log->entity_id,
            'before' => $log->before,
            'after' => $log->after,
            'ipAddress' => $log->ip_address,
            'userAgent' => $log->user_agent,
        ];
    }
}
