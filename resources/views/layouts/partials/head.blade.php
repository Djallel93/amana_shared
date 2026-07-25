{{-- vendor/amana-shared/resources/views/layouts/partials/head.blade.php --}}
{{--
    Head du layout principal partagé (layouts/app.blade.php).
    CSS/JS compilés via Vite dans CHAQUE app (pas de bundle partagé — voir
    docs/architecture.md, section "pourquoi pas de build Vite partagé").
--}}
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('amana-shared.branding.app_name'))</title>

    @hasSection('favicon')
        @yield('favicon')
    @endif

    {{--
        Applique le thème (clair/sombre) avant le premier rendu, pour éviter
        un flash de thème clair si l'utilisateur a choisi le mode sombre.
        Script inline synchrone, placé avant le CSS Vite. La logique de
        bascule (bouton, persistance) vit dans @amana/shared-ui (lib/theme.ts),
        importée par chaque app dans son propre resources/js/app.ts.
    --}}
    <script>
        (function () {
            var stored = localStorage.getItem('amana-theme');
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (!stored && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.ts'])

    @stack('styles')
</head>
