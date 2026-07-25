{{-- vendor/amana-shared/resources/views/layouts/partials/flash.blade.php --}}
{{--
    Messages flash de session.
    Inclus automatiquement dans layouts/app.blade.php.
--}}

@if(session('success'))
    <div class="flash-enter flex items-start gap-3 px-4 py-3 rounded-lg mb-5 text-[13.5px] font-medium border
                bg-emerald-50 border-emerald-200 text-emerald-800" role="alert">
        <span>✅</span>
        <span class="flex-1">{{ session('success') }}</span>
        <button onclick="this.parentElement.remove()"
                class="ml-auto bg-transparent border-0 cursor-pointer opacity-50 hover:opacity-100 text-base text-current leading-none p-0 transition-opacity min-w-[44px] min-h-[44px] flex items-center justify-center"
                aria-label="Fermer">×</button>
    </div>
@endif

@if(session('error'))
    <div class="flash-enter flex items-start gap-3 px-4 py-3 rounded-lg mb-5 text-[13.5px] font-medium border
                bg-rose-50 border-rose-200 text-rose-800" role="alert">
        <span>❌</span>
        <span class="flex-1">{{ session('error') }}</span>
        <button onclick="this.parentElement.remove()"
                class="ml-auto bg-transparent border-0 cursor-pointer opacity-50 hover:opacity-100 text-base text-current leading-none p-0 transition-opacity min-w-[44px] min-h-[44px] flex items-center justify-center"
                aria-label="Fermer">×</button>
    </div>
@endif

@if(session('warning'))
    <div class="flash-enter flex items-start gap-3 px-4 py-3 rounded-lg mb-5 text-[13.5px] font-medium border
                bg-amber-50 border-amber-200 text-amber-800" role="alert">
        <span>⚠️</span>
        <span class="flex-1">{{ session('warning') }}</span>
        <button onclick="this.parentElement.remove()"
                class="ml-auto bg-transparent border-0 cursor-pointer opacity-50 hover:opacity-100 text-base text-current leading-none p-0 transition-opacity min-w-[44px] min-h-[44px] flex items-center justify-center"
                aria-label="Fermer">×</button>
    </div>
@endif

@if(session('info'))
    <div class="flash-enter flex items-start gap-3 px-4 py-3 rounded-lg mb-5 text-[13.5px] font-medium border
                bg-sky-50 border-sky-200 text-sky-900" role="alert">
        <span>ℹ️</span>
        <span class="flex-1">{{ session('info') }}</span>
        <button onclick="this.parentElement.remove()"
                class="ml-auto bg-transparent border-0 cursor-pointer opacity-50 hover:opacity-100 text-base text-current leading-none p-0 transition-opacity min-w-[44px] min-h-[44px] flex items-center justify-center"
                aria-label="Fermer">×</button>
    </div>
@endif
