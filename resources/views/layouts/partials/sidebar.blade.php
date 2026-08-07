{{-- vendor/amana-shared/resources/views/layouts/partials/sidebar.blade.php --}}
{{--
Shell de la sidebar — structure, comportement collapse/mobile, badge de
rôle et footer utilisateur sont communs à toutes les apps AMANA. Les
liens de navigation eux-mêmes sont propres à chaque app et viennent de
config('amana-shared.nav') plutôt que d'être codés en dur ici (c'était
le cas dans amana_web_planning avant la migration vers amana_shared).

Format attendu de config('amana-shared.nav') — tableau ordonné de :
['section' => 'Libellé de section'] — titre de groupe
['route' => 'planning.index', 'label' => 'Planning',
'icon' => '📅', 'role' => null|'membre'|'gestionnaire'|'admin',
'route_pattern' => 'planning.*'] — 'role' filtre l'affichage,
'route_pattern' pilote le
surlignage actif (défaut: 'route')
--}}

{{-- ── Mobile topbar ── --}}
<div
    class="sm:hidden fixed top-0 left-0 right-0 h-topbar bg-sidebar z-[300] flex items-center justify-between px-4 border-b border-white/[0.06]">
    <a href="{{ route(config('amana-shared.home_route')) }}" class="flex items-center gap-2.5 no-underline">
        <span class="w-8 h-8 rounded-full overflow-hidden flex-shrink-0 inline-block">
            <img src="{{ asset('favicon-96x96.png') }}" alt="AMANA" class="w-full h-full object-cover scale-90">
        </span>
        <span
            class="font-heading text-[15px] font-semibold text-white">{{ config('amana-shared.branding.app_name') }}</span>
    </a>
    <button id="hamburgerBtn"
        class="flex flex-col gap-[5px] items-center justify-center w-10 h-10 rounded-md text-white/70 hover:bg-white/10 hover:text-white/75 transition-colors"
        aria-label="Menu" aria-expanded="false">
        <span class="hamburger-bar"></span>
        <span class="hamburger-bar"></span>
        <span class="hamburger-bar"></span>
    </button>
</div>

{{-- ── Overlay mobile ── --}}
<div id="sidebarOverlay" onclick="closeSidebar()"
    class="sm:hidden fixed inset-0 bg-black/50 z-[198] opacity-0 pointer-events-none">
</div>

{{-- ── Sidebar ── --}}
<aside id="mainSidebar"
    class="w-sidebar min-h-screen bg-sidebar flex flex-col fixed top-0 left-0 bottom-0 z-[200] overflow-hidden sidebar-hidden"
    aria-label="Navigation principale">

    {{-- Brand --}}
    <div class="px-5 py-[22px] pb-[18px] border-b border-white/[0.06] relative z-10">
        <a href="{{ route(config('amana-shared.home_route')) }}" class="flex items-center gap-[11px] no-underline">
            <span
                class="w-[38px] h-[38px] rounded-full overflow-hidden flex-shrink-0 shadow-[0_4px_12px_rgba(0,0,0,0.3)] inline-block">
                <img src="{{ asset('favicon-96x96.png') }}" alt="AMANA" class="w-full h-full object-cover scale-90">
            </span>
            <div class="flex flex-col">
                <span
                    class="font-heading text-[16px] font-semibold text-white leading-none tracking-[0.2px]">AMANA</span>
                <span
                    class="text-[10px] text-white/35 tracking-widest uppercase font-medium mt-0.5">{{ config('amana-shared.branding.tagline_short', '') }}</span>
            </div>
        </a>
    </div>

    {{-- Nav section --}}
    <div class="px-3.5 py-4 flex-1 overflow-y-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden relative z-10">

        {{-- Badge rôle --}}
        @auth
            @if(auth()->user()->isAdmin())
                <div class="mx-1 mb-2.5 px-[11px] py-[7px] rounded-sm text-[11px] font-semibold flex items-center gap-1.5
                                    bg-rose-500/[0.14] text-rose-300 border border-rose-500/[0.22]">
                    🛡️ Administrateur
                </div>
            @elseif(auth()->user()->isGestionnaire())
                <div class="mx-1 mb-2.5 px-[11px] py-[7px] rounded-sm text-[11px] font-semibold flex items-center gap-1.5
                                    bg-amber-500/[0.14] text-amber-300 border border-amber-500/[0.22]">
                    ⚙️ Gestionnaire
                </div>
            @elseif(method_exists(auth()->user(), 'isBenevole') && auth()->user()->isBenevole())
                <div class="mx-1 mb-2.5 px-[11px] py-[7px] rounded-sm text-[11px] font-semibold flex items-center gap-1.5
                                    bg-emerald-500/[0.14] text-emerald-300 border border-emerald-500/[0.22]">
                    🤝 Bénévole
                </div>
            @else
                <div class="mx-1 mb-2.5 px-[11px] py-[7px] rounded-sm text-[11px] font-semibold flex items-center gap-1.5
                                    bg-sky-500/[0.14] text-sky-300 border border-sky-500/[0.22]">
                    👤 Membre
                </div>
            @endif
        @endauth

        {{-- ── Navigation propre à l'app, depuis config('amana-shared.nav') ── --}}
        @foreach(config('amana-shared.nav', []) as $item)
            @if(isset($item['section']))
                <p class="px-2.5 mb-1 mt-3 first:mt-0 text-[9.5px] font-bold tracking-[1.4px] uppercase text-white/20">
                    {{ $item['section'] }}
                </p>
            @else
                @continue(!empty($item['role']) && !auth()->user()?->hasAtLeastRole($item['role']))
                @php $navBadge = ($navBadges ?? [])[$item['route']] ?? 0; @endphp
                <a href="{{ route($item['route']) }}"
                    class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                                {{ request()->routeIs($item['route_pattern'] ?? $item['route']) ? 'nav-item-active bg-accent/15 text-white font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
                    onclick="closeSidebar()">
                    <span class="text-sm w-[18px] text-center flex-shrink-0">{{ $item['icon'] ?? '•' }}</span>
                    <span class="flex-1">{{ $item['label'] }}</span>
                    @if($navBadge > 0)
                        <span
                            class="flex-shrink-0 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-bold leading-none bg-rose-500 text-white"
                            aria-label="{{ $navBadge }} en attente"
                        >{{ $navBadge > 99 ? '99+' : $navBadge }}</span>
                    @endif
                </a>
            @endif
        @endforeach

    </div>

    {{-- Footer utilisateur --}}
    <div class="px-3.5 py-3.5 border-t border-white/[0.06]">
        <div class="flex items-center gap-2.5 px-2.5 py-2 rounded-sm bg-white/[0.05]">
            <div
                class="w-8 h-8 bg-accent rounded-full flex items-center justify-center text-xs text-white font-bold flex-shrink-0">
                {{ strtoupper(substr(auth()->user()->prenom ?? 'A', 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0 overflow-hidden">
                <div class="text-[12.5px] text-white/80 font-semibold truncate">
                    {{ auth()->user()->prenom ?? '' }} {{ auth()->user()->nom ?? '' }}
                </div>
                <div class="text-[11px] text-white/32 mt-px">
                    @if(auth()->user()->isAdmin()) Administrateur
                    @elseif(auth()->user()->isGestionnaire()) Gestionnaire
                    @else Membre
                    @endif
                </div>
            </div>
            <button type="button" onclick="toggleAppTheme()" title="Changer le thème"
                class="text-white/30 hover:text-accent-light text-base p-1 rounded transition-colors bg-transparent border-0 cursor-pointer leading-none flex-shrink-0 min-h-[44px] min-w-[44px] flex items-center justify-center">
                <span data-theme-icon>🌙</span>
            </button>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit"
                    class="text-white/30 hover:text-rose-400 text-base p-1 rounded transition-colors bg-transparent border-0 cursor-pointer leading-none flex-shrink-0 min-h-[44px] min-w-[44px] flex items-center justify-center"
                    title="Déconnexion">↪</button>
            </form>
        </div>
    </div>

</aside>