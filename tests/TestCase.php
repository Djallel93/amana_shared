<?php
// tests/TestCase.php

declare(strict_types=1);

namespace Amana\Shared\Tests;

use Amana\Shared\AmanaSharedServiceProvider;
use Amana\Shared\Helpers\AuditHelper;
use Amana\Shared\Models\Personne;
use Amana\Shared\Tests\Support\FakeUser;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base des tests : démarre une VRAIE application Laravel minimale (sans
 * Testbench ni skeleton), avec le service provider du package, les
 * fournisseurs du framework utiles (routage, session, auth, validation,
 * mail, notifications, cache, base SQLite en mémoire) et un noyau HTTP
 * réduit. Les requêtes passent par le vrai routeur, les vrais middlewares
 * et le vrai moteur Blade.
 *
 * Les tables partagées (amana_commun) sont recréées en SQLite avec le
 * strict nécessaire — voir createCommunSchema().
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        $this->app = $this->createApplication();
        $this->createCommunSchema();
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        AuditHelper::clearCache();
    }

    /** @return array<string, mixed> */
    protected function configuration(): array
    {
        $tmp = sys_get_temp_dir() . '/amana-shared-tests';
        @mkdir($tmp . '/views/layouts', 0777, true);
        // Layout local de l'app. Le vrai (amana-shared::layouts.app) charge Vite
        // dans <head>, sans intérêt ici : on garde ce qu'il assemble autour du
        // contenu — sidebar, messages flash, section « content ».
        file_put_contents($tmp . '/views/layouts/app.blade.php', <<<'BLADE'
<!DOCTYPE html><html lang="fr"><body>
@include('amana-shared::layouts.partials.sidebar')
@include('amana-shared::layouts.partials.flash')
@yield('content')
</body></html>
BLADE);

        return [
            'app' => [
                'name' => 'AMANA Test',
                'env' => 'testing',
                'debug' => true,
                'url' => 'http://amana.test',
                'key' => 'base64:' . base64_encode(str_repeat('k', 32)),
                'cipher' => 'AES-256-CBC',
                'timezone' => 'UTC',
                'locale' => 'fr',
                'fallback_locale' => 'fr',
            ],
            'auth' => [
                'defaults' => ['guard' => 'web', 'passwords' => 'personnes'],
                'guards' => ['web' => ['driver' => 'session', 'provider' => 'personnes']],
                'providers' => ['personnes' => ['driver' => 'eloquent', 'model' => Personne::class]],
                'passwords' => ['personnes' => [
                    'provider' => 'personnes',
                    'table' => 'password_reset_tokens',
                    'connection' => 'commun',
                    'expire' => 60,
                    'throttle' => 0,
                ]],
                'password_timeout' => 10800,
            ],
            'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array', 'serialize' => false]], 'prefix' => ''],
            'database' => [
                'default' => 'commun',
                'connections' => ['commun' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]],
            ],
            'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']], 'failed' => ['driver' => 'null']],
            'hashing' => ['driver' => 'bcrypt', 'bcrypt' => ['rounds' => 4]],
            'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class]]],
            'mail' => [
                'default' => 'array',
                'mailers' => ['array' => ['transport' => 'array']],
                'from' => ['address' => 'noreply@amana.test', 'name' => 'AMANA'],
            ],
            'session' => [
                'driver' => 'array', 'lifetime' => 120, 'expire_on_close' => false, 'encrypt' => false,
                'cookie' => 'amana_session', 'path' => '/', 'domain' => null, 'secure' => false,
                'http_only' => true, 'same_site' => 'lax', 'lottery' => [0, 100],
            ],
            'view' => ['paths' => [$tmp . '/views'], 'compiled' => $tmp . '/compiled'],
            'amana-shared' => require dirname(__DIR__) . '/config/amana-shared.php',
        ];
    }

    protected function createApplication(): Application
    {
        $tmp = sys_get_temp_dir() . '/amana-shared-tests';
        @mkdir($tmp . '/compiled', 0777, true);
        @mkdir($tmp . '/storage/framework', 0777, true);

        // Certains composants du framework (notification de réinitialisation) déduisent le
        // namespace de l'app depuis composer.json.
        file_put_contents($tmp . '/composer.json', '{"autoload":{"psr-4":{"App\\\\":"app/"}}}');

        $app = new Application($tmp);
        $app->useStoragePath($tmp . '/storage');
        $app->instance('config', new Repository($this->configuration()));
        $app->instance('request', Request::create('/'));
        $app->singleton(ExceptionHandler::class, Handler::class);
        $app->singleton(\Illuminate\Contracts\Http\Kernel::class, fn ($app) => new class ($app, $app['router']) extends Kernel {
            protected $bootstrappers = [];
        });

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        foreach ([
            \Illuminate\Filesystem\FilesystemServiceProvider::class,
            \Illuminate\Cache\CacheServiceProvider::class,
            \Illuminate\Cookie\CookieServiceProvider::class,
            \Illuminate\Encryption\EncryptionServiceProvider::class,
            \Illuminate\Hashing\HashServiceProvider::class,
            \Illuminate\Session\SessionServiceProvider::class,
            \Illuminate\Translation\TranslationServiceProvider::class,
            \Illuminate\Validation\ValidationServiceProvider::class,
            \Illuminate\View\ViewServiceProvider::class,
            \Illuminate\Database\DatabaseServiceProvider::class,
            \Illuminate\Auth\AuthServiceProvider::class,
            \Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
            \Illuminate\Mail\MailServiceProvider::class,
            \Illuminate\Bus\BusServiceProvider::class,
            \Illuminate\Queue\QueueServiceProvider::class,
            \Illuminate\Notifications\NotificationServiceProvider::class,
            \Illuminate\Foundation\Providers\FormRequestServiceProvider::class,
            \Illuminate\Foundation\Providers\FoundationServiceProvider::class,
            AmanaSharedServiceProvider::class,
        ] as $provider) {
            $app->register($provider);
        }

        $app->boot();

        return $app;
    }

    /** Tables partagées minimales (équivalent SQLite des migrations database/migrations). */
    protected function createCommunSchema(): void
    {
        $schema = Schema::connection('commun');

        $schema->create('ref_applications', function ($t) {
            $t->increments('id');
            $t->string('code', 50)->unique();
            $t->string('libelle', 100)->nullable();
        });
        $schema->create('ref_roles', function ($t) {
            $t->increments('id');
            $t->string('code', 50);
            $t->string('libelle', 100);
            $t->unsignedInteger('id_application');
        });
        $schema->create('ref_personnes', function ($t) {
            $t->increments('id');
            $t->string('nom', 100);
            $t->string('prenom', 100);
            $t->string('email', 255)->unique();
            $t->string('password')->nullable();
            $t->rememberToken();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('telephone', 20)->nullable();
            $t->date('date_debut_planning')->nullable();
            $t->string('statut')->default('En attente');
            $t->timestamp('derniere_maj')->nullable();
        });
        $schema->create('ref_personnes_roles', function ($t) {
            $t->unsignedInteger('id_personne');
            $t->unsignedInteger('id_role');
            $t->date('date_attribution')->nullable();
            $t->primary(['id_personne', 'id_role']);
        });
        $schema->create('password_reset_tokens', function ($t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });
        $schema->create('audit_logs', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id')->nullable();
            $t->unsignedInteger('id_application')->nullable();
            $t->string('action');
            $t->string('module');
            $t->unsignedInteger('entity_id')->nullable();
            $t->string('entity_type')->nullable();
            $t->text('before')->nullable();
            $t->text('after')->nullable();
            $t->string('ip_address')->nullable();
            $t->text('user_agent')->nullable();
            $t->timestamps();
        });

        DB::connection('commun')->table('ref_applications')->insert(['id' => 1, 'code' => 'planning', 'libelle' => 'Planning']);
    }

    /**
     * Crée une vraie Personne (avec rôle applicatif éventuel) en base.
     *
     * @param  string[]  $roles  codes de rôles pour l'application courante ('planning')
     */
    protected function personne(array $roles = [], array $attributs = []): Personne
    {
        static $n = 0;
        $n++;

        $p = new Personne(array_merge([
            'nom' => 'Nom' . $n,
            'prenom' => 'Prenom' . $n,
            'email' => "personne{$n}@amana.test",
            'password' => 'x',
            'statut' => 'Validé',
        ], $attributs));
        $p->save();

        foreach ($roles as $code) {
            $role = \Amana\Shared\Models\Role::query()->firstOrCreate(
                ['code' => $code, 'id_application' => 1],
                ['libelle' => ucfirst($code)],
            );
            DB::connection('commun')->table('ref_personnes_roles')->insert([
                'id_personne' => $p->id, 'id_role' => $role->id, 'date_attribution' => '2026-01-01',
            ]);
        }

        return $p;
    }

    protected function actingAs(?Personne $personne): void
    {
        $guard = $this->app['auth']->guard('web');
        $personne ? $guard->setUser($personne) : $guard->forgetUser();
    }

    /**
     * Enregistre une route sous le groupe « web » minimal (session + erreurs).
     * Retourne la route pour chaîner ->name()/->middleware().
     */
    protected function route(string $method, string $uri, mixed $action): \Illuminate\Routing\Route
    {
        return $this->app['router']->match([$method], $uri, $action)
            ->middleware([StartSession::class, \Illuminate\View\Middleware\ShareErrorsFromSession::class]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function call(string $method, string $uri, array $data = [], array $headers = []): Response
    {
        $request = Request::create($uri, $method, $data);
        foreach ($headers as $k => $v) {
            $request->headers->set($k, $v);
        }

        /** @var \Illuminate\Contracts\Http\Kernel $kernel */
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);

        return $kernel->handle($request);
    }

    /** @return array<string, mixed> */
    protected function json(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Emails envoyés via le transport « array » (aucun envoi réel).
     *
     * @return array<int, array{to: string, subject: string, html: string}>
     */
    protected function mails(): array
    {
        $transport = $this->app['mail.manager']->mailer('array')->getSymfonyTransport();

        return $transport->messages()->map(function ($sent) {
            $email = $sent->getOriginalMessage();

            return [
                'to' => $email->getTo()[0]->getAddress(),
                'subject' => (string) $email->getSubject(),
                'html' => (string) $email->getHtmlBody(),
            ];
        })->values()->all();
    }

    protected function viderMails(): void
    {
        $this->app['mail.manager']->mailer('array')->getSymfonyTransport()->flush();
    }

    /** Les six routes de la page « Mon profil », telles que documentées dans le README. */
    protected function routesProfil(bool $avecExtra = true): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('auth', \Illuminate\Auth\Middleware\Authenticate::class);
        $router->aliasMiddleware('signed', \Illuminate\Routing\Middleware\ValidateSignature::class);

        $this->route('GET', '/login', fn () => 'login')->name('login');
        $this->route('GET', '/mot-de-passe-oublie', fn () => 'oublie')->name('password.request');
        $this->route('GET', '/nouveau-mot-de-passe/{token}', fn () => 'reset')->name('password.reset');
        $this->route('GET', '/', fn () => 'accueil')->name('home');
        $this->route('POST', '/logout', fn () => 'bye')->name('logout');
        $this->app['config']->set('amana-shared.home_route', 'home');
        $this->app['config']->set('amana-shared.profile_email_dns', false);

        $c = \Amana\Shared\Http\Controllers\ProfileController::class;
        $this->route('GET', '/mon-profil', [$c, 'edit'])->name('profile.edit')->middleware('auth');
        $this->route('PUT', '/mon-profil', [$c, 'update'])->name('profile.update')->middleware('auth');
        $this->route('POST', '/mon-profil/email', [$c, 'requestEmailChange'])->name('profile.email.request')->middleware('auth');
        $this->route('GET', '/mon-profil/email/confirmer', [$c, 'confirmEmailChange'])->name('profile.email.confirm')->middleware(['auth', 'signed']);
        $this->route('PUT', '/mon-profil/mot-de-passe', [$c, 'updatePassword'])->name('profile.password.update')->middleware('auth');
        if ($avecExtra) {
            $this->route('PUT', '/mon-profil/extra', [$c, 'updateExtra'])->name('profile.extra.update')->middleware('auth');
        }
        $router->getRoutes()->refreshNameLookups();
    }

    /** Dernière session ouverte par le noyau (pour lire flash/erreurs). */
    protected function fakeUser(string $niveau = 'benevole', array $codes = [], int $id = 1): FakeUser
    {
        return new FakeUser(id: $id, niveau: $niveau, codes: $codes);
    }
}
