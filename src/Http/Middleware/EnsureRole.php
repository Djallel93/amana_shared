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
 * Hiérarchie des rôles — standardisée le 30/08/2026 (voir note ci-dessous
 * sur l'historique de ce fichier) :
 *
 *   admin        → accès complet (peut tout faire)
 *   gestionnaire → accès étendu (gestion métier), mais pas la gestion des
 *                  utilisateurs
 *   membre       → accès lecture + gestion de ses propres données
 *   benevole      → rang le plus bas, sémantique précise (ex. restriction
 *                  à certaines tâches) laissée à chaque app qui l'utilise
 *
 *   gestionnaire_externe → ajouté le 28/08/2026 (amana_web_familles,
 *                  organisations partenaires) — volontairement HORS de la
 *                  cascade ci-dessus : ce n'est pas un rang entre deux
 *                  rôles internes, mais un rôle latéral scopé à
 *                  l'organisation de la personne (voir
 *                  App\Models\Famille::scopeVisiblePar() côté
 *                  amana_web_familles pour le filtrage par organisation).
 *                  Seul admin y a accès en plus de gestionnaire_externe
 *                  lui-même — pas gestionnaire/membre/benevole, qui n'ont
 *                  aucune notion d'organisation.
 *
 * Un admin a automatiquement accès aux routes gestionnaire/membre/benevole.
 * Un gestionnaire a automatiquement accès aux routes membre/benevole.
 * Un membre a automatiquement accès aux routes benevole.
 *
 * Historique : la fusion du 21/07/2026 (depuis amana_web_planning et
 * amana_web_familles) avait documenté ici une cascade benevole → membre qui
 * n'a jamais été implémentée (isBenevole()/isMembre() ne se référençaient
 * pas l'une l'autre) et qui, de toute façon, contredisait la sémantique
 * réelle de amana_web_planning (voir database/seeders/
 * PlanningApplicationSeeder.php côté planning : benevole y est
 * délibérément restreint, EN DESSOUS de membre — la cascade documentée
 * aurait donné à tout bénévole planning un accès Bilan qu'il n'a jamais eu
 * ni n'est censé avoir). Audit du 30/08/2026 : aucune route ni entrée de
 * nav, dans amana_web_familles ou amana_web_planning, ne dépendait de la
 * cascade non implémentée — ce correctif est donc sans impact sur le
 * comportement actuel des deux apps, et se contente d'aligner code et
 * documentation sur la hiérarchie standard ci-dessus.
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
            'membre' => $personne->isMembre(),
            // isBenevole() cascade déjà depuis isMembre() (donc depuis
            // gestionnaire/admin aussi) — voir Personne::isBenevole().
            'benevole' => $personne->isBenevole(),
            // Pas de cascade depuis gestionnaire/membre/benevole (voir
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
