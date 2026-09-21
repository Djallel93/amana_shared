{{-- vendor/amana-shared/resources/views/layouts/partials/nav-item.blade.php --}}
{{--
Un lien de navigation de la sidebar (avec son badge éventuel). Extrait de
sidebar.blade.php où ce balisage était dupliqué (items sans section /
items dans un <details>) — une seule copie à maintenir.

Variables attendues :
  $item      : entrée de config('amana-shared.nav') (route, label, icon,
               route_pattern…)
  $navBadge  : int — compteur à afficher (0 = pas de badge)
--}}
<a href="{{ route($item['route']) }}"
    class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                                                            {{ request()->routeIs($item['route_pattern'] ?? $item['route']) ? 'nav-item-active bg-accent/15 text-white font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
    onclick="closeSidebar()">
    <span class="text-sm w-[18px] text-center flex-shrink-0">{{ $item['icon'] ?? '•' }}</span>
    <span class="flex-1">{{ $item['label'] }}</span>
    @if($navBadge > 0)
        <span
            class="flex-shrink-0 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-bold leading-none bg-rose-500 text-white"
            aria-label="{{ $navBadge }} en attente">{{ $navBadge > 99 ? '99+' : $navBadge }}</span>
    @endif
</a>
