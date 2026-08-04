{{-- vendor/amana-shared/resources/views/auth/forgot-password.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié — {{ config('amana-shared.branding.app_name') }}</title>
    @vite(['resources/css/app.css'])
</head>

<body class="flex min-h-screen bg-surface-2 font-body antialiased">

    {{-- Panneau gauche --}}
    <div
        class="hidden sm:flex flex-1 bg-sidebar flex-col items-center justify-center px-12 py-16 relative overflow-hidden">
        <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-accent/30 blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-20 -right-20 w-72 h-72 rounded-full bg-sky-400/15 blur-3xl pointer-events-none">
        </div>
        <div class="relative z-10 text-center max-w-sm">
            <div
                class="w-30 h-30 mx-auto mb-6 rounded-full overflow-hidden bg-white shadow-[0_16px_40px_rgba(0,0,0,0.35)]">
                <img src="{{ asset('images/amana-logo.png') }}" alt="AMANA" class="w-full h-full object-cover">
            </div>
            <h1 class="font-heading text-3xl font-semibold text-white mb-2.5 tracking-tight">
                {{ config('amana-shared.branding.app_name') }}</h1>
            <p class="text-[14.5px] text-white/45 leading-relaxed">Réinitialisez votre mot de passe en quelques
                secondes.</p>
        </div>
    </div>

    {{-- Panneau droit --}}
    <div class="w-full sm:w-[480px] bg-surface flex items-center justify-center px-6 py-10 sm:px-14 flex-shrink-0">
        <div class="w-full max-w-sm">

            <div class="flex sm:hidden items-center gap-3 mb-8">
                <img src="{{ asset('images/amana-logo.png') }}" alt="AMANA" class="w-9 h-9 rounded-lg object-cover">
                <span
                    class="font-heading text-lg font-semibold text-ink">{{ config('amana-shared.branding.app_name') }}</span>
            </div>

            <h2 class="font-heading text-2xl font-semibold text-ink mb-1.5 tracking-tight">Mot de passe oublié</h2>
            <p class="text-[13.5px] text-ink-muted mb-7 leading-relaxed">
                Indiquez votre email, nous vous enverrons un lien de réinitialisation.
            </p>

            @if(session('success'))
                <div
                    class="flex items-start gap-2.5 px-4 py-3 rounded-lg mb-5 text-[13px] font-medium bg-emerald-50 border border-emerald-200 text-emerald-800">
                    ✅ {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('password.email') }}" method="POST" novalidate data-no-dirty-check>
                @csrf

                <div class="mb-6">
                    <label for="email" class="block text-xs font-bold text-ink mb-1.5 tracking-[0.2px]">Adresse
                        email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email"
                        autofocus placeholder="votre@email.fr" class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]
                                hover:border-ink-muted">
                    @error('email')<span class="block text-xs text-rose-600 mt-1">{{ $message }}</span>@enderror
                </div>

                <button type="submit" class="w-full min-h-[48px] px-6 py-3 bg-accent hover:bg-accent-dark text-white font-bold text-sm rounded-lg
                            shadow-[0_3px_14px_rgba(3,105,161,0.35)] hover:shadow-[0_6px_20px_rgba(3,105,161,0.45)]
                            hover:-translate-y-px active:translate-y-0 transition-all cursor-pointer mb-4">
                    📧 Envoyer le lien
                </button>
            </form>

            <div class="h-px bg-gray-100 my-5"></div>
            <p class="text-center text-[13px] text-ink-muted">
                <a href="{{ route('login') }}" class="text-accent font-semibold hover:underline">← Retour à la
                    connexion</a>
            </p>
        </div>
    </div>

</body>

</html>