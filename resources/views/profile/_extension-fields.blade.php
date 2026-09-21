{{-- vendor/amana-shared/resources/views/profile/_extension-fields.blade.php --}}
{{--
Rendu déclaratif des champs d'une Contracts\ProfileExtension.
Variables : $fields (voir le contrat), $errors.
--}}
@php
    $bag = $errors->getBag('extra');
    $cssChamp = 'w-full px-3 py-2 border-[1.5px] border-ink-faint rounded-lg text-sm bg-surface-2 text-ink';
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    @foreach($fields as $champ)
        @php
            $nom = $champ['name'];
            $type = $champ['type'] ?? 'text';
            $id = 'extra-' . $nom;
            $valeur = old($nom, $champ['value'] ?? null);
            $erreur = $bag->first($nom) ?: $bag->first($nom . '.*');
            $large = in_array($type, ['textarea', 'multiselect'], true);
        @endphp
        <div class="{{ $large ? 'sm:col-span-2' : '' }}">
            @if($type === 'checkbox')
                <label for="{{ $id }}" class="flex items-center gap-2.5 min-h-[44px] cursor-pointer">
                    <input type="hidden" name="{{ $nom }}" value="0">
                    <input type="checkbox" id="{{ $id }}" name="{{ $nom }}" value="1" {{ $valeur ? 'checked' : '' }}
                        class="w-5 h-5 rounded border-ink-faint accent-[var(--color-accent,#0369a1)]">
                    <span class="text-sm font-semibold text-ink">{{ $champ['label'] }}</span>
                </label>
            @elseif($type === 'multiselect')
                <span class="block text-sm font-semibold text-ink mb-1.5">
                    {{ $champ['label'] }}@if(!empty($champ['required'])) <span class="text-rose-500">*</span>@endif
                </span>
                @php
                    $coches = array_map('strval', (array) $valeur);
                @endphp
                {{-- Champ « présent » : sans lui, décocher toutes les cases n'enverrait rien du tout. Les valeurs vides sont écartées côté contrôleur. --}}
                <input type="hidden" name="{{ $nom }}[]" value="">
                <div class="flex flex-wrap gap-2">
                    @foreach(($champ['options'] ?? []) as $val => $libelle)
                        <label class="inline-flex items-center gap-2 min-h-[44px] px-3 rounded-lg border-[1.5px] border-ink-faint bg-surface-2 text-sm text-ink cursor-pointer">
                            <input type="checkbox" name="{{ $nom }}[]" value="{{ $val }}" {{ in_array((string) $val, $coches, true) ? 'checked' : '' }}
                                class="w-4 h-4 rounded border-ink-faint">
                            <span>{{ $libelle }}</span>
                        </label>
                    @endforeach
                </div>
            @else
                <label for="{{ $id }}" class="block text-sm font-semibold text-ink mb-1.5">
                    {{ $champ['label'] }}@if(!empty($champ['required'])) <span class="text-rose-500">*</span>@endif
                </label>
                @if($type === 'select')
                    <select id="{{ $id }}" name="{{ $nom }}" class="{{ $cssChamp }}">
                        @unless(!empty($champ['required']))
                            <option value="">—</option>
                        @endunless
                        @foreach(($champ['options'] ?? []) as $val => $libelle)
                            <option value="{{ $val }}" {{ (string) $valeur === (string) $val ? 'selected' : '' }}>{{ $libelle }}</option>
                        @endforeach
                    </select>
                @elseif($type === 'textarea')
                    <textarea id="{{ $id }}" name="{{ $nom }}" rows="3" class="{{ $cssChamp }} resize-y">{{ $valeur }}</textarea>
                @else
                    <input type="{{ in_array($type, ['tel', 'number', 'date'], true) ? $type : 'text' }}" id="{{ $id }}"
                        name="{{ $nom }}" value="{{ is_array($valeur) ? '' : $valeur }}"
                        @if($type === 'tel') inputmode="tel" autocomplete="tel" @endif
                        class="{{ $cssChamp }}">
                @endif
            @endif
            @if(!empty($champ['hint']))
                <p class="text-xs text-ink-muted mt-1">{{ $champ['hint'] }}</p>
            @endif
            @if($erreur)
                <p class="text-xs text-rose-600 mt-1" role="alert">{{ $erreur }}</p>
            @endif
        </div>
    @endforeach
</div>
