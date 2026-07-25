<?php
// config/amana-shared.php
//
// Config publiée dans chaque app consommatrice via :
//   php artisan vendor:publish --tag=amana-shared-config
//
// Chaque app DOIT adapter ce fichier à son propre contexte — rien ici n'a
// de valeur par défaut « correcte » pour toutes les apps.

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Code applicatif
    |--------------------------------------------------------------------------
    |
    | Doit correspondre à ref_applications.code pour cette app (ex: 'planning',
    | 'familles'). Utilisé par AuditHelper, Setting::get()/set(), et les
    | contrôleurs partagés (Settings, Audit, Stats) pour scoper leurs requêtes.
    |
    */
    'app_code' => env('AMANA_APP_CODE', 'planning'),

    /*
    |--------------------------------------------------------------------------
    | Nom de connexion DB partagée
    |--------------------------------------------------------------------------
    |
    | Nom de la connexion (config/database.php) pointant vers amana_commun.
    | Les modèles partagés (Personne, Role, Application, Setting, AuditLog)
    | l'utilisent tous via $connection = config('amana-shared.connection').
    |
    */
    'connection' => env('AMANA_COMMUN_CONNECTION', 'commun'),

    /*
    |--------------------------------------------------------------------------
    | Route "maison" de repli
    |--------------------------------------------------------------------------
    |
    | Nom de route utilisé par EnsureRole (accès refusé) et par
    | AuthController::showLogin()/showForgotPassword() (déjà connecté) pour
    | rediriger vers la page d'accueil de CETTE app. Chaque app définit la
    | sienne (ex: 'planning.index', 'familles.index').
    |
    */
    'home_route' => env('AMANA_HOME_ROUTE', 'planning.index'),

    /*
    |--------------------------------------------------------------------------
    | Image de marque — vue de connexion / mot de passe oublié
    |--------------------------------------------------------------------------
    */
    'branding' => [
        'app_name' => env('AMANA_APP_NAME', 'AMANA'),
        'tagline' => 'Application AMANA',
        // Liste de [emoji, libellé] affichés dans le panneau gauche de la
        // page de connexion. Vide = panneau gauche minimal (logo + tagline).
        'features' => [],
        // Lien optionnel affiché sous le formulaire de connexion, ex. pour
        // un flux d'inscription publique propre à l'app (candidature,
        // demande d'accès...). Laisser à null pour ne rien afficher.
        'signup_route_name' => null,
        'signup_label' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Journal d'audit — vocabulaires de filtre
    |--------------------------------------------------------------------------
    |
    | Chaque app a ses propres modules/actions métier. Le contrôleur partagé
    | AuditLogControllerBase lit ces listes pour peupler ses filtres et
    | valider les requêtes ; il ne les devine pas depuis le code applicatif.
    |
    */
    'audit' => [
        'modules' => [],
        'actions' => ['create', 'update', 'delete', 'generate', 'login', 'logout', 'webhook'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sidebar — éléments de navigation propres à l'app
    |--------------------------------------------------------------------------
    |
    | Le shell (structure, collapse, badge de rôle) est fourni par le
    | package ; chaque app fournit sa propre liste de sections/liens.
    | Chaque item : ['route' => nom de route, 'label' => libellé,
    | 'icon' => emoji/svg, 'role' => null|'membre'|'gestionnaire'|'admin'].
    |
    | Exemple :
    |   'nav' => [
    |       ['section' => 'Planning'],
    |       ['route' => 'planning.index', 'label' => 'Planning', 'icon' => '📅'],
    |       ['route' => 'settings.index', 'label' => 'Paramètres', 'icon' => '⚙️', 'role' => 'gestionnaire'],
    |   ],
    |
    */
    'nav' => [],
];
