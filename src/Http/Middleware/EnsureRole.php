<?php
// src/Http/Middleware/EnsureRole.php

declare(strict_types=1);

namespace Amana\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de contrôle des rôles — commun à toutes les apps AMANA.
 *
 * Toutes les routes utilisant ce middleware sont déjà protégées par le
 * middleware 'auth' (EnsureAuthenticated) — Auth::check() est donc
 * garanti ici et n'est pas revérifié.
 *
 * Hiérarchie des rôles (fusionnée depuis amana_web_planning et
 * amana_web_familles le 21/07/2026 — familles avait introduit 'benevole') :
 *
 *   admin        → accès complet (peut tout faire)
 *   gestionnaire → accès étendu (gestion métier), mais pas la gestion des
 *                  utilisateurs
 *   benevole      → accès intermédiaire, au-dessus de membre (défini par
 *                  amana_web_familles — sémantique précise laissée à
 *                  chaque app qui l'utilise)
 *   membre       → accès lecture + gestion de ses propres données
 *
 *   gestionnaire_externe → ajouté le 28/08/2026 (amana_web_familles,
 *                  organisations partenaires) — volontairement HORS de la
 *                  cascade ci-dessus : ce n'est pas un rang entre deux
 *                  rôles internes, mais un rôle latéral scopé à
 *                  l'organisation de la personne (voir
 *                  App\Models\Famille::scopeVisiblePar() côté
 *                  amana_web_familles pour le filtrage par organisation).
 *                  Seul admin y a accès en plus de gestionnaire_externe
 *                  lui-même — pas gestionnaire/benevole/membre, qui n'ont
 *                  aucune notion d'organisation.
 *
 * Un admin a automatiquement accès aux routes gestionnaire/benevole/membre.
 * Un gestionnaire a automatiquement accès aux routes benevole/membre.
 * Un benevole a automatiquement accès aux routes membre.
 *
 * Usage dans routes/web.php (inchangé pour les apps existantes) :
 *   Route::middleware('role:admin')
 *   Route::middleware('role:gestionnaire')
 *   Route::middleware('role:benevole')
 *   Route::middleware('role:membre')
 *   Route::middleware('role:gestionnaire_externe')
 *
 * La redirection en cas de refus pointe vers config('amana-shared.home_route')
 * — chaque app définit la sienne, plutôt que le nom de route étant en dur
 * dans ce middleware partagé (planning.index vs familles.index).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        /** @var \Amana\Shared\Models\Personne $personne */
        $personne = Auth::user();

        $autorise = match ($role) {
            'admin' => $personne->isAdmin(),
            'gestionnaire' => $personne->isAdmin() || $personne->isGestionnaire(),
            'benevole' => $personne->isAdmin() || $personne->isGestionnaire() || $personne->isBenevole(),
            'membre' => $personne->isMembre(),
            // Pas de cascade depuis gestionnaire/benevole/membre (voir
            // docblock de classe) — seul admin passe en plus de
            // gestionnaire_externe lui-même.
            'gestionnaire_externe' => $personne->isAdmin() || $personne->isGestionnaireExterne(),
            default => false,
        };

        if (!$autorise) {
            return redirect()->route(config('amana-shared.home_route'))
                ->with('error', 'Vous n\'avez pas les permissions nécessaires pour accéder à cette page.');
        }

        return $next($request);
    }
}
