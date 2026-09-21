{{-- vendor/amana-shared/resources/views/profile/edit.blade.php --}}
{{--
Page « Mon profil » — commune à toutes les apps AMANA, accessible à toute
personne connectée (aucun middleware de rôle). Chaque section a son propre
formulaire, son propre bouton et son propre sac d'erreurs (informations,
email, password, extra) : enregistrements indépendants, erreurs plus claires
sur téléphone.

Variables : $personne, $roles (libellés, app courante), $extension,
$extensionTitle, $extensionFields, $extensionView.
--}}
@extends('layouts.app')

@section('title', 'Mon profil')

@section('content')
    @php
        $cssChamp = 'w-full px-3 py-2 border-[1.5px] border-ink-faint rounded-lg text-sm bg-surface-2 text-ink';
        $cssCarte = 'bg-surface border border-surface-border rounded-lg p-5 mb-5';
        $cssTitre = 'font-heading text-lg font-semibold text-ink mb-1';
        $cssBouton = 'mt-4 px-5 py-2.5 min-h-[44px] bg-accent hover:bg-accent-dark text-white font-bold text-sm rounded-lg transition-colors cursor-pointer';
        $infos = $errors->getBag('informations');
        $mail = $errors->getBag('email');
        $mdp = $errors->getBag('password');
        $extra = $errors->getBag('extra');
    @endphp

    <div class="max-w-2xl">

        {{-- ── En-tête ── --}}
        <div class="{{ $cssCarte }} flex items-center gap-4">
            @include('amana-shared::layouts.partials.avatar', ['personne' => $personne, 'size' => 72])
            <div class="min-w-0">
                <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight truncate">
                    {{ $personne->prenom }} {{ $personne->nom }}
                </h1>
                <p class="text-[13px] text-ink-muted truncate">{{ $personne->email }}</p>
                @if(count($roles) > 0)
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        @foreach($roles as $libelle)
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-accent/15 text-accent">{{ $libelle }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Mes informations ── --}}
        <section id="informations" class="{{ $cssCarte }}" aria-labelledby="t-informations">
            <h2 id="t-informations" class="{{ $cssTitre }}">Mes informations</h2>
            <form action="{{ route('profile.update') }}" method="POST" novalidate>
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3">
                    <div>
                        <label for="p-prenom" class="block text-sm font-semibold text-ink mb-1.5">Prénom <span class="text-rose-500">*</span></label>
                        <input type="text" id="p-prenom" name="prenom" maxlength="100" required autocomplete="given-name"
                            value="{{ old('prenom', $personne->prenom) }}" class="{{ $cssChamp }}">
                        @if($infos->has('prenom'))<p class="text-xs text-rose-600 mt-1" role="alert">{{ $infos->first('prenom') }}</p>@endif
                    </div>
                    <div>
                        <label for="p-nom" class="block text-sm font-semibold text-ink mb-1.5">Nom <span class="text-rose-500">*</span></label>
                        <input type="text" id="p-nom" name="nom" maxlength="100" required autocomplete="family-name"
                            value="{{ old('nom', $personne->nom) }}" class="{{ $cssChamp }}">
                        @if($infos->has('nom'))<p class="text-xs text-rose-600 mt-1" role="alert">{{ $infos->first('nom') }}</p>@endif
                    </div>
                    <div class="sm:col-span-2">
                        <label for="p-telephone" class="block text-sm font-semibold text-ink mb-1.5">Téléphone</label>
                        <input type="tel" id="p-telephone" name="telephone" maxlength="20" inputmode="tel" autocomplete="tel"
                            value="{{ old('telephone', $personne->telephone) }}" class="{{ $cssChamp }}">
                        <p class="text-xs text-ink-muted mt-1">Exemples : 06 12 34 56 78, +33 6 12 34 56 78</p>
                        @if($infos->has('telephone'))<p class="text-xs text-rose-600 mt-1" role="alert">{{ $infos->first('telephone') }}</p>@endif
                    </div>
                </div>
                <button type="submit" class="{{ $cssBouton }}">Enregistrer mes informations</button>
            </form>
        </section>

        {{-- ── Adresse email ── --}}
        <section id="email" class="{{ $cssCarte }}" aria-labelledby="t-email">
            <h2 id="t-email" class="{{ $cssTitre }}">Adresse email</h2>
            <p class="text-[13px] text-ink-muted">
                Adresse actuelle : <strong class="text-ink">{{ $personne->email }}</strong>.
                Pour la changer, indiquez la nouvelle adresse et votre mot de passe : un lien de confirmation
                sera envoyé à la <em>nouvelle</em> adresse, et l'actuelle ne change qu'après confirmation.
            </p>
            <form action="{{ route('profile.email.request') }}" method="POST" novalidate>
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3">
                    <div>
                        <label for="e-email" class="block text-sm font-semibold text-ink mb-1.5">Nouvelle adresse <span class="text-rose-500">*</span></label>
                        <input type="email" id="e-email" name="email" maxlength="255" required autocomplete="email"
                            value="{{ old('email') }}" class="{{ $cssChamp }}">
                        @if($mail->has('email'))<p class="text-xs text-rose-600 mt-1" role="alert">{{ $mail->first('email') }}</p>@endif
                    </div>
                    <div>
                        <label for="e-mdp" class="block text-sm font-semibold text-ink mb-1.5">Mot de passe actuel <span class="text-rose-500">*</span></label>
                        <input type="password" id="e-mdp" name="current_password" required autocomplete="current-password" class="{{ $cssChamp }}">
                        @if($mail->has('current_password'))<p class="text-xs text-rose-600 mt-1" role="alert">{{ $mail->first('current_password') }}</p>@endif
                    </div>
                </div>
                <button type="submit" class="{{ $cssBouton }}">Envoyer le lien de confirmation</button>
            </form>
        </section>

        {{-- ── Mot de passe ── --}}
        <section id="mot-de-passe" class="{{ $cssCarte }}" aria-labelledby="t-mdp">
            <h2 id="t-mdp" class="{{ $cssTitre }}">Mot de passe</h2>
            <p class="text-[13px] text-ink-muted">
                Ce mot de passe est commun à toutes les applications AMANA : après un changement, vous devrez peut-être
                vous reconnecter sur les autres.
            </p>
            <form action="{{ route('profile.password.update') }}" method="POST" novalidate>
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 gap-4 mt-3 sm:max-w-sm">
                    <div>
                        <label for="m-actuel" class="block text-sm font-semibold text-ink mb-1.5">Mot de passe actuel <span class="text-rose-500">*</span></label>
                        <input type="password" id="m-actuel" name="current_password" required autocomplete="current-password" class="{{ $cssChamp }}">
                        @if($mdp->has('current_password'))<p class="text-xs text-rose-600 mt-1" role="alert">{{ $mdp->first('current_password') }}</p>@endif
                    </div>
                    <div>
                        <label for="m-nouveau" class="block text-sm font-semibold text-ink mb-1.5">Nouveau mot de passe <span class="text-rose-500">*</span></label>
                        <input type="password" id="m-nouveau" name="password" minlength="8" required autocomplete="new-password" class="{{ $cssChamp }}">
                        <p class="text-xs text-ink-muted mt-1">8 caractères minimum.</p>
                        @if($mdp->has('password'))<p class="text-xs text-rose-600 mt-1" role="alert">{{ $mdp->first('password') }}</p>@endif
                    </div>
                    <div>
                        <label for="m-confirm" class="block text-sm font-semibold text-ink mb-1.5">Confirmer le nouveau mot de passe <span class="text-rose-500">*</span></label>
                        <input type="password" id="m-confirm" name="password_confirmation" minlength="8" required autocomplete="new-password" class="{{ $cssChamp }}">
                    </div>
                </div>
                <button type="submit" class="{{ $cssBouton }}">Modifier mon mot de passe</button>
            </form>
        </section>

        {{-- ── Mon rôle (lecture seule) ── --}}
        <section id="role" class="{{ $cssCarte }}" aria-labelledby="t-role">
            <h2 id="t-role" class="{{ $cssTitre }}">Mon rôle</h2>
            @if(count($roles) > 0)
                <div class="flex flex-wrap gap-1.5 mt-2">
                    @foreach($roles as $libelle)
                        <span class="inline-block px-2.5 py-0.5 rounded-full text-[12px] font-semibold bg-accent/15 text-accent">{{ $libelle }}</span>
                    @endforeach
                </div>
            @else
                <p class="text-[13px] text-ink-muted mt-2">Aucun rôle n'est attribué sur cette application.</p>
            @endif
            <p class="text-xs text-ink-muted mt-3">Les rôles sont gérés par un administrateur : ils ne peuvent pas être modifiés ici.</p>
        </section>

        {{-- ── Informations spécifiques à l'app (si l'app a lié une extension) ── --}}
        {{-- Section masquée quand l'extension n'a rien à montrer pour cette personne (fields() vide, pas de vue) --}}
        @if($extension !== null && (count($extensionFields) > 0 || $extensionView))
            <section id="extra" class="{{ $cssCarte }}" aria-labelledby="t-extra">
                <h2 id="t-extra" class="{{ $cssTitre }}">{{ $extensionTitle }}</h2>
                <form action="{{ route('profile.extra.update') }}" method="POST" novalidate>
                    @csrf
                    @method('PUT')
                    <div class="mt-3">
                        @if($extensionView)
                            @include($extensionView, ['personne' => $personne, 'fields' => $extensionFields, 'title' => $extensionTitle])
                        @else
                            @include('amana-shared::profile._extension-fields', ['fields' => $extensionFields])
                        @endif
                    </div>
                    <button type="submit" class="{{ $cssBouton }}">Enregistrer</button>
                </form>
            </section>
        @endif

    </div>
@endsection
