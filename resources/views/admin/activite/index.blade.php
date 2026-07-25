{{-- vendor/amana-shared/resources/views/admin/activite/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Statistiques d\'activité')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Statistiques d'activité</h1>
        <p class="text-[13px] text-ink-muted mt-1">Utilisation de l'application</p>
    </div>
</div>

{{--
    Point de montage du composant Vue d'affichage propre à CHAQUE app
    (ex: ActiviteStatistiques.vue dans amana_web_planning) — la forme des
    métriques renvoyées par /admin/activite/data dépend de l'implémentation
    de Amana\Shared\Contracts\ActivityStatisticsProvider que l'app a liée,
    donc le composant qui les affiche reste propre à l'app, contrairement
    au shell Blade ci-dessus qui, lui, est partagé.
--}}
<div id="vue-activite-statistiques"></div>

@endsection

@push('scripts')
<script>
    window.ActiviteStatistiquesConfig = {
        csrf: document.querySelector('meta[name="csrf-token"]').content,
        routes: {
            data: '{{ route('admin.activite.data') }}',
        },
    };
</script>
@endpush
