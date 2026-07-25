<?php
// src/Http/Middleware/EnsureAuthenticated.php

declare(strict_types=1);

namespace Amana\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de base : vérifie que l'utilisateur est connecté.
 *
 * Si non connecté → redirige vers /login avec un message flash.
 * Ne vérifie PAS les rôles — délègue ça à EnsureRole.
 *
 * Chaque app enregistre l'alias 'auth' → ce middleware dans bootstrap/app.php
 * (ou continue d'utiliser son propre alias existant s'il pointe déjà ici).
 */
class EnsureAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login')
                ->with('error', 'Vous devez être connecté pour accéder à cette page.');
        }

        return $next($request);
    }
}
