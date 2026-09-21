<?php
// src/Http/Controllers/NavBadgesController.php

declare(strict_types=1);

namespace Amana\Shared\Http\Controllers;

use Amana\Shared\Contracts\NavBadgeProvider;
use Amana\Shared\Services\NavVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Rafraîchissement des badges de la sidebar (voir Contracts\NavBadgeProvider
 * et layouts/partials/nav-badges-script.blade.php).
 *
 * Le package n'enregistre AUCUNE route : chaque app l'ajoute sous son propre
 * middleware d'authentification (voir README, section « Badges de
 * navigation en direct »).
 *
 * Sécurité : ne renvoie QUE des entiers indexés par nom de route, et
 * uniquement pour les items que la sidebar montrerait à cet utilisateur —
 * même service (Services\NavVisibility) que la sidebar, donc impossible
 * de divulguer l'existence ou le compteur d'un item masqué.
 *
 * Coût : les compteurs du fournisseur sont mis en cache quelques secondes
 * (config 'nav_badges_cache_seconds', défaut 10, 0 = pas de cache),
 * PARTAGÉS entre utilisateurs de l'app. Un fournisseur dont les compteurs
 * dépendent de l'utilisateur connecté doit donc mettre cette valeur à 0.
 */
class NavBadgesController extends Controller
{
    public function __invoke(Request $request, NavVisibility $visibilite): JsonResponse
    {
        if (! app()->bound(NavBadgeProvider::class)) {
            return $this->reponse([]);
        }

        try {
            $compteurs = $this->compteurs();
        } catch (\Throwable $e) {
            // Le script garde la dernière valeur affichée en cas d'échec.
            Log::warning('[NavBadges] Calcul des compteurs impossible', ['erreur' => $e->getMessage()]);

            return $this->reponse([], 503);
        }

        $visibles = $visibilite->visibleByRoute(
            config('amana-shared.nav', []),
            $request->user(),
            array_map('strval', array_keys($compteurs)),
        );

        $payload = [];
        foreach (array_keys($visibles) as $route) {
            $payload[$route] = max(0, (int) ($compteurs[$route] ?? 0));
        }

        return $this->reponse($payload);
    }

    /** @return array<string, int> */
    private function compteurs(): array
    {
        $calculer = fn (): array => app(NavBadgeProvider::class)->counts();
        $ttl = (int) config('amana-shared.nav_badges_cache_seconds', 10);

        if ($ttl <= 0) {
            return $calculer();
        }

        return Cache::remember(
            'amana:nav-badges:' . config('amana-shared.app_code', 'app'),
            $ttl,
            $calculer,
        );
    }

    /** @param  array<string, int>  $payload */
    private function reponse(array $payload, int $status = 200): JsonResponse
    {
        // Objet JSON même vide ({} et non []), jamais mis en cache navigateur.
        return response()
            ->json((object) $payload, $status)
            ->header('Cache-Control', 'no-store, private');
    }
}
