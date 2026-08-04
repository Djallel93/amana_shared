{{-- vendor/amana-shared/resources/views/auth/reset-password.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe — {{ config('amana-shared.branding.app_name') }}</title>
    @vite(['resources/css/app.css'])
</head>

<body class="flex min-h-screen bg-surface-2 font-body antialiased">

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
            <p class="text-[14.5px] text-white/45 leading-relaxed">Choisissez un nouveau mot de passe.</p>
        </div>
    </div>

    <div class="w-full sm:w-[480px] bg-surface flex items-center justify-center px-6 py-10 sm:px-14 flex-shrink-0">
        <div class="w-full max-w-sm">

            <div class="flex sm:hidden items-center gap-3 mb-8">
                <img src="{{ asset('images/amana-logo.png') }}" alt="AMANA" class="w-9 h-9 rounded-lg object-cover">
                <span
                    class="font-heading text-lg font-semibold text-ink">{{ config('amana-shared.branding.app_name') }}</span>
            </div>

            <h2 class="font-heading text-2xl font-semibold text-ink mb-1.5 tracking-tight">Nouveau mot de passe</h2>
            <p class="text-[13.5px] text-ink-muted mb-7 leading-relaxed">Au moins 8 caractères.</p>

            <form action="{{ route('password.update') }}" method="POST" novalidate data-no-dirty-check>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="mb-4">
                    <label for="email" class="block text-xs font-bold text-ink mb-1.5 tracking-[0.2px]">Adresse
                        email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $email) }}" autocomplete="email"
                        autofocus class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]
                                hover:border-ink-muted">
                    @error('email')<span class="block text-xs text-rose-600 mt-1">{{ $message }}</span>@enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="block text-xs font-bold text-ink mb-1.5 tracking-[0.2px]">Nouveau mot
                        de passe</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" oninput="checkStrength(this.value)"
                            autocomplete="new-password" placeholder="Au moins 8 caractères" required class="w-full px-3.5 py-2.5 pr-11 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                    focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]
                                    hover:border-ink-muted">
                        <button type="button" onclick="toggleVisibility('password', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-muted hover:text-ink transition-colors text-base leading-none bg-transparent border-0 cursor-pointer p-1 min-h-[44px] min-w-[44px] flex items-center justify-center">
                            👁️
                        </button>
                    </div>
                    @error('password')<span class="block text-xs text-rose-600 mt-1">{{ $message }}</span>@enderror
                </div>

                <div class="mb-6">
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div id="strengthFill" class="h-full rounded-full transition-all" style="width: 0%"></div>
                    </div>
                    <span id="strengthLabel" class="block text-xs mt-1 font-medium"></span>
                </div>

                <div class="mb-6">
                    <label for="password_confirmation"
                        class="block text-xs font-bold text-ink mb-1.5 tracking-[0.2px]">Confirmer le mot de
                        passe</label>
                    <div class="relative">
                        <input type="password" id="password_confirmation" name="password_confirmation"
                            autocomplete="new-password" placeholder="Répétez le mot de passe" required class="w-full px-3.5 py-2.5 pr-11 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                    focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]
                                    hover:border-ink-muted">
                        <button type="button" onclick="toggleVisibility('password_confirmation', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-muted hover:text-ink transition-colors text-base leading-none bg-transparent border-0 cursor-pointer p-1 min-h-[44px] min-w-[44px] flex items-center justify-center">
                            👁️
                        </button>
                    </div>
                    @error('password_confirmation')<span
                    class="block text-xs text-rose-600 mt-1">{{ $message }}</span>@enderror
                </div>

                <button type="submit" class="w-full min-h-[48px] px-6 py-3 bg-accent hover:bg-accent-dark text-white font-bold text-sm rounded-lg
                            shadow-[0_3px_14px_rgba(3,105,161,0.35)] hover:shadow-[0_6px_20px_rgba(3,105,161,0.45)]
                            hover:-translate-y-px active:translate-y-0 transition-all cursor-pointer">
                    🔐 Enregistrer mon mot de passe
                </button>
            </form>
        </div>
    </div>

    <script>
        function toggleVisibility(id, btn) {
            const input = document.getElementById(id);
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.textContent = input.type === 'password' ? '👁️' : '🙈';
        }

        function checkStrength(password) {
            const fill = document.getElementById('strengthFill');
            const label = document.getElementById('strengthLabel');
            if (!password) { fill.style.width = '0%'; label.textContent = ''; return; }
            let score = 0;
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            const levels = [
                { pct: '20%', color: '#e11d48', text: 'Très faible' },
                { pct: '40%', color: '#f59e0b', text: 'Faible' },
                { pct: '60%', color: '#eab308', text: 'Moyen' },
                { pct: '80%', color: '#22c55e', text: 'Fort' },
                { pct: '100%', color: '#059669', text: 'Très fort' },
            ];
            const level = levels[Math.min(score - 1, 4)] || levels[0];
            fill.style.width = level.pct;
            fill.style.background = level.color;
            label.textContent = level.text;
            label.style.color = level.color;
        }
    </script>
</body>

</html>