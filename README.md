# amana/shared

Fondation partagée pour toutes les apps AMANA (Laravel 13 / PHP 8.4) :
personnes, rôles, applications, réglages, audit, authentification et
UI commune (layout + sidebar).

Package privé — voir `docs/architecture.md` du dépôt principal pour le
contexte complet de cette architecture.

## Pièges connus

**Héritage de connexion sur les relations hasMany/hasOne.** Si un modèle
applicatif (ex. `Restriction`, `Absence`, `Famille`) est le "many" d'une
relation `hasMany`/`hasOne` définie sur un modèle partagé (`Personne`,
`Quartier`...), et ne déclare **pas explicitement** sa propre connexion,
Eloquent (`HasRelationships::newRelatedInstance()`) lui fait hériter la
connexion du modèle PARENT dès qu'il est chargé via cette relation — même
si ce modèle applicatif fonctionne parfaitement en dehors de toute
relation. Concrètement : `Personne::restrictions()` chargerait
silencieusement `plan_restrictions` depuis `amana_commun` au lieu de la
base de l'app, provoquant une erreur "table doesn't exist" (la table existe
bien, juste pas dans la base interrogée).

**Tout modèle applicatif relié en hasMany/hasOne depuis un modèle partagé
DOIT déclarer explicitement sa connexion** :

```php
public function getConnectionName(): ?string
{
    return config('database.default');
}
```

Voir `Restriction`, `Absence`, `CreneauTache` dans `amana_web_planning`, et
`Famille` dans `amana_web_familles` (via `Quartier::familles()`) pour des
exemples concrets. Les modèles partagés eux-mêmes (`Role`, `Application`,
`Setting`, `AuditLog`, `Ville`, `Secteur`) n'ont pas ce problème : ils
déclarent déjà tous leur connexion explicitement.

## Assets de marque (logo, favicons, icônes PWA)

Le logo AMANA, les favicons et les icônes PWA (`amana-logo.png`,
`favicon.ico`, `favicon.svg`, `favicon-96x96.png`, `apple-touch-icon.png`,
`web-app-manifest-{192,512}x192.png`) sont identiques entre toutes les apps
AMANA (vérifié octet pour octet entre planning et familles le 04/08/2026,
avant leur centralisation ici) — voir `resources/images/` dans ce package.

Contrairement à la config/aux vues, ces fichiers doivent être physiquement
présents dans le `public/` de chaque app consommatrice : Laravel sert
`public/` directement, il n'existe pas de mécanisme pour servir des
fichiers statiques depuis `vendor/` sans route dédiée. Le shell partagé
(`amana-shared::layouts.partials.head`) les référence déjà via
`asset('favicon.ico')` etc. — chaque app doit simplement les avoir
physiquement à ces chemins, en les publiant une fois :

```bash
php artisan vendor:publish --tag=amana-shared-assets
```

**`site.webmanifest` n'est PAS publié par ce tag** — son contenu
(`name`/`short_name`) diffère légitimement par app (ex. "AMANA Planning"
vs "AMANA Familles"), il reste propre à chaque app et n'a pas sa place ici.

### Mettre à jour le logo/les favicons pour toutes les apps

1. Remplacer les fichiers dans `amana_shared/resources/images/`.
2. Tagger une nouvelle version (`git tag v1.x.x && git push --tags`).
3. Dans chaque app : `composer update amana/shared`, puis republier en
   forçant l'écrasement des fichiers déjà présents :
   ```bash
   php artisan vendor:publish --tag=amana-shared-assets --force
   ```
4. Rebuild (`npm run build`) et redéployer normalement — ces fichiers ne
   passent pas par Vite, mais un rebuild reste nécessaire pour que le
   `rsync` du déploiement les inclue dans l'artefact livré.

## Installation dans une app consommatrice

### 1. Composer (dépôt privé)

Dans `composer.json` de l'app :

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/Djallel93/amana_shared.git"
    }
  ],
  "require": {
    "amana/shared": "^1.0"
  }
}
```

Pour le développement local (éditer `amana_shared` et voir les changements
reflétés immédiatement dans l'app, sans re-tagger à chaque fois), chaque app
consommatrice est déjà équipée du plugin `wikimedia/composer-merge-plugin` :
copier `composer.local.json.dist` en `composer.local.json` (gitignoré,
jamais commité) puis `composer install` suffit — le `composer.json` commité,
lui, ne change pas et continue de pointer vers le dépôt git privé taggé.
Voir `docs/local-development.md` de chaque app pour le détail complet
(y compris le pendant côté npm via `npm link`, qui ne nécessite aucune
configuration supplémentaire puisque `node_modules/` est déjà gitignoré).

Authentification : le dépôt étant privé, Composer a besoin d'un token.
Voir `docs/composer-auth.md` (local : `composer config --global
github-oauth.github.com <token>` ou `auth.json` non commité ; CI : secret
`COMPOSER_AUTH` — voir `.github/workflows/deploy.yaml` de chaque app).

### 2. Config

```bash
php artisan vendor:publish --tag=amana-shared-config
```

Éditer `config/amana-shared.php` : `app_code`, `home_route`, `branding`,
`audit.modules`/`audit.actions`, `nav`.

### 3. Connexion `commun`

Ajouter dans `config/database.php` :

```php
'commun' => [
    'driver' => 'mysql',
    'host' => env('DB_COMMUN_HOST', env('DB_HOST', '127.0.0.1')),
    'port' => env('DB_COMMUN_PORT', env('DB_PORT', '3306')),
    'database' => env('DB_COMMUN_DATABASE', 'amana_commun'),
    'username' => env('DB_COMMUN_USERNAME', env('DB_USERNAME', 'forge')),
    'password' => env('DB_COMMUN_PASSWORD', env('DB_PASSWORD', '')),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => null,
],
```

### 4. Auth

Dans `config/auth.php`, faire pointer le provider `personnes` sur le
modèle applicatif qui étend le modèle partagé (voir étape 5) :

```php
'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'personnes'],
],
'providers' => [
    'personnes' => ['driver' => 'eloquent', 'model' => App\Models\Personne::class],
],
'passwords' => [
    'personnes' => [
        'provider' => 'personnes',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],
],
```

`password_reset_tokens` vit dans `amana_commun`, pas dans la base par
défaut de l'app. Laravel accepte nativement une connexion dédiée pour le
broker de mots de passe — ajouter `'connection' => 'commun'` :

```php
'passwords' => [
    'personnes' => [
        'provider' => 'personnes',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
        'connection' => 'commun',
    ],
],
```

### 5. Étendre le modèle Personne

Créer `app/Models/Personne.php` dans l'app pour toute relation/logique
propre à l'app (ex. absences, restrictions dans amana_web_planning) :

```php
namespace App\Models;

use Amana\Shared\Models\Personne as SharedPersonne;

class Personne extends SharedPersonne
{
    public function absences() { return $this->hasMany(Absence::class, 'id_personne'); }
    // ...
}
```

### 6. Middleware

Dans `bootstrap/app.php` :

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'auth' => \Amana\Shared\Http\Middleware\EnsureAuthenticated::class,
        'role' => \Amana\Shared\Http\Middleware\EnsureRole::class,
    ]);
})
```

### 7. Routes

L'app garde ses propres routes, elle pointe vers les contrôleurs partagés :

```php
use Amana\Shared\Http\Controllers\AuthController;
use Amana\Shared\Http\Controllers\AuditLogController;
use Amana\Shared\Http\Controllers\ActivityStatsController;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');

Route::middleware(['auth', 'role:gestionnaire'])->group(function () {
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index'); // App\Http\Controllers\SettingsController extends SettingsControllerBase
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/journal', [AuditLogController::class, 'index'])->name('admin.journal.index');
    Route::get('/journal/data', [AuditLogController::class, 'data'])->name('admin.journal.data');
    Route::get('/activite', [ActivityStatsController::class, 'index'])->name('admin.activite.index');
    Route::get('/activite/data', [ActivityStatsController::class, 'data'])->name('admin.activite.data');
});
```

### 8. Statistiques d'activité

Lier votre implémentation dans `AppServiceProvider::register()` :

```php
$this->app->bind(
    \Amana\Shared\Contracts\ActivityStatisticsProvider::class,
    \App\Services\AuditStatistics::class
);
```

### 9. Layout local (une ligne)

`resources/views/layouts/app.blade.php` :

```blade
@extends('amana-shared::layouts.app')
```

### 10. Migrations du schéma partagé

**Jamais** via `php artisan migrate`. Une seule fois, manuellement :

```bash
php artisan amana:migrate-shared          # applique les migrations manquantes
php artisan amana:migrate-shared --fresh  # DANGER — recrée tout amana_commun
```

`php artisan migrate` / `migrate:fresh` de l'app elle-même ne voient
jamais ces migrations (pas de `loadMigrationsFrom()` dans le service
provider) — ils ne touchent que la base de données par défaut de l'app.

### 11. Nettoyage à faire dans chaque app existante

- Supprimer `app/Models/{Personne,Role,Application,Setting,AuditLog}.php`
  (ou les remplacer par un `extends` du modèle partagé si l'app a besoin
  d'ajouter des relations).
- Supprimer `app/Http/Middleware/{EnsureAuthenticated,EnsureRole}.php`.
- Supprimer `app/Helpers/helpers.php` si son seul contenu est `audit()`
  (sinon retirer juste cette fonction) — sinon collision de déclaration
  avec `Amana\Shared\Helpers\helpers.php`.
- Supprimer de `database/migrations/` : la partie ref_roles/ref_personnes
  de `create_base_tables` (garder `ref_taches`, propre au planning),
  `create_ref_applications_table`, `create_audit_logs_table`,
  `create_ref_settings_table`, `refactor_auth_create_password_reset_tokens`.
- `amana_web_familles` : supprimer entièrement
  `create_shared_tables_for_standalone_testing.php` et
  `add_encrypted_type_to_ref_settings.php` (le type encrypted est inclus
  dès la création dans `amana_shared`).
- Supprimer `resources/views/auth/{login,forgot-password,reset-password}.blade.php`,
  `resources/views/layouts/partials/{head,sidebar,flash}.blade.php`
  (remplacés par les inclusions `amana-shared::...`).

## Géographie partagée : Ville / Secteur / Quartier

Déplacées depuis `amana_web_familles` le 21/07/2026 : bien que seule
Familles les utilise aujourd'hui, ce référentiel géographique
(agglomération nantaise — 19 villes / 57 secteurs / 97 quartiers, polygones
MULTIPOLYGON SRID 4326) est générique et vaut la peine d'être partagé
plutôt que dupliqué pour chaque future app à dimension géographique.

- Modèles : `Amana\Shared\Models\{Ville,Secteur,Quartier}` — mêmes API
  qu'avant, connexion `commun` automatique. `Quartier` ne porte PAS de
  relation vers une entité applicative (ex. `Famille`) — chaque app qui en
  a besoin étend le modèle localement, voir `App\Models\Quartier` dans
  amana_web_familles pour l'exemple (`familles(): HasMany`).
- Migrations : `create_villes_table`, `create_secteurs_table`,
  `create_quartiers_table` — comme toute migration partagée, exécutées
  uniquement via `amana:migrate-shared`, jamais par le `migrate` d'une app.
- Peuplement des données réelles (19 villes / 97 quartiers) : seeders
  `Amana\Shared\Database\Seeders\{GeoSeeder,TestGeoSeeder}`, exécutables
  depuis N'IMPORTE QUELLE app consommatrice (ils ciblent `amana_commun`
  eux-mêmes, indépendamment de la connexion par défaut de l'app appelante) :

  ```bash
  php artisan db:seed --class="Amana\Shared\Database\Seeders\GeoSeeder"
  # ou, pour les tests (géographie synthétique minimale et déterministe) :
  php artisan db:seed --class="Amana\Shared\Database\Seeders\TestGeoSeeder"
  ```

- **Cross-DB FK** : toute app qui référence `quartiers.id` depuis sa propre
  base (ex. `familles.id_quartier`) ne peut PAS poser de contrainte FK MySQL
  dessus (bases différentes) — colonne simple, relation Eloquent uniquement,
  comme pour `ref_personnes`. Voir `create_familles_table.php` dans
  amana_web_familles pour l'exemple.
- **Tests** : si une app a des tests qui touchent `villes`/`secteurs`/
  `quartiers` (via `RefreshDatabase`, `TestGeoSeeder`, etc.), pensez à
  surcharger `DB_COMMUN_DATABASE` dans son `phpunit.xml`/`.env.testing` vers
  une base de test dédiée (ex. `amana_commun_testing`) — sinon les tests
  écrivent pour de vrai dans la base `amana_commun` de dev partagée.
  (amana_web_familles n'a plus de suite de tests à ce stade — la suite
  d'origine, qui couvrait notamment cette résolution géographique, a été
  retirée le 23/07/2026 ; à recréer le jour où l'app en a besoin, en gardant
  ce point de configuration à l'esprit dès le premier `phpunit.xml`.)
