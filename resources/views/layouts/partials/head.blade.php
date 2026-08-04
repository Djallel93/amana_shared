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

    {{--
        Favicons / icônes PWA — fichiers réels publiés depuis amana/shared
        (voir AmanaSharedServiceProvider::boot(), tag 'amana-shared-assets').
        Identiques entre toutes les apps AMANA (vérifié octet pour octet le
        04/08/2026) ; seul site.webmanifest reste propre à chaque app (son
        contenu name/short_name diffère légitimement).
    --}}
    <link rel="icon" type="image/png" href="{{ asset('favicon-96x96.png') }}" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="apple-mobile-web-app-title" content="{{ config('amana-shared.branding.tagline_short', config('amana-shared.branding.app_name')) }}">

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