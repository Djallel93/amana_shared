{{-- vendor/amana-shared/resources/views/admin/journal/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Journal d\'audit')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Journal d'audit</h1>
        <p class="text-[13px] text-ink-muted mt-1">
            Historique des actions sensibles (créations, modifications, suppressions, connexions)
        </p>
    </div>
</div>

{{--
    Point de montage JournalAudit.vue (voir @amana/shared-ui) — filtres +
    tableau paginé + diff avant/après dépliable. Données via GET
    /admin/journal/data (Amana\Shared\Http\Controllers\AuditLogController::data),
    route déclarée par l'app consommatrice elle-même (voir routes/web.php).
--}}
<div id="vue-journal-audit"></div>

@endsection

@push('scripts')
<script>
    window.JournalAuditConfig = {
        csrf: document.querySelector('meta[name="csrf-token"]').content,
        routes: {
            data: '{{ route('admin.journal.data') }}',
        },
        modules: @json($modules),
        actions: @json($actions),
        personnes: @json($personnes->map(fn($p) => [
            'id' => $p->id,
            'nom' => "{$p->prenom} {$p->nom}",
        ])),
    };
</script>
@endpush
