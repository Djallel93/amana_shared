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
'icon' => '📅', 'role' => null|'membre'|'gestionnaire'|'admin'|<code de rôle applicatif quelconque>,
'route_pattern' => 'planning.*'] — 'role' filtre l'affichage,
'route_pattern' pilote le
surlignage actif (défaut: 'route')

'role' : d'abord testé contre la hiérarchie interne (hasAtLeastRole,
admin ⊇ gestionnaire ⊇ membre ⊇ benevole) pour ces 4 codes précis ; pour
tout autre code, visible pour qui détient spécifiquement ce rôle
(Personne::hasRole(), scopé à l'app courante) OU pour gestionnaire/admin
— ce dernier volet ajouté le 05/09/2026 pour amana_web_familles (rôles
equipe_reception/pesee/packaging/chargement, voir EnsureLivraisonRole,
qui accorde déjà l'accès à gestionnaire/admin même sans le rôle
spécifique) : sans lui, un compte gestionnaire qui A accès à ces pages
ne voyait jamais leur lien de sidebar. Toujours SANS enseigner à ce
paquet partagé la moindre notion propre à ces rôles précis — le
mécanisme reste générique, valable pour n'importe quel code de rôle de
n'importe quelle app utilisant amana_shared.

'extra_check' (optionnel) : callable(int $idPersonne, string $role): bool
supplémentaire, testé en OR après hasRole()/hasAtLeastRole('gestionnaire')
si aucun des deux n'a déjà rendu l'entrée visible. Ajouté le 08/09/2026
pour amana_web_familles (rôles equipe_* affectables PAR CAMPAGNE en plus
du rôle global, voir App\Models\CampagneEquipeMembre::estAffecteQuelquePart()
dans ce projet) : une personne peut désormais avoir accès à
livraison.pesee.choisir() sans jamais avoir eu le rôle global
'equipe_pesee' coché — hasRole() seul ne suffit plus à décider qui voit
le lien. Passé comme un tableau [FQCN::class, 'methodeStatique'] plutôt
qu'une Closure : reste sérialisable si ce projet active un jour
`config:cache` (une Closure ferait échouer la commande), et garde ce
paquet partagé totalement ignorant de ce qu'est CampagneEquipeMembre —
il se contente d'appeler ce qu'on lui passe. Optionnel : les entrées de
nav qui n'en ont pas besoin (la grande majorité) n'en spécifient
simplement pas.
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
                <div
                    class="mx-1 mb-2.5 px-[11px] py-[7px] rounded-sm text-[11px] font-semibold flex items-center gap-1.5
                                                                    bg-rose-500/[0.14] text-rose-300 border border-rose-500/[0.22]">
                    🛡️ Administrateur
                </div>
            @elseif(auth()->user()->isGestionnaire())
                <div
                    class="mx-1 mb-2.5 px-[11px] py-[7px] rounded-sm text-[11px] font-semibold flex items-center gap-1.5
                                                                    bg-amber-500/[0.14] text-amber-300 border border-amber-500/[0.22]">
                    ⚙️ Gestionnaire
                </div>
            @elseif(!method_exists(auth()->user(), 'isMembre') || auth()->user()->isMembre())
                {{-- isMembre() doit être testé AVANT isBenevole() : la cascade
                     (membre ⊇ benevole) fait que isBenevole() est vrai pour tout
                     membre, donc l'ordre inverse étiquetait les membres « Bénévole ». --}}
                <div
                    class="mx-1 mb-2.5 px-[11px] py-[7px] rounded-sm text-[11px] font-semibold flex items-center gap-1.5
                                                                    bg-sky-500/[0.14] text-sky-300 border border-sky-500/[0.22]">
                    👤 Membre
                </div>
            @else
                <div
                    class="mx-1 mb-2.5 px-[11px] py-[7px] rounded-sm text-[11px] font-semibold flex items-center gap-1.5
                                                                    bg-emerald-500/[0.14] text-emerald-300 border border-emerald-500/[0.22]">
                    🤝 Bénévole
                </div>
            @endif
        @endauth

        {{-- ── Navigation propre à l'app, depuis config('amana-shared.nav') ──
        Regroupée par section pour permettre le repli indépendant de
        chaque groupe (ajouté le 29/08/2026) — <details>/<summary>,
                même pattern déjà utilisé pour les groupes de filtres (voir
                familles/index.blade.php) plutôt qu'une dépendance JS
                supplémentaire (Alpine n'est utilisé nulle part ailleurs dans
                ces apps). `open` par défaut : pas de persistance demandée,
                les sections repartent dépliées à chaque chargement de page —
                contrairement au repli global de la sidebar sur mobile (voir
                amana_shared_ui/MobileSidebar.vue, qui répond à un besoin
                différent). --}}
                @php
                    // Regroupement par section et règles de visibilité : voir
                    // Services\NavVisibility (partagées avec le point de
                    // terminaison des badges, pour qu'il ne renvoie jamais un
                    // compteur que cette sidebar masquerait).
                    $navVisibility = app(\Amana\Shared\Services\NavVisibility::class);
                    $navSections = $navVisibility->sections(config('amana-shared.nav', []));
                @endphp
                @foreach($navSections as $section => $items)
                    @php $itemsVisibles = $navVisibility->filter($items, auth()->user()); @endphp
                    @continue($itemsVisibles->isEmpty())
                    @if($section === '')
                        @foreach($itemsVisibles as $item)
                            @include('amana-shared::layouts.partials.nav-item', [
                                'item' => $item,
                                'navBadge' => ($navBadges ?? [])[$item['route']] ?? 0,
                            ])
                        @endforeach
                    @else
                        <details class="group mt-3 first:mt-0" open>
                            <summary class="cursor-pointer list-none flex items-center justify-between px-2.5 mb-1 select-none">
                                <span
                                    class="text-[9.5px] font-bold tracking-[1.4px] uppercase text-white/20">{{ $section }}</span>
                                <span
                                    class="text-white/20 text-[9px] transition-transform duration-200 group-open:rotate-180">▾</span>
                            </summary>
                            @foreach($itemsVisibles as $item)
                                @include('amana-shared::layouts.partials.nav-item', [
                                    'item' => $item,
                                    'navBadge' => ($navBadges ?? [])[$item['route']] ?? 0,
                                ])
                            @endforeach
                        </details>
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
                    @elseif(!method_exists(auth()->user(), 'isMembre') || auth()->user()->isMembre()) Membre
                    @else Bénévole
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