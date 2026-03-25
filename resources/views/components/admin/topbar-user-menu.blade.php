@props([
    'userName' => '',
    'userEmail' => '',
])

{{-- 依赖父级 Alpine（adminDashboard / resellerPortal）提供 userEmail、userMenuOpen --}}
<div class="relative" @click.away="userMenuOpen = false">
    <button type="button"
            class="flex items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm text-slate-300 hover:bg-slate-700/50 hover:text-white"
            @click="userMenuOpen = !userMenuOpen"
    >
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-600/80 text-xs font-medium text-white"
              x-text="(String(userEmail || '').trim().charAt(0) || 'U').toUpperCase()"></span>
        <span class="hidden max-w-[120px] truncate sm:inline" x-text="userEmail || ''"></span>
        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <div x-show="userMenuOpen"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 top-full z-50 mt-1 w-48 rounded-md border border-slate-700 bg-slate-800 py-1 shadow-lg"
         style="display: none;">
        <div x-show="userEmail" class="border-b border-slate-700 px-3 py-2 text-xs text-slate-400 truncate" x-text="userEmail"></div>
        {{ $slot }}
    </div>
</div>
