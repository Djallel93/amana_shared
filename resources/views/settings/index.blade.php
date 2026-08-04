{{-- vendor/amana-shared/resources/views/settings/index.blade.php --}}
{{--
Vue générique pour une app qui n'a pas besoin de regrouper ses réglages
par catégorie (ex: une nouvelle app avec juste quelques clés simples).
Les apps avec des besoins de présentation plus riches (amana_web_planning
et son regroupement offset_*/couleur_*/calendar_* + registre de
calendriers Google) surchargent SettingsControllerBase::index() et
fournissent leur propre vue — celle-ci reste le point de départ pour
toute nouvelle app.
--}}
@extends('layouts.app')

@section('title', 'Paramètres')

@section('content')

    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Paramètres</h1>
            <p class="text-[13px] text-ink-muted mt-1">Réglages de l'application</p>
        </div>
    </div>

    <form action="{{ route('settings.update') }}" method="POST" class="max-w-2xl">
        @csrf

        <div class="bg-surface border border-surface-border rounded-lg divide-y divide-surface-border">
            @forelse($settings as $cle => $data)
                <div class="p-4 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <label for="setting-{{ $cle }}"
                            class="block text-sm font-semibold text-ink">{{ $data['libelle'] }}</label>
                        @if($data['description'])
                            <p class="text-xs text-ink-muted mt-0.5">{{ $data['description'] }}</p>
                        @endif
                    </div>
                    <div class="flex-shrink-0 w-56">
                        @if($data['type'] === 'boolean')
                            <select id="setting-{{ $cle }}" name="settings[{{ $cle }}]"
                                class="w-full px-3 py-2 border-[1.5px] border-ink-faint rounded-lg text-sm bg-surface-2 text-ink">
                                <option value="1" @selected($data['valeur'])>Activé</option>
                                <option value="0" @selected(!$data['valeur'])>Désactivé</option>
                            </select>
                        @else
                            <input type="text" id="setting-{{ $cle }}" name="settings[{{ $cle }}]" value="{{ $data['valeur'] }}"
                                class="w-full px-3 py-2 border-[1.5px] border-ink-faint rounded-lg text-sm bg-surface-2 text-ink">
                        @endif
                    </div>
                </div>
            @empty
                <p class="p-4 text-sm text-ink-muted">Aucun paramètre configuré pour cette application.</p>
            @endforelse
        </div>

        <button type="submit"
            class="mt-5 px-5 py-2.5 bg-accent hover:bg-accent-dark text-white font-bold text-sm rounded-lg transition-colors cursor-pointer">
            Enregistrer
        </button>
    </form>

@endsection