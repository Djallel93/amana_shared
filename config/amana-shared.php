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
        // Utilisés par emails/partials/_footer.blade.php (voir plus bas) —
        // ajoutés le 04/08/2026 lors de la centralisation des partials
        // email. email_footer_text reprend la phrase de amana_web_planning
        // à titre d'exemple ; CHAQUE app doit la personnaliser (celle de
        // familles disait "suite à une action d'un administrateur...").
        'email_footer_text' => 'Vous recevez cet email suite à la validation de votre candidature bénévole.',
        'contact_email' => 'amana44.benevole@gmail.com',
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

    /*
    |--------------------------------------------------------------------------
    | Badges de navigation en direct
    |--------------------------------------------------------------------------
    |
    | Rafraîchissement des badges numériques de la sidebar sans recharger la
    | page (voir README, « Badges de navigation en direct »). Actif seulement
    | pour une app qui a lié Contracts\NavBadgeProvider ET enregistré la route
    | ci-dessous ; sinon rien ne change et aucune requête n'est émise. Ces
    | clés sont lues avec ces mêmes valeurs par défaut dans le code : une app
    | dont config/amana-shared.php est ancien fonctionne sans les ajouter.
    |
    |   nav_badges_route         : nom de la route JSON enregistrée par l'app
    |   nav_badges_poll_seconds  : intervalle entre deux interrogations (min. 15)
    |   nav_badges_cache_seconds : durée de cache des compteurs, partagée entre
    |                              utilisateurs (0 = pas de cache ; à mettre à 0
    |                              si les compteurs dépendent de l'utilisateur)
    |
    */
    'nav_badges_route' => 'nav-badges.index',
    'nav_badges_poll_seconds' => 45,
    'nav_badges_cache_seconds' => 10,

    /*
    |--------------------------------------------------------------------------
    | Page « Mon profil »
    |--------------------------------------------------------------------------
    |
    | Comme pour les badges : opt-in par app. La sidebar n'affiche la pastille
    | d'initiales (et le lien vers le profil) que si l'app a enregistré la route
    | `profile_route` ; sinon l'ancien logo reste en place. Lues avec ces valeurs
    | par défaut dans le code : un config/amana-shared.php publié plus ancien
    | fonctionne sans ces clés.
    |
    |   profile_route     : nom de la route d'affichage (GET) du profil
    |   profile_email_dns : contrôle DNS (email:rfc,dns) sur la nouvelle adresse ;
    |                       false = format RFC seulement (env. sans réseau / tests)
    |
    */
    'profile_route' => 'profile.edit',
    'profile_email_dns' => true,

    /*
    |--------------------------------------------------------------------------
    | Thème couleur des emails (resources/views/emails/partials/_head.blade.php)
    |--------------------------------------------------------------------------
    |
    | Ajouté le 04/08/2026 : _head.blade.php (styles des emails de
    | notification) était structurellement identique entre planning et
    | familles, seules ces 12 valeurs différaient (bleu marine/sky vs
    | ambre/terracotta) — vérifié octet pour octet avant centralisation.
    | Valeurs par défaut ci-dessous = celles de amana_web_planning ; CHAQUE
    | app doit republier ce fichier et fournir SA propre palette (voir
    | amana_web_familles pour l'exemple ambre/terracotta).
    |
    | accent_rgb / accent_light_rgb : triplets "R, G, B" SANS le wrapper
    | rgba() — composés inline dans le template via rgba({{ ... }}, 0.35)
    | pour ne pas dupliquer une clé par valeur d'opacité utilisée.
    |
    */
    'email_theme' => [
        'header_bg' => '#0c1e2e',          // fond du header + de la features-card
        'accent' => '#0369a1',              // couleur d'accent principale (boutons, liens, textes de marque)
        'accent_dark' => '#0284c7',         // 2e couleur du dégradé "stripe"
        'accent_light' => '#0ea5e9',        // 3e couleur du dégradé "stripe"
        'accent_rgb' => '3, 105, 161',      // = accent, en triplet RGB pour rgba()
        'accent_light_rgb' => '14, 165, 233', // = accent_light, en triplet RGB pour rgba()
        'accent_light_text' => '#7dd3fc',   // texte clair sur fond sombre (bismillah, badge, features-label)
        'accent_pale_text' => '#bae6fd',    // texte encore plus pâle sur fond sombre (corps des lignes de la features-card)
        'accent_darker' => '#0c4a6e',       // texte foncé d'emphase sur fond clair (body-text strong, hadith arabe)
        'hadith_french_text' => '#1e4a6e',  // texte de la traduction française du hadith (ton distinct de accent_darker)
        'accent_pale_bg' => '#f0f6fb',      // fond pâle (carte hadith, footer)
        'accent_pale_border' => '#c7dff0',  // bordure pâle (carte hadith, footer, footer-divider)
    ],
];
