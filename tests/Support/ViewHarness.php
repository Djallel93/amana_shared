<?php
// tests/Support/ViewHarness.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Support;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

/**
 * Rend les vraies vues Blade du package (namespace `amana-shared::`) avec le
 * vrai BladeCompiler, sans démarrer une application Laravel : les quelques
 * helpers globaux qu'utilisent ces vues (route(), asset(), auth(), request(),
 * config(), app(), csrf_field()) sont remplacés par des doublures qui lisent
 * l'état statique ci-dessous. Ces doublures ne sont définies que si le helper
 * n'existe pas déjà (donc jamais à l'intérieur d'une vraie application).
 */
final class ViewHarness
{
    public static ?Authenticatable $user = null;

    /** @var array<string, mixed> */
    public static array $config = [];

    /** Noms de routes que Route::has() doit considérer comme existants. */
    public static array $routes = [];

    public static string $currentRoute = '';

    public static Container $container;

    public static function boot(?string $viewsDir = null): Factory
    {
        self::$user = null;
        self::$config = [];
        self::$routes = [];
        self::$currentRoute = '';
        self::$container = new Container();
        Container::setInstance(self::$container);

        self::defineHelpers();

        $viewsDir ??= dirname(__DIR__, 2) . '/resources/views';
        $cache = sys_get_temp_dir() . '/amana-shared-blade-' . md5($viewsDir);
        @mkdir($cache, 0777, true);
        foreach (glob($cache . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        $files = new Filesystem();
        $compiler = new BladeCompiler($files, $cache);
        $resolver = new EngineResolver();
        $resolver->register('blade', fn () => new CompilerEngine($compiler, $files));
        $resolver->register('php', fn () => new PhpEngine($files));

        $finder = new FileViewFinder($files, []);
        $finder->addNamespace('amana-shared', $viewsDir);

        $factory = new Factory($resolver, $finder, new Dispatcher(self::$container));
        $factory->setContainer(self::$container);
        self::$container->instance('view', $factory);

        return $factory;
    }

    /**
     * Rend une vue et renvoie le HTML (espaces blancs normalisés, pour comparer
     * deux rendus indépendamment de l'indentation Blade).
     *
     * @param  array<string, mixed>  $data
     */
    public static function render(Factory $factory, string $view, array $data = []): string
    {
        return $factory->make($view, $data)->render();
    }

    public static function normalize(string $html): string
    {
        $html = preg_replace('/\s+/', ' ', $html) ?? $html;
        $html = preg_replace('/>\s+</', '><', $html) ?? $html;

        return trim($html);
    }

    private static function defineHelpers(): void
    {
        if (! function_exists('config')) {
            eval('function config($key = null, $default = null) { return \Amana\Shared\Tests\Support\ViewHarness::cfg($key, $default); }');
        }
        if (! function_exists('route')) {
            eval('function route($name, $params = [], $absolute = true) { return "/r/" . $name . (is_array($params) && $params ? "?" . http_build_query($params) : ""); }');
        }
        if (! function_exists('asset')) {
            eval('function asset($path) { return "/" . ltrim($path, "/"); }');
        }
        if (! function_exists('auth')) {
            eval('function auth($guard = null) { return new \Amana\Shared\Tests\Support\FakeGuard(\Amana\Shared\Tests\Support\ViewHarness::$user); }');
        }
        if (! function_exists('request')) {
            eval('function request($key = null) { return new \Amana\Shared\Tests\Support\FakeRequest(); }');
        }
        if (! function_exists('app')) {
            eval('function app($abstract = null, array $params = []) { return $abstract === null ? \Amana\Shared\Tests\Support\ViewHarness::$container : \Amana\Shared\Tests\Support\ViewHarness::$container->make($abstract, $params); }');
        }
        if (! function_exists('csrf_field')) {
            eval('function csrf_field() { return new \Illuminate\Support\HtmlString(\'<input type="hidden" name="_token" value="test">\'); }');
        }
        if (! function_exists('csrf_token')) {
            eval('function csrf_token() { return "test"; }');
        }
    }

    public static function cfg(mixed $key, mixed $default): mixed
    {
        return $key === null ? self::$config : (self::$config[$key] ?? $default);
    }
}

final class FakeGuard
{
    public function __construct(private ?Authenticatable $user)
    {
    }

    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    public function guard(?string $name = null): self
    {
        return $this;
    }

    public function check(): bool
    {
        return $this->user !== null;
    }

    public function guest(): bool
    {
        return $this->user === null;
    }

    public function id()
    {
        return $this->user?->getAuthIdentifier();
    }
}

final class FakeRequest
{
    public function routeIs(string ...$patterns): bool
    {
        foreach ($patterns as $p) {
            if (\Illuminate\Support\Str::is($p, ViewHarness::$currentRoute)) {
                return true;
            }
        }

        return false;
    }
}
