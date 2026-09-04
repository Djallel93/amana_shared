{{-- vendor/amana-shared/resources/views/layouts/app.blade.php --}}
{{--
Layout principal authentifié — commun à toutes les apps AMANA.
Chaque app garde un resources/views/layouts/app.blade.php local, réduit
à une seule ligne :

@extends('amana-shared::layouts.app')

...ce qui permet à toutes les vues existantes qui font déjà
@extends('layouts.app') (sans changement) de continuer à fonctionner :
Blade résout 'layouts.app' contre les vues LOCALES de l'app en premier,
qui elle-même @extends la version partagée.

Pour ajouter du contenu propre à une app en plus du shell partagé
(rare — la sidebar/le contenu suffisent à la plupart des pages),
surcharger les sections 'scripts-extra' ou publier cette vue.
--}}
<!DOCTYPE html>
<html lang="fr">

@include('amana-shared::layouts.partials.head')

<body class="bg-surface-2 font-body text-ink antialiased flex min-h-screen">

    @include('amana-shared::layouts.partials.sidebar')

    {{-- ── Contenu principal ── --}}
    <div id="mainWrapper"
        class="flex-1 flex flex-col min-w-0 ml-sidebar transition-all duration-300 max-sm:ml-0 max-sm:pt-topbar">
        <main class="flex-1 p-8 max-w-screen-xl w-full mx-auto max-lg:p-7 max-sm:px-4 max-sm:py-5">

            @include('amana-shared::layouts.partials.flash')

            @yield('content')

        </main>
    </div>

    {{--
    Points de montage des composants Vue partagés (@amana/shared-ui) :
    MobileSidebar (collapse/mobile de #mainSidebar), Toast, ConfirmDialog
    (remplace confirm() natif), OfflineBanner, UrgentAlertBar et
    NotificationBell (ces deux derniers ajoutés le 03/09/2026 — centre de
    notifications partagé, voir amana_shared/NotificationCenterService).
    Chaque app les importe et les monte dans son propre
    resources/js/app.ts — ces div ne sont que les points d'ancrage,
    communs à toutes les pages du layout.
    --}}
    <div id="vue-mobile-sidebar"></div>

    @stack('scripts')

    <div id="vue-toast"></div>
    <div id="vue-confirm-dialog"></div>
    <div id="vue-offline-banner"></div>
    <div id="vue-urgent-alert-bar"></div>
    <div id="vue-notification-bell"></div>

</body>

</html>